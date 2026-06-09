# Slice 4 — PHP read path + profile switch

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 4  
**Contract:** [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §4 surfaces register

## Goal

Profile “Recent tournaments” reads `amiga_player_tournament_participation` (canonical) with public lifecycle filter.

## Done

- [x] `site/public_html/includes/amiga_player_tournament_lib.php`
  - `amiga_player_tournament_participation_recent($con, $playerId, $limit = 5)`
  - `amiga_player_tournament_totals_row($con, $playerId)`
- [x] `amiga_player_recent_tournaments()` delegates to participation helper (backward-compatible wrapper)
- [x] Public visibility via `amiga_tournament_public_visibility_where('t')`
- [x] Order: `event_chrono DESC`, `event_date DESC`, `tournament_id DESC`
- [x] `knockout_ties` subquery preserved for bracket deep links

## Files changed

- `site/public_html/includes/amiga_player_tournament_lib.php` (new)
- `site/public_html/includes/amiga_tournament_lib.php`
- `site/public_html/includes/amiga_profile_blocks.php` (docblock)

## Verification — STOP GATE B

| Check | Result |
|-------|--------|
| `python -m scripts.amiga verify-player-participation` | pass |
| Browser `/amiga/profile.php?id=386` (Robert S) | Recent tournaments: Nottingham II 8th, Preston I 7th, … links present |
| Browser `/amiga/tournament.php?id=23` (Nottingham II) | Standings load; Robert S in table |

## Known limitations

- Profile still shows position + points only (no rating delta / wc_medal yet).
- `amiga_player_tournament_totals_row` not used on profile hero yet.
- Full paginated tournament history deferred (contract D5).

## Next slice hint

**Slice 5:** WC medal derivation in participation rebuild + totals refresh. Optional honours parity CLI.
