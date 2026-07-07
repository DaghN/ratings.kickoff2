"""Sync Amiga country flag SVGs from vendored lipis/flag-icons 4x3 assets."""

from __future__ import annotations

import argparse
import shutil
from pathlib import Path

from scripts.amiga.country_registry import choosable_flag_codes, load_registry

REPO_ROOT = Path(__file__).resolve().parents[2]
VENDOR_4X3 = REPO_ROOT / "data" / "vendor" / "flag-icons" / "flags" / "4x3"
SITE_FLAGS = REPO_ROOT / "site" / "public_html" / "img" / "flags" / "amiga"


def sync_flags(*, dry_run: bool = False, include_non_choosable: bool = False) -> list[str]:
    if not VENDOR_4X3.is_dir():
        raise SystemExit(f"Missing vendored flag-icons 4x3 dir: {VENDOR_4X3}")

    if include_non_choosable:
        codes = [str(row["flag_code"]).strip() for row in load_registry()["countries"]]
    else:
        codes = choosable_flag_codes()

    copied: list[str] = []
    missing_vendor: list[str] = []
    SITE_FLAGS.mkdir(parents=True, exist_ok=True)

    for code in codes:
        code = code.strip().lower()
        if not code:
            continue
        src = VENDOR_4X3 / f"{code}.svg"
        dst = SITE_FLAGS / f"{code}.svg"
        if not src.is_file():
            missing_vendor.append(code)
            continue
        if dry_run:
            copied.append(code)
            continue
        shutil.copy2(src, dst)
        copied.append(code)

    if missing_vendor:
        raise SystemExit(
            "Missing vendored flag-icons source for flag_code(s): "
            + ", ".join(sorted(missing_vendor))
        )

    return copied


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Sync Amiga flag SVGs from flag-icons vendor")
    parser.add_argument("--dry-run", action="store_true", help="Validate only; do not copy")
    parser.add_argument(
        "--all-registry-rows",
        action="store_true",
        help="Include choosable:false rows (e.g. United Kingdom)",
    )
    args = parser.parse_args(argv)
    copied = sync_flags(dry_run=args.dry_run, include_non_choosable=args.all_registry_rows)
    verb = "Would copy" if args.dry_run else "Copied"
    print(f"{verb} {len(copied)} flag SVG(s) to {SITE_FLAGS}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())