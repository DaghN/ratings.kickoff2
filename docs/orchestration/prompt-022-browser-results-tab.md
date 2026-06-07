# Worker prompt 022 - browser results tab

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing the fourth browser organizer workflow slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing PHP/CSS style and preserve unrelated local changes.

## Strategic context

Worker `018` defined the organizer-first workflow:

`docs/orchestration/browser-organizer-workflow-checkpoint.md`

Workers `019`-`021` implemented the first organizer UI slices:

- `019`: Tournament organizer shell, tabs, create redirect, player picker/chips.
- `020`: Friendly Setup lifecycle status and Start / Mark complete actions.
- `021`: Readable Fixtures schedule and Table preview from entrants before results exist.

The next friction is score entry. The Fixtures tab is now a read-first match schedule, but result entry still lives there. The Results tab is still only a placeholder. This job should make Results the primary place for entering scores while keeping Fixtures focused on previewing the schedule.

Classification: `internal ops` / `UX implementation` / `browser organizer workflow`

## Goal

Make the Results tab the clear operator workspace for entering match results:

- Fixtures tab remains read-first.
- Results tab lists scheduled playable matches with compact score-entry forms.
- Played results are visible for context.
- Existing result-entry guardrails and derived processing remain unchanged.

## Required outcome

Implement Phase D from `docs/orchestration/browser-organizer-workflow-checkpoint.md`.

### 1. Move result entry to Results tab

In `site/public_html/amiga/ops/fixtures.php`:

- Remove or demote score-entry forms from the main Fixtures tab.
- On Fixtures, keep a clear hint/link like "Enter scores on the Results tab" when the tournament is running.
- Results tab should render the primary score-entry list.

### 2. Results tab content

The Results tab should show:

- A clear heading such as **Enter results**.
- If lifecycle is not `running`, explain that results unlock after **Start tournament** on Setup.
- If lifecycle is `running`, list scheduled fixtures with both players assigned and no attached game.
- Each playable row should show:
  - round/leg group label where available
  - Player A vs Player B
  - goals inputs
  - optional extra/notes field only if already supported and not too noisy
  - **Save result** button
- Played fixtures should be visible in a secondary list or section so the operator can see what has already been entered.
- Void or incomplete fixtures should be muted or omitted from primary entry, with a short explanation if needed.

Reuse helpers from `021` for fixture grouping/labels where possible.

### 3. POST/redirect behavior

Keep the `019`/`020` PRG/session-flash pattern:

- `record_result` POST should redirect to `view=results` after success or error, not back to Fixtures.
- Keep `once`, `pwd`, `tournament_id`, and relevant `status` state.
- Preserve existing derived processing behavior (`amiga_process_completed_game`).

### 4. Fixtures tab remains preview-first

Fixtures tab should not feel like the score-entry workspace:

- No large inline score forms on the main schedule.
- It may show played scores and status badges.
- It may link to Results.
- Technical IDs and assignment controls remain on Advanced.

### 5. Preserve behavior and guardrails

Do not change:

- `amiga_fixture_record_result()` validation
- result entry requiring lifecycle `running`
- active entrant checks
- fixture assignment validation
- lifecycle behavior
- public pages
- database schema

This is a presentation/workflow slice, not a result-processing rewrite.

### 6. Minimal styling

In `site/public_html/stylesheets/amiga-tournament.css`, add scoped styles only as needed for:

- results entry groups
- compact score-entry rows
- played-results context
- non-running hint

Do not do a broad visual redesign.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/browser-organizer-workflow-checkpoint.md`
- `docs/orchestration/agent-handoffs/2026-06-08-021-browser-fixtures-table-preview.md`
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

`docs/orchestration/agent-handoffs/2026-06-08-022-browser-results-tab.md`

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

- A not-started league: Results tab explains that result entry unlocks after Start.
- A running league: Results tab shows scheduled playable fixtures with score forms.
- Saving a result redirects back to Results and shows a clear flash.
- Fixtures tab remains readable schedule-first and links to Results.
- Played result appears in Results context and Table still updates.
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

`Add organizer results tab.`

## Non-goals

- No late-entrant fixture generation.
- No Advanced panel polish beyond moving/demoting result controls.
- No Swiss, honours, World Cup class, or group+knockout promotion automation.
- No public registration or public tournament builder.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.

## Expected final response to user

Summarize the Results tab changes, verification run, limitations, handoff document path, and pushed commit hash.
