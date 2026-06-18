# Worker prompt 006 — browser lifecycle controls for internal fixture ops

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops/browser-usability slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The Amiga tournament system now has lifecycle ground truth:

- `tournaments.lifecycle_status`
- `started_at`
- `completed_at`
- CLI `fixtures set-tournament-status`
- result entry allowed only when lifecycle status is `running`

Generated tournaments now default to `draft`, which is correct and conservative. However, the internal browser ops page can create a kitchen-marathon tournament and enter results, but currently cannot move a tournament from `draft`/`ready` to `running`. That makes the browser flow incomplete unless the operator uses CLI.

## Goal

Add password-gated internal browser lifecycle controls to `site/public_html/amiga/ops/fixtures.php`, mirroring the safe CLI transition behavior enough for local/internal tournament operation.

Classification: `internal ops`

## Required scope

1. Show the selected tournament's current lifecycle status, `started_at`, and `completed_at` on the internal fixture page.
2. Add an internal browser form to transition lifecycle status.
3. Enforce conservative transition rules matching the CLI as closely as practical.
4. Keep result entry allowed only when status is `running`.
5. Update docs and create handoff.
6. Commit and push when done.

## Browser transition requirements

The browser page should support at least:

- `draft` → `ready`
- `ready` → `running`
- `running` → `completed` when no scheduled fixtures remain
- `running` → `void` only if no games are attached, or refuse if safer

Do **not** expose a broad force option in the browser unless you strongly justify it. Force transitions should remain CLI-only for now.

For `completed`, the browser should refuse when scheduled fixtures remain unplayed.

For imported Access tournaments, the browser should not allow lifecycle changes.

Use clear flash messages for success/failure.

## Files/areas to inspect first

Read before editing:

- `docs/archive/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md`
- `docs/amiga-data-contract.md`
- `scripts/amiga/tournament_fixtures.py`
- `site/public_html/amiga/ops/fixtures.php`
- `scripts/amiga/README.md`

## Implementation guidance

Keep the PHP lifecycle logic local to `fixtures.php` for now; do not introduce a large framework.

Consider helper functions such as:

- `amiga_fixture_load_lifecycle`
- `amiga_fixture_count_scheduled_fixtures`
- `amiga_fixture_count_tournament_games`
- `amiga_fixture_set_lifecycle_status`

Match the CLI rules where feasible:

- unknown status refused
- imported tournaments refused
- `started_at` set when moving to `running` if empty
- `completed_at` set when moving to `completed` or `archived` if empty
- `completed` refused while scheduled fixtures remain

The internal browser page should remain password-gated via the existing `once` / `pwd` mechanism.

Do not build public UI or navigation in this slice.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-07-006-browser-lifecycle-controls.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Schema/data implications
- Behavior added or changed
- Tests/checks run with exact commands and results
- Browser/local smoke data created/changed/cleaned up
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- PHP lint for `site/public_html/amiga/ops/fixtures.php` using Laragon PHP if available:
  - `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php`
- Python compile for any changed Python modules, if any.
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- Browser-flow-equivalent smoke using CLI or direct HTTP if possible:
  - create a temporary generated tournament
  - transition it through the browser-equivalent helper or browser page path to `ready` and `running`
  - verify result entry is possible only in `running`
  - verify browser path refuses `completed` while scheduled fixtures remain
  - cleanup if safe, or clearly explain if a game prevents cleanup

If HTTP/browser automation is not available, say so and test the PHP helpers via lint plus CLI state checks as far as possible.

## Git requirements

Commit and push when done.

Important:

- Inspect `git status` before staging.
- Do not commit unrelated pre-existing local changes unless this prompt explicitly includes them.
- In your handoff, list any unrelated files intentionally left unstaged.
- Do not use destructive git commands.
- Do not force push.

Suggested commit message:

`Add internal browser lifecycle controls for Amiga tournaments.`

## Non-goals

- No public lifecycle UI.
- No public registration UI.
- No newcomer/player creation.
- No KOA naming policy.
- No Swiss pairing engine.
- No honours derivation.
- No broad navigation redesign.

## Expected final response to user

Summarize what you implemented, tests run, limitations, and the commit hash pushed.
