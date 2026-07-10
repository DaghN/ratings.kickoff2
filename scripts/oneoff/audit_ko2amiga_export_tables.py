#!/usr/bin/env python3
"""Thin wrapper — staging export audit lives in scripts.amiga.staging_export_tables."""

from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.amiga.staging_export_tables import main_audit_staging_export  # noqa: E402


def main() -> int:
    argv = list(sys.argv[1:])
    if not argv:
        argv = ["--database", "ko2amiga_work"]
    return main_audit_staging_export(argv)


if __name__ == "__main__":
    sys.exit(main())
