## Slice checkpoint — C Generalize backfill API

**Done:**
- Central `registry.py` with `RegistryEntry` (status `active` | `stub`); import applies **active** only
- `verify.py` — pre-import validation against Access (fixture count, player rosters, virtual game linking)
- CLI `python -m scripts.amiga structure list` / `structure verify --tournament "…"`
- `audit-suspicious-marathons` rows include `structure_spec_status` (`null` | `stub` | `active`)
- `group_knockout` template `spec_json` extended with `knockout_rounds` keys
- Stub spec **Athens LXI** (fails verify gracefully)
- README § “How to add a structure spec” in `scripts/amiga/README.md`

**Verified:**
- `python -m unittest scripts.amiga.test_tournament_structure -v` — 10 tests OK
- `python -m scripts.amiga structure list` — Homburg active (86/86), Athens LXI stub
- `python -m scripts.amiga structure verify --tournament "Homburg"` — OK
- `python -m scripts.amiga structure verify --tournament "Athens LXI"` — FAIL (expected)
- `python -m scripts.amiga import` — Homburg still applies; stub skipped
- Audit: Homburg `structure_spec_status=active`, Athens LXI `stub`

**Not done (intentionally):**
- Slice D template extensibility (Swiss / double-elim stubs)
- Athens LXI or other tournament backfill data

**Files touched:**
- `scripts/amiga/tournament_structure/registry.py`
- `scripts/amiga/tournament_structure/verify.py` (new)
- `scripts/amiga/tournament_structure/apply.py`
- `scripts/amiga/tournament_structure/audit.py`
- `scripts/amiga/tournament_format.py`
- `scripts/amiga/__main__.py`
- `scripts/amiga/test_tournament_structure.py`
- `scripts/amiga/README.md`
- `docs/amiga-import-layer.md`

**Next slice:** D — document template extension contract; seed `swiss` / `double_elimination` stub templates.

**Dagh:** say `go on` to continue.
