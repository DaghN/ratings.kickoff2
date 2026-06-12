# Amiga tournament medals unification v2 — slice 2 handoff

**Date:** 2026-06-13  
**Slice:** 2 — Backfill WC participation `event_finish_position` from `wc_medal`  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Existing World Cup participation rows: set `event_finish_position` from stored `wc_medal` before totals writer rewrite (slice 3).

---

## Checklist

- [x] `scripts/amiga/sql/021b_wc_finish_backfill.sql` — gold→1, silver→2, bronze→3; WC name filter only; idempotent
- [x] Applied locally on `ko2amiga_db`
- [x] `verify_player_participation.py` — replaced v1 “WC finish must be NULL” with wc_medal/finish parity check

### Verification

```sql
-- bad = 0
SELECT COUNT(*) AS bad FROM amiga_player_tournament_participation
WHERE tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND wc_medal IN ('gold','silver','bronze')
  AND (event_finish_position IS NULL OR event_finish_position NOT IN (1,2,3));

-- podium medal counts
SELECT wc_medal, event_finish_position, COUNT(*) AS n ...
-- gold/1: 23, silver/2: 23, bronze/3: 24 (70 rows total)
```

```powershell
python -m scripts.amiga verify-player-participation
# OK: 4517 participation rows, 473 player totals
```

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/021b_wc_finish_backfill.sql` | **New** — one-shot UPDATE |
| `scripts/amiga/verify_player_participation.py` | v2 WC medal/finish parity invariant |
| `docs/amiga-tournament-honours-rules.md` | §7 migration register |
| `docs/amiga-data-contract.md` | DDL list |
| `scripts/amiga/README.md` | Apply `021b` after `021` |
| `docs/amiga-tournament-medals-unification-implementation-plan.md` | Slice 2 checked |
| `PROJECT_MEMORY.md` | Recent log |

---

## STOP gate notes

None for slice 2.

---

## Known limitations / next slice

- **Non-WC** `event_finish_position` unchanged (~58 NULL backlog remains out of scope).
- **WC rank 4+** still NULL (deferred).
- **`participation-rebuild` still broken** on totals INSERT until slice 3 (`cup_*`/`podiums` dropped).
- **`is_winner`** still uses `wc_medal` for WC until slice 3.

**Next:** Slice 3 — rewrite totals aggregation + `is_winner` single-path (Python + PHP).
