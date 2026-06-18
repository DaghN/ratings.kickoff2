# Amiga surface expansion — slice 1 handoff

**Date:** 2026-06-09  
**Slice:** 1 — Tier A leaderboard wings  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Ship four thin career-stat wings under `/amiga/leaderboards/` reading `amiga_player_stats` only; move rating wing; extend wing nav.

## Checklist

- [x] `goals.php`, `double-digits.php`, `victims.php`, `peak-rating.php` — column parity with online wings
- [x] `leaderboards/rating.php` — rating table + wing nav; `/amiga/rating.php` → 302 redirect
- [x] `includes/amiga_lb_nav.php` — Rating, Goals, DDs & CSs, Victims, Peak rating, Tournament honours (no streaks)
- [x] `includes/amiga_lb_lib.php` — `amiga_lb_player_where_sql()` + `amiga_player_base_from_sql()`
- [x] `amiga_hub_nav.php` — Ladder tab → `/amiga/leaderboards/rating.php`
- [x] Full verify suite — pass
- [x] Spot-check SQL default sorts (GoalsFor, DoubleDigits, DifferentVictims, PeakRating DESC + Rating tie-break)

## Files changed

| File | Change |
|------|--------|
| `site/public_html/amiga/leaderboards/rating.php` | New — rating wing home |
| `site/public_html/amiga/leaderboards/goals.php` | New |
| `site/public_html/amiga/leaderboards/double-digits.php` | New |
| `site/public_html/amiga/leaderboards/victims.php` | New |
| `site/public_html/amiga/leaderboards/peak-rating.php` | New |
| `site/public_html/amiga/rating.php` | 302 redirect to leaderboards/rating |
| `site/public_html/includes/amiga_lb_nav.php` | Six wings |
| `site/public_html/includes/amiga_lb_lib.php` | New shared WHERE |
| `site/public_html/includes/amiga_hub_nav.php` | Ladder href + comment |
| `docs/amiga-profile-v0.md`, `PROJECT_MEMORY.md` | Routes |

## Verification

```
python -m scripts.amiga verify-chronology
OK: 27418 games, 0 backward game_date transitions

python -m scripts.amiga verify-rating-events
OK: rating events verified (603 finalized tournaments, 4517 rating_event rows)

python -m scripts.amiga verify-player-participation
OK: player participation verified (4517 participation rows, 473 player totals)

python -m scripts.amiga verify-player-matchups
OK: player matchups verified (14024 directed pairs, SUM(games)=54836 = 2×27418)
```

**Sort spot-check (ko2amiga_db):** Goals leader Gianni T (8736); DD leader Gianni T (302); Victims leader Robert S (173); Peak Gianni T (2613).

## STOP GATE A — user browser checks (required before slice 2)

1. **Profile honours (slice 0):** `/amiga/profile.php?id=149` — honours strip visible with WC medals and links.
2. **Rating wing:** Hub **Ladder** tab and `/amiga/rating.php` both land on rating table with wing tabs.
3. **Each new wing loads and sorts:**  
   - `/amiga/leaderboards/goals.php`  
   - `/amiga/leaderboards/double-digits.php`  
   - `/amiga/leaderboards/victims.php`  
   - `/amiga/leaderboards/peak-rating.php`  
   Click a player name → profile. Try column sort on one metric per wing.
4. **Wing nav:** From any wing, switch to Tournament honours and back.
5. **Honours hub tab:** Still opens tournament-honours wing.

## Next slice

Slice 2 — HoF deep links to new LB wings (no STOP gate).
