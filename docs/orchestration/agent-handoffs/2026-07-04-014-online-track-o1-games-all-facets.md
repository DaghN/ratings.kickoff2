# Handoff 2026-07-04-014 — Online Track O1 games/all score-line facet dedupe

**Status:** Done · **Page:** `/games/all.php` · **Method:** request cache + single-pass score-line bundle (port Amiga realm games pattern)

**Audit batch:** O1 from [`2026-07-04-012-online-realm-query-audit.md`](2026-07-04-012-online-realm-query-audit.md)

---

## Problem

Default `/games/all.php` spent ~**2.8–3.7 s** SQL on three separate `GROUP BY` facet scans (`gd`, `gs`, `ts`) over wide `ratedresults`. Census curl **3.94 s** — score-line facets dominated server time.

## Shipped

| Change | File |
|--------|------|
| Request-scoped facet cache keyed on filter state | `k2_realm_games_filter_facets.php` |
| Single-pass `GROUP BY gd, gs, ts` when score-line filters idle | same |
| Fallback to per-dimension omit-self queries when gd/gs/ts active | same |
| Parity oracle (8 filter cases) | `scripts/oneoff/online_track_o1_parity_probe.php` |

**Boundaries:** read-time PHP only — no DDL, no ops/, no stored-truth.

## Probe (lib ms, default unfiltered, cold CLI)

| Call | Before | After |
|------|-------:|------:|
| `k2_realm_games_load_score_line_filter_facets` | **3704 ms** (probe) / **2816 ms** (audit) | **~995 ms** |
| `k2_realm_games_all_count` | ~0.5 ms | ~0.6 ms |
| `k2_realm_games_all_fetch_page` | ~10 ms | ~7 ms |

## Curl (`/games/all.php`)

| | Before (census) | After |
|--|----------------:|------:|
| Total | **3.94 s** | **1.31 s** (census) |
| Warm 5-run min | — | **1.45 s** |

Target **<0.5 s** not met — remaining cost is one full-table score-line aggregation (~1 s) + player list fetch + 250-row HTML + PHP bootstrap. Further wins need separate slices (not stored-truth per audit defer list).

## Parity

`php scripts/oneoff/online_track_o1_parity_probe.php` — **ALL OK** (facets + listbox choices + filtered row counts for default, player537, year modes, active gd/gs/ts, player+opponent).

## Verification

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_track_o1_parity_probe.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_hot_path_probe.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_full_census_probe.php --out=scripts\oneoff\online_realm_full_census_results.md

curl.exe -s -o NUL -w "%{time_total}" "http://ratingskickoff.test/games/all.php"
```

## Files

- `site/public_html/includes/k2_realm_games_filter_facets.php`
- `scripts/oneoff/online_track_o1_parity_probe.php`