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


def _goals_et_from_post_et_total(
    post_a: int,
    post_b: int,
    reg_a: int,
    reg_b: int,
) -> tuple[int, int] | None:
    """Derive ET-period goals from post-ET witness total minus regulation."""
    et_a = post_a - reg_a
    et_b = post_b - reg_b
    if et_a < 0 or et_b < 0:
        return None
    return et_a, et_b


def extract_structured_from_extra(
    extra: str | None,
    *,
    goals_a: int | None = None,
    goals_b: int | None = None,
) -> StructuredMatchExtension | None:
    """Parse witness extra text into structured ET / pens (player-A oriented).

    ET witness (``e.t.`` / ``a.e.t.``): default = **score after extra time** (reg + ET).
    Subtract ``goals_a``/``goals_b`` regulation when present. If subtraction is
    negative, fall back to ET-period-only reading (rare).     Human overrides (ET and/or penalties — see verified register):
    ``match_extensions_verified_register.json``.
    """
    if extra is None:
        return None
    text = str(extra).strip()
    if not text:
        return None

    pens_a: int | None = None
    pens_b: int | None = None
    goals_et_a: int | None = None
    goals_et_b: int | None = None
    pen_match: re.Match[str] | None = None

    for pat in _PEN_PATTERNS:
        m = pat.search(text)
        if not m:
            continue
        pen_match = m
        groups = m.groups()
        if len(groups) == 4:
            pens_a, pens_b = int(groups[2]), int(groups[3])
        else:
            pens_a, pens_b = int(groups[0]), int(groups[1])
        break

    if pen_match is not None:
        groups = pen_match.groups()
        if len(groups) == 4 and goals_a is not None and goals_b is not None:
            post_a, post_b = int(groups[0]), int(groups[1])
            diff = _goals_et_from_post_et_total(
                post_a, post_b, int(goals_a), int(goals_b)
            )
            if diff is not None:
                goals_et_a, goals_et_b = diff
            else:
                # e.g. (0-0) before pens on a 1-1 draw — ET-period score, not full-time
                goals_et_a, goals_et_b = post_a, post_b

    if pens_a is None:
        for pat in _ET_PATTERNS:
            m = pat.search(text)
            if not m:
                continue
            post_a, post_b = int(m.group(1)), int(m.group(2))
            if goals_a is not None and goals_b is not None:
                diff = _goals_et_from_post_et_total(
                    post_a, post_b, int(goals_a), int(goals_b)
                )
                if diff is not None:
                    goals_et_a, goals_et_b = diff
                else:
                    goals_et_a, goals_et_b = post_a, post_b
            else:
                goals_et_a, goals_et_b = post_a, post_b
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


_EXTENSION_WITNESS_HINT = re.compile(
    r"(?:a\.)?e\.?\s*t\.?|pen(?:alties)?|p\.?\s*k\.?",
    re.I,
)


def witness_indicates_extension(extra: str | None) -> bool:
    """True when witness text suggests extra time and/or penalty shootout."""
    if extra is None:
        return False
    text = str(extra).strip()
    if not text:
        return False
    if extract_structured_from_extra(text) is not None:
        return True
    return bool(_EXTENSION_WITNESS_HINT.search(text))