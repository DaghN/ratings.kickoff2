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


def _normalize_knockout_label(label: str) -> str:
    text = re.sub(r"\s+", " ", str(label or "").strip().lower())
    if re.match(r"^(?:quarter|semi)\s+final$", text):
        return text + "s" if not text.endswith("s") else text
    return text


def _is_main_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) == "final"


def _is_third_place_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) == "3rd place final"


def _is_semi_final_label(label: str) -> bool:
    return _normalize_knockout_label(label) in {"semi final", "semi finals"}


def _has_third_place_final_scope(standing_rows: list[dict[str, Any]]) -> bool:
    for row in standing_rows:
        if str(row.get("scope_type") or "") != "knockout":
            continue
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        if _is_third_place_final_label(label):
            return True
    return False


def compute_wc_medals_from_standings(
    standing_rows: list[dict[str, Any]],
) -> dict[int, WcMedal]:
    """
    Derive WC medals for one tournament from knockout standings.

    Gold/silver from main ``Final``; bronze from ``3rd Place Final`` winner **or**
    both semi-final losers when no 3rd-place match and Final is complete (Olympic-style).
    Never awards medals from group/overall league rank alone.
    """
    ko_rows = [
        r
        for r in standing_rows
        if str(r.get("scope_type") or "") == "knockout"
    ]

    gold_id: int | None = None
    silver_id: int | None = None
    bronze_ids: set[int] = set()

    for row in ko_rows:
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        player_id = int(row["player_id"])
        position = int(row["position"])

        if _is_main_final_label(label):
            if position == 1:
                gold_id = player_id
            elif position == 2:
                silver_id = player_id
        elif _is_third_place_final_label(label) and position == 1:
            bronze_ids.add(player_id)

    if (
        not _has_third_place_final_scope(standing_rows)
        and gold_id is not None
        and silver_id is not None
    ):
        for row in ko_rows:
            label = knockout_scope_label(str(row.get("scope_key") or ""))
            if not _is_semi_final_label(label):
                continue
            if int(row["position"]) == 2:
                bronze_ids.add(int(row["player_id"]))

    medals: dict[int, WcMedal] = {}
    if gold_id is not None:
        medals[gold_id] = "gold"
    if silver_id is not None:
        medals[silver_id] = "silver"
    for player_id in bronze_ids:
        medals[player_id] = "bronze"
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

    return compute_wc_medals_from_standings(standing_rows)


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

    wc_rows = [row for row in wc_rows if is_world_cup_tournament(str(row["name"]))]

    if dry_run:
        return len(wc_rows)

    updated = 0
    with conn.cursor() as cur:
        for row in wc_rows:
            tid = int(row["id"])
            name = str(row["name"])
            medals = derive_tournament_wc_medals(conn, tid, tournament_name=name)
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
