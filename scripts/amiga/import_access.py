#!/usr/bin/env python3
"""Import ground truth from koatd.mdb into ko2amiga_db."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass
from datetime import date, datetime, timedelta
from pathlib import Path

import pymysql
import pyodbc
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_corrections import (
    IMPORT_SUPPLEMENT_SCORES_ID_BASE,
    SUPPLEMENTAL_SCORES,
    apply_catalog_corrections,
    supplemental_scores_manifest,
)
from scripts.amiga.import_manifest import (
    build_manifest,
    default_manifest_path,
    write_manifest,
)
from scripts.amiga.player_names import (
    build_canonical_name_map,
    canonical_country,
    identity_key,
    normalize_display_name,
)
from scripts.amiga.tournament_names import (
    resolve_phase,
    resolve_tournament_name,
    scores_only_catalog_aliases,
)
from scripts.amiga.tournament_format import (
    LEGACY_TEMPLATE_SLUG,
    audit_tournament_format_flags,
    infer_legacy_tournament_formats,
    seed_format_templates,
)
from scripts.amiga.tournament_structure.apply import (
    ApplyContext,
    apply_structure_spec,
    structure_specs_manifest,
)
log = logging.getLogger(__name__)

_SQL_TRACK_B = Path(__file__).resolve().parent / "sql" / "002_tournament_standings.sql"
_SQL_KNOCKOUT = Path(__file__).resolve().parent / "sql" / "003_knockout_scope.sql"
_SQL_CATALOG_STATS = Path(__file__).resolve().parent / "sql" / "004_tournament_catalog_stats.sql"
_SQL_FORMATS = Path(__file__).resolve().parent / "sql" / "005_tournament_formats.sql"
_SQL_FIXTURES = Path(__file__).resolve().parent / "sql" / "006_tournament_fixtures.sql"
_SQL_ENTRANTS = Path(__file__).resolve().parent / "sql" / "007_tournament_entrants.sql"
_SQL_LIFECYCLE = Path(__file__).resolve().parent / "sql" / "008_tournament_lifecycle.sql"
_SQL_RATING_EVENTS = Path(__file__).resolve().parent / "sql" / "009_rating_events.sql"
_SQL_PLAYER_PARTICIPATION = (
    Path(__file__).resolve().parent / "sql" / "010_player_tournament_participation.sql"
)
_SQL_PLAYER_TOURNAMENT_TOTALS = (
    Path(__file__).resolve().parent / "sql" / "011_player_tournament_totals.sql"
)
_SQL_PLAYER_MATCHUP_SUMMARY = (
    Path(__file__).resolve().parent / "sql" / "012_player_matchup_summary.sql"
)

_AMIGA_TABLES_DROP_ORDER = (
    "amiga_player_matchup_summary",
    "amiga_player_tournament_totals",
    "amiga_player_tournament_participation",
    "amiga_tournament_catalog_stats",
    "amiga_tournament_standings",
    "amiga_rating_events",
    "amiga_game_ratings",
    "amiga_player_stats",
    "amiga_games",
    "tournament_fixtures",
    "tournament_stage_players",
    "tournament_stages",
    "tournament_entrants",
    "amiga_players",
    "ratedresults",
    "playertable",
    "tournaments",
    "tournament_format_templates",
)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_SQL = Path(__file__).resolve().parent / "sql" / "001_core.sql"


@dataclass(frozen=True)
class AccessScore:
    source_id: int
    team_a: str
    team_b: str
    goals_a: int
    goals_b: int
    raw_tournament: str
    phase: str | None
    extra: str | None


def connect_access(mdb: Path) -> pyodbc.Connection:
    conn_str = f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={mdb.resolve()};"
    return pyodbc.connect(conn_str)


def connect_mysql(cfg) -> pymysql.connections.Connection:
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


def _split_sql(sql: str) -> list[str]:
    sql = "\n".join(ln for ln in sql.splitlines() if ln.strip() and not ln.strip().startswith("--"))
    parts: list[str] = []
    for chunk in sql.split(";"):
        lines = [ln for ln in chunk.splitlines() if ln.strip()]
        if lines:
            parts.append("\n".join(lines))
    return parts


def _is_idempotent_alter_error(exc: pymysql.err.OperationalError) -> bool:
    if not exc.args:
        return False
    code = int(exc.args[0])
    if code in (1060, 1061, 1826):  # duplicate column/key/foreign-key name
        return True
    return code == 1005 and "Duplicate" in str(exc)


def apply_schema(conn: pymysql.connections.Connection, *, drop_existing: bool = False) -> None:
    with conn.cursor() as cur:
        if drop_existing:
            cur.execute("SET FOREIGN_KEY_CHECKS = 0")
            for table in _AMIGA_TABLES_DROP_ORDER:
                cur.execute(f"DROP TABLE IF EXISTS `{table}`")
            cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    for sql_path in (
        _SQL,
        _SQL_TRACK_B,
        _SQL_KNOCKOUT,
        _SQL_CATALOG_STATS,
        _SQL_FORMATS,
        _SQL_FIXTURES,
        _SQL_ENTRANTS,
        _SQL_LIFECYCLE,
        _SQL_RATING_EVENTS,
        _SQL_PLAYER_PARTICIPATION,
        _SQL_PLAYER_TOURNAMENT_TOTALS,
        _SQL_PLAYER_MATCHUP_SUMMARY,
    ):
        sql = sql_path.read_text(encoding="utf-8")
        with conn.cursor() as cur:
            for stmt in _split_sql(sql):
                if stmt.strip().upper().startswith("ALTER TABLE"):
                    try:
                        cur.execute(stmt)
                    except pymysql.err.OperationalError as exc:
                        if not _is_idempotent_alter_error(exc):
                            raise
                else:
                    cur.execute(stmt)
    conn.commit()


def truncate_ground_truth(conn: pymysql.connections.Connection) -> None:
    """Clear ground tables for a full reload.

    Also truncates derived tables first (FK dependency). Import does not write
    derived rows — run ``python -m scripts.amiga replay`` before serving pages.
    """
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        cur.execute("TRUNCATE TABLE amiga_player_matchup_summary")
        cur.execute("TRUNCATE TABLE amiga_player_tournament_totals")
        cur.execute("TRUNCATE TABLE amiga_player_tournament_participation")
        cur.execute("TRUNCATE TABLE amiga_tournament_catalog_stats")
        cur.execute("TRUNCATE TABLE amiga_tournament_standings")
        cur.execute("TRUNCATE TABLE amiga_rating_events")
        cur.execute("TRUNCATE TABLE amiga_game_ratings")
        cur.execute("TRUNCATE TABLE amiga_player_stats")
        cur.execute("TRUNCATE TABLE amiga_games")
        cur.execute("TRUNCATE TABLE tournament_fixtures")
        cur.execute("TRUNCATE TABLE tournament_stage_players")
        cur.execute("TRUNCATE TABLE tournament_stages")
        cur.execute("TRUNCATE TABLE tournament_entrants")
        cur.execute("TRUNCATE TABLE amiga_players")
        cur.execute("TRUNCATE TABLE tournaments")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()


def load_access_tournaments(cur: pyodbc.Cursor) -> list[dict]:
    cur.execute("SELECT * FROM [Tournament players]")
    cols = [d[0] for d in cur.description]
    idx = {name: cols.index(name) for name in cols}
    rows: list[dict] = []
    for row in cur.fetchall():
        rows.append(
            {
                "source_id": int(row[idx["ID"]]),
                "name": str(row[idx["Tournament"]]).strip(),
                "chrono": float(row[idx["Chrono"]]) if row[idx["Chrono"]] is not None else None,
                "event_date": row[idx["Date"]],
                "is_cup": bool(row[idx["Cup?"]]),
                "country": row[idx["Country"]],
                "equal_teams": bool(row[idx["EqualTeams"]]),
                "player_count": row[idx["Players"]],
            }
        )
    return rows


def load_access_scores(cur: pyodbc.Cursor) -> list[AccessScore]:
    cur.execute("SELECT ID, [Team A], [Team B], A, B, Tournament, Phase, Extra FROM Scores")
    out: list[AccessScore] = []
    for row in cur.fetchall():
        extra_raw = row[7]
        out.append(
            AccessScore(
                source_id=int(row[0]),
                team_a=normalize_display_name(str(row[1])),
                team_b=normalize_display_name(str(row[2])),
                goals_a=int(row[3]),
                goals_b=int(row[4]),
                raw_tournament=str(row[5]).strip() if row[5] else "",
                phase=str(row[6]).strip() if row[6] else None,
                extra=str(extra_raw).strip() if extra_raw else None,
            )
        )
    return out


def load_country_by_player(cur: pyodbc.Cursor) -> dict[str, str]:
    cur.execute("SELECT Player, Country FROM Rankings")
    out: dict[str, str] = {}
    for row in cur.fetchall():
        name = normalize_display_name(str(row[0]))
        country = str(row[1]).strip() if row[1] else ""
        if name:
            out[name] = country
    return out


def apply_name_map(scores: list[AccessScore], raw_to_canonical: dict[str, str]) -> None:
    for s in scores:
        object.__setattr__(s, "team_a", raw_to_canonical.get(s.team_a, normalize_display_name(s.team_a)))
        object.__setattr__(s, "team_b", raw_to_canonical.get(s.team_b, normalize_display_name(s.team_b)))


def merge_supplemental_scores(scores: list[AccessScore]) -> list[AccessScore]:
    """Append documented games missing from Access Scores (import_corrections.py)."""
    if not SUPPLEMENTAL_SCORES:
        return scores
    existing_ids = {s.source_id for s in scores}
    next_id = IMPORT_SUPPLEMENT_SCORES_ID_BASE
    out = list(scores)
    for sup in SUPPLEMENTAL_SCORES:
        while next_id in existing_ids:
            next_id += 1
        out.append(
            AccessScore(
                source_id=next_id,
                team_a=sup.team_a,
                team_b=sup.team_b,
                goals_a=sup.goals_a,
                goals_b=sup.goals_b,
                raw_tournament=sup.tournament,
                phase=sup.phase,
                extra=sup.extra,
            )
        )
        existing_ids.add(next_id)
        next_id += 1
    return out


def import_all(*, mdb: Path, recreate_schema: bool) -> dict[str, int]:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing import: expected database ko2amiga_db, got {cfg.database!r}")

    acc = connect_access(mdb)
    acc_cur = acc.cursor()
    tournaments = load_access_tournaments(acc_cur)
    catalog_overrides = apply_catalog_corrections(tournaments)
    skip_catalog = scores_only_catalog_aliases()
    skipped_catalog = sorted(t["name"] for t in tournaments if t["name"] in skip_catalog)
    tournaments = [t for t in tournaments if t["name"] not in skip_catalog]
    if skipped_catalog:
        log.info(
            "Skipped %s Access catalog row(s) merged via tournament_names aliases: %s",
            len(skipped_catalog),
            skipped_catalog,
        )
    if catalog_overrides:
        log.info("Applied %s catalog override(s) from import_corrections.py", len(catalog_overrides))
        for entry in catalog_overrides:
            log.info(
                "  → %s.%s: %s → %s",
                entry["tournament"],
                entry["field"],
                entry["access"],
                entry["canonical"],
            )
    scores = load_access_scores(acc_cur)
    scores = merge_supplemental_scores(scores)
    score_supplements = supplemental_scores_manifest()
    if score_supplements:
        log.info(
            "Appended %s supplemental game(s) from import_corrections.py (%s tournament(s))",
            len(SUPPLEMENTAL_SCORES),
            len(score_supplements),
        )
        for entry in score_supplements:
            log.info("  → %s: +%s games", entry["tournament"], entry["games_added"])
    countries = load_country_by_player(acc_cur)
    raw_player_names: set[str] = set()
    for s in scores:
        raw_player_names.add(s.team_a)
        raw_player_names.add(s.team_b)
    raw_to_canonical, merge_log = build_canonical_name_map(scores, countries=countries)
    apply_name_map(scores, raw_to_canonical)
    if merge_log:
        log.info("Merged %s player identity groups (spacing/case duplicates)", len(merge_log))
        for entry in merge_log:
            log.info("  → %s <= %s", entry["canonical"], entry["variants"])
        merge_path = _REPO / "data" / "amiga" / "exports" / "name_merges.json"
        merge_path.parent.mkdir(parents=True, exist_ok=True)
        merge_path.write_text(json.dumps(merge_log, indent=2) + "\n", encoding="utf-8")
    acc.close()

    mysql = connect_mysql(cfg)
    apply_schema(mysql, drop_existing=recreate_schema)
    truncate_ground_truth(mysql)
    template_ids = seed_format_templates(mysql)
    legacy_template_id = template_ids[LEGACY_TEMPLATE_SLUG]
    format_by_name = infer_legacy_tournament_formats(tournaments, scores)

    tour_by_name = {t["name"]: t for t in tournaments}
    with mysql.cursor() as cur:
        for t in tournaments:
            inferred_format = format_by_name[t["name"]]
            cur.execute(
                """
                INSERT INTO tournaments
                  (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                   format_template_id, has_league, has_cup, lifecycle_status, completed_at)
                VALUES (%(source_id)s, %(name)s, %(chrono)s, %(event_date)s, %(is_cup)s,
                        %(country)s, %(equal_teams)s, %(player_count)s,
                        %(format_template_id)s, %(has_league)s, %(has_cup)s,
                        'completed', %(completed_at)s)
                """,
                {
                    **t,
                    "event_date": t["event_date"].date() if isinstance(t["event_date"], datetime) else t["event_date"],
                    "format_template_id": legacy_template_id,
                    "has_league": inferred_format.has_league,
                    "has_cup": inferred_format.has_cup,
                    "completed_at": (
                        datetime.combine(
                            t["event_date"].date() if isinstance(t["event_date"], datetime) else t["event_date"],
                            datetime.min.time(),
                        )
                        if t.get("event_date") is not None
                        else None
                    ),
                },
            )

    # Resolve tournament ids (after alias mapping).
    with mysql.cursor() as cur:
        cur.execute("SELECT id, name FROM tournaments")
        tour_id_by_name = {row["name"]: int(row["id"]) for row in cur.fetchall()}

    missing_parents: set[str] = set()
    for s in scores:
        parent = resolve_tournament_name(s.raw_tournament)
        if parent and parent not in tour_id_by_name:
            missing_parents.add(parent)
    if missing_parents:
        raise SystemExit(f"Tournament catalog missing parents after alias map: {sorted(missing_parents)}")

    # Player ids — canonical names after merge map.
    names: set[str] = set()
    for s in scores:
        names.add(s.team_a)
        names.add(s.team_b)

    variants_by_key: dict[str, list[str]] = {}
    for raw, canonical in raw_to_canonical.items():
        variants_by_key.setdefault(identity_key(canonical), []).append(raw)

    with mysql.cursor() as cur:
        for name in sorted(names):
            variants = variants_by_key.get(identity_key(name), [name])
            country = canonical_country(name, variants, countries)
            cur.execute(
                "INSERT INTO amiga_players (name, country) VALUES (%s, %s)",
                (name, country),
            )

    with mysql.cursor() as cur:
        cur.execute("SELECT id, name FROM amiga_players")
        player_id = {row["name"]: int(row["id"]) for row in cur.fetchall()}

    # Chronology: event date, chrono (same-day tie-break), source id.
    def sort_key(s: AccessScore) -> tuple:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = tour_by_name.get(parent, {})
        chrono = meta.get("chrono")
        ev = meta.get("event_date")
        ev_date = ev.date() if isinstance(ev, datetime) else (ev or date(1970, 1, 1))
        chrono_val = chrono if chrono is not None else 999999.0
        return (ev_date, chrono_val, s.source_id)

    scores_sorted = sorted(scores, key=sort_key)

    # Synthetic Date: calendar day + 1 second per game across all tournaments on that day.
    per_day_seq: dict[date, int] = {}
    game_rows: list[dict] = []
    for s in scores_sorted:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = tour_by_name[parent]
        ev = meta["event_date"]
        base_day = ev.date() if isinstance(ev, datetime) else ev
        if base_day is None:
            base_day = date(1970, 1, 1)
        seq = per_day_seq.get(base_day, 0)
        per_day_seq[base_day] = seq + 1
        game_dt = datetime.combine(base_day, datetime.min.time()) + timedelta(seconds=seq)
        phase = resolve_phase(s.raw_tournament, s.phase)
        game_rows.append(
            {
                "source_scores_id": s.source_id,
                "game_date": game_dt.strftime("%Y-%m-%d %H:%M:%S"),
                "player_a_id": player_id[s.team_a],
                "player_b_id": player_id[s.team_b],
                "tournament_id": tour_id_by_name[parent],
                "phase": phase,
                "goals_a": s.goals_a,
                "goals_b": s.goals_b,
                "extra": s.extra,
            }
        )

    structure_result = apply_structure_spec(
        mysql,
        ApplyContext(
            tour_id_by_name=tour_id_by_name,
            player_id=player_id,
            tour_by_name=tour_by_name,
            scores=scores_sorted,
        ),
        game_rows,
    )

    with mysql.cursor() as cur:
        cur.executemany(
            """
            INSERT INTO amiga_games
              (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id,
               phase, goals_a, goals_b, extra)
            VALUES
              (%(source_scores_id)s, %(game_date)s, %(player_a_id)s, %(player_b_id)s,
               %(tournament_id)s, %(fixture_id)s, %(phase)s, %(goals_a)s, %(goals_b)s, %(extra)s)
            """,
            [
                {
                    **row,
                    "fixture_id": row.get("fixture_id"),
                }
                for row in game_rows
            ],
        )

    format_audit_failures = audit_tournament_format_flags(mysql)
    if format_audit_failures:
        raise SystemExit(f"Tournaments with games but neither format flag set: {format_audit_failures}")

    mysql.commit()
    format_bucket_counts = {
        "league_only": 0,
        "cup_only": 0,
        "league_and_cup": 0,
        "neither": 0,
    }
    for inferred in format_by_name.values():
        if inferred.has_league and inferred.has_cup:
            format_bucket_counts["league_and_cup"] += 1
        elif inferred.has_league:
            format_bucket_counts["league_only"] += 1
        elif inferred.has_cup:
            format_bucket_counts["cup_only"] += 1
        else:
            format_bucket_counts["neither"] += 1
    stats = {
        "tournaments": len(tournaments),
        "games": len(game_rows),
        "players_raw": len(raw_player_names),
        "players_canonical": len(names),
        "name_merge_groups": len(merge_log),
        **{f"format_{key}": value for key, value in format_bucket_counts.items()},
    }
    manifest = build_manifest(
        mdb=mdb,
        stats=stats,
        name_merges=merge_log,
        catalog_overrides=catalog_overrides,
        score_supplements=score_supplements,
        structure_specs=structure_specs_manifest(structure_result),
    )
    manifest_path = default_manifest_path(_REPO)
    write_manifest(manifest_path, manifest)
    log.info("Wrote import manifest: %s", manifest_path)
    mysql.close()
    log.warning(
        "Import cleared derived tables and reloaded ground truth only. "
        "Run `python -m scripts.amiga replay` (or `python -m scripts.amiga run`) before serving the website."
    )
    return stats
