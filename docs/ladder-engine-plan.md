# Ladder engine & replay — plan and intent

**Status:** Agreed direction (May 2026). **Not yet implemented** — no Python replay code in repo until a separate “go” on implementation.

**Audience:** Dagh, Steve, and Cursor agents. This doc captures **decisions from design discussion** so we do not re-derive them from chat history.

**Related docs:**

| Doc | Role |
|-----|------|
| `PROJECT_BRIEF.md` | Product taste and north star |
| `PROJECT_MEMORY.md` | Logistics, shipped charts, deploy |
| `docs/ratings_cpp.txt` | Legacy **live** post-game logic (reference only) |
| `docs/ratedresults-schema.md` | Online per-game row shape (snapshot) |
| `docs/playertable-schema.md` | Online per-player aggregates (snapshot) |
| `docs/generalstatstable-schema.md` | Server-wide single row `id=1` (snapshot) |

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

---

## 3. Conceptual cut from legacy

We are **not** building “50% like prod” by porting `RatingProcedureUnity` line for line.

We **are** building:

- A **small, explicit engine** with clear operations (§4).
- A **nod** to `docs/ratings_cpp.txt` for Elo shape, column names we choose to support, and “what prod roughly does today.”
- **Freedom** to omit or simplify legacy baggage (e.g. huge `generalstatstable` record churn per game) until a feature needs it.

**Why:** Sandbox has **no obligation** to match prod’s decay or every aggregate. Offline will differ in **how rows enter** the system. A greenfield Python design is easier to reason about than a fork of ~2000 lines of C++.

**Steve coordination (agreed pattern):**

- Dagh develops scripts in **Git**; documents exact command and target DB.
- Steve **runs** them on the server when needed (`python3 …`) — SSH shell for Dagh is **not** working (permission denied / connection closed on port 5322); Steve has no strong preference on language.
- When live behaviour should change: **conversation + C++ update**, not surprise changes on prod.

---

## 4. Engine API (intended shape)

Language: **Python 3** (Dagh’s choice — clean headspace, readable, testable; separate from PHP site code).

Core operations (same *ideas* for both tracks; different adapters):

| Function | Purpose |
|----------|---------|
| **`reset_universe(...)`** | Prepare for a full replay: derived state back to agreed baselines. **Immutable game facts** (scores, players on row, dates) usually **kept**; Elo snapshots on game rows, career aggregates, server stats **cleared or zeroed**. Opponent/victim/culprit style fields **must** be rebuilt by replay, not carried over. |
| **`apply_game(...)`** | One rated game: read current player ratings → compute result + Elo → write per-game row → update both players’ aggregates (and server stats only if v1 needs them). |
| **`replay_all(...)`** | `reset_universe` then every game in **`Date`, `id` ascending** order calling `apply_game`. |

**Replay order:** Chronological — same as APIs and rating history charts (`ORDER BY Date ASC, id ASC`).

**“Adam and Eve”:** Agreed. Partial reset of `playertable` while leaving stale cross-player counters is **unsafe**; full replay rebuilds dependent fields.

---

## 5. Two implementation tracks (manageable duplication)

### Track A — Online-shaped **dev** (first)

- Target: dev DB with familiar tables (`ratedresults`, `playertable`, optionally `generalstatstable`).
- Assumption: players often **already exist** in `playertable` before games (like online join/manual insert).
- Deliverable: `reset` + `replay_all` for sandbox; validate site charts and ranked sort on staging.
- Reference: `docs/ratings_cpp.txt` for formula and which columns matter **first**.

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
| Per-game row | Pre-ratings, expected, adjustments, new ratings, goals, flags |
| `playertable.Rating` after game | New rating after game |

**Simplify or postpone until needed:**

- Full `generalstatstable` server-records update every game (can recompute or slim subset later).
- Victim/culprit decrement SQL mid-game — on replay, recompute counts from history or defer.
- Every extreme and streak field on day one — add as fun-stats / profile needs them.
- **`Display = 1` at 1 game** in C++ vs **established = 20 games** on website — keep website rules in PHP; engine can use its own display rules for sandbox.

**Decay:** Dagh wants it **removed** from desired behaviour. Not present in the supplied C++ excerpt; may exist elsewhere in Steve’s tree. New engine: **no decay**. Steve to remove from live when agreed.

**Not in excerpt (confirm with Steve before prod parity):** `Kfactor` numeric value, starting rating for new players, draw `WinnerID` (-1 in C++ vs always set in DB dump), unrated-player paths.

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
| **Development** | Python in repo under `scripts/` (layout TBD: e.g. `scripts/ladder/`, `scripts/online/`, `scripts/offline/`). |
| **Config** | Gitignored server/local ini or environment variables; document variables in README section when code exists. |
| **Running** | Steve runs on staging host against **dev DB**; Dagh sends command + expected duration + “paste last lines of output.” |
| **SSH** | Not available for Dagh (May 2026); do not block on it. |
| **Safety** | Full replay only on **dev** until explicit prod plan + backup. |
| **PHP throwaways** | Schema probes (`scripts/throwaway_*.php`) remain valid for introspection; delete from `public_html` after use. |

**Confirm once with Steve:** `python3` available on staging and MySQL client library installable if needed.

---

## 10. Phased roadmap

| Phase | Deliverable |
|-------|-------------|
| **P0 — Spec v1** | List columns `reset` clears and `apply_game` updates for online dev (minimal set for charts: Elo on row, `playertable.Rating`, W/D/L optional). |
| **P1 — Online dev replay** | Python `reset` + `replay_all` on sandbox; no decay; agreed K and start rating. |
| **P2 — Validate** | Staging charts, ranked order, spot-check players; note deltas vs old prod numbers. |
| **P3 — Offline schema + import** | How Amiga raw data maps to vocabulary; player-from-results. |
| **P4 — Offline replay** | Second track scripts; separate DB connection. |
| **P5 — Live alignment** | Meeting with Steve: C++ changes or future shared engine; prod cutover + backup. |

Chart backlog and “fun stats” on profiles can proceed in PHP against **sandbox** once P2 is trustworthy.

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

1. Starting **Rating** for new/replayed players (1600? value from Steve?).
2. **`Kfactor`** numeric value for sandbox v1.
3. Where **decay** lives in Steve’s full tree and removal plan for live.
4. Whether v1 must update **`generalstatstable`** for `server1.php` headline stats.
5. Amiga: physical **separate database** vs tables on same server.
6. Draw handling for **`WinnerID`** in engine vs website H2H (use `ActualScore == 0.5`).

---

## 13. Authority

- **Product taste:** `PROJECT_BRIEF.md` and Dagh’s latest chat message.
- **This doc:** Intent and architecture for ladder engine / replay until superseded by Dagh.
- **Schema snapshots:** `docs/*-schema.md` are DB truth at capture time; re-run throwaways if columns change.

When this doc and `PROJECT_MEMORY.md` disagree on engine intent, **this doc wins** for replay/offline work.
