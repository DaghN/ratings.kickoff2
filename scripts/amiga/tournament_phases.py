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
        if re.match(r"^Round\s+\d+$", prefix_norm, re.IGNORECASE):
            return f"{prefix_norm} - Group {group}"
        return f"{prefix_norm} - Group {group}"
    return f"Group {group}"


def parse_phase(phase: str | None) -> PhaseScope:
    """Map a game phase label to a standings aggregation scope."""
    if not phase or not str(phase).strip():
        return PhaseScope(ScopeType.OVERALL, "")

    label = _normalize_whitespace(str(phase))

    m = _GROUP_RE.match(label)
    if m:
        return PhaseScope(
            ScopeType.GROUP,
            _canonical_group_key(m.group("prefix"), m.group("group")),
        )

    # ``Round 1 - Group A`` with hyphen already in source string.
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

    if _PLACES_RE.match(label):
        return PhaseScope(ScopeType.PLACEMENT, label)

    if label.lower() in _KNOCKOUT_LABELS:
        # Knockout rows are not league tables in v1; skip from group/overall aggregation.
        return PhaseScope(ScopeType.PLACEMENT, label)

    # Unknown structured label — treat as its own group-like scope for aggregation.
    if label:
        return PhaseScope(ScopeType.GROUP, label)

    return PhaseScope(ScopeType.OVERALL, "")


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
    """Scopes that contribute W/D/L points tables (not knockouts in v1)."""
    if scope.scope_type == ScopeType.OVERALL:
        return True
    if scope.scope_type == ScopeType.GROUP:
        return True
    if scope.scope_type == ScopeType.PLACEMENT and scope.scope_key.lower().startswith("places "):
        return True
    return False
