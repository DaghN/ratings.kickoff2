#!/usr/bin/env python3
"""MG11 compartment audit — modern/ must not import legacy prove pipeline (CODE-1)."""

from __future__ import annotations

import re
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[1]
_MODERN = _REPO / "scripts" / "amiga" / "modern"

_FORBIDDEN_PREFIXES = (
    "scripts.amiga.prove",
    "scripts.amiga.import_access",
    "scripts.amiga.import_witness",
    "scripts.amiga.import_pristine",
    "scripts.amiga.import_prune",
    "scripts.amiga.replay",
)

_IMPORT_RE = re.compile(r"^\s*(?:from|import)\s+([\w.]+)")


def _iter_imports(path: Path) -> list[tuple[int, str]]:
    out: list[tuple[int, str]] = []
    for lineno, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
        m = _IMPORT_RE.match(line)
        if m:
            out.append((lineno, m.group(1)))
    return out


def _is_forbidden(module: str) -> bool:
    if module.startswith("scripts.amiga.modern"):
        return False
    return any(
        module == prefix or module.startswith(prefix + ".")
        for prefix in _FORBIDDEN_PREFIXES
    )


def main() -> int:
    violations: list[str] = []
    for path in sorted(_MODERN.rglob("*.py")):
        if path.name.startswith("_"):
            continue
        for lineno, module in _iter_imports(path):
            if _is_forbidden(module):
                rel = path.relative_to(_REPO)
                violations.append(f"{rel}:{lineno}: {module}")

    if violations:
        print("MG11 compartment audit FAILED:")
        for line in violations:
            print(f"  {line}")
        print("Copy legacy code into modern/ — do not import prove pipeline.")
        return 1

    print(f"MG11 compartment audit OK ({len(list(_MODERN.rglob('*.py')))} files)")
    return 0


if __name__ == "__main__":
    sys.exit(main())