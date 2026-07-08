# Obsolete dev scripts — retirement policy

**Status:** **Complete** (Jun 2026) — slices 1–6 executed; holy ops unchanged.  
**Companion:** [`obsolete-dev-scripts-retirement-implementation-plan.md`](obsolete-dev-scripts-retirement-implementation-plan.md)  
**Audience:** Dagh, Cursor agents.

---

## 1. What we decided

We are **retiring dev-era batch and replay tooling** that predates the Jun 2026 **holy ops** model (baseline/work DBs + PHP ops simul + Amiga **simul** on living ground). These scripts were built when local `ko2unity_db` was the only sandbox and the future path was unknown. They remain in the repo mainly because agents flagged them as “maybe still useful” during cleanup — which adds **context complexity** and **misleading runbook presence** without matching how we work today.

**We are not deleting Python from the project.** Amiga **forward** holy ops = **`python -m scripts.amiga simul`** on **`ko2amiga_work`**; legacy **`prove`** on frozen **`ko2amiga_db`** is oracle-only. Online holy ops **is** PHP (`run_ops_sim.php`). The retirement target is **obsolete fill/repair surfaces**, not every file under `scripts/`.

**If we ever need a quick parity filler again**, we prefer **building from contract + holy ops semantics** over reviving May-era batch chains.

---

## 2. Authoritative truth paths (today)

| Realm | Database(s) | Holy fill path | Holy verify |
|-------|-------------|----------------|-------------|
| **Online** | `ko2unity_work` / `kooldb1` (sign-off); `ko2unity_db` frozen dev (UI/cosmetics) | `run_prepare.php` → `zero-derived` → **`run_ops_sim.php run`** → `FinalizeUtcDay` via timeline sim | **`run_verify_ops_sim.php`** |
| **Amiga** | **`ko2amiga_work`** (living) + frozen **`ko2amiga_db`** (oracle) | **`python -m scripts.amiga simul`** on work · legacy **`prove`** on oracle only | verify modules in simul / prove |

**Wrong derived state on work / Amiga:** re-run holy path (`zero-derived` → simul, or `prove`). **Not** batch SQL, **not** `python -m scripts.ladder run`.

Related locked policies: [`work-db-prepare.md`](work-db-prepare.md) §1.5 · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) · [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md).

---

## 3. Holy ops investigation (Jun 2026)

**Question:** Do holy ops call Python — especially `scripts.ladder` — in a way that would break if we retire batch/replay CLIs?

**Answer:** **Online holy ops: no Python execution. Amiga holy ops: yes — but `scripts.amiga`, not `scripts.ladder run`.**

### 3.1 Online (`site/public_html/ops/`)

| Entry point | Executes Python? | Notes |
|-------------|------------------|-------|
| `run_ops_sim.php` | **No** | PHP `ProcessCompletedGame` P0–P7 + timeline / `FinalizeUtcDay` only |
| `run_verify_ops_sim.php` | **No** | PHP verify modules only |
| `run_prepare.php` (`prepare`, `zero-derived`, `migrate-work`, …) | **No** | `k2_ops_zero_derived` → `ops_reset_universe.php` (PHP). Comment says “mirrors `engine.py`” — **reference only** |
| `run_process_game.php` / `dispatch.php` | **No** | PHP post-game |
| `includes/ops_shell.php` `proc_open` | **No** | **`mysql` / `mysqldump` only** for `refresh-work` — not Python |

**Conclusion:** Retiring `python -m scripts.ladder run` and batch rebuild **does not break online holy ops**, provided we do not remove PHP ops or the shared **library** symbols Amiga still imports (see §3.3).

### 3.2 Amiga (`scripts/amiga/` + `site/public_html/amiga/ops/`)

| Entry point | Executes Python? | Calls `scripts.ladder`? |
|-------------|------------------|-------------------------|
| **`python -m scripts.amiga simul`** | **Yes** — forward holy loop on work | **Imports only** — see §3.3 |
| **`python -m scripts.amiga prove`** | **Yes** — oracle loop (frozen `ko2amiga_db`) | **Imports only** — see §3.3 |
| `python -m scripts.amiga replay` | Yes | Same imports |
| `finalize_tournament.py` | Yes (writer) | `apply_game_row`, `PlayerState`, `START_RATING`, `config` |
| Amiga PHP ops (`finalize-tournament`, `process_completed_game`) | **No shell to Python** | Comments reference Python parity; runtime is PHP |
| `verify_php_community_parity` (in `prove`) | Subprocess → **PHP** oneoff probe | Not `scripts.ladder` |

**Conclusion:** Amiga holy ops **must keep** specific `scripts.ladder` **library modules** until extracted. It **does not** invoke `python -m scripts.ladder run`.

### 3.3 `scripts.ladder` imports still required by Amiga holy path

| Module / symbol | Used by (Amiga) |
|-----------------|-----------------|
| `player_state.PlayerState` | `replay.py`, `finalize_tournament.py`, `snapshot_*`, `realm_persist.py`, `matchup_cumulative.py`, `elo_rank.py`, `player_stats_load.py`, tests |
| `engine.apply_game_row` | `finalize_tournament.py` |
| `constants.START_RATING` (+ sentinels) | `finalize_tournament.py`, `player_stats_load.py` |
| `config.DbConfig`, `_parse_php_config` | `scripts/amiga/config.py` |

**Not imported by Amiga:** `milestones.py`, `period_activity.py`, `period_aggregates.py`, `replay_all`, `run_full`, batch SQL readers, `golden_record_checks` CLI.

### 3.4 Legacy Python on the **non-holy** margin

These still touch `scripts.ladder` but are **not** online or Amiga holy sign-off:

| Surface | Role | Retirement target |
|---------|------|-------------------|
| `python -m scripts.ladder run` | Full dev/sandbox replay + batch tails | **Retire CLI** |
| `scripts/run_local_replay.ps1` | Wrapper → ladder `run` on `ko2unity_db` | **Retire** |
| `run_staging_ladder_replay.sh` | Deprecated staging one-shot | **Retire** |
| `scripts/rebuild_website_derived_data_local.ps1` | Batch SQL chain on frozen dev | **Retire** |
| `scripts/ladder/sql/archive/batch-2026-05/` | Batch repair SQL | **Archive** |
| `python -m scripts.work_prepare` | Legacy prepare / **ab-post-game** oracle | **Trim** — PHP `run_prepare.php` is preferred; `ab-post-game` spawns `scripts.ladder run` |
| `scripts/refresh_local_work_db.ps1` | Still calls Python `work_prepare refresh-work` | **Rewire to PHP** `run_prepare.php refresh-work` (PHP path already exists) |

### 3.5 Agent trap (real incident class)

A prior audit suggested deleting “unused” scripts wholesale. **`composer-2.5` correctly blocked** removal that would break Amiga `prove` via `scripts.ladder` library imports. **This policy exists to prevent that class of mistake.**

**Rule:** Never retire a path under `scripts/` without completing **§5 per-file gate** for **both** online holy ops **and** Amiga holy ops.

---

## 4. Retirement scope

### 4.1 Tier A — Retire (obsolete fill/repair surfaces)

| Asset | Why obsolete |
|-------|----------------|
| `scripts/rebuild_website_derived_data_local.ps1` | Batch website fill; wrong semantics vs simul; dev-only |
| `scripts/ladder/sql/archive/batch-2026-05/` | Same era; repair-only |
| `scripts/run_local_replay.ps1` | Dev replay button; mutates frozen `ko2unity_db` |
| `run_staging_ladder_replay.sh` | Marked deprecated May 2026 |
| `python -m scripts.ladder` **`run` / `reset` / `replay` CLI** | Full-memory replay + batch tails — not holy path |
| `scripts/work_prepare/ab-post-game.py` (+ related `ab_*` oracle) | Archived dev A/B; spawns ladder `run` |
| Dependent one-off repair PS1 that **require** batch chain (e.g. `rebuild_activity_wing_local.ps1` if it only chains batch) | Repair-only |

### 4.2 Tier B — Extract then trim (shared library)

After Amiga imports are repointed:

| Keep (relocate or rename package) | Retire from `engine.py` / package root |
|-----------------------------------|----------------------------------------|
| `player_state.py`, `elo.py`, `outcome.py`, `constants.py`, `config.py` | `replay_all`, `run_full`, `reset_universe` (once PHP-only zero-derived confirmed) |
| `apply_game_row()` (+ minimal `connect()` if needed) | `milestones.py`, `period_*.py`, batch SQL orchestration |
| `milestone_sim.py` | Optional — PHP `day_close_milestones.php` cites it as reference; keep until doc says otherwise |

**Proposed package name:** `scripts/k2_rating_core/` (implementation plan may adjust).

### 4.3 Tier C — Keep (holy or staging)

| Asset | Why keep |
|-------|----------|
| `site/public_html/ops/**` | Online holy ops |
| `python -m scripts.amiga prove` + `scripts/amiga/**` | Amiga holy ops |
| `scripts/export_ko2amiga_work.ps1` / `export_ko2amiga_db.ps1` (shim) / `setup_ko2amiga_db.ps1` | Staging export from work; oracle rebuild only |
| `scripts/prepare_local_work_db.ps1` | Already delegates to **PHP** `run_prepare.php` |
| `scripts/oneoff/load_milestone_definitions.py` | Catalog seed generator (not a derived fill path) |
| `ops/sql/generalstatstable.sql` | Canonical DDL (sync note with ladder copy until moved) |

### 4.4 Explicit non-goals

- Do **not** remove `python -m scripts.amiga` holy loop.
- Do **not** delete `scripts/ladder/player_state.py` (or extract) without updating **every** Amiga import + running **`prove`**.
- Do **not** “fix” batch SQL parity (DDR-052 etc.) — we are retiring the path.
- Do **not** assume “grep found no imports” is sufficient — run **§5 gate** including docs and PS1 wrappers.

---

## 5. Hard rule — per-file retirement gate

**Every file or directory retirement requires its own gate record.** Do not batch-delete folders. Template (copy into implementation plan checklist):

```text
RETIREMENT GATE — <path>
Date:
Agent:

G1. Online holy ops
  [ ] rg site/public_html/ops for path, basename, subprocess python, scripts.ladder
  [ ] rg run_ops_sim.php run_verify_ops_sim.php run_prepare.php dispatch.php
  [ ] Confirm: no proc_open/shell_exec to this asset

G2. Amiga holy ops
  [ ] rg scripts/amiga for imports / subprocess / __main__ prove chain
  [ ] rg site/public_html/amiga/ops for shell to python referencing this asset
  [ ] If scripts.ladder symbol: confirm not in §3.3 required list

G3. Repo-wide references
  [ ] rg entire repo (exclude docs/archive) for path and basename
  [ ] List PS1/sh wrappers and CI if any

G4. Docs
  [ ] List docs that still instruct use — schedule sweep slice

G5. Replacement
  [ ] Document what holy path replaces this (or “none — dev-only removed”)

G6. Proof after change
  [ ] Online: run_prepare zero-derived smoke OR note N/A if dev-only
  [ ] Amiga: `python -m scripts.amiga simul` on work (or oracle `prove` smoke if legacy touch only) if any amiga/ladder touch

G7. Sign-off
  [ ] Gate complete — safe to delete/stub/archive this slice
```

**Minimum proof after any slice touching `scripts/ladder` or `scripts/amiga/modern`:** `python -m scripts.amiga simul` green on **`ko2amiga_work`** (or documented smoke with Dagh approval). Legacy **`prove`** only when intentionally touching frozen oracle code paths.

---

## 6. Reasoning (why retire vs keep)

| Argument for retirement | Response |
|-------------------------|----------|
| “Might be useful for quick Elo check on dev” | Frozen `ko2unity_db` should not be replay-mutated; work DB uses simul |
| “Python oracle for PHP parity” | Sign-off = `run_verify_ops_sim.php`; `ab-post-game` archived |
| “Batch rebuild faster than 8h simul” | Wrong semantics (league, day-close milestones, club_* joins, etc.) — false economy |
| “Agents need a fallback” | Holy path + re-simul is the fallback; docs must stop listing batch |
| “Deleting scripts/ladder breaks Amiga” | **True for library modules** — extract first (Tier B), not a reason to keep `ladder run` |

---

## 7. Progress checklist (track level)

| Step | Status | Notes |
|------|--------|-------|
| Policy + investigation (this doc) | **Done** | Jun 2026 |
| Implementation plan written | **Done** | companion doc |
| Phase 1 — batch rebuild nuclear | **Done** | Jun 2026 slice 1 |
| Phase 2 — ladder CLI + wrappers | **Done** | Jun 2026 slice 2 |
| Phase 3 — work_prepare trim + refresh PS1 → PHP | **Done** | Jun 2026 slice 3 |
| Phase 4 — extract `k2_rating_core` | **Done** | Jun 2026 slice 4 |
| Phase 5 — doc sweep | **Done** | Jun 2026 slice 5 |
| Phase 6 — closure (MEMORY, DEAD_SURFACE) | **Done** | Jun 2026 slice 6 |

---

## 8. Related docs (sweep complete)

- [`PROJECT_MAP.md`](PROJECT_MAP.md) · [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) · [`work-db-prepare.md`](work-db-prepare.md) · [`website-data-contract.md`](website-data-contract.md) · [`DEAD_SURFACE.md`](DEAD_SURFACE.md) § Retired dev scripts

---

## 9. Recovery reference (frozen `ko2unity_db`)

**Confirmed (Jun 2026):** Wrong derived state on the **frozen dev** DB → **re-import** the May dump (`data/dumps/`, `data/README.md`). Do **not** run retired replay or batch CLIs. Work/sign-off DBs → holy ops only (§2).

---

*Parent track:* [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md) · *Amiga parallel:* [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)
