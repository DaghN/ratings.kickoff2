# Post-game — coordination pointer

**Agents:** Do **not** treat “post-game pending” as incomplete **website** work when **schema + REP rebuilds** and **ops PHP replay** already satisfy the contract on work/staging.

**Prod target (agreed Jun 2026):** After cutover, **Steve inserts ground truth**, then invokes **PHP** [`ladder-ops-platform.md`](../ladder-ops-platform.md) `ops/dispatch.php` `CMD=ProcessCompletedGame` (runner today: `run_process_game.php`). **Prod C++ derived post-game is retired** — not extended with M1–M7. Correctness = [`website-data-contract.md`](../website-data-contract.md); PHP reference = `site/public_html/ops/modules/process_completed_game.php` + includes.

**Discrepancy register (contract vs legacy oracle / C++):** [`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md).

---

## Behavior authority (matches contract)

| Area | Document | What it owns |
|------|----------|----------------|
| All project-owned aggregate tables | [`website-data-contract.md`](../website-data-contract.md) | Per-table **Post-game rule**, full rebuild, parity; § **Post-game derived-data behavior** for shared rules (`SET time_zone = '+00:00'`, etc.) |
| Hall of Fame / `generalstatstable` records | [`records-post-game-exception.md`](records-post-game-exception.md) | Tie policy (`>` not `>=`), UTC dates, ratio column removal, staging defect examples |
| Legacy prod C++ (retiring) | [`ratings_cpp.txt`](../ratings_cpp.txt) | Historical field order / quirks — **not** the cutover spec |
| PHP ops post-game (target) | `ops/modules/process_completed_game.php` | Per-game derived writer for prod |
| Cutover index (peak, clubs, pointers, sequence) | [`post-game-cutover-checklist.md`](post-game-cutover-checklist.md) | Links only — rules stay in contract |

**Contract index** (table → post-game §): see the **Derived data index** table at the top of `website-data-contract.md` (`player_period_games`, `player_period_league`, `server_daily_activity`, …). Each row’s **Post-game rule** is what Steve implements for **new** prod games at cutover.

**Do not:** create `cpp-snippets/` or cite `PG-00x` as blocking local/staging work (retired May 2026).

---

## What we coordinate in repo

| Environment | Deliverable |
|-------------|-------------|
| Local / staging | [`schema-register.md`](schema-register.md) + [`replay-register.md`](replay-register.md) (`*_rebuild.sql` or `scripts/rebuild_website_derived_data_local.ps1`) |
| Prod cutover | Schema + full replay + **enable PHP post-game** on live games; retire C++ derived block; note **Prod live** in [`feature-log.md`](feature-log.md) when done |

---

## Steve — prod cutover checklist

1. Apply pending [`schema/migrations/`](../schema/migrations/) on prod.
2. Run matching `*_rebuild.sql` scripts (same set as staging — see [`replay-register.md`](replay-register.md)).
3. **Full ladder replay** so stored truth matches contract (peak-at-20, personal `>`, milestones, …).
4. Wire live games: after ground insert → call **PHP** `ProcessCompletedGame` (see [`ladder-ops-platform.md`](../ladder-ops-platform.md) §2); **remove/disable C++ derived post-game** once verified.
5. Agent index: [`post-game-cutover-checklist.md`](post-game-cutover-checklist.md). **Records:** [`records-post-game-exception.md`](records-post-game-exception.md).

Email template: [`cutover-packet-template.md`](cutover-packet-template.md).

---

## Prod live writer (informal tracker)

No PG-NNN IDs. When prod maintains an aggregate on each new game, note **table name** and **done date** in feature-log **Prod live** or MEMORY.

| Table / area | Prod live writer (target) | Notes |
|--------------|---------------------------|-------|
| Per-game derived (P1–P7) | **PHP ops** (pending cutover) | Reference impl in repo; parity `ab-post-game` |
| `player_milestones` | PHP + periodic league job | Game keys in `post_game_milestones.php`; league keys on finalize |
| `generalstatstable` | PHP | Strict `>` HoF — shipped in ops |
| `player_play_streaks` + HoF cols | PHP | `k2_play_streak_after_rated_game()` — wired from ops |
| `playertable` / Elo on `ratedresults` | PHP ops P1–P2 | Retire C++ `RatingProcedureUnity` derived section at cutover |
| **Legacy** | C++ today | Until PHP hook is live on prod |

**Retired May 2026:** `docs/coordination/cpp-snippets/` (deleted); `post-game-cpp-handoff.md` merged into this file.
