# Schema register

SQL files live in **`schema/migrations/`** (numbered, apply in order). Local: `schema/apply_local.ps1`. Staging/prod: Steve runs the same files against the intended server DB; staging is `kooldb`, production DB identity is Steve-managed and not stored in repo config.

**Note:** Steve reported the game server may **auto-add columns** it knows about. This register still documents **indexes, new tables, and website-driven schema** we rely on, plus what replay/post-game expect.

| ID | Migration | Description | Local | Staging | Prod | Replay depends? | Notes |
|----|-----------|-------------|-------|---------|------|-----------------|-------|
| SCH-001 | `001_ratedresults_player_indexes.sql` | Indexes `idx_ratedresults_idA`, `idx_ratedresults_idB` on `ratedresults` | Done | Done | **Pending** | No | Profile load ~8s→~1s for heavy players; see `PROJECT_MEMORY.md` |
| SCH-002 | `scripts/ladder/sql/generalstatstable.sql` | Create `generalstatstable` + seed row `id=1` | Via replay | Via replay | Exists on prod | Yes (batch rebuild) | Slimmed May 2026 (PG-004): no ratio player leader cols |
| SCH-003 | `002_generalstatstable_drop_ratio_leader_columns.sql` | DROP 28 ratio leader columns on `generalstatstable` | **Applied** (May 2026) | **Pending Steve** | **Pending** | No | With PG-004 PHP + C++; see `docs/RECORDS_PAGE_DATA.md` |
| SCH-004 | `003_player_period_games.sql` | Create `player_period_games` day/month/year activity aggregate table | **Done** | **Done** | **Pending** | Yes (backfill/rebuild) | Staging applied by Steve May 2026; handoff `player-period-games-handoff.md`; post-game PG-005 |
| SCH-005 | `004_status_performance_and_monthly_league.sql` | Add Status performance indexes (`ratedresults.Date`, live `resulttable`) + `player_monthly_league` aggregate table | **Done** | **Done** | **Pending** | Yes (monthly league rebuild) | Staging applied by Steve May 2026; requires REP-004 + PG-006 before prod PHP relies on aggregate truth |
| SCH-006 | `005_period_activity_week_and_peaks.sql` | Add `week` rows to `player_period_games` and create `player_peak_period_games` cache | **Done** | **Pending Steve** | **Pending** | Yes (period + peak rebuild) | Requires updated REP-003 plus REP-005; post-game PG-005 + PG-007 |

### Adding a row

1. Add `schema/migrations/NNN_short_name.sql` (idempotent where possible: `IF NOT EXISTS`).
2. Add a line to this table.
3. Run `schema/apply_local.ps1` on `ko2unity_db`.
4. After Steve applies on staging/prod, set Staging/Prod to **Done** and note date in Notes.

### Status legend

**Pending** · **Done** · **N/A** · **Blocked** (waiting on Steve)
