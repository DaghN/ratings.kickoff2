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
- Commit and push orchestration/process documentation and worker prompts that it creates directly in chat with the user, unless the user asks not to.

The orchestrator should not normally implement the slice itself once this process is active, unless the user explicitly asks for direct implementation or a small correction.

### Composer 2.5 worker agents

Worker agents execute one bounded job at a time. They should implement the requested slice, document what they did, run focused checks, commit, and push.

Workers can suggest next steps, but they do not own strategic sequencing. They must avoid expanding scope unless the prompt explicitly allows it.

### Git steward agents

The user may occasionally run a small git steward agent outside the main worker sequence to commit known leftover local changes, generated manifests, memory updates, or other housekeeping. This is allowed when explicitly initiated by the user.

Git steward commits are not part of the strategic worker sequence. During review, the orchestrator should distinguish them from worker implementation commits and should not treat them as scope drift unless they touch product or schema behavior unexpectedly.

Worker agents should still assume they are **not** git stewards. They must not commit unrelated pre-existing local changes unless their prompt explicitly says to do so.

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

## Prompt contract

Every worker prompt should include:

- The exact repo/workspace context.
- The strategic reason for the job.
- The desired scope and explicit non-goals.
- Files or areas the worker should inspect first.
- Required output handoff path.
- Required verification commands.
- Requirement to commit and push when done.
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

The immediate strategic priority is to harden the tournament foundation before adding broad public UI:

1. Make entrant verification green for current generated/local tournament data.
2. Tournament lifecycle.
3. KOA-aware player naming and newcomer creation workflow — **internal CLI foundation shipped** (`python -m scripts.amiga players …`); public newcomer UX remains deferred.
4. Separation of internal ops from public tournament viewing.
5. Format capability model for future Swiss, group+knockout, marathon, World Cup class, and later honours.

The first worker job in this orchestration model implemented tournament entrants/roster ground truth. Subsequent jobs closed entrant backfill, status workflow, fixture guardrails, lifecycle, and browser ops controls. KOA player naming is now available to operators via CLI only; wire it into entrant onboarding flows in a later slice.
