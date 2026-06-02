# Post-game PHP — development playbook (local / work)

**Status:** **Jun 2026** — Playbook only. **No post-game PHP modules in repo yet** (reverted after first attempt). Implementation not started; parity claims in old drafts are void until re-run locally.
**Audience:** Dagh, Cursor agents.

**This doc is how we build and test.** It does **not** replace:

| Doc | Role |
|-----|------|
| [`website-data-contract.md`](website-data-contract.md) | **What** each derived table/column must do (behaviour authority) |
| [`ladder-ops-platform.md`](ladder-ops-platform.md) | Steve boundary, `dispatch.php`, `ops/` layout, prod target |
| [`work-db-prepare.md`](work-db-prepare.md) | Prepare, zero derived, simul modes A/B/C |
| [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) | Core ladder column lists for reset/replay |

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
| **C — Timeline** | Later — league finalize, day-close milestones, etc. |

Python Mode A today still batch-finalizes some ladder fields at end; treat Python as **oracle for checkpoints**, not as the PHP loop structure.

---

## 3. `ratedresults` access policy

The site avoids **full-history scans on read paths** (profile indexes, stored peak tables, Status aggregates). Post-game must not reintroduce that pattern **per game**.

| Allowed | Forbidden in per-game post-game |
|---------|----------------------------------|
| Read **this** game row (ground + already-derived cols you need) | `SELECT COUNT(*) / SUM(…) FROM ratedresults` with **no** `id` / pair / player bound |
| **Bounded** history: e.g. `EXISTS (… prior pair game … AND id < ?)` with index-friendly predicates | Rescan entire table each game to refresh GST totals |
| Read **two** `playertable` rows (A, B) at start of game | Recompute `RecentAverageRating` from career history (column **retired** — see §4) |
| Increment stored counters on `generalstatstable` id=1 | End-of-run `finalize_*` over all games in PHP sim |

**Contract note:** “Sums from full corpus” on GST aggregate columns describes the **meaning** of the number, not the implementation. Live and PHP sim should **maintain running totals** on `generalstatstable` (and derived cols on the game row you just wrote).

**Indexes:** Use existing `idx_ratedresults_idA`, `idx_ratedresults_idB`, `idx_ratedresults_date` for any bounded history probe.

---

## 4. Retired / out-of-scope columns

| Item | Status |
|------|--------|
| **`playertable.RecentAverageRating`** | **Retired on website** — do not compute, display, or parity-check in PHP post-game. Prepare/zero-derived should NULL it (migration tweak planned; not in prepare yet). Python replay may still write it until ladder script is trimmed — PHP ignores. |
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
1. prepare work     php site/public_html/ops/run_prepare.php prepare --target local-work
2. implement slice  one phase in process_completed_game.php (see §10)
3. sim checkpoint   replay-to = loop process-one logic only; no batch finalize
4. parity           ground truth gate, then derived diff for shipped phase only (§9)
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

**No phase is “verified” until Dagh runs work-only A/B locally** (prepare → PHP sim → snapshot → prepare → Python `run --limit N` → diff). Do not copy “0 mismatches” from old agent notes into commits or MEMORY without a fresh run.

---

## 9. Parity

### 9.1 Layers (only compare what you shipped)

| Level | Check | When |
|-------|--------|------|
| **0** | Ground truth: `idA`, `idB`, `Date`, `GoalsA`, `GoalsB` for `id ≤ G` | Always |
| **1** | `ratedresults` derived columns | Elo / outcome phase |
| **2** | `playertable` for players in those games | Career stats phase |
| **3** | `generalstatstable` id=1 (holders + incremental aggregates) | Server records phase |
| **4** | Aggregates / `player_milestones` | When PHP implements them incrementally |

Exclude `RecentAverageRating` from playertable diffs.

### 9.2 Dev vs work gate (reference)

**Dev last game:** `ratedresults.id = **74879**` (last by `Date`, `id` on dev snapshot).

**Counts:** dev **~74,870** games; work snapshot **~75,204** — compare **`id ≤ 74879`** when using dev as reference.

**Ground truth through 74879:** ids/goals should match; **16** `Date` rows may differ by 1h at DST under `SET time_zone = '+00:00'` — id 74879 not affected.

**Strongest test:** work-only A/B (same DB, prepare → PHP through G → snapshot → prepare → Python through G → diff). Dev not required.

### 9.3 Website read paths

HoF peak panels use **stored** tables only — no live `ratedresults` scan ([`peak_month_leaderboard_query.php`](../site/public_html/includes/peak_month_leaderboard_query.php)). After prepare, peak rows show **`-`** until period/peak sim fills storage. Do not use slow pages as the only parity gate.

---

## 10. Implementation phases (suggested order)

Follow contract processing order. Ship **one phase → checkpoint → parity** before the next.

| Phase | Scope | Notes |
|-------|--------|--------|
| **P0** | Bootstrap + load game row + guards + dev runner skeleton | §5 |
| **P1** | `ratedresults` Elo + outcome derived cols | Mirror `scripts/ladder/elo.py`, `outcome.py` |
| **P2** | `playertable` career counters, rating, extremes, streaks | Mirror `player_state.py`; personal extremes still **`>=`** in Python until contract `>` cutover — document if diverging |
| **P3** | `generalstatstable` — **incremental** aggregates + strict `>` holders | Mirror `server_records.py`; aggregates **increment**, do not rescan |
| **P4** | `player_period_games` + `player_peak_period_games` | contract §§ |
| **P5** | `server_daily_activity`, period totals, matchups, league slices | |
| **P6** | `player_milestones` (incremental) | |
| **P7** | `player_play_streaks` | SCH-014 / contract |

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
| 2026-06 | **Careful rewrite** after revert of first PHP attempt: simul = per-game commit; `ratedresults` policy; `RecentAverageRating` retired; no unverified parity claims. |
| 2026-06 | *(removed)* First playbook + P1–P2 PHP — reverted; do not treat as shipped. |
