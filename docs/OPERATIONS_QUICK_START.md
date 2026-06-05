# Operations quick start — what exists today

**Day-to-day only** — run commands here. Migration tracking: **`docs/UPDATE_DOCS.md`** when you or the agent run **“update docs”**. Registers: **`docs/prod-coordination.md`**.

**Ladder ops (Steve, staging deploy, sim):** [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) — sync **`site/public_html/`** (includes **`ops/`**); DB names in [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md).

**Local two URLs:** dev **`http://ratingskickoff.test/`** · work **`http://work.ratingskickoff.test/`** — setup: `scripts\setup_laragon_work_site.ps1` ([`LOCAL_DEV.md`](LOCAL_DEV.md)).

---

## Status at a glance (Jun 2026)

| Question | Answer |
|----------|--------|
| **Work DB / staging proof path?** | **Yes** — prepare + **`php ops/run_ops_sim.php run`** — [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) |
| **Local Elo / GST replay (`ko2unity_db`)?** | `scripts\run_local_replay.ps1` — core ladder only |
| **Website aggregates on dev DB (repair)?** | `scripts\rebuild_website_derived_data_local.ps1` — **deprecated**; prefer simul on `ko2unity_work` |
| **Steve prod cutover?** | [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |
| **Schema migrations?** | `site/public_html/ops/sql/migrations/` + `run_prepare.php migrate-work` |

---

## Three paths (do not confuse)

| Path | Command | Use when |
|------|---------|----------|
| **Ops simul (authoritative)** | `run_prepare.php` → `run_ops_sim.php` → `run_verify_ops_sim.php` | **`ko2unity_work`**, **`kooldb1`**, prod copy cutover — [`work-db-prepare.md`](work-db-prepare.md) |
| **Ladder replay (dev DB)** | `scripts\run_local_replay.ps1` | Elo + `playertable` + `generalstatstable` on **`ko2unity_db`** only |
| **Batch SQL repair (legacy)** | `scripts\rebuild_website_derived_data_local.ps1` | **`ko2unity_db`** emergency refill of aggregate tables — SQL in `scripts/ladder/sql/archive/batch-2026-05/` |

| Ops helper | Command |
|------------|---------|
| **League awards rebuild** | `php site/public_html/ops/run_finalize_league.php rebuild-all --target local-work` |
| **Finalize due (daily)** | `php site/public_html/ops/run_finalize_league.php finalize-due --target local-work` |
| **Play streaks repair** | `php scripts/rebuild_player_play_streaks.php` (not batch `.sql`) |

**Hall of Fame record dates:** ladder replay + post-game contract — see [`staging-post-game-record-defects.md`](staging-post-game-record-defects.md). **Not** the batch website repair script.

---

## Local replay (“push button”)

**Once per machine:** Laragon **Start All**, `pip install -r scripts/ladder/requirements.txt`, `site/config/ko2unitydb_config.php` pointing at `ko2unity_db`.

From repo root:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1
```

Dry-run first (no writes):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1 -DryRun
```

**~3–5 min**, ~74k games. **Recovery:** re-import dump (`data/README.md`) if you need a clean slate.

**After replay (optional):** `python -m scripts.ladder.golden_record_checks` — Hall of Fame date regression matrix ([`docs/staging-post-game-record-defects.md`](staging-post-game-record-defects.md)).

**Manual equivalent:** `python -m scripts.ladder run --target local` — full options in `scripts/ladder/README.md`. Staging **`kooldb1`** (work DB; legacy name `kooldb` may still exist) requires `--target staging`; production would need a separately reviewed wrapper.

---

## Work DB (prod sandbox — local)

For ladder ops / post-game dev on a **prod-shaped** copy, use **`ko2unity_work`** — not `ko2unity_db` (browser dev). **Canonical pipeline:** [`work-db-prepare.md`](work-db-prepare.md). DB names: [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) · ops: [`ladder-ops-platform.md`](ladder-ops-platform.md).

**One-time setup** (does not touch dev DB):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
```

**Prepare work** (v2 — preferred):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
```

Legacy steps: [`work-db-prepare.md`](work-db-prepare.md) §3.4.

**Simul** (after prepare) — prod-shaped (per-game + UTC day ticks):

```powershell
php site/public_html/ops/run_ops_sim.php run --target local-work
php site/public_html/ops/run_verify_ops_sim.php --target local-work
```

Steve on staging: `--target staging-work` (see `ops/config/work-targets.ini`). Full runbook: [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md).

Legacy: `python -m scripts.ladder run --target sandbox` — batch tail only; **not** cutover sign-off.

Copy `site/config/ladder-work.ini.example` → `ladder-work.ini` first. **Never** refresh/migrate/zero on `ko2unity_baseline`.

---

## Updating replay

| Change | Where |
|--------|--------|
| Elo K, start rating | `scripts/ladder/constants.py`, `elo.py` |
| Per-game row fields | `scripts/ladder/engine.py`, `outcome.py` |
| Player career stats | `scripts/ladder/player_state.py`, `finalize_counts.py` |
| Server row `generalstatstable` | `scripts/ladder/generalstats.py` |
| What gets reset | `scripts/ladder/engine.py` (`reset_universe`), `docs/replay-v1-scope-and-reset.md` |
| New column needs backfill | Above + `ops/sql/migrations/` + [`cutover-readiness.md`](coordination/cutover-readiness.md) |

After code changes: `run_local_replay.ps1` on local; then staging (below).

---

## Schema (local)

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

Adds indexes etc. from `ops/sql/migrations/*.sql` to local `ko2unity_db` (via `apply_local.ps1` or `run_prepare.php migrate-work`). Register: `docs/coordination/schema-register.md`.

---

## Website derived data

**Preferred:** ops simul on **`ko2unity_work`** (see [Work DB](#work-db-prod-sandbox--local) above).

**Legacy repair on `ko2unity_db` only** (batch SQL in `scripts/ladder/sql/archive/batch-2026-05/`):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
```

Contract: `docs/website-data-contract.md`. Refuses non-`ko2unity_db` unless `-AllowNonLocal`.

---

## One-off script

1. Copy `scripts/oneoff/_template.py` → `scripts/oneoff/my_job.py`
2. Register in `docs/coordination/one-off-register.md`
3. `python scripts/oneoff/my_job.py --dry-run` then without `--dry-run`

Prefer replay when the job is “recompute from all games in order.”

---

## Staging / Steve — what to sync today

**Forward path:** WinSCP-sync **`site/public_html/`** (includes **`ops/`**). Work DB on staging = **`kooldb1`** — prepare + simul per [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) and [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md).

| Task | Command / doc |
|------|----------------|
| Schema on work DB | `php ops/run_prepare.php migrate-work --target staging-work` |
| Fill derived tables | `php ops/run_ops_sim.php run --target staging-work` |
| Verify | `php ops/run_verify_ops_sim.php --target staging-work` |
| Live cutover (when scheduled) | [`post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |

**Legacy (May 2026, frozen `kooldb` only):** `run_staging_ladder_replay.sh` + `scripts/ladder/` — historical record [`archive/STAGING_REPLAY-2026-05.md`](archive/STAGING_REPLAY-2026-05.md). **Not** the cutover recipe.

**Cutover email template:** [`coordination/cutover-packet-template.md`](coordination/cutover-packet-template.md)

---

## Folder map (real files)

```text
scripts/ladder/          ← replay engine (Python)
scripts/run_local_replay.ps1
scripts/rebuild_website_derived_data_local.ps1   # legacy repair only
scripts/ladder/sql/archive/batch-2026-05/        # batch SQL (not cutover)
scripts/oneoff/          ← one-off template
site/public_html/ops/sql/migrations/  ← SCH DDL (synced with ops)
run_staging_ladder_replay.sh   ← deprecated May 2026 kooldb replay
docs/coordination/       ← schema + replay registers; contract = behavior
docs/prod-coordination.md      ← hub when coordinating prod
docs/OPERATIONS_QUICK_START.md ← this file
```

---

## Still to-do (not built yet)

- **Live prod cutover** — prep done on `kooldb1`; Steve executes when scheduled ([`cutover-readiness.md`](coordination/cutover-readiness.md))
- **Bundled “staging deploy” script** (WinSCP automate) — optional
- **Periodic jobs** — [`coordination/periodic-register.md`](coordination/periodic-register.md)
