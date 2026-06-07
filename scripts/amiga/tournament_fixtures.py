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
from scripts.amiga.player_registry import check_player_name, create_player, suggest_player_name

VALID_STAGE_TYPES = {"league", "group", "knockout", "placement", "other"}
VALID_FIXTURE_STATUSES = {"scheduled", "played", "void"}
VALID_ENTRANT_STATUSES = {"registered", "withdrawn", "replaced"}
ACTIVE_ENTRANT_STATUSES = {"registered"}
VALID_LIFECYCLE_STATUSES = {
    "draft",
    "registration",
    "ready",
    "running",
    "completed",
    "archived",
    "void",
}
RESULT_ENTRY_LIFECYCLE_STATUSES = {"running"}
ENTRANT_REGISTRATION_LIFECYCLE_STATUSES = {"draft", "registration", "ready"}
IMPORTED_LIFECYCLE_STATUSES = {"completed", "archived"}
GENERATED_DEFAULT_LIFECYCLE_STATUS = "draft"
GENERATED_FIXTURE_PREFIXES = (
    "scripts.amiga.tournament_builder",
    "site.public_html.amiga.ops.fixtures",
)
LIVE_SOURCE_SCORES_ID_BASE = 1_000_000_000
BACKFILL_ENTRANT_NOTE = "backfilled by fixtures backfill-entrants"
WITHDRAW_ENTRANT_ACTION = "withdrawn by fixtures withdraw-entrant"
REPLACE_ENTRANT_ACTION = "replaced by fixtures replace-entrant"


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


def _load_tournament_lifecycle(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[str, Any]:
    row = _load_one(
        conn,
        """
        SELECT id, name, source_id, lifecycle_status, started_at, completed_at
        FROM tournaments
        WHERE id = %s
        """,
        (tournament_id,),
    )
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    return row


def _require_lifecycle_allows_entrant_registration(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
) -> None:
    row = _load_tournament_lifecycle(conn, tournament_id)
    status = str(row["lifecycle_status"])
    if status not in ENTRANT_REGISTRATION_LIFECYCLE_STATUSES:
        allowed = ", ".join(sorted(ENTRANT_REGISTRATION_LIFECYCLE_STATUSES))
        raise ValueError(
            f"tournament_id={tournament_id} lifecycle_status is {status!r}; "
            f"entrant registration is allowed only in {allowed}"
        )


def _require_lifecycle_allows_result_entry(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
) -> None:
    row = _load_tournament_lifecycle(conn, tournament_id)
    status = str(row["lifecycle_status"])
    if status not in RESULT_ENTRY_LIFECYCLE_STATUSES:
        allowed = ", ".join(sorted(RESULT_ENTRY_LIFECYCLE_STATUSES))
        raise ValueError(
            f"tournament_id={tournament_id} lifecycle_status is {status!r}; "
            f"result entry is allowed only in {allowed}"
        )


def _count_unplayed_scheduled_fixtures(conn: pymysql.connections.Connection, *, tournament_id: int) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
              AND f.status = 'scheduled'
            """,
            (tournament_id,),
        )
        return int(cur.fetchone()["n"])


def set_tournament_lifecycle_status(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    status: str,
    dry_run: bool = False,
    force: bool = False,
) -> dict[str, Any]:
    if status not in VALID_LIFECYCLE_STATUSES:
        raise ValueError(f"status must be one of {sorted(VALID_LIFECYCLE_STATUSES)}")

    row = _load_tournament_lifecycle(conn, tournament_id)
    current = str(row["lifecycle_status"])
    if current == status:
        return {
            "dry_run": dry_run,
            "tournament_id": tournament_id,
            "previous_status": current,
            "lifecycle_status": status,
            "changed": False,
            "started_at": row.get("started_at"),
            "completed_at": row.get("completed_at"),
        }

    is_imported = row.get("source_id") is not None
    if is_imported:
        if current in IMPORTED_LIFECYCLE_STATUSES and status not in IMPORTED_LIFECYCLE_STATUSES:
            if not force:
                raise ValueError(
                    f"tournament_id={tournament_id} is an imported historical tournament "
                    f"(lifecycle_status={current!r}); refusing transition to {status!r} without --force"
                )
        if status not in IMPORTED_LIFECYCLE_STATUSES and not force:
            raise ValueError(
                f"tournament_id={tournament_id} is an imported historical tournament; "
                f"only {sorted(IMPORTED_LIFECYCLE_STATUSES)} are allowed without --force"
            )

    unplayed = 0
    if status == "completed":
        unplayed = _count_unplayed_scheduled_fixtures(conn, tournament_id=tournament_id)
        if unplayed > 0 and not force:
            raise ValueError(
                f"tournament_id={tournament_id} has {unplayed} scheduled fixture(s); "
                "refusing transition to completed without --force"
            )

    started_at = row.get("started_at")
    completed_at = row.get("completed_at")
    now = datetime.now(tz=timezone.utc).replace(tzinfo=None)
    if status == "running" and started_at is None:
        started_at = now
    if status in {"completed", "archived"} and completed_at is None:
        completed_at = now

    if not dry_run:
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE tournaments
                SET lifecycle_status = %s,
                    started_at = %s,
                    completed_at = %s
                WHERE id = %s
                """,
                (status, started_at, completed_at, tournament_id),
            )

    return {
        "dry_run": dry_run,
        "tournament_id": tournament_id,
        "previous_status": current,
        "lifecycle_status": status,
        "changed": True,
        "started_at": started_at,
        "completed_at": completed_at,
        "unplayed_scheduled_fixtures": unplayed,
        "force": force,
    }


def _require_player(conn: pymysql.connections.Connection, player_id: int) -> None:
    row = _load_one(conn, "SELECT id FROM amiga_players WHERE id = %s", (player_id,))
    if row is None:
        raise ValueError(f"player_id={player_id} not found")


def _require_active_tournament_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> None:
    entrant = _load_entrant_row(conn, tournament_id=tournament_id, player_id=player_id)
    if entrant is None:
        raise ValueError(
            f"player_id={player_id} is not a tournament entrant in tournament_id={tournament_id}"
        )
    if entrant["status"] != "registered":
        raise ValueError(
            f"player_id={player_id} entrant status is {entrant['status']!r}; "
            "only registered entrants may be used in fixture assignment or result entry"
        )


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


def add_tournament_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
    seed_no: int | None = None,
    status: str = "registered",
    note: str | None = None,
) -> int:
    _require_tournament(conn, tournament_id)
    _require_player(conn, player_id)
    if status not in VALID_ENTRANT_STATUSES:
        raise ValueError(f"status must be one of {sorted(VALID_ENTRANT_STATUSES)}")
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note)
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                seed_no = VALUES(seed_no),
                status = VALUES(status),
                note = VALUES(note)
            """,
            (tournament_id, player_id, seed_no, status, note),
        )
        cur.execute(
            "SELECT id FROM tournament_entrants WHERE tournament_id = %s AND player_id = %s",
            (tournament_id, player_id),
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
        _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=player_a_id)
    if player_b_id is not None:
        _require_player(conn, player_b_id)
        _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=player_b_id)
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
    _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=player_a_id)
    _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=player_b_id)
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
    tournament_id = int(fixture["tournament_id"])
    _require_lifecycle_allows_result_entry(conn, tournament_id=tournament_id)
    _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=int(player_a_id))
    _require_active_tournament_entrant(conn, tournament_id=tournament_id, player_id=int(player_b_id))

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


def list_entrants(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    status: str | None = None,
    limit: int = 500,
) -> list[dict[str, Any]]:
    if status is not None and status not in VALID_ENTRANT_STATUSES:
        raise ValueError(f"status must be one of {sorted(VALID_ENTRANT_STATUSES)}")
    _require_tournament(conn, tournament_id)
    params: list[Any] = [tournament_id]
    status_clause = ""
    if status is not None:
        status_clause = " AND e.status = %s"
        params.append(status)
    params.append(max(1, min(limit, 2000)))
    sql = f"""
        SELECT e.id, e.tournament_id, e.player_id, p.name AS player_name,
               e.seed_no, e.status, e.note, e.created_at
        FROM tournament_entrants e
        INNER JOIN amiga_players p ON p.id = e.player_id
        WHERE e.tournament_id = %s{status_clause}
        ORDER BY e.seed_no IS NULL, e.seed_no ASC, e.id ASC
        LIMIT %s
    """
    with conn.cursor() as cur:
        cur.execute(sql, tuple(params))
        return list(cur.fetchall())


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


def _tournament_generated_by(row: dict[str, Any]) -> str:
    overrides = json.loads(str(row.get("format_overrides") or "{}"))
    return str(overrides.get("generated_by") or "")


def _is_eligible_generated_tournament(row: dict[str, Any]) -> bool:
    if row.get("source_id") is not None:
        return False
    generated_by = _tournament_generated_by(row)
    return any(generated_by.startswith(prefix) for prefix in GENERATED_FIXTURE_PREFIXES)


def _load_eligible_generated_tournaments(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
) -> list[dict[str, Any]]:
    if tournament_id is not None:
        row = _load_one(
            conn,
            "SELECT id, name, source_id, format_overrides FROM tournaments WHERE id = %s",
            (tournament_id,),
        )
        if row is None:
            raise ValueError(f"tournament_id={tournament_id} not found")
        if not _is_eligible_generated_tournament(row):
            raise ValueError(
                f"tournament_id={tournament_id} is not eligible for entrant backfill "
                "(must be generated by approved fixture tooling)"
            )
        return [row]

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, name, source_id, format_overrides
            FROM tournaments
            WHERE source_id IS NULL
            ORDER BY id
            """
        )
        rows = list(cur.fetchall())
    return [row for row in rows if _is_eligible_generated_tournament(row)]


def _collect_tournament_participant_ids(conn: pymysql.connections.Connection, *, tournament_id: int) -> set[int]:
    player_ids: set[int] = set()
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT DISTINCT sp.player_id
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            player_ids.add(int(row["player_id"]))

        cur.execute(
            """
            SELECT f.player_a_id, f.player_b_id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            if row["player_a_id"] is not None:
                player_ids.add(int(row["player_a_id"]))
            if row["player_b_id"] is not None:
                player_ids.add(int(row["player_b_id"]))
    return player_ids


def _load_stage_player_seed_map(conn: pymysql.connections.Connection, *, tournament_id: int) -> dict[int, int | None]:
    """First stage-player seed per player, preferring earliest stage by sequence_no."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT sp.player_id, sp.seed_no, s.sequence_no, s.id AS stage_id
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            WHERE s.tournament_id = %s
            ORDER BY s.sequence_no ASC, s.id ASC, sp.player_id ASC
            """,
            (tournament_id,),
        )
        rows = list(cur.fetchall())
    seeds: dict[int, int | None] = {}
    for row in rows:
        player_id = int(row["player_id"])
        if player_id not in seeds:
            seeds[player_id] = int(row["seed_no"]) if row["seed_no"] is not None else None
    return seeds


def _load_existing_entrant_player_ids(conn: pymysql.connections.Connection, *, tournament_id: int) -> set[int]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT player_id FROM tournament_entrants WHERE tournament_id = %s",
            (tournament_id,),
        )
        return {int(row["player_id"]) for row in cur.fetchall()}


def _load_used_seed_numbers(conn: pymysql.connections.Connection, *, tournament_id: int) -> set[int]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT seed_no FROM tournament_entrants WHERE tournament_id = %s AND seed_no IS NOT NULL",
            (tournament_id,),
        )
        return {int(row["seed_no"]) for row in cur.fetchall()}


def plan_entrant_backfill(conn: pymysql.connections.Connection, *, tournament_id: int) -> list[tuple[int, int | None]]:
    """Return (player_id, seed_no) rows to insert for one tournament. Skips existing entrants."""
    existing = _load_existing_entrant_player_ids(conn, tournament_id=tournament_id)
    participants = _collect_tournament_participant_ids(conn, tournament_id=tournament_id)
    missing = sorted(participants - existing)
    if not missing:
        return []

    stage_seeds = _load_stage_player_seed_map(conn, tournament_id=tournament_id)
    used_seeds = _load_used_seed_numbers(conn, tournament_id=tournament_id)
    planned: list[tuple[int, int | None]] = []
    append_queue: list[int] = []

    for player_id in missing:
        if player_id not in stage_seeds:
            append_queue.append(player_id)
            continue
        seed_no = stage_seeds[player_id]
        if seed_no is not None and seed_no not in used_seeds:
            planned.append((player_id, seed_no))
            used_seeds.add(seed_no)
        elif seed_no is not None:
            planned.append((player_id, None))
        else:
            append_queue.append(player_id)

    next_seed = max(used_seeds) + 1 if used_seeds else 1
    for player_id in append_queue:
        while next_seed in used_seeds:
            next_seed += 1
        planned.append((player_id, next_seed))
        used_seeds.add(next_seed)
        next_seed += 1

    return planned


def _validate_new_entrant_registration(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> None:
    existing = _load_entrant_row(conn, tournament_id=tournament_id, player_id=player_id)
    if existing is None:
        return
    if existing["status"] in ACTIVE_ENTRANT_STATUSES:
        raise ValueError(
            f"player_id={player_id} is already a registered entrant in tournament_id={tournament_id}"
        )
    raise ValueError(
        f"player_id={player_id} entrant status is {existing['status']!r}; "
        "reactivation is not supported by entrant onboarding"
    )


def register_tournament_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
    seed_no: int | None = None,
    note: str | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    tournament = _require_eligible_generated_tournament(conn, tournament_id=tournament_id)
    _require_lifecycle_allows_entrant_registration(conn, tournament_id=tournament_id)
    _require_player(conn, player_id)
    _validate_new_entrant_registration(conn, tournament_id=tournament_id, player_id=player_id)

    entrant_id: int | None = None
    if not dry_run:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note)
                VALUES (%s, %s, %s, 'registered', %s)
                """,
                (tournament_id, player_id, seed_no, note),
            )
            entrant_id = int(cur.lastrowid)

    return {
        "dry_run": dry_run,
        "tournament_id": tournament_id,
        "tournament_name": tournament.get("name"),
        "player_id": player_id,
        "entrant_id": entrant_id,
        "seed_no": seed_no,
        "status": "registered",
        "note": note,
    }


def onboard_newcomer_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    name: str | None = None,
    full_name: str | None = None,
    country: str = "",
    seed_no: int | None = None,
    note: str | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    if name is not None and full_name is not None:
        raise ValueError("provide only one of --name or --full-name, not both")
    if name is None and full_name is None:
        raise ValueError("one of --name or --full-name is required")

    tournament = _require_eligible_generated_tournament(conn, tournament_id=tournament_id)
    _require_lifecycle_allows_entrant_registration(conn, tournament_id=tournament_id)

    name_source: str
    resolved_name: str
    suggestion_payload: dict[str, Any] | None = None

    if name is not None:
        check = check_player_name(name, conn=conn)
        if not check.available:
            existing = check.conflict
            assert existing is not None
            raise ValueError(
                f"name conflict ({check.conflict_kind}): "
                f"normalized={check.normalized_name!r} collides with "
                f"player_id={existing.id} name={existing.name!r}"
            )
        resolved_name = check.normalized_name
        name_source = "explicit"
    else:
        assert full_name is not None
        suggestion_payload = suggest_player_name(full_name, conn=conn)
        suggested = suggestion_payload.get("suggested_name")
        if not suggestion_payload.get("available") or suggested is None:
            reason = suggestion_payload.get("reason") or "no available KOA-style name"
            raise ValueError(f"cannot suggest an available player name for {full_name!r}: {reason}")
        resolved_name = str(suggested)
        name_source = "suggested"

    player_id: int | None = None
    entrant_id: int | None = None

    if dry_run:
        return {
            "dry_run": True,
            "tournament_id": tournament_id,
            "tournament_name": tournament.get("name"),
            "name_source": name_source,
            "resolved_name": resolved_name,
            "country": country,
            "player_id": None,
            "entrant_id": None,
            "seed_no": seed_no,
            "status": "registered",
            "note": note,
            "suggestion": suggestion_payload,
        }

    try:
        player_result = create_player(resolved_name, country=country, conn=conn)
        player_id = player_result["player_id"]
        assert player_id is not None

        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note)
                VALUES (%s, %s, %s, 'registered', %s)
                """,
                (tournament_id, player_id, seed_no, note),
            )
            entrant_id = int(cur.lastrowid)
    except Exception:
        conn.rollback()
        raise

    return {
        "dry_run": False,
        "tournament_id": tournament_id,
        "tournament_name": tournament.get("name"),
        "name_source": name_source,
        "resolved_name": resolved_name,
        "country": country,
        "player_id": player_id,
        "entrant_id": entrant_id,
        "seed_no": seed_no,
        "status": "registered",
        "note": note,
        "suggestion": suggestion_payload,
    }


def insert_entrant_if_missing(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
    seed_no: int | None = None,
    note: str | None = None,
) -> bool:
    """Insert a registered entrant only when no row exists. Preserves withdrawn/replaced rows."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id FROM tournament_entrants WHERE tournament_id = %s AND player_id = %s",
            (tournament_id, player_id),
        )
        if cur.fetchone() is not None:
            return False
    _require_player(conn, player_id)
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note)
            VALUES (%s, %s, %s, 'registered', %s)
            """,
            (tournament_id, player_id, seed_no, note),
        )
    return True


def _append_entrant_note(existing: str | None, action: str, note: str | None) -> str:
    timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    admin_part = f"[{timestamp}] {action}"
    if note and note.strip():
        admin_part = f"{admin_part}: {note.strip()}"
    if existing and existing.strip():
        combined = f"{existing.strip()} | {admin_part}"
    else:
        combined = admin_part
    if len(combined) > 255:
        return combined[:252] + "..."
    return combined


def _require_eligible_generated_tournament(conn: pymysql.connections.Connection, *, tournament_id: int) -> dict[str, Any]:
    row = _load_one(
        conn,
        "SELECT id, name, source_id, format_overrides FROM tournaments WHERE id = %s",
        (tournament_id,),
    )
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    if not _is_eligible_generated_tournament(row):
        if row.get("source_id") is not None:
            raise ValueError(f"tournament_id={tournament_id} is an imported Access tournament; entrant status ops refused")
        raise ValueError(
            f"tournament_id={tournament_id} is not eligible for entrant status ops "
            "(must be generated by approved fixture tooling)"
        )
    return row


def _load_entrant_row(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> dict[str, Any] | None:
    return _load_one(
        conn,
        """
        SELECT id, tournament_id, player_id, seed_no, status, note
        FROM tournament_entrants
        WHERE tournament_id = %s AND player_id = %s
        """,
        (tournament_id, player_id),
    )


def _count_tournament_games_for_player(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games
            WHERE tournament_id = %s
              AND (player_a_id = %s OR player_b_id = %s)
            """,
            (tournament_id, player_id, player_id),
        )
        return int(cur.fetchone()["n"])


def _load_player_fixtures(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id, f.fixture_key, f.status, f.player_a_id, f.player_b_id,
                   (SELECT COUNT(*) FROM amiga_games g WHERE g.fixture_id = f.id) AS game_count
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
              AND (f.player_a_id = %s OR f.player_b_id = %s)
            ORDER BY f.id
            """,
            (tournament_id, player_id, player_id),
        )
        return list(cur.fetchall())


def _validate_withdrawal_eligibility(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
) -> dict[str, Any]:
    entrant = _load_entrant_row(conn, tournament_id=tournament_id, player_id=player_id)
    if entrant is None:
        raise ValueError(f"player_id={player_id} is not a tournament entrant in tournament_id={tournament_id}")
    if entrant["status"] != "registered":
        raise ValueError(
            f"player_id={player_id} entrant status is {entrant['status']!r}; only registered entrants can be withdrawn"
        )
    game_count = _count_tournament_games_for_player(conn, tournament_id=tournament_id, player_id=player_id)
    if game_count > 0:
        raise ValueError(
            f"player_id={player_id} has {game_count} attached game(s) in tournament_id={tournament_id}; withdrawal refused"
        )
    fixtures = _load_player_fixtures(conn, tournament_id=tournament_id, player_id=player_id)
    played = [row for row in fixtures if row["status"] == "played" or int(row["game_count"]) > 0]
    if played:
        fixture_ids = ", ".join(str(int(row["id"])) for row in played)
        raise ValueError(
            f"player_id={player_id} is assigned to played fixture(s) {fixture_ids}; withdrawal refused"
        )
    scheduled = [row for row in fixtures if row["status"] == "scheduled"]
    return {
        "entrant": entrant,
        "scheduled_fixtures": scheduled,
        "stage_player_rows_removed": 0,
        "fixture_slots_cleared": 0,
    }


def withdraw_tournament_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    player_id: int,
    note: str | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    _require_eligible_generated_tournament(conn, tournament_id=tournament_id)
    plan = _validate_withdrawal_eligibility(conn, tournament_id=tournament_id, player_id=player_id)
    entrant = plan["entrant"]
    scheduled = plan["scheduled_fixtures"]
    updated_note = _append_entrant_note(entrant.get("note"), WITHDRAW_ENTRANT_ACTION, note)

    fixture_slots_cleared = 0
    for row in scheduled:
        fixture_id = int(row["id"])
        if int(row["player_a_id"] or 0) == player_id:
            if not dry_run:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE tournament_fixtures SET player_a_id = NULL WHERE id = %s",
                        (fixture_id,),
                    )
            fixture_slots_cleared += 1
        if int(row["player_b_id"] or 0) == player_id:
            if not dry_run:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE tournament_fixtures SET player_b_id = NULL WHERE id = %s",
                        (fixture_id,),
                    )
            fixture_slots_cleared += 1

    if not dry_run:
        with conn.cursor() as cur:
            cur.execute(
                """
                DELETE sp
                FROM tournament_stage_players sp
                INNER JOIN tournament_stages s ON s.id = sp.stage_id
                WHERE s.tournament_id = %s AND sp.player_id = %s
                """,
                (tournament_id, player_id),
            )
            stage_player_rows_removed = int(cur.rowcount)
            cur.execute(
                """
                UPDATE tournament_entrants
                SET status = 'withdrawn', note = %s
                WHERE tournament_id = %s AND player_id = %s
                """,
                (updated_note, tournament_id, player_id),
            )
    else:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*) AS n
                FROM tournament_stage_players sp
                INNER JOIN tournament_stages s ON s.id = sp.stage_id
                WHERE s.tournament_id = %s AND sp.player_id = %s
                """,
                (tournament_id, player_id),
            )
            stage_player_rows_removed = int(cur.fetchone()["n"])

    return {
        "dry_run": dry_run,
        "tournament_id": tournament_id,
        "player_id": player_id,
        "status": "withdrawn",
        "note": updated_note,
        "scheduled_fixtures_touched": len(scheduled),
        "fixture_slots_cleared": fixture_slots_cleared,
        "stage_player_rows_removed": stage_player_rows_removed,
    }


def _validate_replacement_eligibility(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    old_player_id: int,
    new_player_id: int,
) -> dict[str, Any]:
    if old_player_id == new_player_id:
        raise ValueError("old-player-id and new-player-id must differ")
    _require_player(conn, new_player_id)
    old_entrant = _load_entrant_row(conn, tournament_id=tournament_id, player_id=old_player_id)
    if old_entrant is None:
        raise ValueError(
            f"old_player_id={old_player_id} is not a tournament entrant in tournament_id={tournament_id}"
        )
    if old_entrant["status"] != "registered":
        raise ValueError(
            f"old_player_id={old_player_id} entrant status is {old_entrant['status']!r}; "
            "only registered entrants can be replaced"
        )
    if _load_entrant_row(conn, tournament_id=tournament_id, player_id=new_player_id) is not None:
        raise ValueError(
            f"new_player_id={new_player_id} is already a tournament entrant in tournament_id={tournament_id}"
        )
    game_count = _count_tournament_games_for_player(
        conn, tournament_id=tournament_id, player_id=old_player_id
    )
    if game_count > 0:
        raise ValueError(
            f"old_player_id={old_player_id} has {game_count} attached game(s) in tournament_id={tournament_id}; "
            "replacement refused"
        )
    fixtures = _load_player_fixtures(conn, tournament_id=tournament_id, player_id=old_player_id)
    blocked = [row for row in fixtures if row["status"] == "played" or int(row["game_count"]) > 0]
    if blocked:
        fixture_ids = ", ".join(str(int(row["id"])) for row in blocked)
        raise ValueError(
            f"old_player_id={old_player_id} is assigned to played fixture(s) {fixture_ids}; replacement refused"
        )
    scheduled = [row for row in fixtures if row["status"] == "scheduled"]
    return {
        "old_entrant": old_entrant,
        "scheduled_fixtures": scheduled,
    }


def replace_tournament_entrant(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    old_player_id: int,
    new_player_id: int,
    note: str | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    _require_eligible_generated_tournament(conn, tournament_id=tournament_id)
    plan = _validate_replacement_eligibility(
        conn,
        tournament_id=tournament_id,
        old_player_id=old_player_id,
        new_player_id=new_player_id,
    )
    old_entrant = plan["old_entrant"]
    scheduled = plan["scheduled_fixtures"]
    old_note = _append_entrant_note(old_entrant.get("note"), REPLACE_ENTRANT_ACTION, note)
    new_note = _append_entrant_note(None, REPLACE_ENTRANT_ACTION, note)
    seed_no = old_entrant.get("seed_no")

    fixture_slots_updated = 0
    for row in scheduled:
        fixture_id = int(row["id"])
        if int(row["player_a_id"] or 0) == old_player_id:
            if not dry_run:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE tournament_fixtures SET player_a_id = %s WHERE id = %s",
                        (new_player_id, fixture_id),
                    )
            fixture_slots_updated += 1
        if int(row["player_b_id"] or 0) == old_player_id:
            if not dry_run:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE tournament_fixtures SET player_b_id = %s WHERE id = %s",
                        (new_player_id, fixture_id),
                    )
            fixture_slots_updated += 1

    if not dry_run:
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE tournament_stage_players sp
                INNER JOIN tournament_stages s ON s.id = sp.stage_id
                SET sp.player_id = %s
                WHERE s.tournament_id = %s AND sp.player_id = %s
                """,
                (new_player_id, tournament_id, old_player_id),
            )
            stage_player_rows_updated = int(cur.rowcount)
            cur.execute(
                """
                UPDATE tournament_entrants
                SET status = 'replaced', note = %s
                WHERE tournament_id = %s AND player_id = %s
                """,
                (old_note, tournament_id, old_player_id),
            )
            cur.execute(
                """
                INSERT INTO tournament_entrants (tournament_id, player_id, seed_no, status, note)
                VALUES (%s, %s, %s, 'registered', %s)
                """,
                (tournament_id, new_player_id, seed_no, new_note),
            )
            cur.execute(
                "SELECT id FROM tournament_entrants WHERE tournament_id = %s AND player_id = %s",
                (tournament_id, new_player_id),
            )
            new_entrant_id = int(cur.fetchone()["id"])
    else:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*) AS n
                FROM tournament_stage_players sp
                INNER JOIN tournament_stages s ON s.id = sp.stage_id
                WHERE s.tournament_id = %s AND sp.player_id = %s
                """,
                (tournament_id, old_player_id),
            )
            stage_player_rows_updated = int(cur.fetchone()["n"])
        new_entrant_id = None

    return {
        "dry_run": dry_run,
        "tournament_id": tournament_id,
        "old_player_id": old_player_id,
        "new_player_id": new_player_id,
        "old_status": "replaced",
        "new_status": "registered",
        "seed_no": seed_no,
        "new_entrant_id": new_entrant_id,
        "scheduled_fixtures_touched": len(scheduled),
        "fixture_slots_updated": fixture_slots_updated,
        "stage_player_rows_updated": stage_player_rows_updated,
    }


def backfill_tournament_entrants(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    tournaments = _load_eligible_generated_tournaments(conn, tournament_id=tournament_id)
    summary: dict[str, Any] = {
        "dry_run": dry_run,
        "tournaments_scanned": len(tournaments),
        "tournaments_changed": 0,
        "entrants_inserted": 0,
        "details": [],
    }
    for row in tournaments:
        tid = int(row["id"])
        planned = plan_entrant_backfill(conn, tournament_id=tid)
        inserted = 0
        for player_id, seed_no in planned:
            if dry_run:
                inserted += 1
                continue
            if insert_entrant_if_missing(
                conn,
                tournament_id=tid,
                player_id=player_id,
                seed_no=seed_no,
                note=BACKFILL_ENTRANT_NOTE,
            ):
                inserted += 1
        if inserted > 0:
            summary["tournaments_changed"] += 1
        summary["entrants_inserted"] += inserted
        if planned:
            summary["details"].append(
                {
                    "tournament_id": tid,
                    "tournament_name": row.get("name"),
                    "planned": len(planned),
                    "inserted": inserted,
                    "players": [{"player_id": pid, "seed_no": seed} for pid, seed in planned],
                }
            )
    return summary


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
    if not _is_eligible_generated_tournament(row):
        if row.get("source_id") is not None:
            raise ValueError("refusing to delete imported Access tournament")
        raise ValueError("refusing to delete tournament not generated by approved fixture tooling")
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        game_count = int(cur.fetchone()["n"])
        if game_count > 0:
            raise ValueError(f"refusing to delete tournament with {game_count} game(s)")
        cur.execute("DELETE FROM tournaments WHERE id = %s", (tournament_id,))


def audit_entrant_integrity(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
) -> list[str]:
    errors: list[str] = []
    active_statuses = tuple(sorted(ACTIVE_ENTRANT_STATUSES))
    placeholders = ", ".join(["%s"] * len(active_statuses))
    tournament_clause = ""
    tournament_params: tuple[Any, ...] = ()
    if tournament_id is not None:
        _require_tournament(conn, tournament_id)
        tournament_clause = " AND s.tournament_id = %s"
        tournament_params = (tournament_id,)
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT s.tournament_id, sp.player_id
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            LEFT JOIN tournament_entrants e
              ON e.tournament_id = s.tournament_id
             AND e.player_id = sp.player_id
             AND e.status IN ({placeholders})
            WHERE e.id IS NULL{tournament_clause}
            ORDER BY s.tournament_id, sp.player_id
            """,
            (*active_statuses, *tournament_params),
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament {row['tournament_id']} stage player {row['player_id']} "
                "is not an active tournament entrant"
            )

        fixture_params = (*tournament_params, *active_statuses, *tournament_params, *active_statuses)
        cur.execute(
            f"""
            SELECT s.tournament_id, f.id AS fixture_id, f.player_a_id AS player_id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE f.player_a_id IS NOT NULL{tournament_clause}
              AND NOT EXISTS (
                SELECT 1
                FROM tournament_entrants e
                WHERE e.tournament_id = s.tournament_id
                  AND e.player_id = f.player_a_id
                  AND e.status IN ({placeholders})
              )
            UNION
            SELECT s.tournament_id, f.id AS fixture_id, f.player_b_id AS player_id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE f.player_b_id IS NOT NULL{tournament_clause}
              AND NOT EXISTS (
                SELECT 1
                FROM tournament_entrants e
                WHERE e.tournament_id = s.tournament_id
                  AND e.player_id = f.player_b_id
                  AND e.status IN ({placeholders})
              )
            ORDER BY tournament_id, fixture_id, player_id
            """,
            fixture_params,
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament {row['tournament_id']} fixture {row['fixture_id']} "
                f"player {row['player_id']} is not an active tournament entrant"
            )
    return errors


def audit_lifecycle_integrity(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
) -> list[str]:
    errors: list[str] = []
    tournament_clause = ""
    params: tuple[Any, ...] = ()
    if tournament_id is not None:
        _require_tournament(conn, tournament_id)
        tournament_clause = " WHERE t.id = %s"
        params = (tournament_id,)

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT t.id, t.lifecycle_status
            FROM tournaments t{tournament_clause}
            """,
            params,
        )
        for row in cur.fetchall():
            status = str(row["lifecycle_status"])
            if status not in VALID_LIFECYCLE_STATUSES:
                errors.append(
                    f"tournament {row['id']} lifecycle_status {status!r} is not a valid lifecycle status"
                )

        cur.execute(
            f"""
            SELECT t.id, t.lifecycle_status
            FROM tournaments t
            WHERE t.source_id IS NOT NULL
              AND t.lifecycle_status NOT IN ('completed', 'archived')
              {('AND t.id = %s' if tournament_id is not None else '')}
            ORDER BY t.id
            """,
            params,
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament {row['id']} is imported but lifecycle_status is {row['lifecycle_status']!r}; "
                "expected completed or archived"
            )

        cur.execute(
            f"""
            SELECT t.id, t.lifecycle_status
            FROM tournaments t
            WHERE t.source_id IS NULL
              AND t.lifecycle_status IN ('draft', 'registration', 'ready')
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
              {('AND t.id = %s' if tournament_id is not None else '')}
            ORDER BY t.id
            """,
            params,
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament {row['id']} has games but lifecycle_status is {row['lifecycle_status']!r}; "
                "expected running or completed"
            )

        cur.execute(
            f"""
            SELECT t.id, t.lifecycle_status, COUNT(f.id) AS unplayed
            FROM tournaments t
            INNER JOIN tournament_stages s ON s.tournament_id = t.id
            INNER JOIN tournament_fixtures f ON f.stage_id = s.id
            WHERE t.lifecycle_status IN ('completed', 'archived')
              AND f.status = 'scheduled'
              {('AND t.id = %s' if tournament_id is not None else '')}
            GROUP BY t.id, t.lifecycle_status
            ORDER BY t.id
            """,
            params,
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament {row['id']} lifecycle_status is {row['lifecycle_status']!r} "
                f"but has {int(row['unplayed'])} scheduled fixture(s)"
            )

    return errors


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
        for table in ("tournament_entrants", "tournament_stages", "tournament_stage_players", "tournament_fixtures"):
            cur.execute(f"SELECT COUNT(*) AS n FROM {table}")
            print(f"{table}={int(cur.fetchone()['n'])}")
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id IS NOT NULL")
        print(f"fixture_backed_games={int(cur.fetchone()['n'])}")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Internal Amiga tournament stage/fixture operations")
    sub = parser.add_subparsers(dest="cmd", required=True)

    sub.add_parser("verify", help="Verify fixture-backed game integrity")

    p_verify_entrants = sub.add_parser("verify-entrants", help="Verify tournament entrant integrity")
    p_verify_entrants.add_argument("--tournament-id", type=int, default=None)

    p_verify_lifecycle = sub.add_parser("verify-lifecycle", help="Verify tournament lifecycle integrity")
    p_verify_lifecycle.add_argument("--tournament-id", type=int, default=None)

    p_set_status = sub.add_parser("set-tournament-status", help="Transition tournament lifecycle status")
    p_set_status.add_argument("--tournament-id", type=int, required=True)
    p_set_status.add_argument("--status", choices=sorted(VALID_LIFECYCLE_STATUSES), required=True)
    p_set_status.add_argument("--dry-run", action="store_true")
    p_set_status.add_argument("--force", action="store_true")

    p_list_entrants = sub.add_parser("list-entrants", help="List entrants for one tournament")
    p_list_entrants.add_argument("--tournament-id", type=int, required=True)
    p_list_entrants.add_argument("--status", choices=sorted(VALID_ENTRANT_STATUSES), default=None)
    p_list_entrants.add_argument("--limit", type=int, default=500)

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

    p_backfill = sub.add_parser(
        "backfill-entrants",
        help="Backfill missing tournament_entrants for generated fixture-backed tournaments",
    )
    p_backfill.add_argument("--tournament-id", type=int, default=None)
    p_backfill.add_argument("--dry-run", action="store_true")

    p_withdraw = sub.add_parser("withdraw-entrant", help="Withdraw a registered entrant from a generated tournament")
    p_withdraw.add_argument("--tournament-id", type=int, required=True)
    p_withdraw.add_argument("--player-id", type=int, required=True)
    p_withdraw.add_argument("--note", default=None)
    p_withdraw.add_argument("--dry-run", action="store_true")

    p_replace = sub.add_parser("replace-entrant", help="Replace a registered entrant in a generated tournament")
    p_replace.add_argument("--tournament-id", type=int, required=True)
    p_replace.add_argument("--old-player-id", type=int, required=True)
    p_replace.add_argument("--new-player-id", type=int, required=True)
    p_replace.add_argument("--note", default=None)
    p_replace.add_argument("--dry-run", action="store_true")

    p_add_entrant = sub.add_parser(
        "add-entrant",
        help="Register an existing player as a tournament entrant (generated tournaments only)",
    )
    p_add_entrant.add_argument("--tournament-id", type=int, required=True)
    p_add_entrant.add_argument("--player-id", type=int, required=True)
    p_add_entrant.add_argument("--seed-no", type=int, default=None)
    p_add_entrant.add_argument("--note", default=None)
    p_add_entrant.add_argument("--dry-run", action="store_true")

    p_onboard = sub.add_parser(
        "onboard-newcomer",
        help="Create a newcomer and register them as a tournament entrant in one atomic operation",
    )
    p_onboard.add_argument("--tournament-id", type=int, required=True)
    p_onboard.add_argument("--name", default=None, help="Explicit canonical KOA display name")
    p_onboard.add_argument("--full-name", default=None, help="Suggest first available KOA-style name")
    p_onboard.add_argument("--country", default="")
    p_onboard.add_argument("--seed-no", type=int, default=None)
    p_onboard.add_argument("--note", default=None)
    p_onboard.add_argument("--dry-run", action="store_true")

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

        if args.cmd == "verify-entrants":
            errors = audit_entrant_integrity(conn, tournament_id=args.tournament_id)
            _print_counts(conn)
            if errors:
                for error in errors:
                    print(f"FAIL: {error}", file=sys.stderr)
                return 1
            print("OK: tournament entrant integrity checks passed")
            return 0

        if args.cmd == "verify-lifecycle":
            errors = audit_lifecycle_integrity(conn, tournament_id=args.tournament_id)
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT lifecycle_status, COUNT(*) AS n
                    FROM tournaments
                    GROUP BY lifecycle_status
                    ORDER BY lifecycle_status
                    """
                )
                for row in cur.fetchall():
                    print(f"lifecycle_status={row['lifecycle_status']} count={int(row['n'])}")
            if errors:
                for error in errors:
                    print(f"FAIL: {error}", file=sys.stderr)
                return 1
            print("OK: tournament lifecycle integrity checks passed")
            return 0

        if args.cmd == "set-tournament-status":
            summary = set_tournament_lifecycle_status(
                conn,
                tournament_id=args.tournament_id,
                status=args.status,
                dry_run=args.dry_run,
                force=args.force,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                f"tournament_id={summary['tournament_id']} "
                f"previous_status={summary['previous_status']} "
                f"lifecycle_status={summary['lifecycle_status']} "
                f"changed={summary['changed']}"
            )
            if summary.get("started_at") is not None:
                print(f"started_at={summary['started_at']}")
            if summary.get("completed_at") is not None:
                print(f"completed_at={summary['completed_at']}")
            if summary.get("unplayed_scheduled_fixtures"):
                print(f"unplayed_scheduled_fixtures={summary['unplayed_scheduled_fixtures']}")
            return 0

        if args.cmd == "list-entrants":
            rows = list_entrants(
                conn,
                tournament_id=args.tournament_id,
                status=args.status,
                limit=args.limit,
            )
            for row in rows:
                seed = f" seed={row['seed_no']}" if row.get("seed_no") is not None else ""
                note = f" note={row['note']!r}" if row.get("note") else ""
                print(
                    f"entrant_id={row['id']} player_id={row['player_id']} "
                    f"name={row['player_name']} status={row['status']}{seed}{note}"
                )
            print(f"entrants={len(rows)}")
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

        if args.cmd == "backfill-entrants":
            summary = backfill_tournament_entrants(
                conn,
                tournament_id=args.tournament_id,
                dry_run=args.dry_run,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                f"tournaments_scanned={summary['tournaments_scanned']} "
                f"tournaments_changed={summary['tournaments_changed']} "
                f"entrants_inserted={summary['entrants_inserted']}"
            )
            for detail in summary["details"]:
                print(
                    f"tournament_id={detail['tournament_id']} "
                    f"name={detail['tournament_name']!r} "
                    f"planned={detail['planned']} inserted={detail['inserted']}"
                )
                for player in detail["players"]:
                    seed = f" seed={player['seed_no']}" if player["seed_no"] is not None else ""
                    print(f"  player_id={player['player_id']}{seed}")
            return 0

        if args.cmd == "withdraw-entrant":
            summary = withdraw_tournament_entrant(
                conn,
                tournament_id=args.tournament_id,
                player_id=args.player_id,
                note=args.note,
                dry_run=args.dry_run,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                f"tournament_id={summary['tournament_id']} player_id={summary['player_id']} "
                f"status={summary['status']} scheduled_fixtures_touched={summary['scheduled_fixtures_touched']} "
                f"fixture_slots_cleared={summary['fixture_slots_cleared']} "
                f"stage_player_rows_removed={summary['stage_player_rows_removed']}"
            )
            print(f"note={summary['note']!r}")
            return 0

        if args.cmd == "replace-entrant":
            summary = replace_tournament_entrant(
                conn,
                tournament_id=args.tournament_id,
                old_player_id=args.old_player_id,
                new_player_id=args.new_player_id,
                note=args.note,
                dry_run=args.dry_run,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                f"tournament_id={summary['tournament_id']} old_player_id={summary['old_player_id']} "
                f"new_player_id={summary['new_player_id']} old_status={summary['old_status']} "
                f"new_status={summary['new_status']} seed_no={summary['seed_no']} "
                f"new_entrant_id={summary['new_entrant_id']} "
                f"scheduled_fixtures_touched={summary['scheduled_fixtures_touched']} "
                f"fixture_slots_updated={summary['fixture_slots_updated']} "
                f"stage_player_rows_updated={summary['stage_player_rows_updated']}"
            )
            return 0

        if args.cmd == "add-entrant":
            summary = register_tournament_entrant(
                conn,
                tournament_id=args.tournament_id,
                player_id=args.player_id,
                seed_no=args.seed_no,
                note=args.note,
                dry_run=args.dry_run,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            seed = f" seed_no={summary['seed_no']}" if summary.get("seed_no") is not None else ""
            note = f" note={summary['note']!r}" if summary.get("note") else ""
            print(
                f"tournament_id={summary['tournament_id']} player_id={summary['player_id']} "
                f"entrant_id={summary['entrant_id']} status={summary['status']}{seed}{note}"
            )
            return 0

        if args.cmd == "onboard-newcomer":
            summary = onboard_newcomer_entrant(
                conn,
                tournament_id=args.tournament_id,
                name=args.name,
                full_name=args.full_name,
                country=args.country,
                seed_no=args.seed_no,
                note=args.note,
                dry_run=args.dry_run,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            seed = f" seed_no={summary['seed_no']}" if summary.get("seed_no") is not None else ""
            note = f" note={summary['note']!r}" if summary.get("note") else ""
            print(
                f"tournament_id={summary['tournament_id']} name_source={summary['name_source']} "
                f"resolved_name={summary['resolved_name']!r} player_id={summary['player_id']} "
                f"entrant_id={summary['entrant_id']} status={summary['status']}{seed}{note}"
            )
            if summary.get("suggestion"):
                print(json.dumps(summary["suggestion"], indent=2, sort_keys=True))
            return 0
    except ValueError as exc:
        conn.rollback()
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    finally:
        conn.close()

    return 1
