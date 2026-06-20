# Amiga ground layers L0–L5 — implementation plan

**Status:** Slice 1 done (Jun 2026). Policy **v2** locked + doc pass (slice 0b). Pipeline slices 2+ not started.  
**Policy:** [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md)

**Goal:** Separate scripts, DDL bundles, and export profiles for **L1 mirror → L2 prune → L3 witness → L4 structure → L5 product**; keep `prove` green throughout migration.

**DDL note:** Repo folders `sql/ground|structure|derived` = **L3|L4|L5** MySQL schema — not L1/L2 dumps. See policy §6.

---

## Principles while migrating

1. **No big-bang rewrite** — each slice leaves `python -m scripts.amiga prove` green (or documents a temporary orchestrator flag).
2. **Extract, don’t duplicate** — L3 logic stays in existing modules (`import_corrections.py`, `tournament_names.py`, …); new entrypoints call them.
3. **DDL split before export split** — community Pack A is useless if `apply_schema` still creates L5 tables.
4. **L4 reuses disposition track** — register, handlers, materialize, `fixtures.php`; wire into pipeline, don’t redesign.
5. **L2 = hard prune** — no sidecar tables; prune manifest only.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy v1 docs | Dagh OK — **superseded by v2** |
| **0b** | Policy v2 (L0–L5) + comprehensive doc pass | Dagh OK | **Done** Jun 2026 |
| **1** | DDL bundles `sql/ground|structure|derived` + `schema_bundles.py` | `prove` green | **Done** Jun 2026 |
| **2** | `import-pristine` → **L1** full Access mirror SQL | All tables row-count vs `.mdb` |
| **3** | `import-prune` → **L2** + `prune_manifest.json` | L2 tables only; manifest lists drops |
| **4** | `import-witness` extract → **L3** + `apply_schema_ground()` | L3 rows + `import_manifest`; no L5 data |
| **5** | `apply-structure` → **L4** disposition dispatch | Homburg + one `pure_rr` smoke |
| **6** | `prove` orchestrator: L3 → L4 → L5 → verify | Full verify suite green |
| **7** | Export packs Mirror / A / B / C | Staging smoke on Pack B |
| **8** | Docs closure on any drift | Agents cold-start |

---

## Slice 1 — DDL bundles (done)

Maps to **L3 / L4 / L5** schema in `ko2amiga_db`:

```text
sql/ground/       L3 — tournaments, amiga_players, amiga_games
sql/structure/    L4 — 005–009 templates, stages, fixtures, lifecycle
sql/derived/      L5 — ratings, standings, snapshots, matchups, …
```

- `apply_schema_ground()`, `apply_schema_structure()`, `apply_schema_derived()`
- `apply_schema()` = all three (current `prove` path)

**STOP:** `prove` green — verified Jun 2026.

---

## Slice 2 — L1 pristine mirror

**New:** `python -m scripts.amiga import-pristine [--mdb] [--out path]`

- Export **all** Access user tables → SQL (mechanical)
- No corrections, merges, supplements, synthetic `game_date` counter (document conventions in `pristine_manifest.json`)

**Output:** `data/amiga/exports/pristine/` (gitignored)

**STOP:** Row counts per table match `discover_access_schema.py` inventory.

---

## Slice 3 — L2 prune

**New:** `python -m scripts.amiga import-prune [--from pristine.sql]`

- Input: L1 dump
- Output: L2 dump — witness-candidate tables only
- Emit `prune_manifest.json` — `pruned_from_l1[]` with table, rows, reason
- **No sidecar** — dropped tables exist only in L1

Default drop list: policy §5 (`Tables`, `added_players`, `Rankings`, WC `* Tables`, …).

**STOP:** L2 contains `Scores` + `Tournament players`; manifest documents every omission.

---

## Slice 4 — L3 witness

**New:** `python -m scripts.amiga import-witness [--recreate-ground]`

- Read L2 (or `.mdb` until L2 exists)
- Body of current `import_all`: corrections, merges, supplements, games-first players, synthetic order
- `apply_schema_ground()` on recreate
- `import_manifest.json` (existing contract)
- Include Tier E table DDL in L3 bundle path (`finish_override` lives in `sql/derived/` today — **relocate to `sql/ground/` or witness extension in a later slice**)

**STOP:** L5 tables empty until replay; manifest complete.

---

## Slice 5 — L4 structure

**New:** `python -m scripts.amiga apply-structure [--from-disposition]`

- `apply_schema_structure()` if needed
- Disposition dispatch (`structure_spec`, `pure_rr`, `pure_knockout`; skip `pending_review`)
- Live path unchanged: `fixtures.php` writes L4 directly

**STOP:** Known spec tournament has fixtures + `fixture_id`.

---

## Slice 6 — Prove orchestrator

```text
import-witness --recreate-ground   # L3
apply-structure --from-disposition # L4 (--skip-structure dev only)
replay                             # L5
verify suite
```

**STOP:** Same verify counts as baseline.

---

## Slice 7 — Export packs

| Pack | Layers |
|------|--------|
| **Mirror** | L1 |
| **A — Ground** | L3 + manifests |
| **B — Structure** | L3 + L4 |
| **C — Product** | L3 + L4 + L5 (staging default) |

---

## Suggested execution order

**1** (done) → **4** (L3 witness extract) → **6** (orchestrator) → **2** (L1) → **3** (L2) → **5** (L4 wire) → **7** (exports) → **8**.

L1/L2 can parallel after slice 4; L3 extract unblocks `prove` split.

---

## When to propose doc/plan updates

- Transform spans layers (phase patch vs fixture)
- Disposition promotions change Pack B
- Tier E / curated claims added
- KOA interchange format agreed

Do **not** block code on doc rewrites unless G1–G11 conflict.

---

*Plan v2 Jun 2026 — aligns with policy L0–L5.*
