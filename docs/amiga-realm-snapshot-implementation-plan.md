# Amiga realm snapshots — implementation plan

**Status:** **Complete** (Jun 2026) — slices **0–8** done; local `prove` green (~5.4 min).  
**Derived repair (Jun 2026):** Batch `*-rebuild` CLIs retired — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md). Slice notes may name removed commands historically; **corrections = `prove` only**.  
**Policy:** [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md)

**Execution:** Slices **in order**. Dagh says **“slice N”** or **“continue”** between sessions.

**Out of scope for this plan:** historical HoF UI wings, WC medals in realm row (R11), git commit unless Dagh asks, staging export unless needed for verify.

**Migration:** **L1+** — new DDL, finalize/replay writers, HoF read-path switch → **Part B** at wrap-up.

---

## How to use this plan

1. Execute slices **0 → 8** in order.
2. Run each slice **Verification** before continuing.
3. **Stop and report** if `prove` fails, `generalstats` ≠ latest realm snapshot, or PHP/Python finalize parity breaks.
4. **Do not git commit** unless Dagh asks.
5. After slice 8: **UPDATE_DOCS** Part A + Part B.

---

## Locked decisions (do not re-open without user)

See policy **R1–R11**. Summary: `amiga_realm_snapshots` + present `amiga_generalstats`; **full row** every finalize; ratio leaders on row; no replay tail `generalstats-rebuild` on sign-off path.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan + authority cross-links | Dagh OK |
| **1** | DDL `027_realm_snapshots.sql` — extend `amiga_generalstats` (ratio cols) + `amiga_realm_snapshots` | `prove` schema apply | **done** |
| **2** | `realm_row.py` + extend `server_records.py` — `compute_realm_row_at_cutoff(tournament_id)` | unit smoke | **done** |
| **3** | Wire `finalize_tournament.py` — persist realm snapshot + `generalstats` | one tournament smoke | **done** |
| **4** | Replay / refinalize — realm row each finalize; remove tail dependency | full replay | **done** |
| **5** | `verify_realm_snapshots.py` + CLI | 0 errors local | **done** |
| **6** | PHP HoF read switch — `hall-of-fame.php` from `generalstats` only (drop live ratio SQL) | browser HoF spot-check | **done** |
| **7** | PHP `finalize_tournament.php` parity with Python realm persist | one live finalize smoke | **done** |
| **8** | Docs closure, export manifest, MEMORY, feature-log, Part B registers | `prove` green | **done** |

---

## Slice 0 — Policy & plan

### Goal

Lock design before DDL.

### Tasks

- [x] [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)
- [x] This implementation plan
- [x] Cross-links in `amiga-event-snapshot-policy.md`, `amiga-data-contract.md`, `amiga-player-universe-contract.md` §5.5 / §8

### Verification

- Dagh reviewed policy R1–R11.

---

## Slice 1 — DDL

### Goal

`amiga_realm_snapshots` table + ratio leader columns on `amiga_generalstats` (both bundles: `sql/derived/` + flat `sql/` archive copy).

### Tasks

- [x] Add `scripts/amiga/sql/derived/027_realm_snapshots.sql` (and mirror under `scripts/amiga/sql/027_realm_snapshots.sql` if bundle pattern requires)
  - **`amiga_realm_snapshots`**: PK `tournament_id`; indexes per policy §4.1
  - **Payload columns**: copy every non-`id` column from `amiga_generalstats` **after** ratio ALTER (same names, same types)
  - Timeline columns: `event_date`, `event_chrono`, `tournament_name`, `finalized_at`
- [x] **ALTER `amiga_generalstats`** — add ratio leader columns (18 cols):
  - `BiggestWinRatio`, `BiggestWinRatioID`, `BiggestWinRatioName`
  - `BiggestGoalsForAverage`, `BiggestGoalsForAverageID`, `BiggestGoalsForAverageName`
  - `SmallestGoalsAgainstAverage`, `SmallestGoalsAgainstAverageID`, `SmallestGoalsAgainstAverageName`
  - `BiggestGoalRatio`, `BiggestGoalRatioID`, `BiggestGoalRatioName`
  - `BiggestDoubleDigitsRatio`, `BiggestDoubleDigitsRatioID`, `BiggestDoubleDigitsRatioName`
  - `BiggestCleanSheetsRatio`, `BiggestCleanSheetsRatioID`, `BiggestCleanSheetsRatioName`
- [x] Update `schema_bundles.py` derived list (after `026_matchup_at_event.sql`)
- [x] `REALM_SNAPSHOT_COLUMNS` / `GENERALSTATS_COLUMNS` constant module (or extend `server_records.py`) — single manifest for writers and verify → `generalstats_columns.py`

### Verification

```powershell
python -m scripts.amiga prove
# or schema-only: apply derived bundle on empty DB — tables exist, column counts match manifest (~101 payload cols)
```

- [x] `prove` green Jun 2026 — `generalstats` 101 cols (100 payload); `amiga_realm_snapshots` 105 cols; 0 rows until slice 3

---

## Slice 2 — Compute engine

### Goal

One function builds the full realm row dict at a tournament cutoff.

### Tasks

- [x] `scripts/amiga/realm_persist.py` + refactored `server_records.py` (`build_generalstats_payload`, `realm_cutoff.py`)
  - `build_realm_row` / `persist_realm_snapshot_for_tournament`
  - `compute_server_aggregates`, record holders, ratio leaders at cutoff
- [x] Unit tests: `test_realm_row.py`, `test_generalstats_columns.py`
- [x] `build_generalstats_payload` oracle at latest finalized tournament (verify-realm-snapshots; `generalstats-rebuild` CLI retired Jun 2026)

### Verification

```powershell
python -m unittest scripts.amiga.test_realm_row scripts.amiga.test_generalstats_columns -v
```

---

## Slice 3 — Finalize wire (Python)

### Goal

Each `finalize_tournament` writes realm snapshot + updates `generalstats`.

### Tasks

- [x] `realm_persist.py` — `INSERT ... ON DUPLICATE KEY UPDATE` for `amiga_realm_snapshots`; `UPDATE amiga_generalstats`
- [x] `finalize_tournament.py` — after player snapshots + matchups, call realm persist (same transaction)
- [x] `clear_derived` / replay reset — truncate `amiga_realm_snapshots`; re-seed `generalstats` id=1 empty row

### Verification

```powershell
python -m scripts.amiga refinalize --tournament-id <known_id> --dry-run
# then without dry-run on tail tournament — SELECT COUNT(*) FROM amiga_realm_snapshots increases by 1
# generalstats.GamesPlayed > 0
```

---

## Slice 4 — Replay integration

### Goal

Full replay produces one realm snapshot per finalized tournament; holy loop does not call tail `generalstats-rebuild`.

### Tasks

- [x] Confirm `replay.py` finalize loop persists realm rows (slice 3)
- [x] No post-loop `run_generalstats_rebuild` on `prove` sign-off path
- [x] `refinalize.py` — historical (refinalize track retired Jun 2026)
- [x] Verify oracle documented in `scripts/amiga/README.md` (`generalstats-rebuild` CLI retired Jun 2026)

### Verification

```powershell
python -m scripts.amiga prove
# COUNT(amiga_realm_snapshots) ≈ COUNT(finalized tournaments)
```

---

## Slice 5 — Verify CLI

### Goal

`verify_realm_snapshots.py` — contract checks from policy §7.

### Tasks

- [x] `scripts/amiga/verify_realm_snapshots.py`
- [x] Wire `python -m scripts.amiga verify-realm-snapshots` (+ `prove` step)
- [x] Checks: row count, present = latest snapshot, `GamesPlayed` oracle, aggregate DECIMAL rounding

### Verification

```powershell
python -m scripts.amiga verify-realm-snapshots
# OK: realm snapshots verified (605 rows) — Jun 2026
```

---

## Slice 6 — PHP present HoF reads

### Goal

`/amiga/hall-of-fame.php` reads ratio rows from `amiga_generalstats` — no live leader queries.

### Tasks

- [x] Extend `hall-of-fame.php` `$recordColumns` with ratio column names
- [x] Removed `amiga_records_load_ratio_leaders($con)` on present path
- [x] Keep `amiga_records_wc_totals_leaders` unchanged (R11)
- [x] `includes/amiga_records_ratio_leaders.php` — helpers retained; present authority = `generalstats`

### Verification

- Browser: `/amiga/hall-of-fame.php` — 20 record rows render; values match pre-slice spot-check
- No SQL errors when `generalstats` row populated

---

## Slice 7 — PHP finalize parity

### Goal

Live `finalize_tournament.php` writes realm snapshot + `generalstats` like Python.

### Tasks

- [x] `amiga/ops/includes/amiga_realm_snapshot_lib.php` — PHP mirror of compute + persist
- [x] Same transaction boundary as player snapshot persist in `finalize_tournament.php`
- [x] PHP `reopen_tournaments_batch` + `zero-derived` clear `amiga_realm_snapshots` (mirrors Python `refinalize.py` / `replay.clear_derived`)
- [ ] Ops smoke: one generated tournament finalize updates `amiga_realm_snapshots` (manual when staging next finalize)

### Verification

- Python refinalize vs PHP finalize on same tournament — realm rows match (column-wise)

---

## Slice 8 — Docs closure

### Goal

Track complete; export + registers updated.

### Tasks

- [x] `amiga-data-contract.md` — `amiga_realm_snapshots` + `amiga_generalstats` active
- [x] `amiga-player-universe-contract.md` §5.5, §8 rebuild order
- [x] `amiga-event-snapshot-policy.md` §5–§6 realm finalize step
- [x] `export_ko2amiga_db.ps1` — `amiga_realm_snapshots` part after `generalstats`
- [x] `amiga-staging-handoff.md`, `PROJECT_MEMORY.md`, `feature-log.md`
- [x] Amiga L1 register note in `feature-log` (no online `schema-register` — Amiga DDL in `scripts/amiga/sql/derived/027`)

### Verification

```powershell
python -m scripts.amiga prove
python -m scripts.amiga verify-realm-snapshots
# Jun 2026: prove green ~5.4 min; verify-realm 605 rows
```

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Refinalize forward pass expensive | Start with full rescan per affected tournament; optimize later with verify guard |
| PHP/Python drift on ratio tie rules | Shared test vectors; slice 7 parity gate |
| Wide row DDL migration on staging | `prove` nuclear path; multi-part export adds realm_snapshots part |

---

## Historical HoF UI (follow-on, not numbered here)

After slice 8 storage is proven:

- `amiga_realm_history_lib.php` — cutoff read on `amiga_realm_snapshots` (mirror `amiga_rating_history_lib.php`)
- History hub or HoF “as of” wings — product slice when Dagh asks

*Track initiated Jun 2026 — completes deferred event-snapshot policy §9 item.*
