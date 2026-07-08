# Staging pull SQL archives

Written by `scripts/pull_ko2amiga_from_staging.ps1`. Gitignored `*.sql` files.

| File | Retention |
|------|-----------|
| `ko2amiga_staging_pull_latest.sql` | Overwritten each pull (import source) |
| `ko2amiga_staging_pull_YYYY-MM-DD_HHmmss.sql` | One timestamped archive per pull run |

Manifest: `data/amiga/modern/staging-sync-last.json` (gitignored).
