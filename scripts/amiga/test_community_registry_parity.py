"""Python vs PHP community headline column registry parity."""

from __future__ import annotations

import json
import re
import subprocess
import unittest
from pathlib import Path

from scripts.amiga.community_stats_columns import COMMUNITY_HEADLINE_COLUMNS
from scripts.amiga.verify_php_community_parity import _find_php

_REPO = Path(__file__).resolve().parents[2]
_PHP_REGISTRY = (
    _REPO / "site/public_html/amiga/ops/includes/amiga_community_stat_registry.php"
)


def _load_php_headline_columns_from_file() -> tuple[str, ...]:
    text = _PHP_REGISTRY.read_text(encoding="utf-8")
    match = re.search(
        r"function amiga_community_headline_column_names\(\): array\s*\{.*?return\s*\[(.*?)\];",
        text,
        re.DOTALL,
    )
    if not match:
        raise RuntimeError("could not parse PHP headline column registry")
    body = match.group(1)
    cols = re.findall(r"'([^']+)'", body)
    if not cols:
        raise RuntimeError("PHP headline column registry is empty")
    return tuple(cols)


def _load_php_headline_columns_via_cli(php: Path) -> tuple[str, ...]:
    code = (
        f"require {_PHP_REGISTRY.as_posix()!r}; "
        "echo json_encode(amiga_community_headline_column_names());"
    )
    proc = subprocess.run(
        [str(php), "-r", code],
        capture_output=True,
        text=True,
        check=False,
        cwd=str(_REPO),
    )
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr.strip() or proc.stdout)
    cols = json.loads(proc.stdout)
    if not isinstance(cols, list) or not cols:
        raise RuntimeError("PHP headline column registry is empty")
    return tuple(str(c) for c in cols)


def php_headline_column_names() -> tuple[str, ...]:
    php = _find_php()
    if php is not None:
        return _load_php_headline_columns_via_cli(php)
    return _load_php_headline_columns_from_file()


class CommunityRegistryParityTests(unittest.TestCase):
    def test_headline_columns_match_php_registry(self) -> None:
        php_cols = php_headline_column_names()
        self.assertEqual(
            COMMUNITY_HEADLINE_COLUMNS,
            php_cols,
            "COMMUNITY_HEADLINE_COLUMNS must match amiga_community_headline_column_names()",
        )


if __name__ == "__main__":
    unittest.main()
