<?php
/**
 * Rated-game adjustment formatting (game.php / Games tab / status recent games).
 *
 * @param array<string, mixed> $game ratedresults row (assoc keys: ActualScore, AdjustmentA, AdjustmentB, idA, idB, NameA, NameB)
 * @return array{adj: float, id: int, name: string}
 */
function k2_game_rating_adjustment_pick(array $game, string $side): array
{
    $actual = (float) ($game['ActualScore'] ?? -1);
    $adjA = (float) ($game['AdjustmentA'] ?? 0);
    $adjB = (float) ($game['AdjustmentB'] ?? 0);
    $idA = (int) ($game['idA'] ?? 0);
    $idB = (int) ($game['idB'] ?? 0);
    $nameA = (string) ($game['NameA'] ?? '');
    $nameB = (string) ($game['NameB'] ?? '');
    $wantWinner = $side !== 'loser';

    if (abs($actual - 1.0) < 0.001) {
        if ($wantWinner) {
            return ['adj' => $adjA, 'id' => $idA, 'name' => $nameA];
        }

        return ['adj' => $adjB, 'id' => $idB, 'name' => $nameB];
    }

    if (abs($actual) < 0.001) {
        if ($wantWinner) {
            return ['adj' => $adjB, 'id' => $idB, 'name' => $nameB];
        }

        return ['adj' => $adjA, 'id' => $idA, 'name' => $nameA];
    }

    if ($adjA >= $adjB) {
        if ($wantWinner) {
            return ['adj' => $adjA, 'id' => $idA, 'name' => $nameA];
        }

        return ['adj' => $adjB, 'id' => $idB, 'name' => $nameB];
    }

    if ($wantWinner) {
        return ['adj' => $adjB, 'id' => $idB, 'name' => $nameB];
    }

    return ['adj' => $adjA, 'id' => $idA, 'name' => $nameA];
}

function k2_game_rating_adjustment_player_link(int $id, string $name): string
{
    if ($id > 0) {
        return '<a class="k2-link-star" href="individual1.php?id=' . $id . '">'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
}

/**
 * Signed rating adjustment span (blue +value / red -value).
 *
 * @param 'blue'|'red'|null $tone Force winner/loser column styling on game.php rows.
 */
function k2_game_rating_adjustment_span_html(float $adj, ?string $tone = null): string
{
    if ($tone === 'blue') {
        $sign = $adj >= 0 ? '+' : '-';
        $class = 'blue';
    } elseif ($tone === 'red') {
        $sign = '-';
        $class = 'red';
        $adj = abs($adj);
    } else {
        $sign = $adj >= 0 ? '+' : '-';
        $class = $adj >= 0 ? 'blue' : 'red';
    }

    return '<span class="' . $class . '">' . $sign . number_format(abs($adj), 1) . '</span>';
}

/**
 * Rated-game adjustment cell: player name link + signed adjustment (game.php / Games tab).
 *
 * @param array<string, mixed> $game ratedresults row
 */
function k2_game_rating_adjustment_html(array $game): string
{
    $picked = k2_game_rating_adjustment_pick($game, 'winner');

    return k2_game_rating_adjustment_player_link($picked['id'], $picked['name'])
        . ' '
        . k2_game_rating_adjustment_span_html($picked['adj'], 'blue');
}

/**
 * Rated-game adjustment cell for the player who loses rating points.
 *
 * @param array<string, mixed> $game ratedresults row
 */
function k2_game_rating_adjustment_loser_html(array $game): string
{
    $picked = k2_game_rating_adjustment_pick($game, 'loser');

    return k2_game_rating_adjustment_player_link($picked['id'], $picked['name'])
        . ' '
        . k2_game_rating_adjustment_span_html($picked['adj'], 'red');
}
