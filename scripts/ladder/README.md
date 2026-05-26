# Ladder replay (Python v1)

Recalculates Elo on local **`ko2unity_db`** per **`docs/replay-v1-scope-and-reset.md`**. Prod/staging coordination: **`docs/prod-coordination.md`**.

## Setup

```bash
pip install -r scripts/ladder/requirements.txt
```

**Database:** **`ko2unitydb_config.php`** (local: `site/config/`; server: `config/` beside `public_html`, or `../config/` when run from `public_html/scripts/ladder/`). Optional **`ladder.ini`** via `--ini`. **Staging one-shot:** **`docs/STAGING_REPLAY.md`**.

## Commands (from repo root)

```bash
# Safe first check — logs SQL and sample math, no writes
python -m scripts.ladder run --target local --dry-run

# Reset derived columns + full replay (~74k games, full playertable rebuild)
python -m scripts.ladder run --target local

# Steps separately
python -m scripts.ladder reset --target local
python -m scripts.ladder replay --target local

# Rebuild player period activity aggregate (SQL wrapper for local)
powershell -ExecutionPolicy Bypass -File scripts\rebuild_player_period_games_local.ps1

# Smoke test: reset + first 100 games only
python -m scripts.ladder run --target local --limit 100
```

**Recovery:** re-import `data/dumps/ko2unity_db-2026-05-20.sql` if needed (`data/README.md`).

## Defaults

- K = 32, starting rating = 1600, no decay
- Order: `Date ASC`, `id ASC`
- Database allowlist: `ko2unity_db` (local) and `kooldb` (staging). Local can be inferred for `ko2unity_db`; `kooldb` requires `--target staging`.

**v2 replay** also rebuilds career stats on `playertable` (extremes, streaks, victim/culprit counts, `*GameID`, etc.) and rebuilds `generalstatstable` row `id=1` at the end.

**Server records:** non-ratio hall-of-fame rows via `server_records.py` (strict `>` on ties). **Ratio leaders** are **not** written to `generalstatstable` — Records page queries `playertable` (`site/public_html/includes/records_ratio_leaders.php`). Steve: `docs/coordination/records-post-game-exception.md`.

**`generalstatstable`:** DDL in `scripts/ladder/sql/generalstatstable.sql` (from `docs/generalstatstable-schema.md`). `reset` / `run` create the table and seed `id=1` if missing, NULL the row on reset, then fill it after replay. Staging DB name `kooldb` is allowlisted when `$database` in PHP config is `kooldb`.

**`player_period_games`:** Rebuilt from `ratedresults` by `scripts/ladder/sql/player_period_games_rebuild.sql` (or `scripts/rebuild_website_derived_data_local.ps1`). Production live maintenance: contract post-game § — not per-table snippet packs in repo.
