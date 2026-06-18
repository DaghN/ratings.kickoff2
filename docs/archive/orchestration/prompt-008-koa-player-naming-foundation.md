# Worker prompt 008 — KOA player naming foundation

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded foundation slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

We now have fixture-backed tournaments, entrants, lifecycle, browser ops controls, and a refreshed staging export package. The next strategic gap is **player identity for newcomers**.

Dagh explicitly wants to respect KOA naming tradition. Example from KOA forum guidance:

> If the new guy's name is Mark Bentley, he should be entered as Mark Be as there is already a Mark B.

The system does not need public newcomer UI yet, but internal tournament tooling needs a professional-grade foundation for checking/suggesting canonical player names and safely creating players before adding them as entrants.

## Goal

Add an internal, KOA-aware player naming/player creation foundation for `amiga_players`.

Classification: `ground truth` / `internal ops`

## Required outcome

Operators should be able to:

1. Check whether a proposed Amiga player name is usable.
2. Get KOA-style suggestions for a full newcomer name.
3. Create a new `amiga_players` row through an internal CLI command, with `--dry-run` support.
4. Refuse likely duplicate/conflicting names before insert.

Keep this CLI/internal only. No public UI and no browser ops UI in this slice.

## Files/areas to inspect first

Read before editing:

- `scripts/amiga/player_names.py`
- `scripts/amiga/import_access.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/sql/001_core.sql`
- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/archive/orchestration/amiga-tournament-orchestration-model.md`
- Recent handoffs, especially:
  - `docs/archive/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`
  - `docs/archive/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md`
  - `docs/archive/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`

## Implementation guidance

Prefer adding a new module rather than overloading fixture code, for example:

- `scripts/amiga/player_registry.py`

Then expose it through `scripts/amiga/__main__.py`, for example:

- `python -m scripts.amiga players check-name --name "Mark Be"`
- `python -m scripts.amiga players suggest-name --full-name "Mark Bentley"`
- `python -m scripts.amiga players create --name "Mark Be" --country "England" --dry-run`
- `python -m scripts.amiga players create --name "Mark Be" --country "England"`

Exact command names can differ if there is a better local pattern, but keep them discoverable and documented.

Build on existing `scripts/amiga/player_names.py` normalization:

- trim
- collapse whitespace
- strip trailing period
- casefold identity checks

Add helpers there or in the new module as appropriate.

## KOA-style suggestion policy

Implement this as **conservative suggestion logic**, not an irreversible product decision.

Suggested behavior:

- For a full name with at least first + surname tokens, produce `First S`, then `First Su`, `First Sur`, etc. until the suggestion is not already taken under the current identity rules.
- Preserve readable casing from input.
- If first-name + abbreviation candidates are exhausted or ambiguous, return a clear refusal/reason rather than inventing strange names.
- If input is already short/canonical-like, validate it as-is.
- Detect collisions case-insensitively and after whitespace/trailing-period normalization.
- Do not silently auto-merge with existing players.

Examples to aim for:

- If `Mark B` exists, `suggest-name --full-name "Mark Bentley"` should suggest `Mark Be`.
- If `Mark Be` also exists, it should continue to `Mark Ben`, and so on.
- `check-name --name "  Mark   B. "` should normalize to `Mark B` and report collision if `Mark B` exists.

Do not hard-code player ids or rely on a particular Mark row existing. If test data does not contain the exact example, use existing local names to demonstrate collision behavior, or implement unit-level tests around pure functions.

## Creation rules

`players create` should:

- Require a non-empty candidate name.
- Normalize before insert.
- Refuse if normalized/casefolded identity already exists.
- Refuse if exact `amiga_players.name` unique key would collide.
- Accept `--country` defaulting to empty string.
- Set `display=1` unless an existing local convention says otherwise.
- Support `--dry-run` that prints the row that would be inserted and rolls back or avoids writing.
- Return/print the created player id on success.

Do not create tournament entrants in this slice. Player creation and entrant registration remain separate operations.

## Documentation requirements

Update:

- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

If helpful, add a short section to the orchestration model noting that KOA naming now has internal CLI support but public UX remains deferred.

Create handoff:

`docs/archive/orchestration/agent-handoffs/2026-06-07-008-koa-player-naming-foundation.md`

The handoff must include:

- Goal
- Classification
- Files changed
- Behavior added
- Exact commands/tests run and results
- Any sample command output
- Schema/data implications
- Risks/limitations/not verified
- Commit hash and push target
- Recommended next steps

## Verification requirements

Run and report exact commands/results:

- `python -m compileall scripts/amiga`
- `python -m scripts.amiga players check-name ...` for an available and a colliding name
- `python -m scripts.amiga players suggest-name ...`
- `python -m scripts.amiga players create ... --dry-run`
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`
- `git status --short --branch` before staging and before final response

If you add focused unit tests, run them too.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Do not create real players just to test unless you explicitly explain why and the data mutation is acceptable. Prefer `--dry-run` and pure-function tests.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Add KOA-aware Amiga player naming tools.`

## Non-goals

- No public newcomer/player registration page.
- No browser ops UI for player creation.
- No automatic entrant creation.
- No schema change unless you find a strong reason and document it before committing.
- No staging export/import refresh.
- No player merge tooling.

## Expected final response to user

Summarize the internal player naming/create tooling, tests run, limitations, and commit hash pushed.
