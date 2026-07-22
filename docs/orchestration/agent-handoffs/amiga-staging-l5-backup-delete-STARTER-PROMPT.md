# Starter prompt — Amiga Track L5 (staging backup + admin delete)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Current slice:** **5** — Case C narrow (delete non-tip M + truncate forward derived + project-present-at + PHP re-finalize forward). Slices **0–4 done** (inventory, backup seals, restore, Case A, Case B + project-present-at).

**Mission:** Implement Track **L5** — full staging backup seals after tip-changing actions; admin-only delete with Case **A/B** and **narrow Case C** (test-under-real); restore via existing Apply-import family. Policy already locked — do not reopen BA/AD decisions unless Dagh expands.

**Out of scope unless Dagh expands:** L6 ground packs, demotion flags, cups (L3), Track C, lock/unlock delete, per-tournament delete passwords, Finish-confirm Phase B, deep mid-history Case C as day-one smoke. **This session = Case C only** — Case A/B shipped; do not rebuild backup/restore/Case A/B.

**Related:**
- [`docs/amiga-staging-backup-admin-delete-policy.md`](../../amiga-staging-backup-admin-delete-policy.md)
- [`docs/amiga-staging-l5-backup-delete-implementation-plan.md`](../../amiga-staging-l5-backup-delete-implementation-plan.md) — **§5 inventory filled**; slices 0–4 changelog
- [`docs/amiga-live-ops-platform.md`](../../amiga-live-ops-platform.md) §7.3 Case C + §7.4 verbs `truncate-derived-after` / `refinalize-forward-from`
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
| **4** | Case B `delete-last-finalized-tournament` + `project-present-at` — clear §5.3 derived; re-project present at prior tip; **seal after** (AD6). Admin tip/prior + Open links. Smoke: `scripts/oneoff/amiga_case_b_delete_smoke.php` |

**Admin URLs (staging):**
- Backup / restore / Case A/B: `https://ratings.kickoff2.com/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot` (Build `l5-s4-…`)
- Apply import: `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot`
- Organizer fixtures: `…/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` (**not** where tip-delete lives)

**Ops note (Jul 2026-07-22):** Case B local smoke PASS on `ko2amiga_work` (refuse/dry-run/`project-present-at` on tip #607; tip not deleted). WinSCP: deploy slice 4 PHP (`project_present_at.php`, `delete_last_finalized_tournament.php`, `run_backup_ko2amiga.php`, `run_process_game.php`) before staging Case B smoke.

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga Track L5** — staging backup seals + admin delete/repair.

**Current work: Do slice 5** — Case C narrow (delete non-tip M with later finalized events; truncate poisoned forward derived; project-present-at at M−1; PHP re-finalize remaining tips in chrono order; seal after). Slices 0–4 shipped — do not rebuild backup/restore/Case A/B/`project-present-at`.

**Read first (in order):**
1. docs/amiga-staging-backup-admin-delete-policy.md (BA* / AD* — locked; AD4 Case C; AD6 backup after tip repair)
2. docs/amiga-staging-l5-backup-delete-implementation-plan.md — status, **slice 5** row, **§5.3** Case C truncate, changelog for slices 0–4
3. docs/amiga-live-ops-platform.md §7.3 Case C + §7.4 verbs `truncate-derived-after` / `refinalize-forward-from` (reuse shipped `project-present-at` + `amiga_finalize_tournament`)
4. docs/amiga-live-ops-practice-track.md §4–§5 (next = slice 5)
5. Existing code to extend (read before inventing):
   - site/public_html/amiga/ops/modules/delete_last_finalized_tournament.php (Case B — shared clear-derived + tip guards)
   - site/public_html/amiga/ops/modules/project_present_at.php (reuse; do not rewrite)
   - site/public_html/amiga/ops/modules/finalize_tournament.php (forward re-finalize loop)
   - site/public_html/amiga/run_backup_ko2amiga.php (admin UI sibling — Case C belongs here)
   - site/public_html/amiga/includes/amiga_backup_seal_lib.php (seal after — AD6)
6. docs/amiga-staging-handoff.md only if restore/Apply URLs needed for smoke

**Slice 5 deliverable:**
- Admin-only delete of finalized **M** that is **not** chrono-last (later finalized events exist).
- Truncate derived for chrono > M−1; delete M ground; project-present-at M−1; reset + PHP finalize remaining forward events in chrono order.
- After success: backup seal (AD6). Loud failure if seal fails.
- Refuse Case A-style unfinalized (use Case A) and Case B tip-only (use Case B).
- Smoke on **short** forward chain (1–3 events), not mid-2000s.

**Verify:** Test-under-real scenario: delete test M; real tip re-derived; site coherent; seal after.

**STOP / hard rules:**
- Do **not** invent demotion, L6 ground packs, or organizer tip-delete.
- Do not use incomplete `amiga_ops_zero_derived()` as the derived-clear template — follow plan §5.3.
- Limit smoke to short forward chains.
- UTF-8 on Windows: StrReplace existing files; new files via PowerShell UTF8Encoding false (no Write tool dumps for PHP/MD).
- After ship: UPDATE_DOCS Part A (MEMORY + plan changelog + practice track §4 → slice 6 or L5 done).

**Reply first** with a short understanding summary (Case C truncate + re-finalize + where UI lives), then wait for Dagh's **go** unless he already said go / Do slice 5.
```