<?php
/**
 * Amiga Hall of Fame — ladder deep links on record values (rating wing only).
 */
declare(strict_types=1);

/**
 * @return array{sort: int, dir: 'asc'|'desc'}|null
 */
function amiga_records_hof_lb_target(string $metric): ?array
{
    static $map = [
        'most_games' => ['sort' => 5, 'dir' => 'desc'],
        'most_wins' => ['sort' => 6, 'dir' => 'desc'],
        'win_ratio' => ['sort' => 9, 'dir' => 'desc'],
    ];

    return $map[$metric] ?? null;
}

function amiga_records_hof_lb_href(string $metric): ?string
{
    $target = amiga_records_hof_lb_target($metric);
    if ($target === null) {
        return null;
    }

    return '/amiga/rating.php?' . http_build_query([
        'k2_sort' => (string) $target['sort'],
        'k2_dir' => $target['dir'],
    ]);
}
