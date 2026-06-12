# Amiga tournament medals unification v2 — slice 4 handoff

**Date:** 2026-06-13  
**Slice:** 4 — Full rebuild + verify extensions  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

All career totals match v2 invariants on the full `ko2amiga_db` dataset after `participation-rebuild`.

---

## Checklist

- [x] `python -m scripts.amiga participation-rebuild` — 4517 participation, 473 totals
- [x] `verify_player_participation.py` — v2 totals invariants + `is_winner` parity
- [x] Full verify suite (STOP GATE A)

---

## STOP GATE A — results

### Rebuild

```
participation-rebuild complete: participation=4517 totals=473
refresh_wc_medals: 23 World Cup tournament(s), 70 medal row(s) set
```

### Verify suite

```powershell
python -m scripts.amiga verify-chronology
# OK: 27418 games, 0 backward game_date transitions

python -m scripts.amiga verify-rating-events
# OK: 603 finalized tournaments, 4517 rating_event rows

python -m scripts.amiga verify-player-participation
# OK: 4517 participation rows, 473 player totals

python -m scripts.amiga verify-player-matchups
# OK: 14024 directed pairs, SUM(games)=54836 = 2×27418
```

### Alkis P spot SQL

```sql
SELECT p.name, t.event_gold, t.wc_gold, t.event_podiums, t.wc_podiums, t.tournaments_won
FROM amiga_player_tournament_totals t
JOIN amiga_players p ON p.id = t.player_id
WHERE p.name = 'Alkis P';
```

| name | event_gold | wc_gold | event_podiums | wc_podiums | tournaments_won |
|------|------------|---------|---------------|------------|-----------------|
| Alkis P | 58 | 2 | 85 | 8 | 58 |

**Browser:** (no UI change this slice — read paths still v1 columns until slice 5)

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/verify_player_participation.py` | v2 totals + is_winner invariants |
| `docs/amiga-tournament-medals-unification-implementation-plan.md` | Slice 4 checked |
| `PROJECT_MEMORY.md` | Recent log |

**Data only (local DB):** full participation + totals rebuild applied.

---

## Awaiting user OK

Per plan **STOP GATE A** — confirm SQL/verify results before slice 5 (PHP read paths).

**Next after OK:** Slice 5 — profile, player-tournaments, `amiga_player_tournament_lib.php`, honours strip → v2 totals columns.
