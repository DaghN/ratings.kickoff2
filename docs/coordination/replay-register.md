# Replay register

Full-history rebuild from **`ratedresults`** (canonical ladder games). Engine: **`python -m scripts.ladder`** — spec `docs/replay-v1-scope-and-reset.md`.

**Parameters (current sandbox default):** K=32, start rating 1600, **no decay**, order `Date ASC, id ASC`.

**Timezone rule:** `ratedresults.Date` is a MySQL `timestamp`. SQL rebuild scripts must start with `SET time_zone = '+00:00';` before using `DATE(Date)` / `DATE_FORMAT(Date, ...)`, so local rebuilds match staging/prod period boundaries regardless of the developer machine timezone.

**Behavior authority:** Derived table meanings, rebuild order, and parity rules live in `docs/website-data-contract.md`. This register tracks rebuild status and run history only.

**Local website-derived data command:** `scripts/rebuild_website_derived_data_local.ps1` runs the normal derived-table rebuild pipeline and parity checks.

| ID | Trigger | Scope | Local | Staging | Prod | Command / record |
|----|---------|-------|-------|---------|------|------------------|
| REP-001 | Ladder replay v1/v2 baseline | All `ratedresults`; rebuild `playertable` + `generalstatstable` | Done May 2026 | Done May 2026 | **Not run** | `docs/STAGING_REPLAY.md`; `bash run_staging_ladder_replay.sh` (`--target staging`) |
| REP-002 | *(template)* New derived columns need backfill | Extend `scripts/ladder` then full `run` | — | — | — | After schema register items applied |
| REP-003 | Player period games aggregate | Rebuild `player_period_games` day/week/month/year counts from all `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_period_games_rebuild.sql`; staging includes week refresh after SCH-006 |
| REP-004 | Player monthly league aggregate | Rebuild legacy `player_monthly_league` monthly standings from all `ratedresults` | Done May 2026 | Done May 2026 | **Pending** | `scripts/ladder/sql/player_monthly_league_rebuild.sql`; local entrypoint `scripts/rebuild_website_derived_data_local.ps1` |
| REP-005 | Player peak period games cache | Rebuild `player_peak_period_games` from `player_period_games` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_peak_period_games_rebuild.sql`; staging ran after SCH-006 + REP-003 week refresh |
| REP-006 | Server daily activity aggregate | Rebuild `server_daily_activity` from `player_period_games` daily rows | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_daily_activity_rebuild.sql`; staging ran after SCH-007 |
| REP-007 | Player period league | Rebuild `player_period_league` day/week/month/year standings from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_period_league_rebuild.sql` |
| REP-008 | Player milestones | Rebuild `player_milestones` (established_20, dd_merchant_10) from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_milestones_rebuild.sql`; staging re-ran once after MariaDB fix (`ROW_NUMBER` not user variables) |
| REP-009 | Player matchup summary | Rebuild `player_matchup_summary` directed pair totals from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_matchup_summary_rebuild.sql` |
| REP-010 | Server period game totals | Rebuild `server_period_game_totals` (games/goals/draws/DD/CS per period) from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_period_game_totals_rebuild.sql` |
| REP-011 | Server period matchups | Rebuild `server_period_matchups` (canonical pair per period) from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_period_matchups_rebuild.sql` |
| REP-012 | League period awards | Rebuild `league_period`, `player_league_award`, `player_league_totals` for all closed periods using `docs/leagues-rules-spec.md` sort | **Done** (May 2026) | **Pending** | **Pending** | `php scripts/finalize_league_periods.php --full-rebuild`; wrapper `scripts/run_league_awards_rebuild.ps1`; SQL pointer `scripts/ladder/sql/league_period_awards_rebuild.sql` |
| REP-013 | Player league slice totals | Rebuild `player_league_slice_totals` from `player_league_award` | **Done** (May 2026) | **Pending** | **Pending** | `php scripts/finalize_league_periods.php --rebuild-aggregates` (after SCH-010); included in full rebuild |

### Run log (append rows)

| Date | Environment | DB | Who | Games | Exit | Notes |
|------|-------------|-----|-----|-------|------|-------|
| 2026-05 | Local | `ko2unity_db` | Dagh | ~74870 | 0 | v2 playertable + generalstats |
| 2026-05 | Staging | `kooldb` | Steve | ~74870 | 0 | `docs/STAGING_REPLAY.md` |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-003: `player_period_games` rebuilt; each period type sums to 149,740 player appearances |
| 2026-05 | Staging | `kooldb` | Steve | staging current | 0 | REP-003 (day/month/year): schema + rebuild ran; expectation test passed. Steve noted MariaDB requires `COUNT(*)`, not `COUNT()` |
| 2026-05 | Staging | `kooldb` | Steve | staging current | 0 | REP-003 week refresh + REP-005 + REP-006 after SCH-006/SCH-007 (Dagh confirmed May 2026) |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-004: `player_monthly_league` rebuilt; 2,679 rows; `SUM(played)` = 149,740 player appearances |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | REP-004: `player_monthly_league` rebuilt; 2,674 rows; `SUM(played)` = 149,740 player appearances; indexes verified |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-003 + REP-005: week rows added; `player_period_games` rows day/week/month/year = 27,629 / 8,053 / 2,679 / 583, each summing to 149,740 appearances; peak cache rebuilt with 264 rows per period type |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-006: `server_daily_activity` rebuilt from `player_period_games`; 3,146 day rows; `SUM(rated_games)` = 74,870 matching `ratedresults` count |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-007: `player_period_league` rebuilt; 38,944 rows; `SUM(played)/2` for day = 74,870 |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-008: `player_milestones` rebuilt; 151 rows (107 established_20, 44 dd_merchant_10); established_20 matches `playertable` NumberGames>=20 |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-009: `player_matchup_summary` rebuilt; 3,905 rows; `SUM(games)/2` = 74,870 |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-010: `server_period_game_totals` rebuilt; 3,731 rows; `SUM(rated_games)` for day = 74,870 |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-011: `server_period_matchups` rebuilt; 63,283 rows; `SUM(games)` for day = 74,870 |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | UTC timezone pin added to all SQL rebuild scripts, then REP-003/004/005/006/007/008/009/010/011 rerun; daily rows now match `SET time_zone = '+00:00'` buckets (e.g. 2026-05-17=26, 2026-05-18=31) |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | SCH-008 + REP-007–011: first pass OK except `established_20` (MariaDB user-variable quirk); milestones re-run with fixed `player_milestones_rebuild.sql`; verify table all green (`distinct_game_totals=1`, `established_20_diff=0`, day league rows 27,201, milestones 151) |

### Prod cutover (when scheduled)

- **Prerequisite:** [PER-001](periodic-register.md) fade off; schema migrations applied.
- **Tool:** Python replay tested on staging **or** Steve C++ replay to **same spec** (TBD with Steve).
- **Packet:** `docs/coordination/cutover-packet-template.md`
- **After:** Post-game C++ must match replay rules for **new** games (P5).

### Not in replay v1

- `resulttable` (unrated / live shell — see `PROJECT_MEMORY.md`)
- `PlayerRank`, `Display` website rules (PHP)
