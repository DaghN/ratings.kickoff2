# Starter prompt — Amiga Track L5 (staging backup + admin delete)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Current slice:** **4** — Case B admin delete (latest finalized tip + present re-project). Slices **0–3 done** (inventory, backup seals, restore, Case A).

**Mission:** Implement Track **L5** — full staging backup seals after tip-changing actions; admin-only delete with Case **A/B** and **narrow Case C** (test-under-real); restore via existing Apply-import family. Policy already locked — do not reopen BA/AD decisions unless Dagh expands.

**Out of scope unless Dagh expands:** L6 ground packs, demotion flags, cups (L3), Track C, lock/unlock delete, per-tournament delete passwords, Finish-confirm Phase B, deep mid-history Case C as day-one smoke. **This session = Case B only** — Case A shipped; do not implement Case C yet.

**Related:**
- [`docs/amiga-staging-backup-admin-delete-policy.md`](../../amiga-staging-backup-admin-delete-policy.md)
- [`docs/amiga-staging-l5-backup-delete-implementation-plan.md`](../../amiga-staging-l5-backup-delete-implementation-plan.md) — **§5 inventory filled**; slices 0–3 changelog
- [`docs/amiga-live-ops-platform.md`](../../amiga-live-ops-platform.md) §7.2 (Case B) · §7.4 (`delete-last-finalized-tournament` / `project-present-at`) · §9
- [`docs/amiga-live-ops-practice-track.md`](../../amiga-live-ops-practice-track.md) §4–§5
- [`docs/amiga-staging-handoff.md`](../../amiga-staging-handoff.md)
- [`docs/amiga-staging-authority-policy.md`](../../amiga-staging-authority-policy.md)

---

## Already shipped (do not re-do)

| Slice | What exists |
|-------|-------------|
| **0** | Plan §5 inventory (Finish path, pack reuse, 14 derived tables, present re-project, admin UI placement) |
| **1** | `amiga/includes/amiga_backup_seal_lib.php` — seals under `amiga/_backups/seal-*/`; Finish auto-seal; admin Backup now; rolling N=8 + reserve every 5th; BA6 refuse PHP delete of reserves |
| **2** | Restore = `amiga_backup_seal_stage_for_import()` → `_import/` + marker → **Apply import** (full replace). UI on `run_backup_ko2amiga.php`. Helpers: `amiga_staging_import_lib.php` |
| **3** | Case A `delete-unfinalized-tournament` — admin UI on backup page; CLI verb; refuse finalized; **no auto-seal** (tip unchanged) |

**Admin URLs (staging):**
- Backup / restore / Case A: `https://ratings.kickoff2.com/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot`
- Apply import: `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot`
- Organizer fixtures: `…/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` (**not** where tip-delete lives)

**Ops note (Jul 2026-07-22):** Staging and local `ko2amiga_work` were verified **parity** (same tip Nottingham III #607, 27474 games, matching export-table counts + checksums). Staging has a **reserve** seal from Backup now. WinSCP: deploy slice 3 PHP before staging Case A smoke.

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga Track L5** — staging backup seals + admin delete/repair.

**Current work: Do slice 4** — Case B admin delete (latest finalized tip + clear derived + project-present-at prior tip + seal). Slices 0–3 shipped — do not rebuild backup/restore/Case A.

**Read first (in order):**
1. docs/amiga-staging-backup-admin-delete-policy.md (BA* / AD* — locked; AD1 admin-only; AD3 Case B repair; AD6 backup after delete)
2. docs/amiga-staging-l5-backup-delete-implementation-plan.md — status, **slice 4** row, **§5.3–§5.4** derived clear + present re-project, changelog for slices 0–3
3. docs/amiga-live-ops-platform.md §7.2 Case B + §7.4 verbs `delete-last-finalized-tournament` / `project-present-at`
4. docs/amiga-live-ops-practice-track.md §4–§5 (next = slice 4)
5. Existing code to extend (read before inventing):
   - site/public_html/amiga/ops/modules/delete_unfinalized_tournament.php (Case A precedent — guards + seal-after pattern)
   - site/public_html/amiga/run_backup_ko2amiga.php (admin UI sibling — Case B belongs here, NOT fixtures Advanced)
   - site/public_html/amiga/includes/amiga_backup_seal_lib.php (seal after delete — AD6)
   - plan §5.3–§5.4 verify oracles for present re-project
6. docs/amiga-staging-handoff.md only if restore/Apply URLs needed for smoke

**Slice 4 deliverable:**
- Admin-only delete of **chrono-last finalized tip** (Case B).
- Clear derived for that tournament id (§5.3); delete ground; **project-present-at prior tip N** (§5.4).
- After success: backup seal (AD6 / BA2). Loud failure if seal fails.
- Refuse if not chrono-last finalized (Case C later) or unfinalized (use Case A).
- Organizer remains unable to tip-delete.

**Verify:** Tip kitchen gone; profiles/LB tip coherent at prior N; seal after. Prefer work DB or staging with backup first.

**STOP / hard rules:**
- Do **not** implement Case C forward re-finalize in this slice (shared primitives OK if gated).
- Do not invent demotion, L6 ground packs, or organizer tip-delete.
- Do not use incomplete `amiga_ops_zero_derived()` as the derived-clear template — follow plan §5.3.
- UTF-8 on Windows: StrReplace existing files; new files via PowerShell UTF8Encoding false (no Write tool dumps for PHP/MD).
- After ship: UPDATE_DOCS Part A (MEMORY + plan changelog + practice track §4 → slice 5).

**Reply first** with a short understanding summary (Case B + present re-project + where UI lives), then wait for Dagh's **go** unless he already said go / Do slice 4.
```