# Cursor `move_agent_to_root` — hangs from home / empty-window chats

**Status:** Investigated May 2026 (ratings repo). Workaround confirmed; root cause likely Cursor-side (MCP + workspace migration), not project code.

---

## Symptom

Agent calls MCP **`cursor-app-control` → `move_agent_to_root`** and the tool does not return for many minutes (800s+ observed). User stops the agent turn. Work continues if the agent uses **absolute paths** and skips the move.

**Observed chats:** Started from **`empty-window`** workspace (`C:\Users\daghn\.cursor\projects\empty-window`), then agent tries to move to e.g. `C:\Users\daghn\Desktop\Online and Amiga 500 ELO`.

---

## What the tool actually does (not “change cwd”)

Per MCP schema and `INSTRUCTIONS.md`, `move_agent_to_root`:

1. **Switches the Cursor agent workspace** to the target folder (UI / indexing / conversation root).
2. Runs a **migration path** on the destination that includes **`git fetch origin <branch>`** and fast-forward merge (see tool description: use `move_agent_to_cloned_root` to skip this when target is a `cursorfs-clone` sibling).

So it is **not** a trivial shell `cd`. It can block on:

- Workspace reload / file index
- **Network git fetch** (credentials, VPN, GitHub rate limits)
- **Modal prompts** in the IDE (trust folder, git auth) with no agent-visible timeout
- Large repos or paths with **spaces** (`Online and Amiga 500 ELO`) if migration shells quote badly

`create_project` uses argument name **`path`**; `move_agent_to_root` uses **`rootPath`** only. Agents often send both (see transcript `2830f402`); that may confuse validation or the MCP handler.

---

## Evidence from this machine

| Check | Result |
|-------|--------|
| Hung call args | `{ "path": "…", "rootPath": "…" }` — **invalid** extra `path` |
| Successful pattern (other chat) | `{ "rootPath": "c:\\Users\\daghn\\Desktop\\Online and Amiga 500 ELO" }` only — work continued (may still be slow) |
| Chat without move | `0c58b9a1` — edited repo via absolute paths, **never** called move |
| Target repo git | `origin` → `github.com/DaghN/ratings.kickoff2.git`, branch `main` — fetch should work |
| `git fetch origin main --dry-run` | Completes in a few seconds locally |

So a **multi-minute** hang is unlikely to be “git fetch is slow” alone; more likely **IDE workspace switch waiting** or **MCP never returning** after migration.

---

## Recommended workflow (Dagh)

### A) Prefer: start in the project folder

**File → Open Folder →** `Online and Amiga 500 ELO` (or open from Recent).

- Agent workspace is already correct → **do not call** `move_agent_to_root` at start of task.
- Matches how chats that “just worked” behaved.

### B) If chat started from home / empty window

1. Agent should use **`rootPath` only** (never `path`).
2. Call **move alone** — do not batch with Grep/Write/TodoWrite in the same tool batch (parallel tools may leave move pending until it completes).
3. **Cancel after ~60s** if no UI change (folder opened in sidebar, title bar shows project name).
4. Then either:
   - **Manually** Open Folder to the project and tell the agent “workspace is open”, or
   - Continue with **absolute paths** (works for edit/run; weaker workspace indexing).

### C) When creating a new repo

`create_project` → then `move_agent_to_root` with **`rootPath`** = same directory.

### D) Clones / worktrees

If target is under `~/.cursor/cursorfs-clone/`, use **`move_agent_to_cloned_root`** (skips fetch/merge migration).

---

## Recommended agent rule tweak (home workspace)

Current home rule says to **always** `move_agent_to_root` before project work. Safer wording:

- If workspace is **empty-window** or user home and target project is known → try `move_agent_to_root` **once**, `rootPath` only, **not batched**, **60s cap**; on hang, proceed with absolute paths and ask user to Open Folder.
- If user already opened the project folder → **skip** move.

(Optionally add this to user rules via Cursor Settings → Rules.)

---

## What to report to Cursor support

Include:

- MCP: `cursor-app-control` / `move_agent_to_root`
- Started from workspace id **`empty-window`**
- Target path (with spaces)
- Whether IDE showed folder trust / git prompt
- Tool args (`rootPath` vs erroneous `path`)
- Approximate hang duration

**Logs:** Help → Toggle Developer Tools → Console; also check MCP output if exposed.

---

## Related

- Milestones work continued without move: day-close + garden links (May 2026).
- Dedicated project MCP descriptors exist under `.cursor/projects/c-Users-daghn-Desktop-Online-and-Amiga-500-ELO/` when that folder is the workspace.

*Last updated: May 2026.*
