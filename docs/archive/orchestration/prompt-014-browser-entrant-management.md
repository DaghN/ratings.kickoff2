# Worker prompt 014 — browser entrant management

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The live public read-only view is now in place (worker `013`). The next practical operator gap is entrant management in the password-gated browser fixture manager. Today the safe ground-truth flows exist in CLI only:

- `fixtures list-entrants`
- `fixtures add-entrant`
- `fixtures withdraw-entrant`
- `fixtures replace-entrant`
- `fixtures add-stage-player` / `place-entrant`

Browser ops already creates fixture-backed kitchen tournaments, controls lifecycle, assigns fixture slots, and records results. Operators should not need to stitch basic entrant list/add/withdraw/replace work through the CLI for generated tournaments.

## Goal

Add browser ops support for managing existing-player tournament entrants on generated Amiga tournaments, using the same safety rules as the CLI.

Classification: `internal ops` / `ground truth`

## Required outcome

On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, an authenticated operator should be able to:

1. See the tournament entrant list with player id, player name, seed, status, and note.
2. Search existing `amiga_players` by id/name and register one as a tournament entrant with optional seed/note.
3. Withdraw a registered entrant, with the same generated-tournament and fixture/game guardrails as the CLI.
4. Replace a registered entrant with an existing player, preserving the old seed and updating scheduled unplayed fixtures/stage players the same way as the CLI.
5. Get clear success/error flashes without exposing this functionality publicly.

Keep this internal to the password-gated ops page.

## Files/areas to inspect first

Read before editing:

- `site/public_html/amiga/ops/fixtures.php`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-009-internal-entrant-onboarding.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-010-stage-player-assignment-guardrails.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-08-013-read-only-live-public-view.md`

## Implementation guidance

Prefer small PHP helpers inside or near `fixtures.php`, matching the page's existing style.

Suggested helper shape:

- `amiga_fixture_list_entrants(mysqli $con, int $tournamentId): array`
- `amiga_fixture_search_players(mysqli $con, string $query, int $limit = 20): array`
- `amiga_fixture_add_entrant_existing_player(...)`
- `amiga_fixture_withdraw_entrant(...)`
- `amiga_fixture_replace_entrant(...)`

Reuse existing browser helper patterns where possible:

- generated tournament eligibility checks
- lifecycle checks
- `amiga_fixture_require_player`
- `amiga_fixture_require_active_entrant`
- existing flash and transaction handling style

Search can be simple server-rendered HTML in this slice:

- An input for player id or name fragment.
- A bounded result list with Add/Replace buttons.
- Exact player id should work even when name search is ambiguous.
- Escape all displayed player names/countries/notes.

Do not add a new public API unless the current site already has a clearly reusable Amiga player search endpoint and using it is simpler than server-rendered search.

## Guardrails

Match the CLI behavior as closely as practical:

- Generated tournaments only (`source_id IS NULL` and approved `format_overrides.generated_by` prefix).
- Add existing entrant only in `draft`, `registration`, or `ready`.
- Refuse duplicate active entrants.
- Do not silently reactivate `withdrawn` or `replaced` rows.
- Withdrawal requires a current `registered` entrant.
- Withdrawal refuses if the player has tournament games or played/attached-game fixtures.
- Withdrawal clears that player from scheduled unplayed fixture slots and removes stage-player rows, so `verify-entrants` remains green.
- Replacement requires the old player to be `registered` and the new player to exist and not already be an entrant.
- Replacement refuses if the old player has games or played/attached-game fixtures.
- Replacement updates scheduled unplayed fixtures and stage players from old to new player, and preserves old entrant history as `replaced`.
- Use transactions for mutations that touch multiple tables.

## Scope choices

Include:

- Existing-player add by search/id.
- Entrant list.
- Withdraw existing entrant.
- Replace with existing player.
- Documentation of the operator workflow.

Do not include:

- Browser player creation.
- KOA name suggestion UI.
- Public registration.
- Automatic stage placement for newly added entrants.
- Automatic fixture generation/rescheduling for late entrants.

It is acceptable to show a short operator hint after adding an entrant: use stage placement / fixture assignment paths separately when needed.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md` if the browser ops section needs parity notes

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Behavior added/changed
- Exact commands/tests run and results
- Browser/manual checks run
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
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`

Run focused smoke checks. Prefer a temporary generated tournament that is cleaned up at the end:

- Create or use a generated draft/ready tournament.
- Browser or direct local POST smoke for add existing entrant.
- Browser or direct local POST smoke for withdraw.
- Browser or direct local POST smoke for replace.
- Refusal case for duplicate entrant.
- Refusal case for invalid lifecycle (`running` for add).
- Cleanup temporary data and re-run verification.

If browser automation is unavailable, direct local HTTP or PHP/manual checks are acceptable, but document exactly what was and was not verified.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Inspect `git status` before staging and list unrelated files intentionally left unstaged.
- Do not create persistent real players or entrants just for smoke testing unless explicitly justified.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Add browser Amiga entrant management.`

## Non-goals

- No public UI.
- No player creation or KOA name suggestion in the browser.
- No public tournament builder or registration.
- No Swiss, group+KO promotion, honours, or format expansion.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.
- No broad fixture manager redesign.

## Expected final response to user

Summarize the browser entrant management flows, guardrails, verification run, limitations, and commit hash pushed.
