# Amiga surface expansion — slice 5 handoff

**Date:** 2026-06-09  
**Slice:** 5 — Performance rating discovery  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Surface perf rating beyond per-player history sort — profile highlight + leaderboard wing.

## Checklist

- [x] Profile: best event + latest event lines from participation (`games ≥ 2`, non-NULL perf)
- [x] `/amiga/leaderboards/performance-rating.php` — best single-event perf per player
- [x] `amiga_lb_nav.php` — **Perf. rating** wing
- [x] `amiga_perf_rating_column_help()` — shared tooltip text
- [x] `verify-player-participation` — pass
- [x] Spot-check: Christopher D tournament 22 — participation perf = rating_events (2639)

## Tie-break rules (LB)

**Pick each player’s featured event:** highest `performance_rating` → more event games → higher `tournament_id`.

**Leaderboard sort (default):** perf rating DESC → event games DESC → current Elo DESC → `player_id` ASC.

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_player_tournament_lib.php` | `amiga_player_perf_rating_highlight()`, `amiga_lb_performance_rating_rows()` |
| `site/public_html/includes/amiga_performance_rating.php` | `amiga_perf_rating_column_help()` |
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_profile_render_perf_rating_highlight()` |
| `site/public_html/amiga/profile.php` | Load + render highlight |
| `site/public_html/amiga/leaderboards/performance-rating.php` | New wing |
| `site/public_html/includes/amiga_lb_nav.php` | Wing tab |
| `docs/amiga-performance-rating.md`, `docs/amiga-profile-v0.md`, `PROJECT_MEMORY.md` | Read paths |

## Verification

```
python -m scripts.amiga verify-player-participation
OK: player participation verified (4517 participation rows, 473 player totals)
```

**LB leaders (sample):** Gianni T 2878 (WC XIV), Fabio F 2766, Dagh N 2682.

## STOP GATE D — user browser checks

1. **Profile** — `/amiga/profile.php?id=66`: Performance rating section with best/latest; NULL-only players omit section.
2. **Perf LB** — `/amiga/leaderboards/performance-rating.php`: sorts, player/event links, wing nav.
3. **NULL display** — history rows with 1 game or perfect records show em dash on player-tournaments (unchanged).

## Next slice

Slice 6 — Profile moments block. **STOP GATE E** after slice 6.
