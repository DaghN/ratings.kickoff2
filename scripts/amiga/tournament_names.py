"""Normalize Access tournament strings before catalog lookup."""

from __future__ import annotations

# Milan X knock-out rows were stored as separate tournament names in Scores.
MILAN_X_PARENT = "Milan X"
MILAN_X_FRAGMENTS: dict[str, str] = {
    "Milan X 3rd Place Final": "3rd Place Final",
    "Milan X Final": "Final",
    "Milan X Quarter Finals": "Quarter Finals",
    "Milan X Round 1 Group A": "Round 1 Group A",
    "Milan X Round 1 Group B": "Round 1 Group B",
    "Milan X Semi Finals": "Semi Finals",
}

# Other Scores-only names → parent catalog tournament.
TOURNAMENT_ALIASES: dict[str, str] = {
    **{k: MILAN_X_PARENT for k in MILAN_X_FRAGMENTS},
    "Gloucester III Team": "Gloucester III",
    "Groningen VII Cup": "Groningen VII",
}


def resolve_tournament_name(raw: str | None) -> str:
    if not raw:
        return ""
    name = raw.strip()
    return TOURNAMENT_ALIASES.get(name, name)


def resolve_phase(raw_tournament: str | None, access_phase: str | None) -> str | None:
    """Use explicit Phase when present; else infer stage from Milan X fragment names."""
    if access_phase and str(access_phase).strip():
        return str(access_phase).strip()
    if not raw_tournament:
        return None
    fragment = MILAN_X_FRAGMENTS.get(raw_tournament.strip())
    return fragment
