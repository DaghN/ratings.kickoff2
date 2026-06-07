# Agent handoff — browser stage placement

## Goal

Add password-gated browser ops support for placing registered tournament entrants into stages on generated Amiga tournaments, matching CLI `place-entrant` / `add-stage-player` guardrails so operators can complete the late-entrant workflow without switching to CLI.

## Classification

`internal ops` / `ground truth`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — stage list/load helpers, `amiga_fixture_place_stage_entrant`, POST `place_stage_entrant`, stage players UI
- `docs/amiga-data-contract.md` — browser stage placement boundary notes and late-entrant workflow
- `scripts/amiga/README.md` — browser stage placement parity notes
- `docs/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md` — this handoff

## Behavior added/changed

- On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, generated tournaments now show a **Stage players** section below entrant management:
  - Lists each stage (key, name, type) and current stage players (seed, group key).
  - **Place or update** form — POST `place_stage_entrant` selects stage + registered entrant with optional seed/group key.
  - Upserts `tournament_stage_players` (insert or update seed/group when player already in stage).
  - Success flash hints that fixture assignment remains separate.
- Guardrails mirror CLI: generated-only, lifecycle `draft`/`registration`/`ready`, active `registered` entrant required; refuses imported Access tournaments, non-entrants, withdrawn/replaced players, and `running`/`completed`/`archived`/`void` lifecycles.
- Does not create players, entrants, or fixtures; does not alter fixture assignment or result entry behavior.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main
#  M docs/amiga-data-contract.md
#  M scripts/amiga/README.md
#  M site/public_html/amiga/ops/fixtures.php

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php
# No syntax errors detected

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Browser Stage Placement Smoke" --event-date 2026-06-08 --country Denmark --player-ids 1,2,3,4
# created tournament_id=637, stage_id=41

python -m scripts.amiga fixtures add-entrant --tournament-id 637 --player-id 5 --seed-no 5 --note "late signup smoke"
# entrant_id=101 status=registered

# Local HTTP POST smokes (http://ratingskickoff.test):
# place_stage_entrant player 5 seed 5 — flash: Placed player #5 in stage overall (stage id 41)
# place_stage_entrant player 5 seed 6 group A — flash: Updated player #5 in stage overall
# place_stage_entrant player 99 — error: Player 99 is not a tournament entrant
# set lifecycle running via CLI; place_stage_entrant player 1 — error: lifecycle running refused

python -m scripts.amiga fixtures cleanup-generated --tournament-id 637
# deleted

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
python -m scripts.amiga verify-tournament-formats
# all OK after cleanup
```

## Browser/manual checks run

- **HTTP POST** smokes via `curl.exe` against `http://ratingskickoff.test/amiga/ops/fixtures.php` (place, update seed/group, non-entrant refusal, running lifecycle refusal) — all behaved as expected.
- **Not run:** interactive browser automation (MCP browser); stage list UI verified indirectly via same page render after POST.

## Schema/data implications

- No schema migration.
- Mutations upsert `tournament_stage_players` only (`ON DUPLICATE KEY UPDATE seed_no, group_key`).
- No changes to `tournament_entrants`, `tournament_fixtures`, or `amiga_games` from stage placement.

## Public/internal boundary notes

| Surface | Stage placement |
|---------|------------------|
| `/amiga/ops/fixtures.php` | List stages/stage players; place/update registered entrant (password-gated) |
| `/amiga/live-tournament.php`, public tournament pages | Read-only; no stage mutations |
| CLI `fixtures add-stage-player` / `place-entrant` | Still available; same guardrails |

Late-entrant workflow (browser or CLI): add entrant → place in stage → assign fixture slots.

## Risks/limitations/not verified

- No automatic fixture generation/rescheduling after stage placement.
- No bulk stage placement.
- Withdrawn/replaced entrant refusal not separately smoke-tested (same `amiga_fixture_require_active_entrant` as CLI).
- Seed collision when manually setting seed is not enforced (same as CLI).
- Staging export/import refresh not run.
- Interactive browser click-through not automated.

## Commit hash and push target

_(filled after commit)_

Push target: `origin/main`

## Recommended next steps

- Staging re-export before next import (`scripts/export_ko2amiga_db.ps1`).
- Optional: browser fixture slot assignment UX improvements for late entrants.
- Unit tests for `amiga_fixture_place_stage_entrant` validation (PHP or shared contract tests).
