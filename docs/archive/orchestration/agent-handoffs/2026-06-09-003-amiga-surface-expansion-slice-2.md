# Amiga surface expansion — slice 2 handoff

**Date:** 2026-06-09  
**Slice:** 2 — HoF deep links  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Wire HoF ratio/career rows to new LB wings where online has parity (no streak / activity links).

## Checklist

- [x] Extend `amiga_records_hof_links.php` — 20 metrics → wing path + `k2_sort` / `k2_dir`
- [x] Fix rating-wing indices for Amiga Country column (games=4, wins=5, win%=8)
- [x] Update `hall-of-fame.php` — all applicable value cells link to wings (was 3 rows; now 20)
- [x] Ratio rows (DD %, CS %, attack/defense avg, goal ratio) linked — were `-` only

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_records_hof_links.php` | Full metric map + wing paths |
| `site/public_html/amiga/hall-of-fame.php` | `amiga_records_hof_lb_href()` on all linkable rows |
| `PROJECT_MEMORY.md` | Session log |

## Sample hrefs (PHP CLI)

```
most_games      => /amiga/leaderboards/rating.php?k2_sort=4&k2_dir=desc
most_goals      => /amiga/leaderboards/goals.php?k2_sort=4&k2_dir=desc
dd_ratio        => /amiga/leaderboards/double-digits.php?k2_sort=6&k2_dir=desc
most_victims    => /amiga/leaderboards/victims.php?k2_sort=5&k2_dir=desc
peak_rating     => /amiga/leaderboards/peak-rating.php?k2_sort=4&k2_dir=desc
attack_avg      => /amiga/leaderboards/goals.php?k2_sort=6&k2_dir=desc
defense_avg     => /amiga/leaderboards/goals.php?k2_sort=7&k2_dir=asc
```

## Verification

Manual: `/amiga/hall-of-fame.php` — click linked values (e.g. Most goals, DD ratio, Peak rating) → correct wing with column sorted.

No new verify CLI (read-path only).

## Omitted (by design)

- Streak rows — no Amiga streaks wing
- Activity peaks — no Amiga activity wing
- WC medal panel — already links tournament-honours LB

## Next slice

Slice 3 — Top opponents goals + H2H pair page. **STOP GATE B** after slice 3.
