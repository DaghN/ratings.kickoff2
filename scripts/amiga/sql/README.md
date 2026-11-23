# Amiga SQL layout

**Fresh install (Jun 2026):** `schema_bundles.py` applies DDL bundles to `ko2amiga_db`:

| Bundle folder | `apply_schema_*` | Pipeline layer |
|---------------|------------------|----------------|
| [`ground/`](ground/) | `apply_schema_ground()` | **L3** witness |
| [`structure/`](structure/) | `apply_schema_structure()` | **L4** structure |
| [`derived/`](derived/) | `apply_schema_derived()` | **L5** product |

**L1** (full Access mirror) and **L2** (pruned dump) are separate export pipeline artefacts — not these folders.

Flat files in this directory remain for archaeology and are **not applied**; each starts with a line naming the file in use (12 of them differ from their twin in a bundle folder). Only `002_tournament_standings.sql` and `003_knockout_scope.sql` are still read, by the frozen legacy import. **New DDL → ground/structure/derived bundle only** — applied by `python -m scripts.amiga prove` (`apply_schema_*` in `schema_bundles.py`).

Policy: [`docs/amiga-ground-layers-policy.md`](../../../docs/amiga-ground-layers-policy.md) · stack intent: [`docs/amiga-ground-stack.md`](../../../docs/amiga-ground-stack.md)
