"""World Cup Hall of Fame compute (WCH-3).

``build_wc_hof_payload(conn, as_of_tournament_id=E)`` returns the full 28-group WC
HoF holder payload (``WC_HOF_PAYLOAD_COLUMNS``) as of World Cup ``E``, reading the
already-persisted WC player slice (``amiga_player_slice_at_event`` <= E per player)
plus a WC-filtered ``amiga_games`` oracle for single-game anchors.

Selection follows policy §4 / WCH9 (strict > to beat; tie -> lowest ``player_id``;
ratio rows gated at ``games >= 20``). Cumulative / ratio ``{Prefix}Date`` is DERIVED
from the holder's slice timeline (decision ID1). Date helpers are shared with
``verify_wc_hof``.
"""

from __future__ import annotations

from decimal import ROUND_HALF_UP, Decimal
from typing import Any, Callable

import pymysql

from scripts.amiga.realm_cutoff import RealmCutoff, load_realm_cutoff
from scripts.amiga.wc_hof_columns import (
    WC_ESTABLISHED_MIN_GAMES,
    WC_HOF_PAYLOAD_COLUMNS,
)

_WC_NAME_RE = r"^World Cup[[:space:]]+[^[:space:]]"

# §4.1-4.5 + §4.7 — cumulative "Most ..." holders: (prefix, slice column).
WC_CUMULATIVE_HOLDERS: tuple[tuple[str, str], ...] = (
    ("MostWcPlayed", "tournaments_played"),
    ("MostWcGold", "gold"),
    ("MostWcGames", "games"),
    ("MostWcWins", "wins"),
    ("MostWcPoints", "points"),
    ("MostWcGoalsFor", "goals_for"),
    ("MostWcDoubleDigits", "double_digits"),
    ("MostWcCleanSheets", "clean_sheets"),
    ("MostWcOpponents", "different_opponents"),
    ("MostWcVictims", "different_victims"),
    ("MostWcDoubleDigitsVictims", "double_digits_victims"),
    ("MostWcCleanSheetsVictims", "clean_sheets_victims"),
    ("MostWcBestAttackAwards", "best_attack_awards"),
    ("MostWcBestDefenseAwards", "best_defense_awards"),
)


def _num(value: Any) -> float:
    if value is None:
        return 0.0
    return float(value)


def _q4(value: float | None) -> float | None:
    if value is None:
        return None
    return float(Decimal(str(value)).quantize(Decimal("0.0001"), rounding=ROUND_HALF_UP))


# --- ratio metric functions (operate on a slice row dict) ------------------

def _ratio_pts_per_game(r: dict[str, Any]) -> float | None:
    g = _num(r.get("games"))
    return None if g <= 0 else _num(r.get("points")) / g


def _ratio_win_rate(r: dict[str, Any]) -> float | None:
    g = _num(r.get("games"))
    return None if g <= 0 else (_num(r.get("wins")) + 0.5 * _num(r.get("draws"))) / g


def _ratio_gf_per_game(r: dict[str, Any]) -> float | None:
    g = _num(r.get("games"))
    return None if g <= 0 else _num(r.get("goals_for")) / g


def _ratio_ga_per_game(r: dict[str, Any]) -> float | None:
    g = _num(r.get("games"))
    return None if g <= 0 else _num(r.get("goals_against")) / g


def _ratio_gd_per_game(r: dict[str, Any]) -> float | None:
    g = _num(r.get("games"))
    return None if g <= 0 else (_num(r.get("goals_for")) - _num(r.get("goals_against"))) / g


def _ratio_goal_ratio(r: dict[str, Any]) -> float | None:
    v = r.get("goal_ratio")
    return None if v is None else float(v)


def _ratio_dd(r: dict[str, Any]) -> float | None:
    v = r.get("double_digits_ratio")
    return None if v is None else float(v)


def _ratio_cs(r: dict[str, Any]) -> float | None:
    v = r.get("clean_sheets_ratio")
    return None if v is None else float(v)


# §4.2-4.4 — ratio holders: (prefix, metric_fn, higher_better).
WC_RATIO_HOLDERS: tuple[tuple[str, Callable[[dict[str, Any]], float | None], bool], ...] = (
    ("BestWcPtsPerGame", _ratio_pts_per_game, True),
    ("BestWcWinRate", _ratio_win_rate, True),
    ("BestWcGoalsForPerGame", _ratio_gf_per_game, True),
    ("BestWcGoalsAgainstPerGame", _ratio_ga_per_game, False),
    ("BestWcGoalDiffPerGame", _ratio_gd_per_game, True),
    ("BestWcGoalRatio", _ratio_goal_ratio, True),
    ("BestWcDoubleDigitsRatio", _ratio_dd, True),
    ("BestWcCleanSheetsRatio", _ratio_cs, True),
)

_RATIO_FN_BY_PREFIX: dict[str, tuple[Callable[[dict[str, Any]], float | None], bool]] = {
    prefix: (fn, hb) for prefix, fn, hb in WC_RATIO_HOLDERS
}


def _slice_cutoff_rows(
    conn: pymysql.connections.Connection, cutoff: RealmCutoff
) -> list[dict[str, Any]]:
    """Latest WC slice_at_event row per player with (date, chrono, tid) <= cutoff."""
    sql = """
        SELECT x.*, p.name AS player_name
        FROM (
            SELECT s.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s.player_id
                       ORDER BY s.event_date DESC, s.event_chrono DESC,
                                s.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_player_slice_at_event s
            WHERE s.slice_key = 'world_cup'
              AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (%s, %s, %s)
        ) x
        INNER JOIN amiga_players p ON p.id = x.player_id
        WHERE x.rn = 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, (cutoff.event_date, cutoff.chrono, cutoff.tournament_id))
        return list(cur.fetchall())


def _player_wc_timeline(
    conn: pymysql.connections.Connection, player_id: int, cutoff: RealmCutoff
) -> list[dict[str, Any]]:
    sql = """
        SELECT s.*
        FROM amiga_player_slice_at_event s
        WHERE s.slice_key = 'world_cup'
          AND s.player_id = %s
          AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (%s, %s, %s)
        ORDER BY s.event_date ASC, s.event_chrono ASC, s.as_of_tournament_id ASC
    """
    with conn.cursor() as cur:
        cur.execute(sql, (player_id, cutoff.event_date, cutoff.chrono, cutoff.tournament_id))
        return list(cur.fetchall())


def derive_cumulative_rise_date(timeline: list[dict[str, Any]], column: str) -> Any:
    """Event date of the latest row where ``column`` strictly increased."""
    last: Any = None
    prev: float | None = None
    for row in timeline:
        value = _num(row.get(column))
        if prev is None or value > prev:
            last = row.get("event_date")
        prev = value
    return last


def derive_ratio_rise_date(
    timeline: list[dict[str, Any]],
    metric_fn: Callable[[dict[str, Any]], float | None],
    higher_better: bool,
) -> Any:
    """Event date of the latest eligible (games>=20) row where the ratio improved."""
    last: Any = None
    prev: float | None = None
    for row in timeline:
        if _num(row.get("games")) < WC_ESTABLISHED_MIN_GAMES:
            continue
        value = metric_fn(row)
        if value is None:
            continue
        improved = prev is None or (value > prev if higher_better else value < prev)
        if improved:
            last = row.get("event_date")
            prev = value
    return last


def _pick_cumulative(rows: list[dict[str, Any]], column: str) -> dict[str, Any] | None:
    best: dict[str, Any] | None = None
    best_val = 0.0
    for row in rows:
        value = _num(row.get(column))
        if value <= 0:
            continue
        pid = int(row["player_id"])
        if best is None or value > best_val or (value == best_val and pid < int(best["player_id"])):
            best = row
            best_val = value
    return best


def _pick_ratio(
    rows: list[dict[str, Any]],
    metric_fn: Callable[[dict[str, Any]], float | None],
    higher_better: bool,
) -> tuple[dict[str, Any], float] | None:
    best: dict[str, Any] | None = None
    best_val: float | None = None
    for row in rows:
        if _num(row.get("games")) < WC_ESTABLISHED_MIN_GAMES:
            continue
        value = metric_fn(row)
        if value is None:
            continue
        pid = int(row["player_id"])
        if best is None:
            best, best_val = row, value
            continue
        better = value > best_val if higher_better else value < best_val
        tie = value == best_val and pid < int(best["player_id"])
        if better or tie:
            best, best_val = row, value
    return (best, best_val) if best is not None else None


def _cumulative_patch(
    conn: pymysql.connections.Connection,
    rows: list[dict[str, Any]],
    cutoff: RealmCutoff,
    timelines: dict[int, list[dict[str, Any]]],
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for prefix, column in WC_CUMULATIVE_HOLDERS:
        holder = _pick_cumulative(rows, column)
        if holder is None:
            continue
        pid = int(holder["player_id"])
        timeline = timelines.setdefault(pid, _player_wc_timeline(conn, pid, cutoff))
        patch[prefix] = int(_num(holder.get(column)))
        patch[f"{prefix}ID"] = pid
        patch[f"{prefix}Name"] = holder.get("player_name")
        patch[f"{prefix}Date"] = derive_cumulative_rise_date(timeline, column)
    return patch


def _ratio_patch(
    conn: pymysql.connections.Connection,
    rows: list[dict[str, Any]],
    cutoff: RealmCutoff,
    timelines: dict[int, list[dict[str, Any]]],
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for prefix, metric_fn, higher_better in WC_RATIO_HOLDERS:
        picked = _pick_ratio(rows, metric_fn, higher_better)
        if picked is None:
            continue
        holder, value = picked
        pid = int(holder["player_id"])
        timeline = timelines.setdefault(pid, _player_wc_timeline(conn, pid, cutoff))
        patch[prefix] = _q4(value)
        patch[f"{prefix}ID"] = pid
        patch[f"{prefix}Name"] = holder.get("player_name")
        patch[f"{prefix}Date"] = derive_ratio_rise_date(timeline, metric_fn, higher_better)
    return patch


# --- single-game holders (§4.6) — WC-filtered amiga_games oracle ------------

def _wc_cutoff_clause(alias: str = "tg") -> str:
    return (
        f"{alias}.name REGEXP %s "
        f"AND ({alias}.event_date, {alias}.chrono, {alias}.id) <= (%s, %s, %s)"
    )


def _wc_cutoff_params(cutoff: RealmCutoff) -> tuple[Any, ...]:
    return (_WC_NAME_RE, cutoff.event_date, cutoff.chrono, cutoff.tournament_id)


_DATE_EXPR = (
    "COALESCE(DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'), "
    "DATE_FORMAT(g.game_date, '%%Y-%%m-%%d'))"
)


def _most_goals_one_game(conn, cutoff):
    clause = _wc_cutoff_clause()
    params = _wc_cutoff_params(cutoff)
    sql = f"""
        SELECT game_id, player_id, player_name, goals, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   g.goals_a AS goals, {_DATE_EXPR} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE {clause}
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name, g.goals_b, {_DATE_EXPR}
            FROM amiga_games g
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE {clause}
        ) sides
        ORDER BY goals DESC, game_id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, (*params, *params))
        row = cur.fetchone()
    if not row or int(row["goals"]) <= 0:
        return {}
    return {
        "MostWcGoalsInOneGame": int(row["goals"]),
        "MostWcGoalsInOneGameID": int(row["player_id"]),
        "MostWcGoalsInOneGameName": row["player_name"],
        "MostWcGoalsInOneGameDate": row["record_date"],
        "MostWcGoalsInOneGameGameID": int(row["game_id"]),
    }


def _biggest_win_margin(conn, cutoff):
    clause = _wc_cutoff_clause()
    params = _wc_cutoff_params(cutoff)
    sql = f"""
        SELECT g.id AS game_id,
               r.goal_difference AS margin,
               CASE WHEN r.actual_score = 1.0 THEN g.player_a_id
                    WHEN r.actual_score = 0.0 THEN g.player_b_id END AS player_id,
               CASE WHEN r.actual_score = 1.0 THEN pa.name
                    WHEN r.actual_score = 0.0 THEN pb.name END AS player_name,
               {_DATE_EXPR} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score IN (0.0, 1.0)
          AND r.goal_difference IS NOT NULL AND {clause}
        ORDER BY r.goal_difference DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row or row["player_id"] is None:
        return {}
    return {
        "BiggestWcWinDifference": int(row["margin"]),
        "BiggestWcWinDifferenceID": int(row["player_id"]),
        "BiggestWcWinDifferenceName": row["player_name"],
        "BiggestWcWinDifferenceDate": row["record_date"],
        "BiggestWcWinDifferenceGameID": int(row["game_id"]),
    }


def _biggest_draw_sum(conn, cutoff):
    clause = _wc_cutoff_clause()
    params = _wc_cutoff_params(cutoff)
    sql = f"""
        SELECT g.id AS game_id, (g.goals_a + g.goals_b) AS draw_sum,
               g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b,
               {_DATE_EXPR} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score = 0.5 AND {clause}
        ORDER BY draw_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestWcDrawSum": int(row["draw_sum"]),
        "BiggestWcDrawSumIDA": int(row["player_a_id"]),
        "BiggestWcDrawSumIDB": int(row["player_b_id"]),
        "BiggestWcDrawSumNameA": row["name_a"],
        "BiggestWcDrawSumNameB": row["name_b"],
        "BiggestWcDrawSumDate": row["record_date"],
        "BiggestWcDrawSumGameID": int(row["game_id"]),
    }


def _biggest_sum_goals(conn, cutoff):
    clause = _wc_cutoff_clause()
    params = _wc_cutoff_params(cutoff)
    sql = f"""
        SELECT g.id AS game_id,
               COALESCE(r.sum_of_goals, g.goals_a + g.goals_b) AS goal_sum,
               g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b,
               {_DATE_EXPR} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE {clause}
        ORDER BY goal_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestWcSumOfGoals": int(row["goal_sum"]),
        "BiggestWcSumOfGoalsIDA": int(row["player_a_id"]),
        "BiggestWcSumOfGoalsIDB": int(row["player_b_id"]),
        "BiggestWcSumOfGoalsNameA": row["name_a"],
        "BiggestWcSumOfGoalsNameB": row["name_b"],
        "BiggestWcSumOfGoalsDate": row["record_date"],
        "BiggestWcSumOfGoalsGameID": int(row["game_id"]),
    }


# --- single-WC peaks (§4.8) -------------------------------------------------

def _tournament_event_date(conn, tournament_id):
    if tournament_id is None:
        return None
    with conn.cursor() as cur:
        cur.execute("SELECT event_date FROM tournaments WHERE id = %s LIMIT 1", (int(tournament_id),))
        row = cur.fetchone()
    return row["event_date"] if row else None


def _single_wc_peaks_patch(conn, rows):
    patch: dict[str, Any] = {}
    # GF/g peak (higher), GA/g peak (lower) — tie -> lowest player_id.
    best_gf: dict[str, Any] | None = None
    best_ga: dict[str, Any] | None = None
    for row in rows:
        pid = int(row["player_id"])
        gf = row.get("best_single_wc_gf_per_game")
        if gf is not None:
            if (
                best_gf is None
                or float(gf) > float(best_gf["best_single_wc_gf_per_game"])
                or (
                    float(gf) == float(best_gf["best_single_wc_gf_per_game"])
                    and pid < int(best_gf["player_id"])
                )
            ):
                best_gf = row
        ga = row.get("best_single_wc_ga_per_game")
        if ga is not None:
            if (
                best_ga is None
                or float(ga) < float(best_ga["best_single_wc_ga_per_game"])
                or (
                    float(ga) == float(best_ga["best_single_wc_ga_per_game"])
                    and pid < int(best_ga["player_id"])
                )
            ):
                best_ga = row
    if best_gf is not None:
        tid = best_gf.get("best_single_wc_gf_per_game_tournament_id")
        patch["BestSingleWcGoalsForPerGame"] = _q4(float(best_gf["best_single_wc_gf_per_game"]))
        patch["BestSingleWcGoalsForPerGameID"] = int(best_gf["player_id"])
        patch["BestSingleWcGoalsForPerGameName"] = best_gf.get("player_name")
        patch["BestSingleWcGoalsForPerGameTournamentID"] = int(tid) if tid is not None else None
        patch["BestSingleWcGoalsForPerGameDate"] = _tournament_event_date(conn, tid)
    if best_ga is not None:
        tid = best_ga.get("best_single_wc_ga_per_game_tournament_id")
        patch["BestSingleWcGoalsAgainstPerGame"] = _q4(float(best_ga["best_single_wc_ga_per_game"]))
        patch["BestSingleWcGoalsAgainstPerGameID"] = int(best_ga["player_id"])
        patch["BestSingleWcGoalsAgainstPerGameName"] = best_ga.get("player_name")
        patch["BestSingleWcGoalsAgainstPerGameTournamentID"] = int(tid) if tid is not None else None
        patch["BestSingleWcGoalsAgainstPerGameDate"] = _tournament_event_date(conn, tid)
    return patch


def build_wc_hof_payload(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int,
) -> dict[str, Any]:
    """Full WC HoF holder payload as of World Cup ``as_of_tournament_id``."""
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    rows = _slice_cutoff_rows(conn, cutoff)
    timelines: dict[int, list[dict[str, Any]]] = {}

    patch: dict[str, Any] = {}
    patch.update(_cumulative_patch(conn, rows, cutoff, timelines))
    patch.update(_ratio_patch(conn, rows, cutoff, timelines))
    patch.update(_most_goals_one_game(conn, cutoff))
    patch.update(_biggest_win_margin(conn, cutoff))
    patch.update(_biggest_draw_sum(conn, cutoff))
    patch.update(_biggest_sum_goals(conn, cutoff))
    patch.update(_single_wc_peaks_patch(conn, rows))

    return {col: patch.get(col) for col in WC_HOF_PAYLOAD_COLUMNS}