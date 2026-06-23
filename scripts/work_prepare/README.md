# Work DB prepare — Python package (retired CLI)

**Status:** **CLI retired Jun 2026** (obsolete dev scripts retirement, slice 3).

**Canonical prepare path (PHP only):**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
powershell -ExecutionPolicy Bypass -File scripts\refresh_local_work_db.ps1

php site/public_html/ops/run_prepare.php prepare --target local-work
php site/public_html/ops/run_prepare.php refresh-work --target local-work
php site/public_html/ops/run_prepare.php zero-derived --target local-work
php site/public_html/ops/run_prepare.php parity --target local-work
```

Docs: [`docs/work-db-prepare.md`](../../docs/work-db-prepare.md) · [`site/public_html/ops/README.md`](../../site/public_html/ops/README.md)

---

## What remains in this folder

| File | Role |
|------|------|
| `paths.py` | Laragon `mysql` / `mysqldump` discovery — **used by `scripts.amiga.export_packs`** |
| `__main__.py` | Retired CLI stub (exit 1 → use PHP) |

---

## Archived (Jun 2026)

Full Python prepare implementation + PHP-vs-Python A/B oracle:

[`docs/archive/work-prepare-retired-2026-06/`](../../docs/archive/work-prepare-retired-2026-06/)

Includes `ab-post-game` (spawned retired `scripts.ladder run`) — historical only.

**Sign-off:** `run_ops_sim.php` + `run_verify_ops_sim.php` — [`docs/coordination/cutover-readiness.md`](../../docs/coordination/cutover-readiness.md).
