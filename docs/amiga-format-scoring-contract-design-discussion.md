# Amiga format scoring contract — design discussion plan (Jul 2026)

**Status:** **In discussion** — Session C **complete** (D10–D14 locked); Session D next (D15–D17).  
**Purpose:** Working reference for a dedicated design chat that resolves intent about **L4 structure vs L5 standings**, **scoring contracts**, and **where format ground truth lives** — before policy updates and code.

**Authority when implemented:** Will supersede or amend scattered rules in [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md), [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md), [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings, and [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §9 — only after decisions here are locked.

**Related:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (living ground; L4 persists; L5 cleared on simul) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (archive L0–L5 vocabulary)

---

## 1. Problem statement (locked D0)

The first implementation flurry conflated three concerns in one standings engine:

| Concern | What it is | Where it lives today (blur) |
|---------|------------|-----------------------------|
| **Topology** | Stages, fixtures, who plays whom | L4 DB + git `StructureSpec` |
| **Scoring contract** | Points system, tie-break order, KO resolution rules | Hardcoded in `tournament_standings.py` / PHP |
| **Standings projection** | W/D/L, points, `position` rows | L5 `amiga_tournament_standings` |

**Target intent (discussion goal):**

- **Tournament-ground scoring contracts** are inspectable per event (and auditable after bulk rule assignment).
- **L5 standings** are a **projection/cache** only — recomputed from L3 scores + L4 topology + scoring contract on simul/finalize.
- **Standings engine** becomes an **executor** that reads contract config — not the silent owner of “what Amiga football means.”

**Decision D0:** **Locked (2026-07-09)** — wording accepted.

---

## 2. Locked decisions

Record of agreed intent. Runtime topics (D15–D17) in Session D.

| ID | Decision | Outcome |
|----|----------|---------|
| **D0** | Problem statement | **Locked** — topology, scoring contract, and standings projection are separate concerns; engine executes stored rules; L5 is projection. |
| **D1** | Module outcome | **Locked** — always **derived**; **L5 cache only** for stored form. No ground `stage_rankings` table in v1. |
| **D2** | Scoring contract vs projection | **Locked — strict (A)** — scoring contract = **ground/config** (L4b); `amiga_tournament_standings` = **derived projection** (L5). |
| **D4** | Where each truth lives | **Locked** — see §2.2 (register → DB; templates = presets; explicit scoring on every tournament + stage row; simul DB-only). |
| **D5** | Precedence / copy rules | **Locked** — see §2.3 (copy-on-create; stage row = runtime authority). |
| **D6** | Platform default + freeze | **Locked** — `platform_default_v1` in repo; bridge resolver until explicit rows backfilled; **freeze effective contract at finalize**. |
| **D7** | Canonical module key | **Locked** — `stage_id` canonical for compute/UI; phase + scope strings = witness/skin; C→A migration; D10 retires phase fallback. |
| **D8** | Standings executor scope | **Locked** — see §2.5 |
| **D9** | Executor scoring primitives | **Locked** — see §2.7 |
| **D10** | Phase parser fallback retirement | **Locked** — see §2.8 |
| **D12** | `extra` / match extensions | **Locked** — see §2.9 |
| **D13** | Scoring contract serialization | **Locked** — see §2.10 |
| **D14** | Schema version + step enums | **Locked** — see §2.11 |
| **D11** | Disposition register | **Locked** — git routing/materializer only; never scoring rules; not used at simul. |
| **D16** | Export self-containment | **Locked (intent)** — staging dump includes explicit tournament + stage scoring ground; import site does not require git templates to rebuild standings. |

**Deferred:** D3 (L4a/L4b doc split only). **Open:** D18 (promotion storage shape).

**Promotion (P1–P4):** see §2.1.

### 2.1 Promotion overrides vs module outcomes

#### Locked (2026-07-09)

| ID | Rule |
|----|------|
| **P1** | **Promotion ≠ module outcome.** Standings/projection = what the table/tie says under the scoring contract. Promotion = who is placed into the next stage or bracket slot. |
| **P2** | Organizer **may** override promotion without redefining scoring rules (wild card, on-the-spot boundary settlement, etc.). |
| **P3** | **Rejected:** implementing promotion overrides by mutating `amiga_tournament_standings.position` (or any L5 standings row as fake ranks). |
| **P4** | **Locked:** promotion overrides are **L4 ops ground** only — never L5 derived rows. Exact storage shape is **D18** (deferred). |

#### Open — decision **D18** (not locked)

**Not decided yet:** exact ground shape on L4 — e.g. dedicated `promotion_override` records vs materialized `tournament_stage_players` / fixtures only vs promotion edges on a structure graph. P4 locks the **layer**; D18 locks the **tables/ops flow** when promotion graph work begins.

**Module outcome** (L5 projection): ranks / tie winner under the **scoring contract** — “what the table says.”

**Promotion** (structure): which players (or seeds) populate the **next** stage or bracket slot — “who plays in the next phase.”

These can **diverge** by organizer choice. The product must stay flexible: organizer is not locked to engine-suggested advancement from standings alone.

| Concern | Layer | Override? |
|---------|-------|-----------|
| Points, GD, KO tie winner | Scoring contract → L5 projection | Contract-driven; not promotion |
| Who advances to next stage | L4 ops ground (**P4 locked**; shape **D18**) | **Yes** — explicit organizer override allowed |
| Next stage fixture composition | L4 topology (`tournament_stage_players`, fixtures) | Materialized from override + optional rule hint |

**Implication for this track:** standings engine computes **module outcomes** only. A future **promotion** subsystem (graph + ops UI) reads L5 outcomes as **input** and writes **L4 ground** when the organizer overrides — **how** is D18.

**Analogue:** L3 `amiga_tournament_finish_override` (Tier E) at event-finish grain; promotion overrides are the same *class* of curated ops truth at **module boundary** grain.

### 2.2 Authority map (D4 locked)

**Principle:** **Registers author; DB runs.** Git registers (`StructureSpec`, `disposition_register.json`) organize knowledge and drive **materialize** flows. **`ko2amiga_work` is self-contained for simul** — simul does not read git registers. Flow: work in register → materialize/write DB when satisfied (cf. video manifest → align → DB).

| Kind | Authoring / preset | Runtime ground (simul) |
|------|-------------------|-------------------------|
| **Topology (curated)** | Git `StructureSpec` | `tournament_stages`, `tournament_fixtures` after materialize |
| **Topology (bulk legacy)** | `disposition_register.json` + handler code | Same tables after `apply-structure` |
| **Topology (live ops)** | Builder / ops UI | Same tables (no git) |
| **Scoring templates** | `tournament_format_templates.spec_json` — **presets only** | Not authoritative at runtime |
| **Scoring contract (tournament)** | — | **Explicit stored row on every `tournaments`** (target); shape D13 |
| **Scoring contract (stage)** | — | **Explicit stored row on every `tournament_stages`** (target); shape D13 |
| **Platform default** | Repo `platform_default_v1` | Copied into DB rows on create/backfill; D6 bridge until backfill complete |
| **Disposition routing** | Git register only | Not stored for simul; DB holds topology **result** |
| **Promotion override** | — | L4 ops ground (P4; shape D18) |
| **Standings projection** | — | L5 — derived only |

**Templates:** choosing a template in UI or import **writes** concrete config onto tournament/stage rows — templates are quick-choice aides, not runtime truth.

**Migration:** D6 null→`platform_default_v1` resolver is a **bridge** until every tournament/stage has explicit stored contracts (bulk backfill / materialize).

### 2.3 Copy rules (D5 locked)

**At tournament create / template apply:** copy preset (`tournament_format_templates`) or `platform_default_v1` → **write explicit contract on `tournaments` row**.

**At stage create / materialize:** copy from tournament contract by `stage_type` (`round_robin` → league profile, `knockout` → knockout profile) → **write explicit contract on `tournament_stages` row**. Organizer may edit stage row after copy.

**At finalize (D6):** freeze **effective** tournament contract (+ schema version); stage rows should already be explicit.

**At simul / standings compute:** read **`tournament_stages` scoring ground** for each module. Missing stage contract = **data defect**, not silent read-time inheritance from tournament. Tournament row remains source for **new** stages and finalize snapshot.

**D13 note:** explicit storage may be JSON columns on row; audit filters may add denormalized facets later — does not change D4 intent.

### 2.4 Module identity (D7 locked)

**Canonical key:** `tournament_stages.id` (`stage_id`) for scoring-contract binding, standings computation, and UI module surfaces.

**Engine primary path:** `amiga_games.fixture_id` → `tournament_fixtures.stage_id` → stage scoring contract.

**L5:** add `stage_id` (nullable until L4 imprint on that tournament). Target: every standings row keyed by `stage_id`. Row shape from joined `tournament_stages.stage_type` — not from `scope_type` as authority (**D9-pre**, §2.7).

**Witness preserved (not deleted):**

- `amiga_games.phase` — L3 witness of what koatd recorded; **retire inference/compute**, keep column for display and archaeology.
- Legacy `scope_type` / `scope_key` on L5 — may remain for **display / URL compat** (cf. standings scope S8); **not** engine identity at end state.

**Migration:** **C → A** — dual-write / nullable `stage_id` while events lack L4; phase parser = **transition fallback only** until imprint complete (**D10** sets retirement). Permanent canonical key via scope strings (**B**) rejected.

**Rationale:** imprint L4 on all catalog events; unify product under explicit stages. Access `Phase` was optional per-game labels (~61% NULL), not a format schema.

### 2.5 Standings executor (D8 locked)

**Job:** Given L3 game results + L4 module topology + per-stage scoring contracts, compute **module outcomes** and write or return the L5 standings projection (broadcast while running may skip L5 persist until finalize — D15).

**In scope (E1–E7):** load stage scoring contract; group games by module (`fixture → stage_id`, phase fallback during transition); apply D9 primitives; emit L5 rows; idempotent rebuild; PHP/Python parity. **One module** owns routing + math; materializers never compute standings.

**Out of scope (X1–X12):** topology materialization; template choose/copy; finalize freeze; **promotion** (reads L5 as input, P4); honours/Elo/catalog stats; disposition; L3 mutation; phase as end-state authority; **event-wide rollup / Event stats tab** (separate writer — §2.6).

**Orchestration:** executor is stateless (pure compute + write helper); `finalize` / `simul` / ops call it for **module standings only**.

**Transition:** phase fallback branch inside executor until D10; tournaments without L4 may present as uncurated in UI.

### 2.6 Event stats vs module standings (locked distinction)

Two **separate** L5 projections — different writers, different tables, different UI tabs. Do not conflate.

| | **Module standings** | **Event stats (event rollup)** |
|--|----------------------|--------------------------------|
| **Question answered** | Who ranked where **in this module**? Who won **this KO tie**? | How did each player do **across the whole event**? |
| **Writer today** | `tournament_standings.py` / `amiga_post_game_standings.php` → `rebuild_standings_for_tournament` | Finalize / replay → **event block on `amiga_player_event_snapshots`** (not standings executor) |
| **Storage today** | `amiga_tournament_standings` (`scope_type`, `scope_key` → target `stage_id`) | `amiga_player_event_snapshots` per player (`event_points`, `games`, W/D/L, GF/GA, rating delta, perf. rating, …) |
| **UI today** | Stages / standings tabs (`amiga_tournament_standings_rows`) | **Event stats** tab (`amiga_tournament_participation_rows`) |
| **D8 owner** | **Standings executor** | **Not** standings executor — finalize participation writer (§2.6); **not** a D9 executor primitive |

**Product:** Event stats stays a **permanent** tournament-wide surface (ratings, event Pts, finish) — not replaced by per-stage tabs. Imprinting L4 does not remove it.

**Legacy blur to retire:** implicit `league` + `scope_key = ''` in `amiga_tournament_standings` as a stand-in for event rollup — target model keeps event rollup on **snapshot event block** only; module rows on **`stage_id`**. Event stats tab does **not** read synthetic league rows today.

**Running tournaments:** module table may use broadcast standings (RTB); Event stats typically needs finalize (snapshots). Separate paths today and in target model.

### 2.7 Executor scoring primitives (D9 locked)

**D9 scope:** math types the **standings executor** dispatches to — bound on **stage scoring contracts** via `stage_type`. Not event rollup, snapshots, or event finish (those have their own doc homes).

#### D9-pre — L5 module identity (locked with D9)

- **Canonical key:** `stage_id` on every L5 standings row at end state.
- **`scope_type` / `scope_key`:** same arc as `amiga_games.phase` — witness / URL compat only; **retired from compute authority**. Readers join `stage_id` → `tournament_stages` for module kind.
- **`scope_type` ≠ scoring primitive:** legacy L5 storage shape (`league` \| `knockout`); not the contract type system. Target: row shape from **`stage_type`** on the joined stage row.

#### D9a — v1 executor primitive set (locked)

| Primitive | Default for `stage_type` | Writer | Output |
|-----------|--------------------------|--------|--------|
| **`league_table`** | `round_robin` | Standings executor (D8) | L5 module standings for that `stage_id` |
| **`knockout_tie`** | `knockout` | Standings executor (D8) | L5 module standings for that `stage_id` |

Swiss, double elimination, group+knockout, World Cup class, kitchen marathon — all decompose into these stage types; **no additional executor primitives in v1**.

**Today (hardcoded, pre-contract):** `league_table` → `tournament_standings._assign_positions()` (3-1-0; points → GD → GF → games); `knockout_tie` → `_knockout_positions()` (aggregate GD → GF → `extra` parse).

#### D9b — default binding on copy-on-create (locked)

When a stage row is created or materialized (D5): `round_robin` → write `league_table` on the stage scoring contract; `knockout` → write `knockout_tie`. Organizer may override the stage contract after copy. Tie-break order, points values, KO resolution details = contract **parameters** (D12–D14), not separate primitives.

#### D9-excl — explicitly out of D9 (locked)

| Concern | Owner |
|---------|--------|
| Event stats rollup (event Pts, all-phase W/D/L, ratings on snapshots) | Finalize participation — [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md); independent of stages |
| Official **event finish** (`event_finish_position`) | Honours rules — [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md); reads L5 module outcomes; not rollup math |
| Game → module routing | Executor routing (D8); phase fallback until D10 |

#### D9e — `standings_resolver` template strings (locked)

`standings_resolver` in `tournament_format_templates.spec_json` (e.g. `swiss_overall_league`, `knockout_fixture_scopes`) is **deprecated metadata** — no runtime dispatcher today. **No new formats** may rely on it. Target routing = `stage_id` + stage contract primitive. Existing values may remain until template cleanup.

#### D9f — amendment rule (locked)

v1 lists `league_table` and `knockout_tie` only. A **new executor math type** requires an explicit register amendment + executor implementation — not ad-hoc template strings. No “closed forever at N primitives” claim.

**Not primitives** (parameters or other layers): tie-break steps inside `league_table`; pen/`extra` rules inside `knockout_tie` (D12); pairing/bracket topology (L4a); `platform_default_v1` (preset bundle, D6).

### 2.8 Phase parser fallback retirement (D10 locked)

**Scope:** standings **executor** only (Python + PHP). `amiga_games.phase` column **preserved** as L3 witness — D10 retires **compute use in the executor**, not the column.

#### D10a — Fallback trigger (locked)

Phase parser (`parse_phase()` / `amiga_ops_parse_phase()`) runs **only when `fixture_id IS NULL`** on that game — **per-game** grain. Primary path: `fixture_id` → `stage_id` → stage contract → D9 primitive.

#### D10b — During catalog gap (locked)

No special product behaviour while imprint is incomplete (e.g. 515/605 tournaments linked today). Mixed tournaments: each game uses fixture path or fallback side by side until materialize finishes the catalog. No “uncurated” badge required for D10.

#### D10c — Retirement rule (locked)

Fallback remains in the executor until:

1. **100% fixture linkage** — zero `fixture_id IS NULL` games across the catalog, and
2. **One full audit** — standings parity / verify-structure confirms fixture-only routing matches current outputs.

**Then** remove the executor fallback branch (Python + PHP). No indefinite dead-code retention “just in case.”

Post-retirement unlinked games = data corruption (verify/ops), not a separate D10 policy.

#### Deferred (not D10)

- `parse_phase()` in materialize/import paths until those slices rework parser usage
- `legacy_inferred` template flag semantics / cleanup
- Per-tournament retirement flags or coverage thresholds

### 2.9 Match extensions — `extra` and structured L3 (D12 locked)

**Principle:** Match extensions (extra time, penalties, and similar) are **structured L3 ground** on the match/fixture row at end state. `amiga_games.extra` (and fixture running `extra`) remain **human witness text** for display and import archaeology — **not** compute authority once structure exists.

**Legacy:** Text-based penalty parsing in the standings executor (`parse_standings_winner` / PHP parity) is **transitional**. Do **not** extend it. **Retire** when structured fields cover the catalog and a parity audit passes (same retirement habit as D10).

**Out of this register (implementation slice):** column DDL, import backfill, ops entry, deterministic `knockout_tie` resolution chain over structured fields, step enums, golden goal, pre-structure fallback behaviour. League tables continue to use regulation goals unless a future format explicitly requests otherwise.

### 2.10 Scoring contract serialization (D13 locked)

**Canonical runtime ground = relational L4b** on `tournaments` + `tournament_stages` — typed columns and/or small normalized tables. **Not** JSON-canonical scoring contracts with a later port to DB.

| ID | Rule |
|----|------|
| **D13a** | Relational L4b is **runtime authority** at simul. `tournament_format_templates.spec_json` and git presets remain **authoring/presets only** — copy into DB rows on create/backfill (same pattern as topology materialize). |
| **D13b** | **Reject JSON-first** scoring ground with a planned migration to relational — implement relational shape in the scoring-contract slice. |
| **D13c** | **Two grains:** tournament row (defaults + finalize snapshot) and stage row (runtime authority per module, D5). |
| **D13d** | **Tie-break order** and similar ordered rule chains are **relational** (e.g. child rows per stage with sequence + step enum) — not an ordered JSON array inside a blob. Exact DDL = implementation slice + D14 step enum. |
| **D13e** | **D6 freeze** = copy **relational contract fields** onto the tournament frozen snapshot (columns) at finalize — not freeze a JSON blob. |

**Deferred (slice / D14):** exact column names, whether contract is columns-on-row vs `scoring_contract` + `tiebreak_step` tables, backfill from hardcoded 3-1-0, export-pack column list.

### 2.11 Schema version and step enums (D14 locked)

**`scoring_schema_version`:** integer, starts at **`1`** on tournament + stage contract rows. Unknown version at executor read = **hard error** (no silent default). D6 finalize copies version into tournament frozen snapshot columns (D13e).

**Closed step enums** (relational child rows, D13d) — two vocabularies by primitive:

| `league_table` steps | `knockout_tie` steps |
|----------------------|----------------------|
| `points` | `aggregate_goal_difference` |
| `head_to_head` | `extra_time` |
| `goal_difference` | `penalty_shootout` |
| `goals_for` | `golden_goal` |
| `games_played` | |

Steps in enum ≠ steps in default chain. Per-stage chains may use any subset/order allowed for that primitive.

**`platform_default_v1` (repo relational seed, D6):** copied to DB on create/backfill.

| Primitive | Default chain |
|-----------|----------------|
| `league_table` | `points` → `goal_difference` → `goals_for` → `games_played` |
| `knockout_tie` | `aggregate_goal_difference` → `extra_time` → `penalty_shootout` |

**Not in defaults (enum only or audit later):** `head_to_head` on leagues (used on many events — audit catalog later for where it changes outcomes vs default). `golden_goal` on knockouts. No `aggregate_goals_for` (redundant after aggregate GD for standard two-leg ties). Legacy text penalty parse (D12) is transition only — not in default chain.

**Points (v1 default):** win = 3, draw = 1, loss = 0.

**Verify:** structural contract validity CLI in modern verify suite (known version, valid primitive, non-empty chains, steps ∈ enum, points present) — not standings numeric parity.

**Deferred (implementation slice / D17):** exact enum spellings in DDL, CHECK constraints, `tiebreak_profile` preset table, executor implementation of `head_to_head` / structured ET+pens, PHP/Python contract reader.

---

## 3. Vocabulary (working definitions)

Use consistently in this track:

| Term | Meaning |
|------|---------|
| **Module** | One `tournament_stages` atom: `round_robin` (player-set RR) or `knockout` (one 2-player tie). |
| **Fixture result** | L3 ground: regulation goals (+ `extra` witness) for one match. |
| **Module outcome** | Placement **within one module**: RR rank table; KO winner/loser. Not the same as fixture results. |
| **Module standings projection** | Derived L5 rows in **`amiga_tournament_standings`** — per module (`stage_id` target); **standings executor** writes these. |
| **Event rollup / Event stats** | Derived **event-wide** per-player summary — **`amiga_player_event_snapshots`** / participation today; **not** standings executor; not a D9 primitive. |
| **Scoring primitive** | Executor math type on a **stage contract** (`league_table`, `knockout_tie`) — D9 §2.7. Distinct from L5 `scope_type` and from event rollup. |
| **Scoring contract** | Ground/config on tournament/stage row: names a **primitive** + parameters (points, tie-break chain, KO resolution chain). |
| **Standings projection** | Shorthand for **module standings** (`amiga_tournament_standings`) — not Event stats. |
| **Scope routing** | Which games feed which module/table (fixture → stage path vs legacy `phase` parser). |

Policy T14 (“module outcomes on stage”) describes **module outcomes** as what a future promotion graph reads. Today they are **not** stored as first-class `stage_id → ranks`; they appear only via L5 scope rows or recompute.

---

## 4. Layer model (D3 deferred; D4 locked)

```text
L3   Match results           amiga_games (+ extra); running cols on fixtures until official
L4a  Topology                tournament_stages, tournament_fixtures, entrants
L4b  Scoring contract        explicit on tournaments + tournament_stages (D4); templates = presets only
L5a  Module standings       amiga_tournament_standings (standings executor)
L5b  Event rollup           amiga_player_event_snapshots event block (finalize writer; Event stats tab)

GIT  Authoring registers     StructureSpec, disposition_register (materialize → DB; not read at simul)
REPO platform_default_v1     copied into DB on create/backfill
```

**D3 (deferred):** Document **L4a / L4b** split in policy without new pipeline numbers.

---

## 5. JSON vs normalized storage (audit notes — no decision)

The repo already uses **JSON inside MySQL** (`spec_json`, `format_overrides`, `config_json`). The real question is **blob vs normalized vs split authority** — and **at which grain**.

### When JSON-in-DB is a good fit

- Small nested rule documents; template catalog already uses `spec_json`.
- Per-tournament overrides without DDL per tie-break variant.
- Export packs (staging pull) must carry config with ground.

### When JSON-in-DB is weak

- **Audit UI filters** — e.g. “all tournaments with 3-1-0 not 2-1-0”, “league stages where GD tie-break comes before head-to-head”. Opaque JSON makes SQL filters painful unless you **denormalize key facets** (columns or generated columns).
- **PHP/Python parity** without a strict versioned schema.
- **Finalize audit** — template row can change after event completed unless contract is **frozen on the tournament**.

### Open sub-question (raised in chat)

`config_json` on stages may be **insufficient** if ops needs relational audit filters. Alternatives to weigh under **D13**:

- Normalized `scoring_contract` / `tiebreak_step` tables.
- Hybrid: JSON document + **indexed denormalized columns** (`points_win`, `tiebreak_profile_slug`, …).
- Query layer outside MySQL (export + audit script) for bulk review — acceptable for batch disposition work only?

**D13 / D14** decide serialization **after** authority and precedence (D4–D6) are settled.

---

## 6. Decision register

Work through in order. Mark **Status:** `open` | `draft` | `locked` in chat; update this table when locked.

### Tier 0 — Intent

| ID | Decision | Status |
|----|----------|--------|
| **D0** | Problem statement (§1) — confirm wording | **locked** |

### Tier 1 — Conceptual model

| ID | Decision | Status / outcome |
|----|----------|------------------|
| **D1** | Module vs module outcome | **locked** — derived; L5 cache only |
| **D2** | Scoring contract vs standings projection | **locked** — strict A |
| **D3** | L4 unified vs L4a/L4b documented split | open (defer) |

### Tier 2 — Authority and placement

| ID | Decision | Status |
|----|----------|--------|
| **D4** | Where each truth lives | **locked** — §2.2 |
| **D5** | Precedence / copy rules | **locked** — §2.3 |
| **D6** | Platform default + freeze on finalize | **locked** — §2 |

### Tier 2b — Promotion (structure graph track)

| ID | Decision | Status |
|----|----------|--------|
| **D18** | Promotion override storage | open — P1–P4 locked (§2.1); exact tables/ops flow TBD at promotion-graph slice |

### Tier 3 — Identity and engine

| ID | Decision | Question |
|----|----------|----------|
| **D7** | Canonical module key | **locked** — §2.4 |
| **D8** | Standings executor scope | **locked** — §2.5 (module standings only); §2.6 event stats separate |
| **D9** | Executor scoring primitives | **locked** — §2.7 (`league_table`, `knockout_tie`; D9-pre L5 `stage_id`; `standings_resolver` deprecated; D9f amendment) |

### Tier 4 — Legacy and coverage

| ID | Decision | Question |
|----|----------|----------|
| **D10** | Phase parser fallback | **locked** — §2.8 (NULL `fixture_id` only; 100% linkage + audit → delete branch) |
| **D11** | Disposition register | **locked** — §2.2; handler = materializer only |
| **D12** | `extra` / match extensions | **locked** — §2.9 (structured L3 target; `extra` witness; retire text parse) |

### Tier 5 — Data format

| ID | Decision | Question |
|----|----------|----------|
| **D13** | Scoring contract serialization | **locked** — §2.10 (relational L4b; no JSON-canonical; D6 freeze columns) |
| **D14** | Schema version + step enums | **locked** — §2.11 (`scoring_schema_version` 1; enums; `platform_default_v1` chains; verify CLI) |

### Tier 6 — Runtime and product

| ID | Decision | Question |
|----|----------|----------|
| **D15** | Running vs finalized | Broadcast compute uses same contract as finalize (RTB alignment). |
| **D16** | Export packs | **locked (intent)** — self-contained scoring ground in dump; §2.2 |
| **D17** | PHP/Python contract reader | Single shape; two implementations; no policy drift. |

---

## 7. Session plan

Take **one tier per discussion block** where possible. Record outcomes inline under each decision ID.

### Session A — Concept + authority (D0–D6)

**Status:** **Complete (2026-07-09)** — D0, D1, D2, D4, D5, D6, D11, D16 (intent), P1–P4 locked.

### Session B — Keys + engine (D7–D9)

**Status:** **Complete (2026-07-09)** — D7–D9 locked (§2.4–§2.7).

### Session C — Legacy + format (D10–D14)

**Status:** **Complete (2026-07-09)** — D10–D14 locked (§2.8–§2.11).

### Session D — Runtime (D15–D17)

**Outcome target:** RTB parity, export self-containment, shared contract loader spec.

**After all sessions:** Promote locked decisions into policy docs + implementation plan slice(s).

---

## 8. Recommended tackle order (within sessions)

If not following full tier order:

1. **D2** — contract vs projection (philosophy).
2. **D4** — where truth lives (structural).
3. **D7** — `stage_id` as module key (unblocks honours, UI, promotion).
4. **D5, D6** — precedence and freeze.
5. **D13, D14** — format (informed by audit-filter need).
6. Remaining IDs as needed.

---

## 9. Authority map (locked — D4)

See §2.2 for full table. Summary:

| Kind | Authoring | Runtime (simul) |
|------|-----------|-----------------|
| Topology | Git register / disposition → materialize | DB stages/fixtures |
| Scoring | Templates + repo default = presets | Explicit tournament + stage rows |
| Routing | `disposition_register.json` | Not used |
| Promotion | — | L4 ops (D18) |
| Standings | — | L5 derived |

---

## 10. Current-state anchors (Jul 2026)

Facts for discussion — not targets:

- **L4 materialized** on `ko2amiga_work`: 515/605 catalog tournaments (full fixture linkage).
- **Scoring rules:** global 3-1-0 + GD + GF + games; KO aggregate GD → GF → `extra` parse — in Python + PHP only.
- **`standings_resolver` in template JSON:** deprecated (D9e); no dispatcher.
- **L5 key today:** `(tournament_id, scope_type, scope_key)` — not `stage_id`.
- **Structure specs in git:** Homburg active; most `structure_spec` disposition rows not yet materialized.

---

## 11. Changelog

| Date | Change |
|------|--------|
| 2026-07-09 | **Session C complete** — D14 locked §2.11; D10–D14. |
| 2026-07-09 | **D14 locked** — schema v1, step enums, `platform_default_v1` chains, verify CLI intent. |
| 2026-07-09 | **D10 locked** — phase fallback §2.8: NULL `fixture_id` only; 100% linkage + parity audit → delete executor branch. |
| 2026-07-09 | **Session B complete** — D7–D9 locked. |
| 2026-07-09 | **§2.6** — Event stats vs module standings: separate writers/tables; D8 excludes event rollup. |
| 2026-07-09 | **D8 locked** — standings executor scope §2.5; orchestration split. |
| 2026-07-09 | **D7 locked** — `stage_id` canonical; `phase`/scope witness+skin; C→A; D10 retires phase fallback. |
| 2026-07-09 | **Session A complete** — D4, D5, D11, D16 (intent) locked; §2.2 authority map, §2.3 copy rules. |
| 2026-07-09 | **P4 locked** — promotion overrides = L4 ops ground only; D18 remains for storage shape. |
| 2026-07-09 | §2.1 split: P1–P3 locked; D18 added for promotion override storage (deferred). |
| 2026-07-09 | **Session A partial** — D0, D1, D2 (strict), D6 (platform default + finalize freeze) locked; §2.1 promotion. |
| 2026-07-09 | Initial discussion plan from L4/L5 boundary design chat (problem identified; sessions A–D outlined). |
