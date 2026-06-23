"""CLI retired Jun 2026 — work DB prepare is PHP only.

Canonical: php site/public_html/ops/run_prepare.php
Policy: docs/obsolete-dev-scripts-retirement-policy.md
"""

from __future__ import annotations

import argparse
import sys

_RETIRED_MSG = """
[RETIRED] python -m scripts.work_prepare {verb}

Work DB prepare moved to PHP ops (no Python, no scripts.ladder run).

  php site/public_html/ops/run_prepare.php prepare --target local-work
  php site/public_html/ops/run_prepare.php refresh-work --target local-work
  php site/public_html/ops/run_prepare.php migrate-work --target local-work
  php site/public_html/ops/run_prepare.php seed-catalog --target local-work
  php site/public_html/ops/run_prepare.php zero-derived --target local-work
  php site/public_html/ops/run_prepare.php parity --target local-work

  powershell -File scripts/prepare_local_work_db.ps1
  powershell -File scripts/refresh_local_work_db.ps1

Archived Python modules: docs/archive/work-prepare-retired-2026-06/
Policy: docs/obsolete-dev-scripts-retirement-policy.md
""".strip()

_VERBS = (
    "prepare",
    "refresh-work",
    "migrate-work",
    "seed-catalog",
    "zero-derived",
    "seed-lobby",
    "parity",
    "ab-post-game",
)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="[RETIRED] Work prepare CLI — use php site/public_html/ops/run_prepare.php"
    )
    parser.add_argument("verb", nargs="?", choices=_VERBS, default="prepare")
    parser.add_argument("--target", default="local-work", help=argparse.SUPPRESS)
    parser.add_argument("--dry-run", action="store_true", help=argparse.SUPPRESS)
    parser.add_argument("--zero-only", action="store_true", help=argparse.SUPPRESS)
    parser.add_argument("--full", action="store_true", help=argparse.SUPPRESS)
    parser.add_argument("-v", "--verbose", action="store_true", help=argparse.SUPPRESS)
    args, _unknown = parser.parse_known_args()
    print(_RETIRED_MSG.format(verb=args.verb), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
