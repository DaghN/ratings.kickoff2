# `ops/` — server operations (not the public site)

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** WinSCP-sync with `site/public_html/` → staging `public_html/`.

**Full conventions (canonical):** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) **§6 Ops layout & conventions** — naming, bootstrap, dispatcher rules, slice boundaries. **Do not duplicate rules here.**

---

## Today

- **Prepare (PHP):** `run_prepare.php` + `modules/prepare_work.php` — full prepare, `seed-catalog`, `zero-derived`, parity (see §6.6).
- **Post-game P0–P7 (PHP):** `run_process_game.php` — milestones + play streaks. **Sign-off:** `run_ops_sim.php` + `run_verify_ops_sim.php` ([`cutover-readiness.md`](../../../docs/coordination/cutover-readiness.md)).
- **Prod target:** PHP replaces C++ derived post-game at cutover ([`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) §2).
- **Periodic PER-003 (PHP):** `run_finalize_league.php` — `finalize-due` / `rebuild-all` (batch repair); **Steve midnight:** `CMD=FinalizeUtcDay` (league + league milestones + day-close).
- **UTC day tick:** `run_finalize_utc_day.php` — dev runner same as `CMD=FinalizeUtcDay`.
- **Prod-shaped simul:** `run_ops_sim.php run` — full history or `--until-game-id` (preferred). Low-level: `run_timeline_sim.php`.
- **After simul (local gate):** `run_verify_ops_sim.php` — six-value + league + milestone smoke SQL.
- **Dispatcher:** `dispatch.php` — Steve/cron `CMD=` entry · **Steve docs:** [`docs/post-dagh-live-story.md`](docs/post-dagh-live-story.md) (full bootstrap → live), [`docs/steve-live-ops.md`](docs/steve-live-ops.md), [`docs/ops-dispatch.md`](docs/ops-dispatch.md) (synced with WinSCP).
- **Not yet:** live **register** wired on prod (CMD exists: `ProcessPlayerRegistered`).

---

## Checklist (target layout)

| Path | Role |
|------|------|
| `dispatch.php` | Thin `CMD=` router → modules |
| `includes/ops_dispatch.php` | CMD registry + handlers (extend here) |
| `includes/ops_bootstrap.php`, `ops_argv.php` | CLI, DB connect, protected DBs (**planned**) |
| `modules/<snake_case>.php` | One primary file per `CMD` (e.g. `process_completed_game.php`) |
| `sql/migrations/` | Canonical SCH DDL — `migrate-work` applies in filename order; **commit every new `NNN_*.sql`** (not dump-gitignored) |
| `data/milestones_definitions_seed.json` | REP-014 catalog (prepare `seed-catalog`) |
| `config/work-targets.ini` | DB profiles (`staging-work`, …) — copy from `.example`, gitignored |
| `sql/rebuild/` | Optional REP SQL mirrors |

---


## Server deploy (Steve)

Dagh syncs **`site/public_html/`** → server **`public_html/`**. Steve runs all CLI on the host.

1. **`ops/docs/post-dagh-live-story.md`** — bootstrap + live (start here).
2. `ops/config/work-targets.ini.example` → `work-targets.ini` (Steve maintains).
3. Live dispatch: **`ops/docs/steve-live-ops.md`**.

`refresh-work` / full `prepare` = optional two-DB path ([`work-db-prepare.md`](../../../docs/work-db-prepare.md)); **not** the prod-copy runbook.

**Host binaries:** `mysql` / `mysqldump` only needed for `refresh-work` — see `includes/ops_shell.php`.

---

## Quick rules

- **All ladder ops code** → `ops/` only (legacy `staging-scripts/` removed Jun 2026).
- **No business logic** in `dispatch.php`.
- **Work / sim DB:** `ko2unity_work` (+ `ladder-work.ini`); **never** `ko2unity_baseline` / `kooldb2`.
- **Dev DB (`ko2unity_db`):** use `--target local-dev` only for intentional verbs (`seed-catalog`, league finalize); not for full `prepare` / `zero-derived`.
- **Test modules** before shipping `dispatch.php` (see platform doc §6.6).

---

## Local prepare (preferred)

```text
php site/public_html/ops/run_prepare.php prepare --target local-work
```

Or: `powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1`

**Migrate only (SCH DDL on work):**

```text
php site/public_html/ops/run_prepare.php migrate-work --target local-work
```

See [`docs/coordination/ops-schema-migrations.md`](../../../docs/coordination/ops-schema-migrations.md).

**Catalog only (REP-014):**

```text
php site/public_html/ops/run_prepare.php seed-catalog --target local-work
php site/public_html/ops/run_prepare.php seed-catalog --target local-dev
```

Legacy Python: `python -m scripts.work_prepare` (kept for reference).

## Local sim (post-game PHP)

```text
php site/public_html/ops/run_process_game.php replay-to --limit 100 --target local-work
php site/public_html/ops/run_process_game.php status-ratedresults --limit 100 --target local-work
```

**Sign-off gate (prod-shaped):**

```text
php site/public_html/ops/run_ops_sim.php run --target local-work --until-game-id 500
php site/public_html/ops/run_verify_ops_sim.php --target local-work
```

See [`docs/coordination/ops-simul-runbook.md`](../../../docs/coordination/ops-simul-runbook.md). Archived PHP-vs-Python A/B: `python -m scripts.work_prepare ab-post-game` — [`post-game-php-development.md`](../../../docs/post-game-php-development.md) §9 only.

## Local league finalize (PER-003 on work)

Requires `player_period_league` / `player_period_games` from post-game replay (or batch rebuild). After prepare, run post-game sim, then:

```text
php site/public_html/ops/run_finalize_league.php finalize-due --target local-work
```

Simulated midnight (timeline prep):

```text
php site/public_html/ops/run_finalize_league.php finalize-due --target local-work --as-of 2026-05-27T00:00:01Z
```

Parity backfill on work (destructive — truncates awards):

```text
php site/public_html/ops/run_finalize_league.php rebuild-all --target local-work
```

Laragon dev DB (`ko2unity_db`) — same verbs with `--target local-dev` (used by `rebuild_website_derived_data_local.ps1`):

```text
php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev
php site/public_html/ops/run_finalize_league.php finalize-due --target local-dev
```

`scripts/finalize_league_periods.php` is a thin delegate to the above (deprecated).

## Timeline sim (Mode C)

Post-game for each game in order; at each **UTC day** boundary, one `finalize-due` as-of next day `00:00:01Z`.

```text
php site/public_html/ops/run_timeline_sim.php run --target local-work --stop-at 2017-07-10T00:10:00Z
```

Optional `--start-at 2017-06-09T00:00:00Z` to skip earlier history. Stops before processing any game with `Date` > `--stop-at`.

---

## Steve / cron (dispatcher)

**Read first:** [`docs/steve-live-ops.md`](docs/steve-live-ops.md) · Detail: [`docs/ops-dispatch.md`](docs/ops-dispatch.md)

```text
php ops/dispatch.php CMD=ProcessPlayerRegistered player_id=<id> target=staging-work
php ops/dispatch.php CMD=ProcessCompletedGame game_id=<id> target=staging-work
php ops/dispatch.php CMD=FinalizeUtcDay target=staging-work
```

Run from `public_html/`. Exit codes and semantics: [`docs/ops-dispatch.md`](docs/ops-dispatch.md).

Batch simul still uses `run_process_game.php replay-to` (not dispatch).

