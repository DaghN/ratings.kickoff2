# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Ladder ops (Jun 2026):** PHP post-game **P0–P7** in `ops/run_process_game.php` + `dispatch.php`. **Staging simul signed off** on `kooldb1` (`run_verify_ops_sim` 0 fail). **Next (Steve):** live cutover when scheduled — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md). Discrepancies: [`post-game-contract-vs-oracle-discrepancies.md`](docs/coordination/post-game-contract-vs-oracle-discrepancies.md).

- **Milestones:** Catalog **112**; v0 UI + **`kooldb1` simul proof** done. Live writer = **PHP ops** at cutover (not C++).

- **Cutover prep (done):** Schema + PHP ops + **simul proven on `kooldb1`** — [`cutover-readiness.md`](docs/coordination/cutover-readiness.md). **Live prod execution** = Steve when scheduled (not repo backlog).

- **Rated play streaks:** **Proven `kooldb1`** (`ranked4`, HoF). Live = PHP ops at cutover.

- **Leagues:** **Honours proven `kooldb1`** (`leaderboards/league-honours.php`). Live = `FinalizeUtcDay` when wired.

- **Status Leagues:** Phase **1** shipped. Optional backlog only — [`status-period-competitions-wip.md`](docs/status-period-competitions-wip.md) (no agent handoff).

- **Profile:** Feast shipped on **`player/profile.php`**. Optional **lab compare** only (`individual1-profile-lab*.php`) — prompts in [`archive/profile-lab-agent-handoff.md`](docs/archive/profile-lab-agent-handoff.md); live spec [`player-profile-feast.md`](docs/player-profile-feast.md).

- **Design / Status hub:** Phase B v1.2 room grid shipped. Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](docs/activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones (v0) · HoF · Play & Setup — [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). **URLs (Jun 2026):** semantic paths + `leaderboards/` + `player/` folders; registry [`k2_routes.php`](site/public_html/includes/k2_routes.php) — [`url-routes.md`](docs/url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Operational loop:** edit locally → **WinSCP** sync `site/public_html/` → staging; hard refresh. Steve runs server one-offs.

- **Local:** `ratingskickoff.test` (dev) + `work.ratingskickoff.test` (work) — [`LOCAL_DEV.md`](docs/LOCAL_DEV.md).

- **Change style:** small, reversible slices.

- **Amiga realm (Jun 2026):** **A2** shipped — ground/derived schema (`amiga_games`, `amiga_game_ratings`, …), `scripts/amiga/replay.py`, read path `includes/amiga_db.php`. **Staging live** on `ratings.kickoff2.com`; **multi-part browser import verified** (16 parts, `&apply=1&part=1`). **A2 audit** [`audits/amiga-a2-restructure-audit-2026-06-06.md`](docs/audits/amiga-a2-restructure-audit-2026-06-06.md) — export `MAX(id)`, contract-order replay, import/replay docs. **“Export to staged”:** agent runs `scripts\export_ko2amiga_db.ps1` → WinSCP sync `_import/` + `run_import_ko2amiga.php` — [`amiga-staging-handoff.md`](docs/amiga-staging-handoff.md). **Data design:** [`amiga-data-contract.md`](docs/amiga-data-contract.md). **Next:** Track B tournament standings + reference parity.

---

## Deep reference (read on demand)

| Topic | Where |
|--------|--------|
| Live post-game (legacy prod only) | `docs/ratings_cpp.txt` — historical; cutover = PHP ops |
| Ladder ops / PHP post-game | `docs/ladder-ops-platform.md` §2 · `docs/post-game-php-development.md` |
| Per-game table | `docs/ratedresults-schema.md` |
| Replay / Elo sandbox | `scripts/ladder/`, `docs/replay-v1-scope-and-reset.md` |
| Profile layout / charts | `docs/player-profile-feast.md` |
| Activity charts (plan + registry) | `docs/activity-charts.md` |
| Status hub spec | `docs/STATUS_PAGE_DATA.md` |
| Cutover readiness (prep vs live) | `docs/coordination/cutover-readiness.md` |
| Schema DDL status | `docs/coordination/schema-register.md` |
| `player_milestones` row-count timeline | `docs/archive/replay-register-2026-05.md` § Milestone unlock row counts |
| Prod cutover | `docs/prod-coordination.md`, `site/public_html/ops/docs/post-dagh-live-story.md` |
| Ladder ops platform (Steve, `ops/`, sim) | `docs/ladder-ops-platform.md` |
| DB copies (local + staging names) | `docs/coordination/database-copies-2026-06.md` |
| Work DB prepare / simul | `docs/work-db-prepare.md` |
| Ground vs derived columns | `docs/replay-v1-scope-and-reset.md`, `docs/ground-truth-manifest.md` (online) · **`docs/amiga-data-contract.md`** (Amiga) |

---

## Next (prioritised intent)

**Dagh**

1. **Profile** — gradual improvements on production feast (lab pages optional) — [`player-profile-feast.md`](docs/player-profile-feast.md) · [`profile-build-playbook.md`](docs/profile-build-playbook.md).
2. **Status Leagues** — optional polish only — [`status-period-competitions-wip.md`](docs/status-period-competitions-wip.md) (Phase 1 shipped).

**Steve (when ready)**

3. **Prod copy → live PHP ops** — migrate / seed / zero / simul / dispatch — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md); WinSCP `public_html/ops/`.

**Migration habit (not a numbered task):** stored-truth changes → [`UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) Part B + [`prod-coordination.md`](docs/prod-coordination.md) registers.

---

## Recent log

*(Newest first. ~30 rows max. Older rows: [`docs/archive/session-log-2026-q2.md`](docs/archive/session-log-2026-q2.md).)*

| When | What |
|------|------|
| 2026-06 | **Agent doc grep-trap pass** — `schema/migrations` → `ops/sql/migrations` in active playbooks; cutover checklist + replay-v1 + coordination README traps; facilitators doc renamed; wip/status + script allowlists aligned. |
| 2026-06 | **Ops Steve UX + dispatch** — `ops/README.md` “read this first” table; `ops/docs/README` pointer; exit **2** for `already_processed`; steve-live / ops-dispatch / post-dagh aligned; `work-targets.ini` + Help=64 notes. |
| 2026-06 | **Agent doc alignment pass** — ops migration paths, STATUS_PAGE_DATA `kooldb1`, AGENTS traps, `ladder-engine-plan` → archive; staging `staging-scripts/` confirmed gone local + remote. |
| 2026-06 | **Ops includes hygiene** — `day_close_milestones.php`, `league_milestones_sync.php` → `ops/includes/` (FinalizeUtcDay writers only). |
| 2026-06 | **Agent doc pass (tier 1–3)** — `kool-workspace.mdc`, contract, OPERATIONS, playbooks → ops simul vocabulary; `STAGING_REPLAY` archived; day-close surgical SQL → `sql/archive/one-off-2026-06/`. |
| 2026-06 | **Header realm switcher + cross-realm search** — `realm_switcher.php` (Online · Amiga 500 beside wordmark); header search `realm=all` with per-hit realm labels; `player-search.js` in `site_header.php`. |
| 2026-06 | **Doc hygiene + header cleanup** — long handoffs → `docs/archive/` stubs; session log archived; header realm switcher removed (markup/CSS; `realm-switch.js` tint-only); Activity v2 = local + staging; Deferred trimmed (no Amiga/realm backlog). |
| 2026-06 | **Batch rebuild SQL cleanup** — orphans deleted; `*_rebuild.sql` → `scripts/ladder/sql/archive/batch-2026-05/`; OPERATIONS_QUICK_START → ops simul first. |
| 2026-06 | **Ops vocabulary cleanup** — [`cutover-readiness.md`](docs/coordination/cutover-readiness.md); registers reframed; Phase 1.5 handoff retired. |
| 2026-06 | **Steve ops docs under `ops/docs/`** — `post-dagh-live-story.md` (prod copy → live), `steve-live-ops.md`, `ops-dispatch.md`; coordination stubs redirect. |
| 2026-06 | **Staging ops sign-off** — Steve `run_verify_ops_sim` on `kooldb1` (0 fail); Dagh visual parity staging simul vs frozen dev; **AUD-004/005** closed; milestone fixes `clean_sheet_spread`, `giant_slayer` (`a3cb1c0`). **Next:** Live dispatch + cron on staging. |
| 2026-06 | **Rating fade chapter closed** — active docs omit PER-001; tombstone only [`docs/archive/retired-product-decisions.md`](docs/archive/retired-product-decisions.md). |
| 2026-06 | **Ops verify process** — docs: `run_verify_ops_sim` = read-only SQL gate; short-run league FAIL expected; no batch-as-simul-DOD; debug=`stop-at`, Steve parity=74879 ([`docs/coordination/ops-simul-runbook.md`](docs/coordination/ops-simul-runbook.md) § Verify). |
| 2026-06 | **Dead surface pass** — removed `elolist.js`, `status-league-toggle.js`, realm-lab CSS + migration one-shots; `status-realm-lab.php` → 302 `status.php`; audit [`docs/DEAD_SURFACE.md`](docs/DEAD_SURFACE.md). |
| 2026-06 | **Post-game P6 milestones** — PHP incremental + Python oracle; period burst anchor = **crossing game** (5th/10th/…/50th); chrono calendar keys in live PHP with hydrate on `process-one`; `ab-post-game --phase p6` @ 100 games. |
| 2026-06 | **Post-game P0–P5 shipped** — PHP `run_process_game.php` per-game through period aggregates; `ab-post-game --phase p5`; Python rebuild `period_activity.py` + `period_aggregates.py`. |
| 2026-06 | **Post-game PHP reset** — reverted first attempt; new [`post-game-php-development.md`](docs/post-game-php-development.md) (per-game sim, `ratedresults` policy, `RecentAverageRating` retired). |
| 2026-06 | **Peak HoF read path** — removed live `ratedresults` fallback in `peak_month_leaderboard_query.php` (stored tables only). |
| 2026-06 | **Prepare in PHP** — `site/public_html/ops/run_prepare.php`; `prepare_local_work_db.ps1` calls PHP; Python `work_prepare` legacy. |
| 2026-06 | **Prepare v2 end-to-end** — SCH-015 KungFu drop (9+1), `seed-catalog` in orchestrator, parity **idA/idB/Date** vs baseline (UTC). |
| 2026-06 | **Full prepare v2 verified** on `ko2unity_work` — parity all PASS; §4.5 truncates on migrated work. |
| 2026-06 | **Prepare platform v2** — `scripts/work_prepare/`, `prepare_local_work_db.ps1`, `docs/OPS_STANDARDS.md`. |
| 2026-06 | **`docs/work-db-prepare.md`** — vocabulary + ZeroDerived contract signed off; aligned `database-copies`, ladder-ops §8. |
| 2026-06 | **`docs/ground-truth-manifest.md`** — scannable ground vs derived for prod five tables + local/staging roles. |
| 2026-06 | **Local dual website shipped** — `ratingskickoff.test` + `work.ratingskickoff.test`; docs in `LOCAL_DEV.md` + `database-copies-2026-06.md`. |
| 2026-06 | **Post-game doc alignment** — contract vs PHP ops vs C++-today in platform §2, contract, AGENTS, PROJECT_MAP. |
| 2026-06 | **Ops conventions (§6)** — naming, bootstrap guards, test-before-dispatch. |
| 2026-06 | **`staging-scripts/` removed** — May 2026 cutover PHP deleted; ladder ops = `site/public_html/ops/` only ([`archive/staging-scripts-inventory.md`](docs/archive/staging-scripts-inventory.md)). |
| 2026-06 | **Ladder ops platform** — [`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md) + `site/public_html/ops/` incl. `dispatch.php`, `run_process_game.php`, `run_prepare.php`. |
| 2026-06 | **Local prod sandbox live** — baseline + work from sanitized dump; ~75,204 rated on work. |
| 2026-06 | **Local DB model (3 DBs)** — scripts + **`database-copies-2026-06.md`**. |
| 2026-06 | **Profile feast + content v1** — production `player/profile.php`; playbook + v1 in [`player-profile-feast.md`](docs/player-profile-feast.md) / [`profile-build-playbook.md`](docs/profile-build-playbook.md); lab archived. |
| 2026-06 | **Activity charts v2 shipped** — `activity.php` uses `activity-charts-v2.js` + `server_activity_chart_panels.php`; plan [`activity-charts.md`](docs/activity-charts.md). |
| 2026-06 | **URL routes rename** — legacy `server*` / `ranked*` / `individual*` → semantic paths; `k2_routes.php`; [`url-routes.md`](docs/url-routes.md). |
| 2026-06 | **Amiga realm A1** — `ko2amiga_db` import from `koatd.mdb` (27,408 games, 473 players after name merges), Elo replay, rating/profile/games; **staging live** on `ratings.kickoff2.com`; DB config consolidated to `site/config/` — [`amiga-staging-handoff.md`](docs/amiga-staging-handoff.md). |
| 2026-06 | **Amiga A2 + staging import** — ground/derived schema split, `replay.py`, `amiga_db.php` read path; multi-part export/import (16 parts) verified on staging — [`amiga-data-contract.md`](docs/amiga-data-contract.md). |
| 2026-06 | **Amiga A2 audit fixes** — contract-order replay, dynamic export chunking, import/replay boundary docs; audit report [`audits/amiga-a2-restructure-audit-2026-06-06.md`](docs/audits/amiga-a2-restructure-audit-2026-06-06.md). |

---

## Deferred / blocked

- GitHub branch protection — when collaborators land.
- **Extensionless URLs** (`.htaccess` rewrites) — optional; filenames and folders done Jun 2026.
- **Status on prod live DB** + joshua redirect — [`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md).
- **Prod PHP ops cutover** — after prod copy proves live dispatch (Steve).

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub | https://github.com/DaghN/ratings.kickoff2 · branch `main` |
| Staging SFTP | `ratings.kickoff2.com:5322` · user `dagh@ratings.kickoff2.com` |
| Deploy | WinSCP **Synchronize** `site/public_html/` → remote `public_html/` |
| Legacy reference | https://joshua.kickoff2.net/ratings/ |
| Local site | `http://ratingskickoff.test` — **`docs/LOCAL_DEV.md`** |
| Staging DB | MariaDB 10.11 · **`kooldb1`** / **`kooldb2`** (legacy `kooldb` possible) · **no live game writes** on staging copies |
| Local DB | `ko2unity_db` · dump `data/dumps/` · replay `scripts/run_local_replay.ps1` |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| Cutover index | **`docs/coordination/cutover-readiness.md`** |
| `ratedresults` indexes | SCH-001 in ops `migrate-work` |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
