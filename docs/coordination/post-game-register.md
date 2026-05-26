# Post-game (C++) — retired register

**Agents:** Do **not** treat “post-game pending” as incomplete website work. For local and staging, **schema + REP rebuilds** are enough. Live C++ is updated **once at prod cutover** from the contract, not maintained as snippet packs in this repo.

## Behavior authority

[`docs/website-data-contract.md`](../website-data-contract.md) — per-table **Post-game rule** sections and § Post-game derived-data behavior.

**Reference for Steve’s code:** [`docs/ratings_cpp.txt`](../ratings_cpp.txt) (`RatingProcedureUnity` excerpt).

## Exception (keep this file path in mind)

| Topic | Doc |
|--------|-----|
| Records / `generalstatstable` tie-break, UTC, ratio leaders, staging defects | [`records-post-game-exception.md`](records-post-game-exception.md) |

## What we coordinate in repo now

| Environment | Deliverable |
|-------------|-------------|
| Local / staging | [`schema-register.md`](schema-register.md) + [`replay-register.md`](replay-register.md) (`*_rebuild.sql`) |
| Prod cutover | Same SQL + merge C++ from contract (+ records exception doc) — date/note in [`feature-log.md`](feature-log.md) **Prod live** column when done |

## Prod live writer (informal tracker)

No PG-NNN IDs. When prod gets live games on new aggregates, note **table name** and **done date** in feature-log or MEMORY — not a standing snippet backlog.

| Table / area | Prod live writer | Notes |
|--------------|------------------|-------|
| Aggregate tables in contract | Pending Steve | Infer incremental updates from contract § Post-game at cutover |
| `generalstatstable` records | Pending Steve | Use [`records-post-game-exception.md`](records-post-game-exception.md) |
| `playertable` / core ladder | Existing C++ | Replay sandbox: REP-001; Elo K/fade: periodic + future cutover |

**Retired May 2026:** `docs/coordination/cpp-snippets/` (PG-005–013 deleted); per-table snippet register rows removed as agent workflow.
