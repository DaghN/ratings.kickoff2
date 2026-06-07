# Worker prompt 019 - browser organizer shell

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing the first browser organizer workflow slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing PHP/CSS style and preserve unrelated local changes.

## Strategic context

Worker `018` produced the UX checkpoint:

`docs/orchestration/browser-organizer-workflow-checkpoint.md`

The current password-gated `/amiga/ops/fixtures.php` is technically capable but too console-like for a normal organizer. This job starts reshaping it around the happy path:

`create league -> choose players -> preview fixtures/table -> start tournament -> enter results`

This is **not** a full redesign. The first slice should create the organizer shell and navigation, improve league creation, and reduce raw-ID friction. Later slices will handle friendly lifecycle buttons, empty table presentation, fixtures/table/result redesign, and Advanced demotion.

Classification: `internal ops` / `UX implementation` / `browser organizer workflow`

## Goal

Turn `/amiga/ops/fixtures.php` into a navigable internal tournament organizer shell for league creation and tournament views:

- friendly page chrome
- tab-style `view` navigation
- create form only in the Setup context
- league create labels understandable to organizers
- player selection at create without requiring comma-separated raw IDs
- successful create redirects into the new tournament's Fixtures view
- validation errors preserve entered create values

## Required outcome

Implement the first slice from `docs/orchestration/browser-organizer-workflow-checkpoint.md` section 10.

### 1. Organizer chrome and view model

In `site/public_html/amiga/ops/fixtures.php`:

- Change authenticated page title/H1 from "Fixture manager" to **"Tournament organizer"**.
- Intro copy should describe the internal happy path in organizer language.
- Add a `view` query/post state with allowed values:
  - `setup`
  - `players`
  - `fixtures`
  - `table`
  - `results`
  - `advanced`
- Defaults:
  - when `tournament_id > 0`, default `view=fixtures`
  - when no tournament is selected, default `view=setup`
- Preserve existing `status` filtering for fixture rows, but treat it as an Advanced/fixture-table detail rather than primary navigation.

### 2. Tab navigation

Render tab-style navigation when a tournament is selected.

Tabs:

- Setup
- Players
- Fixtures
- Table
- Results
- Advanced

Requirements:

- Preserve `once`, `pwd`, `tournament_id`, and relevant `status`/search params where needed.
- Only render the active panel's primary content to reduce page clutter.
- If no tournament is selected, show Setup content plus the recent generated tournament list.
- Keep recent tournament links, but label them as **Open** and send users into `view=fixtures`.

### 3. Move existing sections into panels

Do this as a light wrapper/refactor, not a full content rewrite:

- Setup: create league form, tournament header/status/lifecycle block for now.
- Players: existing entrants/search/add/withdraw/replace section.
- Fixtures: existing fixture table and result-entry controls can stay here for this slice.
- Table: existing standings section.
- Results: may show a small placeholder pointing to Fixtures for now, or duplicate only the existing result-entry subset if easy. Do **not** do a broad result-entry redesign here.
- Advanced: existing stage players placement, fixture status filter/details, and other debug-ish controls that do not belong in the happy path. If moving all advanced controls is too risky, document what remains outside Advanced and why.

The goal is navigability and context, not perfect final placement.

### 4. Create league UX

Rename "Create kitchen marathon" to **"Create league"**.

Change labels:

- "Legs" -> "Round-robin format"
- `1` -> "Single round-robin"
- `2` -> "Home and away"
- Button: "Create league"

Replace the primary comma-separated `player_ids` text field with a friendlier selected-player list.

Acceptable implementation shapes:

- A server-rendered player search on the create form, adding/removing selected player ids through query/post state and hidden `player_ids[]` inputs.
- Or a small progressive-enhancement UI that uses the existing `/api/player_search.php?realm=amiga` endpoint to add selected players to hidden `player_ids[]` inputs.

Constraints:

- The form submission to create the tournament must use a validated unique list of selected player ids.
- The create path must still refuse fewer than two players and duplicate players.
- The raw comma-separated field may remain in Advanced only, or as a no-JS fallback clearly labelled "Advanced: player IDs"; it must not be the main create path.
- Do not create new players in the browser.
- Do not implement public registration.

### 5. Create redirect and form state

- On successful `create_kitchen`, use POST-Redirect-GET to the new tournament:
  - `?once=amiga-fixtures-one-shot&pwd=...&tournament_id={new_id}&view=fixtures`
- Show a success flash after redirect.
- On create validation error, keep the operator on Setup and preserve:
  - name
  - date
  - country
  - round-robin format
  - selected players
- For other POST actions, prefer redirecting back to the relevant view if practical:
  - entrant actions -> `players`
  - fixture assignment -> `fixtures`
  - result entry -> `fixtures` for this slice
  - lifecycle action -> `setup`
  - stage placement -> `advanced`
- If converting every action to PRG is too risky in this slice, at minimum do it for successful create and document the remaining POST reloads in the handoff.

### 6. Minimal styling

In `site/public_html/stylesheets/amiga-tournament.css`, add only small scoped styles needed for:

- organizer tabs
- selected-player chips/list if implemented
- active panel spacing

Keep the visual pass modest. Do not redesign tables in this job.

## Files/areas to inspect first

Read before editing:

- `docs/orchestration/browser-organizer-workflow-checkpoint.md`
- `site/public_html/amiga/ops/fixtures.php`
- `site/public_html/stylesheets/amiga-tournament.css`
- `site/public_html/api/player_search.php`
- `site/public_html/includes/player_search_bar.php`
- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`
- `docs/orchestration/agent-handoffs/2026-06-08-018-browser-organizer-workflow-checkpoint.md`

## Documentation requirements

Update if behavior changed:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`
- `docs/orchestration/browser-organizer-workflow-checkpoint.md` only if implementation intentionally diverges from the plan

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-08-019-browser-organizer-shell.md`

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

Run focused browser/local HTTP checks if practical:

- Open ops page with password and no `tournament_id`: Setup view is shown.
- Open an existing generated tournament: Fixtures view is default.
- Tab links preserve tournament context.
- Create league with selected players: redirects to the new tournament's Fixtures view.
- Create validation error: entered values and selected players remain visible.
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

`Add browser organizer shell.`

## Non-goals

- No empty league table from entrants yet.
- No friendly Start/Complete replacement for lifecycle dropdown yet.
- No broad result-entry redesign.
- No fixture table redesign beyond panel placement.
- No late-entrant fixture generation.
- No Swiss, honours, World Cup class, or group+knockout promotion automation.
- No public registration or public tournament builder.
- No schema migration unless a blocker is discovered and documented before proceeding.
- No staging export/import refresh.

## Expected final response to user

Summarize the organizer shell changes, how player selection works, verification run, limitations, handoff document path, and pushed commit hash.
