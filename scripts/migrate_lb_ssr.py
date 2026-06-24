#!/usr/bin/env python3
"""Add SSR k2_lb_th / k2_lb_td to hub leaderboard PHP files. Run from repo root."""
from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
PHP_ROOT = REPO / "site" / "public_html"

# path relative to public_html -> default sort column (0-based)
FILES: dict[str, int] = {
    "leaderboards/rating.php": 2,
    "leaderboards/goals.php": 4,
    "leaderboards/double-digits.php": 4,
    "leaderboards/victims.php": 4,
    "leaderboards/streaks.php": 4,
    "leaderboards/milestones.php": 8,
    "leaderboards/peak-rating.php": 4,
    "leaderboards/activity/peaks.php": 4,
    "leaderboards/activity/participation.php": 4,
    "leaderboards/activity/in-a-row.php": 4,
    "amiga/leaderboards/rating.php": 2,
    "amiga/leaderboards/goals.php": 4,
    "amiga/leaderboards/double-digits.php": 4,
    "amiga/leaderboards/victims.php": 4,
    "amiga/leaderboards/peak-rating.php": 4,
    "amiga/leaderboards/performance-rating.php": 3,
    "amiga/leaderboards/tournament-honours.php": 4,
    "amiga/leaderboards/calendar-geo.php": 4,
}


def migrate_head(content: str, default_sort: int) -> str:
    if "k2_lb_table_sort_state" in content:
        return content

    content = content.replace(
        "<?php k2_table_wrap_open(true); ?>\n\n<table",
        "<?php k2_table_wrap_open(true); ?>\n<?php $lbSort = k2_lb_table_sort_state("
        + str(default_sort)
        + "); ?>\n\n<table",
        1,
    )
    # Amiga LBs had $k2LbAnchorCol block between wrap and table
    block_pat = re.compile(
        r"<\?php k2_table_wrap_open\(true\); \?>\s*\n\s*<\?php\s*\n"
        r"\$k2LbAnchorCol = 2;\s*\n"
        r"\$k2LbDefaultSortCol = \d+;\s*\n"
        r"\?>\s*\n<table",
        re.MULTILINE,
    )
    content, n_block = block_pat.subn(
        "<?php k2_table_wrap_open(true); ?>\n<?php $lbSort = k2_lb_table_sort_state("
        + str(default_sort)
        + "); ?>\n<table",
        content,
        count=1,
    )
    content = content.replace(
        "<?php k2_table_wrap_open(true); ?>\n\t\t<table",
        "<?php k2_table_wrap_open(true); ?>\n<?php $lbSort = k2_lb_table_sort_state("
        + str(default_sort)
        + "); ?>\n\t\t<table",
        1,
    )

    table_pat = re.compile(
        r'(<table class="<\?php echo k2_h\(k2_table_ranked_leaderboard_class\(\)\); \?>" '
        r'data-k2-table="sortable" data-k2-autorank="true" )'
        r'data-k2-anchor-col="2" data-k2-default-sort="\d+" data-k2-default-direction="desc">'
    )
    replacement = (
        r'\1data-k2-anchor-col="<?php echo $lbSort[\'anchor\']; ?>" '
        r'data-k2-default-sort="<?php echo $lbSort[\'sort_col\']; ?>" '
        r'data-k2-default-direction="<?php echo k2_h($lbSort[\'sort_dir\']); ?>"'
        r'<?php echo k2_table_skip_initial_sort_attr(' + str(default_sort) + '); ?>>'
    )
    content, n = table_pat.subn(replacement, content, count=1)
    if n == 0:
        raise ValueError("table tag not updated")

    return content


def migrate_thead(content: str) -> str:
    def repl_section(m: re.Match[str]) -> str:
        block = m.group(0)
        if "k2_lb_th(" in block:
            return block
        col = 0
        out: list[str] = []
        pos = 0
        for th in re.finditer(r"<th\b", block):
            start = th.start()
            out.append(block[pos:start])
            # find end of opening th tag
            gt = block.find(">", th.start())
            opening = block[th.start() : gt + 1]
            rest = opening[3:]  # after <th
            extra = ""
            m_cls = re.search(r'class="([^"]*)"', rest)
            if m_cls:
                extra = m_cls.group(1)
            inject = f'<th<?php echo k2_lb_th({col}, $lbSort, {json_extra(extra)}); ?>'
            if rest.strip():
                # keep data-k2-* attrs from original
                attrs = re.sub(r'\s*class="[^"]*"', "", rest).strip()
                if attrs.startswith(" "):
                    new_open = inject + attrs + ">"
                elif attrs:
                    new_open = inject + " " + attrs + ">"
                else:
                    new_open = inject + ">"
            else:
                new_open = inject + ">"
            out.append(new_open)
            col += 1
            pos = gt + 1
        out.append(block[pos:])
        return "".join(out)

    def json_extra(extra: str) -> str:
        if extra == "":
            return "''"
        return "'" + extra.replace("'", "\\'") + "'"

    return re.sub(r"<thead>.*?</thead>", repl_section, content, count=1, flags=re.DOTALL)


def migrate_tbody_row(content: str) -> str:
    if "k2_lb_td(" in content:
        return content

    def repl_tbody(m: re.Match[str]) -> str:
        body = m.group(0)
        if "k2_lb_td(" in body:
            return body

        lines = body.split("\n")
        out_lines: list[str] = []
        col = 0
        in_data_row = False
        for line in lines:
            if "<tr>" in line and "colspan" not in line:
                in_data_row = True
                col = 0
                out_lines.append(line)
                continue
            if "</tr>" in line:
                in_data_row = False
                out_lines.append(line)
                continue
            if not in_data_row:
                out_lines.append(line)
                continue
            m_td = re.match(r"^(\s*)<td(\s+class=\"([^\"]*)\")?>(.*)$", line)
            if not m_td:
                out_lines.append(line)
                continue
            indent, _, cls, rest = m_td.groups()
            extra = cls or ""
            extra_arg = f", '{extra}'" if extra else ""
            out_lines.append(
                f'{indent}<td<?php echo k2_lb_td({col}, $lbSort{extra_arg}); ?>>{rest}'
            )
            col += 1
        return "\n".join(out_lines)

    return re.sub(r"<tbody[^>]*>.*?</tbody>", repl_tbody, content, count=1, flags=re.DOTALL)


def migrate_file(rel: str, default_sort: int) -> None:
    path = PHP_ROOT / rel.replace("/", "\\") if "\\" in str(PHP_ROOT) else PHP_ROOT / rel
    text = path.read_text(encoding="utf-8")
    if "k2_lb_th(" in text:
        print(f"skip (already migrated): {rel}")
        return
    text = migrate_head(text, default_sort)
    text = migrate_thead(text)
    text = migrate_tbody_row(text)
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"migrated: {rel}")


def main() -> int:
    for rel, default_sort in FILES.items():
        try:
            migrate_file(rel, default_sort)
        except Exception as exc:
            print(f"FAIL {rel}: {exc}", file=sys.stderr)
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
