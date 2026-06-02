# Database copies — June 2026 (Steve + local)

**Status:** **Local sandbox done** (Jun 2026). **Staging DB names confirmed** (Jun 2026): `kooldb1` / `kooldb2` via config1/config2.

**After setup (expected):** `ko2unity_work` / `ko2unity_baseline` have **prod table count** (~5 core tables, ~75k `ratedresults`) until you run expand/migrations on work. `ko2unity_db` keeps **project schema** (~19 tables) for browser work.

**Audience:** Dagh, Steve, Cursor agents.

---

## Vocabulary

| Term | Meaning |
|------|---------|
| **Ground truth** | Match facts (who, score, time; events later). Stored first; `game_id` boundary for derived step. |
| **Derived truth** | Elo, milestones, aggregates, etc. — one post-game processor reading DB. |
| **Dev DB** | `ko2unity_db` — browser, PHP, day-to-day feature work. |
| **Prod sandbox** | `ko2unity_baseline` + `ko2unity_work` — prod-shaped copy for expand/sim; **not** the PHP site until cutover. |

---

## Local — three databases (Laragon)

| Database | Role | Migrations? | PHP site? | Ladder / sim? |
|----------|------|-------------|-----------|---------------|
| **`ko2unity_db`** | **Dev** — current work | Yes (`schema/apply_local.ps1` default) | **Yes** (`ko2unitydb_config.php`) | `--target local` (default config) |
| **`ko2unity_baseline`** | Pristine prod snapshot | **Never** | **No** | **Never** |
| **`ko2unity_work`** | Disposable prod-shaped experiments | When you choose (`apply_schema_to_work.ps1`) | **No** until cutover | `--target sandbox --ini site/config/ladder-work.ini` |

**Safety:** Setup scripts **must not** `DROP` or import into `ko2unity_db`. The archived prod dump in `data/dumps/` is **sanitized at extract** (`CREATE DATABASE` / `USE` → `ko2unity_baseline` only). Raw `KOOL_DB.sql` from Steve's zip is **not** kept as the import target.

### Config files

| File | Purpose |
|------|---------|
| `site/config/ko2unitydb_config.php` | PHP + default Python → **`ko2unity_db`** |
| `site/config/ladder-work.ini` | Python sandbox only → **`ko2unity_work`** (copy from `ladder-work.ini.example`) |

### One-time: create sandbox (does not touch dev)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
```

Requires `Downloads\KOOL_DB_Live.zip`. ~15–40 min. **Does not** change PHP config. **Does not** run migrations on work unless `-ApplyMigrationsToWork`.

Alias: `scripts\setup_local_prod_databases.ps1` (same script).

### After bad replay/sim on work only

```powershell
powershell -ExecutionPolicy Bypass -File scripts\reset_local_work_db.ps1
```

### When expand schema is ready (work only)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\apply_schema_to_work.ps1
```

### Verify (read-only)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\verify_local_databases.ps1
powershell -ExecutionPolicy Bypass -File scripts\check_local_dev.ps1
```

### Cutover to prod-shaped site (later, deliberate)

1. Parity/sim checklist passed on `ko2unity_work`.
2. Change `ko2unitydb_config.php` → `$database = 'ko2unity_work'`.
3. Smoke-test `http://ratingskickoff.test/`.
4. Keep or drop `ko2unity_db` as archive.

---

## Archive files (not in Git)

| File | Location |
|------|----------|
| **Prod** | `data/dumps/ko2unity_prod-2026-06-02.sql` from `Downloads\KOOL_DB_Live.zip` |
| **Staging (optional)** | `Downloads\kooldb.zip` → `kooldb.sql` (2026-06-02); not imported locally by default |
| **Legacy dev** | `data/dumps/ko2unity_db-2026-05-20.sql` — old dev import; not prod |

---

## Staging server (`ratings.kickoff2.com`)

Steve (2026-06-02): two prod-shaped copies on the staging server. Config paths are beside `public_html` (same layout as legacy `config/ko2unitydb_config.php`).

| Config file (on server) | `$database` | Intended role (mirror local) |
|-------------------------|-------------|------------------------------|
| `config/ko2unitydb_config1.php` | **`kooldb1`** | **Work** — experiments, replay, schema expand |
| `config/ko2unitydb_config2.php` | **`kooldb2`** | **Reset copy** — pristine second prod copy; clone source for refreshing `kooldb1` |

**Live staging site PHP:** which file the vhost includes is not recorded here — confirm with Steve (often still default `config/ko2unitydb_config.php` → legacy **`kooldb`** from May 2026 work). WinSCP deploys **PHP only**; DB switching is manual / Steve.

**Legacy (May 2026):** single staging DB **`kooldb`** — milestones, replay register, SCH/REP history. Do not assume `kooldb` still exists unless Steve confirms.

**No live game writes** on staging (prod server is separate).

### Local ↔ staging name map

| Local | Staging |
|-------|---------|
| `ko2unity_work` | `kooldb1` (work) |
| `ko2unity_baseline` | `kooldb2` (reset copy) |
| `ko2unity_db` | *(no staging equivalent — dev-only on PC)* |

---

## Environment quick reference

| Environment | Work / active experiments | Reset / pristine copy | Browser / daily dev |
|-------------|---------------------------|------------------------|---------------------|
| **Local** | `ko2unity_work` | `ko2unity_baseline` | `ko2unity_db` |
| **Staging** | `kooldb1` (config1) | `kooldb2` (config2) | N/A (use local dev) |
| **Production** | live DB (Steve) | N/A | N/A |

---

## Agents

| Task | Database |
|------|----------|
| Status, profile, cosmetics on local site | `ko2unity_db` |
| Prod-shaped replay / derived-truth sim | Local: `ko2unity_work` + `ladder-work.ini`. Staging: **`kooldb1`** (Steve / agreed SQL) |
| Reset work from pristine copy | Local: `reset_local_work_db.ps1`. Staging: refresh **`kooldb1`** from **`kooldb2`** (Steve) |
| Reset pristine prod anchor | Local: re-import sanitized dump to `ko2unity_baseline` only (rare) |

**Never** apply migrations or `scripts/ladder reset` on **`ko2unity_baseline`**.

---

## Related

- `data/README.md` — dump paths, step list
- `docs/LOCAL_DEV.md` — Laragon
- `scripts/ladder/README.md` — CLI including sandbox target
- `docs/replay-v1-scope-and-reset.md` — fact vs derived columns
