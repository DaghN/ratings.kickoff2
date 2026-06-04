# Post-game PHP — development playbook (local / work)

**Status:** **Jun 2026** — **P0–P6 shipped**. Parity: `ab-post-game --phase p6` (layers 1–6). PHP incremental milestones in `post_game_milestones.php`; Python oracle `scripts/ladder/milestones.py` + `milestone_sim.py`.
**Audience:** Dagh, Cursor agents.

**This doc is how we build and test.** It does **not** replace:

| Doc | Role |
|-----|------|
| [`website-data-contract.md`](website-data-contract.md) | **What** each derived table/column must do (behaviour authority) |
| [`ladder-ops-platform.md`](ladder-ops-platform.md) | Steve boundary, `dispatch.php`, `ops/` layout, prod target |
| [`work-db-prepare.md`](work-db-prepare.md) | Prepare, zero derived, simul modes A/B/C |
| [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) | Core ladder column lists for reset/replay |
| [`ratings_cpp.txt`](ratings_cpp.txt) | **Prod today** — per-game block shape and field order (inspiration; not the speed or tie-policy target) |

---

## 0. Authority and performance (read before coding)

### 0.1 What to implement — three sources, one rank

| Rank | Source | Use it for |
|------|--------|------------|
| **1 — Must match** | [`website-data-contract.md`](website-data-contract.md) (+ [`records-post-game-exception.md`](coordination/records-post-game-exception.md) for GST records) | **Correctness:** column meanings, processing order, tie policy **targets** (`>` on HoF and, when shipped, playertable personal extremes), UTC, incremental post-game rules. |
| **2 — Structural inspiration** | [`ratings_cpp.txt`](ratings_cpp.txt) (`RatingProcedureUnity` per-game block) | **What prod does today:** which fields get updated per game, rough sequencing (ratedresults → playertable → generalstatstable), formulas that are not spelled out elsewhere. |
| **3 — Parity oracle (batch)** | `scripts/ladder/` (`elo.py`, `outcome.py`, `player_state.py`, `server_records.py`, …) | **Checkpoint diffs** — oracle must be updated when contract changes (see [`post-game-contract-vs-oracle-discrepancies.md`](coordination/post-game-contract-vs-oracle-discrepancies.md)). **Do not** copy replay loop structure (memory + batch finalize). |

**When sources disagree:** **Contract wins.** Update PHP ops and Python oracle together. C++ / old oracle behaviour is legacy only. Prod target is **PHP post-game**, not extending C++ ([`ladder-ops-platform.md`](ladder-ops-platform.md) §2).

### 0.2 Speed — first-class requirement

Post-game PHP must be **fast per game** — suitable for Steve calling it **once after every live match**, and for **Mode A sim** over ~75k games without “batch cheat” shortcuts.

| Requirement | Detail |
|-------------|--------|
| **Per-game budget** | Small constant number of SQL round-trips; reads/writes scoped to this `game_id`, players A/B, and GST row `id=1`. |
| **Incremental only** | Update running totals on `playertable` and `generalstatstable` from values already computed for this game — see §3. |
| **No legacy slow paths** | Do **not** port C++ patterns that scan all of `ratedresults` per game (e.g. old `RecentAverageRating` query, full-table GST `SUM`). |
| **Sim scale** | `replay-to` through 100 / 1000 games should feel like a dev smoke test, not a batch job; full history is late confidence, not every edit. |
| **Server note** | [`ladder-ops-platform.md`](ladder-ops-platform.md) §2 — PHP on prod is acceptable; that is not a licence for per-game full-history scans. |

**Acceptance smell-test:** if one `process_completed_game` call does an unbounded `ratedresults` aggregation, it fails this playbook even if parity passes.

---

## 1. Goal

One shared function for **all derived updates after a single rated game**:

```text
k2_ops_process_completed_game(mysqli $con, int $gameId): void
```

- **Live (later):** Steve calls `php …/ops/dispatch.php CMD=ProcessCompletedGame game_id=<id>` once per game.
- **Sim (target):** Same function in a loop on `ko2unity_work` in `Date ASC, id ASC` order — **each iteration commits**; no cross-game memory; no end-of-run batch fixes.
- **Dev (now):** Prove via a dev runner (e.g. `ops/run_process_game.php`) **before** shipping `dispatch.php` — see [`ladder-ops-platform.md`](ladder-ops-platform.md) §6.6.

Ground truth is **already in** `ratedresults` when post-game runs (Steve insert today; sim assumes row exists). Post-game **reads** `id`, `Date`, `idA`, `idB`, goals, etc.; it does **not** re-insert the match.

---

## 2. Simul model (read before coding)

### 2.1 PHP sim = live shape

| | **PHP (target)** | **Python `ladder run` (reference batch replay)** |
|--|------------------|--------------------------------------------------|
| Unit of work | One `k2_ops_process_completed_game` per game | Loop in memory; batch DB writes |
| `playertable` | Updated and **committed each game** | Updated in RAM; one bulk write at end |
| Network distinct counts | Increment from **bounded** DB checks or flags — **no** `finalize_network_counts` after N games | `finalize_network_counts_from_rows` **once** after full slice |
| GST server totals | **Increment** row `id=1` from this game — **no** `SELECT SUM(…) FROM ratedresults` per game | `compute_server_aggregates()` **once** at end (full scan OK there) |
| GST HoF holders | Load GST row → strict `>` compare → write — per game | `ServerRecordState` in memory during replay |

**Anti-pattern (do not ship):** `replay-to` that keeps a `$players` map across games, only writes `ratedresults` in the loop, then batch-finalizes playertable or network counts. That can match Python **end state** while failing to test prod behaviour.

### 2.2 Simul modes (work DB)

From [`work-db-prepare.md`](work-db-prepare.md) §5:

| Mode | Use for PHP post-game dev |
|------|---------------------------|
| **A — Game-only** | **Yes** — N× `process_completed_game`, same as live. |
| **B — Batch website rebuild** | Parity for **aggregate tables** only when PHP does not own them yet — **not** a substitute for Mode A. |
| **C — Timeline** | `run_ops_sim.php` / `run_timeline_sim.php` — post-game + **`FinalizeUtcDay`** per UTC day. **`entered_arena`:** prepare lobby seed (§4.7), not timeline. Runbook: [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md). **Mode A** (`replay-to` alone) is **not** ops-complete — use Mode C. |

Python Mode A today still batch-finalizes some ladder fields at end; treat Python as **oracle for checkpoints**, not as the PHP loop structure.

### 2.3 P6 milestones — in scope vs out of scope (Jun 2026)

**`ProcessCompletedGame` / `replay-to` use the same code path.** One commit per game. **No** chrono notebook, **no** `ratedresults` re-sim hydrate, **no** replay tail batch (`seed_lobby`, day-close finalize).

**Unprocessable ground truth (skip, do not fatal):** `k2_ops_rated_game_skip_reason()` — `idA`/`idB` ≤ 0, same id, missing goals, or already processed (`NewRatingA` set). Logs `[SKIP] ratedresults id=… reason=…` and continues (`replay-to`) or exits 0 (`dispatch.php`). C++ live only rejected **-1**; historical rows with `idA=0` are skipped on PHP replay (see `docs/ratings_cpp.txt` `RatingProcedureUnity` gate).

**Public display before processing:** `k2_rated_game_is_processed()` (`NewRatingA` set) — game lists show scoreline from goals; Elo columns **`-`** until processed ([`parity-audit-backlog.md`](coordination/parity-audit-backlog.md) **AUD-006**).

**Parity audit (Jun 2026):** Closed — no critical blockers; see backlog **AUD-001–006**. **Ops pipeline (AUD-004/005):** closed — staging `run_ops_sim` + verify PASS + visual sign-off; **next:** Live phase on `kooldb1` ([`coordination/staging-work-steve-brief.md`](coordination/staging-work-steve-brief.md) §4.4).

| In scope (per rated game, DB-backed) | Out of scope |
|--------------------------------------|--------------|
| Exists, streak/tail/network/matchup, period burst, rating `club_*`, `rare_blank`, debut opponent awards, **`giant_slayer`** (ladder SQL), **`daily_habit`** / **`monthly_regular`** (`player_period_games`) | **`perfect_day`**, **`nightmare_day`** — Mode C day-close or rebuild |
| `play_streak_100` via `player_play_streaks.php` | **`entered_arena`** — prepare seed lobby + live `ProcessPlayerRegistered` (not per-game replay) |
| `united_nations` via **`DrawingStreak`** on `playertable` | **`on_the_scoresheet`**, **`merchant_streak`**, **`minimalist_merchant`**, **`knife_edge`**, **`unlucky`** via SCH-018 columns (`ScoreStreak`, …) |
| `weekly_regular`, `year_round` | **`player_period_games`** bounded week/month queries | Implemented (same path as `daily_habit` / `monthly_regular`) |

**Parity:** `ab-post-game` layer 6 excludes only `perfect_day`, `nightmare_day`, `entered_arena`. Apply **SCH-018** before replay; see [`post-game-milestone-facilitators-pending.md`](coordination/post-game-milestone-facilitators-pending.md).

---

## 3. `ratedresults` access policy

The site avoids **full-history scans on read paths** (profile indexes, stored peak tables, Status aggregates). Post-game must not reintroduce that pattern **per game**.

| Allowed | Forbidden in per-game post-game |
|---------|----------------------------------|
| Read **this** game row (ground + already-derived cols you need) | `SELECT COUNT(*) / SUM(…) FROM ratedresults` with **no** `id` / pair / player bound |
| **Bounded** history: e.g. `EXISTS (… prior pair game … AND id < ?)` with index-friendly predicates | Rescan entire table each game to refresh GST totals |
| Read **two** `playertable` rows (A, B) at start of game | Column **removed** (SCH-016) — see §4 |
| Increment stored counters on `generalstatstable` id=1 | End-of-run `finalize_*` over all games in PHP sim |

**Contract note:** “Sums from full corpus” on GST aggregate columns describes the **meaning** of the number, not the implementation. Live and PHP sim should **maintain running totals** on `generalstatstable` (and derived cols on the game row you just wrote).

**Indexes:** Use existing `idx_ratedresults_idA`, `idx_ratedresults_idB`, `idx_ratedresults_date` for any bounded history probe.

### 3.1 Stored facilitators (optional, flagged)

While implementing a phase, **look for** SQL that is correct but **heavy on the per-game hot path** — repeated probes, or bounded history reads that still feel like “fairly slow” work every match. You are **not** asked to squeeze every millisecond; you **are** asked to **flag** candidates where a **migrated, incrementally maintained** field could replace recurring queries.

| Step | What to do |
|------|------------|
| **Default** | Ship the phase with incremental updates from rows already loaded/written (§3, §0.2). |
| **Notice** | If a query runs often per game (e.g. many `EXISTS`/`COUNT` on `ratedresults` for the same pair) or cannot be reduced to one cheap indexed lookup, note it: **Slow-query candidate** — query shape, how often per game, rough idea for stored truth. |
| **Promote** | Open a migration (**SCH-NNN** in `schema/migrations/`) + contract § only when the candidate is on the hot path and indexes alone are not enough. Discuss with Dagh before widening `playertable` or adding new aggregate tables. |
| **Implement** | New stored fields must update **inside** `k2_ops_process_completed_game` (same transaction as the rest of the phase), not in a batch job after sim, if later games depend on them. |

**Good facilitator patterns (examples):**

- **Cumulative counters** on `generalstatstable` id=1 or `playertable` (already the default for GST totals).
- **Directed pair rows** — contract already has `player_matchup_summary`; maintaining A→B stats there may replace repeated “have they played before?” history probes in P2+.
- **Small side tables** — often easier than new wide `playertable` columns; register in [`schema-register.md`](coordination/schema-register.md).

**Do not:**

- Block P1 on schema design for later phases — **flag** and continue.
- Add duplicate stored truth in two places that can drift.
- Use facilitators to avoid per-game commit / simul shape (§2).
- Reintroduce full-history scans “just once per game” (still forbidden in §3).

**Authority for new stored fields:** [`website-data-contract.md`](website-data-contract.md) post-game rule + parity; structural hint from [`ratings_cpp.txt`](ratings_cpp.txt) or `scripts/ladder/` as today.

---

## 4. Retired / out-of-scope columns

| Item | Status |
|------|--------|
| **`playertable.RecentAverageRating`** | **Dropped on work DB** — `schema/migrations/016_drop_playertable_recent_average_rating.sql` (SCH-016) runs in prepare **migrate** step. Do not reference in PHP post-game or parity. Python replay no longer writes it. Prod C++ may still expect the column until cutover. |
| **`Display = 1` + NULL career fields** | Valid on work between zero-derived and replay catch-up; **not** a post-game writer bug. Public site uses `k2_fmt_*` in `includes/k2_safety.php` — see [`playertable-schema.md`](playertable-schema.md). NULL-as-zero storage vs display: [`coordination/parity-audit-backlog.md`](coordination/parity-audit-backlog.md) **AUD-001**. |
| **Ratio leader columns on `generalstatstable`** | Dropped — leaders from `playertable` at read time ([`records-post-game-exception.md`](coordination/records-post-game-exception.md)). |
| **`player_milestones`, period aggregates, …** | Later phases (P4+) — contract § Post-game derived-data behavior. |

---

## 5. Code layout (planned)

```text
site/public_html/ops/
  run_process_game.php          # dev runner (CLI) — like run_prepare.php
  includes/
    ops_bootstrap.php           # CLI, mysqli, time_zone +00:00, DB guards
    post_game_*.php             # elo, player state, GST, constants — one concern per file
  modules/
    process_completed_game.php  # k2_ops_process_completed_game + thin helpers
```

| Rule | Detail |
|------|--------|
| **No `dispatch.php` first** | Router only after checkpoint tests pass. |
| **No business logic in dispatcher** | Parse `CMD`, guards, call `k2_ops_*` only. |
| **No per-game `.sql` rebuild files** | Live post-game = incremental `mysqli` in PHP. Batch `.sql` under `staging-scripts/` / `scripts/ladder/sql/` = **full-history REP** only. |
| **Schema migrations** | `schema/migrations/` via prepare — not post-game. |

Prepare analogue: [`run_prepare.php`](../site/public_html/ops/run_prepare.php) + modules.

---

## 6. Databases

| DB | Use for post-game dev |
|----|------------------------|
| **`ko2unity_work`** | **Primary** — after full prepare; sim and PHP tests here. |
| **`ko2unity_baseline`** | **Never** write. |
| **`ko2unity_db`** (dev) | Optional **reference** for parity; browser at `http://ratingskickoff.test/`. |
| **`ko2unity_db`** for ops CLI | **Off-limits** by default (`allow_dev_db=1` only if intentional). |

Work browse: `http://work.ratingskickoff.test/` → work DB ([`LOCAL_DEV.md`](LOCAL_DEV.md)).

---

## 7. Daily loop

```text
1. prepare work     zero-derived (daily) or full prepare (migrations / refresh) — §work-db-prepare.md
2. implement slice  one phase in process_completed_game.php (see §10)
3. sim checkpoint   replay-to = loop process-one logic only; no batch finalize
4. parity           python -m scripts.work_prepare ab-post-game --limit N (§8.3)
5. repeat           next phase; add dispatch.php when Steve-facing surface needed
```

**Do not confuse:**

| Command | Role |
|---------|------|
| `run_prepare.php prepare` | Refresh → migrate → seed catalog → zero derived |
| `run_process_game.php` (planned) | Per-game derived writer |
| `ladder run` | Python batch replay — parity reference, not PHP loop template |
| `rebuild_website_derived_data_local.ps1` | Mode B REP — **not** per-game |

---

## 8. Checkpoint sims (short runs)

Full ~75k replay is for **late** confidence, not every edit.

### 8.1 Checkpoint types

| Checkpoint | Meaning |
|------------|---------|
| **`--limit N`** | First **N** games in `Date ASC, id ASC` order after prepare. |
| **`--until-game-id G`** | Every game with `(Date, id)` ≤ row **G** in that order. |

Name checkpoints explicitly in chat/commits: e.g. “through **id 74879**” vs “first **1000** games”.

### 8.2 Suggested checkpoints

| Stage | Checkpoint | Why |
|-------|------------|-----|
| Smoke | First **100** games | Guards, typos, commit-per-game |
| Elo slice | **1000** games or id **~5000** | `ratedresults` derived cols |
| Dev reference | **id 74879** | Matches dev DB last game (May 2026) — §9.2 |

### 8.3 Parity status

**Verification gate (preferred):** `python -m scripts.work_prepare ab-post-game --target local-work --limit N --phase p5` — zero-derived → PHP `replay-to` → snapshots → Python `ladder run` → diff layers 1–5. Optional `--full-prepare` for refresh/migrate day-start.

**No phase is “verified” until that command (or equivalent manual steps) reports 0 mismatches** on the shipped layers. Do not copy “0 mismatches” from old agent notes into commits or MEMORY without a fresh run.

**Quick self-check after PHP replay alone:** `python scripts/oneoff/verify_ratedresults_derived_rows.py --target sandbox --limit N` — internal consistency from stored `RatingA`/`RatingB` + goals; **not** a substitute for `ab-post-game`.

---

## 9. Parity

### 9.1 Layers (only compare what you shipped)

| Level | Check | When |
|-------|--------|------|
| **0** | Ground truth: `idA`, `idB`, `Date`, `GoalsA`, `GoalsB` for `id ≤ G` | Always |
| **1** | `ratedresults` derived columns | Elo / outcome phase |
| **2** | `playertable` for players in those games | Career stats phase |
| **3** | `generalstatstable` id=1 (holders + incremental aggregates) | Server records phase |
| **4** | `player_period_games` + `player_peak_period_games` | P4 |
| **5** | `server_daily_activity`, `player_period_league`, `player_matchup_summary`, `server_period_game_totals`, `server_period_matchups` | P5 — not `league_period` / awards (periodic batch) |
| **6** | `player_milestones` | P6 — `ab-post-game --phase p6` |
| **7** | `player_play_streaks` (+ GST streak holders when shipped) | P7 |

`RecentAverageRating` is absent after SCH-016 — no playertable diff column for it.

### 9.2 Dev vs work gate (reference)

**Dev last game:** `ratedresults.id = **74879**` (last by `Date`, `id` on dev snapshot).

**Counts:** dev **~74,870** games; work snapshot **~75,204** — compare **`id ≤ 74879`** when using dev as reference.

**Ground truth through 74879:** ids/goals should match; **16** `Date` rows may differ by 1h at DST under `SET time_zone = '+00:00'` — id 74879 not affected.

**Strongest test:** `ab-post-game` (§8.3) — zero-derived → PHP through checkpoint → snapshot → Python `run` (resets, then replays same N) → diff layer 1. No second full prepare required. Dev not required.

### 9.4 `ab-post-game` orchestrator

| Flag | Effect |
|------|--------|
| (default) | `zero-derived` on work |
| `--full-prepare` | refresh → migrate → seed → zero (day-start) |
| `--skip-prepare` | assume day-zero already |
| `--limit N` / `--until-game-id G` | checkpoint size |
| `--phase p1` / `--layers 1` | diff scope (extend when P2+ ships) |
| `--skip-ground-parity` | skip prepare parity after zero |
| `--skip-sanity` | skip `verify_ratedresults_derived_rows.py` |
| `--keep-snapshot` | leave `parity_ab_ratedresults_php` on work DB |

Snapshot is a work-DB table (not a repo file). PHP via Laragon path auto-detect or `K2_PHP_BIN`.

### 9.3 Website read paths

HoF peak panels use **stored** tables only — no live `ratedresults` scan ([`peak_month_leaderboard_query.php`](../site/public_html/includes/peak_month_leaderboard_query.php)). After prepare, peak rows show **`-`** until period/peak sim fills storage. Do not use slow pages as the only parity gate.

---

## 10. Implementation phases (suggested order)

Follow contract processing order. Ship **one phase → checkpoint → parity** before the next.

| Phase | Scope | Notes |
|-------|--------|--------|
| **P0** | Bootstrap + load game row + guards + dev runner skeleton | §5 |
| **P1** | `ratedresults` Elo + outcome derived cols | Contract + `ratings_cpp.txt` Elo block; parity vs `elo.py` / `outcome.py` |
| **P2** | `playertable` career counters, rating, extremes, streaks | Contract § career peak/nadir + personal `>`; parity vs `player_state.py` (contract-aligned Jun 2026) |
| **P3** | `generalstatstable` — **incremental** aggregates + strict `>` holders | Contract + `records-post-game-exception.md`; parity vs `server_records.py`; aggregates **increment**, do not rescan |
| **P4** | `player_period_games` + `player_peak_period_games` | contract §§ |
| **P5** | `server_daily_activity`, `player_period_league`, `player_matchup_summary`, `server_period_game_totals`, `server_period_matchups` | Shipped; PHP `post_game_period_aggregates.php`; Python `period_aggregates.py` rebuild (processed rows only) |
| **P6** | `player_milestones` (incremental, rated-game only) | **Shipped** — `post_game_milestones.php`; §10.1 for out-of-scope keys. Crossing-game chrono on **this game**; `play_streak_100` via `player_play_streaks.php`. **Not** `perfect_day` / `nightmare_day` / `entered_arena`. |
| **P7** | `player_play_streaks` row + HoF | Table updates ship with P6 milestone hook; full P7 parity TBD |

Full checklist: [`website-data-contract.md`](website-data-contract.md) § Post-game derived-data behavior.

---

## 11. P3 sketch — `generalstatstable` (when you reach it)

Per game, after `ratedresults` + `playertable` for A/B are updated in the **same transaction**:

1. **Load** `generalstatstable` row `id=1`.
2. **Aggregates:** add this game’s `SumOfGoals`, draw flag, DD/CS flags, `GamesPlayed += 1`, recompute ratios from stored numerators — **no full-table scan**.
3. **Holders:** apply `k2_post_game_update_server_records_after_game` from current `playertable` state for A/B; strict `>` only ([`records-post-game-exception.md`](coordination/records-post-game-exception.md)).
4. **Write** patch back to id=1.

Reference (batch end-of-replay only): `scripts/ladder/generalstats.py`, `server_records.py`.

---

## 12. Connection habits

Every post-game connection (PHP or parity SQL):

```sql
SET time_zone = '+00:00';
```

Match [`website-data-contract.md`](website-data-contract.md) and prepare bootstrap.

---

## 13. Explicit non-goals (this playbook)

| Item | Where tracked |
|------|----------------|
| Steve prod `dispatch.php` wiring | [`ladder-ops-platform.md`](ladder-ops-platform.md) §2 |
| Mode C timeline sim | [`work-db-prepare.md`](work-db-prepare.md) §5 |
| Prod C++ cutover | [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md) |
| Replacing Python prepare | [`run_prepare.php`](../site/public_html/ops/run_prepare.php) already canonical |

---

## 14. Changelog

| When | What |
|------|------|
| 2026-06 | **P5** — period aggregates per game (`server_daily_activity`, league slices, matchups, server period totals); layer 5 parity; Python `period_aggregates.py`; PHP treats missing `playertable` rows like Python `setdefault` (1600) for games-only IDs. |
| 2026-06 | **P4** — `player_period_games` + `player_peak_period_games` per game; layer 4 parity; Python `period_activity.py` rebuild from processed rows. |
| 2026-06 | **P3** — incremental `generalstatstable` id=1; `ab-post-game --phase p3`; Python `generalstats.py` counts `NewRatingA IS NOT NULL` only. |
| 2026-06 | **P2** — full `playertable` career per game; layer 2 parity. |
| 2026-06 | **P0/P1** — `run_process_game.php`, per-game commit, ratedresults derived + `playertable.Rating`; `status-ratedresults` coverage verb. |
| 2026-06 | **§3.1** — stored facilitators: flag slow per-game queries; consider SCH + contract when indexes are not enough. |
| 2026-06 | **§0** — authority rank (contract > C++ inspiration > Python parity) and explicit per-game performance requirement. |
| 2026-06 | **SCH-016** — DROP `playertable.RecentAverageRating` on prepare migrate; parity `recent_average_rating_column_absent`. |
| 2026-06 | **Careful rewrite** after revert of first PHP attempt: simul = per-game commit; `ratedresults` policy; `RecentAverageRating` retired; no unverified parity claims. |
| 2026-06 | *(removed)* First playbook + P1–P2 PHP — reverted; do not treat as shipped. |
