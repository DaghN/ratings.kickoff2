# Worker prompt 005 — tournament lifecycle foundation

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded foundation/internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The Amiga tournament system now has:

- format templates and generated tournament builders
- tournament stages and fixtures
- tournament-level entrants
- entrant backfill and entrant status workflow
- active entrant guardrails for fixture assignment and result entry
- fixture-backed result entry with derived ratings/standings

The next foundation gap is lifecycle. We can create, mutate, and enter results, but the database does not formally distinguish draft tournaments, registration/preparation, running events, completed events, archived history, or void/test events. Before public navigation or user-facing creation, lifecycle needs to be ground truth.

## Goal

Add a conservative tournament lifecycle foundation to `ko2amiga_db` and internal tooling.

Classification: `foundation` / `internal ops`

## Required scope

1. Add lifecycle ground-truth fields to `tournaments`.
2. Add internal CLI support for safe lifecycle transitions.
3. Integrate lifecycle defaults into import and generated tournament builders/browser create path.
4. Add lifecycle verification.
5. Update docs and create handoff.
6. Commit and push when done.

## Lifecycle model

Use a conservative status model. Suggested statuses:

- `draft` — created but not ready for result entry
- `registration` — roster being assembled
- `ready` — fixtures/entrants prepared, can start
- `running` — results can be entered
- `completed` — event finished
- `archived` — historical/locked completed event
- `void` — abandoned/test/invalid event

If you choose different labels, justify the choice in the handoff.

Recommended columns:

- `lifecycle_status` enum or varchar with constrained values
- `started_at` datetime nullable
- `completed_at` datetime nullable

Consider whether `created_at` / `updated_at` already exist or should be added. Add only if justified and low-risk.

## Defaults and compatibility

Historical Access-imported tournaments should be treated as completed or archived. Choose the safer default and document it.

Generated future/live tournaments should not silently look like historical completed events. Builders and browser create path should set a live-oriented default, probably `draft` or `ready`. Pick one and document why.

Be careful with existing generated browser smoke tournaments in the local DB. Provide a backfill/migration path or verification rule that handles them.

## Transition policy

Add internal CLI command(s), suggested under:

`python -m scripts.amiga fixtures set-tournament-status`

The command should support:

- `--tournament-id N`
- `--status STATUS`
- `--dry-run`
- optional `--note` only if you add a place to store notes; otherwise skip notes

Transition rules should be conservative but not overbuilt. At minimum:

- Refuse unknown statuses.
- Refuse moving imported historical tournaments away from completed/archived unless explicitly justified.
- Refuse `completed` if scheduled fixtures remain unplayed, unless a `--force` flag is explicitly added and documented.
- Refuse result entry for `completed`, `archived`, or `void` tournaments.
- Decide whether result entry is allowed in `ready` or only `running`; document and implement consistently.

Do not build public lifecycle UI in this slice.

## Files/areas to inspect first

Read before editing:

- `docs/amiga-data-contract.md`
- `docs/amiga-tournament-format-vision.md`
- `docs/archive/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`
- `scripts/amiga/sql/005_tournament_formats.sql`
- `scripts/amiga/sql/006_tournament_fixtures.sql`
- `scripts/amiga/sql/007_tournament_entrants.sql`
- `scripts/amiga/import_access.py`
- `scripts/amiga/tournament_builder.py`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `site/public_html/amiga/ops/fixtures.php`
- `scripts/export_ko2amiga_db.ps1`

## Implementation guidance

Prefer a new SQL file:

`scripts/amiga/sql/008_tournament_lifecycle.sql`

Make schema application idempotent in the same spirit as existing scripts/import handling.

Update `import_access.py` schema apply order.

If adding columns to `tournaments`, the staging export schema dump will include them; no new table part is needed unless you introduce a new table.

Update generated tournament creation:

- Python kitchen marathon builder
- Python group+knockout builder
- PHP internal browser kitchen create path

Update result entry guardrails:

- Python `record_fixture_result`
- PHP browser result entry

Do not break legacy imported games or replay.

## Documentation requirements

Update:

- `docs/amiga-data-contract.md`
- `scripts/amiga/README.md`

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Schema/data implications
- Behavior added or changed
- Tests/checks run with exact commands and results
- Any local data backfill/migration performed
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- Python compile for changed Python modules.
- PHP lint for `site/public_html/amiga/ops/fixtures.php` if changed, using Laragon PHP if available.
- Apply the new SQL locally if needed.
- Verify lifecycle columns/statuses exist.
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga verify-tournament-formats`
- Builder dry-run smoke for kitchen marathon.
- Builder dry-run smoke for group+knockout.
- Lifecycle transition smoke on a temporary generated tournament.
- Negative smoke: result entry is refused for a completed/archived/void tournament.

If any command cannot be run, state why.

## Git requirements

Commit and push when done.

Important:

- Inspect `git status` before staging.
- Do not commit unrelated pre-existing local changes unless this prompt explicitly includes them.
- In your handoff, list any unrelated files intentionally left unstaged.
- Do not use destructive git commands.
- Do not force push.

Suggested commit message:

`Add Amiga tournament lifecycle foundation.`

## Non-goals

- No public lifecycle UI.
- No public registration UI.
- No newcomer/player creation.
- No KOA naming policy.
- No Swiss pairing engine.
- No honours derivation.
- No broad refactor of browser fixture ops.

## Expected final response to user

Summarize what you implemented, tests run, limitations, and the commit hash pushed.
