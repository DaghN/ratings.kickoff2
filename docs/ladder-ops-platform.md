# Ladder operations platform — design (Jun 2026)

**Status:** **Doc + `ops/` folder scaffold** (Jun 2026). **`dispatch.php` and modules not in repo yet** — design agreed with Steve; implementation is a separate slice.  
**Audience:** Dagh, Steve, Cursor agents.

**Related:** [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) (DB names) · [`website-data-contract.md`](website-data-contract.md) (derived rules at cutover) · [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) (fact vs derived columns)

---

## 1. Vocabulary

| Term | Meaning |
|------|---------|
| **Ground truth** | What happened in the match: who played, score, time; later richer in-match events. Persisted first (Steve insert into `ratedresults`). |
| **Derived truth** | Everything computed from facts + prior DB state: Elo, `WinnerID`, milestones, aggregates, league honours, `playertable` career fields, etc. |
| **Post-game** | Derived updates for **one** rated game (`game_id`). |
| **Periodic** | Derived updates driven by **time/calendar** (rating fade, league finalize, …) — not one new game. |
| **Ops** | Server-side runnable tooling under `site/public_html/ops/` — not the public website. |

**Elo is derived truth**, same class as milestones — not a separate “Steve core” category.

---

## 2. Boundary with Steve (agreed Jun 2026)

| Side | Responsibility |
|------|----------------|
| **Steve** | Persist **ground truth** after a rated game (insert into `ratedresults`; later possibly more event columns). Then **invoke** our PHP entry point. |
| **Dagh (repo)** | **Derived truth**: dispatcher, modules, SQL mirrors, sim/replay orchestration, website contract. |

**Agreed call shape (Steve proposed, aligned with ground/derived):**

```text
php …/ops/dispatch.php   CMD=ProcessCompletedGame   game_id=57216
```

- **No** duplicate player ids / scores in the call — module reads facts from DB.
- Other commands later: periodic jobs, batch sim, schema apply (mostly Dagh/CLI; same file family).

**Steve server check (Jun 2026):** PHP/SQL workloads Dagh sent ran fine — one CPU core maxed on heavy jobs; storage, memory, network healthy. PHP is an acceptable host for derived processing on the server.

**Still confirm when convenient:** exact invocation (CLI path vs internal HTTP), which PHP config the **staging website** vhost uses vs `kooldb1`/`kooldb2`.

---

## 3. Architecture — dispatcher + modules

### 3.1 Thin dispatcher (interface + light orchestration)

**File (planned):** `site/public_html/ops/dispatch.php`

| Job | Yes | No |
|-----|-----|-----|
| Parse `CMD` and parameters | ✓ | |
| Guards (allowed DB, required `game_id`, never touch baseline DB, …) | ✓ | |
| Route to one module / runner | ✓ | |
| Elo, milestones, SQL migrations inline | | ✓ |

### 3.2 Modules (outside dispatcher)

Business logic lives in `ops/modules/` (and shared includes as needed):

| Module role | Example `CMD` | Typical caller |
|-------------|-----------------|----------------|
| Per-game derived | `ProcessCompletedGame` | **Steve** (each live game on prod path) |
| Periodic | `RatingFade`, `FinalizeLeaguePeriod`, … | Steve scheduler / exe |
| Schema on work DB | `ApplySchema` | Dagh / Steve staging |
| Reset work from baseline | `ResetWorkFromBaseline` | Dagh |
| Chronological sim | `ReplayChronological` | Dagh (prep / cutover) |
| Parity (optional) | `ParityCheck` | Dagh |

### 3.3 One core function, two outer shells

```text
processCompletedGame(int $gameId)   // shared — all derived steps for one game

Outer A — live (Steve):
  one PHP process per game → processCompletedGame($id) once → exit

Outer B — sim (Dagh):
  one PHP process → foreach game in ORDER BY Date, id → processCompletedGame($id)
```

**Equivalence:** Game *N* after sim should match prod after game *N* if starting derived state matches and periodic side effects are modelled.

**Not equivalent operationally:** 75k separate `php` OS spawns vs one long loop — sim uses **one process, many function calls**.

**Python:** Optional for **parity comparison** only until PHP sim is trusted — not a second authority for prod.

### 3.4 Physical DB split (deferred)

Logical split (fact vs derived columns) is enough for v1. Optional later: `ratedresults_derived` table or facts-only insert — not blocking dispatcher work.

---

## 4. Databases

See **[`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md)** for full tables.

| Environment | Work / experiments | Pristine / reset copy | Browser / daily dev |
|-------------|-------------------|---------------------|---------------------|
| **Local** | `ko2unity_work` | `ko2unity_baseline` | `ko2unity_db` |
| **Staging** | `kooldb1` (config1) | `kooldb2` (config2) | N/A — use local dev |
| **Production** | live DB | — | — |

**Rules:**

- Never migrate or replay on **`ko2unity_baseline`** / **`kooldb2`** (reset sources).
- **`ko2unity_work`** / **`kooldb1`**: expand schema, sim, post-game tests.
- Prod dump import: sanitized dump only — [`data/README.md`](../data/README.md).

---

## 5. Deploy — one WinSCP sync

**Habit:** Everything Steve needs on staging lives under **`site/public_html/`** → sync to `ratings.kickoff2.com` `public_html/`.

| Path | Contents |
|------|----------|
| `site/public_html/` (root) | Website — pages, `api/`, assets |
| `site/public_html/ops/` | **Operations** — dispatcher, modules, SQL mirrors |
| `site/public_html/staging-scripts/` | **Legacy** — migrate into `ops/` over time |

**Schema:**

- **Canonical:** `schema/migrations/` (repo root) — registers, `schema/apply_local.ps1` on dev/work.
- **Staging mirror:** `ops/sql/migrations/` — copy each new migration before sync.

**Not synced:** `scripts/*.ps1` (Windows), `data/dumps/`, gitignored config.

---

## 6. Target `ops/` tree

```text
site/public_html/ops/
  .htaccess              # deny web
  README.md
  dispatch.php           # planned — not in repo yet
  modules/               # post_game.php, periodic_*.php, …
  sql/
    migrations/          # mirror schema/migrations/
    rebuild/             # optional REP SQL mirrors
```

**Local sim (today):** Python replay on work DB — `python -m scripts.ladder run --target sandbox` ([`scripts/ladder/README.md`](../scripts/ladder/README.md)). **After `dispatch.php` ships:** same `CMD=` habit from `public_html/` against `ko2unity_work` / `ladder-work.ini`.

---

## 7. Lifecycle pipelines

### 7.1 Staging / cutover prep (Dagh)

```text
1. ExpandSchema          → ops/sql/migrations on work DB (kooldb1 / ko2unity_work)
2. ResetWorkFromBaseline → clone baseline → work; derived wiped
3. ReplayChronological   → processCompletedGame × N
4. (optional) Periodic commands at boundaries or batch after replay
5. Verify                → checklist queries / CMD=Verify
```

### 7.2 Production (steady state)

```text
Steve: INSERT ratedresults (ground) → game_id
Steve: php ops/dispatch.php CMD=ProcessCompletedGame game_id=…
Periodic: php ops/dispatch.php CMD=… (scheduler)
```

---

## 8. Implementation order (suggested)

1. `dispatch.php` + guards + `ProcessCompletedGame` stub reading `game_id`.
2. Implement derived phases per [`website-data-contract.md`](website-data-contract.md) post-game § (incremental).
3. `ReplayChronological` calling same core on work DB.
4. Migrate high-traffic paths from `staging-scripts/` → `ops/modules/`.
5. Cutover: Steve wires prod call; retire duplicate Python authority when parity boring.

---

## 9. Open questions (non-blocking)

| Item | Notes |
|------|--------|
| Staging **website** DB config | `ko2unitydb_config.php` vs config1/2 — confirm with Steve |
| Legacy **`kooldb`** | May still exist from May 2026; new work uses **kooldb1** / **kooldb2** |
| Periodic vs replay ordering | Interleave day-boundary CMDs in sim, or batch after full replay |

---

## 10. Explicit non-goals (this platform doc)

- Replacing [`website-data-contract.md`](website-data-contract.md) row-level rules before implementation.
- Moving `staging-scripts/` files in the same slice as first `dispatch.php` commit.
- Committing raw prod SQL dumps to git.
