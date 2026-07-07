"""Build data/amiga/country_registry.json from vendored flag-icons country.json."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_SOURCE = REPO_ROOT / "data" / "amiga" / "iso3166" / "flag-icons-country.json"
VENDOR_SOURCE = REPO_ROOT / "data" / "vendor" / "flag-icons" / "country.json"
OUTPUT_PATH = REPO_ROOT / "data" / "amiga" / "country_registry.json"
SITE_OUTPUT_PATH = REPO_ROOT / "site" / "public_html" / "data" / "amiga" / "country_registry.json"
REGISTRY_VERSION = 1
FLAG_ICONS_VERSION = "main-2026-07-07"

# Explicit KOO official names (policy CR7, CR8). Key = flag-icons code.
OFFICIAL_NAME_OVERRIDES: dict[str, str] = {
    "tw": "Taiwan",
    "ie": "Ireland",
    "tr": "Turkey",
}

# Extra non-ISO choosable codes from flag-icons (beyond iso=true set).
EXTRA_CHOOSABLE_CODES: tuple[str, ...] = (
    "gb-eng",
    "gb-sct",
    "gb-wls",
    "gb-nir",
    "xk",  # Kosovo — FIFA; flag-icons has xk
)

# United Kingdom sovereign — not choosable (policy CR6).
EXCLUDED_CHOOSABLE_CODES: frozenset[str] = frozenset({"gb"})

# Legacy L3 aliases -> official_name patches after row build.
LEGACY_ALIASES_BY_OFFICIAL: dict[str, list[str]] = {
    "Northern Ireland": ["N. Ireland"],
    "United Arab Emirates": ["UAE"],
}

SITE_SHORTHAND_BY_OFFICIAL: dict[str, str] = {
    "United Arab Emirates": "UAE",
}


def _source_path(explicit: Path | None) -> Path:
    if explicit is not None:
        return explicit
    if VENDOR_SOURCE.is_file():
        return VENDOR_SOURCE
    return DEFAULT_SOURCE


def _load_flag_icons_countries(path: Path) -> list[dict[str, Any]]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        raise SystemExit(f"Expected JSON array in {path}")
    return [row for row in data if isinstance(row, dict) and row.get("code")]


def _row_from_flag_icons(entry: dict[str, Any], *, choosable: bool) -> dict[str, Any]:
    code = str(entry["code"]).strip().lower()
    name = OFFICIAL_NAME_OVERRIDES.get(code, str(entry["name"]).strip())
    row: dict[str, Any] = {
        "official_name": name,
        "flag_code": code,
        "legacy_aliases": list(LEGACY_ALIASES_BY_OFFICIAL.get(name, [])),
        "site_shorthand": SITE_SHORTHAND_BY_OFFICIAL.get(name),
        "choosable": choosable,
    }
    return row


def build_registry(source: Path) -> dict[str, Any]:
    entries = _load_flag_icons_countries(source)
    by_code = {str(e["code"]).strip().lower(): e for e in entries}

    rows: list[dict[str, Any]] = []
    seen_names: set[str] = set()
    seen_codes: set[str] = set()

    def add_row(entry: dict[str, Any], *, choosable: bool) -> None:
        code = str(entry["code"]).strip().lower()
        row = _row_from_flag_icons(entry, choosable=choosable)
        name = row["official_name"]
        if name in seen_names:
            raise SystemExit(f"Duplicate official_name while building: {name!r}")
        if code in seen_codes:
            raise SystemExit(f"Duplicate flag_code while building: {code!r}")
        seen_names.add(name)
        seen_codes.add(code)
        rows.append(row)

    for entry in entries:
        code = str(entry["code"]).strip().lower()
        if not entry.get("iso"):
            continue
        choosable = code not in EXCLUDED_CHOOSABLE_CODES
        add_row(entry, choosable=choosable)

    for code in EXTRA_CHOOSABLE_CODES:
        if code in EXCLUDED_CHOOSABLE_CODES:
            continue
        entry = by_code.get(code)
        if entry is None:
            raise SystemExit(f"Missing flag-icons entry for extra code: {code}")
        if code in seen_codes:
            continue
        add_row(entry, choosable=True)

    rows.sort(key=lambda r: str(r["official_name"]).casefold())

    return {
        "version": REGISTRY_VERSION,
        "built_from": "lipis/flag-icons country.json + KOO manual rules (amiga-country-registry-policy.md)",
        "flag_icons_version": FLAG_ICONS_VERSION,
        "countries": rows,
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Build Amiga country_registry.json")
    parser.add_argument(
        "--source",
        type=Path,
        default=None,
        help="flag-icons country.json (default: vendor path or data/amiga/iso3166 snapshot)",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=OUTPUT_PATH,
        help="Output registry JSON path",
    )
    args = parser.parse_args(argv)
    source = _source_path(args.source)
    if not source.is_file():
        raise SystemExit(f"flag-icons country source missing: {source}")
    registry = build_registry(source)
    payload = json.dumps(registry, indent=2, ensure_ascii=False) + "\n"
    for out_path in (args.output, SITE_OUTPUT_PATH):
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text(payload, encoding="utf-8")
    choosable = sum(1 for r in registry["countries"] if r.get("choosable"))
    print(
        f"Wrote {args.output} + {SITE_OUTPUT_PATH} "
        f"({len(registry['countries'])} rows, {choosable} choosable)"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())