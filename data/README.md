# Local data (not in Git)

**Inventory:** [`docs/coordination/database-copies-2026-06.md`](../docs/coordination/database-copies-2026-06.md)

---

## Three local databases

| MySQL name | You use it for |
|------------|----------------|
| **`ko2unity_db`** | Browser / PHP — **daily dev** (unchanged by sandbox setup) |
| **`ko2unity_baseline`** | Frozen prod copy — **never** migrate or replay |
| **`ko2unity_work`** | Prod-shaped sim, expand, ladder experiments |

---

## Prod snapshot file

| File | Source |
|------|--------|
| `dumps/ko2unity_prod-2026-06-02.sql` | `Downloads\KOOL_DB_Live.zip` → `KOOL_DB.sql` (~624 MB) |

**Sanitized on extract:** `extract_prod_dump.ps1` rewrites `CREATE DATABASE` / `USE` to **`ko2unity_baseline`** only. The file in `dumps/` is safe to import (it will not create dev `ko2unity_db`). Prefer **`setup_local_prod_sandbox.ps1`** for the full baseline+work flow.

**Staging archive (optional):** `Downloads\kooldb.zip` — not needed for sandbox setup.

---

## Step-by-step: prod sandbox (one time)

1. Laragon → **Start All**.
2. Confirm zip: `C:\Users\daghn\Downloads\KOOL_DB_Live.zip`.
3. Repo root PowerShell:

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
powershell -ExecutionPolicy Bypass -File scripts\setup_local_prod_sandbox.ps1
```

4. When finished:

```powershell
copy site\config\ladder-work.ini.example site\config\ladder-work.ini
powershell -ExecutionPolicy Bypass -File scripts\verify_local_databases.ps1
```

5. Browser still uses **`ko2unity_db`** — no config change.

**Optional later:** `-ApplyMigrationsToWork` on setup, or `scripts\apply_schema_to_work.ps1` when schema expand is ready.

---

## Legacy dev dump (May 2026)

`dumps/ko2unity_db-2026-05-20.sql` — import only via `scripts\import_local_ko2unity_db.ps1` if you need to **rebuild dev** from scratch (separate from prod sandbox).

---

## Reset work DB (minutes)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\reset_local_work_db.ps1
```

---

## PHP / Python config

| Config | Database |
|--------|----------|
| `site/config/ko2unitydb_config.php` | `ko2unity_db` |
| `site/config/ladder-work.ini` | `ko2unity_work` |
