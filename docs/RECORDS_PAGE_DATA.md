# Records page (`server2.php`) — data sources

## `generalstatstable` row `id=1`

Headline server totals and **non-ratio** hall-of-fame rows (most games, streaks, single-game records, pair rows, rating ascent/peak, etc.). Updated by production C++ per game; local replay via `scripts/ladder/server_records.py` + `generalstats.py`.

**Schema (May 2026):** Ratio **player** leader columns were **dropped** from this table locally via `schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql`. Steve should apply the same on staging/prod (`SCH-003`). Server-wide `DoubleDigitsRatio` / `CleanSheetsRatio` **remain** (all-games totals, not player leaders).

## Ratio / average leaders (from `playertable`)

Loaded at page render: `site/public_html/includes/records_ratio_leaders.php`

| Records label | Column | Order |
|---------------|--------|-------|
| Best attack average | `AverageGoalsFor` | DESC |
| Best defense average | `AverageGoalsAgainst` | ASC |
| Best goal ratio | `GoalRatio` | DESC, `GoalRatio > -1` |
| Highest winning frequency | `WinRatio` | DESC |
| Highest double digit frequency | `DoubleDigitsRatio` | DESC |
| Highest clean sheet frequency | `CleanSheetsRatio` | DESC |

Eligible: `NumberGames >= 30`. Ties: lowest `ID`.

Steve: [`records-post-game-exception.md`](coordination/records-post-game-exception.md) — remove C++ post-game writes for ratio leaders on `generalstatstable`.
