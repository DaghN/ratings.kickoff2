# Slice 7 — Wire tournament finalize (participation + totals)

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 7

## Goal

After live finalize + standings refresh, participation and career totals stay current without a full replay.

## Done

- [x] `rebuild_standings_for_tournament(conn, tournament_id)` in `tournament_standings.py`
- [x] `refresh_catalog_stats_for_tournament(conn, tournament_id)` in `tournament_catalog_stats.py`
- [x] `refresh_tournament_participation_stack(conn, tournament_id, skip_standings=…)` orchestrates live path
- [x] Python `finalize_tournament.py`: after commit when `not defer_heavy_derived`, runs full stack then existing verify
- [x] PHP `amiga_post_game_participation.php` + hook in `finalize_tournament.php` after commit (standings already applied in txn)
- [x] CLI `participation-refresh-tournament --tournament-id=N [--skip-standings]`
- [x] `amiga-data-contract.md` table register: participation + totals writers

## Files changed

- `scripts/amiga/tournament_standings.py`
- `scripts/amiga/tournament_catalog_stats.py`
- `scripts/amiga/player_tournament_participation.py`
- `scripts/amiga/finalize_tournament.py`
- `scripts/amiga/__main__.py`
- `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` (new)
- `site/public_html/amiga/ops/modules/finalize_tournament.php`
- `docs/amiga-data-contract.md`

## Verification

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga verify-player-participation` | pass — 3964 / 393 |
| `python -m unittest scripts.amiga.test_player_tournament_incremental …` | pass (7 tests) |
| `python -m scripts.amiga participation-refresh-tournament --tournament-id=23 --skip-standings` | 11 participation + 11 totals |
| `python -m scripts.amiga participation-refresh-tournament --tournament-id=603 --skip-standings` | 32 WC supplement + 3 medals + 32 totals |
| `php -l` on new PHP files | pass |

Batch `replay` / `refinalize` still use `defer_heavy_derived=True` and global participation rebuild at end — unchanged.

## STOP GATE C (user)

1. `python -m scripts.amiga verify-player-participation` — should print OK.
2. Optional live smoke: finalize one generated tournament (kitchen marathon or fixture path), then re-run verify and spot-check player profile recent tournaments for a participant.

## Known limitations

- PHP `refinalize_tournament.php` batch path does not call participation refresh per tournament (relies on Python refinalize global rebuild).
- Participation refresh runs after finalize lock release (separate short transactions).

## Next slice hint

**Slice 8:** H2H schema + `amiga_player_matchup_summary` bulk rebuild.
