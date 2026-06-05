# Agent entry point — KOOL ratings site

**For Cursor agents and new chats.** Repo overview for humans: [`README.md`](README.md). Dagh’s habit: end many sessions with **“update docs”** — that means [Part A + maybe Part B in `docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md), not “database only.”

---

## New chat — read this first

Cold start (do **before** coding unless Dagh pasted full context):

| Order | File | Why |
|-------|------|-----|
| 1 | [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) | Current focus, deploy path, recent work |
| 2 | [`AGENTS.md`](AGENTS.md) | Agent rituals, authority, and finish rules |
| 3 | [`docs/PROJECT_MAP.md`](docs/PROJECT_MAP.md) | What repo is; where code and docs live |
| 4 | Dagh’s message | Today’s goal wins over stale docs |
| 5 | Feature spec **if** obvious | Work DB / simul → **`docs/work-db-prepare.md`** + **`docs/coordination/ops-simul-runbook.md`**. Post-game PHP → **`docs/post-game-php-development.md`** + `ops/run_process_game.php`. Cutover / prod → **`docs/coordination/cutover-readiness.md`** (not batch REP scripts). Else e.g. `docs/STATUS_PAGE_DATA.md`, `docs/activity-charts.md` |
| 6 | [`docs/design-direction.md`](docs/design-direction.md) | If UI/theme work |

**Do not** read all of `docs/coordination/` up front. Open [`docs/prod-coordination.md`](docs/prod-coordination.md) only when the task touches **stored ladder truth** or Steve/migration.

**Run commands:** [`docs/OPERATIONS_QUICK_START.md`](docs/OPERATIONS_QUICK_START.md)

---

## Two rituals

| Ritual | When | Doc |
|--------|------|-----|
| **Bootstrap** | Start of chat / new task | This file + PROJECT_MAP + MEMORY |
| **Update docs** | Dagh says it, or end of substantial slice | [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) — **Part A always**, **Part B if migration triggers** |

---

## Authority (conflicts)

1. Dagh’s latest message  
2. [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) — product taste  
3. [`docs/design-direction.md`](docs/design-direction.md) — visuals  
4. [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) — logistics (offer fixes if wrong)  
5. [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) — how to record work  

---

## Migration awareness (background)

Prod ladder data is written by **Steve** (ground insert per game + periodic jobs). **Reference implementation:** PHP **`ops/dispatch.php`** (`ProcessCompletedGame`, `FinalizeUtcDay`) — see [`ladder-ops-platform.md`](docs/ladder-ops-platform.md) §2. Behaviour rules: [`website-data-contract.md`](docs/website-data-contract.md).

**Prod today:** live games still run **legacy C++** derived post-game until Steve cutover — **do not extend C++** or treat “C++ pending” as blocking repo work. Parity target is PHP ops (simul signed off Jun 2026).

**Performance / stored truth habit:** For DB-backed website work, default to stored/precomputed truth on hot paths (~73× faster than wide `ratedresults` scans at ~75k rows — see May 2026 evidence in prior MEMORY).

**Default question:** *What stored table should this value live in, and what does [`website-data-contract.md`](docs/website-data-contract.md) say for rebuild + post-game?* Work/staging proof: **ops simul** after `migrate-work` — not batch `REP-xxx` or `*_rebuild.sql` on prod. Do **not** treat missing C++ snippets as incomplete features.

**One-line cutover rule:** Prep is done on `kooldb1` via ops simul; live prod is Steve’s scheduled cutover; batch SQL and `rebuild_website_derived_data_local.ps1` are legacy repair on `ko2unity_db` only.

**Steve cutover:** schema + backfill on server, sync `ops/`, wire `dispatch.php` — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md). Hall of Fame records: [`records-post-game-exception.md`](docs/coordination/records-post-game-exception.md) (parity notes at cutover, not new C++ dev).

**Triggers to think about migration:** new DB columns/tables/indexes, `scripts/ladder/` edits, “store this on profile” vs compute in PHP, medals persistent on `playertable`, etc.

**Do not resurrect:** obsolete product ideas (e.g. legacy rating fade / PER-001) — if an old id appears, see [`docs/archive/retired-product-decisions.md`](docs/archive/retired-product-decisions.md) only.

**Then:** Part B of UPDATE_DOCS + [`feature-log.md`](docs/coordination/feature-log.md).

---

## Optional opener for Dagh

**Not required** — rules autoload bootstrap. If you want extra steer:

```
Today: [one line feature goal]
```

Example: `Status league — previous month medals column alignment.`

---

## Autoload (you do **not** activate this)

**`.cursor/rules/kool-workspace.mdc`** has `alwaysApply: true`.

That means Cursor **automatically** attaches it to **every Agent chat** in this workspace. You do **not** need to say “read AGENTS.md”, “follow rules”, or “update docs” for the habits to apply — though **“update docs”** still works as an extra nudge.

| Habit | Automatic? | Your part |
|-------|------------|-----------|
| Read MEMORY + map at chat start | **Rule says yes** — agent should Read those files first | Just say the feature you want |
| Record session in docs when done | **Same turn as shipping code** — Part A of UPDATE_DOCS (see below) | Optional “update docs”; not required if agent already finished in that turn |
| Migration registers | **Only if** the slice changed stored DB truth | Nothing extra |

**Limits (honest):** Rules are instructions, not a hard program. Agents **cannot** run after you close the chat — so finish must happen **when they implement**, not when you archive.

**If you close right after they shipped code** and they already ran Part A in that reply, you are fine. **If you close mid-WIP**, nothing will run — expected.

**Safety habit before archive (5 sec):** If their last message does not mention MEMORY/docs updated, say **update docs** once, wait for one reply, then archive. Optional, not required when they already reported doc updates.

**Limits:** If an agent skips reads or finish, say “read MEMORY first” or “update docs” once. Tuning: **`kool-workspace.mdc`**.

**Check in Cursor:** Settings → Rules (or Project Rules) — you should see **kool-workspace** listed for this repo. No toggle required if the file is committed under `.cursor/rules/`.

---

## For Dagh: is the repo ready?

| Goal | Ready? |
|------|--------|
| Start chats with context | **Yes** — [`README.md`](README.md) → AGENTS + PROJECT_MAP + MEMORY |
| End sessions with “update docs” | **Yes** — UPDATE_DOCS Part A (always) + B (conditional) |
| Migration future-proofing without daily weight | **Yes** — registers + feature-log when L1+ |
| One-click prod cutover | **No** — still Steve + registers when you choose |

Hub: [`docs/prod-coordination.md`](docs/prod-coordination.md)
