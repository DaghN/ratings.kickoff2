# Post-game (C++) register

**Reference:** `docs/ratings_cpp.txt` (`RatingProcedureUnity`) — snapshot of live per-game logic, not deployed from this repo.

**Goal:** List every change needed in **Steve’s post-game path** so **future** rated games write correct data. History backfill → [replay register](replay-register.md).

| ID | Feature / change | Delta vs reference | Readiness | Staging testable? | Prod | Notes |
|----|------------------|-------------------|-----------|-------------------|------|-------|
| PG-001 | Remove rating decay semantics | Fade currently **hourly on DB** (not in excerpt); post-game should not reintroduce decay | L3 | No (staging has no live games) | Pending | Coordinate with [periodic PER-001](periodic-register.md); fade **off** before prod cutover |
| PG-002 | Align Elo K-factor / start rating with sandbox | Sandbox: K=32, start 1600 (`docs/replay-v1-scope-and-reset.md`); confirm live values with Steve | L3 | No | Pending | P5 in `docs/ladder-engine-plan.md` |
| PG-003 | *(template)* New `playertable` / `ratedresults` field maintained per game | Describe column + formula | L1–L3 | No | — | Add row when feature needs live writer |

### Adding a row

1. Describe **column(s)** and **formula** (enough for Steve or for us to port to `scripts/ladder`).
2. If history matters, link replay register item and extend Python replay in same PR.
3. Mark prod **Pending** until C++ deployed; set **Done** with date when Steve confirms.

### Acceptance (lightweight)

Optional: one example game (ids, goals) → expected `ratedresults` / `playertable` fields. Steve’s preferred format TBD (see hub § Steve coordination).
