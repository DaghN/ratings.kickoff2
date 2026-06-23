#!/usr/bin/env python3
"""Verify world_cup player slice tables against ground-truth oracles."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_geo_year import load_player_countries
from scripts.amiga.slice_columns import SLICE_KEY_WORLD_CUP, SLICE_STAT_COLUMNS_V2
from scripts.amiga.slice_game_stats import build_v2_oracle_for_player

_WC_NAME_RE = r"^World Cup[[:space:]]+[^[:space:]]"
_FLOAT_COLS = frozenset(
    {
        "goal_ratio",
        "double_digits_ratio",
        "clean_sheets_ratio",
        "double_digits_conceded_ratio",
        "clean_sheets_conceded_ratio",
    }
)


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
        autocommit=True,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _load_oracle_totals(conn: pymysql.connections.Connection) -> dict[int, dict[str, int]]:
    honours_sql = f"""
        SELECT s.player_id,
               COUNT(*) AS tournaments_played,
               SUM(CASE WHEN s.event_finish_position = 1 THEN 1 ELSE 0 END) AS gold,
               SUM(CASE WHEN s.event_finish_position = 2 THEN 1 ELSE 0 END) AS silver,
               SUM(CASE WHEN s.event_finish_position = 3 THEN 1 ELSE 0 END) AS bronze
        FROM amiga_player_event_snapshots s
        INNER JOIN tournaments t ON t.id = s.tournament_id
        WHERE t.name REGEXP %s
        GROUP BY s.player_id
    """
    games_sql = f"""
        SELECT sides.player_id,
               COUNT(*) AS games,
               SUM(CASE WHEN sides.gf > sides.ga THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN sides.gf = sides.ga THEN 1 ELSE 0 END) AS draws,
               SUM(CASE WHEN sides.gf < sides.ga THEN 1 ELSE 0 END) AS losses,
               COALESCE(SUM(sides.gf), 0) AS goals_for,
               COALESCE(SUM(sides.ga), 0) AS goals_against
        FROM (
            SELECT g.player_a_id AS player_id, g.goals_a AS gf, g.goals_b AS ga, g.tournament_id
            FROM amiga_games g
            UNION ALL
            SELECT g.player_b_id AS player_id, g.goals_b AS gf, g.goals_a AS ga, g.tournament_id
            FROM amiga_games g
        ) sides
        INNER JOIN tournaments t ON t.id = sides.tournament_id
        WHERE t.name REGEXP %s
        GROUP BY sides.player_id
    """
    with conn.cursor() as cur:
        cur.execute(honours_sql, (_WC_NAME_RE,))
        honour_rows = {int(r["player_id"]): r for r in cur.fetchall()}
        cur.execute(games_sql, (_WC_NAME_RE,))
        game_rows = {int(r["player_id"]): r for r in cur.fetchall()}

    player_ids = set(honour_rows) | set(game_rows)
    out: dict[int, dict[str, int]] = {}
    for pid in player_ids:
        h = honour_rows.get(pid, {})
        g = game_rows.get(pid, {})
        gold = int(h.get("gold") or 0)
        silver = int(h.get("silver") or 0)
        bronze = int(h.get("bronze") or 0)
        wins = int(g.get("wins") or 0)
        draws = int(g.get("draws") or 0)
        out[pid] = {
            "tournaments_played": int(h.get("tournaments_played") or 0),
            "gold": gold,
            "silver": silver,
            "bronze": bronze,
            "podiums": gold + silver + bronze,
            "games": int(g.get("games") or 0),
            "wins": wins,
            "draws": draws,
            "losses": int(g.get("losses") or 0),
            "goals_for": int(g.get("goals_for") or 0),
            "goals_against": int(g.get("goals_against") or 0),
            "points": 3 * wins + draws,
        }
    return out


def _load_slice_totals(conn: pymysql.connections.Connection) -> dict[int, dict[str, int]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, tournaments_played, gold, silver, bronze, podiums,
                   games, wins, draws, losses, goals_for, goals_against, points
            FROM amiga_player_slice_totals
            WHERE slice_key = %s
            """,
            (SLICE_KEY_WORLD_CUP,),
        )
        rows = cur.fetchall()
    return {
        int(r["player_id"]): {k: int(r[k] or 0) for k in (
            "tournaments_played", "gold", "silver", "bronze", "podiums",
            "games", "wins", "draws", "losses", "goals_for", "goals_against", "points",
        )}
        for r in rows
    }


def _load_wc_games(conn: pymysql.connections.Connection) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT g.player_a_id AS idA, g.player_b_id AS idB,
                   g.goals_a AS GoalsA, g.goals_b AS GoalsB
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE t.name REGEXP %s
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, g.id ASC
            """,
            (_WC_NAME_RE,),
        )
        return list(cur.fetchall())


def _load_slice_v2(conn: pymysql.connections.Connection) -> dict[int, dict]:
    cols = ", ".join(SLICE_STAT_COLUMNS_V2)
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT player_id, {cols}
            FROM amiga_player_slice_totals
            WHERE slice_key = %s AND tournaments_played > 0
            """,
            (SLICE_KEY_WORLD_CUP,),
        )
        return {int(r["player_id"]): r for r in cur.fetchall()}


def _float_close(a: object, b: object, tol: float = 1e-4) -> bool:
    if a is None and b is None:
        return True
    if a is None or b is None:
        return False
    return abs(float(a) - float(b)) <= tol


def _check_totals_oracle(conn: pymysql.connections.Connection, errors: list[str]) -> None:
    oracle = _load_oracle_totals(conn)
    stored = _load_slice_totals(conn)

    if set(oracle) != set(stored):
        missing = sorted(set(oracle) - set(stored))
        extra = sorted(set(stored) - set(oracle))
        if missing:
            errors.append(f"slice_totals missing players: {missing[:10]}{'…' if len(missing) > 10 else ''}")
        if extra:
            errors.append(f"slice_totals extra players: {extra[:10]}{'…' if len(extra) > 10 else ''}")

    cols = (
        "tournaments_played", "gold", "silver", "bronze", "podiums",
        "games", "wins", "draws", "losses", "goals_for", "goals_against", "points",
    )
    for pid, expected in oracle.items():
        row = stored.get(pid)
        if row is None:
            continue
        for col in cols:
            if row[col] != expected[col]:
                errors.append(
                    f"player_id={pid} {col}: stored={row[col]} oracle={expected[col]}"
                )
        if row["podiums"] != row["gold"] + row["silver"] + row["bronze"]:
            errors.append(f"player_id={pid} podiums != gold+silver+bronze")
        if row["points"] != 3 * row["wins"] + row["draws"]:
            errors.append(f"player_id={pid} points != 3*wins+draws")


def _check_v2_oracle(conn: pymysql.connections.Connection, errors: list[str]) -> None:
    v1_oracle = _load_oracle_totals(conn)
    stored_v2 = _load_slice_v2(conn)
    if not stored_v2:
        return

    games = _load_wc_games(conn)
    player_countries = load_player_countries(conn)

    for pid, row in stored_v2.items():
        v1 = v1_oracle.get(pid)
        if v1 is None:
            errors.append(f"player_id={pid} has V2 slice but no V1 oracle")
            continue
        expected = build_v2_oracle_for_player(v1, games, player_countries, pid)
        for col in SLICE_STAT_COLUMNS_V2:
            stored_val = row.get(col)
            expected_val = expected.get(col)
            if col in _FLOAT_COLS:
                if not _float_close(stored_val, expected_val):
                    errors.append(
                        f"player_id={pid} {col}: stored={stored_val!r} oracle={expected_val!r}"
                    )
            elif int(stored_val or 0) != int(expected_val or 0):
                errors.append(
                    f"player_id={pid} {col}: stored={stored_val} oracle={expected_val}"
                )


def _check_at_event_matches_latest_participation(
    conn: pymysql.connections.Connection,
    errors: list[str],
) -> None:
    """Each player's latest slice_at_event row should match slice_totals when they have WC stats."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.player_id, t.tournaments_played, t.gold, t.games, t.points,
                   t.double_digits, t.different_opponents
            FROM amiga_player_slice_totals t
            WHERE t.slice_key = %s AND t.tournaments_played > 0
            """,
            (SLICE_KEY_WORLD_CUP,),
        )
        totals_rows = cur.fetchall()

        for row in totals_rows:
            pid = int(row["player_id"])
            cur.execute(
                """
                SELECT x.tournaments_played, x.gold, x.games, x.points,
                       x.double_digits, x.different_opponents
                FROM (
                    SELECT s.*,
                           ROW_NUMBER() OVER (
                               PARTITION BY s.player_id
                               ORDER BY s.event_date DESC, s.event_chrono DESC,
                                        s.as_of_tournament_id DESC
                           ) AS rn
                    FROM amiga_player_slice_at_event s
                    WHERE s.slice_key = %s AND s.player_id = %s
                ) x
                WHERE x.rn = 1
                """,
                (SLICE_KEY_WORLD_CUP, pid),
            )
            latest = cur.fetchone()
            if latest is None:
                errors.append(f"player_id={pid} has slice_totals but no slice_at_event row")
                continue
            for col in (
                "tournaments_played", "gold", "games", "points",
                "double_digits", "different_opponents",
            ):
                if int(row[col] or 0) != int(latest[col] or 0):
                    errors.append(
                        f"player_id={pid} totals vs latest at_event {col}: "
                        f"{row[col]} != {latest[col]}"
                    )


def main() -> int:
    conn = _connect()
    errors: list[str] = []
    try:
        _check_totals_oracle(conn, errors)
        _check_v2_oracle(conn, errors)
        _check_at_event_matches_latest_participation(conn, errors)
    finally:
        conn.close()

    if errors:
        print("verify-player-slice FAIL:", len(errors), "issue(s)", file=sys.stderr)
        for msg in errors[:40]:
            print(f"  - {msg}", file=sys.stderr)
        if len(errors) > 40:
            print(f"  … and {len(errors) - 40} more", file=sys.stderr)
        return 1

    print("verify-player-slice OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
