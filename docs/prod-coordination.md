# Production coordination — hub

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **WHAT** must happen on the live ladder database and server jobs before/after a feature is truly on prod. **HOW** for most jobs is documented per register; **post-game C++** has a fixed handoff shape (see below). Replay, schema, cron, and maintenance window details live in registers and the [cutover packet template](coordination/cutover-packet-template.md).

**Authority:** Product taste → `PROJECT_BRIEF.md`. Ladder/replay architecture → `docs/ladder-engine-plan.md`. **This hub** owns prod **coordination** registers until Dagh says otherwise.

**Day-to-day commands:** **`docs/OPERATIONS_QUICK_START.md`**

**Agents — “update docs”:** **`docs/UPDATE_DOCS.md`** — Part A every time; migration registers (Part B) only when relevant

**Philosophy:** This hub is a **migration backlog**, not part of daily vibecoding. Touch registers when a feature changes **stored** ladder data (L1+). L0 PHP-only features get one line in [`coordination/feature-log.md`](coordination/feature-log.md).

---

## Three databases (quick reference)

| Environment | DB name | Live game writes? | Site code | DB updates |
|-------------|---------|-------------------|-----------|------------|
| **Local** | `ko2unity_db` | No | Repo + Laragon | Dump import, `schema/`, `python -m scripts.ladder run --target local` |
| **Staging** | `kooldb` | **No** | WinSCP → `public_html/` | Steve: SQL, replay, one-offs — **not** live play |
| **Production** | Steve-managed live DB (not stored in repo) | **Yes** (C++ post-game + periodic jobs) | Steve / agreed deploy | Continuous + cutover packets |

Steve confirmed staging and production are on entirely different physical servers; do not infer production access from the staging `kooldb` name.

Full detail: `docs/ladder-engine-plan.md` §2, `docs/STATUS_PAGE_DATA.md`, `docs/LOCAL_DEV.md`.

---

## Registers (WHAT to coordinate)

| Register | File | Tracks |
|----------|------|--------|
| Schema | [coordination/schema-register.md](coordination/schema-register.md) | Tables, columns, indexes — SQL in `schema/migrations/` |
| Post-game (C++) | [coordination/post-game-register.md](coordination/post-game-register.md) | Per-game deltas + **snippet packs** in [coordination/cpp-snippets/](coordination/cpp-snippets/) |
| Periodic | [coordination/periodic-register.md](coordination/periodic-register.md) | Hourly fade, future cron jobs |
| Replay | [coordination/replay-register.md](coordination/replay-register.md) | Full-history rebuilds, parameters, run log |
| One-off | [coordination/one-off-register.md](coordination/one-off-register.md) | Rare scripts; prefer replay when possible |

**When a feature touches prod-bound data:** add/update register rows per [`UPDATE_DOCS.md`](UPDATE_DOCS.md) (usually at **“update docs”**, not at idea time). See [prod-readiness levels](#prod-readiness-levels) below.

---

## Standard cutover order (production)

Use for any release that changes **stored ladder truth** (not PHP-only cosmetics):

1. **Agree** cutover with Steve; send [cutover packet](coordination/cutover-packet-template.md).
2. **Turn off rating fade** (hourly) — required before deploy that changes ratings/stats semantics.
3. **Apply schema** — `schema/migrations/*.sql` in order on the production DB (Steve).
4. **Replay history** (if register says so) — Python `scripts/ladder` per `docs/replay-v1-scope-and-reset.md`, tested on staging; or Steve’s C++ replay to the **same written spec**.
5. **Deploy post-game C++** — Steve inserts our [snippet packs](coordination/post-game-cpp-handoff.md); **future** games maintain new columns/aggregates.
6. **Deploy periodic jobs** (if any new/changed).
7. **Deploy PHP** (WinSCP or agreed path).
8. **Smoke checks** — spot profiles, ranked sort, one chart API; log in replay register.
9. **Mark registers done** — update status columns in each register file.

Staging rehearsal: steps 3–4 (+ PHP 7) on staging `kooldb` without live writes — proves server path only.

---

## Prod-readiness levels

| Level | Meaning | Example |
|-------|---------|---------|
| **L0** | PHP read-time only; no new stored truth | Monthly league table on `status.php` (aggregate in SQL) |
| **L1** | Schema only; no replay/post-game yet | New nullable column, unfilled |
| **L2** | Replay must backfill history | New derived column on `playertable` |
| **L3** | Live writer on prod (C++ and/or periodic) | Per-game update; includes **C++ snippet pack** for Steve |
| **L4** | Staging-tested; Steve packet ready | Schema + replay on staging; snippet pack **ready for Steve** |
| **L5** | Prod done | Registers closed |

---

## Tooling map

| Task | Location |
|------|----------|
| Schema SQL | `schema/migrations/` + `schema/README.md` |
| Apply schema locally | `schema/apply_local.ps1` |
| Full replay | `python -m scripts.ladder run --target local` / staging wrapper uses `--target staging` — `scripts/ladder/README.md` |
| Staging replay (Steve) | `docs/STAGING_REPLAY.md`, `run_staging_ladder_replay.sh` |
| One-off template | `scripts/oneoff/` |
| Live C++ reference | `docs/ratings_cpp.txt` |
| Post-game snippet handoff | [coordination/post-game-cpp-handoff.md](coordination/post-game-cpp-handoff.md) |
| Steve email/checklist | `docs/coordination/cutover-packet-template.md` |

---

## Steve coordination pattern

| Area | Our deliverable | Steve |
|------|-----------------|--------|
| **Schema** | `schema/migrations/*.sql` | Runs on the intended production DB |
| **Replay** | Tested Python + spec (`docs/replay-v1-scope-and-reset.md`) | Runs on server; or his C++ replay to same spec |
| **Post-game C++** | **[Snippet packs](coordination/post-game-cpp-handoff.md)** in `docs/coordination/cpp-snippets/` (option 2, May 2026) | Inserts into his post-game code |
| **Periodic** | Register row + ask in cutover packet | Implements scheduler / stops fade |
| **PHP site** | WinSCP / agreed deploy | Host + DB read when needed |

- Dagh reviews snippet packs before send; agents draft from `ratings_cpp.txt` + schema docs + matching `scripts/ladder` logic.
- No SSH for Dagh on server (May 2026); Steve runs batch jobs when asked.

---

## Related docs

- `docs/ladder-engine-plan.md` — three worlds, P5 deferred
- `docs/replay-v1-scope-and-reset.md` — replay scope
- `docs/STAGING_REPLAY.md` — staging one-shot record
- `PROJECT_MEMORY.md` — logistics hand-off

*Created May 2026 — coordination track.*
