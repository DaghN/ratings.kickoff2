"""Reset and chronological replay for online ladder (v1 Elo + v2 full playertable)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from .config import DbConfig
from .constants import (
    ALLOWED_DATABASES,
    PLAYERTABLE_LASTGAME_RESET,
    PLAYERTABLE_NULL_ON_RESET,
    PLAYERTABLE_SENTINELS_ON_RESET,
    PLAYERTABLE_ZERO_ON_RESET,
    RATEDRESULTS_CLEAR,
    START_RATING,
)
from .elo import compute_elo
from .finalize_counts import finalize_network_counts_from_rows
from .generalstats import rebuild_generalstats_if_present
from .period_activity import rebuild_period_activity_if_present
from .period_aggregates import rebuild_period_aggregates_if_present
from .milestones import rebuild_milestones_if_present
from .outcome import outcome_from_goals
from .player_state import PlayerState
from .server_records import ServerRecordState, update_server_records_after_game
from .schema import ensure_generalstatstable, reset_generalstatstable_row

log = logging.getLogger(__name__)

TARGET_DATABASES = {
    "local": "ko2unity_db",
    "sandbox": "ko2unity_work",
    "staging": "kooldb",
}

GAME_SELECT = """
    SELECT id, Date, idA, idB, GoalsA, GoalsB
    FROM ratedresults
    ORDER BY Date ASC, id ASC
"""

RATEDRESULTS_UPDATE = """
    UPDATE ratedresults SET
        RatingA = %(RatingA)s,
        RatingB = %(RatingB)s,
        ExpectedScoreA = %(ExpectedScoreA)s,
        ExpectedScoreB = %(ExpectedScoreB)s,
        AdjustmentA = %(AdjustmentA)s,
        AdjustmentB = %(AdjustmentB)s,
        NewRatingA = %(NewRatingA)s,
        NewRatingB = %(NewRatingB)s,
        RatingDifference = %(RatingDifference)s,
        ActualScore = %(ActualScore)s,
        WinnerID = %(WinnerID)s,
        SumOfGoals = %(SumOfGoals)s,
        GoalDifference = %(GoalDifference)s,
        HomeWin = %(HomeWin)s,
        Draw = %(Draw)s,
        AwayWin = %(AwayWin)s,
        DDPlayerA = %(DDPlayerA)s,
        DDPlayerB = %(DDPlayerB)s,
        CSPlayerA = %(CSPlayerA)s,
        CSPlayerB = %(CSPlayerB)s
    WHERE id = %(id)s
"""


def _playertable_update_sql() -> str:
    sample = PlayerState().to_db_row(1)
    cols = [k for k in sample if k != "ID"]
    sets = ", ".join(f"`{c}` = %({c})s" for c in cols)
    return f"UPDATE playertable SET {sets} WHERE ID = %(ID)s"


PLAYERTABLE_UPDATE = _playertable_update_sql()


def load_player_names(conn: pymysql.connections.Connection) -> dict[int, str]:
    with conn.cursor() as cur:
        cur.execute("SELECT ID, Name FROM playertable")
        return {int(row["ID"]): str(row["Name"]) for row in cur.fetchall()}


def _resolve_target(cfg: DbConfig, target: str | None) -> str:
    if cfg.database not in ALLOWED_DATABASES:
        raise SystemExit(
            f"Refusing to connect: database {cfg.database!r} not in {sorted(ALLOWED_DATABASES)}"
        )

    if target is None:
        if cfg.database == TARGET_DATABASES["local"]:
            return "local"
        if cfg.database == TARGET_DATABASES["sandbox"]:
            return "sandbox"
        raise SystemExit(
            f"Refusing to use database {cfg.database!r} without an explicit target. "
            "Use --target sandbox with ladder-work.ini, or --target staging for staging."
        )

    if target not in TARGET_DATABASES:
        raise SystemExit(f"Unknown target {target!r}; expected one of {sorted(TARGET_DATABASES)}")

    expected = TARGET_DATABASES[target]
    if cfg.database != expected:
        raise SystemExit(
            f"Refusing target {target!r}: config database is {cfg.database!r}, expected {expected!r}."
        )

    return target


def _log_connection_identity(
    conn: pymysql.connections.Connection,
    *,
    target: str,
    configured_host: str,
    configured_port: int,
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT
                DATABASE() AS db,
                CURRENT_USER() AS db_user,
                @@hostname AS server_host,
                @@port AS server_port,
                VERSION() AS server_version,
                @@session.time_zone AS session_tz
            """
        )
        row = cur.fetchone()
        assert row is not None

    log.info(
        "DB target=%s configured_host=%s configured_port=%s db=%s current_user=%s server_host=%s server_port=%s version=%s session_tz=%s",
        target,
        configured_host,
        configured_port,
        row["db"],
        row["db_user"],
        row["server_host"],
        row["server_port"],
        row["server_version"],
        row["session_tz"],
    )


_PROTECTED_BASELINE_DATABASES = frozenset({"ko2unity_baseline", "kooldb2"})


def connect(
    cfg: DbConfig,
    *,
    dry_run: bool,
    target: str | None = None,
) -> pymysql.connections.Connection:
    if cfg.database in _PROTECTED_BASELINE_DATABASES:
        raise SystemExit(
            f"Refusing to connect to protected baseline database {cfg.database!r}."
        )
    resolved_target = _resolve_target(cfg, target)
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
    _log_connection_identity(
        conn,
        target=resolved_target,
        configured_host=cfg.host,
        configured_port=cfg.port,
    )
    with conn.cursor() as cur:
        cur.execute("SELECT DATABASE() AS db")
        row = cur.fetchone()
        assert row is not None
        if row["db"] != cfg.database:
            raise SystemExit(f"DATABASE()={row['db']!r} != configured {cfg.database!r}")
    if dry_run:
        log.info("Dry-run: connected to %s (no commits)", cfg.database)
    return conn


def _sql_set_null(columns: tuple[str, ...]) -> str:
    return ", ".join(f"`{c}` = NULL" for c in columns)


def reset_universe(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    rated_clear = _sql_set_null(RATEDRESULTS_CLEAR)
    sql_rated = f"UPDATE ratedresults SET {rated_clear}"

    player_parts = [f"`Rating` = {START_RATING}"]
    player_parts.append(_sql_set_null(PLAYERTABLE_NULL_ON_RESET))
    player_parts.extend(f"`{c}` = 0" for c in PLAYERTABLE_ZERO_ON_RESET)
    for col, val in PLAYERTABLE_SENTINELS_ON_RESET.items():
        player_parts.append(f"`{col}` = {val}")
    player_parts.append(f"`LastGame` = '{PLAYERTABLE_LASTGAME_RESET}'")
    sql_player = f"UPDATE playertable SET {', '.join(player_parts)}"

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM ratedresults")
        games = cur.fetchone()["n"]
        cur.execute("SELECT COUNT(*) AS n FROM playertable")
        players = cur.fetchone()["n"]

    log.info("reset_universe: ratedresults rows=%s, playertable rows=%s", games, players)

    if dry_run:
        return

    ensure_generalstatstable(conn)
    with conn.cursor() as cur:
        cur.execute(sql_rated)
        log.info("ratedresults cleared: %s rows affected", cur.rowcount)
        cur.execute(sql_player)
        log.info("playertable reset: %s rows affected", cur.rowcount)
    reset_generalstatstable_row(conn)
    conn.commit()


def apply_game_row(
    game: dict[str, Any],
    players: dict[int, PlayerState],
    *,
    names: dict[int, str],
    server_records: ServerRecordState | None = None,
) -> dict[str, Any]:
    id_a = int(game["idA"])
    id_b = int(game["idB"])
    goals_a = int(game["GoalsA"])
    goals_b = int(game["GoalsB"])
    game_id = int(game["id"])
    game_date = game["Date"]

    pa = players.setdefault(id_a, PlayerState())
    pb = players.setdefault(id_b, PlayerState())

    outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
    elo = compute_elo(pa.rating, pb.rating, outcome.actual_score)

    pa.apply_match(
        players=players,
        opponent_id=id_b,
        opponent_rating_before=elo.rating_b,
        goals_for=goals_a,
        goals_against=goals_b,
        actual_score=outcome.actual_score,
        goal_difference=outcome.goal_difference,
        sum_of_goals=outcome.sum_of_goals,
        dd_for=bool(outcome.dd_player_a),
        cs_for=goals_b == 0,
        old_rating=elo.rating_a,
        new_rating=elo.new_rating_a,
        adjustment=elo.adjustment_a,
        game_id=game_id,
        game_date=game_date,
    )
    pb.apply_match(
        players=players,
        opponent_id=id_a,
        opponent_rating_before=elo.rating_a,
        goals_for=goals_b,
        goals_against=goals_a,
        actual_score=1.0 - outcome.actual_score if outcome.actual_score != 0.5 else 0.5,
        goal_difference=outcome.goal_difference,
        sum_of_goals=outcome.sum_of_goals,
        dd_for=bool(outcome.dd_player_b),
        cs_for=goals_a == 0,
        old_rating=elo.rating_b,
        new_rating=elo.new_rating_b,
        adjustment=elo.adjustment_b,
        game_id=game_id,
        game_date=game_date,
    )

    if server_records is not None:
        update_server_records_after_game(
            server_records,
            game_id=game_id,
            game_date=game_date,
            id_a=id_a,
            id_b=id_b,
            name_a=names.get(id_a, ""),
            name_b=names.get(id_b, ""),
            pa=pa,
            pb=pb,
            actual_score=outcome.actual_score,
            goal_difference=outcome.goal_difference,
            sum_of_goals=outcome.sum_of_goals,
            goals_a=goals_a,
            goals_b=goals_b,
            dd_a=bool(outcome.dd_player_a),
            dd_b=bool(outcome.dd_player_b),
            cs_a=bool(outcome.cs_player_a),
            cs_b=bool(outcome.cs_player_b),
            players=players,
            names=names,
        )

    return {
        "id": game_id,
        "idA": id_a,
        "idB": id_b,
        "RatingA": elo.rating_a,
        "RatingB": elo.rating_b,
        "ExpectedScoreA": elo.expected_a,
        "ExpectedScoreB": elo.expected_b,
        "AdjustmentA": elo.adjustment_a,
        "AdjustmentB": elo.adjustment_b,
        "NewRatingA": elo.new_rating_a,
        "NewRatingB": elo.new_rating_b,
        "RatingDifference": elo.rating_difference,
        "ActualScore": outcome.actual_score,
        "WinnerID": outcome.winner_id,
        "SumOfGoals": outcome.sum_of_goals,
        "GoalDifference": outcome.goal_difference,
        "HomeWin": outcome.home_win,
        "Draw": outcome.draw,
        "AwayWin": outcome.away_win,
        "DDPlayerA": outcome.dd_player_a,
        "DDPlayerB": outcome.dd_player_b,
        "CSPlayerA": outcome.cs_player_a,
        "CSPlayerB": outcome.cs_player_b,
    }


def replay_all(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    limit: int | None = None,
    batch_size: int = 500,
) -> None:
    players: dict[int, PlayerState] = {}
    names = load_player_names(conn)
    server_records = ServerRecordState()

    with conn.cursor() as cur:
        cur.execute("SELECT ID FROM playertable")
        for row in cur.fetchall():
            players[int(row["ID"])] = PlayerState()

    with conn.cursor() as cur:
        cur.execute(GAME_SELECT)
        games = cur.fetchall()

    if limit is not None:
        games = games[:limit]

    log.info("replay_all: %s games, %s players in memory", len(games), len(players))

    if dry_run:
        if games:
            sample = apply_game_row(games[0], players, names=names)
            log.info(
                "Dry-run sample game id=%s NewRatingA=%.3f NewRatingB=%.3f",
                sample["id"],
                sample["NewRatingA"],
                sample["NewRatingB"],
            )
        return

    rated_batch: list[dict[str, Any]] = []
    all_rows: list[dict[str, Any]] = []
    processed = 0

    with conn.cursor() as cur:
        for game in games:
            row = apply_game_row(
                game,
                players,
                names=names,
                server_records=server_records,
            )
            rated_batch.append(row)
            all_rows.append(row)
            processed += 1

            if len(rated_batch) >= batch_size:
                cur.executemany(RATEDRESULTS_UPDATE, rated_batch)
                rated_batch.clear()
                if processed % 5000 == 0:
                    log.info("ratedresults: %s / %s games", processed, len(games))

        if rated_batch:
            cur.executemany(RATEDRESULTS_UPDATE, rated_batch)

    log.info("ratedresults replay done; finalizing playertable counts")
    finalize_network_counts_from_rows(players, all_rows)

    player_rows = [st.to_db_row(pid) for pid, st in players.items() if st.games > 0]
    with conn.cursor() as cur:
        if player_rows:
            cur.executemany(PLAYERTABLE_UPDATE, player_rows)
        log.info("playertable updated: %s players with at least one game", len(player_rows))

    rebuild_period_activity_if_present(conn)
    rebuild_period_aggregates_if_present(conn)
    rebuild_milestones_if_present(conn)
    rebuild_generalstats_if_present(conn, server_records)
    conn.commit()
    log.info("replay_all complete: %s games", processed)


def run_full(cfg: DbConfig, *, dry_run: bool, limit: int | None, target: str | None = None) -> None:
    conn = connect(cfg, dry_run=dry_run, target=target)
    try:
        reset_universe(conn, dry_run=dry_run)
        replay_all(conn, dry_run=dry_run, limit=limit)
    finally:
        conn.close()
