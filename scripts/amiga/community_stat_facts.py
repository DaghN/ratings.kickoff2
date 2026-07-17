"""Community dimensional fact rows at tournament cutoff."""

from __future__ import annotations

from collections import defaultdict
from dataclasses import dataclass
from typing import Any

import pymysql

from scripts.amiga.community_game_metrics import (
    canonical_pair,
    country_token,
    rated_game_metrics_from_row,
    year_key,
)
from scripts.amiga.community_stat_registry import (
    ALL_TIME_PERIOD_KEY,
    FACT_SPECS,
    PER_GAME_FACT_SPECS,
    REALM_SLICE_KEY,
    WORLD_CUP_SLICE_KEY,
    CommunityFactSpec,
)
from scripts.amiga.community_stats_columns import COMMUNITY_FACT_COLUMNS
from scripts.amiga.realm_cutoff import cutoff_params, game_cutoff_sql, load_realm_cutoff, tournament_cutoff_params
from scripts.amiga.tournament_honours import is_world_cup_tournament, tournament_is_world_cup


@dataclass
class CommunityRealmScan:
    facts: list[dict[str, Any]]
    tournaments_finalized: int
    distinct_host_countries: int
    wc_games_played: int
    distinct_opponent_pairs: int
    players_debuted: int


class _FactAccum:
    def __init__(self) -> None:
        self._values: dict[tuple[str, str, str, str, str, str], float] = defaultdict(float)
        self._active_players: dict[tuple[str, str, str], set[int]] = defaultdict(set)
        self._active_players_by_nationality: dict[tuple[str, str], set[int]] = defaultdict(set)
        self._active_players_wc_by_nationality: dict[tuple[str, str], set[int]] = defaultdict(set)
        self._active_players_by_nationality_all_time: dict[str, set[int]] = defaultdict(set)
        self._debut_country_by_player: dict[int, str] = {}
        self._nationalities_by_year: dict[str, set[str]] = defaultdict(set)
        self._nationalities_wc_by_year: dict[str, set[str]] = defaultdict(set)
        self._host_countries_by_year: dict[str, set[str]] = defaultdict(set)
        self._pairs_by_year: dict[str, set[tuple[int, int]]] = defaultdict(set)
        self._pairs_cumulative: set[tuple[int, int]] = set()
        self._debut_year_by_player: dict[int, str] = {}
        self._wc_games_played: int = 0

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

    def _bump(self, spec: CommunityFactSpec, period_key: str, slice_key: str, delta: float) -> None:
        if delta == 0:
            return
        self._values[self._key(spec, period_key, slice_key)] += delta

    def add_game(
        self,
        *,
        year: str | None,
        host_country: str | None,
        country_a: str | None,
        country_b: str | None,
        player_a_id: int,
        player_b_id: int,
        goals_a: int,
        goals_b: int,
        metrics: Any,
        is_wc: bool,
    ) -> None:
        if year is not None:
            for spec in PER_GAME_FACT_SPECS:
                if spec.period_type != "year":
                    continue
                if spec.slice_type == "realm":
                    if spec.metric_key == "games":
                        self._bump(spec, year, REALM_SLICE_KEY, 1)
                    elif spec.metric_key == "goals":
                        self._bump(spec, year, REALM_SLICE_KEY, metrics.sum_of_goals)
                    elif spec.metric_key == "draws" and metrics.is_draw:
                        self._bump(spec, year, REALM_SLICE_KEY, 1)
                    elif spec.metric_key == "double_digits":
                        self._bump(spec, year, REALM_SLICE_KEY, metrics.dd_slots)
                    elif spec.metric_key == "clean_sheets":
                        self._bump(spec, year, REALM_SLICE_KEY, metrics.cs_slots)
                    elif spec.metric_key == "high_scoring_games" and metrics.is_high_scoring:
                        self._bump(spec, year, REALM_SLICE_KEY, 1)
                    elif spec.metric_key == "low_scoring_games" and metrics.is_low_scoring:
                        self._bump(spec, year, REALM_SLICE_KEY, 1)
                elif spec.slice_type == "host_country" and host_country is not None:
                    if spec.metric_key == "games":
                        self._bump(spec, year, host_country, 1)
                    elif spec.metric_key == "goals":
                        self._bump(spec, year, host_country, goals_a + goals_b)
                elif spec.slice_type == "player_nationality":
                    for country, goals in ((country_a, goals_a), (country_b, goals_b)):
                        if country is None:
                            continue
                        if spec.metric_key == "games":
                            self._bump(spec, year, country, 1)
                        elif spec.metric_key == "goals":
                            self._bump(spec, year, country, goals)
                elif spec.slice_type == "world_cup" and is_wc:
                    if spec.metric_key == "games":
                        self._bump(spec, year, WORLD_CUP_SLICE_KEY, 1)
                    elif spec.metric_key == "goals":
                        self._bump(spec, year, WORLD_CUP_SLICE_KEY, metrics.sum_of_goals)

            for country in (country_a, country_b):
                if country is not None:
                    self._nationalities_by_year[year].add(country)
            if is_wc:
                for country in (country_a, country_b):
                    if country is not None:
                        self._nationalities_wc_by_year[year].add(country)

            pair = canonical_pair(player_a_id, player_b_id)
            self._pairs_by_year[year].add(pair)
            self._pairs_cumulative.add(pair)

            for player_id, country in ((player_a_id, country_a), (player_b_id, country_b)):
                if player_id not in self._debut_year_by_player:
                    self._debut_year_by_player[player_id] = year
                    if country is not None:
                        self._debut_country_by_player[player_id] = country

            self._active_players[("year", year, "realm")].add(player_a_id)
            self._active_players[("year", year, "realm")].add(player_b_id)
            if country_a is not None:
                self._active_players_by_nationality[(year, country_a)].add(player_a_id)
                self._active_players_by_nationality_all_time[country_a].add(player_a_id)
            if country_b is not None:
                self._active_players_by_nationality[(year, country_b)].add(player_b_id)
                self._active_players_by_nationality_all_time[country_b].add(player_b_id)
            if is_wc:
                self._active_players[("year", year, "world_cup")].add(player_a_id)
                self._active_players[("year", year, "world_cup")].add(player_b_id)
                if country_a is not None:
                    self._active_players_wc_by_nationality[(year, country_a)].add(player_a_id)
                if country_b is not None:
                    self._active_players_wc_by_nationality[(year, country_b)].add(player_b_id)

        if is_wc:
            self._wc_games_played += 1

        for spec in PER_GAME_FACT_SPECS:
            if spec.period_type != "all_time":
                continue
            if spec.slice_type == "host_country" and host_country is not None:
                if spec.metric_key == "games":
                    self._bump(spec, ALL_TIME_PERIOD_KEY, host_country, 1)
                elif spec.metric_key == "goals":
                    self._bump(spec, ALL_TIME_PERIOD_KEY, host_country, goals_a + goals_b)
            elif spec.slice_type == "player_nationality":
                for country, goals in ((country_a, goals_a), (country_b, goals_b)):
                    if country is None:
                        continue
                    if spec.metric_key == "games":
                        self._bump(spec, ALL_TIME_PERIOD_KEY, country, 1)
                    elif spec.metric_key == "goals":
                        self._bump(spec, ALL_TIME_PERIOD_KEY, country, goals)

    def add_tournament_year(self, year: str, host_country: str | None) -> None:
        for spec in FACT_SPECS:
            if spec.period_type == "year" and spec.slice_type == "realm" and spec.metric_key == "tournaments":
                self._bump(spec, year, REALM_SLICE_KEY, 1)
            if (
                spec.period_type == "year"
                and spec.slice_type == "host_country"
                and spec.metric_key == "tournaments"
                and host_country is not None
            ):
                self._bump(spec, year, host_country, 1)
        if host_country is not None:
            self._host_countries_by_year[year].add(host_country)

    def add_all_time_tournament(self, host_country: str | None) -> None:
        if host_country is None:
            return
        for spec in FACT_SPECS:
            if (
                spec.period_type == "all_time"
                and spec.slice_type == "host_country"
                and spec.metric_key == "tournaments"
            ):
                self._bump(spec, ALL_TIME_PERIOD_KEY, host_country, 1)

    def finalize_rows(self, tournament_id: int) -> CommunityRealmScan:
        for (period_type, period_key, slice_kind), players in self._active_players.items():
            if not players:
                continue
            slice_type = "realm" if slice_kind == "realm" else "world_cup"
            slice_key = REALM_SLICE_KEY if slice_type == "realm" else WORLD_CUP_SLICE_KEY
            self._values[
                (period_type, period_key, slice_type, slice_key, "active_players", "game")
            ] = float(len(players))

        for (year, country), players in self._active_players_by_nationality.items():
            if players:
                self._values[
                    ("year", year, "player_nationality", country, "active_players", "participant")
                ] = float(len(players))

        for (year, country), players in self._active_players_wc_by_nationality.items():
            if players:
                self._values[
                    ("year", year, "player_nationality", country, "wc_active_players", "participant")
                ] = float(len(players))

        for country, players in self._active_players_by_nationality_all_time.items():
            if players:
                self._values[
                    (
                        "all_time",
                        ALL_TIME_PERIOD_KEY,
                        "player_nationality",
                        country,
                        "active_players",
                        "participant",
                    )
                ] = float(len(players))

        debut_nat_counts: dict[tuple[str, str], int] = defaultdict(int)
        for player_id, debut_year in self._debut_year_by_player.items():
            country = self._debut_country_by_player.get(player_id)
            if country is not None:
                debut_nat_counts[(debut_year, country)] += 1
        for (year, country), count in debut_nat_counts.items():
            if count:
                self._values[
                    ("year", year, "player_nationality", country, "player_debuts", "participant")
                ] = float(count)

        for year, countries in self._nationalities_by_year.items():
            if countries:
                self._values[
                    ("year", year, "realm", REALM_SLICE_KEY, "distinct_nationalities", "game")
                ] = float(len(countries))
        for year, countries in self._nationalities_wc_by_year.items():
            if countries:
                self._values[
                    ("year", year, "world_cup", WORLD_CUP_SLICE_KEY, "distinct_nationalities", "game")
                ] = float(len(countries))
        for year, countries in self._host_countries_by_year.items():
            if countries:
                self._values[
                    ("year", year, "realm", REALM_SLICE_KEY, "distinct_host_countries", "game")
                ] = float(len(countries))
        for year, pairs in self._pairs_by_year.items():
            if pairs:
                self._values[
                    ("year", year, "realm", REALM_SLICE_KEY, "distinct_pairs", "game")
                ] = float(len(pairs))

        debut_counts: dict[str, int] = defaultdict(int)
        for debut_year in self._debut_year_by_player.values():
            debut_counts[debut_year] += 1
        for year, count in debut_counts.items():
            if count:
                self._values[
                    ("year", year, "realm", REALM_SLICE_KEY, "player_debuts", "game")
                ] = float(count)

        facts: list[dict[str, Any]] = []
        for key, value in self._values.items():
            if value == 0:
                continue
            period_type, period_key, slice_type, slice_key, metric_key, count_basis = key
            facts.append(
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

        return CommunityRealmScan(
            facts=facts,
            tournaments_finalized=0,
            distinct_host_countries=0,
            wc_games_played=self._wc_games_played,
            distinct_opponent_pairs=len(self._pairs_cumulative),
            players_debuted=len(self._debut_year_by_player),
        )


def _load_tournaments_at_cutoff(
    conn: pymysql.connections.Connection,
    cutoff: Any,
) -> tuple[int, int]:
    # Include as_of tournament even when rating_finalized is still 0
    # (PHP sets the flag only after community writers succeed — limbo safety).
    params = (cutoff.tournament_id,) + tournament_cutoff_params(cutoff)
    sql = """
        SELECT t.event_date, t.country
        FROM tournaments t
        WHERE (t.rating_finalized = 1 OR t.id = %s)
          AND (
            t.event_date < %s
            OR (t.event_date = %s AND (t.chrono < %s OR (t.chrono = %s AND t.id <= %s)))
          )
        ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC
        """
    host_countries: set[str] = set()
    with conn.cursor() as cur:
        cur.execute(sql, params)
        rows = cur.fetchall()
    for row in rows:
        host = country_token(row.get("country"))
        if host is not None:
            host_countries.add(host)
    return len(rows), len(host_countries)


def build_community_realm_scan(
    conn: pymysql.connections.Connection,
    as_of_tournament_id: int,
) -> CommunityRealmScan:
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    params = cutoff_params(cutoff)
    tour_params = tournament_cutoff_params(cutoff)
    cutoff_where = game_cutoff_sql("t")
    sql = f"""
        SELECT g.id AS game_id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b, g.phase,
               t.event_date, t.country AS host_country, t.name AS tournament_name, t.is_world_cup,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals, r.actual_score,
               r.dd_player_a, r.dd_player_b, r.cs_player_a, r.cs_player_b
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
            year = year_key(row["event_date"])
            host_country = country_token(row["host_country"])
            metrics = rated_game_metrics_from_row(row)
            is_wc = tournament_is_world_cup(row)
            accum.add_game(
                year=year,
                host_country=host_country,
                country_a=country_token(row["country_a"]),
                country_b=country_token(row["country_b"]),
                player_a_id=int(row["player_a_id"]),
                player_b_id=int(row["player_b_id"]),
                goals_a=int(row["goals_a"]),
                goals_b=int(row["goals_b"]),
                metrics=metrics,
                is_wc=is_wc,
            )

        cur.execute(
            """
            SELECT t.event_date, t.country
            FROM tournaments t
            WHERE (t.rating_finalized = 1 OR t.id = %s)
              AND (
                t.event_date < %s
                OR (t.event_date = %s AND (t.chrono < %s OR (t.chrono = %s AND t.id <= %s)))
              )
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC
            """,
            (as_of_tournament_id,) + tour_params,
        )
        for row in cur.fetchall():
            year = year_key(row["event_date"])
            host = country_token(row.get("country"))
            if year is not None:
                accum.add_tournament_year(year, host)
            accum.add_all_time_tournament(host)

    scan = accum.finalize_rows(as_of_tournament_id)
    tour_count, host_count = _load_tournaments_at_cutoff(conn, cutoff)
    scan.tournaments_finalized = tour_count
    scan.distinct_host_countries = host_count
    return scan


def build_community_facts_at_cutoff(
    conn: pymysql.connections.Connection,
    as_of_tournament_id: int,
) -> list[dict[str, Any]]:
    return build_community_realm_scan(conn, as_of_tournament_id).facts


def fact_row_values(row: dict[str, Any]) -> list[Any]:
    return [row[col] for col in COMMUNITY_FACT_COLUMNS]
