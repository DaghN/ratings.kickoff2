# `ops/` — server operations (not the public site)

---

## Steve — read this first (do not use this README as your runbook)

**You are in the `ops/` folder.** This file is a **developer map**. Your instructions are in **`ops/docs/`**.

| Step | Open this file |
|------|----------------|
| **1 — Start here (prod cutover, full story)** | **[`docs/post-dagh-live-story.md`](docs/post-dagh-live-story.md)** |
| **2 — After cutover (daily commands)** | [`docs/steve-live-ops.md`](docs/steve-live-ops.md) |
| **3 — Only if you need exit codes / CMD detail** | [`docs/ops-dispatch.md`](docs/ops-dispatch.md) |

**Before you run anything:** copy `config/work-targets.ini.example` → `config/work-targets.ini` and set the real database host, user, and password for this server.

`dispatch.php` in this folder is the **program you run** — not the guide. The guide is **`docs/post-dagh-live-story.md`**.

---

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** WinSCP-sync with `site/public_html/` → staging `public_html/`.

**Full conventions (canonical):** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) **§6 Ops layout & conventions** — naming, bootstrap, dispatcher rules, slice boundaries. **Do not duplicate rules here.**

---

## Today

- **Prepare (PHP):** `run_prepare.php` + `modules/prepare_work.php` — full prepare, `seed-catalog`, `zero-derived`, parity (see §6.6).

**Legacy Python:** `python -m scripts.work_prepare` — **retired Jun 2026** (stub → use `run_prepare.php`). Archived modules: `docs/archive/work-prepare-retired-2026-06/`.

- **Post-game P0–P7 (PHP):** `run_process_game.php` — milestones + play streaks. **Sign-off:** `run_ops_sim.php` + `run_verify_ops_sim.php` ([`cutover-readiness.md`](../../../docs/coordination/cutover-readiness.md)).
- **Prod target:** PHP replaces C++ derived post-game at cutover ([`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) §2).
- **Periodic PER-003 (PHP):** `run_finalize_league.php` — `finalize-due` (debug); **`rebuild-all` / `rebuild-aggregates` = dev repair only** (`--target local-dev`; **refused** on work). **Steve midnight:** `CMD=FinalizeUtcDay` (league + league milestones + day-close).
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
| `includes/ops_bootstrap.php`, `ops_argv.php` | CLI, DB connect, protected DBs, `CMD=` parsing |
| `includes/day_close_milestones.php`, `league_milestones_sync.php` | UTC day tick writers (`FinalizeUtcDay` only) — unlock rows via `../includes/milestone_unlock.php` |
| `../includes/milestone_unlock.php` | Single live `player_milestones` INSERT path (post-game, day-close, league, register) |
| `modules/<snake_case>.php` | One primary file per `CMD` (e.g. `process_completed_game.php`) |
| `sql/migrations/` | Canonical SCH DDL — `migrate-work` applies in filename order; **commit every new `NNN_*.sql`** (not dump-gitignored) |
| `data/milestones_definitions_seed.json` | REP-014 catalog (prepare `seed-catalog`) |
| `config/work-targets.ini` | DB profiles (`staging-work`, …) — copy from `.example`, gitignored |
| `config/dispatch-http.ini` | HTTP dispatch `shared_key` — copy from `.example`, gitignored |
| `../dispatch_request.php` | HTTP bridge to dispatch (game server) |
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
- **Work / sim DB:** `ko2unity_work` / `kooldb1` — **sign-off = prepare + simul only** ([`work-db-prepare.md`](../../../docs/work-db-prepare.md) §1.5). **Never** `rebuild-all` on work (CLI refuses).
- **Never** `ko2unity_baseline` / `kooldb2`.
- **Dev DB (`ko2unity_db`):** `--target local-dev` for legacy batch repair and catalog seed — not work sign-off.
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

**Catalog copy repair (no TRUNCATE — keeps `holder_count`):**

```text
php site/public_html/ops/run_prepare.php sync-catalog-copy --target local-work
php site/public_html/ops/run_prepare.php sync-catalog-copy --target staging-work
```

Use when `rule_short` / `display_name` drifted (e.g. UTF-8 mojibake of `≥` → `â‰¥`). Site PHP also repairs that mojibake on read until the DB is synced.

Legacy Python: `python -m scripts.work_prepare` — **retired** (slice 3); use `run_prepare.php` above.

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

## Local league finalize (PER-003 — debug, not work sign-off)

League awards on **work sign-off** come from **`run_ops_sim.php`** (`FinalizeUtcDay` each UTC day). Use standalone finalize only for narrow module debugging:

```text
php site/public_html/ops/run_finalize_league.php finalize-due --target local-work
php site/public_html/ops/run_finalize_league.php finalize-due --target local-work --as-of 2026-05-27T00:00:01Z
```

**After a writer rule change:** `zero-derived` → `run_ops_sim.php` — not `finalize-due` or `rebuild-all` on work.

**Batch league repair (frozen dev only — refused on `local-work` / `staging-work`):**

```text
php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev
php site/public_html/ops/run_finalize_league.php rebuild-aggregates --target local-dev
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

