# Ladder operations platform — design (Jun 2026)

**Status:** **`ops/` dev runners live** (prepare, post-game, league finalize, timeline sim — Jun 2026). **`dispatch.php` not in repo yet** — Steve `CMD=` router is a separate slice.  
**Audience:** Dagh, Steve, Cursor agents.

**Related:** [`work-db-prepare.md`](work-db-prepare.md) (prepare, zero derived, simul modes) · [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) (DB names) · [`website-data-contract.md`](website-data-contract.md) (derived rules at cutover) · [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) (core ladder column manifest)

---

## 1. Vocabulary

| Term | Meaning |
|------|---------|
| **Ground truth** | What happened in the match: who played, score, time; later richer in-match events. Persisted first (Steve insert into `ratedresults`). |
| **Derived truth** | Everything computed from facts + prior DB state: Elo, `WinnerID`, milestones, aggregates, league honours, `playertable` career fields, etc. |
| **Post-game** | Derived updates for **one** rated game (`game_id`). |
| **Periodic** | Derived updates driven by **time/calendar** (rating fade, league finalize, …) — not one new game. |
| **Refresh work** | Clone pristine baseline → work DB (restores prod ground + prod-derived in core tables). |
| **Migrate work** | Apply project `schema/migrations/` on work only. |
| **Zero derived** | Clear derived to day-zero pre-game; keep ground truth. Not the same as refresh work. |
| **Simul** | Re-run derived writers over history (game-only, batch rebuild, or timeline — see [`work-db-prepare.md`](work-db-prepare.md) §5). |
| **Ops** | Server-side runnable tooling under `site/public_html/ops/` — not the public website. |

**Elo is derived truth**, same class as milestones — not a separate “Steve core” category.

---

## 2. Boundary with Steve (agreed Jun 2026)

| Side | Responsibility |
|------|----------------|
| **Steve** | Persist **ground truth** after a rated game (insert into `ratedresults`; later possibly more event columns). Then **invoke** our PHP entry point. |
| **Dagh (repo)** | **Derived truth**: dispatcher, modules, SQL mirrors, sim/replay orchestration, website contract. |

### Post-game runtime authority (read this when docs disagree)

| Question | Canonical answer |
|----------|------------------|
| **What** must post-game compute? | [`website-data-contract.md`](website-data-contract.md) post-game §§ — rules for each table/column. |
| **Who invokes** derived updates after ground insert? | This doc — Steve calls PHP `ops/dispatch.php` `CMD=ProcessCompletedGame` with `game_id` (planned; not in repo yet). |
| **Prod today** | Live games still use **Steve’s C++** derived post-game until cutover. |
| **Prod target** | **PHP ops** (`ProcessCompletedGame`) implements contract rules per game; **C++ derived post-game is retired** (not extended with M1–M7). |

Registers ([`prod-coordination.md`](prod-coordination.md), [`coordination/post-game-register.md`](coordination/post-game-register.md)) track cutover status — they do **not** override the split above.

**Agreed call shape (Steve proposed, aligned with ground/derived):**

```text
php …/ops/dispatch.php   CMD=ProcessCompletedGame   game_id=57216
```

- **No** duplicate player ids / scores in the call — module reads facts from DB.
- Other commands later: periodic jobs, batch sim, schema apply (mostly Dagh/CLI; same file family).

**Steve server check (Jun 2026):** PHP/SQL workloads Dagh sent ran fine — one CPU core maxed on heavy jobs; storage, memory, network healthy. PHP is an acceptable host for derived processing on the server.

**Still confirm when convenient:** exact invocation (CLI path vs internal HTTP), which PHP config the **staging website** vhost uses vs `kooldb1`/`kooldb2`.

---

## 3. Architecture — dispatcher + modules

### 3.1 Thin dispatcher (interface + light orchestration)

**File (planned):** `site/public_html/ops/dispatch.php`

| Job | Yes | No |
|-----|-----|-----|
| Parse `CMD` and parameters | ✓ | |
| Guards (allowed DB, required `game_id`, never touch baseline DB, …) | ✓ | |
| Route to one module / runner | ✓ | |
| Elo, milestones, SQL migrations inline | | ✓ |

### 3.2 Modules (outside dispatcher)

Business logic lives in `ops/modules/` (and shared includes as needed):

| Module role | Example `CMD` | Typical caller |
|-------------|-----------------|----------------|
| Per-game derived | `ProcessCompletedGame` | **Steve** (each live game on prod path) |
| Register / lobby | `ProcessPlayerRegistered` | **Steve** after app registration (`player_id` only) → `entered_arena` from `playertable.JoinDate` |
| Periodic | `RatingFade`, `FinalizeLeaguePeriod`, … | Steve scheduler / exe |
| Schema on work DB | `ApplySchema` | Dagh / Steve staging |
| Refresh work from baseline | `RefreshWorkFromBaseline` (script today: `reset_local_work_db.ps1`) | Dagh |
| Prepare work (refresh + migrate + zero derived) | `PrepareWork` (planned) | Dagh |
| Chronological sim | `ReplayChronological` | Dagh (prep / cutover) |
| Parity (optional) | `ParityCheck` | Dagh |

### 3.3 One core function, two outer shells

```text
processCompletedGame(int $gameId)   // shared — all derived steps for one game

Outer A — live (Steve):
  one PHP process per game → processCompletedGame($id) once → exit

Outer B — sim (Dagh):
  one PHP process → foreach game in ORDER BY Date, id → processCompletedGame($id)
```

**Equivalence:** Game *N* after sim should match prod after game *N* if starting derived state matches and periodic side effects are modelled.

**Not equivalent operationally:** 75k separate `php` OS spawns vs one long loop — sim uses **one process, many function calls**.

**Python:** Optional for **parity comparison** only until PHP sim is trusted — not a second authority for prod.

### 3.4 Physical DB split (deferred)

Logical split (fact vs derived columns) is enough for v1. Optional later: `ratedresults_derived` table or facts-only insert — not blocking dispatcher work.

---

## 4. Databases

See **[`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md)** for full tables.

| Environment | Work / experiments | Pristine / reset copy | Browser / daily dev |
|-------------|-------------------|---------------------|---------------------|
| **Local** | `ko2unity_work` | `ko2unity_baseline` | `ko2unity_db` at **`http://ratingskickoff.test/`** |
| **Local browser (work)** | — | — | **`http://work.ratingskickoff.test/`** → `ko2unity_work` (parallel; no config flip) |
| **Staging** | `kooldb1` (config1) | `kooldb2` (config2) | N/A — use local dev URLs |
| **Production** | live DB | — | — |

**Local browse:** Two hostnames, one PHP tree — see [`LOCAL_DEV.md`](../LOCAL_DEV.md) and [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) § Local dual website. **Do not** “cut over” by editing only `$database` in a shared config; that pattern is retired for local work.

**Rules:**

- Never migrate or replay on **`ko2unity_baseline`** / **`kooldb2`** (reset sources).
- **`ko2unity_work`** / **`kooldb1`**: migrate work, prepare, simul, post-game tests — [`work-db-prepare.md`](work-db-prepare.md).
- Prod dump import: sanitized dump only — [`data/README.md`](../data/README.md).

---

## 5. Deploy — one WinSCP sync

**Habit:** Everything Steve needs on staging lives under **`site/public_html/`** → sync to `ratings.kickoff2.com` `public_html/`.

| Path | Contents |
|------|----------|
| `site/public_html/` (root) | Website — pages, `api/`, assets |
| `site/public_html/ops/` | **Operations** — dispatcher, modules, SQL mirrors |
| `site/public_html/staging-scripts/` | **Legacy** — migrate into `ops/` over time |

**Schema:**

- **Canonical:** `schema/migrations/` (repo root) — registers, `schema/apply_local.ps1` on dev/work.
- **Staging mirror:** `ops/sql/migrations/` — copy each new migration before sync.

**Not synced:** `scripts/*.ps1` (Windows), `data/dumps/`, gitignored config.

---

## 6. Ops layout & conventions

**Canonical rules live here.** [`site/public_html/ops/README.md`](../site/public_html/ops/README.md) is a short checklist for humans syncing the folder — do not fork naming or bootstrap rules into a second spec.

**Status (Jun 2026):** Conventions agreed; **`run_*.php` dev runners + `includes/` + `modules/`** in repo. **`dispatch.php`** still planned (thin router only).

### 6.1 `ops/` vs `staging-scripts/`

| Location | Use |
|----------|-----|
| **`site/public_html/ops/`** | All **new** ladder ops: dispatcher, modules, SQL mirrors, sim/post-game/periodic CMDs. |
| **`site/public_html/staging-scripts/`** | **Legacy** one-shot rebuild runners. **Do not add new logic.** League awards runner **removed** (Jun 2026); catalog superseded by `ops/run_prepare.php seed-catalog`. Inventory: [`coordination/staging-scripts-inventory.md`](coordination/staging-scripts-inventory.md). |

**Migration criteria (when moving a legacy script):** the runner is still needed on staging/prod path; it fits the `CMD=` or documented dev-runner pattern; callers and README are updated in the **same slice**; nothing is deleted from `staging-scripts/` until the replacement is verified.

**Do not** add new long-term business logic under `staging-scripts/`.

### 6.2 Target tree

```text
site/public_html/ops/
  .htaccess                 # deny HTTP
  README.md                 # checklist → this doc §6
  dispatch.php              # thin router (planned)
  run_prepare.php           # dev runner: prepare / seed-catalog / zero-derived / parity
  run_process_game.php      # dev runner: post-game P0–P7
  run_finalize_league.php   # dev runner: PER-003 + REP-012/013
  run_timeline_sim.php      # dev runner: Mode C simul
  includes/
    ops_bootstrap.php       # CLI, mysqli, DB guards
    ops_argv.php            # CMD= key=value parsing (planned)
  modules/
    process_completed_game.php   # example: one primary file per CMD
    …                       # periodic_*, replay_*, etc. as needed
  sql/
    migrations/             # mirror schema/migrations/
    rebuild/                # optional REP SQL mirrors
```

**`ops/lib/`:** not used until shared helpers are duplicated across modules (YAGNI). Shared post-game helpers may later live in `includes/` (bootstrap-sized) or `lib/` — pick one when the need appears; until then, keep modules self-contained.

### 6.3 Naming: `CMD` ↔ file ↔ function

| Layer | Convention | Example |
|-------|------------|---------|
| **Steve / CLI `CMD`** | PascalCase, verb-led | `ProcessCompletedGame`, `ReplayChronological`, `FinalizeLeaguePeriod` |
| **Module file** | `snake_case` under `modules/`, derived from `CMD` | `modules/process_completed_game.php` |
| **PHP entry function** | `k2_ops_<snake_case>()` | `k2_ops_process_completed_game(mysqli $con, int $gameId): void` |

**One primary module file per `CMD`** at first. Orchestration CMDs (`ReplayChronological`, `ApplySchema`) may call other `k2_ops_*` functions in the same or other module files — still no logic in `dispatch.php`.

**Periodic jobs** use the **same** `dispatch.php` (e.g. `CMD=RatingFade`) — not separate top-level PHP entry files unless Steve requires a different host path (document exception if so).

### 6.4 Bootstrap contract

Implemented in `includes/ops_bootstrap.php` and work-target profiles (`local-work`, `local-dev`, `staging-work`). **`dispatch.php`** will reuse the same connect/guards.

| Rule | Detail |
|------|--------|
| **SAPI** | CLI only for ops entry points (same as legacy `staging-scripts/`). |
| **Document root** | Set to `site/public_html/` so config resolves like the website. |
| **Base config** | `site/config/ko2unitydb_config.php` (gitignored). |
| **Work DB override** | `ini=ladder-work.ini` → `[database]` in `site/config/ladder-work.ini` (see `.example`). |
| **Explicit override** | `database=ko2unity_work` on the command line (after ini). |
| **Protected DBs** | **Refuse** connects to `ko2unity_baseline` and `kooldb2` (reset sources). |
| **Dev DB guard** | **`ko2unity_db`** is **off-limits by default**. Use **`--target local-dev`** only on intentional verbs (`seed-catalog`, `finalize-due` / `rebuild-all`, …). Full prepare/zero-derived stay **work DB only**. Browse work at **`http://work.ratingskickoff.test/`** ([`LOCAL_DEV.md`](../LOCAL_DEV.md)). |
| **Charset / TZ** | `utf8mb4`, `SET time_zone = '+00:00'` (match legacy staging bootstrap). |

**Target DB for sim/post-game development:** `ko2unity_work` locally, `kooldb1` on staging — see §4.

### 6.5 `dispatch.php` — allowed vs forbidden

| Allowed | Forbidden |
|---------|-----------|
| Parse `CMD` and `key=value` args | Elo, milestones, aggregates, contract SQL |
| Enforce bootstrap + DB guards | Large copy-pasted logic from `staging-scripts/` |
| `switch`/map `CMD` → require module + call `k2_ops_*` | Reading `ratedresults` for anything beyond sanity checks |
| Exit codes / stderr usage messages | New CMD names without a module file |

### 6.6 Testing before `dispatch.php` exists

Implement and prove modules **before** wiring Steve’s entry point.

| Phase | How |
|-------|-----|
| **Module development** | Build `k2_ops_*` in `modules/`; test via a **temporary** dev runner in the **same slice** (e.g. `ops/run_dev.php` that only `require`s bootstrap + one module) **or** a one-line CLI wrapper documented in the slice — **not** committed as permanent surface unless agreed. |
| **After module works** | Add `dispatch.php` routing to that module; delete or stop using the dev runner in the same or follow-up slice. |
| **Full history sim** | Until `CMD=ReplayChronological` ships: Python `python -m scripts.ladder run --target sandbox` on work DB. |

**Anti-pattern:** shipping `dispatch.php` with stub modules “for later” (see premature stub removed Jun 2026).

### 6.7 Slice boundaries (agents)

| Slice type | Typical contents |
|------------|------------------|
| **Conventions** (this §) | Docs only — no PHP |
| **Schema on work** | `schema/migrations/`, mirror to `ops/sql/migrations/`, PowerShell reset/apply scripts — no post-game |
| **Post-game phase** | One `modules/*.php` + contract subsection + tests on `ko2unity_work` — may include dev runner; may add `dispatch.php` only when the module is real |
| **Legacy migration** | Move one `staging-scripts/` runner → `ops/modules/` + update platform/README |

---

## 7. Target `ops/` tree (summary)

See §6.2 for the full tree. **Local sim (today):** Python replay on work DB — `python -m scripts.ladder run --target sandbox` ([`scripts/ladder/README.md`](../scripts/ladder/README.md)). **After `dispatch.php` ships:** same `CMD=` habit from `public_html/` against `ko2unity_work` / `ladder-work.ini`.

---

## 8. Lifecycle pipelines

### 8.1 Staging / cutover prep (Dagh)

**Prepare** (canonical order — [`work-db-prepare.md`](work-db-prepare.md)):

```text
1. RefreshWorkFromBaseline → clone baseline → work (kooldb2 → kooldb1 / ko2unity_baseline → ko2unity_work)
2. MigrateWork             → schema/migrations on work only
3. ZeroDerived             → derived day-zero; ground truth intact
```

**Simul** (after prepare):

```text
4. ReplayChronological     → processCompletedGame × N (game-only mode today)
5. (optional) Periodic at simulated boundaries (timeline simul) OR batch website rebuild after full history
6. Verify                  → checklist queries / CMD=Verify
```

**Do not** migrate then full-clone without re-migrate — refresh destroys expanded schema on work.

### 8.2 Production (steady state)

```text
Steve: INSERT ratedresults (ground) → game_id
Steve: php ops/dispatch.php CMD=ProcessCompletedGame game_id=…
Periodic: php ops/dispatch.php CMD=… (scheduler)
```

---

## 9. Implementation order (suggested)

1. ~~Ops layout & conventions (§6).~~ **Done (Jun 2026)** — docs only.
2. Work DB **prepare** pipeline documented — [`work-db-prepare.md`](work-db-prepare.md); automate (`PrepareWork` CMD / script) after ZeroDerived checklist sign-off.
3. `ProcessCompletedGame` module + derived phases per [`website-data-contract.md`](website-data-contract.md) (incremental); prove on work DB before dispatcher.
4. `dispatch.php` + guards routing to real modules (not empty stubs).
5. `ReplayChronological` calling same core on work DB.
6. Migrate legacy `staging-scripts/` → `ops/modules/` as needed.
7. Cutover: Steve wires prod call; retire duplicate Python authority when parity boring.

---

## 10. Open questions (non-blocking)

| Item | Notes |
|------|--------|
| Staging **website** DB config | `ko2unitydb_config.php` vs config1/2 — confirm with Steve |
| Legacy **`kooldb`** | May still exist from May 2026; new work uses **kooldb1** / **kooldb2** |
| Periodic vs replay ordering | **Timeline simul** (interleave) vs **batch rebuild** — see [`work-db-prepare.md`](work-db-prepare.md) §5 |
| ZeroDerived aggregate tables | Checklist §4.5 — automation pending Dagh review |

---

## 11. Explicit non-goals (this platform doc)

- Replacing [`website-data-contract.md`](website-data-contract.md) row-level rules before implementation.
- Moving `staging-scripts/` files in the same slice as first `dispatch.php` commit.
- Committing raw prod SQL dumps to git.
