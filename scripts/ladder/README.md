# Ladder replay (Python v1)

Recalculates Elo on local **`ko2unity_db`** per **`docs/replay-v1-scope-and-reset.md`**.

## Setup

```bash
pip install -r scripts/ladder/requirements.txt
```

**Database:** **`ko2unitydb_config.php`** (local: `site/config/`; server: `config/` beside `public_html`, or `../config/` when run from `public_html/scripts/ladder/`). Optional **`ladder.ini`** via `--ini`. **Staging one-shot:** **`docs/STAGING_REPLAY.md`**.

## Commands (from repo root)

```bash
# Safe first check — logs SQL and sample math, no writes
python -m scripts.ladder run --dry-run

# Reset derived columns + full replay (~74k games, full playertable rebuild)
python -m scripts.ladder run

# Steps separately
python -m scripts.ladder reset
python -m scripts.ladder replay

# Smoke test: reset + first 100 games only
python -m scripts.ladder run --limit 100
```

**Recovery:** re-import `data/dumps/ko2unity_db-2026-05-20.sql` if needed (`data/README.md`).

## Defaults

- K = 32, starting rating = 1600, no decay
- Order: `Date ASC`, `id ASC`
- Database allowlist: `ko2unity_db` (local), `kooldb` (staging/dev — whatever `$database` is in PHP config)

**v2 replay** also rebuilds career stats on `playertable` (extremes, streaks, victim/culprit counts, `*GameID`, etc.) and rebuilds `generalstatstable` row `id=1` at the end.

**`generalstatstable`:** DDL in `scripts/ladder/sql/generalstatstable.sql` (from `docs/generalstatstable-schema.md`). `reset` / `run` create the table and seed `id=1` if missing, NULL the row on reset, then fill it after replay. Staging DB name `kooldb` is allowlisted when `$database` in PHP config is `kooldb`.
