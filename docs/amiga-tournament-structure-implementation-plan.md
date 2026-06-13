# Amiga tournament structure — implementation plan (agent slices)

**Status:** **Planned** — trio shipped Jun 2026; execution slices 1+ pending.  
**Policy (locked):** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) (standings tally layer — do not re-litigate) · [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) (background; partially superseded)

**In scope:** Collapse `tournament_stages.stage_type` to `round_robin` | `knockout`; align builders, standings fixture resolver, ops PHP; **game-authoritative** legacy fixture materialization; verify CLI; bulk NULL-phase + phase-labeled backfill; optional catalog flags from stages; Steve WC structure reference (later slice).

**Out of scope (defer unless user expands):**

- Live WC **generator** UI / draw-order fixture scheduling for new events (beyond existing builder smoke)
- Full promotion graph interpreter for all ~603 events
- Tournament index / detail UI cutover to stage-backed format labels (optional late slice)
- Staging export / WinSCP
- Online `kooldb*` ladder
- `swiss` stage type (deferred per policy)

**Authority:** policy doc T1–T15. Standings `scope_type` rules remain in standings policy S1–S10.

**Paused work to align:** format-backbone slices A–E + Homburg pilot ([`amiga-format-backbone-orchestration-prompt.md`](amiga-format-backbone-orchestration-prompt.md)) — **do not extend backfill** until slice 1 enum + slice 3 materialize path smoke.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue with the next slice”**.
2. Agent executes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs slice **Verification** before stopping; fix failures before handoff.
4. Agent writes handoff: `docs/orchestration/agent-handoffs/2026-06-13-0XX-amiga-tournament-structure-slice-N.md` (increment `XXX` from last structure handoff; start at **001** if none exist).
5. At **STOP gates**, agent lists exact browser/SQL/CLI checks and **waits** for user OK.
6. **Do not git commit** unless user asks.
7. After slices that change stored truth: **UPDATE_DOCS** Part A; Part B when migration `023` ships (slice 1).

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| T1 | Stage types: `round_robin` \| `knockout` only (v1) |
| T2 | `round_robin` → standings `league`; `knockout` → standings `knockout` |
| T3 | Retire `league`, `group`, `placement`, `other` as **stage** types |
| T4 | Structure (singleton vs multi-group) is **not** encoded in stage type |
| T5 | Legacy: games authoritative; materialize fixtures from games; no draw-order RR generation |
| T6 | Side parity: fixture A/B must match game A/B after link |
| T7 | NULL phase → implicit single `round_robin` stage; labeled phases → stage buckets |
| T8 | `fixture_id` path takes precedence over phase parser for scope |
| T9 | Recompute `has_league`/`has_cup` from stages (slice 7), not Access-only inference |
| T10 | Steve WC source = structure reference for slice 8+; not blocking bulk RR/KO backfill |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **1** | DDL `023`: stage_type enum migration; fresh `006` DDL; Python/PHP `_fixture_scope` + `VALID_STAGE_TYPES` | **A** — SQL + unit smoke |
| **2** | Builders + Homburg spec + structure verify aligned to new types | — |
| **3** | `materialize_legacy_fixtures()` module + import/replay hook (single-tournament dry-run) | **B** — Athens IV Cup pilot |
| **4** | `verify-legacy-structure` CLI (orphans, side parity, standings parity) | — |
| **5** | Bulk backfill: kitchen marathons (NULL phase) then remaining NULL-phase events | **C** — spot-check list + detail |
| **6** | Phase-labeled events (pure cups, WCs); parser edge fixes as verify finds | **D** — WC sample + mis-tagged cups |
| **7** | Catalog flags from stages; `infer_legacy_tournament_format` alignment | — |
| **8** | Steve WC reference doc + one WC `StructureSpec` draft (user provides source path) | — |
| **9** | Docs closure: data contract, format vision note, MEMORY, feature-log | — |

---

## Slice 1 — Stage type enum migration

### Goal

Stored `tournament_stages.stage_type` and all writers/readers use **`round_robin`** and **`knockout`** only.

### Tasks

- [ ] New migration `scripts/amiga/sql/023_unify_stage_types.sql`:
  - `UPDATE tournament_stages SET stage_type = 'round_robin' WHERE stage_type IN ('league', 'group')`
  - `UPDATE tournament_stages SET stage_type = 'knockout' WHERE stage_type IN ('placement', 'other')`
  - Alter enum to `round_robin`, `knockout` only (MySQL-safe expand → update → shrink)
- [ ] Update fresh-install `scripts/amiga/sql/006_tournament_fixtures.sql` enum
- [ ] `scripts/amiga/tournament_fixtures.py`: `VALID_STAGE_TYPES`
- [ ] `scripts/amiga/tournament_standings.py` + `site/public_html/amiga/ops/includes/amiga_post_game_standings.php`: `_fixture_scope` maps `round_robin` → league, `knockout` → knockout
- [ ] Grep repo for `stage_type` literals; update tests (`test_tournament_structure.py`, etc.)
- [ ] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/023_unify_stage_types.sql`

### Verification

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT stage_type, COUNT(*) FROM tournament_stages GROUP BY stage_type;"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW COLUMNS FROM tournament_stages LIKE 'stage_type';"
python -m pytest scripts/amiga/test_tournament_structure.py -q
python -m scripts.amiga fixtures create --help
```

- [ ] Only `round_robin` and `knockout` in DB and enum
- [ ] Existing generated tournaments (if any) still standings-rebuild without error

### STOP GATE A

User confirms SQL + pytest OK before slice 2.

### Files (expected)

- `scripts/amiga/sql/023_unify_stage_types.sql`
- `scripts/amiga/sql/006_tournament_fixtures.sql`
- `scripts/amiga/tournament_fixtures.py`, `tournament_standings.py`, `tournament_builder.py`
- `site/public_html/amiga/ops/includes/amiga_post_game_standings.php`

---

## Slice 2 — Builders and curated specs

### Goal

All **new** stage creation uses `round_robin` / `knockout`; Homburg pilot spec passes structure verify.

### Tasks

- [ ] `tournament_builder.py`: replace `league`/`group`/`placement` literals
- [ ] `tournament_structure/homburg.py`, `specs.py`, `verify.py`: new type vocabulary
- [ ] `tournament_structure/link.py`: document side-parity requirement (full enforcement in slice 3)
- [ ] Smoke: `python -m scripts.amiga fixtures create` for kitchen_marathon + group_knockout template on test tournament id

### Verification

```powershell
python -m pytest scripts/amiga/test_tournament_structure.py -q
python -m scripts.amiga tournament-structure verify --tournament-id <homburg_id>
```

---

## Slice 3 — Legacy materialize (pilot)

### Goal

**One fixture per game**, copying `player_a_id` / `player_b_id` from game; assign `fixture_id`; create stages from phase buckets.

### Tasks

- [ ] New module e.g. `scripts/amiga/tournament_structure/materialize_legacy.py`:
  - `materialize_legacy_fixtures(conn, tournament_id, *, dry_run=False)`
  - NULL-phase tournament → one `round_robin` stage, all games linked
  - Phase-labeled: bucket by parsed scope (minimal v1: RR vs KO from `tournament_phases.py`)
  - Set `amiga_games.fixture_id`; enforce side match at insert time
- [ ] CLI entry: `python -m scripts.amiga tournament-structure materialize --tournament-id N [--dry-run]`
- [ ] Optional hook after import in `replay` path (behind flag or explicit subcommand first)
- [ ] Pilot: **Athens IV Cup** (`tournament_id=74`) — expect knockout-only stages, no bogus league table after standings rebuild

### Verification

```powershell
python -m scripts.amiga tournament-structure materialize --tournament-id 74 --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id 74
python -m scripts.amiga standings-rebuild --tournament-id 74
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT COUNT(*) orphaned FROM amiga_games g LEFT JOIN tournament_fixtures f ON f.id=g.fixture_id WHERE g.tournament_id=74 AND g.fixture_id IS NOT NULL AND f.id IS NULL;"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT COUNT(*) side_mismatch FROM amiga_games g JOIN tournament_fixtures f ON f.id=g.fixture_id WHERE g.tournament_id=74 AND (g.player_a_id<>f.player_a_id OR g.player_b_id<>f.player_b_id);"
```

- [ ] `side_mismatch = 0`
- [ ] Standings: no spurious `league` scope for pure KO cup (or documented exception)

### STOP GATE B

User confirms Athens IV Cup detail page / SQL before bulk slice 5.

---

## Slice 4 — Verify CLI

### Goal

Repeatable audit for any tournament after materialize.

### Tasks

- [ ] `scripts/amiga/tournament_structure/verify_legacy.py` (or extend `verify.py`):
  - orphan `fixture_id` / missing fixtures for games
  - side parity
  - stage coverage (every game has stage via fixture)
  - optional: standings parity vs phase-only rebuild snapshot
- [ ] `python -m scripts.amiga tournament-structure verify-legacy --tournament-id N`
- [ ] Document commands in `scripts/amiga/README.md`

### Verification

```powershell
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 74
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 281
```

---

## Slice 5 — Bulk NULL-phase backfill

### Goal

Materialize all tournaments where **100% games have NULL phase** (kitchen marathons and similar).

### Tasks

- [ ] Query inventory: `SELECT id, name FROM tournaments t WHERE ...` (document count in handoff)
- [ ] Batch command: `--all-null-phase` with `--dry-run` default off only after user OK at gate C
- [ ] Standings rebuild batch for affected ids
- [ ] `verify-legacy` sample (10 random + known edge cases)

### STOP GATE C

User spot-checks tournament list + 2–3 detail pages (marathon + previously mis-tagged cup).

---

## Slice 6 — Phase-labeled events

### Goal

Pure cups (10 events), World Cups, placement bands; fix parser gaps found by verify (e.g. `Positions` vs `Places`).

### Tasks

- [ ] Extend materialize bucketing for labeled KO / RR phases
- [ ] Parser fixes in `tournament_phases.py` as verify-legacy reports (separate commits per fix)
- [ ] WC sample: one early + one modern tournament id (user may nominate)
- [ ] Re-run honours/participation verify if standings scopes shift

### STOP GATE D

User confirms WC group tables still `league` scope; cups show bracket not league table.

---

## Slice 7 — Catalog flags from stages

### Goal

`has_league` / `has_cup` derived from stage inventory, not Access `Cup?` + NULL-phase heuristic alone.

### Tasks

- [ ] `tournament_format.py`: `infer_format_flags_from_stages()` or post-materialize updater
- [ ] Align `amiga_tournament_index_format_kind()` / `amiga_tournament_format_kind()` read paths
- [ ] `verify-tournament-formats` updated expectations
- [ ] Fix **Kristiansand II Cup** and similar mis-tags

### Verification

```powershell
python -m scripts.amiga verify-tournament-formats
```

---

## Slice 8 — Steve WC structure reference (parallel-friendly)

### Goal

Document modern WC module graph from Steve’s source (~10 WCs); one `StructureSpec` JSON/YAML for a nominated WC.

### Prerequisites

- User adds WC source to repo (path in handoff)
- Steve answers on year-to-year tweaks (optional for draft)

### Tasks

- [ ] `docs/amiga-wc-structure-reference.md` — stages, tracks, promotion, pairing rules
- [ ] `scripts/amiga/tournament_structure/specs/wc_20XX.json` (one event)
- [ ] `verify-legacy` on that WC after materialize slice 6

**Not blocking slices 1–7.**

---

## Slice 9 — Docs closure

### Tasks

- [ ] `amiga-data-contract.md` — stage types, legacy materialize, fixture ground truth for imported events
- [ ] `amiga-tournament-format-vision.md` — pointer to structure policy as authority for stage types
- [ ] `PROJECT_MEMORY.md`, `feature-log.md` (L1 if migration 023 not already logged)
- [ ] Mark this plan **Complete** when slices 1–7 done (slice 8 may trail)

---

## Test tournaments (reference)

| id | Name | Role |
|----|------|------|
| 74 | Athens IV Cup | NULL-phase mis-tagged league+cup; KO structure |
| 281 | Athens L | Incomplete RR — **not** primary misclassification test |
| 22 / 24 | (standings scope fixtures) | Regression for `league`/`knockout` standings |
| Homburg | (format-backbone pilot id) | Curated multi-stage spec |
| Pure cups | `has_league=0`, `has_cup=1` (10 events) | Phase-labeled KO only |

---

## Commands cheat sheet

```powershell
# After slice 1
mysql ko2amiga_db < scripts/amiga/sql/023_unify_stage_types.sql

# Materialize + verify (slices 3–4)
python -m scripts.amiga tournament-structure materialize --tournament-id 74
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 74
python -m scripts.amiga standings-rebuild --tournament-id 74

# Full replay (if import hook added)
python -m scripts.amiga replay

# Format flags (slice 7)
python -m scripts.amiga verify-tournament-formats
```

---

*Track initiated Jun 2026 — import/backfill first, live WC generation later.*
