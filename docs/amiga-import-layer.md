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
| [`tournament_structure/`](../scripts/amiga/tournament_structure/) | Version-controlled structure specs (`StructureSpec` → stages/fixtures); `apply_structure_spec()` hook during import |
| [`import_access.py`](../scripts/amiga/import_access.py) | Calendar-first sort, continuous same-day `game_date`, player/tournament insert order |

### Manual (explicit overrides)

| Module | What it does |
|--------|----------------|
| [`import_corrections.py`](../scripts/amiga/import_corrections.py) | Known-wrong Access catalog fields — **one row per tournament**, with rationale; **World Cup host city + country** (retire Access `WC` placeholder); **supplemental Scores rows** when Access catalog exists but games are missing |

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
| `transforms.score_supplements` | Games appended from external evidence (tournament, count, reason) |
| `transforms.structure_specs` | Registered structure specs applied (or skipped) during import |
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
python -m scripts.amiga audit-suspicious-marathons
```

After a fresh `koatd` drop: `python -m scripts.amiga run --recreate-schema` then verify commands above.

### Tournament structure specs

Registered specs live in `scripts/amiga/tournament_structure/` (`StructureSpec`, `StageSpec`, `FixtureSpec`, `GroupRosterSpec`). Import calls `apply_structure_spec()` after scores are prepared and before games are inserted. Slice A ships an empty registry (no-op); Slice B+ backfills events like Homburg from forum evidence.

Pre-backfill audit — tournaments in Access with **all phases NULL** and **uneven game counts** or **not a full round-robin**:

```powershell
python -m scripts.amiga audit-suspicious-marathons
python -m scripts.amiga audit-suspicious-marathons --out data/amiga/exports/suspicious_marathons.json
```

Pilot candidate **Homburg** (33 players, 86 games, all Phase NULL) should appear in this report. Rows include `structure_spec_status` (`null`, `stub`, or `active`) when registered in `tournament_structure/registry.py`.

```powershell
python -m scripts.amiga structure list
python -m scripts.amiga structure verify --tournament "Homburg"
```

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
| World Cup 2015 | `name` | World Cup 2015 | **World Cup XV (Dublin)** | Chrono 548 between XIV and XVI; Access `Scores` / catalog use year label; reference groups already `World Cup XV Tables` |
| World Cup VIII | `event_date` | 2008-09-08 | **2008-11-09** | Chrono 325 between Newent XIV (Nov 3) and Helsingborg (Nov 14); real event 9 Nov 2008 |
| Wiesbaden IX | `event_date` | 2009-04-07 | **2009-01-25** | Chrono 333 before Wiesbaden X (Feb 22); Access April date breaks IX-before-X order. Source: [KO Gathering forum](https://ko-gathering.com/forum/viewtopic.php?p=247684#p247684) |

Newent XVI (2009-02-13, chrono 334) needs no override — it sits correctly between corrected Wiesbaden IX (Jan 25) and Wiesbaden X (Feb 22).

### World Cup host city + country (manual)

Access `[Tournament players]` labels every World Cup with `Country = 'WC'` and a bare Roman name (`World Cup I` … `World Cup XXIII`). Canonical catalog appends the host city and sets the real nation. Defined in `WORLD_CUP_VENUES` in `import_corrections.py`; `tournament_names.py` maps bare Scores strings to the suffixed catalog names.

| World Cup | City | Country |
|-----------|------|---------|
| I | Dartford | England |
| II | Athens | Greece |
| III | Groningen | Netherlands |
| IV | Milan | Italy |
| V | Cologne | Germany |
| VI | Rickmansworth | England |
| VII | Rome | Italy |
| VIII | Athens | Greece |
| IX | Voitsberg | Austria |
| X | Dusseldorf | Germany |
| XI | Birmingham | England |
| XII | Milan | Italy |
| XIII | Voitsberg | Austria |
| XIV | Copenhagen | Denmark |
| XV | Dublin | Ireland |
| XVI | Milan | Italy |
| XVII | Landskrona | Sweden |
| XVIII | Bournemouth | England |
| XIX | Bremen | Germany |
| XX | Athens | Greece |
| XXI | Torremolinos | Spain |
| XXII | Nottingham | England |
| XXIII | Milan | Italy |

### Supplemental Scores (manual)

| Tournament | Games added | Reason |
|------------|-------------|--------|
| Rodenbach II | **10** | Access catalog row (2012-08-12, 5 players) but zero `Scores` rows; complete round-robin from KO Gathering forum thread |

Supplemental rows use reserved `source_scores_id` ≥ `500_000_000` (see `IMPORT_SUPPLEMENT_SCORES_ID_BASE` in `import_corrections.py`).

### Catalog splits (manual, append-only)

When Access has one `[Tournament players]` row but Scores uses a separate `Tournament` label for a second competition, add an `IMPORT_CATALOG_SPLITS` entry in `import_corrections.py`. Import **appends** the synthetic row at the end of the catalog insert (MySQL id **604+** after Jun 2026 bootstrap); never insert in the middle. Remove the merge alias from `tournament_names.py` so Scores route to the new catalog name.

| Tournament | Parent | `source_id` | Reason |
|------------|--------|-------------|--------|
| Groningen VII Cup | Groningen VII (id **48**) | `900_000_001` | Access catalog row only for main event; 14 cup games under separate Scores label (2002-07-13). Child appends as id **604**. |
| Gloucester III Team | Gloucester III (id **62**) | `900_000_002` | Same-day 10-player event; 90g double RR on parent label + 10g under Team label (Scores IDs 1411–1420). Child appends as id **605**. |

Synthetic catalog rows use reserved `tournaments.source_id` ≥ `900_000_000` (see `IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE`).

### Scores tournament aliases (automatic)

| Access `Scores.Tournament` | Canonical parent | Reason |
|----------------------------|------------------|--------|
| Milan X Final, Quarter Finals, Semi Finals, Round 1 Group A/B, 3rd Place Final | **Milan X** | KO stages stored as separate Scores tournament strings; each fragment has matching `Phase`. Merge to parent; `resolve_phase()` infers phase from fragment name when needed. **Not an import split.** |
| World Cup V KOA Cup | **World Cup V (Cologne)** | 2005 Cologne KOA Cup = consolation bracket within WC V (Alkis matches5; Access `World Cup V Tables` Groups I–L). WC IV uses `KOA Cup - …` phases under the parent; 2005 wrongly split into a second catalog row. Catalog row skipped on import; phases prefixed `KOA Cup - …`. |

See `TOURNAMENT_ALIAS_RATIONALE` in `tournament_names.py`.

---

## Not import’s job

- Elo, standings, career stats (replay / post-game)
- Chart display workarounds
- Editing online realm data
- Importing Access reference tables into the website hot path
