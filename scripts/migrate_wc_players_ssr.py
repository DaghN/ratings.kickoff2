#!/usr/bin/env python3
"""Add k2_lb_th SSR headers to amiga_wc_players_table.php."""
from __future__ import annotations

import re
from pathlib import Path

PATH = Path(__file__).resolve().parents[1] / "site/public_html/includes/amiga_wc_players_table.php"

TABLE_REPLACEMENTS = [
    (
        'data-k2-anchor-col="2" data-k2-default-sort="5" data-k2-default-direction="desc"',
        'data-k2-anchor-col="<?php echo $lbSort[\'anchor\']; ?>" data-k2-default-sort="<?php echo $lbSort[\'sort_col\']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort[\'sort_dir\']); ?>"<?php echo k2_table_skip_initial_sort_attr(5); ?>',
    ),
    (
        'data-k2-anchor-col="2" data-k2-default-sort="9" data-k2-default-direction="desc"',
        'data-k2-anchor-col="<?php echo $lbSort[\'anchor\']; ?>" data-k2-default-sort="<?php echo $lbSort[\'sort_col\']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort[\'sort_dir\']); ?>"<?php echo k2_table_skip_initial_sort_attr(9); ?>',
    ),
    (
        'data-k2-anchor-col="2" data-k2-default-sort="7" data-k2-default-direction="desc"',
        'data-k2-anchor-col="<?php echo $lbSort[\'anchor\']; ?>" data-k2-default-sort="<?php echo $lbSort[\'sort_col\']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort[\'sort_dir\']); ?>"<?php echo k2_table_skip_initial_sort_attr(7); ?>',
    ),
]


def migrate_thead(content: str) -> str:
    if "k2_lb_th(" in content:
        # may already be partial
        pass

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
            gt = block.find(">", th.start())
            rest = block[th.start() + 3 : gt + 1]
            extra = ""
            m_cls = re.search(r'class="([^"]*)"', rest)
            if m_cls:
                extra = m_cls.group(1)
            extra_arg = f", '{extra}'" if extra else ", ''"
            inject = f"<th<?php echo k2_lb_th({col}, $lbSort{extra_arg}); ?>"
            attrs = re.sub(r'\s*class="[^"]*"', "", rest).strip()
            if attrs:
                new_open = inject + (" " + attrs if not attrs.startswith(" ") else attrs) + ">"
            else:
                new_open = inject + ">"
            out.append(new_open)
            col += 1
            pos = gt + 1
        out.append(block[pos:])
        return "".join(out)

    return re.sub(r"<thead>.*?</thead>", repl_section, content, flags=re.DOTALL)


def main() -> None:
    text = PATH.read_text(encoding="utf-8")
    for old, new in TABLE_REPLACEMENTS:
        text = text.replace(old, new)
    text = migrate_thead(text)
    text = text.replace("$lbSort[\\'anchor\\']", "$lbSort['anchor']")
    text = text.replace("$lbSort[\\'sort_col\\']", "$lbSort['sort_col']")
    text = text.replace("$lbSort[\\'sort_dir\\']", "$lbSort['sort_dir']")
    text = re.sub(r'(data-k2-sort="[^"]*")>>', r"\1>", text)
    text = text.replace('?>>">', '?>">')
    PATH.write_text(text, encoding="utf-8", newline="\n")
    print("migrated", PATH.name)


if __name__ == "__main__":
    main()
