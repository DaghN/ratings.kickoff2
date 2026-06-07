# Worker prompt 016 — browser fixture slot assignment UX

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

Workers `014` and `015` made the late-entrant workflow browser-visible:

1. Add existing player as tournament entrant.
2. Place registered entrant into a stage.
3. Assign that player into open fixture slots.

Step 3 still uses raw numeric `Player A` / `Player B` inputs on `/amiga/ops/fixtures.php`. The guardrails are good, but the browser operator experience is still brittle because the page now has enough stage-player context to offer safer stage-scoped selects.

## Goal

Improve the password-gated fixture assignment UI so operators can assign scheduled fixture slots from eligible stage players, while preserving existing guardrails and result-entry behavior.

Classification: `internal ops` / `ground truth`

## Required outcome

On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, an authenticated operator should be able to:

1. See empty/incomplete scheduled fixtures with assignment controls.
2. Choose player A / player B from players already placed in that fixture's stage.
3. Keep existing numeric fallback only if useful, but prefer stage-scoped player selects for normal use.
4. Refuse non-entrants, withdrawn/replaced entrants, players outside the stage, same-player assignments, played/void fixtures, and fixtures with attached games.
5. Get clear success/error flashes without exposing this functionality publicly.

## Files/areas to inspect first

Read before editing:

- `site/public_html/amiga/ops/fixtures.php`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`
- `docs/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md`
- `docs/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md`

## Implementation guidance

Keep the mutation path conservative. The existing `amiga_fixture_assign_players` helper already enforces important rules; do not weaken it.

Recommended shape:

- Add a helper to load stage players keyed by stage id, or reuse the `amiga_fixture_list_stage_players` data from worker `015`.
- In the fixture table, when a scheduled fixture has an empty slot and no attached game, render selects for player A and player B populated from that fixture's stage players.
- Preselect existing fixture players when one side is already assigned.
- Keep the POST action `assign_players` if practical; update only the form inputs/UI.
- If the fixture's stage has fewer than two eligible stage players, show a clear hint to place entrants into the stage first.
- Avoid adding fixture generation, rescheduling, or automatic round-robin repair.

Guardrail notes:

- Stage-scoped selects are a UI convenience, not the only safety layer.
- The server-side assignment helper must still validate active registered entrants and stage membership.
- Assignment should remain allowed for scheduled fixtures regardless of tournament lifecycle, matching the current CLI/browser rule, unless existing docs explicitly say otherwise.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md` if the browser ops section needs parity notes

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-08-016-browser-fixture-slot-assignment-ux.md`

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

- Create or use a generated draft/ready tournament with at least one scheduled fixture whose slots can be cleared or assigned.
- Browser or direct local POST smoke for assigning both fixture players from stage players.
- Smoke for assigning when one slot is already populated, if practical.
- Refusal case for same-player assignment.
- Refusal case for a player who is a registered entrant but not in that fixture's stage, if practical.
- Cleanup temporary data and re-run verification.

If browser automation is unavailable, direct local HTTP or PHP/manual checks are acceptable, but document exactly what was and was not verified.

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

`Improve browser Amiga fixture assignment.`

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

Summarize the fixture assignment UX improvement, guardrails, verification run, limitations, handoff document path, and pushed commit hash.
