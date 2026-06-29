# Amiga World Cup Hall of Fame — implementation plan

**Status:** **Ready to execute** (Jun 2026-29)  
**Policy:** [`amiga-wc-hof-policy.md`](amiga-wc-hof-policy.md)  
**Parent:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) · [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md)

**Execution:** Slices **WCH-0 → WCH-8** in order. Run each slice **Verification** before continuing. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

**Migration:** **L1+** — new DDL, finalize writers, verify in `prove` → **Part B** at closure slice (WCH-8).

---

## How to use this plan (this chat)

1. Say **“Do slice WCH-N”** — agent executes **only** that slice unless you ask for more.
2. **STOP gates** — full `python -m scripts.amiga prove` only where marked (WCH-4, WCH-8). Mid-track use targeted verify + short replay smoke when noted.
3. After each shipping slice: **UPDATE_DOCS Part A**; **Part B** at WCH-8 when DDL lands.
4. Corrections = **`python -m scripts.amiga prove`** only — no ad-hoc SQL repair.

---

## Locked decisions (do not re-open)

From policy **WCH1–WCH13**. Compressed:

- **28** WC HoF rows (includes **Most World Cups played** moved into WC UI block)
- **No** WC perfect row; career **Most perfect events** unchanged
- Sparse storage: **`amiga_wc_hof_snapshots`** — write **only on World Cup finalize**
- Present: **`amiga_wc_hof_present` id=1** (policy option A)
- Time travel: latest WC HoF snapshot **≤ cutoff** — not on `amiga_realm_snapshots`
- Ratio rows: **`games ≥ 20`** on WC slice (`ESTABLISHED_MIN_GAMES` / `k2_established_min_games()`)
- Per-WC attack/defense awards: **no** min games in event; ties → lowest `player_id`
- **`MostWcPlayed` migrates** off `amiga_generalstats` / career realm writers in WCH-7

---

## Reference files (copy patterns)

| Area | Reference |
|------|-----------|
| Career HoF columns | `scripts/amiga/generalstats_columns.py`, `scripts/amiga/sql/derived/013_generalstats.sql` |
| Career holder compute | `scripts/amiga/server_records.py`, `scripts/amiga/realm_incremental.py` |
| Career rise dates | `scripts/amiga/career_rise.py`, `scripts/amiga/honours_totals.py`, SCH-029/030 |
| WC player slice | `scripts/amiga/slice_columns.py`, `slice_totals.py`, `slice_game_stats.py`, `slice_persist.py` |
| Finalize hook | `scripts/amiga/finalize_tournament.py` |
| Realm persist | `scripts/amiga/realm_persist.py` |
| HoF PHP read | `site/public_html/includes/amiga_realm_snapshot_read_lib.php`, `amiga/hall-of-fame.php` |
| HoF LB links | `site/public_html/includes/amiga_records_hof_links.php` |
| WC LB sort cols | `site/public_html/includes/amiga_wc_players_table.php`, `amiga_wc_lb_lib.php` |
| Verify habit | `scripts/amiga/verify_player_slice.py`, `verify_realm_snapshots.py`, `verify_hof_geo_year.py` |
| Country slice plan shape | [`amiga-world-cups-country-slice-implementation-plan.md`](amiga-world-cups-country-slice-implementation-plan.md) |

---

## Environment

| Item | Value |
|------|--------|
| DB | `ko2amiga_db` (local Laragon) |
| Holy loop | `python -m scripts.amiga prove` (~5–25 min) |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| HoF browser | `http://ratingskickoff.test/amiga/hall-of-fame.php` |
| Export after green | `powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1` |

---

## Column manifest (SCH-046)

**Registry file (new):** `scripts/amiga/wc_hof_columns.py` — single source for DDL, persist, verify, PHP read.

**Per record group** — standard holder suffixes unless noted:

| Suffix | Meaning |
|--------|---------|
| *(none)* | Value column |
| `ID` | Holder `player_id` |
| `Name` | Holder display name |
| `Date` | Display date (§7 policy) |
| `GameID` | Single-game anchor (§4.6) |
| `TournamentID` | Single-WC peak anchor (§4.8) |
| `IDA` / `IDB` / `NameA` / `NameB` | Two-player draw record (§4.6 row 23) |

### Record prefixes (28 groups)

| § | Prefix | Value SQL type | Extra anchors |
|---|--------|----------------|---------------|
| 4.1 | `MostWcPlayed` | int | rise date |
| 4.1 | `MostWcGold` | int | rise |
| 4.1 | `MostWcGames` | int | rise |
| 4.1 | `MostWcWins` | int | rise |
| 4.1 | `MostWcPoints` | int | rise |
| 4.2 | `BestWcPtsPerGame` | decimal | rise |
| 4.2 | `BestWcWinRate` | decimal | rise |
| 4.3 | `MostWcGoalsFor` | int | rise |
| 4.3 | `BestWcGoalsForPerGame` | decimal | rise |
| 4.3 | `BestWcGoalsAgainstPerGame` | decimal | rise (lower wins) |
| 4.3 | `BestWcGoalDiffPerGame` | decimal | rise |
| 4.3 | `BestWcGoalRatio` | decimal | rise |
| 4.4 | `MostWcDoubleDigits` | int | rise |
| 4.4 | `BestWcDoubleDigitsRatio` | decimal | rise |
| 4.4 | `MostWcCleanSheets` | int | rise |
| 4.4 | `BestWcCleanSheetsRatio` | decimal | rise |
| 4.5 | `MostWcOpponents` | int | rise |
| 4.5 | `MostWcVictims` | int | rise |
| 4.5 | `MostWcDoubleDigitsVictims` | int | rise |
| 4.5 | `MostWcCleanSheetsVictims` | int | rise |
| 4.6 | `MostWcGoalsInOneGame` | int | `GameID` |
| 4.6 | `BiggestWcWinDifference` | int | `GameID` |
| 4.6 | `BiggestWcDrawSum` | int | `GameID`, `IDA`, `IDB`, `NameA`, `NameB` |
| 4.6 | `BiggestWcSumOfGoals` | int | `GameID`, two players if product shows pair |
| 4.7 | `MostWcBestAttackAwards` | int | rise |
| 4.7 | `MostWcBestDefenseAwards` | int | rise |
| 4.8 | `BestSingleWcGoalsForPerGame` | decimal | `TournamentID` |
| 4.8 | `BestSingleWcGoalsAgainstPerGame` | decimal | `TournamentID` |

**Timeline table keys:** `tournament_id` PK, `event_date`, `event_chrono`, optional `tournament_name`, `finalized_at`.

**Present table:** `amiga_wc_hof_present` — `id = 1`, same payload columns as snapshots (no tournament key).

---

## Slice extensions (SCH-046 part B)

Add to **`amiga_player_slice_totals`** and **`amiga_player_slice_at_event`** (`slice_key = 'world_cup'`):

| Column | Purpose |
|--------|---------|
| `best_attack_awards` | §4.7 cumulative |
| `best_defense_awards` | §4.7 cumulative |
| `best_single_wc_gf_per_game` | §4.8 player peak |
| `best_single_wc_gf_per_game_tournament_id` | §4.8 anchor |
| `best_single_wc_ga_per_game` | §4.8 player peak (lower is better) |
| `best_single_wc_ga_per_game_tournament_id` | §4.8 anchor |

**Rise columns** on slice (both tables) for HoF date projection — `{metric}_last_rise_tournament_id` + `{metric}_last_rise_event_date` for:

- Existing: `tournaments_played` (already present)
- New rise metrics: `gold`, `games`, `wins`, `points`, `goals_for`, `double_digits`, `clean_sheets`, `different_opponents`, `different_victims`, `double_digits_victims`, `clean_sheets_victims`, `best_attack_awards`, `best_defense_awards`
- Ratio rise (store when eligible ratio strictly improves): derive metric-specific rise cols OR recompute rise tournament from slice timeline at WC HoF write — **implementer picks one**; verify oracles the HoF `*Date` either way

**Registry:** extend `scripts/amiga/slice_columns.py`.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **WCH-0** | Policy + this plan | Dagh OK — **done** |
| **WCH-1** | Column manifest + DDL `046_wc_hof.sql` + `schema_bundles.py` + replay clear | `apply-schema` + tables exist |
| **WCH-2** | Slice award + single-peak writers (Python + wire finalize) | unit tests |
| **WCH-3** | `wc_hof.py` compute + persist + finalize hook (WC only) | one-WC smoke + row count |
| **WCH-4** | `verify_wc_hof.py` + `prove` manifest | **`prove` green** |
| **WCH-5** | PHP slice + WC HoF finalize parity | spot-check one WC |
| **WCH-6** | PHP read lib + HoF UI WC block + LB links + TT | browser HoF present + `as=` |
| **WCH-7** | Remove `MostWcPlayed` from career generalstats writers + geo HoF UI row | `verify-hof-geo-year` updated |
| **WCH-8** | Docs closure, policy status, feature-log, optional export | **`prove` green** |

---

## WCH-1 — Manifest + DDL

### Goal

Empty WC HoF tables + slice extension columns; replay can clear them.

### Tasks

- [ ] Create `scripts/amiga/wc_hof_columns.py` — tuples: `WC_HOF_VALUE_COLUMNS`, `WC_HOF_HOLDER_ID_COLUMNS`, …, `WC_HOF_PAYLOAD_COLUMNS`, `WC_HOF_RECORD_SPECS` (prefix, sort_dir, eligibility, date_kind)
- [ ] Create `scripts/amiga/sql/derived/046_wc_hof.sql`:
  - `CREATE TABLE amiga_wc_hof_snapshots` (PK `tournament_id`, chrono index, full payload)
  - `CREATE TABLE amiga_wc_hof_present` (`id` PK, full payload)
  - `ALTER` slice totals + at_event — § slice extensions above
- [ ] Register in `scripts/amiga/schema_bundles.py` (`DERIVED_SQL` + drop order)
- [ ] `scripts/amiga/replay.py` / `clear_derived`: delete from `amiga_wc_hof_snapshots`, `amiga_wc_hof_present`
- [ ] `scripts/amiga/test_wc_hof_columns.py` — manifest column count matches 28 groups × suffix rules

### Verification

```text
python -m scripts.amiga apply-schema
python -m unittest scripts.amiga.test_wc_hof_columns -v
```

---

## WCH-2 — Slice awards + single-WC peaks

### Goal

At each **World Cup** finalize, update player slice fields before WC HoF snapshot compute.

### Tasks

- [ ] `scripts/amiga/wc_slice_awards.py`:
  - Input: tournament *E* games + participants
  - Compute each participant's event GF/g, GA/g (0 games → skip for averages; **still eligible for award if games ≥ 1** — use GF/g with games in denominator)
  - Attack winner: max GF/g; defense winner: min GA/g; tie → lowest `player_id`
  - Increment `best_attack_awards` / `best_defense_awards` on winner slice rows + rise fields
- [ ] In `WorldCupSliceTracker` or finalize hook after V2 game loop:
  - Compute this event's GF/g, GA/g per participant
  - Update `best_single_wc_*` if event improves player peak (strict beat; tie → lowest `player_id` for max GF/g peak; for GA/g lower wins)
- [ ] `scripts/amiga/slice_rise_wc.py` (or extend `slice_totals.py`) — bump `*_last_rise_*` when cumulative slice counters strictly increase at WC finalize
- [ ] Wire in `finalize_tournament.py` after `persist_world_cup_slices_at_tournament` and **before** WC HoF persist (WCH-3)
- [ ] Unit tests: `test_wc_slice_awards.py`, extend `test_slice_game_stats.py` for single-peak updates

### Verification

```text
python -m unittest scripts.amiga.test_wc_slice_awards scripts.amiga.test_slice_game_stats -v
```

---

## WCH-3 — WC HoF compute + persist

### Goal

When tournament *E* is a World Cup, compute full 28-holder payload through chrono ≤ *E* and write snapshot + present.

### Tasks

- [ ] `scripts/amiga/wc_hof.py`:
  - `WC_ESTABLISHED_MIN_GAMES = 20`
  - Load all players' WC slice rows at cutoff *E* (from `amiga_player_slice_at_event` latest ≤ *E* per player — same pattern as `amiga_lb_wc_slice` cutoff)
  - **Cumulative holders (§4.1–4.5, §4.7):** max/min over slice columns; dates from holder rise fields on slice
  - **Ratio holders (§4.2–4.4, §4.3 avgs):** eligible pool `games >= 20`; compute Pts/g, win rate, GF/g, GA/g, GD/g from slice numerators; beat per policy; date from ratio rise
  - **Single-game (§4.6):** WC-filtered oracle over `amiga_games` ≤ *E* (mirror `server_records.py` patches + `is_world_cup_tournament` join) — **do not** trust slice max alone for `GameID`
  - **Single-WC peaks (§4.8):** max/min over players' `best_single_wc_*` + `*_tournament_id`; date from anchor tournament
- [ ] `scripts/amiga/wc_hof_persist.py` — UPSERT snapshot row; REPLACE present id=1
- [ ] Hook in `finalize_tournament.py`:
  ```text
  if is_world_cup_tournament(name):
      apply_wc_slice_awards(...)
      patch = build_wc_hof_payload(conn, as_of_tournament_id=E)
      persist_wc_hof_snapshot(conn, E, patch)
  ```
- [ ] Refinalize forward chain: if refinalizing WC *T*, recompute WC HoF snapshots for *T* and all **later** WC tournament ids in chrono order (helper in `wc_hof_persist.py`)

### Verification

```text
python -m scripts.amiga replay --from-scratch
# SQL spot checks:
#   SELECT COUNT(*) FROM amiga_wc_hof_snapshots;  -- = finalized WC count
#   SELECT * FROM amiga_wc_hof_present WHERE id=1;
#   MostWcGoldID matches top wc gold on slice_totals
```

---

## WCH-4 — Verify + prove

### Goal

Read-only oracles; gate holy loop.

### Tasks

- [ ] `scripts/amiga/verify_wc_hof.py`:
  - Snapshot count = finalized World Cups
  - Present row = latest snapshot by chrono
  - For **each** snapshot row (or sample + full on latest): recompute oracle at that WC cutoff; assert all 28 groups
  - Ratio eligibility assertions
  - Date oracles: rise dates, game dates, tournament dates
  - Award counts vs recomputed per-WC leaders through cutoff
- [ ] Register `verify-wc-hof` in `scripts/amiga/prove.py` after `verify-player-slice`, before or after `verify-realm-snapshots` (order: after slice, before UI-dependent checks)
- [ ] Update `verify_hof_geo_year.py` — **prepare** for WCH-7: stop asserting `MostWcPlayed` on `amiga_generalstats` once migrated (can land in WCH-7 if cleaner)

### Verification

```text
python -m scripts.amiga prove
```

**STOP:** Do not start WCH-5 until `prove` exits 0.

---

## WCH-5 — PHP finalize parity

### Goal

Live finalize path matches Python replay for slice awards + WC HoF row.

### Tasks

- [ ] `site/public_html/amiga/ops/includes/amiga_wc_slice_awards_lib.php` — mirror Python awards + single-peak updates
- [ ] `site/public_html/amiga/ops/includes/amiga_wc_hof_lib.php` — mirror `build_wc_hof_payload` + persist
- [ ] Wire into PHP tournament finalize (same gate: World Cup only) after slice persist
- [ ] Optional: `scripts/amiga/verify_php_wc_hof_parity.py` or extend existing parity pattern — spot one WC tournament id

### Verification

```text
# After Python replay, PHP finalize one test WC in work copy OR row compare script
python -m scripts.amiga verify-wc-hof
```

---

## WCH-6 — Read lib + HoF UI + time travel

### Goal

Ship user-visible WC HoF block.

### Tasks

- [ ] `site/public_html/includes/amiga_wc_hof_read_lib.php`:
  - `amiga_wc_hof_records_load($con, AmigaSnapshotContext $ctx)` — present from `amiga_wc_hof_present`; TT from §8.2 query
  - `amiga_wc_hof_record_column_names()` — from shared manifest list (duplicate minimal PHP array or code-gen later)
- [ ] `site/public_html/amiga/hall-of-fame.php`:
  - Remove `MostWcPlayed` row from calendar/geo section
  - Add optional `<tr class="server-records-section-header">` or panel divider **World Cups**
  - Render 28 rows in §4 order using `$wcRecords`
  - Merge holder ids into `$hofHolderIds` for country flags
- [ ] Extend `site/public_html/includes/amiga_records_hof_links.php` — WC metrics → `/amiga/leaderboards/world-cups/*.php` sort indices
  - HoF-only rows (§4.7 awards, §4.8 peaks): **no link** v1 OK per policy
- [ ] Time travel: when `$ctx->isActive()`, career block unchanged; WC block uses `amiga_wc_hof_records_load`

### Verification

```text
# Present
http://ratingskickoff.test/amiga/hall-of-fame.php

# Time travel — WC gold holder at mid-era cutoff
http://ratingskickoff.test/amiga/hall-of-fame.php?as=year:2005
```

---

## WCH-7 — Migrate MostWcPlayed off career store

### Goal

Single home for `MostWcPlayed` on WC HoF store; no dual writes.

### Tasks

- [ ] Remove `MostWcPlayed` from `server_records.py` `_CAREER_HOLDERS` and `realm_incremental.py` career holder maps
- [ ] Remove `MostWc*` played columns from `generalstats_columns.py` `RECORD_*` tuples (or mark deprecated until DDL drop)
- [ ] Stop projecting `MostWcPlayed*` in career realm snapshot payload
- [ ] Update `verify_hof_geo_year.py` — oracle `MostWcPlayed` from `amiga_wc_hof_present` / WC slice, not `amiga_generalstats`
- [ ] Update `verify_realm_snapshots.py` if it asserts full column parity including old MostWcPlayed
- [ ] Optional follow-on migration `047_drop_generalstats_most_wc_played.sql` — DROP columns from `amiga_generalstats` + `amiga_realm_snapshots` (idempotent); **can ship in WCH-8** after prove green

### Verification

```text
python -m scripts.amiga verify-hof-geo-year
python -m scripts.amiga verify-wc-hof
python -m scripts.amiga verify-realm-snapshots
```

---

## WCH-8 — Closure

### Tasks

- [ ] Policy status → **Implemented** in `amiga-wc-hof-policy.md`
- [ ] `amiga-data-contract.md` — tables Active not Planned
- [ ] `amiga-hof-tournament-geo-policy.md` — remove `MostWcPlayed` from geo HoF table
- [ ] `PROJECT_MEMORY.md` + `feature-log.md` L1 row
- [ ] UPDATE_DOCS Part B (schema register if used)
- [ ] Export smoke optional

### Verification

```text
python -m scripts.amiga prove
```

---

## Ratio / win-rate definitions (implementer contract)

| Metric | Formula | Notes |
|--------|---------|-------|
| Pts/g | `points / games` | Match `amiga_wc_lb_points_per_game` |
| Win rate | `(wins + 0.5 * draws) / games` or LB helper | Match `amiga_wc_lb_win_rate` exactly |
| GF/g, GA/g, GD/g | From slice numerators ÷ `games` | GD/g = `(goals_for - goals_against) / games` |
| Goal ratio | `slice.goal_ratio` when GA > 0 | Same sentinel as career / slice writer |
| Event GF/g (awards) | `event_gf / event_games` | All participants with `event_games >= 1` |

---

## Risks / notes

| Risk | Mitigation |
|------|------------|
| Many rise columns on slice | Centralize in `slice_rise_wc.py`; verify dates in `verify-wc-hof` |
| Refinalize WC forward chain | Unit test refinalize mid-catalog replays later WC snapshots |
| `MostWcPlayed` migration breaks geo verify | WCH-7 updates oracles in same slice |
| HoF table width sync | Reuse `records_hof_sync_*` helpers; WC block may need second sync group or combined labels array |
| Full prove runtime | Run only at WCH-4 and WCH-8 STOP gates |

---

## Starter prompt (this chat)

```text
Today: Amiga WC HoF — slice WCH-N per docs/amiga-wc-hof-implementation-plan.md.
Policy: docs/amiga-wc-hof-policy.md. prove must pass at WCH-4 and WCH-8 STOP gates.
```