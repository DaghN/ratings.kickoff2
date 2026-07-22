# Starter prompt — Amiga organizer workspace simplification

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Current slice:** **0** — read-only inventory (gates, Live, Recent leagues, tabs, withdraw/replace). Policy locked; no product code in slice 0.

**Mission:** Implement organizer UX simplification per OW1–OW14 — Open/Official + Live visible (Hide); no Start/void on happy path; merge Fixtures+Results into stage-scoped play surface; strip lifecycle chrome; abandon withdraw/replace. **Keep RTB** (running package vs Official / Finish boundary).

**Out of scope unless Dagh expands:** Full cup / on-the-fly stage builder; RR mid-event add-player regen; L5 Case B/C tip delete; void-row migration epic; demotion/L6; Finish-confirm Phase B.

**Related:**
- [`docs/amiga-organizer-workspace-simplification-policy.md`](../../amiga-organizer-workspace-simplification-policy.md) (OW1–OW14)
- [`docs/amiga-organizer-workspace-simplification-implementation-plan.md`](../../amiga-organizer-workspace-simplification-implementation-plan.md)
- [`docs/amiga-running-tournament-boundary-policy.md`](../../amiga-running-tournament-boundary-policy.md) (keep Open vs Official)
- [`docs/amiga-organizer-finish-confirm-policy.md`](../../amiga-organizer-finish-confirm-policy.md) (FO5 may move with chrome)
- [`docs/amiga-live-ops-practice-track.md`](../../amiga-live-ops-practice-track.md)
- Primary code: `site/public_html/amiga/ops/fixtures.php` (+ Live hub pages)

---

## Already shipped (do not re-do)

| Item | Notes |
|------|--------|
| **Policy + plan** | OW locked; slices 0–6 outlined |
| **RTB Finish** | Promote + finalize; do not break |
| **Finish confirm Phase A** | Keep; placement may move later |
| **L5 Case A** | Admin delete never-official — separate track |

**Organizer URL (staging):** `https://ratings.kickoff2.com/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot`

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga organizer workspace simplification** (OW1–OW14).

**Current work: Do slice 0** — read-only inventory only. Fill plan §5. Do not change product UI/gates yet.

**Read first (in order):**
1. docs/amiga-organizer-workspace-simplification-policy.md (OW1–OW14 — locked; do not reopen)
2. docs/amiga-organizer-workspace-simplification-implementation-plan.md — slice 0 row + §5 placeholders
3. docs/amiga-running-tournament-boundary-policy.md — keep running-package vs Official; note organizer UX superseded by OW policy
4. docs/amiga-live-ops-practice-track.md §4–§5 (context only; this track named separately from L5)
5. Code to inventory (read before inventing):
   - site/public_html/amiga/ops/fixtures.php (tabs, Start, void/Abandon, Recent leagues SQL, withdraw/replace, result-entry gates)
   - Live hub: live-tournaments.php / live-tournament.php eligibility (lifecycle = running)
   - Finish confirm / Table Finish wiring (FO5)

**Slice 0 deliverable:** Fill plan §5 with:
- 5.1 Gates on running / pre-start (result entry, entrants, stage placement, Finish)
- 5.2 Void / Abandon / Live eligibility
- 5.3 Recent leagues query (what to filter for Open-only)
- 5.4 Withdraw / replace call sites (to remove later)
- 5.5 Tab / chrome map (Setup · Players · Fixtures · Results · Table · Advanced)

**STOP / hard rules:**
- Read-only — no PHP/UI behavior changes in slice 0.
- Do not implement Hide, merge play surface, or remove Start yet (slices 1+).
- Do not reopen OW decisions or invent void migration epics.
- Do not mix L5 Case B work into this chat.
- UTF-8 on Windows: StrReplace existing; new files via PowerShell UTF8Encoding false.
- After ship: UPDATE_DOCS Part A (MEMORY + plan status → slice 1 + §5 filled + practice track note if useful).

**Reply first** with a short understanding summary (slice 0 inventory only + what OW locks), then wait for Dagh's **go** unless he already said go / Do slice 0.
```