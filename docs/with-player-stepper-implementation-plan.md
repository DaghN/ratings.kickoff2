# With player stepper — implementation plan

**Status:** **Complete (Jun 2026)** — slices **0 → 3** shipped.  
**Policy:** [`with-player-stepper-policy.md`](with-player-stepper-policy.md)

**Migration:** **L0** — read-time participation lookups only; **no Part B**.

---

## How to use this plan

1. Execute slices **in order** (user may say “slice N” to start there only if prior slices are done).
2. Run each slice **Verification** before the next slice.
3. **Do not** git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>" unless user asks.
4. After **slice 3** (or agreed stop point): **UPDATE_DOCS** Part A — MEMORY, policy/plan status, `feature-log.md`, `amiga-time-travel-policy.md` cleanup.

---

## Architecture (locked)

| Layer | Share? | Notes |
|-------|--------|-------|
| Participation key-set query | **Yes** | Amiga: `amiga_player_event_snapshots` (`NumberGames > 0`). Online slice 3: rated games in period. |
| Pure step-key resolver | **Yes** | `{ catalog, current_key, eligible_key_set } → { prev_key, next_key }` — no URL, no realm fallback when filter on. Player participation is one way to build the eligible set; tournament catalog filters are others (§5.7). |
| Eligible player list (A–Z, ≥1 game) | **Yes** per realm | Separate SQL; same listbox render |
| URL param + propagation | **No** | `as_with` · `id_with` · `start_with` (+ future `id_*` catalog filters) — small helper families per surface |
| Chevron href builders | **No** | Each surface owns the query key it moves. Tournament layer adds **wing-preserving href resolver** (§5.6) — separate from catalog stepping. |
| Listbox form | **No** | Same widget; different field name and `action` path |
| Tournament catalog builder | **No** (tournament-local) | Filter bag → chrono keys; slice 2 ships `id_with` only — see slice 2 § Architecture |
| TT ribbon stepper | **No reuse for tournament** | WP15 — do not extend snapshot chrome/context for `id=` steps |

**Explicit filter stepping (simpler than T18):** when a with-player param is set, prev/next only land on participated keys; **no** “realm back one event before debut” fallback.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Retire T18 + TT tournament **`id` follows `as=`** sync | **Shipped Jun 2026** |
| **1** | `as_with=` + TT Event ribbon listbox | **Shipped Jun 2026** |
| **2** | `id_with=` + tournament nav chevrons | **Shipped Jun 2026** |
| **3** | `start_with=` + league period row | `league.php` period steps skip inactive months |

---

## Slice 0 — Retire implicit TT smart behaviors

### Goal

Remove **two** implicit time-travel behaviors before shipping explicit controls:

1. **T18** — player-page Event chevrons step only that player's tournaments; picker link-star accents.
2. **Tournament `id` follows `as=`** — on tournament entity pages, TT Event chevrons/picker/redirect no longer change `id=` to match `as=event:{id}`. The tournament you're viewing stays fixed; the **global lens** (`as=`) may change independently. **Navigate to another tournament** = dedicated tournament chevrons (slice 2), not the TT ribbon.

After slice 0: Event wing prev/next is realm-global everywhere; tournament pages do not 302 when `id` ≠ `as=event:{id}`; tournament links preserve active `as=` without rewriting to the destination tournament id.

### T18 — files to change

| File | Change |
|------|--------|
| [`amiga_snapshot_context.php`](../site/public_html/includes/amiga_snapshot_context.php) | Delete block calling `amiga_player_event_stepper_applies` / `amiga_player_event_wing_step_keys` (≈ L201–216) |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Remove `$pickerAccentKeys` block tied to player path (≈ L239–254) |
| [`amiga_player_event_stepper_lib.php`](../site/public_html/includes/amiga_player_event_stepper_lib.php) | **Delete file** |
| [`amiga_snapshot_context_probe.php`](../scripts/oneoff/amiga_snapshot_context_probe.php) | Remove T18 block (≈ L313–353); add player-path Event next = hub-path test |

### Tournament id-follows-as — files to change

| File | Change |
|------|--------|
| [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php) | Remove `amiga_tournament_apply_time_travel_event_id_redirect($_GET)` call (≈ L9) |
| [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) | Remove or no-op `amiga_tournament_apply_time_travel_event_id_redirect()`; trim comment on `amiga_tournament_href()` that references redirect avoidance |
| [`amiga_snapshot_url.php`](../site/public_html/includes/amiga_snapshot_url.php) | **`amiga_snapshot_chrome_nav_href()`** — remove tournament-page branch that rewrites href to `amiga_tournament_url($eventId)` (≈ L214–224); chevrons/picker on tournament page keep current `id=` |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | **`amiga_snapshot_chrome_carry_query_params()`** — remove block that sets `carry['id']` from parsed `as=event:` on tournament paths (≈ L112–118) |
| [`amiga_tournament_tt_link_probe.php`](../scripts/oneoff/amiga_tournament_tt_link_probe.php) | Rewrite expectations: mismatched `id` + `as=event:` is allowed; chrome nav href must **not** force `id` to event key |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | §5.1.1 — mark id-tracks-cutoff **retired**; point to tournament stepper policy |

### Tasks

- [x] Remove T18 branching from context + chrome; delete stepper lib
- [x] Remove tournament id-sync redirect + chrome nav href rewrite + picker hidden-id override
- [x] Update both probes; grep for `apply_time_travel_event_id_redirect` / id-tracks-cutoff docs
- [x] Run probe CLIs (`amiga_tournament_tt_link_probe.php` green; context probe hub-tab assert fixed)

### Verification

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\amiga_snapshot_context_probe.php
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\amiga_tournament_tt_link_probe.php
```

**Browser:**

- `/amiga/tournament/event-stats.php?id=94&as=event:100` — page loads **tournament 94** (no 302 to id=100)
- TT Event picker/chevron changes `as=` but **URL `id=` stays 94**
- Player path vs rating LB: same Event chevron targets (T18)

### Acceptance

- [x] No `amiga_player_event_stepper_applies` references
- [x] No 302 when `id` ≠ `as=event:{id}` on tournament pages
- [x] TT ribbon on tournament page steps `as=` only; `id=` unchanged until user uses tournament nav or explicit tournament link
- [x] Probes exit 0 (tournament TT link probe; context probe hub IA assert updated for T13b)

**No new UI in slice 0.**

---

## Slice 1 — TT Event ribbon (`as_with=`)

### Goal

Opt-in filter on time-travel **Event** wing. Replaces removed T18 behaviour with explicit URL state.

### New / refactored modules

| File | Role |
|------|------|
| **`includes/amiga_participation_step_lib.php`** (new) | `amiga_player_participated_event_keys($con, $playerId): list<string>` · `amiga_player_participated_event_key_set()` · `k2_participation_step_keys(array $catalog, string $currentKey, array $participatedSet): array{prev_key, next_key}` — extract pure stepping from deleted T18 lib (**drop realm fallback** on back) |
| **`includes/amiga_as_with_url.php`** (new, or section in `amiga_snapshot_url.php`) | `amiga_as_with_from_request(): ?int` · `amiga_url_with_as_with(string $url, ?int $playerId): string` · append on `amiga_url_with_context()` / `amiga_url_with_as_param()` when request carries valid `as_with` |

### Files to change

| File | Change |
|------|--------|
| [`amiga_snapshot_context.php`](../site/public_html/includes/amiga_snapshot_context.php) | When `wing === 'event'` **and** valid `as_with` → override `prev_key` / `next_key` via `k2_participation_step_keys` + participation set |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Event wing: render with-player listbox (`as_with` field); `$pickerAccentKeys` when `as_with` set; carry `as_with` in picker form hidden fields + `amiga_snapshot_chrome_carry_query_params` |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Chevron hrefs: preserve `as_with` via URL helpers |
| [`amiga_snapshot_url.php`](../site/public_html/includes/amiga_snapshot_url.php) | Propagate `as_with` on TT-aware links (mirror `as=` habit) |
| [`k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php) | If central Amiga route wrapper — ensure `as_with` passes through `k2_amiga_route()` |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | Ribbon row layout if listbox needs width vars (follow Event picker spacing) |

### Player listbox (TT)

- Field name: **`as_with`**
- First row: off/cancel (omit param on submit)
- Choices: Amiga players with ≥1 rated game, `ORDER BY name ASC`
- Closed trigger: player name + `k2-link-star` when selected
- Idle label suggestion: **“With player”** (closed) / **“All players”** (cancel row)

### Tasks

- [x] Add `amiga_participation_step_lib.php`
- [x] Add `as_with` parse + propagation helpers
- [x] Wire context stepping when `as_with` + Event wing
- [x] Render listbox on Event ribbon; carry on picker + chevrons
- [x] Optional: event picker link-star accents when `as_with` matches row
- [x] Extend probe: `as_with=73` → next skips non-played events; forward clamp at TT cutoff

### Verification

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\amiga_snapshot_context_probe.php
```

**Browser:**

- `/amiga/leaderboards/rating.php?as=event:{id}&as_with=73` — forward chevron skips gaps
- Hub tab click preserves `as_with`
- Forward disabled at snapshot cutoff when later events exist in present day

### Acceptance

- [x] No `as_with` → post–slice-0 realm-global stepping
- [x] `as_with` off via cancel row drops param
- [x] Player pages do **not** auto-set `as_with`

---

## Slice 2 — Tournament chevrons (`id_with=`)

### Goal

League-style prev/next on tournament entity nav; **independent** of `as_with=`. Structure code for **future catalog filters** (WC, host country, …) without shipping them — policy §5.5–§5.7.

### Architecture (slice 2 — locked)

Three **orthogonal** layers — do not merge:

```text
filter bag → catalog builder → step keys → href resolver → chevron/listbox render
```

| Module | Role |
|--------|------|
| **`amiga_tournament_step_catalog.php`** (new, or section in step lib) | `amiga_tournament_step_filter_bag_from_request()` · `amiga_tournament_step_catalog($con, $filterBag, ?cutoff)` → chrono asc tournament id keys. **Slice 2 bag:** `{ player_id: ?int }` from `id_with` only. |
| **`amiga_tournament_step_href.php`** (new) | Capture **nav intent** from current request · `amiga_tournament_step_href($con, $targetId, $intent)` → wing-preserving URL (§5.6 fallback ladder). Uses existing `amiga_tournament_*_url()` helpers — not TT chrome. |
| **`amiga_tournament_step_nav.php`** (new) | Read bag + current `id=` · build catalog · `k2_participation_step_keys()` · render chevrons + with-player listbox · hrefs via step_href. |
| **`amiga_id_with_url.php`** (new) | `id_with` parse/append on tournament folder URLs only — **do not** merge with `as_with` or future `id_*` helpers |

**Filter bag (extensibility contract):**

```php
// Slice 2
['player_id' => ?int]   // from id_with; null/0 = no player filter

// Future (examples — names TBD in policy §5.7)
// + 'wc' => '' | 'world-cup' | 'not-world-cup'
// + 'country' => '' | host country code
```

Catalog builder: `base_keys ∩ player_participated (if set) ∩ wc (if set) ∩ …`

**Do not reuse:** `amiga_snapshot_context.php` stepping, `amiga_snapshot_chrome.php` stepper, `amiga_as_with_url.php` (WP15).

**Do reuse:** `k2_participation_step_keys()`, `amiga_player_participated_event_key_set()`, tournament index match helpers when adding WC/country filters later.

### Placement (locked)

In [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php): extend the primary tournament segment nav row (`k2-amiga-tournament-nav`, ≈ L370–497):

```text
[ Event stats | Stages | Games | … ]     [ ‹ ] [ › ] [ With player ▾ ]
```

- Right-aligned cluster beside `k2-player-nav__links` (flex row — mirror `k2-league-period__standings-nav` pattern).
- Visible **present day and TT**.
- Reference markup: `k2_league_period_render_period_steps_html` + standings nav wrapper.

### New modules (summary)

See **Architecture** above. Optional merge: catalog + href into `amiga_tournament_step_nav.php` if files stay small — but **functions stay logically separate** even in one file.

### Files to change

| File | Change |
|------|--------|
| [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php) | Include step nav after segment pills (both WC and non-WC nav blocks) |
| [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) | Optional shared row metadata for catalog filters (WC flag, host country) if not already on index rows |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | `.k2-amiga-tournament-nav` flex: pills + step cluster (reuse league period step spacing tokens) |

### Stepping rules

- Param (v1): **`id_with`**
- Moves: **`id=`** via filtered catalog — href resolver picks wing path (§5.6)
- Reuse `amiga_participation_step_lib.php` for player key set + `k2_participation_step_keys()`
- When `as=event:` active: base catalog truncated to ≤ cutoff; filters apply within that set
- **`id_with` does not write `as_with`** and vice versa
- **Off-filter tournament (current `id` ∉ eligible set):** chevrons step to nearest eligible neighbor in chrono order (§5.7)
- **Unknown `id` (not in base catalog):** both chevrons disabled
- **Empty eligible set after filters:** both chevrons disabled

### Wing fallback (locked — §5.6)

1. Same view + scope/mode when target supports it  
2. Relax within view (videos games ↔ atmosphere)  
3. Missing wing → **event-stats**  
4. Do not carry games player filter or video deep links on step (v1)

### Future catalog filters (out of slice 2 — §5.7)

After slice 2: add `id_wc`, … — one param, one listbox, one bag field, reuse index matchers. **`id_country` shipped** (host country listbox on entity nav). No change to href fallback or TT ribbon.

### Tasks

- [x] Filter bag + catalog builder (v1: `player_id` only)
- [x] Nav intent capture + wing-preserving href resolver
- [x] `id_with` URL helpers + tournament folder propagation
- [x] Step nav render; wire into tournament page
- [x] Present + TT browser smoke on e.g. tournament id 94
- [x] Probe: `amiga_tournament_step_probe.php` — filtered step, deep-link disable, propagation, videos fallback
- [x] **`id_country`** — host country listbox + filter bag + propagation (§5.7 extension)

### Verification

**Browser:**

- `/amiga/tournament/event-stats.php?id=94` — chevrons change `id=` only
- Same URL + `as=event:94&as_with=354&id_with=354` — each listbox independent
- TT: cannot step forward past cutoff tournament

### Acceptance

- [x] Tournament chevrons work without `as=` (present day)
- [x] `id_with` propagates on tournament tab links, not on unrelated Amiga hub links
- [x] Independent of `as_with=` on same page
- [x] Off-filter tournament → nearest eligible neighbor stepping (§5.7)

---

## Slice 3 — League periods (`start_with=`)

### Goal

Online parity: filter league period chevrons on `league.php`.

### New / changed modules

| File | Role |
|------|------|
| **`includes/k2_league_period_with_player.php`** (new, or extend `k2_league_period_page.php`) | Online players ≥1 rated game; participation keys per `(cup, period, start)`; filtered `period_prev_start` / `period_next_start` |
| **`k2_league_period_page.php`** | `k2_league_period_peer_href()` carries `start_with`; standings nav renders listbox beside period steps |

### Participation oracle (online)

For player P and league period key `start`:

- ≥1 rated row in `ratedresults` within period UTC bounds (reuse league period bounds helper `k2_league_period_games_bounds` family).

### Player listbox (league)

- Field name: **`start_with`**
- Same UX as Amiga listboxes (cancel row, link-star trigger, A–Z, no search)

### Files to change

| File | Change |
|------|--------|
| [`k2_league_period_page.php`](../site/public_html/includes/k2_league_period_page.php) | `k2_league_period_load()` or sibling: compute filtered prev/next when `start_with` set; render listbox in `k2_league_period_render_standings_header` |
| [`k2_league_period_page.php`](../site/public_html/includes/k2_league_period_page.php) | `k2_league_period_peer_href` + games pager hrefs preserve `start_with` |
| [`player-milestones.css`](../site/public_html/stylesheets/player-milestones.css) | `.k2-league-period__standings-nav` — room for listbox (already has chevrons + sibling link) |

### Tasks

- [x] Online eligible player query
- [x] Period participation index (cache per request for player)
- [x] Filtered step keys in period load
- [x] Listbox + propagation on `league.php` peer links
- [x] Optional: one-off probe script for period stepping

### Verification

**Browser:** [`league.php` example](https://ratings.kickoff2.com/league.php?cup=points&period=month&start=2026-02#k2-league-period)

- `start_with={id}` → month chevrons skip months with zero games for that player
- Cancel row clears filter
- Sort params (`k2_sort_*`) preserved on step

### Acceptance

- [x] Independent param — no interaction with Amiga `as_with` / `id_with`
- [x] Same listbox UX as Amiga surfaces
- [x] Off-filter period → nearest eligible neighbor stepping (same as §5.7 / `k2_participation_step_keys`)

---

## CSS / layout notes

| Surface | Hook | Intent |
|---------|------|--------|
| TT ribbon | `.k2-amiga-time-travel__controls` | Add listbox after event picker; may need `--k2-amiga-tt-picker-width` regression pass |
| Tournament nav | `.k2-amiga-tournament-nav` | `display:flex; justify-content:space-between; align-items:center` — pills left, step cluster right |
| League standings | `.k2-league-period__standings-nav` | Listbox after period steps, before sibling month/year link |

Use existing chevron classes: `k2-player-games-day-steps`, `k2-player-games-day-step--prev/next`. Spacing: [`nav-spacing-policy.md`](nav-spacing-policy.md) — bottom-only gaps unchanged.

---

## Smoke URLs (local)

| Slice | URL |
|-------|-----|
| 0 | `/amiga/player/profile.php?id=73&as=event:100` vs `/amiga/leaderboards/rating.php?as=event:100` — same next chevron |
| 1 | `/amiga/leaderboards/rating.php?as=event:100&as_with=73` |
| 2 | `/amiga/tournament/event-stats.php?id=94` · `…&as=event:94&as_with=73&id_with=73` |
| 3 | `/league.php?cup=points&period=month&start=2026-02&start_with=1#k2-league-period` |

Replace ids with known local fixtures if import differs.

---

## Post-implementation docs

When track complete (or per-slice if user stops early):

- [x] `PROJECT_MEMORY.md` — Recent log per shipped slice
- [x] [`with-player-stepper-policy.md`](with-player-stepper-policy.md) — Status → implemented (or partial)
- [x] This plan — check off slices; Status line
- [ ] [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) — remove “retire on ship” notes; delete T18 module row
- [x] [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) — C13 → §5.3 shipped when all slices done
- [x] `docs/coordination/feature-log.md` — one row (L0)

---

## Out of scope (do not implement in this track)

- TT Year / Month `as_with`
- Auto-enable / suggest `as_with` from player pages
- Single global `with=` param
- Shared URL mega-helper across the three params
- Typeahead player search
- Sticky TT pin (C02) — separate track

---

## Changelog

| Date | Change |
|------|--------|
| 2026-06-30 | Slice 3 **shipped** — `start_with=` league period listbox + filtered stepping; `k2_league_period_with_player.php` + probe. |
| 2026-06-30 | Slice 2 **shipped** — tournament chevrons + `id_with=` + wing-preserving href resolver + filter bag. |
| 2026-06-30 | **Slice 2 architecture** — three-layer tournament step (catalog / step / href); filter bag for future WC/country filters; wing fallback §5.6; no TT reuse (WP15–WP17). |
| 2026-06-30 | Slice 1 **shipped** — `as_with=` TT Event ribbon listbox + filtered stepping. |
| 2026-06-30 | Slice 0 **shipped** — T18 + tournament id-follows-as retired. |
| 2026-06-30 | Slice 0 expanded — retire tournament `id` follows `as=` (WP14) alongside T18. |
| 2026-06-30 | Full plan — per-slice files, verification, layout; no handover prompt; implement in single chat track. |
| 2026-06-30 | Initial slice outline. |