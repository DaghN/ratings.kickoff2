# Online games that were never processed (Nov 2026)

**Status:** investigated 2026-11-22. The fix is built and tested in the repo. **Not deployed.** The live ladder still needs a full recalculation.

**Audience:** Dagh, Steve, agents. **Code:** `ops/includes/ops_game_queue.php` · `ops/modules/process_pending_games.php` · test `scripts/test_ops_game_queue.php`.

---

## 1. What is wrong on live

The game server inserts a ground row in `ratedresults`, then calls `dispatch_request.php?CMD=ProcessCompletedGame`. A game counts as processed when `NewRatingA` is set. Read from `games/all.php?sort=rating_a&dir=asc` (unprocessed first) on 2026-11-22:

| Unprocessed games | Count | Ids |
|-------------------|-------|-----|
| Outage, one unbroken block | **150** | 77973 (Oct 30 17:03) to 78122 (Nov 3 18:39), 18 players |
| Single misses | **4** | 76901 (Sep 7 18:45), 77505 (Oct 8 16:40), 77734 and 77735 (Oct 19 10:07, 10:18) |
| Not processable by rule (no player, or self-play) | 5 | 5278, 5279, 5280, 20083, 20084 |

**154 games need processing.** The last game processed before the outage is 77972 (Oct 29 22:35) and the first after it is 78123 (Nov 3 19:44). Everything since then is processed.

**Two numbers for the same player.** Some pages count ground rows (player games page, Status, Games hub). Others read stored totals (`playertable.NumberGames`: leaderboards, profile). One missed game makes them differ by one. Blazej: 4915 against 4888, and the 27 missing are exactly Blazej's unprocessed games (24 outage, 3 single).

**No second fault in the counts or ratings.** For all 270 players on the rating list, stored games, wins, draws and losses equal their processed games, and stored Elo equals a replay of the processed games (largest difference 0.51, from rounding). Not audited on live: streaks, period tables, milestones, league awards, Hall of Fame.

**One old mark:** 15 games have slightly wrong pre-game ratings: 10 from the cutover evening (75839–75848, July 17), where 75843–75847 were processed before 75839–75842, and the ~1 point this left in later games. A full recalculation corrects these too.

### Cause

| Claim | Evidence |
|-------|----------|
| Every call from Oct 30 to Nov 3 failed to take effect | Established: 150 games in a row, no exceptions |
| Web access was closed because of bots | Fits, not proven: the web root last had a file added or removed on Oct 30, and `robots.txt` (blocks AI crawlers) was last edited Nov 12. **Steve to confirm** |
| The 4 single misses | Unknown. The games just before and after were processed. Candidates are a failed or timed-out web call, or an early death that looked like success (§3, item 5). **Steve: does the game server log or retry failed calls?** |

Leagues and day milestones for Oct 30 – Nov 3, the weeks of Oct 26 and Nov 2, and October were finalized without these games if the midnight run worked (**Steve to confirm**). **November's month league closes on Dec 1 00:00 UTC**, so recalculate before then.

---

## 2. Staging and production

`ratings.kickoff2.com` serves the live games (newest seen: 78521, Nov 21 23:08). What older docs call **staging is the live site** for both realms. "No live game writes on staging" in older docs describes June 2026, before the July 18 cutover. Which database name and `target=` live uses is in Steve's `work-targets.ini`, not in this repo.

---

## 3. Weaknesses found, and what the new code does

Measured on a throwaway local copy with the real processing code.

| # | Before | Now (repo) |
|---|--------|------------|
| 1 | Nothing looked for missed games. Later games were processed regardless | A call first processes every earlier waiting game, in order |
| 2 | Processing a missed game late was accepted and silently wrong: 9 tables differed from the correct result (ratings, milestones, play and result streaks, peaks, server stats) | Refused with exit **3**. Only a full recalculation can place it |
| 3 | Two calls at once could both process the same game (2 of 5 tries on InnoDB, 8 tables wrong) or cross (1 of 6 on MyISAM, 5 tables wrong) | One writer at a time (`GET_LOCK` per database). Recalculation holds the same lock |
| 4 | A fault mid-game left it half-written and marked as processed; retry was refused. MyISAM cannot roll back, and `CREATE TABLE IF NOT EXISTS` inside the transaction committed it even on InnoDB | The statement moved out of the transaction: on InnoDB the game is undone and retried. On MyISAM the failure says **HALF-PROCESSED** |
| 5 | An early death in the web entry point answered HTTP 200 with an empty body | HTTP 500 until a reply is built. Failures also go to the PHP error log |
| 6 | The midnight run finalized a day without checking for waiting games | It processes the day's waiting games first, and stops (exit 1) if one fails |

**Catch-up across days** closes each day before the next day's first game, like `run_ops_sim.php`. If the midnight run was missed, the next game call closes the day.

**Limits:** a web call waits at most 5 s for its turn and spends at most 10 s on a backlog, then answers exit 1; the next call continues. More than 1000 waiting games is treated as a recalculation in progress and refused.

**Still open:** live table engine. Local June prod copy is all MyISAM. Item 4 is only fully closed on InnoDB.

### Proof

`php scripts/test_ops_game_queue.php --engine=MyISAM|InnoDB` compares every table with a full recalculation of the same games: normal days, outage with and without the midnight run, every seventh call lost, no calls at all, time-limited calls, a database fault, and 40 calls sent twice, eight at a time, in random order. All pass on both engines.

**Rehearsal at full size** (May 2026 ladder, 74,870 games, plus 361 real live games 77900–78260 with the real outage): the first call after the outage caught up all 150 games and every table ended identical to an in-order run. On Dagh's laptop: 45 s for the 150 games, median 0.2 s for a normal call, 3–4 s for the first call of a day (it closes the day before). Over the web that backlog takes about five calls because of the 10 s limit; the one-minute cron clears it in one go.

**Not tested:** the live server itself, the game server's reaction to exit 3 / HTTP 409 / a slower reply, and a full recalculation at today's size.

---

## 4. Repair plan for live

Order matters: deploy first, because then the recalculation can run while games are being played. Games that end meanwhile wait and are processed in order afterwards.

| # | Who | Step |
|---|-----|------|
| 1 | Steve | Answer the questions in §5 |
| 2 | Dagh | OK the upload of the changed `ops/` files. **Not** `ops/config/` and **not** `ops/includes/ops_work_target.php` (live differs by 2 bytes since July 18) |
| 3 | Steve | Report only, nothing is written. Expect `waiting=0` and `holes=154`: `php ops/dispatch.php CMD=ProcessPending target=YOUR_TARGET dry_run=1` |
| 4 | Steve | **Back up the live database** |
| 5 | Steve | `php ops/run_prepare.php seed-catalog --target YOUR_TARGET` (needed: it resets milestone holder counts, `zero-derived` does not) |
| 6 | Steve | `php ops/run_prepare.php zero-derived --target YOUR_TARGET` |
| 7 | Steve | `php ops/run_ops_sim.php run --target YOUR_TARGET` (about 2½ hours; the June run took 2 h 18 min for 74,865 games) |
| 8 | Steve | `php ops/dispatch.php CMD=ProcessPending target=YOUR_TARGET` (games played meanwhile) |
| 9 | Steve | `php ops/run_verify_ops_sim.php --target YOUR_TARGET`, then step 3 again: expect `holes=0` |
| 10 | Agent | Compare the live ladder with the expected ratings below |

During steps 6–8 the site shows a ladder under construction. To avoid that, run steps 4–9 on a copy and swap, with the game rows that arrived meanwhile inserted before step 8.

**Safety net:** a cron line every minute on the web host, so processing no longer depends on the website being reachable: `php ops/dispatch.php CMD=ProcessPending target=YOUR_TARGET`. If bots have to be blocked again, leave `dispatch_request.php` reachable for the game server.

**Expected Elo after recalculation** (22 players change by 1 or more; replay of games to 78521):
LORENZOL 2241→2188 · Micro 1600→1633 · Canvarol 1976→1953 · geo4444 1845→1863 · Blazej 2509→2526 · Giuliano 2210→2224 · Durban 1929→1940 · Steve 1971→1961 · Matthias 1574→1565 · PaoloA 1867→1876 · KONEY 1282→1274 · Dagh 2495→2502 · Eternalstudent 2043→2038 · Tentalas71 1960→1964 · JimBouki 1520→1517 · Logos 2198→2201. Top five: Blazej 2526, Dagh 2502, GianniT 2501, Giuliano 2224, Logos 2201.

---

## 5. Questions for Steve

1. What was switched off between Oct 30 and Nov 3, and was `dispatch_request.php` unreachable for the game server?
2. Did the midnight cron run on those days, and is its command `dispatch.php CMD=FinalizeUtcDay`? (`run_finalize_utc_day.php` is a dev runner without the new checks.)
3. Does the game server retry or log failed calls (anything for games 76901, 77505, 77734, 77735)? How long does it wait for a reply, and does play wait with it?
4. Which `target=` / database does live use, and are its tables MyISAM or InnoDB?
5. Recalculate on live directly (site looks wrong for about 2½ hours) or on a copy?
6. Can a one-minute cron be added on the web host?
