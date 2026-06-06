"""Compare derived standings to Access reference tables (parity only)."""

from __future__ import annotations

import argparse
import logging
import re
from pathlib import Path

import pymysql
import pyodbc
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_phases import access_group_label_for_parity

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"

def _connect_mysql():
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _connect_access(mdb: Path) -> pyodbc.Connection:
    conn_str = f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={mdb.resolve()};"
    return pyodbc.connect(conn_str)


def _wc_table_name(tournament_name: str) -> str | None:
    m = re.match(r"^World Cup\s+(\S+)$", tournament_name.strip())
    if not m:
        return None
    return f"World Cup {m.group(1)} Tables"


def load_access_overall(cur: pyodbc.Cursor, tournament_name: str) -> list[dict]:
    cur.execute(
        "SELECT Player, G, W, D, L, GS, GC, Pts, P "
        "FROM [Tables] WHERE Tournament = ? ORDER BY Pts DESC, (GS-GC) DESC, GS DESC",
        (tournament_name,),
    )
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, row)) for row in cur.fetchall()]


def load_access_group(
    cur: pyodbc.Cursor, tournament_name: str, scope_key: str
) -> list[dict]:
    wc_table = _wc_table_name(tournament_name)
    if not wc_table:
        raise SystemExit(f"No Access World Cup table mapping for {tournament_name!r}")
    group_label = access_group_label_for_parity(scope_key)
    cur.execute(
        f"SELECT Player, G, W, D, L, GS, GC, Pts, P, Tournament "
        f"FROM [{wc_table}] WHERE Tournament = ? ORDER BY Pts DESC, (GS-GC) DESC",
        (group_label,),
    )
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, row)) for row in cur.fetchall()]


def load_derived(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    scope_type: str,
    scope_key: str,
) -> list[dict]:
    sql = """
        SELECT p.name AS Player, s.games AS G, s.wins AS W, s.draws AS D, s.losses AS L,
               s.goals_for AS GS, s.goals_against AS GC, s.points AS Pts, s.position AS P
        FROM amiga_tournament_standings s
        INNER JOIN amiga_players p ON p.id = s.player_id
        WHERE s.tournament_id = %s AND s.scope_type = %s AND s.scope_key = %s
        ORDER BY s.points DESC, (s.goals_for - s.goals_against) DESC, s.goals_for DESC
    """
    with conn.cursor() as cur:
        cur.execute(sql, (tournament_id, scope_type, scope_key))
        return list(cur.fetchall())


def _row_match(ref: dict, der: dict, *, tol: int = 0) -> list[str]:
    errs: list[str] = []
    for col in ("G", "W", "D", "L", "GS", "GC", "Pts"):
        rv = int(ref[col])
        dv = int(der[col])
        if abs(rv - dv) > tol:
            errs.append(f"{col}: ref={rv} derived={dv}")
    return errs


def compare_tables(ref: list[dict], der: list[dict], *, top_n: int = 10) -> tuple[bool, str]:
    ref_by = {str(r["Player"]).strip(): r for r in ref}
    der_by = {str(r["Player"]).strip(): r for r in der}
    lines: list[str] = []
    ok = True
    ref_top = ref[:top_n]
    for r in ref_top:
        name = str(r["Player"]).strip()
        d = der_by.get(name)
        if not d:
            lines.append(f"MISSING derived row: {name} (ref Pts={r['Pts']})")
            ok = False
            continue
        errs = _row_match(r, d)
        if errs:
            lines.append(f"MISMATCH {name}: " + "; ".join(errs))
            ok = False
        else:
            lines.append(f"OK {name}: Pts={r['Pts']} P_ref={r['P']} P_der={d['P']}")
    extra = set(der_by) - set(ref_by)
    if extra:
        lines.append(f"Extra derived players not in reference: {sorted(extra)[:5]}")
    lines.append(f"Reference rows={len(ref)} derived rows={len(der)}")
    return ok, "\n".join(lines)


def run_parity(
    *,
    tournament: str,
    scope: str = "overall",
    scope_key: str = "",
    mdb: Path = _DEFAULT_MDB,
    top_n: int = 10,
) -> int:
    mysql = _connect_mysql()
    with mysql.cursor() as cur:
        cur.execute("SELECT id FROM tournaments WHERE name = %s", (tournament,))
        row = cur.fetchone()
        if not row:
            raise SystemExit(f"Tournament not found in MySQL: {tournament!r}")
        tournament_id = int(row["id"])

    if scope == "overall":
        scope_type = "overall"
        scope_key = ""
    elif scope == "group":
        scope_type = "group"
        if not scope_key:
            scope_key = "Round 1 - Group A"
    else:
        raise SystemExit(f"Unknown scope: {scope!r}")

    derived = load_derived(mysql, tournament_id, scope_type=scope_type, scope_key=scope_key)
    mysql.close()

    acc = _connect_access(mdb)
    cur = acc.cursor()
    if scope == "overall":
        reference = load_access_overall(cur, tournament)
    else:
        reference = load_access_group(cur, tournament, scope_key)
    acc.close()

    ok, report = compare_tables(reference, derived, top_n=top_n)
    print(report)
    return 0 if ok else 1


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Parity: derived vs Access standings")
    parser.add_argument("--tournament", required=True)
    parser.add_argument("--scope", choices=("overall", "group"), default="overall")
    parser.add_argument("--scope-key", default="", help="e.g. 'Round 1 - Group A'")
    parser.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    parser.add_argument("--top", type=int, default=10)
    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    return run_parity(
        tournament=args.tournament,
        scope=args.scope,
        scope_key=args.scope_key,
        mdb=args.mdb,
        top_n=args.top,
    )


if __name__ == "__main__":
    raise SystemExit(main())
