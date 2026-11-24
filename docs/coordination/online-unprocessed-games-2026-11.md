# Online games that were never processed (Nov 2026)

**Status (2026-11-24): ready to go, waiting for Steve to pick a day.** Our scripts are fixed and tested in the repo, **not uploaded**. The step path is §0, the message for Steve §5. Until step 1b, do not upload `ops/` or `includes/league_standings.php`.

**Audience:** Dagh, Steve, agents. **Code:** `ops/includes/ops_game_queue.php` · `ops/modules/process_pending_games.php` · test `scripts/test_ops_game_queue.php`.

---

## 0. The step path (ready to go)

**Assumptions (Dagh, 2026-11-24):** the online database is on another machine than the web server (very likely joshua, §2), and Steve can recalculate next to it, as in July. If either is wrong, Steve says so and only step 2 changes.

| Step | Who | What | Ready |
|---|---|---|---|
| 1a | Steve | Back up the live online database. Exempt the game machine's address from the web server's automatic block (§4, "Before the upload") | — |
| 1b | Dagh + agent | Upload the 17 files below: preview, upload, read back, then load one online page at once | List checked against live 2026-11-24 |
| 2 | Steve | Recalculate next to the database with the commands in §4 step 2–6 (about 2½ hours). He needs the folders `ops` and `includes` from the web server after 1b, and a `work-targets.ini` target for that database | Tested locally at full size |
| 3a | Steve | Add the one-minute job (§5) next to the midnight job; tell Dagh | Line ready |
| 3b | Agent | Compare live with the expected ratings (§4), then mark this doc, `PROJECT_MEMORY.md` and `ladder-ops-platform.md` done | Expected list ready |

1a, 1b and 2 belong on the same day: from 1b on, new games are stored by the new rules (§3, faults 10–13), and the recalculation puts all of history on the same rules. Best before **Dec 1 00:00 UTC**, when the November league closes.

**Upload list** (compared with live 2026-11-24; everything else in `ops/` is identical):

| Folder | Files |
|---|---|
| `ops/includes/` | `ops_bootstrap.php`, `ops_dispatch.php`, `ops_dispatch_http.php`, `ops_game_queue.php` (new), `ops_reset_universe.php`, `ops_sim.php`, `ops_work_target.php` (only two blank lines differ), `post_game_generalstats.php`, `post_game_player_db.php`, `post_game_server_records.php` |
| `ops/modules/` | `process_completed_game.php`, `process_pending_games.php` (new) |
| `ops/` | `run_ops_sim.php` |
| `ops/docs/` | `ops-dispatch.md`, `post-dagh-live-story.md`, `steve-live-ops.md` |
| `includes/` | `league_standings.php` |

**Never upload** `ops/config/` or `ops/.htaccess`. Steve rewrote the live `ops/.htaccess` on 2026-11-24; the repo copy has his rules, but line endings differ.

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

**Second possible cause (2026-11-23, not confirmed).** Steve: the web server blocks an address whose requests end in errors. A failed call from the game machine gets 400, 401 or 500, so a few failures in a row may have blocked the game machine itself. Steve's block list for Oct 30 to Nov 3 can tell.

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
| The Amiga database is on the web server, "not joshua" | Steve, 2026-11-23. With the 19 ms line this makes joshua near certain for the online database |
| The databases use MyISAM | Steve, 2026-11-23 |
| One machine runs PHP 8.6.0 | Steve, 2026-11-23; which one was not said. Our `ops` scripts use none of the functions that PHP 8.4 and 8.5 gave up; 8.6 itself is not tested (local: 8.3.30) |
| The website's database settings file was last changed June 4 | File date. Not explained: it does not fit a switch on July 18 |
| The online pages' settings file on the web server names a database `kooldb1`, reached as `localhost` | Read over SFTP 2026-11-23 (names only). It does not show where that database physically is: with the 19 ms per statement it may well be passed on to joshua, or it may be a copy on the web server. Not concluded; Steve to confirm |

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

### Four more faults fixed for the same recalculation (2026-11-22)

They change stored results, so they belong in the same upload and the same recalculation.

| # | Before | Now (repo) |
|---|--------|------------|
| 10 | Activity leagues: the tie-break took the first game as player B instead of the first game on either side (`includes/league_standings.php`) | One ranking over both sides. Of 7,422 past periods, 172 had a wrong podium (161 days, 10 weeks, 1 month; 33 golds). Points leagues were right |
| 11 | Deleted accounts (287 and 354, 17 games) were new players at 1600 in every game (`ops/includes/post_game_player_db.php`) | Rating and career are rebuilt from their earlier games, as in the Python replay. One player, one debut, one record per player. Effect on Elo (May 2026 ladder): 4 players by 0.5 or more (rowstu -5.8, Freshy +3.8, BountyBob +1.0, MandhirS +0.6) |
| 12 | `league_wins_*` milestones were stored without their league | Stored from the award of the Nth win, ordered `period_end, league_kind, period_type, period_start` (the order of the May fill: 84 of 84 rows equal) |
| 13 | Hall of Fame peak rating: a stored peak rounded up to 2 decimals "beat" the record in the holder's next game and moved its date (`ops/includes/post_game_server_records.php`) | Only the game that set the peak can set the record |

The medals of some past activity leagues, and the league milestones that follow from them, move to other players in the recalculation.

### Proof

- `php scripts/test_ops_stored_results.php` (faults 10-13 on an invented ladder): all pass; 9 of the 10 checks fail on the old code.
- Faults 11-13 against an independent replay of 693 real games with both deleted accounts: no difference left; the old code had 90 wrong Elo rows and 20 wrong counters. On 501 games without deleted accounts, only the medals and milestones that depend on the tie-break or the source differ from the old code.
- `php scripts/test_ops_game_queue.php --engine=MyISAM|InnoDB` compares every table with a full recalculation of the same games: normal days, outage with and without the midnight job, every seventh call lost, no calls at all, time-limited calls, a database fault, and 40 calls sent twice, eight at a time, in random order. All pass on both engines.
- Full size (May 2026 ladder, 74,870 games, plus real live games 77900–78260 with the real outage): the first call after the outage caught up all 150 games, and every table ended identical to an in-order run.
- The command sequence in §4 with only the two folders `ops` and `includes`, while ten games arrived: the games waited, were processed afterwards, today stayed open, `run_verify_ops_sim.php` 0 failures, every table identical to a run that knew all games from the start.
- Full recalculation with the old and the new scripts, faults 1-9 only: identical in every table.

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

**Before the upload:** the game machine's address must be exempt from the web server's block (see §1). The new scripts answer 409 or 500 on a failure, and a block would stop every later call. The one-minute job (`CMD=ProcessPending`) does not go through the web server, so it would still process the games.

**Before Dec 1** if possible: the November league closes Dec 1 00:00 UTC. A later recalculation still corrects it, after the medals have been shown.

**Expected Elo** (22 players change by 1 or more; replay of games to 78521):
LORENZOL 2241→2188 · Micro 1600→1633 · Canvarol 1976→1953 · geo4444 1845→1863 · Blazej 2509→2526 · Giuliano 2210→2224 · Durban 1929→1940 · Steve 1971→1961 · Matthias 1574→1565 · PaoloA 1867→1876 · KONEY 1282→1274 · Dagh 2495→2502 · Eternalstudent 2043→2038 · Tentalas71 1960→1964 · JimBouki 1520→1517 · Logos 2198→2201. Top five: Blazej 2526, Dagh 2502, GianniT 2501, Giuliano 2224, Logos 2201.

---

## 5. Message to Steve

**History:** first message sent by Dagh 2026-11-22 (WhatsApp; text in git history). Answers 2026-11-23: the Amiga database is on the web server, not on joshua; the databases are MyISAM; one machine runs PHP 8.6.0. The rest was not answered and is now assumed (§0).

Split of work: **we** write and upload the scripts; **Steve** runs the servers, the database, the scheduled jobs and the game app.

**Message 1, the recalculation (drafted 2026-11-24, for Dagh to send):**

> Hi Steve,
>
> The online recalculation is ready on our side. This is the plan, so you know what we're waiting for:
>
> 1. **Prepare (you):** back up the live online database, and exempt the game machine from the web server's automatic block. The new scripts answer with an error code when something fails, and a blocked game machine could not report its games any more.
> 2. **Upload (me):** I upload 17 changed script files with WinSCP, as usual. From then on games are processed in order, and a missed game is caught up automatically.
> 3. **Recalculate (you):** run the recalculation once, next to the database, as in July. It takes about 2½ hours. People can keep playing; their games are processed afterwards. Commands below.
> 4. **Afterwards (you):** add a one-minute job next to the midnight job and tell me. I then check the ladder against the ratings we expect.
>
> We assume the online database is on joshua and that you run it there, or on a copy as in July. If it's different, just tell me what's different.
>
> Steps 1 to 3 should be on the same day, best before Dec 1 when the November league closes. Which day suits you?
>
> Commands, from public_html, with the target name of the midnight job (I wrote live-game):
> `php ops/dispatch.php CMD=ProcessPending target=live-game dry_run=1` (report only; should say holes=154 or a few more)
> `php ops/run_prepare.php seed-catalog --target live-game`
> `php ops/run_prepare.php zero-derived --target live-game`
> `php ops/run_ops_sim.php run --target live-game`
> `php ops/dispatch.php CMD=ProcessPending target=live-game` (games played meanwhile)
> `php ops/run_verify_ops_sim.php --target live-game` (should report 0 failures)
>
> One-minute job: `* * * * * cd /path/to/public_html && php ops/dispatch.php CMD=ProcessPending target=live-game`
>
> Optional, whenever you have time: converting the tables to InnoDB would make a broken connection in the middle of a game harmless; the game is simply tried again a minute later. The game app needs no change.

**Message 2** (server housekeeping) is in Dagh's private notes, not in this repo.
