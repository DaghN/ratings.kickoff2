# Worker prompt 013 — read-only live public view

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded public-UI slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The foundation and internal-ops guardrails are complete through worker job `011`. Job `012` recorded that Dagh's staging refresh succeeded:

- browser import preview showed `parts: 23`
- public `/amiga/tournaments.php` showed historical tournaments only
- a public tournament detail page loaded
- password-gated fixture ops loaded

The next product step is a **read-only live public view** for fixture-backed running events. This must not weaken the conservative public historical boundary: `/amiga/tournaments.php`, `/amiga/tournament.php`, and profile tournament links continue to show only `completed` / `archived` tournaments.

## Goal

Add a public, read-only live tournament view that shows selected fixture-backed `running` events with lifecycle and schedule/results context, without exposing public result entry or internal ops controls.

Classification: `public UI` / `internal ops boundary`

## Required outcome

Public users should be able to:

1. Open the Amiga **Live tournaments** tab.
2. See only live events that are intentionally eligible for public viewing.
3. Select a live event and inspect its lifecycle status, date/country metadata, entrants or players where available, fixture schedule grouped by stage/round/phase, and played scores.
4. Navigate to existing Amiga player/profile pages where the repo already has safe public links.

Public users must not be able to:

- enter results
- assign fixture players
- change lifecycle state
- create tournaments
- see an embedded ops password or password-bearing ops URL
- use this page to discover draft/ready/internal smoke events

## Files/areas to inspect first

Read before editing:

- `site/public_html/amiga/live-tournaments.php`
- `site/public_html/amiga/tournaments.php`
- `site/public_html/amiga/tournament.php`
- `site/public_html/amiga/ops/fixtures.php`
- `site/public_html/includes/amiga_tournament_lib.php`
- `site/public_html/stylesheets/amiga-tournament.css`
- `docs/amiga-data-contract.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-006-browser-lifecycle-controls.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-012-staging-sync-rehearsal.md`

## Implementation guidance

Keep this slice small and product-safe.

Recommended shape:

- Use `/amiga/live-tournaments.php` as the public entry point.
- It may be an index + selected detail in one file, or it may link to a new public detail page such as `/amiga/live-tournament.php?id=N` if that is cleaner.
- Remove any public link that includes `pwd=coffee` or otherwise exposes the passworded fixture manager.
- If you keep a link to internal ops for local/operator convenience, do not include the password in the URL and make it clearly separate from the public read-only path.
- Prefer Amiga PHP helper functions for live read queries if the template would otherwise accumulate large raw SQL blocks.
- Keep styling in the existing Amiga/site chrome (`k2-site`, `amiga-tournament.css`, `k2-table` patterns).

Public live eligibility must be explicit and conservative:

- Require `tournaments.lifecycle_status = 'running'`.
- Require fixture-backed generated structure (`tournament_stages` / `tournament_fixtures`) rather than legacy imported Access rows.
- Do not include `draft`, `registration`, `ready`, `completed`, `archived`, or `void`.
- If the repo does not already have a public-live flag, prefer a small code-level allowlist/config mechanism over a schema migration in this slice. Document how Dagh selects a tournament for public live display.
- Do not broaden `AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES`; historical pages stay completed/archived only.

Fixture display should be read-only and derived from ground truth:

- Show stage name/key/type and fixture ordering in a stable order.
- For scheduled fixtures, show player names when assigned and a clear placeholder when slots are unassigned.
- For played fixtures, show player names and regulation score from the attached `amiga_games` row.
- For void fixtures, show a muted/void state if they are present.
- Avoid calculating future standings or honours in this slice unless existing helpers already provide a safe, simple read.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md` — document the public live read path and its boundary from historical pages and ops.

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-08-013-read-only-live-public-view.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Behavior added/changed
- Exact commands/tests run and results
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

If practical, also do a local browser/manual check against `http://ratingskickoff.test/amiga/live-tournaments.php` and record:

- no password-bearing ops URL appears in the public page source/UI
- non-running generated tournaments are not listed
- a selected running live tournament displays fixtures read-only

If a local browser check cannot be run, state why and rely on lint plus data verification.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Inspect `git status` before staging and list unrelated files intentionally left unstaged.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Add read-only Amiga live tournament view.`

## Non-goals

- No public result entry.
- No public tournament builder or registration.
- No browser entrant onboarding; that is the next planned slice.
- No Swiss, group+KO promotion, honours, or format expansion.
- No schema migration unless you discover an unavoidable blocker and document it before proceeding.
- No changes to historical public visibility (`completed` / `archived` only).
- No staging export/import refresh.
- No broad fixture manager refactor.

## Expected final response to user

Summarize the public live view, the public/internal boundary, verification run, limitations, and commit hash pushed.
