# Amiga standings scope unification — slice 0 handoff

**Date:** 2026-06-11  
**Slice:** 0 — Schema migration `020`  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

Migrate stored standings `scope_type` from `overall`/`group` to unified `league`; shrink enum to `league`|`knockout`; rename `catalog_stats.group_scopes` → `league_scopes`.

---

## Checklist

- [x] New migration `scripts/amiga/sql/020_unify_league_standings_scope.sql`
  - [x] Expand enum → `UPDATE` overall/group → `league`; placement → `knockout` if any
  - [x] Shrink enum to `league`, `knockout` only (default `league`)
  - [x] `CHANGE group_scopes league_scopes`
- [x] Update fresh-install `002_tournament_standings.sql`
- [x] Update fresh-install `004_tournament_catalog_stats.sql`
- [x] Apply locally on `ko2amiga_db`

### Verification

- [x] Only `league` and `knockout` scope types remain
- [x] Tournament 24: `league` + `''` (5 rows)
- [x] Tournament 22: `league` + `League Stage` (12 rows)
- [x] No `overall` / `group` rows
- [x] `league_scopes` column present on catalog stats

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/020_unify_league_standings_scope.sql` | **New** — enum migration + row updates + column rename |
| `scripts/amiga/sql/002_tournament_standings.sql` | Fresh-install enum `league`\|`knockout`, default `league` |
| `scripts/amiga/sql/004_tournament_catalog_stats.sql` | `league_scopes` column name |
| `scripts/amiga/README.md` | Migration `020` apply note |
| `PROJECT_MEMORY.md` | Recent log |
| `docs/coordination/feature-log.md` | L1 row for migration `020` |

---

## Pre/post SQL snapshots

**Pre-migration (`scope_type` counts):**

```
overall   3122
group     2422
knockout  2320
```

**Post-migration:**

```
scope_type  COUNT(*)
league      5544
knockout    2320
```

**Tournaments 22 / 24:**

```
scope_type  scope_key      COUNT(*)
league      League Stage   12        -- tournament 22
league                      5        -- tournament 24 (empty key)
knockout    …              2 each    -- placement ties unchanged
```

**Legacy rows:** `overall_or_group = 0`

**Catalog stats:**

```
league_scopes  int  NO  default 0
```

---

## STOP gate notes

**STOP GATE A** — user confirms SQL spot checks before slice 1.

Writers/readers still emit/read `overall`/`group`/`group_scopes` until slices 1–4. Rebuild after slice 1+ required for enum compliance on new writes.

---

## Next slice

**Slice 1** — Python `tournament_phases.py`, `tournament_standings.py`, catalog stats writer; `ScopeType.LEAGUE` only.
