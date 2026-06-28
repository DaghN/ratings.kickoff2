"""HoF highest peak rating — read-time oracle from per-player PeakRating (Amiga)."""

from __future__ import annotations

from typing import Any

import pymysql

from scripts.amiga.realm_cutoff import RealmCutoff, cutoff_params
from scripts.amiga.server_records import _fmt_date, _latest_player_snapshots_sql


def peak_rating_hof_holder_oracle(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any] | None:
    """Realm HoF peak row: max career PeakRating + peak_rating_tournament_id date."""
    if cutoff is None:
        sql = """
            SELECT s.player_id, p.name AS player_name, s.PeakRating AS peak_value,
                   DATE_FORMAT(tpr.event_date, '%%Y-%%m-%%d') AS peak_date
            FROM amiga_player_current s
            INNER JOIN amiga_players p ON p.id = s.player_id
            LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id
            WHERE s.NumberGames > 0
              AND s.PeakRating IS NOT NULL
              AND s.PeakRating > 0
            ORDER BY s.PeakRating DESC, s.Rating DESC, s.player_id ASC
            LIMIT 1
        """
        params: tuple[Any, ...] = ()
    else:
        params = cutoff_params(cutoff)
        sql = f"""
            SELECT lp.player_id, lp.player_name, lp.PeakRating AS peak_value,
                   DATE_FORMAT(tpr.event_date, '%%Y-%%m-%%d') AS peak_date
            FROM ({_latest_player_snapshots_sql()}) lp
            LEFT JOIN tournaments tpr ON tpr.id = lp.peak_rating_tournament_id
            WHERE lp.NumberGames > 0
              AND lp.PeakRating IS NOT NULL
              AND lp.PeakRating > 0
            ORDER BY lp.PeakRating DESC, lp.Rating DESC, lp.player_id ASC
            LIMIT 1
        """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return None
    return {
        "value": float(row["peak_value"]),
        "player_id": int(row["player_id"]),
        "name": str(row.get("player_name") or ""),
        "date": _fmt_date(row.get("peak_date")),
    }
