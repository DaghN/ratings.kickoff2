<?php
/**
 * Internal fixture browser/result entry for fixture-backed Amiga tournaments.
 *
 * Open: /amiga/ops/fixtures.php?once=amiga-fixtures-one-shot
 * Gate: organizer password via POST (admin password also accepted). Session kept;
 * do not put pwd in the URL.
 *
 * Password file: amiga/_ops/amiga_ops_password.local.php ($organizer_password / $admin_password).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_registry.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_player_naming.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_running_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_scoring_contract.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_match_extensions.php';
require_once __DIR__ . '/modules/process_completed_game.php';
require_once __DIR__ . '/modules/finalize_tournament.php';
require_once __DIR__ . '/includes/amiga_promote_running_tournament.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';
require_once __DIR__ . '/../includes/amiga_ops_password_lib.php';

const AMIGA_FIXTURE_LIVE_SOURCE_SCORES_ID_BASE = 1000000000;

$key = 'amiga-fixtures-one-shot';
$onceValue = (string) ($_GET['once'] ?? $_POST['once'] ?? '');

if ($onceValue !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$gate = amiga_ops_gate('organizer');
$pwdProvided = $gate['provided'];
$pwdOk = $gate['ok'];
// Keep empty for URL builders — auth is session-based after the POST gate.
$pwdValue = '';
$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/ops/fixtures.php', ENT_QUOTES, 'UTF-8');

function amiga_fixture_render_chrome_start(string $pageTitle, bool $withDayPickerAssets = false, bool $withSortableTableAssets = false): void
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
<?php if ($withSortableTableAssets) { $k2RankedCloak = true; } ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php if ($withSortableTableAssets) { ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php } ?>
<?php
    if ($withDayPickerAssets) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_day_picker.php';
        k2_render_day_picker_assets();
        $organizerPickerJs = $_SERVER['DOCUMENT_ROOT'] . '/js/amiga-organizer-player-picker.js';
        if (is_file($organizerPickerJs)) {
            echo '<script type="text/javascript" src="/js/amiga-organizer-player-picker.js?v='
                . (int) @filemtime($organizerPickerJs) . '" defer="defer"></script>' . "\n";
        }
        $organizerCountryJs = $_SERVER['DOCUMENT_ROOT'] . '/js/amiga-organizer-country-picker.js';
        if (is_file($organizerCountryJs)) {
            echo '<script type="text/javascript" src="/js/amiga-organizer-country-picker.js?v='
                . (int) @filemtime($organizerCountryJs) . '" defer="defer"></script>' . "\n";
        }
        $organizerPlayerCreateJs = $_SERVER['DOCUMENT_ROOT'] . '/js/amiga-organizer-player-create.js';
        if (is_file($organizerPlayerCreateJs)) {
            echo '<script type="text/javascript" src="/js/amiga-organizer-player-create.js?v='
                . (int) @filemtime($organizerPlayerCreateJs) . '" defer="defer"></script>' . "\n";
        }
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
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php';
    echo "</body>\n</html>";
}

if (!$pwdOk) {
    amiga_fixture_render_chrome_start('Amiga — Tournament organizer');
    ?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Tournament organizer</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">Enter the organizer password to create or open a league.</p>
</header>
<div class="k2-amiga-live-ops">
<?php if ($pwdProvided) { ?>
  <div class="k2-amiga-live-ops__flash k2-amiga-live-ops__flash--error">Incorrect password.</div>
<?php } ?>
  <form method="post" action="<?php echo $self; ?>" class="k2-amiga-live-ops__grid-form" style="max-width:24rem">
    <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $gateTournamentId = isset($_GET['tournament_id']) ? max(0, (int) $_GET['tournament_id']) : (isset($_POST['tournament_id']) ? max(0, (int) $_POST['tournament_id']) : 0);
    if ($gateTournamentId > 0) {
        ?>
    <input type="hidden" name="tournament_id" value="<?php echo $gateTournamentId; ?>">
    <?php } ?>
    <label>Organizer password
      <input type="password" id="pwd" name="pwd" autocomplete="current-password" required autofocus>
    </label>
    <div class="wide"><button type="submit">Continue</button></div>
  </form>
</div>
<?php
    amiga_fixture_render_chrome_end();
    exit;
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

/** @var list<string> */
const AMIGA_FIXTURE_OPS_VIEWS = ['setup', 'players', 'fixtures', 'table', 'results', 'advanced'];

function amiga_fixture_ops_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function amiga_fixture_ops_flash_set(string $message, bool $isError = false): void
{
    amiga_fixture_ops_session_start();
    $_SESSION['amiga_ops_flash'] = ['message' => $message, 'error' => $isError];
}

/**
 * @return array{message:string,error:bool}|null
 */
function amiga_fixture_ops_flash_consume(): ?array
{
    amiga_fixture_ops_session_start();
    if (!isset($_SESSION['amiga_ops_flash'])) {
        return null;
    }
    $flash = $_SESSION['amiga_ops_flash'];
    unset($_SESSION['amiga_ops_flash']);

    return $flash;
}

function amiga_fixture_ops_redirect(
    string $self,
    string $onceKey,
    string $pwd,
    int $tournamentId,
    string $view,
    string $status = ''
): void {
    $params = [
        'once' => $onceKey,
        'view' => $view,
    ];
    if ($tournamentId > 0) {
        $params['tournament_id'] = $tournamentId;
    }
    if ($status !== '') {
        $params['status'] = $status;
    }
    header('Location: ' . $self . '?' . http_build_query($params));
    exit;
}

/**
 * @param list<int> $ids
 * @return list<int>
 */
function amiga_fixture_validate_player_id_list(array $ids): array
{
    $normalized = [];
    foreach ($ids as $raw) {
        $id = (int) $raw;
        if ($id > 0) {
            $normalized[] = $id;
        }
    }
    $normalized = array_values(array_unique($normalized));
    if (count($normalized) < 2) {
        throw new RuntimeException('At least two players are required.');
    }

    return $normalized;
}

/**
 * @return list<int>
 */
function amiga_fixture_collect_player_ids_from_request(): array
{
    if (isset($_POST['player_ids']) && is_array($_POST['player_ids'])) {
        $ids = [];
        foreach ($_POST['player_ids'] as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids !== []) {
            return amiga_fixture_validate_player_id_list($ids);
        }
    }
    throw new RuntimeException('At least two players are required.');
}

function amiga_fixture_expected_round_robin_fixtures(int $playerCount, int $legs): int
{
    if ($playerCount < 2) {
        return 0;
    }
    $legs = max(1, min(2, $legs));

    return (int) (($playerCount * ($playerCount - 1) / 2) * $legs);
}

/**
 * @param list<int> $ids
 * @return list<array{id:int,name:string}>
 */
function amiga_fixture_load_player_summaries(mysqli $con, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $summaries = [];
    $seen = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $stmt = $con->prepare('SELECT id, name FROM amiga_players WHERE id = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('prepare player summary: ' . $con->error);
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute player summary: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row !== null) {
            $summaries[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }
    }

    return $summaries;
}

/**
 * @return array{
 *   name:string,
 *   event_date:string,
 *   country:string,
 *   legs:int,
 *   player_ids:list<int>,
 *   new_player_full:string,
 *   new_player_country:string,
 *   new_player_preview:string
 * }
 */
function amiga_fixture_create_draft_from_request(): array
{
    $playerIds = [];
    if (isset($_POST['player_ids']) && is_array($_POST['player_ids'])) {
        foreach ($_POST['player_ids'] as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $playerIds[] = $id;
            }
        }
    } elseif (isset($_GET['cp_player']) && is_array($_GET['cp_player'])) {
        foreach ($_GET['cp_player'] as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $playerIds[] = $id;
            }
        }
    }
    $playerIds = array_values(array_unique($playerIds));
    $legs = (int) ($_POST['legs'] ?? $_GET['cp_legs'] ?? 1);

    return [
        'name' => trim((string) ($_POST['name'] ?? $_GET['cp_name'] ?? '')),
        'event_date' => trim((string) ($_POST['event_date'] ?? $_GET['cp_date'] ?? gmdate('Y-m-d'))),
        'country' => trim((string) ($_POST['country'] ?? $_GET['cp_country'] ?? '')),
        'legs' => max(1, min(2, $legs)),
        'player_ids' => $playerIds,
        'is_world_cup' => (($_POST['is_world_cup'] ?? $_GET['cp_is_world_cup'] ?? '') === '1'),
        'new_player_full' => trim((string) ($_POST['new_player_full_name'] ?? $_GET['cp_new_full'] ?? '')),
        'new_player_country' => trim((string) ($_POST['new_player_country'] ?? $_GET['cp_new_country'] ?? '')),
        'new_player_preview' => trim((string) ($_POST['new_player_preview'] ?? $_GET['cp_new_preview'] ?? '')),
    ];
}

/**
 * @param array{name:string,event_date:string,country:string,legs:int,player_ids:list<int>,is_world_cup?:bool} $draft
 */
function amiga_fixture_create_draft_query(array $draft, string $createPlayerSearch = ''): array
{
    $params = [
        'cp_name' => $draft['name'],
        'cp_date' => $draft['event_date'],
        'cp_country' => $draft['country'],
        'cp_legs' => $draft['legs'],
        'view' => 'setup',
    ];
    foreach ($draft['player_ids'] as $playerId) {
        $params['cp_player'][] = $playerId;
    }
    if ($createPlayerSearch !== '') {
        $params['create_player_search'] = $createPlayerSearch;
    }
    if (($draft['new_player_full'] ?? '') !== '') {
        $params['cp_new_full'] = $draft['new_player_full'];
    }
    if (($draft['new_player_country'] ?? '') !== '') {
        $params['cp_new_country'] = $draft['new_player_country'];
    }
    if (($draft['new_player_preview'] ?? '') !== '') {
        $params['cp_new_preview'] = $draft['new_player_preview'];
    }
    if (!empty($draft['is_world_cup'])) {
        $params['cp_is_world_cup'] = '1';
    }

    return $params;
}

function amiga_fixture_validate_create_country(string $country): string
{
    $country = trim($country);
    if (!k2_amiga_country_validate_token($country)) {
        throw new RuntimeException('Choose a valid country from the list.');
    }

    return $country;
}

/**
 * @param list<string> $usedOfficialNames
 * @param list<array<string, mixed>> $moreRows
 */
function amiga_fixture_render_create_country_field(string $selectedCountry, array $usedOfficialNames, array $moreRows): void
{
    $selectedCountry = trim($selectedCountry);
    $selectedInMore = false;
    if ($selectedCountry !== '') {
        $selectedInMore = true;
        foreach ($usedOfficialNames as $usedName) {
            if (strcasecmp($usedName, $selectedCountry) === 0) {
                $selectedInMore = false;
                break;
            }
        }
    }
    $moreOptionsJson = [];
    foreach ($moreRows as $row) {
        $officialName = trim((string) ($row['official_name'] ?? ''));
        if ($officialName === '') {
            continue;
        }
        $moreOptionsJson[] = [
            'value' => $officialName,
            'label' => k2_amiga_country_display_name($officialName),
            'selected' => strcasecmp($officialName, $selectedCountry) === 0,
        ];
    }
    $moreJsonAttr = htmlspecialchars(
        json_encode($moreOptionsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
      <div class="k2-amiga-organizer-create__country">
        <label for="amiga-organizer-country">Country
          <select name="country" id="amiga-organizer-country" required<?php echo $moreOptionsJson !== [] ? ' data-amiga-more-countries="' . $moreJsonAttr . '"' : ''; ?>>
            <option value="">Choose country…</option>
            <?php foreach ($usedOfficialNames as $officialName) {
                $isSelected = !$selectedInMore && strcasecmp($officialName, $selectedCountry) === 0;
                ?>
            <option value="<?php echo k2_h($officialName); ?>"<?php echo $isSelected ? ' selected' : ''; ?>><?php echo k2_h(k2_amiga_country_display_name($officialName)); ?></option>
            <?php } ?>
          </select>
        </label>
        <?php if ($moreOptionsJson !== []) { ?>
        <p class="k2-amiga-organizer-create__country-more">
          <input type="checkbox" id="amiga-organizer-country-more"<?php echo $selectedInMore ? ' checked' : ''; ?>>
          <label for="amiga-organizer-country-more">More countries…</label>
        </p>
        <?php } ?>
      </div>
    <?php
}

/**
 * @param list<string> $usedOfficialNames
 * @param list<array<string, mixed>> $moreRows
 */
function amiga_fixture_render_new_player_country_field(
    string $selectedCountry,
    array $usedOfficialNames,
    array $moreRows
): void {
    $selectedCountry = trim($selectedCountry);
    $selectedInMore = false;
    if ($selectedCountry !== '') {
        $selectedInMore = true;
        foreach ($usedOfficialNames as $usedName) {
            if (strcasecmp($usedName, $selectedCountry) === 0) {
                $selectedInMore = false;
                break;
            }
        }
    }
    $moreOptionsJson = [];
    foreach ($moreRows as $row) {
        $officialName = trim((string) ($row['official_name'] ?? ''));
        if ($officialName === '') {
            continue;
        }
        $moreOptionsJson[] = [
            'value' => $officialName,
            'label' => k2_amiga_country_display_name($officialName),
            'selected' => strcasecmp($officialName, $selectedCountry) === 0,
        ];
    }
    $moreJsonAttr = htmlspecialchars(
        json_encode($moreOptionsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
      <div class="k2-amiga-organizer-create__country k2-amiga-organizer-create__player-country">
        <label for="amiga-organizer-player-country">Nationality
          <select name="new_player_country" id="amiga-organizer-player-country" required<?php echo $moreOptionsJson !== [] ? ' data-amiga-more-countries="' . $moreJsonAttr . '"' : ''; ?>>
            <option value="">Choose country…</option>
            <?php foreach ($usedOfficialNames as $officialName) {
                $isSelected = !$selectedInMore && strcasecmp($officialName, $selectedCountry) === 0;
                ?>
            <option value="<?php echo k2_h($officialName); ?>"<?php echo $isSelected ? ' selected' : ''; ?>><?php echo k2_h(k2_amiga_country_display_name($officialName)); ?></option>
            <?php } ?>
          </select>
        </label>
        <?php if ($moreOptionsJson !== []) { ?>
        <p class="k2-amiga-organizer-create__country-more">
          <input type="checkbox" id="amiga-organizer-player-country-more"<?php echo $selectedInMore ? ' checked' : ''; ?>>
          <label for="amiga-organizer-player-country-more">More countries…</label>
        </p>
        <?php } ?>
      </div>
    <?php
}

/**
 * @param array{
 *   name:string,
 *   event_date:string,
 *   country:string,
 *   legs:int,
 *   player_ids:list<int>,
 *   new_player_full:string,
 *   new_player_country:string,
 *   new_player_preview:string
 * } $draft
 */
function amiga_fixture_redirect_create_compose(
    string $self,
    string $onceKey,
    string $pwd,
    array $draft,
    ?string $flashMessage = null,
    bool $flashError = false
): void {
    if ($flashMessage !== null) {
        amiga_fixture_ops_flash_set($flashMessage, $flashError);
    }
    $params = array_merge(
        ['once' => $onceKey, 'view' => 'setup'],
        amiga_fixture_create_draft_query($draft)
    );
    header('Location: ' . $self . '?' . http_build_query($params));
    exit;
}

function amiga_fixture_ops_url(
    string $self,
    string $onceKey,
    string $pwd,
    int $tournamentId,
    string $view,
    string $status = '',
    array $extra = []
): string {
    $params = array_merge([
        'once' => $onceKey,
        'view' => $view,
    ], $extra);
    if ($tournamentId > 0) {
        $params['tournament_id'] = $tournamentId;
    }
    if ($status !== '') {
        $params['status'] = $status;
    }

    return $self . '?' . http_build_query($params);
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

/**
 * Mark remaining scheduled fixtures void (unplayed / abandoned). Used when finishing early.
 *
 * @return int Number of fixtures voided
 */
function amiga_fixture_void_remaining_scheduled_fixtures(mysqli $con, int $tournamentId): int
{
    $stmt = $con->prepare(
        'UPDATE tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'SET f.status = ? '
        . 'WHERE s.tournament_id = ? AND f.status = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare void remaining scheduled: ' . $con->error);
    }
    $voidStatus = 'void';
    $scheduledStatus = 'scheduled';
    $stmt->bind_param('sis', $voidStatus, $tournamentId, $scheduledStatus);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute void remaining scheduled: ' . $stmt->error);
    }
    $n = (int) $stmt->affected_rows;
    $stmt->close();

    return $n;
}

/** Official L3 game rows for a tournament (not running fixture scores). */
function amiga_fixture_count_official_tournament_games(mysqli $con, int $tournamentId): int
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

/** @deprecated Use amiga_fixture_count_official_tournament_games() for clarity. */
function amiga_fixture_count_tournament_games(mysqli $con, int $tournamentId): int
{
    return amiga_fixture_count_official_tournament_games($con, $tournamentId);
}

function amiga_fixture_count_played_fixtures(mysqli $con, int $tournamentId): int
{
    return amiga_running_tournament_count_played_fixtures($con, $tournamentId);
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
    if ($current === 'draft' || $current === 'registration') {
        return ['ready'];
    }
    if ($current === 'ready') {
        return ['running'];
    }
    if ($current === 'running') {
        $targets = [];
        // Partial finish allowed: unplayed scheduled fixtures are voided on finish/complete.
        if (amiga_fixture_count_played_fixtures($con, $tournamentId) > 0
            || amiga_fixture_count_scheduled_fixtures($con, $tournamentId) === 0
        ) {
            $targets[] = 'completed';
        }
        if (amiga_fixture_count_official_tournament_games($con, $tournamentId) === 0) {
            $targets[] = 'void';
        }

        return $targets;
    }

    return [];
}

function amiga_fixture_organizer_status_label(string $status): string
{
    if ($status === 'draft' || $status === 'registration') {
        return 'Not started';
    }
    if ($status === 'ready') {
        return 'Ready to start';
    }
    if ($status === 'running') {
        return 'In progress';
    }
    if ($status === 'completed' || $status === 'archived') {
        return 'Finished';
    }
    if ($status === 'void') {
        return 'Void';
    }

    return $status;
}

function amiga_fixture_organizer_status_badge_modifier(string $status): string
{
    if ($status === 'draft' || $status === 'registration') {
        return 'not-started';
    }
    if ($status === 'ready') {
        return 'ready';
    }
    if ($status === 'running') {
        return 'running';
    }
    if ($status === 'completed' || $status === 'archived') {
        return 'finished';
    }
    if ($status === 'void') {
        return 'void';
    }

    return 'default';
}

/**
 * Organizer-facing lifecycle summary for Setup tab rendering.
 *
 * Status mapping: draft/registration → Not started; ready → Ready to start;
 * running → In progress; completed/archived → Finished; void → Void.
 *
 * @param array{id:int,name:string,source_id:?int,lifecycle_status:string,started_at:?string,completed_at:?string} $lifecycle
 * @return array{
 *   label:string,
 *   badge_modifier:string,
 *   raw_status:string,
 *   is_imported:bool,
 *   is_read_only:bool,
 *   can_start:bool,
 *   can_void:bool,
 *   finish_hint:?string,
 *   scheduled_remaining:int,
 *   game_count:int
 * }
 */
function amiga_fixture_organizer_lifecycle_ui(mysqli $con, array $lifecycle): array
{
    $rawStatus = $lifecycle['lifecycle_status'];
    $isImported = $lifecycle['source_id'] !== null;
    $scheduledRemaining = amiga_fixture_count_scheduled_fixtures($con, $lifecycle['id']);
    $playedFixtureCount = amiga_fixture_count_played_fixtures($con, $lifecycle['id']);
    $officialGameCount = amiga_fixture_count_official_tournament_games($con, $lifecycle['id']);
    $gameCount = amiga_running_tournament_broadcast_mode($con, $lifecycle['id'])
        ? $playedFixtureCount
        : $officialGameCount;
    $allowed = $isImported ? [] : amiga_fixture_browser_allowed_lifecycle_targets($con, $lifecycle);

    $canStart = !$isImported
        && in_array($rawStatus, ['draft', 'registration', 'ready'], true)
        && (in_array('running', $allowed, true) || in_array('ready', $allowed, true));
    $canVoid = !$isImported && in_array('void', $allowed, true);

    $finishHint = null;
    if ($rawStatus === 'running' && !$isImported) {
        if ($playedFixtureCount > 0) {
            if ($scheduledRemaining > 0) {
                $fixtureWord = $scheduledRemaining === 1 ? 'match' : 'matches';
                $finishHint = $scheduledRemaining . ' unplayed ' . $fixtureWord
                    . ' remain — you can still use '
                    . AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL
                    . ' on the Table tab (unplayed matches become void).';
            } else {
                $finishHint = 'All match results are in — use '
                    . AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL
                    . ' on the Table tab to commit ratings and close the league.';
            }
        }
    }

    $isReadOnly = $isImported
        || in_array($rawStatus, ['completed', 'archived', 'void'], true);

    return [
        'label' => amiga_fixture_organizer_status_label($rawStatus),
        'badge_modifier' => amiga_fixture_organizer_status_badge_modifier($rawStatus),
        'raw_status' => $rawStatus,
        'is_imported' => $isImported,
        'is_read_only' => $isReadOnly,
        'can_start' => $canStart,
        'can_void' => $canVoid,
        'finish_hint' => $finishHint,
        'scheduled_remaining' => $scheduledRemaining,
        'game_count' => $gameCount,
    ];
}

/**
 * Apply an organizer-friendly lifecycle action via existing transition guardrails.
 *
 * start_tournament: draft/registration → ready → running; ready → running.
 *
 * @return array{
 *   tournament_id:int,
 *   action:string,
 *   previous_status:string,
 *   lifecycle_status:string,
 *   changed:bool,
 *   steps:list<string>
 * }
 */
function amiga_fixture_apply_organizer_lifecycle_action(mysqli $con, int $tournamentId, string $action): array
{
    $validActions = ['start_tournament', 'void_tournament'];
    if (!in_array($action, $validActions, true)) {
        throw new RuntimeException('Unknown organizer lifecycle action.');
    }

    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }

    $previousStatus = $lifecycle['lifecycle_status'];
    $steps = [];

    if ($action === 'start_tournament') {
        if (!in_array($previousStatus, ['draft', 'registration', 'ready'], true)) {
            throw new RuntimeException(
                "Tournament {$tournamentId} cannot be started from status '{$previousStatus}'."
            );
        }
        if (in_array($previousStatus, ['draft', 'registration'], true)) {
            $readySummary = amiga_fixture_set_lifecycle_status($con, $tournamentId, 'ready');
            if ($readySummary['changed']) {
                $steps[] = "{$previousStatus} → ready";
            }
        }
        $runningSummary = amiga_fixture_set_lifecycle_status($con, $tournamentId, 'running');

        return [
            'tournament_id' => $tournamentId,
            'action' => $action,
            'previous_status' => $previousStatus,
            'lifecycle_status' => $runningSummary['lifecycle_status'],
            'changed' => $previousStatus !== $runningSummary['lifecycle_status'] || $steps !== [],
            'steps' => array_merge($steps, $runningSummary['changed'] ? ['ready → running'] : []),
        ];
    }

    $targetStatus = 'void';
    $summary = amiga_fixture_set_lifecycle_status($con, $tournamentId, $targetStatus);

    return [
        'tournament_id' => $tournamentId,
        'action' => $action,
        'previous_status' => $summary['previous_status'],
        'lifecycle_status' => $summary['lifecycle_status'],
        'changed' => $summary['changed'],
        'steps' => $summary['changed'] ? ["{$summary['previous_status']} → {$targetStatus}"] : [],
    ];
}

function amiga_fixture_organizer_fixture_status_label(string $status): string
{
    if ($status === 'scheduled') {
        return 'Scheduled';
    }
    if ($status === 'played') {
        return 'Played';
    }
    if ($status === 'void') {
        return 'Void';
    }

    return $status;
}

function amiga_fixture_organizer_fixture_status_modifier(string $status): string
{
    if ($status === 'scheduled') {
        return 'scheduled';
    }
    if ($status === 'played') {
        return 'played';
    }
    if ($status === 'void') {
        return 'void';
    }

    return 'default';
}

/**
 * @param array<string, mixed> $fixture
 */
function amiga_fixture_schedule_group_key(array $fixture): string
{
    $fixtureKey = (string) ($fixture['fixture_key'] ?? '');
    if (preg_match('/-r(\d+)-/', $fixtureKey, $matches)) {
        return 'round:' . (int) $matches[1];
    }
    $legNo = isset($fixture['leg_no']) ? (int) $fixture['leg_no'] : 0;
    if ($legNo > 0) {
        return 'leg:' . $legNo;
    }
    $phase = trim((string) ($fixture['phase_label'] ?? ''));
    if ($phase !== '') {
        return 'phase:' . $phase;
    }

    return 'other:0';
}

/**
 * @param array<string, mixed> $fixture
 */
function amiga_fixture_schedule_group_label(string $groupKey, array $fixture): string
{
    if (preg_match('/^round:(\d+)$/', $groupKey, $matches)) {
        return 'Round ' . (int) $matches[1];
    }
    if (preg_match('/^leg:(\d+)$/', $groupKey, $matches)) {
        return 'Leg ' . (int) $matches[1];
    }
    if (preg_match('/^phase:(.+)$/', $groupKey, $matches)) {
        return $matches[1];
    }

    return 'Fixtures';
}

/**
 * @param list<array<string, mixed>> $fixtures
 * @return list<array{label:string,group_key:string,fixtures:list<array<string, mixed>>}>
 */
function amiga_fixture_group_fixtures_for_schedule(array $fixtures): array
{
    /** @var array<string, array{label:string,group_key:string,fixtures:list<array<string, mixed>>}> */
    $groups = [];
    /** @var list<string> */
    $order = [];
    foreach ($fixtures as $fixture) {
        $key = amiga_fixture_schedule_group_key($fixture);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'label' => amiga_fixture_schedule_group_label($key, $fixture),
                'group_key' => $key,
                'fixtures' => [],
            ];
            $order[] = $key;
        }
        $groups[$key]['fixtures'][] = $fixture;
    }

    $result = [];
    foreach ($order as $key) {
        $result[] = $groups[$key];
    }

    return $result;
}

/**
 * Split fixtures for the Results tab: playable entry rows, played context, skipped counts.
 *
 * @param list<array<string, mixed>> $fixtures
 * @return array{
 *   playable:list<array<string, mixed>>,
 *   played:list<array<string, mixed>>,
 *   skipped_void:int,
 *   skipped_incomplete:int
 * }
 */
function amiga_fixture_partition_for_results(array $fixtures): array
{
    $playable = [];
    $played = [];
    $skippedVoid = 0;
    $skippedIncomplete = 0;

    foreach ($fixtures as $fixture) {
        $status = (string) ($fixture['status'] ?? '');
        if ($status === 'void') {
            $skippedVoid++;
            continue;
        }
        if ($status === 'played' || amiga_running_tournament_fixture_has_result($fixture)) {
            $played[] = $fixture;
            continue;
        }
        if ($status === 'scheduled') {
            if ($fixture['player_a_id'] === null || $fixture['player_b_id'] === null) {
                $skippedIncomplete++;
                continue;
            }
            $playable[] = $fixture;
        }
    }

    return [
        'playable' => $playable,
        'played' => $played,
        'skipped_void' => $skippedVoid,
        'skipped_incomplete' => $skippedIncomplete,
    ];
}

/**
 * @param list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string}> $standingsRows
 * @param list<array{id:int,player_id:int,player_name:string,country:string,seed_no:?int,status:string,note:?string}> $entrants
 * @return array{
 *   rows:list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string,country:string}>,
 *   is_preview:bool,
 *   preview_note:?string
 * }
 */
function amiga_fixture_organizer_table_rows(array $standingsRows, array $entrants): array
{
    $registered = array_values(array_filter(
        $entrants,
        static fn (array $entrant): bool => $entrant['status'] === 'registered'
    ));
    $roster = [];
    foreach ($registered as $entrant) {
        $roster[] = [
            'player_id' => (int) $entrant['player_id'],
            'player_name' => (string) $entrant['player_name'],
            'country' => (string) ($entrant['country'] ?? ''),
        ];
    }

    return amiga_running_tournament_merged_league_table_rows($standingsRows, $roster);
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
        $unplayed = amiga_fixture_void_remaining_scheduled_fixtures($con, $tournamentId);
        if (amiga_fixture_count_played_fixtures($con, $tournamentId) === 0
            && amiga_fixture_count_official_tournament_games($con, $tournamentId) === 0
        ) {
            throw new RuntimeException(
                "Tournament {$tournamentId} has no played fixtures; refusing transition to completed."
            );
        }
    }
    if ($status === 'void') {
        $officialGameCount = amiga_fixture_count_official_tournament_games($con, $tournamentId);
        if ($officialGameCount > 0) {
            throw new RuntimeException(
                "Tournament {$tournamentId} has {$officialGameCount} official game(s); refusing transition to void."
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

/**
 * @param list<int> $playerIds
 */
function amiga_fixture_require_stage_players(mysqli $con, int $stageId, array $playerIds): void
{
    if ($playerIds === []) {
        return;
    }
    $placeholders = implode(', ', array_fill(0, count($playerIds), '?'));
    $sql = 'SELECT player_id FROM tournament_stage_players WHERE stage_id = ? AND player_id IN (' . $placeholders . ')';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare stage player check: ' . $con->error);
    }
    $types = 'i' . str_repeat('i', count($playerIds));
    $params = array_merge([$stageId], $playerIds);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage player check: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $found = [];
    if ($res !== false) {
        while ($row = $res->fetch_assoc()) {
            $found[(int) $row['player_id']] = true;
        }
    }
    $stmt->close();
    foreach ($playerIds as $playerId) {
        if (!isset($found[$playerId])) {
            throw new RuntimeException(
                "Player {$playerId} is not placed in stage {$stageId}; "
                . 'fixture players must belong to the fixture\'s stage.'
            );
        }
    }
}

/** @var list<string> */
const AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES = ['draft', 'registration', 'ready'];
const AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL = 'Finish and make official';

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
 * @param list<array{stage_id:int,stage_key:string,stage_name:string,stage_type:string,player_id:int,player_name:string,seed_no:?int,group_key:?string}> $rows
 * @return array<int, list<array{stage_id:int,stage_key:string,stage_name:string,stage_type:string,player_id:int,player_name:string,seed_no:?int,group_key:?string}>>
 */
function amiga_fixture_stage_players_by_stage(array $rows): array
{
    $byStage = [];
    foreach ($rows as $row) {
        $byStage[$row['stage_id']][] = $row;
    }

    return $byStage;
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
        'SELECT e.id, e.player_id, p.name AS player_name, p.country, e.seed_no, e.status, e.note '
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
            'country' => $row['country'] !== null ? (string) $row['country'] : '',
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
 * @return list<array{id:int,status:string,player_a_id:?int,player_b_id:?int,goals_a:?int,goals_b:?int,has_result:bool}>
 */
function amiga_fixture_load_player_fixtures(mysqli $con, int $tournamentId, int $playerId): array
{
    $stmt = $con->prepare(
        'SELECT f.id, f.status, f.player_a_id, f.player_b_id, f.goals_a, f.goals_b '
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
            'goals_a' => $row['goals_a'] !== null ? (int) $row['goals_a'] : null,
            'goals_b' => $row['goals_b'] !== null ? (int) $row['goals_b'] : null,
            'has_result' => (string) $row['status'] === 'played'
                || ($row['goals_a'] !== null && $row['goals_b'] !== null),
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
        if ($fixture['has_result']) {
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
        if ($fixture['has_result']) {
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
    int $legs,
    bool $isWorldCup = false,
): int {
    $name = trim($name);
    $country = trim($country);
    if ($name === '') {
        throw new RuntimeException('Tournament name is required.');
    }
    amiga_tournament_validate_is_world_cup_correspondence($name, $isWorldCup);
    if (!k2_amiga_country_validate_token($country)) {
        throw new RuntimeException('Choose a valid country from the list.');
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
        $isWorldCupInt = $isWorldCup ? 1 : 0;
        $lifecycleStatus = 'draft';
        $stmt = $con->prepare(
            'INSERT INTO tournaments '
            . '(source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count, '
            . 'format_template_id, format_overrides, has_league, has_cup, is_world_cup, lifecycle_status) '
            . 'VALUES (NULL, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare tournament insert: ' . $con->error);
        }
        // Types: name s, event_date s, is_cup i, country s, equal_teams i,
        // player_count i, format_template_id i, format_overrides s, has_league i,
        // has_cup i, is_world_cup i, lifecycle_status s.
        // (Wrong 'ssisiisiisis' stored format_overrides as 0 — Recent leagues hid the row.)
        $stmt->bind_param(
            'ssisiiisiiis',
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
            $isWorldCupInt,
            $lifecycleStatus
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('execute tournament insert: ' . $stmt->error);
        }
        $tournamentId = (int) $stmt->insert_id;
        $stmt->close();
        amiga_scoring_contract_ensure_tournament_defaults($con, $tournamentId);

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
        $stageName = 'League table';
        $stageType = 'round_robin';
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
        amiga_scoring_contract_ensure_stage($con, $stageId, $stageType);

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
        $phaseLabel = 'League table';
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

/**
 * Repair kitchen leagues whose format_overrides were stored as "0" (bad bind_param types).
 * Restores JSON from stage config + fixture count so Recent leagues / eligibility work again.
 *
 * @return int Number of tournaments repaired
 */
function amiga_fixture_repair_broken_kitchen_format_overrides(mysqli $con): int
{
    $marker = 'site.public_html.amiga.ops.fixtures';
    $sql = "
        SELECT t.id,
               s.config_json,
               (SELECT COUNT(*) FROM tournament_fixtures f
                INNER JOIN tournament_stages s2 ON s2.id = f.stage_id
                WHERE s2.tournament_id = t.id) AS fixture_count
        FROM tournaments t
        INNER JOIN tournament_stages s ON s.tournament_id = t.id
        WHERE t.source_id IS NULL
          AND (
            t.format_overrides IS NULL
            OR t.format_overrides = ''
            OR t.format_overrides = '0'
          )
          AND COALESCE(s.config_json, '') LIKE CONCAT('%', ?, '%')
        GROUP BY t.id, s.config_json
        ORDER BY t.id ASC
        LIMIT 50";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param('s', $marker);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $res = $stmt->get_result();
    $repaired = 0;
    $update = $con->prepare('UPDATE tournaments SET format_overrides = ? WHERE id = ? AND source_id IS NULL');
    if ($update === false) {
        $stmt->close();
        return 0;
    }
    while ($res && ($row = $res->fetch_assoc())) {
        $config = json_decode((string) ($row['config_json'] ?? ''), true);
        if (!is_array($config)) {
            continue;
        }
        $generatedBy = (string) ($config['generated_by'] ?? '');
        if ($generatedBy !== $marker && !str_starts_with($generatedBy, $marker)) {
            continue;
        }
        $legs = (int) ($config['round_robin_legs'] ?? 1);
        if ($legs < 1) {
            $legs = 1;
        }
        $fixtureCount = (int) ($row['fixture_count'] ?? 0);
        $overrides = json_encode([
            'generated_by' => $marker,
            'round_robin_legs' => $legs,
            'fixture_count' => $fixtureCount,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($overrides === false) {
            continue;
        }
        $tournamentId = (int) $row['id'];
        $update->bind_param('si', $overrides, $tournamentId);
        if ($update->execute() && $update->affected_rows > 0) {
            $repaired++;
        }
    }
    $update->close();
    $stmt->close();

    return $repaired;
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
        'SELECT f.id, f.status, f.stage_id, f.goals_a, f.goals_b, s.tournament_id '
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
    if ($fixture['goals_a'] !== null || $fixture['goals_b'] !== null) {
        throw new RuntimeException("Fixture {$fixtureId} already has a running result.");
    }

    $tournamentId = (int) $fixture['tournament_id'];
    $stageId = (int) $fixture['stage_id'];
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerAId);
    amiga_fixture_require_active_entrant($con, $tournamentId, $playerBId);
    amiga_fixture_require_stage_players($con, $stageId, [$playerAId, $playerBId]);

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
    if (amiga_ops_tournament_rating_finalized($con, $tournamentId)) {
        throw new RuntimeException(
            'Tournament is rating-finalized. Run full derived rebuild before entering more results: '
            . '`python -m scripts.amiga prove`'
        );
    }
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
        throw new RuntimeException("Fixture {$fixtureId} already has an official game attached.");
    }

    $extraValue = trim((string) ($extra ?? ''));
    $extraValue = $extraValue === '' ? null : $extraValue;
    $structured = amiga_extract_structured_from_extra($extraValue);
    $goalsEtA = $structured['goals_et_a'] ?? null;
    $goalsEtB = $structured['goals_et_b'] ?? null;
    $pensA = $structured['pens_a'] ?? null;
    $pensB = $structured['pens_b'] ?? null;

    $con->begin_transaction();
    try {
        $stmt = $con->prepare(
            "UPDATE tournament_fixtures SET goals_a = ?, goals_b = ?, extra = ?, "
            . "goals_et_a = ?, goals_et_b = ?, pens_a = ?, pens_b = ?, "
            . "result_recorded_at = UTC_TIMESTAMP(), status = 'played' WHERE id = ?"
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare fixture result update: ' . $con->error);
        }
        $stmt->bind_param('iisiiiii', $goalsA, $goalsB, $extraValue, $goalsEtA, $goalsEtB, $pensA, $pensB, $fixtureId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute fixture result update: ' . $stmt->error);
        }
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return $fixtureId;
}

/**
 * @return list<int>
 */
function amiga_fixture_list_tournament_unrated_game_ids(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT g.id FROM amiga_games g '
        . 'WHERE g.tournament_id = ? '
        . 'AND NOT EXISTS (SELECT 1 FROM amiga_game_ratings r WHERE r.game_id = g.id) '
        . 'ORDER BY g.game_date ASC, g.id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament unrated games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament unrated games: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ids = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $ids[] = (int) $row['id'];
    }
    $stmt->close();

    return $ids;
}

/**
 * Complete lifecycle for an already-official running tournament (RTB-1–8 limbo repair).
 */
function amiga_fixture_try_complete_official_tournament_lifecycle(mysqli $con, int $tournamentId): bool
{
    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null) {
        return false;
    }
    $current = $lifecycle['lifecycle_status'];
    if (in_array($current, ['completed', 'archived'], true)) {
        return false;
    }
    if ($current !== 'running') {
        return false;
    }

    $summary = amiga_fixture_set_lifecycle_status($con, $tournamentId, 'completed');

    return $summary['changed'] || $summary['lifecycle_status'] === 'completed';
}

/**
 * Explicit Advanced repair only — not the Finish happy path.
 * Clears incomplete Finish state while lifecycle is still running.
 * Inventory is intentionally narrow (flag, ratings, standings, snapshots); other
 * post-commit tables may remain — kitchen drills only; serious limbo → pull/repair.
 */
function amiga_fixture_reset_incomplete_finalize(mysqli $con, int $tournamentId): void
{
    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null || $lifecycle['lifecycle_status'] !== 'running') {
        throw new RuntimeException(
            "Tournament {$tournamentId} reset refused: lifecycle must still be running."
        );
    }

    $con->begin_transaction();
    try {
        $stmt = $con->prepare(
            'UPDATE tournaments SET rating_finalized = 0, rating_finalized_at = NULL, '
            . 'scoring_frozen_at = NULL, frozen_scoring_schema_version = NULL WHERE id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare reset incomplete finalize flag: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute reset incomplete finalize flag: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $con->prepare(
            'DELETE r FROM amiga_game_ratings r '
            . 'INNER JOIN amiga_games g ON g.id = r.game_id '
            . 'WHERE g.tournament_id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare reset game_ratings: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute reset game_ratings: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $con->prepare('DELETE FROM amiga_tournament_standings WHERE tournament_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare reset standings: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute reset standings: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $con->prepare('DELETE FROM amiga_player_event_snapshots WHERE tournament_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare reset snapshots: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute reset snapshots: ' . $stmt->error);
        }
        $stmt->close();

        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }
}

/**
 * Organizer finish (Finish and make official): promote + finalize + lifecycle completed.
 *
 * @return array{
 *   processed:int,
 *   failed_game_id:?int,
 *   skip_reason:?string,
 *   lifecycle_completed:bool,
 *   voided_scheduled:int
 * }
 */
function amiga_fixture_reprocess_tournament_derived(mysqli $con, int $tournamentId): array
{
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    if (amiga_ops_tournament_rating_finalized($con, $tournamentId)) {
        $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
        if ($lifecycle !== null && $lifecycle['lifecycle_status'] === 'running') {
            // Honest limbo — never silent-rewind on Finish (drift risk).
            return [
                'processed' => 0,
                'failed_game_id' => null,
                'skip_reason' => 'finalize_limbo',
                'lifecycle_completed' => false,
                'voided_scheduled' => 0,
            ];
        }
        $lifecycleCompleted = amiga_fixture_try_complete_official_tournament_lifecycle($con, $tournamentId);

        return [
            'processed' => 0,
            'failed_game_id' => null,
            'skip_reason' => $lifecycleCompleted ? 'lifecycle_repaired' : 'already_finalized',
            'lifecycle_completed' => $lifecycleCompleted,
            'voided_scheduled' => 0,
        ];
    }

    $lifecycle = amiga_fixture_load_lifecycle($con, $tournamentId);
    if ($lifecycle === null || $lifecycle['lifecycle_status'] !== 'running') {
        return [
            'processed' => 0,
            'failed_game_id' => null,
            'skip_reason' => 'lifecycle_not_running',
            'lifecycle_completed' => false,
            'voided_scheduled' => 0,
        ];
    }

    if (amiga_fixture_count_played_fixtures($con, $tournamentId) === 0
        && amiga_fixture_count_official_tournament_games($con, $tournamentId) === 0
    ) {
        return [
            'processed' => 0,
            'failed_game_id' => null,
            'skip_reason' => 'no_played_fixtures',
            'lifecycle_completed' => false,
            'voided_scheduled' => 0,
        ];
    }

    $voidedScheduled = amiga_fixture_void_remaining_scheduled_fixtures($con, $tournamentId);

    if (amiga_fixture_count_official_tournament_games($con, $tournamentId) === 0) {
        $promote = amiga_promote_running_tournament($con, $tournamentId, false);
        if ($promote['skipped'] && ($promote['skip_reason'] ?? null) === 'games_already_exist') {
            return [
                'processed' => 0,
                'failed_game_id' => null,
                'skip_reason' => 'promote_refused_games_exist',
                'lifecycle_completed' => false,
                'voided_scheduled' => $voidedScheduled,
            ];
        }
    }
    $gameIds = amiga_fixture_list_tournament_unrated_game_ids($con, $tournamentId);
    // Finalize clears+rewrites ratings when not yet official, so empty unrated + existing games is OK.

    try {
        $result = amiga_finalize_tournament($con, $tournamentId, false);
    } catch (Throwable $e) {
        return [
            'processed' => 0,
            'failed_game_id' => $gameIds[0] ?? null,
            'skip_reason' => $e->getMessage(),
            'lifecycle_completed' => false,
            'voided_scheduled' => $voidedScheduled,
        ];
    }

    try {
        $lifecycleSummary = amiga_fixture_set_lifecycle_status($con, $tournamentId, 'completed');
        $lifecycleCompleted = $lifecycleSummary['changed']
            || $lifecycleSummary['lifecycle_status'] === 'completed';
    } catch (Throwable $e) {
        return [
            'processed' => (int) ($result['games'] ?? 0),
            'failed_game_id' => null,
            'skip_reason' => 'lifecycle_complete_failed: ' . $e->getMessage(),
            'lifecycle_completed' => false,
            'voided_scheduled' => $voidedScheduled,
        ];
    }

    return [
        'processed' => (int) ($result['games'] ?? 0),
        'failed_game_id' => null,
        'skip_reason' => null,
        'lifecycle_completed' => $lifecycleCompleted,
        'voided_scheduled' => $voidedScheduled,
    ];
}

function amiga_fixture_undo_unprocessed_result(mysqli $con, int $fixtureId): void
{
    $stmt = $con->prepare(
        'SELECT f.id, f.status, s.tournament_id '
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
    if ((string) $fixture['status'] !== 'played') {
        throw new RuntimeException("Fixture {$fixtureId} is not played.");
    }

    $tournamentId = (int) $fixture['tournament_id'];
    amiga_fixture_require_generated_tournament($con, $tournamentId);
    amiga_fixture_require_running_lifecycle($con, $tournamentId);
    if (amiga_ops_tournament_rating_finalized($con, $tournamentId)) {
        throw new RuntimeException(
            'This tournament is already official. Undo is not available in the browser.'
        );
    }

    $stmt = $con->prepare(
        'SELECT g.id FROM amiga_games g WHERE g.fixture_id = ? ORDER BY g.id ASC LIMIT 2'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare fixture games: ' . $con->error);
    }
    $stmt->bind_param('i', $fixtureId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute fixture games: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $gameIds = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $gameIds[] = (int) $row['id'];
    }
    $stmt->close();
    if ($gameIds !== []) {
        if (count($gameIds) > 1) {
            throw new RuntimeException("Fixture {$fixtureId} has multiple official games; undo refused.");
        }
        $gameId = $gameIds[0];
        if (amiga_ops_game_rating_exists($con, $gameId)) {
            throw new RuntimeException(
                'This result was already processed into ratings and standings. '
                . 'Undo is not available in the browser — use CLI replay to repair derived tables.'
            );
        }
        throw new RuntimeException(
            "Fixture {$fixtureId} has an official game row; running undo refused. Use repair tooling."
        );
    }

    $con->begin_transaction();
    try {
        $stmt = $con->prepare(
            "UPDATE tournament_fixtures SET goals_a = NULL, goals_b = NULL, extra = NULL, "
            . "goals_et_a = NULL, goals_et_b = NULL, pens_a = NULL, pens_b = NULL, "
            . "result_recorded_at = NULL, status = 'scheduled' WHERE id = ?"
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare fixture reopen: ' . $con->error);
        }
        $stmt->bind_param('i', $fixtureId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute fixture reopen: ' . $stmt->error);
        }
        $stmt->close();
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }
}

amiga_fixture_ops_session_start();

$tournamentId = isset($_GET['tournament_id']) ? max(0, (int) $_GET['tournament_id']) : 0;
if (isset($_POST['tournament_id'])) {
    $tournamentId = max(0, (int) $_POST['tournament_id']);
}
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!in_array($status, ['', 'scheduled', 'played', 'void'], true)) {
    $status = '';
}
$view = isset($_GET['view']) ? (string) $_GET['view'] : '';
if (!in_array($view, AMIGA_FIXTURE_OPS_VIEWS, true)) {
    $view = '';
}
if ($tournamentId > 0) {
    if ($view === '') {
        $view = 'fixtures';
    }
} else {
    $view = 'setup';
}
$createDraft = amiga_fixture_create_draft_from_request();
$createSelectedPlayers = [];

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
amiga_fixture_repair_broken_kitchen_format_overrides($con);

$tournament = null;
$lifecycle = null;
/** @var list<string> */
$lifecycleTargets = [];
/** @var array<string, mixed>|null */
$organizerLifecycleUi = null;
$fixtures = [];
$standingsRows = [];
$generatedTournaments = [];
$entrants = [];
$entrantOpsEligible = false;
$tournamentUnratedGameCount = 0;
$tournamentGameCount = 0;
$tournamentCanMakeOfficial = false;
$tournamentRatingFinalized = false;
$scheduledFixtureRemaining = 0;
/** @var array<int, bool> */
$fixtureResultRated = [];
$stages = [];
$stagePlayers = [];
/** @var array<int, list<array{stage_id:int,stage_key:string,stage_name:string,stage_type:string,player_id:int,player_name:string,seed_no:?int,group_key:?string}>> */
$stagePlayersByStage = [];
$stageOpsEligible = false;
$playerSearchQuery = isset($_GET['player_search']) ? trim((string) $_GET['player_search']) : '';
$playerSearchResults = [];
$replacePlayerId = isset($_GET['replace_player_id']) ? max(0, (int) $_GET['replace_player_id']) : 0;
$sessionFlash = amiga_fixture_ops_flash_consume();
$flash = $sessionFlash !== null ? (string) $sessionFlash['message'] : null;
$flashIsError = $sessionFlash !== null ? (bool) $sessionFlash['error'] : false;
$createFormError = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $tournamentId <= 0) {
    $createMutated = false;
    if (isset($_GET['create_clear_new_player'])) {
        $createDraft['new_player_full'] = '';
        $createDraft['new_player_country'] = '';
        $createDraft['new_player_preview'] = '';
        $createMutated = true;
    } elseif (isset($_GET['create_add_player_id'])) {
        $addId = max(0, (int) $_GET['create_add_player_id']);
        if ($addId > 0 && !in_array($addId, $createDraft['player_ids'], true)) {
            $createDraft['player_ids'][] = $addId;
            $createMutated = true;
        }
    } elseif (isset($_GET['create_remove_player_id'])) {
        $removeId = max(0, (int) $_GET['create_remove_player_id']);
        if ($removeId > 0) {
            k2_amiga_player_try_delete_orphan($con, $removeId, null);
            $createDraft['player_ids'] = array_values(array_filter(
                $createDraft['player_ids'],
                static function (int $id) use ($removeId): bool {
                    return $id !== $removeId;
                }
            ));
            $createMutated = true;
        }
    }
    if ($createMutated) {
        $params = array_merge(
            [
                'once' => $key,
                'view' => 'setup',
            ],
            amiga_fixture_create_draft_query($createDraft)
        );
        header('Location: ' . $self . '?' . http_build_query($params));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postStatus = isset($_POST['status']) ? (string) $_POST['status'] : $status;
    if (!in_array($postStatus, ['', 'scheduled', 'played', 'void'], true)) {
        $postStatus = '';
    }
    try {
        if ($action === 'suggest_player') {
            if ($tournamentId > 0) {
                throw new RuntimeException('Create player is only available on the compose league screen.');
            }
            $fullName = trim((string) ($_POST['new_player_full_name'] ?? ''));
            $country = trim((string) ($_POST['new_player_country'] ?? ''));
            if ($fullName === '') {
                throw new RuntimeException('Enter the newcomer’s full name (first and surname).');
            }
            if (!k2_amiga_country_validate_token($country)) {
                throw new RuntimeException('Choose a valid nationality from the list.');
            }
            $suggestion = k2_amiga_suggest_koa_display_name($con, $fullName);
            if (!$suggestion['available'] || $suggestion['suggested_name'] === null) {
                throw new RuntimeException((string) ($suggestion['reason'] ?? 'Could not suggest a KOA display name.'));
            }
            $createDraft = amiga_fixture_create_draft_from_request();
            $createDraft['new_player_full'] = $fullName;
            $createDraft['new_player_country'] = $country;
            $createDraft['new_player_preview'] = (string) $suggestion['suggested_name'];
            amiga_fixture_redirect_create_compose(
                $self,
                $key,
                $pwdValue,
                $createDraft,
                'Suggested ladder name: ' . $createDraft['new_player_preview'] . ' — confirm below to create.'
            );
        } elseif ($action === 'create_player') {
            if ($tournamentId > 0) {
                throw new RuntimeException('Create player is only available on the compose league screen.');
            }
            $fullName = trim((string) ($_POST['new_player_full_name'] ?? ''));
            $country = trim((string) ($_POST['new_player_country'] ?? ''));
            $preview = trim((string) ($_POST['new_player_preview'] ?? ''));
            if ($fullName === '' || $preview === '') {
                throw new RuntimeException('Preview a KOA name before creating the player.');
            }
            $suggestion = k2_amiga_suggest_koa_display_name($con, $fullName);
            if (
                !$suggestion['available']
                || $suggestion['suggested_name'] === null
                || strcasecmp((string) $suggestion['suggested_name'], $preview) !== 0
            ) {
                throw new RuntimeException('Name suggestion changed — preview again before creating.');
            }
            $created = k2_amiga_player_create_live($con, $fullName, $country);
            $createDraft = amiga_fixture_create_draft_from_request();
            $createDraft['new_player_full'] = '';
            $createDraft['new_player_country'] = '';
            $createDraft['new_player_preview'] = '';
            if (!in_array($created['player_id'], $createDraft['player_ids'], true)) {
                $createDraft['player_ids'][] = $created['player_id'];
            }
            amiga_fixture_redirect_create_compose(
                $self,
                $key,
                $pwdValue,
                $createDraft,
                'Created ' . $created['name'] . ' and added to the draft roster.'
            );
        } elseif ($action === 'create_kitchen') {
            $createDraft = [
                'name' => trim((string) ($_POST['name'] ?? '')),
                'event_date' => trim((string) ($_POST['event_date'] ?? '')),
                'country' => trim((string) ($_POST['country'] ?? '')),
                'legs' => max(1, min(2, (int) ($_POST['legs'] ?? 1))),
                'player_ids' => [],
                'is_world_cup' => (($_POST['is_world_cup'] ?? '') === '1'),
            ];
            $playerIds = amiga_fixture_collect_player_ids_from_request();
            $createDraft['player_ids'] = $playerIds;
            $createDraft['country'] = amiga_fixture_validate_create_country($createDraft['country']);
            $tournamentId = amiga_fixture_create_kitchen_tournament(
                $con,
                $createDraft['name'],
                $createDraft['event_date'],
                $createDraft['country'],
                $playerIds,
                $createDraft['legs'],
                (bool) $createDraft['is_world_cup']
            );
            amiga_fixture_ops_flash_set('Created league #' . $tournamentId . '. Fixtures are ready to preview.');
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'fixtures');
        } elseif ($action === 'assign_players') {
            $fixtureId = max(0, (int) ($_POST['fixture_id'] ?? 0));
            amiga_fixture_assign_players(
                $con,
                $fixtureId,
                (int) ($_POST['player_a_id'] ?? 0),
                (int) ($_POST['player_b_id'] ?? 0)
            );
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
            amiga_fixture_ops_flash_set('Assigned players to fixture #' . $fixtureId . '.');
            $assignRedirectView = isset($_POST['view']) && in_array((string) $_POST['view'], AMIGA_FIXTURE_OPS_VIEWS, true)
                ? (string) $_POST['view']
                : 'advanced';
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, $assignRedirectView, $postStatus);
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
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
            amiga_fixture_ops_flash_set(
                'Recorded result on fixture #' . $gameId
                . '. Table updates live from running scores; use '
                . AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL
                . ' on the Table tab when finished.'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'results', $postStatus);
        } elseif ($action === 'reprocess_tournament_derived') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $summary = amiga_fixture_reprocess_tournament_derived($con, $tournamentId);
            if ($summary['skip_reason'] === 'lifecycle_repaired') {
                amiga_fixture_ops_flash_set(
                    'League was already official — lifecycle marked finished so it leaves Live and appears in the tournament catalog.'
                );
            } elseif ($summary['skip_reason'] === 'already_finalized') {
                amiga_fixture_ops_flash_set('This league is already finished and official.');
            } elseif ($summary['skip_reason'] === 'finalize_limbo') {
                amiga_fixture_ops_flash_set(
                    'Finish is stuck mid-way (ratings flagged, catalog incomplete). '
                    . 'Do not click Finish again — use Advanced → Reset incomplete finish, then Finish once.',
                    true
                );
            } elseif ($summary['skip_reason'] === 'lifecycle_not_running') {
                amiga_fixture_ops_flash_set(
                    AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL . ' is only available while the league is in progress.',
                    true
                );
            } elseif ($summary['skip_reason'] === 'no_played_fixtures' || $summary['skip_reason'] === 'no_games_to_finalize') {
                amiga_fixture_ops_flash_set(
                    AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL
                    . ' needs at least one played fixture with scores entered.',
                    true
                );
            } elseif ($summary['skip_reason'] !== null && str_starts_with((string) $summary['skip_reason'], 'lifecycle_complete_failed:')) {
                amiga_fixture_ops_flash_set(
                    'Ratings were committed but lifecycle could not be marked finished: '
                    . substr((string) $summary['skip_reason'], strlen('lifecycle_complete_failed: '))
                    . ' Use Advanced lifecycle or CLI repair.',
                    true
                );
            } elseif ($summary['failed_game_id'] !== null) {
                amiga_fixture_ops_flash_set(
                    AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL
                    . ' stopped after ' . $summary['processed'] . ' match(es) at game #'
                    . $summary['failed_game_id'] . ': ' . (string) $summary['skip_reason']
                    . '. Try `python -m scripts.amiga replay` if this persists.',
                    true
                );
            } elseif ($summary['skip_reason'] !== null) {
                amiga_fixture_ops_flash_set(
                    AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL . ' could not run: ' . (string) $summary['skip_reason'],
                    true
                );
            } else {
                $voided = (int) ($summary['voided_scheduled'] ?? 0);
                $msg = 'League finished and made official — '
                    . $summary['processed']
                    . ' match result(s) committed to ratings and tournament history.';
                if ($voided > 0) {
                    $msg .= ' ' . $voided . ' unplayed match'
                        . ($voided === 1 ? ' was' : 'es were')
                        . ' marked void.';
                }
                amiga_fixture_ops_flash_set($msg);
            }
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'table', $postStatus);
        } elseif ($action === 'undo_fixture_result') {
            $fixtureId = max(0, (int) ($_POST['fixture_id'] ?? 0));
            if ($fixtureId <= 0) {
                throw new RuntimeException('Missing fixture id.');
            }
            amiga_fixture_undo_unprocessed_result($con, $fixtureId);
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
            amiga_fixture_ops_flash_set('Removed unprocessed result for fixture #' . $fixtureId . '. Match is scheduled again.');
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'results', $postStatus);
        } elseif ($action === 'set_lifecycle_status') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $newStatus = trim((string) ($_POST['lifecycle_status'] ?? ''));
            $summary = amiga_fixture_set_lifecycle_status($con, $tournamentId, $newStatus);
            if (!$summary['changed']) {
                amiga_fixture_ops_flash_set("Tournament #{$tournamentId} is already {$newStatus}.");
            } else {
                amiga_fixture_ops_flash_set(
                    "Tournament #{$tournamentId} lifecycle: {$summary['previous_status']} → {$newStatus}."
                );
            }
            $lifecycleRedirectView = trim((string) ($_POST['view'] ?? 'setup'));
            if (!in_array($lifecycleRedirectView, AMIGA_FIXTURE_OPS_VIEWS, true)) {
                $lifecycleRedirectView = 'setup';
            }
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, $lifecycleRedirectView, $postStatus);
        } elseif ($action === 'organizer_lifecycle_action') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            $lifecycleAction = trim((string) ($_POST['lifecycle_action'] ?? ''));
            $summary = amiga_fixture_apply_organizer_lifecycle_action($con, $tournamentId, $lifecycleAction);
            if (!$summary['changed']) {
                if ($lifecycleAction === 'start_tournament') {
                    amiga_fixture_ops_flash_set('Tournament is already in progress.');
                } else {
                    amiga_fixture_ops_flash_set('Tournament is already void.');
                }
            } elseif ($lifecycleAction === 'start_tournament') {
                amiga_fixture_ops_flash_set('Tournament started — you can now enter results on the Results tab.');
            } else {
                amiga_fixture_ops_flash_set(
                    'League abandoned (void). It left Live and will not be made official.'
                );
            }
            $lifecycleActionView = trim((string) ($_POST['view'] ?? 'setup'));
            if (!in_array($lifecycleActionView, AMIGA_FIXTURE_OPS_VIEWS, true)) {
                $lifecycleActionView = 'setup';
            }
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, $lifecycleActionView, $postStatus);
        } elseif ($action === 'reset_incomplete_finalize') {
            $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
            if ($tournamentId <= 0) {
                throw new RuntimeException('Missing tournament id.');
            }
            amiga_fixture_require_generated_tournament($con, $tournamentId);
            amiga_fixture_reset_incomplete_finalize($con, $tournamentId);
            amiga_fixture_ops_flash_set(
                'Incomplete finish reset for #' . $tournamentId
                . ' (flag, ratings, standings, snapshots cleared; games kept). '
                . 'Use Finish and make official on the Table tab once.'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'table', $postStatus);
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
            amiga_fixture_ops_flash_set(
                'Registered player #' . $summary['player_id'] . ' as entrant #' . $summary['entrant_id'] . '.'
                . ' Use stage placement / fixture assignment separately when needed.'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'players', $postStatus);
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
            amiga_fixture_ops_flash_set(
                'Withdrew player #' . $summary['player_id'] . ' ('
                . $summary['fixture_slots_cleared'] . ' fixture slot(s) cleared, '
                . $summary['stage_player_rows_removed'] . ' stage-player row(s) removed).'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'players', $postStatus);
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
            amiga_fixture_ops_flash_set(
                'Replaced player #' . $summary['old_player_id'] . ' with #' . $summary['new_player_id']
                . ' (entrant #' . $summary['new_entrant_id'] . ', seed '
                . ($summary['seed_no'] !== null ? (string) $summary['seed_no'] : 'none') . ').'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'players', $postStatus);
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
            amiga_fixture_ops_flash_set(
                $verb . ' player #' . $summary['player_id'] . ' in stage '
                . $summary['stage_key'] . ' (stage id ' . $summary['stage_id'] . ').'
                . ' Use fixture assignment separately when needed.'
            );
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, 'advanced', $postStatus);
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        if (in_array($action, ['create_kitchen', 'suggest_player', 'create_player'], true)) {
            $createFormError = true;
            $flashIsError = true;
            $flash = $e->getMessage();
            $view = 'setup';
            $tournamentId = 0;
            $createDraft = amiga_fixture_create_draft_from_request();
        } else {
            $flashIsError = true;
            if (isset($_POST['tournament_id'])) {
                $tournamentId = max(0, (int) $_POST['tournament_id']);
            }
            $errorView = 'fixtures';
            if (in_array($action, ['add_entrant', 'withdraw_entrant', 'replace_entrant'], true)) {
                $errorView = 'players';
            } elseif (in_array($action, ['record_result', 'undo_fixture_result'], true)) {
                $errorView = 'results';
            } elseif ($action === 'reprocess_tournament_derived') {
                $errorView = 'table';
            } elseif ($action === 'reset_incomplete_finalize' || $action === 'place_stage_entrant') {
                $errorView = 'advanced';
            } elseif ($action === 'set_lifecycle_status' || $action === 'organizer_lifecycle_action') {
                $errorView = 'setup';
            }
            amiga_fixture_ops_flash_set($e->getMessage(), true);
            amiga_fixture_ops_redirect($self, $key, $pwdValue, $tournamentId, $errorView, $postStatus);
        }
    }
}

if ($tournamentId <= 0) {
    $createSelectedPlayers = amiga_fixture_load_player_summaries($con, $createDraft['player_ids']);
}

$sql = "
    SELECT t.id, t.name, t.event_date,
           COUNT(DISTINCT s.id) AS stage_count,
           COUNT(DISTINCT f.id) AS fixture_count,
           SUM(CASE WHEN f.status = 'played' THEN 1 ELSE 0 END) AS game_count
    FROM tournaments t
    INNER JOIN tournament_stages s ON s.tournament_id = t.id
    LEFT JOIN tournament_fixtures f ON f.stage_id = s.id
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
            $organizerLifecycleUi = amiga_fixture_organizer_lifecycle_ui($con, $lifecycle);
        }
    }

    $sql = "
        SELECT f.id, f.fixture_key, f.leg_no, f.status, f.phase_label,
               s.id AS stage_id, s.stage_key, s.name AS stage_name, s.stage_type, s.sequence_no,
               f.player_a_id, f.player_b_id,
               pa.name AS player_a_name, pb.name AS player_b_name,
               f.goals_a, f.goals_b, f.extra,
               g.id AS game_id
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

    $entrants = amiga_fixture_list_entrants($con, $tournamentId);
    $entrantOpsEligible = amiga_fixture_is_eligible_generated_tournament([
        'source_id' => $tournament['source_id'] !== null ? (int) $tournament['source_id'] : null,
        'format_overrides' => $tournament['format_overrides'] ?? null,
    ]);

    $tournamentRatingFinalized = amiga_ops_tournament_rating_finalized($con, $tournamentId);
    $playedFixtureCount = amiga_fixture_count_played_fixtures($con, $tournamentId);
    $scheduledFixtureRemaining = amiga_fixture_count_scheduled_fixtures($con, $tournamentId);
    if ($tournamentRatingFinalized) {
        $stmt = $con->prepare(
            'SELECT s.position, s.games, s.wins, s.draws, s.losses, '
            . 's.goals_for, s.goals_against, s.points, '
            . 'p.id AS player_id, p.name AS player_name '
            . 'FROM amiga_tournament_standings s '
            . 'INNER JOIN amiga_players p ON p.id = s.player_id '
            . 'WHERE s.tournament_id = ? AND s.scope_type = \'league\' AND s.scope_key = \'\' '
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
    } elseif ($entrantOpsEligible && amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        $standingsRows = amiga_running_tournament_standings_rows($con, $tournamentId);
    }

    if ($entrantOpsEligible) {
        $stageOpsEligible = true;
        $stages = amiga_fixture_list_stages($con, $tournamentId);
        $stagePlayers = amiga_fixture_list_stage_players($con, $tournamentId);
        $stagePlayersByStage = amiga_fixture_stage_players_by_stage($stagePlayers);
    }
    if ($playerSearchQuery !== '') {
        $playerSearchResults = amiga_fixture_search_players($con, $playerSearchQuery);
    }

    $tournamentUnratedGameCount = $tournamentRatingFinalized
        ? count(amiga_fixture_list_tournament_unrated_game_ids($con, $tournamentId))
        : $playedFixtureCount;
    $finishLimbo = $tournamentRatingFinalized
        && $lifecycle !== null
        && $lifecycle['lifecycle_status'] === 'running';
    $tournamentCanMakeOfficial = $entrantOpsEligible
        && $lifecycle !== null
        && $lifecycle['lifecycle_status'] === 'running'
        && $playedFixtureCount > 0
        && !$tournamentRatingFinalized
        && !$finishLimbo;
    $fixtureResultRated = [];
}

$createCountryUsedOfficial = [];
foreach (k2_amiga_country_used_tokens($con) as $token) {
    $row = k2_amiga_country_resolve($token);
    if ($row !== null && !empty($row['choosable'])) {
        $createCountryUsedOfficial[] = (string) $row['official_name'];
    } elseif (k2_amiga_country_validate_official($token)) {
        $createCountryUsedOfficial[] = $token;
    }
}
$createCountryUsedOfficial = array_values(array_unique($createCountryUsedOfficial));
sort($createCountryUsedOfficial, SORT_NATURAL | SORT_FLAG_CASE);
$createCountryUsedSet = array_flip($createCountryUsedOfficial);
$createCountryMoreRows = [];
foreach (k2_amiga_country_choosable_rows() as $row) {
    $official = trim((string) ($row['official_name'] ?? ''));
    if ($official !== '' && !isset($createCountryUsedSet[$official])) {
        $createCountryMoreRows[] = $row;
    }
}

mysqli_close($con);
$fixtureScheduleGroups = amiga_fixture_group_fixtures_for_schedule($fixtures);
$fixtureResultsPartition = amiga_fixture_partition_for_results($fixtures);
$fixtureResultsEntryGroups = amiga_fixture_group_fixtures_for_schedule($fixtureResultsPartition['playable']);
$fixtureResultsPlayedGroups = amiga_fixture_group_fixtures_for_schedule($fixtureResultsPartition['played']);
$resultsTabUrl = amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, 'results', $status);
$organizerTableDisplay = amiga_fixture_organizer_table_rows($standingsRows, $entrants);
$createMatchHint = amiga_fixture_expected_round_robin_fixtures(
    count($createDraft['player_ids']),
    $createDraft['legs']
);
amiga_fixture_render_chrome_start('Amiga — Tournament organizer', true, $view === 'table');
?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Tournament organizer</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Internal league workflow: create a league, choose players, preview fixtures and table, start the tournament, then enter results.
  </p>
  <nav class="k2-player-nav k2-nav-pills k2-amiga-tournament-nav" aria-label="Live tournament tools" style="margin-bottom:1rem">
    <div class="k2-player-nav__links">
      <a href="/amiga/live-tournaments.php" class="k2-player-nav__btn">Live tournaments</a>
      <span class="k2-player-nav__btn is-active" aria-current="page">Tournament organizer</span>
    </div>
  </nav>
</header>

<div class="k2-amiga-live-ops k2-amiga-organizer">
<?php if ($flash !== null) { ?>
  <div class="k2-amiga-live-ops__flash<?php echo $flashIsError ? ' k2-amiga-live-ops__flash--error' : ''; ?>"><?php echo k2_h($flash); ?></div>
<?php } ?>
<?php if ($tournamentId > 0 && $tournament !== null) { ?>
  <div class="k2-amiga-organizer__header">
    <p class="k2-amiga-organizer__back"><a href="<?php echo htmlspecialchars(amiga_fixture_ops_url($self, $key, $pwdValue, 0, 'setup'), ENT_QUOTES, 'UTF-8'); ?>">Create new league</a></p>
    <h2 class="k2-amiga-organizer__title"><?php echo k2_h((string) $tournament['name']); ?></h2>
    <?php if ($organizerLifecycleUi !== null) { ?>
      <p class="k2-amiga-organizer-lifecycle__header-status">
        <span class="k2-amiga-tournament-badge k2-amiga-tournament-badge--lifecycle k2-amiga-tournament-badge--<?php echo k2_h($organizerLifecycleUi['badge_modifier']); ?>"><?php echo k2_h($organizerLifecycleUi['label']); ?></span>
      </p>
    <?php } ?>
  </div>
  <nav class="k2-amiga-organizer-tabs" aria-label="Tournament views">
    <?php
    $tabLabels = [
        'setup' => 'Setup',
        'players' => 'Players',
        'fixtures' => 'Fixtures',
        'table' => 'Table',
        'results' => 'Results',
        'advanced' => 'Advanced',
    ];
    foreach ($tabLabels as $tabView => $tabLabel) {
        $tabUrl = amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, $tabView, $status);
        $isActive = $view === $tabView;
        ?>
    <a href="<?php echo htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8'); ?>" class="k2-amiga-organizer-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8'); ?></a>
    <?php } ?>
  </nav>
<?php } ?>

<div class="k2-amiga-organizer-panel">
<?php if ($view === 'setup') { ?>
  <?php if ($tournamentId <= 0) {
      $organizerPickerBase = $self . '?' . http_build_query(array_merge(
          ['once' => $key, 'view' => 'setup'],
          amiga_fixture_create_draft_query($createDraft)
      ));
      $organizerSelectedIds = implode(',', array_map('intval', $createDraft['player_ids']));
      ?>
  <div class="k2-amiga-live-ops__section k2-amiga-organizer-create">
    <h2>Create league</h2>

    <h3 class="k2-amiga-organizer-create__step">Players</h3>
    <div class="k2-amiga-organizer-player-search"
         data-organizer-search-realm="amiga"
         data-organizer-add-base="<?php echo htmlspecialchars($organizerPickerBase, ENT_QUOTES, 'UTF-8'); ?>"
         data-organizer-selected-ids="<?php echo htmlspecialchars($organizerSelectedIds, ENT_QUOTES, 'UTF-8'); ?>"
         role="search">
      <label class="k2-amiga-organizer-player-search__label" for="amiga-organizer-create-player-search">Find player</label>
      <input id="amiga-organizer-create-player-search" class="k2-amiga-organizer-player-search__input" type="search" maxlength="32" autocomplete="off" spellcheck="false"
        aria-autocomplete="list" aria-expanded="false" aria-controls="amiga-organizer-create-player-search-list" placeholder="Type at least 2 characters…" />
      <ul id="amiga-organizer-create-player-search-list" class="k2-amiga-organizer-player-search__results" role="listbox" hidden="hidden"></ul>
      <span class="k2-amiga-organizer-player-search__live visually-hidden" aria-live="polite"></span>
    </div>

    <div class="k2-amiga-organizer-create-player">
      <h3 class="k2-amiga-organizer-create__step">Create player</h3>
      <p class="k2-amiga-live-ops__muted">Enter full name + nationality; the system assigns the KOA ladder name (no manual abbreviation).</p>
      <?php if ($createDraft['new_player_preview'] !== '') { ?>
        <div class="k2-amiga-organizer-create-player__preview">
          <p><strong>Suggested ladder name:</strong> <?php echo k2_h($createDraft['new_player_preview']); ?></p>
          <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
            <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="create_player">
            <?php foreach ($createDraft['player_ids'] as $pid) { ?>
              <input type="hidden" name="player_ids[]" value="<?php echo (int) $pid; ?>">
            <?php } ?>
            <input type="hidden" name="name" value="<?php echo k2_h($createDraft['name']); ?>">
            <input type="hidden" name="event_date" value="<?php echo k2_h($createDraft['event_date']); ?>">
            <input type="hidden" name="country" value="<?php echo k2_h($createDraft['country']); ?>">
            <input type="hidden" name="legs" value="<?php echo (int) $createDraft['legs']; ?>">
            <input type="hidden" name="new_player_full_name" value="<?php echo k2_h($createDraft['new_player_full']); ?>">
            <input type="hidden" name="new_player_country" value="<?php echo k2_h($createDraft['new_player_country']); ?>">
            <input type="hidden" name="new_player_preview" value="<?php echo k2_h($createDraft['new_player_preview']); ?>">
            <button type="submit">Create player and add to list</button>
          </form>
          <p class="k2-amiga-live-ops__muted"><a href="<?php echo htmlspecialchars($self . '?' . http_build_query(array_merge(['once' => $key, 'view' => 'setup', 'create_clear_new_player' => '1'], amiga_fixture_create_draft_query($createDraft))), ENT_QUOTES, 'UTF-8'); ?>">Clear preview</a></p>
        </div>
      <?php } ?>
      <form class="k2-amiga-live-ops__grid-form k2-amiga-organizer-create-player__form" method="post" action="<?php echo $self; ?>">
        <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="suggest_player">
        <?php foreach ($createDraft['player_ids'] as $pid) { ?>
          <input type="hidden" name="player_ids[]" value="<?php echo (int) $pid; ?>">
        <?php } ?>
        <input type="hidden" name="name" value="<?php echo k2_h($createDraft['name']); ?>">
        <input type="hidden" name="event_date" value="<?php echo k2_h($createDraft['event_date']); ?>">
        <input type="hidden" name="country" value="<?php echo k2_h($createDraft['country']); ?>">
        <input type="hidden" name="legs" value="<?php echo (int) $createDraft['legs']; ?>">
        <label>Full name
          <input type="text" name="new_player_full_name" required maxlength="80" placeholder="Mark Bentley" value="<?php echo k2_h($createDraft['new_player_full']); ?>">
        </label>
        <?php amiga_fixture_render_new_player_country_field($createDraft['new_player_country'], $createCountryUsedOfficial, $createCountryMoreRows); ?>
        <div class="wide">
          <button type="submit">Preview KOA name</button>
        </div>
      </form>
    </div>

    <form class="k2-amiga-live-ops__grid-form k2-amiga-organizer-create__form" method="post" action="<?php echo $self; ?>">
      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="create_kitchen">
      <div class="wide k2-amiga-organizer__selected-players">
        <?php if ($createSelectedPlayers === []) { ?>
          <p class="k2-amiga-live-ops__muted">No players selected yet.</p>
        <?php } else { ?>
          <ul class="k2-amiga-organizer__player-chips">
          <?php foreach ($createSelectedPlayers as $selectedPlayer) {
              $removeUrl = $self . '?' . http_build_query(array_merge(
                  ['once' => $key, 'view' => 'setup', 'create_remove_player_id' => $selectedPlayer['id']],
                  amiga_fixture_create_draft_query($createDraft)
              ));
              ?>
            <li class="k2-amiga-organizer__player-chip">
              <input type="hidden" name="player_ids[]" value="<?php echo (int) $selectedPlayer['id']; ?>">
              <span><?php echo k2_h($selectedPlayer['name']); ?></span>
              <a href="<?php echo htmlspecialchars($removeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="k2-amiga-organizer__chip-remove" aria-label="Remove <?php echo k2_h($selectedPlayer['name']); ?>">×</a>
            </li>
          <?php } ?>
          </ul>
          <?php if ($createMatchHint > 0) { ?>
            <p class="k2-amiga-live-ops__muted"><?php echo count($createSelectedPlayers); ?> player<?php echo count($createSelectedPlayers) === 1 ? '' : 's'; ?> → <?php echo $createMatchHint; ?> fixture<?php echo $createMatchHint === 1 ? '' : 's'; ?>.</p>
          <?php } ?>
        <?php } ?>
      </div>

      <h3 class="wide k2-amiga-organizer-create__step">League details</h3>
      <label>Name
        <input type="text" name="name" required maxlength="120" placeholder="Thursday Kitchen I" value="<?php echo k2_h($createDraft['name']); ?>">
      </label>
      <label>Date
        <?php k2_render_day_picker('amiga-fixture-event-date', 'event_date', $createDraft['event_date'] !== '' ? $createDraft['event_date'] : gmdate('Y-m-d'), 'Tournament date'); ?>
      </label>
      <?php amiga_fixture_render_create_country_field($createDraft['country'], $createCountryUsedOfficial, $createCountryMoreRows); ?>
      <label>Round-robin format
        <select name="legs">
          <option value="1"<?php echo $createDraft['legs'] === 1 ? ' selected' : ''; ?>>Single round-robin</option>
          <option value="2"<?php echo $createDraft['legs'] === 2 ? ' selected' : ''; ?>>Home and away</option>
        </select>
      </label>
      <label class="wide k2-amiga-organizer-create__wc">
        <input type="checkbox" name="is_world_cup" value="1"<?php echo !empty($createDraft['is_world_cup']) ? ' checked' : ''; ?>>
        World Cup event (name must match <code>World Cup …</code>)
      </label>
      <div class="wide">
        <button type="submit">Create league</button>
      </div>
    </form>
  </div>
  <?php } elseif ($tournament === null) { ?>
    <p class="k2-amiga-live-ops__muted">That tournament could not be found. <a href="<?php echo htmlspecialchars(amiga_fixture_ops_url($self, $key, $pwdValue, 0, 'setup'), ENT_QUOTES, 'UTF-8'); ?>">Create or open a league</a> from the list below.</p>
  <?php } elseif ($lifecycle !== null && $organizerLifecycleUi !== null) { ?>
    <div class="k2-amiga-live-ops__section k2-amiga-organizer-lifecycle">
      <h3>Tournament status</h3>
      <p class="k2-amiga-organizer-lifecycle__summary">
        <span class="k2-amiga-tournament-badge k2-amiga-tournament-badge--lifecycle k2-amiga-tournament-badge--<?php echo k2_h($organizerLifecycleUi['badge_modifier']); ?>"><?php echo k2_h($organizerLifecycleUi['label']); ?></span>
      </p>
      <?php if ($organizerLifecycleUi['raw_status'] === 'running') { ?>
        <p class="k2-amiga-live-ops__muted">This league is <strong>public on the Live hub</strong> while in progress —
          <a href="<?php echo k2_h(amiga_live_tournament_url($tournamentId)); ?>">view public page</a>.</p>
      <?php } ?>
      <dl class="k2-amiga-organizer-lifecycle__meta">
        <dt>Started</dt>
        <dd><?php echo $lifecycle['started_at'] !== null ? k2_h($lifecycle['started_at']) : '<span class="k2-amiga-live-ops__muted">not set</span>'; ?></dd>
        <dt>Completed</dt>
        <dd><?php echo $lifecycle['completed_at'] !== null ? k2_h($lifecycle['completed_at']) : '<span class="k2-amiga-live-ops__muted">not set</span>'; ?></dd>
        <dt>Internal status</dt>
        <dd><span class="k2-amiga-live-ops__muted"><?php echo k2_h($organizerLifecycleUi['raw_status']); ?></span></dd>
      </dl>
      <?php if ($organizerLifecycleUi['is_imported']) { ?>
        <p class="k2-amiga-live-ops__muted">Historical import — lifecycle changes are CLI-only.</p>
      <?php } elseif ($organizerLifecycleUi['raw_status'] === 'void') { ?>
        <p class="k2-amiga-live-ops__muted">This tournament was voided. No further lifecycle actions are available in the browser.</p>
      <?php } elseif ($organizerLifecycleUi['is_read_only']) { ?>
        <p class="k2-amiga-live-ops__muted">This tournament is finished. No further lifecycle actions are available in the browser.</p>
      <?php } else { ?>
        <div class="k2-amiga-organizer-lifecycle__actions">
          <?php if ($organizerLifecycleUi['can_start']) { ?>
            <form class="k2-amiga-organizer-lifecycle__action-form" method="post" action="<?php echo $self; ?>">
              <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="action" value="organizer_lifecycle_action">
              <input type="hidden" name="lifecycle_action" value="start_tournament">
              <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
              <input type="hidden" name="view" value="setup">
              <?php if ($status !== '') { ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
              <?php } ?>
              <button type="submit" class="k2-amiga-organizer-lifecycle__action k2-amiga-organizer-lifecycle__action--primary">Start tournament</button>
            </form>
          <?php } ?>
        </div>
        <?php if ($organizerLifecycleUi['raw_status'] === 'running') { ?>
          <p class="k2-amiga-organizer-lifecycle__hint">Next: enter scores on the <strong>Results</strong> tab. When the table looks right, use <strong><?php echo k2_h(AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL); ?></strong> on the Table tab.</p>
        <?php } elseif ($organizerLifecycleUi['finish_hint'] !== null) { ?>
          <p class="k2-amiga-organizer-lifecycle__hint"><?php echo k2_h($organizerLifecycleUi['finish_hint']); ?></p>
        <?php } ?>
      <?php } ?>
      <?php if ($organizerLifecycleUi['raw_status'] !== 'running') { ?>
        <p class="k2-amiga-live-ops__muted">Result entry unlocks after you start the tournament.</p>
      <?php } ?>
    </div>
  <?php } ?>
<?php } ?>

<?php if ($view === 'players' && $tournamentId > 0 && $tournament !== null) { ?>
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
                  <input type="hidden" name="view" value="players">
                  <?php if ($status !== '') { ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php } ?>
                  <input type="hidden" name="player_id" value="<?php echo (int) $entrantRow['player_id']; ?>">
                  <input type="text" name="note" maxlength="120" placeholder="note (optional)" aria-label="Withdrawal note for player <?php echo (int) $entrantRow['player_id']; ?>">
                  <button type="submit">Withdraw</button>
                </form>
                <?php
                $replaceUrl = amiga_fixture_ops_url(
                    $self,
                    $key,
                    $pwdValue,
                    $tournamentId,
                    'players',
                    $status,
                    ['replace_player_id' => (int) $entrantRow['player_id']]
                );
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
      <input type="hidden" name="view" value="players">
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
          $cancelReplaceUrl = amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, 'players', $status);
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
                      <input type="hidden" name="view" value="players">
                      <?php if ($status !== '') { ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php } ?>
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
                      <input type="hidden" name="view" value="players">
                      <?php if ($status !== '') { ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php } ?>
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
  <?php } elseif ($tournament !== null && $tournament['source_id'] !== null) { ?>
    <p class="k2-amiga-live-ops__muted">Imported historical tournament — entrant management is CLI-only.</p>
  <?php } ?>
<?php } ?>

<?php if ($view === 'advanced' && $tournamentId > 0 && $tournament !== null) { ?>
  <?php if ($lifecycle !== null) { ?>
  <div class="k2-amiga-live-ops__section k2-amiga-organizer-lifecycle-advanced">
    <h3>Lifecycle (advanced)</h3>
    <p class="k2-amiga-live-ops__muted">Internal status transitions for operators. Prefer <strong>Start tournament</strong> on Setup and <strong><?php echo k2_h(AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL); ?></strong> on the Table tab for normal league nights.</p>
    <dl class="k2-amiga-organizer-lifecycle__meta">
      <dt>Internal status</dt>
      <dd><span class="k2-amiga-live-ops__muted"><?php echo k2_h($lifecycle['lifecycle_status']); ?></span></dd>
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
        <input type="hidden" name="view" value="advanced">
        <?php if ($status !== '') { ?>
          <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
        <?php } ?>
        <label>Transition to (internal)
          <select name="lifecycle_status" required>
            <?php foreach ($lifecycleTargets as $target) { ?>
              <option value="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php } ?>
          </select>
        </label>
        <button type="submit">Apply lifecycle transition</button>
      </form>
    <?php } else { ?>
      <p class="k2-amiga-live-ops__muted">No single-step browser transitions available from this status. Use CLI <code>fixtures set-tournament-status --force</code> for unusual cases.</p>
    <?php } ?>
    <?php
    $showResetIncomplete = $lifecycle !== null
        && $lifecycle['lifecycle_status'] === 'running'
        && (
            amiga_ops_tournament_rating_finalized($con, $tournamentId)
            || amiga_fixture_count_official_tournament_games($con, $tournamentId) > 0
        );
    if ($showResetIncomplete) { ?>
      <div class="k2-amiga-organizer-lifecycle-advanced__reset" style="margin-top:1.25rem">
        <h4>Reset incomplete finish</h4>
        <p class="k2-amiga-live-ops__muted">
          Use only when Finish failed mid-way (limbo: still In progress, but ratings/flag look half-official).
          Clears <code>rating_finalized</code>, game ratings, standings, and event snapshots for this league.
          Keeps promoted games. Does <strong>not</strong> fully inventory every derived table — kitchen drills only.
        </p>
        <form class="k2-amiga-organizer-lifecycle__action-form" method="post" action="<?php echo $self; ?>"
              onsubmit="return confirm('Reset incomplete finish for this league? Clears ratings/standings/snapshots; keeps games. Then Finish once from Table.');">
          <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="reset_incomplete_finalize">
          <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
          <input type="hidden" name="view" value="advanced">
          <?php if ($status !== '') { ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
          <?php } ?>
          <button type="submit" class="k2-amiga-organizer-lifecycle__action k2-amiga-organizer-lifecycle__action--secondary">Reset incomplete finish</button>
        </form>
      </div>
    <?php } ?>
    <?php if ($organizerLifecycleUi !== null && !empty($organizerLifecycleUi['can_void'])) { ?>
      <div class="k2-amiga-organizer-lifecycle-advanced__void" style="margin-top:1.25rem">
        <h4>Abandon league (void)</h4>
        <p class="k2-amiga-live-ops__muted">
          Marks this league as abandoned: it leaves the Live hub and will <strong>not</strong> be made official
          (no ratings, no historical catalog). Use this for a practice night you are throwing away.
          It does not delete the row from the database — full cleanup comes later.
        </p>
        <form class="k2-amiga-organizer-lifecycle__action-form" method="post" action="<?php echo $self; ?>"
              onsubmit="return confirm('Abandon this league? It will leave Live and cannot be made official.');">
          <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="organizer_lifecycle_action">
          <input type="hidden" name="lifecycle_action" value="void_tournament">
          <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
          <input type="hidden" name="view" value="advanced">
          <?php if ($status !== '') { ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
          <?php } ?>
          <button type="submit" class="k2-amiga-organizer-lifecycle__action k2-amiga-organizer-lifecycle__action--secondary">Abandon league (void)</button>
        </form>
      </div>
    <?php } ?>
  </div>
  <?php } ?>
  <div class="k2-amiga-live-ops__section">
    <h3>Fixture status filter</h3>
    <form class="k2-amiga-live-ops__inline-form" method="get" action="<?php echo $self; ?>">
      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
      <input type="hidden" name="view" value="advanced">
      <label>Status
        <select name="status">
          <?php foreach (['' => 'All', 'scheduled' => 'Scheduled', 'played' => 'Played', 'void' => 'Void'] as $value => $label) { ?>
            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $status === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php } ?>
        </select>
      </label>
      <button type="submit">Apply filter</button>
    </form>
    <p class="k2-amiga-live-ops__muted">Fixture assignment and technical ids are listed below.</p>
  </div>
  <?php if ($fixtures !== []) { ?>
  <div class="k2-amiga-live-ops__section">
    <h3>Fixture details (technical)</h3>
    <p class="k2-amiga-live-ops__muted">Internal ids, keys, and stage metadata. Use this view to assign empty fixture slots.</p>
    <div class="k2-table-wrap">
    <table class="k2-table k2-table--calm-stats">
      <thead>
        <tr><th class="k2-table-cell--left">ID</th><th class="k2-table-cell--left">Stage</th><th class="k2-table-cell--left">Key</th><th>Leg</th><th class="k2-table-cell--left">Players</th><th>Status</th><th class="k2-table-cell--left">Result</th></tr>
      </thead>
      <tbody>
      <?php foreach ($fixtures as $row) { ?>
        <tr>
          <td class="k2-table-cell--left"><?php echo (int) $row['id']; ?></td>
          <td class="k2-table-cell--left"><?php echo k2_h((string) $row['stage_name']); ?><br><span class="k2-amiga-live-ops__muted"><?php echo k2_h((string) $row['stage_key']); ?> · <?php echo k2_h((string) $row['stage_type']); ?></span></td>
          <td class="k2-table-cell--left"><code><?php echo k2_h((string) $row['fixture_key']); ?></code></td>
          <td><?php echo (int) $row['leg_no']; ?></td>
          <td class="k2-table-cell--left">
            <?php echo k2_h((string) ($row['player_a_name'] ?? 'TBD')); ?> vs <?php echo k2_h((string) ($row['player_b_name'] ?? 'TBD')); ?>
            <?php
              $canAssignFixturePlayers = $row['status'] === 'scheduled'
                  && !amiga_running_tournament_fixture_has_result($row)
                  && ($row['player_a_id'] === null || $row['player_b_id'] === null);
              if ($canAssignFixturePlayers && $stageOpsEligible) {
                  $fixtureStageId = (int) $row['stage_id'];
                  $fixtureStagePlayers = $stagePlayersByStage[$fixtureStageId] ?? [];
                  $useStagePlayerSelects = count($fixtureStagePlayers) >= 2;
                  $selectedPlayerA = $row['player_a_id'] !== null ? (int) $row['player_a_id'] : 0;
                  $selectedPlayerB = $row['player_b_id'] !== null ? (int) $row['player_b_id'] : 0;
                  ?>
              <form class="k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
                <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="assign_players">
                <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                <input type="hidden" name="view" value="advanced">
                <?php if ($status !== '') { ?>
                  <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                <?php } ?>
                <input type="hidden" name="fixture_id" value="<?php echo (int) $row['id']; ?>">
                <?php if ($useStagePlayerSelects) { ?>
                  <label class="k2-amiga-live-ops__muted">Player A
                    <select name="player_a_id" required aria-label="Player A for fixture <?php echo (int) $row['id']; ?>">
                      <option value="">— select —</option>
                      <?php foreach ($fixtureStagePlayers as $stagePlayerOption) { ?>
                        <option value="<?php echo (int) $stagePlayerOption['player_id']; ?>"<?php echo $selectedPlayerA === (int) $stagePlayerOption['player_id'] ? ' selected' : ''; ?>><?php echo k2_h($stagePlayerOption['player_name']); ?> (#<?php echo (int) $stagePlayerOption['player_id']; ?>)</option>
                      <?php } ?>
                    </select>
                  </label>
                  <label class="k2-amiga-live-ops__muted">Player B
                    <select name="player_b_id" required aria-label="Player B for fixture <?php echo (int) $row['id']; ?>">
                      <option value="">— select —</option>
                      <?php foreach ($fixtureStagePlayers as $stagePlayerOption) { ?>
                        <option value="<?php echo (int) $stagePlayerOption['player_id']; ?>"<?php echo $selectedPlayerB === (int) $stagePlayerOption['player_id'] ? ' selected' : ''; ?>><?php echo k2_h($stagePlayerOption['player_name']); ?> (#<?php echo (int) $stagePlayerOption['player_id']; ?>)</option>
                      <?php } ?>
                    </select>
                  </label>
                <?php } else { ?>
                  <?php if (count($fixtureStagePlayers) < 2) { ?>
                    <span class="k2-amiga-live-ops__muted">Place at least two entrants in this stage before assigning fixture slots.</span>
                  <?php } ?>
                  <input type="number" name="player_a_id" min="1"<?php echo $selectedPlayerA > 0 ? ' value="' . $selectedPlayerA . '"' : ''; ?> required placeholder="Player A" aria-label="Player A id for fixture <?php echo (int) $row['id']; ?>">
                  <input type="number" name="player_b_id" min="1"<?php echo $selectedPlayerB > 0 ? ' value="' . $selectedPlayerB . '"' : ''; ?> required placeholder="Player B" aria-label="Player B id for fixture <?php echo (int) $row['id']; ?>">
                <?php } ?>
                <button type="submit">Assign</button>
              </form>
            <?php } ?>
          </td>
          <td><span class="k2-amiga-tournament-badge"><?php echo k2_h((string) $row['status']); ?></span></td>
          <td class="k2-table-cell--left"><?php
              if (amiga_running_tournament_fixture_has_result($row)) {
                  echo (int) $row['goals_a'] . '-' . (int) $row['goals_b'];
                  if (!$tournamentRatingFinalized) {
                      echo ' <span class="k2-amiga-live-ops__muted">(running)</span>';
                  } elseif ($row['game_id'] !== null) {
                      echo ' <span class="k2-amiga-live-ops__muted">game #' . (int) $row['game_id'] . '</span>';
                  }
              } else {
                  echo '<span class="k2-amiga-live-ops__muted">—</span>';
              }
          ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php } ?>
  <?php if ($stageOpsEligible) {
      $canPlaceStageEntrants = $lifecycle !== null
          && in_array($lifecycle['lifecycle_status'], AMIGA_FIXTURE_ENTRANT_REGISTRATION_LIFECYCLES, true);
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
          <input type="hidden" name="view" value="advanced">
          <?php if ($status !== '') { ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
          <?php } ?>
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
<?php } ?>

<?php if ($view === 'fixtures' && $tournamentId > 0 && $tournament !== null) { ?>
  <div class="k2-amiga-live-ops__section k2-amiga-organizer-schedule">
    <h2>Match schedule</h2>
    <p class="k2-amiga-live-ops__muted"><?php echo count($fixtures); ?> match<?php echo count($fixtures) === 1 ? '' : 'es'; ?><?php echo $status !== '' ? ' (' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . ' filter active — change on Advanced)' : ''; ?>. Technical ids and assignment controls are on the Advanced tab.<?php
      if (
          $lifecycle !== null
          && $lifecycle['lifecycle_status'] === 'running'
          && $fixtureResultsPartition['playable'] !== []
      ) {
          echo ' <a href="' . htmlspecialchars($resultsTabUrl, ENT_QUOTES, 'UTF-8') . '">Enter scores on the Results tab</a>.';
      }
    ?></p>
    <?php if ($fixtures === []) { ?>
      <p class="k2-amiga-live-ops__muted">No fixtures for this tournament yet.</p>
    <?php } else { ?>
      <?php foreach ($fixtureScheduleGroups as $scheduleGroup) { ?>
        <section class="k2-amiga-organizer-schedule__group">
          <h3 class="k2-amiga-organizer-schedule__heading"><?php echo k2_h($scheduleGroup['label']); ?></h3>
          <ul class="k2-amiga-organizer-schedule__matches">
          <?php foreach ($scheduleGroup['fixtures'] as $row) {
              $statusModifier = amiga_fixture_organizer_fixture_status_modifier((string) $row['status']);
              $playerA = (string) ($row['player_a_name'] ?? 'TBD');
              $playerB = (string) ($row['player_b_name'] ?? 'TBD');
              ?>
            <li class="k2-amiga-organizer-schedule__match">
              <div class="k2-amiga-organizer-schedule__match-main">
                <span class="k2-amiga-organizer-schedule__matchup"><?php echo k2_h($playerA); ?> <span class="k2-amiga-organizer-schedule__vs">vs</span> <?php echo k2_h($playerB); ?></span>
                <?php if (amiga_running_tournament_fixture_has_result($row)) { ?>
                  <span class="k2-amiga-organizer-schedule__score"><?php echo (int) $row['goals_a']; ?>–<?php echo (int) $row['goals_b']; ?></span>
                <?php } ?>
                <span class="k2-amiga-organizer-schedule__status k2-amiga-organizer-schedule__status--<?php echo k2_h($statusModifier); ?>"><?php echo k2_h(amiga_fixture_organizer_fixture_status_label((string) $row['status'])); ?></span>
              </div>
              <?php if (
                  $row['status'] === 'scheduled'
                  && $row['player_a_id'] !== null
                  && $row['player_b_id'] !== null
                  && $lifecycle !== null
                  && $lifecycle['lifecycle_status'] !== 'running'
              ) { ?>
                <p class="k2-amiga-organizer-schedule__hint k2-amiga-live-ops__muted">Start the tournament on Setup to enter results.</p>
              <?php } elseif (
                  $row['status'] === 'scheduled'
                  && ($row['player_a_id'] === null || $row['player_b_id'] === null)
              ) { ?>
                <p class="k2-amiga-organizer-schedule__hint k2-amiga-live-ops__muted">Players not assigned — use Advanced to assign slots.</p>
              <?php } ?>
            </li>
          <?php } ?>
          </ul>
        </section>
      <?php } ?>
    <?php } ?>
  </div>
<?php } ?>

<?php if ($view === 'table' && $tournamentId > 0 && $tournament !== null) { ?>
  <div class="k2-amiga-live-ops__section k2-amiga-organizer-table">
    <h2>League table</h2>
    <?php
    $finishLimboUi = $tournamentRatingFinalized
        && $lifecycle !== null
        && $lifecycle['lifecycle_status'] === 'running';
    ?>
    <?php if ($tournamentRatingFinalized && $lifecycle !== null && $lifecycle['lifecycle_status'] === 'completed') { ?>
      <p class="k2-amiga-organizer-table__preview-note k2-amiga-organizer-table__preview-note--warn">
        This league is <strong>official</strong> — global ratings and site chronology include this event (N&rarr;N+1).
        Ground-truth score edits require a full derived rebuild:
        <code>python -m scripts.amiga prove</code>
      </p>
    <?php } elseif ($finishLimboUi) { ?>
      <p class="k2-amiga-organizer-table__preview-note k2-amiga-organizer-table__preview-note--warn">
        Finish stopped mid-way: ratings were partially written, but this league is <strong>not</strong> in the catalog.
        Do not click Finish again. Go to
        <a href="<?php echo htmlspecialchars(amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, 'advanced', $status), ENT_QUOTES, 'UTF-8'); ?>">Advanced</a>
        → <strong>Reset incomplete finish</strong> (explicit), then use Finish once.
      </p>
    <?php } elseif ($tournamentCanMakeOfficial) { ?>
      <div class="k2-amiga-organizer-table__reprocess">
        <p class="k2-amiga-organizer-table__preview-note">
          Commits played results to ratings and tournament history. This league leaves Live and joins the historical catalog.
          <?php if ($tournamentUnratedGameCount > 0) { ?>
            <?php echo (int) $tournamentUnratedGameCount; ?> match result<?php echo $tournamentUnratedGameCount === 1 ? '' : 's'; ?> ready.
          <?php } ?>
          <?php if ($scheduledFixtureRemaining > 0) { ?>
            <strong><?php echo (int) $scheduledFixtureRemaining; ?> unplayed match<?php echo $scheduledFixtureRemaining === 1 ? '' : 'es'; ?></strong>
            will be marked void (not played) — for example when someone has to leave early.
          <?php } ?>
        </p>
        <form class="k2-amiga-organizer-table__reprocess-form" method="post" action="<?php echo $self; ?>"
          <?php if ($scheduledFixtureRemaining > 0) { ?>
              onsubmit="return confirm('Finish with <?php echo (int) $scheduledFixtureRemaining; ?> unplayed match(es)? They will be marked void and will not count as games.');"
          <?php } ?>
        >
          <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="reprocess_tournament_derived">
          <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
          <input type="hidden" name="view" value="table">
          <?php if ($status !== '') { ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
          <?php } ?>
          <button type="submit" class="k2-amiga-organizer-lifecycle__action k2-amiga-organizer-lifecycle__action--primary"><?php echo k2_h(AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL); ?></button>
        </form>
      </div>
    <?php } elseif ($organizerTableDisplay['preview_note'] !== null) { ?>
      <p class="k2-amiga-organizer-table__preview-note"><?php echo k2_h($organizerTableDisplay['preview_note']); ?></p>
    <?php } ?>
    <?php if ($organizerTableDisplay['rows'] === []) { ?>
      <p class="k2-amiga-live-ops__muted">No registered entrants yet. Add players on the Players tab.</p>
    <?php } else {
        amiga_tournament_render_standings_table($organizerTableDisplay['rows'], false);
    } ?>
  </div>
<?php } ?>

<?php if ($view === 'results' && $tournamentId > 0 && $tournament !== null) {
    $lifecycleRunning = $lifecycle !== null && $lifecycle['lifecycle_status'] === 'running';
    $setupTabUrl = amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, 'setup', $status);
    ?>
  <div class="k2-amiga-live-ops__section k2-amiga-organizer-results">
    <h2>Enter results</h2>
    <?php if ($tournamentUnratedGameCount > 0) {
        $tableTabUrl = amiga_fixture_ops_url($self, $key, $pwdValue, $tournamentId, 'table', $status);
        ?>
      <p class="k2-amiga-organizer-results__hint k2-amiga-organizer-table__preview-note--warn">
        When the league is finished, use <strong><?php echo k2_h(AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL); ?></strong> on the
        <a href="<?php echo htmlspecialchars($tableTabUrl, ENT_QUOTES, 'UTF-8'); ?>">Table tab</a>
        (<?php echo (int) $tournamentUnratedGameCount; ?> result<?php echo $tournamentUnratedGameCount === 1 ? '' : 's'; ?> ready).
      </p>
    <?php } ?>
    <?php if ($organizerLifecycleUi !== null && $organizerLifecycleUi['is_imported']) { ?>
      <p class="k2-amiga-organizer-results__hint k2-amiga-live-ops__muted">Historical import — result entry is read-only in the browser. Use CLI for ops changes.</p>
    <?php } elseif (!$lifecycleRunning) { ?>
      <p class="k2-amiga-organizer-results__hint">Result entry unlocks after you <strong>Start tournament</strong> on the <a href="<?php echo htmlspecialchars($setupTabUrl, ENT_QUOTES, 'UTF-8'); ?>">Setup</a> tab.</p>
    <?php } else { ?>
      <?php if ($fixtureResultsPartition['skipped_void'] > 0 || $fixtureResultsPartition['skipped_incomplete'] > 0) { ?>
        <p class="k2-amiga-organizer-results__hint k2-amiga-live-ops__muted"><?php
            $skipParts = [];
            if ($fixtureResultsPartition['skipped_incomplete'] > 0) {
                $n = $fixtureResultsPartition['skipped_incomplete'];
                $skipParts[] = $n . ' incomplete match' . ($n === 1 ? '' : 'es') . ' (assign players on Advanced)';
            }
            if ($fixtureResultsPartition['skipped_void'] > 0) {
                $n = $fixtureResultsPartition['skipped_void'];
                $skipParts[] = $n . ' void match' . ($n === 1 ? '' : 'es');
            }
            echo k2_h(implode('; ', $skipParts)) . ' omitted from entry.';
        ?></p>
      <?php } ?>
      <?php if ($fixtureResultsEntryGroups === []) { ?>
        <p class="k2-amiga-live-ops__muted">No matches waiting for scores<?php echo $fixtureResultsPartition['played'] !== [] ? ' — all listed fixtures have been entered.' : '.'; ?></p>
      <?php } else { ?>
        <?php foreach ($fixtureResultsEntryGroups as $entryGroup) { ?>
          <section class="k2-amiga-organizer-results__group">
            <h3 class="k2-amiga-organizer-results__heading"><?php echo k2_h($entryGroup['label']); ?></h3>
            <ul class="k2-amiga-organizer-results__entries">
            <?php foreach ($entryGroup['fixtures'] as $row) {
                $playerA = (string) ($row['player_a_name'] ?? 'TBD');
                $playerB = (string) ($row['player_b_name'] ?? 'TBD');
                ?>
              <li class="k2-amiga-organizer-results__entry">
                <form class="k2-amiga-organizer-results__form k2-amiga-live-ops__inline-form" method="post" action="<?php echo $self; ?>">
                  <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="action" value="record_result">
                  <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                  <input type="hidden" name="view" value="results">
                  <?php if ($status !== '') { ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php } ?>
                  <input type="hidden" name="fixture_id" value="<?php echo (int) $row['id']; ?>">
                  <span class="k2-amiga-organizer-results__matchup"><?php echo k2_h($playerA); ?> <span class="k2-amiga-organizer-schedule__vs">vs</span> <?php echo k2_h($playerB); ?></span>
                  <label class="k2-amiga-live-ops__muted"><?php echo k2_h($playerA); ?>
                    <input type="number" name="goals_a" min="0" max="99" required aria-label="Goals for <?php echo k2_h($playerA); ?>">
                  </label>
                  <span aria-hidden="true">–</span>
                  <label class="k2-amiga-live-ops__muted"><?php echo k2_h($playerB); ?>
                    <input type="number" name="goals_b" min="0" max="99" required aria-label="Goals for <?php echo k2_h($playerB); ?>">
                  </label>
                  <button type="submit">Save result</button>
                </form>
              </li>
            <?php } ?>
            </ul>
          </section>
        <?php } ?>
      <?php } ?>
      <?php if ($fixtureResultsPlayedGroups !== []) { ?>
        <section class="k2-amiga-organizer-results__played">
          <h3 class="k2-amiga-organizer-results__played-heading">Already entered</h3>
          <?php foreach ($fixtureResultsPlayedGroups as $playedGroup) { ?>
            <div class="k2-amiga-organizer-results__played-group">
              <h4 class="k2-amiga-organizer-results__played-label"><?php echo k2_h($playedGroup['label']); ?></h4>
              <ul class="k2-amiga-organizer-results__played-list">
              <?php foreach ($playedGroup['fixtures'] as $row) {
                  $playerA = (string) ($row['player_a_name'] ?? 'TBD');
                  $playerB = (string) ($row['player_b_name'] ?? 'TBD');
                  ?>
                <li class="k2-amiga-organizer-results__played-row">
                  <span class="k2-amiga-organizer-results__matchup"><?php echo k2_h($playerA); ?> <span class="k2-amiga-organizer-schedule__vs">vs</span> <?php echo k2_h($playerB); ?></span>
                  <span class="k2-amiga-organizer-results__played-score"><?php echo (int) $row['goals_a']; ?>–<?php echo (int) $row['goals_b']; ?></span>
                  <?php
                    $fixtureId = (int) $row['id'];
                    $canUndoResult = $entrantOpsEligible
                        && $lifecycleRunning
                        && !$tournamentRatingFinalized
                        && amiga_running_tournament_fixture_has_result($row);
                    if ($canUndoResult) { ?>
                    <form class="k2-amiga-organizer-results__undo-form" method="post" action="<?php echo $self; ?>">
                      <input type="hidden" name="once" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="pwd" value="<?php echo htmlspecialchars($pwdValue, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="undo_fixture_result">
                      <input type="hidden" name="tournament_id" value="<?php echo (int) $tournamentId; ?>">
                      <input type="hidden" name="view" value="results">
                      <input type="hidden" name="fixture_id" value="<?php echo $fixtureId; ?>">
                      <?php if ($status !== '') { ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php } ?>
                      <button type="submit" class="k2-amiga-organizer-results__undo">Undo</button>
                    </form>
                  <?php } ?>
                </li>
              <?php } ?>
              </ul>
            </div>
          <?php } ?>
        </section>
      <?php } ?>
    <?php } ?>
  </div>
<?php } ?>

</div><!-- .k2-amiga-organizer-panel -->

<?php if ($view === 'setup' && ($tournamentId <= 0 || $tournament === null)) { ?>
<div class="k2-amiga-live-ops__section">
  <h2>Recent leagues</h2>
  <?php if ($generatedTournaments === []) { ?>
    <p class="k2-amiga-live-ops__muted">No leagues yet — create one above.</p>
  <?php } else { ?>
    <div class="k2-table-wrap">
    <table class="k2-table k2-table--numeric-default k2-table--calm-stats">
      <thead>
        <tr><th class="k2-table-cell--left">League</th><th>Date</th><th>Fixtures</th><th>Games</th><th class="k2-table-cell--left"></th></tr>
      </thead>
      <tbody>
      <?php foreach ($generatedTournaments as $row) {
          $viewUrl = amiga_fixture_ops_url($self, $key, $pwdValue, (int) $row['id'], 'fixtures');
          ?>
        <tr>
          <td class="k2-table-cell--left"><?php echo k2_h((string) $row['name']); ?></td>
          <td><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '<span class="k2-amiga-live-ops__muted">—</span>'; ?></td>
          <td><?php echo (int) $row['fixture_count']; ?></td>
          <td><?php echo (int) $row['game_count']; ?></td>
          <td class="k2-table-cell--left"><a href="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>">Open</a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>
  <?php } ?>
</div>
<?php } ?>
</div>
<?php amiga_fixture_render_chrome_end();
