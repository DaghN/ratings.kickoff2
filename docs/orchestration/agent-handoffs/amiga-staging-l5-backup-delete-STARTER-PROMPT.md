# Starter prompt — Amiga Track L5 (staging backup + admin delete)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Current slice:** **5** — Case C narrow (delete non-tip M + truncate forward derived + project-present-at + PHP re-finalize forward). Slices **0–4 hardened** (inventory, backup seals, restore-from-`_backups/`, Case A, Case B + `project-present-at` proven vs Jul 18 GitHub seal).

**Mission:** Implement Track **L5** — full staging backup seals after tip-changing actions; admin-only delete with Case **A/B** and **narrow Case C** (test-under-real); restore via Apply-import family (**primary:** apply directly from `_backups/<seal>/`). Policy already locked — do not reopen BA/AD decisions unless Dagh expands.

**Out of scope unless Dagh expands:** L6 ground packs, demotion flags, cups (L3), Track C, lock/unlock delete, per-tournament delete passwords, Finish-confirm Phase B, deep mid-history Case C as day-one smoke. **This session = Case C only** — Case A/B + projector + restore UX shipped; do **not** rebuild them.

**Related:**
- [`docs/amiga-staging-backup-admin-delete-policy.md`](../../amiga-staging-backup-admin-delete-policy.md)
- [`docs/amiga-staging-l5-backup-delete-implementation-plan.md`](../../amiga-staging-l5-backup-delete-implementation-plan.md) — **§5 inventory**; slice 4 harden in changelog
- [`docs/amiga-live-ops-platform.md`](../../amiga-live-ops-platform.md) §7.3 Case C + §7.4 verbs `truncate-derived-after` / `refinalize-forward-from`
- [`docs/amiga-player-inverse-count-timeline-policy.md`](../../amiga-player-inverse-count-timeline-policy.md) — present inverse = **pointer recount** (do not “fix” via snapshot cols)
- [`docs/amiga-live-ops-practice-track.md`](../../amiga-live-ops-practice-track.md) §4–§5
- [`docs/amiga-staging-handoff.md`](../../amiga-staging-handoff.md) — side-pull `-TargetDatabase` for compare (never Force-pull over only healthy work)
- [`docs/amiga-staging-authority-policy.md`](../../amiga-staging-authority-policy.md)

---

## Already shipped (do not re-do)

| Slice | What exists |
|-------|-------------|
| **0** | Plan §5 inventory (Finish path, pack reuse, 14 derived tables, present re-project, admin UI placement) |
| **1** | `amiga/includes/amiga_backup_seal_lib.php` — seals under `amiga/_backups/seal-*/`; Finish auto-seal; admin Backup now; rolling N=8 + reserve every 5th; BA6 refuse PHP delete of reserves |
| **2** | Restore (hardened BA4): primary **Restore into DB now** applies multi-part from `_backups/<seal>/` (no `_import` clobber). Optional Copy→`_import`. Helpers: `amiga_staging_import_lib.php`, `amiga_backup_seal_validate_for_restore()` |
| **3** | Case A `delete-unfinalized-tournament` — admin UI on backup page; CLI verb; refuse finalized; **no auto-seal** (tip unchanged) |
| **4** | Case B `delete-last-finalized-tournament` + `project-present-at` — clear §5.3 derived; re-project at prior tip; **seal after** (AD6; split HTTP). Phases `player_current`→`matchups`→`rest`. Inverse = **pointer recount** (Jul 15 ghosts). Matchups = JOIN pairs + txn (not MariaDB EXISTS). Diagnose counts/timing. Smoke: `scripts/oneoff/amiga_case_b_delete_smoke.php`, `amiga_backup_restore_smoke.php`. Proven tip #607 = GitHub `work-2026-07-18-forum` |

**Admin URLs (staging):**
- Backup / restore / Case A/B / re-project / diagnose: `https://ratings.kickoff2.com/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot` (Build **`l5-s4j-2026-07-22`**)
- Apply import (push tray): `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot`
- Organizer fixtures: `…/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` (**not** where tip-delete lives)

**Ops notes (Jul 2026-07-22):**
- After tip ops fail: **side-pull** `pull_ko2amiga_from_staging.ps1 -TargetDatabase ko2amiga_staging_cmp` — never Force-pull over only healthy `ko2amiga_work`.
- Apache Internal Server Error with no PHP flash ⇒ gateway timeout (~30s), not necessarily app exception → use phased re-project.
- Older seals/pulls may have **changelog schema only (0 rows)** — present still correct via pointer recount; TT under `as=` needs changelog data from simul/push.
- Do **not** seal unrepaired present.

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga Track L5** — staging backup seals + admin delete/repair.

**Current work: Do slice 5** — Case C narrow (delete non-tip M with later finalized events; truncate poisoned forward derived; project-present-at at M−1; PHP re-finalize remaining tips in chrono order; seal after). Slices 0–4 hardened — do not rebuild backup/restore/Case A/B/`project-present-at`.

**Read first (in order):**
1. docs/amiga-staging-backup-admin-delete-policy.md (BA* / AD* — locked; AD4 Case C; AD6 backup after tip repair; BA4 restore from `_backups/`)
2. docs/amiga-staging-l5-backup-delete-implementation-plan.md — status, **slice 5** row, **§5.3–§5.4**, changelog for slice 4 harden
3. docs/amiga-live-ops-platform.md §7.3 Case C + §7.4 verbs `truncate-derived-after` / `refinalize-forward-from` (reuse shipped `project-present-at` + `amiga_finalize_tournament`)
4. docs/amiga-player-inverse-count-timeline-policy.md — present inverse = pointer recount (never snap cols / empty-changelog zero-fill)
5. docs/amiga-live-ops-practice-track.md §4–§5 (next = slice 5)
6. Existing code to extend (read before inventing):
   - site/public_html/amiga/ops/modules/delete_last_finalized_tournament.php (Case B — shared clear-derived + tip guards)
   - site/public_html/amiga/ops/modules/project_present_at.php (reuse phases; do not rewrite inverse/matchup paths)
   - site/public_html/amiga/ops/modules/finalize_tournament.php (forward re-finalize loop)
   - site/public_html/amiga/run_backup_ko2amiga.php (admin UI sibling — Case C belongs here)
   - site/public_html/amiga/includes/amiga_backup_seal_lib.php (seal after — AD6)
7. docs/amiga-staging-handoff.md — restore URL; side-pull `-TargetDatabase` for compare (never Force-pull work as sole healthy copy)

**Slice 5 deliverable:**
- Admin-only delete of finalized **M** that is **not** chrono-last (later finalized events exist).
- Truncate derived for chrono > M−1; delete M ground; project-present-at M−1 (phased if needed); reset + PHP finalize remaining forward events in chrono order.
- After success: backup seal (AD6). Loud failure if seal fails.
- Refuse Case A-style unfinalized (use Case A) and Case B tip-only (use Case B).
- Smoke on **short** forward chain (1–3 events), not mid-2000s.

**Verify:** Test-under-real scenario: delete test M; real tip re-derived; site coherent; seal after.

**STOP / hard rules:**
- Do **not** invent demotion, L6 ground packs, or organizer tip-delete.
- Do not use incomplete `amiga_ops_zero_derived()` as the derived-clear template — follow plan §5.3.
- Do not “fix” present inverse via snapshot columns or empty changelog.
- Limit smoke to short forward chains.
- UTF-8 on Windows: StrReplace existing files; new files via PowerShell UTF8Encoding false (no Write tool dumps for PHP/MD).
- After ship: UPDATE_DOCS Part A (MEMORY + plan changelog + practice track §4 → slice 6 or L5 done).

**Reply first** with a short understanding summary (Case C truncate + re-finalize + where UI lives), then wait for Dagh's **go** unless he already said go / Do slice 5.
```