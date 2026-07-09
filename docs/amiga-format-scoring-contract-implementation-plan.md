# Amiga format scoring contract — implementation plan (agent slices)

**Status:** **Not started** — policy locked Jul 2026; code still on hardcoded 3-1-0 bridge.

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
| **SC-4** | PHP contract reader + executor parity with SC-3 | `amiga_post_game_standings.php` |
| **SC-5** | PHP↔Python parity oracle CLI on shared fixtures + contracts | D17 |
| **SC-6** | Catalog backfill: explicit contract rows on all tournaments/stages | Bridge retirement path |
| **SC-7** | D6 finalize freeze writes frozen snapshot columns | `finalize_tournament.py` + PHP |
| **SC-8** | RTB broadcast uses contract reader (fixtures adapter); live hub KO | D15; `amiga_running_tournament_lib.php` |
| **SC-9** | L5 `stage_id` column + dual-write; readers join stage | D7 / D9-pre |
| **SC-10** | Phase parser executor branch removal | After 100% `fixture_id` + audit (SC10 policy) |
| **SC-11** | Structured L3 match extensions (ET/pens cols) + KO steps | Separate from SC-0–9; policy SC11 |

**Suggested order:** SC-0 → SC-1 → SC-2 → SC-3 → SC-5 → SC-4 → SC-6 → SC-7 → SC-8 → SC-9 → SC-10. **SC-11** parallel when match-extension slice is scheduled.

---

## SC-0 — L4b DDL (sketch)

### Goal

Persistent relational home for scoring contracts per policy SC3–SC6.

### Tasks (agent fills detail at slice time)

- [ ] Migration under `scripts/amiga/sql/structure/` (forward ground path per MG11).
- [ ] Stage grain: `primitive`, points cols, `scoring_schema_version`.
- [ ] Child table: `(stage_id, sequence_no, step)` with v1 enum CHECK or app validation.
- [ ] Tournament grain: defaults + frozen snapshot columns post-finalize.
- [ ] Export pack includes new tables/columns (SC16).

### Verification

- [ ] `apply_schema` on `ko2amiga_work` clean.
- [ ] `verify-export-pack` includes contract tables.

---

## SC-3 / SC-4 — Executor refactor (sketch)

### Goal

`compute_tournament_standings` / `amiga_ops_compute_tournament_standings` dispatch on DB contract, not constants.

### Verification

- [ ] `standings-parity` Access sweep still 0 FAIL (engine may change only where contract matches old hardcode).
- [ ] SC-5 PHP↔Python oracle green on sample + live-ops tournaments.

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
