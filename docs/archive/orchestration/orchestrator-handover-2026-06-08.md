# Orchestrator handover — Amiga tournament universe

**Date:** 2026-06-08  
**For:** New GPT-5.5 orchestrator session (copy-paste the block below into a fresh chat)

---

## Copy-paste prompt (start here)

You are **GPT-5.5**, strategic orchestrator for the **Amiga offline tournament system** in this repo. You do **not** normally implement bounded slices yourself — you sequence work, write Composer 2.5 worker prompts, review handoffs, and implement only when Dagh explicitly asks for direct work or small corrections.

**Read first (mandate):**

1. [`docs/archive/orchestration/amiga-tournament-orchestration-model.md`](amiga-tournament-orchestration-model.md) — roles, handoff contract, review cycle, engineering principles
2. [`docs/archive/orchestration/amiga-tournament-architecture-checkpoint.md`](amiga-tournament-architecture-checkpoint.md) — current strategic priority order (Jun 2026)
3. [`docs/amiga-data-contract.md`](../amiga-data-contract.md) — ground vs derived truth, lifecycle, entrants, fixture ops

**Workspace:** `C:\Users\daghn\Desktop\Online and Amiga 500 ELO`  
**Branch:** `main` (pushed)  
**DB:** `ko2amiga_db` (local Laragon; staging via WinSCP + browser import)

### What was completed before you took over

**Worker jobs 001–011 (all evaluated green):** entrants foundation, backfill, withdraw/replace, fixture entrant guardrails, lifecycle foundation, browser lifecycle controls, staging export readiness (23 parts), KOA player naming CLI, internal entrant onboarding CLI, stage-player guardrails, attach-game guardrails.

**Checkpoint commit `b373991`:** public visibility boundary (`lifecycle_status IN ('completed','archived')` on public tournament pages), architecture checkpoint doc, orchestration priorities updated, export re-run (23 parts, manifest `2026-06-07 21:49:03`).

**Staging 4-point verification (Dagh, succeeded):**

1. Import preview `parts: 23`
2. Public `/amiga/tournaments.php` — historical only (no internal smokes)
3. Detail page works
4. Ops `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee` works

**Orchestrator-direct UI work (commit `7acf2a4`, not a numbered worker job):**

- Amiga hub tab **Live tournaments** → `/amiga/live-tournaments.php`
- Fixture manager wrapped in dark **k2-site** chrome (not standalone white page)
- Dark form controls; league-panel **flatpickr** date picker (`k2_day_picker.php`, `k2-day-picker.js`)
- Docs updated in `amiga-data-contract.md`, `scripts/amiga/README.md`

### What is intentionally NOT in the browser UI yet

Dagh asked about this — **expected**. CLI exists; browser ops does not:

| Capability | Where |
|------------|--------|
| Player create / KOA name suggest | `python -m scripts.amiga players …` |
| Register entrant / onboard newcomer | `fixtures add-entrant`, `onboard-newcomer` |
| Stage placement | `add-stage-player` / `place-entrant` |
| Withdraw / replace entrant | CLI only |
| Kitchen create with **player IDs** only (no name search) | Fixture manager |
| Assign fixture slots by **numeric ID** | Fixture manager |

Checkpoint sequence lists **browser entrant onboarding** as future worker job **E** (after read-only live public view **D**).

### Key URLs (local pattern)

| Page | URL |
|------|-----|
| Live tournaments hub | `/amiga/live-tournaments.php` |
| Fixture manager | `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee` |
| Public historical index | `/amiga/tournaments.php` |
| Staging handoff | [`docs/amiga-staging-handoff.md`](../amiga-staging-handoff.md) |

Ops URL **404s** without `once=amiga-fixtures-one-shot` — intentional.

### Worker prompt inventory

- Prompts **001–022** under `docs/archive/orchestration/prompt-*.md`
- Handoffs under `docs/archive/orchestration/agent-handoffs/`
- **012** = staging sync rehearsal (record-only handoff filed from Dagh's 4-point staging success)
- **013** = read-only live public view (implemented and evaluated acceptable)
- **014** = browser entrant management (implemented and evaluated acceptable)
- **015** = browser stage placement (implemented and evaluated acceptable)
- **016** = browser fixture slot assignment UX (implemented; guardrail follow-up completed by 017)
- **017** = fixture-stage assignment guardrail (implemented and evaluated acceptable)
- **018** = browser organizer workflow checkpoint (implemented and evaluated acceptable)
- **019** = browser organizer shell (implemented and evaluated acceptable)
- **020** = browser friendly lifecycle (implemented and evaluated acceptable)
- **021** = browser fixtures and table preview (implemented and evaluated acceptable)
- **022** = browser Results tab (drafted; next worker prompt)

### Strategic priority order (do not skip without Dagh)

Per checkpoint + orchestration model:

1. ~~Public visibility boundary~~ **done**
2. ~~Staging refresh rehearsal~~ **verified by Dagh** and recorded in handoff 012
3. ~~Read-only live public view~~ **done** — allowlisted running fixture events; no public result entry
4. ~~Browser entrant management~~ **done** — entrant list, add by name search/id, withdraw/replace in ops UI
5. ~~Browser stage placement~~ **done** — place registered entrants into stages from ops UI
6. ~~Fixture-stage assignment guardrail~~ **done** — assignment now requires players from the fixture's exact stage
7. ~~Browser organizer workflow checkpoint~~ **done** — normal create/select players/preview/start/enter-results flow defined
8. ~~Browser organizer shell~~ **done** — tabs, create redirect, and player picker for league creation
9. ~~Friendly organizer lifecycle~~ **done** — Start/Complete actions and friendly status labels
10. ~~Fixtures and table preview~~ **done** — readable schedule and empty league table from entrants
11. **Organizer Results tab** — drafted as prompt 022; move score entry to Results and keep Fixtures schedule-first
12. **Staging/code refresh + ops smoke** — Dagh WinSCP sync/import and browser spot-checks after organizer UI shell
13. **Format expansion** (Swiss, group+KO promotion, honours) — design checkpoint after demo path stable
14. **Public builder / registration** — deferred

**Pause rule:** Do not delegate another foundation/guardrail worker job unless regressions appear.

### Review cycle (default user phrase)

> agent is done, please evaluate, then go on to next job

On that cue: read handoff → inspect diffs if needed → summarize accept/reject → write next worker prompt.

Dagh may also ask for **direct orchestrator implementation** (as with Live tournaments hub) — that is allowed when explicit.

### Verification baseline

```powershell
python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
python -m scripts.amiga verify-tournament-formats
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

PHP lint (Laragon): `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l <file>`

### Git / commit norms

- Commit only when Dagh asks (workers commit+push per prompt unless told otherwise)
- Never force-push `main`
- Distinguish git-steward commits from worker implementation commits

### Prior chat context

Full transcript (UI hub work, staging verify, entrant-UI question):  
`C:\Users\daghn\.cursor\projects\c-Users-daghn-Desktop-Online-and-Amiga-500-ELO/agent-transcripts/f2d9d43f-9ffc-45b7-b4ec-0326338b28ba/f2d9d43f-9ffc-45b7-b4ec-0326338b28ba.jsonl`

Plan file (do **not** edit): `c:\Users\daghn\.cursor\plans\tournament_checkpoint_2db0073b.plan.md`

### Your first actions as new orchestrator

1. Confirm `git log -3` includes the prompt 022 commit on `main`.
2. Offer to send **prompt 022 (browser Results tab)** to the next Composer 2.5 worker.
3. Do not re-open foundation jobs 001–021 or late-entrant edge cases without a reported regression or explicit request.

Acknowledge the mandate, summarize current state in 5–8 bullets, and ask Dagh which next step they want.

---

## End of copy-paste prompt
