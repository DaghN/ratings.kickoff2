## Slice checkpoint — E Swiss (first new format)

**Done:**
- `swiss` template promoted to `status: "implemented"` in `tournament_format.py`
- `scripts/amiga/swiss_pairing.py` — round count, round-1 by seed, round-N score-group pairing (avoid rematches when possible)
- `create_swiss_tournament()` — round 1 fixtures, cumulative `overall` league stage
- `generate_swiss_round()` — round N>1 from played games + standings
- CLI: `build-tournament create-swiss`, `generate-swiss-round`, `smoke-swiss`
- Standings: reuses fixture-backed `league` / `overall` path (no new `stage_type` DDL)
- Docs/README updated; `double_elimination` remains the only `planned` template

**Verified:**
- `python -m unittest scripts.amiga.test_swiss_pairing scripts.amiga.test_tournament_format scripts.amiga.test_tournament_structure -v` — 16 tests OK
- `python -m scripts.amiga build-tournament smoke-swiss --player-ids 1,2,3,4` — OK (4 games, 4 overall standings rows, rollback)
- `python -m scripts.amiga verify-tournament-formats` — 6 total (5 implemented, 1 planned)
- `python -m scripts.amiga import` — OK (Homburg structure unchanged)

**Not done (intentionally):**
- Double elimination implementation
- Buchholz tie-breaks, bye points, PHP live-ops mirror for Swiss
- Public UI / browser create-swiss

**Files touched:**
- `scripts/amiga/swiss_pairing.py` (new)
- `scripts/amiga/test_swiss_pairing.py` (new)
- `scripts/amiga/tournament_builder.py`
- `scripts/amiga/tournament_format.py`
- `scripts/amiga/test_tournament_format.py`
- `docs/amiga-tournament-format-vision.md`
- `docs/amiga-format-add-swiss-checklist.md`
- `scripts/amiga/README.md`

**Format backbone A–E complete.** Next optional work: double-elim (Slice E alt), more structure backfills (Athens LXI+), or staging export.

**Dagh:** say `pause` to choose next priority, or request double-elim / another backfill.
