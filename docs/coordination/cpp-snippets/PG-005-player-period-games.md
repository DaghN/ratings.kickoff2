# PG-005 — Player period games aggregate

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** draft for Steve review
**Schema:** [schema-register.md](../schema-register.md) **SCH-004**
**Feature:** Fast games-by-period leaderboards (`docs/player-period-games.md`)

---

## Summary

After each rated game, update `player_period_games` for both players across day, week, month, and year so PHP can render "most games" leaderboards without scanning `ratedresults`.

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
// PG-005 — player_period_games day/week/month/year aggregate.
// Paste after GameDate is read from the inserted ratedresults row.

g_KOOLDB.SafeCreatePrepare(
    con,
    pstmt,
    "INSERT INTO player_period_games(period_type, period_start, player_id, games) VALUES "
    "('day', DATE(?), ?, 1), "
    "('week', DATE_SUB(DATE(?), INTERVAL WEEKDAY(?) DAY), ?, 1), "
    "('month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1), "
    "('year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ?, 1), "
    "('day', DATE(?), ?, 1), "
    "('week', DATE_SUB(DATE(?), INTERVAL WEEKDAY(?) DAY), ?, 1), "
    "('month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ?, 1), "
    "('year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ?, 1) "
    "ON DUPLICATE KEY UPDATE games = games + 1"
);

pstmt->setString(1, GameDate);
pstmt->setInt(2, IdenA);
pstmt->setString(3, GameDate);
pstmt->setString(4, GameDate);
pstmt->setInt(5, IdenA);
pstmt->setString(6, GameDate);
pstmt->setInt(7, IdenA);
pstmt->setString(8, GameDate);
pstmt->setInt(9, IdenA);

pstmt->setString(10, GameDate);
pstmt->setInt(11, IdenB);
pstmt->setString(12, GameDate);
pstmt->setString(13, GameDate);
pstmt->setInt(14, IdenB);
pstmt->setString(15, GameDate);
pstmt->setInt(16, IdenB);
pstmt->setString(17, GameDate);
pstmt->setInt(18, IdenB);

g_KOOLDB.SafePreparedExecute(con, pstmt);
pstmt = NULL;
```

---

## Optional same-flow peak cache

If PG-007 is enabled in the same C++ pass, run `cpp-snippets/PG-007-player-peak-period-games.md` immediately after this block. It reads back the eight rows touched above and updates `player_peak_period_games` only when the new period beats the player's stored peak.

---

## Data contract

| Table | Column | Write | Notes |
|-------|--------|-------|-------|
| `player_period_games` | `period_type` | insert | `day`, `week`, `month`, `year` |
| `player_period_games` | `period_start` | insert | day = game date; week = Monday of game week; month = first day of month; year = Jan 1 |
| `player_period_games` | `player_id` | insert | `IdenA` / `IdenB` |
| `player_period_games` | `games` | insert/update | starts at 1, increments on duplicate key |

Prerequisite schema: `schema/migrations/003_player_period_games.sql` plus `schema/migrations/005_period_activity_week_and_peaks.sql` for `week`.

---

## Rebuild mirror

- **SQL:** `scripts/ladder/sql/player_period_games_rebuild.sql`
- **Local wrapper:** `scripts/rebuild_player_period_games_local.ps1`
- **Peak cache SQL:** `scripts/ladder/sql/player_peak_period_games_rebuild.sql`
- **Parity:** rebuild from `ratedresults` produces the same day/week/month/year count rows that C++ maintains for future games.

---

## Smoke check

| Step | Expected |
|------|----------|
| Backfill/rebuild | `SUM(games)` for each period type equals `COUNT(*) FROM ratedresults` × 2 |
| After one new rated game | Eight period rows inserted or incremented: A/B × day/week/month/year |
| API | `api/server_period_activity_leaderboard.php?period=month&key=YYYY-MM&limit=5` returns top players quickly |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | Drafted schema/backfill/post-game handoff |
| 2026-05 | Agent | Expanded to week rows and linked PG-007 peak cache maintenance |
