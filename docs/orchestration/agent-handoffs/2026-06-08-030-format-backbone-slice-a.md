## Slice checkpoint — A Contract & module skeleton

**Done:**
- Added `scripts/amiga/tournament_structure/` package with `StructureSpec`, `StageSpec`, `FixtureSpec`, `GroupRosterSpec` datatypes and `parse_structure_spec()`
- `apply_structure_spec()` import hook (no-op; empty registry) called after scores prepared, before games INSERT
- Manifest field `transforms.structure_specs` (empty array on import)
- CLI `python -m scripts.amiga audit-suspicious-marathons` → JSON report (NULL phases + uneven/non-RR game counts)
- Unit tests in `scripts/amiga/test_tournament_structure.py`
- Short § in `docs/amiga-import-layer.md`

**Verified:**
- `python -m unittest scripts.amiga.test_tournament_structure -v` — 7 tests OK
- `python -m scripts.amiga audit-suspicious-marathons` — 411 tournaments; **Homburg** listed (33 players, 86 games, all Phase NULL, uneven counts)
- `python -m scripts.amiga import` — OK (603 tournaments, 27418 games)
- `python -m scripts.amiga verify-import-manifest` — OK

**Not done (intentionally):**
- Homburg backfill (Slice B)
- Registry entries, stage/fixture creation, `fixture_id` linking
- Swiss / double-elim templates

**Files touched:**
- `scripts/amiga/tournament_structure/` (new package)
- `scripts/amiga/test_tournament_structure.py`
- `scripts/amiga/import_access.py`
- `scripts/amiga/import_manifest.py`
- `scripts/amiga/__main__.py`
- `docs/amiga-import-layer.md`

**Next slice:** B — encode Homburg `StructureSpec` from forum t=7711 and wire end-to-end stages/fixtures + `fixture_id` on games.

**Dagh:** say `go on` to continue.
