# Amiga import layer

**Purpose:** Define how legacy Microsoft Access (`koatd.mdb`) becomes canonical Amiga **ground truth** in `ko2amiga_db`. Import is the **only** place we normalize, correct, or reinterpret archival data before replay and the website see it.

**Related:** [`amiga-data-contract.md`](amiga-data-contract.md) (layers + chronology) · [`scripts/amiga/README.md`](../scripts/amiga/README.md) (commands)

---

## Data flow

```
koatd.mdb (archival input — what KOA ships)
    │
    ▼  import_access.py + helper modules
    │     • automatic transforms (names, tournament aliases, chronology)
    │     • manual catalog overrides (import_corrections.py)
    │     • writes import_manifest.json
    ▼
MySQL ground truth (tournaments, amiga_games, amiga_players)
    │
    ▼  replay / post-game ops
derived truth (ratings, stats, standings)
```

**Do not edit `koatd.mdb` as the primary fix** for known Access bugs. Fresh drops from KOA would undo local edits. Corrections belong in the import layer (version-controlled, reproducible).

---

## Layer alignment

| Layer | Role |
|-------|------|
| **Archival input** | `data/amiga/source/koatd.mdb` — read-only for import |
| **Import transforms** | Documented rules in `scripts/amiga/` |
| **Ground truth** | MySQL tables after import (or future live submission) |
| **Derived truth** | Replay from ground in `game_date, id` order — never reads Access |
| **Reference truth** | Access `Tables` / `Rankings` — parity tooling only |

Ground truth is **not** “whatever Access says.” It is what we commit to MySQL after import.

---

## Transform registry

### Automatic (policy rules)

| Module | What it does |
|--------|----------------|
| [`player_names.py`](../scripts/amiga/player_names.py) | Collapse spacing / case duplicates (`Oliver ST` → `Oliver St`) |
| [`tournament_names.py`](../scripts/amiga/tournament_names.py) | Map Scores tournament strings to catalog parents (Milan X fragments, cup aliases, WC V KOA Cup → World Cup V) |
| [`tournament_format.py`](../scripts/amiga/tournament_format.py) | Seed format templates and infer non-exclusive `has_league` / `has_cup` catalog flags from canonical phases |
| [`import_access.py`](../scripts/amiga/import_access.py) | Calendar-first sort, continuous same-day `game_date`, player/tournament insert order |

### Manual (explicit overrides)

| Module | What it does |
|--------|----------------|
| [`import_corrections.py`](../scripts/amiga/import_corrections.py) | Known-wrong Access catalog fields — **one row per tournament**, with rationale |

Add manual overrides only when:

- Evidence shows Access is wrong (chrono neighbourhood, external history, etc.)
- Upstream (`koatd`) is unlikely to fix soon
- The override is documented in `OVERRIDE_RATIONALE`

---

## Audit outputs

Each import writes **`data/amiga/exports/import_manifest.json`** (gitignored; regenerate on import):

| Field | Content |
|-------|---------|
| `source` | MDB path, size, modified time |
| `stats` | tournaments, games, raw vs canonical player counts, merge groups |
| `transforms.name_merges` | Same detail as legacy `name_merges.json` |
| `transforms.catalog_overrides` | Applied manual patches (access vs canonical + reason) |
| `registry` | Module pointers for reviewers |

Legacy **`name_merges.json`** is still written for backward compatibility; **`import_manifest.json`** is the canonical audit record.

Example shape: [`amiga-import-manifest.example.json`](amiga-import-manifest.example.json)

---

## Commands

```powershell
python -m scripts.amiga import          # ground truth + manifest
python -m scripts.amiga replay            # derived truth
python -m scripts.amiga verify-import-manifest
python -m scripts.amiga verify-chronology
python -m scripts.amiga audit-catalog-dates
```

After a fresh `koatd` drop: `python -m scripts.amiga run --recreate-schema` then verify commands above.

### Catalog date audit

`python -m scripts.amiga audit-catalog-dates` scans raw Access `[Tournament players]` for:

- **Adjacent chrono inversions** — later `Chrono` but earlier `Date` than the previous row (WC VIII / Wiesbaden IX failure mode)
- **Roman-series violations** — venue `N+1` dated before `N` (e.g. Wiesbaden X before IX)

Exits non-zero if a new inversion appears that is **not** covered by `import_corrections.py`. As of Jun 2026, Access has **2** adjacent inversions; both are registered overrides. This does **not** validate every date against external sources — only structural consistency checks.

---

## Agent policy

- **New Access quirk:** prefer automatic rule in the right module; use `import_corrections.py` only for one-off catalog facts.
- **Never** patch derived tables to paper over import issues.
- **Never** fix chart/replay order in JS/PHP while leaving import wrong.
- Document every manual override in this file’s registry table when adding to `import_corrections.py`.

### Current manual overrides

| Tournament | Field | Access value | Canonical | Reason |
|------------|-------|--------------|-----------|--------|
| World Cup 2015 | `name` | World Cup 2015 | **World Cup XV** | Chrono 548 between XIV and XVI; Access `Scores` / catalog use year label; reference groups already `World Cup XV Tables` |
| World Cup VIII | `event_date` | 2008-09-08 | **2008-11-09** | Chrono 325 between Newent XIV (Nov 3) and Helsingborg (Nov 14); real event 9 Nov 2008 |
| Wiesbaden IX | `event_date` | 2009-04-07 | **2009-01-25** | Chrono 333 before Wiesbaden X (Feb 22); Access April date breaks IX-before-X order. Source: [KO Gathering forum](https://ko-gathering.com/forum/viewtopic.php?p=247684#p247684) |

Newent XVI (2009-02-13, chrono 334) needs no override — it sits correctly between corrected Wiesbaden IX (Jan 25) and Wiesbaden X (Feb 22).

### Scores tournament aliases (automatic)

| Access `Scores.Tournament` | Canonical parent | Reason |
|----------------------------|------------------|--------|
| World Cup V KOA Cup | **World Cup V** | 2005 Cologne KOA Cup = consolation bracket within WC V (Alkis matches5; Access `World Cup V Tables` Groups I–L). WC IV uses `KOA Cup - …` phases under the parent; 2005 wrongly split into a second catalog row. Catalog row skipped on import; phases prefixed `KOA Cup - …`. |

See `TOURNAMENT_ALIAS_RATIONALE` in `tournament_names.py`.

---

## Not import’s job

- Elo, standings, career stats (replay / post-game)
- Chart display workarounds
- Editing online realm data
- Importing Access reference tables into the website hot path
