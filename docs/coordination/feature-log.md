# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Prod live | Periodic | Notes |
|---------|-------|--------|--------|-----------|----------|-------|
| Ladder ops platform (`ops/`, PHP post-game) | — | — | — | — | — | **Jun 2026** — platform doc + `ops/` scaffold + **§6 conventions**; no PHP yet; Steve agreed `game_id` + `CMD=ProcessCompletedGame` |
| Local dual website (dev + work URLs) | — | — | — | — | — | **Jun 2026** — `ratingskickoff.test` → `ko2unity_db`, `work.ratingskickoff.test` → `ko2unity_work`; config router; not config-file cutover |
| Profile graph restoration | L0 | — | — | — | — | `individual1.php` profile visuals restored to Activity-style chart frames; server-origin time axes; peak dashed line; comparison date/games toggle; played-days year picker; top-opponents tall chart; winrate-vs-Elo graph removed |
| Daily active players chart | L2 | SCH-007 | REP-006 | Pending | — | `server_daily_activity`; staging done May 2026; post-game from contract at prod cutover |
| All-time busiest players chart (Activity) | L0 | — | — | — | — | Top 10 by `playertable.NumberGames` (tie → lowest ID); monthly series from `player_period_games`; was monthly top-10 eras |
| Activity Graph Roadmap | L0 | — | — | — | — | Read-time `ratedresults`/`playertable` |
| Activity recent milestones digest | — | — | — | — | — | **Removed Jun 2026**; busiest day moved to Activity summary (`server_period_game_totals`) |
| Activity Double Digit Merchant charts | — | — | — | — | — | **Removed Jun 2026** (Activity + APIs/JS deleted; `milestone.php` Graphs = unlock year + cumulative only; rating histograms on `server1.php` for established only) |
| Activity recent daily chart | L0 | — | — | — | — | Read-time |
| Activity charts v2 (single module) | — | — | — | — | — | **Shipped** on `server1.php` (local + staging) — `activity-charts-v2.js`; legacy boot files removed Jun 2026; [`activity-charts.md`](../activity-charts.md) |
| Persistent tint preference | — | — | — | — | — | Client-only |
| Six-hour tint schedule | — | — | — | — | — | Local 6h slots; manual override; `k2-tint-schedule.js` |
| Header realm switcher | — | — | — | — | — | **Removed Jun 2026** — markup/CSS deleted; `realm-switch.js` tint-only; `data-realm="online"` retained on pages |
| Records two-panel split | L0 | — | — | — | — | Peak cache read path |
| League honours leaderboard (v1) | L0 | — | — | — | — | `ranked9.php`; reads `playertable` + `player_league_totals`; wing tab in `lb_nav.php` |
| League period awards (medals DB) | L4 | SCH-009, SCH-010 | REP-012, REP-013 | Pending | — | **Staging done** May 2026 (Steve; counts match local); League honours + slice totals live on `kooldb`; prod schema + REP pending; no cron/PER-003 yet |
| Status Leagues (Activity + Points) | L0 | — | — | — | — | Shipped (May–Jun 2026): paired tables, nav, Daily games list; spec [`status-period-competitions.md`](../status-period-competitions.md) |
| Status league stack | L4 | SCH-008 | REP-007 | Pending | — | PHP reads `player_period_league` when present; **staging done** May 2026 (Steve verify); prod schema + post-game at cutover |
| Player games server-side filters/sort | L0 | — | — | — | — | Read-time |
| Hall of Fame aggregate read path | L0 | — | — | — | — | Peak/period cache with fallbacks |
| Hall of Fame context links | L0 | — | — | — | — | All values → ranked wings + `provisional=0` + `k2_sort`; peaks → `ranked8#…` (May 2026) |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | JS only |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | Sort + anchor column (`data-k2-anchor-col`) + active-sort emphasis; May 2026 |
| Games tab 14-day buckets | L0 | — | — | — | — | Read-time; **Recent** on `server3.php` |
| Games Highlights (spectacle boards) | L0 | — | — | — | — | `server3.php?view=highlights`; top-100 `ORDER BY` on `ratedresults`; four boards |
| Play & Setup page | L0 | — | — | — | — | Hub tab `join.php`; [`join-play-setup.md`](../join-play-setup.md) |
| Hub / Status cosmetics | — | — | — | — | — | PHP only; May 2026 **peer pill carry-scroll** (`data-k2-carry-scroll`, `k2-carry-scroll.js`) on hub / `lb_nav` / `player_nav` |
| Milestones hub tab (stub) | L0 | — | — | — | — | `milestones.php` + hub nav; full hub WIP [`milestones-hub-ia.md`](../milestones-hub-ia.md) |
| Hub IA — Games off top nav | — | — | — | — | — | `server3.php` Recent + Highlights sub-nav; Status **Games →** → Recent |
| Status league / performance | L2 | SCH-005, SCH-017 | REP-007 | Pending | — | Indexes from SCH-005; month league via `player_period_league`; legacy monthly table dropped Jun 2026 |
| Period activity leaderboards | L2 | SCH-004, SCH-006 | REP-003, REP-005 | Pending | — | Staging SCH+REP done May 2026 |
| Profile hero milestones (no peak) | L0 | — | — | — | — | Hero: `n/catalog` → garden (tier subcounts removed Jun 2026); peak only on leaderboards/charts |
| Milestones Phase 4 v0 UI | L0 | — | — | Pending | — | **Staging DB done** May 2026; WinSCP PHP for garden/ranked10/HoF if not already; prod pending |
| Milestone unlock event UI | L0 | — | — | — | — | Register + UI surfaces; [`milestones-unlock-event-ui.md`](../milestones-unlock-event-ui.md) |
| `perfect_day` / `nightmare_day` day-close `achieved_at` | L0 | — | OO-003 | **Staging done** Jun 2026 | — | SQL + UI verified on staging: **113** midnight rows, Recent `00:00`, garden **Games** → `individual3?day=`; total **6620**; prod pending |
| Milestones doc consolidation | L0 | — | — | — | — | [`milestones-README.md`](../milestones-README.md) + generated [`milestones-catalog.md`](../milestones-catalog.md); tier-curated tables archived |
| Milestone `year_in_heaven` (52 weeks/year) | L2 | SCH-011 | REP-008 splice | **Staging done** May 2026 | — | Catalog **112**, **5** holders on `kooldb` (geo4444/2021); establishing game; add-one playbook local-verify; handoff [`milestones-year-in-heaven-handoff.md`](milestones-year-in-heaven-handoff.md) |
| Milestone `play_streak_100` (100 days of bliss) | L2 | SCH-011 | REP-008 splice | Pending | — | **Catalog on staging** May 2026; copy patch May 2026 (`patch_milestone_catalog_copy.php`); 0 unlock holders; playbook [`milestones-add-one-playbook.md`](milestones-add-one-playbook.md) |
| Milestones post-game contract | L2 | SCH-011–013 | REP-008 | Pending | — | PHP ops P6 + contract §; **staging REP-008 done**; **prod PHP ops cutover** pending Steve |
| Rated play streaks (day/week) | L4 | SCH-014 | REP-015 | Pending | — | **Staging DB+UI done** May 2026; live writer = **PHP ops** at cutover |
| Milestones `diversity_merchant` per-game DD | L4 | — | REP-008b | **Staging done** May 2026 | — | Rule fix: per-game DD × 5 opponents (was cumulative 68→**25**); tier accomplished (`key`/`amber`); staging **6615** rows verified; prod pending |
| Milestones `giant_slayer` active #1 | L2 | — | — | — | — | Rule fix: beat #1 among 365d-active; **staging verify 31** May 2026; contract post-game |
| Milestones Phase 3 (catalog + full rebuild) | L4 | SCH-011, SCH-012, SCH-013 | REP-008, REP-014 | Pending | — | **Staging done** May 2026; catalog **112** keys; prod schema+REP+C++ pending |
| Stored truth expansion | L4 | SCH-008 | REP-007–011 | Pending | — | Five tables: local + **staging `kooldb` done** May 2026 (Steve SCH-008 + REP-007–011, parity verify pass); prod cutover + contract post-game pending |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | Pending | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600) | L2 | SCH-002 | REP-001 | Pending | — | Staging replay done |
| Records ratio leaders from playertable | L2 | SCH-003 | REP-001 note | Pending | — | [`records-post-game-exception.md`](records-post-game-exception.md) — parity at PHP ops cutover |
| Career peak/nadir (`PeakRating`, `LowestRating`) | L2 | — | REP with post-game | Pending | — | Contract § May 2026: unset until 20 games; establish from `Rating` at game 20; max/min after; legacy C++/replay until cutover |

### Column legend

- **Level** — L0–L2 for website work in repo; **Prod live** = Steve C++ merged at cutover (see [`website-data-contract.md`](../website-data-contract.md)). Full ladder: [`prod-coordination.md`](../prod-coordination.md).
- **Schema / Replay** — register IDs or `—`.
- **Prod live** — `Pending` / `Done (date)` / `—` (not applicable). **Not** a standing snippet backlog.

### Adding a row

One line per **shipped/scoped user-facing capability** or **migration chunk**. Post-game behavior belongs in the **contract**, not new PG-NNN files.
