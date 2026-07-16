"""Pure knockout structure handler — contract: docs/amiga-tournament-structure-pure-knockout-handler.md

Does NOT use parse_phase() for stage typing. Groups games by (phase, player pair);
one knockout stage per tie; leg_no from chronological order within tie.
"""

from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from typing import Any

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import (
    MATERIALIZE_SOURCE,
    MaterializeResult,
    StructureReviewRequired,
    _clear_tournament_structure,
    _count_existing_stages,
    _import_create_fixture,
    _load_games,
    _load_tournament,
    _slug_key,
)
from scripts.amiga.tournament_structure.specs import STAGE_TYPE_KNOCKOUT

HANDLER_ID = "pure_knockout"

_GROUP_LIKE = re.compile(r"\bgroup\b", re.IGNORECASE)


@dataclass(frozen=True, slots=True)
class PureKnockoutTieLeg:
    game_id: int
    leg_no: int
    player_a_id: int
    player_b_id: int
    goals_a: int
    goals_b: int
    game_date: str


@dataclass(frozen=True, slots=True)
class PureKnockoutTie:
    phase_label: str
    stage_key: str
    player_lo: int
    player_hi: int
    legs: tuple[PureKnockoutTieLeg, ...]

    @property
    def leg_count(self) -> int:
        return len(self.legs)


@dataclass
class PureKnockoutPreview:
    tournament_id: int
    tournament_name: str
    game_count: int
    tie_count: int
    ties: list[PureKnockoutTie] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    player_count: int = 0

    @property
    def ok_to_apply(self) -> bool:
        return self.game_count > 0 and not self.warnings

    def to_dict(self) -> dict[str, Any]:
        return {
            "handler": HANDLER_ID,
            "tournament_id": self.tournament_id,
            "tournament_name": self.tournament_name,
            "game_count": self.game_count,
            "player_count": self.player_count,
            "tie_count": self.tie_count,
            "ok_to_apply": self.ok_to_apply,
            "warnings": self.warnings,
            "ties": [
                {
                    "phase_label": t.phase_label,
                    "stage_key": t.stage_key,
                    "players": [t.player_lo, t.player_hi],
                    "leg_count": t.leg_count,
                    "legs": [
                        {
                            "game_id": leg.game_id,
                            "leg_no": leg.leg_no,
                            "player_a_id": leg.player_a_id,
                            "player_b_id": leg.player_b_id,
                            "score": f"{leg.goals_a}-{leg.goals_b}",
                            "game_date": leg.game_date,
                        }
                        for leg in t.legs
                    ],
                }
                for t in self.ties
            ],
        }


def _normalize_phase(phase: Any) -> str:
    if phase is None:
        return ""
    text = str(phase).strip()
    return text


def _pair_key(player_a_id: int, player_b_id: int) -> tuple[int, int]:
    a, b = int(player_a_id), int(player_b_id)
    return (min(a, b), max(a, b))


def _tie_stage_key(phase_label: str, player_lo: int, player_hi: int) -> str:
    label = phase_label.strip() or "Knockout"
    return f"ko-{_slug_key(label, fallback='knockout')}-{player_lo}-{player_hi}"


def _game_sort_key(game: dict[str, Any]) -> tuple[str, int]:
    gd = game.get("game_date")
    if gd is None:
        gd = ""
    elif hasattr(gd, "strftime"):
        gd = gd.strftime("%Y-%m-%d %H:%M:%S")
    else:
        gd = str(gd)
    return gd, int(game["id"])


def _collect_warnings(games: list[dict[str, Any]], tie_groups: dict[tuple[str, int, int], list[dict]]) -> list[str]:
    warnings: list[str] = []
    if not games:
        warnings.append("no_games")
        return warnings

    for game in games:
        phase = _normalize_phase(game.get("phase"))
        if not phase:
            warnings.append(f"null_phase:game_id={game['id']}")
        elif _GROUP_LIKE.search(phase):
            warnings.append(f"group_like_phase:{phase!r}:game_id={game['id']}")

    for key, group in tie_groups.items():
        if len(group) > 2:
            phase, lo, hi = key
            warnings.append(f"many_legs:{len(group)}:phase={phase!r}:players={lo}-{hi}")

    return warnings


def infer_pure_knockout_structure(
    games: list[dict[str, Any]],
    *,
    tournament_id: int = 0,
    tournament_name: str = "",
) -> PureKnockoutPreview:
    """Preview how pure_knockout would group games — use in review before register promotion."""
    players: set[int] = set()
    tie_groups: dict[tuple[str, int, int], list[dict[str, Any]]] = {}

    for game in games:
        pa, pb = int(game["player_a_id"]), int(game["player_b_id"])
        players.add(pa)
        players.add(pb)
        phase = _normalize_phase(game.get("phase")) or "Knockout"
        lo, hi = _pair_key(pa, pb)
        key = (phase, lo, hi)
        tie_groups.setdefault(key, []).append(game)

    warnings = _collect_warnings(games, tie_groups)
    ties: list[PureKnockoutTie] = []

    for (phase, lo, hi), group in sorted(tie_groups.items(), key=lambda x: (_game_sort_key(x[1][0]), x[0])):
        ordered = sorted(group, key=_game_sort_key)
        legs = tuple(
            PureKnockoutTieLeg(
                game_id=int(g["id"]),
                leg_no=idx,
                player_a_id=int(g["player_a_id"]),
                player_b_id=int(g["player_b_id"]),
                goals_a=int(g["goals_a"]),
                goals_b=int(g["goals_b"]),
                game_date=_game_sort_key(g)[0],
            )
            for idx, g in enumerate(ordered, start=1)
        )
        ties.append(
            PureKnockoutTie(
                phase_label=phase,
                stage_key=_tie_stage_key(phase, lo, hi),
                player_lo=lo,
                player_hi=hi,
                legs=legs,
            )
        )

    return PureKnockoutPreview(
        tournament_id=tournament_id,
        tournament_name=tournament_name,
        game_count=len(games),
        tie_count=len(ties),
        ties=ties,
        warnings=warnings,
        player_count=len(players),
    )


def materialize_pure_knockout(
    conn: Any,
    tournament_id: int,
    *,
    dry_run: bool = False,
    replace: bool = False,
    force: bool = False,
) -> MaterializeResult:
    """Apply pure knockout handler — only for register-promoted tournaments."""
    tournament = _load_tournament(conn, tournament_id)
    games = _load_games(conn, tournament_id)
    preview = infer_pure_knockout_structure(
        games,
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
    )

    if not games:
        raise ValueError(f"tournament_id={tournament_id} has no games")

    if preview.warnings and not force:
        raise StructureReviewRequired(
            f"tournament_id={tournament_id} ({tournament['name']!r}) pure_knockout preflight failed: "
            f"{'; '.join(preview.warnings[:5])}"
            + (" …" if len(preview.warnings) > 5 else "")
            + " — fix data, use structure_spec, or pass force=True (dev only)"
        )

    existing = _count_existing_stages(conn, tournament_id)
    if existing and not replace:
        raise ValueError(
            f"tournament_id={tournament_id} already has {existing} stage(s) — pass replace=True"
        )
    if existing and replace and not force:
        from scripts.amiga.tournament_structure.materialize_legacy import (
            _is_manually_curated_structure,
        )

        if _is_manually_curated_structure(conn, tournament_id):
            raise ValueError(
                f"tournament_id={tournament_id} has curated structure — "
                "pass --force to wipe and rebuild"
            )
    if existing and replace:
        _clear_tournament_structure(conn, tournament_id)

    result = MaterializeResult(
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
        dry_run=dry_run,
    )

    stage_id_by_key: dict[str, int] = {}
    for seq, tie in enumerate(preview.ties, start=1):
        stage_id = create_stage(
            conn,
            tournament_id=tournament_id,
            stage_key=tie.stage_key,
            name=tie.phase_label,
            stage_type=STAGE_TYPE_KNOCKOUT,
            sequence_no=seq,
            config={
                "materialized_by": MATERIALIZE_SOURCE,
                "handler": HANDLER_ID,
                "legacy_import": True,
                "phase_provenance": "labeled",
            },
        )
        stage_id_by_key[tie.stage_key] = stage_id
        result.stages_created += 1
        result.stage_summary.append(
            {
                "stage_key": tie.stage_key,
                "name": tie.phase_label,
                "stage_type": STAGE_TYPE_KNOCKOUT,
                "game_count": tie.leg_count,
                "leg_count": tie.leg_count,
            }
        )

        for leg in tie.legs:
            fixture_key = f"legacy-g{leg.game_id}"
            fixture_id = _import_create_fixture(
                conn,
                stage_id=stage_id,
                fixture_key=fixture_key,
                player_a_id=leg.player_a_id,
                player_b_id=leg.player_b_id,
                leg_no=leg.leg_no,
                phase_label=tie.phase_label,
            )
            result.fixtures_created += 1
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE amiga_games SET fixture_id = %s WHERE id = %s",
                    (fixture_id, leg.game_id),
                )
            result.games_linked += 1

    return result


def format_preview_text(preview: PureKnockoutPreview) -> str:
    lines = [
        f"Pure knockout preview — {preview.tournament_name!r} (id={preview.tournament_id})",
        f"  games={preview.game_count}  players={preview.player_count}  ties={preview.tie_count}",
        f"  ok_to_apply={preview.ok_to_apply}",
    ]
    if preview.warnings:
        lines.append("  WARNINGS:")
        for w in preview.warnings[:20]:
            lines.append(f"    - {w}")
        if len(preview.warnings) > 20:
            lines.append(f"    … +{len(preview.warnings) - 20} more")
    lines.append("  TIES:")
    for tie in preview.ties:
        legs = ", ".join(
            f"leg{leg.leg_no}: {leg.goals_a}-{leg.goals_b} (g{leg.game_id})" for leg in tie.legs
        )
        lines.append(
            f"    [{tie.phase_label}] p{tie.player_lo}-p{tie.player_hi} ({tie.leg_count} leg(s)): {legs}"
        )
    return "\n".join(lines)


def preview_cli(tournament_id: int, *, as_json: bool = False) -> int:
    from scripts.amiga.tournament_structure.materialize_legacy import _connect

    conn = _connect()
    try:
        tournament = _load_tournament(conn, tournament_id)
        games = _load_games(conn, tournament_id)
        preview = infer_pure_knockout_structure(
            games,
            tournament_id=tournament_id,
            tournament_name=str(tournament["name"]),
        )
    finally:
        conn.close()

    if as_json:
        print(json.dumps(preview.to_dict(), indent=2))
    else:
        print(format_preview_text(preview))
    return 0
