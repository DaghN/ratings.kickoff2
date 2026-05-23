#!/usr/bin/env python3
"""One-shot Phase A nav wiring for ranked, server, and individual pages."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site" / "public_html"

HUB_LEADERBOARDS = """<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>"""

HUB_SERVER = {
    "server1.php": ("trends", """<?php
$k2HubTabActive = 'trends';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>"""),
    "server2.php": ("records", """<?php
$k2HubTabActive = 'records';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>"""),
    "server3.php": ("games", """<?php
$k2HubTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>"""),
}

RANKED_WING = {
    "ranked1.php": "rating",
    "ranked2.php": "goals",
    "ranked3.php": "dds",
    "ranked4.php": "streaks",
    "ranked5.php": "victims",
    "ranked7.php": "results",
}

ABOUTMENU_BLOCK = re.compile(
    r"<ul id=\"aboutmenu\">.*?</ul>\s*(?:<br\s*/>\s*)+",
    re.DOTALL,
)

LB_BEFORE = """<?php
$k2LbWingActive = '{wing}';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>"""


def strip_legacy_nav(content: str) -> str:
    while True:
        new_content = ABOUTMENU_BLOCK.sub("", content, count=1)
        if new_content == content:
            break
        content = new_content
    return content


def wire_ranked(path: Path, wing: str) -> None:
    text = path.read_text(encoding="utf-8")
    text = text.replace(
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>',
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>\n\n' + HUB_LEADERBOARDS,
        1,
    )
    text = strip_legacy_nav(text)
    text = re.sub(
        r"<\?php\s*\n\$k2HubTabActive = 'leaderboards';\s*\ninclude.*?stage1_hub_nav_preview\.php;\s*\n\?>\s*",
        HUB_LEADERBOARDS + "\n",
        text,
        flags=re.DOTALL,
    )
    text = re.sub(
        r"<\?php\s*\n\$k2LbWingActive = '[^']+';\s*\ninclude.*?stage1_lb_wing_preview\.php;\s*\n\?>\s*",
        LB_BEFORE.format(wing=wing) + "\n",
        text,
        flags=re.DOTALL,
    )
    if "lb_nav.php" not in text:
        text = text.replace(
            '<div class="k2-table-wrap">',
            LB_BEFORE.format(wing=wing) + "\n\n<div class=\"k2-table-wrap\">",
            1,
        )
    if "lb_nav_end.php" not in text:
        text = text.replace(
            "</div><!-- .k2-table-wrap -->",
            "</div><!-- .k2-table-wrap -->\n\n<?php include $_SERVER[\"DOCUMENT_ROOT\"] . \"/includes/lb_nav_end.php\"; ?>",
            1,
        )
    text = text.replace(
        "</div><!-- .k2-chrome-tabs -->\n\n</div><!-- .k2-page-nav -->",
        "<?php include $_SERVER[\"DOCUMENT_ROOT\"] . \"/includes/lb_nav_end.php\"; ?>\n\n</div><!-- .k2-page-nav -->",
    )
    text = re.sub(
        r'<p style="margin:12px 0;[^>]*>UPLOAD PROBE.*?</p>\s*',
        "",
        text,
        flags=re.DOTALL,
    )
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"wired ranked: {path.name}")


def wire_server(path: Path, hub_snippet: str) -> None:
    text = path.read_text(encoding="utf-8")
    text = text.replace(
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>',
        '<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>\n\n' + hub_snippet,
        1,
    )
    text = strip_legacy_nav(text)
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"wired server: {path.name}")


PLAYER_PAGES = {
    "individual1.php": "profile",
    "individual2a.php": "wins",
    "individual2b.php": "goals",
    "individual2c.php": "dds",
    "individual3.php": "games",
}

HERO_NAV = """
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = '{tab}';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>
"""


def wire_individual(path: Path, tab: str) -> None:
    text = path.read_text(encoding="utf-8")
    text = strip_legacy_nav(text)
    snippet = HERO_NAV.format(tab=tab)
    if "player_hero.php" not in text:
        # After first playertable row fetch block ending before main content tables
        markers = [
            'if ($row == null) exit();',
            "$row = mysqli_fetch_row($result);",
            "$Name = $row['Name'];",
        ]
        inserted = False
        for marker in markers:
            if marker in text and snippet.strip() not in text:
                if marker == 'if ($row == null) exit();':
                    text = text.replace(
                        marker,
                        marker + snippet,
                        1,
                    )
                    inserted = True
                    break
                if marker == "$Name = $row['Name';":
                    pass
        if not inserted and "$Name = $row['Name'];" in text:
            text = text.replace(
                "$Name = $row['Name'];",
                "$Name = $row['Name'];" + snippet,
                1,
            )
            inserted = True
        if not inserted:
            print(f"WARN: could not place hero on {path.name}")
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"wired individual: {path.name}")


def main() -> None:
    for name, wing in RANKED_WING.items():
        wire_ranked(ROOT / name, wing)
    for name, (_, hub) in HUB_SERVER.items():
        wire_server(ROOT / name, hub)
    for name, tab in PLAYER_PAGES.items():
        p = ROOT / name
        if p.exists():
            wire_individual(p, tab)


if __name__ == "__main__":
    main()
