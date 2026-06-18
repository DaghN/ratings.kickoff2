# Amiga standings scope unification — slice 6 handoff

**Date:** 2026-06-11  
**Slice:** 6 — Full replay proof  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

End-to-end derived truth matches migration intent after `league`/`knockout` scope unification.

---

## Checklist

- [x] `python -m scripts.amiga replay` (~22s)
- [x] `verify-chronology` — OK
- [x] `verify-rating-events` — OK
- [x] `verify-player-participation` — OK
- [x] `verify-player-matchups` — OK
- [x] `standings-parity --sweep` — PASS=683 SKIP=112 EXCEPTION=27 **FAIL=0**
- [x] `scope_type` distribution — **league + knockout only**

---

## Verification output

### Replay (~22s)

```
replay_all complete: tournaments=603 games=27418 rating_events=4517
rebuild_all_standings: 603 tournaments, 7864 standing rows
amiga_player_tournament_participation: 4517 rows
replay post-checks OK
```

### Verify suite (all exit 0)

| Command | Result |
|---------|--------|
| `verify-chronology` | OK: 27418 games, 0 backward transitions |
| `verify-rating-events` | OK: 603 tournaments, 4517 rating_event rows |
| `verify-player-participation` | OK: 4517 participation, 473 totals |
| `verify-player-matchups` | OK: 14024 pairs, SUM(games)=54836 |

### Standings scope (post-replay)

```sql
SELECT scope_type, COUNT(*) FROM amiga_tournament_standings GROUP BY scope_type;
```

| scope_type | n |
|------------|---|
| league | 5544 |
| knockout | 2320 |

**Total:** 7864 rows — matches replay log. No `overall`, `group`, or `placement`.

### Parity sweep

```
PASS=683 SKIP=112 EXCEPTION=27 FAIL=0
```

Report: `data/amiga/exports/standings_parity_report.json`. EXCEPTION count unchanged class (ref_alias_merge, ref_stale_tables, mixed_overall_league_only) — pre-migration baseline behaviour; zero new FAILs from scope change.

---

## Files changed

None (verification slice only). Plan checkboxes + MEMORY updated.

---

## Next slice

**Slice 7** — Documentation closure: policy **Implemented**, data contract, honours rules wording, README, feature-log, starter prompt COMPLETE.
