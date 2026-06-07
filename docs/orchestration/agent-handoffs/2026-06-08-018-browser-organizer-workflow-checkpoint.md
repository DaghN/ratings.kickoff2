# Agent handoff — browser organizer workflow checkpoint

## Goal

Produce an opinionated UX architecture checkpoint for evolving `/amiga/ops/fixtures.php` from a technical console into a clear league organizer workflow, and define the first concrete implementation slice (`prompt-019`).

## Classification

`internal ops` / `UX architecture` / `product workflow`

## Files inspected

- `site/public_html/amiga/ops/fixtures.php` — full page structure, POST actions, UI sections (create, lifecycle, entrants, stage players, fixtures, standings, tournament list)
- `site/public_html/includes/amiga_tournament_lib.php` — public visibility, generated-tournament helpers
- `scripts/amiga/tournament_builder.py` — `create_kitchen_marathon_tournament`, round-robin plan
- `scripts/amiga/tournament_fixtures.py` — lifecycle, entrants, fixtures (referenced via README and prior handoffs)
- `docs/amiga-data-contract.md` — layers and ops boundaries
- `scripts/amiga/README.md` — browser ops capabilities and CLI parity
- `docs/orchestration/agent-handoffs/2026-06-08-014-browser-entrant-management.md`
- `docs/orchestration/agent-handoffs/2026-06-08-015-browser-stage-placement.md`
- `docs/orchestration/agent-handoffs/2026-06-08-016-browser-fixture-slot-assignment-ux.md`
- `docs/orchestration/agent-handoffs/2026-06-08-017-fixture-stage-assignment-guardrail.md`
- `docs/orchestration/amiga-tournament-architecture-checkpoint.md` — strategic context

## Files changed

- `docs/orchestration/browser-organizer-workflow-checkpoint.md` — new checkpoint (diagnosis, happy path, views, mapping, league-first rules, hide/demote, navigation, non-goals, phased plan, prompt-019 spec)
- `docs/orchestration/agent-handoffs/2026-06-08-018-browser-organizer-workflow-checkpoint.md` — this handoff

## Key decisions

1. **League-first, single page** — keep `fixtures.php` with a `view` tab param rather than a multi-route rewrite.
2. **Reuse all guarded POST actions** — no schema migration; presentation and navigation change only in early slices.
3. **Happy path vocabulary** — Create league, Choose players, Preview fixtures, Start tournament, Enter result, View table; lifecycle enum demoted to friendly status on Setup.
4. **Stage players off the happy path** — kitchen marathon auto-places entrants; stage UI moves to Advanced (slice 023).
5. **Empty league table before kickoff** — show entrant rows at zero in Table tab (slice 021); derived standings unchanged underneath.
6. **PRG everywhere** — post-create redirect to `tournament_id` + `view=fixtures`; session flash; form repopulation only on create validation failure.
7. **prompt-019 scope** — shell only: tabs, rename, create redirect, player multi-select at create, PRG for main POST actions; defer friendly lifecycle buttons and fixture/table redesign to 020–022.
8. **Explicit deferrals** — late-entrant generation, Swiss, honours, public registration/builder, group+knockout browser create.

## Proposed first implementation slice

**prompt-019 — Browser organizer shell: tabs, create redirect, player picker**

See full acceptance criteria in `docs/orchestration/browser-organizer-workflow-checkpoint.md` §10.

Summary: rename chrome, introduce `view` tabs, move create form to Setup, replace comma-separated player ids with search-based multi-select, PRG after create and other POSTs, repopulate create form on error.

## Risks/limitations/not verified

- No interactive browser inspection in this job; diagnosis is from source review and prior handoffs 014–017.
- `fixtures.php` is ~2600 lines; tab refactor in 019 is moderate touch risk — keep slice bounded to panel wrapping, not inner logic rewrites.
- Session flash requires PHP session availability on ops page (verify Laragon/staging; fallback query flash if sessions unavailable).
- Player multi-select without JavaScript needs a deliberate server-side pattern (repeated add/remove via POST) — worker 019 must pick one and document it.
- Staging export/import not refreshed.
- Hub link to organizer views deferred to slice 024.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main
```

No PHP or Python files edited; no lint or verify commands required.

## Commit hash and push target

*(filled after commit)*

Push target: `origin/main`

## Recommended next steps

1. Orchestrator issues **prompt-019** from checkpoint §10.
2. After 019–022 land, Dagh staging re-export (`scripts/export_ko2amiga_db.ps1`) + WinSCP sync before demo use.
3. Update `docs/orchestration/amiga-tournament-architecture-checkpoint.md` job sequence when 019 is scheduled.
