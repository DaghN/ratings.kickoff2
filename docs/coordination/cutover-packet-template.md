# Cutover packet template (for Steve)

Copy this section into email/chat when a release needs **prod database + server job** changes. Fill every `[bracket]`. Attach or link Git paths.

---

## Summary

**Release name:** [e.g. Profile indexes + replay parity]

**Goal:** [One sentence — what players will see]

**Risk:** [Low / medium — replay duration, downtime?]

---

## Preconditions

- [ ] Dagh confirmed staging: schema + replay (if any) + PHP
- [ ] **Rating fade (hourly) stopped** — required before this cutover
- [ ] Backup / maintenance window: **[Steve decides]**

---

## 1. Schema (production DB)

Apply in order:

| # | File | Register ID |
|---|------|-------------|
| 1 | `schema/migrations/[NNN_….sql]` | SCH-… |

```bash
# Example — Steve adjusts paths
mysql -u … kooldb < schema/migrations/001_ratedresults_player_indexes.sql
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
| Decay | Off |
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

## 3. Post-game C++ (future games)

**Deploy needed?** [ Yes / No ]

**Handoff style:** Steve merges **insert snippets** from our repo (option 2, May 2026) — not prose-only.

| Register ID | Snippet pack (attach or link) |
|-------------|--------------------------------|
| PG-… | `docs/coordination/cpp-snippets/PG-NNN-….md` |

For each pack, Steve gets:

- **Insert instruction** (where in `RatingProcedureUnity` flow)
- **C++ code block(s)** to paste
- **Data contract** (tables/columns)
- **Replay parity** note (Python already does same for history)

**Reference excerpt:** `docs/ratings_cpp.txt`  
**How we write packs:** `docs/coordination/post-game-cpp-handoff.md`

---

## 4. Periodic jobs

| Register ID | Action |
|-------------|--------|
| PER-001 | Fade **OFF** (already done in preconditions?) |
| PER-… | [new/changed job] |

---

## 5. PHP / site

**Deploy:** [ WinSCP / git pull / other ]

**Paths:** `site/public_html/` → server `public_html/`

---

## 6. Smoke checks

- [ ] `individual1.php?id=[heavy player]` loads in ~1s (if indexes)
- [ ] Ranked page sort by rating sane
- [ ] `[other]`

---

## 7. Rollback (if discussed)

[Steve / Dagh notes — restore backup? re-run old replay?]

---

## Register updates after prod

Dagh will mark **L5** in `docs/coordination/*-register.md` and append replay run log.
