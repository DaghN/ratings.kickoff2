"""Homburg 2004 structure spec (forum t=7711)."""

from __future__ import annotations

from scripts.amiga.tournament_structure.specs import (
    FixtureSpec,
    GroupRosterSpec,
    StageSpec,
    StructureSpec,
)

HOMEBURG_EVIDENCE_URL = "https://ko-gathering.com/forum/viewtopic.php?t=7711"

_HOMEBURG_GROUPS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("A", ("Michael O", "Christoph R", "Boris K", "Thomas R")),
    ("B", ("Klaus F", "Christian D", "Thomas L", "Stefan H")),
    ("C", ("Ralph D", "Daniel G", "Sven S", "Andreas B")),
    ("D", ("Thomas K", "Andreas G", "Manuel R", "Volker K")),
    ("E", ("Gerd W", "Michael M", "Matthias D", "Christoph L")),
    ("F", ("Stefan V", "Gabriel H", "Andreas N", "Christoph K")),
    ("G", ("Sascha F", "Tobias B", "Michael H", "Alexander P")),
    ("H", ("Jorg P", "Thomas N", "Patrick B", "Matthias M", "Alexander S")),
)

# (round_key, phase_label, player_a, player_b, leg_count)
_HOMEBURG_KO_TIES: tuple[tuple[str, str, str, str, int], ...] = (
    ("last_16", "Round of 16", "Michael O", "Alexander S", 2),
    ("last_16", "Round of 16", "Christian D", "Sascha F", 2),
    ("last_16", "Round of 16", "Daniel G", "Thomas R", 2),
    ("last_16", "Round of 16", "Thomas K", "Sven S", 2),
    ("last_16", "Round of 16", "Michael M", "Stefan H", 2),
    ("last_16", "Round of 16", "Stefan V", "Gerd W", 2),
    ("last_16", "Round of 16", "Tobias B", "Christoph K", 2),
    ("last_16", "Round of 16", "Patrick B", "Volker K", 3),
    ("quarter", "Quarter Finals", "Michael O", "Volker K", 2),
    ("quarter", "Quarter Finals", "Christian D", "Christoph K", 2),
    ("quarter", "Quarter Finals", "Daniel G", "Stefan V", 2),
    ("quarter", "Quarter Finals", "Thomas K", "Stefan H", 2),
    ("semi", "Semi Finals", "Michael O", "Thomas K", 2),
    ("semi", "Semi Finals", "Christoph K", "Stefan V", 2),
    ("placement_3rd", "3rd Place Final", "Thomas K", "Christoph K", 3),
    ("final", "Final", "Michael O", "Stefan V", 2),
)


def _group_stages() -> tuple[StageSpec, ...]:
    stages: list[StageSpec] = []
    for seq, (group_key, players) in enumerate(_HOMEBURG_GROUPS, start=1):
        stage_key = f"group-{group_key.lower()}"
        stages.append(
            StageSpec(
                stage_key=stage_key,
                name=f"Group {group_key}",
                stage_type="round_robin",
                group_keys=(group_key,),
                groups=(GroupRosterSpec(group_key=group_key, player_names=players),),
            )
        )
    return tuple(stages)


def _knockout_stages() -> tuple[StageSpec, ...]:
    return (
        StageSpec(
            stage_key="ko-last-16",
            name="Round of 16",
            stage_type="knockout",
            round_keys=("last_16",),
        ),
        StageSpec(
            stage_key="ko-quarter",
            name="Quarter Finals",
            stage_type="knockout",
            round_keys=("quarter",),
        ),
        StageSpec(
            stage_key="ko-semi",
            name="Semi Finals",
            stage_type="knockout",
            round_keys=("semi",),
        ),
        StageSpec(
            stage_key="ko-placement-3rd",
            name="3rd Place Final",
            stage_type="knockout",
            round_keys=("placement_3rd",),
        ),
        StageSpec(
            stage_key="ko-final",
            name="Final",
            stage_type="knockout",
            round_keys=("final",),
        ),
    )


def _knockout_fixtures() -> tuple[FixtureSpec, ...]:
    round_stage = {
        "last_16": "ko-last-16",
        "quarter": "ko-quarter",
        "semi": "ko-semi",
        "placement_3rd": "ko-placement-3rd",
        "final": "ko-final",
    }
    fixtures: list[FixtureSpec] = []
    for tie_idx, (round_key, phase_label, player_a, player_b, legs) in enumerate(
        _HOMEBURG_KO_TIES, start=1
    ):
        stage_key = round_stage[round_key]
        for leg_no in range(1, legs + 1):
            fixtures.append(
                FixtureSpec(
                    fixture_key=f"{round_key}-{tie_idx:02d}-leg-{leg_no}",
                    stage_key=stage_key,
                    player_a=player_a,
                    player_b=player_b,
                    leg_no=leg_no,
                    round_key=round_key,
                )
            )
    return tuple(fixtures)


HOMEBURG_SPEC = StructureSpec(
    catalog_name="Homburg",
    template_slug="group_knockout",
    evidence_url=HOMEBURG_EVIDENCE_URL,
    stages=_group_stages() + _knockout_stages(),
    fixtures=_knockout_fixtures(),
    format_overrides={
        "structure_source": "forum",
        "evidence_url": HOMEBURG_EVIDENCE_URL,
        "group_count": 8,
        "group_h_size": 5,
    },
)
