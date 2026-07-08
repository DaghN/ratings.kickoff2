# Amiga HoF career cumulative rise dates — implementation plan (SCH-030)

> **In progress (Jun 2026):** Not shipped — do **not** treat as complete. **When implementing:** sign-off = **`simul`** on **`ko2amiga_work`**; oracle **`prove`** references below are template only.

**Status:** In progress (Jun 2026)  
**Policy:** [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) § SCH-030 / D11  
**Parent track:** [`amiga-stored-field-semantics-plan.md`](amiga-stored-field-semantics-plan.md)

---

## Goal

HoF `*Date` for legacy **career cumulative** rows (`MostGamesPlayed`, `MostWins`, …) = event where the holder’s displayed value **last rose**, not `record_date` (last game played).

Anchor: **`tournament_id` + `event_date`** (event finalize grain — not game id).

---

## Slices

| Slice | Goal | Deliverables | Proof |
|-------|------|--------------|-------|
| **0** | Policy + manifest | D11 in record-date policy; manifest P1 → SCH-030 | — |
| **1** | DDL SCH-030 | `030_career_rise_dates.sql` + `schema_bundles.py` | Apply on local DB |
| **2** | Career rise writers | `career_rise.py`; wire `snapshot_persist` / `snapshot_row` | `test_career_rise_dates.py` |
| **3** | Realm projection | `realm_incremental.py` + PHP `amiga_realm_incremental_lib.php` `_HOLDER_DATE_FIELD` | `test_realm_holder_dates.py` extended |
| **4** | Verify + prove | `verify_hof_geo_year` career rise oracle + HoF dates for 10 rows | `prove` green |
| **5** | PHP snapshot parity | `amiga_career_rise_lib.php` + `amiga_event_snapshot_persist.php` | optional smoke |
| **6** | Closure | data-contract, MEMORY, export | export + staging handoff |

---

## Metrics (10 × 2 columns)

| HoF prefix | Career value column | Rise column prefix |
|------------|---------------------|-------------------|
| `MostGamesPlayed` | `NumberGames` | `number_games` |
| `MostWins` | `NumberWins` | `number_wins` |
| `MostGoalsScored` | `GoalsFor` | `goals_for` |
| `MostDoubleDigits` | `DoubleDigits` | `double_digits` |
| `MostCleanSheets` | `CleanSheets` | `clean_sheets` |
| `MostDifferentOpponents` | `DifferentOpponents` | `different_opponents` |
| `MostDifferentVictims` | `DifferentVictims` | `different_victims` |
| `MostDoubleDigitsVictims` | `DoubleDigitsVictims` | `double_digits_victims` |
| `MostCleanSheetsVictims` | `CleanSheetsVictims` | `clean_sheets_victims` |
| `BiggestRatingAscent` | `BiggestRatingAscent` | `biggest_rating_ascent` |

**Out of scope:** `BiggestPeakRating` (game-anchored); SCH-029 six; year peaks; ratio leaders; single-game HoF rows.

---

## Starter prompt

```text
Today: SCH-030 career rise — slice N per docs/amiga-hof-career-rise-implementation-plan.md.
Event-anchor tournament_id + event_date only. prove must pass verify-hof-geo-year.
```
