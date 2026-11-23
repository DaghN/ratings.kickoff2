# Finished tracks — moved out of PROJECT_MEMORY "Current focus"

**Moved 2026-11-23**, unchanged except for link paths. These were listed under "Current focus" but are finished (most of them June–July 2026). For what is in motion now, read [`PROJECT_MEMORY.md`](../../PROJECT_MEMORY.md).

---

- **Amiga profile (Jul 2026):** **Track B** — mosaic stat links; **Player chronologies complete for mosaic inventory** — Victims & Culprits + Calendar & geo country unlocks (host/faced/beaten/beaten by); policy [`amiga-player-chronologies-policy.md`](../amiga-player-chronologies-policy.md). **Inverse-count timeline shipped + export/Case C proven** — sparse changelog `amiga_player_inverse_count_at_event` (3,423 rows on tip seal); mosaic + Victims LB TT read changelog; PHP finalize seeds from changelog; `verify-inverse-count-changelog` green — [`amiga-player-inverse-count-timeline-policy.md`](../amiga-player-inverse-count-timeline-policy.md) · proof [`amiga-export-inverse-roundtrip-test-plan.md`](../amiga-export-inverse-roundtrip-test-plan.md).

- **K2 LB SSR (Jul 2026):** **Track A complete** — all hub wings URL landing sort ([`k2-lb-ssr-sort-policy.md`](../k2-lb-ssr-sort-policy.md) **Implemented**). Track **B** = profile mosaic stat links.

- **Milestones:** Catalog **112**; v0 UI + **`kooldb1` simul proof** done. Live writer = **PHP ops** (live since cutover 2026-07-18).

- **Cutover:** Schema + PHP ops + **`kooldb1` simul** + **live execution Done 2026-07-18** — [`cutover-readiness.md`](../coordination/cutover-readiness.md) layers A+B+C.

- **Activity wing (Leaderboards):** **Proven `kooldb1` (Jun 2026)** — SCH-022–025 ops + LB UI (Peaks · Participation · In a row); Steve full bootstrap + simul + verify **0 fail** (participation, play-streak HoF, reached_at oracle). Policy: [`activity-wing-stored-truth-policy.md`](../activity-wing-stored-truth-policy.md). **HoF:** month/year play-streak rows + participation block shipped.

- **Result streaks (Streaks LB):** **Shipped Jun 2026** — SCH-026 `player_result_streaks` + post-game writer + verify; LB tooltips/click-through + player-games streak banner. Work smoke PASS; **`kooldb1` proof** when Steve syncs migration `026` + re-simul.

- **Leagues:** **Honours proven `kooldb1`** (`leaderboards/league-honours.php`). Live = `FinalizeUtcDay` when wired.

- **Status Leagues:** **Shipped** — [`status-period-competitions.md`](../status-period-competitions.md). Meta line: **Week 49, 2025 League** order; period label + rated-games count both `.blue`.

- **Profile (online):** **Complete** — production feast on **`player/profile.php`**; no active build track; spec [`player-profile-feast.md`](../player-profile-feast.md). **Amiga profile:** gradual polish only — [`amiga-profile-v0.md`](../amiga-profile-v0.md). Multi-agent lab sandboxes removed Jun 2026 (handoff archived).

- **Design / Status hub:** Phase B v1.2 room grid shipped. **Live room (v1.5) shipped** — SRL-9 `live_clocks`, cascade glow (LB Elo + league Games/Pts), work sim wall-clock catch-up — [`status-room-live-policy.md`](../status-room-live-policy.md). Prod live DB read + joshua redirect = **deferred** ([`STATUS_PAGE_DATA.md`](../STATUS_PAGE_DATA.md)).

- **Activity (`activity.php`):** Charts v2 shipped **local + staging** — `activity-charts-v2.js` + `server_activity_chart_panels.php` ([`activity-charts.md`](../activity-charts.md)). Optional L4 polish in feature doc only.

- **Hub IA:** Status · Activity · Leaderboards · Milestones · **Games** · HoF · Play & Setup — [`hub-ia-agreement.md`](../hub-ia-agreement.md). **Navigation invariants** (hub bar always present; active pill only on places; **entity pages** at realm root, no pill; singular=entity / plural=hub) — [`navigation-model.md`](../navigation-model.md) NM1–NM6. **Games hub (Jun 2026):** `games/recent.php` + Highlights + **All games** vault (filters, server sort). **URLs:** semantic paths + `games/` + `milestones/` folders; registry [`k2_routes.php`](../../site/public_html/includes/k2_routes.php) — [`url-routes.md`](../url-routes.md).

- **DB performance:** `idx_ratedresults_idA/idB` in ops migration **001** (migrate-work); proven on work DB; live = cutover migrate step.

- **Amiga time travel (Jul 2026):** **TT ribbon sticky v1** — CSS `position:sticky` on all `as=` pages (sticky on only; no pushpin). **F6 nav track signed off** — narrow snapshot reads + y=0 chrome gate + realm query sweep; Dagh S1/S1b pass — [`tt-chrome-baseline-f6-attempt-log.md`](../orchestration/tt-chrome-baseline-f6-attempt-log.md) · [`2026-07-04-003`](../orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) · sticky slice [`2026-07-04-018`](../orchestration/agent-handoffs/2026-07-04-018-tt-ribbon-sticky-v1-css.md).

- **Amiga rating history (Jun 2026):** **V1** — History hub + time-travel rating LB; **News tab (Jul 2026)** — roll + pulse invites (no “coming soon”); [`amiga-rating-history-policy.md`](../amiga-rating-history-policy.md) · [`present-layer-ia.md`](../present-layer-ia.md) §5.5.

- **Amiga event snapshots (Jun 2026):** **Complete (slices 0–9)** — `amiga_player_event_snapshots` + `amiga_player_current`; legacy four tables retired; holy loop `python -m scripts.amiga prove` green. Policy [`amiga-event-snapshot-policy.md`](../amiga-event-snapshot-policy.md).

- **Amiga matchup at event (Jun 2026):** **Complete (slices 0–6)** — `amiga_player_matchup_at_event` + finalize-only network/peaks/H2H; replay tail batches removed. Policy [`amiga-matchup-at-event-policy.md`](../amiga-matchup-at-event-policy.md). HoF → [`amiga-realm-snapshot-policy.md`](../amiga-realm-snapshot-policy.md).

- **Amiga per-opponent performance rating (SCH-044, Jun 2026):** **Complete** — cumulative directed pair TPR stored on `amiga_player_matchup_summary` + `amiga_player_matchup_at_event` (`performance_rating`), recomputed only for pairs played each event at finalize (Python replay = in-memory samples; PHP/live = reseed touched pairs from `amiga_game_ratings`). Surfaced as the **Perf.** column on Opponents W/D/L and read by H2H pair detail (no on-the-fly solve); time travel = latest at-event row ≤ cutoff. `verify-player-matchups` perf oracle; `replay` + verify green. [`amiga-performance-rating.md`](../amiga-performance-rating.md) · [`amiga-matchup-at-event-policy.md`](../amiga-matchup-at-event-policy.md).

- **Amiga realm snapshots (Jun 2026):** **Complete (slices 0–8)** — incremental finalize + `amiga_realm_snapshots` timeline; HoF peak row = read-time `PeakRating` (retired `BiggestPeakRating` Jun 2026); `prove` green ~5 min. Policy [`amiga-realm-snapshot-policy.md`](../amiga-realm-snapshot-policy.md).

- **Amiga HoF calendar-year + geography (Jun 2026):** **Complete** — eight new HoF rows + Calendar & geo LB wing; SCH-028 on snapshots/current + `generalstats`; `verify-hof-geo-year` in `prove`. Policy [`amiga-hof-tournament-geo-policy.md`](../amiga-hof-tournament-geo-policy.md).

- **Amiga HoF record rise dates (Jun 2026):** **Complete (SCH-029, slices 0–8)** — per-metric `*_last_rise_*` on snapshots/current; HoF `*Date` from rise not participation; Python + PHP finalize parity; `verify-hof-geo-year` date oracle. [`amiga-hof-record-date-policy.md`](../amiga-hof-record-date-policy.md).

- **Amiga career HoF rise dates (Jun 2026):** **Complete (SCH-030)** — ten legacy career rows (`MostGamesPlayed` … `BiggestRatingAscent`) get `*_last_rise_*` on snapshots/current; HoF `*Date` from event where scalar last rose; `verify-hof-geo-year` extended (32 rise cols + 18 HoF dates); `prove` green. Plan [`amiga-hof-career-rise-implementation-plan.md`](../amiga-hof-career-rise-implementation-plan.md).

- **Amiga stored id/date semantics Phase B (Jun 2026):** **Complete** — `verify_hof_holder_projection` in `prove` (career source-field dates, game-anchored + ratio oracles). Manifest [`amiga-stored-field-semantics.md`](../amiga-stored-field-semantics.md); plan [`amiga-stored-field-semantics-plan.md`](../amiga-stored-field-semantics-plan.md).

- **Amiga stored id/date semantics Phase C (Jun 2026):** **Complete** — `verify_stored_id_date_pairs` in `prove` (rise FK pairing, honours_last / last participation, career-best replay).

- **Amiga stored id/date semantics Phase D (Jun 2026):** **Retired with refinalize** — `verify-php-finalize-parity` removed Jun 2026 ([`archive/retired-amiga-refinalize-2026-06.md`](retired-amiga-refinalize-2026-06.md)); batch `*-rebuild` CLIs retired same era ([`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md)). Phases A–C (`verify-hof-holder-projection`, `verify-stored-id-date-pairs`, manifest) remain in `prove`.

- **Amiga ground layers L0–L5 (Jun 2026):** Slices **1–11 complete** — strict stack shipped (`prove` L1→L5, `verify-l2-l3`). [`amiga-ground-stack.md`](../amiga-ground-stack.md).

- **Amiga time travel (Jun 2026):** **Phase 1 complete** — header **Present day | Time travel** + one-row ribbon above hub when active; LB (8 wings), HoF at cutoff; **Profile tab full lens shipped Jul 2026-14**. Smoke: `scripts/oneoff/amiga_time_travel_smoke.php`. [`amiga-time-travel-policy.md`](../amiga-time-travel-policy.md).

- **Amiga time travel (Jun 2026):** **T13–T19** — snapshot-only TT hub; **with-player track complete** — **`as_with=`** on TT ribbon **Year/Month/Event** (+ preamble snap), **`id_with=`** + **`id_country=`** (tournament chevrons + page-entry snap), **`start_with=`** (league periods + bootstrap snap). [`with-player-stepper-policy.md`](../with-player-stepper-policy.md) §10 module map.

- **Amiga Opponents wing (Jun 2026):** **W/D/L · Goals · DDs + H2H (slices D+F) shipped** — poster/pickers/pair detail/moments/charts on `amiga/player/opponents/h2h.php`; Amiga `realm=` API branches + event-step rating compare. Policy [`amiga-opponents-wing-policy.md`](../amiga-opponents-wing-policy.md). **Country grain (Jun 2026):** **OCG-1–OCG-7 complete** — roll-up + read-time country TPR; country **W/D/L · Goals · DDs** tables; country **H2H** (poster/pickers/detail/moments/game charts, no rating/rank compare); API `opp_country` + chart JS grain — [`amiga-opponents-country-grain-policy.md`](../amiga-opponents-country-grain-policy.md) · [`amiga-opponents-country-grain-implementation-plan.md`](../amiga-opponents-country-grain-implementation-plan.md).

- **Amiga World Cups LB (Jun 2026):** **V2 UI** — five sub-wings on **World Cups hub → Player stats** only; LB wing **retired** Jun 2026 (legacy URLs 302). Writers proven Jun 2026-23. **Opponents wing inventory links complete (Jul 2026)** — Games + nine `wc_*` chronology destinations (cols 3–13); policy [`amiga-player-chronologies-policy.md`](../amiga-player-chronologies-policy.md) §4.0. [`amiga-world-cups-leaderboard-policy.md`](../amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cups-player-slice-v2-policy.md`](../amiga-world-cups-player-slice-v2-policy.md).

- **Amiga WC HoF (Jun 2026):** **Complete (WCH-1…8, SCH-046)** — 28 WC record rows; sparse `amiga_wc_hof_{snapshots,present}` + HoF UI block + time travel; Python + PHP finalize parity; `prove` green. [`amiga-wc-hof-policy.md`](../amiga-wc-hof-policy.md) · [`amiga-wc-hof-implementation-plan.md`](../amiga-wc-hof-implementation-plan.md).

- **Amiga community stats (Jun 2026):** **V2 writers shipped** — registry v2, `036`/`037`, `prove` green. **Amiga Activity hub v1 shippable (Jul 2026)** — **49 panels / 50 ship IDs** on `/amiga/activity/` six wings; wing copy locked — [`amiga-activity-charts-policy.md`](../amiga-activity-charts-policy.md) §5.0 · plan [`amiga-activity-charts-implementation-plan.md`](../amiga-activity-charts-implementation-plan.md) **complete** (slices 0–10 + copy pass). Per-WC table on World Cups hub wing 2 **shipped**. No open v1 chart backlog.

- **Amiga World Cups hub (Jun 2026):** **Wings 1–4 shipped** — **events catalog** (sortable table, podium flag+name cols) + tournament stats (five sub-wings) + **player stats** + **country stats**. [`amiga-world-cups-hub-policy.md`](../amiga-world-cups-hub-policy.md).

- **Amiga derived writes (Jun 2026):** **Locked** — batch `*-rebuild` CLIs removed; corrections = **`prove` only**; verify = read-only oracles. [`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md).

- **Amiga Countries hub (Jun 2026):** **Shipped** — hub tab + index (player count sort) + country entity **Roster · Rivals** (CRV-1–7: `country/rivals/{h2h,wdl,goals,dds}`); flag links site-wide; cross-links WC country stats. [`amiga-countries-hub-policy.md`](../amiga-countries-hub-policy.md) · [`amiga-country-rivals-policy.md`](../amiga-country-rivals-policy.md).

- **Amiga perfect event (Jun 2026):** **Shipped** — SCH-045; honours LB + WC **Perfect** column; catalog filter; HoF **Most perfect events**. [`amiga-perfect-event-policy.md`](../amiga-perfect-event-policy.md).

- **Amiga perf. rating LB (Jun 2026):** **Shipped** — folder `performance-rating/{best,top,perfect}.php` + segment nav; W-D-L columns; Top 100 fixed set; Perfect shows **∞**. [`amiga-performance-rating-leaderboard-policy.md`](../amiga-performance-rating-leaderboard-policy.md).

- **Amiga tournament videos (Jun 2026):** **TV-3 + TV-4 shipped** — manifest **~300** videos; unified embed UI; **C06** dedicated Videos column; **With videos** filter; **player profile Videos wing**. **Jul 2026:** **TV-2b DB anchor sync** — `sync_db_ids` + `verify-tournament-videos` in `prove` ([`amiga-tournament-videos-policy.md`](../amiga-tournament-videos-policy.md) §12).

- **Amiga format scoring contract (Jul 2026):** **SC-0–SC-9 + SC-11 shipped** — L4b relational contracts, PHP↔Python executor parity, RTB broadcast, L5 `stage_id`, structured ET/pens on games + fixtures (`012_match_extensions.sql`). **SC-10:** non-WC games **100%** `fixture_id` on work (Jul 2026); remaining unlinked games = **unmaterialized WCs** (`wc_deferred`). Policy [`amiga-format-scoring-contract-policy.md`](../amiga-format-scoring-contract-policy.md) · plan [`amiga-format-scoring-contract-implementation-plan.md`](../amiga-format-scoring-contract-implementation-plan.md).

- **Obsolete dev scripts retirement (Jun 2026):** **Track complete** (slices 1–6) — retired batch/replay CLIs stubbed or archived; `scripts/k2_rating_core/` is the shared formula library; runbooks → holy ops — [`obsolete-dev-scripts-retirement-policy.md`](../obsolete-dev-scripts-retirement-policy.md) · inventory [`DEAD_SURFACE.md`](../DEAD_SURFACE.md).
