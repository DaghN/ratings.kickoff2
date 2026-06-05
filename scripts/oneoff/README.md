# One-off scripts

**Policy:** Use [replay](../../docs/coordination/replay-register.md) for history-derived data. One-offs are for imports, fixes, or experiments.

**Register every script** in [`docs/coordination/one-off-register.md`](../../docs/coordination/one-off-register.md) before asking Steve to run anything on staging/prod.

## Workflow

1. Copy `_template.py` → `your_task_name.py`
2. Implement `main(dry_run: bool)` — **no writes** when `dry_run=True`
3. Register row **OO-…** with purpose and environments
4. Local: `python scripts/oneoff/your_task_name.py --dry-run` then without dry-run
5. Staging/prod: send Steve the command + dry-run output + before/after counts

## Database config

Same as ladder replay: `site/config/ko2unitydb_config.php` (local) or server `config/ko2unitydb_config.php`. Use `load_db_config()` and `engine.connect()` from `scripts/ladder/` (see `_template.py`).

Allowlist: `ko2unity_db`, `ko2unity_work`, `ko2unity_baseline`, `kooldb1`, `kooldb2`, and legacy `kooldb` (frozen). Forward staging work = **`kooldb1`** — see [`docs/coordination/database-copies-2026-06.md`](../../docs/coordination/database-copies-2026-06.md).

## Related

- `docs/prod-coordination.md`
- `scripts/ladder/README.md`
