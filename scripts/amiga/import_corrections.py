"""Manual catalog corrections applied at import — Access archival input stays unchanged."""

from __future__ import annotations

import re
from dataclasses import dataclass
from datetime import date, datetime
from typing import Any

# Reserved source_scores_id range for import supplements (Access max ~28k; live ops use >= 1e9).
IMPORT_SUPPLEMENT_SCORES_ID_BASE = 500_000_000

# Reserved tournaments.source_id range for synthetic catalog splits (append-only at import).
IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE = 900_000_000

# Access catalog name → canonical name (Roman series, etc.).
# Scores rows use the Access label — also wired via tournament_names.TOURNAMENT_ALIASES.
TOURNAMENT_NAME_OVERRIDES: dict[str, str] = {
    "World Cup 2015": "World Cup XV",
}

# Tournament name → canonical event_date (calendar day).
# Add entries only with documented evidence; see docs/amiga-import-layer.md.
TOURNAMENT_EVENT_DATE_OVERRIDES: dict[str, date] = {
    "World Cup VIII": date(2008, 11, 9),
    "Wiesbaden IX": date(2009, 1, 25),
}

# Access labels every World Cup host as Country='WC'. Canonical catalog uses real host nations
# and appends the host city to the Roman-series name (I–XXIII).
WORLD_CUP_VENUES: dict[str, tuple[str, str]] = {
    "World Cup I": ("Dartford", "England"),
    "World Cup II": ("Athens", "Greece"),
    "World Cup III": ("Groningen", "Netherlands"),
    "World Cup IV": ("Milan", "Italy"),
    "World Cup V": ("Cologne", "Germany"),
    "World Cup VI": ("Rickmansworth", "England"),
    "World Cup VII": ("Rome", "Italy"),
    "World Cup VIII": ("Athens", "Greece"),
    "World Cup IX": ("Voitsberg", "Austria"),
    "World Cup X": ("Dusseldorf", "Germany"),
    "World Cup XI": ("Birmingham", "England"),
    "World Cup XII": ("Milan", "Italy"),
    "World Cup XIII": ("Voitsberg", "Austria"),
    "World Cup XIV": ("Copenhagen", "Denmark"),
    "World Cup XV": ("Dublin", "Ireland"),
    "World Cup XVI": ("Milan", "Italy"),
    "World Cup XVII": ("Landskrona", "Sweden"),
    "World Cup XVIII": ("Bournemouth", "England"),
    "World Cup XIX": ("Bremen", "Germany"),
    "World Cup XX": ("Athens", "Greece"),
    "World Cup XXI": ("Torremolinos", "Spain"),
    "World Cup XXII": ("Nottingham", "England"),
    "World Cup XXIII": ("Milan", "Italy"),
}

WORLD_CUP_VENUE_RATIONALE = (
    "Access [Tournament players].Country is the placeholder 'WC' for all World Cups; "
    "canonical catalog uses the real host nation and appends the host city to the name."
)

# Canonical player display name → country when L2 witness_player_identity has no row (or wrong).
# Keys must match post-merge canonical names (player_names.py).
PLAYER_COUNTRY_OVERRIDES: dict[str, str] = {
    "Diego L": "Italy",
    "Ingvald E": "Norway",
    "Kjetil D": "Norway",
    "Oyvind H": "Norway",
}

PLAYER_COUNTRY_RATIONALE: dict[str, str] = {
    "Diego L": "Italian player; missing from L2 witness_player_identity.",
    "Ingvald E": "Norwegian player; missing from L2 witness_player_identity.",
    "Kjetil D": "Norwegian player; missing from L2 witness_player_identity.",
    "Oyvind H": "Norwegian player; missing from L2 witness_player_identity.",
}

# Access spelling variant → canonical display name (forced winner in name merge groups).
PLAYER_NAME_ALIASES: dict[str, str] = {
    "Ian Ka": "Ian K",
    "Joerg D": "Jorg D",
    "Joerg S": "Jorg S",
    "Klaus L": "Klaus Le",
}

PLAYER_NAME_ALIAS_RATIONALE: dict[str, str] = {
    "Ian Ka": "Same player as Ian K; Access extended surname abbreviation.",
    "Joerg D": "Same player as Jorg D; Access umlaut spelling (oe) variant.",
    "Joerg S": "Same player as Jorg S; Access umlaut spelling (oe) variant.",
    "Klaus L": "Same player as Klaus Le; Access shorter surname abbreviation.",
}

OVERRIDE_RATIONALE: dict[str, str] = {
    "World Cup 2015": (
        "Access [Tournament players].Tournament and Scores.Tournament use year label "
        "'World Cup 2015'; chrono 548 sits between World Cup XIV and World Cup XVI. "
        "Access group reference table is already 'World Cup XV Tables'. Canonical "
        "catalog name is World Cup XV."
    ),
    "World Cup VIII": (
        "Access [Tournament players].Date is 2008-09-08; chrono 325 sits between "
        "Newent XIV (2008-11-03) and Helsingborg I (2008-11-14). Real-world event "
        "was 9 November 2008."
    ),
    "Wiesbaden IX": (
        "Access Date is 2009-04-07; chrono 333 is before Wiesbaden X (335, 2009-02-22) "
        "and Newent XVI (334, 2009-02-13). Roman-numeral order requires IX before X. "
        "Canonical date 2009-01-25 from KO Gathering forum: "
        "https://ko-gathering.com/forum/viewtopic.php?p=247684#p247684"
    ),
}


@dataclass(frozen=True)
class CatalogSplit:
    """Scores-only competition split from a parent Access catalog row (append at import)."""

    name: str
    parent_name: str
    source_id: int
    chrono_offset: float = 0.5
    is_cup: bool = True
    player_count: int | None = None


# Synthetic catalog rows appended after Access load — never insert in the middle of the list.
IMPORT_CATALOG_SPLITS: tuple[CatalogSplit, ...] = (
    CatalogSplit(
        name="Groningen VII Cup",
        parent_name="Groningen VII",
        source_id=900_000_001,
        chrono_offset=0.5,
        is_cup=True,
        player_count=8,
    ),
    CatalogSplit(
        name="Gloucester III Team",
        parent_name="Gloucester III",
        source_id=900_000_002,
        chrono_offset=0.5,
        is_cup=False,
        player_count=10,
    ),
)

CATALOG_SPLIT_RATIONALE: dict[str, str] = {
    "Groningen VII Cup": (
        "Access [Tournament players] has one row for Groningen VII (2002-07-13) but Scores "
        "uses a separate Tournament label for a 14-game cup (Round 1 / Semi Final / Final). "
        "Split via synthetic catalog row; main event keeps id 48."
    ),
    "Gloucester III Team": (
        "Access [Tournament players] has one row for Gloucester III (2002-10-12, 10 players) "
        "but Scores uses label Gloucester III Team for 10 additional games (IDs contiguous "
        "after the 90-game double round-robin). Split via synthetic catalog row; main keeps id 62."
    ),
}


@dataclass(frozen=True)
class SupplementalScore:
    """One game row absent from Access Scores but documented from external evidence."""

    tournament: str
    team_a: str
    team_b: str
    goals_a: int
    goals_b: int
    phase: str | None = None
    extra: str | None = None


# Games missing from koatd Scores — appended at import with reserved source_scores_id.
# Order within a tournament is the forum / evidence order (affects synthetic game_date).
SUPPLEMENTAL_SCORES: tuple[SupplementalScore, ...] = (
    # Rodenbach II (2012-08-12): Access catalog row, zero Scores rows. Five-player round-robin.
    SupplementalScore("Rodenbach II", "Frank F", "Horst L", 5, 2),
    SupplementalScore("Rodenbach II", "Joerg D", "Jan K", 1, 2),
    SupplementalScore("Rodenbach II", "Joerg D", "Thorsten B", 0, 8),
    SupplementalScore("Rodenbach II", "Jan K", "Horst L", 1, 2),
    SupplementalScore("Rodenbach II", "Jan K", "Frank F", 2, 8),
    SupplementalScore("Rodenbach II", "Horst L", "Thorsten B", 1, 7),
    SupplementalScore("Rodenbach II", "Horst L", "Joerg D", 6, 0),
    SupplementalScore("Rodenbach II", "Thorsten B", "Frank F", 2, 5),
    SupplementalScore("Rodenbach II", "Thorsten B", "Jan K", 5, 1),
    SupplementalScore("Rodenbach II", "Frank F", "Joerg D", 13, 0),
)

SUPPLEMENT_RATIONALE: dict[str, str] = {
    "Rodenbach II": (
        "Access [Tournament players] lists Rodenbach II (2012-08-12, 5 players, chrono 513) but "
        "Scores has zero rows. Results recovered from the original KO Gathering forum thread "
        "(10 games = complete round-robin among Frank F, Horst L, Joerg D, Jan K, Thorsten B)."
    ),
}


_WORLD_CUP_CITY_SUFFIX = re.compile(r"^(World Cup\s+\S+)\s+\([^)]+\)$")


def strip_world_cup_city_suffix(name: str) -> str:
    """``World Cup XV (Dublin)`` → ``World Cup XV`` for Access reference lookups."""
    m = _WORLD_CUP_CITY_SUFFIX.match(name.strip())
    return m.group(1) if m else name


def world_cup_catalog_name(base_name: str) -> str:
    """Roman-series World Cup name with host city suffix."""
    city, _country = WORLD_CUP_VENUES[base_name]
    return f"{base_name} ({city})"


def world_cup_score_aliases() -> dict[str, str]:
    """Map bare Access Scores/catalog names to canonical World Cup catalog names."""
    return {base: world_cup_catalog_name(base) for base in WORLD_CUP_VENUES}


def catalog_name_after_corrections(access_name: str) -> str:
    """Expected MySQL tournaments.name after all import_corrections catalog patches."""
    name = TOURNAMENT_NAME_OVERRIDES.get(access_name, access_name)
    if name in WORLD_CUP_VENUES:
        return world_cup_catalog_name(name)
    return name


def access_reference_tournament_name(canonical_name: str) -> str:
    """Access [Tables].Tournament label for parity when it differs from canonical catalog name."""
    base = strip_world_cup_city_suffix(canonical_name)
    for access_name, canonical in TOURNAMENT_NAME_OVERRIDES.items():
        if canonical == base:
            return access_name
    return base


def _as_date(value: date | datetime | None) -> date | None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.date()
    return value


def apply_catalog_splits(tournaments: list[dict[str, Any]]) -> list[dict[str, str]]:
    """
    Append synthetic catalog rows for Scores-only split tournaments.

    Rows are added at the end of the in-memory list (MySQL ids append after existing catalog).
    """
    by_name = {t["name"]: t for t in tournaments}
    applied: list[dict[str, str]] = []

    for split in IMPORT_CATALOG_SPLITS:
        if split.name in by_name:
            raise ValueError(f"catalog split {split.name!r} already exists in Access catalog")
        parent = by_name.get(split.parent_name)
        if parent is None:
            raise ValueError(f"catalog split parent {split.parent_name!r} not found")
        parent_chrono = parent.get("chrono")
        if parent_chrono is None:
            raise ValueError(f"catalog split parent {split.parent_name!r} has no chrono")

        row = {
            "source_id": split.source_id,
            "name": split.name,
            "chrono": float(parent_chrono) + split.chrono_offset,
            "event_date": parent["event_date"],
            "is_cup": split.is_cup,
            "country": parent["country"],
            "equal_teams": parent.get("equal_teams", False),
            "player_count": (
                split.player_count
                if split.player_count is not None
                else parent.get("player_count")
            ),
        }
        tournaments.append(row)
        by_name[split.name] = row
        applied.append(
            {
                "tournament": split.name,
                "parent": split.parent_name,
                "source_id": split.source_id,
                "chrono": row["chrono"],
                "reason": CATALOG_SPLIT_RATIONALE.get(split.name, ""),
            }
        )

    return applied


def catalog_splits_manifest() -> list[dict[str, str | int | float]]:
    """Summary rows for import_manifest.json (one entry per split tournament)."""
    return [
        {
            "tournament": split.name,
            "parent": split.parent_name,
            "source_id": split.source_id,
            "reason": CATALOG_SPLIT_RATIONALE.get(split.name, ""),
        }
        for split in IMPORT_CATALOG_SPLITS
    ]


def apply_player_country_corrections(countries: dict[str, str]) -> list[dict[str, str]]:
    """
    Patch in-memory player→country map before amiga_players insert.

    Returns applied overrides for the import manifest (empty when identity already matches).
    """
    applied: list[dict[str, str]] = []

    for player, canonical in PLAYER_COUNTRY_OVERRIDES.items():
        access = (countries.get(player) or "").strip()
        if access == canonical:
            continue
        applied.append(
            {
                "player": player,
                "field": "country",
                "access": access,
                "canonical": canonical,
                "reason": PLAYER_COUNTRY_RATIONALE.get(player, ""),
            }
        )
        countries[player] = canonical

    return applied


def apply_catalog_corrections(tournaments: list[dict[str, Any]]) -> list[dict[str, str]]:
    """
    Patch in-memory tournament rows before MySQL insert.

    Returns applied overrides for the import manifest (empty when Access already matches).
    """
    by_name = {t["name"]: t for t in tournaments}
    applied: list[dict[str, str]] = []

    for access_name, canonical_name in TOURNAMENT_NAME_OVERRIDES.items():
        row = by_name.get(access_name)
        if row is None:
            continue
        if row["name"] == canonical_name:
            continue
        applied.append(
            {
                "tournament": access_name,
                "field": "name",
                "access": access_name,
                "canonical": canonical_name,
                "reason": OVERRIDE_RATIONALE.get(access_name, ""),
            }
        )
        del by_name[access_name]
        row["name"] = canonical_name
        by_name[canonical_name] = row

    for name, canonical in TOURNAMENT_EVENT_DATE_OVERRIDES.items():
        row = by_name.get(name)
        if row is None:
            continue
        access_date = _as_date(row.get("event_date"))
        if access_date == canonical:
            continue
        applied.append(
            {
                "tournament": name,
                "field": "event_date",
                "access": access_date.isoformat() if access_date else "",
                "canonical": canonical.isoformat(),
                "reason": OVERRIDE_RATIONALE.get(name, ""),
            }
        )
        row["event_date"] = canonical

    for base_name, (city, country) in WORLD_CUP_VENUES.items():
        row = by_name.get(base_name)
        if row is None:
            continue
        canonical_name = world_cup_catalog_name(base_name)
        access_country = str(row.get("country") or "").strip()
        if row["name"] != canonical_name:
            applied.append(
                {
                    "tournament": base_name,
                    "field": "name",
                    "access": base_name,
                    "canonical": canonical_name,
                    "reason": WORLD_CUP_VENUE_RATIONALE,
                }
            )
            del by_name[base_name]
            row["name"] = canonical_name
            by_name[canonical_name] = row
        if access_country != country:
            applied.append(
                {
                    "tournament": canonical_name,
                    "field": "country",
                    "access": access_country or "WC",
                    "canonical": country,
                    "reason": WORLD_CUP_VENUE_RATIONALE,
                }
            )
            row["country"] = country

    return applied


def supplemental_scores_manifest() -> list[dict[str, str | int]]:
    """Summary rows for import_manifest.json (one entry per supplemented tournament)."""
    by_tournament: dict[str, int] = {}
    for row in SUPPLEMENTAL_SCORES:
        by_tournament[row.tournament] = by_tournament.get(row.tournament, 0) + 1
    return [
        {
            "tournament": name,
            "games_added": count,
            "reason": SUPPLEMENT_RATIONALE.get(name, ""),
        }
        for name, count in sorted(by_tournament.items())
    ]
