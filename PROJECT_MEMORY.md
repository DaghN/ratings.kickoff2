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

- **Amiga community stats (Jun 2026):** **V2 writers shipped** — registry v2, `036`/`037`, `prove` green. **Amiga Activity hub v1 shippable (Jul 2026)** — **49 panels / 50 ship IDs** on `/amiga/activity/` six wings; wing copy locked — [`amiga-activity-charts-policy.md`](docs/amiga-activity-charts-policy.md) §5.0 · plan [`amiga-activity-charts-implementation-plan.md`](docs/amiga-activity-charts-implementation-plan.md) **complete** (slices 0–10 + copy pass). Per-WC table on World Cups hub wing 2 **shipped**. No open v1 chart backlog.

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

*Older rows (before 2026-07-01): [docs/archive/session-log-2026-jun-prune.md](docs/archive/session-log-2026-jun-prune.md).*

| When | Note |
|------|------|
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
| 2026-07-04 | **TT baseline F6 iter 3b reverted** — PHP flush failed smokes (TT blank unchanged, present LB slower feel); next: iter 3d reveal gate or query speed. |
| 2026-07-04 | **TT baseline F6 iter 3a result** — non-TT good; TT y=0 streaming gap (Type B); Countries y>0 whole-page blank (Type A) — analysis in attempt log. |
| 2026-07-04 | **TT perf probe** — month catalog 289× SQL (~283 ms); Countries index pays roster elo attach (~358 ms wasted) — `scripts/oneoff/amiga_tt_perf_probe.php`. |
| 2026-07-04 | **TT perf fixes** — month/year catalog from tournament list (~2 ms); Countries index SQL GROUP BY (~137 ms TT, parity OK) — Dagh sign-off: Countries snappy, month wing normal — `amiga_rating_history_lib.php`, `amiga_countries_lib.php`, `countries.php`. |
| 2026-07-04 | **F20 + roster audit** — country roster TT overfetch (~845 ms all players for 36 Greece); hash `#k2-country-roster` cloak — probe `amiga_country_roster_audit_probe.php`. |
| 2026-07-04 | **Country roster fetch slice** — `query_roster_rows` country-filtered + scoped elo IN (~208 ms late TT); parity OK — Dagh sign-off roster snappy; F20 chrome audit deferred — handoff [`2026-07-04-002-f20-country-rivals-h2h-audit.md`](docs/orchestration/agent-handoffs/2026-07-04-002-f20-country-rivals-h2h-audit.md). |
| 2026-07-04 | **TT baseline slice 0 iter 2** — carry-scroll narrow cloak-top, no early reveal at scroll top, picker anchor; F19 logged — handoff updated. |
| 2026-07-04 | **TT baseline failures logged** — **F6** (sub-ribbon blank at scroll top, ribbon stable, old→blank→new) + **F18** (Countries/WC hub tab TT late-cutoff whole-page blank; Present OK) — smokes S1/S1b/S9–S10 — [`amiga-tt-chrome-sticky-invariants.md`](docs/amiga-tt-chrome-sticky-invariants.md). |
| 2026-07-04 | **C02 TT ribbon pin removed** — surgical revert of `3567037` pin slice; in-flow baseline only — `amiga_snapshot_chrome.php`, `theme.css`; deleted `k2-amiga-time-travel-pin.js`. |
| 2026-07-04 | **TT chrome policy §7 trimmed** — cause/architecture tables removed; symptoms → failures register only. |
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
