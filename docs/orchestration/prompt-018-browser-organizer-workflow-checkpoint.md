# Worker prompt 018 - browser organizer workflow checkpoint

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are doing a bounded product/UX architecture checkpoint for the Amiga tournament browser workflow. GPT-5.5 is acting as strategic orchestrator; you are the worker. This is primarily a design and sequencing job, not an implementation job. Follow the repository's existing documentation style and preserve unrelated local changes.

## Strategic context

Jobs `001`-`017` built the internal tournament backbone and guarded browser ops surface:

- generated tournaments, stages, fixtures, entrants, lifecycle, fixture assignment, and result entry exist
- `/amiga/ops/fixtures.php` is password-gated and can perform many internal operations
- the current browser UI exposes too much of the technical model: lifecycle labels, raw IDs, long page sections, forms that reset after validation errors, top-of-page reloads, and unclear next steps

Dagh does **not** want more late-entrant edge-case work right now. Late entrants are uncommon and should not dominate the next phase.

The important user need is the normal organizer workflow:

`create tournament -> choose format -> pick players -> preview table/fixtures -> start tournament -> enter results`

The next implementation work should make that primary path clear and usable, starting with league-style tournaments. Do not drift into Swiss, honours, public registration, or full format expansion yet.

## Goal

Produce an opinionated browser organizer workflow checkpoint that explains how the internal ops UI should evolve from a technical console into a clear tournament-running flow, then define the first concrete implementation slice.

Classification: `internal ops` / `UX architecture` / `product workflow`

## Required outcome

Create:

`docs/orchestration/browser-organizer-workflow-checkpoint.md`

The checkpoint must include:

1. A concise diagnosis of the current `/amiga/ops/fixtures.php` workflow problems.
2. The proposed happy path for a normal organizer, using friendly concepts rather than database terms.
3. A page/view structure proposal, for example tabs or sections such as `Setup`, `Players`, `Fixtures`, `Table`, and `Results`.
4. How friendly actions map to the existing technical model:
   - create tournament
   - choose league/cup-style format
   - select players
   - generate stages/fixtures
   - start tournament
   - enter results
   - show standings/table
5. A league-first design:
   - choose player list with search/select, not raw IDs
   - choose number of rounds/legs in understandable terms
   - show an empty league table as soon as players/structure exist
   - show generated fixtures before result entry
6. A decision on what to hide, demote, or rename from the current UI, especially:
   - raw lifecycle controls
   - raw player ID fields
   - stage/fixture internals
   - create form remaining the main viewport after tournament creation
7. Concrete navigation/state behavior:
   - redirect into the created tournament after creation
   - keep the operator near the relevant section after POST
   - preserve form values after validation errors
   - avoid top-of-page reload confusion where practical
8. Explicit non-goals and deferrals:
   - late-entrant fixture generation
   - Swiss
   - honours
   - public registration
   - public tournament builder
   - broad schema migration
9. A phased implementation plan with small reviewable slices.
10. A recommended `prompt-019` implementation slice, with enough detail that the orchestrator can turn it into a worker prompt.

## Files/areas to inspect first

Read before writing the checkpoint:

- `site/public_html/amiga/ops/fixtures.php`
- `site/public_html/includes/amiga_tournament_lib.php`
- `scripts/amiga/tournament_builder.py`
- `scripts/amiga/tournament_fixtures.py`
- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`
- `docs/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md`
- `docs/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md`
- `docs/orchestration/agent-handoffs/2026-06-08-016-browser-fixture-slot-assignment-ux.md`
- `docs/orchestration/agent-handoffs/2026-06-08-017-fixture-stage-assignment-guardrail.md`

## Guidance

Be opinionated. Dagh should not need to micromanage the UI.

Think like an organizer running a normal tournament, not like a database maintainer. The existing technical model is allowed to stay under the hood, but the user-facing browser path should speak in organizer actions:

- "Create league"
- "Choose players"
- "Preview fixtures"
- "Start tournament"
- "Enter result"
- "View table"

Prefer a design that reuses existing guarded operations rather than inventing new schema. If you think a small helper or endpoint would make the first implementation slice much cleaner, document it, but do not implement it in this job.

Do not propose a giant rewrite. The output should identify a first slice that can be built and reviewed independently.

## Documentation requirements

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-08-018-browser-organizer-workflow-checkpoint.md`

The handoff must include:

- Goal
- Classification
- Files inspected
- Files changed
- Key decisions
- Proposed first implementation slice
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

The worker's final chat response must explicitly include the checkpoint path, handoff document path, and pushed commit hash.

## Verification requirements

Run and report exact commands/results:

- `git status --short --branch` before staging and before final response
- No PHP lint is required unless PHP files are edited.
- No Python compile is required unless Python files are edited.

If you inspect the local browser UI manually, document what you observed. Browser automation is optional.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Do not edit runtime code unless a tiny documentation link is absolutely necessary; this is a checkpoint job.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Document browser organizer workflow checkpoint.`

## Non-goals

- No implementation of the new organizer UI in this job.
- No late-entrant fixture generation.
- No Swiss, honours, World Cup class, or group+knockout promotion automation.
- No public registration or public tournament builder.
- No staging export/import refresh.
- No schema migration unless you discover and document a blocker before proceeding.

## Expected final response to user

Summarize the recommended organizer workflow, first implementation slice, checkpoint path, handoff path, and pushed commit hash.
