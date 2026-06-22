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
| **Rollout** | **Incremental** — each surface opts in; **present-only hub tabs hidden** under time travel (T13) |

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
| **T14** | **Time-travel entry** | Header **Time travel** from a present-only hub page lands on **`/amiga/leaderboards/rating.php`** with active `as=` (default first calendar year when none set). From wired hub/LB pages, entry keeps the current path + `as=`. |
| **T14b** | **Player-wing entry** | Header **Time travel** from a **player wing** (`/amiga/player/…`) with no active `as=` lands on the **same player path** with `as=` = that player’s **first rated event snapshot** (`event:ID`). Realm picker stays the **full catalog** (earlier years still selectable). |
| **T14c** | **Tournament-page entry** | Header **Time travel** from **`/amiga/tournament.php?id={N}`** (present, no `as=`) lands on the **same tournament path** with `as=event:N` when tournament `N` is in the realm event catalog; else falls back to T14 (first calendar year). **Live tournament** pages remain present-only (T13). |
| **T15** | **Uniform lens** | **Hub (time travel):** only snapshot-worthy tabs (T13b); all derived stats at cutoff. **Player wings:** hero, games, tournaments, opponents, profile — everything at cutoff when wired; preserve `as=` on links. No silent present-day numbers on wired surfaces. |
| **T13b** | **Snapshot-only time-travel hub** | When `as=` is active, hub bar = **Leaderboards · Activity · Hall of Fame** only. **Tournaments** hub tab and future **Games** hub tab are **present-only** (hidden from bar). Historical tournament browse: player tournament list, `tournament.php` detail, deep links — not hub catalog index. |
| **T16** | **No silent exit** | Links from active time travel must **preserve `as=`** on a cutoff-aware page, or **explicitly** open present-only content (T13 editorial redirect, labelled present-only link). Never drop `as=` without user intent. |
| **T17** | **Pre-debut at cutoff** | Valid `amiga_players` row with **no snapshot ≤ cutoff** (or zero games at cutoff): page **loads** (no 404). Hero rank · rating · games show **—** + muted note *Not on the ladder at this cutoff.* Wired tables may be empty; unwired blocks may still show present data with transitional note (T5). |
| **T18** | **Player event stepper** | On `/amiga/player/…` with **Event** wing: chevrons step **this player’s participated tournaments** (`NumberGames > 0`). **Forward** from pre-debut → first played event. **Back** at first played event → one **realm** tournament at a time. Picker stays **full realm catalog**; played events get **linkstar accent** (name + date in open panel and closed trigger when selected). Hub / LB keep realm-global stepping. Year / Month unchanged. |

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

### 4.2 Present-only hub (editorial + catalog collections)

| Surface | Rule |
|---------|------|
| **News** | Present realm **landing** — invitations, reports, interviews, editorial (T13) |
| **Live tournaments** (+ live tournament detail) | Contemporary sign-ups / in-progress — last hub tab in present order |
| **Tournaments** (hub index) | Present hub tab; **hidden** under time travel (T13b). Filtered index/detail reachable via deep links + `as=` when wired |
| **Games** (future hub) | Present hub tab when shipped; **hidden** under time travel (T13b). Realm highlights live in Activity / records first |
| **Misc** (future) | Editorial oddments — present-only when shipped |

Direct `?as=` on **editorial** paths (News, live tournaments) → redirect strips time travel.

### 4.3 Player wings (time-travel lens — wire at cutoff)

Player pills stay visible under time travel. Target **T15** + **T16**:

| Surface | Rule |
|---------|--------|
| **Hero** | Rank · rating · games at cutoff from snapshot + `amiga_player_elo_rank_at_event`; **—** + note when pre-debut (T17) | **Shipped** — `amiga_player_snapshot_lib.php` · `amiga_elo_rank_lib.php` |
| **Hero → games** | `amiga/player/games.php` — hero-viewport game table; filters narrow the set; list ≤ cutoff; **date column = event day only** (`M j Y`) | **Shipped** Jun 2026 |
| **Tournaments** (player) | Participation list ≤ cutoff | **Shipped** Jun 2026 |
| **`tournament.php` detail** | Ribbon + `as=` on entry redirects and in-page nav (**shipped** Jun 2026); standings/stats body still present-day until wired (§4.4) |
| **Opponents** tables | Shipped — `amiga_matchup_snapshot_lib.php` |
| **Profile** blocks | Career / honours at cutoff — phase 2+ |

### 4.4 Transitional defer (visible but not yet at cutoff)

| Surface | Reason |
|---------|--------|
| **Player profile** (blocks) | Hero/career snapshot reads — hero **shipped**; career blocks phase 2+ |
| **Activity** (hub) | Charts not at cutoff yet |
| **Opponents H2H** | Poster/picker/charts not shipped |
| Hub **tournaments.php** with `?as=` | May show present until catalog filter ships; tab hidden regardless (T13b) |

### 4.5 Later phases (registry)

| Surface | Time travel source |
|---------|-------------------|
| **Player profile** — hero / career / honours | Player snapshot at cutoff |
| **Opponents H2H** (poster · moments · charts) | Pair games ≤ cutoff + stored pair row |
| Profile — moments, rating chart, perf highlight | Mixed; wire per slice |
| Activity / server aggregates | `amiga_community_stats` (+ snapshots at cutoff) |
| Hub tournaments index (optional) | Filter catalog ≤ cutoff — tab may stay hidden (T13b) |
| Rating chart overlay | Optional vertical marker at cutoff |

---

## 5. Chrome and IA

### 5.1 Time travel chrome

**Header (Amiga only):** segment beside realm switcher — **Present day | Time travel**. Present strips `as=` on the current path (carrying stable query params — `id`, table sort). **Time travel** sets default `as=` (first calendar year on hub/LB) or keeps active `as=`; from present-only hub pages targets **rating LB** (T14). From **player wings**, default `as=` = player’s first rated event (T14b). From **`tournament.php`** with catalog `id`, default `as=event:id` (T14c). **Wordmark** and **Amiga 500** realm pill use **News** in present mode; in time travel they return to **rating LB** with the active `as=` (realm home without exiting the lens).

**Hub bar (when `as=` active):** **Leaderboards · Activity · Hall of Fame** only (T13b). Present-day order: **News · Leaderboards · Tournaments · Activity · Hall of Fame · Live tournaments** (last). News, Live tournaments, Tournaments, and future Games hub tab are **hidden** under time travel.

**Ribbon (when `as=` active):** compact bar **directly below** the site header (wordmark + Present day | Time travel), at the top of `k2-page-nav` — **above** hub tabs, player hero, and player pills. One row (no wrap): **Year | Month | Event** wing tabs · chevrons + snapshot label · listbox picker. Year/Month wings: label only in stepper. **Event wing:** full layout contract in §5.1.1 (stepper link, picker widths, date formats, linkstar accents on player wings).

**Table sort carry:** same-path ribbon navigation preserves active `k2_sort` / `k2_dir` (PHP hrefs + picker; JS refreshes ribbon after column sort). Cross-page links (hub tabs, other wings) do not carry sort indices.

When inactive: header segment only; no ribbon below/above nav.

#### 5.1.1 Event ribbon layout (shipped Jun 2026)

Applies when Event granularity is active (`as=event:…`). Ribbon `<section>` gets `k2-amiga-time-travel--event-wing`; inline CSS vars size the stepper and picker from the realm event catalog.

| Zone | Content | Format / behaviour |
|------|---------|-------------------|
| **Stepper label** | Tournament name + event date | `M j, Y` (e.g. `Nov 14, 2006`); primary white link (`k2-amiga-history__label--link`), **not** linkstar |
| **Stepper link** | `/amiga/tournament.php?id={cutoff_tournament_id}#tournament` | Active `as=` via `amiga_url_with_as_param()` in `amiga_snapshot_chrome_render_stepper()`. User stays in time travel on the tournament page (T16) |
| **Picker closed** | Tournament name · date | Name left, date **right-aligned** in a fixed-width box; date `M Y`. Width = catalog longest name + fixed date column (not `name+date` char sum) |
| **Picker open** | Full realm catalog (newest first) | Panel width **matches** closed trigger; each row name left · date right. On **player wings** (T18), played events: linkstar on **name and date** |

**Width CSS vars** (computed in `amiga_snapshot_chrome_event_layout_style()`):

| Variable | Rule |
|----------|------|
| `--k2-amiga-tt-stepper-width` | Longest stepper label (`name · M j, Y`), capped ~28rem |
| `--k2-amiga-tt-picker-width` | `max(nameLen)×0.4rem` + ~4.75rem date slot + padding, cap ~19rem |

**Closed trigger layout:** CSS grid `minmax(0, 1fr) auto` with `0.4rem` gap — date sits at the right edge of the catalog-width box; short names leave space between text and date (intentional).

**`tournament.php` link carry:** `amiga_tournament_href()` wraps tournament URLs with `amiga_url_with_context()`. Used for WC entry **302 redirects**, section nav tabs, `amiga_tournament_link()`, and games-filter hidden `as` — so `as=` is not stripped on first paint or tab change.

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
| Tournaments | Yes | **Hidden** (T13b) — player / detail deep links when wired |
| Activity | Yes | Yes (T13b) |
| Hall of Fame | Yes | Yes (T13b) |
| Live tournaments | Yes (last) | **Hidden** (T13) |
| Games (future hub) | Yes (when shipped) | **Hidden** (T13b) |
| Misc (future) | Yes | **Hidden** (T13) |

Implementation: `includes/amiga_hub_nav_lib.php` · `amiga_snapshot_redirect_present_only_page()`.

---

## 6. PHP architecture

| Module | Role |
|--------|------|
| `includes/amiga_player_event_stepper_lib.php` | Player Event chevrons + picker accents (T18) |
| `includes/amiga_player_snapshot_lib.php` | Hero + `amiga_player_load()` at cutoff |
| `includes/amiga_elo_rank_lib.php` | Persisted `elo_rank` reads (present + time travel) |
| `includes/amiga_snapshot_context.php` | Parse `as`, resolve cutoff, `is_active()`, `cutoff()`, `label()`, `query_suffix()` |
| `includes/amiga_snapshot_url.php` (or helpers on context) | `amiga_url_with_context($path, $query)` |
| `includes/amiga_snapshot_chrome.php` | Ribbon HTML; Event wing layout vars; stepper tournament link |
| `includes/amiga_time_mode_nav.php` | Header Present day \| Time travel; entry defaults T14 / T14b / T14c |
| `includes/amiga_tournament_lib.php` | `amiga_tournament_href()`; `amiga_tournament_snapshot_as_param()` (T14c) |
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
| Link carry | Hub → LB → HoF preserves `as=`; present-only hub pages strip `as=` |
| Enter time travel from News | Lands on rating LB with `as=` (T14) |
| Enter time travel from player profile | Same path + `as=` = player first event (T14b) |
| Enter time travel from tournament.php | Same path + `as=event:{id}` when id in catalog (T14c) |
| Player before debut at cutoff | Hero — / — / — + note; no 404 (T17) |
| Player event chevrons | Step played tournaments; picker linkstar on played name + date (T18) |
| Event stepper → tournament | Link lands on `tournament.php` with same `as=`; WC redirects keep `as=` |
| Event picker layout | Catalog-fixed width; closed date right-aligned; open panel = trigger width |
| Hub nav under `as=` | Leaderboards · Activity · HoF only; no Tournaments tab (T13b) |
| Profile with `as` active | Present-day data until wired (T12); target T15 |
| Exit to present | Drops `as=`; returns to current tables; keeps `id` / sort params |
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

---

## 10. Agent policy

- **T13b–T18** hub IA + player pre-debut + player event stepper + **T14c tournament entry**: snapshot-only time-travel bar; player wings at cutoff; smart player TT entry; tournament page enters at that event; no silent `as=` exit.
- Do not wire games/tournament catalogs or profile until explicitly scheduled in §4.4.
- After phase 1 ship: UPDATE_DOCS Part A; feature-log row; cross-link from `amiga-data-contract.md` authority map.
