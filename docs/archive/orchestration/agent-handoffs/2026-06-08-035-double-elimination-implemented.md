## Slice checkpoint — Double elimination implemented

**Done:**
- `double_elim_bracket.py` — bracket round graphs for **4** and **8** players, slot resolution (`seed:N`, `winner:KEY`, `loser:KEY`)
- `create_double_elimination_tournament()` — winners/losers/grand_final stages, winners R1 fixtures
- `advance_double_elim()` — creates next round when current round fully played
- CLI: `create-double-elim`, `advance-double-elim`, `smoke-double-elim`
- Template `double_elimination` → `status: "implemented"`; **all 6 format templates now implemented**
- Standings: existing fixture `knockout` scope per tie (no new DDL)

**Verified:**
- `python -m unittest scripts.amiga.test_double_elim_bracket scripts.amiga.test_tournament_format -v` — OK
- `python -m scripts.amiga build-tournament smoke-double-elim --player-ids 1,2,3,4` — 6 games, 6 knockout scopes, rollback OK
- `python -m scripts.amiga verify-tournament-formats` — 6 total (6 implemented, 0 planned)

**Not done (intentionally):**
- Bracket reset if losers-bracket champion wins grand final
- Browser UI / public create flow
- Historical backfill via structure specs

**Files touched:**
- `scripts/amiga/double_elim_bracket.py` (new)
- `scripts/amiga/test_double_elim_bracket.py` (new)
- `scripts/amiga/tournament_builder.py`
- `scripts/amiga/tournament_format.py`
- `scripts/amiga/test_tournament_format.py`
- `docs/amiga-tournament-format-vision.md`
- `scripts/amiga/README.md`

**Usage:**

```powershell
python -m scripts.amiga build-tournament create-double-elim --name "Cup I" --event-date 2026-06-08 --player-ids 1,2,3,4,5,6,7,8
# Play winners R1 results, then:
python -m scripts.amiga build-tournament advance-double-elim --tournament-id N
```
