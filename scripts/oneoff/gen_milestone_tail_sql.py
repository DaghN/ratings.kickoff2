#!/usr/bin/env python3
"""
Generate player_milestones_rebuild_tail.sql — final curated keys (playertable + matchup).

Also writes player_milestones_rebuild_diversity_merchant.sql (surgical DELETE + INSERTs).

diversity_merchant / travelling_salesman: distinct opponents in a game where player scored 10+.

Regenerate:
  python scripts/oneoff/gen_milestone_tail_sql.py
"""
from __future__ import annotations

import sys
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402

OUT = _REPO / "docs" / "archive" / "batch-rebuild-sql-2026-05" / "player_milestones_rebuild_tail.sql"
OUT_DIVERSITY = (
    _REPO / "docs" / "archive" / "batch-rebuild-sql-2026-05" / "player_milestones_rebuild_diversity_merchant.sql"
)

GAME_N: list[tuple[str, int, int]] = [
    ("half_century_50", 50, 50),
    ("centurion_100", 100, 100),
    ("marathoner_250", 250, 250),
    ("millennium_merchant_1000", 1000, 1000),
]

OPPONENT_N: list[tuple[str, int, int]] = [
    ("ten_opponents", 10, 10),
    ("wide_net", 25, 25),
    ("fifty_faces", 50, 50),
    ("century_of_rivals", 100, 100),
    ("five_victims", 5, 5),
    ("twenty_five_victims", 25, 25),
    ("ten_culprits", 10, 10),
]

PAIR_GAMES: list[tuple[str, int, int]] = [
    ("ten_match_saga", 10, 10),
    ("lifetime_rivalry", 50, 50),
]

PAIR_WINS: list[tuple[str, int, int]] = [
    ("regular_customer", 10, 10),
    ("bogeyman", 20, 20),
]

SPECIAL_DD_OPP: list[tuple[str, int, int]] = [
    ("diversity_merchant", 5, 5),
    ("travelling_salesman", 10, 10),
]

SPECIAL_CS_OPP: list[tuple[str, int, int]] = [
    ("clean_sheet_spread", 10, 10),
]


@dataclass
class PlayerTail:
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    clean_sheets: int = 0
    opponents: set[int] = field(default_factory=set)
    victims: set[int] = field(default_factory=set)
    culprits: set[int] = field(default_factory=set)
    dd_opponents: set[int] = field(default_factory=set)
    cs_opponents: set[int] = field(default_factory=set)
    pair_games: dict[int, int] = field(default_factory=lambda: defaultdict(int))
    pair_wins: dict[int, int] = field(default_factory=lambda: defaultdict(int))
    done: set[str] = field(default_factory=set)


def _unlock(
    rows: list[tuple[int, str, datetime, int, int]],
    pid: int,
    key: str,
    st: PlayerTail,
    dt: datetime,
    gid: int,
    val: int,
) -> None:
    if key in st.done:
        return
    st.done.add(key)
    rows.append((pid, key, dt, val, gid))


def _check_thresholds(
    rows: list[tuple[int, str, datetime, int, int]],
    pid: int,
    st: PlayerTail,
    dt: datetime,
    gid: int,
    specs: list[tuple[str, int, int]],
    current: int,
) -> None:
    for key, thresh, val in specs:
        if current >= thresh:
            _unlock(rows, pid, key, st, dt, gid, val)


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    cur.execute("SELECT ID FROM playertable WHERE NumberGames >= 1")
    eligible = {int(r["ID"]) for r in cur.fetchall()}
    cur.execute(
        """
        SELECT id, `Date`, idA, idB, GoalsA, GoalsB, ActualScore
        FROM ratedresults
        ORDER BY `Date` ASC, id ASC
        """
    )
    games = cur.fetchall()
    con.close()

    players: dict[int, PlayerTail] = {}
    rows: list[tuple[int, str, datetime, int, int]] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        id_a, id_b = int(g["idA"]), int(g["idB"])
        ga, gb = int(g["GoalsA"] or 0), int(g["GoalsB"] or 0)
        sc = float(g["ActualScore"])

        for pid, gf, ga_c, opp, won, drew, lost in (
            (id_a, ga, gb, id_b, sc == 1.0, sc == 0.5, sc == 0.0),
            (id_b, gb, ga, id_a, sc == 0.0, sc == 0.5, sc == 1.0),
        ):
            if pid not in eligible:
                continue
            st = players.setdefault(pid, PlayerTail())

            st.games += 1
            _check_thresholds(rows, pid, st, dt, gid, GAME_N, st.games)

            if gf >= 1 and st.goals_for == 0:
                _unlock(rows, pid, "first_goal", st, dt, gid, 1)

            if won:
                if st.wins == 0:
                    _unlock(rows, pid, "first_victory", st, dt, gid, 1)
                st.wins += 1
                if st.wins == 100:
                    _unlock(rows, pid, "century_of_wins", st, dt, gid, 100)
            elif drew:
                if st.draws == 0:
                    _unlock(rows, pid, "first_handshake", st, dt, gid, 1)
                st.draws += 1
                if st.draws == 10:
                    _unlock(rows, pid, "ten_draws", st, dt, gid, 10)
            elif lost:
                if st.losses == 0:
                    _unlock(rows, pid, "welcome_to_the_ladder", st, dt, gid, 1)
                st.losses += 1
                if st.losses == 100:
                    _unlock(rows, pid, "battle_scarred", st, dt, gid, 100)

            prev_goals = st.goals_for
            st.goals_for += gf
            if prev_goals < 100 <= st.goals_for:
                _unlock(rows, pid, "hundred_goals", st, dt, gid, 100)
            if prev_goals < 1000 <= st.goals_for:
                _unlock(rows, pid, "thousand_goal_club", st, dt, gid, 1000)

            if ga_c == 0:
                st.clean_sheets += 1
                if st.clean_sheets == 1:
                    _unlock(rows, pid, "first_shutout", st, dt, gid, 1)
                if st.clean_sheets == 25:
                    _unlock(rows, pid, "fortress_builder", st, dt, gid, 25)
                if st.clean_sheets == 50:
                    _unlock(rows, pid, "clean_sheet_artist", st, dt, gid, 50)
            if ga_c == 0 and opp not in st.cs_opponents:
                st.cs_opponents.add(opp)
                _check_thresholds(
                    rows, pid, st, dt, gid, SPECIAL_CS_OPP, len(st.cs_opponents)
                )

            if opp not in st.opponents:
                st.opponents.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, OPPONENT_N[:4], len(st.opponents))

            if won and opp not in st.victims:
                st.victims.add(opp)
                _check_thresholds(
                    rows, pid, st, dt, gid, OPPONENT_N[4:6], len(st.victims)
                )

            if lost and opp not in st.culprits:
                st.culprits.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, OPPONENT_N[6:7], len(st.culprits))

            if gf >= 10 and opp not in st.dd_opponents:
                st.dd_opponents.add(opp)
                _check_thresholds(
                    rows, pid, st, dt, gid, SPECIAL_DD_OPP, len(st.dd_opponents)
                )

            st.pair_games[opp] += 1
            _check_thresholds(
                rows, pid, st, dt, gid, PAIR_GAMES, st.pair_games[opp]
            )

            if won:
                st.pair_wins[opp] += 1
                _check_thresholds(
                    rows, pid, st, dt, gid, PAIR_WINS, st.pair_wins[opp]
                )

    lines = [
        "-- Generated by scripts/oneoff/gen_milestone_tail_sql.py",
        "-- Playertable + matchup first cross; source_kind=game.",
        "",
    ]
    for pid, mk, dt, val, gid in rows:
        ts = dt.strftime("%Y-%m-%d %H:%M:%S")
        lines.append(
            f"INSERT INTO `player_milestones` "
            f"(`player_id`, `milestone_key`, `achieved_at`, `value`, "
            f"`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) "
            f"VALUES ({pid}, '{mk}', '{ts}', {val}, 'game', {gid}, NULL, NULL, NULL);"
        )
    lines.append("")
    OUT.write_text("\n".join(lines), encoding="utf-8")

    div_lines = [
        "-- Surgical: diversity_merchant only (gen_milestone_tail_sql.py)",
        "-- Per-game DD (10+ goals) vs 5 distinct opponents. Run after DELETE below.",
        "DELETE FROM `player_milestones` WHERE `milestone_key` = 'diversity_merchant';",
        "",
    ]
    div_rows = [r for r in rows if r[1] == "diversity_merchant"]
    for pid, mk, dt, val, gid in div_rows:
        ts = dt.strftime("%Y-%m-%d %H:%M:%S")
        div_lines.append(
            f"INSERT INTO `player_milestones` "
            f"(`player_id`, `milestone_key`, `achieved_at`, `value`, "
            f"`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) "
            f"VALUES ({pid}, '{mk}', '{ts}', {val}, 'game', {gid}, NULL, NULL, NULL);"
        )
    div_lines.append("")
    OUT_DIVERSITY.write_text("\n".join(div_lines), encoding="utf-8")

    keys = {r[1] for r in rows}
    print(f"Wrote {OUT} ({len(rows)} rows, {len(keys)} keys)")
    print(f"Wrote {OUT_DIVERSITY} ({len(div_rows)} diversity_merchant rows)")


if __name__ == "__main__":
    main()
