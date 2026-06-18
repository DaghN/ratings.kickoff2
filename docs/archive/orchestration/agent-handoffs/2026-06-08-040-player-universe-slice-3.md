# Slice 3 — verify-player-participation CLI

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 3  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §8 parity gates

## Goal

Automate participation + totals parity checks; exit 1 on failure with first 20 errors.

## Done

- [x] `scripts/amiga/verify_player_participation.py`
- [x] Registered as `python -m scripts.amiga verify-player-participation`
- [x] Checks:
  - participation ⊆ games (each row has ≥1 game)
  - overall standings ⊆ participation
  - rating columns match `amiga_rating_events` when event exists; no orphan rating fields
  - per-player `tournaments_played` = participation row count
  - totals row count = distinct players with participation
  - no totals without participation / participation players missing totals
  - `SUM(tournaments_played)` = total participation rows

## Files changed

- `scripts/amiga/verify_player_participation.py` (new)
- `scripts/amiga/__main__.py`

## Verification

| Command | Result |
|---------|--------|
| `python -m scripts.amiga verify-player-participation` | pass — 3122 participation, 329 totals |

## Known limitations

- Does not validate `wc_medal` / cup medal semantics (slice 5).
- Does not check participation ⊇ games-only players without overall standing (standings authority is v1 source).

## Next slice hint

**Slice 4:** PHP `amiga_player_tournament_lib.php` + profile recent tournaments switch. **STOP GATE B** — browser profile check.
