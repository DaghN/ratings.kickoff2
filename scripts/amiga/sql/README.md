# Amiga SQL layout

**Fresh install (Jun 2026):** `schema_bundles.py` applies DDL bundles to `ko2amiga_db`:

| Bundle folder | `apply_schema_*` | Pipeline layer |
|---------------|------------------|----------------|
| [`ground/`](ground/) | `apply_schema_ground()` | **L3** witness |
| [`structure/`](structure/) | `apply_schema_structure()` | **L4** structure |
| [`derived/`](derived/) | `apply_schema_derived()` | **L5** product |

**L1** (full Access mirror) and **L2** (pruned dump) are separate export pipeline artefacts — not these folders.

Only two flat files remain here: `002_tournament_standings.sql` and `003_knockout_scope.sql`, read by the Access import (`import_access.py`, kept as a repair tool). The other flat copies were removed on 2026-11-23 (git tag `archive/before-cleanup-2026-11-23`). **New DDL → ground/structure/derived bundle only** — applied by simul (and by `prove` on the frozen copy) through `apply_schema_*` in `schema_bundles.py`.

Policy: [`docs/amiga-ground-layers-policy.md`](../../../docs/amiga-ground-layers-policy.md) · stack intent: [`docs/amiga-ground-stack.md`](../../../docs/amiga-ground-stack.md)
