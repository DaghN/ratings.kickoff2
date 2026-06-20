# Amiga tournament structure — policy (modules vs structure)

**Status:** **In progress** — slices 1–2 shipped Jun 2026 (migration `023`); slice 3 pilot **revised** (policy v2 Jun 2026).  
**Purpose:** Lock how we model **tournament modules** separately from **event structure** (composition, promotion, rounds, tracks), and how **legacy import** materializes fixtures without inventing draw-order schedules.

**Authority:** This doc owns **module taxonomy**, **structure vs semantics split**, and **legacy backfill rules** — i.e. **L2 structure overlay** in [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md). Standings tally primitives: [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md). Honours finish: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md). Table register: [`amiga-data-contract.md`](amiga-data-contract.md).

**Implementation:** [`amiga-tournament-structure-implementation-plan.md`](amiga-tournament-structure-implementation-plan.md) · starter: [`archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md) · **restart handoff (policy v2):** [`archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md)

**History:** Jun 2026 exploration — modules vs structure. Jun 2026 policy v2 — atomic KO **tie** module; bracket **rounds** in structure only; NULL-phase auto-classify **high-confidence RR only**, flag the rest. Jun 2026 policy v2.1 — **stage / fixture / game** chain locked (fixture = one match, one result; universal for live and legacy).

---

## 1. Four concepts (do not conflate)

```text
MODULE (stage)     tournament_stages     RR scope or KO tie — the atom
  └── FIXTURE      tournament_fixtures     exactly one match → exactly one result
        └── GAME   amiga_games             score row (goals, ET, pens, …)

STRUCTURE (separate artifact)
  rounds, tracks, promotion, placement bands — references stage IDs, reads module outcomes
```

| Concept | Table | Meaning |
|---------|-------|---------|
| **Stage / module** | `tournament_stages` | Atom. `stage_type`: `round_robin` (player set) or `knockout` (one tie between two players). **`tournament_stages.id` is the canonical module handle** for structure graphs. |
| **Fixture** | `tournament_fixtures` | **One match, one result.** Always belongs to exactly one stage (`stage_id`). Never a multi-leg bundle in a single row. |
| **Game** | `amiga_games` | Canonical **score** row. Links to fixture via `fixture_id` when fixture-backed. |

**Two-leg knockout:** one **KO stage** (the tie) + **two fixtures** (leg 1, leg 2) + **two games**. `leg_no` orders legs within the tie; it does **not** mean “one fixture contains multiple legs.”

**Live vs legacy (same schema, different creation order):**

| Path | Fixtures created by… | Games |
|------|----------------------|-------|
| **Live** | Tournament software (schedule first) | Filled when results entered (`record-result`, ops) |
| **Legacy** | Materialize from imported games (results first) | Already ground truth; fixtures derived and assigned to stages |

Do **not** maintain a legacy-only path that skips fixtures and points games directly at stages. The fixture layer is universal; only **provenance** differs.

| Question | Answered by | Examples |
|----------|-------------|----------|
| **What module is this match in?** | `fixture → stage → stage_type` | RR table; KO tie |
| **Where is the score?** | `amiga_games` (linked via `fixture_id`) | goals, extra time |
| **Who ranked / who won the module?** | Derived on the **stage** (RR table or tie winner) | structure reads this later |
| **How do modules combine?** | `StructureSpec`, promotion graph | groups → KO; dinner 1v2/3v4 |

**UI rule:** Every match is either part of **an RR table** or **a 2-player elimination tie** — two module types, easy to display.

**Not a third type:** “Knockout round” (R16, QF, SF) is **structure metadata** grouping several KO ties — not different result physics.

---

## 2. Core decisions (locked)

| # | Decision | Rule |
|---|----------|------|
| **T1** | **Two module types (v1)** | `tournament_stages.stage_type` is **`round_robin`** or **`knockout`** only. |
| **T2** | **Round-robin module** | A **player set** + games among them → points table semantics (`standings.scope_type = league`). |
| **T3** | **Knockout module = one tie** | **Exactly two players**; one or more leg games; tie-winner semantics (`standings.scope_type = knockout`). **Legacy materialize:** one `knockout` stage per tie (not one stage per event, not “knockout round” as type). |
| **T4** | **No `placement` stage type** | Placement bands, 3rd-place finals, dinner 1v2/3v4 = **`knockout` ties** + **structure/pairing** rules. |
| **T5** | **Retire `league` + `group` stage types** | Collapse to **`round_robin`**. Singleton marathon vs Group A of eight = **structure** (`stage_key`, `group_key`), not type. |
| **T6** | **Vocabulary: `league` on standings** | `scope_type = league` = points-table tally primitive only — not a stage type. |
| **T7** | **Games authoritative (legacy)** | Pairings, scores, Team A/B from `amiga_games`. No draw-order RR generation for completed events. |
| **T8** | **Fixture = one match** | One `tournament_fixtures` row = exactly one match = exactly one result. Legacy v1: one fixture per imported game. Multi-leg KO = multiple fixture rows in one KO stage. |
| **T9** | **Fixture universal** | Live and legacy both use `tournament_fixtures`. Live: schedule fixtures then fill games. Legacy: materialize fixtures from games then assign to stages. **Do not** skip fixtures on legacy. |
| **T10** | **Side parity** | `fixture.player_a_id = game.player_a_id` AND `fixture.player_b_id = game.player_b_id`. |
| **T11** | **NULL phase — auto RR when complete** | All phases NULL **and** `game_count = k×n×(n−1)/2` for integer **k ≥ 1** **and** every player has exactly **`(n−1)×k`** games → one `round_robin` / `overall` stage (single- or multi-leg RR). **Otherwise → `needs_structure_review`** — do **not** auto-materialize; do **not** infer knockout. Per-player equality is mandatory (catches data quirks at correct totals). |
| **T12** | **Incomplete RR stays RR** | Withdrawal / early exit → partial schedule → still **`round_robin`** once classified (manual or curated spec) — never “failed RR math ⇒ knockout”. |
| **T13** | **Labeled phases** | RR labels → one `round_robin` stage per scope bucket. KO labels → **one `knockout` stage per tie** (player pair); **one fixture per game** in that stage. |
| **T14** | **Module outcomes on stage** | RR: rank table keyed by `stage_id`. KO: tie winner/loser derived from fixtures/games in that `stage_id`. Structure graph references **stage IDs** and reads these outcomes (standard or special rules in spec). |
| **T15** | **Resolver precedence** | `fixture_id` present → scope from fixture → stage → `stage_type`. Else phase parser fallback. |
| **T16** | **Structure graph deferred** | Full promotion engine not required for bulk RR backfill. Rounds/bracket layout in `StructureSpec` (manual for NULL-phase cups). |
| **T17** | **Catalog flags** | Recompute `has_league` / `has_cup` from stages after backfill (later slice), not Access-only heuristics. |
| **T18** | **Steve WC source** | Structure reference for modern WCs; games remain ground truth. |
| **T19** | **No koatd patches** | Import layer / version-controlled specs only. |
| **T20** | **Rejected: NULL ⇒ KO heuristic** | `not full RR schedule` must **not** imply knockout (slice 3 pilot mistake — reverted). |
| **T21** | **Rejected: fixture as tie container** | A fixture must **not** span multiple legs or multiple results. The tie is the **stage**; legs are separate fixtures. |
| **T22** | **Rejected: legacy without fixtures** | Removing the fixture layer for legacy adds a second code path; keep fixtures, differentiate creation order only (T9). |

### Generated / curated specs (Homburg, live builder)

`StructureSpec` **may** use stage rows as **round containers** (e.g. `ko-semi` holding several ties) for ops UX on **generated** tournaments. **Legacy materialize** uses **per-tie** KO stages (T3). Structure graph / `round_key` on fixtures is authoritative for round grouping in both cases.

---

## 3. Data model

```text
CANONICAL (ground)
  tournament_stages.id          module atom (RR scope | KO tie)
  tournament_fixtures           one match; fixture.stage_id → stage
  amiga_games.fixture_id        score row → fixture (when linked)

STRUCTURE (evolving)
  StructureSpec, round_key on fixtures, promotion edges
  references stage_id; reads module outcomes

DERIVED
  amiga_tournament_standings      scope_type league | knockout
  event_finish_position           tournament-level (honours); walks structure over stages
```

### Pointer chain (always)

```text
amiga_games.fixture_id  →  tournament_fixtures.id  →  tournament_stages.id
```

Scores live on **games**. Match identity (players, stage, status, leg order) lives on **fixtures**. Module physics live on **stages**.

### Module type → standings

| `stage_type` | Module | Standings `scope_type` |
|--------------|--------|-------------------------|
| `round_robin` | Player-set RR scope | `league` |
| `knockout` | One 2-player tie | `knockout` |

### Examples

| Event | Modules | Structure (not type) |
|-------|---------|----------------------|
| Kitchen marathon | 1 × `round_robin` | `kitchen_marathon` |
| Incomplete marathon (withdrawal) | 1 × `round_robin` (partial) | manual confirm or audit |
| WC Group A | 1 × `round_robin` | one of eight parallel groups |
| Semi-final tie (2 legs) | 1 × `knockout` stage | 2 fixtures, 2 games; `round_key: semi` in structure |
| Athens IV NULL cup | **manual** `StructureSpec` | bracket rounds unknown from phases |
| Dinner 1v2, 3v4 | N × `knockout` ties | placement band in structure |

---

## 4. Structure disposition register (locked Jun 2026)

**Product rule:** Every imported tournament has **exactly one** row in a **master disposition register**. Import **never infers** handler from omission — no tournament is processed by a default path because it was “left over” with the marathons.

```text
tournament_id  →  handler  →  shared script module
     1         →  pure_rr  →  materialize_pure_rr()
   413         →  pure_knockout → materialize_pure_knockout()
   137         →  structure_spec → apply_structure_spec(homburg)
   158         →  pending_review → skip structure (logged)
```

| Concept | Meaning |
|---------|---------|
| **Register row** | One catalog `tournament_id` (stable across re-imports) |
| **Handler** | Named script path — many tournaments share one handler |
| **No row** | **Verify fails** (coverage gap) — generator must assign every catalog id before import; use `pending_review` for unsettled, never omit |

This replaces scattered allow/block lists (`NON_WC_TIER_B_AUTO_MATERIALIZE_IDS`, `STRUCTURE_REVIEW_TOURNAMENT_IDS`, …) with **one routing table**. Review chats **change the handler** for an id; they do not “approve materialize.”

### Handler types (v1)

| Handler | When assigned | Script behaviour |
|---------|---------------|------------------|
| **`pure_rr`** | NULL-phase complete k-leg RR verified | One `round_robin` / `overall` stage; all games linked |
| **`pure_knockout`** | Human confirmed: elimination ties only | Group by `(phase, player pair)` → one `knockout` stage per tie; `leg_no` from chrono order within tie |
| **`structure_spec`** | Multi-stage / groups / exotic KO | `apply_structure_spec(spec_slug)` — Homburg, Norwegian Champs, … |
| **`pending_review`** | Not settled | **Skip** structure apply; **log** id + name; import continues (games, replay, phase-parser standings OK). Promote to a real handler when review settles. |
| **`no_games`** | Empty catalog row | Skip |
| **`wc_deferred`** | World Cup awaiting WC spec track | `pending_review` until promoted to `structure_spec` or `pure_knockout` |

Optional **per-row hints** (not a different handler): e.g. `"two_leg_phases": ["Semi Finals"]` on a `pure_knockout` row when leg grouping needs a nudge.

### Register file

- **Location:** `scripts/amiga/tournament_structure/disposition_register.json` — **603 rows** (Jun 2026 bootstrap).
- **Coverage:** all ~603 imported tournaments — including ~503 marathons bulk-assigned `pure_rr` by generator, not by runtime inference.
- **Maintenance:** `audit-inventory` / generator proposes handler; human review **promotes** ids (e.g. `pending_review` → `pure_knockout`).
- **Coverage goal:** every imported `tournaments.id` has a row — unsettled events are **`pending_review`**, not missing. Target: full coverage after review track (Jun 2026).
- **Verify:** `verify-disposition-register` — 100% id coverage; no unknown handlers; optional report of `pending_review` count.

### Why every id is listed (including marathons)

Prevents **shoehorn accidents**: a NULL-phase cup or broken import left unregistered cannot silently match RR math and get a marathon stage. If it’s not in the register, import stops. If it’s `pure_rr`, that was **written explicitly** (even if via bulk generator).

### Relation to old “tiers A/B/C/D”

Tiers remain useful as **audit vocabulary**; **import reads handlers only**:

| Old tier | Typical handler |
|----------|-----------------|
| A | `pure_rr` |
| B cup (graduated) | `pure_knockout` |
| C (unsettled) | `pending_review` |
| D | `structure_spec` |

---

## 4b. Legacy classification tiers (audit vocabulary)

| Tier | Condition | Action |
|------|-----------|--------|
| **A — auto RR** | NULL phase + complete k-leg RR (`k×n×(n−1)/2` games, equal per-player) | `materialize` → one `round_robin` stage; bulk OK (slice 5) |
| **B — labeled** | Phase text on games | Bucket RR scopes / KO ties from parser. **Slice 6:** non-WC only (41 auto-OK). **23 WCs** → WC track (6wc). |
| **C — review** | NULL phase + incomplete RR, cups, uneven per-player at k× total, **`STRUCTURE_REVIEW` / `NON_WC_STRUCTURE_REVIEW_IDS`** audit flags | **Flag** (`needs_structure_review`); **no auto materialize**; human triage or `StructureSpec` |
| **D — curated** | Registry `StructureSpec` | `apply` path (Homburg, future Athens IV) |

**Multi-leg NULL-phase marathons** (home-and-away, 2×/3×/4× RR): tier **A** when per-player game counts match `(n−1)×k`. **Duesseldorf V** (id=416): tier **C** — 3× game total but uneven per-player; listed in `STRUCTURE_REVIEW_TOURNAMENT_IDS`.

**NULL-phase cups** (Athens IV): tier **C** until curated — do not infer rounds or event-wide KO stage.

**Slice 6 (legacy):** interim frozensets in [`tier_b_non_wc_register.py`](../scripts/amiga/tournament_structure/tier_b_non_wc_register.py) — **superseded by disposition register** for import routing. Review: [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

---

## 5. Legacy import behaviour

**Product requirement (import closure):** A fresh koatd import must be **complete when `python -m scripts.amiga run` finishes** — no separate materialize steps. Import reads the **disposition register** (§4): for each tournament, dispatch the named handler. **`pending_review` skips structure with a log** — does not block import. **Missing register row** fails verify (coverage gap); bootstrap generator assigns `pending_review` to unsettled ids so imports stay unblocked while review progresses.

| Data artifact | Role |
|---------------|------|
| **Disposition register** | `disposition_register.json` — **603** ids → handler — **bootstrap shipped** |
| **Pure knockout handler** | `pure_knockout.py` + preview/materialize CLI — **shipped** |
| Handler dispatch in `run` | Slice 10 — not wired yet |

**Review deliverable:** promote id from `pending_review` → correct handler (or add `structure_spec` slug). Not “bless auto script.”

**Current gap:** handler **dispatch** in `import_access.py` / `run` (slice 10). Register + `pure_knockout` handler shipped; bulk tier CLIs are dev repair only.

---

## 6. Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| `not full RR` ⇒ single knockout stage | Misclassifies incomplete RR; conflates event with tie |
| Knockout round as third stage type | Rounds are structure; physics unchanged per tie |
| `league` vs `group` stage types | Encodes structure in enum |
| Infer bracket from NULL-phase game graph | Spaghetti; manual structure for ambiguous events |
| Block all import on promotion engine | Tier A bulk does not need graph |
| Skip fixtures for legacy; `stage_id` on games only | Two code paths; live still needs fixtures; structure has no uniform match handle |
| Fixture row spans multiple legs / results | Tie is the stage; each leg is its own fixture + game |

---

## 7. Out of scope

- Live WC generator UI  
- Full auto promotion for all 603 events  
- Online `kooldb*`  
- Staging export (Dagh deploys)

---

## 8. Related documents

| Doc | Relation |
|-----|----------|
| [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) | Standings tally layer |
| [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) | Background |
| [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md) | Policy v2 + rollback + resume slices |

---

*Policy v2.1 — Jun 2026: stage = module atom; fixture = one match (live + legacy); structure references stages.*
