# Ladder replay (Python v1)

Recalculates Elo per **`docs/replay-v1-scope-and-reset.md`**. Local **dev** = `ko2unity_db`; **prod sandbox** = `ko2unity_work` (see **`docs/coordination/database-copies-2026-06.md`**).

## Setup

```bash
pip install -r scripts/ladder/requirements.txt
```

**Database:** **`ko2unitydb_config.php`** → dev `ko2unity_db`. **Sandbox:** copy `site/config/ladder-work.ini.example` → `ladder-work.ini`, use `--target sandbox --ini site/config/ladder-work.ini`. **Website aggregates on staging/work:** ops simul ([`docs/coordination/cutover-readiness.md`](../../docs/coordination/cutover-readiness.md)) — not [`docs/STAGING_REPLAY.md`](../../docs/STAGING_REPLAY.md) (archive stub).

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

- K = 32, starting rating = 1600
- Order: `Date ASC`, `id ASC`
- Database allowlist includes `ko2unity_db`, `ko2unity_work`, `ko2unity_baseline`, `kooldb1`, `kooldb2`, and legacy `kooldb`. Targets: `local`, `sandbox`, `staging`. Forward staging work DB = **`kooldb1`** ([`docs/coordination/database-copies-2026-06.md`](../../docs/coordination/database-copies-2026-06.md)).

**v2 replay** also rebuilds career stats on `playertable` (extremes, streaks, victim/culprit counts, `*GameID`, etc.) and rebuilds `generalstatstable` row `id=1` at the end.

**Server records:** non-ratio hall-of-fame rows via `server_records.py` (strict `>` on ties). **Ratio leaders** are **not** written to `generalstatstable` — Records page queries `playertable` (`site/public_html/includes/records_ratio_leaders.php`). Steve: `docs/coordination/records-post-game-exception.md`.

**`generalstatstable`:** DDL in `scripts/ladder/sql/generalstatstable.sql` (from `docs/generalstatstable-schema.md`). `reset` / `run` create the table and seed `id=1` if missing, NULL the row on reset, then fill it after replay. Website aggregates on staging/work use **ops simul**, not ladder `run --target staging` alone.

**Period activity (P4):** After replay, `period_activity.py` rebuilds `player_period_games` / peaks from rows with `NewRatingA IS NOT NULL`.

**Period aggregates (P5):** At end of `run`, optional batch SQL from `sql/archive/batch-2026-05/` — **repair/oracle only**; cutover uses **ops simul**.

Production live maintenance: contract post-game § — PHP per-game path in `ops/run_process_game.php`.
