#!/usr/bin/env python3
"""One-shot: wire k2 theme onto remaining public PHP pages."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site" / "public_html"

THEME_LINK = '<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />\n'
HEADER = '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>\n\n'
PAGE_NAV_CLOSE = '\n</div><!-- .k2-page-nav -->\n'

# (filename, ranked_cloak, thr_col_fix, wrap_ranked_table)
PAGES = [
    ("ranked2.php", True, False, True),
    ("ranked3.php", True, False, True),
    ("ranked4.php", True, False, True),
    ("ranked5.php", True, False, True),
    ("ranked6.php", True, False, True),
    ("server2.php", False, False, False),
    ("server3.php", False, False, False),
    ("individual2.php", False, True, False),
    ("individual2a.php", False, True, False),
    ("individual2b.php", False, True, False),
    ("individual2c.php", False, True, False),
    ("individual3.php", False, False, False),
    ("game.php", False, False, False),
    ("individualA.php", False, False, False),
    ("individualB.php", False, False, False),
    ("individualC.php", False, False, False),
]


def patch(path: Path, ranked_cloak: bool, thr_col: bool, wrap_ranked: bool) -> str:
    text = path.read_text(encoding="utf-8")
    if "theme.css" in text and "k2-site" in text:
        return text

    text = re.sub(
        r'<html xmlns="http://www.w3.org/1999/xhtml">',
        '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">',
        text,
        count=1,
    )
    text = re.sub(r"<title>KOOL Rating</title>", "<title>Kick Off 2 ratings</title>", text)
    text = re.sub(r"<title>KO2PCrating</title>", "<title>Kick Off 2 ratings</title>", text)

    if "theme.css" not in text:
        text = text.replace(
            '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n',
            '<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />\n' + THEME_LINK,
            1,
        )

    if thr_col and "thrColFixHdr.css" not in text:
        text = text.replace(
            THEME_LINK,
            THEME_LINK
            + '<link href="stylesheets/thrColFixHdr.css" rel="stylesheet" type="text/css" />\n',
            1,
        )

    text = re.sub(r"<body>\s*\n\s*<br\s*/>\s*\n", "<body class=\"k2-site\">\n\n", text, count=1)
    text = re.sub(
        r"<body>\s*\n\s*\n\s*<br\s*/>\s*\n",
        "<body class=\"k2-site\">\n\n",
        text,
        count=1,
    )
    text = re.sub(
        r"(<body class=\"k2-site\">\s*\n)(<\?php \$id=\$_GET\['id'\]; \?>\s*\n\s*<br\s*/>\s*\n)",
        r"\1<?php $id=$_GET['id']; ?>\n\n",
        text,
        count=1,
    )
    text = re.sub(r"<body>\s*\n\s*\n", "<body class=\"k2-site\">\n\n", text, count=1)
    text = re.sub(r"<body>\s*\n", "<body class=\"k2-site\">\n\n", text, count=1)

    if "site_header.php" not in text:
        text = re.sub(
            r"(<body class=\"k2-site\">\s*\n)",
            r"\1" + HEADER,
            text,
            count=1,
        )

    if wrap_ranked and 'class="k2-table-wrap"' not in text:
        text = text.replace(
            '<table class="example ranked-pages-table',
            '<div class="k2-table-wrap">\n\n<table class="example ranked-pages-table',
            1,
        )
        text = re.sub(
            r"(</table>\s*\n)(<br\s*/>)",
            r"</table>\n\n</div><!-- .k2-table-wrap -->\n\n\2",
            text,
            count=1,
        )

    if ".k2-page-nav" not in text:
        text = re.sub(r"</body>", PAGE_NAV_CLOSE + "</body>", text, count=1)

    return text


def main() -> None:
    for name, cloak, thr, wrap in PAGES:
        path = ROOT / name
        if not path.exists():
            print(f"skip missing {name}")
            continue
        updated = patch(path, cloak, thr, wrap)
        path.write_text(updated, encoding="utf-8", newline="\n")
        print(f"updated {name}")


if __name__ == "__main__":
    main()
