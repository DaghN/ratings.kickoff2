# Amiga SQL layout

**Fresh install (Jun 2026):** `schema_bundles.py` applies DDL bundles to `ko2amiga_db`:

| Bundle folder | `apply_schema_*` | Pipeline layer |
|---------------|------------------|----------------|
| [`ground/`](ground/) | `apply_schema_ground()` | **L3** witness |
| [`structure/`](structure/) | `apply_schema_structure()` | **L4** structure |
| [`derived/`](derived/) | `apply_schema_derived()` | **L5** product |

**L1** (full Access mirror) and **L2** (pruned dump) are separate export pipeline artefacts — not these folders.

Flat files in this directory remain for archaeology. New DDL → appropriate bundle.

Policy: [`docs/amiga-ground-layers-policy.md`](../../../docs/amiga-ground-layers-policy.md) · stack intent: [`docs/amiga-ground-stack.md`](../../../docs/amiga-ground-stack.md)
