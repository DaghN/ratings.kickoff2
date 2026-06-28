#!/usr/bin/env python3
"""Add missing $lbSort = k2_lb_table_sort_state() after hub LB SSR migration."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site" / "public_html"

AMIGA_DEFAULTS: dict[str, int] = {
    "amiga/leaderboards/rating.php": 2,
    "amiga/leaderboards/goals.php": 4,
    "amiga/leaderboards/double-digits.php": 4,
    "amiga/leaderboards/victims.php": 4,
    "amiga/leaderboards/peak-rating.php": 4,
    "amiga/leaderboards/calendar-geo.php": 4,
    "amiga/leaderboards/tournament-honours.php": 4,
    "amiga/leaderboards/performance-rating/best.php": 3,
    "amiga/leaderboards/performance-rating/top.php": 3,
    "amiga/leaderboards/performance-rating/perfect.php": 9,
}

BLOCK_RE = re.compile(
    r"<\?php\s*\n\$k2LbAnchorCol = 2;\s*\n\$k2LbDefaultSortCol = \d+;\s*\n\?>",
    re.MULTILINE,
)

BODY_TD_RE = re.compile(
    r"k2_table_body_td_attr\((\d+), \$k2LbAnchorCol, \$k2LbDefaultSortCol(?:, '([^']*)')?\)"
)

INSERT_RE = re.compile(
    r"(<\?php k2_table_wrap_open\(true\); \?>)\s*\n(<table)",
    re.MULTILINE,
)


def fix_amiga(path: Path, default: int) -> None:
    text = path.read_text(encoding="utf-8")
    new_block = f"<?php $lbSort = k2_lb_table_sort_state({default}); ?>"
    new_text, n = BLOCK_RE.subn(new_block, text, count=1)
    if n != 1:
        raise SystemExit(f"block replace failed ({n}): {path}")

    def body_repl(m: re.Match[str]) -> str:
        col = m.group(1)
        extra = m.group(2)
        if extra:
            return f"k2_lb_td({col}, $lbSort, '{extra}')"
        return f"k2_lb_td({col}, $lbSort)"

    new_text = BODY_TD_RE.sub(body_repl, new_text)
    path.write_text(new_text, encoding="utf-8")
    print(f"OK {path.relative_to(ROOT)}")


def fix_activity(path: Path) -> None:
    text = path.read_text(encoding="utf-8")
    if "k2_lb_table_sort_state" in text:
        print(f"SKIP {path.relative_to(ROOT)}")
        return
    new_text, n = INSERT_RE.subn(
        r"\1\n<?php $lbSort = k2_lb_table_sort_state(4); ?>\n\2",
        text,
        count=1,
    )
    if n != 1:
        raise SystemExit(f"insert failed ({n}): {path}")
    path.write_text(new_text, encoding="utf-8")
    print(f"OK {path.relative_to(ROOT)}")


def main() -> None:
    for rel, default in AMIGA_DEFAULTS.items():
        fix_amiga(ROOT / rel.replace("/", "\\"), default)

    for rel in (
        "leaderboards/activity/peaks.php",
        "leaderboards/activity/participation.php",
        "leaderboards/activity/in-a-row.php",
    ):
        fix_activity(ROOT / rel.replace("/", "\\"))


if __name__ == "__main__":
    main()
