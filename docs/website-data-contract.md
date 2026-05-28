# Website data contract

**Purpose:** One canonical description of the database truth this website expects beyond the raw ladder history.

Given a ground-truth database, this repo should be able to rebuild every project-owned derived table described here and then serve the website without live historical scans on hot paths.

---

## Authority

This document owns:

- what each project-owned derived table means
- how each table is rebuilt from ground truth
- how each table must change after one new rated game
- how to validate parity between rebuilds and source truth

Deployment status is tracked elsewhere:

- Schema status: `docs/coordination/schema-register.md`
- Rebuild/run status: `docs/coordination/replay-register.md`
- User-facing feature map: `docs/coordination/feature-log.md`

Those registers link here for behavior; they do **not** duplicate post-game rules.

### Agent policy (post-game)

- **Local / staging:** Apply schema + run `*_rebuild.sql` ([`scripts/rebuild_website_derived_data_local.ps1`](../scripts/rebuild_website_derived_data_local.ps1)). That is sufficient for PHP to use stored truth.
- **Prod live games:** Steve merges C++ from this document’s **Post-game rule** sections at cutover — not from retired per-table snippet packs.
- **Exception:** server records / `generalstatstable` — [`docs/coordination/records-post-game-exception.md`](coordination/records-post-game-exception.md).

### Derived data index

| Table | Schema | Rebuild (REP) | Post-game (contract §) |
|-------|--------|---------------|-------------------------|
| `player_period_games` | SCH-004, SCH-006 | `player_period_games_rebuild.sql` | Both players × day/week/month/year +1 |
| `player_peak_period_games` | SCH-006 | `player_peak_period_games_rebuild.sql` | After period games; update peak if beaten |
| `server_daily_activity` | SCH-007 | `server_daily_activity_rebuild.sql` | +1 game/day; +active if first game that day |
| `player_period_league` | SCH-008 | `player_period_league_rebuild.sql` | W/D/L/points per period |
| `league_period` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only** — finalize closed periods |
| `player_league_award` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only** |
| `player_league_totals` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only**; re-aggregate from awards |
| `player_league_slice_totals` | SCH-010 | `player_league_slice_totals_rebuild.sql` (REP-013) | **Periodic only**; with career totals after awards |
| `player_milestones` | SCH-008 | `player_milestones_rebuild.sql` | Insert on threshold cross |
| `player_matchup_summary` | SCH-008 | `player_matchup_summary_rebuild.sql` | Directed pair upsert ×2 |
| `server_period_game_totals` | SCH-008 | `server_period_game_totals_rebuild.sql` | Server totals ×4 period types |
| `server_period_matchups` | SCH-008 | `server_period_matchups_rebuild.sql` | Canonical pair ×4 period types |
| `player_monthly_league` | SCH-005 | `player_monthly_league_rebuild.sql` | Legacy monthly (prefer period league) |
| `generalstatstable` | SCH-002–003 | Ladder replay | **Exception doc** — records tie/UTC/ratio |

---

## Source truth

### `ratedresults`

One row per rated online game. This is the canonical per-game history for website aggregates.

Important contract details:

- `Date` is a MySQL `timestamp`.
- All PHP connections and all rebuild SQL scripts must use `SET time_zone = '+00:00'` before deriving periods from `Date`.
- Period starts use UTC boundaries:
  - day: `DATE(Date)`
  - week: Monday of the UTC date's week
  - month: first day of the UTC month
  - year: Jan 1 of the UTC year
- Outcomes should use `ActualScore`:
  - `1` means player A won
  - `0.5` means draw
  - `0` means player B won

Schema reference: `docs/ratedresults-schema.md`.

### `playertable`

Canonical current per-player ladder state and many legacy/extreme stats. Some contract parity checks compare rebuilt derived facts with `playertable` totals.

Schema reference: `docs/playertable-schema.md`.

---

## Rebuild architecture

### Behavior authority: the chronological event engine

The conceptual source of truth for all derived data is a chronological replay of `ratedresults` in `Date ASC, id ASC` order, applying one post-game function per game. This is the **event engine** (`scripts/ladder/engine.py` — `apply_game_row()` + `update_server_records_after_game()`).

After a full replay, the database must be identical to one that was maintained by a correct live post-game script from an empty state. This property — **rebuild is simulation of live** — is what keeps the post-game contract crisp and testable.

### What the event engine currently owns

- `playertable` career stats (Elo, counts, streaks, extremes, victim/culprit pointers)
- `ratedresults` derived columns (ratings, adjustments, flags)
- `generalstatstable` server records (non-ratio hall-of-fame, aggregate totals)

### What the SQL aggregate rebuilds currently own

- `player_period_games`, `player_peak_period_games`, `server_daily_activity`
- `player_period_league`, `player_milestones`, `player_matchup_summary`
- `server_period_game_totals`, `server_period_matchups`
- `player_monthly_league` (legacy)

These are implemented as bulk `INSERT ... SELECT` SQL scripts run after the event engine replay completes.

### Future direction

The SQL aggregate rebuilds are **correct and fast**, and their UTC timezone pinning was already applied. They remain the production rebuild path for aggregate tables.

However, the event engine is the **behavior authority**: if a SQL rebuild ever disagrees with what a chronological per-game simulation would produce, the event engine definition wins.

If a future aggregate table requires complex stateful logic (e.g. streak-aware records, conditional updates), it should be implemented inside the event engine as an in-memory reducer during replay, with optional SQL bulk-rebuild for speed/parity cross-check.

Current aggregate tables do not need to move into the event engine because they are simple period-bucketed aggregations with no stateful tie-break semantics.

### Normal rebuild pipeline

The normal one-command rebuild flow is:

1. Apply schema migrations in `schema/migrations/` if needed.
2. Run full event-engine replay: `scripts/run_local_replay.ps1` (resets `playertable`, `ratedresults` derived cols, `generalstatstable`).
3. Run website aggregate rebuild: `scripts/rebuild_website_derived_data_local.ps1` (rebuilds all aggregate tables).
4. Parity checks pass.

Implementation entrypoint (aggregates only): `scripts/rebuild_website_derived_data_local.ps1`.

The modular SQL files under `scripts/ladder/sql/` remain implementation units. They are not the conceptual source of truth — this document is.

---

## Tables

### `player_period_games`

**Lifecycle:** Active.

**Purpose:** Fast player activity counts by period. Used by Activity, Hall of Fame/Records, profile activity charts, top activity eras, period leaderboards, and as source for other aggregates.

**Source truth:** `ratedresults`.

**Grain:** one row per `(period_type, period_start, player_id)` where the player has at least one rated game.

**Primary key:** `(period_type, period_start, player_id)`.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `period_start` | UTC period start; week rows use Monday |
| `player_id` | `playertable.ID` |
| `games` | Rated games played by the player in that period |

**Full rebuild:** Count player appearances from both sides of `ratedresults` (`idA` and `idB`) grouped by UTC period and player.

**Post-game rule:** After one rated game, increment `games` by 1 for both players across day, week, month, and year. Eight upserts total.

**Parity check:** For each `period_type`, `SUM(games)` must equal `COUNT(*) FROM ratedresults * 2`.

**Implementation:** `scripts/ladder/sql/player_period_games_rebuild.sql`.

---

### `player_peak_period_games`

**Lifecycle:** Active cache.

**Purpose:** Cache each player's best activity period for peak activity leaderboards.

**Source truth:** `player_period_games`.

**Grain:** one row per `(period_type, player_id)`.

**Primary key:** `(period_type, player_id)`.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `player_id` | `playertable.ID` |
| `period_start` | Earliest period where this peak was achieved |
| `games` | Player's personal best game count for that period type |

**Full rebuild:** For every player and period type, choose the maximum `games`; earliest `period_start` wins ties.

**Post-game rule:** After `player_period_games` is updated for a new game, check each touched player-period row. If it beats the stored peak, or ties with an earlier period, update the cache.

**Parity check:** Every cache row must match the best row in `player_period_games` for that `(period_type, player_id)`.

**Implementation:** `scripts/ladder/sql/player_peak_period_games_rebuild.sql`.

---

### `server_daily_activity`

**Lifecycle:** Active.

**Purpose:** Fast all-time daily Activity chart: rated games and active players per UTC day.

**Source truth:** preferred source is `player_period_games` day rows; raw fallback is `ratedresults`.

**Grain:** one row per UTC day.

**Primary key:** `activity_day`.

| Column | Meaning |
|--------|---------|
| `activity_day` | UTC date |
| `rated_games` | Rated games on that date |
| `active_players` | Distinct players with at least one rated game on that date |

**Full rebuild:** From `player_period_games` day rows: `SUM(games) / 2` for rated games and `COUNT(*)` for active players.

**Post-game rule:** Increment `rated_games` by 1 for the game day. Increment `active_players` only for each player who had no previous `player_period_games` day row before this game.

**Parity check:** `SUM(rated_games)` must equal `COUNT(*) FROM ratedresults`.

**Implementation:** `scripts/ladder/sql/server_daily_activity_rebuild.sql`; emergency fallback `scripts/ladder/sql/server_daily_activity_rebuild_raw.sql`.

---

### `player_period_league`

**Lifecycle:** Active.

**Purpose:** Fast Status league tables for day/week/month/year without historical `ratedresults` aggregation.

**Source truth:** `ratedresults`.

**Grain:** one row per `(period_type, period_start, player_id)`.

**Primary key:** `(period_type, period_start, player_id)`.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `period_start` | UTC period start |
| `player_id` | `playertable.ID` |
| `played` | Rated games played |
| `wins` | Wins in the period |
| `draws` | Draws in the period |
| `losses` | Losses in the period |
| `goals_for` | Goals scored by the player |
| `goals_against` | Goals conceded by the player |
| `goal_difference` | `goals_for - goals_against` |
| `points` | `3 * wins + draws` |

**Full rebuild:** Union player A and player B perspectives from `ratedresults`, derive W/D/L/GF/GA/points for each side, then group by UTC period and player.

**Post-game rule:** Upsert both player perspectives across day/week/month/year. Eight upserts total.

**Parity check:** `SUM(played) / 2` for each period type must equal `COUNT(*) FROM ratedresults`.

**Implementation:** `scripts/ladder/sql/player_period_league_rebuild.sql`.

---

### `league_period`

**Lifecycle:** Active (May 2026).

**Purpose:** Metadata for one closed or open league instance — especially **`period_end`** (exclusive UTC boundary used as the canonical “when” for awards and league milestones).

**Rules:** [`leagues-rules-spec.md`](leagues-rules-spec.md).

**Grain:** one row per `(league_kind, period_type, period_start)`.

**Primary key:** `(league_kind, period_type, period_start)`.

| Column | Meaning |
|--------|---------|
| `league_kind` | `points` or `activity` |
| `period_type` | `day`, `week`, `month`, `year` |
| `period_start` | UTC anchor date (Monday for weeks) |
| `period_end` | Exclusive end instant (`00:00:00` UTC first moment after the league) |
| `rated_games` | Rated games in the period (server total) |
| `finalized_at` | When the daily finalize job wrote awards (audit); product time = `period_end` |

**Full rebuild:** For every instance with `period_end <= now`, compute podium from aggregates + `ratedresults` first-game tie-breaks; insert `player_league_award`; set `finalized_at`.

**Post-game rule:** **None** — standings aggregates update per game; awards finalize in **PER-003** daily batch only.

**Parity check:** For a sample of closed weeks, top 3 `player_league_award` rows match shared ranker output from `player_period_league` / `player_period_games`.

**Implementation:** `scripts/ladder/sql/league_period_awards_rebuild.sql` (REP-012); sorter shared with PHP `includes/league_standings.php` (planned).

---

### `player_league_award`

**Lifecycle:** Active (May 2026).

**Purpose:** **Player-centric** persisted podium — all fields needed for profile/history without joining `league_period`. Source of truth for “this player’s league medals.”

**Rules:** [`leagues-rules-spec.md`](leagues-rules-spec.md) — no shared medals; unique ranks 1–3.

**Grain:** one row per `(player_id, league_kind, period_type, period_start)` for players who finished 1st–3rd.

**Primary key:** `(player_id, league_kind, period_type, period_start)`.

| Column | Meaning |
|--------|---------|
| `period_end` | Copied from `league_period` — achievement timestamp for milestones |
| `finish_rank` | 1, 2, or 3 |
| `medal` | `gold`, `silver`, `bronze` |
| `is_winner` | 1 iff `finish_rank = 1` |
| `points`, `goal_difference`, `goals_for`, `played` | Points league snapshot (NULL for activity) |
| `games` | Activity snapshot (NULL for points) |
| `first_game_id`, `first_game_side` | Tie-break audit (`ratedresults.id`; `A` or `B`) |

**Full rebuild:** Truncate awards + totals; re-finalize all closed periods.

**Post-game rule:** **None** (periodic finalize).

**Implementation:** REP-012 + PER-003.

---

### `player_league_totals`

**Lifecycle:** Active (May 2026).

**Purpose:** Fast career counts — league-wins leaderboard, profile badges, `league_wins_*` milestone thresholds.

**Grain:** one row per `player_id`.

**Primary key:** `player_id`.

| Column | Meaning |
|--------|---------|
| `wins` | Count of `finish_rank = 1` across **all 8** league kinds (any period × points or activity) — used for `league_wins_*` milestones |
| `podiums` | Ranks 1–3 |
| `gold` / `silver` / `bronze` | Medal counts |

**Full rebuild:** `GROUP BY player_id` from `player_league_award`.

**Post-game rule:** **None** — updated by finalize job after awards insert.

---

### `player_league_slice_totals`

**Lifecycle:** Active (May 2026).

**Purpose:** Per-player medal counts for each **league kind × time grain** (e.g. gold in monthly points, bronze in weekly activity). Fast profile reads and League honours slice tables without aggregating `player_league_award`.

**Rules:** [`leagues-rules-spec.md`](leagues-rules-spec.md).

**Grain:** one row per `(player_id, league_kind, period_type)` where the player has ≥1 podium in that slice.

**Primary key:** `(player_id, league_kind, period_type)`.

| Column | Meaning |
|--------|---------|
| `gold` / `silver` / `bronze` | Medal counts in that slice only |
| `podiums` | Top-three finishes in that slice |

**Full rebuild:** `GROUP BY player_id, league_kind, period_type` from `player_league_award` (REP-013). Run after REP-012 or via `k2_league_rebuild_player_aggregates()`.

**Post-game rule:** **None** — rebuilt whenever career totals are rebuilt after finalize.

**Parity check:** For a sample player, `SUM(gold)` across all slice rows equals `player_league_totals.gold`.

**PHP:** `k2_league_player_slice_totals($con, $playerId)` in `includes/league_standings.php`.

---

### `player_milestones`

**Lifecycle:** Active.

**Purpose:** Reusable player milestone facts for Activity and achievement-style surfaces.

**Source truth:** `ratedresults` and `playertable` parity.

**Grain:** one row per `(player_id, milestone_key)`.

**Primary key:** `(player_id, milestone_key)`.

| Column | Meaning |
|--------|---------|
| `player_id` | `playertable.ID` |
| `milestone_key` | Stable milestone identifier |
| `achieved_at` | UTC game timestamp when milestone was first achieved |
| `value` | Threshold value for the milestone |

Current milestone keys:

| Key | Meaning |
|-----|---------|
| `established_20` | Player reached 20 rated games |
| `dd_merchant_10` | Player first scored 10 or more goals in one rated game |

**Full rebuild:** Scan all player appearances in chronological order and insert the first achievement row for each milestone.

**Post-game rule:** If updated player totals or the game score cross a milestone threshold, insert the milestone if it does not already exist.

**Parity check:** `established_20` count must equal `COUNT(*) FROM playertable WHERE NumberGames >= 20`.

**Implementation:** `scripts/ladder/sql/player_milestones_rebuild.sql`.

---

### `player_matchup_summary`

**Lifecycle:** Active.

**Purpose:** Directed player-vs-opponent aggregate totals for profile opponent pages and APIs.

**Source truth:** `ratedresults`.

**Grain:** one directed row per `(player_id, opponent_id)`.

**Primary key:** `(player_id, opponent_id)`.

| Column | Meaning |
|--------|---------|
| `player_id` | Subject player |
| `opponent_id` | Opponent |
| `games` | Games against this opponent |
| `wins` | Wins by subject player |
| `draws` | Draws |
| `losses` | Losses by subject player |
| `goals_for` | Subject goals |
| `goals_against` | Opponent goals |

**Full rebuild:** Union both player perspectives from `ratedresults`, then group by directed `(player_id, opponent_id)`.

**Post-game rule:** Upsert two directed rows: A against B and B against A.

**Parity check:** `SUM(games)` must equal `COUNT(*) FROM ratedresults * 2`.

**Implementation:** `scripts/ladder/sql/player_matchup_summary_rebuild.sql`.

---

### `server_period_game_totals`

**Lifecycle:** Active.

**Purpose:** Reusable server-wide period totals for Activity charts: games, goals, draws, high-scoring games, and clean sheets.

**Source truth:** `ratedresults`.

**Grain:** one row per `(period_type, period_start)`.

**Primary key:** `(period_type, period_start)`.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `period_start` | UTC period start |
| `rated_games` | Rated games in period |
| `total_goals` | `GoalsA + GoalsB` summed |
| `draws` | Games with `ActualScore = 0.5` |
| `double_digit_games` | Games with `GoalsA + GoalsB >= 10` |
| `clean_sheets` | Games where either player scored 0 |

**Full rebuild:** Aggregate `ratedresults` by UTC period.

**Post-game rule:** Upsert day/week/month/year rows and increment each count by the new game's contribution.

**Parity check:** `SUM(rated_games)` for day rows must equal `COUNT(*) FROM ratedresults`.

**Implementation:** `scripts/ladder/sql/server_period_game_totals_rebuild.sql`.

---

### `server_period_matchups`

**Lifecycle:** Active.

**Purpose:** Unique matchup breadth per period.

**Source truth:** `ratedresults`.

**Grain:** one canonical player pair per `(period_type, period_start)`.

**Primary key:** `(period_type, period_start, player_a, player_b)`.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `period_start` | UTC period start |
| `player_a` | Lower player ID in the pair |
| `player_b` | Higher player ID in the pair |
| `games` | Games between this pair in this period |

**Full rebuild:** Group `ratedresults` by UTC period plus `LEAST(idA, idB)` and `GREATEST(idA, idB)`.

**Post-game rule:** Upsert day/week/month/year rows for the canonical player pair.

**Parity check:** `SUM(games)` for day rows must equal `COUNT(*) FROM ratedresults`. Monthly `COUNT(*)` rows should match raw UTC distinct-pair counts.

**Implementation:** `scripts/ladder/sql/server_period_matchups_rebuild.sql`.

---

### `player_monthly_league`

**Lifecycle:** Legacy compatibility; superseded by `player_period_league` with `period_type = 'month'`.

**Purpose:** Older monthly Status league aggregate. Kept temporarily because some fallback code and staging/prod slices may still reference it.

**Source truth:** `ratedresults`.

**Grain:** one row per `(month_start, player_id)`.

**Primary key:** `(month_start, player_id)`.

**Full rebuild:** Same monthly league semantics as `player_period_league` month rows.

**Post-game rule:** Legacy monthly upsert per game while this table is maintained. New work should prefer `player_period_league`.

**Parity check:** `SUM(played) / 2` must equal `COUNT(*) FROM ratedresults`.

**Implementation:** `scripts/ladder/sql/player_monthly_league_rebuild.sql`.

---

### `generalstatstable`

**Lifecycle:** Existing core server-stat table.

**Purpose:** One-row server-wide stats and record pointers used by Hall of Fame (`server2.php`) and status surfaces.

**Source truth:** `ratedresults` (chronological replay) and `playertable` (for aggregate totals).

**Grain:** single row, `id = 1`.

**Schema:** `scripts/ladder/sql/generalstatstable.sql`; broader reset behavior in `docs/replay-v1-scope-and-reset.md`.

**UTC rule:** All record dates stored in this table must reflect UTC game time. The replay connection and post-game writer must pin `SET time_zone = '+00:00'` before reading `ratedresults.Date`.

#### Server aggregate totals

Columns like `GamesPlayed`, `GoalsScored`, `NumberOfDraws`, `DoubleDigits`, `CleanSheets`, and derived ratios (`DoubleDigitsRatio`, `CleanSheetsRatio`, `DrawsRatio`, etc.) are simple sums/counts computed from the full `ratedresults` corpus. These have no tie semantics — they are always the current total.

#### Non-ratio hall-of-fame records

Records such as `MostGamesPlayed`, `MostWins`, `MostGoalsScored`, `MostDoubleDigits`, `MostCleanSheets`, `MostDifferentOpponents`, `MostDifferentVictims`, `MostDoubleDigitsVictims`, `MostCleanSheetsVictims`, `BiggestPeakRating`, `BiggestRatingAscent`, `LongestWinningStreak`, `LongestDrawingStreak`, `LongestNonLossStreak`, `MostGoalsScoredInOneGame`, `BiggestWinDifference`, `BiggestDrawSum`, `BiggestSumOfGoals`.

**Tie policy:** First holder keeps the record until **strictly beaten** (`>`, not `>=`). When a player ties the current record value, the incumbent holder, name, and date remain unchanged.

**Record date:** The UTC `ratedresults.Date` of the game where the record was first set or strictly broken.

**Rebuild:** Chronological replay through all games in `Date ASC, id ASC` order, applying the tie policy at each game. Implementation: `scripts/ladder/server_records.py` (`_try_int_max`, `_try_float_max`, `_try_pair_max` — all use strict `>`).

**Post-game rule:** After a new game, for each applicable record column, compare the new value with the stored record value. Update only if new value **strictly exceeds** the stored value. This is a **behavior change** from the legacy C++ code which uses `>=` (see records exception doc).

**Streak records (special case):** Compare the player’s **career longest** (`LongestWinningStreak` / `LongestNonLossStreak` on `playertable`), not the **current** streak, and use strict `>`. Legacy C++ compares `WinningStreakA >= LongestWinningStreakS`; if prod instead uses `LongestWinningStreakA >= …`, the record holder’s date is rewritten on every later game (GianniT: staging showed Dec 2023 instead of 2020/2022). Detail: `docs/coordination/records-post-game-exception.md` § PG-004c.

#### Ratio/average leaders

Ratio and average leaders (best win ratio, best attack/defense average, best goal ratio, best DD/CS frequency) are **not stored** in `generalstatstable`. They were dropped locally via `schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql` (SCH-003).

Leaders are read live from `playertable` at page render by `site/public_html/includes/records_ratio_leaders.php`. Eligible: `NumberGames >= 30`. Ties: lowest player `ID` wins (implicit MySQL `ORDER BY column, ID ASC LIMIT 1`).

**Post-game note:** The live C++ writer must continue updating ratio columns on `playertable` each game (unchanged). It must **stop writing** ratio leader columns to `generalstatstable` (records exception doc).

#### Victim/culprit network counts (per-player, on `playertable`)

Columns like `CleanSheetsVictims`, `DoubleDigitsVictims`, `MostGoalsConcededVictims` on `playertable` count distinct opponents against whom the player set a specific personal record. These are rebuilt by the chronological replay through network set tracking.

The **server-wide** record pointers (e.g. `MostCleanSheetsVictimsS`) in `generalstatstable` update only when a player's personal network victim count **increases** (new distinct victim added). A later game that does not add a new distinct victim must not touch the server record, even if the player's count still ties the server record.

---

## Post-game derived-data behavior

After one new rated game has been inserted into `ratedresults`, the live writer must update derived truth so it matches a future full rebuild.

The game event supplies:

- game id
- UTC `GameDate` from the inserted `ratedresults.Date`
- `IdenA`, `IdenB`
- `GoalsA`, `GoalsB`
- `ActualScore`
- updated player totals where milestone checks need them

Required updates:

| Derived target | Required update |
|----------------|-----------------|
| `player_period_games` | Increment A and B for day/week/month/year |
| `player_peak_period_games` | Recheck peaks for the touched player-period rows |
| `server_daily_activity` | Increment `rated_games`; increment `active_players` only for players newly active that day |
| `player_period_league` | Upsert A and B W/D/L/GF/GA/GD/points for day/week/month/year |
| `player_milestones` | Insert newly achieved `established_20` and `dd_merchant_10` milestones |
| `player_matchup_summary` | Upsert directed A-to-B and B-to-A rows |
| `server_period_game_totals` | Increment day/week/month/year server totals |
| `server_period_matchups` | Increment canonical pair for day/week/month/year |
| `player_monthly_league` | Legacy monthly update only while legacy table is maintained |
| `generalstatstable` | Update server totals; check non-ratio records with strict `>` tie policy; do NOT write ratio leader columns — see [`records-post-game-exception.md`](coordination/records-post-game-exception.md) |

This section is the behavior authority for prod post-game merges. Steve implements from here + `docs/ratings_cpp.txt` at cutover; no per-table C++ snippet packs in repo.

---

## Global validation checklist

Run after a full rebuild:

```sql
SET time_zone = '+00:00';

SELECT COUNT(*) FROM ratedresults;
SELECT SUM(games) / 2 FROM player_period_games WHERE period_type = 'day';
SELECT SUM(played) / 2 FROM player_period_league WHERE period_type = 'day';
SELECT SUM(rated_games) FROM server_daily_activity;
SELECT SUM(rated_games) FROM server_period_game_totals WHERE period_type = 'day';
SELECT SUM(games) FROM server_period_matchups WHERE period_type = 'day';
```

All six values must match.

Additional checks:

- `player_milestones.established_20` count equals `playertable.NumberGames >= 20`.
- Recent `server_period_matchups` month counts equal raw UTC `COUNT(DISTINCT pair)` by month.
- Key APIs keep their JSON shape while reading stored truth.
