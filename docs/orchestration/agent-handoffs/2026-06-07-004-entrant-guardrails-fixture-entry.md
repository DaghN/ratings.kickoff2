# Agent handoff — entrant guardrails for fixture assignment and result entry

## Goal

Wire fail-fast active entrant checks into fixture player assignment and fixture-backed result entry (CLI and browser ops) so withdrawn, replaced, or non-entrant players cannot create bad fixture/game state.

## Classification

`foundation` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — `_require_active_tournament_entrant`; used in `set_fixture_players`, `record_fixture_result`, and `create_fixture` (non-null players)
- `site/public_html/amiga/ops/fixtures.php` — `amiga_fixture_require_active_entrant`; used in `amiga_fixture_assign_players` and `amiga_fixture_record_result`
- `docs/amiga-data-contract.md` — document entrant guardrails on set-players, record-result, create-fixture, and browser ops
- `scripts/amiga/README.md` — note entrant requirement on set-players / record-result
- `docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md` — this handoff

## Schema/data implications

- No schema changes.
- No change to `verify-entrants` audit rules.
- Mutations refuse when `tournament_entrants` row is missing or `status != 'registered'`.

## Behavior added or changed

- `python -m scripts.amiga fixtures set-players` requires both players to be active (`registered`) tournament entrants for the fixture's tournament before updating `tournament_fixtures`.
- `python -m scripts.amiga fixtures record-result` requires both fixture players to be active entrants before inserting `amiga_games`.
- `python -m scripts.amiga fixtures create-fixture` enforces the same rule when `player_a_id` / `player_b_id` are non-null; NULL placeholder slots remain allowed.
- `/amiga/ops/fixtures.php` assignment and result entry mirror the same checks with user-readable errors.

## Tests/checks run with exact commands and results

```powershell
python -m py_compile scripts/amiga/tournament_fixtures.py
# exit 0

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php
# No syntax errors detected

python -m scripts.amiga fixtures verify
# tournament_entrants=11
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Guardrail Smoke Kitchen" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=622

python -m scripts.amiga fixtures record-result --fixture-id 67 --goals-a 2 --goals-b 1 --dry-run
# DRY RUN: rolled back; game_id=27416

python -m scripts.amiga fixtures create-fixture --tournament-id 622 --stage-key overall --fixture-key smoke-placeholder --leg-no 99
# fixture_id=73

python -m scripts.amiga fixtures set-players --fixture-id 73 --player-a-id 1 --player-b-id 2 --dry-run
# DRY RUN: rolled back; OK: fixture players assigned

python -m scripts.amiga fixtures withdraw-entrant --tournament-id 622 --player-id 4 --note "guardrail smoke"
# committed; status=withdrawn

python -m scripts.amiga fixtures set-players --fixture-id 73 --player-a-id 1 --player-b-id 4
# exit 1 — ValueError: player_id=4 entrant status is 'withdrawn'; only registered entrants may be used...

python -m scripts.amiga fixtures create-fixture --tournament-id 622 --stage-key overall --fixture-key smoke-bad --player-a-id 1 --player-b-id 4
# exit 1 — same withdrawn entrant refusal

# (fixture 73 manually set to 1 vs 4 to simulate stale fixture row)
python -m scripts.amiga fixtures record-result --fixture-id 73 --goals-a 1 --goals-b 0
# exit 1 — same withdrawn entrant refusal on record-result

python -m scripts.amiga fixtures cleanup-generated --tournament-id 622
# deleted tournament_id=622

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
# both OK after cleanup
```

## Smoke data created/changed/cleaned up

| Tournament ID | Purpose | Outcome |
|---------------|---------|---------|
| 622 | Positive/negative guardrail smoke | Created, tested assign/record/set-players/create-fixture, deleted via `cleanup-generated` |

No lasting changes to tournaments 610, 611, 614 or other active browser test data.

## Risks/limitations/not verified

- PHP browser ops not exercised via HTTP in this handoff (PHP lint only); logic mirrors CLI checks in shared functions.
- `add_stage_player` and `attach_game_to_fixture` do not yet enforce entrant status (out of scope; `verify-entrants` still catches drift).
- Stale fixture rows with inactive players (e.g. manual DB edit) are refused at result entry rather than auto-repaired.
- Staging re-export not run.

## Commit hash and push target

- Implementation commit: `aa5b7021dd0e4640219d7f0643979b5308ce686e`
- Push target: `origin/main`

## Recommended next steps

1. Add browser ops parity for withdraw/replace if internal UI needs them.
2. Consider entrant guardrails on `add_stage_player` if stage membership should also fail-fast.
3. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
4. KOA newcomer naming policy + player creation (separate job).
5. Swiss pairing engine and public registration UI (later slices).
