# Agent handoff — fixture-stage assignment guardrail

## Goal

Tighten fixture slot assignment so CLI `fixtures set-players` and browser POST `assign_players` require both players to be active `registered` tournament entrants **and** members of the fixture's exact stage in `tournament_stage_players`, matching the stage-scoped assignment UI from worker 016.

## Classification

`internal ops` / `ground truth` / `guardrail`

## Files changed

- `scripts/amiga/tournament_fixtures.py` — `_require_stage_players`; `set_fixture_players` validates fixture `stage_id`
- `site/public_html/amiga/ops/fixtures.php` — `amiga_fixture_require_stage_players`; `amiga_fixture_assign_players` validates fixture `stage_id`
- `docs/amiga-data-contract.md` — fixture-stage membership notes for `set-players` and browser assignment
- `scripts/amiga/README.md` — tightened fixture assignment guardrail notes
- `docs/archive/orchestration/agent-handoffs/2026-06-08-017-fixture-stage-assignment-guardrail.md` — this handoff

## Behavior added/changed

- `set_fixture_players` / `amiga_fixture_assign_players` now load the fixture's `stage_id` and require both players in `tournament_stage_players` for that exact stage.
- Replaces the previous tournament-wide stage-player count check (`COUNT(DISTINCT … WHERE tournament_id = ?`).
- Error messages name the player and `stage_id` when a player is not placed in the fixture's stage.
- Unchanged: distinct players, active `registered` entrant checks, scheduled-only status, refusal when a game is attached, no `running` lifecycle requirement for assignment.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main (before edits)

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php
# No syntax errors detected

python -m compileall scripts/amiga
# Compiling scripts/amiga\tournament_fixtures.py

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

# Smoke setup: group-knockout with players 1–4 split group-a (1,3) / group-b (2,4) / empty final
python -m scripts.amiga build-tournament create-group-knockout --name "Smoke017 Stage Guard" --event-date 2026-06-08 --country Denmark --player-ids 1,2,3,4 --group-count 2
# created tournament_id=639, stage_id=45, fixture_count=3
# fixture_id=151 group-a, fixture_id=152 group-b, fixture_id=153 final

# CLI positive: assign group-a players to group-a fixture
python -m scripts.amiga fixtures set-players --fixture-id 151 --player-a-id 1 --player-b-id 3
# OK: fixture players assigned

# CLI negative: group-a players on final fixture (stage_id=45)
python -m scripts.amiga fixtures set-players --fixture-id 153 --player-a-id 1 --player-b-id 3
# ERROR: player_id=1 is not placed in stage_id=45; fixture players must belong to the fixture's stage

# CLI negative: group-b players on group-a fixture
python -m scripts.amiga fixtures set-players --fixture-id 151 --player-a-id 2 --player-b-id 4
# ERROR: player_id=2 is not placed in stage_id=43; fixture players must belong to the fixture's stage

# HTTP POST positive (group-b fixture 152, players 2+4)
curl.exe -s -X POST "http://ratingskickoff.test/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee" -d "action=assign_players&tournament_id=639&fixture_id=152&player_a_id=2&player_b_id=4"
# flash: Assigned players to fixture #152.

# HTTP POST negative (final fixture 153, group-b players)
curl.exe -s -X POST "http://ratingskickoff.test/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee" -d "action=assign_players&tournament_id=639&fixture_id=153&player_a_id=2&player_b_id=4"
# error flash: Player 2 is not placed in stage 45; fixture players must belong to the fixture's stage.

python -m scripts.amiga fixtures cleanup-generated --tournament-id 639
# deleted tournament_id=639

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
python -m scripts.amiga verify-tournament-formats
# all OK after cleanup
```

## Browser/manual checks run

- **HTTP POST** positive and negative `assign_players` smokes via `curl.exe` against `http://ratingskickoff.test` — behaved as expected.
- **Not run:** interactive browser automation; stage-scoped `<select>` UI unchanged from worker 016.

## Schema/data implications

- No schema migration.
- Assignment still updates `tournament_fixtures.player_a_id` / `player_b_id` only.
- Stricter validation may reject assignments that previously succeeded when players were in a different stage of the same tournament.

## Public/internal boundary notes

| Surface | Fixture assignment |
|---------|-------------------|
| `/amiga/ops/fixtures.php` | Stage-scoped selects + POST `assign_players` (password-gated); server requires fixture-stage membership |
| Public tournament pages | Read-only; no assignment |
| CLI `fixtures set-players` | Same fixture-stage guardrail as browser |

Late-entrant workflow: add entrant → place in stage → assign fixture slots (players must be in the fixture's stage).

## Risks/limitations/not verified

- `create-fixture` with non-null players still checks active entrants only, not fixture-stage membership (unchanged; out of scope).
- Numeric fallback inputs on stages with &lt;2 stage players could still submit invalid cross-stage ids until placement is fixed — server now rejects them.
- Withdrawn/replaced entrant refusal not separately smoke-tested (same `amiga_fixture_require_active_entrant` as before).
- Staging export/import refresh not run.

## Commit hash and push target

`519c8a7` — Require fixture-stage membership for assignment.

Push target: `origin/main`

## Recommended next steps

- Staging re-export before next import (`scripts/export_ko2amiga_db.ps1`).
- Optional: align `create-fixture` non-null player assignment with fixture-stage membership if direct fixture creation with players becomes common.
- Unit tests for `_require_stage_players` / `amiga_fixture_require_stage_players`.
