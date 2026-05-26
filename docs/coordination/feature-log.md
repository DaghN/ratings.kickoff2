# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Post-game (C++) | Periodic | Notes |
|---------|-------|--------|--------|-----------------|----------|-------|
| Activity Graph Roadmap (heatmap, depth, texture, matchups, digest) | L0 | — | — | — | — | `server1.php` five new Activity page features: 12-month daily heatmap, participation depth stacked bars, play-texture multi-line (goals/game, draw %, DD/100, CS/100), unique matchups per month, and a milestone digest card; all read `ratedresults`/`playertable` via five new APIs; no stored truth change |
| Activity Double Digit Merchant charts | L0 | — | — | — | — | `server1.php` reads existing `ratedresults`/`playertable` through three APIs for first 10+ goal milestones: new merchants per year, cumulative merchants, and merchant rating distribution; no stored truth change |
| Activity recent daily chart | L0 | — | — | — | — | `server1.php` reads existing `ratedresults` through `api/server_games_by_day_recent.php` for a past-month games-per-day bar chart with zero-game days included and a `Games` legend chip; no stored truth change |
| Persistent tint preference | — | — | — | — | — | Client-only UI preference: `k2-accent-tune` now persists in `localStorage`, migrates old session-only choices, and syncs open tabs; no stored ladder truth change |
| Records two-panel split | L0 | — | — | — | — | `server2.php` reads the peak period cache/aggregate/fallback query path for natural-width Peak activity and Peak performance panels, including year/month/week/day rows with New/Legendary age markers (`Legendary` in holo) |
| Status league stack | L0 | — | — | — | — | `status.php` reads existing `ratedresults` for uncapped daily, Monday-start weekly, monthly, and yearly league panels with current/previous toggles; monthly still prefers `player_monthly_league`; no new stored truth |
| Player games server-side filters/sort | L0 | — | — | — | — | `individual3.php` reads existing `ratedresults`; auto-submit Result/Opponent filters, URL sort links with styled header help tooltips, 100-row slices; no stored truth change |
| Hall of Fame aggregate read path | L0 | — | — | — | — | `ranked8.php` Activity tab prefers `player_peak_period_games`, then `player_period_games`, then `ratedresults`; Calendar view shows non-sortable day/week/month/year top-20 tables with help + Games-desc indicator, and All time shows sortable natural-width “Most games of all time” + Longevity tables |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | `individual2a/b/c.php` sort/default indicators and opponent-table header help tooltips JS only; player games are tracked separately |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | `ranked1`–`ranked5`, `ranked7`, `ranked8` sort/autorank/tab-default indicators JS only; main leaderboard tabs keep a stable first-column width; sortable headers use styled abbreviation/context tooltips where useful and otherwise rely on the sort hint; no stored truth change |
| Games tab 14-day buckets | L0 | — | — | — | — | `server3.php` read-time SQL on `ratedresults`; 14 day buckets with fully sortable daily game tables (`GD`, integer `Elo Diff`, `Fav ES`, `Adjustment`), useful header popups mirrored on `game.php` as non-sortable help, and deep Elo help on `Fav ES`/`Adjustment`; no stored truth change |
| Hub / Status cosmetics | — | — | — | — | — | PHP only; Status active leaderboard has sortable Rank/Player/Elo/Games headers with only the Elo/rank help kept explicit; recent games keep compact score-only copy and link `Games →`; rated-games arc links `Activity →` to `server1.php`, whose legacy Overall Server Stats table is folded into a key sentence, fact cards, and a small games/opponents line; no prod DB writers |
| Status monthly league / performance | L3 | SCH-005 | REP-004 | PG-006 | — | Local + staging schema/rebuild done: status indexes + `player_monthly_league` aggregate; PHP prefers aggregate with `ratedresults` fallback; prod pending Steve |
| Period activity leaderboards | L3 | SCH-004, SCH-006 | REP-003, REP-005 | PG-005, PG-007 | — | Local week + peak cache schema/rebuild done; staging has original day/month/year aggregate and needs SCH-006 + rebuild; prod method TBD |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | — | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600/no decay) | L2 | SCH-002 | REP-001 | PG-002 (TBD) | PER-001 fade off | Staging replay done; prod not |
| Records ratio leaders from playertable | L3 | SCH-003 | REP-001 note | PG-004 | — | Local 002 DROP 28 GST cols; Steve: same migration + C++ stop writes |

### Column legend

- **Level** — L0–L5 per [`prod-coordination.md`](../prod-coordination.md); `—` = not applicable.
- **Schema / Replay / …** — register ID or `—`.

### Adding a row

One line per **shipped/scoped user-facing capability** or **migration chunk**. Keep loose ideas in the owning feature/spec doc until they are scoped.
