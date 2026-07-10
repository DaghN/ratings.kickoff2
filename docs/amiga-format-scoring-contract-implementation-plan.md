# Amiga format scoring contract — implementation plan (agent slices)

**Status:** **SC-8 shipped (Jul 2026)** — RTB broadcast uses contract-driven fixture compute; live hub knockout bracket; `verify-rtb-standings-parity` oracle.

**Policy (locked):** [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md)

**Design history:** [`amiga-format-scoring-contract-design-discussion.md`](amiga-format-scoring-contract-design-discussion.md)

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md)

**Forward proof:** **`python -m scripts.amiga simul`** on **`ko2amiga_work`** after each slice that touches writers. Oracle **`prove`** on frozen DB only when Dagh asks.

---

## How to use this plan

1. User says **“Do SC-N”** or **“Continue scoring contract slice N”**.
2. Agent executes **one slice** unless user asks for multiple.
3. Run slice **Verification** before handoff.
4. Handoff: `docs/orchestration/agent-handoffs/` or `docs/archive/orchestration/agent-handoffs/` with prefix `amiga-scoring-contract-`.
5. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless user asks.
6. Schema / writer slices: **UPDATE_DOCS** Part A + Part B when L4b DDL ships.

---

## Locked product decisions (do not re-open)

See policy **SC1–SC18** and §3 step enums / `platform_default_v1` chains.

---

## Slice map

| Slice | Deliverable | Notes |
|-------|-------------|--------|
| **SC-0** | L4b DDL: relational contract on `tournaments` + `tournament_stages`; step child table; `scoring_schema_version`; freeze columns on tournament | Part B migration |
| **SC-1** | Repo seed `platform_default_v1` (relational); copy-on-create hooks at stage/tournament materialize | Matches policy §3 |
| **SC-2** | Python contract reader + `verify-scoring-contract` (structural) | D14 verify intent |
| **SC-3** | Python standings executor reads contracts (replace hardcoded 3-1-0 / KO chain) | `tournament_standings.py` |
| **SC-4** | PHP contract reader + executor parity with SC-3 | `amiga_post_game_standings.php` · `amiga_scoring_contract.php` |
| **SC-5** | PHP↔Python parity oracle CLI on shared fixtures + contracts | D17 |
| **SC-6** | Catalog backfill: explicit contract rows on all tournaments/stages | Bridge retirement path |
| **SC-7** | D6 finalize freeze writes frozen snapshot columns | `finalize_tournament.py` + PHP · `freeze-scoring-contracts` CLI |
| **SC-8** | RTB broadcast uses contract reader (fixtures adapter); live hub KO | D15; `amiga_running_tournament_lib.php` · `verify-rtb-standings-parity` |
| **SC-9** | L5 `stage_id` column + dual-write; readers join stage | D7 / D9-pre |
| **SC-10** | Phase parser executor branch removal | After 100% `fixture_id` + audit (SC10 policy) |
| **SC-11** | Structured L3 match extensions (ET/pens cols) + KO steps | Separate from SC-0–9; policy SC11 |

**Suggested order:** SC-0 → SC-1 → SC-2 → SC-3 → SC-5 → SC-4 → SC-6 → SC-7 → SC-8 → SC-9 → SC-10. **SC-11** parallel when match-extension slice is scheduled.

---

## SC-0 — L4b DDL (sketch)

### Goal

Persistent relational home for scoring contracts per policy SC3–SC6.

### Tasks (agent fills detail at slice time)

- [x] Migration under `scripts/amiga/sql/structure/` — **`011_tournament_scoring_contract.sql`**
- [x] Stage grain: `scoring_primitive`, points cols, `scoring_schema_version`, frozen mirror cols
- [x] Child table: `tournament_stage_scoring_steps` (`stage_id`, `sequence_no`, `step` enum v1)
- [x] Tournament grain: `scoring_*_points_default`, `frozen_scoring_schema_version`, `scoring_frozen_at`
- [x] Export pack includes contract table — `L4_TABLES` + `schema_bundles` drop order

### Verification

- [x] `apply_schema_structure` on `ko2amiga_work` clean (idempotent ALTER)
- [x] `standings-parity --sweep` **FAIL=0** (681 PASS, 29 EXCEPTION, 112 SKIP)
- [ ] `verify-export-pack structure` — passes after next `export-pack structure` (manifest lists new table)

---

## SC-1 — platform_default_v1 + copy-on-create (shipped Jul 2026)

### Goal

New stages/tournaments get explicit relational contract rows from repo preset — no executor change yet.

### Delivered

- [x] `scripts/amiga/scoring_contract.py` — `platform_default_v1` chains + `ensure_*` helpers
- [x] Hook: `tournament_fixtures.create_stage()` → `ensure_stage_scoring_contract`
- [x] PHP: `includes/amiga_scoring_contract.php` + `fixtures.php` league create path
- [x] Unit tests: `scripts/amiga/test_scoring_contract.py`

### Verification

- [x] Kitchen marathon smoke on `ko2amiga_work` — tournament defaults 3/1/0, stage `league_table` + 4 steps
- [x] `standings-parity --sweep` **FAIL=0**

**Not in SC-1:** legacy catalog backfill (SC-6), executor reads (SC-3), structural verify CLI (SC-2).

---

## SC-2 — contract reader + structural verify (shipped Jul 2026)

### Goal

Load L4b contracts from DB; fail on malformed rows (D14 structural verify).

### Delivered

- [x] `StageScoringContract` + `load_stage_scoring_contract()` / `load_stage_scoring_steps()` in `scoring_contract.py`
- [x] `validate_stage_scoring_contract()` — schema version, primitive/stage_type, points, step enum, non-empty chain
- [x] `verify_scoring_contract.py` + CLI `verify-scoring-contract`
- [x] Wired into `modern/verify_suite.py` (simul)
- [x] Unit tests extended in `test_scoring_contract.py`

### Verification

- [x] `python -m scripts.amiga verify-scoring-contract` OK on `ko2amiga_work` (0 explicit contracts or all valid)
- [x] Unit tests pass

**Bridge:** verify only stages with `scoring_primitive IS NOT NULL` — legacy NULL rows ignored until SC-6.

---

---

## SC-3 — Python executor reads contracts (shipped Jul 2026)

### Goal

`tournament_standings.py` dispatches points + tie-break/KO chains from L4b contracts.

### Delivered

- [x] `ScoringContext` + `load_scoring_context_for_tournament()` in `scoring_contract.py`
- [x] `default_scoring_context()` for parity CLI (no DB)
- [x] `LEGACY_KNOCKOUT_BRIDGE_STEPS` (GD → GF → pens) when stage contract NULL — catalog parity until SC-6
- [x] DB stage contracts used when `scoring_primitive` set (SC-1 live path uses `platform_default_v1` KO)
- [x] `GAME_SELECT` includes `stage_id`
- [x] Step-aware `_assign_positions` / `_knockout_positions`

### Verification

- [x] `standings-parity --sweep` **FAIL=0** on `ko2amiga_work`
- [x] SC-5 PHP↔Python oracle (`verify-php-standings-parity`)

**Not in SC-3:** PHP executor (`amiga_post_game_standings.php`), catalog backfill (SC-6).

---

## SC-4 — PHP executor reads contracts (shipped Jul 2026)

### Goal

`amiga_ops_compute_tournament_standings` dispatches points + tie-break/KO chains from L4b contracts (parity with SC-3 Python).

### Delivered

- [x] `amiga_scoring_load_context_for_tournament()` + synthetic contracts + legacy KO bridge in `amiga_scoring_contract.php`
- [x] `amiga_ops_compute_tournament_standings($games, $scoringContext)` — contract-driven league sort + KO resolution
- [x] `amiga_ops_standings_apply_game()` loads context from DB
- [x] Game SQL includes `stage_id` (post-game + RTB broadcast fixture adapter)
- [x] `amiga_running_tournament_standings_rows()` loads DB contracts for live hub preview

### Verification

- [x] `standings-parity --sweep` **FAIL=0** on `ko2amiga_work`
- [x] SC-5 PHP↔Python oracle (`verify-php-standings-parity`)

---

## SC-5 — PHP↔Python parity oracle (shipped Jul 2026)

### Goal

Automated verify runs both executors on shared inputs (games + contracts); fails on row diff (D17).

### Delivered

- [x] `scripts/oneoff/amiga_standings_build_parity.php` — PHP CLI probe (JSON rows)
- [x] `scripts/amiga/verify_php_standings_parity.py` — `verify-php-standings-parity` CLI (`--sample`, `--sweep`, `--tournament-id`)
- [x] Wired into `modern/verify_suite.py` (after `verify-scoring-contract`)
- [x] PHP phase-routing parity fixes uncovered by sweep:
  - `amiga_ops_is_knockout_phase`: singular `Quarter Final` / `Semi Final` (matches Python `_QUARTER_SEMI_FINAL_RE`)
  - `hasNullPhase` only when fixture path not taken (matches Python loop order)

### Verification

- [x] `verify-php-standings-parity --sweep` green on `ko2amiga_work`
- [x] `standings-parity --sweep` still **FAIL=0** (Python path unchanged)

---

## SC-6 — Catalog backfill (shipped Jul 2026)

### Goal

Every catalog tournament/stage has explicit relational L4b contract rows; retire NULL `scoring_primitive` bridge on `round_robin` / `knockout` stages.

### Delivered

- [x] `ensure_catalog_stage_scoring_contract()` — league = `platform_default_v1`; catalog KO = stored `LEGACY_KNOCKOUT_BRIDGE_STEPS` (GD → GF → pens) for Access parity
- [x] `backfill_scoring_contracts()` + CLI `backfill-scoring-contracts` (`--dry-run`, `--tournament-id`)
- [x] `goals_for` allowed on `knockout_tie` step enum (structural verify; not in live-ops `platform_default_v1` default chain)
- [x] `verify-scoring-contract` fails on NULL `scoring_primitive` for `round_robin` / `knockout` stages

### Verification

- [x] `backfill-scoring-contracts` on `ko2amiga_work`: **605** tournaments + **605** stages
- [x] `verify-scoring-contract` OK
- [x] `verify-php-standings-parity --sweep` green
- [x] `standings-parity --sweep` **FAIL=0**

**Not in SC-6:** finalize freeze columns (SC-7); legacy `stage_type` values outside `round_robin` / `knockout`.

---

## SC-7 — Finalize freeze (shipped Jul 2026)

### Goal

At tournament finalize, copy effective L4b contract onto frozen snapshot columns (D6 / SC6 policy).

### Delivered

- [x] `freeze_scoring_contracts_for_tournament()` — Python + PHP (`amiga_scoring_freeze_contracts_for_tournament`)
- [x] Hooked in `finalize_tournament.py` + `finalize_tournament.php` (same transaction as `rating_finalized`)
- [x] Tournament: `frozen_scoring_schema_version`, `scoring_frozen_at`
- [x] Stages: copy `scoring_*` → `frozen_scoring_*` for stages with explicit contracts
- [x] `freeze-scoring-contracts` CLI for catalog repair (`rating_finalized=1`, `scoring_frozen_at IS NULL`)
- [x] `verify-scoring-contract` fails on missing freeze for finalized tournaments/stages

### Verification

- [x] `freeze-scoring-contracts` on `ko2amiga_work`: **605** tournaments + **605** stage rows
- [x] `verify-scoring-contract` OK

---

## SC-8 — RTB broadcast + live hub KO (shipped Jul 2026)

### Goal

Running-tournament broadcast (fixtures lane) uses the same contract reader + standings executor as official `amiga_games` path; does not persist L5. Live hub shows league + knockout bracket (policy SC14 / D15).

### Delivered

- [x] `amiga_running_tournament_compute_standings()` — fixture adapter + `ScoringContext`
- [x] `amiga_running_tournament_standings_scope_rows()` / `list_scopes()` / knockout fixture legs
- [x] `amiga_tournament_standings_rows()` / `list_scopes()` / `knockout_fixture_games()` delegate in broadcast mode
- [x] `live-tournament.php` — knockout bracket via `amiga_tournament_render_bracket()`
- [x] `verify-rtb-standings-parity` — Python↔PHP fixture compute; fixture vs `amiga_games`/L5 on finalized live-ops (SKIP when none on work DB)
- [x] `running_tournament_games()` includes `stage_id` (parity with PHP)

### Verification

- [x] `verify-rtb-standings-parity --sweep` SKIP on `ko2amiga_work` (no live-ops fixtures corpus)
- [x] `verify-scoring-contract` OK · `verify-php-standings-parity` OK

---

## Out of scope (this plan)

- Promotion graph storage (**D18**)
- Full catalog L4 materialize (structure track)
- Head-to-head implementation until contract + audit identify tournaments needing it in chain
- Golden goal structured L3 until product case exists

---

## Success criteria (plan complete)

1. Every tournament/stage has explicit relational contract (SC6).
2. Simul rebuild standings from contracts only — no hardcoded WIN_POINTS in executor hot path.
3. RTB broadcast table matches L5 after promote at same scores (SC-8 + SC-5).
4. Finalize freezes contract columns (SC-7).
5. Docs: data contract § Tournament standings points at policy; Track B notes bridge retired.
