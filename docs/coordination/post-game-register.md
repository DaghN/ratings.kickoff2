# Post-game — coordination pointer

**Agents:** Post-game **prep is done** on `kooldb1` (ops simul + verify). **Live PHP ops cutover executed 2026-07-18** — see [`cutover-readiness.md`](cutover-readiness.md). **Not** incomplete website work.

**Prod today:** Steve inserts ground truth → **PHP** `ops/dispatch.php` `CMD=ProcessCompletedGame`. **C++ derived post-game retired.** Behaviour = [`website-data-contract.md`](../website-data-contract.md); code = `ops/modules/process_completed_game.php` + includes.

**Steve runbook:** [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) — **migrate → seed → zero → simul → verify → live dispatch** (not `*_rebuild.sql` marathon).

**Discrepancies:** [`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md).

---

## Behaviour authority

| Area | Document |
|------|----------|
| Aggregate tables | [`website-data-contract.md`](../website-data-contract.md) |
| HoF / records | [`records-post-game-exception.md`](records-post-game-exception.md) |
| Legacy C++ (retired) | [`ratings_cpp.txt`](../ratings_cpp.txt) |
| PHP ops (live) | `ops/modules/process_completed_game.php` |
| Cutover links | [`post-game-cutover-checklist.md`](post-game-cutover-checklist.md) |

---

## Environments

| Environment | Deliverable |
|-------------|-------------|
| Work / `kooldb1` | **Done** — simul sign-off Jun 2026 ([`cutover-readiness.md`](cutover-readiness.md)) |
| Live prod | **Done (2026-07-18)** — PHP ops live; C++ derived retired |

---

## Steve — live path (executed 2026-07-18)

Historical checklist (still the shape for future schema packets):

1. `migrate-work` — all files in `ops/sql/migrations/`
2. `seed-catalog` + `zero-derived`
3. **`run_ops_sim.php run`** + **`run_verify_ops_sim.php`**
4. Wire `ProcessCompletedGame` + `FinalizeUtcDay`; C++ derived block retired
5. Mark **Live prod executed** in [`schema-register.md`](schema-register.md) / [`feature-log.md`](feature-log.md)

Daily ops: [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) · [`steve-live-ops.md`](../../site/public_html/ops/docs/steve-live-ops.md). Email template: [`cutover-packet-template.md`](cutover-packet-template.md).

---

## Live writer tracker

| Table / area | Target writer | Prep (`kooldb1`) |
|--------------|---------------|------------------|
| Per-game P0–P7 | PHP ops | **Proven** (simul) |
| `player_milestones` | PHP + `FinalizeUtcDay` | **Proven** |
| `generalstatstable` | PHP | **Proven** |
| `player_play_streaks` | PHP P7 | **Proven** |
| `playertable` / Elo | PHP P1–P2 | **Proven** |
| **Legacy live today** | C++ | Until cutover |

**Retired May 2026:** `cpp-snippets/`; PG-NNN IDs.
