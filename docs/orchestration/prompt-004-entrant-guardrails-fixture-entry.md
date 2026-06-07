# Worker prompt 004 — entrant guardrails for fixture assignment and result entry

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded foundation/internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The system now has:

- `tournament_entrants` as tournament-level registration ground truth
- entrant backfill for pre-foundation generated tournaments
- `verify-entrants` globally green
- internal entrant status ops: withdraw and replace

The remaining integrity gap is fail-fast validation at mutation time. `verify-entrants` can detect bad stage/fixture participants after the fact, but fixture player assignment and result entry should refuse inactive/non-entrant participants before they create bad state.

## Goal

Wire active entrant checks into fixture assignment and fixture-backed result entry.

Classification: `foundation` / `internal ops`

## Required scope

1. Make `set-players` / `set_fixture_players` require both players to be active `registered` tournament entrants.
2. Make CLI `record-result` / `record_fixture_result` require both fixture players to be active `registered` tournament entrants before inserting `amiga_games`.
3. Mirror the same checks in the internal browser page `site/public_html/amiga/ops/fixtures.php`.
4. Update docs and create handoff.
5. Commit and push when done.

## Rules

Active entrant means:

- row exists in `tournament_entrants`
- same tournament id
- same player id
- `status = 'registered'`

If a player is `withdrawn`, `replaced`, absent, or from another tournament, mutation must fail with a clear error.

Do not change the entrant schema.

Do not create players.

Do not implement public UI.

Do not loosen `verify-entrants`.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`
- `docs/orchestration/agent-handoffs/2026-06-07-002-entrant-backfill-verification.md`
- `docs/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md`
- `docs/amiga-data-contract.md`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `site/public_html/amiga/ops/fixtures.php`

## Implementation guidance

In Python, prefer a small helper, e.g.:

`_require_active_tournament_entrant(conn, tournament_id, player_id)`

Use it in:

- `set_fixture_players`
- `record_fixture_result`

Consider whether `create_fixture` should enforce active entrants too. Be careful: placeholder fixtures may intentionally have `NULL` players, and some builder order may create fixtures after entrants. It is acceptable to enforce only when player ids are non-null if that fits safely.

In PHP, mirror with a helper in `fixtures.php`, e.g.:

`amiga_fixture_require_active_entrant($con, $tournamentId, $playerId)`

Use it in:

- browser fixture player assignment
- browser result entry
- optionally browser fixture/tournament creation if it already creates entrants first

Keep error messages user-readable.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md` if command behavior needs mentioning

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`

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
- PHP lint for `site/public_html/amiga/ops/fixtures.php` using the available Laragon PHP binary if present:
  - `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php`
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- Positive smoke: create a temporary generated tournament, assign/record with registered entrants, verify, cleanup if safe.
- Negative smoke: withdraw or replace an entrant on a temporary tournament, then confirm assigning or recording that inactive/non-entrant player is refused.

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

`Enforce Amiga entrant guardrails on fixture entry.`

## Non-goals

- No public registration UI.
- No newcomer/player creation.
- No KOA naming policy.
- No Swiss pairing engine.
- No lifecycle/status table changes.
- No broad refactor of browser fixture ops.

## Expected final response to user

Summarize what you implemented, tests run, limitations, and the commit hash pushed.
