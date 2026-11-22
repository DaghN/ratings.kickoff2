# Online games that were never processed (Nov 2026)

**Status:** investigated 2026-11-22. Our scripts are fixed and tested in the repo, **not uploaded**. The live ladder still needs one full recalculation. **Next:** Dagh sends Steve the message (§5).

**Audience:** Dagh, Steve, agents. **Code:** `ops/includes/ops_game_queue.php` · `ops/modules/process_pending_games.php` · test `scripts/test_ops_game_queue.php`.

---

## 1. What is wrong on live

A game counts as processed when `ratedresults.NewRatingA` is set. Read from `games/all.php?sort=rating_a&dir=asc` (unprocessed first) on 2026-11-22:

| Unprocessed games | Count | Ids |
|-------------------|-------|-----|
| Outage, one unbroken block | **150** | 77973 (Oct 30 17:03) to 78122 (Nov 3 18:39), 18 players |
| Single misses | **4** | 76901 (Sep 7 18:45), 77505 (Oct 8 16:40), 77734 and 77735 (Oct 19 10:07, 10:18) |
| Not processable by rule (no player, or self-play) | 5 | 5278, 5279, 5280, 20083, 20084 |

**154 games need processing.** Everything after the block is processed.

**Two numbers for the same player.** Some pages count ground rows (player games page, Status, Games hub). Others read stored totals (`playertable.NumberGames`: leaderboards, profile). One missed game makes them differ by one. Blazej: 4915 against 4888, and the 27 missing are exactly Blazej's unprocessed games (24 outage, 3 single).

**No second fault in counts or ratings.** For all 270 players on the rating list, stored games, wins, draws and losses equal their processed games, and stored Elo equals a replay of the processed games (largest difference 0.51, from rounding). Not audited on live: streaks, period tables, milestones, league awards, Hall of Fame.

**One old mark:** 15 games have slightly wrong pre-game ratings: 75839–75848 from the cutover evening (July 17), where 75843–75847 were processed before 75839–75842, and the ~1 point this left in later games. A full recalculation corrects these too.

**Cause.** The block fits the website being closed because of bots (not confirmed): the calls from the game app go through the website. The game machine itself ran without a restart through those days. The cause of the four singles is unknown; a short network break between the machines is the likeliest (§2). Neither needs a fuller answer, because the new scripts recover from any missed call.

---

## 2. Where things run

"Staging" and "production" mean databases and servers, not web addresses. What is known:

| Statement | How we know |
|-----------|-------------|
| There are two machines: the **web server** (`ratings.kickoff2.com`, `kickoff2.com`) and **joshua** (`joshua.kickoff2.net`, game app and old site) | Different network addresses |
| In June 2026 production was `ko2unity_db` on joshua (MariaDB 10.11.7, all tables MyISAM). Staging was `kooldb`, `kooldb1`, `kooldb2` on the web server (MariaDB 10.11.14) | Headers of the dumps from Steve |
| Today the game app, the old site and our website use **one** database | The old site shows the same newest game and the same ratings as ours, and those ratings come from our scripts |
| That database is **not on the web server** | Online pages take about 19 ms per database statement, in step with the number of statements. Amiga pages, whose database is on the web server, take almost none |
| It is very likely on **joshua** | The July template for `work-targets.ini` has a `[live-game]` target with its own host and port |
| The website's database settings file was last changed June 4 | File date. Not explained: it does not fit a switch on July 18 |

**Not known, Steve to confirm:** the machine and name of the live database, how the July switch was done, and whether the old copies on the web server are still used.

**Why it matters.** Our scripts run on the web server and the data is 19 ms away:

| Work | Database round trips (counted locally) | On that line (calculated) |
|------|---------------------|--------------|
| One game | about 280 | about **5 s** |
| Closing a day (midnight job), until now | about 15,000 | about **5 min** |
| Closing a day, after the fix in §3 | 37 | about 1 s |
| Full recalculation | 78,500 games | over **4 days** |

The June recalculation took 2 h 18 min because scripts and database were on the same machine. **A recalculation must run next to the database, or on a copy next to the scripts.**

---

## 3. What was wrong in our scripts, and what they do now

| # | Before | Now (repo) |
|---|--------|------------|
| 1 | Nothing looked for missed games. Later games were processed regardless | A call first processes every earlier waiting game, in order |
| 2 | Processing a missed game late was accepted and silently wrong: 9 tables differed from the correct result | Refused with exit **3**. Only a full recalculation can place it |
| 3 | Two calls at once could process the same game twice or cross (2 of 5 and 1 of 6 tries, 8 and 5 tables wrong) | One writer at a time (`GET_LOCK` per database). Recalculation holds the same lock |
| 4 | A fault mid-game left it half-written and marked as processed; retry was refused | See MyISAM below |
| 5 | An early death in the web entry point answered HTTP 200 with an empty body | HTTP 500 until a reply is built. Failures also go to the PHP error log |
| 6 | The midnight job closed a day without checking for waiting games | It processes the day's waiting games first, and stops (exit 1) if one fails |
| 7 | Closing a day asked the database about every past league period separately | One query |
| 8 | A full recalculation also closed the current, unfinished day | It stops at the present moment |
| 9 | Nothing outside the website could process games | `CMD=ProcessPending`, started by the server's own scheduler every minute |

**MyISAM.** Processing one game writes to about 15 tables. MyISAM saves each write at once and cannot undo. If the connection breaks halfway, some totals include the game and others don't, and the game is already marked as processed, so nothing repairs it. InnoDB makes the whole game all-or-nothing: a broken game leaves no trace, stays waiting and is processed a minute later. Until now InnoDB would not have helped either, because a `CREATE TABLE IF NOT EXISTS` in the middle of each game saved everything before it; that statement has moved. On MyISAM a broken game is now at least reported as **HALF-PROCESSED**. Converting is one command per table and needs no change in the game app.

**Limits:** a web call waits at most 15 s for its turn and spends at most 10 s on a backlog, then answers exit 1; the next call or the one-minute job continues. More than 1000 waiting games is treated as a recalculation in progress and refused.

### Proof

- `php scripts/test_ops_game_queue.php --engine=MyISAM|InnoDB` compares every table with a full recalculation of the same games: normal days, outage with and without the midnight job, every seventh call lost, no calls at all, time-limited calls, a database fault, and 40 calls sent twice, eight at a time, in random order. All pass on both engines.
- Full size (May 2026 ladder, 74,870 games, plus real live games 77900–78260 with the real outage): the first call after the outage caught up all 150 games, and every table ended identical to an in-order run.
- The command sequence in §4 with only the two folders `ops` and `includes`, while ten games arrived: the games waited, were processed afterwards, today stayed open, `run_verify_ops_sim.php` 0 failures, every table identical to a run that knew all games from the start.
- Full recalculation with the old and the new scripts: identical in every table.

**Not tested:** the live server, the game app's reaction to a slower reply, and a recalculation at today's size.

---

## 4. Recalculation

Upload the new scripts first. Then games can be played meanwhile: they wait and are processed in order afterwards.

| Way | Good | Less good |
|-----|------|-----------|
| **A. On the machine with the database** (PHP 8.1+ command line, folders `ops` and `includes`) | Nothing to copy or switch, play goes on | About 2½ hours of load there, if it is as fast as the web server was in June (`nice` helps); the ladder looks unfinished meanwhile |
| **B. On a copy next to the scripts, then switch**, probably as in July | The site looks normal until the switch | Short pause in play; game rows and accounts that arrived meanwhile must be carried over before step 5 |

| # | Step |
|---|------|
| 1 | Back up the live database. Use the target name of the midnight job if it is not `live-game` |
| 2 | Report only, writes nothing. Expect `waiting=0` and `holes=154`: `php ops/dispatch.php CMD=ProcessPending target=live-game dry_run=1` |
| 3 | `php ops/run_prepare.php seed-catalog --target live-game` (resets milestone holder counts, which the next command does not), then `php ops/run_prepare.php zero-derived --target live-game` |
| 4 | `php ops/run_ops_sim.php run --target live-game` |
| 5 | `php ops/dispatch.php CMD=ProcessPending target=live-game` (games played meanwhile) |
| 6 | `php ops/run_verify_ops_sim.php --target live-game`, then step 2 again: expect `holes=0` |
| 7 | Agent compares the live ladder with the expected ratings below |

**Upload note:** live `ops/includes/ops_work_target.php` differs by 2 bytes since July 18. Compare before uploading, and never upload `ops/config/`.

**Before Dec 1** if possible: the November league closes Dec 1 00:00 UTC. A later recalculation still corrects it, after the medals have been shown.

**Expected Elo** (22 players change by 1 or more; replay of games to 78521):
LORENZOL 2241→2188 · Micro 1600→1633 · Canvarol 1976→1953 · geo4444 1845→1863 · Blazej 2509→2526 · Giuliano 2210→2224 · Durban 1929→1940 · Steve 1971→1961 · Matthias 1574→1565 · PaoloA 1867→1876 · KONEY 1282→1274 · Dagh 2495→2502 · Eternalstudent 2043→2038 · Tentalas71 1960→1964 · JimBouki 1520→1517 · Logos 2198→2201. Top five: Blazej 2526, Dagh 2502, GianniT 2501, Giuliano 2224, Logos 2201.

---

## 5. Message to Steve

Split of work: **we** write and upload the scripts; **Steve** runs the server, the database, the scheduled jobs and the game app. A failed call needs no action from the app any more.

> Hi Steve,
>
> We found 154 online games that are stored but were never processed: 150 in a row from Oct 30 to Nov 3 (we guess while the site was closed because of the bots), and 4 single ones (Sep 7, Oct 8, two on Oct 19). They count as played, but they are missing from ratings, leagues, streaks and milestones.
>
> **What we want to reach**
> 1. Recalculate the ladder once, so these games count. Best before Dec 1, when the November league closes.
> 2. No more lost games. We changed our scripts so that any waiting games are processed first, in order, before the next one.
> 3. Processing that works even when the website is closed, through a small job every minute.
>
> **How we think it is set up. Please correct us:**
> - Joshua runs the game app and the live database.
> - The web server runs the website and our scripts, and both use the database on joshua over the network.
> - After each game, the app calls dispatch_request.php on the web server. At midnight, cron on the web server runs FinalizeUtcDay.
> - The old copies on the web server (kooldb, kooldb1, kooldb2) are no longer used.
>
> We ask because the online pages take about 19 ms per database query, while the Amiga pages take almost none. One game needs about 280 round trips, so about 5 seconds (duration_ms in the reply would show it). A full recalculation over that line would take more than 4 days, so it has to run next to the database, or on a copy next to the scripts.
>
> **Questions**
> 1. Is that picture right? If not, which machine and database do the app, the website and our scripts use?
> 2. How did you do the full recalculation and the switch in July, and can we do it the same way again? Running it directly on the machine with the database also works, if that is easier for you: it needs PHP 8.1+ on the command line and two folders from us, people can keep playing (their games are processed afterwards), and the ladder looks unfinished meanwhile. It should take about 2.5 hours, as in June on the web server.
> 3. Are the live tables MyISAM? We would like InnoDB. With MyISAM, if the connection breaks halfway through a game, some tables have the game and others don't, and it can't be undone. With InnoDB the whole game is undone and simply tried again. The app needs no change.
> 4. Does the app wait for the script's reply before play goes on? After an outage a reply can take up to about 30 seconds. If it waits, we will make the script answer at once and do the work afterwards.
>
> **What we would like you to do**
> 1. Back up the live database.
> 2. Convert the tables to InnoDB, if you agree.
> 3. Run the recalculation. We send the exact commands.
> 4. Once the new scripts are up, add this next to the midnight job, with the same target: `* * * * * cd /path/to/public_html && php ops/dispatch.php CMD=ProcessPending target=live-game`
>
> We upload the new scripts as usual, when you are ready. With the job in place you can close the website whenever you need to: games are then processed within a minute anyway.
