# Worker prompt 011 — attach-game entrant guardrails

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops/data-integrity slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The tournament foundation now has guardrails for:

- entrant registration
- newcomer onboarding
- stage-player placement
- fixture player assignment
- fixture-backed result entry

The previous handoff noted one remaining bypass: `fixtures attach-game` can attach an existing `amiga_games` row to a fixture without enforcing entrant status/lifecycle as strictly as the live fixture result path. We should close that before moving further into UI or public-facing features.

## Goal

Harden `fixtures attach-game` so attaching an existing game to a fixture cannot bypass tournament entrant, fixture, or lifecycle invariants.

Classification: `ground truth` / `internal ops`

## Required outcome

Operators should be able to attach an existing game to a fixture only when:

1. The game and fixture belong to the same tournament.
2. The fixture is not already backed by another game.
3. The fixture is in a safe attachable status.
4. The fixture players and game players are compatible.
5. Both game players are active (`registered`) tournament entrants.
6. The tournament lifecycle permits fixture-backed game attachment.

Keep this CLI/internal only. No public UI and no browser ops UI in this slice.

## Files/areas to inspect first

Read before editing:

- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`
- `docs/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md`
- `docs/orchestration/agent-handoffs/2026-06-07-010-stage-player-assignment-guardrails.md`

## Implementation guidance

Focus on `attach_game_to_fixture`.

Use existing helpers where possible:

- `_require_active_tournament_entrant`
- lifecycle helper used by result entry (`_require_lifecycle_allows_result_entry`) or a new attach-specific helper if needed
- `fixture_detail`
- `audit_fixture_integrity`

Add `--dry-run` to the `fixtures attach-game` CLI if it is not already present.

## Rules and guardrails

Attachment rules:

- Refuse if `amiga_games.fixture_id` is already set to another fixture.
- Refuse if the target fixture already has a game attached.
- Refuse if the fixture is `played` with no matching attached game, unless you decide this should be repairable only with a clearly named force/repair command. Prefer refusing in this slice.
- Refuse if the fixture is `void`.
- Require game tournament id to equal fixture tournament id.
- If fixture players are already set, the unordered pair must match the game players.
- If fixture players are not set, either:
  - set them to the game players as part of attach, or
  - refuse and require `set-players` first.

Prefer the conservative option unless existing docs strongly imply auto-fill. If you choose auto-fill, document it and ensure both players are active entrants before writing.

- Require both game players to be active registered entrants.
- Require lifecycle `running` unless there is a strong reason to allow repair before running. Be consistent with result entry.
- On success, set `amiga_games.fixture_id = fixture_id` and set fixture status to `played`.
- `--dry-run` must roll back.

Do not add a `--force` unless there is a real, documented repair use case.

## Verification improvements

Consider whether `audit_fixture_integrity` should also catch:

- a fixture with more than one attached game
- a game attached to a fixture where game players are not active entrants
- a played fixture with no attached game

Keep verification focused and avoid large refactors.

## Documentation requirements

Update:

- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-011-attach-game-entrant-guardrails.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Behavior added/changed
- Exact commands/tests run and results
- Any sample command output
- Schema/data implications
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- `python -m compileall scripts/amiga`
- `python -m unittest scripts.amiga.test_player_names -v`
- A focused attach-game dry-run smoke if practical. If no safe existing game/fixture combination exists, create a temporary generated tournament and use dry-run or rollback paths only.
- Refusal case for non-running lifecycle.
- Refusal case for a player who is not an active entrant, if practical.
- Refusal case for already-attached or incompatible fixture/game, if practical.
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`
- Cleanup any temporary tournament/game data created for smoke testing.
- `git status --short --branch` before staging and before final response

If a command cannot be run, state why.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Do not leave persistent test tournaments, games, entrants, or players unless explicitly justified.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Harden Amiga fixture game attachment.`

## Non-goals

- No public UI.
- No browser ops UI.
- No schema changes unless a blocker is discovered and documented.
- No staging export/import refresh.
- No broad fixture refactor.
- No player creation or entrant onboarding changes beyond calling existing helpers if needed.

## Expected final response to user

Summarize the attach-game guardrails, dry-run behavior, tests run, limitations, and commit hash pushed.
