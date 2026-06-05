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
from scripts.amiga.player_names import (
    build_canonical_name_map,
    canonical_country,
    identity_key,
    normalize_display_name,
)
from scripts.amiga.tournament_names import resolve_phase, resolve_tournament_name
from scripts.ladder.constants import START_RATING

log = logging.getLogger(__name__)

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
    parts: list[str] = []
    for chunk in sql.split(";"):
        lines = [ln for ln in chunk.splitlines() if ln.strip() and not ln.strip().startswith("--")]
        if lines:
            parts.append("\n".join(lines))
    return parts


def apply_schema(conn: pymysql.connections.Connection, *, drop_existing: bool = False) -> None:
    with conn.cursor() as cur:
        if drop_existing:
            cur.execute("SET FOREIGN_KEY_CHECKS = 0")
            for table in ("ratedresults", "playertable", "tournaments"):
                cur.execute(f"DROP TABLE IF EXISTS `{table}`")
            cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    sql = _SQL.read_text(encoding="utf-8")
    with conn.cursor() as cur:
        for stmt in _split_sql(sql):
            cur.execute(stmt)
    conn.commit()


def truncate_ground_truth(conn: pymysql.connections.Connection) -> None:
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        cur.execute("TRUNCATE TABLE ratedresults")
        cur.execute("TRUNCATE TABLE playertable")
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
        out.append(
            AccessScore(
                source_id=int(row[0]),
                team_a=normalize_display_name(str(row[1])),
                team_b=normalize_display_name(str(row[2])),
                goals_a=int(row[3]),
                goals_b=int(row[4]),
                raw_tournament=str(row[5]).strip() if row[5] else "",
                phase=str(row[6]).strip() if row[6] else None,
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


def import_all(*, mdb: Path, recreate_schema: bool) -> dict[str, int]:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing import: expected database ko2amiga_db, got {cfg.database!r}")

    acc = connect_access(mdb)
    acc_cur = acc.cursor()
    tournaments = load_access_tournaments(acc_cur)
    scores = load_access_scores(acc_cur)
    countries = load_country_by_player(acc_cur)
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
    if recreate_schema:
        apply_schema(mysql, drop_existing=True)
    truncate_ground_truth(mysql)

    tour_by_name = {t["name"]: t for t in tournaments}
    with mysql.cursor() as cur:
        for t in tournaments:
            cur.execute(
                """
                INSERT INTO tournaments
                  (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count)
                VALUES (%(source_id)s, %(name)s, %(chrono)s, %(event_date)s, %(is_cup)s,
                        %(country)s, %(equal_teams)s, %(player_count)s)
                """,
                {
                    **t,
                    "event_date": t["event_date"].date() if isinstance(t["event_date"], datetime) else t["event_date"],
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
                "INSERT INTO playertable (Name, Country, Rating) VALUES (%s, %s, %s)",
                (name, country, START_RATING),
            )

    with mysql.cursor() as cur:
        cur.execute("SELECT ID, Name FROM playertable")
        player_id = {row["Name"]: int(row["ID"]) for row in cur.fetchall()}

    # Chronology: chrono, event date, source id.
    def sort_key(s: AccessScore) -> tuple:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = tour_by_name.get(parent, {})
        chrono = meta.get("chrono")
        ev = meta.get("event_date")
        ev_date = ev.date() if isinstance(ev, datetime) else (ev or date(1970, 1, 1))
        return (chrono if chrono is not None else 999999.0, ev_date, s.source_id)

    scores_sorted = sorted(scores, key=sort_key)

    # Synthetic Date: tournament day + 1 second per game within tournament (ordered by source id).
    per_tournament_seq: dict[str, int] = {}
    game_rows: list[dict] = []
    for s in scores_sorted:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = tour_by_name[parent]
        ev = meta["event_date"]
        base_day = ev.date() if isinstance(ev, datetime) else ev
        if base_day is None:
            base_day = date(1970, 1, 1)
        seq = per_tournament_seq.get(parent, 0)
        per_tournament_seq[parent] = seq + 1
        game_dt = datetime.combine(base_day, datetime.min.time()) + timedelta(seconds=seq)
        phase = resolve_phase(s.raw_tournament, s.phase)
        game_rows.append(
            {
                "source_scores_id": s.source_id,
                "Date": game_dt.strftime("%Y-%m-%d %H:%M:%S"),
                "idA": player_id[s.team_a],
                "NameA": s.team_a,
                "idB": player_id[s.team_b],
                "NameB": s.team_b,
                "tournament_id": tour_id_by_name[parent],
                "phase": phase,
                "GoalsA": s.goals_a,
                "GoalsB": s.goals_b,
            }
        )

    with mysql.cursor() as cur:
        cur.executemany(
            """
            INSERT INTO ratedresults
              (source_scores_id, Date, idA, NameA, idB, NameB, tournament_id, phase, GoalsA, GoalsB)
            VALUES
              (%(source_scores_id)s, %(Date)s, %(idA)s, %(NameA)s, %(idB)s, %(NameB)s,
               %(tournament_id)s, %(phase)s, %(GoalsA)s, %(GoalsB)s)
            """,
            game_rows,
        )

    mysql.commit()
    stats = {
        "tournaments": len(tournaments),
        "players": len(names),
        "games": len(game_rows),
        "name_merge_groups": len(merge_log),
    }
    mysql.close()
    return stats
