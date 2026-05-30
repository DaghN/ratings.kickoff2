# Milestones — want / maybe grouped by theme

**Kick Off 2 ratings site · May 2026**

**Purpose:** Manual **tier-band** pass (Aspirational / Dedicated / Accomplished·Keystone / Legendary). **No tiers assigned here** — only thematic grouping so related milestones stay together.

**Source:** [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) (`want` + `maybe` only).

**Unlock counts (read-only probe):** **261** players with ≥1 rated game; **107** veterans (≥20 games) = design population on `ko2unity_db`; rated games **74870**; **2026-05-29 07:07 UTC**. No DB writes. Regenerate: `python scripts/oneoff/milestone_unlock_counts.py --write-doc`. Scratch: `data/scratch/milestone_unlock_counts.json`.

**Tier targets:** Aspirational (pitch) — rarity floor; Dedicated (chrome) — bulk mid ladder; **Accomplished** (amber) ~**15–20** keystones (~15–25 veterans each); **Legendary** (holo) ~**10–15** (flavor + ~3–14% veterans). Thresholds and catalog can be nudged if band counts are off.

**Band names (working):** Aspirational → **Dedicated** → **Accomplished** (Keystones) → Legendary (`pitch` / `chrome` / `amber` / `holo`).

---

## How to use this doc

1. Work **group by group** (not catalog section order).
2. For each row, note your `tier_band` in a scratch column or on the catalog.
3. Use **%vet** (vs **107** veterans) for tier design; **%≥1g** includes tryouts; **Band** is a probe hint only — flavor and tier caps override.
4. See **Duplicates & overlaps** before locking Keystone picks.
5. **Curated tiers (authoritative):** [`milestones-tier-curated.md`](milestones-tier-curated.md). Probe palettes below are for counts only.

**Counts (deduped):** **~111** keys in tables (`top_ten_sweep` discarded).

---

## Duplicates & overlaps (read first)

| Issue | Keys | Note |
|-------|------|------|
| Same milestone, two sections | `debut` | §I Welcome + §II Volume — **one** milestone |
| Same milestone, two sections | `first_handshake` | §II “first draw” + §VII Draw — **one** milestone |
| Same milestone, two sections | `dd_merchant_10` | §IV Scoring + §VI DD culture — **one** milestone |
| League overlap (OK for now) | `period_champion`, `moment_of_glory`, `activity_king` vs §IIIb 2×8 | See curated tier doc |
| Draw streak overlap | `peace_streak`, `united_nations` vs discard `peace_run` | Catalog already points to United Nations for long draw runs |
| Merchant naming | `dd_merchant_10` | Merchant licence merged into Double Digit Merchant |

---

## A. First steps & belonging

*Who you are when you arrive; first outcomes; community warmth.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `debut` | **Debut** | First rated game | ✅ | 261 | 100.0% | 244%+ | pitch | playertable |
| maybe | `persistence` | **Persistence** *(name TBD)* | ~5–10 rated games; early survival | ✅ | 134 | 51.3% | 125%+ | pitch | playertable NumberGames>=10 |
| want | `established_20` | **Established** | 20 rated games | ✅ **DB** | 107 | 41.0% | 100%+ | pitch | playertable |
| want | `first_victory` | **First victory** | First win | ✅ | 145 | 55.6% | 136%+ | pitch | playertable |
| want | `first_goal` | **First goal** | First career goal | ✅ | 226 | 86.6% | 211%+ | pitch | playertable |
| want | `first_handshake` | **First handshake** | First draw | ✅ | 146 | 55.9% | 136%+ | pitch | playertable |
| want | `welcome_to_the_ladder` | **Welcome to the ladder** | First loss | ✅ | 252 | 96.6% | 236%+ | pitch | playertable |
| want | `first_shutout` | **First shutout** | First clean sheet | ✅ | 127 | 48.7% | 119%+ | pitch | playertable |
| want | `newbie_welcomer` | **Newbie welcomer** | You were first rated opponent in someone’s debut | 🔶 | 75 | 28.7% | 70.1% | dedicated? | chronological debut opponent |
| want | `generous` | **Generous** | In a debut game, let newcomer score ≥2 | 🔶 | 43 | 16.5% | 40.2% | dedicated? | chronological debut opp, newbie scored 2+ |
| maybe | `entered_arena` | **Entered the arena** | First lobby presence | 🔴 presence logs | 261 | 100.0% | 244%+ | pitch | playertable JoinDate (register = enter lobby) |

---

## B. Returns & long absence

*Comeback narratives — idle gaps, multi-year return.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|

---

## C. Career volume — rated games played

*How much you have played (ladder tenure by game count).*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `half_century_50` | **Half century** | 50 rated games | ✅ | 83 | 31.8% | 77.6% | dedicated? | playertable |
| want | `centurion_100` | **Centurion** | 100 rated games | ✅ | 72 | 27.6% | 67.3% | dedicated? | playertable |
| maybe | `marathoner_250` | **Marathoner** | 250 rated games | ✅ | 56 | 21.5% | 52.3% | dedicated? | playertable |
| want | `club_500` | **500 club** | 500 rated games | ✅ | 44 | 16.9% | 41.1% | dedicated? | playertable |
| want | `millennium_merchant_1000` | **Millennium merchant** | 1,000 rated games | ✅ | 37 | 14.2% | 34.6% | dedicated? | playertable |
| want | `club_10000` | **10K** | 10,000 rated games | 🔶 | 1 | 0.4% | 0.9% | ultra-rare? | playertable |

---

## D. Career volume — wins, losses & draw counts

*Outcome totals across career (not single-game).*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `ten_wins` | **Ten wins** | 10 career wins | ✅ | 78 | 29.9% | 72.9% | dedicated? | playertable |
| want | `century_of_wins` | **Century of wins** | 100 career wins | ✅ | 51 | 19.5% | 47.7% | dedicated? | playertable |
| want | `battle_scarred` | **Battle-scarred** | 100 career losses | ✅ | 65 | 24.9% | 60.7% | dedicated? | playertable |
| want | `ten_draws` | **Ten draws** | 10 career draws | ✅ | 71 | 27.2% | 66.4% | dedicated? | playertable |

---

## E. Career volume — goals scored (lifetime)

*Career goal totals (distinct from single-game scoring feats).*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `hundred_goals` | *(name TBD — e.g. Century scorer)* | 100 career goals | ✅ | 81 | 31.0% | 75.7% | dedicated? | playertable |
| want | `thousand_goal_club` | **Thousand-goal club** | 1,000 career goals | ✅ | 49 | 18.8% | 45.8% | dedicated? | playertable |

---

## F. Calendar rhythm & sustained presence

*Showing up on a schedule — weeks, months, years.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `daily_habit` | **Daily habit** | Rated game every calendar day Mon–Sun in one Monday-start week | 🔶 | 47 | 18.0% | 43.9% | dedicated? | chronological Mon-Sun week |
| want | `weekly_regular` | **Weekly regular** | ≥1 rated game every week for 3 consecutive months | 🔶 | 47 | 18.0% | 43.9% | dedicated? | chronological ~13 weeks |
| want | `monthly_regular` | **Monthly regular** | Rated game on every calendar day of at least one month | 🔶 | 5 | 1.9% | 4.7% | legendary? | chronological full month days |
| want | `year_round` | **Year-round** | Rated game in 12 consecutive calendar months | 🔶 | 43 | 16.5% | 40.2% | dedicated? | chronological 12 consec months |

---

## G. Activity bursts — days & months

*Intensity spikes (many games in a day; busy month; all-win / all-loss days).*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `hot_day` | **Hot day** | 5 rated games in one UTC day | 🔶 | 134 | 51.3% | 125%+ | pitch | player_period_games day max |
| want | `marathon_day` | **Marathon day** | 10 rated games in one UTC day | 🔶 | 79 | 30.3% | 73.8% | dedicated? | player_period_games day max |
| want | `absurd_day` | **Absurd day** | 20 rated games in one UTC day | 🔶 | 26 | 10.0% | 24.3% | accomplished? | player_period_games day max |
| want | `ultra_day_30` | *(name TBD)* | 30 rated games in one UTC day | 🔶 | 5 | 1.9% | 4.7% | legendary? | player_period_games day max |
| want | `grind_month` | **Grind month** | 50 rated games in one calendar month | 🔶 | 67 | 25.7% | 62.6% | dedicated? | player_period_games month max |
| want | `perfect_day` | **Perfect day** | Won all games in UTC day (min 5) | 🔶 | 36 | 13.8% | 33.6% | dedicated? | chronological UTC day |
| want | `nightmare_day` | **Nightmare day** | Lost all games in UTC day (min 5) | 🔶 | 77 | 29.5% | 72.0% | dedicated? | chronological UTC day |

---

## H. Period leagues — generic & spotlight

*League achievements outside the 2×8 matrix (may consolidate later).*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `moment_of_glory` | **Moment of glory** | Won daily **points** league | 🔶 | 62 | 23.8% | 57.9% | dedicated? | player_league_award (daily points win) |
| want | `activity_king` | **Activity king** | Won monthly **activity** league | 🔶 | 17 | 6.5% | 15.9% | accomplished? | player_league_award (monthly activity win) |

---

## I. Period leagues — 2×8 matrix (medal & winner)

*For each league context: **medal** (podium, likely top 3) and **winner** (#1). Display names all TBD.*

### I.1 Daily

| Curate | Key | Context | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|---------|------| |--------:|--------:|--------:|--------|--------|
| want | `league_daily_points_medal` | Daily · points · medal | 🔶 | 94 | 36.0% | 87.9% | pitch | player_league_award (league_kind='points' AND period_type='day' AND finish_rank<=3) |
| want | `league_daily_activity_medal` | Daily · activity · medal | 🔶 | 114 | 43.7% | 107%+ | pitch | player_league_award (league_kind='activity' AND period_type='day' AND finish_rank<=3) |
| want | `league_daily_activity_winner` | Daily · activity · winner | 🔶 | 84 | 32.2% | 78.5% | dedicated? | player_league_award (league_kind='activity' AND period_type='day' AND is_winner=1) |

### I.2 Weekly

| Curate | Key | Context | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|---------|------| |--------:|--------:|--------:|--------|--------|
| want | `league_weekly_points_medal` | Weekly · points · medal | 🔶 | 44 | 16.9% | 41.1% | dedicated? | player_league_award (league_kind='points' AND period_type='week' AND finish_rank<=3) |
| want | `league_weekly_points_winner` | Weekly · points · winner | 🔶 | 24 | 9.2% | 22.4% | accomplished? | player_league_award (league_kind='points' AND period_type='week' AND is_winner=1) |
| want | `league_weekly_activity_medal` | Weekly · activity · medal | 🔶 | 54 | 20.7% | 50.5% | dedicated? | player_league_award (league_kind='activity' AND period_type='week' AND finish_rank<=3) |
| want | `league_weekly_activity_winner` | Weekly · activity · winner | 🔶 | 33 | 12.6% | 30.8% | accomplished? | player_league_award (league_kind='activity' AND period_type='week' AND is_winner=1) |

### I.3 Monthly

| Curate | Key | Context | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|---------|------| |--------:|--------:|--------:|--------|--------|
| want | `league_monthly_points_medal` | Monthly · points · medal | 🔶 | 27 | 10.3% | 25.2% | accomplished? | player_league_award (league_kind='points' AND period_type='month' AND finish_rank<=3) |
| want | `league_monthly_points_winner` | Monthly · points · winner | 🔶 | 13 | 5.0% | 12.1% | legendary? | player_league_award (league_kind='points' AND period_type='month' AND is_winner=1) |
| want | `league_monthly_activity_medal` | Monthly · activity · medal | 🔶 | 35 | 13.4% | 32.7% | dedicated? | player_league_award (league_kind='activity' AND period_type='month' AND finish_rank<=3) |

### I.4 Yearly

| Curate | Key | Context | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|---------|------| |--------:|--------:|--------:|--------|--------|
| want | `league_yearly_points_medal` | Yearly · points · medal | 🔶 | 12 | 4.6% | 11.2% | legendary? | player_league_award (league_kind='points' AND period_type='year' AND finish_rank<=3) |
| want | `league_yearly_points_winner` | Yearly · points · winner | 🔶 | 5 | 1.9% | 4.7% | legendary? | player_league_award (league_kind='points' AND period_type='year' AND is_winner=1) |
| want | `league_yearly_activity_medal` | Yearly · activity · medal | 🔶 | 15 | 5.7% | 14.0% | accomplished? | player_league_award (league_kind='activity' AND period_type='year' AND finish_rank<=3) |
| want | `league_yearly_activity_winner` | Yearly · activity · winner | 🔶 | 5 | 1.9% | 4.7% | legendary? | player_league_award (league_kind='activity' AND period_type='year' AND is_winner=1) |

---

## J. Period leagues — career win totals

*Cumulative #1 finishes across any of the 8 league types (rules in leagues spec). Humorous names TBD.*

| Curate | Key | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `league_wins_10` | 10 career league wins | 🔶 | 42 | 16.1% | 39.3% | dedicated? | player_league_totals.wins>=10 |
| want | `league_wins_50` | 50 league wins | 🔶 | 23 | 8.8% | 21.5% | accomplished? | player_league_totals.wins>=50 |
| want | `league_wins_100` | 100 league wins | 🔶 | 15 | 5.7% | 14.0% | accomplished? | player_league_totals.wins>=100 |
| want | `league_wins_500` | 500 league wins | 🔶 | 4 | 1.5% | 3.7% | legendary? | player_league_totals.wins>=500 |

---

## K. Single-game attack & scoring

*Goals in one match; scoring streaks; odd blanks.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `brace` | **Brace** | 2+ goals in one game | ✅ | 183 | 70.1% | 171%+ | pitch | ratedresults any game |
| want | `hat_trick` | **Hat-trick** | 3+ in one game | ✅ | 156 | 59.8% | 146%+ | pitch | ratedresults any game |
| want | `five_goal_frenzy` | **Five-goal frenzy** | 5+ in one game | ✅ | 102 | 39.1% | 95.3% | pitch | ratedresults any game |
| want | `eight_goal_storm` | **Eight-goal storm** | 8+ in one game | ✅ | 64 | 24.5% | 59.8% | dedicated? | ratedresults any game |
| want | `dd_merchant_10` | **Double Digit Merchant** | 10+ in one game | ✅ **DB** | 44 | 16.9% | 41.1% | dedicated? | ratedresults any game |
| want | `dozen_dash` | **Dozen dash** | 12+ in one game | ✅ | 31 | 11.9% | 29.0% | accomplished? | ratedresults any game |
| want | `filthy_fifteen` | **Filthy fifteen** | 15+ in one game | ✅ | 13 | 5.0% | 12.1% | legendary? | ratedresults any game |
| want | `on_the_scoresheet` | **On the scoresheet** | Scored in 10 consecutive games | 🔶 | 94 | 36.0% | 87.9% | pitch | chronological 10 scored in row |
| want | `rare_blank` | **Rare blank** | 0 goals in a game after 50+ career games | ✅ | 80 | 30.7% | 74.8% | dedicated? | chronological |

---

## L. Double-digit culture (10+ goals)

*Merchant lore — scored, conceded, shared, spread.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `victim_of_commerce` | **Victim of commerce** | First time conceded 10+ | ✅ | 121 | 46.4% | 113%+ | pitch | ratedresults any game |
| want | `merchant_trade_fair` | **Merchant trade fair** | Draw **10–10** | ✅ | 4 | 1.5% | 3.7% | legendary? | ratedresults 10-10 draw |
| want | `leaky_merchant` | **Leaky merchant** | Won 10+ scored and 9 conceded | ✅ | 7 | 2.7% | 6.5% | legendary? | ratedresults any game |
| want | `travelling_salesman` | **Travelling salesman** | DD vs 10 different opponents | 🔶 | 18 | 6.9% | 16.8% | accomplished? | ratedresults distinct per-game DD opponents (>=10) |
| want | `diversity_merchant` | **Diversity merchant** | DD vs 5 different opponents | 🔶 | 25 | 9.6% | 23.4% | accomplished? | ratedresults distinct per-game DD opponents (>=5) |
| want | `merchant_streak` | **Merchant streak** | 5 consecutive games scoring 10+ | 🔶 | 2 | 0.8% | 1.9% | ultra-rare? | chronological |
| want | `minimalist_merchant` | **Minimalist merchant** | 3 consecutive games with exactly 10 goals scored | 🔶 | 4 | 1.5% | 3.7% | legendary? | chronological |

*(Also `dd_merchant_10` in §K — same milestone.)*

---

## M. Defence, clean sheets & extreme scorelines

*Shutouts, high-scoring wins/draws, comeback from conceding many.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `fortress_builder` | **Fortress builder** | 25 career clean sheets | ✅ | 51 | 19.5% | 47.7% | dedicated? | playertable |
| want | `clean_sheet_artist` | **Clean sheet artist** | 50 career clean sheets | 🔶 | 36 | 13.8% | 33.6% | dedicated? | playertable |
| want | `clean_sheet_spread` | **Clean sheet spread** | Clean sheet vs 10 different opponents | 🔶 | 47 | 18.0% | 43.9% | dedicated? | ratedresults distinct CS victims |
| want | `minimalist` | **Minimalist** | Won 1–0 | ✅ | 79 | 30.3% | 73.8% | dedicated? | ratedresults any game |
| want | `perfect_storm` | **Perfect storm** | Won 10–0 | ✅ | 23 | 8.8% | 21.5% | accomplished? | ratedresults any game |
| want | `battle_hardened` | **Battle hardened** | Draw ≥5–5 | ✅ | 60 | 23.0% | 56.1% | dedicated? | ratedresults 5-5+ draw |
| want | `survivor` | *(Survivor / Last man standing TBD)* | Won after opponent scored 7+ | ✅ | 32 | 12.3% | 29.9% | accomplished? | ratedresults any game |

---

## N. Draw culture

*Draws as a style — counts, high-scoring draws, draw streaks.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `goal_fest_draw` | **Goal fest draw** | Draw, 14+ total goals | ✅ | 37 | 14.2% | 34.6% | dedicated? | ratedresults any game |
| want | `peace_streak` | **Peace streak** | 3 draws in a row | 🔶 | 57 | 21.8% | 53.3% | dedicated? | chronological 3 draws row |
| want | `united_nations` | **United Nations** | 5 draws in a row | 🔶 | 3 | 1.1% | 2.8% | ultra-rare? | chronological 5 draws row |

*(Career draw count `ten_draws` is in §D; first draw `first_handshake` in §A.)*

---

## O. Margins, chaos & knife-edge runs

*Blowouts, totals, consecutive narrow wins/losses.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `comfortable` | **Comfortable** | Won by 5+ goal margin | ✅ | 83 | 31.8% | 77.6% | dedicated? | ratedresults any game |
| want | `ruthless` | **Ruthless** | Won by 10+ goal margin | ✅ | 35 | 13.4% | 32.7% | dedicated? | ratedresults any game |
| want | `hard_lesson` | **Hard lesson** | Lost by 10+ margin | ✅ | 96 | 36.8% | 89.7% | pitch | ratedresults any game |
| want | `twenty_goal_chaos` | **Twenty-goal chaos** | 20+ total goals in game | ✅ | 15 | 5.7% | 14.0% | accomplished? | ratedresults any game |
| want | `knife_edge` | **Knife-edge** | 5 consecutive 1-margin wins | 🔶 | 8 | 3.1% | 7.5% | legendary? | chronological |
| want | `unlucky` | **Unlucky** | 5 consecutive 1-margin losses | 🔶 | 7 | 2.7% | 6.5% | legendary? | chronological |

---

## P. Rating & Elo clubs

*First time reaching post-game rating thresholds.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `club_1700` | **1700 club** | Post-game **Rating** ≥1700 (any game #) | 🔶 | 49 | 18.8% | 45.8% | dedicated? | contract: `Rating`; rebuild still uses `PeakRating` filter — align |
| want | `club_1800` | **1800 club** | ≥1800 | 🔶 | 38 | 14.6% | 35.5% | dedicated? | same |
| want | `club_1900` | **1900 club** | ≥1900 | 🔶 | 31 | 11.9% | 29.0% | accomplished? | same |
| want | `club_2000` | **2000 club** | ≥2000 | 🔶 | 26 | 10.0% | 24.3% | accomplished? | same |
| want | `elite_altitude` | **Elite altitude** | ≥2100 | 🔶 | 18 | 6.9% | 16.8% | accomplished? | same |
| want | `club_2300` | *(name TBD)* | **Rating** ≥2300 | 🔶 | 6 | 2.3% | 5.6% | legendary? | same |

---

## Q. Upsets, giants & nemesis moments

*Beating stronger players; #1 active; record pain on Established rivals.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `massive_upset` | **Massive upset** | Beat opponent 500+ higher (pre-game) | ✅ | 39 | 14.9% | 36.4% | dedicated? | ratedresults pre-game ratings |
| want | `giant_slayer` | **Giant slayer** | Beat #1 rated **active** player | 🔶 | 31 | 11.9% | 29.0% | accomplished? | chrono beat #1 active (365d rolling UTC) |

*(Elite customer DD vs top active is in §L.)*

---

## R. Head-to-head dominance & rivalry depth

*Same opponent repeatedly — wins and long series.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `regular_customer` | **Regular customer** | 10 wins vs same opponent | 🔶 | 71 | 27.2% | 66.4% | dedicated? | player_matchup_summary max vs one opponent |
| want | `bogeyman` | **Bogeyman** | 20 wins vs same opponent | 🔶 | 63 | 24.1% | 58.9% | dedicated? | player_matchup_summary |
| want | `ten_match_saga` | **Ten-match saga** | 10th rated game vs same opponent | 🔶 | 94 | 36.0% | 87.9% | pitch | player_matchup_summary |
| want | `lifetime_rivalry` | **Lifetime rivalry** | 50th rated game vs same opponent | 🔶 | 65 | 24.9% | 60.7% | dedicated? | player_matchup_summary |

---

## S. Opponent breadth (network)

*How many different people you have played.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `ten_opponents` | **Ten opponents** | 10 unique opponents | ✅ | 91 | 34.9% | 85.0% | pitch | playertable |
| want | `wide_net` | **Wide net** | 25 unique opponents | ✅ | 54 | 20.7% | 50.5% | dedicated? | playertable |
| want | `fifty_faces` | **Fifty faces** | 50 unique opponents | ✅ | 26 | 10.0% | 24.3% | accomplished? | playertable |
| want | `century_of_rivals` | **Century of rivals** | 100 unique opponents | ✅ | 2 | 0.8% | 1.9% | ultra-rare? | playertable |

---

## T. Victims & culprits (variety of wins & losses)

*Distinct opponents you have beaten or lost to.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `five_victims` | **Five victims** | 5 distinct victims (wins) | ✅ | 71 | 27.2% | 66.4% | dedicated? | playertable |
| want | `twenty_five_victims` | *(name TBD)* | 25 distinct victims | ✅ | 43 | 16.5% | 40.2% | dedicated? | playertable |
| want | `ten_culprits` | *(funny name TBD)* | 10 distinct culprits (losses) | ✅ | 82 | 31.4% | 76.6% | dedicated? | playertable |

---

## U. Streaks & droughts (form)

*Win/loss runs — hot and cold.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `win_hat_trick` | **Win hat-trick** | 3 wins in a row | 🔶 | 85 | 32.6% | 79.4% | dedicated? | playertable longest streak proxy |
| want | `ten_wins_straight` | **Ten wins straight** | 10 wins in a row | 🔶 | 35 | 13.4% | 32.7% | dedicated? | playertable longest streak proxy |
| want | `rampage` | **Rampage** | 15 wins in a row | 🔶 | 18 | 6.9% | 16.8% | accomplished? | playertable longest streak proxy |
| want | `win_streak_30` | *(name TBD)* | 30 wins in a row | 🔶 | 6 | 2.3% | 5.6% | legendary? | playertable longest streak proxy |
| want | `cold_streak` | **Cold streak** | 5 losses in a row | 🔶 | 138 | 52.9% | 129%+ | pitch | playertable longest streak proxy |
| want | `win_drought` | **Win drought** | 10 games without a win | 🔶 | 102 | 39.1% | 95.3% | pitch | playertable longest streak proxy |

---

## V. Quirky scorelines & merchant edge cases

*Humorous one-offs — specific results and merchant pain.*

| Curate | Key | Display name | Rule (short) | Data | | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |
|--------|-----|--------------|--------------|------| |--------:|--------:|--------:|--------|--------|
| want | `merchant_denied` | **Merchant denied** | Lost 10–9 | ✅ | 6 | 2.3% | 5.6% | legendary? | ratedresults any game |

---

## Tier sizing hints (auto-band from veteran %)

Target garden sizes (design): **pitch** + **dedicated** = bulk; **accomplished** ~15–20; **legendary** ~10–15. **Band** column is a probe hint only — flavor and caps override.

| Band hint | Count | Target tier size (design) |
|-----------|------:|---------------------------|
| pitch | 22 | large (rarity floor; many at 80%+ vet) |
| dedicated? | 50 | large (~35–50) |
| accomplished? | 20 | ~15–20 keystones |
| legendary? | 16 | ~10–15 |
| ultra-rare? | 4 | legendary or nudge threshold |
| ? | 0 |  |

**Veteran denominator:** `NumberGames >= 20` → **107** players. **%vet** can exceed 100% (unlock includes sub-20-game players); that still means **pitch** floor, not discard.

---


---

## Tier palettes (Dagh locked May 2026 + probe)

Presentation order: **Legendary → Accomplished → Dedicated → Aspirational**. Top two bands locked; lower bands are probe hints minus anything already locked above.

**Discarded (catalog):** `back_in_the_game`, `league_daily_points_winner` (dup `moment_of_glory`), `long_sleep_loud_wakeup`, `nine_eight_thriller`, `double_digit_handshake`, `club_5000`.

### Legendary (18)

Holo — flavor + long horizons. Sorted rarest first (%vet).

| § | Key | Display | Unlock | %vet | Rule (short) |
|---|-----|---------|-------:|-----:|--------------|
| C | `club_10000` | **10K** | 1 | 0.9% | 10,000 rated games |
| L | `merchant_streak` | **Merchant streak** | 2 | 1.9% | 5 consecutive games scoring 10+ |
| S | `century_of_rivals` | **Century of rivals** | 2 | 1.9% | 100 unique opponents |
| N | `united_nations` | **United Nations** | 3 | 2.8% | 5 draws in a row |
| L | `minimalist_merchant` | **Minimalist merchant** | 4 | 3.7% | 3 consecutive games with exactly 10 goals scored |
| J | `league_wins_500` | 500 league wins | 4 | 3.7% | 500 league wins |
| L | `merchant_trade_fair` | **Merchant trade fair** | 4 | 3.7% | Draw **10–10** |
| I | `league_yearly_activity_winner` | Yearly · activity · winner | 5 | 4.7% | Yearly · activity · winner |
| I | `league_yearly_points_winner` | Yearly · points · winner | 5 | 4.7% | Yearly · points · winner |
| G | `ultra_day_30` | *(name TBD)* | 5 | 4.7% | 30 rated games in one UTC day |
| F | `monthly_regular` | **Monthly regular** | 5 | 4.7% | Rated game on every calendar day of at least one month |
| V | `merchant_denied` | **Merchant denied** | 6 | 5.6% | Lost 10–9 |
| P | `club_2300` | *(name TBD)* | 6 | 5.6% | Peak rating ≥2300 |
| U | `win_streak_30` | *(name TBD)* | 6 | 5.6% | 30 wins in a row |
| O | `unlucky` | **Unlucky** | 7 | 6.5% | 5 consecutive 1-margin losses |
| L | `leaky_merchant` | **Leaky merchant** | 7 | 6.5% | Won 10+ scored and 9 conceded |
| O | `knife_edge` | **Knife-edge** | 8 | 7.5% | 5 consecutive 1-margin wins |
| K | `filthy_fifteen` | **Filthy fifteen** | 13 | 12.1% | 15+ in one game |

### Accomplished / Keystones (21)

Amber — completeness palette. Sorted rarest first (%vet).

| § | Key | Display | Unlock | %vet | Rule (short) |
|---|-----|---------|-------:|-----:|--------------|
| I | `league_yearly_points_medal` | Yearly · points · medal | 12 | 11.2% | Yearly · points · medal |
| I | `league_monthly_points_winner` | Monthly · points · winner | 13 | 12.1% | Monthly · points · winner |
| I | `league_yearly_activity_medal` | Yearly · activity · medal | 15 | 14.0% | Yearly · activity · medal |
| O | `twenty_goal_chaos` | **Twenty-goal chaos** | 15 | 14.0% | 20+ total goals in game |
| J | `league_wins_100` | 100 league wins | 15 | 14.0% | 100 league wins |
| H | `activity_king` | **Activity king** | 17 | 15.9% | Won monthly **activity** league |
| U | `rampage` | **Rampage** | 18 | 16.8% | 15 wins in a row |
| L | `travelling_salesman` | **Travelling salesman** | 18 | 16.8% | DD vs 10 different opponents |
| M | `perfect_storm` | **Perfect storm** | 23 | 21.5% | Won 10–0 |
| J | `league_wins_50` | 50 league wins | 23 | 21.5% | 50 league wins |
| I | `league_weekly_points_winner` | Weekly · points · winner | 24 | 22.4% | Weekly · points · winner |
| L | `diversity_merchant` | **Diversity merchant** | 25 | 23.4% | DD vs 5 different opponents |
| G | `absurd_day` | **Absurd day** | 26 | 24.3% | 20 rated games in one UTC day |
| P | `club_2000` | **2000 club** | 26 | 24.3% | ≥2000 |
| S | `fifty_faces` | **Fifty faces** | 26 | 24.3% | 50 unique opponents |
| I | `league_monthly_points_medal` | Monthly · points · medal | 27 | 25.2% | Monthly · points · medal |
| Q | `giant_slayer` | **Giant slayer** | 31 | 29.0% | Beat #1 rated **active** player |
| K | `dozen_dash` | **Dozen dash** | 31 | 29.0% | 12+ in one game |
| M | `survivor` | *(Survivor / Last man standing TBD)* | 32 | 29.9% | Won after opponent scored 7+ |
| I | `league_weekly_activity_winner` | Weekly · activity · winner | 33 | 30.8% | Weekly · activity · winner |
| O | `ruthless` | **Ruthless** | 35 | 32.7% | Won by 10+ goal margin |

### Dedicated (49)

Chrome — mid ladder bulk. Sorted rarest first (promotion candidates at top).

| § | Key | Display | Unlock | %vet | Rule (short) |
|---|-----|---------|-------:|-----:|--------------|
| I | `league_monthly_activity_medal` | Monthly · activity · medal | 35 | 32.7% | Monthly · activity · medal |
| U | `ten_wins_straight` | **Ten wins straight** | 35 | 32.7% | 10 wins in a row |
| G | `perfect_day` | **Perfect day** | 36 | 33.6% | Won all games in UTC day (min 5) |
| M | `clean_sheet_artist` | **Clean sheet artist** | 36 | 33.6% | 50 career clean sheets |
| C | `millennium_merchant_1000` | **Millennium merchant** | 37 | 34.6% | 1,000 rated games |
| N | `goal_fest_draw` | **Goal fest draw** | 37 | 34.6% | Draw, 14+ total goals |
| P | `club_1800` | **1800 club** | 38 | 35.5% | ≥1800 |
| Q | `massive_upset` | **Massive upset** | 39 | 36.4% | Beat opponent 500+ higher (pre-game) |
| J | `league_wins_10` | 10 career league wins | 42 | 39.3% | 10 career league wins |
| A | `generous` | **Generous** | 43 | 40.2% | In a debut game, let newcomer score ≥2 |
| F | `year_round` | **Year-round** | 43 | 40.2% | Rated game in 12 consecutive calendar months |
| T | `twenty_five_victims` | *(name TBD)* | 43 | 40.2% | 25 distinct victims |
| C | `club_500` | **500 club** | 44 | 41.1% | 500 rated games |
| I | `league_weekly_points_medal` | Weekly · points · medal | 44 | 41.1% | Weekly · points · medal |
| K | `dd_merchant_10` | **Double Digit Merchant** | 44 | 41.1% | 10+ in one game |
| F | `daily_habit` | **Daily habit** | 47 | 43.9% | Rated game every calendar day Mon–Sun in one Monday-start week |
| F | `weekly_regular` | **Weekly regular** | 47 | 43.9% | ≥1 rated game every week for 3 consecutive months |
| M | `clean_sheet_spread` | **Clean sheet spread** | 47 | 43.9% | Clean sheet vs 10 different opponents |
| E | `thousand_goal_club` | **Thousand-goal club** | 49 | 45.8% | 1,000 career goals |
| P | `club_1700` | **1700 club** | 49 | 45.8% | Rating ≥1700 |
| D | `century_of_wins` | **Century of wins** | 51 | 47.7% | 100 career wins |
| M | `fortress_builder` | **Fortress builder** | 51 | 47.7% | 25 career clean sheets |
| I | `league_weekly_activity_medal` | Weekly · activity · medal | 54 | 50.5% | Weekly · activity · medal |
| S | `wide_net` | **Wide net** | 54 | 50.5% | 25 unique opponents |
| C | `marathoner_250` | **Marathoner** | 56 | 52.3% | 250 rated games |
| N | `peace_streak` | **Peace streak** | 57 | 53.3% | 3 draws in a row |
| M | `battle_hardened` | **Battle hardened** | 60 | 56.1% | Draw ≥5–5 |
| H | `moment_of_glory` | **Moment of glory** | 62 | 57.9% | Won daily **points** league |
| R | `bogeyman` | **Bogeyman** | 63 | 58.9% | 20 wins vs same opponent |
| K | `eight_goal_storm` | **Eight-goal storm** | 64 | 59.8% | 8+ in one game |
| D | `battle_scarred` | **Battle-scarred** | 65 | 60.7% | 100 career losses |
| R | `lifetime_rivalry` | **Lifetime rivalry** | 65 | 60.7% | 50th rated game vs same opponent |
| G | `grind_month` | **Grind month** | 67 | 62.6% | 50 rated games in one calendar month |
| D | `ten_draws` | **Ten draws** | 71 | 66.4% | 10 career draws |
| R | `regular_customer` | **Regular customer** | 71 | 66.4% | 10 wins vs same opponent |
| T | `five_victims` | **Five victims** | 71 | 66.4% | 5 distinct victims (wins) |
| C | `centurion_100` | **Centurion** | 72 | 67.3% | 100 rated games |
| A | `newbie_welcomer` | **Newbie welcomer** | 75 | 70.1% | You were first rated opponent in someone’s debut |
| G | `nightmare_day` | **Nightmare day** | 77 | 72.0% | Lost all games in UTC day (min 5) |
| D | `ten_wins` | **Ten wins** | 78 | 72.9% | 10 career wins |
| G | `marathon_day` | **Marathon day** | 79 | 73.8% | 10 rated games in one UTC day |
| M | `minimalist` | **Minimalist** | 79 | 73.8% | Won 1–0 |
| K | `rare_blank` | **Rare blank** | 80 | 74.8% | 0 goals in a game after 50+ career games |
| E | `hundred_goals` | *(name TBD — e.g. Century scorer)* | 81 | 75.7% | 100 career goals |
| T | `ten_culprits` | *(funny name TBD)* | 82 | 76.6% | 10 distinct culprits (losses) |
| C | `half_century_50` | **Half century** | 83 | 77.6% | 50 rated games |
| O | `comfortable` | **Comfortable** | 83 | 77.6% | Won by 5+ goal margin |
| I | `league_daily_activity_winner` | Daily · activity · winner | 84 | 78.5% | Daily · activity · winner |
| U | `win_hat_trick` | **Win hat-trick** | 85 | 79.4% | 3 wins in a row |

### Aspirational (22)

Pitch — rarity floor. Sorted commonest first (%vet ↓).

| § | Key | Display | Unlock | %vet | Rule (short) |
|---|-----|---------|-------:|-----:|--------------|
| A | `debut` | **Debut** | 261 | 244%+ | First rated game |
| A | `entered_arena` | **Entered the arena** | 261 | 244%+ | First lobby presence |
| A | `welcome_to_the_ladder` | **Welcome to the ladder** | 252 | 236%+ | First loss |
| A | `first_goal` | **First goal** | 226 | 211%+ | First career goal |
| K | `brace` | **Brace** | 183 | 171%+ | 2+ goals in one game |
| K | `hat_trick` | **Hat-trick** | 156 | 146%+ | 3+ in one game |
| A | `first_victory` | **First victory** | 145 | 136%+ | First win |
| A | `first_handshake` | **First handshake** | 146 | 136%+ | First draw |
| U | `cold_streak` | **Cold streak** | 138 | 129%+ | 5 losses in a row |
| A | `persistence` | **Persistence** *(name TBD)* | 134 | 125%+ | ~5–10 rated games; early survival |
| G | `hot_day` | **Hot day** | 134 | 125%+ | 5 rated games in one UTC day |
| A | `first_shutout` | **First shutout** | 127 | 119%+ | First clean sheet |
| L | `victim_of_commerce` | **Victim of commerce** | 121 | 113%+ | First time conceded 10+ |
| I | `league_daily_activity_medal` | Daily · activity · medal | 114 | 107%+ | Daily · activity · medal |
| A | `established_20` | **Established** | 107 | 100%+ | 20 rated games |
| K | `five_goal_frenzy` | **Five-goal frenzy** | 102 | 95.3% | 5+ in one game |
| U | `win_drought` | **Win drought** | 102 | 95.3% | 10 games without a win |
| O | `hard_lesson` | **Hard lesson** | 96 | 89.7% | Lost by 10+ margin |
| I | `league_daily_points_medal` | Daily · points · medal | 94 | 87.9% | Daily · points · medal |
| K | `on_the_scoresheet` | **On the scoresheet** | 94 | 87.9% | Scored in 10 consecutive games |
| R | `ten_match_saga` | **Ten-match saga** | 94 | 87.9% | 10th rated game vs same opponent |
| S | `ten_opponents` | **Ten opponents** | 91 | 85.0% | 10 unique opponents |
---

## Quick index (group → section)

| Group | § | Items (approx.) |
|-------|---|-----------------|
| First steps & belonging | A | 11 |
| Returns & absence | B | 3 |
| Games played | C | 6 |
| W/L/draw counts | D | 4 |
| Career goals | E | 2 |
| Calendar rhythm | F | 4 |
| Activity bursts | G | 6 |
| Leagues generic | H | 4 |
| Leagues 2×8 | I | 16 |
| League win totals | J | 4 |
| Single-game scoring | K | 9 |
| Double-digit culture | L | 9 (+1 dup) |
| Defence & extremes | M | 7 |
| Draw culture | N | 4 |
| Margins & chaos | O | 6 |
| Elo clubs | P | 5 |
| Upsets & giants | Q | 3 |
| H2H rivalry | R | 4 |
| Opponent breadth | S | 4 |
| Victims & culprits | T | 3 |
| Streaks & droughts | U | 5 |
| Quirky scorelines | V | 2 |

---

## Suggested review order (optional)

1. **A → B** — identity & returns  
2. **C → D → E** — volume ladders (games / W-L-D / goals)  
3. **F → G** — rhythm & bursts  
4. **H → I → J** — all league material in one sitting  
5. **K → L → M → N → O → V** — match flavour (attack → DD → defence → draws → margins → quirks)  
6. **P** — Elo clubs (threshold ladder)  
7. **Q → R → S → T** — opponents & rivals  
8. **U** — streaks  

---

*Generated for Phase 2 manual tier pass · May 2026. Assign `tier_band` on catalog or a copy; update [`milestones-product-spec.md`](milestones-product-spec.md) when band names are locked.*

*Probe: `nemesis` not computed. `elite_customer` proxy. `top_ten_sweep` discarded (unstable snapshot). Re-run after replay/league rebuild.*
