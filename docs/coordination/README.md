# Coordination registers

Migration **backlog** for Steve/prod. Agents: update when **`docs/UPDATE_DOCS.md` Part B** applies (not on every “update docs”).

| Start here | [`feature-log.md`](feature-log.md) — what features exist and migration status |
| Behavior | [`../website-data-contract.md`](../website-data-contract.md) — tables, rebuild, post-game rules |
| Playbook | [`../UPDATE_DOCS.md`](../UPDATE_DOCS.md) |
| **Ops completeness** | [`ops-completeness-charter.md`](ops-completeness-charter.md) — post-audit programme (AUD-004); ADR + DDR below |

| File | Purpose |
|------|---------|
| [ops-completeness-charter.md](ops-completeness-charter.md) | **Phase 0** — live/sim definition of done, four tracks, roadmap |
| [ops-orchestration-adr.md](ops-orchestration-adr.md) | Midnight `FinalizeUtcDay`, step order, simul interleaving |
| [ops-derived-data-registry.md](ops-derived-data-registry.md) | **DDR** — per-artifact inventory (trigger, CMD, gaps) |
| [ops-simul-runbook.md](ops-simul-runbook.md) | **Mode C** — prepare + `run_ops_sim.php`; what “simul complete” means |
| [steve-nightly-ops.md](steve-nightly-ops.md) | **Steve** — per-game + one nightly call (non-technical) |
| `site/.../ops/run_verify_ops_sim.php` | CLI verify after local `run_ops_sim` (before staging) |
| [schema-register.md](schema-register.md) | SCH DDL in `ops/sql/migrations/` |
| [ops-schema-migrations.md](ops-schema-migrations.md) | Why migrations live under ops + migration plan |
| [ops-dispatch.md](ops-dispatch.md) | `dispatch.php` CMD registry, exit codes, failure semantics |
| [staging-work-steve-brief.md](staging-work-steve-brief.md) | **Steve narrative** — five phases, ground vs derived, dispatcher |
| [staging-work-steve-handoff.md](staging-work-steve-handoff.md) | WinSCP + prepare + simul commands on `kooldb1`/`kooldb2` |
| [parity-audit-backlog.md](parity-audit-backlog.md) | **Parity audit (closed Jun 2026)** — AUD-001–006; triage + ops pipeline fix (**AUD-004**) next |
| [replay-register.md](replay-register.md) | `*_rebuild.sql` backfills + run log |
| [post-game-register.md](post-game-register.md) | **Retired** — pointer; prod C++ from contract |
| [records-post-game-exception.md](records-post-game-exception.md) | Hall of Fame / `generalstatstable` C++ exception only |
| [periodic-register.md](periodic-register.md) | Scheduled server jobs |
| [one-off-register.md](one-off-register.md) | Rare scripts (`scripts/oneoff/`) |
| [cutover-packet-template.md](cutover-packet-template.md) | Copy for Steve per release |
| [staging-scripts-inventory.md](staging-scripts-inventory.md) | Phase 0: legacy `staging-scripts/` → `ops/` migration backlog |

**Retired May 2026:** `cpp-snippets/` per-table packs — do not recreate.
