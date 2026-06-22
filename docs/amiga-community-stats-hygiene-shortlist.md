# Amiga community stats — hygiene shortlist (recentering)

**Purpose:** Single checklist before adding **new fact grains** or chart read paths on `amiga_community_*`. Use when context gets noisy mid-slice.

**Authority:** [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) · [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) · v1 **shipped** Jun 2026 (`034` + `035`).

**Repair / corrections:** `python -m scripts.amiga prove` only — no reopen/refinalize. See [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md).

---

## What is already solid (do not re-litigate)

| Area | Gate |
|------|------|
| Python holy loop | `replay` → `persist_community_for_tournament` each finalize |
| Stored headline + facts | `verify-community-stats` in `prove` (605 snapshots; multi-event oracle) |
| PHP **build** math | `verify-php-community-parity` (sample tournaments + T24) |
| HoF vs community split | `035` — aggregates off `amiga_generalstats` / `amiga_realm_snapshots` |
| Activity present + TT headline | `amiga_activity_summary.php` → `amiga_community_headline_load` |
| Staging export | `export_ko2amiga_db.ps1` dumps all three community tables |

---

## `verify_php_finalize_parity` — intentional absence (not a bug)

**Yes — this is the refinalize retirement.**

Jun 2026 we removed reopen / refinalize / warm-through because single-tournament reopen+finalize could corrupt cumulative derived state (T24 roster bug). **`verify-php-finalize-parity` was removed with that path** — it mutated DB via reopen+finalize on T24.

| Doc | Correct reading |
|-----|-----------------|
| [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md) | Lists `verify-php-finalize-parity` as removed |
| [`amiga-stored-field-semantics-plan.md`](amiga-stored-field-semantics-plan.md) Phase D | **Retired** — use `prove` |
| `PROJECT_MEMORY` / `amiga-stored-field-semantics.md` | Were stale (“in prove”) — corrected when this shortlist landed |

**Do not restore** reopen/refinalize parity as the primary gate. If we need PHP **finalize persist** confidence, add a **non-mutating** probe (build-only, or compare after full `prove` on a throwaway DB) — not T24 reopen in `prove`.

---

## Suggested hygiene work (priority order)

Do these **before** spec+implement new `amiga_community_stat_facts` grains.

### P0 — Safety nets for schema changes

| # | Item | Why | Deliverable |
|---|------|-----|-------------|
| 1 | **`community-stats-rebuild` repair CLI** | Same class as `generalstats-rebuild`; needed when fact logic changes | `python -m scripts.amiga community-stats-rebuild` — full recompute present + all snapshots + facts; compare to stored |
| 2 | **Stronger `verify-community-stats` SQL guards** | Oracle only samples 3–4 events today | All snapshots have ≥1 fact row; no orphan facts; snapshot timeline cols match `tournaments` |
| 3 | **Registry parity unit test** | First thing to drift when adding grains | Python `COMMUNITY_HEADLINE_COLUMNS` + `V1_FACT_SPECS` == PHP registry constants |

### P1 — PHP confidence (without refinalize)

| # | Item | Why | Deliverable |
|---|------|-----|-------------|
| 4 | **PHP persist parity (optional)** | `verify-php-community-parity` tests **build** only; staging uses PHP ops finalize | Oneoff or verify step: compare stored community rows after PHP finalize on fixed tournament **or** document “staging smoke = import after Python `prove` export” |
| 5 | **`verify-php-community-parity` fail if PHP missing** | Currently soft-skips (exit 0) when Laragon PHP not found | Fail on Windows dev / env `AMIGA_REQUIRE_PHP=1` |

### P2 — Code + doc hygiene

| # | Item | Why | Deliverable |
|---|------|-----|-------------|
| 6 | **Doc sweep** | Agents re-read wrong instructions | `amiga-realm-snapshot-policy.md` §4/6/7 (aggregates moved); implementation plan `- [ ]` → done; remove refinalize tasks from community plan |
| 7 | **Dead code removal** | Confusion after `035` | Delete unused `_merge_game_aggregates` (Python) and `amiga_realm_merge_game_aggregates` (PHP) |
| 8 | **`verify-php-community-parity` in `__main__.py`** | Convenience only | CLI mirror of `prove` step |

### P3 — Defer until feature needs them

| Item | Wait for |
|------|----------|
| `amiga_community_facts_query()` read helper | Chart / JSON API slice |
| Activity TT “since {date}” label scoped to cutoff | TT polish |
| Edit `013`/`027` CREATE to omit aggregates (not only `035` DROP) | Schema cosmetic pass |
| Chart APIs | Grain spec locked in registry + policy addendum |

---

## Explicitly not required before new grains

- Dual-write on realm snapshots (removed)
- Incremental community compute path (always full scan at cutoff — one path)
- Restoring `refinalize.py` or `verify_php_finalize_parity`
- `all_time` + `realm` fact rows in v1 registry (by design — spec explicitly if adding)

---

## Sign-off ritual (unchanged)

```powershell
python -m scripts.amiga prove
```

Full nuclear loop ~5–15 min. Any ground-truth edit to a finalized tournament → **prove**, not partial repair.

After hygiene slices: re-run `prove` + spot-check `/amiga/activity.php` summary vs `SELECT * FROM amiga_community_stats WHERE id=1`.

---

## Related archives (historical only)

- Refinalize retirement: [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md)
- Old plans mentioning `refinalize` / `verify-php-finalize-parity`: treat as archive context

*Shortlist created Jun 2026 — update when a P0/P1 item ships or new grains land.*
