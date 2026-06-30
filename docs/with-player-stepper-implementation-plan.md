# With player stepper — implementation plan

**Status:** **Ready to implement (Jun 2026)** — execute slices **0 → 1 → 2 → 3** in this order in one track.  
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
| Pure step-key resolver | **Yes** | `{ catalog, current_key, participated_key_set } → { prev_key, next_key }` — no URL, no realm fallback when filter on |
| Eligible player list (A–Z, ≥1 game) | **Yes** per realm | Separate SQL; same listbox render |
| URL param + propagation | **No** | `as_with` · `id_with` · `start_with` — three small helper families |
| Chevron href builders | **No** | Each surface owns the query key it moves |
| Listbox form | **No** | Same widget; different field name and `action` path |

**Explicit filter stepping (simpler than T18):** when a with-player param is set, prev/next only land on participated keys; **no** “realm back one event before debut” fallback.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Retire T18 + TT tournament **`id` follows `as=`** sync | **Shipped Jun 2026** |
| **1** | `as_with=` + TT Event ribbon listbox | Probe + browser: filtered Event chevrons |
| **2** | `id_with=` + tournament nav chevrons | Tournament page: independent of `as_with` |
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

- [ ] Add `amiga_participation_step_lib.php`
- [ ] Add `as_with` parse + propagation helpers
- [ ] Wire context stepping when `as_with` + Event wing
- [ ] Render listbox on Event ribbon; carry on picker + chevrons
- [ ] Optional: event picker link-star accents when `as_with` matches row
- [ ] Extend probe: `as_with=73` → next skips non-played events; forward clamp at TT cutoff

### Verification

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\amiga_snapshot_context_probe.php
```

**Browser:**

- `/amiga/leaderboards/rating.php?as=event:{id}&as_with=73` — forward chevron skips gaps
- Hub tab click preserves `as_with`
- Forward disabled at snapshot cutoff when later events exist in present day

### Acceptance

- [ ] No `as_with` → post–slice-0 realm-global stepping
- [ ] `as_with` off via cancel row drops param
- [ ] Player pages do **not** auto-set `as_with`

---

## Slice 2 — Tournament chevrons (`id_with=`)

### Goal

League-style prev/next on tournament entity nav; **independent** of `as_with=`.

### Placement (locked)

In [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php): extend the primary tournament segment nav row (`k2-amiga-tournament-nav`, ≈ L370–497):

```text
[ Event stats | Stages | Games | … ]     [ ‹ ] [ › ] [ With player ▾ ]
```

- Right-aligned cluster beside `k2-player-nav__links` (flex row — mirror `k2-league-period__standings-nav` pattern).
- Visible **present day and TT**.
- Reference markup: `k2_league_period_render_period_steps_html` + standings nav wrapper.

### New modules

| File | Role |
|------|------|
| **`includes/amiga_tournament_step_nav.php`** (new) | Load tournament event catalog (present: full public; TT: ≤ cutoff). Step `id=` with optional `id_with`. Render chevrons + listbox. |
| **`includes/amiga_id_with_url.php`** (new) | `amiga_id_with_from_request()` · append on tournament folder URLs only — **do not** merge into `as_with` helpers |

### Files to change

| File | Change |
|------|--------|
| [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php) | Include step nav after segment pills (both WC and non-WC nav blocks) |
| [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) | Optional: `amiga_tournament_catalog_for_stepping($con, ?cutoff)` — chrono asc keys |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | `.k2-amiga-tournament-nav` flex: pills + step cluster (reuse league period step spacing tokens) |

### Stepping rules

- Param: **`id_with`**
- Moves: **`id=`** on `/amiga/tournament/…` (preserve `scope`, `scope_key`, view path)
- Reuse `amiga_participation_step_lib.php` for key resolution
- When `as=event:` active: catalog truncated to ≤ cutoff for tournament chevrons; **`id=` is independent of `as=`** (slice 0) — no redirect sync
- **`id_with` changes do not write `as_with`** and vice versa

### Tasks

- [ ] Tournament catalog + step key builder for `id=`
- [ ] `id_with` URL helpers + tournament href propagation
- [ ] Render chevron + listbox include; wire into tournament page
- [ ] Present + TT browser smoke on e.g. tournament id 94

### Verification

**Browser:**

- `/amiga/tournament/event-stats.php?id=94` — chevrons change `id=` only
- Same URL + `as=event:94&as_with=354&id_with=354` — each listbox independent
- TT: cannot step forward past cutoff tournament

### Acceptance

- [ ] Tournament chevrons work without `as=` (present day)
- [ ] `id_with` propagates on tournament tab links, not on unrelated Amiga hub links

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
| [`theme.css`](../site/public_html/stylesheets/theme.css) | `.k2-league-period__standings-nav` — room for listbox (already has chevrons + sibling link) |

### Tasks

- [ ] Online eligible player query
- [ ] Period participation index (cache per request for player)
- [ ] Filtered step keys in period load
- [ ] Listbox + propagation on `league.php` peer links
- [ ] Optional: one-off probe script for period stepping

### Verification

**Browser:** [`league.php` example](https://ratings.kickoff2.com/league.php?cup=points&period=month&start=2026-02#k2-league-period)

- `start_with={id}` → month chevrons skip months with zero games for that player
- Cancel row clears filter
- Sort params (`k2_sort_*`) preserved on step

### Acceptance

- [ ] Independent param — no interaction with Amiga `as_with` / `id_with`
- [ ] Same listbox UX as Amiga surfaces

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

- [ ] `PROJECT_MEMORY.md` — Recent log per shipped slice
- [ ] [`with-player-stepper-policy.md`](with-player-stepper-policy.md) — Status → implemented (or partial)
- [ ] This plan — check off slices; Status line
- [ ] [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) — remove “retire on ship” notes; delete T18 module row
- [ ] [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) — C13 → §5.3 shipped when all slices done
- [ ] `docs/coordination/feature-log.md` — one row (L0)

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
| 2026-06-30 | Slice 0 **shipped** — T18 + tournament id-follows-as retired. |
| 2026-06-30 | Slice 0 expanded — retire tournament `id` follows `as=` (WP14) alongside T18. |
| 2026-06-30 | Full plan — per-slice files, verification, layout; no handover prompt; implement in single chat track. |
| 2026-06-30 | Initial slice outline. |