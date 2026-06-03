# `ops/` — server operations (not the public site)

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** WinSCP-sync with `site/public_html/` → staging `public_html/`.

**Full conventions (canonical):** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) **§6 Ops layout & conventions** — naming, bootstrap, dispatcher rules, slice boundaries. **Do not duplicate rules here.**

---

## Today

- **Prepare (PHP):** `run_prepare.php` + `modules/prepare_work.php` — full prepare without `dispatch.php` (see §6.6).
- **Post-game P0–P7 (PHP):** `run_process_game.php` — through milestones + play streaks. Parity: `ab-post-game --phase p6` (layers 1–6); P7 layer diff not wired yet.
- **Prod target:** PHP replaces C++ derived post-game at cutover ([`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) §2).
- **Periodic PER-003 (PHP):** `run_finalize_league.php` — `finalize-due` on work DB (`--as-of` for timeline simul); REP-012/013 via `rebuild-all` / `rebuild-aggregates`.
- **Timeline sim (Mode C):** `run_timeline_sim.php run --stop-at …` — post-game + daily `finalize-due` step.
- **Not yet:** `dispatch.php`, live **register** → `entered_arena`.

---

## Checklist (target layout)

| Path | Role |
|------|------|
| `dispatch.php` | Thin `CMD=` router → modules (**planned**) |
| `includes/ops_bootstrap.php`, `ops_argv.php` | CLI, DB connect, protected DBs (**planned**) |
| `modules/<snake_case>.php` | One primary file per `CMD` (e.g. `process_completed_game.php`) |
| `sql/migrations/` | Mirror of repo `schema/migrations/` before staging sync |
| `sql/rebuild/` | Optional REP SQL mirrors |

**Legacy:** [`../staging-scripts/`](../staging-scripts/) — old runners; migrate in named slices only.

---

## Quick rules

- **New ladder ops code** → `ops/`, not `staging-scripts/`.
- **No business logic** in `dispatch.php`.
- **Work / sim DB:** `ko2unity_work` (+ `ladder-work.ini`); **never** `ko2unity_baseline` / `kooldb2`.
- **Dev DB (`ko2unity_db`):** off-limits for ops unless explicit `allow_dev_db=1` when implemented.
- **Test modules** before shipping `dispatch.php` (see platform doc §6.6).

---

## Local prepare (preferred)

```text
php site/public_html/ops/run_prepare.php prepare --target local-work
```

Or: `powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1`

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

## Planned Steve call (not live yet)

```text
php …/ops/dispatch.php   CMD=ProcessCompletedGame   game_id=<id>
```

