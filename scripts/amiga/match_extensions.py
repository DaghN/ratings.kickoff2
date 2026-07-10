"""Structured L3 match extensions (SC-11) — ET / penalties ground, extra = witness."""

from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Any

_PEN_PATTERNS = (
    re.compile(r"\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)\s*(?:p\.?k\.?|pen)", re.I),
    re.compile(r"\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)(?:pen)?", re.I),
    re.compile(r"\((\d+)\s*-\s*(\d+)\s*pen\.?\)", re.I),
    re.compile(r"(\d+)\s*-\s*(\d+)\s*pen", re.I),
)
_ET_PATTERNS = (
    re.compile(r"\((\d+)\s*-\s*(\d+)\)\s*(?:a\.)?e\.?\s*t\.?", re.I),
    re.compile(r"(\d+)\s*-\s*(\d+)\s*(?:a\.)?e\.?\s*t\.?", re.I),
)


def parse_standings_winner(
    goals_a: int,
    goals_b: int,
    extra: str | None,
    player_a_id: int,
    player_b_id: int,
) -> int | None:
    """Resolve match winner for knockouts (regulation or penalties in witness text)."""
    if goals_a > goals_b:
        return player_a_id
    if goals_b > goals_a:
        return player_b_id
    if not extra or not str(extra).strip():
        return None
    text = str(extra).strip().lower()
    pen_patterns = [
        r"\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)\s*(?:p\.?k\.?|pen)",
        r"\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)",
        r"(\d+)\s*-\s*(\d+)\s*pen",
    ]
    for pat in pen_patterns:
        m = re.search(pat, text)
        if m:
            groups = m.groups()
            if len(groups) == 4:
                pen_a, pen_b = int(groups[2]), int(groups[3])
            else:
                pen_a, pen_b = int(groups[0]), int(groups[1])
            if pen_a > pen_b:
                return player_a_id
            if pen_b > pen_a:
                return player_b_id
    return None


@dataclass(frozen=True)
class StructuredMatchExtension:
    goals_et_a: int | None = None
    goals_et_b: int | None = None
    pens_a: int | None = None
    pens_b: int | None = None


def extract_structured_from_extra(extra: str | None) -> StructuredMatchExtension | None:
    """Parse witness extra text into structured ET / pens (player-A oriented)."""
    if extra is None:
        return None
    text = str(extra).strip()
    if not text:
        return None

    pens_a: int | None = None
    pens_b: int | None = None
    goals_et_a: int | None = None
    goals_et_b: int | None = None

    for pat in _PEN_PATTERNS:
        m = pat.search(text)
        if not m:
            continue
        groups = m.groups()
        if len(groups) == 4:
            pens_a, pens_b = int(groups[2]), int(groups[3])
        else:
            pens_a, pens_b = int(groups[0]), int(groups[1])
        break

    if pens_a is None:
        for pat in _ET_PATTERNS:
            m = pat.search(text)
            if not m:
                continue
            goals_et_a, goals_et_b = int(m.group(1)), int(m.group(2))
            break

    if pens_a is None and goals_et_a is None:
        return None

    return StructuredMatchExtension(
        goals_et_a=goals_et_a,
        goals_et_b=goals_et_b,
        pens_a=pens_a,
        pens_b=pens_b,
    )


def game_extension_fields(game: dict[str, Any]) -> StructuredMatchExtension:
    def _col(key: str) -> int | None:
        if key not in game or game[key] is None:
            return None
        try:
            val = int(game[key])
        except (TypeError, ValueError):
            return None
        return val if val >= 0 else None

    return StructuredMatchExtension(
        goals_et_a=_col("goals_et_a"),
        goals_et_b=_col("goals_et_b"),
        pens_a=_col("pens_a"),
        pens_b=_col("pens_b"),
    )


def _winner_from_pair(
    score_a: int,
    score_b: int,
    player_a_id: int,
    player_b_id: int,
) -> int | None:
    if score_a > score_b:
        return player_a_id
    if score_b > score_a:
        return player_b_id
    return None


def resolve_game_extension_winner(
    game: dict[str, Any],
    step: str,
    player_a_id: int,
    player_b_id: int,
) -> int | None:
    """Resolve knockout extension winner for one leg (structured first, then witness text)."""
    ext = game_extension_fields(game)

    if step == "penalty_shootout":
        if ext.pens_a is not None and ext.pens_b is not None:
            return _winner_from_pair(ext.pens_a, ext.pens_b, player_a_id, player_b_id)
    elif step == "extra_time":
        if ext.goals_et_a is not None and ext.goals_et_b is not None:
            return _winner_from_pair(ext.goals_et_a, ext.goals_et_b, player_a_id, player_b_id)
    elif step == "golden_goal":
        if ext.goals_et_a is not None and ext.goals_et_b is not None:
            won = _winner_from_pair(ext.goals_et_a, ext.goals_et_b, player_a_id, player_b_id)
            if won is not None:
                return won

    extra = game.get("extra")
    if extra is None or not str(extra).strip():
        return None
    return parse_standings_winner(
        int(game.get("goals_a", 0)),
        int(game.get("goals_b", 0)),
        str(extra),
        player_a_id,
        player_b_id,
    )