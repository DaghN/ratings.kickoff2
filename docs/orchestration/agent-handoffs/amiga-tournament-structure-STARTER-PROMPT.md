# Starter prompt — Amiga tournament structure (modules vs legacy backfill)

**Status:** **RESUME** — slices 1–2 + 3b code on `main`; slice 3 **pilot void**; continue from **slice 4** after undo check.  
**Undo + resume:** [`2026-06-13-015-amiga-tournament-structure-undo-and-resume.md`](2026-06-13-015-amiga-tournament-structure-undo-and-resume.md) ← **read first**  
**Policy:** [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) (T1–T22)  
**Plan:** [`docs/amiga-tournament-structure-implementation-plan.md`](../../amiga-tournament-structure-implementation-plan.md)  
**Restart handoff:** [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](2026-06-13-013-amiga-tournament-structure-restart-handoff.md)

---

## RESUME prompt (copy into implementation chat — use this, not slice 1)

```
Read docs/orchestration/agent-handoffs/2026-06-13-015-amiga-tournament-structure-undo-and-resume.md FIRST.

You are continuing the Amiga **tournament structure** track. Slices **1–2** are done (migration 023, builders, Homburg). **Slice 3 pilot is VOID** (never passed GATE B). Code on main includes **3b** (policy v2 materialize) — do not redo unless regression.

**Read next (mandatory):**
1. docs/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md
2. docs/amiga-tournament-structure-policy.md — T1–T22 (especially §1 stage/fixture/game, T8–T9, T14, T21–T22)
3. docs/amiga-tournament-structure-implementation-plan.md — resume at **slice 4**

**Do NOT:** redo slices 1–2; bulk materialize; auto-materialize tier C (e.g. Athens IV id=74); dematerialize Homburg (137).

**DB:** Verify Athens IV (74) per handoff 015 §2C (expect clean). Fix only if check fails.

**First task after I confirm:** Run GATE B′ (handoff 015 §6), report results, then wait for **Do slice 4**.

**Operating mode:** one slice at a time; handoff files; no git commit unless I ask.

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
