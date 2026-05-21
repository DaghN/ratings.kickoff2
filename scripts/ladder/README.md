# Ladder replay (Python v1)

Recalculates Elo on local **`ko2unity_db`** per **`docs/replay-v1-scope-and-reset.md`**.

## Setup

```bash
pip install -r scripts/ladder/requirements.txt
```

Copy **`site/config/ladder.ini.example`** → **`site/config/ladder.ini`** (gitignored).

## Commands (from repo root)

```bash
# Safe first check — logs SQL and sample math, no writes
python -m scripts.ladder run --dry-run

# Reset derived columns + full replay (~74k games)
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
- Database allowlist: `ko2unity_db` only
