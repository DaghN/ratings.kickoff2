# Amiga tournament structure — slice 3 handoff

> **SUPERSEDED (policy v2 Jun 2026)** — NULL⇒KO heuristic and Athens IV pilot cancelled. See [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](2026-06-13-013-amiga-tournament-structure-restart-handoff.md) and slice **3b** in the implementation plan. Dematerialize id=74 if this pilot was applied.

**Date:** 2026-06-13  
**Slice:** 3 — Legacy materialize (pilot)  
**STOP GATE B:** **Waiting for user OK** before slice 5 bulk backfill

---

## Delivered

- [x] `scripts/amiga/tournament_structure/materialize_legacy.py` — `materialize_legacy_fixtures()`
  - One fixture per game; copies `player_a_id` / `player_b_id` from game (side parity by construction)
  - NULL phase: full round-robin schedule → `round_robin` overall; else → single `knockout` stage (Athens IV Cup pattern)
  - Labeled phases: bucket via `parse_phase()` → `round_robin` or `knockout` stage per scope
  - Refuses generated/curated tournaments; `--replace` clears existing stages first
- [x] CLI: `python -m scripts.amiga tournament-structure materialize --tournament-id N [--dry-run] [--replace]`
- [x] CLI: `python -m scripts.amiga standings-rebuild --tournament-id N` (single-tournament helper)
- [x] Unit tests: `MaterializeLegacyTests` in `test_tournament_structure.py`
- [x] Pilot: **Athens IV Cup** (`tournament_id=74`) materialized + standings rebuilt

**Deferred:** replay import hook (plan: explicit subcommand first — slice 5+ may wire).

## NULL-phase stage typing (v1)

| Condition | Stage |
|-----------|--------|
| All phases NULL + game count = n×(n−1)/2 for n players | `round_robin` / `overall` |
| All phases NULL + not full RR (e.g. 6 players, 6 games) | `knockout` / `knockout` |

Aligns with kitchen marathons (slice 5) vs NULL-phase cups (Athens IV).

## Pilot verification — Athens IV Cup (id=74)

```text
materialize --dry-run  → 1 knockout stage, 6 fixtures, rolled back
materialize             → committed
standings-rebuild       → 10 rows

orphaned       = 0
side_mismatch  = 0
standings      = knockout only (10 rows, 0 league)
stages         = 1 × knockout
```

Before materialize: 6 league-scope standings rows from NULL-phase parser. After: knockout-only via fixture path.

## Commands

```powershell
python -m scripts.amiga tournament-structure materialize --tournament-id 74 --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id 74
python -m scripts.amiga standings-rebuild --tournament-id 74
```

## STOP GATE B — user checks

1. **SQL** (local or staging after materialize):

   ```powershell
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT stage_type, stage_key FROM tournament_stages WHERE tournament_id=74;"
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT scope_type, COUNT(*) FROM amiga_tournament_standings WHERE tournament_id=74 GROUP BY scope_type;"
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT COUNT(*) side_mismatch FROM amiga_games g JOIN tournament_fixtures f ON f.id=g.fixture_id WHERE g.tournament_id=74 AND (g.player_a_id<>f.player_a_id OR g.player_b_id<>f.player_b_id);"
   ```

   Expect: `knockout` stage only; standings `knockout` only; `side_mismatch = 0`.

2. **Browser:** `http://ratingskickoff.test/amiga/tournament.php?id=74` — no spurious league table (knockout/bracket presentation).

Reply **OK for slice 4/5** when satisfied.

## Next slice

**Slice 4** — `verify-legacy` CLI (orphans, side parity, stage coverage).

**Slice 5** — bulk NULL-phase marathons (STOP GATE C).
