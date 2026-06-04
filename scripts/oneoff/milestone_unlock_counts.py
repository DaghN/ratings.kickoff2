#!/usr/bin/env python3
"""
Phase 2 milestone difficulty probe — read-only counts (no DB writes).

Denominators:
  - >=1 rated game (all who tried the ladder)
  - >=20 rated games (veteran / design population)

Output: data/scratch/milestone_unlock_counts.json + optional theme-doc patch.

Usage (repo root):
  python scripts/oneoff/milestone_unlock_counts.py --write-doc
  python scripts/oneoff/milestone_unlock_counts.py --doc-only --write-doc
"""
from __future__ import annotations

import argparse
import calendar
import json
import logging
import re
import sys
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta, timezone
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

log = logging.getLogger("milestone_counts")

THEME_DOC = _REPO / "docs" / "milestones-want-maybe-by-theme.md"
CURATED_DOC = _REPO / "docs" / "milestones-tier-curated.md"
CURATED_META = _REPO / "data" / "milestones_curated_meta.json"
DEFINITIONS_SEED = _REPO / "site" / "public_html" / "ops" / "data" / "milestones_definitions_seed.json"
JSON_OUT = _REPO / "data" / "scratch" / "milestone_unlock_counts.json"

TIER_CHART_TOKEN = {
    "legendary": "holo",
    "accomplished": "amber",
    "dedicated": "chrome",
    "aspirational": "pitch",
}

ELIGIBLE_WHERE = "NumberGames >= 1"
VETERAN_WHERE = "NumberGames >= 20"

# Keys removed from catalog (unstable or out of scope) — still in catalog as discard
EXCLUDED_KEYS = frozenset(
    {
        "top_ten_sweep",
        "long_sleep_loud_wakeup",
        "nine_eight_thriller",
        "double_digit_handshake",
        "club_5000",
        "back_in_the_game",
        "league_daily_points_winner",
        "nemesis",
        "elite_customer",
        "podium_month",
        "still_here_years_later",
        "league_monthly_activity_winner",
        "period_champion",
        "six_goal_draw",
    }
)

# Dagh locked picks (May 2026) — probe bands overridden
TIER_LEGENDARY = frozenset(
    {
        "century_of_rivals",
        "united_nations",
        "league_wins_500",
        "merchant_trade_fair",
        "minimalist_merchant",
        "monthly_regular",
        "league_yearly_points_winner",
        "league_yearly_activity_winner",
        "merchant_denied",
        "leaky_merchant",
        "unlucky",
        "knife_edge",
        "merchant_streak",
        "club_10000",
        "filthy_fifteen",
        "club_2300",
        "ultra_day_30",
        "win_streak_30",
    }
)
TIER_ACCOMPLISHED = frozenset(
    {
        "league_yearly_activity_medal",
        "league_yearly_points_medal",
        "league_wins_100",
        "twenty_goal_chaos",
        "activity_king",
        "diversity_merchant",
        "travelling_salesman",
        "rampage",
        "ruthless",
        "giant_slayer",
        "league_wins_50",
        "perfect_storm",
        "league_weekly_points_winner",
        "absurd_day",
        "club_2000",
        "fifty_faces",
        "league_monthly_points_medal",
        "dozen_dash",
        "survivor",
        "league_weekly_activity_winner",
        "league_monthly_points_winner",
    }
)


@dataclass
class CountResult:
    unlock: int
    method: str
    band: str = ""


@dataclass
class PlayerChrono:
    games: int = 0
    last_date: date | None = None
    years: set[int] = field(default_factory=set)
    score_streak: int = 0
    draw_streak: int = 0
    win_margin_streak: int = 0
    loss_margin_streak: int = 0
    merchant_streak: int = 0
    exact_ten_streak: int = 0
    dd_opponents: set[int] = field(default_factory=set)
    cs_opponents: set[int] = field(default_factory=set)
    days_by_month: dict[str, set[int]] = field(default_factory=lambda: defaultdict(set))
    games_by_day: dict[str, list[str]] = field(default_factory=lambda: defaultdict(list))
    games_by_month: dict[str, int] = field(default_factory=lambda: defaultdict(int))
    months_active: list[str] = field(default_factory=list)
    unlocked: set[str] = field(default_factory=set)


def _pct(unlock: int, eligible: int) -> str:
    if eligible <= 0:
        return "—"
    return f"{100.0 * unlock / eligible:.1f}%"


def _pct_vet(unlock: int, veterans: int) -> str:
    if veterans <= 0:
        return "—"
    p = 100.0 * unlock / veterans
    if p >= 100.0:
        return f"{p:.0f}%+"
    return f"{p:.1f}%"


def suggest_band(unlock: int, veterans: int) -> str:
    """Auto-hint for tier pass (not final assignment). Pitch floor accepts >=80% vet."""
    if veterans <= 0:
        return "—"
    vp = 100.0 * unlock / veterans
    if vp >= 80.0 or unlock >= veterans:
        return "pitch"
    if vp < 3.0:
        return "ultra-rare?"
    if vp < 14.0:
        return "legendary?"
    if vp < 32.0:
        return "accomplished?"
    return "dedicated?"


def count_veterans(cur) -> int:
    return count_pt(cur, "NumberGames >= 20")


def finalize_results(
    results: dict[str, CountResult], veterans: int
) -> dict[str, CountResult]:
    out: dict[str, CountResult] = {}
    for key, r in results.items():
        if key in EXCLUDED_KEYS:
            continue
        out[key] = CountResult(
            r.unlock,
            r.method,
            suggest_band(r.unlock, veterans),
        )
    return out


def table_exists(cur, name: str) -> bool:
    cur.execute(
        "SELECT 1 FROM information_schema.tables "
        "WHERE table_schema = DATABASE() AND table_name = %s LIMIT 1",
        (name,),
    )
    return cur.fetchone() is not None


def count_pt(cur, sql_extra: str) -> int:
    cur.execute(
        f"SELECT COUNT(*) AS n FROM playertable WHERE {ELIGIBLE_WHERE} AND ({sql_extra})"
    )
    row = cur.fetchone()
    return int(row["n"])


def count_distinct_players_sql(cur, sql: str) -> int:
    cur.execute(sql)
    row = cur.fetchone()
    return int(row["n"])


def playertable_counts(cur) -> dict[str, CountResult]:
    """Thresholds on playertable (proxy: career max / totals)."""
    specs: list[tuple[str, str, str]] = [
        ("debut", "NumberGames >= 1", "playertable"),
        ("persistence", "NumberGames >= 10", "playertable NumberGames>=10"),
        ("established_20", "NumberGames >= 20", "playertable"),
        ("first_victory", "NumberWins >= 1", "playertable"),
        ("first_goal", "GoalsFor >= 1", "playertable"),
        ("first_handshake", "NumberDraws >= 1", "playertable"),
        ("welcome_to_the_ladder", "NumberLosses >= 1", "playertable"),
        ("first_shutout", "CleanSheets >= 1", "playertable"),
        ("half_century_50", "NumberGames >= 50", "playertable"),
        ("centurion_100", "NumberGames >= 100", "playertable"),
        ("marathoner_250", "NumberGames >= 250", "playertable"),
        ("club_500", "NumberGames >= 500", "playertable"),
        ("millennium_merchant_1000", "NumberGames >= 1000", "playertable"),
        ("club_10000", "NumberGames >= 10000", "playertable"),
        ("club_2300", "PeakRating >= 2300", "playertable PeakRating"),
        ("ten_wins", "NumberWins >= 10", "playertable"),
        ("century_of_wins", "NumberWins >= 100", "playertable"),
        ("battle_scarred", "NumberLosses >= 100", "playertable"),
        ("ten_draws", "NumberDraws >= 10", "playertable"),
        ("hundred_goals", "GoalsFor >= 100", "playertable"),
        ("thousand_goal_club", "GoalsFor >= 1000", "playertable"),
        ("fortress_builder", "CleanSheets >= 25", "playertable"),
        ("clean_sheet_artist", "CleanSheets >= 50", "playertable"),
        ("ten_opponents", "DifferentOpponents >= 10", "playertable"),
        ("wide_net", "DifferentOpponents >= 25", "playertable"),
        ("fifty_faces", "DifferentOpponents >= 50", "playertable"),
        ("century_of_rivals", "DifferentOpponents >= 100", "playertable"),
        ("five_victims", "DifferentVictims >= 5", "playertable"),
        ("twenty_five_victims", "DifferentVictims >= 25", "playertable"),
        ("ten_culprits", "DifferentCulprits >= 10", "playertable"),
        ("club_1700", "PeakRating >= 1700", "playertable PeakRating"),
        ("club_1800", "PeakRating >= 1800", "playertable PeakRating"),
        ("club_1900", "PeakRating >= 1900", "playertable PeakRating"),
        ("club_2000", "PeakRating >= 2000", "playertable PeakRating"),
        ("elite_altitude", "PeakRating >= 2100", "playertable PeakRating"),
        ("win_hat_trick", "LongestWinningStreak >= 3", "playertable longest streak proxy"),
        ("ten_wins_straight", "LongestWinningStreak >= 10", "playertable longest streak proxy"),
        ("win_streak_30", "LongestWinningStreak >= 30", "playertable longest streak proxy"),
        ("rampage", "LongestWinningStreak >= 15", "playertable longest streak proxy"),
        ("cold_streak", "LongestLosingStreak >= 5", "playertable longest streak proxy"),
        ("win_drought", "LongestNonWinStreak >= 10", "playertable longest streak proxy"),
    ]
    out: dict[str, CountResult] = {}
    for key, cond, method in specs:
        out[key] = CountResult(count_pt(cur, cond), method)
    return out


def ratedresults_exists_counts(cur) -> dict[str, CountResult]:
    """Per-player EXISTS-style feats via UNION sides subquery."""
    base = """
        SELECT COUNT(*) AS n FROM (
          SELECT DISTINCT pid FROM (
            SELECT idA AS pid, GoalsA AS gf, GoalsB AS ga, ActualScore AS sc,
                   RatingA AS r_pre, RatingB AS r_opp, idB AS oid, `Date` AS d
            FROM ratedresults
            UNION ALL
            SELECT idB, GoalsB, GoalsA,
                   CASE WHEN ActualScore = 1 THEN 0 WHEN ActualScore = 0 THEN 1 ELSE 0.5 END,
                   RatingB, RatingA, idA, `Date`
            FROM ratedresults
          ) s
          WHERE pid IN (SELECT ID FROM playertable WHERE {eligible})
          AND ({cond})
        ) t
    """.replace("{eligible}", ELIGIBLE_WHERE)

    specs: list[tuple[str, str, str]] = [
        ("brace", "gf >= 2", "ratedresults any game"),
        ("hat_trick", "gf >= 3", "ratedresults any game"),
        ("five_goal_frenzy", "gf >= 5", "ratedresults any game"),
        ("eight_goal_storm", "gf >= 8", "ratedresults any game"),
        ("dd_merchant_10", "gf >= 10", "ratedresults any game"),
        ("dozen_dash", "gf >= 12", "ratedresults any game"),
        ("filthy_fifteen", "gf >= 15", "ratedresults any game"),
        ("victim_of_commerce", "ga >= 10", "ratedresults any game"),
        ("minimalist", "sc = 1 AND gf = 1 AND ga = 0", "ratedresults any game"),
        ("perfect_storm", "sc = 1 AND gf = 10 AND ga = 0", "ratedresults any game"),
        ("battle_hardened", "sc = 0.5 AND gf + ga >= 10 AND gf = ga", "ratedresults 5-5+ draw"),
        ("survivor", "sc = 1 AND ga >= 7", "ratedresults any game"),
        ("six_goal_draw", "sc = 0.5 AND gf + ga >= 6", "ratedresults any game"),
        ("goal_fest_draw", "sc = 0.5 AND gf + ga >= 14", "ratedresults any game"),
        ("comfortable", "sc = 1 AND (gf - ga) >= 5", "ratedresults any game"),
        ("ruthless", "sc = 1 AND (gf - ga) >= 10", "ratedresults any game"),
        ("hard_lesson", "sc = 0 AND (ga - gf) >= 10", "ratedresults any game"),
        ("twenty_goal_chaos", "gf + ga >= 20", "ratedresults any game"),
        ("massive_upset", "sc = 1 AND (r_opp - r_pre) >= 500", "ratedresults pre-game ratings"),
        ("merchant_denied", "sc = 0 AND gf = 9 AND ga = 10", "ratedresults any game"),
        ("merchant_trade_fair", "sc = 0.5 AND gf = 10 AND ga = 10", "ratedresults 10-10 draw"),
        ("leaky_merchant", "sc = 1 AND gf >= 10 AND ga = 9", "ratedresults any game"),
    ]
    out: dict[str, CountResult] = {}
    for key, cond, method in specs:
        cur.execute(base.format(cond=cond))
        out[key] = CountResult(int(cur.fetchone()["n"]), method)
    return out


def matchup_counts(cur) -> dict[str, CountResult]:
    if not table_exists(cur, "player_matchup_summary"):
        return {}
    specs = [
        ("regular_customer", "wins", 10, "player_matchup_summary max vs one opponent"),
        ("bogeyman", "wins", 20, "player_matchup_summary"),
        ("ten_match_saga", "games", 10, "player_matchup_summary"),
        ("lifetime_rivalry", "games", 50, "player_matchup_summary"),
    ]
    out: dict[str, CountResult] = {}
    for key, col, thresh, method in specs:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n FROM (
              SELECT player_id FROM player_matchup_summary
              WHERE player_id IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
              GROUP BY player_id
              HAVING MAX({col}) >= {thresh}
            ) t
            """
        )
        out[key] = CountResult(int(cur.fetchone()["n"]), method)
    return out


def league_counts(cur) -> dict[str, CountResult]:
    if not table_exists(cur, "player_league_award"):
        return {}
    out: dict[str, CountResult] = {}
    mapping = [
        ("period_champion", "is_winner = 1", "any league win"),
        ("moment_of_glory", "is_winner = 1 AND league_kind = 'points' AND period_type = 'day'", "daily points win"),
        ("podium_month", "finish_rank <= 3 AND period_type = 'month'", "monthly podium"),
        ("activity_king", "is_winner = 1 AND league_kind = 'activity' AND period_type = 'month'", "monthly activity win"),
        ("league_daily_points_medal", "league_kind='points' AND period_type='day' AND finish_rank<=3", ""),
        ("league_daily_points_winner", "league_kind='points' AND period_type='day' AND is_winner=1", ""),
        ("league_daily_activity_medal", "league_kind='activity' AND period_type='day' AND finish_rank<=3", ""),
        ("league_daily_activity_winner", "league_kind='activity' AND period_type='day' AND is_winner=1", ""),
        ("league_weekly_points_medal", "league_kind='points' AND period_type='week' AND finish_rank<=3", ""),
        ("league_weekly_points_winner", "league_kind='points' AND period_type='week' AND is_winner=1", ""),
        ("league_weekly_activity_medal", "league_kind='activity' AND period_type='week' AND finish_rank<=3", ""),
        ("league_weekly_activity_winner", "league_kind='activity' AND period_type='week' AND is_winner=1", ""),
        ("league_monthly_points_medal", "league_kind='points' AND period_type='month' AND finish_rank<=3", ""),
        ("league_monthly_points_winner", "league_kind='points' AND period_type='month' AND is_winner=1", ""),
        ("league_monthly_activity_medal", "league_kind='activity' AND period_type='month' AND finish_rank<=3", ""),
        ("league_monthly_activity_winner", "league_kind='activity' AND period_type='month' AND is_winner=1", ""),
        ("league_yearly_points_medal", "league_kind='points' AND period_type='year' AND finish_rank<=3", ""),
        ("league_yearly_points_winner", "league_kind='points' AND period_type='year' AND is_winner=1", ""),
        ("league_yearly_activity_medal", "league_kind='activity' AND period_type='year' AND finish_rank<=3", ""),
        ("league_yearly_activity_winner", "league_kind='activity' AND period_type='year' AND is_winner=1", ""),
    ]
    for key, where, note in mapping:
        cur.execute(
            f"""
            SELECT COUNT(DISTINCT player_id) AS n FROM player_league_award
            WHERE player_id IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
              AND {where}
            """
        )
        method = f"player_league_award ({note or where})"
        out[key] = CountResult(int(cur.fetchone()["n"]), method)

    if table_exists(cur, "player_league_totals"):
        for key, thresh in [
            ("league_wins_10", 10),
            ("league_wins_50", 50),
            ("league_wins_100", 100),
            ("league_wins_500", 500),
        ]:
            cur.execute(
                f"""
                SELECT COUNT(*) AS n FROM player_league_totals
                WHERE player_id IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
                  AND wins >= {thresh}
                """
            )
            out[key] = CountResult(int(cur.fetchone()["n"]), f"player_league_totals.wins>={thresh}")
    return out


def clean_sheet_spread_count(cur) -> CountResult:
    if not table_exists(cur, "player_matchup_summary"):
        return CountResult(0, "missing player_matchup_summary")
    cur.execute(
        f"""
        SELECT COUNT(*) AS n FROM (
          SELECT r.pid FROM (
            SELECT idA AS pid, idB AS oid FROM ratedresults WHERE GoalsB = 0
            UNION ALL
            SELECT idB, idA FROM ratedresults WHERE GoalsA = 0
          ) r
          WHERE pid IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
          GROUP BY pid
          HAVING COUNT(DISTINCT oid) >= 10
        ) t
        """
    )
    return CountResult(
        int(cur.fetchone()["n"]),
        "ratedresults distinct opponents where goals_against=0",
    )


def dd_distinct_opponents_count(cur, min_opponents: int) -> CountResult:
    cur.execute(
        f"""
        SELECT COUNT(*) AS n FROM (
          SELECT pid FROM (
            SELECT idA AS pid, idB AS oid FROM ratedresults WHERE GoalsA >= 10
            UNION ALL
            SELECT idB, idA FROM ratedresults WHERE GoalsB >= 10
          ) r
          WHERE pid IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
          GROUP BY pid
          HAVING COUNT(DISTINCT oid) >= {int(min_opponents)}
        ) t
        """
    )
    label = (
        f"ratedresults distinct per-game DD opponents (>={min_opponents})"
    )
    return CountResult(int(cur.fetchone()["n"]), label)


def travelling_salesman_count(cur) -> CountResult:
    return dd_distinct_opponents_count(cur, 10)


def period_burst_counts(cur) -> dict[str, CountResult]:
    """Day/month bursts from player_period_games if present, else ratedresults."""
    out: dict[str, CountResult] = {}
    if table_exists(cur, "player_period_games"):
        for key, min_g, ptype in [
            ("hot_day", 5, "day"),
            ("marathon_day", 10, "day"),
            ("absurd_day", 20, "day"),
            ("ultra_day_30", 30, "day"),
        ]:
            cur.execute(
                f"""
                SELECT COUNT(*) AS n FROM (
                  SELECT player_id FROM player_period_games
                  WHERE period_type = %s
                    AND player_id IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
                  GROUP BY player_id
                  HAVING MAX(games) >= %s
                ) t
                """,
                (ptype, min_g),
            )
            out[key] = CountResult(int(cur.fetchone()["n"]), "player_period_games day max")
        cur.execute(
            f"""
            SELECT COUNT(*) AS n FROM (
              SELECT player_id FROM player_period_games
              WHERE period_type = 'month'
                AND player_id IN (SELECT ID FROM playertable WHERE {ELIGIBLE_WHERE})
              GROUP BY player_id
              HAVING MAX(games) >= 50
            ) t
            """
        )
        out["grind_month"] = CountResult(int(cur.fetchone()["n"]), "player_period_games month max")
    return out


def _monday_week_key(d: date) -> str:
    monday = d - timedelta(days=d.weekday())
    return monday.isoformat()


def _month_key(d: date) -> str:
    return f"{d.year:04d}-{d.month:02d}"


def run_chronological(cur) -> dict[str, CountResult]:
    cur.execute("SET time_zone = '+00:00'")
    cur.execute(
        """
        SELECT id, `Date`, idA, idB, GoalsA, GoalsB, ActualScore,
               RatingA, RatingB, NewRatingA, NewRatingB
        FROM ratedresults
        ORDER BY `Date` ASC, id ASC
        """
    )
    games = cur.fetchall()
    players: dict[int, PlayerChrono] = {}
    welcomers: set[int] = set()
    generous: set[int] = set()
    rare_blank: set[int] = set()
    back_in_game: set[int] = set()
    long_sleep: set[int] = set()
    still_here: set[int] = set()
    on_scoresheet: set[int] = set()
    knife_edge: set[int] = set()
    unlucky: set[int] = set()
    merchant_streak: set[int] = set()
    minimalist_merchant: set[int] = set()
    giant_slayer: set[int] = set()
    peace_streak: set[int] = set()
    united_nations: set[int] = set()
    perfect_day: set[int] = set()
    nightmare_day: set[int] = set()
    daily_habit: set[int] = set()
    weekly_regular: set[int] = set()
    monthly_regular: set[int] = set()
    year_round: set[int] = set()

    ratings: dict[int, float] = defaultdict(lambda: 1600.0)
    last_game: dict[int, datetime] = {}

    for g in games:
        gid = int(g["id"])
        dt = g["Date"]
        if isinstance(dt, datetime):
            d = dt.date()
        else:
            d = dt
        if not isinstance(dt, datetime):
            continue
        id_a, id_b = int(g["idA"]), int(g["idB"])
        last_game[id_a] = dt
        last_game[id_b] = dt
        ga, gb = int(g["GoalsA"] or 0), int(g["GoalsB"] or 0)
        sc = float(g["ActualScore"])
        kickoff_top_id = giant_slayer_active_top_id(
            ratings, last_game, dt, in_game=(id_a, id_b)
        )

        for pid, gf, ga_c, opp, r_pre, r_opp, new_r in (
            (id_a, ga, gb, id_b, float(g["RatingA"] or 1600), float(g["RatingB"] or 1600), float(g["NewRatingA"] or 0)),
            (id_b, gb, ga, id_a, float(g["RatingB"] or 1600), float(g["RatingA"] or 1600), float(g["NewRatingB"] or 0)),
        ):
            st = players.setdefault(pid, PlayerChrono())
            if st.games == 0 and opp > 0:
                # opponent was in debut game of pid
                if gf >= 2:
                    generous.add(opp)
                welcomers.add(opp)
            st.games += 1
            if st.games >= 51 and gf == 0:
                rare_blank.add(pid)
            if st.last_date is not None:
                gap = (d - st.last_date).days
                if gap >= 365:
                    back_in_game.add(pid)
                if gap >= 365 * 3:
                    long_sleep.add(pid)
            st.last_date = d
            st.years.add(d.year)
            day_key = d.isoformat()
            month_key = _month_key(d)
            st.days_by_month[month_key].add(d.day)
            st.games_by_day[day_key].append("W" if (sc == 1 and pid == id_a) or (sc == 0 and pid == id_b) else ("D" if sc == 0.5 else "L"))
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
                on_scoresheet.add(pid)

            if drew:
                st.draw_streak += 1
            else:
                st.draw_streak = 0
            if st.draw_streak >= 3:
                peace_streak.add(pid)
            if st.draw_streak >= 5:
                united_nations.add(pid)

            if won and margin == 1:
                st.win_margin_streak += 1
            else:
                st.win_margin_streak = 0
            if st.win_margin_streak >= 5:
                knife_edge.add(pid)

            if lost and margin == 1:
                st.loss_margin_streak += 1
            else:
                st.loss_margin_streak = 0
            if st.loss_margin_streak >= 5:
                unlucky.add(pid)

            if gf >= 10:
                st.merchant_streak += 1
                st.dd_opponents.add(opp)
            else:
                st.merchant_streak = 0
            if st.merchant_streak >= 5:
                merchant_streak.add(pid)

            if gf == 10:
                st.exact_ten_streak += 1
            else:
                st.exact_ten_streak = 0
            if st.exact_ten_streak >= 3:
                minimalist_merchant.add(pid)

            if ga_c == 0:
                st.cs_opponents.add(opp)

            ratings[pid] = new_r if new_r > 0 else ratings[pid]
            if giant_slayer_qualifies(
                won=won,
                pid=pid,
                opp=opp,
                top_id=kickoff_top_id,
                r_pre=r_pre,
                r_opp=r_opp,
            ):
                giant_slayer.add(pid)

        # month list for year_round - track after both sides
    for pid, st in players.items():
        if len(st.years) >= 2:
            yrs = sorted(st.years)
            for y in yrs:
                if (y + 5) in st.years:
                    still_here.add(pid)
                    break
        # monthly_regular: all days in some month
        for mk, days in st.days_by_month.items():
            y, m = map(int, mk.split("-"))
            last = calendar.monthrange(y, m)[1]
            if len(days) >= last and all(day in days for day in range(1, last + 1)):
                monthly_regular.add(pid)
                break
        # year_round: 12 consecutive months with >=1 game
        months_sorted = sorted({mk for mk in st.games_by_month if st.games_by_month[mk] > 0})
        if len(months_sorted) >= 12:
            for i in range(len(months_sorted) - 11):
                ok = True
                y0, m0 = map(int, months_sorted[i].split("-"))
                for j in range(12):
                    ym = y0 + (m0 - 1 + j) // 12
                    mm = (m0 - 1 + j) % 12 + 1
                    key = f"{ym:04d}-{mm:02d}"
                    if key not in st.games_by_month or st.games_by_month[key] < 1:
                        ok = False
                        break
                if ok:
                    year_round.add(pid)
                    break
        # daily_habit: 7 days Mon-Sun in one Monday-start week
        weeks: dict[str, set[int]] = defaultdict(set)
        for mk, days in st.days_by_month.items():
            y, m = map(int, mk.split("-"))
            for day in days:
                d0 = date(y, m, day)
                wk = _monday_week_key(d0)
                weeks[wk].add(d0.weekday())
        for wk_days in weeks.values():
            if wk_days >= set(range(7)):
                daily_habit.add(pid)
                break
        # weekly_regular: >=1 game every ISO week for 13 consecutive weeks (~3 months)
        week_keys = sorted({ _monday_week_key(date(int(mk.split('-')[0]), int(mk.split('-')[1]), d))
                            for mk, days in st.days_by_month.items()
                            for d in days })
        if len(week_keys) >= 13:
            for i in range(len(week_keys) - 12):
                block = week_keys[i : i + 13]
                ok = True
                for j in range(1, 13):
                    d0 = date.fromisoformat(block[j - 1])
                    d1 = date.fromisoformat(block[j])
                    if (d1 - d0).days > 10:
                        ok = False
                        break
                if ok:
                    weekly_regular.add(pid)
                    break
        # perfect / nightmare day
        for dk, outcomes in st.games_by_day.items():
            if len(outcomes) >= 5 and all(o == "W" for o in outcomes):
                perfect_day.add(pid)
            if len(outcomes) >= 5 and all(o == "L" for o in outcomes):
                nightmare_day.add(pid)

    eligible_players = {pid for pid, st in players.items() if st.games >= 1}

    def cnt(s: set[int]) -> int:
        return len(s & eligible_players)

    return {
        "newbie_welcomer": CountResult(cnt(welcomers), "chronological debut opponent"),
        "generous": CountResult(cnt(generous), "chronological debut opp, newbie scored 2+"),
        "rare_blank": CountResult(cnt(rare_blank), "chronological"),
        "back_in_the_game": CountResult(cnt(back_in_game), "chronological gap >=365d"),
        "long_sleep_loud_wakeup": CountResult(cnt(long_sleep), "chronological gap >=3y"),
        "still_here_years_later": CountResult(cnt(still_here), "chronological years Y and Y+5"),
        "on_the_scoresheet": CountResult(cnt(on_scoresheet), "chronological 10 scored in row"),
        "knife_edge": CountResult(cnt(knife_edge), "chronological"),
        "unlucky": CountResult(cnt(unlucky), "chronological"),
        "merchant_streak": CountResult(cnt(merchant_streak), "chronological"),
        "minimalist_merchant": CountResult(cnt(minimalist_merchant), "chronological"),
        "giant_slayer": CountResult(
            cnt(giant_slayer), "kickoff beat #1 active (365d rolling UTC)"
        ),
        "perfect_day": CountResult(cnt(perfect_day), "chronological UTC day"),
        "nightmare_day": CountResult(cnt(nightmare_day), "chronological UTC day"),
        "daily_habit": CountResult(cnt(daily_habit), "chronological Mon-Sun week"),
        "weekly_regular": CountResult(cnt(weekly_regular), "chronological ~13 weeks"),
        "monthly_regular": CountResult(cnt(monthly_regular), "chronological full month days"),
        "year_round": CountResult(cnt(year_round), "chronological 12 consec months"),
        "peace_streak": CountResult(cnt(peace_streak), "chronological 3 draws row"),
        "united_nations": CountResult(cnt(united_nations), "chronological 5 draws row"),
    }


_DATA_MARKERS = ("✅", "🔶", "🔴")
_KEY_ROW = re.compile(r"^\| (?:want|maybe) \| `([^`]+)` \|")
_STRIP_STATS = re.compile(r"\s\|\s*\d+\s\|\s*[\d.%+]+%.*$")

_COUNT_HEADER = " | **Unlock** | **%≥1g** | **%vet** | **Band** | Method |"
_COUNT_SEP = " |--------:|--------:|--------:|--------|--------|"
_COUNT_ROW_RE = re.compile(
    r"^\| (?:want|maybe) \| `([^`]+)` \| ([^|]*) \| ([^|]*) \| [^|]* \| "
    r"(\d+) \| [^|]* \| ([\d.]+%\+?) \| "
    r"(pitch|dedicated\?|accomplished\?|legendary\?|ultra-rare\?) \|"
)
# League / short tables: Key | Context or rule | Data | counts…
_COUNT_ROW_SHORT_RE = re.compile(
    r"^\| (?:want|maybe) \| `([^`]+)` \| ([^|]*) \| [^|]* \| "
    r"(\d+) \| [^|]* \| ([\d.]+%\+?) \| "
    r"(pitch|dedicated\?|accomplished\?|legendary\?|ultra-rare\?) \|"
)


def _row_prefix(line: str) -> str | None:
    """Keep columns through Data (✅/🔶/🔴); drop old count columns."""
    line = _STRIP_STATS.sub("", line)
    parts = [p.strip() for p in line.split("|")]
    if len(parts) < 4:
        return None
    kept: list[str] = []
    for i in range(1, len(parts)):
        kept.append(parts[i])
        if any(m in parts[i] for m in _DATA_MARKERS):
            return "| " + " | ".join(kept) + " |"
    return None


def build_histogram(results: dict[str, CountResult], veterans: int) -> str:
    bands: dict[str, list[str]] = defaultdict(list)
    for key, r in sorted(results.items()):
        bands[r.band or "?"].append(key)

    lines = [
        "## Tier sizing hints (auto-band from veteran %)",
        "",
        "Target garden sizes (design): **pitch** + **dedicated** = bulk; "
        "**accomplished** ~15–20; **legendary** ~10–15. "
        "**Band** column is a probe hint only — flavor and caps override.",
        "",
        "| Band hint | Count | Target tier size (design) |",
        "|-----------|------:|---------------------------|",
    ]
    targets = {
        "pitch": "large (rarity floor; many at 80%+ vet)",
        "dedicated?": "large (~35–50)",
        "accomplished?": "~15–20 keystones",
        "legendary?": "~10–15",
        "ultra-rare?": "legendary or nudge threshold",
    }
    order = ["pitch", "dedicated?", "accomplished?", "legendary?", "ultra-rare?", "—", "?"]
    for band in order:
        keys = bands.get(band, [])
        if not keys and band != "?":
            continue
        label = targets.get(band, "")
        lines.append(f"| {band} | {len(keys)} | {label} |")
    for band, keys in sorted(bands.items()):
        if band not in order:
            lines.append(f"| {band} | {len(keys)} | (review) |")

    lines.extend(
        [
            "",
            f"**Veteran denominator:** `NumberGames >= 20` → **{veterans}** players. "
            f"**%vet** can exceed 100% (unlock includes sub-20-game players); "
            "that still means **pitch** floor, not discard.",
            "",
        ]
    )
    return "\n".join(lines)


def _parse_vet_pct(pct_vet: str) -> float:
    return float(pct_vet.replace("%+", "").replace("%", ""))


def load_curated_meta() -> tuple[dict[str, dict[str, object]], dict[str, str]]:
    """Display names, name scores (1–5), and optional key renames for curated doc."""
    if not CURATED_META.is_file():
        return {}, {}
    raw = json.loads(CURATED_META.read_text(encoding="utf-8"))
    renames = {str(k): str(v) for k, v in raw.pop("_key_renames", {}).items()}
    meta: dict[str, dict[str, object]] = {}
    for key, val in raw.items():
        if key.startswith("_") or not isinstance(val, dict):
            continue
        meta[str(key)] = val
    return meta, renames


def apply_curated_key_renames(results: dict[str, CountResult], renames: dict[str, str]) -> None:
    for old, new in renames.items():
        if old in results and new not in results:
            results[new] = results.pop(old)


def merge_curated_row(row: dict[str, object], meta: dict[str, dict[str, object]]) -> dict[str, object]:
    key = str(row["key"])
    m = meta.get(key)
    if m:
        if "display" in m:
            row["display"] = str(m["display"])
        if "score" in m:
            row["name_score"] = int(m["score"])
        if "rule_short" in m:
            row["rule"] = str(m["rule_short"])
    return row


def _display_plain(md: str) -> str:
    return re.sub(r"\*\*([^*]+)\*\*", r"\1", md.strip())


def tier_band_for_key(key: str, theme_band: str) -> str:
    if key in TIER_LEGENDARY:
        return "legendary"
    if key in TIER_ACCOMPLISHED:
        return "accomplished"
    if theme_band == "pitch":
        return "aspirational"
    return "dedicated"


def collect_curated_rows(
    text: str,
    results: dict[str, CountResult],
    veterans: int,
    name_meta: dict[str, dict[str, object]],
) -> list[dict[str, object]]:
    """All rows in the four-band curated set (post-EXCLUDED_KEYS)."""
    by_key = {str(r["key"]): r for r in _collect_theme_rows(text)}
    for row in by_key.values():
        merge_curated_row(row, name_meta)
    locked = TIER_LEGENDARY | TIER_ACCOMPLISHED

    def rows_for(keys: frozenset[str]) -> list[dict[str, object]]:
        out: list[dict[str, object]] = []
        for key in sorted(keys):
            if key in by_key:
                out.append(by_key[key])
            elif key in results:
                out.append(_row_from_results(key, results, veterans))
                merge_curated_row(out[-1], name_meta)
        return out

    pitch = sorted(
        [
            r
            for r in by_key.values()
            if r["band"] == "pitch" and str(r["key"]) not in locked
        ],
        key=lambda r: r["sort"],
        reverse=False,
    )
    dedicated = sorted(
        [
            r
            for r in by_key.values()
            if r["band"] == "dedicated?" and str(r["key"]) not in locked
        ],
        key=lambda r: r["sort"],
        reverse=True,
    )
    legendary = sorted(rows_for(TIER_LEGENDARY), key=lambda r: r["sort"])
    accomplished = sorted(rows_for(TIER_ACCOMPLISHED), key=lambda r: r["sort"])
    return legendary + accomplished + dedicated + pitch


def export_definitions_seed(
    text: str,
    results: dict[str, CountResult],
    veterans: int,
    name_meta: dict[str, dict[str, object]],
    meta: dict,
) -> None:
    """Phase 3 seed: curated keys + display/tier/rules for milestone_definitions."""
    rows = collect_curated_rows(text, results, veterans, name_meta)
    definitions: list[dict[str, object]] = []
    for r in rows:
        key = str(r["key"])
        band = tier_band_for_key(key, str(r.get("band", "")))
        r_probe = results.get(key)
        definitions.append(
            {
                "milestone_key": key,
                "display_name": _display_plain(str(r["display"])),
                "tier_band": band,
                "chart_token": TIER_CHART_TOKEN[band],
                "name_score": r.get("name_score"),
                "rule_short": str(r["rule"]),
                "rule_probe": r_probe.method if r_probe else None,
                "unlock_veterans": r["unlock"],
                "pct_veterans": r["pct_vet"],
            }
        )
    payload = {
        "version": "2026-05-curated",
        "milestone_count": len(definitions),
        "notes": (
            "Seed for milestone_definitions (Phase 3). Not loaded by site yet. "
            "Medal/winner/league rules: docs/leagues-rules-spec.md. "
            "Regenerate: python scripts/oneoff/milestone_unlock_counts.py "
            "--doc-only --write-doc --export-seed"
        ),
        "definitions": definitions,
    }
    DEFINITIONS_SEED.write_text(
        json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    log.info("Wrote %s (%d definitions)", DEFINITIONS_SEED, len(definitions))


def _collect_theme_rows(text: str) -> list[dict[str, object]]:
    current_section = "?"
    rows: list[dict[str, object]] = []
    for line in text.splitlines():
        sec = re.match(r"^## ([A-Z])\.", line)
        if sec:
            current_section = sec.group(1)
        elif line.startswith("## Tier") or line.startswith("## Tier palettes"):
            current_section = "-"
        m_full = _COUNT_ROW_RE.match(line)
        m_short = _COUNT_ROW_SHORT_RE.match(line) if not m_full else None
        if m_full:
            key, display, rule, unlock, pct_vet, band = m_full.groups()
        elif m_short:
            key, display, unlock, pct_vet, band = m_short.groups()
            rule = display
        else:
            continue
        rows.append(
            {
                "section": current_section,
                "key": key,
                "display": display.strip(),
                "rule": rule.strip(),
                "unlock": int(unlock),
                "pct_vet": pct_vet,
                "band": band,
                "sort": _parse_vet_pct(pct_vet),
            }
        )
    return rows


def _row_from_results(
    key: str, results: dict[str, CountResult], veterans: int
) -> dict[str, object]:
    r = results[key]
    return {
        "section": "—",
        "key": key,
        "display": key.replace("_", " "),
        "rule": r.method,
        "unlock": r.unlock,
        "pct_vet": _pct_vet(r.unlock, veterans),
        "band": r.band,
        "sort": (r.unlock / veterans * 100) if veterans else 0.0,
    }


def build_tier_palettes_text(
    text: str, results: dict[str, CountResult], veterans: int
) -> str:
    """Locked legendary/accomplished + aspirational (pitch) + dedicated? palettes."""
    by_key = {str(r["key"]): r for r in _collect_theme_rows(text)}

    def locked_rows(keys: frozenset[str]) -> list[dict[str, object]]:
        out: list[dict[str, object]] = []
        for key in keys:
            if key in by_key:
                out.append(by_key[key])
            elif key in results:
                out.append(_row_from_results(key, results, veterans))
        return out

    def table(
        title: str,
        blurb: str,
        subset: list[dict[str, object]],
        *,
        rarest_first: bool,
    ) -> list[str]:
        ordered = sorted(subset, key=lambda r: r["sort"], reverse=not rarest_first)
        out = [f"### {title}", "", blurb, ""]
        if not ordered:
            out.append("*(none)*")
            out.append("")
            return out
        out.append("| § | Key | Display | Unlock | %vet | Rule (short) |")
        out.append("|---|-----|---------|-------:|-----:|--------------|")
        for r in ordered:
            rule = str(r["rule"])
            if len(rule) > 72:
                rule = rule[:69] + "..."
            out.append(
                f"| {r['section']} | `{r['key']}` | {r['display']} | {r['unlock']} | "
                f"{r['pct_vet']} | {rule} |"
            )
        out.append("")
        return out

    locked = TIER_LEGENDARY | TIER_ACCOMPLISHED
    all_rows = list(by_key.values())
    pitch = [
        r
        for r in all_rows
        if r["band"] == "pitch" and str(r["key"]) not in locked
    ]
    dedicated = [
        r
        for r in all_rows
        if r["band"] == "dedicated?" and str(r["key"]) not in locked
    ]
    leg_locked = locked_rows(TIER_LEGENDARY)
    acc_locked = locked_rows(TIER_ACCOMPLISHED)

    lines = [
        "## Tier palettes (Dagh locked May 2026 + probe)",
        "",
        "Presentation order: **Legendary → Accomplished → Dedicated → Aspirational**. "
        "Top two bands locked; lower bands are probe hints minus anything already locked above.",
        "",
        "**Discarded (catalog):** `back_in_the_game`, `league_daily_points_winner` (dup `moment_of_glory`), "
        "`long_sleep_loud_wakeup`, `nine_eight_thriller`, `double_digit_handshake`, `club_5000`.",
        "",
    ]
    lines.extend(
        table(
            f"Legendary ({len(leg_locked)})",
            "Holo — flavor + long horizons. Sorted rarest first (%vet).",
            leg_locked,
            rarest_first=True,
        )
    )
    lines.extend(
        table(
            f"Accomplished / Keystones ({len(acc_locked)})",
            "Amber — completeness palette. Sorted rarest first (%vet).",
            acc_locked,
            rarest_first=True,
        )
    )
    lines.extend(
        table(
            f"Dedicated ({len(dedicated)})",
            "Chrome — mid ladder bulk. Sorted rarest first (promotion candidates at top).",
            dedicated,
            rarest_first=True,
        )
    )
    lines.extend(
        table(
            f"Aspirational ({len(pitch)})",
            "Pitch — rarity floor. Sorted commonest first (%vet ↓).",
            pitch,
            rarest_first=False,
        )
    )
    return "\n".join(lines)


def build_curated_tier_doc(
    text: str,
    results: dict[str, CountResult],
    eligible: int,
    veterans: int,
    meta: dict,
    name_meta: dict[str, dict[str, object]] | None = None,
) -> str:
    """Authoritative Phase 2 tier snapshot for Dagh (no probe caveats)."""
    name_meta = name_meta or {}
    all_rows = collect_curated_rows(text, results, veterans, name_meta)
    locked = TIER_LEGENDARY | TIER_ACCOMPLISHED
    legendary = [r for r in all_rows if str(r["key"]) in TIER_LEGENDARY]
    accomplished = [r for r in all_rows if str(r["key"]) in TIER_ACCOMPLISHED]
    dedicated = [
        r
        for r in all_rows
        if str(r["key"]) not in locked and r["band"] == "dedicated?"
    ]
    pitch = [
        r for r in all_rows if str(r["key"]) not in locked and r["band"] == "pitch"
    ]

    def tier_table(rows: list[dict[str, object]]) -> list[str]:
        out = [
            "| Key | Display name | Name Q | Rule (short) | Unlock | %vet |",
            "|-----|--------------|:------:|--------------|-------:|-----:|",
        ]
        for r in rows:
            merge_curated_row(r, name_meta)
            rule = str(r["rule"])
            if len(rule) > 80:
                rule = rule[:77] + "..."
            score = r.get("name_score", "—")
            out.append(
                f"| `{r['key']}` | {r['display']} | {score} | {rule} | {r['unlock']} | {r['pct_vet']} |"
            )
        return out

    def collect_still_weak() -> list[tuple[str, str, int | str]]:
        weak: list[tuple[str, str, int | str]] = []
        all_rows = legendary + accomplished + dedicated + pitch
        for r in all_rows:
            merge_curated_row(r, name_meta)
            disp = str(r["display"])
            score = r.get("name_score")
            if score is not None and int(score) <= 2:
                weak.append((str(r["key"]), disp, int(score)))
            elif "·" in disp and "TBD" not in disp:
                weak.append((str(r["key"]), disp, score if score is not None else "—"))
        return sorted(weak, key=lambda x: x[0])

    n_total = len(legendary) + len(accomplished) + len(dedicated) + len(pitch)
    computed = meta.get("computed_at", "")

    lines = [
        "# Milestones — curated tier list",
        "",
        "**Kick Off 2 ratings site · Phase 2 definition snapshot**",
        "",
        "**Status:** **Decided for now** (May 2026). This is the working milestone set and "
        "tier assignment until a later pass changes it. Not an implementation spec — "
        "rules and display copy details live in [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md).",
        "",
        "**Display names & Name Q (1–5):** [`data/milestones_curated_meta.json`](../data/milestones_curated_meta.json). "
        "**Phase 3 seed:** [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json) "
        "(`--export-seed`). **Name Q** = subjective garden-copy quality (5 = ship as-is).",
        "",
        "**Related:** [`milestones-product-spec.md`](milestones-product-spec.md) (presentation) · "
        "[`milestones-want-maybe-by-theme.md`](milestones-want-maybe-by-theme.md) (themed tables + probe) · "
        "[`milestones-project.md`](milestones-project.md) (phases).",
        "",
        "---",
        "",
        "## Summary",
        "",
        "| Band | Chart token | Count | Role |",
        "|------|-------------|------:|------|",
        f"| **Legendary** | `holo` | {len(legendary)} | Rare feats, long horizons, merchant lore peaks |",
        f"| **Accomplished** | `amber` | {len(accomplished)} | Keystones — serious ladder citizenship |",
        f"| **Dedicated** | `chrome` | {len(dedicated)} | Mid-ladder grind, variety, leagues volume |",
        f"| **Aspirational** | `pitch` | {len(pitch)} | First steps and broad participation floor |",
        f"| **Total in curated set** | — | **{n_total}** | — |",
        "",
        f"**Probe context** (difficulty reference only): {eligible} players with ≥1 rated game; "
        f"**{veterans}** veterans (≥20 games); {meta.get('ratedresults_games', '?')} rated games; "
        f"{computed}. Regenerate counts: "
        "`python scripts/oneoff/milestone_unlock_counts.py --write-doc`.",
        "",
        "---",
        "",
        "## Tier order (presentation)",
        "",
        "Legendary → Accomplished → Dedicated → Aspirational (rarest / highest band first in UI).",
        "",
        "---",
        "",
        "## Win-streak milestones — rule",
        "",
        "These keys use **`playertable.LongestWinningStreak`** (career maximum consecutive wins):",
        "",
        "`win_hat_trick` (≥3) · `ten_wins_straight` (≥10) · `rampage` (≥15) · `win_streak_30` (≥30).",
        "",
        "`cold_streak` and `win_drought` use the corresponding **longest loss / non-win streak** columns on the same table.",
        "",
        "Unlock when the stored career-best run reaches the threshold. Implementation should read the "
        "ladder-maintained column (same source as the profile streak display), not a separate replay pass, "
        "unless the data contract is extended later.",
        "",
        "---",
        "",
        f"## Legendary ({len(legendary)})",
        "",
        "",
    ]
    lines.extend(tier_table(legendary))
    lines.extend(["", "---", "", f"## Accomplished ({len(accomplished)})", "", ""])
    lines.extend(tier_table(accomplished))
    lines.extend(["", "---", "", f"## Dedicated ({len(dedicated)})", "", ""])
    lines.extend(tier_table(dedicated))
    lines.extend(["", "---", "", f"## Aspirational ({len(pitch)})", "", ""])
    lines.extend(tier_table(pitch))
    weak = collect_still_weak()
    if weak:
        lines.extend(
            [
                "",
                "---",
                "",
                f"## Still generic or placeholder ({len(weak)})",
                "",
                "Rows with **Name Q ≤ 2** or `Period · cup · role` display labels. "
                "See [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) §XVIII.",
                "",
                "| Key | Display name | Name Q |",
                "|-----|--------------|:------:|",
            ]
        )
        for key, disp, score in weak:
            lines.append(f"| `{key}` | {disp} | {score} |")
    lines.extend(
        [
            "",
            "---",
            "",
            "## Out of curated set (discarded for now)",
            "",
            "Not in the four bands above. Kept in the ideas catalog as `discard` for reference only.",
            "",
            "| Key | Note |",
            "|-----|------|",
            "| `top_ten_sweep` | Unstable snapshot |",
            "| `long_sleep_loud_wakeup` | Cut from legendary |",
            "| `nine_eight_thriller` | Cut |",
            "| `double_digit_handshake` | Merged into `merchant_trade_fair` (10–10 draw) |",
            "| `club_5000` | Superseded by `club_10000` |",
            "| `back_in_the_game` | Cut |",
            "| `league_daily_points_winner` | Duplicate of `moment_of_glory` |",
            "| `nemesis` | Cut |",
            "| `elite_customer` | Cut |",
            "| `podium_month` | Cut |",
            "| `still_here_years_later` | Cut |",
            "| `league_monthly_activity_winner` | Cut (`activity_king` covers monthly activity win) |",
            "| `period_champion` | Cut — redundant vs specific league milestones |",
            "| `six_goal_draw` | Cut — dropped from curated set |",
            "",
            "---",
            "",
            "*Unlock counts: auto from locked tier sets + probe (`milestone_unlock_counts.py --write-doc`). "
            "Do not hand-edit Unlock / %vet. Names/scores: edit `data/milestones_curated_meta.json`.*",
            "",
        ]
    )
    return "\n".join(lines)


def write_curated_tier_doc(
    theme_body: str,
    results: dict[str, CountResult],
    eligible: int,
    veterans: int,
    meta: dict,
    name_meta: dict[str, dict[str, object]] | None = None,
    export_seed: bool = False,
) -> None:
    name_meta = name_meta or load_curated_meta()[0]
    CURATED_DOC.write_text(
        build_curated_tier_doc(
            theme_body, results, eligible, veterans, meta, name_meta=name_meta
        ),
        encoding="utf-8",
    )
    if export_seed:
        export_definitions_seed(theme_body, results, veterans, name_meta, meta)


def apply_doc_counts(
    path: Path,
    results: dict[str, CountResult],
    eligible: int,
    veterans: int,
    meta: dict,
    export_seed: bool = False,
) -> None:
    text = path.read_text(encoding="utf-8")
    probe = (
        f"**Unlock counts (read-only probe):** **{eligible}** players with ≥1 rated game; "
        f"**{veterans}** veterans (≥20 games) = design population on `{meta['database']}`; "
        f"rated games **{meta['ratedresults_games']}**; **{meta['computed_at']}**. "
        "No DB writes. Regenerate: "
        "`python scripts/oneoff/milestone_unlock_counts.py --write-doc`. "
        "Scratch: `data/scratch/milestone_unlock_counts.json`."
    )
    tiers = (
        "**Tier targets:** Aspirational (pitch) — rarity floor; Dedicated (chrome) — bulk mid ladder; "
        "**Accomplished** (amber) ~**15–20** keystones (~15–25 veterans each); "
        "**Legendary** (holo) ~**10–15** (flavor + ~3–14% veterans). "
        "Thresholds and catalog can be nudged if band counts are off."
    )
    band_line = (
        "**Band names (working):** Aspirational → **Dedicated** → **Accomplished** "
        "(Keystones) → Legendary (`pitch` / `chrome` / `amber` / `holo`)."
    )

    text = re.sub(
        r"\*\*Unlock counts \(read-only probe\):\*\*[^\n]+",
        probe,
        text,
        count=1,
    )
    if "**Tier targets:**" in text:
        text = re.sub(r"\*\*Tier targets:\*\*[^\n]+", tiers, text, count=1)
    else:
        text = text.replace(probe, probe + "\n\n" + tiers, 1)
    text = re.sub(
        r"\*\*Band names \(working\):\*\*[^\n]+",
        band_line,
        text,
        count=1,
    )

    hist = build_histogram(results, veterans)
    tier_block_prefix = hist + "\n---\n\n"
    if "## Tier sizing hints" in text:
        text = re.sub(
            r"## Tier sizing hints \(auto-band from veteran %\)\n[\s\S]*?(?=\n---\n\n## Quick index)",
            tier_block_prefix,
            text,
            count=1,
        )
    else:
        text = text.replace(
            "\n## Quick index",
            "\n" + tier_block_prefix,
            text,
            count=1,
        )

    # Remove old probe caveats footer block
    text = re.sub(
        r"\n\*\*Probe caveats:\*\*[^\n]*(?:\n[^\n#][^\n]*)*",
        "",
        text,
        count=1,
    )
    text = text.replace("|| **Unlock**", "| **Unlock**")
    text = text.replace("||--------:|", "|--------:|")
    text = text.replace(
        "3. Use **Unlock** / **%** (of eligible) for difficulty; read **Method**",
        "3. Use **%vet** (vs 107 veterans) for tier design; **%≥1g** includes tryouts; read **Band** + **Method**",
    )
    text = re.sub(
        r"\*\*Counts \(deduped\):\*\*[^\n]+",
        "**Counts (deduped):** **~111** keys in tables (`top_ten_sweep` discarded).",
        text,
        count=1,
    )

    lines_out: list[str] = []
    in_count_table = False
    table_sep: str | None = None
    for line in text.splitlines():
        if line.startswith("| Curate |") and "Key" in line:
            in_count_table = True
            if "Display name" in line:
                line = (
                    "| Curate | Key | Display name | Rule (short) | Data |"
                    + _COUNT_HEADER
                )
                table_sep = (
                    "|--------|-----|--------------|--------------|------|"
                    + _COUNT_SEP
                )
            elif "Context" in line:
                line = "| Curate | Key | Context | Data |" + _COUNT_HEADER
                table_sep = "|--------|-----|---------|------|" + _COUNT_SEP
            else:
                line = "| Curate | Key | Rule (short) | Data |" + _COUNT_HEADER
                table_sep = "|--------|-----|--------------|------|" + _COUNT_SEP
            lines_out.append(line)
            continue
        if line.startswith("|--------|") and in_count_table and table_sep:
            lines_out.append(table_sep)
            continue
        m = _KEY_ROW.match(line)
        if m and in_count_table:
            key = m.group(1)
            if key in EXCLUDED_KEYS:
                continue  # drop row
            prefix = _row_prefix(line)
            if prefix and key in results:
                r = results[key]
                line = (
                    prefix.rstrip("|").strip()
                    + f" | {r.unlock} | {_pct(r.unlock, eligible)} | "
                    f"{_pct_vet(r.unlock, veterans)} | {r.band} | {r.method} |"
                )
            lines_out.append(line)
            continue
        if not line.startswith("|"):
            in_count_table = False
        lines_out.append(line)

    footer = (
        "\n*Probe: planning only. Re-run: "
        "`python scripts/oneoff/milestone_unlock_counts.py --write-doc`. "
        "Authoritative tier list: `docs/milestones-tier-curated.md`.*\n"
    )
    if "*Probe:" not in "".join(lines_out[-3:]):
        lines_out.append(footer.strip())

    body = "\n".join(lines_out) + "\n"
    palette = build_tier_palettes_text(body, results, veterans)
    palette_pat = (
        r"## (?:Accomplished & Legendary candidate palettes|Tier palettes \(Dagh locked)[^\n]*\n"
        r"[\s\S]*?\n---\n\n(?=## Quick index)"
    )
    if re.search(palette_pat, body):
        body = re.sub(palette_pat, palette + "\n---\n\n", body, count=1)
    else:
        body = body.replace(
            "\n## Quick index",
            "\n" + palette + "---\n\n## Quick index",
            1,
        )
    path.write_text(body, encoding="utf-8")
    name_meta, key_renames = load_curated_meta()
    apply_curated_key_renames(results, key_renames)
    write_curated_tier_doc(
        body,
        results,
        eligible,
        veterans,
        meta,
        name_meta=name_meta,
        export_seed=export_seed,
    )
    log.info("Wrote %s", CURATED_DOC)


def main(write_doc: bool, doc_only: bool, export_seed: bool) -> None:
    if doc_only:
        payload = json.loads(JSON_OUT.read_text(encoding="utf-8"))
        meta = payload["meta"]
        eligible = int(meta["eligible_players"])
        veterans = int(meta.get("veteran_players", 0))
        results = {
            k: CountResult(
                int(v["unlock"]),
                str(v["method"]),
                str(v.get("band", "")),
            )
            for k, v in payload["counts"].items()
            if k not in EXCLUDED_KEYS
        }
        if write_doc:
            name_meta, key_renames = load_curated_meta()
            apply_curated_key_renames(results, key_renames)
            apply_doc_counts(
                THEME_DOC,
                results,
                eligible,
                veterans,
                meta,
                export_seed=export_seed,
            )
            log.info("Updated %s from %s", THEME_DOC, JSON_OUT)
            return

    cfg = load_db_config()
    conn = connect(cfg, dry_run=False)
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
            eligible = count_pt(cur, "1=1")
            veterans = count_veterans(cur)
            cur.execute("SELECT COUNT(*) AS n FROM ratedresults")
            games_n = int(cur.fetchone()["n"])

            results: dict[str, CountResult] = {}
            results.update(playertable_counts(cur))
            results.update(ratedresults_exists_counts(cur))
            results.update(matchup_counts(cur))
            results.update(league_counts(cur))
            results.update(period_burst_counts(cur))

            chrono = run_chronological(cur)
            for k, v in chrono.items():
                if k in ("travelling_salesman", "clean_sheet_spread"):
                    continue
                results[k] = v
            results["diversity_merchant"] = dd_distinct_opponents_count(cur, 5)
            results["travelling_salesman"] = travelling_salesman_count(cur)
            results["clean_sheet_spread"] = clean_sheet_spread_count(cur)

            cur.execute(
                f"""
                SELECT COUNT(*) AS n FROM playertable
                WHERE {ELIGIBLE_WHERE}
                """
            )
            results["entered_arena"] = CountResult(
                int(cur.fetchone()["n"]),
                "playertable JoinDate (register = enter lobby)",
            )

            results = finalize_results(results, veterans)
            name_meta, key_renames = load_curated_meta()
            apply_curated_key_renames(results, key_renames)

            meta = {
                "database": cfg.database,
                "eligible_players": eligible,
                "veteran_players": veterans,
                "ratedresults_games": games_n,
                "computed_at": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC"),
            }
            payload = {
                "meta": meta,
                "counts": {
                    k: {"unlock": v.unlock, "method": v.method, "band": v.band}
                    for k, v in sorted(results.items())
                },
            }
            JSON_OUT.parent.mkdir(parents=True, exist_ok=True)
            JSON_OUT.write_text(json.dumps(payload, indent=2), encoding="utf-8")
            log.info("Wrote %s (%d keys)", JSON_OUT, len(results))

            if write_doc:
                apply_doc_counts(
                    THEME_DOC,
                    results,
                    eligible,
                    veterans,
                    meta,
                    export_seed=export_seed,
                )
                log.info("Updated %s", THEME_DOC)

            # stdout summary
            print(
                f"eligible={eligible} veterans={veterans} games={games_n} db={cfg.database}"
            )
            for key in sorted(results.keys()):
                r = results[key]
                line = f"{key}\t{r.unlock}\t{_pct(r.unlock, eligible)}\t{r.method}"
                print(line.encode("ascii", "replace").decode("ascii"))
    finally:
        conn.close()


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--write-doc", action="store_true", help="Patch milestones-want-maybe-by-theme.md")
    parser.add_argument(
        "--doc-only",
        action="store_true",
        help="Apply existing data/scratch/milestone_unlock_counts.json to doc (no DB)",
    )
    parser.add_argument(
        "--export-seed",
        action="store_true",
        help="Write data/milestones_definitions_seed.json from curated set + meta",
    )
    args = parser.parse_args()
    main(
        write_doc=args.write_doc,
        doc_only=args.doc_only,
        export_seed=args.export_seed,
    )
