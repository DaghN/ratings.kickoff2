"""Datatypes for version-controlled tournament structure definitions."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True, slots=True)
class GroupRosterSpec:
    """Players assigned to a group within a league stage."""

    group_key: str
    player_names: tuple[str, ...] = ()

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> GroupRosterSpec:
        names = data.get("player_names") or data.get("players") or []
        return cls(
            group_key=str(data["group_key"]),
            player_names=tuple(str(n) for n in names),
        )

    def to_dict(self) -> dict[str, Any]:
        out: dict[str, Any] = {"group_key": self.group_key}
        if self.player_names:
            out["player_names"] = list(self.player_names)
        return out


@dataclass(frozen=True, slots=True)
class FixtureSpec:
    """One schedulable match slot (single leg or one leg of a tie)."""

    fixture_key: str
    stage_key: str
    player_a: str | None = None
    player_b: str | None = None
    leg_no: int = 1
    group_key: str | None = None
    round_key: str | None = None

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> FixtureSpec:
        return cls(
            fixture_key=str(data["fixture_key"]),
            stage_key=str(data["stage_key"]),
            player_a=data.get("player_a"),
            player_b=data.get("player_b"),
            leg_no=int(data.get("leg_no", 1)),
            group_key=data.get("group_key"),
            round_key=data.get("round_key"),
        )

    def to_dict(self) -> dict[str, Any]:
        out: dict[str, Any] = {
            "fixture_key": self.fixture_key,
            "stage_key": self.stage_key,
            "leg_no": self.leg_no,
        }
        if self.player_a is not None:
            out["player_a"] = self.player_a
        if self.player_b is not None:
            out["player_b"] = self.player_b
        if self.group_key is not None:
            out["group_key"] = self.group_key
        if self.round_key is not None:
            out["round_key"] = self.round_key
        return out


@dataclass(frozen=True, slots=True)
class StageSpec:
    """One tournament stage (group league, knockout bracket, etc.)."""

    stage_key: str
    name: str
    stage_type: str
    group_keys: tuple[str, ...] = ()
    round_keys: tuple[str, ...] = ()
    groups: tuple[GroupRosterSpec, ...] = ()

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> StageSpec:
        groups = tuple(GroupRosterSpec.from_dict(g) for g in data.get("groups", []))
        return cls(
            stage_key=str(data["stage_key"]),
            name=str(data.get("name", data["stage_key"])),
            stage_type=str(data["stage_type"]),
            group_keys=tuple(str(k) for k in data.get("group_keys", ())),
            round_keys=tuple(str(k) for k in data.get("round_keys", ())),
            groups=groups,
        )

    def to_dict(self) -> dict[str, Any]:
        out: dict[str, Any] = {
            "stage_key": self.stage_key,
            "name": self.name,
            "stage_type": self.stage_type,
        }
        if self.group_keys:
            out["group_keys"] = list(self.group_keys)
        if self.round_keys:
            out["round_keys"] = list(self.round_keys)
        if self.groups:
            out["groups"] = [g.to_dict() for g in self.groups]
        return out


@dataclass(frozen=True, slots=True)
class StructureSpec:
    """Full structure definition for one catalog tournament."""

    catalog_name: str
    template_slug: str
    evidence_url: str | None = None
    stages: tuple[StageSpec, ...] = ()
    fixtures: tuple[FixtureSpec, ...] = ()
    format_overrides: dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> StructureSpec:
        stages = tuple(StageSpec.from_dict(s) for s in data.get("stages", []))
        fixtures = tuple(FixtureSpec.from_dict(f) for f in data.get("fixtures", []))
        return cls(
            catalog_name=str(data["catalog_name"]),
            template_slug=str(data["template_slug"]),
            evidence_url=data.get("evidence_url"),
            stages=stages,
            fixtures=fixtures,
            format_overrides=dict(data.get("format_overrides", {})),
        )

    def to_dict(self) -> dict[str, Any]:
        out: dict[str, Any] = {
            "catalog_name": self.catalog_name,
            "template_slug": self.template_slug,
        }
        if self.evidence_url:
            out["evidence_url"] = self.evidence_url
        if self.stages:
            out["stages"] = [s.to_dict() for s in self.stages]
        if self.fixtures:
            out["fixtures"] = [f.to_dict() for f in self.fixtures]
        if self.format_overrides:
            out["format_overrides"] = dict(self.format_overrides)
        return out


def parse_structure_spec(data: dict[str, Any]) -> StructureSpec:
    """Parse a structure spec from JSON or Python dict."""
    if "catalog_name" not in data:
        raise ValueError("structure spec missing catalog_name")
    if "template_slug" not in data:
        raise ValueError("structure spec missing template_slug")
    return StructureSpec.from_dict(data)
