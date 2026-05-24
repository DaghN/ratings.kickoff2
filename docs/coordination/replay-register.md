# Replay register

Full-history rebuild from **`ratedresults`** (canonical ladder games). Engine: **`python -m scripts.ladder`** — spec `docs/replay-v1-scope-and-reset.md`.

**Parameters (current sandbox default):** K=32, start rating 1600, **no decay**, order `Date ASC, id ASC`.

| ID | Trigger | Scope | Local | Staging | Prod | Command / record |
|----|---------|-------|-------|---------|------|------------------|
| REP-001 | Ladder replay v1/v2 baseline | All `ratedresults`; rebuild `playertable` + `generalstatstable` | Done May 2026 | Done May 2026 | **Not run** | `docs/STAGING_REPLAY.md`; `bash run_staging_ladder_replay.sh` |
| REP-002 | *(template)* New derived columns need backfill | Extend `scripts/ladder` then full `run` | — | — | — | After schema register items applied |

### Run log (append rows)

| Date | Environment | DB | Who | Games | Exit | Notes |
|------|-------------|-----|-----|-------|------|-------|
| 2026-05 | Local | `ko2unity_db` | Dagh | ~74870 | 0 | v2 playertable + generalstats |
| 2026-05 | Staging | `kooldb` | Steve | ~74870 | 0 | `docs/STAGING_REPLAY.md` |

### Prod cutover (when scheduled)

- **Prerequisite:** [PER-001](periodic-register.md) fade off; schema migrations applied.
- **Tool:** Python replay tested on staging **or** Steve C++ replay to **same spec** (TBD with Steve).
- **Packet:** `docs/coordination/cutover-packet-template.md`
- **After:** Post-game C++ must match replay rules for **new** games (P5).

### Not in replay v1

- `resulttable` (unrated / live shell — see `PROJECT_MEMORY.md`)
- `PlayerRank`, `Display` website rules (PHP)
