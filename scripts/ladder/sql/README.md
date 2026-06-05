# `scripts/ladder/sql/`

| Path | Role |
|------|------|
| **`generalstatstable.sql`** | Core ladder DDL — used by `scripts/ladder/schema.py` on replay reset/run |
| **`archive/batch-2026-05/`** | Legacy batch `*_rebuild.sql` — **repair / Python oracle only** — see README there |
| **`archive/one-off-2026-06/`** | Historical surgical SQL (frozen `kooldb` era) — superseded by PHP `FinalizeUtcDay` |

**Cutover / work DB:** [`docs/coordination/cutover-readiness.md`](../../docs/coordination/cutover-readiness.md) — use **ops simul**, not batch SQL on prod.
