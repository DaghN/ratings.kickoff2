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

- **Cutover prep vs live:** [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md)
- Schema DDL: [`coordination/schema-register.md`](coordination/schema-register.md)
- Features: [`coordination/feature-log.md`](coordination/feature-log.md)
- Historical batch rebuild log (May 2026): [`archive/replay-register-2026-05.md`](archive/replay-register-2026-05.md)

Those registers link here for behavior; they do **not** duplicate post-game rules.

**One-line cutover rule (agents):** Prep is done on `kooldb1` via ops simul; live prod is Steve’s scheduled cutover; batch `*_rebuild.sql` and `rebuild_website_derived_data_local.ps1` are legacy repair on `ko2unity_db` only — not tasks, not prod.

### Agent policy (post-game)

- **Fill derived tables (happy path):** **`ops/run_ops_sim.php`** after migrate + seed + zero — see [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md). **Not** prod cutover via each `scripts/ladder/sql/archive/batch-2026-05/*_rebuild.sql`.
- **Dev repair only:** [`scripts/rebuild_website_derived_data_local.ps1`](../scripts/rebuild_website_derived_data_local.ps1) — deprecated for cutover; batch SQL chain.
- **Behaviour authority:** This document’s **Post-game rule** sections — implemented in **PHP ops** (`ops/run_process_game.php`, `ops/dispatch.php`).
- **Prod live games (today):** Legacy **C++** still runs until Steve cutover — **do not extend C++**; do not block website/staging work on “C++ pending.”
- **Prod cutover:** Steve inserts ground truth → `dispatch.php CMD=ProcessCompletedGame` (+ `FinalizeUtcDay`) — same rules as here. Guide: [`post-game-php-development.md`](post-game-php-development.md), [`ladder-ops-platform.md`](ladder-ops-platform.md).
- **Historical C++:** [`ratings_cpp.txt`](ratings_cpp.txt) — read-only comparison only.
- **Exception:** Hall of Fame / `generalstatstable` — [`records-post-game-exception.md`](coordination/records-post-game-exception.md).

### Derived data index

**Full history fill (happy path):** `ops/run_ops_sim.php` after migrate + seed + zero. **Batch repair column** = legacy `ko2unity_db` only (`scripts/ladder/sql/archive/batch-2026-05/`).

| Table | Schema | Ops simul / post-game | Batch repair (legacy) | Post-game (contract §) |
|-------|--------|----------------------|------------------------|-------------------------|
| `player_period_games` | SCH-004, SCH-006 | PHP ops P4 | `archive/.../player_period_games_rebuild.sql` | Both players × day/week/month/year +1 |
| `player_peak_period_games` | SCH-006 | PHP ops P4 | `archive/.../player_peak_period_games_rebuild.sql` | After period games; update peak if beaten |
| `player_play_streaks` | SCH-014, SCH-023 | PHP ops P7 | `scripts/rebuild_player_play_streaks.php` | After period games; day/week/month/year streak + HoF when personal best rises |
| `player_result_streaks` | SCH-026 | PHP ops P2 | `scripts/rebuild_player_result_streaks.php` | Per-game match-result runs; boundaries for Streaks LB tooltips / games drill-down |
| `player_activity_participation` | SCH-022, **SCH-025** | PHP ops P4b | `player_activity_participation_rebuild.sql` + `scripts/rebuild_participation_reached.php` | +1 per `is_new_period`; store `active_*_reached_at` on bump; first/last rated day on new UTC day |
| `server_daily_activity` | SCH-007 | PHP ops P5 | `archive/.../server_daily_activity_rebuild.sql` | +1 game/day; +active if first game that day |
| `player_period_league` | SCH-008 | PHP ops P5 | `archive/.../player_period_league_rebuild.sql` | W/D/L/points per period |
| `league_period` | SCH-009 | `FinalizeUtcDay` | `ops/run_finalize_league.php rebuild-all` | **Periodic only** — finalize closed periods |
| `player_league_award` | SCH-009 | `FinalizeUtcDay` | `ops/run_finalize_league.php rebuild-all` | **Periodic only** |
| `player_league_totals` | SCH-009 | `FinalizeUtcDay` | `ops/run_finalize_league.php rebuild-all` | **Periodic only**; re-aggregate from awards |
| `player_league_slice_totals` | SCH-010 | `FinalizeUtcDay` | `ops/run_finalize_league.php rebuild-all` | **Periodic only**; with career totals after awards |
| `milestone_definitions` | SCH-011, **SCH-021** (`holder_count`) | `seed-catalog`; bump on unlock | `load_milestone_definitions.py`; `k2_milestone_holder_counts_rebuild()` | Static catalog + stored holder counts |
| `player_milestones` | SCH-008, SCH-012–013 | PHP ops P6 + `FinalizeUtcDay` | `archive/.../player_milestones_rebuild.sql` (+ splices) | § `player_milestones` — game / league / lobby |
| `player_milestone_totals` | SCH-020 | `k2_milestone_unlock_insert()` bump | `k2_milestone_totals_rebuild()` | § `player_milestone_totals` — per-player tier counts |
| `player_matchup_summary` | SCH-008, **SCH-019** (Jun 2026 ext) | PHP ops P5 | `archive/.../player_matchup_summary_rebuild.sql` (repair; ext TBD) | Directed pair upsert ×2 + goal extremes + DD/CS |
| `server_period_game_totals` | SCH-008 | PHP ops P5 | `archive/.../server_period_game_totals_rebuild.sql` | Server totals ×4 period types |
| `server_period_matchups` | SCH-008 | PHP ops P5 | `archive/.../server_period_matchups_rebuild.sql` | Canonical pair ×4 period types |
| `generalstatstable` | SCH-002–003 | Ladder replay | Ladder replay | **Exception doc** — records tie/UTC/ratio |

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

#### Career peak and nadir (`PeakRating`, `LowestRating`)

**Purpose:** Per-player **career** high and low Elo after the player is **established** (same story as `K2_ESTABLISHED_MIN_GAMES` = **20** rated games). Shown on the Peak rating leaderboard wing (`leaderboards/peak-rating.php`), profile/feast (tooltips TBD), and anywhere else that reads these columns.

**Not the same as:**

- **`generalstatstable.BiggestPeakRating`** — server Hall of Fame “highest peak rating seen in one game”; separate record logic ([`records-post-game-exception.md`](coordination/records-post-game-exception.md)).
- **`player_peak_period_games`** — best **activity** period (games per day/week/month/year), not Elo.

**Unset sentinels (display “none”):**

| Column | Unset value | Site display |
|--------|-------------|--------------|
| `PeakRating` | `0` (or NULL treated as unset) | `-` on `leaderboards/peak-rating.php` |
| `LowestRating` | `5000.00` | `-` on `leaderboards/peak-rating.php` |

**Threshold:** `K2_ESTABLISHED_MIN_GAMES` (**20**) — `site/public_html/includes/lb_player_filters.php`. Leaderboard “exclude provisional” uses the same number for **who is listed**; this section defines **when the columns exist and how they are written**.

**Legacy behaviour (prod C++ today — retiring):** Updates can start from game 1. Peak only moves when `NewRating > PeakRating` **and** `NewRating > OldRating`; nadir only when `NewRating < LowestRating` **and** `NewRating < OldRating`. Reference: `docs/ratings_cpp.txt`.

**Required behaviour (PHP ops post-game + full replay — target):**

1. **Games 1–19:** Leave `PeakRating` and `LowestRating` at **unset** sentinels. Do not update them during the provisional window (provisional rating path may still move `Rating`).
2. **End of the 20th rated game** (`NumberGames` becomes **20** after this game): **Establish** both columns from the player’s **post-game `Rating`** after that game (same value for peak and nadir at birth). Rationale: birth rating (~1600) is not a fair career extreme; after 20 games the current rating is treated as the settled baseline for peak/nadir tracking.
3. **Game 21 onward:** After **every** rated game, update using post-game `Rating` only:
   - `PeakRating` = max(stored peak, new `Rating`)
   - `LowestRating` = min(stored nadir, new `Rating`)
4. **No** per-game “must have gained/lost Elo this game” condition (drop legacy `NewRating > OldRating` / `< OldRating` gates).
5. **`PeakRatingGameID` / `LowestRatingGameID`:** Set to the establishing game (game 20) at establishment; update to the current game whenever the corresponding column changes.

**Establishment edge cases:**

- Run establishment when `NumberGames` **transitions to** 20 on this game (replay must not re-seed on every game with `NumberGames >= 20`).
- If game 20 is a draw with unchanged `Rating`, peak and nadir still initialize to that `Rating` (equal at birth).

**Migration:** No one-off PHP backfill. After the new post-game script ships on prod, run a **full ladder replay** (dry-run post-game on staging first). Replay and live writer must implement this § identically.

**Site (read path):** Until replay, UI may still show legacy-stored values. Profile/feast should treat unset sentinels as “no career peak/nadir yet” once writer matches this §.

**Milestone facilitators (SCH-018, post-game only):** `ScoreStreak`, `MerchantStreak`, `ExactTenGoalStreak`, `WinMarginOneStreak`, `LossMarginOneStreak` — current-run counters for `on_the_scoresheet`, `merchant_streak`, `minimalist_merchant`, `knife_edge`, `unlucky`. Columns are **`NOT NULL` default `0`** (unlike legacy nullable streak columns such as `WinningStreak`). PHP/Python post-game writers always persist **`0`** when the streak is inactive; **zero-derived** resets them to **`0`** via `PLAYERTABLE_ZERO_ON_RESET` (not `NULL`). Updated each rated game in PHP replay/live; not shown on public profile. Rules match `gen_milestone_chrono_sql.py`.

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

These are implemented as bulk `INSERT ... SELECT` SQL scripts run after the event engine replay completes.

### Future direction

The SQL aggregate rebuilds are **correct and fast**, and their UTC timezone pinning was already applied. They remain the production rebuild path for aggregate tables.

However, the event engine is the **behavior authority**: if a SQL rebuild ever disagrees with what a chronological per-game simulation would produce, the event engine definition wins.

If a future aggregate table requires complex stateful logic (e.g. streak-aware records, conditional updates), it should be implemented inside the event engine as an in-memory reducer during replay, with optional SQL bulk-rebuild for speed/parity cross-check.

Current aggregate tables do not need to move into the event engine because they are simple period-bucketed aggregations with no stateful tie-break semantics.

### Normal rebuild pipeline

**Work DB / cutover (authoritative):**

1. `php ops/run_prepare.php migrate-work` (+ `seed-catalog`, `zero-derived` as needed).
2. `php ops/run_ops_sim.php run` then `php ops/run_verify_ops_sim.php`.
3. Sign-off = verify **0 fail** — see [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md).

**Dev DB `ko2unity_db` only (legacy repair):**

1. Schema via `ops/sql/migrations/` if needed.
2. `scripts/run_local_replay.ps1` — Elo + `playertable` + `generalstatstable`.
3. Optional emergency aggregate refill: `scripts/rebuild_website_derived_data_local.ps1` (batch SQL in `scripts/ladder/sql/archive/batch-2026-05/`).

Live/cutover authority is **PHP ops** + this document — not batch SQL on prod.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/player_period_games_rebuild.sql`.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/player_peak_period_games_rebuild.sql`.

---

### `player_play_streaks`

**Lifecycle:** Active.

**Purpose:** Per-player consecutive **rated play** streaks: UTC calendar day / week / month / year with at least one rated game in each period. Server records on `generalstatstable` (`LongestDailyPlayStreak*`, `LongestWeeklyPlayStreak*`, `LongestMonthlyPlayStreak*`, `LongestYearlyPlayStreak*`).

**Source truth:** `player_period_games` (`period_start` lists per type) + `ratedresults` (establishing game id/date).

**Grain:** one row per `(player_id, streak_type)` where `streak_type` ∈ `day`, `week`, `month`, `year`.

**Primary key:** `(player_id, streak_type)`.

| Column | Meaning |
|--------|---------|
| `current_streak` | Length of the active run (may be stale if player has not played since the run ended — apply alive rule on read) |
| `current_anchor` | UTC period anchor: day `Y-m-d`, week Monday, month `Y-m-01`, year `Y-01-01` |
| `current_last_game_id` | `ratedresults.id` — **first** game on `current_anchor` period (not updated by later games the same period) |
| `best_streak` | Personal best consecutive periods |
| `best_anchor_start` | First period anchor of the personal-best run (same anchor rules as `current_anchor`) |
| `best_achieved_at` | `ratedresults.Date` of `best_last_game_id` |
| `best_last_game_id` | **First** rated game on the **last** period of the best run |

**Alive rule (read / display):** UTC “today”. **Day:** today or yesterday. **Week:** this Monday or last Monday. **Month:** this month start or previous month start. **Year:** this year start or previous year start. Else show **0** for current run.

**Full rebuild:** Walk sorted `player_period_games` `period_start` per type; split consecutive runs (`k2_play_streak_next_period`). Set HoF columns from global best rows (all four types).

**Post-game rule (per player, after P4 `is_new_period`):**

0. **Gate:** Run streak logic for a `streak_type` only when P4 reported `is_new_period` for that type (not on every game).
1. **Current / personal best / HoF:** unchanged semantics — see [`activity-wing-stored-truth-policy.md`](activity-wing-stored-truth-policy.md).

**Parity check:** `k2_play_streak_oracle_mismatches()` / `scripts/oneoff/verify_activity_wing_parity_work.php` on work DB; HoF value = `MAX(best_streak)` per type from table.

**Implementation:** `site/public_html/includes/player_play_streaks.php`; ops post-game P7; local repair `scripts/rebuild_player_play_streaks.php`. Historical handoff: [`archive/play-streaks-staging-handoff.md`](archive/play-streaks-staging-handoff.md).

**UI (read stored truth):** Leaderboards → Activity [`leaderboards/activity/`](../site/public_html/leaderboards/activity/peaks.php) — **Peaks** · **Participation** · **In a row**. Streaks wing = match results only. Hall of Fame play-streak rows deferred (GST populated).

---

### `player_result_streaks`

**Lifecycle:** Active (Jun 2026 — schema + rebuild + post-game writer + LB/games UI shipped).

**Purpose:** Per-player **match-result** streak personal bests: consecutive wins, draws, losses, and non-win / non-draw / non-loss runs. Counts remain on `playertable` (`LongestWinningStreak`, …); this table stores **run boundaries** for Leaderboards → Streaks tooltips and player-games drill-down.

**Source truth:** chronological `ratedresults` per player (`Date ASC, id ASC`, `NewRatingA IS NOT NULL`) + `ActualScore` per appearance.

**Grain:** one row per `(player_id, streak_type)` where `streak_type` ∈ `win`, `draw`, `loss`, `non_win`, `non_draw`, `non_loss`.

**Primary key:** `(player_id, streak_type)`.

| Column | Meaning |
|--------|---------|
| `best_streak` | Career maximum run length (must match `playertable.Longest*` for that type after ops simul) |
| `best_start_game_id` / `best_end_game_id` | First / last game in the personal-best run |
| `best_start_at` / `best_end_at` | UTC `ratedresults.Date` for those games (tooltip span) |
| `current_run_start_game_id` | Post-game writer: start game id of the active run (slice 2) |

**Tie policy:** first achievement wins — when two runs tie on length, keep the run with the **earlier** `best_end_at`.

**Full rebuild:** `k2_result_streak_rebuild_all()` — walk each player’s rated games, apply same streak rules as `post_game_player_state.php`, write rows where `best_streak > 0`.

**Post-game rule:** After `playertable` streak counters update each rated game (`k2_result_streak_after_rated_game` in `process_completed_game.php`), upsert boundaries when `best_streak` strictly increases; maintain `current_run_start_game_id` for the active run.

**Parity check:** `k2_result_streak_oracle_mismatches()` — stored vs chronological walker; optional `checkPlayertable=true` after ops simul. Repair: `scripts/rebuild_player_result_streaks.php` (dev / oracle only, not work sign-off).

**Implementation:** `site/public_html/includes/player_result_streaks.php`; migration **SCH-026**; zero-derived truncates table.

**UI (read stored truth):** `leaderboards/streaks.php` — hover personal-best date span (`M j, Y`) + click → `player/games.php?from_game=&to_game=&streak=` (default **newest first**). Streak banner uses same date span. Player games table: date `M j Y, H:i`; GD shows `+` on wins. Falls back to count-only cells when table missing.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/server_daily_activity_rebuild.sql` (batch repair); live = post-game + `player_period_games`.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/player_period_league_rebuild.sql`.

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

**Implementation:** `site/public_html/ops/run_finalize_league.php`; sorter in `includes/league_standings.php`.

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

### `milestone_definitions`

**Lifecycle:** Active (Phase 3).

**Purpose:** Catalog metadata for all curated milestones (display, tier color, short rule). Unlock times live in `player_milestones`.

**Source truth:** [`site/public_html/ops/data/milestones_definitions_seed.json`](../site/public_html/ops/data/milestones_definitions_seed.json) (generated from curated list + probe).

**Grain:** one row per `milestone_key`.

**Primary key:** `milestone_key`.

| Column | Meaning |
|--------|---------|
| `milestone_key` | Stable id (matches `player_milestones.milestone_key`) |
| `display_name` | Garden / profile label |
| `tier_band` | `aspirational` \| `veteran` \| `key` \| `legendary` |
| `chart_token` | `pitch` \| `chrome` \| `amber` \| `holo` |
| `rule_short` | One-line rule for cards |
| `description` | Longer copy (optional; often NULL until copy pass) |
| `sort_order` | Hub section order within tier |
| `icon` | Asset id (TBD) |

**Full rebuild:** `python scripts/oneoff/load_milestone_definitions.py` (truncates and reloads from seed).

**Post-game rule:** None — update seed + reload when catalog changes.

**Implementation:** `site/public_html/ops/sql/migrations/010_milestone_definitions.sql` (SCH-011).

---

### `player_milestones`

**Lifecycle:** Active.

**Purpose:** Reusable player milestone facts for Activity and achievement-style surfaces.

**Source truth:** `ratedresults` (game thresholds), `player_league_award` (league feats). Other families per [`milestones-facilitation.md`](milestones-facilitation.md).

**Grain:** one row per `(player_id, milestone_key)`.

**Primary key:** `(player_id, milestone_key)`.

| Column | Meaning |
|--------|---------|
| `player_id` | `playertable.ID` |
| `milestone_key` | Stable milestone identifier |
| `achieved_at` | UTC instant of first unlock (`ratedresults.Date` or `player_league_award.period_end`) |
| `value` | Threshold or placement snapshot (e.g. 20 games, 3 = podium) |
| `source_kind` | `game`, `league`, or `lobby` — which event type caused the unlock |
| `source_game_id` | `ratedresults.id` when `source_kind = game`; otherwise NULL |
| `source_league_kind` | `points` or `activity` when `source_kind = league` |
| `source_period_type` | `day` / `week` / `month` / `year` when `source_kind = league` |
| `source_period_start` | League period start date when `source_kind = league` |

**Source invariants (rebuild + post-game):**

- `source_kind = game` → `source_game_id` NOT NULL; league columns NULL.
- `source_kind = league` → `source_league_kind`, `source_period_type`, `source_period_start` NOT NULL; `source_game_id` NULL.
- `source_kind = lobby` → only for `entered_arena`: `achieved_at = playertable.JoinDate` (in this product, **registering = entering the lobby**). `source_game_id` and league columns NULL. Not rebuilt by ladder replay; set at account creation (live server).
- UI deep links: game page by `source_game_id`; Status leagues by `source_league_*`; lobby milestone → profile / community copy (no game or league URL). **Link + list context** per key: [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md) (not stored on `player_milestones`).

**Rebuild coverage (May 2026):**

| Family | Keys | Source |
|--------|------|--------|
| Game count / DD | `debut`, `persistence`, `established_20`, `club_500`, `dd_merchant_10` | `ratedresults` Nth appearance (+ `playertable` eligibility); first 10+ goal game for DD |
| League | 16 `league_*` + `moment_of_glory` + `activity_king` + 4 `league_wins_*` | `player_league_award` (requires REP-012 first) |
| Lobby | `entered_arena` | `playertable.JoinDate` (registration = lobby entry) |
| Exists feats | 18 keys (e.g. `brace`, `merchant_trade_fair`) | `ratedresults` first matching game |
| Streaks | 8 keys | Chronological first cross (`gen_milestone_streak_sql.py` → `player_milestones_rebuild_streaks.sql`) |
| Period bursts | 5 keys (`hot_day` … `grind_month`) | `player_period_games` first qualifying UTC day/month; anchor game = rated game where day/month count hits N (`player_milestones_rebuild_period.sql`) |
| Chronological | 16 keys | `gen_milestone_chrono_sql.py` → `player_milestones_rebuild_chrono.sql` (first cross; `peace_streak` in streaks batch) |
| Tail playertable + matchup | 30 keys | `gen_milestone_tail_sql.py` → `player_milestones_rebuild_tail.sql` (first cross; `diversity_merchant` = per-game DD vs 5 opponents) |

**Full rebuild:** `scripts/ladder/sql/archive/batch-2026-05/player_milestones_rebuild.sql` spliced with exists + streaks + chrono + tail + period SQL — run **after** league awards in `scripts/rebuild_website_derived_data_local.ps1`. Regenerate SQL: `scripts/oneoff/gen_milestone_*.py` (see [`milestones-facilitation.md`](milestones-facilitation.md)). Local parity: **112** distinct `milestone_key` values; `python scripts/oneoff/milestone_v0_sanity_check.py` (UI helpers vs SQL). Per-key catalog: [`milestones-catalog.md`](milestones-catalog.md).

**Schema:** SCH-011 (`milestone_definitions`), SCH-012 + SCH-013 (`player_milestones` + `source_kind` including `lobby`).

**Live write path (Jun 2026):** All incremental unlocks on the holy ops path go through **`includes/milestone_unlock.php`** — `k2_milestone_unlock_insert()`. On successful insert, **`k2_milestone_totals_bump()`** maintains **`player_milestone_totals`** (SCH-020) and **`k2_milestone_holder_count_bump()`** maintains **`milestone_definitions.holder_count`** (SCH-021). Detectors (post-game rules, `FinalizeUtcDay`, register) call the librarian; no direct `INSERT INTO player_milestones` elsewhere on live dispatch. Exceptions: prepare lobby seed (`ops_seed_lobby.php` + stored derived rebuild), batch repair SQL under `scripts/ladder/sql/archive/`. Track doc: [`milestones-unlock-librarian.md`](milestones-unlock-librarian.md).

---

### `player_milestone_totals`

**Lifecycle:** Active (Jun 2026).

**Purpose:** O(1) per-player milestone tier counts for meta leaderboard (`leaderboards/milestones.php`) and profile hero glance — avoids live `GROUP BY` over `player_milestones` on every request.

**Grain:** one row per player with ≥1 unlock (players with zero unlocks have no row; reads use `LEFT JOIN` + `COALESCE`).

**Primary key:** `player_id`.

| Column | Meaning |
|--------|---------|
| `total` | Count of unlock rows (keys in `milestone_definitions`) |
| `aspirational` | Tier `aspirational` |
| `dedicated` | Tier `veteran` |
| `accomplished` | Tier `key` |
| `legendary` | Tier `legendary` |

**Live writer:** `k2_milestone_totals_bump()` from `k2_milestone_unlock_insert()` after each new unlock.

**Repair:** `k2_milestone_totals_rebuild()` — truncate + aggregate from `player_milestones` ⋈ `milestone_definitions`. Called after `ops_seed_lobby.php` bulk `entered_arena` seed.

**Schema:** SCH-020 (`020_player_milestone_totals.sql`). Migration includes backfill `INSERT … SELECT`.

**Parity:** `run_verify_ops_sim.php` check `milestone_totals_parity` — per-player totals must match unlock rows.

---

### `milestone_definitions.holder_count` (column)

**Lifecycle:** Active (Jun 2026).

**Purpose:** O(1) per-key holder count for milestones hub catalog (`milestones/catalog.php`) and milestone detail (`milestone.php?key=`) — avoids live `GROUP BY milestone_key` on `player_milestones` per catalog request.

**Grain:** one stored count per catalog row (`milestone_key`).

| Column | Meaning |
|--------|---------|
| `holder_count` | Count of unlock rows per key (includes earners whose account was later deleted) |

**Live writer:** `k2_milestone_holder_count_bump()` from `k2_milestone_unlock_insert()` after each new unlock (+1 per insert, same rule as unlock rows).

**Lobby prepare only:** `k2_milestone_holder_counts_rebuild()` after `ops_seed_lobby.php` bulk seed (bypasses librarian). **Not** after simul; simul ends with incremental state live continues from.

**Schema:** SCH-021 (`021_milestone_definitions_holder_count.sql`). **DDL only** — no backfill in migration; counts from prepare lobby rebuild + simul/live bumps.

**Parity:** `run_verify_ops_sim.php` check `milestone_holder_count_parity` — `holder_count` must equal `COUNT(*)` from `player_milestones` per key (all unlock rows).

**Recent feed:** unchanged — still reads `player_milestones` by `achieved_at`.

---

#### Post-game rule (summary)

**First unlock only** — one row per `(player_id, milestone_key)`. After history is backfilled (REP-008), the live writer **inserts on threshold cross** for new events only. Website v0 is **read-only** until this ships on prod.

| Trigger | When | `source_kind` |
|---------|------|----------------|
| Rated game | After `ratedresults` insert + `playertable` update for both players | `game` |
| League period close | When `player_league_award` row(s) written (PER-003 / finalize) | `league` |
| Account register | When `playertable` row created | `lobby` (`entered_arena` only) |

Steve implements from **`ratings_cpp.txt`** merge + this section. Spec parity: rebuild SQL / `scripts/oneoff/gen_milestone_*.py` (same rules, batch mode).

---

#### Post-game write contract (authoritative)

**Connection:** `SET time_zone = '+00:00'` before reading `ratedresults.Date` or league `period_end`.

**Idempotent insert (required pattern):**

```sql
-- Pseudocode: skip if row exists
IF NOT EXISTS (
  SELECT 1 FROM player_milestones
  WHERE player_id = ? AND milestone_key = ?
) THEN
  INSERT INTO player_milestones (
    player_id, milestone_key, achieved_at, value,
    source_kind, source_game_id,
    source_league_kind, source_period_type, source_period_start
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);
END IF;
```

- **`achieved_at`:** UTC instant of the unlock event (`ratedresults.Date`, `player_league_award.period_end`, `playertable.JoinDate`, or **UTC day-close** for day-complete milestones — see below).
- **`value`:** Threshold snapshot at cross (e.g. `20` for `established_20`, `3` for `brace`) — match rebuild SQL for that key.
- **Never update** `achieved_at` on duplicate events (first cross is permanent).

**Eligibility (game-backed keys):** Player must exist on `playertable`. Rebuild uses `NumberGames >= 1` for most keys; live writer may insert `debut` / `first_*` on the game that brings `NumberGames` from 0→1. Do not insert game milestones for players with no `playertable` row.

**Order after one rated game (both `idA` and `idB`):**

1. Insert `ratedresults` (existing).
2. Update `playertable` totals, streaks, career peak/nadir (§ Career peak and nadir), network counts (existing columns; writer rules per contract).
3. Update derived tables per contract (`player_period_games`, `player_matchup_summary`, …).
4. **Milestone checks** (this section) for each affected player — use **post-update** counters and **this game’s** score/ratings.

Period-burst keys (`hot_day`, `marathon_day`, `absurd_day`, `ultra_day_30`, `grind_month`) run **after** `player_period_games` is incremented for the game’s UTC day/month.

---

#### Game-triggered families (`source_kind = game`)

| Family | Keys (count) | Cross condition (first time) | `value` | Spec / rebuild |
|--------|-------------:|----------------------------|---------|----------------|
| Nth rated appearance | `debut`, `persistence`, `established_20`, `half_century_50`, `centurion_100`, `marathoner_250`, `club_500`, `millennium_merchant_1000`, `club_10000` | `NumberGames` equals N after this game | N | `player_milestones_rebuild.sql` |
| First 10+ goal game | `dd_merchant_10` | First game with `goals_for >= 10` (player side) | 10 | same |
| Rating club | `club_1700`, `club_1800`, `club_2000`, `club_2300` | Post-game **`Rating`** (after Elo update) first `>=` threshold — **independent** of `PeakRating` / established peak-nadir (§ Career peak and nadir). May unlock during games 1–19 as encouragement. | threshold | **Target:** first chronological game where that player’s `NewRating` crosses threshold. **Rebuild today:** same first-cross logic + redundant `PeakRating >= thresh` join on legacy data — see § **Rating club — rebuild / live writer** below. |
| Exists feats | 18 keys (`brace`, `hat_trick`, … `leaky_merchant`) | First game matching condition on **player side** | see generator | `gen_milestone_exists_sql.py` — conditions in file |
| Streak / career | 8 keys (`win_hat_trick`, `ten_wins_straight`, `rampage`, `win_streak_30`, `cold_streak`, `win_drought`, `peace_streak`, `ten_wins`) | **Current** streak or career wins **equals** threshold on this game (crossing game; not a later replay when `Longest*` was higher) | threshold | `milestone_sim.py` (oracle); legacy `gen_milestone_streak_sql.py` |
| Period burst | 5 keys | After period bucket update: when day/month `games` count **equals** 5/10/20/30/50 on the **first** qualifying UTC day/month; `achieved_at` = **that crossing game** (`ratedresults.Date`, `source_game_id` = that game) | 5/10/20/30/50 | `player_milestones_rebuild_period.sql` |
| Chronological | 16 keys (`newbie_welcomer`, `generous`, `merchant_streak`, `perfect_day`, …) | Per-game state machine; see below | varies | `gen_milestone_chrono_sql.py` |
| Tail / playertable | 30 keys (`first_victory`, `century_of_wins`, `ten_opponents`, `travelling_salesman`, …) | Per-game counters / network sets; see below | varies | `gen_milestone_tail_sql.py` |

#### Rating club — rebuild / live writer

| Layer | Status (Jun 2026) |
|-------|-------------------|
| **Prod live (today)** | Legacy C++ does **not** write milestone unlock rows; UI uses batch rebuild data until PHP ops cutover. |
| **PHP ops (repo)** | `ProcessCompletedGame` + P6 milestone modules — cutover target. |
| **Rebuild** | `player_milestones_rebuild.sql`: first game where running max of `NewRating` ≥ threshold; also `INNER JOIN playertable … PeakRating >= thresh`. |
| **Legacy replay verify** | On `ko2unity_db` after replay: holder counts and first-unlock games match first `NewRating >= threshold` for all four keys; `PeakRating` join excludes **no** players. |
| **Live post-game (ops simul)** | **Shipped** — `k2_post_game_milestones_rating_clubs()` uses post-game **`Rating`** cross (games 1–19 allowed). |
| **Batch rebuild (deferred)** | Remove redundant `PeakRating` join from `player_milestones_rebuild.sql` on next regen — DDR-052; not blocking cutover (holy path = ops simul). |
| **Catalog scope** | Only the four keys in the table above are in the 112-key catalog/rebuild. `club_1900` / `elite_altitude` remain ideas-only. |

Cutover index: [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md).

**Exists feat conditions (player side, `ActualScore` as W/D/L):** Same as `gen_milestone_exists_sql.py` — e.g. `brace` → `goals_for >= 2`; `merchant_trade_fair` → draw 10–10; `massive_upset` → win and `(Rating_opponent - Rating_self) >= 500` pre-game.

**Streak keys:** In-memory counters during post-game (win resets loss/draw streaks, etc.). Unlock when **current** streak **equals** threshold on this game (`win_hat_trick` only on a win, `cold_streak` on a loss, `peace_streak` on a draw, `win_drought` when `non_win_streak === 10`). `ten_wins` = 10th career win on this game.

**Exceptions (not in `ProcessCompletedGame`):** `perfect_day` / `nightmare_day` — first UTC day with ≥5 rated games and all W / all L; `achieved_at` = next UTC midnight. **Live/post-game PHP:** `FinalizeUtcDay` — `site/public_html/ops/includes/day_close_milestones.php` (ops simul + Steve midnight dispatch). Not per-game post-game. Historical surgical SQL on frozen `kooldb`: `scripts/ladder/sql/archive/one-off-2026-06/player_milestones_fix_day_close.sql` (audit only).

**Tail highlights (post-update `playertable` or per-game):**

| Key pattern | Cross when |
|-------------|------------|
| `first_victory`, `first_goal`, `first_handshake`, `welcome_to_the_ladder`, `first_shutout` | First win / first scoring game / first draw / first loss / first clean sheet (`goals_against = 0`) |
| `century_of_wins`, `battle_scarred`, `ten_draws` | 100th win, 100th loss, 10th draw |
| `hundred_goals`, `thousand_goal_club` | Cumulative `GoalsFor` crosses 100 / 1000 on this game |
| `fortress_builder`, `clean_sheet_artist` | 25th / 50th clean sheet (`goals_against = 0` on a game) |
| `ten_opponents` … `century_of_rivals` | `DifferentOpponents` crosses 10 / 25 / 50 / 100 |
| `five_victims`, `twenty_five_victims`, `ten_culprits` | `DifferentVictims` / `DifferentCulprits` cross thresholds (victim = opponent beaten; culprit = opponent who beat you — same as `finalize_network_counts`) |
| `diversity_merchant` | 5th **distinct** opponent in a game where player scored 10+ (per-game DD; same family as travelling salesman) |
| `travelling_salesman` | 10th **distinct** opponent in a game where player scored 10+ |
| `clean_sheet_spread` | 10th distinct opponent in a game where you kept a clean sheet (`goals_against = 0`; includes 0–0 draws) |
| `ten_match_saga`, `lifetime_rivalry` | 10th / 50th rated game vs same opponent (per directed pair count) |
| `regular_customer`, `bogeyman` | 10th / 20th win vs same opponent |

**Chronological highlights:**

| Key | Rule (first cross) |
|-----|-------------------|
| `newbie_welcomer` | Opponent was in someone’s **debut** rated game |
| `generous` | Opponent conceded 2+ in someone’s debut game |
| `perfect_day` / `nightmare_day` | End of UTC day: ≥5 games that day, all W / all L. **`achieved_at`** = `00:00:00` UTC on the **calendar day after** the qualifying day (day-close / end-of-day job). **`source_game_id`** = last rated game that qualifying day (evidence anchor only). Garden link: [`milestones-catalog.md`](milestones-catalog.md) · `player_day_games`. |
| `merchant_streak` / `minimalist_merchant` | 5 consecutive games with 10+ goals / 3 consecutive exact 10-goal games |
| `peace_streak` / `united_nations` | 3 / 5 consecutive draws |
| `knife_edge` / `unlucky` | 5 consecutive 1-goal margin wins / losses |
| `on_the_scoresheet` | 10 consecutive games with at least one goal scored |
| `rare_blank` | First game with **0 goals scored** once the player already has **50+** career games (`rule_short`: “after 50+ career games” = in a **later** game, not on the game that completes the 50th). **Live / sim:** `NumberGames >= 51` and `goals_for = 0` on that game; dedupe via chrono `done` (only the first blank after the threshold). Aligns with `rule_short` — not “51+” in copy. |
| `giant_slayer` | **Kickoff active #1** rule (below) |
| `daily_habit`, `weekly_regular`, `monthly_regular`, `year_round` | Calendar habit rules — match `gen_milestone_chrono_sql.py` |
| `play_streak_100` | First cross of **100** consecutive UTC days with ≥1 rated game; unlock on the **game that extends** the day streak to 100 — `k2_play_streak_maybe_unlock_milestone_100()` / `simulate_play_streak_100_milestones()`; batch SQL may still use establishing-game backfill |
| `year_in_heaven` | First calendar year **Y** with a rated game in all **52** UTC week slots (Monday grid containing 1 Jan — profile Played weeks); **live:** unlock on the rated game that fills the 52nd slot (`achieved_at` = that game's `Date`, `source_game_id` = that game) when week `games` = 1 after upsert; batch rebuild SQL may use establishing-game lookup — [`coordination/milestones-year-in-heaven-handoff.md`](coordination/milestones-year-in-heaven-handoff.md) |

**`giant_slayer` (game — kickoff active #1):** For each winner, facts are at **game start** (before this game’s Elo is written to `playertable`):

- **Active player:** `LastGame` within **365 rolling UTC days** before game `Date`, **or** is `idA`/`idB` of this game (same as at kickoff; match players count as active).
- **Kickoff active #1:** highest `playertable.Rating` among active players **before** this game’s `playertable` update; tie → highest `playertable.ID`. Post-game ratings must not be used (beating #1 can demote them).
- **Unlock (first time):** won; opponent is kickoff active #1; opponent ≠ self; pre-game `Rating_opponent >= Rating_self` (`ratedresults.RatingA` / `RatingB`).
- **Live / sim:** `k2_post_game_milestones_apply_giant_slayer_at_kickoff()` runs after `ratedresults` write, **before** `k2_post_game_player_write()` for `idA`/`idB`.
- Insert: `source_kind = game`, `source_game_id = ratedresults.id`, `achieved_at = Date`, `value = 1`.

Rebuild: `gen_milestone_chrono_sql.py`; surgical `player_milestones_rebuild_giant_slayer.sql`. Probe: `scripts/oneoff/milestone_giant_slayer.py`.

**`newbie_welcomer` / `generous`:** Award the **opponent** (`idB` when debut is `idA`, etc.), not the debutant.

---

#### League-triggered families (`source_kind = league`)

Run when **`player_league_award`** rows are written for a closed period (same job as `player_league_totals` — PER-003). For each new award row, check **first time** this player hits that slice or career count.

| Key pattern | Cross when | `achieved_at` | League columns |
|-------------|------------|---------------|----------------|
| `league_*_medal` (8) | First top-3 (`finish_rank <= 3`) in that `league_kind` × `period_type` | `period_end` | From award row |
| `league_*_winner` (8) | First `is_winner = 1` in that slice | `period_end` | From award row |
| `moment_of_glory` | First daily **points** win | `period_end` | points + day |
| `activity_king` | First monthly **activity** win | `period_end` | activity + month |
| `league_wins_10/50/100/500` | Nth career win (`is_winner = 1`, any of 8 leagues) | `period_end` of Nth win | From that award row |

`source_game_id` = NULL. Copy `league_kind`, `period_type`, `period_start` from the triggering `player_league_award` row.

League rules: [`leagues-rules-spec.md`](leagues-rules-spec.md). Rebuild: league block in `player_milestones_rebuild.sql` (after REP-012).

---

#### Lobby (`source_kind = lobby`)

| Key | When | `achieved_at` |
|-----|------|---------------|
| `entered_arena` | `playertable` row created (registration) | `playertable.JoinDate` |

**Not** `LobbyTime`. **Not** on first rated game (`debut` is separate). No `source_game_id` / league columns.

**Live writer:** `k2_milestone_maybe_unlock_entered_arena()` in `includes/player_milestone_entered_arena.php` — called from ops `ProcessPlayerRegistered` when Steve registers a player (dispatcher `player_id` only). **Not** `ProcessCompletedGame` or `replay-to`. Full-history backfill: milestone rebuild SQL / prepare, not post-game replay tail.

---

#### PHP ops coverage (cutover reference)

Implemented in repo (`ops/run_process_game.php` / `FinalizeUtcDay` where noted). Prod receives these via `dispatch.php` at cutover — **not** via new C++ phases.

| Area | Scope | Live trigger |
|------|--------|--------------|
| Register | `entered_arena` | `ProcessPlayerRegistered` |
| Game keys | exists feats, streaks, tail, rating club, chrono (most) | `ProcessCompletedGame` |
| Period burst | 5 keys | After bucket update on crossing game |
| League block | ~20 keys | `FinalizeUtcDay` / league finalize |
| Day-close | `perfect_day`, `nightmare_day` | `FinalizeUtcDay` |

Staging/local: **full backfill** via rebuild + ops simul is enough for UI until prod cutover.

---

#### Parity checks

| Check | Expected |
|-------|----------|
| `source_kind` NULL | 0 rows |
| `established_20` | `COUNT(*)` = `playertable` with `NumberGames >= 20` |
| Catalog | N rows in `milestone_definitions` (111 after `play_streak_100`); distinct keys in `player_milestones` ≤ N (may be N−1 if no holder yet) |
| `dd_merchant_10` | Achiever list count = `COUNT(*) FROM player_milestones WHERE milestone_key = 'dd_merchant_10'` |

**Implementation (rebuild):** `scripts/ladder/sql/archive/batch-2026-05/player_milestones_rebuild.sql` + generated splice files.

---

### `player_matchup_summary`

**Lifecycle:** Active. **Jun 2026 extension (SCH-019):** goal extremes + per-pair DD/CS — contract signed; DDL + P5 + UI in [`player-opponents-hub.md`](player-opponents-hub.md) Phase 3 slice B.

**Purpose:** Directed player-vs-opponent aggregate totals for Opponents wing tables (W/D/L, Goals, DDs), profile top-opponents API, and milestone matchup probes (`games` / `wins` only today).

**Source truth:** `ratedresults` — `GoalsA`, `GoalsB`, `ActualScore`, and per-game flags `DDPlayerA`, `DDPlayerB`, `CSPlayerA`, `CSPlayerB` (written by post-game before P5 runs).

**Grain:** one directed row per `(player_id, opponent_id)`.

**Primary key:** `(player_id, opponent_id)`.

**Schema:** base table in migration **007** (`SCH-008`); extension columns in migration **019** (`SCH-019`, planned filename `019_player_matchup_summary_opponents_ext.sql`).

#### Core columns (shipped SCH-008)

| Column | Type | Meaning |
|--------|------|---------|
| `player_id` | `int` | Subject player |
| `opponent_id` | `int` | Opponent |
| `games` | `smallint unsigned` | Games against this opponent |
| `wins` | `smallint unsigned` | Wins by subject player |
| `draws` | `smallint unsigned` | Draws |
| `losses` | `smallint unsigned` | Losses by subject player |
| `goals_for` | `smallint unsigned` | Subject goals (sum) |
| `goals_against` | `smallint unsigned` | Opponent goals (sum) |

#### Extension columns (SCH-019 — Opponents Goals tail + DDs tab)

| Column | Type | Meaning |
|--------|------|---------|
| `max_goals_for` | `smallint unsigned` | Highest goals scored by subject in one game vs this opponent |
| `max_goals_against` | `smallint unsigned` | Highest goals conceded by subject in one game |
| `min_goals_for` | `smallint unsigned` | Lowest goals scored by subject in one game |
| `min_goals_against` | `smallint unsigned` | Lowest goals conceded by subject in one game |
| `max_win_margin` | `smallint unsigned NULL` | Largest winning margin (`goals_for − goals_against`) in a win; **NULL** until the subject has at least one win vs this opponent |
| `max_loss_margin` | `smallint unsigned NULL` | Largest losing margin (`goals_against − goals_for`) in a loss; **NULL** until at least one loss |
| `max_draw_goals` | `smallint unsigned NULL` | Goals per side in the highest-scoring draw vs this opponent; **NULL** until at least one draw (a stored `0` after draws exist means a 0-0 draw was the highest-scoring draw) |
| `max_goal_sum` | `smallint unsigned` | Highest `goals_for + goals_against` in one game |
| `min_goal_sum` | `smallint unsigned` | Lowest `goals_for + goals_against` in one game |
| `double_digits` | `smallint unsigned` | Games where **subject** scored ≥10 goals (`DDPlayer` for subject — per-player threshold, not combined score) |
| `double_digits_conceded` | `smallint unsigned` | Games where **opponent** scored ≥10 goals against subject |
| `clean_sheets` | `smallint unsigned` | Games where subject conceded 0 (`CSPlayer` for subject) |
| `clean_sheets_conceded` | `smallint unsigned` | Games where subject scored 0 (opponent clean sheet against subject) |

**Nullable subset maxima (`max_win_margin`, `max_loss_margin`, `max_draw_goals`):** one rule — **NULL** means no qualifying game yet for that statistic (same as live `MAX(CASE … ELSE NULL END)`). Do **not** use `0` as a sentinel for “never happened”; `0` is only valid once the subset exists (e.g. 0-0 draw). Additive counters (`double_digits`, …) stay NOT NULL default `0`.

**UI mapping:** Opponents → Goals tail (Max GF/GA, Max win/loss, Max/Min sum, Draw) and Opponents → DDs (DD, CS, conceded variants; ratios computed at read time as `count / games`). Max win / max loss / Draw cells show **—** when the corresponding column is **NULL** (or when `wins` / `losses` / `draws` is 0). Reference live SQL: `site/public_html/includes/player_opponents_tables.php` (`player_opponents_render_goals_table_live`, `player_opponents_render_dds_table`).

**Consumers unchanged by extension:** `api/player_top_opponents.php` (core W/D/L + goals only); `k2_post_game_milestone_matchup_counts()` (`games`, `wins` only).

#### Per-game inputs (directed row: subject P vs opponent O)

From one processed `ratedresults` row, derive **subject** goals and flags:

| Subject is | `goals_for` | `goals_against` | `double_digits` | `double_digits_conceded` | `clean_sheets` | `clean_sheets_conceded` |
|------------|-------------|-----------------|-----------------|--------------------------|----------------|-------------------------|
| Player A | `GoalsA` | `GoalsB` | `DDPlayerA` | `DDPlayerB` | `CSPlayerA` | `CSPlayerB` |
| Player B | `GoalsB` | `GoalsA` | `DDPlayerB` | `DDPlayerA` | `CSPlayerB` | `CSPlayerA` |

Outcome for subject: `w` / `d` / `l` from `ActualScore` (same rules as existing P5 matchup upsert). Flag semantics match `k2_post_game_outcome_from_goals()` (`ops/includes/post_game_outcome.php`): DD = that player's goals ≥10; CS = opponent goals = 0.

Let `gf`, `ga` = subject goals for/against; `gs = gf + ga`; `win_margin = gf − ga` when `w > 0`; `loss_margin = ga − gf` when `l > 0`.

#### Full rebuild

Union both player perspectives from **processed** `ratedresults` (derived flags present), then `GROUP BY` directed `(player_id, opponent_id)`:

- **Sums:** `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `double_digits`, `double_digits_conceded`, `clean_sheets`, `clean_sheets_conceded` — same as core rebuild plus `SUM` of directed DD/CS flags.
- **Extremes:** `MAX`/`MIN` on directed `gf`/`ga`; `MAX(CASE WHEN win THEN win_margin END)` / `MAX(CASE WHEN loss THEN loss_margin END)`; `MAX(CASE WHEN draw THEN gf END)`; `MAX(gs)` / `MIN(gs)`.

**Repair SQL (legacy, not cutover authority):** extend `scripts/ladder/sql/archive/batch-2026-05/player_matchup_summary_rebuild.sql` when batch parity is needed — after SCH-019 lands.

#### Post-game rule (P5 — `k2_post_game_upsert_matchup_summary`)

After each rated game, upsert **two** directed rows (A→B and B→A). Existing additive counters unchanged:

`games += 1`, `wins`/`draws`/`losses +=`, `goals_for`/`goals_against +=`.

**Extension on INSERT** (first game in pair): set mins and maxes to this game's `gf`/`ga`/`gs`; set `max_win_margin` / `max_loss_margin` / `max_draw_goals` only when that outcome applies (else leave **NULL**); DD/CS columns = this game's 0/1 flags.

**Extension on DUPLICATE KEY UPDATE:**

| Column | Update |
|--------|--------|
| `max_goals_for` | `GREATEST(max_goals_for, gf)` |
| `max_goals_against` | `GREATEST(max_goals_against, ga)` |
| `min_goals_for` | `LEAST(min_goals_for, gf)` |
| `min_goals_against` | `LEAST(min_goals_against, ga)` |
| `max_win_margin` | If win: `GREATEST(COALESCE(max_win_margin, 0), win_margin)`; else unchanged |
| `max_loss_margin` | If loss: `GREATEST(COALESCE(max_loss_margin, 0), loss_margin)`; else unchanged |
| `max_draw_goals` | If draw: `GREATEST(COALESCE(max_draw_goals, gf), gf)`; else unchanged |
| `max_goal_sum` | `GREATEST(max_goal_sum, gs)` |
| `min_goal_sum` | `LEAST(min_goal_sum, gs)` |
| `double_digits` | `+=` subject DD flag (0 or 1) |
| `double_digits_conceded` | `+=` opponent DD flag |
| `clean_sheets` | `+=` subject CS flag |
| `clean_sheets_conceded` | `+=` subject scored-zero flag |

**History fill:** `ops/run_ops_sim.php` after `zero-derived` — not live-only incremental writers for past games.

#### Parity checks

| Check | Expected |
|-------|----------|
| Game count | `SUM(games)` = `COUNT(*) FROM ratedresults` × 2 (unchanged) |
| Directed DD sum | For each `player_id`, `SUM(double_digits)` = count of processed games where that player had `DDPlayer* = 1` |
| Directed CS sum | For each `player_id`, `SUM(clean_sheets)` = count of processed games where that player had `CSPlayer* = 1` |
| Pair extremes (spot) | For sample `(player_id, opponent_id)`, `max_goals_for` / DD columns match live aggregation in `player_opponents_tables.php` over games in simul window |

Extend `scripts/work_prepare/ab_period_aggregates.py` P5 parity keys to include extension columns when SCH-019 ships.

**Implementation:** P5 — `ops/includes/post_game_period_aggregates.php`; DDL — `ops/sql/migrations/019_player_matchup_summary_opponents_ext.sql`; proof — [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md) on `ko2unity_work`, then Steve on `staging-work` / `kooldb1`.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/server_period_game_totals_rebuild.sql`.

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

**Implementation:** `scripts/ladder/sql/archive/batch-2026-05/server_period_matchups_rebuild.sql`.

---

### `generalstatstable`

**Lifecycle:** Existing core server-stat table.

**Purpose:** One-row server-wide stats and record pointers used by Hall of Fame (`hall-of-fame.php`) and status surfaces.

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

Ratio and average leaders (best win ratio, best attack/defense average, best goal ratio, best DD/CS frequency) are **not stored** in `generalstatstable`. They were dropped locally via `site/public_html/ops/sql/migrations/002_generalstatstable_drop_ratio_leader_columns.sql` (SCH-003).

Leaders are read live from `playertable` at page render by `site/public_html/includes/records_ratio_leaders.php`. Eligible: `NumberGames >= 20` (`K2_ESTABLISHED_MIN_GAMES`). Ties: lowest player `ID` wins (implicit MySQL `ORDER BY column, ID ASC LIMIT 1`).

**Post-game note:** The live C++ writer must continue updating ratio columns on `playertable` each game (unchanged). It must **stop writing** ratio leader columns to `generalstatstable` (records exception doc).

#### Victim/culprit network counts (per-player, on `playertable`)

Two different mechanisms share “victim/culprit” naming on `playertable`. Do not conflate them when writing post-game or replay code.

##### Distinct-opponent counts

`DifferentVictims`, `DifferentCulprits`, `DoubleDigitsVictims`, `CleanSheetsVictims`, and similar columns count **unique opponents** who ever met a condition (beaten, DD’d, shut out, etc.). Replay rebuild: `scripts/ladder/finalize_counts.py` (`finalize_network_counts_from_rows`) walks all `ratedresults` and fills sets — not the personal-record pointer logic below.

The **server-wide** record pointers (e.g. `MostCleanSheetsVictimsS`) in `generalstatstable` update only when a player's distinct victim count **increases** (new opponent added to the set). A later game that does not add a new distinct victim must not touch the server record, even if the player's count still ties the server record.

##### Personal record pointers and record-holder victim/culprit counts

**Scope:** Per-player **single-game extremes** and the **inverse counts** on opponents (Victims & Culprits leaderboard, `leaderboards/victims.php`). Examples:

| Stored on player | Meaning |
|------------------|---------|
| `BiggestLossDifference`, `BiggestLossCulpritID`, `BiggestLossGameID` | Loser's heaviest defeat: margin and which opponent is credited |
| `BiggestWinDifference`, `BiggestWinVictimID`, `BiggestWinGameID` | Winner's largest win margin and which opponent was beaten for that record |
| `MostGoalsConceded`, `MostGoalsConcededCulpritID`, `MostGoalsConcededGameID` | Most goals conceded in one game + opponent credited |
| `MostGoalsScored`, `MostGoalsScoredVictimID`, `MostGoalsScoredGameID` | Most goals scored in one game + opponent beaten |

**Inverse counts on the opponent's row:**

- `BiggestLossVictims` on **George** = players whose **current** `BiggestLossCulpritID` is George.
- `BiggestWinCulprits` on **Joe** = players whose **current** `BiggestWinVictimID` is Joe.

Counts move when the **credited opponent** for that personal record changes, not on every game between the same pair.

**Legacy behaviour (prod C++ today — retiring):**  
Per-game block uses **`>=`** when comparing a new game to the stored extreme. On a **tied** margin with a **different** opponent, the later opponent takes the credit. Reference: `docs/ratings_cpp.txt`.

**Required tie policy (PHP ops post-game + Python oracle — target):**  
Align with non-ratio Hall of Fame records (§ Non-ratio hall-of-fame records): **first holder keeps until strictly beaten** — use **`>`**, not **`>=`**. Implemented in `post_game_player_state.php` / `player_state.py` (Jun 2026).

When a new game **equals** the stored personal extreme: do **not** change the margin/goals, `*CulpritID` / `*VictimID`, `*GameID`, or any inverse count (`BiggestLossVictims`, `BiggestWinCulprits`, `MostGoalsConcededVictims`, `MostGoalsScoredCulprits`, etc.).

When a new game **strictly exceeds** the stored extreme: update margin/goals and `*GameID`; if the credited opponent changes, apply the same −1 / +1 transfer on the two opponents' inverse counts as today.

**Post-game checklist (must not be overlooked):** Change **`>=` to `>`** on every personal-extreme comparison in the per-game block, including at least:

- `BiggestWinDifference` / `BiggestWinVictimID` / `BiggestWinCulprits`
- `BiggestLossDifference` / `BiggestLossCulpritID` / `BiggestLossVictims`
- `MostGoalsConceded` / `MostGoalsConcededCulpritID` / `MostGoalsConcededVictims`
- `MostGoalsScored` / `MostGoalsScoredVictimID` / `MostGoalsScoredCulprits`
- `LeastGoalsScored`, `LeastGoalsConceded`, and other min/max single-game fields that use the same culprit/victim transfer pattern

**Not covered by HoF-only handoff:** [`records-post-game-exception.md`](coordination/records-post-game-exception.md) addresses `generalstatstable` `>=` → `>` only. Playertable personal pointers and inverse victim/culprit columns are a **separate mandatory** item in any new post-game script.

**Rebuild parity:** Full replay must use the same **`>`** rules so `playertable` matches prod. Golden checks should include at least one **tied margin, two opponents** case for `BiggestLossVictims` / `BiggestWinCulprits`.

**Site copy when `>` ships (do not skip):** Behaviour change is not visible from numbers alone — update user-facing text on the Victims & Culprits wing (`leaderboards/victims.php`) and anywhere else that explains BL/BW/MGC/MGS-style stats:

- **Footer legend** under `leaderboards/victims.php`: removed May 2026; tie rule and abbrev live in column tooltips (`lb_column_help.php`). If a footer returns, do not reintroduce “latest game takes precedence” (**legacy `>=`**).
- **Column tooltips** (`data-k2-help` on `th`): align with `>` — e.g. credited opponent stays on a tie; inverse counts move only when margin is **strictly** beaten and credit shifts.
- **HoF / profile cross-links** only if their help text repeats the old tie story.

Until post-game and replay use `>`, tooltips may still describe legacy behaviour; flag them in the same PR as the C++ / replay change.

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
| `player_activity_participation` | On each `is_new_period`, increment `active_{type}` and set `active_{type}_reached_at` / `reached_game_id` from current game (SCH-025); on new UTC day update `first_rated_day` / `last_rated_day` — see [`activity-wing-stored-truth-policy.md`](activity-wing-stored-truth-policy.md) |
| `player_play_streaks` | Update day/week/month/year when P4 `is_new_period`; HoF columns if personal best rose — see § `player_play_streaks` |
| `player_result_streaks` | Every rated game after playertable write — see § `player_result_streaks` |
| `server_daily_activity` | Increment `rated_games`; increment `active_players` only for players newly active that day |
| `player_period_league` | Upsert A and B W/D/L/GF/GA/GD/points for day/week/month/year |
| `player_milestones` | Idempotent insert on first cross per [`player_milestones`](#player_milestones) § Post-game write contract — **rated game** (both players) from `ProcessCompletedGame` only; **not** `perfect_day` / `nightmare_day` (`FinalizeUtcDay`); **`entered_arena`** = prepare lobby seed + live register, not replay. League keys: `FinalizeUtcDay` / PER-003. Simul runbook: [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md). |
| `player_matchup_summary` | Upsert directed A-to-B and B-to-A rows (core + SCH-019 extremes / DD / CS) |
| `server_period_game_totals` | Increment day/week/month/year server totals |
| `server_period_matchups` | Increment canonical pair for day/week/month/year |
| `generalstatstable` | Update server totals; check non-ratio records with strict `>` tie policy; do NOT write ratio leader columns — see [`records-post-game-exception.md`](coordination/records-post-game-exception.md) |
| `playertable` career peak and nadir | `PeakRating`, `LowestRating`, `PeakRatingGameID`, `LowestRatingGameID` — see § **Career peak and nadir** (establish at 20 games from post-game `Rating`; then max/min every game; no gain/loss gate). |
| `playertable` personal extremes + inverse victim/culprit counts | Apply **`>`** on single-game max/min comparisons; update `*CulpritID` / `*VictimID` and inverse counts **only on strict improvement** — see § Personal record pointers. **Not** included in HoF-only C++ edits. |

This section is the behavior authority for prod post-game merges. Steve implements from here + `docs/ratings_cpp.txt` at cutover; no per-table C++ snippet packs in repo. One-page cutover index: [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md).

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

- `player_milestones`: `COUNT(DISTINCT milestone_key) = 112`; `source_kind IS NULL` count = 0; `established_20` count = `playertable` with `NumberGames >= 20`; `giant_slayer` count = **33** on current import (kickoff active #1 — see § `giant_slayer`; was 31 under post-game #1).
- `python scripts/oneoff/milestone_v0_sanity_check.py` — PHP read helpers match SQL (local).
- Recent `server_period_matchups` month counts equal raw UTC `COUNT(DISTINCT pair)` by month.
- Key APIs keep their JSON shape while reading stored truth.
