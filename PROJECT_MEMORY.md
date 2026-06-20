# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Ladder ops (Jun 2026):** PHP post-game **P0–P7** in `ops/run_process_game.php` + `dispatch.php`. **Staging simul signed off** on `kooldb1` (`run_verify_ops_sim` 0 fail). **Next (Steve):** live cutover when scheduled — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md). Discrepancies: [`post-game-contract-vs-oracle-discrepancies.md`](docs/coordination/post-game-contract-vs-oracle-discrepancies.md).

- **Milestones:** Catalog **112**; v0 UI + **`kooldb1` simul proof** done. Live writer = **PHP ops** at cutover (not C++).

- **Cutover prep (done):** Schema + PHP ops + **simul proven on `kooldb1`** — [`cutover-readiness.md`](docs/coordination/cutover-readiness.md). **Live prod execution** = Steve when scheduled (not repo backlog).

- **Activity wing (Leaderboards):** **Proven `kooldb1` (Jun 2026)** — SCH-022–025 ops + LB UI (Peaks · Participation · In a row); Steve full bootstrap + simul + verify **0 fail** (participation, play-streak HoF, reached_at oracle). Policy: [`activity-wing-stored-truth-policy.md`](docs/activity-wing-stored-truth-policy.md). **HoF:** month/year play-streak rows + participation block shipped.

- **Result streaks (Streaks LB):** **Shipped Jun 2026** — SCH-026 `player_result_streaks` + post-game writer + verify; LB tooltips/click-through + player-games streak banner. Work smoke PASS; **`kooldb1` proof** when Steve syncs migration `026` + re-simul.

- **Leagues:** **Honours proven `kooldb1`** (`leaderboards/league-honours.php`). Live = `FinalizeUtcDay` when wired.

- **Status Leagues:** **Shipped** — [`status-period-competitions.md`](docs/status-period-competitions.md).

- **Profile:** Feast shipped on **`player/profile.php`** — gradual improvements only; live spec [`player-profile-feast.md`](docs/player-profile-feast.md). Multi-agent lab sandboxes removed Jun 2026 (handoff archived).

- **Design / Status hub:** Phase B v1.2 room grid shipped. Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](docs/activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones · **Games** · HoF · Play & Setup — [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). **Games hub (Jun 2026):** `games/recent.php` + Highlights + **All games** vault (filters, server sort). **URLs:** semantic paths + `games/` + `milestones/` folders; registry [`k2_routes.php`](site/public_html/includes/k2_routes.php) — [`url-routes.md`](docs/url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Operational loop:** edit locally → **WinSCP** sync `site/public_html/` → staging; hard refresh. Steve runs server one-offs.

- **Local:** `ratingskickoff.test` (dev) + `work.ratingskickoff.test` (work) — [`LOCAL_DEV.md`](docs/LOCAL_DEV.md).

- **Change style:** small, reversible slices.

- **Amiga realm (Jun 2026):** **Disposition review** — register **605/605**; **38** `pending_review` (promoted through **284**; **187** deferred split); [`disposition-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

- **Amiga rating history (Jun 2026):** **V1 + animation** — History hub + News races (by tournament + by time); [`amiga-rating-history-policy.md`](docs/amiga-rating-history-policy.md).

- **Amiga event snapshots (Jun 2026):** **Slices 0–7 done** — present=`current`, history/race=snapshots, holy loop `prove`. Policy [`amiga-event-snapshot-policy.md`](docs/amiga-event-snapshot-policy.md). **Next:** slice 8 retire legacy tables.

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

1. **Profile** — gradual improvements on production feast — [`player-profile-feast.md`](docs/player-profile-feast.md) · [`profile-build-playbook.md`](docs/profile-build-playbook.md).

**Steve (when ready)**

2. **Prod copy → live PHP ops** — migrate / seed / zero / simul / dispatch — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md); WinSCP `public_html/ops/`.

**Migration habit (not a numbered task):** stored-truth changes → [`UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) Part B + [`prod-coordination.md`](docs/prod-coordination.md) registers.

---

## Recent log

*(Newest first. ~30 rows max. Older rows: [`docs/archive/session-log-2026-q2.md`](docs/archive/session-log-2026-q2.md).)*

| When | What |
|------|------|
| 2026-06 | **Amiga event snapshots slice 7** — `amiga_rating_history_lib.php` historical ladder + top-10 race from `amiga_player_event_snapshots`; event-wing top-10 = `amiga_player_current` parity. |
| 2026-06 | **Amiga holy loop (`prove`)** — nuclear-only path; `prove` = recreate + replay + verify. |
| 2026-06 | **Amiga event snapshots — ops hygiene** — finalize loads stats + events + prior snapshots; `current` website-only. |
| 2026-06 | **Amiga event snapshots slice 6** — `amiga_player_current_lib.php`; PHP reads (profile, LBs, HoF ratios, search API) switch to `amiga_player_current`. |
| 2026-06 | **Amiga event snapshots slice 5** — `verify-event-snapshots` CLI; row counts, current=latest, event-local rollup, rating_events parity, honours, career games; 0 errors on `ko2amiga_db`. |
| 2026-06 | **Amiga event snapshots slice 4** — `rebuild_event_snapshots.py` + CLI; replay/refinalize wire; backfill `4535` snapshots / `473` current on `ko2amiga_db`. |
| 2026-06 | **Profile goals-per-game hint** — “How many games he scored exactly 0, 1, 2… goals in.” + “{name} has averaged X goals per game so far.” after histogram load. |
| 2026-06 | **Amiga rating history Δ** — ladder debut (incl. first wing snapshot) always vs **1600**, not em dash. |
| 2026-06 | **Table hygiene (calm-stats)** — `calm-stats` defaults to secondary body ink; `k2_table_helpers.php` SSR anchor/sort classes; Amiga LBs + History use `ranked-pages-table ranked-table-pending` (online LB parity, fixes Elo FOUC). |
| 2026-06 | **Amiga Elo race by time** — News second chart; calendar playhead, straight segments between rating events (`amiga-top10-rating-race-by-time.js`). |
| 2026-06 | **Amiga top-10 Elo line race** — `/amiga/news.php` + API; by-tournament + by-time variants. |
| 2026-06 | **Amiga profile rating chart (by tournament #)** — origin point at tournament #0 / 1600 Elo (parity with online game #0). |
| 2026-06 | **Amiga profile rating chart (by date)** — x-axis now uses API `timelineStart` (~Nov 2001), not online June 2017 origin; `chart-date-range.js` + `player-rating-chart.js`. |
| 2026-06 | **Player hero avatar link** — avatar → Profile (same href as name); accent ring unchanged. |
| 2026-06 | **Player hero stat links** — rank/rating/games/milestones → `#k2-lb-table` zero-height anchor flush above LB table (table top at viewport); milestones hero count only; garden on Milestones tab. |
| 2026-06 | **Activity games/year tooltip** — hover shows ~games/day on average (YTD days for current year; full calendar year for past years). |
| 2026-06 | **HoF query trim** — `hall-of-fame.php` no longer SELECTs eight unused `*GameID` columns from `generalstatstable`; draw row guard uses `BiggestDrawSum`. `RECORDS_PAGE_DATA.md` updated. |
| 2026-06 | **Goals LB Draw column** — hub `leaderboards/goals.php` + Amiga `amiga/leaderboards/goals.php`: **Draw** → **Max draw** (Opponents Goals stays **Draw** — width). |
| 2026-06 | **Activity In a row drill-down rejected** — not deferred; peaks → Games only. Policy + retired-product-decisions updated; deferred mentions removed. |
| 2026-06 | **Status Leagues closed** — Phase 1.5 / editorial polish removed from backlog; `status-period-competitions-wip.md` archived; MEMORY Next + cutover-readiness cleaned. |
| 2026-06 | **A2 DB error leak (sweep)** — games hub (`recent`/`all`/`highlights`), `k2_realm_games_all.php`, `hall-of-fame.php`, `server_activity_summary.php` → `k2_db_connect_or_public_error` / `k2_query_or_public_error` / `k2_public_error`. |
| 2026-06 | **A2 DB error leak** — `game.php` + `player/games.php` use `k2_db_connect_or_public_error` / `k2_public_error` (profile already had connect); prepared-statement failures log + generic visitor message. |
| 2026-06 | **game.php hub nav + scroll anchor** — `hub_nav.php` (no active tab); `#k2-game` anchor + `k2_game_page_url()`; all in-site game links updated; bare URLs auto-scroll to table when game exists. |
| 2026-06 | **Profile + Games invalid player id** — `player/profile.php` + `player/games.php` use `k2_positive_int_param` / `k2_public_error` (400 invalid id, 404 missing player); no more blank page on stale bookmarks. |
| 2026-06 | **player-profile-feast.md drift** — Opponents IA + Milestones Chronology marked shipped (were still “not shipped / placeholder”); rivalry placeholder card mention removed; sibling-tabs table aligned with Opponents pill. |
| 2026-06 | **Online dead-surface slice** — removed Activity v1-era includes (`peak_period_leaderboards_section`, `period_activity_leaderboards_section`), unused `player_wing_up_link.php`, orphan `activity-mode-toggle.js`; dead CSS `.pm3-rivalry-teaser*`, `.k2-status-bridge*`. Activity `api/server_*.php` unchanged (v2 + Status Leagues). |
| 2026-06 | **Orchestration archive co-move** — 105 handoffs + 27 prompt/checkpoint files → `docs/archive/orchestration/`; live disposition + import-split starters kept in `docs/orchestration/agent-handoffs/`. |
| 2026-06 | **Player games pagination chevrons** — removed `title` hover tooltips on page prev/next steps (`player/games.php`, `games/all.php`); `aria-label` kept for screen readers. |
| 2026-06 | **Doc + script hygiene** — MEMORY Recent log trimmed to 30 rows; `oneoff/` inventory + register buckets; staging config note clarified; opponents-hub + status-period-competitions spec drift fixed. |
| 2026-06 | **Profile bursts week card** — busiest week (P04) added to bursts row; day · week · month · year; links to Games week filter. |
| 2026-06 | **Profile story order** — longest play-streak run line now follows distinct-days beat (was second in list). |
| 2026-06 | **Profile lab cleanup** — removed `individual1-profile-lab1–4.php`, `player_feast_*_lab*`, `player-feast-lab*.css` (16 files); production feast unchanged. |
| 2026-06 | **Player Milestones Chronology** — reuses hub Recent feed UI; tier filter; newest-first; no player column / no list heading. |
| 2026-06 | **Games Highlights tab order** — Most goals → Biggest draws → Biggest wins → Top score (stepchild last). |
| 2026-06 | **Profile At a glance milestone tooltips** — tier counts link to garden tier anchors; coarse two-tap (preview tooltip → navigate). |
| 2026-06 | **Profile career + opponents charts** — center 960px chart stack in page column (`player-feast-sections.css`). |
| 2026-06 | **Profile glance CSS cache-bust** — `player-feast-glance.css` now uses `?v=filemtime` on `profile.php` + lab pages (parity with other feast stylesheets; fixes stale at-a-glance layout/colours on staging). |
| 2026-06 | **Profile top-opponents x-axis** — `max` = top opponent games (no nice-number headroom; `grace: 0`). |
| 2026-06 | **Profile opponents finale lede** — “plenty is still to come!” + “Let's not forget… we picked up along the way.” |
| 2026-06 | **Profile games/month x-axis** — sparse ticks (max 12, match rating-by-date); no forced month unit. |
| 2026-06 | **Profile games/month hint** — “{name}'s monthly activity on the server timeline…” (link-star possessive). |
| 2026-06 | **Profile goals histogram hint** — “How many games {name} scored … goals in.” (link-star; no click line). |
| 2026-06 | **Profile opponents finale** — closing lede + top-20 bar chart (uniform H2H red, no #1 highlight); rivalry placeholder removed; profile ends on chart. |
| 2026-06 | **Profile games/month back link** — Games tab returns to `#games-per-month` (**← Games per month**), not whole charts section. |
| 2026-06 | **Profile games/month chart drill-down** — click month bar → Games tab `?from=profile-games-chart&period=month&anchor=`; x-axis hit testing for thin time-scale bars; hint + tooltip copy. |
| 2026-06 | **Profile + H2H coarse tap UX** — shared `k2-coarse-tap.js`: phone first tap = pinned preview + “Tap again…”; second tap = navigate/filter (played days/weeks, games/month chart, goals histograms, top opponents, H2H total-goals). |
| 2026-06 | **Profile played-weeks hint** — weeks prose ends with `...` (narrative ellipsis, parity with days line). |
| 2026-06 | **Profile charts lede copy** — “the charts below” (dropped “rating” before charts). |
| 2026-06 | **Profile played-days month gaps** — inter-month gap ≈ **1.3×** day-cell width (`×1.3` on `100cqi / (8×cols − 1)`). |
| 2026-06 | **Profile played-days heatmap gaps** — cell + month grid gaps scale with column width (`cqi`, ~30% cell ratio like weeks map). |
| 2026-06 | **Profile played-days heatmap** — page-width cap (1200px); fluid day cells via 7-col `1fr` grid; 12/6/4 month wrap unchanged (`player-feast-sections.css`). |
| 2026-06 | **Profile played-weeks spacing** — intro prose → heatmap gap 16px → 32px (`player-feast-sections.css`). |
| 2026-06 | **Profile played-weeks heatmap** — centered year rows in `#played-weeks` (`player-feast-sections.css`). |
| 2026-06 | **Profile At a glance mobile** — dropped column stack on narrow viewports; three columns stay side-by-side with horizontal scroll when needed (`player-feast-glance.css`). |
| 2026-06 | **Profile career chart alignment (B+C)** — `profileCareerTimeRange()` (Jun 2017 month → month-end); rating by date axis only; `offset: false` on month bars. |
| 2026-06 | **Profile career chart gutters (slice A)** — shared 48px y-axis + 12px right padding via `chart-theme.js` (rating, games/month, goals). |
| 2026-06 | **Profile charts lede** — warm prose before rating/month/goals panels; he/him; sr-only Career rating title. |
| 2026-06 | **Profile bonanza moment logic** — 3× ratio gate on primary sum game; global highest-`SumOfGoals` fallback where ratio passes (replaced H2H win vs same opponent). |
| 2026-06 | **Profile heatmap section rhythm** — padding breaks (no margin collapse): story→days 24px; days→weeks ~52px; weeks→bursts ~32px. |

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
| Staging DB | MariaDB 10.11 · **`kooldb1`** / **`kooldb2`** via `config1`/`config2` · legacy **`kooldb`** frozen · **no live game writes** on staging copies |
| Local DB | `ko2unity_db` · dump `data/dumps/` · replay `scripts/run_local_replay.ps1` |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| Cutover index | **`docs/coordination/cutover-readiness.md`** |
| `ratedresults` indexes | SCH-001 in ops `migrate-work` |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
