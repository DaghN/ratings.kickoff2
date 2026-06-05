# `generalstatstable` schema snapshot

**What this is:** A **single-row** table of server-wide ladder totals and “hall of fame” records. Production updates **`WHERE id = 1`** after each rated game (`docs/ratings_cpp.txt` → `RatingProcedureUnity`). The PHP site reads it from `activity.php` and `hall-of-fame.php` (`SELECT * FROM generalstatstable` — no `WHERE`, but only one row exists).

**How this doc was made:** Dev/staging **`kooldb`**, captured **2026-05-19** via `scripts/throwaway_generalstatstable_schema.php` (`?once=generalstatstable-schema-one-shot`). **Treat as a snapshot** — re-run the throwaway and replace this file if columns change.

**Companion docs:** `docs/ratedresults-schema.md`, `docs/playertable-schema.md`, `docs/ratings_cpp.txt`, `docs/ladder-engine-plan.md`.

**PG-004 (May 2026):** This snapshot still lists **28 ratio player leader** columns (`BiggestWinRatio`, …). They were **dropped** on local `ko2unity_db` via `site/public_html/ops/sql/migrations/002_generalstatstable_drop_ratio_leader_columns.sql` (SCH-003). Steve should apply the same on prod; Records page reads those leaders from `playertable` (`docs/RECORDS_PAGE_DATA.md`). Server-wide `DoubleDigitsRatio` / `CleanSheetsRatio` **stay** on this table.

**Engine / charset:** `MyISAM`, `utf8mb4_general_ci`.

---

## Counts (this snapshot)

| Metric | Value | Notes |
|--------|------:|-------|
| Table rows | **1** | Only `id = 1` |
| `GamesPlayed` | **74,860** | Server game counter |
| `ratedresults` rows | **74,870** | See `docs/ratedresults-schema.md` — **10 more** game rows than `GamesPlayed` |
| `NumberOfDecidedGames` | 65,807 | Non-draw games |
| `NumberOfDraws` | 9,053 | 65,807 + 9,053 = **74,860** ✓ |
| `NumberOfPlayers` | **270** | Players counted in server stats (first game increments) |
| `playertable` rows | 475 | Accounts in DB; **259** with `Display = 1` |
| `GoalsScored` | 551,653 | Sum of goals in all counted games |

**Reconciliation:** `GamesPlayed` matches decided + draws but is **10 short** of `COUNT(*)` on `ratedresults`. Worth checking whether those games were excluded from server totals historically or counters drifted.

---

## Shape of the row

**123 columns** on one logical record (`id` + **122** data fields), all **nullable except `id`**. No `AUTO_INCREMENT` — `id` is `tinyint(4) NOT NULL` with **`UNIQUE KEY (id)`** (not a typical auto-increment PK).

Each “server record” is usually a **tuple** of columns:

| Role | Pattern | Count (approx.) |
|------|---------|----------------:|
| Record **value** | `MostGamesPlayed`, `BiggestWinDifference`, … | 25 |
| Record holder **id** | `MostGamesPlayedID`, `BiggestWinRatioID`, … | 27 |
| Record holder **name** | `MostGamesPlayedName`, … (`varchar(16)`) | 27 |
| Record **date** | `MostGamesPlayedDate`, … (`mediumtext`) | 25 |
| Link to **game** | `*GameID` (four columns) | 4 |

Plus **14 headline server counters/ratios** (players, games, draws, goals, double-digits, clean sheets, etc.).

Draw / high-scoring games that involve **both** players store **two** ids and names (`BiggestDrawSumIDA`/`IDB`, `BiggestSumOfGoalsIDA`/`IDB`, `BiggestDrawSumNameA`/`B`, etc.).

---

## Headline server counters (group 1)

Updated incrementally in C++ each game; legacy C++ ratio-leader blocks used **`NumberGames >= 30`** (removed from `generalstatstable` May 2026 — PHP reads ratio leaders from `playertable` with **`>= 20`** / `K2_ESTABLISHED_MIN_GAMES`).

| Field | Type | Role |
|-------|------|------|
| `NumberOfPlayers` | int | Incremented when a player’s `NumberGames` hits 1 |
| `DifferentOpponentsAverage` | decimal(10,5) | `AVG(DifferentOpponents)` over players with ≥1 opponent |
| `GamesPlayed` | int | +1 per rated game |
| `GamesPlayedAverage` | decimal(10,3) | C++: `2 * GamesPlayed / NumberOfPlayers` |
| `NumberOfDecidedGames` | int | +1 if not a draw |
| `NumberOfDraws` | int | +1 on draw |
| `DecidedGamesRatio` | decimal(10,8) | |
| `DrawsRatio` | decimal(10,8) | |
| `GoalsScored` | int | += `SumOfGoals` |
| `GoalsPerGameAverage` | decimal(10,7) | |
| `DoubleDigits` | int | += both sides’ DD flags |
| `CleanSheets` | int | += both sides’ CS flags |
| `DoubleDigitsRatio` | decimal(10,8) | |
| `CleanSheetsRatio` | decimal(10,8) | |

---

## Server records — values (group 2)

| Field | Type |
|-------|------|
| `MostGamesPlayed` | int |
| `MostWins` | int |
| `BiggestWinRatio` | decimal(10,8) |
| `MostGoalsScored` | int |
| `BiggestGoalsForAverage` | decimal(10,6) |
| `SmallestGoalsAgainstAverage` | decimal(10,6) |
| `BiggestGoalRatio` | decimal(10,5) |
| `MostGoalsScoredInOneGame` | int |
| `BiggestWinDifference` | int |
| `BiggestDrawSum` | int |
| `BiggestSumOfGoals` | int |
| `MostDoubleDigits` | int |
| `MostCleanSheets` | int |
| `BiggestDoubleDigitsRatio` | decimal(10,8) |
| `BiggestCleanSheetsRatio` | decimal(10,8) |
| `MostDifferentOpponents` | int |
| `MostDifferentVictims` | int |
| `MostDoubleDigitsVictims` | int |
| `MostCleanSheetsVictims` | int |
| `BiggestAverageOpponentRating` | decimal(10,6) |
| `BiggestRatingAscent` | decimal(10,5) |
| `BiggestPeakRating` | decimal(10,6) |
| `LongestWinningStreak` | int |
| `LongestDrawingStreak` | int |
| `LongestNonLossStreak` | int |

---

## Server records — holder ids (group 3)

`MostGamesPlayedID`, `MostWinsID`, `BiggestWinRatioID`, `MostGoalsScoredID`, `BiggestGoalsForAverageID`, `SmallestGoalsAgainstAverageID`, `BiggestGoalRatioID`, `MostGoalsScoredInOneGameID`, `BiggestWinDifferenceID`, `BiggestDrawSumIDA`, `BiggestDrawSumIDB`, `BiggestSumOfGoalsIDA`, `BiggestSumOfGoalsIDB`, `MostDoubleDigitsID`, `MostCleanSheetsID`, `BiggestDoubleDigitsRatioID`, `BiggestCleanSheetsRatioID`, `MostDifferentOpponentsID`, `MostDifferentVictimsID`, `MostDoubleDigitsVictimsID`, `MostCleanSheetsVictimsID`, `BiggestAverageOpponentRatingID`, `BiggestRatingAscentID`, `BiggestPeakRatingID`, `LongestWinningStreakID`, `LongestDrawingStreakID`, `LongestNonLossStreakID` — all `int(11)`.

---

## Server records — holder names & dates (groups 4–5)

**Names:** `MostGamesPlayedName`, `MostWinsName`, … (27 × `varchar(16)`), plus `BiggestDrawSumNameA`/`B`, `BiggestSumOfGoalsNameA`/`B`.

**Dates:** matching `*Date` fields (25 × `mediumtext`) — set from the current game’s `Date` when a record is broken in C++.

---

## Links to `ratedresults.id` (group 6)

| Field | Type |
|-------|------|
| `MostGoalsScoredInOneGameGameID` | int(11) |
| `BiggestWinDifferenceGameID` | int(11) |
| `BiggestDrawSumGameID` | int(11) |
| `BiggestSumOfGoalsGameID` | int(11) |

---

## Indexes

**`UNIQUE KEY id (id)`** only — single-row table in practice.

---

## `SHOW CREATE TABLE` (reference)

```sql
CREATE TABLE `generalstatstable` (
  `id` tinyint(4) NOT NULL,
  `NumberOfPlayers` int(11) DEFAULT NULL,
  `DifferentOpponentsAverage` decimal(10,5) DEFAULT NULL,
  `GamesPlayed` int(11) DEFAULT NULL,
  `GamesPlayedAverage` decimal(10,3) DEFAULT NULL,
  `NumberOfDecidedGames` int(11) DEFAULT NULL,
  `NumberOfDraws` int(11) DEFAULT NULL,
  `DecidedGamesRatio` decimal(10,8) DEFAULT NULL,
  `DrawsRatio` decimal(10,8) DEFAULT NULL,
  `GoalsScored` int(11) DEFAULT NULL,
  `GoalsPerGameAverage` decimal(10,7) DEFAULT NULL,
  `DoubleDigits` int(11) DEFAULT NULL,
  `CleanSheets` int(11) DEFAULT NULL,
  `DoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `CleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  `MostGamesPlayed` int(11) DEFAULT NULL,
  `MostWins` int(11) DEFAULT NULL,
  `BiggestWinRatio` decimal(10,8) DEFAULT NULL,
  `MostGoalsScored` int(11) DEFAULT NULL,
  `BiggestGoalsForAverage` decimal(10,6) DEFAULT NULL,
  `SmallestGoalsAgainstAverage` decimal(10,6) DEFAULT NULL,
  `BiggestGoalRatio` decimal(10,5) DEFAULT NULL,
  `MostGoalsScoredInOneGame` int(11) DEFAULT NULL,
  `BiggestWinDifference` int(11) DEFAULT NULL,
  `BiggestDrawSum` int(11) DEFAULT NULL,
  `BiggestSumOfGoals` int(11) DEFAULT NULL,
  `MostDoubleDigits` int(11) DEFAULT NULL,
  `MostCleanSheets` int(11) DEFAULT NULL,
  `BiggestDoubleDigitsRatio` decimal(10,8) DEFAULT NULL,
  `BiggestCleanSheetsRatio` decimal(10,8) DEFAULT NULL,
  `MostDifferentOpponents` int(11) DEFAULT NULL,
  `MostDifferentVictims` int(11) DEFAULT NULL,
  `MostDoubleDigitsVictims` int(11) DEFAULT NULL,
  `MostCleanSheetsVictims` int(11) DEFAULT NULL,
  `BiggestAverageOpponentRating` decimal(10,6) DEFAULT NULL,
  `BiggestRatingAscent` decimal(10,5) DEFAULT NULL,
  `BiggestPeakRating` decimal(10,6) DEFAULT NULL,
  `LongestWinningStreak` int(11) DEFAULT NULL,
  `LongestDrawingStreak` int(11) DEFAULT NULL,
  `LongestNonLossStreak` int(11) DEFAULT NULL,
  `MostGamesPlayedID` int(11) DEFAULT NULL,
  `MostWinsID` int(11) DEFAULT NULL,
  `BiggestWinRatioID` int(11) DEFAULT NULL,
  `MostGoalsScoredID` int(11) DEFAULT NULL,
  `BiggestGoalsForAverageID` int(11) DEFAULT NULL,
  `SmallestGoalsAgainstAverageID` int(11) DEFAULT NULL,
  `BiggestGoalRatioID` int(11) DEFAULT NULL,
  `MostGoalsScoredInOneGameID` int(11) DEFAULT NULL,
  `BiggestWinDifferenceID` int(11) DEFAULT NULL,
  `BiggestDrawSumIDA` int(11) DEFAULT NULL,
  `BiggestDrawSumIDB` int(11) DEFAULT NULL,
  `BiggestSumOfGoalsIDA` int(11) DEFAULT NULL,
  `BiggestSumOfGoalsIDB` int(11) DEFAULT NULL,
  `MostDoubleDigitsID` int(11) DEFAULT NULL,
  `MostCleanSheetsID` int(11) DEFAULT NULL,
  `BiggestDoubleDigitsRatioID` int(11) DEFAULT NULL,
  `BiggestCleanSheetsRatioID` int(11) DEFAULT NULL,
  `MostDifferentOpponentsID` int(11) DEFAULT NULL,
  `MostDifferentVictimsID` int(11) DEFAULT NULL,
  `MostDoubleDigitsVictimsID` int(11) DEFAULT NULL,
  `MostCleanSheetsVictimsID` int(11) DEFAULT NULL,
  `BiggestAverageOpponentRatingID` int(11) DEFAULT NULL,
  `BiggestRatingAscentID` int(11) DEFAULT NULL,
  `BiggestPeakRatingID` int(11) DEFAULT NULL,
  `LongestWinningStreakID` int(11) DEFAULT NULL,
  `LongestDrawingStreakID` int(11) DEFAULT NULL,
  `LongestNonLossStreakID` int(11) DEFAULT NULL,
  `MostGamesPlayedName` varchar(16) DEFAULT NULL,
  `MostWinsName` varchar(16) DEFAULT NULL,
  `BiggestWinRatioName` varchar(16) DEFAULT NULL,
  `MostGoalsScoredName` varchar(16) DEFAULT NULL,
  `BiggestGoalsForAverageName` varchar(16) DEFAULT NULL,
  `SmallestGoalsAgainstAverageName` varchar(16) DEFAULT NULL,
  `BiggestGoalRatioName` varchar(16) DEFAULT NULL,
  `MostGoalsScoredInOneGameName` varchar(16) DEFAULT NULL,
  `BiggestWinDifferenceName` varchar(16) DEFAULT NULL,
  `BiggestDrawSumNameA` varchar(16) DEFAULT NULL,
  `BiggestDrawSumNameB` varchar(16) DEFAULT NULL,
  `BiggestSumOfGoalsNameA` varchar(16) DEFAULT NULL,
  `BiggestSumOfGoalsNameB` varchar(16) DEFAULT NULL,
  `MostDoubleDigitsName` varchar(16) DEFAULT NULL,
  `MostCleanSheetsName` varchar(16) DEFAULT NULL,
  `BiggestDoubleDigitsRatioName` varchar(16) DEFAULT NULL,
  `BiggestCleanSheetsRatioName` varchar(16) DEFAULT NULL,
  `MostDifferentOpponentsName` varchar(16) DEFAULT NULL,
  `MostDifferentVictimsName` varchar(16) DEFAULT NULL,
  `MostDoubleDigitsVictimsName` varchar(16) DEFAULT NULL,
  `MostCleanSheetsVictimsName` varchar(16) DEFAULT NULL,
  `BiggestAverageOpponentRatingName` varchar(16) DEFAULT NULL,
  `BiggestRatingAscentName` varchar(16) DEFAULT NULL,
  `BiggestPeakRatingName` varchar(16) DEFAULT NULL,
  `LongestWinningStreakName` varchar(16) DEFAULT NULL,
  `LongestDrawingStreakName` varchar(16) DEFAULT NULL,
  `LongestNonLossStreakName` varchar(16) DEFAULT NULL,
  `MostGamesPlayedDate` mediumtext DEFAULT NULL,
  `MostWinsDate` mediumtext DEFAULT NULL,
  `BiggestWinRatioDate` mediumtext DEFAULT NULL,
  `MostGoalsScoredDate` mediumtext DEFAULT NULL,
  `BiggestGoalsForAverageDate` mediumtext DEFAULT NULL,
  `SmallestGoalsAgainstAverageDate` mediumtext DEFAULT NULL,
  `BiggestGoalRatioDate` mediumtext DEFAULT NULL,
  `MostGoalsScoredInOneGameDate` mediumtext DEFAULT NULL,
  `BiggestWinDifferenceDate` mediumtext DEFAULT NULL,
  `BiggestDrawSumDate` mediumtext DEFAULT NULL,
  `BiggestSumOfGoalsDate` mediumtext DEFAULT NULL,
  `MostDoubleDigitsDate` mediumtext DEFAULT NULL,
  `MostCleanSheetsDate` mediumtext DEFAULT NULL,
  `BiggestDoubleDigitsRatioDate` mediumtext DEFAULT NULL,
  `BiggestCleanSheetsRatioDate` mediumtext DEFAULT NULL,
  `MostDifferentOpponentsDate` mediumtext DEFAULT NULL,
  `MostDifferentVictimsDate` mediumtext DEFAULT NULL,
  `MostDoubleDigitsVictimsDate` mediumtext DEFAULT NULL,
  `MostCleanSheetsVictimsDate` mediumtext DEFAULT NULL,
  `BiggestAverageOpponentRatingDate` mediumtext DEFAULT NULL,
  `BiggestRatingAscentDate` mediumtext DEFAULT NULL,
  `BiggestPeakRatingDate` mediumtext DEFAULT NULL,
  `LongestWinningStreakDate` mediumtext DEFAULT NULL,
  `LongestDrawingStreakDate` mediumtext DEFAULT NULL,
  `LongestNonLossStreakDate` mediumtext DEFAULT NULL,
  `MostGoalsScoredInOneGameGameID` int(11) DEFAULT NULL,
  `BiggestWinDifferenceGameID` int(11) DEFAULT NULL,
  `BiggestDrawSumGameID` int(11) DEFAULT NULL,
  `BiggestSumOfGoalsGameID` int(11) DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

---

## C++ update pattern (`docs/ratings_cpp.txt`)

After each game the live path:

1. `SELECT * FROM generalstatstable WHERE id=1`
2. Bump counters from the game (`GamesPlayed`, draws/decided, goals, DD/CS, …)
3. Compare per-game events to running server records (margin, draw sum, etc.)
4. Legacy C++ re-queried `playertable` with **`NumberGames >= 30`** for ratio leaders (obsolete; site uses **>= 20** at PHP read time)
5. One massive `UPDATE generalstatstable SET … WHERE id=1` (122 columns in the SET list)

Column names in MySQL **match the C++ variable names** (e.g. `MostGamesPlayedIDS` → `MostGamesPlayedID`).

---

## Python replay implications (`docs/ladder-engine-plan.md`)

| Topic | Implication |
|-------|-------------|
| **v1 scope** | Safe to **defer** per-game `generalstatstable` updates; charts/ranked sort depend on `ratedresults` + `playertable`, not this row. |
| **`reset_universe`** | `scripts/ladder/schema.py` applies `scripts/ladder/sql/generalstatstable.sql` if needed; NULLs all columns on `id=1` (keep the row); do not drop the table. |
| **`replay_all` end** | Prefer **one rebuild** of `id=1` from replayed `ratedresults` + `playertable` rather than 74k incremental C++-style updates. |
| **Elo-sensitive records** | `BiggestPeakRating`, `BiggestRatingAscent`, `BiggestAverageOpponentRating` will change when ratings are replayed from ground truth. |
| **Names** | `*Name` columns are denormalized; rebuild from `playertable.Name` by `*ID` when refreshing. |
| **`activity.php`** | Will show wrong server totals until this row is rebuilt or updated. |

---

## Refreshing this doc

1. Run `scripts/throwaway_generalstatstable_schema.php` on staging.
2. One copy from the grey box → replace the curated content (or re-sanitize from paste).
3. Delete the throwaway from the server.

---

## Privacy / security

Live row contains **player names** in `*Name` fields. This doc lists **schema and aggregate counts only**.
