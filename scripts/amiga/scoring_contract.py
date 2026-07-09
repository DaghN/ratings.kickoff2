"""L4b scoring contract — platform_default_v1 preset and copy-on-create helpers."""

from __future__ import annotations

from typing import Final

import pymysql

PLATFORM_DEFAULT_V1: Final[str] = "platform_default_v1"
SCORING_SCHEMA_VERSION: Final[int] = 1

WIN_POINTS: Final[int] = 3
DRAW_POINTS: Final[int] = 1
LOSS_POINTS: Final[int] = 0

LEAGUE_TABLE_PRIMITIVE: Final[str] = "league_table"
KNOCKOUT_TIE_PRIMITIVE: Final[str] = "knockout_tie"

LEAGUE_TABLE_DEFAULT_STEPS: Final[tuple[str, ...]] = (
    "points",
    "goal_difference",
    "goals_for",
    "games_played",
)

KNOCKOUT_TIE_DEFAULT_STEPS: Final[tuple[str, ...]] = (
    "aggregate_goal_difference",
    "extra_time",
    "penalty_shootout",
)

VALID_STAGE_TYPES: Final[frozenset[str]] = frozenset({"round_robin", "knockout"})


def primitive_for_stage_type(stage_type: str) -> str:
    if stage_type == "round_robin":
        return LEAGUE_TABLE_PRIMITIVE
    if stage_type == "knockout":
        return KNOCKOUT_TIE_PRIMITIVE
    raise ValueError(f"unsupported stage_type for scoring contract: {stage_type!r}")


def default_steps_for_primitive(primitive: str) -> tuple[str, ...]:
    if primitive == LEAGUE_TABLE_PRIMITIVE:
        return LEAGUE_TABLE_DEFAULT_STEPS
    if primitive == KNOCKOUT_TIE_PRIMITIVE:
        return KNOCKOUT_TIE_DEFAULT_STEPS
    raise ValueError(f"unsupported scoring primitive: {primitive!r}")


def ensure_tournament_scoring_defaults(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> bool:
    """Write platform_default_v1 point defaults on tournament when still NULL."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT scoring_win_points_default
            FROM tournaments
            WHERE id = %s
            """,
            (tournament_id,),
        )
        row = cur.fetchone()
        if row is None:
            raise ValueError(f"tournament_id={tournament_id} not found")
        if row["scoring_win_points_default"] is not None:
            return False
        cur.execute(
            """
            UPDATE tournaments
            SET scoring_win_points_default = %s,
                scoring_draw_points_default = %s,
                scoring_loss_points_default = %s
            WHERE id = %s
            """,
            (WIN_POINTS, DRAW_POINTS, LOSS_POINTS, tournament_id),
        )
    return True


def ensure_stage_scoring_contract(
    conn: pymysql.connections.Connection,
    stage_id: int,
    *,
    stage_type: str | None = None,
) -> bool:
    """Copy platform_default_v1 onto stage when scoring_primitive IS NULL."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT tournament_id, stage_type, scoring_primitive
            FROM tournament_stages
            WHERE id = %s
            """,
            (stage_id,),
        )
        row = cur.fetchone()
        if row is None:
            raise ValueError(f"stage_id={stage_id} not found")
        if row["scoring_primitive"] is not None:
            return False

        resolved_type = stage_type or str(row["stage_type"])
        if resolved_type not in VALID_STAGE_TYPES:
            raise ValueError(f"unsupported stage_type for scoring contract: {resolved_type!r}")

        tournament_id = int(row["tournament_id"])
        primitive = primitive_for_stage_type(resolved_type)
        steps = default_steps_for_primitive(primitive)

        ensure_tournament_scoring_defaults(conn, tournament_id)

        cur.execute(
            """
            UPDATE tournament_stages
            SET scoring_primitive = %s,
                scoring_schema_version = %s,
                scoring_win_points = %s,
                scoring_draw_points = %s,
                scoring_loss_points = %s
            WHERE id = %s
              AND scoring_primitive IS NULL
            """,
            (primitive, SCORING_SCHEMA_VERSION, WIN_POINTS, DRAW_POINTS, LOSS_POINTS, stage_id),
        )
        if cur.rowcount == 0:
            return False

        for sequence_no, step in enumerate(steps, start=1):
            cur.execute(
                """
                INSERT INTO tournament_stage_scoring_steps (stage_id, sequence_no, step)
                VALUES (%s, %s, %s)
                """,
                (stage_id, sequence_no, step),
            )
    return True
