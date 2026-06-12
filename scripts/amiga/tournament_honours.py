"""World Cup podium finish derivation from tournament standings (Amiga honours v2)."""

from __future__ import annotations

import re
from typing import Any

_WORLD_CUP_NAME_RE = re.compile(r"^World Cup\s+\S", re.IGNORECASE)


def is_world_cup_tournament(name: str) -> bool:
    """Match PHP ``amiga_tournament_is_world_cup()`` — ``^World Cup\\s+\\S``."""
    return bool(_WORLD_CUP_NAME_RE.match(str(name or "").strip()))


def knockout_scope_label(scope_key: str) -> str:
    """Phase label from ``{label}|{player_a}-{player_b}`` scope keys."""
    return str(scope_key or "").split("|", 1)[0].strip()


def _normalize_knockout_label(label: str) -> str:
    text = re.sub(r"\s+", " ", str(label or "").strip().lower())
    if re.match(r"^(?:quarter|semi)\s+final$", text):
        return text + "s" if not text.endswith("s") else text
    return text


def _is_main_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) == "final"


def _is_third_place_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) == "3rd place final"


def _is_semi_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) in {"semi final", "semi finals"}


def _has_third_place_final_scope(standing_rows: list[dict[str, Any]]) -> bool:
    for row in standing_rows:
        if str(row.get("scope_type") or "") != "knockout":
            continue
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        if _is_third_place_final_label(label):
            return True
    return False


def compute_wc_podium_finish_from_standings(
    standing_rows: list[dict[str, Any]],
) -> dict[int, int]:
    """
    Derive WC podium ``event_finish_position`` (1/2/3) from knockout standings.

    Gold/silver from main ``Final``; bronze from ``3rd Place Final`` winner **or**
    both semi-final losers when no 3rd-place match and Final is complete (Olympic-style).
    Never assigns finish from league rank alone.
    """
    ko_rows = [
        r
        for r in standing_rows
        if str(r.get("scope_type") or "") == "knockout"
    ]

    gold_id: int | None = None
    silver_id: int | None = None
    bronze_ids: set[int] = set()

    for row in ko_rows:
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        player_id = int(row["player_id"])
        position = int(row["position"])

        if _is_main_final_label(label):
            if position == 1:
                gold_id = player_id
            elif position == 2:
                silver_id = player_id
        elif _is_third_place_final_label(label) and position == 1:
            bronze_ids.add(player_id)

    if (
        not _has_third_place_final_scope(standing_rows)
        and gold_id is not None
        and silver_id is not None
    ):
        for row in ko_rows:
            label = knockout_scope_label(str(row.get("scope_key") or ""))
            if not _is_semi_final_label(label):
                continue
            if int(row["position"]) == 2:
                bronze_ids.add(int(row["player_id"]))

    finish: dict[int, int] = {}
    if gold_id is not None:
        finish[gold_id] = 1
    if silver_id is not None:
        finish[silver_id] = 2
    for player_id in bronze_ids:
        finish[player_id] = 3
    return finish
