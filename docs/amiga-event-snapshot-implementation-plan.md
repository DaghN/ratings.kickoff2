# Amiga event snapshots — implementation plan

**Status:** **Complete** (Jun 2026).  
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
| **1** | DDL `024_player_snapshots.sql` (snapshots + current) | `prove` / `apply_schema` bundle | **done** |
| **2** | Python `snapshot_row.py` — build full row from finalize state | Unit smoke from one tournament | **done** |
| **3** | Wire `finalize_tournament.py` + `player_stats_load` from current | One tournament finalize smoke | **done** |
| **4** | Replay / refinalize backfill all snapshots + current | Full replay + verify CLI | **done** |
| **5** | `verify_event_snapshots.py` | 0 errors local | **done** |
| **6** | PHP read switch → `amiga_player_current` + helpers | Browser: profile + one LB | **done** |
| **7** | Generalize `amiga_rating_history_lib.php` → snapshot cutoff (rating first, then columns) | History page parity | **done** |
| **8** | Retire old tables from schema/import/clear_derived; drop DDL migration `025_drop_legacy_player_tables.sql` | Full replay clean | **done** |
| **9** | Docs closure, MEMORY, feature-log, Part B registers | Dagh OK | **done** |

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

- [x] Add `scripts/amiga/sql/024_player_snapshots.sql`
  - **`amiga_player_event_snapshots`**: PK `(player_id, tournament_id)`; indexes per policy §4.1
  - Column sources (copy types from existing DDL):
    - `001_core.sql` → `amiga_player_stats` career columns (on both tables; current uses `player_id` PK)
    - `010_player_tournament_participation.sql` → event-local columns
    - `011_player_tournament_totals.sql` → honours columns
    - `009_rating_events.sql` → overlap with event-local (dedupe names)
    - Policy §4.5 career-best perf columns
  - **`amiga_player_current`**: same fact columns as snapshot **minus** `tournament_id` / event-only keys; PK `player_id`; optional `last_tournament_id`, `last_event_date`, `last_finalized_at` for debugging
- [x] Wire into `import_access.apply_schema()` after `023` (keep legacy tables until slice 8)
- [x] Add to `clear_derived` / drop order (snapshots before current; both before legacy if coexisting)
- [x] `scripts/amiga/README.md` apply note

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

- [x] Both tables exist; row counts 0 before backfill

---

## Slice 2 — Snapshot row builder (Python)

### Goal

Single module to build dict/SQL row for snapshot + current from finalize context.

### Tasks

- [x] `scripts/amiga/snapshot_row.py` — `build_event_snapshot_row`, `build_snapshot_from_finalize_parts`, `current_row_from_snapshot`
- [x] `career_columns_from_player_state` — `PlayerState.to_db_row`
- [x] `honours_columns_from_totals_row`
- [x] `career_best_performance_fields` — LB tie-break parity
- [x] `snapshot_insert_sql()` / `current_upsert_sql()`
- [x] `scripts/amiga/test_snapshot_row.py` (7 tests)

### Verification

```powershell
python -m unittest scripts.amiga.test_snapshot_row -v
```

- [x] Row dict keys cover policy §4.2–4.5 (137 snapshot columns)
- [x] 7 unit tests pass

---

## Slice 3 — Finalize wire

### Goal

`tournament finalize` writes snapshot + current instead of (or alongside, until slice 8) legacy tables.

### Tasks

- [x] `player_stats_load.py` → load from `amiga_player_stats` only (not `amiga_player_current`)
- [x] Entry Elo from last `amiga_rating_events.rating_after` before event (not stale career rating)
- [x] Prior career-best from prior `amiga_player_event_snapshots` (not `current`)
- [x] `snapshot_persist.py` + `finalize_tournament.py` — persist after participation refresh
- [x] PHP: `amiga_event_snapshot_persist.php` + `finalize_tournament.php`; ops reads from stats
- [x] Dual-write: legacy stats/events/participation + snapshots/current
- [x] `reopen_tournament` clears snapshot rows for reopened event

### Verification

```powershell
python -m scripts.amiga reopen-tournament --tournament-id=25
python -m scripts.amiga finalize-tournament --tournament-id=25
```

- [x] 37 snapshot rows + 37 current rows for WC XXIII (Milan)
- [x] `current.Rating` / `NumberGames` match `amiga_player_stats` for all 37 participants

---

## Slice 4 — Full backfill

### Goal

Populate snapshots + current for entire catalog from replay/finalize chain.

### Tasks

- [x] `scripts/amiga/rebuild_event_snapshots.py` — clear + chronological replay; incremental honours; network counts through tournaments-so-far
- [x] `snapshot_persist.py` — optional in-memory honours/prior-best/event-games (backfill path)
- [x] `replay.py` — after participation totals, `rebuild_all_event_snapshots`
- [x] `refinalize.py` — participation rebuild + snapshot rebuild after refinalize-from
- [x] CLI `python -m scripts.amiga rebuild-event-snapshots`

### Verification

```powershell
python -m scripts.amiga rebuild-event-snapshots
python -m scripts.amiga verify-rating-events
```

- [x] 4535 snapshot rows; 473 current rows (local `ko2amiga_db` Jun 2026)
- [x] `current` career rating matches `amiga_player_stats` for all 473 players; 37 rows differ on `NumberGames` only — current matches rated game row count (legacy stats stale)
- [ ] `verify-rating-events` — pre-existing chain-break on player 9 (unchanged by slice 4)
- [ ] Slice 5 `verify-event-snapshots` — formal contract checks

---

## Slice 5 — Verify CLI

### Goal

`verify_event_snapshots.py` — contract checks from policy §8.

### Tasks

- [x] `scripts/amiga/verify_event_snapshots.py`
- [x] Wire `python -m scripts.amiga verify-event-snapshots`
- [x] Checks: row counts; FK orphans; current = latest snapshot; event-local vs games rollup + participation; event rating block vs `amiga_rating_events`; current honours vs totals; `NumberGames` vs `amiga_games` count

### Verification

```powershell
python -m scripts.amiga verify-event-snapshots
```

- [x] 0 errors after slice 4 backfill (4535 / 473 local Jun 2026)
- Event rating **chain** across consecutive events: covered by `verify-rating-events` (snapshots copy per-row `amiga_rating_events`; known pre-existing chain break on player 9)

---

## Slice 6 — PHP present reads

### Goal

Switch hot paths to `amiga_player_current`.

### Tasks

- [x] `includes/amiga_player_current_lib.php` — `amiga_player_career_table`, `amiga_player_current_row`, `amiga_player_base_from_sql($con)`
- [x] Update: `amiga_player_load.php`, `amiga_lb_lib.php`, leaderboards, `amiga_records_ratio_leaders.php`, `amiga_player_moments_lib.php`, `amiga_player_tournament_lib.php`, `api/player_search.php`, `api/player_rating_history.php`, ops post-game reads
- [x] Grep: no website reads from `amiga_player_stats` (ops dual-write + rebuild DELETE remain until slice 8)

### Verification

- [x] Top-10 rating LB order identical (`current` vs legacy `stats`)
- [x] `amiga_player_load(9)` — 901 games, rating 2013 from `amiga_player_current`
- [ ] Browser smoke on staging (Dagh)

---

## Slice 7 — Historical reads

### Goal

Generalize history lib to use `amiga_player_event_snapshots` (all columns available; ship rating parity first).

### Tasks

- [x] `amiga_rating_history_lib.php` — ladder/cutoff/race reads from `amiga_player_event_snapshots` (not `amiga_rating_events`)
- [x] `history.php` unchanged UX
- [x] `api/amiga_top10_rating_race.php` — via lib (snapshot source)
- [x] Update [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) implementation refs

### Verification

- [x] Event wing last step top-10 = `amiga_player_current` rating LB (`scripts/oneoff/amiga_history_current_parity.php`)
- [x] Probe + race payload smoke (`amiga_rating_history_probe.php`, `race` argv)
- [ ] Browser smoke on staging (Dagh)

---

## Slice 8 — Retire legacy tables

### Goal

Remove dual-write; drop retired tables from schema path.

### Tasks

- [x] `scripts/amiga/sql/025_drop_legacy_player_tables.sql` — `DROP TABLE` participation, totals, rating_events, player_stats (FK order)
- [x] Remove from `import_access` apply bundle (009 finalize markers + 024; no 010/011/stats in recreate path)
- [x] Update `_AMIGA_TABLES_DROP_ORDER`, `clear_derived`, `truncate_ground_truth`
- [x] Remove dual-write from Python finalize + replay; PHP ops finalize/snapshot persist aligned
- [x] Grep repo — website reads on snapshots/current; verifiers snapshot-based

### Verification

```powershell
python -m scripts.amiga prove
```

- [x] Full pipeline green on local `ko2amiga_db` (Jun 2026 holy loop)

---

## Slice 9 — Documentation closure

### Tasks

- [x] `amiga-player-universe-contract.md` §3–§5 register update
- [x] `amiga-data-contract.md` table register
- [x] `PROJECT_MEMORY.md` — event snapshot track
- [x] `docs/coordination/feature-log.md` — L1 entry
- [x] Part B: staging export table list (`export_ko2amiga_db.ps1`); `scripts/amiga/README.md`

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
