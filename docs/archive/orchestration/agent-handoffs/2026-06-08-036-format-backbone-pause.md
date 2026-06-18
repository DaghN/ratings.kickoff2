# Format backbone — pause checkpoint

**Date:** 2026-06-08  
**Status:** Paused at Dagh's request after slices A–E plus double elimination.

## What shipped

| Slice | Summary | Handoff |
|-------|---------|---------|
| A | Structure spec contract, import hook, suspicious-marathons audit | [030](2026-06-08-030-format-backbone-slice-a.md) |
| B | Homburg end-to-end (8 groups + KO, 86 fixtures, `fixture_id` on games) | [031](2026-06-08-031-format-backbone-slice-b.md) |
| C | Registry + `structure list` / `structure verify` + README how-to | [032](2026-06-08-032-format-backbone-slice-c.md) |
| D | Template extension contract; planned stubs for Swiss / double-elim | [033](2026-06-08-033-format-backbone-slice-d.md) |
| E | Swiss pairing + builder smoke | [034](2026-06-08-034-format-backbone-slice-e.md) |
| + | Double elimination (4/8 players) — all 6 format templates implemented | [035](2026-06-08-035-double-elimination-implemented.md) |

## Verification baseline (all green at pause)

```powershell
python -m unittest scripts.amiga.test_tournament_structure scripts.amiga.test_tournament_format scripts.amiga.test_swiss_pairing scripts.amiga.test_double_elim_bracket -v
python -m scripts.amiga structure verify --tournament "Homburg"
python -m scripts.amiga import
python -m scripts.amiga replay
python -m scripts.amiga verify-tournament-formats
python -m scripts.amiga build-tournament smoke-swiss --player-ids 1,2,3,4
python -m scripts.amiga build-tournament smoke-double-elim --player-ids 1,2,3,4
```

`verify-tournament-formats` reports **6 implemented, 0 planned**.

## Intentionally not done

- Athens LXI or other historical backfills (registry stub only)
- Browser UI for Swiss / double elimination
- Double-elim bracket reset grand final
- Swiss Buchholz / bye points; PHP standings mirror for new formats
- Staging export re-run after Homburg structure (schema unchanged; ground-truth rows changed locally)
- Browser spot-check: `tournament.php?id=137` (Homburg groups + bracket)

## Suggested resume options

| Option | Work |
|--------|------|
| **A** | `export_ko2amiga_db.ps1` + staging sync; verify Homburg UI on staging |
| **B** | Second structure backfill (e.g. Athens LXI) — evidence + spec + registry only |
| **C** | Browser ops for Swiss / double-elim create + advance |

## Key entry points

- Orchestration: [`docs/archive/orchestration/amiga-format-backbone-orchestration-prompt.md`](../amiga-format-backbone-orchestration-prompt.md)
- Structure specs: `scripts/amiga/tournament_structure/`
- Format vision: [`docs/amiga-tournament-format-vision.md`](../../amiga-tournament-format-vision.md) §9
- Swiss checklist: [`docs/amiga-format-add-swiss-checklist.md`](../../../amiga-format-add-swiss-checklist.md)

**Resume:** paste the starter in the orchestration prompt, say **`go on`** (or pick option A/B/C above).
