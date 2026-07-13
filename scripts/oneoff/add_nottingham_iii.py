"""Add Nottingham III (2026-05-31) to ko2amiga_work — 8p double RR, H2H tie-break."""
from __future__ import annotations

import sys
from collections import defaultdict
from datetime import date, datetime, time, timedelta

from scripts.amiga.finalize_tournament import finalize_tournament
from scripts.amiga.modern.work_db import connect_work
from scripts.amiga.tournament_builder import create_kitchen_marathon_tournament
from scripts.amiga.tournament_fixtures import (
    _next_live_source_scores_id,
    list_fixtures,
    next_tournament_chrono,
    record_fixture_result,
    set_tournament_lifecycle_status,
)

NAME = "Nottingham III"
EVENT_DATE = date(2026, 5, 31)
COUNTRY = "England"

PLAYER_IDS_BY_NAME = {
    "Mick C": 322,
    "Ilker C": 178,
    "Robert S": 382,
    "Mark W": 282,
    "Ian K": 175,
    "Steve C": 417,
    "Steve B": 415,
    "James L": 185,
}

H2H_LEAGUE_STEPS = (
    "points",
    "head_to_head",
    "goal_difference",
    "goals_for",
    "games_played",
)

PLAY_ORDER = [
    ("Mick C", "Ilker C", 5, 7),
    ("Robert S", "Mark W", 5, 0),
    ("Ian K", "Steve C", 2, 7),
    ("Steve B", "James L", 7, 2),
    ("Ilker C", "Ian K", 6, 1),
    ("Steve B", "Robert S", 4, 2),
    ("James L", "Mick C", 5, 3),
    ("Steve C", "Mark W", 6, 1),
    ("James L", "Ilker C", 1, 5),
    ("Steve C", "Steve B", 4, 5),
    ("Mark W", "Ian K", 3, 1),
    ("Mick C", "Robert S", 2, 4),
    ("Ilker C", "Mark W", 6, 5),
    ("Mick C", "Steve C", 3, 8),
    ("Robert S", "James L", 4, 1),
    ("Ian K", "Steve B", 1, 7),
    ("Robert S", "Ilker C", 2, 3),
    ("Ian K", "Mick C", 3, 3),
    ("Steve B", "Mark W", 2, 4),
    ("James L", "Steve C", 3, 7),
    ("Ilker C", "Steve B", 5, 5),
    ("James L", "Ian K", 1, 4),
    ("Steve C", "Robert S", 4, 4),
    ("Mark W", "Mick C", 5, 4),
    ("Steve C", "Ilker C", 3, 5),
    ("Mark W", "James L", 1, 0),
    ("Mick C", "Steve B", 6, 10),
    ("Robert S", "Ian K", 4, 2),
    ("Ilker C", "Mick C", 5, 2),
    ("Mark W", "Robert S", 3, 2),
    ("Steve C", "Ian K", 7, 2),
    ("James L", "Steve B", 2, 7),
    ("Ian K", "Ilker C", 4, 7),
    ("Robert S", "Steve B", 3, 5),
    ("Mick C", "James L", 1, 3),
    ("Mark W", "Steve C", 2, 5),
    ("Ilker C", "James L", 3, 4),
    ("Steve B", "Steve C", 3, 1),
    ("Ian K", "Mark W", 2, 3),
    ("Robert S", "Mick C", 4, 3),
    ("Mark W", "Ilker C", 4, 6),
    ("Steve C", "Mick C", 4, 2),
    ("James L", "Robert S", 3, 4),
    ("Steve B", "Ian K", 7, 3),
    ("Ilker C", "Robert S", 10, 1),
    ("Mick C", "Ian K", 2, 5),
    ("Mark W", "Steve B", 1, 11),
    ("Steve C", "James L", 8, 2),
    ("Steve B", "Ilker C", 7, 4),
    ("Ian K", "James L", 4, 1),
    ("Robert S", "Steve C", 5, 7),
    ("Mick C", "Mark W", 1, 5),
    ("Ilker C", "Steve C", 3, 4),
    ("James L", "Mark W", 3, 6),
    ("Steve B", "Mick C", 9, 2),
    ("Ian K", "Robert S", 2, 3),
]


def _set_stage_scoring_steps(conn, stage_id: int, steps: tuple[str, ...]) -> None:
    with conn.cursor() as cur:
        cur.execute("DELETE FROM tournament_stage_scoring_steps WHERE stage_id = %s", (stage_id,))
        for sequence_no, step in enumerate(steps, start=1):
            cur.execute(
                """
                INSERT INTO tournament_stage_scoring_steps (stage_id, sequence_no, step)
                VALUES (%s, %s, %s)
                """,
                (stage_id, sequence_no, step),
            )


def _load_fixture_rows(conn, tournament_id: int) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id, f.player_a_id, f.player_b_id, f.leg_no, f.status
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            ORDER BY f.leg_no ASC, f.id ASC
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def _pair_key(a: int, b: int) -> tuple[int, int]:
    return (a, b) if a < b else (b, a)


def _promote_in_play_order(
    conn,
    tournament_id: int,
    fixture_ids_in_order: list[int],
    event_date: date,
) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        if int(cur.fetchone()["n"]) > 0:
            raise RuntimeError("amiga_games already exist for tournament")

    placeholders = ",".join(["%s"] * len(fixture_ids_in_order))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT f.id AS fixture_id, f.player_a_id, f.player_b_id, f.goals_a, f.goals_b,
                   f.extra, f.goals_et_a, f.goals_et_b, f.pens_a, f.pens_b, f.phase_label
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.id IN ({placeholders})
            """,
            (tournament_id, *fixture_ids_in_order),
        )
        by_id = {int(row["fixture_id"]): row for row in cur.fetchall()}

    missing = [fid for fid in fixture_ids_in_order if fid not in by_id]
    if missing:
        raise RuntimeError(f"fixtures missing for promote: {missing}")

    base = datetime.combine(event_date, time(2, 0, 0))
    game_ids: list[int] = []
    with conn.cursor() as cur:
        for idx, fixture_id in enumerate(fixture_ids_in_order):
            row = by_id[fixture_id]
            if row["goals_a"] is None or row["goals_b"] is None:
                raise RuntimeError(f"fixture_id={fixture_id} missing goals")
            source_scores_id = _next_live_source_scores_id(conn)
            game_date = base + timedelta(seconds=idx)
            extra = row["extra"]
            extra_value = extra.strip() if extra and str(extra).strip() else None
            cur.execute(
                """
                INSERT INTO amiga_games
                  (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id,
                   phase, goals_a, goals_b, extra, goals_et_a, goals_et_b, pens_a, pens_b)
                VALUES
                  (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    source_scores_id,
                    game_date.strftime("%Y-%m-%d %H:%M:%S"),
                    int(row["player_a_id"]),
                    int(row["player_b_id"]),
                    tournament_id,
                    fixture_id,
                    row["phase_label"],
                    int(row["goals_a"]),
                    int(row["goals_b"]),
                    extra_value,
                    row["goals_et_a"],
                    row["goals_et_b"],
                    row["pens_a"],
                    row["pens_b"],
                ),
            )
            game_ids.append(int(cur.lastrowid))
    return game_ids


def main() -> int:
    conn = connect_work()
    try:
        player_ids = [
            PLAYER_IDS_BY_NAME["Mick C"],
            PLAYER_IDS_BY_NAME["Ilker C"],
            PLAYER_IDS_BY_NAME["Robert S"],
            PLAYER_IDS_BY_NAME["Mark W"],
            PLAYER_IDS_BY_NAME["Ian K"],
            PLAYER_IDS_BY_NAME["Steve C"],
            PLAYER_IDS_BY_NAME["Steve B"],
            PLAYER_IDS_BY_NAME["James L"],
        ]

        created = create_kitchen_marathon_tournament(
            conn,
            name=NAME,
            event_date=EVENT_DATE,
            country=COUNTRY,
            player_ids=player_ids,
            legs=2,
        )
        tournament_id = int(created["tournament_id"])
        stage_id = int(created["stage_id"])
        _set_stage_scoring_steps(conn, stage_id, H2H_LEAGUE_STEPS)
        set_tournament_lifecycle_status(conn, tournament_id=tournament_id, status="running")
        conn.commit()

        pair_fixtures: dict[tuple[int, int], list[dict]] = defaultdict(list)
        for row in _load_fixture_rows(conn, tournament_id):
            pair_fixtures[_pair_key(int(row["player_a_id"]), int(row["player_b_id"]))].append(row)
        for fixtures in pair_fixtures.values():
            fixtures.sort(key=lambda r: int(r["leg_no"]))

        pair_meetings: dict[tuple[int, int], int] = defaultdict(int)
        fixture_ids_in_order: list[int] = []

        for home_name, away_name, home_goals, away_goals in PLAY_ORDER:
            home_id = PLAYER_IDS_BY_NAME[home_name]
            away_id = PLAYER_IDS_BY_NAME[away_name]
            key = _pair_key(home_id, away_id)
            meeting = pair_meetings[key]
            fixtures = pair_fixtures[key]
            if meeting >= len(fixtures):
                raise RuntimeError(f"too many meetings for pair {home_name} vs {away_name}")
            fixture = fixtures[meeting]
            pair_meetings[key] += 1

            fa = int(fixture["player_a_id"])
            fb = int(fixture["player_b_id"])
            if home_id == fa and away_id == fb:
                goals_a, goals_b = home_goals, away_goals
            elif home_id == fb and away_id == fa:
                goals_a, goals_b = away_goals, home_goals
            else:
                raise RuntimeError(f"fixture player mismatch for {home_name} vs {away_name}")

            record_fixture_result(
                conn,
                fixture_id=int(fixture["id"]),
                goals_a=goals_a,
                goals_b=goals_b,
            )
            fixture_ids_in_order.append(int(fixture["id"]))

        if len(fixture_ids_in_order) != 56:
            raise RuntimeError(f"expected 56 fixtures recorded, got {len(fixture_ids_in_order)}")

        scheduled = list_fixtures(conn, tournament_id=tournament_id, status="scheduled")
        if scheduled:
            raise RuntimeError(f"{len(scheduled)} fixture(s) still scheduled")

        with conn.cursor() as cur:
            cur.execute(
                "SELECT chrono FROM tournaments WHERE id = %s",
                (tournament_id,),
            )
            if cur.fetchone()["chrono"] is None:
                next_chrono = next_tournament_chrono(
                    conn,
                    EVENT_DATE,
                    exclude_tournament_id=tournament_id,
                )
                cur.execute(
                    "UPDATE tournaments SET chrono = %s WHERE id = %s",
                    (next_chrono, tournament_id),
                )

        game_ids = _promote_in_play_order(
            conn,
            tournament_id,
            fixture_ids_in_order,
            EVENT_DATE,
        )
        conn.commit()

        result = finalize_tournament(conn, tournament_id)
        set_tournament_lifecycle_status(conn, tournament_id=tournament_id, status="completed")
        conn.commit()

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT s.position, p.name, s.points,
                       s.goals_for - s.goals_against AS gd, s.goals_for
                FROM amiga_tournament_standings s
                INNER JOIN amiga_players p ON p.id = s.player_id
                WHERE s.tournament_id = %s AND s.scope_type = 'league'
                ORDER BY s.position ASC, s.player_id ASC
                """,
                (tournament_id,),
            )
            standings = list(cur.fetchall())

        print(f"tournament_id={tournament_id} games={len(game_ids)} finalized={result.get('games')}")
        print("standings:")
        for row in standings:
            print(
                f"  {int(row['position']):>2} {row['name']:<10} "
                f"pts={int(row['points'])} gd={int(row['gd'])} gf={int(row['goals_for'])}"
            )
        return 0
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())