# Amiga surface expansion — slice 8 handoff (track closure)

**Date:** 2026-06-10  
**Slice:** 8 — Documentation closure  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Register shipped surfaces; point deferred work to overview §4.

## Checklist

- [x] `docs/amiga-profile-v0.md` — blocks, routes, files, deferred vs shipped
- [x] `docs/amiga-player-universe-contract.md` §4 — surfaces register (shipped/deferred)
- [x] `docs/amiga-realm-vision.md` — Tier A wings + surface expansion complete
- [x] `docs/amiga-performance-rating.md` — recent-tournament Perf suffix read path
- [x] `docs/amiga-surface-expansion-overview.md` — status complete, inventory gap updated
- [x] Full verify suite — all pass

## Track summary (slices 0–8)

| Slice | Deliverable |
|-------|-------------|
| 0 | Profile honours strip |
| 1 | Tier A LB wings + `amiga_lb_nav` |
| 2 | HoF → LB deep links |
| 3 | Top opponents goals + `/amiga/h2h.php` |
| 4 | Tournament event-stats tab |
| 5 | Perf rating profile highlight + LB wing |
| 6 | Profile moments (`*GameID`) |
| 7 | Honours LB cup/podiums; history filters; recent enrich |
| 8 | Documentation closure (this handoff) |

## Verification (full suite)

```
python -m scripts.amiga verify-chronology
OK: 27418 games, 0 backward game_date transitions (canonical and insert order)

python -m scripts.amiga verify-rating-events
OK: rating events verified (603 finalized tournaments, 4517 rating_event rows)

python -m scripts.amiga verify-player-participation
OK: player participation verified (4517 participation rows, 473 player totals)

python -m scripts.amiga verify-player-matchups
OK: player matchups verified (14024 directed pairs, SUM(games)=54836 = 2×27418)
```

## Known limitations → overview §4

- `PeakRatingGameID` not written by replay — peak-rating **game** moment card empty until writer added
- `amiga_player_tournament_slice_totals`, tournament Games tab, activity charts, live incremental H2H/generalstats — deferred
- No match streak surfaces (product lock)

## Docs updated (slice 8)

`amiga-profile-v0.md`, `amiga-player-universe-contract.md`, `amiga-realm-vision.md`, `amiga-performance-rating.md`, `amiga-surface-expansion-overview.md`, `amiga-surface-expansion-implementation-plan.md`, `PROJECT_MEMORY.md`

## Next work

Pick from [`amiga-surface-expansion-overview.md`](../../amiga-surface-expansion-overview.md) §4 Potential or general Amiga/product backlog in [`amiga-realm-vision.md`](../../amiga-realm-vision.md).
