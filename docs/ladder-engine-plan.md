# Ladder engine & replay — plan and intent

**Status:** **P0–P2 done** (May 2026) — local **`ko2unity_db`** + staging **`kooldb`** one-shot replay. **Deferred:** P3–P5 (Amiga/offline, prod C++ alignment). **Commands:** `scripts/ladder/README.md`; staging record: **`docs/STAGING_REPLAY.md`**; logistics: `PROJECT_MEMORY.md`.

**Audience:** Dagh, Steve, and Cursor agents. This doc captures **decisions from design discussion** so we do not re-derive them from chat history.

**Related docs:**

| Doc | Role |
|-----|------|
| `PROJECT_BRIEF.md` | Product taste and north star |
| `docs/prod-coordination.md` | **Prod cutover hub** — registers, schema/replay/Steve packet |
| `PROJECT_MEMORY.md` | Logistics, shipped charts, deploy, CLI quick reference |
| `scripts/ladder/README.md` | **How to run** `reset` / `replay` / `run` (authoritative for commands) |
| `docs/ratings_cpp.txt` | Legacy **live** post-game logic (reference only) |
| `docs/ratedresults-schema.md` | Online per-game row shape (snapshot) |
| `docs/playertable-schema.md` | Online per-player aggregates (snapshot) |
| `docs/generalstatstable-schema.md` | Server-wide single row `id=1` (snapshot) |
| `data/README.md` | Local SQL dump import (not in Git) |
| `docs/LOCAL_DEV.md` | Laragon, `ratingskickoff.test`, local DB name |
| `docs/replay-v1-scope-and-reset.md` | **P0** — scope + reset manifest (expanded in v2 replay) |

---

## 1. What we are trying to do

### Goals

1. **Dev database (sandbox)** — Recalculate ratings and derived stats from all stored games using rules we control (e.g. **no rating decay**), so charts and profiles on staging reflect trustworthy numbers while we iterate on the PHP site.

2. **Amiga 500 / offline universe (later)** — Same *kind* of pipeline for a **separate** results population, with import and identity rules that differ from online (e.g. players created from results, not account signup).

3. **Website (PHP)** — Continue improving presentation (charts, fun stats, tone). The site **reads** the database; it does not own the replay engine.

4. **Production online (later, with Steve)** — When sandbox behaviour is understood and agreed, align **live** post-game processing (today: Steve’s C++) with the desired formula — **not** a silent assumption that sandbox code already runs in prod.

### Non-goals (for this phase)

- Byte-for-byte reimplementation of legacy C++ for its own sake.
- Forcing sandbox recalc to match current prod numbers before decay is removed and rules are agreed.
- Deciding how the **website** exposes two realms (separate pages vs `realm=` parameter vs duplicate APIs) — see §8.
- Committing DB passwords or running destructive jobs on production without an explicit cutover plan.

---

## 2. Three worlds (do not conflate)

| World | Who writes after each live game | Database | Engine |
|-------|----------------------------------|----------|--------|
| **Production online** | Steve’s C++ (legacy + updates) | Live ladder DB | Existing server tool |
| **Dev sandbox** | **New Python engine** (batch replay; no live feed while sandbox-only) | Dev copy (e.g. `kooldb` on staging config) | `scripts/` Python — online track |
| **Amiga 500 / offline** | Import + **Python offline track** | Separate DB or separate tables (TBD physically) | `scripts/` Python — offline track |

**Mental model:** Legacy C++ is **prod reference** and **coordination point** with Steve. Python is **our clean room** for dev and offline until we deliberately merge behaviour into live.

### Databases: who gets live game writes? (confirmed May 2026)

| Environment | Typical DB name | Live C++ post-game writes? | How data stays current |
|-------------|-----------------|------------------------------|-------------------------|
| **Production** (joshua / live ladder) | `kooldb` | **Yes** — each rated game + periodic jobs (e.g. hourly rating fade) | Continuous |
| **Staging** (`ratings.kickoff2.com`) | `kooldb` | **No** — game server does **not** write here | PHP deploy via WinSCP; DB updated by **dump import**, **Steve-run scripts** (replay, schema SQL, one-offs), or manual refresh — not by live play |
| **Local** (Laragon) | `ko2unity_db` | **No** | SQL dump import + local Python replay / migrations |

**Implication:** Staging is for **code + agreed batch jobs** on a writable copy, not for testing “the next game landed in the DB.” Status panels that need `IsOnline` / live `resulttable` rows will look **stale** on staging unless you refresh the dump or point PHP at prod (read-only, Steve agreement). Prod cutover testing = schema SQL + replay packet + updated C++ spec, rehearsed on staging first.

---

## 3. Conceptual cut from legacy

We are **not** building “50% like prod” by porting `RatingProcedureUnity` line for line.

We **are** building:

- A **small, explicit engine** with clear operations (§4).
- A **nod** to `docs/ratings_cpp.txt` for Elo shape, column names we choose to support, and “what prod roughly does today.”
- **Freedom** to omit per-game legacy baggage where a **batch** rebuild at end of replay is enough (e.g. `generalstatstable` — see `scripts/ladder/generalstats.py`).

**Why:** Sandbox has **no obligation** to match prod’s decay or every aggregate. Offline will differ in **how rows enter** the system. A greenfield Python design is easier to reason about than a fork of ~2000 lines of C++.

**Steve coordination (agreed pattern):**

- Dagh develops scripts in **Git**; documents exact command and target DB.
- Steve **runs** them on the server when needed (`python3 …`) — SSH shell for Dagh is **not** working (permission denied / connection closed on port 5322); Steve has no strong preference on language.
- When live behaviour should change: **conversation + C++ update**, not surprise changes on prod.

---

## 4. Engine API (implemented in `scripts/ladder/`)

Language: **Python 3** (Dagh’s choice — clean headspace, readable, testable; separate from PHP site code).

CLI: **`python -m scripts.ladder`** with `reset`, `replay`, or `run` (see README).

Core operations (same *ideas* for both tracks; offline track not built yet):

| Function | Purpose |
|----------|---------|
| **`reset_universe(...)`** | Prepare for a full replay: derived state back to agreed baselines. **Immutable game facts** (scores, players on row, dates) usually **kept**; Elo snapshots on game rows, career aggregates, server stats **cleared or zeroed**. Ensures **`generalstatstable`** exists (DDL from `scripts/ladder/sql/`) and NULLs row `id=1`. |
| **`apply_game_row(...)`** | One rated game in memory + DB row dict: read current ratings → result + Elo → update both **`PlayerState`** aggregates (v2: extremes, streaks, cross-player record pointers). |
| **`replay_all(...)`** | Chronological replay of all `ratedresults`, batch-write games and `playertable`, then **`finalize_network_counts`** and **`rebuild_generalstats_if_present`** (server row **once at end**, not per game). |

**Replay order:** Chronological — same as APIs and rating history charts (`ORDER BY Date ASC, id ASC`).

**“Adam and Eve”:** Agreed. Partial reset of `playertable` while leaving stale cross-player counters is **unsafe**; full replay rebuilds dependent fields.

---

## 5. Two implementation tracks (manageable duplication)

### Track A — Online-shaped **dev** (first)

- Target: dev DB with familiar tables (`ratedresults`, `playertable`, `generalstatstable`).
- Assumption: players often **already exist** in `playertable` before games (like online join/manual insert).
- **Done locally:** `reset` + `replay_all` on **`ko2unity_db`**; Laragon site validated (profiles, leaderboards, server stats).
- **Staging:** **done** — see **`docs/STAGING_REPLAY.md`** (Steve, May 2026).
- Reference: `docs/ratings_cpp.txt` for formula and which columns matter.

### Track B — **Offline / Amiga 500** (second)

- **Separate entry scripts**, tweaked for offline reality — not one file full of `if offline`.
- Example differences (expected):
  - **Player identity:** create `playertable` rows when a **new name** appears in results; no join date at account creation.
  - Possibly **extra columns** or tables; nullable offline-only fields OK if vocabulary stays aligned.
  - Import path from raw Amiga results before replay.
- Reuse: **Elo math**, replay loop, **schema vocabulary** (§6) — extract shared module when copy-paste hurts.

**Drift risk:** Fix bug in track A, forget track B. Mitigation: shared `ladder/` (or `scripts/common/`) for Elo + `ActualScore` from goals; one **“shared rules”** section in this doc; update both tracks when formula changes.

---

## 6. Schema vocabulary (agreed now)

**Decision:** One **naming contract** across universes so PHP/SQL for charts can be reused or parameterized later.

- Table **roles:** per-game results table, per-player table (same logical names preferred: `ratedresults`, `playertable`).
- **Column names** for shared concepts stay aligned with online schema docs (`GoalsA`, `RatingA`, `NewRatingA`, `ActualScore`, `WinnerID`, `playertable.Rating`, `NumberGames`, `*GameID`, etc.) unless offline raw data forces normalization **on import** into those names.
- Offline may add **extra nullable columns**; avoid renaming core columns per universe.

**Deferred (explicitly):**

- Physical layout: two databases vs `realm` column vs prefixed tables.
- How PHP pages choose universe (`realm=` query param vs separate site section vs second DB connection in config).

**Import rule of thumb:** Normalize messy Amiga data **into** this vocabulary at import time rather than maintaining parallel chart APIs with different column names.

---

## 7. What we borrow vs simplify from legacy C++

From `docs/ratings_cpp.txt` / live behaviour (reference):

| Topic | Intended alignment |
|-------|-------------------|
| Elo expected score | Logistic: `1 / (1 + 10^((Rb-Ra)/400))` |
| Adjustment | `K * (ActualScore - Expected)`; zero-sum between A and B |
| `ActualScore` from goals | A win `1`, draw `0.5`, B win `0` |
| `WinnerID` from goals | A win → `idA`; B win → `idB`; draw → **`-1`** (non-NULL sentinel; matches live dump) |
| Per-game row | Pre-ratings, expected, adjustments, new ratings, goals, flags |
| `playertable.Rating` after game | New rating after game |

**Simplify vs live C++ (sandbox replay):**

- **`generalstatstable`:** **batch rebuild** of row `id=1` after full replay (not 74k incremental UPDATEs). Prod keeps C++ per-game updates after any one-shot recalc.
- **Victim/culprit pointer counts:** cross-player ±1 semantics ported in v2 `player_state`; network counts (`DifferentVictims`, etc.) from `finalize_counts`.
- **Career extremes / streaks:** v2 `playertable` rebuild covers profile and ranked needs; fun-stats PHP can follow.
- **`Display = 1` at 1 game** in C++ vs **established = 20 games** on website — keep website rules in PHP; engine can use its own display rules for sandbox.

**Decay:** Dagh wants it **removed** from desired behaviour. Not present in the supplied C++ excerpt; may exist elsewhere in Steve’s tree. New engine: **no decay**. Steve to remove from live when agreed.

**Not in excerpt (confirm with Steve before prod parity):** `Kfactor` numeric value for live (sandbox v1 uses **K=32**), starting rating for new players (sandbox **1600**), unrated-player paths.

---

## 8. Website and PHP (deferred decisions)

The PHP site loads DB credentials from server-only config:

```text
public_html/../config/ko2unitydb_config.php
```

Dev vs prod = **what that file points at**, not a second committed config in Git.

**For Python on the server:** Use the **same connection values** (env vars or gitignored `config.ini` on server — **not** committed). Do not rely on PHP `include` from CLI unless we add a tiny bridge.

**Two realms on website — TBD:**

- Same APIs + `?realm=amiga500`
- Same SQL + different DB connection per realm
- Duplicate pages under `/amiga/`
- etc.

This plan **does not** choose one. Schema vocabulary (§6) is chosen so any option stays easy later.

---

## 9. Execution and environment

| Item | Plan |
|------|------|
| **Development** | **`scripts/ladder/`** (online track); future **`scripts/offline/`** or similar for Amiga. |
| **Config** | **`ko2unitydb_config.php`** (same as PHP); optional gitignored **`ladder.ini`**; allowlist `ko2unity_db`, `kooldb`. Staging steps: **`docs/STAGING_REPLAY.md`**. |
| **Staging run** | **`docs/STAGING_REPLAY.md`** only — WinSCP + Steve shell; not Git deploy. |
| **SSH** | Not available for Dagh (May 2026); do not block on it. |
| **Safety** | Full replay only on **dev** until explicit prod plan + backup. |
| **Schema migrations** | `schema/migrations/` + register `docs/coordination/schema-register.md` |
| **PHP throwaways** | Schema probes (`scripts/throwaway_*.php`) remain valid for introspection; delete from `public_html` after use. |

**Confirm once with Steve:** `python3` available on staging and MySQL client library installable if needed.

---

## 10. Phased roadmap

| Phase | Status | Deliverable |
|-------|--------|-------------|
| **P0 — Spec v1** | **Done** | **`docs/replay-v1-scope-and-reset.md`** — scope, reset manifest, column contract. |
| **P1 — Online dev replay** | **Done (local)** | **`scripts/ladder/`** — `reset` + `replay_all` + v2 `playertable` + batch `generalstatstable`. |
| **P2 — Validate** | **Done** | Local Laragon + staging **`kooldb`** one-shot replay (May 2026). |
| **P3 — Offline schema + import** | **Deferred** | Amiga raw data → vocabulary; player-from-results. |
| **P4 — Offline replay** | **Deferred** | Second track scripts; separate DB connection. |
| **P5 — Live alignment** | **Deferred** | Steve: C++ decay removal / formula alignment; prod cutover + backup. |

PHP/charts work can continue on **local `ko2unity_db`** and **staging `kooldb`**; prod cutover waits for P5.

---

## 11. Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Sandbox numbers ≠ live site | Label as sandbox; do not promise parity until P5. |
| Two Python tracks drift | Shared Elo module + this doc’s shared rules. |
| Over-scoping v1 | Minimal column set; expand when a feature requires it. |
| Under-scoping reset | Document `reset` SQL clearly; test on disposable dev DB. |
| Amiga schema unknown | Vocabulary agreed; physical tables flexible. |
| Steve busy | Scripts runnable with one command; clear logs. |

---

## 12. Open questions (to resolve during implementation)

1. Starting **Rating** for new/replayed players on **live** (sandbox replay locked to **1600** in `docs/replay-v1-scope-and-reset.md`).
2. **`Kfactor`** on **live** (sandbox v1 locked to **32**).
3. Where **decay** lives in Steve’s full tree and removal plan for live.
4. ~~Whether v1 must update **`generalstatstable`**~~ — **yes:** batch rebuild at end of `run` (`generalstats.py`); table auto-created if missing.
5. Amiga: physical **separate database** vs tables on same server.

---

## 13. Authority

- **Product taste:** `PROJECT_BRIEF.md` and Dagh’s latest chat message.
- **This doc:** Intent and architecture for ladder engine / replay until superseded by Dagh.
- **Schema snapshots:** `docs/*-schema.md` are DB truth at capture time; re-run throwaways if columns change.

When this doc and `PROJECT_MEMORY.md` disagree on **engine architecture / phases**, **this doc wins**. For **CLI usage and current logistics**, prefer **`scripts/ladder/README.md`** and **`PROJECT_MEMORY.md`**.
