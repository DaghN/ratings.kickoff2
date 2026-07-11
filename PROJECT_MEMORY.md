# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

**Amiga agents (Jul 2026):** Forward = **`ko2amiga_work`** + **`simul`**. **Pull staged** = `pull_ko2amiga_from_staging.ps1 -Force` · **Push staged** = `export_ko2amiga_work.ps1`. Legacy **`prove`** / Access L0–L5 = oracle only — [`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) §0.

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

- **Design / Status hub:** Phase B v1.2 room grid shipped. **Live room (v1.5) shipped** — SRL-9 `live_clocks`, cascade glow (LB Elo + league Games/Pts), work sim wall-clock catch-up — [`status-room-live-policy.md`](docs/status-room-live-policy.md). Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](docs/activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones · **Games** · HoF · Play & Setup — [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). **Navigation invariants** (hub bar always present; active pill only on places; **entity pages** at realm root, no pill; singular=entity / plural=hub) — [`navigation-model.md`](docs/navigation-model.md) NM1–NM6. **Games hub (Jun 2026):** `games/recent.php` + Highlights + **All games** vault (filters, server sort). **URLs:** semantic paths + `games/` + `milestones/` folders; registry [`k2_routes.php`](site/public_html/includes/k2_routes.php) — [`url-routes.md`](docs/url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Operational loop:** edit locally → **WinSCP** sync `site/public_html/` → staging; hard refresh. Steve runs server one-offs.

- **Local:** `ratingskickoff.test` (dev) + `work.ratingskickoff.test` (work) — [`LOCAL_DEV.md`](docs/LOCAL_DEV.md).

- **Change style:** small, reversible slices.

- **Amiga time travel (Jul 2026):** **TT ribbon sticky v1** — CSS `position:sticky` on all `as=` pages (sticky on only; no pushpin). **F6 nav track signed off** — narrow snapshot reads + y=0 chrome gate + realm query sweep; Dagh S1/S1b pass — [`tt-chrome-baseline-f6-attempt-log.md`](docs/orchestration/tt-chrome-baseline-f6-attempt-log.md) · [`2026-07-04-003`](docs/orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) · sticky slice [`2026-07-04-018`](docs/orchestration/agent-handoffs/2026-07-04-018-tt-ribbon-sticky-v1-css.md).

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

- **Amiga community stats (Jun 2026):** **V2 writers shipped** — registry v2, `036`/`037`, `prove` green. **Amiga Activity hub v1 shippable (Jul 2026)** — **49 panels / 50 ship IDs** on `/amiga/activity/` six wings; wing copy locked — [`amiga-activity-charts-policy.md`](docs/amiga-activity-charts-policy.md) §5.0 · plan [`amiga-activity-charts-implementation-plan.md`](docs/amiga-activity-charts-implementation-plan.md) **complete** (slices 0–10 + copy pass). Per-WC table on World Cups hub wing 2 **shipped**. No open v1 chart backlog.

- **Amiga World Cups hub (Jun 2026):** **Wings 1–4 shipped** — **events catalog** (sortable table, podium flag+name cols) + tournament stats (five sub-wings) + **player stats** + **country stats**. [`amiga-world-cups-hub-policy.md`](docs/amiga-world-cups-hub-policy.md).

- **Amiga derived writes (Jun 2026):** **Locked** — batch `*-rebuild` CLIs removed; corrections = **`prove` only**; verify = read-only oracles. [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md).

- **Amiga Countries hub (Jun 2026):** **Shipped** — hub tab + index (player count sort) + country entity **Roster · Rivals** (CRV-1–7: `country/rivals/{h2h,wdl,goals,dds}`); flag links site-wide; cross-links WC country stats. [`amiga-countries-hub-policy.md`](docs/amiga-countries-hub-policy.md) · [`amiga-country-rivals-policy.md`](docs/amiga-country-rivals-policy.md).

- **Amiga perfect event (Jun 2026):** **Shipped** — SCH-045; honours LB + WC **Perfect** column; catalog filter; HoF **Most perfect events**. [`amiga-perfect-event-policy.md`](docs/amiga-perfect-event-policy.md).
- **Amiga perf. rating LB (Jun 2026):** **Shipped** — folder `performance-rating/{best,top,perfect}.php` + segment nav; W-D-L columns; Top 100 fixed set; Perfect shows **∞**. [`amiga-performance-rating-leaderboard-policy.md`](docs/amiga-performance-rating-leaderboard-policy.md).

- **Amiga tournament videos (Jun 2026):** **TV-3 + TV-4 shipped** — manifest **~299** videos; unified embed UI; **C06** dedicated Videos column; **With videos** filter; **player profile Videos wing**. **Jul 2026:** **TV-2b DB anchor sync** — `sync_db_ids` + `verify-tournament-videos` in `prove` ([`amiga-tournament-videos-policy.md`](docs/amiga-tournament-videos-policy.md) §12).

- **Amiga live ops (Jul 2026):** **RTB shipped (RTB-1–RTB-9)** — running scores until organizer **Finish and make official** (promote + finalize + lifecycle `completed` in one Table-tab action). Policy: [`amiga-running-tournament-boundary-policy.md`](docs/amiga-running-tournament-boundary-policy.md) rev. 2.

- **Amiga format scoring contract (Jul 2026):** **SC-0–SC-9 + SC-11 shipped** — L4b relational contracts, PHP↔Python executor parity, RTB broadcast, L5 `stage_id`, structured ET/pens on games + fixtures (`012_match_extensions.sql`). **SC-10 blocked** on structure/materialize (~58% games `fixture_id`). Witness audit: `Scores.Extra` token inventory; `WG` unconfirmed in Access. Policy [`amiga-format-scoring-contract-policy.md`](docs/amiga-format-scoring-contract-policy.md) · plan [`amiga-format-scoring-contract-implementation-plan.md`](docs/amiga-format-scoring-contract-implementation-plan.md).

- **Amiga modern ground (Jul 2026):** **Cutover bootstrap complete** (D0 → PROMOTE-1 + DOC-1 + CODE-1). **Staged prod / local repair shop:** [`amiga-staging-authority-policy.md`](docs/amiga-staging-authority-policy.md). **PULL-1a/1b shipped** — `pull_ko2amiga_from_staging.ps1` + `run_export_ko2amiga.php` (verified pull Jul 2026). Daily loop: pull → repair → push (`export_ko2amiga_work.ps1` after simul when needed). [`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md).

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
| Amiga live ops (staging authority, repair, media) | [`amiga-live-ops-platform.md`](docs/amiga-live-ops-platform.md) · drill [`amiga-live-ops-practice-track.md`](docs/amiga-live-ops-practice-track.md) |
| DB copies (local + staging names) | `docs/coordination/database-copies-2026-06.md` |
| Work DB prepare / simul | `docs/work-db-prepare.md` |
| Ground vs derived columns | `docs/replay-v1-scope-and-reset.md`, `docs/ground-truth-manifest.md` (online) · **`docs/amiga-data-contract.md`** (Amiga) |
| Session log archives (older Recent log rows) | [`docs/archive/session-log-2026-q2.md`](docs/archive/session-log-2026-q2.md) · [`docs/archive/session-log-2026-jun-prune.md`](docs/archive/session-log-2026-jun-prune.md) |

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

| Date | Note |
|------|------|
| 2026-07-11 | **Birmingham VIII Silver Cup (317) pure knockout** — 7p SF bye Garry C; Tier E **1,2,3,3,5,5,5**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Bournemouth II (544) pure knockout** — 7p; plural QF/SF stage names; Tier E finish **1 Garry C · 2 Dagh N · 3 Andy G · 3 Mark W · 5 Simon K · 5 Steve C · 5 Steve E**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XXI Gold Cup (452) pure knockout** — 5 stages / 5g; Tier E finish **1 Jon G · 2 Steve E · 3 Simon K · 3 Steve C · 5 Garry C · 5 Robert S**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XIV Silver Cup (414) pure knockout** — 3× QF · 1 SF · Final; Tier E finish **1 Mandhir S · 2 Andy H · 3 Graham S · 4 Andy E/Grant N/Ren A**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XXXIII (519) pure knockout** — 6p SF bye (Tom P); 5 stages / 5g; Tier E finish **1 Garry C · 2 Simon K · 3 Steve E · 3 Tom P · 5 Brian C · 5 John M**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XXVII (493) pure knockout** — 6p SF bye (Rick S); 5 stages / 5g; Tier E finish **1 Jon G · 2 Steve E · 3 Rick S · 3 Tom P · 5 Dan N · 5 Garry C**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XXVIII (500) pure knockout** — 6p QF bye (Tom P); 5 stages / 5g; Tier E finish **1 Simon K · 2 Steve E · 3 Garry C · 3 Tom P · 5 John M · 5 Thomas J**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XLV (568) pure knockout** — 4 stages (Semi Finals ×2 · 3rd · Final); g21621 SC-11 ET 2-2 pens 8-7; Tier B finish 1–4; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Birmingham XXXVII (535) pure knockout** — 3 stages (2× **Semi Finals** + Final); g20217 SC-11 ET 0-0 pens 1-0; Tier E finish **1 Brian C · 2 Gary T · 3 Glen H · 3 John M**; `has_league=0` `has_cup=1`. |
| 2026-07-11 | **Amiga work git checkpoint (CHECKPOINT-1)** — `seal_amiga_work_checkpoint.ps1` + sealed `data/amiga/checkpoints/work-2026-07-11-tail/` (~71 MB); docs/runbooks updated; not pushed to staging. |
| 2026-07-11 | **Amiga simul green (ko2amiga_work)** — full replay + 24-step verify suite OK after SC-5 PHP phase parser + verify argv/fixes. |
| 2026-07-11 | **SC-5 PHP phase parser parity** — `amiga_tournament_phases.php` synced with Python (`Game of Shame`, Playouts band, play-outs regex); `verify-php-standings-parity --sweep` green. |
| 2026-07-11 | **SC-7 replay hygiene** — `clear_derived` clears scoring freeze markers; idempotent stage freeze sync on finalize/repair; tests in `test_scoring_contract.py`. |
| 2026-07-11 | **Amiga simul (ko2amiga_work)** — L5 replay OK (605 events / 27418g); SC-7 stage freeze repair (177 stages); Milan 89 standings + parity OK; full verify stops on WC VI (206) PHP standings parity (pre-existing). |
| 2026-07-11 | **Milan I (89) Group B extra stage** — L4 `round-1-group-b-extra` for g2394–2398; UI Phase from stage name only (never L2→L3 phase override); witness `amiga_games.phase` stays `Round 1 - Group B`. |
| 2026-07-11 | **Milan I (89) Tier E sparse finish** — FFZ idd=175: 1–4 podium · **=5** QF losers · 8 group-only NULL; sparse override semantics + snapshots refreshed; `ko2amiga_05_finish_override.sql` export. |
| 2026-07-11 | **Milan (89) manual materialize** — uneven Groups A/B + two-leg KO; 10 stages / 81g. |
| 2026-07-11 | **Athens XCI (22) manual materialize** — 12p league + placement finals ladder; 7 stages / 78g; Tier B finish 1–12 (Christopher D champion). |
| 2026-07-11 | **Milan X (156) Tier E finish** — **=5** QF losers · **=9** group non-advancers (Fulvio O · Angelo S). |
| 2026-07-11 | **Milan X (156) manual materialize** — 2×5 groups + two-leg QF/SF + **3-leg** 3rd/Final; 10 stages / 58g. |
| 2026-07-11 | **Norwegian Champs (121) Tier E finish** — banded placement: **=5** QF losers · **=9** Qual. losers · **=13** group-only (not 5–8 / 9–12 / 13–15 distinct ranks). |
| 2026-07-10 | **Tournament page nav unified** — all events use **Stages** top tab + sub-nav (WC pattern); bracket renders only on Bracket sub-tab; `standings.php` → 302 `stages.php`. |
| 2026-07-11 | **Groningen VII (48) Tier E finish override** — full ladder 1–9 in `amiga_tournament_finish_override` (auto derivation duplicated positions after group splits); snapshots refreshed. |
| 2026-07-10 | **Groningen VII (48) Round 2 group split** — **Round 2 - Group D** (Kees V · Sjoerd K · Luitzen B) **· Group E** (Mark P · Riemer P · Evert V); script `split_groningen_vii_round2_groups.py`. |
| 2026-07-10 | **Groningen VII (48) Round 1 group split** — **Round 1 - Group A/B/C** (3×3 double RR, 6g each); script `split_groningen_vii_round1_groups.py`; Tier B finish unchanged 1–9. |
| 2026-07-10 | **Groningen VII (48) manual materialize** — `Semi-Final` parser; **League 7-9** (witness `Playouts`); KO semis + placement finals; Tier B finish 1–9. |
| 2026-07-10 | **Event-stats Finish sort** — numeric `data-k2-sort-value` from `event_finish_position` (fixes 10 before 1 on asc); player tournament history table too. |
| 2026-07-10 | **Homburg II (152) manual materialize** — 15 stages / 66g; **Group A/B** + Playouts 5-8 + placement finals; Tier E finish 1–10 (forum t=10006; Final agg 4–4). |
| 2026-07-10 | **Milan XVII (198) manual materialize** — `Playouts 5-7` parser; 11 stages / 36g; **Group A** · **Group B** split + NULL RR `phase_label`; Tier B finish 1–7. |
| 2026-07-10 | **Seeshaupt (267) manual materialize** — parser `Game of Shame` KO; 3 stages / 12g; **League** · **Game of Shame** · **Final**; SC-11 g10546; Tier E finish 1 Thorsten · 2 Robert · 3 Eric · 4 Thomas · 5 Norbert. |
| 2026-07-10 | **SC-11 materialized tail verified** — **111** g3192–93; **145** g5159/5178/5180/5187 + **Gianni T champion** (4-leg Final 2–2, g5180 pens for title). |
| 2026-07-10 | **L3 import SC-11 Kristiansand** — `SCORE_CORRECTIONS` g1189+g1188; structured cols at `import-witness`; inventory + import-layer docs. |
| 2026-07-10 | **Full simul on ko2amiga_work** — L5 replay ~26 min; goals drift fixed (`SUM(GoalsFor)` 196301); Kristiansand 54 + Milan V 145 finishes OK; L3/L4 preserved. Verify failed **only** `verify-scoring-contract` (SC-7 missing on manual-materialize stages — expected). |
| 2026-07-10 | **Tier E full-ladder policy + Milan V** — honours/runbook: any override ⇒ all `1..N` rows; **145** expanded to 8 rows (was Sandro-only). **Kristiansand g1189 Type B drift:** `SUM(GoalsFor)` 196303 vs fresh games 196301 (−2); only Aasmund + Glenn +1 each — ground/ratings synced; **full simul replay needed**. |
| 2026-07-10 | **SC-11 ET parser v2 + re-backfill** — witness `e.t.`/`a.e.t.` default = **post-ET total** (subtract regulation → `goals_et_*`); bulk backfill 103 games; **verified register** skips human-checked rows (`match_extensions_verified_register.json`). |
| 2026-07-10 | **SC-11 extension handoff v2** — Access `extra` verbatim + forum URLs/snippets from disposition/review queue; `extension_review_handoff.py`. |
| 2026-07-10 | **Kelkheim VII (215) materialize** — 12p league + full placement KO (Langenfeld family); 13 stages / 90g; AET final g8924; Tier B finish 1 Stefan · 2 Michael O · 3–12 full ladder. |
| 2026-07-10 | **Langenfeld (276) materialize** — 8p league + full placement KO; 13 stages / 52g; Tier B finish 1–8 (Oliver champion). |
| 2026-07-10 | **Milan V (145) materialize** — parser fix Play Outs/Finals plural; 11 stages / 54g; groups + QF/SF/Play Outs/placement finals; Tier E **full ladder 1–8** (Sandro withdrew). |
| 2026-07-10 | **Milan XII (166) manual materialize** — 8p double RR + KO; 4 stages / 63 fixtures; **League** · **Semi Finals** · **Final** (3 legs); `has_league=1` `has_cup=1`; KO `phase_label` NULL (witness **Finals**); Tier B finish **1 Gianni · 2 Luigi · 3 Marco/Mario**. |
| 2026-07-10 | **Athens IV Cup (74) manual materialize** — lucky-loser 6p cup; **Quarter Finals** + Semi Finals + Final; `has_league=0` `has_cup=1`; Tier A finish. |
| 2026-07-10 | **Kristiansand (54) SC-11 patch** — g1189 ET `1-0`; g1188 pens **7–8** (Glenn bronze winner); finish **3 Glenn · 4 Oskar** corrected. |
| 2026-07-10 | **Kristiansand (54) manual materialize** — 2×4 groups + full placement KO chain; 10 stages / 32 fixtures; `has_league=1` `has_cup=1`; Tier B finish 1–8 matches forum. |
| 2026-07-10 | **Milan IV (134) manual materialize** — 7p league + playoffs (7 KO games); 5 stages / 50 fixtures; **League** · **Semi Finals** · **3rd Place Final** · **Final**; `has_league=1` `has_cup=1`; Tier B finish refreshed. |
| 2026-07-10 | **Milan XIV (176) manual materialize** — cleared slice-6 cup review; Venice-style 6p double RR + KO; 5 stages / 38 fixtures; **League** · **Semi Finals** · **3rd Place Final** · **Final**; Tier B finish refreshed. |
| 2026-07-10 | **Rome (29) manual materialize** — 6p double RR + two-leg final; 2 stages / 32 fixtures; **League** · **Final**; `has_league=1` `has_cup=1`; Tier B finish refreshed. |
| 2026-07-10 | **Frankfurt (173) stage display name** — `Round 1 - League` → **`League`** on `ko2amiga_work` (align with Venice 64; witness `g.phase` stays `Round 1`). |
| 2026-07-10 | **Venice (64) manual materialize** — NULL-phase league+cup (Frankfurt-style); 5 stages / 19 fixtures; stage names **League** · **Semi Finals** · **3rd Place Final** · **Final**; `has_league=1` `has_cup=1`; `backfill-standings-stage-id` + verify OK; Tier B finish refreshed. |
| 2026-07-10 | **Kristiansand II Cup (111) materialize** — `materialize-pure-knockout --force`; 8 KO stages; two mini-cup stage names; `has_league=0` `has_cup=1`. **110** = league seeding only (Tier C finish); no combined override. |
| 2026-07-10 | **Kristiansand II (110) materialize** — legacy `materialize --replace`; 1 stage / 28 fixtures / 28 links; stage name **`League`**; `has_league=1` `has_cup=0` (league-only; cup playoffs on id 111); `backfill-standings-stage-id` + verify OK. |
| 2026-07-10 | **Staging export prepared** — `export_ko2amiga_work.ps1` green: 41 parts (~71 MB), 27,418 games, `07a` scoring steps; manifest `2026-07-10 05:53` — WinSCP + browser import pending. |
| 2026-07-10 | **Staging export table registry** — `scripts/amiga/staging_export_tables.py` + `data/amiga/staging_export_tables.json`; push/pull read JSON; `export_ko2amiga_work.ps1` runs write + audit preflight; adds `tournament_stage_scoring_steps` part (`07a`). |
| 2026-07-10 | **Groningen VII Cup (604) pure knockout** — `materialize-pure-knockout`; 7 KO stages / 14 fixtures; display names Quarter Finals · Semi Finals · Final (witness Round 1 / Semi Final kept). |
| 2026-07-10 | **Bulk stage rename Overall → League** — 503 single-stage null-phase RR marathons on `ko2amiga_work` (`stage_key` `overall` unchanged; witness `g.phase` NULL). |
| 2026-07-10 | **Stage-first game phase column** — `amiga_rated_games_from_sql()` + lean hub SQL: `COALESCE(s.name, g.phase)`; `phase_witness` + `stage_id` for standings links; tournament / player / realm games tables wired. |
| 2026-07-10 | **Frankfurt (173) stage display name** — `tournament_stages.name` `Round 1` → **`League`** on `ko2amiga_work` (Jul 2026: was briefly `Round 1 - League`; witness `g.phase` unchanged). |
| 2026-07-10 | **Manual materialize runbook** — [`amiga-tournament-structure-manual-materialize-runbook.md`](docs/amiga-tournament-structure-manual-materialize-runbook.md); handlers + review-queue + data contract demote bulk-first; Frankfurt (173) template. |
| 2026-07-10 | **Frankfurt (173) legacy materialize** — cleared slice-6 cup review; `materialize_legacy` allows `ko2amiga_work`; 5 stages / 20 fixtures / 20 `fixture_id` links; `backfill-standings-stage-id` + `verify-standings-stage-id` OK on work. |
| 2026-07-10 | **Scoring contract SC-11 session** — structured L3 ET/pens (`012_match_extensions.sql`); Python+PHP resolver; RTB dual-write + promote; backfill 103/108; `verify-match-extensions` in simul suite; Access `Scores.Extra` audit (`WG` = literal token only, 3 rows WC IV); SC-10 still blocked on fixture linkage. Docs: plan, policy, data contract, schema discovery, RTB, design §2.9. |
| 2026-07-10 | **SC-9 shipped** — L5 `stage_id` column + dual-write; backfill 605 tournaments; `verify-standings-stage-id --sweep` OK. **Next:** SC-10. |
| 2026-07-10 | **SC-8 shipped** — RTB broadcast compute + tournament_lib broadcast routing; live hub KO bracket; `verify-rtb-standings-parity`. **Next:** SC-9. |
| 2026-07-10 | **SC-7 shipped** — `freeze_scoring_contracts_for_tournament` in Python + PHP finalize; `freeze-scoring-contracts` catalog repair (605); verify-scoring-contract extended. **Next:** SC-8. |
| 2026-07-10 | **SC-6 shipped** — `backfill-scoring-contracts` on `ko2amiga_work` (605 tournaments + stages); catalog KO stores legacy GF chain; verify-scoring + PHP parity + Access sweep green. **Next:** SC-7. |
| 2026-07-10 | **SC-5 shipped** — `verify-php-standings-parity` CLI + probe; modern verify suite; PHP phase-routing parity fixes; sweep green. **Next:** SC-6. |
| 2026-07-10 | **SC-4 shipped** — PHP `amiga_ops_compute_tournament_standings` reads `ScoringContext`; `amiga_scoring_contract.php` reader/context helpers; RTB broadcast loads DB contracts; `standings-parity --sweep` FAIL=0. **Next:** SC-5. |
| 2026-07-10 | **SC-3 shipped** — Python `compute_tournament_standings` reads `ScoringContext`; legacy KO bridge; `standings-parity --sweep` FAIL=0. **Next:** SC-4. |
| 2026-07-10 | **SC-2 shipped** — `StageScoringContract` reader + `verify-scoring-contract` CLI (modern simul suite). **Next:** SC-3. |
| 2026-07-10 | **SC-1 shipped** — `scoring_contract.py` + `platform_default_v1` copy-on-create (`create_stage`, PHP fixtures); smoke OK on `ko2amiga_work`. **Next:** SC-2. |
| 2026-07-10 | **SC-0 shipped** — L4b DDL `011_tournament_scoring_contract.sql` (`tournament_stage_scoring_steps` + stage/tournament contract cols); `standings-parity --sweep` FAIL=0 on `ko2amiga_work`. **Next:** SC-1. |
| 2026-07-10 | **L4/L5 scoring contract — promoted to policy** — [`amiga-format-scoring-contract-policy.md`](docs/amiga-format-scoring-contract-policy.md) + [`amiga-format-scoring-contract-implementation-plan.md`](docs/amiga-format-scoring-contract-implementation-plan.md); cross-refs in data contract, structure, standings scope, format vision, RTB. **Next:** SC-0. |
| 2026-07-10 | **L4/L5 — Session D complete** (D15, D17; design track sessions A–D done) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-10 | **L4/L5 — D15 locked** (RTB alignment §2.12; live hub KO in scope) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — Session C complete** (D10–D14; D14 §2.11 `platform_default_v1` chains) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — D13 locked** (relational L4b serialization §2.10) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — D12 locked** (match extensions §2.9: structured L3, retire text parse) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — D10 locked** (phase fallback retirement §2.8) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — D9 locked** (executor primitives §2.7: `league_table`, `knockout_tie`; Session B complete) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 — D8 locked** (standings executor §2.5) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 scoring contract — D7 locked** (`stage_id` canonical; phase witness preserved) — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md) §2.4. |
| 2026-07-09 | **L4/L5 scoring contract — Session A complete** — D0–D2, D4–D6, D5, D11, D16, P1–P4 locked — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-09 | **L4/L5 scoring contract design track** — discussion plan opened (D0–D17, sessions A–D); problem: topology vs scoring rules vs standings projection conflated in engine — [`amiga-format-scoring-contract-design-discussion.md`](docs/amiga-format-scoring-contract-design-discussion.md). |
| 2026-07-08 | **L3 legacy fixes inventory** — [`amiga-l3-legacy-fixes-inventory.md`](docs/amiga-l3-legacy-fixes-inventory.md): human-readable catalog of all koatd corrections at L2→L3 import (tournaments, WC venues, splits, supplements, name merges incl. automatic, countries). |
| 2026-07-08 | **Agent pull ritual** — `kool-workspace.mdc` + `AGENTS.md` cold-start: “pull staged Amiga” → run `pull_ko2amiga_from_staging.ps1 -Force` (no simul default). |
| 2026-07-08 | **PULL-1 verified on staging** — full pull green (605 / 469 / 27,418); `run_export_ko2amiga.php` export-v4 (JSON + download); `pull_ko2amiga_from_staging.ps1` (simul opt-in `-Simul`); `staging-sync-last.json`. |
| 2026-07-08 | **PULL-1a pull script** — `pull_ko2amiga_from_staging.ps1` (staging generate → download → replace `ko2amiga_work`; simul opt-in `-Simul`). |
| 2026-07-08 | **PULL-1b staging pull export** — `run_export_ko2amiga.php` (preview + generate + download → `amiga/_export/ko2amiga_staging_pull.sql`). [`amiga-staging-handoff.md`](docs/amiga-staging-handoff.md). |
| 2026-07-08 | **Amiga staging authority policy** — staged = prod, local = repair shop, pull → repair → push (SS-1–SS-7); permissions open; PULL-1 PoC manual. [`amiga-staging-authority-policy.md`](docs/amiga-staging-authority-policy.md). |
| 2026-07-08 | **Doc pass 3b (intelligent banner review)** — fixed misleading banners (in-progress plans, countries hub, obsolete-dev, video/RTB); refined Product policy vs Historical wording; body fixes (profile, performance rating, CR migration). |
| 2026-07-08 | **Doc pass 3** — Historical/sign-off banners on 55+ shipped Amiga plans and policies; archive index § shipped feature plans; `__main__.py` CLI docstring. |
| 2026-07-08 | **Doc pass 2 (agent orientation)** — `kool-workspace.mdc`, MEMORY blurb, live-ops opening, obsolete-dev-scripts proof gates, RTB plan banner, archive index §0; foreground simul/work vs oracle prove. |
| 2026-07-08 | **Doc pass (modern orientation)** — README, PROJECT_MAP, OPERATIONS, staging handoff, data-contract, live-ops, scripts/amiga/README: simul/work forward; prove/oracle archived. |
| 2026-07-08 | **DOC-1 + CODE-1 done** — Access pipeline docs archived (`docs/archive/amiga-access-pipeline-index.md`); `modern/README.md` + `audit_amiga_modern_compartment.py`; prove/import/replay FROZEN. |
| 2026-07-08 | **PROMOTE-1 done** — `ko2amiga_config.local.php` → work; `export_ko2amiga_work.ps1` + `Export-Ko2AmigaStaging.ps1`; `promote-video-deploy`; simul video on by default (`--skip-video` opt-out). |
| 2026-07-08 | **V-1 done** — `seal-video-oracle` → `seed-video-work` → `align-video-work` → `verify-tournament-videos-work` green on `ko2amiga_work` (299 videos, 13 remap escalations non-fatal); `work_video_paths()` patches legacy module imports; simul `--with-video` wired. |
| 2026-07-08 | **Modern video policy** — [`amiga-modern-video-policy.md`](docs/amiga-modern-video-policy.md): canonical `game_id` on work, oracle/work file compartments, V-1 slices. |
| 2026-07-08 | **L4-1 done** — `verify-structure-work` green on `ko2amiga_work` (16,046 fixtures; disposition smoke). |
| 2026-07-08 | **Simul preflight/postcheck** — aligned with living ground (no day 0 count pin on simul). |
| 2026-07-08 | **P-1 done** — `python -m scripts.amiga parity` green: 29 tables counts + semantic signatures. |
| 2026-07-08 | **S-1 done** — `scripts/amiga/modern/` simul compartment: `simul`, `replay`, `apply-structure-work`, 22-step verify suite; `KO2AMIGA_DATABASE` env hook; `python -m scripts.amiga simul` green on work (~17 min replay + verify). |
| 2026-07-08 | **W-1 done** — `ko2amiga_work` seeded from `data/amiga/day0/` via `seed-work` CLI (605 / 469 / 27,418 L3; derived cleared). |
| 2026-07-08 | **`is_world_cup` flag shipped** — L3 `tournaments.is_world_cup` + snapshot denorm; import derives from name regex; `verify-is-world-cup` in prove; read paths use stored flag; organizer create checkbox ⟺ name. Policy: [`amiga-world-cup-flag-policy.md`](docs/amiga-world-cup-flag-policy.md). |
| 2026-07-08 | **RTB-9 shipped** — one **Finish and make official** button (promote + finalize + lifecycle `completed`); Setup **Mark complete** retired; limbo repair on re-click. |
| 2026-07-08 | **RTB policy rev. 2** — locked vocabulary + finish-action contract. |
| 2026-07-08 | **Live tournament league table** — `/amiga/live-tournament.php` shows `amiga_tournament_render_standings_table()` (broadcast merge via `amiga_live_tournament_league_table_rows()`); shared merge helper with organizer. |
| 2026-07-08 | **Organizer Table tab UX** — shared `amiga_tournament_render_standings_table()` + k2 sortable stack; merge all registered entrants (zero-game players at tied rank); country flags via entrant/standings rows. |
| 2026-07-08 | **Organizer Table tab (RTB broadcast)** — `amiga_running_tournament_games()` joins stage metadata so running standings resolve `round_robin`/`overall` → league `scope_key=''` (was preview zeros when only `phase_label` reached parser). Python `running_tournament_games()` parity. |
| 2026-07-07 | **RTB shipped (RTB-1–RTB-8)** — fixture running columns (`structure/006` via `prove`); PHP/Python promote at Make official; `verify-running-tournament-boundary` in holy loop; **`prove` green**. Staging: export → WinSCP → import → Ref-League-A drill. |
| 2026-07-07 | **RTB plan + inventory locked** — holy-ops-only DDL; implementation plan RTB-PREFLIGHT→RTB-8 ([`amiga-running-tournament-boundary-implementation-plan.md`](docs/amiga-running-tournament-boundary-implementation-plan.md)). |
| 2026-07-07 | **Amiga player create shipped (PC-1–PC-7)** — organizer compose Create player; `player_source` in ground bundle via prove; orphan hygiene; `verify-player-create` in prove. Staging: prove → export → WinSCP → browser import. |
| 2026-07-07 | **Amiga country registry shipped (CR-1–CR-8)** — `country_registry.json` (254 rows, 253 choosable); L3 alias normalize (`N. Ireland`→Northern Ireland, `UAE`→United Arab Emirates); 253 lipis/flag-icons SVGs; Python verify in `prove`; PHP `k2_amiga_country_registry.php` + activity chart boot map; organizer create league = used countries + **More countries…** (`amiga-organizer-country-picker.js`). **Staging:** sync `public_html/data/amiga/country_registry.json` + flag SVGs with PHP (empty tables if JSON missing). Policy/plan: [`amiga-country-registry-policy.md`](docs/amiga-country-registry-policy.md). **Backlog CR-9:** URL 301 aliases, shorthand toggle, edit country after create. |
| 2026-07-07 | **Organizer gate UX** — password-only login (removed tournament id field); optional URL deep link preserved. |
| 2026-07-07 | **Live hub — start=public (ALO11)** — removed config allowlist; `running` generated leagues auto on `/amiga/live-tournaments.php`; organizer **Make official** = finalize UX; data contract + live-ops policy updated. |
| 2026-07-07 | **Amiga fixture manager 500 fix** — `amiga_country_slice_game_stats_lib.php` wrong `post_game_outcome` require killed `fixtures.php` before password gate; practice track §4 L0-001. |
| 2026-07-07 | **Amiga live ops — practice-first track** — §12 rewritten in platform doc; new [`amiga-live-ops-practice-track.md`](docs/amiga-live-ops-practice-track.md) (Ref-League-A / Ref-Cup-A drill loop, pain log, L0 start); AGENTS + MEMORY + staging handoff cross-links. |
| 2026-07-06 | **Milestone panel carry-scroll** — Made it/Graphs pills omit spotlight hash; carry payload beats `$k2ScrollTargetId`; hash handler skips carry-nav links |
| 2026-07-06 | **Milestone spotlight glow fade** — 1.5s tier border glow-in on cold entry; warm chain skips on milestone→milestone (mirrors feast player hero) |
| 2026-07-06 | **Milestone spotlight inset** — detail hero card `margin-left: calc(--k2-player-hero-glow-inset + 10px)` (feast player hero parity) |
| 2026-07-06 | **Milestone detail hash landing** — catalog/recent/garden → `milestone.php` scroll to `#k2-ms-detail-spotlight` (spotlight card); `$k2ScrollTargetId` for bare key-only URLs |
| 2026-07-06 | **Docs pass** — STATUS_PAGE_DATA, feature-log, implementation plan, prod-readiness nuance (deploy-safe vs behaviour-proven) |
| 2026-07-06 | **SRL-9 live_clocks** — pulse sends half-clock anchor every beat; client resyncs on `changed: false` too |
| 2026-07-06 | **Sim wall-clock catch-up** — missed seconds replay on status load + pulse + sim control (cap 600 s/call) for realistic off-page sim |
| 2026-07-06 | **TT ribbon carry-scroll (stuck)** — anchor `viewportOffset` inside CSS-sticky ribbon lied when stuck → restore jumped to latch (~181px); store skips anchor when ribbon stuck + restore safety net — `k2-carry-scroll.js`, `k2_carry_scroll_restore.php` |
| 2026-07-06 | **Cascade fatal fix** — pulse missing `lb_player_filters.php` broke rated-finish cascade (500 response); live→recent + scores now apply |
| 2026-07-06 | **League cascade glow** — Activity Games + Points Pts on rated finish |
| 2026-07-06 | **LB cascade glow** — finishing-game rating gainers: **Elo only** (white) in active leaderboard |
| 2026-07-06 | **Live games order** — newest kickoff at bottom (`StartTime ASC`) |
| 2026-07-06 | **Login recency glow fix** — recent logins glows returning players promoted to head, not only brand-new list ids |
| 2026-07-06 | **Status live client fix** — signal cursor commits after DOM apply (cascade/recent no longer skipped on next poll) |
| 2026-07-06 | **Writer-agnostic pulse** — Status live reads fresh DB signals each poll; no sim/prod branching; finish cascade driven by `last_rated_id` diff only |
| 2026-07-06 | **Cascade glow stagger off** — superseded by SRL-12 retirement (sequence removed entirely) |
| 2026-07-06 | **Status online panel** — heading `<count> online` (`.blue`); list sorted `LastLogin ASC` (first logged in on top); glow bloom strengthened |
| 2026-07-06 | **Live sim L2→L3** — synthetic registrations queue a match with a veteran so `Sim_*` players can finish rated games |
| 2026-07-06 | **Cascade rating glow** — finished-game players only: name + Elo ink in active LB (not count or whole row) |
| 2026-07-06 | **Glow — text ink only** — unified 2.6 s jukebox bloom on names/digits/counts; stronger multi-layer text-shadow (Jul 6 tune) |
| 2026-07-06 | **SRL-16** — Status cascade rating tbody swap → `k2TableRefreshSortableBody()` (user sort preserved) |
| 2026-07-06 | **Online glow semantics** — glow on `LastLogin` epoch increase (just logged in), not DOM-id diff; force-remove offline rows during glow; `data-last-login-epoch` on online rows. |
| 2026-07-06 | **Status room name glow** — Online + Recent player links use same white bloom as score digits (`.k2-status-room` CSS scope). |
| 2026-07-06 | **Live sim 10 min wall cap** — auto Stop cleanup at 600 s from `started_at` (before catch-up); stalled idle halt when L3 queue exhausted — [`status-room-live-sim-spec.md`](docs/status-room-live-sim-spec.md). |
| 2026-07-06 | **Status live kickoff glow fix** — glow 0–0 after score sync (patchLiveScores no longer replaces fresh row HTML); explicit kickoff pulse in `patchLive`. |
| 2026-07-06 | **Status live new-game glow** — kickoff rows glow 0–0 score digits (white bloom), not player names — SRL-10 tweak in `k2-live-glow.js`. |
| 2026-07-06 | **Status room visibility catch-up** — immediate pulse on tab focus / pageshow after Stop (or any DB change) while Status was backgrounded; Stop + cache invalidate confirmed OK |
| 2026-07-06 | **Status room glow fixes** — recency lists glow by id diff (each new login/reg/game row, including same-second batch); SSR `data-player-id` on rows; live score pulse on scoring side only |
| 2026-07-06 | **Login/register integrity** — no bootstrap batch; one lobby event/tick; register without LastLogin/online; login-only sets IsOnline+LastLogin |
| 2026-07-06 | **Live sim guard tightened** — requires `ko2unity_work` + `work.ratingskickoff.test` (staging/prod safe after sync). |
| 2026-07-06 | **Live environment sim spec** — comprehensive platform doc (L1–L3 + L2 registration; dispatcher boundary) — [`status-room-live-sim-spec.md`](docs/status-room-live-sim-spec.md). |
| 2026-07-06 | **Status room live sim harness** — one-click ~20-game sequence + lobby login/logout on `work.ratingskickoff.test/status-room-live-sim.php`; post-game ops on finish; Stop = halt ticks only — [`status-room-live-sim-spec.md`](docs/status-room-live-sim-spec.md) Tier B. |
| 2026-07-06 | **Status room live sim spec** — work DB + `work.ratingskickoff.test`, Tier A–C lobby sim for pulse testing — [`status-room-live-sim-spec.md`](docs/status-room-live-sim-spec.md). |
| 2026-07-06 | **Status room live — shipped** — 1 s pulse (`api/status_room_pulse.php`), DOM patches + client half clocks, cascade on rated finish, text-ink glow @ 2.6 s — [`status-room-live-policy.md`](docs/status-room-live-policy.md). |
| 2026-07-06 | **Status room live — spec + plan** — heartbeat polling, `last_rated_id` cascade, client half clocks, jukebox-style glow choreography — [`status-room-live-policy.md`](docs/status-room-live-policy.md) · [`status-room-live-implementation-plan.md`](docs/status-room-live-implementation-plan.md). |
| 2026-07-05 | **About hero copy** — “Amiga tournament” → “Amiga tournaments” (plural). |
| 2026-07-05 | **Box art story hash landing** — `#k2-boxart-story` moved to zero-height anchor just above hero panel (12px scroll-margin); Status link unchanged. |
| 2026-07-05 | **Amiga live ops platform policy** — locked three lanes (A canon local / B ladder staged / C editorial staged), staging as community ground authority, timeline vs present anchored repair, ground packs, media DB writes, bidirectional flow — [`amiga-live-ops-platform.md`](docs/amiga-live-ops-platform.md) (ALO1–ALO10). |
| 2026-07-05 | **Site footer + About stub** — `includes/site_footer.php` on `body.k2-site` pages: © year, Dagh Nielsen, About, `daghnielsen@gmail.com`; `/about.php` leaf (maintainer + contact); `k2_site_end.inc.php`; PL13 trimmed to provenance-only (no editorial links). |
| 2026-07-04 | **Amiga Games Recent — section headings** — tournament labels moved outside table (online Recent parity); removed in-table colspan row that looked like a stray header row. |
| 2026-07-04 | **Profile played-weeks heatmap** — 30/70 left/right spare-space grid (was centred 50/50); `player-feast-sections.css`. |
| 2026-07-04 | **Amiga Games Highlights — Tournament column** — Date → Tournament → Player A (parity with Recent); `default_sort_col` +1 on all five boards (× two scopes); join-back already had tournament cols — no SQL change; parity + TT probe green. |
| 2026-07-04 | **Online Track O5-lite — Status year defer + first_games memo** — defer current-year league bundle to client prewarm; request memo on `k2_league_load_first_games`. `build_period_competitions` **~530→~98 ms**; curl `/status.php` **~0.40→~0.15 s**. Handoff [`2026-07-04-017`](docs/orchestration/agent-handoffs/2026-07-04-017-online-track-o5-status-year-defer-memo.md). |
| 2026-07-04 | **Online Track O1 — games/all score-line facet dedupe** — request cache + single-pass `GROUP BY gd, gs, ts` when score-line filters idle (port Amiga realm games pattern). Facet load **~3704→~995 ms**; census curl **3.94→1.31 s**. **<0.5 s** target not met (full-table aggregation + HTML remain). Parity OK — `online_track_o1_parity_probe.php`. Handoff [`2026-07-04-014`](docs/orchestration/agent-handoffs/2026-07-04-014-online-track-o1-games-all-facets.md). |
| 2026-07-04 | **Online Track O4 — games highlights inner LIMIT** — narrow sort subquery (`id` + metric cols) + join-back; request cache per board. `k2_games_highlights_fetch` **~1421→~722 ms**; census curl **0.987→0.758 s** (Heavy→Noticeable). **<0.15 s SQL** target not met — filesort on ~75k rows without metric index (DDL deferred). Parity OK — `online_games_highlights_parity_probe.php`. Handoff [`2026-07-04-016`](docs/orchestration/agent-handoffs/2026-07-04-016-online-track-o4-games-highlights.md). |
| 2026-07-04 | **Online Track O2 — player games facet dedupe** — career-wide facet bundle (request cache); validate reads bundle not 5 duplicate scans; single-pass gf/ga/gs/gd when numerics idle. Facet stack **~2325 ms → ~820 ms** @ id=537 (load **0 ms** cache hit). Curl **3.26→~1.67 s** best; **<0.5 s** target not met (COUNT + 500-row fetch + HTML remain). Parity OK — `online_player_games_facet_parity_probe.php`. Handoff [`2026-07-04-015`](docs/orchestration/agent-handoffs/2026-07-04-015-online-track-o2-player-games-facets.md). |
| 2026-07-04 | **Online realm query audit (read-path census)** — **65** paths on `ko2unity_db`; **4 >0.8 s**, **5 Heavy**; top pain: `/games/all.php` **3.94 s**, `/player/games.php?id=537` **3.26 s**, `server_play_texture` **1.92 s** (facet waterfall + full-table scan). Hot-path probe: score-line facets **~2.8 s**, player games validate+facets **~2.3 s**. Handoff [`2026-07-04-012`](docs/orchestration/agent-handoffs/2026-07-04-012-online-realm-query-audit.md) · results [`online_realm_full_census_results.md`](scripts/oneoff/online_realm_full_census_results.md). **Audit-only — no fixes shipped.** |
| 2026-07-04 | **Amiga realm full census re-run (post J–M)** — 96 paths; **3 >0.8 s**, **5 Heavy**; player/games id=382 **1.09→0.74 s**; new debt rivals H2H/W/D/L + games/recent (curl variance vs warmed lib probes). Results [`amiga_realm_full_census_results.md`](scripts/oneoff/amiga_realm_full_census_results.md) · handoff [`2026-07-04-013`](docs/orchestration/agent-handoffs/2026-07-04-013-amiga-realm-census-rerun.md). |
| 2026-07-04 | **Track M — player games facet dedupe** — career-wide facet bundle (request cache); validate reads bundle not 8 duplicate scans; single year histogram + single-pass gf/ga/gs/gd. Facet stack **~548 ms → ~210 ms** @ year:2024 TT (load **0 ms** on cache hit). Curl id=382 **0.53 s** best (was **1.09 s** census). Handoff [`2026-07-04-010`](docs/orchestration/agent-handoffs/2026-07-04-010-track-m-player-games-facet-dedupe.md). |
| 2026-07-04 | **Rivals W/D/L lean perf batch** — lean nation-pair perf subquery + directed rival-token IN filter; pass tokens from rollup rows. Parity OK. Lib Germany W/D/L @ year:2024 **~736 ms → ~557 ms**; perf_batch **~329 ms**. Handoff [`2026-07-04-011`](docs/orchestration/agent-handoffs/2026-07-04-011-rivals-wdl-lean-perf-batch.md). |
| 2026-07-04 | **Track L — Games hub + four LB wings (TT perf)** — Highlights inner LIMIT subquery + `046` metric indexes (lib **544→73 ms** present); catalog `SUM(game_count)` for TT games count; tournament COUNT cache; LB lede prewarm on peak/rating/perf-best/honours; Recent sectioned single table + batch-fetch dedupe. Parity green (`amiga_track_l_parity_probe.php`, `amiga_games_highlights_parity_probe.php`). Warm curl: highlights **0.26 s**, rating **0.23 s**, perf best **0.25 s**, honours **0.15 s**; recent/all still HTML-bound (**0.73 s** / **0.73 s** @ `year:2024`, lib **≤140 ms**). Handoff [`2026-07-04-009`](docs/orchestration/agent-handoffs/2026-07-04-009-track-l-games-hub-lb-tt-perf.md). |
| 2026-07-04 | **Country rivals Track K (TT perf)** — cross-border SQL on matchup window + W/D/L perf batch; H2H pair games use directed country WHERE; moments normalize ~9 picks only (929-game pairs). Lib @ `year:2024`: H2H **~440 ms** sequential; moments **8 ms**. W warmed curl: H2H **0.45 s** best; W/D/L **0.55 s** best; CvC APIs **0.12–0.15 s**. Parity OK — handoff [`2026-07-04-008`](docs/orchestration/agent-handoffs/2026-07-04-008-track-k-country-rivals-tt-perf.md) · probe `amiga_country_rivals_track_k_probe.php`. |
| 2026-07-04 | **Track J — player games pagination** — `K2_PLAYER_GAMES_PAGE_SIZE` (500) LIMIT/OFFSET on `/amiga/player/games.php`; shared `amiga_games_render_page_nav()`; status slice range + sort/filter links reset offset. Probe green (`amiga_player_games_pagination_probe.php`: ≤500 rows, ID parity). Curl id=382 @ `year:2024`: warm best **~0.69–0.82 s** (was census worst **1.09 s** on ~1492-row HTML); local 3-run variance up to ~1.1 s. Handoff [`2026-07-04-007`](docs/orchestration/agent-handoffs/2026-07-04-007-track-j-player-games-pagination.md). |
| 2026-07-04 | **TT F6 track closure (docs)** — F6/F18/F19 resolved in invariants; F20 query resolved; attempt log issue-closure summary |
| 2026-07-04 | **TT Track H — tournament tail + entities** — videos/stages defer standings+participation on non-content views; shared `amiga_tournament_videos_wings_for_id` + wc_game_index cache; `amiga_rated_games_from_sql` tournament/game inner scan; player tournaments catalog-stats knockout_ties + request cache. Parity green (`amiga_track_h_parity_probe.php`). Curl worst: videos **0.43 s**, stages@589 **0.46 s**, player/tournaments **0.39 s** (was ~0.5-1.0 s). Handoff [`2026-07-04-006`](docs/orchestration/agent-handoffs/2026-07-04-006-track-h-tournament-tail-tt-perf.md). |
*Older rows (before 2026-07-01): [docs/archive/session-log-2026-jun-prune.md](docs/archive/session-log-2026-jun-prune.md).*

| When | Note |
|------|------|
| 2026-07-04 | **Amiga TT table scroll pad + rating LB footer** — shared `k2-amiga-table-scroll-view` on WC hub + rating LB; rating footer “N rated games in database” removed (count stays in hub chapter lede via `amiga_lb_games_count()`). |
| 2026-07-04 | **Track I — WC player wings + HoF/chronology glue (TT perf)** — `amiga_lb_wc_slice_*` request cache keyed by cutoff only (removed per-sub-wing ORDER BY duplicate queries); present path one SELECT shared with shell `player_count`. Parity green (`amiga_wc_slice_parity_probe.php`). Curl @ `year:2024`: opponents **1.25→0.50 s**, honours **0.92→0.64 s**, results **1.21→0.50 s**; chronology/HoF unchanged (stats cache already D). |
| 2026-07-04 | **Player vs player opponents Track G (TT perf)** — `amiga_player_opponents_matchup_rows` request cache (pattern D); H2H panel single-pass + `matchup_row_from_rows` (eliminates duplicate matchup + triple directed reads); directed pair at cutoff → narrow window + PK join-back; `amiga_player_h2h_pair_game_rows_raw` memo (moments + chart API payloads). Curl id=382 present: h2h **0.17–0.18 s** (was **0.24–0.29 s**), wdl/goals/dds **0.12–0.14 s** (was **0.16–0.17 s**); @ `year:2024` h2h **0.25–0.30 s**, table wings **0.20–0.22 s**. Parity OK ×7 — `scripts/oneoff/amiga_player_opponents_parity_probe.php` · probe `amiga_player_opponents_tt_probe.php`. |
| 2026-07-04 | **Track F — LB remainder (TT perf verify)** — Five pages (`rating`, `peak-rating`, `perf/best`, `perf/top`, `tournament-honours`): prior F6 + slow-wings + perf-Best fixes already in tree; lib probe all hot paths **≤160 ms** @ worst TT (`month:2014-07`); warmed curl all **≤0.56 s** (under 0.8 s bar). Parity: peak **OK ×7**; perf Best per-player **OK** (json order-only on sorted export); honours cache hit 0 ms on 2nd count. New probe `scripts/oneoff/amiga_lb_track_f_tt_probe.php`. No lib code diff this slice. |
| 2026-07-04 | **Games hub Track C (TT perf slice 2)** — All games present: catalog path for count/years/host when no hero player (`amiga_realm_games_all_catalog_eligible`); score-line single-pass lean scan retained (~170 ms); lib probe present **~203 ms** blocking (was ~615 ms). Recent: batch tournament game fetch + `recent_tournaments` request cache + catalog recent count @ present. Highlights: direct lean `ORDER BY … LIMIT` (parity green ×5 boards ×3 cutoffs). Probes: `amiga_games_hub_tt_probe.php`, `amiga_games_highlights_parity_probe.php`. Handoff [`2026-07-04-004`](docs/orchestration/agent-handoffs/2026-07-04-004-amiga-tt-query-optimization-sweep.md) slice 2. |
| 2026-07-04 | **Perf. rating LB Best wing (TT)** — `amiga_lb_performance_rating_*_at_cutoff` + present Best: wide dual `ROW_NUMBER()` on `amiga_player_event_snapshots` → `amiga_lb_snapshot_from_sql('s')` + narrow best-event window (`amiga_lb_best_perf_event_join_sql`). Lib probe worst TT **284 ms → 62 ms** (`month:2025-09`); present **107 → 64 ms**. Parity OK per-player ×7 cutoffs — `scripts/oneoff/amiga_lb_perf_rating_best_parity_probe.php`. Top/Perfect wings get snapshot helper only (same session). |
| 2026-07-04 | **Track D — tournament entity + catalog (TT perf)** — shared read stack: at-cutoff `amiga_tournament_index_*` request cache (single SELECT, count from cache); batch `step_player_facet_counts` (`GROUP BY` replaces O(players) loop, **284 ms → ~45 ms**); lazy WC bracket on non-stages views; scoped caches on load/standings/participation/games/bracket. Lib bootstrap id=589 **~40–51 ms**; id=603 stages **~170 ms** cold / **~15–19 ms** warm; curl 5 URLs all **≤0.78 s** @ present + 3 cutoffs (fixtures 589/603). Parity green — `amiga_tournament_index_parity_probe.php` · `amiga_tournament_player_facet_parity_probe.php`; oracle `scripts/oneoff/amiga_tournament_tt_probe.php`. |
| 2026-07-04 | **Player vs country opponents Track B (TT perf)** — `amiga_player_opponents_country_*` load path: request cache + `withPerf` (W/D/L only); H2H single-pass + scoped pair perf + memoized country game rows; shared `amiga_matchup_at_event_latest_from_sql()` narrow window (pattern A). Curl busy player 382 @ `year:2024`: h2h **0.34 s** (was **0.49 s**), wdl **0.30 s**, goals/dds **~0.15–0.34 s**. Parity OK — `scripts/oneoff/amiga_player_opponents_country_parity_probe.php`. |
| 2026-07-04 | **Country rivals Track A (TT perf)** — Goals/DDs skip perf batch (`withPerf=false` default); W/D/L opts in; H2H pair perf + moments + chart payloads share memoized `h2h_game_rows_raw()`; batch perf request cache. Present curl: goals **0.21 s**, dds **0.19 s**, h2h **0.57 s** (was ~0.8–0.95 s); panel probe **208–273 ms** TT. Parity OK — handoff [`2026-07-04-002`](docs/orchestration/agent-handoffs/2026-07-04-002-f20-country-rivals-h2h-audit.md) Track A. |
| 2026-07-04 | **Amiga realm full census re-run (post J–M)** — superseded by [`2026-07-04-013`](docs/orchestration/agent-handoffs/2026-07-04-013-amiga-realm-census-rerun.md); prior snapshot below. |
| 2026-07-04 | **Amiga realm full census (post batch 1–2 refresh)** — 96 paths/APIs; **1 flagged >0.8 s** (`player/games.php?id=382` **1.09 s** HTML-bound, Track J pagination shipped). Post–Track K @ `year:2024`: rivals H2H **~0.45 s** warmed; W/D/L still **~0.55–0.87 s**; CvC APIs **~0.12–0.15 s** warmed. Remaining: W/D/L page target, games/recent **0.57 s**. Results `scripts/oneoff/amiga_realm_full_census_results.md`. |
| 2026-07-04 | **TT query sweep slice 1** — census probe `scripts/oneoff/amiga_tt_page_census_probe.php`; WC slice narrow window + cache (parity green); countries elo dense equality; Games All/Recent lean + catalog-stats TT path (**2 s → ~0.08 s curl @ year:2024**); player games player-scoped scan (query 15 ms, curl still ~1.4 s on 1500-row HTML). F20 rivals deferred. Handoff [`2026-07-04-004`](docs/orchestration/agent-handoffs/2026-07-04-004-amiga-tt-query-optimization-sweep.md) + playbook §4/§6. |
| 2026-07-04 | **TT stamp LED timing** — wing/toggle arrival (`k2_tt_entry`) hid the LED until `DOMContentLoaded` because `k2-amiga-tt-stamp.js` waited on `k2OnPageReady`; slow table HTML blocked parse → LED co-waited with table (F19). Fix: `initStamp()` at script eval (stamp markup + script emit together in ribbon, before table). No carry-scroll changes. Policy §5.0 Motion row updated. |
| 2026-07-04 | **TT sweep handoff opened** — realm-wide TT query optimization sweep for next agent: census probe over all wired TT pages ×3 cutoffs, worst-first fixes per playbook, parity oracles, success bar ≤ 0.8 s curl / no vanish — [`2026-07-04-004`](docs/orchestration/agent-handoffs/2026-07-04-004-amiga-tt-query-optimization-sweep.md). Known suspects: WC player/country slice windows, countries elo-rank attach (dense-equality candidate), histograms. F20 rivals stays on its own handoff. |
| 2026-07-04 | **TT query optimization playbook** — method doc for realm-wide TT perf slices: budgets (700 ms cloak / ~500 ms paint holding), probe-first workflow, five fix patterns (narrow window + PK join-back via `amiga_lb_snapshot_from_sql`, dense-event equality, metric-first index, request cache, no recompute), parity-oracle requirement, remaining-suspects inventory (slice snapshot libs, countries elo-rank attach, F20 rivals) — [`docs/amiga-tt-query-optimization-playbook.md`](docs/amiga-tt-query-optimization-playbook.md); linked from TT policy §10 + AGENTS traps + attempt log. |
| 2026-07-04 | **Three slow LB wings fixed (TT)** — tournament-honours / calendar-geo / peak-rating vanished in TT at any y: private queries never got the 3d-b1 narrow shape (0.6–3.4 s, past the 700 ms cloak/gate). Honours + calendar-geo → shared `amiga_lb_snapshot_from_sql()` (+ honours rows request cache, count now free); peak-rating → **dense-event equality** on `amiga_player_elo_rank_at_event` (`er.tournament_id = cutoff` — table verified dense, one row per debuted player per finalize) replacing a 173k-row window scan. All 40–92 ms, parity green ×7 scenarios, curl TT ≈ present. PHP memory was never the issue (4–6 MB; the heaviness was MySQL temp-table materialization). Probes `scripts/oneoff/amiga_lb_slow_wings_*`, `amiga_lb_peak_rating_parity_probe.php` — attempt log § Three slow LB wings. |
| 2026-07-04 | **F6 iter 3d-c shipped (TT y=0 chrome gate)** — Dagh: y>0 fixed by 3d-b, but y=0 still insta-vanished below ribbon on every TT nav (realm-wide). Mechanism: no cloak at y=0 → ribbon paints at TTFB → Chrome **paint holding ends** → old page discarded, void until chapter bytes. Fix in `k2_carry_scroll_restore.php`: y=0 + TT destination (`?as=`) = full-body cloak chrome gate (no contentful paint → old page held), reveal on `.k2-hub-chapter` parsed / domReady / 700 ms timeout; non-TT y=0 unchanged. Visual sign-off pending — handoff `2026-07-04-003`. **Same session: WC TT slowness fixed** — `amiga_community_year_realm_games_at_cutoff` full-scanned 446k-row `amiga_community_stat_facts` (2.6 s at event:583); new `idx_community_facts_metric_period` (48 ms; DDL `scripts/amiga/sql/034_community_stats.sql` + derived mirror, local applied, staging via next export) + `amiga_world_cup_stats_rows` request cache; chronology TT 5+ s → ~0.5 s. |
| 2026-07-04 | **F6 iter 3d-b shipped (rating LB TT)** — root cause 174-col `snap.*` window scan; `amiga_lb_snapshot_from_sql` narrow+join-back (~50 ms, was 0.5–2 s), slim Δ map (`amiga_rating_history_rating_map_at_cutoff`), games-count request cache; TT hub-nav→chapter block ~165–230 ms (was 710–906), under 700 ms cloak timeout; parity probes green — browser S1/S1b sign-off pending — [`2026-07-04-003`](docs/orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md). Peak-rating wing elo-rank query (~3.3 s TT) = follow-up (done same day, see slow-wings line). |
| 2026-07-04 | **Result streaks — orphan scope** — post-game writer + verify oracle skip deleted ids (no `playertable` row); fixes Steve simul FAIL on player 287 (LeeLaptop). Milestones unchanged. |
| 2026-07-04 | **F20 rivals H2H audit** — England/Italy probe: panel 2.2–3.7 s TT; double `rivals_rows`; Type B at y=0 / Type A at y>0; recommend query dedupe then PHP flush (3b). Handoff `2026-07-04-002`. |
| 2026-07-04 | **Status recency clocks** — Recent logins/games/live/day-tab times split weekday + `HH:MM` columns so clocks align vertically. |
| 2026-07-04 | **Status New players dates** — join dates space-padded (`Jul  1, 2026`) + right-aligned in recency column so comma/year line up before names. |
| 2026-07-04 | **Status active LB sort toggle** — `status.php` duplicate `k2-table.js` load (head + jukebox enqueue) fixed via `k2_table_js_enqueue()`; `k2-table.js` skips re-init on same table (`data-k2-table-init`). |
| 2026-07-04 | **H2H rating compare toolbar** — view + line-style toggles stacked in two rows (Amiga/online Opponents H2H; matches profile rank chart layout). |
| 2026-07-04 | **Amiga profile chart headings** — `Elo rating` / `Elo rank` panel titles use chart pitch / chart chrome ink (online rating heading stays chart amber). |
| 2026-07-04 | **Amiga profile rating chart toolbar** — view + line-style toggles stacked in two rows (matches rank chart toolbar layout). |
| 2026-07-04 | **Amiga profile chart ink (swap)** — solo rating → chart pitch (line, peak dash, toggles, peak links); solo rank → chart chrome; online rating stays chart amber; rating peak dash matches series ink (not holo). |
| 2026-07-04 | **Amiga profile chart ink** — solo rating chart → chart chrome (`T.chrome()` + chrome peak/toggle CSS); solo rank chart → chart pitch (`T.pitch()` + pitch peak/toggle CSS); online profile rating chart stays chart amber. |
| 2026-07-04 | **Player feast hero inset** — `--k2-player-hero-glow-inset` (14px) left margin on `body.k2-player-wing` feast hero so panel glow is not clipped at the content edge; vertical chrome stays 24px. |
| 2026-07-04 | **Online Peak rating LB context tooltip polish** — games list uses shared parent grid (dash-aligned scores); removed peak-game row highlight; game rows in idA/idB order; cursor-aware placement (prefer right of pointer); **newest game first** — `lb_peak_rating_lib.php`, `theme.css`, `lb-peak-rating-tooltip.js`. |
| 2026-07-04 | **TT baseline F6 iter 3b reverted** — PHP flush failed smokes; handoff for iter 3d+ rating LB — [`2026-07-04-003`](docs/orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md). |
| 2026-07-04 | **TT baseline F6 iter 3a result** — non-TT good; TT y=0 streaming gap (Type B); Countries y>0 whole-page blank (Type A) — analysis in attempt log. |
| 2026-07-04 | **TT perf probe** — month catalog 289× SQL (~283 ms); Countries index pays roster elo attach (~358 ms wasted) — `scripts/oneoff/amiga_tt_perf_probe.php`. |
| 2026-07-04 | **TT perf fixes** — month/year catalog from tournament list (~2 ms); Countries index SQL GROUP BY (~137 ms TT, parity OK) — Dagh sign-off: Countries snappy, month wing normal — `amiga_rating_history_lib.php`, `amiga_countries_lib.php`, `countries.php`. |
| 2026-07-04 | **F20 + roster audit** — country roster TT overfetch (~845 ms all players for 36 Greece); hash `#k2-country-roster` cloak — probe `amiga_country_roster_audit_probe.php`. |
| 2026-07-04 | **Country roster fetch slice** — `query_roster_rows` country-filtered + scoped elo IN (~208 ms late TT); parity OK — Dagh sign-off roster snappy; F20 chrome audit deferred — handoff [`2026-07-04-002-f20-country-rivals-h2h-audit.md`](docs/orchestration/agent-handoffs/2026-07-04-002-f20-country-rivals-h2h-audit.md). |
| 2026-07-04 | **TT baseline slice 0 iter 2** — carry-scroll narrow cloak-top, no early reveal at scroll top, picker anchor; F19 logged — handoff updated. |
| 2026-07-04 | **TT baseline failures logged** — **F6** (sub-ribbon blank at scroll top, ribbon stable, old→blank→new) + **F18** (Countries/WC hub tab TT late-cutoff whole-page blank; Present OK) — smokes S1/S1b/S9–S10 — [`amiga-tt-chrome-sticky-invariants.md`](docs/amiga-tt-chrome-sticky-invariants.md). |
| 2026-07-04 | **C02 TT ribbon pin removed** — surgical revert of `3567037` pin slice; in-flow baseline only — `amiga_snapshot_chrome.php`, `theme.css`; deleted `k2-amiga-time-travel-pin.js`. |
| 2026-07-04 | **TT chrome policy §7 trimmed** — cause/architecture tables removed; symptoms → failures register only. |
| 2026-07-04 | **WC Player stats — default sort regression** — `amiga_lb_wc_slice_rows_*` again use `amiga_lb_wc_slice_order_sql()` per sub-wing (was `player_id ASC` with skip-initial-sort); cache keyed by view. |
| 2026-07-04 | **Online profile rating chart — day hover tooltip** — By date hover reuses played-days game list (`player-calendar-day-tooltip.js` + `player_calendar_day_games.php`); summary adds “Rating at day end” in fixed chart amber; heatmap tooltips switched from tint link-star to `k2-link-star--chart-amber`. |
| 2026-07-04 | **Online profile rating chart peak** — fixed `[object Object]` in date peak summary (`peakAfterClause` object fallback); dashed peak line uses per-game career peak (not end-of-day) with y-axis headroom when intraday peak exceeds day-close plot points. |
| 2026-07-04 | **TT chrome sticky invariants** — first register from reverted attempt — [`amiga-tt-chrome-sticky-invariants.md`](docs/amiga-tt-chrome-sticky-invariants.md). |
| 2026-07-04 | **TT chrome sticky (CD track) — terminology locked** — §2.4: **sticky on/off**, **in flow**, **stuck** (three states); cross-refs swept — [`amiga-tt-chrome-dock-policy.md`](docs/amiga-tt-chrome-dock-policy.md). |
| 2026-07-04 | **TT chrome sticky (CD track) — policy revised** — reverted slice 0–6 code; stamp→ribbon in flow, in flow→stuck latch, pushpin sticky off; implementation plan retired — [`amiga-tt-chrome-dock-policy.md`](docs/amiga-tt-chrome-dock-policy.md). |
| 2026-07-04 | **TT chrome dock (CD track)** — policy drafted (superseded same day by revision above after revert). |
| 2026-07-04 | **K2 quiet date — complete** — five Amiga Date-default tables opt in (WC Chronology, tournament catalog, perf Perfect, player tournament history, live index); legacy `data-k2-quiet-sort-cols` removed from JS; docs swept. |
| 2026-07-04 | **K2 quiet date — implemented** — shared helpers, `data-k2-quiet-default-sort-cols`, unified CSS; removed wrong quiet on Amiga player games user Date sort. |
| 2026-07-04 | **TT ribbon wing tab order** — Event · Month · Year (was Year · Month · Event); default entry unchanged (`as=event:{first}`). Policy T8 + design-direction updated. |
| 2026-07-04 | **Amiga H2H rating compare peak links** — tournament names in peak summary use hero/rival chrome/red (`pm3-chart-peak-link--subject/opponent`); `peakAfterClause` now forwards `peakLinkClass` to rank chart core. |
| 2026-07-03 | **Player feast hero chrome gap** — `--k2-player-hero-chrome-gap` (24px): hub bar → hero + hero → player nav on `body.k2-player-wing` (online + Amiga); replaces `calc(nav-gap + 12px)`. |
| 2026-07-03 | **Amiga tournaments catalog filters** — host country / year / winner / winning country listboxes always visible; Reset filters pill always shown (`is-idle` when inactive); parity with player tournament history. |
| 2026-07-03 | **Filter stack spacing (shipped)** — Tier 1 five pages: bottom-only `--k2-nav-gap`, no wrapper vertical `gap`; Amiga player games scope tabs moved outside filter wrapper (wing→scope 12px, scope→form 12px). Policy [`filter-stack-spacing-policy.md`](docs/filter-stack-spacing-policy.md). |
| 2026-07-03 | **Amiga player tournaments filters** — four horizontal segment toggles (World Cups · Perfect run · Wins · Podiums); host country + year listboxes always visible (7.5rem / 50px / 6rem slots); Reset filters pill always shown (`is-idle` when inactive); host country panel rows get inline flags via shared `amiga_tournament_index_country_listbox_choices()`. |
| 2026-07-03 | **Mobile / smartphone policy** — locked intent: read-first + pinch-second; dense tables stay tables (not card-reflow debt). Agent doc [`k2-mobile-smartphone-policy.md`](docs/k2-mobile-smartphone-policy.md); cross-refs in `design-direction`, `PROJECT_BRIEF`, `k2-tooltip-policy`, `AGENTS.md`. Known gaps: chart tap-to-tooltip, hover+click tap/double-tap, nav touch targets. |
| 2026-07-03 | **Browser Back scroll restore** — `k2_carry_scroll_restore.php`: `pagehide` saves scrollY per URL; `back_forward` reload restores it (overrides `#player` hash landing); inbound hash scroll strips hash via `replaceState` so history tracks free scroll. |
| 2026-07-03 | **TT Event ribbon — table inline flag** — stepper uses `k2_amiga_inline_flag_and_link()` (`k2-amiga-wc-podium-player`, 20×15); not prose `inline-flag-text`. |
| 2026-07-03 | **Amiga rank chart peak — rating snapshot link** — always appends “Click here to see the time travel snapshot…” (`pm3-chart-peak-link` on *here* → `ratingSnapshotHref` / LB parity). |
| 2026-07-03 | **Amiga rank chart peak — absent tournament clause** — when peak rank tournament has no participation snapshot, summary appends same copy as peak-rating LB (`amiga_player_peak_rank_absent_clause()`); API `peak.playedInEvent` + `peak.absentClause`. |
| 2026-07-03 | **Amiga chart peak copy — inline flag text** — `k2-amiga-inline-flag-text` (16×12 baseline flag + link) replaces `k2-amiga-wc-podium-player` in peak summaries. |
| 2026-07-03 | **Amiga profile chart peak copy — tournament links** — rating + rank peak summaries: host flag + `k2-link-star` tournament link (`event-stats.php#tournament`); API `peak` adds `tournamentId`, `hostCountry`, `flagCode`. |
| 2026-07-03 | **Elo rating glance typography** — `--rating` hover matches tier B player glance (18px name, 9px labels, 14px values, 14×16 padding, 8×20 stat gap). |
| 2026-07-03 | **Elo rating glance copy** — name (+ Amiga flag), rank + rating, footer “Click to view rating leaderboard”; hidden at Amiga pre-debut cutoff. |
| 2026-07-03 | **Online LB + Status Elo links** — 11 hub LB wings + League honours + Status active table: `k2_lb_rating_cell_link()` → rating LB `#k2-lb-player-{id}`; `rating.php` row anchors + `lb-rating-page.js`; profile hero rank/rating → row anchor. |
| 2026-07-03 | **Amiga rating LB — same-page Elo row scroll** — row anchors moved to Player col (autorank was wiping rank col); `js/amiga-lb-rating-page.js` on `rating.php` only. |
| 2026-07-03 | **Amiga LB + WC player stats Elo links** — career Elo columns use `k2_amiga_lb_rating_cell_link()` (rating LB `#k2-lb-player-{id}`; country roster parity); 7 LB wings + perf-rating table + 5 WC player sub-wings. |
| 2026-07-03 | **Status heritage box** — removed hover tooltip (`data-k2-help` on boxart link); border/glow hover unchanged. |
| 2026-07-03 | **Status heritage box hover** — border hover matches profile moment cards (2px accent + `--k2-accent-glow` outward); image lift/scale unchanged; interior lamp inset kept (`theme.css`). |
| 2026-07-03 | **Player glance — nav pill false trigger** — `amiga-player-glance.js` no longer treats any profile URL as a glance anchor; requires `data-k2-player-glance` / `data-k2-amiga-player-glance` (wing Profile pill excluded). Hand-rolled player links migrated to `k2_player_link()`; Status leagues JS picks get explicit attribute. |
| 2026-07-03 | **Player hero atomic paint (refresh narrow flash)** — feast hero (`width: fit-content`) painted mid-parse at partial width (~283px vs 751px) with the glow border on reload → narrow border flash. Fix: inline parser-sync scripts around the hero `<article>` toggle `html.k2-player-hero-parsing` (`k2_player_hero_atomic_paint_open/close()` in `k2_player_hero_glow_session.php`, both hero includes); `player-hero-rank.css` hides the feast hero while set, DOMContentLoaded fallback un-hides. Hero now paints fully laid out or not at all — verified via rAF paint log (hidden during 283px frames, visible only at 751px). |
| 2026-07-03 | **Carry-scroll y=0 pill-nav hero flash** — `carryReady()` waits for the destination page's matching `nav[data-k2-carry-scroll]` (payload anchor label) before uncloak — hero above the nav is fully parsed; content below (huge games tables) still streams (no blanking). Uncloak = `carryReady() \|\| domReady`. Fixes tab-switch-at-top narrow flash / blink; refresh path covered by atomic hero paint row above. |
| 2026-07-03 | **Amiga player hero WC medals** — +15px right margin on `.k2-player-hero__medals` (bronze column breathing room vs hero border); in commit 047f6af. |
| 2026-07-03 | **Theme.css dead-token pass (v1 complete)** — ~45 proven-dead selectors removed (elolist table compat, old join-page tokens, retired header chrome, hero Country stat, WC LB tabs, pad-left sm/xl/xxl/xxxl, `--k2-h2h2-red-ring`); 7,052 → 6,691 lines; dynamic-class + protected families kept; audit `scripts/audit_theme_css_dead_tokens.py`; log in [`DEAD_SURFACE.md`](docs/DEAD_SURFACE.md) § Jul 2026; smoke OK. |
| 2026-07-03 | **Theme.css dead-token cleanup starter** — handoff prompt for grep-only `theme.css` hygiene pass ([`theme-css-dead-tokens-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/theme-css-dead-tokens-STARTER-PROMPT.md)). |
| 2026-07-03 | **PROJECT_MEMORY prune** — Recent log trimmed to Jul 2026 rows (162 kept); **531** older rows → [`docs/archive/session-log-2026-jun-prune.md`](docs/archive/session-log-2026-jun-prune.md). |
| 2026-07-03 | **K2 table audit — Amiga highlights exception** — `amiga_games_highlights_helpers.php` whitelisted (compact board, online parity); `amiga/games/highlights.php` shell-owned head; audit PASS (0 Tier C). |
| 2026-07-03 | **Profile goals-per-game hint** — “How many games {name} scored…” uses link-star player name instead of “he”. |
| 2026-07-03 | **Amiga Activity People wing intro** — question-led lede: actives, debuts, unique/new matchups (replaces bar-chart pairing hint). |
| 2026-07-03 | **Amiga Activity Shape wing intro** — *What are we made of?* + histogram catalog lede (Texture-wing parity; TT long-tail hint dropped from copy). |
| 2026-07-03 | **Amiga Activity Texture wing intro** — *What are the games like?* narrative lede with Dartford / Copenhagen tournament links; full arc once cutoff ≥ WC XIV (577); simpler *to present day* before that. |
| 2026-07-03 | **Amiga Activity GEO-008 year bar tooltip** — beside bar (left/right), same placement as WC participants/nations breakdown bars. |
| 2026-07-03 | **Amiga Activity chart HTML tooltips** — default above placement clamps to viewport (fixes left-edge cumulative points clipping off-screen). |
| 2026-07-03 | **Amiga Activity GEO-009 cumulative chart** — hover/click points limited to host-country unlock events (~12, not every tournament); trailing anchor extends stepped line to latest event. |
| 2026-07-03 | **Amiga Activity GEO-009 cumulative tooltip** — host flag inline before tournament name at every point (Growth-style HTML tooltip; reuses `cumulativeTooltipFlagHtml`). |
| 2026-07-03 | **Amiga Activity WC games/year chart** — realm ghost layer hidden by default; legend toggles comparison; hint under heading. |
| 2026-07-03 | **Amiga Countries index — WC players column** — read-time count of rated nationals with `wc_played ≥ 1` before WC entries; roster hero stat + tooltip; policy updated. |
| 2026-07-02 | **Amiga Activity Geography — Compare B empty default** — second Compare listbox adds top `...country` placeholder (default empty); duel year bars start single-series (England only); default race lines = top **4** countries (`AMIGA_COMMUNITY_GEO_RACE_KEYS_DEFAULT`); reverts same-day “Compare B required” rule. |
| 2026-07-03 | **Player games table tooltips (online + Amiga)** — no help on Team A/B; GF · GA · GD · ES · Result · Adjustment use hero name instead of “this player”. |
| 2026-07-03 | **Amiga player games table** — parity with online player games: **Result** before **Adjustment**; hero/opponent rating cols → **Rating A** / **Rating B** (`amiga/player/games.php`, `amiga_player_game_row.php`). |
| 2026-07-03 | **Online player games table** — hero/opponent rating cols → **Rating A** / **Rating B** (Team A/B pre-game Elo; tooltips match Amiga games recent); sort keys `rating_a`/`rating_b`. |
| 2026-07-03 | **Online player games table** — **Result** column moved to just before **Adjustment**; sort-col index map + row renderer updated (`player/games.php`, `k2_player_game_row.php`). |
| 2026-07-04 | **Profile hero/glance milestones** — tier counts load for all players with `player_milestones` rows (incl. `entered_arena` at register); removed erroneous `NumberGames >= 1` gate on hero + at-a-glance. |
| 2026-07-04 | **Profile presence dates** — first/last rated game from `ratedresults` only (no `JoinDate` / `LastGame` fallback); **—** when zero games; played-days/weeks calendars skip when no debut. |
| 2026-07-04 | **Player search — no games gate** — `api/player_search.php` lists all named accounts (online + Amiga); rating meta uses `COALESCE(Rating, 1600)`. Ladder/filter pickers elsewhere still use `NumberGames >= 1` where appropriate. |
| 2026-07-04 | **Retire Display read guards site-wide** — PHP read paths use **`NumberGames >= 1`** for ladder pool (`k2_playertable_rated_pool_sql()` in `lb_player_filters.php`); lobby recency + milestones Recent ungated. Legacy `Display` column untouched on import; ops post-game write unchanged pending Steve policy. |
| 2026-07-02 | **Online player games table** — dropped redundant **Opponent** name column (Team A/B already show both players); updated `k2_player_game_sort_col_index()` + row renderer column indexes (`player/games.php`, `k2_player_game_row.php`). |
| 2026-07-03 | **Player name hover glance — show gate 500ms** — `SHOW_DELAY_MS` in `amiga-player-glance.js` (online + Amiga). |
| 2026-07-03 | **Amiga tournament stepper — host-country option flags** — host-country listbox dropdown rows only (`flag_html`); with-player stays text-only. |
| 2026-07-02 | **Amiga tournament stepper — WC only pill** — `id_wc=world-cup` toggle on entity chevrons (Shuffle-style pill before with-player); faceted listbox counts + auto-snap; propagates on wing/chevron links — [`with-player-stepper-policy.md`](docs/with-player-stepper-policy.md) §5.7. |
| 2026-07-02 | **Status Leagues period nav** — wrapper transparent (no segment chrome); **60px** `column-gap` between period tabs and step nav; stacked-row JS uses line-break detection (`theme.css`, `status-period-competitions.js`). |
| 2026-07-02 | **Games hub mobile viewport fix (both realms)** — root cause: absolute `.visually-hidden` th spans (Adjustment lost) escaped unpositioned `.k2-table-wrap` and widened the document → phones zoomed the whole site out. Fix: `.k2-table-wrap { position: relative }` + `overflow-x: hidden` safety on `html`/`body`/hub wrappers. Amiga Highlights → compact `k2-games-highlights-table` (online parity, no scroll mirror; `amiga_rated_game_highlights_row_html()`). |
| 2026-07-02 | **Status west recency lists** — New players / Recent logins / Recent games: subgrid date column (widest row per panel) + 8px gap; names align without widening panels (`theme.css`). |
| 2026-07-02 | **Online player hero — milestone tier counts** — hero + name-hover glance (tier B): four tier-colored counts (all tiers incl. zero, space-separated); garden tier links + tooltips; pure tier ink, weight 600; spacing tuned in `player-hero-rank.css`. Scope: `player_hero.php` + online glance API/JS. |
| 2026-07-02 | **Player name hover glance UX** — canvas/print split: opaque slab instant; border, content, lift shadow fade 850ms on print; fetch on hover; 150ms show gate; no loading chip. |
| 2026-07-02 | **Player hero feast width** — `.k2-player-hero--feast` shrink-wraps to content (`fit-content` + inner `max-content` grid); online + Amiga player wing tabs. Popover glance unchanged. |
| 2026-07-02 | **Amiga player name hover glance** — read-only tier **B** default on Amiga player links; API `amiga_player_glance.php`. **Lift shadow:** omnidirectional neutral halo (`--k2-amiga-glance-lift-shadow` in `amiga-player-glance.css`) — zero offset, layered blurs + 1px ring; separate from accent border glow; tuned for busy table backgrounds. |
| 2026-07-02 | **Amiga Activity Texture — low-scoring rate** — `low_scoring_games` year/realm fact + `low_scoring_rate` API + 6th Texture panel (≤3 goals, per 100 games); registry + Python/PHP writers; **`prove` green** local. |
| 2026-07-02 | **Amiga WC stats wings** — removed Activity cross-link intro from all Tournament stats sub-wings (`amiga_world_cup_stats_wing_body.inc.php`). |
| 2026-07-02 | **Amiga Activity Texture — high-scoring hint** — `k2-chart-block__hint` under High-scoring rate panel (ten+ goals both sides, per 100 games); matches online Activity chart hint pattern. |
| 2026-07-02 | **Activity summary copy** — second sentence “We average…” (online + Amiga activity lede). |
| 2026-07-03 | **Amiga WC country Honours — WCs before WC players** — column order swapped on honours table only. |
| 2026-07-03 | **Amiga WC country Results + Participation — WC entries prefix** — shared prefix drops **WCs** for **WC entries** (Honours tooltip); Participation removes duplicate **Entries** column; default sort col 3. |
| 2026-07-03 | **Amiga WC country stats — WC players column** — all six sub-wings: header **WC players**, tooltip label **World Cup players** (`amiga_wc_countries_table.php`). |
| 2026-07-02 | **Amiga player hero Events anchor** — Events stat + profile tournament history links land on `#k2-player-tournaments-table` (all-events filter). |
| 2026-07-02 | **Amiga player tournaments table anchor** — `#k2-player-tournaments-table` above history table + scroll pad; hero World Cups + honours WC history links land there (`k2_carry_scroll_restore`). |
| 2026-07-02 | **Amiga player hero layout** — World Cups with core stats (Rank–Games); WC medals alone after 20px gap (country-hero parity). |
| 2026-07-02 | **Amiga player hero World Cups stat** — WC slice `tournaments_played` before medal counts; links to Tournaments wing World Cups filter; TT cutoff from slice-at-event row. |
| 2026-07-02 | **Amiga player hero Events stat** — stored `tournaments_played` between Rating and Games; links to Tournaments wing; TT cutoff from snapshot row. |
| 2026-07-02 | **Activity year-bar HTML tooltips + prove** — shared `renderBreakdownYearBar()`: GEO-008/Q-VOL-005 (`host_tournaments_by_year`), GEO-010 (`nationality_active_by_year`), Q-WC-006/007 (`wc_active_players` → `wc_nationality_active_by_year`); **`prove` green** local (~23 min). |
| 2026-07-02 | **Nations player grains — docs + prove** — Activity **48 panels / 49 Q-IDs**; +3 nationality player grains (no DDL). |
| 2026-07-02 | **Nations player grains B–D shipped** — `all_time×nationality×active_players`, `year×nationality×player_debuts`; 3 new Nations panels; 8-panel page order; GEO-010 tooltip (no list scroll). |
| 2026-07-02 | **Amiga Activity Nations — distinct nationalities tooltip** — new stored fact `year × player_nationality × active_players`; `year_facts` returns `nationality_active_by_year` breakdown; bar hover lists flag + country + active player count (HTML tooltip). Re-prove `ko2amiga_db` to populate facts. |
| 2026-07-03 | **Amiga Activity draw-rate tooltip** — year bar hover shows *7.8% of 1,234 games* (denominator from `year_rates.denominator_by_year`, TT-aware). |
| 2026-07-02 | **Amiga tournament video game links GL-5…6 shipped** — `video_game_links.csv` sidecar merge (`stream_map` mode), manifest `game_start_sec[]`, sync/verify/build wired; policy + README + implementation plan trimmed. Sidecar empty until stream curation. |
| 2026-07-03 | **Amiga Activity World Cups wing lede** — section title *How big is the big stage?*; year-by-year participants · nations · games · goals copy. |
| 2026-07-03 | **Amiga Activity WC cumulative chart fix** — `amiga_community_snapshot_series()` filters `WcGamesPlayed` to World Cup catalog names only (~23 points, not 605 realm events). |
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
| 2026-07-01 | **Mobile nav load bar** — restored `.turbo-progress-bar { display:none }` in `theme.css` (stale Turbo cache on phone); site-wide `theme-color` + `color-scheme: dark` in `k2_head.php` so browser chrome progress blends with page bg. |
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
| 2026-07-06 | **Profile played-days/weeks** — narrative counts + calendar/rating-chart day tooltips use `k2-link-star` (not chart amber); Amiga rating tooltips keep chart pitch. |
| 2026-07-01 | **Amiga Activity summary — player averages** — removed Games per player card; prose below cards matches online (`Players average … rated games and … different opponents.`). |
| 2026-07-01 | **Perf-rating LB W/L color** — Best · Top 100 · Perfect tables: W/L cells use `.blue` / `.red` via `amiga_profile_tournament_wdl_cell()`. |
| 2026-07-01 | **Rating LB W/L color** — Wins/Losses cells on online + Amiga `/leaderboards/rating.php` use `.blue` / `.red` (via `k2_fmt_wdl_count()`); zero stays plain. |


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
