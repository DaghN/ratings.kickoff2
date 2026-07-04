# Handoff 2026-07-04-015 — Online Track O2 player games facet dedupe

**Status:** Done · **Fixture:** player **537** (geo4444, 11087 games) · **Method:** request cache + validate/load dedupe (port Amiga Track M)

**Audit batch:** O2 from [`2026-07-04-012-online-realm-query-audit.md`](2026-07-04-012-online-realm-query-audit.md)

---

## Problem

Busy-player `/player/games.php?id=537` spent ~**2.3 s** SQL on duplicate facet passes: `validate_filters_career_wide` (~1029 ms) ran opponent + four score-line GROUP BY scans, then `load_filter_facets` (~1296 ms) repeated result + opponent + four score-line scans. Census curl **3.26 s**.

## Shipped

| Change | File |
|--------|------|
| Career-wide facet bundle (request cache) shared by validate + unfiltered load | `k2_player_games_filter_facets.php` |
| Single-pass gf/ga/gs/gd scan when numerics idle | same |
| `validate_filters_career_wide` reads bundle instead of 5 duplicate queries | same |
| Parity oracle | `scripts/oneoff/online_player_games_facet_parity_probe.php` |

**Boundaries:** read-time PHP only — no DDL, no ops/, no stored-truth.

## Probe (lib ms, player 537, cold CLI)

| Call | Before | After |
|------|-------:|------:|
| `validate_filters_career_wide` | ~1029 ms | **~820 ms** (builds bundle) |
| `load_filter_facets` (career-wide) | ~1296 ms | **0 ms** (cache hit) |
| **Facet stack total** | **~2325 ms** | **~820 ms** |
| `count_query` | ~148 ms | ~177 ms |
| `fetch_page_query` | ~174 ms | ~284 ms |

## Curl (`/player/games.php?id=537`)

| | Before (census) | After (warm 3-run) |
|--|----------------:|-------------------:|
| Total | **3.26 s** | **1.67–1.80 s** best |

Target **<0.5 s** not met — remaining cost is bundle build (~820 ms) + COUNT + 500-row fetch + HTML render. Further wins need separate slices (pagination already 500; count/fetch or HTML-bound work).

## Parity

`php scripts/oneoff/online_player_games_facet_parity_probe.php 537` — **PARITY OK** (career bundle vs per-dimension queries; listbox counts; six filtered row-count cases).

## Verification

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_hot_path_probe.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_player_games_facet_parity_probe.php 537

curl.exe -s -o NUL -w "%{time_total}" "http://ratingskickoff.test/player/games.php?id=537"
```

## Files

- `site/public_html/includes/k2_player_games_filter_facets.php`
- `scripts/oneoff/online_player_games_facet_parity_probe.php`