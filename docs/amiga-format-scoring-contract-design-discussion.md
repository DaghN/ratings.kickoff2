# Amiga format scoring contract — design discussion plan (Jul 2026)

**Status:** **In discussion** — no decisions locked; no implementation started.  
**Purpose:** Working reference for a dedicated design chat that resolves intent about **L4 structure vs L5 standings**, **scoring contracts**, and **where format ground truth lives** — before policy updates and code.

**Authority when implemented:** Will supersede or amend scattered rules in [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md), [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md), [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings, and [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §9 — only after decisions here are locked.

**Related:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (living ground; L4 persists; L5 cleared on simul) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (archive L0–L5 vocabulary)

---

## 1. Problem statement (agreed direction, not yet locked)

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

**Decision D0:** Confirm or refine this problem statement in Session A.

---

## 2. Vocabulary (working definitions)

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

## 3. Layer model (strawman — react in discussion)

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

## 4. JSON vs normalized storage (audit notes — no decision)

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

## 5. Decision register

Work through in order. Mark **Status:** `open` | `draft` | `locked` in chat; update this table when locked.

### Tier 0 — Intent

| ID | Decision | Status |
|----|----------|--------|
| **D0** | Problem statement (§1) — confirm wording | open |

### Tier 1 — Conceptual model

| ID | Decision | Question |
|----|----------|----------|
| **D1** | Module vs module outcome | Is module outcome always derived, ever stored as ground, or L5 cache only? |
| **D2** | Scoring contract vs standings projection | Lock: contract = input; rows = output. No policy only in code constants. |
| **D3** | L4 unified vs L4a/L4b documented split | Same tables + doc split vs explicit separation; defer if blocking. |

### Tier 2 — Authority and placement

| ID | Decision | Question |
|----|----------|----------|
| **D4** | Where each truth lives | Template / tournament / stage / git — primary authority per kind (topology, scoring, routing). |
| **D5** | Precedence chain | e.g. stage → tournament overrides → template → platform default. |
| **D6** | Freeze on finalize | Snapshot scoring contract on tournament at finalize vs always resolve live from template. |

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

## 6. Session plan

Take **one tier per discussion block** where possible. Record outcomes inline under each decision ID.

### Session A — Concept + authority (D0–D6)

**Outcome target:** Agreed ground vs derived diagram; precedence chain; freeze-on-finalize yes/no.

**Suggested first three within session:** D2 → D4 → D6 (philosophy, then placement, then audit/immutability).

### Session B — Keys + engine (D7–D9)

**Outcome target:** `stage_id` as canonical module key (likely); engine as executor; primitive enum.

### Session C — Legacy + format (D10–D14)

**Outcome target:** Phase fallback sunset; serialization sketch; schema version; audit-filter strategy (addresses JSON vs queryable columns).

### Session D — Runtime (D15–D17)

**Outcome target:** RTB parity, export self-containment, shared contract loader spec.

**After all sessions:** Promote locked decisions into policy docs + implementation plan slice(s).

---

## 7. Recommended tackle order (within sessions)

If not following full tier order:

1. **D2** — contract vs projection (philosophy).
2. **D4** — where truth lives (structural).
3. **D7** — `stage_id` as module key (unblocks honours, UI, promotion).
4. **D5, D6** — precedence and freeze.
5. **D13, D14** — format (informed by audit-filter need).
6. Remaining IDs as needed.

---

## 8. Strawman authority map (for Session A reaction)

Not locked:

| Kind | Primary authority | Copied to DB when |
|------|-------------------|-------------------|
| Topology (curated) | Git `StructureSpec` | Materialize → stages/fixtures |
| Topology (bulk RR/KO) | Disposition handler + materializer | apply-structure |
| Scoring defaults | `tournament_format_templates.spec_json` | Template seed |
| Scoring instance | `tournaments.format_overrides` (+ optional stage `config_json`) | Create / materialize / **finalize?** |
| Routing | `disposition_register.json` | Never rules — handler only |
| Standings rows | L5 | Rebuild on finalize/simul |

---

## 9. Current-state anchors (Jul 2026)

Facts for discussion — not targets:

- **L4 materialized** on `ko2amiga_work`: 515/605 catalog tournaments (full fixture linkage).
- **Scoring rules:** global 3-1-0 + GD + GF + games; KO aggregate GD → GF → `extra` parse — in Python + PHP only.
- **`standings_resolver` in template JSON:** aspirational; no dispatcher.
- **L5 key today:** `(tournament_id, scope_type, scope_key)` — not `stage_id`.
- **Structure specs in git:** Homburg active; most `structure_spec` disposition rows not yet materialized.

---

## 10. Changelog

| Date | Change |
|------|--------|
| 2026-07-09 | Initial discussion plan from L4/L5 boundary design chat (problem identified; sessions A–D outlined). |
