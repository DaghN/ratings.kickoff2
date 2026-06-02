# Operations quick start — what exists today

**Day-to-day only** — run commands here. Migration tracking: **`docs/UPDATE_DOCS.md`** when you or the agent run **“update docs”**. Registers: **`docs/prod-coordination.md`**.

**Ladder ops (Steve, staging deploy, sim):** [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) — sync **`site/public_html/`** (includes **`ops/`**); DB names in [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md).

**Local two URLs:** dev **`http://ratingskickoff.test/`** · work **`http://work.ratingskickoff.test/`** — setup: `scripts\setup_laragon_work_site.ps1` ([`LOCAL_DEV.md`](LOCAL_DEV.md)).

---

## Status at a glance (May 2026)

| Question | Answer |
|----------|--------|
| **Local replay — one command?** | **Yes** — `scripts\run_local_replay.ps1` (or `python -m scripts.ladder run --target local`) |
| **Website derived data rebuild — one command?** | **Yes** — `scripts\rebuild_website_derived_data_local.ps1` |
| **How to change replay logic?** | Edit **`scripts/ladder/`** — see [Updating replay](#updating-replay) |
| **One-off template?** | **Yes** — `scripts/oneoff/_template.py` + README |
| **Staging package for Steve?** | **Partial** — `run_staging_ladder_replay.sh` + upload `scripts/ladder/`; no single zip; schema SQL separate |
| **Schema migrations folder?** | **Yes** — `schema/migrations/` + `schema/apply_local.ps1` |
| **Coordination registers?** | **Docs only** — track WHAT for prod; not automated |

---

## Two rebuild paths (do not confuse)

| Path | Command | Resets | Replays / rebuilds |
|------|---------|--------|---------------------|
| **Ladder replay** | `scripts\run_local_replay.ps1` | Derived columns on `ratedresults` + career fields on `playertable`; NULLs `generalstatstable` id=1 | **Every game** in `Date ASC, id ASC` order (Elo + per-game stats + in-memory server records), then **batch** `playertable` write + **GST** aggregates/holders |
| **Website derived data** | `scripts\rebuild_website_derived_data_local.ps1` | Truncates/rebuilds aggregate tables + **league awards** (REP-012) | SQL + `php scripts/finalize_league_periods.php --full-rebuild` |
| **League awards only** | `scripts\run_league_awards_rebuild.ps1` | `player_league_award`, `player_league_totals` | After aggregates exist; see [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| **Finalize due leagues (daily)** | `php scripts/finalize_league_periods.php` | Closed periods not yet in `league_period` | PER-003 prod cron equivalent |

**Hall of Fame record dates (Gianni streaks, Fiery victims, Eternalstudent opponents, etc.):** ladder replay + fixed C++ post-game — **not** the website-derived script. Known staging defects: [`docs/staging-post-game-record-defects.md`](staging-post-game-record-defects.md).

**Is replay “reset + game-by-game only”?** Almost: `python -m scripts.ladder run` = **(1) reset** derived ladder state, **(2) chronological per-game replay** (~74k), **(3) short end phase** — finalize opponent/victim counts, write `playertable`, rebuild `generalstatstable` row `id=1` (SQL totals + record holders). No separate step for GST within that command. Website aggregates are a **second** optional command if you need Status/Activity tables refreshed.

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

For ladder ops / post-game dev on a **prod-shaped** copy, use **`ko2unity_work`** — not `ko2unity_db` (browser dev). Full roles: [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) · conventions: [`ladder-ops-platform.md`](ladder-ops-platform.md) §6.

**One-time setup** (does not touch dev DB):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
```

**Typical loop:**

```powershell
# Clone baseline → work (fast reset)
powershell -ExecutionPolicy Bypass -File scripts\reset_local_work_db.ps1

# Expand project schema on work when ready
powershell -ExecutionPolicy Bypass -File scripts\apply_schema_to_work.ps1

# Full derived replay on work (until PHP ReplayChronological exists)
python -m scripts.ladder run --target sandbox --ini site/config/ladder-work.ini
```

Copy `site/config/ladder-work.ini.example` → `ladder-work.ini` first. **Never** run destructive ops on `ko2unity_baseline`.

---

## Updating replay

| Change | Where |
|--------|--------|
| Elo K, start rating | `scripts/ladder/constants.py`, `elo.py` |
| Per-game row fields | `scripts/ladder/engine.py`, `outcome.py` |
| Player career stats | `scripts/ladder/player_state.py`, `finalize_counts.py` |
| Server row `generalstatstable` | `scripts/ladder/generalstats.py` |
| What gets reset | `scripts/ladder/engine.py` (`reset_universe`), `docs/replay-v1-scope-and-reset.md` |
| New column needs backfill | Above + `schema/migrations/` + register in `docs/coordination/replay-register.md` |

After code changes: `run_local_replay.ps1` on local; then staging (below).

---

## Schema (local)

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

Adds indexes etc. from `schema/migrations/*.sql` to local `ko2unity_db`. The script refuses non-local DB names unless `-AllowNonLocal` is explicitly passed for a reviewed one-off. Register: `docs/coordination/schema-register.md`.

---

## Website derived data (local)

Rebuilds the website-owned aggregate tables from ground truth and runs parity checks:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
```

Contract: `docs/website-data-contract.md`.

The command refuses non-local DB names unless `-AllowNonLocal` is explicitly passed for a reviewed one-off. It pins MySQL to UTC, runs the modular SQL rebuilds in dependency order, and verifies totals against `ratedresults`.

---

## One-off script

1. Copy `scripts/oneoff/_template.py` → `scripts/oneoff/my_job.py`
2. Register in `docs/coordination/one-off-register.md`
3. `python scripts/oneoff/my_job.py --dry-run` then without `--dry-run`

Prefer replay when the job is “recompute from all games in order.”

---

## Staging — what to give Steve

**Not** a single installer. Repeatable **upload list**:

| Upload to server `public_html/` | From repo |
|----------------------------------|-----------|
| `run_staging_ladder_replay.sh` | repo root |
| `scripts/ladder/` (whole tree) | `scripts/ladder/` |

Steve runs from `public_html/`:

```bash
bash run_staging_ladder_replay.sh
```

The wrapper passes `--target staging`, and the Python replay prints DB identity before writes.

**Schema on staging:** send SQL file(s) from `schema/migrations/` — Steve runs on staging work DB **`kooldb1`** (pristine copy **`kooldb2`**). Legacy **`kooldb`** may still exist from May 2026. Steve confirmed staging and production are on entirely different physical servers; production cutover still needs its own reviewed instructions.

**Full checklist:** `docs/STAGING_REPLAY.md` · **Cutover email template:** `docs/coordination/cutover-packet-template.md`

**Remember:** staging DB does **not** get live games — replay is how numbers catch up.

---

## Folder map (real files)

```text
scripts/ladder/          ← replay engine (Python)
scripts/run_local_replay.ps1
scripts/rebuild_website_derived_data_local.ps1
scripts/oneoff/          ← one-off template
schema/migrations/       ← SQL for Steve + local apply
run_staging_ladder_replay.sh   ← Steve staging replay wrapper
docs/coordination/       ← schema + replay registers; contract = behavior
docs/prod-coordination.md      ← hub when coordinating prod
docs/OPERATIONS_QUICK_START.md ← this file
```

---

## Still to-do (not built yet)

- **Prod** replay / schema / C++ cutover (registers track; Steve executes)
- **Bundled “staging deploy” script** (WinSCP automate) — optional
- **Elo K / fade** prod changes — contract + periodic register when scoped
- **Periodic jobs** beyond documenting fade (PER-001)
