"""Verify structure specs against Access ground truth (pre-import audit)."""

from __future__ import annotations

import argparse
import json
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from scripts.amiga.import_access import (
    _DEFAULT_MDB,
    apply_name_map,
    connect_access,
    load_access_scores,
    load_access_tournaments,
)
from scripts.amiga.player_names import build_canonical_name_map
from scripts.amiga.tournament_names import resolve_tournament_name
from scripts.amiga.tournament_structure.build import _expand_group_fixtures, _expand_knockout_fixtures
from scripts.amiga.tournament_structure.link import link_games_to_fixtures
from scripts.amiga.tournament_structure.registry import (
    all_registry_entries,
    registry_entry_for_catalog,
)
from scripts.amiga.tournament_structure.specs import StructureSpec, is_round_robin_group_stage


@dataclass
class VerifyResult:
    catalog_name: str
    status: str
    ok: bool
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    access_game_count: int = 0
    planned_fixture_count: int = 0
    access_player_count: int = 0

    def to_dict(self) -> dict[str, Any]:
        return {
            "catalog_name": self.catalog_name,
            "registry_status": self.status,
            "ok": self.ok,
            "errors": self.errors,
            "warnings": self.warnings,
            "access_game_count": self.access_game_count,
            "planned_fixture_count": self.planned_fixture_count,
            "access_player_count": self.access_player_count,
        }


def _access_games_for_catalog(mdb: Path, catalog_name: str) -> tuple[list, dict[str, int]]:
    acc = connect_access(mdb)
    cur = acc.cursor()
    scores = load_access_scores(cur)
    acc.close()
    raw_to_canonical, _ = build_canonical_name_map(scores, countries={})
    apply_name_map(scores, raw_to_canonical)

    games = [
        s
        for s in scores
        if resolve_tournament_name(s.raw_tournament) == catalog_name
    ]
    player_names = sorted({s.team_a for s in games} | {s.team_b for s in games})
    player_id = {name: idx + 1 for idx, name in enumerate(player_names)}
    return games, player_id


def _catalog_exists(mdb: Path, catalog_name: str) -> bool:
    acc = connect_access(mdb)
    cur = acc.cursor()
    tournaments = load_access_tournaments(cur)
    acc.close()
    return any(t["name"] == catalog_name for t in tournaments)


def count_planned_fixtures(spec: StructureSpec, player_id: dict[str, int]) -> int:
    return len(_expand_group_fixtures(spec, player_id)) + len(_expand_knockout_fixtures(spec))


def verify_structure_spec(
    spec: StructureSpec,
    *,
    registry_status: str = "active",
    mdb: Path = _DEFAULT_MDB,
) -> VerifyResult:
    """Validate a spec against Access scores (no MySQL required)."""
    result = VerifyResult(
        catalog_name=spec.catalog_name,
        status=registry_status,
        ok=True,
    )

    if registry_status == "stub":
        result.errors.append(f"{spec.catalog_name!r} is registered as stub — not import-ready")
        result.ok = False

    if not _catalog_exists(mdb, spec.catalog_name):
        result.errors.append(f"catalog {spec.catalog_name!r} not found in Access")
        result.ok = False
        return result

    games, player_id = _access_games_for_catalog(mdb, spec.catalog_name)
    result.access_game_count = len(games)
    result.access_player_count = len(player_id)

    group_stages = [s for s in spec.stages if is_round_robin_group_stage(s)]
    if spec.template_slug == "group_knockout" and not group_stages:
        result.errors.append("group_knockout spec has no round_robin group stages")
        result.ok = False

    roster_names: set[str] = set()
    for stage in group_stages:
        for roster in stage.groups:
            roster_names.update(roster.player_names)

    missing_players = sorted(name for name in roster_names if name not in player_id)
    if missing_players:
        result.errors.append(
            f"roster players missing from Access scores: {missing_players[:5]}"
            + (f" (+{len(missing_players) - 5} more)" if len(missing_players) > 5 else "")
        )
        result.ok = False

    extra_roster = sorted(name for name in player_id if roster_names and name not in roster_names)
    if roster_names and extra_roster:
        result.warnings.append(
            f"{len(extra_roster)} Access player(s) not in any group roster"
        )

    try:
        result.planned_fixture_count = count_planned_fixtures(spec, player_id)
    except ValueError as exc:
        result.errors.append(str(exc))
        result.ok = False
        return result

    if result.planned_fixture_count == 0 and result.access_game_count > 0:
        result.errors.append(
            f"planned fixture count is 0 but Access has {result.access_game_count} games"
        )
        result.ok = False

    if result.planned_fixture_count != result.access_game_count:
        result.errors.append(
            f"fixture/game mismatch: planned {result.planned_fixture_count}, "
            f"Access {result.access_game_count}"
        )
        result.ok = False

    if result.planned_fixture_count > 0 and games:
        from scripts.amiga.tournament_structure.build import BuiltFixture, StructureBuildResult

        planned = _expand_group_fixtures(spec, player_id) + _expand_knockout_fixtures(spec)
        built = [
            BuiltFixture(
                fixture_id=idx + 1,
                player_a_id=player_id[str(fx.player_a)],
                player_b_id=player_id[str(fx.player_b)],
                stage_key=stage_key,
                leg_no=fx.leg_no,
            )
            for idx, (stage_key, _label, fx) in enumerate(planned)
        ]
        build = StructureBuildResult(
            tournament_id=1,
            catalog_name=spec.catalog_name,
            fixtures=tuple(built),
            fixture_count=len(built),
            stage_count=len(spec.stages),
        )
        game_rows = [
            {
                "tournament_id": 1,
                "player_a_id": player_id[s.team_a],
                "player_b_id": player_id[s.team_b],
            }
            for s in sorted(games, key=lambda g: g.source_id)
        ]
        link = link_games_to_fixtures(game_rows, tournament_id=1, build=build)
        if link.orphans:
            result.errors.append(f"{link.orphans} Access game(s) could not be linked to planned fixtures")
            result.ok = False

    return result


def verify_catalog(catalog_name: str, *, mdb: Path = _DEFAULT_MDB) -> VerifyResult:
    entry = registry_entry_for_catalog(catalog_name)
    if entry is None:
        return VerifyResult(
            catalog_name=catalog_name,
            status="unregistered",
            ok=False,
            errors=[f"no structure spec registered for {catalog_name!r}"],
        )
    return verify_structure_spec(entry.spec, registry_status=entry.status, mdb=mdb)


def list_registry(*, mdb: Path = _DEFAULT_MDB) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    for entry in all_registry_entries():
        spec = entry.spec
        row: dict[str, Any] = {
            "catalog_name": spec.catalog_name,
            "template_slug": spec.template_slug,
            "status": entry.status,
            "group_stages": sum(1 for s in spec.stages if is_round_robin_group_stage(s)),
            "knockout_fixtures": len(spec.fixtures),
            "evidence_url": spec.evidence_url,
            "notes": entry.notes,
        }
        if _catalog_exists(mdb, spec.catalog_name):
            games, player_id = _access_games_for_catalog(mdb, spec.catalog_name)
            row["access_games"] = len(games)
            row["access_players"] = len(player_id)
            try:
                row["planned_fixtures"] = count_planned_fixtures(spec, player_id)
            except ValueError:
                row["planned_fixtures"] = 0
        else:
            row["access_games"] = None
            row["planned_fixtures"] = None
        rows.append(row)
    return rows


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Tournament structure spec registry")
    sub = parser.add_subparsers(dest="cmd", required=True)

    sub.add_parser("list", help="List registered structure specs")

    p_verify = sub.add_parser("verify", help="Verify one registered spec against Access")
    p_verify.add_argument("--tournament", required=True, help="Canonical catalog name")
    p_verify.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_verify.add_argument("--json", action="store_true")

    args = parser.parse_args(argv)

    if args.cmd == "list":
        rows = list_registry()
        print(json.dumps({"structure_specs": rows, "count": len(rows)}, indent=2))
        return 0

    if args.cmd == "verify":
        result = verify_catalog(args.tournament, mdb=args.mdb)
        if args.json:
            print(json.dumps(result.to_dict(), indent=2))
        else:
            if result.ok:
                print(
                    f"OK: {result.catalog_name} — {result.planned_fixture_count} fixtures, "
                    f"{result.access_game_count} Access games"
                )
            else:
                print(f"FAIL: {result.catalog_name}", file=sys.stderr)
                for err in result.errors:
                    print(f"  - {err}", file=sys.stderr)
                for warn in result.warnings:
                    print(f"  warn: {warn}", file=sys.stderr)
        return 0 if result.ok else 1

    return 1


if __name__ == "__main__":
    sys.exit(main())
