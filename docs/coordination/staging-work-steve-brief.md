# Staging work DB — brief for Steve (Jun 2026)

**Audience:** Steve (integration + game server) and Dagh (derived pipeline + website).  
**Purpose:** One place to understand *why* we are on staging now, what the five phases mean, and how `public_html/ops/` + `dispatch.php` fit in — without assuming you have read the whole repo.

**Short technical appendix:** [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md) (commands, ini file).  
**Dispatcher detail:** [`ops-dispatch.md`](ops-dispatch.md) (exit codes, failure semantics).

---

## 1. What we are trying to prove

Kick Off 2 ratings today on **prod** still uses **C++** to update ladder-derived columns after each game. We have rebuilt that logic in **PHP** on a **work database**: same ground-truth game rows, derived state recomputed by our modules (Elo, career stats, server records, milestones, league inputs, play streaks, etc.).

We are **not** asking you to cut over prod yet.

We **are** asking you to help run **`kooldb1` on the staging server** as a **full dress rehearsal**:

- Refresh from a pristine prod snapshot.
- Replay ~75k games in PHP (hours).
- Optionally point **ratings.kickoff2.com** at that DB so we all watch the site while it runs.
- Then wire the **game server** to write ground truth into `kooldb1` and call **one PHP entry** after each game — the same code simul used, one game at a time.

When that is boring, we repeat the pattern on **prod** (server 3).

---

## 2. Three databases on the staging host (names as we use them)

| Database | Nickname | Role |
|----------|----------|------|
| **`kooldb2`** | Baseline / snapshot | Recent **copy of prod**. **Read-only clone source.** Refresh work by dumping `kooldb2` → `kooldb1`. Do not experiment here. |
| **`kooldb1`** | **Staged work** | Where **all** prepare, simul, website tests, and live-shaped tests run. This is “fake prod” for the next weeks. |
| **Legacy staging DB** (e.g. `kooldb`) | Frozen reference | The DB the site used while Dagh built schema and batch fixes over ~2 weeks. **Human-verified truth** at a point in time (~game **74800**). We **compare** `kooldb1` to this at checkpoints; we do **not** rewrite it during the test. |

**Prod (server 3)** is unchanged until we deliberately promote.

---

## 3. Ground truth vs derived truth (who does what)

| | **Ground truth** | **Derived truth** |
|---|------------------|-------------------|
| **Meaning** | What happened in the match: players, score, date, etc. | Everything computed from history: Elo, `WinnerID`, `playertable` career fields, `generalstatstable`, period tables, milestones, league standings inputs, awards after finalize, … |
| **Who writes it today on prod** | **Steve** (game server → `ratedresults` insert) | **Steve’s C++** post-game |
| **Who writes it on staged work** | **Steve** (same as prod — app exe as if prod) | **PHP ops** (`public_html/ops/`) |
| **Steve’s call after a rated game (target)** | *(already done when row exists)* | `php ops/dispatch.php CMD=ProcessCompletedGame game_id=N target=staging-work` |

You do **not** pass scores or player ids in the dispatch call — PHP reads the row from `ratedresults`.

**League medals / career league stats** are **not** per-game. A **daily** job finalizes closed UTC periods:

`php ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work`

(Same rules as `run_finalize_league.php finalize-due`, exposed for cron.)

---

## 4. The five phases (how Dagh described the plan)

These are the **story beats**, not five separate projects. **Bug fixing** runs through all of them.

```text
  Broadcast          public site reads kooldb1 while we trust the pipeline
       ↑
  Simul              PHP replays history on kooldb1 (checkpoint or full)
       ↑
  Prepare            refresh → migrate → seed catalog → zero derived
       ↑
  (baseline kooldb2 intact)

  Live               game server writes ground truth + dispatch per game + midnight finalize
       ↑
  (after simul is trusted, or on a fresh prepare)

  Bug fixing         whenever parity or the site disagrees with frozen reference
```

### 4.1 Prepare

**Goal:** Turn `kooldb1` into a clean **day zero** for derived data while keeping **full game history** in `ratedresults`.

**One command** (from `public_html/`):

```bash
php ops/run_prepare.php prepare --target staging-work
```

That does, in order:

1. **Refresh** — clone `kooldb2` → `kooldb1` (you may already refresh by your usual process; prepare can include this).
2. **Migrate** — apply schema in `ops/sql/migrations/` (new tables, dropped columns, indexes).
3. **Seed catalog** — 112 milestone definitions from `ops/data/milestones_definitions_seed.json`.
4. **Zero derived** — clear Elo, career fields, aggregates, etc.; ground rows untouched.
5. **Seed lobby** — `entered_arena` for players with valid join dates.

**You do not need `dispatch.php` for prepare.** `run_prepare.php` is enough.

**Config:** `ops/config/work-targets.ini` (copy from `.example`; MySQL login = same as `ko2unitydb_config1.php`; only DB names differ).

---

### 4.2 Simul

**Goal:** Prove PHP post-game over **many** games — same code path as live, but driven in chronological batch.

```bash
php ops/run_process_game.php replay-to --until-game-id G --target staging-work   # checkpoint
php ops/run_process_game.php replay-to --target staging-work                    # full history
```

- Order: `Date ASC`, `id ASC`.
- Stops after game **G** when `--until-game-id` is set (inclusive).
- Full run is roughly **1–5 hours** depending on host.

**Checkpoint (~74800):** Dagh compares `kooldb1` at **G** to the **frozen reference DB** (SQL + spot checks on the site). Ground truth at G already matched in earlier checks; this pass is about **derived** state and UI.

**Optional:** `run_timeline_sim.php` if we need **league finalize at each UTC midnight** during replay (heavier). First pass can be post-game only and run finalize in a second pass.

**You do not need `dispatch.php` for simul.** `run_process_game.php` calls the same module as dispatch will.

---

### 4.3 Broadcast

**Goal:** While simul (usually **full** simul) runs, **visitors** see the ladder updating on the real staging hostname — Status, leagues, profiles, milestones — not a private DB view.

**What changes:** Dagh switches the **website** PHP config so **ratings.kickoff2.com** reads **`kooldb1`** (typically `ko2unitydb_config1.php`). That is a **Dagh / hosting** switch, not something we ask you to guess.

**What we do together:**

- You monitor server load (PHP CLI, MySQL, disk).
- Dagh watches pages and reports wrong numbers, missing medals, UTC countdown issues, etc.
- We treat surprising UI as **bugs to fix and re-run** (prepare + simul slice), not as “expected because staging.”

Broadcast is **not** a different pipeline — it is **simul + public read access** to the same `kooldb1`.

---

### 4.4 Live (live-shaped test)

**Goal:** After we trust batch simul, exercise **prod’s shape**: one new game at a time.

| Step | Who |
|------|-----|
| Game server persists rated result | **Steve** → `ratedresults` on **`kooldb1`** (credentials must point at work DB, **not** server 3 prod) |
| Derived update for that game | **Steve** (or cron wrapper) → `dispatch.php CMD=ProcessCompletedGame game_id=N target=staging-work` |
| Daily league finalize | **Cron** ~00:00:01 UTC → `dispatch.php CMD=FinalizeLeagueDue target=staging-work` |
| New registration lobby milestone | `CMD=ProcessPlayerRegistered player_id=N` when wired |

**Dispatcher = thin router.** It does not contain ladder rules; it connects to `kooldb1` and calls the same functions simul uses.

**Exit codes (scripting):**

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Failed — **do not** assume derived tables updated (transaction rolled back) |
| 2 | Already processed (`NewRatingA` set) — safe to skip duplicate call |
| 64 | Bad command line |

**Retry:** If exit **1** and `NewRatingA` is still NULL for that `game_id`, fix cause and call again.

---

### 4.5 Bug fixing

**Goal:** Close the loop when checkpoint parity or the broadcast site disagrees with what we already trust.

**Typical loop:**

1. Note game id **G** and what is wrong (Elo, milestone, league row, Status UTC, …).
2. Dagh fixes PHP/modules; sync `public_html/ops/` via WinSCP.
3. Re-run **prepare** (or `zero-derived` + simul from clean derived if ground is still trusted).
4. Re-run simul to **G** or full.
5. Re-check reference DB and site.

This is expected engineering, not a failure of the plan.

---

## 5. What ships in `public_html/ops/` (WinSCP)

Sync **`site/public_html/`** → server **`public_html/`**. That includes:

| Piece | Role |
|-------|------|
| `run_prepare.php` | Prepare, migrate, seed, zero-derived |
| `run_process_game.php` | Simul / per-game dev runner |
| `run_finalize_league.php` | League finalize (batch; same logic as dispatch CMD) |
| `run_timeline_sim.php` | Optional timeline simul |
| **`dispatch.php`** | **Your** stable entry: `CMD=ProcessCompletedGame`, `FinalizeLeagueDue`, `ProcessPlayerRegistered` |
| `ops/sql/migrations/` | Schema apply on work |
| `ops/data/milestones_definitions_seed.json` | Milestone catalog |
| `ops/config/work-targets.ini` | **Created on server only** (not in git) |

`ops/` is **CLI only** (not web-facing).

---

## 6. Suggested order (practical)

| Order | Phase | Notes |
|-------|-------|-------|
| 1 | **Prepare** | Once per “start over” cycle |
| 2 | **Simul (checkpoint)** | `--until-game-id` ~74800 vs frozen reference |
| 3 | **Bug fixing** | Until checkpoint is good |
| 4 | **Broadcast + full simul** | Site on `kooldb1`; replay to end |
| 5 | **Bug fixing** | From site + SQL during/after full run |
| 6 | **Live** | Game server → `kooldb1` + dispatch + cron |
| 7 | **Prod** | Later — server 3 |

Prepare before simul. Live after simul trust. Broadcast during full simul. Prod last.

---

## 7. What we need from Steve (checklist)

- [ ] Confirm **`kooldb1` / `kooldb2`** names and which DB is the frozen reference (`kooldb` or other).
- [ ] **`work-targets.ini`** on server (or confirm prepare connects with agreed credentials).
- [ ] Run or allow **`php ops/run_prepare.php prepare --target staging-work`** when Dagh asks for a clean cycle.
- [ ] Run **`replay-to`** (checkpoint or full) — or agree Dagh SSH runs it; same command either way.
- [ ] For **broadcast:** no change on your side except normal hosting; Dagh switches site config to `kooldb1`.
- [ ] For **live:** point **game server** DB at **`kooldb1`**; after each rated game call **`dispatch.php`**; schedule **`FinalizeLeagueDue`** at UTC midnight.
- [ ] **Do not mutate `kooldb2`** except as intentional refresh source.

---

## 8. What Dagh handles

- Parity SQL and decisions at checkpoint **G** (~74800) vs frozen reference.
- Switching **website** config to **`kooldb1`** for broadcast tests (and telling you when it is live).
- PHP fixes, WinSCP sync of `ops/`, docs/registers.
- Prod cutover plan **after** staging phases are boring.

---

## 9. One-sentence summary for the team

> **`kooldb1` is prod-shaped staging: Steve’s game writes ground truth there; PHP `ops/` rebuilds derived state (batch simul first, then `dispatch.php` per game); the public site can read the same DB so we catch real bugs before server 3.**

---

## 10. Command cheat sheet (from `public_html/`)

```bash
# Day zero
php ops/run_prepare.php prepare --target staging-work

# Simul
php ops/run_process_game.php replay-to --until-game-id 74800 --target staging-work
php ops/run_process_game.php replay-to --target staging-work

# League batch (simul follow-up or cron-shaped test)
php ops/run_finalize_league.php finalize-due --target staging-work

# Live-shaped (after trust)
php ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=staging-work
php ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work
php ops/dispatch.php CMD=Help
```

---

*Questions or different DB names on the host — reply to Dagh and we update this brief and `work-targets.ini.example`.*
