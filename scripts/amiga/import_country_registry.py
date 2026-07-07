"""L3 country token canonicalization against country_registry.json."""

from __future__ import annotations

from typing import Any

from scripts.amiga.country_registry import (
    canonicalize_country_token,
    official_names,
    registry_version,
    validate_official,
)


def _normalize_country_value(
    raw: object,
    *,
    entity: str,
    name: str,
    field: str,
    normalizations: list[dict[str, str]],
) -> str:
    access = str(raw or "").strip()
    if access == "":
        return ""
    canonical = canonicalize_country_token(access)
    if canonical != access:
        normalizations.append(
            {
                "entity": entity,
                "name": name,
                "field": field,
                "access": access,
                "canonical": canonical,
                "reason": "Registry legacy alias or official token (amiga-country-registry-policy.md CR11).",
            }
        )
    return canonical


def apply_country_registry_to_prepared(prepared: Any) -> list[dict[str, str]]:
    """
    Canonicalize player and tournament country fields on a WitnessPrepared object.

    Mutates prepared.countries and prepared.tournaments in place.
    """
    normalizations: list[dict[str, str]] = []

    for player, country in list(prepared.countries.items()):
        prepared.countries[player] = _normalize_country_value(
            country,
            entity="player",
            name=str(player),
            field="country",
            normalizations=normalizations,
        )

    for tournament in prepared.tournaments:
        tournament["country"] = _normalize_country_value(
            tournament.get("country"),
            entity="tournament",
            name=str(tournament.get("name") or ""),
            field="country",
            normalizations=normalizations,
        )

    validate_prepared_countries(prepared)
    return normalizations


def validate_prepared_countries(prepared: Any) -> None:
    """Fail import if any non-empty country is not a registry official_name."""
    invalid: list[str] = []
    official = official_names()

    for player, country in prepared.countries.items():
        token = str(country or "").strip()
        if token and token not in official:
            invalid.append(f"player {player!r}: {token!r}")

    for tournament in prepared.tournaments:
        token = str(tournament.get("country") or "").strip()
        if token and token not in official:
            invalid.append(f"tournament {tournament.get('name')!r}: {token!r}")

    if invalid:
        raise SystemExit(
            "Country token(s) not in registry after canonicalize:\n  - "
            + "\n  - ".join(sorted(invalid))
        )


def registry_manifest_metadata() -> dict[str, int | str]:
    return {
        "version": registry_version(),
    }
