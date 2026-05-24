<?php
/**
 * Rated-game adjustment cell: player name link + signed adjustment (game.php / Games tab).
 *
 * @param array<string, mixed> $game ratedresults row (assoc keys: ActualScore, AdjustmentA, AdjustmentB, idA, idB, NameA, NameB)
 */
function k2_game_rating_adjustment_html(array $game): string
{
    $actual = (float) ($game['ActualScore'] ?? -1);
    $adjA = (float) ($game['AdjustmentA'] ?? 0);
    $adjB = (float) ($game['AdjustmentB'] ?? 0);
    $idA = (int) ($game['idA'] ?? 0);
    $idB = (int) ($game['idB'] ?? 0);
    $nameA = (string) ($game['NameA'] ?? '');
    $nameB = (string) ($game['NameB'] ?? '');

    if (abs($actual - 1.0) < 0.001) {
        $adj = $adjA;
        $pid = $idA;
        $pname = $nameA;
    } elseif (abs($actual) < 0.001) {
        $adj = $adjB;
        $pid = $idB;
        $pname = $nameB;
    } else {
        if ($adjA >= $adjB) {
            $adj = $adjA;
            $pid = $idA;
            $pname = $nameA;
        } else {
            $adj = $adjB;
            $pid = $idB;
            $pname = $nameB;
        }
    }

    $sign = $adj >= 0 ? '+' : '-';
    $adjText = $sign . number_format(abs($adj), 1);
    $nameHtml = $pid > 0
        ? '<a class="k2-link-star" href="individual1.php?id=' . $pid . '">' . htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') . '</a>'
        : htmlspecialchars($pname, ENT_QUOTES, 'UTF-8');

    return $nameHtml . ' <span class="blue">' . $adjText . '</span>';
}
