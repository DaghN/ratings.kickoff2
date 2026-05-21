# Local data (not in Git)

This folder holds **local-only** database artefacts for sandbox work on your PC.

## Database dump

| File | Description |
|------|-------------|
| `dumps/ko2unity_db-2026-05-20.sql` | HeidiSQL export of dev (~May 2026). Creates database **`ko2unity_db`** (MariaDB 10.11.7). |

**Do not commit** files under `dumps/`. They may contain account-related data and are large (~600 MB).

**Tables in this dump:** `ratedresults`, `playertable`, `resulttable` only. **`generalstatstable` is not in the `.sql` file** (not removed by import — ask Steve for a fuller export if you need local `server1.php` records).

## Import into Laragon (one-time)

**Note:** Recent Laragon versions often **do not** show **Menu → MySQL → Import**. That is normal. Use one of the methods below.

1. **Start All** in Laragon (MySQL must be green/running).

2. Pick **one** import method:

   **A — Repo script (easiest)** — from project root in PowerShell:

   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts\import_local_ko2unity_db.ps1
   ```

   Uses `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` when present. Takes several minutes.

   **B — Laragon Terminal** — **Menu → Laragon → Terminal** (Cmder), then:

   ```bat
   mysql -u root < "C:\Users\daghn\Desktop\Online and Amiga 500 ELO\data\dumps\ko2unity_db-2026-05-20.sql"
   ```

   (Adjust the path if your repo lives elsewhere.) The dump includes `CREATE DATABASE` / `USE ko2unity_db`.

   **C — HeidiSQL** (bundled with Laragon) — **Menu → MySQL → HeidiSQL**, connect as **root** (Laragon default password is often empty), then **File → Load SQL file** (or Run SQL file), select `dumps/ko2unity_db-2026-05-20.sql`, execute. Fine for ~600 MB; can take a while.

   **Not recommended:** phpMyAdmin/Adminer for this size (timeouts).

3. Verify:

   ```sql
   USE ko2unity_db;
   SELECT COUNT(*) AS games FROM ratedresults;
   SELECT COUNT(*) AS players FROM playertable;
   SELECT id, GamesPlayed FROM generalstatstable;
   ```

## PHP site config (gitignored)

Copy `site/config/ko2unitydb_config.php.example` to `site/config/ko2unitydb_config.php` and set `$database = 'ko2unity_db'` (and Laragon credentials).

## Python ladder scripts (later)

Python ladder uses the same DB as PHP (`site/config/ko2unitydb_config.php`). Staging: **`docs/STAGING_REPLAY.md`**.

## Restore after a bad reset/replay

Drop and re-import the same `.sql` file into a fresh `ko2unity_db` (keep the pristine dump unchanged).
