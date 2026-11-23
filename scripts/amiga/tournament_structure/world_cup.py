"""World Cup structure from the hand-reviewed register (``world_cup_register.json``).

Every round of every World Cup was checked by hand against its games (players, pairs, legs):
a group is all-play-all, a knockout round is ties of two players. The register says which,
plus the cup each round belongs to (main, KOA Cup, Silver/Bronze Cup, Playouts, Fun Cup) and
where a knockout round is shown (bracket section and order). This module only applies it:
one ``round_robin`` stage per group, one ``knockout`` stage per tie, one fixture per game.

Stage names keep today's standings scope keys: groups use ``parse_phase`` (as before),
knockout ties the round name as recorded. Policy: docs/amiga-tournament-structure-display-policy.md.
"""

from __future__ import annotations

import json
import logging
import re
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

import pymysql

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_phases import parse_phase
from scripts.amiga.tournament_structure.materialize_legacy import (
    MaterializeResult,
    _clear_tournament_structure,
    _import_create_fixture,
    _load_games,
    _load_tournament,
    _slug_key,
)
from scripts.amiga.tournament_structure.specs import STAGE_TYPE_KNOCKOUT, STAGE_TYPE_ROUND_ROBIN

log = logging.getLogger(__name__)

REGISTER_PATH = Path(__file__).with_name("world_cup_register.json")
MATERIALIZE_SOURCE = "scripts.amiga.tournament_structure.world_cup"
VALID_TYPES = frozenset({"group", "knockout"})
VALID_TRACKS = frozenset({"main", "koa", "silver", "bronze", "playouts", "fun"})
VALID_SECTIONS = frozenset({"main", "placement_bracket", "placement_final", "other"})


def _label(phase: Any) -> str:
    return re.sub(r"\s+", " ", str(phase or "").strip())


def load_world_cup_register(path: Path | None = None) -> dict[int, dict[str, Any]]:
    """World Cup id → {name, rounds: {round name: decision}}; refuses a malformed register."""
    raw = json.loads((path or REGISTER_PATH).read_text(encoding="utf-8"))
    out: dict[int, dict[str, Any]] = {}
    for key, wc in raw["world_cups"].items():
        for label, d in wc["rounds"].items():
            where = f"world_cup_register {key} {label!r}"
            if d.get("type") not in VALID_TYPES:
                raise ValueError(f"{where}: type must be one of {sorted(VALID_TYPES)}")
            if d.get("track") not in VALID_TRACKS:
                raise ValueError(f"{where}: track must be one of {sorted(VALID_TRACKS)}")
            if d["type"] == "knockout" and d.get("section") not in VALID_SECTIONS:
                raise ValueError(f"{where}: knockout section must be one of {sorted(VALID_SECTIONS)}")
        out[int(key)] = wc
    return out


def world_cup_register_ids() -> frozenset[int]:
    return frozenset(load_world_cup_register())


def materialize_world_cup(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
    replace: bool = False,
    register: dict[int, dict[str, Any]] | None = None,
) -> MaterializeResult:
    """Build stages and fixtures of one World Cup from the register; links every game."""
    reg = register if register is not None else load_world_cup_register()
    wc = reg.get(tournament_id)
    if wc is None:
        raise ValueError(f"tournament_id={tournament_id} is not in the World Cup register")
    tournament = _load_tournament(conn, tournament_id)
    games = _load_games(conn, tournament_id)
    if not games:
        raise ValueError(f"tournament_id={tournament_id} has no games")

    rounds: dict[str, dict[str, Any]] = {_label(k): v for k, v in wc["rounds"].items()}
    missing = sorted({_label(g["phase"]) for g in games} - set(rounds))
    if missing:
        raise ValueError(f"tournament_id={tournament_id}: rounds not in the register: {missing}")

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s", (tournament_id,))
        existing = int(cur.fetchone()["n"])
    if existing and not replace:
        raise ValueError(f"tournament_id={tournament_id} already has {existing} stage(s) — pass replace=True")
    if existing:
        _clear_tournament_structure(conn, tournament_id)

    # Stage per group, stage per knockout tie, in register order (groups first).
    order = {label: i for i, label in enumerate(rounds)}
    stages: dict[str, dict[str, Any]] = {}
    games_by_stage: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for g in games:
        label = _label(g["phase"])
        d = rounds[label]
        if d["type"] == "group":
            stage_key = _slug_key(label)
            name = parse_phase(label).scope_key
            stage_type = STAGE_TYPE_ROUND_ROBIN
        else:
            lo, hi = sorted((int(g["player_a_id"]), int(g["player_b_id"])))
            stage_key = f"ko-{_slug_key(label, fallback='knockout')}-{lo}-{hi}"
            name = label
            stage_type = STAGE_TYPE_KNOCKOUT
        if stage_key not in stages:
            config: dict[str, Any] = {
                "materialized_by": MATERIALIZE_SOURCE,
                "legacy_import": True,
                "world_cup_round": label,
                "track": d["track"],
            }
            if d["type"] == "knockout":
                config["bracket_section"] = d["section"]
                config["round_order"] = int(d["order"])
                config["round_title"] = d.get("title", label)
            stages[stage_key] = {
                "name": name,
                "stage_type": stage_type,
                "track": d["track"],
                "config": config,
                "sort": (0 if d["type"] == "group" else 1, order[label], stage_key),
            }
        games_by_stage[stage_key].append(g)

    result = MaterializeResult(
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
        dry_run=dry_run,
    )
    for seq, stage_key in enumerate(sorted(stages, key=lambda k: stages[k]["sort"]), start=1):
        s = stages[stage_key]
        stage_id = create_stage(
            conn,
            tournament_id=tournament_id,
            stage_key=stage_key,
            name=s["name"],
            stage_type=s["stage_type"],
            sequence_no=seq,
            track_key=s["track"],
            config=s["config"],
        )
        result.stages_created += 1
        result.stage_summary.append(
            {"stage_key": stage_key, "name": s["name"], "stage_type": s["stage_type"],
             "game_count": len(games_by_stage[stage_key])}
        )
        for g in games_by_stage[stage_key]:
            fixture_id = _import_create_fixture(
                conn,
                stage_id=stage_id,
                fixture_key=f"legacy-g{int(g['id'])}",
                player_a_id=int(g["player_a_id"]),
                player_b_id=int(g["player_b_id"]),
                leg_no=1,
                phase_label=None,  # the stage name is the label (keeps today's scope keys)
            )
            with conn.cursor() as cur:
                cur.execute("UPDATE amiga_games SET fixture_id = %s WHERE id = %s", (fixture_id, int(g["id"])))
            result.fixtures_created += 1
            result.games_linked += 1
    log.info(
        "materialize_world_cup tournament_id=%s stages=%s games=%s dry_run=%s",
        tournament_id, result.stages_created, result.games_linked, dry_run,
    )
    return result


def verify_world_cup(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    register: dict[int, dict[str, Any]] | None = None,
) -> list[str]:
    """Problems with one World Cup's stored structure (empty list = right)."""
    reg = register if register is not None else load_world_cup_register()
    wc = reg[tournament_id]
    rounds = {_label(k): v for k, v in wc["rounds"].items()}
    problems: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id, g.phase, g.player_a_id, g.player_b_id, s.stage_type, s.track_key, s.tournament_id AS stid
            FROM amiga_games g
            LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
            LEFT JOIN tournament_stages s ON s.id = f.stage_id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        games = list(cur.fetchall())
    by_round: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for g in games:
        label = _label(g["phase"])
        by_round[label].append(g)
        d = rounds.get(label)
        if d is None:
            problems.append(f"game {g['id']}: round {label!r} not in the register")
            continue
        want = STAGE_TYPE_ROUND_ROBIN if d["type"] == "group" else STAGE_TYPE_KNOCKOUT
        if g["stage_type"] is None or int(g["stid"]) != tournament_id:
            problems.append(f"game {g['id']}: not linked to a stage of this tournament")
        elif g["stage_type"] != want or g["track_key"] != d["track"]:
            problems.append(f"game {g['id']} ({label}): stored {g['stage_type']}/{g['track_key']}, register {want}/{d['track']}")
    for label in rounds:
        if label not in by_round:
            problems.append(f"register round {label!r} has no games")
    # Shape: a group is all-play-all; in a knockout round each player meets one opponent
    # (a round named twice for two separate ties carries a note in the register).
    for label, gs in by_round.items():
        d = rounds.get(label)
        if d is None:
            continue
        pairs = {tuple(sorted((int(g["player_a_id"]), int(g["player_b_id"])))) for g in gs}
        per_player = Counter(p for pair in pairs for p in pair)
        n = len(per_player)
        if d["type"] == "group" and len(pairs) != n * (n - 1) // 2:
            problems.append(f"group {label!r}: {n} players but {len(pairs)} pairs (not all-play-all)")
        if d["type"] == "knockout" and max(per_player.values()) > 1 and not d.get("note"):
            problems.append(f"knockout round {label!r}: a player meets more than one opponent")
    return problems
