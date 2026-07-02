# Amiga Activity charts — implementation plan

**Status:** **Ready** (Jul 2026) — slices 0–10 not started. Read-only chart track: **no finalize writers, no DDL** (unless a slice-8 probe promotes a histogram to S6 — separate sign-off + Part B).
**Locked IA / product:** [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) — wings, panel order, selectors, TT rules, bucket defaults.
**Questions:** [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) — 46 ship IDs → 45 panels.
**Pattern source:** online [`activity-charts.md`](activity-charts.md) (module, frames, mobile rules).

---

## 0. Scope & invariants (every slice)

| Invariant | Rule |
|-----------|------|
| **Read-only** | Chart APIs + UI only. `python -m scripts.amiga prove` surface untouched; no registry/writer edits. |
| **TT on every API** | Fetches pass **`as=` only**; endpoint resolves cutoff via `amiga_snapshot_context_from_request()` and reads at cutoff `tournament_id`. Never with-player params on fetches. |
| **Link carry** | Sub-nav + cross-links via `k2_amiga_route()` / `amiga_url_with_context()` (carries `as=` + `as_with`); JS-built hrefs via `k2-amiga-time-travel-url.js`. |
| **Chart chrome** | One module `js/amiga-activity-charts.js`; `.k2-chart-panel` + `.k2-chart-frame`; `chart-theme.js` tooltips/tokens; boot `k2OnPageReady`; sequential loader; no per-chart boot files; no canvas `%` sizing; no `Chart.defaults` mutation. |
| **URL shape** | Folder pages per mode; query params filters only (`?hosts=`, `?nats=`). [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md). |
| **Encoding** | New PHP/JS/MD files written UTF-8 no BOM (Windows agent rule). |
| **Finish per slice** | Local verify (§5) + `PROJECT_MEMORY.md` line + status tick here. Part B only if S6 DDL ships. |

---

## 1. Slice overview

| Slice | Deliverable | Panels shipped | Status |
|-------|-------------|----------------|--------|
| **0** | Platform: folder + routes + shell/nav + 302 + JS module skeleton + read helpers + first 2 APIs | 0 (placeholder pages) | — |
| **1** | Growth wing | 7 | — |
| **2** | People wing | 5 | — |
| **3** | Texture wing (rates API + reference lines) | 5 | — |
| **4** | World Cups wing (ghost bars + overlay) | 6 | — |
| **5** | Geography selector platform (duel + race controls, slice_series API) | 0 | — |
| **6** | Geography — Hosts | 8 | — |
| **7** | Geography — Nations | 5 | — |
| **8** | **STOP gate:** Shape C8 probes + bucket lock (IA-4) | 0 | — |
| **9** | Shape wing | 9 | — |
| **10** | Polish: mobile pass, perf order, cross-links, docs finish | — | — |

Running total after slice 9: **45 panels / 46 question IDs.**

---

## 2. Panel registry (parity contract)

Load order = table order per page. Panel class doubles as registry id.

### Growth — `amiga/activity/growth.php`

| # | Panel class | API call | Chart | ID(s) |
|---|-------------|----------|-------|-------|
| 1 | `.amiga-act-games-year-chart` | `year_facts?slice=realm&metric=games` | bar, category years | Q-VOL-001 |
| 2 | `.amiga-act-games-cumulative-chart` | `snapshot_series?metric=GamesPlayed` | line, time | Q-VOL-002 |
| 3 | `.amiga-act-tournaments-year-chart` | `year_facts?slice=realm&metric=tournaments` | bar | Q-VOL-005 |
| 4 | `.amiga-act-tournaments-cumulative-chart` | `snapshot_series?metric=TournamentsFinalized` | line | Q-VOL-006 |
| 5 | `.amiga-act-goals-year-chart` | `year_facts?slice=realm&metric=goals` | bar | Q-VOL-007 |
| 6 | `.amiga-act-goals-cumulative-chart` | `snapshot_series?metric=GoalsScored` | line | Q-VOL-008 |
| 7 | `.amiga-act-games-per-tournament-year-chart` | `year_rates?rate=games_per_tournament` | bar | Q-ECO-004 |

### People — `amiga/activity/people.php`

| # | Panel class | API call | Chart | ID(s) |
|---|-------------|----------|-------|-------|
| 1 | `.amiga-act-active-players-year-chart` | `year_facts?slice=realm&metric=active_players` | bar | Q-VOL-003 |
| 2 | `.amiga-act-debuts-year-chart` | `year_facts?slice=realm&metric=player_debuts` | bar | Q-SHP-009 |
| 3 | `.amiga-act-players-cumulative-chart` | `snapshot_series?metric=NumberOfPlayers` | line | Q-VOL-004 + Q-SHP-010 |
| 4 | `.amiga-act-pairs-year-chart` | `year_facts?slice=realm&metric=distinct_pairs` | bar | Q-SHP-001 |
| 5 | `.amiga-act-pairs-cumulative-chart` | `snapshot_series?metric=DistinctOpponentPairs` | line | Q-SHP-002 |

### Geography Hosts — `amiga/activity/geography/hosts.php` (duel state `?hosts=A,B`)

| # | Panel class | API call | Chart | ID(s) |
|---|-------------|----------|-------|-------|
| 1 | `.amiga-act-host-games-year-chart` | `year_facts?slice=host_country&metric=games&keys=` | grouped bar (duel) | Q-GEO-001 |
| 2 | `.amiga-act-host-games-race-chart` | `slice_series?slice=host_country&metric=games&keys=` | multi-line (race) | Q-GEO-002 |
| 3 | `.amiga-act-host-tournaments-year-chart` | `year_facts?slice=host_country&metric=tournaments&keys=` | grouped bar | Q-GEO-004 |
| 4 | `.amiga-act-host-tournaments-race-chart` | `slice_series?slice=host_country&metric=tournaments&keys=` | multi-line | Q-GEO-014 |
| 5 | `.amiga-act-host-goals-year-chart` | `year_facts?slice=host_country&metric=goals&keys=` | grouped bar | Q-GEO-003 |
| 6 | `.amiga-act-host-goals-race-chart` | `slice_series?slice=host_country&metric=goals&keys=` | multi-line | Q-GEO-013 |
| 7 | `.amiga-act-host-countries-year-chart` | `year_facts?slice=realm&metric=distinct_host_countries` | bar | Q-GEO-008 |
| 8 | `.amiga-act-host-countries-cumulative-chart` | `snapshot_series?metric=DistinctHostCountries` | stepped line | Q-GEO-009 |

### Geography Nations — `amiga/activity/geography/nations.php` (duel state `?nats=A,B`)

| # | Panel class | API call | Chart | ID(s) |
|---|-------------|----------|-------|-------|
| 1 | `.amiga-act-nat-appearances-year-chart` | `year_facts?slice=player_nationality&metric=games&keys=` | grouped bar | Q-GEO-005 |
| 2 | `.amiga-act-nat-appearances-race-chart` | `slice_series?slice=player_nationality&metric=games&keys=` | multi-line | Q-GEO-007 |
| 3 | `.amiga-act-nat-goals-year-chart` | `year_facts?slice=player_nationality&metric=goals&keys=` | grouped bar | Q-GEO-006 |
| 4 | `.amiga-act-nat-goals-race-chart` | `slice_series?slice=player_nationality&metric=goals&keys=` | multi-line | Q-GEO-015 |
| 5 | `.amiga-act-nationalities-year-chart` | `year_facts?slice=realm&metric=distinct_nationalities` | bar | Q-GEO-010 |

### World Cups — `amiga/activity/world-cups.php`

| # | Panel class | API call | Chart | ID(s) |
|---|-------------|----------|-------|-------|
| 1 | `.amiga-act-wc-games-year-chart` | `year_facts?slice=world_cup&metric=games` + realm games (ghost) | layered bar | Q-WC-001 |
| 2 | `.amiga-act-wc-share-year-chart` | `year_rates?rate=wc_share` | % bar | Q-WC-003 |
| 3 | `.amiga-act-wc-games-cumulative-chart` | `snapshot_series?metric=WcGamesPlayed` | line | Q-WC-002 |
| 4 | `.amiga-act-wc-goals-per-game-year-chart` | `year_rates?rate=wc_goals_per_game` (+realm rate overlay) | bar + line overlay | Q-WC-011 |
| 5 | `.amiga-act-wc-nations-year-chart` | `year_facts?slice=world_cup&metric=distinct_nationalities` | bar | Q-WC-006 |
| 6 | `.amiga-act-wc-players-year-chart` | `year_facts?slice=world_cup&metric=active_players` | bar | Q-WC-007 |

### Texture — `amiga/activity/texture.php` (all with all-time reference line)

| # | Panel class | API call | ID(s) |
|---|-------------|----------|-------|
| 1 | `.amiga-act-goals-per-game-year-chart` | `year_rates?rate=goals_per_game` | Q-TEX-007 |
| 2 | `.amiga-act-draw-rate-year-chart` | `year_rates?rate=draw_rate` | Q-TEX-006 |
| 3 | `.amiga-act-dd-rate-year-chart` | `year_rates?rate=dd_rate` | Q-TEX-008 |
| 4 | `.amiga-act-cs-rate-year-chart` | `year_rates?rate=cs_rate` | Q-TEX-009 |
| 5 | `.amiga-act-high-scoring-rate-year-chart` | `year_rates?rate=high_scoring_rate` | Q-TEX-013 |

### Shape — `amiga/activity/shape.php` (bucket edges finalized at slice 8)

| # | Panel class | API call | ID(s) |
|---|-------------|----------|-------|
| 1 | `.amiga-act-career-games-histogram` | `histogram?kind=career_games` | Q-SHP-007 |
| 2 | `.amiga-act-tournaments-played-histogram` | `histogram?kind=tournaments_played` | Q-SHP-008 |
| 3 | `.amiga-act-distinct-opponents-histogram` | `histogram?kind=distinct_opponents` | Q-SHP-014 |
| 4 | `.amiga-act-active-years-histogram` | `histogram?kind=active_years` | Q-SHP-015 |
| 5 | `.amiga-act-countries-played-histogram` | `histogram?kind=countries_played` | Q-SHP-003 |
| 6 | `.amiga-act-wcs-played-histogram` | `histogram?kind=world_cups_played` | Q-SHP-004 |
| 7 | `.amiga-act-rating-distribution-histogram` | `histogram?kind=rating` | Q-SHP-016 |
| 8 | `.amiga-act-goal-sum-histogram` | `histogram?kind=goal_sum` | Q-SHP-005 |
| 9 | `.amiga-act-tournament-size-histogram` | `histogram?kind=tournament_games` | Q-SHP-006 |
---

## 3. API contracts

All endpoints: JSON, `realm` implied Amiga (own files, no online realm param), resolve `as=` via `amiga_snapshot_context_from_request()`; absent/invalid `as` = present (latest snapshot / `amiga_community_stats` id=1 where applicable). Errors: `{"error": "..."}` + 4xx. Shared read helpers extend [`amiga_community_stats_lib.php`](../site/public_html/includes/amiga_community_stats_lib.php): `amiga_community_facts_query()` (general grain read at cutoff) + `amiga_community_snapshot_series()`.

### `api/amiga_community_year_facts.php`

- Params: `slice` (realm | host_country | player_nationality | world_cup), `metric` (registry metric key), optional `keys` (CSV slice keys, max 7, validated), `as`.
- Response: `{ "years": [2001, …], "series": [{ "key": "England", "values": [...] }], "available_keys": ["England", …], "cutoff": { "label": "...", "partial_year": 2003 } }`.
- Realm slice returns one series with `key: "*"`. `available_keys` only for dimensional slices (drives pickers).
- Read: facts rows `WHERE tournament_id = {cutoff} AND period_type = 'year' AND slice_type = {slice} AND metric_key = {metric}`.

### `api/amiga_community_snapshot_series.php`

- Params: `metric` — **whitelist**: `GamesPlayed`, `GoalsScored`, `NumberOfPlayers`, `TournamentsFinalized`, `DistinctHostCountries`, `WcGamesPlayed`, `DistinctOpponentPairs`; `as`.
- Response: `{ "points": [{ "t": tournament_id, "date": "Y-m-d", "name": "...", "value": N }] }` — all snapshots with `event_chrono` <= cutoff, chrono order. `name` feeds tooltips + click-through.

### `api/amiga_community_slice_series.php`

- Params: `slice` (host_country | player_nationality), `metric` (games | goals | tournaments), `keys` (CSV, max 7; default top 5 by all-time value at cutoff), `as`.
- Response: per key, `{ "points": [{ "t", "date", "name", "value" }] }` from **`all_time` facts across snapshots** <= cutoff; plus `available_keys` ranked by all-time value at cutoff (drives race defaults + add-country).

### `api/amiga_community_year_rates.php`

- Params: `rate` (goals_per_game | draw_rate | dd_rate | cs_rate | high_scoring_rate | games_per_tournament | wc_share | wc_goals_per_game), `as`.
- Response: `{ "years": [...], "values": [...], "reference": N|null, "overlay": { "label": "...", "values": [...] }|null }`.
- Derivations (all from year facts at cutoff): rates = numerator ÷ realm `games` (goals_per_game = goals ÷ games; games_per_tournament = games ÷ tournaments; wc_share = wc games ÷ realm games; wc_goals_per_game = wc goals ÷ wc games, overlay = realm goals_per_game). `reference` = all-time average from headline row at cutoff (texture rates only). Zero-denominator years → `null` values, skipped by Chart.js.

### `api/amiga_community_histogram.php` (slice 9; shapes locked at slice 8)

- Params: `kind` (career_games | tournaments_played | distinct_opponents | active_years | countries_played | world_cups_played | rating | goal_sum | tournament_games), `as`.
- Response: `{ "buckets": [{ "label": "10-24", "count": N }], "population": N, "population_label": "players" }` — buckets computed server-side.
- Oracle sources (validated at slice 8): player kinds → `amiga_player_event_snapshots` at cutoff / `amiga_player_current` present; `goal_sum` → game rows at cutoff; `tournament_games` → per-tournament game counts at cutoff; `active_years` → S5 probe decides.

---

## 4. Slice details

### Slice 0 — Platform

**Goal:** the sub-hub exists; charts can be added wing by wing.

- Folder `site/public_html/amiga/activity/` + **seven thin leaf files** (placeholder body: section head + "Charts arriving soon" note so nav is never broken).
- Shell pair `includes/amiga_activity_hub_shell_start.inc.php` / `_end.inc.php` (copy `amiga_world_cups_hub_shell_*`): chapter header + KOA lede (live reads at cutoff), wing nav include, `body.k2-amiga-activity-charts`.
- Wing nav `includes/amiga_activity_hub_nav.php` (6 tabs) + nested `includes/amiga_activity_geography_nav.php` (Host nations · Nationalities) — hrefs via route keys + `amiga_url_with_context()`.
- Routes: policy §3.2 keys in `k2_amiga_routes.php`; hub nav lib Activity href → Growth; `amiga/activity.php` becomes a **302 stub** to Growth preserving query.
- Summary tiles include moves onto Growth (above panels).
- `js/amiga-activity-charts.js` skeleton: `registerPanel` + sequential `drain()` + status handling, gated on body class, boot via `k2OnPageReady`.
- Read helpers: `amiga_community_facts_query()` + `amiga_community_snapshot_series()` in `amiga_community_stats_lib.php`.
- APIs: `amiga_community_year_facts.php` (realm slice) + `amiga_community_snapshot_series.php`.
- Docs: [`url-routes.md`](url-routes.md) route table + sub-hub example row; nav follows [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md).

**STOP:** all seven pages render with hub bar + chapter + wing nav, present and `as=year:2005`; both APIs return valid JSON at present + cutoff; no console errors.

### Slice 1 — Growth (7 panels)

- Panels per registry §2; pairs layout (bar above line per metric).
- L2 tooltips: tournament name title; **click-through** to tournament page via `k2-amiga-time-travel-url.js` (desktop pointers only).
- `year_rates` endpoint ships here with `games_per_tournament` only.
- Placeholder note removed from growth.php.

**STOP:** Dagh visual sign-off on Growth (desktop + phone scroll) before replicating the pattern to other wings.

### Slice 2 — People (5 panels)

- Note in markup: cumulative players panel is the VOL-004 + SHP-010 merge (policy §4).

### Slice 3 — Texture (5 panels)

- Extend `year_rates` with the four texture rates + `high_scoring_rate`; `reference` values from headline at cutoff; reference line rendering in module (dashed, muted, labelled in tooltip footer).

### Slice 4 — World Cups (6 panels)

- Extend `year_rates` with `wc_share` + `wc_goals_per_game` (+ overlay values).
- Ghost-bar layered rendering (realm behind WC, muted tone) in module — reused nowhere else yet, keep generic (`datasets[].ghost` flag).
- Intro copy cross-links World Cups hub; WC hub Tournament stats wing gains a link back (one line).

### Slice 5 — Geography selector platform (no panels)

- Module controls: **duel** (two flag dropdowns, grouped bars) + **race** (legend chips, add-country, cap 7).
- URL state: `?hosts=` / `?nats=` CSV; `history.replaceState` on change; PHP prefills selects from GET; invalid keys → defaults (England, Germany).
- `slice_series` API ships here.
- Option lists + race defaults from `available_keys` (cutoff-aware).
- Flags inline per entity-links policy; country names link to country roster.

**STOP:** duel + race behave on a dev harness page (or directly on slice-6 first panel) at present + TT cutoff; URL round-trip (paste URL → same selection) proven.

### Slice 6 — Geography Hosts (8 panels) · Slice 7 — Geography Nations (5 panels)

- Registry §2 order; one duel state drives all Pattern-A charts on the page.
- GEO-009 stepped line tooltip: names the tournament that unlocked each new host country.

### Slice 8 — Shape probes (IA-4 STOP gate — no UI)

- Probe each `histogram` kind read-time at **four cutoffs**: first event, ~2007 peak, ~2015 mid, present. Record ms + row counts **in this section**.
- Guidance: < ~150 ms at present = ship read-time (S4/S5); worse → cache-in-API or propose **S6 store** (Dagh sign-off + DDL slice + Part B; storage policy §6 rule).
- Lock bucket edges from real distributions (policy §5.6 defaults are the starting point; note deviations here).

**STOP:** table of probe timings + chosen oracle per kind + final bucket edges recorded here before slice 9 starts.

### Slice 9 — Shape (9 panels)

- `histogram` API per slice-8 outcomes; % of population in tooltips.

### Slice 10 — Polish + finish

- Mobile pass: `touch-action: pan-y pinch-zoom` on panels; tooltips off coarse; heaviest panels (race multi-lines) last in loader queue.
- Cross-link audit (Countries hub, WC hub, tournament click-through carry).
- Registry parity check: 45 panels ↔ 46 ship IDs, per-panel checklist from [`activity-charts.md`](activity-charts.md) §6.
- Docs: this plan statuses; policy status line; [`url-routes.md`](url-routes.md); catalog plan step 6 → done; MEMORY; feature-log row (Part A). Part B only if S6 shipped.

---

## 5. Verification habits (each ship slice)

- Browser: wing page at **present**, `as=year:2005`, `as=month:2010-06`, `as=event:{first}` — charts truncate correctly; partial-year bar labelled; pickers shrink to cutoff keys.
- Console clean on load; single chart instance per canvas (no duplicate init on re-fetch).
- Selector slices: URL round-trip + back/forward sanity.
- Phone (or narrow devtools): scroll over panels, no horizontal jank, tooltips off.
- `python -m scripts.amiga prove` untouched — run only if anything under `scripts/amiga/` changed (should be never in this track).

---

## 6. Out of scope

- New finalize writers / registry grains / DDL (catalog step 4 closed; S6 only via slice-8 escalation).
- Online Activity changes; per-WC stats table; player profile charts.
- The 2 **later** questions (Q-TEX-002/003) and all cut rows.

*Plan created Jul 2026 from the locked IA in [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md).*