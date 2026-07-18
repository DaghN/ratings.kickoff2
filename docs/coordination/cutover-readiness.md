# Cutover readiness — ops vocabulary (Jun 2026)

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **what is done vs what is left** for production. Replaces misleading readings of **“Pending on prod”** in old registers as “work still to do in the repo.”

**One-line rule:** Prep is done on `kooldb1` via ops simul; **live PHP ops cutover executed 2026-07-18** (Steve hosting + ground insert; derived writers from this repo). Retired dev batch/replay CLIs are **not** tasks or prod — [`obsolete-dev-scripts-retirement-policy.md`](../obsolete-dev-scripts-retirement-policy.md).

**Steve runbook (live execution only):** [`site/public_html/ops/docs/post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) — if he opens `ops/README.md` on the server, the top section sends him here.  
**Simul definition of done:** [`ops-simul-runbook.md`](ops-simul-runbook.md) · **Work hygiene (agents):** [`work-db-prepare.md`](../work-db-prepare.md) §1.5  
**Schema DDL:** [`schema-register.md`](schema-register.md) · **Historical batch era:** [`../archive/replay-register-2026-05.md`](../archive/replay-register-2026-05.md)

---

## Three layers (use this, not “pending = incomplete”)

| Layer | Question | Status |
|-------|----------|--------|
| **A — Repo / ops package** | Migrations in git? PHP post-game P0–P7? `dispatch.php`? | **Done** — `site/public_html/ops/sql/migrations/` + `run_process_game.php` |
| **B — Prod-shaped proof** | migrate → seed → zero → **simul** → **verify** on a prod copy? | **Done on `kooldb1`** — Steve simul sign-off; `run_verify_ops_sim` 0 fail |
| **C — Live prod execution** | Same verbs on **live** DB + wire dispatch/cron? | **Done (2026-07-18)** — live games on PHP ops |

**Agents:** Do **not** tell Dagh to “finish REP-003” or “apply pending schema on staging.” Layers **A+B+C** are complete for the online ops cutover set.

---

## Database names (do not confuse)

| DB | Role | Agent rule |
|----|------|------------|
| **`kooldb`** | May 2026 single staging DB; batch `*_rebuild.sql` era | **Frozen — historical logs only. No tasks.** |
| **`kooldb1`** | Staging **work** DB (mirrors `ko2unity_work`) | **Forward proof environment** — prepare + simul + verify |
| **`kooldb2`** | Staging pristine clone source | Never migrate/simul — clone source only |
| **Live prod** | Steve-managed | **Done (2026-07-18)** — PHP ops live; C++ derived retired |

See [`database-copies-2026-06.md`](database-copies-2026-06.md).

---

## What proved on `kooldb1` (layer B)

Steve + Dagh (Jun 2026), prod-shaped path:

```text
migrate-work → seed-catalog → zero-derived → run_ops_sim.php → run_verify_ops_sim.php
```

| Area | Proof notes |
|------|-------------|
| **Ops simul** | Signed off; verify **0 fail / 0 warn** (74,865 processed, Jun 2026) |
| **Post-game PHP** | P0–P7 in `run_process_game.php`; live target `CMD=ProcessCompletedGame` + `FinalizeUtcDay` |
| **Activity wing** | SCH-022–025 — participation, play-streak month/year, reached_at oracle; verify PASS |
| **League honours** | `leaderboards/league-honours.php` — `player_league_totals` + slice totals; UI + data verified after simul |
| **Rated play streaks** | `leaderboards/streaks.php` + HoF on `hall-of-fame.php`; `player_play_streaks` day/week/month/year + GST |
| **Status leagues** | `player_period_league` + activity via `player_period_games` (Phase **1** shipped) |
| **Milestones** | Catalog **112**; `player_milestone_totals` + `holder_count` parity PASS; ~5657 game-sourced unlock rows |
| **Indexes (SCH-001)** | `idx_ratedresults_idA` / `idB` — part of **`migrate-work`** (migration `001_…`), not a separate manual prod prep step |

**Not the cutover recipe:** running batch `*_rebuild.sql` from `scripts/ladder/sql/archive/batch-2026-05/` on prod. **Repair only** if simul breaks mid-history.

---

## Live prod (layer C) — Done (2026-07-18)

Executed checklist (details in post-dagh-live-story — keep as historical / future-packet shape):

1. WinSCP sync `public_html/` incl. `ops/`
2. `work-targets.ini` on server
3. On chosen DB (prod copy first, then live): **migrate-work → seed-catalog → zero-derived**
4. **run_ops_sim.php run** → **run_verify_ops_sim.php**
5. Wire **ProcessCompletedGame** after each ground insert; **FinalizeUtcDay** ~00:00:01 UTC
6. Retire legacy **C++ derived** post-game on live — **done**
7. Mark **Live prod executed** in [`schema-register.md`](schema-register.md) / [`feature-log.md`](feature-log.md) — this sweep

---

## Derived data authority

| Happy path | Tool |
|------------|------|
| Fill all website aggregates from history | **`php ops/run_ops_sim.php run`** (timeline: per-game + UTC day ticks) |
| Gate after simul | **`php ops/run_verify_ops_sim.php`** |
| Day zero before simul | **`migrate-work`**, **`seed-catalog`**, **`zero-derived`** |

| Deprecated for cutover / agent tasks | Tool |
|--------------------------------------|------|
| Batch table rebuilds | `docs/archive/batch-rebuild-sql-2026-05/*_rebuild.sql` (archived repair only) |
| Dev-only chain | Retired — [`obsolete-dev-scripts-retirement-policy.md`](../obsolete-dev-scripts-retirement-policy.md) |
| PHP vs Python A/B (dev era) | Archived `work_prepare` oracle — see [`post-game-php-development.md`](../post-game-php-development.md) §9 |
| Day-close surgical SQL (frozen `kooldb`) | `docs/archive/batch-rebuild-sql-one-off-2026-06/player_milestones_fix_day_close.sql` — superseded by `FinalizeUtcDay` |

**Elo / core ladder** (`playertable` ratings from all games): still documented in [`replay-v1-scope-and-reset.md`](../replay-v1-scope-and-reset.md) — distinct from website aggregate simul; live + ops simul own website derived tables via PHP ops.

---

## Feature snapshot (prod-ready vs live executed)

| Feature | Repo + ops (A) | Proven `kooldb1` (B) | Live prod (C) |
|---------|----------------|----------------------|---------------|
| PHP ops post-game P0–P7 | Done | Done (simul) | **Done (2026-07-18)** |
| Activity wing (participation + in-a-row) | Done | **Proven** | **Done (2026-07-18)** |
| League honours `ranked9` | Done | **Proven** | **Done (2026-07-18)** |
| Play streaks UI + DB | Done | **Proven** | **Done (2026-07-18)** |
| Status leagues | Done | **Proven** | **Done (2026-07-18)** |
| Milestones v0 + catalog | Done | **Proven** | **Done (2026-07-18)** |
| `ratedresults` player indexes | In migration 001 | Via migrate-work on work | **Done (2026-07-18)** |

---

## Related registers (updated Jun 2026)

| Doc | Role |
|-----|------|
| [`schema-register.md`](schema-register.md) | SCH migrations: **in ops package** / **`kooldb1`** / **live executed** |
| [`replay-register.md`](replay-register.md) | **Stub** → historical batch REP + run log |
| [`feature-log.md`](feature-log.md) | Features: **kooldb1 proof** vs **live cutover** |
| [`post-game-register.md`](post-game-register.md) | PHP ops cutover pointer |

---

*Layer C completed 2026-07-18 (live PHP ops). Keep this doc as the A/B/C vocabulary; do not re-open “scheduled cutover” language for online ops.*
