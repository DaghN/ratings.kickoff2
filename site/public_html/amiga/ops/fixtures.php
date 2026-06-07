<?php
/**
 * Internal fixture browser/result entry for fixture-backed Amiga tournaments.
 *
 * Usage:
 *   /amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee&tournament_id=N
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once __DIR__ . '/modules/process_completed_game.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

const AMIGA_FIXTURE_LIVE_SOURCE_SCORES_ID_BASE = 1000000000;

$key = 'amiga-fixtures-one-shot';
$opsPassword = 'coffee';
$onceValue = (string) ($_GET['once'] ?? $_POST['once'] ?? '');
$pwdValue = (string) ($_GET['pwd'] ?? $_POST['pwd'] ?? '');

if ($onceValue !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$pwdProvided = $pwdValue !== '';
$pwdOk = $pwdProvided && hash_equals($opsPassword, $pwdValue);
$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/ops/fixtures.php', ENT_QUOTES, 'UTF-8');

function amiga_fixture_render_chrome_start(string $pageTitle, bool $withDayPickerAssets = false): void
{
    global $k2AmigaHubTabActive;
    $k2AmigaHubTabActive = 'live-tournaments';
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo k2_h($pageTitle); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php
    if ($withDayPickerAssets) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_day_picker.php';
        k2_render_day_picker_assets();
    }
?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php'; ?>
    <?php
}

function amiga_fixture_render_chrome_end(): void
{
    echo "</div><!-- .k2-page-nav -->\n</body>\n</html>";
}

if (!$pwdOk) {
    amiga_fixture_render_chrome_start('Amiga — Fixture manager');
    ?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Fixture manager</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">Password required for fixture ops.</p>
</header>
<div class="k2-amiga-live-ops">
<?php if ($pwdProvided) { ?>
  <div class="k2-amiga-live-ops__flash k2-amiga-live-ops__flash--error">Incorrect password.</div>
<?php } ?>
  <form method="get" action="<?php echo $self; ?>" class="k2-amiga-live-ops__grid-form" style="max-width:24rem">
    <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
    <label>Password
      <input type="password" id="pwd" name="pwd" autocomplete="current-password" required autofocus>
    </label>
    <label>Tournament id
      <input type="number" id="tournament_id" name="tournament_id" min="1">
    </label>
    <div class="wide"><button type="submit">Continue</button></div>
  </form>
</div>
<?php
    amiga_fixture_render_chrome_end();
    exit;
}

$tournamentId = isset($_GET['tournament_id']) ? max(0, (int) $_GET['tournament_id']) : 0;
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!in_array($status, ['', 'scheduled', 'played', 'void'], true)) {
    $status = '';
}

function amiga_fixture_next_live_source_scores_id(mysqli $con): int
{
    $base = AMIGA_FIXTURE_LIVE_SOURCE_SCORES_ID_BASE;
    $stmt = $con->prepare(
        'SELECT COALESCE(MAX(source_scores_id), ? - 1) AS max_id '
        . 'FROM amiga_games WHERE source_scores_id >= ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare next source id: ' . $con->error);
    }
    $stmt->bind_param('ii', $base, $base);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute next source id: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['max_id'] ?? ($base - 1)) + 1;
}

function amiga_fixture_next_game_date(mysqli $con): string
{
    $res = $con->query(
        "SELECT COALESCE("
        . "DATE_FORMAT(DATE_ADD(MAX(game_date), INTERVAL 1 SECOND), '%Y-%m-%d %H:%i:%s'), "
        . "DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%d %H:%i:%s')) AS next_game_date "
        . "FROM amiga_games"
    );
    if ($res === false) {
        throw new RuntimeException('next game date: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();

    return (string) ($row['next_game_date'] ?? gmdate('Y-m-d H:i:s'));
}

/**
 * @return list<int>
 */
function amiga_fixture_parse_player_ids(string $raw): array
{
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    if (count($ids) < 2) {
        throw new RuntimeException('At least two player ids are required.');
    }
    if (count(array_unique($ids)) !== count($ids)) {
        throw new RuntimeException('Player ids must be unique.');
    }

    return $ids;
}

function amiga_fixture_require_player(mysqli $con, int $playerId): void
{
    $stmt = $con->prepare('SELECT id FROM amiga_players WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare player check: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player check: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc() !== null;
    $stmt->close();
    if (!$exists) {
        throw new RuntimeException("Player {$playerId} not found.");
    }
}

/** @var list<string> */
const AMIGA_FIXTURE_VALID_LIFECYCLE_STATUSES = [
    'draft',
    'registration',
    'ready',
    'running',
    'completed',
    'archived',
    'void',
];

/** @var list<string> */
const AMIGA_FIXTURE_IMPORTED_LIFECYCLE_STATUSES = ['completed', 'archived'];

/**
 * @return array{id:int,name:string,source_id:?int,lifecycle_status:string,started_at:?string,completed_at:?string}|null
 */
function amiga_fixture_load_lifecycle(mysqli $con, int $tournamentId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, name, source_id, lifecycle_status, started_at, completed_at '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare lifecycle load: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute lifecycle load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
        'lifecycle_status' => (string) $row['lifecycle_status'],
        'started_at' => $row['started_at'] !== null ? (string) $row['started_at'] : null,
        'completed_at' => $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
    ];
}

function amiga_fixture_count_scheduled_fixtures(mysqli $con, int $tournamentId): int
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND f.status = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare scheduled fixture count: ' . $con->error);
    }
    $scheduled = 'scheduled';
    $stmt->bind_param('is', $tournamentId, $scheduled);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute scheduled fixture count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

function amiga_fixture_count_tournament_games(mysqli $con, int $tournamentId): int
{
    $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament game count: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament game count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

/**
 * @param array{id:int,name:string,source_id:?int,lifecycle_status:string,started_at:?string,completed_at:?string} $lifecycle
 * @return list<string>
 */
function amiga_fixture_browser_allowed_lifecycle_targets(mysqli $con, array $lifecycle): array
{
    if ($lifecycle['source_id'] !== null) {
        return [];
    }

    $current = $lifecycle['lifecycle_status'];
    $tournamentId = $lifecycle['id'];
    if ($current === 'draft') {
        return ['ready'];
    }
    if ($current === 'ready') {
        return ['running'];
    }
    if ($current === 'running') {
        $targets = [];
        if (amiga_fixture_count_scheduled_fixtures($con, $tournamentId) === 0) {
            $targets[] = 'completed';
        }
        if (amiga_fixture_count_tournament_games($con, $tournamentId) === 0) {
            $targets[] = 'void';
        }

        return $targets;
    }

    return [];
}

/**
 * @return array{
 *   tournament_id:int,
 *   previous_status:string,
 *   lifecycle_status:string,
 *   changed:bool,
 *   started_at:?string,
 *   completed_at:?string,
 *   unplayed_scheduled_fixtures:int
 * }
 */
function amiga_fixture_set_lifecycle_status(mysqli $con, int $tournamentId, string $status): array
{
    if (!in_array($status, AMIGA_FIXTURE_VALID_LIFECYCLE_STATUSES, true)) {
        throw new RuntimeException(
            'lifecycle_status must be one of: ' . implode(', ', AMIGA_FIXTURE_VALID_LIFECYCLE_STATUSES) . '.'
        );
    }

    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }

    $current = $lifecycle['lifecycle_status'];
    if ($current === $status) {
        return [
            'tournament_id' => $tournamentId,
            'previous_status' => $current,
            'lifecycle_status' => $status,
            'changed' => false,
            'started_at' => $lifecycle['started_at'],
            'completed_at' => $lifecycle['completed_at'],
            'unplayed_scheduled_fixtures' => 0,
        ];
    }

    if ($lifecycle['source_id'] !== null) {
        throw new RuntimeException(
            "Tournament {$tournamentId} is an imported historical tournament; "
            . 'lifecycle changes are not allowed in the browser ops page.'
        );
    }

    $allowed = amiga_fixture_browser_allowed_lifecycle_targets($con, $lifecycle);
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException(
            "Tournament {$tournamentId} lifecycle_status is '{$current}'; "
            . "browser transition to '{$status}' is not allowed."
        );
    }

    $unplayed = 0;
    if ($status === 'completed') {
        $unplayed = amiga_fixture_count_scheduled_fixtures($con, $tournamentId);
        if ($unplayed > 0) {
            throw new RuntimeException(
                "Tournament {$tournamentId} has {$unplayed} scheduled fixture(s); "
                . 'refusing transition to completed.'
            );
        }
    }
    if ($status === 'void') {
        $gameCount = amiga_fixture_count_tournament_games($con, $tournamentId);
        if ($gameCount > 0) {
            throw new RuntimeException(
                "Tournament {$tournamentId} has {$gameCount} game(s); refusing transition to void."
            );
        }
    }

    $startedAt = $lifecycle['started_at'];
    $completedAt = $lifecycle['completed_at'];
    $now = gmdate('Y-m-d H:i:s');
    if ($status === 'running' && $startedAt === null) {
        $startedAt = $now;
    }
    if (in_array($status, ['completed', 'archived'], true) && $completedAt === null) {
        $completedAt = $now;
    }

    $stmt = $con->prepare(
        'UPDATE tournaments SET lifecycle_status = ?, started_at = ?, completed_at = ? WHERE id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare lifecycle update: ' . $con->error);
    }
    $stmt->bind_param('sssi', $status, $startedAt, $completedAt, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute lifecycle update: ' . $stmt->error);
    }
    $stmt->close();

    return [
        'tournament_id' => $tournamentId,
        'previous_status' => $current,
        'lifecycle_status' => $status,
        'changed' => true,
        'started_at' => $startedAt,
        'completed_at' => $completedAt,
        'unplayed_scheduled_fixtures' => $unplayed,
    ];
}

function amiga_fixture_require_running_lifecycle(mysqli $con, int $tournamentId): void
{
    $stmt = $con->prepare(
        'SELECT lifecycle_status FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare lifecycle check: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute lifecycle check: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }
    if ((string) $row['lifecycle_status'] !== 'running') {
        throw new RuntimeException(
            "Tournament {$tournamentId} lifecycle_status is '{$row['lifecycle_status']}'; "
            . 'result entry is allowed only when lifecycle_status is running.'
        );
    }
}

function amiga_fixture_require_active_entrant(mysqli $con, int $tournamentId, int $playerId): void
{
    $stmt = $con->prepare(
        'SELECT status FROM tournament_entrants WHERE tournament_id = ? AND player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare entrant check: ' . $con->error);
    }
    $stmt->bind_param('ii', $tournamentId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entrant check: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException(
            "Player {$playerId} is not a tournament entrant in tournament {$tournamentId}."
        );
    }
    if ((string) $row['status'] !== 'registered') {
        throw new RuntimeException(
            "Player {$playerId} entrant status is '{$row['status']}'; "
            . 'only registered entrants may be used in fixture assignment or result entry.'
        );
    }
}

/** @var list<string> */
const AMIGA_FIXTURE_GENERATED_BY_PREFIXES = [
    'scripts.amiga.tournament_builder',
    'site.public_html.amiga.ops.fixtures',
];

/** @var list<string> */
const AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES = ['draft', 'registration', 'ready'];

const AMIGA_FIXTURE_WITHDRAW_ENTRANT_ACTION = 'withdrawn by fixtures browser ops';
const AMIGA_FIXTURE_REPLACE_ENTRANT_ACTION = 'replaced by fixtures browser ops';

function amiga_fixture_tournament_generated_by(array $row): string
{
    $overrides = json_decode((string) ($row['format_overrides'] ?? '{}'), true);
    if (!is_array($overrides)) {
        return '';
    }

    return (string) ($overrides['generated_by'] ?? '');
}

function amiga_fixture_is_eligible_generated_tournament(array $row): bool
{
    if ($row['source_id'] !== null) {
        return false;
    }
    $generatedBy = amiga_fixture_tournament_generated_by($row);
    foreach (AMIGA_FIXTURE_GENERATED_BY_PREFIXES as $prefix) {
        if (str_starts_with($generatedBy, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{id:int,name:string,source_id:?int,format_overrides:?string,lifecycle_status:string}
 */
function amiga_fixture_require_generated_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT id, name, source_id, format_overrides, lifecycle_status '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament load: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }
    $normalized = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
        'format_overrides' => $row['format_overrides'] !== null ? (string) $row['format_overrides'] : null,
        'lifecycle_status' => (string) $row['lifecycle_status'],
    ];
    if (!amiga_fixture_is_eligible_generated_tournament($normalized)) {
        if ($normalized['source_id'] !== null) {
            throw new RuntimeException(
                "Tournament {$tournamentId} is an imported Access tournament; entrant ops refused."
            );
        }
        throw new RuntimeException(
            "Tournament {$tournamentId} is not eligible for entrant ops "
            . '(must be generated by approved fixture tooling).'
        );
    }

    return $normalized;
}

function amiga_fixture_require_entrant_registration_lifecycle(mysqli $con, int $tournamentId): void
{
    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }
    $current = $lifecycle['lifecycle_status'];
    if (!in_array($current, AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES, true)) {
        throw new RuntimeException(
            "Tournament {$tournamentId} lifecycle_status is '{$current}'; "
            . 'entrant registration is allowed only in draft, registration, or ready.'
        );
    }
}

function amiga_fixture_require_stage_placement_lifecycle(mysqli $con, int $tournamentId): void
{
    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }
    $current = $lifecycle['lifecycle_status'];
    if (!in_array($current, AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES, true)) {
        throw new RuntimeException(
            "Tournament {$tournamentId} lifecycle_status is '{$current}'; "
            . 'stage player placement is allowed only in draft, registration, or ready.'
        );
    }
}

/**
 * @return list<array{id:int,stage_key:string,name:string,stage_type:string,sequence_no:int}>
 */
function amiga_fixture_list_stages(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT id, stage_key, name, stage_type, sequence_no '
        . 'FROM tournament_stages WHERE tournament_id = ? '
        . 'ORDER BY sequence_no ASC, id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage list: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage list: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'id' => (int) $row['id'],
            'stage_key' => (string) $row['stage_key'],
            'name' => (string) $row['name'],
            'stage_type' => (string) $row['stage_type'],
            'sequence_no' => (int) $row['sequence_no'],
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * @return array{id:int,stage_key:string,name:string,stage_type:string}|null
 */
function amiga_fixture_load_stage(mysqli $con, int $tournamentId, int $stageId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, stage_key, name, stage_type '
        . 'FROM tournament_stages WHERE id = ? AND tournament_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage load: ' . $con->error);
    }
    $stmt->bind_param('ii', $stageId, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'stage_key' => (string) $row['stage_key'],
        'name' => (string) $row['name'],
        'stage_type' => (string) $row['stage_type'],
    ];
}

/**
 * @return list<array{stage_id:int,stage_key:string,stage_name:string,stage_type:string,player_id:int,player_name:string,seed_no:?int,group_key:?string}>
 */
function amiga_fixture_list_stage_players(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT s.id AS stage_id, s.stage_key, s.name AS stage_name, s.stage_type, '
        . 'sp.player_id, p.name AS player_name, sp.seed_no, sp.group_key '
        . 'FROM tournament_stage_players sp '
        . 'INNER JOIN tournament_stages s ON s.id = sp.stage_id '
        . 'INNER JOIN amiga_players p ON p.id = sp.player_id '
        . 'WHERE s.tournament_id = ? '
        . 'ORDER BY s.sequence_no ASC, s.id ASC, sp.seed_no IS NULL, sp.seed_no ASC, sp.player_id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage player list: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage player list: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'stage_id' => (int) $row['stage_id'],
            'stage_key' => (string) $row['stage_key'],
            'stage_name' => (string) $row['stage_name'],
            'stage_type' => (string) $row['stage_type'],
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'seed_no' => $row['seed_no'] !== null ? (int) $row['seed_no'] : null,
            'group_key' => $row['group_key'] !== null ? (string) $row['group_key'] : null,
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * @return array{stage_id:int,stage_key:string,player_id:int,seed_no:?int,group_key:?string,updated:bool}
 */
function amiga_fixture_place_stage_entrant(
    mysqli $con,
    int $tournamentId,
    int $stageId,
    int $playerId,
    ?int $seedNo,
    ?string $groupKey
): array {
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    amiga_fixture_require_stage_placement_lifecycle($con, $tournamentId);
    $stage = amiga_fixture_load_stage($con, $tournamentId, $stageId);
    if ($stage === null) {
        throw new RuntimeException("Stage {$stageId} not found in tournament {$tournamentId}.");
    }
    amiga_fixture_require_player($con, $playerId);
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerId);

    $groupKeyValue = $groupKey !== null && trim($groupKey) !== '' ? trim($groupKey) : null;

    $stmt = $con->prepare(
        'SELECT player_id FROM tournament_stage_players WHERE stage_id = ? AND player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage player lookup: ' . $con->error);
    }
    $stmt->bind_param('ii', $stageId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage player lookup: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $updated = $existing !== null;

    if ($seedNo === null) {
        $stmt = $con->prepare(
            'INSERT INTO tournament_stage_players (stage_id, player_id, seed_no, group_key) '
            . 'VALUES (?, ?, NULL, ?) '
            . 'ON DUPLICATE KEY UPDATE seed_no = VALUES(seed_no), group_key = VALUES(group_key)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage player upsert: ' . $con->error);
        }
        $stmt->bind_param('iis', $stageId, $playerId, $groupKeyValue);
    } else {
        $stmt = $con->prepare(
            'INSERT INTO tournament_stage_players (stage_id, player_id, seed_no, group_key) '
            . 'VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE seed_no = VALUES(seed_no), group_key = VALUES(group_key)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage player upsert: ' . $con->error);
        }
        $stmt->bind_param('iiis', $stageId, $playerId, $seedNo, $groupKeyValue);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage player upsert: ' . $stmt->error);
    }
    $stmt->close();

    return [
        'stage_id' => $stageId,
        'stage_key' => $stage['stage_key'],
        'player_id' => $playerId,
        'seed_no' => $seedNo,
        'group_key' => $groupKeyValue,
        'updated' => $updated,
    ];
}

function amiga_fixture_append_entrant_note(?string $existing, string $action, ?string $note): string
{
    $timestamp = gmdate('Y-m-d');
    $adminPart = "[{$timestamp}] {$action}";
    $trimmedNote = trim((string) ($note ?? ''));
    if ($trimmedNote !== '') {
        $adminPart .= ': ' . $trimmedNote;
    }
    $existingTrimmed = trim((string) ($existing ?? ''));
    $combined = $existingTrimmed !== '' ? $existingTrimmed . ' | ' . $adminPart : $adminPart;
    if (strlen($combined) > 255) {
        return substr($combined, 0, 252) . '...';
    }

    return $combined;
}

/**
 * @return array{id:int,player_id:int,seed_no:?int,status:string,note:?string}|null
 */
function amiga_fixture_load_entrant_row(mysqli $con, int $tournamentId, int $playerId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, player_id, seed_no, status, note '
        . 'FROM tournament_entrants WHERE tournament_id = ? AND player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare entrant load: ' . $con->error);
    }
    $stmt->bind_param('ii', $tournamentId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entrant load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'player_id' => (int) $row['player_id'],
        'seed_no' => $row['seed_no'] !== null ? (int) $row['seed_no'] : null,
        'status' => (string) $row['status'],
        'note' => $row['note'] !== null ? (string) $row['note'] : null,
    ];
}

/**
 * @return list<array{id:int,player_id:int,player_name:string,seed_no:?int,status:string,note:?string}>
 */
function amiga_fixture_list_entrants(mysqli $con, int $tournamentId, int $limit = 500): array
{
    $limit = max(1, min($limit, 2000));
    $stmt = $con->prepare(
        'SELECT e.id, e.player_id, p.name AS player_name, e.seed_no, e.status, e.note '
        . 'FROM tournament_entrants e '
        . 'INNER JOIN amiga_players p ON p.id = e.player_id '
        . 'WHERE e.tournament_id = ? '
        . 'ORDER BY e.seed_no IS NULL, e.seed_no ASC, e.id ASC '
        . 'LIMIT ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare entrant list: ' . $con->error);
    }
    $stmt->bind_param('ii', $tournamentId, $limit);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entrant list: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'id' => (int) $row['id'],
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'seed_no' => $row['seed_no'] !== null ? (int) $row['seed_no'] : null,
            'status' => (string) $row['status'],
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array{id:int,name:string,country:?string}>
 */
function amiga_fixture_search_players(mysqli $con, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $limit = max(1, min($limit, 50));
    $rows = [];
    $seen = [];

    if (preg_match('/^\d+$/', $query) === 1) {
        $playerId = (int) $query;
        if ($playerId > 0) {
            $stmt = $con->prepare('SELECT id, name, country FROM amiga_players WHERE id = ? LIMIT 1');
            if ($stmt === false) {
                throw new RuntimeException('prepare player id search: ' . $con->error);
            }
            $stmt->bind_param('i', $playerId);
            if (!$stmt->execute()) {
                throw new RuntimeException('execute player id search: ' . $stmt->error);
            }
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row !== null) {
                $id = (int) $row['id'];
                $seen[$id] = true;
                $rows[] = [
                    'id' => $id,
                    'name' => (string) $row['name'],
                    'country' => $row['country'] !== null ? (string) $row['country'] : null,
                ];
            }
        }
    }

    $like = '%' . $query . '%';
    $nameLimit = $limit - count($rows);
    if ($nameLimit > 0) {
        $stmt = $con->prepare(
            'SELECT id, name, country FROM amiga_players WHERE name LIKE ? ORDER BY name ASC LIMIT ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare player name search: ' . $con->error);
        }
        $stmt->bind_param('si', $like, $nameLimit);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute player name search: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'country' => $row['country'] !== null ? (string) $row['country'] : null,
            ];
        }
        $stmt->close();
    }

    return $rows;
}

function amiga_fixture_validate_new_entrant_registration(mysqli $con, int $tournamentId, int $playerId): void
{
    $existing = amiga_fixture_load_entrant_row($con, $tournamentId, $playerId);
    if ($existing === null) {
        return;
    }
    if ($existing['status'] === 'registered') {
        throw new RuntimeException(
            "Player {$playerId} is already a registered entrant in tournament {$tournamentId}."
        );
    }
    throw new RuntimeException(
        "Player {$playerId} entrant status is '{$existing['status']}'; "
        . 'reactivation is not supported by entrant onboarding.'
    );
}

/**
 * @return array{entrant_id:int,player_id:int,seed_no:?int}
 */
function amiga_fixture_add_entrant_existing_player(
    mysqli $con,
    int $tournamentId,
    int $playerId,
    ?int $seedNo,
    ?string $note
): array {
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    amiga_fixture_require_entrant_registration_lifecycle($con, $tournamentId);
    amiga_fixture_require_player($con, $playerId);
    amiga_fixture_validate_new_entrant_registration($con, $tournamentId, $playerId);

    if ($seedNo === null) {
        $stmt = $con->prepare(
            'INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note) '
            . "VALUES (?, ?, NULL, 'registered', ?)"
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant insert: ' . $con->error);
        }
        $stmt->bind_param('iis', $tournamentId, $playerId, $note);
    } else {
        $stmt = $con->prepare(
            'INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note) '
            . "VALUES (?, ?, ?, 'registered', ?)"
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant insert: ' . $con->error);
        }
        $stmt->bind_param('iiis', $tournamentId, $playerId, $seedNo, $note);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entrant insert: ' . $stmt->error);
    }
    $entrantId = (int) $stmt->insert_id;
    $stmt->close();

    return [
        'entrant_id' => $entrantId,
        'player_id' => $playerId,
        'seed_no' => $seedNo,
    ];
}

/**
 * @return list<array{id:int,status:string,player_a_id:?int,player_b_id:?int,game_count:int}>
 */
function amiga_fixture_load_player_fixtures(mysqli $con, int $tournamentId, int $playerId): array
{
    $stmt = $con->prepare(
        'SELECT f.id, f.status, f.player_a_id, f.player_b_id, '
        . '(SELECT COUNT(*) FROM amiga_games g WHERE g.fixture_id = f.id) AS game_count '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND (f.player_a_id = ? OR f.player_b_id = ?) '
        . 'ORDER BY f.id'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player fixtures load: ' . $con->error);
    }
    $stmt->bind_param('iii', $tournamentId, $playerId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player fixtures load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'player_a_id' => $row['player_a_id'] !== null ? (int) $row['player_a_id'] : null,
            'player_b_id' => $row['player_b_id'] !== null ? (int) $row['player_b_id'] : null,
            'game_count' => (int) $row['game_count'],
        ];
    }
    $stmt->close();

    return $rows;
}

function amiga_fixture_count_tournament_games_for_player(mysqli $con, int $tournamentId, int $playerId): int
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_games '
        . 'WHERE tournament_id = ? AND (player_a_id = ? OR player_b_id = ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player game count: ' . $con->error);
    }
    $stmt->bind_param('iii', $tournamentId, $playerId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player game count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

/**
 * @return array{entrant:array<string,mixed>,scheduled_fixtures:list<array<string,mixed>>}
 */
function amiga_fixture_validate_withdrawal_eligibility(mysqli $con, int $tournamentId, int $playerId): array
{
    $entrant = amiga_fixture_load_entrant_row($con, $tournamentId, $playerId);
    if ($entrant === null) {
        throw new RuntimeException(
            "Player {$playerId} is not a tournament entrant in tournament {$tournamentId}."
        );
    }
    if ($entrant['status'] !== 'registered') {
        throw new RuntimeException(
            "Player {$playerId} entrant status is '{$entrant['status']}'; only registered entrants can be withdrawn."
        );
    }
    $gameCount = amiga_fixture_count_tournament_games_for_player($con, $tournamentId, $playerId);
    if ($gameCount > 0) {
        throw new RuntimeException(
            "Player {$playerId} has {$gameCount} attached game(s) in tournament {$tournamentId}; withdrawal refused."
        );
    }
    $fixtures = amiga_fixture_load_player_fixtures($con, $tournamentId, $playerId);
    $playedIds = [];
    foreach ($fixtures as $fixture) {
        if ($fixture['status'] === 'played' || $fixture['game_count'] > 0) {
            $playedIds[] = (int) $fixture['id'];
        }
    }
    if ($playedIds !== []) {
        throw new RuntimeException(
            'Player ' . $playerId . ' is assigned to played fixture(s) '
            . implode(', ', $playedIds) . '; withdrawal refused.'
        );
    }
    $scheduled = array_values(array_filter(
        $fixtures,
        static fn(array $fixture): bool => $fixture['status'] === 'scheduled'
    ));

    return [
        'entrant' => $entrant,
        'scheduled_fixtures' => $scheduled,
    ];
}

function amiga_fixture_withdraw_entrant(
    mysqli $con,
    int $tournamentId,
    int $playerId,
    ?string $note
): array {
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    $plan = amiga_fixture_validate_withdrawal_eligibility($con, $tournamentId, $playerId);
    $entrant = $plan['entrant'];
    $scheduled = $plan['scheduled_fixtures'];
    $updatedNote = amiga_fixture_append_entrant_note(
        $entrant['note'],
        AMIGA_FIXTURE_WITHDRAW_ENTRANT_ACTION,
        $note
    );

    $con->begin_transaction();
    try {
        $fixtureSlotsCleared = 0;
        foreach ($scheduled as $fixture) {
            $fixtureId = (int) $fixture['id'];
            if ($fixture['player_a_id'] === $playerId) {
                $stmt = $con->prepare('UPDATE tournament_fixtures SET player_a_id = NULL WHERE id = ?');
                if ($stmt === false) {
                    throw new RuntimeException('prepare fixture slot clear A: ' . $con->error);
                }
                $stmt->bind_param('i', $fixtureId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('execute fixture slot clear A: ' . $stmt->error);
                }
                $stmt->close();
                $fixtureSlotsCleared++;
            }
            if ($fixture['player_b_id'] === $playerId) {
                $stmt = $con->prepare('UPDATE tournament_fixtures SET player_b_id = NULL WHERE id = ?');
                if ($stmt === false) {
                    throw new RuntimeException('prepare fixture slot clear B: ' . $con->error);
                }
                $stmt->bind_param('i', $fixtureId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('execute fixture slot clear B: ' . $stmt->error);
                }
                $stmt->close();
                $fixtureSlotsCleared++;
            }
        }

        $stmt = $con->prepare(
            'DELETE sp FROM tournament_stage_players sp '
            . 'INNER JOIN tournament_stages s ON s.id = sp.stage_id '
            . 'WHERE s.tournament_id = ? AND sp.player_id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage player delete: ' . $con->error);
        }
        $stmt->bind_param('ii', $tournamentId, $playerId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute stage player delete: ' . $stmt->error);
        }
        $stagePlayerRowsRemoved = (int) $stmt->affected_rows;
        $stmt->close();

        $stmt = $con->prepare(
            "UPDATE tournament_entrants SET status = 'withdrawn', note = ? "
            . 'WHERE tournament_id = ? AND player_id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant withdraw: ' . $con->error);
        }
        $stmt->bind_param('sii', $updatedNote, $tournamentId, $playerId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute entrant withdraw: ' . $stmt->error);
        }
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return [
        'player_id' => $playerId,
        'status' => 'withdrawn',
        'scheduled_fixtures_touched' => count($scheduled),
        'fixture_slots_cleared' => $fixtureSlotsCleared,
        'stage_player_rows_removed' => $stagePlayerRowsRemoved,
    ];
}

/**
 * @return array{old_entrant:array<string,mixed>,scheduled_fixtures:list<array<string,mixed>>}
 */
function amiga_fixture_validate_replacement_eligibility(
    mysqli $con,
    int $tournamentId,
    int $oldPlayerId,
    int $newPlayerId
): array {
    if ($oldPlayerId === $newPlayerId) {
        throw new RuntimeException('Old and new player ids must differ.');
    }
    amiga_fixture_require_player($con, $newPlayerId);
    $oldEntrant = amiga_fixture_load_entrant_row($con, $tournamentId, $oldPlayerId);
    if ($oldEntrant === null) {
        throw new RuntimeException(
            "Player {$oldPlayerId} is not a tournament entrant in tournament {$tournamentId}."
        );
    }
    if ($oldEntrant['status'] !== 'registered') {
        throw new RuntimeException(
            "Player {$oldPlayerId} entrant status is '{$oldEntrant['status']}'; "
            . 'only registered entrants can be replaced.'
        );
    }
    if (amiga_fixture_load_entrant_row($con, $tournamentId, $newPlayerId) !== null) {
        throw new RuntimeException(
            "Player {$newPlayerId} is already a tournament entrant in tournament {$tournamentId}."
        );
    }
    $gameCount = amiga_fixture_count_tournament_games_for_player($con, $tournamentId, $oldPlayerId);
    if ($gameCount > 0) {
        throw new RuntimeException(
            "Player {$oldPlayerId} has {$gameCount} attached game(s) in tournament {$tournamentId}; replacement refused."
        );
    }
    $fixtures = amiga_fixture_load_player_fixtures($con, $tournamentId, $oldPlayerId);
    $blockedIds = [];
    foreach ($fixtures as $fixture) {
        if ($fixture['status'] === 'played' || $fixture['game_count'] > 0) {
            $blockedIds[] = (int) $fixture['id'];
        }
    }
    if ($blockedIds !== []) {
        throw new RuntimeException(
            'Player ' . $oldPlayerId . ' is assigned to played fixture(s) '
            . implode(', ', $blockedIds) . '; replacement refused.'
        );
    }
    $scheduled = array_values(array_filter(
        $fixtures,
        static fn(array $fixture): bool => $fixture['status'] === 'scheduled'
    ));

    return [
        'old_entrant' => $oldEntrant,
        'scheduled_fixtures' => $scheduled,
    ];
}

function amiga_fixture_replace_entrant(
    mysqli $con,
    int $tournamentId,
    int $oldPlayerId,
    int $newPlayerId,
    ?string $note
): array {
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    $plan = amiga_fixture_validate_replacement_eligibility($con, $tournamentId, $oldPlayerId, $newPlayerId);
    $oldEntrant = $plan['old_entrant'];
    $scheduled = $plan['scheduled_fixtures'];
    $oldNote = amiga_fixture_append_entrant_note(
        $oldEntrant['note'],
        AMIGA_FIXTURE_REPLACE_ENTRANT_ACTION,
        $note
    );
    $newNote = amiga_fixture_append_entrant_note(null, AMIGA_FIXTURE_REPLACE_ENTRANT_ACTION, $note);
    $seedNo = $oldEntrant['seed_no'];

    $con->begin_transaction();
    try {
        $fixtureSlotsUpdated = 0;
        foreach ($scheduled as $fixture) {
            $fixtureId = (int) $fixture['id'];
            if ($fixture['player_a_id'] === $oldPlayerId) {
                $stmt = $con->prepare('UPDATE tournament_fixtures SET player_a_id = ? WHERE id = ?');
                if ($stmt === false) {
                    throw new RuntimeException('prepare fixture replace A: ' . $con->error);
                }
                $stmt->bind_param('ii', $newPlayerId, $fixtureId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('execute fixture replace A: ' . $stmt->error);
                }
                $stmt->close();
                $fixtureSlotsUpdated++;
            }
            if ($fixture['player_b_id'] === $oldPlayerId) {
                $stmt = $con->prepare('UPDATE tournament_fixtures SET player_b_id = ? WHERE id = ?');
                if ($stmt === false) {
                    throw new RuntimeException('prepare fixture replace B: ' . $con->error);
                }
                $stmt->bind_param('ii', $newPlayerId, $fixtureId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('execute fixture replace B: ' . $stmt->error);
                }
                $stmt->close();
                $fixtureSlotsUpdated++;
            }
        }

        $stmt = $con->prepare(
            'UPDATE tournament_stage_players sp '
            . 'INNER JOIN tournament_stages s ON s.id = sp.stage_id '
            . 'SET sp.player_id = ? WHERE s.tournament_id = ? AND sp.player_id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage player replace: ' . $con->error);
        }
        $stmt->bind_param('iii', $newPlayerId, $tournamentId, $oldPlayerId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute stage player replace: ' . $stmt->error);
        }
        $stagePlayerRowsUpdated = (int) $stmt->affected_rows;
        $stmt->close();

        $stmt = $con->prepare(
            "UPDATE tournament_entrants SET status = 'replaced', note = ? "
            . 'WHERE tournament_id = ? AND player_id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare old entrant replace: ' . $con->error);
        }
        $stmt->bind_param('sii', $oldNote, $tournamentId, $oldPlayerId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute old entrant replace: ' . $stmt->error);
        }
        $stmt->close();

        $registered = 'registered';
        if ($seedNo === null) {
            $stmt = $con->prepare(
                'INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note) '
                . 'VALUES (?, ?, NULL, ?, ?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('prepare new entrant insert: ' . $con->error);
            }
            $stmt->bind_param('iiss', $tournamentId, $newPlayerId, $registered, $newNote);
        } else {
            $stmt = $con->prepare(
                'INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note) '
                . 'VALUES (?, ?, ?, ?, ?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('prepare new entrant insert: ' . $con->error);
            }
            $stmt->bind_param('iiiss', $tournamentId, $newPlayerId, $seedNo, $registered, $newNote);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException('execute new entrant insert: ' . $stmt->error);
        }
        $newEntrantId = (int) $stmt->insert_id;
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return [
        'old_player_id' => $oldPlayerId,
        'new_player_id' => $newPlayerId,
        'new_entrant_id' => $newEntrantId,
        'seed_no' => $seedNo,
        'fixture_slots_updated' => $fixtureSlotsUpdated,
        'stage_player_rows_updated' => $stagePlayerRowsUpdated,
    ];
}

function amiga_fixture_template_id(mysqli $con, string $slug): int
{
    $stmt = $con->prepare('SELECT id FROM tournament_format_templates WHERE slug = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare template lookup: ' . $con->error);
    }
    $stmt->bind_param('s', $slug);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute template lookup: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("Format template {$slug} is not seeded.");
    }

    return (int) $row['id'];
}

/**
 * @param list<int> $playerIds
 * @return list<array{round:int,match:int,leg:int,a:int,b:int}>
 */
function amiga_fixture_round_robin_plan(array $playerIds, int $legs): array
{
    if (!in_array($legs, [1, 2], true)) {
        throw new RuntimeException('Legs must be 1 or 2.');
    }
    $players = array_values($playerIds);
    if (count($players) % 2 === 1) {
        $players[] = null;
    }
    $n = count($players);
    $rounds = $n - 1;
    $half = intdiv($n, 2);
    $rotation = $players;
    $firstLeg = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $match = 1;
        for ($i = 0; $i < $half; $i++) {
            $a = $rotation[$i];
            $b = $rotation[$n - 1 - $i];
            if ($a === null || $b === null) {
                continue;
            }
            if ($round % 2 === 0) {
                [$a, $b] = [$b, $a];
            }
            $firstLeg[] = ['round' => $round, 'match' => $match, 'leg' => 1, 'a' => (int) $a, 'b' => (int) $b];
            $match++;
        }
        $fixed = $rotation[0];
        $last = array_pop($rotation);
        $middle = array_slice($rotation, 1);
        $rotation = array_merge([$fixed, $last], $middle);
    }

    if ($legs === 1) {
        return $firstLeg;
    }

    $secondLeg = [];
    foreach ($firstLeg as $fixture) {
        $secondLeg[] = [
            'round' => $fixture['round'] + $rounds,
            'match' => $fixture['match'],
            'leg' => 2,
            'a' => $fixture['b'],
            'b' => $fixture['a'],
        ];
    }

    return array_merge($firstLeg, $secondLeg);
}

/**
 * @param list<int> $playerIds
 */
function amiga_fixture_create_kitchen_tournament(
    mysqli $con,
    string $name,
    string $eventDate,
    string $country,
    array $playerIds,
    int $legs
): int {
    $name = trim($name);
    $country = trim($country);
    if ($name === '') {
        throw new RuntimeException('Tournament name is required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        throw new RuntimeException('Event date must be YYYY-MM-DD.');
    }
    foreach ($playerIds as $playerId) {
        amiga_fixture_require_player($con, $playerId);
    }

    $templateId = amiga_fixture_template_id($con, 'kitchen_marathon');
    $fixtures = amiga_fixture_round_robin_plan($playerIds, $legs);
    $overrides = json_encode([
        'generated_by' => 'site.public_html.amiga.ops.fixtures',
        'round_robin_legs' => $legs,
        'fixture_count' => count($fixtures),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($overrides === false) {
        throw new RuntimeException('Could not encode tournament overrides.');
    }

    $con->begin_transaction();
    try {
        $playerCount = count($playerIds);
        $equalTeams = 0;
        $hasLeague = 1;
        $hasCup = 0;
        $isCup = 0;
        $lifecycleStatus = 'draft';
        $stmt = $con->prepare(
            'INSERT INTO tournaments '
            . '(source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count, '
            . 'format_template_id, format_overrides, has_league, has_cup, lifecycle_status) '
            . 'VALUES (NULL, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare tournament insert: ' . $con->error);
        }
        $stmt->bind_param(
            'ssisiiisiis',
            $name,
            $eventDate,
            $isCup,
            $country,
            $equalTeams,
            $playerCount,
            $templateId,
            $overrides,
            $hasLeague,
            $hasCup,
            $lifecycleStatus
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('execute tournament insert: ' . $stmt->error);
        }
        $tournamentId = (int) $stmt->insert_id;
        $stmt->close();

        $entrantStatus = 'registered';
        $stmt = $con->prepare(
            'INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note) '
            . 'VALUES (?, ?, ?, ?, NULL)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant insert: ' . $con->error);
        }
        foreach ($playerIds as $idx => $playerId) {
            $seedNo = $idx + 1;
            $stmt->bind_param('iiis', $tournamentId, $playerId, $seedNo, $entrantStatus);
            if (!$stmt->execute()) {
                throw new RuntimeException('execute entrant insert: ' . $stmt->error);
            }
        }
        $stmt->close();

        $stageKey = 'overall';
        $stageName = 'Overall';
        $stageType = 'league';
        $sequenceNo = 1;
        $stageConfig = json_encode([
            'generated_by' => 'site.public_html.amiga.ops.fixtures',
            'player_count' => $playerCount,
            'round_robin_legs' => $legs,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($stageConfig === false) {
            throw new RuntimeException('Could not encode stage config.');
        }
        $stmt = $con->prepare(
            'INSERT INTO tournament_stages '
            . '(tournament_id, parent_stage_id, stage_key, name, stage_type, track_key, sequence_no, config_json) '
            . 'VALUES (?, NULL, ?, ?, ?, NULL, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage insert: ' . $con->error);
        }
        $stmt->bind_param('isssis', $tournamentId, $stageKey, $stageName, $stageType, $sequenceNo, $stageConfig);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute stage insert: ' . $stmt->error);
        }
        $stageId = (int) $stmt->insert_id;
        $stmt->close();

        $stmt = $con->prepare(
            'INSERT INTO tournament_stage_players (stage_id, player_id, seed_no, group_key) VALUES (?, ?, ?, NULL)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare stage player insert: ' . $con->error);
        }
        foreach ($playerIds as $idx => $playerId) {
            $seedNo = $idx + 1;
            $stmt->bind_param('iii', $stageId, $playerId, $seedNo);
            if (!$stmt->execute()) {
                throw new RuntimeException('execute stage player insert: ' . $stmt->error);
            }
        }
        $stmt->close();

        $fixtureStatus = 'scheduled';
        $phaseLabel = 'Overall';
        $stmt = $con->prepare(
            'INSERT INTO tournament_fixtures '
            . '(stage_id, fixture_key, player_a_id, player_b_id, leg_no, status, phase_label) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare fixture insert: ' . $con->error);
        }
        foreach ($fixtures as $fixture) {
            $fixtureKey = sprintf('overall-r%02d-m%02d', $fixture['round'], $fixture['match']);
            $playerAId = $fixture['a'];
            $playerBId = $fixture['b'];
            $legNo = $fixture['leg'];
            $stmt->bind_param(
                'isiiiss',
                $stageId,
                $fixtureKey,
                $playerAId,
                $playerBId,
                $legNo,
                $fixtureStatus,
                $phaseLabel
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('execute fixture insert: ' . $stmt->error);
            }
        }
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return $tournamentId;
}

function amiga_fixture_assign_players(mysqli $con, int $fixtureId, int $playerAId, int $playerBId): void
{
    if ($fixtureId <= 0 || $playerAId <= 0 || $playerBId <= 0) {
        throw new RuntimeException('Fixture and player ids are required.');
    }
    if ($playerAId === $playerBId) {
        throw new RuntimeException('Fixture players must be different.');
    }
    amiga_fixture_require_player($con, $playerAId);
    amiga_fixture_require_player($con, $playerBId);

    $stmt = $con->prepare(
        'SELECT f.id, f.status, s.tournament_id '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE f.id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture assignment load: ' . $con->error);
    }
    $stmt->bind_param('i', $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture assignment load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $fixture = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($fixture === null) {
        throw new RuntimeException("Fixture {$fixtureId} not found.");
    }
    if ((string) $fixture['status'] !== 'scheduled') {
        throw new RuntimeException("Fixture {$fixtureId} is not scheduled.");
    }

    $tournamentId = (int) $fixture['tournament_id'];
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerAId);
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerBId);

    $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture assignment game count: ' . $con->error);
    }
    $stmt->bind_param('i', $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture assignment game count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ((int) ($row['n'] ?? 0) > 0) {
        throw new RuntimeException("Fixture {$fixtureId} already has an attached game.");
    }

    $stmt = $con->prepare(
        'SELECT COUNT(DISTINCT sp.player_id) AS n '
        . 'FROM tournament_stage_players sp '
        . 'INNER JOIN tournament_stages s ON s.id = sp.stage_id '
        . 'WHERE s.tournament_id = ? AND sp.player_id IN (?, ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament player check: ' . $con->error);
    }
    $stmt->bind_param('iii', $tournamentId, $playerAId, $playerBId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament player check: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ((int) ($row['n'] ?? 0) !== 2) {
        throw new RuntimeException('Fixture players must already belong to the tournament.');
    }

    $stmt = $con->prepare('UPDATE tournament_fixtures SET player_a_id = ?, player_b_id = ? WHERE id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture assignment update: ' . $con->error);
    }
    $stmt->bind_param('iii', $playerAId, $playerBId, $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture assignment update: ' . $stmt->error);
    }
    $stmt->close();
}

function amiga_fixture_record_result(mysqli $con, int $fixtureId, int $goalsA, int $goalsB, ?string $extra): int
{
    if ($goalsA < 0 || $goalsB < 0) {
        throw new RuntimeException('Goals must be non-negative.');
    }

    $stmt = $con->prepare(
        'SELECT f.id, f.player_a_id, f.player_b_id, f.status, f.phase_label, s.tournament_id '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE f.id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture load: ' . $con->error);
    }
    $stmt->bind_param('i', $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $fixture = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($fixture === null) {
        throw new RuntimeException("Fixture {$fixtureId} not found.");
    }
    if ((string) $fixture['status'] !== 'scheduled') {
        throw new RuntimeException("Fixture {$fixtureId} is not scheduled.");
    }
    if ($fixture['player_a_id'] === null || $fixture['player_b_id'] === null) {
        throw new RuntimeException('Fixture must have both players before result entry.');
    }

    $tournamentId = (int) $fixture['tournament_id'];
    $playerAId = (int) $fixture['player_a_id'];
    $playerBId = (int) $fixture['player_b_id'];
    amiga_fixture_require_running_lifecycle($con, $tournamentId);
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerAId);
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerBId);

    $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture game count: ' . $con->error);
    }
    $stmt->bind_param('i', $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture game count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ((int) ($row['n'] ?? 0) > 0) {
        throw new RuntimeException("Fixture {$fixtureId} already has an attached game.");
    }

    $sourceScoresId = amiga_fixture_next_live_source_scores_id($con);
    $gameDate = amiga_fixture_next_game_date($con);
    $extraValue = trim((string) ($extra ?? ''));
    $extraValue = $extraValue === '' ? null : $extraValue;
    $phase = $fixture['phase_label'] !== null ? (string) $fixture['phase_label'] : null;

    $con->begin_transaction();
    try {
        $stmt = $con->prepare(
            'INSERT INTO amiga_games '
            . '(source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id, phase, goals_a, goals_b, extra) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare game insert: ' . $con->error);
        }
        $stmt->bind_param(
            'isiiiisiis',
            $sourceScoresId,
            $gameDate,
            $playerAId,
            $playerBId,
            $tournamentId,
            $fixtureId,
            $phase,
            $goalsA,
            $goalsB,
            $extraValue
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('execute game insert: ' . $stmt->error);
        }
        $gameId = (int) $stmt->insert_id;
        $stmt->close();

        $stmt = $con->prepare("UPDATE tournament_fixtures SET status = 'played' WHERE id = ?");
        if ($stmt === false) {
            throw new RuntimeException('prepare fixture update: ' . $con->error);
        }
        $stmt->bind_param('i', $fixtureId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute fixture update: ' . $stmt->error);
        }
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return $gameId;
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$tournament = null;
$lifecycle = null;
/** @var list<string> */
$lifecycleTargets = [];
$fixtures = [];
$standingsRows = [];
$generatedTournaments = [];
$entrants = [];
$entrantOpsEligible = false;
$stages = [];
$stagePlayers = [];
$stageOpsEligible = false;
$playerSearchQuery = isset($_GET['player_search']) ? trim((string) $_GET['player_search']) : '';
$playerSearchResults = [];
$replacePlayerId = isset($_GET['replace_player_id']) ? max(0, (int) $_GET['replace_player_id']) : 0;
$flash = null;
$flashIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_kitchen') {
            $tournamentId = amiga_fixture_create_kitchen_tournament(
                $con,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['event_date'] ?? ''),
                (string) ($_POST['country'] ?? ''),
                amiga_fixture_parse_player_ids((string) ($_POST['player_ids'] ?? '')),
                (int) ($_POST['legs'] ?? 1)
            );
            $flash = 'Created tournament #' . $tournamentId . '.';
        } elseif ($action === 'assign_players') {
            $fixtureId = max(0, (int) ($_POST['fixture_id'] ?? 0));
            amiga_fixture_assign_players(
                $con,
                $fixtureId,
                (int) ($_POST['player_a_id'] ?? 0),
                (int) ($_POST['player_b_id'] ?? 0)
            );
            $flash = 'Assigned players to fixture #' . $fixtureId . '.';
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
        } elseif ($action === 'record_result') {
            $fixtureId = max(0, (int) ($_POST['fixture_id'] ?? 0));
            $goalsA = (int) ($_POST['goals_a'] ?? -1);
            $goalsB = (int) ($_POST['goals_b'] ?? -1);
            if ($fixtureId <= 0) {
                throw new RuntimeException('Missing fixture id.');
            }
            $gameId = amiga_fixture_record_result(
                $con,
                $fixtureId,
                $goalsA,
                $goalsB,
                isset($_POST['extra']) ? (string) $_POST['extra'] : null
            );
            $processed = amiga_process_completed_game($con, $gameId, false, false);
            if ($processed['skipped']) {
                $flashIsError = true;
                $flash = 'Created game #' . $gameId . ', but derived processing skipped: ' . (string) $processed['skip_reason'];
            } else {
                $flash = 'Recorded fixture result as game #' . $gameId . ' and processed derived standings/ratings.';
            }
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
        } elseif ($action === 'set_lifecycle_status') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $newStatus = trim((string) ($_POST['lifecycle_status'] ?? ''));
            $summary = amiga_fixture_set_lifecycle_status($con, $tournamentId, $newStatus);
            if (!$summary['changed']) {
                $flash = "Tournament #{$tournamentId} is already {$newStatus}.";
            } else {
                $flash = "Tournament #{$tournamentId} lifecycle: {$summary['previous_status']} → {$newStatus}.";
            }
        } elseif ($action === 'add_entrant') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $playerId = max(0, (int) ($_POST['player_id'] ?? 0));
            if ($playerId <= 0) {
                throw new RuntimeException('Missing player id.');
            }
            $seedRaw = trim((string) ($_POST['seed_no'] ?? ''));
            $seedNo = $seedRaw === '' ? null : max(0, (int) $seedRaw);
            $noteRaw = trim((string) ($_POST['note'] ?? ''));
            $note = $noteRaw === '' ? null : $noteRaw;
            $summary = amiga_fixture_add_entrant_existing_player($con, $tournamentId, $playerId, $seedNo, $note);
            $flash = 'Registered player #' . $summary['player_id'] . ' as entrant #' . $summary['entrant_id'] . '.';
            $flash .= ' Use stage placement / fixture assignment separately when needed.';
        } elseif ($action === 'withdraw_entrant') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $playerId = max(0, (int) ($_POST['player_id'] ?? 0));
            if ($playerId <= 0) {
                throw new RuntimeException('Missing player id.');
            }
            $noteRaw = trim((string) ($_POST['note'] ?? ''));
            $note = $noteRaw === '' ? null : $noteRaw;
            $summary = amiga_fixture_withdraw_entrant($con, $tournamentId, $playerId, $note);
            $flash = 'Withdrew player #' . $summary['player_id'] . ' ('
                . $summary['fixture_slots_cleared'] . ' fixture slot(s) cleared, '
                . $summary['stage_player_rows_removed'] . ' stage-player row(s) removed).';
            $replacePlayerId = 0;
        } elseif ($action === 'replace_entrant') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $oldPlayerId = max(0, (int) ($_POST['old_player_id'] ?? 0));
            $newPlayerId = max(0, (int) ($_POST['new_player_id'] ?? 0));
            if ($oldPlayerId <= 0 || $newPlayerId <= 0) {
                throw new RuntimeException('Old and new player ids are required.');
            }
            $noteRaw = trim((string) ($_POST['note'] ?? ''));
            $note = $noteRaw === '' ? null : $noteRaw;
            $summary = amiga_fixture_replace_entrant($con, $tournamentId, $oldPlayerId, $newPlayerId, $note);
            $flash = 'Replaced player #' . $summary['old_player_id'] . ' with #' . $summary['new_player_id']
                . ' (entrant #' . $summary['new_entrant_id'] . ', seed '
                . ($summary['seed_no'] !== null ? (string) $summary['seed_no'] : 'none') . ').';
            $replacePlayerId = 0;
        } elseif ($action === 'place_stage_entrant') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $stageId = max(0, (int) ($_POST['stage_id'] ?? 0));
            if ($stageId <= 0) {
                throw new RuntimeException('Missing stage id.');
            }
            $playerId = max(0, (int) ($_POST['player_id'] ?? 0));
            if ($playerId <= 0) {
                throw new RuntimeException('Missing player id.');
            }
            $seedRaw = trim((string) ($_POST['seed_no'] ?? ''));
            $seedNo = $seedRaw === '' ? null : max(0, (int) $seedRaw);
            $groupRaw = trim((string) ($_POST['group_key'] ?? ''));
            $groupKey = $groupRaw === '' ? null : $groupRaw;
            $summary = amiga_fixture_place_stage_entrant(
                $con,
                $tournamentId,
                $stageId,
                $playerId,
                $seedNo,
                $groupKey
            );
            $verb = $summary['updated'] ? 'Updated' : 'Placed';
            $flash = $verb . ' player #' . $summary['player_id'] . ' in stage '
                . $summary['stage_key'] . ' (stage id ' . $summary['stage_id'] . ').';
            $flash .= ' Use fixture assignment separately when needed.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flashIsError = true;
        $flash = $e->getMessage();
        if (isset($_POST['tournament_id'])) {
            $tournamentId = max(0, (int) $_POST['tournament_id']);
        }
    }
}

$sql = "
    SELECT t.id, t.name, t.event_date,
           COUNT(DISTINCT s.id) AS stage_count,
           COUNT(DISTINCT f.id) AS fixture_count,
           COUNT(DISTINCT g.id) AS game_count
    FROM tournaments t
    INNER JOIN tournament_stages s ON s.tournament_id = t.id
    LEFT JOIN tournament_fixtures f ON f.stage_id = s.id
    LEFT JOIN amiga_games g ON g.fixture_id = f.id
    WHERE t.source_id IS NULL
      AND (
        COALESCE(t.format_overrides, '') LIKE '%scripts.amiga.tournament_builder%'
        OR COALESCE(t.format_overrides, '') LIKE '%site.public_html.amiga.ops.fixtures%'
      )
    GROUP BY t.id, t.name, t.event_date
    ORDER BY t.id DESC
    LIMIT 50";
$res = $con->query($sql);
while ($res && ($row = $res->fetch_assoc())) {
    $generatedTournaments[] = $row;
}
if ($res) {
    $res->free();
}

if ($tournamentId > 0) {
    $stmt = $con->prepare(
        'SELECT id, name, event_date, source_id, format_overrides, lifecycle_status, started_at, completed_at '
        . 'FROM tournaments WHERE id = ?'
    );
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $tournament = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($tournament !== null) {
        $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
        if ($lifecycle !== null) {
            $lifecycleTargets = amiga_fixture_browser_allowed_lifecycle_targets($con, $lifecycle);
        }
    }

    $sql = "
        SELECT f.id, f.fixture_key, f.leg_no, f.status, f.phase_label,
               s.stage_key, s.name AS stage_name, s.stage_type, s.sequence_no,
               f.player_a_id, f.player_b_id,
               pa.name AS player_a_name, pb.name AS player_b_name,
               g.id AS game_id, g.goals_a, g.goals_b
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        LEFT JOIN amiga_players pa ON pa.id = f.player_a_id
        LEFT JOIN amiga_players pb ON pb.id = f.player_b_id
        LEFT JOIN amiga_games g ON g.fixture_id = f.id
        WHERE s.tournament_id = ?";
    if ($status !== '') {
        $sql .= " AND f.status = ?";
    }
    $sql .= " ORDER BY s.sequence_no ASC, s.id ASC, f.id ASC LIMIT 500";

    $stmt = $con->prepare($sql);
    if ($status !== '') {
        $stmt->bind_param('is', $tournamentId, $status);
    } else {
        $stmt->bind_param('i', $tournamentId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $fixtures[] = $row;
    }
    $stmt->close();

    $stmt = $con->prepare(
        'SELECT s.position, s.games, s.wins, s.draws, s.losses, '
        . 's.goals_for, s.goals_against, s.points, '
        . 'p.id AS player_id, p.name AS player_name '
        . 'FROM amiga_tournament_standings s '
        . 'INNER JOIN amiga_players p ON p.id = s.player_id '
        . 'WHERE s.tournament_id = ? AND s.scope_type = \'overall\' AND s.scope_key = \'\' '
        . 'ORDER BY s.position ASC, s.points DESC, (s.goals_for - s.goals_against) DESC, s.goals_for DESC'
    );
    if ($stmt !== false) {
        $stmt->bind_param('i', $tournamentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $standingsRows[] = $row;
        }
        $stmt->close();
    }

    $entrantOpsEligible = amiga_fixture_is_eligible_generated_tournament([
        'source_id' => $tournament['source_id'] !== null ? (int) $tournament['source_id'] : null,
        'format_overrides' => $tournament['format_overrides'] ?? null,
    ]);
    if ($entrantOpsEligible) {
        $entrants = amiga_fixture_list_entrants($con, $tournamentId);
        $stageOpsEligible = true;
        $stages = amiga_fixture_list_stages($con, $tournamentId);
        $stagePlayers = amiga_fixture_list_stage_players($con, $tournamentId);
    }
    if ($playerSearchQuery !== '') {
        $playerSearchResults = amiga_fixture_search_players($con, $playerSearchQuery);
    }
}

mysqli_close($con);
amiga_fixture_render_chrome_start('Amiga — Fixture manager', true);
?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Fixture manager</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Create generated tournaments, manage entrants, lifecycle, assign players, and record results.
  </p>
  <nav class="k2-player-nav k2-nav-pills k2-amiga-tournament-nav" aria-label="Live tournament tools" style="margin-bottom:1rem">
    <div class="k2-player-nav__links">
      <a href="/amiga/live-tournaments.php" class="k2-player-nav__btn">Live tournaments</a>
      <span class="k2-player-nav__btn is-active" aria-current="page">Fixture manager</span>
    </div>
  </nav>
</header>

<div class="k2-amiga-live-ops">
<?php if ($flash !== null) { ?>
  <div class="k2-amiga-live-ops__flash<?php echo $flashIsError ? ' k2-amiga-live-ops__flash--error' : ''; ?>"><?php echo k2_h($flash); ?></div>
<?php } ?>
<form class="k2-amiga-live-ops__inline-form" method="get" action="<?php echo $self; ?>">
  <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
  <label>Tournament id <input type="number" name="tournament_id" min="1" value="<?php echo $tournamentId > 0 ? (int) $tournamentId : ''; ?>"></label>
  <label>Status
    <select name="status">
      <?php foreach (['' => 'All', 'scheduled' => 'Scheduled', 'played' => 'Played', 'void' => 'Void'] as $value => $label) { ?>
        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $status === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php } ?>
    </select>
  </label>
  <button type="submit">View</button>
</form>

<div class="k2-amiga-live-ops__section">
  <h2>Create kitchen marathon</h2>
  <p class="k2-amiga-live-ops__muted">Internal ops only. Creates one generated tournament, one overall league stage, and scheduled round-robin fixtures.</p>
  <form class="k2-amiga-live-ops__grid-form" method="post" action="<?php echo $self; ?>">
    <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="create_kitchen">
    <label>Name
      <input type="text" name="name" required maxlength="120" placeholder="Thursday Kitchen I">
    </label>
    <label>Date
      <?php k2_render_day_picker('amiga-fixture-event-date', 'event_date', gmdate('Y-m-d'), 'Tournament date'); ?>
    </label>
    <label>Country
      <input type="text" name="country" maxlength="50" placeholder="Denmark">
    </label>
    <label>Legs
      <select name="legs">
        <option value="1">1</option>
        <option value="2">2</option>
      </select>
    </label>
    <label class="wide">Player ids, comma-separated
      <input type="text" name="player_ids" required placeholder="1,2,3,4">
    </label>
    <div class="wide">
      <button type="submit">Create tournament</button>
    </div>
  </form>
</div>

<?php if ($tournamentId <= 0) { ?>
  <p class="k2-amiga-live-ops__muted">Enter a generated tournament id, or pick a recent generated fixture-backed tournament below.</p>
<?php } elseif ($tournament === null) { ?>
  <p class="k2-amiga-live-ops__muted">Tournament not found.</p>
<?php } else { ?>
  <h2><?php echo k2_h((string) $tournament['name']); ?> <span class="k2-amiga-live-ops__muted">#<?php echo (int) $tournament['id']; ?></span></h2>
  <?php if ($lifecycle !== null) { ?>
    <div class="k2-amiga-live-ops__section" style="padding:.75rem 1rem;border:1px solid var(--k2-border-subtle);border-radius:var(--k2-radius-md);max-width:42rem">
      <h3>Tournament lifecycle</h3>
      <dl style="display:grid;grid-template-columns:auto 1fr;gap:.25rem 1rem;margin:0">
        <dt>Status</dt>
        <dd><span class="k2-amiga-tournament-badge"><?php echo k2_h($lifecycle['lifecycle_status']); ?></span></dd>
        <dt>Started</dt>
        <dd><?php echo $lifecycle['started_at'] !== null ? k2_h($lifecycle['started_at']) : '<span class="k2-amiga-live-ops__muted">not set</span>'; ?></dd>
        <dt>Completed</dt>
        <dd><?php echo $lifecycle['completed_at'] !== null ? k2_h($lifecycle['completed_at']) : '<span class="k2-amiga-live-ops__muted">not set</span>'; ?></dd>
      </dl>
      <?php if ($lifecycle['source_id'] !== null) { ?>
        <p class="k2-amiga-live-ops__muted">Imported historical tournament — lifecycle changes are CLI-only.</p>
      <?php } elseif ($lifecycleTargets !== []) { ?>
        <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
          <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="set_lifecycle_status">
          <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
          <label>Transition to
            <select name="lifecycle_status" required>
              <?php foreach ($lifecycleTargets as $target) { ?>
                <option value="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php } ?>
            </select>
          </label>
          <button type="submit">Apply lifecycle transition</button>
        </form>
      <?php } elseif ($lifecycle['lifecycle_status'] === 'running') { ?>
        <p class="k2-amiga-live-ops__muted">No browser transitions available: complete all scheduled fixtures before marking completed, or use CLI for force transitions.</p>
      <?php } else { ?>
        <p class="k2-amiga-live-ops__muted">No browser lifecycle transitions available from this status.</p>
      <?php } ?>
      <?php if ($lifecycle['lifecycle_status'] !== 'running') { ?>
        <p class="k2-amiga-live-ops__muted">Result entry is allowed only when lifecycle status is <strong>running</strong>.</p>
      <?php } ?>
    </div>
  <?php } ?>
  <?php if ($entrantOpsEligible) {
      $canRegisterEntrants = $lifecycle !== null
          && in_array($lifecycle['lifecycle_status'], AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES, true);
      $registeredPlayerIds = [];
      foreach ($entrants as $entrantRow) {
          if ($entrantRow['status'] === 'registered') {
              $registeredPlayerIds[$entrantRow['player_id']] = true;
          }
      }
      $replaceEntrant = null;
      if ($replacePlayerId > 0) {
          foreach ($entrants as $entrantRow) {
              if ($entrantRow['player_id'] === $replacePlayerId) {
                  $replaceEntrant = $entrantRow;
                  break;
              }
          }
      }
      ?>
  <div class="k2-amiga-live-ops__section">
    <h3>Tournament entrants</h3>
    <p class="k2-amiga-live-ops__muted">Generated tournament only. Add, withdraw, or replace existing players with the same guardrails as the CLI.</p>
    <?php if ($entrants === []) { ?>
      <p class="k2-amiga-live-ops__muted">No entrants registered yet.</p>
    <?php } else { ?>
      <div class="k2-table-wrap">
      <table class="k2-table k2-table--calm-stats">
        <thead>
          <tr>
            <th class="k2-table-cell--left">Player</th>
            <th>Seed</th>
            <th>Status</th>
            <th class="k2-table-cell--left">Note</th>
            <th class="k2-table-cell--left">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($entrants as $entrantRow) { ?>
          <tr>
            <td class="k2-table-cell--left"><?php echo k2_h($entrantRow['player_name']); ?> <span class="k2-amiga-live-ops__muted">#<?php echo (int) $entrantRow['player_id']; ?></span></td>
            <td><?php echo $entrantRow['seed_no'] !== null ? (int) $entrantRow['seed_no'] : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
            <td><span class="k2-amiga-tournament-badge"><?php echo k2_h($entrantRow['status']); ?></span></td>
            <td class="k2-table-cell--left"><?php echo $entrantRow['note'] !== null && $entrantRow['note'] !== '' ? k2_h($entrantRow['note']) : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
            <td class="k2-table-cell--left">
              <?php if ($entrantRow['status'] === 'registered') { ?>
                <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>" style="margin-bottom:.35rem">
                  <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="action" value="withdraw_entrant">
                  <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                  <input type="hidden" name="player_id" value="<?php echo (int) $entrantRow['player_id']; ?>">
                  <input type="text" name="note" maxlength="120" placeholder="note (optional)" aria-label="Withdrawal note for player <?php echo (int) $entrantRow['player_id']; ?>">
                  <button type="submit">Withdraw</button>
                </form>
                <?php
                $replaceUrl = $self
                    . '?once=' . rawurlencode($key)
                    . '&pwd=' . rawurlencode($pwdValue)
                    . '&tournament_id=' . (int) $tournamentId
                    . '&replace_player_id=' . (int) $entrantRow['player_id'];
                if ($status !== '') {
                    $replaceUrl .= '&status=' . rawurlencode($status);
                }
                ?>
                <a href="<?php echo htmlspecialchars($replaceUrl, ENT_QUOTES, 'UTF-8'); ?>">Replace…</a>
              <?php } else { ?>
                <span class="k2-amiga-live-ops__muted">—</span>
              <?php } ?>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      </div>
    <?php } ?>

    <h4 style="margin-top:1.25rem">Search existing players</h4>
    <?php if ($replaceEntrant !== null) { ?>
      <p class="k2-amiga-live-ops__muted">Replacing <?php echo k2_h($replaceEntrant['player_name']); ?> (#<?php echo (int) $replaceEntrant['player_id']; ?>). Pick a replacement below.</p>
    <?php } elseif (!$canRegisterEntrants && $replacePlayerId <= 0) { ?>
      <p class="k2-amiga-live-ops__muted">New entrant registration requires lifecycle draft, registration, or ready.</p>
    <?php } ?>
    <form class="k2-amiga-live-ops__inline-form" method="get" action="<?php echo $self; ?>">
      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
      <?php if ($status !== '') { ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
      <?php } ?>
      <?php if ($replacePlayerId > 0) { ?>
        <input type="hidden" name="replace_player_id" value="<?php echo (int) $replacePlayerId; ?>">
      <?php } ?>
      <label>Player id or name
        <input type="search" name="player_search" value="<?php echo k2_h($playerSearchQuery); ?>" placeholder="id or name fragment" required>
      </label>
      <button type="submit">Search</button>
      <?php if ($replacePlayerId > 0) {
          $cancelReplaceUrl = $self
              . '?once=' . rawurlencode($key)
              . '&pwd=' . rawurlencode($pwdValue)
              . '&tournament_id=' . (int) $tournamentId;
          if ($status !== '') {
              $cancelReplaceUrl .= '&status=' . rawurlencode($status);
          }
          ?>
        <a href="<?php echo htmlspecialchars($cancelReplaceUrl, ENT_QUOTES, 'UTF-8'); ?>">Cancel replace</a>
      <?php } ?>
    </form>

    <?php if ($playerSearchQuery !== '') { ?>
      <?php if ($playerSearchResults === []) { ?>
        <p class="k2-amiga-live-ops__muted">No players matched <?php echo k2_h($playerSearchQuery); ?>.</p>
      <?php } else { ?>
        <div class="k2-table-wrap" style="margin-top:.75rem">
        <table class="k2-table k2-table--calm-stats">
          <thead>
            <tr><th class="k2-table-cell--left">Player</th><th class="k2-table-cell--left">Country</th><th class="k2-table-cell--left">Action</th></tr>
          </thead>
          <tbody>
          <?php foreach ($playerSearchResults as $playerRow) {
              $isRegisteredEntrant = isset($registeredPlayerIds[$playerRow['id']]);
              ?>
            <tr>
              <td class="k2-table-cell--left"><?php echo k2_h($playerRow['name']); ?> <span class="k2-amiga-live-ops__muted">#<?php echo (int) $playerRow['id']; ?></span></td>
              <td class="k2-table-cell--left"><?php echo $playerRow['country'] !== null && $playerRow['country'] !== '' ? k2_h($playerRow['country']) : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
              <td class="k2-table-cell--left">
                <?php if ($replaceEntrant !== null && $playerRow['id'] !== $replaceEntrant['player_id']) { ?>
                  <?php if ($isRegisteredEntrant) { ?>
                    <span class="k2-amiga-live-ops__muted">already an entrant</span>
                  <?php } else { ?>
                    <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
                      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="replace_entrant">
                      <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                      <input type="hidden" name="old_player_id" value="<?php echo (int) $replaceEntrant['player_id']; ?>">
                      <input type="hidden" name="new_player_id" value="<?php echo (int) $playerRow['id']; ?>">
                      <input type="text" name="note" maxlength="120" placeholder="note (optional)" aria-label="Replacement note">
                      <button type="submit">Replace with this player</button>
                    </form>
                  <?php } ?>
                <?php } elseif ($replaceEntrant === null && $canRegisterEntrants) { ?>
                  <?php if ($isRegisteredEntrant) { ?>
                    <span class="k2-amiga-live-ops__muted">already an entrant</span>
                  <?php } else { ?>
                    <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
                      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="add_entrant">
                      <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                      <input type="hidden" name="player_id" value="<?php echo (int) $playerRow['id']; ?>">
                      <input type="number" name="seed_no" min="1" placeholder="seed" aria-label="Seed for player <?php echo (int) $playerRow['id']; ?>">
                      <input type="text" name="note" maxlength="120" placeholder="note (optional)" aria-label="Registration note for player <?php echo (int) $playerRow['id']; ?>">
                      <button type="submit">Add entrant</button>
                    </form>
                  <?php } ?>
                <?php } else { ?>
                  <span class="k2-amiga-live-ops__muted">—</span>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
        </div>
      <?php } ?>
    <?php } ?>
  </div>
  <?php if ($stageOpsEligible) {
      $canPlaceStageEntrants = $lifecycle !== null
          && in_array($lifecycle['lifecycle_status'], AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES, true);
      $stagePlayersByStage = [];
      foreach ($stagePlayers as $stagePlayerRow) {
          $stagePlayersByStage[$stagePlayerRow['stage_id']][] = $stagePlayerRow;
      }
      ?>
  <div class="k2-amiga-live-ops__section">
    <h3>Stage players</h3>
    <p class="k2-amiga-live-ops__muted">Place registered entrants into tournament stages with the same guardrails as <code>fixtures place-entrant</code>. Does not generate or reschedule fixtures.</p>
    <?php if ($stages === []) { ?>
      <p class="k2-amiga-live-ops__muted">No stages defined for this tournament.</p>
    <?php } else { ?>
      <?php foreach ($stages as $stageRow) {
          $stageId = (int) $stageRow['id'];
          $playersInStage = $stagePlayersByStage[$stageId] ?? [];
          ?>
      <div style="margin-bottom:1rem">
        <h4 style="margin:0 0 .35rem"><?php echo k2_h($stageRow['name']); ?> <span class="k2-amiga-live-ops__muted">(<?php echo k2_h($stageRow['stage_key']); ?> · <?php echo k2_h($stageRow['stage_type']); ?>)</span></h4>
        <?php if ($playersInStage === []) { ?>
          <p class="k2-amiga-live-ops__muted">No players in this stage yet.</p>
        <?php } else { ?>
          <div class="k2-table-wrap">
          <table class="k2-table k2-table--calm-stats">
            <thead>
              <tr><th class="k2-table-cell--left">Player</th><th>Seed</th><th>Group</th></tr>
            </thead>
            <tbody>
            <?php foreach ($playersInStage as $stagePlayerRow) { ?>
              <tr>
                <td class="k2-table-cell--left"><?php echo k2_h($stagePlayerRow['player_name']); ?> <span class="k2-amiga-live-ops__muted">#<?php echo (int) $stagePlayerRow['player_id']; ?></span></td>
                <td><?php echo $stagePlayerRow['seed_no'] !== null ? (int) $stagePlayerRow['seed_no'] : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
                <td><?php echo $stagePlayerRow['group_key'] !== null && $stagePlayerRow['group_key'] !== '' ? k2_h($stagePlayerRow['group_key']) : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          </div>
        <?php } ?>
      </div>
      <?php } ?>

      <h4 style="margin-top:1.25rem">Place or update stage entrant</h4>
      <?php if (!$canPlaceStageEntrants) { ?>
        <p class="k2-amiga-live-ops__muted">Stage placement requires lifecycle draft, registration, or ready.</p>
      <?php } else { ?>
        <form class="k2-amiga-live-ops__grid-form" method="post" action="<?php echo $self; ?>" style="max-width:36rem">
          <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="place_stage_entrant">
          <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
          <label>Stage
            <select name="stage_id" required>
              <?php foreach ($stages as $stageRow) { ?>
                <option value="<?php echo (int) $stageRow['id']; ?>"><?php echo k2_h($stageRow['name']); ?> (<?php echo k2_h($stageRow['stage_key']); ?>)</option>
              <?php } ?>
            </select>
          </label>
          <label>Registered entrant
            <select name="player_id" required>
              <option value="">— select —</option>
              <?php foreach ($entrants as $entrantRow) {
                  if ($entrantRow['status'] !== 'registered') {
                      continue;
                  }
                  ?>
                <option value="<?php echo (int) $entrantRow['player_id']; ?>"><?php echo k2_h($entrantRow['player_name']); ?> (#<?php echo (int) $entrantRow['player_id']; ?>)</option>
              <?php } ?>
            </select>
          </label>
          <label>Seed (optional)
            <input type="number" name="seed_no" min="1" placeholder="seed">
          </label>
          <label>Group key (optional)
            <input type="text" name="group_key" maxlength="32" placeholder="e.g. A">
          </label>
          <div class="wide">
            <button type="submit">Place in stage</button>
          </div>
        </form>
        <p class="k2-amiga-live-ops__muted" style="margin-top:.5rem">Late-entrant workflow: add entrant → place in stage → assign fixture slots below. Re-submitting updates seed/group for an existing stage player.</p>
      <?php } ?>
    <?php } ?>
  </div>
  <?php } ?>
  <?php } elseif ($tournament !== null && $tournament['source_id'] !== null) { ?>
    <p class="k2-amiga-live-ops__muted">Imported historical tournament — entrant management is CLI-only.</p>
  <?php } ?>
  <p class="k2-amiga-live-ops__muted"><?php echo count($fixtures); ?> fixture<?php echo count($fixtures) === 1 ? '' : 's'; ?> shown.</p>
  <div class="k2-table-wrap">
  <table class="k2-table k2-table--calm-stats">
    <thead>
      <tr><th class="k2-table-cell--left">ID</th><th class="k2-table-cell--left">Stage</th><th class="k2-table-cell--left">Key</th><th class="k2-table-cell--left">Players</th><th>Status</th><th class="k2-table-cell--left">Result</th></tr>
    </thead>
    <tbody>
    <?php foreach ($fixtures as $row) { ?>
      <tr>
        <td class="k2-table-cell--left"><?php echo (int) $row['id']; ?></td>
        <td class="k2-table-cell--left"><?php echo k2_h((string) $row['stage_name']); ?><br><span class="k2-amiga-live-ops__muted"><?php echo k2_h((string) $row['stage_type']); ?></span></td>
        <td class="k2-table-cell--left"><?php echo k2_h((string) $row['fixture_key']); ?></td>
        <td class="k2-table-cell--left">
          <?php echo k2_h((string) ($row['player_a_name'] ?? 'TBD')); ?> vs <?php echo k2_h((string) ($row['player_b_name'] ?? 'TBD')); ?>
          <?php if ($row['status'] === 'scheduled' && ($row['player_a_id'] === null || $row['player_b_id'] === null)) { ?>
            <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
              <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="action" value="assign_players">
              <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
              <input type="hidden" name="fixture_id" value="<?php echo (int) $row['id']; ?>">
              <input type="number" name="player_a_id" min="1" required placeholder="Player A" aria-label="Player A id">
              <input type="number" name="player_b_id" min="1" required placeholder="Player B" aria-label="Player B id">
              <button type="submit">Assign</button>
            </form>
          <?php } ?>
        </td>
        <td><span class="k2-amiga-tournament-badge"><?php echo k2_h((string) $row['status']); ?></span></td>
        <td class="k2-table-cell--left"><?php
            if ($row['game_id'] !== null) {
                echo (int) $row['goals_a'] . '-' . (int) $row['goals_b'] . ' ';
                echo '<span class="k2-amiga-live-ops__muted">game #' . (int) $row['game_id'] . '</span>';
            } elseif (
                $row['status'] === 'scheduled'
                && $row['player_a_id'] !== null
                && $row['player_b_id'] !== null
                && $lifecycle !== null
                && $lifecycle['lifecycle_status'] === 'running'
            ) {
                ?>
                <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
                  <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="action" value="record_result">
                  <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                  <input type="hidden" name="fixture_id" value="<?php echo (int) $row['id']; ?>">
                  <input type="number" name="goals_a" min="0" max="99" required aria-label="Goals A">
                  <span>-</span>
                  <input type="number" name="goals_b" min="0" max="99" required aria-label="Goals B">
                  <input type="text" name="extra" maxlength="100" placeholder="extra" aria-label="Extra">
                  <button type="submit">Record</button>
                </form>
                <?php
            } elseif (
                $row['status'] === 'scheduled'
                && $row['player_a_id'] !== null
                && $row['player_b_id'] !== null
                && $lifecycle !== null
                && $lifecycle['lifecycle_status'] !== 'running'
            ) {
                echo '<span class="k2-amiga-live-ops__muted">result entry requires running lifecycle</span>';
            } else {
                echo '<span class="k2-amiga-live-ops__muted">not played</span>';
            }
        ?></td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
  </div>
  <div class="k2-amiga-live-ops__section">
    <h2>Overall standings</h2>
    <?php if ($standingsRows === []) { ?>
      <p class="k2-amiga-live-ops__muted">No derived standings yet. Enter a fixture result to populate this table.</p>
    <?php } else { ?>
      <div class="k2-table-wrap">
      <table class="k2-table k2-table--numeric-default k2-table--calm-stats">
        <thead>
          <tr><th>Pos</th><th class="k2-table-cell--left">Player</th><th>Games</th><th>W-D-L</th><th>Goals</th><th>GD</th><th>Pts</th></tr>
        </thead>
        <tbody>
        <?php foreach ($standingsRows as $row) {
            $gf = (int) $row['goals_for'];
            $ga = (int) $row['goals_against'];
            $gd = $gf - $ga;
            ?>
          <tr>
            <td><?php echo (int) $row['position']; ?></td>
            <td class="k2-table-cell--left"><?php echo k2_h((string) $row['player_name']); ?> <span class="k2-amiga-live-ops__muted">#<?php echo (int) $row['player_id']; ?></span></td>
            <td><?php echo (int) $row['games']; ?></td>
            <td><?php echo (int) $row['wins']; ?>-<?php echo (int) $row['draws']; ?>-<?php echo (int) $row['losses']; ?></td>
            <td><?php echo $gf; ?>-<?php echo $ga; ?></td>
            <td><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
            <td><strong><?php echo (int) $row['points']; ?></strong></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      </div>
    <?php } ?>
  </div>
<?php } ?>

<div class="k2-amiga-live-ops__section">
  <h2>Generated fixture-backed tournaments</h2>
  <?php if ($generatedTournaments === []) { ?>
    <p class="k2-amiga-live-ops__muted">No generated fixture-backed tournaments currently exist in this database.</p>
  <?php } else { ?>
    <div class="k2-table-wrap">
    <table class="k2-table k2-table--numeric-default k2-table--calm-stats">
      <thead>
        <tr><th class="k2-table-cell--left">ID</th><th class="k2-table-cell--left">Tournament</th><th>Date</th><th>Stages</th><th>Fixtures</th><th>Games</th><th class="k2-table-cell--left"></th></tr>
      </thead>
      <tbody>
      <?php foreach ($generatedTournaments as $row) {
          $viewUrl = $self
              . '?once=' . rawurlencode($key)
              . '&pwd=' . rawurlencode($pwdValue)
              . '&tournament_id=' . (int) $row['id'];
          ?>
        <tr>
          <td class="k2-table-cell--left"><?php echo (int) $row['id']; ?></td>
          <td class="k2-table-cell--left"><?php echo k2_h((string) $row['name']); ?></td>
          <td><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '<span class="k2-amiga-live-ops__muted">none</span>'; ?></td>
          <td><?php echo (int) $row['stage_count']; ?></td>
          <td><?php echo (int) $row['fixture_count']; ?></td>
          <td><?php echo (int) $row['game_count']; ?></td>
          <td class="k2-table-cell--left"><a href="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>">view fixtures</a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>
  <?php } ?>
</div>
</div>
<?php amiga_fixture_render_chrome_end();
