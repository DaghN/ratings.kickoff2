# K2 tooltip policy

**Status:** Locked intent (Jun 2026). Agent entry point for hover help on tables, charts, and chrome controls.
**Authority:** Product + visual contract; defers to [`design-direction.md`](design-direction.md) microcopy. Deep table contract: [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md) § `k2-table.js`. Dagh's latest chat wins on scope.

**For agents:** read this before adding column help, header abbreviations, chart hovers, or control tooltips. Pair with [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) when the surface is a table.

---

## Purpose

The site uses one dark tooltip look (`.k2-table-tooltip` in `theme.css`, tokens `--k2-tooltip-*`). Native browser `title` tooltips are slow, unstyled, and inconsistent with Chart.js hovers and table header help.

**Common agent failure:** ship a sortable table with the correct cloak/mirror stack but put help text in `title="..."` on `<th>`. That looks done until you hover.

---

## Locked decisions

| ID | Decision |
|----|----------|
| T1 | **No native `title` on site-owned chrome** — tables, controls, links, column help, etc. Supplemental hover copy → `data-k2-help` + K2 tooltip (or Chart.js merge). |
| T2 | **Table headers and body cells** → `data-k2-help` (+ optional `data-k2-tooltip-label`) wired by `js/k2-table.js`. Enqueue via `k2_sortable_table_assets_head.inc.php`, `k2_lb_sortable_table_head.inc.php`, or `k2_table_js_enqueue()` on static header-help pages. |
| T3 | **Hub leaderboard column copy** → shared helpers in `includes/lb_column_help.php` (`k2_lb_help_*()`), not one-off inline strings. |
| T4 | **Chart.js hovers** → `js/chart-theme.js` (`mergeTooltip()` / `applyTooltipDefaults()`); colours from `--k2-tooltip-*` (same surface as table tooltips). |
| T5 | **Custom DOM widgets** (calendar day, heatmap cell, jukebox FAB, time-travel stamp, coarse-tap pin) → build/position an element with class `k2-table-tooltip` (+ `__title` / `__body` children). Copy the nearest reference JS file; do not invent a new bubble style. |
| T6 | **Supplemental copy only** — abbreviations, formulas, unfamiliar rules, hidden context. Do not tooltip text that only repeats the visible label; sortable headers may rely on shared **Click to sort.** when no extra explanation is needed. |

---

## Cross-origin embeds (YouTube and similar)

Tooltips **inside** an embedded player (YouTube controls, progress bar, etc.) are rendered by the third-party iframe. K2 **cannot** restyle them.

**Policy:** do **not** remove or avoid `title` on the iframe element to chase K2 parity. Keep a descriptive iframe `title` (and visible caption/h3/prose where the page already has it). Site-owned help **around** the player — caption Elo links, games-table Rating headers — still follows T1–T6.

---

## Pick a reference (read one file first)

| Scenario | Reference | Mechanism |
|----------|-----------|-----------|
| Sortable games table headers | `games/recent.php`, `games/all.php` | `data-k2-help` + `data-k2-tooltip-label` on `<th>`; sortable assets in head |
| Amiga tournament games table | `amiga_tournament_render_games_table()` in `includes/amiga_tournament_lib.php` | Same games-column help as Recent |
| Static single-game row headers | `game.php`, `amiga/game.php` | `data-k2-help` on `<th>`; `k2_table_js_enqueue()` in head |
| Hub LB sortable headers | Any `leaderboards/*.php` wing + `includes/lb_column_help.php` | `data-k2-help` on `<th>` via `k2_lb_th` + help attrs |
| Activity peak / streak body cells | `includes/lb_activity_lib.php` → `k2_lb_activity_echo_tooltip_td()` | `data-k2-help-html="1"` + `data-k2-tooltip-hide-title="1"` + `tabindex="0"` |
| Hall of Fame label/value cells | `hall-of-fame.php` | `k2-table-helped` + `data-k2-help` |
| Chart panel hover | `js/chart-theme.js`, `docs/activity-charts.md` | `T.mergeTooltip(...)` |
| Non-table control (FAB, mode toggle) | `includes/k2_jukebox.php`, `includes/amiga_time_mode_nav.php` | `data-k2-help` + `k2_table_js_enqueue()` |
| Amiga player games status-line perf rating | `amiga/player/games.php` | `data-k2-help` + `data-k2-tooltip-label` on `.k2-player-games-status__perf`; sortable table assets in head |
| WC Videos spotlight caption Elo links | `amiga_tournament_videos_lib.php` → `amiga_tournament_videos_wc_game_caption_html()` | `data-k2-help` on `.k2-tournament-videos__rating-link`; re-bind via `k2TableInitHelpTooltips()` after in-session caption swap |
| Embedded video iframe | `game.php`, `amiga_tournament_videos_*.inc.php`, `join_page_section.php`, `amiga-tournament-videos.js` | iframe `title` kept; player-internal tooltips out of scope (see § Cross-origin embeds) |
| Player calendar day hover | `js/player-feast/player-calendar.js` | Programmatic `.k2-table-tooltip` |
| Player name hover glance (online + Amiga) | `js/amiga-player-glance.js`, `includes/amiga_player_glance_config.php`, `api/player_glance.php`, `api/amiga_player_glance.php` | Read-only hero popover; **opt-in** `data-k2-player-glance` / `data-k2-amiga-player-glance` on `k2_player_link()` / `k2_amiga_player_link()` only (not profile nav pills); opaque canvas instant, print + shadow fade **850ms**; fetch on hover; **500ms** show gate; tier **B** default; `?k2_glance=A\|B` |
| H2H scoreline heatmap cell | `js/player-h2h-scoreline-heatmap.js` | Programmatic `.k2-table-tooltip` |

If unsure: **grep** `data-k2-help` in `site/public_html/` and open the nearest neighbour.

---

## Table header markup (T2)

Minimal sortable header with label + help:

```html
<th data-k2-sort="number"
    data-k2-tooltip-label="Goal difference"
    data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
```

Multi-line help: use `&#10;` line breaks in the attribute (see **Fav ES** / **Adjustment** on `games/recent.php`).

PHP wide tables: put attrs on the same `<th>` as `k2_table_sortable_th_attr(...)` — **never** `title="..."`.

Body-cell tooltips (peaks, streaks, HoF values):

- `class="k2-table-helped"` (applied by JS on headers; set on cells when hand-authored)
- `tabindex="0"` for keyboard/coarse tap
- `data-k2-tooltip-hide-title="1"` when the visible cell text is the label

---

## Before shipping — self-check

- [ ] Read reference from § above (not only this policy).
- [ ] No `<th ... title=` for column help (run `python scripts/audit_k2_table_compliance.py`).
- [ ] Page loads `k2-table.js` when headers/cells use `data-k2-help`.
- [ ] Hub wings use `lb_column_help.php` where a shared string exists.
- [ ] Chart work uses `chart-theme.js`, not raw Chart.js tooltip defaults.
- [ ] Hover once in browser: dark `.k2-table-tooltip`, not OS-native yellow tooltip.

---

## Anti-patterns

| Do not | Do instead |
|--------|------------|
| `title="..."` on `<th>` for GD / Elo / formula help | `data-k2-help` + optional `data-k2-tooltip-label` |
| Bare `k2_table_js_enqueue()` without reading table checklist | Sortable stack from [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) |
| Inline duplicate of hub LB help strings | `k2_lb_help_*()` in `lb_column_help.php` |
| New tooltip CSS class / bubble colour | `.k2-table-tooltip` or Chart.js theme merge |
| Tooltip that repeats the column header verbatim | Omit help or use **Click to sort.** only |

---

## Audit

`python scripts/audit_k2_table_compliance.py` reports **Tier C** sortable-table stack gaps, **`<th title=…>`** violations, and a **broader tooltip pass** (PHP `title=` on site-owned help surfaces, JS native `title`, Chart.js without `mergeTooltip`). iframe `title` on video embeds is allowlisted — see § Cross-origin embeds.

---

*Last updated: Jun 2026 — keep in sync when a new tooltip surface ships (add a row to § Pick a reference).*