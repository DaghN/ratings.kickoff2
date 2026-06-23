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

- **Amiga realm (Jun 2026):** **Disposition review** — register **605/605**; **44** `pending_review`; [`disposition-REVIEW-STARTER`](docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

- **Amiga rating history (Jun 2026):** **V1 + animation** — History hub + News races (by tournament + by time); [`amiga-rating-history-policy.md`](docs/amiga-rating-history-policy.md).

- **Amiga event snapshots (Jun 2026):** **Complete (slices 0–9)** — `amiga_player_event_snapshots` + `amiga_player_current`; legacy four tables retired; holy loop `python -m scripts.amiga prove` green. Policy [`amiga-event-snapshot-policy.md`](docs/amiga-event-snapshot-policy.md).

- **Amiga matchup at event (Jun 2026):** **Complete (slices 0–6)** — `amiga_player_matchup_at_event` + finalize-only network/peaks/H2H; replay tail batches removed. Policy [`amiga-matchup-at-event-policy.md`](docs/amiga-matchup-at-event-policy.md). HoF → [`amiga-realm-snapshot-policy.md`](docs/amiga-realm-snapshot-policy.md).

- **Amiga realm snapshots (Jun 2026):** **Complete (slices 0–8)** — incremental finalize + `amiga_realm_snapshots` timeline; HoF from `generalstats`; `prove` green ~5 min. Policy [`amiga-realm-snapshot-policy.md`](docs/amiga-realm-snapshot-policy.md).

- **Amiga HoF calendar-year + geography (Jun 2026):** **Complete** — eight new HoF rows + Calendar & geo LB wing; SCH-028 on snapshots/current + `generalstats`; `verify-hof-geo-year` in `prove`. Policy [`amiga-hof-tournament-geo-policy.md`](docs/amiga-hof-tournament-geo-policy.md).

- **Amiga HoF record rise dates (Jun 2026):** **Complete (SCH-029, slices 0–8)** — per-metric `*_last_rise_*` on snapshots/current; HoF `*Date` from rise not participation; Python + PHP finalize parity; `verify-hof-geo-year` date oracle. [`amiga-hof-record-date-policy.md`](docs/amiga-hof-record-date-policy.md).

- **Amiga career HoF rise dates (Jun 2026):** **Complete (SCH-030)** — ten legacy career rows (`MostGamesPlayed` … `BiggestRatingAscent`) get `*_last_rise_*` on snapshots/current; HoF `*Date` from event where scalar last rose; `verify-hof-geo-year` extended (32 rise cols + 18 HoF dates); `prove` green. Plan [`amiga-hof-career-rise-implementation-plan.md`](docs/amiga-hof-career-rise-implementation-plan.md).

- **Amiga stored id/date semantics Phase B (Jun 2026):** **Complete** — `verify_hof_holder_projection` in `prove` (career source-field dates, game-anchored + ratio oracles). Manifest [`amiga-stored-field-semantics.md`](docs/amiga-stored-field-semantics.md); plan [`amiga-stored-field-semantics-plan.md`](docs/amiga-stored-field-semantics-plan.md).

- **Amiga stored id/date semantics Phase C (Jun 2026):** **Complete** — `verify_stored_id_date_pairs` in `prove` (rise FK pairing, honours_last / last participation, career-best replay).

- **Amiga stored id/date semantics Phase D (Jun 2026):** **Retired with refinalize** — `verify-php-finalize-parity` removed Jun 2026 ([`archive/retired-amiga-refinalize-2026-06.md`](docs/archive/retired-amiga-refinalize-2026-06.md)); batch `*-rebuild` CLIs retired same era ([`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md)). Phases A–C (`verify-hof-holder-projection`, `verify-stored-id-date-pairs`, manifest) remain in `prove`.

- **Amiga ground layers L0–L5 (Jun 2026):** Slices **1–11 complete** — strict stack shipped (`prove` L1→L5, `verify-l2-l3`). [`amiga-ground-stack.md`](docs/amiga-ground-stack.md).

- **Amiga time travel (Jun 2026):** **Phase 1 complete** — header **Present day | Time travel** + one-row ribbon above hub when active; LB (8 wings), HoF at cutoff; profile present-only. Smoke: `scripts/oneoff/amiga_time_travel_smoke.php`. [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md).

- **Amiga time travel (Jun 2026):** **T13–T18** — snapshot-only TT hub; player-wing TT entry = first event (`T14b`); pre-debut hero **—** + note (`T17`); **player Event chevrons** + picker accents (`T18`). [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md).
- **Amiga Opponents wing (Jun 2026):** **W/D/L · Goals · DDs tables shipped** — `amiga_matchup_snapshot_lib.php` (present + at-event); time travel wired. H2H rivalry wing still placeholder. Policy [`amiga-opponents-wing-policy.md`](docs/amiga-opponents-wing-policy.md).

- **Amiga World Cups LB (Jun 2026):** **V1 shipped** — Honours · Results · Goals wing; WC extracted from tournament honours + calendar-geo; slice tables + TT. [`amiga-world-cups-leaderboard-policy.md`](docs/amiga-world-cups-leaderboard-policy.md).

- **Amiga community stats (Jun 2026):** **Shipped** — `amiga_community_stats` + snapshots + facts; Activity summary; aggregate cols **dropped** from realm/HoF tables (`035`); multi-event + PHP build parity in `prove`. [`amiga-community-stats-policy.md`](docs/amiga-community-stats-policy.md).

- **Amiga derived writes (Jun 2026):** **Locked** — batch `*-rebuild` CLIs removed; corrections = **`prove` only**; verify = read-only oracles. [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md).

- **Obsolete dev scripts retirement (Jun 2026):** **Policy + plan locked** — batch rebuild + `scripts.ladder run` era to retire; holy ops investigation confirms **online = PHP only**, **Amiga = `scripts.amiga prove`** (library imports from `scripts.ladder`, not `ladder run`). **Not executed yet.** [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md) · [`obsolete-dev-scripts-retirement-implementation-plan.md`](docs/obsolete-dev-scripts-retirement-implementation-plan.md).

---

## Deep reference (read on demand)

| Topic | Where |
|--------|--------|
| Live post-game (legacy prod only) | `docs/ratings_cpp.txt` — historical; cutover = PHP ops |
| Ladder ops / PHP post-game | `docs/ladder-ops-platform.md` §2 · `docs/post-game-php-development.md` |
| Per-game table | `docs/ratedresults-schema.md` |
| Replay / Elo sandbox (legacy) | `docs/replay-v1-scope-and-reset.md` · retirement track [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md) |
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

| When | Note |
|------|------|
| 2026-06 | **Obsolete dev scripts retirement** — policy + implementation plan; holy ops audit: online ops never exec Python; Amiga `prove` imports `scripts.ladder` library only (`player_state`, `apply_game_row`, `constants`, `config`). Per-file retirement gate mandatory before delete. |
| 2026-06 | **Post-game parity register sweep** — `post-game-contract-vs-oracle-discrepancies.md`: closed false Opens (`play_streak_100`, P7 verify); split `club_*` live Fixed vs batch Deferred; layer 7 superseded by `verify_activity_wing_parity`; DDR-052 + cutover checklist aligned. |
| 2026-06 | **Milestones docs drift fix** — `milestones-product-spec.md` + `milestones-project.md`: 112/112 keys shipped (removed stale wave-1 ~88 TODO); meta LB wing + hub v2 marked done; Accomplished **%** wing noted as deferred (counts ship today). |
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
| 2026-06 | **Amiga TT T14b/T17** — player-wing TT entry = first event snapshot; pre-debut cutoff loads with hero — + note (no 404); `amiga_player_publish_hero_context()`. |
| 2026-06 | **Amiga time travel picker** — Year/Month dropdown lists newest first (catalog order unchanged for stepper). |
| 2026-06 | **Amiga player hero at cutoff** — `amiga_player_snapshot_lib.php`; `amiga_player_load()` branches on `as=`; hero games link → player games tab. |
| 2026-06 | **Amiga time travel T13–T15** — editorial hub tabs hidden; News landing; `amiga_hub_nav_lib.php`. |
| 2026-06 | **Amiga honours doc drift** — PHP comments + honours-rules / surface-expansion overview aligned to `amiga_player_current` (retired `amiga_player_tournament_totals` read path). |
| 2026-06 | **Amiga Opponents tables** — `amiga_matchup_snapshot_lib.php` + W/D/L · Goals · DDs wings (stored + time travel); H2H placeholder. |
| 2026-06 | **Amiga Opponents IA shell** — player pill + `amiga/player/opponents/{h2h,wdl,goals,dds}.php`; placeholder wing bodies; routes in `k2_amiga_routes.php`. |
| 2026-06 | **Amiga matchup SCH-031** — goal extremes on `matchup_summary` + `matchup_at_event` (online SCH-019 parity); finalize Python+PHP; verify oracle; replay green. |
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
| 2026-06 | **Profile At a glance mobile** — dropped column stack on narrow viewports; three columns stay side-by-side with horizontal scroll when needed (`player-feast-glance.css`). |
| 2026-06 | **Profile career chart alignment (B+C)** — `profileCareerTimeRange()` (Jun 2017 month → month-end); rating by date axis only; `offset: false` on month bars. |
| 2026-06 | **Profile career chart gutters (slice A)** — shared 48px y-axis + 12px right padding via `chart-theme.js` (rating, games/month, goals). |
| 2026-06 | **Amiga time travel realm home** — wordmark + Amiga 500 toggle keep active `as=` and land on rating LB (not News) when in time travel. |
| 2026-06 | **Amiga TT T14c** — Present→Time travel from `tournament.php?id=N` uses `as=event:N` (catalog); player first event (T14b) and year default unchanged. |
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
| Local DB | `ko2unity_db` frozen · dump `data/dumps/` · derived fill = work simul (not `run_local_replay` — retiring) |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| Cutover index | **`docs/coordination/cutover-readiness.md`** |
| `ratedresults` indexes | SCH-001 in ops `migrate-work` |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
