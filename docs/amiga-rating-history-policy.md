# Amiga rating history — policy (historical ladder snapshots)

**Status:** **Policy locked** (Jun 2026). **V1:** surface only — no new stored truth. **V2:** sparse cumulative stats at finalize (deferred).  
**Purpose:** Historical **rating ladder** snapshots at chosen moments — browse past leaderboards, later animation / bar-chart race inputs.

**Implementation:** [`amiga-rating-history-implementation-plan.md`](amiga-rating-history-implementation-plan.md)

**Authority:** Rating timeline = [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) · layers = [`amiga-data-contract.md`](amiga-data-contract.md) · player surfaces = [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md)

**Hub placement (dev):** dedicated **History** tab on the Amiga hub (`/amiga/history.php`) — temporary navigation for building the feature. Product IA may later fold “present vs history” under Leaderboards; do not block V1 on that redesign.

---

## 1. Executive summary

### What we are building

A **time-travel rating ladder**: at any chosen moment, show who was on the ladder, in rank order, with Elo — using truth already committed at tournament finalize.

| Phase | Data | Surface |
|-------|------|---------|
| **V1** | `amiga_rating_events` + `tournaments` (compute on read) | History hub tab · Event / Month / Year wings · chevrons + picker · rating + rank only |
| **V2** | Sparse cumulative career columns on the event timeline (schema + finalize writers) | Same shell; add LB columns (games, goals, …) incrementally |

V1 does **not** materialize dense snapshot tables (`603 × 600`). Full ladder at moment T = **last `rating_after` per player** among events on or before T, then sort.

### Why sparse compute is enough

- ~603 finalized tournaments, ~600 players, ~4,500 `amiga_rating_events` rows today.
- Extracting ~600 rows (one per active player) and sorting is milliseconds — no second stored ladder required for V1.
- V2 widens each sparse row; the read pattern stays “last row per player before cutoff.”

---

## 2. Locked product decisions (V1)

| # | Decision | Rule |
|---|----------|------|
| **H1** | **Columns** | **Rating + rank only** (plus player name / country for display). No games, W-D-L, opp avg in V1. |
| **H2** | **Three wings** | **Event · Month · Year** — tabbed sub-navigation on the History page. Each wing has its own snapshot catalog and prev/next chevrons + direct picker (dropdown or equivalent). |
| **H3** | **Roster rule** | Include **only** players with ≥1 `amiga_rating_events` row on or before the snapshot cutoff. **No** players before their first event; no placeholder rows; no “—”. |
| **H4** | **Sort / rank** | Sort by exact `rating_after` **DESC** (`decimal(10,6)`), then stable tie-break (`player_id ASC`). **Unique rank** (1…N). Display Elo as **rounded integer** (same as current rating LB); sort uses full precision — display ties are cosmetic only. |
| **H5** | **Tournament scope** | **Finalized historical tournaments only** (`rating_finalized = 1` / import-complete catalog). No live / open tournament path in V1. |
| **H6** | **Hub tab** | Add **History** to `amiga_hub_nav.php` → `/amiga/history.php`. Dev surface; IA may change later. |
| **H7** | **Stored truth** | V1 = **L0 read-time** — no DDL, no finalize/replay writer changes. |
| **H8** | **Authority** | Never use Access `Rankings` grid. `amiga_rating_events` is the ladder timeline. |

---

## 3. Snapshot semantics (three wings)

All wings answer: *“What was the rating ladder after the last rating commit on or before this cutoff?”*

Between finalizes, ratings are **flat** (finalize-boundary model). Month/year views are **as-of** cutoffs, not new commits.

### 3.1 Event wing

| Field | Rule |
|-------|------|
| **Moments** | One snapshot per **finalized** tournament, in catalog chrono order (`event_date ASC`, `chrono ASC`, `id ASC`). |
| **Cutoff** | Through that tournament inclusive. |
| **Label** | Tournament name + formatted `event_date` (e.g. “World Cup XVII · Nov 2003”). |
| **Count** | ~603 steps (current catalog). |

Chevrons: previous / next **tournament** in chrono order among finalized events.

### 3.2 Month wing

| Field | Rule |
|-------|------|
| **Moments** | One snapshot per **calendar month** that has ≥1 finalized tournament with `event_date` in that month, **or** optionally every month from first ladder month through last — **implementation default:** only months where at least one finalize occurred (fewer picker entries). |
| **Cutoff** | Last finalize in that month (max chrono among tournaments with `event_date` in `YYYY-MM`). If multiple tournaments same month, ladder reflects state **after the last one** in chrono order. |
| **Label** | “November 2003” (locale-neutral `Month YYYY`). |
| **Empty months** | Skip — no snapshot row (picker only lists months with at least one finalize). |

Chevrons: previous / next **month that has a snapshot** in the catalog.

### 3.3 Year wing

| Field | Rule |
|-------|------|
| **Moments** | One snapshot per **calendar year** with ≥1 finalized tournament. |
| **Cutoff** | Last finalize in that year (max chrono among tournaments with `YEAR(event_date) = Y`). |
| **Label** | “2003”. |

Chevrons: previous / next **year** in the catalog.

### 3.4 URL / state

- Default: latest snapshot in the active wing (current ladder ≈ event wing last tournament).
- Query params (illustrative): `?wing=event|month|year` and `?at=<id>` where `<id>` is wing-specific key (`tournament_id`, `YYYY-MM`, or `YYYY`).
- Chevrons and picker update URL for bookmarking.

---

## 4. Data model (V1 — read only)

### Source tables

| Table | Role |
|-------|------|
| `amiga_rating_events` | `rating_after` per `(player_id, tournament_id)` — sparse timeline |
| `tournaments` | Chrono order, labels, `event_date`, `rating_finalized` filter |
| `amiga_players` | Name, country (display) |

### Ladder extraction (conceptual)

For cutoff `(event_date, chrono, tournament_id)`:

1. Consider all `amiga_rating_events` joined to `tournaments` where tournament chrono ≤ cutoff.
2. Per `player_id`, keep the row with **maximum** tournament chrono (tie-break `tournament_id`).
3. Join `amiga_players` for display fields.
4. Sort by `rating_after DESC`, `player_id ASC`; assign rank 1…N.

**Performance:** Full table today is ~4.5k rows — filter + group is cheap. If `EXPLAIN` ever complains, denorm `event_date` / `event_chrono` onto `amiga_rating_events` (participation already does) — **not required for V1**.

### Indexes (existing)

- `uq_rating_event_tournament_player`
- `idx_rating_events_player_chrono`
- `idx_rating_events_tournament`
- Tournament chrono: `tournaments(event_date, chrono)` via join

---

## 5. UI structure (V1)

```text
Amiga hub:  … | History | …

/amiga/history.php
  H1: Historical ladder  (chapter)
  Sub-wings: [ Event ] [ Month ] [ Year ]
  ◀  {snapshot label}  ▶     [ picker ▾ ]
  Table: Rank | Player | Elo | Country (optional)
```

- Reuse `k2-table` sortable patterns where helpful; default sort = rank (pre-sorted server-side).
- Link player names → `/amiga/player/profile.php`.
- Optional footnote: “Ratings commit at tournament finalize; within-month steps reflect event order.”

**Not in V1:** animation player, bar chart race export, comparison to present-day LB side-by-side, API JSON for external tools (can add thin `api/` later if needed).

---

## 6. V2 intent (deferred — not V1 scope)

### Goal

Store **cumulative career stats as of each event** on the sparse timeline (same grain as `amiga_rating_events` — one row per player per tournament they played).

### Approach (preferred)

- **Widen sparse rows** (extend `amiga_rating_events` or sibling `amiga_player_career_snapshots` at same PK) with cumulative fields copied from finalize state: `NumberGames`, goals, W-D-L, `DifferentOpponents`, `AverageOpponentRating`, etc.
- **Do not** materialize dense `603 × all_players` tables unless profiling demands it.
- Serving full historical LB with many columns = same “last row per player before cutoff” query on wider rows.

### Rough storage (ballpark)

| Shape | Rows | Size (order of magnitude) |
|-------|------|---------------------------|
| Sparse cumulative per event played | ~4,500 | **1–20 MB** depending on column count |
| Dense every player every tournament | ~100k–180k | **25–150 MB** |

V2 triggers **Part B** migration docs (finalize Python + PHP parity, verify, replay).

### V2 surface

Same History page — add columns wing by wing. No redesign required if V1 shell is sound.

---

## 7. Out of scope

| Topic | Reason |
|-------|--------|
| Live / running tournaments | Not developing live entry alongside this |
| Access `Rankings` parity | Reference only |
| Per-game ladder steps inside an event | Contradicts finalize contract |
| Historical match streaks | Amiga match-streak policy |
| Dense snapshot cache / animation export | Defer until after V1 ship or explicit ask |
| Merging History into Leaderboards IA | Product pass after V1 works |

---

## 8. Verification (V1)

| Check | Expect |
|-------|--------|
| Event wing last step | Matches current `/amiga/leaderboards/rating.php` order (same `rating_after` sources) |
| Player count at early snapshot | Small; grows monotonically over time |
| Player absent before debut | Not listed |
| Month with 2 tournaments | Same as event wing after **later** tournament in chrono order |
| Rank uniqueness | No duplicate rank integers |

Spot SQL + browser checks in implementation plan.

---

## 9. Agent policy

- V1: **no** `scripts/amiga/sql/` migrations; **no** finalize/replay changes.
- Read path: new helper(s) in `includes/amiga_rating_history_lib.php` (name TBD) — not raw SQL in templates.
- Register new surface in [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §4 when shipped.
- Animation / “chart race” is a **follow-on** using the same snapshot catalog + sparse extraction.
