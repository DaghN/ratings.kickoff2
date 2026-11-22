# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily agents between sessions (Cursor until Jul 2026; Claude Code from Nov 2026 via `CLAUDE.md`). Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

**Amiga agents (Jul 2026):** Forward = **`ko2amiga_work`** + **`simul`**. **Pull staged** = `pull_ko2amiga_from_staging.ps1 -Force` · **Side-pull compare** = `-TargetDatabase ko2amiga_staging_cmp` (work untouched) · **Push staged** = `push_ko2amiga_to_staging.ps1 -Force` (export + gate + WinSCP + Apply import + verify; Nov 2026) · export-only = `export_ko2amiga_work.ps1`. Legacy **`prove`** / Access L0–L5 = oracle only — [`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) §0.

---

## Current focus

- **Amiga profile (Jul 2026):** **Track B** — mosaic stat links; **Player chronologies complete for mosaic inventory** — Victims & Culprits + Calendar & geo country unlocks (host/faced/beaten/beaten by); policy [`amiga-player-chronologies-policy.md`](docs/amiga-player-chronologies-policy.md). **Inverse-count timeline shipped + export/Case C proven** — sparse changelog `amiga_player_inverse_count_at_event` (3,423 rows on tip seal); mosaic + Victims LB TT read changelog; PHP finalize seeds from changelog; `verify-inverse-count-changelog` green — [`amiga-player-inverse-count-timeline-policy.md`](docs/amiga-player-inverse-count-timeline-policy.md) · proof [`amiga-export-inverse-roundtrip-test-plan.md`](docs/amiga-export-inverse-roundtrip-test-plan.md).

- **K2 LB SSR (Jul 2026):** **Track A complete** — all hub wings URL landing sort ([`k2-lb-ssr-sort-policy.md`](docs/k2-lb-ssr-sort-policy.md) **Implemented**). Track **B** = profile mosaic stat links.

- **Ladder ops (Jul 2026):** PHP post-game **P0–P7** live on prod — **cutover executed 2026-07-18** (Steve ground insert + invoke; derived writers from this repo). Staging simul was signed off earlier on `kooldb1`. Runbook: [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md). Discrepancies: [`post-game-contract-vs-oracle-discrepancies.md`](docs/coordination/post-game-contract-vs-oracle-discrepancies.md).

- **Ladder repair (Nov 2026):** 154 live games unprocessed; in-order processing is ready in `ops/` but **not uploaded**; live needs one full recalculation next to its database. **Message sent to Steve 2026-11-22, waiting. Until Dagh says so, leave `ops/` and `includes/league_standings.php` out of every WinSCP upload** — [`online-unprocessed-games-2026-11.md`](docs/coordination/online-unprocessed-games-2026-11.md).

- **Milestones:** Catalog **112**; v0 UI + **`kooldb1` simul proof** done. Live writer = **PHP ops** (live since cutover 2026-07-18).

- **Cutover:** Schema + PHP ops + **`kooldb1` simul** + **live execution Done 2026-07-18** — [`cutover-readiness.md`](docs/coordination/cutover-readiness.md) layers A+B+C.

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

- **Amiga realm (Jul 2026):** **Games hub shipped** — `/amiga/games/{recent,highlights,all}.php`. **Tournament structure — non-WC materialize tail complete** on `ko2amiga_work`: **583/606** catalog ids have stages; remaining **23** = `wc_deferred` World Cups only; disposition register **0** `pending_review`; parser queue **empty** (slice **6a**). Forward: **WC structure track** + display imprint P2–P3 — [`amiga-tournament-structure-review-queue.md`](docs/amiga-tournament-structure-review-queue.md) · [`amiga-tournament-structure-display-policy.md`](docs/amiga-tournament-structure-display-policy.md).

- **Amiga rating history (Jun 2026):** **V1** — History hub + time-travel rating LB; **News tab (Jul 2026)** — roll + pulse invites (no “coming soon”); [`amiga-rating-history-policy.md`](docs/amiga-rating-history-policy.md) · [`present-layer-ia.md`](docs/present-layer-ia.md) §5.5.

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

- **Amiga time travel (Jun 2026):** **Phase 1 complete** — header **Present day | Time travel** + one-row ribbon above hub when active; LB (8 wings), HoF at cutoff; **Profile tab full lens shipped Jul 2026-14**. Smoke: `scripts/oneoff/amiga_time_travel_smoke.php`. [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md).

- **Amiga time travel (Jun 2026):** **T13–T19** — snapshot-only TT hub; **with-player track complete** — **`as_with=`** on TT ribbon **Year/Month/Event** (+ preamble snap), **`id_with=`** + **`id_country=`** (tournament chevrons + page-entry snap), **`start_with=`** (league periods + bootstrap snap). [`with-player-stepper-policy.md`](docs/with-player-stepper-policy.md) §10 module map.
- **Amiga Opponents wing (Jun 2026):** **W/D/L · Goals · DDs + H2H (slices D+F) shipped** — poster/pickers/pair detail/moments/charts on `amiga/player/opponents/h2h.php`; Amiga `realm=` API branches + event-step rating compare. Policy [`amiga-opponents-wing-policy.md`](docs/amiga-opponents-wing-policy.md). **Country grain (Jun 2026):** **OCG-1–OCG-7 complete** — roll-up + read-time country TPR; country **W/D/L · Goals · DDs** tables; country **H2H** (poster/pickers/detail/moments/game charts, no rating/rank compare); API `opp_country` + chart JS grain — [`amiga-opponents-country-grain-policy.md`](docs/amiga-opponents-country-grain-policy.md) · [`amiga-opponents-country-grain-implementation-plan.md`](docs/amiga-opponents-country-grain-implementation-plan.md).

- **Amiga World Cups LB (Jun 2026):** **V2 UI** — five sub-wings on **World Cups hub → Player stats** only; LB wing **retired** Jun 2026 (legacy URLs 302). Writers proven Jun 2026-23. **Opponents wing inventory links complete (Jul 2026)** — Games + nine `wc_*` chronology destinations (cols 3–13); policy [`amiga-player-chronologies-policy.md`](docs/amiga-player-chronologies-policy.md) §4.0. [`amiga-world-cups-leaderboard-policy.md`](docs/amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cups-player-slice-v2-policy.md`](docs/amiga-world-cups-player-slice-v2-policy.md).

- **Amiga WC HoF (Jun 2026):** **Complete (WCH-1…8, SCH-046)** — 28 WC record rows; sparse `amiga_wc_hof_{snapshots,present}` + HoF UI block + time travel; Python + PHP finalize parity; `prove` green. [`amiga-wc-hof-policy.md`](docs/amiga-wc-hof-policy.md) · [`amiga-wc-hof-implementation-plan.md`](docs/amiga-wc-hof-implementation-plan.md).

- **Amiga community stats (Jun 2026):** **V2 writers shipped** — registry v2, `036`/`037`, `prove` green. **Amiga Activity hub v1 shippable (Jul 2026)** — **49 panels / 50 ship IDs** on `/amiga/activity/` six wings; wing copy locked — [`amiga-activity-charts-policy.md`](docs/amiga-activity-charts-policy.md) §5.0 · plan [`amiga-activity-charts-implementation-plan.md`](docs/amiga-activity-charts-implementation-plan.md) **complete** (slices 0–10 + copy pass). Per-WC table on World Cups hub wing 2 **shipped**. No open v1 chart backlog.

- **Amiga World Cups hub (Jun 2026):** **Wings 1–4 shipped** — **events catalog** (sortable table, podium flag+name cols) + tournament stats (five sub-wings) + **player stats** + **country stats**. [`amiga-world-cups-hub-policy.md`](docs/amiga-world-cups-hub-policy.md).

- **Amiga derived writes (Jun 2026):** **Locked** — batch `*-rebuild` CLIs removed; corrections = **`prove` only**; verify = read-only oracles. [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md).

- **Amiga Countries hub (Jun 2026):** **Shipped** — hub tab + index (player count sort) + country entity **Roster · Rivals** (CRV-1–7: `country/rivals/{h2h,wdl,goals,dds}`); flag links site-wide; cross-links WC country stats. [`amiga-countries-hub-policy.md`](docs/amiga-countries-hub-policy.md) · [`amiga-country-rivals-policy.md`](docs/amiga-country-rivals-policy.md).

- **Amiga perfect event (Jun 2026):** **Shipped** — SCH-045; honours LB + WC **Perfect** column; catalog filter; HoF **Most perfect events**. [`amiga-perfect-event-policy.md`](docs/amiga-perfect-event-policy.md).
- **Amiga perf. rating LB (Jun 2026):** **Shipped** — folder `performance-rating/{best,top,perfect}.php` + segment nav; W-D-L columns; Top 100 fixed set; Perfect shows **∞**. [`amiga-performance-rating-leaderboard-policy.md`](docs/amiga-performance-rating-leaderboard-policy.md).

- **Amiga tournament videos (Jun 2026):** **TV-3 + TV-4 shipped** — manifest **~300** videos; unified embed UI; **C06** dedicated Videos column; **With videos** filter; **player profile Videos wing**. **Jul 2026:** **TV-2b DB anchor sync** — `sync_db_ids` + `verify-tournament-videos` in `prove` ([`amiga-tournament-videos-policy.md`](docs/amiga-tournament-videos-policy.md) §12).

- **Amiga live ops (Jul 2026):** **Organizer vertical v1 complete on staging** — L5 backup/delete/repair + Case C insert + chrono integer + staged insert/delete parity + **AD8 backdate guard** (staged smoke PASS Jul 24). Standing doc [`amiga-organizer-track-status.md`](docs/amiga-organizer-track-status.md). L6 shelved.

- **Amiga format scoring contract (Jul 2026):** **SC-0–SC-9 + SC-11 shipped** — L4b relational contracts, PHP↔Python executor parity, RTB broadcast, L5 `stage_id`, structured ET/pens on games + fixtures (`012_match_extensions.sql`). **SC-10:** non-WC games **100%** `fixture_id` on work (Jul 2026); remaining unlinked games = **unmaterialized WCs** (`wc_deferred`). Policy [`amiga-format-scoring-contract-policy.md`](docs/amiga-format-scoring-contract-policy.md) · plan [`amiga-format-scoring-contract-implementation-plan.md`](docs/amiga-format-scoring-contract-implementation-plan.md).

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
| Amiga live ops (staging authority, repair, media) | [`amiga-live-ops-platform.md`](docs/amiga-live-ops-platform.md) · drill [`amiga-live-ops-practice-track.md`](docs/amiga-live-ops-practice-track.md) · **organizer track status** [`amiga-organizer-track-status.md`](docs/amiga-organizer-track-status.md) · finish confirm [`amiga-organizer-finish-confirm-policy.md`](docs/amiga-organizer-finish-confirm-policy.md) · **workspace UX** [`amiga-organizer-workspace-simplification-policy.md`](docs/amiga-organizer-workspace-simplification-policy.md) |
| DB copies (local + staging names) | `docs/coordination/database-copies-2026-06.md` |
| Work DB prepare / simul | `docs/work-db-prepare.md` |
| Ground vs derived columns | `docs/replay-v1-scope-and-reset.md`, `docs/ground-truth-manifest.md` (online) · **`docs/amiga-data-contract.md`** (Amiga) |
| Session log archives (older Recent log rows) | [`docs/archive/session-log-2026-q2.md`](docs/archive/session-log-2026-q2.md) · [`docs/archive/session-log-2026-jun-prune.md`](docs/archive/session-log-2026-jun-prune.md) · [`docs/archive/session-log-2026-jul-prune.md`](docs/archive/session-log-2026-jul-prune.md) |

---

## Next (prioritised intent)

**Dagh**

1. **Amiga profile** — optional polish on v0 feast — [`amiga-profile-v0.md`](docs/amiga-profile-v0.md). (Online profile feast **complete** — [`player-profile-feast.md`](docs/player-profile-feast.md).)

2. **Amiga Tournaments hub — tournament stats wing (C14)** — metadata leaderboards (most debuts, largest field, …) beside chronological catalog; WC hub pattern. **Approved** — [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) §6.4; pairs **C08** editorial on `tournament.php`; likely extend `amiga_tournament_catalog_stats` at finalize.

3. **World Cup XXIV (Oslo) videos.** **Day streams done 2026-11-22** (61 games linked by timestamp). 61 of 283 games have video, including the full final, semis, Silver final, Game of Shame and 3rd place. The six unmatched stream lines are friendlies (Dagh confirmed 2026-11-22; he checked Sandro-Alessandro). Still to do: per-match KO2CV uploads if Steve posts them, which could cover the missing quarter-finals and placings ([`amiga-tournament-videos-policy.md`](docs/amiga-tournament-videos-policy.md)).

**Steve (ongoing)**

4. **Live PHP ops** — ground insert + invoke dispatch (running since **2026-07-18**) — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md) · [`steve-live-ops.md`](site/public_html/ops/docs/steve-live-ops.md). Future schema packets still use migrate / seed / zero / simul / verify before live.

**Migration habit (not a numbered task):** stored-truth changes → [`UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) Part B + [`prod-coordination.md`](docs/prod-coordination.md) registers.

---

## Recent log

| Date | Note |
|------|------|
| 2026-11-22 | **Fix: Amiga listbox filters dropped time travel.** Player Tournaments, Games and Videos and Games hub All games had `as=` only in the form `action`, which a GET submit discards. New `amiga_form_context_inputs()` in `amiga_snapshot_url.php` writes hidden `as` + `as_with`; the Tournaments list and tournament Games forms now use it too (they lost `as_with`). Rule added to [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md) §3.4. 72 pages compared before and after: no other HTML difference. Uploaded and checked on live the same day (seven files). |
| 2026-11-22 | **Fix: Amiga player Tournaments filters added `finish=3`.** `amiga_player_hero.php` runs at page scope and its World Cup medal loop (added 2026-07-16) overwrote the page's `$winnerFilter` / `$finishFilter` before the filter bar was drawn, for every player with a WC medal. The links got `finish=2|3` or `winner=with-win`, and the Wins pill lost its state. Hero variables now all carry a `$hero` prefix. A static scan of `public_html` finds no other include that overwrites a page variable with a different value. Uploaded and checked on live the same day. |
| 2026-11-22 | **Online: 154 games never processed on live** (150 in a row Oct 30 – Nov 3, 4 singles). Counts and ratings otherwise audit clean.<br>• Scripts fixed and tested, **not uploaded**: waiting games first and in order, one writer at a time, late games refused (exit 3), `CMD=ProcessPending` for a one-minute job, day close 15,000 → 37 round trips, recalculation no longer closes the running day. Test `scripts/test_ops_game_queue.php`.<br>• Live database is **not on the web server** (19 ms per statement, very likely joshua), so a recalculation must run next to it.<br>• Message sent to Steve (WhatsApp), waiting for the answer: [`online-unprocessed-games-2026-11.md`](docs/coordination/online-unprocessed-games-2026-11.md) §5. |
| 2026-11-22 | **Push gate fix (SYNC-1).** The first version let a compare-only side-pull reset the push baseline, so a push after one could have overwritten staged-only data. Now side-pulls write `staging-sidepull-last.json`; only a full pull into `ko2amiga_work` or a push that verified MATCH sets `staging-sync-last.json`, and a missing, foreign or side-pull baseline aborts. Tested: 15 offline cases; a real side-pull leaves the baseline unchanged; the reproduced scenario passes the old gate and aborts on the new one; live dry run OK. The baseline update after a real push is tested offline only (no live push was run). Not covered: edits on staged during the ~2 min push itself. Also: Steve Camber's name corrected in the Oslo stream sources; checkpoint pointers in AGENTS.md and the staging handoff now name `work-2026-11-22-oslo-wc`. |
| 2026-11-22 | **Oslo WC day streams linked to games.** KO2CV_TV `SW3Ry0t53FI` (Day 1) and `H7YSe23l708` (Day 2) are `stream_map` rows. **61 games** (27 + 34) are linked from Steve C's YouTube description timestamps.<br>• Descriptions saved in `data/amiga/forward/…oslo/`; matcher `scripts/oneoff/amiga_oslo_wc_stream_links.py`. It filters by day (Day 1 = Round 1), takes the stage from Steve's labels, and proves each link unique with the verify resolver; Dagh 12-0 John H uses `leg` 2 for the second meeting.<br>• 10 lines skipped: 4 non-games and 6 pairs with no official game, probably friendlies.<br>• Align, verify and promote are green (303 videos, 146 verified). Uploaded; live game pages open the stream at the right second.<br>• Older KO2CV day streams (Milan 2025, Nottingham 2024) have no description timestamps. |
| 2026-11-22 | **Video fix: WC 2019 Bremen semi Thor S v Gianni T.** The two leg videos were linked crosswise because the YouTube titles name the players in reverse. Now `I8YSVVTWK44` → game **25455** (Thor 7-7 Gianni, leg 1) and `4YchXF8E5VU` → **25456** (Gianni 4-6 Thor, leg 2), both verified. Changed in the shared `review.csv` and the `apply_review.py` patches; align, verify and promote are green; deploy JSON uploaded and matches live. **Workflow fix:** `review.csv` is now in `modern/video_catalog.py` always-refresh. Before, git edits never reached the work copy after the first seed and align failed with `id_mismatch`; the one-off workaround was `seed-video-work --force`. Verified count 142 → 144. |
| 2026-11-22 | **One-command Amiga push (PUSH-1 + SYNC-1)**: `scripts/push_ko2amiga_to_staging.ps1` + `scripts/lib/Push-Ko2AmigaStaging.ps1`. It runs export → gate (fresh staged dump fingerprint must equal the last pull, else abort) → WinSCP upload → server preview check → POST Apply import with the admin password from the local git-ignored file → side-pull MATCH. Gate tested both ways (unchanged passes; a one-character row change is caught). Real identical-data push to live: 3 min, 42/42 parts, **MATCH**. Runbooks updated (handoff, authority §9–10, AGENTS, kool-workspace, CLAUDE.md). |
| 2026-11-22 | **World Cup XXIV (Oslo, 14–15 Nov) entered and live on staged.**<br>• Tournament **608**, chrono 608, 283 games, 26 placings; newcomers Steve Cl and Jon L (England), Terje S and Martin H (Norway).<br>• Source: KOA t=17982, hash-verified, in `data/amiga/forward/…`. Appended with `scripts/oneoff/amiga_append_world_cup_2026_oslo.py`, then simul plus a 29-step verify. 24/26 ratings match Dagh's forum table; the Game of Shame (rated, as always) explains Jørn F and Jan G.<br>• Checkpoint `work-2026-11-22-oslo-wc`, pushed via WinSCP + Apply import; work ≡ staged **MATCH**.<br>• Fixes: `structure/008_tournament_lifecycle.sql` no longer resets fixture-less forward tournaments to `running` on simul (608 was hidden until re-import); `verify-event-snapshots` skips the four inverse counts (a 153-player false positive before this event); live `/amiga/activity.php` was a stray online page, now the git 302. News pulse: Upcoming → **Latest tournament**. |
| 2026-11-22 | **Claude setup follow-through.**<br>• `.mdc` `@`-imports don't expand in Claude, so a SessionStart hook in `.claude/settings.json` now prints `kool-workspace.mdc` into context. Verified in a fresh session.<br>• 20 `scripts/**/*.ps1` files got a UTF-8 BOM. PS 5.1 read them as cp1252, and a `—` inside a double-quoted string broke `check_local_dev.ps1` and `reset_local_work_db.ps1`. All 37 now parse and `check_local_dev` passes.<br>• Recent log pruned: 641 Jul 1–19 rows moved to [`archive/session-log-2026-jul-prune.md`](docs/archive/session-log-2026-jul-prune.md).<br>• Checkpoint pointers in AGENTS.md and `amiga-staging-handoff.md` now name `work-2026-07-23-chrono-integer`; `inverse-roundtrip` was never committed.<br>• Staged Amiga side-pull into `ko2amiga_staging_cmp`, then `amiga_compare_two_dbs.php` work vs staged: **MATCH** (tip #607, 27474 games, inverse 3423). Nothing new on staged since July, so no pull of work was needed. |
| 2026-11-22 | **Moved from Cursor to Claude Code.** `CLAUDE.md` imports `AGENTS.md` and the `kool-workspace` rule. `.claude/settings.json` adds an allowlist (git, gh read-only, Laragon php/mysql, repo Python and PS1 scripts), denies force-push and `reset --hard`, and sets `PYTHONIOENCODING=utf-8` (the Amiga CLI crashed on cp1252). `.claude/rules/k2-table-php.md` mirrors the Cursor glob rule. Verified: Claude's Write and Edit keep UTF-8 and CRLF, so the UTF-16 rule is Cursor-only. The repo had no commits from 2026-07-24 to 2026-11-22, so pull Amiga from staged before the next Amiga work. |
| 2026-07-24 | **Create min 4 players** — kitchen create refuses &lt;4; Create button disabled until roster has 4. |
| 2026-07-24 | **AD8 staged smoke PASS** — deep-backdate create requires admin password on staging; admin session does not bypass. [`amiga-organizer-backdate-guard-policy.md`](docs/amiga-organizer-backdate-guard-policy.md). |
| 2026-07-23 | **AD8 backdate guard shipped** — create date >1 calendar month (UTC) requires admin password on fixtures create; client panel + server gate. [`amiga-organizer-backdate-guard-policy.md`](docs/amiga-organizer-backdate-guard-policy.md). |
| 2026-07-23 | **Organizer track status doc** — vertical v1 inventory; AD8 backdate guard locked; Phase D staged insert/delete parity PASS; live-ops platform §3.2 refreshed. [`amiga-organizer-track-status.md`](docs/amiga-organizer-track-status.md). |
| 2026-07-23 | **Chrono integer repair** — dense ints `1…607` on work; `matchup_at_event.event_chrono` → `double`; simul replay; seals `work-2026-07-23-pre-chrono-integer` + `work-2026-07-23-chrono-integer`; Case C insert `+1` / delete `-1` ground bump. Policy [`amiga-chrono-integer-policy.md`](docs/amiga-chrono-integer-policy.md). |
| 2026-07-23 | **Catalog chrono fix** — superseded by integer bump (CI11); legacy `repair-insert-catalog-chrono` CLI retained. |
| 2026-07-23 | **Case C insert design locked** — mid-history Finish policy + implementation plan; **shipped + staged proof PASS**. |
| 2026-07-23 | **Session wrap (L5 + inverse)** — L5 v1 **Implemented**; export JSON data parts; Case C inverse seed; round-trip A/B/C PASS; staged Case C retest PASS; triple agreement GitHub `work-2026-07-23-inverse-roundtrip` ≡ work ≡ staged (#16, inverse 3423). Docs: backup policy, L5 plan, live-ops, practice track, inverse policy, handoff, feature-log. Build **`l5-case-c-inv-seed-2026-07-23`**. |
| 2026-07-23 | **Triple agreement PASS** — seal ≡ work ≡ staged (side-pull cmp): tip #607, #16, inverse **3423/3423**, full P-1 **PASS**. |
| 2026-07-23 | **Case C staged retest PASS** — seed fix → restore pre-#16 → Case C #16 → seal `…-120630Z-case_c_delete` → cmp vs work: present_mm **0**, inverse **3406≡3406**. |
| 2026-07-23 | **Case C inverse seed** — PHP finalize seeds four inverse counts from changelog (not snapshot cols). First Phase C FAIL → fix → re-proof. |
| 2026-07-23 | **Export/seal = JSON list** — PS1 + L5 seals iterate `staging_export_tables.json` (fixes Jul 18 inverse data omission). Round-trip plan A/B PASS. |
| 2026-07-23 | **PHP player WC slice = Python** — participate checkpoints every finalize (`tournaments_played > 0`); country/V2 stay WC-only. |
| 2026-07-22 | **L5 slice 5 Case C** — truncate > N + delete M + project N + refinalize + seal; local smoke PASS. Build **`l5-s5`**. |
| 2026-07-22 | **L5 slice 4 hardened + restore UX** — After botched Case B, present repair: inverse = **pointer recount** (Jul 15 ghosts; not snap cols / empty changelog); matchups = **JOIN pairs** + txn (not MariaDB EXISTS); HTTP phases `player_current`→`matchups`→`rest`; **Diagnose** counts/timing (MariaDB `max_statement_time`). Staging tip #607 **PERFECT MATCH** vs GitHub `work-2026-07-18-forum` (restore seal + side-pull `ko2amiga_staging_cmp`). Local `ko2amiga_work` rebuilt from same seal. **BA4:** **Restore into DB now** from `_backups/` (no `_import` clobber); Copy→`_import` secondary. Side-pull: `pull_ko2amiga_from_staging.ps1 -TargetDatabase …`. Build **`l5-s4j`**. Next: slice 5 Case C. |
| 2026-07-22 | **OW follow-up** — Setup removed from in-tournament tabs (Players · Play · Table); create/Recent via “Create new league” landing only. |
| 2026-07-22 | **OW slice 6 (track done)** — policy **Implemented**; practice-track §3 CREATE/OPEN/PLAY; RTB organizer vocabulary aligned; OW12 stage builder deferred. Happy path: Create → Play → Finish (+ optional Hide). |
| 2026-07-22 | **OW slice 5** — Advanced demoted to muted Technical / repair tools; withdraw/replace UI + browser POST abandoned (CLI kept); Setup slim Open meta (Hide stays header). Next: slice 6 docs/RTB vocabulary. |
| 2026-07-22 | **L5 Case A Open → organizer Play** — `/amiga/ops/fixtures.php?once=…&view=play&tournament_id=` (not catalog). Build `l5-s4c`. |
| 2026-07-22 | **OW slice 4** — Fixtures+Results merged into stage-scoped **Play** (`view=play` + `stage_id`); create/Open land on Play; legacy fixtures/results URLs remap. Next: slice 5 Advanced demote + strip withdraw/replace. |
| 2026-07-22 | **OW slice 3** — create as `running`+`started_at`; Start UI retired; draft/ready auto-heal to Open; Results landing after create. Live still = running + live_visible. Next: slice 4 play surface merge. |
| 2026-07-22 | **OW slice 2** — Hide/Show Live via `format_overrides.live_visible` (default on); Live hub filters hidden; Abandon/void retired from browser; Finish unaffected. Next: slice 3 No Start. |
| 2026-07-22 | **OW slice 1** — Recent tournaments rename + Open-only filter (`rating_finalized=0`, lifecycle draft|registration|ready|running; void/Official excluded). Next: slice 2 Hide/Show Live. |
| 2026-07-22 | **OW slice 0 inventory** — plan §5 filled (gates, Live=`running`, Recent leagues SQL, withdraw/replace, tab map). Next: slice 1 Open-only Recent tournaments. No PHP/UI changes. |
| 2026-07-22 | **Organizer workspace simplification locked** — OW1–OW14 policy + thin plan (Open/Hide; no Start/void; stage-scoped play merge; drop withdraw/replace). Code not started. |
| 2026-07-22 | **L5 slice 3b** — dropped Case A auto-seal (policy: tip-changing only). Case A UI needs WinSCP of `run_backup_ko2amiga.php` + `delete_unfinalized_tournament.php` (look for Build `l5-s3b-…`). Next: slice 4 Case B. |
| 2026-07-22 | **L5 slice 3 done** — Case A `delete-unfinalized-tournament` (admin backup page + ops module/CLI); refuse finalized tip; (auto-seal later dropped — 3b); local smoke PASS. Next: slice 4 Case B. |
| 2026-07-22 | **L5 slices 0–2 shipped + pushed** (`2a3b39f1`); staging Backup now **reserve** seal; work↔staging **parity OK** (Nottingham III #607, 27474 games, checksums); local smoke/compare dumps cleaned. Next: slice 3 Case A (starter prompt ready). |
| 2026-07-22 | **L5 slice 2 done** — Restore stages seal into `_import/` then Apply import (full replace); smoke PASS (Country probe wiped). Next: slice 3 Case A. |
| 2026-07-22 | **L5 slice 1 done** — `amiga_backup_seal_lib.php` + Finish seal + `/amiga/run_backup_ko2amiga.php`; rolling/reserve BA5–BA6; local smoke 42 parts / reserve PHP-delete refused. Next: slice 2 restore. |
| 2026-07-22 | **L5 slice 0 done** — inventory §5 filled (Finish path, pack reuse, 14 derived tables, present re-project, admin UI = import sibling); next slice 1. |
| 2026-07-21 | **Creative ledger status audit** — [`creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md): C03 Activity + C04 News v1 → shipped; C02 → sticky v1 successor; birds-eye / open stack / ledger buckets refreshed. Open firm to-dos unchanged (C14 + C08, C01, C16…). |
| 2026-07-22 | **L5 handover** — plan + starter (backup seals, Case A/B, narrow Case C); practice track §4 L5 ready; AD4 updated. |
| 2026-07-22 | **Doc sweep** — live-ops §6.2/§7/§9/ALO*, staging-authority §6–7/ADMIN-1, RTB, modern-ground, handoff aligned to backup/admin-delete policy; L6 shelved consistently. |
| 2026-07-22 | **Backup + admin delete intent locked** — organizer vs admin; backup-after Finish/delete; admin-only tip delete; L6/demotion out — [`amiga-staging-backup-admin-delete-policy.md`](docs/amiga-staging-backup-admin-delete-policy.md). |
| 2026-07-21 | **L6 shelved** — ground pack not planned until further notice; full staging backup pack preferred. |
| 2026-07-21 | **Track L → L2** — finish-confirm Phase A boring; practice track gate = Ref-League-A ×3 reps; §4 idle. |
| 2026-07-21 | **Organizer finish confirm — Phase A complete (slice 4)** — FO9 demoted to prefill-only residual; policy Implemented; practice track L1 idle for next feedback. Optional slice 5 = Phase B finish-mode sketch. |
| 2026-07-21 | **Organizer finish confirm — slice 3** — Make official gated until finishing order confirmed (server `finish_order_not_confirmed` before void/promote; Finish button hidden until confirm). WinSCP `fixtures.php` + `amiga_finish_confirm_proposal.php`. |
| 2026-07-21 | **Organizer finish confirm — FO2 ties** — Confirm allows shared places; only 1..N (N = registered entrants). Olympic deferred. Sync `amiga_finish_override_write.php`. |
| 2026-07-21 | **Organizer finish confirm — slice 2** — Table “Who finished where?” panel + `confirm_finish_order` → Tier E; prefill from derive/Tier E/table; Finish still ungated (slice 3). WinSCP `fixtures.php` + `amiga-tournament.css` + `amiga_finish_confirm_*.php` / write helper when testing staging. |
| 2026-07-20 | **Root share card** — `status.php` OG title/description = site-wide default (root `/` redirects here); WhatsApp refresh via Facebook Sharing Debugger. |
| 2026-07-20 | **Victims mosaic BL/BW** — written out as Biggest loss victims / Biggest win culprits; tooltip Loss/Win → lowercase (profile + Victims LB). |
| 2026-07-20 | **Victims mosaic casing** — mid-line `victims`/`culprits` lowercase (DD/CS/MGC/BL/MGS/BW); standalone Victims·Culprits stay title case; tooltips already matched. |
| 2026-07-20 | **Amiga profile mosaic labels** — Goals / DDs & CSs / Victims DD·CS / Tournament honours / Calendar & geo; peak year+count + Peak rating Peak/rank/Nadir dates **inline** (muted date, wide gap); `amiga_profile_lb_slices.php` + `player-feast-sections.css`. |

*Older rows: [docs/archive/session-log-2026-jul-prune.md](docs/archive/session-log-2026-jul-prune.md) (Jul 1–19) · [docs/archive/session-log-2026-jun-prune.md](docs/archive/session-log-2026-jun-prune.md) (before Jul 1).*


---

## Deferred / blocked

- GitHub branch protection — when collaborators land.
- **Extensionless URLs** (`.htaccess` rewrites) — optional; filenames and folders done Jun 2026.
- **Status on prod live DB** + joshua redirect — [`STATUS_PAGE_DATA.md`](docs/STATUS_PAGE_DATA.md).
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
