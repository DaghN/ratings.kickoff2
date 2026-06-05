# `site/config/` — local database credentials

**Not in Git (except router + examples):** real passwords live in `*.local.php`.

| File | Git | Purpose |
|------|-----|---------|
| `ko2unitydb_config.php` | Yes | **Router** — hostname picks dev vs work local file |
| `ko2unitydb_config.local.php` | No | Dev site → **`ko2unity_db`** (`ratingskickoff.test`) |
| `ko2unitydb_config_work.local.php` | No | Work site → **`ko2unity_work`** (`work.ratingskickoff.test`) |
| `ko2amiga_config.php` | Yes | Amiga realm router (includes local file) |
| `ko2amiga_config.local.php` | No | Amiga CLI + PHP → **`ko2amiga_db`** (`/amiga/rating.php`) |
| `ladder-work.ini` | No | Python CLI sandbox → **`ko2unity_work`** |
| `ladder.ini` | No | Optional Python override |

**Setup:** `scripts\setup_laragon_work_site.ps1`  
**Why two browser URLs:** [`docs/LOCAL_DEV.md`](../../docs/LOCAL_DEV.md) § Why two URLs
