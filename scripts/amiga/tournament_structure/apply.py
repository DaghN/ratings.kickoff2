"""Apply structure specs during import."""

from __future__ import annotations

import logging
from dataclasses import dataclass, field
from typing import TYPE_CHECKING, Any

import pymysql

from scripts.amiga.tournament_structure.build import build_tournament_structure
from scripts.amiga.tournament_structure.link import link_games_to_fixtures
from scripts.amiga.tournament_structure.registry import active_structure_specs

if TYPE_CHECKING:
    from scripts.amiga.import_access import AccessScore

log = logging.getLogger(__name__)


@dataclass(frozen=True, slots=True)
class ApplyContext:
    """Inputs available at the structure-apply hook point."""

    tour_id_by_name: dict[str, int]
    player_id: dict[str, int]
    tour_by_name: dict[str, dict]
    scores: list[AccessScore]


@dataclass
class SpecApplyStats:
    catalog_name: str
    fixture_count: int = 0
    games_linked: int = 0
    orphan_games: int = 0


@dataclass
class ApplyResult:
    """Summary of structure application (manifest + logging)."""

    applied: tuple[str, ...] = ()
    skipped: tuple[str, ...] = ()
    stats_by_name: dict[str, SpecApplyStats] = field(default_factory=dict)


def apply_structure_spec(
    conn: pymysql.connections.Connection,
    ctx: ApplyContext,
    game_rows: list[dict],
) -> ApplyResult:
    """Create stages/fixtures and link fixture_id on game rows before INSERT."""
    applied: list[str] = []
    skipped: list[str] = []
    stats_by_name: dict[str, SpecApplyStats] = {}

    for spec in active_structure_specs():
        if spec.catalog_name not in ctx.tour_id_by_name:
            log.warning(
                "Structure spec for %r skipped — not in import catalog",
                spec.catalog_name,
            )
            skipped.append(spec.catalog_name)
            continue

        tournament_id = ctx.tour_id_by_name[spec.catalog_name]
        build = build_tournament_structure(
            conn,
            spec,
            tournament_id=tournament_id,
            player_id=ctx.player_id,
        )
        link = link_games_to_fixtures(
            game_rows,
            tournament_id=tournament_id,
            build=build,
        )
        if link.orphans:
            raise SystemExit(
                f"Structure apply {spec.catalog_name!r}: {link.orphans} game(s) could not be linked to fixtures"
            )
        stats_by_name[spec.catalog_name] = SpecApplyStats(
            catalog_name=spec.catalog_name,
            fixture_count=build.fixture_count,
            games_linked=link.linked,
            orphan_games=link.orphans,
        )
        applied.append(spec.catalog_name)
        log.info(
            "Applied structure %r: %s fixtures, %s games linked",
            spec.catalog_name,
            build.fixture_count,
            link.linked,
        )

    return ApplyResult(
        applied=tuple(applied),
        skipped=tuple(skipped),
        stats_by_name=stats_by_name,
    )


def structure_specs_manifest(result: ApplyResult | None = None) -> list[dict[str, Any]]:
    """Manifest rows for transforms.structure_specs."""
    rows: list[dict[str, Any]] = []
    from scripts.amiga.tournament_structure.registry import all_registry_entries

    for entry_row in all_registry_entries():
        spec = entry_row.spec
        entry: dict[str, Any] = {
            "catalog_name": spec.catalog_name,
            "template_slug": spec.template_slug,
            "registry_status": entry_row.status,
        }
        if entry_row.notes:
            entry["notes"] = entry_row.notes
        if spec.evidence_url:
            entry["evidence_url"] = spec.evidence_url
        if result is not None:
            if entry_row.status != "active":
                entry["applied"] = False
                entry["skip_reason"] = f"registry_{entry_row.status}"
            elif spec.catalog_name in result.applied:
                entry["applied"] = True
                stats = result.stats_by_name.get(spec.catalog_name)
                if stats is not None:
                    entry["fixture_count"] = stats.fixture_count
                    entry["games_linked"] = stats.games_linked
            elif spec.catalog_name in result.skipped:
                entry["applied"] = False
                entry["skip_reason"] = "not_in_catalog"
        rows.append(entry)
    return rows
