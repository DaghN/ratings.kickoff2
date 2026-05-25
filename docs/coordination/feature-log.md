# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Post-game (C++) | Periodic | Notes |
|---------|-------|--------|--------|-----------------|----------|-------|
| Records two-panel split | L0 | — | — | — | — | `server2.php` reads the existing peak period aggregate/fallback query path for natural-width Peak activity and Peak performance panels, with New/Legendary age markers (`Legendary` in holo); no new stored truth |
| Player games server-side filters/sort | L0 | — | — | — | — | `individual3.php` reads existing `ratedresults`; auto-submit Result/Opponent filters, URL sort links with styled header help tooltips, 100-row slices; no stored truth change |
| Hall of Fame aggregate read path | L0 | — | — | — | — | `ranked8.php` Activity tab prefers existing `player_period_games` when available, with `ratedresults` fallback; Period toggle headings use “Most games in one day/month/year” and All time shows natural-width “Most games of all time” + Longevity tables |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | `individual2a/b/c.php` sort/default indicators and opponent-table header help tooltips JS only; player games are tracked separately |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | `ranked1`–`ranked5`, `ranked7`, `ranked8` sort/autorank/tab-default indicators JS only; main leaderboard tabs keep a stable first-column width; sortable headers now use styled abbreviation/sort-help tooltips; no stored truth change |
| Games tab 7-day buckets | L0 | — | — | — | — | Read-time SQL on `ratedresults`; no stored truth change |
| Hub / Status cosmetics | — | — | — | — | — | PHP only; no prod DB writers |
| Period activity leaderboards | L3 | SCH-004 | REP-003 | PG-005 | — | Local + staging schema/backfill done; staging expectation test passed; prod method TBD |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | — | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600/no decay) | L2 | SCH-002 | REP-001 | PG-002 (TBD) | PER-001 fade off | Staging replay done; prod not |
| Records ratio leaders from playertable | L3 | SCH-003 | REP-001 note | PG-004 | — | Local 002 DROP 28 GST cols; Steve: same migration + C++ stop writes |

### Column legend

- **Level** — L0–L5 per [`prod-coordination.md`](../prod-coordination.md); `—` = not applicable.
- **Schema / Replay / …** — register ID or `—`.

### Adding a row

One line per **shipped/scoped user-facing capability** or **migration chunk**. Keep loose ideas in the owning feature/spec doc until they are scoped.
