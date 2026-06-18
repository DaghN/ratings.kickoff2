# Agent handoff — browser fixture slot assignment UX

## Goal

Improve the password-gated fixture assignment UI on generated Amiga tournaments so operators assign scheduled fixture slots from stage-scoped player selects instead of raw numeric ids, while preserving existing `amiga_fixture_assign_players` guardrails and result-entry behavior.

## Classification

`internal ops` / `ground truth`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — `amiga_fixture_stage_players_by_stage`, stage_id in fixture query, stage-scoped assignment selects with numeric fallback
- `docs/amiga-data-contract.md` — browser fixture assignment boundary notes
- `scripts/amiga/README.md` — browser fixture assignment parity notes
- `docs/archive/orchestration/agent-handoffs/2026-06-08-016-browser-fixture-slot-assignment-ux.md` — this handoff

## Behavior added/changed

- On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, incomplete **scheduled** fixtures with no attached game show an **Assign** form:
  - When the fixture's stage has **≥2 stage players**, **Player A** / **Player B** are `<select>` controls populated from that stage only; existing slot assignments are preselected.
  - When the stage has **<2 stage players** on a generated tournament, a hint asks the operator to place entrants in the stage first; numeric player-id inputs remain as fallback.
  - Imported/non-generated tournaments without stage-player data keep numeric fallback only.
- POST `assign_players` unchanged; still calls `amiga_fixture_assign_players` (active registered entrants, tournament stage-player membership, distinct players, scheduled status, refuses attached games). No `running` lifecycle required for assignment.
- Result entry and other ops sections unchanged.

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

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Browser Fixture Assign Smoke" --event-date 2026-06-08 --country Denmark --player-ids 1,2,3,4
# created tournament_id=638, stage_id=42

# Cleared fixture 145 (both slots) and 146 (player B only) for smoke via local UPDATE

# GET http://ratingskickoff.test/...&tournament_id=638 — HTML contains <select name="player_a_id"> for fixtures 145/146

# Local HTTP POST smokes (http://ratingskickoff.test):
# assign_players fixture 145 players 1+2 — flash: Assigned players to fixture #145.
# assign_players fixture 146 players 3+4 (partial slot) — flash: Assigned players to fixture #146.
# assign_players fixture 147 players 1+1 — error: Fixture players must be different.
# add-entrant player 5 (not placed in stage); assign 1+5 on fixture 147 — error: Fixture players must already belong to the tournament.

python -m scripts.amiga fixtures cleanup-generated --tournament-id 638
# deleted

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
python -m scripts.amiga verify-tournament-formats
# all OK after cleanup
```

## Browser/manual checks run

- **HTTP GET** verified stage-scoped `<select>` elements render for incomplete scheduled fixtures on generated tournament 638.
- **HTTP POST** smokes via `curl.exe` (full assign, partial preselect assign, same-player refusal, non-stage-player refusal) — all behaved as expected.
- **Not run:** interactive browser automation (MCP browser); click-through of select widgets not automated.

## Schema/data implications

- No schema migration.
- Assignment still updates `tournament_fixtures.player_a_id` / `player_b_id` only.
- No changes to `tournament_entrants`, `tournament_stage_players`, or `amiga_games` from assignment UI.

## Public/internal boundary notes

| Surface | Fixture assignment |
|---------|-------------------|
| `/amiga/ops/fixtures.php` | Stage-scoped selects + POST `assign_players` (password-gated) |
| `/amiga/live-tournament.php`, public tournament pages | Read-only; no assignment |
| CLI `fixtures set-players` | Still available; same server guardrails |

Late-entrant workflow (browser or CLI): add entrant → place in stage → assign fixture slots.

## Risks/limitations/not verified

- Server-side assignment still validates tournament-level stage-player membership (same as CLI `set-players`), not fixture-stage-only membership; stage-scoped selects are a UI convenience.
- Numeric fallback remains when a stage has fewer than two stage players (or on non-generated tournaments).
- No automatic fixture generation/rescheduling after assignment.
- Withdrawn/replaced entrant refusal not separately smoke-tested for assignment (same `amiga_fixture_require_active_entrant` as before).
- Staging export/import refresh not run.
- Interactive browser select UX not automated.

## Commit hash and push target

`f271124` — Improve browser Amiga fixture assignment.

Push target: `origin/main`

## Recommended next steps

- Staging re-export before next import (`scripts/export_ko2amiga_db.ps1`).
- Optional: strengthen server-side assignment to require both players in the fixture's specific stage (would be a guardrail tightening, not yet implemented).
- Unit tests for assignment form rendering / `amiga_fixture_stage_players_by_stage`.
