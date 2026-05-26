# Post-game record bugs — exact lines in `docs/ratings_cpp.txt`

**Source:** `docs/ratings_cpp.txt` (`RatingProcedureUnity`) — snapshot of prod post-game logic, not deployed from this repo.

**How to read this doc**

| Label | Meaning |
|-------|---------|
| **EXACT** | Line is in the excerpt; reproduces wrong dates under stated conditions when simulated. |
| **NOT IN EXCERPT** | Staging symptom cannot be produced from the excerpt as written; live prod may differ — Steve must diff. |

---

## Bug 1 — `>=` instead of `>` (EXACT, many lines)

**Mechanism:** Whenever the player’s value is still **equal to** the server record and the condition runs, `GameDate` is written again. Incumbent loses “first set” date on ties.

**Fix:** Change `>=` to `>` on every server-record comparison below.

### Cumulative totals (no boolean) — date drifts on **every game** by current record holder

These run **every rated game** for both players. Once player P is holder and `P’s count >= server`, **each further game by P** refreshes the date.

| Record | Excerpt lines | Condition |
|--------|---------------|-----------|
| Most games played | **1574–1587** | `NumberGamesA >= MostGamesPlayedS` / `NumberGamesB >= …` |
| Most wins | **1590–1603** | `NumberWinsA >= MostWinsS` |
| Most goals scored (career) | **1619–1632** | `GoalsForA >= MostGoalsScoredS` |
| Most goals in one game | **1674–1688** | `goalsA >= MostGoalsScoredInOneGameS` |
| Biggest win margin | **1692–1707** | `GoalDifference >= BiggestWinDifferenceS` (on win) |
| Biggest draw sum | **1710–1718** | `SumOfGoals >= BiggestDrawSumS` (on draw) |
| Biggest sum of goals | **1722–1731** | `SumOfGoals >= BiggestSumOfGoalsS` |
| Most double digits | **1734–1747** | `DoubleDigitsA >= MostDoubleDigitsS` (on DD game) |
| Most clean sheets | **1750–1763** | `CleanSheetsA >= MostCleanSheetsS` (on CS game) |
| Biggest rating ascent | **1868–1881** | `CurrentRatingAscentA >= BiggestRatingAscentS` |
| Biggest peak rating | **1884–1897** | `NewRatingA >= BiggestPeakRatingS` |

**Verified example (EXACT):** `MostGamesPlayed` lines **1574–1587** — after geo4444 reaches 11,087 games, the excerpt logic updates `MostGamesPlayedDate` on **87** later geo4444 games; last write **2026-05-18** (not the day the record was first set).

### Streak records (EXACT `>=`, wrong variable — see Bug 2)

| Record | Excerpt lines | Condition |
|--------|---------------|-----------|
| Longest winning streak | **1900–1913** | `WinningStreakA >= LongestWinningStreakS` |
| Longest draw streak | **1916–1928** | `DrawingStreakA >= LongestDrawingStreakS` (draw games only) |
| Longest non-loss streak | **1932–1945** | `NonLossStreakA >= LongestNonLossStreakS` |

**GianniT LWS/LNLS staging (Dec 2023):** Simulating **only** lines **1900–1945** as written gives last LWS date **2020-11-23**, not 2023-12-26. So staging’s Gianni streak dates are **NOT IN EXCERPT** unless prod compares `LongestWinningStreakA` / `LongestNonLossStreakA` with `>=` (career max still 70/120 on every later game).

### Network counts (boolean + `>=`) — date wrong on **tie** when boolean is 1

Boolean is set only when count **strictly** increases (e.g. **1067–1070**, **1104–1107**, **827–830**). Server update:

| Record | Excerpt lines | Condition |
|--------|---------------|-----------|
| Most different opponents | **1791–1804** | `DifferentOpponentsA >= MostDifferentOpponentsS && MostDifferentOpponentsBooleanA == 1` |
| Most different victims | **1807–1820** | `DifferentVictimsA >= MostDifferentVictimsS && MostDifferentVictimsBooleanA == 1` |
| Most DD victims | **1823–1836** | `DoubleDigitsVictimsA >= … && MostDoubleDigitsVictimsBooleanA == 1` |
| Most clean sheet victims | **1839–1852** | `CleanSheetsVictimsA >= … && MostCleanSheetsVictimsBooleanA == 1` |

**Tie sub-bug (EXACT):** If two players reach the same count on different games, the second `>=` overwrites holder/date even though the first player “set” the record first. Fix: `>` not `>=`.

**Eternalstudent opponents/victims (local replay):** Simulating **1791–1812** as written gives last opponent/victim date **2026-05-04** when counts reach 103/101 — **not** “every game after.” Staging drift to last game day is **NOT IN EXCERPT** unless prod drops `&& MostDifferentOpponentsBooleanA == 1` (then **every** game at 103 refreshes date — simulated last **2026-05-18**).

**Fiery CS victims Mar 13 (staging):** Simulating **1839–1852** with boolean gives last date **2026-01-30**. On **2026-03-13** Fiery had **no** clean-sheet games, so boolean cannot be 1. Mar 13 is **NOT IN EXCERPT** unless prod omits the boolean test.

---

## Bug 2 — Streak server block uses **current** streak (EXACT)

**Lines:** **1900–1945**

**Mechanism:** Server record compares `WinningStreakA` / `NonLossStreakA` (current run) to `LongestWinningStreakS`, not `LongestWinningStreakA` (career max on `playertable`).

**playertable** correctly uses `LongestWinningStreakA` at **1020–1054** (`WinningStreakA > LongestWinningStreakA`).

**Fix (Steve):**

```cpp
if (LongestWinningStreakA > LongestWinningStreakS) {
    LongestWinningStreakS = LongestWinningStreakA;
    // …
}
```

Same for `LongestNonLossStreakA` / `LongestDrawingStreakA` (draw branch).

---

## Bug 3 — Ratio leaders re-query every game (EXACT, separate issue)

**Lines:** **1606–1616**, **1635–1671**, **1766–1788**, **1855–1865**

Each game, `SELECT … MAX(WinRatio) …` (etc.) and overwrites ratio leader columns on `generalstatstable` with **this game’s** `GameDate`. PHP now reads ratio leaders from `playertable`; these blocks should be **deleted** — see [`records-post-game-exception.md`](records-post-game-exception.md).

---

## Summary table (your examples)

| Case | Exact line(s) in excerpt? | What produces staging-style “last game” date |
|------|---------------------------|---------------------------------------------|
| Gianni LWS / LNLS → 2023-12-26 | **NOT** 1900–1945 as written | Prod likely `Longest*StreakA >=` (not in excerpt) |
| Fiery CS victims → 2026-03-13 | **NOT** 1839–1852 with boolean | Prod likely **1839–1845 without `&& Boolean==1`** |
| Eternalstudent opponents/victims | **NOT** 1791–1812 with boolean | Prod likely **1791–1804 without boolean**, or wrong field |
| geo4444 most games → drifts | **YES 1574–1587** | Every game while holder (`>=`) |
| Any record tie at same value | **YES** all `>=` rows above | Second player (or same player if boolean wrongly 1) |

---

## Steve verification checklist

1. Diff live `RatingProcedureUnity` against `docs/ratings_cpp.txt` (streak variables and boolean gates).
2. Search for `LongestWinningStreakS` / `MostCleanSheetsVictimsS` assignments — confirm `>=` and `&& Boolean`.
3. After fix: `>` everywhere; streaks use `Longest*StreakA`; keep booleans for network counts.
4. Rebuild `generalstatstable` from replay or one-time SQL backfill after deploy.

**Python contract:** `scripts/ladder/server_records.py` — strict `>`, career longest for streaks, `new_cs_victim` / `new_opponent` gates for network rows. Check: `python -m scripts.ladder.golden_record_checks`.
