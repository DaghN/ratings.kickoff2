# `ops/` — server operations (not the public site)

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** WinSCP-sync with `site/public_html/` → staging `public_html/`.

**Full conventions (canonical):** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) **§6 Ops layout & conventions** — naming, bootstrap, dispatcher rules, slice boundaries. **Do not duplicate rules here.**

---

## Today

- **Prepare (PHP):** `run_prepare.php` + `modules/prepare_work.php` — full prepare, `seed-catalog`, `zero-derived`, parity (see §6.6).
- **Post-game P0–P7 (PHP):** `run_process_game.php` — through milestones + play streaks. Parity: `ab-post-game --phase p6` (layers 1–6); P7 layer diff not wired yet.
- **Prod target:** PHP replaces C++ derived post-game at cutover ([`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) §2).
- **Periodic PER-003 (PHP):** `run_finalize_league.php` — `finalize-due` on work DB (`--as-of` for timeline simul); REP-012/013 via `rebuild-all` / `rebuild-aggregates`.
- **Timeline sim (Mode C):** `run_timeline_sim.php run --stop-at …` — post-game + daily `finalize-due` step.
- **Dispatcher:** `dispatch.php` — Steve/cron `CMD=` entry ([`ops-dispatch.md`](../../../docs/coordination/ops-dispatch.md)).
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

**Legacy:** [`../staging-scripts/`](../staging-scripts/) — old runners; migrate in named slices only.

---

## Staging WinSCP (self-contained under `public_html/ops/`)

Sync **`site/public_html/`** → server **`public_html/`** (includes all of `ops/`).

On the server once:

1. `ops/config/work-targets.ini.example` → `ops/config/work-targets.ini` — fill `[staging-work]` with same MySQL login as `config/ko2unitydb_config1.php` (`kooldb1` / `kooldb2`).
2. Run: `php ops/run_prepare.php prepare --target staging-work`

No separate `data/` or `site/config/` upload required for prepare/dispatch (legacy `site/config/work-targets.ini` still works locally if present).

**Steve staging runbook:** [`docs/coordination/staging-work-steve-handoff.md`](../../../docs/coordination/staging-work-steve-handoff.md)

**Host binaries for refresh:** `mysql` / `mysqldump` on PATH or standard Laragon (Windows) / `/usr/bin` (Linux) paths — see `includes/ops_shell.php`.

---

## Quick rules

- **New ladder ops code** → `ops/`, not `staging-scripts/`.
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

**P1 parity gate (orchestrated):**

```text
python -m scripts.work_prepare ab-post-game --target local-work --limit 100
```

Default: zero-derived → PHP `replay-to` → snapshots → Python `ladder run` → diff layers 1–5 with `--phase p5` (tol 0.001). Python batch-rebuilds period + aggregate tables from processed `ratedresults` at end of replay (`period_activity.py`, `period_aggregates.py`).

See [`scripts/work_prepare/README.md`](../../../scripts/work_prepare/README.md) and [`scripts/ladder/README.md`](../../../scripts/ladder/README.md).

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

```text
php site/public_html/ops/dispatch.php CMD=ProcessCompletedGame game_id=<id> target=staging-work
php site/public_html/ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work
```

Exit codes, failure semantics, adding CMDs: [`docs/coordination/ops-dispatch.md`](../../../docs/coordination/ops-dispatch.md).

Batch simul still uses `run_process_game.php replay-to` (not dispatch).

