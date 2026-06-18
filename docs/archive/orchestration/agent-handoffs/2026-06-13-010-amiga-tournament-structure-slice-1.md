# Amiga tournament structure — slice 1 handoff

**Date:** 2026-06-13  
**Slice:** 1 — Stage type enum migration (`023`)  
**STOP GATE A:** **Waiting for user OK** before slice 2

---

## Delivered

- [x] Migration `scripts/amiga/sql/023_unify_stage_types.sql` — `league`/`group` → `round_robin`; `placement`/`other` → `knockout`; enum shrunk to `round_robin`|`knockout`
- [x] Fresh-install DDL `scripts/amiga/sql/006_tournament_fixtures.sql` enum updated
- [x] `VALID_STAGE_TYPES = {"round_robin", "knockout"}` in `tournament_fixtures.py`
- [x] `_fixture_scope` / `amiga_ops_fixture_standings_scope` — primary `round_robin`→`league`, `knockout`→`knockout`; legacy aliases retained for pre-migration reads
- [x] Unit tests: `FixtureScopeMappingTests` in `test_tournament_structure.py`
- [x] Applied locally on `ko2amiga_db`

## Verification (local)

```text
SELECT stage_type, COUNT(*) FROM tournament_stages GROUP BY stage_type;
  round_robin  8
  knockout     5

SHOW COLUMNS … stage_type → enum('round_robin','knockout')

python -m unittest scripts.amiga.test_tournament_structure -q  → 14 OK
python -m scripts.amiga fixtures create-stage --help  → {knockout,round_robin}
rebuild_standings_for_tournament(conn, 137)  → OK
```

**Note:** Plan listed `pytest` and `fixtures create --help`; local env uses `unittest`; CLI subcommand is `fixtures create-stage`.

## Pre-migration counts

| stage_type | count |
|------------|------:|
| group | 8 |
| knockout | 4 |
| placement | 1 |

## Files touched

| File | Change |
|------|--------|
| `scripts/amiga/sql/023_unify_stage_types.sql` | **New** |
| `scripts/amiga/sql/006_tournament_fixtures.sql` | Enum |
| `scripts/amiga/tournament_fixtures.py` | `VALID_STAGE_TYPES` |
| `scripts/amiga/tournament_standings.py` | `_fixture_scope` |
| `site/public_html/amiga/ops/includes/amiga_post_game_standings.php` | PHP parity |
| `scripts/amiga/test_tournament_structure.py` | Scope mapping tests |

**Not in slice 1 (slice 2):** `tournament_builder.py`, `tournament_structure/homburg.py`, `verify.py` — still use legacy type literals in specs/builders.

## STOP GATE A — user checks

1. **SQL** (staging after you apply `023`):

   ```powershell
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT stage_type, COUNT(*) FROM tournament_stages GROUP BY stage_type;"
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW COLUMNS FROM tournament_stages LIKE 'stage_type';"
   ```

   Expect only `round_robin` and `knockout`.

2. **Unit smoke:**

   ```powershell
   python -m unittest scripts.amiga.test_tournament_structure -q
   ```

3. **Optional:** Homburg (`tournament_id=137`) standings still rebuild without error after migrate.

Reply **OK for slice 2** when satisfied.

## Next slice

**Slice 2** — Builders + Homburg spec + structure verify aligned to `round_robin`/`knockout`.
