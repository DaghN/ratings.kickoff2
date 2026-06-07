"""Internal stage/fixture helpers for fixture-backed Amiga tournaments."""

from __future__ import annotations

import argparse
from datetime import datetime, timedelta, timezone
import json
import sys
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

VALID_STAGE_TYPES = {"league", "group", "knockout", "placement", "other"}
VALID_FIXTURE_STATUSES = {"scheduled", "played", "void"}
LIVE_SOURCE_SCORES_ID_BASE = 1_000_000_000


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing fixture ops: expected ko2amiga_db, got {cfg.database!r}")
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


def _load_one(conn: pymysql.connections.Connection, sql: str, params: tuple[Any, ...]) -> dict[str, Any] | None:
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.fetchone()


def _require_tournament(conn: pymysql.connections.Connection, tournament_id: int) -> dict[str, Any]:
    row = _load_one(conn, "SELECT id, name FROM tournaments WHERE id = %s", (tournament_id,))
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    return row


def _require_player(conn: pymysql.connections.Connection, player_id: int) -> None:
    row = _load_one(conn, "SELECT id FROM amiga_players WHERE id = %s", (player_id,))
    if row is None:
        raise ValueError(f"player_id={player_id} not found")


def _load_stage_by_key(conn: pymysql.connections.Connection, tournament_id: int, stage_key: str) -> dict[str, Any] | None:
    return _load_one(
        conn,
        "SELECT id, tournament_id, stage_key FROM tournament_stages WHERE tournament_id = %s AND stage_key = %s",
        (tournament_id, stage_key),
    )


def _parse_json_object(raw: str | None) -> dict[str, Any] | None:
    if raw is None or raw.strip() == "":
        return None
    value = json.loads(raw)
    if not isinstance(value, dict):
        raise ValueError("JSON config must be an object")
    return value


def _parse_played_at(raw: str | None) -> datetime | None:
    if raw is None or raw.strip() == "":
        return None
    text = raw.strip()
    if text.endswith("Z"):
        text = text[:-1] + "+00:00"
    dt = datetime.fromisoformat(text)
    if dt.tzinfo is not None:
        dt = dt.astimezone(timezone.utc).replace(tzinfo=None)
    return dt


def _next_live_source_scores_id(conn: pymysql.connections.Connection) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COALESCE(MAX(source_scores_id), %s - 1) AS max_id
            FROM amiga_games
            WHERE source_scores_id >= %s
            """,
            (LIVE_SOURCE_SCORES_ID_BASE, LIVE_SOURCE_SCORES_ID_BASE),
        )
        return int(cur.fetchone()["max_id"]) + 1


def _next_append_only_game_date(conn: pymysql.connections.Connection) -> datetime:
    with conn.cursor() as cur:
        cur.execute("SELECT MAX(game_date) AS max_game_date FROM amiga_games")
        row = cur.fetchone()
    max_game_date = row["max_game_date"] if row else None
    if isinstance(max_game_date, datetime):
        return max_game_date + timedelta(seconds=1)
    return datetime.now(tz=timezone.utc).replace(tzinfo=None, microsecond=0)


def create_stage(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    stage_key: str,
    name: str,
    stage_type: str,
    sequence_no: int = 0,
    track_key: str | None = None,
    parent_stage_key: str | None = None,
    config: dict[str, Any] | None = None,
) -> int:
    _require_tournament(conn, tournament_id)
    if stage_type not in VALID_STAGE_TYPES:
        raise ValueError(f"stage_type must be one of {sorted(VALID_STAGE_TYPES)}")
    parent_stage_id = None
    if parent_stage_key:
        parent = _load_stage_by_key(conn, tournament_id, parent_stage_key)
        if parent is None:
            raise ValueError(f"parent stage {parent_stage_key!r} not found in tournament_id={tournament_id}")
        parent_stage_id = int(parent["id"])

    sql = """
        INSERT INTO tournament_stages
            (tournament_id, parent_stage_id, stage_key, name, stage_type, track_key, sequence_no, config_json)
        VALUES
            (%(tournament_id)s, %(parent_stage_id)s, %(stage_key)s, %(name)s, %(stage_type)s,
             %(track_key)s, %(sequence_no)s, %(config_json)s)
        ON DUPLICATE KEY UPDATE
            parent_stage_id = VALUES(parent_stage_id),
            name = VALUES(name),
            stage_type = VALUES(stage_type),
            track_key = VALUES(track_key),
            sequence_no = VALUES(sequence_no),
            config_json = VALUES(config_json)
    """
    params = {
        "tournament_id": tournament_id,
        "parent_stage_id": parent_stage_id,
        "stage_key": stage_key,
        "name": name,
        "stage_type": stage_type,
        "track_key": track_key,
        "sequence_no": sequence_no,
        "config_json": json.dumps(config, sort_keys=True) if config is not None else None,
    }
    with conn.cursor() as cur:
        cur.execute(sql, params)
        cur.execute(
            "SELECT id FROM tournament_stages WHERE tournament_id = %s AND stage_key = %s",
            (tournament_id, stage_key),
        )
        return int(cur.fetchone()["id"])


def add_stage_player(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    stage_key: str,
    player_id: int,
    seed_no: int | None = None,
    group_key: str | None = None,
) -> None:
    stage = _load_stage_by_key(conn, tournament_id, stage_key)
    if stage is None:
        raise ValueError(f"stage {stage_key!r} not found in tournament_id={tournament_id}")
    _require_player(conn, player_id)
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_stage_players (stage_id, player_id, seed_no, group_key)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE seed_no = VALUES(seed_no), group_key = VALUES(group_key)
            """,
            (int(stage["id"]), player_id, seed_no, group_key),
        )


def create_fixture(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    stage_key: str,
    fixture_key: str,
    player_a_id: int | None = None,
    player_b_id: int | None = None,
    leg_no: int = 1,
    status: str = "scheduled",
    phase_label: str | None = None,
    scheduled_at: str | None = None,
) -> int:
    stage = _load_stage_by_key(conn, tournament_id, stage_key)
    if stage is None:
        raise ValueError(f"stage {stage_key!r} not found in tournament_id={tournament_id}")
    if status not in VALID_FIXTURE_STATUSES:
        raise ValueError(f"status must be one of {sorted(VALID_FIXTURE_STATUSES)}")
    if player_a_id is not None:
        _require_player(conn, player_a_id)
    if player_b_id is not None:
        _require_player(conn, player_b_id)
    if player_a_id is not None and player_b_id is not None and player_a_id == player_b_id:
        raise ValueError("fixture players must be different")

    sql = """
        INSERT INTO tournament_fixtures
            (stage_id, fixture_key, player_a_id, player_b_id, leg_no, status, phase_label, scheduled_at)
        VALUES
            (%(stage_id)s, %(fixture_key)s, %(player_a_id)s, %(player_b_id)s, %(leg_no)s,
             %(status)s, %(phase_label)s, %(scheduled_at)s)
        ON DUPLICATE KEY UPDATE
            player_a_id = VALUES(player_a_id),
            player_b_id = VALUES(player_b_id),
            leg_no = VALUES(leg_no),
            status = VALUES(status),
            phase_label = VALUES(phase_label),
            scheduled_at = VALUES(scheduled_at)
    """
    params = {
        "stage_id": int(stage["id"]),
        "fixture_key": fixture_key,
        "player_a_id": player_a_id,
        "player_b_id": player_b_id,
        "leg_no": leg_no,
        "status": status,
        "phase_label": phase_label,
        "scheduled_at": scheduled_at,
    }
    with conn.cursor() as cur:
        cur.execute(sql, params)
        cur.execute(
            "SELECT id FROM tournament_fixtures WHERE stage_id = %s AND fixture_key = %s",
            (int(stage["id"]), fixture_key),
        )
        return int(cur.fetchone()["id"])


def attach_game_to_fixture(
    conn: pymysql.connections.Connection,
    *,
    game_id: int,
    fixture_id: int,
) -> None:
    game = _load_one(
        conn,
        "SELECT id, tournament_id, player_a_id, player_b_id FROM amiga_games WHERE id = %s",
        (game_id,),
    )
    if game is None:
        raise ValueError(f"game_id={game_id} not found")
    fixture = _load_one(
        conn,
        """
        SELECT f.id, f.player_a_id, f.player_b_id, s.tournament_id
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        WHERE f.id = %s
        """,
        (fixture_id,),
    )
    if fixture is None:
        raise ValueError(f"fixture_id={fixture_id} not found")
    if int(game["tournament_id"]) != int(fixture["tournament_id"]):
        raise ValueError("game and fixture belong to different tournaments")

    fixture_players = {fixture["player_a_id"], fixture["player_b_id"]} - {None}
    game_players = {int(game["player_a_id"]), int(game["player_b_id"])}
    if fixture_players and {int(pid) for pid in fixture_players} != game_players:
        raise ValueError("game players do not match fixture players")

    with conn.cursor() as cur:
        cur.execute("UPDATE amiga_games SET fixture_id = %s WHERE id = %s", (fixture_id, game_id))
        cur.execute("UPDATE tournament_fixtures SET status = 'played' WHERE id = %s", (fixture_id,))


def set_fixture_players(
    conn: pymysql.connections.Connection,
    *,
    fixture_id: int,
    player_a_id: int,
    player_b_id: int,
) -> None:
    if player_a_id == player_b_id:
        raise ValueError("fixture players must be different")
    _require_player(conn, player_a_id)
    _require_player(conn, player_b_id)
    fixture = _load_one(
        conn,
        """
        SELECT f.id, f.status, s.tournament_id
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        WHERE f.id = %s
        """,
        (fixture_id,),
    )
    if fixture is None:
        raise ValueError(f"fixture_id={fixture_id} not found")
    if fixture["status"] != "scheduled":
        raise ValueError(f"fixture_id={fixture_id} status is {fixture['status']!r}, expected 'scheduled'")
    tournament_id = int(fixture["tournament_id"])
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id = %s", (fixture_id,))
        if int(cur.fetchone()["n"]) > 0:
            raise ValueError(f"fixture_id={fixture_id} already has an attached game")
        cur.execute(
            """
            SELECT COUNT(DISTINCT sp.player_id) AS n
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            WHERE s.tournament_id = %s
              AND sp.player_id IN (%s, %s)
            """,
            (tournament_id, player_a_id, player_b_id),
        )
        if int(cur.fetchone()["n"]) != 2:
            raise ValueError("fixture players must already belong to the tournament")
        cur.execute(
            "UPDATE tournament_fixtures SET player_a_id = %s, player_b_id = %s WHERE id = %s",
            (player_a_id, player_b_id, fixture_id),
        )


def record_fixture_result(
    conn: pymysql.connections.Connection,
    *,
    fixture_id: int,
    goals_a: int,
    goals_b: int,
    extra: str | None = None,
    played_at: datetime | None = None,
) -> int:
    if goals_a < 0 or goals_b < 0:
        raise ValueError("goals must be non-negative")
    fixture = _load_one(
        conn,
        """
        SELECT f.id, f.player_a_id, f.player_b_id, f.status, f.phase_label, s.tournament_id
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        WHERE f.id = %s
        """,
        (fixture_id,),
    )
    if fixture is None:
        raise ValueError(f"fixture_id={fixture_id} not found")
    if fixture["status"] != "scheduled":
        raise ValueError(f"fixture_id={fixture_id} status is {fixture['status']!r}, expected 'scheduled'")
    player_a_id = fixture["player_a_id"]
    player_b_id = fixture["player_b_id"]
    if player_a_id is None or player_b_id is None:
        raise ValueError("fixture must have both players before result entry")

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id = %s", (fixture_id,))
        if int(cur.fetchone()["n"]) > 0:
            raise ValueError(f"fixture_id={fixture_id} already has an attached game")

    game_date = played_at or _next_append_only_game_date(conn)
    last_game_date = _next_append_only_game_date(conn) - timedelta(seconds=1)
    if game_date <= last_game_date:
        raise ValueError(f"played_at={game_date.isoformat(sep=' ')} is not after last game_date")

    source_scores_id = _next_live_source_scores_id(conn)
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO amiga_games
              (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id,
               phase, goals_a, goals_b, extra)
            VALUES
              (%(source_scores_id)s, %(game_date)s, %(player_a_id)s, %(player_b_id)s,
               %(tournament_id)s, %(fixture_id)s, %(phase)s, %(goals_a)s, %(goals_b)s, %(extra)s)
            """,
            {
                "source_scores_id": source_scores_id,
                "game_date": game_date.strftime("%Y-%m-%d %H:%M:%S"),
                "player_a_id": int(player_a_id),
                "player_b_id": int(player_b_id),
                "tournament_id": int(fixture["tournament_id"]),
                "fixture_id": fixture_id,
                "phase": fixture["phase_label"],
                "goals_a": goals_a,
                "goals_b": goals_b,
                "extra": extra.strip() if extra and extra.strip() else None,
            },
        )
        game_id = int(cur.lastrowid)
        cur.execute("UPDATE tournament_fixtures SET status = 'played' WHERE id = %s", (fixture_id,))
    return game_id


def list_fixtures(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    status: str | None = None,
    limit: int = 200,
) -> list[dict[str, Any]]:
    if status is not None and status not in VALID_FIXTURE_STATUSES:
        raise ValueError(f"status must be one of {sorted(VALID_FIXTURE_STATUSES)}")
    _require_tournament(conn, tournament_id)
    params: list[Any] = [tournament_id]
    status_clause = ""
    if status is not None:
        status_clause = " AND f.status = %s"
        params.append(status)
    params.append(max(1, min(limit, 1000)))
    sql = f"""
        SELECT f.id, f.fixture_key, f.leg_no, f.status, f.phase_label,
               s.stage_key, s.name AS stage_name, s.stage_type, s.sequence_no,
               f.player_a_id, pa.name AS player_a_name,
               f.player_b_id, pb.name AS player_b_name,
               g.id AS game_id, g.goals_a, g.goals_b, g.extra
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        LEFT JOIN amiga_players pa ON pa.id = f.player_a_id
        LEFT JOIN amiga_players pb ON pb.id = f.player_b_id
        LEFT JOIN amiga_games g ON g.fixture_id = f.id
        WHERE s.tournament_id = %s{status_clause}
        ORDER BY s.sequence_no ASC, s.id ASC, f.id ASC
        LIMIT %s
    """
    with conn.cursor() as cur:
        cur.execute(sql, tuple(params))
        return list(cur.fetchall())


def fixture_detail(conn: pymysql.connections.Connection, *, fixture_id: int) -> dict[str, Any] | None:
    sql = """
        SELECT f.id, f.fixture_key, f.leg_no, f.status, f.phase_label, f.scheduled_at,
               s.tournament_id, t.name AS tournament_name,
               s.stage_key, s.name AS stage_name, s.stage_type, s.sequence_no,
               f.player_a_id, pa.name AS player_a_name,
               f.player_b_id, pb.name AS player_b_name,
               g.id AS game_id, g.source_scores_id, g.game_date, g.goals_a, g.goals_b, g.extra
        FROM tournament_fixtures f
        INNER JOIN tournament_stages s ON s.id = f.stage_id
        INNER JOIN tournaments t ON t.id = s.tournament_id
        LEFT JOIN amiga_players pa ON pa.id = f.player_a_id
        LEFT JOIN amiga_players pb ON pb.id = f.player_b_id
        LEFT JOIN amiga_games g ON g.fixture_id = f.id
        WHERE f.id = %s
    """
    return _load_one(conn, sql, (fixture_id,))


def cleanup_generated_tournament(conn: pymysql.connections.Connection, *, tournament_id: int) -> None:
    row = _load_one(
        conn,
        """
        SELECT id, name, source_id, format_overrides
        FROM tournaments
        WHERE id = %s
        """,
        (tournament_id,),
    )
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    if row.get("source_id") is not None:
        raise ValueError("refusing to delete imported Access tournament")
    overrides = json.loads(str(row.get("format_overrides") or "{}"))
    generated_by = str(overrides.get("generated_by") or "")
    allowed_generators = (
        "scripts.amiga.tournament_builder",
        "site.public_html.amiga.ops.fixtures",
    )
    if not any(generated_by.startswith(prefix) for prefix in allowed_generators):
        raise ValueError("refusing to delete tournament not generated by approved fixture tooling")
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        game_count = int(cur.fetchone()["n"])
        if game_count > 0:
            raise ValueError(f"refusing to delete tournament with {game_count} game(s)")
        cur.execute("DELETE FROM tournaments WHERE id = %s", (tournament_id,))


def audit_fixture_integrity(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id AS game_id, g.tournament_id AS game_tournament_id, s.tournament_id AS fixture_tournament_id
            FROM amiga_games g
            INNER JOIN tournament_fixtures f ON f.id = g.fixture_id
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE g.tournament_id <> s.tournament_id
            ORDER BY g.id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"game {row['game_id']} tournament {row['game_tournament_id']} "
                f"!= fixture tournament {row['fixture_tournament_id']}"
            )

        cur.execute(
            """
            SELECT g.id AS game_id
            FROM amiga_games g
            INNER JOIN tournament_fixtures f ON f.id = g.fixture_id
            WHERE f.player_a_id IS NOT NULL
              AND f.player_b_id IS NOT NULL
              AND NOT (
                (g.player_a_id = f.player_a_id AND g.player_b_id = f.player_b_id)
                OR (g.player_a_id = f.player_b_id AND g.player_b_id = f.player_a_id)
              )
            ORDER BY g.id
            """
        )
        for row in cur.fetchall():
            errors.append(f"game {row['game_id']} players do not match fixture players")

        cur.execute(
            """
            SELECT f.id AS fixture_id, f.status, COUNT(g.id) AS game_count
            FROM tournament_fixtures f
            LEFT JOIN amiga_games g ON g.fixture_id = f.id
            GROUP BY f.id, f.status
            HAVING (f.status = 'played' AND game_count <> 1)
                OR (f.status = 'scheduled' AND game_count <> 0)
            ORDER BY f.id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"fixture {row['fixture_id']} status {row['status']!r} has {row['game_count']} game(s)"
            )
    return errors


def _print_counts(conn: pymysql.connections.Connection) -> None:
    with conn.cursor() as cur:
        for table in ("tournament_stages", "tournament_stage_players", "tournament_fixtures"):
            cur.execute(f"SELECT COUNT(*) AS n FROM {table}")
            print(f"{table}={int(cur.fetchone()['n'])}")
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id IS NOT NULL")
        print(f"fixture_backed_games={int(cur.fetchone()['n'])}")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Internal Amiga tournament stage/fixture operations")
    sub = parser.add_subparsers(dest="cmd", required=True)

    sub.add_parser("verify", help="Verify fixture-backed game integrity")

    p_stage = sub.add_parser("create-stage", help="Create or update a tournament stage")
    p_stage.add_argument("--tournament-id", type=int, required=True)
    p_stage.add_argument("--stage-key", required=True)
    p_stage.add_argument("--name", required=True)
    p_stage.add_argument("--stage-type", choices=sorted(VALID_STAGE_TYPES), required=True)
    p_stage.add_argument("--sequence-no", type=int, default=0)
    p_stage.add_argument("--track-key", default=None)
    p_stage.add_argument("--parent-stage-key", default=None)
    p_stage.add_argument("--config-json", default=None)

    p_stage_player = sub.add_parser("add-stage-player", help="Add or update a player in a stage")
    p_stage_player.add_argument("--tournament-id", type=int, required=True)
    p_stage_player.add_argument("--stage-key", required=True)
    p_stage_player.add_argument("--player-id", type=int, required=True)
    p_stage_player.add_argument("--seed-no", type=int, default=None)
    p_stage_player.add_argument("--group-key", default=None)

    p_fixture = sub.add_parser("create-fixture", help="Create or update a fixture")
    p_fixture.add_argument("--tournament-id", type=int, required=True)
    p_fixture.add_argument("--stage-key", required=True)
    p_fixture.add_argument("--fixture-key", required=True)
    p_fixture.add_argument("--player-a-id", type=int, default=None)
    p_fixture.add_argument("--player-b-id", type=int, default=None)
    p_fixture.add_argument("--leg-no", type=int, default=1)
    p_fixture.add_argument("--status", choices=sorted(VALID_FIXTURE_STATUSES), default="scheduled")
    p_fixture.add_argument("--phase-label", default=None)
    p_fixture.add_argument("--scheduled-at", default=None)

    p_attach = sub.add_parser("attach-game", help="Attach an existing game to a fixture")
    p_attach.add_argument("--game-id", type=int, required=True)
    p_attach.add_argument("--fixture-id", type=int, required=True)

    p_set_players = sub.add_parser("set-players", help="Assign players to a scheduled fixture")
    p_set_players.add_argument("--fixture-id", type=int, required=True)
    p_set_players.add_argument("--player-a-id", type=int, required=True)
    p_set_players.add_argument("--player-b-id", type=int, required=True)
    p_set_players.add_argument("--dry-run", action="store_true")

    p_record = sub.add_parser("record-result", help="Create one canonical game row from a scheduled fixture")
    p_record.add_argument("--fixture-id", type=int, required=True)
    p_record.add_argument("--goals-a", type=int, required=True)
    p_record.add_argument("--goals-b", type=int, required=True)
    p_record.add_argument("--extra", default=None)
    p_record.add_argument("--played-at", default=None, help="UTC ISO timestamp; defaults to last game_date + 1s")
    p_record.add_argument("--dry-run", action="store_true")

    p_list = sub.add_parser("list", help="List fixtures for one tournament")
    p_list.add_argument("--tournament-id", type=int, required=True)
    p_list.add_argument("--status", choices=sorted(VALID_FIXTURE_STATUSES), default=None)
    p_list.add_argument("--limit", type=int, default=200)

    p_detail = sub.add_parser("detail", help="Show one fixture")
    p_detail.add_argument("--fixture-id", type=int, required=True)

    p_cleanup = sub.add_parser("cleanup-generated", help="Delete an unplayed tournament generated by builder")
    p_cleanup.add_argument("--tournament-id", type=int, required=True)
    p_cleanup.add_argument("--dry-run", action="store_true")

    args = parser.parse_args(argv)
    conn = _connect()
    try:
        if args.cmd == "verify":
            errors = audit_fixture_integrity(conn)
            _print_counts(conn)
            if errors:
                for error in errors:
                    print(f"FAIL: {error}", file=sys.stderr)
                return 1
            print("OK: fixture integrity checks passed")
            return 0

        if args.cmd == "create-stage":
            stage_id = create_stage(
                conn,
                tournament_id=args.tournament_id,
                stage_key=args.stage_key,
                name=args.name,
                stage_type=args.stage_type,
                sequence_no=args.sequence_no,
                track_key=args.track_key,
                parent_stage_key=args.parent_stage_key,
                config=_parse_json_object(args.config_json),
            )
            conn.commit()
            print(f"stage_id={stage_id}")
            return 0

        if args.cmd == "add-stage-player":
            add_stage_player(
                conn,
                tournament_id=args.tournament_id,
                stage_key=args.stage_key,
                player_id=args.player_id,
                seed_no=args.seed_no,
                group_key=args.group_key,
            )
            conn.commit()
            print("OK: stage player upserted")
            return 0

        if args.cmd == "create-fixture":
            fixture_id = create_fixture(
                conn,
                tournament_id=args.tournament_id,
                stage_key=args.stage_key,
                fixture_key=args.fixture_key,
                player_a_id=args.player_a_id,
                player_b_id=args.player_b_id,
                leg_no=args.leg_no,
                status=args.status,
                phase_label=args.phase_label,
                scheduled_at=args.scheduled_at,
            )
            conn.commit()
            print(f"fixture_id={fixture_id}")
            return 0

        if args.cmd == "attach-game":
            attach_game_to_fixture(conn, game_id=args.game_id, fixture_id=args.fixture_id)
            conn.commit()
            print("OK: game attached to fixture")
            return 0

        if args.cmd == "set-players":
            set_fixture_players(
                conn,
                fixture_id=args.fixture_id,
                player_a_id=args.player_a_id,
                player_b_id=args.player_b_id,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print("OK: fixture players assigned")
            return 0

        if args.cmd == "record-result":
            game_id = record_fixture_result(
                conn,
                fixture_id=args.fixture_id,
                goals_a=args.goals_a,
                goals_b=args.goals_b,
                extra=args.extra,
                played_at=_parse_played_at(args.played_at),
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(f"game_id={game_id}")
            return 0

        if args.cmd == "list":
            rows = list_fixtures(
                conn,
                tournament_id=args.tournament_id,
                status=args.status,
                limit=args.limit,
            )
            for row in rows:
                score = ""
                if row.get("game_id") is not None:
                    score = f" score={row['goals_a']}-{row['goals_b']} game_id={row['game_id']}"
                print(
                    f"fixture_id={row['id']} status={row['status']} stage={row['stage_key']} "
                    f"key={row['fixture_key']} {row.get('player_a_name') or '#'} vs "
                    f"{row.get('player_b_name') or '#'}{score}"
                )
            print(f"fixtures={len(rows)}")
            return 0

        if args.cmd == "detail":
            row = fixture_detail(conn, fixture_id=args.fixture_id)
            if row is None:
                print(f"FAIL: fixture_id={args.fixture_id} not found", file=sys.stderr)
                return 1
            print(json.dumps(row, default=str, indent=2, sort_keys=True))
            return 0

        if args.cmd == "cleanup-generated":
            cleanup_generated_tournament(conn, tournament_id=args.tournament_id)
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
                print(f"would delete tournament_id={args.tournament_id}")
            else:
                conn.commit()
                print(f"deleted tournament_id={args.tournament_id}")
            return 0
    finally:
        conn.close()

    return 1
