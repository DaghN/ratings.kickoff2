# `ratedresults` schema snapshot

**What this is:** One row per **rated online game** on the KOOL ladder. This is the only per-game table used by the site — there is no separate `games` table. `game.php?id=` loads a row from here; charts and APIs aggregate this table.

**How this doc was made:** Dev/staging database **`kooldb`**, captured **2026-05-19** via `scripts/throwaway_ratedresults_schema.php` (`?once=ratedresults-schema-one-shot`). **Treat as a snapshot** — re-run the throwaway and refresh this file if columns change.

**Companion doc:** `docs/playertable-schema.md` (per-player aggregates; many extremes on `playertable` point back to a `ratedresults.id` via `*GameID` columns).

**Engine / charset:** `MyISAM`, `utf8mb4_general_ci`.

---

## Counts (this snapshot)

| Metric | Value |
|--------|------:|
| Total games | 74,870 |
| Earliest `Date` | 2017-06-09 22:06:32 |
| Latest `Date` | 2026-05-18 21:55:30 |
| `id` range | 10 … 74,879 |
| `AUTO_INCREMENT` (next id) | 74,880 |

---

## Result encoding (`ActualScore` / `WinnerID`)

Both columns are **derived from goals** at insert time (legacy C++ in `docs/ratings_cpp.txt`). **Raw facts** for outcome are `GoalsA`, `GoalsB`, `idA`, `idB`.

**`ActualScore`** (decimal) — canonical for Elo and new APIs:

| `ActualScore` | Meaning (from `game.php`) | Rows (snapshot) |
|---------------|---------------------------|----------------:|
| `1` | **Player A** (`idA`) won | 34,333 |
| `0` | **Player B** (`idB`) won | 31,484 |
| `0.5` | Draw | 9,053 |

**`WinnerID`** — denormalized winner pointer; **never NULL** in this snapshot (74,870 / 74,870). **“Populated” means non-NULL**, not “always a real player id”:

| Outcome | `WinnerID` value | Rows (snapshot) |
|---------|------------------|----------------:|
| A won (`ActualScore = 1`) | `idA` | 34,333 |
| B won (`ActualScore = 0`) | `idB` | 31,484 |
| Draw (`ActualScore = 0.5`) | **`-1`** (legacy sentinel) | 9,053 |

Verified on local import **`ko2unity_db`** (May 2026): 0 rows where draw `WinnerID` is `idA`/`idB`; 0 win rows with mismatched `WinnerID`. PHP in this repo **does not write** `WinnerID` — only reads it.

**H2H / charts:** Prefer **`ActualScore`** for draws (`api/player_head_to_head.php` checks `0.5` before `WinnerID`). Older pages (e.g. `individual3.php`) also use `ActualScore == 0.5` for draw display.

**Legacy flags** (still on the row, used by older opponent pages): `HomeWin`, `Draw`, `AwayWin`, `DDPlayerA` / `DDPlayerB` (double digit), `CSPlayerA` / `CSPlayerB` (clean sheet). Prefer `ActualScore` and goals for new code; flags are rebuilt on replay like `WinnerID`.

**Ratings on the row:** `RatingA` / `RatingB` = pre-game; `AdjustmentA` / `AdjustmentB` = delta; `NewRatingA` / `NewRatingB` = post-game. `api/player_rating_history.php` uses **post-game** `NewRatingA` / `NewRatingB` for player rating charts.

---

## Indexes

| Index | Columns | Purpose |
|-------|---------|---------|
| **PRIMARY** | `id` | Game link `game.php?id=` |
| **idx_ratedresults_idA** | `idA` | Player-as-side-A lookups (`WHERE idA = ?`, feast/charts) |
| **idx_ratedresults_idB** | `idB` | Player-as-side-B lookups (`WHERE idB = ?`) |

Apply on each environment:

| Where | How |
|-------|-----|
| **Local (Laragon)** | `scripts/apply_ratedresults_player_indexes.ps1` |
| **Staging / prod (no SSH)** | Copy `scripts/throwaway_ratedresults_player_indexes.php` to `public_html/`, open in browser (preview then `&apply=1`), delete file after — see script header |
| **With terminal** | `scripts/sql/ratedresults_player_indexes.sql` |

`Date` is still unindexed — add only if date-first queries (server-wide month charts) need it.

Queries using `idA = ? OR idB = ?` use both indexes (index merge). Much faster than full table scan for profile load.

---

## Naming note for PHP

Columns are **PascalCase** in MySQL (`GoalsA`, `HomeWin`, `Date`). Some legacy PHP uses lowercase in SQL (`goalsA`, `homewin`) — MariaDB on Linux often accepts that; **new code should use the real column names** from the table below.

`NameA` / `NameB` are denormalized copies of player names at game time (not a substitute for `playertable`).

---

## Columns (full list)

From **`SHOW FULL COLUMNS`** (snapshot).

| Field | Type | Null | Key | Notes |
|-------|------|------|-----|--------|
| id | int(11) | NO | PRI | auto_increment; game link `game.php?id=` |
| Date | timestamp | NO | | game datetime; use in monthly charts |
| idA | int(11) | YES | | home / side A player id |
| NameA | mediumtext | YES | | snapshot name A |
| idB | int(11) | YES | | away / side B player id |
| NameB | mediumtext | YES | | snapshot name B |
| RatingA | decimal(10,6) | YES | | pre-game ELO A |
| RatingB | decimal(10,6) | YES | | pre-game ELO B |
| RatingDifference | decimal(10,6) | YES | | |
| GoalsA | int(11) | YES | | goals scored by A |
| GoalsB | int(11) | YES | | goals scored by B |
| HomeWin | tinyint(4) | YES | | legacy flag |
| Draw | tinyint(4) | YES | | legacy flag |
| AwayWin | tinyint(4) | YES | | legacy flag |
| DDPlayerA | tinyint(4) | YES | | legacy double-digit flag |
| DDPlayerB | tinyint(4) | YES | | legacy |
| CSPlayerA | tinyint(4) | YES | | legacy clean sheet |
| CSPlayerB | tinyint(4) | YES | | legacy |
| ExpectedScoreA | decimal(10,6) | YES | | Elo expected score A |
| ExpectedScoreB | decimal(10,6) | YES | | Elo expected score B |
| ActualScore | decimal(10,6) | YES | | **0** = B win, **0.5** = draw, **1** = A win |
| AdjustmentA | decimal(10,6) | YES | | rating change A |
| AdjustmentB | decimal(10,6) | YES | | rating change B |
| NewRatingA | decimal(10,6) | YES | | post-game rating A |
| NewRatingB | decimal(10,6) | YES | | post-game rating B |
| SumOfGoals | int(11) | YES | | `GoalsA + GoalsB` |
| GoalDifference | int(11) | YES | | |
| WinnerID | int(11) | YES | | winner’s `playertable.ID`; **`-1`** on draw |

---

## `SHOW CREATE TABLE` (reference)

```sql
CREATE TABLE `ratedresults` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `idA` int(11) DEFAULT NULL,
  `NameA` mediumtext DEFAULT NULL,
  `idB` int(11) DEFAULT NULL,
  `NameB` mediumtext DEFAULT NULL,
  `RatingA` decimal(10,6) DEFAULT NULL,
  `RatingB` decimal(10,6) DEFAULT NULL,
  `RatingDifference` decimal(10,6) DEFAULT NULL,
  `GoalsA` int(11) DEFAULT NULL COMMENT 'Goals Scored',
  `GoalsB` int(11) DEFAULT NULL,
  `HomeWin` tinyint(4) DEFAULT NULL,
  `Draw` tinyint(4) DEFAULT NULL,
  `AwayWin` tinyint(4) DEFAULT NULL,
  `DDPlayerA` tinyint(4) DEFAULT NULL,
  `DDPlayerB` tinyint(4) DEFAULT NULL,
  `CSPlayerA` tinyint(4) DEFAULT NULL,
  `CSPlayerB` tinyint(4) DEFAULT NULL,
  `ExpectedScoreA` decimal(10,6) DEFAULT NULL,
  `ExpectedScoreB` decimal(10,6) DEFAULT NULL,
  `ActualScore` decimal(10,6) DEFAULT NULL,
  `AdjustmentA` decimal(10,6) DEFAULT NULL,
  `AdjustmentB` decimal(10,6) DEFAULT NULL,
  `NewRatingA` decimal(10,6) DEFAULT NULL,
  `NewRatingB` decimal(10,6) DEFAULT NULL,
  `SumOfGoals` int(11) DEFAULT NULL,
  `GoalDifference` int(11) DEFAULT NULL,
  `WinnerID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=74880 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

---

## Related site usage (for agents)

| Area | Usage |
|------|--------|
| `game.php` | Single-row detail (numeric `mysqli_fetch_row` indices — fragile; prefer named columns in new code) |
| `individual3.php` | Player game list |
| `server3.php` | Recent activity |
| `api/player_*`, `api/server_*` | Charts and leaderboards (monthly counts, H2H, rating history, peak month, etc.) |
| `playertable.*GameID` | Extremes on profile link to `ratedresults.id` |

**Established player rule (20 games)** is computed from this table (career game count per player), not stored on each row.

---

## Refreshing this doc

1. Deploy `scripts/throwaway_ratedresults_schema.php` to `public_html/` (manual copy; not in default WinSCP sync of `site/public_html/`).
2. Open with `?once=ratedresults-schema-one-shot`, one copy from the grey box.
3. Replace curated sections above from the new dump (or paste raw block into a scratch file and re-tidy).
4. Delete the throwaway from the server.

---

## Privacy / security

Rows contain **player names** at time of game. This doc lists **schema and aggregate counts only**, not sample games. Do not commit row-level exports with names unless intentional.
