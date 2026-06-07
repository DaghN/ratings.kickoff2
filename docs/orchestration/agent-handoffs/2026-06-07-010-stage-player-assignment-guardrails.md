# Agent handoff — stage-player assignment guardrails

## Goal

Harden stage-player assignment so stage membership cannot drift away from tournament-level entrant ground truth, and add internal CLI flows for placing a registered entrant into a stage (with dry-run).

## Classification

`ground truth` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — hardened `add_stage_player`, `place_stage_entrant` alias, `_require_lifecycle_allows_stage_placement`, CLI `add-stage-player` / `place-entrant` with `--dry-run`
- `scripts/amiga/README.md` — stage placement CLI examples
- `docs/amiga-data-contract.md` — document `add-stage-player` / `place-entrant` guardrails
- `docs/orchestration/agent-handoffs/2026-06-07-010-stage-player-assignment-guardrails.md` — this handoff

## Behavior added/changed

- `add_stage_player` now requires a generated tournament (`_require_eligible_generated_tournament`), lifecycle in `draft` / `registration` / `ready`, and an active (`registered`) `tournament_entrants` row before upserting `tournament_stage_players`. Refuses imported Access tournaments, non-entrants, and `withdrawn` / `replaced` entrants. Does not auto-create players, entrants, or fixtures. Supports `dry_run` and returns a summary dict.
- `python -m scripts.amiga fixtures add-stage-player` exposes the hardened path with `--seed-no`, `--group-key`, and `--dry-run`.
- `python -m scripts.amiga fixtures place-entrant` is an alias for the same operation (operator-facing name for late entrant placement).
- Internal builders (`tournament_builder.py`) continue to call `add_stage_player` after `add_tournament_entrant`; guardrails pass during generated-tournament creation in `draft`.
- `verify-entrants` unchanged; CLI placement now fails before creating orphan stage-player rows.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main (before edits)

python -m compileall scripts/amiga
# exit 0

python -m unittest scripts.amiga.test_player_names -v
# Ran 6 tests — OK

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Stage Guardrail Smoke 2" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=632

python -m scripts.amiga fixtures add-entrant --tournament-id 632 --player-id 5 --seed-no 5 --note "late signup"
# tournament_id=632 player_id=5 entrant_id=79 status=registered seed_no=5 note='late signup'

python -m scripts.amiga fixtures add-stage-player --tournament-id 632 --stage-key overall --player-id 5 --seed-no 5 --dry-run
# DRY RUN: rolled back
# tournament_id=632 stage_key='overall' stage_id=36 player_id=5 seed_no=5

python -m scripts.amiga fixtures place-entrant --tournament-id 632 --stage-key overall --player-id 5 --seed-no 5
# tournament_id=632 stage_key='overall' stage_id=36 player_id=5 seed_no=5

python -m scripts.amiga fixtures add-stage-player --tournament-id 632 --stage-key overall --player-id 99
# exit 1 — ERROR: player_id=99 is not a tournament entrant in tournament_id=632

python -m scripts.amiga fixtures set-tournament-status --tournament-id 632 --status running
python -m scripts.amiga fixtures add-stage-player --tournament-id 632 --stage-key overall --player-id 1 --dry-run
# exit 1 — ERROR: lifecycle_status is 'running'; stage player placement is allowed only in draft, ready, registration

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

python -m scripts.amiga fixtures cleanup-generated --tournament-id 632
# deleted tournament_id=632
```

## Schema/data implications

- No schema changes.
- `tournament_stage_players` composite primary key `(stage_id, player_id)` unchanged; no surrogate `id` column.

## Risks/limitations/not verified

- No public UI or browser ops for stage placement.
- No automatic fixture scheduling after stage placement (late entrants still need manual fixture ops).
- `attach_game_to_fixture` still does not enforce entrant status (out of scope).
- Non-dry-run refusal cases for withdrawn/replaced entrants not separately smoke-tested (same `_require_active_tournament_entrant` as fixture ops).
- Staging re-export not run.

## Commit hash and push target

- Implementation commit: `3977041`
- Push target: `origin/main`

## Recommended next steps

1. Browser ops parity for stage placement if internal UI needs it.
2. Workflow doc for late entrant: `add-entrant` / `onboard-newcomer` → `place-entrant` → `set-players` on placeholder fixtures.
3. Unit tests for `add_stage_player` validation (transaction rollback harness).
4. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
