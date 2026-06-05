# Ops-owned schema migrations — design & migration plan

**Status:** **Approved direction (Jun 2026).** Canonical DDL moves under `site/public_html/ops/sql/migrations/`; apply via PHP in ops.  
**Audience:** Dagh, Steve, Cursor agents.

**Related:** [`schema-register.md`](schema-register.md) · [`work-db-prepare.md`](../work-db-prepare.md) · [`ladder-ops-platform.md`](../ladder-ops-platform.md) §5–8 · [`site/public_html/ops/README.md`](../../site/public_html/ops/README.md)

---

## 1. Problem

Prepare pipeline (prod baseline → work → simul) requires **migrate work** before zero-derived and simul. Migrations define what PHP post-game and aggregates may assume (tables, indexes, dropped columns).

Today:

- **Canonical files:** `schema/migrations/*.sql` (repo root)
- **Apply:** `schema/apply_local.ps1` (Laragon PowerShell + `mysql.exe`)
- **Ops:** `run_prepare.php migrate-work` shells out to that script — **orchestrates but does not own** DDL
- **Deploy:** `ops/sql/migrations/` documented as WinSCP mirror — **was empty**; staging historically used server-only `staging-sql/`

That splits “platform” (synced `ops/`) from “foundational step 0” (root `schema/`). For legacy prod → prepared work → simul, migrations must live in the **same bundle** as prepare, not as “local only, then copy.”

---

## 2. Scope

### In `ops/sql/migrations/` (SCH DDL)

Numbered schema migrations from [`schema-register.md`](schema-register.md): `CREATE`/`ALTER`/`DROP`/`INDEX`, idempotent where possible.

### Not in migrations

| Artifact | Location |
|----------|----------|
| REP rebuild SQL (backfill from `ratedresults`) | `scripts/ladder/sql/` (optional future mirror: `ops/sql/rebuild/`) |
| Milestone catalog seed | `ops/data/milestones_definitions_seed.json` + `seed-catalog` |
| One-off surgical fixes | `scripts/oneoff/` (local); ops sim/replay on work/staging |

---

## 3. Optimal end state

```text
site/public_html/ops/
  sql/migrations/NNN_*.sql     ← canonical SCH files (synced)
  includes/ops_migrate.php     ← apply loop, UTC, DB guards
  run_prepare.php              ← migrate-work → ops migrator
  (future) dispatch.php        ← CMD=MigrateWork → same function
```

| Rule | Detail |
|------|--------|
| **Single tree** | No duplicate `schema/migrations` + manual copy |
| **Apply** | PHP `k2_ops_apply_migrations()` — mysqli, file order by name |
| **Semantics** | Re-apply all files each run; files must stay **idempotent** (today’s contract) |
| **Ledger** | Optional later (`ops_schema_migration`); not required for relocation |
| **Guards** | Same as ops mutate: never baseline; work targets only unless explicit dev exception |
| **Steve** | WinSCP `ops/` includes SQL; `php ops/run_prepare.php migrate-work` or thin `mysql` shell helper |

**Prod cutover:** same SQL files may run on prod per schema-register row — coordination, not automatic prepare on prod.

---

## 4. Current apply semantics (preserve)

- No Flyway-style version table today.
- `apply_local.ps1` runs every `*.sql` in sorted order with `SET time_zone = '+00:00'`.
- Safe after **refresh work** (clone prod) because migrations use `IF NOT EXISTS` / `information_schema` checks.

---

## 5. Phased implementation

| Phase | Work | Status |
|-------|------|--------|
| **A** | This doc + platform/README pointers | **Done** (Jun 2026) |
| **B** | `git mv` SQL → `ops/sql/migrations/`; stub `schema/migrations/README`; update register paths | **Done** (Jun 2026) |
| **C** | `ops_migrate.php`; `migrate-work` uses PHP; `apply_local.ps1` → ops path | **Done** (Jun 2026) |
| **D** | Catalog seed + `work-targets.ini` under `ops/data/`, `ops/config/` (WinSCP = `public_html` only) | **Done** (Jun 2026) |
| **D** | Steve handoff; retire new `staging-sql/` DDL; historical packets banner | Pending |
| **E** | `dispatch.php` `CMD=MigrateWork` | With dispatcher slice |

**Defer:** moving `scripts/ladder/sql` to `ops/sql/rebuild`; migration ledger table.

**Jun 2026 follow-up:** SCH-011 and SCH-014 `ALTER` blocks made idempotent so PHP `migrate-work` can re-apply on an already-migrated work DB (mysqli throws on duplicate column; mysql CLI masked this on some dev paths).

---

## 6. Risks

| Risk | Mitigation |
|------|------------|
| Two trees during transition | Atomic move (Phase B) |
| Non-idempotent new SCH | Review + register rule; add ledger before first non-idempotent file |
| Server without full repo | Migrate only needs `ops/` + PHP + DB creds (refresh still needs clone tooling) |
| MariaDB vs MySQL | Keep `information_schema` idiom in SQL files |

---

## 7. Decision record

1. **Canonical DDL:** `site/public_html/ops/sql/migrations/`
2. **Apply:** PHP in ops (shared by `migrate-work` and future `CMD=MigrateWork`)
3. **Idempotency:** re-apply-all until ledger justified
4. **Retire:** manual mirror copy step; new DDL not in `staging-sql/`

---

*Update the Phase table when slices land.*
