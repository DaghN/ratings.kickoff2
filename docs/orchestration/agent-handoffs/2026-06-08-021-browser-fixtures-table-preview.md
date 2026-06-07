# Agent handoff — browser fixtures and table preview

## Goal

Implement prompt-021 Phase C slice: organizer-friendly Fixtures schedule and empty league Table preview before kickoff, while demoting technical fixture detail to Advanced.

## Classification

`internal ops` / `UX implementation` / `browser organizer workflow`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — schedule grouping helpers, Fixtures tab redesign, Table tab empty preview, Advanced technical fixture table + assignment forms
- `site/public_html/stylesheets/amiga-tournament.css` — scoped schedule and table preview styles
- `docs/amiga-data-contract.md` — browser fixtures/table preview boundary notes
- `scripts/amiga/README.md` — organizer preview summary
- `docs/orchestration/agent-handoffs/2026-06-08-021-browser-fixtures-table-preview.md` — this handoff

## Behavior added/changed

### Fixtures tab (read-first schedule)

- Replaced ops table (fixture id, key, stage type) with grouped **Match schedule** sections.
- Groups derive from `fixture_key` round segment (`overall-rNN-mMM` → `Round N`), else `leg_no`, else `phase_label`.
- Each row shows player names, friendly status badge (`Scheduled` / `Played` / `Void`), score when played.
- Compact result-entry form on scheduled matches when lifecycle is `running` (de-emphasized below matchup).
- Hints when results require start, or when slots need assignment on Advanced.
- Active status filter (from Advanced) still applies to the underlying query; noted in copy when set.

### Table tab (empty preview)

- When no `amiga_tournament_standings` overall rows exist, lists active `registered` entrants with all stats at zero and position `—`.
- Note: **No results yet — showing entrants at zero.**
- When derived standings exist, uses them unchanged (authoritative). No merge of missing entrants after partial play.

### Advanced tab

- Added **Fixture details (technical)** table: fixture id, key, leg, stage key/type, assignment forms, raw status, game id/score.
- Fixture status filter unchanged; copy updated to point here for assignment.
- `assign_players` POST redirects to posted `view` (default `advanced`).

### Data loading

- Entrants list loaded for all tournaments (including imported) to support Table preview; stage ops remain generated-only.

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

python -m scripts.amiga build-tournament smoke-fixture-result --player-ids 1,2
# DRY RUN: rolled back — smoke tournament_id=642, fixture_id=160, game_id=27426
```

## Browser/manual checks run, if any

- Not run in-browser (password-gated local ops page). Smoke CLI path above exercises create → result → derived processing with rollback.

## Schema/data implications

None. Presentation-only read helpers; no new tables or writes.

## Public/internal boundary notes

- Changes confined to `/amiga/ops/fixtures.php` (password-gated organizer). Public live and historical tournament pages unchanged.

## Risks/limitations/not verified

- Round grouping parses `fixture_key` `-rNN-` pattern; non–kitchen-marathon keys fall back to leg or phase label.
- Partial-play table does not backfill entrants missing from derived standings (documented).
- Browser UI not manually verified in this session.
- Result entry still on Fixtures tab (Results tab redesign deferred to 022).

## Commit hash and push target

_To be filled after commit/push._

## Recommended next steps

- **022** — Dedicated Results tab; further demote result entry from Fixtures.
- **023** — Advanced panel polish (withdraw/replace relocation if needed).
- Manual browser pass: create league → Fixtures grouped schedule → Table zero rows → Start → one result → Table derived rows.
