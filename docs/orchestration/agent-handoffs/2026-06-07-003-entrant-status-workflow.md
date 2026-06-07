# Agent handoff — entrant status workflow

## Goal

Add internal CLI support for entrant withdrawal and replacement while preserving tournament/fixture integrity for generated live-tournament tooling.

## Classification

`foundation` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — `withdraw_tournament_entrant`, `replace_tournament_entrant`, validation helpers, CLI `withdraw-entrant` / `replace-entrant`
- `docs/amiga-data-contract.md` — withdraw/replace command documentation
- `scripts/amiga/README.md` — apply/verify/withdraw/replace commands
- `docs/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md` — this handoff

## Schema/data implications

- No schema changes.
- Withdrawal sets `tournament_entrants.status = 'withdrawn'` and appends a deterministic admin note.
- Replacement sets old row `status = 'replaced'`, inserts new row `status = 'registered'` reusing the old `seed_no`.
- Both ops remove/update `tournament_stage_players` and scheduled unplayed `tournament_fixtures` so `verify-entrants` stays green.
- Limited to generated tournaments (`source_id IS NULL` and `format_overrides.generated_by` prefix from approved fixture tooling). Imported Access tournaments are refused.

## Behavior added or changed

- `python -m scripts.amiga fixtures withdraw-entrant --tournament-id N --player-id P [--note TEXT] [--dry-run]`
  - Requires existing `registered` entrant.
  - Refuses when the player has `amiga_games` or played/attached-game fixtures in the tournament.
  - Clears the player from scheduled unplayed fixture slots and removes stage-player rows (chosen over refuse-on-scheduled so withdrawal is usable and verification stays green).
- `python -m scripts.amiga fixtures replace-entrant --tournament-id N --old-player-id OLD --new-player-id NEW [--note TEXT] [--dry-run]`
  - Requires old `registered` entrant; new player must exist in `amiga_players` and not already be a tournament entrant.
  - Refuses when old player has games or played/attached-game fixtures.
  - Updates scheduled unplayed fixtures and stage players from old to new player; preserves old entrant row as `replaced`.
- Command names match the orchestration prompt (`withdraw-entrant`, `replace-entrant`).

## Tests/checks run with exact commands and results

```powershell
python -m py_compile scripts/amiga/tournament_fixtures.py
# exit 0

python -m scripts.amiga fixtures verify
# tournament_entrants=11
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Withdraw Smoke Kitchen" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=620

python -m scripts.amiga fixtures withdraw-entrant --tournament-id 620 --player-id 4 --note "smoke dry-run" --dry-run
# DRY RUN: rolled back; scheduled_fixtures_touched=3 fixture_slots_cleared=3 stage_player_rows_removed=1

python -m scripts.amiga fixtures withdraw-entrant --tournament-id 620 --player-id 4 --note "smoke real"
# committed; status=withdrawn

python -m scripts.amiga fixtures verify-entrants --tournament-id 620
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures cleanup-generated --tournament-id 620
# deleted

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Replace Smoke Kitchen" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=621

python -m scripts.amiga fixtures replace-entrant --tournament-id 621 --old-player-id 4 --new-player-id 5 --note "smoke dry-run" --dry-run
# DRY RUN: rolled back; fixture_slots_updated=3 stage_player_rows_updated=1

python -m scripts.amiga fixtures replace-entrant --tournament-id 621 --old-player-id 4 --new-player-id 5 --note "smoke real"
# committed; new_entrant_id=36 seed_no=4

python -m scripts.amiga fixtures list-entrants --tournament-id 621
# 5 entrants: player 4 replaced, player 5 registered seed=4

python -m scripts.amiga fixtures list --tournament-id 621
# 6 scheduled fixtures; player 5 appears in former player 4 slots

python -m scripts.amiga fixtures verify-entrants --tournament-id 621
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures cleanup-generated --tournament-id 621
# deleted

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
# both OK after cleanup
```

## Smoke data created/changed/cleaned up

| Tournament ID | Purpose | Outcome |
|---------------|---------|---------|
| 620 | Withdraw smoke | Created, player 4 withdrawn, deleted via `cleanup-generated` |
| 621 | Replace smoke | Created, player 4 → 5 replaced, deleted via `cleanup-generated` |

No lasting changes to tournaments 610, 611, 614 or other active browser test data.

## Risks/limitations/not verified

- Generated-tournament only; imported Access tournaments refused.
- Withdrawal clears scheduled fixture slots (NULL) rather than refusing when scheduled fixtures exist — required for usable ops and `verify-entrants` green; leaves incomplete round-robin schedules until manual repair or replacement.
- Does not create players, public UI, or PHP browser ops for withdraw/replace.
- Does not wire fail-fast entrant checks into `set_fixture_players` / `record_fixture_result` yet.
- Staging re-export not run.
- Seed collision if two active entrants share a seed after replacement is not re-validated (reuses old seed only on new row; old row keeps historical seed on `replaced` row).

## Commit hash and push target

- Implementation commit: `(filled after commit)`
- Push target: `origin/main`

## Recommended next steps

1. Wire entrant status checks into `set_fixture_players` / fixture result entry for fail-fast validation.
2. Add browser ops parity in `fixtures.php` if internal UI needs withdraw/replace.
3. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
4. KOA newcomer naming policy + player creation (separate job).
5. Swiss pairing engine and public registration UI (later slices).
