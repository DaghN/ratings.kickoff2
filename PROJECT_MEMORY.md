# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief.

---

## Current focus

- Await **Steve’s message** on server/codebase access, deploy, and DB reality; fold that into next steps.
- Keep changes **small and reversible** (see brief): polish, correctness, and phased features — no speculative re-platform.

---

## Next (intended, not committed)

- Incorporate Steve’s input: local dev story, what `main` means vs production, secrets/config, migration/deploy flow.
- When codebase is available here: skim structure, align with brief, propose first **vertical slice** (one improvement with clear done criteria).

---

## Recent log

| When (approx.) | What |
|----------------|------|
| 2026-05 | Git repo initialized on **`main`**; remote **origin** → [ratings.kickoff2](https://github.com/DaghN/ratings.kickoff2). Initial commit: `PROJECT_BRIEF.md`, `.gitignore` (ignores `.env` etc.). Branch protection / CI: **not set yet**. |
| 2026-05 | Workflow: **simple solo Git** — `main` + optional short-lived branches; not full Git flow. |

*(Append new rows for meaningful milestones — avoid noise.)*

---

## Deferred / blocked

- GitHub branch protection, PR policies — intentionally deferred until collaboration needs them.
- Foundational infra decisions that depend on **Steve** — see Focus / Next above.

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub repo | https://github.com/DaghN/ratings.kickoff2 |
| Default branch | `main` |
| Production host | Steve (details TBD) |
| Codebase in this workspace | Not yet synced from server; brief + memory + ignore only until import |

---

## Agent hygiene

- After completing a slice: **one line** under Recent log; adjust **Current focus** / **Next**.
- Prefer linking to GitHub issues/PRs if the project adopts them later; until then this file + chat are the trail.
- Do **not** paste secrets or production credentials here.
