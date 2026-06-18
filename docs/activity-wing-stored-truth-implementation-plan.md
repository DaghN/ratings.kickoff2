# Activity wing stored truth — implementation plan

**Status:** **Complete + proven on `kooldb1` (Jun 2026)**  
**Policy:** [`activity-wing-stored-truth-policy.md`](activity-wing-stored-truth-policy.md)  
**Track owner:** closed.

---

## Execution order (locked)

```text
1. Ops slices 0–3  → DONE (schema, P4b, P7, verify + orthogonal parity on WORK)
2. Smoke ladder      → DONE (100 + 1000 games, parity PASS)
3. Steve simul       → DONE (`kooldb1` verify 0 fail Jun 2026)
4. UI slice          → DONE (leaderboards/activity/* + Streaks wing column drop)
5. Closure           → DONE (docs, feature-log, MEMORY)
```

**In scope (ops, done):** `player_play_streaks` month/year + **GST `LongestMonthlyPlayStreak*` / `LongestYearlyPlayStreak*`** post-game, same as day/week.

---

## Orthogonal parity (definition)

**Where:** **`ko2unity_work`** only — the DB `run_ops_sim.php` targets (`local-work`).

**What:** After **incremental** post-game (smoke simul), compare each **new** stored-truth table to **slow oracle queries** on data **already on work** — independent of the incremental writers.

| New stored truth | Oracle (on work, same game range processed) |
|------------------|-----------------------------------------------|
| `player_activity_participation.*` | `COUNT(*)` / `MIN`/`MAX` from `player_period_games` per player and `period_type` |
| `player_play_streaks` all types `best_streak` | Period-list run walker from `player_period_games` |
| `generalstatstable` `Longest*PlayStreak` (month/year) | `MAX(best_streak)` from `player_play_streaks` for that `streak_type` + tie order |
| `player_peak_period_games` (regression) | `MAX(games)` per `(player_id, period_type)` from `player_period_games` |

**Not parity:**

- Filling or proving schema on **`ko2unity_db`** (dev) — separate job; do not mix into this track.
- Treating batch **rebuild scripts** as the definition of correct — they are optional **repair** or a second oracle, not the smoke sign-off.
- Full `ratedresults` rescans except narrow spot checks (e.g. establishing `game_id`).

**When:** After each smoke step (**100**, then **1000**, then longer if approved).

---

## How to use this plan

**Track closed (Jun 2026).** Retained for reference. Slice 4 (Steve full simul) **signed off** on `kooldb1`.

- **One slice per session** unless Dagh says otherwise.
- **Smoke ladder:** `--limit 100` → run parity SQL → **wait for Dagh OK** → `--limit 1000` → parity → **wait for Dagh OK** → longer/full only if Dagh approves.
- Slices 0–6: **done** (incl. Steve simul slice 4).

### Environment

| Item | Value |
|------|--------|
| **Proof DB** | **`ko2unity_work`** · `http://work.ratingskickoff.test/` |
| Dev DB | `ko2unity_db` · `http://ratingskickoff.test/` — **not** parity gate for this track |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Prepare | `php site/public_html/ops/run_prepare.php migrate-work --target local-work` |
| Zero | `php site/public_html/ops/run_prepare.php zero-derived --target local-work` |
| Simul | `php site/public_html/ops/run_ops_sim.php --target local-work` |
| Verify | `php site/public_html/ops/run_verify_ops_sim.php --target local-work` |

---

## Slice map

| Slice | Deliverable | Status |
|-------|-------------|--------|
| **0** | SCH-022 participation; SCH-023 enum + **GST month/year streak columns** | **Done** |
| **1** | P4b: `is_new_period` + participation increment | **Done** |
| **2** | P7: month/year streaks; gate on `is_new_period`; wire from P4 | **Done** |
| **3** | Verify module + parity SQL doc; smoke **100 → 1000** ladder | **Done** |
| **4** | *(external)* Steve full simul | **Done** (`kooldb1` Jun 2026) |
| **5** | UI: `leaderboards/activity/*`, 3 tables, nav; drop Days/Weeks on Streaks wing | **Done** |
| **6** | Closure: registers, MEMORY, policy | **Done** |

**Removed from UI burst:** HoF page slice, milestones slice, dev-DB fill.

**In ops burst:** GST month/year streak columns + P7 post-game HoF updates (mirror day/week).

---

## Slice 0 — Schema

**Goal:** DDL only on work.

### Tasks

- [x] `022_player_activity_participation.sql`
- [x] `023_play_streaks_month_year.sql` — extend `streak_type` enum (`month`, `year`); add `LongestMonthlyPlayStreak*` / `LongestYearlyPlayStreak*` on `generalstatstable` (mirror `014_player_play_streaks.sql` day/week pattern)
- [x] `ops_prepare_constants.php` — truncate list
- [x] `scripts/work_prepare/constants.py` — truncate list (aligned)
- [x] `schema-register.md` SCH-022, SCH-023
- [x] `ops-derived-data-registry.md` DDR-014

### Verify

`migrate-work --target local-work` — **done** Jun 2026. Tables exist; enum accepts `month`/`year`; zero-derived truncates `player_activity_participation`.

---

## Slice 1 — P4b

**Goal:** `is_new_period`; populate `player_activity_participation`.

### Tasks

- [x] Refactor `k2_post_game_upsert_period_game` → `{games, is_new_period}`
- [x] Participation increment + first/last day
- [x] Export flags for P7 from `k2_post_game_update_period_activity_after_game`

### Orthogonal parity (spot, work DB)

After a few manual `run_process_game.php` calls on work:

```sql
-- Player X: active_days must equal oracle:
SELECT COUNT(*) FROM player_period_games
WHERE player_id = ? AND period_type = 'day';

SELECT active_days FROM player_activity_participation WHERE player_id = ?;
```

Same-day second game: `is_new_period = false`, counters unchanged.

**Verified Jun 2026:** replay 100 — global sums match; 0 per-player mismatches; games 10→11 same day player 237 stays `active_days=1`.

---

## Slice 2 — P7

**Goal:** Month/year streak types; gate all four types on `is_new_period`.

### Tasks

- [x] Extend `k2_play_streak_next_period`, `k2_play_streak_hof_column_map`, establishing-game lookup, rebuild walker
- [x] `k2_play_streak_after_rated_game` takes P4 period map + flags; **month/year `k2_play_streak_maybe_update_hof`**
- [x] **No** new milestone unlock hooks
- [x] `scripts/oneoff/verify_activity_wing_parity_work.php` (participation + streak oracle + HoF)

### Smoke + parity

**Verified Jun 2026:** `replay-to --limit 100` on work → `verify_activity_wing_parity_work.php` **PASS** (0 streak oracle mismatches; HoF = table max for day/week/month/year).

---

## Slice 3 — Verify + smoke ladder

**Goal:** Documented parity SQL + automated verify where practical; **100 → 1000** ladder.

### Tasks

- [x] `verify_ops_sim.php` — global sum parity: `SUM(active_*)` vs `COUNT(*)` on `player_period_games` by type (+ streak oracle + HoF)
- [x] Parity SQL pack in `ops/modules/verify_activity_wing_parity.php` header + policy doc
- [x] `player_activity_participation_rebuild.sql` for **repair only** (not smoke gate)
- [x] `rebuild_player_play_streaks.php` month/year + GST month/year (repair only — slice 2)

### Smoke ladder (work DB)

**Step A — 100 games** — **PASS** (slice 2).

**Step B — 1000 games**

```powershell
php site/public_html/ops/run_prepare.php zero-derived --target local-work
php site/public_html/ops/run_process_game.php replay-to --limit 1000 --target local-work
php scripts/oneoff/verify_activity_wing_parity_work.php --target local-work
php site/public_html/ops/run_verify_ops_sim.php --target local-work
```

**Verified Jun 2026:** orthogonal parity **PASS** (396 day periods, streak oracle 0 mismatches, HoF day=10 week=23 month=6 year=1). `run_verify_ops_sim` activity checks all PASS; `league_awards` **fail** expected on Mode A `replay-to` (no day-close / league finalization).

→ **STOP gate met** — Steve simul (slice 4) requested and **passed** on `kooldb1` (Jun 2026).

---

## Slice 4 — External (Steve) — **DONE**

Steve full bootstrap + simul on `kooldb1`; `run_verify_ops_sim` **0 fail / 0 warn** (74,865 processed). Participation sums, per-player counts, SCH-025 reached_at oracle, play-streak oracle, and HoF month/year play-streak rows all **PASS**.

No further repo work on this track.

---

## Slice 5 — UI

**Goal:** Activity wing on local dev / staged after data fill.

### Tasks

- [x] `leaderboards/activity/peaks.php` · `participation.php` · `in-a-row.php` + inner segment nav
- [x] `k2_routes.php`: `lb-activity` + segment keys; redirect `activity-peaks.php`
- [x] Three sortable tables (segment menu — not stacked on one page)
- [x] `lb_nav.php` label **Activity**
- [x] `streaks.php` — remove Days/Weeks columns
- [x] Profile hero games link → Activity peaks (career games sort)
- [x] Removed `activity-mode-toggle.js` from Activity wing pages

- [x] Peaks/Participation/In a row tooltips (counts + date ranges; `k2-table.js` HTML help)
- [x] Peaks default sort **Peak day**; Games column after ELO
- [x] Peak counts → `player/games.php` (`day` or `period`+`anchor`, `from=activity-peaks`, `#day-games` anchor, played-period chevrons)
- [x] Calm-stats cell links (`k2-table-cell-link`) — not link-star

### Verify

Browser on staged + dev; sort columns; tooltips; peak → games drill-down and back link.

---

## Slice 6 — Closure — **DONE**

- [x] `schema-register.md`, `feature-log`, `PROJECT_MEMORY.md`, policy status
- [x] UI + ops shipped; docs reflect repo track complete
- [x] HoF Activity block (participation + play-streak month/year + career celebration); SCH-025 `active_*_reached_at` on P4b
- [x] Steve simul (slice 4) on `kooldb1` — **done** Jun 2026; live cutover = Steve layer C

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Incremental vs oracle drift | Orthogonal parity at 100 and 1000 on **work** |
| Confusing dev DB fill with parity | Policy A11; proof DB = work only |
| UI before Steve simul | UI shipped ahead of simul Jun 2026; `kooldb1` full simul now signed off |
| Long smoke without OK | Ladder stops at 1000 until Dagh approves more |
| Old `activity-peaks.php` bookmarks | Redirect/shim to `activity.php` |
| HoF participation tie dates | SCH-025 stores `active_*_reached_at` on P4b; backfill `rebuild_participation_reached.php` after migrate on each DB |
