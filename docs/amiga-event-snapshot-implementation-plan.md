# Amiga event snapshots — implementation plan

**Status:** **Ready to execute** (Jun 2026).  
**Policy:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md)

**Execution:** Slices **in order in this chat** (no separate starter prompts). Dagh says **“slice N”** or **“continue”** between slices if desired.

**Out of scope for this plan:** historical HoF (`amiga_realm_snapshots`), historical H2H, git commit unless Dagh asks, staging export unless needed for verify.

**Migration:** **L1+** — new DDL, finalize/replay writers, read-path switch → **Part B** at wrap-up.

---

## How to use this plan

1. Execute slices **0 → 9** in order.
2. Run each slice **Verification** before continuing.
3. **Stop and report** if full replay fails, current ≠ latest snapshot, or PHP/Python finalize parity breaks (same habit as [`amiga-tournament-finalize-implementation-plan.md`](amiga-tournament-finalize-implementation-plan.md) §2.2).
4. **Do not git commit** unless Dagh asks.
5. After slice 9: **UPDATE_DOCS** Part A + Part B.

---

## Locked decisions (do not re-open without user)

See policy **S1–S11**. Summary: `amiga_player_event_snapshots` + `amiga_player_current`; full row every finalize; retire four legacy player tables; present = current; history = cutoff on snapshots.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan + authority cross-links | — **done** |
| **1** | DDL `024_player_snapshots.sql` (snapshots + current) | Tables exist; `import --recreate-schema` or manual apply |
| **2** | Python `snapshot_row.py` — build full row from finalize state | Unit smoke from one tournament |
| **3** | Wire `finalize_tournament.py` + `player_stats_load` from current | One tournament finalize smoke |
| **4** | Replay / refinalize backfill all snapshots + current | Full replay + verify CLI |
| **5** | `verify_event_snapshots.py` | 0 errors local |
| **6** | PHP read switch → `amiga_player_current` + helpers | Browser: profile + one LB |
| **7** | Generalize `amiga_rating_history_lib.php` → snapshot cutoff (rating first, then columns) | History page parity |
| **8** | Retire old tables from schema/import/clear_derived; drop DDL migration `025_drop_legacy_player_tables.sql` | Full replay clean |
| **9** | Docs closure, MEMORY, feature-log, Part B registers | Dagh OK |

---

## Slice 0 — Policy & plan

### Goal

Lock design before DDL.

### Tasks

- [x] [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md)
- [x] This implementation plan
- [x] Cross-links in `amiga-data-contract.md`, `amiga-rating-history-policy.md` §6

### Verification

- Dagh reviewed policy S1–S11.

---

## Slice 1 — DDL

### Goal

Create `amiga_player_event_snapshots` and `amiga_player_current` with **full** column manifest.

### Tasks

- [ ] Add `scripts/amiga/sql/024_player_snapshots.sql`
  - **`amiga_player_event_snapshots`**: PK `(player_id, tournament_id)`; indexes per policy §4.1
  - Column sources (copy types from existing DDL):
    - `001_core.sql` → `amiga_player_stats` career columns (on both tables; current uses `player_id` PK)
    - `010_player_tournament_participation.sql` → event-local columns
    - `011_player_tournament_totals.sql` → honours columns
    - `009_rating_events.sql` → overlap with event-local (dedupe names)
    - Policy §4.5 career-best perf columns
  - **`amiga_player_current`**: same fact columns as snapshot **minus** `tournament_id` / event-only keys; PK `player_id`; optional `last_tournament_id`, `last_event_date`, `last_finalized_at` for debugging
- [ ] Wire into `import_access.apply_schema()` after `023` (keep legacy tables until slice 8)
- [ ] Add to `clear_derived` / drop order (snapshots before current; both before legacy if coexisting)
- [ ] `scripts/amiga/README.md` apply note

### DDL notes

- Prefer **identical career/honours column names** to retired tables for PHP migration.
- Prefix only where collision would occur (document in SQL comment header).
- Both tables: `ENGINE=InnoDB`, `utf8mb4`.

### Verification

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db < scripts/amiga/sql/024_player_snapshots.sql
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW TABLES LIKE 'amiga_player_%'; DESCRIBE amiga_player_event_snapshots;" 
```

- [ ] Both tables exist; row counts 0 before backfill

---

## Slice 2 — Snapshot row builder (Python)

### Goal

Single module to build dict/SQL row for snapshot + current from finalize context.

### Tasks

- [ ] `scripts/amiga/snapshot_row.py`
  - `build_event_snapshot_row(player_id, tournament_id, catalog, player_state, event_facts, honours_totals, finalized_at) -> dict`
  - `career_best_performance_fields(...)` for running max perf rating + tournament id
  - Reuse `_stats_row` / `PlayerState.to_db_row()` for career block
- [ ] `snapshot_insert_sql()` / `current_upsert_sql()` helpers
- [ ] Small smoke: import module; build row from mock or one finalized tournament in DB

### Verification

```powershell
python -c "from scripts.amiga.snapshot_row import build_event_snapshot_row; print('ok')"
```

- [ ] Row dict keys cover policy §4.2–4.5

---

## Slice 3 — Finalize wire

### Goal

`tournament finalize` writes snapshot + current instead of (or alongside, until slice 8) legacy tables.

### Tasks

- [ ] `player_stats_load.py` → load from `amiga_player_current` (rename conceptually; map columns to `PlayerState`)
- [ ] `finalize_tournament.py`:
  - After in-memory finalize, for each participant: `INSERT snapshot`, `UPSERT current`
  - Transaction with existing game_ratings + tournament finalized marker
  - Integrate honours totals from participation stack **into snapshot row** (may call existing rollup logic, then copy result onto row)
- [ ] PHP mirror: `site/public_html/amiga/ops/modules/finalize_tournament.php` if it writes stats/events directly — parity with Python
- [ ] During transition: optionally **dual-write** legacy + new tables until slice 5 verify passes (prefer dual-write over big-bang if refinalize path is fragile)

### Verification

```powershell
python -m scripts.amiga finalize-tournament --tournament-id <open_id>
```

- [ ] Snapshot rows for participants; current updated; rating_finalized set
- [ ] `current.Rating` matches last snapshot `rating_after` for sample player

---

## Slice 4 — Full backfill

### Goal

Populate snapshots + current for entire catalog from replay/finalize chain.

### Tasks

- [ ] `replay.py` or dedicated `rebuild_snapshots.py`: clear snapshots + current; run finalize chain (or refactor `commit_heavy_player_derived` path)
- [ ] `refinalize.py`: forward cascade uses snapshot/current bootstrap
- [ ] Row count ≈ `amiga_rating_events` count pre-migration

### Verification

```powershell
python -m scripts.amiga replay
python -m scripts.amiga verify-rating-events
```

- [ ] ~4535 snapshot rows; ~473 current rows
- [ ] Last event wing player count unchanged

---

## Slice 5 — Verify CLI

### Goal

`verify_event_snapshots.py` — contract checks from policy §8.

### Tasks

- [ ] `scripts/amiga/verify_event_snapshots.py`
- [ ] Wire `python -m scripts.amiga verify-event-snapshots`
- [ ] Checks: current = latest snapshot; career rating chain; event-local games rollup sample; honours rollup sample; no orphan FKs

### Verification

```powershell
python -m scripts.amiga verify-event-snapshots
```

- [ ] 0 errors after slice 4 backfill

---

## Slice 6 — PHP present reads

### Goal

Switch hot paths to `amiga_player_current`.

### Tasks

- [ ] `includes/amiga_player_current_lib.php` — `amiga_player_current_row($con, $playerId)`, table name constant
- [ ] Update: `amiga_player_load.php`, `amiga_lb_lib.php`, leaderboards, profile, `api/player_search.php`, `api/player_rating_history.php` (snapshots), ops post-game loaders
- [ ] Grep: eliminate `amiga_player_stats` reads (except ops migration shims until slice 8)

### Verification

- [ ] Browser: `/amiga/leaderboards/rating.php` order unchanged
- [ ] Browser: `/amiga/player/profile.php` smoke for one player

---

## Slice 7 — Historical reads

### Goal

Generalize history lib to use `amiga_player_event_snapshots` (all columns available; ship rating parity first).

### Tasks

- [ ] Refactor `amiga_rating_history_lib.php` → `amiga_snapshot_history_lib.php` (or extend in place) — table name + column mapping
- [ ] `history.php` unchanged UX; optional extra columns behind later ask
- [ ] `api/amiga_top10_rating_race.php` → snapshot source
- [ ] Update [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) implementation refs

### Verification

- [ ] Event wing last step = current rating LB
- [ ] News race animations still run

---

## Slice 8 — Retire legacy tables

### Goal

Remove dual-write; drop retired tables from schema path.

### Tasks

- [ ] `scripts/amiga/sql/025_drop_legacy_player_tables.sql` — `DROP TABLE` participation, totals, rating_events, player_stats (FK order)
- [ ] Remove from `import_access` apply bundle (replace 009–011 with 024 in recreate path)
- [ ] Update `_AMIGA_TABLES_DROP_ORDER`, `clear_derived`, `truncate_ground_truth`
- [ ] Remove dual-write from finalize
- [ ] Grep repo for dropped table names

### Verification

```powershell
python -m scripts.amiga import --recreate-schema
python -m scripts.amiga replay
python -m scripts.amiga verify-event-snapshots
```

- [ ] Full pipeline green on local `ko2amiga_db`

---

## Slice 9 — Documentation closure

### Tasks

- [ ] `amiga-player-universe-contract.md` §3–§5 register update
- [ ] `amiga-data-contract.md` table register
- [ ] `PROJECT_MEMORY.md` — event snapshot track
- [ ] `docs/coordination/feature-log.md` — L1 entry
- [ ] Part B: schema register / prod-coordination note if applicable

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Wide DDL typo | Generate column list from `DESCRIBE` script in slice 1 |
| Refinalize forward cascade bugs | Slice 4 full replay oracle; verify CLI |
| PHP/Python finalize drift | Slice 3 spot-check same tournament both paths |
| Performance of current vs `is_latest` | Keep `amiga_player_current`; benchmark optional post-ship |
| Participation read paths | Snapshots `WHERE tournament_id` replaces participation |

---

## Files expected (summary)

| Area | Files |
|------|--------|
| DDL | `scripts/amiga/sql/024_player_snapshots.sql`, `025_drop_legacy_player_tables.sql` |
| Python | `snapshot_row.py`, `verify_event_snapshots.py`; edits `finalize_tournament.py`, `player_stats_load.py`, `replay.py`, `refinalize.py`, `import_access.py`, `__main__.py` |
| PHP | `amiga_player_current_lib.php`; edits load/LB/history/ops |
| Docs | policy, plan, contract updates |

---

## Quick reference — present vs historical SQL

**Present leaderboard:**

```sql
SELECT … FROM amiga_player_current c
INNER JOIN amiga_players p ON p.id = c.player_id
WHERE c.NumberGames > 0
ORDER BY c.Rating DESC;
```

**Historical ladder at cutoff:**

```sql
-- Per policy §6: last snapshot per player on or before cutoff, then sort
-- Implement in amiga_snapshot_history_lib.php (reuse V1 ROW_NUMBER pattern)
```
