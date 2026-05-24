# Schema register

SQL files live in **`schema/migrations/`** (numbered, apply in order). Local: `schema/apply_local.ps1`. Staging/prod: Steve runs the same files against `kooldb`.

**Note:** Steve reported the game server may **auto-add columns** it knows about. This register still documents **indexes, new tables, and website-driven schema** we rely on, plus what replay/post-game expect.

| ID | Migration | Description | Local | Staging | Prod | Replay depends? | Notes |
|----|-----------|-------------|-------|---------|------|-----------------|-------|
| SCH-001 | `001_ratedresults_player_indexes.sql` | Indexes `idx_ratedresults_idA`, `idx_ratedresults_idB` on `ratedresults` | Done | Done | **Pending** | No | Profile load ~8s→~1s for heavy players; see `PROJECT_MEMORY.md` |
| SCH-002 | `scripts/ladder/sql/generalstatstable.sql` | Create `generalstatstable` + seed row `id=1` | Via replay | Via replay | Exists on prod | Yes (batch rebuild) | Not duplicated in `schema/migrations/` — owned by ladder replay DDL |

### Adding a row

1. Add `schema/migrations/NNN_short_name.sql` (idempotent where possible: `IF NOT EXISTS`).
2. Add a line to this table.
3. Run `schema/apply_local.ps1` on `ko2unity_db`.
4. After Steve applies on staging/prod, set Staging/Prod to **Done** and note date in Notes.

### Status legend

**Pending** · **Done** · **N/A** · **Blocked** (waiting on Steve)
