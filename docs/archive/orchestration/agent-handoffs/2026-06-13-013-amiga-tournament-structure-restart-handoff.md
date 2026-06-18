# Amiga tournament structure — restart handoff (policy v2)

**Date:** 2026-06-13  
**Audience:** New or continued implementation agent  
**Trigger:** Dagh + planning chat revised module model and NULL-phase classification after slice 3 pilot review.

---

## What changed (policy v2)

1. **Two module primitives:** `round_robin` **scope** (player set) and `knockout` **tie** (exactly 2 players). Not “knockout round” as a type.
2. **Bracket rounds** (R16, QF, placement bands, 3rd-place final) live in **`StructureSpec` / structure graph** — not in `stage_type`.
3. **Legacy materialize — KO granularity:** one `knockout` **stage per tie** (player pair), not one stage per event.
4. **NULL-phase classification:**
   - **Auto:** full RR schedule (`game_count = n×(n−1)/2`) → one `round_robin` stage.
   - **Else:** `needs_structure_review` — **refuse materialize**; no “not full RR ⇒ knockout”.
5. **Athens IV Cup (id=74)** is **not** an auto-materialize pilot — tier C until curated `StructureSpec`.

**Authoritative:** [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) T1–T22.

### Stage / fixture / game (v2.1)

- **Fixture** = one match, one result (live + legacy). Tie = **stage**; legs = separate fixtures.
- **Live:** schedule fixtures → enter results → games.
- **Legacy:** games ground truth → materialize fixtures → assign stages.
- Do **not** skip fixtures on legacy or treat one fixture as a multi-leg tie.

---

## Work already done — KEEP

| Slice | Status | Notes |
|-------|--------|-------|
| **1** | ✅ Keep | Migration `023`, `round_robin`/`knockout` enum, `_fixture_scope` PHP/Python |
| **2** | ✅ Keep | Builders, Homburg spec types, structure verify |
| **3** | ⚠️ **Revised** | `materialize_legacy.py` shipped with **bad** NULL heuristic — **fixed in policy v2 commit** |

**Do not redo slices 1–2** unless regression found.

---

## Work to ROLL BACK

### Code (superseded behaviour — fixed in repo)

- ~~`null_phase_stage_bucket()` returning `knockout` when not full RR~~ → replaced by `classify_null_phase_tournament()` → `needs_structure_review`
- ~~Slice 3 tests expecting NULL 6-player cup → knockout~~ → expect `needs_structure_review`
- ~~Implementation plan: Athens IV as slice 3 pilot / STOP GATE B~~ → superseded

### Local DB (if slice 3 pilot was applied)

**Athens IV Cup (`tournament_id=74`)** may have materialized structure. Revert:

```powershell
python -m scripts.amiga tournament-structure dematerialize --tournament-id 74
python -m scripts.amiga standings-rebuild --tournament-id 74
```

Or SQL equivalent: delete stages for tournament 74 (fixtures cascade; `amiga_games.fixture_id` nulls via FK).

**Migration `023`:** do **not** roll back — still correct.

**Homburg / generated tournaments:** untouched by legacy materialize.

---

## Resume from here (revised slice map)

| Next | Deliverable |
|------|-------------|
| **3b** | Policy v2 materialize (this handoff + code fix) — unit tests, dematerialize CLI, refuse tier C |
| **4** | `verify-legacy` CLI + `audit` tier A/C inventory from `ko2amiga_db` |
| **5** | Bulk **tier A only** (NULL + full RR marathons) — STOP GATE C |
| **6** | Tier B labeled events; per-tie KO stages |
| **6b** | Manual `StructureSpec` queue (Athens IV, NULL cups) — Dagh triage list |
| **7+** | Catalog flags, Steve WC reference, docs closure |

**Do not run slice 5 bulk** until 3b verified.

---

## Verification after 3b

```powershell
python -m unittest scripts.amiga.test_tournament_structure -q

# Tier A smoke (pick a known full NULL-phase marathon id)
python -m scripts.amiga tournament-structure materialize --tournament-id <marathon_id> --dry-run

# Tier C must refuse
python -m scripts.amiga tournament-structure materialize --tournament-id 74
# Expect: FAIL / needs_structure_review

# Dematerialize if needed
python -m scripts.amiga tournament-structure dematerialize --tournament-id 74
```

---

## Starter prompt

Use [`amiga-tournament-structure-STARTER-PROMPT.md`](amiga-tournament-structure-STARTER-PROMPT.md) **RESUME block** (not slice 1 from scratch).

---

## STOP gates (revised)

- **Gate B (old):** Athens IV pilot — **cancelled**
- **Gate C:** After tier-A bulk only — user spot-checks marathons

---

*Dagh approved policy v2 in planning chat Jun 2026.*
