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
| **Rollout** | **Incremental** — each surface opts in; unwired pages stay present until promoted |

There is **no** per-page “Current | Historical” split. One chrome control, one cutoff, navigation preserves context.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **T1** | **Single cutoff** | Year / month / event wings all normalize to one internal `{ tournament_id, event_date, chrono, label }` before any page logic |
| **T2** | **URL is authority** | Active time travel = `as` query param present and valid. Bookmarkable, shareable, back-button safe. No session-only primary state |
| **T3** | **Present by default** | Absent or invalid `as` → present reads; pages unchanged visually except optional “Enter time travel” entry |
| **T4** | **Link propagation** | When `as` is active, **all Amiga internal links** append the same param (hub tabs, LB wings, player pills, HoF links). Infrastructure ships with phase 1 |
| **T5** | **Incremental surfaces** | Pages without a snapshot read path **ignore** time travel and show present data. Optional ribbon note on unwired pages: “This section still shows present-day data” |
| **T6** | **No new routes fork** | Same PHP paths (`/amiga/leaderboards/rating.php`, `/amiga/player/profile.php`, …) — not a parallel `/amiga/history/…` tree |
| **T7** | **Commit boundary** | Cutoffs align with **tournament finalize** semantics (same as rating history V1). Month/year = last finalize on or before period end; empty periods repeat prior state |
| **T8** | **Wing tab order** | Chrome tabs: **Year · Month · Event** (coarse-to-fine browsing) |
| **T9** | **History tab removed** | **Jun 2026:** dedicated History hub tab and ladder page retired. Rating at cutoff lives on `/amiga/leaderboards/rating.php?as=`. Legacy `/amiga/history.php` 301 → rating LB (preserves `as=` / `wing`+`at`). |
| **T10** | **Excluded realms** | Online hub, Amiga ops, live tournaments, import tooling — **ignore** `as`; never enter time travel chrome |
| **T11** | **Streaks** | No time-travel surfaces for match streaks (Amiga product policy — non-authoritative columns) |
| **T12** | **Profile deferred** | Player profile is **not** in phase 1 — treat as **unshipped** for time travel until a dedicated profile + time-travel slice. Links may carry `as=`; page stays present-only |

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

---

## 4. Read-path register

### 4.1 Phase 1 (wired surfaces)

| Surface | Present source | Time travel source | Notes |
|---------|----------------|-------------------|--------|
| **Leaderboards** (all wings) | `amiga_player_current` | Last `amiga_player_event_snapshots` row per player ≤ cutoff | Sort wing metric on snapshot row |
| **Hall of Fame** | `amiga_generalstats` | `amiga_realm_snapshots` at cutoff | Full row incl. ratio leaders |

### 4.2 Phase 1 — present-only (explicit defer)

| Surface | Reason |
|---------|--------|
| **Player profile** (entire page) | Profile v0 treated as **unshipped** for time travel; substantial future work before snapshot reads — **phase 2+** |
| Games / game detail | Ground truth |
| Tournaments index / detail | Catalog; filter deferred |
| Activity | Not built |
| News / rating races | Animation specialty; phase 2+ |
| **`/amiga/h2h.php`** | Exists but minimal; phase 2+ |
| Live tournaments | N/A |

### 4.3 Later phases (registry)

| Surface | Time travel source |
|---------|-------------------|
| **Player profile** — hero / career / honours | Player snapshot at cutoff |
| **Player profile** — top opponents | `amiga_player_matchup_at_event` at cutoff |
| Profile — moments, recent tournaments, rating chart, perf highlight | Mixed; wire when profile slice defines behaviour |
| H2H pair page | `amiga_player_matchup_at_event` directed pair |
| Opponents tables (if split from profile) | Matchup at-event for player |
| Activity / server aggregates | `amiga_realm_snapshots` aggregate columns |
| Tournaments / games lists | Filter ground truth ≤ cutoff |
| Rating chart overlay | Optional vertical marker at cutoff |

---

## 5. Chrome and IA

### 5.1 Time travel chrome

**Header (Amiga only):** segment beside realm switcher — **Present day | Time travel**. Present strips `as=` on the current path; Time travel sets default `as=` (first calendar year) or keeps active `as=`.

**Ribbon (when `as=` active):** compact bar **above** hub or player nav — one row: **Year | Month | Event** segments · chevrons + snapshot label · listbox picker. No separate title line; no entry link below hub; no exit link (Present day segment replaces it).

**Table sort carry:** same-path ribbon navigation preserves active `k2_sort` / `k2_dir` (PHP hrefs + picker; JS refreshes ribbon after column sort). Cross-page links (hub tabs, other wings) do not carry sort indices.

When inactive: header segment only; no ribbon below/above nav.

### 5.2 Copy rules

Use **time travel** / **as of** / **present** consistently. Avoid mixing "historical", "snapshot", "archive" in user-facing chrome.

### 5.3 Unwired sections

When `as` is active on a page **not yet wired** (including profile in phase 1): show **present-day data** only; optional muted ribbon or page note: “This section still shows present-day data.”

---

## 6. PHP architecture

| Module | Role |
|--------|------|
| `includes/amiga_snapshot_context.php` | Parse `as`, resolve cutoff, `is_active()`, `cutoff()`, `label()`, `query_suffix()` |
| `includes/amiga_snapshot_url.php` (or helpers on context) | `amiga_url_with_context($path, $query)` |
| `includes/amiga_snapshot_chrome.php` | Ribbon HTML; included from Amiga layout |
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
| Link carry | Hub → LB → HoF preserves `as`; profile URL may carry `as` but page stays present |
| LB rating at event X | Matches rating wing at same cutoff (snapshot ladder oracle) |
| HoF at year Y | Holder fields match `amiga_realm_snapshots` row at resolved cutoff |
| Profile with `as` active | Present-day data only (phase 1); optional unwired note |
| Exit to present | Drops param; returns to current tables |

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

- Phase 1 scope: **leaderboards, HoF** + **infrastructure** — see implementation plan. **No profile snapshot reads in phase 1.**
- Do not wire games/tournament catalogs or profile until explicitly scheduled in §4.3.
- After phase 1 ship: UPDATE_DOCS Part A; feature-log row; cross-link from `amiga-data-contract.md` authority map.
