# Agent handoff — tournament entrants foundation

## Goal

Add tournament-level entrant/registration ground truth (`tournament_entrants`) for future live Amiga tournaments: schema, import/export integration, internal builder population, and CLI verify/list helpers. No public registration UI.

## Classification

`foundation`

## Files changed

- `scripts/amiga/sql/007_tournament_entrants.sql` — new DDL
- `scripts/amiga/import_access.py` — schema apply, drop/truncate order
- `scripts/amiga/tournament_fixtures.py` — `add_tournament_entrant`, `list_entrants`, `audit_entrant_integrity`, CLI `list-entrants` / `verify-entrants`
- `scripts/amiga/tournament_builder.py` — populate entrants in kitchen marathon + group knockout builders; verify-built entrant count
- `scripts/export_ko2amiga_db.ps1` — export table list + `ko2amiga_05_entrants.sql` part (renumbered stage/fixture parts)
- `site/public_html/amiga/ops/fixtures.php` — kitchen-marathon browser create path populates entrants
- `docs/amiga-data-contract.md` — table register + entrants section
- `scripts/amiga/README.md` — apply/verify commands

## Schema/data implications

- New ground table `tournament_entrants`: `tournament_id`, `player_id`, `seed_no`, `status` (`registered` | `withdrawn` | `replaced`), optional `note`, `created_at`.
- Unique `(tournament_id, player_id)`; index `(tournament_id, seed_no)`.
- FKs to `tournaments` and `amiga_players` with `ON DELETE CASCADE`.
- **`display_name_snapshot` deferred:** canonical names stay in `amiga_players`; snapshot would drift on rename and replacement-player modeling belongs in a later slice. Optional `note` covers admin comments.
- Legacy Access import leaves entrants empty (same as stages/fixtures). Full import truncates `tournament_entrants`.
- Staging export adds part `ko2amiga_05_entrants.sql`; fixtures part is now `08`, games chunks start at `09`.

## Behavior added or changed

- `create_kitchen_marathon_tournament` and `create_group_knockout_tournament` insert all selected players as `registered` entrants before stage players.
- PHP `amiga_fixture_create_kitchen_tournament` mirrors the same entrant inserts.
- `python -m scripts.amiga fixtures list-entrants --tournament-id N` lists entrants with seed/status.
- `python -m scripts.amiga fixtures verify-entrants` checks globally; `--tournament-id N` scopes to one tournament.
- Verification fails when stage players or fixture participants are not active (`registered`) entrants.

## Tests/checks run with exact commands and results

```powershell
python -m py_compile scripts/amiga/import_access.py scripts/amiga/tournament_fixtures.py scripts/amiga/tournament_builder.py
# exit 0

Get-Content scripts/amiga/sql/007_tournament_entrants.sql | mysql -u root ko2amiga_db
mysql -u root -N -B -e "SHOW TABLES LIKE 'tournament_entrants'" ko2amiga_db
# tournament_entrants

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Entrant Smoke Kitchen" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4 --dry-run
python -m scripts.amiga build-tournament create-group-knockout --name "Entrant Smoke Cup" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4 --group-count 2 --dry-run
# DRY RUN: rolled back; created structure OK

python -m scripts.amiga build-tournament smoke-fixture-result --player-ids 1,2
# DRY RUN rolled back; smoke OK

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Scoped Entrant Check" --event-date 2026-06-07 --player-ids 1,2
python -m scripts.amiga fixtures list-entrants --tournament-id 619
# 2 registered entrants
python -m scripts.amiga fixtures verify-entrants --tournament-id 619
# OK: tournament entrant integrity checks passed
python -m scripts.amiga fixtures cleanup-generated --tournament-id 619
# deleted
```

**Note:** Global `fixtures verify-entrants` (no `--tournament-id`) currently fails on pre-existing generated smoke tournaments (610, 611, 614) created before this slice. Scoped verify passes on newly built tournaments.

## Risks/limitations/not verified

- No backfill of entrants for existing generated tournaments; global verify will report those until cleaned up or backfilled manually.
- No entrant withdrawal/replacement ops CLI yet (schema supports status; writers not added).
- No public registration UI; website read path unchanged.
- Staging re-export not run in this session (`export_ko2amiga_db.ps1` updated but not executed).
- PHP group-knockout browser path not implemented (Python only).

## Commit hash and push target

- Implementation commit: `5499c1124e793526e7030dc1d463167d3cc847ea`
- Handoff commit: (this file)
- Push target: `origin/main`

## Recommended next steps

1. Backfill or cleanup legacy generated tournaments missing entrants so global `verify-entrants` is green in dev/staging.
2. Add entrant status change ops (`withdraw`, `replace`) when replacement-player policy is defined.
3. Wire entrant checks into `set_fixture_players` / fixture result entry for fail-fast validation.
4. KOA newcomer naming policy + player creation (separate job).
5. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import to publish `ko2amiga_05_entrants.sql`.
