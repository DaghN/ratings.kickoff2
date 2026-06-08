## Slice checkpoint — B Homburg through the backbone

**Done:**
- `scripts/amiga/tournament_structure/homburg.py` — full `HOMEBURG_SPEC` from forum t=7711 (8 groups A–H, KO rounds with two-legged ties + replays)
- `build.py` — creates stages, stage players, 86 fixtures; sets `format_template_id=group_knockout`, `has_league=1`, `has_cup=1`, `format_overrides` with evidence URL
- `link.py` — deterministic game→fixture linking by player pair + import chronology
- Import hook applies structure and writes `fixture_id` on `amiga_games` INSERT
- Manifest `transforms.structure_specs` records Homburg with `fixture_count=86`, `games_linked=86`

**Verified:**
- `python -m unittest scripts.amiga.test_tournament_structure -v` — 7 tests OK
- `python -m scripts.amiga import` — Built Homburg: 13 stages, 86 fixtures, 86 games linked
- `python -m scripts.amiga replay` — OK (27418 games)
- `python -m scripts.amiga verify-chronology` — OK
- `python -m scripts.amiga verify-import-manifest` — OK
- MySQL spot-check (id=137): 86 games, 0 orphan `fixture_id`, 8 group standings scopes + 16 knockout ties

**Not done (intentionally):**
- Generalized registry CLI (`structure verify` / `structure list`) — Slice C
- Swiss / double-elim stubs — Slice D
- Other tournament backfills

**Files touched:**
- `scripts/amiga/tournament_structure/homburg.py` (new)
- `scripts/amiga/tournament_structure/build.py` (new)
- `scripts/amiga/tournament_structure/link.py` (new)
- `scripts/amiga/tournament_structure/apply.py`
- `scripts/amiga/tournament_structure/registry.py`
- `scripts/amiga/import_access.py`
- `scripts/amiga/test_tournament_structure.py`

**Browser check (local):** `http://ratingskickoff.test/amiga/tournament.php?id=137` — expect group tabs A–H + knockout bracket (not a single overall table).

**Staging:** schema unchanged — re-import + replay locally, then `scripts/export_ko2amiga_db.ps1` when ready to ship.

**Next slice:** C — registry file + `structure verify` / `structure list` CLI so tournament #2 is data-only.

**Dagh:** say `go on` to continue.
