"""Create stages and fixtures from a StructureSpec (import backfill)."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass
from itertools import combinations
from typing import Any

import pymysql

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_format import seed_format_templates
from scripts.amiga.tournament_structure.specs import FixtureSpec, StructureSpec, is_round_robin_group_stage, normalize_stage_type

log = logging.getLogger(__name__)

_ROUND_STAGE_KEY = {
    "last_16": "ko-last-16",
    "quarter": "ko-quarter",
    "semi": "ko-semi",
    "placement_3rd": "ko-placement-3rd",
    "final": "ko-final",
}


@dataclass(frozen=True, slots=True)
class BuiltFixture:
    """One schedulable slot with DB id for game linking."""

    fixture_id: int
    player_a_id: int
    player_b_id: int
    stage_key: str
    leg_no: int


@dataclass(frozen=True, slots=True)
class StructureBuildResult:
    tournament_id: int
    catalog_name: str
    fixtures: tuple[BuiltFixture, ...]
    fixture_count: int
    stage_count: int


def _player_slug(name: str) -> str:
    return name.lower().replace(" ", "-").replace(".", "")


def _import_create_fixture(
    conn: pymysql.connections.Connection,
    *,
    stage_id: int,
    fixture_key: str,
    player_a_id: int,
    player_b_id: int,
    leg_no: int,
    phase_label: str,
) -> int:
    """Insert fixture without live-ops entrant guardrails (historical import only)."""
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_fixtures
                (stage_id, fixture_key, player_a_id, player_b_id, leg_no, status, phase_label)
            VALUES (%s, %s, %s, %s, %s, 'played', %s)
            ON DUPLICATE KEY UPDATE
                player_a_id = VALUES(player_a_id),
                player_b_id = VALUES(player_b_id),
                leg_no = VALUES(leg_no),
                status = VALUES(status),
                phase_label = VALUES(phase_label)
            """,
            (stage_id, fixture_key, player_a_id, player_b_id, leg_no, phase_label),
        )
        cur.execute(
            "SELECT id FROM tournament_fixtures WHERE stage_id = %s AND fixture_key = %s",
            (stage_id, fixture_key),
        )
        return int(cur.fetchone()["id"])


def _import_add_stage_player(
    conn: pymysql.connections.Connection,
    *,
    stage_id: int,
    player_id: int,
    seed_no: int,
    group_key: str | None,
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_stage_players (stage_id, player_id, seed_no, group_key)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE seed_no = VALUES(seed_no), group_key = VALUES(group_key)
            """,
            (stage_id, player_id, seed_no, group_key),
        )


def _resolve_player_id(player_id: dict[str, int], name: str) -> int:
    if name not in player_id:
        raise ValueError(f"player {name!r} not in import player map")
    return player_id[name]


def _expand_group_fixtures(
    spec: StructureSpec,
    player_id: dict[str, int],
) -> list[tuple[str, str, FixtureSpec]]:
    """Return (stage_key, phase_label, fixture_spec) for group round-robin legs."""
    out: list[tuple[str, str, FixtureSpec]] = []
    for stage in spec.stages:
        if not is_round_robin_group_stage(stage):
            continue
        for roster in stage.groups:
            players = roster.player_names
            group_key = roster.group_key
            phase_label = f"Group {group_key}"
            for player_a, player_b in combinations(players, 2):
                slug_a = _player_slug(player_a)
                slug_b = _player_slug(player_b)
                fixture_key = f"group-{group_key.lower()}-{slug_a}-vs-{slug_b}"
                out.append(
                    (
                        stage.stage_key,
                        phase_label,
                        FixtureSpec(
                            fixture_key=fixture_key,
                            stage_key=stage.stage_key,
                            player_a=player_a,
                            player_b=player_b,
                            leg_no=1,
                            group_key=group_key,
                        ),
                    )
                )
    return out


def _expand_knockout_fixtures(spec: StructureSpec) -> list[tuple[str, str, FixtureSpec]]:
    out: list[tuple[str, str, FixtureSpec]] = []
    for fixture in spec.fixtures:
        round_key = fixture.round_key or ""
        stage_key = _ROUND_STAGE_KEY.get(round_key, fixture.stage_key)
        phase_label = _knockout_phase_label(round_key, fixture)
        out.append((stage_key, phase_label, fixture))
    return out


def _knockout_phase_label(round_key: str, _fixture: FixtureSpec) -> str:
    return {
        "last_16": "Round of 16",
        "quarter": "Quarter Finals",
        "semi": "Semi Finals",
        "placement_3rd": "3rd Place Final",
        "final": "Final",
    }.get(round_key, round_key.replace("_", " ").title())


def build_tournament_structure(
    conn: pymysql.connections.Connection,
    spec: StructureSpec,
    *,
    tournament_id: int,
    player_id: dict[str, int],
) -> StructureBuildResult:
    """Create stages, stage players, and fixtures for one registered spec."""
    template_ids = seed_format_templates(conn)
    template_id = template_ids.get(spec.template_slug)
    if template_id is None:
        raise ValueError(f"unknown template_slug {spec.template_slug!r}")

    overrides = dict(spec.format_overrides)
    if spec.evidence_url and "evidence_url" not in overrides:
        overrides["evidence_url"] = spec.evidence_url
    overrides["structure_spec"] = spec.catalog_name

    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE tournaments
            SET format_template_id = %s,
                has_league = 1,
                has_cup = 1,
                format_overrides = %s
            WHERE id = %s
            """,
            (template_id, json.dumps(overrides, sort_keys=True), tournament_id),
        )

    stage_id_by_key: dict[str, int] = {}
    for seq, stage in enumerate(spec.stages, start=1):
        track_key = stage.group_keys[0] if stage.group_keys else None
        stage_id = create_stage(
            conn,
            tournament_id=tournament_id,
            stage_key=stage.stage_key,
            name=stage.name,
            stage_type=normalize_stage_type(stage.stage_type),
            sequence_no=seq,
            track_key=track_key,
            config={"import_structure": spec.catalog_name},
        )
        stage_id_by_key[stage.stage_key] = stage_id
        if is_round_robin_group_stage(stage):
            roster = stage.groups[0]
            for seed_no, player_name in enumerate(roster.player_names, start=1):
                pid = _resolve_player_id(player_id, player_name)
                _import_add_stage_player(
                    conn,
                    stage_id=stage_id,
                    player_id=pid,
                    seed_no=seed_no,
                    group_key=roster.group_key,
                )

    built: list[BuiltFixture] = []
    planned: list[tuple[str, str, FixtureSpec]] = []
    planned.extend(_expand_group_fixtures(spec, player_id))
    planned.extend(_expand_knockout_fixtures(spec))

    for stage_key, phase_label, fixture in planned:
        stage_id = stage_id_by_key[fixture.stage_key]
        player_a_id = _resolve_player_id(player_id, str(fixture.player_a))
        player_b_id = _resolve_player_id(player_id, str(fixture.player_b))
        fixture_id = _import_create_fixture(
            conn,
            stage_id=stage_id,
            fixture_key=fixture.fixture_key,
            player_a_id=player_a_id,
            player_b_id=player_b_id,
            leg_no=fixture.leg_no,
            phase_label=phase_label,
        )
        built.append(
            BuiltFixture(
                fixture_id=fixture_id,
                player_a_id=player_a_id,
                player_b_id=player_b_id,
                stage_key=stage_key,
                leg_no=fixture.leg_no,
            )
        )

    log.info(
        "Built structure for %r: %s stages, %s fixtures",
        spec.catalog_name,
        len(stage_id_by_key),
        len(built),
    )
    return StructureBuildResult(
        tournament_id=tournament_id,
        catalog_name=spec.catalog_name,
        fixtures=tuple(built),
        fixture_count=len(built),
        stage_count=len(stage_id_by_key),
    )
