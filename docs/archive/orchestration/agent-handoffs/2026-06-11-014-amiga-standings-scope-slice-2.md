# Amiga standings scope unification — slice 2 handoff

**Date:** 2026-06-11  
**Slice:** 2 — PHP post-game standings parity  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

PHP standings writers emit `league` for all points tables; catalog stats refresh uses `league_scopes`; ops labels/SQL aligned.

---

## Checklist

- [x] `amiga_tournament_phases.php` — `AMIGA_SCOPE_TYPE_LEAGUE`; `parse_phase` + `is_league_scope`
- [x] `amiga_post_game_standings.php` — fixture scopes, synthetic aggregate, catalog stats SQL
- [x] `fixtures.php` — kitchen marathon stage/phase label "League table"; standings read `league` + `''`
- [x] `process_completed_game.php` — spot-check queries use `league`

### Verification

- [x] PHP `-l` syntax on all touched files
- [x] `php site/public_html/amiga/ops/run_process_game.php verify` — OK (London XXIII + WC spot checks)
- [x] PHP compute + rebuild tournament 24 → 5 rows, all `league` + `''`

---

## Files changed

| File | Change |
|------|--------|
| `site/public_html/amiga/ops/includes/amiga_tournament_phases.php` | `LEAGUE` \| `KNOCKOUT` only |
| `site/public_html/amiga/ops/includes/amiga_post_game_standings.php` | Full writer parity + `league_scopes` |
| `site/public_html/amiga/ops/fixtures.php` | Labels + standings SELECT |
| `site/public_html/amiga/ops/modules/process_completed_game.php` | Verify spot checks |

---

## Verification output

```
php -l → No syntax errors (4 files)

php run_process_game.php verify:
  verify OK (counts + standings spot-checks)

PHP compute tournament 24:
  php_compute_rows=5 league_empty=5
  OK: PHP rebuild tournament 24
```

---

## STOP gate notes

None for slice 2.

---

## Known limitations / next slice

- `participation_placement.php` / `amiga_post_game_participation.php` still filter `overall`/`group` — **slice 3**.
- Public `tournament.php` still uses `?scope=overall` — **slice 4**.
- `amiga_tournament_lib.php` still reads `group_scopes` column alias — **slice 4/5**.

**Next:** Slice 3 — `resolve_primary_league_standings()` + honours Tier B/C.
