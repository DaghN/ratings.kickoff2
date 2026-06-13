"""Normalize Access tournament strings before catalog lookup."""

from __future__ import annotations

from scripts.amiga.import_corrections import (
    TOURNAMENT_NAME_OVERRIDES,
    WORLD_CUP_VENUES,
    world_cup_catalog_name,
    world_cup_score_aliases,
)

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

# World Cup V (2005): KOA Cup consolation bracket was a separate Access catalog row;
# same weekend event as World Cup V — phases prefixed like World Cup IV KOA Cup.
WC_V_PARENT = world_cup_catalog_name("World Cup V")
WC_V_KOA_CUP = "World Cup V KOA Cup"


def _build_tournament_aliases() -> dict[str, str]:
    aliases: dict[str, str] = {
        **{k: MILAN_X_PARENT for k in MILAN_X_FRAGMENTS},
        WC_V_KOA_CUP: WC_V_PARENT,
        **world_cup_score_aliases(),
    }
    for access_name, roman_base in TOURNAMENT_NAME_OVERRIDES.items():
        if roman_base in WORLD_CUP_VENUES:
            aliases[access_name] = world_cup_catalog_name(roman_base)
        else:
            aliases[access_name] = roman_base
    return aliases


# Other Scores-only names → parent catalog tournament.
TOURNAMENT_ALIASES: dict[str, str] = _build_tournament_aliases()

# Documented in docs/amiga-import-layer.md when adding aliases.
_MILAN_X_ALIAS_RATIONALE = (
    "Access Scores stores Milan X knock-out stages as separate Tournament strings "
    "(e.g. Milan X Quarter Finals) beside the catalog parent Milan X. Each fragment "
    "row already has a matching Phase column. Merge into Milan X via alias; "
    "resolve_phase() supplies phase when Access Phase is null on the parent label."
)

TOURNAMENT_ALIAS_RATIONALE: dict[str, str] = {
    **{k: _MILAN_X_ALIAS_RATIONALE for k in MILAN_X_FRAGMENTS},
    WC_V_KOA_CUP: (
        "Access [Tournament players] lists World Cup V KOA Cup as chrono 138 (Nov 13) "
        "beside World Cup V (chrono 137). Alkis WC 2005: non–Round 2 qualifiers played "
        "the KOA Cup; World Cup V Tables holds Groups I–L in the same reference table as "
        "A–H. World Cup IV (2004) stores KOA Cup as phased games under the parent. "
        "Merge scores into World Cup V with KOA Cup - … phase labels."
    ),
}


def scores_only_catalog_aliases() -> frozenset[str]:
    """Access catalog rows that exist only because Scores used a fragment name."""
    return frozenset(k for k, v in TOURNAMENT_ALIASES.items() if k != v)


def resolve_tournament_name(raw: str | None) -> str:
    if not raw:
        return ""
    name = raw.strip()
    return TOURNAMENT_ALIASES.get(name, name)


def resolve_phase(raw_tournament: str | None, access_phase: str | None) -> str | None:
    """Use explicit Phase when present; else infer stage from Milan X fragment names."""
    if not raw_tournament:
        return None
    raw_name = raw_tournament.strip()

    if access_phase and str(access_phase).strip():
        phase = str(access_phase).strip()
        if raw_name == WC_V_KOA_CUP and not phase.lower().startswith("koa cup"):
            return f"KOA Cup - {phase}"
        return phase

    fragment = MILAN_X_FRAGMENTS.get(raw_name)
    return fragment

