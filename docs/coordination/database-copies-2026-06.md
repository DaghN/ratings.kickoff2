# Database copies — June 2026 (Steve + local)

**Status:** **Local sandbox done** (Jun 2026). **Staging DB names confirmed** (Jun 2026): `kooldb1` / `kooldb2` via config1/config2.

**After setup (expected):** `ko2unity_work` / `ko2unity_baseline` have **prod table count** (~5 core tables, ~75k `ratedresults`) until you run expand/migrations on work. `ko2unity_db` keeps **project schema** (~19 tables) for browser work.

**Audience:** Dagh, Steve, Cursor agents.

**Platform (Steve boundary, dispatcher, ops folder, sim):** [`docs/ladder-ops-platform.md`](../ladder-ops-platform.md)

**Prepare / zero derived / simul vocabulary:** [`docs/work-db-prepare.md`](../work-db-prepare.md) (canonical).

---

## Vocabulary

| Term | Meaning |
|------|---------|
| **Ground truth** | Match facts (who, score, time; events later). Stored first; `game_id` boundary for derived step. Column/table list: [`docs/ground-truth-manifest.md`](../ground-truth-manifest.md). |
| **Derived truth** | Elo, milestones, aggregates, etc. — one post-game processor reading DB. Same manifest for sandbox vs prod boundaries. |
| **Dev DB** | `ko2unity_db` — browser, PHP, day-to-day feature work. |
| **Prod sandbox** | `ko2unity_baseline` + `ko2unity_work` — prod-shaped copy for migrate/sim; **not** the PHP site until cutover. |
| **Refresh work** | Clone baseline → work (script: `reset_local_work_db.ps1`). **Not** “zero derived.” |
| **Migrate work** | Apply `schema/migrations/` on work only. |
| **Zero derived** | Derived day-zero pre-game; ground truth kept. See [`work-db-prepare.md`](../work-db-prepare.md) §4. |

---

## Local — three databases (Laragon)

| Database | Role | Migrations? | PHP site? | Ladder / sim? |
|----------|------|-------------|-----------|---------------|
| **`ko2unity_db`** | **Dev** — current work | Yes (`schema/apply_local.ps1` default) | **Yes** (`ko2unitydb_config.php`) | `--target local` (default config) |
| **`ko2unity_baseline`** | Pristine prod snapshot | **Never** | **No** | **Never** |
| **`ko2unity_work`** | Disposable prod-shaped experiments | When you choose (`apply_schema_to_work.ps1`) | **`http://work.ratingskickoff.test/`** (after setup) | `--target sandbox --ini site/config/ladder-work.ini` |

**Safety:** Setup scripts **must not** `DROP` or import into `ko2unity_db`. The archived prod dump in `data/dumps/` is **sanitized at extract** (`CREATE DATABASE` / `USE` → `ko2unity_baseline` only). Raw `KOOL_DB.sql` from Steve's zip is **not** kept as the import target.

### Config files

| File | Purpose |
|------|---------|
| `site/config/ko2unitydb_config.php` | Router: **`ratingskickoff.test`** → `.local.php` → **`ko2unity_db`**; **`work.ratingskickoff.test`** → `_work.local.php` → **`ko2unity_work`** |
| `site/config/ladder-work.ini` | Python CLI sandbox → **`ko2unity_work`** (copy from `ladder-work.ini.example`) |

### One-time: create sandbox (does not touch dev)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
```

Requires `Downloads\KOOL_DB_Live.zip`. ~15–40 min. **Does not** change PHP config. **Does not** run migrations on work unless `-ApplyMigrationsToWork`.

Alias: `scripts\setup_local_prod_databases.ps1` (same script).

### Prepare work DB (typical)

**Preferred (v2):** [`docs/work-db-prepare.md`](../work-db-prepare.md) · [`docs/OPS_STANDARDS.md`](../OPS_STANDARDS.md)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
```

Fast path: `-ZeroOnly`. Legacy manual steps: work-db-prepare §3.4.

### Verify (read-only)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\verify_local_databases.ps1
powershell -ExecutionPolicy Bypass -File scripts\check_local_dev.ps1
```

### Local dual website (Jun 2026 — shipped)

**Decision:** Browse **`ko2unity_work`** at **`http://work.ratingskickoff.test/`** while **`http://ratingskickoff.test/`** stays on **`ko2unity_db`**. We **rejected** the older plan of temporarily changing `$database` in one PHP config file (“cut over config to work”) because it is easy to leave work enabled by mistake and it blocks parallel dev + sandbox use.

| Piece | Detail |
|-------|--------|
| **Router (git)** | `site/config/ko2unitydb_config.php` — hostname → `*.local.php` |
| **Setup** | `scripts\setup_laragon_work_site.ps1` + hosts line (often needs Administrator) |
| **Apache** | Laragon `ServerAlias *.ratingskickoff.test` — no second vhost file |
| **Docs** | [`LOCAL_DEV.md`](../LOCAL_DEV.md) § URLs |

**Smoke test (Jun 2026):** leaderboards on work URL show prod-snapshot ratings (~75k games) **until** prepare step 3 (zero derived). Dev URL unchanged.

**Setup steps:**

1. `scripts\setup_laragon_work_site.ps1` (hosts + `*_work.local.php`).
2. Open **`http://work.ratingskickoff.test/`** alongside **`http://ratingskickoff.test/`**.
3. Run **prepare** ([`work-db-prepare.md`](../work-db-prepare.md)), then **simul** when ready (`ladder run --target sandbox` or future ops CMDs).

### Future prod / staging cutover (later, deliberate)

**Not** the same as local dual URLs. Live games, Steve, `kooldb1`/`kooldb2`, PHP `ops/` — [`ladder-ops-platform.md`](../ladder-ops-platform.md).

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
| Refresh work from baseline | Local: `reset_local_work_db.ps1`. Staging: clone **`kooldb1`** ← **`kooldb2`** (Steve) |
| Prepare work (full) | [`work-db-prepare.md`](../work-db-prepare.md) — refresh → migrate → zero derived |
| Re-import pristine prod anchor | Local: sanitized dump → `ko2unity_baseline` only (rare) |

**Never** migrate, zero derived, or replay on **`ko2unity_baseline`** / **`kooldb2`**.

---

## Related

- [`docs/work-db-prepare.md`](../work-db-prepare.md) — prepare pipeline, simul modes, zero derived checklist
- [`docs/ladder-ops-platform.md`](../ladder-ops-platform.md) — ops folder, dispatcher, deploy
- `data/README.md` — dump paths, step list
- `docs/LOCAL_DEV.md` — Laragon
- `scripts/ladder/README.md` — CLI including sandbox target
- `docs/replay-v1-scope-and-reset.md` — core ladder column manifest
