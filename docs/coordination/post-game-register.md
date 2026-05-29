# Post-game (C++) — coordination pointer

**Agents:** Do **not** treat “post-game pending” or missing prod C++ as incomplete **website** work. For local and staging, **schema + REP rebuilds** are enough for PHP to use stored truth. Live C++ is updated **once at prod cutover** from the contract, not from per-table snippet packs in this repo.

---

## Behavior authority (matches contract)

| Area | Document | What it owns |
|------|----------|----------------|
| All project-owned aggregate tables | [`website-data-contract.md`](../website-data-contract.md) | Per-table **Post-game rule**, full rebuild, parity; § **Post-game derived-data behavior** for shared rules (`SET time_zone = '+00:00'`, etc.) |
| Hall of Fame / `generalstatstable` records | [`records-post-game-exception.md`](records-post-game-exception.md) | Tie policy (`>` not `>=`), UTC dates, ratio column removal, staging defect examples |
| Steve’s existing code shape | [`ratings_cpp.txt`](../ratings_cpp.txt) | `RatingProcedureUnity` excerpt — merge point, not the spec |

**Contract index** (table → post-game §): see the **Derived data index** table at the top of `website-data-contract.md` (`player_period_games`, `player_period_league`, `server_daily_activity`, …). Each row’s **Post-game rule** is what Steve implements for **new** prod games at cutover.

**Do not:** create `cpp-snippets/` or cite `PG-00x` as blocking local/staging work (retired May 2026).

---

## What we coordinate in repo

| Environment | Deliverable |
|-------------|-------------|
| Local / staging | [`schema-register.md`](schema-register.md) + [`replay-register.md`](replay-register.md) (`*_rebuild.sql` or `scripts/rebuild_website_derived_data_local.ps1`) |
| Prod cutover | Same SQL on prod + merge C++ from contract (+ records exception doc); note **Prod live** in [`feature-log.md`](feature-log.md) when done |

---

## Steve — prod cutover checklist

1. Apply pending [`schema/migrations/`](../schema/migrations/) on prod.
2. Run matching `*_rebuild.sql` scripts (same set as staging — see [`replay-register.md`](replay-register.md)).
3. Merge post-game C++ from **contract** post-game rules + [`ratings_cpp.txt`](../ratings_cpp.txt).
4. **Records only:** [`records-post-game-exception.md`](records-post-game-exception.md) + [`staging-post-game-record-defects.md`](../staging-post-game-record-defects.md).

Email template: [`cutover-packet-template.md`](cutover-packet-template.md).

---

## Prod live writer (informal tracker)

No PG-NNN IDs. When prod maintains an aggregate on each new game, note **table name** and **done date** in feature-log **Prod live** or MEMORY.

| Table / area | Prod live writer | Notes |
|--------------|------------------|-------|
| Aggregate tables in contract | Pending Steve | Incremental rules: contract § per table |
| `player_milestones` (110 keys) | Pending Steve | Full spec: contract § `player_milestones` post-game (M1–M7 phases); rebuild REP-008 on staging/prod first |
| `generalstatstable` records | Pending Steve | [`records-post-game-exception.md`](records-post-game-exception.md) |
| `player_play_streaks` + play-streak HoF cols | Pending Steve | Contract § `player_play_streaks`; PHP reference `includes/player_play_streaks.php`; after `player_period_games`; **staging REP-015 done** May 2026 — prod C++ still required for live games |
| `playertable` / core ladder | Existing C++ | Replay sandbox: REP-001; Elo K/fade: periodic + future cutover |

**Retired May 2026:** `docs/coordination/cpp-snippets/` (deleted); `post-game-cpp-handoff.md` merged into this file.
