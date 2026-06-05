# Cutover packet template (for Steve)

Copy this section into email/chat when a release needs **prod database + server job** changes. Fill every `[bracket]`. Attach or link Git paths.

**Forward cutover (Jun 2026):** Schema DDL lives in **`site/public_html/ops/sql/migrations/`**; apply via **`php ops/run_prepare.php migrate-work`** on the target DB (staging work = **`kooldb1`**). Website aggregates = **`ops/run_ops_sim.php`** — not batch `REP-xxx` on legacy **`kooldb`**. Live post-game target = **PHP `ops/dispatch.php`** — see [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md).

---

## Summary

**Release name:** [e.g. Profile indexes + replay parity]

**Goal:** [One sentence — what players will see]

**Risk:** [Low / medium — replay duration, downtime?]

---

## Preconditions

- [ ] Dagh confirmed staging: schema + replay (if any) + PHP
- [ ] Backup / maintenance window: **[Steve decides]**

---

## 1. Schema (production DB)

Apply in order:

| # | File | Register ID |
|---|------|-------------|
| 1 | `site/public_html/ops/sql/migrations/[NNN_….sql]` | SCH-… |

```bash
# Preferred — after WinSCP sync of ops/
php site/public_html/ops/run_prepare.php migrate-work --target staging-work

# Manual equivalent (Steve adjusts host/user/DB — work DB = kooldb1)
mysql -u … kooldb1 < site/public_html/ops/sql/migrations/001_ratedresults_player_indexes.sql
```

**Verify:** `[e.g. SHOW INDEX FROM ratedresults WHERE Key_name LIKE 'idx_ratedresults_id%']`

---

## 2. Replay (if applicable)

**Needed?** [ Yes / No ]

| Parameter | Value |
|-----------|--------|
| Tool | [ reviewed production replay wrapper / Steve C++ to spec ] |
| K-factor | 32 |
| Start rating | 1600 |
| Order | `Date ASC`, `id ASC` |
| Tables | `ratedresults`, `playertable`, `generalstatstable` |

**Spec:** `docs/replay-v1-scope-and-reset.md`

**Command (Python path):**

```bash
cd /path/to/public_html
bash run_PROD_WRAPPER_TBD.sh
```

**Expected:** exit 0; log ends with `replay_all complete`, `generalstatstable id=1 updated`.

**Verify:** `[row counts, spot-check player id=237 rating]`

---

## 3. Post-game PHP ops (live games at cutover)

**Deploy needed?** [ Yes / No ]

**Handoff style:** Wire **`ops/dispatch.php` `CMD=ProcessCompletedGame`** (+ midnight **`FinalizeUtcDay`**) per **[`steve-live-ops.md`](../../site/public_html/ops/docs/steve-live-ops.md)**. Rules in **[`website-data-contract.md`](../website-data-contract.md)** — § Post-game derived-data behavior. Retire legacy **C++ derived** post-game; do not extend C++.

| Area | Doc |
|------|-----|
| Aggregate tables | `website-data-contract.md` (infer upserts from post-game rules + `*_rebuild.sql` parity) |
| Records / Hall of Fame | [`records-post-game-exception.md`](records-post-game-exception.md) + [`staging-post-game-record-defects.md`](../staging-post-game-record-defects.md) |

**Reference excerpt:** `docs/ratings_cpp.txt`

---

## 4. Periodic jobs

| Register ID | Action |
|-------------|--------|
| PER-… | [new/changed job — see [`periodic-register.md`](periodic-register.md)] |

---

## 5. PHP / site

**Deploy:** [ WinSCP / git pull / other ]

**Paths:** `site/public_html/` → server `public_html/`

---

## 6. Smoke checks

- [ ] `player/profile.php?id=[heavy player]` loads in ~1s (if indexes)
- [ ] Ranked page sort by rating sane
- [ ] `[other]`

---

## 7. Rollback (if discussed)

[Steve / Dagh notes — restore backup? re-run old replay?]

---

## Register updates after prod

Dagh will mark **L5** in `docs/coordination/*-register.md` and append replay run log.
