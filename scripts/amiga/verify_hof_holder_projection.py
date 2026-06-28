#!/usr/bin/env python3
"""Verify HoF holder projection vs independent oracles (Phase B stored-field semantics)."""

from __future__ import annotations

import sys
from datetime import date, datetime
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import (
    RATIO_LEADER_ID_COLUMNS,
    RATIO_LEADER_NAME_COLUMNS,
    RATIO_LEADER_VALUE_COLUMNS,
    RECORD_HOLDER_GAME_ID_COLUMNS,
)
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id, load_realm_cutoff
from scripts.amiga.realm_incremental import (
    _CAREER_ROW_PREFIXES,
    _holder_record_date,
    _ratio_leaders_from_player_rows,
    _career_holders_from_player_rows,
)
from scripts.amiga.server_records import (
    _biggest_draw_sum_patch,
    _biggest_sum_goals_patch,
    _biggest_win_margin_patch,
    _load_cutoff_player_rows,
    _most_goals_one_game_patch,
    compute_ratio_leader_patch,
)

_SINGLE_GAME_PREFIXES: tuple[str, ...] = (
    "MostGoalsScoredInOneGame",
    "BiggestWinDifference",
)

_PAIR_GAME_PREFIXES: tuple[str, ...] = (
    "BiggestDrawSum",
    "BiggestSumOfGoals",
)

_SINGLE_GAME_FIELDS: dict[str, tuple[str, ...]] = {
    prefix: (prefix, f"{prefix}ID", f"{prefix}Name", f"{prefix}Date")
    for prefix in _SINGLE_GAME_PREFIXES
}
_SINGLE_GAME_FIELDS["MostGoalsScoredInOneGame"] += ("MostGoalsScoredInOneGameGameID",)
_SINGLE_GAME_FIELDS["BiggestWinDifference"] += ("BiggestWinDifferenceGameID",)

_PAIR_GAME_FIELDS: dict[str, tuple[str, ...]] = {
    "BiggestDrawSum": (
        "BiggestDrawSum",
        "BiggestDrawSumIDA",
        "BiggestDrawSumIDB",
        "BiggestDrawSumNameA",
        "BiggestDrawSumNameB",
        "BiggestDrawSumDate",
        "BiggestDrawSumGameID",
    ),
    "BiggestSumOfGoals": (
        "BiggestSumOfGoals",
        "BiggestSumOfGoalsIDA",
        "BiggestSumOfGoalsIDB",
        "BiggestSumOfGoalsNameA",
        "BiggestSumOfGoalsNameB",
        "BiggestSumOfGoalsDate",
        "BiggestSumOfGoalsGameID",
    ),
}

_GAME_ID_TO_DATE_PREFIX: dict[str, str] = {
    col: col.removesuffix("GameID")
    for col in RECORD_HOLDER_GAME_ID_COLUMNS
}


def _norm_date(value: Any) -> str | None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.strftime("%Y-%m-%d")
    if isinstance(value, date):
        return value.isoformat()
    text = str(value).strip()
    if not text:
        return None
    return text[:10]


def _norm_scalar(value: Any) -> Any:
    if value is None:
        return None
    if isinstance(value, (int, float)):
        return float(value)
    return value


def _values_equal(stored: Any, expected: Any, *, float_field: bool = False) -> bool:
    if stored is None and expected is None:
        return True
    if float_field:
        try:
            return float(stored) == float(expected)
        except (TypeError, ValueError):
            return False
    return stored == expected


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _game_oracle_patch(
    conn: pymysql.connections.Connection,
    cutoff: Any,
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    patch.update(_most_goals_one_game_patch(conn, cutoff=cutoff))
    patch.update(_biggest_win_margin_patch(conn, cutoff=cutoff))
    patch.update(_biggest_draw_sum_patch(conn, cutoff=cutoff))
    patch.update(_biggest_sum_goals_patch(conn, cutoff=cutoff))
    return patch


def _game_event_date_for_id(conn: pymysql.connections.Connection, game_id: int) -> str | None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COALESCE(
                DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'),
                DATE_FORMAT(g.game_date, '%%Y-%%m-%%d')
            ) AS record_date
            FROM amiga_games g
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE g.id = %s
            LIMIT 1
            """,
            (game_id,),
        )
        row = cur.fetchone()
    if not row:
        return None
    return _norm_date(row.get("record_date"))


def _compare_fields(
    errors: list[str],
    *,
    label: str,
    stored: dict[str, Any],
    oracle: dict[str, Any],
    fields: tuple[str, ...],
    float_fields: frozenset[str] | None = None,
) -> None:
    float_fields = float_fields or frozenset()
    for key in fields:
        stored_val = stored.get(key)
        oracle_val = oracle.get(key)
        if not _values_equal(stored_val, oracle_val, float_field=key in float_fields):
            errors.append(
                f"{label} {key}: stored={stored_val!r} oracle={oracle_val!r}"
            )


def verify_hof_holder_projection(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    latest_tid = latest_finalized_tournament_id(conn)
    if latest_tid is None:
        return errors

    cutoff = load_realm_cutoff(conn, latest_tid)
    player_rows = _load_cutoff_player_rows(conn, cutoff)
    row_by_id = {int(row["player_id"]): row for row in player_rows}

    career_oracle = _career_holders_from_player_rows(player_rows)
    game_oracle = _game_oracle_patch(conn, cutoff)
    ratio_row_oracle = _ratio_leaders_from_player_rows(player_rows)
    ratio_sql_oracle = compute_ratio_leader_patch(conn, as_of_tournament_id=latest_tid)

    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1 LIMIT 1")
        gst = cur.fetchone() or {}
        cur.execute(
            """
            SELECT * FROM amiga_realm_snapshots
            ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC
            LIMIT 1
            """
        )
        latest_realm = cur.fetchone()

    float_holder_fields = frozenset({"BiggestRatingAscent"})

    for _value_col, prefix in _CAREER_ROW_PREFIXES:
        fields = (prefix, f"{prefix}ID", f"{prefix}Name", f"{prefix}Date")
        _compare_fields(
            errors,
            label=f"career {prefix}",
            stored=gst,
            oracle=career_oracle,
            fields=fields,
            float_fields=float_holder_fields,
        )
        if latest_realm:
            _compare_fields(
                errors,
                label=f"realm career {prefix}",
                stored=latest_realm,
                oracle=career_oracle,
                fields=fields,
                float_fields=float_holder_fields,
            )

        holder_id = int(gst.get(f"{prefix}ID") or 0)
        if holder_id and holder_id in row_by_id:
            expected_date = _holder_record_date(prefix, row_by_id[holder_id])
            if _norm_date(gst.get(f"{prefix}Date")) != _norm_date(expected_date):
                errors.append(
                    f"{prefix}Date source-field: gst={gst.get(f'{prefix}Date')!r} "
                    f"expected _holder_record_date={expected_date!r}"
                )

    for prefix in _SINGLE_GAME_PREFIXES:
        fields = _SINGLE_GAME_FIELDS[prefix]
        _compare_fields(
            errors,
            label=f"game {prefix}",
            stored=gst,
            oracle=game_oracle,
            fields=fields,
            float_fields=float_holder_fields,
        )
        if latest_realm:
            _compare_fields(
                errors,
                label=f"realm game {prefix}",
                stored=latest_realm,
                oracle=game_oracle,
                fields=fields,
                float_fields=float_holder_fields,
            )

    for prefix in _PAIR_GAME_PREFIXES:
        fields = _PAIR_GAME_FIELDS[prefix]
        _compare_fields(
            errors,
            label=f"game {prefix}",
            stored=gst,
            oracle=game_oracle,
            fields=fields,
        )
        if latest_realm:
            _compare_fields(
                errors,
                label=f"realm game {prefix}",
                stored=latest_realm,
                oracle=game_oracle,
                fields=fields,
            )

    for game_id_col in RECORD_HOLDER_GAME_ID_COLUMNS:
        game_id = gst.get(game_id_col)
        if game_id is None:
            continue
        game_id = int(game_id)
        prefix = _GAME_ID_TO_DATE_PREFIX[game_id_col]
        date_key = f"{prefix}Date"
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM amiga_games WHERE id = %s LIMIT 1", (game_id,))
            if not cur.fetchone():
                errors.append(f"{game_id_col}={game_id}: game row missing")
                continue
        expected_date = _game_event_date_for_id(conn, game_id)
        if _norm_date(gst.get(date_key)) != expected_date:
            errors.append(
                f"{date_key} game-anchor: gst={gst.get(date_key)!r} "
                f"expected from game_id={game_id}: {expected_date!r}"
            )

    ratio_fields = (
        RATIO_LEADER_VALUE_COLUMNS
        + RATIO_LEADER_ID_COLUMNS
        + RATIO_LEADER_NAME_COLUMNS
    )
    ratio_value_fields = frozenset(RATIO_LEADER_VALUE_COLUMNS)
    for key in ratio_fields:
        float_field = key in ratio_value_fields
        stored_val = gst.get(key)
        row_val = ratio_row_oracle.get(key)
        sql_val = ratio_sql_oracle.get(key)
        if not _values_equal(stored_val, row_val, float_field=float_field):
            errors.append(
                f"ratio {key}: gst={stored_val!r} player-row oracle={row_val!r}"
            )
        if not _values_equal(row_val, sql_val, float_field=float_field):
            errors.append(
                f"ratio {key}: player-row oracle={row_val!r} sql oracle={sql_val!r}"
            )
        if latest_realm and not _values_equal(
            latest_realm.get(key), row_val, float_field=float_field
        ):
            errors.append(
                f"realm ratio {key}: realm={latest_realm.get(key)!r} "
                f"player-row oracle={row_val!r}"
            )

    return errors


def main() -> int:
    conn = _connect()
    try:
        errors = verify_hof_holder_projection(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-hof-holder-projection issue(s):", file=sys.stderr)
        for err in errors[:30]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 30:
            print(f"  ... and {len(errors) - 30} more", file=sys.stderr)
        return 1

    print("OK: verify-hof-holder-projection")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
