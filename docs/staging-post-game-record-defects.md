# Staging — known Hall of Fame record date defects (C++ post-game)

**Purpose:** Present knowledge for **prod post-game deploy** and regression testing. These are **wrong `generalstatstable` record dates** observed on staging (and prod-shaped behaviour), **most likely written by Steve’s per-game C++** (`RatingProcedureUnity`), not by the PHP site.

**Authority after fix:** [`docs/website-data-contract.md`](website-data-contract.md) · exact C++ lines: [`docs/coordination/post-game-record-bugs-exact.md`](coordination/post-game-record-bugs-exact.md) · Steve handoff: [`docs/coordination/records-post-game-exception.md`](coordination/records-post-game-exception.md)

**Library parity (correct targets):** `scripts/k2_rating_core/server_records.py`. Automated checks: archived `docs/archive/ladder-retired-2026-06/golden_record_checks.py` (historical).

---

## Symptom class

**“Date = holder’s last game”** (or last game at tied record value) instead of **“date = first time that record value was reached.”**

Root causes in C++ excerpt (see exact line doc):

1. **`>=` instead of `>`** on server-record comparisons — refreshes date every game while still tied at the record.
2. **Streak blocks** compare **current** streak to server record, not **career longest** (`LongestWinningStreakA`).
3. **Suspected on live prod (verify with Steve):** victim/opponent rows run **`>=` without boolean gate** — every game at max count refreshes date (excerpt *with* boolean does not reproduce Mar 13 / last-game drift for Fiery/Eternalstudent).

---

## Critical regression matrix (test revised post-game against these)

Use **Hall of Fame** (`hall-of-fame.php`) or SQL on `generalstatstable WHERE id = 1`.

| Record | Holder | Value | **Wrong (staging / bug)** | **Correct (contract / golden)** | PG-004 area |
|--------|--------|-------|---------------------------|----------------------------------|-------------|
| Longest winning streak | GianniT | 70 | Date **2023-12-26** (last Gianni game) | **2020-11-23** UTC | PG-004c streaks: `LongestWinningStreakA > …`, not `WinningStreakA >=` |
| Longest non-loss streak | GianniT | 120 | Date **2023-12-26** | **2022-02-16** UTC | Same |
| Most clean sheet victims | FieryPhoenix | 76 | Date **2026-03-13** | **2026-01-30** UTC | PG-004b: `>` + keep `MostCleanSheetsVictimsBoolean`; prod may omit boolean |
| Most different opponents | Eternalstudent | 103 | Date **holder’s last game** (~2026-05-18) | **2026-05-04** UTC (first reach 103) | PG-004b network rows: `>` + boolean |
| Most different victims | Eternalstudent | 101 | Date **holder’s last game** | **2026-05-04** UTC | Same |
| Longest drawing streak | j1mpst3r | 5 | Later tie steals holder/date | First holder **2020-06-13** UTC | `>` tie policy |
| Biggest peak rating | Dagh | (peak) | Date drifts to **2026-05-18** | **2026-05-14** UTC first reach | `>` on `NewRatingA > BiggestPeakRatingS` |
| Most games played | geo4444 | 11087+ | Date drifts to **last game** | First day record was **set** | `>` at lines ~1574–1587 in excerpt |

**Values** (70, 120, 76, 103, 101) are stable; **dates** are what break.

---

## How to verify

### Before deploy (staging still C++-shaped GST)

```bash
# On server, from public_html/ (config → kooldb)
mysql … -e "SELECT LongestWinningStreak, LongestWinningStreakDate, LongestNonLossStreakDate, MostCleanSheetsVictimsDate, MostDifferentOpponentsDate, MostDifferentVictimsDate FROM generalstatstable WHERE id=1\G"
```

Expect **wrong** column values in the table above if GST has not been replayed since PG-004 Python landed.

### After revised C++ + backfill

1. **History:** ops simul on target DB (`run_ops_sim.php` on work/staging) **or** one-shot GST backfill Steve agrees.
2. **Future games:** fixed post-game only maintains GST incrementally.
3. **Automated:** archived golden record checks in `docs/archive/ladder-retired-2026-06/golden_record_checks.py` (historical).

### UI smoke

Open **Hall of Fame** → streak / victims / opponents rows → dates must match **Correct** column, not holder’s last activity day.

---

## What fixing prod post-game does *not* do alone

- **Does not rewrite** past wrong dates already stored in `generalstatstable` — needs **replay** or agreed SQL backfill after C++ deploy.
- **Does not** fix website aggregate tables (`player_period_games`, etc.) — separate contract in [`docs/website-data-contract.md`](website-data-contract.md).

---

## Related commands (not the same as post-game fix)

| Command | What it fixes |
|---------|----------------|
| `php ops/run_ops_sim.php` | Full derived fill including **GST row id=1** (PHP PG-004 semantics) |
| Retired dev batch CLIs | Aggregate tables only — **not** Hall of Fame GST — see retirement policy |

See [`docs/OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) — **Two rebuild paths**.
