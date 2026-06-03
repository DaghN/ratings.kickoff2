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
| REP-004 | Player monthly league aggregate | *(Retired Jun 2026)* Legacy `player_monthly_league`; superseded by REP-007 month rows in `player_period_league` | Done May 2026 | Done May 2026 | N/A | Table dropped SCH-017; rebuild SQL removed |
| REP-005 | Player peak period games cache | Rebuild `player_peak_period_games` from `player_period_games` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_peak_period_games_rebuild.sql`; staging ran after SCH-006 + REP-003 week refresh |
| REP-006 | Server daily activity aggregate | Rebuild `server_daily_activity` from `player_period_games` daily rows | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_daily_activity_rebuild.sql`; staging ran after SCH-007 |
| REP-007 | Player period league | Rebuild `player_period_league` day/week/month/year standings from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_period_league_rebuild.sql` |
| REP-008 | Player milestones | Rebuild `player_milestones`: game keys from `ratedresults` + league wave from `player_league_award` (run after REP-012) | Done May 2026 | **Done** (May 2026) | **Pending** | Staging: wave 1 full rebuild + **giant_slayer=31**; canonical totals after REP-008b: **6615** rows, **diversity_merchant=25** |
| REP-008b | `diversity_merchant` surgical | DELETE + re-insert `diversity_merchant` only (per-game DD × 5 opponents); reload catalog tier | Done May 2026 | **Done** (May 2026) | **Pending** | Staging verified: **25** holders, **6615** total rows, `giant_slayer` still **31**; catalog `tier_band=key`, `chart_token=amber`; [`milestones-staging-diversity-merchant-fix.md`](milestones-staging-diversity-merchant-fix.md) |
| REP-014 | Milestone definitions catalog | Load `milestone_definitions` from seed JSON | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Staging: `load_milestone_definitions.php`; **111** rows May 2026 (incl. `play_streak_100`); was 110 before add-one |
| REP-009 | Player matchup summary | Rebuild `player_matchup_summary` directed pair totals from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/player_matchup_summary_rebuild.sql` |
| REP-010 | Server period game totals | Rebuild `server_period_game_totals` (games/goals/draws/DD/CS per period) from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_period_game_totals_rebuild.sql` |
| REP-011 | Server period matchups | Rebuild `server_period_matchups` (canonical pair per period) from `ratedresults` | Done May 2026 | **Done** (May 2026) | **Pending** | `scripts/ladder/sql/server_period_matchups_rebuild.sql` |
| REP-012 | League period awards | Rebuild `league_period`, `player_league_award`, `player_league_totals` for all closed periods using `docs/leagues-rules-spec.md` sort | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Local: `scripts/run_league_awards_rebuild.ps1`; staging: `public_html/staging-scripts/run_league_awards_rebuild.php` |
| REP-013 | Player league slice totals | Rebuild `player_league_slice_totals` from `player_league_award` | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Runs at end of REP-012 full rebuild |
| REP-015 | Player rated play streaks | Rebuild `player_play_streaks` + HoF `LongestDaily/WeeklyPlayStreak*` from `player_period_games` + `ratedresults` | **Done** (May 2026) | **Done** (May 2026) | **Pending** | `php scripts/rebuild_player_play_streaks.php`; staging `staging-scripts/run_player_play_streaks_rebuild.php`; needs SCH-014 + REP-003 |

### Milestone unlock row counts (timeline)

**Do not treat older numbers as “wrong staging”.** Each row count matched a rebuild phase in May 2026:

| Phase | Typical total rows | Notes |
|-------|-------------------|--------|
| SCH-008 + REP-007–011 first pass | **151** | Only `established_20` + `dd_merchant_10` in `player_milestones` (shell parity before full catalog) |
| REP-014 + REP-008 wave 1 | **6658** | Full **110**-key catalog; before `diversity_merchant` per-game rule fix |
| **Canonical today** | **6615** | After **REP-008b**; verify `diversity_merchant` = **25**, `giant_slayer` = **31** |

**Staging / local sanity check:** After **REP-008b**, baseline **6615** rows. Total **grows** when new unlocks land (e.g. catalog **112**, `year_in_heaven`). **Jun 2026** staging after day-close surgical SQL: **6620** total, **`perfect_day`+`nightmare_day` = 113** (all `00:00:00`). Run log rows below keep historical counts for audit; use this table for “what should it be now?”.

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
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | SCH-009/010 + REP-012/013: 7424 instances, 21873 awards, 122 players / 7424 wins, 398 slice rows; matches local; Dagh ranked9 + Status leagues UI parity |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | SCH-014 + REP-015: `player_play_streaks` 264×2 rows; max day **87** week **126**; HoF `LongestDailyPlayStreak` 87 (id **582**, game **52468**), `LongestWeeklyPlayStreak` 126 (id **344**, game **39412**); matches local |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | SCH-011–013 + REP-014 + REP-008 wave 1: 110 keys, 6658 unlock rows, 0 null source_kind, giant_slayer=31, established_20_diff=0, geo4444=100/110; ~79s rebuild |
| 2026-05 | Staging | `kooldb` | Steve | 74870 | 0 | REP-008b + REP-014 reload: diversity_merchant=25, total_rows=6615, giant_slayer=31, diversity tier key/amber; matches local |
| 2026-06 | Staging | `kooldb` | Steve | — | 0 | Day-close surgical: `player_milestones_fix_day_close.sql`; perfect+nightmare=**113**, all `TIME(achieved_at)` midnight; total_rows=**6620**; garden PHP separate |

### Prod cutover (when scheduled)

- **Prerequisite:** [PER-001](periodic-register.md) fade off; schema migrations applied.
- **Tool:** Python replay tested on staging **or** Steve C++ replay to **same spec** (TBD with Steve).
- **Packet:** `docs/coordination/cutover-packet-template.md`
- **After:** Post-game C++ must match replay rules for **new** games (P5).

### Not in replay v1

- `resulttable` (unrated / live shell — see `PROJECT_MEMORY.md`)
- `PlayerRank`, `Display` website rules (PHP)
