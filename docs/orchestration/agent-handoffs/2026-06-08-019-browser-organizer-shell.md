# Agent handoff — browser organizer shell

## Goal

Implement prompt-019 first slice: turn `/amiga/ops/fixtures.php` into a navigable tournament organizer shell with tabbed views, friendly league create UX, server-side player picker at create, POST-redirect-GET after successful create, and form repopulation on create validation errors.

## Classification

`internal ops` / `UX implementation` / `browser organizer workflow`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — organizer chrome, `view` state, tab panels, PRG + session flash, create league form with player chips/search
- `site/public_html/stylesheets/amiga-tournament.css` — minimal tab/chip/panel styles
- `docs/amiga-data-contract.md` — ops page described as tabbed tournament organizer
- `scripts/amiga/README.md` — browser ops notes for organizer views and create redirect
- `docs/orchestration/agent-handoffs/2026-06-08-019-browser-organizer-shell.md` — this handoff

## Behavior added/changed

1. **Chrome** — page title/H1 **Tournament organizer**; intro describes create → players → preview → start → results happy path.
2. **`view` query param** — `setup`, `players`, `fixtures`, `table`, `results`, `advanced`. Default `fixtures` when `tournament_id > 0`, else `setup`.
3. **Tab navigation** — horizontal tabs when a tournament is open; only the active panel renders primary content.
4. **Panel placement** — create league + recent list on Setup (no tournament); lifecycle on Setup (tournament open); entrants on Players; fixture table + result entry on Fixtures; standings on Table; Results placeholder; stage placement + fixture status filter on Advanced.
5. **Create league UX** — renamed from kitchen marathon; round-robin format labels; server-side player search with add/remove chips and hidden `player_ids[]`; match-count hint; comma-separated ids only in collapsed Advanced fallback.
6. **PRG** — session flash (`$_SESSION['amiga_ops_flash']`); successful `create_kitchen` redirects to `tournament_id` + `view=fixtures`; other POST actions redirect to mapped views (entrants → players, results/assign → fixtures, lifecycle → setup, stage place → advanced).
7. **Create error** — stays on Setup without redirect; preserves name, date, country, format, selected players.
8. **Recent tournaments** — link label **Open**; targets `view=fixtures`.

## Exact commands/tests run and results

```powershell
git status --short --branch
# ## main...origin/main
#  M docs/amiga-data-contract.md
#  M scripts/amiga/README.md
#  M site/public_html/amiga/ops/fixtures.php
#  M site/public_html/stylesheets/amiga-tournament.css

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

- Attempted `Invoke-WebRequest` to `http://localhost/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee` — **404** (local docroot path not verified in this session). Interactive tab/create/redirect checks deferred to Dagh on Laragon/staging.

## Schema/data implications

None. No migrations; same POST actions and backend functions.

## Public/internal boundary notes

- Ops page remains password-gated with `once=amiga-fixtures-one-shot`.
- No public registration or player creation in browser.
- Public live pages unchanged.

## Risks/limitations/not verified

- **Session flash** — first use of PHP sessions on this page; verify session save path works on staging (fallback not implemented).
- **Friendly lifecycle** — raw transition dropdown still on Setup (slice 020).
- **Fixture/table presentation** — ids/keys still visible on Fixtures tab (slices 021–022).
- **Results tab** — placeholder only; entry remains on Fixtures for this slice.
- **Create form when tournament open** — intentionally hidden; create only when no tournament selected (Setup).
- **Withdraw/replace** — still on Players tab, not Advanced (slice 023 may move edge-case controls).
- No smoke tournament created via browser in this job.

## Commit hash and push target

_(filled after commit)_

Push target: `origin/main`

## Recommended next steps

1. **prompt-020** — friendly Start/Complete buttons on Setup; hide raw lifecycle dropdown.
2. **prompt-021** — round-grouped fixtures, empty league table from entrants, hide fixture ids on main tabs.
3. Dagh: manual browser pass on Laragon — create league with chips → lands on Fixtures; validation error preserves draft; tab links keep context.
4. Staging re-export/sync before demo use (not part of this slice).
