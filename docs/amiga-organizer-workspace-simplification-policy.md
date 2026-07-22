# Amiga organizer workspace simplification — policy (Jul 2026)

**Status:** **Implemented** (Jul 2026) — slices 0–6; happy-path chrome shipped in `fixtures.php`.

**Deferred (explicit, not blockers):** OW12 on-the-fly stage builder UX; mid-event add-player into a full RR (fixture regen). Storage may still use `lifecycle_status = running` for Open (OW14).

**Plan:** [`amiga-organizer-workspace-simplification-implementation-plan.md`](amiga-organizer-workspace-simplification-implementation-plan.md) (**done**).

**Parents:** [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (Open/running package vs Official — **kept**) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) · [`amiga-organizer-finish-confirm-policy.md`](amiga-organizer-finish-confirm-policy.md) · [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) (admin Case A delete)

**Audience:** Dagh, Cursor agents.

**Motivation:** Organizer fixtures grew as a browser skin over lifecycle/ops machinery (Start, void, six tabs, Advanced theatre). Secretaries need **minimal → advanced**: create → play (stage-scoped) → finish. This doc locks that product model. UI pixel detail lived in the plan (closed).

---

## 1. Goal

1. Create an **Open** tournament workspace.
2. Enter results on a **stage-scoped play surface** (and later grow stages).
3. **Finish and make official**.

Optional: **Hide** from spectator Live. Rare: **admin hard delete** of never-official junk (Case A).

Drop organizer-facing **Start**, **void/Abandon-as-lifecycle**, and chrome that exists mainly to expose the old state machine.

---

## 2. Two universes (RTB core — keep)

| Universe | Meaning |
|----------|---------|
| **Open workspace** | Not finalized. Scores live in the **running package** (fixtures), not as official `amiga_games` / L5 until Finish. |
| **Official** | After **Finish and make official**: promote + derive + catalog/tip semantics. |

**Finish** remains the **only** crossing into Official. Soft lifecycle labels are not required to carry that boundary.

---

## 3. Locked decisions (OW1–OW14)

| Id | Decision |
|----|----------|
| **OW1** | **Organizer states** = **Open** / **Official** / **Deleted**. Not draft · registration · ready · running · void · completed as the secretary mental model. |
| **OW2** | **Live visible** = independent flag/action on an Open tournament (default **on** at create). Organizer may **Hide** / **Show on Live**. Not nested under Setup as a “Setup concept.” |
| **OW3** | **After create:** workspace is Open and **immediately scoreable**; Live visible defaults **on**. **No Start** on the happy path. |
| **OW4** | **Hide** replaces **void** for spectator effect. Hide is reversible; does not finalize; does not delete; **does not block Finish**. |
| **OW5** | **Void** is retired as a product concept (no Abandon-as-lifecycle on the happy path). |
| **OW6** | **Recent tournaments** (rename from Recent leagues) lists **Open** workspaces only — including Hidden. **Not** finalized/Official tip kitchens. |
| **OW7** | **No happy-path reopen of Official** via Recent tournaments. Historical catalog / normal site for finished events; admin/repair if needed. |
| **OW8** | **Merge Fixtures + Results** into one **play surface**. Schedule and score entry are one secretary job. |
| **OW9** | Play surface is **stage-scoped**: navigate/select a **stage**, then enter results for that stage’s matches. Single-stage kitchens use the same UI with one stage. |
| **OW10** | **Strip lifecycle chrome** from the happy path: no mandatory Start; no void/Abandon ritual; Advanced is not a peer tab for normal nights (repair/technical only). |
| **OW11** | **Withdraw / replace entrant** — **abandoned** as organizer features. Unplayed = don’t enter results (Finish already voids leftover scheduled). Replace is overengineering. |
| **OW12** | **While Open**, structure may grow (**add stages / place players into stages** as events unfold) — detail in plan. Mid-event **add player into a pre-built full RR** is **not** assumed free (fixture regen); freeze or explicit rebuild = plan, not a blocker for this policy. |
| **OW13** | **Admin Case A** hard-deletes never-official Open junk. **No auto-seal** after Case A (tip unchanged). Tip deletes = Case B/C + seals. |
| **OW14** | Implementation may remap internal `lifecycle_status` (or keep it as storage); **organizers must not need that vocabulary**. |

---

## 4. After create

On successful create (today’s kitchen path and successors):

- State = **Open**, scoreable immediately.
- **Live visible = on** (appears on public Live when other Live rules allow).
- Entrants/fixtures from create remain as today for single-league kitchens.

No mandatory Start click.

---

## 5. Hide (Live visibility)

- First-class **Hide** / **Show on Live** on the Open workspace chrome (header; not “under Setup”).
- Affects **spectator Live only**.
- Hidden Open tournaments stay in **Recent tournaments**.
- Finish allowed while Hidden.

---

## 6. Recent tournaments

- Rename **Recent leagues** → **Recent tournaments**.
- Filter: **Open only** (not `rating_finalized` / Official; not deleted).
- Includes Hidden.

---

## 7. Organizer chrome (happy path)

Essential shape:

**Create → play (stage-scoped matches + scores) → standings / finish confirm → Finish.**

| Secondary | Rare |
|-----------|------|
| Tournament meta, Hide/Show Live | Admin Case A delete; limbo/repair; raw ops |

| Shipped | Policy |
|---------|--------|
| One **Play** tab (`view=play`, optional `stage_id`) | Stage-scoped play surface |
| No Start / no void on happy path | Gone |
| Advanced → muted **Technical / repair tools** | Not peer happy-path tab |
| Setup | **Create / Recent landing only** — not an in-tournament peer tab |
| Players withdraw/replace | Abandoned (CLI may remain) |
| Recent tournaments (Open only) | Includes Hidden |
| Table + Finish confirm (FO5) | End-of-night path |

Future cups may add controls **without** restoring Start/void/dual match tabs.

---

## 8. Finish

Unchanged in intent: **Finish and make official** (+ finish-confirm as shipped). After success: Official; off Live; off Recent tournaments.

---

## 9. Delete

| Who | What |
|-----|------|
| Organizer | No Official/tip delete; Hide ≠ delete |
| Admin | Case A never-official hard delete; Case B/C tip repair + seals |

---

## 10. Relation to RTB and older lifecycle prose

- **Keep:** running package vs Official; no L3/L5 until Finish; Live as broadcast surface; promote+finalize on Finish.
- **Supersede (organizer-facing):** mandatory Start; void/Abandon-as-lifecycle; draft/ready/running as secretary UX; dual Fixtures/Results; withdraw/replace.
- Storage may still use `lifecycle_status = running` for Open — agents follow **this policy** for organizer UX copy and chrome.

---

## 11. Explicitly out of this policy

- Pixel layout beyond OW8–OW10 (shipped in-tournament tabs: Players · Play · Table; Setup = create/Recent landing only).
- On-the-fly stage builder UX (**deferred**).
- RR mid-event “add player” mechanic (**deferred**).
- Migrating historical `void` rows (irrelevant edge; handle opportunistically in code if needed — not a policy epic).
- Case B/C tip delete (L5).

---

## 12. Success criteria (behavioural)

- Create → enter results → Finish with **no Start** and **no void**.
- One stage-scoped play surface (no separate Fixtures vs Results).
- Hide/Show Live without changing Open vs Official; Finish works while Hidden.
- Recent tournaments never lists Official tip kitchens.
- No organizer withdraw/replace on the happy path.
- RTB preserved: no official `amiga_games`/L5 until Finish.

---

## 13. Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | **Implemented** — slices 0–6; OW12 stage builder / mid-event RR add deferred; RTB boundary kept. |
| 2026-07-22 | **Locked intent v1** — Open/Official + Live visible; no Start/void; merge stage-scoped play surface; strip lifecycle chrome; abandon withdraw/replace; Case A no auto-seal; plan separate. |