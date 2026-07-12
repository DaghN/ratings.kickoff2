"""Slice 6 register: non-WC tier-B curation (planning Jun 2026).

World Cup catalog names → **WC track** (slice 8+), not slice 6.
Regenerate audit: ``python -m scripts.oneoff.curate_tier_b_non_wc``.
Cup safety audit: ``python -m scripts.oneoff.audit_auto_ok_cups``.
"""

from __future__ import annotations

import re
from typing import Any, Final

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
NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS: frozenset[int] = frozenset()

# Slice 6 cup-audit blockers — ids here refuse ``materialize`` until human triage.
# **Remove each id after materialize** (runbook § stale register hygiene).
# Cleared Jul 2026 (already on work DB): 75, 158, 171, 189, 192 (pure knockout cups);
# labeled-phase tail 465, 518, 570, 521, 553.
NON_WC_SLICE6_CUP_REVIEW_IDS: frozenset[int] = frozenset()

NON_WC_STRUCTURE_REVIEW_IDS: frozenset[int] = (
    NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS | NON_WC_SLICE6_CUP_REVIEW_IDS
)

# Fix ``tournament_phases.py`` before materialize — **slice 6a** (not slice 6 bulk).
NON_WC_PARSER_FIX_FIRST_IDS: frozenset[int] = frozenset({
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


def _stage_counts_by_tournament(conn) -> dict[int, int]:
    import pymysql

    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            """
            SELECT t.id AS tournament_id,
                   COUNT(s.id) AS stage_count
            FROM tournaments t
            LEFT JOIN tournament_stages s ON s.tournament_id = t.id
            GROUP BY t.id
            """
        )
        return {int(row["tournament_id"]): int(row["stage_count"]) for row in cur.fetchall()}


def audit_stale_structure_review_register(
    conn,
    *,
    register: Any | None = None,
) -> dict[str, Any]:
    """Find review frozensets / pending_review rows that no longer block materialize.

    Stale = tournament already has ``tournament_stages`` but still listed as a
    materialize refusal or disposition ``pending_review``.
    """
    from scripts.amiga.tournament_structure.disposition_register import (
        HANDLER_PENDING_REVIEW,
        DispositionRegister,
    )

    reg = register if register is not None else DispositionRegister.load()
    stage_counts = _stage_counts_by_tournament(conn)

    review_frozenset_materialized: list[dict[str, Any]] = []
    for tid in sorted(all_structure_review_tournament_ids()):
        stages = stage_counts.get(tid, 0)
        if stages <= 0:
            continue
        row = reg.get(tid)
        review_frozenset_materialized.append(
            {
                "tournament_id": tid,
                "name": _catalog_name(conn, tid),
                "stages": stages,
                "disposition_handler": row.handler if row else None,
                "action": "remove from tier_b_non_wc_register / STRUCTURE_REVIEW frozenset",
            }
        )

    pending_review_materialized: list[dict[str, Any]] = []
    for tid, row in sorted(reg.rows.items()):
        if row.handler != HANDLER_PENDING_REVIEW:
            continue
        stages = stage_counts.get(tid, 0)
        if stages <= 0:
            continue
        pending_review_materialized.append(
            {
                "tournament_id": tid,
                "name": _catalog_name(conn, tid),
                "stages": stages,
                "disposition_notes": row.notes,
                "action": "promote disposition handler + review-queue log (already materialized)",
            }
        )

    stale_count = len(review_frozenset_materialized) + len(pending_review_materialized)
    return {
        "ok": stale_count == 0,
        "stale_count": stale_count,
        "review_frozenset_materialized": review_frozenset_materialized,
        "pending_review_materialized": pending_review_materialized,
    }


def _catalog_name(conn, tournament_id: int) -> str:
    import pymysql

    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute("SELECT name FROM tournaments WHERE id = %s", (tournament_id,))
        row = cur.fetchone()
    return str(row["name"]) if row else f"id={tournament_id}"
