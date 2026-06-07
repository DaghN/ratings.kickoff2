"""Compare derived standings to Access reference tables (parity only)."""

from __future__ import annotations

import argparse
import json
import logging
import re
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pymysql
import pyodbc
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_names import normalize_display_name
from scripts.amiga.tournament_names import TOURNAMENT_ALIASES
from scripts.amiga.tournament_phases import access_group_label_for_parity
from scripts.amiga.tournament_standings import compute_tournament_standings

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_DEFAULT_REPORT = _REPO / "data" / "amiga" / "exports" / "standings_parity_report.json"

_STAT_COLS = ("G", "W", "D", "L", "GS", "GC", "Pts")


def _connect_mysql() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
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


def _player_key(name: str) -> str:
    return normalize_display_name(str(name))


def _index_by_player(rows: list[dict]) -> dict[str, dict]:
    return {_player_key(r["Player"]): r for r in rows}


def load_access_overall(cur: pyodbc.Cursor, tournament_name: str) -> list[dict]:
    cur.execute(
        "SELECT Player, G, W, D, L, GS, GC, Pts, P "
        "FROM [Tables] WHERE Tournament = ? ORDER BY Pts DESC, (GS-GC) DESC, GS DESC",
        (tournament_name,),
    )
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, row)) for row in cur.fetchall()]


def load_access_group(
    cur: pyodbc.Cursor,
    tournament_name: str,
    scope_key: str,
    *,
    access_tables: set[str] | None = None,
) -> list[dict]:
    wc_table = _wc_table_name(tournament_name)
    if not wc_table:
        return []
    if access_tables is not None and wc_table not in access_tables:
        return []
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


def _row_match(ref: dict, der: dict) -> list[str]:
    errs: list[str] = []
    for col in _STAT_COLS:
        rv = ref.get(col)
        dv = der.get(col)
        if rv is None or dv is None:
            errs.append(f"{col}: missing")
            continue
        if int(rv) != int(dv):
            errs.append(f"{col}: ref={int(rv)} derived={int(dv)}")
    return errs


def compare_tables(
    ref: list[dict],
    der: list[dict],
    *,
    top_n: int | None = None,
) -> tuple[bool, str, list[str]]:
    """Compare reference vs derived. When top_n is None, compare all reference rows."""
    ref_by = _index_by_player(ref)
    der_by = _index_by_player(der)
    lines: list[str] = []
    mismatches: list[str] = []
    ok = True

    if not ref and der:
        ok = False
        lines.append(f"No reference rows but derived has {len(der)} players")
        return ok, "\n".join(lines), mismatches

    ref_items = ref[:top_n] if top_n is not None else ref
    for r in ref_items:
        name = _player_key(r["Player"])
        d = der_by.get(name)
        if not d:
            msg = f"MISSING derived row: {name} (ref Pts={r['Pts']})"
            lines.append(msg)
            mismatches.append(msg)
            ok = False
            continue
        errs = _row_match(r, d)
        if errs:
            msg = f"MISMATCH {name}: " + "; ".join(errs)
            lines.append(msg)
            mismatches.append(msg)
            ok = False
        else:
            lines.append(f"OK {name}: Pts={r['Pts']} P_ref={r.get('P')} P_der={d.get('P')}")

    extra = set(der_by) - set(ref_by)
    if extra:
        msg = f"Extra derived players not in reference: {sorted(extra)[:8]}"
        lines.append(msg)
        if top_n is None:
            mismatches.append(msg)
            ok = False

    lines.append(f"Reference rows={len(ref)} derived rows={len(der)}")
    return ok, "\n".join(lines), mismatches


def _agg_from_score_rows(rows: list[tuple]) -> dict[str, dict[str, int]]:
    players: dict[str, dict[str, int]] = {}
    for ta, tb, a, b in rows:
        a, b = int(a), int(b)
        for name, gf, ga in ((ta, a, b), (tb, b, a)):
            key = _player_key(name)
            if key not in players:
                players[key] = {"G": 0, "W": 0, "D": 0, "L": 0, "GS": 0, "GC": 0}
            p = players[key]
            p["G"] += 1
            p["GS"] += gf
            p["GC"] += ga
            if gf > ga:
                p["W"] += 1
            elif gf < ga:
                p["L"] += 1
            else:
                p["D"] += 1
    for p in players.values():
        p["Pts"] = p["W"] * 3 + p["D"]
    return players


def load_engine_scope_stats(
    mysql_cur: DictCursor,
    tournament_id: int,
    *,
    scope_type: str,
    scope_key: str,
) -> dict[str, dict[str, int]]:
    """Recompute one scope via ``compute_tournament_standings`` (engine oracle)."""
    mysql_cur.execute(
        "SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id, "
        "g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id "
        "FROM amiga_games g WHERE g.tournament_id = %s "
        "ORDER BY g.source_scores_id ASC, g.id ASC",
        (tournament_id,),
    )
    games = mysql_cur.fetchall()
    if not games:
        return {}
    mysql_cur.execute(
        "SELECT id, name FROM amiga_players WHERE id IN (%s)"
        % ",".join(
            str(pid)
            for pid in {
                int(g["player_a_id"])
                for g in games
            }
            | {int(g["player_b_id"]) for g in games}
        )
    )
    id_to_name = {int(r["id"]): r["name"] for r in mysql_cur.fetchall()}
    rows = compute_tournament_standings(games)
    out: dict[str, dict[str, int]] = {}
    for row in rows:
        if row["scope_type"] != scope_type or row["scope_key"] != scope_key:
            continue
        pname = id_to_name.get(int(row["player_id"]), str(row["player_id"]))
        key = _player_key(pname)
        out[key] = {
            "G": int(row["games"]),
            "W": int(row["wins"]),
            "D": int(row["draws"]),
            "L": int(row["losses"]),
            "GS": int(row["goals_for"]),
            "GC": int(row["goals_against"]),
            "Pts": int(row["points"]),
        }
    return out


def load_null_phase_stats(mysql_cur: DictCursor, tournament_id: int) -> dict[str, dict[str, int]]:
    """Null-phase games only — for mixed-tournament overall classification."""
    mysql_cur.execute(
        "SELECT pa.name AS a, pb.name AS b, g.goals_a AS ga, g.goals_b AS gb "
        "FROM amiga_games g "
        "JOIN amiga_players pa ON pa.id = g.player_a_id "
        "JOIN amiga_players pb ON pb.id = g.player_b_id "
        "WHERE g.tournament_id = %s AND g.phase IS NULL",
        (tournament_id,),
    )
    return _agg_from_score_rows(
        [(r["a"], r["b"], r["ga"], r["gb"]) for r in mysql_cur.fetchall()]
    )


def _stats_match(a: dict[str, int], b: dict[str, int]) -> bool:
    return all(int(a.get(c, -1)) == int(b.get(c, -2)) for c in _STAT_COLS)


def _derived_stats_by_player(der: list[dict]) -> dict[str, dict[str, int]]:
    out: dict[str, dict[str, int]] = {}
    for r in der:
        key = _player_key(r["Player"])
        out[key] = {col: int(r[col]) for col in _STAT_COLS}
    return out


def classify_mismatch(
    *,
    tournament_name: str,
    scope_type: str,
    scope_key: str,
    ref: list[dict],
    der: list[dict],
    acc_cur: pyodbc.Cursor,
    mysql_cur: DictCursor,
    tournament_id: int,
) -> str:
    """Classify a reference mismatch (derived ≠ Access Tables)."""
    der_stats = _derived_stats_by_player(der)
    engine_stats = load_engine_scope_stats(
        mysql_cur,
        tournament_id,
        scope_type=scope_type,
        scope_key=scope_key,
    )

    if scope_type == "overall":
        mysql_cur.execute(
            "SELECT SUM(phase IS NULL) AS n_null, SUM(phase IS NOT NULL) AS n_struct "
            "FROM amiga_games WHERE tournament_id = %s",
            (tournament_id,),
        )
        phase_mix = mysql_cur.fetchone() or {}
        if int(phase_mix.get("n_null") or 0) > 0 and int(phase_mix.get("n_struct") or 0) > 0:
            null_stats = load_null_phase_stats(mysql_cur, tournament_id)
            if der_stats == null_stats:
                return "mixed_overall_league_only"

    if der_stats == engine_stats:
        aliases = [k for k, v in TOURNAMENT_ALIASES.items() if v == tournament_name]
        if aliases:
            return "ref_alias_merge"
        return "ref_stale_tables"

    # Player merge at import may split/combine vs raw Access Tables names.
    ref_stats = {
        _player_key(r["Player"]): {col: int(r[col]) for col in _STAT_COLS} for r in ref
    }
    if der_stats == ref_stats:
        return "pass"

    return "engine_bug"


@dataclass
class ScopeResult:
    tournament_id: int
    tournament_name: str
    scope_type: str
    scope_key: str
    status: str  # pass | skip | fail | exception
    reason: str = ""
    mismatches: list[str] = field(default_factory=list)


def compare_scope(
    *,
    tournament_id: int,
    tournament_name: str,
    scope_type: str,
    scope_key: str,
    mysql: pymysql.connections.Connection,
    acc_cur: pyodbc.Cursor,
    mysql_cur: DictCursor,
    access_tables: set[str],
    classify: bool = True,
) -> ScopeResult:
    derived = load_derived(mysql, tournament_id, scope_type=scope_type, scope_key=scope_key)
    if not derived:
        return ScopeResult(
            tournament_id, tournament_name, scope_type, scope_key, "skip", "no_derived"
        )

    if scope_type == "overall":
        reference = load_access_overall(acc_cur, tournament_name)
    else:
        reference = load_access_group(
            acc_cur, tournament_name, scope_key, access_tables=access_tables
        )

    if not reference:
        return ScopeResult(
            tournament_id, tournament_name, scope_type, scope_key, "skip", "no_reference"
        )

    ok, _, mismatches = compare_tables(reference, derived, top_n=None)
    if ok:
        return ScopeResult(tournament_id, tournament_name, scope_type, scope_key, "pass")

    if not classify:
        return ScopeResult(
            tournament_id,
            tournament_name,
            scope_type,
            scope_key,
            "fail",
            mismatches=mismatches,
        )

    reason = classify_mismatch(
        tournament_name=tournament_name,
        scope_type=scope_type,
        scope_key=scope_key,
        ref=reference,
        der=derived,
        acc_cur=acc_cur,
        mysql_cur=mysql_cur,
        tournament_id=tournament_id,
    )
    if reason == "engine_bug":
        return ScopeResult(
            tournament_id,
            tournament_name,
            scope_type,
            scope_key,
            "fail",
            reason=reason,
            mismatches=mismatches,
        )
    return ScopeResult(
        tournament_id,
        tournament_name,
        scope_type,
        scope_key,
        "exception",
        reason=reason,
        mismatches=mismatches[:3],
    )


def _list_reference_tournaments(acc_cur: pyodbc.Cursor) -> set[str]:
    acc_cur.execute("SELECT Tournament FROM [Tables]")
    return {str(r[0]).strip() for r in acc_cur.fetchall()}


def _access_table_names(acc_cur: pyodbc.Cursor) -> set[str]:
    return {t.table_name for t in acc_cur.tables(tableType="TABLE")}


def run_sweep(
    *,
    mdb: Path = _DEFAULT_MDB,
    report_path: Path = _DEFAULT_REPORT,
    tournament_id: int | None = None,
    fail_fast: bool = False,
    only_failures: bool = False,
) -> int:
    mysql = _connect_mysql()
    acc = _connect_access(mdb)
    acc_cur = acc.cursor()
    access_tables = _access_table_names(acc_cur)
    ref_tournaments = _list_reference_tournaments(acc_cur)

    with mysql.cursor() as mysql_cur:
        if tournament_id is not None:
            mysql_cur.execute(
                "SELECT id, name FROM tournaments WHERE id = %s", (tournament_id,)
            )
        else:
            mysql_cur.execute("SELECT id, name FROM tournaments ORDER BY id")
        tournaments = list(mysql_cur.fetchall())

    results: list[ScopeResult] = []
    for t in tournaments:
        tid, tname = int(t["id"]), str(t["name"])
        if tname not in ref_tournaments and not _wc_table_name(tname):
            continue

        scopes: list[tuple[str, str]] = [("overall", "")]
        if _wc_table_name(tname) and f"{_wc_table_name(tname)}" in access_tables:
            with mysql.cursor() as c2:
                c2.execute(
                    "SELECT DISTINCT scope_key FROM amiga_tournament_standings "
                    "WHERE tournament_id = %s AND scope_type = 'group' ORDER BY scope_key",
                    (tid,),
                )
                for row in c2.fetchall():
                    scopes.append(("group", str(row["scope_key"])))

        with mysql.cursor() as mysql_cur:
            for scope_type, scope_key in scopes:
                if scope_type == "overall" and tname not in ref_tournaments:
                    continue
                res = compare_scope(
                    tournament_id=tid,
                    tournament_name=tname,
                    scope_type=scope_type,
                    scope_key=scope_key,
                    mysql=mysql,
                    acc_cur=acc_cur,
                    mysql_cur=mysql_cur,
                    access_tables=access_tables,
                )
                results.append(res)

                if res.status == "fail" and fail_fast:
                    mysql.close()
                    acc.close()
                    _write_report(results, report_path, mdb)
                    print(f"FAIL-FAST: {tname} {scope_type} {scope_key!r}: {res.mismatches[:2]}")
                    return 1

    mysql.close()
    acc.close()

    summary = _summarize(results)
    _write_report(results, report_path, mdb, summary=summary)
    _print_sweep_summary(summary, results, only_failures=only_failures)
    return 0 if summary["fail"] == 0 else 1


def _summarize(results: list[ScopeResult]) -> dict[str, int]:
    counts: dict[str, int] = {"pass": 0, "skip": 0, "fail": 0, "exception": 0}
    for r in results:
        counts[r.status] = counts.get(r.status, 0) + 1
    return counts


def _write_report(
    results: list[ScopeResult],
    path: Path,
    mdb: Path,
    *,
    summary: dict[str, int] | None = None,
) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload: dict[str, Any] = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "mdb": str(mdb.resolve()),
        "summary": summary or _summarize(results),
        "results": [asdict(r) for r in results],
    }
    path.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    log.info("Wrote parity report: %s", path)


def _print_sweep_summary(
    summary: dict[str, int],
    results: list[ScopeResult],
    *,
    only_failures: bool,
) -> None:
    print(
        f"Sweep: PASS={summary['pass']} SKIP={summary['skip']} "
        f"EXCEPTION={summary['exception']} FAIL={summary['fail']}"
    )
    for r in results:
        if only_failures and r.status not in ("fail", "exception"):
            continue
        if r.status == "pass":
            continue
        label = f"{r.tournament_name}"
        if r.scope_type == "group":
            label += f" [{r.scope_key}]"
        if r.status == "exception":
            print(f"  EXCEPTION ({r.reason}): {label}")
        elif r.status == "fail":
            print(f"  FAIL: {label} — {r.mismatches[:2]}")
        elif not only_failures and r.status == "skip":
            print(f"  SKIP ({r.reason}): {label}")


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

    acc = _connect_access(mdb)
    cur = acc.cursor()
    if scope == "overall":
        reference = load_access_overall(cur, tournament)
    else:
        reference = load_access_group(cur, tournament, scope_key)
    acc.close()
    mysql.close()

    ok, report, _ = compare_tables(reference, derived, top_n=top_n)
    print(report)
    return 0 if ok else 1


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Parity: derived vs Access standings")
    parser.add_argument("--tournament", help="Single-tournament spot check")
    parser.add_argument("--scope", choices=("overall", "group"), default="overall")
    parser.add_argument("--scope-key", default="", help="e.g. 'Round 1 - Group A'")
    parser.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    parser.add_argument("--top", type=int, default=10)
    parser.add_argument(
        "--sweep",
        action="store_true",
        help="Sweep all tournaments with Access reference standings",
    )
    parser.add_argument("--tournament-id", type=int, default=None, help="Limit sweep to one id")
    parser.add_argument("--fail-fast", action="store_true")
    parser.add_argument("--only-failures", action="store_true")
    parser.add_argument(
        "--report",
        type=Path,
        default=_DEFAULT_REPORT,
        help="JSON report path for --sweep",
    )
    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")

    if args.sweep:
        return run_sweep(
            mdb=args.mdb,
            report_path=args.report,
            tournament_id=args.tournament_id,
            fail_fast=args.fail_fast,
            only_failures=args.only_failures,
        )

    if not args.tournament:
        parser.error("--tournament is required unless --sweep is set")

    return run_parity(
        tournament=args.tournament,
        scope=args.scope,
        scope_key=args.scope_key,
        mdb=args.mdb,
        top_n=args.top,
    )


if __name__ == "__main__":
    raise SystemExit(main())
