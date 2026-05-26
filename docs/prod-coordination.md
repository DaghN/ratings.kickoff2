# Production coordination — hub

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **WHAT** must happen on the live ladder database and server jobs before/after a feature is truly on prod. **HOW** for most jobs is documented per register; **post-game C++** has a fixed handoff shape (see below). Replay, schema, cron, and maintenance window details live in registers and the [cutover packet template](coordination/cutover-packet-template.md).

**Authority:** Product taste → `PROJECT_BRIEF.md`. Ladder/replay architecture → `docs/ladder-engine-plan.md`. **This hub** owns prod **coordination** registers until Dagh says otherwise.

**Day-to-day commands:** **`docs/OPERATIONS_QUICK_START.md`**

**Agents — “update docs”:** **`docs/UPDATE_DOCS.md`** — Part A every time; migration registers (Part B) only when relevant

**Philosophy:** This hub is a **migration backlog**, not part of daily vibecoding. Touch registers when a feature changes **stored** ladder data (L1+). L0 PHP-only features get one line in [`coordination/feature-log.md`](coordination/feature-log.md).

**Performance policy:** For DB-backed website work, treat stored/indexed/replayed truth as a normal option, not an exceptional burden. Steve handoff, schema SQL, replay/backfill, and post-game C++ snippets are project machinery we already have; their existence should not make agents default to slow live historical queries.

**Derived website data contract:** `docs/website-data-contract.md` is the behavior authority for project-owned aggregate tables, rebuild rules, parity checks, and post-game requirements. Coordination registers track deployment status.

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
| Post-game (C++) | [coordination/post-game-register.md](coordination/post-game-register.md) | **Retired snippet workflow** — prod C++ from [website-data-contract.md](website-data-contract.md); records: [records-post-game-exception.md](coordination/records-post-game-exception.md) |
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
5. **Deploy post-game C++** — Steve merges live writer from [website-data-contract.md](website-data-contract.md) post-game rules (+ [records exception](coordination/records-post-game-exception.md) if applicable).
6. **Deploy periodic jobs** (if any new/changed).
7. **Deploy PHP** (WinSCP or agreed path).
8. **Smoke checks** — spot profiles, ranked sort, one chart API; log in replay register.
9. **Mark registers done** — update status columns in each register file.

Staging rehearsal: steps 3–4 (+ PHP 7) on staging `kooldb` without live writes — proves server path only.

---

## Prod-readiness levels

| Level | Meaning | Example |
|-------|---------|---------|
| **L0** | PHP read-time only; no new stored truth | Status copy/layout change using existing columns |
| **L1** | Schema only; no replay/post-game yet | New nullable column, unfilled |
| **L2** | Replay must backfill history | New derived column on `playertable` |
| **L3** | *(retired for agents)* | Was “snippet pack required” — use **L2 + Prod live** in feature-log instead |
| **L4** | Staging-tested; cutover packet ready | Schema + REP on staging; contract documents post-game |
| **L5** | Prod done | Schema + REP + prod live writer; registers closed |

---

## Stored truth decision habit

Many website surfaces can be built two ways:

- **Read-time SQL:** query historical tables directly when the page loads.
- **Stored truth:** add an index, aggregate table, `playertable` field, replay output, post-game C++ writer, or periodic job so the page reads precomputed values.

Agents should actively consider the stored-truth path for any stat-heavy or hot DB-backed feature. The coordination cost is real, but it is expected workflow here, not a reason to avoid the better data shape. A small schema/index change can be the difference between multi-second pages and sub-second pages.

Prefer stored/indexed/replayed truth when:

- A query scans many rows in `ratedresults` / `resulttable` or repeats per player.
- The feature appears on hot pages such as profile, status, activity, leaderboards, Hall of Fame, achievements, or fun stats.
- The stat will be reused across pages or sessions.
- The result changes naturally after each game or on a predictable schedule.
- An index or aggregate would simplify PHP and reduce page-load risk.

Prefer read-time SQL when:

- The query is demonstrably cheap on realistic data.
- The feature is exploratory, temporary, admin-only, or rarely loaded.
- The result is hard to define as durable ladder truth.
- Stored state would add more complexity than the page-load cost justifies.

When stored truth is the right shape, use: schema migration, REP rebuild scripts, document post-game rules in `website-data-contract.md`, and `docs/UPDATE_DOCS.md` Part B. Do **not** add per-table C++ snippet packs.

---

## Tooling map

| Task | Location |
|------|----------|
| Schema SQL | `schema/migrations/` + `schema/README.md` |
| Apply schema locally | `schema/apply_local.ps1` |
| Full replay | `python -m scripts.ladder run --target local` / staging wrapper uses `--target staging` — `scripts/ladder/README.md` |
| Website derived data rebuild | `scripts/rebuild_website_derived_data_local.ps1` — contract `docs/website-data-contract.md` |
| Staging replay (Steve) | `docs/STAGING_REPLAY.md`, `run_staging_ladder_replay.sh` |
| One-off template | `scripts/oneoff/` |
| Live C++ reference | `docs/ratings_cpp.txt` |
| Post-game (prod cutover) | [website-data-contract.md](website-data-contract.md); records: [records-post-game-exception.md](coordination/records-post-game-exception.md) |
| Steve email/checklist | `docs/coordination/cutover-packet-template.md` |

---

## Steve coordination pattern

| Area | Our deliverable | Steve |
|------|-----------------|--------|
| **Schema** | `schema/migrations/*.sql` | Runs on the intended production DB |
| **Replay** | Tested Python + spec (`docs/replay-v1-scope-and-reset.md`) | Runs on server; or his C++ replay to same spec |
| **Post-game C++** | [website-data-contract.md](website-data-contract.md) (+ records exception doc) | Merges into his post-game code at prod cutover |
| **Periodic** | Register row + ask in cutover packet | Implements scheduler / stops fade |
| **PHP site** | WinSCP / agreed deploy | Host + DB read when needed |

- At prod cutover, Steve implements from the contract + `ratings_cpp.txt`; records use the exception doc with staging defect matrix.
- No SSH for Dagh on server (May 2026); Steve runs batch jobs when asked.

---

## Related docs

- `docs/ladder-engine-plan.md` — three worlds, P5 deferred
- `docs/replay-v1-scope-and-reset.md` — replay scope
- `docs/STAGING_REPLAY.md` — staging one-shot record
- `PROJECT_MEMORY.md` — logistics hand-off

*Created May 2026 — coordination track.*
