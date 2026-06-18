# Amiga tournament structure — implementation plan (agent slices)

**Status:** **In progress** — slices 1–2 shipped; slice 3 pilot **superseded by 3b** (policy v2 Jun 2026).  
**Policy (locked):** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) · **restart:** [`archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) (standings tally layer — do not re-litigate) · [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) (background; partially superseded)

**In scope:** Collapse `tournament_stages.stage_type` to `round_robin` | `knockout`; align builders, standings fixture resolver, ops PHP; **game-authoritative** legacy fixture materialization; verify CLI; bulk NULL-phase + phase-labeled backfill; optional catalog flags from stages; Steve WC structure reference (later slice).

**Out of scope (defer unless user expands):**

- Live WC **generator** UI / draw-order fixture scheduling for new events (beyond existing builder smoke)
- Full promotion graph interpreter for all ~603 events
- Tournament index / detail UI cutover to stage-backed format labels (optional late slice)
- Staging export / WinSCP
- Online `kooldb*` ladder
- `swiss` stage type (deferred per policy)

**Authority:** policy doc T1–T22. Standings `scope_type` rules remain in standings policy S1–S10.

**Paused work to align:** format-backbone slices A–E + Homburg pilot ([`amiga-format-backbone-orchestration-prompt.md`](archive/orchestration/amiga-format-backbone-orchestration-prompt.md)) — **do not extend backfill** until slice 1 enum + slice 3 materialize path smoke.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue with the next slice”**.
2. Agent executes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs slice **Verification** before stopping; fix failures before handoff.
4. Agent writes handoff: `docs/archive/orchestration/agent-handoffs/2026-06-13-0XX-amiga-tournament-structure-slice-N.md` (increment `XXX` from last structure handoff; start at **001** if none exist).
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
| T7 | NULL + complete k-leg RR schedule → auto materialize; else **needs_structure_review** (no auto-KO) |
| T8 | KO module = **one stage per 2-player tie**; rounds in StructureSpec only |
| T9 | `fixture_id` path takes precedence over phase parser for scope |
| T10 | Bulk slice 5 = **tier A only** (NULL-phase complete RR marathons, k≥1) |
| T11 | Steve WC source = structure reference for slice 8+ |
| T12 | **Fixture = one match, one result** — universal live + legacy (policy T8–T9, T21–T22) |
| T13 | **Module outcomes on `stage_id`**; structure graph references stages, not games |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **1** | DDL `023`: stage_type enum migration; fresh `006` DDL; Python/PHP `_fixture_scope` + `VALID_STAGE_TYPES` | **A** ✓ |
| **2** | Builders + Homburg spec + structure verify aligned to new types | ✓ |
| **3** | ~~Pilot materialize~~ — **superseded** (bad NULL⇒KO heuristic) | ~~B~~ cancelled |
| **3b** | Policy v2 materialize: tier A only; per-tie KO; `dematerialize`; rollback Athens IV if needed | **B′** |
| **4** | `verify-legacy` CLI + tier A/C inventory | — |
| **5** | Bulk **tier A** NULL-phase complete RR marathons (1×–6× legs) | **C** |
| **6** | **Non-WC tier B bulk** — 41 auto-OK only (`NON_WC_TIER_B_AUTO_MATERIALIZE_IDS`) | **E** |
| **6a** | **Parser-fix queue** — 8 events (`NON_WC_PARSER_FIX_FIRST_IDS`); fix phases, re-curate, materialize | **E′** |
| **6b** | Manual StructureSpec queue (11 review ids + NULL cups) | — |
| **6wc** | **World Cups** (23 tier-B WCs) — WC track | **D** |
| **7** | Catalog flags from stages | — |
| **8** | Steve WC reference doc + one WC `StructureSpec` draft | — |
| **9** | Docs closure | — |

---

## Slice 1 — Stage type enum migration

### Goal

Stored `tournament_stages.stage_type` and all writers/readers use **`round_robin`** and **`knockout`** only.

### Tasks

- [x] New migration `scripts/amiga/sql/023_unify_stage_types.sql`:
  - `UPDATE tournament_stages SET stage_type = 'round_robin' WHERE stage_type IN ('league', 'group')`
  - `UPDATE tournament_stages SET stage_type = 'knockout' WHERE stage_type IN ('placement', 'other')`
  - Alter enum to `round_robin`, `knockout` only (MySQL-safe expand → update → shrink)
- [x] Update fresh-install `scripts/amiga/sql/006_tournament_fixtures.sql` enum
- [x] `scripts/amiga/tournament_fixtures.py`: `VALID_STAGE_TYPES`
- [x] `scripts/amiga/tournament_standings.py` + `site/public_html/amiga/ops/includes/amiga_post_game_standings.php`: `_fixture_scope` maps `round_robin` → league, `knockout` → knockout
- [x] Grep repo for `stage_type` literals; update tests (`test_tournament_structure.py`, etc.)
- [x] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/023_unify_stage_types.sql`

### Verification

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT stage_type, COUNT(*) FROM tournament_stages GROUP BY stage_type;"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW COLUMNS FROM tournament_stages LIKE 'stage_type';"
python -m pytest scripts/amiga/test_tournament_structure.py -q
python -m scripts.amiga fixtures create --help
```

- [x] Only `round_robin` and `knockout` in DB and enum
- [x] Existing generated tournaments (if any) still standings-rebuild without error

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

- [x] `tournament_builder.py`: replace `league`/`group`/`placement` literals
- [x] `tournament_structure/homburg.py`, `specs.py`, `verify.py`: new type vocabulary
- [x] `tournament_structure/link.py`: document side-parity requirement (full enforcement in slice 3)
- [x] Smoke: `build-tournament create-kitchen-marathon` + `create-group-knockout` dry-run

### Verification

```powershell
python -m pytest scripts/amiga/test_tournament_structure.py -q
python -m scripts.amiga tournament-structure verify --tournament-id <homburg_id>
```

---

## Slice 3 — Legacy materialize (pilot) — SUPERSEDED

**Do not use.** Original pilot used `not full RR ⇒ knockout` and one event-wide KO stage for Athens IV. Replaced by **slice 3b** per policy v2 ([`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md)).

If pilot was applied locally to `tournament_id=74`, run:

```powershell
python -m scripts.amiga tournament-structure dematerialize --tournament-id 74
python -m scripts.amiga standings-rebuild --tournament-id 74
```

---

## Slice 3b — Policy v2 materialize

### Goal

Game-authoritative materialize with **tier A auto only**; **per-tie** KO stages; refuse tier C.

### Tasks

- [x] `classify_null_phase_tournament()` / `round_robin_legs()` → `auto_rr` when `k×` RR + equal per-player; else `needs_structure_review`
- [x] `STRUCTURE_REVIEW_TOURNAMENT_IDS` audit flag (Duesseldorf V id=416)
- [x] Refuse materialize on tier C (`StructureReviewRequired`)
- [x] Labeled KO → one `knockout` stage per player pair (tie)
- [x] `dematerialize` CLI for rollback
- [ ] Unit tests green; dematerialize Athens IV if pilot applied
- [ ] Handoff `2026-06-13-014-amiga-tournament-structure-slice-3b.md`

### Verification

```powershell
python -m unittest scripts.amiga.test_tournament_structure -q
python -m scripts.amiga tournament-structure materialize --tournament-id 74
# Expect FAIL: needs_structure_review
python -m scripts.amiga tournament-structure dematerialize --tournament-id 74
python -m scripts.amiga tournament-structure materialize --tournament-id <full_rr_marathon_id> --dry-run
```

### STOP GATE B′

User confirms tier-A dry-run on one marathon + Athens IV refuses + dematerialize OK.

---

## Slice 4 — Verify CLI

### Goal

Repeatable audit for any tournament after materialize.

### Tasks

- [x] `scripts/amiga/tournament_structure/verify_legacy.py` (or extend `verify.py`):
  - orphan `fixture_id` / missing fixtures for games
  - side parity
  - stage coverage (every game has stage via fixture)
  - optional: standings parity vs phase-only rebuild snapshot
- [x] `python -m scripts.amiga tournament-structure verify-legacy --tournament-id N`
- [x] `python -m scripts.amiga tournament-structure audit-inventory` (tier A/B/C/D from `ko2amiga_db`)
- [x] Document commands in `scripts/amiga/README.md`

### Verification

```powershell
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 74
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 281
```

**Local inventory (Jun 2026, after tier-A k× adjustment):** 603 imported — tier A **503**, B **83**, C **16**, D **1** (Homburg); materialized **1**. Tier C = cups, withdrawals, irregular counts, + Duesseldorf V audit flag.

---

## Slice 5 — Bulk tier-A NULL-phase backfill

### Goal

Materialize tournaments where **100% games have NULL phase** **and** complete k-leg RR schedule (`round_robin_legs()` passes; per-player equality enforced).

**Expected bulk count:** ~503 tier-A events (was 108 when only 1× RR accepted).

### Tasks

- [x] Query inventory: tier A **503** (via `audit-inventory`)
- [x] Batch command: `materialize-tier-a --dry-run` / `--apply` (requires explicit flag; GATE C before apply)
- [x] `--rebuild-standings` + `--verify-sample N` on apply
- [x] Dry-run: **503/503** OK, 0 failures (Jun 2026 local)

### STOP GATE C

User spot-checks tournament list + 2–3 detail pages (marathon + previously mis-tagged cup).

**GATE C anchors:** Jerez XI id=**1** (2× RR), Milan XXIII id=**318** (1×), Athens IV id=**74** still tier C.

```powershell
python -m scripts.amiga tournament-structure materialize-tier-a --apply --rebuild-standings --verify-sample 10
```

---

## Slice 6 — Non-WC tier B bulk (41 only)

### Goal

Materialize **only** `NON_WC_TIER_B_AUTO_MATERIALIZE_IDS` (**41** events). **Not** the 8 parser-fix ids (→ slice **6a**). **Not** WCs (→ **6wc**). **Not** manual review (→ **6b**).

Planning curation: [`2026-06-13-018-amiga-tournament-structure-slice-6-curation.md`](archive/orchestration/agent-handoffs/2026-06-13-018-amiga-tournament-structure-slice-6-curation.md).

### Tasks

- [ ] `materialize-tier-b-non-wc` CLI — allow-list **41 only**; `is_slice_6_auto_ok()`
- [ ] Pilot: Gloucester I Cup (**75**), Stoke Cup (**158**); negatives **592**, **48**
- [ ] `--apply --rebuild-standings --verify-sample 10` after **GATE E**

### STOP GATE E

User spot-checks 2 labeled cups; confirms **592** and **48** refuse materialize.

---

## Slice 6a — Parser-fix queue (8 events)

### Goal

Separate slice **after slice 6 bulk** (or parallel only if Dagh asks). Fix `tournament_phases.py` for edge labels on these **8** ids only; re-run `curate_tier_b_non_wc`; move fixed ids from `NON_WC_PARSER_FIX_FIRST_IDS` → auto list; materialize with standings verify.

**Ids:** 48, 145, 152, 166, 198, 267, 269, 284 — see handoff 018 table.

### Tasks

- [ ] Parser patches (Playouts, Play Outs, Places/Positions, Place N Final, Finals plural, …)
- [ ] Re-curate; update register
- [ ] `materialize-tier-b-non-wc` or per-id materialize for **graduated** ids only
- [ ] `verify-legacy --check-standings` per event

### STOP GATE E′

User spot-checks 1–2 graduated parser-fix events (e.g. Groningen VII **48**).

### Do not

- Include these 8 in slice **6** bulk
- Materialize while still listed in `NON_WC_PARSER_FIX_FIRST_IDS` (materialize refuses)

---

## Slice 6wc — World Cups (deferred WC track)

### Goal

~23 tier-B World Cups + Steve WC reference + WC `StructureSpec` drafts. **Not slice 6.** Former slice 8 expands here.

### STOP GATE D

User confirms WC group tables + brackets (WC-specific).

---

## Slice 6b — Manual review queue

### Goal

StructureSpec / triage for tier C and `NON_WC_STRUCTURE_REVIEW_IDS` (Athens LXXXV, Fun Cup events, Athens IV, …).

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

## Slice 10 — Import closure (structure apply hook)

### Goal

**One import command, no follow-up materialize.** Wire tier A + graduated tier B legacy materialize into the same hook that already applies tier D `StructureSpec` during `import_access.py`.

### Prerequisites

- Review track producing durable data: `StructureSpec` entries, register graduations, parser fixes.
- Slices 5–6 bulk logic proven (tier A + safe tier B).

### Tasks

- [x] `pure_knockout.py` handler + contract doc + preview/materialize CLI
- [x] `disposition_register.json` bootstrap (603 rows) + generate/verify CLI
- [ ] `apply_disposition_handlers_for_import(conn)` — dispatch register in `run`
- [ ] Call from `import_access.py` after games staged (or post-insert), before commit
- [ ] Standings rebuild for materialized tournaments in same pass
- [ ] `verify-legacy --sample N` in `run` or post-import smoke
- [ ] Deprecate separate bulk CLIs as **dev/repair only**, not ritual

### Verification

```powershell
python -m scripts.amiga run --recreate-schema
python -m scripts.amiga tournament-structure audit-inventory --json
# Expect materialized_count ≈ tier A + tier D + graduated tier B; review ids still 0 stages
```

User confirms fresh import needs **no** manual materialize commands.

---

## Slice 9 — Docs closure

### Tasks

- [ ] `amiga-data-contract.md` — stage types, legacy materialize, fixture ground truth for imported events
- [ ] `amiga-tournament-format-vision.md` — pointer to structure policy as authority for stage types
- [ ] `PROJECT_MEMORY.md`, `feature-log.md` (L1 if migration 023 not already logged)
- [ ] Mark this plan **Complete** when slices 1–7 **and slice 10 (import closure)** done (slice 8 may trail)

---

## Test tournaments (reference)

| id | Name | Role |
|----|------|------|
| 74 | Athens IV Cup | Tier C — manual StructureSpec; **do not** auto-materialize |
| 281 | Athens L | Incomplete RR — **not** primary misclassification test |
| 22 / 24 | (standings scope fixtures) | Regression for `league`/`knockout` standings |
| Homburg | (format-backbone pilot id) | Curated multi-stage spec |
| Pure cups | `has_league=0`, `has_cup=1` (10 events) | Phase-labeled KO only |

---

## Commands cheat sheet

```powershell
# After slice 1
mysql ko2amiga_db < scripts/amiga/sql/023_unify_stage_types.sql

# Materialize + verify (slices 3b–4)
python -m scripts.amiga tournament-structure materialize --tournament-id <marathon_id> --dry-run
python -m scripts.amiga tournament-structure dematerialize --tournament-id 74

# Full replay (if import hook added)
python -m scripts.amiga replay

# Format flags (slice 7)
python -m scripts.amiga verify-tournament-formats
```

---

*Track initiated Jun 2026 — import/backfill first, live WC generation later.*
