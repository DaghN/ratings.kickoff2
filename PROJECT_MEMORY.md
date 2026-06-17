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

- **Status Leagues:** Phase **1** shipped. Optional backlog only — [`status-period-competitions-wip.md`](docs/status-period-competitions-wip.md) (no agent handoff).

- **Profile:** Feast shipped on **`player/profile.php`**. Optional **lab compare** only (`individual1-profile-lab*.php`) — prompts in [`archive/profile-lab-agent-handoff.md`](docs/archive/profile-lab-agent-handoff.md); live spec [`player-profile-feast.md`](docs/player-profile-feast.md).

- **Design / Status hub:** Phase B v1.2 room grid shipped. Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](docs/activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones · **Games** · HoF · Play & Setup — [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). **Games hub (Jun 2026):** `games/recent.php` + Highlights + **All games** vault (filters, server sort). **URLs:** semantic paths + `games/` + `milestones/` folders; registry [`k2_routes.php`](site/public_html/includes/k2_routes.php) — [`url-routes.md`](docs/url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Operational loop:** edit locally → **WinSCP** sync `site/public_html/` → staging; hard refresh. Steve runs server one-offs.

- **Local:** `ratingskickoff.test` (dev) + `work.ratingskickoff.test` (work) — [`LOCAL_DEV.md`](docs/LOCAL_DEV.md).

- **Change style:** small, reversible slices.

- **Amiga realm (Jun 2026):** **Disposition review** — register **605/605**; **38** `pending_review` (promoted through **284**; **187** deferred split); [`disposition-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

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
| 2026-06 | **Hub chapter titles** — accent+glow trial **reverted**; keep primary white: sole orientation landmark, avoids link-star/tint collision; lede stays editorial. |
| 2026-06 | **Streaks LB nav fix** — `leaderboards/streaks.php` used `hub_nav.php` + single `lb_nav.php` (was duplicate wing chrome with Rating active). |
| 2026-06 | **League period sort** — scoped `k2_sort` per table (`league-standings` / `league-games`); fixes games table picking standings column on load. |
| 2026-06 | **League period hub bar** — `hub_nav.php` + tint picker on `league.php`; no active hub pill; `#k2-league-period` anchor unchanged. |
| 2026-06 | **League period step chevrons** — prev/next period on open/close line (Status bounds); half-pill gap, no label. |
| 2026-06 | **League period landing anchor** — `#k2-league-period` on title `h1`; hash scroll after layout; carry-scroll restore skipped when URL has hash. |
| 2026-06 | **League period URL parse** — `league.php` accepts month (`2023-01`) and year (`2023`) start keys from Status; was Y-m-d only. |
| 2026-06 | **League period page intro** — hub-chapter title (`Points league · Week 22, 2026`); prose lede with UTC open/close instants + link-star game count (live vs final). |
| 2026-06 | **League period page upgrade** — Standings + paginated full games table (`k2_rated_game_row`); medals only after period ends. |
| 2026-06 | **Status league column links** — “Activity league →” / “Points league →” link to `league.php` for active period key; JS syncs href on tab/step/picker. |
| 2026-06 | **League period page** — removed footer meta line (“Current leagues on Status…”); page ends at standings table. |
| 2026-06 | **Milestone league source link** — `k2_league_period_href()` now uses `k2_route('league')` (root `/league.php?…`); fixes 404 from `/player/milestones.php` and `/milestones/*`. |
| 2026-06 | **Steve `kooldb1` full re-simul** — bootstrap + simul + `run_verify_ops_sim` **0 fail / 0 warn** (74,865 processed); participation sums, reached_at oracle, play-streak HoF, milestone totals/holder_count parity PASS; orphan-milestone verify fix confirmed. |
| 2026-06 | **Result streaks UI polish** — tooltip/banner dates `M j, Y`; streak games list newest-first; player games date `M j Y, H:i`; GD `+` on wins. |
| 2026-06 | **Result streaks slice 4** — Streaks LB tooltips + click-through to player games (`from_game`/`to_game` filter); `lb_result_streaks_lib.php`; back-link `from=result-streaks`. |
| 2026-06 | **Result streaks slice 2** — `k2_result_streak_after_rated_game()` in `process_completed_game.php`; verify `result_streak_oracle` in `run_verify_ops_sim.php`; work smoke 100 PASS. |
| 2026-06 | **Result streaks slice 1** — SCH-026 `player_result_streaks` + `player_result_streaks.php` rebuild/oracle; REP-016 `rebuild_player_result_streaks.php`; zero-derived truncate; UI = slice 4. |
| 2026-06 | **SCH-025 participation reached_at** — P4b stores establishing game on `active_*` bump; HoF/LB read stored columns (~28ms vs ~12s); backfill `rebuild_participation_reached.php`; verify oracle in activity wing parity. |
| 2026-06 | **HoF career celebration** — Most milestones + league gold/silver/bronze (read `player_milestone_totals` / `player_league_totals`; dates from latest unlock/award); `records_career_leaders.php`. |
| 2026-06 | **HoF Activity rows** — month/year play streaks (GST) + participation leaders (active days/weeks/months/years + longevity) after streak block; LB deep links; `records_activity_leaders.php`. |
| 2026-06 | **LB Elo tooltip** — `k2_lb_help_elo_rating()` now says “We use standard Elo…” (online + Amiga leaderboards). |
| 2026-06 | **Header cross-realm search fix** — `player-search.js` click/Enter used root `realm=all` instead of per-hit `data-player-realm`; Amiga picks now route to `/amiga/player/profile.php`. |
| 2026-06 | **Leaderboards wing order** — Rating → Activity → Milestones → League honours → Goals → DDs → Streaks → Victims → Peak rating (`lb_nav.php`). |
| 2026-06 | **Activity wing — `kooldb1` proven** — Steve full simul + verify PASS; track closed. |
| 2026-06 | **Hall of Fame layout** — single record table; extra chapter-to-table breathing room (`margin-top` on `.server-records-hof`). |
| 2026-06 | **Games All games filters** — 28px margin above filter block (sub-nav separation; results stay tight below). |
| 2026-06 | **Hub IA — Games tab** — promoted to hub nav after Milestones (`hub_nav.php`, `$k2HubTabActive = 'games'`); Status **Games →** kept. |
| 2026-06 | **Games hub intro** — All games chapter line: “searches the full history with filters and sorting”. |
| 2026-06 | **Amiga player games** — Reset uses shared `k2-player-games-reset` accent pill. |
| 2026-06 | **Player games day banner** — lead sentence ends with period after year; “UTC” suffix removed. |
| 2026-06 | **Player games day view** — removed “clear day filter”; Reset uses shared `k2-player-games-reset` accent pill (All games + Games tab). |
| 2026-06 | **Games All games status row** — fixed-width count line + stable chevron column; 60px gap before Reset filters. |
| 2026-06 | **Games All games player search** — filter autocomplete applies `?player=` (removed duplicate `player-search.js` load; pick URL built at click). |
| 2026-06 | **Games All games phase 2 filters** — Player (search + rating + A–Z), Opponent (gated; by games + A–Z), Score-line (GD/Sum/TS), Year (in/since/until); `k2_realm_games_all_filters_ui.php` + `k2-realm-games-filters.js`. |
| 2026-06 | **Games hub sort column highlight** — All games + Recent: PHP `k2-table-col-sorted` via `k2_rated_game_sort_col_index`; Highlights/player games unchanged (JS / existing PHP). |
| 2026-06 | **Games TS column + All games filter prep** — **TS** on Recent/All/`game.php` full rows; All games `top_score` sort; shared `k2_ratedresults_games_filters.php`; `k2_realm_games_all` WHERE/pager/sanitize; `player-search.js` filter mode; phase 2 filter UI still WIP. |
| 2026-06 | **Top score (TS)** — Highlights board `top_score` (pill + heading “Top score”); column **TS** + tooltip; HoF `most_goals_one_game` → `board=top_score`; replaces one-side peak / Peak column. |
| 2026-06 | **Games All games v1** — `games/all.php`: Recent-shaped table, server-side sort (all columns), 250-row pages, Previous/Next + Reset; `includes/k2_realm_games_all.php`; filters deferred. |
| 2026-06 | **Games hub folder URLs** — `games/recent.php`, `games/highlights.php`, `games/all.php` (placeholder); was single `games.php` + `?view=`; `k2_routes` keys `games-recent` / `games-highlights` / `games-all`; HoF spectacle links via `k2_games_highlights_href()`. |
| 2026-06 | **Milestones hub folder URLs** — `milestones/recent.php` + `milestones/catalog.php` (was `milestones.php` + `?view=catalog`); `k2_routes` keys `milestones-recent` / `milestones-catalog`; no legacy redirects (pre-public). |
| 2026-06 | **Opponents H2H pickers spacing** — doubled gap between search/listbox row and fighter poster cards (`clamp(56px, 8vw, 88px)`). |
| 2026-06 | **Opponents H2H all-games link** — `games.php?opponent=` now `#matching-games` (anchor above “Showing … matching games”). |
| 2026-06 | **Played-days** — legend under grid removed; hint = story line per year. |
| 2026-06 | **Games tab day filter polish** — `?day=` banner uses tooltip-style date (`Monday, Jan 27, 2034`); **← Played days** → profile `#played-days`; default sort Date desc when day set without explicit sort. |
| 2026-06 | **Profile played-days tooltip games** — hover list (≤8) with UTC time, pre-game ratings, Status-style rows; “Click for more” → Games tab day filter. |
| 2026-06 | **Profile played-days tooltips** — `k2-table-tooltip`; date `M j, Y`; game count from `player_period_games`. |
| 2026-06 | **Profile played-days hint** — per-year count in section hint (replaces “through today”); year picker only. |
| 2026-06 | **Profile played-days year picker** — inactive hover matches player nav (`rgba(255,255,255,0.05)`). |
| 2026-06 | **Profile played-days year picker** — active year pill gets realm accent fill (milestone tier filter pattern). |
| 2026-06 | **Profile played-days toolbar** — year count line beside year picker; count in link-star. |
| 2026-06 | **Profile played-days heatmap** — first career year + current year always show full 12-month grid (future/prior months empty). |
| 2026-06 | **Opponents H2H combined goals x-label** — “Goal sum” below chart (not panel hint); Chart.js x-axis title off. |
| 2026-06 | **Opponents H2H combined goals naming** — chart title, hint, tooltips, axis, meta aligned on “combined goals per game” (was “total goals”). |
| 2026-06 | **Opponents H2H combined goals meta** — “…average {avg} combined goals per game” (word order). |
| 2026-06 | **Opponents H2H total goals histogram click** — bar click → `games.php?gs=` + `opponent=` (goal sum listbox); nearest-bar hit testing + hint copy. |
| 2026-06 | **Player games Goal sum listbox** — `gs` filter UI on `games.php` (backend already wired); matches GF/GA listbox pattern with game counts in meta. |
| 2026-06 | **Opponents H2H grouped goals histogram click** — side-by-side bars: rival (red) click → `ga` filter; was always `gf` because global Chart.js interaction mode `index` returned both bars and handler took `elements[0]`. |
| 2026-06 | **Activity league orphan eligibility** — `LEFT JOIN playertable` + `#id` fallback (same as points); finalize/awards/milestones include deleted-account IDs; work: zero-derived → simul. |
| 2026-06 | **Work DB ops hygiene** — `work-db-prepare.md` §1.5: sign-off = prepare + simul only; docs demote batch repair; CLI refuses `rebuild-all` on work targets. |
| 2026-06 | **SCH-021 holder_count policy** — counts all unlock rows (incl. deleted accounts); bump on each unlock; verify same rule; no post-simul rebuild; migration 021 DDL-only; lobby rebuild after bulk seed only. |
| 2026-06 | **Opponents H2H wins chart title** — cumulative wins chart heading `Wins vs {opponent}` (was `Head-to-head vs`). |
| 2026-06 | **Opponents H2H all-games link** — chrome `All {N} rated games vs {name} →` below race table, above moments; → `games.php?opponent=`. |
| 2026-06 | **Profile rivalry teaser (placeholder)** — dashed card after Most played opponents: top rival by games + H2H link; fuller band TBD. |
| 2026-06 | **Opponents H2H scoreline heatmap grid** — square 0…N both axes; N = max GF or GA seen in the pairing (e.g. 13 → 14×14 cells). |
| 2026-06 | **Opponents H2H scoreline heatmap tile size** — fixed 36px cells (was fluid ~46px on wide Dagh vs Logos); horizontal scroll when grid wider than panel. |
| 2026-06 | **Opponents H2H scoreline heatmap axis** — x tick labels live in the data grid header row so GA numbers center on tiles. |
| 2026-06 | **Opponents H2H scoreline heatmap scale** — intensity legend rows labelled `{hero} win` / `{rival} win` (full phrase chrome/red). |
| 2026-06 | **Opponents H2H race 0–0 tint** — only **Least conceded** row colours a 0–0 tie; other rows (incl. clean sheets) stay muted. |
| 2026-06 | **Opponents H2H scoreline heatmap hover** — 2px flush border in outcome pure colour (chrome / holo / red). |
| 2026-06 | **Opponents H2H scoreline heatmap touch** — phone: tap tile → tooltip + ring; tap same tile again → games list. |
| 2026-06 | **Opponents H2H scoreline heatmap axes** — hero vertical (0 bottom, label left); rival horizontal (0 left, ticks + name below grid); origin 0–0 bottom-left. |
| 2026-06 | **Opponents H2H scoreline heatmap** — full GF×GA grid (subject POV); win chrome / loss red / draw holo + count intensity; click → `games.php?gf=&ga=&opponent=`; below total goals chart. |
| 2026-06 | **Opponents H2H total goals meta copy** — “have scored {avg} total goals on average” (not “a total of … goals”). |
| 2026-06 | **Opponents Goals TG/g column** — average combined goals per game `(GF+GA)/games` after Ratio on Goals tab; read-time from summary rows. |
| 2026-06 | **Opponents H2H total goals histogram** — SumOfGoals distribution (holo bars); click → `games.php?gs=` + `opponent=`; below cumulative goals on H2H tab. |
| 2026-06 | **Opponents H2H cumulative goals chart** — chrome/red lines by game #; extends `player_head_to_head.php`; sits below cumulative wins on H2H tab. |
| 2026-06 | **Opponents H2H pair detail performance rating** — first race row; chess-style TPR per side from `ratedresults` (read-time, min 2 games); shared `performance_rating.php` with Amiga. |
| 2026-06 | **Opponents H2H poster stat mirror** — opponent card outputs rating before rank so rank stays on the outer (avatar) edge, matching the subject card toward the `vs`. |
| 2026-06 | **Opponents H2H goals histogram copy** — hints use hero + opponent names (not you/this opponent). |
| 2026-06 | **Opponents H2H listbox meta** — `ensureOption` preserves `.k2-h2h-listbox__meta` on selected row (same fix as games GF/GA). |
| 2026-06 | **Player games opponent listbox** — game count as right-aligned meta (split row), not parenthesis in label; matches GF/GA listboxes. |
| 2026-06 | **Opponents H2H goals histograms** — your + rival single-series charts **and** grouped side-by-side chart; shared 0..max x-axis; clicks → `gf` / `ga`. |
| 2026-06 | **Profile goals-per-game histogram** — full GF distribution (0..max) on Profile; `player_goals_distribution.php` + API; amber bars; click → `player/games.php?gf=`; games tab GF listbox shares helper. |
| 2026-06 | **Opponents H2H chart headings** — `Head-to-head vs {opponent}` and `Rating comparison vs {opponent}` (PHP + JS on chart load). |
| 2026-06 | **Opponents H2H chart meta** — cumulative wins chart shows `{n} rated games` only. |
| 2026-06 | **Opponents H2H rating compare toggle** — By date / By games active state uses pure chrome (not tint segment tokens). |
| 2026-06 | **Profile top opponents chart** — most-played bar back on Profile (own section); bar click → Opponents H2H; pair charts stay on H2H tab only. |
| 2026-06 | **Profile games/month chart** — fixed relative `api/` path (404 under `/player/`); same class of bug as pre-move H2H matchup charts. |
| 2026-06 | **Opponents H2H charts** — Profile Matchups block moved to H2H tab (cumulative H2H · rating compare); Profile keeps career rating + games/month; top opponents restored on Profile Jun 2026. |
| 2026-06 | **Opponents H2H charts** — Profile Matchups block moved to H2H tab (top opponents · cumulative H2H · rating compare); bar click switches `?opponent=`; Profile keeps career rating + games/month only. |
| 2026-06 | **Opponents Goals GF/GA tooltips** — column help says “against this opponent” (not career copy from leaderboards). |
| 2026-06 | **Opponents H2H race 0–0 ties** — tied zeros stay muted grey on all race rows except Clean sheets (both sides still chrome/red there). |
| 2026-06 | **Player games GF/GA filters** — `player/games.php` adds Goals scored + Goals conceded listboxes (`gf`/`ga` URL params); options from player’s distinct counts (Opponent-style). |
| 2026-06 | **Opponents H2H moments name wrap** — scoreline names fill grid column (`stretch` + `width:100%`); dropped `text-wrap:pretty`; light negative letter-spacing so ~14-char names (e.g. Eternalstudent) stay one line in 48rem deck. |
| 2026-06 | **Opponents H2H moments goal glow** — winner/draw goal numbers use race-table neon text-shadow stack. |
| 2026-06 | **Opponents H2H poster lead meter** — chrome/red glow via blurred pseudo + box-shadow; track on `::before` so bloom isn’t buried. |
| 2026-06 | **Opponents H2H poster W/L glow** — full neon stack on win counts at weight 600 (700 was too heavy with same glow). |
| 2026-06 | **Opponents H2H moments card chrome** — active cards use milestone detail spotlight border + glow stack by default. |
| 2026-06 | **Milestone detail spotlight** — default card uses full lit border + glow (was hover-only). |
| 2026-06 | **Lit-card hover fix** — dropped broken html shadow tokens; 2px borders + explicit garden glow (rest + hover); matches profile `pm3-moment` thick border. |
| 2026-06 | **Lit-card hover pattern** — shared `--k2-card-lit-hover-shadow` in `theme.css`; applied to H2H fighter cards, milestone garden (unlocked), catalog, detail spotlight; H2H moments already had it. |
| 2026-06 | **Opponents H2H moments hover** — active cards: profile-style lit border + glow on hover (box-shadow ring, no layout shift). |
| 2026-06 | **Opponents H2H race table ties** — equal rows: both values in own colour (chrome + red), not holo. |
| 2026-06 | **Opponents H2H moments kicker** — sentence-case display title (dropped all-caps tag styling). |
| 2026-06 | **Opponents H2H moments score ink v2** — winner name in own colour; beaten side much dimmer; dash = winner colour. |
| 2026-06 | **Opponents H2H moments score ink** — smaller goals; winner number + date = winner colour; beaten name/goals = dim grey; draw = holo throughout. |
| 2026-06 | **Opponents H2H moments layout** — shorter cards (drop min-height + scoreline push-down); wider grid gap both axes. |
| 2026-06 | **Opponents H2H moments card glow** — card border glow matches milestone garden `.k2-ms-card.is-unlocked`; removed radial fill + top hairline + hover shadow ramp; inner text glow unchanged. |
| 2026-06 | **Opponents H2H rivalry chrome lock** — subject side locked to `--k2-pure-chrome` for pickers (search + listboxes), poster, race table, moments; wing hero keeps picked tint. |
| 2026-06 | **Opponents H2H moments — scorecard redesign** — 3×3 deck of `k2-h2h2-mcard` cards; true score orientation (`NameA gA–gB NameB`, never flipped, full names both sides); subject blue / opponent red identity; winner-glow not hero Win/Loss; no emoji; **card accent = game outcome** (subject win blue / rival win red / draw holo); self-contained CSS. |
| 2026-06 | **Player hero accent tweak** (cosmetic, all hero pages) — name now `--k2-link-star` (matches rank/rating ink); base `.k2-player-hero__stat-value` + `.k2-player-hero__milestones-total` muted to `--k2-text-secondary` so **Games + Milestones** read quiet (both brighten to primary on hover) while Rank/Rating stay link-star (via globally-loaded `player-hero-rank.css`). Also `.k2-player-hero__inner` → `align-items:center` so the avatar centres against the text block (was top-lifted via default stretch). `theme.css` only. Avatar stays pure `--k2-accent` (flagged: not link-star). |
| 2026-06 | **Opponents DDs color** — Double Digits blue, DD conceded red (parity with Goals GF/GA). |
| 2026-06 | **Opponents Goals GF/GA color** — GF column blue, GA column red (parity with W/D/L wins/losses). |
| 2026-06 | **Opponents default wing** — main Opponents pill → Head-to-head (`player_opponents_default_href()` / `player/opponents/h2h.php`). |
| 2026-06 | **Milestone totals (Phase 2)** — SCH-020 `player_milestone_totals`; librarian bump; meta LB + profile read stored truth; parity in `verify_ops_sim` — [`milestones-unlock-librarian.md`](docs/milestones-unlock-librarian.md). |
| 2026-06 | **Milestone catalog holders (Phase 2b)** — SCH-021 `holder_count` on `milestone_definitions`; librarian bump; hub catalog + detail reads; parity in `verify_ops_sim`. |
| 2026-06 | **Milestone unlock librarian (Phase 1)** — all live ops `player_milestones` INSERTs via `includes/milestone_unlock.php` — [`milestones-unlock-librarian.md`](docs/milestones-unlock-librarian.md). |
| 2026-06 | **H2H poster contract** — versus panel spec (rank/rating/W-D-L centre; no country; pair detail band scoped); [`player-opponents-h2h-poster.md`](docs/player-opponents-h2h-poster.md). |
| 2026-06 | **Carry-scroll restore** — no upward re-scroll after first apply; still scrolls down when short pages grow. |
| 2026-06 | **Opponents H2H carry-scroll** — opponent pick (search + listboxes) stores `scrollY` before navigate; same restore path as hub pills / games filters. |
| 2026-06 | **Opponents H2H v1** — search (global, games vs context) + games/A–Z dropdowns + pair headline; `?view=h2h&opponent=`; charts still on Profile. |
| 2026-06 | **Opponents Phase 3 slice 3** — `ko2unity_work` zero + simul to game 500 (verify PASS, spot parity 24 pairs); Goals/DDs Opponents tabs read SCH-019 summary; Steve `kooldb1` next. |
| 2026-06 | **Opponents Phase 3 slice 2** — SCH-019 migration + P5 extended upsert + AB parity. |
| 2026-06 | **Opponents slice A** — W/D/L + core Goals read `player_matchup_summary` (~25× faster locally vs live scan); Goals Max/Min pending schema slice B; DDs still live. |
| 2026-06 | **Player wing shell** — unified « Leaderboards link (`player_wing_up_link.php`, `k2-player-wing` body class, single `k2-page-nav` from `site_header`); online + Amiga player tabs. |
| 2026-06 | **Player Opponents hub Phase 1** — top pill Opponents; `player/opponents.php` + inner W/D/L · Goals · DDs · H2H (stub); old wdl/goals/dds pages removed; Profile unchanged — [`player-opponents-hub.md`](docs/player-opponents-hub.md). |
| 2026-06 | **Player Opponents hub (planning)** — agreed IA: umbrella Opponents pill (W/D/L · Goals · DDs · H2H sub-tabs), slim Profile matchups, optional Career totals; recenter doc [`player-opponents-hub.md`](docs/player-opponents-hub.md). |
| 2026-06 | **Player hero back link** — compact `&larr; Leaderboards` above hero (status `k2-status-panel__more` sizing) on online + Amiga player pages. |
| 2026-06 | **Player games Result column** — draws show **Draw** (not `-`) on online + Amiga games tabs; `k2_player_game_result_html()`. |
| 2026-06 | **Amiga profile rating chart polish** — shorter chart hint; `player-feast-sections.css` toggle styling; calendar tooltip “at day end”. |
| 2026-06 | **Amiga player hero country flags** — fourth hero stat column (Country label + flag) after Rank/Rating/Games; `k2_amiga_country_flag.php` + `img/flags/amiga/` SVGs. |
| 2026-06 | **Amiga import split queue closed** — Milan X not split (documented alias); Groningen **604** + Gloucester **605** splits shipped; gate open for disposition review. |
| 2026-06 | **Amiga Gloucester III Team import split** — id **62**→90g + **605**→10g; both `pending_review`; register **605/605**. |
| 2026-06 | **Amiga Groningen VII Cup import split** — `IMPORT_CATALOG_SPLITS` + append hook; id **48**→46g + **604**→14g; both `pending_review`; register **604/604** — [`import-layer`](docs/amiga-import-layer.md) § catalog splits. |
| 2026-06 | **Amiga import split starter** — append-only synthetic catalog policy; queue Groningen VII (split), Gloucester III (pending), Milan X (not split); blocks disposition until gate — [`import-split-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-import-split-REVIEW-STARTER-PROMPT.md). |
| 2026-06 | **Amiga disposition register + pure_knockout handler** — 603/603 JSON; preview CLI; 70 pending_review — [`handlers`](docs/amiga-tournament-structure-handlers.md) · [`disposition-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md). |
| 2026-06 | **Amiga tournament structure slice 6 apply** — GATE E OK; `materialize-tier-b-non-wc --apply` 41/41 (later **35 rolled back** by cup audit). |
| 2026-06 | **Amiga tournament structure slice 6** — `materialize-tier-b-non-wc` bulk CLI; dry-run **41/41** OK — handoff [`018`](docs/orchestration/agent-handoffs/2026-06-13-018-amiga-tournament-structure-slice-6-curation.md). |
| 2026-06 | **Amiga tournament structure slice 5** — `materialize-tier-a` bulk CLI; dry-run **503/503** OK — handoff [`017`](docs/orchestration/agent-handoffs/2026-06-13-017-amiga-tournament-structure-slice-5.md). |
| 2026-06 | **Amiga tournament structure slice 6 CLI** — `materialize-tier-b-non-wc` dry-run 41/41; 38 tests; GATE E pending apply. Review track: [`REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md). |
| 2026-06 | **Amiga tournament structure slice 4** — `verify-legacy` CLI (fixture integrity + optional standings parity); `audit-inventory` tier A/B/C/D; GATE B′ passed. |
| 2026-06 | **Amiga tournament structure undo/resume** — handoff [`015`](docs/orchestration/agent-handoffs/2026-06-13-015-amiga-tournament-structure-undo-and-resume.md); slice 3 pilot void; Athens IV local standings restored; resume slice 4. |
| 2026-06 | **Amiga tournament structure slice 3b (policy v2)** — RR scope + KO tie modules; tier-A auto only; `dematerialize`; Athens IV dematerialized; handoff [`014`](docs/orchestration/agent-handoffs/2026-06-13-014-amiga-tournament-structure-slice-3b.md). |
| 2026-06 | **Amiga tournament structure slice 3** — ~~pilot~~ **superseded** — handoff [`012`](docs/orchestration/agent-handoffs/2026-06-13-012-amiga-tournament-structure-slice-3.md). |
| 2026-06 | **Amiga tournament structure slice 2** — builders, Homburg spec, build/verify helpers, link side-parity doc, browser ops `round_robin`; Homburg verify OK (86/86); handoff [`2026-06-13-011-amiga-tournament-structure-slice-2.md`](docs/orchestration/agent-handoffs/2026-06-13-011-amiga-tournament-structure-slice-2.md). |
| 2026-06 | **Amiga tournament structure slice 1** — migration `023` (`round_robin`/`knockout` stage enum); `_fixture_scope` + PHP parity; `VALID_STAGE_TYPES`; 13 stages migrated (8 RR, 5 KO); STOP GATE A — handoff [`2026-06-13-010-amiga-tournament-structure-slice-1.md`](docs/orchestration/agent-handoffs/2026-06-13-010-amiga-tournament-structure-slice-1.md). |
| 2026-06 | **Amiga tournament structure policy v2** — RR scope + KO **tie** modules; rounds in StructureSpec; NULL auto RR only when full schedule else flag; slice 3 pilot superseded; restart handoff [`013`](docs/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md). |
| 2026-06 | **Amiga tournament structure track — planning** — policy [`amiga-tournament-structure-policy.md`](docs/amiga-tournament-structure-policy.md); implementation plan slices 1–9; starter [`amiga-tournament-structure-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md) (modules vs structure; game-authoritative legacy backfill; migration `023` next). |
| 2026-06 | **Amiga tournaments index** — Date column before Tournament; formatted `M j, Y` (profile/LB event date style). |
| 2026-06 | **Leaderboards hub lede** — “rating” → “ratings” in `lb_nav.php` chapter copy. |
| 2026-06 | **Amiga tournament honours LB** — default sort Events (desc); server ORDER BY matches JS default. |
| 2026-06 | **Amiga tournament medals v2 track complete** — slices 0–8; honours rules v2 **Implemented**; migrations `021`/`021b`/`022`; universe contract + data contract updated. |
| 2026-06 | **Amiga tournament medals v2 slice 7** — honours LB v2 columns (Elo, event block, WC block, medal SVG headers); default sort WC gold; Alkis P #1 event podiums (85). |
| 2026-06 | **Amiga tournament medals v2 slice 6** — migration `022` drops `wc_medal`; writers/readers/verify cleaned; `compute_wc_podium_finish_from_standings`; rebuild+verify OK; zero `wc_medal` in py/php. |
| 2026-06 | **Amiga tournament medals v2 slice 5** — profile/tournaments read paths: finish from `event_finish_position`; honours strip uses `event_podiums`; STOP GATE B OK (Alkis P). |
| 2026-06 | **Amiga tournament medals v2 slice 4** — full `participation-rebuild` (4517/473); v2 totals verify invariants; STOP GATE A passed (Alkis P `event_gold=58` `wc_gold=2`). |
| 2026-06 | **Amiga tournament medals v2 slice 3** — v2 totals writers (`event_*`/`wc_*`); `is_winner` = finish 1 only; Python/PHP parity; Alkis P spot `event_gold=58` `wc_gold=2`. |
| 2026-06 | **Amiga tournament medals v2 slice 2** — `021b` backfill: 70 WC podium rows (`wc_medal`→finish 1/2/3); verify wc_medal/finish parity OK. |
| 2026-06 | **Amiga tournament medals v2 slice 1** — Tier D: WC podium → `event_finish_position` 1/2/3 (`compute_tier_d_wc_finish`); Python + PHP parity; 45 unit tests OK. |
| 2026-06 | **Amiga tournament medals v2 slice 0** — migration `021` on `ko2amiga_db` (`event_*`, `wc_played`/`wc_podiums`; drop `cup_*`; `podiums`→`event_podiums`); fresh `011`; verify-player-participation OK. |
| 2026-06 | **Amiga tournament medals v2 track** — honours policy v2 (unified `event_finish_position`, `event_*`/`wc_*` totals, drop `cup_*`/`wc_medal`); plan + starter [`amiga-tournament-medals-unification-*`](docs/amiga-tournament-medals-unification-implementation-plan.md); execution slices 0–8 pending. |
| 2026-06 | **Status Online panel** — `.k2-status-name-list` stacks one player per line (was flex-wrap inline). |
| 2026-06 | **Amiga perf-rating leaderboard** — dropped Career games column; default sort index adjusted. |
| 2026-06 | **Amiga HoF table sync** — Career + Peak panels use `server-records-panels--sync-cols` (shared `records_hof_table.php` dry-run); WC medals table unchanged (3-col). |
| 2026-06 | **Amiga perf-rating leaderboard** — Date column after Event (`M j, Y` via `amiga_profile_format_event_date`); `event_date`/`event_chrono` in `amiga_lb_performance_rating_rows`. |
| 2026-06 | **Amiga perf-rating leaderboard intro** — `performance-rating.php` lede: perfect-record exclusion + draw/loss qualification wording. |
| 2026-06 | **Amiga game.php Date column** — `k2-table--single-game` overrides ranked-pages col-2 min-width (was leaderboard Player 9.1em). |
| 2026-06 | **Amiga game.php (Phase 2)** — `/amiga/game.php?id=` neutral row (tournament, phase, Elo); `amiga_rated_game_row.php`; ID links from player games + profile moments. |
| 2026-06 | **Amiga player URL taxonomy (Phase 1)** — profile/games/tournaments → `/amiga/player/*`; `k2_amiga_routes.php`; 302 from legacy flat URLs; frees `/amiga/games.php` for future realm log. |
| 2026-06 | **Leaderboards hub lede** — `lb_nav.php` chapter intro: “activity peaks...” ellipsis instead of “, and more.” |
| 2026-06 | **Activity hub lede** — `activity.php` chapter intro shortened (removed day/month/year clause; Oxford comma on “active, and who”). |
| 2026-06 | **Goals table column parity** — `player/goals.php` + hub `leaderboards/goals.php` + `amiga/leaderboards/goals.php`: GF/GA, Max win/loss/sum labels; tail order Max GF → Max GA → Max win → Max loss → Max sum → Draw (Min* profile-only). |
| 2026-06 | **Games table top scroll mirror** — `data-k2-scroll-mirror` + `k2-table-scroll-mirror.js` on player games + hub `games.php` Recent/Highlights (synced bar when wide). |
| 2026-06 | **In-page scrollbar chrome** — `--k2-scrollbar-*` tokens + shared rules on `.k2-table-wrap`, listbox panels, heatmaps, profile week grid, bracket rails, etc. (muted thumb; not OS gray). |
| 2026-06 | **Amiga games Year filter** — listbox `?year=` (calendar year only); `YEAR(Date) =` selected year; perf API + sort URLs preserve filter alongside existing Since. |
| 2026-06 | **Carry-scroll restore fix** — `k2_carry_scroll_restore.php`: conditional retries (head, DOMContentLoaded, ResizeObserver); no `load` re-apply; user wheel/touch/key/mousedown cancels; fixes scroll-to-top during games page load. |
| 2026-06 | **hub-ia-agreement carry-scroll** — peer pill row documents games pager (`data-k2-carry-scroll` on `.k2-player-games-status`). |
| 2026-06 | **Player games status line** — `.k2-player-games-status` 12px `--k2-text-muted` (was inheriting body 14px primary); online + Amiga games/tournament list summaries. |
| 2026-06 | **Carry-scroll games pager** — online `player/games.php` Previous/Next 100 (`data-k2-carry-scroll` on status bar); shared `[data-k2-carry-scroll] a.k2-player-games-action` selector. |
| 2026-06 | **Carry-scroll filter Reset** — `a.k2-player-games-action` inside `form[data-k2-carry-scroll]` (games filters online + Amiga + tournament games tab). |
| 2026-06 | **Carry-scroll listboxes + sort** — `k2-carry-scroll.js`: shared `K2CarryScroll.store()`, listbox `change` on `form[data-k2-carry-scroll]`, server-sort on `.k2-table--player-games`; script non-deferred in `k2_head.php`; online `player/games.php` form opted in. |
| 2026-06 | **Amiga games Since filter** — listbox `?since=` (calendar year); `YEAR(game_date) >=` selected year; perf API + sort URLs preserve filter. |
| 2026-06 | **Amiga games list Perf. rating** — async `api/amiga_player_games_perf_rating.php` + `amiga-player-games-perf.js`; status line on `/amiga/games.php`; shared `amiga_player_games_filters_from_request()`. |
| 2026-06 | **Player tournament filter panel** — `.k2-player-tournament-filters` padding `4px` (match player nav); tighter row gaps. |
| 2026-06 | **Carry-scroll reverted** — `k2-carry-scroll.js` + `k2_carry_scroll_restore.php` restored to last committed baseline (simple pill click → store `scrollY`; no session peak / per-dest cache). |
| 2026-06 | **Amiga games WC filter fix** — World Cup SQL REGEXP had stray literal space (`World Cup [[:space:]]` → `World Cup[[:space:]]`); World Cups pill returned 0 games. |
| 2026-06 | **Player games filter layout** — stacked label above listbox per field; Amiga games row = four equal columns + Reset (online + Amiga). |
| 2026-06 | **Player games sort carry-scroll** — `k2-carry-scroll.js` stores scrollY on `.k2-table--player-games` server-sort column clicks (online + Amiga `/player/games.php`, `/amiga/games.php`). |
| 2026-06 | **Player goals matchup table** — min/max column labels tightened: **Min/Max GF/GA**, **Min/Max sum**, **Max win/loss**; shared `k2_lb_help_least_*` tooltips. |
| 2026-06 | **Amiga player games tournament filter** — Tournament listbox on `/amiga/games.php` (`?tournament=`); events ordered latest-first (`chrono` / `event_date`); sort links + matching-games status preserve filter. |
| 2026-06 | **Amiga player games status** — unfiltered list: `N official games.`; filtered: `N matching games.` |
| 2026-06 | **Amiga player games filters** — Result/Opponent listbox + Reset keep scroll (`data-k2-carry-scroll` on filter form). |
| 2026-06 | **Player tournament history** — plain count line above table (`39 events in total.`; filter-aware; no sort jargon). |
| 2026-06 | **Player tournament history filters** — `.k2-player-tournament-filters` panel (Event + Location rows); All / World Cups only; carry-scroll on pills. |
| 2026-06 | **Amiga tournament games tab** — removed in-tab **Games** h2 (nav pill is enough). |
| 2026-06 | **Amiga tournament games Phase col** — hidden when every row has empty `phase` (plain single-league events); shown when any game has a phase label. |
| 2026-06 | **Amiga tournament games filter** — player listbox + Reset keep scroll (`data-k2-carry-scroll`; listbox `change` handler — `form.submit()` skips submit event). |
| 2026-06 | **Amiga tournament event stats** — removed in-tab **Event stats** h2 only; lede kept. |
| 2026-06 | **Amiga tournament standings** — removed league-table footnote (replay/3-1-0 copy) under standings tables. |
| 2026-06 | **Amiga tournament nav scroll** — section pills under hero use `data-k2-carry-scroll` (same as hub/player nav) so tab switches keep scroll position. |
| 2026-06 | **Amiga tournament entry anchor** — `#tournament` on hero; `amiga_tournament_link()` defaults to it so index/profile links land with tournament title at viewport top. |
| 2026-06 | **Amiga hub Activity tab** — `/amiga/activity.php` empty placeholder; after Live tournaments in hub nav. |
| 2026-06 | **Amiga hub News tab** — `/amiga/news.php` empty placeholder; first hub tab + realm default (wordmark + Amiga 500 switcher). |
| 2026-06 | **Header wordmark realm home** — Kick Off 2 title links to current realm default (`/status.php` or `/amiga/news.php`), not always online. |
| 2026-06 | **Amiga staging export fix** — `export_ko2amiga_db.ps1` now dumps participation, tournament totals, matchup summary, generalstats (+ finish override); 29 parts (was 24). Fixes empty `/amiga/player-tournaments.php` on staging after import. |
| 2026-06 | **Amiga tournaments hub** — removed chapter lede on `/amiga/tournaments.php` (title + filter pills only). |
| 2026-06 | **Online hub copy** — status arc + leaderboards lede: “online Kick Off 2” without leading with “rated”; competitive detail in tables/wings. |
| 2026-06 | **Agent track playbook** — [`docs/orchestration/agent-track-playbook.md`](docs/orchestration/agent-track-playbook.md) (doc · plan · prompt · slices); AGENTS + PROJECT_MAP links; online + Amiga. |
| 2026-06 | **Hub ledes (online)** — Activity, Games, Leaderboards, Milestones (`milestones.php`). |
| 2026-06 | **Online HoF table cols** — Activity + Performance share col 1 width via PHP label register + `--k2-hof-label-col-ch` (`records_hof_table.php`). |
| 2026-06 | **Online HoF hub chapter** — rules block as `k2-hub-chapter__list` (en-dash bullets); optional `$k2HubChapterList` on hub chapter include. |
| 2026-06 | **Amiga WC tournament nav** — top tabs Event stats · Stages · Games; group/bracket sub-tabs under Stages (`?view=stages`). |
| 2026-06 | **Amiga tournament Games tab** — `?view=games`; player dropdown; `idx_amiga_games_tournament` scoped read. |
| 2026-06 | **Docs** — `amiga-surface-expansion-overview` §3.4/3.5: Perf. rating on tournament event-stats marked shipped (removed stale “not in v1”). |
| 2026-06 | **Amiga standings scope slice 7 / track complete** — policy **Implemented**; data contract + honours Tier B/C + README + feature-log; starter prompt COMPLETE. |
| 2026-06 | **Amiga standings scope slice 6** — full `replay` (~22s) + verify suite all OK; standings 5544 league + 2320 knockout; parity sweep FAIL=0. |
| 2026-06 | **Amiga standings scope slice 5** — parity/verify tooling `league`; honours test fixture; grep clean (PHP zero hits; `standings_parity` CLI labels only); catalog-stats + verify-participation OK. |
| 2026-06 | **Amiga standings scope slice 4** — `tournament.php` + `amiga_tournament_lib` league readers; legacy scope 302; Athens 22/24 browser OK; STOP gate C. |
| 2026-06 | **Amiga standings scope slice 3** — `resolve_primary_league_standings()` Python/PHP; Tier B/C honours; 34 unit tests + participation-rebuild; spot 22/24/544 unchanged; STOP gate B. |
| 2026-06 | **Amiga standings scope slice 2** — PHP `AMIGA_SCOPE_TYPE_LEAGUE`; post-game standings + fixtures labels; ops verify OK; PHP t24 rebuild smoke. |
| 2026-06 | **Amiga standings scope slice 1** — Python `ScopeType.LEAGUE`; `tournament_phases`/`standings`/`catalog_stats` writers; parity derived reads; 25 unit tests OK; t24 rebuild smoke. |
| 2026-06 | **Amiga standings scope slice 0** — migration `020`: standings enum `league`\|`knockout`; 5544 league + 2320 knockout rows; `league_scopes` column; fresh `002`/`004` updated; STOP gate A. |
| 2026-06 | **Amiga standings scope unification — planning** — policy [`amiga-standings-scope-policy.md`](docs/amiga-standings-scope-policy.md); implementation plan slices 0–7; starter prompt for new chat (merge `overall`+`group` → `league`). |
| 2026-06 | **Amiga event finish slice 10 / track complete** — honours rules **Implemented**; docs closure; `feature-log` L1 row; migrations `017`–`019`. |
| 2026-06 | **Amiga event finish slice 9** — `amiga_tournament_finish_override` (019) + Tier E hook in Python/PHP derivation; unit tests OK. |
| 2026-06 | **Amiga event finish slice 8** — migration `018` drops `overall_position`; writers/tests/verify updated; full verify suite OK. |
| 2026-06 | **Amiga event finish slice 7** — UI reads `event_finish_position` (profile + player-tournaments + event-stats); STOP gate C for browser check. |
| 2026-06 | **Amiga event finish slice 6** — PHP placement parity + post-game participation writer (`event_finish_position`, `best_knockout_phase`); smoke 544 OK; verify suite OK. |
| 2026-06 | **Amiga event finish slice 5** — participation writer + honours totals rebuild (4517 rows); STOP gate B; legacy `overall_position` retained until slice 8. |
| 2026-06 | **Amiga event finish slice 4** — `derive_best_knockout_phase` (main bracket depth label); 7 unit tests + Bournemouth DB check. |
| 2026-06 | **Amiga event finish slice 3** — WC `wc_medal` shared semi bronze; no group-overall medal fallback; Tier D NULL finish. |
| 2026-06 | **Amiga event finish slice 2** — Tier B league+cup merge; placement-finals (3rd/5th/7th…) fix; Athens LXXXV DB test; STOP gate A. |
| 2026-06 | **Amiga event finish slice 1** — `derive_event_finish_position` Tier A (shared semi bronze) + Tier C; 14 unit tests; writer still legacy `overall_position`. |
| 2026-06 | **Amiga event finish slice 0** — migration `017`: `event_finish_position` + `best_knockout_phase` on participation; fresh `010` updated; writers unchanged. |
| 2026-06 | **Amiga event finish plan** — policy [`amiga-tournament-honours-rules.md`](docs/amiga-tournament-honours-rules.md) + agent slices 0–10 [`amiga-event-finish-implementation-plan.md`](docs/amiga-event-finish-implementation-plan.md) + starter [`amiga-event-finish-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md). |
| 2026-06 | **Amiga tournament honours LB** — dropped cup medal cols; order WC medals → played → won → podiums (last). |
| 2026-06 | **Amiga LB wing order** — Tournament honours tab second after Rating (`amiga_lb_nav.php`). |
| 2026-06 | **Amiga hub IA** — main hub tab **Ladder** → **Leaderboards**; retired redundant **Honours** top-level tab (tournament honours stays sub-wing). |
| 2026-06 | **Amiga rating LB** — removed hub intro lede above table. |
| 2026-06 | **Amiga LB default-sort fix** — five ladder wings: `data-k2-default-sort` decremented by 1 so JS sort highlight matches PHP `ORDER BY` (Elo, Scored, Peak, DDs, Victims). |
| 2026-06 | **Online LB anchor column** — all hub leaderboard wings (except Rating) use ELO col 2 as permanent `data-k2-anchor-col`; default sort unchanged per wing. |
| 2026-06 | **Amiga surface expansion complete (slices 0–8)** — profile feast v1, seven LB wings, H2H, event-stats, HoF deep links; docs + full verify suite pass; deferred → overview §4. |
| 2026-06 | **Amiga surface expansion slice 7** — honours LB cup medals + podiums columns; player-tournaments Cups/country filters; recent tournaments Winner + Perf suffix. |
| 2026-06 | **Amiga surface expansion slice 6** — profile Moments block from stats `*GameID` pointers (`amiga_player_moments_lib.php`); no streak card; peak game card pending `PeakRatingGameID` in replay. |
| 2026-06 | **Amiga surface expansion slice 5** — profile perf highlight + `/amiga/leaderboards/performance-rating.php` wing. |
| 2026-06 | **Amiga surface expansion slice 4** — tournament.php Event stats tab from participation (W-D-L, goals, rating, perf). |
| 2026-06 | **Amiga surface expansion slice 3** — profile top opponents goals + `/amiga/h2h.php` pair page; games.php opponent filter linked. |
| 2026-06 | **Amiga surface expansion slice 2** — HoF value cells deep-link to Tier A LB wings (`amiga_records_hof_links.php`). |
| 2026-06 | **Amiga surface expansion slice 1** — Tier A LB wings (Goals, DDs, Victims, Peak) + `amiga_lb_nav`; rating at `/amiga/leaderboards/rating.php`. |
| 2026-06 | **Amiga surface expansion slice 0** — profile honours strip from `amiga_player_tournament_totals` (WC medals, wins, podiums); [`amiga-surface-expansion-implementation-plan.md`](docs/amiga-surface-expansion-implementation-plan.md). |
| 2026-06 | **Online HoF spectacle links** — Performance single-game record values link to `games.php?view=highlights` boards (not Goals LB); `records_hof_links.php`. |
| 2026-06 | **Hub section chapter rollout** — `k2_hub_chapter.inc.php` + `.k2-hub-chapter` (1.25rem title); online Activity/LB/Milestones/HoF; Amiga LB/Tournaments/Live tournaments/HoF; placeholder lede except LB + Tournaments. |
| 2026-06 | **Amiga participation placement ladder** — games-driven roster (`participation_placement.py` + PHP parity); knockout cups + group/KO events without overall scope now appear on player tournament history; contract §5.2.2; run `participation-rebuild`. |
| 2026-06 | **Amiga derived-stat placement** — player-universe contract §5.0 (stored-truth policy, glossary, decision tree, placement matrix). |
| 2026-06 | **Amiga performance rating** — event TPR on `amiga_rating_events` + participation denorm; Perf. rating column on `/amiga/player-tournaments.php`; migration `015`; [`amiga-performance-rating.md`](docs/amiga-performance-rating.md). |
| 2026-06 | **Amiga participation points model** — `event_points` (full-event 3-1-0 from `amiga_games`); phase points stay in `amiga_tournament_standings` only; games rollup + WC finish/medal UI; migration `014`; contract §5.2.1. |
| 2026-06 | **Amiga finalize latency** — batch full replay ~23s; live one tail-end finalize ~0.7s local (network scan bound). |
| 2026-06 | **Amiga replay Tier A** — in-memory `players` across batch, defer stats + shared names; full replay ~23s (was ~90s / ~5½ min); live finalize unchanged. |
| 2026-06 | **Amiga replay perf** — `defer_heavy_derived` + `commit_heavy_player_derived()`; live finalize unchanged. |
| 2026-06 | **Amiga tournament finalize rating — closed** — slices 0–7 shipped; staging 24-part import verified (`ratings.kickoff2.com`); contract Implemented; rework pause lifted. |
| 2026-06 | **Amiga tournament finalize rating (slices 0–7)** — `amiga_rating_events`, Python replay + PHP live ops + read path + refinalize; export part 24; PHP `replay-to` removed. |
| 2026-06 | **Amiga hub nav v0** — `includes/amiga_hub_nav.php` (Ladder · Tournaments · Hall of Fame); `/amiga/hall-of-fame.php` stub; wired on rating/tournaments/tournament pages; [`amiga-realm-vision.md`](docs/amiga-realm-vision.md) inventory doc. |
| 2026-06 | **Amiga standings reference parity** — `standings-parity --sweep` (671 PASS, 0 engine FAIL); Silver/Bronze cup group label fix; report `data/amiga/exports/standings_parity_report.json`; contract § Known parity exceptions. |
| 2026-06 | **Amiga cup bracket UI** — knockout bracket on `amiga/tournament.php` (`amiga_tournament_bracket.php`, `amiga-tournament.css`); phase-ordered columns + placement sections; index Cup/League badges + filter; profile recent cups link `#bracket`. Read-path only via `amiga_tournament_lib.php`. |
| 2026-06 | **Box art story page** — `boxart.php` + `includes/boxart_story_section.php` + `stylesheets/boxart-story.css`; long-form, illustrated history of the KO2 cover (Cameron Buxton; Andy Gray foreground, Hugo Sánchez background; 6 Jun 2026 WhatsApp/Reddit sleuthing). Status heritage box now links to it (`status_room_section.php`; hover-only styles in `theme.css`). Images in `images/boxart/`. Content/cosmetic only. |
| 2026-06 | **Amiga ops simul v1** — `zero-derived`, `replay-to`, `verify` on `amiga/ops/`; sim chronology (next unrated in contract order); 500-game parity gate vs Python `replay --limit 500`; live `process-one` append-only unchanged. |
| 2026-06 | **Amiga ProcessCompletedGame v1** — `amiga/ops/` PHP post-game (ratings + player stats); CLI `run_process_game.php`; append-only chronology; parity vs `replay` on game 27408; contract + README updated. |
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
| 2026-06 | **Rodenbach II supplemental import** — Access catalog row had zero `Scores`; 10 forum-recovered round-robin games appended in `import_corrections.py` (ground truth **27,418** games). |
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
