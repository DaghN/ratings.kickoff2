# Handoff 2026-07-04-004 — Amiga realm TT query optimization sweep

**Status:** Done (slice 1) · **Owner:** agent 2026-07-04 · **Method doc (read first):** [`docs/amiga-tt-query-optimization-playbook.md`](../../amiga-tt-query-optimization-playbook.md)
**Predecessor evidence:** [`2026-07-04-003`](2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) + [`tt-chrome-baseline-f6-attempt-log.md`](../tt-chrome-baseline-f6-attempt-log.md) (Attempts 4-5, Three slow LB wings, World Cups hub).

---

## 1. Mission

Chew through **every TT-wired Amiga surface**, measure its blocking queries at early/late cutoffs, and bring each page's blocking segment under **~250 ms at the worst cutoff** using the playbook patterns. The chrome machinery (y=0 gate, carry cloak) is already correct — when a TT page still vanishes, it is a query past the 700 ms budget, and the fix is the query.

Already done (do not redo): rating LB career/delta/count (3d-b), tournament-honours, calendar-geo, peak-rating, World Cups share-of-year index + stats-rows cache, Countries roster query (earlier session), `amiga_community_stat_facts` index.

## 2. Work order

### Phase 1 — realm-wide curl census (one probe, ~30 min)

Build `scripts/oneoff/amiga_tt_page_census_probe.php` (or .ps1): curl every TT-wired page (TT policy §4.1 register + hub tab matrix §5.5) at 3 cutoffs — `month:2002-06` (early), `month:2014-07`, `year:2024` or `month:2025-09` (late) — plus present. Record status + total time. Flag anything **> 0.8 s total** as a target. Suggested URL list (extend from `docs/url-routes.md`):

- `/amiga/leaderboards/{rating,goals,dds-css,results,activity,tournament-honours,calendar-geo,peak-rating}.php`
- `/amiga/world-cups/…` (chronology, stats/*, players/*, countries/*)
- `/amiga/countries/…` (index, roster, rivals — rivals H2H is F20, see §4)
- `/amiga/hall-of-fame.php`, histograms/community pages wired at cutoff
- Player wings at cutoff (profile, games, tournaments, opponents, h2h) for a busy player (e.g. id with ~500 events) — single-player reads should already be cheap; census confirms
- Tournament / game detail pages under TT if wired (check §4.1 before assuming)

### Phase 2 — worst-first fixes

For each flagged page, follow the playbook §2 method: lib-level probe -> identify anti-pattern -> fix -> **full row-set parity oracle** (5-7 cutoffs incl. `year:2001` no-history and a late one; beware `event:22` is a LATE cutoff) -> curl re-sweep -> docs Part A same turn.

Known suspects going in (playbook §4):

| Target | Shape | Expected pattern |
|--------|-------|------------------|
| `amiga_slice_snapshot_lib.php` `amiga_lb_wc_slice_rows_at_cutoff` (+ counts calling it) | `s.*`/`x.*` window over `amiga_player_slice_at_event` (3k x 46) | A (narrow window + join-back) + D (cache: count() the cached rows) |
| `amiga_country_slice_snapshot_lib.php` `amiga_lb_wc_country_rows_at_cutoff` | `x.*` window over `amiga_country_slice_at_event` (353 x 55) | A + D |
| `amiga_countries_lib.php` `amiga_countries_attach_elo_ranks_at_cutoff` | window over dense `amiga_player_elo_rank_at_event` filtered to player list | **B** (dense equality: `er.tournament_id = cutoff` + `player_id IN (...)`) |
| `amiga_community_histogram_lib.php` | window over snapshots | A if wide |
| WC hub shell chapter counts (`amiga_wc_honours_player_count`, `amiga_wc_country_count`) | call the slice rows fns above | fixed for free by A + D above |
| Matchup/H2H libs (`amiga_matchup_snapshot_lib`, `amiga_player_h2h_pair_lib`) | `m.*` windows but `player_id = ?` filtered | probe; likely fine; fix only if census flags |

### Phase 3 — close the loop

- Re-run the census; paste before/after table into this handoff.
- Update playbook §4 inventory (remove fixed rows, add anything newly found) and §6 calibration table.
- MEMORY line + this handoff status -> Done.

## 3. Success bar (Dagh)

- Census: every TT-wired page ≤ ~0.8 s curl total at the worst cutoff (rating LB benchmark: ~0.6-0.7 s).
- Browser: no page vanish on TT nav anywhere in the realm — chrome updates in place, only table bodies may swap (Type C).
- Parity oracles green for every changed query — byte-identical row sets or a written justification.
- No product semantics changes (ordering, filters, `as_with` behaviour untouched).

## 4. Boundaries

- **Do NOT** touch `k2_carry_scroll_restore.php` / `js/k2-carry-scroll.js` (F6 track, sign-off pending) — query work only.
- **Do NOT** touch online-realm files (`peak_month_leaderboard_query.php`, `lb_activity_lib.php`, `league_standings.php`, `player_milestones_helpers.php` online paths).
- **F20 Countries rivals H2H** — **shipped** same day in [`2026-07-04-002`](2026-07-04-002-f20-country-rivals-h2h-audit.md) (query slice; chrome flush still optional).
- New indexes: DDL in `scripts/amiga/sql/` + `sql/derived/` mirror + ALTER local `ko2amiga_db`; staging inherits via next export. No prod/staging direct edits.
- No new `SELECT snap.*` / wide-alias ROW_NUMBER windows anywhere — shared helpers or dense equality.

## 5. Status log

| Date | Entry |
|------|-------|
| 2026-07-04 | Handoff created after LB wings + WC hub fixes; playbook published |
| 2026-07-04 | **Slice 1 shipped** — census probe `scripts/oneoff/amiga_tt_page_census_probe.php`; fixes below. Re-run census before closing remaining gaps. |

### Census before / after (curl total, worst cutoff unless noted)

| Page | Before | After | Notes |
|------|--------|-------|-------|
| `/amiga/games/all.php` | **2.0 s** (all cutoffs) | **~0.08 s** (`year:2024`) · ~0.6 s present | Lean scan + catalog stats for count/years/host; single-pass score-line facets |
| `/amiga/games/recent.php` | **1.0 s** (`year:2024`) | **~0.6 s** | Lean tournament-indexed hub fetch |
| `/amiga/world-cups/players/*` | 0.25–0.31 s | **~0.09 s** | WC slice narrow window + request cache |
| `/amiga/player/games.php?id=382` | **1.3–1.5 s** | **~1.4 s** | DB path **15 ms** (player-scoped inner scan); curl still HTML-bound (~1500 `<tr>`) — not a query vanish |
| `/amiga/country/rivals/*` | **1.0–2.3 s** | unchanged | **F20 handoff** — out of scope this slice |
| Rating LB + other LB wings | already ≤0.7 s | unchanged | prior 3d-b work |

Probe: `scripts/oneoff/amiga_games_all_tt_probe.php` — blocking lib calls at `year:2024` **~235 ms** (was ~1800 ms).

### Shipped code (slice 1)

- **WC slice** — `amiga_lb_wc_player_slice_from_sql()` / `amiga_lb_wc_country_slice_from_sql()` + request cache; parity `scripts/oneoff/amiga_wc_slice_parity_probe.php` green ×6 cutoffs ×3 views.
- **Countries roster elo attach** — dense equality `er.tournament_id = cutoff` (pattern B).
- **Games All** — player-scoped / lean game scans; catalog-stats count/years/host at TT; combined score-line facet pass; hub count dedupe.
- **Games Recent** — lean tournament-indexed fetch in `amiga_realm_games_hub_lib.php`.
- **Player games** — `amiga_rated_games_from_sql($playerId)` pushes hero filter into indexed inner scan.

### Remaining (next slice if needed)

- **Player games curl > 0.8 s** — query fixed; page time = rendering ~1500-row table + filter chrome (no product change allowed).
- **F20 country rivals** — **done** — [`2026-07-04-002`](2026-07-04-002-f20-country-rivals-h2h-audit.md) query slice.
- **Present-mode `/amiga/games/all.php`** — ~0.6–0.9 s (score-line game scan); TT cutoffs now fast.