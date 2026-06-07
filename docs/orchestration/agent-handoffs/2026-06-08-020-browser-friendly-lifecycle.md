# Agent handoff — browser friendly lifecycle

## Goal

Implement prompt-020 Phase B slice: replace raw lifecycle dropdown on Setup with organizer-friendly status labels and Start / Mark complete / Void actions, while preserving existing guardrails and moving raw transitions to Advanced.

## Classification

`internal ops` / `UX implementation` / `browser organizer workflow`

## Files changed

- `site/public_html/amiga/ops/fixtures.php` — friendly status helpers, organizer lifecycle actions, Setup UI, Advanced raw dropdown
- `site/public_html/stylesheets/amiga-tournament.css` — scoped lifecycle status/action styles
- `docs/amiga-data-contract.md` — browser lifecycle UX boundary notes
- `scripts/amiga/README.md` — organizer lifecycle action summary
- `docs/orchestration/agent-handoffs/2026-06-08-020-browser-friendly-lifecycle.md` — this handoff

## Behavior added/changed

### Friendly status labels

Organizer-facing mapping (raw value kept in Setup meta + Advanced only):

| `lifecycle_status` | Organizer label |
|--------------------|-----------------|
| `draft`, `registration` | **Not started** |
| `ready` | **Ready to start** |
| `running` | **In progress** |
| `completed`, `archived` | **Finished** |
| `void` | **Void** |

Tournament header shows the friendly badge only.

### Setup tab actions

- **Start tournament** — `POST action=organizer_lifecycle_action` + `lifecycle_action=start_tournament`. From `draft` or `registration`, transitions `→ ready → running` in sequence via `amiga_fixture_set_lifecycle_status()`. From `ready`, transitions `→ running` only.
- **Mark complete** — when `running` and no scheduled fixtures remain.
- **Void tournament** — secondary button when `running`, no games exist, and void is allowed.
- When scheduled fixtures remain during `running`, an explanation hint is shown instead of Mark complete.
- Finished/void/imported tournaments show read-only copy; no primary dropdown.

### Advanced tab

- Raw **Transition to (internal)** dropdown retained for single-step ops (`draft`/`registration`→`ready`, `ready`→`running`, etc.) with same guardrails.
- `registration` now allowed to transition to `ready` in browser (parity with draft).

### PRG / flash

- Organizer lifecycle POST redirects to `view=setup` with friendly flash messages.
- Advanced raw transition POST redirects back to `view=advanced` (uses posted `view`).

### Guardrails preserved

- Imported tournaments: read-only in browser.
- `completed` refused when scheduled fixtures remain.
- `void` refused when games exist.
- Result entry still requires `running`.
- No browser `--force`.

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

- Not run in this worker session (no Laragon browser smoke). Recommended manual checks:
  - Setup tab on a draft generated league shows friendly status + **Start tournament**.
  - Start moves to `running` with success flash; Fixtures tab allows result entry.
  - Running league with scheduled fixtures shows Mark complete unavailable + explanation.
  - Imported tournament shows read-only lifecycle copy.
  - Advanced tab still exposes raw transition dropdown for generated tournaments.

## Schema/data implications

None. No migrations.

## Public/internal boundary notes

- Ops page remains password-gated; lifecycle changes only on generated tournaments.
- Public historical and live read paths unchanged.

## Risks/limitations/not verified

- **Browser smoke** — interactive Start/Complete flow not verified in this session.
- **Start gate** — checkpoint §5 suggested gating start on entrant/fixture completeness; not added in this slice (same as pre-020 guardrails).
- **Empty league table / fixture presentation** — deferred to slices 021–022.

## Commit hash and push target

- _(filled after commit)_

Push target: `origin/main`

## Recommended next steps

1. **prompt-021** — round-grouped fixtures, empty league table from entrants, hide fixture ids on main tabs.
2. Manual browser smoke on staging for Start / Mark complete / imported read-only.
3. **prompt-022** — dedicated Results tab for score entry.
