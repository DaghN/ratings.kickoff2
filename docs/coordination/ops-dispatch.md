# Ops dispatcher (`dispatch.php`)

**Status:** Shipped (Jun 2026).  
**Role:** **Router only** — parse `CMD=` / parameters, connect to the right work DB, call one `k2_ops_*` module, return a clear exit code. All rules live in modules + [`website-data-contract.md`](../website-data-contract.md).

**Not in scope for this file:** Elo, milestones SQL, migrations, batch simul (use `run_process_game.php` / `run_timeline_sim.php` for long replays).

---

## Design intent

| Principle | Detail |
|-----------|--------|
| **Dispatcher ≠ action script** | `dispatch.php` must stay thin so Steve (and cron) have **one stable entry** while we add CMDs over time. |
| **Extensible registry** | New CMD = new row in `K2_OPS_DISPATCH_REGISTRY` + handler in `includes/ops_dispatch.php` + module under `modules/`. |
| **Same code as simul** | `CMD=ProcessCompletedGame` calls the same `k2_ops_process_completed_game()` as `run_process_game.php process-one`. |
| **Explicit DB** | Every call requires `target=` (profile) or `database=` (work DB name). No silent default on staging. |

---

## Invocation

From `public_html/` on server (paths relative to repo layout):

```text
php site/public_html/ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=staging-work
php site/public_html/ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work
php site/public_html/ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work as_of=2026-06-03T00:00:01Z
php site/public_html/ops/dispatch.php CMD=ProcessPlayerRegistered player_id=42 target=staging-work
php site/public_html/ops/dispatch.php CMD=Help
```

Aliases: `database=kooldb1` instead of `target=staging-work` when the name matches a profile’s `work_database`.

Optional: `dry_run=1` or `--dry-run` (no commit).

---

## Registered CMDs (v1)

| CMD | Module | When Steve calls |
|-----|--------|------------------|
| `ProcessCompletedGame` | `process_completed_game.php` | After ground truth insert for one rated game |
| `FinalizeLeagueDue` | `finalize_league_period.php` | League finalize only (legacy cron; prefer `FinalizeUtcDay`) |
| `FinalizeUtcDay` | `finalize_utc_day.php` | **Daily ~00:00:01 UTC** — league + league event milestones + `perfect_day` / `nightmare_day` |
| `ProcessPlayerRegistered` | `process_player_registered.php` | New account / lobby milestone |

**Future CMDs (same file family):** `ReplayChronological`, `PrepareWork`, `MigrateWork` — batch/dev paths may stay on `run_*.php` until needed in cron.

---

## Exit codes (for scripts and monitoring)

| Code | Meaning | Steve action |
|------|---------|--------------|
| **0** | Success; derived work committed (or dry-run ok) | Continue |
| **1** | Failure (DB, missing row, invalid data, exception) | **Do not** assume derived tables updated; fix and retry |
| **2** | No-op: game **already processed** (`NewRatingA` set) | Safe to skip duplicate call |
| **64** | Usage / unknown CMD | Fix command line |

Stdout lines are prefixed `[dispatch]` for log grep.

---

## Failure semantics — “what if post-game didn’t finish?”

### One game = one transaction

`k2_ops_process_completed_game()` wraps **all** post-game writes in a single MySQL transaction:

- `ratedresults` derived columns  
- `playertable` career fields  
- `generalstatstable` (server records)  
- period / peak activity  
- milestones (when period counts available)  
- play streaks  

On any error: **`rollback`** — nothing from that game is left half-applied.

**Steve can rely on:** exit code **≠ 0** ⇒ treat that `game_id` as **not successfully derived** (ground row may still exist with goals set and `NewRatingA` still NULL).

### Exit 2 — already processed

If `NewRatingA` is already set, the module refuses with exit **2**. No DB change. Duplicate dispatcher calls after success are visible in logs.

### What dispatch does *not* guarantee

| Concern | Notes |
|---------|--------|
| **Ground insert failed** | Dispatcher is never run; DB has no row — out of scope. |
| **Steve runs dispatch before ground commit visible** | Module may not find row — exit **1**. |
| **Crash after commit, before Steve sees exit 0** | Rare; DB is updated; retry gets exit **2**. |
| **Batch simul mid-run stop** | Last game may be unprocessed; use `status-ratedresults` or SQL on `NewRatingA` — not dispatch. |
| **League finalize** | Separate CMD; does not run per game. |

### Retry policy

| Situation | Retry? |
|-----------|--------|
| Exit **1**, `NewRatingA` still NULL | Yes, after fixing cause |
| Exit **2** | No — already done |
| Exit **1**, partial suspicion | Query `ratedresults` for that `id`; if derived NULL, safe to retry |

---

## Target / credentials

Same as prepare: `target=staging-work` → `kooldb1` / `kooldb2` baseline for refresh-only paths. Dispatcher **only connects to work** DB; it will not mutate `kooldb2` / `ko2unity_baseline`.

MySQL **host/user/password**: copy from staging `ko2unitydb_config1.php` into `ops/config/work-targets.ini` `[staging-work]` (synced under `public_html/ops/`). Full staging runbook: [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md).

---

## HTTP

`ops/.htaccess` denies web access. **CLI only.**

---

## Adding a CMD

1. Implement `k2_ops_*` in `modules/<snake_case>.php`.  
2. Register in `K2_OPS_DISPATCH_REGISTRY` + `switch` in `k2_ops_dispatch_run()`.  
3. Document here and in [`periodic-register.md`](periodic-register.md) or post-game register if applicable.  
4. Do **not** add business logic to `dispatch.php`.
