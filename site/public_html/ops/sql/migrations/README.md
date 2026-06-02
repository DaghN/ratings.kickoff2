# SQL migrations mirror (staging)

**Canonical source in git:** [`schema/migrations/`](../../../../../schema/migrations/) at repo root.

When adding a migration:

1. Add `schema/migrations/NNN_….sql` (registers + local `schema/apply_local.ps1`).
2. Copy the same file here before WinSCP sync so Steve / staging CLI can run `mysql … < ops/sql/migrations/NNN_….sql`.

Until a file is copied here, staging may lag local schema — check [`docs/coordination/schema-register.md`](../../../../../docs/coordination/schema-register.md).
