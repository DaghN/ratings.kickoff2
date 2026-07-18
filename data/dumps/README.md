# Database dumps (working extracts)

**Policy:** intentional **continuity backups** of database state **belong in git** when sealed as milestones (Amiga: `data/amiga/checkpoints/`, `data/amiga/day0/`; online: same habit wanted). See [`PROJECT_BRIEF.md`](../../PROJECT_BRIEF.md) and repo [`README.md`](../../README.md) Continuity.

This folder holds **working / local** online extracts. Contents are often **gitignored** until a sealed online checkpoint habit exists — that is hygiene for large scratch dumps, **not** “databases must never be committed.”

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