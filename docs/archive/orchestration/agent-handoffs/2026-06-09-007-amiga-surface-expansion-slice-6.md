# Amiga surface expansion — slice 6 handoff

**Date:** 2026-06-10  
**Slice:** 6 — Profile moments block  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Trophy games from `amiga_player_stats` `*GameID` pointers — single-game fetches only (no `amiga_games` table scans).

## Checklist

- [x] `amiga_player_moments_lib.php` — load `BiggestWinGameID`, `MostGoalsScoredGameID`, `PeakRatingGameID` + batch IN fetch for game rows
- [x] `amiga_profile_render_moments()` — `pm3-moment` / `pm3-moments` classes (`player-feast.css` already on profile)
- [x] Three card types only: biggest win, goal festival, peak rating game — **no streak card**
- [x] Score links to `/amiga/games.php?id={player}` with `opponent=` when known
- [x] CLI spot-check: Oliver St (345) goal festival **26–0** vs Thomas Kl (game 21315)

## Data note

`PeakRatingGameID` is **NULL for all players** in current `ko2amiga_db` replay — peak rating game card will not render until replay writes that pointer. Biggest win + goal festival cards work (often same game ID).

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_player_moments_lib.php` | New loader + parse helpers |
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_profile_render_moments()` |
| `site/public_html/amiga/profile.php` | Load + render after perf highlight |
| `docs/amiga-profile-v0.md`, `PROJECT_MEMORY.md` | Read paths |

## Verification

```
Player 345: biggest_win 26–0 vs Thomas Kl; goal_festival 26–0 vs Thomas Kl
Player 73: 20–0 vs Gianluca P (both cards)
```

Page load: 1 stats row + 1 batched game query (≤3 IDs), no full-table scan.

## STOP GATE E — user browser checks

1. **Oliver St** — `/amiga/profile.php?id=345`: Moments section with **26–0** vs Thomas Kl; score links to games filtered by opponent.
2. **Dagh N** — `/amiga/profile.php?id=73`: **20–0** goal festival card.
3. **Busy player** — e.g. Gianni T (`id=149`): two moment cards; section hidden when no valid game IDs.

## Next slice

Slice 7 — Honours LB podiums/cups + history filters + recent-tournament enrich. **STOP GATE F** after slice 7.
