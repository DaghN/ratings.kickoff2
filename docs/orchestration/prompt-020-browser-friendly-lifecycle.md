# Worker prompt 020 - browser friendly lifecycle

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing the second browser organizer workflow slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing PHP/CSS style and preserve unrelated local changes.

## Strategic context

Worker `018` defined the organizer-first workflow:

`docs/orchestration/browser-organizer-workflow-checkpoint.md`

Worker `019` implemented the first shell slice:

- `/amiga/ops/fixtures.php` now presents as **Tournament organizer**
- `view` tabs exist (`setup`, `players`, `fixtures`, `table`, `results`, `advanced`)
- league creation uses a player picker/chips instead of primary raw IDs
- successful create redirects to the new league's Fixtures view

The next friction is lifecycle language. The Setup tab still exposes the raw technical transition dropdown (`draft`, `ready`, `running`, `completed`, `void`). That is correct internally but poor organizer UX. This job should keep the existing lifecycle guardrails while replacing the happy-path controls with friendly actions.

Classification: `internal ops` / `UX implementation` / `browser organizer workflow`

## Goal

Make the Setup tab speak organizer language:

- show friendly tournament status
- provide clear **Start tournament** and **Mark complete** actions when allowed
- keep dangerous or unusual lifecycle controls out of the primary path
- preserve existing lifecycle invariants and public/internal boundaries

## Required outcome

Implement the Phase B slice from `docs/orchestration/browser-organizer-workflow-checkpoint.md`.

### 1. Friendly status labels

In `site/public_html/amiga/ops/fixtures.php`, map lifecycle values to organizer labels in the Setup/tournament header:

- `draft` / `registration` / `ready` -> **Not started** or **Ready to start** (choose one clear mapping and document it)
- `running` -> **In progress**
- `completed` / `archived` -> **Finished**
- `void` -> **Void**

Keep the raw lifecycle value available in muted/Advanced copy if helpful, but not as the primary status shown to a normal organizer.

### 2. Replace primary lifecycle dropdown

On the Setup tab for generated tournaments:

- Replace the raw "Transition to" dropdown with action buttons where allowed:
  - **Start tournament**
  - **Mark complete**
  - **Void tournament** only if already supported safely and clearly secondary
- Under the hood, continue using `amiga_fixture_set_lifecycle_status()`.
- The action names should be organizer-friendly. Do not expose `draft`, `ready`, `running`, or `completed` as the main button labels.

Recommended behavior:

- If current status is `draft` or `registration`, **Start tournament** may first move to `ready` and then `running`, or may move through existing allowed transitions in a small helper. Keep this explicit in code and handoff.
- If current status is `ready`, **Start tournament** moves to `running`.
- If current status is `running` and no scheduled fixtures remain, **Mark complete** moves to `completed`.
- If current status is `running` but scheduled fixtures remain, show why complete is unavailable.
- If current status is `completed` / `archived`, show read-only finished state.
- Imported Access tournaments remain read-only in browser lifecycle controls.

### 3. Preserve guardrails

Do not weaken current lifecycle rules:

- imported historical tournaments refuse browser mutations
- `completed` still refuses when scheduled fixtures remain
- `void` still refuses when games exist
- result entry remains allowed only when lifecycle is `running`
- no browser force transition

If you add a helper for friendly actions, keep it small and route through existing validation rather than duplicating transition logic unsafely.

### 4. POST/redirect behavior

Keep the `019` PRG/session-flash pattern:

- lifecycle action POST redirects back to `view=setup`
- success and error messages appear in Setup
- tab context and password/once state are preserved

### 5. Advanced/debug access

Move or keep the raw lifecycle dropdown only in **Advanced** if you think operators still need it for unusual supported transitions. If kept:

- label it clearly as advanced/internal
- keep the same guardrails
- do not make it more prominent than the friendly Setup actions

If you remove the raw dropdown entirely, document that decision in the handoff.

### 6. Minimal styling

In `site/public_html/stylesheets/amiga-tournament.css`, add only small scoped styles if needed for:

- status summary
- action button row
- unavailable action explanation

Do not redesign fixtures, table, or result-entry presentation in this job.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/browser-organizer-workflow-checkpoint.md`
- `docs/orchestration/agent-handoffs/2026-06-08-019-browser-organizer-shell.md`
- `site/public_html/amiga/ops/fixtures.php`
- `site/public_html/stylesheets/amiga-tournament.css`
- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`

## Documentation requirements

Update if behavior changed:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`
- `docs/orchestration/browser-organizer-workflow-checkpoint.md` only if implementation intentionally diverges from the plan

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-08-020-browser-friendly-lifecycle.md`

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

The worker's final chat response must explicitly include the handoff document path and pushed commit hash.

## Verification requirements

Run and report exact commands/results:

- `git status --short --branch` before staging and before final response
- PHP lint for every changed PHP file, using Laragon PHP:
  - `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php`
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`

Run focused browser/local checks if practical:

- Setup tab for a new/generated league shows friendly status, not the raw dropdown as primary UI.
- **Start tournament** moves an eligible league to `running` and returns to Setup with a clear flash.
- **Mark complete** is unavailable while scheduled fixtures remain, with a clear explanation.
- Imported/historical tournament lifecycle controls remain read-only.
- Result entry is still unavailable unless lifecycle is `running`.
- Cleanup any generated smoke tournament if one was created solely for testing and has no games.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Inspect `git status` before staging and list unrelated files intentionally left unstaged.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.
- Do not create persistent real tournaments for smoke testing unless explicitly justified; cleanup generated test tournaments when possible.

Suggested commit message:

`Add friendly organizer lifecycle actions.`

## Non-goals

- No empty league table from entrants yet.
- No fixture table redesign.
- No dedicated Results tab redesign.
- No late-entrant fixture generation.
- No Swiss, honours, World Cup class, or group+knockout promotion automation.
- No public registration or public tournament builder.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.

## Expected final response to user

Summarize the friendly lifecycle changes, verification run, limitations, handoff document path, and pushed commit hash.
