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
    return 'Your career high Elo after 20 rated games. Dash (—) until then.';
}

function k2_lb_help_peak_elo_rank(): string
{
    return 'Your best career ladder rank (lowest rank number). First attainment wins on ties.';
}

function k2_lb_help_peak_rating_date(): string
{
    return 'Tournament day when your peak rating was first reached (event finalize, not per-game).';
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
    return 'Mean Elo of opponents you have faced (career).';
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
    return 'Tournament events you entered (at least one rated game).';
}

function k2_lb_help_amiga_event_gold(): string
{
    return 'Tournament wins — holistic 1st place across the event (all tournament types).';
}

function k2_lb_help_amiga_event_silver(): string
{
    return 'Holistic 2nd-place tournament finishes.';
}

function k2_lb_help_amiga_event_bronze(): string
{
    return 'Holistic 3rd-place tournament finishes.';
}

function k2_lb_help_amiga_event_podiums(): string
{
    return 'Top-three tournament finishes (gold + silver + bronze).';
}

function k2_lb_help_amiga_wc_played(): string
{
    return 'World Cup events you entered.';
}

function k2_lb_help_amiga_wc_gold(): string
{
    return 'World Cup wins (holistic 1st place).';
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
    return 'World Cup top-three finishes (gold + silver + bronze).';
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
    return 'Match points from World Cup games only — 3 for a win, 1 for a draw, 0 for a loss.';
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

function k2_lb_help_amiga_peak_year_games(): string
{
    return 'Most rated games in a single calendar year (by tournament date).';
}

function k2_lb_help_amiga_peak_year_tournaments(): string
{
    return 'Most tournaments played in a single calendar year.';
}

function k2_lb_help_amiga_countries_played_in(): string
{
    return 'Distinct host countries of tournaments you entered, including your own country when set.';
}

function k2_lb_help_amiga_opponent_countries_faced(): string
{
    return 'Distinct opponent countries you faced, including your own country when set.';
}

function k2_lb_help_amiga_opponent_countries_beaten(): string
{
    return 'Distinct opponent countries where you have at least one win.';
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
    return 'World Cup wins by nationals - every medal counts, so two compatriots on the podium both add to this total.';
}

function k2_lb_help_amiga_wc_country_silver(): string
{
    return 'World Cup second-place finishes by nationals (summed across all players from this country).';
}

function k2_lb_help_amiga_wc_country_bronze(): string
{
    return 'World Cup third-place finishes by nationals (summed across all players from this country).';
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
    return 'Rating change';
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
    return 'Change in Elo rating since the start of the most recent World Cup.';
}

/** data-k2-tooltip-label + data-k2-help for present-day World Cup Δ column (header text: Δ). */
function k2_lb_amiga_wc_start_rating_delta_column_help_attrs(): string
{
    return ' data-k2-tooltip-label="' . htmlspecialchars(k2_lb_amiga_rating_delta_tooltip_label(), ENT_QUOTES, 'UTF-8') . '"'
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
        . '(frozen pre-game ratings).';
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
