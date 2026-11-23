# Amiga recount — an independent check of the stored numbers

**Command:** `python -m scripts.amiga recount` (about 50 seconds, reads only)
**Code:** `scripts/amiga/modern/recount.py` · tests `scripts/amiga/test_recount.py`
**Since:** 2026-11-23

---

## What it does

It counts the numbers again and compares them with what is stored: about 12 million values in 18 tables, for today and for every earlier tournament. It starts from the game results, the players, the tournaments and the finish places, and from nothing else.

`RESULT: SAME`, `DIFFERENT` (with the table, the column and an example), or `COLUMN WITHOUT A RECOUNT`. Report: `data/amiga/modern/recount-last.json` (not in git).

| Where | When |
|---|---|
| In every rebuild | Last step of `simul` |
| On a copy of live | At the end of every pull (`-SkipRecount` leaves it out); it never stops the pull. By hand: `python -m scripts.amiga recount --database ko2amiga_staging_cmp` |

After a website tournament, a pull is therefore also the check of what Finish stored.

## Why

Most rebuild checks ask whether the stored numbers fit together, or calculate them again with the same code. They find damaged or half-updated data, but not a wrong calculation. Measured 2026-11-23 with mistakes planted in the Python calculation (22 of them changed something):

| Check | Noticed |
|---|---|
| The 28 rebuild checks | 5 of 22 (3 only because PHP and Python disagreed) |
| The recount | 20 of 22 |

`php-finish-check` ([`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md) §0) notices a mistake made in one of the two programs. A mistake made in both gets through: the World Cup XII bronze did, from June to November 2026. Both sides are usually written together from the same idea.

## Rules

1. **No calculation code is imported** in `recount.py`. A test checks it.
2. **A new or changed stored number gets its recount in the same change**, written from the written rule (the policy page), not copied from the calculation code. A stored column that is neither recounted nor listed with a reason in `NOT_RECOUNTED` makes the run fail. So does a new derived table.
3. **When the recount and the calculation disagree, do not adjust the recount to fit.** Find out which of them follows the written rule. If the rule is unclear, ask Dagh.

## Not recounted

| What | Why |
|---|---|
| Finish places, best knockout phase | Taken as given; they come from the tournament structure |
| League tables (`amiga_tournament_standings`), and the counts of them in the catalogue | PHP and Python are compared in `verify-php-standings-parity` |
| World Cup Hall of Fame (`amiga_wc_hof_*`, best attack/defence, best single World Cup) | `verify-wc-hof` recounts them itself |
| Group and knockout game counts of a World Cup | Depend on the stage structure |
| `LeastGoalsScoredVictims`, `LeastGoalsConcededCulprits` | No page shows them; not kept up for players who did not take part |

Ratings may differ by 0.002 (they are stored with six decimals at every event), performance ratings by 0.05, the sum of opponents' ratings by 0.2. Other decimals by one step in the last place.

## Found by the recount

- **Tied single-game records (2026-11-23).** Two games can reach the same record (most goals in one game, biggest win, biggest draw, most goals in a game). Within a tournament the first game held it, between tournaments the lower player number, and the written rule said the lower player number. Dagh decided: **the first game keeps the record.** Changed in Python and PHP, also for the World Cup Hall of Fame. Only time travel showed a difference; the present records were not tied.

## Proof

- The rebuilt database and the copy of live (both 608 tournaments, 2026-11-23): `SAME`, 12,208,421 values each.
- Noticed, one example each: Elo factor 33 · performance rating scale 420 · double digits from 9 goals · 2 points for a win · fourth place as a podium · a winning streak that survives a draw · biggest win from the last game instead of the first · best rank from the last time instead of the first · clean sheets with one goal allowed · World Cup victims that include draws · goals given to the other player's country · a record holder on a tie with the higher number · ratio records from 10 games.
- Not noticed, as expected: the World Cup win rate record (`verify-wc-hof` notices it) and league ties decided by goals before goal difference (`verify-php-standings-parity` notices it while PHP is right).
