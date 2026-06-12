# Feature migration log

Lightweight index: **what we built** and **cutover status**. Agents update on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

**Read first:** [`cutover-readiness.md`](cutover-readiness.md) — **kooldb1 proof** ≠ **live prod executed**. “Not executed” on live is **scheduled go-live**, not repo work pending. **Do not** assign batch `REP-xxx` scripts for prod cutover.

| Feature | Level | Schema | Ops simul | `kooldb1` proof | Live cutover | Periodic | Notes |
|---------|-------|--------|-----------|-----------------|--------------|----------|-------|
| Ladder ops platform (`ops/`, PHP post-game) | — | — | — | **Done** Jun 2026 | **Not executed** | — | P0–P7 + `dispatch.php` + `dispatch_request.php` (HTTP); Steve `CMD=ProcessCompletedGame` |
| Local dual website (dev + work URLs) | — | — | — | — | — | — | **Jun 2026** — `ratingskickoff.test` → `ko2unity_db`, `work.ratingskickoff.test` → `ko2unity_work`; config router |
| Amiga offline realm (A1) | L0 | `scripts/amiga/sql/001_core.sql` | — | — | **Not executed** | — | **Staging live Jun 2026** — `ko2amiga_db`, rating/profile/games; config `site/config/` — [`amiga-staging-handoff.md`](../amiga-staging-handoff.md) |
| Amiga games tab filtered Perf. rating | L0 | — | — | — | — | — | Read-time async API on `/amiga/games.php` status line — [`amiga-performance-rating.md`](../amiga-performance-rating.md) |
| Amiga event finish + honours | L1 | `017`–`019` (`scripts/amiga/sql/`) | — | **Done** local | **Not executed** | — | `event_finish_position`, drop `overall_position`, Tier E override hook; [`amiga-tournament-honours-rules.md`](../amiga-tournament-honours-rules.md) |
| Amiga standings scope unification | L1 | `020` (`scripts/amiga/sql/`) | — | **Done** local (slices 0–7) | **Not executed** | — | `league`\|`knockout` enum; `league_scopes`; `resolve_primary_league_standings`; replay + verify OK — [`amiga-standings-scope-policy.md`](../amiga-standings-scope-policy.md) |
| Profile graph restoration | L0 | — | — | — | — | — | `player/profile.php` profile visuals restored to Activity-style chart frames; server-origin time axes; peak dashed line; comparison date/games toggle; played-days year picker; top-opponents tall chart; winrate-vs-Elo graph removed |
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
| Header cross-realm player search | — | — | — | — | — | — | `api/player_search.php?realm=all`; realm label per dropdown row; H2H stays online-only |
| Records two-panel split | L0 | — | — | — | — | — | Peak cache read path |
| League honours leaderboard (v1) | L0 | — | — | **Proven** | **Not executed** | — | `leaderboards/league-honours.php`; **proven on `kooldb1`** after simul |
| League period awards (medals DB) | L4 | SCH-009, SCH-010 | Yes | **Proven** | **Not executed** | PER-003 at cutover | Same simul as honours; live cron `FinalizeUtcDay` when wired |
| Status Leagues (Activity + Points) | L0 | — | — | **Proven** | **Not executed** | — | Phase **1** shipped; spec [`status-period-competitions.md`](../status-period-competitions.md) |
| Status league stack | L4 | SCH-008 | Yes | **Proven** | **Not executed** | — | `player_period_league`; PHP ops post-game at live cutover |
| Player games server-side filters/sort | L0 | — | — | — | — | — | Read-time |
| Hall of Fame aggregate read path | L0 | — | — | — | — | — | Peak/period cache |
| Hall of Fame context links | L0 | — | — | — | — | — | ranked wings + `k2_sort` (May 2026) |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | — | JS only |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | — | Sort + anchor column (May 2026) |
| Games tab 14-day buckets | L0 | — | — | — | — | — | **Recent** on `games.php` |
| Games Highlights (spectacle boards) | L0 | — | — | — | — | — | `games.php?view=highlights` |
| Play & Setup page | L0 | — | — | — | — | — | `join.php` |
| Box art story page | — | — | — | — | — | — | `boxart.php` (+ `boxart_story_section.php`, `boxart-story.css`, `images/boxart/`); KO2 cover history; Status heritage box links to it; PHP/CSS/content only |
| Hub / Status cosmetics | — | — | — | — | — | — | PHP only |
| Milestones hub tab (stub) | L0 | — | — | — | — | — | `milestones.php` v0 hub |
| Hub IA — Games off top nav | — | — | — | — | — | — | `games.php` sub-nav |
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
| Milestones `diversity_merchant` per-game DD | L4 | — | Yes | **Proven** | **Not executed** | — | **25** holders; **6615** canonical rows |
| Milestones `giant_slayer` active #1 | L2 | — | Yes | **Proven** | **Not executed** | — | **31** holders on work DB |
| Milestones Phase 3 (catalog + full rebuild) | L4 | SCH-011–013 | Yes | **Proven** | **Not executed** | — | Catalog **112**; simul on `kooldb1` |
| Stored truth expansion | L4 | SCH-008 | Yes | **Proven** | **Not executed** | — | Five tables; **ops simul on `kooldb1`** (not May `kooldb` batch) |
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
