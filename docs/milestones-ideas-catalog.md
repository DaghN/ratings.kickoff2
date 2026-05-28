# Milestone ideas catalog

**Kick Off 2 ratings site · May 2026**

**Project phase:** **Phase 1 (idea creation) complete** — this file is a **first brainstorm + pass 1 curation**, **not** a finalized or signed-off catalog. See [`milestones-project.md`](milestones-project.md).

Brainstorm list with `want` / `maybe` / `discard` from pass 1. Items not mentioned in pass 1 are **discard** (kept in tables for reference, with notes where useful).

**Related:** [`milestones-project.md`](milestones-project.md) · [`milestones-product-spec.md`](milestones-product-spec.md) (tier-band **plan**) · [`milestones-want-maybe-by-theme.md`](milestones-want-maybe-by-theme.md) (**want/maybe grouped for Phase 2 tier pass**) · [`milestones-system-discussion.md`](milestones-system-discussion.md)

---

## Pass 1 curation rules

| Rule | Meaning |
|------|---------|
| **want** | In scope for the milestone system |
| **maybe** | Likely in scope; name or rule still open |
| **discard** | Out of scope for now — **silence = discard** |
| **tbd** | Section held open (e.g. future match data) |

Renames and rule changes from pass 1 are applied in the tables below. **Display names marked *(name TBD)*** still need copy.

---

## Product direction (pass 1)

These sit alongside the item list — they shape how milestones integrate into the site.

### Naming & tiers

- **Milestones** = the general system and long-tail unlocks.
- **Tier bands (plan):** four bands — Aspirational / Veteran / Key / Legendary — see [`milestones-product-spec.md`](milestones-product-spec.md). Pass 2 should add `tier_band` per row.
- **Key milestones** = amber band, **~15–20** (not 10) — completeness palette + achiever lists (same set). Chosen from want pool in Phase 2.

### Surfaces

| Surface | Direction |
|---------|-----------|
| **Own hub tab** | Milestones likely **big enough for a dedicated tab**, not only an Activity corner. Exact layout TBD — lots of data (lists, counts, leagues, feats). |
| **Profile** | **Milestone count** shown prominently; full unlock list/history TBD with profile rethink. |
| **Leaderboard** | **Most milestones** meta-leaderboard — want (Key-only vs all-count still open). |
| **Hall of Fame** | Unchanged — server **records**, not personal first-times. |

### Leagues & healthy competition

- Period leagues (activity + points, day/week/month/year) are **wanted in the milestone system** even though the site avoids pure “rank obsession” elsewhere — e.g. **most games in a year** is worth celebrating.
- **16 league milestones (2×8):** for each of **8 league contexts** (daily / weekly / monthly / yearly × **points** and **activity**), track both **medal** (podium — rule TBD, likely top 3) and **winner** (#1). Unique display names per league TBD (not necessarily generic “Period champion”).
- **Overlap OK** for now between generic league wins (Period champion, Moment of glory, etc.) and the 2×8 set — consolidate later.
- **Career league win totals:** milestones at **10 / 50 / 100 / 500** league wins (any league type — rule TBD); humorous names TBD (*getting the hang of it*, *tasting blood*, …).

### Active player definition (pass 1)

For **Elite customer** and **Giant slayer:** **active** = rated at least one game within the **last calendar year** (UTC rule to match ladder).

---

## Pass 1 summary

| Status | Approx. count |
|--------|----------------|
| **want** | ~115+ (incl. 16 league medal/winner + 4 league-win totals + new additions) |
| **maybe** | 4 |
| **discard** | remainder of original brainstorm |
| **tbd** | §XVI future data |

---

## Legend

| Column | Meaning |
|--------|---------|
| **Curate** | `want` · `maybe` · `discard` · `tbd` |
| **Tier** | Key · Featured · Feat · Aspiration (design hint only until Key 10 chosen) |
| **Data** | ✅ now · 🔶 rule + rebuild · 🔴 not stored yet |

---

## I. Welcome & belonging

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `debut` | **Debut** | First rated game | ✅ | |
| maybe | `entered_arena` | **Entered the arena** | First lobby presence | 🔴 | Needs presence logs |
| discard | `signed_up` | Signed up | JoinDate | 🔶 | Pass 1: not mentioned |
| discard | `back_in_the_game` | **Back in the game** | Return after ≥1 year idle | 🔶 | Pass 2: discard |
| discard | `long_sleep_loud_wakeup` | **Long sleep, loud wake-up** | Return after ≥3 years idle | 🔶 | Pass 2: discard — legendary only via not playing is unfair |
| discard | `still_here_years_later` | **Still here years later** | Played in year N and N+5 | 🔶 | Pass 2: cut from curated set |
| discard | `early_adopter_opponent` | Early adopter opponent | — | 🔶 | Pass 1: not mentioned |
| discard | `ten_years_on_ladder` | Ten years on the ladder | — | 🔶 | Pass 1: not mentioned |
| discard | `founding_era` | Founding era | June 2017 window | ✅ | Pass 1: not mentioned |
| discard | `witnessed_server_game_n` | Witnessed server milestone game | — | 🔶 | Pass 1: not mentioned |

---

## II. Volume & career depth

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `debut` | **Debut** | (= first rated game) | ✅ | Same as §I |
| maybe | `persistence` | **Persistence** *(name TBD)* | Early survival band — e.g. 5–10 rated games; celebrates sticking through rough start | ✅ | Was “Getting started / Regular”; rename open |
| **want** | `established_20` | **Established** | 20 rated games | ✅ | **In DB** |
| discard | `statistically_relevant_30` | Statistically relevant | 30 games | ✅ | Pass 1: not mentioned |
| **want** | `half_century_50` | **Half century** | 50 rated games | ✅ | |
| **want** | `centurion_100` | **Centurion** | 100 rated games | ✅ | |
| discard | `double_century_200` | Double century | 200 games | ✅ | Pass 1: not mentioned |
| maybe | `marathoner_250` | **Marathoner** | 250 rated games | ✅ | |
| **want** | `club_500` | **500 club** | 500 rated games | ✅ | |
| discard | `iron_calendar_750` | Iron calendar | 750 games | ✅ | Pass 1: not mentioned |
| **want** | `millennium_merchant_1000` | **Millennium merchant** | 1,000 rated games | ✅ | |
| discard | `club_5000` | *(name TBD)* | 5,000 rated games | 🔶 | Pass 2: superseded by `club_10000` legendary |
| **want** | `club_10000` | *(name TBD)* | 10,000 rated games | 🔶 | Pass 2: legendary volume target |
| discard | `legend_volume_2000` | Legend volume | 2,000 games | 🔶 | Pass 1: not mentioned |
| **want** | `first_victory` | **First victory** | First win | ✅ | |
| **want** | `ten_wins` | **Ten wins** | 10 career wins | ✅ | |
| discard | `fifty_wins` | Fifty wins | 50 wins | ✅ | Pass 1: not mentioned |
| **want** | `century_of_wins` | **Century of wins** | 100 career wins | ✅ | |
| **want** | `first_handshake` | **First handshake** | First draw | ✅ | |
| **want** | `welcome_to_the_ladder` | **Welcome to the ladder** | First loss | ✅ | |
| **want** | `battle_scarred` | **Battle-scarred** | 100 career losses | ✅ | |
| **want** | `hundred_goals` | *(name TBD — e.g. Century scorer)* | 100 career goals | ✅ | Pass 1: 500 goals → **100 goals** |
| discard | `five_hundred_goals` | 500 goals | 500 goals | ✅ | Superseded by 100-goals choice |
| **want** | `thousand_goal_club` | **Thousand-goal club** | 1,000 career goals | ✅ | |

---

## III. Calendar, rhythm & activity bursts

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `daily_habit` | **Daily habit** | Played **every calendar day** Mon–Sun in one **Monday-start week** | 🔶 | Renamed from “Weekly habit”; was 7 distinct days |
| **want** | `monthly_regular` | **Monthly regular** | Rated game on **every calendar day** of at least one month (28–31 days per that month’s length) | 🔶 | Pass 1 tweak: was 30 distinct days; now full-month daily presence |
| **want** | `year_round` | **Year-round** | Rated game in **12 consecutive calendar months** | 🔶 | Pass 1: consecutive required |
| **want** | `weekly_regular` | **Weekly regular** | At least one rated game **every week for 3 consecutive months** | 🔶 | Pass 1 addition |
| **want** | `hot_day` | **Hot day** | 5 rated games in one UTC day | 🔶 | |
| **want** | `marathon_day` | **Marathon day** | 10 rated games in one UTC day | 🔶 | |
| **want** | `absurd_day` | **Absurd day** | 20 rated games in one UTC day | 🔶 | |
| **want** | `ultra_day_30` | *(name TBD)* | 30 rated games in one UTC day | 🔶 | Pass 2: legendary (name TBD) |
| discard | `busy_month_30` | Busy month | 30 games in month | 🔶 | Pass 1: not mentioned |
| **want** | `grind_month` | **Grind month** | 50 rated games in one calendar month | 🔶 | |
| discard | `weekly_league_participant` | Weekly league participant | — | 🔶 | Pass 1: not mentioned |
| **want** | `period_champion` | **Period champion** | Won a period league (generic — points or activity) | 🔶 | Overlaps 2×8 set; OK for now |
| **want** | `moment_of_glory` | **Moment of glory** | Won **daily points** league | 🔶 | Pass 1 addition |
| discard | `podium_month` | **Podium month** | Top 3 in **monthly** league (points or activity — pick one or both TBD) | 🔶 | Pass 2: cut from curated set |
| **want** | `activity_king` | **Activity king** | Won **monthly activity** league | 🔶 | Pass 1 addition |
| discard | `no_year_off` | No year off | 12 consecutive months | 🔶 | Overlaps **Year-round** |
| discard | `two_year_presence` | Two-year presence | 24 months | 🔶 | Pass 1: not mentioned |
| discard | `birthday_kickoff` | Birthday kickoff | — | 🔶 | Pass 1: not mentioned |

---

## IIIb. Period leagues — medal & winner (2×8)

*Pass 1: **want** all 16. Unique display names TBD per row (not generic labels in UI).*

**Medal** = podium finish (exact place rule TBD — likely top 3). **Winner** = #1 for that period.

| Curate | Key (internal) | Display name | League context | Data |
|--------|----------------|--------------|----------------|------|
| **want** | `league_daily_points_medal` | *(unique name TBD)* | Daily · points · medal | 🔶 |
| discard | `league_daily_points_winner` | *(unique name TBD)* | Daily · points · winner | 🔶 | Pass 2: dup `moment_of_glory` |
| **want** | `league_weekly_points_medal` | *(unique name TBD)* | Weekly · points · medal | 🔶 |
| **want** | `league_weekly_points_winner` | *(unique name TBD)* | Weekly · points · winner | 🔶 |
| **want** | `league_monthly_points_medal` | *(unique name TBD)* | Monthly · points · medal | 🔶 |
| **want** | `league_monthly_points_winner` | *(unique name TBD)* | Monthly · points · winner | 🔶 | Pass 2: **accomplished** (demoted from legendary) |
| **want** | `league_yearly_points_medal` | *(unique name TBD)* | Yearly · points · medal | 🔶 | Pass 2: accomplished |
| **want** | `league_yearly_points_winner` | *(unique name TBD)* | Yearly · points · winner | 🔶 |
| **want** | `league_daily_activity_medal` | *(unique name TBD)* | Daily · activity · medal | 🔶 |
| **want** | `league_daily_activity_winner` | *(unique name TBD)* | Daily · activity · winner | 🔶 |
| **want** | `league_weekly_activity_medal` | *(unique name TBD)* | Weekly · activity · medal | 🔶 |
| **want** | `league_weekly_activity_winner` | *(unique name TBD)* | Weekly · activity · winner | 🔶 |
| **want** | `league_monthly_activity_medal` | *(unique name TBD)* | Monthly · activity · medal | 🔶 |
| discard | `league_monthly_activity_winner` | *(unique name TBD)* | Monthly · activity · winner | 🔶 | Pass 2: cut (Activity king covers monthly activity win) |
| **want** | `league_yearly_activity_medal` | *(unique name TBD)* | Yearly · activity · medal | 🔶 |
| **want** | `league_yearly_activity_winner` | *(unique name TBD)* | Yearly · activity · winner | 🔶 |

---

## IIIc. Career league win totals

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `league_wins_10` | *(humorous name TBD)* | 10 career league wins (#1 in any of 8 leagues) | 🔶 | Rule locked — [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| **want** | `league_wins_50` | *(humorous name TBD)* | 50 league wins (same) | 🔶 | |
| **want** | `league_wins_100` | *(humorous name TBD)* | 100 league wins (same) | 🔶 | |
| **want** | `league_wins_500` | *(humorous name TBD)* | 500 league wins (same) | 🔶 | |

---

## IV. Scoring & attack

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `first_goal` | **First goal** | First career goal | ✅ | |
| **want** | `brace` | **Brace** | 2+ goals in one game | ✅ | |
| **want** | `hat_trick` | **Hat-trick** | 3+ goals in one game | ✅ | |
| **want** | `five_goal_frenzy` | **Five-goal frenzy** | 5+ in one game | ✅ | |
| **want** | `eight_goal_storm` | **Eight-goal storm** | 8+ in one game | ✅ | |
| **want** | `dd_merchant_10` | **Double Digit Merchant** | 10+ goals in one game | ✅ | **In DB** |
| **want** | `dozen_dash` | **Dozen dash** | 12+ in one game | ✅ | |
| **want** | `filthy_fifteen` | **Filthy fifteen** | 15+ in one game | ✅ | Renamed from Fifteen fever |
| discard | `scoreboard_breaker_20` | Scoreboard breaker | 20+ goals | ✅ | Pass 1: not mentioned |
| discard | `repeat_merchant_5` | Repeat merchant | 5 career DDs | 🔶 | Pass 1: not mentioned |
| discard | `wholesale_merchant_10` | Wholesale merchant | 10 career DDs | 🔶 | Pass 1: not mentioned |
| **want** | `on_the_scoresheet` | **On the scoresheet** | Scored in 10 consecutive games | 🔶 | |
| **want** | `rare_blank` | **Rare blank** | 0 goals in a game after 50+ career games | ✅ | |
| discard | `personal_explosion` | Personal explosion | — | 🔶 | Pass 1: not mentioned |

---

## V. Defence & clean sheets

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `first_shutout` | **First shutout** | First clean sheet | ✅ | |
| discard | `five_clean_sheets` | Five clean sheets | — | ✅ | Pass 1: not mentioned |
| discard | `ten_clean_sheets` | Ten clean sheets | — | ✅ | Pass 1: not mentioned |
| **want** | `fortress_builder` | **Fortress builder** | 25 career clean sheets | ✅ | |
| **want** | `clean_sheet_merchant` | **Clean sheet artist** | 50 career clean sheets | 🔶 | Internal key unchanged |
| **want** | `minimalist` | **Minimalist** | Won 1–0 | ✅ | Renamed from Minimalist win |
| **want** | `perfect_storm` | **Perfect storm** | Won 10–0 | ✅ | |
| **want** | `battle_hardened` | **Battle hardened** | Draw with score **≥5–5** | ✅ | Pass 1 addition |
| **want** | `survivor` | *(name TBD — Survivor / Last man standing)* | **Won** after opponent scored **7+** | ✅ | Pass 1 addition |
| discard | `merchant_victim` | Merchant victim | Conceded 10+ first time | ✅ | Pass 1: not mentioned |
| discard | `wall_streak` | Wall streak | — | 🔶 | Pass 1: not mentioned |
| discard | `nil_nil_diplomat` | Nil-nil diplomat | 0–0 draw | ✅ | Pass 1: not mentioned |

---

## VI. Double-digit culture

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `dd_merchant_10` | **Double Digit Merchant** | (= 10+ scored) | ✅ | Merge **Merchant licence** into this name |
| **want** | `victim_of_commerce` | **Victim of commerce** | First time conceded 10+ | ✅ | |
| **want** | `merchant_trade_fair` | **Merchant trade fair** | Draw **10–10** (replaces separate DD-draw milestone) | ✅ | Pass 2: merged `double_digit_handshake` |
| discard | `double_digit_handshake` | **Double-digit handshake** | DD in a draw | ✅ | Pass 2: merged into **Merchant trade fair** (10–10) |
| **want** | `leaky_merchant` | **Leaky merchant** | Won with 10+ scored and 9 conceded | ✅ | |
| discard | `three_customers` | Three customers | 3 DD victims | 🔶 | Pass 1: not mentioned |
| **want** | `travelling_salesman` | **Travelling salesman** | DD’d 10 different opponents | 🔶 | |
| discard | `elite_customer` | **Elite customer** | DD vs **highest-rated active** opponent | 🔶 | Pass 2: cut from curated set |

---

## VII. Draw culture

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `first_handshake` | **First handshake** | First draw | ✅ | Duplicate label with first draw — one milestone |
| **want** | `ten_draws` | **Ten draws** | 10 career draws | ✅ | |
| **want** | `six_goal_draw` | **Six-goal draw** | Draw, 6+ total goals | ✅ | |
| discard | `ten_goal_thriller_draw` | Ten-goal thriller draw | 10+ total | ✅ | Superseded by Goal fest threshold |
| **want** | `goal_fest_draw` | **Goal fest draw** | Draw, **14+ total goals** (e.g. 7–7) | ✅ | Was 15+; pass 1 → **14+** |
| **want** | `peace_streak` | **Peace streak** | 3 draws in a row | 🔶 | |
| **want** | `united_nations` | **United Nations** | 5 draws in a row | 🔶 | |
| discard | `diplomats_start` | Diplomat’s start | — | 🔶 | Pass 1: not mentioned |
| discard | `personal_stalemate_epic` | Personal stalemate epic | — | 🔶 | Pass 1: not mentioned |

---

## VIII. Margins, chaos & single-game extremes

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `comfortable` | **Comfortable** | Won by 5+ goal margin | ✅ | |
| **want** | `ruthless` | **Ruthless** | Won by 10+ goal margin | ✅ | Pass 2: accomplished |
| **want** | `hard_lesson` | **Hard lesson** | **Lost by 10+ margin** | ✅ | Pass 1: name kept; rule **not** −50 Elo |
| discard | `shipped_ten` | Shipped ten | 10+ loss margin | ✅ | Merged into **Hard lesson** |
| **want** | `twenty_goal_chaos` | **Twenty-goal chaos** | 20+ total goals in game | ✅ | |
| discard | `absurd_total_30` | Absurd total | 30+ total | ✅ | Pass 1: not mentioned |
| **want** | `knife_edge` | **Knife-edge** | **5 consecutive 1-margin wins** | 🔶 | Pass 1: rule changed from single 1–0 |
| **want** | `unlucky` | **Unlucky** | **5 consecutive 1-margin losses** | 🔶 | Pass 1 addition |
| discard | `record_margin` | Record margin | — | 🔶 | Pass 1: not mentioned |
| discard | `personal_bonanza` | Personal bonanza | — | 🔶 | Pass 1: not mentioned |

---

## IX. Elo & rating journey

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `club_1700` | **1700 club** | First time post-game rating ≥1700 | 🔶 | |
| **want** | `club_1800` | **1800 club** | ≥1800 | 🔶 | |
| **want** | `club_1900` | **1900 club** | ≥1900 | 🔶 | Pass 2: not accomplished — dedicated? |
| **want** | `club_2000` | **2000 club** | ≥2000 | 🔶 | |
| **want** | `elite_altitude` | **Elite altitude** | ≥2100 | 🔶 | Not in accomplished pick set (dedicated band) |
| **want** | `club_2300` | *(name TBD)* | Peak rating ≥2300 | 🔶 | Pass 2: legendary (name TBD) |
| discard | `new_peak` | New peak | — | 🔶 | Pass 1: not mentioned |
| discard | `two_thousand_peak` | Two-thousand peak | — | 🔶 | Pass 1: not mentioned |
| discard | `big_jump_50` | Big jump | +50 Elo one game | ✅ | Pass 1: not mentioned |
| discard | `rocket_game_80` | Rocket game | +80 Elo | ✅ | Pass 1: not mentioned |
| discard | `hard_lesson_elo` | Hard lesson (Elo) | −50 Elo | ✅ | **Hard lesson** reused for 10+ loss margin |
| discard | `near_peak_form` | Near peak form | — | 🔶 | Pass 1: not mentioned |
| discard | `recovery_arc` | Recovery arc | — | 🔶 | Pass 1: not mentioned |
| discard | `sustained_elite` | Sustained elite | — | 🔴 | Pass 1: not mentioned |

---

## X. Upsets, giant-killings & rivals

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| discard | `giant_killing_100` | Giant killing | Beat +100 higher | ✅ | Superseded by Giant slayer / Massive upset |
| **want** | `massive_upset` | **Massive upset** | Beat opponent rated **500+** higher (pre-game) | ✅ | Was 200+ |
| discard | `earthquake_300` | Earthquake | +300 upset | ✅ | Pass 1: not mentioned |
| discard | `bad_day_office` | Bad day at the office | Lost to +100 lower | ✅ | Pass 1: not mentioned |
| discard | `breakthrough` | Breakthrough | — | 🔶 | Pass 1: not mentioned |
| **want** | `regular_customer` | **Regular customer** | 10 wins vs same opponent | 🔶 | |
| **want** | `bogeyman` | **Bogeyman** | 20 wins vs same opponent | 🔶 | |
| discard | `top_ten_sweep` | **Top-ten sweep** | Beat each of current top 10 (min 1 each) | 🔶 | Pass 2: unstable snapshot (top 10 moves without you playing) |
| **want** | `giant_slayer` | **Giant slayer** | Beat **#1 rated active** player | 🔶 | Active = game within last year |
| discard | `best_scalp` | Best scalp | — | 🔶 | Pass 1: not mentioned |
| discard | `embarrassment` | Embarrassment | — | 🔶 | Pass 1: not mentioned |
| discard | `nemesis` | **Nemesis** | Inflict **largest margin defeat ever** on an **Established** opponent | 🔶 | Pass 2: cut from curated set |

---

## XI. Streaks & runs

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `win_hat_trick` | **Win hat-trick** | 3 wins in a row | 🔶 | |
| discard | `five_alive` | Five alive | 5 wins | 🔶 | Pass 1: not mentioned |
| **want** | `ten_wins_straight` | **Ten wins straight** | 10 wins in a row | 🔶 | |
| **want** | `rampage` | **Rampage** | 15 wins in a row | 🔶 | Accomplished |
| **want** | `win_streak_30` | *(name TBD)* | 30 wins in a row | 🔶 | Pass 2: legendary |
| **want** | `cold_streak` | **Cold streak** | **5 losses** in a row | 🔶 | Was 3 losses |
| discard | `peace_run` | Peace run | 5 draws row | 🔶 | Use **United Nations** |
| discard | `unbeaten_ten` | Unbeaten ten | — | 🔶 | Pass 1: not mentioned |
| discard | `fortress_run` | Fortress run | — | 🔶 | Pass 1: not mentioned |
| **want** | `win_drought` | **Win drought** | 10 games without a win | 🔶 | |
| discard | `personal_best_streak` | Personal best streak | — | 🔶 | Pass 1: not mentioned |

---

## XII. Opponents & network

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| discard | `first_rival` | First rival | 1 opponent | ✅ | Pass 1: not mentioned |
| **want** | `ten_opponents` | **Ten opponents** | 10 unique opponents | ✅ | |
| **want** | `wide_net` | **Wide net** | 25 unique opponents | ✅ | |
| **want** | `fifty_faces` | **Fifty faces** | 50 unique opponents | ✅ | |
| **want** | `century_of_rivals` | **Century of rivals** | 100 unique opponents | ✅ | |
| discard | `community_glue_150` | Community glue | 150 opponents | ✅ | Pass 1: not mentioned |
| discard | `international_circuit` | International circuit | — | 🔶 | Pass 1: not mentioned |
| **want** | `ten_match_saga` | **Ten-match saga** | 10th rated game vs same opponent | 🔶 | |
| **want** | `lifetime_rivalry` | **Lifetime rivalry** | 50th rated game vs same opponent | 🔶 | |
| discard | `established_round_robin` | Established round-robin | — | 🔶 | Pass 1: not mentioned |

---

## XIII. Victims & culprits

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `five_victims` | **Five victims** | 5 distinct victims (wins) | ✅ | |
| **want** | `twenty_five_victims` | *(interesting name TBD)* | 25 distinct victims | ✅ | Renamed from bland “Twenty-five victims” |
| discard | `fifty_victims` | Fifty victims | 50 victims | ✅ | Pass 1: not mentioned |
| **want** | `ten_culprits` | *(funny tongue-in-cheek name TBD)* | 10 distinct culprits (losses) | ✅ | |
| **want** | `diversity_merchant` | **Diversity merchant** | DD’d 5 different opponents | 🔶 | Renamed from Merchant diversity |
| **want** | `clean_sheet_spread` | **Clean sheet spread** | CS vs 10 different opponents | 🔶 | |
| discard | `repeat_customer_dd` | Repeat customer | DD same victim twice | 🔶 | Pass 1: not mentioned |
| discard | `personal_vendetta_score` | Personal vendetta score | — | 🔶 | Pass 1: not mentioned |

---

## XIV. Scene, server & shared history

*Entire section **discard** pass 1 — not mentioned. Kept for possible pass 2.*

| Curate | Key (internal) | Display name | Notes |
|--------|----------------|--------------|-------|
| discard | `busiest_day_witness` | Busiest day witness | Pass 1: not mentioned |
| discard | `busiest_month_witness` | Busiest month witness | Pass 1: not mentioned |
| discard | `early_era` | Early era | Pass 1: not mentioned |
| discard | `first_merchant_global` | First merchant | Pass 1: not mentioned |
| discard | `first_established_global` | First established | Pass 1: not mentioned |
| discard | `established_centenary` | Established centenary | Pass 1: not mentioned |
| discard | `merchant_50th` | Merchant #50 | Pass 1: not mentioned |
| discard | `historic_game_n` | Historic game | Pass 1: not mentioned |

---

## XV. Obscure, humorous & feat tier

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| discard | `nine_eight_thriller` | **Nine-eight thriller** | Won 9–8 | ✅ | Pass 2: legendary cut |
| **want** | `merchant_denied` | **Merchant denied** | Lost 10–9 | ✅ | |
| discard | `eleven_nil` | Eleven-nil | — | ✅ | Pass 1: not mentioned |
| discard | `eleven_nil_victim` | Eleven-nil victim | — | ✅ | Pass 1: not mentioned |
| **want** | `perfect_day` | **Perfect day** | Won **all** games in UTC day, **min 5** games | 🔶 | Was min 3 |
| **want** | `nightmare_day` | **Nightmare day** | Lost all games in UTC day, **min 5** | 🔶 | Was min 3 |
| **want** | `merchant_streak` | **Merchant streak** | **5** consecutive games scoring 10+ | 🔶 | Pass 2: was 3 — harder legendary |
| **want** | `minimalist_merchant` | **Minimalist merchant** | 3 consecutive games with **exactly** 10 goals scored | 🔶 | Pass 1 addition |
| discard | `exact_ten_thrice` | Exact ten thrice | — | 🔶 | Overlaps Minimalist merchant |
| discard | `goals_gt_games_month` | Goals > games month | — | 🔶 | Pass 1: not mentioned |
| discard | `five_draws_start` | Five draws start | — | 🔶 | Pass 1: not mentioned |
| discard | `established_double` | Established double | — | 🔶 | Pass 1: not mentioned |
| discard | `zero_sum` | Zero sum | — | ✅ | Pass 1: not mentioned |
| discard | `mirror_match` | Mirror match | — | 🔶 | Pass 1: not mentioned |
| discard | `never_merchant` | Never merchant | — | 🔶 | Pass 1: not mentioned |
| discard | `never_shut_out` | Never shut out | — | 🔶 | Pass 1: not mentioned |

---

## XVb. Community & welcoming (pass 1 additions)

| Curate | Key (internal) | Display name | Rule | Data | Notes |
|--------|----------------|--------------|------|------|-------|
| **want** | `newbie_welcomer` | **Newbie welcomer** | First rated opponent to a **new arrival’s debut game** | 🔶 | Who welcomed the newbie |
| **want** | `generous` | **Generous** | In a **new arrival’s debut game**, opponent let them score **≥2** goals | 🔶 | Warmth / sportmanship |

---

## XVI. Future — richer match data or Amiga realm

**Pass 1: tbd** — no feedback yet; section unchanged for later.

| Curate | Key (internal) | Display name | Data |
|--------|----------------|--------------|------|
| tbd | `card_magnet` | Card magnet | 🔴 |
| tbd | `comeback_king` | Comeback king | 🔴 |
| tbd | `keeper_scorer` | Keeper scorer | 🔴 |
| tbd | `amiga_champion` | Amiga champion | 🔴 |
| tbd | `purist` | Purist | 🔴 |
| tbd | `amiga_centurion` | Amiga centurion | 🔴 |

---

## XVII. Integration (updated after pass 1)

| Topic | Direction |
|-------|-----------|
| **Hub tab** | Dedicated **Milestones** tab — scope TBD but treated as a major feature, not a small Activity widget |
| **Key milestones (~15–20)** | Amber band; pick from **want** pool — achiever lists + garden (see product spec) |
| **Profile** | **Milestone count** prominent; layout open with profile rethink |
| **Leaderboard** | **Most milestones** leaderboard — want |
| **Overlap** | League milestones may duplicate until consolidated; OK for now |
| **Moments vs milestones** | Complementary — best game vs first-time threshold |

---

## XVIII. Open questions (pass 2+)

**Naming TBD from pass 1:** Persistence band, 5,000-game club, Survivor vs Last man standing, 25-victims name, ten-culprits funny name, 100-goals name, all 16 league unique names, league-win humorous quartet.

**Rules TBD:** What counts as one “league win” for 10/50/100/500 totals; podium vs winner edge cases; Key milestone final 10; Giant slayer / Elite customer when no active #1; Nemesis if Established never lost big.

**Product TBD:** Milestones tab IA; Key-only vs all-count on leaderboard; medal = top 3 confirmed?

**Discarded but interesting for pass 2:** Server witness milestones (§XIV), global order stats (50th merchant), founding era, comeback feats (§XVI).

---

*Pass 1 recorded May 2026. **Phase 1 (idea creation) closed** — list not finalized. Next phase: see [`milestones-project.md`](milestones-project.md).*
