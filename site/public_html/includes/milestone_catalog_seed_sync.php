<?php
/**
 * Sync milestone_definitions copy fields from ops seed without TRUNCATE.
 * Preserves holder_count and other non-seed columns.
 */
declare(strict_types=1);

/**
 * Double-UTF-8 encoding of common seed glyphs appears as mojibake in rule_short.
 * Repair on read so corrupted live/staging rows still render correctly until DB sync.
 *   ≥ (U+2265) → â‰¥  (bytes C3 A2 E2 80 B0 C2 A5)
 *   ’ (U+2019) → â€™ (bytes C3 A2 E2 82 AC E2 84 A2)
 *   – (U+2013) → â€œ (bytes C3 A2 E2 82 AC E2 80 9C)  // looks like 10â€“10
 */
function k2_milestone_repair_rule_utf8_mojibake(string $text): string
{
    static $map = [
        "\xC3\xA2\xE2\x80\xB0\xC2\xA5" => "\xE2\x89\xA5", // ≥
        "\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2" => "\xE2\x80\x99", // ’
        "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C" => "\xE2\x80\x93", // –
    ];

    return str_replace(array_keys($map), array_values($map), $text);
}

function k2_milestone_catalog_seed_json_path(): string
{
    return dirname(__DIR__) . '/ops/data/milestones_definitions_seed.json';
}

/**
 * @return list<array{milestone_key:string,display_name:string,rule_short:string}>
 */
function k2_milestone_catalog_seed_copy_rows(): array
{
    $path = k2_milestone_catalog_seed_json_path();
    if (!is_file($path)) {
        throw new RuntimeException('Missing milestone seed: ' . $path);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read milestone seed: ' . $path);
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || !isset($payload['definitions']) || !is_array($payload['definitions'])) {
        throw new RuntimeException('Invalid milestone seed JSON (definitions).');
    }
    $out = [];
    foreach ($payload['definitions'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = (string) ($row['milestone_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $out[] = [
            'milestone_key' => $key,
            'display_name' => (string) ($row['display_name'] ?? ''),
            'rule_short' => (string) ($row['rule_short'] ?? ''),
        ];
    }

    return $out;
}

/**
 * UPDATE display_name + rule_short from seed where they differ.
 * Does not TRUNCATE; does not touch holder_count / tier / sort_order.
 *
 * @return array{
 *   checked:int,
 *   updated:int,
 *   missing:list<string>,
 *   changed_keys:list<string>
 * }
 */
function k2_milestone_sync_catalog_copy_from_seed(mysqli $con, bool $apply = true): array
{
    $rows = k2_milestone_catalog_seed_copy_rows();
    $checked = 0;
    $updated = 0;
    $missing = [];
    $changedKeys = [];

    $sel = $con->prepare(
        'SELECT `display_name`, `rule_short` FROM `milestone_definitions` WHERE `milestone_key` = ? LIMIT 1'
    );
    if ($sel === false) {
        throw new RuntimeException('prepare SELECT milestone_definitions: ' . $con->error);
    }
    $upd = null;
    if ($apply) {
        $upd = $con->prepare(
            'UPDATE `milestone_definitions` SET `display_name` = ?, `rule_short` = ? WHERE `milestone_key` = ? LIMIT 1'
        );
        if ($upd === false) {
            $sel->close();
            throw new RuntimeException('prepare UPDATE milestone_definitions: ' . $con->error);
        }
    }

    foreach ($rows as $row) {
        $checked++;
        $key = $row['milestone_key'];
        $sel->bind_param('s', $key);
        if (!$sel->execute()) {
            $sel->close();
            if ($upd !== null) {
                $upd->close();
            }
            throw new RuntimeException('SELECT milestone_definitions: ' . $sel->error);
        }
        $res = $sel->get_result();
        $cur = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        if ($cur === null) {
            $missing[] = $key;
            continue;
        }
        $curName = (string) ($cur['display_name'] ?? '');
        $curRule = (string) ($cur['rule_short'] ?? '');
        if ($curName === $row['display_name'] && $curRule === $row['rule_short']) {
            continue;
        }
        $changedKeys[] = $key;
        if ($apply && $upd !== null) {
            $name = $row['display_name'];
            $rule = $row['rule_short'];
            $upd->bind_param('sss', $name, $rule, $key);
            if (!$upd->execute()) {
                $sel->close();
                $upd->close();
                throw new RuntimeException('UPDATE milestone_definitions: ' . $upd->error);
            }
            $updated++;
        }
    }

    $sel->close();
    if ($upd !== null) {
        $upd->close();
    }

    return [
        'checked' => $checked,
        'updated' => $apply ? $updated : count($changedKeys),
        'missing' => $missing,
        'changed_keys' => $changedKeys,
    ];
}