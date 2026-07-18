# Agent handoff — staging export readiness

## Goal

Refresh and verify the `ko2amiga_db` staging export/import package for the current Amiga tournament schema (entrants + lifecycle columns) and data.

## Classification

`migration` / `internal ops`

## Files changed

- `site/public_html/amiga/_import/ko2amiga_manifest.json` — regenerated part list (23 parts)
- `scripts/amiga/README.md` — part count 22 → 23
- `site/public_html/amiga/_import/README.md` — part numbering and count 22 → 23
- `docs/amiga-staging-handoff.md` — part count, payload range, expected row counts
- `docs/archive/orchestration/agent-handoffs/2026-06-07-007-staging-export-readiness.md` — this handoff

## Export command run and result

```powershell
git status --short --branch
# ## main...origin/main

powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
# Chunking games/ratings: max id 27422 (chunk size 5000)
# Wrote 23 part files + manifest to ...\site\public_html\amiga\_import
# Full dump: ...\ko2amiga_db.sql
# Archive copy: ...\data\amiga\exports\ko2amiga_db-2026-06-07.sql
# exit 0
```

## Manifest/part count before and after

| | Parts | Notes |
|---|------|-------|
| **Before** | 18 | Stale pre-entrants layout (`ko2amiga_02_tournaments.sql`, games from part 04, no format templates or entrants) |
| **After** | 23 | Current script layout; extra games/ratings pair vs prior 22-part docs because `MAX(amiga_games.id)=27422` (was 27408) |

After manifest includes `ko2amiga_05_entrants.sql` and FK-safe order: format templates → tournaments → players → entrants → stages → stage players → fixtures → games/ratings chunks → stats → standings → catalog stats.

## Which generated files were committed and why

- **`ko2amiga_manifest.json`** — tracked in git; importer and agents rely on the committed part list for preview/apply expectations.

## Which generated files were intentionally left untracked/ignored and why

Per `.gitignore` (`site/public_html/amiga/_import/*.sql`):

- All `ko2amiga_*.sql` part files and `ko2amiga_db.sql` — large generated dumps; deployed via WinSCP with `public_html/`, not version control.
- `data/amiga/exports/ko2amiga_db-2026-06-07.sql` — local archive copy from export script.

## Verification performed

```powershell
git status --short --branch   # after export
#  M site/public_html/amiga/_import/ko2amiga_manifest.json

# Schema (ko2amiga_01_schema.sql): tournament_entrants, lifecycle_status, started_at, completed_at present

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# lifecycle_status=running count=5; completed count=603
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

mysql -u root -N -B -e "SELECT COUNT(*) players, (SELECT COUNT(*) FROM ko2amiga_db.amiga_games) games, (SELECT COUNT(*) FROM ko2amiga_db.tournament_entrants) entrants FROM ko2amiga_db.amiga_players"
# 473  27416  19
```

## Risks/limitations/not verified

- **Staging browser import not run** — local export only; Dagh must WinSCP-sync `_import/` SQL parts + manifest, then preview/apply on staging.
- **Part count grows with game volume** — games/ratings chunk pairs scale with `MAX(amiga_games.id)`; docs now say 23 for current DB but will increase after more fixture-backed games.
- **Smoke tournaments** — local DB includes generated lifecycle smoke tournaments (5 `running`, 19 entrants); left as-is per non-goals.
- **Staging server state** — still on pre-refresh export until sync + import.

## Commit hash and push target

- Commit: `3872265` — Refresh Amiga staging export manifest.
- Branch: `main`
- Remote: `origin/main`

## Recommended next steps

1. WinSCP sync `site/public_html/` (including all `_import/ko2amiga_*.sql` + updated manifest).
2. Staging preview: `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=YOUR_OPS_PASSWORD` — confirm `parts: 23`.
3. Apply import with `&apply=1&part=1`; spot-check rating, profile, tournaments, and `/amiga/ops/fixtures.php` lifecycle panel on a generated tournament.
4. After next significant schema change, re-run `scripts/export_ko2amiga_db.ps1` before staging refresh.
