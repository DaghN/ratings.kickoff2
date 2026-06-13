"""Slice 6 register: non-WC tier-B curation (planning Jun 2026).

World Cup catalog names → **WC track** (slice 8+), not slice 6.
Regenerate audit: ``python -m scripts.oneoff.curate_tier_b_non_wc``.
"""

from __future__ import annotations

import re
from typing import Final

_WORLD_CUP_NAME_RE = re.compile(r"^World Cup\s+\S", re.IGNORECASE)


def is_world_cup_catalog_name(name: str) -> bool:
    """Match ``amiga_tournament_is_world_cup_by_name()`` in PHP."""
    return _WORLD_CUP_NAME_RE.match(name.strip()) is not None


# 23 tier-B World Cups — defer to WC track; never slice 6 bulk.
DEFERRED_WORLD_CUP_TOURNAMENT_IDS: frozenset[int] = frozenset({
    5, 9, 14, 16, 20, 25, 26, 66, 115, 140, 169, 206, 280, 358, 418,
    480, 526, 554, 569, 577, 585, 596, 603,
})

# Tier C / manual — non-WC labeled events distrusted for auto bulk (slice 6b queue).
NON_WC_STRUCTURE_REVIEW_IDS: frozenset[int] = frozenset({
    22,   # Athens XCI — League Stage (odd singleton scope)
    294,  # Langenfeld II — Fun Cup
    352,  # Wiesbaden V — Playout Group
    406,  # Seeshaupt III — Game of Shame
    409,  # Hamburg V — Fun Cup
    440,  # Frankfurt II — Gold Cup / Silver Cup (event-wide labels, not groups)
    477,  # Osnabruck II — Fun Cup
    496,  # Rodenbach I — Fun Cup
    503,  # Leicester I — Qualifying Round
    591,  # Amsterdam I — multi parallel cup tracks (bronze/silver/clogs)
    592,  # Athens LXXXV — 66 NULL + 12 labeled (mixed provenance)
})

# Fix ``tournament_phases.py`` before materialize — **slice 6a** (not slice 6 bulk).
NON_WC_PARSER_FIX_FIRST_IDS: frozenset[int] = frozenset({
    48,   # Groningen VII — Playouts; Semi Final vs Semi Finals
    145,  # Milan V — Play Outs
    152,  # Homburg II — Playouts
    166,  # Milan XII — Finals (plural)
    198,  # Milan XVII — Playouts 5-7
    267,  # Seeshaupt — Game of Shame + round
    269,  # Cologne I — Place N Final variants
    284,  # Athens LIII — Places 5-8, Playouts Group, Playoffs Group
})

# Slice 6 bulk allow — 41 non-WC cups/champs with uniform KO/group phase labels.
NON_WC_TIER_B_AUTO_MATERIALIZE_IDS: frozenset[int] = frozenset({
    75, 89, 121, 156, 158, 171, 173, 176, 189, 192, 215, 248, 276, 316, 317,
    329, 338, 341, 345, 413, 414, 452, 453, 454, 463, 465, 471, 493, 500, 518,
    519, 521, 524, 535, 540, 544, 548, 553, 566, 568, 570,
})

# GATE E pilots (non-WC bulk).
NON_WC_PILOT_TOURNAMENT_IDS: tuple[int, ...] = (
    75,   # Gloucester I Cup — typical labeled cup
    158,  # Stoke Cup
    592,  # negative control — must refuse
)

# Union with NULL-phase audit flags in materialize_legacy (416, …).
def all_structure_review_tournament_ids() -> frozenset[int]:
    from scripts.amiga.tournament_structure.materialize_legacy import (
        STRUCTURE_REVIEW_TOURNAMENT_IDS,
    )

    return STRUCTURE_REVIEW_TOURNAMENT_IDS | NON_WC_STRUCTURE_REVIEW_IDS


def is_parser_fix_deferred(tournament_id: int) -> bool:
    """True while id is in slice 6a queue (refuse materialize until parser fixed + re-curated)."""
    return tournament_id in NON_WC_PARSER_FIX_FIRST_IDS


def is_deferred_from_slice_6(tournament_id: int, tournament_name: str) -> bool:
    return tournament_id in DEFERRED_WORLD_CUP_TOURNAMENT_IDS or is_world_cup_catalog_name(
        tournament_name
    )


def is_slice_6_auto_ok(tournament_id: int, tournament_name: str) -> bool:
    if is_deferred_from_slice_6(tournament_id, tournament_name):
        return False
    if tournament_id in NON_WC_STRUCTURE_REVIEW_IDS:
        return False
    if tournament_id in NON_WC_PARSER_FIX_FIRST_IDS:
        return False
    return tournament_id in NON_WC_TIER_B_AUTO_MATERIALIZE_IDS
