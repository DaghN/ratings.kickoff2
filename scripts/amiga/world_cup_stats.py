"""Build and persist per–World Cup event stats rows."""

from __future__ import annotations

from collections import defaultdict
from datetime import datetime
from typing import Any

import pymysql

from scripts.amiga.community_game_metrics import (
    canonical_pair,
    country_token,
    is_knockout_phase,
    rate,
    rated_game_metrics_from_row,
)
from scripts.amiga.tournament_honours import compute_wc_podium_finish_from_standings, is_world_cup_tournament
from scripts.amiga.world_cup_stats_columns import WORLD_CUP_STATS_COLUMNS


def _host_city_from_name(name: str) -> str | None:
    text = str(name or "").strip()
    if "(" in text and text.endswith(")"):
        inner = text.rsplit("(", 1)[-1].rstrip(")").strip()
        return inner if inner else None
    return None


def _load_podium_player_ids(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> tuple[int | None, int | None, int | None]:
    sql = """
        SELECT scope_type, scope_key, player_id, position
        FROM amiga_tournament_standings
        WHERE tournament_id = %s
        """
    with conn.cursor() as cur:
        cur.execute(sql, (tournament_id,))
        rows = cur.fetchall()
    finish = compute_wc_podium_finish_from_standings(rows)
    gold = silver = bronze = None
    bronze_candidates: list[int] = []
    for pid, place in finish.items():
        if place == 1:
            gold = int(pid)
        elif place == 2:
            silver = int(pid)
        elif place == 3:
            bronze_candidates.append(int(pid))
    if bronze_candidates:
        bronze = min(bronze_candidates)
    return gold, silver, bronze


def _count_first_time_wc_players(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    player_ids: set[int],
    event_date: Any,
    chrono: float,
) -> int:
    if not player_ids:
        return 0
    placeholders = ", ".join(["%s"] * len(player_ids))
    sql = f"""
        SELECT DISTINCT g.player_a_id AS pid
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE g.player_a_id IN ({placeholders})
          AND t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
          AND (
            t.event_date < %s
            OR (t.event_date = %s AND (t.chrono < %s OR (t.chrono = %s AND t.id < %s)))
          )
        UNION
        SELECT DISTINCT g.player_b_id AS pid
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE g.player_b_id IN ({placeholders})
          AND t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
          AND (
            t.event_date < %s
            OR (t.event_date = %s AND (t.chrono < %s OR (t.chrono = %s AND t.id < %s)))
          )
        """
    params: list[Any] = list(player_ids) + [event_date, event_date, chrono, chrono, tournament_id]
    params += list(player_ids) + [event_date, event_date, chrono, chrono, tournament_id]
    with conn.cursor() as cur:
        cur.execute(sql, params)
        prior = {int(row["pid"]) for row in cur.fetchall()}
    return sum(1 for pid in player_ids if pid not in prior)


def _realm_games_in_year(
    conn: pymysql.connections.Connection,
    calendar_year: int | None,
    event_date: Any,
    chrono: float,
    tournament_id: int,
) -> int | None:
    if calendar_year is None:
        return None
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE YEAR(t.event_date) = %s
              AND (
                t.event_date < %s
                OR (t.event_date = %s AND (t.chrono < %s OR (t.chrono = %s AND t.id <= %s)))
              )
            """,
            (calendar_year, event_date, event_date, chrono, chrono, tournament_id),
        )
        row = cur.fetchone()
    return int(row["n"] or 0) if row else 0


def build_world_cup_stats_row(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    finalized_at: datetime | None = None,
) -> dict[str, Any] | None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, name, event_date, chrono, country, rating_finalized_at
            FROM tournaments WHERE id = %s LIMIT 1
            """,
            (tournament_id,),
        )
        tour = cur.fetchone()
    if not tour or not is_world_cup_tournament(str(tour.get("name") or "")):
        return None

    event_date = tour.get("event_date")
    chrono = float(tour.get("chrono") or 0)
    calendar_year = int(event_date.year) if event_date is not None else None
    host_country = country_token(tour.get("country"))
    host_city = _host_city_from_name(str(tour.get("name") or ""))

    sql = """
        SELECT g.id AS game_id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b, g.phase,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals, r.actual_score,
               r.dd_player_a, r.dd_player_b, r.cs_player_a, r.cs_player_b
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE g.tournament_id = %s
        ORDER BY g.game_date ASC, g.id ASC
        """
    rated_games = 0
    draws = 0
    goals = 0
    dd_slots = 0
    cs_slots = 0
    high_scoring = 0
    low_scoring = 0
    blowouts = 0
    international_games = 0
    knockout_games = 0
    group_games = 0
    players: set[int] = set()
    nationalities: set[str] = set()
    host_players: set[int] = set()
    guest_players: set[int] = set()
    pairs: set[tuple[int, int]] = set()
    nationality_pairs: set[tuple[str, str]] = set()
    games_per_player: dict[int, int] = defaultdict(int)
    opponents_per_player: dict[int, set[int]] = defaultdict(set)

    highest_sum = -1
    highest_sum_game_id: int | None = None
    lowest_sum: int | None = None
    lowest_sum_game_id: int | None = None
    biggest_margin = -1
    biggest_margin_game_id: int | None = None
    highest_draw_sum = -1
    highest_draw_game_id: int | None = None
    most_goals_one = -1
    most_goals_one_game_id: int | None = None

    with conn.cursor() as cur:
        cur.execute(sql, (tournament_id,))
        for row in cur.fetchall():
            metrics = rated_game_metrics_from_row(row)
            rated_games += 1
            goals += metrics.sum_of_goals
            dd_slots += metrics.dd_slots
            cs_slots += metrics.cs_slots
            if metrics.is_draw:
                draws += 1
                if metrics.sum_of_goals > highest_draw_sum:
                    highest_draw_sum = metrics.sum_of_goals
                    highest_draw_game_id = metrics.game_id
            if metrics.is_high_scoring:
                high_scoring += 1
            if metrics.is_low_scoring:
                low_scoring += 1
            if metrics.is_blowout:
                blowouts += 1
            if is_knockout_phase(metrics.phase):
                knockout_games += 1
            else:
                group_games += 1

            if metrics.sum_of_goals > highest_sum:
                highest_sum = metrics.sum_of_goals
                highest_sum_game_id = metrics.game_id
            if lowest_sum is None or metrics.sum_of_goals < lowest_sum:
                lowest_sum = metrics.sum_of_goals
                lowest_sum_game_id = metrics.game_id
            if metrics.margin > biggest_margin:
                biggest_margin = metrics.margin
                biggest_margin_game_id = metrics.game_id
            one_player_max = max(metrics.goals_a, metrics.goals_b)
            if one_player_max > most_goals_one:
                most_goals_one = one_player_max
                most_goals_one_game_id = metrics.game_id

            for pid, opp, country in (
                (metrics.player_a_id, metrics.player_b_id, country_token(row["country_a"])),
                (metrics.player_b_id, metrics.player_a_id, country_token(row["country_b"])),
            ):
                players.add(pid)
                games_per_player[pid] += 1
                opponents_per_player[pid].add(opp)
                if country is not None:
                    nationalities.add(country)
                    if host_country is not None and country == host_country:
                        host_players.add(pid)
                    elif host_country is not None:
                        guest_players.add(pid)
            ca = country_token(row["country_a"])
            cb = country_token(row["country_b"])
            if ca and cb:
                nationality_pairs.add(tuple(sorted((ca, cb))))
                if ca != cb:
                    international_games += 1
            pairs.add(canonical_pair(metrics.player_a_id, metrics.player_b_id))

    decided = rated_games - draws
    distinct_players = len(players)
    max_games = max(games_per_player.values()) if games_per_player else 0
    avg_games = round(sum(games_per_player.values()) / distinct_players, 3) if distinct_players else None
    avg_opponents = None
    if opponents_per_player:
        avg_opponents = round(
            sum(len(s) for s in opponents_per_player.values()) / len(opponents_per_player),
            3,
        )

    first_time_wc = _count_first_time_wc_players(conn, tournament_id, players, event_date, chrono)
    gold_id, silver_id, bronze_id = _load_podium_player_ids(conn, tournament_id)
    champion_games = games_per_player.get(gold_id, 0) if gold_id is not None else None

    realm_year_games = _realm_games_in_year(
        conn, calendar_year, event_date, chrono, tournament_id
    )
    share_year = rate(rated_games, realm_year_games or 0) if realm_year_games else None

    if finalized_at is None:
        finalized_at = tour.get("rating_finalized_at")
    if finalized_at is None:
        finalized_at = datetime.utcnow()

    guest_share = rate(len(guest_players), distinct_players)

    row: dict[str, Any] = {
        "tournament_id": tournament_id,
        "tournament_name": str(tour.get("name") or ""),
        "calendar_year": calendar_year,
        "event_date": event_date,
        "event_chrono": chrono,
        "host_country": host_country,
        "host_city": host_city,
        "rated_games": rated_games,
        "decided_games": decided,
        "draws": draws,
        "goals": goals,
        "double_digit_slots": dd_slots,
        "clean_sheet_slots": cs_slots,
        "high_scoring_games": high_scoring,
        "low_scoring_games": low_scoring,
        "blowout_games": blowouts,
        "knockout_games": knockout_games,
        "group_games": group_games,
        "goals_per_game": rate(goals, rated_games),
        "draw_rate": rate(draws, rated_games),
        "decided_rate": rate(decided, rated_games),
        "double_digit_rate": rate(dd_slots, rated_games),
        "clean_sheet_rate": rate(cs_slots, rated_games),
        "high_scoring_rate": rate(high_scoring, rated_games),
        "low_scoring_rate": rate(low_scoring, rated_games),
        "blowout_rate": rate(blowouts, rated_games),
        "distinct_players": distinct_players,
        "distinct_player_nationalities": len(nationalities),
        "max_games_one_player": max_games,
        "first_time_wc_players": first_time_wc,
        "distinct_opponent_pairs": len(pairs),
        "avg_games_per_player": avg_games,
        "avg_opponents_per_player": avg_opponents,
        "distinct_host_country_players": len(host_players),
        "distinct_guest_players": len(guest_players),
        "guest_player_share": guest_share,
        "distinct_opponent_countries_pairs": len(nationality_pairs),
        "international_games": international_games,
        "international_game_share": rate(international_games, rated_games),
        "highest_goal_sum": highest_sum if rated_games else None,
        "highest_goal_sum_game_id": highest_sum_game_id,
        "lowest_goal_sum": lowest_sum,
        "lowest_goal_sum_game_id": lowest_sum_game_id,
        "biggest_margin": biggest_margin if rated_games else None,
        "biggest_margin_game_id": biggest_margin_game_id,
        "highest_scoring_draw_sum": highest_draw_sum if highest_draw_sum >= 0 else None,
        "highest_scoring_draw_game_id": highest_draw_game_id,
        "most_goals_one_player_game": most_goals_one if most_goals_one >= 0 else None,
        "most_goals_one_player_game_id": most_goals_one_game_id,
        "gold_player_id": gold_id,
        "silver_player_id": silver_id,
        "bronze_player_id": bronze_id,
        "champion_game_count": champion_games,
        "share_of_year_games": share_year,
        "finalized_at": finalized_at,
    }
    missing = [c for c in WORLD_CUP_STATS_COLUMNS if c not in row]
    if missing:
        raise RuntimeError(f"world cup stats row missing columns: {missing}")
    return row


def persist_world_cup_stats_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    finalized_at: datetime | None = None,
    commit: bool = True,
) -> bool:
    row = build_world_cup_stats_row(conn, tournament_id, finalized_at=finalized_at)
    if row is None:
        return False
    col_list = ", ".join(f"`{c}`" for c in WORLD_CUP_STATS_COLUMNS)
    placeholders = ", ".join(["%s"] * len(WORLD_CUP_STATS_COLUMNS))
    updates = ", ".join(
        f"`{c}` = VALUES(`{c}`)" for c in WORLD_CUP_STATS_COLUMNS if c != "tournament_id"
    )
    sql = (
        f"INSERT INTO amiga_world_cup_stats ({col_list}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )
    values = [row[c] for c in WORLD_CUP_STATS_COLUMNS]
    with conn.cursor() as cur:
        cur.execute(sql, values)
    if commit:
        conn.commit()
    return True
