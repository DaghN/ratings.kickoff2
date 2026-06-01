# Milestone catalog (generated)

**Start here:** [`milestones-README.md`](milestones-README.md).

Per-key **intended + implemented UI** view: identity (tier, title, rule) plus unlock-event **Link** and **Event**. Rebuild probe hints come from the definitions seed.

**Machine sources:** `data/milestones_definitions_seed.json` · `data/milestone_garden_links.json` · PHP `milestone_garden_links.php`.

**DB / rebuild contract:** [`website-data-contract.md`](website-data-contract.md) § `player_milestones` · families [`milestones-facilitation.md`](milestones-facilitation.md).

**Regenerate:** `python scripts/oneoff/build_milestone_garden_links.py` · **Seed version:** `2026-05-curated` · **Keys:** 112

## Summary by tier

| Band | Chart token | Keys |
|------|-------------|-----:|
| Legendary | `holo` | 20 |
| Accomplished | `amber` | 21 |
| Dedicated | `chrome` | 49 |
| Aspirational | `pitch` | 22 |
| **Total** | — | **112** |

## Full catalog

Sorted: Legendary → Accomplished → Dedicated → Aspirational, then `milestone_key`.

| `milestone_key` | Tier | Display name | Rule (short) | Link | Event | `rule_probe` |
|-----------------|------|--------------|--------------|------|-------|--------------|
| `century_of_rivals` | Legendary | Century of rivals | 100 unique opponents | Game | Scoreline (anchor game) | playertable |
| `club_10000` | Legendary | 10K | 10,000 rated games | Game | Scoreline (anchor game) | playertable |
| `club_2300` | Legendary | Summit club | Rating ≥2300 | Game | Scoreline (anchor game) | playertable PeakRating |
| `filthy_fifteen` | Legendary | Filthy fifteen | 15+ in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `knife_edge` | Legendary | Knife-edge | 5 consecutive 1-margin wins | Game | Scoreline (anchor game) | chronological |
| `league_wins_500` | Legendary | Cup collector | 500 league wins | League | League period label | player_league_totals.wins>=500 |
| `league_yearly_activity_winner` | Legendary | Calendar tyrant | Yearly activity winner | League | League period label | player_league_award (league_kind='activity' AND period_type='year' AND is_winner=1) |
| `league_yearly_points_winner` | Legendary | Destroyer of dreams | Yearly points winner | League | League period label | player_league_award (league_kind='points' AND period_type='year' AND is_winner=1) |
| `leaky_merchant` | Legendary | Leaky merchant | 10+ scored and 9 conceded | Game | Scoreline (anchor game) | ratedresults any game |
| `merchant_denied` | Legendary | Merchant denied | Lost 10–9 | Game | Scoreline (anchor game) | ratedresults any game |
| `merchant_streak` | Legendary | Wholesale run | 5 consecutive games scoring 10+ | Game | Scoreline (anchor game) | chronological |
| `merchant_trade_fair` | Legendary | Merchant trade fair | Draw **10–10** | Game | Scoreline (anchor game) | ratedresults 10-10 draw |
| `minimalist_merchant` | Legendary | Minimalist merchant | 3 consecutive games with exactly 10 goals scored | Game | Scoreline (anchor game) | chronological |
| `monthly_regular` | Legendary | Monthly regular | Rated game on every day of a calendar month | Game | Scoreline (anchor game) | chronological full month days |
| `play_streak_100` | Legendary | 100 days of bliss | 100 consecutive UTC days with a rated game | Game | Scoreline (anchor game) | player_period_games day streak |
| `ultra_day_30` | Legendary | Server overload | 30 rated games in one UTC day | Game | Scoreline (anchor game) | player_period_games day max |
| `united_nations` | Legendary | United Nations | 5 draws in a row | Game | Scoreline (anchor game) | chronological 5 draws row |
| `unlucky` | Legendary | Unlucky | 5 consecutive 1-margin losses | Game | Scoreline (anchor game) | chronological |
| `win_streak_30` | Legendary | Untouchable | 30 wins in a row | Game | Scoreline (anchor game) | playertable longest streak proxy |
| `year_in_heaven` | Legendary | Year in Heaven | Rated game in every UTC week of a calendar year | Game | Scoreline (anchor game) | player_period_games 52-week calendar year |
| `absurd_day` | Accomplished | Absurd day | 20 rated games in one UTC day | Game | Scoreline (anchor game) | player_period_games day max |
| `activity_king` | Accomplished | Activity king | Won monthly **activity** league | League | League period label | player_league_award (monthly activity win) |
| `club_2000` | Accomplished | 2000 club | Rating ≥2000 | Game | Scoreline (anchor game) | playertable PeakRating |
| `diversity_merchant` | Accomplished | Diversity merchant | DD vs 5 different opponents | Game | Scoreline (anchor game) | ratedresults distinct per-game DD opponents (>=5) |
| `dozen_dash` | Accomplished | Dozen dash | Scored 12+ in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `fifty_faces` | Accomplished | Fifty faces | 50 unique opponents | Game | Scoreline (anchor game) | playertable |
| `giant_slayer` | Accomplished | Giant slayer | Beat #1 rated **active** player | Game | Scoreline (anchor game) | chrono beat #1 active (365d rolling UTC) |
| `league_monthly_points_medal` | Accomplished | Runner-up reign | Monthly points medal | League | League period label | player_league_award (league_kind='points' AND period_type='month' AND finish_rank<=3) |
| `league_monthly_points_winner` | Accomplished | Reigning accountant | Monthly points winner | League | League period label | player_league_award (league_kind='points' AND period_type='month' AND is_winner=1) |
| `league_weekly_activity_winner` | Accomplished | Seven-day siege | Weekly activity winner | League | League period label | player_league_award (league_kind='activity' AND period_type='week' AND is_winner=1) |
| `league_weekly_points_winner` | Accomplished | Ledger lord of the week | Weekly points winner | League | League period label | player_league_award (league_kind='points' AND period_type='week' AND is_winner=1) |
| `league_wins_100` | Accomplished | Tasting blood | 100 league wins | League | League period label | player_league_totals.wins>=100 |
| `league_wins_50` | Accomplished | Cupboard filling up | 50 league wins | League | League period label | player_league_totals.wins>=50 |
| `league_yearly_activity_medal` | Accomplished | Grinder | Yearly activity medal | League | League period label | player_league_award (league_kind='activity' AND period_type='year' AND finish_rank<=3) |
| `league_yearly_points_medal` | Accomplished | Dreams merely wounded | Yearly points medal | League | League period label | player_league_award (league_kind='points' AND period_type='year' AND finish_rank<=3) |
| `perfect_storm` | Accomplished | Perfect storm | Won 10–0 | Game | Scoreline (anchor game) | ratedresults any game |
| `rampage` | Accomplished | Rampage | 15 wins in a row | Game | Scoreline (anchor game) | playertable longest streak proxy |
| `ruthless` | Accomplished | Ruthless | Won by 10+ goal margin | Game | Scoreline (anchor game) | ratedresults any game |
| `survivor` | Accomplished | Last man standing | Won after opponent scored 7+ | Game | Scoreline (anchor game) | ratedresults any game |
| `travelling_salesman` | Accomplished | Travelling salesman | DD vs 10 different opponents | Game | Scoreline (anchor game) | ratedresults distinct per-game DD opponents (>=10) |
| `twenty_goal_chaos` | Accomplished | Twenty-goal chaos | 20+ total goals in game | Game | Scoreline (anchor game) | ratedresults any game |
| `battle_hardened` | Dedicated | Battle-hardened | 5-5 or higher draw | Game | Scoreline (anchor game) | ratedresults 5-5+ draw |
| `battle_scarred` | Dedicated | Battle-scarred | 100 career losses | Game | Scoreline (anchor game) | playertable |
| `bogeyman` | Dedicated | Bogeyman | 20 wins vs same opponent | Game | Scoreline (anchor game) | player_matchup_summary |
| `centurion_100` | Dedicated | Centurion | 100 rated games | Game | Scoreline (anchor game) | playertable |
| `century_of_wins` | Dedicated | Century of wins | 100 career wins | Game | Scoreline (anchor game) | playertable |
| `clean_sheet_artist` | Dedicated | Clean sheet artist | 50 career clean sheets | Game | Scoreline (anchor game) | playertable |
| `clean_sheet_spread` | Dedicated | Clean sheet spread | Clean sheet vs 10 different opponents | Game | Scoreline (anchor game) | ratedresults distinct CS victims |
| `club_1700` | Dedicated | 1700 club | Rating ≥1700 | Game | Scoreline (anchor game) | playertable PeakRating |
| `club_1800` | Dedicated | 1800 club | Rating ≥1800 | Game | Scoreline (anchor game) | playertable PeakRating |
| `club_500` | Dedicated | 500 club | 500 rated games | Game | Scoreline (anchor game) | playertable |
| `comfortable` | Dedicated | Comfortable | Won by 5+ goal margin | Game | Scoreline (anchor game) | ratedresults any game |
| `daily_habit` | Dedicated | Daily habit | Rated game every day Monday to Sunday | Game | Scoreline (anchor game) | chronological Mon-Sun week |
| `dd_merchant_10` | Dedicated | Double Digit Merchant | Scored 10+ in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `eight_goal_storm` | Dedicated | Eight-goal storm | 8+ in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `five_victims` | Dedicated | Hit list | 5 distinct victims (wins) | Game | Scoreline (anchor game) | playertable |
| `fortress_builder` | Dedicated | Fortress builder | 25 career clean sheets | Game | Scoreline (anchor game) | playertable |
| `generous` | Dedicated | Generous | In a debut game, let newcomer score ≥2 | Game | Scoreline (anchor game) | chronological debut opp, newbie scored 2+ |
| `goal_fest_draw` | Dedicated | Goal fest draw | 7-7 or higher draw | Game | Scoreline (anchor game) | ratedresults any game |
| `grind_month` | Dedicated | Grind month | 50 rated games in one calendar month | Game | Scoreline (anchor game) | player_period_games month max |
| `half_century_50` | Dedicated | Half-century knock | 50 rated games | Game | Scoreline (anchor game) | playertable |
| `hundred_goals` | Dedicated | Century scorer | 100 career goals | Game | Scoreline (anchor game) | playertable |
| `league_daily_activity_winner` | Dedicated | Burned the day | Daily activity winner | League | League period label | player_league_award (league_kind='activity' AND period_type='day' AND is_winner=1) |
| `league_monthly_activity_medal` | Dedicated | Court of the king | Monthly activity medal | League | League period label | player_league_award (league_kind='activity' AND period_type='month' AND finish_rank<=3) |
| `league_weekly_activity_medal` | Dedicated | Siege survivor | Weekly activity medal | League | League period label | player_league_award (league_kind='activity' AND period_type='week' AND finish_rank<=3) |
| `league_weekly_points_medal` | Dedicated | Honours of the week | Weekly points medal | League | League period label | player_league_award (league_kind='points' AND period_type='week' AND finish_rank<=3) |
| `league_wins_10` | Dedicated | League debutante | 10 career league wins | League | League period label | player_league_totals.wins>=10 |
| `lifetime_rivalry` | Dedicated | Lifetime rivalry | 50th rated game vs same opponent | Game | Scoreline (anchor game) | player_matchup_summary |
| `marathon_day` | Dedicated | Marathon day | 10 rated games in one UTC day | Game | Scoreline (anchor game) | player_period_games day max |
| `marathoner_250` | Dedicated | Marathoner | 250 rated games | Game | Scoreline (anchor game) | playertable |
| `massive_upset` | Dedicated | Massive upset | Beat opponent 500+ higher (pre-game) | Game | Scoreline (anchor game) | ratedresults pre-game ratings |
| `millennium_merchant_1000` | Dedicated | Millennium merchant | 1,000 rated games | Game | Scoreline (anchor game) | playertable |
| `minimalist` | Dedicated | Minimalist | Won 1–0 | Game | Scoreline (anchor game) | ratedresults any game |
| `moment_of_glory` | Dedicated | Moment of glory | Won daily **points** league | League | League period label | player_league_award (daily points win) |
| `newbie_welcomer` | Dedicated | Newbie welcomer | You were first rated opponent in someone’s debut | Game | Scoreline (anchor game) | chronological debut opponent |
| `nightmare_day` | Dedicated | Nightmare day | Lost all games in UTC day (min 5) | Games | Nightmare day — all losses (5+ rated games that UTC day) | chronological UTC day |
| `peace_streak` | Dedicated | Peace streak | 3 draws in a row | Game | Scoreline (anchor game) | chronological 3 draws row |
| `perfect_day` | Dedicated | Perfect day | Won all games in UTC day (min 5) | Games | All wins that UTC day (5+ rated games). | chronological UTC day |
| `rare_blank` | Dedicated | Rare blank | 0 goals in a game after 50+ career games | Game | Scoreline (anchor game) | chronological |
| `regular_customer` | Dedicated | Regular customer | 10 wins vs same opponent | Game | Scoreline (anchor game) | player_matchup_summary max vs one opponent |
| `ten_culprits` | Dedicated | Rogues' gallery | 10 distinct culprits (losses) | Game | Scoreline (anchor game) | playertable |
| `ten_draws` | Dedicated | Draw collector | 10 career draws | Game | Scoreline (anchor game) | playertable |
| `ten_wins` | Dedicated | Ten up | 10 career wins | Game | Scoreline (anchor game) | playertable |
| `ten_wins_straight` | Dedicated | Ten straight | 10 wins in a row | Game | Scoreline (anchor game) | playertable longest streak proxy |
| `thousand_goal_club` | Dedicated | Thousand-goal club | 1,000 career goals | Game | Scoreline (anchor game) | playertable |
| `twenty_five_victims` | Dedicated | Quarter century of victims | 25 distinct victims | Game | Scoreline (anchor game) | playertable |
| `weekly_regular` | Dedicated | Weekly regular | ≥1 rated game every week for 3 consecutive months | Game | Scoreline (anchor game) | chronological ~13 weeks |
| `wide_net` | Dedicated | Wide net | 25 unique opponents | Game | Scoreline (anchor game) | playertable |
| `win_hat_trick` | Dedicated | Win hat-trick | 3 wins in a row | Game | Scoreline (anchor game) | playertable longest streak proxy |
| `year_round` | Dedicated | Year-round | Rated game in 12 consecutive calendar months | Game | Scoreline (anchor game) | chronological 12 consec months |
| `brace` | Aspirational | Brace | 2+ goals in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `cold_streak` | Aspirational | Cold streak | 5 losses in a row | Game | Scoreline (anchor game) | playertable longest streak proxy |
| `debut` | Aspirational | Debut | First rated game | Game | Scoreline (anchor game) | playertable |
| `entered_arena` | Aspirational | Entered the arena | Registered and entered the lobby | — | Joined the ladder | playertable JoinDate (register = enter lobby) |
| `established_20` | Aspirational | Established | 20 rated games | Game | Scoreline (anchor game) | playertable |
| `first_goal` | Aspirational | First goal | First career goal | Game | Scoreline (anchor game) | playertable |
| `first_handshake` | Aspirational | First handshake | First draw | Game | Scoreline (anchor game) | playertable |
| `first_shutout` | Aspirational | Clean sheet | First clean sheet | Game | Scoreline (anchor game) | playertable |
| `first_victory` | Aspirational | First victory | First win | Game | Scoreline (anchor game) | playertable |
| `five_goal_frenzy` | Aspirational | Five-goal frenzy | 5+ goals in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `hard_lesson` | Aspirational | Hard lesson | Lost by 10+ margin | Game | Scoreline (anchor game) | ratedresults any game |
| `hat_trick` | Aspirational | Hat-trick | 3+ goals in one game | Game | Scoreline (anchor game) | ratedresults any game |
| `hot_day` | Aspirational | Hot day | 5 rated games in one UTC day | Game | Scoreline (anchor game) | player_period_games day max |
| `league_daily_activity_medal` | Aspirational | Honour board | Daily activity medal | League | League period label | player_league_award (league_kind='activity' AND period_type='day' AND finish_rank<=3) |
| `league_daily_points_medal` | Aspirational | Almost the headline | Daily points medal | League | League period label | player_league_award (league_kind='points' AND period_type='day' AND finish_rank<=3) |
| `on_the_scoresheet` | Aspirational | On the scoresheet | Scored in 10 consecutive games | Game | Scoreline (anchor game) | chronological 10 scored in row |
| `persistence` | Aspirational | Through the swamp | 10 rated games | Game | Scoreline (anchor game) | playertable NumberGames>=10 |
| `ten_match_saga` | Aspirational | Ten-match saga | 10th rated game vs same opponent | Game | Scoreline (anchor game) | player_matchup_summary |
| `ten_opponents` | Aspirational | First roster | 10 unique opponents | Game | Scoreline (anchor game) | playertable |
| `victim_of_commerce` | Aspirational | Victim of commerce | Conceded 10+ goals | Game | Scoreline (anchor game) | ratedresults any game |
| `welcome_to_the_ladder` | Aspirational | Welcome to the ladder | First loss | Game | Scoreline (anchor game) | playertable |
| `win_drought` | Aspirational | Win drought | 10 games without a win | Game | Scoreline (anchor game) | playertable longest streak proxy |
