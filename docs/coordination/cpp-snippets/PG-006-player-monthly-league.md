# PG-006 — Player monthly league aggregate

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** draft for Steve review
**Schema:** [schema-register.md](../schema-register.md) **SCH-005**
**Feature:** Fast Status monthly league table (`docs/STATUS_PAGE_DATA.md`)

---

## Summary

After each rated game, update `player_monthly_league` for both players in the inserted game's calendar month so Status can render the monthly league without rebuilding standings from `ratedresults` on page load.

---

## Anchor in `docs/ratings_cpp.txt`

- **Function:** `RatingProcedureUnity`
- **Place in flow:** after the game row is inserted into `ratedresults`, `gameID` is fetched, and `GameDate` is read from that inserted row.
- **Current nearby code:** `docs/ratings_cpp.txt` around `SELECT LAST_INSERT_ID()` and `SELECT Date FROM ratedresults WHERE id=?`.

Use the inserted row's `GameDate`, not local wall-clock time, so the aggregate month matches the stored game row exactly.

---

## Insert instruction (for Steve)

Add this block after `GameDate` has been set from `ratedresults.Date`, before or after the existing `playertable` updates. It depends on:

- `IdenA`
- `IdenB`
- `goalsA`
- `goalsB`
- `ActualScore`
- `GameDate`
- existing `pstmt`

---

## C++ snippet

```cpp
// PG-006 — player_monthly_league standings aggregate.
// Paste after GameDate is read from the inserted ratedresults row.

int MonthlyWinsA = (ActualScore == 1) ? 1 : 0;
int MonthlyDrawsA = (ActualScore == 0.5) ? 1 : 0;
int MonthlyLossesA = (ActualScore == 0) ? 1 : 0;
int MonthlyPointsA = (ActualScore == 1) ? 3 : ((ActualScore == 0.5) ? 1 : 0);
int MonthlyGDA = goalsA - goalsB;

int MonthlyWinsB = (ActualScore == 0) ? 1 : 0;
int MonthlyDrawsB = (ActualScore == 0.5) ? 1 : 0;
int MonthlyLossesB = (ActualScore == 1) ? 1 : 0;
int MonthlyPointsB = (ActualScore == 0) ? 3 : ((ActualScore == 0.5) ? 1 : 0);
int MonthlyGDB = goalsB - goalsA;

g_KOOLDB.SafeCreatePrepare(
    con,
    pstmt,
    "INSERT INTO player_monthly_league("
    "month_start, player_id, played, wins, draws, losses, goals_for, goals_against, goal_difference, points"
    ") VALUES "
    "(CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1, ?, ?, ?, ?, ?, ?, ?), "
    "(CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1, ?, ?, ?, ?, ?, ?, ?) "
    "ON DUPLICATE KEY UPDATE "
    "played = played + VALUES(played), "
    "wins = wins + VALUES(wins), "
    "draws = draws + VALUES(draws), "
    "losses = losses + VALUES(losses), "
    "goals_for = goals_for + VALUES(goals_for), "
    "goals_against = goals_against + VALUES(goals_against), "
    "goal_difference = goal_difference + VALUES(goal_difference), "
    "points = points + VALUES(points)"
);

pstmt->setString(1, GameDate);
pstmt->setInt(2, IdenA);
pstmt->setInt(3, MonthlyWinsA);
pstmt->setInt(4, MonthlyDrawsA);
pstmt->setInt(5, MonthlyLossesA);
pstmt->setInt(6, goalsA);
pstmt->setInt(7, goalsB);
pstmt->setInt(8, MonthlyGDA);
pstmt->setInt(9, MonthlyPointsA);

pstmt->setString(10, GameDate);
pstmt->setInt(11, IdenB);
pstmt->setInt(12, MonthlyWinsB);
pstmt->setInt(13, MonthlyDrawsB);
pstmt->setInt(14, MonthlyLossesB);
pstmt->setInt(15, goalsB);
pstmt->setInt(16, goalsA);
pstmt->setInt(17, MonthlyGDB);
pstmt->setInt(18, MonthlyPointsB);

g_KOOLDB.SafePreparedExecute(con, pstmt);
pstmt = NULL;
```

---

## Data contract

| Table | Column | Write | Notes |
|-------|--------|-------|-------|
| `player_monthly_league` | `month_start` | insert | First day of inserted game month |
| `player_monthly_league` | `player_id` | insert | `IdenA` / `IdenB` |
| `player_monthly_league` | `played` | insert/update | Increments by 1 for both players |
| `player_monthly_league` | `wins`, `draws`, `losses` | insert/update | Player-perspective outcome |
| `player_monthly_league` | `goals_for`, `goals_against`, `goal_difference` | insert/update | Player-perspective goals |
| `player_monthly_league` | `points` | insert/update | 3/1/0 monthly league points |

Prerequisite schema: `schema/migrations/004_status_performance_and_monthly_league.sql`.

---

## Rebuild mirror

- **SQL:** `scripts/ladder/sql/player_monthly_league_rebuild.sql`
- **Local wrapper:** `scripts/rebuild_player_monthly_league_local.ps1`
- **Parity:** rebuild from `ratedresults` produces the same monthly player standings that C++ maintains for future games.

---

## Smoke check

| Step | Expected |
|------|----------|
| Backfill/rebuild | `SUM(played)` equals `COUNT(*) FROM ratedresults` × 2 |
| Current Status page | Current and previous monthly tables match the raw `ratedresults` aggregate |
| After one new rated game | Two rows inserted or incremented: A/B for that game month |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | Drafted schema/backfill/post-game handoff |
