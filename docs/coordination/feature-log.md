# Feature migration log

Lightweight index: **what we built**, **prod level**, **migration status**. Agents update this on **“update docs”** ([`../UPDATE_DOCS.md`](../UPDATE_DOCS.md)) — not on every CSS tweak.

| Feature | Level | Schema | Replay | Post-game (C++) | Periodic | Notes |
|---------|-------|--------|--------|-----------------|----------|-------|
| Hub / Status cosmetics | — | — | — | — | — | PHP only; no prod DB writers |
| Status monthly league (table) | L0 | — | — | — | — | Read-time SQL on `ratedresults` |
| Profile `ratedresults` indexes | L1 | SCH-001 | — | — | — | Prod index apply pending Steve |
| Ladder replay sandbox (K32/1600/no decay) | L2 | SCH-002 | REP-001 | PG-002 (TBD) | PER-001 fade off | Staging replay done; prod not |
| League medals on profile | — | — | — | — | — | *Not started* — future row when scoped |

### Column legend

- **Level** — L0–L5 per [`prod-coordination.md`](../prod-coordination.md); `—` = not applicable.
- **Schema / Replay / …** — register ID or `—`.

### Adding a row

One line per **user-facing capability** or **migration chunk**. Link register IDs, not essays.
