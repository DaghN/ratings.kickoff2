# Coordination registers

Migration **backlog** for Steve/prod. Agents: update when **`docs/UPDATE_DOCS.md` Part B** applies (not on every “update docs”).

| Start here | [`feature-log.md`](feature-log.md) — what features exist and migration status |
| Behavior | [`../website-data-contract.md`](../website-data-contract.md) — tables, rebuild, post-game rules |
| Playbook | [`../UPDATE_DOCS.md`](../UPDATE_DOCS.md) |

| File | Purpose |
|------|---------|
| [schema-register.md](schema-register.md) | SCH DDL in `ops/sql/migrations/` |
| [ops-schema-migrations.md](ops-schema-migrations.md) | Why migrations live under ops + migration plan |
| [ops-dispatch.md](ops-dispatch.md) | `dispatch.php` CMD registry, exit codes, failure semantics |
| [staging-work-steve-handoff.md](staging-work-steve-handoff.md) | WinSCP + prepare + simul on `kooldb1`/`kooldb2` |
| [replay-register.md](replay-register.md) | `*_rebuild.sql` backfills + run log |
| [post-game-register.md](post-game-register.md) | **Retired** — pointer; prod C++ from contract |
| [records-post-game-exception.md](records-post-game-exception.md) | Hall of Fame / `generalstatstable` C++ exception only |
| [periodic-register.md](periodic-register.md) | Scheduled server jobs |
| [one-off-register.md](one-off-register.md) | Rare scripts (`scripts/oneoff/`) |
| [cutover-packet-template.md](cutover-packet-template.md) | Copy for Steve per release |
| [staging-scripts-inventory.md](staging-scripts-inventory.md) | Phase 0: legacy `staging-scripts/` → `ops/` migration backlog |

**Retired May 2026:** `cpp-snippets/` per-table packs — do not recreate.
