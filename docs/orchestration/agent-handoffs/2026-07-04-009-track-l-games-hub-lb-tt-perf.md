# Handoff 2026-07-04-009 — Track L: Games hub + four LB wings TT perf

**Status:** Done · **Owner:** agent 2026-07-04 · **Method doc:** [`docs/amiga-tt-query-optimization-playbook.md`](../../amiga-tt-query-optimization-playbook.md)
**Predecessor:** Track C slice 2 in [`2026-07-04-004`](2026-07-04-004-amiga-tt-query-optimization-sweep.md)

---

## 1. Mission

Polish TT feel on **Games hub** (recent · highlights · all) and **four LB wings** (peak-rating · rating · perf/best · tournament-honours). Census before: games/recent **0.57 s**, LB present **0.21–0.30 s**. Targets: games **≤0.35 s**, LB **≤0.25 s** (warm curl).

## 2. Shipped

| Area | Change | Pattern |
|------|--------|---------|
| **Highlights** | Metric-first inner `LIMIT` subquery (g/gr/t only) + join-back for display cols; request cache per board/cutoff | C + D |
| **Highlights indexes** | `046_game_ratings_metric_indexes.sql` — `idx_amiga_game_ratings_sum_goals`, `idx_amiga_game_ratings_goal_diff` | C |
| **Games count @ TT** | `amiga_lb_games_count_uncached()` → catalog `SUM(game_count)` at cutoff (parity with game scan) | stored truth |
| **Tournament index count** | `amiga_tournament_index_count()` → `COUNT(*)` + request cache (was `count(cached_all_rows)`) | D |
| **LB chapter lede** | `amiga_lb_chapter_lede_html_for_request($con, $ctx)` prewarm on four LB pages before `mysqli_close` — nav reuses cache | D |
| **Games Recent** | Batch fetch once; pass `$gamesByTournament` into `amiga_games_hub_status_counts()`; single sectioned table (`amiga_realm_games_hub_render_sectioned_table`) | D + HTML |
| **Hub lib** | Request cache on `amiga_realm_games_hub_fetch_games_by_tournaments()` | D |
| **Games All facets** | Request cache on `amiga_realm_games_load_score_line_filter_facets()` | D |

## 3. Parity (green)

- `scripts/oneoff/amiga_games_highlights_parity_probe.php` — all 5 boards × 3 cutoffs **OK**
- `scripts/oneoff/amiga_track_l_parity_probe.php` — score-line facets + tournament count + **games count** × 4 cutoffs **OK**

## 4. Probe results (lib ms + warm curl worst)

Probe: `scripts/oneoff/amiga_track_l_tt_probe.php`

| Surface | Lib worst TT | Curl worst | Target | Notes |
|---------|--------------|------------|--------|-------|
| games/recent @ `year:2024` | **~19 ms** | **0.735 s** | ≤0.35 s | DB fixed; **~876 `<tr>`** HTML (5 tournaments, all games) — same class as Track J player games |
| games/highlights @ present | **~72 ms** | **0.263 s** | ≤0.35 s | **Meets target** (was lib ~544 ms before inner LIMIT) |
| games/all @ `year:2024` | **~138 ms** | **0.727 s** | ≤0.35 s | Facets single-pass ~140 ms + 250-row page HTML |
| peak-rating @ present | **~12 ms** | **0.258 s** | ≤0.25 s | Marginal — chrome + wide LB table |
| rating @ present | **~3 ms** | **0.226 s** | ≤0.25 s | **Meets target** |
| perf/best @ `year:2024` | **~38 ms** | **0.250 s** | ≤0.25 s | **Meets target** (lede prewarm) |
| tournament-honours @ present | **~3 ms** | **0.151 s** | ≤0.25 s | **Meets target** |

## 5. Residual (query work done; curl over target)

- **games/recent** and **games/all** — blocking lib **≤140 ms** @ worst TT; curl gap = TT chrome + large table HTML (+ All games score-line facet scan on first load). No product semantics change in this slice.
- **peak-rating** — **0.258 s** vs **0.25 s** LB target; lib **~21 ms** @ `year:2024`. Optional future: row pagination only if Dagh wants sub-250 ms curl on all LB wings.

## 6. Files touched

- `site/public_html/includes/amiga_games_highlights_helpers.php`
- `site/public_html/includes/amiga_realm_games_hub_lib.php`
- `site/public_html/includes/amiga_lb_snapshot_lib.php`
- `site/public_html/includes/amiga_lb_lib.php`
- `site/public_html/includes/amiga_games_hub_helpers.php`
- `site/public_html/includes/amiga_realm_games_hub_table.php`
- `site/public_html/includes/amiga_realm_games_filter_facets.php`
- `site/public_html/includes/amiga_tournament_lib.php`
- `site/public_html/amiga/games/recent.php`
- `site/public_html/amiga/leaderboards/{peak-rating,rating,tournament-honours}.php`
- `site/public_html/amiga/leaderboards/performance-rating/best.php`
- `scripts/amiga/sql/046_game_ratings_metric_indexes.sql` (+ derived mirror)
- `scripts/oneoff/amiga_track_l_{tt,parity}_probe.php`