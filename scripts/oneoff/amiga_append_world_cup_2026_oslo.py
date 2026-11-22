"""Append World Cup XXIV (Oslo, 14-15 Nov 2026) as catalog-tail ground on ko2amiga_work.

Forward witness path (amiga-modern-ground-platform.md §12): insert L3 ground rows
(tournaments + amiga_games + amiga_tournament_finish_override), then run
``python -m scripts.amiga finalize-tournament --tournament-id N``. Derived data is
NOT written here.

Source: data/amiga/forward/2026-11-14-world-cup-xxiv-oslo/koa_results.txt
(KOA forum t=17982, hash-verified against the live posts 2026-11-22).

Default = validate only (no writes). ``--apply`` inserts in one transaction.
Newcomers must already exist (create them with ``python -m scripts.amiga players create``).

Usage:
  python scripts/oneoff/amiga_append_world_cup_2026_oslo.py            # validate
  python scripts/oneoff/amiga_append_world_cup_2026_oslo.py --apply    # insert ground
"""
from __future__ import annotations

import argparse
import itertools
import re
import sys
from collections import Counter, defaultdict
from datetime import datetime, timedelta
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO))

import pymysql  # noqa: E402

from scripts.amiga.config import load_amiga_db_config  # noqa: E402
from scripts.amiga.tournament_fixtures import _next_live_source_scores_id  # noqa: E402

SOURCE = REPO / "data/amiga/forward/2026-11-14-world-cup-xxiv-oslo/koa_results.txt"

TOURNAMENT = {
    "name": "World Cup XXIV (Oslo)",
    "event_date": "2026-11-14",
    "country": "Norway",
    "is_cup": 0,
    "equal_teams": 1,
    "format_template_id": 1,  # Legacy inferred, same as every earlier World Cup
    "has_league": 1,
    "has_cup": 1,
    "is_world_cup": 1,
    "lifecycle_status": "completed",
}

# Forum short name -> amiga_players.name (only where they differ).
NAME_MAP = {
    "Chris D": "Christopher D",
    "Jørn F": "Jorn F",
}

NEWCOMERS = {"Steve Cl": "England", "Jon L": "England", "Terje S": "Norway", "Martin H": "Norway"}

# Forum section header -> stored phase label (precedent from earlier World Cups).
SECTION_PHASE = {
    "Game of Shame": "Game of Shame",
    "Silver Cup": "Silver Cup - Group G",
    "Silver Cup semi-finals": "Places 17-20",
    "19th place final": "19th Place Final",
    "Silver Cup final": "17th Place Final",
    "15th place final": "15th Place Final",
    "13th place final": "13th Place Final",
    "11th place final": "11th Place Final",
    "9th place final": "9th Place Final",
    "Quarter finals": "Quarter Final",
    "Places 5-8": "Places 5-8",
    "7th place final": "7th Place Final",
    "5th place final": "5th Place Final",
    "Semi finals": "Semi Final",
    "3rd place final": "3rd Place Final",
    "Final": "Final",
}
# Sections whose games form several groups; split by connected components in listed order.
GROUPED_SECTIONS = {
    "Round 1": ["Round 1 - Group A", "Round 1 - Group B", "Round 1 - Group C", "Round 1 - Group D"],
    "World Cup": ["Round 2 - Group E", "Round 2 - Group F"],
}

# Unofficial Elo table posted by dnielsen on the same thread (score, new rating).
FORUM_ELO = {
    "Fabio F": (20.0, 2611), "Dagh N": (24.0, 2600), "Gianni T": (21.5, 2571), "Chris D": (16.5, 2384),
    "Thor S": (15.5, 2328), "Steve B": (13.5, 2144), "Steve C": (15.0, 2116), "Nikos A": (13.0, 2073),
    "Jacob K": (10.5, 2057), "Alessandro V": (10.0, 2041), "Sandro T": (10.5, 1993), "Ilker C": (11.0, 1974),
    "John H": (9.0, 1964), "Mark W": (10.5, 1950), "Frank F": (5.5, 1852), "Robert S": (6.0, 1832),
    "Ian K": (10.5, 1723), "Jørn F": (9.5, 1695), "Mick C": (9.0, 1676), "Rodolfo M": (7.0, 1648),
    "Jon L": (6.5, 1611), "Steve Cl": (6.0, 1608), "Terje S": (7.5, 1604), "Jan G": (5.0, 1595),
    "Heiko W": (5.0, 1594), "Martin H": (4.0, 1558),
}

GAME_RE = re.compile(r"^(?P<a>.+?) (?P<ga>\d+)(?: -)? (?P<gb>\d+) (?P<b>.+)$")
STANDING_RE = re.compile(r"^(?P<pos>\d+)\. (?P<name>.+?)(?: - .*)?$")


def canon(name: str) -> str:
    name = name.strip()
    if name == "Jorn F":
        name = "Jørn F"
    return name


def parse_source() -> tuple[list[dict], list[tuple[int, str]]]:
    games: list[dict] = []
    standings: list[tuple[int, str]] = []
    section = None
    in_standings = False
    for raw in SOURCE.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line == "Final Standings":
            in_standings = True
            continue
        if in_standings:
            m = STANDING_RE.match(line)
            if not m:
                raise SystemExit(f"Bad standings line: {line!r}")
            standings.append((int(m["pos"]), canon(m["name"])))
            continue
        m = GAME_RE.match(line)
        if m and section is not None:
            games.append({"section": section, "a": canon(m["a"]), "b": canon(m["b"]),
                          "ga": int(m["ga"]), "gb": int(m["gb"])})
            continue
        if line in SECTION_PHASE or line in GROUPED_SECTIONS:
            section = line
            continue
        raise SystemExit(f"Unrecognised line: {line!r}")
    return games, standings


def assign_phases(games: list[dict]) -> None:
    by_section: dict[str, list[dict]] = defaultdict(list)
    for g in games:
        by_section[g["section"]].append(g)
    for section, labels in GROUPED_SECTIONS.items():
        rows = by_section[section]
        groups: list[set[str]] = []
        for g in rows:
            hit = [s for s in groups if g["a"] in s or g["b"] in s]
            merged = set().union(*hit) | {g["a"], g["b"]} if hit else {g["a"], g["b"]}
            groups = [s for s in groups if s not in hit] + [merged]
        # order groups by first appearance
        first = {}
        for i, g in enumerate(rows):
            for s_i, s in enumerate(groups):
                if g["a"] in s and s_i not in first:
                    first[s_i] = i
        ordered = [groups[i] for i in sorted(first, key=first.get)]
        if len(ordered) != len(labels):
            raise SystemExit(f"{section}: expected {len(labels)} groups, found {len(ordered)}")
        for g in rows:
            for label, members in zip(labels, ordered):
                if g["a"] in members:
                    g["phase"] = label
    for g in games:
        g.setdefault("phase", SECTION_PHASE.get(g["section"]))
        if not g["phase"]:
            raise SystemExit(f"No phase for {g}")


def validate(games: list[dict], standings: list[tuple[int, str]]) -> list[str]:
    errors: list[str] = []
    players = sorted({g["a"] for g in games} | {g["b"] for g in games})
    if len(players) != 26:
        errors.append(f"expected 26 participants, found {len(players)}: {players}")
    st_names = [n for _, n in standings]
    if sorted(st_names) != players:
        errors.append(f"standings names differ from participants: {set(st_names) ^ set(players)}")
    if [p for p, _ in standings] != list(range(1, 27)):
        errors.append("standings positions are not 1..26")
    for g in games:
        if g["a"] == g["b"]:
            errors.append(f"self game {g}")

    by_phase: dict[str, list[dict]] = defaultdict(list)
    for g in games:
        by_phase[g["phase"]].append(g)

    def check_league(phase: str, double: bool) -> None:
        rows = by_phase[phase]
        members = sorted({g["a"] for g in rows} | {g["b"] for g in rows})
        if double:
            ordered = Counter((g["a"], g["b"]) for g in rows)
            want = {(x, y) for x in members for y in members if x != y}
            if set(ordered) != want or any(v != 1 for v in ordered.values()):
                errors.append(f"{phase}: not a complete double round robin ({len(rows)} games, {len(members)} players)")
        else:
            pairs = Counter(frozenset((g["a"], g["b"])) for g in rows)
            want = {frozenset(p) for p in itertools.combinations(members, 2)}
            if set(pairs) != want or any(v != 1 for v in pairs.values()):
                errors.append(f"{phase}: not a complete single round robin ({len(rows)} games, {len(members)} players)")
        print(f"  {phase:22s} {len(members):2d} players {len(rows):3d} games")

    for p in GROUPED_SECTIONS["Round 1"]:
        check_league(p, double=True)
    check_league("Silver Cup - Group G", double=False)
    for p in GROUPED_SECTIONS["World Cup"]:
        check_league(p, double=False)

    # two-leg ties: same pair, reversed home/away
    for phase in ["Places 17-20", "15th Place Final", "13th Place Final", "11th Place Final", "9th Place Final",
                  "Quarter Final", "Places 5-8", "7th Place Final", "5th Place Final", "Semi Final", "Final"]:
        rows = by_phase[phase]
        if len(rows) % 2:
            errors.append(f"{phase}: odd number of legs ({len(rows)})")
            continue
        for leg1, leg2 in zip(rows[0::2], rows[1::2]):
            if (leg1["a"], leg1["b"]) != (leg2["b"], leg2["a"]):
                errors.append(f"{phase}: legs not paired {leg1} / {leg2}")
            agg_a = leg1["ga"] + leg2["gb"]
            agg_b = leg1["gb"] + leg2["ga"]
            if agg_a == agg_b:
                errors.append(f"{phase}: aggregate tie {leg1['a']} v {leg1['b']} {agg_a}-{agg_b} (needs ET/pens)")
    return errors


def tie_winners(games: list[dict], phase: str) -> list[tuple[str, str]]:
    rows = [g for g in games if g["phase"] == phase]
    out = []
    if len(rows) == 1:
        g = rows[0]
        return [(g["a"], g["b"]) if g["ga"] > g["gb"] else (g["b"], g["a"])]
    for leg1, leg2 in zip(rows[0::2], rows[1::2]):
        agg_a = leg1["ga"] + leg2["gb"]
        agg_b = leg1["gb"] + leg2["ga"]
        out.append((leg1["a"], leg1["b"]) if agg_a > agg_b else (leg1["b"], leg1["a"]))
    return out


def check_placements(games: list[dict], standings: list[tuple[int, str]]) -> list[str]:
    errors = []
    pos = {n: p for p, n in standings}
    finals = {"Final": 1, "3rd Place Final": 3, "5th Place Final": 5, "7th Place Final": 7, "9th Place Final": 9,
              "11th Place Final": 11, "13th Place Final": 13, "15th Place Final": 15, "17th Place Final": 17,
              "19th Place Final": 19}
    for phase, place in finals.items():
        for winner, loser in tie_winners(games, phase):
            if (pos[winner], pos[loser]) != (place, place + 1):
                errors.append(f"{phase}: winner {winner} #{pos[winner]}, loser {loser} #{pos[loser]} (expected {place}/{place + 1})")
    return errors


def scores(games: list[dict]) -> dict[str, float]:
    s: dict[str, float] = defaultdict(float)
    for g in games:
        if g["ga"] > g["gb"]:
            s[g["a"]] += 1
        elif g["ga"] < g["gb"]:
            s[g["b"]] += 1
        else:
            s[g["a"]] += 0.5
            s[g["b"]] += 0.5
    return s


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true", help="insert ground rows (default: validate only)")
    args = ap.parse_args()

    games, standings = parse_source()
    assign_phases(games)
    print(f"parsed {len(games)} games, {len(standings)} standings rows")
    errors = validate(games, standings)
    errors += check_placements(games, standings)

    sc = scores(games)
    diffs = {n: (sc[n], FORUM_ELO[n][0]) for n in FORUM_ELO if abs(sc[n] - FORUM_ELO[n][0]) > 1e-9}
    print(f"score sum parsed={sum(sc.values())} forum_table={sum(v[0] for v in FORUM_ELO.values())}")
    if diffs:
        print(f"  score differences vs forum Elo table (parsed, table): {diffs}")

    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_work":
        errors.append(f"refusing: target database is {cfg.database!r}, expected ko2amiga_work")
    conn = pymysql.connect(host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
                           database=cfg.database, charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
                           autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id, name, country FROM amiga_players")
            by_name = {r["name"]: r for r in cur.fetchall()}
            ids: dict[str, int] = {}
            missing = []
            for forum_name in sorted({g["a"] for g in games} | {g["b"] for g in games}):
                db_name = NAME_MAP.get(forum_name, forum_name)
                row = by_name.get(db_name)
                if row is None:
                    missing.append(forum_name)
                    continue
                if forum_name in NEWCOMERS and row["country"] != NEWCOMERS[forum_name]:
                    errors.append(f"{forum_name}: country {row['country']!r} != {NEWCOMERS[forum_name]!r}")
                ids[forum_name] = int(row["id"])
            unexpected = [m for m in missing if m not in NEWCOMERS]
            if unexpected:
                errors.append(f"unknown players (not newcomers): {unexpected}")
            cur.execute("SELECT COUNT(*) n FROM tournaments WHERE name = %s", (TOURNAMENT["name"],))
            if cur.fetchone()["n"]:
                errors.append(f"tournament {TOURNAMENT['name']!r} already exists")
            cur.execute("SELECT MAX(chrono) c, MAX(id) i FROM tournaments")
            r = cur.fetchone()
            next_chrono = int(r["c"]) + 1
            cur.execute("SELECT MAX(event_date) d FROM tournaments")
            last_date = cur.fetchone()["d"]
            if str(last_date) >= TOURNAMENT["event_date"]:
                errors.append(f"not a tip append: latest event_date {last_date} >= {TOURNAMENT['event_date']}")

        if missing:
            print(f"newcomers not yet created: {missing}")
        if errors:
            print("\nVALIDATION FAILED:")
            for e in errors:
                print("  - " + e)
            return 1
        print(f"\nvalidation OK. next chrono={next_chrono}; players mapped={len(ids)}/26")
        if not args.apply:
            print("dry run (no --apply): nothing written")
            return 0
        if missing:
            print("refusing --apply: create newcomers first")
            return 1

        base = datetime.strptime(TOURNAMENT["event_date"], "%Y-%m-%d") + timedelta(hours=2)
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO tournaments
                  (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                   format_template_id, has_league, has_cup, is_world_cup, lifecycle_status,
                   completed_at, rating_finalized)
                VALUES (NULL, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0)
                """,
                (TOURNAMENT["name"], next_chrono, TOURNAMENT["event_date"], TOURNAMENT["is_cup"],
                 TOURNAMENT["country"], TOURNAMENT["equal_teams"], 26, TOURNAMENT["format_template_id"],
                 TOURNAMENT["has_league"], TOURNAMENT["has_cup"], TOURNAMENT["is_world_cup"],
                 TOURNAMENT["lifecycle_status"], TOURNAMENT["event_date"] + " 00:00:00"),
            )
            tid = cur.lastrowid
            # Forward games use the live source_scores_id range (>= 1e9), like organizer-promoted games.
            first_ssid = _next_live_source_scores_id(conn)
            rows = [
                (first_ssid + i, base + timedelta(seconds=i), ids[g["a"]], ids[g["b"]], tid, g["phase"], g["ga"], g["gb"])
                for i, g in enumerate(games)
            ]
            cur.executemany(
                """
                INSERT INTO amiga_games
                  (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, phase, goals_a, goals_b)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """,
                rows,
            )
            cur.executemany(
                "INSERT INTO amiga_tournament_finish_override (tournament_id, player_id, event_finish_position) VALUES (%s, %s, %s)",
                [(tid, ids[n], p) for p, n in standings],
            )
        conn.commit()
        print(f"INSERTED tournament_id={tid} chrono={next_chrono} games={len(rows)} finish_override=26")
        print(f"next: python -m scripts.amiga finalize-tournament --tournament-id {tid}")
        return 0
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())
