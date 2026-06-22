# Derived Data Registry (DDR)

**Status:** **v1 (Jun 2026)** — contract post-game table mapped; periodic/simul paths updated after `FinalizeUtcDay`.  
**Charter:** [`ops-completeness-charter.md`](ops-completeness-charter.md)  
**Orchestration:** [`ops-orchestration-adr.md`](ops-orchestration-adr.md)  
**Verify:** `php ops/run_verify_ops_sim.php` after local simul

One row per **logical derived artifact**. Not every column gets its own row.

---

## Column definitions

| Column | Values / notes |
|--------|----------------|
| **ID** | `DDR-###` stable |
| **Artifact** | Table(s) / milestone group / behaviour |
| **Trigger** | `per_game` · `utc_day_close` · `register` · `prepare` · `batch_only` · `retired` |
| **Depends on** | Other DDR IDs |
| **Contract** | [`website-data-contract.md`](../website-data-contract.md) § Post-game |
| **Live CMD** | Dispatch CMD when incremental |
| **Module** | PHP path or SQL |
| **Simul (Mode C)** | `Y` if `run_ops_sim` fills it |
| **Incremental** | `Y` / `N` / `partial` |
| **Batch repair** | REP / rebuild script |
| **Code** | `ok` · `gap` · `warn` · `n/a` |

---

## Per game — `ProcessCompletedGame`

| ID | Artifact | Trigger | Module | Simul | Incr. | Code | Website (primary) |
|----|----------|---------|--------|-------|-------|------|-------------------|
| DDR-001 | `ratedresults` derived | per_game | `process_completed_game.php` | Y | Y | ok | game.php, lists |
| DDR-002 | `playertable` career + extremes | per_game | `post_game_player_state.php` | Y | Y | ok | ranked1–7, profiles |
| DDR-003 | `generalstatstable` HoF (non-ratio) | per_game | GST helpers | Y | Y | ok | records / ranked |
| DDR-004 | `player_period_games` | per_game | period burst | Y | Y | ok | status, activity |
| DDR-005 | `player_period_league` inputs | per_game | `league_standings.php` | Y | Y | ok | status leagues |
| DDR-006 | `player_peak_period_games` | per_game | peaks | Y | Y | ok | status peaks |
| DDR-007 | `server_daily_activity` | per_game | server activity | Y | Y | ok | status charts |
| DDR-008 | `player_matchup_summary` | per_game | matchup | Y | Y | ok | profiles |
| DDR-009 | `server_period_game_totals`, `server_period_matchups` | per_game | server period | Y | Y | ok | status |
| DDR-010 | P6 game milestones (~90 keys) | per_game | `milestone_unlock.php` → `post_game_milestones.php` | Y | Y | ok | garden, profiles |
| DDR-011 | `player_play_streaks` | per_game | `player_play_streaks.php` | Y | Y | ok | Activity in-a-row; GST day/week/month/year; `best_anchor_start` (SCH-024) |
| DDR-012 | `player_milestone_totals` | per_game + day + register | `milestone_unlock.php` bump; `k2_milestone_totals_rebuild()` repair | Y | Y | ok | meta LB, profile hero |
| DDR-013 | `milestone_definitions.holder_count` | per_game + day + register | `milestone_unlock.php` bump (+1/unlock); `k2_milestone_holder_counts_rebuild()` lobby prepare only | Y | Y | ok | hub catalog, milestone detail |
| DDR-014 | `player_activity_participation` | per_game | `post_game_period_activity.php` (P4b) | Y | Y | ok | Activity participation wing — slice 1 writer; repair `player_activity_participation_rebuild.sql` |
| DDR-015 | `player_result_streaks` | per_game | `player_result_streaks.php` | Y | Y | ok | Streaks LB boundaries; verify `result_streak_oracle`; repair `scripts/rebuild_player_result_streaks.php`; SCH-026 |

**Excluded from per-game (by design):** `perfect_day`, `nightmare_day`, `entered_arena`, league medal keys — see below.

---

## UTC day close — `FinalizeUtcDay`

| ID | Artifact | Depends | Module | Simul | Incr. | Code | Website |
|----|----------|---------|--------|-------|-------|------|---------|
| DDR-020 | `player_league_award`, `league_period` | DDR-005 | `finalize_league_period.php` | Y | Y | ok | ranked9, honours |
| DDR-021 | `player_league_totals`, slice totals | DDR-020 | `league_standings.php` | Y | Y | ok | ranked9 |
| DDR-022 | `league_wins_10/50/100/500` | DDR-020 | `k2_league_sync_win_milestones` | Y | partial | ok | garden |
| DDR-023 | 16 league event keys | DDR-020 | `ops/includes/league_milestones_sync.php` | Y | Y | ok | garden |
| DDR-024 | `perfect_day`, `nightmare_day` | DDR-004 | `ops/includes/day_close_milestones.php` | Y | Y | ok | garden, individual3 |
| DDR-025 | Day tick orchestrator | DDR-020–024 | `finalize_utc_day.php` | Y | Y | ok | — |

**Mode A (`replay-to`) only:** DDR-001–011 partial; DDR-020–025 **missing** → empty honours / day-close.

---

## Prepare & register

| ID | Artifact | Trigger | How | Simul | Code | Notes |
|----|----------|---------|-----|-------|------|-------|
| DDR-026 | `milestone_definitions` | prepare | `seed-catalog` | Y | ok | Catalog, not unlocks |
| DDR-030 | `entered_arena` | prepare + register | `seed-lobby` / `ProcessPlayerRegistered` | Y* | ok | *Sim = prepare §4.7 only |

---

## Batch repair only (not daily ops)

| ID | Artifact | Batch | When |
|----|----------|-------|------|
| DDR-050 | Full `player_milestones` | `player_milestones_rebuild.sql` | Parity / repair |
| DDR-051 | Website aggregates | `rebuild_website_derived_data_local.ps1` | Mode B shortcut |
| DDR-052 | `club_*` SQL | rebuild SQL | **Deferred** — remove redundant `PeakRating` join on regen; live P6 uses `Rating` only ([`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md)) |

---

---

**Retired product ideas** (not DDR rows): [`archive/retired-product-decisions.md`](../archive/retired-product-decisions.md).

---

## Phase 1 sweep (Jun 2026)

| Source | Status |
|--------|--------|
| Contract § Post-game derived-data | Mapped DDR-001–011, 020–025 |
| Discrepancy register | Linked; `club_*` live **Fixed**; batch SQL = DDR-052 **Deferred** |
| `ops_dispatch.php` | `ProcessCompletedGame`, `FinalizeUtcDay`, `ProcessPlayerRegistered` |
| `process_completed_game.php` chain | Matches DDR-001–011 |
| Prepare §4.5 / §4.7 | Truncates + lobby seed |
| Website spot-check | Index below — manual after local verify |

---

## Verification

**`run_verify_ops_sim.php`** — **read-only** work-DB checks after simul. Does **not** invoke prepare, timeline sim, `ProcessCompletedGame`, `FinalizeUtcDay`, or batch rebuild scripts. Safe to run without starting another replay.

**Narrative (misreads to avoid):** [`ops-simul-runbook.md`](ops-simul-runbook.md) § Verify.

### Local sequence

| Phase | Command |
|-------|---------|
| Day zero | `php ops/run_prepare.php prepare --target local-work` |
| Proof / smoke | `run_timeline_sim.php run --stop-at …` **or** `run_ops_sim.php run --until-game-id 500` |
| Optional gate | `php ops/run_verify_ops_sim.php --target local-work` |
| Depth (Steve) | `run_ops_sim.php run --until-game-id 74879` on staging when local gate passes |

### What verify checks (SQL only)

| Id | Meaning | Short local run |
|----|---------|-----------------|
| `rated_games` | Processed vs unprocessed tail | Unprocessed tail → **warn** |
| `six_value` | Contract day totals = processed count | **Fail** if real inconsistency |
| `league_awards` | Awards + finalized periods exist | Often **fail** until enough history — **not** batch trigger |
| `league_milestones` | Distinct league-related keys | Often **warn** |
| `day_close` | `perfect_day` / `nightmare_day` counts | Informational |
| `lobby_seed` | `entered_arena` vs `JoinDate` | Prepare, not sim depth |
| `game_milestones` | Game-sourced rows | **Warn** if empty after no games |
| `milestone_totals_parity` | `player_milestone_totals` vs unlock rows | **Fail** if mismatch (SCH-020) |
| `milestone_holder_count_parity` | `milestone_definitions.holder_count` vs all unlock rows per key | **Fail** if mismatch (SCH-021) |
| `activity_participation_sum_*` | `SUM(active_*)` vs `player_period_games` row counts | **Fail** if mismatch (DDR-014) |
| `activity_participation_per_player` | Per-player participation vs period counts | **Fail** if mismatch |
| `activity_play_streak_oracle` | `best_streak` vs period-list walker | **Fail** if mismatch (DDR-011) |
| `activity_hof_play_streak_*` | HoF `Longest*PlayStreak` vs `MAX(best_streak)` | **Fail** if mismatch |
| `result_streak_oracle` | `player_result_streaks` vs chronological walker + `playertable.Longest*` | **Fail** if mismatch (DDR-015) |

Exit **1** only on severity **`fail`**. Warnings do not fail the run.

### What verify does not replace

| Need | Tool |
|------|------|
| Ground parity at prepare | `run_prepare.php` built-in parity |
| Frozen dev / layer diffs | `ab-post-game`, spot SQL, site @ checkpoint |
| Repair empty derived | Re-prepare + Mode C simul — **not** Mode B batch as happy path |

| Check | Tool |
|-------|------|
| Six-value + league + milestones (work internal) | `run_verify_ops_sim.php` |
| Layer diffs | `ab-post-game` (exclude `entered_arena`, day-close as documented) |
| Site spot | ranked9, garden, status @ checkpoint |

**Steve staging:** **Signed off Jun 2026** — full bootstrap + simul + `run_verify_ops_sim` PASS on `kooldb1` (participation, play-streak, milestone librarian parity included). **Next:** Live phase — see [`ops-simul-runbook.md`](ops-simul-runbook.md) § Staging sign-off.

---

## Website consumers

| Surface | DDR |
|---------|-----|
| ranked1–7, ranked10 | 002 |
| ranked5 | 002 |
| ranked9, league honours | 020–023 |
| individual3, game.php | 001, 024 |
| Milestone garden | 010, 022–024, 030 |
| status / realm | 004–007, 020 |

---

## Change log

| Date | Change |
|------|--------|
| Jun 2026 | v1 inventory; verify runner; Mode C simul flags |
| Jun 2026 | DDR-030 prepare-only for sim; `run_ops_sim` |
| Jun 2026 | Verification § — verify read-only; short-run expectations; no batch-as-gate |
| Jun 2026 | Rating fade removed from active docs — [`archive/retired-product-decisions.md`](../archive/retired-product-decisions.md) |
| Jun 2026 | Staging simul sign-off — verify PASS + visual parity; AUD-004 closed |
