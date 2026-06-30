# Agent track playbook — doc · plan · prompt · slices

**For Dagh and Cursor agents.** Repeatable workflow for **multi-session, multi-slice** work across the repo — **online** (`kooldb*`, `ops/`, `scripts/ladder/`) and **Amiga** (`ko2amiga_db`, `scripts/amiga/`) alike.

**Shorthand:** *explore → doc → plan → prompt → slices*

**Handoff storage (Jun 2026):** Active review starters live in [`agent-handoffs/`](agent-handoffs/) (2 files today). Completed slice handoffs + retired starters + browser/entrant prompts live in [`../archive/orchestration/`](../archive/orchestration/README.md).

---

## Quick start (Dagh)

When a feature needs locked decisions and more than one chat to ship:

1. **Explore** in a normal chat — analysis, options, no code (unless you explicitly want a spike).
2. Say **“let’s write the doc trio”** (or similar) — agent produces **policy doc + implementation plan + starter prompt**.
3. **New chat** — paste the **starter prompt** block; confirm the agent’s understanding reply.
4. Say **“Do slice 0”** (or **“Continue with the next slice”**) per session until the plan’s closure slice.

Cosmetic or single-file fixes: **skip this playbook** — implement directly, then [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) Part A if needed.

---

## When to use this workflow

| Use a **track** when… | **Skip** the track when… |
|------------------------|---------------------------|
| Work spans **several sessions** or touches many files | One bugfix, CSS, copy, nav tweak |
| **Product rules** must stay locked (schema, honours, post-game, contracts) | Read-time-only PHP with no new stored truth |
| **Stored ladder / derived truth** changes (online or Amiga) | Question-only or planning-only (no shipping yet) |
| You want **STOP gates** (SQL, browser, simul) before risky steps | Spike you plan to throw away |
| Steve / cutover / migration registers may apply later | |

**Realms:** Same workflow; only the **authority docs** and **verify commands** change.

| Realm | Typical policy / contract | Typical proof |
|-------|---------------------------|---------------|
| **Online** | [`website-data-contract.md`](../website-data-contract.md), feature spec (e.g. [`STATUS_PAGE_DATA.md`](../STATUS_PAGE_DATA.md)) | `ops/run_ops_sim.php`, `scripts/ladder/`, verify scripts in plan |
| **Amiga** | [`amiga-data-contract.md`](../amiga-data-contract.md), domain policy docs | `python -m scripts.amiga replay`, `verify-*` CLI |

---

## The five phases

```text
Phase 0 — Explore     (often the current chat — feedback, no slice work)
Phase 1 — Doc         (policy / decisions — the “what and why”)
Phase 2 — Plan        (implementation plan — slices, verification, STOP gates)
Phase 3 — Prompt      (starter prompt — cold-start contract for a new chat)
Phase 4 — Slices      (execute one slice per session; handoff files)
```

**Rule:** Exploration chats **decide**; execution chats **start from the starter prompt** after the trio exists. Do not begin slice 0 during an explore-only thread unless you explicitly switch modes.

**Creative brainstorm (pre-track):** Product / "what else?" sessions that are **not** yet a locked track → read [`creative-ideas-july-2026.md`](../creative-ideas-july-2026.md) (ledger + recipe). Update that doc + `PROJECT_MEMORY` when Dagh closes a creative session — not on every cold start.

---

## The three artifacts

### 1) Policy doc (decisions)

**Role:** Authority for **what we decided** — not a task list.

**Location:** `docs/<topic>-policy.md`, `docs/<topic>-rules.md`, or an existing contract section if the track is small.

**Include:**

- Status (`Planned` → `Implemented`)
- Purpose (one paragraph)
- **Locked decisions** table (numbered — e.g. S1, E1, P1)
- Data / behaviour model (diagram or table)
- **Rejected alternatives** (prevents re-debate)
- **Out of scope**
- **URL / page structure** — product and behaviour here; **canonical paths defer to** [`k2-page-structure-checklist.md`](../k2-page-structure-checklist.md) and [`url-routes.md`](../url-routes.md). Do **not** lock query-param mode tabs (`?view=`, `?wing=`, `?tab=`) in feature policy without cross-check.
- Links to parent contracts ([`website-data-contract.md`](../website-data-contract.md), [`amiga-data-contract.md`](../amiga-data-contract.md), [`design-direction.md`](../design-direction.md))

**Examples:**

| Track | Policy doc |
|-------|------------|
| Amiga event finish | [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) |
| Amiga standings scope | [`amiga-standings-scope-policy.md`](../amiga-standings-scope-policy.md) |
| Amiga tournament structure | [`amiga-tournament-structure-policy.md`](../amiga-tournament-structure-policy.md) |
| Online stored truth | Often [`website-data-contract.md`](../website-data-contract.md) + a focused addendum |

---

### 2) Implementation plan (slices)

**Role:** Agent **execution contract** — how to ship in safe increments.

**Location:** `docs/<topic>-implementation-plan.md` (sibling to policy doc).

**Include:**

- Status, link to policy, in/out of scope
- **How to use this plan** (one slice per session, handoffs, no commit unless asked, UPDATE_DOCS timing)
- **Slice map** table (slice #, deliverable, STOP gate)
- Per slice: goal, task checklist, **verification commands**, expected files
- **STOP gates** — what you check (SQL, browser URL, simul) before the next slice
- Environment reference (DB names, PHP/MySQL paths, replay commands)
- Handoff file naming convention

**Slice sizing:** Each slice should be verifiable in one session; end with a green check or a clear failure report.

**Examples:**

| Track | Plan |
|-------|------|
| Amiga event finish | [`amiga-event-finish-implementation-plan.md`](../amiga-event-finish-implementation-plan.md) |
| Amiga standings scope | [`amiga-standings-scope-implementation-plan.md`](../amiga-standings-scope-implementation-plan.md) |
| Amiga tournament structure | [`amiga-tournament-structure-implementation-plan.md`](../amiga-tournament-structure-implementation-plan.md) |
| Amiga player universe | [`amiga-player-universe-implementation-plan.md`](../amiga-player-universe-implementation-plan.md) |

---

### 3) Starter prompt (new chat bootstrap)

**Role:** Copy-paste block so a **fresh agent** needs no chat history.

**Location (active track):** `docs/orchestration/agent-handoffs/<topic>-STARTER-PROMPT.md`

When a track **closes**, move its starter + slice handoffs to `docs/archive/orchestration/agent-handoffs/` (see [`../archive/orchestration/README.md`](../archive/orchestration/README.md)).

**Include:**

- Links to policy + plan
- Fenced **Prompt (copy from here)** block containing:
  - **CRITICAL — first reply rule:** no tools on first turn; restate understanding; wait for user confirm before slice 0
  - **Read first** (ordered list)
  - **Operating mode** (one slice at a time, handoffs, STOP gates, UPDATE_DOCS, no git unless asked)
  - **Locked decisions** (compressed from policy)
  - **Out of scope**
  - **Environment** (DB, paths, verify/replay commands)
  - **Start command** (“Do slice 0” unless user specifies otherwise)
- Mark **COMPLETE** at top when the track finishes (context only — do not restart migration)

**Examples:** [`amiga-event-finish-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md), [`amiga-standings-scope-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md)

---

## Phase 4 — Executing slices

### Per slice (agent)

1. Execute **only** the requested slice (unless you asked for multiple).
2. Run all **Verification** steps in the plan; fix before stopping.
3. Write handoff: `docs/archive/orchestration/agent-handoffs/YYYY-MM-DD-NNN-<track>-slice-N.md`
4. At a **STOP gate:** list exact checks; **wait** for your OK.
5. Same turn as shipping: [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) **Part A**; **Part B** if stored truth / schema / `scripts/ladder/` / registers apply.
6. **Do not git commit** unless you asked.

### Handoff file (minimum)

1. Goal (one line)  
2. Checklist from plan (boxes marked)  
3. Files changed  
4. Verification output (pass/fail summary)  
5. STOP gate notes (if any)  
6. Known limitations / next slice  

### Your commands

| You say | Agent does |
|---------|------------|
| **Do slice N** | Slice N only |
| **Continue with the next slice** | Next uncompleted slice |
| **update docs** | UPDATE_DOCS Part A (+ B if triggered) — even if code was already done |
| OK at STOP gate | Proceed to next slice |

---

## How this relates to other rituals

| Ritual | Doc | Relation to tracks |
|--------|-----|-------------------|
| Bootstrap | [`AGENTS.md`](../../AGENTS.md) | Every chat; execution chats also read policy + plan from starter prompt |
| Update docs | [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) | End of **each slice** that ships code; closure slice updates policy status + feature-log |
| Migration / Steve | [`prod-coordination.md`](../prod-coordination.md), Part B of UPDATE_DOCS | Online tracks that change `kooldb*` schema or post-game |
| Amiga DDL | `scripts/amiga/sql/`, [`amiga-data-contract.md`](../amiga-data-contract.md) | Amiga tracks — not `ops/sql/migrations/` unless explicitly shared |

---

## Naming conventions (suggested)

| Artifact | Pattern |
|----------|---------|
| Policy | `docs/<area>-<topic>-policy.md` or `docs/<area>-<topic>-rules.md` |
| Plan | `docs/<area>-<topic>-implementation-plan.md` |
| Starter (active) | `docs/orchestration/agent-handoffs/<area>-<topic>-STARTER-PROMPT.md` |
| Starter / handoff (archived) | `docs/archive/orchestration/agent-handoffs/…` |
| Handoff (per slice) | `docs/archive/orchestration/agent-handoffs/YYYY-MM-DD-NNN-<area>-<topic>-slice-N.md` |

Use a consistent `<topic>` slug across all four (e.g. `amiga-event-finish`, `rating-events`, `status-leagues-phase-2`).

---

## Checklist — creating a new track

- [ ] Exploration done; decisions stable enough to lock
- [ ] Policy doc written (locked table + rejected alternatives + out of scope)
- [ ] Implementation plan written (slice map + verification + STOP gates)
- [ ] Starter prompt written (first-reply rule + copy block)
- [ ] `PROJECT_MEMORY.md` — one line under Recent log (planning or complete)
- [ ] Closure slice: policy status **Implemented**, starter prompt **COMPLETE**, feature-log if user-facing

---

## Anti-patterns

- **One giant chat** for the whole track — context loss; use slices + handoffs.
- **Policy in the plan only** — agents re-debate product rules mid-slice.
- **Skipping the starter prompt** — you re-explain rituals every session.
- **Slice 0 in the explore chat** — mix design and migration; use a new chat after the trio exists.
- **Track for every tweak** — overhead without benefit.
- **Forgetting STOP gates** on irreversible steps (drop column, enum shrink, prod-shaped simul).

---

## Reference tracks (completed or active)

| Track | Policy | Plan | Starter |
|-------|--------|------|---------|
| Amiga event finish | [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) | [`amiga-event-finish-implementation-plan.md`](../amiga-event-finish-implementation-plan.md) | [`amiga-event-finish-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md) ✓ |
| Amiga tournament medals v2 | [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) v2 | [`amiga-tournament-medals-unification-implementation-plan.md`](../amiga-tournament-medals-unification-implementation-plan.md) | [`amiga-tournament-medals-unification-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md) ✓ |
| Amiga standings scope | [`amiga-standings-scope-policy.md`](../amiga-standings-scope-policy.md) | [`amiga-standings-scope-implementation-plan.md`](../amiga-standings-scope-implementation-plan.md) | [`amiga-standings-scope-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md) |
| Amiga tournament structure | [`amiga-tournament-structure-policy.md`](../amiga-tournament-structure-policy.md) | [`amiga-tournament-structure-implementation-plan.md`](../amiga-tournament-structure-implementation-plan.md) | [`amiga-tournament-structure-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md) |
| Amiga player universe | [`amiga-player-universe-contract.md`](../amiga-player-universe-contract.md) | [`amiga-player-universe-implementation-plan.md`](../amiga-player-universe-implementation-plan.md) | [`amiga-player-universe-STARTER-PROMPT.md`](../archive/orchestration/agent-handoffs/amiga-player-universe-STARTER-PROMPT.md) |
| **Amiga disposition review (active)** | [`amiga-tournament-structure-handlers.md`](../amiga-tournament-structure-handlers.md) | [`amiga-tournament-structure-review-queue.md`](../amiga-tournament-structure-review-queue.md) | [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) |

Online tracks have used the same handoff folder (e.g. rating-events slices, format-backbone, ops-simul work) — policy may live in a feature spec or contract section rather than a separate `*-policy.md`; the **plan + prompt + slices** shape still applies.

---

*Agents: this playbook does not replace [`AGENTS.md`](../../AGENTS.md) or [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) — it structures **large** work only.*
