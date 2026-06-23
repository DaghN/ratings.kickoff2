# work_prepare — archived Python (Jun 2026)

Retired during [obsolete dev scripts retirement](../obsolete-dev-scripts-retirement-policy.md) **slice 3**.

**Replacement:** `php site/public_html/ops/run_prepare.php` — see [`work-db-prepare.md`](../work-db-prepare.md).

## Contents

| Module | Was |
|--------|-----|
| `prepare.py`, `refresh.py`, `migrate.py`, `zero_derived.py`, … | Python prepare platform v2 (superseded by PHP `run_prepare.php`) |
| `ab_post_game.py` + `ab_*.py` | Archived dev A/B — PHP replay slice vs retired `scripts.ladder run` |
| `constants.py` | Aggregate truncate lists — now `ops/includes/ops_prepare_constants.php` |

## Active remnant

`scripts/work_prepare/paths.py` remains for Amiga `export_packs.py` (`find_mysqldump_exe`).

Do not restore `python -m scripts.work_prepare` as a runbook step.
