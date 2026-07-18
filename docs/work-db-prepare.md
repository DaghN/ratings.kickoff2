# Work DB — prepare, zero derived, and simul modes

**Status:** **Prepare platform v2 shipped** (Jun 2026) — PHP `run_prepare.php` + `prepare_local_work_db.ps1`. Retired Python prepare CLI: [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md).  
**Audience:** Dagh, Steve, Cursor agents.

**Canonical for:** `ko2unity_work` (local) · **`kooldb1`** (staging work) · pristine copies **`ko2unity_baseline`** / **`kooldb2`**.

**Related:** [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md) (names, safety) · [`ladder-ops-platform.md`](ladder-ops-platform.md) (ops CMDs, Steve boundary) · [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) (core ladder column manifest) · [`ground-truth-manifest.md`](ground-truth-manifest.md) (ground vs derived) · [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) (commands today)

---

## 1. Vocabulary (do not say “reset” alone)

| Term | Meaning | Local today | Avoid calling it |
|------|---------|-------------|------------------|
| **Refresh work** | Drop/recreate work DB and **clone from baseline** (full MySQL copy). Restores prod ground truth **and** prod-derived values in core tables. | `refresh_local_work_db.ps1` → PHP `run_prepare.php refresh-work` | “Reset work DB” |
| **Migrate work** | Apply `ops/sql/migrations/` to work only (indexes, KungFu drop, tables). | `ops/run_prepare.php migrate-work` | “Expand” alone (informal OK in chat) |
| **Seed catalog** | Load `milestone_definitions` reference rows (not unlocks). | `run_prepare.php seed-catalog` | — |
| **Sync catalog copy** | UPDATE `display_name` / `rule_short` from seed only (no TRUNCATE; keeps `holder_count`). | `run_prepare.php sync-catalog-copy` | Fix mojibake / copy drift |
| **Zero derived** | Clear **derived** columns/tables to **day-zero pre-game** state. Ground truth rows stay; facts on `ratedresults` stay. | `run_prepare.php zero-derived` (§4.3 + §4.5 truncates) | “Reset” without qualifier |
| **Simul** | Re-execute derived writers over history (umbrella term). See §3 modes. | `php ops/run_ops_sim.php run` | Assuming simul = only core Elo replay |

**Baseline** (`ko2unity_baseline` / `kooldb2`): frozen prod import — **never** migrate, replay, or zero derived. Only a **clone source** for refresh work.

**Dev** (`ko2unity_db`): daily browser DB — **not** this pipeline.

### 1.5 Work DB hygiene (sign-off — agents read this)

**Purpose of work** (`ko2unity_work` / **`kooldb1`**): take a prod copy → migrate → strip derived → **simul to present day** → verify. That is the **only** way to fill derived truth for cutover sign-off.

**Allowed on sign-off work** (`--target local-work` or `staging-work`):

| Step | Command |
|------|---------|
| Day zero | `run_prepare.php migrate-work` · `seed-catalog` · `zero-derived` (or `prepare`) |
| Prod-shaped fill | `run_ops_sim.php run` (full or `--until-game-id`) |
| Read-only check | `run_verify_ops_sim.php` · `run_milestone_orphan_probe.php` |
| Narrow module dev | `run_process_game.php` / `run_timeline_sim.php` **during** ops development — not a substitute for sign-off after a rule change |

**When derived state is wrong after a code fix:** **`zero-derived` → simul again.** Not repair jobs.

**Forbidden on sign-off work** (use `--target local-dev` / frozen `ko2unity_db` for legacy batch repair only):

| Do not run on work | Why |
|--------------------|-----|
| `run_finalize_league.php rebuild-all` / `rebuild-aggregates` | **Refused at CLI** — batch league repair, not simul |
| Retired dev batch SQL chain | See [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) |
| Ad-hoc “fix awards / milestones / holder_count” bulk SQL | Patch-in-place defeats the proof run |

**Steve on `kooldb1`:** sync `site/public_html/` → `migrate-work` → `seed-catalog` → `zero-derived` → `run_ops_sim.php` → `run_verify_ops_sim.php`. A full re-simul after a writer fix is **expected**, not a failure of the process.

**Canonical runbook:** [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md).

---

## 2. End state after **prepare** (before simul)

Work DB should be safe for **simul development** and browsing `http://work.ratingskickoff.test/` without stale prod Elo/stats:

| Requirement | Detail |
|-------------|--------|
| Schema | Same project tables/columns as dev (migrations applied on work). |
| Ground truth | Full `ratedresults` history; `eventhistory` / `resulttable` unchanged; `playertable` identity/prefs preserved. |
| Derived | **No** prod-era ladder or website aggregate data that could mislead simul or PHP — day-zero pre-game (§4). |
| Not done yet | Chronological simul has **not** run; that is §3. |

**Warning:** After **refresh work** only, the work URL still shows **prod ratings** until **migrate work** + **zero derived** complete.

---

## 3. Prepare pipeline

### 3.1 Full prepare (default)

Use when work is untrusted, schema on work is wrong/missing, or you need a new prod snapshot on work.

```text
1. Refresh work       clone ko2unity_baseline → ko2unity_work  (wipes prior migrations on work)
2. Migrate work       ops/sql/migrations on ko2unity_work (indexes, KungFu drop 015, new tables)
3. Seed catalog       milestone_definitions from ops/data/milestones_definitions_seed.json (112 rows)
4. Zero derived       derived day-zero on work (§4)
5. Seed lobby         entered_arena from playertable.JoinDate for all registered players (§4.7)
```

**Staging mirror:** Steve (or agreed SQL) refreshes **`kooldb1`** from **`kooldb2`**, then migrations, then zero derived.

**Order note:** Migrate **before** zero derived so new tables exist and can be emptied; zero derived **after** migrate so new derived columns are cleared too. Refresh **destroys** a prior migrate — always re-migrate after refresh.

### 3.2 Fast prepare (schema already current on work)

When only derived state is wrong (bad replay, partial simul):

```text
Zero derived only     (includes seed lobby — step 5 of full prepare)
```

Skip refresh if ground truth on work is still trusted.

### 3.3 Commands (prepare platform v2 — preferred)

```powershell
# Full prepare + parity checks
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1

# Fast prepare (zero derived only)
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1 -ZeroOnly

# Dry run
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1 -DryRun
```

**CLI (preferred):** `php site/public_html/ops/run_prepare.php prepare --target local-work` (add `--zero-only` or `--dry-run`). See [`site/public_html/ops/README.md`](../site/public_html/ops/README.md) and [`OPS_STANDARDS.md`](OPS_STANDARDS.md).

Optional: `site/config/work-targets.ini` from `work-targets.ini.example` (staging-work profile).

**Never** refresh/migrate/zero on **`ko2unity_baseline`** / **`kooldb2`**.

### 3.4 Legacy manual path (historical)

Superseded by PHP `run_prepare.php` verbs. See [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md).

### 3.5 Parity checklist (v2 vs legacy)

After **full prepare**, `php site/public_html/ops/run_prepare.php parity --target local-work` should report all **PASS**:

| Check | Expect |
|-------|--------|
| `ratedresults_count_vs_baseline` | Same row count as baseline |
| `ratedresults_core_ids_match_baseline` | `idA`, `idB`, `Date` match baseline (UTC session — same as ladder replay) |
| `ratedresults_goals_match_baseline` | `GoalsA` / `GoalsB` unchanged |
| `index_idx_ratedresults_idA` / `idB` / `date` | Present after migrate |
| `kungfu_columns_absent` | No `KungFu%` columns (post **015**) |
| `recent_average_rating_column_absent` | No `playertable.RecentAverageRating` (post **016**) |
| `milestone_definitions_seeded` | 112 catalog rows |
| `ratedresults_derived_cleared` | `NewRatingA IS NULL` on all rows |
| `playertable_rating_day_zero` | All `Rating = 1600` |
| `player_milestones_lobby_seeded` | `entered_arena` rows = playertable with valid `JoinDate`; no other keys |
| `player_period_games_empty` / `server_daily_activity_empty` | 0 rows (or table missing before migrate) |

Compare to historical path: v2 `zero-derived` clears §4.5 tables; legacy shortcuts may leave stale aggregate rows.

**Operational smoke:** `zero-derived` → `run_ops_sim.php run` → `run_verify_ops_sim.php` on work after prepare.

---

## 4. Zero derived — checklist (signed off Jun 2026)

**Intent:** After zero derived, every **derived** cell is empty or at contract default as if **no rated game had been processed** — but all match **facts** remain in `ratedresults`.

**Sign-off:** Dagh — full §4 accepted as contract. **Implemented:** PHP `run_prepare.php zero-derived` (core + §4.5 truncates).

**Authority for column lists:** [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) (core five tables) · [`ground-truth-manifest.md`](ground-truth-manifest.md) · [`website-data-contract.md`](website-data-contract.md).

### 4.1 Never touch (ground)

| Object | Action |
|--------|--------|
| `eventhistory` | Leave as imported |
| `resulttable` | Leave as imported |
| `ratedresults` | Preserve: `id`, `Date`, `idA`, `idB`, `NameA`, `NameB`, `GoalsA`, `GoalsB` |
| `playertable` | Preserve identity, account, prefs, profile, telemetry, `Feedback_*`, `LastLogin`, `LastActive`, lobby fields — see replay §5.1 |

### 4.2 Leave as import (signed off — do not zero)

| Object | Action | Notes |
|--------|--------|--------|
| `playertable.Display` | **Leave** as import | Not ladder-derived; legacy Unity/site listing |
| `playertable.PlayerRank` | **Leave** as import | Not Dagh ladder lane; Steve B / legacy if prod changes needed |

**Website:** Rows with `Display = 1` and NULL career fields are valid between zero-derived and replay catch-up (e.g. joins present on work baseline after dev freeze). Leaderboards still list them; PHP renders unset stats as `-` via `k2_safety.php` formatters — see [`playertable-schema.md`](playertable-schema.md) § Display without derived career stats.

### 4.3 Core ladder derived (implemented: `reset_universe`)

| Object | Action |
|--------|--------|
| `ratedresults` | NULL all derived columns (Elo, `WinnerID`, flags, …) — replay §4.2 |
| `playertable` | `Rating = 1600`; NULL career derived; sentinels per replay §5.2 |
| `generalstatstable` | Ensure row `id=1`; NULL/clear all data columns (whole table derived) |

**CLI:** `php site/public_html/ops/run_prepare.php zero-derived --target local-work`.

### 4.4 Schema cleanup via migrate (not zero derived)

| Object | Action |
|--------|--------|
| `playertable.KungFu*` + `resulttable.KungFuGameID` | **Drop columns** via migration **015** — not zeroed |
| `generalstatstable` obsolete ratio/HoF columns | **Drop** per migration 002 — not zeroed |

Re-run **migrate work** after every **refresh work** so drops stay applied.

### 4.5 Project aggregate tables — truncate empty (derived-only)

These tables **do not exist** on baseline until **migrate work**. After migrate, they must be **empty** at day zero (TRUNCATE or equivalent).

| Table | Notes |
|-------|--------|
| `player_period_games` | Status/Activity aggregates |
| `player_peak_period_games` | Peak panels |
| `player_activity_participation` | Activity wing participation + longevity (SCH-022) |
| `server_daily_activity` | Daily chart source |
| `player_period_league` | League activity slices |
| `player_matchup_summary` | Matchup aggregates |
| `server_period_game_totals` | Period totals |
| `server_period_matchups` | Period matchup breadth |
| `player_milestones` | Unlock rows only |
| `player_play_streaks` | Rated play streak state |
| `player_league_award` | Period awards (PER-003) |
| `player_league_totals` | Career league totals |
| `player_league_slice_totals` | Slice totals |
| `league_period` | Closed period registry |

**Catalog (not player state):**

| Table | Action |
|-------|--------|
| `milestone_definitions` | **Do not truncate** — static catalog from migrations; not simul output |

### 4.7 Lobby milestone seed (ground truth, not simul)

After §4.5 truncates `player_milestones`, **seed lobby** restores `entered_arena` from preserved `playertable.JoinDate` (register = enter lobby — live-faithful, not `NumberGames >= 1`).

| Key | Eligibility | `achieved_at` | `source_kind` |
|-----|-------------|---------------|---------------|
| `entered_arena` | Valid `JoinDate` on `playertable` | `JoinDate` | `lobby` |

Runs at end of **zero derived** (full and fast prepare). Idempotent. Not produced by game replay — see [`website-data-contract.md`](website-data-contract.md) § Lobby.

**CLI:** `seed-lobby` verb or bundled in `zero-derived` / `prepare`.

### 4.6 Implementation (prepare platform v2)

| Layer | Contract | Implementation |
|-------|----------|----------------|
| Core ladder day-zero | §4.3 | PHP `run_prepare.php zero-derived` |
| Aggregate truncates (§4.5) | TRUNCATE at day zero | Same — skips missing tables |
| Lobby seed (§4.7) | `entered_arena` from `JoinDate` | Bundled in `zero-derived` / `prepare` |
| Full prepare orchestrator | Refresh → migrate → seed catalog → zero derived | `prepare_local_work_db.ps1` / `run_prepare.php prepare` |

**Seed catalog:** `prepare` runs `seed-catalog` after migrate (112 rows from `site/public_html/ops/data/milestones_definitions_seed.json`). **Seed lobby:** `zero-derived` inserts `entered_arena` for every player with valid `JoinDate`. Other `player_milestones` unlock rows stay empty until simul.

---

## 5. Simul (after prepare)

**Simul** = any faithful re-execution of derived writers over history. Prepare ends at day zero; simul **writes** derived truth.

### 5.1 Modes

| Mode | What runs | When to use |
|------|-----------|-------------|
| **A — Game-only** | Per-game processor × N in `Date, id` order (Elo, career, GST batch at end of run) | Ladder parity, post-game module dev |
| **B — Game + batch website rebuild** | Mode A, then truncate/rebuild aggregate SQL + league/milestone rebuild scripts | **Retired** — repair archive only; see retirement policy |
| **C — Timeline** | Mode A **plus** `FinalizeUtcDay` at each UTC day boundary | **Prod-shaped simul** — league, league milestones, day-close |

**Today:** Mode A ≈ `php ops/run_process_game.php replay-to`. Mode B ≈ archived batch repair (not sign-off). Mode C ≈ **`php ops/run_ops_sim.php run`** (or `run_timeline_sim.php`) — see [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md). **`entered_arena`:** prepare §4.7 only, not Mode C loop.

`run_ops_sim.php` assumes **zero derived** at start — equivalent to prepare step 4 + Mode C in one command.

### 5.2 Target equivalence

Game *N* after simul should match prod after game *N* only if periodic side effects are **modelled** (Mode C or careful batch). Batch rebuild (Mode B) is a shortcut, not the definition of simul.

### 5.3 Live path (later)

After cutover: Steve inserts ground → `CMD=ProcessCompletedGame` per game; periodic jobs on schedule — same core as Mode A/C, not a separate ruleset.

---

## 6. Quick reference — what not to confuse

| Question | Answer |
|----------|--------|
| Does refresh work clear derived? | **No** — it **restores prod derived** in core tables. You must zero derived after. |
| Does migrate work clear derived? | **No** — shape only. |
| Is `reset_local_work_db.ps1` “zero derived”? | **No** — it is **refresh work**. |
| Can I browse work safely mid-pipeline? | Only after **zero derived** if you must not see stale prod stats. |
| Baseline dirty with prod Elo? | **Expected** — baseline is not prepared; work is. |

---

## 7. Related commands

| Goal | See |
|------|-----|
| One-time sandbox create | `scripts/setup_local_prod_sandbox.ps1`, `data/README.md` |
| Verify DB roles | `scripts/verify_local_databases.ps1` |
| Retired dev fill scripts | [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) |

---

## 8. Changelog

| When | What |
|------|------|
| 2026-06 | Initial doc pass: vocabulary, prepare order, simul modes A/B/C, ZeroDerived checklist. |
| 2026-06 | **§4.7 seed lobby** — `entered_arena` from `JoinDate` at end of zero-derived. |
| 2026-06 | **Prepare platform v2** — `scripts/work_prepare/`, parity command, legacy aliases. |
| 2026-06 | **§4 signed off** (Dagh). |
| 2026-06 | **§1.5 work DB hygiene** — sign-off = prepare + simul only; no batch repair on work; CLI refuses `rebuild-all` on work targets. |
