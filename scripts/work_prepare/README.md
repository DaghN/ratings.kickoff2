# Work DB prepare — platform v2 (legacy Python)

Canonical doc: [`docs/work-db-prepare.md`](../../docs/work-db-prepare.md)  
Standards: [`docs/OPS_STANDARDS.md`](../../docs/OPS_STANDARDS.md)

**Preferred (PHP, no dispatch.php):** [`site/public_html/ops/run_prepare.php`](../../site/public_html/ops/run_prepare.php)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
php site/public_html/ops/run_prepare.php prepare --target local-work
php site/public_html/ops/run_prepare.php parity --target local-work
```

**Legacy Python CLI** (kept for reference; same verbs):

```powershell
python -m scripts.work_prepare prepare --target local-work
python -m scripts.work_prepare parity --target local-work

# Post-game Mode A parity (after P1+ PHP changes)
python -m scripts.work_prepare ab-post-game --target local-work --limit 100
```

**`ab-post-game`** (see [`docs/post-game-php-development.md`](../docs/post-game-php-development.md) §8.3): zero-derived (default) → PHP `replay-to` → `verify_ratedresults_derived_rows.py` → snapshot tables → Python `ladder run` → diff shipped layers (`--phase p5` = layers 1–5). Use `--full-prepare` when refresh/migrate needed.

Optional config: copy `site/config/work-targets.ini.example` → `work-targets.ini`.

**Legacy scripts** (kept for parity reference):

| Legacy | v2 |
|--------|-----|
| `reset_local_work_db.ps1` | `refresh-work` |
| `apply_schema_to_work.ps1` | `migrate-work` |
| `python -m scripts.ladder reset --target sandbox` | `zero-derived` |

Requires: Laragon MySQL + PHP; Python only if using legacy CLI or ladder sim.
