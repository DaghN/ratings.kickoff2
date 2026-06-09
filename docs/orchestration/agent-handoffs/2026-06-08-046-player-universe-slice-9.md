# Slice 9 — verify-player-matchups CLI

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 9

## Goal

CLI parity gate for `amiga_player_matchup_summary` vs raw `amiga_games`.

## Done

- [x] `scripts/amiga/verify_player_matchups.py`
- [x] Registered as `verify-player-matchups` in `__main__.py`
- [x] `scripts/amiga/test_verify_player_matchups.py`

## Checks implemented

| Check | Rule |
|-------|------|
| Global parity | `SUM(games) = 2 × COUNT(amiga_games)` |
| W-D-L | `games = wins + draws + losses` on every row |
| Coverage | every directed game pair has a summary row |
| Orphans | no summary row without games |
| Mirror | `(A,B)` stats mirror `(B,A)` (wins↔losses, goals swapped) |
| Spot-check | top 12 pairs by `games` recomputed from `amiga_games` |

## Files changed

- `scripts/amiga/verify_player_matchups.py` (new)
- `scripts/amiga/test_verify_player_matchups.py` (new)
- `scripts/amiga/__main__.py`

## Verification — **STOP GATE D**

```powershell
python -m scripts.amiga verify-player-matchups
```

| Command | Result |
|---------|--------|
| `verify-player-matchups` | OK — 14024 pairs, SUM(games)=54836 = 2×27418 |
| `python -m unittest scripts.amiga.test_verify_player_matchups` | pass |

## Known limitations

- Full verify can take ~2–3 minutes locally (pair-coverage + mirror joins over full history).
- Live incremental matchup upsert still not wired (finalize path).

## Next slice hint

**Slice 10:** Profile top opponents block + `amiga_player_top_opponents()` — **STOP GATE E**.
