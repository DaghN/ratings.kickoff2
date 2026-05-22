# Profile data audit

Working document for Profile redesign (pass 2 mocks).  
**Part A** = sources & map. **Part B** = value & story ranking (B1 + B2). **Part C** = feast contract + pass 2 mock briefs (not a locked wireframe).

Anchor player for examples: **Steve, `id=237`**.

---

## Part A — Sources & map

### A1. Feast tab architecture (all player pages)

Every `individual*.php` page shares:

| Layer | Source | Notes |
|-------|--------|-------|
| Site header | `includes/site_header.php` | Wordmark, player search, realm switcher |
| Hero | `includes/player_hero.php` + `player_hero_vars.php` | Name, **Rating**, **Peak**, **Games** (initial avatar). **Rank not in hero.** |
| Pills | `includes/player_nav.php` | Profile · Games · Wins · Goals · DDs |
| Body | Per-page | See below |

**Pill → page → primary data source**

| Pill | File | Primary source | Grain |
|------|------|----------------|-------|
| **Profile** | `individual1.php` | `playertable` + `ratedresults` (lookups) + Chart.js APIs | One player; career + charts |
| **Games** | `individual3.php` | `ratedresults` (all rows for player, newest first) | One row per rated game |
| **Wins** | `individual2a.php` | `ratedresults` aggregated per opponent | Opponent × W/D/L counts & ratios |
| **Goals** | `individual2b.php` | `ratedresults` aggregated per opponent | Opponent × goal stats |
| **DDs** | `individual2c.php` | `ratedresults` aggregated per opponent | Opponent × DD/CS stats |

---

### A2. Production Profile (`individual1.php`) — vertical order today

Actual render order (what users scroll through):

1. **Hero** — Name, Rating (accent), Peak, Games  
2. **Charts (7 UI blocks)** — see A4  
3. **Table A — “Identity”** (2-col label / value)  
   - Name, Rank (computed), Rating, Last Login, Last Game, Join Date  
4. **Table B — “Career totals”**  
   - Games, Wins (%), Draws (%), Losses (%)  
   - Goals For (avg), Goals Against (avg), Goal Ratio  
   - Double Digits (%), Clean Sheets (%), DD Conceded (%), CS Conceded (%)  
5. **Table C — “Rating block”** (mixed 2-col and 3-col “extreme” rows)  
   - Average Opponent Rating  
   - Current Rating Ascent **or** Descent (one row)  
   - Biggest Rating Ascent, Biggest Rating Descent (order swaps by sign of descent)  
   - Recent Avg. Rating (30 games) — *label says 30; field is `RecentAverageRating` (C++ uses last 300 games per MEMORY)*  
   - Rating Nadir + date (from `LowestRatingGameID`)  
   - Peak Rating + date (from `PeakRatingGameID`)  
6. **Table D — “Streaks”** (current + longest + “non-*”)  
   - Winning, Longest Winning  
   - Drawing, Longest Drawing  
   - Losing, Longest Losing  
   - No Loss, Longest No Loss  
   - No Draw, Longest No Draw  
   - No Win, Longest No Win  
7. **Table E — “Opponent counts”** (aggregate counts, not per-opponent)  
   - Different Opponents, Victims, Culprits  
   - DD Victims, DD Culprits, CS Victims, CS Culprits  
   - Biggest Loss Victims, Biggest Win Culprits, Most Goals Conceded Victims, Most Goals Scored Culprits, Least Goals Scored Victims, Least Goals Conceded Culprits  
8. **Table F — “Recent result pointers”** (3-col: label, score vs opponent link, date)  
   - Last Win, Last Draw, Last Loss (`LastWinGameID`, etc.)  
9. **Table G — “Positive extremes”** (3-col game links)  
   - Biggest Win, Most For, Least Against  
10. **Table H — “Negative extremes”** (3-col)  
    - Biggest Loss, Most Against, Least For  
11. **Table I — “Draw / sum extremes”** (3-col)  
    - Biggest Draw, Biggest Sum, Smallest Sum  
12. **Table J — “Rating prestige games”** (3-col)  
    - Max Rated Victim (opponent rating in parens), Min Rated Culprit  

**Not on Profile:** per-opponent tables (those are Wins/Goals/DDs tabs).

---

### A3. `playertable` — fields relevant to public Profile

#### A3a. Shown in hero or Profile tables today

| Field(s) | Shown where | Companion / link |
|----------|-------------|------------------|
| `Name` | Hero + Table A | Duplicate |
| `Rating` | Hero + Table A | — |
| `PeakRating` | Hero | Date via `PeakRatingGameID` in Table C |
| `NumberGames` | Hero + Table B | — |
| Rank (computed) | Table A only | `COUNT+1 WHERE rating > player` |
| `JoinDate`, `LastLogin`, `LastGame` | Table A | — |
| `NumberWins/Draws/Losses`, `WinRatio`, etc. | Table B | Percentages inline |
| `GoalsFor/Against`, averages, `GoalRatio` | Table B | — |
| `DoubleDigits*`, `CleanSheets*` + ratios | Table B | — |
| `AverageOpponentRating` | Table C | — |
| `CurrentRatingAscent/Descent`, `Biggest*` | Table C | — |
| `RecentAverageRating` | Table C | Label mismatch (30 vs 300) |
| `LowestRating` + `LowestRatingGameID` | Table C | Date lookup |
| `PeakRating` + `PeakRatingGameID` | Table C | Date lookup |
| All `*Streak` / `Longest*` | Table D | — |
| `DifferentOpponents`, Victims/Culprits counts | Table E | — |
| `*Victims`, `*Culprits` count fields | Table E | — |
| `LastWin/Draw/LossGameID` | Table F | Full game row |
| `BiggestWinGameID`, `MostGoalsScoredGameID`, `LeastGoalsConcededGameID` | Table G | 3-col |
| `BiggestLossGameID`, `MostGoalsConcededGameID`, `LeastGoalsScoredGameID` | Table H | 3-col |
| `BiggestDrawGameID`, `BiggestSumOfGoalsGameID`, `SmallestSumOfGoalsGameID` | Table I | 3-col |
| `HighestRatedVictimGameID`, `LowestRatedCulpritGameID` | Table J | Opponent rating in cell |

#### A3b. On `playertable` but **not** shown on Profile (candidates / dead)

| Field | Notes |
|-------|-------|
| `Profile_Bio`, `Profile_AvatarURL`, `Profile_LinkURL` | Schema exists; PHP unused |
| `PlayerRank` | Legacy Unity rank (9999 sentinel); site uses computed rank |
| `HighestRatedVictim`, `LowestRatedCulprit` | Scalar; site uses game-linked rows instead |
| `Display` | Gates whether stats show or "-" |
| `Email`, `CryptPassword`, prefs, `Feedback_*`, `IsOnline`, etc. | Account/telemetry — not for public profile |

#### A3c. Scalar extremes without game link on Profile (only magnitude on row)

Many Table B fields are scalars; Tables G–J add **story** via `*GameID` + opponent names.

| Scalar | Typical use |
|--------|-------------|
| `BiggestWinDifference`, `BiggestDrawSum`, `BiggestLossDifference` | Magnitude; game in G/H/I |
| `MostGoalsScored`, `LeastGoalsScored`, etc. | Same pattern |
| `SmallestSumOfGoals`, `BiggestSumOfGoals` | Same |

---

### A4. Chart APIs & UI (Profile only today)

All: `GET`, `realm=online`, `id=<player>`. MariaDB / `ratedresults` (+ `playertable` for name).

| # | UI title (approx) | API | JS | Question it answers |
|---|-------------------|-----|-----|---------------------|
| 1 | Rating history | `player_rating_history.php` | `player-rating-chart.js` | How did ELO move over **calendar time**? Peak/current marked |
| 2 | Games per month | `player_games_by_month.php` | `player-games-month-chart.js` | When was I active? |
| 3 | Rating by game number | `player_rating_history.php` (same data) | `player-rating-game-chart.js` | ELO after each game (1…N), equal spacing |
| 4 | Win rate vs opponent rating | `player_winrate_vs_opponent_rating.php` | `player-winrate-opponent-chart.js` | Do I beat players rated higher/lower? (50-pt buckets) |
| 5 | Most played opponents | `player_top_opponents.php` | `player-top-opponents-chart.js` | Who do I play most? (top 20 bars, click → H2H) |
| 6 | Head-to-head cumulative | `player_head_to_head.php` | `player-head-to-head-chart.js` | vs selected opponent: cumulative wins over time |
| 7 | Rating comparison vs opponent | `player_compare_rating_history.php` | `player-compare-rating-chart.js` | Both players’ rating curves overlaid |
| — | Opponent search | `player_search.php` | `player-h2h-opponent-search.js` | Pick opponent for #6–7 |

**Rivalry cluster:** #5–7 + search are one interaction system (top opponents drives selection).

**Not a Profile chart today:** single-line “Lee 2042 games” (derivable from `ratedresults` or top-opponents API).

---

### A5. `ratedresults` — row-level data (Games tab + lookups)

**Games tab (`individual3`)** exposes per game:

- Game ID, Date/time, Team A/B names & ids, goals, Result (Win/Loss/draw), Opponent, goals for/against player, diff, sum, **player pre-rating**, opponent pre-rating, expected score %, **rating adjustment**

**Profile extremes** pull one row by `*GameID` from `playertable`: scoreline, opponent link, date (sometimes opponent rating).

**Derivable in SQL (not all exposed on site):**

- Recent N games (mock loader pattern)  
- Games this calendar month  
- Top opponents by count (same as API #5)  
- Per-opponent anything (Wins/Goals/DDs tabs)  
- Monthly goal totals, busiest month, etc. (fun-stats brainstorm — not built)

---

### A6. Sibling tabs — column inventory (what Profile should not duplicate)

#### Games (`individual3.php`)

Full **match ledger** (sortable/filterable/paged). Authoritative for “what happened game by game.”

#### Wins (`individual2a.php`) — per opponent

Games, Wins, Draws, Losses, Win/Draw/Loss **ratios**.

#### Goals (`individual2b.php`) — per opponent

Games, GF, GA, averages, ratio, most/least scored/conceded, biggest W/L/D diffs, draw count, biggest/smallest goal sums.

#### DDs (`individual2c.php`) — per opponent

Games, DD, DD conceded, CS, CS conceded, four ratio columns.

**Implication:** Profile should **not** rebuild full per-opponent tables; Wins/Goals/DDs own that. Profile may **summarize** one rivalry or link to a tab.

---

### A7. Computed / off-table assets (not on prod Profile)

| Asset | How | Used today |
|-------|-----|------------|
| Ladder rank | Query on `playertable` | Table A only (not hero) |
| Top opponents list | SQL or `player_top_opponents.php` | Chart #5 only |
| H2H / compare series | APIs when opponent chosen | Charts #6–7 |
| Established player (20th game) | Window SQL | Server trends only |
| `Profile_*` media | Columns | Nowhere |
| Online presence / live | `IsOnline`, status.php | Status tab (Phase B), not profile |

---

### A8. Duplicate & overlap map (same fact, many places)

| Fact | Appearances |
|------|-------------|
| Name | Hero, Table A |
| Rating | Hero, Table A, charts 1–3 |
| Peak | Hero, Table C (+ chart 1 summary) |
| Games count | Hero, Table B |
| Win/loss/draw | Table B; implied in Games tab; form derivable from recent games |
| Opponent universe | Table E counts; chart #5; full breakdown on Wins tab |
| Rivalry depth | Chart #5–7; Wins tab per opponent; **not** one prose line today |
| Career game extremes | Tables G–J; partial overlap with Goals tab per-opponent MAX columns |
| Rating arc | Charts 1 & 3; Table C nadir/peak/ascent |
| Streaks | Table D only |
| DD/CS career totals | Table B; DD tab per opponent |

---

### A9. Display gating

If `Display != 1`, hero shows "—" for rating/peak; most table cells show "-" or 0.  
Profile redesign must define **empty / low-games player** behavior (Part B).

---

### A10. Part A summary — inventory counts

| Bucket | Count (approx) |
|--------|----------------|
| Chart blocks on Profile | 7 + opponent search |
| playertable fields used on Profile | ~70+ row values across tables |
| Game-linked story rows (3-col) | 16 row types |
| Per-opponent columns (other tabs) | Wins 8, Goals 16, DDs 10 |
| Unused public profile columns | `Profile_*`, `PlayerRank` (for display) |

**Next:** Part B ranks each bucket for storytelling value (headline / scroll / demote / tab-only / chart vs table). Part C turns that into a single scroll feast order.

---

## Part B1 — Per-asset value ranking

**Date:** May 2026. **Not final** until Dagh confirms **DROP** rows in §B1.9.

### B1.0 Verdict legend

| Verdict | Meaning on Profile tab |
|---------|-------------------------|
| **CORE** | Fixed **identity strip** (always same place): name · rank · rating · peak · games |
| **HEADLINE** | Strong candidate for **first screen** (below CORE / pills, above heavy scroll) |
| **SCROLL** | Keep on Profile — full feast, clear presentation |
| **COMPACT** | Keep but **merged** into a small block (not its own legacy row) |
| **DEMOTE** | Keep on Profile but **low** in scroll / lighter styling |
| **CHART** | Keep as chart (layout/size may change in pass 2) |
| **CHART-DEMOTE** | Keep chart but **secondary** (pair side-by-side, shorter, or below fold) |
| **RIVALRY** | Part of **rivalry / opponents** section (charts + short copy) |
| **TAB-GAMES** | Do not repeat — **Games** tab is canonical |
| **TAB-WINS** | Do not repeat — **Wins** tab |
| **TAB-GOALS** | Do not repeat — **Goals** tab |
| **TAB-DDS** | Do not repeat — **DDs** tab |
| **MERGE → X** | No standalone row; fold into asset X |
| **DROP** | **Remove from Profile** — logged in §B1.9 for reconsideration |

**Rank source (locked):** `COUNT(*)+1` on `playertable` where `display = 1` and `rating` greater than player’s — **not** `PlayerRank`.

**Sparse / `Display ≠ 1`:** CORE shows name; rating/peak/games/rank as “—” or hidden; most career rows empty — Profile should show **join date + games=0 story**, not blank encyclopedia.

---

### B1.1 Identity strip (CORE) — proposed

| Asset | Source | Verdict | Rationale |
|-------|--------|---------|-----------|
| Player name | `playertable.Name` | **CORE** | Primary label |
| Ladder rank | Computed query | **CORE** | Dagh confirmed; prestige + orientation |
| Current rating | `Rating` | **CORE** | Focal number (prod got this right) |
| Peak rating | `PeakRating` | **CORE** | Arc story with current; pair with rating |
| Rated games count | `NumberGames` | **CORE** | Participation prestige (Steve: 5940) |

**Not CORE but headline-adjacent (see B1.2):** last game date, win %, join tenure.

---

### B1.2 Charts & rivalry UI (each asset)

| ID | Asset | Verdict | Rationale | If duplicate, winner |
|----|-------|---------|-----------|----------------------|
| C1 | Rating history (calendar) + peak/current summary | **CHART** | Best single “career arc” view; supports peak vs now | Wins over rating nadir row + peak date row |
| C2 | Games per month | **CHART** | Activity / “still here” rhythm; complements last-game | Wins over raw month SQL alone |
| C3 | Rating by game number | **CHART-DEMOTE** | Useful for veterans; overlaps C1 semantically | Keep but smaller or paired with C1 on desktop; not headline |
| C4 | Win rate vs opponent rating (buckets) | **CHART-DEMOTE** | Analyst “how do you perform vs strength”; not welcoming-first | Below rivalry block |
| C5 | Most played opponents (top 20 bar) | **CHART** + **RIVALRY** | Primary rivalry discovery; click drives H2H | Wins over Table E opponent counts + mock rival boxes |
| C6 | H2H cumulative wins vs opponent | **CHART** + **RIVALRY** | Deep rematch story **after** opponent picked | Keep in feast; default opponent = #1 from C5 |
| C7 | Compare rating history vs opponent | **CHART** + **RIVALRY** | Strong for top rivals; optional until opponent selected | Same section as C6 |
| C8 | Opponent search (H2H picker) | **RIVALRY** | Needed for C6–7; compact UI, not a “section” | MERGE into rivalry block chrome |

**Chart presentation (pass 2 note):** thinner lines, paired grids on PC, stacked on phone — still **all on page**, not hidden.

---

### B1.3 Table A — identity / dates (row-by-row)

| Row (prod label) | Fields | Verdict | Rationale |
|------------------|--------|---------|-----------|
| Name | `Name` | **DROP** | Duplicate of CORE |
| Rank | computed | **MERGE → CORE** | Elevate to strip |
| Rating | `Rating` | **DROP** | Duplicate of CORE |
| Last Login | `LastLogin` | **SCROLL** (high) — label **“Last seen online”** | Dagh: core social signal (“still active?”); not CORE strip but must be visible; absence humour lines → **B2/defer** |
| Last Game | `LastGame` | **HEADLINE** | Last **rated match**; pair with last seen (online ≠ last game) |
| Join Date | `JoinDate` | **SCROLL** or **COMPACT** | Tenure / “member since” — one line in participation block |

---

### B1.4 Table B — career totals (row-by-row)

| Row | Verdict | Rationale |
|-----|---------|-----------|
| Games | **DROP** | Duplicate of CORE |
| Wins (+ %) | **COMPACT** → **W-D-L block** | One strip: W / D / L counts + win % — headline or scroll |
| Draws (+ %) | **MERGE → W-D-L block** | Same |
| Losses (+ %) | **MERGE → W-D-L block** | Same |
| Goals For (+ avg) | **SCROLL** | Core football stat; avg in same row |
| Goals Against (+ avg) | **SCROLL** | Pair with GF |
| Goal Ratio | **MERGE → goals pair** | Redundant third number if GF/GA shown |
| Double Digits (+ %) | **COMPACT** → **DD/CS snapshot** | Community flavour; not 4 separate ratio rows |
| Clean Sheets (+ %) | **MERGE → DD/CS snapshot** | Same |
| Double Digits Conceded (+ %) | **TAB-DDS** or **MERGE → DD/CS snapshot** | Detail hunters → DDs tab; one combined line max on Profile |
| Clean Sheets Conceded (+ %) | **TAB-DDS** or **MERGE → DD/CS snapshot** | Same |

---

### B1.5 Table C — rating block (row-by-row)

| Row | Verdict | Rationale |
|-----|---------|-----------|
| Average Opponent Rating | **SCROLL** | “Strength of schedule” — interesting, one number |
| Current Rating Ascent / Descent | **DROP** | Dagh: arbitrary swing (e.g. draw −3 then climb); not a story — §B1.9 |
| Biggest Rating Ascent | **DROP** | Same — peak/arc covered by CORE + C1 — §B1.9 |
| Biggest Rating Descent | **DROP** | Same — §B1.9 |
| Recent Avg. Rating | **DROP** | Dagh: competes with current rating; obscure; implies “truer” form — §B1.9 |
| Rating Nadir (+ date) | **DROP** | Computable but low story / harsh; C1 shows troughs — §B1.9 |
| Peak Rating (+ date) | **MERGE → CORE caption** or **COMPACT** | Peak number in CORE; **date only** as subtitle or chart annotation — not full duplicate row |

**Scalars only on row (no game link):** `BiggestRatingAscent`, `BiggestRatingDescent` — if kept, prefer **SCROLL** text; game ID not stored for these in `playertable`.

---

### B1.6 Table D — streaks (row-by-row)

| Row | Verdict | Rationale |
|-----|---------|-----------|
| Winning Streak (current) | **SCROLL** | Live form |
| Longest Winning Streak | **HEADLINE** or **SCROLL** | Trophy-grade (Steve: 17) — moment card candidate |
| Drawing Streak (current) | **DROP** | Niche; rarely celebrated |
| Longest Drawing Streak | **DEMOTE** | Oddity stat; low universal appeal |
| Losing Streak (current) | **DROP** | Harsh, low invitation value |
| Longest Losing Streak | **DEMOTE** | Available for completeness; not headline |
| No Loss Streak (current) | **DROP** | Obscure |
| Longest No Loss Streak | **DEMOTE** | Secondary |
| No Draw Streak (current) | **DROP** | Very niche |
| Longest No Draw Streak | **DROP** | §B1.9 |
| No Win Streak (current) | **DROP** | Harsh |
| Longest No Win Streak | **DEMOTE** | Footnote |

**Proposed COMPACT block:** “Streaks” — current win + longest win + (optional) longest non-loss; drop rest from Profile.

---

### B1.7 Table E — opponent counts (row-by-row)

| Row | Verdict | Rationale |
|-----|---------|-----------|
| Different Opponents | **SCROLL** | Participation breadth — “played X distinct opponents” |
| Victims | **COMPACT** | KO2 jargon; one line “beaten X distinct opponents” if community expects term |
| Culprits | **COMPACT** | Pair with victims |
| Double Digit Victims | **TAB-DDS** | Per-opponent DD detail on DDs tab |
| Double Digit Culprits | **TAB-DDS** | Same |
| Clean Sheet Victims | **TAB-DDS** | Same |
| Clean Sheet Culprits | **TAB-DDS** | Same |
| Biggest Loss Victims (count) | **DROP** | Obscure count; not a story |
| Biggest Win Culprits (count) | **DROP** | §B1.9 |
| Most Goals Conceded Victims | **DROP** | §B1.9 |
| Most Goals Scored Culprits | **DROP** | §B1.9 |
| Least Goals Scored Victims | **DROP** | §B1.9 |
| Least Goals Conceded Culprits | **DROP** | §B1.9 |

**RIVALRY copy (derivable):** one **HEADLINE** line — e.g. “2,042 rated games vs Lee” — **MERGE** with C5, not separate boxes.

---

### B1.8 Game-linked rows (Tables F–J, each row)

| Row | `*GameID` | Verdict | Rationale |
|-----|-----------|---------|-----------|
| Last Win | `LastWinGameID` | **MERGE → recent matches** | Superseded by last N games list |
| Last Draw | `LastDrawGameID` | **MERGE → recent matches** | Same |
| Last Loss | `LastLossGameID` | **MERGE → recent matches** | Same |
| Biggest Win | `BiggestWinGameID` | **HEADLINE** | Trophy / moment card |
| Most For | `MostGoalsScoredGameID` | **SCROLL** | Goal festival story |
| Least Against | `LeastGoalsConcededGameID` | **SCROLL** | Defensive highlight |
| Biggest Loss | `BiggestLossGameID` | **DEMOTE** | Honest but not leading; available on scroll |
| Most Against | `MostGoalsConcededGameID` | **DEMOTE** | Pain game; low on page |
| Least For | `LeastGoalsScoredGameID` | **DROP** | “Worst attacking game” — embarrassment, low invite |
| Biggest Draw | `BiggestDrawGameID` | **SCROLL** | Fun high-scoring draw |
| Biggest Sum | `BiggestSumOfGoalsGameID` | **SCROLL** | Chaos / goal bonanza (may equal Most For) |
| Smallest Sum | `SmallestSumOfGoalsGameID` | **DROP** | Boring 0–0 style; low story |
| Max Rated Victim | `HighestRatedVictimGameID` | **SCROLL** | Prestige — beat a strong rated opponent |
| Min Rated Culprit | `LowestRatedCulpritGameID` | **DROP** | Obscure; “lost to lowest-rated opponent” — niche shame stat |

**Moments section (pass 2):** merge **HEADLINE + SCROLL** game rows into **cards** (score, opponent, year, link) — optional small icon (pass 1 A).

---

### B1.8b Derivable assets (not on prod Profile today)

| Asset | Source | Verdict | Rationale |
|-------|--------|---------|-----------|
| Recent N rated matches (e.g. 8–10) | `ratedresults` | **HEADLINE** | Replaces Last W/D/L + form pills; real density |
| Games this calendar month | `ratedresults` | **HEADLINE** or **COMPACT** | Aliveness (Steve: 32) |
| Win % only | `WinRatio` | **MERGE → W-D-L block** | Avoid extra row |
| Points to next rank above | computed | **COMPACT** | Optional CORE subtitle (“12 pts to #21”) |
| Signature rivalry one-liner | top opponent SQL | **HEADLINE** | Story; pairs with C5–7 |
| H2H W-D-L vs featured rival | `ratedresults` | **RIVALRY** **COMPACT** | One line before H2H chart |
| Busiest month / goals in month | SQL aggregate | **B2** | Defer new build to Part B2 |
| Established (20th game date) | window SQL | **DROP** for Profile | Server/trends metric, not player identity |
| `Profile_Bio` / avatar / video | `Profile_*` | **B2** | Schema gap — Part B2 |

---

### B1.9 DROP registry (reconsideration log)

**Purpose:** Every **DROP** is removed from Profile in B1 proposal only — data remains in DB, other tabs, or charts. Revisit before production cut.

| Asset | Was on prod as | Reason dropped | Could return if… |
|-------|----------------|----------------|------------------|
| Name (table row) | Table A | CORE duplicate | Never as table row |
| Rating (table row) | Table A | CORE duplicate | Never as table row |
| Games (table row) | Table B | CORE duplicate | Never as table row |
| ~~Last Login~~ | ~~Table A~~ | **RESTORED** → **Last seen online** (SCROLL, high) | See §B1.3; absence copy → B2 |
| Rating Nadir (+ date) | Table C | Harsh; C1 shows troughs | You want explicit “lowest rated game” plaque |
| Drawing Streak (current) | Table D | Niche | — |
| Losing Streak (current) | Table D | Harsh | — |
| No Loss / No Draw / No Win (current) | Table D | Obscure or harsh | — |
| Longest No Draw Streak | Table D | Very niche | — |
| Biggest Loss Victims (count) | Table E | Obscure aggregate | — |
| Biggest Win Culprits (count) | Table E | Obscure aggregate | — |
| Most Goals Conceded Victims (count) | Table E | Obscure aggregate | — |
| Most Goals Scored Culprits (count) | Table E | Obscure aggregate | — |
| Least Goals Scored Victims (count) | Table E | Obscure aggregate | — |
| Least Goals Conceded Culprits (count) | Table E | Obscure aggregate | — |
| Least For (game) | Table H | Low invite / shame | Historical curiosity |
| Smallest Sum (game) | Table I | Low excitement | — |
| Min Rated Culprit (game) | Table J | Obscure shame stat | Analyst mode demand |
| Current Rating Ascent / Descent | Table C | Arbitrary single-match swing; chart shows arc | — |
| Biggest Rating Ascent | Table C | Same; peak in CORE + C1 | — |
| Biggest Rating Descent | Table C | Same | — |
| Recent Avg. Rating (label “30 games”; engine ~300) | Table C | Competes with CORE rating; niche aggregate | If we add a dedicated “form” index later |

**Demoted but not dropped** (still on Profile, lower): longest losing streak, biggest loss game, most against game, many Table D “longest non-*”, C3/C4 charts.

---

### B1.10 Duplicate resolution (which copy wins)

| Fact | Keep on Profile | Drop / demote other |
|------|-----------------|---------------------|
| Name, rating, peak, games | **CORE strip** | Hero duplicate rows, Table A/B game row |
| Rank | **CORE** (computed) | `PlayerRank` column forever |
| Peak date | Chart C1 annotation or CORE subtitle | Full Table C peak row |
| Rating arc / lows / “form” | **C1** + **CORE rating** | Nadir row; recent avg rating |
| Activity timing | **Last game HEADLINE** + **last seen online (high SCROLL)** + **C2** | Duplicate login row in old Table A only |
| W/D/L record | **COMPACT block** | Three separate ratio rows |
| Opponent universe | **Different opponents SCROLL** + **C5** | Eight “victims/culprits” count rows |
| Rivalry depth | **C5–C7 + one HEADLINE line** | Five rival boxes (pass 1 B) |
| Recency | **Recent matches HEADLINE** | Last win/draw/loss rows; W/L/D pills |
| Extremes | **Moment cards** (subset) | 16-row extreme tables as formatted today |
| Per-opponent breakdown | **Tabs Wins/Goals/DDs** | Rebuilding grids on Profile |

---

### B1.11 Sparse / low-games player notes

| Situation | Profile behaviour (proposal) |
|-----------|----------------------------|
| `Display ≠ 1`, 0 rated games | CORE: name only; copy “Not on rated ladder yet” or similar; **DROP** charts that need games; **SCROLL** join date only |
| 1–19 games | CORE with rank if displayed; C1/C3 thin but valid; **DEMOTE** rivalry block; fewer moment cards |
| 20+ games | Full feast per verdicts above |

---

### B1.12 B1 summary counts (proposal)

| Verdict | Approx. count |
|---------|----------------|
| CORE | 5 |
| HEADLINE | ~8–10 (incl. recent matches, moments, rivalry line, charts C1–C2) |
| CHART (feast) | 7 (all stay; 2 demoted in layout) |
| SCROLL / COMPACT / DEMOTE | ~25–30 lines **after merges** (vs ~70+ today) |
| TAB-* (no Profile repeat) | Per-opponent columns + detailed DD grids |
| DROP (registry) | **22** row-level assets (last login **removed** from drops per Dagh review) |

**Dagh review (May 2026):** All §B1.9 drops **approved** except **last login restored** as **last seen online** (high SCROLL). **Rating ascent/descent** (current + biggest) → **DROP**. **Recent avg. rating** → **DROP**. Absence humour lines → defer **B2**.

**Dagh action:** Further DROP disputes → edit §B1.9 before pass 2 mocks / Part C.

**Next:** **Part B2** — what’s missing (new assets & patterns), after B1 confirmed.

---

## Part B2 — What’s missing (additive ideas)

**Date:** May 2026. **Scope:** New or reframed value for the Profile tab — **not** re-litigating B1 drops/keeps.

**Explicitly out of scope for B2** (handled elsewhere): **last seen online**, absence humour lines, placement of login — per Dagh; not evaluated below.

**Method:** Cross-domain patterns (sports stats, competitive gaming, chess/Go ladders, Strava/FIFA-adjacent *ideas* — not clones) mapped onto **what `ratedresults` + `playertable` already support** or could support with modest SQL/PHP. No fantasy integrations.

---

### B2.1 Jobs visitors still can’t answer well (after B1)

Even after B1’s feast, a strong Profile should still improve these **gaps**:

| Job | Partially served today | Gap |
|-----|------------------------|-----|
| “How good are they **right now**?” | CORE rating | Missing **ladder context** (rank field size, distance to next rank) |
| “What’s their **story**?” | Peak + games + charts | Missing **one-glance narrative** (tenure, signature rivalry, milestone) |
| “Are they **still in the mix**?” | Last game (B1 HEADLINE) | Missing **short-term form** without a second “rating” (goal diff, W-D-L in last N) |
| “Who matters to them?” | Chart C5–7 | Missing **default featured rival** + record line before click |
| “What are the **legendary** bits?” | Some game rows | Missing **curated moments wall** (not 16-table scan) |
| “How do they compare to **the field**?” | Rank # | Missing **percentile / population** framing |
| “Who is this **as a person**?” | Name + avatar initial | Missing **bio / photo** (schema exists, unused) |

B2 fills gaps **without** restoring B1 DROPs (nadir, recent avg, ascent/descent, etc.).

---

### B2.2 Cross-domain patterns (transferable ideas)

What mature profile products do well — adapted to a **long-running online ladder**:

| Pattern (elsewhere) | KO2 translation | Needs new data? |
|--------------------|-----------------|-----------------|
| **Hero + 3–5 KPIs** (ESPN, FIFA) | B1 CORE + activity block | No |
| **Mini sparkline** in header (finance, Strava) | Last 20–50 rating points inline | No — subset of C1 data |
| **Featured matchup** (boxing, tennis H2H) | Auto-select #1 opponent + W-D-L + jump to H2H chart | No |
| **Career milestones** (chess.com 1000th game) | 20th game “established”, 1000th/5000th game links, crossed 2000/2200 rating games | SQL window on `ratedresults` |
| **Season / year summary** (sports reference) | Best calendar year (wins or year-end rating), busiest month | SQL aggregate |
| **Activity heatmap** (GitHub, Strava) | Games per week over last 1–2 years | SQL |
| **Recent results strip** (every sports site) | Last 8–10 matches with score + Δrating | No |
| **Highlight reel** (cards, not tables) | 4–6 moment cards from `*GameID` | No — presentation |
| **Percentile / population** (percentile rank) | “Top 8% of rated players” from `display=1` count | One query |
| **Giant-killings / upsets** | Wins vs opponent 100+ rating higher (count + best example) | SQL |
| **Identity layer** (LinkedIn, Discord) | `Profile_Bio`, avatar URL, optional link | Columns exist |
| **Deep-link filters** (“all games vs X”) | Games tab with opponent pre-filter query param | UX + `individual3` |
| **Rolling form** (last 10 W-D-L) | One sentence or compact chips with **scores**, not letters only | No |
| **Share / OG card** | Static image for Discord — | Build pipeline — defer |
| **Live “playing now”** | Status tab / `IsOnline` | Not Profile v1 |

**Poor fits for KO2 (avoid):** generic achievement badges with no backend; social feed; karma; comparing to Messi — wrong culture.

---

### B2.3 New assets from **existing** data only (recommended candidates)

Grouped by **impact vs effort**. All truthful on ladder data; Steve (`237`) is the stress test.

#### Tier P0 — high impact, modest SQL/PHP; strong pass 2 mock candidates

| ID | Asset | What it is | Source / build | Why it matters |
|----|-------|------------|----------------|----------------|
| N1 | **Ladder context line** | “#22 of 259 rated players” (live counts) | Rank query + `COUNT(display=1)` | Answers “how elite?” without extra rating |
| N2 | **Gap to rank above** | “18 points behind #21” (or next higher rating) | Single row lookup on `playertable` | Natural competitive hook; sits under CORE |
| N3 | **Peak gap in plain language** | “353 below career peak (2279)” | `PeakRating − Rating` | Story without second “authoritative” rating |
| N4 | **Featured rivalry block** | Default opponent #1 by game count; **W–D–L** vs them; link to C6/C7 pre-filled | Top-opponent SQL + H2H aggregate | Fixes pass 1 “rival orbit” mistake the right way |
| N5 | **Recent matches strip** | 8–10 rows: date, opponent, score, W/D/L, **rating Δ** | `ratedresults` DESC | Form + aliveness; B1 HEADLINE |
| N6 | **Moments wall (curated)** | 4–6 cards: biggest win, goal festival, biggest draw, max rated victim, longest win streak number | `*GameID` + scalars | Replaces scanning Tables G–J |
| N7 | **Mini rating sparkline** | ~40px tall, last N games in CORE strip or activity row | Reuse `player_rating_history` points | Pro dashboards; does not compete with CORE number |

#### Tier P1 — strong feast additions; slightly more SQL or UI

| ID | Asset | What it is | Source / build | Notes |
|----|-------|------------|----------------|-------|
| N8 | **Established milestone** | “On ladder since [date of 20th rated game]” or “Established Jun 2017” | Window: 20th row by Date | Community vocabulary from server charts |
| N9 | **Career game milestones** | Links: “Game #1000”, “#5000” with `game.php` id from ordered `ratedresults` | `ROW_NUMBER()` or offset query | Steve: game #5940 → “#5000” party |
| N10 | **Rating threshold crossings** | “Crossed 2000 on [date]” (2200, 2100…) — pick 2–3 thresholds | Scan `NewRating*` chronologically | Celebratory, not analyst |
| N11 | **Busiest month** | Month with most games; optional goals in that month | `GROUP BY YYYY-MM` | Fun stat; monthly SQL |
| N12 | **Best calendar year** | Year with most wins or highest ending rating | Year aggregate | “2019 was your year” |
| N13 | **Giant-killings** | Count of wins where opp pre-rating ≥ player+100; best example game card | `ratedresults` join | Prestige beat stronger player |
| N14 | **Favourite victim** | Opponent you've beaten most (min games threshold) | Opponent aggregate | Positive mirror of “nemesis” |
| N15 | **Nemesis line** (careful) | Opponent with highest win% against you (min 20 games) | Opponent aggregate | Use **light copy** — not shame-first |
| N16 | **Net goals last 10** | GF−GA over last 10 games | Recent rows | Form without second rating |
| N17 | **Activity heatmap** | 52-week grid, colour = games count | Weekly bucket SQL | Visual “wow”; works on phone |
| N18 | **Goals per month chart** | GF/GA or total goals by month (new chart) | New API sibling to games/month | Storytelling complement to C2 |
| N19 | **Rolling win % chart** | 50-game rolling win rate | Window SQL + new small chart | Form over time — not recent avg **rating** |
| N20 | **Filter link: all games vs Lee** | `individual3.php?opponent=263` (param TBD) | UX only | Makes rivalry actionable |
| N21 | **Participation sentence** | Template: “[Name] has played [G] rated games across [O] opponents since [year].” | Fields + counts | Cheap narrative glue |

#### Tier P2 — valuable but schema, scope, or maintenance heavier

| ID | Asset | What it is | Why defer |
|----|-------|------------|-----------|
| N22 | **`Profile_Bio`** (short) | 1–3 lines under name | Needs edit UI + moderation story |
| N23 | **`Profile_AvatarURL` / photo** | Real face, especially Amiga realm | Upload/hosting trust |
| N24 | **`Profile_LinkURL`** | WC video, stream, forum | Same |
| N25 | **`player_media` table** | Multiple clips/photos | design-direction v2 |
| N26 | **Dual-realm profile card** | Online + Amiga side by side | P3–P4 ladder engine |
| N27 | **Share card / OG image** | Discord preview | Image gen pipeline |
| N28 | **Compare two players** | Pick任意 rival compare page | Scope beyond Profile |
| N29 | **Achievements / badges system** | “First 10-goal game” | Needs rules engine + icons |
| N30 | **Challenge1/2 forum hooks** | Columns on `playertable` | Product tie-in unclear |

#### Tier P3 — skip or harmful for Profile tone

| ID | Idea | Why skip |
|----|------|----------|
| — | Second smoothed rating (recent avg, Elo Glicko) | Dagh dropped — competes with CORE |
| — | Home/away splits using `HomeWin` | **Misleading** — `idA` is not “home stadium” online |
| — | Longest absence shaming block | Out of scope; separate from feast |
| — | Leaderboard of embarrassments | Against inclusive tone |
| — | AI-generated biography | Trust + tone risk |

---

### B2.4 Presentation-only upgrades (no new facts)

Worth pass 2 **without** new queries:

| Idea | Effect |
|------|--------|
| **CORE strip typography** | Prod clarity: one orange number, companions labeled |
| **Chart grid on desktop** | C1+C2 side-by-side; C3+C4 second row; shorter canvases |
| **Thinner chart lines, fewer gridlines** | “Professional” polish you asked for |
| **Rivalry section heading** | “Rivals & rematches” — C5–7 grouped, default opponent loaded |
| **Moment card icons** | Optional pass 1 A glyph per moment type |
| **Section labels** | “Career”, “Form”, “Rivals”, “Records” — scroll feast wayfinding |
| **Link density** | Every moment → `game.php`; every opponent → profile |

---

### B2.5 What B2 does **not** propose

- Restoring any §B1.9 **DROP** (nadir row, recent avg, ascent/descent, obscure counts, etc.).
- **Last seen online** placement or absence copy (deferred entirely).
- Duplicating **Wins/Goals/DDs** grids on Profile.
- **Five rival boxes** (pass 1 B) — superseded by **N4 + charts**.
- Site-wide features (Status live feed, forum embed) — other tabs / Phase B hub.

---

### B2.6 Recommended bundle for pass 2 mocks (superseded by §B2.9)

*Original P0 bundle replaced after Dagh B2 review.*

---

### B2.7 Open questions for Dagh — **resolved (May 2026)**

| # | Question | Decision |
|---|----------|----------|
| 1 | Featured rival | **All-time #1** by rated game count |
| 2 | Nemesis / favourite victim | **Neither** — **featured rivalry (N4) only** |
| 3 | Milestones | **Achievement/milestone system** (P2 session): celebratory for veterans, **aspirational** for newbies — not boring game-ID links |
| 4 | New charts in mocks | **Real APIs + JS** in a dedicated mock folder — not grey placeholders |
| 5 | Sparkline in CORE | **No** (N7 rejected); other **clear** slick graphics still welcome |

**Out of scope for B2/C:** last seen online placement, absence humour.

---

### B2.8 B2 additions — Dagh review (accepted / rejected)

| ID | Verdict | Notes |
|----|---------|-------|
| N1 Ladder “of N players” | **REJECT** | Leaderboards already give field context |
| N2 Points to next rank | **REJECT** | Obscure |
| N3 Peak gap text | **REJECT** | Peak + rating in CORE suffice |
| N4 Featured rivalry (all-time) | **ACCEPT** | Fun; pursue if implementation works |
| N5 Recent matches as “form” | **REJECT** as form | Full ledger on Games tab; optional **activity signal** only (see N5b) |
| N5b Activity signal | **CONSIDER** | Light “still active” cue for strangers — not W-D-L form strip |
| N6 Moments wall | **ACCEPT** | Elevate best extremes; pass 1 A direction |
| N6b Longevity tenure | **ACCEPT** | e.g. “10+ years on the ladder” — celebrate participation, not seniority |
| N7 Rating sparkline | **REJECT** | Too obscure; clarity first |
| N8 Established (20th game) | **REJECT** | Use **first rated game** date/tenure instead |
| N8b First game / tenure | **ACCEPT** | Longevity story |
| N9 Game #1000 links | **REJECT** | Boring links; celebrate activity another way |
| N9b Activity celebration | **BACKLOG** | Tied to achievement system (P2) |
| N10 Rating thresholds | **REJECT** | Redundant vs rating chart |
| N11 Busiest month | **ACCEPT** | Show game count; add **busiest day** + **busiest year** |
| N12 Best calendar year | **REJECT** | Redundant vs rating graph |
| N13 Giant-killings | **REJECT** | — |
| N14 Favourite victim | **REJECT** | Rivalry only |
| N15 Nemesis | **REJECT** | Rivalry only |
| N16 Net goals last 10 | **REJECT** | Form not valued in this ladder culture |
| N17 Activity heatmap | **ACCEPT** | Wow + real activity value |
| N18 Goals per month chart | **REJECT** | ~scales with games played |
| N19 Rolling win % | **REJECT** | — |
| N20 Filter link vs Lee | **DEFER** | Games tab already filterable — deep-link only if it aids rivalry block |
| N21 Participation sentence | **ACCEPT** | Celebrates activity |
| P2 Bio self-fill | **DEFER** | Weak uptake; **offline realm** photos/bio; **cross-realm profile link** when same person known |
| P2 Achievement system | **ACCEPT** (session) | Simple rules (“10 goals in a game”, etc.); aspirational + celebratory |

**Form metrics (general):** Premier-league-style “form” **not a fit** for semi-casual friends-on-server ladder — strangers don’t care either. **Activity/aliveness** ≠ form.

**Story in one breath:** **CORE (rating + games + peak + rank)** carries default story; **rivalry** and **longevity/moments** are **add-ons**, not a mandatory narrative paragraph.

---

### B2.9 Revised bundle for pass 2 mocks (post-review)

**Identity:** B1 CORE (name, rank, rating, peak, games) — prod clarity; no N1–N3, no sparkline.

**Headline / upper feast (additive):**

- **N21** participation sentence (includes tenure / game count celebration)  
- **N8b** longevity (years since **first rated game**, not “established”)  
- **N6** moments wall (curated extremes + e.g. longest win streak)  
- **N4** featured rivalry (all-time #1 + W–D–L + chart block C5–7 default opponent)  
- **N5b** (optional) minimal activity cue — not a form strip — TBD in mock  
- **N11** busiest month / day / year (compact “peak activity” stats)

**Scroll / visual:**

- B1 charts (redesigned layout; real code under mock chart folder per Dagh)  
- **N17** activity heatmap (at least one mock)  
- B1 SCROLL keeps (W-D-L, goals, avg opp rating, streaks, different opponents, etc.)

**Deferred to achievement session (P2):** N9b, threshold-style badges, aspirational milestones for low-game players.

**Presentation:** Section labels, chart grid, moment cards, rivalry chrome — explorative pass 2.

**Preview infra:** `api/player_feast/` + `js/player-feast/` for calendar; production chart JS reused on `profile_feast.php`.

---

### B2.10 B2 summary (after review)

| Status | Items |
|--------|--------|
| **ACCEPT** | N4, N6, N6b, N8b, N11 (+ day/year), N17, N21; P2 achievements (later session); offline photos + cross-realm link (later) |
| **CONSIDER** | N5b activity signal; N20 deep-link only if rivalry needs it |
| **REJECT** | N1–N3, N5 as form, N7, N8 established, N9 links, N10, N12–N16, N18–N19; nemesis/victim/giant-killing patterns |
| **REJECT (culture)** | Form metrics, second rating summaries, premier-league “current form” framing |

**Next:** **Part C** — feast contract (below).

---

## Part C — Feast contract & pass 2 mock briefs

**Date:** May 2026. **Purpose:** Direction for pass 2 mocks — **not** a locked wireframe.  
**Inputs:** B1 (per-asset verdicts + §B1.9 drops), B2.9 (Dagh-reviewed additions), prod clarity lesson (CORE strip).

**How to use this doc:**

| Use | Do not use |
|-----|------------|
| Checklist: every mock includes the same **content** | Fixed pixel order across mocks |
| Zone rules: what must read before deep scroll | Theme skins that omit rivalry or moments |
| Reference order v0 as **starting stack** | Lab banner on mock pages |
| Per-mock **visual emphasis** paragraphs | Restoring §B1.9 DROPs |

After pass 2 review → **C v1** may lock a single production order.

---

### C1. Non‑negotiables (all pass 2 mocks)

#### C1.1 Identity CORE (prod clarity)

Fixed strip — **one focal rating** (realm accent), companions labeled, no competing rating summaries:

| Slot | Source |
|------|--------|
| Name | `playertable.Name` |
| Rank | Computed (`display=1`, rating sort) — **not** `PlayerRank` |
| Rating | `Rating` |
| Peak | `PeakRating` |
| Games | `NumberGames` |

**Excluded from CORE:** recent avg rating, ascent/descent, peak-gap sentence, “#22 of 259”, points to next rank.

#### C1.2 Chrome & navigation

- Site header (wordmark, search, realm) — unchanged.  
- **Hero + feast pills:** hero (CORE) then **Profile · Games · Wins · Goals · DDs** — same as prod IA (player context on every feast tab).  
- **No** profile-lab banner on preview pages (lab portal removed May 2026; single preview at `profile_feast.php`).

#### C1.3 Content parity (every mock includes)

| Block | Source | Notes |
|-------|--------|-------|
| **Participation / tenure** | N21 + N8b | Sentence + years since **first rated game** (not “established” 20th) |
| **Moments wall** | N6 + B1 game rows | Curated cards (biggest win, goal festival, big draw, max rated victim, **longest win streak**, etc.) — not 16-row extreme table |
| **Featured rivalry** | N4 | **All-time #1** opponent by game count; W–D–L line; charts default to that opponent |
| **Rivalry charts** | C5–C8 | Top opponents, H2H, compare, search — **on page**, grouped; layout flexible |
| **Rating & activity charts** | C1–C2 + B1 keeps | Rating over time, games per month; redesign allowed (pair, resize, thinner lines) |
| **CHART-DEMOTE** | C3–C4 | Rating-by-game#, win-rate buckets — present somewhere in scroll |
| **Activity heatmap** | N17 | At least **one** mock wires real API + JS (mock chart folder) |
| **Busiest periods** | N11 | Busiest **month, day, year** (game counts shown) |
| **B1 SCROLL stats** | §B1 | W–D–L compact block, goals for/against, avg opponent rating, current/longest win streak, different opponents — **not** full legacy table layout |
| **Last seen online** | B1.3 | High placement somewhere in upper feast — **out of B2 scope** but **accepted**; label “Last seen online”; absence jokes deferred |

#### C1.4 Explicitly out (all mocks)

- §B1.9 **DROP** registry rows (nadir, recent avg, ascent/descent, nemesis/victim/giant-killing, obscure counts, etc.).  
- Form metrics: last-10 W/L pills, rolling win %, net goals last 10.  
- N1–N3 ladder context / peak-gap copy.  
- Rival orbit boxes (pass 1 B).  
- Hidden `<details>` analyst sections.  
- Duplicate CORE rows in tables.  
- Full per-opponent grids (Wins/Goals/DDs tabs own those).

#### C1.5 Sparse / `Display ≠ 1` players

| Case | Behaviour |
|------|-----------|
| Not on ladder (`Display ≠ 1`) | CORE: name; rating/peak/rank/games as “—” or hidden; short copy; omit or thin charts needing history |
| Few games (&lt;20) | Full contract but smaller moments set; rivalry block if any opponent exists |
| Veteran (e.g. Steve 237) | Full contract — stress test |

#### C1.6 Mock technical requirements

- Anchor player: **`id=237`** (overridable `?id=`).  
- **Real** chart endpoints + JS (production chart JS on preview; calendar via `api/player_feast/`).
- Reuse production `theme.css` tokens; preview layout CSS (`player-feast*.css`).

---

### C2. Zones (priority bands — order within band is flexible)

```
┌─────────────────────────────────────────┐
│  ZONE A — IDENTITY (must land fast)    │
│  CORE strip + pills                     │
│  Optional: one activity cue (last game    │
│  and/or games this month)               │
└─────────────────────────────────────────┘
          ↓  (celebrate before analyst)
┌─────────────────────────────────────────┐
│  ZONE B — CELEBRATE                     │
│  Participation + tenure · moments wall  │
│  · busiest month/day/year               │
│  · last seen online (high)              │
└─────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────┐
│  ZONE C — UNDERSTAND                    │
│  Featured rivalry + chart cluster       │
│  · heatmap · remaining charts           │
│  · compact career stats (B1 SCROLL)     │
└─────────────────────────────────────────┘
```

**Rules:**

1. **Zone A** must be understood in ~5 seconds — prod clarity wins.  
2. **Zone B** before **Zone C** in *meaning* (participation/stories before bucketed win-rate chart) — **not** necessarily strict DOM order in every mock.  
3. **Zone C** is scroll feast — everything visible by scrolling; **no** accordions hiding charts.  
4. **Games tab** remains canonical for full match ledger — Profile may show **light** activity cue only (N5b), not a second ledger.

---

### C3. Reference vertical order (draft v0 — revise freely)

Suggested **starting** stack for mocks; permute within zones for pass 2 exploration:

| # | Block |
|---|--------|
| 1 | Site header |
| 2 | CORE strip (5 stats) |
| 3 | Feast pills |
| 4 | Participation sentence + tenure (years since first game) |
| 5 | Last seen online + last rated game (compact activity row) |
| 6 | Moments wall (4–6 cards) |
| 7 | Busiest month / day / year |
| 8 | Featured rivalry intro (name, games, W–D–L) |
| 9 | Chart: top opponents (C5) — default select #1 rival |
| 10 | Charts: H2H + compare (C6–7) + opponent search |
| 11 | Chart: rating over time (C1) |
| 12 | Chart: games per month (C2) |
| 13 | Activity heatmap (N17) |
| 14 | Charts: rating by game # (C3) + win rate vs opp rating (C4) — paired on desktop if possible |
| 15 | Compact stats: W–D–L, goals, avg opp rating, streaks, different opponents |

**After pass 2:** pick one mock/hybrid → **C v1** production order.

---

### C4. Pass 2 mock briefs (same checklist, different emphasis)

**May 2026:** Lab mocks A/B/C and portal removed. Working preview: `profile_feast.php` (integrate → `individual1.php` + feast tabs). Archived mocks in git before cleanup (`b8c5a98`).  
**Content parity** per C1.3; **visual emphasis** differs:

#### Mock A — **The Chronicle** (celebration-forward)

**Thesis:** Magazine / trophy room — moments and longevity lead; charts support the legend.

| Emphasis | Treatment |
|----------|-----------|
| Zone B dominant above fold | Large moments grid; strong participation headline; busiest periods as “footnotes” with personality |
| Rivalry | Present but **below** moments — one sharp rivalry line, then chart block |
| Charts | C1–C2 full width; C3–C4 demoted lower; heatmap as “career texture” |
| Stats | Compact footer strip, not table encyclopedia |
| Look | Editorial type, card icons (pass 1 A), warm copy |

**Tests:** Do moments + tenure carry the page before analytics?

#### Mock B — **The Arena** (rivalry-forward)

**Thesis:** Rematch story — all-time featured rival owns the upper scroll; charts are the centerpiece.

| Emphasis | Treatment |
|----------|-----------|
| Rivalry block high | #1 opponent name, game count, W–D–L prominent; C5–C7 immediately below |
| Moments | Smaller row — 3 cards only |
| Heatmap | Mid-page — shows **shared history** rhythm alongside rivalry |
| CORE | Strict prod clarity — no extra numbers in strip |
| Look | Competitive, high contrast, chart grid 2×2 on desktop; **no** teal second accent; **no** form pills |

**Tests:** Does default H2H feel alive without rival boxes?

#### Mock C — **The Atlas** (activity-forward)

**Thesis:** Presence on the ladder — heatmap + participation + charts prove an active career.

| Emphasis | Treatment |
|----------|-----------|
| Heatmap + busiest periods high | N17 and N11 near top of Zone B/C |
| Participation sentence | Large typographic band |
| Moments | Horizontal “exhibits” under heatmap |
| Rivalry | Standard block mid-scroll |
| Charts | C2 + C1 adjacent; rating journey readable at a glance |
| Look | Clean cartography — labels, section titles (“Career”, “Rivals”, “Records”) |

**Tests:** Does activity read without “form” metrics?

---

### C5. Presentation principles (pass 2 — explorative)

| Principle | Application |
|-----------|-------------|
| **Clarity = cool** | Every graphic readable in 2 seconds; no sparkline obscurity |
| **One orange number** | Rating focal in CORE; chart accents from `theme.css` |
| **Desktop grid, mobile stack** | Side-by-side charts where it cuts scroll; stack on narrow |
| **Section labels** | Short headings between zones — wayfinding |
| **Moment cards > rows** | Game links, opponent, year, score; optional glyph |
| **Chart polish** | Thinner lines, fewer gridlines, consistent captions with sample size |
| **Rivalry chrome** | One section title; search tucked inside block |

---

### C6. After pass 2 (path to production)

1. Dagh reviews three mocks + prod `individual1.php?id=237` (four tabs).  
2. Note steals: layout, emphasis, chart pairing, moment card style.  
3. Write **C v1** — single reference order + chosen mock hybrid.  
4. Implement `individual1.php` in slices (CORE → zones → charts → drop legacy rows).  
5. **Achievement system** — separate session (P2); aspirational badges not blocking Profile v1.  
6. **Offline realm** photos + cross-realm link — separate track.

---

### C7. Part C summary

| Deliverable | Status |
|-------------|--------|
| Feast contract (C1) | Locked for pass 2 |
| Zones (C2) | Locked in spirit; flexible in DOM |
| Reference order v0 (C3) | Draft — revise after mocks |
| Mock A/B/C briefs (C4) | Locked emphasis; shared content |
| Production order (C v1) | **After** pass 2 review |

**Audit status:** Parts **A + B + C** complete for pass 2 mock generation.

---

*Implement pass 2 mocks against C1–C4; update C3 → C v1 after review.*
