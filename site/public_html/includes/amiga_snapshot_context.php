<?php
/**
 * Amiga realm time travel — request-scoped snapshot context (`?as=wing:key`).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_rating_history_lib.php';

/** @var AmigaSnapshotContext|null */
$GLOBALS['_amiga_snapshot_context'] = null;

final class AmigaSnapshotContext
{
    /**
     * @param list<array<string, mixed>> $catalog
     * @param array<string, mixed>|null $entry
     */
    private function __construct(
        private readonly bool $active,
        private readonly string $wing,
        private readonly string $key,
        private readonly ?array $entry,
        private readonly ?string $prevKey,
        private readonly ?string $nextKey,
        private readonly array $catalog,
    ) {
    }

    public static function present(): self
    {
        return new self(false, 'event', '', null, null, null, []);
    }

    /**
     * @param array{
     *   wing: string,
     *   key: string,
     *   entry: array<string, mixed>|null,
     *   prev_key: string|null,
     *   next_key: string|null,
     *   catalog: list<array<string, mixed>>
     * } $view
     */
    public static function fromCatalogView(array $view): self
    {
        $entry = $view['entry'];
        if ($entry === null || $entry['cutoff_tournament_id'] === null) {
            return self::present();
        }

        return new self(
            true,
            $view['wing'],
            (string) $view['key'],
            $entry,
            $view['prev_key'],
            $view['next_key'],
            $view['catalog'],
        );
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function wing(): string
    {
        return $this->wing;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): ?string
    {
        if (!$this->active || $this->entry === null) {
            return null;
        }

        return (string) ($this->entry['label'] ?? '');
    }

    /**
     * @return array{
     *   wing: string,
     *   key: string,
     *   tournament_id: int,
     *   event_date: string,
     *   chrono: float,
     *   label: string
     * }|null
     */
    public function cutoff(): ?array
    {
        return amiga_snapshot_cutoff_from_catalog_entry($this->entry, $this->wing, $this->key);
    }

    public function prevKey(): ?string
    {
        return $this->prevKey;
    }

    public function nextKey(): ?string
    {
        return $this->nextKey;
    }

    /**
     * @return array{
     *   wing: string,
     *   key: string,
     *   tournament_id: int,
     *   event_date: string,
     *   chrono: float,
     *   label: string
     * }|null
     */
    public function prevCutoff(): ?array
    {
        if (!$this->active || $this->prevKey === null || $this->prevKey === '') {
            return null;
        }

        $prevEntry = amiga_rating_history_catalog_entry_by_key($this->catalog, $this->prevKey);
        if ($prevEntry === null) {
            return null;
        }

        return amiga_snapshot_cutoff_from_catalog_entry($prevEntry, $this->wing, $this->prevKey);
    }

    /**
     * Canonical `as` value, e.g. `year:2003`.
     */
    public function asParam(): ?string
    {
        if (!$this->active) {
            return null;
        }

        return amiga_snapshot_format_as_param($this->wing, $this->key);
    }

    /** Query fragment `as=year%3A2003` or empty when present. */
    public function asQueryString(): string
    {
        $param = $this->asParam();
        if ($param === null) {
            return '';
        }

        return 'as=' . rawurlencode($param);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return $this->catalog;
    }

    /**
     * Active wing catalog entry (label, cutoff ids, …).
     *
     * @return array<string, mixed>|null
     */
    public function entry(): ?array
    {
        return $this->entry;
    }
}

function amiga_snapshot_context_from_request(mysqli $con): AmigaSnapshotContext
{
    if ($GLOBALS['_amiga_snapshot_context'] instanceof AmigaSnapshotContext) {
        return $GLOBALS['_amiga_snapshot_context'];
    }

    $asRaw = amiga_snapshot_as_param_from_request();
    if ($asRaw === null) {
        $ctx = AmigaSnapshotContext::present();
        $GLOBALS['_amiga_snapshot_context'] = $ctx;

        return $ctx;
    }

    $view = amiga_snapshot_resolve_as($con, $asRaw);
    if ($view === null) {
        $ctx = AmigaSnapshotContext::present();
        $GLOBALS['_amiga_snapshot_context'] = $ctx;

        return $ctx;
    }

    $ctx = AmigaSnapshotContext::fromCatalogView($view);
    $GLOBALS['_amiga_snapshot_context'] = $ctx;

    return $ctx;
}

function amiga_snapshot_context_peek(): ?AmigaSnapshotContext
{
    $ctx = $GLOBALS['_amiga_snapshot_context'] ?? null;

    return $ctx instanceof AmigaSnapshotContext ? $ctx : null;
}

/** Reset cached context (tests / probes only). */
function amiga_snapshot_context_reset(): void
{
    $GLOBALS['_amiga_snapshot_context'] = null;
    $GLOBALS['_amiga_rating_history_tournaments'] = null;
}

/**
 * Read `as` from request, or legacy history `wing` + `at`.
 */
function amiga_snapshot_as_param_from_request(): ?string
{
    if (isset($_GET['as'])) {
        $as = trim((string) $_GET['as']);
        if ($as !== '') {
            return $as;
        }
    }

    if (isset($_GET['wing'], $_GET['at'])) {
        $at = trim((string) $_GET['at']);
        if ($at !== '') {
            $wing = amiga_rating_history_normalize_wing((string) $_GET['wing']);

            return amiga_snapshot_format_as_param($wing, $at);
        }
    }

    return null;
}

/**
 * Valid `as` from the request for URL propagation (parse-only; no DB).
 */
function amiga_snapshot_propagate_as_param(): ?string
{
    $raw = amiga_snapshot_as_param_from_request();
    if ($raw === null) {
        return null;
    }

    return amiga_snapshot_parse_as_param($raw) !== null ? $raw : null;
}

/**
 * Map active cutoff to a wing catalog key when switching Year / Month / Event tabs.
 *
 * @param array{event_date: string, tournament_id: int}|null $cutoff
 */
function amiga_snapshot_wing_key_from_cutoff(?array $cutoff, string $targetWing): ?string
{
    if ($cutoff === null) {
        return null;
    }

    return match (amiga_rating_history_normalize_wing($targetWing)) {
        'year' => substr($cutoff['event_date'], 0, 4),
        'month' => substr($cutoff['event_date'], 0, 7),
        default => (string) $cutoff['tournament_id'],
    };
}

/** Default time-travel entry — first calendar year (`year:{yyyy}`). */
function amiga_snapshot_latest_as_param(mysqli $con): ?string
{
    $catalog = amiga_rating_history_catalog_year($con);
    if ($catalog === []) {
        return null;
    }

    $first = $catalog[0];

    return amiga_snapshot_format_as_param('year', (string) $first['key']);
}
