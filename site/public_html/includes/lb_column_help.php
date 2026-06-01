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
    return 'Current Elo rating. KOOL uses standard Elo with a fixed K-factor of 32 for every player. Individual game pages show the expected score and rating-change calculation.';
}

function k2_lb_help_games(): string
{
    return 'Rated games you have played (career).';
}

function k2_lb_help_peak(): string
{
    return 'Your career high Elo after 20 rated games. Dash (—) until then.';
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

/** Goals wing (ranked2). */
function k2_lb_help_goals_scored(): string
{
    return 'Your career goals for.';
}

function k2_lb_help_goals_conceded(): string
{
    return 'Your career goals against.';
}

function k2_lb_help_goals_scored_avg(): string
{
    return 'Your goals for per rated game.';
}

function k2_lb_help_goals_conceded_avg(): string
{
    return 'Your goals against per rated game.';
}

function k2_lb_help_goal_ratio(): string
{
    return 'Your goals for divided by your goals against.';
}

function k2_lb_help_most_scored(): string
{
    return 'Most goals you scored in one game.';
}

function k2_lb_help_most_conceded(): string
{
    return 'Most goals you conceded in one game.';
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
