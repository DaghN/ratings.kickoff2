# Agent handoff — entrant backfill and verification cleanup

## Goal

Add a safe internal backfill/repair path so existing generated fixture-backed tournaments missing `tournament_entrants` rows can be brought into entrant integrity, and make global `fixtures verify-entrants` pass in the local database.

## Classification

`foundation` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — `backfill-entrants` CLI, `backfill_tournament_entrants`, `plan_entrant_backfill`, eligibility helpers, conservative `insert_entrant_if_missing`
- `docs/amiga-data-contract.md` — backfill command documentation
- `scripts/amiga/README.md` — apply/verify/backfill commands
- `docs/orchestration/agent-handoffs/2026-06-07-002-entrant-backfill-verification.md` — this handoff

## Schema/data implications

- No schema changes.
- Backfill inserts `registered` rows into existing `tournament_entrants` only when `(tournament_id, player_id)` is absent.
- Existing entrant rows (including `withdrawn` / `replaced`) are never updated or overwritten.
- Backfilled rows set `note = 'backfilled by fixtures backfill-entrants'` for auditability.
- Eligibility is limited to tournaments with `source_id IS NULL` and `format_overrides.generated_by` prefix matching approved fixture tooling (`scripts.amiga.tournament_builder` or `site.public_html.amiga.ops.fixtures`).

## Behavior added or changed

- `python -m scripts.amiga fixtures backfill-entrants` scans all eligible generated tournaments and inserts missing entrants from stage players and fixture participants.
- `--tournament-id N` scopes repair to one tournament (errors if not eligible).
- `--dry-run` reports planned inserts without committing.
- Seed assignment: prefer stage-player seed from earliest stage; append after known seeds for fixture-only participants; leave `NULL` when a proposed stage seed collides with an existing entrant seed.
- `cleanup-generated` eligibility check refactored to shared `_is_eligible_generated_tournament` helper.

## Tests/checks run with exact commands and results

```powershell
python -m py_compile scripts/amiga/tournament_fixtures.py
# exit 0

python -m scripts.amiga fixtures verify
# tournament_entrants=0 (before backfill)
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# exit 1 — 52 FAIL lines for tournaments 610, 611, 614 (stage players and fixture participants missing active entrants)

python -m scripts.amiga fixtures backfill-entrants --dry-run
# DRY RUN: rolled back
# tournaments_scanned=3 tournaments_changed=3 entrants_inserted=11
# tournament 610: 4 players; 611: 4 players; 614: 3 players

python -m scripts.amiga fixtures backfill-entrants
# tournaments_scanned=3 tournaments_changed=3 entrants_inserted=11
# committed

python -m scripts.amiga fixtures verify-entrants
# tournament_entrants=11
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures list-entrants --tournament-id 610
# 4 registered entrants with seeds 1–4 and backfill note

python -m scripts.amiga fixtures backfill-entrants --dry-run
# tournaments_scanned=3 tournaments_changed=0 entrants_inserted=0 (idempotent)
```

## Data repair performed locally

| Tournament ID | Name | Entrants inserted |
|---------------|------|-------------------|
| 610 | Browser Fixture Smoke | 4 |
| 611 | well well well | 4 |
| 614 | well the 2nd | 3 |

**Total:** 11 entrants inserted across 3 generated tournaments.

## Risks/limitations/not verified

- Does not backfill imported Access tournaments (`source_id` set) — intentional.
- Does not change `withdrawn` / `replaced` rows; if a stage player exists only as withdrawn, verify would still fail (no such cases in local DB).
- Does not create players or change public UI.
- Staging re-export not run; staging DB not repaired in this session.
- PHP group-knockout browser path still not implemented.

## Commit hash and push target

- Implementation commit: `1f7f77238f4e2047fc2c7be17427506805bbacee`
- Push target: `origin/main`

## Recommended next steps

1. Run `fixtures backfill-entrants --dry-run` on staging before applying if similar pre-foundation generated tournaments exist there.
2. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import to publish entrant data.
3. Add entrant status change ops (`withdraw`, `replace`) when replacement-player policy is defined.
4. Wire entrant checks into `set_fixture_players` / fixture result entry for fail-fast validation.
