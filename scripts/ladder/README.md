# Ladder replay (Python v1)

Recalculates Elo per **`docs/replay-v1-scope-and-reset.md`**. Local **dev** = `ko2unity_db`; **prod sandbox** = `ko2unity_work` (see **`docs/coordination/database-copies-2026-06.md`**).

## Setup

```bash
pip install -r scripts/ladder/requirements.txt
```

**Database:** **`ko2unitydb_config.php`** → dev `ko2unity_db`. **Sandbox:** copy `site/config/ladder-work.ini.example` → `ladder-work.ini`, use `--target sandbox --ini site/config/ladder-work.ini`. **Staging:** **`docs/STAGING_REPLAY.md`**.

## Commands (from repo root)

### Dev (`ko2unity_db`)

```bash
python -m scripts.ladder run --target local --dry-run
python -m scripts.ladder run --target local
```

### Prod sandbox (`ko2unity_work` — destructive to work only)

```bash
python -m scripts.ladder run --target sandbox --ini site/config/ladder-work.ini --dry-run
python -m scripts.ladder run --target sandbox --ini site/config/ladder-work.ini
```

Reset work from baseline: `powershell -File scripts\reset_local_work_db.ps1`

```bash
python -m scripts.ladder reset --target local
python -m scripts.ladder replay --target local
python -m scripts.ladder run --target local --limit 100
powershell -ExecutionPolicy Bypass -File scripts\rebuild_player_period_games_local.ps1
```

**Recovery:** re-import `data/dumps/ko2unity_db-2026-05-20.sql` if needed (`data/README.md`).

## Defaults

- K = 32, starting rating = 1600, no decay
- Order: `Date ASC`, `id ASC`
- Database allowlist includes `ko2unity_db`, `ko2unity_work`, `ko2unity_baseline`, `kooldb`. Targets: `local`, `sandbox`, `staging`.

**v2 replay** also rebuilds career stats on `playertable` (extremes, streaks, victim/culprit counts, `*GameID`, etc.) and rebuilds `generalstatstable` row `id=1` at the end.

**Server records:** non-ratio hall-of-fame rows via `server_records.py` (strict `>` on ties). **Ratio leaders** are **not** written to `generalstatstable` — Records page queries `playertable` (`site/public_html/includes/records_ratio_leaders.php`). Steve: `docs/coordination/records-post-game-exception.md`.

**`generalstatstable`:** DDL in `scripts/ladder/sql/generalstatstable.sql` (from `docs/generalstatstable-schema.md`). `reset` / `run` create the table and seed `id=1` if missing, NULL the row on reset, then fill it after replay. Staging DB name `kooldb` is allowlisted when `$database` in PHP config is `kooldb`.

**`player_period_games`:** Rebuilt from `ratedresults` by `scripts/ladder/sql/player_period_games_rebuild.sql` (or `scripts/rebuild_website_derived_data_local.ps1`). Production live maintenance: contract post-game § — not per-table snippet packs in repo.
