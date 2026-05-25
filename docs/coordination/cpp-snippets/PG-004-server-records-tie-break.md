# PG-004 — Server records: ratio leaders off `generalstatstable`

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** ready for Steve
**Schema:** [schema-register.md](../schema-register.md) **SCH-003**
**Feature:** Records page (`server2.php`)

---

## Summary

Drop **28 columns** on `generalstatstable` that duplicated ratio/average **player** record leaders. Leaders come from **`playertable`** at page load (PHP). Production C++ must **stop writing** those columns. **Keep** server-wide `DoubleDigitsRatio` / `CleanSheetsRatio` on row id=1 (totals across all games, not a player leader).

---

## Schema (Steve — staging + prod `kooldb`)

Apply the same migration we use locally:

**File:** [`schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql`](../../schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql)

```sql
ALTER TABLE `generalstatstable`
  DROP COLUMN `BiggestWinRatio`,
  DROP COLUMN `BiggestGoalsForAverage`,
  -- … (full list in migration file: 7 metrics × value + ID + Name + Date)
```

**Order of operations with website deploy:**

1. Deploy PHP (`records_ratio_leaders.php` + `server2.php` using `playertable` queries).
2. Run migration **002** on `kooldb` (drops columns).
3. Deploy C++ that no longer references dropped columns.

If C++ is deployed before the migration, ensure it does not `UPDATE`/`SELECT` removed column names.

---

## C++ post-game (Steve)

**Anchor:** `docs/ratings_cpp.txt` — `RatingProcedureUnity`, blocks ~1605–1865.

1. **Delete** seven per-game `SELECT … WinRatio / AverageGoalsFor / …` blocks that refreshed ratio leaders on `generalstatstable`.
2. **Keep** updating `playertable` ratio columns each game (unchanged).
3. **Non-ratio** hall-of-fame rows on `generalstatstable`: change `>=` to `>` so ties keep incumbent (**PG-004b**).

---

## PHP (already in repo)

- `site/public_html/includes/records_ratio_leaders.php`
- `server2.php` — `mysqli_fetch_assoc` on `generalstatstable`; ratio rows from include

Spec: [`docs/RECORDS_PAGE_DATA.md`](../../RECORDS_PAGE_DATA.md)

---

## Replay (Python)

- `scripts/ladder/sql/generalstatstable.sql` — CREATE without dropped columns (new DBs).
- `scripts/ladder/generalstats.py` — no longer writes ratio leader fields.
- Local: `schema/apply_local.ps1` applies **002** on `ko2unity_db`.

---

## Columns removed (28)

| Metric | Columns dropped |
|--------|-------------------|
| Best win ratio | `BiggestWinRatio`, `*ID`, `*Name`, `*Date` |
| Best attack average | `BiggestGoalsForAverage`, … |
| Best defense average | `SmallestGoalsAgainstAverage`, … |
| Best goal ratio | `BiggestGoalRatio`, … |
| Best DD ratio (player) | `BiggestDoubleDigitsRatio`, … |
| Best CS ratio (player) | `BiggestCleanSheetsRatio`, … |
| Biggest avg opponent rating | `BiggestAverageOpponentRating`, … |

**Not dropped:** `DoubleDigitsRatio`, `CleanSheetsRatio` in the headline aggregate section (server totals).

---

## Smoke check

| Step | Expected |
|------|----------|
| After 002 on local | `SHOW COLUMNS FROM generalstatstable` has no `BiggestWinRatio` |
| Records page | Win ratio = top `playertable.WinRatio` (≥30 games), not stale GST row |
| C++ post-game | No compile/runtime reference to dropped names |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | SCH-003 migration + Steve DROP recommendation |
| 2026-05 | Agent | Ratio leaders moved to playertable queries |
