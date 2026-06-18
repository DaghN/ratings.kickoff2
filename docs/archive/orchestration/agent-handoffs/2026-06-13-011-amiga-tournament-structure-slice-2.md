# Amiga tournament structure — slice 2 handoff

**Date:** 2026-06-13  
**Slice:** 2 — Builders and curated specs aligned to `round_robin`/`knockout`  
**Prerequisite:** Slice 1 STOP GATE A (user confirmed via slice 2 request)

---

## Delivered

- [x] `tournament_builder.py` — `league`/`group` → `round_robin` for kitchen marathon, Swiss, group-knockout
- [x] `tournament_structure/homburg.py` — group stages → `round_robin`; 3rd-place → `knockout`
- [x] `tournament_structure/specs.py` — `STAGE_TYPE_*`, `normalize_stage_type`, `is_round_robin_group_stage`
- [x] `tournament_structure/build.py` + `verify.py` — use new helpers
- [x] `tournament_structure/link.py` — side-parity requirement documented (enforcement slice 3)
- [x] `site/public_html/amiga/ops/fixtures.php` — browser kitchen create uses `round_robin`
- [x] `test_tournament_structure.py` — spec test vocabulary updated

## Verification (local)

```text
python -m unittest scripts.amiga.test_tournament_structure -q  → 14 OK
python -m scripts.amiga structure verify --tournament "Homburg"  → OK 86 fixtures / 86 Access games
python -m scripts.amiga build-tournament create-kitchen-marathon … --dry-run  → OK (rolled back)
python -m scripts.amiga build-tournament create-group-knockout … --dry-run  → OK (rolled back)
```

## Files touched

| File | Change |
|------|--------|
| `scripts/amiga/tournament_builder.py` | Stage types |
| `scripts/amiga/tournament_structure/homburg.py` | Spec types |
| `scripts/amiga/tournament_structure/specs.py` | Constants + helpers |
| `scripts/amiga/tournament_structure/build.py` | RR group detection |
| `scripts/amiga/tournament_structure/verify.py` | RR group detection |
| `scripts/amiga/tournament_structure/link.py` | Side-parity doc |
| `site/public_html/amiga/ops/fixtures.php` | Browser create |
| `scripts/amiga/test_tournament_structure.py` | Test data |

## Next slice

**Slice 3** — `materialize_legacy_fixtures()` + Athens IV Cup pilot (`tournament_id=74`) — **STOP GATE B**.

Say **Do slice 3** when ready.
