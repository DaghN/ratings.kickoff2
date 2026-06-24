# Amiga World Cups — country slice implementation plan

**Status:** **Planned** (Jun 2026-24) — policy locked; **no code shipped**.

**Policy:** [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md)  
**Parent:** [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit** unless Dagh asks.

**Migration:** **L1+** — new DDL, finalize writers, verify in `prove` → **Part B** at closure slice.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product + column definitions + roll-up rules (**WCCS1–24**) |
| **This plan** | File-level tasks, STOP gates, verify commands, reference files, UI workshop timing |
| **Starter prompt** (optional) | Cold-start block for a fresh agent chat — create when slice 1 begins |

Policy §9 lists slice **names**; this doc is the **execution contract**.

---

## How to use this plan

1. Execute slices **CS-1 → CS-7** in order (**CS-0** = policy + this plan).
2. **STOP** if `prove` fails on `verify-country-slice` or PHP parity spot-check fails.
3. **Do not** ship hub UI (CS-6) before `prove` green on writers (CS-5).
4. **Column placement** (WCCS20): decide during **CS-6 prep** or a short Dagh review — metric catalog in policy §5 is authoritative.
5. After **CS-7**: UPDATE_DOCS Part A + Part B + feature-log L1 row.

---

## Locked decisions (do not re-open without user)

See policy **WCCS1–24**. Compressed:

- Nation grain; `Unknown` bucket; eligibility = ≥1 WC national
- Player-games accounting (domestic double-count)
- Sums / max-of-maxes / set-unions per policy §4
- DD·CS victims **no win gate** (match player ops)
- Perf rating + avg opp rating on **Results**
- Win rate = `(W + ½D) / games`
- Hub only — no LB Countries wing
- Stored truth at finalize — no live `amiga_games` scans on read

---

## Reference implementation (copy patterns, do not fork logic)

| Area | Reference |
|------|-----------|
| Player WC slice DDL | `scripts/amiga/sql/derived/033_player_slice.sql`, `039_player_slice_v2.sql` |
| Player slice writers | `scripts/amiga/slice_totals.py`, `slice_game_stats.py`, `slice_persist.py` |
| Finalize hook | `scripts/amiga/finalize_tournament.py` (after player WC persist) |
| Player verify | `scripts/amiga/verify_player_slice.py` |
| PHP writers | `site/public_html/amiga/ops/includes/amiga_slice_totals_lib.php`, `amiga_slice_game_stats_lib.php`, `amiga_slice_persist_lib.php` |
| Player hub UI | `site/public_html/includes/amiga_wc_players_table.php`, `amiga_wc_players_wing_body.inc.php` |
| Performance rating | `scripts/amiga/performance_rating.py` |
| Country normalize | `scripts/amiga/player_geo_year.py` → `normalize_country` |
| K2 table stack | [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) — grep `amiga_wc_players_table.php` |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **CS-0** | Policy + this plan | Dagh OK — **done** |
| **CS-1** | DDL `040_country_slice.sql` + `schema_bundles.py` + column registry | schema apply on empty DB |
| **CS-2** | Python `CountryWorldCupSliceTracker` + sum/max roll-ups + realm scalars | unit tests green |
| **CS-3** | Wire `finalize_tournament.py` + `country_slice_persist.py` | one WC tournament smoke |
| **CS-4** | `verify_country_slice.py` + `prove` manifest step | 0 errors on fresh replay tail |
| **CS-5** | PHP finalize parity + `replay.clear_derived` includes country tables | Python vs PHP row spot-check |
| **CS-6** | Hub wing 4 UI — five sub-wings + routes + hub sub-nav | browser + `audit_k2_table_compliance.py` |
| **CS-7** | Docs closure, export smoke, feature-log | `prove` green (~5–25 min) |

---

## CS-1 — DDL + registry

### Goal

Empty country slice tables; columns match policy §5.

### Tasks

- [ ] Create `scripts/amiga/sql/derived/040_country_slice.sql`:
  - `amiga_country_slice_totals` PK `(country_token, slice_key)` — default `slice_key = 'world_cup'`
  - `amiga_country_slice_at_event` PK `(country_token, slice_key, as_of_tournament_id)`
  - All columns from policy §5.1–§5.6 + realm denominator fields if stored per-row
- [ ] Register in `scripts/amiga/schema_bundles.py` `DERIVED_SQL` + `_DERIVED_DROP_ORDER` (before player slice drops or after — mirror player slice ordering)
- [ ] Add `scripts/amiga/country_slice_columns.py` (or extend `slice_columns.py`) — `COUNTRY_SLICE_STAT_COLUMNS`
- [ ] `replay.py` / `clear_derived`: `DELETE FROM amiga_country_slice_at_event` + `amiga_country_slice_totals`

### Verification

```text
python -m scripts.amiga apply-schema
# Confirm tables exist; all numeric columns default 0 / NULL per player slice habit
```

---

## CS-2 — Python tracker + roll-ups

### Goal

In-memory country state; correct sum / max / union semantics.

### Tasks

- [ ] `scripts/amiga/country_slice_totals.py` — `empty_country_world_cup_slice()`, helpers
- [ ] `scripts/amiga/country_slice_game_stats.py` — `CountryWorldCupSliceTracker`:
  - Mirror `WorldCupSliceTracker` perspective rules for **country_token** (policy §5.6)
  - Domestic / international game counters
  - Collect `(opponent_rating, score)` pairs per national player-game for perf rating
  - Accumulate `sum_opponent_rating` for average
- [ ] `rollup_country_from_players(country_token, player_ids, player_slice_rows)` — sum + max columns
- [ ] `compute_realm_wc_scalars(all_country_rows_or_games)` — `realm_wc_tournament_count`, `realm_wc_player_games`, `realm_wc_goals_for`
- [ ] Derive: `win_rate`, shares, `points_per_realm_wc`, ratios, `performance_rating` via `performance_rating_from_pairs`
- [ ] `scripts/amiga/test_country_slice_game_stats.py` — domestic double-count, DD victim without win, Italy–Italy faced set

### Verification

```text
python -m unittest scripts.amiga.test_country_slice_game_stats -v
```

---

## CS-3 — Finalize wire

### Goal

Country rows update on every WC tournament finalize, after player slice persist.

### Tasks

- [ ] `scripts/amiga/country_slice_persist.py` — load prior, persist `at_event` + upsert `totals` (mirror `slice_persist.py`)
- [ ] In `finalize_tournament.py` after `persist_world_cup_slices_at_tournament`:
  1. Build `player_id → country_token` map (`Unknown` for blank)
  2. For each country touched this tournament (and cumulative recompute strategy — **full re-rollup from player slices + game replay** vs incremental: prefer **replay-safe full country recompute at end of each WC finalize** for v1 simplicity, or incremental tracker carried in replay dict like player slice — document choice in code comment)
  3. Persist all country rows
- [ ] Ensure non-WC tournaments do **not** mutate country slice

### Verification

```text
# Replay through first WC; spot Italy (or known country) row non-zero
python -m scripts.amiga replay --through-tournament-id <first_wc_id>
# Manual SQL spot-check one country_token row
```

---

## CS-4 — Verify oracles

### Goal

`verify-country-slice` in `prove` manifest.

### Tasks

- [ ] `scripts/amiga/verify_country_slice.py`:
  - Sum metrics: `GROUP BY country_token` over `amiga_player_slice_totals` joined to `amiga_players`
  - Max metrics: `MAX(...)` per group
  - Network six: independent game-loop oracle (must **not** sum player victim counts)
  - Domestic / international counts from games
  - `average_opponent_rating`, `performance_rating` oracles
  - TT: `country_slice_at_event` at mid-cutoff vs oracle
- [ ] Register CLI in `scripts/amiga/__main__.py`
- [ ] Add to `prove` manifest (after `verify-player-slice` or sibling)

### Verification

```text
python -m scripts.amiga verify-country-slice
```

---

## CS-5 — PHP parity + prove green

### Goal

Finalize PHP produces identical country rows to Python replay.

### Tasks

- [ ] `site/public_html/amiga/ops/includes/amiga_country_slice_totals_lib.php`
- [ ] `site/public_html/amiga/ops/includes/amiga_country_slice_game_stats_lib.php` — mirror `CountryWorldCupSliceTracker`
- [ ] `site/public_html/amiga/ops/includes/amiga_country_slice_persist_lib.php`
- [ ] Wire `finalize_tournament.php` (or existing ops finalize path) — same hook point as Python
- [ ] Spot-check: replay one tournament via PHP ops if parity harness exists; else compare rows after full `prove`

### Verification

```text
python -m scripts.amiga prove
# Must include verify-country-slice — 0 fail
```

**STOP:** Dagh OK before UI slice.

---

## CS-6 — Hub wing 4 UI

### Goal

Five sortable country tables under `/amiga/world-cups/countries/*`.

### Pre-slice workshop (Dagh)

- [ ] Column placement per sub-wing (policy §5 catalog — which extras appear on which tab)
- [ ] Default sort per wing (proposed: Honours gold ↓, Results pts ↓ or perf rating ↓, Goals GF ↓, DDs DD ↓, Opponents opponents ↓)

### Tasks

- [ ] Routes in `includes/k2_amiga_routes.php` + `docs/url-routes.md`
- [ ] `site/public_html/amiga/world-cups/countries/*.php` — five pages + hub shell vars
- [ ] `includes/amiga_wc_countries_lb_lib.php` — read `amiga_country_slice_*` at cutoff
- [ ] `includes/amiga_wc_countries_table.php` — renderers (copy k2-table stack from player table)
- [ ] `includes/amiga_wc_countries_wing_body.inc.php`
- [ ] Hub sub-nav: add **Country stats** beside **Player stats** in `amiga_world_cups_hub_shell` / nav include
- [ ] Flags via `k2_amiga_country_flag.php`; `Unknown` text-only
- [ ] `as=` propagation on all links
- [ ] Column help strings in `lb_column_help.php` where new labels need tooltips

### Verification

```text
python scripts/audit_k2_table_compliance.py
# Browser: http://ratingskickoff.test/amiga/world-cups/countries/honours.php
# Spot: Italy row vs manual oracle; Unknown row shows 3 players
```

---

## CS-7 — Closure

### Goal

Docs + export path + registers.

### Tasks

- [ ] Policy status → **Implemented** (or **Shipped**)
- [ ] This plan status → **Complete**
- [ ] `amiga-data-contract.md` — register `040` tables
- [ ] `amiga-world-cups-hub-policy.md` — wing 4 shipped
- [ ] `feature-log.md` L1 row
- [ ] `PROJECT_MEMORY.md` Recent log
- [ ] Optional: archive starter prompt to `docs/archive/orchestration/` if used

### Verification

```text
python -m scripts.amiga prove
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
# Ready for WinSCP + staging import when Dagh chooses
```

---

## Environment

| Item | Value |
|------|--------|
| Local DB | `ko2amiga_db` via Laragon |
| PHP CLI | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Dev URL | `http://ratingskickoff.test/amiga/world-cups/countries/honours.php` |
| Holy loop | `python -m scripts.amiga prove` |
| Corrections | **`prove` only** — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) |

---

## Risks / watch points

| Risk | Mitigation |
|------|------------|
| Summing player `double_digits_victims` for country | **Forbidden** — game-loop union oracle in verify |
| Country perf rating drift vs player TPR | Reuse `performance_rating.py`; same min-games + all-0/1 NULL rules |
| Incremental vs full country recompute | Prefer v1 pattern matching player slice replay carry-forward; document in CS-3 |
| `Unknown` country merges later | Row key is token string — correcting player country requires re-`prove` |
| UI column sprawl | Workshop before CS-6; extras can start on Results only |

---

## Optional: starter prompt

When beginning **CS-1** in a **new chat**, add:

`docs/orchestration/agent-handoffs/amiga-wc-country-slice-STARTER-PROMPT.md`

Use [`agent-track-playbook.md`](orchestration/agent-track-playbook.md) § starter prompt template — links to policy + this plan, compressed WCCS table, “Do slice CS-1”, no tools on first reply until Dagh confirms.

---

## Revision log

| When | What |
|------|------|
| 2026-06-24 | Implementation plan created — slices CS-0–CS-7, reference files, STOP gates |
