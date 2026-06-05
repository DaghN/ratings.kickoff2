# Post-Dagh live database — full story (Steve)

**On server:** `public_html/ops/docs/post-dagh-live-story.md` (Dagh syncs `site/public_html/` via WinSCP; **you** run everything on the host).  
**Status:** Jun 2026 — batch simul path proven; **next** = bootstrap on a **prod copy**, then live-shaped dispatch.

**Short live commands:** [`steve-live-ops.md`](steve-live-ops.md) · **Dispatcher detail:** [`ops-dispatch.md`](ops-dispatch.md)

---

## Who does what

| | Dagh | Steve |
|---|------|-------|
| PHP / ops code | Uploads `public_html/` (incl. `ops/`) | Runs CLI on server |
| MySQL | — | Prod copy, migrations, simul, game server, cron, site DB config |
| `work-targets.ini` | Ships `.example` in repo | Copy → `ops/config/work-targets.ini`, fill credentials |

This doc assumes **you already have a prod copy** on the server (how you clone or refresh it is up to you). It does **not** use the **`prepare`** verb or **`refresh-work`** — those exist for a two-database “clone baseline → work” workflow we are **not** prescribing here.

---

## Rough outline (read this first)

Starting point: **one prod copy** ready for PHP ladder work.

| Step | What |
|------|------|
| **1. Migrate** | Apply `ops/sql/migrations/` |
| **2. Seed catalog** | Milestone definition rows (`seed-catalog`) |
| **3. Zero derived** | Clear PHP-owned derived state; keep ground rows; **lobby seed** runs at end of this step |
| **4. Simul** | Full history replay + UTC midnight ticks (`run_ops_sim.php`) |
| **5. Go live** | Ground inserts + `dispatch.php` — **no** C++ post-game, **no** rating decay |

**Live, every day:**

| When | You (ground) | Then PHP |
|------|----------------|----------|
| Player registers | Insert **`playertable`** row | `CMD=ProcessPlayerRegistered` |
| Rated match saved | Insert **`ratedresults`** (columns in §Rated game insert) | `CMD=ProcessCompletedGame` |
| ~**00:00:01 UTC** | *(nothing)* | `CMD=FinalizeUtcDay` |

**Not in live ops:** C++ post-game on the same row · rating decay/fade · `FinalizeLeagueDue` alone (use **`FinalizeUtcDay`**).

All commands below: **`cd public_html`**, then `php ops/…`, with your usual **`target=…`** (see `ops/config/work-targets.ini`).

---

## What we have already proved

We ran migrate → seed → zero → **prod-shaped simul** → verify on a staging copy and signed off (0 fail / 0 warn). That shows PHP can rebuild derived data from ground truth.

**Your next job:** Same bootstrap steps on **your** prod copy (without `refresh-work`), then wire **live-shaped** ops (one game at a time).

---

## Phase 1 — Bootstrap on the prod copy (batch)

Run these **separate** verbs — **do not** run `php ops/run_prepare.php prepare` (that includes refresh).

```bash
cd public_html

php ops/run_prepare.php migrate-work --target YOUR_TARGET
php ops/run_prepare.php seed-catalog --target YOUR_TARGET
php ops/run_prepare.php zero-derived --target YOUR_TARGET
```

| Verb | Purpose |
|------|---------|
| **`migrate-work`** | New tables/columns/indexes under `ops/sql/migrations/` |
| **`seed-catalog`** | 112 rows from `ops/data/milestones_definitions_seed.json` |
| **`zero-derived`** | Day-zero derived columns/tables; **ground** `ratedresults` / `playertable` facts kept; **`entered_arena`** seeded from `JoinDate` at end |

Then prod-shaped simul (**not** `replay-to` alone):

```bash
php ops/run_ops_sim.php run --target YOUR_TARGET
php ops/run_verify_ops_sim.php --target YOUR_TARGET
```

Optional stop: `run_ops_sim.php run --target YOUR_TARGET --until-game-id N`

**One-time server setup:** `ops/config/work-targets.ini.example` → `ops/config/work-targets.ini` (gitignored; you maintain credentials and `target` profile).

---

## Phase 2 — Go live (live-shaped)

After simul + verify look good, point the **game server** and **website** at the same database you used above.

### 2.1 New registration

| Step | Action |
|------|--------|
| 1 | Commit new row in **`playertable`** (your existing path). |
| 2 | Lobby milestone (PHP only): |

```bash
php ops/dispatch.php CMD=ProcessPlayerRegistered player_id=PLAYER_ID target=YOUR_TARGET
```

Players already on the DB before cutover got **`entered_arena`** during **`zero-derived`** lobby seed. Use this CMD for **new** registrations only.

### 2.2 Each rated match

| Step | Action |
|------|--------|
| 1 | `INSERT` into **`ratedresults`** — **only** the ground columns in §Rated game insert. |
| 2 | **Do not** run C++ post-game on that row. |
| 3 | Derived update: |

```bash
php ops/dispatch.php CMD=ProcessCompletedGame game_id=GAME_ID target=YOUR_TARGET
```

Use the new row’s **`id`** as `game_id`. PHP reads the row; do not pass scores on the command line.

**Exit codes:** `0` OK (committed, or skipped bad row — see log) · `1` failed — retry after fix if `NewRatingA` still NULL · `2` already processed (duplicate dispatch) · `64` bad CLI.

### 2.3 UTC midnight (cron)

Once per calendar day, about **00:00:01 UTC**:

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=YOUR_TARGET
```

League finalize, league event milestones, `perfect_day` / `nightmare_day` — one call.

### 2.4 Turn off for this test

| Stop | Use instead |
|------|-------------|
| C++ post-game after `ratedresults` insert | `CMD=ProcessCompletedGame` |
| Old partial league cron | `CMD=FinalizeUtcDay` |
| Rating decay / fade | *(none — retired)* |

---

## Rated game insert — ground columns only

PHP treats **`NewRatingA IS NOT NULL`** as “already processed”. For live, leave all derived columns **out of the INSERT** so they stay NULL until dispatch runs.

### Include in `INSERT` (required)

| Column | Notes |
|--------|--------|
| **`Date`** | Match time. Use **`SET time_zone = '+00:00'`** on the connection before period logic (same as today). |
| **`idA`**, **`idB`** | Valid player ids, both **> 0**, not equal. |
| **`NameA`**, **`NameB`** | Player names at game time — **required** (site and `game.php` expect them). |
| **`GoalsA`**, **`GoalsB`** | Integers, not NULL. Outcome comes from goals. |

Example shape (column list only — bind values your way):

```sql
INSERT INTO ratedresults (Date, idA, NameA, idB, NameB, GoalsA, GoalsB)
VALUES (?, ?, ?, ?, ?, ?, ?);
```

### Do not include in `INSERT` (PHP derives)

Omit these from the column list entirely — do not write C++-era values at insert time:

| Column |
|--------|
| `RatingA`, `RatingB`, `RatingDifference` |
| `ExpectedScoreA`, `ExpectedScoreB`, `ActualScore` |
| `AdjustmentA`, `AdjustmentB`, `NewRatingA`, `NewRatingB` |
| `SumOfGoals`, `GoalDifference` |
| `HomeWin`, `Draw`, `AwayWin` |
| `DDPlayerA`, `DDPlayerB`, `CSPlayerA`, `CSPlayerB` |
| `WinnerID` |

Pre-game Elo for processing comes from **`playertable.Rating`** at dispatch time; PHP writes the row’s rating columns when it commits.

Schema reference (git repo): `docs/ratedresults-schema.md`

### Rows PHP will skip (no commit)

| Reason | Exit | Cause |
|--------|------|--------|
| `already_processed` | **2** | `NewRatingA` already set — safe duplicate dispatch |
| `invalid_idA_idB` | **0** | Missing or non-positive ids |
| `idA_equals_idB` | **0** | Same player both sides |
| `goals_missing` | **0** | NULL goals |

### What one successful `ProcessCompletedGame` updates

Among others: `ratedresults` derived columns, both players’ `playertable`, `generalstatstable`, period tables, game milestones. League **awards** and day-close milestones need **`FinalizeUtcDay`**, not extra per-game calls.

---

## Calling dispatch from the game server

- **Working directory:** `public_html/`, or absolute paths to `php` and `ops/dispatch.php`.
- **Arguments:** separate tokens — `CMD=ProcessCompletedGame` `game_id=57216` `target=YOUR_TARGET` (not one comma-separated string).
- Read the process **exit code**, not only stdout (`[dispatch]` log prefix).
- **`ops/` is CLI only** — not HTTP.

---

## Suggested order

| # | Task |
|---|------|
| 1 | WinSCP / deploy: `public_html/ops/` from Dagh; you maintain `work-targets.ini` |
| 2 | Prod copy in place (your process) |
| 3 | `migrate-work` → `seed-catalog` → `zero-derived` |
| 4 | `run_ops_sim.php run` → `run_verify_ops_sim.php` |
| 5 | Game server + site on that DB |
| 6 | Registration → `ProcessPlayerRegistered` |
| 7 | Each rated game → ground `INSERT` → `ProcessCompletedGame` (no C++) |
| 8 | Cron `FinalizeUtcDay` ~00:00:01 UTC |
| 9 | Smoke: one registration, one rated game, one UTC day tick |

---

## Related docs

| Doc | Use |
|-----|-----|
| [`steve-live-ops.md`](steve-live-ops.md) | Three live CMDs, exit codes |
| [`ops-dispatch.md`](ops-dispatch.md) | Failure/retry, legacy CMDs |
| [`ops-simul-runbook.md`](../../../../docs/coordination/ops-simul-runbook.md) | Why `run_ops_sim` ≠ `replay-to` |
| [`ops/README.md`](../README.md) | Ops folder map |

---

*Jun 2026 — prod-copy path: migrate / seed / zero / simul / live; no `prepare` / `refresh-work` in this runbook.*
