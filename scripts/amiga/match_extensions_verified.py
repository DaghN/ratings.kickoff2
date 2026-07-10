"""Human-verified SC-11 match extensions (extra time and/or penalties).

Workflow and what counts as verified: see match_extensions_verified_register.json workflow section.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from scripts.amiga.match_extensions import witness_indicates_extension

_REGISTER_PATH = Path(__file__).resolve().parent / "match_extensions_verified_register.json"


def load_verified_register() -> dict[str, Any]:
    """Full register payload (empty games dict when file missing)."""
    if not _REGISTER_PATH.is_file():
        return {"schema_version": 1, "games": {}}
    return json.loads(_REGISTER_PATH.read_text(encoding="utf-8"))


def load_verified_game_ids() -> frozenset[int]:
    """Game ids present in the verified register — skip bulk backfill overwrite."""
    data = load_verified_register()
    games = data.get("games") or {}
    out: set[int] = set()
    for key in games:
        try:
            out.add(int(key))
        except (TypeError, ValueError):
            continue
    return frozenset(out)


def is_verified(game_id: int) -> bool:
    return int(game_id) in load_verified_game_ids()


def extension_review_status(extra: str | None, game_id: int) -> str:
    """unchecked | verified | not_applicable."""
    if not witness_indicates_extension(extra):
        return "not_applicable"
    if is_verified(game_id):
        return "verified"
    return "unchecked"
