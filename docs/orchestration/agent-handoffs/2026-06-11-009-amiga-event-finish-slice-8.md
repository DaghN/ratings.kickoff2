# Amiga event finish — slice 8 handoff

**Date:** 2026-06-11  
**Slice:** 8 — Drop `overall_position`  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Remove legacy `overall_position` column and all product code references.

---

## Checklist

- [x] Migration `scripts/amiga/sql/018_drop_overall_position.sql` (applied local `ko2amiga_db`)
- [x] Fresh `010_player_tournament_participation.sql` — no `overall_position`
- [x] Python writers + `derive_participation_positions` removed
- [x] PHP writers + legacy placement helpers removed
- [x] `verify-player-participation` extended (no finish `= 0`; WC finish NULL)
- [x] `participation-rebuild` — 4517 rows
- [x] Full verify suite OK

---

## Files changed

| Area | Files |
|------|--------|
| Schema | `018_drop_overall_position.sql`, `010_player_tournament_participation.sql` |
| Python | `player_tournament_participation.py`, `participation_placement.py`, `tournament_honours.py`, tests, `verify_player_participation.py` |
| PHP | `amiga_post_game_participation.php`, `amiga_participation_placement.php` |
| Docs | `amiga-data-contract.md`, `amiga-player-universe-contract.md`, `amiga-profile-v0.md`, `scripts/amiga/README.md` |

---

## Verify output

```
OK: verify-chronology
OK: verify-rating-events
OK: player participation verified (4517 participation rows, 473 player totals)
OK: verify-player-matchups
38 unit tests OK
```

---

## Deploy note

Staging/prod need `018` applied **before** syncing PHP/Python writers that no longer INSERT `overall_position`.

---

## Next

**Slice 9** — Tier E override table (empty hook). **Slice 10** — documentation closure + Part B registers.
