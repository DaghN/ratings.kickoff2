# Production coordination — hub

**Audience:** Dagh, Steve, Cursor agents.

**Purpose:** One place for **WHAT** must happen on the live ladder database and server jobs before/after a feature is truly on prod. **HOW** Steve runs it (C++ vs Python, cron, maintenance window) is negotiated per cutover — documented in each register item and in the [cutover packet template](coordination/cutover-packet-template.md).

**Authority:** Product taste → `PROJECT_BRIEF.md`. Ladder/replay architecture → `docs/ladder-engine-plan.md`. **This hub** owns prod **coordination** registers until Dagh says otherwise.

---

## Three databases (quick reference)

| Environment | DB name | Live game writes? | Site code | DB updates |
|-------------|---------|-------------------|-----------|------------|
| **Local** | `ko2unity_db` | No | Repo + Laragon | Dump import, `schema/`, `python -m scripts.ladder run` |
| **Staging** | `kooldb` | **No** | WinSCP → `public_html/` | Steve: SQL, replay, one-offs — **not** live play |
| **Production** | `kooldb` | **Yes** (C++ post-game + periodic jobs) | Steve / agreed deploy | Continuous + cutover packets |

Full detail: `docs/ladder-engine-plan.md` §2, `docs/STATUS_PAGE_DATA.md`, `docs/LOCAL_DEV.md`.

---

## Registers (WHAT to coordinate)

| Register | File | Tracks |
|----------|------|--------|
| Schema | [coordination/schema-register.md](coordination/schema-register.md) | Tables, columns, indexes — SQL in `schema/migrations/` |
| Post-game (C++) | [coordination/post-game-register.md](coordination/post-game-register.md) | Per-game deltas vs `docs/ratings_cpp.txt` |
| Periodic | [coordination/periodic-register.md](coordination/periodic-register.md) | Hourly fade, future cron jobs |
| Replay | [coordination/replay-register.md](coordination/replay-register.md) | Full-history rebuilds, parameters, run log |
| One-off | [coordination/one-off-register.md](coordination/one-off-register.md) | Rare scripts; prefer replay when possible |

**When starting a feature:** add at least one ledger row (or mark **L0 PHP-only** in the feature doc). See [prod-readiness levels](#prod-readiness-levels) below.

---

## Standard cutover order (production)

Use for any release that changes **stored ladder truth** (not PHP-only cosmetics):

1. **Agree** cutover with Steve; send [cutover packet](coordination/cutover-packet-template.md).
2. **Turn off rating fade** (hourly) — required before deploy that changes ratings/stats semantics.
3. **Apply schema** — `schema/migrations/*.sql` in order on prod `kooldb` (Steve).
4. **Replay history** (if register says so) — Python `scripts/ladder` per `docs/replay-v1-scope-and-reset.md`, tested on staging; or Steve’s C++ replay to the **same written spec**.
5. **Deploy post-game C++** — so **future** games maintain new columns/aggregates.
6. **Deploy periodic jobs** (if any new/changed).
7. **Deploy PHP** (WinSCP or agreed path).
8. **Smoke checks** — spot profiles, ranked sort, one chart API; log in replay register.
9. **Mark registers done** — update status columns in each register file.

Staging rehearsal: steps 3–4 (+ PHP 7) on staging `kooldb` without live writes — proves server path only.

---

## Prod-readiness levels

| Level | Meaning | Example |
|-------|---------|---------|
| **L0** | PHP read-time only; no new stored truth | Monthly league table on `status.php` (aggregate in SQL) |
| **L1** | Schema only; no replay/post-game yet | New nullable column, unfilled |
| **L2** | Replay must backfill history | New derived column on `playertable` |
| **L3** | Live writer on prod (C++ and/or periodic) | Per-game update to new column |
| **L4** | Staging-tested; Steve packet ready | Schema + replay run on staging |
| **L5** | Prod done | Registers closed |

---

## Tooling map

| Task | Location |
|------|----------|
| Schema SQL | `schema/migrations/` + `schema/README.md` |
| Apply schema locally | `schema/apply_local.ps1` |
| Full replay | `python -m scripts.ladder run` — `scripts/ladder/README.md` |
| Staging replay (Steve) | `docs/STAGING_REPLAY.md`, `run_staging_ladder_replay.sh` |
| One-off template | `scripts/oneoff/` |
| Live C++ reference | `docs/ratings_cpp.txt` |
| Steve email/checklist | `docs/coordination/cutover-packet-template.md` |

---

## Steve coordination pattern (unchanged)

- Dagh: spec + scripts/SQL in Git + staging proof where possible.
- Steve: run on server (no SSH for Dagh, May 2026); C++ post-game updates; periodic scheduler.
- Prod replay: Python tested on staging **or** Steve’s tool — **same spec** either way.

---

## Related docs

- `docs/ladder-engine-plan.md` — three worlds, P5 deferred
- `docs/replay-v1-scope-and-reset.md` — replay scope
- `docs/STAGING_REPLAY.md` — staging one-shot record
- `PROJECT_MEMORY.md` — logistics hand-off

*Created May 2026 — coordination track.*
