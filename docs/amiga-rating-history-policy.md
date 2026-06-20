# Amiga rating history — policy (historical ladder snapshots)

**Status:** **V1 implemented** (Jun 2026). **V2:** superseded by [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) (full event snapshots + `amiga_player_current`).  
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
| **V1** | `amiga_rating_events` + `tournaments` (compute on read) | History hub tab · Event / Month / Year wings · chevrons + picker · rating + rank + Δ |
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
| **H1** | **Columns** | **Rating + rank + Δ** (plus player name / country for display). Δ = wing-step rating change (see §3.5). No games, W-D-L, opp avg in V1. |
| **H2** | **Wings** | **Event · Month · Year** — tabbed sub-navigation on the History page. Each wing has its own snapshot catalog and prev/next chevrons + direct picker (dropdown or equivalent). World Cups use **Year** (or **Event**) — no separate WC wing. |
| **H3** | **Roster rule** | Include **only** players with ≥1 `amiga_rating_events` row on or before the snapshot cutoff. **No** players before their first event; no placeholder rows; no “—”. |
| **H4** | **Sort / rank** | Sort by exact `rating_after` **DESC** (`decimal(10,6)`), then stable tie-break (`player_id ASC`). **Unique rank** (1…N). Display Elo as **rounded integer** (same as current rating LB); sort uses full precision — display ties are cosmetic only. |
| **H5** | **Tournament scope** | **Finalized historical tournaments only** (`rating_finalized = 1` / import-complete catalog). No live / open tournament path in V1. |
| **H6** | **Hub tab** | Add **History** to `amiga_hub_nav.php` → `/amiga/history.php`. Dev surface; IA may change later. |
| **H7** | **Stored truth** | V1 = **L0 read-time** — no DDL, no finalize/replay writer changes. |
| **H8** | **Authority** | Never use Access `Rankings` grid. `amiga_rating_events` is the ladder timeline. |

---

## 3. Snapshot semantics (wings)

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
| **Moments** | **Every calendar month** from first ladder month through last (inclusive), whether or not a tournament finalized in that month. |
| **Cutoff** | Last finalize with `event_date` on or before the **last day** of that month (chrono max). If no finalize in month, ladder unchanged from prior month — chevrons still advance month by month. |
| **Label** | “November 2003” (locale-neutral `Month YYYY`). |
| **Empty months** | **Included** in picker and chevrons; ladder repeats previous state until the next finalize. |

Chevrons: previous / next **calendar month** in the continuous range (not skip empty months).

### 3.3 Year wing

| Field | Rule |
|-------|------|
| **Moments** | **Every calendar year** from first ladder year through last (inclusive), whether or not a tournament finalized in that year. |
| **Cutoff** | Last finalize with `event_date` on or before **31 Dec** of that year (chrono max). Empty years repeat prior ladder state. |
| **Label** | “2003”. |

Chevrons: previous / next **calendar year** in the continuous range.

### 3.4 URL / state

- Default: latest snapshot in the active wing (current ladder ≈ event wing last tournament).
- Query params (illustrative): `?wing=event|month|year` and `?at=<id>` where `<id>` is wing-specific key (`tournament_id`, `YYYY-MM`, or `YYYY`).
- Chevrons and picker update URL for bookmarking.

### 3.5 Δ column (wing-step change)

| Field | Rule |
|-------|------|
| **Meaning** | Change in displayed Elo vs the **previous snapshot in the same wing** (not “since last event played”). |
| **First snapshot in wing** | Same as ladder debut — baseline **1600** (everyone on that list is new to the ladder). |
| **Player debut on ladder** | Absent from previous wing snapshot → baseline **1600** (Amiga start rating). |
| **Event wing shortcut** | Player did not play in the snapshot tournament → **0** (valid because consecutive event snapshots are consecutive tournaments). |
| **Month / Year** | Full compare vs previous month/year ladder (no participant shortcut). |
| **Display** | Rounded integer; `+N` / `-N` with `.blue` / `.red` spans; `0` neutral (same as game adjustment styling). |

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
  Table: Rank | Player | Elo | Δ | Country
```

- Reuse `k2-table` sortable patterns where helpful; default sort = rank (pre-sorted server-side).
- Link player names → `/amiga/player/profile.php`.
- Optional footnote: “Ratings commit at tournament finalize; within-month steps reflect event order.”

**Not in V1:** ~~animation player~~ (shipped V1.1 on News — see below), bar chart race export, comparison to present-day LB side-by-side, API JSON for external tools (race API added for News embed).

### 5.1 Top-10 line race (News tab — V1.1)

- **Route:** `/amiga/news.php` — two animations:
  - **By tournament** — playhead steps event-by-event (`amiga-top10-rating-race.js`)
  - **By time** — playhead advances on calendar; straight segments between each player&rsquo;s rating events (`amiga-top10-rating-race-by-time.js`)
- **Data:** `GET /api/amiga_top10_rating_race.php` — `sortMs` on series points + `timelineStartMs` / `timelineEndMs` in meta
- **Controls:** Play / pause (starts from first event if at end), speed, scrubber; default view = latest frame, paused.
- **JS:** `js/amiga-top10-rating-race.js` + Chart.js time scale, straight segments between event ratings, end labels for current top 10.

---

## 6. V2 — event snapshots (authority elsewhere)

**Superseded (Jun 2026).** Full player timeline + present projection:

- **Policy:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md)
- **Plan:** [`amiga-event-snapshot-implementation-plan.md`](amiga-event-snapshot-implementation-plan.md)

Summary: `amiga_player_event_snapshots` (canonical, full row per participated event) + `amiga_player_current` (materialized latest). Historical surfaces generalize the V1 read pattern on the wider snapshot rows. Retires `amiga_rating_events`, `amiga_player_stats`, `amiga_player_tournament_participation`, `amiga_player_tournament_totals`.

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
