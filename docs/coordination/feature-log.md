# Feature migration log

Lightweight index: **what we built** and **cutover status**. Agents update on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

**Read first:** [`cutover-readiness.md`](cutover-readiness.md) — **kooldb1 proof** ≠ **live prod executed**. “Not executed” on live is **scheduled go-live**, not repo work pending. **Do not** assign batch `REP-xxx` scripts for prod cutover.

| Feature | Level | Schema | Ops simul | `kooldb1` proof | Live cutover | Periodic | Notes |
|---------|-------|--------|-----------|-----------------|--------------|----------|-------|
| Ladder ops platform (`ops/`, PHP post-game) | — | — | — | **Done** Jun 2026 | **Not executed** | — | P0–P7 + `dispatch.php` + `dispatch_request.php` (HTTP); Steve `CMD=ProcessCompletedGame` |
| Local dual website (dev + work URLs) | — | — | — | — | — | — | **Jun 2026** — `ratingskickoff.test` → `ko2unity_db`, `work.ratingskickoff.test` → `ko2unity_work`; config router |
| Amiga player hero country flags | — | — | — | — | — | — | Country stat column (label + flag) on player profile/tournaments/games; `k2_amiga_country_flag.php` |
| 2026-06 | **Amiga ground layers L0–L5 (modular pipeline)** | — | L3 ground includes `finish_override`; export packs + modular `prove` | — | **Track complete** slices 1–8 Jun 2026 | — | — | [`amiga-ground-layers-policy.md`](../amiga-ground-layers-policy.md) |
| Amiga holy loop (`prove`) | L0 | `apply_schema` bundle `001–013`, `019`, `024`, `026`, `027` | — | **Done** local Jun 2026 | **Not executed** | — | Nuclear-only path: `python -m scripts.amiga prove`; incremental import gated — [`amiga-import-layer.md`](../amiga-import-layer.md) |
| Amiga offline realm (A1) | L0 | `scripts/amiga/sql/001_core.sql` | — | — | **Not executed** | — | **Staging live Jun 2026** — `ko2amiga_db`, rating/profile/games; config `site/config/` — [`amiga-staging-handoff.md`](../amiga-staging-handoff.md) |
| Amiga historical rating ladder | L0 | — | — | **Retired** hub tab Jun 2026 | **Not executed** (Amiga staging) | — | Ladder-at-cutoff via rating LB + time travel; legacy `/amiga/history.php` redirects — [`amiga-rating-history-policy.md`](../amiga-rating-history-policy.md) |
| Amiga time travel (phase 1) | L0 | — | — | **Done** local Jun 2026 | **Not executed** (Amiga staging) | — | Shared `as=` + ribbon; LB (8 wings) + HoF at cutoff; profile present-only — smoke [`amiga_time_travel_smoke.php`](../scripts/oneoff/amiga_time_travel_smoke.php) · [`amiga-time-travel-policy.md`](../amiga-time-travel-policy.md) |
| Amiga top-10 Elo line race (News) | L0 | — | — | — | **Not executed** (Amiga staging) | — | `/amiga/news.php` + `api/amiga_top10_rating_race.php` — dynamic top 10 chart race |
| Amiga single game page | L0 | — | — | — | — | — | `/amiga/game.php?id=` — neutral row + tournament/phase; list ID links |
| Amiga games tab filtered Perf. rating | L0 | — | — | — | — | — | Read-time async API on `/amiga/player/games.php` status line — [`amiga-performance-rating.md`](../amiga-performance-rating.md) |
| Amiga event finish + honours | L1 | `017`–`019` (`scripts/amiga/sql/`) | — | **Done** local | **Not executed** | — | Tier E overrides = **L3 witness** claims (feature-log L1 = migration level); [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) |
| Amiga standings scope unification | L1 | `020` (`scripts/amiga/sql/`) | — | **Done** local (slices 0–7) | **Not executed** | — | `league`\|`knockout` enum; `league_scopes`; `resolve_primary_league_standings`; replay + verify OK — [`amiga-standings-scope-policy.md`](../amiga-standings-scope-policy.md) |
| Amiga tournament medals unification v2 | L1 | `021`–`022` (`scripts/amiga/sql/`) | — | **Done** local Jun 2026 | **Not executed** | — | Unified finish + `event_*`/`wc_*` totals; honours LB — [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) v2 **Implemented** |
| Amiga tournament structure (stage types) | L1 | `023` (`scripts/amiga/sql/`) | — | **Done** local slice 1 | **Not executed** | — | `round_robin`\|`knockout` stage enum; fixture scope parity — [`amiga-tournament-structure-policy.md`](../amiga-tournament-structure-policy.md) |
| Amiga event snapshots (player truth) | L1 | `024` + `025` drop (`scripts/amiga/sql/`) | — | **Done** slices 0–9 local | **Not executed** | — | Present=`current`; history/event-local=snapshots; legacy four tables retired — [`amiga-event-snapshot-policy.md`](../amiga-event-snapshot-policy.md) |
| Amiga matchup at event + finalize network | L1 | `026` (`scripts/amiga/sql/`) | — | **Done** slices 0–6 local | **Not executed** | — | `amiga_player_matchup_at_event`; network/peaks/H2H at finalize; no replay tail batches — [`amiga-matchup-at-event-policy.md`](../amiga-matchup-at-event-policy.md) |
| Amiga realm snapshots (HoF + realm stats timeline) | L1 | `027` | — | **Complete** local Jun 2026 (`prove` green) | **Not executed** | — | Full row per finalize; incremental compute; export includes `amiga_realm_snapshots` — [`amiga-realm-snapshot-policy.md`](../amiga-realm-snapshot-policy.md) |
| Player display names (canonical) | L0 | — | — | — | — | — | UI resolves `playertable.Name` by ID everywhere; `ratedresults` snapshots audit-only; `k2_player_display_names.php`; rename report script |
| Profile graph restoration | L0 | — | — | — | — | — | `player/profile.php` profile visuals restored to Activity-style chart frames; server-origin time axes; peak dashed line; comparison date/games toggle; played-days year picker; top-opponents tall chart; **goals-per-game histogram** (+ career avg in hint); winrate-vs-Elo graph removed |
| Profile coarse tap + games/month drill-down | L0 | — | — | — | — | — | `k2-coarse-tap.js` — phone two-tap preview then navigate on heatmaps + bar charts; games/month bar → Games tab `profile-games-chart`; back link `#games-per-month` — [`player-profile-feast.md`](../player-profile-feast.md) |
| Player games GF/GA/GS filters | L0 | — | — | — | — | — | `player/games.php` — `gf`/`ga` listboxes + `gs` URL filter (total goals in game); chart click-through from H2H histogram |
| Opponents Goals TG/g column | L0 | — | — | — | — | — | `(GF+GA)/games` per opponent after Ratio on `/player/opponents/goals.php`; read-time from `player_matchup_summary` |
| Opponents H2H scoreline heatmap | L0 | — | — | — | — | — | Full GF×GA grid per pair; outcome tint + intensity; click → `games.php?gf=&ga=&opponent=` — [`player-opponents-h2h-poster.md`](../player-opponents-h2h-poster.md) |
| Daily active players chart | L2 | SCH-007 | Yes | **Done** | **Not executed** | — | `server_daily_activity`; post-game via PHP ops at live cutover |
| All-time busiest players chart (Activity) | L0 | — | — | — | — | — | Top 10 by `playertable.NumberGames` (tie → lowest ID); monthly series from `player_period_games`; was monthly top-10 eras |
| Activity Graph Roadmap | L0 | — | — | — | — | — | Read-time `ratedresults`/`playertable` |
| Activity recent milestones digest | — | — | — | — | — | — | **Removed Jun 2026** |
| Activity Double Digit Merchant charts | — | — | — | — | — | — | **Removed Jun 2026** |
| Activity recent daily chart | L0 | — | — | — | — | — | Read-time |
| Activity charts v2 (single module) | — | — | — | — | — | — | **Shipped** — [`activity-charts.md`](../activity-charts.md) |
| Persistent tint preference | — | — | — | — | — | — | Client-only |
| Six-hour tint schedule | — | — | — | — | — | — | `k2-tint-schedule.js` |
| Header realm switcher | — | — | — | — | — | — | **Re-shipped Jun 2026** — `realm_switcher.php` beside wordmark; Online ↔ `/status.php`, Amiga 500 ↔ `/amiga/rating.php` |
| Header cross-realm player search | — | — | — | — | — | — | `api/player_search.php?realm=all`; realm label per dropdown row; pick uses per-hit `data-player-realm` (fixed Jun 2026); H2H stays online-only |
| Records two-panel split | L0 | — | — | — | — | — | Peak cache read path |
| League honours leaderboard (v1) | L0 | — | — | **Proven** | **Not executed** | — | `leaderboards/league-honours.php`; **proven on `kooldb1`** after simul |
| League period awards (medals DB) | L4 | SCH-009, SCH-010 | Yes | **Proven** | **Not executed** | PER-003 at cutover | Activity + points same orphan eligibility (`LEFT JOIN`); re-simul on work after rule change |
| Status Leagues (Activity + Points) | L0 | — | — | **Proven** | **Not executed** | — | **Shipped**; spec [`status-period-competitions.md`](../status-period-competitions.md) |
| Status league stack | L4 | SCH-008 | Yes | **Proven** | **Not executed** | — | `player_period_league`; PHP ops post-game at live cutover |
| Player games server-side filters/sort | L0 | — | — | — | — | — | Read-time |
| Hall of Fame aggregate read path | L0 | — | — | — | — | — | Peak/period cache + participation (Nth-period ties) + milestones/league read-time (Jun 2026) |
| Hall of Fame context links | L0 | — | — | — | — | — | ranked wings + `k2_sort` (May 2026) |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | — | JS only |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | — | Sort + anchor column (May 2026) |
| Games tab 14-day buckets | L0 | — | — | — | — | — | **Recent** on `games/recent.php` |
| Games Highlights (spectacle boards) | L0 | — | — | — | — | — | `games/highlights.php` |
| Games All games browse | L0 | — | — | — | — | — | `games/all.php` — filters, server sort, chevron pager, Reset filters pill; hub tab Jun 2026 |
| Play & Setup page | L0 | — | — | — | — | — | `join.php` |
| Box art story page | — | — | — | — | — | — | `boxart.php` (+ `boxart_story_section.php`, `boxart-story.css`, `images/boxart/`); KO2 cover history; Status heritage box links to it; PHP/CSS/content only |
| Hub / Status cosmetics | — | — | — | — | — | — | PHP only |
| Milestones hub tab (stub) | L0 | — | — | — | — | — | `milestones.php` v0 hub |
| Hub IA — Games tab | — | — | — | — | — | — | **Jun 2026** — `games/recent.php` hub tab after Milestones; Status **Games →** retained |
| Hub IA — Games off top nav | — | — | — | — | — | — | Superseded Jun 2026 — Games promoted to hub tab |
| Status league / performance | L2 | SCH-005, SCH-017 | Yes | **Proven** | **Not executed** | — | Indexes + `player_period_league` |
| Period activity leaderboards | L2 | SCH-004, SCH-006 | Yes | **Proven** | **Not executed** | — | `player_period_games` / peaks |
| Profile hero milestones (no peak) | L0 | — | — | — | — | — | Garden hero |
| Milestones Phase 4 v0 UI | L0 | — | — | **Proven** | **Not executed** | — | Garden / ranked10 / HoF on `kooldb1` |
| Milestone unlock event UI | L0 | — | — | — | — | — | [`milestones-unlock-event-ui.md`](../milestones-unlock-event-ui.md) |
| `perfect_day` / `nightmare_day` day-close | L0 | — | Yes | **Proven** | **Not executed** | — | 113 midnight rows on work DB |
| Milestones doc consolidation | L0 | — | — | — | — | — | [`milestones-README.md`](../milestones-README.md) |
| Milestone `year_in_heaven` | L2 | SCH-011 | Yes | **Proven** | **Not executed** | — | Catalog **112**; holders on work DB |
| Milestone `play_streak_100` | L2 | SCH-011 | Yes | **Proven** | **Not executed** | — | 0 holders; catalog seeded |
| Milestones post-game contract | L2 | SCH-011–013 | Yes | **Proven** | **Not executed** | — | PHP ops P6; simul on `kooldb1`; live = dispatch at cutover |
| Rated play streaks (day/week) | L4 | SCH-014 | Yes | **Proven** | **Not executed** | — | `ranked4` + HoF; **proven on `kooldb1`**; live writer = PHP ops P7 |
| Activity wing stored truth (participation + streaks) | L4 | SCH-022–025 | Yes | **Proven** | **Not executed** | — | **`kooldb1` simul signed off** Jun 2026 (participation + play-streak + reached_at verify PASS) — [`activity-wing-stored-truth-policy.md`](../activity-wing-stored-truth-policy.md) |
| Result streak boundaries (Streaks LB) | L2 | SCH-026 | Yes | **Done** (ops + UI) | **Not executed** | — | `player_result_streaks`; LB tooltips + games drill-down; date/GD polish Jun 2026 |
| Milestones `diversity_merchant` per-game DD | L4 | — | Yes | **Proven** | **Not executed** | — | **25** holders; **6615** canonical rows |
| Milestones `giant_slayer` active #1 | L2 | — | Yes | **Proven** | **Not executed** | — | **31** holders on work DB |
| Milestones Phase 3 (catalog + full rebuild) | L4 | SCH-011–013 | Yes | **Proven** | **Not executed** | — | Catalog **112**; simul on `kooldb1` |
| Milestone meta leaderboard totals | L2 | SCH-020 | Yes | **Proven** | **Not executed** | — | `player_milestone_totals`; verify `milestone_totals_parity` PASS on `kooldb1` |
| Milestone catalog holder counts | L2 | SCH-021 | Yes | **Proven** | **Not executed** | — | All unlock rows incl. orphans; verify `milestone_holder_count_parity` PASS on `kooldb1` |
| Stored truth expansion | L4 | SCH-008 | Yes | **Proven** | **Not executed** | — | Five tables; **ops simul on `kooldb1`** (not May `kooldb` batch) |
| Opponents wing stored matchup (SCH-019) | L2 | SCH-019 | Yes | **Proven** | **Not executed** | — | Full `kooldb1` simul Jun 2026 — [`player-opponents-hub.md`](../player-opponents-hub.md) |
| Amiga realm snapshots + HoF (ko2amiga_db) | L5 | `028_hof_tournament_geo` | — | **Done** (local prove) | — | — | Incremental realm row + eight calendar/geo HoF records; Calendar & geo LB wing; `verify-hof-geo-year` |
| Amiga HoF record rise dates (ko2amiga_db) | L5 | `029_hof_record_rise_dates` | — | **Complete** (local prove + export) | — | — | Per-metric last-rise id/date; holder `*Date` from rise not participation — [`amiga-hof-record-date-policy.md`](../amiga-hof-record-date-policy.md) |
| Amiga career HoF rise dates (ko2amiga_db) | L5 | `030_career_rise_dates` | — | **Complete** (local prove + export) | — | — | Ten legacy career rows (`MostGamesPlayed` …) — rise `*Date` at event finalize — [`amiga-hof-record-date-policy.md`](../amiga-hof-record-date-policy.md) § SCH-030 |
| Amiga HoF holder projection verify (ko2amiga_db) | L5 | — | — | **Complete** (local prove) | — | — | Phase B stored semantics — [`amiga-stored-field-semantics-plan.md`](../amiga-stored-field-semantics-plan.md) |
| Amiga stored id/date pairing verify (ko2amiga_db) | L5 | — | — | **Complete** (local prove) | — | — | Phase C — rise/honours/career-best invariants |
| Amiga PHP finalize parity smoke (ko2amiga_db) | L5 | — | — | **Complete** (local prove) | — | — | Phase D — T24 reopen+finalize; prior-snapshot carry in PHP persist |
| H2H versus poster + pair detail + moments + charts | L0 | — | — | — | — | — | Poster + race table (perf rating last) + **3×3 moments grid (v2: neutral shells, goal-digit neon)** + **pair charts on H2H** (cumulative wins · cumulative goals · **total goals histogram** · rating compare · goals-per-game histograms · **scoreline heatmap**); **top opponents bar on Profile** — [`player-opponents-h2h-poster.md`](../player-opponents-h2h-poster.md) |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | **Done** (migrate) | **Not executed** | — | Migration `001` in ops package; live = migrate-work on cutover |
| Ladder replay sandbox (K32/1600) | L2 | SCH-002 | Partial | **Done** (May) | **Not executed** | — | Core ladder via `scripts/ladder`; website aggregates via ops simul |
| Records ratio leaders from playertable | L2 | SCH-003 | Yes | **Proven** | **Not executed** | — | [`records-post-game-exception.md`](records-post-game-exception.md) |
| Career peak/nadir (`PeakRating`, `LowestRating`) | L2 | — | Yes | **Proven** | **Not executed** | — | Contract §; PHP ops at cutover |

### Column legend

- **Level** — L0–L4 website/migration depth; see [`prod-coordination.md`](../prod-coordination.md).
- **Schema** — SCH id or `—`.
- **Ops simul** — Filled by `run_ops_sim.php` on work DB (not batch `REP-xxx` on prod).
- **`kooldb1` proof** — **Proven** / **Done** after migrate + simul + verify on work DB (`kooldb1` or `ko2unity_work`). **Not** “work still to do.”
- **Live cutover** — **Not executed** until Steve runs cutover on **live** prod; **Done (date)** after go-live.
- **Periodic** — cron/`FinalizeUtcDay` at live cutover.

### Adding a row

One line per **shipped/scoped user-facing capability** or **migration chunk**. Post-game behavior belongs in the **contract**, not new PG-NNN files.
