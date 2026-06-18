# Slice 12 — Hall of Fame page subset

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 12

## Goal

Replace Amiga HoF stub with career + single-game records, ratio leaders, and WC medal panel — no streak rows; profile links to `/amiga/profile.php`.

## Done

- [x] `/amiga/hall-of-fame.php` — two server-records panels + WC medals panel
- [x] `includes/amiga_records_common.php` — render helpers (age markers, rows)
- [x] `includes/amiga_records_hof_links.php` — value links to `/amiga/rating.php` (games/wins/win % columns)
- [x] `includes/amiga_records_ratio_leaders.php` — ratio leaders + `amiga_records_wc_medal_leaders()`
- [x] Omits: peak activity calendar, play-day/week streaks, match streaks
- [x] `amiga-data-contract.md` read path

## Files changed

- `site/public_html/amiga/hall-of-fame.php`
- `site/public_html/includes/amiga_records_common.php` (new)
- `site/public_html/includes/amiga_records_hof_links.php` (new)
- `site/public_html/includes/amiga_records_ratio_leaders.php` (new)
- `docs/amiga-data-contract.md`

## Verification — **STOP GATE F**

**User checkpoint:** open `/amiga/hall-of-fame.php` in browser.

| Check | Expected |
|-------|----------|
| Page loads | 503 only if `amiga_generalstats` empty — run `replay` first |
| Career panel | Most games → Robert S (1520) or current rebuild holder |
| Peak panel | Most goals in one game, biggest win, ratios populated |
| WC panel | Gold/silver/bronze leaders with profile links |
| Holder links | `/amiga/profile.php?id=` |
| No streak rows | No winning/drawing/play streak records |

## Known limitations

- Value deep-links only for metrics with an Amiga rating-table column (games, wins, win %); other wings deferred until `/amiga/leaderboards/*` ships.
- WC panel shows single leader per medal (ties break by name, player_id).

## Next slice hint

**Slice 13:** `/amiga/leaderboards/tournament-honours.php` — **STOP GATE G**.
