# Post-game: contract vs oracle / legacy — discrepancy register

**Authority:** [`website-data-contract.md`](../website-data-contract.md) § Post-game derived-data behavior wins over batch replay helpers, `ratings_cpp.txt`, and prod C++ **legacy** behaviour.

**Parity rule (Jun 2026):** `ab-post-game` diffs PHP ops replay against Python **only where Python implements contract target**. Rows below marked **Fixed Jun 2026** should match after `player_state.py` / `post_game_player_state.php` updates.

Use this list for manual review before prod cutover. Do not treat “Python passed yesterday” as proof of contract compliance for rows still **Open**.

---

## Status legend

| Status | Meaning |
|--------|---------|
| **Fixed** | PHP + Python oracle aligned to contract in repo |
| **Open** | Contract target not fully implemented or oracle still legacy |
| **N/A per game** | Not in scope for `process_completed_game` (different job) |
| **GST OK** | Already matched contract before this pass |

---

## Per-game `playertable` (P2)

| Topic | Contract | Was (legacy) | Status |
|-------|----------|--------------|--------|
| Career `PeakRating` / `LowestRating` | Unset until 20 games; establish at game 20; game 21+ max/min of post-game `Rating`; no gain/loss gate | From game 1; peak only on rating gain in game | **Fixed** — `k2_post_game_player_apply_career_peak_nadir()` / `PlayerState._apply_career_peak_nadir()` |
| Personal extremes (BL/BW/MGS/MGC, sums, rated victim/culprit) | Strict **`>`** on tie; inverse counts move only when credit shifts on strict beat | **`>=`** / **`<=`** on several fields | **Fixed** — PHP + `player_state.py` |
| `highest_rated_victim` / `lowest_rated_culprit` | Strict beat | `>=` / `<=` | **Fixed** |

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
| Game keys (streak, tail, period burst, DB calendar, …) | Crossing game / first cross; **no chrono notebook** | `post_game_milestones.php` + `milestone_sim.py` | **Fixed** (ongoing parity: `ab-post-game --phase p6`; pending keys excluded) |
| `giant_slayer` | Kickoff active #1 SQL on `playertable` before this game write | `k2_post_game_milestones_kickoff_active_top_player_id()` via `apply_giant_slayer_at_kickoff()` | **Fixed** Jun 2026 (`a3cb1c0`; staging sign-off) |
| `clean_sheet_spread` | 10th distinct opponent on a clean sheet in anchor game | `post_game_milestones.php` distinct CS victims | **Fixed** Jun 2026 (`a3cb1c0`; staging sign-off) |
| `perfect_day` / `nightmare_day` | Day-close `achieved_at` (`FinalizeUtcDay` or rebuild SQL) | `ops/includes/day_close_milestones.php` via `CMD=FinalizeUtcDay` / timeline sim — **not** per-game | **N/A per game** — excluded from `ab-post-game` layer 6 diff |
| `rare_blank` | After 50+ career games → first 0-goal game (`NumberGames >= 51`) | PHP + sim | **Fixed** |
| **League event keys** (16) | On **league period finalize** (PER-003) | `k2_league_sync_event_milestones()` in `FinalizeUtcDay` | **N/A per game** — incremental via day tick |
| **League win-count keys** (4) | On finalize | `k2_league_sync_win_milestones()` | **N/A per game** |
| `entered_arena` | On register (`lobby`); simul via prepare seed | `ProcessPlayerRegistered` (live); **prepare §4.7** `seed-lobby` (work) — **not** `replay-to` or timeline sim | **N/A per game** — excluded from layer 6 diff |
| `club_*` rebuild SQL | First `Rating` cross; drop `PeakRating` join when peak-at-20 replay ships | Rebuild SQL may still join `PeakRating` | **Open** — regen `player_milestones_rebuild.sql` after cutover replay |
| `play_streak_100` contract text | Unlock on game that extends day streak to 100 | Doc line still mentions MIN on 100th day in places | **Open** — copy-only in [`website-data-contract.md`](../website-data-contract.md) § chrono table |

---

## `player_play_streaks` (P7)

| Topic | Contract | Implementation | Status |
|-------|----------|----------------|--------|
| Day/week rows + HoF on personal best | After `player_period_games` | `k2_play_streak_after_rated_game()` from ops | **Fixed** in PHP |
| `ab-post-game` layer 7 | Diff vs rebuild | Not implemented | **Open** — tooling only |

---

## Other contract checklist rows

| Topic | Contract | PHP ops | Status |
|-------|----------|---------|--------|
| `player_monthly_league` | *(Removed Jun 2026)* | N/A — table dropped SCH-017 | **Closed** — month via `player_period_league` only |
| P4–P5 period tables | Incremental | Shipped | **Fixed** |
| League finalize (`player_league_award`, totals, …) | Periodic | Not in `process_completed_game` | **N/A per game** |

---

## Prod runtime (coordination, not a code bug)

| Topic | Notes |
|-------|--------|
| **Prod today** | Live games still run **Steve C++** post-game until cutover. |
| **Prod target** | **PHP** `ops/` `ProcessCompletedGame` implements contract; **retire C++ derived writer** when replay + staging sign-off complete — see [`ladder-ops-platform.md`](../ladder-ops-platform.md) §2, [`post-game-register.md`](post-game-register.md). |
| **M1–M7 milestone phases** | Were a **C++ rollout checklist**; same rules now owned by PHP modules + periodic jobs. |

---

## Review checklist (manual)

1. Re-run `python -m scripts.work_prepare ab-post-game --phase p2 --limit 100` (and p6) after P2 rule changes.
2. Spot-check players with `NumberGames` 19→21 for peak/nadir sentinels vs values.
3. Spot-check tied BL/BW margins: holder + inverse counts unchanged on tie.
4. Regenerate milestone rebuild SQL if `club_*` / league copy depends on peak-at-20 data.
5. Update `leaderboards/victims.php` tooltips when personal `>` ships to prod (contract § Personal record pointers).
