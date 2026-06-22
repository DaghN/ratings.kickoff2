"""Community dimensional fact rows at tournament cutoff."""

from __future__ import annotations

from collections import defaultdict
from typing import Any

import pymysql

from scripts.amiga.community_stat_registry import (
    ALL_TIME_PERIOD_KEY,
    REALM_SLICE_KEY,
    V1_FACT_SPECS,
    CommunityFactSpec,
)
from scripts.amiga.community_stats_columns import COMMUNITY_FACT_COLUMNS
from scripts.amiga.realm_cutoff import cutoff_params, game_cutoff_sql, load_realm_cutoff


def _country_token(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text if text else None


def _year_key(event_date: Any) -> str | None:
    if event_date is None:
        return None
    return str(event_date.year)


class _FactAccum:
    def __init__(self) -> None:
        self._values: dict[tuple[str, str, str, str, str, str], float] = defaultdict(float)
        self._active_players: dict[tuple[str, str], set[int]] = defaultdict(set)

    def _key(
        self,
        spec: CommunityFactSpec,
        period_key: str,
        slice_key: str,
    ) -> tuple[str, str, str, str, str, str]:
        return (
            spec.period_type,
            period_key,
            spec.slice_type,
            slice_key,
            spec.metric_key,
            spec.count_basis,
        )

    def add_game(
        self,
        *,
        year: str | None,
        host_country: str | None,
        player_a_id: int,
        player_b_id: int,
        country_a: str | None,
        country_b: str | None,
        goals_a: int,
        goals_b: int,
        sum_of_goals: int,
    ) -> None:
        if year is not None:
            for spec in V1_FACT_SPECS:
                if spec.period_type != "year":
                    continue
                if spec.slice_type == "realm":
                    if spec.metric_key == "games":
                        self._values[self._key(spec, year, REALM_SLICE_KEY)] += 1
                    elif spec.metric_key == "goals":
                        self._values[self._key(spec, year, REALM_SLICE_KEY)] += sum_of_goals
                    elif spec.metric_key == "active_players":
                        self._active_players[(spec.period_type, year)].add(player_a_id)
                        self._active_players[(spec.period_type, year)].add(player_b_id)
                elif spec.slice_type == "host_country" and host_country is not None:
                    if spec.metric_key == "games":
                        self._values[self._key(spec, year, host_country)] += 1
                elif spec.slice_type == "player_nationality":
                    for country, player_id, goals in (
                        (country_a, player_a_id, goals_a),
                        (country_b, player_b_id, goals_b),
                    ):
                        if country is None:
                            continue
                        if spec.metric_key == "games":
                            self._values[self._key(spec, year, country)] += 1
                        elif spec.metric_key == "goals":
                            self._values[self._key(spec, year, country)] += goals

        for spec in V1_FACT_SPECS:
            if spec.period_type != "all_time":
                continue
            if spec.slice_type == "host_country" and host_country is not None:
                if spec.metric_key == "games":
                    self._values[self._key(spec, ALL_TIME_PERIOD_KEY, host_country)] += 1
            elif spec.slice_type == "player_nationality":
                for country in (country_a, country_b):
                    if country is None:
                        continue
                    if spec.metric_key == "games":
                        self._values[
                            self._key(spec, ALL_TIME_PERIOD_KEY, country)
                        ] += 1

    def rows(self, tournament_id: int) -> list[dict[str, Any]]:
        out: list[dict[str, Any]] = []
        for key, value in self._values.items():
            if value == 0:
                continue
            period_type, period_key, slice_type, slice_key, metric_key, count_basis = key
            out.append(
                {
                    "tournament_id": tournament_id,
                    "period_type": period_type,
                    "period_key": period_key,
                    "slice_type": slice_type,
                    "slice_key": slice_key,
                    "metric_key": metric_key,
                    "count_basis": count_basis,
                    "value": value,
                }
            )
        for (period_type, period_key), players in self._active_players.items():
            if not players:
                continue
            out.append(
                {
                    "tournament_id": tournament_id,
                    "period_type": period_type,
                    "period_key": period_key,
                    "slice_type": "realm",
                    "slice_key": REALM_SLICE_KEY,
                    "metric_key": "active_players",
                    "count_basis": "game",
                    "value": float(len(players)),
                }
            )
        return out


def build_community_facts_at_cutoff(
    conn: pymysql.connections.Connection,
    as_of_tournament_id: int,
) -> list[dict[str, Any]]:
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    params = cutoff_params(cutoff)
    cutoff_where = game_cutoff_sql("t")
    sql = f"""
        SELECT g.player_a_id, g.player_b_id, g.goals_a, g.goals_b,
               t.event_date, t.country AS host_country,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE {cutoff_where}
        ORDER BY g.game_date ASC, g.id ASC
        """
    accum = _FactAccum()
    with conn.cursor() as cur:
        cur.execute(sql, params)
        for row in cur.fetchall():
            accum.add_game(
                year=_year_key(row["event_date"]),
                host_country=_country_token(row["host_country"]),
                player_a_id=int(row["player_a_id"]),
                player_b_id=int(row["player_b_id"]),
                country_a=_country_token(row["country_a"]),
                country_b=_country_token(row["country_b"]),
                goals_a=int(row["goals_a"]),
                goals_b=int(row["goals_b"]),
                sum_of_goals=int(row["sum_of_goals"] or 0),
            )
    return accum.rows(as_of_tournament_id)


def fact_row_values(row: dict[str, Any]) -> list[Any]:
    return [row[col] for col in COMMUNITY_FACT_COLUMNS]
