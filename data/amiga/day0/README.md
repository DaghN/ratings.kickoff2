# Day 0 L3 witness archive

**Version:** `day0-2026-07-08` — sealed from local `ko2amiga_db` (Jul 2026).

**Policy:** [`docs/amiga-modern-ground-platform.md`](../../docs/amiga-modern-ground-platform.md) section 7.

## Contents

L3 witness tables only — **no L4 structure, no L5 derived, no video.**

| Table | Rows (seal) |
|-------|-------------|
| tournaments | 605 |
| amiga_players | 469 |
| amiga_games | 27,418 |
| amiga_tournament_finish_override | 0 |
| tournament_format_templates | 6 |

`ko2amiga_db` remains the **parity oracle** (frozen) until P-1 passes. **`ko2amiga_work`** is seeded from this bundle only (W-1).

## Files

- `manifest.json` — version, counts, `sql_parts` load order
- `day0_01_schema.sql` — DDL for L3 tables
- `day0_02` … `day0_05` — small table data
- `day0_06` … `day0_11` — `amiga_games` chunks (5k rows)
- `manifests/` — witness provenance (`import_manifest.json`, `name_merges.json`)

## Reseal

```powershell
powershell -ExecutionPolicy Bypass -File scripts\export_amiga_day0.ps1
# or: python -m scripts.amiga seal-day0
```

Reseal only for deliberate day-0 version bumps (archaeology) — not for daily ops.