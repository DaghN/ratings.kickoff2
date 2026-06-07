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
        'SELECT id, name, event_date, source_id, lifecycle_status, started_at, completed_at '
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
}

mysqli_close($con);
amiga_fixture_render_chrome_start('Amiga — Fixture manager', true);
?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Fixture manager</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Create generated tournaments, manage lifecycle, assign players, and record results.
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
