#!/usr/bin/env python3
"""Verify geo/year player scalars, rise dates, and HoF holders (policy: amiga-hof-tournament-geo-policy.md)."""

from __future__ import annotations

import sys
from datetime import date, datetime
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import GAME_SELECT_FOR_TOURNAMENT
from scripts.amiga.career_rise import (
    CAREER_RISE_PLAYER_COLUMNS,
    CAREER_RISE_SPECS,
    apply_career_rise_fields,
    empty_career_rise_state,
    prior_career_values_from_row,
)
from scripts.amiga.generalstats_columns import (
    GEO_RISE_PLAYER_COLUMNS,
    HONOURS_RISE_PLAYER_COLUMNS,
    RECORD_RISE_PLAYER_COLUMNS,
)
from scripts.amiga.honours_totals import empty_honours_totals, increment_honours_totals
from scripts.amiga.player_geo_year import PlayerGeoYearTracker, load_player_countries
from scripts.amiga.realm_incremental import _career_holders_from_player_rows
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id, load_realm_cutoff
from scripts.amiga.server_records import _CAREER_HOLDERS, _load_cutoff_player_rows

_ALKIS_PLAYER_ID = 14
_ALKIS_EVENT_GOLD_RISE_DATE = "2025-09-20"

_GEO_YEAR_COLUMNS = (
    "peak_year_games",
    "peak_year_games_year",
    "peak_year_tournaments",
    "peak_year_tournaments_year",
    "countries_played_in",
    "opponent_countries_faced",
    "opponent_countries_beaten",
)

_HOF_GEO_HONOURS_PREFIXES = tuple(prefix for _v, _c, prefix in _CAREER_HOLDERS if prefix in {
    "MostGamesInOneYear",
    "MostTournamentsInOneYear",
    "MostTournamentsPlayed",
    "MostTournamentWins",
    "MostPerfectEvents",
    "MostCountriesPlayedIn",
    "MostOpponentCountriesFaced",
    "MostOpponentCountriesBeaten",
})
_CAREER_RISE_HOF_PREFIXES = tuple(spec[2] for spec in CAREER_RISE_SPECS)
_HOF_PREFIXES = _HOF_GEO_HONOURS_PREFIXES + _CAREER_RISE_HOF_PREFIXES


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


def _norm_tid(value: Any) -> int | None:
    if value is None:
        return None
    return int(value)


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


def _finalized_tournament_ids(conn: pymysql.connections.Connection) -> list[int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id FROM tournaments
            WHERE rating_finalized = 1
            ORDER BY event_date ASC, chrono ASC, id ASC
            """
        )
        return [int(row["id"]) for row in cur.fetchall()]


def _oracle_honours_by_player(conn: pymysql.connections.Connection) -> dict[int, dict[str, Any]]:
    totals_by_player: dict[int, dict[str, Any]] = {}
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.player_id, s.tournament_id, s.event_date, s.tournament_name,
                   s.event_finish_position, s.is_winner, s.is_perfect_event
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 1
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, s.player_id ASC
            """
        )
        rows = cur.fetchall()

    for row in rows:
        pid = int(row["player_id"])
        if pid not in totals_by_player:
            totals_by_player[pid] = empty_honours_totals()
        increment_honours_totals(totals_by_player[pid], row)
    return totals_by_player


def _oracle_geo_by_player(conn: pymysql.connections.Connection) -> dict[int, dict[str, int | None]]:
    player_countries = load_player_countries(conn)
    tracker = PlayerGeoYearTracker()
    tournament_ids = _finalized_tournament_ids(conn)

    for tournament_id in tournament_ids:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT event_date, country FROM tournaments WHERE id = %s LIMIT 1",
                (tournament_id,),
            )
            tour = cur.fetchone()
            cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
            games = cur.fetchall()
            cur.execute(
                """
                SELECT player_id, games_in_event
                FROM amiga_player_event_snapshots
                WHERE tournament_id = %s
                """,
                (tournament_id,),
            )
            snap_rows = cur.fetchall()

        games_in_event = {int(r["player_id"]): int(r["games_in_event"] or 0) for r in snap_rows}
        participant_ids = set(games_in_event.keys())
        tracker.apply_tournament(
            tournament_id=tournament_id,
            event_date=tour["event_date"] if tour else None,
            host_country=tour["country"] if tour else None,
            games=games,
            games_in_event=games_in_event,
            participant_ids=participant_ids,
            player_countries=player_countries,
        )

    out: dict[int, dict[str, int | None]] = {}
    for pid in set(player_countries) | set(tracker._year_buckets) | set(tracker._host_countries):
        if pid not in player_countries and pid not in tracker._host_countries:
            continue
        scalars = tracker.scalars_for(pid, player_countries.get(pid))
        if any(int(scalars.get(col) or 0) > 0 for col in _GEO_YEAR_COLUMNS if not col.endswith("_year")):
            out[pid] = scalars
    return out


def _oracle_career_rise_by_player(conn: pymysql.connections.Connection) -> dict[int, dict[str, Any]]:
    rise_by_player: dict[int, dict[str, Any]] = {}
    career_by_player: dict[int, dict[str, int | float]] = {}
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.*, t.event_date
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 1
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, s.player_id ASC
            """
        )
        rows = cur.fetchall()

    for row in rows:
        pid = int(row["player_id"])
        prior_career = career_by_player.get(pid, prior_career_values_from_row({}))
        rise_state = rise_by_player.get(pid, empty_career_rise_state())
        new_career = prior_career_values_from_row(row)
        rise_by_player[pid] = apply_career_rise_fields(
            rise_state,
            prior_career,
            new_career,
            tournament_id=int(row["tournament_id"]),
            event_date=row.get("event_date"),
        )
        career_by_player[pid] = new_career
    return rise_by_player


def _expected_rise_fields(
    honours_oracle: dict[int, dict[str, Any]],
    geo_oracle: dict[int, dict[str, int | None]],
    career_rise_oracle: dict[int, dict[str, Any]],
    player_id: int,
) -> dict[str, Any]:
    expected: dict[str, Any] = {}
    honours = honours_oracle.get(player_id, empty_honours_totals())
    for col in HONOURS_RISE_PLAYER_COLUMNS:
        expected[col] = honours.get(col)
    geo = geo_oracle.get(player_id, {})
    for col in GEO_RISE_PLAYER_COLUMNS:
        expected[col] = geo.get(col) if geo else None
    career = career_rise_oracle.get(player_id, {})
    for col in CAREER_RISE_PLAYER_COLUMNS:
        expected[col] = career.get(col)
    return expected


def _alkis_regression_checks(
    honours_oracle: dict[int, dict[str, Any]],
    gst: dict[str, Any],
) -> list[str]:
    errors: list[str] = []
    alkis = honours_oracle.get(_ALKIS_PLAYER_ID)
    if alkis is None:
        return errors

    rise_date = _norm_date(alkis.get("event_gold_last_rise_event_date"))
    if int(alkis.get("event_gold") or 0) >= 58 and rise_date != _ALKIS_EVENT_GOLD_RISE_DATE:
        errors.append(
            f"Alkis regression player_id={_ALKIS_PLAYER_ID}: "
            f"event_gold_last_rise_event_date oracle={rise_date!r} "
            f"expected {_ALKIS_EVENT_GOLD_RISE_DATE!r}"
        )

    if int(gst.get("MostTournamentWinsID") or 0) == _ALKIS_PLAYER_ID:
        gst_date = _norm_date(gst.get("MostTournamentWinsDate"))
        if gst_date != _ALKIS_EVENT_GOLD_RISE_DATE:
            errors.append(
                f"Alkis regression: MostTournamentWinsDate gst={gst_date!r} "
                f"expected {_ALKIS_EVENT_GOLD_RISE_DATE!r}"
            )
    return errors


def verify_hof_geo_year(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    honours_oracle = _oracle_honours_by_player(conn)
    geo_oracle = _oracle_geo_by_player(conn)
    career_rise_oracle = _oracle_career_rise_by_player(conn)

    with conn.cursor() as cur:
        cur.execute(
            "SELECT player_id, "
            + ", ".join(_GEO_YEAR_COLUMNS)
            + " FROM amiga_player_current"
        )
        current_rows = cur.fetchall()

    for row in current_rows:
        pid = int(row["player_id"])
        expected = geo_oracle.get(pid, {col: 0 if not col.endswith("_year") else None for col in _GEO_YEAR_COLUMNS})
        for col in _GEO_YEAR_COLUMNS:
            stored = row.get(col)
            exp = expected.get(col)
            if col.endswith("_year"):
                if stored is None and exp is None:
                    continue
                if int(stored or 0) != int(exp or 0):
                    errors.append(f"player_id={pid} {col}: stored={stored!r} oracle={exp!r}")
            else:
                if int(stored or 0) != int(exp or 0):
                    errors.append(f"player_id={pid} {col}: stored={stored!r} oracle={exp!r}")

    with conn.cursor() as cur:
        cur.execute(
            "SELECT player_id, "
            + ", ".join(RECORD_RISE_PLAYER_COLUMNS)
            + " FROM amiga_player_current"
        )
        rise_current = {int(row["player_id"]): row for row in cur.fetchall()}

    rise_pids = set(rise_current) | set(honours_oracle) | set(geo_oracle) | set(career_rise_oracle)
    for pid in sorted(rise_pids):
        stored = rise_current.get(pid, {})
        expected = _expected_rise_fields(honours_oracle, geo_oracle, career_rise_oracle, pid)
        for col in RECORD_RISE_PLAYER_COLUMNS:
            stored_val = stored.get(col) if stored else None
            exp_val = expected.get(col)
            if col.endswith("_event_date"):
                if _norm_date(stored_val) != _norm_date(exp_val):
                    errors.append(
                        f"player_id={pid} {col}: stored={stored_val!r} oracle={exp_val!r}"
                    )
            else:
                if _norm_tid(stored_val) != _norm_tid(exp_val):
                    errors.append(
                        f"player_id={pid} {col}: stored={stored_val!r} oracle={exp_val!r}"
                    )

    latest_tid = latest_finalized_tournament_id(conn)
    if latest_tid is None:
        return errors

    cutoff = load_realm_cutoff(conn, latest_tid)
    player_rows = _load_cutoff_player_rows(conn, cutoff)
    holder_patch = _career_holders_from_player_rows(player_rows)
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1 LIMIT 1")
        gst = cur.fetchone() or {}

    for prefix in _HOF_PREFIXES:
        value_key = prefix
        id_key = f"{prefix}ID"
        date_key = f"{prefix}Date"
        if holder_patch.get(value_key) != gst.get(value_key):
            errors.append(
                f"generalstats {value_key}: stored={gst.get(value_key)!r} "
                f"oracle={holder_patch.get(value_key)!r}"
            )
        if int(holder_patch.get(id_key) or 0) != int(gst.get(id_key) or 0):
            errors.append(
                f"generalstats {id_key}: stored={gst.get(id_key)!r} "
                f"oracle={holder_patch.get(id_key)!r}"
            )
        if _norm_date(holder_patch.get(date_key)) != _norm_date(gst.get(date_key)):
            errors.append(
                f"generalstats {date_key}: stored={gst.get(date_key)!r} "
                f"oracle={holder_patch.get(date_key)!r}"
            )

    errors.extend(_alkis_regression_checks(honours_oracle, gst))

    with conn.cursor() as cur:
        cur.execute(
            "SELECT * FROM amiga_realm_snapshots ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC LIMIT 1"
        )
        latest_realm = cur.fetchone()

    if latest_realm:
        for prefix in _HOF_PREFIXES:
            for suffix in ("", "ID", "Name", "Date"):
                col = prefix + suffix
                if latest_realm.get(col) != gst.get(col):
                    errors.append(
                        f"realm vs generalstats {col}: realm={latest_realm.get(col)!r} gst={gst.get(col)!r}"
                    )

    return errors


def main() -> int:
    conn = _connect()
    try:
        errors = verify_hof_geo_year(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-hof-geo-year issue(s):", file=sys.stderr)
        for err in errors[:30]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 30:
            print(f"  ... and {len(errors) - 30} more", file=sys.stderr)
        return 1

    print("OK: verify-hof-geo-year")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
