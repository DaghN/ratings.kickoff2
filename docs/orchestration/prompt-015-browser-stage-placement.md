# Worker prompt 015 — browser stage placement

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

Worker `014` added password-gated browser entrant management: list, search, add existing player, withdraw, and replace. The remaining operator friction for late entrants is stage placement. CLI already has the guarded flow:

- `fixtures add-stage-player`
- `fixtures place-entrant`

Browser ops can now add an entrant, but the operator must still switch to CLI to put that entrant into a stage before fixture assignment can use them.

## Goal

Add browser ops support for placing registered tournament entrants into stages on generated Amiga tournaments, matching the CLI `place-entrant` / `add-stage-player` guardrails.

Classification: `internal ops` / `ground truth`

## Required outcome

On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, an authenticated operator should be able to:

1. See each tournament stage and its current stage players.
2. Add or update a registered entrant's membership in a selected stage with optional seed and group key.
3. Refuse non-entrants, withdrawn/replaced entrants, imported Access tournaments, and invalid lifecycle states.
4. Keep fixture assignment and result entry behavior unchanged.
5. Get clear success/error flashes without exposing this functionality publicly.

Keep this internal to the password-gated ops page.

## Files/areas to inspect first

Read before editing:

- `site/public_html/amiga/ops/fixtures.php`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/orchestration/agent-handoffs/2026-06-07-010-stage-player-assignment-guardrails.md`
- `docs/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md`

## Implementation guidance

Prefer small PHP helpers inside or near `fixtures.php`, matching the page's existing style.

Suggested helper shape:

- `amiga_fixture_list_stages(mysqli $con, int $tournamentId): array`
- `amiga_fixture_list_stage_players(mysqli $con, int $tournamentId): array`
- `amiga_fixture_place_stage_entrant(...)`

Reuse existing browser helper patterns where possible:

- generated tournament eligibility checks
- lifecycle checks
- active entrant checks
- existing flash and transaction handling style

UI shape can be simple:

- A stage-player section below entrant management.
- For each stage, show stage key/name/type and existing stage players.
- A form that selects one stage and one registered entrant (or accepts player id) plus optional seed/group key.
- If an entrant is already in the stage, update seed/group key rather than inserting a duplicate, matching CLI behavior.
- Show an operator hint that fixture generation/rescheduling is still separate.

## Guardrails

Match the CLI behavior as closely as practical:

- Generated tournaments only (`source_id IS NULL` and approved `format_overrides.generated_by` prefix).
- Allowed lifecycle: `draft`, `registration`, or `ready`.
- Refuse `running`, `completed`, `archived`, and `void`.
- Require an active `registered` tournament entrant.
- Refuse withdrawn/replaced/non-entrant players.
- Do not create players or entrants.
- Do not generate fixtures or rewrite existing fixture schedules.
- Do not alter historical imported Access tournaments.

## Scope choices

Include:

- Stage list and stage-player list in browser ops.
- Place/update a registered entrant in a stage.
- Optional seed and group key fields.
- Documentation of the late-entrant browser workflow: add entrant -> place in stage -> assign fixture slots.

Do not include:

- Player creation or KOA name suggestion.
- Public registration.
- Automatic fixture generation/rescheduling for late entrants.
- Swiss/group promotion logic.
- Bulk stage placement.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md` if the browser ops section needs parity notes

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md`

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
- Browser or direct local POST smoke for placing a registered entrant into a stage.
- Browser or direct local POST smoke for updating that stage player's seed/group key.
- Refusal case for non-entrant or withdrawn/replaced entrant.
- Refusal case for invalid lifecycle (`running`).
- Cleanup temporary data and re-run verification.

If browser automation is unavailable, direct local HTTP or PHP/manual checks are acceptable, but document exactly what was and was not verified.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Inspect `git status` before staging and list unrelated files intentionally left unstaged.
- Do not create persistent real players, entrants, stage players, or fixtures just for smoke testing unless explicitly justified.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Add browser Amiga stage placement.`

## Non-goals

- No public UI.
- No player creation or KOA name suggestion in the browser.
- No public tournament builder or registration.
- No automatic fixture generation/rescheduling for late entrants.
- No Swiss, group+KO promotion, honours, or format expansion.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.
- No broad fixture manager redesign.

## Expected final response to user

Summarize the browser stage-placement flow, guardrails, verification run, limitations, handoff document path, and pushed commit hash.
