# Session log archive — 2026 Q2

**Archived Jun 2026.** Older PROJECT_MEMORY.md Recent log rows (May–Jun 2026). For current focus see [../PROJECT_MEMORY.md](../PROJECT_MEMORY.md).

**Later prune (Jul 2026):** dated + month-only rows before 2026-07-01 → [`session-log-2026-jun-prune.md`](session-log-2026-jun-prune.md).

---

*(Newest first within this archive.)*

---

## Archived from PROJECT_MEMORY Recent log (Jun 2026 doc prune)

*(Newest first within this block.)*

| When | What |
|------|------|
| 2026-06 | **Profile moment scorelines** — Plex Sans 26px/600 (match burst cards; was Exo 2 display). |
| 2026-06 | **Profile played days** — section heading hidden; heatmap hint continues “The story so far” (anchor + sr-only title kept). |
| 2026-06 | **Profile “The story so far”** — lab 2 prose band shipped after At a glance (win/play streaks, opponents/victims, best year wins, distinct days); day/week streak 50/50 per page load. |
| 2026-06 | **Profile played-days → weeks gap** — section margin 18px + calendar bottom padding 16px (between original and tight pass). |
| 2026-06 | **Milestone garden/catalog cards** — unlocked border back to 1px (glow + title carry tier; spotlight stays 2px). |
| 2026-06 | **Player nav tab order** — Profile · Opponents · Milestones · Games (`player_nav.php`). |
| 2026-06 | **Player Games filter anchor** — `#k2-player-games-filters` above filter row; H2H chart clicks land there (min-height scroll pad + hash restore). |
| 2026-06 | **Opponents H2H filters** — picker sizing matches Games tab; chosen name in picking listbox only (search → games box); search focus red; pickers→hero gap halved. |
| 2026-06 | **Player Games nav→filters gap** — +16px margin-top on filter bar (28px total with nav bar; drill-down day-view matches). |
| 2026-06 | **Player Games drill-down chevrons** — half pill width (14px) gap between prev/next in day/period banner. |
| 2026-06 | **Player Games page chevrons** — status-row page nav shifted right one pill width (player table-meta only). |
| 2026-06 | **Player Games drill-down chrome** — 500-row pages; split context banner vs table-meta; hide filter bar + reset in day/period/streak drill-down; page chevrons only when drill-down total > 500. |
| 2026-06 | **Player Games status row** — chevron page nav + **Reset filters** in status row (All games parity); reset removed from filter bar. |
| 2026-06 | **Player Games filter link-star** — active Result/Opponent/GF/GA/Sum listbox triggers use `k2-link-star` ink (All games parity). |
| 2026-06 | **Player Games filter blanks** — default Result/Opponent/GF/GA/Sum listboxes show empty trigger + blank first dropdown row (All games parity); `min-height` on player-games triggers so empty state does not collapse. |
| 2026-06 | **Filter field labels** — shared `--k2-filter-field-label-color` (All games dim style) on player Games + Opponents H2H pickers. |
| 2026-06 | **Profile played-days future tiles** — ghosted cells (`--future`) vs full empty fill for past missed days; grid kept through year end. |
| 2026-06 | **Profile played-weeks hint** — “Since {first game date}, {name} has played in {N} different weeks.” (distinct from days copy). |
| 2026-06 | **Profile played-days copy** — year status line: “enjoyed N days of online Kick Off 2” (was “played on N rated days”). `player-calendar.js`. |
| 2026-06 | **Header brand spacing** — realm switcher gap beside wordmark doubled (`k2-site-header__brand` 16px → 32px). |
| 2026-06 | **Player display names** — site UI always shows `playertable.Name` (by ID); `ratedresults` snapshots unchanged. Helper `k2_player_display_names.php`; rename report `scripts/oneoff/player_name_renames_report.php`. Search aliases / “formerly known as” deferred. |
| 2026-06 | **Tint schedule reordered** — midnight slot is now Holo (was Amber); order holo → pitch → chrome → amber. `k2-tint-schedule.js` + `tint-vs-realm.md`. |
| 2026-06 | **H2H moments v2 shipped** — neutral shells; muted kickers; holo draws; full poster neon on goal digits only. `player-opponents-h2h-moments.css`. |
| 2026-06 | **Hub chapter titles** — accent+glow trial **reverted**; keep primary white: sole orientation landmark, avoids link-star/tint collision; lede stays editorial. |
| 2026-06 | **Streaks LB nav fix** — `leaderboards/streaks.php` used `hub_nav.php` + single `lb_nav.php` (was duplicate wing chrome with Rating active). |
| 2026-06 | **League period sort** — scoped `k2_sort` per table (`league-standings` / `league-games`); fixes games table picking standings column on load. |
| 2026-06 | **Rating chart API** — `player_rating_history.php` + `player_compare_rating_history.php` omit unprocessed `ratedresults` (`NewRatingA IS NULL`); fixes zero spikes on work/staging simul DBs; tail still from `playertable.Rating`. |
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
| 2026-06 | **Amiga tournament structure slice 6** — `materialize-tier-b-non-wc` bulk CLI; dry-run **41/41** OK — handoff [`018`](docs/archive/orchestration/agent-handoffs/2026-06-13-018-amiga-tournament-structure-slice-6-curation.md). |
| 2026-06 | **Amiga tournament structure slice 5** — `materialize-tier-a` bulk CLI; dry-run **503/503** OK — handoff [`017`](docs/archive/orchestration/agent-handoffs/2026-06-13-017-amiga-tournament-structure-slice-5.md). |
| 2026-06 | **Amiga tournament structure slice 6 CLI** — `materialize-tier-b-non-wc` dry-run 41/41; 38 tests; GATE E pending apply. Review track: [`REVIEW-STARTER`](docs/archive/orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md). |
| 2026-06 | **Amiga tournament structure slice 4** — `verify-legacy` CLI (fixture integrity + optional standings parity); `audit-inventory` tier A/B/C/D; GATE B′ passed. |
| 2026-06 | **Amiga tournament structure undo/resume** — handoff [`015`](docs/archive/orchestration/agent-handoffs/2026-06-13-015-amiga-tournament-structure-undo-and-resume.md); slice 3 pilot void; Athens IV local standings restored; resume slice 4. |
| 2026-06 | **Amiga tournament structure slice 3b (policy v2)** — RR scope + KO tie modules; tier-A auto only; `dematerialize`; Athens IV dematerialized; handoff [`014`](docs/archive/orchestration/agent-handoffs/2026-06-13-014-amiga-tournament-structure-slice-3b.md). |
| 2026-06 | **Amiga tournament structure slice 3** — ~~pilot~~ **superseded** — handoff [`012`](docs/archive/orchestration/agent-handoffs/2026-06-13-012-amiga-tournament-structure-slice-3.md). |
| 2026-06 | **Amiga tournament structure slice 2** — builders, Homburg spec, build/verify helpers, link side-parity doc, browser ops `round_robin`; Homburg verify OK (86/86); handoff [`2026-06-13-011-amiga-tournament-structure-slice-2.md`](docs/archive/orchestration/agent-handoffs/2026-06-13-011-amiga-tournament-structure-slice-2.md). |
| 2026-06 | **Amiga tournament structure slice 1** — migration `023` (`round_robin`/`knockout` stage enum); `_fixture_scope` + PHP parity; `VALID_STAGE_TYPES`; 13 stages migrated (8 RR, 5 KO); STOP GATE A — handoff [`2026-06-13-010-amiga-tournament-structure-slice-1.md`](docs/archive/orchestration/agent-handoffs/2026-06-13-010-amiga-tournament-structure-slice-1.md). |
| 2026-06 | **Amiga tournament structure policy v2** — RR scope + KO **tie** modules; rounds in StructureSpec; NULL auto RR only when full schedule else flag; slice 3 pilot superseded; restart handoff [`013`](docs/archive/orchestration/agent-handoffs/2026-06-13-013-amiga-tournament-structure-restart-handoff.md). |
| 2026-06 | **Amiga tournament structure track — planning** — policy [`amiga-tournament-structure-policy.md`](docs/amiga-tournament-structure-policy.md); implementation plan slices 1–9; starter [`amiga-tournament-structure-STARTER-PROMPT.md`](docs/archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md) (modules vs structure; game-authoritative legacy backfill; migration `023` next). |
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
| 2026-06 | **Amiga event finish plan** — policy [`amiga-tournament-honours-rules.md`](docs/amiga-tournament-honours-rules.md) + agent slices 0–10 [`amiga-event-finish-implementation-plan.md`](docs/amiga-event-finish-implementation-plan.md) + starter [`amiga-event-finish-STARTER-PROMPT.md`](docs/archive/orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md). |
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

| When | What |
|------|------|
| 2026-06 | **Doc hygiene** — `PROJECT_MEMORY` trim → `docs/archive/session-log-2026-q2.md`; completed handoffs + milestones brainstorm → archive stubs; PHP ops = cutover narrative; Phase 1.5 handoff restored active. |
| 2026-06 | **Header realm switcher removed** — markup/CSS deleted; `realm-switch.js` tint-only; hub IA docs updated. |
| 2026-06 | **Status leagues period menu** — Day/Week/Month/Year selector now uses the shared `k2-chrome-tabs` segment track with compact milestone-style density. |
| 2026-06 | **Wordmark bloom softened** — Kick Off 2 neon keeps its street-sign feel with reduced outer haze and hover flare. |
| 2026-06 | **Status heritage glow** — right-side KO2 box art gets a clipped warm tint halo/rays inside the inset, balancing the wordmark glow. |
| 2026-06 | **Status heritage inset** — dark well + muted art; tint backlight removed for fresh pass. |
| 2026-06 | **Self-hosted fonts** — Google Fonts removed; `fonts/*.woff2` + `k2-fonts.css` + preload in `k2_fonts_head.php`; audit `docs/self-hosted-assets.md`; regen `scripts/sync_self_hosted_fonts.ps1`. |
| 2026-06 | **Player hero links** — name → Profile; rank/rating → `leaderboards/rating.php`; games → Activity peaks all-time (`leaderboards/activity-peaks.php#k2-peak-period-all-time`); neutral pointer-only stat/name links. |
| 2026-06 | **Player DDs tab (`individual2c`)** — hub `ranked3` headers/tooltips + column order; calm-stats; Games anchor. |
| 2026-06 | **Player Goals tab (`individual2b`)** — Win/Loss margin SQL fixed (CASE on outcome, not MAX of signed diffs); hub LB headers; Games anchor; Draw/Least display fixes. |
| 2026-06 | **Player Games polish (`player/games.php`)** — calm-stats table ink, shared `k2-archive-listbox` filters (Status Leagues parity), default sort id desc, server-side `k2-table-col-sorted`; win/loss blue/red kept. |
| 2026-06 | **Status Leagues — Daily games list** — under Activity + Points when **Daily** tab active; recent-games layout + `game.php` id link; `k2_status_rated_games_for_calendar_day` + `api/status_period_day_games.php`. |
| 2026-06 | **Activity bar animation** — **off** (`ACTIVITY_BAR_ENTRANCE_ENABLED` in `chart-theme.js`); grow-up WIP (stutter). |
| 2026-06 | **Activity heatmap months** — month row uses `grid-column: span N` per month (full “Jan”, not ellipsis in one week column). |
| 2026-06 | **Activity heatmap layout** — cells scale to panel width (`ResizeObserver` + CSS vars); taller/wider on desktop; min 8px + horizontal scroll on narrow viewports. |
| 2026-06 | **Activity bar animation on phone** — same grow-up as desktop (~420ms); scroll policy unchanged (no tooltips / no touchstart). |
| 2026-06 | **Activity bar animation fix** — grow from y-axis baseline (`getPixelForValue`), not canvas top; stacked year chart uses stack foot. |
| 2026-06 | **Activity charts L4 (partial)** — desktop bar grow-up (`chartKind: 'bar'` in v2); lines unchanged; phone still no animation. |
| 2026-06 | **Activity busiest-day card** — summary stat order: Rated games (label) → count → Busiest day · date (note). |
| 2026-06 | **Activity highlights panel width** — `.server-activity-summary` uses `--k2-max-width` (1200px), not chart 960px cap. |
| 2026-06 | **Activity charts v2 L3** — `activity.php` ships v2 only; legacy 12 JS files deleted; `server1-charts-lab.php` → redirect; `body.k2-activity-charts` + `server_activity_chart_panels.php`. |
| 2026-06 | **Activity charts phone touch** — coarse: panel + canvas `touch-action: pan-y pinch-zoom` (scroll + pinch; `pan-y` alone blocked zoom); Chart.js tooltips off on phone; heatmap tooltips desktop-only. |
| 2026-06 | **Chart `T.amber()`** — returns resolved `amberSoft()` rgb so Chart.js never gets unresolved `color-mix` vars (fixed black line on top-10 chart for LORENZOL). |
| 2026-06 | **Daily active players chart** — calendar 30-day rolling mean (gap days = 0); explicit smooth line in v2 + legacy `server-daily-active-players-chart.js`. |
| 2026-06 | **Activity charts v2 L2** — all 12 panels in `activity-charts-v2.js`; `server_activity_chart_panels_lab.php`; lab loads `chart-date-range.js`; production unchanged (legacy). |
| 2026-06 | **Milestones Recent density** — feed typography tuned to **12px / 1.4**, tighter **7px** row padding, and narrower date column (`player-milestones.css`). |
| 2026-06 | **Revert site-wide mobile experiments** — removed viewport meta, hub scroll CSS, canvas stretch; lab CSS scoped to `body.k2-activity-charts-lab` only; `activity.php` canvases 960×271 restored. |
| 2026-06 | **Activity charts lab UX** — tap link `activity.php` ↔ lab; no lab banner; lab summary full column width, charts still 960px frame. |
| 2026-06 | **Activity charts v2 L1 lab** — `server1-charts-lab.php` + `activity-charts-v2.js` (games/day); `.k2-chart-frame` CSS; summary → `includes/server_activity_summary.php`; removed canvas `%` stretch rules. |
| 2026-06 | **Activity charts v2 plan** — [`docs/activity-charts.md`](docs/activity-charts.md): single module, lab → promote, panel registry + parity checklist. |
| 2026-06 | **Activity charts fix** — `activity-charts.js` boot only on `DOMContentLoaded` (first chart was skipped when boot ran before defer modules registered). Mobile: viewport meta in `k2_head.php`, chart `touch-action: pan-y`, fluid canvas width (removed 960px attrs), header/hub `min-width: 0`. |
| 2026-06 | **Activity charts rewrite** — `chart-theme.js` slim (colours + tooltips + `activityChartOptions` only; no global `Chart.defaults` / touch plugin / `createBarChart`). `activity-charts.js` loads panels **sequentially** (~100ms gap). All Activity modules register with `K2ActivityCharts`; heatmap + play texture + busiest players **re-enabled** on `activity.php`. Mobile: `animation: false`; busiest chart skips hover highlight on coarse pointers. |
| 2026-06 | **Fix** — restored missing `prefersReducedMotion()` in `chart-theme.js` (broke all Activity charts with “Could not load…”). |
| 2026-06 | **Activity chart interaction** — desktop bar grow restored even when browser reports reduced motion; mobile chart touch is tap-based (`touchstart`/`click`, no `touchmove`) + `touch-action: manipulation`. |
| 2026-06 | **Cumulative established tooltip** — body line only: `Total established: N` (removed afterLabel explainer). |
| 2026-06 | **Activity charts** — `createBarChart` (y=0 then update); `k2TouchPointer` plugin; no viewport IO. |
| 2026-06 | **Chart tooltips** — `T.mergeTooltip()` + dark `--k2-tooltip-surface`; swatch boxes use `multiKeyBackground` + solid `labelColor` (no white inside); heatmap uses `.k2-table-tooltip` DOM. |
| 2026-06 | **Activity summary** — **Busiest day** stat (PHP, `server_period_game_totals`); removed Recent milestones panel + digest API/JS. |
| 2026-06 | **Most games played chart** — hover highlight fix (`dataset` mode + opacity dim; tooltip still index-by-month). |
| 2026-06 | **Most games played chart** — trailing 6-month rolling average; fixed top 10 by `NumberGames` (tie → lowest ID). |
| 2026-06 | **Activity layout** — All-time busiest players + Play texture are last on `activity.php` (after established rating distribution). |
| 2026-06 | **Activity tab** — removed **Goals per month** chart + `server_goals_by_month.php` / `server-goals-month-chart.js`. |
| 2026-06 | **Activity layout** — daily activity heatmap is chart **#4** on `activity.php` (after games day / month / year). |
| 2026-06 | **`milestone.php` Graphs** — rating distribution charts removed for DD Merchant + Established (year + cumulative only); established rating distribution stays on `activity.php` Activity. |
| 2026-06 | **Activity cleanup** — deleted APIs/JS for participation depth + all three DD merchant Activity charts. |
| 2026-06 | **Activity tab** — dropped **Participation depth by month** chart from `activity.php` (redundant vs active players / games per month). |
| 2026-06 | **Activity copy** — unique matchups chart hint: “social breadth of the community” (`activity.php`). |
| 2026-06 | **Activity tab** — removed three Double Digit Merchant charts from `activity.php` (new/cumulative per year, rating distribution); established-player charts + milestone digest unchanged on Activity. |
| 2026-06 | **Status rated-games arc** — removed **Activity →** link from arc panel (`status_room_section.php`); Activity remains hub tab on `activity.php`. |
| 2026-06 | **Activity peaks sub-nav** — `leaderboards/activity-peaks.php` Calendar / All time uses **`k2-chrome-tabs`** segment track; removed duplicate top margin (was wing gap + mode bar margin). |
| 2026-06 | **League honours sub-nav** — `leaderboards/league-honours.php` cup/grain filters use **`k2-chrome-tabs`** segment track (matches Activity peaks / milestones / games highlights), not Status leagues period pills. |
| 2026-06 | **`milestone.php` achiever match lines** — Event scoreline uses official **team A · GoalsA–GoalsB · team B** (`ratedresults` order), not unlocker-first. |
| 2026-06 | **`games.php` games tables** — calm-stats body ink; Recent default sort ID desc; empty-day row stays secondary (`k2-table.js` skips colspan sorted-col). |
| 2026-05 | **`daily_habit` rule copy** — Rule (short): “Rated game every day Monday to Sunday” (copy patch + local `milestone_definitions`). |
| 2026-06 | **`game.php`** — leaderboard **`k2-table--calm-stats`** body ink only; hub tabs removed (detail page stays header + table). |
| 2026-06 | **Games Highlights** — `games.php` sub-nav **Recent** \| **Highlights**; board order Most goals → Biggest draws → One-side peak → Biggest wins; ties **lower game ID first** (SQL + `data-k2-sort-tie-value`). |
| 2026-05 | **`milestone.php` Graphs typography** — chart titles use **`k2-panel-heading`**; block hints + legend labels use **`--k2-text-secondary`**. |
| 2026-05 | **`milestone.php` polish** — removed catalog footer line; Graphs blocks use Activity-style **`--k2-chart-max-width` (960px)** boxed charts. |
| 2026-06 | **Staging `perfect_day` / `nightmare_day` day-close** — Steve SQL + Dagh browser smoke **done**: **113** rows midnight UTC; Recent **`00:00`**; garden **Games** → `player/games.php?day=`; total **6620**. Handoff: [`milestones-staging-cutover-packet.md`](docs/coordination/milestones-staging-cutover-packet.md) § Day-close. |
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
| 2026-05 | **Streaks LB headers** — `leaderboards/streaks.php`: Wins, Undefeated, Draws, Decided, Losses, Win drought, Days, Weeks (no “streak” in labels). |
| 2026-05 | **Hub LB padding reset** — all `ranked-pages-table` wings: uniform 8px in CSS; stripped `k2-table-cell--pad-left-*` from ranked1–7,10 + league honours (Goals/DD/CS already clean). |
| 2026-05 | **LB column padding** — removed legacy `k2-table-cell--pad-left-*` on Goals + DD/CS wings (`ranked2`/`ranked3`); was widening cols after full-word headers. |
| 2026-05 | **DD/CS LB headers** — `leaderboards/double-digits.php`: Double Digits, Clean Sheets, DD conceded, CS conceded; ratio cols still abbreviated; footer removed. |
| 2026-05 | **k2-table sort toggle** — same-column click re-sorts asc/desc (was DOM reverse only); fixes second click not reaching true descending order. |
| 2026-05 | **Goals LB headers** — `leaderboards/goals.php`: Scored/Conceded, Most Scored/Most Conceded, Draw/Goal sum, Win/Loss margin; footer trimmed. |
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
| 2026-05 | **Hub IA — Milestones tab + Games off hub** — `hub_nav.php`: Status · Activity · Leaderboards · **Milestones** · HoF; `milestones.php` stub; `games.php` via Status only; WIP [`docs/milestones-hub-ia.md`](docs/milestones-hub-ia.md). |
| 2026-05 | **Leaderboards wing order (scenario A)** — `lb_nav.php`: classic block unchanged (Rating→Victims), then League honours · Milestones · Activity peaks, **Peak rating** last (`ranked1`); hub default still `leaderboards/rating.php`. |
| 2026-05 | **Milestones LB polish** — `leaderboards/milestones.php` + League honours: **ELO rating** header + `k2-table-cell--pad-left-sm` on Games (matches classic ranked tables). |
| 2026-05 | **`giant_slayer` rule fix** — active #1 (365d rolling UTC); `milestone_giant_slayer.py` + chrono regen; surgical `player_milestones_rebuild_giant_slayer.sql`; contract post-game §; holders 22→31 (geo4444 unlocks). |
| 2026-05 | **Milestones Phase 4 v0** — garden page, profile `{n}/110` glance + tier dots, `leaderboards/milestones.php` meta-leaderboard, `hall-of-fame.php` DD Merchant achiever trial; read-only on local DB. |
| 2026-05 | **Games hub (`games.php`)** — day headings for days older than yesterday show weekday + date (`Monday · May 26, 2026`); Today/Yesterday unchanged. |
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
| 2026-05 | **League honours views** — `leaderboards/league-honours.php` pills Overall / Activity / Points + Day–Year; URL `cup` & `grain`. |
| 2026-05 | **League honours v1** — `leaderboards/league-honours.php` wing; spec [`docs/leagues-career-leaderboard-proposal.md`](docs/leagues-career-leaderboard-proposal.md). |
| 2026-05 | **Activity league uncapped on Status** — all players with ≥1 game shown; `limit=0` default in API/SSR. |
| 2026-05 | **Rating wing anchor** — `leaderboards/peak-rating.php`: Peak (col 4) is link-star anchor; current Elo is neutral like other columns. |
| 2026-05 | **Status league cross-tint anchors** — `k2-table--league-anchor-cross`: Games/Pts use `--k2-league-anchor-ink` (chrome on amber/pitch tint, pitch on chrome/holo), not `--k2-link-star`. |
| 2026-05 | **Status league calm-stats fix** — `status-period-competitions.js` rebuilds league HTML client-side; matched PHP calm-stats/anchors + `window.k2TableApplyAnchors` after inject/cache restore. |
| 2026-05 | **Calm-stats site-wide (hub tables)** — `k2-table--calm-stats` + anchors on ranked8 activity peaks, Status league tables, `hall-of-fame.php` record values; `initAnchorTables()` for non-sortable tables. Profile `individual2a/b/c` unchanged. |
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
| 2026-05 | **Top activity eras chart shipped locally** — `activity.php` now has a "Top activity eras" multi-player line chart: each month shows the top 10 players by rated games, lines appear/disappear as players enter/leave the top 10, hover highlights one player and dims others; powered by new `api/server_top_activity_eras.php` reading `player_period_games` (L0, no new stored truth). |
| 2026-05 | **Realm header identity layout promoted** — shared `site_header.php` now uses the first lab direction: Online/Amiga beside the Kick Off 2 wordmark, with player search isolated on the right; strip variant remains lab-only for comparison. |
| 2026-05 | **Stored truth performance policy added** — agent instructions now say DB-backed features should actively consider indexes, aggregate tables, replay outputs, `playertable` fields, periodic jobs, and post-game C++ updates as normal options for hot stats/profile/achievement work, not burdens to avoid. |
| 2026-05 | **Ranked8 phone activity layout fix** — Calendar and All time activity tables now keep their intended two-column layout below tablet widths, with horizontal overflow only if a very narrow viewport needs it. |
| 2026-05 | **Period activity staged preview unblocked** — `dev-period-activity.php` now permits the staging host (`ratings.kickoff2.com`) while remaining host-guarded elsewhere; page copy now says dev/staging preview. |
| 2026-05 | **Status panel action-link alignment** — the active leaderboard `Leaderboards →` link now uses the same compact Status action styling as `Activity →` and `Games →`. |
| 2026-05 | **Activity Graph Roadmap shipped** — five new Activity features: 12-month daily heatmap (GitHub-style), participation depth stacked bars (1/2-4/5-9/10+ bands), play-texture small-multiples (goals/game, draw %, DD/100, CS/100), unique matchups per month, and a recent milestone digest card; all L0 read-time from `ratedresults`+`playertable`. |
| 2026-05 | **Double Digit Merchant charts** — Activity now has a read-time chart trio for first 10+ goal games: new merchants by year, cumulative merchants, and merchant rating distribution; data is derived from `ratedresults`, not stored on `playertable`. |
| 2026-05 | **Activity copy sharpened** — `activity.php` no longer says "server" in user-facing chart headings/status/aria copy; the past-month daily games chart now shows the same `Games` legend chip as the longer-horizon charts. |
| 2026-05 | **Tooltip microcopy audit** — redundant chart helper under the Activity daily chart removed; table/header tooltip copy now favors abbreviation definitions, formulas, and contextual rules while obvious labels fall back to the shared `Click to sort.` affordance; tint picker native hover titles are removed. |
| 2026-05 | **Chart semantics pass** — chart colors now follow a first-pass vocabulary: pitch = games/wins/profile subject, amber = goals, chrome = active players/projections/opponent focus, holo = cumulative history, magenta = milestones, teal = distributions; dense monthly bars stay borderless. |
| 2026-05 | **Activity recent daily chart** — `activity.php` now opens its chart stack with a past-month games-per-day bar chart from `api/server_games_by_day_recent.php`, including zero-game days. |
| 2026-05 | **Hub nav reordered** — top nav is now `Status · Activity · Games · Leaderboards · Hall of Fame`, frontloading life/evidence before competition and records. |
| 2026-05 | **Status leaderboard sorting** — Status active leaderboard now loads `k2-table.js` for sortable Rank/Player/Elo/Games columns with compact header help, autorank on resort, `past year` heading copy, and `Leaderboards →` destination meta. |
| 2026-05 | **Game table tooltips** — `games.php` keeps all-column header popups and `game.php` mirrors them as non-sortable help; deep Elo explanation lives on `Fav ES` and visible `Adjustment`. |
| 2026-05 | **Activity summary completes legacy stats merge** — `activity.php` now folds the old Overall Server Stats table into a key sentence, four fact cards (goals/draws/DD/CS), and a quiet games/opponents line before charts. |
| 2026-05 | **Status arc → Activity landing** — Status rated-games arc links to `activity.php` with a discreet left-aligned action below the sentence; Activity opens with the all-time activity story before the historical charts. |
| 2026-05 | **Table spacing cleanup + Games detail path** — inline table `&nbsp;`/`text-align` hacks removed from ranked/player/server/game table families in favor of `theme.css` utilities; `games.php` now shows 14 day buckets with fully sortable game tables (`GD`, `Elo Diff`, `Fav ES`, `Adjustment`), and Status recent games links to the full Games list via `Games →`. |
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
| 2026-05 | **Activity / Hall of Fame / Records polish** — `leaderboards/activity-peaks.php` period/all-time activity tables, `hall-of-fame.php` two-panel Hall of Fame split, peak-period aggregate fallback, and natural-width table polish are in repo. |
| 2026-05 | **Games tab shared row renderer** — `game.php` and the Games tab share `includes/k2_rated_game_row.php`; current Games tab behavior is recorded in the newer table-spacing cleanup row above. |
| 2026-05 | **Status Phase B v1.2 in repo** — `status.php` has 4-col room grid, active leaderboard, monthly league toggle, recent logins/registrations/games; prod DB read + joshua redirect still open. |
| 2026-05 | **Profile feast shipped** — production `player/profile.php` feast layout only; mock lab removed; further profile work should be gradual copy/UX. |
| 2026-05 | **Core migration/prod coordination set up** — `prod-coordination.md`, registers, schema migrations, staging replay docs; prod post-game from `website-data-contract.md`; prod live ratings still C++. |
| 2026-05 | **Chart/theme foundation shipped** — six-ink chart palette, dark theme tokens, shared header/nav/wing tabs, and `status.php` hub landing are in repo. |
