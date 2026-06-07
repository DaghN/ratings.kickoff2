# Agent handoff — read-only live public view

## Goal

Add a public, read-only live tournament view for selected fixture-backed `running` events without weakening the historical public boundary (`completed` / `archived` only on `/amiga/tournaments.php` and `/amiga/tournament.php`).

## Classification

`public UI` / `internal ops boundary`

## Files changed

- `site/public_html/includes/amiga_tournament_lib.php` — public live allowlist, eligibility helpers, index/load/participants/fixture queries
- `site/public_html/amiga/live-tournaments.php` — public read-only index (removed password-bearing ops links)
- `site/public_html/amiga/live-tournament.php` — new public read-only detail page
- `site/public_html/stylesheets/amiga-tournament.css` — `.k2-amiga-live-view` styles
- `site/config/ko2amiga_config.local.php.example` — documented optional `$amigaPublicLiveTournamentIds`
- `docs/amiga-data-contract.md` — public live read path and boundary notes

## Behavior added/changed

- **Live tournaments tab** (`/amiga/live-tournaments.php`) is now a public read-only index. It lists only tournaments that are simultaneously: on the allowlist, `lifecycle_status = running`, and fixture-backed generated events.
- **Live tournament detail** (`/amiga/live-tournament.php?id=N`) shows lifecycle metadata, date/country, players (entrants or stage-player fallback), and fixtures grouped by stage with read-only scores.
- **Publishing:** add tournament ids to `AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS` in `amiga_tournament_lib.php` and/or `$amigaPublicLiveTournamentIds` in gitignored local config. Committed allowlist is empty (safe default).
- **Removed** public links containing `pwd=coffee`. Operator note links to `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` without a password query param.
- **Unchanged:** historical pages and profile links still use `AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES` (`completed`, `archived` only).

## Tests/checks run with exact commands and results

```text
git status --short --branch
## main...origin/main
(unrelated local docs left unstaged)

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/includes/amiga_tournament_lib.php
No syntax errors detected

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/live-tournaments.php
No syntax errors detected

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/live-tournament.php
No syntax errors detected

python -m scripts.amiga fixtures verify
OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
OK: every tournament with games has has_league or has_cup
```

**Local HTTP checks** (`http://ratingskickoff.test`):

- `live-tournaments.php` — OK: no `pwd=coffee` in page source; ops link present without password param; new read-only copy served.
- `live-tournament.php?id=627` without allowlist — shows “Live tournament not found.” (non-allowlisted running event not exposed).
- PHP helper smoke with `$amigaPublicLiveTournamentIds = [627]` — `load=Browser Lifecycle Smoke`, `index_count=1`, `fixture_groups=1`, `fixtures=6`.

## Schema/data implications

- No schema migration.
- Publishing is code/config allowlist only (`AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS` + optional local config array).

## Public/internal boundary notes

| Surface | Who | What |
|---------|-----|------|
| `/amiga/tournaments.php`, `/amiga/tournament.php`, profile links | Public | `completed` / `archived` only |
| `/amiga/live-tournaments.php`, `/amiga/live-tournament.php` | Public | Allowlisted `running` generated events, read-only |
| `/amiga/ops/fixtures.php` | Internal | Password-gated create/assign/result/lifecycle |

## Risks/limitations/not verified

- Committed allowlist is empty — staging/production show an empty live index until Dagh adds ids.
- No standings/honours/bracket advancement on the live detail page (deferred).
- HTTP 404 status code for missing live tournaments may not propagate through all local Apache/proxy setups; body text is correct.
- Staging browser pass not run by agent; relied on local `ratingskickoff.test` checks.

## Commit hash and push target

- `d840a5b` on `origin/main`

## Recommended next steps

- Publish a real running event by adding its id to `AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS` (or staging local config), then verify on staging.
- Next planned slice: browser entrant onboarding (internal ops), not public registration.
