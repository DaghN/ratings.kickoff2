# Amiga event finish — slice 2 handoff

**Date:** 2026-06-11  
**Slice:** 2 — Tier B (league + cup)  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

League+cup marathons: cup podium from main bracket; league `overall` for non-KO players; cup overrides league for bracket participants.

---

## Checklist

- [x] `compute_tier_b_league_cup_finish` — league baseline + `compute_tier_a_knockout_finish` merge
- [x] Wired in `derive_event_finish_position` when `has_league` + `has_cup`
- [x] Fixed `placement_final_winner_loser_ranks` (3rd/5th/7th… place finals; was mis-treating 5th+ as 3rd)
- [x] Unit tests: minimal final-only, cup override, Milan-style semi bronze, 5th-place final
- [x] DB integration test: Athens LXXXV (`tournament_id=592`)

### Verification

- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 19 OK

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/participation_placement.py` | Tier B + placement-final rank helper |
| `scripts/amiga/test_participation_placement.py` | Tier B tests + `EventFinishDbIntegrationTests` |

---

## Verification output

```
Ran 19 tests in 0.180s — OK
```

---

## STOP GATE A (slice 5 not done — review before slice 3)

**DB columns:** `event_finish_position` still NULL on all rows (writers slice 5). Dry-run compares Python `derive_participation_positions` (legacy) vs `derive_event_finish_position` (new) from standings.

| Metric | Value |
|--------|-------|
| `overall_position > 0` in participation | 4517 |
| `event_finish_position IS NOT NULL` | 0 (expected until slice 5) |

### Example tournaments (dry-run)

**Pure KO — Bournemouth II (544):** 7 players, **5 diffs**. Catalog flags `has_league+has_cup` but no `overall` scope → Tier A fallback. Key change: semi loser 286 legacy **4** → new **3** (shared bronze). Local standings have `Semi Final|30-134` both at `position=1` (Access quirk) so player 30 is ranked by depth (5+) not bronze — unit tests use corrected semi rows.

**Pure league — London XXIII (371):** 25 players, **0 diffs**. New finish = overall league position (Tier C).

**League+cup — Athens LXXXV (592):** 12 players, **2 diffs** (expected):

| Player | Legacy (`overall_position`) | New (`event_finish_position`) |
|--------|----------------------------|-------------------------------|
| Player 37 | 9 (league) | 10 (lost 9th-place final) |
| Player 242 | 10 (league) | 9 (won 9th-place final) |

Placement finals (3rd/5th/7th/9th/11th) now override league rank for KO participants — legacy stored league-only rank.

**Milan X (156):** 8 players, **0 diffs** (full bracket + league merge).

### What to check

1. Athens LXXXV placement-final swap (9th-place match) looks correct vs `/amiga/tournament.php?id=592`.
2. Pure KO shared semi bronze still sane on a cup you know (e.g. Bournemouth II).
3. Say **OK** to proceed to slice 3 (WC medals), or flag a tournament id to spot-check.

---

## Known limitations / next slice

- **Slice 3:** WC shared bronze medals; Tier D explicit NULL
- Writers still use legacy `overall_position`
- Tier E overrides not implemented
