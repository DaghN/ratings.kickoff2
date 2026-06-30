# With player — stepper filter policy

**Status:** **Locked (Jun 2026)** — **all slices shipped** (`as_with=` TT ribbon, `id_with=` tournament chevrons, `start_with=` league periods).  
**Implementation plan:** [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md)

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
| **WP7** | **v1 granularity (Amiga TT)** | **Event wing only** on the time-travel ribbon. Year / Month wings stay realm-global in v1. |
| **WP8** | **Present + time travel** | Tournament chevrons work in **both** present day and active time travel. TT ribbon filter requires active `as=` (Event wing). |
| **WP9** | **Snapshot clamp (TT)** | When `as=` is active, TT Event chevrons **must not step forward past the resolved cutoff**. Same clamp with or without `as_with=`. Tournament chevrons use their own catalog clamp rules under TT (§5.4). |
| **WP10** | **Retire T18 first (slice 0)** | Remove path-based auto-stepping on `/amiga/player/…` **before** shipping new filters. After slice 0, all pages use realm-global Event stepping until `as_with=` ships. Implicit picker accents without an explicit filter param are removed with T18. |
| **WP11** | **Chevron reference UX** | Online **league period** chevrons (`k2_league_period_render_period_steps_html`) are the visual reference: half-pill prev/next, `data-k2-carry-scroll`. Amiga tournament chevrons match. |
| **WP12** | **Pairs with sticky TT** | Optional pinned TT ribbon (**C02**) — `as_with=` scrubbing on long pages is a primary use case; not a v1 dependency. |
| **WP13** | **Independent filters on one page** | On `tournament.php` with time travel, **TT ribbon** (`as_with=` → steps `as=`) and **tournament row** (`id_with=` → steps `id=`) are independent. Either, both, or neither may be set. They must not share one URL param or one listbox state. |
| **WP14** | **Retire tournament `id` follows `as=` (slice 0)** | On tournament entity pages, TT Event ribbon steps **`as=` only** — **`id=` stays fixed**. **`amiga_tournament_href()`** carries active `as=` only — never rewrites to `as=event:{linked id}`. Snapshot changes = TT picker (or future steppers), not inbound links. |
| **WP15** | **Tournament step — separate from TT** | Tournament chevrons are **not** a variant of the TT ribbon stepper. **Do not** reuse `amiga_snapshot_context.php` stepping, `amiga_snapshot_chrome.php` stepper render, or `as_with` URL helpers for `id=` navigation. Share only participation lookup + pure step-key helper (WP6). |
| **WP16** | **Tournament step — three layers** | **(1) Catalog** — which tournament ids are steppable. **(2) Stepping** — prev/next key within catalog. **(3) Href resolver** — wing-preserving URL for target `id=`. Catalog filters and wing fallback are **orthogonal** — do not merge into one function. |
| **WP17** | **Tournament step — wing intent + fallback** | Chevron hrefs **preserve navigation intent** (event-stats, games, videos mode, standings/stages scope). Resolve per-target availability; **default fallback = event-stats**. Reuse existing tournament URL helpers (`amiga_tournament_videos_resolve_mode()`, `amiga_tournament_stages_entry_url()`, …) — not TT chrome. |

---

## 3. Surfaces register

### 3.1 Amiga — tournament page chevrons (new)

| Item | Rule |
|------|------|
| **Pages** | Tournament entity folder (`/amiga/tournament/{event-stats,standings,games,videos,…}.php?id=`) |
| **Placement** | Fixed position **to the right of the tournament segment nav**. Same row: **prev · next · with-player listbox** (more catalog filters may follow — §5.7). |
| **URL param (v1)** | **`id_with={player_id}`** — player participation filter on the stepping catalog |
| **Catalog** | Public tournament catalog in chrono order (≤ TT cutoff when `as=` active); narrowed by active **step filter stack** (§5.7) |
| **Activity test** | `amiga_player_event_snapshots`, `NumberGames > 0` |
| **Visibility** | Present day and time travel |
| **Stepping target** | Updates tournament **`id=`** only (tournament chevrons). TT ribbon may change **`as=`** independently (WP14). |
| **Wing on step** | Preserve current wing **intent** with per-target fallback — §5.6 |
| **Implementation** | Tournament-local modules only — §5.5; **not** TT ribbon reuse (WP15) |

### 3.2 Amiga — time-travel ribbon (Event wing)

| Item | Rule |
|------|------|
| **When** | Active `as=` and **Event** wing on snapshot ribbon |
| **Placement** | Ribbon control row — listbox beside Event chevrons / event picker |
| **URL param** | **`as_with={player_id}`** — filters stepping of **`as=`** (Event wing) only |
| **Catalog** | Full realm event catalog (cutoff-truncated when `as=` active) |
| **Stepping target** | Updates `as=event:{id}`; does not replace tournament `id_with=` |
| **Picker accents** | When `as_with=` active, participated events for that player may use link-star in the open event list (secondary). Primary signal = listbox trigger |

### 3.3 Online — league period chevrons (extend)

| Item | Rule |
|------|------|
| **Pages** | `league.php` (`cup` + `period` + `start=`) |
| **Placement** | Beside existing period chevrons on standings header row |
| **URL param** | **`start_with={player_id}`** — filters stepping of **`start=`** only |
| **Catalog** | League periods for active `cup` + `period`, within Status bounds |
| **Activity test** | Player had ≥ 1 rated game in that league period |
| **Player list source** | Online ladder players with ≥ 1 rated game |

### 3.4 URL param summary

| Surface | Param (v1) | Steps | Propagate on |
|---------|------------|-------|--------------|
| TT Event ribbon | `as_with` | `as=` (Event wing) | Amiga internal links that preserve `as=` (TT-aware propagation) |
| Tournament entity | `id_with` | `id=` (via catalog) | Tournament folder URLs (`/amiga/tournament/…`) |
| League period | `start_with` | `start=` | `league.php` peer links (`cup` + `period` preserved) |

**Future tournament-step catalog filters (§5.7)** use **separate params** on the same propagation family — not merged into `id_with` or TT params.

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

### 5.7 Tournament step catalog filters (future — design locked, UI deferred)

The tournament hub index (`/amiga/tournaments.php`) already filters by WC, host country, type, videos, etc. Entity-page chevrons may later expose a **subset** of those ideas as **stepping catalog filters** — same mental model (“browse tournaments, but only this slice”), different surface.

**Not in slice 2 scope** — document now so slice 2 code stays extensible.

| Rule | Detail |
|------|--------|
| **Composition** | Filters **AND** together: `eligible = base_catalog ∩ filter₁ ∩ filter₂ ∩ …` |
| **Separate URL params** | One param per filter axis on the **tournament-step family** — same pattern as WP4. **Do not** fold into `id_with`. Prefer **`id_*` prefix** (e.g. `id_wc`, `id_country`) over reusing hub index param names (`wc=`, `country=`) on entity pages — avoids conflating “index table filter” with “chevron catalog filter” in propagation helpers. Final names TBD at implementation. |
| **Propagation** | Tournament folder URLs only — alongside `id_with`, `as=`, and resolved scope when applicable. Never on TT ribbon, hub tabs, or unrelated Amiga links (WP5). |
| **Controls** | Same listbox grammar as with-player (cancel row, link-star when active, no search) — one control owns one param. |
| **Matching logic** | Reuse or wrap tournament index matchers (`amiga_tournament_index_matches_wc_filter()`, `amiga_tournament_index_matches_country_filter()`, …) — **do not** duplicate filter rules in the step nav include. |
| **Step helper** | `k2_participation_step_keys()` treats the post-filter key set as `eligible_key_set` — player participation is just one filter. |
| **Empty catalog** | Both chevrons **disabled**; page still renders for requested `id=` — no 404. |
| **Current `id=` ∉ filtered set** | Page **still loads**. Chevrons step to the **nearest eligible tournament** before/after the current event in chrono order (same rule as TT `as_with=` / `k2_participation_step_keys`). Current tournament need not match the filter. |
| **Unknown / non-catalog `id=`** | Both chevrons **disabled** (invalid or non-public tournament). |

**Plausible future axes (non-exhaustive):**

| Axis | Example param | Notes |
|------|---------------|-------|
| With player | `id_with` | **Slice 2** |
| WC vs all | `id_wc=world-cup` / `not-world-cup` | Mirror index semantics |
| Host country | `id_country={name}` | **Shipped** — mirror index host-country filter (country name string, not ISO code) |

Adding a filter later = new param + parse/append helper + catalog bag field + one listbox — **not** a change to TT ribbon or href fallback rules.

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
| Online league chevrons | `start_with` | League period (`start=`) | Rated game in period |

**Same UX and listbox behaviour; different URL params and propagation.** Shared participation lookup across Amiga surfaces; online uses `ratedresults` / league membership reads.

---

## 8. Implementation slices (summary)

Full checklist: [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md).

| Slice | Deliverable |
|-------|-------------|
| **0** | Retire T18 — no new UI |
| **1** | `as_with=` + listbox on TT Event ribbon + participation lookup helper |
| **2** | Tournament chevrons + `id_with=` (reuses lookup helper only) |
| **3** | League period row + `start_with=` |

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
| **Tournament step catalog filters** (WC, host country, …) | **Deferred post–slice 2** — architecture in §5.7; slice 2 builds filter-bag catalog only |

---

## 10. Changelog

| Date | Change |
|------|--------|
| 2026-06-30 | Track **complete** — slice 3 `start_with=` on league period chevrons (`k2_league_period_with_player.php`, probe green). |
| 2026-06-30 | §5.7 — current `id` off filter steps to **nearest eligible** neighbor (not disabled chevrons); base catalog never pre-filtered. |
| 2026-06-30 | **WP15–WP17 + §5.5–§5.7** — tournament step architecture (three layers, wing fallback, future catalog filters with AND composition); slice 2 extensibility without TT reuse. |
| 2026-06-30 | Slice 1 shipped — explicit `as_with=` on Event ribbon replaces T18. |
| 2026-06-30 | **WP14** — retire tournament `id` follows TT `as=` (slice 0); tournament chevrons own `id=` navigation. |
| 2026-06-30 | Planning revision — per-surface params (`as_with`, `id_with`, `start_with`); shared participation lookup only; T18 slice 0 first; independent filters on tournament+TT (WP13). |
| 2026-06-30 | Initial policy — creative session alignment. |