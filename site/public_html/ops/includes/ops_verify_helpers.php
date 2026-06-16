<?php
/**
 * Shared helpers for ops simul verification modules.
 */
declare(strict_types=1);

function k2_ops_verify_scalar(mysqli $con, string $sql): int
{
    $res = $con->query($sql);
    if ($res === false) {
        return -1;
    }
    $row = $res->fetch_assoc();
    $res->free();

    if ($row === null) {
        return -1;
    }
    $val = $row['c'] ?? reset($row);

    return (int) round((float) $val);
}

function k2_ops_verify_table_count(mysqli $con, string $table): int
{
    if (!k2_ops_table_exists($con, $table)) {
        return 0;
    }

    return k2_ops_verify_scalar($con, "SELECT COUNT(*) AS c FROM `{$table}`");
}

/**
 * @return array{id: string, label: string, ok: bool, detail: string, severity: string}
 */
function k2_ops_verify_check(
    string $id,
    string $label,
    bool $ok,
    string $detail,
    string $severity = 'ok'
): array {
    return [
        'id' => $id,
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
        'severity' => $severity,
    ];
}

/**
 * @param list<array{id: string, label: string, ok: bool, detail: string, severity: string}> $checks
 */
function k2_ops_verify_exit_code(array $checks): int
{
    foreach ($checks as $c) {
        if ($c['severity'] === 'fail' && !$c['ok']) {
            return 1;
        }
    }

    return 0;
}
