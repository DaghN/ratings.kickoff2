# Worker prompt 017 — fixture-stage assignment guardrail

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded guardrail correction for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

Worker `016` improved browser fixture assignment UX by rendering stage-scoped player selects for incomplete scheduled fixtures. However, the server-side assignment helper still validates that both players belong somewhere in the tournament's stage-player set, not necessarily to the fixture's own stage.

That leaves a mismatch:

- UI: stage-scoped selects imply fixture-stage membership.
- Server guardrail: currently tournament-level stage membership is enough.

This job tightens the ground-truth guardrail so CLI and browser assignment both reject players who are not placed in the specific stage that owns the fixture.

## Goal

Harden fixture slot assignment so `set-players` / browser `assign_players` require both players to be active tournament entrants **and** members of the fixture's specific stage.

Classification: `internal ops` / `ground truth` / `guardrail`

## Required outcome

For any scheduled fixture assignment path:

1. Player A and Player B must be different.
2. Both players must be active `registered` entrants for the tournament.
3. Both players must be present in `tournament_stage_players` for the fixture's exact `stage_id`.
4. Assignment must still refuse played/void fixtures and fixtures with attached games.
5. Browser stage-scoped selects from worker `016` remain, but server-side validation is authoritative.
6. CLI `fixtures set-players` and browser POST `assign_players` enforce the same rule.

## Files/areas to inspect first

Read before editing:

- `scripts/amiga/tournament_fixtures.py`
- `site/public_html/amiga/ops/fixtures.php`
- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-08-016-browser-fixture-slot-assignment-ux.md`

## Implementation guidance

Keep this as a focused guardrail correction.

Expected implementation shape:

- In Python `set_fixture_players`, load the fixture's `stage_id` and validate both players against that exact stage.
- In PHP `amiga_fixture_assign_players`, load the fixture's `stage_id` and validate both players against that exact stage.
- Prefer a small helper in each language, for example:
  - Python: `_require_stage_players(conn, stage_id, player_ids)` or similar.
  - PHP: `amiga_fixture_require_stage_players(mysqli $con, int $stageId, array $playerIds): void` or similar.
- Keep existing active-entrant checks; this is an additional invariant, not a replacement.
- Error messages should clearly say when a player is not placed in the fixture's stage.

Do not change result entry, attach-game, stage placement, or fixture generation unless a direct blocker is found.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md` if the fixture assignment note needs tightening

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-08-017-fixture-stage-assignment-guardrail.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Behavior added/changed
- Exact commands/tests run and results
- Browser/manual checks run, if any
- Schema/data implications
- Public/internal boundary notes
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- `git status --short --branch` before staging and before final response
- PHP lint for every changed PHP file, using Laragon PHP:
  - `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l <file>`
- `python -m compileall scripts/amiga`
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`

Run focused smoke checks. Prefer a temporary generated tournament that is cleaned up at the end:

- Positive CLI smoke: assign both fixture players from the fixture's exact stage.
- Negative CLI smoke: try assigning a registered entrant who is placed in another stage, or registered but not placed in this fixture's stage; it must fail.
- Browser/direct local POST positive smoke for assigning from the fixture's stage.
- Browser/direct local POST negative smoke for assigning a player outside the fixture's stage, if practical.
- Cleanup temporary data and re-run verification.

If constructing a multi-stage fixture setup is cumbersome, create a minimal generated test structure with two stages and a scheduled fixture in one stage, then clean it up. Document the setup exactly.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Inspect `git status` before staging and list unrelated files intentionally left unstaged.
- Do not create persistent real players, entrants, stage players, fixtures, or games just for smoke testing unless explicitly justified.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Require fixture-stage membership for assignment.`

## Non-goals

- No public UI.
- No player creation or KOA name suggestion in the browser.
- No public tournament builder or registration.
- No automatic fixture generation/rescheduling.
- No Swiss, group+KO promotion, honours, or format expansion.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.
- No broad fixture manager redesign.

## Expected final response to user

Summarize the fixture-stage assignment guardrail, verification run, limitations, handoff document path, and pushed commit hash.
