# Post-game (C++) register

**Reference:** `docs/ratings_cpp.txt` (`RatingProcedureUnity`) — snapshot of live per-game logic, not deployed from this repo.

**Steve handoff (May 2026):** **Option 2** — we provide **C++ snippets to insert**, based on the excerpt, not prose-only specs. Format and workflow: **[post-game-cpp-handoff.md](post-game-cpp-handoff.md)** · files in **[cpp-snippets/](cpp-snippets/)**.

**Goal:** List every change needed in **Steve’s post-game path** so **future** rated games write correct data. History backfill → [replay register](replay-register.md).

| ID | Feature / change | Snippet pack | Readiness | Prod | Notes |
|----|------------------|--------------|-----------|------|-------|
| PG-001 | Remove rating decay semantics | *(TBD when scoped)* | L3 | Pending | Fade is **hourly periodic** ([PER-001](periodic-register.md)); post-game must not reintroduce decay; snippet pack may be “delete/disable X” |
| PG-002 | Align Elo K-factor / start rating with sandbox | *(TBD when scoped)* | L3 | Pending | Sandbox K=32, start 1600; snippets should match `scripts/ladder/elo.py` |
| PG-003 | *(template)* New field / per-game aggregate | `cpp-snippets/PG-003-….md` | L1–L3 | — | Add row + snippet file when feature needs live writer |
| PG-004 | Ratio leaders: DROP 28 GST cols (SCH-003), PHP `playertable` queries, C++ stop writes; non-ratio ties `>` | [cpp-snippets/PG-004-server-records-tie-break.md](cpp-snippets/PG-004-server-records-tie-break.md) | L3 | Pending | Local 002 applied; Steve: same migration + snippet |

### Adding a row (required for L3 features)

1. Add line to the table above.
2. Copy [cpp-snippets/_template.md](cpp-snippets/_template.md) → `cpp-snippets/PG-NNN-short-name.md` and draft C++ **before** cutover.
3. Implement the same behaviour in **`scripts/ladder/`** if replay must backfill ([replay register](replay-register.md)).
4. Link snippet path in [cutover packet](cutover-packet-template.md) §3.
5. Dagh review → send Steve → mark **Prod Done** with date.

### Column legend

- **Snippet pack** — path under `docs/coordination/cpp-snippets/`; **ready for Steve** when reviewed.
- **Readiness** — see [prod-readiness levels](../prod-coordination.md#prod-readiness-levels).
