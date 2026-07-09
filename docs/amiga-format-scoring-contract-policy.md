# Amiga format scoring contract — policy (Jul 2026)

> **Product policy:** Scoring rules are **ground truth** (L4b), stored relationally. Standings rows are **derived** (L5). The standings **executor** reads contracts — it does not own format semantics.

**Status:** **Policy locked (Jul 2026)** — promoted from design sessions A–D. History and decision IDs: [`amiga-format-scoring-contract-design-discussion.md`](amiga-format-scoring-contract-design-discussion.md).

**Implementation:** [`amiga-format-scoring-contract-implementation-plan.md`](amiga-format-scoring-contract-implementation-plan.md)

**Related:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (L4a modules) · [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) (L5 `scope_type`) · [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings · [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §9 · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (RTB broadcast) · [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) (event finish)

---

## 1. Four concerns (do not conflate)

| Concern | Layer | Storage (target) |
|---------|-------|------------------|
| **Topology** | L4a | `tournament_stages`, `tournament_fixtures`, entrants |
| **Scoring contract** | L4b | Relational rows on `tournaments` + `tournament_stages` (+ step child rows) |
| **Module standings** | L5a | `amiga_tournament_standings` (executor output) |
| **Event rollup / Event stats** | L5b | `amiga_player_event_snapshots` / participation (finalize writer) |

**Registers author; DB runs.** Git (`StructureSpec`, `disposition_register.json`, template `spec_json`) drives **materialize** only. **Simul reads MySQL only** — no git at runtime.

---

## 2. Core decisions (locked)

| # | Decision | Rule |
|---|----------|------|
| **SC1** | **Contract vs projection** | Scoring contract = **L4b ground**. `amiga_tournament_standings` = **derived cache** only. |
| **SC2** | **Module outcomes derived** | Ranks / tie winners are computed; no ground `stage_rankings` table in v1. |
| **SC3** | **Relational L4b** | Runtime scoring ground = **typed DB rows** — not JSON-canonical blobs. Templates/git = **presets** copied on create/backfill. |
| **SC4** | **Two grains** | **Tournament** row: defaults + **frozen snapshot** at finalize. **Stage** row: **runtime authority** for standings compute. |
| **SC5** | **Copy-on-create** | `round_robin` stage → `league_table`; `knockout` stage → `knockout_tie` on stage contract unless overridden. |
| **SC6** | **Freeze at finalize** | Copy effective relational contract (+ `scoring_schema_version`) to tournament frozen columns — not a JSON blob. |
| **SC7** | **`stage_id` canonical** | Module identity for compute/UI. `amiga_games.phase` and L5 `scope_type`/`scope_key` = **witness/compat** only (retired from compute authority). |
| **SC8** | **Standings executor** | One module: load contract → route games → apply primitive → write/return L5a. **Not:** promotion, honours, Elo, event rollup, topology. |
| **SC9** | **Executor primitives (v1)** | `league_table`, `knockout_tie` only. New primitives require explicit policy amendment. |
| **SC10** | **Phase parser fallback** | `parse_phase()` in executor **only when `fixture_id IS NULL`** (per-game). Remove executor branch after **100% fixture linkage + parity audit**. |
| **SC11** | **Match extensions** | ET/pens target **structured L3** fields; `extra` = witness text. Retire text penalty parse when structured coverage + audit pass. |
| **SC12** | **Event stats separate** | Event-wide rollup + Event stats tab = finalize participation writer — **not** standings executor. |
| **SC13** | **Event finish separate** | `event_finish_position` = honours rules reading L5a — not rollup math. |
| **SC14** | **RTB alignment** | Broadcast (fixtures) and official (`amiga_games`) use **same contracts + executor**; broadcast **does not persist L5**. Live hub **league + KO** in scope. |
| **SC15** | **PHP/Python parity** | Single v1 contract reader shape; both runtimes; **PHP↔Python parity oracle** on shared inputs. |
| **SC16** | **Export self-contained** | Staging dump includes explicit scoring ground; import site does not require git templates to rebuild. |
| **SC17** | **`standings_resolver` deprecated** | Template JSON resolver strings are not runtime dispatch — routing = `stage_id` + stage contract. |
| **SC18** | **Promotion ≠ standings** | Promotion overrides = L4 ops ground only (shape: deferred **D18**). Never fake L5 `position`. |

---

## 3. Schema version and step enums (v1)

- **`scoring_schema_version`:** integer, starts at **`1`**. Unknown version at read = **hard error**.
- **Ordered chains** (tie-break / KO resolution): **relational child rows** (`sequence_no` + step enum) — not JSON arrays.

### `league_table` steps (enum)

`points` · `head_to_head` · `goal_difference` · `goals_for` · `games_played`

### `knockout_tie` steps (enum)

`aggregate_goal_difference` · `extra_time` · `penalty_shootout` · `golden_goal`

### `platform_default_v1` (default chains)

| Primitive | Default chain |
|-----------|----------------|
| `league_table` | `points` → `goal_difference` → `goals_for` → `games_played` |
| `knockout_tie` | `aggregate_goal_difference` → `extra_time` → `penalty_shootout` |

**Points:** win = 3, draw = 1, loss = 0.

**Not in defaults:** `head_to_head` (enum only — catalog audit later); `golden_goal`. No `aggregate_goals_for` after aggregate GD (redundant for standard two-leg ties).

---

## 4. Vocabulary: `scope_type` vs scoring primitive

| Term | Meaning |
|------|---------|
| **`league_table` / `knockout_tie`** | Scoring **math** primitive on stage contract (D9). |
| **`league` / `knockout` on L5** | **Storage shape** on `amiga_tournament_standings` ([`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md)). |
| **`stage_id`** | Canonical module key on L5 rows (target). |
| **Synthetic `league` + `scope_key = ''`** | Standings aggregate for mixed-phase display — **not** Event stats source. |

---

## 5. Writers (who computes what)

| Output | Writer | Reads |
|--------|--------|-------|
| L5a module standings | Standings executor | L3 games/fixtures + L4b stage contracts |
| L5b event rollup (Event stats) | Finalize participation | L3 games + rating batch |
| Event finish | Honours / finalize | L5a module outcomes |

---

## 6. Bridge (today → target)

**Today:** 3-1-0 + GD/GF/games and KO aggregate GD/GF + `extra` text parse are **hardcoded** in `tournament_standings.py` / `amiga_post_game_standings.php`. Explicit relational contracts **not yet shipped**.

**Bridge:** `platform_default_v1` resolver copies preset into DB on backfill; retire hardcoding after contract rows + executor refactor + verify green on **`ko2amiga_work`** simul.

---

## 7. Deferred (other tracks)

| Topic | Where |
|-------|--------|
| L4a / L4b doc split label only | Design **D3** |
| Promotion override storage | **D18** / structure graph track |
| Match extension DDL + structured ET/pens | Implementation slice (see SC11) |
| Exact contract DDL table names | [`amiga-format-scoring-contract-implementation-plan.md`](amiga-format-scoring-contract-implementation-plan.md) |

---

## 8. Agent policy

- **Read first:** this policy + structure policy T1–T14 for modules vs structure.
- **Do not** add per-tournament scoring rules only in Python/PHP constants after backfill ships.
- **Do not** extend `parse_standings_winner` regex (SC11).
- **Verify:** structural contract verify + PHP↔Python parity oracle (when implemented) in modern verify suite on work DB.
- **Steve / migration:** new L4b DDL = Part B when schema ships.
