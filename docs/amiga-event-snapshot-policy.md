# Amiga event snapshots — foundational policy

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Locked** (Jun 2026) — design authority; implementation **complete** (Jun 2026).  
**Implementation plan:** [`amiga-event-snapshot-implementation-plan.md`](amiga-event-snapshot-implementation-plan.md)

**Supersedes (for player timeline truth):** sparse V2 intent in [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) §6 · per-table placement for retired tables in [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 (snapshots become the home for career + event-local + honours cumulative at participated-event grain).

**Related:** [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) (commit boundary) · [`amiga-data-contract.md`](amiga-data-contract.md) (layers) · [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) (historical surfaces)

---

## 1. Executive summary

Amiga player derived truth is a **sparse timeline**: one complete row per `(player_id, tournament_id)` for every tournament the player **played in** (≥1 game), written at **tournament finalize**.

| Table | Grain | Role |
|-------|-------|------|
| **`amiga_player_event_snapshots`** | `(player_id, tournament_id)` participated | **Canonical authority** — full player state after that event |
| **`amiga_player_current`** | `player_id` | **Materialized present** — each player’s latest snapshot row; not independently editable |

**There is no separate concept of “current” in the data model.** Present-day reads use `amiga_player_current`. Historical reads use snapshots at a cutoff. “Today” on the website means **after the last finalized event**, same as now.

**Finalize rule (no exceptions):** for each participant with games in the tournament, write one **full equivalent row** to snapshots and upsert `amiga_player_current` in the **same transaction**.

---

## 2. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **S1** | **Canonical grain** | `(player_id, tournament_id)` where the player had ≥1 game in that tournament |
| **S2** | **Full row** | Store the **complete** player fact row at finalize — career stats, event-local facts, honours rollups, rating commit fields, game-ID pointers, ratios. **No column cherry-picking** per surface |
| **S3** | **Table names** | `amiga_player_event_snapshots` (timeline) · `amiga_player_current` (present projection). **Retire** `amiga_player_stats`, `amiga_rating_events`, `amiga_player_tournament_participation`, `amiga_player_tournament_totals` after migration |
| **S4** | **Current is projection** | `amiga_player_current` is updated atomically when a new snapshot row is written. Verify: current row = latest snapshot row per player. Snapshots win on conflict. **Ops/finalize must not read `current` for inputs** — only write it as output; website reads are the consumer |
| **S5** | **Non-participants** | Players who did not play in event *E* get **no** new snapshot row; their state at cutoff *E* is their **last prior** snapshot |
| **S6** | **Historical read pattern** | At cutoff *T*: per `player_id`, take the snapshot row with maximum tournament chrono ≤ *T*; sort/filter for the surface |
| **S7** | **Present read pattern** | `SELECT … FROM amiga_player_current` (or PHP helper). Optional speed test later; separate current table is allowed for ergonomics even if “latest from snapshots” is fast enough |
| **S8** | **New fields** | Any new player fact added to the product → add column(s) to **both** snapshot and current schemas; finalize writer always populates them. No “current-only” columns |
| **S9** | **Commit boundary** | Unchanged: only **tournament finalize** writes snapshots/current (plus full replay rebuild). Per-game ops write `amiga_game_ratings` only |
| **S10** | **Streak columns** | **Stored** on snapshot/current for engine parity (`PlayerState`); **not displayed** in Amiga product (existing policy) |
| **S11** | **Separate grains (unchanged)** | Per-game (`amiga_game_ratings`), phase standings (`amiga_tournament_standings`), H2H (`amiga_player_matchup_summary`), realm (`amiga_generalstats` + `amiga_realm_snapshots` per [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)) |
| **S12** | **Inverse victim/culprit counts (exception to S5)** | `MostGoalsScoredCulprits`, `BiggestWinCulprits`, `MostGoalsConcededVictims`, `BiggestLossVictims` on a player's sparse snapshot row are **not** TT-authoritative when the player did not participate in the event where the count changed. **Shipped:** sparse changelog `amiga_player_inverse_count_at_event` — [`amiga-player-inverse-count-timeline-policy.md`](amiga-player-inverse-count-timeline-policy.md). Chronology pointer inventory reads remain correct via other players' snapshots. |

---

## 3. Mental model

```text
Question: "What does player X have?"
Answer:   "After which event?" → row at that cutoff
          Default: latest participated event (= amiga_player_current)

Question: "Who leads the goals leaderboard in November 2003?"
Answer:   Snapshots at month cutoff → last row per player → ORDER BY GoalsFor
```

Between finalizes, nothing changes — same as rating history V1. Month/year wings are **as-of** cutoffs on the snapshot timeline, not new commits.

---

## 4. Row shape (one wide row)

Each snapshot row contains **three logical blocks** on the same `(player_id, tournament_id)` key. Column names should mirror retired tables where possible to ease read-path migration.

### 4.1 Keys and catalog denorm

| Column group | Examples |
|--------------|----------|
| Primary key | `player_id`, `tournament_id` |
| Chrono (for cutoff queries) | `event_date`, `event_chrono` |
| Catalog denorm | `tournament_name`, `is_cup`, `country`, `has_league`, `has_cup` |
| Commit meta | `finalized_at` |

Index: `(player_id, event_date, event_chrono, tournament_id)` · `(tournament_id, player_id)`.

### 4.2 Event-local block (this tournament only)

Mirrors today’s `amiga_player_tournament_participation` / rating-event fields for **this** event:

`games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `avg_goals_for`, `avg_goals_against`, `event_points`, `event_finish_position`, `is_winner`, `best_knockout_phase`, `rating_before`, `rating_delta`, `rating_after`, `performance_rating`, `games_in_event`, …

### 4.3 Career cumulative block (as of end of this event)

Mirrors today’s `amiga_player_stats` / `PlayerState.to_db_row()` **in full**:

`Rating`, `NumberGames`, W-D-L, goals, ratios, extremes, network counts, peaks/nadirs, **all `*GameID` / `*VictimID` / `*CulpritID` pointers**, ascent/descent fields, streak columns (stored only), `LastGame`, …

### 4.4 Honours cumulative block (as of end of this event)

Mirrors today’s `amiga_player_tournament_totals`:

`tournaments_played`, `tournaments_won`, `event_gold`, `event_silver`, `event_bronze`, `event_podiums`, `wc_*`, `last_event_date`, `last_tournament_id`, …

### 4.5 Career derived highlights (on snapshot for LB parity)

Fields needed for leaderboards that are **running career extrema** (not plain cumulative), computed during finalize and stored on each snapshot:

- `career_best_performance_rating` + `career_best_performance_tournament_id` (and any columns needed to render performance-rating LB historically)
- `elo_rank` — career rating ladder position after this event (LB sort: `Rating DESC`, `player_id ASC`); also mirrored on `amiga_player_elo_rank_at_event` for **every** player with games > 0 each finalize (supports time-travel hero + future rank chart API)

Exact column list = union of §4.2–4.5 in implementation plan DDL slice; **default is include, not exclude**.

---

## 5. Read-path register (target)

| Surface | Present | Historical (cutoff) |
|---------|---------|---------------------|
| Profile hero, career strip, moments | `amiga_player_current` | Optional later: snapshot at date |
| Leaderboards (all wings) | `amiga_player_current` | `amiga_player_event_snapshots` + history lib |
| Rating chart API | snapshots (player filter) | same |
| Player tournament history | snapshots `WHERE player_id = ?` ORDER BY chrono | same (replaces participation table) |
| Tournament event stats | snapshots `WHERE tournament_id = ?` | cutoff ≤ that event |
| History hub / races | — | snapshots + catalogs (generalize V1 lib) |
| HoF | `amiga_generalstats` (ratio leaders on row) | [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) → `amiga_realm_snapshots` at cutoff (historical UI follow-on) |

**PHP rule:** hot paths use `amiga_player_current` or snapshot helpers — **not** retired table names after migration.

---

## 6. Finalize writer (conceptual)

```text
1. Load prior career from latest snapshot strictly before this event (empty for debutants);
   entry Elo from last snapshot before this event; prior career-best from same prior snapshot
2. Process tournament games (frozen within-event Elo) → `amiga_game_ratings` rows
3. Build in-memory event rating commits; update PlayerState; standings + catalog for this event
4. Build participation-shaped rows in memory; derive network + peaks from cumulative matchups; for each active participant:
     INSERT full `amiga_player_event_snapshots` row
     UPSERT `amiga_player_current` from that row
5. Persist `amiga_player_matchup_at_event` + upsert `amiga_player_matchup_summary` for participants
6. Persist realm snapshot + `amiga_generalstats` ([`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md))
7. Mark `tournaments.rating_finalized`
```

**Batch replay:** loop finalize only — **no** `commit_heavy_player_derived` or matchup/generalstats/catalog tail batches at end.

**Proof path:** `python -m scripts.amiga prove` — nuclear reset + replay + verify suite. Partial derived repair (reopen + single finalize) is **retired** — unsafe for cumulative truth ([`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md)).

---

## 7. Tables retired after migration

| Retired table | Replaced by |
|---------------|-------------|
| `amiga_player_stats` | `amiga_player_current` |
| `amiga_rating_events` | snapshot event-local + career rating columns |
| `amiga_player_tournament_participation` | snapshot event-local block |
| `amiga_player_tournament_totals` | snapshot honours block + `amiga_player_current` |

**Not retired:** `amiga_game_ratings`, `amiga_tournament_standings`, `amiga_player_matchup_summary`, `amiga_generalstats`, ground truth tables.

---

## 8. Verification

| Check | Expect |
|-------|--------|
| Row count | `COUNT(snapshots)` ≈ participated player×event pairs (~4.5k today) |
| Current count | `COUNT(current)` = players with ≥1 snapshot |
| Parity | `current.*` = latest snapshot row per player (column-wise, within tolerance) |
| Present rating LB | Matches pre-migration order |
| History event wing last step | Unchanged vs V1 |
| Event-local | Snapshot event block matches games rollup for that tournament |
| Honours | Snapshot honours block matches rollup from prior snapshots + this event |

---

## 9. Out of scope (this track)

| Topic | Notes |
|-------|--------|
| Historical HoF **UI** wings | Storage: [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md); UI follow-on |
| Historical H2H | Different grain |
| Dense every-player-every-tournament table | Not needed |
| Online `kooldb*` | Separate realm |

---

## 10. Agent policy

- **Part B** migration registers apply when DDL + finalize writers ship.
- Do not add new reads from retired tables after cutover.
- Implementation: [`amiga-event-snapshot-implementation-plan.md`](amiga-event-snapshot-implementation-plan.md) — slices in order in one chat unless Dagh splits.
