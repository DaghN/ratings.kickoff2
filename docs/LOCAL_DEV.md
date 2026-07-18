# Local development on Dagh’s PC

**Audience:** Dagh and Cursor agents. **Goal:** one place for Laragon paths, URLs, DB import, and common fixes on this machine.

**Staging/production** deploy is still WinSCP → `ratings.kickoff2.com`. This doc is **local only**.

### Local vs staging vs prod (DB)

| | Local | Staging | Production |
|---|--------|---------|------------|
| DB name | **`ko2unity_db`** (dev); sandbox: `ko2unity_work` + `ko2unity_baseline` | **`kooldb1`** / **`kooldb2`** (config1/config2) — [`database-copies-2026-06.md`](coordination/database-copies-2026-06.md) | Steve-managed live DB |
| Live game writes | No | **No** | **Yes** (**PHP ops** since **2026-07-18**; C++ derived retired) |
| Site code updates | Edit in repo | WinSCP sync | Steve / agreed deploy |
| Typical DB refresh | Re-import dump | Steve: replay / SQL / dump | Continuous |

See **`docs/coordination/database-copies-2026-06.md`** (DB names) and **`docs/STATUS_PAGE_DATA.md`** (snapshot vs live).

---

## Machine facts (verified May 2026)

| Item | Value |
|------|--------|
| Laragon root | **`C:\laragon`** |
| Laragon GUI | `C:\laragon\laragon.exe` |
| MySQL client | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| Apache httpd | `C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe` |
| Apache config includes | `C:\laragon\etc\apache2\sites-enabled\*.conf` |
| Web junction | `C:\laragon\www\ratingskickoff` → repo **`site\public_html`** |
| Hosts entry | `127.0.0.1 ratingskickoff.test` (#laragon magic!) |
| Local dev DB | **`ko2unity_db`** (PHP config) |
| Prod sandbox | **`ko2unity_baseline`** + **`ko2unity_work`** — see `data/README.md` |
| Prod SQL dump (working; often gitignored) | `data/dumps/ko2unity_prod-2026-06-02.sql` — sealed continuity backups **do** belong in git ([`README.md`](../README.md) Continuity) |
| PHP DB config | Router: `site/config/ko2unitydb_config.php` · credentials: `*.local.php` (gitignored) |
| Python DB config | Same as PHP: `site/config/ko2unitydb_config.php` (optional `ladder.ini` override) |
| Examples (committed) | `site/config/*.example` |

**Agents:** If Laragon is “not found”, check **`C:\laragon` first** — earlier sessions may have searched before MySQL was started or paths were wrong.

---

## URLs (do not confuse)

| URL | Database | Use |
|-----|----------|-----|
| **`http://ratingskickoff.test/`** | **`ko2unity_db`** | Daily dev — full schema, cosmetics, features |
| **`http://work.ratingskickoff.test/`** | **`ko2unity_work`** | Prod-shaped sandbox — replay/post-game browse |

Both use the **same** `site/public_html` code. Only the hostname selects the DB ([`site/config/ko2unitydb_config.php`](../site/config/ko2unitydb_config.php) router → `*.local.php` files).

**One-time setup for work URL:**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_laragon_work_site.ps1
```

Adds `127.0.0.1 work.ratingskickoff.test` to **hosts** (may need **Run as administrator**). Laragon’s `auto.ratingskickoff.test.conf` already has `ServerAlias *.ratingskickoff.test` — no extra Apache vhost file.

**Config files:**

| File | Git | Points at |
|------|-----|-----------|
| `ko2unitydb_config.php` | Yes (router) | picks local file from hostname |
| `ko2unitydb_config.local.php` | No | `ko2unity_db` |
| `ko2unitydb_config_work.local.php` | No | `ko2unity_work` |

Copy from `*.example` if missing. **CLI Python ladder** still defaults to **dev** via `.local.php`; use `--ini site/config/ladder-work.ini` for work.

### Why two URLs (not flipping `$database` in one config file)

**Shipped Jun 2026.** We deliberately **do not** “cut over” the PHP site by editing `ko2unitydb_config.php` to point at `ko2unity_work` and back.

| Approach | Problem |
|----------|---------|
| **Flip `$database` in a single config** | Easy to forget you left work enabled; dev cosmetics/features hit the wrong DB; agents assume one browser DB. |
| **Two hostnames + router** (chosen) | **`ratingskickoff.test`** and **`work.ratingskickoff.test`** are unambiguous bookmarks; dev and prod-shaped sandbox run **in parallel**. |

**Mental shortcut:**

- **Dev website** → `http://ratingskickoff.test/` → `ko2unity_db`
- **Work website** → `http://work.ratingskickoff.test/` → `ko2unity_work`

Same PHP tree; only hostname + gitignored `*.local.php` differ. **Future prod/staging cutover** (Steve, `kooldb1`, live games) is a separate decision — documented in [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) and [`ladder-ops-platform.md`](ladder-ops-platform.md), not “rename dev config and hope.”

**Verified (Jun 2026):** leaderboards on work URL show prod-snapshot ratings after hosts + `setup_laragon_work_site.ps1`.

---

## Daily workflow (after one-time setup below)

1. Open **Laragon** (your desktop shortcut is fine).
2. Click **Start All**.
3. Open **`http://ratingskickoff.test/`** (dev) and/or **`http://work.ratingskickoff.test/`** (sandbox) as needed.
4. **Stop All** — wait a few seconds; the site should stop loading (watchdog stops Apache if Laragon left it running). **Verified working May 2026.**

No PowerShell scripts are required for normal use after dual-URL setup.

---

## One-time setup: Apache + Avast (important)

**Symptom:** `ratingskickoff.test` unreachable after Laragon **Stop All → Start All**, while MySQL still works.

**Cause (confirmed on this PC):** **Avast** injects `SSLKEYLOGFILE` pointing at `\\.\aswMonFltProxy\...`. Apache then fails immediately with `AH10226` / `AH00016` in:

`C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\logs\error_log`

Only **MySQL** starts; nothing listens on port **80**.

**Fix (run once from repo root):**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_laragon_apache_fix.ps1
```

This installs:

1. A tiny **`httpd.exe` launcher** (clears `SSLKEYLOGFILE`, runs `httpd-real.exe`).
2. An **Apache watchdog** in `C:\laragon\usr\apache-watchdog.ps1` (via `usr\Procfile` **autorun**) — when **Stop All** turns off MySQL but leaves Apache on port 80, it stops Apache within a few seconds so the site actually goes down.

**Restart Laragon once** after setup so the watchdog is loaded. Requires **gcc** (MinGW) on PATH.

Also in Laragon (once): **Menu → Preferences → Services & Ports** → ensure **Apache** is **enabled** (checked).

**After a Laragon Apache upgrade** (new `httpd.exe` overwrites the shim), run `setup_laragon_apache_fix.ps1` again.

**Optional Avast-side fix:** exclude `C:\laragon\bin\apache\` and `C:\laragon\laragon.exe` from HTTPS scanning so `SSLKEYLOGFILE` is not injected.

---

## Optional diagnostic

If something still fails:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\check_local_dev.ps1
```

Checks MySQL, port 80, HTTP 200, and DB row counts.

**Note:** Laragon **Menu → MySQL** often has **no “Import”** item — use `scripts/import_local_ko2unity_db.ps1` or HeidiSQL (see `data/README.md`).

---

## Databases

| Purpose | Command / doc |
|---------|----------------|
| **Dev** (browser) | `ko2unity_db` — `check_local_dev.ps1` |
| **Prod sandbox** (sim) | `setup_local_prod_sandbox.ps1` — **`data/README.md`** |
| **Status of all three** | `verify_local_databases.ps1` |

```powershell
powershell -ExecutionPolicy Bypass -File scripts\check_local_dev.ps1
powershell -ExecutionPolicy Bypass -File scripts\verify_local_databases.ps1
```

**Schema migrations (indexes, etc.):** after import, run once:

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

(Wrapper: `scripts\apply_ratedresults_player_indexes.ps1` — local helper that applies only `001_ratedresults_player_indexes.sql`.)

Then spot-check `http://ratingskickoff.test/player/profile.php?id=237` — profile should load in ~1s for heavy players or ~100ms for light ones (was multi-second before indexes).

**Staging / production (no terminal):** WinSCP copy **`scripts/throwaway_ratedresults_player_indexes.php`** → server **`public_html/`** only when needed; browser preview/apply per file header; **delete from server immediately after**. This file is **not** in `site/public_html/` (avoids accidental sync).

---

## What’s in the local dump (important)

Dump file: **`data/dumps/ko2unity_db-2026-05-20.sql`** (HeidiSQL export from **`ts-joshua`**, May 2026).

| Table | In dump? | Notes |
|-------|----------|--------|
| `ratedresults` | Yes | ~74,870 games |
| `playertable` | Yes | 475 rows; still has **`KungFu*`** columns (pre–column-drop snapshot) |
| `resulttable` | Yes | Wide match log (~81k rows): **live/shelved games** for hub Status (`docs/STATUS_PAGE_DATA.md`). Not used by ladder chart APIs. |
| **`generalstatstable`** | **Not in `.sql` file** | Row `id=1` is created/filled by **PHP ops simul** or post-game on work DB. Frozen dev `ko2unity_db`: re-import dump or use work sandbox — not retired replay CLIs ([`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md)). |

**Hub Status page:** same DB as legacy [joshua status.php](https://joshua.kickoff2.net/status.php) — build locally without a separate API; see **`docs/STATUS_PAGE_DATA.md`**. `IsOnline` / in-progress games are stale on local and staging (no live writes); prod only for “tonight” truth.

PHP ops simul can fill `generalstatstable`; rebuild later via work DB prepare + simul if needed.

---

## PHP config path

Site code loads:

```text
{DOCUMENT_ROOT}/../config/ko2unitydb_config.php
```

With the junction, that resolves to **`site/config/`** in the repo. Copy from **`ko2unitydb_config.php.example`** if missing.

---

## Agent workflow (local)

1. Assume **Start Menu Laragon → Start All** is enough if `setup_laragon_apache_fix.ps1` was run; use **`check_local_dev.ps1`** only when diagnosing failures.
2. DB work: confirm `DATABASE()` = `ko2unity_db`.
3. Destructive scripts: **dry-run first**; keep pristine SQL dump for re-import.
4. **Secrets:** do **not** commit `site/config/ko2unitydb_config.php` / `*.local.php` / ops credential ini. **Working** `data/dumps/*.sql` stay untracked by default; **do not** treat that as “databases must never be in git” — sealed continuity backups belong in git ([`README.md`](../README.md) Continuity · [`PROJECT_BRIEF.md`](../PROJECT_BRIEF.md)). **Staging / cutover ladder work:** [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) + ops simul — **not** Laragon. [`STAGING_REPLAY.md`](STAGING_REPLAY.md) is an **archive redirect** only (May 2026 one-shot record).

---

## When local is good enough → Steve

Send: exact command, `DATABASE()` name, row counts before/after, last log lines. Staging uses **`ko2unitydb_config.php`** on server (not in Git).

---

## Related docs

- `data/README.md` — dump import
- `docs/STATUS_PAGE_DATA.md` — legacy status → table map; Phase B hub work
- `PROJECT_MEMORY.md` — deploy/staging logistics
- `docs/ladder-engine-plan.md` — Python replay plan
