# Amiga standings scope unification — slice 5 handoff

**Date:** 2026-06-11  
**Slice:** 5 — Parity and verify extensions  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

Tooling speaks `league`; guards prevent regression.

---

## Checklist

- [x] `standings_parity.py` — module docstring clarifies CLI `overall`/`group` are Access labels; `_derived_scope_type()` maps to `league` for MySQL/derived paths
- [x] `verify_player_participation.py` — SQL uses `league` + `''`; variable renamed `missing_primary_league_participation`
- [x] `tournament_catalog_stats.py` — already uses `league_scopes` (slice 1); rebuild verified
- [x] `test_tournament_honours.py` — fixture `scope_type: league` (stored vocabulary)
- [x] Grep product code `scope_type.*overall|group` — **zero hits** in `site/public_html`; Python only `standings_parity.py` (intentional CLI labels)

### Verification

- [x] `python -m scripts.amiga catalog-stats-rebuild` — 603 tournaments
- [x] `python -m scripts.amiga verify-player-participation` — OK (4517 participation rows)
- [x] `python -m unittest scripts.amiga.test_tournament_honours` — 8 OK

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/standings_parity.py` | Module docstring (CLI vs stored scope) |
| `scripts/amiga/verify_player_participation.py` | Rename guard variable |
| `scripts/amiga/test_tournament_honours.py` | `league` fixture + test name |
| `scripts/amiga/tournament_honours.py` | Docstring wording |
| `docs/amiga-standings-scope-implementation-plan.md` | Slice 5 checkboxes |

---

## Grep notes (acceptable remaining hits)

| Location | Why OK |
|----------|--------|
| `standings_parity.py` | Access parity CLI labels; derived reads use `league` |
| `amiga_tournament_lib.php` URL builder | Legacy `?scope=overall\|group` → canonical `league` (slice 4) |
| `tournament_format.py` / `tournament_builder.py` | Organizer `stage_key` `overall`, not standings `scope_type` |
| `scripts/amiga/sql/*.sql` | Migration history archives |

---

## Next slice

**Slice 6** — Full `python -m scripts.amiga replay` + full verify suite (`verify-chronology`, `verify-rating-events`, `verify-player-participation`, `verify-player-matchups`). Optional `standings-parity --sweep`.
