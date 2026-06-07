"""Tournament format template seeding and legacy flag inference."""

from __future__ import annotations

import json
import sys
from dataclasses import dataclass
from typing import TYPE_CHECKING, Iterable

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_names import resolve_phase, resolve_tournament_name
from scripts.amiga.tournament_phases import is_knockout_phase, is_league_scope, parse_phase

if TYPE_CHECKING:
    from scripts.amiga.import_access import AccessScore


LEGACY_TEMPLATE_SLUG = "legacy_inferred"

FORMAT_TEMPLATES: tuple[dict[str, object], ...] = (
    {
        "slug": LEGACY_TEMPLATE_SLUG,
        "name": "Legacy inferred",
        "description": "Imported Access event; structure inferred from phase labels and catalog hints.",
        "spec": {
            "source": "access_import",
            "legacy_phase_fallback": True,
            "fixture_backed": False,
        },
    },
    {
        "slug": "kitchen_marathon",
        "name": "Kitchen marathon",
        "description": "Single-table round-robin or open marathon format.",
        "spec": {
            "stages": [{"key": "overall", "type": "league"}],
            "legacy_phase_fallback": False,
        },
    },
    {
        "slug": "group_knockout",
        "name": "Group + knockout",
        "description": "Group league stage followed by elimination ties.",
        "spec": {
            "stages": [
                {"key": "groups", "type": "league_groups"},
                {"key": "knockout", "type": "knockout"},
            ],
            "legacy_phase_fallback": False,
        },
    },
    {
        "slug": "world_cup_class",
        "name": "World Cup class",
        "description": "Multi-track World Cup-style event with groups and placement cups.",
        "spec": {
            "tracks": ["main", "silver", "bronze", "koa"],
            "stages": [
                {"key": "round_1", "type": "league_groups"},
                {"key": "round_2", "type": "league_groups"},
                {"key": "classification", "type": "knockout_tracks"},
            ],
            "legacy_phase_fallback": False,
        },
    },
)


@dataclass(frozen=True, slots=True)
class TournamentFormatInference:
    has_league: bool
    has_cup: bool
    game_count: int


def seed_format_templates(conn: pymysql.connections.Connection) -> dict[str, int]:
    """Upsert built-in templates and return slug -> id."""
    insert_sql = """
        INSERT INTO tournament_format_templates
            (slug, name, schema_version, description, spec_json)
        VALUES
            (%(slug)s, %(name)s, 1, %(description)s, %(spec_json)s)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            schema_version = VALUES(schema_version),
            description = VALUES(description),
            spec_json = VALUES(spec_json)
    """
    rows = [
        {
            "slug": t["slug"],
            "name": t["name"],
            "description": t["description"],
            "spec_json": json.dumps(t["spec"], sort_keys=True),
        }
        for t in FORMAT_TEMPLATES
    ]
    with conn.cursor() as cur:
        cur.executemany(insert_sql, rows)
        cur.execute("SELECT id, slug FROM tournament_format_templates")
        return {str(row["slug"]): int(row["id"]) for row in cur.fetchall()}


def infer_legacy_tournament_format(
    *,
    is_cup: bool,
    phases: Iterable[str | None],
) -> TournamentFormatInference:
    has_league = False
    has_cup = bool(is_cup)
    game_count = 0

    for phase in phases:
        game_count += 1
        scope = parse_phase(phase)
        if is_league_scope(scope):
            has_league = True
        if is_knockout_phase(phase):
            has_cup = True

    return TournamentFormatInference(
        has_league=has_league,
        has_cup=has_cup,
        game_count=game_count,
    )


def infer_legacy_tournament_formats(
    tournaments: list[dict],
    scores: list["AccessScore"],
) -> dict[str, TournamentFormatInference]:
    """Infer non-exclusive catalog flags from canonical import parent + phase labels."""
    phases_by_parent: dict[str, list[str | None]] = {str(t["name"]): [] for t in tournaments}
    for score in scores:
        parent = resolve_tournament_name(score.raw_tournament)
        phases_by_parent.setdefault(parent, []).append(resolve_phase(score.raw_tournament, score.phase))

    by_name: dict[str, TournamentFormatInference] = {}
    for t in tournaments:
        name = str(t["name"])
        by_name[name] = infer_legacy_tournament_format(
            is_cup=bool(t.get("is_cup")),
            phases=phases_by_parent.get(name, []),
        )
    return by_name


def audit_tournament_format_flags(conn: pymysql.connections.Connection) -> list[dict[str, object]]:
    """Return tournaments with games but neither format flag set."""
    sql = """
        SELECT t.id, t.name, COUNT(g.id) AS game_count
        FROM tournaments t
        INNER JOIN amiga_games g ON g.tournament_id = t.id
        GROUP BY t.id, t.name, t.has_league, t.has_cup
        HAVING game_count > 0 AND t.has_league = 0 AND t.has_cup = 0
        ORDER BY t.id
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return list(cur.fetchall())


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing format audit: expected ko2amiga_db, got {cfg.database!r}")
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def format_bucket_counts(conn: pymysql.connections.Connection) -> dict[str, int]:
    sql = """
        SELECT
          SUM(CASE WHEN has_league = 1 AND has_cup = 0 THEN 1 ELSE 0 END) AS league_only,
          SUM(CASE WHEN has_league = 0 AND has_cup = 1 THEN 1 ELSE 0 END) AS cup_only,
          SUM(CASE WHEN has_league = 1 AND has_cup = 1 THEN 1 ELSE 0 END) AS league_and_cup,
          SUM(CASE WHEN has_league = 0 AND has_cup = 0 THEN 1 ELSE 0 END) AS neither
        FROM tournaments
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()
    return {key: int(row[key] or 0) for key in ("league_only", "cup_only", "league_and_cup", "neither")}


def main(argv: list[str] | None = None) -> int:
    _ = argv
    conn = _connect()
    try:
        failures = audit_tournament_format_flags(conn)
        buckets = format_bucket_counts(conn)
    finally:
        conn.close()

    print("Tournament format buckets: " + ", ".join(f"{k}={v}" for k, v in buckets.items()))
    if failures:
        for row in failures:
            print(
                f"FAIL: tournament id={row['id']} name={row['name']!r} "
                f"has {row['game_count']} games but neither flag",
                file=sys.stderr,
            )
        return 1
    print("OK: every tournament with games has has_league or has_cup")
    return 0
