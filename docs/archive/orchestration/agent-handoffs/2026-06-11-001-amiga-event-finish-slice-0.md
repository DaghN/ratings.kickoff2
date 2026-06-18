# Amiga event finish — slice 0 handoff

**Date:** 2026-06-11  
**Slice:** 0 — Schema (additive only)  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Add `event_finish_position` and `best_knockout_phase` to `amiga_player_tournament_participation` without dropping legacy `overall_position`.

---

## Checklist

- [x] New migration `scripts/amiga/sql/017_event_finish_position.sql`
  - [x] `event_finish_position` `SMALLINT NULL` after `overall_position`
  - [x] `best_knockout_phase` `VARCHAR(50) NULL` after `wc_medal`
  - [x] Did **not** drop `overall_position`
- [x] Update `scripts/amiga/sql/010_player_tournament_participation.sql` for fresh installs
- [x] Document migration in honours rules §7 and data contract table register
- [x] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/017_event_finish_position.sql`

### Verification

- [x] `SHOW COLUMNS` — `event_finish_position` and `best_knockout_phase` present
- [x] Verify suite still passes (writers unchanged)

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/017_event_finish_position.sql` | **New** — ALTER TABLE additive columns |
| `scripts/amiga/sql/010_player_tournament_participation.sql` | Fresh-install DDL includes both columns |
| `docs/amiga-tournament-honours-rules.md` | §7 status + migration table |
| `docs/amiga-data-contract.md` | Participation register + DDL list |
| `docs/amiga-player-universe-contract.md` | Column status + §10 migration row |
| `scripts/amiga/README.md` | Apply `017` in foundation migration list |
| `PROJECT_MEMORY.md` | Recent log |

---

## Verification output

```
SHOW COLUMNS ... event_finish_position → smallint YES NULL
SHOW COLUMNS ... best_knockout_phase   → varchar(50) YES NULL

OK: 27418 games, 0 backward game_date transitions
OK: rating events verified (603 finalized tournaments, 4517 rating_event rows)
OK: player participation verified (4517 participation rows, 473 player totals)
OK: player matchups verified (14024 directed pairs, SUM(games)=54836 = 2×27418)
```

---

## STOP gate notes

None for slice 0.

---

## Known limitations / next slice

- New columns exist but are **NULL** on all rows until slice 5 writer rebuild.
- `overall_position` still written and read everywhere — unchanged this slice.
- **Next:** Slice 1 — `derive_event_finish_position()` Tier A + Tier C in `participation_placement.py` + unit tests.
