# Amiga player rank chart — implementation plan

**Status:** Ready to execute (Jun 2026). Policy locked.  
**Policy:** [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md)

**In scope (v1):** Solo rank-over-time chart on `/amiga/player/profile.php` · JSON API · scale/window/line controls · time travel · Amiga only.

**Out of scope (v1):** H2H rank compare · online realm · X date-range zoom · smart default algorithm · milestone annotations · explainer copy · percentile slider · new DB tables · git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>" unless Dagh asks.

**Migration:** **L0** — read `amiga_player_elo_rank_at_event` only; **no Part B**.

---

## How to use this plan

1. Execute slices **in order** (or user says “slice N”).
2. Run each slice **Verification** before moving on.
3. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless user asks.
4. After slice 5 ships: **UPDATE_DOCS** Part A — MEMORY, policy status, `amiga-profile-v0.md`, `feature-log.md` row.

---

## Chart platform contract (mandatory)

Rank chart **must** match existing site chart conventions — do not invent parallel markup or colours.

| Contract | Source | Rule for rank chart |
|----------|--------|---------------------|
| Panel chrome | `theme.css` · [`activity-charts.md`](activity-charts.md) §3 | `.player-rank-chart.k2-chart-panel` — register selector in `theme.css` alongside `.player-rating-chart` |
| Max width | `--k2-chart-max-width` (960px) | Panel + frame cap |
| Frame height | `--k2-chart-frame-height` (271px default) | `.k2-chart-frame` fixed height; Chart.js `responsive: true`, `maintainAspectRatio: false` **inside frame** |
| Canvas sizing | [`activity-charts.md`](activity-charts.md) | **No** `width: 100% !important` / `aspect-ratio` hacks on `<canvas>` |
| Heading | [`design-direction.md`](design-direction.md) | `h3.k2-panel-heading` — e.g. **Elo rank**; **no** `k2-chart-block__hint` in v1 |
| Status line | `player-feast-sections.css` | `pm3d-chart__status k2-chart-panel__status` |
| Segment toggles | `pm3d-rating-toggle` / `pm3d-chart-toolbar` | Scale · window · line style; active = `.is-active` + `--k2-segment-active-*` |
| Colours | `js/chart-theme.js` | Solo line: `T.lineStroke(T.amber(), 0.15)` — **same as rating chart**; not `linkStar()`, not pitch/chrome, not H2H red |
| Tooltips | `T.mergeTooltip()` | Dark tooltips aligned with `.k2-table-tooltip` |
| Axes | `T.tickColor()`, `T.softGrid()` | Muted ticks; soft grid |
| Plot gutter | `T.careerChartGutterOptions()` | Left padding so Y labels do not jump when band/scale changes |
| Y-axis width | `T.careerChartYAxisOptions()` | **Required** on every chart rebuild (band toggles change tick label width) |
| Chart init | `T.createActivityChart()` + `T.activityChartOptions(..., { chartKind: 'line' })` | Same path as [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js) |
| Line defaults | `player-rating-chart.js` | `tension: 0.1`, `pointRadius: 0`, `pointHoverRadius: 4` when connected |
| Script load order | [`amiga/player/profile.php`](../site/public_html/amiga/player/profile.php) | `chart.umd` → adapter → `chart-theme.js` → `chart-date-range.js` → `player-rank-history.js` → `player-rank-chart.js` (defer) |
| Copy minimalism | Policy R17 | Tooltips + empty-band status only; defer peak line / summary strip unless trivial |

**Reference implementations (copy structure, not logic):**

- PHP shell: `amiga_profile_render_rating_chart()` in [`amiga_profile_blocks.php`](../site/public_html/includes/amiga_profile_blocks.php)
- JS init: [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js)
- Multi-row toolbar: [`player_opponents_h2h_charts.php`](../site/public_html/includes/player_opponents_h2h_charts.php) (compare rating block)

---

## Locked decisions (policy R1–R18)

Do not re-open without user. Full semantics in policy §2–§7.

**Defaults on first load:** Linear scale · **Career** window · Connected line.

**Data:** `amiga_player_elo_rank_at_event` — all global finalizes after debut (~489 points Fabio #109 vs ~39 participation snapshots).

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | User alignment — **done** |
| **1** | Read lib + JSON API + history loader JS | curl/JSON + row counts |
| **2** | Profile PHP shell + `theme.css` selector | Panel renders; scripts enqueue |
| **3** | Chart.js core (linear career + whole community, connected, inverted Y) | Fabio #109 + Darren #84 smoke |
| **4** | Full controls (all scales/windows/line toggle) + empty-band states | Policy §6 toolbar complete |
| **5** | Time travel + QA closure + docs | Hero rank = last point at cutoff |

---

## Slice 1 — API + read library + loader

### Goal

Server returns rank-at-event series; client loader mirrors `player-rating-history.js`.

### Tasks

- [ ] Create `site/public_html/includes/amiga_player_rank_history_lib.php`
  - `amiga_player_rank_history_points($con, int $playerId, ?AmigaSnapshotContext $ctx): array`
  - Query `amiga_player_elo_rank_at_event` JOIN `tournaments` for name
  - Order: `event_date ASC`, `event_chrono ASC`, `tournament_id ASC`
  - TT: omit rows `>` cutoff (same tuple order as `amiga_player_elo_rank_at_cutoff`)
  - Per point: `tournamentId`, `eventDate`, `eloRank`, `ladderSize` (count rows for `tournament_id`), `percentile` (policy R8), `tournamentName`
  - `meta`: `careerBestRank`, `careerWorstRank`, `ceiling`, `cutoffActive`, `timelineStart` (first point date or null)
- [ ] Create `site/public_html/api/player_rank_history.php`
  - `GET realm=amiga&id=` required; `as=` optional (wire from profile TT when present)
  - Reuse Amiga DB bootstrap pattern from `player_rating_history.php`
  - JSON shape per policy §4
- [ ] Create `site/public_html/js/player-rank-history.js`
  - `K2PlayerRankHistory.load(playerId, realm, options)` → fetch API
  - Pass through `as` from page when `document` / root `data-as` if needed

### Verification

```powershell
# JSON smoke (local)
curl -s "http://ratingskickoff.test/api/player_rank_history.php?realm=amiga&id=109" | php -r "echo json_encode(json_decode(stream_get_contents(STDIN), true)['meta'] ?? [], JSON_PRETTY_PRINT);"
```

```sql
-- Point count parity
SELECT COUNT(*) FROM amiga_player_elo_rank_at_event WHERE player_id = 109;
-- Expect API points.length match (present mode)
```

- [ ] Fabio #109: ~489 points; `meta.careerBestRank=1`, `careerWorstRank` ≈ 135
- [ ] Darren #84: points present; worst rank > best rank
- [ ] Invalid id → 400; unknown player → empty/error consistent with rating API
- [ ] `percentile` matches `100 * (ladderSize - eloRank + 1) / ladderSize` spot-check

### Files

- `site/public_html/includes/amiga_player_rank_history_lib.php`
- `site/public_html/api/player_rank_history.php`
- `site/public_html/js/player-rank-history.js`

---

## Slice 2 — Profile shell + CSS registration

### Goal

Empty chart panel on profile with correct chrome and script tags.

### Tasks

- [ ] `amiga_profile_render_rank_chart(int $playerId)` in [`amiga_profile_blocks.php`](../site/public_html/includes/amiga_profile_blocks.php)
  - Mirror rating block: section wrapper, `.player-rank-chart.k2-chart-panel`, `data-player-id`, `data-realm="amiga"`
  - `h3.k2-panel-heading` — **Elo rank**
  - `.pm3d-chart-toolbar` placeholder rows for toggles (slice 4 fills behaviour)
  - Status + `.k2-chart-frame` + canvas `aria-label="Elo rank over time"`
  - **No** hint paragraph
- [ ] Call from [`amiga/player/profile.php`](../site/public_html/amiga/player/profile.php) below rating chart
- [ ] Enqueue scripts (after existing chart stack): `player-rank-history.js`, `player-rank-chart.js` with `filemtime` cache-bust
- [ ] Add `body.k2-site .player-rank-chart` to panel selector list in [`theme.css`](../site/public_html/stylesheets/theme.css)
- [ ] Optional: `.player-feast-body .k2-chart-frame` rules already apply if class structure matches rating chart

### Verification

- [ ] Profile loads without JS errors when chart init is stub/no-op
- [ ] Panel shows 960px max width, bordered surface, 271px frame
- [ ] View source: script order correct

---

## Slice 3 — Chart core (linear + date X)

### Goal

One working chart: **Linear · Career · Connected** (policy default).

### Tasks

- [ ] Create `site/public_html/js/player-rank-chart.js`
  - Init on `.player-rank-chart` roots (same pattern as rating chart `initRoot`)
  - Load via `K2PlayerRankHistory`
  - Build `{ x: Date, y: rank | null, raw: {...} }` per point
  - X: time scale; range from `meta.timelineStart` month start through end of current month (reuse `K2ChartDateRange.profileCareerTimeRange()` if compatible, else first point → last point + padding)
  - Y: **inverted** linear rank — Career window (policy R13 padding)
  - Dataset: `T.lineStroke(T.amber(), 0.15)`, connected, `spanGaps: false`
  - Tooltip: date, tournament name, `#rank of N`, percentile one decimal
  - Empty series: status text (pre-debut / no data)
  - Use `withCareerPlotGutter` / `careerYScale` equivalents from rating chart

### Verification (browser)

- [ ] Fabio #109 — Career: visible climb; not a flat hairline at top for whole career
- [ ] Darren #84 — Career: drift ~57 → ~300 readable
- [ ] Tooltip shows event-local N (e.g. `#135 of 177` early Fabio)
- [ ] Resize window — canvas not stretched/blurry (frame contract)

---

## Slice 4 — Controls (scales, windows, line style)

### Goal

Full policy §6 toolbar.

### Tasks

- [ ] **Scale toggle:** Linear · Log · Percentile (`data-scale`)
- [ ] **Window toggle** (contextual):
  - Linear: Top 20 · Top 50 · Top 100 · Career · Whole community
  - Log: hide or static label “Full ladder” (whole-community domain only)
  - Percentile: Full · 50–100 · 90–100 · 95–100
- [ ] **Line toggle:** Connected · Stepped
- [ ] Rebuild chart on any control change; preserve `careerChartYAxisOptions`
- [ ] **Band clip (linear Top K):** `y: null` when `eloRank > K`; empty status when never in band
- [ ] **Log:** transform ticks via callback; domain 1…ceiling on log scale
- [ ] **Percentile:** y = precomputed percentile; axis per preset; #1 at top (high percentile at top)
- [ ] Hide/disable invalid window buttons when scale changes

### Verification

| Player | Check |
|--------|-------|
| Fabio #109 | Whole community shows early rank; Top 20 line starts at first ≤20; Log readable full span; Percentile Full ~24% → ~100% |
| Darren #84 | Top 20 → empty status; Career + Whole community OK; Percentile Full stable ~36% recent |
| Never top 20 | Pick from QA list in policy §8 — status not crash |

---

## Slice 5 — Time travel + closure

### Goal

TT parity with hero; docs updated.

### Tasks

- [ ] API + loader: honour profile `?as=` (pass from PHP `data-as` or URL on fetch)
- [ ] Truncated series; recompute `meta.ceiling` / career min/max on client or trust server meta
- [ ] Pre-debut cutoff: empty chart + muted status (no `#0`)
- [ ] Spot-check: last chart point `eloRank` = hero rank at same cutoff
- [ ] **UPDATE_DOCS** Part A: MEMORY, policy status → implemented, `amiga-profile-v0.md`, `feature-log.md`

### Verification

- [ ] `/amiga/player/profile.php?id=237&as=year:2003` — rank chart truncated; hero `#N` matches last point
- [ ] Present mode: last point rank = `amiga_player_current.elo_rank`

---

## File checklist (cumulative)

| File | Slice |
|------|-------|
| `docs/amiga-player-rank-chart-policy.md` | 0 |
| `docs/amiga-player-rank-chart-implementation-plan.md` | 0 |
| `includes/amiga_player_rank_history_lib.php` | 1 |
| `api/player_rank_history.php` | 1 |
| `js/player-rank-history.js` | 1 |
| `includes/amiga_profile_blocks.php` (rank render) | 2 |
| `amiga/player/profile.php` | 2 |
| `stylesheets/theme.css` | 2 |
| `js/player-rank-chart.js` | 3–4 |

Optional later: `scripts/oneoff/amiga_rank_history_probe.php` for CLI JSON smoke.

---

## Acceptance fixtures (manual QA)

From policy §8 — run after slice 4–5.

| ID | Name | Role |
|----|------|------|
| 109 | Fabio F | Elite arc; band clip; log; percentile |
| 84 | Darren G | Mid-table drift; Top 20 empty |
| TBD | never ≤ top 100 | Empty-band status |

---

## Related docs

| Doc | When |
|-----|------|
| [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md) | Product rules |
| [`amiga-profile-v0.md`](amiga-profile-v0.md) | Profile surface register |
| [`design-direction.md`](design-direction.md) | Chart palette |
| [`player-profile-feast.md`](player-profile-feast.md) | Surface rhythm |
| [`activity-charts.md`](activity-charts.md) | Frame contract |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Cutoff behaviour |