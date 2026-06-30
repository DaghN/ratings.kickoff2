# Amiga time travel — policy

**Status:** **Phase 1 implemented** (Jun 2026) — local proof + CLI smoke green; staging browser sign-off when Dagh syncs.  
**Implementation plan (phase 1):** [`amiga-time-travel-implementation-plan.md`](amiga-time-travel-implementation-plan.md)

**Parent:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) · [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md)

**Related:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`amiga-profile-v0.md`](amiga-profile-v0.md)

---

## 1. Executive summary

**Time travel** is a realm-wide optional lens on the Amiga 500 hub. When active, derived-truth pages show the realm **as it stood after a chosen cutoff** (year, month, or event). When inactive, every page behaves exactly as today (**present** mode).

| Concept | Rule |
|---------|------|
| **Default** | **Present** — no `as` query param; reads `amiga_player_current`, `amiga_player_matchup_summary`, `amiga_generalstats` |
| **Time travel on** | URL carries `as=`; one shared cutoff drives all wired read paths |
| **UI name** | **Time travel** — header **Present day | Time travel**; snapshot label in stepper between chevrons |
| **Granularity** | **Year · Month · Event** — tab order in chrome (coarse → fine) |
| **Rollout** | **Incremental** — each surface opts in; **editorial / live-ops hub tabs** hidden under time travel (T13); snapshot-worthy tabs keep present order (T13b) |

There is **no** per-page “Current | Historical” split. One chrome control, one cutoff, navigation preserves context.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **T1** | **Single cutoff** | Year / month / event wings all normalize to one internal `{ tournament_id, event_date, chrono, label }` before any page logic |
| **T2** | **URL is authority** | Active time travel = `as` query param present and valid. Bookmarkable, shareable, back-button safe. No session-only primary state |
| **T3** | **Present by default** | Absent or invalid `as` → present reads; pages unchanged visually except optional “Enter time travel” entry |
| **T4** | **Link propagation** | When `as` is active, **all Amiga internal links** append the same param (hub tabs, LB wings, player pills, HoF links). Infrastructure ships with phase 1 |
| **T5** | **Incremental surfaces** | Player/archive pages wire cutoff reads in slices. **Transitional:** unwired player blocks may show present data with an optional note until wired — **not** for present-only hub tabs (T13) |
| **T6** | **No new routes fork** | Same PHP paths (`/amiga/leaderboards/rating.php`, `/amiga/player/profile.php`, …) — not a parallel `/amiga/history/…` tree |
| **T7** | **Commit boundary** | Cutoffs align with **tournament finalize** semantics (same as rating history V1). Month/year = last finalize on or before period end; empty periods repeat prior state |
| **T8** | **Wing tab order** | Chrome tabs: **Year · Month · Event** (coarse-to-fine browsing) |
| **T9** | **History tab removed** | **Jun 2026:** dedicated History hub tab and ladder page retired. Rating at cutoff lives on `/amiga/leaderboards/rating.php?as=`. Legacy `/amiga/history.php` 301 → rating LB (preserves `as=` / `wing`+`at`). |
| **T10** | **Excluded realms** | Online hub, Amiga **ops**, import tooling — **ignore** `as`; never enter time travel chrome |
| **T11** | **Streaks** | No time-travel surfaces for match streaks (Amiga product policy — non-authoritative columns) |
| **T12** | **Profile blocks deferred** | Hero rank/rating/games at cutoff **shipped** (`amiga_player_snapshot_lib.php`). Remaining profile career blocks — phase 2+ |
| **T13** | **Present-only hub tabs** | **News** (present realm landing), **Live tournaments** (last tab in present order), and future editorial hubs (e.g. **Misc**) appear **only** in Present day hub nav. When `as=` is active, **omit** them from the hub bar. Direct requests to those paths **drop `as=`** (302 to present URL). |
| **T14** | **Mode toggle — fixed homes (T19)** | Header **Time travel** from present → **`/amiga/leaderboards/rating.php?as=year:{first}`** always (first calendar year in catalog). Header **Present day** from time travel → **`/amiga/news.php`** always. Same paths from every page; no contextual path or `as=` on toggle entry. |
| **T19** | **Toggle vs ribbon** | **Present day \| Time travel** = mode boundary (fixed homes, T14). **Year · Month · Event ribbon** = move through time **inside** the lens while keeping the current view (except event wing on `tournament.php` — §5.1.1). |
| ~~**T14b**~~ | *(superseded by T19)* | ~~Player-wing contextual toggle entry~~ — ribbon remains; player Event stepping → [`with-player-stepper-policy.md`](with-player-stepper-policy.md). |
| ~~**T14c**~~ | *(superseded by T19)* | ~~Tournament-page contextual toggle entry~~ — event ribbon on `tournament.php` (§5.1.1) remains. |
| **T15** | **Uniform lens** | **Hub (time travel):** only snapshot-worthy tabs (T13b); all derived stats at cutoff. **Player wings:** hero, games, tournaments, opponents, profile — everything at cutoff when wired; preserve `as=` on links. No silent present-day numbers on wired surfaces. |
| **T13b** | **Time-travel hub bar** | When `as=` is active, hub bar = **present hub tabs minus editorial / live-ops (T13)**, same relative order. **Jun 2026:** **Leaderboards · World Cups · Tournaments · Countries · Games · Activity · Hall of Fame**. Implementation: `K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS` in `amiga_hub_nav_lib.php` — extend when adding snapshot-worthy tabs; add paths to T13 redirect list when adding editorial tabs. |
| **T16** | **No silent exit** | Links from active time travel must **preserve `as=`** on a cutoff-aware page, or **explicitly** open present-only content (T13 editorial redirect, labelled present-only link). Never drop `as=` without user intent. |
| **T17** | **Pre-debut at cutoff** | Valid `amiga_players` row with **no snapshot ≤ cutoff** (or zero games at cutoff): page **loads** (no 404). Hero rank · rating · games show **—** + muted note *Not on the ladder at this cutoff.* Wired tables may be empty; unwired blocks may still show present data with transitional note (T5). |
| ~~**T18**~~ | *(superseded Jun 2026)* | ~~Player-page implicit Event stepping + picker accents~~ — **slice 0:** remove; **slice 1+:** explicit **`as_with=`** on Event ribbon. [`with-player-stepper-policy.md`](with-player-stepper-policy.md). |

---

## 3. URL and cutoff semantics

### 3.1 Query param

Canonical param: **`as`**

| Form | Example | Meaning |
|------|---------|---------|
| `year:YYYY` | `as=year:2003` | Last finalize with `event_date` ≤ 31 Dec YYYY |
| `month:YYYY-MM` | `as=month:2003-11` | Last finalize with `event_date` ≤ last day of month |
| `event:ID` | `as=event:589` | Through tournament `ID` inclusive (catalog chrono) |

- Invalid or unknown keys → treat as **present** (or 400 on strict pages — implementation choice; prefer silent present for bad bookmarks).
- **Legacy alias:** `/amiga/history.php?…` 301 → `/amiga/leaderboards/rating.php` with the same `as=` (or `wing`/`at` mapped to `as=`).

### 3.2 Internal cutoff struct

All wired readers receive:

```text
{
  wing: year | month | event,
  key: string,              // e.g. "2003", "2003-11", "589"
  tournament_id: int,       // resolved cutoff event
  event_date: string,       // Y-m-d
  chrono: float,
  label: string             // display, e.g. "November 2003" or "World Cup XVII · Nov 2003"
}
```

Resolution logic reuses / generalizes [`amiga_rating_history_lib.php`](../site/public_html/includes/amiga_rating_history_lib.php) catalogs.

### 3.3 Chevrons and picker

Same behaviour as rating history pilot: prev/next step within active wing; jump picker; update `as` in URL. **`data-k2-carry-scroll`** on wing/chevron links when applicable.

**Event wing** uses two date formats: stepper label `M j, Y` (e.g. `Nov 14, 2006`); picker `M Y` (e.g. `Nov 2018`). Layout contract: §5.1.1.

---

## 4. Read-path register

### 4.1 Phase 1 (wired surfaces)

| Surface | Present source | Time travel source | Notes |
|---------|----------------|-------------------|--------|
| **Leaderboards** (all wings) | `amiga_player_current` | Last `amiga_player_event_snapshots` row per player ≤ cutoff | Sort wing metric on snapshot row; **rank column** = enumerate sorted result in PHP (not `elo_rank` column) |
| **Hall of Fame** | `amiga_generalstats` | `amiga_realm_snapshots` at cutoff | Full row incl. ratio leaders |
| **Opponents** W/D/L · Goals · DDs | `amiga_player_matchup_summary` | Latest `amiga_player_matchup_at_event` per opponent ≤ cutoff | `amiga_matchup_snapshot_lib.php`; H2H wing still placeholder |
| **Hero → games** | All rated `amiga_games` for player | Games in tournaments with chrono tuple ≤ cutoff | `amiga_snapshot_rated_game_cutoff_and_sql()` on rated-games subquery |
| **Player tournaments** | All `amiga_player_event_snapshots` rows | Snapshot rows with event tuple ≤ cutoff | `amiga_player_tournament_participation_rows()` |
| **Tournaments hub** | Full public catalog | Tournaments with chrono tuple ≤ cutoff | `amiga_tournament_index_rows()` + `amiga_snapshot_tournament_cutoff_and_sql()`; stats from `amiga_tournament_catalog_stats` (event-intrinsic) |

### 4.2 Present-only hub (editorial + live-ops)

| Surface | Rule |
|---------|------|
| **News** | Present realm **landing** — invitations, reports, interviews, editorial (T13) |
| **Live tournaments** (+ live tournament detail) | Contemporary sign-ups / in-progress — last hub tab in present order |
| **Misc** (future) | Editorial oddments — present-only when shipped |

Direct `?as=` on **editorial / live-ops** paths (News, live tournaments) → redirect strips time travel.

### 4.2b Snapshot hub tabs (time travel bar)

| Surface | Rule |
|---------|------|
| **Games** hub | `/amiga/games/recent.php` (+ Highlights · All games) — counts and rows at cutoff via `amiga_lb_games_count()` + snapshot SQL |
| **Tournaments** hub | `/amiga/tournaments.php` — catalog rows ≤ cutoff; filter pills + listboxes preserve `as=` (`k2_amiga_route('amiga-tournaments')`); **With videos** filter kept under TT (manifest discovery) |

### 4.3 Player wings (time-travel lens — wire at cutoff)

Player pills stay visible under time travel. Target **T15** + **T16**:

| Surface | Rule |
|---------|--------|
| **Hero** | Rank · rating · games at cutoff from snapshot + `amiga_player_elo_rank_at_event`; **—** + note when pre-debut (T17) | **Shipped** — `amiga_player_snapshot_lib.php` · `amiga_elo_rank_lib.php` |
| **Hero → games** | `amiga/player/games.php` — hero-viewport game table; filters narrow the set; list ≤ cutoff; **date column = event day only** (`M j Y`) | **Shipped** Jun 2026 |
| **Tournaments** (player) | Participation list ≤ cutoff | **Shipped** Jun 2026 |
| **`tournament.php` detail** | Ribbon + `as=` on entry redirects and in-page nav (**shipped** Jun 2026). Event stats / standings read finalize-time truth — unchanged after finalize; URL `as=event:` for a post-cutoff id is not reachable from TT catalog browse |
| **Opponents** tables | Shipped — `amiga_matchup_snapshot_lib.php` |
| **Profile rating chart** | Snapshot events ≤ cutoff; date x-axis ends at cutoff (`amiga_player_rating_history_payload`) | **Shipped** Jun 2026 |
| **Player Videos** | Game-linked manifest rows ≤ cutoff (`amiga_snapshot_rated_game_cutoff_and_sql` on index load) | **Shipped** Jun 2026 |
| **Profile** blocks | Career / honours at cutoff — phase 2+ |

### 4.4 Transitional defer (visible but not yet at cutoff)

| Surface | Reason |
|---------|--------|
| **Player profile** (blocks) | Hero/career snapshot reads — hero **shipped**; career blocks phase 2+ |
| **Activity** (hub) | Charts not at cutoff yet |
| **Opponents H2H** | Poster/picker/moments shipped; rank compare at cutoff shipped; rating compare date axis at cutoff **shipped** Jun 2026 |

### 4.5 Later phases (registry)

| Surface | Time travel source |
|---------|-------------------|
| **Player profile** — hero / career / honours | Player snapshot at cutoff |
| **Opponents H2H** (poster · moments · charts) | Pair games ≤ cutoff + stored pair row |
| Profile — moments, rating chart, perf highlight | Mixed; wire per slice |
| Activity / server aggregates | `amiga_community_stats` (+ snapshots at cutoff) |
| Rating chart overlay | Optional vertical marker at cutoff |

---

## 5. Chrome and IA

### 5.0 Chrome — product intent (Jun 2026)

Phase 1 proved the **data lens**: one `as=` cutoff, correct snapshot reads, link propagation. Browsing still felt like *the same page with different numbers* — correct but not *felt*. The **atmospheric chrome stack** records what we shipped to fix that.

| Layer | Role | Intent |
|-------|------|--------|
| **Entry warning** | Present-mode hover on header **Time travel** | Playful side-effects copy (`amiga_time_mode_nav_time_travel_help_text()`) — sets tone *before* the lens activates; honest about outdated stats without blocking exploration |
| **Temporal stamp** | LED date banner above the ribbon | Persistent **when** cue — “you are here in time” separate from navigation controls; sci-fi terminal mood (mono kicker + DSEG7 segments + blinking `_`) |
| **Snapshot ribbon** | Year · Month · Event stepper + picker | Functional **how you move** through time — unchanged phase-1 contract (§5.1) |
| **Hub chapter suppression** | Omit `k2-hub-chapter` on snapshot hub tabs when `as=` active | Stamp + ribbon own the landmark; avoid duplicating “where you are” with present-day section titles |
| **Rating LB Δ column** | Wing-step Elo change after Elo when `as=` active | Data companion to the stamp — “what moved since the previous step in this mode” |

**What success looks like**

- Time travel reads as a **mode**, not a hidden filter — even on pages the visitor already knows (rating LB, player hero, tournament detail).
- The stamp answers **when** at a glance; the ribbon answers **how to step**; tables answer **who was on top then**.
- Present day stays unchanged: no stamp, no ribbon, hub chapters remain.

**Temporal stamp — locked display rules (v1 static)**

| Rule | Detail |
|------|--------|
| **Placement** | Top of `k2-page-nav`: below wordmark / header mode toggle, **above** snapshot ribbon — on every Amiga page with active time travel (same surfaces as snapshot chrome; ops/import excluded) |
| **Kicker** | `››` prompt + wing label: **YEAR END REACHED** · **MONTH END REACHED** · **TEMPORAL LINK ESTABLISHED** (event) |
| **LED date** | From active wing picker key — **year** → `Y`; **month** → `MM . Y` (last day of selected month); **event** → cutoff tournament `event_date` as `DD . MM . YYYY` (DSEG7 `.` separator; swap `AMIGA_TT_STAMP_LED_FIELD_SEP` in stamp PHP to try `:`). Year/month wings follow chevron/picker selection, not resolved cutoff tournament date (empty periods repeat prior snapshot state for data, not for display). |
| **Typography** | DSEG7 Classic for LED segments only — display exception per [`design-direction.md`](design-direction.md) § Typography |
| **Motion** | **Ambient:** blinking `_` cursor (click to pause/resume; `localStorage`). **Toggle entry** (`k2_tt_entry=1`): whole panel fade-in + kicker typewriter. **Wing tab change** (`k2_tt_entry=wing`): kicker typewriter + **LED clock opacity fade** (1100ms; no panel rise). **Typewriter:** fixed **32 cps** for toggle and wing (`TYPEWRITER_CPS` in `k2-amiga-tt-stamp.js`). Stepper/picker/hub nav/direct URL do not trigger. Hover tooltip follows pointer near `_`. **Load:** stamp JS sync after markup. |
| **A11y** | `aria-label` plain English: *As of {j F Y}*; decorative kicker/LED `aria-hidden` |

**Rejected in this slice:** event name line under the stamp (redundant with ribbon stepper); animated segment rollover; hub-only stamp scope (stamp must follow the lens everywhere).

**Key files:** `includes/amiga_time_travel_stamp.php` (`amiga_time_travel_stamp_arrival_entry_query()` · `amiga_time_travel_stamp_wing_arrival_entry_query()`) · `js/k2-amiga-tt-stamp.js` (sync-loaded after stamp markup) · `includes/amiga_snapshot_chrome.php` (wing tabs + render order) · `stylesheets/theme.css` (`.k2-amiga-tt-stamp`, `k2-tt-stamp-led-fade`) · `stylesheets/k2-fonts.css` (DSEG7 `@font-face`).

### 5.1 Time travel chrome

**Header (Amiga only):** segment beside realm switcher — **Present day | Time travel** (`data-k2-carry-scroll` on nav — same scroll lock as hub pills). **Present day** always → **`/amiga/news.php`** (T19). **Time travel** from present → **`/amiga/leaderboards/rating.php?as=year:{first}`**; when already in the lens → rating LB with **active `as=`** (same as wordmark / realm home). In-lens time stepping uses the **ribbon**, not the toggle. In **present** mode only, hover **Time travel** for a `data-k2-help` tooltip (`amiga_time_mode_nav_time_travel_help_text()` — warning copy + side-effects punchline). `amiga_url_present()` strips `as=` on links — **not** used by the mode toggle.

**Hub bar (when `as=` active):** **Leaderboards · World Cups · Tournaments · Countries · Games · Activity · Hall of Fame** (T13b). Present-day order: **News · Leaderboards · World Cups · Tournaments · Countries · Games · Activity · Hall of Fame · Live** (last). News and Live are **hidden** under time travel.

**Ribbon (when `as=` active):** compact bar at the top of `k2-page-nav` — **below the temporal stamp**, **above** hub tabs, player hero, and player pills. One row (no wrap): **Year | Month | Event** wing tabs · chevrons + snapshot label · listbox picker · optional **with-player listbox** (Event wing — [`with-player-stepper-policy.md`](with-player-stepper-policy.md)). Year/Month wings: label only in stepper. **Event wing:** full layout contract in §5.1.1 (stepper link, picker widths, date formats).

**Table sort carry:** same-path ribbon navigation preserves active `k2_sort` / `k2_dir` (PHP hrefs + picker; JS refreshes ribbon after column sort). Cross-page links (hub tabs, other wings) do not carry sort indices.

When inactive: header segment only; no ribbon below/above nav.

**Hub chapter headers (when `as=` active):** omit `k2-hub-chapter` on snapshot hub tabs — Leaderboards (`amiga_lb_nav.php`), **Countries** (`amiga/countries.php`), Activity, Hall of Fame, **Tournaments** (`amiga/tournaments.php`). **Leaderboards:** no chapter in present day either (Jun 2026). **World Cups:** chapter **shown** under `as=` with snapshot WC count + `(except the Covid)` once first missed season has passed.

**Temporal stamp (when `as=` active, v1 static):** see §5.0 for product intent. Implementation: shared `.k2-amiga-tt-stamp` in `k2-page-nav`, **below wordmark / above snapshot ribbon** on every Amiga page with active time travel. Render: `amiga_time_travel_stamp_render($ctx)` from `amiga_snapshot_chrome_render_active()`; helper in `includes/amiga_time_travel_stamp.php`.

**Rating LB Δ column (when `as=` active):** Leaderboards → Rating only — extra **Δ** column after Elo; wing-step change vs previous snapshot in the active wing (same rules as [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) §3.5). `amiga_lb_rating_delta_map()` + cell helpers in `amiga_lb_snapshot_lib.php`. Column tooltip: title **Rating change**; body *Change in Elo rating since the previous snapshot in the chosen mode (year, month, or event).* — `k2_lb_amiga_rating_delta_column_help_attrs()`.

#### 5.1.1 Event ribbon layout (shipped Jun 2026)

Applies when Event granularity is active (`as=event:…`). Ribbon `<section>` gets `k2-amiga-time-travel--event-wing`; inline CSS vars size the stepper and picker from the realm event catalog.

| Zone | Content | Format / behaviour |
|------|---------|-------------------|
| **Stepper label** | Tournament name + event date | `M j, Y` (e.g. `Nov 14, 2006`); primary white link (`k2-amiga-history__label--link`), **not** linkstar |
| **Stepper link** | `/amiga/tournament.php?id={cutoff_tournament_id}#tournament` | Active `as=` via `amiga_url_with_as_param()` in `amiga_snapshot_chrome_render_stepper()`. User stays in time travel on the tournament page (T16) |
| **Event wing on `tournament.php`** | Chevrons, picker, and mismatched bookmarks | **`id` tracks cutoff event** — `amiga_snapshot_chrome_nav_href()` + picker carry + `amiga_tournament_apply_time_travel_event_id_redirect()` keep the tournament detail in sync with `as=event:{id}` (Jun 2026) |
| **Picker closed** | Tournament name · date | Name left, date **right-aligned** in a fixed-width box; date `M Y`. Width = catalog longest name + fixed date column (not `name+date` char sum) |
| **Picker open** | Full realm catalog (newest first) | Panel width **matches** closed trigger; each row name left · date right. When **`as_with=`** active ([`with-player-stepper-policy.md`](with-player-stepper-policy.md)), participated events for that player may use linkstar on **name and date** (secondary) |

**Width CSS vars** (computed in `amiga_snapshot_chrome_event_layout_style()`):

| Variable | Rule |
|----------|------|
| `--k2-amiga-tt-stepper-width` | Longest stepper label (`name · M j, Y`), capped ~28rem |
| `--k2-amiga-tt-picker-width` | `max(nameLen)×0.4rem` + ~4.75rem date slot + padding, cap ~19rem |

**Closed trigger layout:** CSS grid `minmax(0, 1fr) auto` with `0.4rem` gap — date sits at the right edge of the catalog-width box; short names leave space between text and date (intentional).

**`tournament.php` link carry:** `amiga_tournament_href()` wraps tournament URLs with active `as=`. **Event wing:** sets `as=event:{id}` for the **linked** tournament (player list, profile, games column — avoids redirect to ribbon cutoff). **Year/Month wing:** preserves current cutoff. Chevron/picker on `tournament.php` still sync `id` via `amiga_snapshot_chrome_nav_href()` + `amiga_tournament_apply_time_travel_event_id_redirect()` when picker submits a stale hidden `id`.

**Key files**

| File | Role |
|------|------|
| `includes/amiga_snapshot_chrome.php` | Ribbon markup, width vars, stepper tournament link |
| `includes/amiga_rating_history_lib.php` | `event_date_label` (stepper), `event_date_picker_label` (picker) |
| `includes/k2_archive_listbox.php` | `triggerShowsMeta` split trigger + option meta |
| `js/k2-archive-listbox.js` | `trigger-meta` + linkstar sync on commit |
| `stylesheets/theme.css` | `.k2-amiga-time-travel--event-wing` ribbon + picker grid |
| `includes/amiga_tournament_lib.php` | `amiga_tournament_href()` |

**Probes:** `scripts/oneoff/amiga_snapshot_context_probe.php` · `scripts/oneoff/amiga_tournament_tt_link_probe.php`

**Not in this slice:** tournament page standings / event-stats **data** at cutoff (§4.4) — chrome and URL lens only.

### 5.2 Copy rules

Use **time travel** / **as of** / **present** consistently. Avoid mixing "historical", "snapshot", "archive" in user-facing chrome.

### 5.3 Player wings vs hub (T15)

| Lens | Scope |
|------|--------|
| **Hub time travel** | Snapshot realm: LBs, Activity, HoF (T13b) |
| **Player wings** | Full player story at cutoff: hero, **hero → games**, tournaments, opponents, profile |

**Hero → games** = `amiga/player/games.php` hero-viewport table (deliberate viewpoint from the subject). Opponent (and other filters) narrow that list; not a separate “opponent games” page.

### 5.4 Unwired player sections (transitional)

Player/archive pages still wiring cutoff reads may show present-day blocks briefly; prefer **wire in clusters** (hero + games tab, etc.) over permanent mixed pages. Optional muted note until wired — target **T15** (no silent mix).

### 5.5 Hub tab matrix

| Tab | Present hub | Time-travel hub |
|-----|-------------|-----------------|
| News | Yes (landing) | **Hidden** (T13) |
| Leaderboards | Yes | Yes (T13b) |
| World Cups | Yes | Yes (T13b) |
| Countries | Yes | Yes (T13b) |
| Activity | Yes | Yes (T13b) |
| Hall of Fame | Yes | Yes (T13b) |
| Tournaments | Yes | Yes (T13b) — catalog ≤ cutoff; filters preserve `as=` |
| Live tournaments | Yes (last) | **Hidden** (T13) |
| Games hub | `/amiga/games/recent.php` | Yes | Yes (T13b) |
| Misc (future) | Yes | **Hidden** (T13) |

Implementation: `includes/amiga_hub_nav_lib.php` · `amiga_snapshot_redirect_present_only_page()`.

---

## 6. PHP architecture

| Module | Role |
|--------|------|
| `includes/amiga_player_event_stepper_lib.php` | *(retire on ship)* T18 — superseded by [`with-player-stepper-policy.md`](with-player-stepper-policy.md) |
| `includes/amiga_player_snapshot_lib.php` | Hero + `amiga_player_load()` at cutoff |
| `includes/amiga_elo_rank_lib.php` | Persisted `elo_rank` reads (present + time travel) |
| `includes/amiga_snapshot_context.php` | Parse `as`, resolve cutoff, `is_active()`, `cutoff()`, `label()`, `query_suffix()` |
| `includes/amiga_snapshot_url.php` (or helpers on context) | `amiga_url_with_context($path, $query)` |
| `includes/amiga_snapshot_chrome.php` | Ribbon HTML; Event wing layout vars; stepper tournament link |
| `includes/amiga_time_mode_nav.php` | Header Present day \| Time travel; fixed homes T14/T19 |
| `includes/amiga_time_travel_stamp.php` | Hub temporal stamp (LED date + kicker) when `as=` active |
| `includes/amiga_tournament_lib.php` | `amiga_tournament_href()`; event-wing `tournament.php` sync (§5.1.1) |
| `includes/k2_archive_listbox.php` | Shared listbox; Event picker `triggerShowsMeta` + split rows |
| Generalized catalog | Extend `amiga_rating_history_lib.php` or thin wrapper — **do not** duplicate cutoff SQL |
| Read libs | Branch: `$ctx->isActive()` → snapshot table path |

**Rule:** Pages never parse `as` directly after phase 1 foundation slice.

---

## 7. Data authority (unchanged)

Time travel does **not** add tables or writers. It only changes **read paths** against existing snapshot stores:

- [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) — player timeline
- [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) — pair timeline
- [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) — realm / HoF timeline

**Migration:** **L0** — PHP read-path only; **no Part B** for time travel tracks unless a slice adds DDL (not expected).

---

## 8. Verification (sign-off)

| Check | Expect |
|-------|--------|
| Present mode | With no `as`, byte-identical behaviour to pre-time-travel pages (regression) |
| Link carry | Hub → LB → HoF preserves `as=`; present-only hub pages strip `as=`; header search → Amiga profile preserves `as=` |
| Enter time travel (toggle, present) | Always rating LB + `as=year:{first}` (T14/T19) |
| Present day toggle (from time travel) | Always `/amiga/news.php` (T19); `amiga_url_present()` strips `as=` on in-page links — not the toggle |
| Time travel toggle (already in lens) | Rating LB + active `as=` (wordmark parity) |
| Player before debut at cutoff | Hero — / — / — + note; no 404 (T17) |
| With player filter | Opt-in **`as_with=`** on Event ribbon; picker linkstar secondary — [`with-player-stepper-policy.md`](with-player-stepper-policy.md) (T18 removed slice 0) |
| Event stepper → tournament | Link lands on `tournament.php` with same `as=`; WC redirects keep `as=` |
| Event wing on tournament.php | Chevrons / picker / `as=event:` change `id` to cutoff tournament (302 when mismatched) |
| Event picker layout | Catalog-fixed width; closed date right-aligned; open panel = trigger width |
| Temporal stamp | Visible on hub + player wings + tournament detail with `as=`; kicker matches wing; LED date matches picker (year/month period end; event cutoff date) |
| Stamp toggle arrival | One-shot `k2_tt_entry=1` from present → time travel; panel fade + 32 cps kicker typewriter; param stripped from URL |
| Stamp wing arrival | Year · Month · Event tabs append one-shot `k2_tt_entry=wing`; 32 cps kicker + 1100ms LED opacity fade (no panel rise); stepper/picker do not trigger |
| Hub chapters under `as=` | Snapshot hub tabs omit `k2-hub-chapter`; present day keeps chapters |
| Rating LB Δ under `as=` | Column after Elo; wing-step delta; tooltip title **Rating change** |
| Hub nav under `as=` | Leaderboards · World Cups · Tournaments · Countries · Games · Activity · HoF (T13b) |
| Tournaments hub under `as=` | Catalog row count ≤ present; filter URLs carry `as=`; hub chapter omitted |
| Profile with `as` active | Present-day data until wired (T12); target T15 |
| LB rating at event X | Matches rating wing at same cutoff (snapshot ladder oracle) |
| HoF at year Y | Holder fields match `amiga_realm_snapshots` row at resolved cutoff |

---

## 9. Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Per-page Current / Historical tabs | Clutter; duplicates picker; breaks cross-navigation |
| Separate historical URL tree | Double maintenance; breaks link propagation |
| Session-only time travel state | Not shareable; confusing on refresh |
| Big-bang all pages before ship | Blocks launch; incremental registry is intentional |
| Dense day-level picker | Finalize boundary is event-level; month/year sufficient |
| Partial profile in phase 1 | Profile has substantial future work; half-wired page adds confusion |

### Future (not scheduled)

| Idea | Notes |
|------|--------|
| **Present-mode tournament stepper** | Hub **Tournaments** tab is a long flat list; Event-wing time-travel chevrons + picker are the best tournament browse UX today. A similar stepper on the present hub tab (no `as=` lens) is a plausible follow-on — out of scope until scheduled. |

---

## 10. Agent policy

- **T13b–T19** hub IA + player pre-debut + player event stepper + **T19 fixed toggle homes**: snapshot-only time-travel bar; player wings at cutoff; mode toggle = News ↔ rating LB + `as=`; ribbon for in-lens time; no silent `as=` exit on wired links (T16).
- Do not wire games/tournament catalogs or profile until explicitly scheduled in §4.4.
- After phase 1 ship: UPDATE_DOCS Part A; feature-log row; cross-link from `amiga-data-contract.md` authority map.
