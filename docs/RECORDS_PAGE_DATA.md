# Records page (`hall-of-fame.php`) — data sources

## Context links (May 2026)

- **All record values** link to the matching Leaderboards wing via `includes/records_hof_links.php`, plus `k2_sort` / `k2_dir` on ranked wings. **Ratio/average rows only** also pass `provisional=0` (same ≥20 pool as HoF eligibility). Count/streak/single-game rows omit that param so leaderboards open with default include toggles **on**. `js/k2-table.js` applies sort on load for `ranked-pages-table` only. Labels stay plain text.
- **`*GameID` columns** (`MostGoalsScoredInOneGameGameID`, etc.) remain in `generalstatstable` for a future game-record list — not used in HoF links.
- **Activity peaks** (“most games in one year/month/week/day”): value → `leaderboards/activity-peaks.php#k2-peak-period-{day|week|month|year}` — scrolls that panel into view (`scroll-margin-top` + `activity-mode-toggle.js` ensures Calendar tab is shown).

## `generalstatstable` row `id=1`

Headline server totals and **non-ratio** hall-of-fame rows (most games, streaks, single-game records, pair rows, rating ascent/peak, etc.). Updated by production C++ per game; local replay via `scripts/ladder/server_records.py` + `generalstats.py`.

**Rated play streaks (May 2026):** `LongestDailyPlayStreak*` / `LongestWeeklyPlayStreak*` on row `id=1` — consecutive UTC days / Mon–Sun weeks with ≥1 rated game. Shown on `hall-of-fame.php` as **Most days in a row** and **Most weeks in a row** (after peak “most games in …”, before Most wins). Backfill: REP-015; post-game: contract § `player_play_streaks`. **Staging verified** May 2026: daily **87** (player 582), weekly **126** (player 344).

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

Eligible: `NumberGames >= 20` (`K2_ESTABLISHED_MIN_GAMES` in `lb_player_filters.php` — same as leaderboard “exclude provisional” and `established_20` milestone). Ties: lowest `ID`.

Steve: [`records-post-game-exception.md`](coordination/records-post-game-exception.md) — remove C++ post-game writes for ratio leaders on `generalstatstable`.
