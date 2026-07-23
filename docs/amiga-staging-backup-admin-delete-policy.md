# Amiga staging backup and admin delete — policy (Jul 2026)

**Status:** **Implemented (v1)** — Jul 2026: L5 slices 0–5 + thorough Case C (M=#16) + inverse finalize seed; slice 6 docs close. Demotion flags and per-tournament ground packs (**L6**) remain **out of scope**.

**Plan / prompt:** [`amiga-staging-l5-backup-delete-implementation-plan.md`](amiga-staging-l5-backup-delete-implementation-plan.md) · starter archived as track-complete note in that plan §8.

**Parents:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) · [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md)

**Related proof:** [`amiga-export-inverse-roundtrip-test-plan.md`](amiga-export-inverse-roundtrip-test-plan.md) (export packing + Case C inverse) · Build **`l5-case-c-inv-seed-2026-07-23`**.

**Threat model (v1):** Protect against mistakes and a **website** admin (`$admin_password`) — not against WinSCP/SSH filesystem access (Dagh retains that outer key).

---

## 1. Locked picture (v1)

Three roles / mechanisms:

| # | Piece | Rule |
|---|--------|------|
| **1** | **Organizers** (`$organizer_password`; admin password also accepted where already wired) | Create kitchens, enter results, void/abandon **never-official** work, **Finish and make official** (incl. finish-confirm Tier E). **No** delete of finalized / published tip events. |
| **2** | **Admins** (`$admin_password`) | Everything organizers can do **plus** destructive tip ops — notably **delete tournament(s)** on staging — and import/export / backup restore surfaces. Delete is **admin-only** (no lock/unlock matrix, no per-tournament delete password in v1). |
| **3** | **Backup system** | After every **successful tip-changing** action, seal a **full staging backup pack** (same family as today’s `_import` manifest + SQL parts). Undo / recover = restore a prior seal via Apply import (or equivalent). |

This is **enough** for KOA scale: rare events, trusted small admin set, organizers need publish/cancel-draft — not forensic ladder editing.

---

## 2. Backup rules (BA1–BA8)

| Id | Decision |
|----|----------|
| **BA1** | **Artifact** = full DB backup pack equivalent to today’s browser rebuild payload: `ko2amiga_manifest.json` + `ko2amiga_*.sql` parts (KOOL convention, not a generic mysqldump API). |
| **BA2** | **When** = **after** successful **tip-changing** actions — at least: **Make official** (append **or** mid-history insert repair per **AD7**) and **admin tip delete** (Case B/C delete — finalized tip / mid-history with repair). Also after explicit admin “backup now” / successful full import if those exist. **Not** after each score entry. **Not** after Case A (unfinalized trash — tip unchanged). |
| **BA3** | **Not before delete by default** — pre-delete tip should already be the previous after-Finish (or after-prior-action) seal. Strict rule: tip-changing success implies after-backup (or loud failure). |
| **BA4** | **Restore** = full replace of staged `ko2amiga_db` from a chosen seal pack (same Apply-import engine). **Primary UX:** apply **directly from** `amiga/_backups/<seal>/` (multi-part, auto-continue) — **does not** overwrite `amiga/_import/` (push tray). Optional advanced: copy seal into `_import` for tray/Apply. |
| **BA5** | **Retention** = rolling last **N** seals (e.g. 5–10) on server; **reserve** seals (e.g. every 5th, or manual milestone) not swept by the rolling cleaner. Exact N tunable at implement time. |
| **BA6** | **Web admin vs filesystem** — website admin must **not** be able to bulk-erase reserve seals through PHP. Rolling cleanup may be automatic; reserve delete = WinSCP/Dagh only (or no UI). |
| **BA7** | **L6 ground packs** = **shelved** — per-tournament packs not planned; full pack is the safety path. |
| **BA8** | **Demotion / soft-exclude flags** = **not required for v1 safety** — backups + admin hard delete (with repair) suffice; demotion deferred to avoid published-set filter surface. |

---

## 3. Delete rules (AD1–AD6)

| Id | Decision |
|----|----------|
| **AD1** | **Admin-only** for removing published / finalized tournaments from staging. |
| **AD2** | **Organizer cancel** = historically void / abandon never-official (Advanced). **Superseded for new UX** by **Hide** (Live visibility) + admin Case A delete — [`amiga-organizer-workspace-simplification-policy.md`](amiga-organizer-workspace-simplification-policy.md). Not the same as admin delete of an official tip event. |
| **AD3** | After successful admin delete of a **finalized tip** event: run **anchored repair** appropriate to the case (v1 target = **Case A** unfinalized trash + **Case B** delete latest finalized — re-project present). Prefer PHP live path on staging; do **not** require local `prove`/`simul` as daily delete. Exact verb names follow live-ops §7.4 when implemented. |
| **AD4** | **Case C delete (narrow) shipped in L5** — delete non-tip **M** with later finalized events (e.g. test under real tip): truncate poisoned forward derived; re-project at M−1; **re-finalize forward** via PHP live finalize. **Proven Jul 2026-23:** thorough M=#16 (10 forward events) after inverse changelog seed fix — [`amiga-export-inverse-roundtrip-test-plan.md`](amiga-export-inverse-roundtrip-test-plan.md). Deep mid-2000s chains still optional later. Plan: [`amiga-staging-l5-backup-delete-implementation-plan.md`](amiga-staging-l5-backup-delete-implementation-plan.md). |
| **AD5** | **No** organizer lock/unlock delete matrix and **no** per-tournament delete password in v1. |
| **AD6** | After successful **tip** delete + repair (Case B/C delete): **BA2** backup of the new tip. **Case A** (unfinalized trash) does **not** auto-seal — tip unchanged; optional Backup now. |
| **AD7** | **Case C insert (mid-history Finish) — design locked, not shipped** — when organizer **Finish and make official** on running **M** whose catalog `(event_date, chrono, id)` sorts **before** ≥1 already-finalized event: **automatic** insert repair (not admin-only, not tip-only refuse). Pipeline: truncate derived > N → reset forward `rating_finalized` (keep ground) → promote M → project-present-at N → finalize M then forward in chrono order → **BA2** seal. Loud secretary confirm listing **k** later events to recompute; phased HTTP like admin Case C. **Authority:** catalog chrono, not wall-clock Finish. Plan: [`amiga-case-c-insert-finish-implementation-plan.md`](amiga-case-c-insert-finish-implementation-plan.md). |

---

## 4. Why this is solid enough

| Need | Covered by |
|------|------------|
| Secretaries run leagues | Organizer path + Finish confirm |
| Drop a bad training night before official | Organizer void |
| Drop a bad **official** tip kitchen | Admin delete + Case B + backup after |
| Undo sabotage / bad admin delete (website password only) | Prior seals on server (esp. reserve) + Apply import; WinSCP as outer key |
| Avoid dual live DBs / demotion UI sprawl | Full packs + hard delete at tip |

**Not claimed:** perfect protection against someone with WinSCP; perfect mid-catalog rewrite without Case C; automatic offline copies (nice later).

---

## 5. Rejected for v1

| Rejected | Why |
|----------|-----|
| Demotion-first instead of backups | Extra read-path surface; backups already answer sabotage |
| Dual raw/main live databases | Sync/authority cost |
| Per-tournament delete passwords / lock bits | Bookkeeping; A deletes B’s unlocked event |
| Backup only on admin request | Easy to forget after Finish |
| Implement L6 ground packs now | Shelved; full pack enough |

---

## 6. Implementation note (not a sprint)

Ship when Track L / live-ops feedback names it (likely around **L5**). Until then: manual rebuild from last local/`_import` pack remains the practical restore (already used). This doc locks **intent** so agents do not invent demotion or L6 as required for “can we delete?”

---

## 7. Changelog

| Date | Change |
|------|--------|
| 2026-07-23 | **AD7** — Case C insert / mid-history Finish design locked (organizer path + BA2); implementation plan linked; not shipped. |
| 2026-07-23 | **Implemented (v1)** — L5 Case A/B/C + seals + inverse seed; thorough Case C M=#16 PASS; AD4 note; L6 still out. |
| 2026-07-22 | **AD2 note** — organizer void superseded by Hide + Case A (workspace simplification policy). |
| 2026-07-22 | **BA4 restore UX** — primary apply-from-`_backups/` (Build `l5-s4j`); `_import` copy optional. |
| 2026-07-22 | **AD4** — narrow Case C in L5 scope (test-under-real); L5 plan + starter linked. |
| 2026-07-22 | **Locked v1 intent** — organizer vs admin; backup-after tip actions; admin-only delete; L6/demotion out; retention + web-admin threat model. |