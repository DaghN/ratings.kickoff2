#!/usr/bin/env python3
"""
Scan site/public_html for k2-table sortable compliance tiers.

Run from repo root:
  python scripts/audit_k2_table_compliance.py

Tier A — wide / filter-reload tables (Jun 2026 target):
  ranked helper class, SSR th helpers, scroll-mirror wrap, page cloak + assets head.

Tier B — hub leaderboard wings (acceptable legacy within hub):
  ranked leaderboard helper + page cloak + LB/assets head + mirror wrap; SSR th optional.

Tier C — straggler (needs migration or documented exception):
  sortable markup missing tier A/B head/stack signals.

Tooltip — native title on table headers (Jun 2026):
  No `<th ... title=` for column help; use data-k2-help per docs/k2-tooltip-policy.md.

Tooltip policy (T1) — broader pass:
  No native `title=` on site-owned help surfaces (th/td/dt/a/button/span);
  iframe `title` on video embeds allowed (cross-origin player — see k2-tooltip-policy.md);
  Chart.js should use chart-theme mergeTooltip.

Exit code 1 when any Tier C files remain (excluding documented exceptions),
any th-title violations remain, or any tooltip-policy violations remain.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
PHP_ROOT = REPO / "site" / "public_html"

SORTABLE = 'data-k2-table="sortable"'

RANKED_HELPER = ("k2_table_ranked_sortable_class", "k2_table_ranked_leaderboard_class")

PAGE_CLOAK = (
    "k2RankedCloak",
    "k2_sortable_table_assets_head.inc.php",
    "k2_lb_sortable_table_head.inc.php",
)

# Includes/pages with sortable markup in a parent shell (cloak on shell, not child)
SHELL_OWNED_HEAD = {
    "games/recent.php": "games_hub_shell_start.inc.php",
    "games/highlights.php": "games_hub_shell_start.inc.php",
    "games/all.php": "games_hub_shell_start.inc.php",
}

# rel path -> reason (still sortable but intentionally not Tier A/B)
DOCUMENTED_EXCEPTIONS: dict[str, str] = {
    "includes/games_highlights_helpers.php": "Compact highlights boards — no full ranked stack",
    "includes/status_room_section.php": "Status active board — cloak on status.php parent",
    "includes/player_milestones_helpers.php": "Milestone digest tables — parent page owns head",
    "includes/k2_league_table_render.php": "Status league — compact calm-stats; not hub LB",
}

# Includes known to need Tier A migration (backlog)
KNOWN_BACKLOG: dict[str, str] = {
    "amiga/tournament.php": "page shell only — tables in amiga_tournament_lib / amiga_profile_blocks",
}

# rel path -> reason (allowed <th title=...> — rare)
TH_TITLE_EXCEPTIONS: dict[str, str] = {}

TH_TITLE_LINE = re.compile(r"<th\b[^>]*\btitle\s*=", re.IGNORECASE)
TH_TITLE_PHP_LINE = re.compile(
    r"k2_table_sortable_th_attr\s*\([^)]*\)[^>]*\btitle\s*="
    r"|title\s*=\s*\"[^\"]*\"[^>]*>\s*[^<]+\s*</th>",
    re.IGNORECASE,
)

JS_ROOT = PHP_ROOT / "js"

# Native title= on HTML attrs (exclude data-k2-tooltip-hide-title via negative lookbehind).
TITLE_ATTR = re.compile(r"(?<![\w-])title\s*=", re.IGNORECASE)

PHP_TITLE_LINE_SKIP = (
    re.compile(r"data-k2-tooltip-hide-title", re.I),
    re.compile(r"['\"]title['\"]\s*=>"),
    re.compile(r"\$[\w]*title\s*=", re.I),
    re.compile(r"\$row\['title'\]"),
    re.compile(r"\$video\['title'\]"),
    re.compile(r"\$alt\['title'\]"),
    re.compile(r"<iframe\b", re.I),
    re.compile(r"<title\b", re.I),
    re.compile(r"k2-tournament-videos__title", re.I),
)

PHP_TITLE_FILE_EXCEPTIONS: dict[str, str] = {
    "includes/join_page_section.php": "video embed iframe title",
    "game.php": "video embed iframe title",
    "includes/amiga_tournament_videos_body.inc.php": "video embed iframe title",
    "includes/amiga_tournament_videos_wc_render.inc.php": "video embed iframe title",
}

JS_TITLE_SET = re.compile(
    r"\.setAttribute\s*\(\s*['\"]title['\"]|(?<![\w])\.title\s*=",
    re.I,
)

JS_TITLE_LINE_SKIP = (
    re.compile(r"//"),
    re.compile(r"k2-table-tooltip__title", re.I),
    re.compile(r"__title", re.I),
    re.compile(r"axis-title", re.I),
    re.compile(r"y-title", re.I),
    re.compile(r"chart-tooltip-rating", re.I),
)

JS_TITLE_FILE_EXCEPTIONS: dict[str, str] = {
    "js/amiga-tournament-videos.js": "video embed iframe.title (mountEmbed)",
}

CHART_TOOLTIP_RAW = re.compile(r"\btooltip\s*:\s*\{")
CHART_MERGE = "mergeTooltip"

CHART_FILE_EXCEPTIONS: dict[str, str] = {
    "js/chart-theme.js": "defines mergeTooltip",
    "js/player-rank-chart-core.js": "custom k2-chart-html-tooltip widget",
}


def rel(path: Path) -> str:
    return path.relative_to(PHP_ROOT).as_posix()


def is_page_file(path: Path) -> bool:
    r = rel(path)
    if r.startswith("includes/") or r.startswith("amiga/ops/"):
        return False
    return True


def classify(path: Path, text: str) -> tuple[str, list[str]]:
    if SORTABLE not in text:
        return "—", []

    r = rel(path)
    notes: list[str] = []

    has_ranked_helper = any(m in text for m in RANKED_HELPER)
    has_ranked_literal = "ranked-pages-table" in text
    has_ssr_th = "k2_table_sortable_th_attr" in text or "k2_lb_th(" in text
    has_mirror_wrap = "k2_table_wrap_open(true)" in text or "data-k2-scroll-mirror" in text
    has_cloak_or_assets = any(m in text for m in PAGE_CLOAK)
    bare_enqueue = (
        "k2_table_js_enqueue()" in text
        and "k2_sortable_table_assets_head" not in text
        and "k2_lb_sortable_table_head" not in text
    )

    if r in SHELL_OWNED_HEAD:
        shell = PHP_ROOT / "includes" / SHELL_OWNED_HEAD[r]
        if shell.is_file():
            shell_text = shell.read_text(encoding="utf-8", errors="replace")
            if any(m in shell_text for m in PAGE_CLOAK):
                if has_ranked_helper and has_mirror_wrap:
                    return "B", [f"head on {SHELL_OWNED_HEAD[r]}"]
        return "C", [f"shell {SHELL_OWNED_HEAD[r]} missing cloak/assets"]

    if r in DOCUMENTED_EXCEPTIONS:
        return "exception", [DOCUMENTED_EXCEPTIONS[r]]

    page = is_page_file(path)

    # Tier A signals
    tier_a = (
        (has_ranked_helper or has_ranked_literal)
        and has_ssr_th
        and has_mirror_wrap
        and (has_cloak_or_assets or not page)
    )

    # Tier B — hub LB pages / WC shell tables
    tier_b = (
        has_ranked_helper
        and has_mirror_wrap
        and (has_cloak_or_assets or not page)
    )

    if tier_a:
        return "A", notes

    if tier_b and page and "leaderboards/" in r:
        return "A", ["hub LB — full SSR"]
    if tier_b and "amiga_wc_players_table" in r:
        return "A", ["WC players LB — full SSR"]
    if tier_b and page:
        return "B", notes

    if not page and has_ranked_helper and has_mirror_wrap and not has_ssr_th:
        return "B", ["include: parent owns head; SSR th optional"]

    if not page:
        if has_ranked_helper and has_mirror_wrap and has_ssr_th:
            return "A", notes
        if SORTABLE in text:
            return "C", ["include: legacy sortable markup"]
        return "C", notes

    # page-level stragglers
    if bare_enqueue:
        notes.append("bare k2_table_js_enqueue()")
    if not has_cloak_or_assets:
        notes.append("missing k2RankedCloak / sortable assets head")
    if not has_ranked_helper and not has_ranked_literal:
        notes.append("missing k2_table_ranked_*_class()")
    if not has_mirror_wrap:
        notes.append("missing scroll-mirror wrap")
    if not has_ssr_th and has_ranked_helper:
        notes.append("missing SSR th helpers")

    return "C", notes


def find_th_title_violations(path: Path, text: str) -> list[str]:
    r = rel(path)
    if r in TH_TITLE_EXCEPTIONS:
        return []

    violations: list[str] = []
    for line_no, line in enumerate(text.splitlines(), start=1):
        stripped = line.strip()
        if stripped.startswith("//") or stripped.startswith("#"):
            continue
        if stripped.startswith("*") and "title" not in stripped.lower():
            continue
        if TH_TITLE_LINE.search(line) or TH_TITLE_PHP_LINE.search(line):
            violations.append(f"line {line_no}: native title on <th> — use data-k2-help")
    return violations


def audit_th_titles() -> list[tuple[str, list[str]]]:
    rows: list[tuple[str, list[str]]] = []
    for path in sorted(PHP_ROOT.rglob("*.php")):
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"skip {path}: {exc}", file=sys.stderr)
            continue
        hits = find_th_title_violations(path, text)
        if hits:
            rows.append((rel(path), hits))
    return rows


def _line_skipped(line: str, patterns: tuple[re.Pattern[str], ...]) -> bool:
    return any(p.search(line) for p in patterns)


def find_php_title_attr_violations(path: Path, text: str) -> list[str]:
    r = rel(path)
    if r in PHP_TITLE_FILE_EXCEPTIONS:
        return []

    violations: list[str] = []
    for line_no, line in enumerate(text.splitlines(), start=1):
        stripped = line.strip()
        if stripped.startswith("//") or stripped.startswith("#"):
            continue
        if stripped.startswith("*") and "title" not in stripped.lower():
            continue
        if not TITLE_ATTR.search(line):
            continue
        if _line_skipped(line, PHP_TITLE_LINE_SKIP):
            continue
        if TH_TITLE_LINE.search(line):
            continue
        violations.append(
            f"line {line_no}: native title= — use data-k2-help (docs/k2-tooltip-policy.md T1)"
        )
    return violations


def audit_php_title_attrs() -> list[tuple[str, list[str]]]:
    rows: list[tuple[str, list[str]]] = []
    for path in sorted(PHP_ROOT.rglob("*.php")):
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"skip {path}: {exc}", file=sys.stderr)
            continue
        hits = find_php_title_attr_violations(path, text)
        if hits:
            rows.append((rel(path), hits))
    return rows


def find_js_title_violations(path: Path, text: str) -> list[str]:
    r = rel(path)
    if r in JS_TITLE_FILE_EXCEPTIONS:
        return []

    violations: list[str] = []
    for line_no, line in enumerate(text.splitlines(), start=1):
        if not JS_TITLE_SET.search(line):
            continue
        if _line_skipped(line, JS_TITLE_LINE_SKIP):
            continue
        violations.append(
            f"line {line_no}: native .title / setAttribute('title') — use K2 tooltip pattern"
        )
    return violations


def audit_js_titles() -> list[tuple[str, list[str]]]:
    rows: list[tuple[str, list[str]]] = []
    if not JS_ROOT.is_dir():
        return rows
    for path in sorted(JS_ROOT.rglob("*.js")):
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"skip {path}: {exc}", file=sys.stderr)
            continue
        hits = find_js_title_violations(path, text)
        if hits:
            rows.append((rel(path), hits))
    return rows


def audit_chart_tooltips() -> list[tuple[str, str]]:
    rows: list[tuple[str, str]] = []
    if not JS_ROOT.is_dir():
        return rows
    for path in sorted(JS_ROOT.rglob("*.js")):
        r = rel(path)
        if r in CHART_FILE_EXCEPTIONS:
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"skip {path}: {exc}", file=sys.stderr)
            continue
        if "new Chart(" not in text:
            continue
        if CHART_MERGE in text:
            continue
        if CHART_TOOLTIP_RAW.search(text):
            rows.append((r, "Chart.js tooltip: { without mergeTooltip — use chart-theme.js"))
    return rows


def main() -> int:
    rows: list[tuple[str, str, str, str]] = []

    for path in sorted(PHP_ROOT.rglob("*.php")):
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"skip {path}: {exc}", file=sys.stderr)
            continue
        if SORTABLE not in text:
            continue
        tier, notes = classify(path, text)
        r = rel(path)
        backlog = KNOWN_BACKLOG.get(r, "")
        note = "; ".join(n for n in [backlog, *notes] if n)
        rows.append((tier, r, note, backlog))

    tiers = {"A": [], "B": [], "C": [], "exception": []}
    for tier, r, note, _ in rows:
        tiers.setdefault(tier, []).append((r, note))

    print("K2 table sortable compliance audit")
    print(f"Root: {PHP_ROOT}")
    print()

    for label, key in (
        ("Tier A (full wide-table stack)", "A"),
        ("Tier B (hub LB / acceptable legacy)", "B"),
        ("Documented exceptions", "exception"),
        ("Tier C (needs work)", "C"),
    ):
        items = tiers.get(key, [])
        print(f"## {label} — {len(items)}")
        for r, note in items:
            suffix = f" — {note}" if note else ""
            print(f"  {r}{suffix}")
        print()

    tier_c = tiers.get("C", [])
    th_title_rows = audit_th_titles()
    php_title_rows = audit_php_title_attrs()
    js_title_rows = audit_js_titles()
    chart_tooltip_rows = audit_chart_tooltips()

    if th_title_rows:
        print("## Tooltip — <th title=…> violations — {0}".format(len(th_title_rows)))
        for r, hits in th_title_rows:
            for hit in hits:
                print(f"  {r}: {hit}")
        print("  Fix: docs/k2-tooltip-policy.md (data-k2-help, not title on <th>)")
        print()

    if php_title_rows:
        print("## Tooltip — PHP title=… violations — {0}".format(len(php_title_rows)))
        for r, hits in php_title_rows:
            for hit in hits:
                print(f"  {r}: {hit}")
        print("  Fix: docs/k2-tooltip-policy.md T1 (data-k2-help); embed iframe title allowed per § Cross-origin embeds")
        print()

    if js_title_rows:
        print("## Tooltip — JS native title violations — {0}".format(len(js_title_rows)))
        for r, hits in js_title_rows:
            for hit in hits:
                print(f"  {r}: {hit}")
        print()

    if chart_tooltip_rows:
        print("## Tooltip — Chart.js without mergeTooltip — {0}".format(len(chart_tooltip_rows)))
        for r, note in chart_tooltip_rows:
            print(f"  {r}: {note}")
        print()

    if tier_c:
        print(f"FAIL: {len(tier_c)} Tier C file(s). See docs/k2-table-and-games-plan.md § Compliance backlog.")
        return 1

    if th_title_rows:
        print(f"FAIL: {len(th_title_rows)} file(s) with native title on <th>. See docs/k2-tooltip-policy.md.")
        return 1

    if php_title_rows:
        print(f"FAIL: {len(php_title_rows)} file(s) with native title= help. See docs/k2-tooltip-policy.md.")
        return 1

    if js_title_rows:
        print(f"FAIL: {len(js_title_rows)} JS file(s) with native title. See docs/k2-tooltip-policy.md.")
        return 1

    if chart_tooltip_rows:
        print(f"FAIL: {len(chart_tooltip_rows)} Chart.js file(s) missing mergeTooltip.")
        return 1

    print("PASS: no Tier C sortable files; no tooltip-policy violations.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
