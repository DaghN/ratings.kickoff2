"""Internal builder for new fixture-backed Amiga tournaments."""

from __future__ import annotations

import argparse
import json
import sys
from dataclasses import dataclass
from datetime import date
from typing import Any

import pymysql

from scripts.amiga.tournament_fixtures import (
    _connect,
    _load_one,
    _require_player,
    add_stage_player,
    add_tournament_entrant,
    create_fixture,
    create_stage,
    record_fixture_result,
)
from scripts.amiga.tournament_format import seed_format_templates

BUILDER_NAME = "scripts.amiga.tournament_builder"


@dataclass(frozen=True, slots=True)
class FixturePlan:
    round_no: int
    match_no: int
    leg_no: int
    player_a_id: int
    player_b_id: int


def _parse_player_ids(raw: str) -> list[int]:
    ids = [int(part.strip()) for part in raw.split(",") if part.strip()]
    if len(ids) < 2:
        raise ValueError("at least two player ids are required")
    if len(set(ids)) != len(ids):
        raise ValueError("player ids must be unique")
    return ids


def _parse_date(raw: str) -> date:
    return date.fromisoformat(raw)


def round_robin_fixture_plan(player_ids: list[int], *, legs: int = 1) -> list[FixturePlan]:
    """Generate a deterministic circle-method round-robin plan."""
    if legs not in (1, 2):
        raise ValueError("legs must be 1 or 2")
    players: list[int | None] = list(player_ids)
    if len(players) % 2 == 1:
        players.append(None)

    n = len(players)
    rounds = n - 1
    half = n // 2
    first_leg: list[FixturePlan] = []
    rotation = players[:]

    for round_no in range(1, rounds + 1):
        match_no = 1
        for i in range(half):
            a = rotation[i]
            b = rotation[n - 1 - i]
            if a is None or b is None:
                continue
            if round_no % 2 == 0:
                a, b = b, a
            first_leg.append(
                FixturePlan(
                    round_no=round_no,
                    match_no=match_no,
                    leg_no=1,
                    player_a_id=a,
                    player_b_id=b,
                )
            )
            match_no += 1
        rotation = [rotation[0], rotation[-1], *rotation[1:-1]]

    if legs == 1:
        return first_leg

    second_leg = [
        FixturePlan(
            round_no=fixture.round_no + rounds,
            match_no=fixture.match_no,
            leg_no=2,
            player_a_id=fixture.player_b_id,
            player_b_id=fixture.player_a_id,
        )
        for fixture in first_leg
    ]
    return first_leg + second_leg


def expected_round_robin_fixtures(player_count: int, *, legs: int) -> int:
    return (player_count * (player_count - 1) // 2) * legs


def _split_groups(player_ids: list[int], group_count: int) -> list[list[int]]:
    if group_count < 2:
        raise ValueError("group_count must be at least 2")
    if len(player_ids) < group_count * 2:
        raise ValueError("each group needs at least two players")
    groups = [[] for _ in range(group_count)]
    for idx, player_id in enumerate(player_ids):
        groups[idx % group_count].append(player_id)
    return groups


def _template_id(conn: pymysql.connections.Connection, slug: str) -> int:
    ids = seed_format_templates(conn)
    if slug not in ids:
        raise ValueError(f"format template {slug!r} is not seeded")
    return ids[slug]


def create_kitchen_marathon_tournament(
    conn: pymysql.connections.Connection,
    *,
    name: str,
    event_date: date,
    country: str | None,
    player_ids: list[int],
    legs: int = 1,
    equal_teams: bool = False,
) -> dict[str, int]:
    for player_id in player_ids:
        _require_player(conn, player_id)

    template_id = _template_id(conn, "kitchen_marathon")
    fixtures = round_robin_fixture_plan(player_ids, legs=legs)
    overrides = {
        "generated_by": BUILDER_NAME,
        "round_robin_legs": legs,
        "fixture_count": len(fixtures),
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 0, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 1, 0)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
            },
        )
        tournament_id = int(cur.lastrowid)

    for seed_no, player_id in enumerate(player_ids, start=1):
        add_tournament_entrant(
            conn,
            tournament_id=tournament_id,
            player_id=player_id,
            seed_no=seed_no,
        )

    stage_id = create_stage(
        conn,
        tournament_id=tournament_id,
        stage_key="overall",
        name="Overall",
        stage_type="league",
        sequence_no=1,
        config={
            "generated_by": BUILDER_NAME,
            "player_count": len(player_ids),
            "round_robin_legs": legs,
        },
    )
    for seed_no, player_id in enumerate(player_ids, start=1):
        add_stage_player(
            conn,
            tournament_id=tournament_id,
            stage_key="overall",
            player_id=player_id,
            seed_no=seed_no,
        )
    for fixture in fixtures:
        create_fixture(
            conn,
            tournament_id=tournament_id,
            stage_key="overall",
            fixture_key=f"overall-r{fixture.round_no:02d}-m{fixture.match_no:02d}",
            player_a_id=fixture.player_a_id,
            player_b_id=fixture.player_b_id,
            leg_no=fixture.leg_no,
            phase_label="Overall",
        )

    return {
        "tournament_id": tournament_id,
        "stage_id": stage_id,
        "player_count": len(player_ids),
        "fixture_count": len(fixtures),
    }


def create_group_knockout_tournament(
    conn: pymysql.connections.Connection,
    *,
    name: str,
    event_date: date,
    country: str | None,
    player_ids: list[int],
    group_count: int = 2,
    group_legs: int = 1,
    equal_teams: bool = False,
) -> dict[str, int]:
    for player_id in player_ids:
        _require_player(conn, player_id)

    groups = _split_groups(player_ids, group_count)
    template_id = _template_id(conn, "group_knockout")
    group_fixture_count = sum(len(round_robin_fixture_plan(group, legs=group_legs)) for group in groups)
    overrides = {
        "generated_by": BUILDER_NAME,
        "group_count": group_count,
        "group_legs": group_legs,
        "group_fixture_count": group_fixture_count,
        "knockout_fixture_count": 1,
        "knockout_policy": "top group players must be assigned manually after groups",
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 1, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 1, 1)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
            },
        )
        tournament_id = int(cur.lastrowid)

    for seed_no, player_id in enumerate(player_ids, start=1):
        add_tournament_entrant(
            conn,
            tournament_id=tournament_id,
            player_id=player_id,
            seed_no=seed_no,
        )

    fixture_count = 0
    for group_idx, group in enumerate(groups, start=1):
        group_key = chr(ord("A") + group_idx - 1)
        stage_key = f"group-{group_key.lower()}"
        create_stage(
            conn,
            tournament_id=tournament_id,
            stage_key=stage_key,
            name=f"Group {group_key}",
            stage_type="group",
            sequence_no=group_idx,
            track_key=group_key,
            config={
                "generated_by": BUILDER_NAME,
                "group_key": group_key,
                "round_robin_legs": group_legs,
            },
        )
        for seed_no, player_id in enumerate(group, start=1):
            add_stage_player(
                conn,
                tournament_id=tournament_id,
                stage_key=stage_key,
                player_id=player_id,
                seed_no=seed_no,
                group_key=group_key,
            )
        for fixture in round_robin_fixture_plan(group, legs=group_legs):
            create_fixture(
                conn,
                tournament_id=tournament_id,
                stage_key=stage_key,
                fixture_key=f"{stage_key}-r{fixture.round_no:02d}-m{fixture.match_no:02d}",
                player_a_id=fixture.player_a_id,
                player_b_id=fixture.player_b_id,
                leg_no=fixture.leg_no,
                phase_label=f"Group {group_key}",
            )
            fixture_count += 1

    knockout_stage_id = create_stage(
        conn,
        tournament_id=tournament_id,
        stage_key="final",
        name="Final",
        stage_type="knockout",
        sequence_no=100,
        config={
            "generated_by": BUILDER_NAME,
            "requires_manual_player_assignment": True,
        },
    )
    create_fixture(
        conn,
        tournament_id=tournament_id,
        stage_key="final",
        fixture_key="final",
        phase_label="Final",
    )
    fixture_count += 1

    return {
        "tournament_id": tournament_id,
        "stage_id": knockout_stage_id,
        "player_count": len(player_ids),
        "fixture_count": fixture_count,
    }


def verify_built_tournament(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    allow_attached_games: bool = False,
) -> list[str]:
    errors: list[str] = []
    row = _load_one(
        conn,
        """
        SELECT t.id, t.name, t.player_count, t.has_league, t.has_cup,
               t.format_overrides, ft.slug AS template_slug
        FROM tournaments t
        LEFT JOIN tournament_format_templates ft ON ft.id = t.format_template_id
        WHERE t.id = %s
        """,
        (tournament_id,),
    )
    if row is None:
        return [f"tournament_id={tournament_id} not found"]
    template_slug = row["template_slug"]
    if template_slug not in {"kitchen_marathon", "group_knockout"}:
        errors.append(f"template is {template_slug!r}, expected generated template")
    if template_slug == "kitchen_marathon" and (int(row["has_league"]) != 1 or int(row["has_cup"]) != 0):
        errors.append("expected kitchen_marathon has_league=1 and has_cup=0")
    if template_slug == "group_knockout" and (int(row["has_league"]) != 1 or int(row["has_cup"]) != 1):
        errors.append("expected group_knockout has_league=1 and has_cup=1")

    overrides: dict[str, Any] = {}
    if row.get("format_overrides"):
        overrides = json.loads(str(row["format_overrides"]))
    if template_slug == "group_knockout":
        expected_fixtures = int(overrides.get("group_fixture_count", 0)) + int(
            overrides.get("knockout_fixture_count", 0)
        )
    else:
        legs = int(overrides.get("round_robin_legs", 1))
        expected_fixtures = expected_round_robin_fixtures(int(row["player_count"] or 0), legs=legs)

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s", (tournament_id,))
        if int(cur.fetchone()["n"]) != 1:
            errors.append("expected exactly one stage")

        cur.execute(
            "SELECT COUNT(*) AS n FROM tournament_entrants WHERE tournament_id = %s AND status = 'registered'",
            (tournament_id,),
        )
        entrants = int(cur.fetchone()["n"])
        if entrants != int(row["player_count"] or 0):
            errors.append(f"entrant count {entrants}, expected {row['player_count']}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        stage_players = int(cur.fetchone()["n"])
        if stage_players != int(row["player_count"] or 0):
            errors.append(f"stage player count {stage_players}, expected {row['player_count']}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        fixtures = int(cur.fetchone()["n"])
        if fixtures != expected_fixtures:
            errors.append(f"fixture count {fixtures}, expected {expected_fixtures}")

        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        attached_games = int(cur.fetchone()["n"])
        if attached_games and not allow_attached_games:
            errors.append(f"expected no attached games yet, found {attached_games}")

    return errors


def smoke_fixture_result_flow(conn: pymysql.connections.Connection, *, player_ids: list[int]) -> dict[str, int]:
    result = create_kitchen_marathon_tournament(
        conn,
        name="Fixture Smoke Rollback",
        event_date=date.today(),
        country="Test",
        player_ids=player_ids,
        legs=1,
    )
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            ORDER BY f.id
            LIMIT 1
            """,
            (result["tournament_id"],),
        )
        fixture_id = int(cur.fetchone()["id"])
    game_id = record_fixture_result(conn, fixture_id=fixture_id, goals_a=1, goals_b=0)
    errors = verify_built_tournament(conn, tournament_id=result["tournament_id"], allow_attached_games=True)
    if errors:
        raise ValueError("; ".join(errors))
    return {
        **result,
        "fixture_id": fixture_id,
        "game_id": game_id,
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Internal builder for new fixture-backed tournaments")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_create = sub.add_parser("create-kitchen-marathon", help="Create a kitchen marathon with round-robin fixtures")
    p_create.add_argument("--name", required=True)
    p_create.add_argument("--event-date", required=True, help="YYYY-MM-DD")
    p_create.add_argument("--country", default=None)
    p_create.add_argument("--player-ids", required=True, help="Comma-separated Amiga player ids")
    p_create.add_argument("--legs", type=int, choices=(1, 2), default=1)
    p_create.add_argument("--equal-teams", action="store_true")
    p_create.add_argument("--dry-run", action="store_true")

    p_group = sub.add_parser("create-group-knockout", help="Create group round robins plus a final placeholder")
    p_group.add_argument("--name", required=True)
    p_group.add_argument("--event-date", required=True, help="YYYY-MM-DD")
    p_group.add_argument("--country", default=None)
    p_group.add_argument("--player-ids", required=True, help="Comma-separated Amiga player ids")
    p_group.add_argument("--group-count", type=int, default=2)
    p_group.add_argument("--group-legs", type=int, choices=(1, 2), default=1)
    p_group.add_argument("--equal-teams", action="store_true")
    p_group.add_argument("--dry-run", action="store_true")

    p_verify = sub.add_parser("verify-built", help="Verify a generated tournament structure")
    p_verify.add_argument("--tournament-id", type=int, required=True)
    p_verify.add_argument("--allow-attached-games", action="store_true")

    p_smoke = sub.add_parser("smoke-fixture-result", help="Rollback smoke for build + fixture result entry")
    p_smoke.add_argument("--player-ids", required=True, help="Comma-separated Amiga player ids")

    args = parser.parse_args(argv)
    conn = _connect()
    try:
        if args.cmd == "create-kitchen-marathon":
            result = create_kitchen_marathon_tournament(
                conn,
                name=args.name,
                event_date=_parse_date(args.event_date),
                country=args.country,
                player_ids=_parse_player_ids(args.player_ids),
                legs=args.legs,
                equal_teams=args.equal_teams,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                "created "
                + ", ".join(f"{key}={value}" for key, value in result.items())
            )
            return 0

        if args.cmd == "create-group-knockout":
            result = create_group_knockout_tournament(
                conn,
                name=args.name,
                event_date=_parse_date(args.event_date),
                country=args.country,
                player_ids=_parse_player_ids(args.player_ids),
                group_count=args.group_count,
                group_legs=args.group_legs,
                equal_teams=args.equal_teams,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print(
                "created "
                + ", ".join(f"{key}={value}" for key, value in result.items())
            )
            return 0

        if args.cmd == "verify-built":
            errors = verify_built_tournament(
                conn,
                tournament_id=args.tournament_id,
                allow_attached_games=args.allow_attached_games,
            )
            if errors:
                for error in errors:
                    print(f"FAIL: {error}", file=sys.stderr)
                return 1
            print("OK: generated tournament structure verified")
            return 0

        if args.cmd == "smoke-fixture-result":
            result = smoke_fixture_result_flow(conn, player_ids=_parse_player_ids(args.player_ids))
            conn.rollback()
            print("DRY RUN: rolled back")
            print(
                "smoke "
                + ", ".join(f"{key}={value}" for key, value in result.items())
            )
            return 0
    finally:
        conn.close()

    return 1
