# Worker prompt 021 - browser fixtures and table preview

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing the third browser organizer workflow slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing PHP/CSS style and preserve unrelated local changes.

## Strategic context

Worker `018` defined the organizer-first workflow:

`docs/orchestration/browser-organizer-workflow-checkpoint.md`

Workers `019` and `020` implemented the first organizer UI slices:

- `019`: Tournament organizer shell, tabs, create redirect, player picker/chips.
- `020`: Friendly Setup lifecycle status and Start / Mark complete actions.

The next friction is preview confidence. After creating a league, the organizer should immediately see a clean fixture list and a league table, even before any results exist. Today the Fixtures tab still exposes fixture ids, stage internals, and fixture keys, while the Table tab says there are no derived standings until the first result.

Classification: `internal ops` / `UX implementation` / `browser organizer workflow`

## Goal

Make the Fixtures and Table tabs useful as pre-tournament preview surfaces:

- Fixtures tab should read like a match schedule, not a fixture database table.
- Table tab should show all registered players at zero before the first result.
- Main tabs should hide debug ids/keys/stage internals that belong in Advanced.

## Required outcome

Implement Phase C from `docs/orchestration/browser-organizer-workflow-checkpoint.md`.

### 1. Fixtures tab: organizer-friendly schedule

In `site/public_html/amiga/ops/fixtures.php`, improve the main Fixtures tab presentation:

- Hide fixture id, `fixture_key`, `stage_key`, and `stage_type` from the main table/card view.
- Show readable match rows:
  - round/leg label if available or derivable
  - Player A vs Player B
  - status (`scheduled`, `played`, `void`) in a muted/friendly badge
  - score for played fixtures
  - result-entry controls may remain here for this slice, but should not dominate the read-first view
- Group or visually separate fixtures by round/leg where practical.

Preferred grouping:

- Use existing fixture ordering and derive a friendly group label from `fixture_key`, `leg_no`, or `phase_label`.
- For kitchen-marathon round robins, labels such as `Round 1`, `Round 2`, or `Leg 1` / `Leg 2` are enough.
- If robust round derivation is not practical, group by `leg_no` and document the limitation.

Keep the existing status filter behavior in Advanced. The Fixtures tab can continue to respect the current filter if already applied, but it should not surface the filter controls as primary UI.

### 2. Table tab: empty standings before results

When `amiga_tournament_standings` has no overall rows yet, the Table tab should still show registered entrants as an empty league table:

- Player names from active `registered` entrants.
- Games/W/D/L/GF/GA/GD/Pts all zero.
- Position can be blank, dash, or seed order.
- Add a short note like "No results yet - showing entrants at zero."

When derived standings rows exist, keep using them as the authoritative stats.

If some entrants are missing from derived standings after partial play, include zero/default rows only if that is safe and clear; otherwise document the limitation. Do not rewrite standings generation in this job.

### 3. Advanced tab keeps technical detail

Technical details removed from the main Fixtures tab should remain reachable in Advanced if already available or cheap to include:

- fixture id
- fixture key
- stage name/key/type
- status filter
- assignment forms for empty slots if they are too noisy for the main Fixtures tab

Do not delete operator capability. Demote debug/detail surfaces rather than removing them.

### 4. Preserve behavior and guardrails

Do not change:

- result-entry guardrails
- fixture assignment validation
- lifecycle rules
- stage placement behavior
- public pages
- database schema

This is a presentation/readability slice. It may add small PHP helpers for display rows, grouping, or empty standings rows.

### 5. Minimal styling

In `site/public_html/stylesheets/amiga-tournament.css`, add scoped styles only as needed for:

- fixture groups/round headings
- match rows/cards
- empty table note
- muted technical metadata if any remains

Do not do a broad visual redesign.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/browser-organizer-workflow-checkpoint.md`
- `docs/orchestration/agent-handoffs/2026-06-08-019-browser-organizer-shell.md`
- `docs/orchestration/agent-handoffs/2026-06-08-020-browser-friendly-lifecycle.md`
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

`docs/orchestration/agent-handoffs/2026-06-08-021-browser-fixtures-table-preview.md`

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

- Create or open a generated league with no results: Fixtures tab shows a readable schedule and Table tab shows all entrants at zero.
- Start tournament and record one result if using a disposable generated smoke tournament; Table tab still renders correctly.
- Advanced tab still exposes technical fixture details or explains where they remain available.
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

`Improve organizer fixtures and table preview.`

## Non-goals

- No dedicated Results tab redesign; result entry may remain on Fixtures for this slice.
- No late-entrant fixture generation.
- No Swiss, honours, World Cup class, or group+knockout promotion automation.
- No public registration or public tournament builder.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.

## Expected final response to user

Summarize the Fixtures/Table preview changes, verification run, limitations, handoff document path, and pushed commit hash.
