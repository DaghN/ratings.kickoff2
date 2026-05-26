# Player period games aggregate

**Purpose:** Fast day/week/month/year "most games" leaderboards without scanning `ratedresults` on page load.

---

## Table

`player_period_games` stores only periods where a player has at least one rated game.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `period_start` | `YYYY-MM-DD`; week rows use Monday, month rows use first day of month, year rows use Jan 1 |
| `player_id` | `playertable.ID` |
| `games` | Rated games played by that player in that period |

Primary key: `(period_type, period_start, player_id)`.

Examples:

| Query period | Stored row |
|--------------|------------|
| May 25, 2026 | `day`, `2026-05-25` |
| Week starting May 25, 2026 | `week`, `2026-05-25` |
| March 2019 | `month`, `2019-03-01` |
| 2019 | `year`, `2019-01-01` |

`player_peak_period_games` caches each player's personal best period for the Activity Hall of Fame.

| Column | Meaning |
|--------|---------|
| `period_type` | `day`, `week`, `month`, or `year` |
| `player_id` | `playertable.ID` |
| `period_start` | Winning period start; earliest period wins tied peak counts |
| `games` | Player's best game count for that period type |

---

## Writers

### Backfill / rebuild

Historical truth is rebuilt from `ratedresults`:

- SQL: `scripts/ladder/sql/player_period_games_rebuild.sql`
- Peak cache SQL: `scripts/ladder/sql/player_peak_period_games_rebuild.sql`
- Local wrapper: `scripts/rebuild_player_period_games_local.ps1`

Local result on May 2026 dump:

| Period | Rows | Player appearances |
|--------|------|--------------------|
| day | 27,629 | 149,740 |
| week | 8,053 | 149,740 |
| month | 2,679 | 149,740 |
| year | 583 | 149,740 |

The appearance total equals `ratedresults` games × 2 players.
The peak cache rebuild produced 264 rows per period type locally; PHP joins to `playertable`, so visible leaderboard rows can be slightly lower if historical aggregate rows reference player IDs no longer present in `playertable`.

### Live post-game

Production must upsert both rated players after every inserted `ratedresults` row:

- Player A + Player B
- day + week + month + year
- total: 8 `INSERT ... ON DUPLICATE KEY UPDATE games = games + 1` values per rated game

Steve handoff: `docs/coordination/cpp-snippets/PG-005-player-period-games.md`.

Then PG-007 conditionally updates `player_peak_period_games` for the same eight touched rows.

---

## Readers

The period activity query layer reads this table:

- `site/public_html/includes/period_activity_leaderboard_query.php`
- `site/public_html/includes/peak_month_leaderboard_query.php` (Activity busiest day/week/month/year from `player_peak_period_games`, all-time “Since” dates, and Longevity first/last/days from day rows; falls back to `player_period_games`/`ratedresults` if newer aggregate tables are not ready)
- `site/public_html/server2.php` (Records Peak activity year/month/week/day rows)
- API: `site/public_html/api/server_period_activity_leaderboard.php`
- Preview page: `site/public_html/dev-period-activity.php` (local-only, unlinked)

Local Longevity timing on the May 2026 dump: aggregate query ~39 ms; raw `ratedresults` fallback ~3.4 s. Treat the aggregate path as the production-speed path.

The API contract remains:

```text
period=day|week|month|year
key=YYYY-MM-DD|Monday YYYY-MM-DD|YYYY-MM|YYYY
limit=1..100
```

---

## Deployment state

| Environment | State |
|-------------|-------|
| Local `ko2unity_db` | Schema applied; backfill run |
| Staging `kooldb` | Original day/month/year schema + rebuild run by Steve; week + peak cache pending new handoff |
| Production `kooldb` | Pending method decision: schema + rebuild + post-game C++ |

Staging has no live game writes, so backfill is enough to test PHP there. Production needs the C++ writer before this becomes live truth.

MariaDB compatibility note from staging: write aggregate checks with `COUNT(*)`, not bare `COUNT()`.
