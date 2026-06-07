"""Manual catalog corrections applied at import — Access archival input stays unchanged."""

from __future__ import annotations

from datetime import date, datetime
from typing import Any

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


def access_reference_tournament_name(canonical_name: str) -> str:
    """Access [Tables].Tournament label for parity when it differs from canonical catalog name."""
    for access_name, canonical in TOURNAMENT_NAME_OVERRIDES.items():
        if canonical == canonical_name:
            return access_name
    return canonical_name


def _as_date(value: date | datetime | None) -> date | None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.date()
    return value


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

    return applied
