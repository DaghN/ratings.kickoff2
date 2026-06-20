# Amiga ground layers L0–L3 — implementation plan

**Status:** Not started (Jun 2026).  
**Policy:** [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md)

**Goal:** Separate scripts, DDL bundles, and export profiles for L0 pristine → L1 witness → L2 structure → L3 derived; keep `prove` green throughout migration.

---

## Principles while migrating

1. **No big-bang rewrite** — each slice leaves `python -m scripts.amiga prove` green (or documents a temporary orchestrator flag).
2. **Extract, don’t duplicate** — L1 logic stays in existing modules (`import_corrections.py`, `tournament_names.py`, …); new entrypoints call them.
3. **DDL split before export split** — community pack is useless if `apply_schema` still creates L3 tables.
4. **L2 reuses disposition track** — register, handlers, materialize, `fixtures.php`; wire into pipeline, don’t redesign.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan (docs only) | Dagh OK |
| **1** | DDL bundles: `sql/ground/`, `sql/structure/`, `sql/derived/` + `apply_schema` refactor | `apply_schema` parity on fresh DB |
| **2** | `import-pristine` CLI → L0 SQL dump + row counts | Diff vs Access row counts |
| **3** | `import-witness` CLI (extract from `import_all`) + L1-only recreate | L1 tables + manifest; no L3 rows |
| **4** | `apply-structure` CLI (disposition dispatch + materialize path) | Homburg + one `pure_rr` smoke |
| **5** | `prove` orchestrator: `witness → structure → replay → verify` | Full verify suite green |
| **6** | Export profiles A/B/C in `export_ko2amiga_db.ps1` + `_import/README` | Staging import smoke on pack B |
| **7** | Docs closure: import-layer, data-contract, README, MEMORY | Agents can cold-start |

---

## Slice 1 — DDL bundles

**Today:** `import_access.apply_schema()` runs `001`–`013`, `019`, `024`, `026` in one loop.

**Target:**

```text
scripts/amiga/sql/ground/       tournaments, amiga_players, amiga_games (core)
scripts/amiga/sql/structure/    005–009 templates, stages, fixtures, lifecycle, finalize markers
scripts/amiga/sql/derived/      002 standings, 004 catalog_stats, 012/026 matchup, 013 generalstats, 024 snapshots, …
```

- `apply_schema_ground()`, `apply_schema_structure()`, `apply_schema_derived()` — callable independently.
- `import_access` / `prove` call all three until slice 5 splits orchestration.
- Drop order updated per bundle.

**STOP:** Fresh install + existing `prove` unchanged.

---

## Slice 2 — L0 pristine

**New:** `python -m scripts.amiga import-pristine [--mdb] [--out path]`

- Read Access; write SQL or load scratch DB **without**:
  - `import_corrections`, supplements, splits
  - `player_names` merges (or flag `--mechanical-only`)
- Optional: skip synthetic `game_date` counter (use raw tournament date only) — document in manifest as L0 convention.

**Output:** `data/amiga/exports/pristine/` or `site/public_html/amiga/_import_pristine/` (gitignored).

**STOP:** Game/tournament counts match Access; zero manifest overrides.

---

## Slice 3 — L1 witness

**New:** `python -m scripts.amiga import-witness [--recreate-ground]`

- Extract body of current `import_all` **minus** `apply_structure_spec` and **minus** derived truncate side-effects beyond ground FK deps.
- `apply_schema_ground()` only on recreate (structure/derived tables untouched or absent).
- Emit `import_manifest.json` (unchanged contract).

**Refactor:** `import_all` becomes thin wrapper or deprecated alias → `import-witness` + `apply-schema-derived` + …

**STOP:** After witness-only import, L3 tables empty or absent; website derived reads empty until replay.

---

## Slice 4 — L2 structure

**New:** `python -m scripts.amiga apply-structure [--tournament-id N] [--from-disposition]`

- Load L1 witness DB.
- `apply_schema_structure()` if needed.
- Run disposition dispatch (slice 10 from structure track — wire here):
  - `structure_spec` active specs
  - `pure_rr` / `pure_knockout` handlers
  - `pending_review` → skip + log
- Idempotent per tournament; manifest section `structure_applied`.

**Live path unchanged:** `fixtures.php` / `tournament_builder` write L2 directly on running events.

**STOP:** Tournament with known spec (Homburg) has fixtures + `fixture_id`; `verify-disposition-register` passes.

---

## Slice 5 — Prove orchestrator

**New flow:**

```text
import-witness --recreate-ground   # L1
apply-structure --from-disposition # L2 (optional flag --skip-structure for dev)
replay                             # L3
verify suite
```

- `prove` = above + nuclear option on derived for sign-off.
- Flag `--skip-structure` only for dev; not sign-off.

**STOP:** Same verify counts as pre-refactor baseline.

---

## Slice 6 — Export profiles

**Extend** `scripts/export_ko2amiga_db.ps1`:

| Profile | Tables |
|---------|--------|
| **A** | ground only + manifest pointer |
| **B** | ground + structure |
| **C** | current full dump (L1+L2+L3) |

Separate manifest JSON per profile or one manifest with `layers: ["witness","structure"]`.

**STOP:** Browser import of pack B on staging without L3 parts; profile pages work for historical events with structure.

---

## Slice 7 — Documentation

- [`amiga-import-layer.md`](amiga-import-layer.md) — L1 only; link L0/L2
- [`amiga-data-contract.md`](amiga-data-contract.md) — authority map + table register tagged L1/L2/L3
- [`scripts/amiga/README.md`](../scripts/amiga/README.md) — new commands
- [`amiga-staging-handoff.md`](amiga-staging-handoff.md) — pack C default; B for community handoff
- `PROJECT_MEMORY` + [`feature-log.md`](coordination/feature-log.md)

---

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| FK order across bundles | Ground ← structure ← derived; document in `apply_schema_*` |
| Long prove during migration | Keep monolith path behind `--legacy-prove` until slice 5 |
| L2 partial backfill | Pack B documents disposition version; `pending_review` count expected |
| PHP ops expects full schema | Staging stays pack C until community needs B |

---

## Suggested execution order after slice 0

**Slice 1** (DDL split) → **Slice 3** (witness extract) → **Slice 5** (orchestrator) → **Slice 2** (pristine) → **Slice 4** (structure wire) → **Slice 6** (exports) → **Slice 7**.

Pristine (slice 2) can parallel after slice 1; witness extract is higher priority for community path.

---

## When to propose doc/plan updates (standing offer to Dagh)

Agents should **propose** a doc or plan slice when:

- A new transform touches more than one layer (e.g. correction that mutates `phase` vs structure)
- Disposition register promotions change community pack B semantics
- Export or import gateway timeout forces pack splitting changes
- KOA/community agrees on an external interchange format (fold into policy §lobby)
- `prove` verify suite gains layer-specific gates

Do **not** block implementation slices on doc rewrites unless G1–G8 conflict.

---

*Plan Jun 2026 — modular ground layers track.*
