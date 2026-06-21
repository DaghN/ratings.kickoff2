#!/usr/bin/env python3
"""Verify geo/year player scalars and HoF holders (policy: amiga-hof-tournament-geo-policy.md)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import GAME_SELECT_FOR_TOURNAMENT
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.player_geo_year import PlayerGeoYearTracker, load_player_countries
from scripts.amiga.realm_incremental import _career_holders_from_player_rows
from scripts.amiga.realm_incremental import _career_holders_from_player_rows
from scripts.amiga.server_records import _CAREER_HOLDERS, _load_cutoff_player_rows
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id, load_realm_cutoff

_GEO_YEAR_COLUMNS = (
    "peak_year_games",
    "peak_year_games_year",
    "peak_year_tournaments",
    "peak_year_tournaments_year",
    "countries_played_in",
    "opponent_countries_faced",
    "opponent_countries_beaten",
)

_HOF_PREFIXES = tuple(prefix for _v, _c, prefix in _CAREER_HOLDERS if prefix in {
    "MostGamesInOneYear",
    "MostTournamentsInOneYear",
    "MostTournamentsPlayed",
    "MostTournamentWins",
    "MostWcPlayed",
    "MostCountriesPlayedIn",
    "MostOpponentCountriesFaced",
    "MostOpponentCountriesBeaten",
})


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


def _oracle_geo_by_player(conn: pymysql.connections.Connection) -> dict[int, dict[str, int | None]]:
    player_countries = load_player_countries(conn)
    tracker = PlayerGeoYearTracker()

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id FROM tournaments
            WHERE rating_finalized = 1
            ORDER BY event_date ASC, chrono ASC, id ASC
            """
        )
        tournament_ids = [int(row["id"]) for row in cur.fetchall()]

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


def verify_hof_geo_year(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    oracle = _oracle_geo_by_player(conn)
    with conn.cursor() as cur:
        cur.execute(
            "SELECT player_id, "
            + ", ".join(_GEO_YEAR_COLUMNS)
            + " FROM amiga_player_current"
        )
        current_rows = cur.fetchall()

    for row in current_rows:
        pid = int(row["player_id"])
        expected = oracle.get(pid, {col: 0 if not col.endswith("_year") else None for col in _GEO_YEAR_COLUMNS})
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
