# Amiga realm scripts

Phase A0+ tooling for the offline Access source (`data/amiga/source/koatd.mdb`).

## Requirements

- Windows with **Microsoft Access Driver** (`*.mdb`, `*.accdb`) — usually installed with Office or Access Database Engine.
- Python 3 + `pyodbc` (already used elsewhere in the repo; `pip install pyodbc` if needed).

## Commands (repo root)

```powershell
# One-shot local build (create DB, import, Elo replay)
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Or step by step:
python -m scripts.amiga import --recreate-schema
python -m scripts.amiga replay

# Schema inventory from Access
python scripts/amiga/discover_access_schema.py

# SQL dump for Steve
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

Browser:

- `http://ratingskickoff.test/amiga/rating.php`
- `http://ratingskickoff.test/amiga/profile.php?id=1`
- `http://ratingskickoff.test/amiga/games.php?id=1`

Profile template: [`docs/amiga-profile-v0.md`](../../docs/amiga-profile-v0.md)

## Docs

- Discovery write-up: [`docs/amiga-schema-discovery.md`](../../docs/amiga-schema-discovery.md)
- Source layout: [`data/amiga/README.md`](../../data/amiga/README.md)
