#!/usr/bin/env python3
"""
Audit Access tournament catalog: chrono vs event_date inversions.

Reads raw koatd.mdb (before import_corrections). Fails if new inversions
appear that are not covered by TOURNAMENT_EVENT_DATE_OVERRIDES.
"""

from __future__ import annotations

import re
import sys
from datetime import date, datetime
from pathlib import Path

import pyodbc

from scripts.amiga.import_access import _DEFAULT_MDB
from scripts.amiga.import_corrections import TOURNAMENT_EVENT_DATE_OVERRIDES

_ROMAN = {
    "I": 1, "II": 2, "III": 3, "IV": 4, "V": 5, "VI": 6, "VII": 7, "VIII": 8,
    "IX": 9, "X": 10, "XI": 11, "XII": 12, "XIII": 13, "XIV": 14, "XV": 15,
    "XVI": 16, "XVII": 17, "XVIII": 18, "XIX": 19, "XX": 20, "XXI": 21,
    "XXII": 22, "XXIII": 23, "XXIV": 24, "XXV": 25,
}
_SERIES_RE = re.compile(
    r"^(.+?)\s+(I{1,3}|IV|VI{0,3}|IX|X{1,3}|XIV|XVI{0,3}|XIX|XX|XXI{1,3}|XXIV)$"
)


def _as_date(value: date | datetime | None) -> date | None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.date()
    return value


def load_access_tournaments(mdb: Path) -> list[dict]:
    conn = pyodbc.connect(
        f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={mdb.resolve()};"
    )
    cur = conn.cursor()
    cur.execute(
        "SELECT Tournament, Chrono, [Date] FROM [Tournament players] ORDER BY Chrono"
    )
    rows: list[dict] = []
    for name, chrono, dt in cur.fetchall():
        rows.append(
            {
                "name": str(name).strip(),
                "chrono": float(chrono) if chrono is not None else None,
                "event_date": _as_date(dt),
            }
        )
    conn.close()
    return rows


def adjacent_chrono_inversions(tournaments: list[dict]) -> list[dict]:
    out: list[dict] = []
    for i in range(1, len(tournaments)):
        prev_row, row = tournaments[i - 1], tournaments[i]
        if prev_row["chrono"] is None or row["chrono"] is None:
            continue
        prev_date, cur_date = prev_row["event_date"], row["event_date"]
        if prev_date and cur_date and cur_date < prev_date:
            out.append(
                {
                    "prev_name": prev_row["name"],
                    "prev_date": prev_date.isoformat(),
                    "prev_chrono": prev_row["chrono"],
                    "name": row["name"],
                    "date": cur_date.isoformat(),
                    "chrono": row["chrono"],
                    "gap_days": (prev_date - cur_date).days,
                }
            )
    return out


def roman_series_violations(tournaments: list[dict]) -> list[dict]:
    by_prefix: dict[str, list[tuple[int, date, str]]] = {}
    for row in tournaments:
        match = _SERIES_RE.match(row["name"])
        if not match or row["event_date"] is None:
            continue
        prefix, roman = match.group(1), match.group(2)
        num = _ROMAN.get(roman)
        if num is None:
            continue
        by_prefix.setdefault(prefix, []).append((num, row["event_date"], row["name"]))

    out: list[dict] = []
    for prefix, items in sorted(by_prefix.items()):
        items.sort(key=lambda item: item[0])
        for i in range(1, len(items)):
            _, prev_date, prev_name = items[i - 1]
            _, cur_date, cur_name = items[i]
            if cur_date < prev_date:
                out.append(
                    {
                        "series": prefix,
                        "lower": prev_name,
                        "lower_date": prev_date.isoformat(),
                        "higher": cur_name,
                        "higher_date": cur_date.isoformat(),
                    }
                )
    return out


def main(argv: list[str] | None = None) -> int:
    mdb = _DEFAULT_MDB
    if not mdb.is_file():
        print(f"Missing Access source: {mdb}", file=sys.stderr)
        return 1

    tournaments = load_access_tournaments(mdb)
    inversions = adjacent_chrono_inversions(tournaments)
    roman_bad = roman_series_violations(tournaments)
    overridden = set(TOURNAMENT_EVENT_DATE_OVERRIDES)

    print(f"Tournaments in Access: {len(tournaments)}")
    print(
        f"\nAdjacent chrono/date inversions (calendar backward vs prior chrono): "
        f"{len(inversions)}"
    )
    unhandled: list[dict] = []
    for inv in inversions:
        covered = {inv["name"], inv["prev_name"]} & overridden
        tag = "override registered" if covered else "NO OVERRIDE"
        if not covered:
            unhandled.append(inv)
        print(
            f"  [{tag}] chrono {inv['prev_chrono']:.0f} {inv['prev_name']} "
            f"({inv['prev_date']}) -> chrono {inv['chrono']:.0f} {inv['name']} "
            f"({inv['date']})  [{inv['gap_days']}d]"
        )

    print(f"\nRoman-series date violations (N+1 before N): {len(roman_bad)}")
    for item in roman_bad:
        print(
            f"  {item['series']}: {item['lower']} ({item['lower_date']}) "
            f"after {item['higher']} ({item['higher_date']})"
        )

    if unhandled:
        print(
            f"\nFAIL: {len(unhandled)} inversion(s) without import_corrections entry",
            file=sys.stderr,
        )
        return 1

    print(
        f"\nOK: {len(inversions)} known inversion(s), all covered by import_corrections.py"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
