## Slice checkpoint — D Template extensibility

**Done:**
- §9 **Template extension contract** in `docs/amiga-tournament-format-vision.md` (stage factory, standings resolver, lifecycle `planned` → `implemented`)
- Planned seed templates `swiss` and `double_elimination` in `FORMAT_TEMPLATES` (`spec_json.status: "planned"`)
- `verify-tournament-formats` re-seeds templates and reports counts (6 total: 4 implemented, 2 planned)
- `list_seeded_templates()` + `verify_template_registry()` in `tournament_format.py`
- Swiss implementation checklist: `docs/amiga-format-add-swiss-checklist.md` (≤1 page)
- Unit tests in `scripts/amiga/test_tournament_format.py`

**Verified:**
- `python -m unittest scripts.amiga.test_tournament_format scripts.amiga.test_tournament_structure -v` — 12 tests OK
- `python -m scripts.amiga import` — OK; seeds 6 templates including planned stubs
- `python -m scripts.amiga verify-tournament-formats` — OK: `6 total (4 implemented, 2 planned)`

**Not done (intentionally):**
- Swiss / double-elim builders, standings branches, or live ops (Slice E — only when Dagh requests)
- No DDL changes

**Files touched:**
- `scripts/amiga/tournament_format.py`
- `scripts/amiga/test_tournament_format.py` (new)
- `docs/amiga-tournament-format-vision.md`
- `docs/amiga-format-add-swiss-checklist.md` (new)
- `scripts/amiga/README.md`

**Next slice:** E — pick one format (Swiss or double-elim), implement template + builder + standings + smoke tournament (only when Dagh says go).

**Dagh:** say `go on` for Slice E, or `pause` to choose format / priorities.
