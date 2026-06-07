# Worker prompt 001 — tournament entrants foundation

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded foundation slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The project is moving from legacy `koatd.mdb` phase-string tournament structure toward explicit, professional-grade tournament ground truth in `ko2amiga_db`.

Current foundation already includes:

- `tournament_format_templates`
- `tournaments.format_template_id`, `format_overrides`, `has_league`, `has_cup`
- `tournament_stages`
- `tournament_stage_players`
- `tournament_fixtures`
- nullable `amiga_games.fixture_id`
- fixture-backed result entry via CLI and internal browser ops
- derived standings/ratings/catalog stats that remain rebuildable

The next foundation gap is tournament-level entrants/roster ground truth. Right now `tournament_stage_players` can represent players in stages, but there is no canonical tournament entrant/registration table. This is a blocker for best-in-class tournament creation, future Swiss support, newcomer handling, player withdrawal/replacement, and public registration flows.

## Goal

Add a tournament-level entrants foundation for future live tournaments.

The goal is **not** to build public registration UI yet. This is a ground-truth schema + internal tooling slice.

## Required scope

1. Add DDL for a new ground table, tentatively named `tournament_entrants`.
2. Integrate the new table into import/schema/drop/export order where appropriate.
3. Update internal builders so newly generated tournaments populate entrants before or alongside stage players.
4. Add CLI/internal helper functions to list and verify entrants.
5. Update docs and create a worker handoff document.

## Schema expectations

Design conservatively, matching existing MySQL style.

The table should support at least:

- tournament id
- player id
- seed number
- entrant status, e.g. `registered`, `withdrawn`, `replaced` or a similarly conservative enum
- optional display name snapshot or note if justified
- created timestamp

Think carefully about whether `display_name_snapshot` belongs now. If included, explain why. If deferred, explain why.

Constraints should protect core truth:

- FK to `tournaments`
- FK to `amiga_players`
- unique tournament/player
- useful tournament/seed lookup

Do **not** add newcomer/player creation in this slice. That is a later KOA naming-policy job.

## Files/areas to inspect first

Read these before editing:

- `docs/amiga-data-contract.md`
- `docs/amiga-tournament-format-vision.md`
- `scripts/amiga/sql/005_tournament_formats.sql`
- `scripts/amiga/sql/006_tournament_fixtures.sql`
- `scripts/amiga/import_access.py`
- `scripts/export_ko2amiga_db.ps1`
- `scripts/amiga/tournament_builder.py`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`

## Implementation guidance

Prefer a new SQL file:

`scripts/amiga/sql/007_tournament_entrants.sql`

Make schema application idempotent in the same spirit as existing schema scripts/import handling.

Update drop/truncate/export ordering carefully. Respect foreign keys.

Update builder behavior:

- `create_kitchen_marathon_tournament` should add all selected players as tournament entrants.
- `create_group_knockout_tournament` should add all selected players as tournament entrants.
- Stage players should remain stage-specific; entrants are tournament-level registration truth.

Add helper/CLI support where it fits best, likely in `scripts/amiga/tournament_fixtures.py` or a new small module if you judge that cleaner:

- list entrants for a tournament
- verify entrant integrity

Verification should catch at least:

- stage players not present as active/registered tournament entrants
- fixture players not present as active/registered tournament entrants
- duplicate or invalid entrant rows should already be blocked by constraints

Do not implement public UI in this slice.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`

The handoff must include:

- Goal
- Classification: `foundation`
- Files changed
- Schema/data implications
- Behavior added or changed
- Tests/checks run with exact commands and results
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run focused checks. At minimum:

- Python compile for changed Python modules.
- Fixture integrity / entrant verification command(s).
- Dry-run builder smoke for kitchen marathon.
- Dry-run builder smoke for group+knockout.
- If schema was applied locally, verify the new table exists and builders populate it.

Also run any existing relevant checks that are cheap and clearly applicable, e.g.:

- `python -m scripts.amiga verify-tournament-formats`

If a check cannot be run, state exactly why in the handoff.

## Git requirements

Commit and push when done.

Do not commit unrelated local changes, generated cache files, secrets, or user edits. Inspect `git status` carefully. Do not use destructive git commands. Do not force push.

Use a concise commit message consistent with the repository style, for example:

`Add Amiga tournament entrants foundation.`

## Non-goals

- No public registration UI.
- No newcomer/player creation.
- No KOA naming convention implementation.
- No Swiss pairing engine.
- No broad refactor of fixture/result-entry UI.
- No destructive cleanup of existing browser smoke data unless explicitly needed and safe.

## Expected final response to user

Summarize what you implemented, tests run, any limitations, and the commit hash pushed.
