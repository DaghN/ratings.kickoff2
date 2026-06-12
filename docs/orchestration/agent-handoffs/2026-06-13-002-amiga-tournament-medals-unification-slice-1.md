# Amiga tournament medals unification v2 — slice 1 handoff

**Date:** 2026-06-13  
**Slice:** 1 — Tier D derivation (WC podium → finish 1/2/3)  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

World Cup podium players receive `event_finish_position` 1 / 2 / 3 from existing WC knockout medal logic (`compute_wc_medals_from_standings`).

---

## Checklist

- [x] `compute_tier_d_wc_finish()` in `participation_placement.py` — gold→1, silver→2, bronze→3
- [x] Wired in `derive_event_finish_position` for World Cup tournaments
- [x] PHP: `amiga_participation_compute_tier_d_wc_finish()` + shared `amiga_participation_compute_wc_medals_from_standings()`
- [x] Ops wrapper delegates to participation helper (deduped medal logic)
- [x] Unit tests: final gold/silver, 3rd-place bronze, shared semi bronze, group rank never copied

### Verification

```powershell
python -m unittest scripts.amiga.test_participation_placement scripts.amiga.test_tournament_honours -v
# Ran 45 tests — OK
```

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/participation_placement.py` | `compute_tier_d_wc_finish`; Tier D in `derive_event_finish_position` |
| `scripts/amiga/test_participation_placement.py` | v2 Tier D tests (replaced v1 “WC finish NULL” cases) |
| `site/public_html/includes/amiga_participation_placement.php` | Tier D + WC medals helper |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | Ops medals → participation helper |

---

## Writer / rebuild choice (documented)

**Derivation is live in `derive_event_finish_position`**, which `build_participation_rows_for_tournament` already calls — so the next `participation-rebuild` would write WC finish values for rebuilt tournaments.

**This slice did not run `participation-rebuild`.** Existing DB participation rows still have `event_finish_position = NULL` on WC events (v1 data).

**Slice 2** will backfill existing WC rows from `wc_medal` before totals rewrite. **Slice 4** will update `verify_player_participation.py` to accept WC podium finishes and drop the v1 “WC finish must be NULL” check.

**Do not run full `participation-rebuild` until slice 3+** (totals writers still reference dropped `cup_*` / `podiums` columns).

---

## STOP gate notes

None for slice 1.

---

## Next

**Slice 2** — backfill SQL: `wc_medal` → `event_finish_position` 1/2/3 on existing World Cup participation rows.
