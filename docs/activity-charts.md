# Activity charts — architecture & v2 plan

**Page:** `site/public_html/server1.php` (hub tab **Activity**).  
**Authority:** Product taste → [`design-direction.md`](design-direction.md). Hub data patterns → [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) (summary PHP only; charts are client-side). **This doc** owns chart JS/CSS structure, panel registry, lab → promote.

**Status (Jun 2026):** **L3 shipped** — [`server1.php`](../site/public_html/server1.php) loads only [`js/activity-charts-v2.js`](../site/public_html/js/activity-charts-v2.js); panels in [`includes/server_activity_chart_panels.php`](../site/public_html/includes/server_activity_chart_panels.php); `body.k2-activity-charts`. Legacy `activity-charts.js` + eleven `server-*-chart.js` boot files **removed**. [`server1-charts-lab.php`](../site/public_html/server1-charts-lab.php) redirects to `server1.php`. Chart panels are now grouped into five question-led Activity sections (`k2-activity-section`) with short intro copy above the factual chart headings.

---

## 1. Goal

One professional **Activity charts feature**:

- **One JS owner** (module or single bundle) with an explicit **panel registry** — not eleven copy-paste boot files.
- **PHP JSON APIs unchanged** — same URLs, query params, response shapes.
- **CSS chart panel component** — capped width (~960px), Chart.js `responsive` inside a **frame** — never stretch the `<canvas>` with `%` / `aspect-ratio` hacks.
- **Lab → promote** — parallel surface until parity; then one switch on `server1.php` and delete legacy boot files.

---

## 2. Non-goals (v1 lab / first promotion)

| Deferred | Reason |
|----------|--------|
| Viewport lazy-load (`IntersectionObserver`) | After sequential loader proven; avoids boot-order bugs |
| Phone long-press tooltips (scroll-safe) | After lazy load / animation baseline |
| Changing stored aggregates or APIs | Front-end restructure only |
| Profile / milestone charts | Out of scope; they keep `chart-theme.js` tokens only |

---

## 3. Target architecture

```
server1.php (markup: panels + status lines)
    ↓
api/server_*.php (JSON, realm=online)
    ↓
activity-charts-v2.js (single module)
    registry → fetch → buildChart / buildHeatmap
    loader: sequential v1; optional lazy v2
    ↓
chart-theme.js — colours, mergeTooltip, activityChartOptions (per-instance only)
chart-date-range.js — cumulative established only (K2ChartDateRange)
    ↓
Chart.js 4.4.7 + date-fns adapter
```

### `chart-theme.js` (keep slim)

- Colour helpers (`pitch`, `chrome`, `barStroke`, `lineStroke`, …).
- `mergeTooltip()` / `applyTooltipDefaults()` — dark tooltips aligned with `.k2-table-tooltip`; `multiKeyBackground` + solid `labelColor` (no white inside swatches).
- `activityChartOptions(userOptions, { chartKind })` — interaction/events; bar grow-up via **`createActivityChart()`** when `ACTIVITY_BAR_ENTRANCE_ENABLED` is true in `chart-theme.js` (**false** Jun 2026); otherwise plain `new Chart`. Chart.js **`responsive: true`**; phone: no tooltips / no `touchstart`; **must not** mutate `Chart.defaults`.

### Activity module (to build)

- **`registerPanel({ id, selector, run })`** — `run(root)` returns a `Promise`; clears/sets `.…-status` inside panel.
- **`boot()`** — only on **`DOMContentLoaded`** (defer scripts must register before boot).
- **v1 loader:** one panel at a time, ~100ms gap. No `resizeChart` during mount; no post-boot `resizeAll`. `window` `resize` listener attached only after load + 600ms buffer. Bar charts: `resizeDelay: 600`, `maintainAspectRatio: false`, `chartKind: 'bar'` for grow-up.
- **Heatmap:** DOM grid in `run()`, not Chart.js — same API as today.

### CSS (target)

```html
<div class="server-games-day-chart k2-chart-panel">
  <h2 class="k2-panel-heading">…</h2>
  <p class="server-games-day-chart-status">…</p>
  <div class="k2-chart-frame">
    <canvas aria-label="…"></canvas>
  </div>
</div>
```

- **`.k2-chart-panel`** — existing panel chrome (`max-width: var(--k2-chart-max-width)`, padding, border) — consolidate selectors in `theme.css`.
- **`.k2-chart-frame`** — `max-width: 960px; width: 100%; margin: 0 auto;` optional fixed height (271px / 360px for tall chart). Chart.js sizes canvas to **frame**, not viewport.
- **Do not** set `width: 100% !important` / `aspect-ratio` on `<canvas>` — causes bitmap stretch (Jun 2026 regression).

### Dependencies (unchanged)

| Asset | Role |
|-------|------|
| `js/chart.umd.min.js` | Chart.js 4.4.7 |
| `js/chartjs-adapter-date-fns.bundle.min.js` | Time scales |
| `js/chart-theme.js` | Tokens + tooltips + `activityChartOptions` |
| `js/chart-date-range.js` | Cumulative established: `appendRatingThroughToday`, `endOfToday` |

---

## 4. Lab → promote

### Production surface (shipped)

- **`server1.php`** — `body.k2-activity-charts`; one script `activity-charts-v2.js`; panels include with `.k2-chart-frame`.
- **`server1-charts-lab.php`** — 302 → `server1.php` (old bookmarks).

### Phases

| Phase | Deliverable |
|-------|-------------|
| **L1** | Lab + games/day + `.k2-chart-frame` — done |
| **L2** | All 12 panels in v2 — done |
| **L3** | Promote to `server1.php`; delete legacy boot files — **done** |
| **L4** | Bar grow-up animation — **done** (`chartKind` in v2; phone + desktop); optional: lazy load, phone long-press tooltips |

### Promotion gate

- All rows in §6 **Pass** on lab URL.
- No console errors on load.
- Dagh sign-off on visual baseline (desktop 960px-cap panels, phone scroll + fit).

### Legacy removal

**Done (Jun 2026):** removed `activity-charts.js` and eleven `server-*-chart.js` boot files. History in git; APIs unchanged.

---

## 5. Panel registry (parity contract)

**Load order** = table order (matches `server1.php`).

| # | Panel class | API | Chart | Notes |
|---|-------------|-----|-------|--------|
| 1 | `.server-games-day-chart` | `api/server_games_by_day_recent.php?realm=online` | bar, time day | Status: `.server-games-day-chart-status` |
| 2 | `.server-games-month-chart` | `api/server_games_by_month.php?realm=online` | bar, time month | `barSolid` pitch |
| 3 | `.server-games-year-chart` | `api/server_games_by_year.php?realm=online` | stacked bar | YTD + projected remainder; tooltip footer pace |
| 4 | `.server-activity-heatmap` | `api/server_games_by_day_year.php?realm=online` | DOM grid | `.activity-heatmap-wrap` (full chart width); cells from `ResizeObserver`; `overflow-x` only when grid wider than wrap; month row spans weeks per month |
| 5 | `.server-active-players-month-chart` | `api/server_active_players_by_month.php?realm=online` | bar, time month | `barSolid` chrome |
| 6 | `.server-daily-active-players-chart` | `api/server_daily_active_players.php?realm=online&source=stored` | line, 30d rolling | Client-side **calendar** 30-day trailing mean (gap days = 0); smooth line (`stepped: false`) |
| 7 | `.server-matchup-breadth-chart` | `api/server_matchup_breadth.php?realm=online` | bar, time month | `barSolid` holo |
| 8 | `.server-established-players-year-chart` | `api/server_established_players_by_year.php?realm=online` | bar, time year | `games_required` in tooltip |
| 9 | `.server-cumulative-established-month-chart` | `api/server_cumulative_established_by_month.php?realm=online` | line, stepped | `K2ChartDateRange`; tooltip body: `Total established: N` only |
| 10 | `.server-established-rating-distribution-chart` | `api/server_established_rating_distribution.php?realm=online&bucket=100&min_games=20` | bar, category | ELO buckets; % in afterLabel |
| 11 | `.server-top-activity-eras-chart` | `api/server_top_activity_eras.php?realm=online` | multi-line | Top 10 by `NumberGames`; 6-mo trailing avg; **desktop** dataset hover highlight; coarse: nearest, no highlight |
| 12 | `.server-play-texture-chart` | `api/server_play_texture.php?realm=online` | 4× line | Dual y-axis (`yLeft` / `yRight`) |

**Summary block** above charts is **PHP-only** (`generalstatstable`, `playertable`, `server_period_game_totals` for busiest day) — not part of chart module.

---

## 6. Parity checklist (per panel)

Use when porting each row in §5.

| Check | Pass? |
|-------|-------|
| Status clears on success; empty + error copy matches legacy | |
| Data renders (spot-check vs production API JSON) | |
| Tooltip title/label/footer match legacy | |
| Desktop: hover/tooltip sensible | |
| Android: scroll + pinch-zoom over chart panels (`touch-action: pan-y pinch-zoom`; Chart.js tooltips off on coarse) | |
| Panel width ≤ 960px, not stretched; readable axis labels | |
| No duplicate init (single chart instance) | |

---

## 7. Mobile v1 (definition of done)

- **Scroll / layout on phone:** tune **inside the lab page only**; no site-wide viewport or hub CSS experiments.
- **Layout:** Chart.js inside `.k2-chart-frame` on `body.k2-activity-charts` (`server1.php`).
- **Tooltips:** desktop hover/tap via Chart.js `touchstart`; **phone** — tooltips disabled + `touch-action: pan-y pinch-zoom` on panels/canvases so scroll and pinch-zoom work (heatmap tooltips desktop-only).
- **Perf:** sequential load; heaviest panels (11, 12) last in queue.

---

## 8. Current implementation

- [`js/activity-charts-v2.js`](../site/public_html/js/activity-charts-v2.js) — panel registry, sequential `drain()` on `DOMContentLoaded`, gated by `body.k2-activity-charts`.
- [`includes/server_activity_chart_panels.php`](../site/public_html/includes/server_activity_chart_panels.php) — five question-led `k2-activity-section` groups plus `.k2-chart-frame` markup.

**Do not** add new per-chart boot files. **Do not** reintroduce `whenBlockVisible`, `createChart`, `mergeChartOptions`, global `Chart.defaults` mutation, or canvas `%` sizing.

---

## 9. Reuse vs discard

| Kept | Removed Jun 2026 (git history) |
|------|-------------------------------|
| All `api/server_*.php` endpoints | Eleven `server-*-chart.js` boot files |
| Slim `chart-theme.js` API | `activity-charts.js` orchestrator |
| Panel class names + `.k2-chart-frame` | Per-file `initRoot` duplicates |
| Heatmap DOM + CSS classes | Lab duplicate page (redirect only) |

---

## 10. Related docs

| Doc | Relationship |
|-----|----------------|
| [`design-direction.md`](design-direction.md) | Activity copy, chart palette, tooltip look |
| [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) | Status hub data; Activity summary tables |
| [`player-profile-feast.md`](player-profile-feast.md) | Profile charts (separate; share `chart-theme.js`) |
| [`website-data-contract.md`](website-data-contract.md) | Stored aggregates behind APIs (e.g. daily active `source=stored`) |
| [`coordination/feature-log.md`](coordination/feature-log.md) | Light index row when v2 promotes |

---

## 11. Agent notes

- **Bootstrap:** Read this file when task says Activity charts, `server1.php` graphs, chart loader, or mobile chart UX.
- **Migration:** Front-end only — **no** Part B unless new DB columns/APIs added.
- **Finish:** On lab ship or promote, UPDATE_DOCS Part A — `PROJECT_MEMORY.md` + this file status line + optional feature-log row.
