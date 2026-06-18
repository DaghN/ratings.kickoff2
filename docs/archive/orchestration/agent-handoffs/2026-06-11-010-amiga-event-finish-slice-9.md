# Amiga event finish — slice 9 handoff

**Date:** 2026-06-11  
**Slice:** 9 — Tier E override hook  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Curated finish override table + derivation hook for exotic formats; no bulk data yet.

---

## Checklist

- [x] Migration `019_tournament_finish_override.sql` — `amiga_tournament_finish_override`
- [x] Python `derive_event_finish_position(..., overrides=)` + writer loads overrides per tournament
- [x] PHP parity: `amiga_participation_apply_finish_overrides` + `amiga_ops_participation_finish_overrides_for_tournament`
- [x] Honours rules §3 Tier E updated
- [x] Unit tests: override beats KO finish; override sets NULL generic finish
- [x] `verify-player-participation` OK

---

## Schema

```sql
amiga_tournament_finish_override (
  tournament_id, player_id, event_finish_position
  PRIMARY KEY (tournament_id, player_id)
)
```

Empty by default. Populate via import/ops; participation rebuild reads on each tournament refresh.

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/019_tournament_finish_override.sql` | New table |
| `scripts/amiga/participation_placement.py` | `apply_finish_overrides`, `overrides` param |
| `scripts/amiga/player_tournament_participation.py` | `_load_finish_overrides` |
| `site/public_html/includes/amiga_participation_placement.php` | Tier E merge |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | Load overrides |
| `scripts/amiga/test_participation_placement.py` | Tier E tests |
| `docs/amiga-tournament-honours-rules.md` | §3 Tier E shipped |

---

## Deploy note

Apply `019` before writers that query `amiga_tournament_finish_override`.

---

## Next

**Slice 10** — documentation closure (honours rules **Implemented**, Part B registers if needed).
