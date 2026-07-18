# Production coordination — hub

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **WHAT** must happen on the live ladder database and server jobs before/after a feature is truly on prod. **HOW** Steve runs PHP ops: [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) · live commands: [`steve-live-ops.md`](../site/public_html/ops/docs/steve-live-ops.md).

**Authority:** Product taste → `PROJECT_BRIEF.md`. Ops design → [`ladder-ops-platform.md`](ladder-ops-platform.md). **This hub** owns migration registers and cutover **process** until Dagh says otherwise.

**Day-to-day commands:** [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md)

**Agents — “update docs”:** [`UPDATE_DOCS.md`](UPDATE_DOCS.md) — Part A every time; registers (Part B) only when stored truth changes.

**Philosophy:** This hub tracks **live prod execution** when stored truth changes — not day-to-day repo work. **Prep is done** for the Jun 2026 ops cutover set (migrations in package, simul proven on `kooldb1`) — see [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md). L0 PHP-only features get one line in [`coordination/feature-log.md`](coordination/feature-log.md).

**Performance policy:** For DB-backed website work, treat stored/indexed/replayed truth as a normal option. Schema SQL, replay/backfill, and **PHP ops post-game** are expected machinery — not reasons to default to slow live historical queries.

**Derived website data contract:** [`website-data-contract.md`](website-data-contract.md) — behaviour authority for aggregate tables, rebuild rules, post-game rules. Registers track **deployment status** only. Cutover index: [`coordination/post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md).

### Post-game runtime (Jun 2026)

| | |
|--|--|
| **Rules (what)** | [`website-data-contract.md`](website-data-contract.md) post-game §§ |
| **Reference implementation** | PHP `ops/run_process_game.php` + `ops/dispatch.php` — see [`post-game-php-development.md`](post-game-php-development.md) |
| **Prod today** | **PHP ops** live since **2026-07-18** — Steve inserts ground truth → `CMD=ProcessCompletedGame` (+ `FinalizeUtcDay` cron); **C++ derived retired** — **do not extend C++** |
| **Steve / repo split** | Steve: ground insert + hosting + invoke · Repo: derived contract + writers + website — [`ladder-ops-platform.md`](ladder-ops-platform.md) §2 |

**Agents:** Do **not** cite “prod still on C++” or “M1–M7 pending cutover” as blocking local/staging or website work. Do **not** recreate `cpp-snippets/`. Historical C++: [`ratings_cpp.txt`](ratings_cpp.txt) (read-only reference).

---

## Three databases (quick reference)

| Environment | DB name | Live game writes? | Site code | DB updates |
|-------------|---------|-------------------|-----------|------------|
| **Local dev** | `ko2unity_db` | No | Repo + Laragon | Dump import, `schema/apply_local.ps1`, `--target local-dev` |
| **Local sandbox** | `ko2unity_work` (+ `ko2unity_baseline` pristine) | No | `work.ratingskickoff.test` | Prepare: [`work-db-prepare.md`](work-db-prepare.md); simul: `--target local-work` |
| **Staging** | `kooldb1` work / `kooldb2` reset (legacy `kooldb` possible) | **No** | WinSCP → `public_html/` | Steve: SQL, replay, ops simul — **not** live play |
| **Production** | Steve-managed live DB (not in repo) | **Yes** (**PHP ops** since **2026-07-18**; C++ derived retired) | Steve / agreed deploy | Continuous + packets when stored truth changes |

Steve confirmed staging and production are on entirely different physical servers.

Full detail: [`coordination/database-copies-2026-06.md`](coordination/database-copies-2026-06.md), [`LOCAL_DEV.md`](LOCAL_DEV.md).

---

## Registers (WHAT to coordinate)

| Register | File | Tracks |
|----------|------|--------|
| **Cutover readiness** | [cutover-readiness.md](coordination/cutover-readiness.md) | **Start here** — prep (A+B) vs live execution (C) |
| Schema | [schema-register.md](coordination/schema-register.md) | SCH DDL in `ops/sql/migrations/` |
| Post-game | [post-game-register.md](coordination/post-game-register.md) | PHP ops cutover pointer |
| Periodic | [periodic-register.md](coordination/periodic-register.md) | Scheduled jobs (`FinalizeUtcDay`, etc.) |
| Replay (historical) | [replay-register.md](coordination/replay-register.md) | Stub → May 2026 batch log |
| One-off | [one-off-register.md](coordination/one-off-register.md) | Rare scripts; prefer ops simul when possible |

**When a feature touches prod-bound data:** update registers per [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part B (usually at **“update docs”**). See [prod-readiness levels](#prod-readiness-levels).

---

## Standard cutover order (production)

Use when a release changes **stored ladder truth** (not PHP-only cosmetics):

1. **Agree** cutover with Steve; send [cutover packet](coordination/cutover-packet-template.md).
2. **Apply schema** — `ops/sql/migrations/*.sql` in order on the production DB (Steve).
3. **Ops simul backfill** — `php ops/run_ops_sim.php run` then `run_verify_ops_sim.php` on prod copy first (**not** per-table `*_rebuild.sql` marathon).
4. **Deploy PHP ops + site** — WinSCP `public_html/` incl. `ops/`; Steve configures `work-targets.ini`.
5. **Wire live dispatch** — after each rated game: ground insert → `dispatch.php CMD=ProcessCompletedGame`; ~00:00:01 UTC: `CMD=FinalizeUtcDay` ([`steve-live-ops.md`](../site/public_html/ops/docs/steve-live-ops.md)).
6. **Records exception** — if Hall of Fame / `generalstatstable` behaviour changed: [`records-post-game-exception.md`](coordination/records-post-game-exception.md) (parity vs legacy C++ on cutover, not new C++ development).
7. **Smoke checks** — spot profiles, ranked sort, one chart API; log in replay register.
8. **Mark registers done** — update feature-log + register status columns.

**Staging rehearsal:** steps 2–5 on **`kooldb1`** (or prod copy) without live prod writes — batch simul + verify already signed off Jun 2026.

---

## Prod-readiness levels

| Level | Meaning | Example |
|-------|---------|---------|
| **L0** | PHP read-time only; no new stored truth | Status copy/layout using existing columns |
| **L1** | Schema only; no backfill yet | New nullable column, unfilled |
| **L2** | Replay / simul must backfill history | New derived column on `playertable` |
| **L3** | *(retired)* | Was “snippet pack required” |
| **L4** | **Prep complete** — ops package + simul verified on work DB (`kooldb1` / prod copy) | migrate-work + simul + verify; see [`cutover-readiness.md`](coordination/cutover-readiness.md) |
| **L5** | **Live executed** | Steve ran cutover on live prod + dispatch wired |

---

## Stored truth decision habit

Prefer stored/indexed/replayed truth when hot pages scan `ratedresults` heavily or values update per game. Prefer read-time SQL when cheap and exploratory.

When stored truth is right: schema in `ops/sql/migrations/`, fill history via **ops simul**, document post-game in [`website-data-contract.md`](website-data-contract.md), Part B of UPDATE_DOCS. Implement writers in **PHP ops**, not new C++.

---

## Tooling map

| Task | Location |
|------|----------|
| Schema SQL | `ops/sql/migrations/` |
| Apply schema locally | `schema/apply_local.ps1` · `ops/run_prepare.php migrate-work` |
| Work prepare / simul | [`work-db-prepare.md`](work-db-prepare.md) · `ops/run_prepare.php` · `ops/run_ops_sim.php` |
| Post-game PHP | `ops/run_process_game.php` · `ops/dispatch.php` |
| Steve bootstrap → live | [`ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |
| Website derived fill (happy path) | `ops/run_ops_sim.php` — see [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md) |
| Website derived rebuild (retired dev repair) | [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) |
| Legacy C++ (historical only) | [`ratings_cpp.txt`](ratings_cpp.txt) |
| Post-game rules | [`website-data-contract.md`](website-data-contract.md) |
| Records cutover notes | [`records-post-game-exception.md`](coordination/records-post-game-exception.md) |
| Steve email/checklist | [`cutover-packet-template.md`](coordination/cutover-packet-template.md) |

---

## Steve coordination pattern

| Area | Our deliverable | Steve |
|------|-----------------|--------|
| **Schema** | `ops/sql/migrations/*.sql` | Runs on prod / prod copy |
| **Backfill** | Tested simul + verify on staging or prod copy | Runs CLI on server |
| **Post-game (cutover)** | PHP `ops/` synced; contract §§ | Inserts ground truth; calls `dispatch.php` |
| **Periodic** | `FinalizeUtcDay` in ops; register row | Cron ~00:00:01 UTC |
| **PHP site** | WinSCP `public_html/` | Host + DB config |

No SSH for Dagh on server (May 2026); Steve runs batch jobs when asked.

---

## Related docs

- [`ladder-ops-platform.md`](ladder-ops-platform.md) — Steve boundary, module layout
- [`post-game-php-development.md`](post-game-php-development.md) — PHP post-game build guide
- [`replay-v1-scope-and-reset.md`](replay-v1-scope-and-reset.md) — core ladder replay scope
- `PROJECT_MEMORY.md` — logistics hand-off

*Updated Jun 2026 — PHP ops cutover; C++ legacy prod-only until Steve switches.*
