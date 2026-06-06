# Amiga realm scripts

Phase A0+ tooling for the offline Access source (`data/amiga/source/koatd.mdb`).

## Requirements

- Windows with **Microsoft Access Driver** (`*.mdb`, `*.accdb`) — usually installed with Office or Access Database Engine.
- Python 3 + `pyodbc` (already used elsewhere in the repo; `pip install pyodbc` if needed).

## Commands (repo root)

```powershell
# One-shot local build (create DB, import, Elo replay)
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Or step by step (import alone clears derived tables — always replay before browsing):
python -m scripts.amiga import --recreate-schema
python -m scripts.amiga replay

# Schema inventory from Access
python scripts/amiga/discover_access_schema.py

# SQL dump for staging (after local ko2amiga_db is ready)
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

Export writes **16 part files** + `ko2amiga_manifest.json` under `site/public_html/amiga/_import/` (plus optional full `ko2amiga_db.sql`). Sync all of `_import/` via WinSCP.

**Staging refresh:** WinSCP sync `public_html/`, then browser import (verified Jun 2026, A2 schema):

- **Preview:** `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` — confirm `parts: 16`
- **Apply:** same URL with `&apply=1&part=1` (auto-continues)
- **Local dry-run:** `http://ratingskickoff.test/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`

Script: `site/public_html/amiga/run_import_ko2amiga.php` · payload: `site/public_html/amiga/_import/`

Browser (local pages):

- `http://ratingskickoff.test/amiga/rating.php`
- `http://ratingskickoff.test/amiga/profile.php?id=1`
- `http://ratingskickoff.test/amiga/games.php?id=1`

Staging pages: `https://ratings.kickoff2.com/amiga/…` — DB config in `site/config/ko2amiga_config.local.php`; handoff [`docs/amiga-staging-handoff.md`](../../docs/amiga-staging-handoff.md).

Profile template: [`docs/amiga-profile-v0.md`](../../docs/amiga-profile-v0.md)

## Docs

- **Data contract:** [`docs/amiga-data-contract.md`](../../docs/amiga-data-contract.md) — ground / derived / reference layers
- Discovery write-up: [`docs/amiga-schema-discovery.md`](../../docs/amiga-schema-discovery.md)
- Source layout: [`data/amiga/README.md`](../../data/amiga/README.md)
