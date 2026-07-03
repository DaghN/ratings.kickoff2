"""Throwaway audit: find theme.css class/id/custom-property tokens with zero
references under site/public_html/ (outside theme.css itself).

Usage: python scripts/audit_theme_css_dead_tokens.py
Output: report to stdout; candidates only, human must verify before deleting.
"""

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
THEME = ROOT / "site" / "public_html" / "stylesheets" / "theme.css"
SITE = ROOT / "site" / "public_html"

SEARCH_EXTS = {".php", ".js", ".css", ".html", ".htm", ".inc"}


def strip_comments(css: str) -> str:
    return re.sub(r"/\*.*?\*/", " ", css, flags=re.S)


def extract_tokens(css: str):
    no_comments = strip_comments(css)
    # Remove declaration blocks to keep only selector text (naive but fine:
    # nested @media handled by removing innermost braces repeatedly)
    sel_text = no_comments
    prev = None
    while prev != sel_text:
        prev = sel_text
        sel_text = re.sub(r"\{[^{}]*\}", " ; ", sel_text)

    classes = set(re.findall(r"\.(-?[_a-zA-Z][\w-]*)", sel_text))
    ids = set(re.findall(r"#(-?[_a-zA-Z][\w-]*)", sel_text))
    # custom property definitions anywhere in the file (inside blocks)
    props = set(re.findall(r"(--[\w-]+)\s*:", no_comments))
    return classes, ids, props


def site_files():
    for p in SITE.rglob("*"):
        if p.is_file() and p.suffix.lower() in SEARCH_EXTS:
            if p == THEME:
                continue
            yield p


def main():
    css = THEME.read_text(encoding="utf-8", errors="replace")
    classes, ids, props = extract_tokens(css)

    print(f"theme.css tokens: {len(classes)} classes, {len(ids)} ids, {len(props)} custom props")

    corpus = []  # (path, text)
    for p in site_files():
        try:
            corpus.append((p, p.read_text(encoding="utf-8", errors="replace")))
        except OSError:
            pass
    print(f"searched files: {len(corpus)}")

    def hits(needle: str):
        out = []
        for p, text in corpus:
            if needle in text:
                out.append(p)
        return out

    dead_classes = []
    for c in sorted(classes):
        if not hits(c):
            dead_classes.append(c)

    dead_ids = []
    for i in sorted(ids):
        if not hits(i):
            dead_ids.append(i)

    css_no_comments = strip_comments(css)
    dead_props = []
    for pr in sorted(props):
        # consumers: var(--x) or var(--x, fallback) anywhere incl. theme.css
        used_in_theme = re.search(r"var\(\s*" + re.escape(pr) + r"\b", css_no_comments)
        used_elsewhere = hits("var(" + pr) or hits(pr)  # any mention outside theme.css
        if not used_in_theme and not used_elsewhere:
            dead_props.append(pr)

    print(f"\n== ZERO-HIT CLASSES ({len(dead_classes)}) ==")
    for c in dead_classes:
        print("  ." + c)
    print(f"\n== ZERO-HIT IDS ({len(dead_ids)}) ==")
    for i in dead_ids:
        print("  #" + i)
    print(f"\n== UNCONSUMED CUSTOM PROPS ({len(dead_props)}) ==")
    for pr in dead_props:
        print("  " + pr)


if __name__ == "__main__":
    sys.exit(main())
