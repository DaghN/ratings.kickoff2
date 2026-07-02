<?php
/**
 * Amiga player hover glance — JSON payload from stored player truth.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_player_matchup_lib.php';
require_once __DIR__ . '/amiga_player_slice_lib.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

/**
 * @return array<string, mixed>
 */
function amiga_player_glance_payload(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    $playerId = max(0, $playerId);
    if ($playerId < 1) {
        throw new InvalidArgumentException('Invalid player id.');
    }

    $ctx ??= amiga_snapshot_context_from_request($con);

    try {
        $pm = amiga_player_load($con, $playerId, $ctx);
    } catch (RuntimeException $e) {
        $identity = amiga_player_identity_row($con, $playerId);
        if ($identity === null) {
            throw $e;
        }

        return amiga_player_glance_payload_from_identity($identity);
    }

    $preDebut = ($pm['at_cutoff'] ?? true) === false;
    $display = !empty($pm['display']);
    $country = trim((string) ($pm['country'] ?? ''));
    $flagMeta = $country !== '' ? k2_amiga_country_flag_meta($country) : null;

    $wcMedals = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
    $worldCups = 0;
    if (!$preDebut) {
        $wcRow = amiga_player_wc_medal_counts($con, $playerId, $ctx);
        $worldCups = (int) ($wcRow['wc_played'] ?? 0);
        $wcMedals = [
            'gold' => (int) ($wcRow['wc_gold'] ?? 0),
            'silver' => (int) ($wcRow['wc_silver'] ?? 0),
            'bronze' => (int) ($wcRow['wc_bronze'] ?? 0),
        ];
    }

    return [
        'id' => (int) $pm['id'],
        'name' => (string) $pm['name'],
        'country' => $country,
        'flag_code' => $flagMeta['code'] ?? null,
        'display' => $display,
        'pre_debut' => $preDebut,
        'rank' => (!$preDebut && $display) ? amiga_player_normalize_elo_rank($pm['rank'] ?? null) : null,
        'rating' => (!$preDebut && $display && isset($pm['rating']) && !k2_db_is_null($pm['rating']))
            ? (int) round((float) $pm['rating'])
            : null,
        'events' => $preDebut ? null : (int) ($pm['events'] ?? 0),
        'games' => $preDebut ? null : (int) ($pm['games'] ?? 0),
        'world_cups' => $preDebut ? null : $worldCups,
        'wc_medals' => $preDebut ? ['gold' => 0, 'silver' => 0, 'bronze' => 0] : $wcMedals,
    ];
}

/**
 * @param array<string, mixed> $identity
 * @return array<string, mixed>
 */
function amiga_player_glance_payload_from_identity(array $identity): array
{
    $country = trim((string) ($identity['country'] ?? ''));
    $flagMeta = $country !== '' ? k2_amiga_country_flag_meta($country) : null;

    return [
        'id' => (int) $identity['id'],
        'name' => (string) $identity['name'],
        'country' => $country,
        'flag_code' => $flagMeta['code'] ?? null,
        'display' => true,
        'pre_debut' => true,
        'rank' => null,
        'rating' => null,
        'events' => null,
        'games' => null,
        'world_cups' => null,
        'wc_medals' => ['gold' => 0, 'silver' => 0, 'bronze' => 0],
    ];
}