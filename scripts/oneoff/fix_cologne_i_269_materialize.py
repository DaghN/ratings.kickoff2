"""Post-materialize fix for Cologne I (269): Round 1 group split + Round 2 KO ties."""
from __future__ import annotations

from collections import defaultdict

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import (
    MATERIALIZE_SOURCE,
    _slug_key,
)
from scripts.amiga.tournament_standings import rebuild_standings_for_tournament

TOURNAMENT_ID = 269


def _infer_round1_groups(conn: pymysql.connections.Connection) -> tuple[frozenset[int], frozenset[int]]:
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            """
            SELECT player_a_id AS a, player_b_id AS b
            FROM amiga_games
            WHERE tournament_id = %s AND phase = 'Round 1'
            """,
            (TOURNAMENT_ID,),
        )
        adj: dict[int, set[int]] = defaultdict(set)
        for row in cur.fetchall():
            a, b = int(row["a"]), int(row["b"])
            adj[a].add(b)
            adj[b].add(a)
    seen: set[int] = set()
    groups: list[set[int]] = []
    for node in adj:
        if node in seen:
            continue
        stack = [node]
        comp: set[int] = set()
        while stack:
            n = stack.pop()
            if n in seen:
                continue
            seen.add(n)
            comp.add(n)
            for nb in adj[n]:
                if nb not in seen:
                    stack.append(nb)
        groups.append(comp)
    groups.sort(key=len)
    if [len(g) for g in groups] != [12, 13]:
        raise RuntimeError(f"unexpected R1 group sizes: {[len(g) for g in groups]}")
    return frozenset(groups[0]), frozenset(groups[1])


def _split_round1_groups(
    conn: pymysql.connections.Connection,
    *,
    group_a: frozenset[int],
    group_b: frozenset[int],
) -> int:
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            "SELECT id FROM tournament_stages WHERE tournament_id=%s AND stage_key=%s",
            (TOURNAMENT_ID, "round-1"),
        )
        old = cur.fetchone()
        if old is None:
            raise RuntimeError("round-1 stage missing")
        old_id = int(old["id"])

    stage_a = create_stage(
        conn,
        tournament_id=TOURNAMENT_ID,
        stage_key="round-1-group-a",
        name="Round 1 - Group A",
        stage_type="round_robin",
        sequence_no=1,
        config={"materialized_by": MATERIALIZE_SOURCE, "legacy_import": True, "group_split": True},
    )
    stage_b = create_stage(
        conn,
        tournament_id=TOURNAMENT_ID,
        stage_key="round-1-group-b",
        name="Round 1 - Group B",
        stage_type="round_robin",
        sequence_no=2,
        config={"materialized_by": MATERIALIZE_SOURCE, "legacy_import": True, "group_split": True},
    )

    moved = 0
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            """
            SELECT f.id AS fixture_id, g.player_a_id, g.player_b_id
            FROM tournament_fixtures f
            JOIN amiga_games g ON g.fixture_id = f.id
            WHERE f.stage_id = %s
            """,
            (old_id,),
        )
        for row in cur.fetchall():
            pa, pb = int(row["player_a_id"]), int(row["player_b_id"])
            if pa in group_a and pb in group_a:
                new_stage = stage_a
            elif pa in group_b and pb in group_b:
                new_stage = stage_b
            else:
                raise RuntimeError(f"cross-group R1 fixture {row['fixture_id']}: {pa} vs {pb}")
            cur.execute(
                "UPDATE tournament_fixtures SET stage_id=%s, phase_label=NULL WHERE id=%s",
                (new_stage, int(row["fixture_id"])),
            )
            moved += 1
        cur.execute("DELETE FROM amiga_tournament_standings WHERE stage_id = %s", (old_id,))
        cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_id,))
    return moved


def _split_round2_knockout(conn: pymysql.connections.Connection) -> int:
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            "SELECT id FROM tournament_stages WHERE tournament_id=%s AND stage_key=%s",
            (TOURNAMENT_ID, "round-2"),
        )
        old = cur.fetchone()
        if old is None:
            raise RuntimeError("round-2 stage missing")
        old_id = int(old["id"])
        cur.execute(
            """
            SELECT g.id AS game_id, g.player_a_id, g.player_b_id, g.fixture_id
            FROM amiga_games g
            JOIN tournament_fixtures f ON f.id = g.fixture_id
            WHERE g.tournament_id = %s AND f.stage_id = %s
            ORDER BY g.id
            """,
            (TOURNAMENT_ID, old_id),
        )
        games = cur.fetchall()

    ties: dict[tuple[int, int], list[dict]] = defaultdict(list)
    for game in games:
        pa, pb = int(game["player_a_id"]), int(game["player_b_id"])
        key = (min(pa, pb), max(pa, pb))
        ties[key].append(game)

    tie_count = 0
    for (lo, hi), tie_games in sorted(ties.items()):
        stage_key = f"ko-{_slug_key('Round 2')}-{lo}-{hi}"
        stage_id = create_stage(
            conn,
            tournament_id=TOURNAMENT_ID,
            stage_key=stage_key,
            name="Round 2",
            stage_type="knockout",
            sequence_no=4 + tie_count,
            config={"materialized_by": MATERIALIZE_SOURCE, "legacy_import": True, "round_2_knockout_fix": True},
        )
        for leg_no, game in enumerate(sorted(tie_games, key=lambda g: int(g["game_id"])), start=1):
            with conn.cursor() as cur:
                cur.execute(
                    """
                    UPDATE tournament_fixtures
                    SET stage_id = %s, leg_no = %s, phase_label = NULL
                    WHERE id = %s
                    """,
                    (stage_id, leg_no, int(game["fixture_id"])),
                )
        tie_count += 1

    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_tournament_standings WHERE stage_id = %s", (old_id,))
        cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_id,))
    return tie_count


def _resequence_stages(conn: pymysql.connections.Connection) -> None:
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(
            """
            SELECT id, stage_key, stage_type, name
            FROM tournament_stages
            WHERE tournament_id = %s
            ORDER BY
                CASE
                    WHEN stage_key = 'round-1-group-a' THEN 1
                    WHEN stage_key = 'round-1-group-b' THEN 2
                    WHEN stage_key = 'playouts' THEN 3
                    WHEN stage_key LIKE 'ko-round-2-%%' THEN 4
                    WHEN name = 'Places 9-16' THEN 10
                    WHEN name = 'Places 13-16' THEN 20
                    WHEN name = 'Places 9-12' THEN 30
                    WHEN name LIKE '%%th Place Final' AND name NOT LIKE '24th%%' THEN 40
                    WHEN name = 'Quarter Finals' THEN 50
                    WHEN name = 'Places 5-8' THEN 60
                    WHEN name IN ('7th Place Final', '5th Place Final') THEN 70
                    WHEN name = 'Semi Finals' THEN 80
                    WHEN name = '3rd Place Final' THEN 90
                    WHEN name = 'Final' THEN 100
                    WHEN name = '24th Place Final' THEN 110
                    ELSE 200
                END,
                stage_key
            """,
            (TOURNAMENT_ID,),
        )
        rows = cur.fetchall()
    for seq, row in enumerate(rows, start=1):
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE tournament_stages SET sequence_no = %s WHERE id = %s",
                (seq, int(row["id"])),
            )


def main() -> None:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        group_a, group_b = _infer_round1_groups(conn)
        r1_moved = _split_round1_groups(conn, group_a=group_a, group_b=group_b)
        r2_ties = _split_round2_knockout(conn)
        _resequence_stages(conn)
        rows = rebuild_standings_for_tournament(conn, TOURNAMENT_ID)
        conn.commit()
        print(
            f"fix_cologne_i_269: r1_fixtures={r1_moved} r2_ties={r2_ties} "
            f"group_a={len(group_a)} group_b={len(group_b)} standings_rows={rows}"
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()