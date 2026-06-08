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

## Commit

*(hash after push)*
