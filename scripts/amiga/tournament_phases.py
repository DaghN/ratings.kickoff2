"""Normalize Access phase labels into standings scope keys.

Phase label taxonomy (non-exhaustive; strings are messy):
- Group stages: ``Round 1 - Group A``, ``Round 1 Group A``, ``Silver Cup - Group G``
- Knockout: ``Quarter Finals``, ``Semi Finals``, ``Final``, placement finals
- Placement brackets: ``Places 9-16``, ``Places 17-20``, …
- Informal: phase NULL → single overall round-robin within the tournament
"""

from __future__ import annotations

import re
from dataclasses import dataclass
from enum import Enum


class ScopeType(str, Enum):
    OVERALL = "overall"
    GROUP = "group"
    PLACEMENT = "placement"
    KNOCKOUT = "knockout"


@dataclass(frozen=True, slots=True)
class PhaseScope:
    scope_type: ScopeType
    scope_key: str  # '' for overall; canonical label for group/placement


_GROUP_RE = re.compile(
    r"^(?:(?P<prefix>Round\s+\d+|Silver\s+Cup|Bronze\s+Cup)\s*[-]?\s*)?"
    r"Group\s+(?P<group>[A-Z](?:/[A-Z])?)$",
    re.IGNORECASE,
)
_PLACES_RE = re.compile(r"^Places\s+(\d+(?:-\d+)?)$", re.IGNORECASE)
_PLACE_FINAL_RE = re.compile(r"^\d+(?:st|nd|rd|th)\s+Place\s+Final$", re.IGNORECASE)
_KNOCKOUT_LABELS = frozenset(
    {
        "quarter finals",
        "semi finals",
        "final",
        "3rd place final",
        "5th place final",
        "7th place final",
        "9th place final",
        "11th place final",
        "13th place final",
        "15th place final",
    }
)


def _normalize_whitespace(label: str) -> str:
    return re.sub(r"\s+", " ", label.strip())


def _canonical_group_key(prefix: str | None, group: str) -> str:
    group = group.upper().replace(" ", "")
    if prefix:
        prefix_norm = _normalize_whitespace(prefix)
        return f"{prefix_norm} - Group {group}"
    return f"Group {group}"


def is_knockout_phase(phase: str | None) -> bool:
    """Elimination phases: paired ties, not league tables."""
    if not phase or not str(phase).strip():
        return False
    label = _normalize_whitespace(str(phase))
    if label.lower() in _KNOCKOUT_LABELS:
        return True
    if _PLACES_RE.match(label):
        return True
    if _PLACE_FINAL_RE.match(label):
        return True
    return False


def knockout_pair_scope_key(phase: str, player_a_id: int, player_b_id: int) -> str:
    """Stable scope_key for one elimination tie (two players)."""
    phase = _normalize_whitespace(phase)
    lo, hi = min(player_a_id, player_b_id), max(player_a_id, player_b_id)
    return f"{phase}|{lo}-{hi}"


def parse_phase(phase: str | None) -> PhaseScope:
    """Map a game phase label to a standings aggregation scope."""
    if not phase or not str(phase).strip():
        return PhaseScope(ScopeType.OVERALL, "")

    label = _normalize_whitespace(str(phase))

    if is_knockout_phase(label):
        return PhaseScope(ScopeType.KNOCKOUT, label)

    m = _GROUP_RE.match(label)
    if m:
        return PhaseScope(
            ScopeType.GROUP,
            _canonical_group_key(m.group("prefix"), m.group("group")),
        )

    m2 = re.match(
        r"^(?P<prefix>Round\s+\d+|Silver\s+Cup|Bronze\s+Cup)\s*-\s*Group\s+(?P<group>[A-Z](?:/[A-Z])?)$",
        label,
        re.IGNORECASE,
    )
    if m2:
        return PhaseScope(
            ScopeType.GROUP,
            _canonical_group_key(m2.group("prefix"), m2.group("group")),
        )

    # Unknown structured label — treat as its own group-like scope for aggregation.
    return PhaseScope(ScopeType.GROUP, label)


def access_group_label_for_parity(scope_key: str) -> str:
    """Map derived scope_key to Access ``World Cup * Tables``.Tournament short label."""
    m = re.match(r"^Round\s+\d+\s*-\s*Group\s+([A-Z](?:/[A-Z])?)$", scope_key, re.IGNORECASE)
    if m:
        return f"Group {m.group(1).upper()}"
    m2 = re.match(r"^Group\s+([A-Z](?:/[A-Z])?)$", scope_key, re.IGNORECASE)
    if m2:
        return f"Group {m2.group(1).upper()}"
    return scope_key


def is_league_scope(scope: PhaseScope) -> bool:
    """Scopes that contribute W/D/L points tables (round-robin groups only)."""
    if scope.scope_type == ScopeType.OVERALL:
        return True
    if scope.scope_type == ScopeType.GROUP:
        return True
    return False
