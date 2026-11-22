"""Audit extra-time and penalty notes on ko2amiga_work. Read-only.

    python scripts/oneoff/amiga_extra_time_audit.py

1. Every game with a note: is the tie level before extra time, and is the winner placed above the loser?
2. Every level World Cup knockout tie without a note.
3. World Cup player totals: our games against Access [Tables] (needs data/amiga/source/koatd.mdb).

Background and results: docs/amiga-extra-time-review-2026-11.md
"""

from __future__ import annotations

import re
import sys
from collections import defaultdict
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))

from scripts.amiga.import_corrections import PLAYER_NAME_ALIASES

MDB = ROOT / "data" / "amiga" / "source" / "koatd.mdb"
GROUP_PHASE = re.compile(r"Group|League|Round 1\b")
KNOCKOUT_PHASE = re.compile(r"Final|Places|Positions")
PLAYOUTS_LEFT_OUT = {"World Cup II", "World Cup III"}  # Access [Tables] skip those games


def main() -> int:
    conn = pymysql.connect(
        host="127.0.0.1", user="root", password="", database="ko2amiga_work", charset="utf8mb4", cursorclass=DictCursor
    )
    cur = conn.cursor()
    cur.execute("SELECT id, name FROM amiga_players")
    name = {r["id"]: r["name"] for r in cur.fetchall()}
    cur.execute("SELECT tournament_id, player_id, event_finish_position FROM amiga_player_event_snapshots")
    place = {(r["tournament_id"], r["player_id"]): r["event_finish_position"] for r in cur.fetchall()}
    cur.execute(
        "SELECT g.*, t.name AS tournament FROM amiga_games g INNER JOIN tournaments t ON t.id = g.tournament_id ORDER BY g.id"
    )
    games = cur.fetchall()
    conn.close()

    ties: dict[tuple, list[dict]] = defaultdict(list)
    for g in games:
        pair = tuple(sorted((g["player_a_id"], g["player_b_id"])))
        ties[(g["tournament_id"], g["tournament"], g["phase"] or "", pair)].append(g)

    notes = odd = 0
    print("1. Notes that do not fit the rest of the data")
    for (tid, tournament, phase, (x, y)), legs in ties.items():
        noted = [leg for leg in legs if leg["extra"]]
        if not noted:
            continue
        notes += len(noted)

        def total(col: str) -> tuple[int, int]:
            own = sum((leg[f"{col}_a"] if leg["player_a_id"] == x else leg[f"{col}_b"]) or 0 for leg in legs)
            other = sum((leg[f"{col}_b"] if leg["player_a_id"] == x else leg[f"{col}_a"]) or 0 for leg in legs)
            return own, other

        normal, extra, pens = total("goals"), total("goals_et"), total("pens")
        after = (normal[0] + extra[0], normal[1] + extra[1])
        score = after if after[0] != after[1] else pens
        problems = []
        if normal[0] != normal[1]:
            problems.append(f"not level before extra time {normal}")
        if after[0] != after[1] and pens != (0, 0):
            problems.append(f"penalties although not level {after}")
        if score[0] == score[1]:
            problems.append("no winner")
        else:
            winner, loser = (x, y) if score[0] > score[1] else (y, x)
            places = place.get((tid, winner)), place.get((tid, loser))
            if None not in places and places[0] > places[1]:
                problems.append(f"{name[winner]} wins but is placed {places[0]}, {name[loser]} {places[1]}")
        if problems:
            odd += 1
            text = " / ".join(
                f"{name[g['player_a_id']]} {g['goals_a']}-{g['goals_b']} {name[g['player_b_id']]} [{g['extra']}]" for g in legs
            )
            print(f"   {tournament}, {phase}: {text}: {'; '.join(problems)}")
    print(f"   {notes} notes, {odd} ties to look at")

    print("2. Level World Cup knockout ties without a note")
    for (tid, tournament, phase, (x, y)), legs in ties.items():
        if not tournament.startswith("World Cup") or any(leg["extra"] for leg in legs):
            continue
        if GROUP_PHASE.search(phase) and not KNOCKOUT_PHASE.search(phase):
            continue
        diff = sum((leg["goals_a"] - leg["goals_b"]) * (1 if leg["player_a_id"] == x else -1) for leg in legs)
        if diff == 0:
            text = " / ".join(
                f"{name[g['player_a_id']]} {g['goals_a']}-{g['goals_b']} {name[g['player_b_id']]}" for g in legs
            )
            print(f"   {tournament}, {phase}: {text}; placed {name[x]} {place.get((tid, x))}, {name[y]} {place.get((tid, y))}")

    print("3. World Cup player totals against Access [Tables]")
    if not MDB.is_file():
        print("   skipped: no koatd.mdb")
        return 0
    import pyodbc

    access = pyodbc.connect(f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={MDB};ReadOnly=1;", readonly=True)
    # Access counts extra time differently from event to event, so accept any of the four ways of counting.
    ways = [defaultdict(lambda: [0] * 6) for _ in range(4)]
    for g in games:
        if not g["tournament"].startswith("World Cup"):
            continue
        if (g["phase"] or "").startswith("KOA Cup"):
            continue  # a separate event in Access [Tables]
        event = re.sub(r"\s+\(.*\)$", "", g["tournament"])
        event = "World Cup 2015" if event == "World Cup XV" else event
        if event in PLAYOUTS_LEFT_OUT and (g["phase"] or "").startswith("Playouts"):
            continue
        for me, you in (("a", "b"), ("b", "a")):
            own, other = g[f"goals_{me}"], g[f"goals_{you}"]
            et_own, et_other = g[f"goals_et_{me}"] or 0, g[f"goals_et_{you}"] or 0
            for n, way in enumerate(ways):
                gf, ga = (own + et_own, other + et_other) if n & 1 else (own, other)
                result = own + et_own - other - et_other if n & 2 else own - other
                row = way[(event, name[g[f"player_{me}_id"]])]
                for i, v in enumerate((1, result > 0, result == 0, result < 0, gf, ga)):
                    row[i] += v
    rows = exact = 0
    for player, *want, event in access.cursor().execute(
        "SELECT Player, G, W, D, L, GS, GC, Tournament FROM Tables WHERE Tournament LIKE 'World Cup%'"
    ):
        rows += 1
        got = [way.get((event.strip(), PLAYER_NAME_ALIASES.get(player.strip(), player.strip()))) for way in ways]
        if [int(v) for v in want] in got:
            exact += 1
        else:
            print(f"   {event} {player}: games {got[0]}, table {[int(v) for v in want]}")
    print(f"   {rows} players, {exact} exact")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
