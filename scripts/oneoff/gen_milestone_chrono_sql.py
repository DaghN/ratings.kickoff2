#!/usr/bin/env python3
"""
Generate player_milestones_rebuild_chrono.sql — first chronological cross + source_game_id.

Skips peace_streak (gen_milestone_streak_sql.py). Regenerate:
  python scripts/oneoff/gen_milestone_chrono_sql.py
"""
from __future__ import annotations

import calendar
import sys
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402
from scripts.oneoff.milestone_giant_slayer import (  # noqa: E402
    giant_slayer_active_top_id,
    giant_slayer_qualifies,
)

OUT = _REPO / "scripts" / "ladder" / "sql" / "player_milestones_rebuild_chrono.sql"
GIANT_SLAYER_OUT = (
    _REPO / "scripts" / "ladder" / "sql" / "player_milestones_rebuild_giant_slayer.sql"
)

SKIP_KEYS = frozenset({"peace_streak"})


def _monday_week_key(d: date) -> str:
    monday = d - timedelta(days=d.weekday())
    return monday.isoformat()


def _month_key(d: date) -> str:
    return f"{d.year:04d}-{d.month:02d}"


@dataclass
class PlayerChrono:
    games: int = 0
    last_date: date | None = None
    current_day: str | None = None
    last_dt: datetime | None = None
    last_gid: int = 0
    score_streak: int = 0
    draw_streak: int = 0
    win_margin_streak: int = 0
    loss_margin_streak: int = 0
    merchant_streak: int = 0
    exact_ten_streak: int = 0
    days_by_month: dict[str, set[int]] = field(default_factory=lambda: defaultdict(set))
    games_by_day: dict[str, list[str]] = field(default_factory=lambda: defaultdict(list))
    games_by_month: dict[str, int] = field(default_factory=lambda: defaultdict(int))
    week_days: dict[str, set[int]] = field(default_factory=lambda: defaultdict(set))
    week_keys: list[str] = field(default_factory=list)
    done: set[str] = field(default_factory=set)


def _unlock(
    rows: list[tuple[int, str, datetime, int, int]],
    pid: int,
    key: str,
    st: PlayerChrono,
    dt: datetime,
    gid: int,
    val: int,
) -> None:
    if key in SKIP_KEYS or key in st.done:
        return
    st.done.add(key)
    rows.append((pid, key, dt, val, gid))


def _finalize_day(
    rows: list[tuple[int, str, datetime, int, int]],
    pid: int,
    st: PlayerChrono,
    day_key: str,
    dt: datetime,
    gid: int,
) -> None:
    outcomes = st.games_by_day.get(day_key, [])
    if len(outcomes) >= 5 and all(o == "W" for o in outcomes):
        _unlock(rows, pid, "perfect_day", st, dt, gid, 5)
    if len(outcomes) >= 5 and all(o == "L" for o in outcomes):
        _unlock(rows, pid, "nightmare_day", st, dt, gid, 5)


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    cur.execute("SELECT ID FROM playertable WHERE NumberGames >= 1")
    eligible = {int(r["ID"]) for r in cur.fetchall()}
    cur.execute(
        """
        SELECT id, `Date`, idA, idB, GoalsA, GoalsB, ActualScore,
               RatingA, RatingB, NewRatingA, NewRatingB
        FROM ratedresults
        ORDER BY `Date` ASC, id ASC
        """
    )
    games = cur.fetchall()
    con.close()

    players: dict[int, PlayerChrono] = {}
    ratings: dict[int, float] = defaultdict(lambda: 1600.0)
    last_game: dict[int, datetime] = {}
    rows: list[tuple[int, str, datetime, int, int]] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        d = dt.date()
        id_a, id_b = int(g["idA"]), int(g["idB"])
        last_game[id_a] = dt
        last_game[id_b] = dt
        ga, gb = int(g["GoalsA"] or 0), int(g["GoalsB"] or 0)
        sc = float(g["ActualScore"])

        for pid, gf, ga_c, opp, r_pre, r_opp, new_r in (
            (
                id_a,
                ga,
                gb,
                id_b,
                float(g["RatingA"] or 1600),
                float(g["RatingB"] or 1600),
                float(g["NewRatingA"] or 0),
            ),
            (
                id_b,
                gb,
                ga,
                id_a,
                float(g["RatingB"] or 1600),
                float(g["RatingA"] or 1600),
                float(g["NewRatingB"] or 0),
            ),
        ):
            st = players.setdefault(pid, PlayerChrono())
            day_key = d.isoformat()

            if st.games == 0:
                ost = players.setdefault(opp, PlayerChrono())
                _unlock(rows, opp, "newbie_welcomer", ost, dt, gid, 1)
                if gf >= 2:
                    _unlock(rows, opp, "generous", ost, dt, gid, 2)

            if st.current_day is not None and st.current_day != day_key:
                _finalize_day(
                    rows,
                    pid,
                    st,
                    st.current_day,
                    st.last_dt or dt,
                    st.last_gid or gid,
                )
            st.current_day = day_key

            if st.games >= 50 and gf == 0:
                _unlock(rows, pid, "rare_blank", st, dt, gid, 0)

            st.games += 1
            month_key = _month_key(d)
            st.days_by_month[month_key].add(d.day)
            outcome = (
                "W"
                if (sc == 1 and pid == id_a) or (sc == 0 and pid == id_b)
                else ("D" if sc == 0.5 else "L")
            )
            st.games_by_day[day_key].append(outcome)
            st.games_by_month[month_key] += 1

            won = (sc == 1 and pid == id_a) or (sc == 0 and pid == id_b)
            drew = sc == 0.5
            lost = not won and not drew
            margin = abs(gf - ga_c) if won or lost else 0

            if gf > 0:
                st.score_streak += 1
            else:
                st.score_streak = 0
            if st.score_streak >= 10:
                _unlock(rows, pid, "on_the_scoresheet", st, dt, gid, 10)

            if drew:
                st.draw_streak += 1
            else:
                st.draw_streak = 0
            if st.draw_streak >= 5:
                _unlock(rows, pid, "united_nations", st, dt, gid, 5)

            if won and margin == 1:
                st.win_margin_streak += 1
            else:
                st.win_margin_streak = 0
            if st.win_margin_streak >= 5:
                _unlock(rows, pid, "knife_edge", st, dt, gid, 5)

            if lost and margin == 1:
                st.loss_margin_streak += 1
            else:
                st.loss_margin_streak = 0
            if st.loss_margin_streak >= 5:
                _unlock(rows, pid, "unlucky", st, dt, gid, 5)

            if gf >= 10:
                st.merchant_streak += 1
            else:
                st.merchant_streak = 0
            if st.merchant_streak >= 5:
                _unlock(rows, pid, "merchant_streak", st, dt, gid, 5)

            if gf == 10:
                st.exact_ten_streak += 1
            else:
                st.exact_ten_streak = 0
            if st.exact_ten_streak >= 3:
                _unlock(rows, pid, "minimalist_merchant", st, dt, gid, 3)

            ratings[pid] = new_r if new_r > 0 else ratings[pid]
            top_id = giant_slayer_active_top_id(
                ratings, last_game, dt, in_game=(id_a, id_b)
            )
            if giant_slayer_qualifies(
                won=won, pid=pid, opp=opp, top_id=top_id, r_pre=r_pre, r_opp=r_opp
            ):
                _unlock(rows, pid, "giant_slayer", st, dt, gid, 1)

            y, m = map(int, month_key.split("-"))
            last = calendar.monthrange(y, m)[1]
            days = st.days_by_month[month_key]
            if len(days) >= last and all(day in days for day in range(1, last + 1)):
                _unlock(rows, pid, "monthly_regular", st, dt, gid, last)

            wk = _monday_week_key(d)
            st.week_days[wk].add(d.weekday())
            if st.week_days[wk] >= set(range(7)):
                _unlock(rows, pid, "daily_habit", st, dt, gid, 7)

            if wk not in st.week_keys:
                st.week_keys.append(wk)
                st.week_keys.sort()
                if len(st.week_keys) >= 13:
                    for i in range(len(st.week_keys) - 12):
                        block = st.week_keys[i : i + 13]
                        ok = True
                        for j in range(1, 13):
                            d0 = date.fromisoformat(block[j - 1])
                            d1 = date.fromisoformat(block[j])
                            if (d1 - d0).days > 10:
                                ok = False
                                break
                        if ok:
                            _unlock(rows, pid, "weekly_regular", st, dt, gid, 13)
                            break

            months_sorted = sorted(
                mk for mk in st.games_by_month if st.games_by_month[mk] > 0
            )
            if len(months_sorted) >= 12:
                for i in range(len(months_sorted) - 11):
                    y0, m0 = map(int, months_sorted[i].split("-"))
                    ok = True
                    for j in range(12):
                        ym = y0 + (m0 - 1 + j) // 12
                        mm = (m0 - 1 + j) % 12 + 1
                        key = f"{ym:04d}-{mm:02d}"
                        if key not in st.games_by_month or st.games_by_month[key] < 1:
                            ok = False
                            break
                    if ok:
                        _unlock(rows, pid, "year_round", st, dt, gid, 12)
                        break

            st.last_dt = dt
            st.last_gid = gid

    for pid, st in players.items():
        if st.current_day is not None and st.last_dt is not None:
            _finalize_day(rows, pid, st, st.current_day, st.last_dt, st.last_gid)

    appearance_eligible = {pid for pid, st in players.items() if st.games >= 1}
    rows = [r for r in rows if r[0] in appearance_eligible]

    lines = [
        "-- Generated by scripts/oneoff/gen_milestone_chrono_sql.py",
        "-- Chronological first cross; source_kind=game. peace_streak in streaks SQL.",
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
    gs = [r for r in rows if r[1] == "giant_slayer"]
    gs_lines = [
        "-- Surgical: giant_slayer only (gen_milestone_chrono_sql.py)",
        "-- Active #1 = highest rating among players with rated game in last 365d UTC",
        "-- (or either player in the current game). Run after DELETE below.",
        "DELETE FROM `player_milestones` WHERE `milestone_key` = 'giant_slayer';",
        "",
    ]
    for pid, mk, dt, val, gid in gs:
        ts = dt.strftime("%Y-%m-%d %H:%M:%S")
        gs_lines.append(
            f"INSERT INTO `player_milestones` "
            f"(`player_id`, `milestone_key`, `achieved_at`, `value`, "
            f"`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) "
            f"VALUES ({pid}, '{mk}', '{ts}', {val}, 'game', {gid}, NULL, NULL, NULL);"
        )
    gs_lines.append("")
    GIANT_SLAYER_OUT.write_text("\n".join(gs_lines), encoding="utf-8")
    print(f"Wrote {OUT} ({len(rows)} rows, {len({r[1] for r in rows})} keys)")
    print(f"Wrote {GIANT_SLAYER_OUT} ({len(gs)} giant_slayer rows)")


if __name__ == "__main__":
    main()
