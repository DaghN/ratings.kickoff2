"""Match video rows to amiga_games.id by tournament + players + score."""

from __future__ import annotations

import re
from dataclasses import dataclass

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config


@dataclass(frozen=True)
class GameRow:
    game_id: int
    player_a_id: int
    player_b_id: int
    goals_a: int
    goals_b: int
    phase: str


def _parse_score(raw: str) -> tuple[int, int] | None:
    if not raw:
        return None
    m = re.match(r"^\s*(\d+)\s*-\s*(\d+)\s*$", raw.replace(" ", ""))
    if not m:
        m = re.match(r"^\s*(\d+)\s*-\s*(\d+)\s*$", raw)
    if not m:
        return None
    return int(m.group(1)), int(m.group(2))


def _phase_hint(stage: str) -> str | None:
    s = (stage or "").lower()
    if s == "final":
        return "final"
    if s == "semi":
        return "semi"
    if s in ("quarter", "qf"):
        return "quarter"
    if s == "bronze":
        return "3rd place"
    if s == "silver":
        return "final"
    if s == "shame":
        return "shame"
    if s == "league":
        return None
    return None


def _phase_matches(db_phase: str, hint: str | None) -> bool:
    if hint is None:
        return True
    p = (db_phase or "").lower()
    if not p:
        return True
    if hint == "final":
        return "final" in p
    if hint == "semi":
        return "semi" in p
    if hint == "quarter":
        return "quarter" in p
    if hint == "3rd place":
        return "3rd place" in p
    if hint == "shame":
        return "shame" in p
    return hint in p


def load_tournament_games(tournament_id: int) -> list[GameRow]:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, player_a_id, player_b_id, goals_a, goals_b, phase "
                "FROM amiga_games WHERE tournament_id = %s",
                (tournament_id,),
            )
            rows = cur.fetchall()
    finally:
        conn.close()
    return [
        GameRow(
            int(r["id"]),
            int(r["player_a_id"]),
            int(r["player_b_id"]),
            int(r["goals_a"]),
            int(r["goals_b"]),
            str(r["phase"] or ""),
        )
        for r in rows
    ]


def match_game(
    games: list[GameRow],
    *,
    player_a_id: int | None,
    player_b_id: int | None,
    score: str,
    stage: str = "",
    leg: int | None = None,
) -> tuple[int | None, str | None]:
    if not player_a_id or not player_b_id:
        return None, "missing player ids"
    parsed = _parse_score(score)
    if parsed is None:
        return None, "unparsed score"
    ga_video, gb_video = parsed
    phase = _phase_hint(stage)
    exact: list[GameRow] = []
    swapped: list[GameRow] = []
    for g in games:
        if {g.player_a_id, g.player_b_id} != {player_a_id, player_b_id}:
            continue
        if g.player_a_id == player_a_id and g.player_b_id == player_b_id:
            if (g.goals_a, g.goals_b) == (ga_video, gb_video):
                exact.append(g)
        elif g.player_a_id == player_b_id and g.player_b_id == player_a_id:
            if g.goals_a == gb_video and g.goals_b == ga_video:
                swapped.append(g)
    candidates = exact if exact else swapped
    if phase:
        phase_hits = [c for c in candidates if _phase_matches(c.phase, phase)]
        if len(phase_hits) == 1:
            return phase_hits[0].game_id, None
        if len(phase_hits) > 1:
            candidates = phase_hits
    if len(candidates) > 1 and leg in (1, 2):
        ordered = sorted(candidates, key=lambda c: c.game_id)
        idx = leg - 1
        if idx < len(ordered):
            return ordered[idx].game_id, None
    if len(candidates) == 1:
        return candidates[0].game_id, None
    if len(candidates) > 1:
        return None, f"ambiguous ({len(candidates)} games)"
    return None, "no game match"