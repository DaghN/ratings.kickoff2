# Schema register

**Start here for status:** [`cutover-readiness.md`](cutover-readiness.md) (layers A/B/C — prep done vs live execution).

DDL lives in **`site/public_html/ops/sql/migrations/`** (apply in order). Applied on work DBs via:

```text
php site/public_html/ops/run_prepare.php migrate-work --target local-work
```

(Steve prod copy / live: same files — [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md).)

**Legacy `kooldb` (May 2026):** frozen POC — **no further work.** Proof environment: **`kooldb1`** / local **`ko2unity_work`**.

**Behavior authority:** [`website-data-contract.md`](../website-data-contract.md). This register tracks **DDL deployment status** only.

| ID | Migration | Description | In ops package | `kooldb1` / work proof | Live prod executed | Notes |
|----|-----------|-------------|----------------|------------------------|--------------------|-------|
| SCH-001 | `001_ratedresults_player_indexes.sql` | Indexes `idx_ratedresults_idA`, `idx_ratedresults_idB` | Yes | **Done** (migrate-work) | **Not yet** | Profile ~8s→~1s; included in cutover migrate-work |
| SCH-002 | `scripts/ladder/sql/generalstatstable.sql` | `generalstatstable` + seed row `id=1` | Yes | Via simul / ladder | **Exists** (legacy prod) | Ratio cols dropped SCH-003 |
| SCH-003 | `002_generalstatstable_drop_ratio_leader_columns.sql` | DROP 28 ratio leader columns | Yes | **Done** | **Not yet** | [`records-post-game-exception.md`](records-post-game-exception.md) |
| SCH-004 | `003_player_period_games.sql` | `player_period_games` | Yes | **Done** (simul) | **Not yet** | Filled by ops simul, not batch SQL |
| SCH-005 | `004_status_performance_and_monthly_league.sql` | `ratedresults.Date` index + legacy monthly table | Yes | **Done** | **Not yet** | Monthly table removed SCH-017 |
| SCH-006 | `005_period_activity_week_and_peaks.sql` | Week rows + `player_peak_period_games` | Yes | **Done** (simul) | **Not yet** | |
| SCH-007 | `006_server_daily_activity.sql` | `server_daily_activity` | Yes | **Done** (simul) | **Not yet** | |
| SCH-008 | `007_stored_truth_expansion.sql` | Five aggregate tables | Yes | **Done** (simul) | **Not yet** | Milestone counts: [`../archive/replay-register-2026-05.md`](../archive/replay-register-2026-05.md) |
| SCH-009 | `008_league_period_awards.sql` | League awards tables | Yes | **Done** (simul) | **Not yet** | Honours: `leaderboards/league-honours.php` proven on `kooldb1` |
| SCH-010 | `009_player_league_slice_totals.sql` | Slice totals | Yes | **Done** (simul) | **Not yet** | |
| SCH-011 | `010_milestone_definitions.sql` | Catalog table | Yes | **Done** (seed-catalog) | **Not yet** | **112** keys in seed Jun 2026 |
| SCH-012 | `011_player_milestones_source.sql` | `source_kind` + pointers | Yes | **Done** (simul) | **Not yet** | |
| SCH-013 | `012_player_milestones_source_lobby.sql` | `lobby` source_kind | Yes | **Done** (simul) | **Not yet** | Live: `ProcessPlayerRegistered` at cutover |
| SCH-014 | `014_player_play_streaks.sql` | Streaks + HoF cols | Yes | **Done** (simul) | **Not yet** | Streaks UI proven on `kooldb1` |
| SCH-015 | `015_drop_kungfu_columns.sql` | DROP KungFu cols | Yes | **Done** (prepare) | **Not yet** | Coordinate if prod C++ still writes cols |
| SCH-016 | `016_drop_playertable_recent_average_rating.sql` | DROP `RecentAverageRating` | Yes | **Done** (prepare) | **Not yet** | Retire C++ reference at cutover |
| SCH-017 | `017_drop_player_monthly_league.sql` | DROP legacy monthly league table | Yes | **Done** (migrate-work) | **Not yet** | |
| SCH-018 | `018_playertable_milestone_streak_facilitators.sql` | P6 facilitator columns on `playertable` | Yes | **Done** (migrate-work + simul) | **Not yet** | |
| SCH-019 | `019_player_matchup_summary_opponents_ext.sql` | `player_matchup_summary` goal extremes + DD/CS | Yes | **Done** (migrate + simul to 500 on work) | **Not yet** | Opponents Phase 3 — [`player-opponents-hub.md`](../player-opponents-hub.md) |
| SCH-020 | `020_player_milestone_totals.sql` | `player_milestone_totals` per-player tier counts | Yes | **Done** (migrate-work + parity on work) | **Not yet** | Meta LB + profile hero; bump via `milestone_unlock.php` — [`milestones-unlock-librarian.md`](../milestones-unlock-librarian.md) |
| SCH-021 | `021_milestone_definitions_holder_count.sql` | `milestone_definitions.holder_count` catalog aggregate (all unlock rows) | Yes | **Done** (migrate-work + parity on work) | **Not yet** | DDL only; bump per unlock; lobby rebuild after bulk seed — [`milestones-unlock-librarian.md`](../milestones-unlock-librarian.md) |

### Column legend

| Column | Meaning |
|--------|---------|
| **In ops package** | File committed under `ops/sql/migrations/` |
| **`kooldb1` / work proof** | Applied via **migrate-work** and/or populated via **ops simul** on work DB — **prep complete** |
| **Live prod executed** | Steve ran migrate (and cutover simul if needed) on **live** ladder DB — **go-live only** |

**“Not yet” on live prod is not repo backlog.** See [`cutover-readiness.md`](cutover-readiness.md).

### Adding a row

1. Add `site/public_html/ops/sql/migrations/NNN_short_name.sql`.
2. Add a line here; set **In ops package** = Yes.
3. After **migrate-work + simul** on `kooldb1` / `ko2unity_work`, set **`kooldb1` / work proof** = Done.
4. After Steve cutover on live, set **Live prod executed** = Done (date in Notes).
