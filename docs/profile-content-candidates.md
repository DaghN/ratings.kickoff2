# Profile content — candidate catalog (Jun 2026)

**Purpose:** Wide menu for Profile tab additions after the [narrative confirmation](player-profile-feast.md#narrative-model-jun-2026-confirmation).

**Complete build list:** **Profile content v1** § below (Dagh curated Jun 2026). **How to implement:** [`profile-build-playbook.md`](profile-build-playbook.md) — placement charter, module recipes, waves, acceptance checks.

**How to use:** Skim **P0** first. Mark keep / defer / kill per row. Respect **Do not propose** § at bottom (May audit DROPs).

**Legend**

| Col | Meaning |
|-----|---------|
| **Zone** | A identity · B celebrate · C understand |
| **Effort** | **R** read stored row · **Q** modest query · **A** new aggregate/API · **U** UX/link only |
| **Tone** | celebrate · neutral · shame (shame = demote or DROP) |
| **Form** | line · card · tile · strip · chart · link |

---

## Already shipped (reference — not candidates)

Hero · Presence · Career duo · Personal bests · Moments · Played days/weeks heatmaps · Rating / games-month / matchup charts · Hero milestones `{n}/{catalog}`.

---

## A — Identity & ladder context

| ID | Hook (visitor sees) | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|---------------------|----------|--------|--------|------|------|-----|-------------|
| A01 | “#22 of 312 rated players” | How elite? | `COUNT(display=1)` + rank query | Q | neutral | line | P1 | May audit kept out of CORE; subtitle only |
| A02 | “18 points behind #21” | Gap to next rank? | `playertable` next higher rating | Q | neutral | line | P2 | Competitive hook; optional |
| A03 | “353 below career peak (2279)” | Fall from peak? | `PeakRating − Rating` (in `$pm` as `peak_gap`) | R | neutral | line | P1 | `$pm` already has `peak_gap` |
| A04 | Participation sentence: “Steve · 5,940 rated games · 142 opponents · since 2017” | Who at a glance? | `$pm` + first game | R | celebrate | line | **P0** | N21; narrative glue |
| A05 | “On the rated ladder for 9 years” | Tenure? | `$pm` `years_on_ladder` / `tenure_label` | R | celebrate | line | P1 | Already computed in load |
| A06 | “Established Jun 2017” (20th game date) | When settled? | 20th row `ratedresults` by Date | Q | celebrate | line | P1 | N8; community vocab |
| A07 | “Game #5000 · Apr 2023” link | Volume milestone? | Nth game `ratedresults` id | Q | celebrate | link | P2 | N9; veterans only |
| A08 | “Crossed 2000 Elo · Mar 2019” | Rating threshold? | Chronological `NewRating*` scan | Q | celebrate | line | P2 | N10; pick 2–3 thresholds |
| A09 | “Top 8% of rated players” | Population context? | rank / display count | Q | neutral | line | P2 | Light percentile |
| A10 | Hero subtitle: W–D–L compact under games | Record at a glance? | `$pm` wins/draws/losses | R | neutral | line | P1 | Avoid second “rating” |
| A11 | `Profile_Bio` 1–3 lines under name | Who as a person? | `playertable.Profile_Bio` | U | neutral | line | P2 | N22; needs edit UI |
| A12 | Avatar photo instead of initial | Face? | `Profile_AvatarURL` | U | neutral | visual | P2 | N23; hosting trust |
| A13 | Provisional badge (&lt;20 games) | Not established yet? | `NumberGames` | R | neutral | line | P1 | Sparse player UX |
| A14 | “Not on rated ladder” copy | Display gate? | `Display ≠ 1` | R | neutral | line | P1 | Sparse UX |

---

## B — Presence & aliveness

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| B01 | Last seen online | Still around? | `LastLogin` | R | neutral | tile | — | **Shipped** (Presence) |
| B02 | Last rated game | Still playing? | `LastGame` | R | neutral | tile | — | **Shipped** |
| B03 | Games this month / year | Recent volume? | `player_period_games` | R | neutral | tile | — | **Shipped** |
| B04 | “Active today / this week” | Right now? | `player_period_games` day/week | R | neutral | line | P1 | Boolean cue |
| B05 | “47 days since last rated game” | Gone quiet? | `LastGame` delta | R | neutral | line | P2 | Absence copy deferred in audit |
| B06 | Current win streak (if ≥3) | Hot hand? | `WinningStreak` | R | celebrate | line | P1 | `$pm` has `winning_streak` |
| B07 | Play streak: “12-day run · best 87” | Calendar habit? | `player_play_streaks` day | R | celebrate | line | **P0** | Streaks LB owns detail |
| B08 | Play streak: “6-week run · best 126” | Weekly habit? | `player_play_streaks` week | R | celebrate | line | P1 | Same |
| B09 | Recent matches strip (8 rows: opp, score, ΔElo) | Form? | `ratedresults` DESC | Q | neutral | strip | P1 | N5; competes with Games tab — cap at 5 |
| B10 | Last 10: “7W–1D–2L” chips | Short form? | Recent rows | Q | neutral | strip | P2 | Audit deferred form metrics |
| B11 | Net goals last 10 (+12) | Scoring form? | Recent GF−GA | Q | neutral | line | P2 | N16 |
| B12 | “32 games this month — busiest since 2024” | Pace vs history? | month + past peaks | Q | celebrate | line | P2 | Needs compare logic |

---

## B — Career character (personality stats)

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| C01 | Rated games + rank | Volume actor? | `$pm` | R | celebrate | tile | — | **Shipped** (Career) |
| C02 | Wins + rank | Winner? | `$pm` | R | celebrate | tile | — | **Shipped** |
| C03 | Goals scored + rank | Attacker? | `$pm` | R | celebrate | tile | — | **Shipped** |
| C04 | Double digits + rank | Flair? | `$pm` | R | celebrate | tile | — | **Shipped** |
| C05 | Opponents faced + rank | Network? | `$pm` | R | celebrate | tile | — | **Shipped** |
| C06 | W–D–L strip: “3177 · 705 · 2058 (53.5%)” | Full record? | `$pm` | R | neutral | line | P1 | B1 COMPACT block |
| C07 | Goals for / against one line | Balance? | `$pm` GF/GA | R | neutral | line | P1 | In load, not shown |
| C08 | Goal ratio | Efficiency? | `$pm` `goal_ratio` | R | neutral | line | P2 | In load |
| C09 | Clean sheets count | Defender? | `$pm` `clean_sheets` | R | celebrate | line | P1 | In load |
| C10 | DD + CS snapshot one line | KO2 flavour? | DD, CS, ratios | R | celebrate | line | P1 | B1 DD/CS snapshot |
| C11 | Avg opponent rating | Strength of schedule? | `AverageOpponentRating` | R | neutral | line | P1 | B1 SCROLL |
| C12 | “142 opponents · 89 victims” | Spread? | DifferentOpponents / Victims | R | neutral | line | P2 | Table E trimmed |
| C13 | Draw rate callout if high | Draw merchant? | WinRatio + draws | R | celebrate | line | P2 | Character joke |
| C14 | Rank on Peak rating LB | Peak prestige? | `PeakRating` + ranked1 logic | Q | celebrate | line | P2 | Cross-wing |

---

## B — Moments & game-linked stories

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| M01 | Longest win streak card | Best run? | `LongestWinningStreak` | R | celebrate | card | — | **Shipped** |
| M02 | Biggest win / draw / goal festival / bonanza | Legendary games? | `*GameID` trophies | R | celebrate | card | — | **Shipped** (4 cards) |
| M03 | Max rated victim game | Beat a giant? | `HighestRatedVictimGameID` | R | celebrate | card | **P0** | B1 SCROLL; not in moments yet |
| M04 | Least goals conceded game | Defensive masterclass? | `LeastGoalsConcededGameID` | R | celebrate | card | P1 | B1 SCROLL |
| M05 | Biggest draw (if not dup) | Epic stalemate? | already in trophies | — | — | — | — | Check dup with M02 |
| M06 | “11–0 · 2020” style second row on streak card | Streak context? | streak + linked game | Q | celebrate | card | P2 | Enrich existing |
| M07 | Giant-killing count + best game | Upsets? | wins vs opp pre-rating +100 | Q | celebrate | card | P1 | N13 |
| M08 | Favourite victim (most wins vs one opp) | Who do you beat? | `player_matchup_summary` | Q | celebrate | line | P2 | N14; min games |
| M09 | Featured rival W–D–L line before charts | Main nemesis? | top opponent + summary | R | neutral | line | **P0** | N4; chart selects #1 |
| M10 | Biggest loss game card | Honest low? | `BiggestLossGameID` | R | shame | card | P3 | B1 DEMOTE only |
| M11 | Most goals conceded game | Pain game? | `MostGoalsConcededGameID` | R | shame | card | DROP | B1 demote; skip |
| M12 | Filter link: “All games vs Lee” | Actionable rivalry? | Games tab param | U | neutral | link | P1 | N20; UX TBD |

---

## B — Personal peaks & activity story

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| P01 | Busiest day / month / year | When went off? | `player_peak_period_games` | R | celebrate | tile | — | **Shipped** |
| P02 | Best calendar year (most wins) | Which year was yours? | `player_period_league` or games by year | Q | celebrate | line | P1 | N12 |
| P03 | Best calendar year (most games) | Activity year? | `player_period_games` year | R | celebrate | line | P1 | |
| P04 | Busiest week ever | Short burst? | `player_peak_period_games` week | R | celebrate | line | P2 | Row exists in contract |
| P05 | “Played on 412 distinct days” | Lifetime presence? | COUNT days in `player_period_games` | Q | celebrate | line | P1 | Heatmap summary |
| P06 | Current calendar-year played days vs last year | Trend? | day rows YoY | Q | neutral | line | P2 | |

---

## B — Milestone snippets (not the garden)

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| MS01 | Latest unlock: “Centurion · Apr 2024” → link | What happened recently? | `player_milestones` MAX achieved_at | R | celebrate | line | **P0** | Garden tab owns full set |
| MS02 | Signature unlock: highest tier (holo) achieved | Rarest feat? | milestones ⋈ definitions tier | Q | celebrate | card | **P0** | One card max |
| MS03 | “47 / 112 milestones” + link to garden | Progress? | hero counts | R | neutral | line | — | **Partial** (hero only) |
| MS04 | Unlocks in last 12 months (count) | Recent journey? | achieved_at filter | Q | celebrate | line | P1 | |
| MS05 | Tier band counts: “12 green · 8 blue · 4 amber · 1 holo” | Shape of career? | GROUP BY tier | Q | neutral | line | P1 | Milestones meta feel |
| MS06 | Next tease: “2 wins from Marathoner (250 games)” | Near miss? | NumberGames vs catalog | Q | neutral | line | P2 | Can feel grindy |
| MS07 | Three latest unlocks mini-list | Recent story? | ORDER BY achieved_at LIMIT 3 | R | celebrate | strip | P1 | Not full story tab |
| MS08 | League-tied milestone card (e.g. `moment_of_glory`) | League + milestone cross? | `player_milestones` league source | R | celebrate | card | P1 | Links Status leagues |

---

## B — League snippets (new layer)

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| L01 | Latest medal: “Gold · Weekly Points · May 2026” | Recent glory? | `player_league_award` MAX period_end | R | celebrate | line | **P0** | Link league honours |
| L02 | Career podium: “12🥇 · 8🥈 · 5🥉” | League career? | `player_league_totals` | R | celebrate | line | **P0** | |
| L03 | Strongest slice: “7 golds in Monthly Activity” | Where they win? | `player_league_slice_totals` MAX gold | R | celebrate | line | P1 | `k2_league_player_slice_totals()` |
| L04 | League wins count (any of 8) | Champion habit? | `player_league_totals.wins` | R | celebrate | line | P1 | Overlaps milestone keys |
| L05 | Best finish this period (if ranked top 3 live) | Current hunt? | open period standings | Q | neutral | line | P2 | Status owns live; teaser only |
| L06 | First league gold date | When broke through? | MIN period_end WHERE gold | Q | celebrate | line | P2 | |
| L07 | Dual line: latest medal + career totals | Combined beat | L01+L02 | R | celebrate | tile | P1 | One module |
| L08 | Link: “League honours →” | Depth? | wing URL + player filter | U | neutral | link | P1 | |

---

## C — Heatmaps & activity texture

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| H01 | Played days year picker grid | Daily habit? | calendar API | R | celebrate | visual | — | **Shipped** |
| H02 | Played weeks grid | Weekly habit? | weeks API | R | celebrate | visual | — | **Shipped** |
| H03 | Heatmap summary under grid | Count this year? | status text | R | neutral | line | — | **Shipped** |
| H04 | Longest played-day run in selected year | Year streak? | day rows consecutive | Q | celebrate | line | P2 | |
| H05 | Milestone cross-mark on heatmap day | Unlock on this day? | milestones achieved_at date | Q | celebrate | overlay | P3 | Future overlay |
| H06 | Compare played days YoY same month | Seasonal? | period_games | Q | neutral | line | P3 | |

---

## C — Charts & analyst depth

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| G01 | Rating over time + peak line | Career arc? | rating API | R | neutral | chart | — | **Shipped** |
| G02 | Games per month | Activity rhythm? | month API | R | neutral | chart | — | **Shipped** |
| G03 | Top opponents → H2H / compare | Rivalry depth? | matchup APIs | R | neutral | chart | — | **Shipped** |
| G04 | Opponent search after charts | Rare matchup? | search API | U | neutral | control | — | **Shipped** |
| G05 | Goals per month chart | Scoring rhythm? | new aggregate | A | neutral | chart | P2 | N18 |
| G06 | Rolling 50-game win % | Form over time? | window SQL | A | neutral | chart | P3 | N19; analyst |
| G07 | Rating sparkline in hero | Mini arc? | subset rating history | Q | neutral | visual | P2 | N7 |
| G08 | Win rate vs opp rating buckets | Performance vs strength? | API existed | A | neutral | chart | DROP | Removed Jun 2026 |
| G09 | Peak/nadir markers on rating chart | Full arc? | Peak/Lowest game IDs | Q | neutral | chart | P2 | Nadir shame — peak only OK |

---

## Cross-cutting & sparse players

| ID | Hook | Question | Source | Effort | Tone | Form | Pri | Dup / notes |
|----|------|----------|--------|--------|------|------|-----|-------------|
| X01 | Empty moments: “First trophy game awaits” | New player? | games count | U | celebrate | copy | P1 | Empty state |
| X02 | Thin charts message (&lt;5 games) | Too early? | games | U | neutral | copy | P1 | |
| X03 | Skip rivalry block if one opponent only | Sparse? | opponent count | U | neutral | rule | P1 | |
| X04 | League/milestone snippets hidden if 0 | Clean sparse? | counts | U | neutral | rule | P1 | |
| X05 | Deep link to Milestones garden | More depth? | tab URL | U | neutral | link | P1 | |
| X06 | Deep link to Streaks LB filtered? | If top streak? | ranked4 | U | celebrate | link | P2 | HoF cross |

---

## Do not propose (May audit DROP — unless tagged “reconsider”)

Rating nadir row · Recent avg rating · Current/biggest rating ascent/descent · Win rate vs opp rating chart · Least For / Smallest Sum games · Min rated culprit · Obscure victim/culprit count rows · Current losing/drawing streaks as headline · Duplicate CORE rows · Full per-opponent tables · Full milestone garden · Full league history table · Nemesis shame-first copy.

---

## Profile content v1 — Dagh curation (Jun 2026)

**Status:** Curated build list. **Not fully implemented** — follow playbook waves §7.

### Reject / no

**A:** A01, A02, A05, A06, A09, A10, A11, A12, A13, A14  
**B:** B04, B10, B11, B12  
**C:** C06–C11, C13  
**M:** M04, M05 (dup M02), M06 (unclear — skip until spec), M07  
**P:** P06  
**MS:** MS05, MS06, MS07  
**L:** L03, L05  
**H:** H06  
**G:** G05–G09 (keep shipped G01–G04 only)  
**X:** X02, X03  

### Defer

A03, A07, A08 · B05 · H04  

### Consider / maybe (needs design pass)

| ID | Dagh note |
|----|-----------|
| **A04** | Participation sentence — **competes with hero fold** (rank/rating/games already there); only if it adds without clutter |
| **B09** | Recent matches strip — answers “what is he doing on the server”; cap rows; vs Games tab |
| **M10** | Biggest loss card — only if tone right (hidden card, light touch) |
| **M11** | Most goals conceded — humorous angle (e.g. Fisher asleep GK gag) |
| **C14** | Peak Elo historic rank — **select few only**, e.g. “Reached 2134 — 7th highest ever” with cutoff |
| **L06** | First league gold — memorable moment card; may need DB/store facilitation |
| **H05** | Heatmap overlay options — milestone days and/or **DD days lit up** (needs stored per-day DD — heavier) |

### Keep — already shipped

B01–B03 · C01–C05 (see rank rethink) · M01–M02 · P01 · H01–H03 · G01–G04 · MS03 (hero count)

### Keep — build next (v1 scope)

| ID | Implementation note |
|----|---------------------|
| **B06** | Current win streak when meaningful (e.g. ≥3) |
| **B07 / B08** | Play streak — **pick one narrative per load or rotate:** current run vs historical best with date (“played 37 games in a row in …”); day vs week as alternatives |
| **C01–C05** | Keep tiles; **rethink `(#rank)` column** — keep only if cleaner visually |
| **C12** | “142 opponents · 89 victims” — promising; watch dup with opponents tile |
| **M03** | Max rated victim card — **celebrate non-elite upsets**; consider **show only below rating/rank cutoff** |
| **M08** | Favourite victim line — “His favourite victim is … — beaten X times” |
| **M09** | Featured rivalry W–D–L before matchup charts — try it |
| **M12** | Deep link to `individual3.php` with **opponent pre-filtered** |
| **P02** | Best year as **ticker** — e.g. “Won 135 games in 2023!” (wins; P03/P04 folded into P01 busiest) |
| **P05** | **Distinct days played** — key stat; **site-wide** (Profile + HoF + elsewhere); not just heatmap footnote |
| **MS01** | Latest unlock — **card**: “Unlocked *X* on *date*” → `milestone.php` |
| **MS02** | Holo unlock count (or amber count if no holo) — celebrate rarity |
| **MS04** | Unlocks in last 12 months — good for newcomers seeing activity |
| **MS08** | League-tied milestone card — subtle league emphasis |
| **L01** | Latest medal — with **bling** |
| **L02 / L07 / L08** | Career medal totals — aggregate gold (and podium) with presentation + honours deep link |
| **L04** | League wins count — key community-presence stat |
| **X01** | Positive empty states for moments |
| **X04** | **Conditional display rules** (and optional rotation) for snippets |
| **X05 / X06** | Deep links where they earn their place (garden, streaks LB, etc.) |

### Charts & heatmaps

- **Charts:** no new chart types; keep current stack.  
- **Heatmaps:** keep days/weeks/year picker; H03 status line probably keep; let viewers count streaks themselves (no H04).

### Cross-site / data follow-ups

| Item | Scope |
|------|--------|
| **P05** distinct days played | Precompute or cheap COUNT on `player_period_games` day rows; expose on Profile + **Hall of Fame** (not designed yet) |
| **L06** first gold moment | May need stored “first award” or query on `player_league_award` |
| **H05** DD-day heatmap | New stored truth or expensive rebuild — defer unless facilitated |
| **M12** Games tab opponent filter | `individual3.php` query param + filter UI |

---

## Curation worksheet (archived — filled Jun 2026)

See **Profile content v1** § above. Supersedes agent P0 shortlist.

---

*Generated Jun 2026. Curation recorded Jun 2026 (Dagh).*
