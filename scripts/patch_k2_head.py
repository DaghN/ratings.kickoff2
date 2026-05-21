#!/usr/bin/env python3
"""One-shot: replace duplicated CSS head blocks with includes/k2_head.php."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site" / "public_html"
INCLUDE = '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>\n'
RANKED = (
    '<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>\n'
)

BLOCKS = [
    (
        '<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />\n'
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/favicon_head.php"; ?>\n'
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/ranked_table_cloak_head.php"; ?>\n',
        RANKED,
    ),
    (
        '<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />\n'
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/favicon_head.php"; ?>\n'
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/theme_boot_head.php"; ?>\n',
        INCLUDE,
    ),
    (
        '<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />\n'
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/favicon_head.php"; ?>\n',
        INCLUDE,
    ),
    (
        '<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n'
        '<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />\n',
        INCLUDE,
    ),
]


def main() -> None:
    for path in sorted(ROOT.glob("*.php")):
        text = path.read_text(encoding="utf-8")
        if "k2_head.php" in text:
            continue
        orig = text
        for old, new in BLOCKS:
            if old in text:
                text = text.replace(old, new, 1)
                break
        if text != orig:
            path.write_text(text, encoding="utf-8")
            print("patched", path.name)
        elif "main2.css" in text:
            print("SKIP", path.name)


if __name__ == "__main__":
    main()
