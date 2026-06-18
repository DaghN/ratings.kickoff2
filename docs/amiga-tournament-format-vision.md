# Amiga tournament format system — vision & analysis

**Status:** Architectural investigation (Jun 2026). **Superseded for stage-type decisions** by [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) + agent track [`amiga-tournament-structure-implementation-plan.md`](amiga-tournament-structure-implementation-plan.md). **Audience:** implementers, product, agents.  
**Purpose:** Capture analysis from design conversations about moving beyond legacy `koatd` phase strings toward an explicit format/fixture model in `ko2amiga_db`, while preserving historical fidelity.

**Handoff prompt for implementation:** [`amiga-tournament-format-handoff-prompt.md`](amiga-tournament-format-handoff-prompt.md) (exploration era) · **current execution:** [`archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md)

**Related:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-schema-discovery.md`](amiga-schema-discovery.md) · [`amiga-realm-vision.md`](amiga-realm-vision.md) · [`amiga-track-b-tournament-standings-agent-prompt.txt`](amiga-track-b-tournament-standings-agent-prompt.txt)

---

## 1. Executive summary

### Problem

The offline Amiga realm (~603 tournaments, ~27k games) stores tournament **structure** almost entirely in optional **`Scores.Phase` string labels** (~39% of games; ~61% NULL). There is no explicit format schema, stage graph, fixture list, or registration model in `koatd.mdb`.

We built `ko2amiga_db` to import that log and **infer** standings/brackets via regex parsing (`scripts/amiga/tournament_phases.py`). That works for **historical display** but is not a foundation for **live tournament creation**, new formats, or reliable honours rules.

### Product direction (gravitating toward)

1. **Explicit format metadata** in **our** database (`ko2amiga_db`), not in `koatd`.
2. **Non-exclusive dimensions** — e.g. league play + knockout play (World Cups are both; kitchen marathons may end with a final).
3. **Registration, stages, fixtures, validated result entry** for new events.
4. **Legacy import** maps old phase strings into the new model (or marks events `legacy_inferred` and keeps phase fallback).
5. **Respect legacy** = reproduce intended tables/brackets from game facts + documented import transforms — not copy stale Access snapshot tables as truth.

### Recommended architecture (hybrid)

```
LEGACY LAYER (imported)
  amiga_games.phase, tournaments catalog, import aliases
        ↓ map / infer
CANONICAL FORMAT LAYER (ko2amiga only, evolves)
  format templates, stages, tracks, fixtures, format flags
        ↓ drives
DERIVED LAYER (rebuildable)
  standings, brackets, honours, catalog_stats
```

**Rule:** derived logic prefers format/fixture IDs when present; falls back to phase parser for legacy games.

---

## 2. How `koatd` encodes tournaments today

### 2.1 Three Access layers

| Layer | Table(s) | Authority |
|-------|----------|-----------|
| Event catalog | `[Tournament players]` | Names, dates, chrono, `Cup?`, `EqualTeams`, player count |
| Game log | `Scores` | **Canonical** — teams, goals, tournament, `Phase`, `Extra` |
| Standing snapshots | `Tables`, `World Cup * Tables`, … | **Reference only** — often stale vs aggregating `Scores` |

See [`amiga-schema-discovery.md`](amiga-schema-discovery.md).

### 2.2 What `Phase` carries (overloaded)

One varchar often encodes simultaneously:

- Structure type (group RR vs knockout vs placement band)
- Stage (`Round 1`, `Quarter Finals`, …)
- Track (`Silver Cup`, `Bronze Cup`, `KOA Cup`, …)
- Group id (`Group A` … `Group L`)
- Placement label (`Places 9-16`, `11th Place Final`, …)

**NULL phase** (~61%): implicit single round-robin (kitchen marathon).

### 2.3 Catalog flags are weak

| Field | Reality |
|-------|---------|
| `Cup?` | Raw Access: 30/604 set because `World Cup V KOA Cup` is a duplicate catalog row; canonical import after alias skip: 29/603 set. **All real World Cup parent rows are 0** |
| `EqualTeams` | Correlates with structured cups; not a format spec |

Structure lives in **phases**, not catalog flags.

### 2.4 Legacy inconsistencies (fixed at import, not in mdb)

| Issue | Mitigation |
|-------|------------|
| Milan X fragments as separate `Scores.Tournament` | `tournament_names.py` aliases → parent + phase inference |
| World Cup V KOA Cup as second catalog row (2005) | Alias → `World Cup V (Cologne)`, phases prefixed `KOA Cup - …`; catalog row skipped |
| World Cup 2015 year label vs Roman XV | `import_corrections.py` name override |
| Inconsistent phase spelling | `tournament_phases.py` normalizers |

Pattern: **import layer owns canonical truth** — see [`amiga-import-layer.md`](amiga-import-layer.md).

---

## 3. How `ko2amiga_db` works today

### 3.1 Layer map

| Layer | Tables | Written by |
|-------|--------|------------|
| Ground | `tournaments`, `amiga_players`, `amiga_games` | Import / future submission |
| Derived | `amiga_game_ratings`, `amiga_player_stats`, `amiga_tournament_standings`, `amiga_tournament_catalog_stats` | `replay` / PHP post-game |

### 3.2 Standings engine (Track B v1)

- Source: games per `tournament_id`, ordered by `source_scores_id`
- Points: 3/1/0; tie-break GD, GF
- Scopes from `parse_phase()`:
  - NULL → `overall`
  - Group labels → `group` scope
  - Knockout labels → `knockout` scope per player pair (`{phase}|{id}-{id}`)
- `extra` column: ET/penalties text for knockout winner only (not Elo)
- **Not shipped:** cross-stage bracket advancement graph (Tier 4 in track-b prompt)

Key files:

- `scripts/amiga/tournament_phases.py`
- `scripts/amiga/tournament_standings.py`
- `scripts/amiga/tournament_names.py`
- `site/public_html/includes/amiga_tournament_lib.php`
- `site/public_html/amiga/ops/includes/amiga_tournament_phases.php`

### 3.3 Website “format” today (index filter)

Exclusive **league OR cup** badge from derived signals + `is_cup`:

```php
// amiga_tournament_index_format_kind() — simplified
if (knockout_ties > 0 || group_scopes > 0) return 'cup';
if (is_cup) return 'cup';
return 'league';
```

World Cups filtered by **name regex** (`^World Cup\s+…`), not `Cup?`.

**Agreed direction (not yet implemented):** dual flags `has_league` / `has_cup` on catalog at import; at least one true when games exist.

### 3.4 Empirical classification (Jun 2026 DB)

Proposed model: **league** = group scopes OR null-phase games; **cup** = knockout scopes OR Access `Cup?`.

| Bucket | ~Count |
|--------|-------:|
| League only | 517 |
| Cup only | 10 |
| **Both** | **75** (includes all 23 World Cups) |
| Neither (0 games) | 1 |

World Cups: groups + knockouts, no null-phase games → **both** structurally.

**Audit note (Jun 2026 implementation check):** current code already has derived standings, knockout tie scopes, PHP incremental post-game standings, `extra` import, tournament catalog stats, multipart staging export, format templates, catalog format flags, and a stage/fixture schema foundation. Format-system work must preserve FK/drop ordering, the append-only `process-one` contract, and the Amiga policy that match streaks are not product truth.

---

## 4. What works / what strains

### Works well (keep)

- Import + provenance + import manifest audit
- Phase parser for messy historical labels
- Derived standings with parity tooling (`standings-parity --sweep`)
- Tournament index + standings + bracket UI (phase-grouped columns, not full graph)
- Incremental post-game ops (`process-one`, append-only Elo)
- Import aliases as precedent for structural fixes

### Strains (motivation for new system)

| Limitation | Impact |
|------------|--------|
| No stage graph | Bracket UI groups by phase name; no advancement edges |
| Phases as implicit DSL | New format = new regex / alias rules |
| Inconsistent KO scopes | e.g. WC IV `KOA Cup - Final` → group scope; main `Final` → knockout |
| No fixtures/registration | Cannot validate “who plays whom” at entry time |
| Exclusive league/cup UI | Misrepresents World Cups and marathon+final events |
| `is_cup` unreliable | Honours rules cannot depend on it |
| Mixed events | Athens LXXXV: overall table excludes KO legs (`mixed_overall_league_only` parity note) |

---

## 5. Target capabilities (eventual product)

Not a binding spec — defines the design space.

1. **Event registration** — host, date, venue, capacity
2. **Roster** — entrants (Amiga player ids; optional online account link later)
3. **Format template** — reusable definitions (marathon, group+KO, WC-class multi-track, …)
4. **Stage graph** — ordered stages, parent/child, promotion rules (where known)
5. **Fixtures** — scheduled pairings, legs, optional schedule metadata
6. **Validated result entry** — select fixture, enter score + optional `extra`
7. **Auto-generated phase labels** (optional) — for export/PDF/legacy-shaped dumps
8. **Honours derivation** — medals, cup wins, marathon wins from structured outcomes
9. **Format versioning** — templates evolve without breaking past events

### Orthogonal dimensions (not either/or)

| Dimension | Examples |
|-----------|----------|
| League play | Null-phase marathon; group round-robins |
| Elimination play | KO brackets, placement bands |
| Multi-track | Main / Silver / Bronze / KOA |
| Series | World Cup I–XXIII; Birmingham N |
| Honour tier | World Cup vs kitchen vs national open |

---

## 6. Architectural options considered

| Option | Summary | Verdict |
|--------|---------|---------|
| **A — Phase forever** | Infer everything from `phase` strings | OK for read-only archive; poor for live ops |
| **B — Dual catalog flags** | `has_league`, `has_cup` at import | Good near-term; insufficient alone |
| **C — Format spec + stages** | Templates, stages, fixtures in ko2amiga | **Recommended core** for new system |
| **D — Full draw engine** | Automated seeding, promotion, rematch logic | Defer until C is stable |

### Suggested hybrid (C + legacy fallback)

**New tables (illustrative — implementer must normalize):**

```
tournament_format_templates     -- slug, name, schema_version, spec JSON
tournaments                     -- + format_template_id, format_overrides JSON, has_league, has_cup, …
tournament_stages               -- tournament_id, stage_key, stage_type, parent_stage_id, track_key, config JSON
tournament_stage_players        -- roster per stage (optional)
tournament_fixtures             -- stage_id, player_a_id, player_b_id, leg, status, …
amiga_games                     -- + fixture_id NULL for legacy; phase retained for provenance/display
```

**Legacy import:**

- Default `format_template_id = 'legacy_inferred'` (or similar).
- Backfill stages/fixtures **optionally** (World Cups first, or infer-only).
- Never mutate `source_scores_id` / original phase text without manifest entry.

**Live creation:**

- Admin picks template → system creates stages/fixtures → results attach to fixtures.
- `phase` generated on write if needed for backward-compatible exports.

**Standings resolver:**

```
resolve_scope(game):
  if game.fixture_id → scope from fixture.stage
  else → parse_phase(game.phase)   // legacy path
```

---

## 7. Legacy fidelity — what “respect koatd” means

| Do | Don't |
|----|-------|
| Preserve game facts, provenance, import transforms | Require koatd schema changes for new features |
| Reproduce community-expected tables/brackets from games | Import Access `Tables` snapshots as derived truth |
| Document transforms in import manifest | Encode new formats only as ad-hoc phase strings |
| Keep phase parser permanently as legacy reader | Assume phases are sufficient for live input |

**World Cup V KOA Cup case study:** Real event = one World Cup weekend; KOA = consolation track. Access split into two catalog rows; `World Cup V Tables` already has Groups A–L. Fix: merge at import with `KOA Cup - …` phases (see import-layer doc).

---

## 8. Implementation phasing (suggested, non-binding)

### Slice 0 — Audit & contract (first PR in new effort)

- Validate analysis against live DB + `koatd.mdb` samples
- Extend [`amiga-data-contract.md`](amiga-data-contract.md) table register
- DDL design review; migration strategy for staging multi-part SQL export

### Slice 1 — Catalog enrichment

- `has_league`, `has_cup` on `tournaments` (import-computed from phase histogram)
- Import audit: games > 0 ⇒ at least one flag true
- Keep raw `is_cup` as `access_cup_flag` or document verbatim import

### Slice 2 — Format template core

- `tournament_format_templates` seed rows: `legacy_inferred`, `kitchen_marathon`, `group_knockout`, `world_cup_class` (minimal JSON spec)
- `tournaments.format_template_id` + overrides
- No UI yet; SQL + Python module + tests

### Slice 3 — Stages & fixtures (new events only)

- CRUD module for stages/fixtures (**started**: internal Python CLI/service)
- Internal tournament builder from template (**started**: `kitchen_marathon` round-robin only)
- Result submission sets `fixture_id`; generates phase string (**partially started**: attach helper only, no website submission yet)
- Standings engine branch for fixture-backed games

### Slice 4 — Legacy backfill (incremental)

- Infer stages from phase histogram per tournament (batch job)
- World Cups + major cups first
- Parity gates vs `standings-parity`

### Slice 5 — Registration & admin UI

- PHP or ops CLI for create tournament from template
- Defer public registration until identity model clear

### Explicit deferrals

- Full WC placement promotion engine
- Cross-realm online account linking
- Bracket advancement graph UI (unless stage graph data exists)
- Replacing regex parser for all imported games

---

## 9. Template extension contract (Jun 2026)

Format templates are rows in `tournament_format_templates` with JSON `spec_json`. **Implemented** templates drive live builders and import backfill; **planned** templates reserve slug + shape only (`status: "planned"` in spec).

### What every template must declare

| Contract piece | Role | Implemented by |
|----------------|------|----------------|
| **`slug` + `stages[]`** | Canonical stage keys and types | `seed_format_templates()` · DDL `tournament_stages` |
| **`stage_factory`** (slug or module path) | Creates stages + fixtures for **new** events | `tournament_builder.py` (e.g. `kitchen_marathon`, `group_knockout`) |
| **`standings_resolver`** | How `amiga_games` map to `amiga_tournament_standings` scopes | `tournament_standings._fixture_scope()` · phase fallback when `fixture_id` NULL |
| **`legacy_phase_fallback`** | Whether imported games without fixtures use `tournament_phases.py` | `true` only for `legacy_inferred` |
| **Structure backfill** (historical) | Optional `StructureSpec` in `tournament_structure/registry.py` | Import hook `apply_structure_spec()` |

### Template status lifecycle

```
planned  →  implemented  →  (optional) deprecated
```

- **`planned`:** seeded in DB; no builder, no standings branch, no tournaments may reference except tests.
- **`implemented`:** builder and/or import structure path exists; `verify-tournament-formats` counts it as active.
- Tournaments always store `format_template_id` + optional `format_overrides` JSON for event-specific facts (evidence URL, round count, etc.).

### Seeded templates (Jun 2026)

| Slug | Status | Stage factory | Standings resolver |
|------|--------|---------------|-------------------|
| `legacy_inferred` | implemented | — (import only) | `parse_phase()` |
| `kitchen_marathon` | implemented | `create_kitchen_marathon_tournament` | fixture `league` / overall |
| `group_knockout` | implemented | `create_group_knockout_tournament` + structure backfill | fixture `group` + `knockout` |
| `world_cup_class` | implemented (partial) | deferred | phase + partial fixture |
| `swiss` | **implemented** | `create_swiss_tournament` | fixture `league` / overall (cumulative) |
| `double_elimination` | **implemented** | `create_double_elimination_tournament` + `advance_double_elim` | fixture `knockout` per tie |

### Adding a new format family

1. Add `FORMAT_TEMPLATES` row with `spec_json` (start with `status: "planned"` if not ready).
2. Implement **stage factory** and wire `tournament_builder` CLI.
3. Add **standings resolver** branch (Python + PHP if live ops).
4. Add tests + `verify-tournament-formats`.
5. For historical events: `StructureSpec` + registry — not ad-hoc import code.

**Swiss implementation checklist:** [`amiga-format-add-swiss-checklist.md`](amiga-format-add-swiss-checklist.md)

---

## 10. Open product questions

1. Backfill all 603 events or only `legacy_inferred` + curated majors?
2. Is bracket **advancement graph** required, or phase-grouped columns enough for v2?
3. Honours rules: data-driven from stage/track keys vs community narrative docs (Alkis pages)?
4. Live result entry: append-only Elo only, or allow corrections with replay-from-point?
5. Who may author new format templates (core maintainers vs per-host JSON)?

---

## 11. Key codebase entry points

| Area | Path |
|------|------|
| Import | `scripts/amiga/import_access.py` |
| Tournament aliases | `scripts/amiga/tournament_names.py` |
| Catalog corrections | `scripts/amiga/import_corrections.py` |
| Phase parser | `scripts/amiga/tournament_phases.py` |
| Standings | `scripts/amiga/tournament_standings.py` |
| Catalog stats | `scripts/amiga/tournament_catalog_stats.py` |
| Core DDL | `scripts/amiga/sql/001_core.sql`, `002_tournament_standings.sql`, `004_tournament_catalog_stats.sql`, `005_tournament_formats.sql`, `006_tournament_fixtures.sql` |
| Replay | `scripts/amiga/replay.py` |
| PHP standings ops | `site/public_html/amiga/ops/includes/amiga_post_game_standings.php` |
| Website read | `site/public_html/includes/amiga_tournament_lib.php`, `amiga/tournament.php` |
| Parity | `scripts/amiga/standings_parity.py` |
| Access inventory | `data/amiga/source/koatd.mdb`, `python -m scripts.amiga discover_access_schema` |

### Commands

```powershell
python -m scripts.amiga import
python -m scripts.amiga replay
python -m scripts.amiga standings-parity --sweep
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-import-manifest
```

---

## 12. Conversation context (Jun 2026)

This document synthesises a Cursor chat thread covering:

1. World Cup V / KOA Cup duplicate on tournament index — investigation + **import fix shipped** (`World Cup V KOA Cup` → parent, phases prefixed).
2. Whether league/cup should be exclusive — **no**; dual flags recommended at import.
3. Whether phases are sufficient long-term — **no for live ops**; hybrid format layer recommended.
4. Request for deep architectural report and implementation handoff (this doc).

Prior agent work in that thread: DB queries against `ko2amiga_db`, Access ODBC reads, parity checks, import+replay verification after KOA merge.

---

## 13. Success criteria for the new system

1. **New tournament** can be created from a template without typing phase strings.
2. **Legacy tournaments** still render correctly via fallback parser (0 parity regressions on sweep).
3. **Format flags** (`has_league`, `has_cup`) are stable, audited, documented.
4. **Contract** lists every new table with ground vs derived classification.
5. **Import manifest** records format backfill transforms where applied.
6. **Staging export** (`scripts/export_ko2amiga_db.ps1`) updated for new DDL parts.

---

*End of vision document. For the agent handoff prompt, see [`amiga-tournament-format-handoff-prompt.md`](amiga-tournament-format-handoff-prompt.md).*
