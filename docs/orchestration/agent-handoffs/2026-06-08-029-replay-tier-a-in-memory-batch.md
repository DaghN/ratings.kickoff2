# Replay Tier A — in-memory batch finalize

**Date:** 2026-06-08  

## Goal

Batch replay: incremental in-memory career state per tournament; full-history only for network + peak at end.

## Done

- [x] `finalize_tournament(..., players=, names=, persist_player_stats=, defer_heavy_derived=)`
- [x] `commit_heavy_player_derived(conn, players=)` — uses accumulated memory, not DB reload
- [x] `replay.py` — shared `players` + names once; batched `executemany` game ratings
- [x] `refinalize-from` — load players after `rebuild_stats_through_finalized`, same batch path
- [x] Skip per-tournament verify when `defer_heavy_derived` (oracle: `verify-rating-events`)
- [x] Live CLI / PHP unchanged (defaults)

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga replay` (full) | ~23s local |
| `python -m scripts.amiga verify-rating-events` | pass |
| `python -m scripts.amiga refinalize-smoke` | pass |

## Live finalize latency (tail end, local Jun 2026)

| Event | Games | `finalize_tournament` (live path) |
|-------|------:|----------------------------------:|
| Last in catalog — World Cup XXIII (`id=25`) | 331 | ~0.76s |
| Small late event (`id=15`) | 18 | ~0.68s |

Dominated by one full-history network-count scan, not in-event game count.

## Commit

`0fcc8d7` — pushed to `main`
