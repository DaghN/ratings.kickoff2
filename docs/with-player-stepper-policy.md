# With player — stepper filter policy

**Status:** **Locked (Jun 2026)** — **all slices shipped** plus post-track extensions: **`as_with=`** (TT Event ribbon), **`id_with=`** + **`id_country=`** (tournament chevrons), **`start_with=`** (league periods), **filter auto-snap** on all three surfaces.  
**Implementation plan:** [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md) · **Module map:** §10

**Parent:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) (C02 sticky TT, C13 with-player stepper)

**Related:** [`navigation-model.md`](navigation-model.md) · [`k2-archive-listbox.php`](../site/public_html/includes/k2_archive_listbox.php) · [`k2_league_period_page.php`](../site/public_html/includes/k2_league_period_page.php) · [`design-direction.md`](design-direction.md) (link-star, segment grammar)

---

## 1. Executive summary

**With player** is an **opt-in navigation filter** on timeline steppers. When active, prev/next chevrons skip slices where the chosen player had **no rated activity**, and land only on slices where they did.

| Concept | Rule |
|---------|------|
| **Default** | **Off** — chevrons step the full catalog for that control |
| **Activation** | User deliberately picks a player from a **listbox** — never auto-enabled from page context |
| **URL authority** | **Separate query param per surface** (see §3) — each filter is scoped to the axis that control steps. Bookmarkable; propagated only within that surface's link family |
| **Realms** | **Amiga** (tournament chevrons + time-travel Event ribbon) and **Online** (league period chevrons) — **one product idea**, **three independent URL/filter implementations** |
| **T18** | Amiga time-travel implicit player-page Event stepping — **retire in slice 0** (before new filters ship) |

**User question answered:** *“Step through time, but only where this person was actually playing.”*

League **period buckets** and Amiga **tournament events** are the same idea at different grains — both are time slices; the filter ignores inactive slices. **Do not** unify URL state across surfaces — on `tournament.php` under time travel, TT ribbon and tournament chevrons can both be visible and both legitimately filtered to the same or different players.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WP1** | **Opt-in only** | Never auto-enable any with-player param from player profile, tournaments table, H2H, or any referrer. No tips, nudges, or onboarding chrome. |
| **WP2** | **Player listbox control** | Alphabetical player list (archive listbox pattern). **No typeahead/search box**. First row = **clear / off** (cancel line). When a player is selected, the **closed trigger** uses **link-star** accent (`k2-link-star`) — primary visual signal. |
| **WP3** | **Player list source** | Players with **≥ 1 rated game** in the active realm only. Sort **A–Z by display name**. |
| **WP4** | **Per-surface URL params** | Each stepper owns its param — tied to the query key it moves (§3.4). Absent, zero, or unknown player id → filter off for **that surface only** (silent ignore; no 404). |
| **WP5** | **Scoped link propagation** | Each param propagates only on links that stay in the **same surface family** and preserve the stepped axis. Never drop a with-player param without user intent (cancel row). **Do not** copy TT filter params onto unrelated surfaces or vice versa. |
| **WP6** | **Shared lookup only — not shared URL/engine** | **Share:** participation key-set query per realm + optional pure helper `{ catalog, current_key, participated_set, direction } → next/prev key` (no URL knowledge). **Separate:** param names, parse/append helpers, form actions, chevron href builders, propagation rules per surface. No unified “stepper controller” that tries to infer context. |
| **WP7** | **TT ribbon granularity** | **`as_with=`** filters stepping on **Year, Month, and Event** wings (participation keyed by calendar year, YYYY-MM month, or tournament id). |
| **WP8** | **Present + time travel** | Tournament chevrons work in **both** present day and active time travel. TT ribbon filter requires active `as=` (Event wing). |
| **WP9** | **Snapshot clamp (TT)** | When `as=` is active, TT Event chevrons **must not step forward past the resolved cutoff**. Same clamp with or without `as_with=`. Tournament chevrons use their own catalog clamp rules under TT (§5.4). |
| **WP10** | **Retire T18 first (slice 0)** | Remove path-based auto-stepping on `/amiga/player/…` **before** shipping new filters. After slice 0, all pages use realm-global Event stepping until `as_with=` ships. Implicit picker accents without an explicit filter param are removed with T18. |
| **WP11** | **Chevron reference UX** | Online **league period** chevrons (`k2_league_period_render_period_steps_html`) are the visual reference: half-pill prev/next, `data-k2-carry-scroll`. Amiga tournament chevrons match. |
| **WP12** | **Pairs with sticky TT** | CD track sticky (C02 **retired** Jul 2026) — `as_with=` scrubbing on long pages; not a baseline dependency |
| **WP13** | **Independent filters on one page** | On `tournament.php` with time travel, **TT ribbon** (`as_with=` → steps `as=`) and **tournament row** (`id_with=` → steps `id=`) are independent. Either, both, or neither may be set. They must not share one URL param or one listbox state. |
| **WP14** | **Retire tournament `id` follows `as=` (slice 0)** | On tournament entity pages, TT Event ribbon steps **`as=` only** — **`id=` stays fixed**. **`amiga_tournament_href()`** carries active `as=` only — never rewrites to `as=event:{linked id}`. Snapshot changes = TT picker (or future steppers), not inbound links. |
| **WP15** | **Tournament step — separate from TT** | Tournament chevrons are **not** a variant of the TT ribbon stepper. **Do not** reuse `amiga_snapshot_context.php` stepping, `amiga_snapshot_chrome.php` stepper render, or `as_with` URL helpers for `id=` navigation. Share only participation lookup + pure step-key helper (WP6). |
| **WP16** | **Tournament step — three layers** | **(1) Catalog** — which tournament ids are steppable. **(2) Stepping** — prev/next key within catalog. **(3) Href resolver** — wing-preserving URL for target `id=`. Catalog filters and wing fallback are **orthogonal** — do not merge into one function. |
| **WP17** | **Tournament step — wing intent + fallback** | Chevron hrefs **preserve navigation intent** (event-stats, games, videos mode, standings/stages scope). Resolve per-target availability; **default fallback = event-stats**. Reuse existing tournament URL helpers (`amiga_tournament_videos_resolve_mode()`, `amiga_tournament_stages_entry_url()`, …) — not TT chrome. |
| **WP18** | **Filter auto-snap — separate entry per surface** | When a step filter is active and the current slice is off-filter, **302** to nearest eligible neighbor (prev, else next). **Do not** hook all surfaces through one controller or through late page loads (e.g. snapshot context after HTML). Each surface owns its redirect entry — §5.8. |

---

## 3. Surfaces register

### 3.1 Amiga — tournament page chevrons

| Item | Rule |
|------|------|
| **Pages** | Tournament entity folder (`/amiga/tournament/{event-stats,standings,games,videos,…}.php?id=`) — PHP-first via `amiga_tournament_page.php` (redirects before HTML) |
| **Placement** | Fixed position **to the right of the tournament segment nav**. Same row: **prev · next · WC-only pill · with-player listbox · host-country listbox** (§5.7). |
| **URL params** | **`id_with={player_id}`** · **`id_country={host country name}`** · **`id_wc=world-cup`** (WC-only toggle) — separate controls; filter bag ANDs them |
| **Catalog** | Public tournament catalog in chrono order (≤ TT cutoff when `as=` active); narrowed by active **step filter stack** (§5.7) |
| **Activity test (player)** | `amiga_player_event_snapshots`, `NumberGames > 0` |
| **Match test (country)** | Tournament index host country — `amiga_tournament_index_matches_country_filter()` |
| **Faceted counts** | Listbox `meta` = tournament count in stepping catalog after cross-filter (player ↔ country ↔ WC-only) |
| **Visibility** | Present day and time travel |
| **Stepping target** | Updates tournament **`id=`** only (tournament chevrons). TT ribbon may change **`as=`** independently (WP14). |
| **Off-filter snap** | **302** on page entry when any step filter active and current `id=` off-filter — §5.8 |
| **Wing on step** | Preserve current wing **intent** with per-target fallback — §5.6 |
| **Implementation** | Tournament-local modules only — §5.5, §10; **not** TT ribbon reuse (WP15) |

### 3.2 Amiga — time-travel ribbon (Year · Month · Event)

| Item | Rule |
|------|------|
| **When** | Active `as=` on snapshot ribbon (any wing) |
| **Placement** | Ribbon control row — listbox beside wing chevrons / snapshot picker |
| **URL param** | **`as_with={player_id}`** — filters stepping of **`as=`** for the **active wing** (year / month / event keys) |
| **Catalog** | Full realm wing catalog (cutoff-truncated when `as=` active) |
| **Stepping target** | Updates `as={wing}:{key}`; does not replace tournament `id_with=` |
| **Off-filter snap** | When `as_with=` is active and the current year/month/event is off-filter → **302** to nearest eligible slice (prev, else next). Entry: `includes/amiga_as_with_snap.php` via `amiga_page_preamble.php` **before DOCTYPE**. |
| **Picker accents** | When `as_with=` active, participated years/months/events for that player may use link-star in the open picker list (secondary). Primary signal = listbox trigger |

### 3.3 Online — league period chevrons (extend)

| Item | Rule |
|------|------|
| **Pages** | `league.php` (`cup` + `period` + `start=`) |
| **Placement** | Beside existing period chevrons on standings header row |
| **URL param** | **`start_with={player_id}`** — filters stepping of **`start=`** only |
| **Catalog** | League periods for active `cup` + `period`, within Status bounds |
| **Activity test** | Player had ≥ 1 rated game in that league period |
| **Off-filter snap** | When `start_with=` is active and current `start=` is off-filter → **302** to nearest eligible period (prev, else next). Entry: `k2_start_with_snap_bootstrap.php` before DOCTYPE on `league.php`. |
| **Player list source** | Online ladder players with ≥ 1 rated game |

### 3.4 URL param summary

| Surface | Param | Steps | Propagate on |
|---------|------------|-------|--------------|
| TT Event ribbon | `as_with` | `as=` (Year / Month / Event wing) | Amiga internal links that preserve `as=` (TT-aware propagation) |
| Tournament entity | `id_with` | `id=` (via catalog) | Tournament folder URLs (`/amiga/tournament/…`) |
| Tournament entity | `id_country` | `id=` (via catalog) | Same tournament folder family as `id_with` |
| League period | `start_with` | `start=` | `league.php` peer links (`cup` + `period` preserved) |

**Future tournament-step catalog filters** (e.g. `id_wc`) — same propagation family as `id_with` / `id_country`; see §5.7.

**Example (both filters on tournament page):**  
`/amiga/tournament/event-stats.php?id=94&as=event:94&as_with=354&id_with=354` — legitimate. TT chevrons respect `as_with=`; tournament chevrons respect `id_with=`; changing one listbox does not alter the other param.

---

## 4. Control specification

### 4.1 With-player listbox (per surface)

| Rule | Detail |
|------|--------|
| **Pattern** | `k2_archive_listbox_render()` + `k2-archive-listbox.js` — same widget, **different form field name** per surface (`as_with`, `id_with`, `start_with`) |
| **Idle / off row** | First option — clear label (copy TBD; must read as cancellation). Submit omits that param |
| **Player rows** | Display name; value = numeric `player_id` |
| **Selected state** | Closed trigger shows player name + **`k2-link-star`** on trigger |
| **No search** | Full scrollable list (WP2) |

### 4.2 Chevron stepper

| Rule | Detail |
|------|--------|
| **Markup** | `k2-player-games-day-steps` — league period reference |
| **Disabled ends** | Catalog bounds or TT forward clamp |
| **Carry-scroll** | `data-k2-carry-scroll` on stepper + listbox form |
| **Sort carry** | Preserve `k2_sort` / `k2_dir` on same-path steps where applicable |

---

## 5. Stepping semantics

### 5.1 Filter off (default)

Prev/next use the **full catalog** for **that surface's control** — realm-global Event stepping everywhere after T18 removal.

### 5.2 Filter on (surface param set)

1. Load participation key-set for the player (realm-appropriate query).
2. **Forward:** next catalog key after current where player participated.
3. **Backward:** previous catalog key before current where player participated.
4. No key in direction → chevron disabled.

Pure key resolution may call a shared helper; **href building stays per surface**.

### 5.3 Time-travel cutoff (`as_with=` / TT Event chevrons)

When `as=` resolves to a cutoff:

- Event catalog entries **after** cutoff excluded from TT stepping.
- Forward chevron disabled at cutoff even if player played later events in present day.
- `as_with=` filtering applies within the truncated catalog.

### 5.4 Tournament chevrons under time travel (`id_with=`)

- Tournament catalog may be truncated to events ≤ cutoff when `as=` is active (same visibility rules as TT catalog browse).
- `id_with=` filters within that truncated set.
- **`id_with=` does not change `as=`**; **`as_with=` does not change `id=`** — only each control's own chevrons/listbox.

### 5.5 Tournament step architecture (slice 2 — locked)

Tournament entity chevrons are a **separate control** from the TT Event ribbon. Same product vocabulary (chevrons, opt-in listboxes, URL authority), **different code path**.

| Layer | Responsibility | Share with TT? |
|-------|----------------|----------------|
| **Catalog builder** | Base public list (chrono asc; ≤ cutoff under TT) → apply **filter bag** → **eligible key set** (not a shortened catalog) | **No** — TT uses wing catalogs / `as=` |
| **Step resolver** | `{ catalog, current_key, eligible_key_set } → prev_key / next_key` | **Yes** — `k2_participation_step_keys()` (eligible set = output of filter stack) |
| **Href resolver** | Target `id=` + current **nav intent** → wing-preserving tournament URL | **No** — TT uses `amiga_url_with_as_param()` |

**Slice 2 ships `id_with=` only**, but the catalog builder must accept a **filter bag** from day one so later catalog filters slot in without refactoring chevron render or href logic.

**Do not build:** a unified “stepper controller” that switches on path between `as=` and `id=` (WP6, WP15).

### 5.6 Wing-preserving hrefs (slice 2 — locked)

When a chevron changes `id=`, the visitor should stay on the **same kind of tournament page** when the target supports it.

**Nav intent** (captured from the current request — not a blind copy of the path string):

| Field | Examples |
|-------|----------|
| Top-level view | `event-stats`, `games`, `videos`, `standings`, `stages` |
| Standings scope | `scope`, `scope_key`, WC vs non-WC mode |
| Videos sub-mode | `games` / `atmosphere` when on videos folder paths |

**Resolution order** for target tournament id:

1. Same intent if the target supports it (reuse existing tournament helpers).
2. Relax within the same view (e.g. videos: atmosphere ↔ games via `amiga_tournament_videos_resolve_mode()`).
3. Fall back one top-level view when the wing is absent (e.g. videos → event-stats when `amiga_tournament_has_videos()` is false).
4. Standings/stages: target-aware entry (`amiga_tournament_stages_entry_url()` / league default) — do not copy invalid `scope_key`.
5. **Universal last resort: event-stats** (tournament entity “home”).

Resolve at **href-build time** (chevron links land on valid URLs). Existing page-load redirects (e.g. video mode 302) remain a safety net.

**Do not carry on `id=` step (v1):** games tab player filter, video deep links (`v=`, `game=`, `t=`), table sort — unless a later slice explicitly adds them.

### 5.7 Tournament step catalog filters (shipped v1 + extensions)

Entity-page chevrons expose a **subset** of tournament-index filter ideas as **stepping catalog filters** — same mental model as the hub table (“browse tournaments, but only this slice”), different surface.

| Rule | Detail |
|------|--------|
| **Composition** | Filters **AND** together: `eligible = base_catalog ∩ filter₁ ∩ filter₂ ∩ …` |
| **Separate URL params** | One param per filter axis — **`id_*` prefix** on entity pages (not hub `wc=` / `country=`). |
| **Propagation** | Tournament folder URLs only — alongside `id_with`, `id_country`, `as=`, scope when applicable. Never on TT ribbon or unrelated Amiga links (WP5). |
| **Controls** | Listboxes: cancel row, link-star when active, no search, faceted `meta` counts; **host-country option rows** may carry `flag_html` (decorative 20×15 img — closed trigger text-only). **WC-only:** Shuffle-style pill toggle (jukebox grammar) — first filter after chevrons. Adjacent triggers in one filter row — see `amiga-tournament.css` § step nav filters. |
| **Matching logic** | Reuse tournament index matchers — **do not** duplicate rules in the step nav include. |
| **Step helper** | `k2_participation_step_keys()` on eligible key set (player filter builds a set; country/WC filters use index matchers). |
| **Empty eligible set** | Both chevrons **disabled**; page still renders for requested `id=` — no 404. |
| **Unknown / non-catalog `id=`** | Both chevrons **disabled**. |

**Shipped filter axes:**

| Axis | Param | Notes |
|------|-------|-------|
| With player | `id_with` | Slice 2 |
| Host country | `id_country` | Country **name string** (index semantics), listbox after WC pill |
| WC only | `id_wc=world-cup` | Pill toggle (off = param absent); reuses index WC matcher |

**Plausible extensions (not shipped):**

| Axis | Example param | Notes |
|------|---------------|-------|
| Non-WC only | `id_wc=not-world-cup` | Mirror index `not-world-cup` if needed later |

Adding a filter = new param + parse/append helper + bag field + one listbox — **not** a change to TT ribbon or href fallback rules.

### 5.8 Filter auto-snap (302)

When the user applies or lands with an **active step filter** but the **current slice is off-filter**, redirect once to the **nearest eligible** neighbor — **prefer previous** (back in chrono), else **next**. Same rule as chevron nearest-neighbor stepping. If no eligible neighbor exists, the page loads with both chevrons disabled.

**Critical:** redirect must run **before any HTML output**. Do **not** rely on `amiga_snapshot_context_from_request()` on pages that emit `<!DOCTYPE` first — headers are already sent.

| Surface | Entry (PHP) | When |
|---------|---------------|------|
| **TT Event** | `amiga_as_with_snap.php` ← `amiga_page_preamble.php` | Event wing + valid `as_with` |
| **Tournament** | `amiga_tournament_apply_step_filter_snap_redirect()` in `amiga_tournament_page.php` | Any active step filter in bag |
| **League** | `k2_start_with_snap_bootstrap.php` | First line of `league.php` before DOCTYPE |

**TT preamble pages (Jun 2026):** all `/amiga/leaderboards/*.php` wings + `hall-of-fame.php`. Other Amiga pages that output HTML before DB connect need the same preamble line when `as_with` snap should apply.

**Shared math only (WP6):** `k2_participation_step_keys()` / optional `k2_participation_snap_target_key()` — **not** a unified redirect controller.

**Do not carry on snap redirect (v1):** table sort params on tournament step (unless already on URL); video deep links.

---

## 6. T18 and tournament id-sync retirement (slice 0)

**Ship before** new with-player filters (WP10, WP14).

| Remove | Notes |
|--------|-------|
| `amiga_player_event_stepper_applies()` path sniffing | Player URLs behave like hub URLs for Event stepping |
| Player-only prev/next in `amiga_snapshot_context.php` | Realm-global default |
| Implicit picker accents on player paths | No accent without explicit `as_with=` (slice 1) |
| `amiga_tournament_apply_time_travel_event_id_redirect()` | No 302 when `id` ≠ `as=event:{id}` |
| Event-wing `as=event:{linked id}` in `amiga_tournament_href()` | Preserve active `as=` only |
| `amiga_tournament_snapshot_as_param()` | Dead helper removed |
| `amiga_snapshot_chrome_nav_href()` tournament id rewrite | TT chevrons on tournament page keep page `id=` |
| Picker hidden `id` forced from `as=event:` | Carry actual page `id=` |
| `amiga_player_event_stepper_lib.php` | Delete; participation query returns in slice 1 |

**Retired with slice 0:** `amiga_tournament_apply_time_travel_event_id_redirect()`, Event-wing `as=event:{linked id}` rewrite in `amiga_tournament_href()`, dead `amiga_tournament_snapshot_as_param()`.

Probe: player-path Event next = hub-path; tournament page with mismatched `id`/`as=` loads without redirect.

---

## 7. Cross-realm parity

| Surface | Param | Catalog grain | Activity signal |
|---------|-------|---------------|-----------------|
| Amiga TT Event ribbon | `as_with` | Tournament event | `amiga_player_event_snapshots` |
| Amiga tournament chevrons | `id_with` | Tournament event | Same |
| Amiga tournament chevrons | `id_country` | Tournament event (host) | Tournament index row country |
| Online league chevrons | `start_with` | League period (`start=`) | `player_period_games` / rated games in period |

**Same UX and listbox behaviour; different URL params and propagation.** Shared participation lookup across Amiga surfaces; online uses `ratedresults` / league membership reads.

---

## 8. Implementation slices (summary)

Full checklist: [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md).

| Slice | Deliverable |
|-------|-------------|
| **0** | Retire T18 — no new UI |
| **1** | `as_with=` + listbox on TT Event ribbon + participation lookup helper |
| **2** | Tournament chevrons + `id_with=` + `id_country=` + filter auto-snap |
| **3** | League period row + `start_with=` + filter auto-snap |

**Migration level:** **L0** — read-time only; no new stored tables.

---

## 9. Out of scope (v1)

| Item | Notes |
|------|-------|
| With player on TT **Year / Month** wings | Deferred |
| Auto-enable from player pages | Rejected (WP1) |
| Search/typeahead player picker | Rejected (WP2) |
| Single global `with=` param | Rejected (WP4, WP13) |
| Unified stepper controller / shared URL engine | Rejected (WP6, WP15) |
| Session-only filter state | Rejected — URL is authority |
| New DB columns / post-game writers | Not expected |
| **`id_wc` and other index filters on tournament chevrons** | Architecture in §5.7 — not shipped |
| Unified filter auto-snap controller | Rejected — §5.8 per-surface entry (WP18) |

---

## 10. Implementation reference (agents)

| Module | Surface | Role |
|--------|---------|------|
| [`amiga_participation_step_lib.php`](../site/public_html/includes/amiga_participation_step_lib.php) | Shared | Amiga participation keys; `k2_participation_step_keys()`; optional `k2_participation_snap_target_key()` |
| [`amiga_as_with_url.php`](../site/public_html/includes/amiga_as_with_url.php) | TT | `as_with` parse + query append |
| [`amiga_as_with_snap.php`](../site/public_html/includes/amiga_as_with_snap.php) | TT | Event-wing filter auto-snap (302) |
| [`amiga_page_preamble.php`](../site/public_html/includes/amiga_page_preamble.php) | TT | Early hook before DOCTYPE on TT-heavy pages |
| [`amiga_snapshot_context.php`](../site/public_html/includes/amiga_snapshot_context.php) | TT | Chevron `prev_key` / `next_key` override when `as_with` + Event wing — **not** auto-snap |
| [`amiga_id_with_url.php`](../site/public_html/includes/amiga_id_with_url.php) | Tournament | `id_with` parse + append |
| [`amiga_id_country_url.php`](../site/public_html/includes/amiga_id_country_url.php) | Tournament | `id_country` parse + append |
| [`amiga_id_wc_url.php`](../site/public_html/includes/amiga_id_wc_url.php) | Tournament | `id_wc` parse + append + toggle href |
| [`amiga_tournament_step_catalog.php`](../site/public_html/includes/amiga_tournament_step_catalog.php) | Tournament | Filter bag, catalog, step keys, snap target |
| [`amiga_tournament_step_href.php`](../site/public_html/includes/amiga_tournament_step_href.php) | Tournament | Wing-preserving hrefs + `amiga_tournament_apply_step_filter_snap_redirect()` |
| [`amiga_tournament_step_nav.php`](../site/public_html/includes/amiga_tournament_step_nav.php) | Tournament | Chevrons + listboxes render |
| [`k2_start_with_url.php`](../site/public_html/includes/k2_start_with_url.php) | League | `start_with` parse + append |
| [`k2_league_period_with_player.php`](../site/public_html/includes/k2_league_period_with_player.php) | League | Participation + filtered adjacent periods + snap apply |
| [`k2_start_with_snap_bootstrap.php`](../site/public_html/includes/k2_start_with_snap_bootstrap.php) | League | Early hook on `league.php` |

**Probes:** `scripts/oneoff/amiga_snapshot_context_probe.php` · `amiga_tournament_step_probe.php` · `k2_league_period_step_probe.php`

**CSS:** [`amiga-tournament.css`](../site/public_html/stylesheets/amiga-tournament.css) — step nav filter row (adjacent listboxes, ghost trigger width like `games/all` Rating picker).

**Smoke URLs (local):**

| Case | URL |
|------|-----|
| TT filter + snap | `/amiga/leaderboards/rating.php?as=event:27&as_with=62` → 302 to nearest played event |
| TT filter stepping | `/amiga/leaderboards/rating.php?as=event:{id}&as_with=73` |
| Tournament filters | `/amiga/tournament/event-stats.php?id=598&id_with=…&id_country=…&id_wc=world-cup` |
| League filter + snap | `/league.php?cup=points&period=month&start=…&start_with=…` |

---

## 11. Changelog

| Date | Change |
|------|--------|
| 2026-07-02 | **`id_wc=world-cup`** — WC-only pill on tournament chevrons (Shuffle-style); faceted counts + snap + propagation; §3.1 / §5.7 / §10. |
| 2026-06-30 | **Doc sweep** — §5.7 shipped (`id_country`), §5.8 auto-snap entry points (WP18), §10 module map; TT preamble page list; league/tournament snap docs aligned. |
| 2026-06-30 | Track **complete** — slice 3 `start_with=`; post-track **`id_country`**, faceted counts, filter auto-snap all surfaces. |
| 2026-06-30 | **WP15–WP17 + §5.5–§5.7** — tournament step architecture (three layers, wing fallback, future catalog filters with AND composition); slice 2 extensibility without TT reuse. |
| 2026-06-30 | Slice 1 shipped — explicit `as_with=` on Event ribbon replaces T18. |
| 2026-06-30 | **WP14** — retire tournament `id` follows TT `as=` (slice 0); tournament chevrons own `id=` navigation. |
| 2026-06-30 | Planning revision — per-surface params (`as_with`, `id_with`, `start_with`); shared participation lookup only; T18 slice 0 first; independent filters on tournament+TT (WP13). |
| 2026-06-30 | Initial policy — creative session alignment. |