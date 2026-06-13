"""Flag auto-OK tier-B tournaments that are not obvious 2^n single-elim cups."""

from __future__ import annotations

import math
import re
from collections import Counter
from dataclasses import dataclass, field

from scripts.amiga.tournament_phases import ScopeType, is_knockout_phase, parse_phase
from scripts.amiga.tournament_structure.materialize_legacy import _connect
from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
    NON_WC_SLICE6_CUP_REVIEW_IDS,
    NON_WC_TIER_B_AUTO_MATERIALIZE_IDS,
)
from scripts.amiga.tournament_structure.verify_legacy import _load_games


def is_power_of_two(n: int) -> bool:
    return n > 0 and (n & (n - 1)) == 0


def next_power_of_two(n: int) -> int:
    if n <= 1:
        return 1
    return 1 << (n - 1).bit_length()


@dataclass
class CupAudit:
    tournament_id: int
    name: str
    players: int
    games: int
    flags: list[str] = field(default_factory=list)
    phases: list[str] = field(default_factory=list)
    league_phase_games: int = 0
    ko_phase_games: int = 0

    @property
    def obvious_pure_cup(self) -> bool:
        return not self.flags

    @property
    def danger_score(self) -> int:
        return len(self.flags)


def audit_tournament(tid: int, name: str, games: list[dict]) -> CupAudit:
    players = set()
    for g in games:
        players.add(int(g["player_a_id"]))
        players.add(int(g["player_b_id"]))
    n = len(players)
    gcount = len(games)

    phases = [str(g.get("phase") or "").strip() for g in games]
    phase_counts = Counter(p for p in phases if p)
    unique_phases = sorted(phase_counts.keys())

    league_games = 0
    ko_games = 0
    league_labels: list[str] = []
    for p, c in phase_counts.items():
        if not p:
            continue
        if is_knockout_phase(p):
            ko_games += c
        else:
            scope = parse_phase(p)
            if scope.scope_type == ScopeType.LEAGUE:
                league_games += c
                league_labels.append(p)

    row = CupAudit(
        tournament_id=tid,
        name=name,
        players=n,
        games=gcount,
        phases=unique_phases,
        league_phase_games=league_games,
        ko_phase_games=ko_games,
    )

    # --- flags ---
    if n < 2:
        row.flags.append("too_few_players")

    if gcount != n - 1:
        row.flags.append(f"not_single_elim_count(games={gcount},players={n},want={n - 1})")

    if not is_power_of_two(n):
        row.flags.append(f"not_power_of_2_players({n})")
    else:
        bracket_size = n
        if gcount != bracket_size - 1:
            pass  # already flagged

    # Non-power-of-2 but n-1 games = bye bracket (e.g. 15 players, 14 games) — flag as softer
    if not is_power_of_two(n) and gcount == n - 1:
        row.flags.append(f"bye_bracket({n}_players)")

    if league_games > 0:
        row.flags.append(f"league_like_phases({league_games}g:{','.join(league_labels[:4])})")

    # Round N labels that parse as league (Stoke pattern)
    for label in league_labels:
        if re.match(r"^round\s+\d+$", label, re.I):
            row.flags.append(f"round_n_as_league({label!r})")

    if re.search(r"\bgroup\b", " ".join(unique_phases), re.I):
        row.flags.append("has_group_phase")

    if re.search(r"champs?\b", name, re.I) and "cup" not in name.lower():
        row.flags.append("championship_not_cup_name")

    if len(unique_phases) > 6:
        row.flags.append(f"many_phase_labels({len(unique_phases)})")

    # Multiple round-robin-ish rounds before KO
    round_labels = [p for p in unique_phases if re.match(r"^round\s+\d+$", p, re.I)]
    if len(round_labels) > 1:
        row.flags.append(f"multi_round_labels({len(round_labels)})")

    # KO phase label variants / placement
    for p in unique_phases:
        pl = p.lower()
        if "place" in pl and "final" in pl:
            row.flags.append("placement_final_phases")
            break
        if "3rd" in pl or "5th" in pl:
            row.flags.append("placement_final_phases")
            break

    if "finals" in " ".join(unique_phases).lower() and "final" in " ".join(unique_phases).lower():
        if any(p.lower() == "finals" for p in unique_phases):
            row.flags.append("finals_plural_label")

    # All KO phases but weird player count already caught
    if ko_games == gcount and league_games == 0 and is_power_of_two(n) and gcount == n - 1:
        # Clear obvious — remove bye flag if only issue was... no, power of 2 clears bye
        pass

    return row


def main() -> None:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name FROM tournaments WHERE id IN (%s)"
                % ",".join(str(i) for i in all_ids)
            )
            meta = {int(r["id"]): str(r["name"]) for r in cur.fetchall()}

        audits: list[CupAudit] = []
        for tid in all_ids:
            games = _load_games(conn, tid)
            audits.append(audit_tournament(tid, meta.get(tid, "?"), games))

        obvious = [a for a in audits if a.obvious_pure_cup]
        dangerous = [a for a in audits if not a.obvious_pure_cup]
        dangerous.sort(key=lambda a: (-a.danger_score, a.tournament_id))

        all_ids = sorted(NON_WC_TIER_B_AUTO_MATERIALIZE_IDS | NON_WC_SLICE6_CUP_REVIEW_IDS)
        print(f"Cup audit: {len(NON_WC_TIER_B_AUTO_MATERIALIZE_IDS)} safe auto-OK + "
              f"{len(NON_WC_SLICE6_CUP_REVIEW_IDS)} demoted review")
        print("=" * 60)
        print(f"Obvious pure 2^n single-elim cups: {len(obvious)}")
        print(f"Flagged (weirder / dangerous): {len(dangerous)}")
        print()

        print("--- OBVIOUS (safe-looking) ---")
        for a in obvious:
            print(f"  id={a.tournament_id:3}  {a.players:2}p {a.games:2}g  {a.name}")

        print()
        print("--- FLAG FOR MANUAL REVIEW ---")
        for a in dangerous:
            flags = "; ".join(a.flags)
            print(f"  id={a.tournament_id:3}  {a.players:2}p {a.games:2}g  {a.name}")
            print(f"         {flags}")
            print(f"         phases: {a.phases[:8]}{'...' if len(a.phases)>8 else ''}")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
