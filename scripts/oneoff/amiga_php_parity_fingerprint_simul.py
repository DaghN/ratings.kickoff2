from __future__ import annotations

import hashlib
import json
from decimal import Decimal
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

TID = 608
PARTICIPANTS = [382, 14, 149, 417, 441, 134, 418, 470]
PY_DB = "ko2amiga_parity_simul"
PHP_DB = "ko2amiga_parity_php"


def connect(db: str):
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=db, charset="utf8mb4", cursorclass=DictCursor,
    )


def norm(v):
    if isinstance(v, Decimal):
        return float(v)
    if hasattr(v, "isoformat"):
        return v.isoformat()
    return v


def row_sig(row: dict, cols: list[str]) -> tuple:
    return tuple(norm(row.get(c)) for c in cols)


def fetch_all(conn, sql: str, params=()):
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return list(cur.fetchall())


def count(conn, sql: str, params=()):
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return int(cur.fetchone()["n"])


def compare_sets(name, py_rows, php_rows, key_cols, value_cols):
    cols = key_cols + value_cols
    py_map = {row_sig(r, key_cols): row_sig(r, value_cols) for r in py_rows}
    php_map = {row_sig(r, key_cols): row_sig(r, value_cols) for r in php_rows}
    only_py = sorted(set(py_map) - set(php_map))
    only_php = sorted(set(php_map) - set(py_map))
    both = set(py_map) & set(php_map)
    mismatches = []
    for k in sorted(both):
        if py_map[k] != php_map[k]:
            # find which cols differ
            diffs = []
            for i, col in enumerate(value_cols):
                if py_map[k][i] != php_map[k][i]:
                    diffs.append({"col": col, "py": py_map[k][i], "php": php_map[k][i]})
            mismatches.append({"key": k, "diffs": diffs[:12], "diff_count": len(diffs)})
    return {
        "table": name,
        "py_rows": len(py_rows),
        "php_rows": len(php_rows),
        "only_py": len(only_py),
        "only_php": len(only_php),
        "value_mismatches": len(mismatches),
        "sample_mismatches": mismatches[:5],
        "sample_only_py": only_py[:5],
        "sample_only_php": only_php[:5],
        "verdict": (
            "match" if not only_py and not only_php and not mismatches
            else ("count_gap" if only_py or only_php else "numeric_or_content")
        ),
    }


def main():
    py = connect(PY_DB)
    php = connect(PHP_DB)
    report = {"tournament_id": TID, "surfaces": []}

    # tournament meta
    for label, conn in (("py", py), ("php", php)):
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, chrono, rating_finalized, lifecycle_status, is_world_cup, event_date, country "
                "FROM tournaments WHERE id=%s",
                (TID,),
            )
            report.setdefault("tournament", {})[label] = {k: norm(v) for k, v in cur.fetchone().items()}

    # games + ratings for T
    game_sql = """
        SELECT g.id, g.source_scores_id, g.game_date, g.player_a_id, g.player_b_id,
               g.goals_a, g.goals_b, g.fixture_id,
               r.rating_a, r.rating_b, r.adjustment_a, r.adjustment_b, r.actual_score,
               r.expected_score_a, r.winner_id, r.home_win, r.draw, r.away_win
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        WHERE g.tournament_id = %s
        ORDER BY g.id
    """
    py_games = fetch_all(py, game_sql, (TID,))
    php_games = fetch_all(php, game_sql, (TID,))
    # compare by fixture_id (stable) rather than game id if ids match anyway
    report["surfaces"].append(compare_sets(
        "amiga_games+ratings",
        py_games, php_games,
        ["fixture_id", "player_a_id", "player_b_id", "goals_a", "goals_b"],
        ["rating_a", "rating_b", "adjustment_a", "adjustment_b", "actual_score",
         "expected_score_a", "winner_id", "home_win", "draw", "away_win"],
    ))

    # standings
    st_sql = """
        SELECT scope_type, scope_key, player_id, position, games, wins, draws, losses,
               goals_for, goals_against, points, stage_id
        FROM amiga_tournament_standings WHERE tournament_id=%s
        ORDER BY scope_type, scope_key, position, player_id
    """
    report["surfaces"].append(compare_sets(
        "amiga_tournament_standings",
        fetch_all(py, st_sql, (TID,)), fetch_all(php, st_sql, (TID,)),
        ["scope_type", "scope_key", "player_id", "stage_id"],
        ["position", "games", "wins", "draws", "losses", "goals_for", "goals_against", "points"],
    ))

    # snapshots for T
    snap_sql = """
        SELECT * FROM amiga_player_event_snapshots WHERE tournament_id=%s ORDER BY player_id
    """
    py_snap = fetch_all(py, snap_sql, (TID,))
    php_snap = fetch_all(php, snap_sql, (TID,))
    if py_snap and php_snap:
        ignore = {"finalized_at", "created_at", "updated_at"}
        cols = [c for c in py_snap[0].keys() if c not in ignore]
        key = ["player_id", "tournament_id"]
        vals = [c for c in cols if c not in key]
        report["surfaces"].append(compare_sets("amiga_player_event_snapshots", py_snap, php_snap, key, vals))

    # current for participants
    ph = ",".join(["%s"] * len(PARTICIPANTS))
    cur_sql = f"SELECT * FROM amiga_player_current WHERE player_id IN ({ph}) ORDER BY player_id"
    py_cur = fetch_all(py, cur_sql, PARTICIPANTS)
    php_cur = fetch_all(php, cur_sql, PARTICIPANTS)
    if py_cur and php_cur:
        ignore = {"updated_at", "as_of_tournament_id"}  # as_of may differ if chrono path differs? keep and compare
        cols = [c for c in py_cur[0].keys() if c not in {"updated_at"}]
        key = ["player_id"]
        vals = [c for c in cols if c not in key]
        report["surfaces"].append(compare_sets("amiga_player_current(participants)", py_cur, php_cur, key, vals))

    # matchup at event for T
    mu_sql = """
        SELECT player_id, opponent_id, games, wins, draws, losses, goals_for, goals_against,
               performance_rating, as_of_tournament_id
        FROM amiga_player_matchup_at_event WHERE as_of_tournament_id=%s
        ORDER BY player_id, opponent_id
    """
    report["surfaces"].append(compare_sets(
        "amiga_player_matchup_at_event",
        fetch_all(py, mu_sql, (TID,)), fetch_all(php, mu_sql, (TID,)),
        ["player_id", "opponent_id"],
        ["games", "wins", "draws", "losses", "goals_for", "goals_against", "performance_rating"],
    ))

    # slice at event / totals for participants
    sl_sql = """
        SELECT * FROM amiga_player_slice_at_event
        WHERE as_of_tournament_id=%s AND slice_key='world_cup'
        ORDER BY player_id
    """
    py_sl = fetch_all(py, sl_sql, (TID,))
    php_sl = fetch_all(php, sl_sql, (TID,))
    if py_sl or php_sl:
        cols = list(py_sl[0].keys()) if py_sl else list(php_sl[0].keys())
        ignore = {"event_chrono", "event_date"}  # chrono may diverge - report separately
        key = ["player_id", "slice_key", "as_of_tournament_id"]
        vals = [c for c in cols if c not in key and c not in ignore]
        report["surfaces"].append(compare_sets("amiga_player_slice_at_event", py_sl, php_sl, key, vals))
        # chrono/date side channel
        report["slice_chrono"] = {
            "py": sorted({(r["player_id"], norm(r.get("event_chrono")), norm(r.get("event_date"))) for r in py_sl}),
            "php": sorted({(r["player_id"], norm(r.get("event_chrono")), norm(r.get("event_date"))) for r in php_sl}),
        }

    tot_sql = f"""
        SELECT * FROM amiga_player_slice_totals
        WHERE slice_key='world_cup' AND player_id IN ({ph})
        ORDER BY player_id
    """
    py_tot = fetch_all(py, tot_sql, PARTICIPANTS)
    php_tot = fetch_all(php, tot_sql, PARTICIPANTS)
    if py_tot or php_tot:
        cols = list(py_tot[0].keys()) if py_tot else list(php_tot[0].keys())
        key = ["player_id", "slice_key"]
        vals = [c for c in cols if c not in key]
        # assert no as_of in totals
        report["slice_totals_has_as_of"] = {
            "py": "as_of_tournament_id" in (py_tot[0] if py_tot else {}),
            "php": "as_of_tournament_id" in (php_tot[0] if php_tot else {}),
        }
        report["surfaces"].append(compare_sets("amiga_player_slice_totals", py_tot, php_tot, key, vals))

    # country slice
    csl_sql = """
        SELECT * FROM amiga_country_slice_at_event
        WHERE as_of_tournament_id=%s AND slice_key='world_cup'
        ORDER BY country_token
    """
    py_c = fetch_all(py, csl_sql, (TID,))
    php_c = fetch_all(php, csl_sql, (TID,))
    report["country_slice_counts"] = {"py": len(py_c), "php": len(php_c)}
    if py_c or php_c:
        cols = list(py_c[0].keys()) if py_c else list(php_c[0].keys())
        key = ["country_token", "slice_key", "as_of_tournament_id"]
        vals = [c for c in cols if c not in key and c not in {"event_chrono", "event_date"}]
        report["surfaces"].append(compare_sets("amiga_country_slice_at_event", py_c, php_c, key, vals))

    # realm / community / wc hof / world_cup_stats
    for table, where, params in [
        ("amiga_realm_snapshots", "tournament_id=%s", (TID,)),
        ("amiga_community_stats_snapshots", "tournament_id=%s", (TID,)),
        ("amiga_community_stat_facts", "tournament_id=%s", (TID,)),
        ("amiga_world_cup_stats", "tournament_id=%s", (TID,)),
        ("amiga_wc_hof_snapshots", "tournament_id=%s", (TID,)),
        ("amiga_tournament_catalog_stats", "tournament_id=%s", (TID,)),
        ("amiga_player_inverse_count_at_event", "tournament_id=%s", (TID,)),
    ]:
        try:
            py_n = count(py, f"SELECT COUNT(*) AS n FROM {table} WHERE {where}", params)
            php_n = count(php, f"SELECT COUNT(*) AS n FROM {table} WHERE {where}", params)
        except Exception as exc:  # noqa: BLE001
            report["surfaces"].append({"table": table, "verdict": "error", "error": str(exc)})
            continue
        entry = {"table": table, "py_rows": py_n, "php_rows": php_n, "verdict": "match" if py_n == php_n else "count_gap"}
        if py_n and php_n and py_n <= 200:
            py_rows = fetch_all(py, f"SELECT * FROM {table} WHERE {where}", params)
            php_rows = fetch_all(php, f"SELECT * FROM {table} WHERE {where}", params)
            def h(rows):
                blob = json.dumps(rows, sort_keys=True, default=str).encode()
                return hashlib.sha256(blob).hexdigest()[:16]
            entry["py_hash"] = h(py_rows)
            entry["php_hash"] = h(php_rows)
            entry["verdict"] = "match" if entry["py_hash"] == entry["php_hash"] else "content_hash_mismatch"
        report["surfaces"].append(entry)

    # wc hof present + community present + generalstats single row hashes
    for table in ["amiga_wc_hof_present", "amiga_community_stats", "amiga_generalstats"]:
        py_rows = fetch_all(py, f"SELECT * FROM {table}")
        php_rows = fetch_all(php, f"SELECT * FROM {table}")
        def h(rows):
            return hashlib.sha256(json.dumps(rows, sort_keys=True, default=str).encode()).hexdigest()[:16]
        report["surfaces"].append({
            "table": table,
            "py_rows": len(py_rows),
            "php_rows": len(php_rows),
            "py_hash": h(py_rows),
            "php_hash": h(php_rows),
            "verdict": "match" if h(py_rows) == h(php_rows) else "content_hash_mismatch",
        })

    out = Path("data/amiga/parity/fingerprint-608-simul-vs-php.json")
    out.write_text(json.dumps(report, indent=2, default=str) + "\n", encoding="utf-8")
    # summary print
    print("TOURNAMENT", json.dumps(report["tournament"], indent=2))
    print("\nSURFACES:")
    for s in report["surfaces"]:
        v = s.get("verdict")
        mark = "OK" if v == "match" else "GAP"
        extra = ""
        if s.get("value_mismatches"):
            extra = f" mismatches={s['value_mismatches']}"
        if s.get("only_py") or s.get("only_php"):
            extra += f" only_py={s.get('only_py')} only_php={s.get('only_php')}"
        print(f"  [{mark}] {s['table']}: py={s.get('py_rows')} php={s.get('php_rows')} {v}{extra}")
    print(f"\nwrote {out}")


if __name__ == "__main__":
    main()