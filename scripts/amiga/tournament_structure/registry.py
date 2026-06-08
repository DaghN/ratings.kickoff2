"""Central registry of tournament structure specs (import applies active entries only)."""

from __future__ import annotations

from dataclasses import dataclass

from scripts.amiga.tournament_structure.homburg import HOMEBURG_SPEC
from scripts.amiga.tournament_structure.specs import StructureSpec

# Stub: Athens LXI is a real suspicious-marathon candidate (9 players, NULL phases)
# with no structure defined yet — verify must fail gracefully.
ATHENS_LXI_STUB_SPEC = StructureSpec(
    catalog_name="Athens LXI",
    template_slug="group_knockout",
    evidence_url=None,
    format_overrides={"status": "stub", "notes": "placeholder — add stages/fixtures before enabling"},
)


@dataclass(frozen=True, slots=True)
class RegistryEntry:
    spec: StructureSpec
    status: str  # "active" | "stub"
    notes: str | None = None


REGISTRY_ENTRIES: tuple[RegistryEntry, ...] = (
    RegistryEntry(HOMEBURG_SPEC, "active", notes="German Championships 2004 — forum t=7711"),
    RegistryEntry(
        ATHENS_LXI_STUB_SPEC,
        "stub",
        notes="Candidate backfill — spec incomplete (Slice C placeholder)",
    ),
)


def all_registry_entries() -> tuple[RegistryEntry, ...]:
    return REGISTRY_ENTRIES


def active_structure_specs() -> tuple[StructureSpec, ...]:
    """Specs applied during import."""
    return tuple(entry.spec for entry in REGISTRY_ENTRIES if entry.status == "active")


def all_structure_specs() -> tuple[StructureSpec, ...]:
    """All registered specs (active + stub) for list/verify."""
    return tuple(entry.spec for entry in REGISTRY_ENTRIES)


def registry_entry_for_catalog(catalog_name: str) -> RegistryEntry | None:
    for entry in REGISTRY_ENTRIES:
        if entry.spec.catalog_name == catalog_name:
            return entry
    return None


def structure_spec_for_catalog(catalog_name: str) -> StructureSpec | None:
    entry = registry_entry_for_catalog(catalog_name)
    return entry.spec if entry is not None else None


def registry_status_for_catalog(catalog_name: str) -> str | None:
    entry = registry_entry_for_catalog(catalog_name)
    return entry.status if entry is not None else None
