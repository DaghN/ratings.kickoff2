"""Incremental realm snapshot compute (prior row + tournament delta)."""

from __future__ import annotations

from typing import Any

import pymysql

from scripts.amiga.career_rise import HOF_PREFIX_TO_CAREER_RISE_DATE
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.realm_cutoff import RealmCutoff, cutoff_params, game_cutoff_sql, load_realm_cutoff
from scripts.amiga.server_records import (
    ESTABLISHED_MIN_GAMES,
    _CAREER_HOLDERS,
    _RATIO_LEADERS,
    _aggregate_patch,
    _fmt_date,
)
from scripts.amiga.player_geo_year import year_period_end

# Career holder value column on player row -> generalstats prefix.
_CAREER_ROW_PREFIXES: list[tuple[str, str]] = [
    (value_col, prefix)
    for _prefix, value_col, prefix in _CAREER_HOLDERS
    if prefix != "BiggestPeakRating"
]

_HOLDER_DATE_FIELD: dict[str, str] = {
    "MostGamesInOneYear": "peak_year_games_year",
    "MostTournamentsInOneYear": "peak_year_tournaments_year",
    "MostTournamentsPlayed": "tournaments_played_last_rise_event_date",
    "MostTournamentWins": "event_gold_last_rise_event_date",
    "MostWcPlayed": "wc_played_last_rise_event_date",
    "MostCountriesPlayedIn": "countries_played_in_last_rise_event_date",
    "MostOpponentCountriesFaced": "opponent_countries_faced_last_rise_event_date",
    "MostOpponentCountriesBeaten": "opponent_countries_beaten_last_rise_event_date",
    **HOF_PREFIX_TO_CAREER_RISE_DATE,
}


def _holder_record_date(prefix: str, row: dict[str, Any]) -> str | None:
    field = _HOLDER_DATE_FIELD.get(prefix)
    if field in ("peak_year_games_year", "peak_year_tournaments_year"):
        return year_period_end(row.get(field))
    if field:
        return _fmt_date(row.get(field))
    return _fmt_date(row.get("record_date"))


def empty_prior_payload() -> dict[str, Any]:
    return {col: None for col in GENERALSTATS_PAYLOAD_COLUMNS}


def load_prior_realm_payload(
    conn: pymysql.connections.Connection,
    as_of_tournament_id: int,
) -> dict[str, Any]:
    """Realm payload from the latest snapshot strictly before this tournament's chrono."""
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    params = cutoff_params(cutoff)
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.*
            FROM amiga_realm_snapshots r
            WHERE (r.event_date, r.event_chrono, r.tournament_id)
                  < (%s, %s, %s)
            ORDER BY r.event_date DESC, r.event_chrono DESC, r.tournament_id DESC
            LIMIT 1
            """,
            params,
        )
        row = cur.fetchone()
    if not row:
        return empty_prior_payload()
    return {col: row.get(col) for col in GENERALSTATS_PAYLOAD_COLUMNS}


def tournament_game_aggregate_delta(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[str, int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS games,
                   SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
                   COALESCE(SUM(r.sum_of_goals), 0) AS goals,
                   COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
                   COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        row = cur.fetchone()
    return {
        "games": int(row["games"] or 0),
        "draws": int(row["draws"] or 0),
        "goals": int(row["goals"] or 0),
        "dd": int(row["dd"] or 0),
        "cs": int(row["cs"] or 0),
    }


def tournament_game_aggregate_delta_from_memory(
    games: list[dict[str, Any]],
    ratings_by_game_id: dict[int, dict[str, Any]],
) -> dict[str, int]:
    games_n = 0
    draws = 0
    goals = 0
    dd = 0
    cs = 0
    for game in games:
        game_id = int(game["id"] if "id" in game else game.get("id"))
        rating = ratings_by_game_id.get(game_id)
        if rating is None:
            continue
        games_n += 1
        actual = float(rating["actual_score"])
        if actual == 0.5:
            draws += 1
        goals += int(rating.get("sum_of_goals") or 0)
        dd += int(rating.get("dd_player_a") or 0) + int(rating.get("dd_player_b") or 0)
        cs += int(rating.get("cs_player_a") or 0) + int(rating.get("cs_player_b") or 0)
    return {
        "games": games_n,
        "draws": draws,
        "goals": goals,
        "dd": dd,
        "cs": cs,
    }


def _merge_game_aggregates(
    prior: dict[str, Any],
    delta: dict[str, int],
    *,
    num_players: int,
    diff_opp_avg: Any,
) -> dict[str, Any]:
    games = int(prior.get("GamesPlayed") or 0) + delta["games"]
    draws = int(prior.get("NumberOfDraws") or 0) + delta["draws"]
    decided = games - draws
    goals = int(prior.get("GoalsScored") or 0) + delta["goals"]
    dd = int(prior.get("DoubleDigits") or 0) + delta["dd"]
    cs = int(prior.get("CleanSheets") or 0) + delta["cs"]
    return _aggregate_patch(
        games=games,
        draws=draws,
        decided=decided,
        goals=goals,
        dd=dd,
        cs=cs,
        num_players=num_players,
        diff_opp_avg=diff_opp_avg,
    )


def _player_count_stats(player_rows: list[dict[str, Any]]) -> tuple[int, Any]:
    """Player counts from an in-memory row list (replay tier 3)."""
    active = [r for r in player_rows if int(r.get("NumberGames") or 0) >= 1]
    num_players = len(active)
    diff_vals = [
        float(r["DifferentOpponents"])
        for r in active
        if r.get("DifferentOpponents") is not None and int(r["DifferentOpponents"]) >= 1
    ]
    diff_opp_avg = sum(diff_vals) / len(diff_vals) if diff_vals else None
    return num_players, diff_opp_avg


def _player_count_stats_sql_present(
    conn: pymysql.connections.Connection,
) -> tuple[int, Any]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_player_current WHERE NumberGames >= 1"
        )
        num_players = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT AVG(DifferentOpponents) AS a
            FROM amiga_player_current
            WHERE DifferentOpponents >= 1
            """
        )
        diff_opp_avg = cur.fetchone()["a"]
    return num_players, diff_opp_avg


def _player_count_stats_sql_cutoff(
    conn: pymysql.connections.Connection,
    cutoff: RealmCutoff,
) -> tuple[int, Any]:
    from scripts.amiga.server_records import _latest_player_snapshots_sql

    params = cutoff_params(cutoff)
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM ({_latest_player_snapshots_sql()}) lp
            WHERE lp.NumberGames >= 1
            """,
            params,
        )
        num_players = int(cur.fetchone()["n"])
        cur.execute(
            f"""
            SELECT AVG(lp.DifferentOpponents) AS a
            FROM ({_latest_player_snapshots_sql()}) lp
            WHERE lp.DifferentOpponents >= 1
            """,
            params,
        )
        diff_opp_avg = cur.fetchone()["a"]
    return num_players, diff_opp_avg


def fetch_player_current_rows(
    conn: pymysql.connections.Connection,
) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.*, p.name AS player_name,
                   COALESCE(
                       DATE_FORMAT(t.event_date, '%Y-%m-%d'),
                       DATE_FORMAT(g.game_date, '%Y-%m-%d')
                   ) AS record_date
            FROM amiga_player_current s
            INNER JOIN amiga_players p ON p.id = s.player_id
            LEFT JOIN amiga_games g ON g.id = s.LastGameGameID
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE s.NumberGames >= 1
            """
        )
        return list(cur.fetchall())


def _record_dates_for_game_ids(
    conn: pymysql.connections.Connection,
    game_ids: set[int],
) -> dict[int, str | None]:
    if not game_ids:
        return {}
    placeholders = ", ".join(["%s"] * len(game_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT g.id AS game_id,
                   COALESCE(
                       DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'),
                       DATE_FORMAT(g.game_date, '%%Y-%%m-%%d')
                   ) AS record_date
            FROM amiga_games g
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE g.id IN ({placeholders})
            """,
            tuple(sorted(game_ids)),
        )
        rows = cur.fetchall()
    return {int(r["game_id"]): _fmt_date(r["record_date"]) for r in rows}


def player_rows_from_states(
    conn: pymysql.connections.Connection,
    players: dict[int, PlayerState],
    names: dict[int, str],
) -> list[dict[str, Any]]:
    game_ids = {
        int(st.last_game_id)
        for st in players.values()
        if st.games >= 1 and st.last_game_id is not None
    }
    dates = _record_dates_for_game_ids(conn, game_ids)
    rows: list[dict[str, Any]] = []
    for player_id, state in players.items():
        if state.games < 1:
            continue
        row = state.to_db_row(player_id)
        row["player_id"] = player_id
        row["player_name"] = names.get(player_id, "")
        row["record_date"] = dates.get(int(state.last_game_id or 0))
        rows.append(row)
    return rows


def _beats_holder(
    candidate_value: Any,
    candidate_id: int,
    holder_value: Any,
    holder_id: Any,
    *,
    higher_is_better: bool,
) -> bool:
    if candidate_value is None:
        return False
    try:
        cv = float(candidate_value)
    except (TypeError, ValueError):
        return False
    if cv <= 0 and higher_is_better:
        return False
    if holder_value is None or holder_id is None:
        return True
    try:
        hv = float(holder_value)
    except (TypeError, ValueError):
        return True
    hid = int(holder_id)
    if higher_is_better:
        if cv > hv:
            return True
        return cv == hv and candidate_id < hid
    if cv < hv:
        return True
    return cv == hv and candidate_id < hid


def _career_holders_from_player_rows(
    player_rows: list[dict[str, Any]],
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for value_col, prefix in _CAREER_ROW_PREFIXES:
        best_row: dict[str, Any] | None = None
        best_value: Any = None
        best_id = 0
        for row in player_rows:
            value = row.get(value_col)
            if value is None:
                continue
            try:
                fv = float(value)
            except (TypeError, ValueError):
                continue
            if fv <= 0:
                continue
            pid = int(row["player_id"])
            if best_row is None or fv > float(best_value) or (fv == float(best_value) and pid < best_id):
                best_row = row
                best_value = value
                best_id = pid
        if best_row is None:
            continue
        patch[prefix] = best_value
        patch[f"{prefix}ID"] = best_id
        patch[f"{prefix}Name"] = best_row["player_name"]
        patch[f"{prefix}Date"] = _holder_record_date(prefix, best_row)
    return patch


def _ratio_leaders_from_player_rows(
    player_rows: list[dict[str, Any]],
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for prefix, column, direction, extra_where in _RATIO_LEADERS:
        higher_is_better = direction.upper() == "DESC"
        best_row: dict[str, Any] | None = None
        best_value: Any = None
        best_id = 0
        for row in player_rows:
            if int(row.get("NumberGames") or 0) < ESTABLISHED_MIN_GAMES:
                continue
            value = row.get(column)
            if value is None:
                continue
            if extra_where and column == "GoalRatio":
                try:
                    if float(value) <= -1:
                        continue
                except (TypeError, ValueError):
                    continue
            pid = int(row["player_id"])
            if best_row is None or _beats_holder(
                value,
                pid,
                best_value,
                best_id,
                higher_is_better=higher_is_better,
            ):
                best_row = row
                best_value = value
                best_id = pid
        if best_row is None:
            continue
        patch[prefix] = best_value
        patch[f"{prefix}ID"] = best_id
        patch[f"{prefix}Name"] = best_row["player_name"]
    return patch


def _event_date_expr_for_games(conn: pymysql.connections.Connection, tournament_id: int) -> str | None:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT event_date FROM tournaments WHERE id = %s LIMIT 1",
            (tournament_id,),
        )
        row = cur.fetchone()
    if not row or row.get("event_date") is None:
        return None
    return str(row["event_date"])


def _single_game_candidates_from_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[str, Any]:
    event_date = _event_date_expr_for_games(conn, tournament_id)
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id AS game_id, g.player_a_id, g.player_b_id,
                   g.goals_a, g.goals_b, g.game_date,
                   pa.name AS name_a, pb.name AS name_b,
                   r.actual_score, r.goal_difference, r.sum_of_goals,
                   r.rating_a, r.rating_b, r.adjustment_a, r.adjustment_b,
                   r.new_rating_a, r.new_rating_b
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        games = cur.fetchall()
    return _single_game_candidates_from_rows(games, event_date)


def _game_record_date(game: dict[str, Any], event_date: str | None) -> str | None:
    if event_date:
        return event_date
    gd = game.get("game_date")
    return str(gd) if gd is not None else None


def _single_game_candidates_from_rows(
    games: list[dict[str, Any]],
    event_date: str | None,
) -> dict[str, Any]:
    patch: dict[str, Any] = {}

    best_goals: tuple[int, int, int, str, str | None] | None = None
    best_win: tuple[int, int, int, str, str | None] | None = None
    best_draw: tuple[int, int, int, int, str, str, str | None] | None = None
    best_sum: tuple[int, int, int, int, str, str, str | None] | None = None
    best_peak: tuple[float, int, int, str, str | None] | None = None

    for game in games:
        game_id = int(game["game_id"] if "game_id" in game else game["id"])
        record_date = _game_record_date(game, event_date)
        goals_a = int(game["goals_a"] if "goals_a" in game else game["GoalsA"])
        goals_b = int(game["goals_b"] if "goals_b" in game else game["GoalsB"])
        name_a = str(game.get("name_a") or game.get("nameA") or "")
        name_b = str(game.get("name_b") or game.get("nameB") or "")
        id_a = int(game["player_a_id"] if "player_a_id" in game else game["idA"])
        id_b = int(game["player_b_id"] if "player_b_id" in game else game["idB"])

        for pid, goals, name in ((id_a, goals_a, name_a), (id_b, goals_b, name_b)):
            cand = (goals, game_id, pid, name, record_date)
            if best_goals is None or goals > best_goals[0] or (goals == best_goals[0] and game_id < best_goals[1]):
                best_goals = cand

        actual = game.get("actual_score")
        if actual is not None:
            actual_f = float(actual)
            if actual_f in (0.0, 1.0) and game.get("goal_difference") is not None:
                margin = int(game["goal_difference"])
                if actual_f == 1.0:
                    pid, name = id_a, name_a
                else:
                    pid, name = id_b, name_b
                cand = (margin, game_id, pid, name, record_date)
                if best_win is None or margin > best_win[0] or (margin == best_win[0] and game_id < best_win[1]):
                    best_win = cand

            if actual_f == 0.5:
                draw_sum = goals_a + goals_b
                cand = (draw_sum, game_id, id_a, id_b, name_a, name_b, record_date)
                if best_draw is None or draw_sum > best_draw[0] or (
                    draw_sum == best_draw[0] and game_id < best_draw[1]
                ):
                    best_draw = cand

        goal_sum = game.get("sum_of_goals")
        if goal_sum is None:
            goal_sum = goals_a + goals_b
        goal_sum = int(goal_sum)
        cand = (goal_sum, game_id, id_a, id_b, name_a, name_b, record_date)
        if best_sum is None or goal_sum > best_sum[0] or (goal_sum == best_sum[0] and game_id < best_sum[1]):
            best_sum = cand

        for pid, name, rating, adjustment, new_rating in (
            (
                id_a,
                name_a,
                game.get("rating_a"),
                game.get("adjustment_a"),
                game.get("new_rating_a"),
            ),
            (
                id_b,
                name_b,
                game.get("rating_b"),
                game.get("adjustment_b"),
                game.get("new_rating_b"),
            ),
        ):
            if rating is None or adjustment is None:
                continue
            peak = new_rating
            if peak is None:
                peak = float(rating) + float(adjustment)
            peak_f = float(peak)
            cand = (peak_f, game_id, pid, name, record_date)
            if best_peak is None or peak_f > best_peak[0] or (
                peak_f == best_peak[0] and game_id < best_peak[1]
            ):
                best_peak = cand

    if best_goals:
        patch.update(
            {
                "MostGoalsScoredInOneGame": best_goals[0],
                "MostGoalsScoredInOneGameID": best_goals[2],
                "MostGoalsScoredInOneGameName": best_goals[3],
                "MostGoalsScoredInOneGameDate": best_goals[4],
                "MostGoalsScoredInOneGameGameID": best_goals[1],
            }
        )
    if best_win:
        patch.update(
            {
                "BiggestWinDifference": best_win[0],
                "BiggestWinDifferenceID": best_win[2],
                "BiggestWinDifferenceName": best_win[3],
                "BiggestWinDifferenceDate": best_win[4],
                "BiggestWinDifferenceGameID": best_win[1],
            }
        )
    if best_draw:
        patch.update(
            {
                "BiggestDrawSum": best_draw[0],
                "BiggestDrawSumIDA": best_draw[2],
                "BiggestDrawSumIDB": best_draw[3],
                "BiggestDrawSumNameA": best_draw[4],
                "BiggestDrawSumNameB": best_draw[5],
                "BiggestDrawSumDate": best_draw[6],
                "BiggestDrawSumGameID": best_draw[1],
            }
        )
    if best_sum:
        patch.update(
            {
                "BiggestSumOfGoals": best_sum[0],
                "BiggestSumOfGoalsIDA": best_sum[2],
                "BiggestSumOfGoalsIDB": best_sum[3],
                "BiggestSumOfGoalsNameA": best_sum[4],
                "BiggestSumOfGoalsNameB": best_sum[5],
                "BiggestSumOfGoalsDate": best_sum[6],
                "BiggestSumOfGoalsGameID": best_sum[1],
            }
        )
    if best_peak:
        patch.update(
            {
                "BiggestPeakRating": best_peak[0],
                "BiggestPeakRatingID": best_peak[2],
                "BiggestPeakRatingName": best_peak[3],
                "BiggestPeakRatingDate": best_peak[4],
            }
        )
    return patch


def _single_game_candidates_from_memory(
    games: list[dict[str, Any]],
    ratings_by_game_id: dict[int, dict[str, Any]],
    names: dict[int, str],
    event_date: str | None,
) -> dict[str, Any]:
    enriched: list[dict[str, Any]] = []
    for game in games:
        game_id = int(game["id"])
        rating = ratings_by_game_id.get(game_id, {})
        enriched.append(
            {
                "game_id": game_id,
                "player_a_id": int(game["idA"]),
                "player_b_id": int(game["idB"]),
                "goals_a": int(game["GoalsA"]),
                "goals_b": int(game["GoalsB"]),
                "game_date": game.get("Date"),
                "name_a": names.get(int(game["idA"]), ""),
                "name_b": names.get(int(game["idB"]), ""),
                **rating,
            }
        )
    return _single_game_candidates_from_rows(enriched, event_date)


_SINGLE_GAME_PREFIXES: tuple[tuple[str, str, bool], ...] = (
    ("MostGoalsScoredInOneGame", "MostGoalsScoredInOneGame", True),
    ("BiggestWinDifference", "BiggestWinDifference", True),
    ("BiggestDrawSum", "BiggestDrawSum", True),
    ("BiggestSumOfGoals", "BiggestSumOfGoals", True),
    ("BiggestPeakRating", "BiggestPeakRating", True),
)


def _merge_single_game_records(
    prior: dict[str, Any],
    event_candidates: dict[str, Any],
) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for value_key, prefix, higher_is_better in _SINGLE_GAME_PREFIXES:
        prior_value = prior.get(value_key)
        prior_id = prior.get(f"{prefix}ID") if prefix != "BiggestDrawSum" else None
        cand_value = event_candidates.get(value_key)
        if prefix == "BiggestDrawSum":
            if cand_value is not None:
                cand_id = int(event_candidates.get("BiggestDrawSumIDA") or 0)
                prior_a = prior.get("BiggestDrawSum")
                if _beats_holder(cand_value, cand_id, prior_a, prior.get("BiggestDrawSumIDA"), higher_is_better=True):
                    for key in (
                        "BiggestDrawSum",
                        "BiggestDrawSumIDA",
                        "BiggestDrawSumIDB",
                        "BiggestDrawSumNameA",
                        "BiggestDrawSumNameB",
                        "BiggestDrawSumDate",
                        "BiggestDrawSumGameID",
                    ):
                        if key in event_candidates:
                            patch[key] = event_candidates[key]
                else:
                    for key in (
                        "BiggestDrawSum",
                        "BiggestDrawSumIDA",
                        "BiggestDrawSumIDB",
                        "BiggestDrawSumNameA",
                        "BiggestDrawSumNameB",
                        "BiggestDrawSumDate",
                        "BiggestDrawSumGameID",
                    ):
                        if prior.get(key) is not None:
                            patch[key] = prior[key]
            else:
                for key in (
                    "BiggestDrawSum",
                    "BiggestDrawSumIDA",
                    "BiggestDrawSumIDB",
                    "BiggestDrawSumNameA",
                    "BiggestDrawSumNameB",
                    "BiggestDrawSumDate",
                    "BiggestDrawSumGameID",
                ):
                    if prior.get(key) is not None:
                        patch[key] = prior[key]
            continue

        if prefix == "BiggestSumOfGoals":
            if cand_value is not None:
                cand_id = int(event_candidates.get("BiggestSumOfGoalsIDA") or 0)
                if _beats_holder(
                    cand_value,
                    cand_id,
                    prior.get("BiggestSumOfGoals"),
                    prior.get("BiggestSumOfGoalsIDA"),
                    higher_is_better=True,
                ):
                    keys = (
                        "BiggestSumOfGoals",
                        "BiggestSumOfGoalsIDA",
                        "BiggestSumOfGoalsIDB",
                        "BiggestSumOfGoalsNameA",
                        "BiggestSumOfGoalsNameB",
                        "BiggestSumOfGoalsDate",
                        "BiggestSumOfGoalsGameID",
                    )
                    patch.update({k: event_candidates[k] for k in keys if k in event_candidates})
                else:
                    keys = (
                        "BiggestSumOfGoals",
                        "BiggestSumOfGoalsIDA",
                        "BiggestSumOfGoalsIDB",
                        "BiggestSumOfGoalsNameA",
                        "BiggestSumOfGoalsNameB",
                        "BiggestSumOfGoalsDate",
                        "BiggestSumOfGoalsGameID",
                    )
                    patch.update({k: prior[k] for k in keys if prior.get(k) is not None})
            else:
                keys = (
                    "BiggestSumOfGoals",
                    "BiggestSumOfGoalsIDA",
                    "BiggestSumOfGoalsIDB",
                    "BiggestSumOfGoalsNameA",
                    "BiggestSumOfGoalsNameB",
                    "BiggestSumOfGoalsDate",
                    "BiggestSumOfGoalsGameID",
                )
                patch.update({k: prior[k] for k in keys if prior.get(k) is not None})
            continue

        id_key = f"{prefix}ID"
        cand_id = event_candidates.get(id_key)
        if cand_value is not None and cand_id is not None:
            if _beats_holder(
                cand_value,
                int(cand_id),
                prior_value,
                prior_id,
                higher_is_better=higher_is_better,
            ):
                for key in (
                    value_key,
                    id_key,
                    f"{prefix}Name",
                    f"{prefix}Date",
                    f"{prefix}GameID",
                ):
                    if key in event_candidates:
                        patch[key] = event_candidates[key]
            else:
                for key in (
                    value_key,
                    id_key,
                    f"{prefix}Name",
                    f"{prefix}Date",
                    f"{prefix}GameID",
                ):
                    if prior.get(key) is not None:
                        patch[key] = prior[key]
        else:
            for key in (
                value_key,
                id_key,
                f"{prefix}Name",
                f"{prefix}Date",
                f"{prefix}GameID",
            ):
                if prior.get(key) is not None:
                    patch[key] = prior[key]
    return patch


def build_generalstats_payload_incremental(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    prior_payload: dict[str, Any] | None = None,
    players: dict[int, PlayerState] | None = None,
    names: dict[int, str] | None = None,
    games: list[dict[str, Any]] | None = None,
    ratings_by_game_id: dict[int, dict[str, Any]] | None = None,
    event_date: Any | None = None,
) -> dict[str, Any]:
    """
    Prior realm row + tournament delta. When ``players``/``games`` are passed (replay),
    skip player_current and tournament aggregate SQL.
    """
    if prior_payload is None:
        prior_payload = load_prior_realm_payload(conn, tournament_id)

    if games is not None and ratings_by_game_id is not None:
        delta = tournament_game_aggregate_delta_from_memory(games, ratings_by_game_id)
        event_date_str = str(event_date) if event_date is not None else None
        event_candidates = _single_game_candidates_from_memory(
            games,
            ratings_by_game_id,
            names or {},
            event_date_str,
        )
    else:
        delta = tournament_game_aggregate_delta(conn, tournament_id)
        event_candidates = _single_game_candidates_from_tournament(conn, tournament_id)

    # Geo/honours holder columns live on amiga_player_current (persisted before realm row).
    holder_rows = fetch_player_current_rows(conn)
    # Aggregate player stats from SQL (matches oracle / DECIMAL storage), not Python AVG.
    num_players, diff_opp_avg = _player_count_stats_sql_present(conn)
    patch: dict[str, Any] = {}
    patch.update(_merge_game_aggregates(prior_payload, delta, num_players=num_players, diff_opp_avg=diff_opp_avg))
    patch.update(_career_holders_from_player_rows(holder_rows))
    patch.update(_ratio_leaders_from_player_rows(holder_rows))
    patch.update(_merge_single_game_records(prior_payload, event_candidates))

    return {col: patch.get(col) for col in GENERALSTATS_PAYLOAD_COLUMNS}
