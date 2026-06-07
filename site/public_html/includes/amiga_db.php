<?php
/**
 * Amiga realm read-path SQL — joins ground + derived tables into ratedresults-shaped rows.
 */
declare(strict_types=1);

/** ORDER BY fragment for game chronology walks (matches replay.py GAME_SELECT). */
function amiga_game_chronology_order_sql(string $direction = 'ASC'): string
{
    $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    return "g.game_date {$dir}, g.id {$dir}";
}

/** Subquery alias `r` with column names compatible with legacy ratedresults consumers. */
function amiga_rated_games_from_sql(): string
{
    return <<<'SQL'
FROM (
    SELECT
        g.id,
        g.game_date AS `Date`,
        g.player_a_id AS idA,
        pa.name AS NameA,
        g.player_b_id AS idB,
        pb.name AS NameB,
        g.tournament_id,
        g.phase,
        g.goals_a AS GoalsA,
        g.goals_b AS GoalsB,
        gr.rating_a AS RatingA,
        gr.rating_b AS RatingB,
        gr.rating_difference AS RatingDifference,
        gr.home_win AS HomeWin,
        gr.draw AS Draw,
        gr.away_win AS AwayWin,
        gr.dd_player_a AS DDPlayerA,
        gr.dd_player_b AS DDPlayerB,
        gr.cs_player_a AS CSPlayerA,
        gr.cs_player_b AS CSPlayerB,
        gr.expected_score_a AS ExpectedScoreA,
        gr.expected_score_b AS ExpectedScoreB,
        gr.actual_score AS ActualScore,
        gr.adjustment_a AS AdjustmentA,
        gr.adjustment_b AS AdjustmentB,
        gr.new_rating_a AS NewRatingA,
        gr.new_rating_b AS NewRatingB,
        gr.sum_of_goals AS SumOfGoals,
        gr.goal_difference AS GoalDifference,
        gr.winner_id AS WinnerID,
        t.name AS tournament_name
    FROM amiga_games g
    INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id
    INNER JOIN amiga_players pa ON pa.id = g.player_a_id
    INNER JOIN amiga_players pb ON pb.id = g.player_b_id
    LEFT JOIN tournaments t ON t.id = g.tournament_id
) r
SQL;
}

/** Ground + derived player row with playertable-shaped column names. */
function amiga_player_base_from_sql(): string
{
    return <<<'SQL'
FROM amiga_players p
INNER JOIN amiga_player_stats s ON s.player_id = p.id
SQL;
}
