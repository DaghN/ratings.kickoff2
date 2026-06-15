# Milestone unlock librarian — track doc

**Status:** Phase 1 **complete**. Phase 2 **complete** (SCH-020 `player_milestone_totals` + read switch + bump). Phase 2b **complete** (SCH-021 `milestone_definitions.holder_count` + catalog read switch + bump).

**Authority:** Live unlock rules stay in [`website-data-contract.md`](website-data-contract.md) § `player_milestones`. This doc owns **write-path consolidation** and slice execution.

---

## Goal

One **librarian** (`k2_milestone_unlock_insert`) owns every **live** `player_milestones` INSERT. Call sites remain **detectors** (post-game rules, day-close, league sync, register). Unlock rows must be **unchanged** vs pre-refactor (same keys, timestamps, source columns).

Phase 2 adds aggregate bump + read switch; bump is **stubbed** until `player_milestone_totals` exists.

---

## Locked decisions

| ID | Decision |
|----|----------|
| L1 | Single writer API: `k2_milestone_unlock_insert(mysqli $con, array $payload): bool` — `true` only when a new row inserted |
| L2 | Idempotency: duplicate `(player_id, milestone_key)` → `false`, no exception |
| L3 | Insert style: `INSERT … SELECT … WHERE NOT EXISTS` (equivalent to today’s ops post-game pattern) |
| L4 | Bump: `k2_milestone_totals_bump()` runs only when L1 returned `true` **and** `player_milestone_totals` table exists — **no-op in Phase 1** |
| L5 | Phase 1 order: librarian refactor → verify on holy ops → Phase 2 schema (Steve) |
| L6 | **Not** routed through librarian (documented exceptions): `ops_seed_lobby.php`, batch `scripts/ladder/sql/archive/*_rebuild*.sql` |

### Payload (all source columns)

| Field | Type | Notes |
|-------|------|--------|
| `player_id` | int | required |
| `milestone_key` | string | required |
| `achieved_at` | string | datetime |
| `value` | int | |
| `source_kind` | `game` \| `league` \| `lobby` \| null | |
| `source_game_id` | int \| null | |
| `source_league_kind` | string \| null | |
| `source_period_type` | string \| null | |
| `source_period_start` | string \| null | date |

### Live dispatch → writers (unchanged events)

| CMD | Writers to route |
|-----|------------------|
| `ProcessCompletedGame` | `post_game_milestones.php` → librarian; `year_in_heaven`, `play_streak_100` via `k2_milestone_insert_game_unlock` |
| `FinalizeUtcDay` | `day_close_milestones.php`, `league_milestones_sync.php`, `league_standings.php` (`k2_league_sync_win_milestones`) |
| `ProcessPlayerRegistered` | `player_milestone_entered_arena.php` |

### Rejected

- MySQL triggers on `player_milestones` for totals
- Phase 1 DDL / read switch before refactor verified
- Folding league event milestone inserts into `k2_league_finalize_instance()` (keep ADR step order)

---

## Phase 1 — slice map

| Slice | Deliverable | STOP |
|-------|-------------|------|
| **1** | `includes/milestone_unlock.php` — librarian + gated bump stub | Module loads from ops `DOCUMENT_ROOT` |
| **2** | `post_game_milestones.php` → librarian | `process-one` spot game; row unchanged |
| **3** | `k2_milestone_insert_game_unlock` delegates to librarian | Grep: no INSERT in post-game tree except librarian |
| **4** | `player_milestone_entered_arena.php` → librarian | `ProcessPlayerRegistered` idempotent |
| **5** | `day_close_milestones.php` → librarian | `FinalizeUtcDay` day-close counts on replay = 0 new |
| **6** | League event + win sync → loop librarian | `FinalizeUtcDay` + `verify_ops_sim` league checks |
| **7** | Grep audit, MEMORY, contract § write path note | Ready for Phase 2 |

### Verification (local)

- PHP: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- Post-game: `php site/public_html/ops/run_process_game.php process-one --target local-dev game_id=N`
- Dispatch: `php site/public_html/ops/dispatch.php CMD=… target=local-work`
- Simul smoke: `php site/public_html/ops/run_verify_ops_sim.php --target local-work` (after slice 6+)

### Grep allowlist (after slice 7)

Direct `INSERT INTO player_milestones` only in:

- `includes/milestone_unlock.php`
- `ops/includes/ops_seed_lobby.php`
- `scripts/ladder/sql/archive/` (batch repair)

---

## Phase 2 preview (deferred)

1. `020_player_milestone_totals.sql` + schema register + backfill
2. Implement `k2_milestone_totals_bump()` + tier map from `milestone_definitions`
3. Switch `k2_milestone_meta_leaderboard_rows()`, `k2_milestone_player_counts()` reads
4. `verify_ops_sim` parity check; Steve cutover; UPDATE_DOCS Part B

## Phase 2b — catalog holder counts (complete)

1. `021_milestone_definitions_holder_count.sql` — `holder_count` column (DDL only)
2. `k2_milestone_holder_count_bump()` +1 per unlock insert; `k2_milestone_holder_counts_rebuild()` after lobby bulk seed only
3. `k2_milestone_holder_counts()`, `k2_milestone_definition_hub()` read stored column (fallback live agg)
4. `k2_milestone_stored_derived_rebuild()` after lobby seed; `milestone_holder_count_parity` in verify (all unlock rows)

---

## Session log

| When | Slice | Notes |
|------|-------|-------|
| 2026-06 | 1–2 | `milestone_unlock.php`; post-game `k2_post_game_milestone_try_insert_game` → librarian |
| 2026-06 | 3–6 | Helpers, register, day-close, league event + win sync → librarian; live INSERT only in librarian + `ops_seed_lobby` |
| 2026-06 | 7 | Grep audit clean; `verify_ops_sim` PASS on `ko2unity_work`; contract + MEMORY updated — Phase 1 closed |
| 2026-06 | P2 | SCH-020 `player_milestone_totals`; bump + rebuild; meta LB + profile reads; parity check PASS on work |
| 2026-06 | P2b policy | `holder_count` = all unlock rows (orphans kept); verify + bump aligned; no post-simul rebuild; 021 DDL-only |
