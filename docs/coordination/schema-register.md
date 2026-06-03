# Schema register

SQL files live in **`schema/migrations/`** (numbered, apply in order). Local: `schema/apply_local.ps1`. Staging/prod: Steve runs the same files against the intended server DB; staging is `kooldb`, production DB identity is Steve-managed and not stored in repo config.

**Note:** Steve reported the game server may **auto-add columns** it knows about. This register still documents **indexes, new tables, and website-driven schema** we rely on, plus what replay/post-game expect.

**Behavior authority:** Table meanings and rebuild/post-game rules live in `docs/website-data-contract.md`. This register tracks schema deployment status only.

| ID | Migration | Description | Local | Staging | Prod | Replay depends? | Notes |
|----|-----------|-------------|-------|---------|------|-----------------|-------|
| SCH-001 | `001_ratedresults_player_indexes.sql` | Indexes `idx_ratedresults_idA`, `idx_ratedresults_idB` on `ratedresults` | Done | Done | **Pending** | No | Profile load ~8s→~1s for heavy players; see `PROJECT_MEMORY.md` |
| SCH-002 | `scripts/ladder/sql/generalstatstable.sql` | Create `generalstatstable` + seed row `id=1` | Via replay | Via replay | Exists on prod | Yes (batch rebuild) | Slimmed May 2026 (PG-004): no ratio player leader cols |
| SCH-003 | `002_generalstatstable_drop_ratio_leader_columns.sql` | DROP 28 ratio leader columns on `generalstatstable` | **Applied** (May 2026) | **Pending Steve** | **Pending** | No | Records C++: `records-post-game-exception.md`; see `docs/RECORDS_PAGE_DATA.md` |
| SCH-004 | `003_player_period_games.sql` | Create `player_period_games` day/month/year activity aggregate table | **Done** | **Done** | **Pending** | Yes (backfill/rebuild) | Staging applied May 2026; archived handoff `archive/player-period-games-handoff.md` |
| SCH-005 | `004_status_performance_and_monthly_league.sql` | Add Status performance indexes (`ratedresults.Date`, live `resulttable`) + `player_monthly_league` aggregate table | **Done** | **Done** | **Pending** | No (table dropped by SCH-017) | Staging applied May 2026; indexes retained; monthly table removed Jun 2026 |
| SCH-006 | `005_period_activity_week_and_peaks.sql` | Add `week` rows to `player_period_games` and create `player_peak_period_games` cache | **Done** | **Done** (May 2026) | **Pending** | Yes (period + peak rebuild) | Staging SCH-006 + REP-003 week + REP-005 done May 2026 |
| SCH-007 | `006_server_daily_activity.sql` | Create `server_daily_activity` daily aggregate (games + active players per day) | **Done** | **Done** (May 2026) | **Pending** | Yes (backfill from `player_period_games`) | Staging SCH-007 + REP-006 done May 2026 |
| SCH-008 | `007_stored_truth_expansion.sql` | Create `player_period_league`, `player_milestones`, `player_matchup_summary`, `server_period_game_totals`, `server_period_matchups` | **Done** | **Done** (May 2026) | **Pending** | Yes (rebuild scripts per table) | Staging: Steve applied `007` + REP-007–011; parity checks pass (74,870 games). **`player_milestones` row count:** see [`replay-register.md`](replay-register.md) § Milestone unlock row counts — **6615** canonical after REP-008b (not **151** from first pass). Contract `docs/website-data-contract.md` |
| SCH-009 | `008_league_period_awards.sql` | Create `league_period`, `player_league_award`, `player_league_totals` | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Yes (REP-012) | Rules: `docs/leagues-rules-spec.md`; staging via `staging-sql/008` + `staging-scripts/run_league_awards_rebuild.php` |
| SCH-010 | `009_player_league_slice_totals.sql` | Create `player_league_slice_totals` (8-way career breakdown per player) | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Yes (REP-013) | Included in staging full awards rebuild |
| SCH-011 | `010_milestone_definitions.sql` | Create `milestone_definitions` catalog (seed JSON; **111** after `play_streak_100`) | **Done** (May 2026) | **Done** (May 2026) | **Pending** | No | Staging **111** rows verified May 2026 (`play_streak_100`: **100 days**, rule_short as seeded) |
| SCH-012 | `011_player_milestones_source.sql` | Add `source_kind` + game/league pointer columns on `player_milestones` | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Yes (REP-008) | Staging REP-008 May 2026; rebuild populates; post-game must set on new unlocks |
| SCH-013 | `012_player_milestones_source_lobby.sql` | Extend `source_kind` with `lobby` for `entered_arena` (`playertable.JoinDate` = register/lobby) | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Yes (REP-008) | Staging with REP-008; live writer at account register on prod |
| SCH-014 | `014_player_play_streaks.sql` | Create `player_play_streaks`; add `LongestDailyPlayStreak*` / `LongestWeeklyPlayStreak*` on `generalstatstable` | **Done** (May 2026) | **Done** (May 2026) | **Pending** | Yes (REP-015) | Staging verified May 2026 (Steve): day/week max **87**/**126**, HoF player **582**/**344**; UI `ranked4.php` + `server2.php` |
| SCH-015 | `015_drop_kungfu_columns.sql` | DROP `playertable.KungFu*` (9) + `resulttable.KungFuGameID` (1); sanitizes invalid `LastGame`/`LastLogin` before ALTER | **Done** (Jun 2026, prepare on work; parity PASS) | **Pending** | **Pending** | No | Apply with UTC (`apply_local.ps1`); coordinate Steve if prod C++ still writes these columns |
| SCH-016 | `016_drop_playertable_recent_average_rating.sql` | DROP `playertable.RecentAverageRating` (retired; was avg of own last N post-game ratings) | **Done** (Jun 2026, via prepare migrate on work) | **Pending** | **Pending** | No | Website does not read; Python replay no longer writes; prod C++ still references until cutover |
| SCH-017 | `017_drop_player_monthly_league.sql` | DROP legacy `player_monthly_league` (month league via `player_period_league`) | **Pending** (Jun 2026) | **Pending** | **Pending** | No | Status reads `player_period_league` only; apply with prepare migrate-work |
| SCH-018 | `018_playertable_milestone_streak_facilitators.sql` | ADD `ScoreStreak`, `MerchantStreak`, `ExactTenGoalStreak`, `WinMarginOneStreak`, `LossMarginOneStreak` on `playertable` (P6 chrono facilitators) | **Pending** (Jun 2026) | **Pending** | **Pending** | Yes (full replay resets to 0; unlocks via post-game) | PHP `post_game_player_state.php` + `post_game_milestones.php`; oracle `player_state.py` |

### Adding a row

1. Add `schema/migrations/NNN_short_name.sql` (idempotent where possible: `IF NOT EXISTS`).
2. Add a line to this table.
3. Run `schema/apply_local.ps1` on `ko2unity_db`.
4. After Steve applies on staging/prod, set Staging/Prod to **Done** and note date in Notes.

### Status legend

**Pending** · **Done** · **N/A** · **Blocked** (waiting on Steve)
