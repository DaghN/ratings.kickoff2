"""Calendar-year peaks and geography scalars for Amiga player snapshots."""

from __future__ import annotations

from collections import defaultdict
from datetime import date, datetime
from typing import Any

# Geography metrics tracked for HoF last-rise dates (policy: amiga-hof-record-date-policy.md).
GEO_RISE_METRICS: tuple[str, ...] = (
    "countries_played_in",
    "opponent_countries_faced",
    "opponent_countries_beaten",
)


def normalize_country(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text if text else None


def calendar_year(event_date: Any) -> int | None:
    if event_date is None:
        return None
    if isinstance(event_date, datetime):
        return int(event_date.year)
    if isinstance(event_date, date):
        return int(event_date.year)
    text = str(event_date).strip()
    if len(text) >= 4 and text[:4].isdigit():
        return int(text[:4])
    return None


def year_period_end(year: int | None) -> str | None:
    if year is None:
        return None
    return f"{int(year)}-12-31"


def _empty_rise_fields() -> dict[str, Any]:
    out: dict[str, Any] = {}
    for metric in GEO_RISE_METRICS:
        out[f"{metric}_last_rise_tournament_id"] = None
        out[f"{metric}_last_rise_event_date"] = None
    return out


class PlayerGeoYearTracker:
    """Incremental year buckets + geography sets carried across tournament finalize."""

    def __init__(self) -> None:
        self._year_buckets: dict[int, dict[int, dict[str, int]]] = defaultdict(
            lambda: defaultdict(lambda: {"games": 0, "tournaments": 0})
        )
        self._host_countries: dict[int, set[str]] = defaultdict(set)
        self._opponent_faced: dict[int, set[str]] = defaultdict(set)
        self._opponent_beaten: dict[int, set[str]] = defaultdict(set)
        self._opponent_beaten_by: dict[int, set[str]] = defaultdict(set)
        self._rise_tournament_id: dict[int, dict[str, int | None]] = defaultdict(dict)
        self._rise_event_date: dict[int, dict[str, Any]] = defaultdict(dict)

    def apply_tournament(
        self,
        *,
        tournament_id: int,
        event_date: Any,
        host_country: Any,
        games: list[dict[str, Any]],
        games_in_event: dict[int, int],
        participant_ids: set[int],
        player_countries: dict[int, str | None],
    ) -> None:
        affected: set[int] = set()
        for pid in participant_ids:
            if int(games_in_event.get(pid, 0) or 0) > 0:
                affected.add(int(pid))
        for game in games:
            affected.add(int(game["idA"]))
            affected.add(int(game["idB"]))

        prior_counts = {
            pid: self._display_geo_counts(pid, player_countries.get(pid))
            for pid in affected
        }

        year = calendar_year(event_date)
        host = normalize_country(host_country)

        for pid in participant_ids:
            games_n = int(games_in_event.get(pid, 0) or 0)
            if games_n <= 0:
                continue
            if host:
                self._host_countries[pid].add(host)
                bucket = self._year_buckets[pid][year]
                bucket["games"] += games_n
                bucket["tournaments"] += 1

        for game in games:
            id_a = int(game["idA"])
            id_b = int(game["idB"])
            goals_a = int(game["GoalsA"])
            goals_b = int(game["GoalsB"])
            country_a = normalize_country(player_countries.get(id_a))
            country_b = normalize_country(player_countries.get(id_b))

            if country_b:
                self._opponent_faced[id_a].add(country_b)
            if country_a:
                self._opponent_faced[id_b].add(country_a)

            if goals_a > goals_b and country_b:
                self._opponent_beaten[id_a].add(country_b)
            elif goals_b > goals_a and country_a:
                self._opponent_beaten[id_b].add(country_a)
            if goals_a < goals_b and country_b:
                self._opponent_beaten_by[id_a].add(country_b)
            elif goals_b < goals_a and country_a:
                self._opponent_beaten_by[id_b].add(country_a)

        for pid in affected:
            after = self._display_geo_counts(pid, player_countries.get(pid))
            before = prior_counts[pid]
            for metric in GEO_RISE_METRICS:
                if after[metric] > before[metric]:
                    self._rise_tournament_id[pid][metric] = int(tournament_id)
                    self._rise_event_date[pid][metric] = event_date

    def scalars_for(self, player_id: int, own_country: Any) -> dict[str, Any]:
        counts = self._display_geo_counts(player_id, own_country)
        peak_games, peak_games_year = self._peak_for(player_id, "games")
        peak_events, peak_events_year = self._peak_for(player_id, "tournaments")

        out: dict[str, Any] = {
            "peak_year_games": peak_games,
            "peak_year_games_year": peak_games_year,
            "peak_year_tournaments": peak_events,
            "peak_year_tournaments_year": peak_events_year,
            "countries_played_in": counts["countries_played_in"],
            "opponent_countries_faced": counts["opponent_countries_faced"],
            "opponent_countries_beaten": counts["opponent_countries_beaten"],
            "opponent_countries_beaten_by": counts["opponent_countries_beaten_by"],
            **_empty_rise_fields(),
        }
        for metric in GEO_RISE_METRICS:
            out[f"{metric}_last_rise_tournament_id"] = self._rise_tournament_id.get(
                player_id, {}
            ).get(metric)
            out[f"{metric}_last_rise_event_date"] = self._rise_event_date.get(
                player_id, {}
            ).get(metric)
        return out

    def _display_geo_counts(self, player_id: int, own_country: Any) -> dict[str, int]:
        """Counts matching ``scalars_for`` display (game/event evidence only)."""
        return {
            "countries_played_in": len(self._host_countries.get(player_id, set())),
            "opponent_countries_faced": len(self._opponent_faced.get(player_id, set())),
            "opponent_countries_beaten": len(self._opponent_beaten.get(player_id, set())),
            "opponent_countries_beaten_by": len(self._opponent_beaten_by.get(player_id, set())),
        }

    def _peak_for(self, player_id: int, key: str) -> tuple[int, int | None]:
        buckets = self._year_buckets.get(player_id)
        if not buckets:
            return 0, None
        best_value = 0
        best_years: list[int] = []
        for yr, bucket in buckets.items():
            value = int(bucket.get(key, 0) or 0)
            if value > best_value:
                best_value = value
                best_years = [int(yr)]
            elif value == best_value and value > 0:
                best_years.append(int(yr))
        if best_value <= 0 or not best_years:
            return 0, None
        return best_value, min(best_years)


def load_player_countries(conn) -> dict[int, str | None]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, country FROM amiga_players")
        rows = cur.fetchall()
    return {int(row["id"]): row.get("country") for row in rows}
