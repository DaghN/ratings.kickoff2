# Agent handoff — browser organizer shell

## Goal

Implement prompt-019 first slice: turn `/amiga/ops/fixtures.php` into a navigable tournament organizer shell with tabbed views, friendly league create UX, player autocomplete at create, POST-redirect-GET after successful create, and form repopulation on create validation errors.

## Classification

`internal ops` / `UX implementation` / `browser organizer workflow`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — organizer shell, tab panels, PRG + session flash, create-league flow (players first, then details), bug fixes
- `site/public_html/js/amiga-organizer-player-picker.js` — Amiga realm autocomplete for league create (`Add to league`, not profile navigation)
- `site/public_html/stylesheets/amiga-tournament.css` — tab/chip/panel styles + organizer player-search dropdown
- `docs/amiga-data-contract.md` — ops page described as tabbed tournament organizer
- `scripts/amiga/README.md` — browser ops notes for organizer views and create redirect
- `docs/orchestration/agent-handoffs/2026-06-08-019-browser-organizer-shell.md` — this handoff

## Behavior added/changed

### Core shell (commit `1a4ecd0`)

1. **Chrome** — page title/H1 **Tournament organizer**; intro describes create → players → preview → start → results happy path.
2. **`view` query param** — `setup`, `players`, `fixtures`, `table`, `results`, `advanced`. Default `fixtures` when `tournament_id > 0`, else `setup`.
3. **Tab navigation** — horizontal tabs when a tournament is open; only the active panel renders primary content.
4. **Panel placement** — create on Setup (no tournament); lifecycle on Setup (tournament open); entrants on Players; fixture table + result entry on Fixtures; standings on Table; Results placeholder; stage placement + fixture status filter on Advanced.
5. **PRG** — session flash; successful `create_kitchen` redirects to `view=fixtures`; other POST actions redirect to mapped views.
6. **Create error** — stays on Setup; preserves name, date, country, format, selected players.

### Follow-up polish (commit after `1a4ecd0`)

7. **Runtime fixes** — `AMIGA_FIXTURE_OPS_VIEWS` and draft state initialized after helper definitions; removed duplicate `AMIGA_FIXTURE_GENERATED_BY_PREFIXES` (already in `amiga_tournament_lib.php` via include chain).
8. **Player autocomplete at create** — `amiga-organizer-player-picker.js` uses `/api/player_search.php?realm=amiga`; isolated CSS/classes so site-header `player-search.js` does not hijack clicks to profile pages.
9. **Create flow order** — **Players** (search + chips) first, then **League details**, then **Create league** button.
10. **Removed console chrome** — no top **Tournament id** picker; no comma-separated player-id fallback; no instructional helper line under search.
11. **Recent leagues** — renamed list; **Open** links (no id column); **Create new league** link when viewing an open tournament.

## Exact commands/tests run and results

```powershell
git status --short --branch
# ## main...origin/main
#  M site/public_html/amiga/ops/fixtures.php
#  M site/public_html/stylesheets/amiga-tournament.css
# ?? site/public_html/js/amiga-organizer-player-picker.js

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
```

## Browser/manual checks run, if any

- Dagh verified create-flow UX interactively during follow-up (autocomplete, ordering, id picker removal).
- Automated `Invoke-WebRequest` to localhost docroot still not used as gate; staging/Laragon path is operator-dependent.

## Schema/data implications

None. No migrations; same POST actions and backend functions.

## Public/internal boundary notes

- Ops page remains password-gated with `once=amiga-fixtures-one-shot`.
- No public registration or player creation in browser.
- Public live pages unchanged.

## Risks/limitations/not verified

- **Session flash** — first use of PHP sessions on this page; verify session save path on staging.
- **Friendly lifecycle** — raw transition dropdown still on Setup (slice 020).
- **Fixture/table presentation** — ids/keys still visible on Fixtures tab (slices 021–022).
- **Results tab** — placeholder only; entry remains on Fixtures for this slice.
- **Withdraw/replace** — still on Players tab, not Advanced (slice 023).

## Commit hash and push target

- `1a4ecd0383f1f779fc8aea5f0dc474a8e768c7e9` — Add browser organizer shell.
- `b13397b421dd59e4c4b959fe6ddcae1fb98d43eb` — Polish organizer create flow and fix setup bugs.

Push target: `origin/main`

## Recommended next steps

1. **prompt-020** — friendly Start/Complete buttons on Setup; hide raw lifecycle dropdown.
2. **prompt-021** — round-grouped fixtures, empty league table from entrants, hide fixture ids on main tabs.
3. Staging re-export/sync before demo use.
