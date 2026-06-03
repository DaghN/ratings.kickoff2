# Schema migrations (relocated)

**Canonical location:** [`site/public_html/ops/sql/migrations/`](../../site/public_html/ops/sql/migrations/)

Apply on work DB:

```text
php site/public_html/ops/run_prepare.php migrate-work --target local-work
```

Legacy Windows wrapper (same SQL files):

```text
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1 -Database ko2unity_work
```

Design: [`docs/coordination/ops-schema-migrations.md`](../docs/coordination/ops-schema-migrations.md) · register: [`docs/coordination/schema-register.md`](../docs/coordination/schema-register.md)
