# Online Track O5-lite — Status year defer + first_games memo

**Track:** Status read-path perf · **Date:** 2026-07-04 · **Realm:** online

## Summary

Two read-time fixes for `status.php` first paint:

1. **Request memo** — `k2_league_load_first_games()` caches by `(periodStart, periodEnd, maxDate)` within one PHP request (points + activity tiebreaker dedupe).
2. **Defer current year** — `k2_status_build_period_competitions()` skips full year Activity + Points on server; `status-period-competitions.js` prewarm fetches year via existing JSON APIs (~300 ms after load). Prewarm queue prioritizes year before day/month.

## Performance

| Metric | Before | After |
|--------|-------:|------:|
| `build_period_competitions` | ~530–760 ms | **~98 ms** |
| Curl `/status.php` | ~0.40 s (census) | **~0.14–0.16 s** |

Year tab: instant after prewarm; ~350–700 ms if clicked before warm completes (same as archive year today).

## Files

- `site/public_html/includes/league_standings.php` — first_games memo
- `site/public_html/includes/status_queries.php` — year stub in period bundle
- `site/public_html/js/status-period-competitions.js` — year-first prewarm sort

## Verify

```powershell
php scripts/oneoff/status_load_breakdown_probe.php
curl http://ratingskickoff.test/status.php
```

Browser: open Status → wait 1 s → Year tab should populate without spinner.