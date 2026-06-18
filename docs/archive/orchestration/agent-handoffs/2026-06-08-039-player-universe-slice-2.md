# Slice 2 — totals rebuild + replay wire

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 2  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §5.3

## Goal

Career rollups in `amiga_player_tournament_totals`; full `replay` populates participation + totals automatically.

## Done

- [x] `rebuild_all_participation_totals(conn, *, dry_run=False)` in `player_tournament_participation.py`
- [x] Truncate + `INSERT … SELECT GROUP BY player_id` from participation
- [x] `wc_*` from `wc_medal` (all zero until slice 5); `cup_*` from non-WC `is_cup` + overall position 1/2/3
- [x] `podiums`, `last_event_date`, `last_tournament_id` (chrono/date ordering)
- [x] `replay.py` after standings: participation → totals → catalog_stats
- [x] CLI `python -m scripts.amiga participation-rebuild` (participation + totals)

## Files changed

- `scripts/amiga/player_tournament_participation.py`
- `scripts/amiga/replay.py`
- `scripts/amiga/__main__.py`

## Verification — STOP GATE A

| Command / check | Result |
|-----------------|--------|
| `python -m scripts.amiga replay` | pass — **~21s** |
| `python -m scripts.amiga verify-rating-events` | pass — 603 tournaments, 4517 rating events |
| `python -m scripts.amiga verify-chronology` | pass — 27418 games, 0 backward transitions |
| `participation_rows` | 3122 |
| `totals_rows` | 329 |
| `SUM(tournaments_played)` | **3122** (= participation_rows) |
| Unit tests (slice 1) | pass — 2/2 |

Replay log excerpt: participation 3122 rows → totals 329 players.

## Known limitations

- `wc_gold/silver/bronze` all zero (`wc_medal = 'none'` until slice 5).
- No `verify-player-participation` CLI until slice 3.
- Profile still reads standings path until slice 4.

## Next slice hint

**Slice 3:** `verify-player-participation` CLI with contract §8 parity gates. No STOP gate.
