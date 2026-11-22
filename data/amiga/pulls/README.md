# Staging pull SQL archives

Written by `scripts/pull_ko2amiga_from_staging.ps1`. Gitignored `*.sql` files.

| File | Retention |
|------|-----------|
| `ko2amiga_staging_pull_latest.sql` | Overwritten each pull (import source) |
| `ko2amiga_staging_pull_YYYY-MM-DD_HHmmss.sql` | One timestamped archive per pull run |

Records (gitignored, in `data/amiga/modern/`): `staging-sync-last.json` = **push baseline** (full pull into `ko2amiga_work`, or a push that verified MATCH); `staging-sidepull-last.json` = last side-pull. The push gate needs the baseline's **timestamped** dump: delete it and the next push will ask for a full pull.
