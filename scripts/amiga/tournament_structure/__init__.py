"""Tournament structure specs — stages, fixtures, import backfill."""

from scripts.amiga.tournament_structure.apply import (
    ApplyContext,
    apply_structure_spec,
    structure_specs_manifest,
)
from scripts.amiga.tournament_structure.registry import all_structure_specs
from scripts.amiga.tournament_structure.specs import (
    FixtureSpec,
    GroupRosterSpec,
    StageSpec,
    StructureSpec,
    parse_structure_spec,
)

__all__ = [
    "ApplyContext",
    "FixtureSpec",
    "GroupRosterSpec",
    "StageSpec",
    "StructureSpec",
    "all_structure_specs",
    "apply_structure_spec",
    "parse_structure_spec",
    "structure_specs_manifest",
]
