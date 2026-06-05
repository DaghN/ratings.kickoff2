# Post-game — coordination pointer

**Agents:** Post-game **prep is done** on `kooldb1` (ops simul + verify). **Not** incomplete website work. Live prod still uses legacy C++ until cutover — see [`cutover-readiness.md`](cutover-readiness.md).

**Prod target (agreed Jun 2026):** Steve inserts ground truth → **PHP** `ops/dispatch.php` `CMD=ProcessCompletedGame`. **Retire C++ derived post-game** at cutover. Behaviour = [`website-data-contract.md`](../website-data-contract.md); code = `ops/modules/process_completed_game.php` + includes.

**Steve runbook:** [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) — **migrate → seed → zero → simul → verify → live dispatch** (not `*_rebuild.sql` marathon).

**Discrepancies:** [`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md).

---

## Behaviour authority

| Area | Document |
|------|----------|
| Aggregate tables | [`website-data-contract.md`](../website-data-contract.md) |
| HoF / records | [`records-post-game-exception.md`](records-post-game-exception.md) |
| Legacy C++ (retiring) | [`ratings_cpp.txt`](../ratings_cpp.txt) |
| PHP ops (target) | `ops/modules/process_completed_game.php` |
| Cutover links | [`post-game-cutover-checklist.md`](post-game-cutover-checklist.md) |

---

## Environments

| Environment | Deliverable |
|-------------|-------------|
| Work / `kooldb1` | **Done** — simul sign-off Jun 2026 ([`cutover-readiness.md`](cutover-readiness.md)) |
| Live prod | **Not executed** — Steve cutover when scheduled |

---

## Steve — prod cutover (summary)

1. `migrate-work` — all files in `ops/sql/migrations/`
2. `seed-catalog` + `zero-derived`
3. **`run_ops_sim.php run`** + **`run_verify_ops_sim.php`**
4. Wire `ProcessCompletedGame` + `FinalizeUtcDay`; disable C++ derived block
5. Mark **Live prod executed** in [`schema-register.md`](schema-register.md) / [`feature-log.md`](feature-log.md)

Details: [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md). Email: [`cutover-packet-template.md`](cutover-packet-template.md).

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
