# PG-007 — Player peak period games cache

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** draft for Steve review
**Schema:** [schema-register.md](../schema-register.md) **SCH-006**
**Feature:** Fast Activity Hall of Fame peak day/week/month/year tables

---

## Summary

After PG-005 increments `player_period_games`, update `player_peak_period_games` for both players across day, week, month, and year so PHP can render each player's personal best period without re-ranking all period rows.

---

## Anchor in `docs/ratings_cpp.txt`

- **Function:** `RatingProcedureUnity`
- **Place in flow:** immediately after PG-005 has upserted `player_period_games` for the inserted rated game.
- **Current nearby code:** same anchor as PG-005, after `GameDate` is read from the inserted `ratedresults` row.

Use the inserted row's `GameDate`, not local wall-clock time.

---

## Insert instruction (for Steve)

Add this block after the PG-005 `player_period_games` upsert. It only depends on:

- `IdenA`
- `IdenB`
- `GameDate`
- existing `pstmt`

---

## C++ snippet

```cpp
// PG-007 — player_peak_period_games day/week/month/year cache.
// Paste immediately after PG-005 has incremented player_period_games.

g_KOOLDB.SafeCreatePrepare(
    con,
    pstmt,
    "INSERT INTO player_peak_period_games(period_type, player_id, period_start, games) "
    "SELECT g.period_type, g.player_id, g.period_start, g.games "
    "FROM player_period_games g INNER JOIN ("
    "SELECT 'day' AS period_type, DATE(?) AS period_start, ? AS player_id "
    "UNION ALL SELECT 'week', DATE_SUB(DATE(?), INTERVAL WEEKDAY(?) DAY), ? "
    "UNION ALL SELECT 'month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ? "
    "UNION ALL SELECT 'year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ? "
    "UNION ALL SELECT 'day', DATE(?), ? "
    "UNION ALL SELECT 'week', DATE_SUB(DATE(?), INTERVAL WEEKDAY(?) DAY), ? "
    "UNION ALL SELECT 'month', CAST(DATE_FORMAT(?, '%Y-%m-01') AS DATE), ? "
    "UNION ALL SELECT 'year', CAST(CONCAT(YEAR(?), '-01-01') AS DATE), ?"
    ") c ON c.period_type = g.period_type "
    "AND c.period_start = g.period_start "
    "AND c.player_id = g.player_id "
    "ON DUPLICATE KEY UPDATE "
    "period_start = CASE "
    "WHEN VALUES(games) > player_peak_period_games.games THEN VALUES(period_start) "
    "WHEN VALUES(games) = player_peak_period_games.games AND VALUES(period_start) < player_peak_period_games.period_start THEN VALUES(period_start) "
    "ELSE player_peak_period_games.period_start END, "
    "games = GREATEST(player_peak_period_games.games, VALUES(games))"
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

## Data contract

| Table | Column | Write | Notes |
|-------|--------|-------|-------|
| `player_peak_period_games` | `period_type` | insert | `day`, `week`, `month`, `year` |
| `player_peak_period_games` | `player_id` | insert | `IdenA` / `IdenB` |
| `player_peak_period_games` | `period_start` | insert/update | Earliest period wins when the peak game count is tied |
| `player_peak_period_games` | `games` | insert/update | Stored personal peak count for that period type |

Prerequisite schema: `schema/migrations/005_period_activity_week_and_peaks.sql`.

---

## Rebuild mirror

- **SQL:** `scripts/ladder/sql/player_peak_period_games_rebuild.sql`
- **Local wrapper:** `scripts/rebuild_player_period_games_local.ps1`
- **Parity:** rebuild chooses the same winner as C++: highest `games`, earliest `period_start` on ties.

---

## Smoke check

| Step | Expected |
|------|----------|
| Backfill/rebuild | Each period type has one peak row per player with at least one game in that period type |
| After one new rated game | Up to eight peak rows are inserted or conditionally updated: A/B × day/week/month/year |
| Ranked8 | Activity peak day/week/month/year tables read `player_peak_period_games` first |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | Drafted schema/backfill/post-game handoff |
