#!/usr/bin/env python3
"""Remove legacy #container / 3-column layout from individual1.php (layout only)."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / "site" / "public_html" / "individual1.php"
text = path.read_text(encoding="utf-8")

text = text.replace(
    '<link href="stylesheets/thrColFixHdr.css" rel="stylesheet" type="text/css" />\n',
    "",
)
text = text.replace("<div id=\"container\">\n\n", "")
text = text.replace("\n<!-- end #container --></div>\n", "\n")
text = text.replace("\n  <div id=\"sidebar1\">\n  \n", "\n")
text = text.replace("\n  <!-- end #sidebar1 --></div>\n", "\n")
text = text.replace("\n  <div id=\"sidebar2\">\n\n\n\n", "\n")
text = text.replace("\n  <!-- end #sidebar2 --></div>\n", "\n")
text = text.replace("\n  <div id=\"mainContent\">\n  \n\n", "\n")
text = text.replace("\n  <!-- end #mainContent --></div>\n", "\n")
text = text.replace(
    "\t<!-- This clearing element should immediately follow the #mainContent div "
    "in order to force the #container div to contain all child floats -->"
    "<br class=\"clearfloat\" />\n\n",
    "\n",
)
text = text.replace(' style="max-width: 780px; margin-bottom: 16px;"', "")

path.write_text(text, encoding="utf-8", newline="\n")
print(f"Simplified layout in {path.name}")
