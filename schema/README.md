# Schema migrations

Numbered SQL for **ko2unity_db** (local) and server DB handoffs. Tracked in **`docs/coordination/schema-register.md`**.

## Layout

```text
schema/
  migrations/
    001_ratedresults_player_indexes.sql
  apply_local.ps1          # Laragon — applies all migrations in order
  README.md
```

**Not here:** `scripts/ladder/sql/generalstatstable.sql` — applied by ladder replay `reset`/`run` (register SCH-002).

## Apply locally (Windows + Laragon)

From repo root:

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

Options: `-Database ko2unity_db` `-User root` `-Password ''`. The local wrapper refuses other DB names unless `-AllowNonLocal` is explicitly passed for a reviewed one-off.

Requires Laragon MySQL running (`docs/LOCAL_DEV.md`).

## Apply on staging / production

Steve runs the **same files** in numeric order against the intended server DB. Include paths in [cutover packet](../docs/coordination/cutover-packet-template.md).

**Staging:** no live game writes — schema + replay are how the DB catches up (`docs/prod-coordination.md`).

## Adding a migration

1. Next number: `NNN_short_description.sql`
2. Prefer idempotent DDL (`IF NOT EXISTS`, safe `ALTER`).
3. Register row in `docs/coordination/schema-register.md`
4. Run `apply_local.ps1`; commit SQL + register update.

## Related

- `scripts/apply_ratedresults_player_indexes.ps1` — local compat wrapper that applies only `001_ratedresults_player_indexes.sql`
- `docs/prod-coordination.md` — cutover order
