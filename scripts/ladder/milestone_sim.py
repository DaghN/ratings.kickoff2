"""Chronological milestone simulation (batch oracle for P6 parity)."""

from __future__ import annotations

import calendar
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta
from typing import Any

from scripts.oneoff.milestone_giant_slayer import (
    giant_slayer_active_top_id,
    giant_slayer_qualifies,
)

MilestoneRow = tuple[int, str, datetime, int, int]  # pid, key, achieved_at, value, game_id

NTH_GAME_KEYS: dict[int, tuple[str, int]] = {
    1: ("debut", 1),
    10: ("persistence", 10),
    20: ("established_20", 20),
    50: ("half_century_50", 50),
    100: ("centurion_100", 100),
    250: ("marathoner_250", 250),
    500: ("club_500", 500),
    1000: ("millennium_merchant_1000", 1000),
    10000: ("club_10000", 10000),
}

CLUB_THRESHOLDS: tuple[tuple[str, int], ...] = (
    ("club_1700", 1700),
    ("club_1800", 1800),
    ("club_2000", 2000),
    ("club_2300", 2300),
)


def simulate_appearance_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    counts: dict[int, int] = {}
    dd_done: set[int] = set()
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        id_a, id_b = int(g["idA"]), int(g["idB"])
        ga, gb = int(g["GoalsA"] or 0), int(g["GoalsB"] or 0)
        for pid, gf in ((id_a, ga), (id_b, gb)):
            if eligible is not None and pid not in eligible:
                continue
            counts[pid] = counts.get(pid, 0) + 1
            n = counts[pid]
            if n in NTH_GAME_KEYS:
                key, val = NTH_GAME_KEYS[n]
                rows.append((pid, key, dt, val, gid))
            if gf >= 10 and pid not in dd_done:
                dd_done.add(pid)
                rows.append((pid, "dd_merchant_10", dt, 10, gid))

    return rows


def simulate_club_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    peak: dict[int, float] = {}
    done: dict[int, set[str]] = {}
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        for pid, new_r, pre_r in (
            (int(g["idA"]), float(g["NewRatingA"] or 0), float(g["RatingA"] or 0)),
            (int(g["idB"]), float(g["NewRatingB"] or 0), float(g["RatingB"] or 0)),
        ):
            if eligible is not None and pid not in eligible:
                continue
            if new_r <= 0:
                continue
            prev = peak.get(pid, pre_r)
            peak[pid] = max(prev, new_r)
            unlocked = done.setdefault(pid, set())
            for key, thresh in CLUB_THRESHOLDS:
                if key in unlocked:
                    continue
                if prev < thresh <= peak[pid]:
                    unlocked.add(key)
                    rows.append((pid, key, dt, thresh, gid))

    return rows


# --- streak (gen_milestone_streak_sql.py) ---

STREAK_SPECS: list[tuple[str, str, int, int]] = [
    ("win_hat_trick", "win", 3, 3),
    ("ten_wins_straight", "win", 10, 10),
    ("rampage", "win", 15, 15),
    ("win_streak_30", "win", 30, 30),
    ("cold_streak", "loss", 5, 5),
    ("win_drought", "non_win", 10, 10),
    ("peace_streak", "draw", 3, 3),
    ("ten_wins", "career_wins", 10, 10),
]


@dataclass
class PlayerStreak:
    win: int = 0
    loss: int = 0
    non_win: int = 0
    draw: int = 0
    career_wins: int = 0
    done: set[str] = field(default_factory=set)


def simulate_streak_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    players: dict[int, PlayerStreak] = {}
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        id_a, id_b = int(g["idA"]), int(g["idB"])
        sc = float(g["ActualScore"])

        for pid, won, drew, lost in (
            (id_a, sc == 1.0, sc == 0.5, sc == 0.0),
            (id_b, sc == 0.0, sc == 0.5, sc == 1.0),
        ):
            if eligible is not None and pid not in eligible:
                continue
            st = players.setdefault(pid, PlayerStreak())

            if won:
                st.win += 1
                st.loss = 0
                st.non_win = 0
                st.draw = 0
                st.career_wins += 1
            elif drew:
                st.win = 0
                st.loss = 0
                st.non_win += 1
                st.draw += 1
            elif lost:
                st.win = 0
                st.loss += 1
                st.non_win += 1
                st.draw = 0

            for key, kind, thresh, val in STREAK_SPECS:
                if key in st.done:
                    continue
                if kind == "career_wins":
                    ok = st.career_wins == thresh
                elif kind == "win":
                    ok = won and st.win == thresh
                elif kind == "loss":
                    ok = lost and st.loss == thresh
                elif kind == "draw":
                    ok = drew and st.draw == thresh
                else:
                    ok = not won and st.non_win == thresh
                if not ok:
                    continue
                st.done.add(key)
                rows.append((pid, key, dt, val, gid))

    return rows


# --- year_in_heaven (52 UTC week slots / calendar year) ---


def _calendar_year_week_mondays(year: int) -> list[str]:
    if year < 2000 or year > 2100:
        return []
    jan1 = date(year, 1, 1)
    week1 = jan1 - timedelta(days=jan1.weekday())
    return [(week1 + timedelta(days=7 * i)).isoformat() for i in range(52)]


def _calendar_year_for_week_monday(week_monday: str) -> int | None:
    try:
        y = int(week_monday[:4])
    except ValueError:
        return None
    for candidate in (y - 1, y, y + 1):
        if week_monday in _calendar_year_week_mondays(candidate):
            return candidate
    return None


def simulate_year_in_heaven_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    """Unlock on the game that fills the 52nd week slot (matches live post-game)."""
    played: dict[tuple[int, int], set[str]] = defaultdict(set)
    done: set[int] = set()
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        d = dt.date()
        week_monday = (d - timedelta(days=d.weekday())).isoformat()
        id_a, id_b = int(g["idA"]), int(g["idB"])

        for pid in (id_a, id_b):
            if eligible is not None and pid not in eligible:
                continue
            if pid in done:
                continue
            cal_year = _calendar_year_for_week_monday(week_monday)
            if cal_year is None:
                continue
            slots = set(_calendar_year_week_mondays(cal_year))
            if week_monday not in slots:
                continue
            key = (pid, cal_year)
            before = len(played[key])
            played[key].add(week_monday)
            if before == 51 and len(played[key]) == 52:
                done.add(pid)
                rows.append((pid, "year_in_heaven", dt, cal_year, gid))

    return rows


# --- period burst (crossing game = anchor) ---

DAY_BURST_THRESHOLDS: tuple[tuple[int, str, int], ...] = (
    (5, "hot_day", 5),
    (10, "marathon_day", 10),
    (20, "absurd_day", 20),
    (30, "ultra_day_30", 30),
)


def simulate_period_burst_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    day_count: dict[int, dict[str, int]] = defaultdict(lambda: defaultdict(int))
    month_count: dict[int, dict[str, int]] = defaultdict(lambda: defaultdict(int))
    done: set[tuple[int, str]] = set()
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        d = dt.date()
        day_key = d.isoformat()
        month_key = _month_key(d)
        id_a, id_b = int(g["idA"]), int(g["idB"])

        for pid in (id_a, id_b):
            if eligible is not None and pid not in eligible:
                continue
            day_count[pid][day_key] += 1
            month_count[pid][month_key] += 1
            dc = day_count[pid][day_key]
            mc = month_count[pid][month_key]

            for n, key, val in DAY_BURST_THRESHOLDS:
                if dc == n and (pid, key) not in done:
                    done.add((pid, key))
                    rows.append((pid, key, dt, val, gid))

            if mc == 50 and (pid, "grind_month") not in done:
                done.add((pid, "grind_month"))
                rows.append((pid, "grind_month", dt, 50, gid))

    return rows


# --- chrono (gen_milestone_chrono_sql.py) ---

SKIP_CHRONO_KEYS = frozenset({"peace_streak"})


def _monday_week_key(d: date) -> str:
    monday = d - timedelta(days=d.weekday())
    return monday.isoformat()


def _month_key(d: date) -> str:
    return f"{d.year:04d}-{d.month:02d}"


@dataclass
class PlayerChrono:
    games: int = 0
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


def _chrono_unlock(
    rows: list[MilestoneRow],
    pid: int,
    key: str,
    st: PlayerChrono,
    dt: datetime,
    gid: int,
    val: int,
) -> None:
    if key in SKIP_CHRONO_KEYS or key in st.done:
        return
    st.done.add(key)
    rows.append((pid, key, dt, val, gid))


def _day_close_achieved_at(day_key: str) -> datetime:
    d = date.fromisoformat(day_key)
    return datetime.combine(d + timedelta(days=1), datetime.min.time())


def _finalize_day(
    rows: list[MilestoneRow],
    pid: int,
    st: PlayerChrono,
    day_key: str,
    gid: int,
) -> None:
    outcomes = st.games_by_day.get(day_key, [])
    if len(outcomes) < 5:
        return
    close_at = _day_close_achieved_at(day_key)
    if all(o == "W" for o in outcomes):
        _chrono_unlock(rows, pid, "perfect_day", st, close_at, gid, 5)
    if all(o == "L" for o in outcomes):
        _chrono_unlock(rows, pid, "nightmare_day", st, close_at, gid, 5)


def simulate_chrono_milestones(games: list[dict[str, Any]]) -> list[MilestoneRow]:
    players: dict[int, PlayerChrono] = {}
    ratings: dict[int, float] = defaultdict(lambda: 1600.0)
    last_game: dict[int, datetime] = {}
    rows: list[MilestoneRow] = []

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
                _chrono_unlock(rows, opp, "newbie_welcomer", ost, dt, gid, 1)
                if gf >= 2:
                    _chrono_unlock(rows, opp, "generous", ost, dt, gid, 2)

            if st.current_day is not None and st.current_day != day_key:
                _finalize_day(rows, pid, st, st.current_day, st.last_gid or gid)
            st.current_day = day_key

            st.games += 1
            # “after 50+ career games” → NumberGames >= 51 on qualifying game
            if st.games >= 51 and gf == 0:
                _chrono_unlock(rows, pid, "rare_blank", st, dt, gid, 0)
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
            if st.score_streak == 10:
                _chrono_unlock(rows, pid, "on_the_scoresheet", st, dt, gid, 10)

            if drew:
                st.draw_streak += 1
            else:
                st.draw_streak = 0
            if st.draw_streak == 5:
                _chrono_unlock(rows, pid, "united_nations", st, dt, gid, 5)

            if won and margin == 1:
                st.win_margin_streak += 1
            else:
                st.win_margin_streak = 0
            if st.win_margin_streak == 5:
                _chrono_unlock(rows, pid, "knife_edge", st, dt, gid, 5)

            if lost and margin == 1:
                st.loss_margin_streak += 1
            else:
                st.loss_margin_streak = 0
            if st.loss_margin_streak == 5:
                _chrono_unlock(rows, pid, "unlucky", st, dt, gid, 5)

            if gf >= 10:
                st.merchant_streak += 1
            else:
                st.merchant_streak = 0
            if st.merchant_streak == 5:
                _chrono_unlock(rows, pid, "merchant_streak", st, dt, gid, 5)

            if gf == 10:
                st.exact_ten_streak += 1
            else:
                st.exact_ten_streak = 0
            if st.exact_ten_streak == 3:
                _chrono_unlock(rows, pid, "minimalist_merchant", st, dt, gid, 3)

            ratings[pid] = new_r if new_r > 0 else ratings[pid]
            top_id = giant_slayer_active_top_id(
                ratings, last_game, dt, in_game=(id_a, id_b)
            )
            if giant_slayer_qualifies(
                won=won, pid=pid, opp=opp, top_id=top_id, r_pre=r_pre, r_opp=r_opp
            ):
                _chrono_unlock(rows, pid, "giant_slayer", st, dt, gid, 1)

            y, m = map(int, month_key.split("-"))
            last = calendar.monthrange(y, m)[1]
            days = st.days_by_month[month_key]
            if len(days) >= last and all(day in days for day in range(1, last + 1)):
                _chrono_unlock(rows, pid, "monthly_regular", st, dt, gid, last)

            wk = _monday_week_key(d)
            st.week_days[wk].add(d.weekday())
            if len(st.week_days[wk]) == 7:
                _chrono_unlock(rows, pid, "daily_habit", st, dt, gid, 7)

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
                            _chrono_unlock(rows, pid, "weekly_regular", st, dt, gid, 13)
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
                        _chrono_unlock(rows, pid, "year_round", st, dt, gid, 12)
                        break

            st.last_dt = dt
            st.last_gid = gid

    for pid, st in players.items():
        if st.current_day is not None:
            _finalize_day(rows, pid, st, st.current_day, st.last_gid)

    appearance_eligible = {pid for pid, st in players.items() if st.games >= 1}
    return [r for r in rows if r[0] in appearance_eligible]


# --- tail (gen_milestone_tail_sql.py) ---

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


def _tail_unlock(
    rows: list[MilestoneRow],
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
    rows: list[MilestoneRow],
    pid: int,
    st: PlayerTail,
    dt: datetime,
    gid: int,
    specs: list[tuple[str, int, int]],
    current: int,
) -> None:
    for key, thresh, val in specs:
        if current == thresh:
            _tail_unlock(rows, pid, key, st, dt, gid, val)


def simulate_tail_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    players: dict[int, PlayerTail] = {}
    rows: list[MilestoneRow] = []

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
            if eligible is not None and pid not in eligible:
                continue
            st = players.setdefault(pid, PlayerTail())

            st.games += 1
            _check_thresholds(rows, pid, st, dt, gid, GAME_N, st.games)

            if gf >= 1 and st.goals_for == 0:
                _tail_unlock(rows, pid, "first_goal", st, dt, gid, 1)

            if won:
                if st.wins == 0:
                    _tail_unlock(rows, pid, "first_victory", st, dt, gid, 1)
                st.wins += 1
                if st.wins == 100:
                    _tail_unlock(rows, pid, "century_of_wins", st, dt, gid, 100)
            elif drew:
                if st.draws == 0:
                    _tail_unlock(rows, pid, "first_handshake", st, dt, gid, 1)
                st.draws += 1
                if st.draws == 10:
                    _tail_unlock(rows, pid, "ten_draws", st, dt, gid, 10)
            elif lost:
                if st.losses == 0:
                    _tail_unlock(rows, pid, "welcome_to_the_ladder", st, dt, gid, 1)
                st.losses += 1
                if st.losses == 100:
                    _tail_unlock(rows, pid, "battle_scarred", st, dt, gid, 100)

            prev_goals = st.goals_for
            st.goals_for += gf
            if prev_goals < 100 <= st.goals_for:
                _tail_unlock(rows, pid, "hundred_goals", st, dt, gid, 100)
            if prev_goals < 1000 <= st.goals_for:
                _tail_unlock(rows, pid, "thousand_goal_club", st, dt, gid, 1000)

            if ga_c == 0:
                st.clean_sheets += 1
                if st.clean_sheets == 1:
                    _tail_unlock(rows, pid, "first_shutout", st, dt, gid, 1)
                if st.clean_sheets == 25:
                    _tail_unlock(rows, pid, "fortress_builder", st, dt, gid, 25)
                if st.clean_sheets == 50:
                    _tail_unlock(rows, pid, "clean_sheet_artist", st, dt, gid, 50)
                if gf > 0 and opp not in st.cs_opponents:
                    st.cs_opponents.add(opp)
                    _check_thresholds(
                        rows, pid, st, dt, gid, SPECIAL_CS_OPP, len(st.cs_opponents)
                    )

            if opp not in st.opponents:
                st.opponents.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, OPPONENT_N[:4], len(st.opponents))

            if won and opp not in st.victims:
                st.victims.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, OPPONENT_N[4:6], len(st.victims))

            if lost and opp not in st.culprits:
                st.culprits.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, OPPONENT_N[6:7], len(st.culprits))

            if gf >= 10 and opp not in st.dd_opponents:
                st.dd_opponents.add(opp)
                _check_thresholds(rows, pid, st, dt, gid, SPECIAL_DD_OPP, len(st.dd_opponents))

            st.pair_games[opp] += 1
            _check_thresholds(rows, pid, st, dt, gid, PAIR_GAMES, st.pair_games[opp])

            if won:
                st.pair_wins[opp] += 1
                _check_thresholds(rows, pid, st, dt, gid, PAIR_WINS, st.pair_wins[opp])

    return rows


@dataclass
class _DayPlayStreak:
    length: int = 0
    anchor: str = ""
    done_100: bool = False


def simulate_play_streak_100_milestones(
    games: list[dict[str, Any]],
    eligible: set[int] | None = None,
) -> list[MilestoneRow]:
    """UTC day play streak — unlock on the game that extends the run to 100 days."""
    players: dict[int, _DayPlayStreak] = {}
    rows: list[MilestoneRow] = []

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if not isinstance(dt, datetime):
            continue
        day = dt.date().isoformat()
        id_a, id_b = int(g["idA"]), int(g["idB"])

        for pid in (id_a, id_b):
            if eligible is not None and pid not in eligible:
                continue
            st = players.setdefault(pid, _DayPlayStreak())
            if st.done_100:
                continue

            anchor = st.anchor
            if anchor == "":
                st.length = 1
                st.anchor = day
                continue

            if day == anchor:
                continue

            expected = (date.fromisoformat(anchor) + timedelta(days=1)).isoformat()
            if day == expected:
                st.length += 1
                st.anchor = day
                if st.length == 100:
                    st.done_100 = True
                    rows.append((pid, "play_streak_100", dt, 100, gid))
            else:
                st.length = 1
                st.anchor = day

    return rows
