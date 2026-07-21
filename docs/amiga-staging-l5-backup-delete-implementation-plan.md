# Amiga Track L5 — staging backup + admin delete — implementation plan (Jul 2026)

**Status:** **In progress** — **slices 0–2 done**. Next: **slice 3** (Case A delete).

**Policy (locked intent):** [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) (BA*, AD*).

**Starter:** [`orchestration/agent-handoffs/amiga-staging-l5-backup-delete-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-staging-l5-backup-delete-STARTER-PROMPT.md).

**Parents:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §7 (Case A/B/C) · §9 (backup packs) · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) · [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) · [`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md).

---

## 1. Goal

Ship Track **L5** organizer-ops closure for Ref-League-A:

1. **Full staging backup packs** after successful tip-changing actions (Make official, admin delete) — same artifact family as today’s Apply import (`manifest` + SQL parts).
2. **Admin-only** delete with anchored repair:
   - **Case A** — never-finalized / never-official trash
   - **Case B** — delete **latest** finalized tip + re-project present
   - **Case C (narrow, in scope)** — delete non-tip **M** when later events exist; truncate poisoned derived; re-project at M−1; **re-finalize forward** remaining tip events via **PHP live finalize** (typical accident: test kitchen under a real tip)
3. Restore = Apply import (or picker) from a prior seal — undo without demotion / L6.

**Exit (behavioural):** Dagh can Finish a kitchen → seal exists; admin can delete a tip training event and a under-tip test event; site coherent; restore from previous seal works; no local `prove` required for the happy repair path.

---

## 2. Non-goals

| Out | Why |
|-----|-----|
| **L6** ground packs | Shelved |
| Demotion / soft-exclude flags | Deferred (BA8) |
| Lock/unlock or per-tournament delete passwords | Rejected (AD5) |
| Cups (L3) / Track C | Separate |
| Deep Case C (delete mid-2000s, long forward chains) as day-one | Same verbs; smoke on **short** forward set (1–few events) first |
| Offline/NAS copies | Nice later; WinSCP is outer key |
| Finish-confirm Phase B | Optional separate |

---

## 3. Case vocabulary (agents)

| Case | Meaning | L5 |
|------|---------|-----|
| **A** | No `rating_finalized` / no L5 for that id — delete L3+L4 (+ running package) | **Required** |
| **B** | Delete **chrono-last** finalized tip — strip its derived + re-project present at prior N | **Required** |
| **C** | Delete **M** with finalized events after it — strip M + truncate derived for chrono > M−1; present at M−1; PHP re-finalize forward | **Required (narrow smoke)** |

Case B is Case C with an empty forward set — implement shared primitives, then gate UI.

---

## 4. Slices

| Slice | Deliverable | Verify | STOP |
|-------|-------------|--------|------|
| **0** | Inventory + locks: where Finish commits; export pack writer today (`export_ko2amiga_work` / staging export lib); which derived tables Case B must clear; how present re-project should mirror verify oracles; admin surface placement (ops page vs fixtures Advanced). Write notes into this plan §5. | Read-only | No staging destructive smoke yet |
| **1** | **Backup seal writer** — after successful Make official (and callable from admin): write dated full pack under server `_backups/` (or agreed path); rolling N + reserve rule (BA5–BA6). Prefer reuse of existing dump/part logic. | Staging or local smoke: Finish → pack appears; reserve not deletable via PHP | Do not wire delete yet |
| **2** | **Restore path** — list seals; restore = feed Apply import (copy into `_import` or import-from-path). Admin password only. | Restore prior seal wipes a post-seal kitchen | STOP if replace semantics unclear |
| **3** | **Case A delete** — admin deletes unfinalized / void-eligible generated tournament; no present re-project. Backup after (AD6). | Kitchen draft/running abandoned → gone; seal after | — |
| **4** | **Case B delete** — admin deletes latest finalized tip; clear derived for that id; `project-present-at` prior tip; backup after. | Tip kitchen gone; profiles/LB tip coherent; restore undoes | Prefer work DB or staging with backup first |
| **5** | **Case C narrow** — admin deletes M with ≥1 finalized after; truncate forward derived; re-project; loop PHP finalize for remaining events in chrono order; backup after. | Test-under-real scenario on staging/work: delete test, real tip re-derived; site coherent | Limit smoke to **short** forward chain (1–3 events) |
| **6** | **Docs / practice track** — policy status Implemented (or Partially if C limited); L5 gate note; UPDATE_DOCS Part A; reject inventing L6/demotion | — | — |

**One slice per chat** unless Dagh says continue. Serial feedback still applies for UX bugs after ship.

---

## 5. Slice 0 inventory (filled 2026-07-22)

Read-only inventory for slices 1–5. Chrono key: `(tournaments.event_date, tournaments.chrono, tournaments.id)`.

### 5.1 Finish → tip commit path

**Browser (primary):** `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` — `amiga_ops_gate('organizer')` (admin password also accepted).

| Step | POST `action` | Effect |
|------|---------------|--------|
| 1 | `confirm_finish_order` | Tier E → `amiga_tournament_finish_override` |
| 2 | `reprocess_tournament_derived` | **Finish and make official** |

**Orchestrator:** `amiga_fixture_reprocess_tournament_derived()` in `fixtures.php` → void scheduled → `amiga_promote_running_tournament()` → **`amiga_finalize_tournament()`** (`ops/modules/finalize_tournament.php`) → `lifecycle_status=completed`.

**Tip commit success:** `rating_finalized=1` + `rating_finalized_at`; lifecycle completed; flash + redirect to Table. Advisory lock `'amiga_finalize_tournament'`.

**CLI (oracle only):** `run_process_game.php finalize-tournament` — finalize only (no promote / finish-confirm / lifecycle).

**Post-success backup hook:** **slice 1** — `amiga_backup_seal_write_from_config()` after successful Make official. Limbo repair: Advanced `reset_incomplete_finalize` (not happy path).

**Key files:** `fixtures.php` · `finalize_tournament.php` · `amiga_promote_running_tournament.php` · finish-confirm includes · event/matchup/community/realm/WC persist libs under `amiga/ops/includes/`.

### 5.2 Pack generation reuse

| Path | Artifact | Apply-import compatible? |
|------|----------|--------------------------|
| Local `export_ko2amiga_work.ps1` → `Export-Ko2AmigaStaging.ps1` | `amiga/_import/ko2amiga_manifest.json` + `ko2amiga_*.sql` parts (~40) | **Yes** (push payload) |
| Staging `run_export_ko2amiga.php` + `amiga_staging_export_lib.php` | `amiga/_export/` monolithic pull dump + pull manifest | **No** (pull only) |
| `seal_amiga_work_checkpoint.ps1` | `data/amiga/checkpoints/work-…/` copy of import pack | Local git milestone |
| `amiga/_backups/` | dated `seal-*/` via `amiga_backup_seal_lib.php` (Finish + admin Backup now) | **Yes** (slice 1 shipped) |

**Manifest shape:** `{ generated, source_database, staging_database, parts: [...] }`. Parts: `01` schema → `02`–`09` ground → chunked games/ratings → derived tail. Table list: `staging_export_tables.json` ← `scripts/amiga/staging_export_tables.py`.

**Import/restore:** `run_import_ko2amiga.php` (`amiga_ops_gate('admin')`) reads `_import/` one part per request. **Slice 2 shipped:** Restore… on backup page → `amiga_backup_seal_stage_for_import()` copies seal into `_import/` (+ `.restore_from_seal.json` marker) → Apply import = **full replace** (schema DROP+CREATE + data; same engine as push). Helpers: `amiga_staging_import_lib.php`.

**Slice 1 shipped:** PHP ports chunking + manifest into `amiga/_backups/seal-*/` (includes `inverse_count` part missing from older PS1 packs). Admin Backup now + Finish auto-seal.

### 5.3 Derived tables to clear (Case B/C)

**Case B** — DELETE rows for tip `tournament_id` T (or `as_of_tournament_id` / via games):

| Table | Key |
|-------|-----|
| `amiga_game_ratings` | via `amiga_games.tournament_id` |
| `amiga_player_event_snapshots` | `tournament_id` |
| `amiga_player_elo_rank_at_event` | `tournament_id` |
| `amiga_player_inverse_count_at_event` | `tournament_id` |
| `amiga_player_matchup_at_event` | `as_of_tournament_id` |
| `amiga_tournament_standings` | `tournament_id` |
| `amiga_tournament_catalog_stats` | `tournament_id` |
| `amiga_realm_snapshots` | `tournament_id` |
| `amiga_community_stats_snapshots` | `tournament_id` |
| `amiga_community_stat_facts` | `tournament_id` |
| `amiga_world_cup_stats` | `tournament_id` (WC) |
| `amiga_player_slice_at_event` | `as_of_tournament_id` |
| `amiga_country_slice_at_event` | `as_of_tournament_id` |
| `amiga_wc_hof_snapshots` | `tournament_id` (WC) |

**Case C** — same 14 families for **all** tournaments with chrono **> N** (cutoff = M−1), plus ground delete for M; reset `rating_finalized` / scoring freeze / per-game ratings on remaining forward T; PHP re-finalize in chrono order.

**Ground (when removing event):** `tournaments`, `amiga_games`, entrants, stages, fixtures, stage players, scoring steps, finish override. Do **not** use `amiga_ops_zero_derived()` or fixtures partial reset as the template — incomplete vs `scripts/amiga/modern/clear_derived.py`. Chrono helper: `scripts/amiga/realm_cutoff.py` (`game_cutoff_sql`).

### 5.4 Present re-project

**Present tables to rebuild at cutoff N** (not keyed by deleted id — full re-project):

| Present | From timeline |
|---------|---------------|
| `amiga_player_current` | latest `amiga_player_event_snapshots` ≤ N (+ elo rank + inverse-count overlay) |
| `amiga_generalstats` id=1 | latest `amiga_realm_snapshots` ≤ N |
| `amiga_player_matchup_summary` | from `matchup_at_event` at N + orphan-pair cleanup (**not** simple copy) |
| `amiga_community_stats` id=1 | latest `amiga_community_stats_snapshots` ≤ N |
| `amiga_community_stat_facts` | facts at N |
| `amiga_player_slice_totals` / `amiga_country_slice_totals` | latest slice_at_event ≤ N |
| `amiga_wc_hof_present` id=1 | latest `amiga_wc_hof_snapshots` ≤ N |

**Oracle = verify SQL** (no shipped `project-present-at` yet): `verify_event_snapshots`, `verify_realm_snapshots`, `verify_community_stats`, `verify_player_slice`, `verify_country_slice`, `verify_wc_hof`, `verify_player_matchups`, `verify_inverse_count_changelog`. PHP finalize writers are tip-incremental only — slice 4–5 must implement `project-present-at` mirroring those oracles. FK CASCADE on tournament delete does **not** refresh present → ghost tip until re-project.

### 5.5 Admin UI placement

**Decision:** New **`$admin_password`** page at `/amiga/` sibling to import/export (e.g. `run_admin_ko2amiga.php?once=…`). Copy `amiga_ops_gate('admin')` + `amiga_ops_render_password_form` from `run_import_ko2amiga.php`.

| Reject | Why |
|--------|-----|
| fixtures Advanced / Table | Organizer gate; AD2 void ≠ tip delete; plan §5.5 |
| Stuffing delete into import page alone | Import already large; prefer thin hub or dedicated admin page with cross-links |

**Today:** no browser tip-delete; Advanced = Abandon (void) + Reset incomplete finish only. CLI Case A-ish: `fixtures cleanup-generated`. Planned verbs live under `amiga/ops/` modules, UI under `/amiga/` admin surface.

---

## 6. Technical risks

| Risk | Mitigation |
|------|------------|
| Incomplete derived delete → ghost tip | Inventory in slice 0; verify-lite after B/C; do **not** copy `amiga_ops_zero_derived` / fixtures partial reset |
| Case C re-finalize ≠ historical PHP path | Use **same** `amiga_finalize_tournament` / promote rules as Make official; rely on Finish↔simul-oracle sign-off |
| Backup fails after delete | AD6/BA3: treat as loud failure; do not claim success without seal |
| Web admin deletes reserves | BA6: no PHP delete for reserve |
| Long Case C runtime | Narrow smoke only; timeout/progress UX later |
| Staging PHP export ≠ Apply pack | Slice 1 ported PS1 chunking+manifest to PHP seals; pull monolith remains pull-only |
| No `project-present-at` yet | Slice 4–5 implement from verify oracles (§5.4); matchup_summary needs orphan cleanup |

---

## 7. Verification checklist (L5 done)

- [x] Finish kitchen → backup seal on server  
- [x] Admin restore previous seal → tip matches that seal  
- [ ] Case A: remove never-official generated league  
- [ ] Case B: remove latest finalized training tip; present coherent  
- [ ] Case C: test under real → delete test → real remains correct after re-finalize  
- [ ] Organizer cannot tip-delete  
- [ ] Reserve seals not erasable via website admin UI  
- [ ] No L6 / demotion invented  

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | **Session wrap** — slices 0–2 committed/pushed; staging reserve seal + work↔staging parity verified; local compare/smoke dumps cleaned. Next: slice 3. |
| 2026-07-22 | **Slice 2 done** — Restore stages seal → `_import/` then Apply import (BA4 full replace); import helpers extracted; local smoke PASS (mutate Country → Apply → wiped). Next: slice 3 Case A. |
| 2026-07-22 | **Slice 1 done** — PHP seal writer (`amiga_backup_seal_lib.php`) → `amiga/_backups/`; Finish wires after Make official; admin `/amiga/run_backup_ko2amiga.php`; rolling N=8 + reserve every 5th; BA6 refuse PHP delete reserve. Local smoke PASS (42 parts, mysqldump). Next: slice 2. |
| 2026-07-22 | **Slice 0 done** — §5 inventory filled (Finish path, pack reuse, derived/present lists, admin UI placement). Next: slice 1. |
| 2026-07-22 | Initial L5 plan — backup + Case A/B + **narrow Case C in scope**; slices 0–6. |