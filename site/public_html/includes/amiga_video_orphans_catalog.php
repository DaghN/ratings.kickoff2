<?php
declare(strict_types=1);

/**
 * Curated orphan-video groups (hand-maintained, Jun 2026).
 * YouTube ids must have no guessed_tournament_id in review.csv.
 * Tournament assignment review sign-off Jun 2026 — groups are non-event KO2 / dup candidates only.
 */
return [
    [
        'id' => 'wc2008-ko2cv-coverage',
        'title' => 'Likely WC 2008 Athens clips (KO2CV coverage filenames)',
        'lede' => 'Athens08_* ko2cv uploads without tournament id. Canonical alkelele/forum rows are on tid 358; these may be dupes or extras.',
        'youtube_ids' => [
            'rWC_SyUpVsY',
            'hOX45SSMPPs',
            'P7Hy8ukFMV4',
            'nHCgeNguYjo',
            'Jmf-dMGlr3Q',
            'gXDfN4FIpoA',
        ],
    ],
    [
        'id' => 'alkelele-shot-height',
        'title' => 'Alkelele — Shot height series',
        'lede' => 'Tutorial series (parts 1–12). General technique, not tied to one event.',
        'youtube_ids' => [
            'gmTGWqrpfvk',
            '8AMm3tbu_Zk',
            'E3z0nUEaEdQ',
            '6NvVlNJCtsE',
            '2_9tRTGBRGw',
            'SnXmRNT2Wlw',
            'liHK9Hwyjm8',
            'r51TubowiOA',
            'OEEPDiPpuh4',
            'k-olmsY16pE',
            'yPzByUETgEo',
            'n-lLmj9Bn6c',
        ],
    ],
    [
        'id' => 'alkelele-technique',
        'title' => 'Alkelele — Technique & tricks',
        'lede' => 'Single-topic tutorials and compilations from alkelele channel.',
        'youtube_ids' => [
            'rOunQzfpmGM',
            'rY3wB0A1RL8',
            'ATYc-Lq4pOM',
            'UMshtbULLR0',
            'TAp83vT0-0U',
            'RUS5l3mciP8',
            'fXi9Nl0QtPY',
            'mx0jEWsyobI',
            'DVZW7pW_s4w',
            'RRz0OwZFzD4',
        ],
    ],
    [
        'id' => 'general-ko2',
        'title' => 'General KO2 / channel misc',
        'lede' => 'PC setup, analysis, and short clips without event context.',
        'youtube_ids' => [
            'kOTZ1zFlqGo',
            'UKK1qptd08c',
            'RMGeWarwrEo',
        ],
    ],
    [
        'id' => 'community-clips',
        'title' => 'Community clips (costas / ko2cv)',
        'lede' => 'Short or informal uploads — not mapped to any tournament (reviewed Jun 2026).',
        'youtube_ids' => [
            'YjhOh2gXgkY',
            'rqXl5Io_tes',
        ],
    ],
];