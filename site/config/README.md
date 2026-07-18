# `site/config/` — local database credentials

**Not in Git (except router + examples):** real passwords live in `*.local.php`.

| File | Git | Purpose |
|------|-----|---------|
| `ko2unitydb_config.php` | Yes | **Router** — hostname picks dev vs work local file |
| `ko2unitydb_config.local.php` | No | Dev site → **`ko2unity_db`** (`ratingskickoff.test`) |
| `ko2unitydb_config_work.local.php` | No | Work site → **`ko2unity_work`** (`work.ratingskickoff.test`) |
| `ko2amiga_config.php` | Yes | Amiga realm router — loaded via `../../config/ko2amiga_config.php` from `public_html/amiga/` and APIs |
| `ko2amiga_config.local.php` | No | Amiga CLI + PHP + staging → **`ko2amiga_work`** / oracle as configured |
| `amiga_ops_password.php` | Yes | Loader for Amiga import / export / fixtures gate |
| `amiga_ops_password.local.php` | No | Ops password value (never commit). Copy from `.example`. Same file on staging under `site/config/`. |
| `ladder-work.ini` | No | Python CLI sandbox → **`ko2unity_work`** |
| `ladder.ini` | No | Optional Python override |

**Setup:** `scripts\setup_laragon_work_site.ps1`  
**Why two browser URLs:** [`docs/LOCAL_DEV.md`](../../docs/LOCAL_DEV.md) § Why two URLs
