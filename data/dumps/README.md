# Database dumps (gitignored contents)

Files here are **not in Git**. See [`data/README.md`](../README.md).

## Safe prod archive

| File | Safe? |
|------|--------|
| **`ko2unity_prod-2026-06-02.sql`** | **Yes** — sanitized at extract; `CREATE DATABASE` / `USE` → **`ko2unity_baseline`** only |

Create with:

```powershell
powershell -File scripts\extract_prod_dump.ps1
```

## Unsafe

| File | Risk |
|------|------|
| Raw `KOOL_DB.sql` from Steve's zip | Creates **`ko2unity_db`** — would overwrite **dev** |
| `*.unsanitized.bak` | Same as raw export |

Never import raw Steve exports into Laragon without running `scripts\sanitize_prod_dump.ps1` first.
