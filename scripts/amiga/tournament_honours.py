"""World Cup medal derivation from tournament standings (Amiga-native honours v1)."""

from __future__ import annotations

import logging
import re
from typing import Any, Literal

import pymysql

log = logging.getLogger(__name__)

WcMedal = Literal["none", "gold", "silver", "bronze"]

_WORLD_CUP_NAME_RE = re.compile(r"^World Cup\s+\S", re.IGNORECASE)


def is_world_cup_tournament(name: str) -> bool:
    """Match PHP ``amiga_tournament_is_world_cup()`` — ``^World Cup\\s+\\S``."""
    return bool(_WORLD_CUP_NAME_RE.match(str(name or "").strip()))


def knockout_scope_label(scope_key: str) -> str:
    """Phase label from ``{label}|{player_a}-{player_b}`` scope keys."""
    return str(scope_key or "").split("|", 1)[0].strip()


def compute_wc_medals_from_standings(
    standing_rows: list[dict[str, Any]],
    *,
    overall_positions: dict[int, int] | None = None,
) -> dict[int, WcMedal]:
    """
    Derive WC medals for one tournament from knockout/placement standings.

    v1: gold/silver from main ``Final`` knockout tie; bronze from ``3rd Place Final``
    winner. Fallback to overall positions 1/2/3 only when no knockout/placement rows.
    """
    overall_positions = overall_positions or {}
    ko_or_placement = [
        r
        for r in standing_rows
        if str(r.get("scope_type") or "") in {"knockout", "placement"}
    ]

    gold_id: int | None = None
    silver_id: int | None = None
    bronze_id: int | None = None

    for row in ko_or_placement:
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        player_id = int(row["player_id"])
        position = int(row["position"])
        label_lower = label.lower()

        if str(row.get("scope_type") or "") == "knockout":
            if label_lower == "final":
                if position == 1:
                    gold_id = player_id
                elif position == 2:
                    silver_id = player_id
            elif label_lower == "3rd place final" and position == 1:
                bronze_id = player_id

    if gold_id is not None or silver_id is not None or bronze_id is not None:
        medals: dict[int, WcMedal] = {}
        if gold_id is not None:
            medals[gold_id] = "gold"
        if silver_id is not None:
            medals[silver_id] = "silver"
        if bronze_id is not None:
            medals[bronze_id] = "bronze"
        return medals

    if ko_or_placement:
        return {}

    medals = {}
    for player_id, position in overall_positions.items():
        if position == 1:
            medals[int(player_id)] = "gold"
        elif position == 2:
            medals[int(player_id)] = "silver"
        elif position == 3:
            medals[int(player_id)] = "bronze"
    return medals


def _load_tournament_name(conn: pymysql.connections.Connection, tournament_id: int) -> str:
    with conn.cursor() as cur:
        cur.execute("SELECT name FROM tournaments WHERE id = %s", (tournament_id,))
        row = cur.fetchone()
    if not row:
        return ""
    return str(row["name"])


def derive_tournament_wc_medals(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    tournament_name: str | None = None,
) -> dict[int, WcMedal]:
    name = tournament_name if tournament_name is not None else _load_tournament_name(conn, tournament_id)
    if not is_world_cup_tournament(name):
        return {}

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT scope_type, scope_key, player_id, position
            FROM amiga_tournament_standings
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        standing_rows = cur.fetchall()

    overall_positions: dict[int, int] = {}
    for row in standing_rows:
        if str(row["scope_type"]) == "overall" and str(row.get("scope_key") or "") == "":
            overall_positions[int(row["player_id"])] = int(row["position"])

    return compute_wc_medals_from_standings(
        standing_rows,
        overall_positions=overall_positions,
    )


def derive_wc_medal(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    player_id: int,
) -> WcMedal:
    medals = derive_tournament_wc_medals(conn, tournament_id)
    return medals.get(int(player_id), "none")


def refresh_wc_medals(
    conn: pymysql.connections.Connection,
    tournament_id: int | None = None,
    *,
    dry_run: bool = False,
) -> int:
    """Update ``wc_medal`` on participation rows for World Cup events."""
    with conn.cursor() as cur:
        if tournament_id is not None:
            cur.execute("SELECT id, name FROM tournaments WHERE id = %s", (tournament_id,))
            wc_rows = list(cur.fetchall())
        else:
            cur.execute("SELECT id, name FROM tournaments WHERE name REGEXP %s", (r"^World Cup[[:space:]]+[^[:space:]]",))
            wc_rows = list(cur.fetchall())

    if dry_run:
        return len(wc_rows)

    updated = 0
    with conn.cursor() as cur:
        for row in wc_rows:
            tid = int(row["id"])
            medals = derive_tournament_wc_medals(conn, tid, tournament_name=str(row["name"]))
            cur.execute(
                """
                UPDATE amiga_player_tournament_participation
                SET wc_medal = 'none'
                WHERE tournament_id = %s
                """,
                (tid,),
            )
            for player_id, medal in medals.items():
                cur.execute(
                    """
                    UPDATE amiga_player_tournament_participation
                    SET wc_medal = %s
                    WHERE tournament_id = %s AND player_id = %s
                    """,
                    (medal, tid, player_id),
                )
                updated += int(cur.rowcount)
            cur.execute(
                """
                UPDATE amiga_player_tournament_participation
                SET is_winner = CASE WHEN wc_medal = 'gold' THEN 1 ELSE 0 END
                WHERE tournament_id = %s
                """,
                (tid,),
            )
    conn.commit()
    log.info(
        "refresh_wc_medals: %s World Cup tournament(s), %s medal row(s) set",
        len(wc_rows),
        updated,
    )
    return updated
