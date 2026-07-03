<?php
/**
 * Leaderboard column header tooltips (hub wings ranked1–5, 7, 10, league honours).
 *
 * Supplemental context only — do not repeat visible labels; see docs/design-direction.md
 * and docs/k2-table-and-games-plan.md § `k2-table.js` Contract.
 */
declare(strict_types=1);

function k2_lb_help_elo_rating(): string
{
    return 'We use standard Elo with a fixed K-factor of 32 for every player. Individual game pages show the expected score and rating-change calculation.';
}

function k2_lb_elo_column_tooltip_label(): string
{
    return 'Elo rating';
}

/** data-k2-tooltip-label + data-k2-help for career/snapshot Elo columns (header text: Elo). */
function k2_lb_elo_column_help_attrs(?string $helpBody = null): string
{
    $body = $helpBody ?? k2_lb_help_elo_rating();

    return ' data-k2-tooltip-label="' . htmlspecialchars(k2_lb_elo_column_tooltip_label(), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-k2-help="' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '"';
}

function k2_lb_help_elo_rating_status(): string
{
    return k2_lb_help_elo_rating()
        . ' This leaderboard includes all active online players in the past year. The complete leaderboards are in the leaderboards section.';
}

function k2_lb_help_games(): string
{
    return 'Rated games you have played (career).';
}

function k2_lb_help_peak(): string
{
    return 'Your career high Elo after 20 rated games.';
}

function k2_lb_help_peak_elo_rank(): string
{
    return 'Your best career ladder rank (lowest rank number). First attainment wins on ties.';
}

function k2_lb_help_peak_rating_date(): string
{
    return 'Tournament day when your peak rating was first reached (event finalize, not per-game).';
}

function k2_lb_help_online_peak_rating_date(): string
{
    return 'UTC day when your career peak rating was reached (rated game date).';
}

function k2_lb_help_peak_elo_rank_date(): string
{
    return 'Tournament day when your peak rank was first reached.';
}

function k2_lb_help_nadir(): string
{
    return 'Your career low Elo after 20 rated games. Dash (—) until then.';
}

function k2_lb_help_opponent_avg(): string
{
    return 'Average Elo of opponents you have faced (career).';
}

function k2_lb_help_highest_victim(): string
{
    return 'Highest-rated opponent you have beaten (their pre-game Elo on that win).';
}

function k2_lb_help_lowest_culprit(): string
{
    return 'Lowest-rated opponent you have lost to (their pre-game Elo on that loss).';
}

/** Goals wing (ranked2). Short headers may use GF/GA; tooltip bodies use scored/conceded and say career vs one game. */
function k2_lb_help_goals_scored(): string
{
    return 'Total goals scored across your rated career.';
}

function k2_lb_help_goals_conceded(): string
{
    return 'Total goals conceded across your rated career.';
}

function k2_lb_help_goals_scored_avg(): string
{
    return 'Average goals scored per rated game.';
}

function k2_lb_help_goals_conceded_avg(): string
{
    return 'Average goals conceded per rated game.';
}

function k2_lb_help_goal_ratio(): string
{
    return 'Career goals scored divided by career goals conceded.';
}

function k2_lb_help_total_goals_per_game(): string
{
    return 'Average combined goals per rated game against this opponent (your goals plus theirs).';
}

function k2_lb_help_most_scored(): string
{
    return 'Most goals scored in one rated game.';
}

function k2_lb_help_most_conceded(): string
{
    return 'Most goals conceded in one rated game.';
}

function k2_lb_help_least_scored(): string
{
    return 'Fewest goals scored in one rated game.';
}

function k2_lb_help_least_conceded(): string
{
    return 'Fewest goals conceded in one rated game.';
}

function k2_lb_help_win_margin(): string
{
    return 'Your largest winning margin in one game.';
}

function k2_lb_help_loss_margin(): string
{
    return 'Your heaviest defeat in one game.';
}

function k2_lb_help_biggest_draw(): string
{
    return 'Your highest-scoring draw.';
}

function k2_lb_help_goal_sum(): string
{
    return 'Most total goals in one game you played in.';
}

function k2_lb_help_least_goal_sum(): string
{
    return 'Fewest total goals in one game you played in.';
}

/** DD / clean sheets wing (ranked3). */
function k2_lb_help_double_digits(): string
{
    return 'Games where you scored 10 or more goals.';
}

function k2_lb_help_clean_sheets(): string
{
    return 'Games where you held the opponent scoreless.';
}

function k2_lb_help_double_digits_ratio(): string
{
    return 'Share of your games where you scored 10 or more goals.';
}

function k2_lb_help_clean_sheets_ratio(): string
{
    return 'Share of your games where you held the opponent scoreless.';
}

function k2_lb_help_double_digits_conceded(): string
{
    return 'Games where you conceded 10 or more goals.';
}

function k2_lb_help_clean_sheets_conceded(): string
{
    return 'Games where you scored no goals.';
}

function k2_lb_help_double_digits_conceded_ratio(): string
{
    return 'Share of your games where you conceded 10 or more goals.';
}

function k2_lb_help_clean_sheets_conceded_ratio(): string
{
    return 'Share of your games where you scored no goals.';
}

/** Streaks wing (ranked4) — career longest result streaks. */
function k2_lb_help_streak_wins(): string
{
    return 'Your longest run of consecutive wins.';
}

function k2_lb_help_streak_undefeated(): string
{
    return 'Your longest run without a loss (wins and draws).';
}

function k2_lb_help_streak_draws(): string
{
    return 'Your longest run of consecutive draws.';
}

function k2_lb_help_streak_decided(): string
{
    return 'Your longest run without a draw (wins and losses only).';
}

function k2_lb_help_streak_losses(): string
{
    return 'Your longest run of consecutive losses.';
}

function k2_lb_help_streak_win_drought(): string
{
    return 'Your longest run without a win (draws and losses).';
}

/** League honours wing. */
function k2_lb_help_league_podium(): string
{
    return 'Your top-three league finishes (gold + silver + bronze).';
}

function k2_lb_help_league_silver(): string
{
    return 'Second-place league finishes you earned.';
}

function k2_lb_help_league_bronze(): string
{
    return 'Third-place league finishes you earned.';
}

/** Activity peak tables (ranked8 longevity). */
function k2_lb_help_first_rated_game(): string
{
    return 'Date of your first rated game.';
}

function k2_lb_help_last_rated_game(): string
{
    return 'Date of your latest rated game.';
}

function k2_lb_help_rated_span_days(): string
{
    return 'Days between your first and latest rated games.';
}

function k2_lb_help_participation_longevity(): string
{
    return 'Calendar span from your first rated game through your latest rated game, both days counted (last minus first, plus one). Not the same as active days — you can have gaps in between.';
}

/** HoF / value-cell tooltip when longevity is shown as a bare day count. */
function k2_lb_help_longevity_value_count(int $days): string
{
    $unit = $days === 1 ? 'day' : 'days';

    return $days . ' calendar ' . $unit
        . ' from first rated game through latest rated game, both endpoints counted.'
        . ' Gaps with no rated games still count toward this span (unlike active days).';
}

function k2_lb_help_active_days(): string
{
    return 'Distinct UTC calendar days with at least one rated game.';
}

function k2_lb_help_active_weeks(): string
{
    return 'Distinct UTC weeks (Monday–Sunday) with at least one rated game.';
}

function k2_lb_help_active_months(): string
{
    return 'Distinct calendar months with at least one rated game.';
}

function k2_lb_help_active_years(): string
{
    return 'Distinct calendar years with at least one rated game.';
}

/** Victims & Culprits wing (ranked5) — plain “you” copy; shared tie line on single-game records. */
function k2_lb_help_victims_wing_tie(): string
{
    return 'In a tie, the first offender gets the credit.';
}

function k2_lb_help_opponents(): string
{
    return 'Different players you have faced in rated games.';
}

function k2_lb_help_victims(): string
{
    return 'Different players you have beaten at least once.';
}

function k2_lb_help_culprits(): string
{
    return 'Different players who have beaten you at least once.';
}

function k2_lb_help_dd_victims(): string
{
    return 'Victims you scored 10 or more against at least once.';
}

function k2_lb_help_cs_victims(): string
{
    return 'Victims you shut out at least once.';
}

function k2_lb_help_dd_culprits(): string
{
    return 'Culprits who scored 10 or more against you at least once.';
}

function k2_lb_help_cs_culprits(): string
{
    return 'Culprits who shut you out at least once.';
}

function k2_lb_help_mgc_victims(): string
{
    return 'Victims whose most conceded goals game was against you. ' . k2_lb_help_victims_wing_tie();
}

function k2_lb_help_bl_victims(): string
{
    return 'Victims whose biggest loss game was against you. ' . k2_lb_help_victims_wing_tie();
}

function k2_lb_help_mgs_culprits(): string
{
    return 'Culprits whose most scored goals game was against you. ' . k2_lb_help_victims_wing_tie();
}

function k2_lb_help_bw_culprits(): string
{
    return 'Culprits whose biggest win game was against you. ' . k2_lb_help_victims_wing_tie();
}

function k2_lb_help_win_ratio(): string
{
    return 'Your rated wins ÷ your rated games.';
}

function k2_lb_help_draw_ratio(): string
{
    return 'Your rated draws ÷ your rated games.';
}

function k2_lb_help_loss_ratio(): string
{
    return 'Your rated losses ÷ your rated games.';
}

function k2_lb_help_milestones_total(): string
{
    return 'Milestone unlocks you have earned (all tiers).';
}

/** Amiga tournament honours wing (`/amiga/leaderboards/tournament-honours.php`). */
function k2_lb_help_amiga_tournament_events(): string
{
    return 'Tournament events you entered.';
}

function k2_lb_help_amiga_event_gold(): string
{
    return 'Tournament wins.';
}

function k2_lb_help_amiga_event_silver(): string
{
    return 'Tournament 2nd-place finishes.';
}

function k2_lb_help_amiga_event_bronze(): string
{
    return 'Tournament 3rd-place finishes.';
}

function k2_lb_help_amiga_event_podiums(): string
{
    return 'Top-three tournament finishes.';
}

function k2_lb_help_amiga_perfect_events(): string
{
    return 'Tournaments where you won all your games.';
}

/** Amiga career goals LB (`/amiga/leaderboards/goals.php`). Omits "rated" (Amiga realm convention). */
function k2_lb_help_amiga_games(): string
{
    return 'Games you have played (career).';
}

function k2_lb_help_amiga_goals_scored(): string
{
    return 'Total goals scored across your career.';
}

function k2_lb_help_amiga_goals_conceded(): string
{
    return 'Total goals conceded across your career.';
}

function k2_lb_help_amiga_goals_scored_avg(): string
{
    return 'Average goals scored per game.';
}

function k2_lb_help_amiga_goals_conceded_avg(): string
{
    return 'Average goals conceded per game.';
}

function k2_lb_help_amiga_most_scored(): string
{
    return 'Most goals scored in one game.';
}

function k2_lb_help_amiga_most_conceded(): string
{
    return 'Most goals conceded in one game.';
}

function k2_lb_help_amiga_wc_perfect_events(): string
{
    return 'World Cups where you won all your games.';
}

function k2_lb_help_amiga_wc_played(): string
{
    return 'World Cups you entered.';
}

function k2_lb_help_amiga_wc_gold(): string
{
    return 'World Cup wins.';
}

function k2_lb_help_amiga_wc_silver(): string
{
    return 'World Cup 2nd-place finishes.';
}

function k2_lb_help_amiga_wc_bronze(): string
{
    return 'World Cup 3rd-place finishes.';
}

function k2_lb_help_amiga_wc_podiums(): string
{
    return 'Top-three World Cup finishes.';
}

function k2_lb_help_wins(): string
{
    return 'Rated wins in World Cup games.';
}

function k2_lb_help_draws(): string
{
    return 'Rated draws in World Cup games.';
}

function k2_lb_help_losses(): string
{
    return 'Rated losses in World Cup games.';
}

function k2_lb_help_amiga_wc_match_points(): string
{
    return 'Match points in all World Cup games — 3 for a win, 1 for a draw, 0 for a loss.';
}

function k2_lb_help_amiga_wc_games(): string
{
    return 'World Cup games.';
}

function k2_lb_help_amiga_wc_points_per_game(): string
{
    return 'Match points per World Cup game (Pts ÷ games).';
}

function k2_lb_help_amiga_wc_win_rate(): string
{
    return 'Draws count as half a win: (wins + half of draws) divided by games.';
}

function k2_lb_help_amiga_wc_goal_difference(): string
{
    return 'Goals for minus goals against in World Cup games.';
}

function k2_lb_help_amiga_wc_goal_difference_per_game(): string
{
    return 'Goal difference per World Cup game (GD ÷ games).';
}

/** Amiga WC players LB — Goals wing. Player grain; World Cup games only. */
function k2_lb_help_amiga_wc_goals_scored(): string
{
    return 'Goals scored in World Cup games.';
}

function k2_lb_help_amiga_wc_goals_conceded(): string
{
    return 'Goals conceded in World Cup games.';
}

function k2_lb_help_amiga_wc_goals_scored_avg(): string
{
    return 'Average goals scored per World Cup game.';
}

function k2_lb_help_amiga_wc_goals_conceded_avg(): string
{
    return 'Average goals conceded per World Cup game.';
}

function k2_lb_help_amiga_wc_goal_ratio(): string
{
    return 'World Cup goals scored divided by World Cup goals conceded.';
}

function k2_lb_help_amiga_wc_most_goals_scored(): string
{
    return 'Most goals scored in one World Cup game.';
}

function k2_lb_help_amiga_wc_most_goals_conceded(): string
{
    return 'Most goals conceded in one World Cup game.';
}

function k2_lb_help_amiga_wc_win_margin(): string
{
    return 'Your largest winning margin in one World Cup game.';
}

function k2_lb_help_amiga_wc_loss_margin(): string
{
    return 'Your heaviest defeat in one World Cup game.';
}

function k2_lb_help_amiga_wc_goal_sum(): string
{
    return 'Most total goals in one World Cup game you played in.';
}

function k2_lb_help_amiga_wc_biggest_draw(): string
{
    return 'Your highest-scoring draw in World Cup games.';
}

/** Amiga WC players LB — DDs & CSs wing. Player grain; World Cup games only. */
function k2_lb_help_amiga_wc_double_digits(): string
{
    return 'World Cup games where you scored 10 or more goals.';
}

function k2_lb_help_amiga_wc_clean_sheets(): string
{
    return 'World Cup games where you held the opponent scoreless.';
}

function k2_lb_help_amiga_wc_double_digits_ratio(): string
{
    return 'Share of your World Cup games where you scored 10 or more goals.';
}

function k2_lb_help_amiga_wc_clean_sheets_ratio(): string
{
    return 'Share of your World Cup games where you held the opponent scoreless.';
}

function k2_lb_help_amiga_wc_double_digits_conceded(): string
{
    return 'World Cup games where you conceded 10 or more goals.';
}

function k2_lb_help_amiga_wc_clean_sheets_conceded(): string
{
    return 'World Cup games where you scored no goals.';
}

function k2_lb_help_amiga_wc_double_digits_conceded_ratio(): string
{
    return 'Share of your World Cup games where you conceded 10 or more goals.';
}

function k2_lb_help_amiga_wc_clean_sheets_conceded_ratio(): string
{
    return 'Share of your World Cup games where you scored no goals.';
}

/** Amiga WC players LB — Opponents wing. Player grain; World Cup games only. */
function k2_lb_help_amiga_wc_opponents(): string
{
    return 'Different players you have faced in World Cup games.';
}

function k2_lb_help_amiga_wc_victims(): string
{
    return 'Different players you have beaten at least once in World Cup games.';
}

function k2_lb_help_amiga_wc_dd_victims(): string
{
    return 'Opponents you scored 10 or more against at least once in World Cup games.';
}

function k2_lb_help_amiga_wc_cs_victims(): string
{
    return 'Opponents you shut out at least once in World Cup games.';
}

function k2_lb_help_amiga_wc_opponent_countries_faced(): string
{
    return 'Distinct opponent countries you faced in World Cup games, including your own country.';
}

function k2_lb_help_amiga_wc_opponent_countries_beaten(): string
{
    return 'Distinct countries that you have beaten an opponent from in World Cup games, including your own country.';
}

function k2_lb_help_amiga_peak_year_games(): string
{
    return 'Most games in a single calendar year.';
}

function k2_lb_help_amiga_peak_year_tournaments(): string
{
    return 'Most tournaments played in a single calendar year.';
}

function k2_lb_help_amiga_countries_played_in(): string
{
    return 'Distinct host countries of tournaments you entered, including your own country.';
}

function k2_lb_help_amiga_opponent_countries_faced(): string
{
    return 'Distinct opponent countries you faced, including your own country.';
}

function k2_lb_help_amiga_opponent_countries_beaten(): string
{
    return 'Distinct countries that you have beaten an opponent from, including your own country.';
}

/** Amiga Opponents country grain W/D/L table. */
function k2_lb_help_amiga_opponents_country(): string
{
    return 'Opponent nationality bucket — includes games vs compatriots when this row is your own country.';
}

function k2_lb_help_amiga_opponents_country_games(): string
{
    return 'Rated games vs opponents from this country. Opens your games list filtered to this opponent country.';
}

function k2_lb_help_amiga_opponents_country_wins(): string
{
    return 'Wins vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_draws(): string
{
    return 'Draws vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_losses(): string
{
    return 'Losses vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_win_ratio(): string
{
    return 'Share of games won vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_draw_ratio(): string
{
    return 'Share of games drawn vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_loss_ratio(): string
{
    return 'Share of games lost vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_performance_rating(): string
{
    return 'The Elo level implied by your results vs opponents from this country (frozen pre-game opponent ratings). Needs at least 2 games; shows ∞ for a perfect win record (all wins).';
}

function k2_lb_help_amiga_opponents_country_goals_for(): string
{
    return 'Goals scored vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_goals_against(): string
{
    return 'Goals conceded vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_double_digits(): string
{
    return 'Games where you scored 10+ goals vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_clean_sheets(): string
{
    return 'Games where you conceded 0 goals vs opponents from this country.';
}

function k2_lb_help_amiga_opponents_country_double_digits_conceded(): string
{
    return 'Games where opponents from this country scored 10+ against you.';
}

function k2_lb_help_amiga_opponents_country_clean_sheets_conceded(): string
{
    return 'Games where opponents from this country failed to score against you.';
}

/** Amiga country Rivals nation-pair tables. */
function k2_lb_help_amiga_country_rivals_rival(): string
{
    return 'Directed rival nation (hero → rival). Domestic compatriot matchups (hero vs hero) are excluded from Rivals.';
}

function k2_lb_help_amiga_country_rivals_games(): string
{
    return 'Rated games between nationals from the hero country and this rival nation. Opens All games filtered to this nation pair.';
}

function k2_lb_help_amiga_country_rivals_wins(): string
{
    return 'Wins by players from the hero country vs nationals from this rival.';
}

function k2_lb_help_amiga_country_rivals_draws(): string
{
    return 'Draws between nationals from the hero country and this rival.';
}

function k2_lb_help_amiga_country_rivals_losses(): string
{
    return 'Losses by players from the hero country vs nationals from this rival.';
}

function k2_lb_help_amiga_country_rivals_win_ratio(): string
{
    return 'Share of games won by the hero country vs this rival.';
}

function k2_lb_help_amiga_country_rivals_draw_ratio(): string
{
    return 'Share of games drawn between the hero country and this rival.';
}

function k2_lb_help_amiga_country_rivals_loss_ratio(): string
{
    return 'Share of games lost by the hero country vs this rival.';
}

function k2_lb_help_amiga_country_rivals_performance_rating(): string
{
    return 'Elo level implied by hero-country results vs this rival (frozen pre-game opponent ratings). Needs at least 2 games; shows ∞ for a perfect win record.';
}

function k2_lb_help_amiga_country_rivals_goals_for(): string
{
    return 'Goals scored by hero-country nationals vs this rival.';
}

function k2_lb_help_amiga_country_rivals_goals_against(): string
{
    return 'Goals conceded by hero-country nationals vs this rival.';
}

function k2_lb_help_amiga_country_rivals_double_digits(): string
{
    return 'Games where hero-country nationals scored 10+ vs this rival.';
}

function k2_lb_help_amiga_country_rivals_clean_sheets(): string
{
    return 'Games where hero-country nationals conceded 0 vs this rival.';
}

function k2_lb_help_amiga_country_rivals_double_digits_conceded(): string
{
    return 'Games where rival nationals scored 10+ against the hero country.';
}

function k2_lb_help_amiga_country_rivals_clean_sheets_conceded(): string
{
    return 'Games where rival nationals failed to score against the hero country.';
}

/** World Cups hub - Country stats (`amiga_wc_countries_table.php`). Nation grain. */
function k2_lb_help_amiga_wc_country_players(): string
{
    return 'Players from this country who have taken part in at least one World Cup.';
}

function k2_lb_help_amiga_wc_country_wcs(): string
{
    return 'World Cups in which at least one player from this country competed (each event counted once).';
}

function k2_lb_help_amiga_wc_country_gold(): string
{
    return 'World Cup wins for this country.';
}

function k2_lb_help_amiga_wc_country_silver(): string
{
    return 'World Cup 2nd-place finishes for this country.';
}

function k2_lb_help_amiga_wc_country_bronze(): string
{
    return 'World Cup 3rd-place finishes for this country.';
}

function k2_lb_help_amiga_wc_country_podiums(): string
{
    return 'Total World Cup podium finishes for this country (gold + silver + bronze).';
}

function k2_lb_help_amiga_wc_country_games(): string
{
    return 'Player-games by nationals in World Cups - each side of a match counts once, '
        . 'so compatriot vs compatriot adds two toward this country.';
}

/** WC country Results wing — natural copy; no "player-games" (see `amiga_wc_countries_render_results`). */
function k2_lb_help_amiga_wc_country_results_games(): string
{
    return 'World Cup games played by this country\'s players. Each side of a match counts once.';
}

function k2_lb_help_amiga_wc_country_results_points_per_game(): string
{
    return 'Average match points per game.';
}

function k2_lb_help_amiga_wc_country_results_win_rate(): string
{
    return 'Draws count as half a win: (wins + half of draws) ÷ games.';
}

function k2_lb_help_amiga_wc_country_results_avg_opponent_rating(): string
{
    return 'Average opponent Elo in this country\'s World Cup games.';
}

function k2_lb_help_amiga_wc_country_results_performance_rating(): string
{
    return 'Elo level implied by this country\'s World Cup results.';
}

function k2_lb_help_amiga_wc_country_results_games_per_player(): string
{
    return 'Games divided by the number of players from this country.';
}

function k2_lb_help_amiga_wc_country_results_domestic_games(): string
{
    return 'Games where both players are from this country.';
}

function k2_lb_help_amiga_wc_country_results_domestic_share(): string
{
    return 'Domestic games as a share of all games for this country.';
}

function k2_lb_help_amiga_wc_country_results_international_games(): string
{
    return 'Games where the players are from two different countries.';
}

function k2_lb_help_amiga_wc_country_results_international_share(): string
{
    return 'International games as a share of all games for this country.';
}

function k2_lb_help_amiga_wc_country_results_games_share(): string
{
    return 'This country\'s games as a share of all World Cup games.';
}

function k2_lb_help_amiga_wc_country_wins(): string
{
    return 'Wins by nationals in World Cup games (summed across all players from this country).';
}

function k2_lb_help_amiga_wc_country_draws(): string
{
    return 'Draws by nationals in World Cup games (summed across all players from this country).';
}

function k2_lb_help_amiga_wc_country_losses(): string
{
    return 'Losses by nationals in World Cup games (summed across all players from this country).';
}

function k2_lb_amiga_rating_delta_tooltip_label(): string
{
    return 'Rating change (time travel mode)';
}

function k2_lb_help_amiga_rating_delta(): string
{
    return 'Change in Elo rating since the previous snapshot in the chosen mode (year, month, or event).';
}

/** data-k2-tooltip-label + data-k2-help for time-travel Δ column (header text: Δ). */
function k2_lb_amiga_rating_delta_column_help_attrs(): string
{
    return ' data-k2-tooltip-label="' . htmlspecialchars(k2_lb_amiga_rating_delta_tooltip_label(), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-k2-help="' . htmlspecialchars(k2_lb_help_amiga_rating_delta(), ENT_QUOTES, 'UTF-8') . '"';
}

function k2_lb_help_amiga_wc_start_rating_delta(): string
{
    return 'Change in Elo rating since the start of the most recent tournament.';
}

/**
 * @param array{id: int, name: string, event_date: string, chrono: float} $lastWc
 */
function k2_lb_help_amiga_wc_start_rating_delta_html(array $lastWc): string
{
    require_once __DIR__ . '/amiga_rating_history_lib.php';

    $nameHtml = '<span class="k2-link-star">'
        . htmlspecialchars(trim($lastWc['name']), ENT_QUOTES, 'UTF-8') . '</span>';
    $dateHtml = '<span class="k2-link-star">'
        . htmlspecialchars(amiga_rating_history_format_event_date_label($lastWc), ENT_QUOTES, 'UTF-8') . '</span>';

    return 'Change in Elo rating since the start of the most recent '
        . $nameHtml . ' which took place on ' . $dateHtml . '.';
}

/**
 * @param ?array{id: int, name: string, event_date: string, chrono: float} $lastWc
 */
function k2_lb_amiga_wc_start_rating_delta_column_help_attrs(?array $lastWc = null): string
{
    $attrs = ' data-k2-tooltip-label="Rating change"';

    if ($lastWc !== null) {
        return $attrs
            . ' data-k2-help-html="1"'
            . ' data-k2-help="' . htmlspecialchars(k2_lb_help_amiga_wc_start_rating_delta_html($lastWc), ENT_QUOTES, 'UTF-8') . '"';
    }

    return $attrs
        . ' data-k2-help="' . htmlspecialchars(k2_lb_help_amiga_wc_start_rating_delta(), ENT_QUOTES, 'UTF-8') . '"';
}

function k2_lb_help_amiga_wc_country_points(): string
{
    return 'Match points from national World Cup games - 3 for a win, 1 for a draw, 0 for a loss.';
}

function k2_lb_help_amiga_wc_country_points_per_game(): string
{
    return 'National match points divided by national player-games (Pts / games).';
}

function k2_lb_help_amiga_wc_country_win_rate(): string
{
    return 'Draws count as half a win: (wins + half of draws) divided by player-games.';
}

function k2_lb_help_amiga_wc_country_avg_opponent_rating(): string
{
    return 'Average frozen opponent rating across all national player-games - a simple mean, not the performance rating.';
}

function k2_lb_help_amiga_wc_country_performance_rating(): string
{
    return 'Rating level implied by this country\'s combined World Cup results against the opponents its players faced '
        . '(frozen pre-game ratings). Shows ∞ for a perfect win record (all wins, ≥2 games).';
}

function k2_lb_help_amiga_wc_country_points_per_realm_wc(): string
{
    return 'Country points divided by every World Cup held so far - not only the World Cups this country participated in.';
}

function k2_lb_help_amiga_wc_country_participations(): string
{
    return 'Total national entries in World Cups - one player in one event counts 1; '
        . 'seven players from the same country in the same event counts 7.';
}

function k2_lb_help_amiga_countries_wc_entries_index(): string
{
    return k2_lb_help_amiga_wc_country_participations();
}

function k2_lb_help_amiga_countries_wc_players_index(): string
{
    return 'Rated players from this country who have entered at least one World Cup.';
}

function k2_lb_help_amiga_countries_games_per_player(): string
{
    return 'Career rated games divided by the number of distinct players from this country.';
}

function k2_lb_help_amiga_wc_country_participations_per_player(): string
{
    return 'WC participations divided by the number of distinct players from this country.';
}

function k2_lb_help_amiga_wc_country_games_per_player(): string
{
    return 'National player-games divided by the number of distinct players from this country.';
}

function k2_lb_help_amiga_wc_country_domestic_games(): string
{
    return 'Player-games where both competitors are from this country (compatriot vs compatriot).';
}

function k2_lb_help_amiga_wc_country_domestic_share(): string
{
    return 'Domestic player-games as a share of all national player-games.';
}

function k2_lb_help_amiga_wc_country_international_games(): string
{
    return 'Player-games where the opponent is from a different country.';
}

function k2_lb_help_amiga_wc_country_international_share(): string
{
    return 'International player-games as a share of all national player-games.';
}

function k2_lb_help_amiga_wc_country_games_share(): string
{
    return 'This country\'s player-games as a share of all World Cup player-games.';
}

function k2_lb_help_amiga_wc_country_goals_share(): string
{
    return 'Goals scored by nationals as a share of all goals in World Cup games.';
}

function k2_lb_help_amiga_wc_country_goals_for(): string
{
    return 'Goals scored by nationals in World Cup games (summed across all players from this country).';
}

function k2_lb_help_amiga_wc_country_goals_against(): string
{
    return 'Goals conceded by nationals in World Cup games (summed across all players from this country).';
}

function k2_lb_help_amiga_wc_country_goal_difference(): string
{
    return 'National goals for minus goals against in World Cup games.';
}

function k2_lb_help_amiga_wc_country_goals_for_per_game(): string
{
    return 'National goals for divided by national player-games.';
}

function k2_lb_help_amiga_wc_country_goals_against_per_game(): string
{
    return 'National goals against divided by national player-games.';
}

function k2_lb_help_amiga_wc_country_goal_difference_per_game(): string
{
    return 'National goal difference divided by national player-games.';
}

function k2_lb_help_amiga_wc_country_goal_ratio(): string
{
    return 'National goals for divided by national goals against.';
}

function k2_lb_help_amiga_wc_country_most_goals_scored(): string
{
    return 'Most goals scored by any one national in a single World Cup game (best individual game, not the national total).';
}

function k2_lb_help_amiga_wc_country_most_goals_conceded(): string
{
    return 'Most goals conceded by any one national in a single World Cup game.';
}

function k2_lb_help_amiga_wc_country_biggest_win(): string
{
    return 'Largest winning margin by any national in one World Cup game.';
}

function k2_lb_help_amiga_wc_country_biggest_loss(): string
{
    return 'Largest losing margin by any national in one World Cup game.';
}

function k2_lb_help_amiga_wc_country_biggest_sum(): string
{
    return 'Most combined goals in one World Cup game involving a national (both sides added).';
}

function k2_lb_help_amiga_wc_country_biggest_draw(): string
{
    return 'Highest-scoring draw by any national (equal goals on both sides).';
}

function k2_lb_help_amiga_wc_country_double_digits(): string
{
    return 'Player-games where a national scored 10 or more goals (summed across all nationals).';
}

function k2_lb_help_amiga_wc_country_clean_sheets(): string
{
    return 'Player-games where a national held the opponent scoreless (summed across all nationals).';
}

function k2_lb_help_amiga_wc_country_double_digits_ratio(): string
{
    return 'Share of national player-games where a national scored 10 or more goals.';
}

function k2_lb_help_amiga_wc_country_clean_sheets_ratio(): string
{
    return 'Share of national player-games where a national held the opponent scoreless.';
}

function k2_lb_help_amiga_wc_country_double_digits_conceded(): string
{
    return 'Player-games where a national conceded 10 or more goals (summed across all nationals).';
}

function k2_lb_help_amiga_wc_country_clean_sheets_conceded(): string
{
    return 'Player-games where a national scored no goals (summed across all nationals).';
}

function k2_lb_help_amiga_wc_country_double_digits_conceded_ratio(): string
{
    return 'Share of national player-games where a national conceded 10 or more goals.';
}

function k2_lb_help_amiga_wc_country_clean_sheets_conceded_ratio(): string
{
    return 'Share of national player-games where a national scored no goals.';
}

function k2_lb_help_amiga_wc_country_opponent_countries_faced(): string
{
    return 'Distinct countries faced by any national in World Cup games. '
        . 'Includes this country when compatriots play each other.';
}

function k2_lb_help_amiga_wc_country_opponent_countries_beaten(): string
{
    return 'Distinct countries beaten at least once by a national in a World Cup game.';
}

function k2_lb_help_amiga_wc_country_opponents(): string
{
    return 'Distinct players faced by any national - each opponent counted once even if several compatriots played them.';
}

function k2_lb_help_amiga_wc_country_victims(): string
{
    return 'Distinct players beaten at least once by a national in a World Cup game.';
}

function k2_lb_help_amiga_wc_country_dd_victims(): string
{
    return 'Distinct opponents any national scored 10+ against at least once - a win is not required.';
}

function k2_lb_help_amiga_wc_country_cs_victims(): string
{
    return 'Distinct opponents any national held scoreless at least once - a win is not required.';
}
