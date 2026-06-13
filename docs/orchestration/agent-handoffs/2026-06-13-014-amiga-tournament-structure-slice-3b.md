# Amiga tournament structure — slice 3b handoff (policy v2)

**Date:** 2026-06-13  
**Slice:** 3b — Materialize policy revision  
**STOP GATE B′:** Ready for user OK before slice 4

---

## Delivered

- [x] Policy v2: [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) T1–T18
- [x] Restart handoff: [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](2026-06-13-013-amiga-tournament-structure-restart-handoff.md)
- [x] `classify_null_phase_tournament()` — `auto_rr` | `needs_structure_review`
- [x] Refuse materialize on tier C (`StructureReviewRequired`)
- [x] Labeled KO → one `knockout` stage per player pair (tie)
- [x] `dematerialize` CLI
- [x] Unit tests updated (18 OK)
- [x] Athens IV (74) dematerialized locally (pilot rollback)
- [x] Policy v2.1: stage/fixture/game chain (T8–T22) in policy + data contract

## Verification (local)

```text
python -m unittest scripts.amiga.test_tournament_structure -q  → 18 OK
materialize --tournament-id 74  → FAIL needs_structure_review ✓
dematerialize --tournament-id 74  → removed 1 stage, unlinked 6 games ✓
```

## Next slice

**Slice 4** — `verify-legacy` CLI + tier A/C inventory from `ko2amiga_db`.

## User checks (GATE B′)

1. Confirm Athens IV refuses auto-materialize (message above).
2. Optional: tier-A `--dry-run` on a known full NULL-phase marathon id.
3. Reply **OK for slice 4** when satisfied.

---

*Supersedes slice 3 handoff [`012`](2026-06-13-012-amiga-tournament-structure-slice-3.md).*
