# Starter prompt — Amiga tournament structure (modules vs legacy backfill)

**Status:** **RESUME** — slices 1–2 done; slice 3b done; **continue from slice 4**.  
**Policy:** [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) (T1–T22)  
**Plan:** [`docs/amiga-tournament-structure-implementation-plan.md`](../../amiga-tournament-structure-implementation-plan.md)  
**Restart handoff:** [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](2026-06-13-013-amiga-tournament-structure-restart-handoff.md)

---

## RESUME prompt (copy into implementation chat — use this, not slice 1)

```
You are continuing the Amiga **tournament structure** track. Slices **1–2 and 3b** are done (migration 023, builders, Homburg, policy v2 materialize).

**Read first (mandatory):**
1. docs/amiga-tournament-structure-policy.md — T1–T22 (especially §1 stage/fixture/game, T8–T9, T14, T21–T22)
2. docs/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md
3. docs/amiga-tournament-structure-implementation-plan.md — resume at **slice 4**

**Do NOT redo slices 1–2 or 3b** unless you find a regression.

**Locked architecture (do not re-litigate):**
- Stage = module atom (`tournament_stages.id`); types: `round_robin` scope | `knockout` tie
- Fixture = exactly one match, one result — NEVER a multi-leg bundle in one row
- Game = score row; `game.fixture_id → fixture.stage_id → stage`
- Two-leg KO: one KO stage + two fixtures + two games
- Live: fixtures first, games fill results. Legacy: games first, materialize fixtures + assign stages. SAME schema (T9)
- NULL phase: auto-materialize tier A (full RR) only; else needs_structure_review
- Bracket rounds / placement bands = StructureSpec only, not a third stage type

**Your next slice: 4** — verify-legacy CLI + tier A/C inventory.

**Operating mode:** one slice at a time; handoff files; no git commit unless I ask; UPDATE_DOCS Part A when slice completes.

Confirm your understanding before taking action.
```

---

## Original prompt (cold start — only if slices 1–2 were never done)

<details>
<summary>Deprecated for this track — slices 1–2 already shipped</summary>

See git history or [`2026-06-13-010-amiga-tournament-structure-slice-1.md`](2026-06-13-010-amiga-tournament-structure-slice-1.md). Use **RESUME prompt** above instead.

</details>

---

## After slices 4–7

Mark plan status **Complete** in slice 9. Slice 8 (Steve WC reference) may trail.
