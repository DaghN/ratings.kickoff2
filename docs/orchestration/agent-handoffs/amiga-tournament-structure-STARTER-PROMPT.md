# Starter prompt — Amiga tournament structure (modules vs legacy backfill)

**Status:** **RESUME** — slices 1–2, 3b, **4** on `main`; tier-A **k× RR** adjustment shipped (handoff **016**); continue **slice 5**.  
**Read first:** [`2026-06-13-016-amiga-tournament-structure-tier-a-multi-rr.md`](2026-06-13-016-amiga-tournament-structure-tier-a-multi-rr.md)  
**Undo + resume:** [`2026-06-13-015-amiga-tournament-structure-undo-and-resume.md`](2026-06-13-015-amiga-tournament-structure-undo-and-resume.md)  
**Policy:** [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) (T1–T22)  
**Plan:** [`docs/amiga-tournament-structure-implementation-plan.md`](../../amiga-tournament-structure-implementation-plan.md)

---

## RESUME prompt (copy into implementation chat)

```
Read docs/orchestration/agent-handoffs/2026-06-13-016-amiga-tournament-structure-tier-a-multi-rr.md FIRST.

You are continuing the Amiga **tournament structure** track. Slices **1–2**, **3b**, and **4** are done. **Slice 3 pilot is VOID.**

**Planning intervention (already in repo — do not redo):**
Tier A now accepts NULL-phase **multi-leg** round robins: `game_count = k×n×(n−1)/2` AND every player has `(n−1)×k` games (`round_robin_legs()` in materialize_legacy.py). Was 1× only → inventory tier A **503** (was 108), tier C **16** (was 411).
Duesseldorf V (id=416) is flagged `STRUCTURE_REVIEW_TOURNAMENT_IDS` — tier C despite 3× game total (uneven per-player). Do not bulk-materialize it.

**Read next:**
1. docs/amiga-tournament-structure-policy.md — T11, §4 tiers
2. docs/amiga-tournament-structure-implementation-plan.md — **slice 5**

**Do NOT:** redo slices 1–4; dematerialize Homburg (137); auto-materialize tier C (Athens IV id=74, Duesseldorf V id=416); bulk materialize without user OK at GATE C.

**Smoke before slice 5:**
python -m unittest scripts.amiga.test_tournament_structure -q
python -m scripts.amiga tournament-structure audit-inventory
Expect tier counts A:503 B:83 C:16 D:1.

**First task:** Report smoke results, then wait for **Do slice 5** (tier-A bulk materialize, dry-run first).

**Operating mode:** one slice at a time; handoff files; git commit only when Dagh asks.

Confirm your understanding before taking action.
```

---

## Original prompt (cold start — only if slices 1–2 were never done)

<details>
<summary>Deprecated for this track — slices 1–2 already shipped</summary>

See git history or [`2026-06-13-010-amiga-tournament-structure-slice-1.md`](2026-06-13-010-amiga-tournament-structure-slice-1.md). Use **RESUME prompt** above instead.

</details>

---

## After slices 5–7

Mark plan status **Complete** in slice 9. Slice 8 (Steve WC reference) may trail.
