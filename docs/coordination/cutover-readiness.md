# Cutover readiness — ops vocabulary (Jun 2026)

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **what is done vs what is left** for production. Replaces misleading readings of **“Pending on prod”** in old registers as “work still to do in the repo.”

**One-line rule:** Prep is done on `kooldb1` via ops simul; live prod is Steve’s scheduled cutover; batch `*_rebuild.sql` and `rebuild_website_derived_data_local.ps1` are legacy repair on `ko2unity_db` only — not tasks, not prod.

**Steve runbook (live execution only):** [`site/public_html/ops/docs/post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) — if he opens `ops/README.md` on the server, the top section sends him here.  
**Simul definition of done:** [`ops-simul-runbook.md`](ops-simul-runbook.md) · **Work hygiene (agents):** [`work-db-prepare.md`](../work-db-prepare.md) §1.5  
**Schema DDL:** [`schema-register.md`](schema-register.md) · **Historical batch era:** [`../archive/replay-register-2026-05.md`](../archive/replay-register-2026-05.md)

---

## Three layers (use this, not “pending = incomplete”)

| Layer | Question | Status (Jun 2026) |
|-------|----------|-------------------|
| **A — Repo / ops package** | Migrations in git? PHP post-game P0–P7? `dispatch.php`? | **Done** — `site/public_html/ops/sql/migrations/` + `run_process_game.php` |
| **B — Prod-shaped proof** | migrate → seed → zero → **simul** → **verify** on a prod copy? | **Done on `kooldb1`** — Steve simul sign-off; `run_verify_ops_sim` 0 fail |
| **C — Live prod execution** | Same verbs on **live** DB + wire dispatch/cron? | **Not yet** — deliberate **go-live** when agreed; **not** prep debt |

**Agents:** Do **not** tell Dagh to “finish REP-003” or “apply pending schema on staging.” Layer **A+B** are complete for the cutover set. Layer **C** is Steve’s scheduled cutover.

---

## Database names (do not confuse)

| DB | Role | Agent rule |
|----|------|------------|
| **`kooldb`** | May 2026 single staging DB; batch `*_rebuild.sql` era | **Frozen — historical logs only. No tasks.** |
| **`kooldb1`** | Staging **work** DB (mirrors `ko2unity_work`) | **Forward proof environment** — prepare + simul + verify |
| **`kooldb2`** | Staging pristine clone source | Never migrate/simul — clone source only |
| **Live prod** | Steve-managed | Cutover **execution** when scheduled |

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

## Live prod (layer C) — when Steve is ready

Single checklist (details in post-dagh-live-story):

1. WinSCP sync `public_html/` incl. `ops/`
2. `work-targets.ini` on server
3. On chosen DB (prod copy first, then live): **migrate-work → seed-catalog → zero-derived**
4. **run_ops_sim.php run** → **run_verify_ops_sim.php**
5. Wire **ProcessCompletedGame** after each ground insert; **FinalizeUtcDay** ~00:00:01 UTC
6. Retire legacy **C++ derived** post-game on live
7. Mark **Live prod executed** in [`schema-register.md`](schema-register.md) / [`feature-log.md`](feature-log.md)

---

## Derived data authority

| Happy path | Tool |
|------------|------|
| Fill all website aggregates from history | **`php ops/run_ops_sim.php run`** (timeline: per-game + UTC day ticks) |
| Gate after simul | **`php ops/run_verify_ops_sim.php`** |
| Day zero before simul | **`migrate-work`**, **`seed-catalog`**, **`zero-derived`** |

| Deprecated for cutover / agent tasks | Tool |
|--------------------------------------|------|
| Batch table rebuilds | `scripts/ladder/sql/archive/batch-2026-05/*_rebuild.sql` (repair only) |
| Dev-only chain | `scripts/rebuild_website_derived_data_local.ps1` |
| PHP vs Python A/B (dev era) | `python -m scripts.work_prepare ab-post-game` — see [`post-game-php-development.md`](../post-game-php-development.md) §9 (archived tooling) |
| Day-close surgical SQL (frozen `kooldb`) | `scripts/ladder/sql/archive/one-off-2026-06/player_milestones_fix_day_close.sql` — superseded by `FinalizeUtcDay` |

**Elo / core ladder** (`playertable` ratings from all games): still documented in [`replay-v1-scope-and-reset.md`](../replay-v1-scope-and-reset.md) — distinct from website aggregate simul; ops simul owns website derived tables at cutover.

---

## Feature snapshot (prod-ready vs live executed)

| Feature | Repo + ops (A) | Proven `kooldb1` (B) | Live prod (C) |
|---------|----------------|----------------------|---------------|
| PHP ops post-game P0–P7 | Done | Done (simul) | Not executed |
| Activity wing (participation + in-a-row) | Done | **Proven** | Not executed |
| League honours `ranked9` | Done | **Proven** | Not executed |
| Play streaks UI + DB | Done | **Proven** | Not executed |
| Status leagues Phase 1 | Done | **Proven** | Not executed |
| Milestones v0 + catalog | Done | **Proven** | Not executed |
| `ratedresults` player indexes | In migration 001 | Via migrate-work on work | Not executed |

Optional product backlog ( **not** cutover blockers): Status Phase 1.5 polish in [`status-period-competitions-wip.md`](../status-period-competitions-wip.md) only — **no** agent bootstrap handoff.

---

## Related registers (updated Jun 2026)

| Doc | Role |
|-----|------|
| [`schema-register.md`](schema-register.md) | SCH migrations: **in ops package** / **`kooldb1`** / **live executed** |
| [`replay-register.md`](replay-register.md) | **Stub** → historical batch REP + run log |
| [`feature-log.md`](feature-log.md) | Features: **kooldb1 proof** vs **live cutover** |
| [`post-game-register.md`](post-game-register.md) | PHP ops cutover pointer |

---

*When layer C completes for live prod, update this doc’s table and Steve runbook with date + target DB name.*
