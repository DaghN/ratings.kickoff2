# Handoff 2026-07-04-016 — Online Track O4 games highlights inner LIMIT

**Status:** Done · **Fixture:** `ko2unity_db` present mode · **Method:** narrow inner LIMIT subquery + join-back (mirror Amiga Track L Highlights)

**Audit batch:** O4 from [`2026-07-04-012-online-realm-query-audit.md`](2026-07-04-012-online-realm-query-audit.md)

---

## Problem

`/games/highlights.php` spent ~**899 ms** SQL in `k2_games_highlights_fetch` — wide `SELECT` all columns from `ratedresults`, filesort on spectacle metric, `LIMIT 100`. Census curl **0.987 s** (Heavy).

## Shipped

| Change | File |
|--------|------|
| `k2_games_highlights_board_limit_scan()` — per-board filter + sort keys | `games_highlights_helpers.php` |
| `k2_games_highlights_lean_limit_subquery_sql()` — inner subquery projects only sort cols (`id` + metric) before `LIMIT` | same |
| `k2_games_highlights_fetch()` — join-back to `ratedresults` for display cols; request cache per board/limit | same |
| Parity oracle (4 boards × row IDs + payload) | `scripts/oneoff/online_games_highlights_parity_probe.php` |

**Boundaries:** read-time PHP only — no DDL, no ops/, no stored-truth.

## Probe (lib ms, `most_goals`, cold CLI)

| Call | Before | After |
|------|-------:|------:|
| `k2_games_highlights_fetch` | **~1421 ms** (probe) / **899 ms** (audit) | **~722 ms** |
| `k2_games_hub_status_counts` | ~14 ms | ~8 ms |

## Curl (`/games/highlights.php`)

| | Before (census) | After (census rerun) |
|--|----------------:|---------------------:|
| Total | **0.987 s** | **0.758 s** |

Warm curl 3-run best **~944 ms** (variance on local Laragon). Target **<0.15 s SQL / ~0.2 s curl** not met — residual cost is full-table filesort on ~75k `ratedresults` rows with no metric index (`SumOfGoals`, `GoalDifference`, etc.). Audit defers further DDL; Amiga Track L also added metric indexes (`046_game_ratings_metric_indexes.sql`) for sub-100 ms lib.

## Parity

`php scripts/oneoff/online_games_highlights_parity_probe.php` — **ALL OK** (`most_goals`, `biggest_draws`, `biggest_wins`, `top_score`).

## Verification

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_hot_path_probe.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_games_highlights_parity_probe.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_full_census_probe.php --out=scripts\oneoff\online_realm_full_census_results.md

curl.exe -s -o NUL -w "%{time_total}" "http://ratingskickoff.test/games/highlights.php"
```

## Residual / follow-up

- **Metric indexes on `ratedresults`** (e.g. `(SumOfGoals, id)`, `(GoalDifference, id)`) — ops/cutover register; likely needed for **<150 ms** SQL.
- Request cache helps repeat board fetch within one PHP request only.

## Files

- `site/public_html/includes/games_highlights_helpers.php`
- `scripts/oneoff/online_games_highlights_parity_probe.php`
- `scripts/oneoff/online_realm_full_census_results.md` (census rerun)