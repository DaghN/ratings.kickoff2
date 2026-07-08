# Amiga World Cup flag — implementation plan

> **Historical execution record (Jul 2026):** Feature **shipped** via **`prove`** on frozen **`ko2amiga_db`**. Steps below are archaeology — **do not re-run for new work**. Forward: **`simul`** on **`ko2amiga_work`** → **`export_ko2amiga_work.ps1`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **WC-F1–WC-F8 shipped (Jul 2026)** — `prove` green on local `ko2amiga_db`.  
**Policy:** [`amiga-world-cup-flag-policy.md`](amiga-world-cup-flag-policy.md)  
**Parent:** [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-ground-stack.md`](amiga-ground-stack.md)

**Execution:** Slices in order. STOP after DDL + import until `python -m scripts.amiga prove` exits 0. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

---

## Locked decisions (do not re-open)

See policy **WC1–WC14**. Compressed:

- **`is_world_cup`** on `tournaments` (L3) + snapshot/participation copy
- **Import:** derive from `^World Cup\s+\S` on canonical name, **last** in persist
- **No** manifest overrides, **no** index v1
- **Live create:** checkbox ⟺ name regex; **independent** of format template
- **Repair:** re-import + prove — no ad-hoc SQL

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **WC-F1** | DDL: `tournaments.is_world_cup` in `sql/ground/` (+ mirror); snapshot/participation column in `sql/derived/` | `apply_schema_*` on local DB |
| **WC-F2** | Import persist: set flag in `import_access.py` after catalog transforms; shared `is_world_cup_tournament()` | Spot-check ~17 WCs true |
| **WC-F3** | `prove` verify: all imported tournaments flag == name rule | `prove` exits 0 |
| **WC-F4** | Writers copy flag to snapshot rows (`snapshot_row.py`, `player_tournament_participation.py`, PHP finalize) | Unit tests |
| **WC-F5** | Read path: `amiga_tournament_is_world_cup()` reads column; remove SQL `REGEXP` from player/realm games filters | Grep clean for hot `REGEXP` WC |
| **WC-F6** | Live ops: checkbox on create + server-side WC11 validation | Browser smoke create reject/accept |
| **WC-F7** | Python read paths: finalize, slice, WC stats, community facts use DB flag | `prove` + spot probes |
| **WC-F8** | Policy supersede lines (WCH13, M1, data-contract register) + UPDATE_DOCS Part A/B | Docs only |

---

## Reference files

| Area | File |
|------|------|
| Import persist | `scripts/amiga/import_access.py` |
| Name rule (Python) | `scripts/amiga/tournament_honours.py` — `is_world_cup_tournament()` |
| Name rule (PHP) | `site/public_html/includes/amiga_tournament_lib.php` |
| Format flags precedent | `scripts/amiga/tournament_format.py`, `sql/structure/005_tournament_formats.sql` |
| Snapshot columns | `scripts/amiga/snapshot_row.py`, `sql/derived/024_player_snapshots.sql` |
| Player games REGEXP | `site/public_html/includes/amiga_player_games_lib.php` |
| Organizer create | `site/public_html/amiga/ops/fixtures.php` |
| Prove | `python -m scripts.amiga prove` |

---

## Verification commands

```powershell
python -m scripts.amiga prove
python -m scripts.amiga verify-tournament-formats
```

Optional spot-check:

```sql
SELECT id, name, is_world_cup FROM tournaments WHERE is_world_cup = 1 ORDER BY chrono;
SELECT COUNT(*) FROM tournaments WHERE is_world_cup <> (name REGEXP '^World Cup[[:space:]]+[^[:space:]]');
```