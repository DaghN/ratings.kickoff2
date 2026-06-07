# Agent handoff — browser lifecycle controls

## Goal

Add password-gated internal browser lifecycle controls to `/amiga/ops/fixtures.php` so operators can move generated tournaments from `draft` through `running` without the CLI, while keeping conservative transition rules and result-entry guardrails.

## Classification

`internal ops`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — lifecycle helpers, POST `set_lifecycle_status`, lifecycle panel UI, result-entry forms hidden unless `running`
- `docs/amiga-data-contract.md` — browser ops lifecycle section
- `scripts/amiga/README.md` — browser lifecycle transition notes
- `docs/orchestration/agent-handoffs/2026-06-07-006-browser-lifecycle-controls.md` — this handoff

## Schema/data implications

- No schema changes. Uses existing `tournaments.lifecycle_status`, `started_at`, and `completed_at` from migration 008.
- Local smoke tournament `627` left in `running` with two recorded games (cannot `cleanup-generated` once games exist).

## Behavior added or changed

- **Lifecycle panel:** selected tournament shows `lifecycle_status`, `started_at`, and `completed_at`.
- **Browser transitions (generated only):** `draft`→`ready`, `ready`→`running`, `running`→`completed` when no scheduled fixtures remain, `running`→`void` when no games exist.
- **Refusals:** imported Access tournaments refuse all browser lifecycle changes; `completed` while scheduled fixtures remain; `void` when games exist; unknown or disallowed target statuses; no `--force` in browser.
- **Timestamps:** `started_at` set on first transition to `running`; `completed_at` set on first transition to `completed` (mirrors CLI).
- **Result entry:** record forms shown only when `lifecycle_status = running`; backend guard unchanged.

## Tests/checks run with exact commands and results

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php
# No syntax errors detected

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# lifecycle_status=running count=5
# lifecycle_status=completed count=603
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Browser Lifecycle Smoke" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
# created tournament_id=627

# HTTP smoke via work.ratingskickoff.test (Laragon):
curl.exe -s "http://work.ratingskickoff.test/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee&tournament_id=627"
# Shows lifecycle_status=draft, transition target ready, result entry blocked

curl.exe -s -X POST -d "once=amiga-fixtures-one-shot&pwd=coffee&action=set_lifecycle_status&tournament_id=627&lifecycle_status=ready" http://work.ratingskickoff.test/amiga/ops/fixtures.php
# Tournament #627 lifecycle: draft → ready.

curl.exe -s -X POST -d "once=amiga-fixtures-one-shot&pwd=coffee&action=set_lifecycle_status&tournament_id=627&lifecycle_status=running" http://work.ratingskickoff.test/amiga/ops/fixtures.php
# Tournament #627 lifecycle: ready → running.; Record buttons visible

curl.exe -s -X POST -d "once=amiga-fixtures-one-shot&pwd=coffee&action=set_lifecycle_status&tournament_id=627&lifecycle_status=completed" http://work.ratingskickoff.test/amiga/ops/fixtures.php
# flash--error: browser transition to 'completed' is not allowed (6 scheduled fixtures)

python -m scripts.amiga fixtures record-result --fixture-id 90 --goals-a 2 --goals-b 1
# game_id=27421 (allowed while running)

curl.exe -s -X POST -d "once=amiga-fixtures-one-shot&pwd=coffee&action=set_lifecycle_status&tournament_id=627&lifecycle_status=void" http://work.ratingskickoff.test/amiga/ops/fixtures.php
# flash--error: browser transition to 'void' is not allowed (games exist)

curl.exe -s -X POST -d "once=amiga-fixtures-one-shot&pwd=coffee&action=set_lifecycle_status&tournament_id=1&lifecycle_status=ready" http://work.ratingskickoff.test/amiga/ops/fixtures.php
# flash--error: imported historical tournament; lifecycle changes not allowed in browser

python -m scripts.amiga fixtures set-tournament-status --tournament-id 627 --status running --force
# restored running after brief CLI draft test
```

## Browser/local smoke data created/changed/cleaned up

- Created tournament `627` ("Browser Lifecycle Smoke") via CLI; transitioned `draft`→`ready`→`running` via HTTP POST; recorded games `27421`, `27422`.
- Not cleaned up: games prevent `cleanup-generated`; tournament left in `running` for verify-lifecycle.

## Risks/limitations/not verified

- Browser lifecycle logic is duplicated in PHP (not shared with Python CLI module).
- `registration`, `archived`, and arbitrary reverse transitions remain CLI-only.
- No `--force` in browser; operators must use CLI to complete with unplayed fixtures or change imported tournaments.
- HTTP smoke used `work.ratingskickoff.test` (Laragon); `localhost` path returned 404 in this environment.
- Staging re-export not run.

## Commit hash and push target

- Implementation commit: _(filled after commit)_
- Push target: `origin/main`

## Recommended next steps

1. Extract shared lifecycle transition rules into a single module if PHP/Python drift becomes painful.
2. Consider `registration` workflow in browser when entrant UI lands.
3. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
4. Public tournament pages should filter on lifecycle when exposed.
