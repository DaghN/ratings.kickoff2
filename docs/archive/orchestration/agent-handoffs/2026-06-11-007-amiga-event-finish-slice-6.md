# Amiga event finish — slice 6 handoff

**Date:** 2026-06-11  
**Slice:** 6 — PHP parity  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Live/post-game PHP path matches Python derivation for `event_finish_position`, `best_knockout_phase`, honours totals, and WC medals.

---

## Checklist

- [x] `includes/amiga_participation_placement.php` — Tier A/B/C/D + `derive_best_knockout_phase`; `amiga_participation_is_winner` uses finish + WC gold
- [x] `amiga/ops/includes/amiga_post_game_participation.php` — INSERT new columns; totals SQL (podiums, cup_*); WC shared semi bronze
- [x] PHP syntax lint on touched files
- [x] Smoke: `amiga_ops_participation_refresh_tournament` tournament **544** (Bournemouth II) — **0 diffs** vs Python rebuild
- [x] `participation-rebuild` + verify suite OK

---

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_participation_placement.php` | Full tier parity port from Python slices 1–4 |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | Writers, totals agg, WC medals |

---

## Smoke (tournament 544)

```
PHP refresh tournament 544: rows=7 totals=7 diffs=0
```

Dagh (73): `event_finish_position=2`, `best_knockout_phase=Final` — unchanged after PHP refresh.

---

## Verify suite

```
OK: player participation verified (4517 participation rows, 473 player totals)
OK: 27418 games, 0 backward game_date transitions
OK: rating events verified (603 finalized tournaments, 4517 rating_event rows)
```

---

## Next slice

**Slice 7 — UI read paths:** profile and tournament history show `event_finish_position`; WC unchanged (medal only). **STOP GATE C** after slice 7.

Legacy `overall_position` still written until **slice 8**.
