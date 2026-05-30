# Records post-game exception — `generalstatstable` / Hall of Fame

**Why this file exists:** Server records are not simple aggregate bookkeeping. Staging defects, tie policy, UTC dates, and ratio-column removal need worked examples for Steve’s prod C++ merge. **All other derived tables:** behavior only in [`website-data-contract.md`](../website-data-contract.md) — no per-table C++ snippet packs in repo.

**Schema:** [schema-register.md](schema-register.md) **SCH-003**
**Feature:** Hall of Fame (`server2.php`)
**Behavior authority:** [`website-data-contract.md`](../website-data-contract.md) — `generalstatstable` section.
**Regression matrix:** [`staging-post-game-record-defects.md`](../staging-post-game-record-defects.md)

---

## Summary

Three categories of change to the `generalstatstable` post-game update:

1. **DELETE** — ratio/average leader writes (7 blocks, 28 columns) — leaders now come from `playertable` at page load.
2. **CHANGE** — non-ratio record tie policy from `>=` to `>` — first holder keeps the record until strictly beaten.
3. **ADD** — UTC timezone requirement — record dates must be UTC.

**Separate handoff (this file does not cover it):** `playertable` personal record pointers and inverse victim/culprit counts (`BiggestLossVictims`, `BiggestWinCulprits`, `MostGoalsConcededVictims`, etc.) must also move from legacy **`>=` to `>`** in the per-game C++ block. Required behaviour and checklist: [`website-data-contract.md`](../website-data-contract.md) § *Personal record pointers and record-holder victim/culprit counts*. Do not mark records post-game “done” without that pass.

---

## 1. DELETE: Ratio leader blocks

**Current C++ (lines ~1605–1788):** Seven per-game blocks that query `playertable` for ratio/average leaders and write them to `generalstatstable`:

- `BiggestWinRatio` (line ~1606)
- `BiggestGoalsForAverage` (line ~1635)
- `SmallestGoalsAgainstAverage` (line ~1648)
- `BiggestGoalRatio` (line ~1661)
- `BiggestDoubleDigitsRatio` (line ~1766)
- `BiggestCleanSheetsRatio` (line ~1778)
- `BiggestAverageOpponentRating` (line ~1855)

**Action:** Delete all seven `SELECT ... FROM playertable WHERE ... = (SELECT MAX/MIN ...)` blocks and their corresponding `setDouble`/`setInt`/`setString` bindings in the UPDATE statement.

**Also delete from the UPDATE statement:** The `BiggestWinRatio`, `BiggestGoalsForAverage`, `SmallestGoalsAgainstAverage`, `BiggestGoalRatio`, `BiggestDoubleDigitsRatio`, `BiggestCleanSheetsRatio`, `BiggestAverageOpponentRating` value/ID/Name/Date columns (28 total).

**Keep unchanged:** `playertable` ratio column writes (`WinRatio`, `AverageGoalsFor`, etc.) — these are still needed for page-load queries.

---

## 2. CHANGE: Tie policy `>=` to `>`

**Current C++ behavior:** Most non-ratio record checks use `>=`, which overwrites the incumbent on every tie:

```cpp
// CURRENT (WRONG): overwrites record on tie
if (NumberGamesA >= MostGamesPlayedS)
```

**Required behavior:** Use strict `>` so the first holder keeps the record until actually beaten:

```cpp
// CORRECT: first holder keeps on tie
if (NumberGamesA > MostGamesPlayedS)
```

**Lines to change (all `>=` to `>` in the `generalstatstable` update section, lines ~1574–1945):**

| Record | Current lines | Change |
|--------|---------------|--------|
| `MostGamesPlayed` | ~1574, ~1581 | `>=` to `>` |
| `MostWins` | ~1590, ~1597 | `>=` to `>` |
| `MostGoalsScored` (total) | ~1619, ~1626 | `>=` to `>` |
| `MostGoalsScoredInOneGame` | ~1674, ~1682 | `>=` to `>` |
| `BiggestWinDifference` | ~1692, ~1700 | `>=` to `>` |
| `BiggestDrawSum` | ~1710 | `>=` to `>` |
| `BiggestSumOfGoals` | ~1722 | `>=` to `>` |
| `MostDoubleDigits` | ~1734, ~1741 | `>=` to `>` |
| `MostCleanSheets` | ~1750, ~1757 | `>=` to `>` |
| `MostDifferentOpponents` | ~1791, ~1798 | `>=` to `>` |
| `MostDifferentVictims` | ~1807, ~1814 | `>=` to `>` |
| `MostDoubleDigitsVictims` | ~1823, ~1830 | `>=` to `>` |
| `MostCleanSheetsVictims` | ~1839, ~1846 | `>=` to `>` |
| `BiggestRatingAscent` | ~1868, ~1875 | `>=` to `>` |
| `BiggestPeakRating` | ~1884, ~1891 | `>=` to `>` |
| `LongestWinningStreak` | ~1900, ~1907 | `>=` to `>` |
| `LongestDrawingStreak` | ~1916, ~1923 | `>=` to `>` |
| `LongestNonLossStreak` | ~1932, ~1939 | `>=` to `>` |

**Important:** This is a deliberate behavior change, not a bug fix in the traditional sense. The intended product behavior is that records are permanent once set — a new player must actually **beat** the record to claim it.

---

## 3. ADD: UTC timezone requirement

**Current C++ behavior:** `GameDate` is read from the game insert and written directly to `generalstatstable` date columns. If the MySQL session timezone is not UTC, dates stored will reflect the session TZ offset.

**Required behavior:** The post-game connection must ensure record dates are stored in UTC. Either:
- Pin `SET time_zone = '+00:00'` at session start, OR
- Ensure `GameDate` is derived from the `ratedresults.Date` timestamp which MySQL already stores in UTC internally.

Since `ratedresults.Date` is a `TIMESTAMP` column, MySQL stores it in UTC regardless of session TZ. But when the C++ reads it back as a string for `GameDate`, it converts through the session TZ. So the connection **must** have `SET time_zone = '+00:00'` to get correct UTC strings.

---

## Schema (Steve — staging + production DB)

**File:** [`schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql`](../../schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql)

**Order of operations:**

1. Deploy PHP (already using `playertable` for ratio leaders).
2. Run migration **002** on `kooldb` (drops 28 columns).
3. Deploy C++ with all three changes (delete ratio blocks, `>=` to `>`, UTC pin).

---

## 4. ADD: Rated play streak records (SCH-014)

**Columns on `generalstatstable` id=1** (from `schema/migrations/014_player_play_streaks.sql`):

- `LongestDailyPlayStreak`, `LongestDailyPlayStreakID`, `LongestDailyPlayStreakName`, `LongestDailyPlayStreakDate`, `LongestDailyPlayStreakGameID`
- `LongestWeeklyPlayStreak`, `LongestWeeklyPlayStreakID`, `LongestWeeklyPlayStreakName`, `LongestWeeklyPlayStreakDate`, `LongestWeeklyPlayStreakGameID`

**Per-game flow (after `player_period_games` + `player_play_streaks` row update):**

1. Update each player’s `player_play_streaks` day/week row (PHP reference: `k2_play_streak_apply_game` in `includes/player_play_streaks.php`).
2. Only if that player’s **`best_streak` strictly increased**, compare to the HoF column set for that `streak_type`.

**HoF tie policy (differs from simple `>` on value):**

| Compare | Winner |
|---------|--------|
| Longer `best_streak` | Challenger |
| Same length, earlier `best_achieved_at` (`ratedresults.Date` of establishing game) | Earlier |
| Same length, same `best_last_game_id` (typical mutual game) | Player where `player_id = ratedresults.idB` |

Establishing game = **first** rated game (`MIN(id)`) on the last UTC day / last UTC week of the run — not the last game that day (avoids punishing extra games).

**Not** the same as `LongestWinningStreak` on `playertable` — those are result streaks; these are “played at least one rated game” streaks.

Contract: [`website-data-contract.md`](../website-data-contract.md) § `player_play_streaks`. Staging: [`play-streaks-staging-handoff.md`](play-streaks-staging-handoff.md) — **SCH-014 + REP-015 verified** May 2026 (Steve); HoF rows live on staging `server2.php`; prod C++ post-game still pending.

---

## Replay (Python — already implements correct behavior)

- `scripts/ladder/server_records.py` — `_try_int_max`, `_try_float_max`, `_try_pair_max` all use strict `>`.
- `scripts/ladder/engine.py` — connection pinned to `SET time_zone = '+00:00'`.
- `scripts/ladder/generalstats.py` — does not write ratio leader fields.

---

## Golden record validation

After replay or C++ deployment, verify with `python -m scripts.ladder.golden_record_checks`:

- `LongestDrawingStreak = 5 / j1mpst3r / 2020-06-13 23:25:53 UTC` (not overwritten by later ties)
- `MostCleanSheetsVictims = 76 / FieryPhoenix / 2026-01-30 13:22:21 UTC` (not drifted to last game)
- `BiggestPeakRating` date = first reach of peak (not last game at same peak)
- All record dates are UTC (no Estonia +3 or other local offsets)

---

## Smoke check

| Step | Expected |
|------|----------|
| After 002 on staging | `SHOW COLUMNS FROM generalstatstable` has no `BiggestWinRatio` |
| Records page | Win ratio = top `playertable.WinRatio` (>=20 games / `K2_ESTABLISHED_MIN_GAMES`), not stale GST row |
| C++ post-game | No compile/runtime reference to dropped column names |
| After next game | Record dates are UTC; tied records are not overwritten |

---

## PG-004c — Streak records audit (GianniT / staging Dec 2023)

**Symptom (staging/prod):** `LongestWinningStreak` and `LongestNonLossStreak` show correct values (70 / 120) and holder (GianniT), but **both dates** show **2023-12-26** — Gianni’s last game day — instead of **2020-11-23** and **2022-02-16** when those streaks were actually set.

**Local after Python replay (correct):** `2020-11-23 23:08:10` and `2022-02-16 22:28:49` UTC.

### Two different layers in `ratings_cpp.txt`

| Layer | Variable | Update rule | Purpose |
|-------|----------|---------------|---------|
| **playertable** (lines ~1020–1054) | `WinningStreakA` → `LongestWinningStreakA` | `WinningStreakA > LongestWinningStreakA` | Career max on player row — **correct** |
| **generalstatstable** (lines ~1900–1945) | `WinningStreakA` vs `LongestWinningStreakS` | `WinningStreakA >= LongestWinningStreakS` | Server hall-of-fame row — **wrong operator** |

playertable logic is fine. The server-record block is where staging/prod dates go wrong.

### What the excerpt actually does (lines 1899–1945)

```cpp
// Update longest winning streak
if (WinningStreakA >= LongestWinningStreakS)
{
    LongestWinningStreakS = WinningStreakA;
    LongestWinningStreakIDS = IdenA;
    LongestWinningStreakNameS = NameA;
    LongestWinningStreakDateS = GameDate;
}
// … same pattern for NonLossStreakA / LongestNonLossStreakS
```

Problems in this block:

1. **`>=` instead of `>`** — ties refresh holder/date (general tie bug; PG-004b).
2. **Compares current streak (`WinningStreakA`), not career max (`LongestWinningStreakA`)** — easy to misread the column name `LongestWinningStreakS` and “fix” prod by switching to `LongestWinningStreakA` **without** changing `>=`. That would cause exactly the Gianni symptom.

### GianniT simulation on full history

| Rule | Updates after first record set? | Last date written |
|------|------------------------------|-------------------|
| Excerpt as written: `WinningStreakA >= 70` | **No** — only once when he hits 70 in 2020 | 2020-11-23 |
| **Prod-like mistake:** `LongestWinningStreakA >= 70` | **Yes** — every Gianni game while career max stays 70 (182 games) | **2023-12-26** (last game) |
| **Intended:** `LongestWinningStreakA > 70` or `WinningStreakA > 70` | Only when record strictly broken | 2020 / 2022 |

Same pattern for non-loss: `LongestNonLossStreakA >= 120` → last update **2023-12-26** (139 games after 2022-02-16).

**Conclusion:** Staging’s Dec 26 dates match **“compare career longest with `>=`”**, not the excerpt’s **“compare current streak with `>=`”**. Steve should confirm live C++ against `docs/ratings_cpp.txt` — if prod uses `LongestWinningStreakA` / `LongestNonLossStreakA` in the server block, that is the smoking gun.

### Required fix (Steve)

Use **career max** and **strict beat**:

```cpp
if (LongestWinningStreakA > LongestWinningStreakS)
{
    LongestWinningStreakS = LongestWinningStreakA;
    LongestWinningStreakIDS = IdenA;
    LongestWinningStreakNameS = NameA;
    LongestWinningStreakDateS = GameDate;
}
if (LongestNonLossStreakA > LongestNonLossStreakS)
{
    LongestNonLossStreakS = LongestNonLossStreakA;
    // …
}
```

Do **not** only change `>=` → `>` while still comparing `WinningStreakA` — that fixes ties but is the wrong metric for the stored value. Do **not** use `LongestWinningStreakA >= LongestWinningStreakS`.

Draw streak block (lines 1916–1928) has the same `>=` issue but is gated on `ActualScore == 0.5`; apply the same `>` rule and keep using **current** `DrawingStreakA` (only meaningful on draw games).

### Gianni — longest non-loss streak (same bug family)

`LongestNonLossStreak` is updated in the **same block** as winning streak (lines 1932–1945) with the same `NonLossStreakA >= LongestNonLossStreakS` pattern.

| Rule | Last date written for Gianni |
|------|------------------------------|
| Excerpt: `NonLossStreakA >= 120` | **2022-02-16** (only when he hits 120) |
| Prod-like: `LongestNonLossStreakA >= 120` | **2023-12-26** (every game after career max is 120) |
| Intended: `LongestNonLossStreakA > 120` | **2022-02-16** |

So both Gianni streak dates jumping to his last game day are **one class of bug**, not two. Fix both in the same C++ edit.

### Python replay (`server_records.py`)

Replay uses **career longest** with strict `>` (`st.longest_winning_streak`, `st.longest_non_loss_streak`). That matches the intended C++ fix and passes golden checks.

---

## PG-004d — Victim-count records audit (FieryPhoenix / staging Mar 2026)

**Symptom (staging/prod):** `MostCleanSheetsVictims = 76 / FieryPhoenix` but date **2026-03-13** (last game) instead of **2026-01-30** when victim count first reached 76.

**Local after replay (correct):** `2026-01-30 13:22:21` UTC. Fiery’s last game is also 2026-03-13, so “date = last game” is the same fingerprint as Gianni’s streak bug, but the mechanism is **not identical**.

### What the excerpt does (two steps)

**Step 1 — playertable (lines 827–830):** recount distinct CS victims; set boolean only on strict increase:

```cpp
if (CleanSheetsVictimsTemp > CleanSheetsVictimsA) {
    MostCleanSheetsVictimsBooleanA = 1;
    CleanSheetsVictimsA = CleanSheetsVictimsTemp;
}
```

**Step 2 — generalstatstable (lines 1839–1845):**

```cpp
if (CleanSheetsVictimsA >= MostCleanSheetsVictimsS && MostCleanSheetsVictimsBooleanA == 1)
{
    MostCleanSheetsVictimsS = CleanSheetsVictimsA;
    MostCleanSheetsVictimsIDS = IdenA;
    MostCleanSheetsVictimsNameS = NameA;
    MostCleanSheetsVictimsDateS = GameDate;
}
```

Comments at lines 700–701 explain the intent: boolean avoids false server alarms when the record holder merely equals the record on a routine DD/CS game.

Booleans are initialised to **0** each game (line 51); they are **not** sticky across games in the excerpt.

### Simulation on full history

| Rule | Fiery server date updates | Last date |
|------|---------------------------|-----------|
| **Excerpt** (boolean + `>=`, only when `new_cs_victim`) | **15** times (each new victim tier) | **2026-01-30** when count hits **76** |
| **Missing boolean:** `CleanSheetsVictimsA >= server` on **every** Fiery game once count is 76 | **5,999** games | **2026-03-13** (last game) |
| **Intended:** `>` and only when count **strictly** increases | **1** time at 76 | **2026-01-30** |

Critical detail: on **2026-03-13** Fiery played **12 games with zero clean sheets** (no `CSPlayerA` / `CSPlayerB`). So if staging shows Mar 13, prod **cannot** be running the excerpt’s boolean gate as written — nothing on that day sets `MostCleanSheetsVictimsBooleanA = 1`.

**Conclusion:** Staging’s Mar 13 date matches prod (or historical C++) that updates server CS-victim record whenever `CleanSheetsVictimsA >= MostCleanSheetsVictimsS` **without** requiring `MostCleanSheetsVictimsBooleanA == 1`, so **every Fiery game** after he reaches 76 refreshes the date — including ordinary non-CS games on his last day.

Dagh’s hypothesis (“every Fiery game updates the date”) is **directionally right**; the precise bug is **`>=` without the boolean gate** (or boolean omitted in deployed code), not “boolean stuck on.”

### Same pattern on other victim/opponent records

These use the same **boolean + `>=`** shape in the excerpt:

- `MostDifferentOpponents` / `MostDifferentVictims`
- `MostDoubleDigitsVictims`
- `MostCleanSheetsVictims`

All need the same Steve fix: **`>`** and update only when the boolean was set this game (count strictly increased), or equivalent in replay.

### Required fix (Steve)

```cpp
if (CleanSheetsVictimsA > MostCleanSheetsVictimsS && MostCleanSheetsVictimsBooleanA == 1)
{
    MostCleanSheetsVictimsS = CleanSheetsVictimsA;
    // ...
}
```

Keep the boolean; do **not** drop it and compare on every game. Do **not** use `>=`.

Python replay already gates on `st.game_flags.new_cs_victim` with strict `>` in `server_records.py`.

### Golden checks

`python -m scripts.ladder.golden_record_checks` — Fiery CS victims date must contain `2026-01-30`, must **not** contain `2026-03-13`.

---

### Golden checks (streak + victims)

`python -m scripts.ladder.golden_record_checks` — Gianni LWS/LNLS dates; Fiery CS victims date; see checks in script.

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| 2026-05 | Agent | SCH-003 migration + Steve DROP recommendation |
| 2026-05 | Agent | Ratio leaders moved to playertable queries |
| 2026-05 | Agent | Strengthened: explicit tie policy table, UTC requirement, golden checks, behavior-change framing |
| 2026-05 | Agent | PG-004c: GianniT streak date audit — `Longest*A >=` prod hypothesis vs excerpt `WinningStreakA >=` |
| 2026-05 | Agent | PG-004d: Fiery CS victims — Mar 13 needs `>=` without boolean; excerpt boolean would stop at Jan 30 |
