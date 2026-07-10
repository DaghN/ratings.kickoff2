"""Slice 6 register: non-WC tier-B curation (planning Jun 2026).

World Cup catalog names → **WC track** (slice 8+), not slice 6.
Regenerate audit: ``python -m scripts.oneoff.curate_tier_b_non_wc``.
Cup safety audit: ``python -m scripts.oneoff.audit_auto_ok_cups``.
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

# Original slice 6b manual review (pre–cup-audit curation).
NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS: frozenset[int] = frozenset({
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

# Slice 6 bulk mis-curated (Jun 2026 audit) — not obvious 2^n single-elim cups.
# Dematerialized + materialize refuses until human triage.
NON_WC_SLICE6_CUP_REVIEW_IDS: frozenset[int] = frozenset({
    75,   # Gloucester I Cup — 24p bye; Round 1/2 as league
    89,   # Milan — groups
    121,  # Norwegian Champs — groups + placement
    156,  # Milan X — groups
    158,  # Stoke Cup — 15p bye; Round 1 → league (parser)
    171,  # Copenhagen Cup — placement band
    189,  # Manchester II Cup — 15p bye; Round 1 → league
    192,  # Hertford V Cup — extra Round 1 games
    215,  # Kelkheim VII — placement marathon
    248,  # Athens XXXVIII Cup — not n-1
    276,  # Langenfeld — Round 1 league + Places 5-8
    316,  # Birmingham VIII Gold — Round 1 → league despite 8p/7g
    317,  # Birmingham VIII Silver — bye; Round 1 → league
    329,  # Athens LXI Cup — placement
    338,  # Seeshaupt II — multi-game Round 1
    341,  # Copenhagen III Cup — Round 1 league
    345,  # Voitsberg I — 5-player oddity
    414,  # Birmingham XIV Silver — 6p bye (KO labels OK; demoted pending triage)
    452,  # Birmingham XXI Gold — 6p bye
    463,  # Dudley XX Cup — extra games
    465,  # Wiesbaden XII — groups + gold/silver
    471,  # Seeshaupt IV — extra games
    493,  # Birmingham XXVII — bye; Round 1 → league
    500,  # Birmingham XXVIII — 6p bye
    518,  # Seeshaupt V — groups
    519,  # Birmingham XXXIII — bye; Round 1 → league
    521,  # Oldenburg II — full group cup + many placement finals
    524,  # Birmingham XXXV — extra games
    535,  # Birmingham XXXVII — Round 1 as league
    544,  # Bournemouth II — 7p bye
    553,  # Hanau III — placement marathon
    568,  # Birmingham XLV — 4 games for 4 players
    570,  # Volkenrath IV — Round 1 league + Places 5-8
})

NON_WC_STRUCTURE_REVIEW_IDS: frozenset[int] = (
    NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS | NON_WC_SLICE6_CUP_REVIEW_IDS
)

# Fix ``tournament_phases.py`` before materialize — **slice 6a** (not slice 6 bulk).
NON_WC_PARSER_FIX_FIRST_IDS: frozenset[int] = frozenset({
    48,   # Groningen VII — Playouts; Semi Final vs Semi Finals
    152,  # Homburg II — Playouts
    198,  # Milan XVII — Playouts 5-7
    267,  # Seeshaupt — Game of Shame + round
    269,  # Cologne I — Place N Final variants
    284,  # Athens LIII — Places 5-8, Playouts Group, Playoffs Group
})

# Slice 6 bulk allow — obvious 2^n single-elim cups only (Jun 2026 audit).
NON_WC_TIER_B_AUTO_MATERIALIZE_IDS: frozenset[int] = frozenset({
    413,  # Birmingham XIV Gold Cup — 8p 7g all KO
    453,  # Birmingham XXI Silver Cup — 4p 3g
    454,  # Birmingham XXI Bronze Cup — 4p 3g
    540,  # Birmingham XXXVIII — 4p 3g
    548,  # Birmingham XL — 4p 3g
    566,  # Birmingham XLIII — 4p 3g
})

# GATE E pilots (non-WC bulk).
NON_WC_PILOT_TOURNAMENT_IDS: tuple[int, ...] = (
    413,  # Birmingham XIV Gold Cup — safe 8p/7g pure cup
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
