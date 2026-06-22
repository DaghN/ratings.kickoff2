<?php
/**
 * V1 community stat fact registry (mirrors scripts/amiga/community_stat_registry.py).
 */
declare(strict_types=1);

const AMIGA_COMMUNITY_REALM_SLICE_KEY = '*';
const AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY = '*';

/** @return list<string> */
function amiga_community_headline_column_names(): array
{
    return [
        'NumberOfPlayers',
        'DifferentOpponentsAverage',
        'GamesPlayed',
        'GamesPlayedAverage',
        'NumberOfDecidedGames',
        'NumberOfDraws',
        'DecidedGamesRatio',
        'DrawsRatio',
        'GoalsScored',
        'GoalsPerGameAverage',
        'DoubleDigits',
        'CleanSheets',
        'DoubleDigitsRatio',
        'CleanSheetsRatio',
    ];
}
