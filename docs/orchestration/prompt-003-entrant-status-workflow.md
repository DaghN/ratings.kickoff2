# Worker prompt 003 — entrant status workflow

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded foundation/internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The Amiga tournament system now has tournament-level entrant ground truth:

- `tournament_entrants`
- builders/browser create paths populate registered entrants
- `fixtures verify-entrants` is globally green after backfill

The next missing foundation piece is controlled entrant status changes. Before public registration, Swiss pairing, or serious event operations, we need safe internal ops for withdrawals and replacements.

## Goal

Add internal CLI support for entrant withdrawal and replacement while preserving tournament/fixture integrity.

Classification: `foundation` / `internal ops`

## Required scope

1. Add CLI commands for withdrawing an entrant and replacing an entrant.
2. Ensure integrity rules are conservative and explicit.
3. Update verification if needed.
4. Update docs.
5. Create handoff document.
6. Commit and push when done.

## Desired commands

Suggested names under `python -m scripts.amiga fixtures`:

- `withdraw-entrant --tournament-id N --player-id P [--note TEXT] [--dry-run]`
- `replace-entrant --tournament-id N --old-player-id OLD --new-player-id NEW [--note TEXT] [--dry-run]`

If you choose different names, explain why in the handoff.

## Withdrawal policy

Withdrawal should:

- Require an existing `tournament_entrants` row.
- Refuse to withdraw if the player has any attached `amiga_games` in that tournament.
- Refuse to withdraw if the player is assigned to any played fixture.
- For scheduled unplayed fixtures, choose a conservative behavior:
  - either refuse withdrawal while scheduled fixtures exist, or
  - clear that player from scheduled fixtures and explain/document it.

Prefer the safer option unless the existing code strongly suggests otherwise.

Set entrant `status = 'withdrawn'` and append/preserve an admin note.

## Replacement policy

Replacement should:

- Require old entrant exists.
- Require new player exists in `amiga_players`.
- Refuse if new player is already an entrant in the tournament.
- Refuse if old player has attached games in that tournament.
- Preserve old entrant as `status = 'replaced'`.
- Insert new entrant as `registered`, preferably reusing old seed number.
- For scheduled unplayed fixtures involving the old player, update them to the new player.
- Refuse if any fixture involving old player is already played or has an attached game.

Do not create new players in this slice.

Do not implement public UI in this slice.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`
- `docs/orchestration/agent-handoffs/2026-06-07-002-entrant-backfill-verification.md`
- `docs/amiga-data-contract.md`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `site/public_html/amiga/ops/fixtures.php`

## Implementation guidance

Prefer implementing this in `scripts/amiga/tournament_fixtures.py` near the entrant helpers.

Make operations transaction-safe.

Use explicit validation helpers if it keeps the logic readable.

Notes:

- Be careful with entrant notes. If appending to an existing note, keep it concise and deterministic.
- Do not mutate completed games.
- Do not mutate legacy imported tournaments unless you deliberately justify it. It is acceptable to limit these commands to generated/live tournaments for now, but document the choice.
- Keep `verify-entrants` meaningful after withdrawal/replacement. If withdrawn/replaced entrants should not satisfy active fixture/stage requirements, make sure scheduled fixture updates or refusals keep verification green.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Schema/data implications
- Behavior added or changed
- Tests/checks run with exact commands and results
- Smoke data created/changed/cleaned up
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- `python -m py_compile scripts/amiga/tournament_fixtures.py`
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- A dry-run withdraw smoke on a generated tournament with no games for the target player.
- A real withdraw smoke on a temporary generated tournament, followed by `verify-entrants`, then cleanup if safe.
- A dry-run replace smoke on a generated tournament with no games for the old player.
- A real replace smoke on a temporary generated tournament, followed by `list-entrants`, `fixtures list`, `verify-entrants`, then cleanup if safe.

Use temporary generated tournaments for destructive smoke tests, not tournament `610` or other active browser test tournaments with games.

If any command cannot be run, state why.

## Git requirements

Commit and push when done.

Important:

- Inspect `git status` before staging.
- Do not commit unrelated pre-existing local changes unless this prompt explicitly includes them.
- In your handoff, list any unrelated files intentionally left unstaged.
- Do not use destructive git commands.
- Do not force push.

Suggested commit message:

`Add Amiga entrant status workflow.`

## Non-goals

- No public registration UI.
- No newcomer/player creation.
- No KOA naming policy.
- No Swiss pairing engine.
- No lifecycle/status table changes unless you discover a blocker and document it.
- No broad refactor of browser fixture ops.

## Expected final response to user

Summarize what you implemented, tests run, limitations, and the commit hash pushed.
