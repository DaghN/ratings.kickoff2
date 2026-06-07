# Agent handoff — browser results tab

## Goal

Implement prompt-022 Phase D slice: dedicated Results tab for score entry; Fixtures tab remains read-first schedule preview.

## Classification

`internal ops` / `UX implementation` / `browser organizer workflow`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — `amiga_fixture_partition_for_results`, Results tab entry UI, Fixtures tab demoted (no inline score forms), `record_result` PRG to `view=results`
- `site/public_html/stylesheets/amiga-tournament.css` — scoped Results tab entry and played-context styles
- `docs/amiga-data-contract.md` — browser results entry boundary notes
- `scripts/amiga/README.md` — organizer results tab summary
- `docs/orchestration/agent-handoffs/2026-06-08-022-browser-results-tab.md` — this handoff

## Behavior added/changed

### Results tab (primary score entry)

- Heading **Enter results**.
- When lifecycle is not `running`, explains that entry unlocks after **Start tournament** on Setup (link).
- When `running`, lists playable scheduled fixtures (both players assigned, no game) grouped by round/leg/phase via existing schedule helpers.
- Each row: matchup label, goals inputs, **Save result** button; hidden `view=results` on POST.
- **Already entered** section lists played fixtures with scores for operator context.
- Void and incomplete fixtures omitted from entry with a short summary note.
- Imported tournaments: read-only message (no browser entry).

### Fixtures tab (read-first)

- Removed inline score-entry forms from match schedule rows.
- When `running` and playable fixtures exist, schedule intro links to Results tab.
- Per-row hints retained for not-started and unassigned slots.

### POST/redirect

- `record_result` success/error redirects to `view=results` (was `fixtures`).
- `amiga_fixture_record_result` and `amiga_process_completed_game` unchanged.

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
# DRY RUN: rolled back — smoke tournament_id=643, fixture_id=161, game_id=27427
```

## Browser/manual checks run, if any

- Not run in-browser (password-gated local ops page). CLI smoke path exercises create → result → derived processing with rollback.

## Schema/data implications

None. Presentation and redirect-only; no new tables or writes.

## Public/internal boundary notes

- Changes confined to `/amiga/ops/fixtures.php` (password-gated organizer). Public live and historical tournament pages unchanged.

## Risks/limitations/not verified

- Round grouping reuses `fixture_key` `-rNN-` parse; non–kitchen-marathon keys fall back to leg or phase label.
- No `extra` notes field on Results forms (backend supports it; omitted to keep rows compact).
- Browser UI not manually verified in this session.
- Advanced tab still has technical fixture table only (no result forms there).

## Commit hash and push target

- Commit: `c711aad`
- Push target: `origin/main`

## Recommended next steps

- **023** — Advanced panel polish (withdraw/replace relocation, status filter UX).
- **024** — Hub integration (`live-tournaments.php` links to organizer views).
- Manual browser pass: not-started league → Results hint; start → enter one result on Results → flash on Results → Fixtures schedule link → Table updates.
