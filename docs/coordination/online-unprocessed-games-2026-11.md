# Online games that were never processed (Nov 2026)

**Status (2026-11-28): steps 1a and 1b done; Steve's recalculation (step 2) is running, but far from the database.** At 06:34-06:37 UTC the live leaderboard grew by about 39 games in 172 s: about 14 games a minute, 4.4 s per game, which is the speed of §2 for a run on the web server (about 4 days for 78,500 games). Next to the database it should be about 10 games a second. Message to Steve in §5 ("Recalculation too slow"). Steve backed up the live online database, then we uploaded the 17 files (read back identical; the Status page and a league page fine on live). His first try of the commands, before the upload, failed because they need the new scripts; live was unchanged by it (ratings and milestone holders checked). The step path is §0, the messages for Steve §5. Never upload `ops/config/` or `ops/.htaccess`.

**Audience:** Dagh, Steve, agents. **Code:** `ops/includes/ops_game_queue.php` · `ops/modules/process_pending_games.php` · test `scripts/test_ops_game_queue.php`.

---

## 0. The step path (ready to go)

**Assumptions (Dagh, 2026-11-24):** the online database is on another machine than the web server (very likely joshua, §2), and Steve can recalculate next to it, as in July. If either is wrong, Steve says so and only step 2 changes.

| Step | Who | What | Ready |
|---|---|---|---|
| 1a | Steve | Back up the live online database. No exemption from the web server's block is needed (§4, "Before the upload") | **Done 2026-11-28** |
| 1b | Dagh + agent | Upload the 17 files below: preview, upload, read back, then load one online page at once | **Done 2026-11-28**: live unchanged since 2026-11-24 beforehand; read back identical; Status and a league page without errors. The live versions from before are kept locally (git-ignored `_notes/live-before-2026-11-28/`); the two new files had none |
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

**Cause.** The block fits the website being closed because of bots (not confirmed): the calls from the game app go through the website. The game server itself ran without a restart through those days. The cause of the four singles is unknown; a short network break between the machines is the likeliest (§2). Neither needs a fuller answer, because the new scripts recover from any missed call.

**Second possible cause (2026-11-23, not confirmed).** Steve: the web server blocks an address whose requests end in errors. A failed call from the game server gets 400, 401 or 500, so a few failures in a row may have blocked the game server itself. Steve's block list for Oct 30 to Nov 3 can tell. From the upload on, normal outcomes answer 200 (§4, "Before the upload").

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
| 5 | An early death in the web entry point answered HTTP 200 with an empty body | HTTP 500 if PHP dies before a reply is built. Every handled outcome answers 200 with the JSON saying what happened (§4, "Before the upload"). Failures also go to the PHP error log |
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

**Before the upload:** no exemption from the web server's block is needed (changed 2026-11-28, after Steve asked). The web server blocks an address after a few error replies, so `dispatch_request.php` answers **200** for every handled outcome (exit 0 to 3); the JSON `exit` says which, and exit 1 and 3 go to the PHP error log. Error codes remain only for real faults: wrong key 401, missing config 503, bad call 400, PHP crash 500. The scripts on live before the upload answer 500 for exit 1, which may be how the game server was blocked from Oct 30 to Nov 3 (§1). The one-minute job (`CMD=ProcessPending`) does not go through the web server at all.

**Before Dec 1** if possible: the November league closes Dec 1 00:00 UTC. A later recalculation still corrects it, after the medals have been shown.

**Expected Elo** (22 players change by 1 or more; replay of games to 78521):
LORENZOL 2241→2188 · Micro 1600→1633 · Canvarol 1976→1953 · geo4444 1845→1863 · Blazej 2509→2526 · Giuliano 2210→2224 · Durban 1929→1940 · Steve 1971→1961 · Matthias 1574→1565 · PaoloA 1867→1876 · KONEY 1282→1274 · Dagh 2495→2502 · Eternalstudent 2043→2038 · Tentalas71 1960→1964 · JimBouki 1520→1517 · Logos 2198→2201. Top five: Blazej 2526, Dagh 2502, GianniT 2501, Giuliano 2224, Logos 2201.

---

## 5. Message to Steve

**History:** first message sent by Dagh 2026-11-22 (WhatsApp; text in git history). Answers 2026-11-23: the Amiga database is on the web server, not on joshua; the databases are MyISAM; one machine runs PHP 8.6.0. The rest was not answered and is now assumed (§0).

Split of work: **we** write and upload the scripts; **Steve** runs the servers, the database, the scheduled jobs and the game app.

**Message 1, the recalculation (drafted 2026-11-24, sent by Dagh before 2026-11-28).** Formatted for WhatsApp (`*bold*`, three backticks for the command boxes). The sent version also asked Steve to exempt the game server from the IP block; the reply below withdrew that, and the line is gone from this copy.

````text
Hi Steve,

The online recalculation is ready on our side. Here is the plan, so you know what we're waiting for. In steps 3 and 4 we ask you to run some exact commands from us; they are at the bottom of this message.

*1. Prepare (you)*
- Back up the live online database.

*2. Upload (me)*
I upload 17 changed script files with WinSCP, as usual. From then on games are processed in order, and a missed game is caught up automatically.

*3. Recalculate (you): run our 6 commands*
Run the 6 commands under *Commands for step 3* below, one after the other, next to the database, as in July. It takes about 2½ hours. People can keep playing; their games are processed afterwards.

*4. One-minute job (you): add our 1 line*
Add the line under *Line for step 4* below to the scheduled jobs (cron), next to the midnight job. Then tell me, and I'll check the ladder against the ratings we expect.

We assume the online database is on joshua and that you run step 3 there, or on a copy as in July. If anything is different, just tell me.

Steps 1 to 3 should be on the same day, best before Dec 1, when the November league closes. Which day suits you?

*Commands for step 3*
Run them in the folder that holds our ops and includes folders. If that's on joshua, copy both from public_html after my upload in step 2, and point the target in ops/config/work-targets.ini at the database there. I wrote the target as live-game; use the midnight job's target name if it's different.

```
php ops/dispatch.php CMD=ProcessPending target=live-game dry_run=1
php ops/run_prepare.php seed-catalog --target live-game
php ops/run_prepare.php zero-derived --target live-game
php ops/run_ops_sim.php run --target live-game
php ops/dispatch.php CMD=ProcessPending target=live-game
php ops/run_verify_ops_sim.php --target live-game
```

What each one does:
1. Report only, changes nothing. It should say holes=154 or a few more.
2. and 3. Reset the calculated tables (ratings, leagues, milestones). The games and the accounts stay.
4. The recalculation itself, about 2½ hours.
5. Processes the games played meanwhile.
6. Check. It should report 0 failures.

*Line for step 4*
With the real path to public_html:

```
* * * * * cd /path/to/public_html && php ops/dispatch.php CMD=ProcessPending target=live-game
```

*Optional, whenever you have time*
Converting the tables to InnoDB would make a broken connection in the middle of a game harmless: the game is simply tried again a minute later. The game app needs no change.
````

**Reply to Steve's two questions (2026-11-28, for Dagh to send).** Steve tried the commands and got errors, and asked whether he must whitelist every player who logs in; he would prefer that the scripts did not answer with errors unless something is really wrong. He had read "game machine" in message 1 as the players' computers; it meant the game server, the one machine that reports finished games to the website. Copy everything inside the box below.

````text
Hi Steve, you guessed right: the commands only work after my upload of the 17 files, because they are new. Your try changed nothing, the ladder is fine.

About the whitelist: sorry, I wrote that unclearly. By "game machine" I meant the game server, the one machine that reports finished games to the website. The players' computers never call our scripts.

But your wish was a good one, so I've changed the scripts: they now answer normally (200) for everything that is part of normal running, like a game processed, a game that waits for the next call, or a game that was already done. What happened is written in the reply text, and anything unusual goes to the PHP error log. They only answer with an error code when something is really broken: a wrong key, a missing config file, or a PHP crash. So you don't need to whitelist anything, not even the game server.

The old scripts on live today answer 500 when a game call fails or is busy. That may be how the game server got blocked from Oct 30 to Nov 3. The upload fixes that too.

One small thing: if you ever change the shared key, change it in the game server and in ops/config/dispatch-http.ini at the same moment. With a wrong key the script has to answer with an error, so a mismatch could get the game server blocked.

So step 1 is now only the backup. Tell me when it's done, and I'll upload the 17 files right away. Then you can run the commands the same day.
````

**Go-ahead after the upload (2026-11-28, for Dagh to send).**

````text
Hi Steve, thanks for the backup! The 17 files are uploaded and checked, and the website works as before.

You can now run the 6 commands from my first message (*Commands for step 3*), and then add the one-minute job (*Line for step 4*). If you run them on joshua, copy the ops and includes folders from public_html first, now that they are updated.

The first command only reports and changes nothing: it should say holes=154 or a few more. If it says something else, or anything gives an error, just send me the text before you go on.

Tell me when it's all done, and I'll check the ladder against the ratings we expect.
````

**Recalculation too slow (2026-11-28, for Dagh to send).**

````text
Hi Steve, I can see the recalculation running on the website, but it's going slowly: about 14 games a minute. At that speed the 78,500 games take about 4 days. That's the speed we expect when the script runs on the web server, because every database call has to travel to the database machine and back (about 19 ms each, and a game needs about 230 of them). Next to the database it should do about 10 games a second, so about 2½ hours in all.

Can you run it on the machine with the online database instead? If so:
1. Stop the running command (Ctrl-C). That's safe: the commands start from zero, so nothing is lost, and games played meanwhile just wait.
2. On the database machine, with our ops and includes folders copied there and the target pointing at the database there, run commands 2 to 6 from my first message again.

If the database machine can't run PHP, just let the current run continue: it will finish correctly by itself, only slowly. In that case please make sure it keeps running when you log out (screen or nohup), and tell me, so we can find a faster way for next time.
````

**Message 2** (server housekeeping) is in Dagh's private notes, not in this repo.
