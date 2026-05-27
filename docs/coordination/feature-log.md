# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Prod live | Periodic | Notes |
|---------|-------|--------|--------|-----------|----------|-------|
| Daily active players chart | L2 | SCH-007 | REP-006 | Pending | — | `server_daily_activity`; staging done May 2026; post-game from contract at prod cutover |
| Top activity eras chart | L0 | — | — | — | — | `player_period_games`; no new stored truth |
| Activity Graph Roadmap | L0 | — | — | — | — | Read-time `ratedresults`/`playertable` |
| Activity Double Digit Merchant charts | L0 | — | — | — | — | Read-time |
| Activity recent daily chart | L0 | — | — | — | — | Read-time |
| Persistent tint preference | — | — | — | — | — | Client-only |
| Records two-panel split | L0 | — | — | — | — | Peak cache read path |
| Status Leagues (Activity + Points) | L0 | — | — | — | — | Phase 1 shipped; single-slot nav + cache + prewarm; Phase 1.5 [`status-period-competitions-wip.md`](../status-period-competitions-wip.md) |
| Status league stack | L4 | SCH-008 | REP-007 | Pending | — | PHP reads `player_period_league` when present; **staging done** May 2026 (Steve verify); prod schema + post-game at cutover |
| Player games server-side filters/sort | L0 | — | — | — | — | Read-time |
| Hall of Fame aggregate read path | L0 | — | — | — | — | Peak/period cache with fallbacks |
| Player stat `k2-table.js` migration | L0 | — | — | — | — | JS only |
| Leaderboard `k2-table.js` migration | L0 | — | — | — | — | JS only |
| Games tab 14-day buckets | L0 | — | — | — | — | Read-time |
| Hub / Status cosmetics | — | — | — | — | — | PHP only |
| Status monthly league / performance | L2 | SCH-005 | REP-004 | Pending | — | Staging done; prod indexes + live writer at cutover |
| Period activity leaderboards | L2 | SCH-004, SCH-006 | REP-003, REP-005 | Pending | — | Staging SCH+REP done May 2026 |
| Stored truth expansion | L4 | SCH-008 | REP-007–011 | Pending | — | Five tables: local + **staging `kooldb` done** May 2026 (Steve SCH-008 + REP-007–011, parity verify pass); prod cutover + contract post-game pending |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | Pending | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600/no decay) | L2 | SCH-002 | REP-001 | Pending | PER-001 | Staging replay done |
| Records ratio leaders from playertable | L2 | SCH-003 | REP-001 note | Pending | — | [`records-post-game-exception.md`](records-post-game-exception.md) for prod C++ |

### Column legend

- **Level** — L0–L2 for website work in repo; **Prod live** = Steve C++ merged at cutover (see [`website-data-contract.md`](../website-data-contract.md)). Full ladder: [`prod-coordination.md`](../prod-coordination.md).
- **Schema / Replay** — register IDs or `—`.
- **Prod live** — `Pending` / `Done (date)` / `—` (not applicable). **Not** a standing snippet backlog.

### Adding a row

One line per **shipped/scoped user-facing capability** or **migration chunk**. Post-game behavior belongs in the **contract**, not new PG-NNN files.
