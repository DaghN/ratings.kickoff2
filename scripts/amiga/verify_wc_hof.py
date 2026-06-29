#!/usr/bin/env python3
"""Verify the World Cup Hall of Fame store against independent oracles (WCH-4).

Read-only. Layered oracle (no import of ``wc_hof`` compute internals):

  1. Snapshot count == finalized World Cups; ``amiga_wc_hof_present`` id=1 equals the
     latest snapshot payload by chrono.
  2. Per snapshot (every WC cutoff): HoF holder selection is re-derived
       - cumulative / ratio / single-WC-peak holders by argmax/argmin over the stored
         WC slice cutoff rows (value + id), with the 20-game ratio gate;
       - single-game rows from raw ``amiga_games`` goals (value + holder id(s) + GameID);
       - every ``{Prefix}Date`` re-derived independently (rise timeline / game / tournament).
  3. Awards + single-WC peaks slice columns (NOT covered by verify-player-slice) are
     re-tallied from ``amiga_games`` per-WC leaders through the full catalogue and
     compared to ``amiga_player_slice_totals``; the present award/peak holders are
     checked against that recompute.

Exit 0 = OK, 1 = at least one mismatch.
"""

from __future__ import annotations

import sys
from decimal import ROUND_HALF_UP, Decimal
from typing import Any, Callable

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

_WC_NAME_RE = r"^World Cup[[:space:]]+[^[:space:]]"
_MIN_GAMES = 20
_TOL = 5e-5

# Cumulative "Most ..." HoF rows -> WC slice column.
_CUMULATIVE: tuple[tuple[str, str], ...] = (
    ("MostWcPlayed", "tournaments_played"),
    ("MostWcGold", "gold"),
    ("MostWcGames", "games"),
    ("MostWcWins", "wins"),
    ("MostWcPoints", "points"),
    ("MostWcGoalsFor", "goals_for"),
    ("MostWcDoubleDigits", "double_digits"),
    ("MostWcCleanSheets", "clean_sheets"),
    ("MostWcOpponents", "different_opponents"),
    ("MostWcVictims", "different_victims"),
    ("MostWcDoubleDigitsVictims", "double_digits_victims"),
    ("MostWcCleanSheetsVictims", "clean_sheets_victims"),
    ("MostWcBestAttackAwards", "best_attack_awards"),
    ("MostWcBestDefenseAwards", "best_defense_awards"),
)


def _num(v: Any) -> float:
    return 0.0 if v is None else float(v)


def _q4(v: float | None) -> Decimal | None:
    if v is None:
        return None
    return Decimal(str(v)).quantize(Decimal("0.0001"), rounding=ROUND_HALF_UP)


def _ratio_pts(r): g = _num(r["games"]); return None if g <= 0 else _num(r["points"]) / g
def _ratio_win(r): g = _num(r["games"]); return None if g <= 0 else (_num(r["wins"]) + 0.5 * _num(r["draws"])) / g
def _ratio_gf(r): g = _num(r["games"]); return None if g <= 0 else _num(r["goals_for"]) / g
def _ratio_ga(r): g = _num(r["games"]); return None if g <= 0 else _num(r["goals_against"]) / g
def _ratio_gd(r): g = _num(r["games"]); return None if g <= 0 else (_num(r["goals_for"]) - _num(r["goals_against"])) / g
def _ratio_goalratio(r): v = r.get("goal_ratio"); return None if v is None else float(v)
def _ratio_dd(r): v = r.get("double_digits_ratio"); return None if v is None else float(v)
def _ratio_cs(r): v = r.get("clean_sheets_ratio"); return None if v is None else float(v)

# Ratio HoF rows -> (slice metric fn, higher_better).
_RATIO: tuple[tuple[str, Callable[[dict], float | None], bool], ...] = (
    ("BestWcPtsPerGame", _ratio_pts, True),
    ("BestWcWinRate", _ratio_win, True),
    ("BestWcGoalsForPerGame", _ratio_gf, True),
    ("BestWcGoalsAgainstPerGame", _ratio_ga, False),
    ("BestWcGoalDiffPerGame", _ratio_gd, True),
    ("BestWcGoalRatio", _ratio_goalratio, True),
    ("BestWcDoubleDigitsRatio", _ratio_dd, True),
    ("BestWcCleanSheetsRatio", _ratio_cs, True),
)


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", cursorclass=DictCursor, autocommit=True,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _finalized_wcs(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, event_date, chrono, name
            FROM tournaments
            WHERE rating_finalized = 1 AND name REGEXP %s
            ORDER BY event_date ASC, chrono ASC, id ASC
            """,
            (_WC_NAME_RE,),
        )
        return list(cur.fetchall())


def _cutoff_slice_rows(conn, ev_date, chrono, tid) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT x.*, p.name AS player_name FROM (
                SELECT s.*, ROW_NUMBER() OVER (
                    PARTITION BY s.player_id
                    ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                ) rn
                FROM amiga_player_slice_at_event s
                WHERE s.slice_key = 'world_cup'
                  AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (%s, %s, %s)
            ) x
            INNER JOIN amiga_players p ON p.id = x.player_id
            WHERE x.rn = 1
            """,
            (ev_date, chrono, tid),
        )
        return list(cur.fetchall())


def _player_timeline(conn, pid, ev_date, chrono, tid) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT * FROM amiga_player_slice_at_event s
            WHERE s.slice_key = 'world_cup' AND s.player_id = %s
              AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (%s, %s, %s)
            ORDER BY s.event_date ASC, s.event_chrono ASC, s.as_of_tournament_id ASC
            """,
            (pid, ev_date, chrono, tid),
        )
        return list(cur.fetchall())


def _rise_date_cumulative(timeline, column):
    last, prev = None, None
    for row in timeline:
        v = _num(row.get(column))
        if prev is None or v > prev:
            last = row.get("event_date")
        prev = v
    return last


def _rise_date_ratio(timeline, fn, higher_better):
    last, prev = None, None
    for row in timeline:
        if _num(row.get("games")) < _MIN_GAMES:
            continue
        v = fn(row)
        if v is None:
            continue
        if prev is None or (v > prev if higher_better else v < prev):
            last, prev = row.get("event_date"), v
    return last


def _pick_cumulative(rows, column):
    best, best_val = None, 0.0
    for row in rows:
        v = _num(row.get(column))
        if v <= 0:
            continue
        pid = int(row["player_id"])
        if best is None or v > best_val or (v == best_val and pid < int(best["player_id"])):
            best, best_val = row, v
    return best


def _pick_ratio(rows, fn, higher_better):
    best, best_val = None, None
    for row in rows:
        if _num(row.get("games")) < _MIN_GAMES:
            continue
        v = fn(row)
        if v is None:
            continue
        pid = int(row["player_id"])
        if best is None:
            best, best_val = row, v
            continue
        better = v > best_val if higher_better else v < best_val
        if better or (v == best_val and pid < int(best["player_id"])):
            best, best_val = row, v
    return best, best_val


def _date_str(value) -> str | None:
    if value is None:
        return None
    return str(value)[:10]


def _eq_num(a, b) -> bool:
    if a is None and b is None:
        return True
    if a is None or b is None:
        return False
    return abs(float(a) - float(b)) <= _TOL


def _check_counts_and_present(conn, errors) -> list[dict]:
    wcs = _finalized_wcs(conn)
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) n FROM amiga_wc_hof_snapshots")
        snaps = int(cur.fetchone()["n"])
    if snaps != len(wcs):
        errors.append(f"snapshot count {snaps} != finalized WC count {len(wcs)}")
    if not wcs:
        return wcs
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.* FROM amiga_wc_hof_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC LIMIT 1
            """
        )
        latest = cur.fetchone()
        cur.execute("SELECT * FROM amiga_wc_hof_present WHERE id = 1")
        present = cur.fetchone()
    if present is None:
        errors.append("amiga_wc_hof_present id=1 missing")
        return wcs
    if latest is not None:
        for col in present:
            if col == "id":
                continue
            if str(present.get(col)) != str(latest.get(col)):
                errors.append(f"present.{col}={present.get(col)!r} != latest snapshot {latest.get(col)!r}")
    return wcs


def _check_snapshot_holders(conn, wc, errors) -> None:
    tid, ev_date, chrono = int(wc["id"]), wc["event_date"], wc["chrono"]
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_wc_hof_snapshots WHERE tournament_id = %s", (tid,))
        snap = cur.fetchone()
    if snap is None:
        errors.append(f"tid={tid}: no snapshot row")
        return
    rows = _cutoff_slice_rows(conn, ev_date, chrono, tid)
    tl_cache: dict[int, list[dict]] = {}

    def timeline(pid):
        if pid not in tl_cache:
            tl_cache[pid] = _player_timeline(conn, pid, ev_date, chrono, tid)
        return tl_cache[pid]

    def fail(col, got, exp):
        errors.append(f"tid={tid} {col}: stored={got!r} oracle={exp!r}")

    # Cumulative holders + rise dates.
    for prefix, column in _CUMULATIVE:
        holder = _pick_cumulative(rows, column)
        if holder is None:
            continue
        pid = int(holder["player_id"])
        if int(_num(snap.get(prefix))) != int(_num(holder.get(column))):
            fail(prefix, snap.get(prefix), int(_num(holder.get(column))))
        if snap.get(f"{prefix}ID") != pid:
            fail(f"{prefix}ID", snap.get(f"{prefix}ID"), pid)
        exp_date = _date_str(_rise_date_cumulative(timeline(pid), column))
        if _date_str(snap.get(f"{prefix}Date")) != exp_date:
            fail(f"{prefix}Date", snap.get(f"{prefix}Date"), exp_date)

    # Ratio holders + rise dates.
    for prefix, fn, hb in _RATIO:
        holder, value = _pick_ratio(rows, fn, hb)
        if holder is None:
            continue
        pid = int(holder["player_id"])
        if not _eq_num(snap.get(prefix), _q4(value)):
            fail(prefix, snap.get(prefix), _q4(value))
        if snap.get(f"{prefix}ID") != pid:
            fail(f"{prefix}ID", snap.get(f"{prefix}ID"), pid)
        exp_date = _date_str(_rise_date_ratio(timeline(pid), fn, hb))
        if _date_str(snap.get(f"{prefix}Date")) != exp_date:
            fail(f"{prefix}Date", snap.get(f"{prefix}Date"), exp_date)

    # Single-WC peaks (selection over stored slice peak columns).
    _check_peak_holder(snap, rows, "BestSingleWcGoalsForPerGame", "best_single_wc_gf_per_game", True, conn, fail)
    _check_peak_holder(snap, rows, "BestSingleWcGoalsAgainstPerGame", "best_single_wc_ga_per_game", False, conn, fail)

    # Single-game rows from raw goals.
    _check_single_game(conn, ev_date, chrono, tid, snap, fail)


def _check_peak_holder(snap, rows, prefix, column, higher_better, conn, fail):
    best, best_val = None, None
    for row in rows:
        v = row.get(column)
        if v is None:
            continue
        v = float(v)
        pid = int(row["player_id"])
        if best is None:
            best, best_val = row, v
            continue
        better = v > best_val if higher_better else v < best_val
        if better or (v == best_val and pid < int(best["player_id"])):
            best, best_val = row, v
    if best is None:
        return
    pid = int(best["player_id"])
    tid_anchor = best.get(f"{column}_tournament_id")
    if not _eq_num(snap.get(prefix), _q4(best_val)):
        fail(prefix, snap.get(prefix), _q4(best_val))
    if snap.get(f"{prefix}ID") != pid:
        fail(f"{prefix}ID", snap.get(f"{prefix}ID"), pid)
    if snap.get(f"{prefix}TournamentID") != (int(tid_anchor) if tid_anchor is not None else None):
        fail(f"{prefix}TournamentID", snap.get(f"{prefix}TournamentID"), tid_anchor)
    with conn.cursor() as cur:
        cur.execute("SELECT event_date FROM tournaments WHERE id = %s", (tid_anchor,))
        r = cur.fetchone()
    exp_date = _date_str(r["event_date"]) if r else None
    if _date_str(snap.get(f"{prefix}Date")) != exp_date:
        fail(f"{prefix}Date", snap.get(f"{prefix}Date"), exp_date)


def _check_single_game(conn, ev_date, chrono, tid, snap, fail):
    # Build WC games <= cutoff once.
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b,
                   COALESCE(t.event_date, g.game_date) AS rec_date
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE t.name REGEXP %s
              AND (t.event_date, t.chrono, t.id) <= (%s, %s, %s)
            """,
            (_WC_NAME_RE, ev_date, chrono, tid),
        )
        games = list(cur.fetchall())
    if not games:
        return

    # Most goals in one game (per side), tie -> lowest game id.
    best = None
    for g in games:
        for goals, pid in ((int(g["goals_a"]), int(g["player_a_id"])), (int(g["goals_b"]), int(g["player_b_id"]))):
            cand = (goals, -int(g["id"]), pid, g)
            if goals > 0 and (best is None or cand[:2] > best[:2]):
                best = cand
    if best is not None:
        goals, _, pid, g = best
        if int(_num(snap.get("MostWcGoalsInOneGame"))) != goals:
            fail("MostWcGoalsInOneGame", snap.get("MostWcGoalsInOneGame"), goals)
        if snap.get("MostWcGoalsInOneGameGameID") != int(g["id"]):
            fail("MostWcGoalsInOneGameGameID", snap.get("MostWcGoalsInOneGameGameID"), int(g["id"]))

    # Biggest winning margin, tie -> lowest game id.
    best = None
    for g in games:
        diff = abs(int(g["goals_a"]) - int(g["goals_b"]))
        if int(g["goals_a"]) == int(g["goals_b"]):
            continue
        cand = (diff, -int(g["id"]), g)
        if best is None or cand[:2] > best[:2]:
            best = cand
    if best is not None:
        diff, _, g = best
        if int(_num(snap.get("BiggestWcWinDifference"))) != diff:
            fail("BiggestWcWinDifference", snap.get("BiggestWcWinDifference"), diff)
        if snap.get("BiggestWcWinDifferenceGameID") != int(g["id"]):
            fail("BiggestWcWinDifferenceGameID", snap.get("BiggestWcWinDifferenceGameID"), int(g["id"]))

    # Biggest draw sum, tie -> lowest game id.
    best = None
    for g in games:
        if int(g["goals_a"]) != int(g["goals_b"]):
            continue
        s = int(g["goals_a"]) + int(g["goals_b"])
        cand = (s, -int(g["id"]), g)
        if best is None or cand[:2] > best[:2]:
            best = cand
    if best is not None:
        s, _, g = best
        if int(_num(snap.get("BiggestWcDrawSum"))) != s:
            fail("BiggestWcDrawSum", snap.get("BiggestWcDrawSum"), s)
        if snap.get("BiggestWcDrawSumGameID") != int(g["id"]):
            fail("BiggestWcDrawSumGameID", snap.get("BiggestWcDrawSumGameID"), int(g["id"]))

    # Biggest sum of goals, tie -> lowest game id.
    best = None
    for g in games:
        s = int(g["goals_a"]) + int(g["goals_b"])
        cand = (s, -int(g["id"]), g)
        if best is None or cand[:2] > best[:2]:
            best = cand
    if best is not None:
        s, _, g = best
        if int(_num(snap.get("BiggestWcSumOfGoals"))) != s:
            fail("BiggestWcSumOfGoals", snap.get("BiggestWcSumOfGoals"), s)
        if snap.get("BiggestWcSumOfGoalsGameID") != int(g["id"]):
            fail("BiggestWcSumOfGoalsGameID", snap.get("BiggestWcSumOfGoalsGameID"), int(g["id"]))


def _check_awards_and_peaks_from_games(conn, errors) -> None:
    """Independent recompute of award tallies + peaks from amiga_games (full history)."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.tournament_id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b,
                   t.event_date, t.chrono
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE t.rating_finalized = 1 AND t.name REGEXP %s
            ORDER BY t.event_date ASC, t.chrono ASC, g.tournament_id ASC, g.id ASC
            """,
            (_WC_NAME_RE,),
        )
        games = list(cur.fetchall())

    # Per tournament -> per player {games, gf, ga}.
    by_t: dict[int, dict[int, dict[str, int]]] = {}
    for g in games:
        t = int(g["tournament_id"])
        per = by_t.setdefault(t, {})
        a, b = int(g["player_a_id"]), int(g["player_b_id"])
        ga_, gb_ = int(g["goals_a"]), int(g["goals_b"])
        pa = per.setdefault(a, {"games": 0, "gf": 0, "ga": 0})
        pb = per.setdefault(b, {"games": 0, "gf": 0, "ga": 0})
        pa["games"] += 1; pa["gf"] += ga_; pa["ga"] += gb_
        pb["games"] += 1; pb["gf"] += gb_; pb["ga"] += ga_

    attack: dict[int, int] = {}
    defense: dict[int, int] = {}
    peak_gf: dict[int, float] = {}
    peak_ga: dict[int, float] = {}
    for t, per in by_t.items():
        a_best = d_best = None
        for pid, agg in per.items():
            if agg["games"] <= 0:
                continue
            gf_pg = float(_q4(agg["gf"] / agg["games"]))
            ga_pg = float(_q4(agg["ga"] / agg["games"]))
            if pid not in peak_gf or gf_pg > peak_gf[pid]:
                peak_gf[pid] = gf_pg
            if pid not in peak_ga or ga_pg < peak_ga[pid]:
                peak_ga[pid] = ga_pg
            if a_best is None or (-gf_pg, pid) < a_best[0]:
                a_best = ((-gf_pg, pid), pid)
            if d_best is None or (ga_pg, pid) < d_best[0]:
                d_best = ((ga_pg, pid), pid)
        if a_best is not None:
            attack[a_best[1]] = attack.get(a_best[1], 0) + 1
        if d_best is not None:
            defense[d_best[1]] = defense.get(d_best[1], 0) + 1

    # Compare slice totals for every player with WC participation.
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, best_attack_awards, best_defense_awards,
                   best_single_wc_gf_per_game, best_single_wc_ga_per_game
            FROM amiga_player_slice_totals WHERE slice_key = 'world_cup' AND tournaments_played > 0
            """
        )
        stored = {int(r["player_id"]): r for r in cur.fetchall()}

    pids = set(stored) | set(attack) | set(defense) | set(peak_gf) | set(peak_ga)
    for pid in sorted(pids):
        row = stored.get(pid)
        sa = int(row["best_attack_awards"]) if row else 0
        sd = int(row["best_defense_awards"]) if row else 0
        if sa != attack.get(pid, 0):
            errors.append(f"player_id={pid} best_attack_awards stored={sa} oracle={attack.get(pid, 0)}")
        if sd != defense.get(pid, 0):
            errors.append(f"player_id={pid} best_defense_awards stored={sd} oracle={defense.get(pid, 0)}")
        if row is not None and pid in peak_gf and not _eq_num(row["best_single_wc_gf_per_game"], _q4(peak_gf[pid])):
            errors.append(
                f"player_id={pid} best_single_wc_gf_per_game stored={row['best_single_wc_gf_per_game']!r} "
                f"oracle={_q4(peak_gf[pid])!r}"
            )
        if row is not None and pid in peak_ga and not _eq_num(row["best_single_wc_ga_per_game"], _q4(peak_ga[pid])):
            errors.append(
                f"player_id={pid} best_single_wc_ga_per_game stored={row['best_single_wc_ga_per_game']!r} "
                f"oracle={_q4(peak_ga[pid])!r}"
            )


def main() -> int:
    conn = _connect()
    errors: list[str] = []
    try:
        wcs = _check_counts_and_present(conn, errors)
        for wc in wcs:
            _check_snapshot_holders(conn, wc, errors)
        _check_awards_and_peaks_from_games(conn, errors)
    finally:
        conn.close()

    if errors:
        print("verify-wc-hof FAIL:", len(errors), "issue(s)", file=sys.stderr)
        for msg in errors[:40]:
            print(f"  - {msg}", file=sys.stderr)
        if len(errors) > 40:
            print(f"  ... and {len(errors) - 40} more", file=sys.stderr)
        return 1

    print("verify-wc-hof OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())