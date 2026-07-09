# Amiga format scoring contract — design discussion plan (Jul 2026)

**Status:** **In discussion** — Session A partial complete (D0, D1, D2, D6 locked); D4+ open.  
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

## 2. Locked decisions (Session A — partial)

Record of agreed intent. Implementation and storage shape follow in D4–D14.

| ID | Decision | Outcome |
|----|----------|---------|
| **D0** | Problem statement | **Locked** — topology, scoring contract, and standings projection are separate concerns; engine executes stored rules; L5 is projection. |
| **D1** | Module outcome | **Locked** — always **derived**; **L5 cache only** for stored form. No ground `stage_rankings` table in v1. Revisit only if promotion graph performance or product needs require it. |
| **D2** | Scoring contract vs projection | **Locked — strict (A)** — scoring contract = **ground/config** (L4b); `amiga_tournament_standings` = **derived projection** (L5), recomputable on simul/finalize; no scoring policy only in scattered code constants. |
| **D6** | Platform default + freeze | **Locked** — (1) named **`platform_default_v1`** artefact (today’s 3-1-0 + GD/GF/games league tie-break + KO agg GD → GF → `extra` parse); engine always loads a contract object. (2) **Resolver fallback:** missing explicit per-tournament/stage config → `platform_default_v1`. (3) **Freeze effective contract at finalize** (explicit config or resolved default + schema version) so historical recompute does not drift when platform default evolves. |

**Session A still open:** D3 (defer), **D4**, **D5**.

**Design constraint (partial lock — see §2.1):** Promotion is separate from module outcomes. **Locked:** P1–P4 (incl. L4 ops ground only). **Open:** D18 storage shape.

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

---

## 3. Vocabulary (working definitions)

Use consistently in this track:

| Term | Meaning |
|------|---------|
| **Module** | One `tournament_stages` atom: `round_robin` (player-set RR) or `knockout` (one 2-player tie). |
| **Fixture result** | L3 ground: regulation goals (+ `extra` witness) for one match. |
| **Module outcome** | Placement **within one module**: RR rank table; KO winner/loser. Not the same as fixture results. |
| **Scoring contract** | Ground/config: rules for turning fixture results into module outcomes (points, tie-break chain, KO resolution chain). |
| **Standings projection** | Derived L5 rows (`amiga_tournament_standings`) — cache of module outcomes keyed for reads/honours. |
| **Scope routing** | Which games feed which module/table (fixture → stage path vs legacy `phase` parser). |

Policy T14 (“module outcomes on stage”) describes **module outcomes** as what a future promotion graph reads. Today they are **not** stored as first-class `stage_id → ranks`; they appear only via L5 scope rows or recompute.

---

## 4. Layer model (strawman — react in discussion)

Not committed. Documents the split the chat is exploring:

```text
L3   Match results           amiga_games (+ extra); running cols on fixtures until official
L4a  Topology                tournament_stages, tournament_fixtures, entrants, promotion (future)
L4b  Scoring contract        per-template defaults + per-tournament/stage overrides (TBD storage)
L5   Standings projection    amiga_tournament_standings (+ honours inputs)

GIT  Curated topology        StructureSpec, disposition_register (routing only)
```

**D3 (deferred):** Keep one **L4** in pipeline docs but document **L4a / L4b** as conceptual split — or unify physically in same tables with clear column ownership.

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
| **D4** | Where each truth lives | open |
| **D5** | Precedence chain | open |
| **D6** | Platform default + freeze on finalize | **locked** — see §2 |

### Tier 2b — Promotion (structure graph track)

| ID | Decision | Status |
|----|----------|--------|
| **D18** | Promotion override storage | open — P1–P4 locked (§2.1); exact tables/ops flow TBD at promotion-graph slice |

### Tier 3 — Identity and engine

| ID | Decision | Question |
|----|----------|----------|
| **D7** | Canonical module key | `stage_id` on L5 rows vs keep `scope_type` + `scope_key` (+ migration path). |
| **D8** | Engine responsibilities | Load contract → group games → apply rules → write L5; explicit out-of-scope list. |
| **D9** | Scoring primitive set | Closed vocabulary (`league_table`, `knockout_tie`, …); retire orphan `standings_resolver` strings unless wired. |

### Tier 4 — Legacy and coverage

| ID | Decision | Question |
|----|----------|----------|
| **D10** | Phase parser fallback | When `fixture_id` NULL only; retirement criteria vs L4 coverage %. |
| **D11** | Disposition register | Handler = materializer only, never scoring rules? |
| **D12** | `extra` / penalties | Match witness (L3) vs rule “consult extra for pens” (part of knockout contract). |

### Tier 5 — Data format

| ID | Decision | Question |
|----|----------|----------|
| **D13** | Serialization shape | JSON blocks vs normalized tables vs hybrid (incl. audit-filter columns). |
| **D14** | Schema versioning | `scoring_schema_version`, closed tie-break step enum, verify CLI. |

### Tier 6 — Runtime and product

| ID | Decision | Question |
|----|----------|----------|
| **D15** | Running vs finalized | Broadcast compute uses same contract as finalize (RTB alignment). |
| **D16** | Export packs | Pack B must recompute standings without git checkout? |
| **D17** | PHP/Python contract reader | Single shape; two implementations; no policy drift. |

---

## 7. Session plan

Take **one tier per discussion block** where possible. Record outcomes inline under each decision ID.

### Session A — Concept + authority (D0–D6)

**Outcome target:** Agreed ground vs derived diagram; precedence chain; freeze-on-finalize yes/no.

**Progress (2026-07-09):** D0, D1, D2, D6 **locked**. Promotion override constraint recorded (§2.1). **Next:** D4, then D5.

**Suggested first three within session:** D2 → D4 → D6 (philosophy, then placement, then audit/immutability).

### Session B — Keys + engine (D7–D9)

**Outcome target:** `stage_id` as canonical module key (likely); engine as executor; primitive enum.

### Session C — Legacy + format (D10–D14)

**Outcome target:** Phase fallback sunset; serialization sketch; schema version; audit-filter strategy (addresses JSON vs queryable columns).

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

## 9. Strawman authority map (for Session A reaction)

Partially informed by D6 lock. D4 will refine.

| Kind | Primary authority | Copied to DB when |
|------|-------------------|-------------------|
| Topology (curated) | Git `StructureSpec` | Materialize → stages/fixtures |
| Topology (bulk RR/KO) | Disposition handler + materializer | apply-structure |
| Scoring defaults | `tournament_format_templates.spec_json` | Template seed |
| Scoring instance | `tournaments.format_overrides` (+ optional stage override) | Create / materialize; **effective contract frozen at finalize** |
| Platform default | Named `platform_default_v1` in repo | Resolver when explicit config absent; frozen copy at finalize |
| Promotion override | L4 ops ground (**P4**; shape **D18**) | Organizer action at module boundary |
| Routing | `disposition_register.json` | Never rules — handler only |
| Standings rows | L5 | Rebuild on finalize/simul from L3 + L4a + frozen/effective contract |

---

## 10. Current-state anchors (Jul 2026)

Facts for discussion — not targets:

- **L4 materialized** on `ko2amiga_work`: 515/605 catalog tournaments (full fixture linkage).
- **Scoring rules:** global 3-1-0 + GD + GF + games; KO aggregate GD → GF → `extra` parse — in Python + PHP only.
- **`standings_resolver` in template JSON:** aspirational; no dispatcher.
- **L5 key today:** `(tournament_id, scope_type, scope_key)` — not `stage_id`.
- **Structure specs in git:** Homburg active; most `structure_spec` disposition rows not yet materialized.

---

## 11. Changelog

| Date | Change |
|------|--------|
| 2026-07-09 | **P4 locked** — promotion overrides = L4 ops ground only; D18 remains for storage shape. |
| 2026-07-09 | §2.1 split: P1–P3 locked; D18 added for promotion override storage (deferred). |
| 2026-07-09 | **Session A partial** — D0, D1, D2 (strict), D6 (platform default + finalize freeze) locked; §2.1 promotion. |
| 2026-07-09 | Initial discussion plan from L4/L5 boundary design chat (problem identified; sessions A–D outlined). |
