# PG-005 — Player period games aggregate

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** draft for Steve review
**Schema:** [schema-register.md](../schema-register.md) **SCH-004**
**Feature:** Fast games-by-period leaderboards (`docs/player-period-games.md`)

---

## Summary

After each rated game, update `player_period_games` for both players across day, month, and year so PHP can render "most games" leaderboards without scanning `ratedresults`.

---

## Anchor in `docs/ratings_cpp.txt`

- **Function:** `RatingProcedureUnity`
- **Place in flow:** after the game row is inserted into `ratedresults`, `gameID` is fetched, and `GameDate` is read from that inserted row.
- **Current nearby code:** `docs/ratings_cpp.txt` lines around `SELECT LAST_INSERT_ID()` and `SELECT Date FROM ratedresults WHERE id=?`.

Use the inserted row's `GameDate`, not local wall-clock time, so the aggregate periods match the stored game row exactly.

---

## Insert instruction (for Steve)

Add this block after `GameDate` has been set from `ratedresults.Date`, before or after the existing `playertable` updates. It only depends on:

- `IdenA`
- `IdenB`
- `GameDate`
- existing `pstmt`

---

## C++ snippet

```cpp
// PG-005 — player_period_games day/month/year aggregate.
// Paste after GameDate is read from the inserted ratedresults row.

g_KOOLDB.SafeCreatePrepare(
    con,
    pstmt,
    "INSERT INTO player_period_games(period_type, period_start, player_id, games) VALUES "
    "('day', DATE(?), ?, 1), "
    "('month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1), "
    "('year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ?, 1), "
    "('day', DATE(?), ?, 1), "
    "('month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1), "
    "('year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ?, 1) "
    "ON DUPLICATE KEY UPDATE games = games + 1"
);

pstmt->setString(1, GameDate);
pstmt->setInt(2, IdenA);
pstmt->setString(3, GameDate);
pstmt->setInt(4, IdenA);
pstmt->setString(5, GameDate);
pstmt->setInt(6, IdenA);

pstmt->setString(7, GameDate);
pstmt->setInt(8, IdenB);
pstmt->setString(9, GameDate);
pstmt->setInt(10, IdenB);
pstmt->setString(11, GameDate);
pstmt->setInt(12, IdenB);

g_KOOLDB.SafePreparedExecute(con, pstmt);
pstmt = NULL;
```

---

## Data contract

| Table | Column | Write | Notes |
|-------|--------|-------|-------|
| `player_period_games` | `period_type` | insert | `day`, `month`, `year` |
| `player_period_games` | `period_start` | insert | day = game date; month = first day of month; year = Jan 1 |
| `player_period_games` | `player_id` | insert | `IdenA` / `IdenB` |
| `player_period_games` | `games` | insert/update | starts at 1, increments on duplicate key |

Prerequisite schema: `schema/migrations/003_player_period_games.sql`.

---

## Rebuild mirror

- **SQL:** `scripts/ladder/sql/player_period_games_rebuild.sql`
- **Local wrapper:** `scripts/rebuild_player_period_games_local.ps1`
- **Parity:** rebuild from `ratedresults` produces the same day/month/year count rows that C++ maintains for future games.

---

## Smoke check

| Step | Expected |
|------|----------|
| Backfill/rebuild | `SUM(games)` for each period type equals `COUNT(*) FROM ratedresults` × 2 |
| After one new rated game | Six rows inserted or incremented: A/B × day/month/year |
| API | `api/server_period_activity_leaderboard.php?period=month&key=YYYY-MM&limit=5` returns top players quickly |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | Drafted schema/backfill/post-game handoff |
