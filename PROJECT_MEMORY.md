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

- **Status Leagues:** **Shipped** — [`status-period-competitions.md`](docs/status-period-competitions.md). Meta line: **Week 49, 2025 League** order; period label + rated-games count both `.blue`.

- **Profile (online):** **Complete** — production feast on **`player/profile.php`**; no active build track; spec [`player-profile-feast.md`](docs/player-profile-feast.md). **Amiga profile:** gradual polish only — [`amiga-profile-v0.md`](docs/amiga-profile-v0.md). Multi-agent lab sandboxes removed Jun 2026 (handoff archived).

- **Design / Status hub:** Phase B v1.2 room grid shipped. Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](docs/activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones · **Games** · HoF · Play & Setup — [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). **Navigation invariants** (hub bar always present; active pill only on places; **entity pages** at realm root, no pill; singular=entity / plural=hub) — [`navigation-model.md`](docs/navigation-model.md) NM1–NM6. **Games hub (Jun 2026):** `games/recent.php` + Highlights + **All games** vault (filters, server sort). **URLs:** semantic paths + `games/` + `milestones/` folders; registry [`k2_routes.php`](site/public_html/includes/k2_routes.php) — [`url-routes.md`](docs/url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Operational loop:** edit locally → **WinSCP** sync `site/public_html/` → staging; hard refresh. Steve runs server one-offs.

- **Local:** `ratingskickoff.test` (dev) + `work.ratingskickoff.test` (work) — [`LOCAL_DEV.md`](docs/LOCAL_DEV.md).

- **Change style:** small, reversible slices.

- **Amiga realm (Jun 2026):** **Games hub shipped** — `/amiga/games/{recent,highlights,all}.php`; TT-sensitive; filters on All games deferred. **Disposition review** — register **605/605**; **44** `pending_review`; [`disposition-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

- **Amiga rating history (Jun 2026):** **V1** — History hub + time-travel rating LB; News tab = blank placeholder; [`amiga-rating-history-policy.md`](docs/amiga-rating-history-policy.md).

- **Amiga event snapshots (Jun 2026):** **Complete (slices 0–9)** — `amiga_player_event_snapshots` + `amiga_player_current`; legacy four tables retired; holy loop `python -m scripts.amiga prove` green. Policy [`amiga-event-snapshot-policy.md`](docs/amiga-event-snapshot-policy.md).

- **Amiga matchup at event (Jun 2026):** **Complete (slices 0–6)** — `amiga_player_matchup_at_event` + finalize-only network/peaks/H2H; replay tail batches removed. Policy [`amiga-matchup-at-event-policy.md`](docs/amiga-matchup-at-event-policy.md). HoF → [`amiga-realm-snapshot-policy.md`](docs/amiga-realm-snapshot-policy.md).

- **Amiga per-opponent performance rating (SCH-044, Jun 2026):** **Complete** — cumulative directed pair TPR stored on `amiga_player_matchup_summary` + `amiga_player_matchup_at_event` (`performance_rating`), recomputed only for pairs played each event at finalize (Python replay = in-memory samples; PHP/live = reseed touched pairs from `amiga_game_ratings`). Surfaced as the **Perf.** column on Opponents W/D/L and read by H2H pair detail (no on-the-fly solve); time travel = latest at-event row ≤ cutoff. `verify-player-matchups` perf oracle; `replay` + verify green. [`amiga-performance-rating.md`](docs/amiga-performance-rating.md) · [`amiga-matchup-at-event-policy.md`](docs/amiga-matchup-at-event-policy.md).

- **Amiga realm snapshots (Jun 2026):** **Complete (slices 0–8)** — incremental finalize + `amiga_realm_snapshots` timeline; HoF peak row = read-time `PeakRating` (retired `BiggestPeakRating` Jun 2026); `prove` green ~5 min. Policy [`amiga-realm-snapshot-policy.md`](docs/amiga-realm-snapshot-policy.md).

- **Amiga HoF calendar-year + geography (Jun 2026):** **Complete** — eight new HoF rows + Calendar & geo LB wing; SCH-028 on snapshots/current + `generalstats`; `verify-hof-geo-year` in `prove`. Policy [`amiga-hof-tournament-geo-policy.md`](docs/amiga-hof-tournament-geo-policy.md).

- **Amiga HoF record rise dates (Jun 2026):** **Complete (SCH-029, slices 0–8)** — per-metric `*_last_rise_*` on snapshots/current; HoF `*Date` from rise not participation; Python + PHP finalize parity; `verify-hof-geo-year` date oracle. [`amiga-hof-record-date-policy.md`](docs/amiga-hof-record-date-policy.md).

- **Amiga career HoF rise dates (Jun 2026):** **Complete (SCH-030)** — ten legacy career rows (`MostGamesPlayed` … `BiggestRatingAscent`) get `*_last_rise_*` on snapshots/current; HoF `*Date` from event where scalar last rose; `verify-hof-geo-year` extended (32 rise cols + 18 HoF dates); `prove` green. Plan [`amiga-hof-career-rise-implementation-plan.md`](docs/amiga-hof-career-rise-implementation-plan.md).

- **Amiga stored id/date semantics Phase B (Jun 2026):** **Complete** — `verify_hof_holder_projection` in `prove` (career source-field dates, game-anchored + ratio oracles). Manifest [`amiga-stored-field-semantics.md`](docs/amiga-stored-field-semantics.md); plan [`amiga-stored-field-semantics-plan.md`](docs/amiga-stored-field-semantics-plan.md).

- **Amiga stored id/date semantics Phase C (Jun 2026):** **Complete** — `verify_stored_id_date_pairs` in `prove` (rise FK pairing, honours_last / last participation, career-best replay).

- **Amiga stored id/date semantics Phase D (Jun 2026):** **Retired with refinalize** — `verify-php-finalize-parity` removed Jun 2026 ([`archive/retired-amiga-refinalize-2026-06.md`](docs/archive/retired-amiga-refinalize-2026-06.md)); batch `*-rebuild` CLIs retired same era ([`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md)). Phases A–C (`verify-hof-holder-projection`, `verify-stored-id-date-pairs`, manifest) remain in `prove`.

- **Amiga ground layers L0–L5 (Jun 2026):** Slices **1–11 complete** — strict stack shipped (`prove` L1→L5, `verify-l2-l3`). [`amiga-ground-stack.md`](docs/amiga-ground-stack.md).

- **Amiga time travel (Jun 2026):** **Phase 1 complete** — header **Present day | Time travel** + one-row ribbon above hub when active; LB (8 wings), HoF at cutoff; profile present-only. Smoke: `scripts/oneoff/amiga_time_travel_smoke.php`. [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md).

- **Amiga time travel (Jun 2026):** **T13–T19** — snapshot-only TT hub; **with-player track complete** — **`as_with=`** on TT ribbon **Year/Month/Event** (+ preamble snap), **`id_with=`** + **`id_country=`** (tournament chevrons + page-entry snap), **`start_with=`** (league periods + bootstrap snap). [`with-player-stepper-policy.md`](docs/with-player-stepper-policy.md) §10 module map.
- **Amiga Opponents wing (Jun 2026):** **W/D/L · Goals · DDs + H2H (slices D+F) shipped** — poster/pickers/pair detail/moments/charts on `amiga/player/opponents/h2h.php`; Amiga `realm=` API branches + event-step rating compare. Policy [`amiga-opponents-wing-policy.md`](docs/amiga-opponents-wing-policy.md). **Country grain (Jun 2026):** **OCG-1–OCG-7 complete** — roll-up + read-time country TPR; country **W/D/L · Goals · DDs** tables; country **H2H** (poster/pickers/detail/moments/game charts, no rating/rank compare); API `opp_country` + chart JS grain — [`amiga-opponents-country-grain-policy.md`](docs/amiga-opponents-country-grain-policy.md) · [`amiga-opponents-country-grain-implementation-plan.md`](docs/amiga-opponents-country-grain-implementation-plan.md).

- **Amiga World Cups LB (Jun 2026):** **V2 UI** — five sub-wings on **World Cups hub → Player stats** only; LB wing **retired** Jun 2026 (legacy URLs 302). Writers proven Jun 2026-23. [`amiga-world-cups-leaderboard-policy.md`](docs/amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cups-player-slice-v2-policy.md`](docs/amiga-world-cups-player-slice-v2-policy.md).

- **Amiga WC HoF (Jun 2026):** **Complete (WCH-1…8, SCH-046)** — 28 WC record rows; sparse `amiga_wc_hof_{snapshots,present}` + HoF UI block + time travel; Python + PHP finalize parity; `prove` green. [`amiga-wc-hof-policy.md`](docs/amiga-wc-hof-policy.md) · [`amiga-wc-hof-implementation-plan.md`](docs/amiga-wc-hof-implementation-plan.md).

- **Amiga community stats (Jun 2026):** **V2 writers shipped** — registry v2, `036`/`037`, `prove` green. **Activity charts shipped Jul 2026** — 45 panels / 46 ship IDs on `/amiga/activity/` six wings — [`amiga-activity-charts-policy.md`](docs/amiga-activity-charts-policy.md) · [`amiga-activity-charts-implementation-plan.md`](docs/amiga-activity-charts-implementation-plan.md) track **complete** (slices 0–10). Per-WC table on World Cups hub wing 2 **shipped**.

- **Amiga World Cups hub (Jun 2026):** **Wings 1–4 shipped** — **events catalog** (sortable table, podium flag+name cols) + tournament stats (five sub-wings) + **player stats** + **country stats**. [`amiga-world-cups-hub-policy.md`](docs/amiga-world-cups-hub-policy.md).

- **Amiga derived writes (Jun 2026):** **Locked** — batch `*-rebuild` CLIs removed; corrections = **`prove` only**; verify = read-only oracles. [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md).

- **Amiga Countries hub (Jun 2026):** **Shipped** — hub tab + index (player count sort) + country entity **Roster · Rivals** (CRV-1–7: `country/rivals/{h2h,wdl,goals,dds}`); flag links site-wide; cross-links WC country stats. [`amiga-countries-hub-policy.md`](docs/amiga-countries-hub-policy.md) · [`amiga-country-rivals-policy.md`](docs/amiga-country-rivals-policy.md).

- **Amiga perfect event (Jun 2026):** **Shipped** — SCH-045; honours LB + WC **Perfect** column; catalog filter; HoF **Most perfect events**. [`amiga-perfect-event-policy.md`](docs/amiga-perfect-event-policy.md).
- **Amiga perf. rating LB (Jun 2026):** **Shipped** — folder `performance-rating/{best,top,perfect}.php` + segment nav; W-D-L columns; Top 100 fixed set; Perfect shows **∞**. [`amiga-performance-rating-leaderboard-policy.md`](docs/amiga-performance-rating-leaderboard-policy.md).

- **Amiga tournament videos (Jun 2026):** **TV-3 + TV-4 shipped** — manifest **~299** videos; unified embed UI; **C06** dedicated Videos column; **With videos** filter; **player profile Videos wing**. **Jul 2026:** **TV-2b DB anchor sync** — `sync_db_ids` + `verify-tournament-videos` in `prove` ([`amiga-tournament-videos-policy.md`](docs/amiga-tournament-videos-policy.md) §12).

- **Obsolete dev scripts retirement (Jun 2026):** **Track complete** (slices 1–6) — retired batch/replay CLIs stubbed or archived; `scripts/k2_rating_core/` is the shared formula library; runbooks → holy ops — [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md) · inventory [`DEAD_SURFACE.md`](docs/DEAD_SURFACE.md).

---

## Deep reference (read on demand)

| Topic | Where |
|--------|--------|
| Live post-game (legacy prod only) | `docs/ratings_cpp.txt` — historical; cutover = PHP ops |
| Ladder ops / PHP post-game | `docs/ladder-ops-platform.md` §2 · `docs/post-game-php-development.md` |
| Per-game table | `docs/ratedresults-schema.md` |
| Replay / Elo formulas (library) | `scripts/k2_rating_core/` · historical column manifest [`replay-v1-scope-and-reset.md`](docs/replay-v1-scope-and-reset.md) |
| Profile layout / charts | `docs/player-profile-feast.md` |
| Activity charts (plan + registry) | `docs/activity-charts.md` |
| Status hub spec | `docs/STATUS_PAGE_DATA.md` |
| Page nav spacing (chrome gaps) | `docs/nav-spacing-policy.md` · `docs/k2-nav-implementation-checklist.md` (agents) |
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

1. **Amiga profile** — optional polish on v0 feast — [`amiga-profile-v0.md`](docs/amiga-profile-v0.md). (Online profile feast **complete** — [`player-profile-feast.md`](docs/player-profile-feast.md).)

2. **Amiga Tournaments hub — tournament stats wing (C14)** — metadata leaderboards (most debuts, largest field, …) beside chronological catalog; WC hub pattern. **Approved** — [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §6.4; pairs **C08** editorial on `tournament.php`; likely extend `amiga_tournament_catalog_stats` at finalize.

**Steve (when ready)**

3. **Prod copy → live PHP ops** — migrate / seed / zero / simul / dispatch — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md); WinSCP `public_html/ops/`.

**Migration habit (not a numbered task):** stored-truth changes → [`UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) Part B + [`prod-coordination.md`](docs/prod-coordination.md) registers.

---

## Recent log

| When | Note |
|------|------|
| 2026-07-02 | **Amiga player name hover glance** — read-only tier **B** default on Amiga player links; API `amiga_player_glance.php`. **Lift shadow:** omnidirectional neutral halo (`--k2-amiga-glance-lift-shadow` in `amiga-player-glance.css`) — zero offset, layered blurs + 1px ring; separate from accent border glow; tuned for busy table backgrounds. |
| 2026-07-02 | **Amiga Activity Texture — low-scoring rate** — `low_scoring_games` year/realm fact + `low_scoring_rate` API + 6th Texture panel (≤3 goals, per 100 games); registry + Python/PHP writers; **`prove` green** local. |
| 2026-07-02 | **Amiga WC stats wings** — removed Activity cross-link intro from all Tournament stats sub-wings (`amiga_world_cup_stats_wing_body.inc.php`). |
| 2026-07-02 | **Amiga Activity Texture — high-scoring hint** — `k2-chart-block__hint` under High-scoring rate panel (ten+ goals both sides, per 100 games); matches online Activity chart hint pattern. |
| 2026-07-02 | **Activity summary copy** — second sentence “We average…” (online + Amiga activity lede). |
| 2026-07-02 | **Amiga player hero Events anchor** — Events stat + profile tournament history links land on `#k2-player-tournaments-table` (all-events filter). |
| 2026-07-02 | **Amiga player tournaments table anchor** — `#k2-player-tournaments-table` above history table + scroll pad; hero World Cups + honours WC history links land there (`k2_carry_scroll_restore`). |
| 2026-07-02 | **Amiga player hero layout** — World Cups with core stats (Rank–Games); WC medals alone after 20px gap (country-hero parity). |
| 2026-07-02 | **Amiga player hero World Cups stat** — WC slice `tournaments_played` before medal counts; links to Tournaments wing World Cups filter; TT cutoff from slice-at-event row. |
| 2026-07-02 | **Amiga player hero Events stat** — stored `tournaments_played` between Rating and Games; links to Tournaments wing; TT cutoff from snapshot row. |
| 2026-07-02 | **Activity year-bar HTML tooltips + prove** — shared `renderBreakdownYearBar()`: GEO-008/Q-VOL-005 (`host_tournaments_by_year`), GEO-010 (`nationality_active_by_year`), Q-WC-006/007 (`wc_active_players` → `wc_nationality_active_by_year`); **`prove` green** local (~23 min). |
| 2026-07-02 | **Nations player grains — docs + prove** — Activity **48 panels / 49 Q-IDs**; +3 nationality player grains (no DDL). |
| 2026-07-02 | **Nations player grains B–D shipped** — `all_time×nationality×active_players`, `year×nationality×player_debuts`; 3 new Nations panels; 8-panel page order; GEO-010 tooltip (no list scroll). |
| 2026-07-02 | **Amiga Activity Nations — distinct nationalities tooltip** — new stored fact `year × player_nationality × active_players`; `year_facts` returns `nationality_active_by_year` breakdown; bar hover lists flag + country + active player count (HTML tooltip). Re-prove `ko2amiga_db` to populate facts. |
| 2026-07-02 | **Amiga tournament video game links GL-5…6 shipped** — `video_game_links.csv` sidecar merge (`stream_map` mode), manifest `game_start_sec[]`, sync/verify/build wired; policy + README + implementation plan trimmed. Sidecar empty until stream curation. |
| 2026-07-02 | **Amiga tournament video game links GL-1…4 shipped** — `game_links.py` + `audit_game_links.py`; sync remap/locks; 8 dual-leg manifest rows; `verify_tournament_videos` **0 errors**. |
| 2026-07-02 | **Amiga tournament video game links policy (GL-0)** — [`amiga-tournament-videos-game-links-policy.md`](docs/amiga-tournament-videos-game-links-policy.md): match facts authoritative, `amiga_games.id` cache only, sync remap + verify oracle plan (GL-1…6); dual-leg/stream N-game links. |
| 2026-07-02 | **Amiga tournament Videos Games wing — game id desc** — `amiga_tournament_videos_wc_game_index()` sorts by `game_id` DESC only (no stage bucket). |
| 2026-07-02 | **Amiga tournament Games tab — game id desc** — `amiga_tournament_games_rows()` + table default sort by `id` DESC only (no date). |
| 2026-07-02 | **Amiga Rating LB present-day Δ tooltip** — WC-start Δ header help names the latest WC tournament + date in `k2-link-star` spans (no redundant “World Cup” before the name). |
| 2026-07-02 | **Amiga Activity chart track complete (slice 10 polish)** — mobile `touch-action` on Amiga panels; page-scoped loader queue + deferred race/histogram panels; geo roster links + Countries hub cross-link carry `as=`; registry parity 45↔46 (VOL-004+SHP-010 merge); policy + catalog step 6 + url-routes closed. |
| 2026-07-02 | **Amiga Activity slice 9 shipped (Shape wing)** — 9 histogram panels live on `/amiga/activity/shape.php`; new `api/amiga_community_histogram.php` + `mountHistogram()` (bucket count + % tooltips); loader queues `active_years` last; **45/45 panels** on track. |
| 2026-07-02 | **Amiga Activity slice 8 shipped (Shape probes STOP gate)** — no UI; `includes/amiga_community_histogram_lib.php` + `scripts/oneoff/amiga_community_histogram_probe.php`; 9 kinds × 4 cutoffs probed on `ko2amiga_db`; policy §5.6 bucket edges locked; all kinds ship read-time in slice 9 (no S6); `active_years` game_scan ~147 ms present, slower at mid cutoffs — defer panel in loader queue. |
| 2026-07-02 | **Amiga Activity slice 7 shipped (Geography Nations wing)** — 5 panels on `/amiga/activity/geography/nations.php`: appearances + goals duel/race (`player_nationality`) + realm distinct-nationalities year bar; slice-5 harness removed; reuses slice-6 geo panel mounts. |
| 2026-07-02 | **Amiga Activity slice 6 shipped (Geography Hosts wing)** — 8 panels on `/amiga/activity/geography/hosts.php`: games/tournaments/goals duel bars + race lines driven by slice-5 selector; realm distinct-host-countries year bar + cumulative stepped line (GEO-009 unlock tooltip); generic `mountGeoDuelYear`/`mountGeoRace`/`registerGeoPanel`; Growth slice-1 visual sign-off recorded. |
| 2026-07-02 | **Online Opponents H2H charts fix** — `player_opponents_page.php` now loads `player-opponents-h2h-chart-context.js` (Amiga parity); restores wins, cumulative goals, combined goals, and scoreline heatmap bootstrap from `data-chart-opponent-id` (regression since Jun 2026 Amiga H2H refactor). |
| 2026-07-02 | **Amiga Activity slice 5 shipped (Geography selector platform)** — duel + race controls on `/amiga/activity/geography/{hosts,nations}.php` with harness charts; `?hosts=` / `?nats=` URL state + `replaceState`; new `api/amiga_community_slice_series.php`; `year_facts` extended for `host_country` + `player_nationality` + `available_keys`; lib helpers `amiga_community_slice_series()` + geo selection; module exports `getGeoState` / `renderGroupedYearBar` / `renderRaceLines` for slices 6–7. |
| 2026-07-02 | **Amiga Activity slice 4 shipped (World Cups wing)** — 6 panels on `/amiga/activity/world-cups.php`: WC games ghost bars (realm behind), WC share %, cumulative `WcGamesPlayed` curve, WC goals/game with realm overlay line, nations + players year bars; `year_facts` `slice=world_cup`; `year_rates` `wc_share` + `wc_goals_per_game`; cross-links WC hub ↔ Activity WC wing. |
| 2026-07-02 | **Amiga Activity slice 3 shipped (Texture wing)** — 5 rate bars on `/amiga/activity/texture.php` with dashed all-time reference lines; `year_rates` extended (goals/draw/DD/CS/high-scoring + `reference` from headline at cutoff); `renderYearRateBar()` + tooltip footer; helper `amiga_community_year_rate_reference_at_cutoff()`. |
| 2026-07-02 | **Amiga Activity slice 2 shipped (People wing)** — 5 panels live on `/amiga/activity/people.php`: active players + debuts year bars, cumulative players line (VOL-004 + SHP-010 merge note), distinct pairs year bar + cumulative line; reuses slice-1 mounts; TT verified at `as=year:2005`. |
| 2026-07-02 | **Amiga Activity slice 1 shipped (Growth wing)** — 7 panels live on `/amiga/activity/growth.php`: games/tournaments/goals year bars + cumulative curves (every point = tournament; tooltip name + date + total; desktop click-through to event stats carrying `as=`) + avg games-per-tournament bar; new `api/amiga_community_year_rates.php`; generic module mounts (`mountYearFacts`/`mountYearRate`/`mountCumulative`) reusable for wings 2–7; TT partial-year tooltip footer. **Site-wide fix:** `k2-page-boot.js` fired `k2OnPageReady` callbacks twice per load (Turbo-removal regression) — double chart boots put "Could not load…" statuses on online Activity; now fires exactly once. |
| 2026-07-02 | **Amiga Activity slice 0 shipped** — `/amiga/activity/` sub-hub live: 7 leaf pages, shell pair + wing/geography navs, 9 route keys, `activity.php` 302 → Growth, KOA chapter lede at cutoff, `js/amiga-activity-charts.js` skeleton, lib read helpers, APIs `amiga_community_year_facts` + `amiga_community_snapshot_series` (TT via `as=`). Fixed latent bug: present community reads picked `MAX(tournament_id)` (a 2002 fractional-chrono import), now chrono-latest snapshot. |
| 2026-07-02 | **Amiga Activity chart track planned** — IA locked (six wings, 45 panels, duel/race country selectors, click-through curves, TT semantics) in `amiga-activity-charts-policy.md`; sliced build plan (0–10, C8 probe STOP gate) in `amiga-activity-charts-implementation-plan.md`; catalog plan §9.1 closed, step 6 next. |
| 2026-07-02 | **Amiga community stats doc sync** — catalog steps 4 done (v2 writers); step 5 = chart IA (§9.1) before full 46-panel impl plan. |
| 2026-07-02 | **Amiga TT ribbon with-player panel** — dropdown panel only: `min-width: 100%` + name-based max (decoupled from trigger); trigger/ribbon spacing unchanged. |
| 2026-07-01 | **Amiga Opponents country H2H default** — when `country=` omitted, default picker skips hero's own nation (most-played foreign country; fallback top bucket if all domestic). |
| 2026-07-01 | **Amiga player Tournaments filter segments** — `.k2-amiga-tournament-index-segment-filters` flex gap moved to `theme.css` so All/World Cups ↔ Perfect run rows get `--k2-nav-gap` without loading `amiga-tournament.css`. |
| 2026-07-01 | **Amiga Opponents nav row gap** — wing ↔ grain segment spacing doubled (`calc(var(--k2-nav-gap) * 2)` on `.k2-player-opponents__nav-row`). |
| 2026-07-01 | **Profile at-a-glance Presence** — swapped row order: Last rated game above First rated game (`player_feast_presence_stat_rows`). |
| 2026-07-01 | **Status Leagues meta line** — period label, rated-games count, and end date/countdown `.blue` spans bold in competitions meta. |
| 2026-07-01 | **League points table stat colours** — Status + `league.php` points standings: W/GF `.blue`, L/GA `.red` (colour only; Pts/Games keep `k2-status-table__hero-stat` bold). |
| 2026-07-01 | **Player hero chrome shipped** — `.k2-player-hero` (online + Amiga player wing): H2H-style accent border + panel/avatar glow in `theme.css`; +12px below hero (`calc(--k2-nav-gap + 12px)`). Country/tournament heroes unchanged. Experiment toggle removed. |
| 2026-07-01 | **C16 universe map** — gestating in creative doc §6.6: map-first wow piece, creator nodes, three-audience intent (newcomers / veterans / creators). |
| 2026-07-01 | **Creative C16 spark** — KO2 universe map in [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §5.4 / §6.6 (Misc Scene leaf; visual vs atlas TBD). |
| 2026-07-01 | **Present layer & site completion doc** — expanded [`present-layer-ia.md`](docs/present-layer-ia.md): Misc lifecycle, leaf pages (PL14), footer/about (PL12–13, PL15), shippable v1 checklist §12, post-ship growth PL16. |
| 2026-07-01 | **Present layer IA intent doc** — [`present-layer-ia.md`](docs/present-layer-ia.md): News scrollable roll (~weekly), pulse rail (~daily, online-heavy), Misc phased shelf, Play & Setup vs Amiga onboarding; PL1–PL12; hub-ia + C04 cross-links. |
| 2026-07-01 | **Amiga Rivals H2H chart drill-down** — nation-pair `gf`/`ga`/`gs` on `/amiga/games/all.php`; combined-goals chart ctx fix; dedupe URL params; player games 404 before output. |
| 2026-07-01 | **Amiga WC country Opponents wing** — Opponents column `.blue` on `/amiga/world-cups/countries/opponents.php` (parity with players Opponents wing). |
| 2026-07-01 | **Amiga WC country Results Pts colour** — Pts column `.blue` on `/amiga/world-cups/countries/results.php` (parity with players Results wing). |
| 2026-07-01 | **Amiga World Cups Chronology footer** — removed events table footnote (WC count stays in hub chapter lede). |
| 2026-07-01 | **Amiga World Cups wing map + Countries footer** — Player stats bullet: "…World Cup exploits"; removed Countries index footnote (count stays in chapter lede). |
| 2026-07-01 | **Amiga World Cups hub chapter lede** — dropped "always"; blue counts for WCs + WC players + WC countries (`amiga_world_cups_hub_chapter_lede_html()`); shell loads via `amiga_wc_honours_player_count()` / `amiga_wc_country_count()`. |
| 2026-07-01 | **Amiga Activity summary lede** — players + countries + rated games + tournaments (blue counts, TT-aware); `amiga_lb_rated_country_count()` restored for country total. Lede verb: **have played** (ongoing cumulative, vs bare “played”). |
| 2026-07-01 | **Amiga Leaderboards chapter lede** — shortened to games + tournament counts only (removed player/country clause); still dynamic blue + TT cutoff. |
| 2026-07-01 | **Amiga TT hub chapters** — Tournaments, HoF, Activity no longer suppress `k2-hub-chapter` under `as=`; stamp + ribbon sit above chapter. Policy: `amiga-time-travel-policy.md` §5.0, `design-direction.md`, `hub-ia-agreement.md`. |
| 2026-07-01 | **Amiga Goals LB GD/g column** — `(GF − GA) / games` on `/amiga/leaderboards/goals.php` after GA/g (parity with WC player stats Goals wing; no tooltip). |
| 2026-07-01 | **Amiga rating LB TT event Δ links** — non-zero Δ cells link to event-stats `#tournament` for snapshot tournament (`amiga_lb_rating_delta_cell`, `rating.php`, `theme.css`). |
| 2026-07-01 | **Amiga Peak rating LB Peak rank tooltip** — prose tooltip + link to rating LB `as=event:{id}` (page top, no hash); absent-tournament clause; query adds peak rank tournament + played flag (`amiga_lb_peak_rating_lib.php`, `amiga_lb_snapshot_lib.php`). |
| 2026-07-01 | **Amiga Peak rating LB Peak styling + tooltip** — Elo col anchor (default); Peak `.blue` + tournament link/tooltip; copy “highest Elo rating ever” + date + “all-time peak rating” (`amiga_lb_peak_rating_lib.php`, `theme.css`). |
| 2026-07-01 | **Amiga Peak rating LB Peak tooltip** — Peak cell prose tooltip (tournament + Δ + peak rating); link to event-stats; coarse double-tap / desktop click via `data-k2-coarse-tap` + k2-table action footer (`amiga_lb_peak_rating_lib.php`). |
| 2026-07-01 | **Online Peak rating LB context tooltip** — Peak + Peak date cells hover: 9 games before, peak game, next game via `/api/lb_peak_rating_context.php` + `lb-peak-rating-tooltip.js` (cal-style list + rating delta column). |
| 2026-07-01 | **Online Peak rating LB Peak date column** — col 5 after Peak (`PeakRatingGameID` → `ratedresults.Date`); sortable; header help `k2_lb_help_online_peak_rating_date()`; replaced prior Peak hover-only tooltip. |
| 2026-07-01 | **Online Peak rating LB hover date** — Peak column tooltip via `PeakRatingGameID` → `ratedresults.Date` (`lb_peak_rating_lib.php`); same K2 body-cell pattern as streaks/activity peaks. |
| 2026-07-01 | **Tint picker — close on navigation** — open state no longer persisted in `sessionStorage`; every page load starts closed (`theme_boot_head.php` + `k2-tint-toggle.js`); clicking any site link closes the panel immediately; bfcache back also closes. Docs: `tint-vs-realm.md`, `design-direction.md`, `hub-ia-agreement.md`, `self-hosted-assets.md`. |
| 2026-07-01 | **Amiga calendar-geo LB Peak games colour** — Peak games column values wrapped in `.blue` on `/amiga/leaderboards/calendar-geo.php`. |
| 2026-07-01 | **Amiga Perf. rating LB** — Perf. column `.blue` + `k2-table--perf-rating-lb` CSS so stat green wins over anchor link-star (Best/Top anchor col 3; Perfect ∞). |
| 2026-07-02 | **Amiga Countries hub gold sort** — gold column tiebreak silver then bronze (`data-k2-sort-tie-cols` on gold `<th>`; `k2-table.js`). |
| 2026-07-01 | **Amiga Peak rating LB** — Peak column values wrapped in `.blue` (parity with online `leaderboards/peak-rating.php`). |
| 2026-07-01 | **Amiga Victims LB Opponents** — column values wrapped in `.blue` (parity with online `leaderboards/victims.php`). |
| 2026-07-01 | **Amiga WC players Opponents wing** — Opponents column values wrapped in `.blue` on `/amiga/world-cups/players/opponents.php`. |
| 2026-07-01 | **Amiga WC countries Participation wing** — Entries column values wrapped in `.blue` on `/amiga/world-cups/countries/participation.php`. |
| 2026-07-01 | **Amiga WC players Results wing** — Pts column values wrapped in `.blue` on `/amiga/world-cups/players/results.php`. |
| 2026-07-01 | **Honours-table medal columns** — gradient + fw600 when count > 0; plain muted `0` (no active-sort brightening). |
| 2026-07-01 | **Amiga Countries hub Players column** — `.blue` on `/amiga/countries.php` index table. |
| 2026-07-01 | **Milestones meta LB tier counts** — `k2-table--milestones-meta-lb`: four tier columns always weight 600 (hue unchanged; sort emphasis on header + Milestones total / Games only). |
| 2026-07-01 | **Stat green tune** — `--k2-stat-positive-green` `#bdd852` → `#c4e84c` (between loud `#c6ef4a` and dull nudge). |
| 2026-07-01 | **Status league Pts/Games → `.blue`** — removed `k2-table--league-anchor-cross`, `--k2-league-anchor-*`, and `data-k2-anchor-col` on league tables; Pts + Games use stat palette (`--k2-table-positive`). PHP + `status-period-competitions.js` + docs. |
| 2026-07-01 | **`.blue` chrome/holo — stat green** — `--k2-stat-positive-green` `#c6ef4a` for `--k2-table-positive` (replaces pure pitch); amber/pitch tint unchanged (78% cyan). |
| 2026-07-01 | **Amiga tournament Event stats GF/GA colour** — GF column `blue`, GA column `red` on `/amiga/tournament/event-stats.php` (all tournaments). |
| 2026-07-01 | **Amiga Highlights biggest upsets tooltip** — board tab help copy: "The biggest rating gaps overcome by the underdog." |
| 2026-07-01 | **Amiga HoF win rate** — "Highest winning frequency" row = read-time `(wins + ½·draws) ÷ games` via `amiga_hof_win_rate_holder()` (matches rating LB Win rate column; stored `BiggestWinRatio`/`WinRatio` unchanged). |
| 2026-07-01 | **Tournament video DB anchors — doc sweep** — policy §12, implementation plan TV-2b, ground-stack/import/staging/profile/k2-embedded/navigation-model/**OPERATIONS_QUICK_START**/**amiga-derived-write-policy** updated; `sync_db_ids` + `verify-tournament-videos` in `prove`. |
| 2026-07-01 | **Amiga career DDs LB colour** — Double Digits column `blue`, DD conceded column `red` on `/amiga/leaderboards/double-digits.php`. |
| 2026-07-01 | **Online DDs LB colour** — Double Digits column `blue`, DD conceded column `red` on `/leaderboards/double-digits.php` (Amiga parity). |
| 2026-07-01 | **Amiga career Goals LB GF/GA colour** — GF column `blue`, GA column `red` on `/amiga/leaderboards/goals.php`. |
| 2026-07-01 | **Online Goals LB GF/GA colour** — GF column `blue`, GA column `red` on `/leaderboards/goals.php` (Amiga parity). |
| 2026-07-01 | **Online Streaks LB Wins colour** — Wins column `blue` on `/leaderboards/streaks.php`. |
| 2026-07-01 | **Online Peak rating LB Peak colour** — Peak column `blue` on `/leaderboards/peak-rating.php`. |
| 2026-07-01 | **Online Victims LB Opponents colour** — Opponents column `blue` on `/leaderboards/victims.php`. |
| 2026-07-01 | **Amiga WC player Honours gold colour reverted** — removed `blue` from WC gold medal column on `/amiga/world-cups/players/honours.php`. |
| 2026-07-01 | **Amiga WC country DDs colour** — Double digits column `blue`, DD against column `red` on `/amiga/world-cups/countries/dds.php`. |
| 2026-07-01 | **Amiga WC country Goals GF/GA colour** — GF column `blue`, GA column `red` on `/amiga/world-cups/countries/goals.php`. |
| 2026-07-01 | **Amiga WC country Results W/L colour** — W column `blue`, L column `red` on `/amiga/world-cups/countries/results.php`. |
| 2026-07-01 | **Amiga WC player DDs colour** — Double Digits column `blue`, DD C column `red` on `/amiga/world-cups/players/dds.php`. |
| 2026-07-01 | **Amiga WC player Goals GF/GA colour** — GF column `blue`, GA column `red` on `/amiga/world-cups/players/goals.php`. |
| 2026-07-01 | **Amiga WC player Results W/L colour** — W column values `blue`, L column values `red` on `/amiga/world-cups/players/results.php`. |
| 2026-07-01 | **HoF record value scroll anchors** — online + Amiga HoF leaderboard links append `#k2-lb-table`; online LB anchor consolidated in `lb_nav.php` / `lb_activity_nav.php` / `league_honours_panel.php`; Amiga WC player wings via `amiga_wc_players_table_shell_open()`. |
| 2026-07-01 | **Games hub lede** — chapter intro adds “since June 9, 2017” after rated-game count (`games_hub_shell_start.inc.php`). |
| 2026-07-01 | **Amiga tournament nav order** — WC pills: Event stats · Games · Stages · Videos (Games before Stages; Videos last). |
| 2026-07-01 | **Rating LB Δ tooltip label** — time-travel Δ column title `Rating change (time travel mode)`; present-day WC Δ stays `Rating change`. |
| 2026-07-01 | **TT as_with Year/Month** — with-player filter + filtered chevrons + auto-snap on all TT ribbon wings; snap/chevron hrefs preserve `as_with=` via `amiga_url_with_as_param()`. |
| 2026-07-01 | **C06 cleanup** — removed dev glyph picker page/lib; consolidated `amiga_tournament_video_column_cell()`; CSS scoped to `.k2-table-cell--video-glyph` only. |
| 2026-07-01 | **C06 + tournaments index table** — Videos column (blank header, empty when no footage, no tooltips); Players + Games columns centered (`k2-table-cell--center`, parity with WC chronology). |
| 2026-07-01 | **C06 column polish** — empty Videos column header; blank cells when no footage; no glyph tooltips. |
| 2026-07-01 | **C06 glyph → Phosphor play-circle-fill** — chronology video glyph switched to `ph:play-circle-fill` (picker row #15); dev picker at `/amiga/dev/video-glyph-picker.php`. |
| 2026-06-30 | **With-player stepper doc sweep** — policy §5.7–§5.8 + §10 module map; plan probes/inventory; TT snap via preamble (not snapshot context); `id_country` + faceted counts documented. |
| 2026-06-30 | **TT as_with auto-snap fix** — `amiga_as_with_snap.php` + preamble before DOCTYPE; LB wings + HoF wired. |
| 2026-06-30 | **Tournament step filter layout fix** — ghost listbox `align-self: flex-start` (panel = trigger width); theme panel override like games/all. |
| 2026-06-30 | **Tournament step filter facets** — with-player + host-country listboxes show faceted tournament counts (`meta`); counts cross-filter (player↔country). |
| 2026-06-30 | **Tournament step host country** — `id_country=` listbox after with-player on entity nav; filter bag + propagation via `amiga_id_country_url.php`; probe extended. |
| 2026-06-30 | **League period UI** — period chevrons + with-player row above Standings heading; sibling cup link in intro lede. |
| 2026-06-30 | **With player slice 3** — league period `start_with=` listbox + filtered chevrons; `k2_league_period_with_player.php`, `k2_start_with_url.php`; probe green; track complete. |
| 2026-06-30 | **With player slice 2** — tournament chevrons + `id_with=` on entity nav; filter-bag catalog, wing-preserving hrefs, `amiga_tournament_step_*` modules; probe green. |
| 2026-06-30 | **With player slice 2 prep (docs)** — policy §5.5–§5.7 + plan: tournament step three layers, wing fallback, future catalog filters (`id_wc`, `id_country`) via filter bag; no TT reuse (WP15–WP17). |
| 2026-06-30 | **Agent onboarding** — AGENTS traps + kool-workspace + TT policy §3.4 + page-structure checklist: with-player URL carry alongside `as=`. |
| 2026-06-30 | **With player slice 1 fix** — shared `k2-amiga-time-travel-url.js`; header search + H2H chart games links preserve `as_with=`. |
| 2026-06-30 | **With player slice 1** — `as_with=` on TT Event ribbon: participation lib, listbox, filtered Event chevrons, picker link-star accents, URL propagation. |
| 2026-06-30 | **With player slice 0 follow-up** — `amiga_tournament_href()` no longer rewrites `as=event:{linked id}`; removed dead `amiga_tournament_snapshot_as_param()`. |
| 2026-06-30 | **With player stepper plan** — slice 0 adds WP14 (retire tournament id-follows-as); full plan ready. |
| 2026-06-30 | **With player stepper planning** — policy revised: separate params (`as_with` / `id_with` / `start_with`), shared lookup only, slice 0 = T18 removal; [`with-player-stepper-implementation-plan.md`](docs/with-player-stepper-implementation-plan.md). |
| 2026-06-30 | **Amiga sticky TT ribbon (C02)** — pushpin on time-travel bar; pins Year/Month/Event + stepper + listboxes (`k2-amiga-time-travel-pin.js`); default off; `localStorage`. [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §6.1. |
| 2026-06-30 | **Status — On this day last year (C07)** — arc panel link **On this day last year →** → Points day league (`start=` UTC today − 1 year); [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §6.3. |
| 2026-06-30 | **Amiga Highlights — biggest upsets (C15)** — fifth board on `/amiga/games/highlights.php?board=biggest_upsets`; underdog-wins only (lower-rated winner); TT + WC scope inherited. [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §6.5. |
| 2026-06-30 | **With player stepper policy** — locked spec [`with-player-stepper-policy.md`](docs/with-player-stepper-policy.md): opt-in listbox + chevrons (Amiga tournament nav, TT Event ribbon, online league); retires T18; creative **C13**. |
| 2026-06-30 | **Amiga WC hub Covid lede fix** — `amiga_world_cups_hub_chapter_as_of()` uses calendar period end for year/month TT (not cutoff tournament date); `(except for Covid)` shows on `year:2020` at Dec 2020+. |
| 2026-06-30 | **C14–C15 approved** — promoted from spark to firm to-do: Tournaments hub tournament-stats wing (C14) + Highlights biggest upsets board (C15). [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §5.1 · `PROJECT_MEMORY` Next #2–3. |
| 2026-06-30 | **Creative ledger C14–C15** — tournament metadata LB wing (most debuts etc., WC-style split on Tournaments hub); Amiga Highlights **biggest upsets** board (rating gain). [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §5 · §6.4–§6.5. |
| 2026-06-30 | **Creative session wrap** — [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) discoverability locked (§4.0); wired into `UPDATE_DOCS` · `AGENTS.md` (task-triggered) · `agent-track-playbook` Phase 0. Rank-chart H2H policy: online **not planned**. |
| 2026-06-30 | **Creative ideas ledger** — [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md): Jul 2026 brainstorm (recipe, origin stories, C01–C12 approved/parked/rejected). Rank-chart docs: H2H compare marked **shipped Amiga-only** (was stale "not built"). |
| 2026-06-30 | **Amiga Countries index lede** — dropped WC country-stats cross-link sentence from chapter intro. |
| 2026-06-30 | **Amiga TT stamp LED (year/month)** — temporal stamp LED + a11y follow ribbon picker key (period end), not resolved cutoff tournament date; fixes month chevrons through quiet months. [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md) §5.0 |
| 2026-06-30 | **Amiga WC stats Year % read-time** — `share_of_year_games` derived at read from `amiga_community_stat_facts` at viewer cutoff (present or TT); latest fact row ≤ cutoff per calendar year (handles sparse tail snapshots). Fixes WC I 100% bug (e.g. 143/173). Writer/DB column cleanup deferred next slice. [`amiga-world-cup-stats-table-plan.md`](docs/amiga-world-cup-stats-table-plan.md) |
| 2026-06-30 | **Amiga World Cups hub chapter lede** — Christmas intro + blue WC count; Covid clause time-aware (none / singular *year* / plural *years* by cutoff); chapter shown under `as=` with snapshot count. |
| 2026-06-30 | **Amiga World Cups hub chapter** — `k2-hub-chapter` title + four-wing map list above sub-nav (`amiga_world_cups_hub_helpers.php`); `k2_hub_chapter` skips empty lede. |
| 2026-06-30 | **Amiga tournaments index** — removed **Perfect run** facet toggle from `/amiga/tournaments.php` (player tournament history filter unchanged). |
| 2026-06-30 | **Amiga perf-rating Perfect wing Date column** — body cells stay muted (`k2-amiga-lb-perf-rating-date`) when date is active sort; header keeps sorted chrome. |
| 2026-06-30 | **Amiga perf-rating Perfect wing lede** — “Every perfect tournament run, `<span class="blue">`{count}`</span>` in total.” on `perfect.php`. |
| 2026-07-01 | **Mobile nav load bar** — restored `.turbo-progress-bar { display:none }` in `theme.css` (stale Turbo cache on phone); site-wide `theme-color` + `color-scheme: dark` in `k2_head.php` so browser chrome progress blends with page bg. |
| 2026-06-30 | **Amiga game id links → video landing** — `k2_amiga_game_page_url()` hash from manifest: caption (1 video), menu (2+), else `#k2-game`. All id links via existing helper chain. |
| 2026-06-30 | **Amiga game page hash landing** — `#k2-game` anchor + `$k2ScrollTargetId` (bare id URLs); `k2_amiga_game_page_url()` canonical helper; all inbound id links funnel through it (`amiga_rated_game_id_html` + H2H/moments/WC stats call sites). Short-page scroll via existing `k2_carry_scroll_restore.php` `ensureMinScrollHeight`. |
| 2026-06-30 | **Amiga game page hub nav** — `/amiga/game.php?id=` includes `amiga_hub_nav.php` with `$k2AmigaHubTabActive = ''` (NM1/NM2 entity page; matches online `game.php` + tournament entity pattern). |
| 2026-06-30 | **Amiga game page video embed** — `/amiga/game.php?id=` shows spotlight player + scoreboard caption when manifest links a clip; multi-video games get stacked “Video 1/2…” picker (`?v=` deep link); `amiga-game-video.js`. |
| 2026-06-30 | **Amiga Countries index default tiebreak** — equal player counts sort by games DESC (then country token); fixed `data-k2-skip-initial-sort` col + `data-k2-sort-tie-order="match"` for client parity. |
| 2026-06-30 | **Amiga Countries index lede** — narrative chapter copy with blue country count + roster/rivals CTA; `amiga_countries_index_chapter_lede_html()`; WC country stats cross-link kept. |
| 2026-06-30 | **Jukebox popup white flash** — sync dark boot `document.write` on `about:blank` before `location.replace`; `color-scheme:dark` + panel `#131922` pre-paint in `jukebox.php`; prefetch `/jukebox.php` from FAB + head. |
| 2026-06-30 | **Jukebox first-open flash fix** — single `window.open('/jukebox.php', …)` (no blank→close→recreate); main tab keeps focus until player `ready`; inline `#0b0f14` pre-paint in `jukebox.php`; `k2-jukebox-popup-live` session flag. Doc: `k2-jukebox-popup.md`. |
| 2026-06-30 | **Jukebox playlist row hover** — track title picks up `--k2-link-star` accent when the playlist row is hovered/focused. |
| 2026-06-30 | **Jukebox FAB glow — final timing** — auto-advance glow 2.6s; gentle rise to a sharp peak (~38%, rise ~1s) then prompt-but-gradual fade (`cubic-bezier(0.25,0,0.45,1)`, ~1.6s tail, no plateau); subtle glow-forward bloom, thin 1px rim. Launcher fallback timer 3.0s. |
| 2026-06-30 | **Jukebox progress bar** — smooth playback via `requestAnimationFrame` + `scaleX` fill (replaces jerky `timeupdate` + width transition). |
| 2026-06-30 | **Jukebox FAB — auto-advance glow** — popup broadcasts `track-change` / `auto-advance` on `ended`; main-tab FAB pulses accent glow (`is-track-change`) via `k2-jukebox-launcher.js` + `k2-jukebox.css`. Manual next/prev unchanged. Doc: `k2-jukebox-popup.md`. |
| 2026-06-30 | **Tint schedule boundaries shifted** — six-hour auto rotation now holo 04–10, pitch 10–16, chrome 16–22, amber 22–04 (was midnight-aligned slots). `k2-tint-schedule.js` + `tint-vs-realm.md`; amber period id anchors to evening calendar day for manual-override continuity across midnight. |
| 2026-06-29 | **Activity CSS audit** — removed dead legacy embed block (`server-peak-period-leaderboards*`, `server-period-activity-leaderboards*`, unused singular LB panel/calendar/summary rules); kept live `server-period-activity-leaderboard__*` picker tokens + `server-peak-period-leaderboard-status`; `.k2-activity-section__intro` → **14px**. |
| 2026-06-29 | **Hub chapter typography** — `.k2-hub-chapter__lede` + `__list` and `.k2-hub-page-intro` bumped **13px → 14px** (`theme.css`); matches body size, still muted. |
| 2026-06-29 | **Jukebox FAB — reuse existing popup after nav.** `k2-jukebox-launcher.js` now re-acquires the named window via `window.open('', 'k2jukebox')` **without** features before creating; passing features when a player is already open was spawning a second jukebox instead of raising the first. Doc: `k2-jukebox-popup.md`. |
| 2026-06-29 | **HoF (New!) cutoff** — online + Amiga: `(New!)` marker when record date is within **6 months** (was 1 month); lede bullets updated on both `/hall-of-fame.php` pages. |
| 2026-06-29 | **Amiga country hero — medal row nesting** — removed premature `</div>` in `amiga_country_hero.php` so `.k2-player-hero__medals` stays inside `.k2-player-hero__stats` (player-hero parity; fixes medals stacking under Players/Games/WC entries). |
| 2026-06-29 | **Amiga player hero — WC medal spacing** — gold/silver/bronze stats wrapped in `.k2-player-hero__medals` with equal-width columns so value centers align evenly; country hero matches. |
| 2026-06-29 | **Amiga player hero — inline country flag** — `amiga_player_hero.php` uses `k2_amiga_inline_flag_and_link()` beside the name (H2H fighter-card parity); separate Country stat removed; 24×18 flag in `theme.css`. |
| 2026-06-29 | **Amiga country roster — Elo links** — Elo column links to rating LB row anchor (`#k2-lb-player-{id}`) via `k2_amiga_lb_rating_cell_link()`; preserves `as=` time travel; `k2-link-star` (entity drill-down parity with player/tournament cells). |
| 2026-06-29 | **Amiga country Rivals H2H poster — card symmetry** — nation-pair poster passes `subject`/`opponent` into `k2_h2h_poster_country_card_html()` so hero hugs `vs` from the left (blue) and rival from the right (red). |
| 2026-07-01 | **Activity Participation LB** — Games column anchor + default sort (col 3) + `.blue` values; SQL `ORDER BY NumberGames DESC`. |
| 2026-07-02 | **Amiga Activity Growth wing** — section title *How much Kick Off 2 do we play?*; cumulative games/tournaments/goals tooltips use HTML external tooltip (host flag + tournament name, event delta + total); snapshot series API adds `host`. |
| 2026-07-02 | **Amiga Activity Growth intro** — dropped click-to-open tournament hint from section intro copy. |
| 2026-07-02 | **Amiga Activity Geography — race country links** — roster hrefs append `#k2-country-roster` (hero anchor), matching `k2_amiga_country_roster_href()`. |
| 2026-07-02 | **Amiga Activity Geography Nations intro** — race-line hint: *Click on a flag to toggle a country on or off.* |
| 2026-07-02 | **Amiga Activity Geography — compare listbox layout** — reverted `duel-pair` / `contain: layout` (z-index regression); geo controls use games-filter + TT pattern (panel width = trigger, `inline-flex` compare row, controls `z-index: 20`). |
| 2026-07-02 | **Amiga Activity Geography — race line cap 9** — race country list / `?hosts=` / `?nats=` CSV allow up to 9 series (was 7); JS + PHP + APIs aligned via `AMIGA_COMMUNITY_GEO_RACE_KEYS_MAX`. |
| 2026-07-02 | **Amiga Activity Geography — Add country trigger** — race-add listbox idle trigger reads *Add country* (fixed label survives JS rebuild via `data-k2-listbox-fixed-trigger-label`). |
| 2026-07-02 | **Amiga Activity Geography — race list not pills** — Race line countries render as flat flag + link rows (no pill chrome); click toggles line, shift+click removes. |
| 2026-07-02 | **Amiga Activity Geography — filter row labels** — Compare / Race lines use shared `k2-realm-games-filters__row-label` row headers (games-filter convention), not bespoke muted spans. |
| 2026-07-02 | **Amiga Activity Geography — Compare B required** — removed "—" empty option from second Compare listbox; B always resolves to a country (default Germany / second by volume). |
| 2026-07-02 | **Amiga Activity Geography — compare vs race decoupled** — changing Compare A/B no longer prepends those countries to Race line chips; compare and race lists stay independent on the client. |
| 2026-07-02 | **Amiga Activity Geography — listbox panel flags** — Compare / Race `k2_archive_listbox` dropdown options show inline country flags (`flag_html` / `flagHtml`); JS rebuild path matches PHP first paint. |
| 2026-07-02 | **Amiga Activity Geography — K2 listboxes** — Compare / Race line native `<select>` replaced with `k2_archive_listbox` + `k2-archive-listbox.js` on hosts + nations wings. |
| 2026-07-02 | **Amiga Activity Geography copy** — Hosts *Who's hosting tournaments?*; Nations *Where do we come from?*; intros trimmed to compare/race line only (People-wing parity). |
| 2026-07-02 | **Amiga Activity People — cumulative tooltips** — Growth-style HTML tooltips on *Cumulative players* + *Cumulative distinct pairs* (host flag + tournament name); player curve adds *N new player(s)* delta row (count-sensitive). |
| 2026-07-02 | **Amiga Activity People — cumulative panel** — panel title *Cumulative players*; removed Q-ID sub-intro under chart. |
| 2026-07-02 | **Amiga Activity People wing copy** — section title *Who's playing?*; intro: active + debuts as a pair, curves show roster growth tournament by tournament. |
| 2026-07-02 | **Amiga Activity summary — Busiest year card** — fifth stat card: peak realm games in one calendar year at cutoff; note *games · YYYY*; read via `amiga_community_busiest_year_at_cutoff()`. |
| 2026-07-02 | **Amiga Activity Growth intro — TT-aware eras** — *mid-2000s boom* / *lean mid-2010s* / *modern revival* only after cutoff year ≥ 2008 / 2018 / 2022; early `as=` omits era names. |
| 2026-07-02 | **Amiga Activity hub chapter lede** — question-led invite under *N years of the KOA*; headline numbers stay in summary panel above stat cards. |
| 2026-07-02 | **Amiga Activity hub lede** — tournament count copy: *…605 official Amiga tournaments.* |
| 2026-07-02 | **Amiga Activity hub intro** — chapter title = *N years of the KOA* (N = calendar year − 2001); lede opens *Since 2001, …*; full summary panel above wing tabs on all Activity wings. |
| 2026-07-01 | **Amiga Activity summary — player averages** — removed Games per player card; prose below cards matches online (`Players average … rated games and … different opponents.`). |
| 2026-07-01 | **Perf-rating LB W/L color** — Best · Top 100 · Perfect tables: W/L cells use `.blue` / `.red` via `amiga_profile_tournament_wdl_cell()`. |
| 2026-07-01 | **Rating LB W/L color** — Wins/Losses cells on online + Amiga `/leaderboards/rating.php` use `.blue` / `.red` (via `k2_fmt_wdl_count()`); zero stays plain. |
| 2026-06-29 | **Amiga games All games — empty filter fix** — `amiga_realm_games_all_request_state()` no longer maps missing `country`/`rival` to `Unknown` (was filtering to zero games); nation-pair filter applies only when both params are present in the URL. |
| 2026-06-29 | **Amiga tournament hero — feast grid** — entity pages + live view use country-hero layout (host flag 124×93, name, Date/Players/Games/Winner stats); `amiga_tournament_hero.php` + `amiga_tournament_winner()`. |
| 2026-06-29 | **Amiga import — Ian Ka / Klaus L aliases** — merge into Ian K and Klaus Le; all game players now have country (471 → 469). |
| 2026-06-29 | **Amiga import — player fixes** — Diego L → Italy; Joerg D/S alias merge into Jorg D/S (`PLAYER_NAME_ALIASES`); player count 471 (−2 dupes). |
| 2026-06-30 | **Amiga Games All — full filter panel** — `amiga/games/all.php`: online-parity filters (player search/pickers, opponent row, faceted GD/Sum/TS, year+mode) + segment bars All/World Cup + All/Videos + **Host country** listbox (`host=`); rivals `country`+`rival` preserved; Reset pill; `k2-realm-games-filters.js` Amiga realm. **Search ×** clears player/opponent picker only (online + Amiga All games). |
| 2026-06-29 | **Amiga import — Norway player countries** — `PLAYER_COUNTRY_OVERRIDES` in `import_corrections.py` for Ingvald E, Kjetil D, Oyvind H (missing L2 identity); wired into import manifest; local `import --incremental` applied. |
| 2026-06-29 | **Docs — status hygiene** — corrected stale Current focus: WC HoF **complete** (WCH-1…8), country Rivals **shipped** (CRV-1–7), online profile feast **complete** (Amiga profile = polish only); fixed WC10 row in WC LB policy + WC hub out-of-scope; `player-profile-feast.md` archived v1+ backlog section. |
| 2026-06-29 | **Amiga WC player stats — LB wing retired** — removed World Cups tab from Leaderboards; `/amiga/leaderboards/world-cups/*` 302 → hub `world-cups/players/*`; HoF/profile/tournament-honours links retargeted; deleted LB-only nav/shell includes. |
| 2026-06-29 | **Docs — WC nav structure sweep** — policies, url-routes, navigation-model NM7, profile-v0, HoF/perfect-event/staging handoff aligned to hub-only Player stats. |
| 2026-06-29 | **Amiga World Cups hub wing order** — nav tabs: Chronology · Player stats · Country stats · Tournament stats (`amiga_world_cups_hub_nav.php`; WCH4 policy). |
| 2026-06-29 | **Amiga country Rivals — All games anchor** — rivals H2H “All rated games” link + chart drill-downs now append `#matching-games`; `amiga/games/all.php` gains matching anchor above table (parity with player games / OCG H2H). |
| 2026-06-29 | **Amiga export — WC HoF tables missing on staging** — `export_ko2amiga_db.ps1` omitted `amiga_wc_hof_{snapshots,present}` (SCH-046); added to schema + parts 39–40; re-export ready for WinSCP + browser import. |
| 2026-06-29 | **Amiga player Videos tab** — video count moved from footnote below table to `k2-player-games-status` above table (`amiga_player_videos_render.inc.php`); footer removed from `amiga/player/videos.php`. |
| 2026-06-29 | **Jukebox playlist refresh** — removed Lotus Esprit *Track 02* from `playlist.json`; player now re-fetches playlist on window focus / launcher ping (`cache: no-store` + `?v=filemtime`) so a reused popup window picks up edits instead of keeping an in-memory list. |
| 2026-06-29 | **Amiga Opponents country H2H pair detail** — race table mirrors vs-player (goals/DD); reverse country TPR vs hero in perf batch (`performance_rating_vs_hero`). |
| 2026-06-29 | **Docs — three matchup grains** — player vs player / player vs country / country vs country cross-ref in rivals + OCG policies, url-routes, navigation-model, hub policy; domestic A→A contrast documented. |
| 2026-06-29 | **Amiga country Rivals — exclude domestic row** — hero→same-country rival dropped from all four wings + H2H default/redirect; Denmark defaults to Sweden. |
| 2026-06-29 | **Amiga country Rivals shipped (CRV-1–7)** — nation-pair roll-up from matchup tables; wings `country/rivals/{h2h,wdl,goals,dds}`; W/D/L·Goals·DDs k2-tables; H2H poster/moments/charts (`nation-pair` grain); games filter `country`+`rival` on All games; DK→SE parity 131/40/17/74. |
| 2026-06-29 | **Amiga country Rivals (CRV-0)** — nation-pair policy + implementation plan locked; second roll-up from matchup tables; `country/rivals/{h2h,wdl,goals,dds}` + `rival=` param. |
| 2026-06-29 | **Amiga Opponents country grain OCG-5** — country H2H poster/pickers/detail + reverse country TPR on pair strip; `country/h2h.php` deep links. |
| 2026-06-29 | **Amiga Opponents country grain OCG-1 + OCG-3** — `amiga_player_opponents_country_{load,perf}_lib.php` roll-up from pair matchup rows + batch country TPR; country W/D/L table (`amiga_player_opponents_country_tables.php`); Games links via `opp_country=`; player 73 parity probe 264=264 games. |
| 2026-06-29 | **Amiga Games Highlights sort fix** — four board default sort columns were off by 1 (copied online indices; Amiga table adds Tournament + Phase). Boards now resolve via `amiga_realm_games_all_sort_col_index()` (GD=9, Sum=10, TS=11 with rank). |
| 2026-06-29 | **Amiga HoF single-game → Highlights** — career + WC §4.6 value cells link to `/amiga/games/highlights.php` boards (mirror online); WC rows use `scope=world-cup`. Highlights page: **All games | World Cups** segment beside board pills. |
| 2026-06-29 | **Amiga WC HoF — ratio rows polish** — HoF ratio/average WC rows (pts/game, win rate, gf/ga/gd per game, goal ratio, DD rate, CS rate) now render `-` for date (match career ratio rows; ratios are "best as of now/snapshot", no churny achievement date). Fixed holder-flag prefetch regex in `amiga_wc_hof_read_lib.php` so `*PerGameID` ratio holders (e.g. BestWcGoalsAgainstPerGameID → Gianluca T) get country flags. Ratio values confirmed current-best (no preserved peak). Stored `*Date` cols kept inert (not surfaced anywhere). |
| 2026-06-29 | **Amiga WC Hall of Fame shipped (SCH-046, WCH-1…8)** — `amiga_wc_hof_{snapshots,present}` + 6 slice cols; 28 WC records (sparse WC-only snapshots + present + time travel); Python (`wc_hof.py`/`wc_slice_awards.py`/`wc_hof_persist.py`) + **PHP finalize parity** (`amiga_wc_hof_lib.php`/`amiga_wc_slice_awards_lib.php`); HoF UI WC block + LB deep links. `MostWcPlayed` **migrated off** career generalstats/realm — legacy DDL cols **dropped** (`028` no longer adds + idempotent `schema_bundles.py` helper; realm read lib cleaned). `prove` green incl. `verify-wc-hof` + `verify-hof-geo-year` + `verify-realm-snapshots`. Status **Implemented** — [`amiga-wc-hof-policy.md`](docs/amiga-wc-hof-policy.md). |
| 2026-06-29 | **Amiga tournaments index lede** — chapter copy with WC 2001 Dartford link + live tournament count. |
| 2026-06-29 | **Amiga tournaments index filters** — segment toggles in inner wrapper (`gap: 12px`); listbox pickers stay at parent 6px gap. |
| 2026-06-29 | **Amiga WC HoF implementation plan** — [`amiga-wc-hof-implementation-plan.md`](docs/amiga-wc-hof-implementation-plan.md) WCH-0–8 slices ready to execute. |
| 2026-06-29 | **Amiga WC HoF policy** — locked [`amiga-wc-hof-policy.md`](docs/amiga-wc-hof-policy.md): 28 WC record rows, sparse WC-only snapshots, 20-game ratio gate; implementation plan next. |
| 2026-06-29 | **Amiga Activity summary** — removed Decided games headline card (`amiga_activity_summary.php`). |
| 2026-06-29 | **Amiga WC country Participation sub-wing** — new tab after Results (`participation.php`); Results wing match-outcome columns only. |
| 2026-06-29 | **Amiga WC country Results — drop Pts per WC column** from results table UI. |
| 2026-06-29 | **Amiga WC country Results tooltips** — natural copy, no W/D/L help, no "player-games" on results wing. |
| 2026-06-29 | **Amiga WC country honours medal tooltips** — concise gold/silver/bronze copy (aligned with player WC honours wing). |
| 2026-06-29 | **Amiga WC stats geography — Intl games tooltip** — title International games; body updated in `amiga_world_cup_stats_table.php`. |
| 2026-06-29 | **Amiga perf-rating LB — W/D/L tooltips removed** on Best · Top 100 · Perfect tables. |
| 2026-06-29 | **Amiga perf-rating Top 100 lede** — drop "in the realm at this date" from wing copy. |
| 2026-06-29 | **Amiga peak-rating LB — Peak rank tie-break** — `data-k2-sort-tie-value` from peak rank date (first attainment wins on equal rank). |
| 2026-06-29 | **Amiga goals LB tooltips** — Amiga-specific helpers; drop "rated" from column help (Elo unchanged). |
| 2026-06-29 | **Amiga WC DDs + Opponents LB tooltips** — WC-specific copy on both player wings (aligned with goals/results). |
| 2026-06-29 | **Amiga WC goals LB tooltips** — WC-specific copy for all goal columns (was career/rated-game helpers). |
| 2026-06-29 | **Amiga WC results LB tooltips** — Games, Pts (Points label), W/D/L help removed on results wing. |
| 2026-06-29 | **Amiga WC honours LB tooltips** — WCs, medals, Podiums, Perfect copy aligned with tournament honours wing (`lb_column_help.php`). |
| 2026-06-29 | **Amiga WC stats participation — column order fix** — `amiga_world_cup_stats_columns_for_view()`: Games is anchor index 3 (not 4); participation wing order Tournament · Year · Players · 1st WC · Games · … |
| 2026-06-29 | **Amiga tournament honours LB tooltips** — Events, medal, Podiums, Perfect copy simplified in `lb_column_help.php`. |
| 2026-06-29 | **Amiga calendar-geo LB tooltips** — Peak games, host countries, countries faced, countries beaten copy tightened in `lb_column_help.php`. |
| 2026-06-29 | **Amiga tournament honours LB sort** — default gold → silver → bronze → events (matches WC honours pattern); `amiga_lb_tournament_honours_order_sql()` shared by present + TT reads. |
| 2026-06-29 | **H2H poster fighter card names** — nowrap + `width: max-content` card (320–380px); fixes long Amiga names (e.g. Christopher D + flag) wrapping in `player-opponents-h2h-poster.css`. |
| 2026-06-29 | **Amiga WC event-stats Medal column** — Gold/Silver/Bronze use `amiga_wc_podium_metal_label_markup()` (same gradient metal as player hero). |
| 2026-06-29 | **Amiga tournament event-stats `#` rank column** — first col autorank (`data-k2-autorank="true"`); Player anchor col 1; default Pts sort col 11; ranks follow active sort (SSR = Pts desc tiebreak). |
| 2026-06-28 | **Amiga game table flags fix** — player games + single-game queries now SELECT `country_a`/`country_b`/`tournament_country`; pages load `amiga-tournament.css` (helpers were correct; rows lacked data). |
| 2026-06-30 | **Amiga tournaments index — Winner + Winning country filters** — faceted archive listboxes on `/amiga/tournaments.php` (`winner`, `winner_country`); pill URLs + summary line carry both; host-country facet now omits self (parity with year facet). |
| 2026-06-28 | **Amiga tournament link strays** — country roster Last event + player videos Tournament col now `amiga_tournament_link()` (was hand-built `<a href>`). |
| 2026-06-28 | **Amiga inline table flags shipped** — retired flag-only Country columns; `k2_amiga_country_roster_link()` + `k2_amiga_lb_country_cell()`; `amiga_tournament_link()` → `k2-link-star`; 17 table surfaces migrated per [`k2-table-entity-links-policy.md`](docs/k2-table-entity-links-policy.md). |
| 2026-06-28 | **K2 table entity links policy** — [`k2-table-entity-links-policy.md`](docs/k2-table-entity-links-policy.md) locked; implementation same day (inline flags). |
| 2026-06-28 | **Amiga table flags — API tidy** — retired `k2_amiga_country_flag_linked()`; single primitive `k2_amiga_country_flag_link()` (decorative false default); caption passes tgame class + decorative true. |
| 2026-06-28 | **Amiga Tournaments hub — time travel** — TT hub tab; catalog rows ≤ cutoff (`amiga_tournament_index_rows`); filter pills + listbox preserve `as=` via `k2_amiga_route('amiga-tournaments')`; hub chapter suppressed under TT. Policy T13b = present hub minus editorial (News · Live · future Misc). |
| 2026-06-28 | **Amiga perf-rating LB — shipped** — Best · Top 100 · Perfect sub-wings (`performance-rating/{best,top,perfect}.php`); W-D-L columns; ∞ on Perfect (183 rows); top=100 fixed set; legacy `performance-rating.php` 302; TT on all paths. |
| 2026-06-28 | **Amiga perf-rating LB policy** — locked Best · Top 100 · Perfect sub-wings (folder + segment nav), W-D-L columns, ∞ on Perfect, fixed top-100 set, TT reads. [`amiga-performance-rating-leaderboard-policy.md`](docs/amiga-performance-rating-leaderboard-policy.md) |
| 2026-06-28 | **Amiga perfect event — shipped (SCH-045)** — `is_perfect_event` on snapshots; career `perfect_events` + HoF **Most perfect events**; catalog **Perfect run** filter; honours LB + WC honours **Perfect** column; `verify-perfect-event` in `prove` (183 participations; Oliver St 24). |
| 2026-06-28 | **Player Videos — time travel index** — game index ≤ cutoff via `amiga_snapshot_rated_game_cutoff_and_sql`; opponent facets on filtered set; unwired note suppressed (`k2AmigaPlayerTabWiredAtCutoff`). |
| 2026-06-28 | **Player Videos — time travel tab link** — `amiga_player_videos_url()` now passes query via `k2_amiga_route(..., $params)` once (was double-`?` when `as=` active → dropped `id` → blank page). |
| 2026-06-28 | **Amiga player tournaments — Perfect run filter** — chrome-tab segment (`perfect=with-participant`, `is_perfect_event`); parity with catalog index. |
| 2026-06-28 | **Amiga tournaments index — filter summary line** — plain-language count above table (`amiga_tournament_index_list_summary()`); replaces footnote "(filtered)". |
| 2026-06-28 | **Amiga player tournaments — segment filter layout** — Event chrome tabs + Host country/Year listboxes (catalog index parity); retired bordered `.k2-player-tournament-filters` panel. |
| 2026-06-28 | **Amiga tournaments index — Host country + Year filters** — archive listboxes on `/amiga/tournaments.php` (faceted counts, pill URLs carry `country`/`year`/`k2_sort`); shared games filter stack. |
| 2026-06-28 | **Amiga HoF — retire BiggestPeakRating** — dropped from `013`/`027` DDL + writers; HoF “Highest peak rating” = read-time `MAX(PeakRating)` + `peak_rating_tournament_id` date; `verify-hof-peak-rating-holder` in `prove`. |
| 2026-06-28 | **Amiga staging export — stale part cleanup** — `export_ko2amiga_db.ps1` deletes old `ko2amiga_*.sql` in `_import/` not listed in manifest after each run (keeps `ko2amiga_db.sql`); avoids ~100 MB of orphan parts from renumbered exports. |
| 2026-06-28 | **YouTube embeds — origin param** — `k2_youtube_embed_url()` + site-wide `<meta name="referrer">`; fixes “Sign in to confirm you’re not a bot” / Error 153 on tournament Videos, join promo, game placeholder. |
| 2026-06-28 | **Amiga HoF — LB deep-link sort indices** — fixed eight off-by-one `k2_sort` targets in `amiga_records_hof_links.php` (calendar-geo peak/host/faced/beaten, tournament honours events/gold, WC honours played). |
| 2026-06-28 | **Amiga HoF — dual-holder flags fix** — country batch lookup now includes `*IDA`/`*IDB` holder columns (was `str_ends_with(..., 'ID')` only); fixes missing flag on second player in biggest draw / sum of goals rows. |
| 2026-06-28 | **Amiga HoF — player country flags** — holder column uses `k2_amiga_lb_player_cell()` (batch `amiga_players.country` lookup); dual-holder rows (biggest draw / sum of goals) get flag per player. |
| 2026-06-27 | **Country roster — # column** — first col shows 1…n by Elo desc (default sort); `data-k2-autorank="true"`; existing **Rank** col unchanged (global `elo_rank`). |
| 2026-06-27 | **Country roster — flag in Player column** — `amiga_countries_roster_table.php`: Flag column removed; flag before player name via `k2_amiga_lb_player_cell()`; Elo default sort col 1, anchor Player col 0; fixed skip-initial-sort attr (was 11). |
| 2026-06-27 | **WC player stats tables — flag in Player column** — all five sub-wings (Honours, Results, Goals, DDs, Opponents) in `amiga_wc_players_table.php`: Country column removed; flag before player name via `k2_amiga_lb_player_cell()`; covers hub + Leaderboards → World Cups dual surface. |
| 2026-06-27 | **Amiga career LBs — flag in Player column** — all eight wings (Rating, Peak, Goals, DDs, Victims, Perf., Tournament honours, Calendar & geo): Country column removed; flag before player name via `k2_amiga_lb_player_cell()`; default sort column indices shifted −1 where needed. |
| 2026-06-27 | **Table flags 20×15** — `k2-amiga-country-flag-img` default; player hero stays 24×18 via wrapper override. |
| 2026-06-27 | **Table flag centering** — centered country cells: roster link `inline-block` so 24×18 flags honor `text-align: center` (all Amiga flag columns already had `k2-table-cell--center`). |
| 2026-06-27 | **Amiga table flags unified** — `k2-amiga-country-flag-img` (24×18, 2px radius) via flag helper default; tables + player hero share class; large/tgame flags keep custom class. |
| 2026-06-27 | **Player hero country gap** — +14px `margin-left` before Country stat column (after Games). |
| 2026-06-27 | **Player hero stat centering** — feast shell: labels + values centered in each stat column (Rank, Rating, Games, Country, WC medals). |
| 2026-06-27 | **Player hero flag baseline** — 3px `margin-bottom` on country link lifts 18px flag to optical flush with stat numerals (24px value row). |
| 2026-06-27 | **Player hero flag simplify** — 28×21 (match table), no border/crop; wrapper keeps 2px radius + overflow clip; hover ring unchanged. |
| 2026-06-27 | **Country hero flag corners** — doubled flag `border-radius` (`--k2-radius-sm`, 4px; was half). |
| 2026-06-27 | **Amiga player hero WC medals** — sparse gold/silver/bronze counts (gradient metal styling, 20px lead gap) to the right of country flag on all player-wing heroes; reads `amiga_player_slice_totals` / snapshot at cutoff via `amiga_player_wc_medal_counts()`. |
| 2026-06-27 | **K2 tooltip policy — embed exception clarified** — T1 scoped to site-owned chrome only; new § Cross-origin embeds: YouTube player tooltips not K2-styleable, iframe `title` kept; reverted brief aria-label-only embed experiment. Site-owned WC caption Elo links still use K2 tooltips. |
| 2026-06-27 | **WC Videos spotlight caption tooltips** — pre-game Elo links above the player use `data-k2-help` + K2 dark tooltip; `k2TableInitHelpTooltips()` re-binds after in-session caption swap. Games index Rating A/B headers were already compliant. |
| 2026-06-27 | **WC Videos spotlight spacing** — player now **fills viewport height** when scrolled to it: removed old **52rem** spotlight max-width (was leaving a large empty band below); width `min(vh-chrome×16/9, 100vw−2rem)`. Vertical rhythm via CSS vars: **0.75rem** caption↔viewport top (`scroll-margin-top`), **0.15rem** caption↔video (rests on player), **0.5rem** below video (`padding-bottom`). |
| 2026-06-27 | **WC Videos spotlight polish** — caption row gets **transparent** bg + **no row hover** (overrides `.k2-table tbody tr` surface/hover); its left edge + the "↑ All videos" right edge **track the player box** (same width cap + `margin-inline:auto`). "↑ All videos" **moved out of the caption row to the player's top-right corner** (absolute in `position:relative` `.k2-game-page__video-wrap`, `top:0;left:100%`; no small-screen fallback by request — nice-to-have). Caption now **flush to viewport top** (`scroll-margin-top:0`) with a **minimal gap to the player** (`0.2rem`). |
| 2026-06-27 | **K2 tooltip policy sweep** — fixed native `title` on Amiga profile perf-rating labels, tournament phase links, bracket scores, status box-art link; removed redundant H2H heatmap scale `title`s; milestone charts use `T.mergeTooltip`; extended `audit_k2_table_compliance.py` with PHP/JS/Chart tooltip passes. |
| 2026-06-27 | **WC Videos spotlight caption → headerless scoreboard.** For game videos the plain `Phase · A vs B` label above the player is replaced by a one-row, headerless `k2-table--tournament-games` table that mirrors the index Games row: linked game id · [Phase] · 🇦 Player A *(pre-game Elo)* · A · B · Player B *(pre-game Elo)* 🇧, winner's goal in blue, per-side flags only when the game has country data. Each Elo sits in parentheses as a link to the **rating leaderboard table top** (`/amiga/leaderboards/rating.php#k2-lb-table`). New `amiga_tournament_videos_wc_game_caption_html()` builds it; play buttons carry it in `data-spotlight-html` so in-session picks swap the caption via `innerHTML` (text `data-spotlight-label` fallback for Atmosphere/extras, which keep the simple caption). Cold load renders it server-side in `wc_body`. Spotlight label container switched `<p>`→`<div>`; head now `align-items:center`. Browser-verified cold + in-session + rating href. [`k2-embedded-video-page-policy.md`](docs/k2-embedded-video-page-policy.md) §2.3. |
| 2026-06-27 | **Amiga player games — performance rating tooltip** — status-line `Performance rating` above table now uses `data-k2-help` + `data-k2-tooltip-label` (K2 dark tooltip via `k2-table.js`); removed native `title`. |
| 2026-06-27 | **K2 tooltip policy shipped** — new [`k2-tooltip-policy.md`](docs/k2-tooltip-policy.md) (T1–T7 locked rules + reference table); wired into AGENTS, kool-workspace, table checklist, design-direction; `audit_k2_table_compliance.py` flags `<th title=`; fixed WC videos games table Rating A/B headers. |
| 2026-06-27 | **Amiga tournament games table tooltips** — GD/Sum/TS/Rating/Elo/Fav ES/Adjustment headers now use `data-k2-help` + `k2-table-tooltip` (same copy as Games hub); removed native `title` attrs. |
| 2026-06-27 | **Navigation model (NM1–NM6) + entity-page refactor shipped** — new [`navigation-model.md`](docs/navigation-model.md) states the invariants: hub bar always present (TT under snapshot picker); active pill only on hub/sub-hub **places**; **entity pages** (game/player/tournament/country/milestone) live at the realm root in a **singular** namespace with **no active pill** (plural = hub place). **Code:** (1) tournament detail `amiga_tournament_page.php` pill neutralized (`$k2AmigaHubTabActive=''`); URL already correct (`tournament/` vs `tournaments.php`). (2) Country roster relocated `amiga/countries/roster.php` → **`amiga/country/roster.php`** (singular entity namespace) + new **Rivals** segment (`amiga/country/rivals.php`, placeholder; country-vs-country H2H later); shared shell `includes/amiga_country_page.php` + segment `includes/amiga_country_nav.php`; new routes `amiga-country-roster`/`-rivals` + `k2_amiga_country_rivals_href()`; `k2_amiga_country_roster_href()` repointed so all CH9 flag cells follow; old path 302s (`k2_amiga_legacy_redirect`). Countries **index** keeps its active pill. Fixed stale "player pages replace hub tabs" claim in `hub-ia-agreement.md`. Docs: `url-routes.md`, `amiga-countries-hub-policy.md` (CH3/CH19/CH23/CH24/§6/§9/§11), `amiga-profile-v0.md`, `k2-nav-implementation-checklist.md`, `AGENTS.md`. Lint clean; browser-verified (roster + rivals + 302 + index pill + tournament no-pill). |
| 2026-06-27 | **Profile rating chart — Stepwise/Connected toggle** — added the H2H rating-compare line-style toggle to the solo profile Elo rating chart (Amiga profile markup `amiga_profile_blocks.php`, wrapped view + line-style toggles in `.pm3d-chart-toolbar`). `player-rating-chart.js` now reads/applies a line style (`stepped`/`smooth`) on both By date + By tournament # views, rebuilds on toggle, and scopes the view-toggle loop to `[data-view]`. **Default = Stepwise** on Amiga profile; online profile (no toggle markup) defaults to Connected (`smooth`), unchanged. |
| 2026-06-27 | **WC country honours — WC entries column** — Honours sub-wing now shows **WC entries** (`wc_participations`) after WCs; same stored field + tooltip as Countries index. |
| 2026-06-27 | **Amiga game.php header cleanup** — dropped tooltips on Date, A/B goals, Tournament, GD, Winner; goal margin column renamed Diff → GD. |
| 2026-06-27 | **Amiga player games table** — default sort ID desc (named constants); date col quiet when sorted by date; +2px vertical body padding. |
| 2026-06-26 | **Countries roster sort — float rating** — roster order + k2-table Elo sort use full `Rating`; display stays rounded; fixes tied-Elo rows (e.g. Jon G above Ben G at year:2018). |
| 2026-06-26 | **Countries roster hero — option 1 layout** — player-feast grid (flag left 72×54, name + stats right); plain Gold/Silver/Bronze labels; dropped 1st/2nd/3rd + gradient medal typography in hero. |
| 2026-06-26 | **Countries roster hero — feast styling** — country hero mirrors player feast shell: rectangular flag in media slot (accent frame + glow), link-star name, labeled stat row (Players · Games · WC entries · medals). |
| 2026-06-26 | **Jukebox FAB tooltip** — launcher hover uses shared `k2-table-tooltip` (`data-k2-help` + `k2_table_js_enqueue()`); dynamic **Playing: …** via launcher JS; native `title` removed. |
| 2026-06-26 | **Countries roster — drop chapter block** — removed title + lede above country hero on `roster.php` (hero already names country; 404 path keeps minimal chapter). |
| 2026-06-26 | **Amiga LB — drop chapter block (present)** — removed `k2_hub_chapter.inc.php` from `amiga_lb_nav.php` (Leaderboards title + placeholder lede; TT already skipped). |
| 2026-06-26 | **World Cups hub — drop chapter lede (present)** — removed `k2_hub_chapter.inc.php` from WC shell (present had still shown title + lede; TT already skipped); stripped dead `$k2AmigaWorldCupsChapterLede` from wing entry files. |
| 2026-06-26 | **World Cups Chronology — flat URL** — wing 1 moved from `chronology/index.php` to `world-cups/chronology.php` (no sub-wings under Chronology); legacy folder path 302; routes + hub nav updated. |
| 2026-06-26 | **Amiga time travel — snapshot picker z-index** — `.k2-amiga-time-travel` now stacks at 1220 (above `.k2-hub-bar` 1210, below header 1300) so the archive listbox dropdown is not painted behind hub tabs. |
| 2026-06-26 | **World Cups — retired stats Podium sub-wing** — duplicated Chronology medal columns; removed from tournament-stats nav + table renderer; `stats/podium.php` 302 → chronology. Medal display stays on wing 1 only. |
| 2026-06-26 | **World Cups chronology — column order** — Players now before Games in wing 1 events table (`amiga_world_cups_events_table.php`; policy §4.1 list row). |
| 2026-06-26 | **Amiga LB wing tab order** — World Cups now second after Rating; Tournament honours third (`includes/amiga_lb_nav.php`). |
| 2026-06-26 | **Highlights boards — full column parity with the hub tables.** Extended the 4 `games/highlights.php` boards (most goals · biggest draws · biggest wins · top score) from the trimmed compact layout to the **same columns as `games/all.php`**: GD · Sum · TS now always shown, plus the Elo block (Rating A · Rating B · Elo Diff · Fav ES · Adjustment · Adjustment lost). Extracted a shared `k2_rated_game_elo_cells()` helper (`includes/k2_rated_game_row.php`) used by **both** the full row and the compact (highlights) row, so the rating/adjustment logic lives in one place; the compact row now renders the Elo cells after TS (keeping its leading `#` rank column + autorank). Fixed full layout `# ID Date TeamA A B TeamB GD Sum TS RatingA RatingB EloDiff FavES Adj AdjLost`; updated each board's `default_sort_col` (most_goals/biggest_draws Sum=8, biggest_wins GD=7, top_score TS=9) so **default sorts are preserved**. Dropped the per-board GD/Sum hide helpers; `colspan` 16; new tight-width CSS for `--rating-a/-b/--elo-diff/--fav-es/--adjustment/--adjustment-lost` (theme.css). Lint clean; all 4 boards browser-verified (columns + default-sort column highlight). |
| 2026-06-26 | **Online games tables — scoreboard treatment (cosmetic).** Ported the Amiga tournament-table decisions to the shared full-game row `k2_rated_game_row_html()` **and the compact variant `k2_rated_game_row_compact_html()`** (`includes/k2_rated_game_row.php`): new opts `show_winner` (default true), `highlight_winner_goal`, `team_a_align`. Full row now builds cells with a running index so the **Winner column can be dropped** without breaking sort indices; added `withWinner` param to `k2_rated_game_hub_sort_col_map()`/`k2_rated_game_sort_col_index()`. Applied (`show_winner=false` + `highlight_winner_goal` + `team_a_align=right`) to **`games/all.php`**, **`games/recent.php`**, the **league-period games table** (`k2_league_period_page.php`), and **all 4 Highlights boards** (`games/highlights.php` via `games_highlights_helpers.php` — most goals · biggest draws · biggest wins · top score; their `default_sort_col` is unchanged because Winner was always the last column): Team A right-aligned (hugs the centre A·B columns), `B` header left-aligned to match its body cells, winning goal shown **blue + bold** (`<strong class="blue">`, no new CSS; draws highlight neither), Winner column removed (winner still explicit in the Adjustment +/− columns on the full row). `game.php` single-game detail **keeps** its Winner column (defaults unchanged). Online Date keeps real `H:i` clock (unlike synthetic Amiga). Lint clean; verified `games/all.php` + all 4 highlight boards in browser. |
| 2026-06-26 | **Amiga tournament Games table — comprehensive scoreboard.** `/amiga/tournament/games.php` (`amiga_tournament_render_games_table()`) rebuilt from the thin `#·Player A·Score·Player B` layout into the realm's first **neutral comprehensive games table**: re-backed `amiga_tournament_games_rows()` with `amiga_rated_games_from_sql()` (full Elo view + `country_a/b`); columns ID · [Phase] · Player A · A · B · Player B · GD · Sum · TS · Rating A · Rating B · Elo Diff · Fav ES · Adjustment (win+loss) — **no date/order column** (rows arrive in event chronology, `skip-initial-sort`). Two-goal-column scoreboard mirrored around the centre (Player A right-aligned, Player B left); **conditional player flags** (`amiga_tournament_games_show_flags()` — only when ≥2 countries in the pool; flag left of A, right of B); winner's goal cell emphasised (no Winner column). CSS in `amiga-tournament.css` (`.k2-amiga-tgame-*`). **Recovery note:** mid-edit the disk filled and truncated `amiga_tournament_lib.php` to 0 bytes; `git checkout` restored the pre-Videos committed version, so the uncommitted Videos-tab integration in that file was re-applied by hand (`path_for_view`/`view_from_request` `videos` cases, `amiga_tournament_videos_url()`, three redirect allow-lists). |
| 2026-06-26 | **Amiga game page date column** — `/amiga/game.php` now shows event day only (`M j Y`); synthetic clock time removed. Shared `amiga_player_game_date_html()` moved to `amiga_rated_game_row.php` (same formatter as player games list). |
| 2026-06-26 | **Doc sweep (Turbo removal / carry-scroll / jukebox).** Corrected docs that still described Turbo as live: `k2-turbo-page-init-checklist.md` carry-scroll + hash-landing sections now describe the **pre-paint cloak** in `k2_carry_scroll_restore.php` (was "restore on `turbo:render`, no cloak" — the opposite of current); Jukebox special-case + boot-shim + reference rows point at `k2-page-boot.js` / `k2-jukebox-popup.md`; checklist test steps de-Turbo'd. `PROJECT_MAP.md` row reframed historical + added jukebox-popup row. `k2-jukebox-popup.md` updated (centred 500px window, raise/behind toggle + pointerdown race note, focus/blur messages, ping no longer steals focus, tint sync). `amiga-countries-hub-policy.md` "Turbo hash landing" → "Hash landing". Stale code comments fixed in `k2-jukebox.css` + `k2-carry-scroll.js`. (Left intact: `window.__flag` guard comments — accurate history, guards kept as harmless hygiene; dead `turbo:before-cache` no-op listeners.) |
| 2026-06-26 | **Jukebox popup — centred + FAB raise/behind toggle.** (1) `window.open` now computes centred `left`/`top` from `screen.availWidth/Height` (+`availLeft/Top` for multi-monitor) in `k2-jukebox-launcher.js` `buildFeatures()`. (2) **FAB now toggles stacking**: launcher keeps a live `jukeboxWin` handle + `jukeboxFocused` flag; click raises the popup if it's behind, or sends it behind (`win.blur()` + `window.focus()`) if it's in front — instead of only ever raising. State is tracked via new `focus`/`blur` BroadcastChannel messages the player emits on window focus/blur (+ initial `document.hasFocus()`). **Race fix:** pressing the FAB focuses the main window, which blurs the popup; that blur message would flip `jukeboxFocused` to false before the `click` fired (symptom: window dropped behind while the button was held, then popped back on release). The launcher now snapshots the front/behind state on `pointerdown` (synchronous, before the async blur message arrives) and uses that for the click decision. (3) **Fixed focus theft**: player's `ping` handler no longer calls `window.focus()` (the FAB pings on every main-tab load, so the popup was jumping to front on each navigation) — it now only replies with `state` + current focus. Note: there is no standard "lower window" API, so "send behind" relies on `blur`+opener-`focus` (works in Chromium; may vary by browser). |
| 2026-06-26 | **Jukebox popup — scheduled tint + 2-row transport.** (1) **Scheduled 6-hour tint now follows in the popup while open** — `theme_boot_head.php` adds a self-rescheduling boundary tick (`K2TintSchedule.msUntilNextPeriod` → `setTimeout` → re-apply + reschedule). It self-cancels once `window.__k2RealmSwitchBound` is set, so on pages with the tint picker `realm-switch.js` stays the sole scheduler (and re-syncs pills); the popup (no realm-switch) gets its own. Was: popup only picked up the schedule on open. (2) **Control panel relaid out** in `k2-jukebox.css` — `.k2-jukebox__transport` is now a 2-row `grid-template-areas` (`controls` centered on row 1; `shuffle` left + `volume` right on row 2). Play button 36→42px (outlined accent circle, no fill); prev/next 32→38px; controls gap 18px. Removed the now-conflicting `max-width:520px` 3-column transport override (popup is always ≤520). Verified at 360×500. |
| 2026-06-26 | **Jukebox popup polish** — (1) default window height 620 → **500** (`FEATURES` in `js/k2-jukebox-launcher.js`) so a fresh popup no longer dips under the Windows taskbar; (2) **shuffle-active fill** now matches the milestone tier-selector pill — `.k2-jukebox__shuffle.is-active` uses `background: color-mix(in srgb, var(--k2-accent) 14%, var(--k2-bg-elevated))` + `border: color-mix(... 42%, var(--k2-border-subtle))` (was a faint 8%-into-transparent); (3) **live tint sync to the popup** — `includes/theme_boot_head.php` now adds a cross-window `storage` listener that re-runs `K2TintSchedule.applyAccentToRoot(resolveAccent())` when `ACCENT_KEY`/`PERIOD_KEY`/`CLOCK_KEY` change, so changing the tint in the main tab live-retints the jukebox window (and any other open tab). storage events fire only in *other* documents, so the changing window already updates itself; idempotent with `realm-switch.js`'s own listener. Verified: dispatched accent change retints popup amber→pitch→holo; shuffle fill resolves. |
| 2026-06-26 | **Jukebox popup layout** — control panel (now-playing, VU, progress, transport, shuffle, volume) now pinned; only the playlist scrolls in its frame. Popup overrides in `jukebox.php` lock `.k2-jukebox--window` + `.k2-jukebox__panel` to `height:100vh; overflow:hidden` (+ `html{overflow:hidden;scrollbar-gutter:auto}`) so the native window scrollbar is gone and the existing `.k2-jukebox__tracks-wrap` (flex:1; overflow:auto) is the only scroller — matches the old docked behaviour. Verified at 360×620. |
| 2026-06-26 | **Tooltip z-order fix** — `.k2-table-tooltip` raised `z-index 1000 → 1500` so `data-k2-help` tooltips (e.g. the Time-travel toggle warning) float above hub bar (1210), header (1300) and jukebox FAB (1400). Chose this over lowering the hub bar because the hub bar's z-index exists to keep the **tint picker panel** above sticky page content (table headers z 50–80), not to sit above other chrome. Stacking ladder now: page < hub bar 1210 < header 1300 < jukebox FAB 1400 < tooltips 1500. |
| 2026-06-26 | **Carry-scroll flash fixed for full-page nav (post-Turbo).** Without Turbo the browser painted the page top (wordmark) before JS scrolled down. `k2_carry_scroll_restore.php` now engages a **pre-paint cloak** (`html.k2-carry-cloak body{visibility:hidden}`) **only when** a carry payload or URL-hash target is pending, applies the scroll inside a rAF loop the moment the document is tall enough (or the DOM is fully parsed — handles a deep offset onto a shorter page), then reveals. Hard 700ms timeout + `load` listener guarantee it can never stay hidden. `html` already paints `--k2-bg-page`, so the brief hold is a solid theme color, not white. Dropped the dead `turbo:render`/`turbo:load`/`suppressTurboScrollToTop` code. Verified locally: carry to 1500 and 5000 restore exactly with no flash; normal navs never cloak. |
| 2026-06-26 | **Turbo Drive removed — jukebox is now a popup window.** Hotwired Turbo (and its whole bug class: body-script re-exec stacking listeners, cloak/snapshot races on the TT LED stamp, tint "dead picker", carry-scroll flash) is gone. Every navigation is a normal full page load again. Gapless music now lives in a **separate popup window** (`/jukebox.php`, owns the `<audio>`, survives main-tab navigation). Site shows a floating **FAB launcher** (`includes/k2_jukebox.php` + `js/k2-jukebox-launcher.js`) that opens/focuses the window via `window.open('','k2jukebox')` (synchronous, no popup-block; reuses existing window via same-origin `__k2JukeboxReady` check). Player = `js/k2-jukebox-player.js`; FAB mirrors now-playing via `BroadcastChannel('k2-jukebox')`. **Boot shim:** `js/k2-turbo-boot.js` → `js/k2-page-boot.js` — keeps the `k2OnPageReady`/`k2PageReady`/`k2:page-ready` API (dispatched once per full load) so all consumers (k2-table, charts, filters, player-search, carry-scroll) work unchanged. **Deleted:** `turbo.es2017-umd.js`, `k2-turbo-boot.js`, `k2-turbo.css`, old `k2-jukebox.js`. Carry-scroll already had a full-load path (`restoreOnFullLoad`) so it keeps working; its `turbo:*` listeners are now harmless no-ops. Verified locally (browser): tint toggles once/click, sortable table boots, TT LED stamp renders + stays visible (year/month), popup plays playlist. See [`docs/k2-jukebox-popup.md`](docs/k2-jukebox-popup.md). |
| 2026-06-26 | **Turbo body-script listener stacking fix** — `k2-tint-toggle.js`, `realm-switch.js`, `k2-amiga-tt-stamp.js` are body scripts; Turbo re-evaluates them every in-page nav, stacking duplicate `document`/`window` listeners. Tint toggle fired an even number of times → "dead" picker (esp. Amiga, many navs). Bound globals **once** per document (`window.__k2TintToggleBound` / `__k2RealmSwitchBound` / `__k2TtStampBound`); re-eval just re-syncs the swapped DOM. **TT LED stamp:** arrival/LED cloak now releases via `animationend` **or** a fallback timeout (700ms toggle / 1500ms wing), self-heals stale `html.k2-tt-arrival-pending`, and settles transient classes on `turbo:before-cache` so cached snapshots never freeze it hidden. Checklist note added. |
| 2026-06-26 | **Jukebox z-order** — `.k2-jukebox` raised `z-index 1200 → 1400` so the floating player sits above everything. Intended stack (low→high): hub bar nav + tint picker (1210) < site header search box + dropdown (1300) < jukebox (1400). Fixes the header search dropdown painting over the open jukebox panel. |
| 2026-06-27 | **WC Videos cold deep-link scroll + fit + cold-Back.** Four connected fixes from Dagh's share-link feedback. (1) **Scroll on cold load without `#hash`:** `amiga_tournament_page.php` sets a new **generic** `$k2ScrollTargetId` before `k2_head.php`; `k2_carry_scroll_restore.php` reads it as a server-declared **pre-paint scroll target** (after URL hash / pending-hash) → cloak+scroll+reassert exactly like a hash. So hashless `?v=…` URLs (and reloads) land on the player, no flash. In-session URLs are now **hashless** (clean, directly shareable; a future "share" button just copies `location.href`). (2) **Cold Back never leaves the site:** `syncColdLoad` seeds an index entry beneath the clip (`replaceState(index)`+`pushState(clip)`), so Back / the new control returns to the list even from a cold link (and after scroll-up + picking another clip — switch `replaceState`s, cap stays `[index, clip]`). (3) **"↑ All videos" link** in the spotlight label row (zero extra vertical space, only while watching). It is **distinct from browser Back**: `onAllVideos` pushes the index URL, hides the player, clears the highlight, and smooth-scrolls the **tournament hero to the top of the viewport** (global nav scrolls above it) — whereas browser Back keeps the last-watched row highlighted + centred. Real-`href` fallback for no-JS. (4) **Viewport-height cap:** player wrap `width: min(100%, calc((100svh - 4rem) * 16 / 9))` scoped in `amiga-tournament-videos.css` so it never exceeds the viewport at high zoom / short windows; the `4rem` is only the chrome above the player (label + gaps), **not** the fixed jukebox FAB (which floats and consumes no layout), so the player keeps max real estate (shared `game.php` rule untouched). Browser-verified (cold scroll top-aligned, Back→index centred, "All videos"→top no-highlight, 480px-tall player 417px high & fits). [`k2-embedded-video-page-policy.md`](docs/k2-embedded-video-page-policy.md) §1.2/§2.2–2.5. |
| 2026-06-28 | **Amiga WC Videos — WC 2015 Dublin final legs** — swapped game IDs: **pZC2wayc8Hk** (1st leg Andy vs Gianni) → **23054**; **JnBrb7dgJvU** (2nd leg Gianni vs Andy) → **23050** (title home-away, not koatd insert order). `resolve_games.pick_game_ids` prefers player_a/b alignment. |
| 2026-06-28 | **Amiga player Videos wing** — `/amiga/player/videos.php?id=` when manifest has linked match clips; conditional **Videos** pill; reverse-chrono cross-tournament index + opponent listbox filter + tournament embed spotlight/`?v=` deep links. |
| 2026-06-28 | **Amiga tournament videos — human review sign-off** — Dagh reviewed **tournaments index** (all events, WC + non-WC): no obvious mis-assignments in manifest. **Orphans page**: no row left that should map to a tournament Videos tab; remaining unassigned = tutorials/general KO2/dup candidates; excluded = audit; **7** dropped (not KO2). Re-open on new harvest only. Policy §Human review sign-off. |
| 2026-06-28 | **Amiga tournament videos — drop batch (6)** — **BZdkM3tIz8w**, **YsSqNlTRdBE**, **FY0JBr6qu9U**, **JYe18t4jnN0**, **Iq19IVIZ8QY**, **EPB6ZZghpEk** off file (not KO2). **dropped.csv** now 7 ids. |
| 2026-06-28 | **Amiga tournament videos — drop workflow** — `drop_video.py` + `dropped.csv` denylist; first drop **P41ms03SW-Y** (Gremlin Graphics industry talk, not KO2). |
| 2026-06-27 | **Amiga tournaments index filters** — World Cup split to own row (All · World Cups · Not World Cups); format row is Leagues · Cups · League + cup only. `?wc=` param; legacy `?type=world-cup` still works. |
| 2026-06-27 | **Amiga tournament videos — unified embed UI** — Games/Atmosphere wings + spotlight player on all events with manifest rows (was WC-only card layout). Wing nav hides empty section; atmosphere-only default wing. Policy §9.1 updated. |
| 2026-06-27 | **Amiga tournament videos — UKC08 awards** — **bHjAB8MU_BI**, **43JlZ2QyPeA**, **G0-EQ16K0Ts** assigned to **Birmingham VIII** (**315**) as ceremony/atmosphere extras. Manifest **299** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2006 Rickmansworth** — eight KO2CV `KOA_WC2006_*` parts assigned to games **8437–8444**; alkelele/forum dupes excluded (ko2cv canonical for better YouTube quality). Shame stays **bvdkP6rHmMo** (alkelele). Orphans −8. |
| 2026-06-27 | **Amiga orphan videos index** — `/amiga/videos/orphans.php`: curated groups + excluded section; `review.csv` synced to `public_html/data/` on manifest build. |
| 2026-06-27 | **Amiga tournament videos — UKC 2002 misfile** — **p5d5s11rHMw** ("KO2 UKC 2002") moved WC 2002 Athens (**66**) → **Gloucester I** (**32**). |
| 2026-06-27 | **Amiga WC Videos — WC 2006 Rickmansworth shame** — ko2cv **BN-dj4sl0TU** excluded; alkelele **bvdkP6rHmMo** canonical (game **8573**). Manifest **296** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2007 Rome semi leg 1** — **Mtb4qPBQg6o** ("KOA WC 2007") is canonical Semi1 leg 1 (game **11345**, Gianni–Gianluca **4–3**); lower-quality duplicate **gmCjZSeyLqE** excluded. Manifest **297** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2007 Rome** — seven KO2CV `KOA_WC2007_*` clips were harvested as `stream`/`coverage` (no tournament id); manually matched to games **11345–11351**, **11402** (KOA Cup), **11447** (shame). Semi1 Part1 = **Mtb4qPBQg6o** (was generic **gmCjZSeyLqE**, now dup excluded). Games tab **9** match rows + shame. |
| 2026-06-27 | **Amiga WC Videos — WC 2008 Athens** — four ko2cv dupes excluded (final legs **eO0cByqpD1o** / **Qz8CUZ1evzY**, silver **947VFBRpXlk**, shame **I74mFcUp2wc**); alkelele kept. Manifest **292** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2009 Voitsberg** — ko2cv dupes excluded (final leg 1 **a-OmwoP1OjM** had wrong **16402**; silver **xuCyTNYCli0** had group **16294**; final leg 2 **ws1Z7pYGvmA**); alkelele canonical. Manifest **295** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2011 Birmingham** — semi/final leg B clips remapped (**20108**, **20107**, **20112**); silver cup note on **20009**. |
| 2026-06-27 | **Amiga UKC 2012 videos** — eight KO2CV clips moved off WC XII Milan (**554**) → **Bournemouth III/IV** (545/546) with game IDs **20407–20499**; score-matched per clip. |
| 2026-06-27 | **Amiga WC Videos — WC 2013 Voitsberg catalog** — nine KO2CV Part 04–12 clips reclassified `stream→match` with game IDs (**21833** group, **21855** 9th, semis **21872–21875**, 3rd **21876**, final **21877–21878**); Parts 01–03 ceremony/shame stay Atmosphere. |
| 2026-06-27 | **Amiga WC Videos — WC 2023 extras** — `3iAgrEi5Mk0` ("Goals Compilation, December 2023", 4m) excluded; not WC XXI. Manifest **298** videos. |
| 2026-06-27 | **Amiga WC Videos — WC 2014 extras** — `qhpd0y30pMs` ("Kick Off 2 Amiga 2014 July 10", 85s) excluded from manifest; not WC XIV coverage. Manifest **299** videos. |
| 2026-06-27 | **WC Videos Back button — root cause fixed + UX polish.** Real cause of the "second clip won't Back to index" bug was **YouTube iframe history pollution**: reassigning `iframe.src` pushes an entry onto the shared session history, so Back stepped *inside the iframe* (video cleared, URL unchanged, no `popstate`) before reaching the page. Fix: mount clips by **replacing the iframe node** (`mountEmbed`/`unmountEmbed`, verified history-flat) and rewrote `amiga-tournament-videos.js` flagless. Per Dagh: (1) **Back always → index** — stack capped at `[index, clip]` (first pick `pushState`, clip switch `replaceState`); no cycling. (2) **Last-watched row stays highlighted on the index** (`lastWatchedState`, `tr.is-active`) so the next leg is easy to find; Back scrolls it into view. (3) **`autoplay=1` on pick** (embed-only param; allowed since click = user gesture). Browser-verified end-to-end. [`k2-embedded-video-page-policy.md`](docs/k2-embedded-video-page-policy.md) §2.3–2.4. |
| 2026-06-26 | **WC Videos history fix v3** *(superseded by 2026-06-27 above)* — tagged states + commit guard; carry-scroll skips in-page ▶ clicks (the carry-scroll guard is kept). |
| 2026-06-26 | **WC Videos deep-link policy** — [`k2-embedded-video-page-policy.md`](docs/k2-embedded-video-page-policy.md): `v=` playback, optional `game=`, index/deep-link/Back rules; Phase A/B; expandable for profile Videos later. Slice **TV-URL** in implementation plan. |
| 2026-06-26 | **Amiga WC Videos — WC 2014 Copenhagen semis** — `TIixxHSjASc` was dual-linked **22463+22464** while `2_AnWxbb6ho` already covers leg 1 → leg 2 only **22464**; semi1 legs scored/patched **22461** / **22462**. |
| 2026-06-26 | **Amiga WC Videos — WC 2019 Bremen semis** — Fabio/Andy leg B **25454** (was duplicate **25453**); Gianni/Thor legs swapped to **25456** / **25455** with scores. |
| 2026-06-26 | **Amiga WC Videos — WC 2022 Athens catalog** — nine KO2CV Part clips reclassified `stream→match` with per-video `ROW_PATCHES` (semis **25985–25988**, 3rd **25989**, final **25990–25991**, bronze **25949**, silver **25960**); three day streams stay Atmosphere. |
| 2026-06-26 | **Amiga WC Videos — dual-leg semi game IDs** — leg-B rows (2024 Fabio/Andy **26874**, 2012 Andy/Dagh **21272**, 2023 Gianni/Fabio **26299**) patched in `apply_review.py`; `apply_row_game_id_locks` so bulk matcher cannot overwrite explicit IDs. |
| 2026-06-26 | **Amiga WC Videos tweaks** — Atmosphere wing label; `wc_video_slot=third_place` manual tags (19 WCs); smaller ▶; scroll-to-player on play; fixed 3rd vs bronze/33rd heuristic. |
| 2026-06-26 | **Amiga tournament videos — KO2CV streams (23)** — `@KO2CV_TV/streams` added via manual map (WC 2016–2025 + UKC Preston/Nottingham); manifest **300** videos. |
| 2026-06-26 | **Amiga tournament videos — TV-3 Videos tab** — `amiga_tournament_videos_lib.php` + `videos.php` tab; grouped sections, lazy embed, player/game links, alternates; tab hidden when no manifest rows; browser OK on WC XXIII (id **25**) + UKC Gloucester (id **32**). |
| 2026-06-26 | **Amiga flag → roster sweep** — `k2_amiga_country_table_cell()` defaults linked; host + podium + roster hero/rows included (**CH9**). |
| 2026-06-26 | **Turbo hash-anchor docs** — `k2-turbo-page-init-checklist.md` § Hash anchor landing. |
| 2026-06-26 | **WC Chronology table — quiet Date sort** — default `event_date` desc unchanged; `data-k2-quiet-sort-cols="0"` + k2-table.js so Date never gets active-sort header/body emphasis; other columns highlight normally. |
| 2026-06-26 | **Amiga tournament videos — manual adds (6)** — WC 2001/2003 atmosphere, UKC 2002 Gloucester stream, London XXIII game **14809** (2-part upload), Stoke **5622** atmosphere; manifest **277** videos. |
| 2026-06-26 | **Amiga tournament videos — TV-1 chat review batch 3** — final escalations resolved; **205/205** match rows with `game_id_guess`. |
| 2026-06-26 | **Amiga tournament videos — TV-1 game_id resolver** — `resolve_games.py` (dual-leg ≥20 min rule); **182→205** match rows linked. |
| 2026-06-26 | **Amiga tournament videos — TV-1 harvest** — `scripts/amiga/tournament_videos/` (yt-dlp + forum t=15358 + enrich); **`data/amiga/tournament_videos/review.csv`** (336 rows, 0 duplicate IDs); relation_group hints on 2010 dual-URL bullets. **STOP:** Dagh CSV review before TV-2. |
| 2026-06-26 | **Amiga tournament videos — implementation plan** — [`amiga-tournament-videos-implementation-plan.md`](docs/amiga-tournament-videos-implementation-plan.md): slices TV-1–TV-6, manifest paths, harvest/build/validate scripts, PHP Videos tab pilot on WC XXIII. |
| 2026-06-26 | **Amiga tournament videos — policy doc** — [`amiga-tournament-videos-policy.md`](docs/amiga-tournament-videos-policy.md): Videos tab, manifest model, six-source harvest, dedupe-by-youtube-id, Chronology + Has videos filter; implementation not started. |
| 2026-06-26 | **Jukebox Turbo Drive** — Hotwired Turbo + `data-turbo-permanent` jukebox for gapless cross-page playback; `k2-turbo-boot.js` + `k2:page-ready` bridge. |
| 2026-06-26 | **Jukebox vs tint picker fix** — Turbo nav no longer leaves duplicate `#k2-jukebox-root` in `<body>`; hub bar `z-index: 1210` keeps Tint clickable above open jukebox panel. |
| 2026-06-26 | **Turbo filter listbox re-init** — `individual3-filters.js`, `k2-realm-games-filters.js`, `status-period-competitions.js` hook `k2PageReady` so archive listbox filters work after in-page navigation. |
| 2026-06-26 | **Turbo carry-scroll flash + blank + header search — root-cause fix (v6)** — restore scrollY synchronously on `turbo:render` (pre-paint) and set `Turbo.navigator.currentVisit.scrolled=true` to suppress Turbo's scroll-to-top; **removed body-visibility cloak entirely** (it caused the blank-page delay while the flash leaked when restore completed early). No more flash or blank on LB **and** hub wing changes; nav anchor offset preserved. Header search dropdown was behind `.k2-hub-bar` (z-index 1210) — gave `.k2-site-header` its own stacking context (`position:relative; z-index:1300`). |
| 2026-06-26 | **Jukebox site-wide + nav resume** — player on all themed pages; localStorage saves track/time/playing across navigations and auto-resumes on load. |
| 2026-06-26 | **Amiga jukebox v1** — opt-in floating player on Amiga realm (`k2_jukebox.php`, 18-track MP3 playlist under `/audio/amiga/`); floppy FAB, panel transport, shuffle, localStorage prefs. |
| 2026-06-26 | **Amiga chart TT x-axis fix** — profile rank `rankChartTimeRange` treated flat points array as nested series (xMax never capped); rating charts read `Core` before `player-rank-chart-core.js` loaded. Cutoff x-range now in `chart-date-range.js` (`rankPointsTimeRange` / `ratingChartTimeRange`). |
| 2026-06-26 | **Amiga rating charts + time travel** — profile Elo chart + H2H rating compare filter snapshot points at cutoff, skip flat line to today, cap date x-axis at last cutoff event. |
| 2026-06-26 | **Amiga TT tournament list links** — Event wing `amiga_tournament_href()` now sets `as=event:{clicked id}` so player tournament rows open the chosen event (not ribbon cutoff via redirect). |
| 2026-06-26 | **Amiga peak-rating LB time travel fix** — TT query had two cutoff `(?, ?, ?)` clauses but only three bind params; table was empty / execute failed. Smoke step **4b** added. |
| 2026-06-25 | **H2H rating compare line style** — Stepwise first in toolbar + default (was Connected); online + Amiga. |
| 2026-06-25 | **Rank chart #1 headroom** — linear Y extends slightly below #1 for hover/grid clearance; `afterBuildTicks` keeps the #1 grid line when auto ticks skip it. Solo + H2H compare. |
| 2026-06-25 | **H2H rating compare By # tooltips** — event date uses site `M j, Y` locale format (not ISO `YYYY-MM-DD`); comma before date instead of middle dot. |
| 2026-06-25 | **H2H compare chart tooltips — scroll dismiss** — rank + rating HTML tooltips hide on page scroll; chart hover state + rating date hover dots cleared via `K2ChartTheme.registerChartHtmlTooltipScrollDismiss`. |
| 2026-06-25 | **H2H wins + cumulative goals charts — game #0 origin** — prepend `(0, 0)` and x-axis `min: 0` so single-game pairings draw a line segment (Amiga + online). |
| 2026-06-25 | **Amiga chart peak copy — tournament anchor** — rank + rating peak summaries (profile solo, H2H compare) append `, after {tournament name}` from stored `*_tournament_id`; rating API `peak` via `amiga_player_rating_peak_summary()`. |
| 2026-06-25 | **H2H rating By tournament # hover + line style** — shared index resolver (`resolveCompareRatingGameTooltipItems`); custom hover dots + tooltip follow cursor; Connected/Stepwise applies to both By date and By tournament #. |
| 2026-06-25 | **H2H rating By date hover dots fix** — plugin registered on `config.plugins`; marker state set from external tooltip + painted in `afterDraw` at cursor X × line Y (chrome/red). |
| 2026-06-25 | **Amiga profile rank chart grid** — `rankChartGrid()` (stronger than softGrid) so Y rank bands read at a glance. |
| 2026-06-25 | **Amiga H2H rank compare — HTML tooltips** — bold chrome/red rank ink (`#N of L (P%)`) per player + tournament on each line; shared date title. |
| 2026-06-25 | **Amiga H2H rating compare — tournament # tooltips** — **By tournament #** shows shared index in title; each player’s label carries their own tournament + date + rating (not hero-only in title). |
| 2026-06-25 | **Amiga H2H rating compare — date tail fix** — `player-compare-rating-chart.js` now applies `appendRatingThroughToday` for event granularity (parity with solo profile); stepped lines on Amiga **By date** so inactive players (e.g. Darren G last event 2009) extend flat to today instead of clustering on the left. |
| 2026-06-25 | **Amiga rank chart peak summary** — profile + H2H read `peak` from API (`peak_elo_rank` + tournament date); not client history scan. |
| 2026-06-25 | **Amiga peak-rating LB dates** — **Peak date** via `peak_rating_tournament_id` + **Peak rank date** via `peak_elo_rank_tournament_id`. |
| 2026-06-25 | **Amiga peak Elo rank (SCH-041)** — `peak_elo_rank` + `peak_elo_rank_tournament_id` on timeline + `current`; writer in `elo_rank.py` / ops PHP; verify in `prove`; **Peak rank** column on peak-rating LB (TT via dense timeline). |
| 2026-06-25 | **Amiga profile rank chart — X-axis locked** — full timeline from first Amiga tournament (`timelineStart` on `amiga_games`) → today; no in-chart zoom; Y **Career** is not an X trim (sparse ~600 finalize points / ~25 years). |
| 2026-06-25 | **Amiga H2H rank comparison chart shipped** — `player_compare_rank_history.php` + `player-compare-rank-chart.js` on `h2h.php`; union Career Y; dual peak text; shared `player-rank-chart-core.js`. |
| 2026-06-25 | **Amiga H2H rank chart — policy locked** — [`amiga-player-rank-chart-h2h-policy.md`](docs/amiga-player-rank-chart-h2h-policy.md): union **Career** Y default, full X timeline, chrome/red dual lines, **dual peak text lines** (no dashed canvas peak overlay); slices 6a–6e in implementation plan. |
| 2026-06-25 | **Amiga rank chart peak copy** — text `Peak:` summary under toolbar (profile solo); **not** a drawn peak line on the chart (R18). |
| 2026-06-25 | **Amiga profile rank chart hint** — sub-heading under **Elo rank**: “End-of-day rank after each tournament day.” (`k2-chart-block__hint`). |
| 2026-06-25 | **Amiga profile rank chart — post-ship tweak session** — Linear · Percentile only (log dropped); toolbar `data-range-mode` + Career-first band order; stepped-only line; transition edge-clip + empty-band axes (no status); percentile Career meta; Y-axis tick colour fix; policy/plan/profile-v0 updated. |
| 2026-06-25 | **Amiga profile rating chart default** — `player-rating-chart.js` respects markup initial tab; Amiga profile opens **By date** (online profile still **By game #**). |
| 2026-06-25 | **Header search + Amiga time travel** — `player-search.js` carries active `as=` on Amiga profile picks (T16); Online picks unchanged. |
| 2026-06-30 | **Amiga hub tab order** — present: News · Leaderboards · World Cups · Tournaments · Countries · Games · Activity · HoF · Live; TT bar: LB · WC · Tournaments · Countries · Games · Activity · HoF (`amiga_hub_nav_lib.php`). |
| 2026-06-26 | **Amiga tournament event-stats Country column** — first col player nationality flag (`pl.country`) with roster link `#k2-country-roster`; anchor col → Player (1); default Pts sort col 11. |
| 2026-06-25 | **Amiga tournament folder URLs** — `amiga/tournament/{event-stats,standings,stages,games}.php?id=`; shared `includes/amiga_tournament_page.php`; legacy `tournament.php?view=` 302; `index.php` redirect-only (not a nav tab). |
| 2026-06-25 | **Amiga tournament default tab** — all events open on **Event stats** (leftmost); ordinary standings nav uses `standings.php` (WC → `stages.php`). |
| 2026-06-25 | **Amiga profile rank chart slice 5 (TT closure)** — hero rank = last chart point at cutoff (probe + browser #237 @ 2003); pre-debut empty state; URL `as` fallback in JS. |
| 2026-06-25 | **Amiga profile rank chart slices 2–4** — profile panel + `player-rank-chart.js` (controls + Chart.js); Fabio #109 smoke 489 pts. |
| 2026-06-25 | **Amiga profile rank chart slice 1** — `amiga_player_rank_history_lib.php` + `api/player_rank_history.php` + `player-rank-history.js`; rank-at-event series + TT `as=`; probe `scripts/oneoff/amiga_rank_history_probe.php`. |
| 2026-06-25 | **Amiga profile rank chart — implementation plan** — [`docs/amiga-player-rank-chart-implementation-plan.md`](docs/amiga-player-rank-chart-implementation-plan.md): slices 1–5 (API → shell → chart core → controls → TT); mandatory chart platform contract from rating/Activity patterns. |
| 2026-06-25 | **Amiga profile rank chart — policy locked** — [`docs/amiga-player-rank-chart-policy.md`](docs/amiga-player-rank-chart-policy.md): `elo_rank_at_event` all finalizes, date X, linear/log/percentile + bands, connected/stepped, TT, minimal copy; solo v1 only. |
| 2026-06-25 | **Amiga staging export — elo_rank timeline** — `export_ko2amiga_db.ps1` now dumps `amiga_player_elo_rank_at_event`; hero rank 0 → — in TT/present load. Re-import staging for time-travel rank on profile/H2H. |
| 2026-06-25 | **H2H scoreline heatmap axes + scale** — rectangular grid (hero GF rows × rival GA cols, not forced square); intensity legend uses min(8, peak) buckets so low-count pairings no longer show junk ranges like `1–0`. Online + Amiga. |
| 2026-06-25 | **Amiga Opponents H2H slice F** — moments grid + full chart stack (cumulative W/goals, rating compare by date/tournament #, goals histograms, total-goals + scoreline heatmap); `amiga_player_h2h_pair_lib.php` + `?realm=amiga` on H2H chart APIs; `player-opponents-h2h-chart-context.js`. |
| 2026-06-25 | **Amiga Opponents H2H slice D** — poster + search/by-games/A–Z pickers + pair-detail races + all-games link; stored `matchup_summary` / `matchup_at_event` + read-time pair perf; `player_h2h_opponent_search.php?realm=amiga`. |
| 2026-06-25 | **Games filter listbox empty idle fix** — `k2-archive-listbox.js` commits `''` idle (host/opp country, hero GD on player + Amiga games); was blocked by falsy guards. |
| 2026-06-25 | **Amiga player Games Reset filters** — status line after perf rating (· Reset filters); carry-scroll on status row. |
| 2026-06-25 | **Amiga UAE flag** — `UAE` → `ae.svg` in `k2_amiga_country_flag.php` (tournament host country, e.g. Dubai I). |
| 2026-06-25 | **Amiga player Games expanded filters** — three-row layout: opponent/tournament/host+opp country; year/since/until (until inclusive); result/GF/GA/GD/sum; faceted omit-self counts (`amiga_player_games_filter_facets.php`). |
| 2026-06-25 | **Amiga player Games filter layout** — natural wrap (10px gap), no equal-width grid columns. |
| 2026-06-25 | **Amiga player Games scope segment** — All games / World Cup in `k2-chrome-tabs` bar (filter URLs, segment chrome). |
| 2026-06-25 | **Amiga player Games filters chrome** — drop bordered `k2-player-tournament-filters` panel; scope + listboxes inline like online. |
| 2026-06-25 | **Amiga player Games listbox UX** — port online idle/link-star/ghost-width/panel=trigger; opponent+tournament meta counts in panel. |
| 2026-06-25 | **Amiga player Games table flash** — `ranked-table-pending` + reveal after scroll mirror / fonts (`k2-table.js` server-sort hook); ID anchor SSR. |
| 2026-06-25 | **Amiga player Games scope pills** — drop Event row label; pills **All games** / **World Cup**. |
| 2026-06-25 | **Games All listbox panel width** — ghost-sized pickers shrink-wrap trigger; panels match trigger (not field/18rem); flex `align-self: flex-start`. |
| 2026-06-25 | **Games All faceted score-line counts** — GD / Sum / TS listbox meta reflects other active filters; absolute GD; shared `k2_games_filter_facet_helpers.php`. |
| 2026-06-25 | **Games All year mode reset** — hide Mode field with `!important` (flex overrode `[hidden]`); ignore/clear `year_mode` when year unset; omit from form when hidden. |
| 2026-06-25 | **Games All year mode** — Mode field hidden until year set; listbox toggle-close keeps link-star accent (blur + CSS). |
| 2026-06-25 | **Player Games faceted counts fix** — do not clear filters on empty intersection (Draw + SUM 7 → 0 rows); career-wide validation only. |
| 2026-06-25 | **Player Games faceted filter counts** — listbox `meta` counts reflect other active filters; numeric gaps kept between extremes; `k2_player_games_filter_facets.php`. |
| 2026-06-25 | **Player Games goal filters** — filter row labels GF / GA / GD / SUM; new hero-signed **GD** listbox (`gd` param, `+N`/`−N`/`0` labels); shared WHERE in `k2_ratedresults_games_filters.php`. |
| 2026-06-25 | **Adjustment tooltip copy** — drop “now” from Elo rating-change help on game + games list pages (online + Amiga). |
| 2026-06-25 | **Amiga rating LB Δ header** — flat-top Δ on `th.k2-table-col-delta` (12px/500). |
| 2026-06-25 | **Amiga rating LB WC Δ** — present-day `/amiga/leaderboards/rating.php` shows Δ since start of last World Cup; time-travel wing Δ unchanged — [`amiga-rating-history-policy.md`](docs/amiga-rating-history-policy.md) §3.6. |
| 2026-06-25 | **Amiga TT LED field sep (period trial)** — `AMIGA_TT_STAMP_LED_FIELD_SEP` = `.`; `k2-amiga-tt-stamp--sep-period` pads zero-width DSEG7 period. |
| 2026-06-25 | **Games All opponent row** — hidden until player chosen; `[hidden]` + `.k2-realm-games-filters__row[hidden]{display:none}` (flex row CSS was overriding HTML hidden). |
| 2026-06-25 | **Games filter listbox ghost sizer** — SSR width from longest option in `k2_archive_listbox_render()` (`k2-archive-listbox--ghost-sized`); JS width sync skipped; fixes narrow-then-snap on player games filters. |
| 2026-06-25 | **Games filter listboxes (slice 1)** — canonical idle/accent on `k2_archive_listbox_render($idleValue)` + JS `data-k2-listbox-idle-value`; PHP empty-label fix; online player games + games/all migrated; Amiga player games next. |
| 2026-06-25 | **Profile stat ink** — online hero: all four stats link-star + glow; at-a-glance value cells link-star (Achievements Milestones row tier colors unchanged); Amiga hero Games matches rank/rating. |
| 2026-06-24 | **Player wing segment tabs** -- Profile/Opponents/… bar → `.k2-chrome-tabs.k2-player-wing-tabs` (fit-content); tournament detail nav stays full-width `.k2-player-nav-bar`. |
| 2026-06-24 | **Entity hero stack gap** -- `.k2-player-hero` + `.k2-amiga-tournament-hero` use `--k2-nav-gap` (was 6px / 16px); policy N1 = page-chrome stack layers, not nav-only. |
| 2026-06-24 | **Player wing hub bar** -- realm hub nav on all online + Amiga player pages (`player_wing_hub_nav.inc.php` / `amiga_player_wing_hub_nav.inc.php`); tint picker moved off player nav to hub bar; hero anchor landing = later slice. |
| 2026-06-24 | **Amiga segment sub-navs** -- LB wing (`.k2-amiga-lb-tabs`), WC hub tabs, tournaments index filter (new `amiga_tournament_index_nav.php`); online LB stays full-width for filters. |
| 2026-06-25 | **Amiga Countries hub docs sweep** — policy/plan marked complete; `amiga-profile-v0`, `url-routes`, `amiga-time-travel-policy` (TT tab + matrix), WC hub/country-slice cross-links, k2-table/k2-nav checklists, surface-expansion inventory, UPDATE_DOCS + AGENTS cold-start. |
| 2026-06-24 | **UTF-16 sweep** -- converted nav docs + `amiga_tt_stamp_html_probe.php` to UTF-8; repo scan clean (no UTF-16); 5 UTF-8-BOM files left (harmless). Rule: `.cursor/rules/utf8-windows.mdc`. |
| 2026-06-24 | **K2 nav agent checklist** -- `docs/k2-nav-implementation-checklist.md`; wired into AGENTS.md + kool-workspace (page chrome nav tasks). |
| 2026-06-24 | **Page nav spacing Phase 3** -- grep audit; tokenized remaining nav-like 12px holdouts; neutralized legacy `.k2-hub-tabs` margin; deleted dead `.k2-chrome-tabs > .server-peak-period-leaderboards` rule; audit table in `nav-spacing-policy.md`. |
| 2026-06-24 | **Page nav spacing Phase 2** -- bottom-only `--k2-nav-gap` everywhere; deleted `:has()` spacing + dead bar+table rule; token aliases removed; `lb_nav_end.php` dropped; Games + Amiga WC hub shells close `.k2-page-nav`. Option A (12px, no 16px hub exception). |
| 2026-06-24 | **Page nav spacing Phase 1** -- `--k2-nav-gap` + wing `.k2-chrome-tabs` 4px->12px in `theme.css`; plain LB wings (Milestones, Rating, Amiga LB) fixed. |
| 2026-06-24 | **Amiga present hub tab order** — News · Leaderboards · World Cups · **Countries** · Activity · HoF · Tournaments · Live tournaments; TT block (LB · WC · **Countries** · Activity · HoF) contiguous after News. |
| 2026-06-24 | **Amiga WC stats Goals columns** — renamed Max margin → Max win, Max player goals → Max GF; peak order: Max draw · Max win · Max GF · Max sum · Min sum. |
| 2026-06-24 | **Amiga TT stamp motion (2a shipped)** — toggle `k2_tt_entry=1` (panel fade + 32 cps typewriter); wing `k2_tt_entry=wing` (32 cps + 1100ms LED opacity fade); sync JS after stamp; clickable cursor (`localStorage`). |
| 2026-06-24 | **Amiga TT event-wing layout fix** — `amiga_snapshot_chrome_nav_href()` now requires `amiga_tournament_lib.php` before `amiga_tournament_page_request_path()`; Event wing stepper chevrons hit undefined function (same silent-abort pattern as carry-query fix). |
| 2026-06-24 | **Amiga TT docs sweep (T19)** — policy §5.1/§8/§10, implementation plan slices 2/6, data-contract, design-direction, hub-ia, MEMORY, PHP comments aligned to fixed toggle homes; World Cups in TT hub bar copy. |
| 2026-06-24 | **Amiga TT T19 toggle homes** — Present day → News; Time travel from present → rating LB + first year; in-lens toggle → rating LB + active `as=`; retired T14b/T14c contextual entry. |
| 2026-06-24 | **Amiga TT layout fix** — `amiga_snapshot_chrome_carry_query_params()` requires `amiga_tournament_lib.php`; silent Throwable had aborted picker mid-form. |
| 2026-06-24 | **Amiga TT snapshot chevrons** — tier-pill fill on stepper prev/next (14% elevated tint + 42% border; matches milestone tier filter). |
| 2026-06-24 | **WC events catalog column order** — Country (host flag) before Tournament on `/amiga/world-cups/` wing 1. |
| 2026-06-24 | **Amiga TT atmospheric chrome — docs** — policy §5.0 product intent (stamp + ribbon stack, chapter suppression, Δ column); design-direction + hub-ia cross-links. |
| 2026-06-26 | **Amiga staging export — WC country slice** — `export_ko2amiga_db.ps1` now includes `amiga_country_slice_{totals,at_event}` (parts 37–38); fixes empty WC hub wing 4 country sub-wings on staging. |
| 2026-06-24 | **Amiga TT hub chapters** — hide `k2-hub-chapter` title/lede on Leaderboards, World Cups, Activity, HoF when `as=` active; present day unchanged. |
| 2026-06-24 | **WC country Opponents column order** — Countries faced + Countries beaten moved after CS victims (parity with player Opponents wing). |
| 2026-06-24 | **WC hub wing 1 events table** — `/amiga/world-cups/` sortable catalog (tournaments-index cols minus Format; gold/silver/bronze medal headers + flag+name podium cells from `amiga_world_cup_stats`). |
| 2026-06-24 | **Amiga Opponents Elo + Country** — W/D/L, Goals, DDs ledger tables: opponent Elo + flag before Games; read path joins `amiga_player_current` / event snapshot rating + `p.country`. |
| 2026-06-24 | **WC player Results Win rate** — col after Pts/g; `(wins + 0.5×draws)/games` via `amiga_wc_lb_win_rate()` at render (same pattern as Pts/g). |
| 2026-06-24 | **WC country stats sub-nav** — `k2-amiga-world-cups-countries-tabs` added to existing WC wing-tab CSS selectors (parity with players/stats). |
| 2026-06-24 | **WC tournament stats host flag** — Country (host nation) column after Year on all five wing-2 tables; `host_country` + `k2_amiga_country_table_cell_or_dash()`. |
| 2026-06-24 | **Amiga LB Country column (Goals→Perf.)** — centered Country (flag) after Elo on Goals, DDs, Victims, Peak, Performance wings; `k2_lb_th_country()` / `k2_lb_td_country_open()`; perf rating SQL adds `pl.country`. |
| 2026-06-24 | **Rating LB Δ tooltip** — title `Rating change` + body naming chosen mode (year/month/event); `k2_lb_amiga_rating_delta_column_help_attrs()` (replaces wing jargon). |
| 2026-06-24 | **Career Elo column unify** — header `Elo` (centered th), tooltip title `Elo rating` via `k2_lb_th_elo` + `k2_lb_elo_column_help_attrs()` across hub LBs, WC players, status board; event/game/Perf. cols unchanged. |
| 2026-06-24 | **Amiga country flags rollout** — centered flags on LB Rating, Calendar-geo, Tournament honours + tournament catalog (`amiga_tournament_index_render_table`); `k2_amiga_country_table_cell_or_dash()` for empty host. |
| 2026-06-24 | **WC player stats country flags** — Country col on five player LB tables: centered flags via `k2_amiga_country_table_cell()` (+ shared helper in `k2_amiga_country_flag.php`); `data-k2-sort-value` for text sort. |
| 2026-06-24 | **WC country column center** — Country (flag) col on all five hub tables: `k2-table-cell--center` in `theme.css` + `amiga_wc_countries_table.php`. |
| 2026-06-24 | **WC country stats tooltips** — nation-grain column help on all five hub tables (`lb_column_help.php`); header tweaks (Entries, Realm %, etc.). |
| 2026-06-24 | **Amiga WC country stats policy** — wing 4 Country stats locked: five sub-wings, `amiga_country_slice_*`, roll-up rules, perf rating + avg opp rating on Results; hub policy + PROJECT_MAP updated. |
| 2026-06-24 | **Amiga WC country stats implementation plan** — CS-0–CS-7 slices, reference files, STOP gates ([`amiga-world-cups-country-slice-implementation-plan.md`](docs/amiga-world-cups-country-slice-implementation-plan.md)). |
| 2026-06-24 | **skip-initial-sort SQL parity** — Activity Participation `ORDER BY active_days DESC`; league honours zero-fallback matches gold-first order (WC player LBs fixed earlier via per-view slice order). |
| 2026-06-24 | **Header realm switcher carry-scroll** — Online · Amiga 500 nav: `data-k2-carry-scroll` on `realm_switcher_nav.php` (reuses `k2-realm-switch__btn` pill selector). |
| 2026-06-24 | **Amiga time mode carry-scroll** — header **Present day | Time travel** + ribbon Year/Month/Event wings: `data-k2-carry-scroll` on nav; `k2-realm-switch__btn` in `k2-carry-scroll.js` pill selector. |
| 2026-06-24 | **WC player LB default sort** — per-wing SQL `ORDER BY` via `amiga_lb_wc_slice_order_sql()` + `amiga_wc_lb_rows_for_view()` (fixes skip-initial-sort header/body mismatch on Results/Goals/DDs/Opponents). |
| 2026-06-24 | **Amiga time travel tooltip** — present-mode hover help: side-effects list order → lost wins before missing players (`amiga_time_mode_nav_time_travel_help_text()`). |
| 2026-06-24 | **Amiga rating LB time-travel Δ column** — Rating wing with `?as=`: wing-step Elo delta vs previous snapshot (Δ after Elo; blue + / red − / dash for 0). |
| 2026-06-24 | **Amiga time travel tooltip copy** — side-effects punchline expanded: lost bragging rights, acute nostalgia, rematch-everyone-from-2003 (`amiga_time_mode_nav_time_travel_help_text()`). |
| 2026-06-24 | **K2 hub LB `$lbSort` fix** — Amiga 8 LB wings + online Activity 3 wings: missing `k2_lb_table_sort_state()` after SSR migration (`scripts/fix_lb_sort_state.py`). |
| 2026-06-24 | **K2 table follow-ups** — hub LB wings Tier B→A (`k2_lb_th`/`k2_lb_td` SSR on 18 pages + WC players + league honours); Amiga `tournament.php` standings + games → `amiga_tournament_lib` render helpers; `audit_k2_table_compliance.py` PASS (0 Tier C); `amiga-profile-v0` + `amiga-player-universe-contract` k2-table notes. |
| 2026-06-24 | **K2 table compliance** — `scripts/audit_k2_table_compliance.py` + `.cursor/rules/k2-table-php.mdc`; games hub shell `$k2RankedCloak`; plan doc audit + backlog section. |
| 2026-06-24 | **K2 table agent checklist** — `docs/k2-table-implementation-checklist.md`; bootstrap triggers in AGENTS + kool-workspace; reference-by-scenario table (stop bare `k2_table_js_enqueue` sortable pages). |
| 2026-06-24 | **Amiga live tournaments index** — `/amiga/live-tournaments.php`: cloak + sortable assets; `amiga_live_tournament_index_render_table()` SSR sort/anchor; default Date desc; Tournament anchor col 0. |
| 2026-06-24 | **Scroll mirror panel radius** — top corner strip on `.k2-table-wrap` only when `.k2-table-mirror-group--active` (fixes square tops when table does not overflow). |
| 2026-06-24 | **Amiga WC sub-nav compact segment** — `k2-lb-wc-tabs` + hub player/stats inner tabs: `width: fit-content` on container/bar (matches Activity participation). |
| 2026-06-24 | **Sub-nav spacing** — unified wing → sub-nav gap (`--k2-wing-to-subnav-gap` 12px) in `theme.css`: LB honours/activity, player Opponents/Milestones, Amiga WC LB + hub inner tabs; fixed dead `.k2-chrome-tabs > .k2-lb-league-honours` rule. |
| 2026-06-24 | **Amiga tournament catalog index** — `/amiga/tournaments.php`: `$k2RankedCloak` + sortable assets; `amiga_tournament_index_render_table()` SSR sort/anchor; filter pills carry `k2_sort`. |
| 2026-06-24 | **Amiga player tournament history table** — `$k2RankedCloak` + sortable assets head; SSR sort/anchor + `skip-initial-sort` on default Date desc; scroll mirror; filter pills carry `k2_sort`. |
| 2026-06-23 | **Opponents ledger tables (both realms)** — W/D/L · Goals · DDs: `$k2RankedCloak` + `k2_sortable_table_assets_head.inc.php` on ledger views; `k2_table_wrap_open(true)` + `k2-table--player-matchup` on all three; URL `k2_sort` on table attrs. |
| 2026-06-23 | **k2-table scroll mirror rollout** — shared `k2_sortable_table_assets_head.inc.php` + `k2_lb_sortable_table_head.inc.php`; online hub LBs + league honours + games/league/player-games migrated to `k2_table_wrap_open(true)`; Amiga LBs/WC shells use shared head include. |
| 2026-06-23 | **k2-table column widths** — hub Rank/Player min-widths opt-in via `k2-table--hub-rank-player-cols`; `k2_table_ranked_sortable_class()` + `k2_table_ranked_leaderboard_class()`; online LBs migrated to helper; status league tables excluded. |
| 2026-06-23 | **Amiga tournament page layout** — dropped legacy `1.25rem` horizontal gutters; hero/nav/bracket span full `.k2-page-nav` column (fixes nav overflow). |
| 2026-06-23 | **Amiga tournament event-stats table** — ranked cloak + `ranked-table-pending` + SSR sort/anchor cells; anchor col 0 (Player); scroll mirror; `tournament.php` `$k2RankedCloak`. |
| 2026-06-23 | **Sortable table platform** — dropped `.k2-hub-sortable-table`; WC + Amiga LBs use `k2_table_wrap_open(true)` + `.k2-page-nav` global width rules only. |
| 2026-06-23 | **Scroll mirror layout** — panel shrink-wraps until overflow; full width only under `.k2-table-mirror-group--active`; WC stats mirror on all views. |
| 2026-06-23 | **Amiga WC player stats layout** — removed `.k2-amiga-wc-players-table` horizontal padding + footnote inset; tables align with tournament-stats tabs (full 1200px). |
| 2026-06-23 | **Amiga WC player stats V2 UI** — five sub-wings on hub + LB dual surface; enriched Goals + DDs & CSs + Opponents; `amiga_wc_players_table.php` + routes. |
| 2026-06-23 | **Amiga WC player slice V2 writers** — `039` DDL; `WorldCupSliceTracker` + finalize/replay; PHP `amiga_slice_game_stats_lib.php`; `verify-player-slice` V2 oracles; **`prove` green** (~21 min). |
| 2026-06-23 | **Amiga WC player slice V2 policy** — five sub-wings (Goals enrich + DDs & CSs + Opponents); DDL/writer/verify contract — [`amiga-world-cups-player-slice-v2-policy.md`](docs/amiga-world-cups-player-slice-v2-policy.md). |
| 2026-06-23 | **Amiga WC player stats dual surface** — hub wing 3 + LB World Cups share `amiga_wc_players_wing_body.inc.php` / `amiga_wc_players_table.php`; policy WCH8–WCH9 amended (no LB→hub redirect). |
| 2026-06 | **Amiga WC stats blowout + intl columns** — DDL `038` + writers + UI (Goals Blowout %, Geography Intl games/Intl %). **`prove` green** (~21 min, 23 WC rows, full verify suite). |
| 2026-06 | **Amiga TT tournament browse sync** — on `tournament.php` + Event wing, chevrons/picker follow cutoff `id`; 302 when `id` ≠ `as=event:{id}`. |
| 2026-06 | **Amiga time travel toggle tooltip** — present-mode **Time travel** hover shows warning + side-effects copy via `data-k2-help`; `k2_table_js_enqueue()` dedupes script load. |
| 2026-06 | **Amiga per-WC stats table spec — curation pass 1** — [`amiga-world-cup-stats-table-plan.md`](docs/amiga-world-cup-stats-table-plan.md): must-have + nice = ship set; guest/host player counts; Q-WC-003 / `share_of_year_games` clarified. |
| 2026-06 | **Obsolete dev scripts slice 6 (closure)** — track complete; policy §7 all Done; `DEAD_SURFACE.md` retired-script inventory; frozen `ko2unity_db` = re-import only. |
| 2026-06 | **Obsolete dev scripts slice 5** — doc sweep: `OPERATIONS_QUICK_START`, `work-db-prepare`, `website-data-contract`, runbooks/coordination docs → holy ops + retirement policy; `replay-v1` historical banner; exit grep clean outside policy/archive. |
| 2026-06 | **Obsolete dev scripts slice 4** — `scripts/k2_rating_core/` (apply_game, player_state, elo, …); Amiga holy imports repointed; ladder replay code → `docs/archive/ladder-retired-2026-06/`; `prove` L5 green + verifiers (fixed `verify-l2-l3` argv in prove). |
| 2026-06-24 | **Player hero landing anchor** — `#player` zero-height anchor before hero (online + Amiga); inbound links via `k2_player_profile_href()` / `k2_amiga_player_profile_href()` + `k2_player_link()` / `k2_amiga_player_link()`; wing pills stay hash-free + carry-scroll. |
| 2026-06-24 | **Amiga News tab blank** — removed top-10 Elo line race charts, API, JS, race-only CSS/lib; `/amiga/news.php` = header only (realm landing unchanged). |
| 2026-06 | **Obsolete dev scripts slice 3** — `work_prepare` CLI stubbed; 19 modules → `docs/archive/work-prepare-retired-2026-06/`; `refresh_local_work_db.ps1` → PHP `run_prepare.php refresh-work`; `paths.py` kept for Amiga export. |
| 2026-06 | **Obsolete dev scripts slice 2** — retired `python -m scripts.ladder run` CLI, `run_local_replay.ps1`, `run_staging_ladder_replay.sh` (archived); `scripts/ladder/README.md` → library-only. Amiga imports unchanged. |
| 2026-06 | **Obsolete dev scripts slice 1** — stubbed `rebuild_website_derived_data_local.ps1`, `rebuild_activity_wing_local.ps1`, `rebuild_player_period_games_local.ps1`; batch SQL → `docs/archive/batch-rebuild-sql-2026-05/`; one-off SQL → `docs/archive/batch-rebuild-sql-one-off-2026-06/`. |
| 2026-06 | **Obsolete dev scripts retirement** — policy + implementation plan; holy ops audit: online ops never exec Python; Amiga `prove` imports `scripts.ladder` library only (`player_state`, `apply_game_row`, `constants`, `config`). Per-file retirement gate mandatory before delete. |
| 2026-06 | **Post-game parity register sweep** — `post-game-contract-vs-oracle-discrepancies.md`: closed false Opens (`play_streak_100`, P7 verify); split `club_*` live Fixed vs batch Deferred; layer 7 superseded by `verify_activity_wing_parity`; DDR-052 + cutover checklist aligned. |
| 2026-06 | **Milestones docs drift fix** — `milestones-product-spec.md` + `milestones-project.md`: 112/112 keys shipped (removed stale wave-1 ~88 TODO); meta LB wing + hub v2 marked done; Accomplished **%** wing noted as deferred (counts ship today). |
| 2026-06 | **Amiga WC stats sub-wings** — Goals · DDs & CSs · Participation · Geography · Podium under `/amiga/world-cups/stats/`; shared anchor cols; `stats.php` → Goals. [`amiga-world-cup-stats-table-plan.md`](docs/amiga-world-cup-stats-table-plan.md) §3.13 |
| 2026-06 | **Amiga WC stats load shift fix** — `ranked-table-pending` + scoped cloak (site leaderboard pattern); revert bespoke shrink-wrap; `min-width: 100%` table width like tournaments list. |
| 2026-06 | **Amiga World Cups hub wing 2** — sortable tournament stats from `amiga_world_cup_stats_read_lib.php` + TT cutoff. [`amiga-world-cups-hub-policy.md`](docs/amiga-world-cups-hub-policy.md) |
| 2026-06 | **Amiga World Cups hub shell** — hub tab (2nd after News); `/amiga/world-cups/` three wings + player sub-nav; Activity moved after Tournaments. [`amiga-world-cups-hub-policy.md`](docs/amiga-world-cups-hub-policy.md) |
| 2026-06 | **Amiga community stats v2 + WC table writers** — DDL `036`/`037`; registry v2 facts + headline extensions; `amiga_world_cup_stats` (23 rows); Python + PHP finalize; `verify-world-cup-stats` + PHP parity in `prove` green (~21 min). Charts deferred. |
| 2026-06 | **Amiga community stats catalog step 3** — Dagh curation: **46 ship**, 2 later, 28 cut; per-WC table + histogram UX backlog noted. |
| 2026-06 | **Amiga community stats catalog step 2** — dedupe (73 active, 3 cut), storage S0–S7 refined, 9 writer clusters; question catalog updated. |
| 2026-06 | **Amiga community stats question catalog** — 76 brainstorm rows (6 wings); [`amiga-community-stats-question-catalog.md`](docs/amiga-community-stats-question-catalog.md); curation pending. |
| 2026-06 | **Amiga community stats v2 catalog plan** — question-first method, lens taxonomy (L1–L4), storage classes S0–S7, wings IA; [`amiga-community-stats-catalog-plan.md`](docs/amiga-community-stats-catalog-plan.md). |
| 2026-06 | **Amiga community stats Phase 2 hygiene** — stronger `verify-community-stats` SQL guards; `test_community_registry_parity`; `AMIGA_REQUIRE_PHP=1` gate; dead aggregate helpers removed. |
| 2026-06 | **Amiga community stats hygiene shortlist** — archived; P0 backlog → implementation plan § Phase 2. |
| 2026-06 | **Amiga derived-write Phase 2** — live docs sweep: implementation plans + policy runbooks → `prove` only; SQL header comments updated. |
| 2026-06 | **Amiga derived-write policy** — retired batch `*-rebuild` CLIs; prove-only corrections. [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md). |
| 2026-06 | **Amiga community stats shipped** — DDL `034`, finalize writers, facts v1 registry, Activity summary, `verify-community-stats` in `prove` (605 snapshots). |
| 2026-06 | **Amiga community stats policy** — locked hybrid storage (headline snapshots + fact table); [`amiga-community-stats-policy.md`](docs/amiga-community-stats-policy.md). |
| 2026-06 | **Amiga ground stack doc sweep** — closed stale “planned/gap/target” refs; slices 1–11 consistent across policy, stack, import-layer, README. |
| 2026-06 | **Amiga ground slice 11** — `verify-l2-l3` boundary gate (L2 lineage, re-prepare parity, nationality oracle); wired into `prove`; strict stack track **complete**. |
| 2026-06 | **Amiga ground slice 10** — L3 from L2 only (`import_l2_witness.py`, `prepare_witness_from_l2`); `prove` full L1→L5; no `.mdb` on witness path. |
| 2026-06 | **Amiga ground slice 9** — L2 `witness_player_identity` from L1 Rankings; drop `Countries`; `extracted_from_l1` manifest; `verify-prune` + unit tests. |
| 2026-06 | **Amiga strict ground stack (policy v3)** — [`amiga-ground-stack.md`](docs/amiga-ground-stack.md): L0→L5 chain; slices 1–11 **complete** (Jun 2026). |
*(Newest first. ~30 rows max. Older rows: [`docs/archive/session-log-2026-q2.md`](docs/archive/session-log-2026-q2.md).)*

| When | What |
|------|------|
| 2026-07 | **Amiga TT Present day toggle** — from time travel, **Present day** stays on the same page without `as=` (filters kept); entry to time travel unchanged (rating LB + first event). |
| 2026-07 | **Amiga time travel entry default** — header **Time travel** from present opens **Event** wing at first ladder tournament (`as=event:26` Dartford WC), not first calendar year. |
| 2026-06 | **Amiga Games hub** — `/amiga/games/{recent,highlights,all}.php`; hub tab (present + time travel); TT-sensitive counts; Recent = last 5 tournaments, **ID desc**; Highlights = four boards; All games = server sort + 250/page (filters deferred); table = tournament games + Date + Tournament (host flag); **player flags always** when country known (tournament games + hub). |
| 2026-06-22 | **Amiga fresh prove + staging export** — full `python -m scripts.amiga prove` green (~6 min, 27 418 games); export 31 parts incl. `slice_totals` + `slice_at_event` (221 / 3050 rows); ready WinSCP + browser import. |
| 2026-06 | **Amiga World Cups LB slice 3** — WC columns off tournament honours; Events/Wins/WCs off calendar-geo; HoF deep links retargeted; profile WC LB link. |
| 2026-06 | **Amiga World Cups LB fix** — TT `bind_param` types; Results + Goals pages; realm WC holder bind. |
| 2026-06 | **Amiga World Cups LB slice 0** — SCH-033 `amiga_player_slice_{totals,at_event}`; `wc_*` dropped from snapshots/current; writers + `verify-player-slice` in `prove` green. |
| 2026-06 | **Amiga World Cups LB policy** — slice tables + folder sub-wings; extract WC from tournament honours; V1 podium/results/goals; [`amiga-world-cups-leaderboard-policy.md`](docs/amiga-world-cups-leaderboard-policy.md). |
| 2026-06 | **Amiga hero games date** — player games tab shows event day only (`Aug 4 2013`), no time. |
| 2026-06 | **Amiga tournament games player filter** — dropdown sorted A–Z by name (`amiga_tournament_game_player_choices`). |
| 2026-06 | **Opponents tables Games links** — W/D/L · Goals · DDs `Games` column → hero games tab `?opponent=` (online + Amiga; Amiga carries `as=`). |
| 2026-06 | **Amiga TT player games + tournaments** — hero games tab + tournament history filter ≤ snapshot cutoff; perf API parity; probe `scripts/oneoff/amiga_player_wing_cutoff_probe.php`. |
| 2026-06 | **Amiga player nav order** — hero universe pills: Profile · Opponents · Tournaments · Games (`amiga_player_nav.php`). |
| 2026-06 | **Amiga elo_rank (SCH-032)** — `elo_rank` on snapshots/current + `amiga_player_elo_rank_at_event` (~173k rows / ~8 MB local); finalize Python+PHP; **hero UI** (all player wings) reads persisted rank; LB tables still sort+enumerate; `prove` green. |
| 2026-06 | **Amiga TT T18** — player Event chevrons step played tournaments; realm back before debut; picker lickstar accents; `amiga_player_event_stepper_lib.php`. |
| 2026-06 | **Amiga TT T14b/T17** — *(T14b toggle entry superseded T19)* pre-debut cutoff loads with hero — + note (no 404); `amiga_player_publish_hero_context()`; first-event `as=` still used by T18 stepper. |
| 2026-06 | **Amiga time travel picker** — Year/Month dropdown lists newest first (catalog order unchanged for stepper). |
| 2026-06 | **Amiga player hero at cutoff** — `amiga_player_snapshot_lib.php`; `amiga_player_load()` branches on `as=`; hero games link → player games tab. |
| 2026-06 | **Amiga time travel T13–T15** — editorial hub tabs hidden; News landing; `amiga_hub_nav_lib.php`. |
| 2026-06 | **Amiga honours doc drift** — PHP comments + honours-rules / surface-expansion overview aligned to `amiga_player_current` (retired `amiga_player_tournament_totals` read path). |
| 2026-06 | **Amiga Opponents tables** — `amiga_matchup_snapshot_lib.php` + W/D/L · Goals · DDs wings (stored + time travel); H2H placeholder. |
| 2026-06 | **Amiga Opponents IA shell** — player pill + `amiga/player/opponents/{h2h,wdl,goals,dds}.php`; placeholder wing bodies; routes in `k2_amiga_routes.php`. |
| 2026-06 | **Amiga matchup SCH-031** — goal extremes on `matchup_summary` + `matchup_at_event` (online SCH-019 parity); finalize Python+PHP; verify oracle; replay green. |
| 2026-06 | **Amiga perfect event implementation plan** — SCH-045 slices 0–9 (DDL, writers, prove, UI, closure). [`amiga-perfect-event-implementation-plan.md`](docs/amiga-perfect-event-implementation-plan.md) |
| 2026-06 | **Amiga perfect event policy** — locked definition (≥2 games, all wins); storage on snapshots/current/catalog/realm; WC honours read from `is_perfect_event` (no slice columns); HoF MostPerfectEvents. [`amiga-perfect-event-policy.md`](docs/amiga-perfect-event-policy.md) |
| 2026-06 | **Amiga Opponents wing policy** — [`amiga-opponents-wing-policy.md`](docs/amiga-opponents-wing-policy.md); incremental port plan (audit + slice discipline); no implementation plan yet. |
| 2026-06 | **Amiga profile hygiene** — removed legacy `/amiga/h2h.php` + profile top-opponents table; future Opponents wing under `amiga/player/opponents/*`. |
| 2026-06 | **Amiga time travel sort carry** — `k2_sort`/`k2_dir` preserved on same-path chevron/picker/granularity nav; JS ribbon refresh on column sort. |
| 2026-06 | **Amiga time travel chrome v2** — header Present day \| Time travel segment; ribbon above hub (one row: wings + stepper + picker); entry/exit links removed. |
| 2026-06 | **Amiga History tab removed** — hub tab + ladder page retired; `/amiga/history.php` 301 → rating LB (`as=` preserved). |
| 2026-06 | **Amiga HoF** — removed World Cup medals panel; tournament honours stay on LB wing only. |
| 2026-06 | **Amiga time travel slice 5** — History on shared `as=` + chrome; legacy wing/at → canonical URL. |
| 2026-06 | **Amiga time travel slice 4** — HoF reads realm snapshots at cutoff; LB deep links carry `as=`. |
| 2026-06 | **Amiga time travel entry default** — inactive **Time travel** link opens first calendar year (`year:` wing), not latest event. |
| 2026-06 | **Amiga time travel slice 3** — all eight LB wings read `amiga_player_event_snapshots` at cutoff; probe top-10 history parity OK. |
| 2026-06 | **Amiga time travel slice 2** — ribbon chrome + hub/player link propagation; entry link; profile unwired note. |
| 2026-06 | **Amiga time travel slice 1** — snapshot context + URL helpers + shared `as=` resolution; probe OK. |
| 2026-06 | **Amiga time travel policy** — global `as=` lens locked; phase 1 = LB + HoF (profile deferred). [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md) |
| 2026-06 | **Amiga stored id/date Phase C** — `verify_stored_id_date_pairs.py` (rise FK pairing, honours_last, last participation, career-best replay); wired in `prove`; P4–P6 closed. |
| 2026-06 | **Amiga stored id/date Phase B** — `verify_hof_holder_projection.py` (career `_holder_record_date`, game SQL oracle + GameID dates, ratio dual oracle); wired in `prove`; manifest P2/P3 closed. |
| 2026-06 | **Amiga SCH-030 career HoF rise dates** — `030_career_rise_dates` DDL; `career_rise.py` + PHP lib; snapshot persist + realm projection; `verify-hof-geo-year` career oracle (18 HoF dates); unit tests; `prove` green; export refreshed. |
| 2026-06 | **Amiga stored id/date semantics Phase A** — manifest + ranked backlog in [`amiga-stored-field-semantics.md`](docs/amiga-stored-field-semantics.md). |
| 2026-06 | **Amiga stored id/date semantics plan** — decision doc for manifest + verify phases A–D (fallback after SCH-029); [`amiga-stored-field-semantics-plan.md`](docs/amiga-stored-field-semantics-plan.md). |
| 2026-06 | **Amiga HoF record dates slice 8 (track complete)** — docs closure; `export_ko2amiga_db.ps1`; SCH-029 shipped. Also fixed PHP `finalize_tournament` `bind_param` typo blocking refinalize. |
| 2026-06 | **Amiga HoF record dates slice 7** — PHP `amiga_honours_totals_lib.php` + geo rise tracking + snapshot persist increment/copy rise cols (mirrors Python slices 2–4). |
| 2026-06 | **Amiga HoF record dates slice 6** — `verify_hof_geo_year.py` rise oracle + HoF `*Date` checks + Alkis regression; full `prove` green (~5.7 min). |
| 2026-06 | **Amiga HoF record dates slice 5** — realm holder `*Date` from `*_last_rise_event_date` (Python + PHP); `test_realm_holder_dates.py`. |
| 2026-06 | **Amiga HoF record dates slice 4** — rise columns on `SNAPSHOT_COLUMNS`/`CURRENT_COLUMNS`; honours + geo rise wired through persist; verify current parity. |
| 2026-06 | **Amiga HoF record dates slice 3** — `player_geo_year.py` geo last-rise id/date in `scalars_for`; `GEO_RISE_METRICS`; tests extended. |
| 2026-06 | **Amiga HoF record dates slice 2** — `honours_totals.py` per-metric last-rise id/date; `test_honours_rise_dates.py`. Snapshot wire slice 4+. |
| 2026-06 | **Amiga HoF record dates slice 1** — SCH-029 DDL (`029_hof_record_rise_dates.sql`); 12 last-rise cols on snapshots + current; `schema_bundles.py`. Writers slice 2+. |
| 2026-06 | **Amiga HoF record dates (planned)** — policy + sliced plan: per-metric `*_last_rise_tournament_id` + `*_last_rise_event_date` at finalize (SCH-029); fixes SCH-028 `honours_last_event_date` misuse. [`amiga-hof-record-date-policy.md`](docs/amiga-hof-record-date-policy.md) |
| 2026-06 | **Amiga HoF calendar-year + geography** — SCH-028; eight HoF rows + Calendar & geo LB; `player_geo_year` tracker; `verify-hof-geo-year`; PHP finalize parity; `prove` green. |
| 2026-06 | **Amiga realm snapshots slice 8** — export `amiga_realm_snapshots`; docs closure; track complete; `prove` green. |
| 2026-06 | **Amiga PHP ops realm parity** — `zero-derived` + refinalize reopen batch clear `amiga_realm_snapshots` / `matchup_at_event` (matches Python). |
| 2026-06 | **Amiga realm snapshot perf (tiers 2–3–1)** — incremental finalize; `prove` ~5.4 min. |
| 2026-06 | **Amiga realm snapshots slice 1** — `027_realm_snapshots.sql`; ratio cols on `generalstats`; `generalstats_columns.py`; `prove` green. |
| 2026-06 | **Amiga realm snapshots policy (slice 0)** — `amiga_realm_snapshots` + full `generalstats` at finalize; ratio leaders on row; plan slices 1–8. |
| 2026-06 | **Amiga finish_override L3 relocation** — DDL `sql/ground/002`; Pack ground; replay/zero-derived preserve curated rows. |
| 2026-06 | **Amiga ground layers slice 8** — docs closure; track **complete** (policy §8 CLI map, cross-doc drift fixed). |
| 2026-06 | **Amiga ground layers slice 7** — export packs Mirror/ground/structure/product + verify-export-pack. |
| 2026-06 | **Amiga finalize S4 alignment** — ops bootstrap prior career + honours carry from latest snapshot before event (not `amiga_player_current`); Python + PHP + snapshot persist; policy §6 fixed. |
| 2026-06 | **Amiga refinalize retired** — removed reopen/refinalize/warm-through/verify-php-finalize-parity; derived repair = `prove` only; archive [`retired-amiga-refinalize-2026-06.md`](docs/archive/retired-amiga-refinalize-2026-06.md). |
| 2026-06 | **Amiga finalize warm-through guard** — superseded by refinalize retirement; career-games oracle kept in finalize verify. |
| 2026-06 | **Amiga ground layers slice 5** — `apply-structure` / `verify-structure` (L4 disposition; `prove` green). |
| 2026-06 | **Amiga ground layers slice 4** — `import-witness` / `verify-witness` (L3 witness extract; `prove` green). |
| 2026-06 | **Amiga ground layers slice 3** — `import-prune` / `verify-prune` (L2: Scores + Tournament players + Countries). |
| 2026-06 | **Amiga ground layers slice 2** — `import-pristine` / `verify-pristine` (L1 full mirror SQL, 38 Access tables). |
| 2026-06 | **Amiga ground layers policy v2** — renumber L0–L5; hard L2 prune; doc pass. |
| 2026-06 | **Amiga ground layers slice 1** — `schema_bundles.py`; DDL split; `prove` green. |
| 2026-06 | **Amiga PHP live finalize parity** — `finalize_tournament.php` cumulative matchups, at-event persist, incremental network/peaks (mirrors Python replay path). |
| 2026-06 | **Amiga matchup at event (slices 0–6)** — `amiga_player_matchup_at_event`; network + peaks + H2H at finalize; replay tail batches removed; `prove` green (~210k at-event rows). Policy [`amiga-matchup-at-event-policy.md`](docs/amiga-matchup-at-event-policy.md). |
| 2026-06 | **Amiga event snapshots slice 9** — docs closure: player-universe §3–§5, data-contract, policy writer path, staging export → snapshots/current; track **complete**. |
| 2026-06 | **Amiga event snapshots slice 8** — dropped legacy four player tables; finalize/replay write snapshots+current only; `prove` green (4535 snapshots / 473 current). |
| 2026-06 | **Amiga player hero links** — avatar + name → profile; rank/rating → Rating LB `#k2-lb-table`; games → Rating LB Games sort (same anchor); anchor markup on all Amiga LB wings via `amiga_lb_nav.php`. |
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
| 2026-06 | **League period games table** — default sort is game ID (desc), not date; fetch order matches (`k2_league_period_page.php`). |
| 2026-06 | **Profile At a glance mobile** — dropped column stack on narrow viewports; three columns stay side-by-side with horizontal scroll when needed (`player-feast-glance.css`). |
| 2026-06 | **Profile career chart alignment (B+C)** — `profileCareerTimeRange()` (Jun 2017 month → month-end); rating by date axis only; `offset: false` on month bars. |
| 2026-06 | **Profile career chart gutters (slice A)** — shared 48px y-axis + 12px right padding via `chart-theme.js` (rating, games/month, goals). |
| 2026-06 | **Amiga time travel realm home** — wordmark + Amiga 500 toggle keep active `as=` and land on rating LB (not News) when in time travel. |
| 2026-06 | **Amiga Opponents Goals column label** — Goals tab header **TG/g** → **Sum/g** (`amiga_player_opponents_tables.php`). |
| 2026-06 | **Amiga TT T14c** — *(toggle entry superseded T19)* `amiga_tournament_snapshot_as_param()` retained for event ribbon on `tournament.php` (§5.1.1). |
| 2026-06 | **Profile bonanza moment logic** — 3× ratio gate on primary sum game; global highest-`SumOfGoals` fallback where ratio passes (replaced H2H win vs same opponent). |
| 2026-06 | **Profile heatmap section rhythm** — padding breaks (no margin collapse): story→days 24px; days→weeks ~52px; weeks→bursts ~32px. |
| 2026-06 | **K2 page structure checklist** — agent onboarding for new pages/tabs/modes (`docs/k2-page-structure-checklist.md`); wired into AGENTS, kool-workspace, url-routes, agent-track playbook. |
| 2026-06 | **Amiga tournament Videos folder modes (TV-FOLDER)** — Games/Atmosphere → `tournament/videos/{games,atmosphere}.php`; legacy `videos.php` 302. |

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
| Local DB | `ko2unity_db` frozen · dump `data/dumps/` · derived fill = work simul — [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md) |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| Cutover index | **`docs/coordination/cutover-readiness.md`** |
| `ratedresults` indexes | SCH-001 in ops `migrate-work` |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
