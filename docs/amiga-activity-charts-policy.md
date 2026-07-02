# Amiga Activity charts — IA & product policy

**Status:** **Shipped** (Jul 2026) — chart track **complete** (45 panels / 46 ship IDs on `/amiga/activity/`). Closes catalog-plan §9.1 **IA-1 · IA-2 · IA-3 · IA-5** and step **6**. **IA-4** (histogram probes) cleared in implementation plan slice 8.
**Implementation track:** [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md)
**Questions (product source):** [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) — 46 ship IDs
**Method / storage:** [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) · [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) (storage shape — do not reopen)
**UI pattern:** online [`activity-charts.md`](activity-charts.md) · **Time travel:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · **URLs:** [`url-routes.md`](url-routes.md) + [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md)

---

## 1. North star

Turn the stored community-stats truth (**605 event snapshots** Nov 2001 – Nov 2025 · year facts 2001–2025 · ~27.4k games · 469 players · **12 host countries** · **21 nationalities**) into a question-led chart feast about how the KOA scene grew, spread geographically, played World Cups, and shaped match texture — split into **six wings** so each page is one story, every chart is **time-travel aware**, and country selectors + click-through curves make the section a rabbit hole rather than a dashboard.

Design gift vs online Activity: **every cumulative-curve point is a real tournament.** Tooltips carry tournament names; points click through to tournament pages (§7.3).

---

## 2. Naming

| Decision | Rule |
|----------|------|
| Hub tab label | **Activity** (short tab labels; the Amiga hub bar is already realm-scoped) |
| Chapter title | **N years of the KOA** (N = calendar year minus 2001) |
| Chapter lede | Question-led invite (Growth · People · World Cups · Texture); *The charts in the wings below…* |
| KOA identity | **Summary panel lede** (*Since 2001, … official KOA Amiga tournaments.*) above stat cards; chapter title = *N years of the KOA* |
| Summary panel | Five stat cards (Goals · Draws · Double digits · Clean sheets · **Busiest year**) plus *Players average …* line — shared across all wings, above wing tabs. Busiest year = peak realm games in one calendar year at cutoff (`games · YYYY` note). |

---

## 3. IA — Activity sub-hub (six wings, seven leaf pages)

**IA-1 (split) + IA-2 (grouping & order) resolved.** Foldered sub-hub per [`url-routes.md`](url-routes.md) § Sub-hub navigation; wing order is a narrative arc: *how much → who → where → the big stage → how it felt → what the community is made of*.

| Wing | Leaf page | Panels | Question IDs |
|------|-----------|--------|--------------|
| **Growth** *(default)* | `/amiga/activity/growth.php` | 7 | VOL-001 · 002 · 005 · 006 · 007 · 008 · ECO-004 |
| **People** | `/amiga/activity/people.php` | 5 | VOL-003 · VOL-004+SHP-010 (merged) · SHP-009 · 001 · 002 |
| **Geography — Hosts** | `/amiga/activity/geography/hosts.php` | 8 | GEO-001 · 002 · 004 · 014 · 003 · 013 · 008 · 009 |
| **Geography — Nations** | `/amiga/activity/geography/nations.php` | 5 | GEO-005 · 007 · 006 · 015 · 010 |
| **World Cups** | `/amiga/activity/world-cups.php` | 6 | WC-001 · 003 · 002 · 011 · 006 · 007 |
| **Texture** | `/amiga/activity/texture.php` | 5 | TEX-007 · 006 · 008 · 009 · 013 |
| **Shape** | `/amiga/activity/shape.php` | 9 | SHP-007 · 008 · 014 · 015 · 003 · 004 · 016 · 005 · 006 |

**46 question IDs → 45 panels** (one merge, §4). Every leaf lands at 5–9 charts — inside the 6–10 sub-wing heuristic; the sequential loader only ever handles its own wing.

### 3.1 Navigation chrome

- Sub-nav row (wing tabs): **Growth · People · Geography · World Cups · Texture · Shape** — copied from the World Cups hub shell pattern (`amiga_world_cups_hub_shell_start.inc.php` + wing nav include).
- **Geography** gets a nested second row **Host nations · Nationalities** (pattern: `world-cups/stats/*` nav).
- Chapter header + lede render on **every** wing via the shared shell (`k2_hub_chapter.inc.php` pattern).
- The existing **summary tiles** block ([`amiga_activity_summary.php`](../site/public_html/includes/amiga_activity_summary.php)) stays on **Growth only**, above the charts.

### 3.2 Routes & redirects

`K2_AMIGA_ROUTES` additions:

| Route key | Path |
|-----------|------|
| `amiga-activity` | `amiga/activity/growth.php` (hub default) |
| `amiga-activity-growth` | `amiga/activity/growth.php` |
| `amiga-activity-people` | `amiga/activity/people.php` |
| `amiga-activity-geography` | `amiga/activity/geography/hosts.php` (Geography default) |
| `amiga-activity-geography-hosts` | `amiga/activity/geography/hosts.php` |
| `amiga-activity-geography-nations` | `amiga/activity/geography/nations.php` |
| `amiga-activity-world-cups` | `amiga/activity/world-cups.php` |
| `amiga-activity-texture` | `amiga/activity/texture.php` |
| `amiga-activity-shape` | `amiga/activity/shape.php` |

- Hub nav lib (`amiga_hub_nav_lib.php`) Activity href updates to the Growth route.
- Legacy `/amiga/activity.php` → **302** to Growth, preserving query (`as=`, `as_with`, filters).
- Query params on wing pages are **filters only** (`?hosts=`, `?nats=`) — never `?view=` / `?wing=` / `?tab=`.

---

## 4. Panel consolidations & respected cuts

| Decision | Rule |
|----------|------|
| **VOL-004 ≡ SHP-010 merge** | `NumberOfPlayers` = `PlayersDebuted` at every snapshot (a debut is what makes a player; verified 469/469, 468/468, …). **One panel** — *Cumulative player* — satisfies both IDs. |
| **Q-TEX-012 stays cut** | No online-style combined 4-line texture chart, even though it would be cheap. Each texture rate is its own bar chart. |
| **Scope** | This policy consumes exactly the **46 ship IDs** — no resurrections from the cut log, no new questions without a catalog row first. |
---

## 5. Wing specs

Every wing opens with a one-line question header + short intro copy (`k2-activity-section` head pattern from online Activity). Panel order below is page order.

### 5.1 Growth — "How much did the scene play?" (7 panels)

Each volume metric is a **pair**: year bars, then the cumulative event-timeline line directly below. The bar answers "which years were big?"; the line answers "how did we get to 27,418?". Pairs beat toggles: rhythm and accumulation in one scroll, and panels stay 1:1 with question IDs.

**Section intro (time travel):** Era call-outs in the Growth lede (*mid-2000s boom*, *lean mid-2010s*, *modern revival*) appear only once the cutoff calendar year has lived through that beat — thresholds **2008 / 2018 / 2022** (`amiga_activity_growth_panels.inc.php`). Present mode uses the current calendar year (all three). Early cutoffs get the rhythm sentence without era names.

| # | Panel | ID(s) | Design |
|---|-------|-------|--------|
| 1 | Games per year | Q-VOL-001 | Hero bar, pitch tone. The 2007 peak (2,888) vs the 2020 trough (56) is the story of the scene — intro copy points at it. |
| 2 | Cumulative games | Q-VOL-002 | 605-point line vs `event_date`. Tooltip: tournament name + date + running total. Click-through (§7.3). |
| 3 | Tournaments per year | Q-VOL-005 | Bar, chrome tone. |
| 4 | Cumulative tournaments | Q-VOL-006 | Line; near-perfect staircase — slope changes show scene tempo. |
| 5 | Goals per year | Q-VOL-007 | Bar. |
| 6 | Cumulative goals | Q-VOL-008 | Line. |
| 7 | Avg games per tournament per year | Q-ECO-004 | Closing bar (derive at read: VOL-001 ÷ VOL-005). "Did events get bigger or smaller?" |

### 5.2 People — "Who's playing?" (5 panels)

| # | Panel | ID(s) | Design |
|---|-------|-------|--------|
| 1 | Active players per year | Q-VOL-003 | Hero bar. |
| 2 | New players per year | Q-SHP-009 | Bar directly beneath #1, same x-axis span — reads as "of the actives, how many were fresh blood?" |
| 3 | Cumulative player | Q-VOL-004 + Q-SHP-010 | One line (merge, §4). Tooltip on steep steps: e.g. "+12 debuts at World Cup V". Click-through. |
| 4 | Distinct opponent pairs per year | Q-SHP-001 | Bar — "how socially mixed was each year?" |
| 5 | Cumulative distinct pairs | Q-SHP-002 | Line to ~6,978 — the community handshake graph filling in. Click-through. |

### 5.3 Geography — "Where in the world was Kick Off?" (8 + 5 panels, two modes)

The wow wing; resolves **IA-3** (§6). With only 12 host countries and 21 nationalities, full dropdowns with inline flags beat search pickers.

**`geography/hosts.php` — "Who's hosting tournaments?"**

| # | Panel | ID(s) | Pattern |
|---|-------|-------|---------|
| 1 | Games hosted per year | Q-GEO-001 | A — duel bars |
| 2 | Cumulative games hosted | Q-GEO-002 | B — race lines |
| 3 | Tournaments hosted per year | Q-GEO-004 | A |
| 4 | Cumulative tournaments hosted | Q-GEO-014 | B |
| 5 | Goals hosted per year | Q-GEO-003 | A |
| 6 | Cumulative goals hosted | Q-GEO-013 | B |
| 7 | Distinct host countries per year | Q-GEO-008 | Plain bar (realm) |
| 8 | Cumulative distinct host countries | Q-GEO-009 | Stepped line 1→12 — "the map filling in"; tooltip names the tournament that unlocked each new country |

**`geography/nations.php` — "Where do we come from?"**

| # | Panel | ID(s) | Pattern |
|---|-------|-------|---------|
| 1 | Appearances per year by nationality | Q-GEO-005 | A — duel bars |
| 2 | Cumulative appearances | Q-GEO-007 | B — race lines |
| 3 | Goals per year by nationality | Q-GEO-006 | A |
| 4 | Cumulative goals by nationality | Q-GEO-015 | B |
| 5 | Distinct nationalities per year | Q-GEO-010 | Plain bar |

Country names in control rows link to `/amiga/country/roster.php?country=` — geography charts are an on-ramp to the Countries universe.

### 5.4 World Cups — "How big was the big stage?" (6 panels)

Community WC lens — deliberately distinct from the World Cups hub per-event tables; intro copy cross-links both ways.

| # | Panel | ID(s) | Design |
|---|-------|-------|--------|
| 1 | WC games per year | Q-WC-001 | Hero bar with **total realm games as muted ghost bars behind** — instant context, both series already ship. |
| 2 | WC share of each year's games | Q-WC-003 | % bar (derive at read: WC games ÷ realm games). |
| 3 | Cumulative WC games | Q-WC-002 | Line to ~8,449 (`WcGamesPlayed` snapshots). Click-through. |
| 4 | WC goals per game per year | Q-WC-011 | Bar with **realm goals-per-game overlaid as a line** (TEX-007 data) — "is the WC tighter than regular play?" |
| 5 | Nations at the World Cup per year | Q-WC-006 | Bar. |
| 6 | WC players per year | Q-WC-007 | Bar. |

### 5.5 Texture — "How did the games feel, year by year?" (5 panels)

All five are year-local rate bars (L3, derived at the API from stored numerators). Quality move: **each bar chart gets a horizontal all-time-average reference line** from the headline stats at cutoff (`GoalsPerGameAverage`, `DrawsRatio`, `DoubleDigitsRatio`, `CleanSheetsRatio`; high-scoring average derived) — every year visibly reads as scratchier or wilder than the era average, at zero storage cost.

| # | Panel | ID(s) |
|---|-------|-------|
| 1 | Goals per game per year *(hero)* | Q-TEX-007 |
| 2 | Draw rate per year | Q-TEX-006 |
| 3 | Double-digit rate per year | Q-TEX-008 |
| 4 | Clean-sheet rate per year | Q-TEX-009 |
| 5 | High-scoring rate per year (sum ≥ 10) | Q-TEX-013 |

### 5.6 Shape — "What is the community made of?" (9 panels)

Histograms at cutoff (C8). Order runs people → games → events, most personal first. Tooltips give count **and % of population**. Time travel is most magical here: the same histogram at `as=year:2005` vs present shows the community growing its long tail.

| # | Panel | ID(s) | Buckets (defaults, §10) |
|---|-------|-------|-------------------------|
| 1 | Players by career games | Q-SHP-007 | 1–9 · 10–24 · 25–49 · 50–99 · 100–249 · 250–499 · 500–999 · 1000+ |
| 2 | Players by tournaments played | Q-SHP-008 | 1 · 2–4 · 5–9 · 10–19 · 20–49 · 50+ |
| 3 | Players by distinct opponents | Q-SHP-014 | 1–4 · 5–9 · 10–19 · 20–49 · 50–99 · 100+ |
| 4 | Players by active calendar years | Q-SHP-015 | Exact N (1…25) — hero-profile crossover stat |
| 5 | Players by countries played in | Q-SHP-003 | Exact N (1…12) |
| 6 | Players by World Cups played | Q-SHP-004 | Exact N |
| 7 | Rating distribution | Q-SHP-016 | 50-pt buckets (smaller field than online; 50 gives shape) |
| 8 | Games by total goals | Q-SHP-005 | Exact 0…19, then 20+ |
| 9 | Tournaments by game count | Q-SHP-006 | 1–9 · 10–24 · 25–49 · 50–99 · 100+ |
---

## 6. Country selectors (IA-3 resolved)

Two control patterns, both Geography-only in v1:

### Pattern A — Country duel (single-country year bars)

- Compact control row above the chart: **[flag ▾ Country A] vs [flag ▾ Country B]** — two-series grouped bars in two accent colours.
- Defaults: **England vs Germany** (top two by all-time volume) for both hosts and nations pages.
- Change → client-side refetch → chart update in place → `history.replaceState` so `?hosts=England,Italy` / `?nats=England,Greece` is bookmarkable and shareable. **No full page reload.**
- PHP prefills the selects from GET on first render; invalid names fall back to defaults.

### Pattern B — Cumulative race (multi-line)

- Default series: **top 5 countries by all-time volume at the current cutoff**, one line each.
- Race country list toggles lines (click hide/show; shift+click remove); **"+ add country"** dropdown appends a series (cap ~7).
- Same URL state mechanics as Pattern A (CSV param drives the full series list when present).

### Shared rules

- Dropdown option lists come from the API response (`available_keys`) — i.e. **countries that exist at the current cutoff** (time travel keeps pickers honest).
- Inline flags per [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) conventions — duel field beside trigger, flat race country list (flag + link), and **each country row in the listbox dropdown panel**; country names link to the country entity page.
- One duel state per page (`?hosts=` on hosts.php, `?nats=` on nations.php) drives **all Pattern A charts on that page** — consistent comparisons, fewer controls.

---

## 7. Cross-cutting chart design

### 7.1 Chrome stack (copy of proven online v2)

- `body.k2-amiga-activity-charts`; **one** JS module `js/amiga-activity-charts.js` (panel registry + sequential `drain()`, boot via `k2OnPageReady`).
- `.k2-chart-panel` + `.k2-chart-frame` (960px cap, fixed frame heights); `chart-theme.js` tokens + dark tooltips; Chart.js 4.4.7 + date-fns adapter.
- **No** per-chart boot files, **no** canvas `%`/`aspect-ratio` sizing, **no** `Chart.defaults` mutation ([`activity-charts.md`](activity-charts.md) §8 rules apply verbatim).
- Panel registry doc: implementation plan §2 is the parity contract (like online §5).

### 7.2 Tooltips

- L2 event-timeline lines: title = **tournament name**, body = date + value. This is the rabbit-hole detail — every point has a name.
- Year bars: year + value; partial years under TT labelled (§8).
- Histograms: bucket + count + % of population.
- Phone: Chart.js tooltips off on coarse pointers; `touch-action: pan-y pinch-zoom` (online mobile rules).

### 7.3 Click-through on cumulative curves

- Desktop: Chart.js `onClick` on an L2 line point navigates to that tournament (`/amiga/tournament/event-stats.php?id=`), carrying `as=`/`as_with` via [`k2-amiga-time-travel-url.js`](../site/public_html/js/k2-amiga-time-travel-url.js) helpers. Precedent: H2H chart → games click-through.
- Coarse pointers: skip (no click nav where tooltips are disabled).
- Cursor: `pointer` on point hover; panel hint line mentions clickability once per page.

### 7.4 Overlays and reference lines

- **Ghost bars**: WC games per year renders realm games behind in a muted tone (both series ship).
- **Reference lines**: texture bars carry the all-time average at cutoff as a horizontal line; WC goals-per-game bar carries the realm per-year rate as a line overlay.
- No new question IDs are created by overlays — they reuse shipped series.

---

## 8. Time travel semantics

All 46 questions are TT-tagged **yes**. Rules (per [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §3.4):

| Concern | Rule |
|---------|------|
| API fetches | Pass **`as=` only** — never with-player params |
| Cutoff resolution | API resolves `as` → cutoff `tournament_id` (`amiga_snapshot_context_from_request`), then reads facts/snapshots `WHERE tournament_id = cutoff` — single indexed read (facts are keyed per finalize event) |
| Year bars under TT | Stop at the cutoff year; the cutoff-row facts hold partial-year counts, so the last bar is honestly partial — tooltip: *"through World Cup XVII · Nov 2003"* |
| Cumulative lines under TT | End at the cutoff event |
| Pickers under TT | Option lists = slice keys present at cutoff (`available_keys` from the cutoff facts read) |
| Navigation | Sub-nav + wing links preserve `as=`/`as_with` via `k2_amiga_route()` + `amiga_url_with_context()`; selector `replaceState` URLs keep `as=` untouched |
| Hub bar | Activity is already in the T13b time-travel hub tab set — no change |

---

## 9. Chart APIs (read-only families)

Five JSON endpoints under `site/public_html/api/` cover all 45 panels; shared read helpers live in `amiga_community_stats_lib.php` (general `amiga_community_facts_query()` + snapshot series helper). Exact params: implementation plan §3.

| Endpoint | Serves | Source |
|----------|--------|--------|
| `amiga_community_year_facts.php` | All L1 year bars (realm, host_country, player_nationality, world_cup; optional `keys=` CSV) | `amiga_community_stat_facts` year rows at cutoff |
| `amiga_community_snapshot_series.php` | Realm cumulative lines (whitelisted headline columns) | `amiga_community_stats_snapshots` |
| `amiga_community_slice_series.php` | Per-country cumulative race lines | `all_time` facts across snapshots |
| `amiga_community_year_rates.php` | L3 derived rates + reference values | Year facts numerators + headline averages |
| `amiga_community_histogram.php` | Shape wing | Player/game state at cutoff (oracle per probe outcome) |

**No finalize writers, no DDL** in this track. If a Shape probe forces an S6 histogram store, that is a separate DDL slice with Dagh sign-off + Part B registers (storage policy applies).

---

## 10. Histogram buckets (IA-5 defaults)

- Bucket schemes in §5.6 are **defaults**, locked only after the C8 probes (implementation plan slice 8) confirm real distributions — adjust bucket edges there without reopening this policy.
- Long-tail metrics (career games, tournaments, opponents, tournament size) share the "1-ish · small · medium · large · huge" shape philosophy; small-domain metrics (countries, WCs, active years, goal sum) stay exact-N.
- API returns buckets server-side (labels + counts + population) so chart JS stays dumb.

---

## 11. Catalog-plan §9.1 decision register

| # | Decision | Resolution |
|---|----------|------------|
| **IA-1** | One page vs split URLs | **Split** — foldered sub-hub, six wings / seven leaf pages (§3) |
| **IA-2** | Section grouping + order | **Growth · People · Geography (Hosts/Nations) · World Cups · Texture · Shape** — narrative arc (§3, §5) |
| **IA-3** | Multi-line geography UX | **Duel bars (A) + race lines (B)** — defaults from all-time volume at cutoff, URL param state, client refetch (§6) |
| **IA-4** | C8 histogram probes | **Open by design** — probe gate = implementation plan slice 8; no Shape UI before probes recorded |
| **IA-5** | Histogram bucket policy | **Defaults locked in §5.6/§10**; edges adjustable at probe slice |

---

## 12. Agent notes

- Read **this policy** + the implementation plan before touching Activity chart code; do not re-derive IA in-chat.
- Question set authority stays with the catalog — new charts need a catalog row (ship) first.
- URL shape authority: [`url-routes.md`](url-routes.md) + [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md); nav chrome: [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md).
- Chart JS/CSS hard rules: [`activity-charts.md`](activity-charts.md) §3/§8 (frame sizing, no per-chart boot files, tooltip theme).
- TT link carry + API `as=`-only: [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §3.4.
- Finish: each shipped slice = UPDATE_DOCS Part A (MEMORY line + plan status); Part B only if an S6 store ships.

---

## 13. Related docs

| Doc | Relationship |
|-----|--------------|
| [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md) | Sliced build track + panel registry (parity contract) |
| [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) | The 46 ship questions (product source) |
| [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) | Method, lenses, §9.1 IA register (closed by this doc) |
| [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) | Storage shape (headline + facts + snapshots) |
| [`activity-charts.md`](activity-charts.md) | Online chart architecture — pattern source |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Cutoff semantics for all chart reads |
| [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) | Per-WC event table (complementary product) |

*Policy locked Jul 2026 — chart-track IA agreed in chat (Activity page preparation summary follow-up session).*