# Operations quick start — what exists today

**Day-to-day only** — run commands here. Migration tracking: **`docs/UPDATE_DOCS.md`** when you or the agent run **“update docs”**. Registers: **`docs/prod-coordination.md`**.

**Ladder ops (Steve, staging deploy, sim):** [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) — sync **`site/public_html/`** (includes **`ops/`**); DB names in [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md).

**Retired dev scripts (Jun 2026):** [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) — do not use batch rebuild or Python ladder CLI for fill/sign-off.

**Local two URLs:** dev **`http://ratingskickoff.test/`** · work **`http://work.ratingskickoff.test/`** — setup: `scripts\setup_laragon_work_site.ps1` ([`LOCAL_DEV.md`](LOCAL_DEV.md)).

---

## Status at a glance (Jun 2026)

| Question | Answer |
|----------|--------|
| **Work DB / staging proof path?** | **Yes** — prepare + **`php ops/run_ops_sim.php run`** — [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) |
| **Fill derived on work / staging?** | `zero-derived` → `run_ops_sim.php` → `run_verify_ops_sim.php` — [`work-db-prepare.md`](work-db-prepare.md) §1.5 |
| **Frozen dev DB (`ko2unity_db`) recovery?** | Re-import May dump (`data/README.md`) — not retired batch/replay CLIs |
| **Steve prod cutover?** | [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |
| **Schema migrations?** | `site/public_html/ops/sql/migrations/` + `run_prepare.php migrate-work` |
| **Amiga staging DB refresh?** | Agent **runs** `scripts\export_ko2amiga_db.ps1` when Dagh asks to export to staged → tells him **ready for sync + import** → **preview** `/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` → **apply** `&apply=1` on `ratings.kickoff2.com` — [`amiga-staging-handoff.md`](amiga-staging-handoff.md) |

---

## Two paths (do not confuse)

| Path | Command | Use when |
|------|---------|----------|
| **Ops simul (authoritative)** | `run_prepare.php` → `run_ops_sim.php` → `run_verify_ops_sim.php` | **`ko2unity_work`**, **`kooldb1`** — [`work-db-prepare.md`](work-db-prepare.md) **§1.5** |
| **League awards dev repair** | `php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev` | **`ko2unity_db`** emergency only — **refused** on work |

**Work DB rule:** wrong derived state on work → **`zero-derived` → `run_ops_sim.php` again**. No batch repair on `local-work` / `staging-work`. Details: [`work-db-prepare.md`](work-db-prepare.md) §1.5.

| Ops helper | Command | Work sign-off? |
|------------|---------|----------------|
| **Prod-shaped simul** | `php site/public_html/ops/run_ops_sim.php run --target local-work` | **Yes** |
| **Verify (read-only)** | `php site/public_html/ops/run_verify_ops_sim.php --target local-work` | **Yes** |
| **League awards batch repair** | `php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev` | **No** — dev repair only; **refused** on work |
| **Finalize due (standalone)** | `php site/public_html/ops/run_finalize_league.php finalize-due --target local-work` | Module debug only — not sign-off after a rule change |
| **Play streaks repair** | `php scripts/rebuild_player_play_streaks.php` | Dev / one-off — not work sign-off |
| **Participation reached_at backfill (SCH-025)** | `php scripts/rebuild_participation_reached.php` | After migrate `025` on repair/dev DB — not live post-game path |

**Hall of Fame record dates:** PHP ops post-game contract — see [`staging-post-game-record-defects.md`](staging-post-game-record-defects.md).

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

Copy `site/config/ladder-work.ini.example` → `ladder-work.ini` first. **Never** refresh/migrate/zero on `ko2unity_baseline`.

---

## Updating post-game / Elo formulas

| Change | Where |
|--------|--------|
| Elo K, start rating | `scripts/k2_rating_core/constants.py`, `elo.py` — PHP mirrors in `ops/includes/post_game_*.php` |
| Per-game row fields | `scripts/k2_rating_core/apply_game.py`, `outcome.py` |
| Player career stats | `scripts/k2_rating_core/player_state.py` |
| Server row `generalstatstable` | `ops/includes/post_game_server_records.php` |
| What gets cleared at day zero | `ops/run_prepare.php` zero-derived + [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) |
| New column needs backfill | Above + `ops/sql/migrations/` + [`cutover-readiness.md`](coordination/cutover-readiness.md) |

After code changes: **re-simul on work** (`zero-derived` → `run_ops_sim.php` → verify).

---

## Schema (local)

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

Adds indexes etc. from `ops/sql/migrations/*.sql` to local `ko2unity_db` (via `apply_local.ps1` or `run_prepare.php migrate-work`). Register: `docs/coordination/schema-register.md`.

---

## Website derived data

**Preferred:** ops simul on **`ko2unity_work`** (see [Work DB](#work-db-prod-sandbox--local) above).

**Contract:** `docs/website-data-contract.md`. **Retired dev batch chain:** [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md).

---

## One-off script

1. Copy `scripts/oneoff/_template.py` → `scripts/oneoff/my_job.py`
2. Register in `docs/coordination/one-off-register.md`
3. `python scripts/oneoff/my_job.py --dry-run` then without `--dry-run`

Prefer ops simul when the job is “recompute from all games in order.”

**Throwaway vs one-off:** Steve one-offs = register OO row + `scripts/oneoff/`. Browser schema probes = `scripts/throwaway_*.php` (manual copy to `public_html`, not default sync). Milestone generators / parity scripts = local toolkit — [`scripts/oneoff/README.md`](../scripts/oneoff/README.md).

---

## Staging / Steve — what to sync today

**Forward path:** WinSCP-sync **`site/public_html/`** (includes **`ops/`**). Work DB on staging = **`kooldb1`** — prepare + simul per [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) and [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md).

| Task | Command / doc |
|------|----------------|
| Schema on work DB | `php ops/run_prepare.php migrate-work --target staging-work` |
| Fill derived tables | `php ops/run_ops_sim.php run --target staging-work` |
| Verify | `php ops/run_verify_ops_sim.php --target staging-work` |
| Live cutover (when scheduled) | [`post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |

**Historical May 2026 staging one-shot:** [`archive/STAGING_REPLAY-2026-05.md`](archive/STAGING_REPLAY-2026-05.md). **Not** the cutover recipe.

**Cutover email template:** [`coordination/cutover-packet-template.md`](coordination/cutover-packet-template.md)

---

## Amiga realm (offline — separate DB)

Not online ladder ops. Local build any way you like → export snapshot → sync → browser import on staging.

**Agent habit:** Dagh says **export to staged** → run export script, confirm **ready for WinSCP sync and staging import** (with URLs below).

| Step | Command / URL |
|------|----------------|
| Build local `ko2amiga_db` | `scripts\setup_ko2amiga_db.ps1` or `python -m scripts.amiga run` |
| **Sign-off / derived rebuild** | `python -m scripts.amiga prove` — L0→L5 + tournament-video DB anchor sync + verify ([`amiga-derived-write-policy.md`](amiga-derived-write-policy.md); manifest sync [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §12) |
| **Export SQL (agent runs this)** | `scripts\export_ko2amiga_db.ps1` → `site/public_html/amiga/_import/ko2amiga_db.sql` — run **`sync_db_ids`** (or full **`prove`**) first if player merges shifted ids; commit `review.csv` + `tournament_videos.json` with export |
| Deploy (Dagh) | WinSCP sync `site/public_html/` |
| **Staging import (preview)** | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee |
| **Staging import (apply)** | same + `&apply=1` |
| **Local import dry-run** | http://ratingskickoff.test/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee |

Full handoff: [`amiga-staging-handoff.md`](amiga-staging-handoff.md) · scripts: [`scripts/amiga/README.md`](../scripts/amiga/README.md)

---

## Folder map (real files)

```text
site/public_html/ops/           ← holy ops (prepare, simul, verify, post-game)
scripts/k2_rating_core/         ← shared Elo library (Amiga + PHP mirror reference)
scripts/amiga/                  ← Amiga holy ops (prove)
scripts/oneoff/                 ← registered one-offs + local toolkit (see README)
scripts/throwaway_*.php         ← browser probes only; not default WinSCP sync
site/public_html/ops/sql/migrations/  ← SCH DDL (synced with ops)
docs/coordination/              ← schema + replay registers; contract = behavior
docs/prod-coordination.md       ← hub when coordinating prod
docs/OPERATIONS_QUICK_START.md  ← this file
docs/obsolete-dev-scripts-retirement-policy.md  ← retired dev CLIs
```

---

## Still to-do (not built yet)

- **Live prod cutover** — prep done on `kooldb1`; Steve executes when scheduled ([`cutover-readiness.md`](coordination/cutover-readiness.md))
- **Bundled “staging deploy” script** (WinSCP automate) — optional
- **Periodic jobs** — [`coordination/periodic-register.md`](coordination/periodic-register.md)
