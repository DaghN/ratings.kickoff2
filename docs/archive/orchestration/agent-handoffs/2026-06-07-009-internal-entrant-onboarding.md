# Agent handoff — internal entrant onboarding flow

## Goal

Add internal CLI tooling so operators can register existing players or atomically onboard newcomers (KOA naming + player creation + entrant registration) into generated tournaments, with dry-run support and guardrails.

## Classification

`ground truth` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — `register_tournament_entrant`, `onboard_newcomer_entrant`, lifecycle/duplicate guardrails, CLI `add-entrant` and `onboard-newcomer`
- `scripts/amiga/player_registry.py` — `create_player` defers commit when caller owns the connection (atomic onboarding)
- `scripts/amiga/README.md` — onboarding CLI examples
- `docs/amiga-data-contract.md` — document `add-entrant` / `onboard-newcomer` ops
- `docs/archive/orchestration/agent-handoffs/2026-06-07-009-internal-entrant-onboarding.md` — this handoff

## Behavior added

- `python -m scripts.amiga fixtures add-entrant --tournament-id N --player-id P [--seed-no N] [--note TEXT] [--dry-run]` registers an existing player as a `registered` entrant on generated tournaments only. Allowed lifecycle: `draft`, `registration`, `ready`. Refuses imported tournaments, duplicate active entrants, and `withdrawn` / `replaced` rows (no reactivation).
- `python -m scripts.amiga fixtures onboard-newcomer --tournament-id N (--name TEXT | --full-name TEXT) [--country TEXT] [--seed-no N] [--note TEXT] [--dry-run]` validates KOA naming (`check_player_name` or `suggest_player_name`), creates the player, and registers the entrant in one transaction. Refuses when both `--name` and `--full-name` are provided. Rolls back the new player if entrant insert fails. Does not insert `tournament_stage_players`.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main

python -m compileall scripts/amiga
# exit 0

python -m unittest scripts.amiga.test_player_names -v
# Ran 6 tests — OK

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Onboard Smoke Kitchen" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=628

python -m scripts.amiga fixtures add-entrant --tournament-id 628 --player-id 5 --seed-no 5 --note "late signup" --dry-run
# DRY RUN: rolled back
# tournament_id=628 player_id=5 entrant_id=None status=registered seed_no=5 note='late signup'

python -m scripts.amiga fixtures onboard-newcomer --tournament-id 628 --full-name "Mark Bentley" --country "England" --seed-no 5 --dry-run
# DRY RUN: rolled back
# name_source=suggested resolved_name='Mark Be' player_id=None entrant_id=None
# suggestion JSON: suggested_name=Mark Be, available=true

python -m scripts.amiga fixtures onboard-newcomer --tournament-id 628 --name "Totally Unique Zz Player" --country "England" --seed-no 6 --dry-run
# DRY RUN: rolled back; name_source=explicit resolved_name='Totally Unique Zz Player'

python -m scripts.amiga fixtures add-entrant --tournament-id 628 --player-id 1 --dry-run
# exit 1 — ERROR: player_id=1 is already a registered entrant in tournament_id=628

python -m scripts.amiga fixtures onboard-newcomer --tournament-id 628 --name "Mark B" --country "England" --dry-run
# exit 1 — ERROR: name conflict (exact): normalized='Mark B' collides with player_id=279

python -m scripts.amiga fixtures set-tournament-status --tournament-id 628 --status running
python -m scripts.amiga fixtures add-entrant --tournament-id 628 --player-id 5 --dry-run
# exit 1 — ERROR: lifecycle_status is 'running'; entrant registration is allowed only in draft, ready, registration

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

python -m scripts.amiga fixtures cleanup-generated --tournament-id 628
# deleted tournament_id=628 (no persistent test players/entrants left)
```

## Schema/data implications

- No schema changes.
- `tournament_entrants` rows only; no automatic `tournament_stage_players` insertion.
- `create_player` no longer auto-commits when passed an external connection (enables atomic onboarding).

## Risks/limitations/not verified

- No public UI or browser ops for onboarding.
- No automatic stage-player or fixture assignment after entrant registration.
- No reactivation of `withdrawn` / `replaced` entrants via these commands.
- Non-dry-run `onboard-newcomer` commit path not exercised against live DB in this slice (dry-run and refusal cases only; smoke tournament cleaned up).
- Seed number uniqueness across entrants is not enforced (same as existing builder/backfill behavior).

## Commit hash and push target

`876ddca` — Add internal Amiga entrant onboarding tools.

Push target: `origin/main`

## Recommended next steps

- Browser ops UI for entrant onboarding (optional; deferred).
- Stage-player assignment workflow after late entrant registration.
- Unit tests for `register_tournament_entrant` / `onboard_newcomer_entrant` validation (transaction rollback harness).
- Consider seed collision checks when `--seed-no` is supplied.
