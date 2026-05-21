# Replay v1 — locked scope & reset manifest (P0)

**Status:** **P1 implemented** (`scripts/ladder/`, May 2026). Verified on local **`ko2unity_db`**: 74,870 games replayed; 0 NULL `NewRating*`; draws `WinnerID = -1` (9,053).

**Authority:** Product intent in `docs/ladder-engine-plan.md`. If this doc and that plan disagree on v1 scope, **this doc wins** until Dagh says otherwise.

**Database:** Local / dev **`ko2unity_db`** (`ratedresults`, `playertable`). See `docs/LOCAL_DEV.md`.

---

## 1. V1 purpose (one sentence)

Recalculate **Elo and core per-game rating columns** from fixed game facts in chronological order, **without rating decay**, and refresh a **minimal** set of `playertable` career fields — enough to trust **ranked sort** and **rating history charts** on the local site.

---

## 2. Locked formula parameters (sandbox defaults)

| Parameter | V1 value | Notes |
|-----------|----------|--------|
| **K-factor** | **32** | Common ladder default; document in replay logs. Confirm with Steve before prod parity. |
| **Starting `Rating`** (at reset) | **1600.0** | Matches `playertable.Rating` column default in schema snapshot. |
| **Rating decay** | **Off** | Not in our engine; not in supplied C++ excerpt. |
| **Expected score** | `1 / (1 + 10^((Rb - Ra) / 400))` | Player A perspective; B is `1 - ExpectedScoreA`. |
| **`ActualScore` from goals** | A win `1`, draw `0.5`, B win `0` | If `GoalsA > GoalsB` → 1; equal → 0.5; else 0. |
| **`WinnerID` from goals** | A win → `idA`; B win → `idB`; draw → **`-1`** (matches C++ and current DB — see `docs/ratedresults-schema.md`). Recompute on replay; do not read pre-reset values as input. |
| **Replay order** | `ORDER BY Date ASC, id ASC` | Same as charts / `docs/ladder-engine-plan.md`. |
| **`generalstatstable`** | **Batch rebuild** | DDL `scripts/ladder/sql/generalstatstable.sql`; reset NULLs row `id=1`; filled at end of replay (not per-game). |
| **`resulttable`** | **Untouched** | Legacy / unrated rows; not part of online replay v1. |

---

## 3. Tables touched in v1

| Table | Reset? | Replay (`apply_game`)? |
|-------|--------|-------------------------|
| **`ratedresults`** | Clear derived columns (§4) | Yes — per game |
| **`playertable`** | Clear derived columns (§5); set `Rating = 1600` | Yes — after each game (minimal set §6) |
| **`generalstatstable`** | No | No |
| **`resulttable`** | No | No |

---

## 4. `ratedresults` — reset manifest

### 4.1 Preserve (immutable game facts)

Do **not** change:

- `id`
- `Date`
- `idA`, `idB`
- `NameA`, `NameB` (denormalized snapshots; optional future normalization)
- `GoalsA`, `GoalsB`

### 4.2 Clear or NULL (rebuilt by replay)

Set to **`NULL`** (preferred) before replay:

| Column group | Columns |
|--------------|---------|
| Pre/post Elo | `RatingA`, `RatingB`, `RatingDifference`, `ExpectedScoreA`, `ExpectedScoreB`, `AdjustmentA`, `AdjustmentB`, `NewRatingA`, `NewRatingB` |
| Outcome encoding | `ActualScore`, `WinnerID` |
| Derived from goals | `SumOfGoals`, `GoalDifference` |
| Legacy flags | `HomeWin`, `Draw`, `AwayWin`, `DDPlayerA`, `DDPlayerB`, `CSPlayerA`, `CSPlayerB` |

**v1 `apply_game` will repopulate** the cleared columns in the “Clear” groups from goals + Elo math.

---

## 5. `playertable` — reset manifest

### 5.1 Preserve (identity, account, prefs, telemetry)

Do **not** change:

- **Keys & identity:** `ID`, `Name`, `Email`, `CryptPassword`, `GUID`
- **Account / legal:** `LegalAccepted`, `JoinDate`
- **Prefs / profile / cosmetics:** `Pref_*`, `Profile_*`, `Country`, `Language`, `AvoidRank`, `Challenge1`, `Challenge2`, `NewForumPosts`, `Pref_UseCustomKits`, kit colour fields
- **Telemetry / feedback blobs:** all `Feedback_*` columns
- **Network / lobby (not replay-derived):** `IsOnline`, `IPPort`, `LobbyTime`
- **Listing flags (website-owned for v1):** `Display`, `PlayerRank` — leave as imported; PHP “established” rules stay on site (`docs/playertable-schema.md`)
- **Login activity (not recomputed in v1):** `LastLogin`, `LastActive`

**Local dump note:** If **`KungFu*`** columns still exist (pre-migration snapshot), **leave them unchanged** in v1; we do not read or write them.

### 5.2 Reset to career baseline (rebuilt by replay)

**`Rating`:** set **`1600.000000`** for **every row** (not only active players).

**All other derived career fields:** set to **`NULL`**, except use schema **sentinels** where `NOT NULL` without a sensible NULL:

| Column | Reset value |
|--------|-------------|
| `LeastGoalsScored` | `50` (NOT NULL default in snapshot) |
| `LeastGoalsConceded` | `50` |
| `SmallestSumOfGoals` | `50` |
| `LowestRating` | `5000.00` (legacy “no real record yet” sentinel; PHP uses `!= 5000`) |
| `LowestRatedCulprit` | `5000.00` (NOT NULL default) |

**Columns to NULL** (non-exhaustive; implement as explicit list in code):

- Game counts & results: `NumberGames`, `NumberWins`, `NumberDraws`, `NumberLosses`, `WinRatio`, `DrawRatio`, `LossRatio`
- Goals: `GoalsFor`, `GoalsAgainst`, `AverageGoalsFor`, `AverageGoalsAgainst`, `GoalRatio`
- Extremes (values): `MostGoalsScored`, `MostGoalsConceded`, `BiggestWinDifference`, `BiggestDrawSum`, `BiggestLossDifference`, `BiggestSumOfGoals`, `DoubleDigits`, `CleanSheets`, and all ratio/conceded variants
- Opponent / victim / culprit **counts**: `DifferentOpponents`, `DifferentVictims`, `DoubleDigitsVictims`, `CleanSheetsVictims`, `MostGoalsConcededVictims`, `LeastGoalsScoredVictims`, `BiggestWinVictims`, `DifferentCulprits`, `DoubleDigitsCulprits`, `CleanSheetsCulprits`, `MostGoalsScoredCulprits`, `LeastGoalsConcededCulprits`, `BiggestWinCulprits`
- Rating career: `SumOfOpponentsRating`, `AverageOpponentRating`, `HighestRatedVictim`, `CurrentRatingAscent`, `BiggestRatingAscent`, `CurrentRatingDescent`, `BiggestRatingDescent`, `PeakRating`, `RecentAverageRating`
- Streaks (current + longest): `WinningStreak`, `DrawingStreak`, `LosingStreak`, `NonWinStreak`, `NonDrawStreak`, `NonLossStreak`, `LongestWinningStreak`, `LongestDrawingStreak`, `LongestLosingStreak`, `LongestNonWinStreak`, `LongestNonDrawStreak`, `LongestNonLossStreak`
- Pointers to record games: all `*GameID` and `*VictimID` / `*CulpritID` columns listed in `docs/playertable-schema.md`
- **Last game pointers (replay will set):** `LastGame`, `LastGameGameID`, `LastWinGameID`, `LastDrawGameID`, `LastLossGameID` — NULL at reset; v1 may only set `LastGame` + `LastGameGameID` during replay (see §6)

---

## 6. `apply_game` — v1 write set (P1 implementation)

Per game, after reading current `Rating` for `idA` and `idB`:

### 6.1 `ratedresults` (this `id`)

| Write | Source |
|-------|--------|
| `RatingA`, `RatingB` | Pre-game ratings from `playertable` |
| `ExpectedScoreA`, `ExpectedScoreB` | Elo formula |
| `AdjustmentA`, `AdjustmentB` | `K * (ActualScore - Expected)` (zero-sum) |
| `NewRatingA`, `NewRatingB` | Pre + adjustment |
| `RatingDifference` | `RatingA - RatingB` (sign as legacy) |
| `ActualScore`, `WinnerID` | From goals (§2) |
| `SumOfGoals`, `GoalDifference` | From goals |
| `HomeWin`, `Draw`, `AwayWin`, `DDPlayerA`, `DDPlayerB`, `CSPlayerA`, `CSPlayerB` | From goals (match legacy semantics) |

### 6.2 `playertable` (both players)

| Write | Rule |
|-------|------|
| `Rating` | Post-game rating for that side |
| `NumberGames` | Increment |
| `NumberWins` / `NumberDraws` / `NumberLosses` | Increment per outcome |
| `WinRatio`, `DrawRatio`, `LossRatio` | Recompute from W/D/L totals |
| `GoalsFor`, `GoalsAgainst` | Add goals scored/conceded for that side |
| `LastGame` | This game’s `Date` |
| `LastGameGameID` | This game’s `id` |

**v2 replay (`scripts/ladder/` May 2026)** also rebuilds: extremes, streaks, opponent/victim/culprit counts, rating career fields, all `*GameID` / `*VictimID` / `*CulpritID` pointers, `RecentAverageRating`, and `Display=1` when `NumberGames >= 1`. **`generalstatstable`** row `id=1` is ensured (CREATE + seed if missing), cleared on reset, and rebuilt from final `ratedresults` + `playertable`.

---

## 7. Safety & verification (before first real reset)

1. **Target DB:** confirm `SELECT DATABASE()` → **`ko2unity_db`**.
2. **Row counts (before):** `SELECT COUNT(*) FROM ratedresults` → expect **~74,870** locally.
3. **Backup:** keep `data/dumps/ko2unity_db-2026-05-20.sql`; re-import if reset goes wrong (`data/README.md`).
4. **Dry-run (P1):** first CLI flag `--dry-run` logs row counts and sample SQL, no `UPDATE`.
5. **After full replay:** spot-check one known player’s rating chart, `ranked1.php` order, and `SELECT Rating FROM playertable ORDER BY Rating DESC LIMIT 10`.

---

## 8. Out of scope for v1 (later phases)

- `generalstatstable` rebuild
- Full `playertable` extremes, streaks, victim/culprit graphs
- `resulttable` / Amiga offline track
- Parity with Steve’s live C++ numbers (sandbox may differ)
- Changing `Display` / established-player rules (stay in PHP)

---

## 9. P1 implementation

| Piece | Location |
|-------|----------|
| CLI | `python -m scripts.ladder` — `reset`, `replay`, `run` |
| Code | `scripts/ladder/` (`engine.py`, `outcome.py`, `elo.py`) |
| Config | `site/config/ladder.ini` (from `ladder.ini.example`) |
| Usage | `scripts/ladder/README.md` |

**Verified (2026-05-21, local):** `run` ~198s; top rating spot-check (e.g. game `id=10` → 1616 / 1584). **P2:** spot-check site charts / `ranked1.php`.

---

## Related docs

| Doc | Role |
|-----|------|
| `docs/ladder-engine-plan.md` | Architecture & phases |
| `docs/ratedresults-schema.md` | Per-game columns |
| `docs/playertable-schema.md` | Per-player columns |
| `docs/ratings_cpp.txt` | Legacy reference (not a port target) |
| `docs/LOCAL_DEV.md` | Laragon + local DB |
