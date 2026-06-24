#!/usr/bin/env python3
"""Fix syntax errors from migrate_lb_ssr.py."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site" / "public_html"

for p in list(ROOT.glob("leaderboards/**/*.php")) + list(ROOT.glob("amiga/leaderboards/**/*.php")):
    text = p.read_text(encoding="utf-8")
    if "k2_lb_th(" not in text:
        continue
    orig = text
    text = text.replace("$lbSort[\\'anchor\\']", "$lbSort['anchor']")
    text = text.replace("$lbSort[\\'sort_col\\']", "$lbSort['sort_col']")
    text = text.replace("$lbSort[\\'sort_dir\\']", "$lbSort['sort_dir']")
    text = re.sub(r'(data-k2-sort="[^"]*")>>', r"\1>", text)
    text = text.replace('?>>">', '?>">')
    text = text.replace('?>>"', '?>"')
    if text != orig:
        p.write_text(text, encoding="utf-8", newline="\n")
        print("fixed", p.relative_to(ROOT))
