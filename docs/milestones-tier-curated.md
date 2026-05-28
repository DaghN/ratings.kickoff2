# Milestones — curated tier list

**Kick Off 2 ratings site · Phase 2 definition snapshot**

**Status:** **Decided for now** (May 2026). This is the working milestone set and tier assignment until a later pass changes it. Not an implementation spec — rules and display copy details live in [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md).

**Related:** [`milestones-product-spec.md`](milestones-product-spec.md) (presentation) · [`milestones-want-maybe-by-theme.md`](milestones-want-maybe-by-theme.md) (themed tables + probe) · [`milestones-project.md`](milestones-project.md) (phases).

---

## Summary

| Band | Chart token | Count | Role |
|------|-------------|------:|------|
| **Legendary** | `holo` | 18 | Rare feats, long horizons, merchant lore peaks |
| **Accomplished** | `amber` | 20 | Keystones — serious ladder citizenship |
| **Dedicated** | `chrome` | 50 | Mid-ladder grind, variety, leagues volume |
| **Aspirational** | `pitch` | 24 | First steps and broad participation floor |
| **Total in curated set** | — | **112** | — |

**Probe context** (difficulty reference only): 261 players with ≥1 rated game; **107** veterans (≥20 games); 74870 rated games; 2026-05-28 10:30 UTC. Regenerate counts: `python scripts/oneoff/milestone_unlock_counts.py --write-doc`.

---

## Tier order (presentation)

Legendary → Accomplished → Dedicated → Aspirational (rarest / highest band first in UI).

---

## Win-streak milestones — rule

These keys use **`playertable.LongestWinningStreak`** (career maximum consecutive wins):

`win_hat_trick` (≥3) · `ten_wins_straight` (≥10) · `rampage` (≥15) · `win_streak_30` (≥30).

`cold_streak` and `win_drought` use the corresponding **longest loss / non-win streak** columns on the same table.

Unlock when the stored career-best run reaches the threshold. Implementation should read the ladder-maintained column (same source as the profile streak display), not a separate replay pass, unless the data contract is extended later.

---

## Legendary (18)


| Key | Display name | Rule (short) | Unlock | %vet |
|-----|--------------|--------------|-------:|-----:|
| `club_10000` | *(name TBD)* | 10,000 rated games | 1 | 0.9% |
| `century_of_rivals` | **Century of rivals** | 100 unique opponents | 2 | 1.9% |
| `merchant_streak` | **Merchant streak** | 5 consecutive games scoring 10+ | 2 | 1.9% |
| `united_nations` | **United Nations** | 5 draws in a row | 3 | 2.8% |
| `league_wins_500` | 500 league wins | 500 league wins | 4 | 3.7% |
| `merchant_trade_fair` | **Merchant trade fair** | Draw **10–10** | 4 | 3.7% |
| `minimalist_merchant` | **Minimalist merchant** | 3 consecutive games with exactly 10 goals scored | 4 | 3.7% |
| `league_yearly_activity_winner` | Yearly · activity · winner | Yearly · activity · winner | 5 | 4.7% |
| `league_yearly_points_winner` | Yearly · points · winner | Yearly · points · winner | 5 | 4.7% |
| `monthly_regular` | **Monthly regular** | Rated game on every calendar day of at least one month | 5 | 4.7% |
| `ultra_day_30` | *(name TBD)* | 30 rated games in one UTC day | 5 | 4.7% |
| `club_2300` | *(name TBD)* | Peak rating ≥2300 | 6 | 5.6% |
| `merchant_denied` | **Merchant denied** | Lost 10–9 | 6 | 5.6% |
| `win_streak_30` | *(name TBD)* | 30 wins in a row | 6 | 5.6% |
| `leaky_merchant` | **Leaky merchant** | Won 10+ scored and 9 conceded | 7 | 6.5% |
| `unlucky` | **Unlucky** | 5 consecutive 1-margin losses | 7 | 6.5% |
| `knife_edge` | **Knife-edge** | 5 consecutive 1-margin wins | 8 | 7.5% |
| `filthy_fifteen` | **Filthy fifteen** | 15+ in one game | 13 | 12.1% |

---

## Accomplished (20)


| Key | Display name | Rule (short) | Unlock | %vet |
|-----|--------------|--------------|-------:|-----:|
| `league_yearly_points_medal` | Yearly · points · medal | Yearly · points · medal | 12 | 11.2% |
| `league_monthly_points_winner` | Monthly · points · winner | Monthly · points · winner | 13 | 12.1% |
| `league_wins_100` | 100 league wins | 100 league wins | 15 | 14.0% |
| `league_yearly_activity_medal` | Yearly · activity · medal | Yearly · activity · medal | 15 | 14.0% |
| `twenty_goal_chaos` | **Twenty-goal chaos** | 20+ total goals in game | 15 | 14.0% |
| `activity_king` | **Activity king** | Won monthly **activity** league | 17 | 15.9% |
| `rampage` | **Rampage** | 15 wins in a row | 18 | 16.8% |
| `travelling_salesman` | **Travelling salesman** | DD vs 10 different opponents | 18 | 16.8% |
| `giant_slayer` | **Giant slayer** | Beat #1 rated **active** player | 22 | 20.6% |
| `league_wins_50` | 50 league wins | 50 league wins | 23 | 21.5% |
| `perfect_storm` | **Perfect storm** | Won 10–0 | 23 | 21.5% |
| `league_weekly_points_winner` | Weekly · points · winner | Weekly · points · winner | 24 | 22.4% |
| `absurd_day` | **Absurd day** | 20 rated games in one UTC day | 26 | 24.3% |
| `club_2000` | **2000 club** | ≥2000 | 26 | 24.3% |
| `fifty_faces` | **Fifty faces** | 50 unique opponents | 26 | 24.3% |
| `league_monthly_points_medal` | Monthly · points · medal | Monthly · points · medal | 27 | 25.2% |
| `dozen_dash` | **Dozen dash** | 12+ in one game | 31 | 29.0% |
| `survivor` | *(Survivor / Last man standing TBD)* | Won after opponent scored 7+ | 32 | 29.9% |
| `league_weekly_activity_winner` | Weekly · activity · winner | Weekly · activity · winner | 33 | 30.8% |
| `ruthless` | **Ruthless** | Won by 10+ goal margin | 35 | 32.7% |

---

## Dedicated (50)


| Key | Display name | Rule (short) | Unlock | %vet |
|-----|--------------|--------------|-------:|-----:|
| `win_hat_trick` | **Win hat-trick** | 3 wins in a row | 85 | 79.4% |
| `league_daily_activity_winner` | Daily · activity · winner | Daily · activity · winner | 84 | 78.5% |
| `half_century_50` | **Half century** | 50 rated games | 83 | 77.6% |
| `comfortable` | **Comfortable** | Won by 5+ goal margin | 83 | 77.6% |
| `ten_culprits` | *(funny name TBD)* | 10 distinct culprits (losses) | 82 | 76.6% |
| `hundred_goals` | *(name TBD — e.g. Century scorer)* | 100 career goals | 81 | 75.7% |
| `rare_blank` | **Rare blank** | 0 goals in a game after 50+ career games | 80 | 74.8% |
| `marathon_day` | **Marathon day** | 10 rated games in one UTC day | 79 | 73.8% |
| `minimalist` | **Minimalist** | Won 1–0 | 79 | 73.8% |
| `ten_wins` | **Ten wins** | 10 career wins | 78 | 72.9% |
| `nightmare_day` | **Nightmare day** | Lost all games in UTC day (min 5) | 77 | 72.0% |
| `newbie_welcomer` | **Newbie welcomer** | You were first rated opponent in someone’s debut | 75 | 70.1% |
| `centurion_100` | **Centurion** | 100 rated games | 72 | 67.3% |
| `ten_draws` | **Ten draws** | 10 career draws | 71 | 66.4% |
| `regular_customer` | **Regular customer** | 10 wins vs same opponent | 71 | 66.4% |
| `five_victims` | **Five victims** | 5 distinct victims (wins) | 71 | 66.4% |
| `diversity_merchant` | **Diversity merchant** | DD vs 5 different opponents | 68 | 63.6% |
| `grind_month` | **Grind month** | 50 rated games in one calendar month | 67 | 62.6% |
| `battle_scarred` | **Battle-scarred** | 100 career losses | 65 | 60.7% |
| `lifetime_rivalry` | **Lifetime rivalry** | 50th rated game vs same opponent | 65 | 60.7% |
| `eight_goal_storm` | **Eight-goal storm** | 8+ in one game | 64 | 59.8% |
| `bogeyman` | **Bogeyman** | 20 wins vs same opponent | 63 | 58.9% |
| `moment_of_glory` | **Moment of glory** | Won daily **points** league | 62 | 57.9% |
| `battle_hardened` | **Battle hardened** | Draw ≥5–5 | 60 | 56.1% |
| `peace_streak` | **Peace streak** | 3 draws in a row | 57 | 53.3% |
| `marathoner_250` | **Marathoner** | 250 rated games | 56 | 52.3% |
| `league_weekly_activity_medal` | Weekly · activity · medal | Weekly · activity · medal | 54 | 50.5% |
| `wide_net` | **Wide net** | 25 unique opponents | 54 | 50.5% |
| `century_of_wins` | **Century of wins** | 100 career wins | 51 | 47.7% |
| `fortress_builder` | **Fortress builder** | 25 career clean sheets | 51 | 47.7% |
| `thousand_goal_club` | **Thousand-goal club** | 1,000 career goals | 49 | 45.8% |
| `club_1700` | **1700 club** | Rating ≥1700 | 49 | 45.8% |
| `daily_habit` | **Daily habit** | Rated game every calendar day Mon–Sun in one Monday-start week | 47 | 43.9% |
| `weekly_regular` | **Weekly regular** | ≥1 rated game every week for 3 consecutive months | 47 | 43.9% |
| `clean_sheet_spread` | **Clean sheet spread** | Clean sheet vs 10 different opponents | 47 | 43.9% |
| `club_500` | **500 club** | 500 rated games | 44 | 41.1% |
| `league_weekly_points_medal` | Weekly · points · medal | Weekly · points · medal | 44 | 41.1% |
| `dd_merchant_10` | **Double Digit Merchant** | 10+ in one game | 44 | 41.1% |
| `generous` | **Generous** | In a debut game, let newcomer score ≥2 | 43 | 40.2% |
| `year_round` | **Year-round** | Rated game in 12 consecutive calendar months | 43 | 40.2% |
| `twenty_five_victims` | *(name TBD)* | 25 distinct victims | 43 | 40.2% |
| `league_wins_10` | 10 career league wins | 10 career league wins | 42 | 39.3% |
| `massive_upset` | **Massive upset** | Beat opponent 500+ higher (pre-game) | 39 | 36.4% |
| `club_1800` | **1800 club** | ≥1800 | 38 | 35.5% |
| `millennium_merchant_1000` | **Millennium merchant** | 1,000 rated games | 37 | 34.6% |
| `goal_fest_draw` | **Goal fest draw** | Draw, 14+ total goals | 37 | 34.6% |
| `perfect_day` | **Perfect day** | Won all games in UTC day (min 5) | 36 | 33.6% |
| `clean_sheet_merchant` | **Clean sheet artist** | 50 career clean sheets | 36 | 33.6% |
| `league_monthly_activity_medal` | Monthly · activity · medal | Monthly · activity · medal | 35 | 32.7% |
| `ten_wins_straight` | **Ten wins straight** | 10 wins in a row | 35 | 32.7% |

---

## Aspirational (24)


| Key | Display name | Rule (short) | Unlock | %vet |
|-----|--------------|--------------|-------:|-----:|
| `period_champion` | **Period champion** | Won a period league (points or activity) | 87 | 81.3% |
| `ten_opponents` | **Ten opponents** | 10 unique opponents | 91 | 85.0% |
| `league_daily_points_medal` | Daily · points · medal | Daily · points · medal | 94 | 87.9% |
| `on_the_scoresheet` | **On the scoresheet** | Scored in 10 consecutive games | 94 | 87.9% |
| `ten_match_saga` | **Ten-match saga** | 10th rated game vs same opponent | 94 | 87.9% |
| `six_goal_draw` | **Six-goal draw** | Draw, 6+ total goals | 96 | 89.7% |
| `hard_lesson` | **Hard lesson** | Lost by 10+ margin | 96 | 89.7% |
| `five_goal_frenzy` | **Five-goal frenzy** | 5+ in one game | 102 | 95.3% |
| `win_drought` | **Win drought** | 10 games without a win | 102 | 95.3% |
| `established_20` | **Established** | 20 rated games | 107 | 100%+ |
| `league_daily_activity_medal` | Daily · activity · medal | Daily · activity · medal | 114 | 107%+ |
| `victim_of_commerce` | **Victim of commerce** | First time conceded 10+ | 121 | 113%+ |
| `first_shutout` | **First shutout** | First clean sheet | 127 | 119%+ |
| `hot_day` | **Hot day** | 5 rated games in one UTC day | 134 | 125%+ |
| `cold_streak` | **Cold streak** | 5 losses in a row | 138 | 129%+ |
| `first_victory` | **First victory** | First win | 145 | 136%+ |
| `first_handshake` | **First handshake** | First draw | 146 | 136%+ |
| `hat_trick` | **Hat-trick** | 3+ in one game | 156 | 146%+ |
| `persistence` | **Persistence** *(name TBD)* | ~5–10 rated games; early survival | 169 | 158%+ |
| `brace` | **Brace** | 2+ goals in one game | 183 | 171%+ |
| `first_goal` | **First goal** | First career goal | 226 | 211%+ |
| `welcome_to_the_ladder` | **Welcome to the ladder** | First loss | 252 | 236%+ |
| `debut` | **Debut** | First rated game | 261 | 244%+ |
| `entered_arena` | **Entered the arena** | First lobby presence | 261 | 244%+ |

---

## Out of curated set (discarded for now)

Not in the four bands above. Kept in the ideas catalog as `discard` for reference only.

| Key | Note |
|-----|------|
| `top_ten_sweep` | Unstable snapshot |
| `long_sleep_loud_wakeup` | Cut from legendary |
| `nine_eight_thriller` | Cut |
| `double_digit_handshake` | Merged into `merchant_trade_fair` (10–10 draw) |
| `club_5000` | Superseded by `club_10000` |
| `back_in_the_game` | Cut |
| `league_daily_points_winner` | Duplicate of `moment_of_glory` |
| `nemesis` | Cut |
| `elite_customer` | Cut |
| `podium_month` | Cut |
| `still_here_years_later` | Cut |
| `league_monthly_activity_winner` | Cut (`activity_king` covers monthly activity win) |

---

*Auto-generated from locked tier sets + probe. Do not hand-edit unlock counts — re-run the script.*
