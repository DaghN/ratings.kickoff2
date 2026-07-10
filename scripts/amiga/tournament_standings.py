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
from scripts.amiga.scoring_contract import (
    DRAW_POINTS,
    LOSS_POINTS,
    WIN_POINTS,
    ScoringContext,
    StageScoringContract,
    default_scoring_context,
    load_scoring_context_for_tournament,
)
from scripts.amiga.match_extensions import parse_standings_winner, resolve_game_extension_winner
from scripts.amiga.tournament_phases import (
    PhaseScope,
    ScopeType,
    is_knockout_phase,
    is_league_scope,
    knockout_pair_scope_key,
    parse_phase,
)

log = logging.getLogger(__name__)

GAME_SELECT = """
    SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id,
           g.goals_a, g.goals_b, g.phase, g.extra,
           g.goals_et_a, g.goals_et_b, g.pens_a, g.pens_b,
           g.source_scores_id,
           g.fixture_id,
           f.phase_label AS fixture_phase_label,
           s.id AS stage_id,
           s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
    FROM amiga_games g
    LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
    LEFT JOIN tournament_stages s ON s.id = f.stage_id
    WHERE g.tournament_id IS NOT NULL
    ORDER BY g.tournament_id ASC, g.source_scores_id ASC, g.id ASC
"""


@dataclass
class PlayerStanding:
    win_points: int = WIN_POINTS
    draw_points: int = DRAW_POINTS
    loss_points: int = LOSS_POINTS
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    goals_against: int = 0

    @property
    def points(self) -> int:
        return self.wins * self.win_points + self.draws * self.draw_points

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


def regulation_outcome(
    goals_a: int,
    goals_b: int,
    *,
    contract: StageScoringContract,
) -> tuple[int, int, int]:
    """Return (points_a, points_b, outcome) where outcome: 1=a win, 0=draw, -1=b win."""
    win_pts = contract.win_points
    draw_pts = contract.draw_points
    loss_pts = contract.loss_points
    if goals_a > goals_b:
        return win_pts, loss_pts, 1
    if goals_a < goals_b:
        return loss_pts, win_pts, -1
    return draw_pts, draw_pts, 0


def _standing_for_table(
    table: dict[int, PlayerStanding],
    player_id: int,
    contract: StageScoringContract,
) -> PlayerStanding:
    st = table.get(player_id)
    if st is None:
        st = PlayerStanding(
            win_points=contract.win_points,
            draw_points=contract.draw_points,
            loss_points=contract.loss_points,
        )
        table[player_id] = st
    return st


def _apply_game(
    standings: dict[tuple[ScopeType, str], dict[int, PlayerStanding]],
    scope: PhaseScope,
    player_a_id: int,
    player_b_id: int,
    goals_a: int,
    goals_b: int,
    *,
    contract: StageScoringContract,
    league_only: bool = True,
) -> None:
    if league_only and not is_league_scope(scope):
        return
    key = (scope.scope_type, scope.scope_key)
    table = standings.setdefault(key, {})
    pa = _standing_for_table(table, player_a_id, contract)
    pb = _standing_for_table(table, player_b_id, contract)

    pts_a, pts_b, outcome = regulation_outcome(goals_a, goals_b, contract=contract)

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


def _league_metric(st: PlayerStanding, step: str) -> int | None:
    if step == "points":
        return st.points
    if step in ("goal_difference", "aggregate_goal_difference"):
        return st.goal_difference
    if step == "goals_for":
        return st.goals_for
    if step == "games_played":
        return st.games
    if step == "head_to_head":
        return None
    if step in ("extra_time", "penalty_shootout", "golden_goal"):
        return None
    return None


def _league_sort_key(st: PlayerStanding, contract: StageScoringContract) -> tuple:
    parts: list[int] = []
    for step in contract.steps:
        metric = _league_metric(st, step)
        if metric is None:
            continue
        parts.append(-metric)
    if not parts:
        return (-st.points, -st.goal_difference, -st.goals_for, -st.games)
    return tuple(parts)


def _league_position_tie_key(st: PlayerStanding, contract: StageScoringContract) -> tuple:
    parts: list[int] = []
    for step in contract.steps:
        if step == "games_played":
            break
        metric = _league_metric(st, step)
        if metric is None:
            continue
        parts.append(-metric)
    if not parts:
        return (-st.points, -st.goal_difference, -st.goals_for)
    return tuple(parts)


def _sort_key(item: tuple[int, PlayerStanding]) -> tuple:
    _pid, st = item
    return (-st.points, -st.goal_difference, -st.goals_for, -st.games)


def _knockout_positions(
    table: dict[int, PlayerStanding],
    games: list[dict[str, Any]] | None,
    contract: StageScoringContract,
) -> list[tuple[int, PlayerStanding, int]]:
    """Two-player tie: resolve via contract step chain."""
    if len(table) != 2:
        return _assign_positions(table, contract)
    (id1, s1), (id2, s2) = sorted(table.items(), key=lambda x: x[0])

    for step in contract.steps:
        if step == "aggregate_goal_difference":
            if s1.goal_difference > s2.goal_difference:
                return [(id1, s1, 1), (id2, s2, 2)]
            if s2.goal_difference > s1.goal_difference:
                return [(id2, s2, 1), (id1, s1, 2)]
        elif step == "goals_for":
            if s1.goals_for > s2.goals_for:
                return [(id1, s1, 1), (id2, s2, 2)]
            if s2.goals_for > s1.goals_for:
                return [(id2, s2, 1), (id1, s1, 2)]
        elif step in ("extra_time", "penalty_shootout", "golden_goal"):
            if games:
                for g in games:
                    wid = resolve_game_extension_winner(
                        g,
                        step,
                        int(g["player_a_id"]),
                        int(g["player_b_id"]),
                    )
                    if wid is not None:
                        loser_id = id2 if wid == id1 else id1
                        return [(wid, table[wid], 1), (loser_id, table[loser_id], 2)]
        elif step == "points":
            if s1.points > s2.points:
                return [(id1, s1, 1), (id2, s2, 2)]
            if s2.points > s1.points:
                return [(id2, s2, 1), (id1, s1, 2)]

    return _assign_positions(table, contract)


def _assign_positions(
    table: dict[int, PlayerStanding],
    contract: StageScoringContract,
) -> list[tuple[int, PlayerStanding, int]]:
    ranked = sorted(table.items(), key=lambda item: _league_sort_key(item[1], contract))
    out: list[tuple[int, PlayerStanding, int]] = []
    pos = 0
    prev_key: tuple | None = None
    for rank_idx, (pid, st) in enumerate(ranked, start=1):
        key = _league_position_tie_key(st, contract)
        if key != prev_key:
            pos = rank_idx
            prev_key = key
        out.append((pid, st, pos))
    return out


def _fixture_label(g: dict[str, Any]) -> str:
    label = g.get("fixture_phase_label") or g.get("stage_name") or g.get("stage_key") or "Fixture"
    return str(label).strip() or "Fixture"


def _game_stage_id(g: dict[str, Any]) -> int | None:
    raw = g.get("stage_id")
    if raw is None:
        return None
    try:
        sid = int(raw)
    except (TypeError, ValueError):
        return None
    return sid if sid > 0 else None


def _record_scope_stage_id(
    scope_stage_ids: dict[tuple[ScopeType, str], int | None],
    scope_key: tuple[ScopeType, str],
    stage_id: int | None,
) -> None:
    if stage_id is None:
        return
    existing = scope_stage_ids.get(scope_key)
    if existing is None:
        scope_stage_ids[scope_key] = stage_id
        return
    if existing != stage_id:
        log.warning(
            "compute_tournament_standings: mixed stage_id for scope %s (had %s, got %s)",
            scope_key,
            existing,
            stage_id,
        )


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

    if stage_type == "round_robin":
        if stage_key == "" or stage_key.lower() == "overall":
            return PhaseScope(ScopeType.LEAGUE, ""), False
        return PhaseScope(ScopeType.LEAGUE, label), False
    if stage_type == "knockout":
        pair_key = knockout_pair_scope_key(label, player_a_id, player_b_id)
        return PhaseScope(ScopeType.KNOCKOUT, pair_key), True
    # Legacy stage types (pre-migration 023) — keep until all DBs migrated.
    if stage_type == "league":
        if stage_key == "" or stage_key.lower() == "overall":
            return PhaseScope(ScopeType.LEAGUE, ""), False
        return PhaseScope(ScopeType.LEAGUE, label), False
    if stage_type == "group":
        return PhaseScope(ScopeType.LEAGUE, label), False
    if stage_type == "placement":
        pair_key = knockout_pair_scope_key(label, player_a_id, player_b_id)
        return PhaseScope(ScopeType.KNOCKOUT, pair_key), True
    if stage_type == "other":
        return PhaseScope(ScopeType.LEAGUE, label), False
    return None


def compute_tournament_standings(
    games: list[dict[str, Any]],
    *,
    scoring_context: ScoringContext | None = None,
) -> list[dict[str, Any]]:
    """Build standings rows for one tournament's games (already ordered)."""
    tournament_id = int(games[0]["tournament_id"]) if games else 0
    context = scoring_context or default_scoring_context(tournament_id)

    scopes: dict[tuple[ScopeType, str], dict[int, PlayerStanding]] = {}
    scope_contracts: dict[tuple[ScopeType, str], StageScoringContract] = {}
    knockout_scopes: dict[tuple[ScopeType, str], dict[int, PlayerStanding]] = {}
    knockout_games: dict[tuple[ScopeType, str], list[dict[str, Any]]] = defaultdict(list)
    scope_stage_ids: dict[tuple[ScopeType, str], int | None] = {}
    has_null_phase = False
    has_structured = False

    def _record_scope_contract(
        scope_key: tuple[ScopeType, str],
        contract: StageScoringContract,
    ) -> None:
        existing = scope_contracts.get(scope_key)
        if existing is None:
            scope_contracts[scope_key] = contract
            return
        if (
            existing.primitive != contract.primitive
            or existing.steps != contract.steps
            or existing.win_points != contract.win_points
        ):
            log.warning(
                "compute_tournament_standings: mixed scoring contracts for scope %s "
                "in tournament_id=%s",
                scope_key,
                tournament_id,
            )

    for g in games:
        phase = g.get("phase")
        player_a_id = int(g["player_a_id"])
        player_b_id = int(g["player_b_id"])
        goals_a = int(g["goals_a"])
        goals_b = int(g["goals_b"])

        fixture_scope = _fixture_scope(g, player_a_id, player_b_id)
        if fixture_scope is not None:
            scope, is_elimination = fixture_scope
            contract = context.contract_for_game(g, is_elimination=is_elimination)
            scope_key = (scope.scope_type, scope.scope_key)
            _record_scope_contract(scope_key, contract)
            _record_scope_stage_id(scope_stage_ids, scope_key, _game_stage_id(g))
            has_structured = True
            if is_elimination:
                knockout_games[scope_key].append(g)
                _apply_game(
                    knockout_scopes,
                    scope,
                    player_a_id,
                    player_b_id,
                    goals_a,
                    goals_b,
                    contract=contract,
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
                    contract=contract,
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
            contract = context.default_knockout
            _record_scope_contract(scope_key, contract)
            knockout_games[scope_key].append(g)
            _apply_game(
                knockout_scopes,
                scope,
                player_a_id,
                player_b_id,
                goals_a,
                goals_b,
                contract=contract,
                league_only=False,
            )
            continue

        scope = parse_phase(phase)
        scope_key = (scope.scope_type, scope.scope_key)
        contract = context.default_league
        _record_scope_contract(scope_key, contract)
        _apply_game(
            scopes,
            scope,
            player_a_id,
            player_b_id,
            goals_a,
            goals_b,
            contract=contract,
        )

    # Marathon round-robins: all phase NULL → one implicit league table only.
    if has_null_phase and not has_structured:
        scopes = {
            k: v for k, v in scopes.items() if k == (ScopeType.LEAGUE, "")
        }
        scope_contracts[(ScopeType.LEAGUE, "")] = context.default_league
    elif has_null_phase and has_structured:
        # Mixed: synthesize league + '' aggregating all labeled league-scope games.
        league_aggregate: dict[int, PlayerStanding] = {}
        for (stype, skey), table in scopes.items():
            if stype == ScopeType.LEAGUE and skey == "":
                continue
            if stype != ScopeType.LEAGUE:
                continue
            for pid, st in table.items():
                agg = league_aggregate.get(pid)
                if agg is None:
                    agg = PlayerStanding(
                        win_points=st.win_points,
                        draw_points=st.draw_points,
                        loss_points=st.loss_points,
                    )
                    league_aggregate[pid] = agg
                agg.games += st.games
                agg.wins += st.wins
                agg.draws += st.draws
                agg.losses += st.losses
                agg.goals_for += st.goals_for
                agg.goals_against += st.goals_against
        if league_aggregate:
            scopes[(ScopeType.LEAGUE, "")] = league_aggregate
            scope_contracts[(ScopeType.LEAGUE, "")] = context.default_league
            scope_stage_ids[(ScopeType.LEAGUE, "")] = None

    for (stype, skey), table in knockout_scopes.items():
        scopes[(stype, skey)] = table

    rows: list[dict[str, Any]] = []
    for (stype, skey), table in sorted(scopes.items(), key=lambda x: (x[0][0].value, x[0][1])):
        if not table:
            continue
        contract = scope_contracts.get(
            (stype, skey),
            context.default_knockout if stype == ScopeType.KNOCKOUT else context.default_league,
        )
        if stype == ScopeType.KNOCKOUT:
            ranked = _knockout_positions(table, knockout_games.get((stype, skey), []), contract)
        else:
            ranked = _assign_positions(table, contract)
        for pid, st, pos in ranked:
            rows.append(
                {
                    "tournament_id": tournament_id,
                    "player_id": pid,
                    "scope_type": stype.value,
                    "scope_key": skey,
                    "stage_id": scope_stage_ids.get((stype, skey)),
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
           g.goals_a, g.goals_b, g.phase, g.extra,
           g.goals_et_a, g.goals_et_b, g.pens_a, g.pens_b,
           g.source_scores_id,
           g.fixture_id,
           f.phase_label AS fixture_phase_label,
           s.id AS stage_id,
           s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
    FROM amiga_games g
    LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
    LEFT JOIN tournament_stages s ON s.id = f.stage_id
    WHERE g.tournament_id = %s
    ORDER BY g.source_scores_id ASC, g.id ASC
"""

_STANDINGS_INSERT_SQL = """
    INSERT INTO amiga_tournament_standings (
        tournament_id, player_id, scope_type, scope_key, stage_id,
        position, games, wins, draws, losses,
        goals_for, goals_against, points
    ) VALUES (
        %(tournament_id)s, %(player_id)s, %(scope_type)s, %(scope_key)s, %(stage_id)s,
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
    scoring_context = load_scoring_context_for_tournament(conn, tournament_id)
    rows = compute_tournament_standings(games, scoring_context=scoring_context)
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
        scoring_context = load_scoring_context_for_tournament(conn, tid)
        all_rows.extend(
            compute_tournament_standings(
                by_tournament[tid],
                scoring_context=scoring_context,
            )
        )

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
