# Worker prompt 010 — stage-player assignment guardrails

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops/data-integrity slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The current foundation now supports:

- fixture-backed generated tournaments
- tournament entrants as ground truth
- lifecycle controls
- KOA-aware player creation
- internal entrant onboarding

The next gap is that `tournament_stage_players` can still be updated through the lower-level stage command without the same explicit entrant/lifecycle guardrails we now have for fixture assignment and result entry. Also, after a late entrant is registered, operators need a clear internal way to place that entrant into one or more stages before fixture assignment.

## Goal

Harden stage-player assignment so stage membership cannot drift away from tournament-level entrant ground truth, and add a small internal CLI flow for placing a registered entrant into a stage.

Classification: `ground truth` / `internal ops`

## Required outcome

Operators should be able to:

1. Add/update a stage player only if the player is an active (`registered`) tournament entrant.
2. Dry-run stage-player placement.
3. Place a late registered entrant into a named stage with optional seed/group fields.
4. See a clear refusal when the player is not an active entrant, the tournament/stage is invalid, or lifecycle status makes placement unsafe.

Keep this CLI/internal only. No public UI and no browser ops UI in this slice.

## Files/areas to inspect first

Read before editing:

- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/player_registry.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`
- `docs/orchestration/agent-handoffs/2026-06-07-009-internal-entrant-onboarding.md`

## Implementation guidance

Prefer using and improving existing helpers:

- `add_stage_player`
- `_require_active_tournament_entrant`
- lifecycle helpers in `tournament_fixtures.py`
- `list_entrants`

Suggested CLI shape:

```powershell
python -m scripts.amiga fixtures add-stage-player --tournament-id N --stage-key overall --player-id P --seed-no 5 --dry-run

python -m scripts.amiga fixtures place-entrant --tournament-id N --stage-key overall --player-id P --seed-no 5 --group-key A --dry-run
```

It is acceptable for `place-entrant` to be an alias/wrapper around hardened `add-stage-player` if that keeps the surface small. If you keep only `add-stage-player`, document it clearly as the stage placement command and add `--dry-run`.

## Rules and guardrails

Stage placement rules:

- Player must be an active (`registered`) `tournament_entrants` row for that tournament.
- Refuse `withdrawn` and `replaced` entrants.
- Refuse imported historical tournaments for new stage-player mutations unless existing code already has a safe reason to allow them.
- Allow placement only in lifecycle `draft`, `registration`, or `ready` by default.
- Refuse placement in `running`, `completed`, `archived`, and `void` by default.
- Do not auto-create tournament entrants.
- Do not auto-create players.
- Do not auto-create fixtures.
- Do not alter played fixtures or games.
- `--dry-run` must not persist changes.

Existing builders may still call helper functions internally. If hardening `add_stage_player` affects builders, update builders/tests accordingly while preserving valid generated-tournament creation.

## Integrity improvements

Consider adding a focused verification/refusal path if not already covered:

- `verify-entrants` should continue to catch any stage player who is not an active entrant.
- CLI stage placement should fail before creating such a bad row.

Do not change schema unless a real blocker is found.

## Documentation requirements

Update:

- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-010-stage-player-assignment-guardrails.md`

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
- Create a temporary generated tournament for smoke testing, or use an existing safe generated draft tournament if one exists.
- `fixtures add-entrant ... --dry-run` or non-persistent equivalent to show a player can become eligible.
- `fixtures add-stage-player ... --dry-run` or `fixtures place-entrant ... --dry-run` for an active entrant.
- A refusal case for a player who is not a tournament entrant.
- A refusal case for an invalid lifecycle such as `running`.
- Cleanup any temporary tournament if created and no games were added.
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`
- `git status --short --branch` before staging and before final response

If a command cannot be run, state why.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Do not leave persistent test tournaments, players, entrants, or stage players unless explicitly justified.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Harden Amiga stage player assignment.`

## Non-goals

- No public UI.
- No browser ops UI.
- No automatic fixture scheduling for late entrants.
- No player creation changes.
- No entrant onboarding changes beyond calling existing helpers if needed.
- No schema changes unless a blocker is discovered and documented.
- No staging export/import refresh.

## Expected final response to user

Summarize the stage-player guardrails, dry-run behavior, tests run, limitations, and commit hash pushed.
