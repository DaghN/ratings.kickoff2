# Starter prompt — Amiga organizer finish confirm (Tier E UI)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Mission:** Implement Phase A of organizer **finish confirm** (policy FO1–FO10) — secretary confirms finishing order before Make official; persist Tier E; medals/Winner work for kitchens including WC-stamped RR.

**Docs first already done.** Do **not** re-debate FO1–FO10 unless Dagh opens a decision. **Slices 0–1 done** (plan §3a + write helper). Default = **Do slice 2** (Table UI confirm) unless he names another.

**Out of scope unless Dagh expands:** Track C, cup templates (L3), post-official repair of completed #609, more WC finish heuristics, silent Finish rewind.

**Related:** [`docs/amiga-organizer-finish-confirm-policy.md`](../../amiga-organizer-finish-confirm-policy.md) · [`docs/amiga-organizer-finish-confirm-implementation-plan.md`](../../amiga-organizer-finish-confirm-implementation-plan.md) · [`docs/amiga-tournament-honours-rules.md`](../../amiga-tournament-honours-rules.md) · [`docs/amiga-running-tournament-boundary-policy.md`](../../amiga-running-tournament-boundary-policy.md) · [`docs/amiga-live-ops-practice-track.md`](../../amiga-live-ops-practice-track.md)

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga organizer finish confirm** (Phase A).

**Read first (in order):**
1. docs/amiga-organizer-finish-confirm-policy.md (FO1–FO10)
2. docs/amiga-organizer-finish-confirm-implementation-plan.md (slices)
3. docs/amiga-live-ops-practice-track.md §4 (queue depth 1 — do not mix unrelated secretary feedback)
4. RTB Finish section in docs/amiga-running-tournament-boundary-policy.md §6 as needed
5. Honours Tier E in docs/amiga-tournament-honours-rules.md as needed

**Mission:** Slice-by-slice per the implementation plan. Default = **Do slice 2** (Table UI: prefill A–D, edit/reorder, Confirm → Tier E) unless Dagh says otherwise. Slices 0–1 are done — do not re-inventory or rewrite the write helper unless fixing a bug.

**Rules:**
- One slice per session unless Dagh says continue.
- No Track C / no cup template track / no post-official #609 rewind UI.
- Do not invent new WC hardcoded finish rules (FO4, FO9).
- Tier E full ladder for secretary path; ground table already exists.
- UTF-8: StrReplace existing files; new files via PowerShell UTF8Encoding false (no Write tool for new PHP/MD dumps).
- After shipping a slice: UPDATE_DOCS Part A (MEMORY + plan changelog + practice track if L1 cycle advances).

**Reply first** with a short understanding summary (FO intent + which slice you will do), then wait for "Do slice N" / "go" if he has not already named the slice.
```