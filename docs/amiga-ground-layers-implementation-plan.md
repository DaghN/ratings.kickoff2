# Amiga ground layers L0–L5 — implementation plan

**Status:** Slices **1–11** done (Jun 2026). Strict stack **complete**; policy v3 + [`amiga-ground-stack.md`](amiga-ground-stack.md) locked.  
**Policy:** [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) · **stack intent:** [`amiga-ground-stack.md`](amiga-ground-stack.md)

**Goal:** **Strict inferential chain** — each layer reads only the previous layer’s output. Separate scripts, DDL bundles, and export profiles for **L1 → L2 → L3 → L4 → L5**; keep `prove` green throughout.

**DDL note:** Repo folders `sql/ground|structure|derived` = **L3|L4|L5** MySQL schema — not L1/L2 dumps. See policy §6.

---

## Principles while migrating

1. **No big-bang rewrite** — each slice leaves `python -m scripts.amiga prove` green (or documents a temporary orchestrator flag).
2. **Strict chain (G12)** — L3 reads **L2 only**; no `koatd.mdb` in `import-witness` / `prove` after slice 10. See [`amiga-ground-stack.md`](amiga-ground-stack.md).
3. **Extract, don’t duplicate** — L3 transform logic stays in existing modules; new entrypoints call them. **Prune rules live only in L2** (`import_prune.py`).
4. **DDL split before export split** — community Pack A is useless if `apply_schema` still creates L5 tables.
5. **L4 reuses disposition track** — register, handlers, materialize, `fixtures.php`; wire into pipeline, don’t redesign.
6. **L2 = hard prune + identity extract** — `witness_player_identity` from L1 `Rankings`; drop `Countries`; no full `Rankings` grid in L2.

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
| **6** | `prove` orchestrator: L3 → L4 → L5 → verify | Full verify suite green | **Done** Jun 2026 |
| **7** | Export packs Mirror / A / B / C | Staging smoke on Pack B | **Done** Jun 2026 |
| **8** | Docs closure on any drift | Agents cold-start | **Done** Jun 2026 |
| **9** | L2 `witness_player_identity`; drop `Countries` retain; `extracted_from_l1` in manifest | `verify-prune` green | **Done** Jun 2026 |
| **10** | L3 from L2 only (`prepare_witness_from_l2`); `prove` L1→L2→L3→L4→L5; remove `.mdb` from witness path | `prove` green; no pyodbc in L3 | **Done** Jun 2026 |
| **11** | L2→L3 boundary verify (`verify-l2-l3`) + docs/code closure | `prove` green; manifest L2 lineage | **Done** Jun 2026 |

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

## Slice 9 — L2 realign (done)

**CLI:** `python -m scripts.amiga import-prune` · `verify-prune`

- Module: [`scripts/amiga/import_prune.py`](../scripts/amiga/import_prune.py)
- Retain: `Scores`, `Tournament players`
- Extract L1 `Rankings` → **`witness_player_identity`** (`player`, `country`)
- Drop: `Countries`, full `Rankings` grid, all other L1 tables
- Manifest: `extracted_from_l1` + `pruned_from_l1` with Rankings note
- Tests: [`scripts/amiga/test_import_prune.py`](../scripts/amiga/test_import_prune.py)

**STOP:** 2 access tables + 465 identity rows; 36 pruned; `verify-prune` green — Jun 2026.

---

## Slice 10 — L3 from L2 only (done)

**CLI:** `python -m scripts.amiga import-witness [--l2-dir] [--recreate-ground]` · `prove` (full L1→L5 chain)

- Module: [`scripts/amiga/import_l2_witness.py`](../scripts/amiga/import_l2_witness.py) — parses `L2_pruned.sql` (`Scores`, `Tournament players`, `witness_player_identity`)
- `prepare_witness_from_l2(l2_dir)` in [`import_access.py`](../scripts/amiga/import_access.py); `prepare_witness_from_access(mdb)` retained for legacy audit only
- `import_witness` / `import_witness_nuclear` / `prove` default to `data/amiga/exports/pruned/` — no `.mdb` on witness path
- `build_manifest(source=…)` records L2 layer metadata; `--l1-dir` / `--l2-dir` / `--skip-l1-l2` on `prove`
- Tests: [`scripts/amiga/test_import_l2_witness.py`](../scripts/amiga/test_import_l2_witness.py)

**STOP:** 605 tournaments, 27,418 games, 473 players; full `prove` green (~6 min) — Jun 2026.

---

## Slice 11 — L2→L3 boundary verify (done)

**CLI:** `python -m scripts.amiga verify-l2-l3 [--l2-dir] [--manifest]`

- Module: [`scripts/amiga/verify_l2_l3_boundary.py`](../scripts/amiga/verify_l2_l3_boundary.py)
- Gates: manifest `source.layer == L2`; L2 path matches; re-prepare parity (`prepare_witness_from_l2` vs manifest stats vs MySQL); nationality join oracle
- Wired into `prove` verify suite; `verify-import-manifest` rejects non-L2 lineage
- Tests: [`scripts/amiga/test_verify_l2_l3_boundary.py`](../scripts/amiga/test_verify_l2_l3_boundary.py)

**STOP:** 27,408 L2 scores + 10 supplements → 27,418 games; 465/473 players with L2 identity country — Jun 2026.

---

## Slice 3 — L2 prune (historical — superseded by slice 9)

**CLI:** `python -m scripts.amiga import-prune [--l1-dir] [--out-dir]`

- Module: [`scripts/amiga/import_prune.py`](../scripts/amiga/import_prune.py)
- Input: `data/amiga/exports/pristine/` (L1)
- Output: `data/amiga/exports/pruned/L2_pruned.sql` + `prune_manifest.json`
- **Shipped Jun 2026:** retained `Scores`, `Tournament players`, `Countries` (3 tables)
- **Target (slice 9):** retain `Scores`, `Tournament players`; emit **`witness_player_identity`** from L1 `Rankings`; **drop `Countries`**
- Verify: `python -m scripts.amiga verify-prune`

**STOP (Jun 2026, pre–slice 9):** 3 tables retained (`Scores`, `Tournament players`, `Countries`), 35 pruned. Superseded by slice 9 (`witness_player_identity`, no `Countries`).

---

## Slice 4 — L3 witness (done)

**CLI:** `python -m scripts.amiga import-witness [--l2-dir] [--recreate-ground]`

- Module: [`scripts/amiga/import_access.py`](../scripts/amiga/import_access.py) — `prepare_witness_from_l2` (slice 10); `prepare_witness_from_access` legacy audit only
- Verify: [`scripts/amiga/verify_witness.py`](../scripts/amiga/verify_witness.py), [`scripts/amiga/verify_l2_l3_boundary.py`](../scripts/amiga/verify_l2_l3_boundary.py)
- `import_all` / `run` delegate to L3 witness + L4 disposition (no inline `apply_structure_spec`)
- `--recreate-ground` applies L3/L4 DDL only (no L5 derived bundle)
- Tier E `finish_override` DDL in `sql/ground/002` (L3 curated; replay preserves rows)

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

## Slice 6 — Prove orchestrator (done)

**CLI:** `python -m scripts.amiga prove [--l1-dir] [--l2-dir] [--skip-l1-l2] [--skip-structure]`

```text
import-pristine                    # L0 → L1
import-prune                       # L1 → L2
import-witness --recreate-ground   # L2 → L3
apply-structure --from-disposition # L4
replay                             # L5
verify suite
```

- `prove.py` orchestrates full strict chain (slice 10); `--skip-l1-l2` reuses existing L2
- Helpers: `import_witness_nuclear()`, `import_witness_reload()` read L2 via `prepare_witness_from_l2`

**STOP (Jun 2026):** 27,418 games, 4,535 snapshots, 210,960 at-event matchups — full L1→L5 chain verified.

---

## Slice 7 — Export packs (done)

**CLI:** `python -m scripts.amiga export-pack {mirror|ground|structure|product|all}`

- Module: [`scripts/amiga/export_packs.py`](../scripts/amiga/export_packs.py)
- Verify: `python -m scripts.amiga verify-export-pack {pack}`
- Output: `data/amiga/exports/packs/{pack}/` + `pack_manifest.json`
- **Mirror** — L1 `L1_mirror.sql` (from `import-pristine`)
- **ground** (Pack A) — L3 tables + manifests; no L5
- **structure** (Pack B) — L3 + L4 + disposition register
- **product** (Pack C) — L3 + L4 + L5 full derived
- Staging chunked import remains `scripts/export_ko2amiga_db.ps1` (browser-friendly parts)

**STOP:** `verify-export-pack structure` — fixtures + Homburg/pure_rr linked; all packs verified Jun 2026.

---

## Slice 8 — Docs closure (done)

Cross-doc pass after slices 1–7 — agents cold-start from policy + this plan + [`scripts/amiga/README.md`](../scripts/amiga/README.md).

**Updated:** `amiga-ground-layers-policy.md` §8–§10, `amiga-import-layer.md`, `amiga-data-contract.md`, `amiga-tournament-structure-policy.md` §5, `amiga-tournament-structure-review-queue.md`, disposition review starter, `amiga-staging-handoff.md`, `PROJECT_MEMORY.md`.

**Removed stale refs:** “slice 10 dispatch”, monolithic `prove`, L1/L2 “planned”, tier-D-only import structure hook.

---

## Suggested execution order

**Historical (slices 1–8):** 1 → 4 → 6 → 2 → 3 → 5 → 7 → 8 (L1/L2 added after L3 extract — created the L0→L3 gap).

**Forward (strict stack):** **complete** (slices 9–11). Maintenance = disposition review + community witness content.

---

## When to propose doc/plan updates

- Transform spans layers (phase patch vs fixture)
- Disposition promotions change Pack B
- Tier E / curated claims added
- KOA interchange format agreed

Do **not** block code on doc rewrites unless G1–G11 conflict.

---

*Plan v4 Jun 2026 — strict L0→L5 chain complete (slices 1–11).*
