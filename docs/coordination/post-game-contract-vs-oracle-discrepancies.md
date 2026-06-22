# Post-game: contract vs oracle / legacy — discrepancy register

**Authority:** [`website-data-contract.md`](../website-data-contract.md) § Post-game derived-data behavior wins over batch replay helpers, `ratings_cpp.txt`, and prod C++ **legacy** behaviour.

**Last sweep:** 2026-06-22 — walked every **Open** row against repo code + sign-off path (`run_ops_sim.php` → `run_verify_ops_sim.php`). Several rows were stale: live PHP was **Fixed** but batch SQL / `ab-post-game` tooling still said **Open**.

**Sign-off path (Jun 2026):** **`run_ops_sim.php` + `run_verify_ops_sim.php`** on work / `kooldb1` — not `ab-post-game` (archived dev tooling; layers 0–6 only).

Use this list before prod cutover. Do not treat “Python passed yesterday” as proof for rows still **Open** or **Deferred**.

---

## Status legend

| Status | Meaning |
|--------|---------|
| **Fixed** | Contract target implemented on the **live ops path** (PHP `ProcessCompletedGame` / `FinalizeUtcDay` / register); oracle or verify agrees where checked |
| **Deferred** | Known gap on **batch repair / legacy SQL only** — not used on work simul holy path |
| **Superseded** | Original tracking item replaced by a different verifier or process |
| **Won't fix** | Accepted; no contract change planned |
| **N/A per game** | Not in scope for `process_completed_game` (different job) |
| **GST OK** | Already matched contract before this pass |
| **Closed** | Topic removed from product (e.g. dropped table) |

---

## Sweep summary (2026-06-22)

| Was | Now | Evidence |
|-----|-----|----------|
| `club_*` **Open** (single row) | Split: live **Fixed**; batch SQL **Deferred** | PHP `k2_post_game_milestones_rating_clubs()` uses `Rating` cross; rebuild SQL line 168 still joins `PeakRating` |
| `play_streak_100` contract **Open** | **Fixed** | Contract § chrono correct; PHP `player_play_streaks.php:614`; Python `milestone_sim.py:744` |
| `ab-post-game` layer 7 **Open** | **Superseded** | No layer 7 in `ab_layers.py`; P7 verified by `verify_activity_wing_parity.php` in `run_verify_ops_sim.php` |
| P7 `play_streak_100` unlock | **Fixed** (added) | Same as above — was implemented but not listed under P7 |
| `player_result_streaks` | **Fixed** (added) | P2 writer + `verify_result_streaks_parity.php` in verify simul |

---

## Per-game `playertable` (P2)

| Topic | Contract | Was (legacy) | Status |
|-------|----------|--------------|--------|
| Career `PeakRating` / `LowestRating` | Unset until 20 games; establish at game 20; game 21+ max/min of post-game `Rating`; no gain/loss gate | From game 1; peak only on rating gain in game | **Fixed** — `k2_post_game_player_apply_career_peak_nadir()` / `PlayerState._apply_career_peak_nadir()` |
| Personal extremes (BL/BW/MGS/MGC, sums, rated victim/culprit) | Strict **`>`** on tie; inverse counts move only when credit shifts on strict beat | **`>=`** / **`<=`** on several fields | **Fixed** — PHP + `player_state.py` |
| `highest_rated_victim` / `lowest_rated_culprit` | Strict beat | `>=` / `<=` | **Fixed** |

---

## `player_result_streaks` (P2 extension)

| Topic | Contract | Implementation | Status |
|-------|----------|----------------|--------|
| Per-game match-result runs | Incremental after outcome | `post_game_*` + `player_result_streaks.php` | **Fixed** |
| Verify | Stored rows vs chronological oracle | `verify_result_streaks_parity.php` in `run_verify_ops_sim.php` | **Fixed** — `kooldb1` simul sign-off Jun 2026 |

---

## `generalstatstable` HoF (P3)

| Topic | Contract | Implementation | Status |
|-------|----------|----------------|--------|
| Non-ratio HoF holders | Strict **`>`** | `k2_post_game_server_try_*` uses `>`; `server_records.py` documents `>` | **GST OK** |
| Ratio leader columns on GST | Not written post-game | Not written in PHP | **GST OK** |
| Incremental GST totals | Running totals per game | `k2_post_game_update_generalstats_after_game` | **GST OK** |

---

## `player_milestones` (P6)

| Topic | Contract | Oracle / PHP | Status |
|-------|----------|--------------|--------|
| Game keys (streak, tail, period burst, DB calendar, …) | Crossing game / first cross; **no chrono notebook** | `post_game_milestones.php` + `milestone_sim.py` | **Fixed** — optional regression: `ab-post-game --phase p6` |
| `giant_slayer` | Kickoff active #1 SQL on `playertable` before this game write | `k2_post_game_milestones_kickoff_active_top_player_id()` via `apply_giant_slayer_at_kickoff()` | **Fixed** Jun 2026 (`a3cb1c0`; staging sign-off) |
| `clean_sheet_spread` | 10th distinct opponent on a clean sheet in anchor game | `post_game_milestones.php` distinct CS victims | **Fixed** Jun 2026 (`a3cb1c0`; staging sign-off) |
| `perfect_day` / `nightmare_day` | Day-close `achieved_at` (`FinalizeUtcDay` or rebuild SQL) | `ops/includes/day_close_milestones.php` via `CMD=FinalizeUtcDay` / timeline sim — **not** per-game | **N/A per game** |
| `rare_blank` | After 50+ career games → first 0-goal game (`NumberGames >= 51`) | PHP + sim | **Fixed** |
| **League event keys** (16) | On **league period finalize** (PER-003) | `k2_league_sync_event_milestones()` in `FinalizeUtcDay` | **N/A per game** |
| **League win-count keys** (4) | On finalize | `k2_league_sync_win_milestones()` | **N/A per game** |
| `entered_arena` | On register (`lobby`); simul via prepare seed | `ProcessPlayerRegistered` (live); **prepare §4.7** `seed-lobby` (work) | **N/A per game** |
| `club_*` **live post-game** | First post-game **`Rating`** `>=` threshold (any game #) | `k2_post_game_milestones_rating_clubs()` — `preGameRating < thresh && newRating >= thresh` | **Fixed** — ops simul P6 |
| `club_*` **batch rebuild SQL** | First `NewRating` cross; no `PeakRating` join once peak-at-20 replay ships | `player_milestones_rebuild.sql` line 168: `INNER JOIN … PeakRating >= thresh` | **Deferred** — DDR-052; repair-only (`rebuild_website_derived_data_local.ps1`); holy path = ops simul, not this SQL |
| `play_streak_100` **live + contract** | Unlock on game that **extends** UTC day streak to **100** | PHP `k2_play_streak_after_rated_game()` → `$newLen === 100`; contract § chrono; Python `simulate_play_streak_100_milestones()` | **Fixed** — 0 holders on current data; rebuild SQL empty until someone hits 100 days |

---

## `player_play_streaks` (P7)

| Topic | Contract | Implementation | Status |
|-------|----------|----------------|--------|
| Day/week/month/year rows + HoF on personal best | After `player_period_games` | `k2_play_streak_after_rated_game()` from ops P7 | **Fixed** |
| `play_streak_100` milestone | Day streak crosses 100 on this game | `player_play_streaks.php:614` → `k2_play_streak_maybe_unlock_milestone_100()` | **Fixed** — see P6 row |
| **Verify (sign-off)** | Stored streaks vs period oracle | `verify_activity_wing_parity.php` → `k2_play_streak_oracle_mismatches()` in `run_verify_ops_sim.php` | **Fixed** — `kooldb1` simul Jun 2026 |
| `ab-post-game` “layer 7” | Diff vs Python rebuild | Never existed — `LAYER_REGISTRY` stops at layer 6 (`ab_layers.py`) | **Superseded** — use verify simul above; `ab-post-game` archived per [`post-game-php-development.md`](../post-game-php-development.md) §9 |

---

## Other contract checklist rows

| Topic | Contract | PHP ops | Status |
|-------|----------|---------|--------|
| `player_monthly_league` | *(Removed Jun 2026)* | N/A — table dropped SCH-017 | **Closed** — month via `player_period_league` only |
| P4–P5 period tables | Incremental | Shipped | **Fixed** |
| League finalize (`player_league_award`, totals, …) | Periodic | `FinalizeUtcDay` | **N/A per game** |

---

## Prod runtime (coordination, not a code bug)

| Topic | Notes |
|-------|--------|
| **Prod today** | Live games still run **Steve C++** post-game until cutover. |
| **Prod target** | **PHP** `ops/` `ProcessCompletedGame` + `FinalizeUtcDay`; retire C++ derived writer — [`ladder-ops-platform.md`](../ladder-ops-platform.md) §2, [`post-game-register.md`](post-game-register.md). |
| **M1–M7 milestone phases** | Were a **C++ rollout checklist**; same rules now owned by PHP modules + periodic jobs. |

---

## Review checklist (manual)

1. **Holy path:** `php ops/run_ops_sim.php run --target local-work` then `php ops/run_verify_ops_sim.php --target local-work` — expect 0 fail on work / `kooldb1`.
2. Spot-check players with `NumberGames` 19→21 for peak/nadir sentinels vs values.
3. Spot-check tied BL/BW margins: holder + inverse counts unchanged on tie.
4. **Optional (archived):** `python -m scripts.work_prepare ab-post-game --phase p6 --limit 100` — not required for cutover sign-off.
5. **Deferred only:** when regen `player_milestones_rebuild.sql`, drop `PeakRating` join on `club_*` block (peak-at-20 already on ops simul).
6. Update `leaderboards/victims.php` tooltips when personal `>` ships to prod (contract § Personal record pointers).

---

## Changelog

| Date | Note |
|------|------|
| 2026-06-22 | Full sweep: closed false **Open** rows; split `club_*`; P7 verify superseded `ab-post-game` layer 7; added `player_result_streaks` + P7 `play_streak_100` rows. |
| 2026-06 | P2 personal extremes, `giant_slayer`, `clean_sheet_spread` fixes. |
