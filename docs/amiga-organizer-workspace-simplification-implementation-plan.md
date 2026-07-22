# Amiga organizer workspace simplification — implementation plan (Jul 2026)

**Status:** **Planned** — policy locked; start at slice 0 when Dagh opens the track.

**Policy:** [`amiga-organizer-workspace-simplification-policy.md`](amiga-organizer-workspace-simplification-policy.md) (OW1–OW14).

**Parents:** same as policy · practice track · fixtures.php inventory in slice 0.

**Starter:** [`orchestration/agent-handoffs/amiga-organizer-workspace-simplification-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-organizer-workspace-simplification-STARTER-PROMPT.md) (defaults to **slice 0**).

---

## 1. Goal

Ship organizer UX and gates that match OW1–OW14 without breaking RTB Finish or Live broadcast.

**Exit (behavioural):** Ref-League-A night: create → stage-scoped scores → finish confirm → Finish; Hide optional; Recent tournaments = Open only; no Start/void/withdraw/replace on happy path.

---

## 2. Non-goals (this track)

| Out | Why |
|-----|-----|
| Full cup / on-the-fly stage builder | Later slice or separate track |
| RR mid-event add-player regen | Explicit later; freeze OK for kitchens |
| Case B/C tip delete | L5 track |
| Void-row migration epic | Irrelevant; opportunistic map if needed |
| Demotion / L6 | Shelved elsewhere |

---

## 3. Suggested slices

| Slice | Deliverable | Verify | STOP |
|-------|-------------|--------|------|
| **0** | Inventory: every gate on `running` / `void` / entrant withdraw-replace; Live hub query; Recent leagues SQL; tab map. Notes → §5. | Read-only | — |
| **1** | **Recent tournaments** rename + Open-only filter (incl. Hidden when Hide exists; until then exclude finalized + void if still present). | Official tip gone from list; Open kitchen remains | — |
| **2** | **Hide / Show on Live** (flag or remap); default on at create; Finish allowed while hidden; retire void/Abandon happy path. | Hidden off Live; still in Recent; Finish OK | — |
| **3** | **No Start** — Open scoreable at create; remove/lock Start from happy path; adjust result-entry gates. | Create → score without Start | STOP if Live/broadcast assumptions break |
| **4** | **Merge play surface** — one stage-scoped Matches/Play UI replacing Fixtures+Results. | Single-stage kitchen night works | — |
| **5** | **Chrome cleanup** — demote Advanced; remove withdraw/replace UI; Setup = create/meta only (Hide not “owned by Setup”). | Happy path runnable without Advanced | — |
| **6** | Docs / RTB vocabulary pass + practice-track note; starter if needed | — | — |

**One slice per chat** unless Dagh says continue. Prefer serial feedback on Ref-League-A after chrome changes.

---

## 4. Technical risks

| Risk | Mitigation |
|------|------------|
| Live hub and result entry both key off `lifecycle_status = running` | Slice 0 map; slice 2–3 introduce Open + live_visible without breaking Finish |
| Finish confirm FO5 assumes Table tab | Revise FO5 placement when slice 4–5 ships |
| Half-migrated void/Start in Advanced | Demote, don’t leave two happy paths |
| Stage navigator with one stage feels heavy | Default-select sole stage; no extra clicks |

---

## 5. Slice 0 inventory (fill in slice 0)

_Placeholder — executing agent replaces this section._

### 5.1 Gates on `running` / pre-start
- …

### 5.2 Void / Abandon / Live eligibility
- …

### 5.3 Recent leagues query
- …

### 5.4 Withdraw / replace call sites
- …

### 5.5 Tab / chrome map
- …

---

## 6. Verification checklist (track done)

- [ ] Create → score → Finish with no Start / no void
- [ ] One stage-scoped play surface
- [ ] Hide/Show Live; Finish while Hidden
- [ ] Recent tournaments = Open only (incl. Hidden)
- [ ] No withdraw/replace on happy path
- [ ] Advanced not required for Ref-League-A
- [ ] RTB Finish still promotes correctly
- [ ] Policy status → Implemented (or Partially if stage builder deferred)

---

## 7. Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | Starter prompt added (slice 0 default). |
| 2026-07-22 | Initial plan — slices 0–6 from locked OW policy. |