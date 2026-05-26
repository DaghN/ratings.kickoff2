# Replay register

Full-history rebuild from **`ratedresults`** (canonical ladder games). Engine: **`python -m scripts.ladder`** — spec `docs/replay-v1-scope-and-reset.md`.

**Parameters (current sandbox default):** K=32, start rating 1600, **no decay**, order `Date ASC, id ASC`.

| ID | Trigger | Scope | Local | Staging | Prod | Command / record |
|----|---------|-------|-------|---------|------|------------------|
| REP-001 | Ladder replay v1/v2 baseline | All `ratedresults`; rebuild `playertable` + `generalstatstable` | Done May 2026 | Done May 2026 | **Not run** | `docs/STAGING_REPLAY.md`; `bash run_staging_ladder_replay.sh` (`--target staging`) |
| REP-002 | *(template)* New derived columns need backfill | Extend `scripts/ladder` then full `run` | — | — | — | After schema register items applied |
| REP-003 | Player period games aggregate | Rebuild `player_period_games` day/week/month/year counts from all `ratedresults` | Done May 2026 | **Pending Steve** | **Pending** | `scripts/ladder/sql/player_period_games_rebuild.sql`; local wrapper `scripts/rebuild_player_period_games_local.ps1` |
| REP-004 | Player monthly league aggregate | Rebuild `player_monthly_league` monthly standings from all `ratedresults` | Done May 2026 | Done May 2026 | **Pending** | `scripts/ladder/sql/player_monthly_league_rebuild.sql`; local wrapper `scripts/rebuild_player_monthly_league_local.ps1` |
| REP-005 | Player peak period games cache | Rebuild `player_peak_period_games` from `player_period_games` | Done May 2026 | **Pending Steve** | **Pending** | `scripts/ladder/sql/player_peak_period_games_rebuild.sql`; local wrapper runs after REP-003 |

### Run log (append rows)

| Date | Environment | DB | Who | Games | Exit | Notes |
|------|-------------|-----|-----|-------|------|-------|
| 2026-05 | Local | `ko2unity_db` | Dagh | ~74870 | 0 | v2 playertable + generalstats |
| 2026-05 | Staging | `kooldb` | Steve | ~74870 | 0 | `docs/STAGING_REPLAY.md` |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-003: `player_period_games` rebuilt; each period type sums to 149,740 player appearances |
| 2026-05 | Staging | `kooldb` | Steve | staging current | 0 | REP-003: schema + rebuild ran; expectation test passed. Steve noted MariaDB requires `COUNT(*)`, not `COUNT()` |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-004: `player_monthly_league` rebuilt; 2,679 rows; `SUM(played)` = 149,740 player appearances |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | REP-004: `player_monthly_league` rebuilt; 2,674 rows; `SUM(played)` = 149,740 player appearances; indexes verified |
| 2026-05 | Local | `ko2unity_db` | Agent | 74870 | 0 | REP-003 + REP-005: week rows added; `player_period_games` rows day/week/month/year = 27,629 / 8,053 / 2,679 / 583, each summing to 149,740 appearances; peak cache rebuilt with 264 rows per period type |

### Prod cutover (when scheduled)

- **Prerequisite:** [PER-001](periodic-register.md) fade off; schema migrations applied.
- **Tool:** Python replay tested on staging **or** Steve C++ replay to **same spec** (TBD with Steve).
- **Packet:** `docs/coordination/cutover-packet-template.md`
- **After:** Post-game C++ must match replay rules for **new** games (P5).

### Not in replay v1

- `resulttable` (unrated / live shell — see `PROJECT_MEMORY.md`)
- `PlayerRank`, `Display` website rules (PHP)
