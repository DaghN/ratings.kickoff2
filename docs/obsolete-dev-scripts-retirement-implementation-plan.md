# Obsolete dev scripts — implementation plan

**Status:** **Ready to execute** — policy locked in [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md).  
**Rule:** **§5 retirement gate in the policy doc is mandatory for every file** — no folder-level deletes without per-file gates.

---

## 0. Prerequisites

- Read policy doc §3 (holy ops investigation) before slice 0.
- Do **not** start deletion in the same chat as policy-only unless Dagh explicitly says **execute slice N**.
- **Online proof smoke (when touching online paths):**  
  `php site/public_html/ops/run_prepare.php zero-derived --target local-work` (dry-run OK for gate-only)  
  Full sign-off remains full simul — not required per micro-slice unless the slice changes post-game writers.
- **Amiga proof (when touching `scripts/ladder` or `scripts/amiga`):**  
  `python -m scripts.amiga prove` — **required** before closing any slice that moves/renames ladder library code.

---

## 1. Slice overview

| Slice | Goal | Risk |
|-------|------|------|
| **0** | Inventory + gate template populated | Low |
| **1** | Batch rebuild nuclear (PS1 + SQL archive) | Low — not on holy path |
| **2** | Ladder CLI + wrappers retired/stubbed | Medium — ensure Amiga imports untouched |
| **3** | `work_prepare` oracle trim; `refresh_local_work_db.ps1` → PHP | Low–medium |
| **4** | Extract `k2_rating_core`; repoint Amiga imports | **High** — run `prove` |
| **5** | Doc sweep + misleading runbook removal | Low |
| **6** | Closure checklist + MEMORY | Low |

**STOP gates:** After slice 2, 3, 4 — run Amiga `prove` if any `scripts.ladder` file changed. After slice 4 — **mandatory** full `prove`.

---

## 2. Slice 0 — Inventory and gates (no deletions)

**Deliverable:** Copy of §5 gate filled for **every candidate path** in §2.1 below (can live in this file as checkboxes or a scratch `docs/archive/obsolete-scripts-gates-2026-06.md` if the list grows).

**Actions:**

1. Run baseline greps (record counts in gate notes):

```powershell
# Online ops — should be comments only for scripts.ladder
rg -i "python|scripts\.ladder|scripts/ladder|\.py" site/public_html/ops --glob "*.php"

# Amiga ops PHP — should be user-facing hints only, no exec
rg -i "exec\(|shell_exec|proc_open|python" site/public_html/amiga/ops --glob "*.php"

# Amiga Python imports from scripts.ladder
rg "from scripts\.ladder|import scripts\.ladder" scripts/amiga

# Repo references to batch / ladder run
rg "rebuild_website_derived|run_local_replay|scripts\.ladder run|batch-2026-05" --glob "*.{ps1,sh,php,py,md}"
```

2. Confirm holy path commands unchanged:

| Command | Must remain working |
|---------|---------------------|
| `php site/public_html/ops/run_ops_sim.php run --target local-work` | Yes |
| `php site/public_html/ops/run_verify_ops_sim.php --target local-work` | Yes |
| `python -m scripts.amiga prove` | Yes |

**Exit criteria:** Gate row started for each §2.1 path; investigation matches policy §3.

---

## 2.1 Candidate retirement inventory

Use **policy §5 gate** per row. Status column updated during execution.

### Tier A — Retire first (no Amiga library dependency)

| Path | Holy online? | Holy Amiga? | Gate | Status |
|------|--------------|-------------|------|--------|
| `scripts/rebuild_website_derived_data_local.ps1` | No | No | | ☐ |
| `scripts/ladder/sql/archive/batch-2026-05/` (entire dir → `docs/archive/...`) | No | No | | ☐ |
| `scripts/ladder/sql/archive/one-off-2026-06/` (audit SQL only) | No | No | | ☐ |
| `scripts/run_local_replay.ps1` | No | No | | ☐ |
| `run_staging_ladder_replay.sh` | No | No | | ☐ |
| `scripts/reset_local_work_db.ps1` (deprecated forwarder) | No | No | | ☐ |
| `scripts/rebuild_activity_wing_local.ps1` | No | No | | ☐ |
| `scripts/work_prepare/ab_post_game.py` | No | No | | ☐ |
| `scripts/work_prepare/ab_*.py` (oracle modules) | No | No | per file | ☐ |

### Tier A — Stub after library extract (slice 2–4)

| Path | Holy online? | Holy Amiga? | Gate | Status |
|------|--------------|-------------|------|--------|
| `scripts/ladder/__main__.py` | No | No | | ☐ |
| `scripts/ladder/milestones.py` | No | No | | ☐ |
| `scripts/ladder/period_activity.py` | No | No | | ☐ |
| `scripts/ladder/period_aggregates.py` | No | No | | ☐ |
| `scripts/ladder/golden_record_checks.py` | No | No | | ☐ |
| `scripts/ladder/engine.py` — `replay_all`, `run_full`, `reset_universe` | No | **Imports:** `apply_game_row` only | | ☐ |

### Tier B — Extract to `scripts/k2_rating_core/` (keep semantics)

| Source | Consumers after move |
|--------|----------------------|
| `player_state.py` | Amiga holy writers + tests |
| `elo.py`, `outcome.py` | `apply_game_row` |
| `constants.py` | Amiga + ops PHP alignment comments |
| `config.py` | Amiga `config.py`, oneoffs (update or keep thin re-export) |
| `engine.py` — `apply_game_row`, `connect` | Amiga `finalize_tournament.py` |
| `finalize_counts.py` | Only if still needed by `apply_game_row` / Amiga — **gate before move** |
| `milestone_sim.py` | Reference for PHP day-close — **keep** unless doc explicitly drops |

### Tier C — Do not retire

| Path | Reason |
|------|--------|
| `site/public_html/ops/**` | Online holy ops |
| `scripts/amiga/**` | Amiga holy ops |
| `scripts/prepare_local_work_db.ps1` | PHP delegate |
| `scripts/export_ko2amiga_db.ps1`, `setup_ko2amiga_db.ps1` | Staging; calls `prove` |
| `scripts/oneoff/load_milestone_definitions.py` | Catalog seed tool |
| `ops/sql/generalstatstable.sql` | DDL authority |

---

## 3. Slice 1 — Batch rebuild nuclear

**Goal:** Remove misleading batch fill entry points; archive SQL.

**Steps (each with §5 gate before edit):**

1. **`scripts/rebuild_website_derived_data_local.ps1`**  
   - Replace body with stub: print deprecation, point to `run_ops_sim.php` + policy doc, `exit 1`.  
   - Gate G1–G7.

2. **`scripts/ladder/sql/archive/batch-2026-05/`**  
   - Move to `docs/archive/batch-rebuild-sql-2026-05/` (or `scripts/ladder/sql/archive/` → `docs/archive/` with README).  
   - Leave stub README at old path pointing to archive + policy.  
   - Gate per SQL file **or** one gate for directory if move is copy-preserving (still run G3 repo-wide).

3. **Dependent PS1** (`rebuild_activity_wing_local.ps1`, any other that shells to rebuild script)  
   - Stub or delete per gate.

4. **Quick verify:**  
   - `rg rebuild_website_derived_data_local` — only stubs/archive/README remain in active paths.

**Exit criteria:** No runnable batch chain on `ko2unity_db` without deliberate archive dig; holy ops untouched.

---

## 4. Slice 2 — Ladder CLI and wrappers

**Goal:** Stop advertising `python -m scripts.ladder run`.

**Steps:**

1. **`scripts/run_local_replay.ps1`** — delete or stub → policy doc. Gate.

2. **`run_staging_ladder_replay.sh`** — delete or move to `docs/archive/`. Gate.

3. **`scripts/ladder/__main__.py`** — replace `run`/`reset`/`replay` verbs with stderr message:
   - online work → `run_ops_sim.php`
   - Amiga → `scripts.amiga prove`
   - historical → policy doc  
   Gate.

4. **`scripts/ladder/README.md`** — rewrite as **library + history**; remove “commands” for `run`.

5. **Do not** delete `milestones.py` / `period_*.py` until slice 4 confirms no imports (Amiga does not import them today).

**Exit criteria:** `python -m scripts.ladder run` fails fast with helpful message; `python -m scripts.amiga prove` still green.

---

## 5. Slice 3 — `work_prepare` trim and refresh PS1

**Goal:** Holy prepare path is PHP-only for routine use.

**Steps:**

1. **`scripts/refresh_local_work_db.ps1`** — rewire to PHP:
   ```powershell
   # Delegate to run_prepare.php refresh-work --target local-work
   ```
   (mirror `prepare_local_work_db.ps1` pattern). Gate.

2. **`scripts/work_prepare/ab_post_game.py`** + `ab_*.py` — archive to `docs/archive/work-prepare-ab-oracle-2026-05/` or delete after gate shows no CI/cron references.

3. **`scripts/work_prepare/zero_derived.py`** — if only caller was legacy CLI, stub `work_prepare` verb with “use PHP zero-derived”. **Gate:** confirm `prepare_local_work_db.ps1` and `run_prepare.php` do not call Python.

4. **`scripts/work_prepare/README.md`** — mark archived; point to `ops/README.md`.

**Exit criteria:** Full local prepare via `prepare_local_work_db.ps1` uses PHP only; no subprocess to `scripts.ladder run`.

---

## 6. Slice 4 — Extract `k2_rating_core` (highest risk)

**Goal:** Amiga holy path imports from a clearly named package; trim `scripts/ladder` to archive or delete.

**Steps:**

1. Create `scripts/k2_rating_core/` with moved modules (see §2.1 Tier B).

2. Update imports in:
   - `scripts/amiga/finalize_tournament.py`
   - `scripts/amiga/replay.py`
   - `scripts/amiga/config.py`
   - `scripts/amiga/snapshot_*.py`, `realm_persist.py`, `matchup_cumulative.py`, `elo_rank.py`, `player_stats_load.py`
   - `scripts/amiga/test_*.py` as needed

3. Optional compatibility shim in `scripts/ladder/__init__.py`:
   ```python
   # Deprecated re-exports — remove after one release
   from scripts.k2_rating_core.player_state import PlayerState  # etc.
   ```
   Only if gate finds unexpected third-party imports; prefer clean break + grep.

4. Remove or archive empty `scripts/ladder/` remainder.

5. Update PHP mirror comments (`post_game_*.php`, `ops_prepare_constants.php`) to cite `k2_rating_core` instead of `scripts/ladder`.

**Mandatory proof:**

```powershell
python -m scripts.amiga prove
python -m unittest scripts.amiga.test_snapshot_row scripts.amiga.test_realm_row -v
```

**Exit criteria:** `prove` green; no `from scripts.ladder` in `scripts/amiga/` (except shims if kept).

---

## 7. Slice 5 — Doc sweep

Update or archive pointers in (minimum list):

| Doc | Change |
|-----|--------|
| [`PROJECT_MAP.md`](PROJECT_MAP.md) | Remove ladder replay from essential commands; add retirement policy link |
| [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) | Remove `run_local_replay` / batch rebuild rows |
| [`work-db-prepare.md`](work-db-prepare.md) | §1 simul row: remove `python -m scripts.ladder run` |
| [`website-data-contract.md`](website-data-contract.md) | Event engine authority = PHP ops; batch refs → archive |
| [`post-game-php-development.md`](post-game-php-development.md) | Demote Python oracle to historical |
| [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) | Banner: historical; holy path = ops simul |
| [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md) | Remove “replay + batch before simul” |
| [`scripts/ladder/README.md`](../scripts/ladder/README.md) or `k2_rating_core/README.md` | Library-only |
| [`AGENTS.md`](../AGENTS.md) | Trap: do not suggest ladder `run` |

**Exit criteria:** `rg "run_local_replay|rebuild_website_derived|scripts\.ladder run"` on active docs (exclude `docs/archive/`) returns only retirement policy + archive pointers.

---

## 8. Slice 6 — Closure

- [ ] Policy doc §7 checklist all **Done**
- [ ] `PROJECT_MEMORY.md` — one line: retirement track complete
- [ ] Optional: `docs/DEAD_SURFACE.md` row per retired path
- [ ] Confirm with Dagh: frozen `ko2unity_db` recovery = re-import dump, not replay scripts

---

## 9. Starter prompt (new chat — execute slice N)

```text
Read first:
- docs/obsolete-dev-scripts-retirement-policy.md (§3 holy ops, §5 gate rule)
- docs/obsolete-dev-scripts-retirement-implementation-plan.md

Task: Execute slice <N> only.

Non-negotiable:
- Complete policy §5 retirement gate for EVERY file touched before delete/stub/move
- Online holy ops = PHP ops only — no accidental Python removal from prove path
- Amiga: python -m scripts.amiga prove must stay green if scripts/ladder or scripts/amiga changes
- Do not fix batch SQL parity — retire paths only

Report: gate checklist per file + proof commands run + docs updated in slice scope.
```

---

## 10. Recovery reference (post-retirement)

| Need | Use instead of retired script |
|------|-------------------------------|
| Fill derived on work / `kooldb1` | `zero-derived` → `run_ops_sim.php run` → `run_verify_ops_sim.php` |
| Fill Amiga derived | `python -m scripts.amiga prove` |
| Refresh local work from baseline | `php site/public_html/ops/run_prepare.php refresh-work --target local-work` |
| Repair frozen `ko2unity_db` | Re-import May dump (`data/dumps/`) — **do not** batch-rebuild |
| Future quick parity tool | Build new from contract — do not restore `ladder run` |

---

*Policy:* [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md)
