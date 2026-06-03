<?php
/**
 * Post-game formula defaults — keep aligned with scripts/ladder/constants.py.
 */
declare(strict_types=1);

const K2_POST_GAME_K_FACTOR = 32;
const K2_POST_GAME_START_RATING = 1600.0;

/** Draw outcome sentinel for WinnerID (matches Python / legacy C++). */
const K2_POST_GAME_WINNER_ID_DRAW = -1;

/** Personal extreme sentinels (scripts/ladder/constants.py / replay-v1 §5.2). */
const K2_POST_GAME_SENTINEL_LEAST_GOALS = 50;
const K2_POST_GAME_SENTINEL_LOWEST_RATING = 5000.0;
const K2_POST_GAME_SENTINEL_GOAL_RATIO = -1.0;

/** LastGame baseline after zero-derived (UTC). */
const K2_POST_GAME_LASTGAME_RESET = '1970-01-01 00:00:00';
