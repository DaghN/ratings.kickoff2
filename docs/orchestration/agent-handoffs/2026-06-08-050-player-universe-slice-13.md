# Slice 13 — Tournament honours leaderboard

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 13

## Goal

`/amiga/leaderboards/tournament-honours.php` reads `amiga_player_tournament_totals` only; sortable WC medals + event wins/played.

## Done

- [x] `site/public_html/amiga/leaderboards/tournament-honours.php`
- [x] `includes/amiga_lb_nav.php` — Rating + Tournament honours wings
- [x] `includes/amiga_hub_nav.php` — **Honours** hub tab → tournament honours LB
- [x] `amiga_tournament_honours_leaderboard_rows()` in `amiga_player_tournament_lib.php`
- [x] HoF WC panel link to full leaderboard
- [x] `amiga-data-contract.md` read path

## Files changed

- `site/public_html/amiga/leaderboards/tournament-honours.php` (new)
- `site/public_html/includes/amiga_lb_nav.php` (new)
- `site/public_html/includes/amiga_player_tournament_lib.php`
- `site/public_html/includes/amiga_hub_nav.php`
- `site/public_html/amiga/hall-of-fame.php`
- `docs/amiga-data-contract.md`

## Verification — **STOP GATE G**

**User checkpoint:** `/amiga/leaderboards/tournament-honours.php`

| Agent SQL check (top by default sort) | |
|---------------------------------------|---|
| #1 WC gold | Gianni T — 5 gold, 10 silver, … |
| Sort columns | WC gold/silver/bronze, tournaments won, played |
| Row count | 393 players with `tournaments_played > 0` |

## Known limitations

- Only two LB wings (Rating + Tournament honours); goals/DD/streaks wings deferred per realm vision.
- No inactive/provisional filters (small offline pool; all imported players with events shown).

## Next slice hint

**Slice 14:** Documentation closure — table register Active status, README CLIs, final handoff.
