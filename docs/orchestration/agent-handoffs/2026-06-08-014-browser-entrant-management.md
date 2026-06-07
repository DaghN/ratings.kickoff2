# Agent handoff — browser entrant management

## Goal

Add password-gated browser ops support for listing, searching, adding, withdrawing, and replacing tournament entrants on generated Amiga tournaments, matching CLI guardrails so operators do not need the CLI for basic entrant work.

## Classification

`internal ops` / `ground truth`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — entrant helper functions, POST actions, entrants UI (list, search, add/withdraw/replace)
- `docs/amiga-data-contract.md` — browser entrant ops boundary notes
- `scripts/amiga/README.md` — browser entrant parity notes
- `docs/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md` — this handoff

## Behavior added/changed

- On `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=...&tournament_id=N`, generated tournaments now show:
  - **Entrant list** — player id, name, seed, status, note; withdraw and replace actions for `registered` rows.
  - **Player search** — GET `player_search` by id or name fragment (bounded to 20 results); exact id lookup even when name is ambiguous.
  - **Add existing entrant** — POST `add_entrant` when lifecycle is `draft`, `registration`, or `ready`; optional seed/note; success hint to use stage placement separately.
  - **Withdraw** — POST `withdraw_entrant`; refuses games/played fixtures; clears scheduled unplayed fixture slots and removes stage-player rows in a transaction.
  - **Replace** — link sets `replace_player_id`; search results offer replace; POST `replace_entrant`; preserves old seed; updates scheduled fixtures/stage players; marks old row `replaced`.
- Imported Access tournaments show a muted CLI-only message (no entrant UI).
- Guardrails mirror CLI: generated-only, no duplicate active entrants, no reactivation of `withdrawn`/`replaced`, lifecycle check on add.

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

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Browser Entrant Smoke" --event-date 2026-06-08 --country Denmark --player-ids 1,2,3,4
# created tournament_id=636

# Local HTTP POST smokes (http://ratingskickoff.test):
# add_entrant player 5 — flash: Registered player #5 as entrant #95
# add_entrant player 1 — error: already a registered entrant
# withdraw_entrant player 4 — flash: 3 fixture slots cleared, 1 stage-player row removed
# replace_entrant 3→6 — flash: Replaced player #3 with #6 (entrant #96, seed 3)
# set lifecycle running via CLI; add_entrant player 7 — error: lifecycle running refused

python -m scripts.amiga fixtures list-entrants --tournament-id 636
# 6 rows: withdrawn 4, replaced 3, registered 1/2/5/6

python -m scripts.amiga fixtures verify-entrants --tournament-id 636
# OK

python -m scripts.amiga fixtures cleanup-generated --tournament-id 636
# deleted

python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
python -m scripts.amiga verify-tournament-formats
# all OK after cleanup
```

## Browser/manual checks run

- **HTTP POST** smokes via `curl.exe` against `http://ratingskickoff.test/amiga/ops/fixtures.php` (add, duplicate refusal, withdraw, replace, running lifecycle refusal) — all behaved as expected.
- **Not run:** interactive browser automation (MCP browser); server-rendered search GET UI verified indirectly via same page load after POST redirects.

## Schema/data implications

- No schema migration.
- Mutations touch `tournament_entrants`, `tournament_stage_players`, and `tournament_fixtures` (scheduled unplayed slots) with transactions on withdraw/replace.
- Admin notes use `[date] withdrawn/replaced by fixtures browser ops` prefix (distinct from CLI command labels).

## Public/internal boundary notes

| Surface | Entrant management |
|---------|-------------------|
| `/amiga/ops/fixtures.php` | Full list/search/add/withdraw/replace (password-gated) |
| `/amiga/live-tournament.php`, public tournament pages | Read-only; no entrant mutations |
| CLI `fixtures add-entrant` / `onboard-newcomer` / `withdraw-entrant` / `replace-entrant` | Still available; player creation and newcomer onboarding remain CLI-only |

## Risks/limitations/not verified

- No browser player creation or KOA name suggestion (`onboard-newcomer` deferred to CLI).
- No automatic stage placement or fixture generation for late entrants.
- Replace flow requires clicking “Replace…” then searching (two-step UX).
- Seed collision when manually setting `--seed-no` / form seed is not enforced (same as CLI).
- Staging export/import refresh not run.
- Interactive browser click-through not automated.

## Commit hash and push target

_(filled after commit)_

Push target: `origin/main`

## Recommended next steps

- Browser stage placement (`place-entrant` parity) for late entrants.
- Optional: unify admin-note action strings between CLI and browser if audit trail should be identical.
- Staging re-export before next import (`scripts/export_ko2amiga_db.ps1`).
