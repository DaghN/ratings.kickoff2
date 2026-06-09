"""Derive tournament standings from ground-truth games."""

from __future__ import annotations

import logging
import re
from collections import defaultdict
from dataclasses import dataclass
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_phases import (
    PhaseScope,
    ScopeType,
    is_knockout_phase,
    is_league_scope,
    knockout_pair_scope_key,
    parse_phase,
)

log = logging.getLogger(__name__)

WIN_POINTS = 3
DRAW_POINTS = 1
LOSS_POINTS = 0

GAME_SELECT = """
    SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id,
           g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id,
           g.fixture_id,
           f.phase_label AS fixture_phase_label,
           s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
    FROM amiga_games g
    LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
    LEFT JOIN tournament_stages s ON s.id = f.stage_id
    WHERE g.tournament_id IS NOT NULL
    ORDER BY g.tournament_id ASC, g.source_scores_id ASC, g.id ASC
"""


@dataclass
class PlayerStanding:
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    goals_against: int = 0

    @property
    def points(self) -> int:
        return self.wins * WIN_POINTS + self.draws * DRAW_POINTS

    @property
    def goal_difference(self) -> int:
        return self.goals_for - self.goals_against


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing standings: expected ko2amiga_db, got {cfg.database!r}")
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def regulation_outcome(goals_a: int, goals_b: int) -> tuple[int, int, int]:
    """Return (points_a, points_b, outcome) where outcome: 1=a win, 0=draw, -1=b win."""
    if goals_a > goals_b:
        return WIN_POINTS, LOSS_POINTS, 1
    if goals_a < goals_b:
        return LOSS_POINTS, WIN_POINTS, -1
    return DRAW_POINTS, DRAW_POINTS, 0


def parse_standings_winner(
    goals_a: int,
    goals_b: int,
    extra: str | None,
    player_a_id: int,
    player_b_id: int,
) -> int | None:
    """Resolve match winner for knockouts (regulation or Extra). None = unresolved draw."""
    if goals_a > goals_b:
        return player_a_id
    if goals_b > goals_a:
        return player_b_id
    if not extra or not str(extra).strip():
        return None
    text = str(extra).strip().lower()
    # Penalties: ``(4-4) 5-3 p.k.``, ``(5-4 pen.)``, ``(0-0) 7-6pen``
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
    # Extra time with different score: ``7-7 e.t.`` is still a draw — unresolved.
    return None


def _apply_game(
    standings: dict[tuple[ScopeType, str], dict[int, PlayerStanding]],
    scope: PhaseScope,
    player_a_id: int,
    player_b_id: int,
    goals_a: int,
    goals_b: int,
    *,
    league_only: bool = True,
) -> None:
    if league_only and not is_league_scope(scope):
        return
    key = (scope.scope_type, scope.scope_key)
    table = standings.setdefault(key, {})
    pa = table.setdefault(player_a_id, PlayerStanding())
    pb = table.setdefault(player_b_id, PlayerStanding())

    pts_a, pts_b, outcome = regulation_outcome(goals_a, goals_b)

    pa.games += 1
    pb.games += 1
    pa.goals_for += goals_a
    pa.goals_against += goals_b
    pb.goals_for += goals_b
    pb.goals_against += goals_a

    if outcome == 1:
        pa.wins += 1
        pb.losses += 1
    elif outcome == -1:
        pa.losses += 1
        pb.wins += 1
    else:
        pa.draws += 1
        pb.draws += 1

    _ = pts_a, pts_b  # points derived from W/D/L counts


def _sort_key(item: tuple[int, PlayerStanding]) -> tuple:
    _pid, st = item
    return (-st.points, -st.goal_difference, -st.goals_for, -st.games)


def _knockout_positions(
    table: dict[int, PlayerStanding],
    games: list[dict[str, Any]] | None = None,
) -> list[tuple[int, PlayerStanding, int]]:
    """Two-player tie: aggregate GD/GF, then Extra penalties when still tied."""
    if len(table) != 2:
        return _assign_positions(table)
    (id1, s1), (id2, s2) = sorted(table.items(), key=lambda x: x[0])
    if s1.goal_difference > s2.goal_difference:
        winner, loser = (id1, s1), (id2, s2)
    elif s2.goal_difference > s1.goal_difference:
        winner, loser = (id2, s2), (id1, s1)
    elif s1.goals_for > s2.goals_for:
        winner, loser = (id1, s1), (id2, s2)
    elif s2.goals_for > s1.goals_for:
        winner, loser = (id2, s2), (id1, s1)
    else:
        if games:
            for g in games:
                extra = g.get("extra")
                if not extra or not str(extra).strip():
                    continue
                wid = parse_standings_winner(
                    int(g["goals_a"]),
                    int(g["goals_b"]),
                    str(extra),
                    int(g["player_a_id"]),
                    int(g["player_b_id"]),
                )
                if wid is not None:
                    loser_id = id2 if wid == id1 else id1
                    return [(wid, table[wid], 1), (loser_id, table[loser_id], 2)]
        return _assign_positions(table)
    wid, ws = winner
    lid, ls = loser
    return [(wid, ws, 1), (lid, ls, 2)]


def _assign_positions(table: dict[int, PlayerStanding]) -> list[tuple[int, PlayerStanding, int]]:
    ranked = sorted(table.items(), key=_sort_key)
    out: list[tuple[int, PlayerStanding, int]] = []
    pos = 0
    prev_key: tuple | None = None
    for rank_idx, (pid, st) in enumerate(ranked, start=1):
        key = (-st.points, -st.goal_difference, -st.goals_for)
        if key != prev_key:
            pos = rank_idx
            prev_key = key
        out.append((pid, st, pos))
    return out


def _fixture_label(g: dict[str, Any]) -> str:
    label = g.get("fixture_phase_label") or g.get("stage_name") or g.get("stage_key") or "Fixture"
    return str(label).strip() or "Fixture"


def _fixture_scope(
    g: dict[str, Any],
    player_a_id: int,
    player_b_id: int,
) -> tuple[PhaseScope, bool] | None:
    if g.get("fixture_id") is None:
        return None
    stage_type = str(g.get("stage_type") or "").strip().lower()
    stage_key = str(g.get("stage_key") or "").strip()
    label = _fixture_label(g)

    if stage_type == "league":
        if stage_key == "" or stage_key.lower() == "overall":
            return PhaseScope(ScopeType.OVERALL, ""), False
        return PhaseScope(ScopeType.GROUP, label), False
    if stage_type == "group":
        return PhaseScope(ScopeType.GROUP, label), False
    if stage_type in {"knockout", "placement"}:
        pair_key = knockout_pair_scope_key(label, player_a_id, player_b_id)
        return PhaseScope(ScopeType.KNOCKOUT, pair_key), True
    if stage_type == "other":
        return PhaseScope(ScopeType.GROUP, label), False
    return None


def compute_tournament_standings(
    games: list[dict[str, Any]],
) -> list[dict[str, Any]]:
    """Build standings rows for one tournament's games (already ordered)."""
    scopes: dict[tuple[ScopeType, str], dict[int, PlayerStanding]] = {}
    knockout_scopes: dict[tuple[ScopeType, str], dict[int, PlayerStanding]] = {}
    knockout_games: dict[tuple[ScopeType, str], list[dict[str, Any]]] = defaultdict(list)
    has_null_phase = False
    has_structured = False

    for g in games:
        phase = g.get("phase")
        player_a_id = int(g["player_a_id"])
        player_b_id = int(g["player_b_id"])
        goals_a = int(g["goals_a"])
        goals_b = int(g["goals_b"])

        fixture_scope = _fixture_scope(g, player_a_id, player_b_id)
        if fixture_scope is not None:
            scope, is_elimination = fixture_scope
            has_structured = True
            if is_elimination:
                scope_key = (scope.scope_type, scope.scope_key)
                knockout_games[scope_key].append(g)
                _apply_game(
                    knockout_scopes,
                    scope,
                    player_a_id,
                    player_b_id,
                    goals_a,
                    goals_b,
                    league_only=False,
                )
            else:
                _apply_game(
                    scopes,
                    scope,
                    player_a_id,
                    player_b_id,
                    goals_a,
                    goals_b,
                )
            continue

        if not phase:
            has_null_phase = True
        else:
            has_structured = True

        if is_knockout_phase(phase):
            pair_key = knockout_pair_scope_key(str(phase), player_a_id, player_b_id)
            scope = PhaseScope(ScopeType.KNOCKOUT, pair_key)
            scope_key = (scope.scope_type, scope.scope_key)
            knockout_games[scope_key].append(g)
            _apply_game(
                knockout_scopes,
                scope,
                player_a_id,
                player_b_id,
                goals_a,
                goals_b,
                league_only=False,
            )
            continue

        scope = parse_phase(phase)
        _apply_game(
            scopes,
            scope,
            player_a_id,
            player_b_id,
            goals_a,
            goals_b,
        )

    # Marathon round-robins: all phase NULL → one overall table only.
    if has_null_phase and not has_structured:
        scopes = {
            k: v for k, v in scopes.items() if k == (ScopeType.OVERALL, "")
        }
    elif has_null_phase and has_structured:
        # Mixed: also keep overall aggregating all league-scope games.
        overall: dict[int, PlayerStanding] = defaultdict(PlayerStanding)
        for (stype, skey), table in scopes.items():
            if stype == ScopeType.OVERALL and skey == "":
                continue
            if stype not in (ScopeType.OVERALL, ScopeType.GROUP):
                continue
            for pid, st in table.items():
                o = overall[pid]
                o.games += st.games
                o.wins += st.wins
                o.draws += st.draws
                o.losses += st.losses
                o.goals_for += st.goals_for
                o.goals_against += st.goals_against
        if overall:
            scopes[(ScopeType.OVERALL, "")] = dict(overall)

    for (stype, skey), table in knockout_scopes.items():
        scopes[(stype, skey)] = table

    tournament_id = int(games[0]["tournament_id"]) if games else 0
    rows: list[dict[str, Any]] = []
    for (stype, skey), table in sorted(scopes.items(), key=lambda x: (x[0][0].value, x[0][1])):
        if not table:
            continue
        if stype == ScopeType.KNOCKOUT:
            ranked = _knockout_positions(table, knockout_games.get((stype, skey), []))
        else:
            ranked = _assign_positions(table)
        for pid, st, pos in ranked:
            rows.append(
                {
                    "tournament_id": tournament_id,
                    "player_id": pid,
                    "scope_type": stype.value,
                    "scope_key": skey,
                    "position": pos,
                    "games": st.games,
                    "wins": st.wins,
                    "draws": st.draws,
                    "losses": st.losses,
                    "goals_for": st.goals_for,
                    "goals_against": st.goals_against,
                    "points": st.points,
                }
            )
    return rows


GAME_SELECT_FOR_TOURNAMENT = """
    SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id,
           g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id,
           g.fixture_id,
           f.phase_label AS fixture_phase_label,
           s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
    FROM amiga_games g
    LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
    LEFT JOIN tournament_stages s ON s.id = f.stage_id
    WHERE g.tournament_id = %s
    ORDER BY g.source_scores_id ASC, g.id ASC
"""

_STANDINGS_INSERT_SQL = """
    INSERT INTO amiga_tournament_standings (
        tournament_id, player_id, scope_type, scope_key,
        position, games, wins, draws, losses,
        goals_for, goals_against, points
    ) VALUES (
        %(tournament_id)s, %(player_id)s, %(scope_type)s, %(scope_key)s,
        %(position)s, %(games)s, %(wins)s, %(draws)s, %(losses)s,
        %(goals_for)s, %(goals_against)s, %(points)s
    )
"""


def rebuild_standings_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> int:
    """Replace derived standings for one tournament from ground-truth games."""
    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()
    rows = compute_tournament_standings(games)
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_tournament_standings WHERE tournament_id = %s",
            (tournament_id,),
        )
        if rows:
            cur.executemany(_STANDINGS_INSERT_SQL, rows)
    conn.commit()
    log.info(
        "rebuild_standings_for_tournament: tournament_id=%s rows=%s",
        tournament_id,
        len(rows),
    )
    return len(rows)


def clear_standings(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_tournament_standings")
        n = cur.fetchone()["n"]
    log.info("clear_standings: %s existing rows", n)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_tournament_standings")
    conn.commit()


def rebuild_all_standings(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
    batch_size: int = 1000,
) -> int:
    with conn.cursor() as cur:
        cur.execute(GAME_SELECT)
        all_games = cur.fetchall()

    by_tournament: dict[int, list[dict[str, Any]]] = defaultdict(list)
    for g in all_games:
        by_tournament[int(g["tournament_id"])].append(g)

    all_rows: list[dict[str, Any]] = []
    for tid in sorted(by_tournament):
        all_rows.extend(compute_tournament_standings(by_tournament[tid]))

    log.info(
        "rebuild_all_standings: %s tournaments, %s standing rows",
        len(by_tournament),
        len(all_rows),
    )
    if dry_run:
        return len(all_rows)

    with conn.cursor() as cur:
        for i in range(0, len(all_rows), batch_size):
            batch = all_rows[i : i + batch_size]
            cur.executemany(_STANDINGS_INSERT_SQL, batch)
    conn.commit()
    return len(all_rows)


def run_standings_rebuild(*, dry_run: bool = False) -> int:
    conn = _connect()
    try:
        clear_standings(conn, dry_run=dry_run)
        return rebuild_all_standings(conn, dry_run=dry_run)
    finally:
        conn.close()
