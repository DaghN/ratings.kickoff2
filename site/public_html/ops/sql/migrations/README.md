# SQL migrations (canonical)

Numbered SCH DDL for work DB prepare. **Register:** [`docs/coordination/schema-register.md`](../../../../docs/coordination/schema-register.md)  
**Design:** [`docs/coordination/ops-schema-migrations.md`](../../../../docs/coordination/ops-schema-migrations.md)

## Apply

```text
php site/public_html/ops/run_prepare.php migrate-work --target local-work
```

Part of full prepare: `run_prepare.php prepare --target local-work`.

Files are applied in **sorted filename order**, re-run every time (must be **idempotent**). Session uses `SET time_zone = '+00:00'`.

## Adding a migration

1. Add `NNN_short_name.sql` here (idempotent where possible).
2. Add SCH row in `schema-register.md`.
3. Run `migrate-work` on `ko2unity_work` (or full `prepare`).

**Not here:** REP rebuild SQL → `scripts/ladder/sql/`; catalog seed → `data/` + `seed-catalog`.
