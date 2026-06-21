# Amiga time travel — implementation plan (phase 1)

**Status:** **Phase 1 complete** (Jun 2026) — slices 0–6 done; CLI smoke [`scripts/oneoff/amiga_time_travel_smoke.php`](../scripts/oneoff/amiga_time_travel_smoke.php).  
**Policy:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md)

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-rating-history-implementation-plan.md`](amiga-rating-history-implementation-plan.md) (pilot superseded by shared context)

**In scope (phase 1):** Shared snapshot context + time travel chrome + link propagation · **Leaderboards** (all eight wings) · **Hall of Fame** · **`history.php`** aligned to shared `as` param.

**Out of scope (phase 1):**

- **Player profile** — entire page treated as unshipped for time travel; present-only even when `as=` is active (policy T12). Wire in phase 2 when profile work lands.
- New pages (dedicated opponents hub, expanded H2H poster, activity charts)
- `/amiga/h2h.php` snapshot reads (page exists — phase 2+)
- Games / tournaments catalog filtering
- News / bar-chart race
- DDL / finalize writer changes
- Staging export unless Dagh syncs for browser proof
- Git commit unless Dagh asks

**Migration:** **L0** — read-path + PHP includes only; **no Part B**.

---

## Phase 1 surface checklist

| Item | Phase 1? | Notes |
|------|----------|-------|
| Leaderboards (8 wings) | **Yes** | rating, goals, DD/CS, victims, peak, perf rating, tournament honours, calendar-geo |
| Hall of Fame | **Yes** | `amiga_realm_snapshots` |
| Infrastructure + chrome | **Yes** | Context, ribbon, link propagation |
| `history.php` | **Align** | Reuse chrome + `as=`; avoid duplicate cutoff logic |
| Player profile | **No** | Present-only; optional unwired note when `as` active — phase 2+ |
| `h2h.php` | **No** | Phase 2+ |
| News, Activity, Games, Tournaments | **No** | Present-only / deferred |

---

## How to use this plan

1. Execute slices **in order** unless Dagh splits.
2. Run each slice **Verification** before moving on.
3. **Do not git commit** unless Dagh asks.
4. After slice 6: **UPDATE_DOCS** Part A — MEMORY, policy status, `amiga-data-contract.md` authority map, `hub-ia-agreement.md` time-travel note, optional `feature-log.md` row.

---

## Locked decisions (do not re-open without user)

See policy **T1–T12**. Summary: global `as=` lens; URL authority; incremental surfaces; Year · Month · Event chrome; present default; link propagation ships in phase 1; **profile excluded from phase 1**.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | User OK — **done** |
| **1** | `amiga_snapshot_context.php` + URL helpers + catalog generalization | Unit/probe: resolve `as=` keys — **done** |
| **2** | Time travel ribbon + hub entry + link propagation (hub, LB nav) | Browser: param survives hub clicks — **done** |
| **3** | Leaderboards — snapshot read path all wings | Spot-check vs present + vs history.php — **done** |
| **4** | Hall of Fame — realm snapshot at cutoff | HoF holders match DB at cutoff — **done** |
| **5** | `history.php` — shared context (`as=`) + shared chrome; legacy `wing`/`at` alias | history URL ↔ LB rating parity — **done** |
| **6** | Doc closure, regression smoke, MEMORY | CLI smoke green; Dagh browser OK on staging — **done** |

---

## Slice 1 — Snapshot context foundation

### Goal

One PHP module resolves `as=` to cutoff struct; present when absent/invalid.

### Tasks

- [x] Create `site/public_html/includes/amiga_snapshot_context.php`
  - `amiga_snapshot_context_from_request(): AmigaSnapshotContext`
  - `is_active(): bool`
  - `cutoff(): ?array{tournament_id, event_date, chrono, label, wing, key}`
  - `prev_key(): ?string` and `prev_cutoff(): ?array` — from wing catalog (for chevrons + future LB deltas)
  - `as_query_string(): string` — `as=year:2003` for appending
- [x] Create `site/public_html/includes/amiga_snapshot_url.php` (or methods on context)
  - `amiga_url_with_context(string $path, array $extra = []): string`
  - `amiga_url_present()`, `amiga_url_with_as()`
- [x] Generalize catalog builders in `amiga_rating_history_lib.php`:
  - `amiga_snapshot_parse_as_param`, `amiga_snapshot_format_as_param`
  - `amiga_snapshot_resolve_catalog_view`, `amiga_snapshot_resolve_as`, `amiga_snapshot_cutoff_from_catalog_entry`
  - Reuse existing event/month/year catalogs and chrono rules (policy T7)
- [x] Probe: `scripts/oneoff/amiga_snapshot_context_probe.php`

### Verification

```powershell
# Resolve year 2003 → tournament_id matches last finalize in 2003
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts/oneoff/amiga_snapshot_context_probe.php
```

- `as=` absent → `is_active() === false`
- `as=event:{last_id}` → cutoff matches `amiga_rating_history_resolve_view` for event wing

---

## Slice 2 — Time travel chrome + link propagation

### Goal

Users can turn time travel on/off; navigation preserves `as` on wired pages.

### Tasks

- [x] Create `site/public_html/includes/amiga_snapshot_chrome.php`
  - Ribbon when active (policy §5.1)
  - Wing tabs Year · Month · Event; chevrons; picker (extract from `history.php`)
  - Exit to present link
- [x] Include chrome from a single Amiga layout hook after `amiga_hub_nav.php` on hub pages
- [x] Include chrome after `amiga_player_nav.php` on player wings
- [x] Hub entry when inactive: **Time travel** link → first year `as=year:{yyyy}`
- [x] Update `amiga_hub_nav.php` — `href`s via `amiga_url_with_context()`
- [x] Update `amiga_lb_nav.php` — wing tab hrefs
- [x] Update `k2_amiga_route()` / `k2_amiga_player_link()` — append context when active
- [x] Profile unwired note when `as` active on player pages
- [x] CSS ribbon styles in `theme.css` (reuse `k2-amiga-history__*` controls)
- [x] Skip duplicate chrome on `history.php` until slice 5

### Verification

- Enable `?as=year:2010` on `/amiga/leaderboards/rating.php`
- Click HoF hub tab → URL still has same `as`
- Click player name → profile URL carries `as` but profile shows present data
- Exit to present → param gone

---

## Slice 3 — Leaderboards (all wings)

### Goal

Every LB wing sorts from snapshot rows at cutoff when time travel active.

**Future-proofing (wing-step deltas):** return structured rows (`player_id` + raw metric values), not presentation-only HTML. Snapshot context must expose **prev cutoff** / `prev_key` from the same wing catalog (for a later optional Δ toggle — same pattern as `amiga_rating_history_ladder_with_deltas()`). Phase 1 does not ship Δ columns on LB wings; do not block them in the read layer.

### Tasks

- [x] Create `site/public_html/includes/amiga_lb_snapshot_lib.php` (or extend existing LB loaders)
  - `amiga_lb_query_career()` — last snapshot per player ≤ cutoff; wing-specific helpers for honours / geo / perf
  - `$ctx` exposes `prev_key` / `prevCutoff()` from wing catalog (slice 1) — reused by ribbon chevrons and future delta enricher
  - Rank assignment same rules as present LB (unique rank, tie-break `player_id ASC`)
- [x] Wire each wing PHP file to branch on `$ctx->isActive()`:

| Wing file | Sort / source column (snapshot row) |
|-----------|-------------------------------------|
| `rating.php` | `Rating` |
| `goals.php` | `GoalsFor` |
| `double-digits.php` | DD/CS columns |
| `victims.php` | network victim/culprit columns |
| `peak-rating.php` | `PeakRating` |
| `performance-rating.php` | best event `performance_rating` ≤ cutoff |
| `tournament-honours.php` | honours columns |
| `calendar-geo.php` | geo/year scalar columns |

- [x] Player links in tables use `amiga_url_with_context()` (via `k2_amiga_player_link` / `k2_amiga_route` from slice 2)

### Verification

- [x] Present mode: tables unchanged vs pre-slice (probe `lb_present_rows`)
- [x] `as=event:{id}` rating wing top-10 player order matches `history.php` ladder at same cutoff
- [x] Spot-check goals + calendar-geo at `as=year:2008` (probe `lb_year2008_goals`)

---

## Slice 4 — Hall of Fame

### Goal

HoF panel reads realm snapshot at cutoff.

### Tasks

- [x] Add `site/public_html/includes/amiga_realm_snapshot_read_lib.php`
  - `amiga_realm_generalstats_at_cutoff($con, $ctx)` → row shaped like `amiga_generalstats`
  - `amiga_hof_records_load($con, $ctx)` — present vs snapshot branch
- [x] Update `site/public_html/amiga/hall-of-fame.php`:
  - `$ctx->isActive()` → load from `amiga_realm_snapshots`
  - Else → existing `amiga_generalstats` read
- [x] WC medals panel: **present-only in phase 1** — unwired note when time travel active
- [x] HoF holder links to LB wings carry `as` (`amiga_records_hof_lb_href` → `amiga_url_with_context`)

### Verification

- [x] SQL oracle: `MostGamesPlayed` at year:2003 cutoff matches `amiga_realm_snapshots` row (probe)
- [x] year:2003 holders differ from present when records changed since

---

## Slice 5 — History page alignment

### Goal

No duplicate cutoff systems; history pilot uses shared context.

### Tasks

- [x] `history.php` reads `$ctx = amiga_snapshot_context_from_request()`
  - Prefer `as=` param; legacy `wing` + `at` → 302 to canonical `as=` (internal map before redirect)
- [x] Replace page-local picker/stepper with shared time-travel chrome (`$k2AmigaSnapshotChromePath`)
- [x] Rating ladder + Δ via `amiga_rating_history_resolve_from_context()` (existing snapshot ladder)
- [x] **Option A:** History tab stays rating-ladder + Δ specialty; other LBs use `/amiga/leaderboards/*?as=`
- [x] Policy T9 unchanged — History remains dedicated tab; shared `as=` + chrome

### Verification

- [x] `as=event:{id}` history top-10 player order matches rating LB at same cutoff (probe)
- [x] Legacy `?wing=month&at=2003-11` resolves via shared context (probe)
- [x] Bare `/history.php` → 302 to latest event `?as=event:{id}`

---

## Slice 6 — Doc closure + smoke

### Tasks

- [x] Policy status → **Phase 1 implemented**
- [x] UPDATE_DOCS Part A: MEMORY, `amiga-data-contract.md`, `hub-ia-agreement.md`, `feature-log.md`
- [x] CLI smoke: [`scripts/oneoff/amiga_time_travel_smoke.php`](../scripts/oneoff/amiga_time_travel_smoke.php) (six steps below)
- [ ] Browser smoke on `ratingskickoff.test` (Dagh — optional staging sync)

| Step | Action | CLI |
|------|--------|-----|
| 1 | Open LB rating present — baseline | smoke step 1 |
| 2 | Enter time travel `as=year:2003` — ribbon visible | smoke step 2 |
| 3 | Navigate HoF — holders change / param kept | smoke step 3 |
| 4 | Switch LB wings — tables reflect cutoff / param kept | smoke step 4 |
| 5 | Click player from LB — profile present-only note; URL keeps `as` | smoke step 5 (URL); browser for note |
| 6 | Exit to present — ribbon gone, tables match step 1 | smoke step 6; browser for ribbon |

**Also run:** `php scripts/oneoff/amiga_snapshot_context_probe.php` (context + LB + HoF + History oracles).

### Verification

- [x] CLI smoke six steps green (local `ko2amiga_db`)
- [ ] Dagh browser sign-off on local / staging when convenient

---

## File touch list (expected)

| File | Slice |
|------|-------|
| `includes/amiga_snapshot_context.php` | 1 |
| `includes/amiga_snapshot_url.php` | 1 |
| `includes/amiga_snapshot_chrome.php` | 2 |
| `includes/amiga_rating_history_lib.php` | 1, 5 |
| `includes/amiga_hub_nav.php` | 2 |
| `includes/amiga_lb_nav.php` | 2, 3 |
| `includes/amiga_lb_snapshot_lib.php` | 3 |
| `includes/amiga_lb_*.php` or per-wing PHP | 3 |
| `includes/amiga_realm_snapshot_lib.php` | 4 |
| `amiga/hall-of-fame.php` | 4 |
| `amiga/history.php` | 5 |
| `amiga/player/profile.php` | 2 optional (unwired note only) |
| `stylesheets/…` (ribbon) | 2 |

---

## Phase 2 preview (not this plan)

- **Player profile** — hero, career, honours, top opponents at cutoff (when profile slice ships)
- `/amiga/h2h.php` directed pair at cutoff
- Profile moments / recent tournaments / chart marker
- Games & tournaments catalog filter
- Activity realm aggregates from realm snapshots
- Fold History tab into Leaderboards IA if desired
