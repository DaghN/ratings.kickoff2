# Milestones — staging + Steve handoff (May 2026)

**Index only.** Use the operational runbook:

**→ [`milestones-staging-cutover-packet.md`](milestones-staging-cutover-packet.md)** — WinSCP file list, Steve commands, expected SQL output, email template, smoke URLs.

Behavior authority: [`website-data-contract.md`](../website-data-contract.md) § **`player_milestones`**.

| Item | Status in repo |
|------|----------------|
| Schema SCH-011–013 | `schema/migrations/010–012_*.sql` |
| Catalog REP-014 | `ops/run_prepare.php seed-catalog` (legacy: `load_milestone_definitions.php`) |
| History REP-008 | `staging-scripts/run_player_milestones_rebuild.php` (+ `staging-sql/milestones/*.sql`) |
| `giant_slayer` fix | Built into rebuild script (chrono + surgical SQL); verify **31** holders |
| Website v0 | WinSCP list in packet § C |
| Post-game spec | Contract M1–M7 — **prod later** |

**Staging prerequisite:** REP-012/013 already on `kooldb` (May 2026).

*Update schema/replay registers when Steve’s screenshots match the packet’s expected counts.*
