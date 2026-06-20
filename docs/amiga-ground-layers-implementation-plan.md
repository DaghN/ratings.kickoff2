# Amiga ground layers L0–L5 — implementation plan

**Status:** Slices 1–5 done (Jun 2026). Policy v2 locked. Slice 6+ not started.  
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
| **2** | `import-pristine` → **L1** full Access mirror SQL | All tables row-count vs `.mdb` | **Done** Jun 2026 |
| **3** | `import-prune` → **L2** + `prune_manifest.json` | L2 tables only; manifest lists drops | **Done** Jun 2026 |
| **4** | `import-witness` extract → **L3** + `apply_schema_ground()` | L3 rows + `import_manifest`; no L5 data | **Done** Jun 2026 |
| **5** | `apply-structure` → **L4** disposition dispatch | Homburg + one `pure_rr` smoke | **Done** Jun 2026 |
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

## Slice 2 — L1 pristine mirror (done)

**CLI:** `python -m scripts.amiga import-pristine [--mdb] [--out-dir]`

- Module: [`scripts/amiga/import_pristine.py`](../scripts/amiga/import_pristine.py)
- Output: `data/amiga/exports/pristine/L1_mirror.sql` + `pristine_manifest.json` (gitignored)
- Verify: `python -m scripts.amiga verify-pristine` (default on export)

**STOP:** 38 tables exported Jun 2026; `Scores` = 27,408 rows (raw Access, no supplements).

---

## Slice 3 — L2 prune (done)

**CLI:** `python -m scripts.amiga import-prune [--l1-dir] [--out-dir]`

- Module: [`scripts/amiga/import_prune.py`](../scripts/amiga/import_prune.py)
- Input: `data/amiga/exports/pristine/` (L1)
- Output: `data/amiga/exports/pruned/L2_pruned.sql` + `prune_manifest.json`
- Retain: `Scores`, `Tournament players`, `Countries`
- Verify: `python -m scripts.amiga verify-prune`

**STOP:** 3 tables retained, 35 pruned (Jun 2026); 28,033 rows kept.

---

## Slice 4 — L3 witness (done)

**CLI:** `python -m scripts.amiga import-witness [--recreate-ground]`

- Module: [`scripts/amiga/import_access.py`](../scripts/amiga/import_access.py) — `prepare_witness_from_access`, `persist_witness_to_mysql`, `import_witness`
- Verify: [`scripts/amiga/verify_witness.py`](../scripts/amiga/verify_witness.py) — `python -m scripts.amiga verify-witness`
- `import_all` = thin wrapper (L3 + L4 structure spec + full schema)
- `--recreate-ground` applies L3/L4 DDL only (no L5 derived bundle)
- Tier E `finish_override` DDL relocation deferred

**STOP:** 605 tournaments, 27,418 games, 473 players; L4/L5 empty until replay — verified Jun 2026; `prove` green.

---

## Slice 5 — L4 structure (done)

**CLI:** `python -m scripts.amiga apply-structure --from-disposition [--recreate-structure] [--tournament-id] [--limit] [--dry-run]`

- Module: [`scripts/amiga/apply_structure.py`](../scripts/amiga/apply_structure.py)
- Verify: [`scripts/amiga/verify_structure.py`](../scripts/amiga/verify_structure.py) — `python -m scripts.amiga verify-structure`
- Dispatches `disposition_register.json`: `pure_rr`, `pure_knockout`, `structure_spec` (active registry only); skips `pending_review`, `wc_deferred`, `no_games`
- Post-L3 path: games already in `amiga_games`; `apply_structure_spec_for_tournament()` added for registry specs
- Register fix: 6 stale `pure_rr`/`pure_knockout` rows → `pending_review` (Jun 2026)

**STOP:** Homburg id=137 fixtures + all games linked; pure_rr smoke id=1 — verified Jun 2026; `prove` green.

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
