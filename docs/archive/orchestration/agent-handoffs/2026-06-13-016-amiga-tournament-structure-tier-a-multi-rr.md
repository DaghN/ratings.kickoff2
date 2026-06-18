# Handoff 016 — Tier A multi-leg RR classifier adjustment

**Date:** 2026-06-13  
**Track:** Amiga tournament structure  
**Status:** Planning intervention — **implemented in repo**; implementation agent resumes at **slice 5**

---

## What changed (planning chat)

Slice 4 `audit-inventory` reported tier C **411** — looked too high. Analysis showed **396 of 411** were NULL-phase events with game totals at **integer multiples** of single-round RR (`2×`, `3×`, `4×` …) — i.e. home-and-away / multi-leg marathons misclassified because policy T11 only accepted **1×** `n×(n−1)/2`.

### New tier-A rule (policy T11 v2.1)

Tier **A** when **all** of:

1. Every game has NULL `phase`
2. `game_count = k × n×(n−1)/2` for integer **k ≥ 1**
3. Every player has exactly **`(n−1) × k`** games

Still one `round_robin` / `overall` stage on materialize (k legs = more fixtures, same module).

Tier **C** when NULL phase but any check fails — cups, withdrawals, odd totals, **or** per-player inequality.

### Audit flag

**Duesseldorf V** (id=**416**): 4 players, 18 games (3× total) but per-player counts **8/9/9/10** — passes coarse 3× math, fails equality. Added to `STRUCTURE_REVIEW_TOURNAMENT_IDS` in `materialize_legacy.py` → always tier C until triaged.

### Inventory after change (local `ko2amiga_db`)

| Tier | Count | Notes |
|------|------:|-------|
| **A** | **503** | was 108 (1× only) |
| **B** | 83 | unchanged |
| **C** | **16** | cups, withdrawals, irregular + 416 |
| **D** | 1 | Homburg |

---

## Code touched

| File | Change |
|------|--------|
| `scripts/amiga/tournament_structure/materialize_legacy.py` | `round_robin_legs()`, `STRUCTURE_REVIEW_TOURNAMENT_IDS`, updated `classify_null_phase_tournament()` |
| `scripts/amiga/tournament_structure/verify_legacy.py` | `classify_legacy_tier(..., tournament_id=)`; tier detail strings |
| `scripts/amiga/test_tournament_structure.py` | +4 tests (2× RR, uneven per-player, manual flag) |
| `docs/amiga-tournament-structure-policy.md` | T11 + §4 tiers table |
| `docs/amiga-tournament-structure-implementation-plan.md` | slice 5 scope + inventory |

---

## Verification (implementation agent — quick smoke)

```powershell
python -m unittest scripts.amiga.test_tournament_structure -q
python -m scripts.amiga tournament-structure audit-inventory
python -m scripts.amiga tournament-structure verify-legacy --tournament-id 416
python -m scripts.amiga tournament-structure materialize --tournament-id 416
python -m scripts.amiga tournament-structure materialize --tournament-id 1 --dry-run
```

Expect:

- 27 tests OK
- Inventory **503 / 83 / 16 / 1**
- id=416 tier C; materialize **FAIL** (audit flag)
- id=1 dry-run OK (2× RR marathon)

---

## Slice 5 implications

- Bulk materialize **~503** tier-A events (not 108)
- GATE C spot-check: include one **2×** marathon (e.g. Jerez XI id=1) + one old 1× tier-A + confirm Athens IV (74) still tier C
- Do **not** dematerialize Homburg (137)

---

## Tier C remainder (~15 besides 416)

Cups (74, 111), near-complete withdrawal (174), incomplete RR (281), and ~11 events with non-integer-multiple game counts — slice **6b** / manual `StructureSpec` queue.
