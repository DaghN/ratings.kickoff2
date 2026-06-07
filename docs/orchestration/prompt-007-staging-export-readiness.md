# Worker prompt 007 — staging export readiness for entrants/lifecycle

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded operations/readiness slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

Recent slices added important ground-truth changes:

- `tournament_entrants`
- entrant backfill/status/guardrails
- `tournaments.lifecycle_status`, `started_at`, `completed_at`
- internal browser lifecycle controls

Multiple handoffs note: **staging re-export has not been run**. Before the next staging import or browser test on staging, we need to verify the export/import package is current and includes the new schema/data correctly.

## Goal

Refresh and verify the `ko2amiga_db` staging export/import package for the current Amiga tournament schema and data.

Classification: `migration` / `internal ops`

## Required scope

1. Inspect the current export/import scripts and staging import docs.
2. Run the local staging export script if safe and supported.
3. Verify the manifest/parts include entrants and lifecycle-ready schema.
4. Update docs if part counts or instructions are stale.
5. Commit and push appropriate tracked export/docs changes.
6. Create handoff.

## Files/areas to inspect first

Read before acting:

- `scripts/export_ko2amiga_db.ps1`
- `site/public_html/amiga/_import/README.md` if present
- `site/public_html/amiga/_import/ko2amiga_manifest.json`
- `scripts/amiga/README.md`
- `docs/amiga-staging-handoff.md`
- `docs/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`
- `docs/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md`
- `docs/orchestration/agent-handoffs/2026-06-07-006-browser-lifecycle-controls.md`

## Implementation guidance

Run the export script if possible:

`powershell -ExecutionPolicy Bypass -File scripts/export_ko2amiga_db.ps1`

or the repository's established equivalent.

After export, inspect `git status` carefully. Some generated SQL dump parts may be ignored or intentionally untracked. Do not force-add ignored files unless the repository clearly tracks them or the prompt/docs indicate they should be tracked.

At minimum, verify:

- manifest part list includes `ko2amiga_05_entrants.sql`
- schema dump includes `tournament_entrants`
- schema dump includes `lifecycle_status`, `started_at`, `completed_at`
- data export order remains FK-safe:
  - `tournament_format_templates`
  - `tournaments`
  - `amiga_players`
  - `tournament_entrants`
  - `tournament_stages`
  - `tournament_stage_players`
  - `tournament_fixtures`
  - `amiga_games`
  - derived tables

If the export script cannot run because local tooling is missing, do not fake success. Document the blocker and still verify script/docs consistency as far as possible.

## Documentation requirements

Update docs only if stale:

- `scripts/amiga/README.md`
- `site/public_html/amiga/_import/README.md`
- `docs/amiga-staging-handoff.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-007-staging-export-readiness.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Export command run and result
- Manifest/part count before and after
- Which generated files were committed and why
- Which generated files were intentionally left untracked/ignored and why
- Verification performed
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results where possible:

- `git status --short --branch` before staging
- export command
- `git status --short --branch` after export
- manifest inspection showing entrant part
- schema inspection showing lifecycle columns
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`

If any command cannot be run, state why.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated pre-existing local changes unless this prompt explicitly includes them.
- Do not force-add ignored dump files without clear repo precedent.
- In your handoff, list any unrelated or ignored files intentionally left unstaged.
- Do not use destructive git commands.
- Do not force push.

Suggested commit message:

`Refresh Amiga staging export manifest.`

If no generated tracked files change and only docs/handoff change, use a more accurate message.

## Non-goals

- No schema design changes unless a real export blocker is found.
- No public UI.
- No staging server import unless explicitly requested by the user.
- No cleanup of local smoke tournaments.
- No player creation or KOA naming work.

## Expected final response to user

Summarize export readiness, files committed, tests run, limitations, and commit hash pushed.
