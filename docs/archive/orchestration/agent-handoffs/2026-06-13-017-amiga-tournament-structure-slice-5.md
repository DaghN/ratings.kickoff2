# Handoff 017 — Slice 5 tier-A bulk materialize

**Date:** 2026-06-13  
**Track:** Amiga tournament structure  
**Status:** **GATE C passed** — live apply complete (Jun 2026 local)

---

## Apply result (local `ko2amiga_db`)

```powershell
python -m scripts.amiga tournament-structure materialize-tier-a --apply --rebuild-standings --verify-sample 10
```

| Metric | Value |
|--------|------:|
| Bulk processed | **501** (2 tier-A already materialized at run start) |
| Failed | **0** |
| Standings rebuilt | **501** |
| Verify sample | **10/10 OK** |
| `materialized_count` (inventory) | **504** (503 tier-A + Homburg) |

GATE C anchors post-apply: id=**1** Jerez XI OK · id=**318** Milan XXIII OK · id=**74** Athens IV still tier C unmaterialized · id=**416** Duesseldorf V still tier C.

| Item | Detail |
|------|--------|
| CLI | `python -m scripts.amiga tournament-structure materialize-tier-a` |
| Modes | `--dry-run` (preview) or `--apply` (live — GATE C) |
| Options | `--rebuild-standings`, `--verify-sample N`, `--limit`, `--seed` |
| Module | `scripts/amiga/tournament_structure/bulk_tier_a.py` |
| Excludes | ids 74, 137, 416 (belt-and-suspenders) |

---

## Dry-run (local `ko2amiga_db`)

```powershell
python -m scripts.amiga tournament-structure materialize-tier-a --dry-run
```

**Result:** candidates=**503**, materialized=**503**, failed=**0** (~34s). DB unchanged (`materialized_count` still **1** = Homburg).

---

## GATE C — before apply

Spot-check in browser:

1. **Jerez XI** (id=**1**) — 2× RR marathon  
2. **Milan XXIII** (id=**318**) — 1× RR marathon  
3. **Athens IV Cup** (id=**74**) — still tier C, no structure  

Then:

```powershell
python -m scripts.amiga tournament-structure materialize-tier-a --apply --rebuild-standings --verify-sample 10
python -m scripts.amiga tournament-structure audit-inventory
```

Expect `materialized_count` ≈ **504** (503 tier-A + Homburg).

---

## Do not

- Dematerialize Homburg (137)  
- Auto-materialize tier C (74, 416, …)  
- Run `--apply` without GATE C sign-off

---

*Slice 6 next: phase-labeled events (tier B).*
