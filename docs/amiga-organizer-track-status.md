# Amiga organizer track — status (Jul 2026)

**Status:** **Vertical stack v1 complete** on staging — organizer happy path + admin repair + backup seals + mid-chronology insert/delete. Practice track **idle** at L5 gate; next work = serial feedback or named gaps below.

**Read first for ops:** [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) (serial feedback) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) (URLs, sync, import).

**Parents:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md).

---

## 1. Executive summary

| Audience | What works today (staging) |
|----------|----------------------------|
| **Organizer** | Create kitchen league → Play results → Table + finish confirm → **Make official**; optional Hide; newcomer player create; mid-history Finish when later tips exist (Case C insert, loud confirm + auto repair). |
| **Admin** | Full import/export; **Backup now** + **Restore** from `_backups/`; delete unfinalized junk (Case A); delete tip finalized (Case B); delete mid-history finalized M with forward repair (Case C delete). Auto-seal after tip-changing success (BA2). |
| **Dagh (local)** | `ko2amiga_work` repair shop; `export_ko2amiga_work.ps1` → WinSCP → staged import; side-pull compare `-TargetDatabase ko2amiga_staging_cmp`. |

**Not claimed:** browser cup creation (Ref-Cup-A), Lane C media uploads, per-tournament ground packs (L6), demotion flags, staging-native verify suite.

---

## 2. Shipped stack (organizer happy path)

| # | Capability | Policy / plan | Surface |
|---|------------|---------------|---------|
| 1 | **Workspace UX** (Open/Hide; Players·Play·Table; no Start/void) | [`amiga-organizer-workspace-simplification-policy.md`](amiga-organizer-workspace-simplification-policy.md) | `fixtures.php` |
| 2 | **Running vs official** (fixture scores until Make official) | [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) | RTB promote + finalize |
| 3 | **Finish confirm** (Tier E placement before Finish) | [`amiga-organizer-finish-confirm-policy.md`](amiga-organizer-finish-confirm-policy.md) | Table tab |
| 4 | **Player create** (newcomer naming, registry country) | [`amiga-player-create-policy.md`](amiga-player-create-policy.md) | Create flow on fixtures |
| 5 | **Dual passwords** (organizer vs admin POST gate) | [`amiga-staging-handoff.md`](amiga-staging-handoff.md) | `amiga_ops_password_lib.php` |
| 6 | **Case C insert** (mid-history Finish; forward re-finalize) | AD7 · [`amiga-case-c-insert-finish-implementation-plan.md`](amiga-case-c-insert-finish-implementation-plan.md) | `fixtures.php` phased HTTP |
| 7 | **Integer chrono + bump** (insert `+1` / delete `-1` on forward ground) | [`amiga-chrono-integer-policy.md`](amiga-chrono-integer-policy.md) | `amiga_chrono_integer_lib.php` |
| 8 | **Backdate guard** (create date >1 month → admin password) | [`amiga-organizer-backdate-guard-policy.md`](amiga-organizer-backdate-guard-policy.md) **Implemented** · staged smoke PASS | `fixtures.php` create (inline panel JS) |

**Practice-track gates:** L0–L1 done (Ref-League-A); **L5 done** (backup + delete/repair). L3–L4 (cup browser) deferred.

---

## 3. Shipped stack (admin / safety)

| Case | What | Auto-seal (BA2) | Module / UI |
|------|------|-----------------|-------------|
| **A** | Delete never-official Open kitchen | No (tip unchanged) | `delete_unfinalized_tournament.php` · backup page |
| **B** | Delete latest finalized tip | Yes | `delete_last_finalized_tournament.php` · backup page |
| **C delete** | Delete finalized M with later tips; truncate + re-finalize forward | Yes | `delete_finalized_mid_tournament.php` · backup page |
| **C insert** | Organizer Finish M before later tips; same repair family | Yes (`case_c_insert`) | `insert_finalized_mid_tournament.php` · fixtures |
| **Restore** | Full DB from `_backups/<seal>/` without clobbering `_import` | — | `run_backup_ko2amiga.php` |
| **Re-project** | Present at cutoff N (phased; inverse pointer recount) | — | `project_present_at.php` |
| **Import** | Full replace staged DB from `_import/` parts | — | `run_import_ko2amiga.php` |

Policy: [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) **Implemented (v1)**. Plan: [`amiga-staging-l5-backup-delete-implementation-plan.md`](amiga-staging-l5-backup-delete-implementation-plan.md) **Complete**.

---

## 4. Proof record (Jul 2026-23)

| Proof | Result | Artifact |
|-------|--------|----------|
| Export inverse round-trip A/B/C | **PASS** | [`amiga-export-inverse-roundtrip-test-plan.md`](amiga-export-inverse-roundtrip-test-plan.md) |
| Case C delete thorough (M=#16, 10 forward) | **PASS** | Build `l5-case-c-inv-seed-2026-07-23` |
| Chrono integer repair on work | **PASS** | seals `work-2026-07-23-pre-chrono-integer` / `chrono-integer` |
| Staged import of chrono pack + insert/delete ~2022 | **PASS** | side-pull `ko2amiga_staging_cmp` vs work: ground identical; 607 tips / 27474 games; tournament chronos 0 diff; matchup H2H stats 0 diff |
| PHP vs Python rating on present | **46 players** at ~6th decimal epsilon only (known cosmetic; integer career fields match) |

---

## 5. URLs (staging)

| Role | URL |
|------|-----|
| Organizer | https://ratings.kickoff2.com/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot |
| Admin backup / delete / restore | https://ratings.kickoff2.com/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot |
| Import preview / apply | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot |

Local: replace host with `http://ratingskickoff.test`. Use POST password forms — not `?pwd=` in the address bar.

---

## 6. Gaps and deferred (not blockers for league secretaries)

| Gap | Why deferred | Track |
|-----|--------------|-------|
| **Ref-Cup-A in browser** | CLI create first; league path must be boring | L3–L4 |
| **Lane C media** (YouTube/photos on staging) | Editorial DDL + moderation not shipped | L7 |
| **L6 per-tournament ground packs** | Full seal enough for v1 | Shelved |
| **Demotion / soft-exclude flags** | Backups + hard delete suffice | Rejected v1 |
| **Staging verify-derived suite** | Diagnose lite on admin page; full prove stays local | Planned |
| **Pull single tournament from staging** | Full pull script exists; per-id pack shelved with L6 | — |

---

## 7. Recommended next slices (when named)

1. **Ref-Cup-A** — smallest KO browser create after another Ref-League-A ×3 cycle feels boring.
2. **Lane C** — one YouTube URL on a finalized tournament (L7).
3. Serial UX feedback on Ref-League-A happy path (practice track).

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-07-24 | **AD8 staged smoke PASS** — deep-backdate create requires admin password; admin session does not bypass. |
| 2026-07-23 | **AD8 shipped** — create backdate admin password gate. |
| 2026-07-23 | **Initial status doc** — post chrono-integer export, staged insert/delete parity, L5 + Case C insert stack declared v1 complete; AD8 backdate guard locked not shipped. |