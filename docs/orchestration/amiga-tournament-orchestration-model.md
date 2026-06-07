# Amiga tournament orchestration model

**Status:** Working process, Jun 2026  
**Purpose:** Define how strategic planning and implementation work should be split between the supervising GPT-5.5 chat and serial Composer 2.5 worker agents for the Amiga tournament universe.

## Roles

### GPT-5.5 orchestrator

The orchestrator owns product coherence, architecture, sequencing, and review. It should keep the broad tournament system in view: historical fidelity, future tournament creation, player identity, stages, fixtures, result entry, standings, honours, public navigation, and internal operations.

The orchestrator should:

- Decide the next bounded job.
- Write a copy-paste prompt for a worker agent.
- Specify expected output files and verification requirements.
- Review the worker's handoff document and code changes when the user reports completion.
- Decide whether deeper inspection is needed.
- Produce the next worker prompt when satisfied.
- Commit and push orchestration/process documentation, handoff records, and worker prompts that it creates or edits directly in chat with the user, unless the user explicitly asks not to.

The orchestrator should not normally implement the slice itself once this process is active, unless the user explicitly asks for direct implementation or a small correction.

### Composer 2.5 worker agents

Worker agents execute one bounded job at a time. They should implement the requested slice, document what they did, run focused checks, commit, and push.

Workers can suggest next steps, but they do not own strategic sequencing. They must avoid expanding scope unless the prompt explicitly allows it.

### Git steward agents

The user may occasionally run a small git steward agent outside the main worker sequence to commit known leftover local changes, generated manifests, memory updates, or other housekeeping. This is allowed when explicitly initiated by the user.

Git steward commits are not part of the strategic worker sequence. During review, the orchestrator should distinguish them from worker implementation commits and should not treat them as scope drift unless they touch product or schema behavior unexpectedly.

Worker agents should still assume they are **not** git stewards. They must not commit unrelated pre-existing local changes unless their prompt explicitly says to do so.

## Orchestrator git requirements

When the orchestrator creates or edits orchestration documents, process documents, handoff records, or worker prompts directly in chat, it should commit and push those documentation changes before handing control back to Dagh.

This includes:

- new `docs/orchestration/prompt-*.md` worker prompts
- new or corrected `docs/orchestration/agent-handoffs/*.md` records
- updates to checkpoint, handover, or process docs

Do not leave orchestrator-authored prompt or process documentation uncommitted unless Dagh explicitly asks for a draft-only change. Do not include unrelated working-tree changes in these commits.

## Standard worker output

Every worker job should create a handoff document under:

`docs/orchestration/agent-handoffs/`

Use dated, numbered names:

`YYYY-MM-DD-NNN-short-slug.md`

Each handoff document should include:

- Goal
- Classification: `foundation`, `migration`, `internal ops`, `public UI`, `derived logic`, `docs/strategy`, or another explicit category
- Files changed
- Schema/data implications
- Behavior added or changed
- Tests/checks run, including exact commands and results
- Risks, limitations, and anything not verified
- Commit hash and push target
- Recommended next steps

The worker's final chat response must explicitly include the handoff document path and the pushed commit hash. If either is missing from the final response, the orchestrator should treat the job report as incomplete until the worker supplies or fixes the reference.

## Prompt contract

Every worker prompt should include:

- The exact repo/workspace context.
- The strategic reason for the job.
- The desired scope and explicit non-goals.
- Files or areas the worker should inspect first.
- Required output handoff path.
- Required verification commands.
- Requirement to commit and push when done.
- Requirement that the final response includes the handoff document path and pushed commit hash.
- Instruction not to use destructive git commands or revert unrelated user changes.
- Instruction to inspect `git status` before staging and list any unrelated files intentionally left unstaged.

## Non-negotiable engineering principles

- Preserve ground truth vs derived truth separation.
- Do not encode future clean formats only as ad hoc phase strings.
- Prefer explicit format/stage/fixture/entrant data for new tournaments.
- Keep legacy import behavior faithful and auditable.
- Keep derived tables rebuildable.
- Keep public UI separate from internal ops until the public product shape is intentionally designed.
- Treat player identity and KOA naming conventions as product-critical, not incidental strings.
- Preserve append-only live result chronology unless a migration/rebuild mode is explicitly requested.
- Never copy stale Access standing snapshots into truth tables.

## Review cycle

The default user phrase after a worker completes is:

> agent is done, please evaluate, then go on to next job

On that cue, the orchestrator should:

1. Read the worker handoff document.
2. Inspect relevant diffs and changed files if needed.
3. Run or request focused verification if the handoff leaves gaps.
4. Summarize whether the slice is acceptable.
5. Write the next copy-paste worker prompt.

The user may instead ask for overview, pause, or a different next direction. The newest user instruction wins.

## Verification standard

Worker jobs should aim to leave relevant verification green. If a global verification command cannot be green because of transitional local data, pre-existing smoke data, missing staging exports, or another known reason, the handoff must:

- State the exact failing command.
- Include representative failure output.
- Explain why it is not considered a regression.
- Recommend the next cleanup/backfill job needed to make the verification green.

The orchestrator should usually prioritize making important global checks green before expanding product surface area further.

## Current strategic priority

**Checkpoint 2026-06-07:** Foundation and internal-ops guardrails (jobs 001–011) are complete, the Dagh-assisted staging refresh is recorded in job 012, the read-only public live view landed in job 013, browser entrant management landed in job 014, and browser stage placement landed in job 015. Worker 016 improved browser fixture assignment UX, but needs a fixture-stage server guardrail follow-up before deeper model slices resume.

Immediate priority order:

1. ~~**Public/private visibility boundary**~~ — done; public historical pages show only `completed` / `archived` tournaments; internal ops unchanged. See [`amiga-tournament-architecture-checkpoint.md`](amiga-tournament-architecture-checkpoint.md).
2. ~~**Staging refresh rehearsal**~~ — verified by Dagh and recorded in [`agent-handoffs/2026-06-07-012-staging-sync-rehearsal.md`](agent-handoffs/2026-06-07-012-staging-sync-rehearsal.md).
3. ~~**Read-only live public view**~~ — fixture schedule + lifecycle for allowlisted running events (no public result entry). See [`agent-handoffs/2026-06-08-013-read-only-live-public-view.md`](agent-handoffs/2026-06-08-013-read-only-live-public-view.md).
4. ~~**Browser entrant management**~~ — entrant list, add existing players by search/id, withdraw, and replace in ops UI. See [`agent-handoffs/2026-06-08-014-browser-entrant-management.md`](agent-handoffs/2026-06-08-014-browser-entrant-management.md).
5. ~~**Browser stage placement**~~ — place registered entrants into stages from ops UI. See [`agent-handoffs/2026-06-08-015-browser-stage-placement.md`](agent-handoffs/2026-06-08-015-browser-stage-placement.md).
6. **Fixture-stage assignment guardrail** — require slot assignments to use players from the fixture's exact stage. See [`prompt-017-fixture-stage-assignment-guardrail.md`](prompt-017-fixture-stage-assignment-guardrail.md).
7. **Format capability model** — Swiss, group+knockout promotion, World Cup class, honours. Design checkpoint after demo path stable.

Completed foundation (do not re-delegate unless regressions appear):

- Tournament entrants, lifecycle, KOA naming CLI, entrant onboarding, stage placement, fixture/attach guardrails, browser lifecycle controls, staging manifest refresh (job 007).
