# Amiga staging — deploy & refresh

**Status:** **Live** on `ratings.kickoff2.com` (Jun 2026) — rating, profile, games, cross-realm search.

**Operational model (Jul 2026):** **Staged `ko2amiga_db` = prod** for community ground; **local `ko2amiga_work` = repair shop** — pull → simul/repair → push. Policy: [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md). Runbook: this doc. Live ops: [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) + [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md).

**Agents — remind Dagh:** When local `ko2amiga_db` should match staging (any import path, not only Access file changes): export → WinSCP sync → browser import. Script: `public_html/amiga/run_import_ko2amiga.php` (build tag in page header, e.g. `a2-2026-06-06-b4`). Password **`coffee`** — add `&pwd=coffee` to the URL, or enter it on the form when the `once` link is valid without `pwd`. **Preview:** `/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` · **Apply:** `&apply=1&part=1` (short parts auto-continue; avoids gateway timeout). Staging base: `https://ratings.kickoff2.com` · local: `http://ratingskickoff.test`. Import payload: `public_html/amiga/_import/ko2amiga_manifest.json` + `ko2amiga_*.sql` part files (gitignored; WinSCP). Full dump `ko2amiga_db.sql` optional (Heidi fallback).

**Agents — when Dagh says “export to staged” (or similar):** **run** `scripts\export_ko2amiga_work.ps1` yourself (dumps local **`ko2amiga_work`** into staging-import `ko2amiga_*` parts; promotes work video manifest first; **regenerates + audits** export table manifest from `schema_bundles` before dump). Use `setup_ko2amiga_db.ps1` only for a full Access oracle rebuild (`export_ko2amiga_db.ps1` shim). Then reply that the dump is **ready for WinSCP sync and staging import** — include preview/apply URLs above. Do not hand-wave “run export locally”; execute it.

**Destructive import (read every time):** Staging browser import **replaces** the whole staging `ko2amiga_db` from export parts — it does **not** merge events that exist only on staging. Routine refresh = export **living work** after **simul**, not nuclear `prove` + oracle export. Community mistakes on staging → anchored repair ([`amiga-live-ops-platform.md`](amiga-live-ops-platform.md)), not full reimport from Access.

**Agents — pull staged → local (repair shop):** Run `powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force` when Dagh says **pull staged Amiga** (or: pull Amiga from staged · refresh `ko2amiga_work` from staging). **Execute the script** — do not hand-wave WinSCP/mysqldump. Sync to staging first if export PHP changed: `run_export_ko2amiga.php` + `includes/amiga_staging_export_lib.php` (export build **v4+**). **Does not run simul by default** — `-Simul` only when sign-off needs it. Writes `data/amiga/modern/staging-sync-last.json`. Manual URLs below. Policy: [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) §8.

**Work git checkpoint (milestone backup — not staging push):** When forward **`ko2amiga_work`** must be recoverable before push (structure tail, Tier E, etc.), seal a named checkpoint:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label tail
```

Writes `data/amiga/checkpoints/work-YYYY-MM-DD-<label>/` (export parts + `manifest.json` + `companion/` JSON snapshots). **Opt-in git:** add a `.gitignore` allowlist for that folder (see [`data/amiga/checkpoints/README.md`](../data/amiga/checkpoints/README.md)). **First sealed:** `work-2026-07-11-tail` (~71 MB, commit `14a15d6`). Policy: [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) §7.

---

## Layout (same as online site)

| Piece | Path on server |
|--------|----------------|
| Web root | `public_html/` (WinSCP sync from `site/public_html/`) |
| Amiga DB config | `config/ko2amiga_config.local.php` — **sibling of `public_html`**, not inside it |
| Config router (git) | `config/ko2amiga_config.php` |
| Amiga PHP include | `include __DIR__ . '/../../config/ko2amiga_config.php';` in `public_html/amiga/*.php` |
| Database | **`ko2amiga_db`** (separate from online `kooldb*`) |
| Import payload | `public_html/amiga/_import/ko2amiga_manifest.json` (tracked) + `ko2amiga_01_schema.sql` … part files ending in snapshots/current + derived tables (SQL parts gitignored; WinSCP) (+ optional full `ko2amiga_db.sql`) |
| Pull export dump | `public_html/amiga/_export/ko2amiga_staging_pull.sql` + `ko2amiga_staging_pull_manifest.json` (gitignored; **overwrite** each generate) |
| Work git checkpoints | `data/amiga/checkpoints/work-YYYY-MM-DD-<label>/` (milestone seals; SQL opt-in per folder) — [`data/amiga/checkpoints/README.md`](../data/amiga/checkpoints/README.md) |
| **Export table manifest** | `public_html/data/amiga/staging_export_tables.json` (tracked; source = `scripts/amiga/staging_export_tables.py` synced to `schema_bundles`) |

**Export table registry (Jul 2026):** Canonical list = `scripts/amiga/staging_export_tables.py` (`STAGING_EXPORT_TABLES`). Must match product tables from `schema_bundles` DDL (minus retired L4 tables). Committed JSON is consumed by push export (`Export-Ko2AmigaStaging.ps1`) and pull export (`amiga_staging_export_lib.php`). **`export_ko2amiga_work.ps1`** runs `write-staging-export-tables` + `audit-staging-export --database ko2amiga_work` before mysqldump — export **fails** if a new bundle table is missing (prevents pull → small fix → incomplete push loops). Manual: `python -m scripts.amiga audit-staging-export` · `scripts/oneoff/audit_ko2amiga_export_tables.py`.

Online `kooldb*` is untouched. Credentials mirror staging config1 user/password; only `$database` differs.

---

## Live URLs

- https://ratings.kickoff2.com/amiga/rating.php
- https://ratings.kickoff2.com/amiga/tournaments.php
- https://ratings.kickoff2.com/amiga/tournament.php?id=372 (London XXIII — adjust id after import)
- https://ratings.kickoff2.com/amiga/player/profile.php?id=1
- https://ratings.kickoff2.com/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee (organizer — **Create player** on compose league after prove/export/import)

---

## Pull staged → local (repair shop)

**One command (PULL-1a):**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force
```

Triggers staging export PHP (`generate=1&format=json`), downloads dump, **replaces** local **`ko2amiga_work`**, writes `data/amiga/modern/staging-sync-last.json`. **Simul opt-in:** `-Simul` (~20 min; not default). Requires Laragon MySQL + synced export PHP on staging (`run_export_ko2amiga.php` + `includes/amiga_staging_export_lib.php`, build **v4+**).

**File retention:** Staging keeps **one** SQL file (overwrite). Local pull keeps `ko2amiga_staging_pull_latest.sql` (overwrite) plus one timestamped archive per run under `data/amiga/pulls/` (gitignored).

**Verified Jul 2026:** full pull from `ratings.kickoff2.com` — ~74 MB mysqldump, import ~2.5 min, spot-check **605 / 469 / 27,418** games.

**Manual / browser** (same end state):

| Step | URL |
|------|-----|
| **Preview** (no dump) | https://ratings.kickoff2.com/amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee |
| **Generate dump** | https://ratings.kickoff2.com/amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee&generate=1 |
| **Download dump** | https://ratings.kickoff2.com/amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee&download=1 |

Local dry-run: same paths on `http://ratingskickoff.test` when Laragon `ko2amiga_db` is configured.

---

## Dagh — code or data refresh

1. **Code:** WinSCP sync **`site/public_html/`** → staging **`public_html/`** (usual button). Include **`public_html/data/amiga/country_registry.json`** (country registry — Jul 2026) and **`img/flags/amiga/`** (253 flag SVGs) with PHP changes; without the JSON, Amiga table pages render headers but **fatal mid-row** (empty bodies).

2. **Data** — whenever local **`ko2amiga_work`** is the state you want on staging (simul, live-ops forward, manual SQL, etc.):

**PHP-only sync** (organizer PHP/JS without re-import): staging DB may lag until the next full import — prefer export + import for schema changes.

```powershell
# Full rebuild from Access (oracle prove + export — archaeology only):
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Daily path — export living ground (simul first if derived changed):
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1
```

WinSCP sync `public_html/` (must include `amiga/run_import_ko2amiga.php` + `amiga/_import/ko2amiga_manifest.json` + all `ko2amiga_*.sql` part files from export), then open a preview URL (or apply URL — password form if `pwd` is omitted):

| Step | URL |
|------|-----|
| **Preview** (no DB changes) | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee |
| **Apply import** | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee&apply=1&part=1 |

Preview must show the manifest **part count** from the latest export and the importer build tag. Apply runs part 1 (schema) through the last part; each part auto-continues (~2s). Expect **~473 players**, **~27k games**, **`amiga_player_event_snapshots`** + **`amiga_player_current`** + **`amiga_player_elo_rank_at_event`** (time-travel hero rank), **`tournament_entrants`**, and **`lifecycle_status`** columns after import completes.

**Post-import verify (local or after staging refresh):** On work clone: `python -m scripts.amiga simul` or spot-check verify CLIs. On oracle: `python -m scripts.amiga parity` (work vs frozen `ko2amiga_db`). Staging does not run Python replay automatically — export must come from local **`ko2amiga_work`** that already passed **simul**.

**Tournament video manifest:** Modern path — `export_ko2amiga_work.ps1` runs **snapshot → `align-video-work` → `promote-video-deploy`** (shared sidecar canon → deploy `site/public_html/data/amiga/`). Legacy oracle rebuild: **`prove`** + `sync_db_ids` before export ([`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §12).

Password is **`coffee`** (`&pwd=coffee` in URL, or type it on the prompt page).

Local dry-run (same paths, `ratingskickoff.test`): preview URL above with local host.

Spot-check: `/amiga/games/recent.php`, `/amiga/rating.php`, `/amiga/player/profile.php?id=1`, `/amiga/player/games.php?id=1` (legacy `/amiga/profile.php` and `/amiga/games.php?id=` redirect to player tabs; bare `/amiga/games.php` → Games hub Recent).

**Verified Jun 2026:** multi-part browser import on staging (`ratings.kickoff2.com`) — rating, profile, and games spot-checks OK. **Jun 2026 (slice 9):** export tail = `amiga_player_event_snapshots` + `amiga_player_current` (legacy participation/totals/rating_events retired). **Jun 2026 (elo_rank):** export adds `amiga_player_elo_rank_at_event` (~173k rows local) for time-travel hero/H2H rank; spot-check profile `?as=year:2003` shows `#N` not `#0`. **Jun 2026 (matchup-at-event):** export also includes `amiga_player_matchup_at_event`. **Jun 2026 (realm snapshots):** export tail adds `amiga_realm_snapshots` + ratio columns on `amiga_generalstats` (~605 rows). **Jun 2026 (HoF geo/year):** export tail adds SCH-028 columns on snapshots/current + eight new `generalstats` holder fields; spot-check `/amiga/hall-of-fame.php` and `/amiga/leaderboards/calendar-geo.php`. **Jun 2026-22 (World Cups slice):** export tail adds `amiga_player_slice_totals` + `amiga_player_slice_at_event` (~221 / ~3050 rows); spot-check `/amiga/world-cups/players/honours.php` + time travel. **Jun 2026-26 (WC country slice):** export tail adds `amiga_country_slice_totals` + `amiga_country_slice_at_event` (~22 / ~373 rows); spot-check `/amiga/world-cups/countries/honours.php` and sibling sub-wings. **Jun 2026-29 (WC HoF, SCH-046):** export tail adds `amiga_wc_hof_snapshots` + `amiga_wc_hof_present` (parts 39–40; ~23 + 1 rows); spot-check `/amiga/hall-of-fame.php` **World Cups** block (career block still from `amiga_generalstats` / realm snapshots). Re-import after sync if player tournament, honours, HoF, WC player stats, or WC country stats pages were empty/stale.

**Local export paths:** routine staging dump = `scripts\export_ko2amiga_work.ps1` (source `ko2amiga_work`; output still `ko2amiga_*` for staging `ko2amiga_db` import; preflight audit + manifest regen). Oracle-only shim = `export_ko2amiga_db.ps1`. Shared logic: `scripts\lib\Export-Ko2AmigaStaging.ps1` (reads `data/amiga/staging_export_tables.json`). **Jul 2026 (SC-0):** push parts include `ko2amiga_07a_stage_scoring_steps.sql` for `tournament_stage_scoring_steps`.

**Fallback:** Steve Heidi/mysql import of `ko2amiga_db.sql` — only if browser import fails.

---

## Steve — one-time setup (done)

1. Create MySQL database **`ko2amiga_db`**.
2. Import `public_html/amiga/_import/ko2amiga_db.sql`.
3. Copy `config/ko2amiga_config.local.php.example` → `config/ko2amiga_config.local.php` (same folder as online `ko2unitydb_config.local.php`).

**Do not** put Amiga config under `public_html/amiga/` — pages load `../../config/ko2amiga_config.php` only.

---

## WhatsApp — only if browser import fails

```
Amiga staging import failed in browser.

Import files synced: public_html/amiga/_import/ko2amiga_manifest.json + ko2amiga_*.sql
Failed on part: [N/24]
Error: [paste from run_import_ko2amiga.php apply page]

Please re-import into ko2amiga_db (Heidi/mysql) or check PHP limits.
```
