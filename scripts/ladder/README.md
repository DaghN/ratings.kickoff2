# scripts.ladder — deprecated shim

**Use [`scripts/k2_rating_core/`](../k2_rating_core/)** for all new code.

This package retains:

| Path | Role |
|------|------|
| `__init__.py` | Re-exports `k2_rating_core` (transitional) |
| `__main__.py` | Retired CLI stub (exit 1) |
| `sql/generalstatstable.sql` | GST DDL (sync with `ops/sql/generalstatstable.sql`) |
| `sql/archive/*/README.md` | Pointers to archived batch SQL |

Retired replay implementation: [`docs/archive/ladder-retired-2026-06/`](../../docs/archive/ladder-retired-2026-06/).
