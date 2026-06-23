"""CLI retired Jun 2026 — scripts.ladder is a library package only.

Online fill: php site/public_html/ops/run_ops_sim.php run
Amiga fill:   python -m scripts.amiga prove
Policy:       docs/obsolete-dev-scripts-retirement-policy.md
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

_RETIRED_MSG = """
[RETIRED] python -m scripts.ladder {command}

Full-memory ladder replay (reset / replay / run) was dev-era tooling.
It is not holy ops and must not fill work DB or staging sign-off databases.

  Online (ko2unity_work / kooldb1):
    php site/public_html/ops/run_prepare.php zero-derived --target local-work
    php site/public_html/ops/run_ops_sim.php run --target local-work
    php site/public_html/ops/run_verify_ops_sim.php --target local-work

  Amiga (ko2amiga_db):
    python -m scripts.amiga prove

  Policy: docs/obsolete-dev-scripts-retirement-policy.md
""".strip()


def main() -> None:
    parser = argparse.ArgumentParser(
        description="[RETIRED] Ladder replay CLI — see docs/obsolete-dev-scripts-retirement-policy.md"
    )
    parser.add_argument(
        "command",
        nargs="?",
        choices=("reset", "replay", "run"),
        default="run",
        help="All verbs retired (Jun 2026)",
    )
    parser.add_argument("--dry-run", action="store_true", help=argparse.SUPPRESS)
    parser.add_argument("--limit", type=int, default=None, metavar="N", help=argparse.SUPPRESS)
    parser.add_argument("--ini", type=Path, default=None, help=argparse.SUPPRESS)
    parser.add_argument(
        "--target",
        choices=("local", "sandbox", "staging"),
        default=None,
        help=argparse.SUPPRESS,
    )
    parser.add_argument("-v", "--verbose", action="store_true", help=argparse.SUPPRESS)
    args = parser.parse_args()
    print(_RETIRED_MSG.format(command=args.command), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
