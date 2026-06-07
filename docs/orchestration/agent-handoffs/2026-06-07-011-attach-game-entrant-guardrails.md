# Agent handoff — attach-game entrant guardrails

## Goal

Harden `fixtures attach-game` so attaching an existing game to a fixture cannot bypass tournament entrant, fixture, or lifecycle invariants.

## Classification

`ground truth` / `internal ops`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — hardened `attach_game_to_fixture` with lifecycle, entrant, fixture-status, and attachment guardrails; `--dry-run` on CLI; expanded `audit_fixture_integrity` for inactive entrants on fixture-backed games
- `scripts/amiga/README.md` — document `attach-game` requirements and dry-run example
- `docs/amiga-data-contract.md` — document `attach-game` rules and verify audit extension
- `docs/orchestration/agent-handoffs/2026-06-07-011-attach-game-entrant-guardrails.md` — this handoff

## Behavior added/changed

- `attach_game_to_fixture` now refuses when:
  - game and fixture belong to different tournaments
  - `amiga_games.fixture_id` is already set (same or different fixture)
  - target fixture already has an attached game
  - fixture status is `played` or `void` (only `scheduled` allowed)
  - fixture players are unset (requires `set-players` first; no auto-fill)
  - fixture players are set but do not match game players (unordered pair)
  - tournament `lifecycle_status` is not `running` (same rule as `record-result`)
  - either game player is not an active (`registered`) tournament entrant
- On success: sets `amiga_games.fixture_id` and marks fixture `played`.
- `python -m scripts.amiga fixtures attach-game` supports `--dry-run` (rolls back, prints summary).
- `audit_fixture_integrity` now flags fixture-backed games whose players are not active `registered` entrants.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main (before edits)

python -m compileall scripts/amiga
# exit 0

python -m unittest scripts.amiga.test_player_names -v
# Ran 6 tests — OK

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Attach Guardrail Smoke" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=634

# (inserted unattached game_id=27423 for players 1 vs 4 matching fixture 126)

python -m scripts.amiga fixtures set-tournament-status --tournament-id 634 --status running

python -m scripts.amiga fixtures attach-game --game-id 27423 --fixture-id 126 --dry-run
# DRY RUN: rolled back
# game_id=27423 fixture_id=126 tournament_id=634 player_a_id=1 player_b_id=4

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Attach Lifecycle Refusal" --event-date 2026-06-07 --country Denmark --player-ids 1,2
# created tournament_id=635

# (inserted unattached game_id=27424 for players 1 vs 2)

python -m scripts.amiga fixtures attach-game --game-id 27424 --fixture-id 132
# exit 1 — ERROR: tournament_id=635 lifecycle_status is 'draft'; result entry is allowed only in running

python -m scripts.amiga fixtures set-tournament-status --tournament-id 635 --status running
# (manually set player_id=2 entrant to withdrawn for refusal test)

python -m scripts.amiga fixtures attach-game --game-id 27424 --fixture-id 132
# exit 1 — ERROR: player_id=2 entrant status is 'withdrawn'; only registered entrants may be used...

python -m scripts.amiga fixtures record-result --fixture-id 126 --goals-a 2 --goals-b 1
# game_id=27425 (fixture 126 now played)

python -m scripts.amiga fixtures attach-game --game-id 27423 --fixture-id 126
# exit 1 — ERROR: fixture_id=126 status is 'played'; attachment refused

python -m scripts.amiga fixtures attach-game --game-id 27425 --fixture-id 127
# exit 1 — ERROR: game_id=27425 is already attached to fixture_id=126

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK after smoke cleanup

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

# cleanup: deleted amiga_games and tournaments 634, 635
```

## Schema/data implications

- No schema changes.
- `verify` audit extended; no change to `verify-entrants` rules.

## Risks/limitations/not verified

- No browser ops path for `attach-game` (CLI/internal only by design).
- No `--force` repair path for attaching to `played` fixtures with missing games.
- Fixture players are not auto-filled from game players; operators must run `set-players` first.
- Entrant refusal smoke used manual `UPDATE tournament_entrants SET status='withdrawn'` because `withdraw-entrant` refuses when unattached tournament games exist for that player.
- Staging re-export not run.

## Commit hash and push target

- Implementation commit: _(filled after commit)_
- Push target: `origin/main`

## Recommended next steps

1. Consider whether browser ops needs an attach-game repair flow for operators.
2. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
3. Swiss pairing engine and public registration UI (later slices).
