# Amiga realm snapshots — foundational policy

**Status:** **Locked** (Jun 2026) — implementation **complete** (slices 0–8); historical HoF UI follow-on.  
**Implementation plan:** [`amiga-realm-snapshot-implementation-plan.md`](amiga-realm-snapshot-implementation-plan.md)

**Parent:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) (player timeline — complete) · [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.5

**Supersedes:** deferred “realm snapshots later” notes in event-snapshot policy §9 · live ratio queries on `amiga_player_current` for HoF authority · end-of-replay-only `generalstats-rebuild` as sign-off path.

---

## 1. Executive summary

Amiga **realm-wide** derived truth is a **sparse timeline**: one **complete** `amiga_generalstats`-shaped row per **finalized tournament**, written at **tournament finalize**.

| Table | Grain | Role |
|-------|-------|------|
| **`amiga_realm_snapshots`** | `tournament_id` (one row per finalized event) | **Canonical authority** — full realm / HoF state after that event |
| **`amiga_generalstats`** | `id = 1` | **Materialized present** — latest realm snapshot row; not independently editable |

**Full row rule:** each snapshot stores the **entire** HoF / record-book column set on `amiga_generalstats` (career extremes, single-game records, ratio leaders). Realm-wide headline totals (`GamesPlayed`, `GoalsScored`, …) live on [`amiga_community_stats`](amiga-community-stats-policy.md) — not on realm snapshot rows (since `035`).

**Finalize rule (no exceptions):** after player snapshots and matchup commits for tournament *E*, compute realm state through end of *E*, `INSERT` full row into `amiga_realm_snapshots`, `UPDATE` `amiga_generalstats` from that row — **same transaction** as other finalize derived writes where practical.

---

## 2. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **R1** | **Canonical grain** | One row per **finalized** `tournament_id` (chrono order follows tournament catalog) |
| **R2** | **Full row** | Snapshot row = **complete** HoF record-book payload (every data column on `amiga_generalstats` after `035`). Record holders are one blob per cutoff — enables historical HoF from the same cutoff. Realm headline totals = community stats tables |
| **R3** | **Table names** | `amiga_realm_snapshots` (timeline) · `amiga_generalstats` (present projection, unchanged name) |
| **R4** | **Current is projection** | `amiga_generalstats` id=1 is updated atomically when a new realm snapshot is written. Verify: `generalstats.*` = latest realm snapshot row (column-wise). **Finalize must not read `generalstats` for inputs** — only write it as output; website reads are the consumer |
| **R5** | **Commit boundary** | Tournament finalize only (plus full replay rebuild). Per-game ops do not touch realm snapshots |
| **R6** | **Ratio leaders persisted** | Win / attack / defense / goal / DD / CS ratio record holders live **on the row** (value + player id + name). **Not** live `ORDER BY` on `amiga_player_current` at read time (deliberate improvement over online `generalstatstable` + `records_ratio_leaders.php` split) |
| **R7** | **No streak records** | Amiga product omits match-streak and play-streak HoF columns — unchanged from [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.5 |
| **R8** | **Tie policy** | Career / single-game record beats: **strict `>`** on value to replace holder; equal value → **lowest `player_id` wins**. Ratio leaders: same eligible pool (`NumberGames >= k2_established_min_games()`), order by metric then `player_id ASC` |
| **R9** | **Compute scope at finalize** | Realm row at event *E* = scan **all rated games and player state through chrono ≤ *E*** (same semantic as batch `server_records.py` today, scoped to cutoff). Incremental carry-forward optimizations allowed if verify proves parity |
| **R10** | **No replay tail batch** | `python -m scripts.amiga prove` must not depend on post-replay `generalstats-rebuild`. CLI remains **repair oracle** only |
| **R11** | **WC medals panel** | World Cup gold/silver/bronze leaders on `/amiga/hall-of-fame.php` stay **out of** `amiga_generalstats` / realm snapshots until a separate decision — honours live on player snapshots/current today |

---

## 3. Mental model

```text
Question: "What is the Amiga realm record book?"
Answer:   "After which event?" → realm snapshot row at that cutoff
          Default: latest finalized event (= amiga_generalstats id=1)

Question: "How many goals had the realm scored by end of 2003?"
Answer:   Realm snapshot at month/year/event cutoff → GoalsScored column
          (same row as HoF — no separate aggregate store)
```

Between finalizes, realm derived truth is unchanged — same habit as player snapshots.

---

## 4. Row shape

### 4.1 Timeline keys (snapshot only)

| Column group | Examples |
|--------------|----------|
| Primary key | `tournament_id` |
| Chrono | `event_date`, `event_chrono` |
| Catalog denorm | `tournament_name` (optional, for debug/export) |
| Commit meta | `finalized_at` |

Index: `(event_date, event_chrono, tournament_id)`.

### 4.2 Payload — mirror `amiga_generalstats`

**Column manifest** = all non-`id` columns on `amiga_generalstats` after this track’s DDL (see implementation plan slice 1). Today that is:

| Block | Count | Examples |
|-------|-------|----------|
| **Realm aggregates** | 14 | `NumberOfPlayers`, `GamesPlayed`, `GoalsScored`, `DecidedGamesRatio`, … |
| **Record holders** | ~87 | `MostGamesPlayed` + id/name/date; single-game records + `*GameID`; ratio leaders (`BiggestWinRatio` + id/name); … |

**Baseline DDL:** 83 columns on `amiga_generalstats` (`013_generalstats.sql`). **This track adds** 18 columns for six ratio/average leaders (value + id + name each) → **~101 data columns** on both tables.

**`BiggestRatingAscent`:** remains in the row (writer populates); HoF UI may omit until a product slice adds a row — storage is not optional.

**New HoF / realm field:** add column to **both** `amiga_generalstats` and `amiga_realm_snapshots`; finalize always populates. No “current-only” or “HoF-only” columns.

---

## 5. Read-path register (target)

| Surface | Present | Historical (cutoff) |
|---------|---------|---------------------|
| Hall of Fame main panel | `amiga_generalstats` | `amiga_realm_snapshots` + cutoff helper (later UI slice) |
| Ratio leader rows on HoF | `amiga_generalstats` (not live SQL) | realm snapshot at cutoff |
| WC medals panel (3 rows) | `amiga_player_current` honours | unchanged (R11) |
| Future realm activity / stats charts | — | [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) — **not** realm snapshot aggregate cols after migration |

**PHP rule:** present HoF hot path reads `amiga_generalstats` only — **not** `amiga_records_load_ratio_leaders()` against `amiga_player_current` after cutover.

---

## 6. Finalize writer (conceptual)

```text
1. … existing finalize: games → ratings → standings → player snapshots/current → matchups
2. Load prior realm snapshot for tournament before E (or empty)
3. Compute full generalstats-shaped patch through end of E:
     - aggregates from amiga_games + amiga_game_ratings (games ≤ E)
     - career holders from player snapshot/current at cutoff E (or in-memory PlayerState map)
     - single-game records from games ≤ E
     - ratio leaders from established players at cutoff E
4. INSERT amiga_realm_snapshots (full row)
5. UPDATE amiga_generalstats id=1 from that row
6. Mark tournament finalized (existing)
```

**Batch replay:** each tournament finalize performs step 2–5 — **no** `generalstats-rebuild` at end.

**Refinalize tournament *T*:** rewrite realm snapshot at *T*, recompute forward snapshots for later tournaments (inherent to cumulative realm scans — same class of problem as player snapshot refinalize).

---

## 7. Verification

| Check | Expect |
|-------|--------|
| Row count | `COUNT(realm_snapshots)` = count of finalized tournaments in chrono replay |
| Present parity | `amiga_generalstats` = latest realm snapshot row (all columns) |
| Batch oracle | One-shot `generalstats-rebuild` after full history **matches** finalize-built present row (repair CLI only) |
| HoF page | Present holders match pre-migration order within tie policy |
| Aggregates | `GamesPlayed` on latest snapshot = `COUNT(amiga_games)` with ratings |

---

## 8. Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Persist only “HoF holder” columns, skip aggregates | Blocks historical realm totals; false split; re-opens column debates |
| Keep ratio leaders as live SQL on `amiga_player_current` | Online fatigue; finalize is infrequent; blocks historical ratio HoF |
| End-of-replay `generalstats-rebuild` only | Breaks holy loop; stale present row today; inconsistent with player/matchup finalize commits |
| Dense realm row every calendar day | Unnecessary — tournament finalize is the product commit boundary |
| Port online streak / play-streak HoF columns | Locked out of Amiga product |

---

## 9. Out of scope (this track)

| Topic | Notes |
|-------|--------|
| Historical HoF **UI** (event / month / year wings) | Follow-on read-path slice; storage must exist first |
| WC medals in realm row | R11 — separate decision |
| Online `generalstatstable` | Separate realm |
| Per-game incremental generalstats | Amiga commits at finalize only |
| **Community stats / Activity aggregates** | [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) — separate tables; headline cols on realm row are legacy until migration |

---

## 10. Agent policy

- **Part B** migration registers apply when DDL + finalize writers ship.
- Extend [`scripts/amiga/server_records.py`](../scripts/amiga/server_records.py) — do not fork a second record-compute implementation.
- Implementation: [`amiga-realm-snapshot-implementation-plan.md`](amiga-realm-snapshot-implementation-plan.md) — slices in order unless Dagh splits.
