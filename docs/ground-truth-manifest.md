# Ground truth manifest

For Dagh and Steve — quick sanity check when planning replay, migrations, or prod work.

Ground = facts that happened. Do not rewrite on live prod from this repo.

Derived = can be recomputed or dropped when obsolete. Safe to reset on sandbox (`ko2unity_work` / staging `kooldb1`).

Legacy / someone else = still on the server, but not Dagh’s job to change.

Detail: `docs/replay-v1-scope-and-reset.md`, `docs/website-data-contract.md`, `docs/coordination/database-copies-2026-06.md`. **Prepare / zero derived on work:** `docs/work-db-prepare.md`.


Where things live

  Local browser dev     ko2unity_db        dev + all website aggregate tables
  Local frozen copy     ko2unity_baseline   prod import, never replay or migrate
  Local sandbox         ko2unity_work       experiments, replay, new tables
  Staging work          kooldb1             same role as ko2unity_work
  Staging reset copy    kooldb2             same role as ko2unity_baseline
  Production            live DB (Steve)     five core tables below


Prod — five tables at a glance

  eventhistory          whole table = GROUND
  resulttable           whole table = GROUND
  ratedresults          8 columns ground, rest derived
  playertable           ~50 columns ground, ~93 derived (see lists)
  generalstatstable     whole table = DERIVED (one row, id = 1)

  ko2unity_db also has ~14 extra tables (milestones, period stats, etc.).
  Those exist only on dev/staging after migrations — not on prod today.
  All of them are derived.


eventhistory — do not touch

  Server log: logins, logouts, game lines, registrations.
  ~300k rows. Not part of ladder replay.

  Columns: ID, EventTime, EventType, Num1, Num2, Str1, Str2


resulttable — do not touch

  Bigger match log (rated and unrated). Replay v1 leaves it alone.


ratedresults — split

  Ground (match facts only):
    id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB

  Derived (Elo, flags, winner, sums — replay can rebuild on sandbox):
    RatingA, RatingB, RatingDifference,
    ExpectedScoreA, ExpectedScoreB, ActualScore,
    AdjustmentA, AdjustmentB, NewRatingA, NewRatingB,
    SumOfGoals, GoalDifference, WinnerID,
    HomeWin, Draw, AwayWin, DDPlayerA, DDPlayerB, CSPlayerA, CSPlayerB


playertable — split

  Ground — identity, account, prefs, telemetry:
    ID, Name, Email, CryptPassword, GUID, LegalAccepted, JoinDate
    Pref_Formation, Pref_AutoSlides, Pref_PBD, Pref_TrapFix
    Pref_UseCustomKits, Pref_KitStyleA, Pref_KitColour1–3, Pref_KitStyleB, Pref_KitColour4–5
    Country, Language, Profile_Bio, Profile_AvatarURL, Profile_LinkURL
    AvoidRank, Challenge1, Challenge2, NewForumPosts
    IsOnline, IPPort, LobbyTime
    LastLogin, LastActive
    all Feedback_* columns

  Derived — ladder career (PHP ops live / ops simul rebuild on sandbox):
    Rating, NumberGames, wins/draws/losses, ratios, goals, extremes, streaks,
    opponent/victim/culprit counts, all *GameID and *VictimID and *CulpritID columns,
    LastGame (rebuilt from games — not the same as LastLogin)

  Not Dagh — derived but owned elsewhere (Steve B / legacy Unity):
    Display
    PlayerRank
    Old site used these for “who shows on a list”. This ratings site does not.
    Do not read or write in new PHP. Leave prod values alone unless Steve B agrees.

  Planned delete on prod (coordinate with Steve):
    KungFuLevel, KungFuWinBank, KungFuLoseBank, KungFuLastGameID, KungFuLastGameDate,
    KungFuNumberOfGames, KungFuPeakLevel, KungFuPeakLevelDate, KungFuDisplay
    Already dropped on local dev. Also consider resulttable.KungFuGameID when dropping.


generalstatstable — derived, not ground

  Single row: server totals + Hall of Fame records.
  PHP ops updates live on prod (since 2026-07-18). Ops simul rebuilds on sandbox.

  Good targets to delete on prod (obsolete derived):
    BiggestWinRatio, BiggestGoalsForAverage, SmallestGoalsAgainstAverage,
    BiggestGoalRatio, BiggestDoubleDigitsRatio, BiggestCleanSheetsRatio,
    BiggestAverageOpponentRating
    plus matching *ID, *Name, *Date columns for each
    Dev already dropped these — Records page uses playertable instead.
    Legacy C++ path for these was bugged; SCH-003 drop executed with ops cutover.

  Dev may have extra streak columns on this row; prod may still have ratio columns.
  Both sides are still derived, not facts.


What Dagh’s repo may do

  On ko2unity_work / kooldb1:
    reset and replay derived columns on ratedresults and playertable
    rebuild generalstatstable and website aggregate tables

  On ko2unity_baseline / kooldb2:
    read only

  On production:
    no scripts that UPDATE or DELETE ground tables or ground columns
    new games = Steve inserts ground truth → PHP ops `ProcessCompletedGame` (derived)

  Schema cleanup (KungFu drop, ratio column drop):
    Steve + registered migration — not a day-to-day agent change


When unsure

  Match facts and eventhistory → ground.
  Anything you could rebuild from rated games in order → derived.
  Display, PlayerRank, KungFu → not your lane unless Steve(s) sign off.
