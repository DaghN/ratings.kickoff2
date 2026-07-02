# Amiga Activity charts — implementation plan

**Status:** **Shipped** (Jul 2026) — **45 panels / 46 ship IDs** live on `/amiga/activity/` (six wings); track **complete** (slices 0–10). Read-only chart track: **no finalize writers, no DDL**.
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
| **0** | Platform: folder + routes + shell/nav + 302 + JS module skeleton + read helpers + first 2 APIs | 0 (placeholder pages) | **Done** Jul 2026 |
| **1** | Growth wing | 7 | **Done** Jul 2026 |
| **2** | People wing | 5 | **Done** Jul 2026 |
| **3** | Texture wing (rates API + reference lines) | 5 | **Done** Jul 2026 |
| **4** | World Cups wing (ghost bars + overlay) | 6 | **Done** Jul 2026 |
| **5** | Geography selector platform (duel + race controls, slice_series API) | 0 | **Done** |
| **6** | Geography — Hosts | 8 | **Done** |
| **7** | Geography — Nations | 5 | **Done** |
| **8** | **STOP gate:** Shape C8 probes + bucket lock (IA-4) | 0 | **Done** Jul 2026 |
| **9** | Shape wing | 9 | **Done** Jul 2026 |
| **10** | Polish: mobile pass, perf order, cross-links, docs finish | — | **Done** Jul 2026 |

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

- Params: `slice` (realm | host_country | player_nationality | world_cup), `metric` (registry metric key), optional `keys` (CSV slice keys, max 9, validated), `as`.
- Response: `{ "years": [2001, …], "series": [{ "key": "England", "values": [...] }], "available_keys": ["England", …], "cutoff": { "label": "...", "event_date": "Y-m-d", "partial_year": 2003 } }`.
- Realm slice returns one series with `key: "*"`. `available_keys` only for dimensional slices (drives pickers).
- Read: facts rows `WHERE tournament_id = {cutoff} AND period_type = 'year' AND slice_type = {slice} AND metric_key = {metric}`.

### `api/amiga_community_snapshot_series.php`

- Params: `metric` — **whitelist**: `GamesPlayed`, `GoalsScored`, `NumberOfPlayers`, `TournamentsFinalized`, `DistinctHostCountries`, `WcGamesPlayed`, `DistinctOpponentPairs`; `as`.
- Response: `{ "points": [{ "t": tournament_id, "date": "Y-m-d", "name": "...", "value": N }] }` — all snapshots with `event_chrono` <= cutoff, chrono order. `name` feeds tooltips + click-through.

### `api/amiga_community_slice_series.php`

- Params: `slice` (host_country | player_nationality), `metric` (games | goals | tournaments), `keys` (CSV, max 9; default top 5 by all-time value at cutoff), `as`.
- Response: per key, `{ "points": [{ "t", "date", "name", "value" }] }` from **`all_time` facts across snapshots** <= cutoff; plus `available_keys` ranked by all-time value at cutoff (drives race defaults + add-country).

### `api/amiga_community_year_rates.php`

- Params: `rate` (goals_per_game | draw_rate | dd_rate | cs_rate | high_scoring_rate | games_per_tournament | wc_share | wc_goals_per_game), `as`. *(Texture + WC rates shipped slices 3–4.)*
- Response: `{ "years": [...], "values": [...], "reference": N|null, "overlay": { "label": "...", "values": [...] }|null, "cutoff": {...}|null }` — `cutoff` mirrors `year_facts` (`label`, `event_date`, `partial_year`).
- Derivations (all from year facts at cutoff): rates = numerator ÷ realm `games` (goals_per_game = goals ÷ games; games_per_tournament = games ÷ tournaments; wc_share = wc games ÷ realm games; wc_goals_per_game = wc goals ÷ wc games, overlay = realm goals_per_game). `reference` = all-time average from headline row at cutoff (texture rates only). Zero-denominator years → `null` values, skipped by Chart.js.

### `api/amiga_community_histogram.php` (slice 9; shapes locked at slice 8)

- Params: `kind` (career_games | tournaments_played | distinct_opponents | active_years | countries_played | world_cups_played | rating | goal_sum | tournament_games), `as`.
- Response: `{ "buckets": [{ "label": "10-24", "count": N }], "population": N, "population_label": "players"|"games"|"tournaments", "cutoff": {...}|null }` — buckets computed server-side via `amiga_community_histogram_compute()` in `includes/amiga_community_histogram_lib.php`.
- Oracle sources (slice 8 locked — all read-time, no S6): `player_snapshot` → snapshot/current at cutoff; `player_snapshot+slice` → WC slice on snapshots; `game_scan` → rated game rows at cutoff (`active_years`, `goal_sum`); `tournament_scan` → per-tournament game counts at cutoff.

---

## 4. Slice details

### Slice 0 — Platform — **Done** (Jul 2026)

**Goal:** the sub-hub exists; charts can be added wing by wing.

*Shipped notes:* read helpers landed as `amiga_community_year_facts_at_cutoff()` + `amiga_community_year_span_at_cutoff()` + `amiga_community_slice_keys_at_cutoff()` + `amiga_community_snapshot_series()`. **Latent bug fixed:** `amiga_community_latest_snapshot_tournament_id()` used `MAX(tournament_id)`, but tournament ids are **not chronological** (fractional-chrono catalog imports, e.g. ids 604/605 = 2002 events) — present-mode reads now pick the chrono-latest snapshot (`ORDER BY event_chrono DESC`). Year-facts API zero-fills across the realm games span at cutoff so every year chart shares one x-axis; chapter lede = live KOA sentence at cutoff (summary lede hidden on Growth via `$k2AmigaActivitySummaryHideLede`).

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

### Slice 1 — Growth (7 panels) — **Done** (Jul 2026)

- Panels per registry §2; pairs layout (bar above line per metric).
- L2 tooltips: tournament name title; **click-through** to tournament page via `k2-amiga-time-travel-url.js` (desktop pointers only).
- `year_rates` endpoint ships here with `games_per_tournament` only.
- Placeholder note removed from growth.php.

*Shipped notes:* panels markup in `includes/amiga_activity_growth_panels.inc.php`; module gained generic mounts `mountYearFacts` / `mountYearRate` / `mountCumulative` (tones: games = pitch, tournaments = chrome, goals = amber, rate = teal) reusable by every later wing. Panel cards style via one generic rule (`body.k2-amiga-activity-charts .k2-chart-panel` — no 45-class CSS list); shell body also carries `k2-activity-charts` so the online frame/section CSS applies verbatim. TT: partial cutoff year gets a tooltip footer (*"Partial year — through Nov 27, 2005"* via `cutoff.event_date` now in `year_facts`/`year_rates` responses); curves end at the cutoff event; point click → `/amiga/tournament/event-stats.php?id=` carrying `as=`. **Site-wide latent bug fixed:** the `k2-page-boot.js` shim invoked every `k2OnPageReady` callback **twice** per load (Turbo-removal regression) — on the online Activity page the second boot's canvas-reuse errors put *"Could not load …"* status lines above every chart; shim now fires callbacks exactly once (verified online Activity 11/11 clean + LB table boot).

**STOP:** Dagh visual sign-off on Growth (desktop + phone scroll) before replicating the pattern to other wings. **Signed off Jul 2026.**

### Slice 2 — People (5 panels) — **Done** (Jul 2026)

- Note in markup: cumulative players panel is the VOL-004 + SHP-010 merge (policy §4).

*Shipped notes:* panels in `includes/amiga_activity_people_panels.inc.php` — active players + debuts year bars (chrome + holo, shared x-axis span), cumulative players line (holo, merge note in panel intro), distinct pairs year bar + cumulative line (teal). Reuses slice-1 mounts only; no new APIs. TT verified (`as=year:2005` → 5 year bars, 148 curve points, chapter lede at cutoff).

### Slice 3 — Texture (5 panels) — **Done** (Jul 2026)

- Extend `year_rates` with the four texture rates + `high_scoring_rate`; `reference` values from headline at cutoff; reference line rendering in module (dashed, muted, labelled in tooltip footer).

*Shipped notes:* `year_rates` extended with `goals_per_game`, `draw_rate`, `dd_rate`, `cs_rate`, `high_scoring_rate`; `reference` from headline at cutoff (`GoalsPerGameAverage`, `DrawsRatio`, `DoubleDigitsRatio`, `CleanSheetsRatio`; high-scoring derived from summed year facts) via `amiga_community_year_rate_reference_at_cutoff()`. Module gained `renderYearRateBar()` — dashed muted all-time line + tooltip footer (*All-time avg: …*); rate formatting (`percent` for draws, `per100` for DD/CS/high-scoring). Panels in `includes/amiga_activity_texture_panels.inc.php` (tones: pitch · chrome · magenta · holo · amber).

### Slice 4 — World Cups (6 panels) — **Done** (Jul 2026)

- Extend `year_rates` with `wc_share` + `wc_goals_per_game` (+ overlay values).
- Ghost-bar layered rendering (realm behind WC, muted tone) in module — reused nowhere else yet, keep generic (`datasets[].ghost` flag).
- Intro copy cross-links World Cups hub; WC hub Tournament stats wing gains a link back (one line).

*Shipped notes:* `year_facts` extended with `slice=world_cup` (games · goals · active_players · distinct_nationalities); `year_rates` adds `wc_share` (WC games ÷ realm games) + `wc_goals_per_game` (overlay = realm goals/game per year). Module: `renderGhostYearBar()` + `mountWcGamesGhostYear()` (`datasets[].ghost` muted realm bars behind WC); `renderYearRateBar()` overlay line for WC goals/game vs realm. Panels in `includes/amiga_activity_world_cups_panels.inc.php`. Cross-links: Activity intro → World Cups hub + Tournament stats; `amiga_world_cup_stats_wing_body.inc.php` one-line link back to Activity World Cups wing. Helper `amiga_community_year_series_filled_at_cutoff()` shared by year_facts.

### Slice 5 — Geography selector platform (no panels) — **Done** (Jul 2026)

- Module controls: **duel** (two flag dropdowns, grouped bars) + **race** (country list, add-country, cap 9).
- URL state: `?hosts=` / `?nats=` CSV; `history.replaceState` on change; PHP prefills selects from GET; invalid keys → defaults (England, Germany).
- `slice_series` API ships here.
- Option lists + race defaults from `available_keys` (cutoff-aware).
- Flags inline per entity-links policy; country names link to country roster.

*Shipped notes:* `includes/amiga_activity_geography_selector.inc.php` on hosts + nations pages — control row + harness duel year bar + race cumulative lines (games metric). APIs: `amiga_community_slice_series.php`; `year_facts` dimensional slices with `keys` + `available_keys`. Lib: `amiga_community_slice_series()`, `amiga_community_geo_page_selection()`, `amiga_community_year_series_filled_for_keys_at_cutoff()`. Module: geography platform (`initGeographyPlatform`, `getGeoState`, `renderGroupedYearBar`, `renderRaceLines`); chip click toggles line visibility; shift+click removes a country from the race list. CSS under `body.k2-amiga-activity-charts .k2-amiga-act-geo-*`. HTTP smoke: APIs + pages 200 at present and `as=year:2005`.

**STOP:** duel + race behave on a dev harness page (or directly on slice-6 first panel) at present + TT cutoff; URL round-trip (paste URL → same selection) proven.

### Slice 6 — Geography Hosts (8 panels) — **Done** (Jul 2026)

- Registry §2 order; one duel state drives all Pattern-A charts on the page.
- GEO-009 stepped line tooltip: names the tournament that unlocked each new host country.

*Shipped notes:* `includes/amiga_activity_geography_hosts_panels.inc.php` — 6 geo-linked panels (games/tournaments/goals × duel year bar + race cumulative) wired through slice-5 selector state; 2 realm panels (distinct host countries year bar + cumulative stepped line with unlock tooltip via `mountHostCountriesCumulative()`). Harness charts removed from hosts selector (nations page keeps harness until slice 7). Module: generic `mountGeoDuelYear()` / `mountGeoRace()` + `registerGeoPanel()` + `refreshGeoAllPanels()` on selection change. Fixed stray `});` that had broken the module IIFE after slice 5.

### Slice 7 — Geography Nations (5 panels) — **Done** (Jul 2026)

- Registry §2 order; one duel state drives all Pattern-A charts on the page.

*Shipped notes:* `includes/amiga_activity_geography_nations_panels.inc.php` — appearances + goals duel/race panels (`player_nationality` slice via shared selector state) + realm `distinct_nationalities` year bar; harness preview charts removed from selector include. Reuses slice-6 `registerGeoPanel()` / `mountGeoDuelYear()` / `mountGeoRace()` unchanged. **Jul 2026+:** distinct-nationalities year bar hover tooltip — per-country active players (`year × player_nationality × active_players` stored fact; `year_facts` field `nationality_active_by_year`; `mountNationalitiesYear()` HTML tooltip).

### Slice 8 — Shape probes (IA-4 STOP gate — no UI) — **Done** (Jul 2026)

- Probe each `histogram` kind read-time at **four cutoffs**: first event, ~2007 peak, ~2015 mid, present. Record ms + row counts **in this section**.
- Guidance: < ~150 ms at present = ship read-time (S4/S5); worse → cache-in-API or propose **S6 store** (Dagh sign-off + DDL slice + Part B; storage policy §6 rule).
- Lock bucket edges from real distributions (policy §5.6 defaults are the starting point; note deviations here).

**STOP:** table of probe timings + chosen oracle per kind + final bucket edges recorded here before slice 9 starts.

*Shipped notes:* `includes/amiga_community_histogram_lib.php` — nine kinds, bucket defs (`amiga_community_histogram_bucket_defs()`), raw-value oracles, `amiga_community_histogram_compute()` + `amiga_community_histogram_probe()`; CLI `scripts/oneoff/amiga_community_histogram_probe.php` (4 cutoffs × 9 kinds). Probed on local **`ko2amiga_db`** Jul 2026.

#### Probe timings (ms, population, max raw value)

| kind | first event | year 2007 | year 2015 | present |
|------|------------:|----------:|----------:|--------:|
| career_games | 39 · 31 · max 15 | 100 · 304 · 794 | 161 · 435 · 1290 | **4 · 469 · 1520** |
| tournaments_played | 38 · 31 · 1 | 87 · 304 · 61 | 149 · 435 · 101 | **3 · 469 · 115** |
| distinct_opponents | 40 · 31 · 12 | 91 · 304 · 114 | 142 · 435 · 164 | **4 · 469 · 182** |
| active_years | 41 · 31 · 1 | 173 · 304 · 7 | **257 · 435 · 15** | **147 · 469 · 23** |
| countries_played | 45 · 31 · 2 | 108 · 304 · 6 | 139 · 435 · 9 | **3 · 469 · 10** |
| world_cups_played | 52 · 31 · 1 | 102 · 304 · 7 | 143 · 435 · 15 | **5 · 469 · 23** |
| rating | 45 · 31 · 1808 | 128 · 304 · 2346 | 130 · 435 · 2545 | **4 · 469 · 2601** |
| goal_sum | 76 · 143 · 17 | 120 · 11848 · 21 | 95 · 23054 · 26 | **93 · 27418 · 26** |
| tournament_games | 2 · 1 · 143 | 16 · 270 · 687 | 14 · 562 · 687 | **16 · 605 · 687** |

Cells: **ms · population · max_value**. First-event label: World Cup I (Dartford) · Nov 3, 2001.

#### Oracle per kind (locked)

| kind | oracle | population |
|------|--------|------------|
| career_games, tournaments_played, distinct_opponents, countries_played, rating | `player_snapshot` | players |
| world_cups_played | `player_snapshot+slice` | players |
| active_years, goal_sum | `game_scan` | players / games |
| tournament_games | `tournament_scan` | tournaments |

#### Bucket edges (locked — policy §5.6 defaults, no deviations)

Encoded in `amiga_community_histogram_bucket_defs()`: range buckets for career games / tournaments / distinct opponents / tournament games; exact 1…25 (`active_years`), 1…12 (`countries_played`), 0…24 (`world_cups_played`); rating 50-pt steps 650–2450 + tail; goal_sum exact 0…19 + 20+ tail. Max observed at present fits all schemes (see probe max_value column).

#### Slice 9 decision (STOP gate cleared)

- **Ship read-time for all nine kinds** — no S6 DDL, no cache-in-API layer in v1.
- **`active_years`** exceeds ~150 ms at historical cutoffs (173 ms @ 2007, 257 ms @ 2015) but **147 ms @ present**; acceptable with sequential panel loader — queue this panel **after** snapshot-backed histograms (slice 10 perf pass).
- **`goal_sum`** ~93 ms @ present (game scan over ~27k games) — ship read-time; borderline only if TT lands on heavy mid-era cutoffs (~120 ms @ 2007).
- Mid-cutoff slowness on snapshot kinds (e.g. career_games 161 ms @ 2015) is TT-only; present path is fast (3–5 ms).

### Slice 9 — Shape (9 panels) — **Done** (Jul 2026)

- `api/amiga_community_histogram.php` + panel mounts per slice-8 oracles/buckets; % of population in tooltips.

*Shipped notes:* `includes/amiga_activity_shape_panels.inc.php` — 9 histogram panels on `/amiga/activity/shape.php`; `mountHistogram()` + `renderHistogramBar()` in `js/amiga-activity-charts.js`; sequential loader queues snapshot kinds first, `goal_sum` then `active_years` last (slice-8 probe guidance). Tooltips: bucket + count + % of population (`data-k2-help` N/A — bar tooltips via `chart-theme.js`).

### Slice 10 — Polish + finish — **Done** (Jul 2026)

- Mobile pass: `touch-action: pan-y pinch-zoom` on panels; tooltips off coarse; heaviest panels (race multi-lines) last in loader queue.
- Cross-link audit (Countries hub, WC hub, tournament click-through carry).
- Registry parity check: 45 panels ↔ 46 ship IDs, per-panel checklist from [`activity-charts.md`](activity-charts.md) §6.
- Docs: this plan statuses; policy status line; [`url-routes.md`](url-routes.md); catalog plan step 6 → done; MEMORY; feature-log row (Part A). Part B only if S6 shipped.

*Shipped notes:* **Mobile** — `theme.css` coarse-pointer rule extended to `k2-amiga-activity-charts` panels/canvases (matches online Activity). Tooltips already off on coarse via `chart-theme.js` `activityChartOptions`. **Loader** — `buildPanelQueue()` mounts only panels present on the page; `loadTier: 'deferred'` queues geography **race** multi-lines + Shape `goal_sum` / `active_years` after lighter panels on that page. **Cross-links** — Geography intro → Countries hub (`amiga_url_with_context`); WC wing → World Cups hub (slice 4); geo chip roster hrefs carry `as=` via `K2AmigaTimeTravelUrl`; cumulative click-through already carried `as=` (slice 1). **Registry parity** — 45 panels = 46 ship IDs with **Q-VOL-004 + Q-SHP-010** merged in one cumulative-players panel (policy §4); all registry selectors wired in `amiga-activity-charts.js`.

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