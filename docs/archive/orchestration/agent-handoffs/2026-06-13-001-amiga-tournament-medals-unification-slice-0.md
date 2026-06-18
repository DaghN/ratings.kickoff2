# Amiga tournament medals unification v2 — slice 0 handoff

**Date:** 2026-06-13  
**Slice:** 0 — Totals schema (migration `021`)  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Add v2 career columns on `amiga_player_tournament_totals`; rename `podiums` → `event_podiums`; drop `cup_*`. Writers and read paths unchanged — new columns default to 0 until slices 3–4.

---

## Checklist

- [x] New migration `scripts/amiga/sql/021_tournament_medals_totals.sql`
  - [x] `event_gold`, `event_silver`, `event_bronze` (INT NOT NULL DEFAULT 0)
  - [x] `podiums` → `event_podiums`
  - [x] `wc_played`, `wc_podiums` (INT NOT NULL DEFAULT 0)
  - [x] Dropped `cup_gold`, `cup_silver`, `cup_bronze`
- [x] Updated `scripts/amiga/sql/011_player_tournament_totals.sql` for fresh installs
- [x] Registered in honours rules §7, data contract DDL list, `scripts/amiga/README.md`
- [x] Applied locally: `mysql -u root ko2amiga_db < scripts/amiga/sql/021_tournament_medals_totals.sql`

### Verification

- [x] `SHOW COLUMNS … event_gold` → present
- [x] `SHOW COLUMNS … event_podiums` → present (renamed from `podiums`)
- [x] `SHOW COLUMNS … cup_gold` → empty
- [x] `python -m scripts.amiga verify-player-participation` → OK (4517 participation, 473 totals)

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/021_tournament_medals_totals.sql` | **New** — ALTER totals table |
| `scripts/amiga/sql/011_player_tournament_totals.sql` | Fresh-install v2 column set |
| `docs/amiga-tournament-honours-rules.md` | §7 migration `021` shipped |
| `docs/amiga-tournament-medals-unification-implementation-plan.md` | Slice 0 tasks checked |
| `docs/amiga-data-contract.md` | DDL list + `021` |
| `scripts/amiga/README.md` | Apply `021` note + foundation list |
| `docs/coordination/feature-log.md` | L1 row (v2 track in progress) |
| `PROJECT_MEMORY.md` | Recent log |

---

## Verification output

```
SHOW COLUMNS … event_gold     → int NOT NULL DEFAULT 0
SHOW COLUMNS … event_podiums → int NOT NULL DEFAULT 0
SHOW COLUMNS … cup_gold      → (no rows)

OK: player participation verified (4517 participation rows, 473 player totals)
```

**Post-migrate column order (existing DB):** `event_*` and `wc_played`/`wc_*`/`wc_podiums` added via ALTER; `event_podiums` remains after `wc_podiums` (cosmetic only). Fresh `011` uses canonical order.

---

## STOP gate notes

None for slice 0 (SQL columns only).

---

## Known limitations / next slice

- **`participation-rebuild` totals path will fail** until slice 3 — Python/PHP writers still INSERT `cup_*` / `podiums`.
- **PHP read paths** (`amiga_player_tournament_lib.php`, profile blocks, honours LB) still SELECT `cup_*` / `podiums` — update in slices 5–7.
- **New totals columns are 0** on all rows until slice 4 full rebuild.
- **Next:** Slice 1 — Tier D: WC podium → `event_finish_position` 1/2/3 in `participation_placement.py` + unit tests.
