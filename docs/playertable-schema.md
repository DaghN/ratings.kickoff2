# `playertable` schema snapshot

Reference captured from **staging** via `scripts/throwaway_playertable_schema.php` (May 2026).  
**Treat as a snapshot:** production schema may drift; re-run the script if columns change.

**May 2026 (dev/staging):** nine unused **`KungFu*`** columns were dropped from `playertable` (`KungFuLevel`, `KungFuWinBank`, `KungFuLoseBank`, `KungFuLastGameID`, `KungFuLastGameDate`, `KungFuNumberOfGames`, `KungFuPeakLevel`, `KungFuPeakLevelDate`, `KungFuDisplay`). They are **not** listed below. Production may still have them until the same migration is applied there.

**Engine / charset (from `SHOW CREATE TABLE`):** `MyISAM`, `utf8mb4_general_ci`.

---

## Counts (same snapshot)

| Metric | Value |
|--------|------:|
| Total rows | 475 |
| `Display = 1` | 259 |
| `Display` NULL / empty bucket | 216 |
| `PlayerRank = 9999` (default sentinel) | 449 |
| `PlayerRank` in **1 … 26** (one row each) | 26 |

So **`PlayerRank`** defaults to **`9999`** (`NOT NULL DEFAULT 9999`): almost everyone stays “unranked” in that legacy column; the old **`PlayerRank <> 9999`** site filter exposed only those **26** ladder-ranked rows. **`Display`** splits who is allowed on public ladder-style listings (`Display = 1`).

---

## Naming note for PHP

MySQL column names use **PascalCase** (e.g. `ID`, `Display`, `PlayerRank`). Some legacy PHP uses lowercase keys depending on fetch mode; `player/profile.php` uses `$row['ID']`, `$row['Name']`, etc. Keep case consistent when writing new queries.

---

## Columns (full list)

Types and nullability from **`SHOW FULL COLUMNS`**.

| Field | Type | Null | Key | Default |
|-------|------|------|-----|---------|
| ID | int(11) | NO | PRI | auto_increment |
| Name | varchar(16) | YES | | |
| Email | varchar(256) | YES | | |
| CryptPassword | int(11) | YES | | |
| LegalAccepted | tinyint(4) | YES | | 0 |
| JoinDate | timestamp | NO | | current_timestamp() |
| LastLogin | datetime | NO | | current_timestamp() |
| LastGame | datetime | NO | | current_timestamp() |
| LastActive | timestamp | NO | | current_timestamp() |
| Pref_Formation | int(11) | NO | | 0 |
| Pref_AutoSlides | tinyint(1) | NO | | 0 |
| Pref_PBD | tinyint(1) | NO | | 0 |
| Pref_TrapFix | tinyint(1) | NO | | 0 |
| Country | varchar(4) | NO | | |
| Language | tinytext | YES | | |
| Display | mediumint(9) | YES | | |
| NumberGames | mediumint(9) | YES | | |
| NumberWins | mediumint(9) | YES | | |
| NumberDraws | mediumint(9) | YES | | |
| NumberLosses | mediumint(9) | YES | | |
| WinRatio | decimal(5,4) | YES | | |
| DrawRatio | decimal(5,4) | YES | | |
| LossRatio | decimal(5,4) | YES | | |
| GoalsFor | mediumint(9) | YES | | |
| GoalsAgainst | mediumint(9) | YES | | |
| AverageGoalsFor | decimal(6,4) | YES | | |
| AverageGoalsAgainst | decimal(6,4) | YES | | |
| GoalRatio | decimal(7,4) | YES | | |
| MostGoalsScored | tinyint(4) | YES | | |
| LeastGoalsScored | tinyint(4) | NO | | 50 |
| MostGoalsConceded | tinyint(4) | YES | | |
| LeastGoalsConceded | tinyint(4) | NO | | 50 |
| BiggestWinDifference | tinyint(4) | YES | | |
| BiggestDrawSum | tinyint(4) | YES | | |
| BiggestLossDifference | tinyint(4) | YES | | |
| SmallestSumOfGoals | tinyint(4) | NO | | 50 |
| BiggestSumOfGoals | tinyint(4) | YES | | |
| DoubleDigits | mediumint(9) | YES | | |
| CleanSheets | mediumint(9) | YES | | |
| DoubleDigitsConceded | mediumint(9) | YES | | |
| CleanSheetsConceded | mediumint(9) | YES | | |
| DoubleDigitsRatio | decimal(5,4) | YES | | |
| CleanSheetsRatio | decimal(5,4) | YES | | |
| DoubleDigitsConcededRatio | decimal(5,4) | YES | | |
| CleanSheetsConcededRatio | decimal(5,4) | YES | | |
| DifferentOpponents | smallint(6) | YES | | |
| DifferentVictims | smallint(6) | YES | | |
| DoubleDigitsVictims | smallint(6) | YES | | |
| CleanSheetsVictims | smallint(6) | YES | | |
| MostGoalsConcededVictims | smallint(6) | YES | | |
| LeastGoalsScoredVictims | smallint(6) | YES | | |
| BiggestLossVictims | smallint(6) | YES | | |
| DifferentCulprits | smallint(6) | YES | | |
| DoubleDigitsCulprits | smallint(6) | YES | | |
| CleanSheetsCulprits | smallint(6) | YES | | |
| MostGoalsScoredCulprits | smallint(6) | YES | | |
| LeastGoalsConcededCulprits | smallint(6) | YES | | |
| BiggestWinCulprits | smallint(6) | YES | | |
| SumOfOpponentsRating | decimal(15,6) | YES | | |
| AverageOpponentRating | decimal(7,3) | YES | | |
| HighestRatedVictim | decimal(6,2) | YES | | |
| LowestRatedCulprit | decimal(6,2) | NO | | 5000.00 |
| CurrentRatingAscent | decimal(14,6) | YES | | |
| BiggestRatingAscent | decimal(11,3) | YES | | |
| CurrentRatingDescent | decimal(14,6) | YES | | |
| BiggestRatingDescent | decimal(11,3) | YES | | |
| LowestRating | decimal(6,2) | NO | | 5000.00 |
| PeakRating | decimal(6,2) | YES | | |
| ~~RecentAverageRating~~ | — | — | — | **Dropped** locally/staging via **SCH-016** (`016_drop_playertable_recent_average_rating.sql`). Legacy C++ still computed rolling avg of own `NewRating*`; site retired. |
| Rating | decimal(10,6) | NO | | 1600.000000 |
| WinningStreak | smallint(6) | YES | | |
| DrawingStreak | smallint(6) | YES | | |
| LosingStreak | smallint(6) | YES | | |
| NonWinStreak | smallint(6) | YES | | |
| NonDrawStreak | smallint(6) | YES | | |
| NonLossStreak | smallint(6) | YES | | |
| LongestWinningStreak | smallint(6) | YES | | |
| LongestDrawingStreak | smallint(6) | YES | | |
| LongestLosingStreak | smallint(6) | YES | | |
| LongestNonWinStreak | smallint(6) | YES | | |
| LongestNonDrawStreak | smallint(6) | YES | | |
| LongestNonLossStreak | smallint(6) | YES | | |
| ScoreStreak | smallint(6) | NO | | 0 | **SCH-018** — `on_the_scoresheet` facilitator; writer uses `0`, not NULL |
| MerchantStreak | smallint(6) | NO | | 0 | **SCH-018** — `merchant_streak` |
| ExactTenGoalStreak | smallint(6) | NO | | 0 | **SCH-018** — `minimalist_merchant` |
| WinMarginOneStreak | smallint(6) | NO | | 0 | **SCH-018** — `knife_edge` |
| LossMarginOneStreak | smallint(6) | NO | | 0 | **SCH-018** — `unlucky` |
| LastGameGameID | int(11) | YES | | |
| LastWinGameID | int(11) | YES | | |
| LastDrawGameID | int(11) | YES | | |
| LastLossGameID | int(11) | YES | | |
| LowestRatingGameID | int(11) | YES | | |
| PeakRatingGameID | int(11) | YES | | |
| MostGoalsScoredGameID | int(11) | YES | | |
| LeastGoalsScoredGameID | int(11) | YES | | |
| MostGoalsConcededGameID | int(11) | YES | | |
| LeastGoalsConcededGameID | int(11) | YES | | |
| BiggestWinGameID | int(11) | YES | | |
| BiggestDrawGameID | int(11) | YES | | |
| BiggestLossGameID | int(11) | YES | | |
| SmallestSumOfGoalsGameID | int(11) | YES | | |
| BiggestSumOfGoalsGameID | int(11) | YES | | |
| MostGoalsScoredVictimID | mediumint(9) | YES | | |
| LeastGoalsConcededVictimID | mediumint(9) | YES | | |
| BiggestWinVictimID | mediumint(9) | YES | | |
| MostGoalsConcededCulpritID | mediumint(9) | YES | | |
| LeastGoalsScoredCulpritID | mediumint(9) | YES | | |
| BiggestLossCulpritID | mediumint(9) | YES | | |
| HighestRatedVictimGameID | int(11) | YES | | |
| LowestRatedCulpritGameID | int(11) | YES | | |
| Feedback_UPnP | longtext | YES | | |
| Feedback_DXInput | longtext | YES | | |
| Feedback_StopwatchFrequency | bigint(20) | YES | | |
| Feedback_StopwatchIsHiRes | tinyint(1) | YES | | |
| Feedback_Screen | longtext | YES | | |
| Feedback_Version | longtext | YES | | |
| Feedback_Login | longtext | YES | | |
| Feedback_GameFontSize | float | YES | | |
| Feedback_Language | text | YES | | |
| Feedback_GameZoomLevel | int(11) | YES | | |
| Feedback_LastResolution | text | YES | | |
| Feedback_VerbosePing_OutgoingServer | longtext | YES | | |
| Feedback_VerbosePing_ArrivalClient | longtext | YES | | |
| Feedback_VerbosePing_OutgoingClient | longtext | YES | | |
| Feedback_VerbosePing_ArrivalServer | longtext | YES | | |
| Feedback_VerbosePing_JitterIncoming | longtext | YES | | |
| Feedback_VerbosePing_PeakIncoming | longtext | YES | | |
| Feedback_VerbosePing_JitterOutgoing | longtext | YES | | |
| Feedback_VerbosePing_PeakOutgoing | longtext | YES | | |
| IsOnline | tinyint(1) | YES | | |
| IPPort | varchar(64) | YES | | |
| GUID | varchar(32) | NO | | 0 |
| LobbyTime | int(11) | NO | | 0 |
| PlayerRank | int(11) | NO | | 9999 |
| Pref_UseCustomKits | int(11) | YES | | 0 |
| Pref_KitStyleA | tinyint(4) | YES | | 0 |
| Pref_KitColour1 | int(11) | YES | | 0 |
| Pref_KitColour2 | int(11) | YES | | 0 |
| Pref_KitColour3 | int(11) | YES | | 0 |
| Pref_KitStyleB | tinyint(4) | YES | | 0 |
| Pref_KitColour4 | int(11) | YES | | 0 |
| Pref_KitColour5 | int(11) | YES | | 0 |
| Profile_Bio | text | YES | | |
| Profile_AvatarURL | varchar(1024) | YES | | |
| Profile_LinkURL | varchar(1024) | YES | | |
| AvoidRank | tinyint(1) | YES | | 0 |
| Challenge1 | int(11) | YES | | 0 |
| Challenge2 | int(11) | YES | | |
| NewForumPosts | tinyint(4) | YES | | 0 |

---

## Career peak and nadir (`PeakRating`, `LowestRating`)

**Behaviour authority:** [`website-data-contract.md`](website-data-contract.md) § **Career peak and nadir**.

| Column | Unset sentinel | Meaning when set |
|--------|----------------|------------------|
| `PeakRating` | `0` | Career high Elo after establishment |
| `LowestRating` | `5000.00` | Career low Elo after establishment |
| `PeakRatingGameID` | NULL | Game id for current peak |
| `LowestRatingGameID` | NULL | Game id for current nadir |

**Target (future post-game):** unset until **20** rated games; at game 20 both initialize to post-game **`Rating`**; from game 21 onward max/min of `Rating` every game (no “must gain/lose this game” rule). **Legacy prod/replay today:** different rules — see contract § legacy vs required.

**Related but separate:** `Rating` (current Elo); `generalstatstable.BiggestPeakRating` (server record).

---

## `JoinDate` and `entered_arena`

In the app, **registering = entering the lobby**. Milestone **`entered_arena`**: `source_kind = lobby`, `achieved_at = JoinDate`. Distinct from **`debut`** (first rated game). Not ladder replay–derived. `LobbyTime` is a separate network/telemetry field — **do not** use it for this milestone. See [`milestones-facilitation.md`](milestones-facilitation.md).

---

## Related site filters (historical context)

- **`Display`** — legacy SteveB / Unity listing flag; **not used on PHP read paths** (Jul 2026). Ladder pool = **`NumberGames >= 1`** via `k2_playertable_rated_pool_sql()` in `lb_player_filters.php`.
- **`PlayerRank <> 9999`** — legacy **Unity / server-side rank** slot; **449** accounts keep the default **`9999`**. The ratings site previously filtered on this, which hid almost everyone.

### Display without derived career stats (Jun 2026)

After **zero-derived** (or partial replay on work), a row may have **`Display = 1`** (left from import per [`work-db-prepare.md`](work-db-prepare.md) §4.2) while **`NumberGames`**, ratio columns, and **`AverageOpponentRating`** are still **NULL** until post-game reaches that player’s games. Public PHP must not call `round()` / `number_format()` on those NULLs — use helpers in `site/public_html/includes/k2_safety.php`.

**Leaderboard display policy (Jun 2026):**

| Derived `NumberGames` | Games column | ELO `Rating` | Other career counts / ratios |
|----------------------|--------------|--------------|------------------------------|
| NULL or 0 (no replay yet) | **0** | **1600** (day-zero in DB) | **`-`** |
| **> 0** | actual count | actual rating | **0** when DB has NULL for a zero count (post-game often stores zero as NULL — see [`parity-audit-backlog.md`](coordination/parity-audit-backlog.md) AUD-001); ratios **0%** when unset but games started; **`-`** only when undefined (e.g. goal ratio sentinel **-1** on ranked2) |

All **`leaderboards/peak-rating.php`–`leaderboards/rating.php`** playertable wings use this policy via `k2_fmt_games_played`, `k2_fmt_count`, `k2_fmt_lb_stat`, `k2_fmt_pct_from_ratio`, and `k2_fmt_decimal` (pass **`NumberGames`** as `$games`). **ranked2** draw column and **goal ratio -1** sentinel are documented exceptions.

**Still dash when career started:** Peak / Nadir on **ranked1** (`k2_fmt_peak_rating` / `k2_fmt_nadir_rating` until set); **ranked2** goal ratio **-1** (0 GF and 0 GA); **ranked1** Lowest Culprit when stored as **5000** (unset sentinel).

**Other leaderboard pages:** **ranked8** (activity peaks), **ranked9** (league honours), **ranked10** (milestones) use different tables — only **ranked10** games column uses `k2_fmt_games_played`.

---

## Privacy / security

Rows include **credentials**, **telemetry** (`Feedback_*`), and **network hints** (`IPPort`). Do not expose dumps publicly; this doc lists **schema only**, no row data.
