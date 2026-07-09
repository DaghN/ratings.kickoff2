"""L4b scoring contract — platform_default_v1 preset and copy-on-create helpers."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Final

import pymysql

PLATFORM_DEFAULT_V1: Final[str] = "platform_default_v1"
SCORING_SCHEMA_VERSION: Final[int] = 1
KNOWN_SCORING_SCHEMA_VERSIONS: Final[frozenset[int]] = frozenset({SCORING_SCHEMA_VERSION})

WIN_POINTS: Final[int] = 3
DRAW_POINTS: Final[int] = 1
LOSS_POINTS: Final[int] = 0

LEAGUE_TABLE_PRIMITIVE: Final[str] = "league_table"
KNOCKOUT_TIE_PRIMITIVE: Final[str] = "knockout_tie"

VALID_PRIMITIVES: Final[frozenset[str]] = frozenset({LEAGUE_TABLE_PRIMITIVE, KNOCKOUT_TIE_PRIMITIVE})

LEAGUE_TABLE_STEPS: Final[frozenset[str]] = frozenset({
    "points",
    "head_to_head",
    "goal_difference",
    "goals_for",
    "games_played",
})

KNOCKOUT_TIE_STEPS: Final[frozenset[str]] = frozenset({
    "aggregate_goal_difference",
    "extra_time",
    "penalty_shootout",
    "golden_goal",
})

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

# Executor-only KO chain for legacy catalog (NULL DB contract). Preserves GD → GF → pens
# parity until SC-6 backfill; not stored in platform_default_v1 (policy omits GF step).
LEGACY_KNOCKOUT_BRIDGE_STEPS: Final[tuple[str, ...]] = (
    "aggregate_goal_difference",
    "goals_for",
    "penalty_shootout",
)

VALID_STAGE_TYPES: Final[frozenset[str]] = frozenset({"round_robin", "knockout"})


@dataclass(frozen=True, slots=True)
class StageScoringContract:
    """Runtime L4b contract for one stage (D17 reader shape v1)."""

    stage_id: int
    tournament_id: int
    stage_key: str
    stage_type: str
    primitive: str
    schema_version: int
    win_points: int
    draw_points: int
    loss_points: int
    steps: tuple[str, ...]


def allowed_steps_for_primitive(primitive: str) -> frozenset[str]:
    if primitive == LEAGUE_TABLE_PRIMITIVE:
        return LEAGUE_TABLE_STEPS
    if primitive == KNOCKOUT_TIE_PRIMITIVE:
        return KNOCKOUT_TIE_STEPS
    raise ValueError(f"unsupported scoring primitive: {primitive!r}")


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


def load_stage_scoring_steps(
    conn: pymysql.connections.Connection,
    stage_id: int,
) -> tuple[str, ...]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT sequence_no, step
            FROM tournament_stage_scoring_steps
            WHERE stage_id = %s
            ORDER BY sequence_no ASC
            """,
            (stage_id,),
        )
        rows = cur.fetchall()
    steps: list[str] = []
    expected_seq = 1
    for row in rows:
        seq = int(row["sequence_no"])
        if seq != expected_seq:
            raise ValueError(
                f"stage_id={stage_id}: scoring step sequence gap at {expected_seq} (got {seq})"
            )
        steps.append(str(row["step"]))
        expected_seq += 1
    return tuple(steps)


def load_stage_scoring_contract(
    conn: pymysql.connections.Connection,
    stage_id: int,
) -> StageScoringContract | None:
    """Return contract when scoring_primitive is set; None when stage has no contract yet."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, tournament_id, stage_key, stage_type, scoring_primitive,
                   scoring_schema_version, scoring_win_points, scoring_draw_points,
                   scoring_loss_points
            FROM tournament_stages
            WHERE id = %s
            """,
            (stage_id,),
        )
        row = cur.fetchone()
    if row is None:
        raise ValueError(f"stage_id={stage_id} not found")
    if row["scoring_primitive"] is None:
        return None

    missing = [
        name
        for name, val in (
            ("scoring_schema_version", row["scoring_schema_version"]),
            ("scoring_win_points", row["scoring_win_points"]),
            ("scoring_draw_points", row["scoring_draw_points"]),
            ("scoring_loss_points", row["scoring_loss_points"]),
        )
        if val is None
    ]
    if missing:
        raise ValueError(
            f"stage_id={stage_id}: scoring_primitive set but missing {', '.join(missing)}"
        )

    steps = load_stage_scoring_steps(conn, stage_id)
    return StageScoringContract(
        stage_id=int(row["id"]),
        tournament_id=int(row["tournament_id"]),
        stage_key=str(row["stage_key"]),
        stage_type=str(row["stage_type"]),
        primitive=str(row["scoring_primitive"]),
        schema_version=int(row["scoring_schema_version"]),
        win_points=int(row["scoring_win_points"]),
        draw_points=int(row["scoring_draw_points"]),
        loss_points=int(row["scoring_loss_points"]),
        steps=steps,
    )


def validate_stage_scoring_contract(contract: StageScoringContract) -> list[str]:
    """Structural L4b checks only — not standings numeric parity."""
    errors: list[str] = []
    label = (
        f"stage_id={contract.stage_id} tournament_id={contract.tournament_id} "
        f"stage_key={contract.stage_key!r}"
    )

    if contract.primitive not in VALID_PRIMITIVES:
        errors.append(f"{label}: unknown primitive {contract.primitive!r}")

    if contract.schema_version not in KNOWN_SCORING_SCHEMA_VERSIONS:
        errors.append(f"{label}: unknown scoring_schema_version={contract.schema_version}")

    if contract.stage_type in VALID_STAGE_TYPES:
        try:
            expected_primitive = primitive_for_stage_type(contract.stage_type)
            if contract.primitive != expected_primitive:
                errors.append(
                    f"{label}: primitive {contract.primitive!r} does not match "
                    f"stage_type {contract.stage_type!r} (expected {expected_primitive!r})"
                )
        except ValueError:
            pass

    for field_name, value in (
        ("scoring_win_points", contract.win_points),
        ("scoring_draw_points", contract.draw_points),
        ("scoring_loss_points", contract.loss_points),
    ):
        if value < 0 or value > 255:
            errors.append(f"{label}: invalid {field_name}={value}")

    if not contract.steps:
        errors.append(f"{label}: scoring step chain is empty")

    if contract.primitive in VALID_PRIMITIVES:
        allowed = allowed_steps_for_primitive(contract.primitive)
        for step in contract.steps:
            if step not in allowed:
                errors.append(f"{label}: step {step!r} not allowed for primitive {contract.primitive!r}")

    if contract.primitive == LEAGUE_TABLE_PRIMITIVE and "points" not in contract.steps:
        errors.append(f"{label}: league_table contract must include points step")

    if contract.primitive == KNOCKOUT_TIE_PRIMITIVE and "aggregate_goal_difference" not in contract.steps:
        errors.append(f"{label}: knockout_tie contract must include aggregate_goal_difference step")

    return errors


def synthetic_league_contract(
    *,
    tournament_id: int,
    stage_id: int = 0,
    stage_key: str = "",
    stage_type: str = "round_robin",
    win_points: int = WIN_POINTS,
    draw_points: int = DRAW_POINTS,
    loss_points: int = LOSS_POINTS,
) -> StageScoringContract:
    return StageScoringContract(
        stage_id=stage_id,
        tournament_id=tournament_id,
        stage_key=stage_key,
        stage_type=stage_type,
        primitive=LEAGUE_TABLE_PRIMITIVE,
        schema_version=SCORING_SCHEMA_VERSION,
        win_points=win_points,
        draw_points=draw_points,
        loss_points=loss_points,
        steps=LEAGUE_TABLE_DEFAULT_STEPS,
    )


def synthetic_knockout_contract(
    *,
    tournament_id: int,
    stage_id: int = 0,
    stage_key: str = "",
    win_points: int = WIN_POINTS,
    draw_points: int = DRAW_POINTS,
    loss_points: int = LOSS_POINTS,
    steps: tuple[str, ...] | None = None,
) -> StageScoringContract:
    return StageScoringContract(
        stage_id=stage_id,
        tournament_id=tournament_id,
        stage_key=stage_key,
        stage_type="knockout",
        primitive=KNOCKOUT_TIE_PRIMITIVE,
        schema_version=SCORING_SCHEMA_VERSION,
        win_points=win_points,
        draw_points=draw_points,
        loss_points=loss_points,
        steps=steps or LEGACY_KNOCKOUT_BRIDGE_STEPS,
    )


@dataclass(frozen=True, slots=True)
class ScoringContext:
    """Resolved L4b contracts for one tournament standings compute pass."""

    default_league: StageScoringContract
    default_knockout: StageScoringContract
    by_stage_id: dict[int, StageScoringContract]

    def contract_for_game(self, game: dict[str, Any], *, is_elimination: bool) -> StageScoringContract:
        raw_stage_id = game.get("stage_id")
        if raw_stage_id is not None:
            stage_id = int(raw_stage_id)
            if stage_id in self.by_stage_id:
                return self.by_stage_id[stage_id]
        return self.default_knockout if is_elimination else self.default_league


def _tournament_point_defaults(row: dict[str, Any] | None) -> tuple[int, int, int]:
    if row is None:
        return WIN_POINTS, DRAW_POINTS, LOSS_POINTS
    win = row.get("scoring_win_points_default")
    draw = row.get("scoring_draw_points_default")
    loss = row.get("scoring_loss_points_default")
    return (
        int(win) if win is not None else WIN_POINTS,
        int(draw) if draw is not None else DRAW_POINTS,
        int(loss) if loss is not None else LOSS_POINTS,
    )


def load_scoring_context_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> ScoringContext:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT scoring_win_points_default, scoring_draw_points_default, scoring_loss_points_default
            FROM tournaments
            WHERE id = %s
            """,
            (tournament_id,),
        )
        tour_row = cur.fetchone()
        cur.execute(
            """
            SELECT id, stage_key, stage_type
            FROM tournament_stages
            WHERE tournament_id = %s
            ORDER BY id
            """,
            (tournament_id,),
        )
        stage_rows = cur.fetchall()

    win_pts, draw_pts, loss_pts = _tournament_point_defaults(tour_row)
    default_league = synthetic_league_contract(
        tournament_id=tournament_id,
        win_points=win_pts,
        draw_points=draw_pts,
        loss_points=loss_pts,
    )
    default_knockout = synthetic_knockout_contract(
        tournament_id=tournament_id,
        win_points=win_pts,
        draw_points=draw_pts,
        loss_points=loss_pts,
        steps=LEGACY_KNOCKOUT_BRIDGE_STEPS,
    )

    by_stage_id: dict[int, StageScoringContract] = {}
    for row in stage_rows:
        stage_id = int(row["id"])
        stage_type = str(row["stage_type"])
        loaded = load_stage_scoring_contract(conn, stage_id)
        if loaded is not None:
            by_stage_id[stage_id] = loaded
            continue
        if stage_type == "knockout":
            by_stage_id[stage_id] = synthetic_knockout_contract(
                tournament_id=tournament_id,
                stage_id=stage_id,
                stage_key=str(row["stage_key"]),
                win_points=win_pts,
                draw_points=draw_pts,
                loss_points=loss_pts,
                steps=LEGACY_KNOCKOUT_BRIDGE_STEPS,
            )
        else:
            by_stage_id[stage_id] = synthetic_league_contract(
                tournament_id=tournament_id,
                stage_id=stage_id,
                stage_key=str(row["stage_key"]),
                stage_type=stage_type,
                win_points=win_pts,
                draw_points=draw_pts,
                loss_points=loss_pts,
            )

    return ScoringContext(
        default_league=default_league,
        default_knockout=default_knockout,
        by_stage_id=by_stage_id,
    )


def default_scoring_context(tournament_id: int = 0) -> ScoringContext:
    """In-memory bridge when DB context is unavailable (parity CLI, unit tests)."""
    league = synthetic_league_contract(tournament_id=tournament_id)
    knockout = synthetic_knockout_contract(tournament_id=tournament_id)
    return ScoringContext(
        default_league=league,
        default_knockout=knockout,
        by_stage_id={},
    )


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
