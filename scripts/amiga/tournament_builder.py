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
    GENERATED_DEFAULT_LIFECYCLE_STATUS,
    _connect,
    _load_one,
    _require_player,
    add_stage_player,
    add_tournament_entrant,
    create_fixture,
    create_stage,
    record_fixture_result,
    set_tournament_lifecycle_status,
)
from scripts.amiga.double_elim_bracket import (
    SUPPORTED_BRACKET_SIZES,
    bracket_round_count,
    expected_fixture_count,
    initial_round_defs,
    match_winner,
    next_round_defs,
    resolve_fixture_players,
    seed_map,
)
from scripts.amiga.swiss_pairing import (
    collect_played_pairs,
    standings_totals,
    swiss_round1_pairings,
    swiss_round_count,
    swiss_round_pairings,
)
from scripts.amiga.tournament_format import seed_format_templates
from scripts.amiga.tournament_standings import compute_tournament_standings

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
        "live_visible": 1,
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup, lifecycle_status)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 0, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 1, 0,
                 %(lifecycle_status)s)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
                "lifecycle_status": GENERATED_DEFAULT_LIFECYCLE_STATUS,
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
        name="League",
        stage_type="round_robin",
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
            phase_label=None,
        )

    return {
        "tournament_id": tournament_id,
        "stage_id": stage_id,
        "player_count": len(player_ids),
        "fixture_count": len(fixtures),
    }


def create_swiss_tournament(
    conn: pymysql.connections.Connection,
    *,
    name: str,
    event_date: date,
    country: str | None,
    player_ids: list[int],
    round_count: int | None = None,
    equal_teams: bool = False,
) -> dict[str, int]:
    """Create a Swiss event with round 1 pairings (by seed). Later rounds via generate_swiss_round."""
    for player_id in player_ids:
        _require_player(conn, player_id)

    rounds = round_count if round_count is not None else swiss_round_count(len(player_ids))
    if rounds < 1:
        raise ValueError("round_count must be at least 1")

    round1, bye_player = swiss_round1_pairings(player_ids)
    template_id = _template_id(conn, "swiss")
    overrides = {
        "generated_by": BUILDER_NAME,
        "pairing_policy": "swiss_standard",
        "round_count": rounds,
        "scheduled_rounds": [1],
        "fixture_count": len(round1),
        "round_1_bye_player_id": bye_player,
        "live_visible": 1,
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup, lifecycle_status)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 0, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 1, 0,
                 %(lifecycle_status)s)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
                "lifecycle_status": GENERATED_DEFAULT_LIFECYCLE_STATUS,
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
        name="Swiss",
        stage_type="round_robin",
        sequence_no=1,
        config={
            "generated_by": BUILDER_NAME,
            "format": "swiss",
            "round_count": rounds,
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

    for pairing in round1:
        create_fixture(
            conn,
            tournament_id=tournament_id,
            stage_key="overall",
            fixture_key=f"swiss-r{pairing.round_no:02d}-m{pairing.match_no:02d}",
            player_a_id=pairing.player_a_id,
            player_b_id=pairing.player_b_id,
            leg_no=1,
            phase_label=f"Round {pairing.round_no}",
        )

    return {
        "tournament_id": tournament_id,
        "stage_id": stage_id,
        "player_count": len(player_ids),
        "fixture_count": len(round1),
        "round_count": rounds,
    }


def generate_swiss_round(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    round_no: int,
) -> dict[str, int]:
    """Create fixtures for Swiss round N>1 from cumulative standings."""
    row = _load_one(
        conn,
        """
        SELECT t.id, t.player_count, t.format_overrides, ft.slug AS template_slug
        FROM tournaments t
        LEFT JOIN tournament_format_templates ft ON ft.id = t.format_template_id
        WHERE t.id = %s
        """,
        (tournament_id,),
    )
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    if str(row.get("template_slug")) != "swiss":
        raise ValueError(f"tournament_id={tournament_id} is not swiss format")

    overrides: dict[str, Any] = json.loads(str(row["format_overrides"] or "{}"))
    round_count = int(overrides.get("round_count", 0))
    scheduled = [int(r) for r in overrides.get("scheduled_rounds", [])]
    if round_no < 2 or round_no > round_count:
        raise ValueError(f"round_no must be between 2 and {round_count}")
    if round_no in scheduled:
        raise ValueError(f"round {round_no} fixtures already exist")
    if round_no != max(scheduled, default=0) + 1:
        raise ValueError(f"round {round_no} not next after scheduled rounds {scheduled}")

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT e.player_id
            FROM tournament_entrants e
            WHERE e.tournament_id = %s AND e.status = 'registered'
            ORDER BY e.seed_no ASC, e.player_id ASC
            """,
            (tournament_id,),
        )
        player_ids = [int(r["player_id"]) for r in cur.fetchall()]

        cur.execute(
            """
            SELECT g.player_a_id, g.player_b_id, g.goals_a, g.goals_b
            FROM amiga_games g
            WHERE g.tournament_id = %s
            ORDER BY g.id ASC
            """,
            (tournament_id,),
        )
        games = list(cur.fetchall())

    points, gf, ga = standings_totals(games)
    played = collect_played_pairs(games)
    pairings, bye_player = swiss_round_pairings(
        round_no=round_no,
        player_ids=player_ids,
        points=points,
        goals_for=gf,
        goals_against=ga,
        played_pairs=played,
    )

    for pairing in pairings:
        create_fixture(
            conn,
            tournament_id=tournament_id,
            stage_key="overall",
            fixture_key=f"swiss-r{pairing.round_no:02d}-m{pairing.match_no:02d}",
            player_a_id=pairing.player_a_id,
            player_b_id=pairing.player_b_id,
            leg_no=1,
            phase_label=f"Round {pairing.round_no}",
        )

    scheduled.append(round_no)
    overrides["scheduled_rounds"] = scheduled
    overrides["fixture_count"] = int(overrides.get("fixture_count", 0)) + len(pairings)
    if bye_player is not None:
        overrides[f"round_{round_no}_bye_player_id"] = bye_player

    with conn.cursor() as cur:
        cur.execute(
            "UPDATE tournaments SET format_overrides = %s WHERE id = %s",
            (json.dumps(overrides, sort_keys=True), tournament_id),
        )

    return {
        "tournament_id": tournament_id,
        "round_no": round_no,
        "fixture_count": len(pairings),
        "bye_player_id": bye_player or 0,
    }


def _load_de_outcomes(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
) -> dict[str, dict[str, int]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.fixture_key, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            INNER JOIN amiga_games g ON g.fixture_id = f.id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        rows = list(cur.fetchall())
    outcomes: dict[str, dict[str, int]] = {}
    for row in rows:
        winner_id, loser_id = match_winner(
            int(row["player_a_id"]),
            int(row["player_b_id"]),
            int(row["goals_a"]),
            int(row["goals_b"]),
        )
        outcomes[str(row["fixture_key"])] = {
            "winner_id": winner_id,
            "loser_id": loser_id,
        }
    return outcomes


def _de_fixture_exists(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    fixture_key: str,
) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.fixture_key = %s
            LIMIT 1
            """,
            (tournament_id, fixture_key),
        )
        return cur.fetchone() is not None


def create_double_elimination_tournament(
    conn: pymysql.connections.Connection,
    *,
    name: str,
    event_date: date,
    country: str | None,
    player_ids: list[int],
    equal_teams: bool = False,
) -> dict[str, int]:
    """Create double-elim bracket (winners R1 only; later rounds via advance_double_elim)."""
    bracket_size = len(player_ids)
    if bracket_size not in SUPPORTED_BRACKET_SIZES:
        raise ValueError(f"double elimination supports bracket sizes {sorted(SUPPORTED_BRACKET_SIZES)}, got {bracket_size}")

    for player_id in player_ids:
        _require_player(conn, player_id)

    seeds = seed_map(player_ids)
    template_id = _template_id(conn, "double_elimination")
    round0 = initial_round_defs(bracket_size)
    overrides = {
        "generated_by": BUILDER_NAME,
        "bracket_size": bracket_size,
        "bracket_round_created": 0,
        "fixture_count": len(round0),
        "expected_fixture_count": expected_fixture_count(bracket_size),
        "seeds": {str(k): v for k, v in seeds.items()},
        "live_visible": 1,
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup, lifecycle_status)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 1, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 0, 1,
                 %(lifecycle_status)s)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
                "lifecycle_status": GENERATED_DEFAULT_LIFECYCLE_STATUS,
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

    create_stage(
        conn,
        tournament_id=tournament_id,
        stage_key="winners",
        name="Winners Bracket",
        stage_type="knockout",
        sequence_no=1,
        config={"generated_by": BUILDER_NAME, "bracket": "winners"},
    )
    create_stage(
        conn,
        tournament_id=tournament_id,
        stage_key="losers",
        name="Losers Bracket",
        stage_type="knockout",
        sequence_no=2,
        config={"generated_by": BUILDER_NAME, "bracket": "losers"},
    )
    create_stage(
        conn,
        tournament_id=tournament_id,
        stage_key="grand_final",
        name="Grand Final",
        stage_type="knockout",
        sequence_no=3,
        config={"generated_by": BUILDER_NAME, "bracket": "grand_final"},
    )

    outcomes: dict[str, dict[str, int]] = {}
    for fixture in round0:
        player_a_id, player_b_id = resolve_fixture_players(
            fixture,
            seeds=seeds,
            outcomes=outcomes,
        )
        create_fixture(
            conn,
            tournament_id=tournament_id,
            stage_key=fixture.stage_key,
            fixture_key=fixture.fixture_key,
            player_a_id=player_a_id,
            player_b_id=player_b_id,
            leg_no=1,
            phase_label=fixture.phase_label,
        )

    return {
        "tournament_id": tournament_id,
        "player_count": len(player_ids),
        "fixture_count": len(round0),
        "bracket_size": bracket_size,
        "bracket_rounds": bracket_round_count(bracket_size),
    }


def advance_double_elim(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
) -> dict[str, int]:
    """Create the next bracket round when the current round is fully played."""
    row = _load_one(
        conn,
        """
        SELECT t.id, t.format_overrides, ft.slug AS template_slug
        FROM tournaments t
        LEFT JOIN tournament_format_templates ft ON ft.id = t.format_template_id
        WHERE t.id = %s
        """,
        (tournament_id,),
    )
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    if str(row.get("template_slug")) != "double_elimination":
        raise ValueError(f"tournament_id={tournament_id} is not double_elimination format")

    overrides: dict[str, Any] = json.loads(str(row["format_overrides"] or "{}"))
    bracket_size = int(overrides.get("bracket_size", 0))
    round_created = int(overrides.get("bracket_round_created", 0))
    next_index = round_created + 1
    if next_index >= bracket_round_count(bracket_size):
        return {"tournament_id": tournament_id, "fixtures_created": 0}

    outcomes = _load_de_outcomes(conn, tournament_id=tournament_id)
    from scripts.amiga.double_elim_bracket import BRACKET_ROUNDS

    for fixture in BRACKET_ROUNDS[bracket_size][round_created]:
        if fixture.fixture_key not in outcomes:
            return {"tournament_id": tournament_id, "fixtures_created": 0}

    seeds = {int(k): int(v) for k, v in dict(overrides.get("seeds", {})).items()}
    created = 0
    for fixture in next_round_defs(bracket_size, next_index):
        if _de_fixture_exists(conn, tournament_id=tournament_id, fixture_key=fixture.fixture_key):
            continue
        player_a_id, player_b_id = resolve_fixture_players(
            fixture,
            seeds=seeds,
            outcomes=outcomes,
        )
        create_fixture(
            conn,
            tournament_id=tournament_id,
            stage_key=fixture.stage_key,
            fixture_key=fixture.fixture_key,
            player_a_id=player_a_id,
            player_b_id=player_b_id,
            leg_no=1,
            phase_label=fixture.phase_label,
        )
        created += 1

    overrides["bracket_round_created"] = next_index
    overrides["fixture_count"] = int(overrides.get("fixture_count", 0)) + created
    with conn.cursor() as cur:
        cur.execute(
            "UPDATE tournaments SET format_overrides = %s WHERE id = %s",
            (json.dumps(overrides, sort_keys=True), tournament_id),
        )

    return {
        "tournament_id": tournament_id,
        "fixtures_created": created,
        "bracket_round_created": next_index,
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
        "live_visible": 1,
    }

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournaments
                (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                 format_template_id, format_overrides, has_league, has_cup, lifecycle_status)
            VALUES
                (NULL, %(name)s, NULL, %(event_date)s, 1, %(country)s, %(equal_teams)s,
                 %(player_count)s, %(format_template_id)s, %(format_overrides)s, 1, 1,
                 %(lifecycle_status)s)
            """,
            {
                "name": name,
                "event_date": event_date.isoformat(),
                "country": country,
                "equal_teams": equal_teams,
                "player_count": len(player_ids),
                "format_template_id": template_id,
                "format_overrides": json.dumps(overrides, sort_keys=True),
                "lifecycle_status": GENERATED_DEFAULT_LIFECYCLE_STATUS,
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
            stage_type="round_robin",
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
        SELECT t.id, t.name, t.has_league, t.has_cup, t.lifecycle_status,
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
    if template_slug not in {"kitchen_marathon", "group_knockout", "swiss", "double_elimination"}:
        errors.append(f"template is {template_slug!r}, expected generated template")
    if template_slug == "kitchen_marathon" and (int(row["has_league"]) != 1 or int(row["has_cup"]) != 0):
        errors.append("expected kitchen_marathon has_league=1 and has_cup=0")
    if template_slug == "group_knockout" and (int(row["has_league"]) != 1 or int(row["has_cup"]) != 1):
        errors.append("expected group_knockout has_league=1 and has_cup=1")
    if template_slug == "swiss" and (int(row["has_league"]) != 1 or int(row["has_cup"]) != 0):
        errors.append("expected swiss has_league=1 and has_cup=0")
    if template_slug == "double_elimination" and (int(row["has_league"]) != 0 or int(row["has_cup"]) != 1):
        errors.append("expected double_elimination has_league=0 and has_cup=1")
    if str(row.get("lifecycle_status")) != GENERATED_DEFAULT_LIFECYCLE_STATUS and not allow_attached_games:
        errors.append(
            f"lifecycle_status is {row.get('lifecycle_status')!r}, "
            f"expected {GENERATED_DEFAULT_LIFECYCLE_STATUS!r}"
        )

    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM tournament_entrants WHERE tournament_id = %s AND status = 'registered'",
            (tournament_id,),
        )
        entrants = int(cur.fetchone()["n"])
    if entrants < 1:
        errors.append("expected at least one registered entrant, found 0")

    overrides: dict[str, Any] = {}
    if row.get("format_overrides"):
        overrides = json.loads(str(row["format_overrides"]))
    if template_slug == "group_knockout":
        expected_fixtures = int(overrides.get("group_fixture_count", 0)) + int(
            overrides.get("knockout_fixture_count", 0)
        )
    elif template_slug == "swiss":
        expected_fixtures = int(overrides.get("fixture_count", 0))
    elif template_slug == "double_elimination":
        expected_fixtures = int(overrides.get("fixture_count", 0))
    else:
        legs = int(overrides.get("round_robin_legs", 1))
        expected_fixtures = expected_round_robin_fixtures(entrants, legs=legs)

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s", (tournament_id,))
        stage_count = int(cur.fetchone()["n"])
        if template_slug == "group_knockout" and stage_count < 2:
            errors.append(f"expected at least two stages for group_knockout, got {stage_count}")
        elif template_slug == "double_elimination" and stage_count != 3:
            errors.append(f"expected three stages for double_elimination, got {stage_count}")
        elif template_slug in {"kitchen_marathon", "swiss"} and stage_count != 1:
            errors.append(f"expected exactly one stage, got {stage_count}")

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
        if template_slug not in {"double_elimination"} and stage_players != entrants:
            errors.append(f"stage player count {stage_players}, expected {entrants}")

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
        if attached_games:
            errors.append(f"expected no official games yet, found {attached_games}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.status = 'played'
            """,
            (tournament_id,),
        )
        played_fixtures = int(cur.fetchone()["n"])
        if allow_attached_games:
            if played_fixtures == 0:
                errors.append("expected played fixtures with running results, found none")
        elif played_fixtures:
            errors.append(f"expected no played fixtures yet, found {played_fixtures}")

    return errors


def _scheduled_fixtures(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    status: str = "scheduled",
) -> list[int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.status = %s
            ORDER BY f.id ASC
            """,
            (tournament_id, status),
        )
        return [int(row["id"]) for row in cur.fetchall()]


def _load_tournament_games(conn: pymysql.connections.Connection, tournament_id: int) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id,
                   g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id,
                   g.fixture_id,
                   f.phase_label AS fixture_phase_label,
                   s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
            FROM amiga_games g
            LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
            LEFT JOIN tournament_stages s ON s.id = f.stage_id
            WHERE g.tournament_id = %s
            ORDER BY g.id ASC
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def smoke_double_elim_flow(conn: pymysql.connections.Connection, *, player_ids: list[int]) -> dict[str, int]:
    """Rollback smoke: play through a 4-player double-elim bracket."""
    if len(player_ids) != 4:
        raise ValueError("smoke-double-elim requires exactly four player ids")

    result = create_double_elimination_tournament(
        conn,
        name="Double Elim Smoke Rollback",
        event_date=date.today(),
        country="Test",
        player_ids=player_ids,
    )
    tournament_id = int(result["tournament_id"])
    expected_games = expected_fixture_count(4)
    set_tournament_lifecycle_status(conn, tournament_id=tournament_id, status="running")

    safety = 0
    while safety < 20:
        safety += 1
        scheduled = _scheduled_fixtures(conn, tournament_id=tournament_id)
        if scheduled:
            for fixture_id in scheduled:
                record_fixture_result(conn, fixture_id=fixture_id, goals_a=3, goals_b=1)
            continue
        advance = advance_double_elim(conn, tournament_id=tournament_id)
        if int(advance["fixtures_created"]) == 0:
            break

    games = _load_tournament_games(conn, tournament_id)
    if len(games) != expected_games:
        raise ValueError(f"expected {expected_games} double-elim games, got {len(games)}")

    standings = compute_tournament_standings(games)
    knockout_rows = [r for r in standings if r["scope_type"] == "knockout"]
    knockout_scopes = {r["scope_key"] for r in knockout_rows}
    if len(knockout_scopes) != expected_games:
        raise ValueError(
            f"expected {expected_games} knockout scopes, got {len(knockout_scopes)}"
        )

    errors = verify_built_tournament(conn, tournament_id=tournament_id, allow_attached_games=True)
    if errors:
        raise ValueError("; ".join(errors))

    return {
        **result,
        "games_played": len(games),
        "knockout_scopes": len(knockout_scopes),
    }


def smoke_swiss_flow(conn: pymysql.connections.Connection, *, player_ids: list[int]) -> dict[str, int]:
    """Rollback smoke: create 4-player Swiss, play two rounds, assert overall standings."""
    if len(player_ids) != 4:
        raise ValueError("smoke-swiss requires exactly four player ids")

    result = create_swiss_tournament(
        conn,
        name="Swiss Smoke Rollback",
        event_date=date.today(),
        country="Test",
        player_ids=player_ids,
    )
    tournament_id = int(result["tournament_id"])
    set_tournament_lifecycle_status(conn, tournament_id=tournament_id, status="running")

    for idx, fixture_id in enumerate(_scheduled_fixtures(conn, tournament_id=tournament_id)):
        record_fixture_result(conn, fixture_id=fixture_id, goals_a=3, goals_b=idx % 2)

    generate_swiss_round(conn, tournament_id=tournament_id, round_no=2)
    for fixture_id in _scheduled_fixtures(conn, tournament_id=tournament_id):
        record_fixture_result(conn, fixture_id=fixture_id, goals_a=2, goals_b=1)

    games = _load_tournament_games(conn, tournament_id)
    standings = compute_tournament_standings(games)
    league = [r for r in standings if r["scope_type"] == "league" and r["scope_key"] == ""]
    if len(league) != 4:
        raise ValueError(f"expected 4 league standings rows, got {len(league)}")
    if len(games) != 4:
        raise ValueError(f"expected 4 Swiss games (2 rounds × 2 pairings), got {len(games)}")

    errors = verify_built_tournament(conn, tournament_id=tournament_id, allow_attached_games=True)
    if errors:
        raise ValueError("; ".join(errors))

    return {
        **result,
        "games_played": len(games),
        "standings_rows": len(league),
    }


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
    set_tournament_lifecycle_status(
        conn,
        tournament_id=result["tournament_id"],
        status="running",
    )
    fixture_id = record_fixture_result(conn, fixture_id=fixture_id, goals_a=1, goals_b=0)
    errors = verify_built_tournament(conn, tournament_id=result["tournament_id"], allow_attached_games=True)
    if errors:
        raise ValueError("; ".join(errors))
    return {
        **result,
        "fixture_id": fixture_id,
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

    p_swiss = sub.add_parser("create-swiss", help="Create a Swiss tournament (round 1 pairings by seed)")
    p_swiss.add_argument("--name", required=True)
    p_swiss.add_argument("--event-date", required=True, help="YYYY-MM-DD")
    p_swiss.add_argument("--country", default=None)
    p_swiss.add_argument("--player-ids", required=True, help="Comma-separated Amiga player ids (seed order)")
    p_swiss.add_argument("--round-count", type=int, default=None)
    p_swiss.add_argument("--equal-teams", action="store_true")
    p_swiss.add_argument("--dry-run", action="store_true")

    p_swiss_round = sub.add_parser("generate-swiss-round", help="Generate Swiss round N>1 from standings")
    p_swiss_round.add_argument("--tournament-id", type=int, required=True)
    p_swiss_round.add_argument("--round", type=int, required=True)
    p_swiss_round.add_argument("--dry-run", action="store_true")

    p_smoke_swiss = sub.add_parser("smoke-swiss", help="Rollback smoke for Swiss create + two rounds + standings")
    p_smoke_swiss.add_argument("--player-ids", required=True, help="Exactly four comma-separated Amiga player ids")

    p_de = sub.add_parser("create-double-elim", help="Create double-elimination bracket (4 or 8 players)")
    p_de.add_argument("--name", required=True)
    p_de.add_argument("--event-date", required=True, help="YYYY-MM-DD")
    p_de.add_argument("--country", default=None)
    p_de.add_argument("--player-ids", required=True, help="Comma-separated player ids in seed order")
    p_de.add_argument("--equal-teams", action="store_true")
    p_de.add_argument("--dry-run", action="store_true")

    p_de_adv = sub.add_parser("advance-double-elim", help="Create next double-elim round from played results")
    p_de_adv.add_argument("--tournament-id", type=int, required=True)
    p_de_adv.add_argument("--dry-run", action="store_true")

    p_smoke_de = sub.add_parser("smoke-double-elim", help="Rollback smoke for 4-player double elimination")
    p_smoke_de.add_argument("--player-ids", required=True, help="Exactly four comma-separated Amiga player ids")

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

        if args.cmd == "create-swiss":
            result = create_swiss_tournament(
                conn,
                name=args.name,
                event_date=_parse_date(args.event_date),
                country=args.country,
                player_ids=_parse_player_ids(args.player_ids),
                round_count=args.round_count,
                equal_teams=args.equal_teams,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print("created " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0

        if args.cmd == "generate-swiss-round":
            result = generate_swiss_round(
                conn,
                tournament_id=args.tournament_id,
                round_no=args.round,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print("generated " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0

        if args.cmd == "smoke-swiss":
            result = smoke_swiss_flow(conn, player_ids=_parse_player_ids(args.player_ids))
            conn.rollback()
            print("DRY RUN: rolled back")
            print("smoke-swiss " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0

        if args.cmd == "create-double-elim":
            result = create_double_elimination_tournament(
                conn,
                name=args.name,
                event_date=_parse_date(args.event_date),
                country=args.country,
                player_ids=_parse_player_ids(args.player_ids),
                equal_teams=args.equal_teams,
            )
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print("created " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0

        if args.cmd == "advance-double-elim":
            result = advance_double_elim(conn, tournament_id=args.tournament_id)
            if args.dry_run:
                conn.rollback()
                print("DRY RUN: rolled back")
            else:
                conn.commit()
            print("advanced " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0

        if args.cmd == "smoke-double-elim":
            result = smoke_double_elim_flow(conn, player_ids=_parse_player_ids(args.player_ids))
            conn.rollback()
            print("DRY RUN: rolled back")
            print("smoke-double-elim " + ", ".join(f"{key}={value}" for key, value in result.items()))
            return 0
    finally:
        conn.close()

    return 1
