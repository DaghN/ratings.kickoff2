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
| `player_play_streaks` | SCH-014 | `rebuild_player_play_streaks.php` (REP-015) | After period games; day/week streak + HoF if personal best rises |
| `server_daily_activity` | SCH-007 | `server_daily_activity_rebuild.sql` | +1 game/day; +active if first game that day |
| `player_period_league` | SCH-008 | `player_period_league_rebuild.sql` | W/D/L/points per period |
| `league_period` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only** — finalize closed periods |
| `player_league_award` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only** |
| `player_league_totals` | SCH-009 | `league_period_awards_rebuild.sql` (REP-012) | **Periodic only**; re-aggregate from awards |
| `player_league_slice_totals` | SCH-010 | `player_league_slice_totals_rebuild.sql` (REP-013) | **Periodic only**; with career totals after awards |
| `milestone_definitions` | SCH-011 | `scripts/oneoff/load_milestone_definitions.py` | Static catalog; reload when seed changes |
| `player_milestones` | SCH-008, SCH-012–013 | `player_milestones_rebuild.sql` (+ spliced generators) | § `player_milestones` — game / league / lobby; M1–M7 phases |
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

#### Career peak and nadir (`PeakRating`, `LowestRating`)

**Purpose:** Per-player **career** high and low Elo after the player is **established** (same story as `K2_ESTABLISHED_MIN_GAMES` = **20** rated games). Shown on the Peak rating leaderboard wing (`ranked1.php`), profile/feast (tooltips TBD), and anywhere else that reads these columns.

**Not the same as:**

- **`generalstatstable.BiggestPeakRating`** — server Hall of Fame “highest peak rating seen in one game”; separate record logic ([`records-post-game-exception.md`](coordination/records-post-game-exception.md)).
- **`player_peak_period_games`** — best **activity** period (games per day/week/month/year), not Elo.

**Unset sentinels (display “none”):**

| Column | Unset value | Site display |
|--------|-------------|--------------|
| `PeakRating` | `0` (or NULL treated as unset) | `-` on `ranked1.php` |
| `LowestRating` | `5000.00` | `-` on `ranked1.php` |

**Threshold:** `K2_ESTABLISHED_MIN_GAMES` (**20**) — `site/public_html/includes/lb_player_filters.php`. Leaderboard “exclude provisional” uses the same number for **who is listed**; this section defines **when the columns exist and how they are written**.

**Legacy behaviour (prod C++ + replay today — not the target):** Updates can start from game 1. Peak only moves when `NewRating > PeakRating` **and** `NewRating > OldRating`; nadir only when `NewRating < LowestRating` **and** `NewRating < OldRating`. Reference: `docs/ratings_cpp.txt`; replay: `scripts/ladder/player_state.py` (`apply_match`).

**Required behaviour (future post-game + full replay):**

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

### `player_play_streaks`

**Lifecycle:** Active.

**Purpose:** Per-player consecutive **rated play** streaks: UTC calendar days and UTC weeks (Monday–Sunday) with at least one rated game in each period. Powers future profile/HoF surfaces; server records on `generalstatstable` (`LongestDailyPlayStreak*`, `LongestWeeklyPlayStreak*`).

**Source truth:** `player_period_games` (day/week `period_start` lists) + `ratedresults` (establishing game id/date).

**Grain:** one row per `(player_id, streak_type)` where `streak_type` ∈ `day`, `week`.

**Primary key:** `(player_id, streak_type)`.

| Column | Meaning |
|--------|---------|
| `current_streak` | Length of the active run (may be stale if player has not played since the run ended — apply alive rule on read) |
| `current_anchor` | UTC date of last day in the run, or Monday of last week in the run |
| `current_last_game_id` | `ratedresults.id` — **first** game on `current_anchor` period (not updated by later games the same period) |
| `best_streak` | Personal best consecutive periods |
| `best_achieved_at` | `ratedresults.Date` of `best_last_game_id` |
| `best_last_game_id` | **First** rated game on the **last** day/week of the best run |

**Alive rule (read / display):** UTC “today”. **Day:** `current_anchor` is today or yesterday → show `current_streak`, else **0**. **Week:** `current_anchor` is this week’s Monday or last week’s Monday → show `current_streak`, else **0**.

**Full rebuild:** Walk each player’s sorted `player_period_games` day/week `period_start` values; split into consecutive runs (next period = previous + 1 day or + 7 days). Best run = max length; tie on length → earlier `best_achieved_at`. Current run = last run in history (alive applied only at read). Establishing games = `MIN(ratedresults.id)` per `(player, period)`. Then set HoF columns from global best rows.

**Post-game rule (per player, after `player_period_games` upsert):**

1. **Current:** Same period as `current_anchor` → no length change, do not move `current_last_game_id`. Next period → `current_streak + 1`, new anchor, `current_last_game_id` = this game if first on that period. Gap → ended run; if ended length **>** `best_streak`, update personal best from establishing game on previous anchor; start new run at 1.
2. **Personal best:** Update only when `best_streak` **strictly increases** (equal length with later date does not replace).
3. **HoF** (`generalstatstable` id=1): Only if step 2 increased `best_streak`. Beat incumbent when: greater length; or same length and earlier `best_achieved_at`; or same length, same `best_last_game_id`, and this player is `ratedresults.idB` (mutual game).

**Parity check:** Rebuild from `player_period_games` must match incremental simulation on a sample of players; HoF daily/weekly equals top `best_streak` from table with tie order above.

**Implementation:** `site/public_html/includes/player_play_streaks.php`; `scripts/rebuild_player_play_streaks.php`; staging `staging-scripts/run_player_play_streaks_rebuild.php`. Handoff: [`coordination/play-streaks-staging-handoff.md`](coordination/play-streaks-staging-handoff.md).

**UI (read stored truth):** Leaderboards → Streaks [`ranked4.php`](../site/public_html/ranked4.php) — **Days** / **Weeks** (`best_streak` from `player_play_streaks`). Hall of Fame [`server2.php`](../site/public_html/server2.php) — **Most days in a row** / **Most weeks in a row** (`generalstatstable` `LongestDailyPlayStreak*` / `LongestWeeklyPlayStreak*`). **Staging verified** May 2026 (Steve; max day 87, week 126).

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

### `milestone_definitions`

**Lifecycle:** Active (Phase 3).

**Purpose:** Catalog metadata for all curated milestones (display, tier color, short rule). Unlock times live in `player_milestones`.

**Source truth:** [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json) (generated from curated list + probe).

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

**Implementation:** `schema/migrations/010_milestone_definitions.sql` (SCH-011).

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
| Period bursts | 5 keys (`hot_day` … `grind_month`) | `player_period_games` first cross + last game that UTC day/month (`player_milestones_rebuild_period.sql`) |
| Chronological | 16 keys | `gen_milestone_chrono_sql.py` → `player_milestones_rebuild_chrono.sql` (first cross; `peace_streak` in streaks batch) |
| Tail playertable + matchup | 30 keys | `gen_milestone_tail_sql.py` → `player_milestones_rebuild_tail.sql` (first cross; `diversity_merchant` = per-game DD vs 5 opponents) |

**Full rebuild:** `scripts/ladder/sql/player_milestones_rebuild.sql` spliced with exists + streaks + chrono + tail + period SQL — run **after** league awards in `scripts/rebuild_website_derived_data_local.ps1`. Regenerate SQL: `scripts/oneoff/gen_milestone_*.py` (see [`milestones-facilitation.md`](milestones-facilitation.md)). Local parity: **112** distinct `milestone_key` values; `python scripts/oneoff/milestone_v0_sanity_check.py` (UI helpers vs SQL). Per-key catalog: [`milestones-catalog.md`](milestones-catalog.md).

**Schema:** SCH-011 (`milestone_definitions`), SCH-012 + SCH-013 (`player_milestones` + `source_kind` including `lobby`).

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
| Streak / career | 8 keys (`win_hat_trick`, `ten_wins_straight`, `rampage`, `win_streak_30`, `cold_streak`, `win_drought`, `peace_streak`, `ten_wins`) | **Current** streak or career wins at end of this game reaches threshold (not `Longest*` alone) | threshold | `gen_milestone_streak_sql.py` |
| Period burst | 5 keys | After period bucket update: day/month `games` count first reaches 5/10/20/30/50; `achieved_at` = **last game that UTC day/month** (same as rebuild LATERAL) | 5/10/20/30/50 | `player_milestones_rebuild_period.sql` |
| Chronological | 16 keys (`newbie_welcomer`, `generous`, `merchant_streak`, `perfect_day`, …) | Per-game state machine; see below | varies | `gen_milestone_chrono_sql.py` |
| Tail / playertable | 30 keys (`first_victory`, `century_of_wins`, `ten_opponents`, `travelling_salesman`, …) | Per-game counters / network sets; see below | varies | `gen_milestone_tail_sql.py` |

#### Rating club — rebuild / live writer

| Layer | Status (May 2026) |
|-------|-------------------|
| **Prod C++** | Does **not** write `player_milestones` (no `club_*` or other game keys). Milestones on prod UI come from rebuild until M1–M7 live writer ships. |
| **Rebuild** | `player_milestones_rebuild.sql`: first game where running max of `NewRating` ≥ threshold; also `INNER JOIN playertable … PeakRating >= thresh`. |
| **Legacy replay verify** | On `ko2unity_db` after replay: holder counts and first-unlock games match first `NewRating >= threshold` for all four keys; `PeakRating` join excludes **no** players. |
| **When § Career peak and nadir ships** | Remove `PeakRating` join from rebuild SQL. Live post-game must use post-game **`Rating`**, not `PeakRating`, or players with `Rating >= 1700` before game 20 would miss unlocks. |
| **Catalog scope** | Only the four keys in the table above are in the 112-key catalog/rebuild. `club_1900` / `elite_altitude` remain ideas-only. |

Cutover index: [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md).

**Exists feat conditions (player side, `ActualScore` as W/D/L):** Same as `gen_milestone_exists_sql.py` — e.g. `brace` → `goals_for >= 2`; `merchant_trade_fair` → draw 10–10; `massive_upset` → win and `(Rating_opponent - Rating_self) >= 500` pre-game.

**Streak keys:** Use in-memory streak counters maintained during `RatingProcedureUnity` (same semantics as rebuild: win resets loss/draw streaks, etc.). Unlock when **current** streak crosses threshold on this game. `ten_wins` = career win count ≥ 10.

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
| `clean_sheet_spread` | 10th distinct opponent with a clean-sheet win (`goals_against = 0`, `goals_for > 0`) |
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
| `giant_slayer` | **Active #1** rule (below) |
| `daily_habit`, `weekly_regular`, `monthly_regular`, `year_round` | Calendar habit rules — match `gen_milestone_chrono_sql.py` |
| `play_streak_100` | First cross of **100** consecutive UTC days with ≥1 rated game; unlock game = `MIN(id)` on the 100th UTC day of the run — `gen_milestone_play_streak_100_sql.py`; live: `k2_play_streak_maybe_unlock_milestone_100()` after establishing game on that day |
| `year_in_heaven` | First calendar year **Y** with a rated game in all **52** UTC week slots (Monday grid containing 1 Jan — profile Played weeks); unlock game = `MIN(id)` on the week Monday that completes 52/52 — `gen_milestone_year_in_heaven_sql.py`; live: `k2_milestone_maybe_unlock_year_in_heaven()` on first game of a new UTC week, after `player_period_games` week upsert — [`coordination/milestones-year-in-heaven-handoff.md`](coordination/milestones-year-in-heaven-handoff.md) |

**`giant_slayer` (game — active #1):** After this game’s Elo and `LastGame` updates, for each winner:

- **Active player:** `LastGame` within **365 rolling UTC days** before game `Date`, **or** is `idA`/`idB` of this game.
- **Active #1:** highest `Rating` among active players; tie → highest `playertable.ID`.
- **Unlock (first time):** won; opponent is active #1; opponent ≠ self; pre-game `Rating_opponent >= Rating_self`.
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

---

#### Suggested Steve rollout (prod C++)

| Phase | Scope | Unlocks live on new events |
|-------|--------|----------------------------|
| **M1** | `entered_arena` at register; `established_20`, `dd_merchant_10` with full `source_*` | Lobby + 2 headline game keys |
| **M2** | All **exists** feats (18) | Single-game conditions |
| **M3** | Nth-game + **rating club** (`Rating`, not `PeakRating`) + `first_*` / career tail keys driven off updated `playertable` | Volume / firsts |
| **M4** | Streak keys (8) using live streak counters | Streak crosses |
| **M5** | `player_period_games` + period burst (5) | After bucket increment |
| **M6** | League block (20) on finalize | PER-003 |
| **M7** | Remaining chronological + matchup/network keys | Highest complexity |

Staging/local: **full backfill** via rebuild is enough for UI until each phase ships on prod.

---

#### Parity checks

| Check | Expected |
|-------|----------|
| `source_kind` NULL | 0 rows |
| `established_20` | `COUNT(*)` = `playertable` with `NumberGames >= 20` |
| Catalog | N rows in `milestone_definitions` (111 after `play_streak_100`); distinct keys in `player_milestones` ≤ N (may be N−1 if no holder yet) |
| `dd_merchant_10` | Achiever list count = `COUNT(*) FROM player_milestones WHERE milestone_key = 'dd_merchant_10'` |

**Implementation (rebuild):** `scripts/ladder/sql/player_milestones_rebuild.sql` + generated splice files.

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

Leaders are read live from `playertable` at page render by `site/public_html/includes/records_ratio_leaders.php`. Eligible: `NumberGames >= 20` (`K2_ESTABLISHED_MIN_GAMES`). Ties: lowest player `ID` wins (implicit MySQL `ORDER BY column, ID ASC LIMIT 1`).

**Post-game note:** The live C++ writer must continue updating ratio columns on `playertable` each game (unchanged). It must **stop writing** ratio leader columns to `generalstatstable` (records exception doc).

#### Victim/culprit network counts (per-player, on `playertable`)

Two different mechanisms share “victim/culprit” naming on `playertable`. Do not conflate them when writing post-game or replay code.

##### Distinct-opponent counts

`DifferentVictims`, `DifferentCulprits`, `DoubleDigitsVictims`, `CleanSheetsVictims`, and similar columns count **unique opponents** who ever met a condition (beaten, DD’d, shut out, etc.). Replay rebuild: `scripts/ladder/finalize_counts.py` (`finalize_network_counts_from_rows`) walks all `ratedresults` and fills sets — not the personal-record pointer logic below.

The **server-wide** record pointers (e.g. `MostCleanSheetsVictimsS`) in `generalstatstable` update only when a player's distinct victim count **increases** (new opponent added to the set). A later game that does not add a new distinct victim must not touch the server record, even if the player's count still ties the server record.

##### Personal record pointers and record-holder victim/culprit counts

**Scope:** Per-player **single-game extremes** and the **inverse counts** on opponents (Victims & Culprits leaderboard, `ranked5.php`). Examples:

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

**Legacy behaviour (prod C++ today; replay mirrors — not the target policy):**  
Per-game block uses **`>=`** when comparing a new game to the stored extreme (e.g. `GoalDifference >= BiggestLossDifference`). Games run in **`ratedresults` `Date ASC, id ASC`** order. When the condition fires, margin and `*GameID` update; if the credited opponent id changes, decrement the previous opponent's inverse count and increment the new opponent's. On a **tied** margin with a **different** opponent, the later opponent takes the credit (classic 7–0 then 0–7 tie). On a tied margin with the **same** opponent, inverse counts are unchanged but the game id may advance to the later game.

Reference: `docs/ratings_cpp.txt` (per-game block); replay: `scripts/ladder/player_state.py` (`apply_match`, `_transfer_record_count`).

**Required tie policy (future post-game + full replay — deliberate change):**  
Align with non-ratio Hall of Fame records (§ Non-ratio hall-of-fame records): **first holder keeps until strictly beaten** — use **`>`**, not **`>=`**.

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

**Site copy when `>` ships (do not skip):** Behaviour change is not visible from numbers alone — update user-facing text on the Victims & Culprits wing (`ranked5.php`) and anywhere else that explains BL/BW/MGC/MGS-style stats:

- **Footer legend** under `ranked5.php`: removed May 2026; tie rule and abbrev live in column tooltips (`lb_column_help.php`). If a footer returns, do not reintroduce “latest game takes precedence” (**legacy `>=`**).
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
| `player_play_streaks` | Update day/week current + personal best for A and B; HoF columns if personal best rose — see § `player_play_streaks` |
| `server_daily_activity` | Increment `rated_games`; increment `active_players` only for players newly active that day |
| `player_period_league` | Upsert A and B W/D/L/GF/GA/GD/points for day/week/month/year |
| `player_milestones` | Idempotent insert on first cross per [`player_milestones`](#player_milestones) § Post-game write contract — rated game (both players), league finalize (PER-003), register (`entered_arena`). Until prod ships: backfill-only; site reads rebuild. |
| `player_matchup_summary` | Upsert directed A-to-B and B-to-A rows |
| `server_period_game_totals` | Increment day/week/month/year server totals |
| `server_period_matchups` | Increment canonical pair for day/week/month/year |
| `player_monthly_league` | Legacy monthly update only while legacy table is maintained |
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

- `player_milestones`: `COUNT(DISTINCT milestone_key) = 112`; `source_kind IS NULL` count = 0; `established_20` count = `playertable` with `NumberGames >= 20`; `giant_slayer` count = **31** (active #1 rule — see § `giant_slayer`).
- `python scripts/oneoff/milestone_v0_sanity_check.py` — PHP read helpers match SQL (local).
- Recent `server_period_matchups` month counts equal raw UTC `COUNT(DISTINCT pair)` by month.
- Key APIs keep their JSON shape while reading stored truth.
