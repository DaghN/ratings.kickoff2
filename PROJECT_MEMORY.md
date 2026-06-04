# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Milestones project:** **Staging DB done** May 2026. Catalog **112**. **Post-game target:** PHP ops (`run_process_game.php`) replaces prod C++ derived writer at cutover — [`ladder-ops-platform.md`](docs/ladder-ops-platform.md) §2, [`post-game-register.md`](docs/coordination/post-game-register.md). Discrepancy register: [`post-game-contract-vs-oracle-discrepancies.md`](docs/coordination/post-game-contract-vs-oracle-discrepancies.md). **`club_*`:** regen rebuild SQL after peak-at-20 replay.

- **Rated play streaks:** **Staging DB + UI done** May 2026 (SCH-014, REP-015; `ranked4` Days/Weeks, HoF `server2`). **Next:** prod schema + C++ post-game; profile surface TBD.

- **Leagues integration:** Awards DB + **League honours v1** on **local + staging** (`ranked9.php`, SCH-009/010, REP-012/013 verified May 2026). **Next:** profile league block; prod schema/REP when cutover; daily finalize (PER-003) only if/when wanted.
- **Status Leagues Phase 1:** shipped in repo (nav, single table, prewarm, lock-step floor). **Daily tab:** games-this-day list under league tables (Jun 2026). **Phase 1.5** optional polish — [`docs/status-period-competitions-wip.md`](docs/status-period-competitions-wip.md).
- **Design / cosmetics track:** Phase A hub shell + Status Phase B v1.2 room grid; `docs/STATUS_PAGE_DATA.md`. Steve for prod DB read + joshua redirect. **Realm switcher** markup kept in header; **hidden in CSS** until Amiga ships.

- **Activity charts v2:** **shipped on `server1.php`** — [`activity-charts-v2.js`](site/public_html/js/activity-charts-v2.js) + [`server_activity_chart_panels.php`](site/public_html/includes/server_activity_chart_panels.php); legacy boot files removed. **Bar grow-up** on phone + desktop via `chart-theme.js` (Jun 2026). Plan: [`docs/activity-charts.md`](docs/activity-charts.md). **Next (optional L4):** lazy load, phone long-press tooltips.
- **Charts:** **six-colour palette signed** (May 2026) — canonical tokens in `theme.css` + `chart-theme.js`. Profile uses pitch/chrome or `profileCompare*` helpers.

- **DB performance (May 2026):** Profile load fixed mainly via **`idx_ratedresults_idA` / `idx_ratedresults_idB`** — local + staging; **production still pending** (Steve when agreed). Heavy profiles ~**8s → ~1s** locally. **Status page local + staging DB fixed** via `idx_ratedresults_date`, `idx_resulttable_live_status`, and **`player_period_league`** (legacy `player_monthly_league` dropped SCH-017 Jun 2026). **`server1.php` shell is fast locally** (~40–120ms HTML); remaining Activity cost is async chart APIs (~2.8s concurrent / ~7s sequential), mainly established-player and active-player aggregates.

- **Profile feast (shipped):** production **`individual1.php`** only — **`docs/player-profile-feast.md`**. Further work = gradual copy/UX, not mock lab (`docs/archive/`).

- **Hub IA (May 2026):** Status · Activity · Leaderboards · **Milestones** · HoF — **Games** off hub (`server3.php` via Status). See [`docs/hub-ia-agreement.md`](docs/hub-ia-agreement.md).
- **Product tone:** ladder stays **truthful and data-rich**; surface **inclusive, playful, welcoming**. Profile above-the-fold = participation first; deep analytics lower (“matchup lab”).

- **Operational loop:** edit locally/Git → **WinSCP** sync `site/public_html/` → staging `public_html/`; hard refresh after assets. **SSH:** permission denied for Dagh (May 2026) — Steve runs one-offs when sent.

- **Ladder replay (Python):** P0–P5 on work sandbox (`scripts/ladder/` + `ab-post-game`); local `ko2unity_db` + staging replay history in **`docs/STAGING_REPLAY.md`**. Sandbox: `--target sandbox` on **`ko2unity_work`**. **Prod live post-game still C++** until PHP ops cutover (target: **`ladder-ops-platform.md`**).

- **`ratedresults` only** for ladder/replay (~74.9k rated rows). **`resulttable`** is wider match log — external JSON on `GameID` can differ slightly; expected.

- **Ladder ops platform (Jun 2026):** Post-game **P0–P6** in `ops/run_process_game.php`. Parity: **`ab-post-game --phase p6 --limit N`** (layer 6 milestones). **100-game parity OK** Jun 2026; **1000-game timing** ~849s PHP replay vs ~140s Python oracle (work DB).
- **Local dual website (Jun 2026, live):** **`ratingskickoff.test`** → dev DB · **`work.ratingskickoff.test`** → work DB (router + `setup_laragon_work_site.ps1`); **not** config-file cutover. Leaderboards on work URL verified vs prod snapshot. **`database-copies-2026-06.md`** § Local dual website · **`LOCAL_DEV.md`**.

- **Change style:** small, reversible slices.

---

## Deep reference (read on demand)

| Topic | Where |
|--------|--------|
| Live post-game C++ (prod today) | `docs/ratings_cpp.txt` |
| Ladder ops / PHP post-game (target) | `docs/ladder-ops-platform.md` §2 · build guide `docs/post-game-php-development.md` |
| Per-game table | `docs/ratedresults-schema.md` |
| Replay / Elo sandbox | `scripts/ladder/`, `docs/replay-v1-scope-and-reset.md` |
| Profile layout / charts | `docs/player-profile-feast.md` |
| Activity charts (plan + registry) | `docs/activity-charts.md` |
| Status hub spec | `docs/STATUS_PAGE_DATA.md` |
| Staging schema / replay status | `docs/coordination/schema-register.md`, `docs/coordination/replay-register.md` |
| `player_milestones` row-count timeline (151 → 6658 → **6615**) | `docs/coordination/replay-register.md` § Milestone unlock row counts |
| Prod cutover | `docs/prod-coordination.md`, `docs/coordination/` |
| Ladder ops platform (Steve, `ops/`, sim) | `docs/ladder-ops-platform.md` |
| DB copies (local + staging names) | `docs/coordination/database-copies-2026-06.md` |
| Work DB prepare / simul | `docs/work-db-prepare.md` |
| Ground vs derived columns | `docs/replay-v1-scope-and-reset.md`, `docs/ground-truth-manifest.md` |

---

## Next (prioritised intent)

1. **Deploy Activity v2** — WinSCP sync `server1.php`, `activity-charts-v2.js`, `server_activity_chart_panels.php`, `theme.css`, `chart-theme.js`; hard refresh.
2. **Deploy cosmetics slice** — WinSCP sync `site/public_html/` → staging; hard refresh hub, ranked, server, **status** pages.
3. **Status on prod data** — Steve: prod DB read for live panels; joshua redirect when agreed (`docs/STATUS_PAGE_DATA.md`).
4. **Launch polish** — unhide realm switcher when Amiga realm ships (`theme.css` + `site_header.php`).
5. **Profile gradual improvements** — `docs/player-profile-feast.md`; archived planning in `docs/archive/`.
6. **Work DB prepare v2** — [`scripts/work_prepare/`](scripts/work_prepare/), `prepare_local_work_db.ps1`; legacy scripts kept for parity. **Next:** parity smoke vs legacy, then retire old entry points; **`ProcessCompletedGame`** before `dispatch.php`.
7. **Prod coordination** — when stored truth changes: `docs/prod-coordination.md`, registers. **Staging:** SCH-008 + REP-007–011 **done** May 2026; prod cutover + contract post-game still pending Steve.

---

## Recent log

*(Newest first. Keep this to the latest high-signal handoff rows; older phase detail lives in feature docs / git.)*

| When | What |
|------|------|
| 2026-06 | **Rating fade chapter closed** — active docs omit PER-001; tombstone only [`docs/archive/retired-product-decisions.md`](docs/archive/retired-product-decisions.md). |
| 2026-06 | **Ops verify process** — docs: `run_verify_ops_sim` = read-only SQL gate; short-run league FAIL expected; no batch-as-simul-DOD; debug=`stop-at`, Steve parity=74879 ([`docs/coordination/ops-simul-runbook.md`](docs/coordination/ops-simul-runbook.md) § Verify). |
| 2026-06 | **Dead surface pass** — removed `elolist.js`, `status-league-toggle.js`, realm-lab CSS + migration one-shots; `status-realm-lab.php` → 302 `status.php`; audit [`docs/DEAD_SURFACE.md`](docs/DEAD_SURFACE.md). |
| 2026-06 | **Post-game P6 milestones** — PHP incremental + Python oracle; period burst anchor = **crossing game** (5th/10th/…/50th); chrono calendar keys (`daily_habit`, `weekly_regular`, `monthly_regular`, `year_round`, `rare_blank`) in live PHP with hydrate on `process-one`; `ab-post-game --phase p6` @ 100 games. |
| 2026-06 | **Post-game P0–P5 shipped** — PHP `run_process_game.php` per-game through period aggregates; `ab-post-game --phase p5`; Python rebuild `period_activity.py` + `period_aggregates.py`. |
| 2026-06 | **Post-game PHP reset** — reverted first attempt (playbook+P1–P2); new [`post-game-php-development.md`](docs/post-game-php-development.md) (per-game sim, `ratedresults` policy, `RecentAverageRating` retired). |
| 2026-06 | **Peak HoF read path** — removed live `ratedresults` fallback in `peak_month_leaderboard_query.php` (stored tables only; fixes slow server2 + post-prepare false peaks). |
| 2026-06 | **Prepare in PHP** — `site/public_html/ops/run_prepare.php` (no `dispatch.php`); `prepare_local_work_db.ps1` calls PHP; Python `work_prepare` legacy. |
| 2026-06 | **Prepare v2 end-to-end** — SCH-015 KungFu drop (9+1), `seed-catalog` in orchestrator, parity **idA/idB/Date** vs baseline (UTC); `apply_local.ps1` pins `time_zone=+00:00` (fixes 16 DST `Date` drifts on index build). |
| 2026-06 | **Full prepare v2 verified** on `ko2unity_work` — parity all PASS; §4.5 truncates on migrated work; fixed `schema/apply_local.ps1` Unicode em-dash breaking PowerShell migrate. |
| 2026-06 | **Prepare platform v2** — `scripts/work_prepare/`, `prepare_local_work_db.ps1`, §4.5 truncates, `refresh_local_work_db.ps1`, `docs/OPS_STANDARDS.md`. |
| 2026-06 | **`docs/work-db-prepare.md`** §4 signed off (ZeroDerived contract). |
| 2026-06 | **`docs/work-db-prepare.md`** — vocabulary (refresh / migrate / zero derived / simul modes A–C), prepare order; aligned `database-copies`, OPERATIONS, ladder-ops §8. |
| 2026-06 | **`docs/ground-truth-manifest.md`** — scannable ground vs derived for prod five tables + local/staging roles; KungFu + ratio HoF columns = delete targets; `Display`/`PlayerRank` = not Dagh. |
| 2026-06 | **Local dual website shipped** — two URLs in parallel (rejected config `$database` flip); work leaderboards smoke-tested; docs in `LOCAL_DEV.md` + `database-copies-2026-06.md`. |
| 2026-06 | **Post-game doc alignment** — contract vs PHP ops vs C++-today called out in platform §2, contract, AGENTS, OPERATIONS, PROJECT_MAP. |
| 2026-06 | **Ops conventions (§6)** — naming, bootstrap guards, `staging-scripts/` vs `ops/`, test-before-dispatch; docs only. |
| 2026-06 | **Ladder ops springboard** — [`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md) + `ops/` scaffold; no dispatcher PHP in repo. |
| 2026-06 | **Local prod sandbox live** — baseline + work from sanitized dump; dev untouched; sanitize fix in `ProdDumpSanitize.ps1`; verify ~75,204 rated on work. |
| 2026-06 | **Local DB model (3 DBs)** — scripts + **`database-copies-2026-06.md`**; ground/derived + `game_id` API direction to Steve. |
| 2026-06 | **Games Highlights table ink** — `k2-games-highlights-table` now gets the same `k2-table--calm-stats` secondary body text as leaderboards/Recent (`theme.css`). |
| 2026-06 | **Profile lab Agents 4–5 prompts** — multi-model compare; same v1 brief as 1–2 with mandatory B1/B2 layout rethink (`profile-lab-agent-handoff.md`). |
| 2026-06 | **Profile lab B1/B2 freedom** — docs: Presence/Career duo tables are production reference only; lab agents should rethink layout (same v1 facts). |
| 2026-06 | **Profile lab agent handoff** — `docs/profile-lab-agent-handoff.md`: multi-agent prompts, `individual1-profile-lab{N}.php` isolation; production untouched until merge. |
| 2026-06 | **Profile build playbook** — `docs/profile-build-playbook.md`: placement charter (B1–B5 + C), module recipes, waves, acceptance checks; links v1 curated list. |
| 2026-06 | **Profile content v1 curated** — Dagh pass on `profile-content-candidates.md`: ship play streak, P05 distinct days (site-wide), milestone/league snippets, M03/M08/M09, rivalry/Games deep links; defer A03/A07/A08; reject extra charts. |
| 2026-06 | **Profile content catalog** — `docs/profile-content-candidates.md`: ~70 candidates (P0–P3) across identity, presence, moments, milestones, league, heatmaps, charts; for Dagh curation. |
| 2026-06 | **Profile graph polish (2)** — played-days year picker ascending; peak label flips below line when near chart top; selected opponent bar gets green border; heatmap legend swatches matched size. |
| 2026-06 | **Profile graph restoration** — `individual1.php` charts use Activity-style full-width `k2-chart-frame`s; rating peak dashed line restored; graph time axes start at server origin (2017-06-09); rating comparison gets date/games-played toggle; top opponents remains the matchup gateway; career played-days heatmap; winrate-vs-Elo API/JS removed. |
| 2026-06 | **Rating LB Elo tooltip** — `ranked1.php` + `ranked7.php` ELO rating headers now explain standard Elo, fixed K=32, and point users to game-page expected-score/adjustment tooltips. |
| 2026-06 | **Activity section intros** — `server1.php` chart stack grouped into five question-led sections with tightened one-line copy; original header styling kept. |
| 2026-06 | **Secondary control polish** — Player Games Reset/Previous/Next are quiet action pills; Status Leagues period stepper gets a grouped surface while keeping period tabs separate. |
| 2026-06 | **`game.php` interim video** — below rated-game table when found: replay-waiting copy + 16:9 YouTube embed (2024 Online WC final) until browser replay ships. |
| 2026-06 | **Status leagues period menu** — Day/Week/Month/Year selector now uses the shared `k2-chrome-tabs` segment track with compact milestone-style density. |
| 2026-06 | **Wordmark bloom softened** — Kick Off 2 neon keeps its street-sign feel with reduced outer haze and hover flare. |
| 2026-06 | **Status heritage glow** — right-side KO2 box art gets a clipped warm tint halo/rays inside the inset, balancing the wordmark glow. |
| 2026-06 | **Status heritage inset** — dark well + muted art; tint backlight removed for fresh pass. |
| 2026-06 | **Self-hosted fonts** — Google Fonts removed; `fonts/*.woff2` + `k2-fonts.css` + preload in `k2_fonts_head.php`; audit `docs/self-hosted-assets.md`; regen `scripts/sync_self_hosted_fonts.ps1`. |
| 2026-06 | **Player hero links** — name → Profile; rank/rating → `ranked7.php`; games → Activity peaks all-time (`ranked8.php#k2-peak-period-all-time`); neutral pointer-only stat/name links. |
| 2026-06 | **Player DDs tab (`individual2c`)** — hub `ranked3` headers/tooltips + column order; calm-stats; Games anchor. |
| 2026-06 | **Player Goals tab (`individual2b`)** — Win/Loss margin SQL fixed (CASE on outcome, not MAX of signed diffs); hub LB headers; Games anchor; Draw/Least display fixes. |
| 2026-06 | **Player Games polish (`individual3.php`)** — calm-stats table ink, shared `k2-archive-listbox` filters (Status Leagues parity), default sort id desc, server-side `k2-table-col-sorted`; win/loss blue/red kept. |
| 2026-06 | **Status Leagues — Daily games list** — under Activity + Points when **Daily** tab active; recent-games layout + `game.php` id link; `k2_status_rated_games_for_calendar_day` + `api/status_period_day_games.php`. |
| 2026-06 | **Activity bar animation** — **off** (`ACTIVITY_BAR_ENTRANCE_ENABLED` in `chart-theme.js`); grow-up WIP (stutter). |
| 2026-06 | **Activity heatmap months** — month row uses `grid-column: span N` per month (full “Jan”, not ellipsis in one week column). |
| 2026-06 | **Activity heatmap layout** — cells scale to panel width (`ResizeObserver` + CSS vars); taller/wider on desktop; min 8px + horizontal scroll on narrow viewports. |
| 2026-06 | **Activity bar animation on phone** — same grow-up as desktop (~420ms); scroll policy unchanged (no tooltips / no touchstart). |
| 2026-06 | **Activity bar animation fix** — grow from y-axis baseline (`getPixelForValue`), not canvas top; stacked year chart uses stack foot. |
| 2026-06 | **Activity charts L4 (partial)** — desktop bar grow-up (`chartKind: 'bar'` in v2); lines unchanged; phone still no animation. |
| 2026-06 | **Activity busiest-day card** — summary stat order: Rated games (label) → count → Busiest day · date (note). |
| 2026-06 | **Activity highlights panel width** — `.server-activity-summary` uses `--k2-max-width` (1200px), not chart 960px cap. |
| 2026-06 | **Activity charts v2 L3** — `server1.php` ships v2 only; legacy 12 JS files deleted; `server1-charts-lab.php` → redirect; `body.k2-activity-charts` + `server_activity_chart_panels.php`. |
| 2026-06 | **Activity charts phone touch** — coarse: panel + canvas `touch-action: pan-y pinch-zoom` (scroll + pinch; `pan-y` alone blocked zoom); Chart.js tooltips off on phone; heatmap tooltips desktop-only. |
| 2026-06 | **Chart `T.amber()`** — returns resolved `amberSoft()` rgb so Chart.js never gets unresolved `color-mix` vars (fixed black line on top-10 chart for LORENZOL). |
| 2026-06 | **Daily active players chart** — calendar 30-day rolling mean (gap days = 0); explicit smooth line in v2 + legacy `server-daily-active-players-chart.js`. |
| 2026-06 | **Activity charts v2 L2** — all 12 panels in `activity-charts-v2.js`; `server_activity_chart_panels_lab.php`; lab loads `chart-date-range.js`; production unchanged (legacy). |
| 2026-06 | **Milestones Recent density** — feed typography tuned to **12px / 1.4**, tighter **7px** row padding, and narrower date column (`player-milestones.css`). |
| 2026-06 | **Revert site-wide mobile experiments** — removed viewport meta, hub scroll CSS, canvas stretch; lab CSS scoped to `body.k2-activity-charts-lab` only; `server1.php` canvases 960×271 restored. |
| 2026-06 | **Activity charts lab UX** — tap link `server1.php` ↔ lab; no lab banner; lab summary full column width, charts still 960px frame. |
| 2026-06 | **Activity charts v2 L1 lab** — `server1-charts-lab.php` + `activity-charts-v2.js` (games/day); `.k2-chart-frame` CSS; summary → `includes/server_activity_summary.php`; removed canvas `%` stretch rules. |
| 2026-06 | **Activity charts v2 plan** — [`docs/activity-charts.md`](docs/activity-charts.md): single module, lab → promote, panel registry + parity checklist. |
| 2026-06 | **Activity charts fix** — `activity-charts.js` boot only on `DOMContentLoaded` (first chart was skipped when boot ran before defer modules registered). Mobile: viewport meta in `k2_head.php`, chart `touch-action: pan-y`, fluid canvas width (removed 960px attrs), header/hub `min-width: 0`. |
| 2026-06 | **Activity charts rewrite** — `chart-theme.js` slim (colours + tooltips + `activityChartOptions` only; no global `Chart.defaults` / touch plugin / `createBarChart`). `activity-charts.js` loads panels **sequentially** (~100ms gap). All Activity modules register with `K2ActivityCharts`; heatmap + play texture + busiest players **re-enabled** on `server1.php`. Mobile: `animation: false`; busiest chart skips hover highlight on coarse pointers. |
| 2026-06 | **Fix** — restored missing `prefersReducedMotion()` in `chart-theme.js` (broke all Activity charts with “Could not load…”). |
| 2026-06 | **Activity chart interaction** — desktop bar grow restored even when browser reports reduced motion; mobile chart touch is tap-based (`touchstart`/`click`, no `touchmove`) + `touch-action: manipulation`. |
| 2026-06 | **Cumulative established tooltip** — body line only: `Total established: N` (removed afterLabel explainer). |
| 2026-06 | **Activity charts** — `createBarChart` (y=0 then update); `k2TouchPointer` plugin; no viewport IO. |
| 2026-06 | **Chart tooltips** — `T.mergeTooltip()` + dark `--k2-tooltip-surface`; swatch boxes use `multiKeyBackground` + solid `labelColor` (no white inside); heatmap uses `.k2-table-tooltip` DOM. |
| 2026-06 | **Activity summary** — **Busiest day** stat (PHP, `server_period_game_totals`); removed Recent milestones panel + digest API/JS. |
| 2026-06 | **Most games played chart** — hover highlight fix (`dataset` mode + opacity dim; tooltip still index-by-month). |
| 2026-06 | **Most games played chart** — trailing 6-month rolling average; fixed top 10 by `NumberGames` (tie → lowest ID). |
| 2026-06 | **Activity layout** — All-time busiest players + Play texture are last on `server1.php` (after established rating distribution). |
| 2026-06 | **Activity tab** — removed **Goals per month** chart + `server_goals_by_month.php` / `server-goals-month-chart.js`. |
| 2026-06 | **Activity layout** — daily activity heatmap is chart **#4** on `server1.php` (after games day / month / year). |
| 2026-06 | **`milestone.php` Graphs** — rating distribution charts removed for DD Merchant + Established (year + cumulative only); established rating distribution stays on `server1.php` Activity. |
| 2026-06 | **Activity cleanup** — deleted APIs/JS for participation depth + all three DD merchant Activity charts. |
| 2026-06 | **Activity tab** — dropped **Participation depth by month** chart from `server1.php` (redundant vs active players / games per month). |
| 2026-06 | **Activity copy** — unique matchups chart hint: “social breadth of the community” (`server1.php`). |
| 2026-06 | **Activity tab** — removed three Double Digit Merchant charts from `server1.php` (new/cumulative per year, rating distribution); established-player charts + milestone digest unchanged on Activity. |
| 2026-06 | **Status rated-games arc** — removed **Activity →** link from arc panel (`status_room_section.php`); Activity remains hub tab on `server1.php`. |
| 2026-06 | **Activity peaks sub-nav** — `ranked8.php` Calendar / All time uses **`k2-chrome-tabs`** segment track; removed duplicate top margin (was wing gap + mode bar margin). |
| 2026-06 | **League honours sub-nav** — `ranked9.php` cup/grain filters use **`k2-chrome-tabs`** segment track (matches Activity peaks / milestones / games highlights), not Status leagues period pills. |
| 2026-06 | **`milestone.php` achiever match lines** — Event scoreline uses official **team A · GoalsA–GoalsB · team B** (`ratedresults` order), not unlocker-first. |
| 2026-06 | **`server3.php` games tables** — calm-stats body ink; Recent default sort ID desc; empty-day row stays secondary (`k2-table.js` skips colspan sorted-col). |
| 2026-05 | **`daily_habit` rule copy** — Rule (short): “Rated game every day Monday to Sunday” (copy patch + local `milestone_definitions`). |
| 2026-06 | **`game.php`** — leaderboard **`k2-table--calm-stats`** body ink only; hub tabs removed (detail page stays header + table). |
| 2026-06 | **Games Highlights** — `server3.php` sub-nav **Recent** \| **Highlights**; board order Most goals → Biggest draws → One-side peak → Biggest wins; ties **lower game ID first** (SQL + `data-k2-sort-tie-value`). |
| 2026-05 | **`milestone.php` Graphs typography** — chart titles use **`k2-panel-heading`**; block hints + legend labels use **`--k2-text-secondary`**. |
| 2026-05 | **`milestone.php` polish** — removed catalog footer line; Graphs blocks use Activity-style **`--k2-chart-max-width` (960px)** boxed charts. |
| 2026-06 | **Staging `perfect_day` / `nightmare_day` day-close** — Steve SQL + Dagh browser smoke **done**: **113** rows midnight UTC; Recent **`00:00`**; garden **Games** → `individual3.php?day=`; total **6620**. Handoff: [`milestones-staging-cutover-packet.md`](docs/coordination/milestones-staging-cutover-packet.md) § Day-close. |
| 2026-05 | **`milestone.php` Graphs** — per-key year + cumulative charts (tier `T.pitch()` etc., ladder MIN Date→today); removed monthly timeline. |
| 2026-05 | **`milestone.php` segments** — **Made it** \| Graphs; carry-scroll on tier filter + detail panels. |
| 2026-05 | **Milestones catalog** — removed redundant `title` tooltip on cards (name/rule already visible). |
| 2026-05 | **`milestone.php` spotlight** — garden-style glow card (name + rule only); dropped hero tier/holders/desc panel. |
| 2026-05 | **`milestone.php` achievers table** — tier `--k2-ms-accent` on header hairline, sort, links; row/wrap borders unchanged. |
| 2026-05 | **Profile milestone garden** — Game/League event links use tier ink (`k2-ms-tier-event-link`), not link-star. |
| 2026-05 | **Milestone dates** — display/header drop “UTC” suffix; achievers Unlocked column stays muted when sorted. |
| 2026-05 | **Milestone achievers sort** — `k2-table.js` tie-break on `data-k2-sort-tie-value` (fixed `#` when `achieved_at` matches, e.g. same game). |
| 2026-05 | **`milestone.php`** achievers — fixed `#` = unlock order (1 earliest); default sort unlock desc; no autorank. |
| 2026-05 | **`milestone.php`** — hub Recent \| Catalog sub-nav (`$k2MsHubView` empty); text crumb removed. |
| 2026-05 | **Milestones Recent filter** — tier segment pills (`.k2-ms-recent-tier-filter` + chrome track; tier ink). |
| 2026-05 | **Catalog cards** — dropped rule `line-clamp` + hidden `<br>` pad (false `…`); `min-height` only. |
| 2026-05 | **`five_goal_frenzy`** — `rule_short` → `5+ goals in one game` (catalog copy patch). |
| 2026-05 | **Milestones Recent** — fix league/Games event links (`html_entity_decode` before tier re-wrap). |
| 2026-05 | **Milestones Recent** — cluster left; filter centred over table width; fixed cols + inner scroll. |
| 2026-05 | **Text selection** — `::selection` on `body.k2-site` (tint-aware); overrides Windows/browser blue default. |
| 2026-05 | **Hub lede** — shared `.k2-hub-page-intro` (muted 13px): Milestones catalog + HoF notes above tables. |
| 2026-05 | **Leagues pickers** — Flatpickr day grid + month chrome + date input: secondary (selected day still accent). |
| 2026-05 | **HoF + hub LBs** — HoF label/date muted; ranked/Status/peaks calm tables secondary; active sort primary 600. |
| 2026-05 | **Milestone garden** — catalog-style hover lift (`translateY(-1px)`) on profile garden cards. |
| 2026-05 | **Milestones Catalog** — tier sections + garden headings (Aspirational…Legendary); sort unchanged within band. |
| 2026-05 | **Milestones Catalog intro** — single-line h1 instead of title + lede split. |
| 2026-05 | **Milestones Recent layout** — five shrink-wrapped columns (when · player · milestone · muted rule · event); tier links not link-star; feed `width: fit-content`. |
| 2026-05 | **`five_goal_frenzy` explainer** — `rule_short` = “Scored 5 goals or more in one game” (seed + local DB via catalog copy patch). |
| 2026-05 | **Milestones Catalog UI** — compact equal-height grid; no card/title glow (glow stays on profile garden). |
| 2026-05 | **`milestones-README.md`** — plain-language Rule vs Event; seed vs copy-patches workflows for agents. |
| 2026-05 | **`perfect_day` event copy** — `event_context_label` = “All wins that UTC day (5+ rated games).”; regen `milestone_garden_links.json`. |
| 2026-05 | **Milestones doc consolidation** — [`milestones-README.md`](docs/milestones-README.md) + generated [`milestones-catalog.md`](docs/milestones-catalog.md) (112 keys); tier-curated tables archived; one build script. |
| 2026-05 | **Milestone achievers table** — **Event** (what happened) + **Link** (Game/League/Games from register). |
| 2026-05 | **Milestone unlock event UI** — one register (`event_link` + `event_context`); Recent feed + garden + achievers use `k2_milestone_unlock_event_*`; spec [`milestones-unlock-event-ui.md`](docs/milestones-unlock-event-ui.md). |
| 2026-05 | **Milestones Recent typography** — feed **13px** / 1.45 (hub parity with tabs, Status hints); was 14px body inherit. |
| 2026-05 | **Milestone ms-holo only** — legendary `#bf80f8`; dedicated chrome + pitch/amber use `--k2-pure-*` on milestone surfaces. |
| 2026-05 | **Milestones Recent tab** — stripped intro/count copy; fixed **100**-row feed; tier filter only (`milestones.php`). |
| 2026-05 | **Doc hygiene — SCH-008 / milestone counts** — removed stale MEMORY bullet (“staging SCH-008 pending”); canonical timeline in [`replay-register.md`](docs/coordination/replay-register.md) § Milestone unlock row counts (**6615** rows today, not 151). |
| 2026-05 | **Status leaderboard tooltips** — Elo help: full tables in Leaderboards section; Games = career count. |
| 2026-05 | **LB header tooltips** — `lb_column_help.php`; hub wings: no Elo label-echo; Games/abbrev/formulas; ranked5 inverse-record tie rule in tooltips; footer legend removed; Peak/Nadir 20-game copy. |
| 2026-05 | **Post-game cutover index** — [`post-game-cutover-checklist.md`](docs/coordination/post-game-cutover-checklist.md): peak-at-20, `club_*` on `Rating`, pointers `>`, HoF, replay ritual; contract § Rating club implementation notes (investigation closed). |
| 2026-05 | **Leagues picker slot** — fixed row width = max(day, week, month, year) pickers; tab switch stable. |
| 2026-05 | **Day picker label** — `May 27, 2026` (no weekday); meta ticker unchanged. |
| 2026-05 | **Listbox fixed width** — measure longest option label; lock trigger width (Leagues + Flatpickr + Daily). |
| 2026-05 | **Leagues labels/meta** — full month names; meta `League` (plain) + blue label; listbox open state without accent ring on scroll. |
| 2026-05 | **Listbox typography** — archive/Flatpickr pickers: secondary + weight 500; subtle hover mix (not full primary); design-direction row. |
| 2026-05 | **Leagues day picker chrome** — Daily trigger matches archive listbox (date label + chevron, no calendar icon); opens Flatpickr on click. |
| 2026-05 | **Leagues picker visibility** — one picker per tab via `data-active-period` CSS + formatted date on Daily; fixes week picker stuck between arrows. |
| 2026-05 | **Flatpickr listbox fix** — month/year dead clicks: capture `stopPropagation` on `click` blocked trigger handler; shield uses mousedown capture only; day grid `pointer-events` layering. |
| 2026-05 | **Flatpickr listbox** — re-init month/year on calendar open if DOM replaced; archive pickers close when switching to Day. |
| 2026-05 | **KOOL listbox pickers** — Leagues week/month/year + Flatpickr month/year use `k2-archive-listbox` (accent hover); native `<select>` removed from those controls. |
| 2026-05 | **Career peak/nadir contract** — `website-data-contract.md`: unset until 20 games; establish both from post-game `Rating` at game 20; max/min every game after; no gain/loss gate; full replay on cutover. `club_*` milestones = **`Rating`** (provisional OK); rebuild/C++ alignment **TBD**. |
| 2026-05 | **Playertable tie policy in contract** — `website-data-contract.md`: personal pointers + inverse BL/BW/MGC/MGS need `>` (not `>=`); HoF handoff separate; `ranked5` tooltips/footer called out at cutover. |
| 2026-05 | **Streaks LB headers** — `ranked4.php`: Wins, Undefeated, Draws, Decided, Losses, Win drought, Days, Weeks (no “streak” in labels). |
| 2026-05 | **Hub LB padding reset** — all `ranked-pages-table` wings: uniform 8px in CSS; stripped `k2-table-cell--pad-left-*` from ranked1–7,10 + league honours (Goals/DD/CS already clean). |
| 2026-05 | **LB column padding** — removed legacy `k2-table-cell--pad-left-*` on Goals + DD/CS wings (`ranked2`/`ranked3`); was widening cols after full-word headers. |
| 2026-05 | **DD/CS LB headers** — `ranked3.php`: Double Digits, Clean Sheets, DD conceded, CS conceded; ratio cols still abbreviated; footer removed. |
| 2026-05 | **k2-table sort toggle** — same-column click re-sorts asc/desc (was DOM reverse only); fixes second click not reaching true descending order. |
| 2026-05 | **Goals LB headers** — `ranked2.php`: Scored/Conceded, Most Scored/Most Conceded, Draw/Goal sum, Win/Loss margin; footer trimmed. |
| 2026-05 | **LB filter toggles keep sort** — `k2_sort`/`k2_dir` on inactive/provisional toggle hrefs; `k2-table.js` syncs URL + filter links on column sort (same wing only; wing tabs unchanged). |
| 2026-05 | **Established = 20 games aligned** — `K2_ESTABLISHED_MIN_GAMES` in `lb_player_filters.php`; HoF ratio leaders + footer 20 (was 30); HoF LB links add `provisional=0`. C++ ratio blocks still documented as legacy 30. |
| 2026-05 | **HoF context links** — values → LB wings + `k2_sort`; `provisional=0` only on ratio/average rows; activity peaks → `ranked8#…`. |
| 2026-05 | **Milestone catalog copy pass** — 26 keys (`display_name`/`rule_short`); seed + `milestone_catalog_copy_patches.json` + `apply_milestone_catalog_copy_patch.py`; staging `patch_milestone_catalog_copy.php`; local DB applied. |
| 2026-06 | **Tint menu polish** — hub/player nav right-anchored **Tint** disclosure + dim swatch pills (`k2_tint_picker.php`, `k2-tint-toggle.js`, `theme.css`); schedule/manual override unchanged. |
| 2026-05 | **Six-hour tint schedule** — local slots; manual pill lasts **current period only** (`k2-accent-manual-period`); `k2-tint-schedule.js` + boot; optional UTC via `k2-accent-clock`. |
| 2026-05 | **Realm switcher hidden** — Online/Amiga toggle not shown in production header; markup + `realm-switch.js` kept; `status-realm-lab.php` unchanged. |
| 2026-05 | **Play & Setup hub tab** — `join.php` in `hub_nav.php` (2nd tab); header utility link removed; spec [`docs/join-play-setup.md`](docs/join-play-setup.md), [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). |
| 2026-05 | **`k2-link-star` links** — default hover/focus underline site-wide; `k2_player_link()` emits class; Elo stays `<span class="k2-link-star">`. |
| 2026-05 | **Color primitives** — `--k2-pure-*` + pointer chain documented in `design-direction.md` § Color System; code in `theme.css` / `chart-theme.js`. |
| 2026-05 | **Milestones garden order** — Legendary: **`year_in_heaven`** after **`merchant_trade_fair`**; **`play_streak_100`** before **`club_10000`** (10K last). |
| 2026-05 | **Peer pill carry-scroll** — hub / `lb_nav` / `player_nav` keep `window.scrollY` on pill navigation; same active pill click does not reload (`preventDefault`); short pages extend min-height; other links unchanged. |
| 2026-05 | **Hall of Fame layout** — Peak performance panel: spacer row after Best goal ratio (before frequency rows). |
| 2026-05 | **`year_in_heaven` staging verified** — catalog **112**, **5** unlock rows on `kooldb` (2018–2025; geo4444=344/2021); establishing game on completing week; profile Played weeks + add-one playbook local-verify note. |
| 2026-05 | **Milestone `year_in_heaven` shipped** — 52 UTC weeks/calendar year; `gen_milestone_year_in_heaven_sql.py` + PHP post-game; handoff [`milestones-year-in-heaven-handoff.md`](docs/coordination/milestones-year-in-heaven-handoff.md). |
| 2026-05 | **Profile Played weeks** — career map (52 UTC week tiles/year from **first rated game**); `player_calendar_weeks.php` + `player-calendar-weeks.js`; tooltips show range + `games` from `player_period_games`. |
| 2026-05 | **Profile Personal bests** — busiest day/month/year read `player_peak_period_games` (one query) instead of three `ratedresults` GROUP BY scans; matches ranked8 peak cache. |
| 2026-05 | **`play_streak_100` catalog on staging** — `milestone_definitions` **111** rows; key verified (**100 days**, UTC-day rule); 0 holders until someone hits 100-day streak. |
| 2026-05 | **Milestones catalog total** — garden + hero read `k2_milestone_catalog_total()` from DB (111); `play_streak_100` after `merchant_trade_fair` in legendary garden order. |
| 2026-05 | **Milestone `play_streak_100` (100 days)** — first post-v0 catalog add; `rule_short` in `milestone_definitions`; rebuild SQL + post-game on day streak 100; playbook [`docs/coordination/milestones-add-one-playbook.md`](docs/coordination/milestones-add-one-playbook.md); 0 holders on current import (max day streak 87). |
| 2026-05 | **Rated play streaks — staging verified** — Steve SCH-014 + REP-015 on `kooldb` (max day **87**, week **126**; HoF 582/344); UI **ranked4** Days/Weeks + **server2** Most days/weeks in a row; registers + handoff updated; prod C++ post-game pending. |
| 2026-05 | **Profile hero milestones typography** — milestone row matches 20px stat values; shared value min-height + baseline alignment so numbers rest on same floor as rank/rating/games. |
| 2026-05 | **Profile hero — milestones, no peak** — `player_hero.php`: rank · rating · games · milestones (`{n}/110` + tier dots); peak removed from hero (still on `ranked1` + rating charts); glance strip removed; all player tabs via `player_hero_vars.php`. |
| 2026-05 | **`diversity_merchant` staging verified** — REP-008b: **25** holders, **6615** total rows, tier `key`/`amber`; matches local; [`milestones-staging-diversity-merchant-fix.md`](docs/coordination/milestones-staging-diversity-merchant-fix.md). |
| 2026-05 | **Milestones staging DB (wave 1)** — Steve REP-008/014: 110 keys, full rebuild + giant_slayer=31; superseded row count 6658 → **6615** after diversity fix. |
| 2026-05 | **Milestones staging — MariaDB period SQL fix** — removed `LATERAL` from `player_milestones_rebuild_period.sql`; staging bootstrap runs statements individually; Steve re-upload + re-run REP-008. |
| 2026-05 | **Milestones staging cutover packet** — [`docs/coordination/milestones-staging-cutover-packet.md`](docs/coordination/milestones-staging-cutover-packet.md): WinSCP manifest, Steve commands, expected counts (incl. **giant_slayer = 31**), staging PHP rebuild scripts. |
| 2026-05 | **Milestones post-game contract** — `website-data-contract.md` § full write rules (game/league/lobby), M1–M7 Steve phases. |
| 2026-05 | **Milestones v0 sanity** — `milestone_v0_sanity_check.py` passed (PHP helpers = SQL; browser spot-check). |
| 2026-05 | **Hub IA — Milestones tab + Games off hub** — `hub_nav.php`: Status · Activity · Leaderboards · **Milestones** · HoF; `milestones.php` stub; `server3.php` via Status only; WIP [`docs/milestones-hub-ia.md`](docs/milestones-hub-ia.md). |
| 2026-05 | **Leaderboards wing order (scenario A)** — `lb_nav.php`: classic block unchanged (Rating→Victims), then League honours · Milestones · Activity peaks, **Peak rating** last (`ranked1`); hub default still `ranked7.php`. |
| 2026-05 | **Milestones LB polish** — `ranked10.php` + League honours: **ELO rating** header + `k2-table-cell--pad-left-sm` on Games (matches classic ranked tables). |
| 2026-05 | **`giant_slayer` rule fix** — active #1 (365d rolling UTC); `milestone_giant_slayer.py` + chrono regen; surgical `player_milestones_rebuild_giant_slayer.sql`; contract post-game §; holders 22→31 (geo4444 unlocks). |
| 2026-05 | **Milestones Phase 4 v0** — garden page, profile `{n}/110` glance + tier dots, `ranked10.php` meta-leaderboard, `server2.php` DD Merchant achiever trial; read-only on local DB. |
| 2026-05 | **Games hub (`server3.php`)** — day headings for days older than yesterday show weekday + date (`Monday · May 26, 2026`); Today/Yesterday unchanged. |
| 2026-05 | **Milestones rebuild complete** — `gen_milestone_tail_sql.py` (30 playertable/matchup keys); **110/110** in `player_milestones`, 0 null `source_kind`; tail parity 0 mismatches. |
| 2026-05 | **Milestones chrono wave** — `gen_milestone_chrono_sql.py` → 16 keys; `milestone_chrono_parity.py` 0 mismatches. |
| 2026-05 | **Milestones period wave** — `player_milestones_rebuild_period.sql` wired (5 keys); **64/110**; `milestone_period_parity.py` 0 mismatches. |
| 2026-05 | **Milestones streaks wave** — `gen_milestone_streak_sql.py`; 8 streak keys; `milestone_streak_parity.py`. |
| 2026-05 | **Milestones wave 2** — peak `club_*`, `club_10000`, 18 exists feats; exists parity script. |
| 2026-05 | **Milestones wave 2a** — `debut`, `persistence`, `club_500` (Nth game + `source_game_id`). |
| 2026-05 | **`entered_arena` locked** — register = lobby → `JoinDate`, `source_kind=lobby`; distinct from `debut`. SCH-013. |
| 2026-05 | **Milestones source pointers** — SCH-012 on `player_milestones` (`source_kind`, game id or league period); wave 1 rebuild populates 22 league/game key types. |
| 2026-05 | **Milestones Phase 3 kickoff** — SCH-011 `milestone_definitions`, seed loader, facilitation doc, league wave in `player_milestones_rebuild.sql`; rebuild order = milestones after REP-012. |
| 2026-05 | **Leaderboards wing tab** — `ranked8` sub-nav label **Activity peaks** (was Activity). |
| 2026-05 | **League honours grain persistence** — Activity ↔ Points tab links keep current day/week/month/year (`league_honours_panel.php`). |
| 2026-05 | **Milestones Phase 2 trim** — cut `period_champion`, `six_goal_draw`; `persistence` = 10 games; `milestones_definitions_seed.json` export (`--export-seed`). |
| 2026-05 | **Milestones league names locked** — last 12 placeholders named in `milestones_curated_meta.json` (Burned the day, Honour board, Almost the headline, siege/ledger/monthly/yearly set, Cupboard filling up). |
| 2026-05 | **Milestones naming pass** — display names + Name Q (1–5) in `data/milestones_curated_meta.json`; curated doc regen adds weak-name index; `clean_sheet_merchant` → `clean_sheet_artist`. |
| 2026-05 | **`milestones-tier-curated.md`** — authoritative four-band snapshot (auto-regen from probe); cut nemesis, elite_customer, podium_month, still_here, monthly activity winner. |
| 2026-05 | **Milestones want/maybe by theme** — [`docs/milestones-want-maybe-by-theme.md`](docs/milestones-want-maybe-by-theme.md): ~112 deduped items in 22 thematic groups (A–V) for manual tier assignment; no tiers assigned. |
| 2026-05 | **Milestones tier plan** — [`docs/milestones-product-spec.md`](docs/milestones-product-spec.md): four color bands (garden + story + leaderboard tie-break); Key = amber ~15–20 completeness set (same as achiever lists); plan not locked. [`milestones-project.md`](docs/milestones-project.md) updated. |
| 2026-05 | **Status Leagues toolbar** — period segment left, ←/picker/→ centered in remaining row width; nav nowrap; wraps to full-width centered row via `is-period-nav-stacked` + ResizeObserver (`theme.css`, `status-period-competitions.js`). |
| 2026-05 | **League awards on staging** — Steve SCH-009/010 + REP-012/013 on `kooldb` (7424 instances, 21873 awards; matches local); Dagh confirmed League honours + Status leagues UI parity. |
| 2026-05 | **League period pills** — segment labels Daily / Weekly / Monthly / Year (`k2_status_period_segment_label`); panel titles use “Year league” not Yearly. |
| 2026-05 | **`player_league_slice_totals` (SCH-010)** — per-player gold/silver/bronze by league_kind × period_type; REP-013 rebuild; League honours + `k2_league_player_slice_totals()` for profile. |
| 2026-05 | **League honours views** — `ranked9.php` pills Overall / Activity / Points + Day–Year; URL `cup` & `grain`. |
| 2026-05 | **League honours v1** — `ranked9.php` wing; spec [`docs/leagues-career-leaderboard-proposal.md`](docs/leagues-career-leaderboard-proposal.md). |
| 2026-05 | **Activity league uncapped on Status** — all players with ≥1 game shown; `limit=0` default in API/SSR. |
| 2026-05 | **Rating wing anchor** — `ranked1.php`: Peak (col 4) is link-star anchor; current Elo is neutral like other columns. |
| 2026-05 | **Status league cross-tint anchors** — `k2-table--league-anchor-cross`: Games/Pts use `--k2-league-anchor-ink` (chrome on amber/pitch tint, pitch on chrome/holo), not `--k2-link-star`. |
| 2026-05 | **Status league calm-stats fix** — `status-period-competitions.js` rebuilds league HTML client-side; matched PHP calm-stats/anchors + `window.k2TableApplyAnchors` after inject/cache restore. |
| 2026-05 | **Calm-stats site-wide (hub tables)** — `k2-table--calm-stats` + anchors on ranked8 activity peaks, Status league tables, `server2.php` record values; `initAnchorTables()` for non-sortable tables. Profile `individual2a/b/c` unchanged. |
| 2026-05 | **Leaderboard calm-stats** — all hub sortable LBs + Status active board: neutral cells, anchor link-star; active sort = bold grey until tuned. |
| 2026-05 | **Leaderboard anchor columns** — `data-k2-anchor-col` + `k2-table.js`: one permanent link-star column per wing (Elo on Rating/Results/Status only); lighter `k2-table-col-sorted` when sorting a non-anchor column. |
| 2026-05 | **League awards Track 1 local** — `league_standings.php`, REP-012 backfill, finalize script; Status points/activity use tie-break sort; `player_league_totals` + win milestones synced. |
| 2026-05 | **League career wins** — `league_wins_*` = #1 in any of 8 (period × points/activity); `player_league_totals.wins`. |
| 2026-05 | **Leagues rules + SCH-009** — tie-breaks locked (points: Pts→GD→GF→Pld→first_game_id→idB; activity: games→first_game_id→idB); `period_end` = achievement time; player-centric `player_league_award`; deep-link `status.php?league_kind=&period=&start=`; PER-003 daily finalize. |
| 2026-05 | **Milestones Phase 1 closed** — idea creation done: [`docs/milestones-project.md`](docs/milestones-project.md), discussion paper, pass 1 catalog (draft, not final). Naming: Milestones + Key subset; own hub tab + profile count + meta-leaderboard planned. Monthly regular rule: game every day of a calendar month. Phase 2 = definition/spec. |
| 2026-05 | **Staging SCH-008 + REP-007–011 done** — Steve applied stored-truth expansion on `kooldb`; milestones re-run after MariaDB fix; verify all 15 checks pass (74,870 games, `established_20_diff=0`). Registers updated. |
| 2026-05 | **Removed dev period activity preview** — deleted `dev-period-activity.php` + `js/status-period-activity.js`; activity league lives on Status Leagues only. |
| 2026-05 | **Leagues cleanup + docs** — removed dead legacy league panel PHP; docs: Phase 1 shipped / 1.5 next. |
| 2026-05 | **Phase 1.5 backlog** — wip checklist + day games list; handoff [`docs/coordination/status-period-competitions-phase-1.5-handoff.md`](docs/coordination/status-period-competitions-phase-1.5-handoff.md). |
| 2026-05 | **Status Leagues lock-step floor** — `first_rated_day` from `ratedresults`; clamp day/week/month after derive; picker labels `Jul 2017` for synthetic options. |
| 2026-05 | **Status Leagues rapid ←/→** — abort stale foreground fetch; nav seq for errors; prewarm debounced + max 2 parallel; clear error on cache hit. |
| 2026-05 | **Status Leagues day ← fix** — `day_min` falls back to `ratedresults` when `player_period_games` has one day; prewarm default on. |
| 2026-05 | **Status Leagues day calendar** — icon toggle close fix; custom month dropdown (12 months, disable out-of-range vs Flatpickr hiding). |
| 2026-05 | **Status Leagues nav fix** — JSON keys attrs (single-quoted); showView uses `hidden` attr; Flatpickr on separate anchor not day value field. |
| 2026-05 | **Status Leagues nav** — ←/→ + picker; removed scope toggle; SSR current period per tab; medals when period ended. |
| 2026-05 | **Status year leagues meta** — end date includes year (`ended Jan 1, 2026 UTC` for 2025 leagues). |
| 2026-05 | **Status Leagues layout** — points centered in space after activity; wrap only when insufficient room (not scope-based gaps). |
| 2026-05 | **Status Leagues meta** — ended periods: end **date** in blue (`ended May 25, 00:00 UTC`); live countdown duration only in blue. |
| 2026-05 | **Status Leagues scope UX** — 3-way segment (Today / Last week / Earlier); period pickers visible only for Earlier; prev labels Last week/month/year. |
| 2026-05 | **Status period competitions Phase 1** — replaced four stacked points-only league panels with paired Activity + Points block (`status_period_competitions_section.php`, `status-period-competitions.js`, `api/status_period_points_league.php`); WIP spec [`docs/status-period-competitions-wip.md`](docs/status-period-competitions-wip.md). |
| 2026-05 | **Policy doc sweep** — deleted `cpp-snippets/`; merged `post-game-cpp-handoff` into `post-game-register`; archived refactor plan + period-games handoff; fixed `PROJECT_MAP`, `STATUS_PAGE_DATA`, `player-profile-feast`, `UPDATE_DOCS` contract row; clarified SCH-008 staging vs local in feature-log/schema-register. |
| 2026-05 | **Post-game snippet workflow retired** — behavior only in `docs/website-data-contract.md`; local/staging = SCH + REP; deleted `cpp-snippets/` PG-005–013; kept `docs/coordination/records-post-game-exception.md` (ex-HoF PG-004). Agents must not cite PG-NNN as blocking work. `feature-log` uses **Prod live** not Post-game column. |
| 2026-05 | **Staging HoF record defects catalogued** — [`docs/staging-post-game-record-defects.md`](docs/staging-post-game-record-defects.md): Gianni streak dates, Fiery CS victims, Eternalstudent opp/vic, etc. (C++ post-game); golden checks extended; ops doc clarifies ladder replay vs website-derived rebuild. |
| 2026-05 | **Post-game replay contract** — Python replay now pins `SET time_zone = '+00:00'` at connection, so `generalstatstable` record dates are UTC-correct. `docs/website-data-contract.md` expanded with full `generalstatstable` semantics (tie policy: strict `>`, ratio leaders excluded, UTC rule, victim-count gates). Golden record checks added (`scripts/ladder/golden_record_checks.py`). PG-004 rewritten as explicit behavior-change handoff (DELETE ratio blocks, CHANGE `>=` to `>`, ADD UTC pin). Replay architecture section documents event engine as behavior authority, SQL rebuilds as parity helpers. Local replay rerun: all golden checks pass. |
| 2026-05 | **Derived-data contract refactor** — `docs/website-data-contract.md` is now the behavior authority for project-owned aggregate tables, rebuild rules, parity checks, and post-game requirements. `scripts/rebuild_website_derived_data_local.ps1` is the one-command local rebuild path; old period/monthly rebuild wrappers now point to it. `docs/stored-truth-expansion.md` and `docs/player-period-games.md` are redirects, while registers track status only. |
| 2026-05 | **UTC period-boundary fix** — `ratedresults.Date` is `timestamp`, so local Estonia MySQL sessions were rebuilding day buckets three hours ahead of UK/staging. Added `SET time_zone = '+00:00'` to PHP DB connections and rebuild scripts, reran local aggregate rebuilds, and verified daily stored rows now match UTC buckets (e.g. 2026-05-17=26, 2026-05-18=31). `api/server_matchup_breadth.php` now also uses the UTC pin and `server_period_matchups`. |
| 2026-05 | **Daily active players chart** — `server_daily_activity` (SCH-007); stored path ~73× faster than raw `ratedresults` in local perf test; API `source=stored|raw`. |
| 2026-05 | **Dev period activity date picker affordance** — the Daily panel date input now has a visible accent calendar button and a brightened native picker indicator, so users can open the calendar instead of typing a date. |
| 2026-05 | **Top activity eras chart shipped locally** — `server1.php` now has a "Top activity eras" multi-player line chart: each month shows the top 10 players by rated games, lines appear/disappear as players enter/leave the top 10, hover highlights one player and dims others; powered by new `api/server_top_activity_eras.php` reading `player_period_games` (L0, no new stored truth). |
| 2026-05 | **Realm header identity layout promoted** — shared `site_header.php` now uses the first lab direction: Online/Amiga beside the Kick Off 2 wordmark, with player search isolated on the right; strip variant remains lab-only for comparison. |
| 2026-05 | **Stored truth performance policy added** — agent instructions now say DB-backed features should actively consider indexes, aggregate tables, replay outputs, `playertable` fields, periodic jobs, and post-game C++ updates as normal options for hot stats/profile/achievement work, not burdens to avoid. |
| 2026-05 | **Ranked8 phone activity layout fix** — Calendar and All time activity tables now keep their intended two-column layout below tablet widths, with horizontal overflow only if a very narrow viewport needs it. |
| 2026-05 | **Period activity staged preview unblocked** — `dev-period-activity.php` now permits the staging host (`ratings.kickoff2.com`) while remaining host-guarded elsewhere; page copy now says dev/staging preview. |
| 2026-05 | **Status panel action-link alignment** — the active leaderboard `Leaderboards →` link now uses the same compact Status action styling as `Activity →` and `Games →`. |
| 2026-05 | **Activity Graph Roadmap shipped** — five new Activity features: 12-month daily heatmap (GitHub-style), participation depth stacked bars (1/2-4/5-9/10+ bands), play-texture small-multiples (goals/game, draw %, DD/100, CS/100), unique matchups per month, and a recent milestone digest card; all L0 read-time from `ratedresults`+`playertable`. |
| 2026-05 | **Double Digit Merchant charts** — Activity now has a read-time chart trio for first 10+ goal games: new merchants by year, cumulative merchants, and merchant rating distribution; data is derived from `ratedresults`, not stored on `playertable`. |
| 2026-05 | **Activity copy sharpened** — `server1.php` no longer says "server" in user-facing chart headings/status/aria copy; the past-month daily games chart now shows the same `Games` legend chip as the longer-horizon charts. |
| 2026-05 | **Tooltip microcopy audit** — redundant chart helper under the Activity daily chart removed; table/header tooltip copy now favors abbreviation definitions, formulas, and contextual rules while obvious labels fall back to the shared `Click to sort.` affordance; tint picker native hover titles are removed. |
| 2026-05 | **Chart semantics pass** — chart colors now follow a first-pass vocabulary: pitch = games/wins/profile subject, amber = goals, chrome = active players/projections/opponent focus, holo = cumulative history, magenta = milestones, teal = distributions; dense monthly bars stay borderless. |
| 2026-05 | **Activity recent daily chart** — `server1.php` now opens its chart stack with a past-month games-per-day bar chart from `api/server_games_by_day_recent.php`, including zero-game days. |
| 2026-05 | **Hub nav reordered** — top nav is now `Status · Activity · Games · Leaderboards · Hall of Fame`, frontloading life/evidence before competition and records. |
| 2026-05 | **Status leaderboard sorting** — Status active leaderboard now loads `k2-table.js` for sortable Rank/Player/Elo/Games columns with compact header help, autorank on resort, `past year` heading copy, and `Leaderboards →` destination meta. |
| 2026-05 | **Game table tooltips** — `server3.php` keeps all-column header popups and `game.php` mirrors them as non-sortable help; deep Elo explanation lives on `Fav ES` and visible `Adjustment`. |
| 2026-05 | **Activity summary completes legacy stats merge** — `server1.php` now folds the old Overall Server Stats table into a key sentence, four fact cards (goals/draws/DD/CS), and a quiet games/opponents line before charts. |
| 2026-05 | **Status arc → Activity landing** — Status rated-games arc links to `server1.php` with a discreet left-aligned action below the sentence; Activity opens with the all-time activity story before the historical charts. |
| 2026-05 | **Table spacing cleanup + Games detail path** — inline table `&nbsp;`/`text-align` hacks removed from ranked/player/server/game table families in favor of `theme.css` utilities; `server3.php` now shows 14 day buckets with fully sortable game tables (`GD`, `Elo Diff`, `Fav ES`, `Adjustment`), and Status recent games links to the full Games list via `Games →`. |
| 2026-05 | **Status league stack shipped locally** — `status.php` now stacks uncapped Daily, Weekly (Monday-start), Monthly, and Yearly league panels where the monthly league was; each has its own current/previous toggle, shared 3/1/0 table logic, MySQL `NOW()` server-clock boundaries, and live countdown/end meta. |
| 2026-05 | **Period activity + daily activity on staging** — `kooldb`: SCH-006/007 + REP-003 (week), REP-005, REP-006 backfills done May 2026; stored-truth PHP OK on staging; prod live C++ at cutover per contract. |
| 2026-05 | **Status online presence fix** — Online now uses nonzero `IsOnline` directly, without the `Display = 1` ladder/public-stats gate, and `status.php` sends no-cache headers so frozen lobby presence is not hidden by stale pages. |
| 2026-05 | **Status recent games simplified** — recent games on `status.php` now show player names and score only; rating adjustment deltas were removed from that compact lane. |
| 2026-05 | **Status column balance tweak** — `status.php` room grid now runs ticker/new players, online/logins, live/recent games, then a strengthened art/leaderboard lane, with the first column slightly widened. |
| 2026-05 | **Leaderboard filter docs cleanup** — stale open/todo references removed from hub/status docs; current Leaderboard filters are treated as shipped, not next-step experiments. |
| 2026-05 | **Persistent tint preference** — tint picker now stores `k2-accent-tune` in `localStorage`, migrates old session-only values, boots before first paint, and syncs across open tabs. |
| 2026-05 | **Status realm header lab** — `status-realm-lab.php` compares two mock shells on real Status content: A realm beside wordmark, B realm strip above hub nav; shared header remains unchanged. |
| 2026-05 | **Status performance staging DB done** — Steve ran SCH-005 + REP-004 on staging `kooldb`; indexes exist and `player_monthly_league` check passed (`SUM(played)` 149,740 = `ratedresults` × 2). Monthly row count differs from local (2,674 vs 2,679), which is OK; appearances are the invariant. |
| 2026-05 | **`elolist.css` cleanup** — legacy stylesheet removed from shared head; ranked table cloak now lives in `theme.css`; K2 table plan open-work item closed. |
| 2026-05 | **Hub nav preview scaffolding pruned** — removed `nav-preview.php`, `?k2_hub_nav`/session style overrides, and solid/soft CSS branches; segment nav is now the fixed contract. |
| 2026-05 | **Tint picker docs settled** — hidden-by-default behavior remains current; stale launch-decision wording pruned. |
| 2026-05 | **Chart helper tone audit** — stale chart/helper tone backlog pruned; current chart contract/copy already covers canonical colours, context, sample-size, and matchup framing. |
| 2026-05 | **K2 table plan cleanup** — stale open-work entries pruned; remaining follow-ups now reflect only active table work. |
| 2026-05 | **Status page performance fix** — local schema `004_status_performance_and_monthly_league.sql` adds `ratedresults.Date` + live `resulttable` indexes and `player_monthly_league`; Status monthly league now prefers the aggregate with raw SQL fallback. Loader ~6.6s → ~51ms; local HTTP ~8.5s → ~0.28s. |
| 2026-05 | **Current-truth docs prune** — MEMORY recent log trimmed; `design-direction.md`, `hub-ia-agreement.md`, and `k2-table-and-games-plan.md` now foreground current contracts/open work instead of phase diary history. |
| 2026-05 | **Replay/ops safety gates** — ladder replay now has explicit `--target local|staging`, refuses staging `kooldb` unless target is explicit, logs DB identity preflight, staging wrapper passes `--target staging`; local schema/index/period rebuild wrappers refuse non-local DBs without `-AllowNonLocal`. |
| 2026-05 | **Period activity staging DB done** — Steve ran `player_period_games` schema + rebuild on staging `kooldb`; expectation test passed; note MariaDB requires `COUNT(*)`, not `COUNT()`. |
| 2026-05 | **Legacy PHP safety pass** — added `includes/k2_safety.php`; `individual2a/b/c.php` now validate player `id`, use safe DB connect/query errors, and escape opponent links; `ranked1`–`ranked5`/`ranked7` use the same helper for DB connect/query errors and escaped player links. |
| 2026-05 | **Sortable header help tooltips** — `k2-table.js` now uses a styled shared tooltip for sortable headers, combining abbreviation/activity/player-table explanations with the “Click to sort.” hint, including server-side Games history sort links. |
| 2026-05 | **Realm switch flash fix** — header toggle initial paint now follows early `html[data-realm]` boot state, so Amiga no longer flashes Online during main-nav page loads. |
| 2026-05 | **Leaderboard/player table modernization** — `ranked1`–`ranked5`, `ranked7`, `ranked8`, and `individual2a/b/c` use opt-in `k2-table.js`; profile Games uses server-side Result/Opponent filters, URL sort links, 100-row slices, and shared row rendering. |
| 2026-05 | **Activity / Hall of Fame / Records polish** — `ranked8.php` period/all-time activity tables, `server2.php` two-panel Hall of Fame split, peak-period aggregate fallback, and natural-width table polish are in repo. |
| 2026-05 | **Games tab shared row renderer** — `game.php` and the Games tab share `includes/k2_rated_game_row.php`; current Games tab behavior is recorded in the newer table-spacing cleanup row above. |
| 2026-05 | **Status Phase B v1.2 in repo** — `status.php` has 4-col room grid, active leaderboard, monthly league toggle, recent logins/registrations/games; prod DB read + joshua redirect still open. |
| 2026-05 | **Profile feast shipped** — production `individual1.php` feast layout only; mock lab removed; further profile work should be gradual copy/UX. |
| 2026-05 | **Core migration/prod coordination set up** — `prod-coordination.md`, registers, schema migrations, staging replay docs; prod post-game from `website-data-contract.md`; prod live ratings still C++. |
| 2026-05 | **Chart/theme foundation shipped** — six-ink chart palette, dark theme tokens, shared header/nav/wing tabs, and `status.php` hub landing are in repo. |

---

## Deferred / blocked

- GitHub branch protection — when collaborators land.
- **Amiga/offline** datasets — after dev migration workflow is routine.
- **Pretty URLs** (`/online/{id}`) — needs Steve (`.htaccess` / vhost).

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub | https://github.com/DaghN/ratings.kickoff2 · branch `main` |
| Staging SFTP | `ratings.kickoff2.com:5322` · user `dagh@ratings.kickoff2.com` |
| Deploy | WinSCP **Synchronize** `site/public_html/` → remote `public_html/` |
| Legacy reference | https://joshua.kickoff2.net/ratings/ |
| Local site | `http://ratingskickoff.test` — **`docs/LOCAL_DEV.md`** |
| Staging DB | MariaDB 10.11 · `kooldb` writable · **no live game writes** |
| Local DB | `ko2unity_db` · dump `data/dumps/` · replay `scripts/run_local_replay.ps1` |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| `ratedresults` indexes | `idx_ratedresults_idA`, `idx_ratedresults_idB` — local + staging; prod via Steve |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
