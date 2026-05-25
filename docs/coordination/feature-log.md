# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Post-game (C++) | Periodic | Notes |
|---------|-------|--------|--------|-----------------|----------|-------|
| Player games server-side filters/sort | L0 | — | — | — | — | `individual3.php` reads existing `ratedresults`; auto-submit Result/Opponent filters, URL sort links, 100-row slices; no stored truth change |
| Hall of Fame aggregate read path | L0 | — | — | — | — | `ranked8.php` busiest day/month/year prefers existing `player_period_games` when available, with `ratedresults` fallback until rollout |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | `individual2a/b/c.php` sort/default indicators JS only; player games are tracked separately |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | `ranked1`–`ranked5`, `ranked7`, `ranked8` sort/autorank/tab-default indicators JS only; no stored truth change |
| Games tab 7-day buckets | L0 | — | — | — | — | Read-time SQL on `ratedresults`; no stored truth change |
| Hub / Status cosmetics | — | — | — | — | — | PHP only; no prod DB writers |
| Status monthly league (table) | L0 | — | — | — | — | Read-time SQL on `ratedresults` |
| Period activity leaderboards | L3 | SCH-004 | REP-003 | PG-005 | — | Local schema/backfill/PHP done; staging handoff ready; prod method TBD |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | — | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600/no decay) | L2 | SCH-002 | REP-001 | PG-002 (TBD) | PER-001 fade off | Staging replay done; prod not |
| Records ratio leaders from playertable | L3 | SCH-003 | REP-001 note | PG-004 | — | Local 002 DROP 28 GST cols; Steve: same migration + C++ stop writes |
| League medals on profile | — | — | — | — | — | *Not started* — future row when scoped |

### Column legend

- **Level** — L0–L5 per [`prod-coordination.md`](../prod-coordination.md); `—` = not applicable.
- **Schema / Replay / …** — register ID or `—`.

### Adding a row

One line per **user-facing capability** or **migration chunk**. Link register IDs, not essays.
