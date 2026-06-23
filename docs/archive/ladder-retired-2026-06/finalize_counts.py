"""Recompute opponent/victim/culprit counts from replayed ratedresults."""

from __future__ import annotations

from collections import defaultdict

from .player_state import PlayerState


def _ingest(
    by_player: dict[int, dict],
    player_id: int,
    opponent_id: int,
    *,
    key: str,
) -> None:
    by_player[player_id][key].add(opponent_id)


def finalize_network_counts_from_rows(
    players: dict[int, PlayerState],
    game_rows: list[dict],
) -> None:
    """Derive network counts from replayed game rows (no per-player SQL)."""
    buckets: dict[int, dict[str, set[int]]] = defaultdict(
        lambda: {
            "opponents": set(),
            "victims": set(),
            "culprits": set(),
            "dd_victims": set(),
            "dd_culprits": set(),
            "cs_victims": set(),
            "cs_culprits": set(),
        }
    )

    for g in game_rows:
        id_a = int(g["idA"])
        id_b = int(g["idB"])
        score = float(g["ActualScore"])
        dd_a = int(g.get("DDPlayerA") or 0)
        dd_b = int(g.get("DDPlayerB") or 0)
        cs_a = int(g.get("CSPlayerA") or 0)
        cs_b = int(g.get("CSPlayerB") or 0)

        _ingest(buckets, id_a, id_b, key="opponents")
        _ingest(buckets, id_b, id_a, key="opponents")

        if score == 1.0:
            _ingest(buckets, id_a, id_b, key="victims")
            _ingest(buckets, id_b, id_a, key="culprits")
        elif score == 0.0:
            _ingest(buckets, id_b, id_a, key="victims")
            _ingest(buckets, id_a, id_b, key="culprits")

        if dd_a:
            _ingest(buckets, id_a, id_b, key="dd_victims")
            _ingest(buckets, id_b, id_a, key="dd_culprits")
        if dd_b:
            _ingest(buckets, id_b, id_a, key="dd_victims")
            _ingest(buckets, id_a, id_b, key="dd_culprits")
        if cs_a:
            _ingest(buckets, id_a, id_b, key="cs_victims")
            _ingest(buckets, id_b, id_a, key="cs_culprits")
        if cs_b:
            _ingest(buckets, id_b, id_a, key="cs_victims")
            _ingest(buckets, id_a, id_b, key="cs_culprits")

    for pid, st in players.items():
        if st.games <= 0:
            continue
        b = buckets.get(pid)
        if not b:
            continue
        st.different_opponents = len(b["opponents"])
        st.different_victims = len(b["victims"])
        st.different_culprits = len(b["culprits"])
        st.double_digits_victims = len(b["dd_victims"])
        st.double_digits_culprits = len(b["dd_culprits"])
        st.clean_sheets_victims = len(b["cs_victims"])
        st.clean_sheets_culprits = len(b["cs_culprits"])


def finalize_network_counts(conn, players: dict[int, PlayerState]) -> None:
    """Load ratedresults and fill network counts (used after DB replay)."""
    import pymysql

    with conn.cursor() as cur:
        cur.execute(
            "SELECT idA, idB, ActualScore, DDPlayerA, DDPlayerB, CSPlayerA, CSPlayerB "
            "FROM ratedresults"
        )
        rows = cur.fetchall()
    finalize_network_counts_from_rows(players, rows)
