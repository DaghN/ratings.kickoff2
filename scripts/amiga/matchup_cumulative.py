"""In-memory cumulative directed pair stats for Amiga tournament finalize."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from scripts.ladder.player_state import PlayerState


@dataclass
class PairTotals:
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    goals_against: int = 0
    dd_wins: int = 0
    dd_losses: int = 0
    cs_wins: int = 0
    cs_losses: int = 0

    def to_row(self, player_id: int, opponent_id: int) -> dict[str, Any]:
        return {
            "player_id": player_id,
            "opponent_id": opponent_id,
            "games": self.games,
            "wins": self.wins,
            "draws": self.draws,
            "losses": self.losses,
            "goals_for": self.goals_for,
            "goals_against": self.goals_against,
            "dd_wins": self.dd_wins,
            "dd_losses": self.dd_losses,
            "cs_wins": self.cs_wins,
            "cs_losses": self.cs_losses,
        }


@dataclass
class MatchupCumulative:
    """player_id -> opponent_id -> cumulative pair totals through processed history."""

    pairs: dict[int, dict[int, PairTotals]] = field(default_factory=dict)

    def _pair(self, player_id: int, opponent_id: int) -> PairTotals:
        by_opp = self.pairs.setdefault(player_id, {})
        return by_opp.setdefault(opponent_id, PairTotals())

    def apply_game(self, game: dict[str, Any]) -> None:
        id_a = int(game["player_a_id"] if "player_a_id" in game else game["idA"])
        id_b = int(game["player_b_id"] if "player_b_id" in game else game["idB"])
        goals_a = int(game["goals_a"] if "goals_a" in game else game["GoalsA"])
        goals_b = int(game["goals_b"] if "goals_b" in game else game["GoalsB"])

        if goals_a > goals_b:
            w_a, d_a, l_a = 1, 0, 0
            w_b, d_b, l_b = 0, 0, 1
        elif goals_a < goals_b:
            w_a, d_a, l_a = 0, 0, 1
            w_b, d_b, l_b = 1, 0, 0
        else:
            w_a, d_a, l_a = 0, 1, 0
            w_b, d_b, l_b = 0, 1, 0

        dd_a = goals_a >= 10
        dd_b = goals_b >= 10
        cs_a = goals_b == 0
        cs_b = goals_a == 0

        pa = self._pair(id_a, id_b)
        pa.games += 1
        pa.wins += w_a
        pa.draws += d_a
        pa.losses += l_a
        pa.goals_for += goals_a
        pa.goals_against += goals_b
        if dd_a:
            pa.dd_wins += 1
        if dd_b:
            pa.dd_losses += 1
        if cs_a:
            pa.cs_wins += 1
        if cs_b:
            pa.cs_losses += 1

        pb = self._pair(id_b, id_a)
        pb.games += 1
        pb.wins += w_b
        pb.draws += d_b
        pb.losses += l_b
        pb.goals_for += goals_b
        pb.goals_against += goals_a
        if dd_b:
            pb.dd_wins += 1
        if dd_a:
            pb.dd_losses += 1
        if cs_b:
            pb.cs_wins += 1
        if cs_a:
            pb.cs_losses += 1

    def pairs_for_player(self, player_id: int) -> dict[int, PairTotals]:
        return self.pairs.get(player_id, {})

    def network_counts(self, player_id: int) -> dict[str, int]:
        pairs = self.pairs_for_player(player_id)
        return {
            "different_opponents": len(pairs),
            "different_victims": sum(1 for p in pairs.values() if p.wins > 0),
            "different_culprits": sum(1 for p in pairs.values() if p.losses > 0),
            "double_digits_victims": sum(1 for p in pairs.values() if p.dd_wins > 0),
            "double_digits_culprits": sum(1 for p in pairs.values() if p.dd_losses > 0),
            "clean_sheets_victims": sum(1 for p in pairs.values() if p.cs_wins > 0),
            "clean_sheets_culprits": sum(1 for p in pairs.values() if p.cs_losses > 0),
        }

    def apply_network_to_player_state(self, player_id: int, st: PlayerState) -> None:
        counts = self.network_counts(player_id)
        st.different_opponents = counts["different_opponents"]
        st.different_victims = counts["different_victims"]
        st.different_culprits = counts["different_culprits"]
        st.double_digits_victims = counts["double_digits_victims"]
        st.double_digits_culprits = counts["double_digits_culprits"]
        st.clean_sheets_victims = counts["clean_sheets_victims"]
        st.clean_sheets_culprits = counts["clean_sheets_culprits"]


def apply_peak_from_event_rating(st: PlayerState, rating_after: float) -> None:
    """Incremental peak/nadir from finalize event rating (policy M6)."""
    if st.games <= 0:
        return
    if st.peak_rating <= 0 or rating_after > st.peak_rating:
        st.peak_rating = rating_after
    if st.lowest_rating <= 0 or rating_after < st.lowest_rating:
        st.lowest_rating = rating_after
