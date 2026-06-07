# Worker prompt 009 — internal entrant onboarding flow

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

You are implementing one bounded internal-ops slice for the Amiga tournament system. GPT-5.5 is acting as strategic orchestrator; you are the implementation worker. Follow the repository's existing style and preserve unrelated local changes.

## Strategic context

The current foundation now supports:

- fixture-backed generated tournaments
- tournament entrants as ground truth
- lifecycle controls
- KOA-aware internal player name checking/suggestions/creation

The practical gap is that an operator still has to stitch several commands together to put a newcomer into a generated tournament. We want an internal onboarding command that safely connects player identity to tournament registration without adding public UI yet.

## Goal

Add internal CLI tooling for onboarding tournament entrants, including existing players and optional newcomer creation through the KOA naming tools.

Classification: `ground truth` / `internal ops`

## Required outcome

Operators should be able to:

1. Register an existing player as a tournament entrant with seed/note support.
2. Dry-run that registration safely.
3. Create a newcomer using the KOA-aware player naming checks, then register that player as an entrant in one atomic operation.
4. Refuse unsafe duplicate player names or invalid tournament states.

Keep this CLI/internal only. No public UI and no browser ops UI in this slice.

## Files/areas to inspect first

Read before editing:

- `scripts/amiga/tournament_fixtures.py`
- `scripts/amiga/player_registry.py`
- `scripts/amiga/player_names.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`
- `docs/orchestration/agent-handoffs/2026-06-07-008-koa-player-naming-foundation.md`
- Recent entrant handoffs:
  - `docs/orchestration/agent-handoffs/2026-06-07-001-tournament-entrants-foundation.md`
  - `docs/orchestration/agent-handoffs/2026-06-07-003-entrant-status-workflow.md`
  - `docs/orchestration/agent-handoffs/2026-06-07-004-entrant-guardrails-fixture-entry.md`

## Implementation guidance

Prefer building on existing helpers:

- `tournament_fixtures.add_tournament_entrant`
- `tournament_fixtures.list_entrants`
- `player_registry.check_player_name`
- `player_registry.create_player`
- `player_registry.suggest_player_name`

Add clear CLI commands. The exact naming can follow local style, but these are suggested:

```powershell
python -m scripts.amiga fixtures add-entrant --tournament-id N --player-id P --seed-no 5 --note "late signup" --dry-run

python -m scripts.amiga fixtures onboard-newcomer --tournament-id N --full-name "Mark Bentley" --country "England" --seed-no 5 --dry-run
python -m scripts.amiga fixtures onboard-newcomer --tournament-id N --name "Mark Be" --country "England" --seed-no 5 --dry-run
```

For `onboard-newcomer`, support either:

- `--name`: use an explicit canonical KOA display name, validated through `player_registry`
- `--full-name`: suggest the first available KOA-style name, then use that suggestion

If both are provided, either refuse or define a clear precedence. Prefer refusing to avoid ambiguity.

## Rules and guardrails

Registration rules:

- Generated tournaments only, unless existing code has an established broader rule. Imported Access tournaments should not accept internal entrant mutations by default.
- Tournament should be in `draft`, `registration`, or `ready`. Refuse `running`, `completed`, `archived`, and `void` unless there is a compelling existing rule to allow otherwise.
- Refuse duplicate active entrant registration unless the existing behavior is intentionally idempotent and clearly reported.
- Preserve `withdrawn` / `replaced` semantics. Do not silently reactivate such entrants unless the command explicitly says it does, and do not add such reactivation in this slice unless necessary.
- `--dry-run` must not persist either player creation or entrant registration.
- Newcomer onboarding should be atomic: if entrant registration fails, the newly created player must not be left committed.

Player rules:

- Reuse the naming module and DB checks from Worker 008.
- Do not bypass KOA name collision checks.
- Do not create real players during tests unless unavoidable; prefer dry-runs and transaction rollback.

Stage/fixture rules:

- Do not automatically insert `tournament_stage_players` or alter fixtures in this slice unless the command explicitly asks for it and the behavior is documented.
- Entrant registration is tournament-level ground truth; stage placement remains a separate operation for now.

## Documentation requirements

Update:

- `scripts/amiga/README.md`
- `docs/amiga-data-contract.md`

Create handoff:

`docs/orchestration/agent-handoffs/2026-06-07-009-internal-entrant-onboarding.md`

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
- `python -m unittest scripts.amiga.test_player_names -v`
- `python -m scripts.amiga fixtures add-entrant ... --dry-run` against a generated tournament and existing player
- `python -m scripts.amiga fixtures onboard-newcomer ... --dry-run`
- a refusal case for duplicate player name or invalid lifecycle
- `python -m scripts.amiga fixtures verify`
- `python -m scripts.amiga fixtures verify-entrants`
- `python -m scripts.amiga fixtures verify-lifecycle`
- `python -m scripts.amiga verify-tournament-formats`
- `git status --short --branch` before staging and before final response

If local generated tournament ids vary, choose one from `fixtures list-entrants` / local DB inspection and state which id was used.

## Git requirements

Commit and push when done.

Important:

- Do not commit unrelated local changes.
- Do not create persistent real players or entrants just for smoke testing unless you explain why and verify cleanup/acceptability.
- Do not edit Access source data.
- Do not run destructive git commands.
- Do not force push.

Suggested commit message:

`Add internal Amiga entrant onboarding tools.`

## Non-goals

- No public registration page.
- No browser ops UI.
- No automatic stage-player or fixture assignment.
- No player merge/rename tooling.
- No schema changes unless a blocker is discovered and documented.
- No staging export/import refresh.

## Expected final response to user

Summarize the entrant onboarding commands, safety guarantees, tests run, limitations, and commit hash pushed.
