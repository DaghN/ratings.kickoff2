# Starter prompt — Amiga Track L5 (staging backup + admin delete)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Current slice:** **3** — Case A admin delete (unfinalized / never-official). Slices **0–2 done** (inventory, backup seals, restore).

**Mission:** Implement Track **L5** — full staging backup seals after tip-changing actions; admin-only delete with Case **A/B** and **narrow Case C** (test-under-real); restore via existing Apply-import family. Policy already locked — do not reopen BA/AD decisions unless Dagh expands.

**Out of scope unless Dagh expands:** L6 ground packs, demotion flags, cups (L3), Track C, lock/unlock delete, per-tournament delete passwords, Finish-confirm Phase B, deep mid-history Case C as day-one smoke. **This session = Case A only** — do not implement Case B/C delete/repair yet.

**Related:**
- [`docs/amiga-staging-backup-admin-delete-policy.md`](../../amiga-staging-backup-admin-delete-policy.md)
- [`docs/amiga-staging-l5-backup-delete-implementation-plan.md`](../../amiga-staging-l5-backup-delete-implementation-plan.md) — **§5 inventory filled**; slices 0–2 changelog
- [`docs/amiga-live-ops-platform.md`](../../amiga-live-ops-platform.md) §7.1 (Case A) · §7.4 (`delete-unfinalized-tournament`) · §9
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

**Admin URLs (staging):**
- Backup / restore: `https://ratings.kickoff2.com/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot`
- Apply import: `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot`
- Organizer fixtures: `…/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` (**not** where tip-delete lives)

**Ops note (Jul 2026-07-22):** Staging and local `ko2amiga_work` were verified **parity** (same tip Nottingham III #607, 27474 games, matching export-table counts + checksums). Staging has a **reserve** seal from Backup now. WinSCP already deployed slices 1–2 PHP.

**CLI precedent (Case A-ish, not browser):** `python -m scripts.amiga` / `tournament_fixtures.py` → `cleanup-generated` — ground delete for unplayed generated tournaments. Prefer PHP live path for staging admin UI; may learn from CLI for which L3/L4 tables to wipe.

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga Track L5** — staging backup seals + admin delete/repair.

**Current work: Do slice 3** — Case A admin delete only (unfinalized / never-official / void-eligible generated kitchen). Slices 0–2 are already shipped — do not rebuild backup/restore.

**Read first (in order):**
1. docs/amiga-staging-backup-admin-delete-policy.md (BA* / AD* — locked; AD1 admin-only; AD2 organizer void ≠ tip delete; AD6 backup after delete)
2. docs/amiga-staging-l5-backup-delete-implementation-plan.md — status, **slice 3** row, **§5 inventory** (esp. §5.1 Finish, §5.3 ground tables, §5.5 admin UI), changelog for slices 0–2
3. docs/amiga-live-ops-platform.md §7.1 Case A + §7.4 verb `delete-unfinalized-tournament`
4. docs/amiga-live-ops-practice-track.md §4–§5 (next = slice 3)
5. Existing code to extend (read before inventing):
   - site/public_html/amiga/includes/amiga_backup_seal_lib.php (seal after delete — AD6)
   - site/public_html/amiga/run_backup_ko2amiga.php (admin sibling — Case A UI belongs here or thin admin page, NOT fixtures Advanced)
   - site/public_html/amiga/ops/fixtures.php (organizer Abandon/void only — do not put tip-delete here)
   - scripts/amiga/tournament_fixtures.py cleanup-generated (CLI ground-delete precedent)
6. docs/amiga-staging-handoff.md only if restore/Apply URLs needed for smoke

**Slice 3 deliverable:**
- Admin-only delete of **unfinalized** tournament (no `rating_finalized`, no L5 timeline for that id).
- Delete L3+L4 ground (+ running package / finish override as appropriate). **No present re-project.**
- After success: backup seal (AD6 / BA2). Loud failure if seal fails.
- Refuse Case A path if tournament is finalized (point organizer/admin to Case B later — do not implement B here).
- Organizer remains unable to tip-delete; Abandon/void stays organizer Advanced.

**Verify:** Draft/running abandoned kitchen → gone from Live / DB; seal appears after. Prefer local `ko2amiga_work` smoke first; staging only if Dagh asks — **restore seal must exist first** (already does on staging).

**STOP / hard rules:**
- Do **not** implement Case B or Case C repair in this slice.
- Do not invent demotion, L6 ground packs, or organizer tip-delete.
- Do not use incomplete `amiga_ops_zero_derived()` / fixtures limbo reset as the Case A template.
- UTF-8 on Windows: StrReplace existing files; new files via PowerShell UTF8Encoding false (no Write tool dumps for PHP/MD).
- After ship: UPDATE_DOCS Part A (MEMORY + plan changelog + practice track §4 → slice 4).

**Reply first** with a short understanding summary (Case A only + where UI lives + backup-after), then wait for Dagh's **go** unless he already said go / Do slice 3.
```