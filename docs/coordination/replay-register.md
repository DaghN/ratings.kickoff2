# Replay register — redirect (Jun 2026)

**Do not use REP-xxx rows as an agent task list for prod or staging.**

**Cutover / prod readiness:** [`cutover-readiness.md`](cutover-readiness.md) — derived data is filled by **`ops/run_ops_sim.php`**, not by running each `*_rebuild.sql` on prod.

**Historical register (May 2026 batch rebuilds on legacy `kooldb`, run log, milestone row counts):** [`../archive/replay-register-2026-05.md`](../archive/replay-register-2026-05.md)

**Repair-only SQL** (archived): `docs/archive/batch-rebuild-sql-2026-05/*_rebuild.sql` — not cutover path. See [`obsolete-dev-scripts-retirement-policy.md`](../obsolete-dev-scripts-retirement-policy.md).

**Core ladder column manifest** (historical): [`replay-v1-scope-and-reset.md`](../replay-v1-scope-and-reset.md) — holy fill = ops simul.
