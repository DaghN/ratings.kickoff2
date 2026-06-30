# Amiga player rank chart — implementation plan

**Status:** **Complete** (Jun 2026). Solo + H2H compare slices done local.  
**Policy:** [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md)

**In scope (v1):** Solo rank-over-time chart on `/amiga/player/profile.php` · JSON API · scale/window controls · stepped line · time travel · Amiga only.

**Out of scope (solo v1 only):** online realm · in-chart X date trim/zoom (full community `timelineStart` → today only) · smart default algorithm · milestone annotations · explainer copy · percentile slider · new DB tables.

**H2H compare:** **Shipped** Jun 2026 — see § H2H compare slices + [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md). **Online** H2H rank compare: **not planned** (Amiga only).

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
| Heading | [`design-direction.md`](design-direction.md) | `h3.k2-panel-heading` — **Elo rank**; `k2-chart-block__hint` — end-of-day rank after each tournament day |
| Status line | `player-feast-sections.css` | `pm3d-chart__status k2-chart-panel__status` |
| Segment toggles | `pm3d-rating-toggle` / `pm3d-chart-toolbar` | Scale · window (contextual via `data-range-mode`); active = `.is-active` + `--k2-segment-active-*` |
| Colours | `js/chart-theme.js` | Solo line: `T.lineStroke(T.amber(), 0.15)` — **same as rating chart**; not `linkStar()`, not pitch/chrome, not H2H red |
| Tooltips | `T.mergeTooltip()` | Dark tooltips aligned with `.k2-table-tooltip` |
| Axes | `T.tickColor()`, `T.softGrid()` | Muted ticks; soft grid |
| Plot gutter | `T.careerChartGutterOptions()` | Left padding so Y labels do not jump when band/scale changes |
| Y-axis width | `T.careerChartYAxisOptions()` | **Required** on every chart rebuild (band toggles change tick label width) |
| Chart init | `T.createActivityChart()` + `T.activityChartOptions(..., { chartKind: 'line' })` | Same path as [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js) |
| Line defaults | `player-rank-chart.js` | **Stepped only** (`stepped: true`); `tension: 0`, `pointRadius: 0`, `pointHoverRadius: 4` |
| Script load order | [`amiga/player/profile.php`](../site/public_html/amiga/player/profile.php) | `chart.umd` → adapter → `chart-theme.js` → `chart-date-range.js` → `player-rank-history.js` → `player-rank-chart.js` (defer) |
| Copy minimalism | Policy R16 | Tooltips only; empty band = empty chart (axes, no status); pre-debut / no history use status line |

**Reference implementations (copy structure, not logic):**

- PHP shell: `amiga_profile_render_rating_chart()` in [`amiga_profile_blocks.php`](../site/public_html/includes/amiga_profile_blocks.php)
- JS init: [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js)
- Multi-row toolbar: [`player_opponents_h2h_charts.php`](../site/public_html/includes/player_opponents_h2h_charts.php) (compare rating block)

---

## Locked decisions (policy R1–R18)

Do not re-open without user. Full semantics in policy §2–§7.

**Defaults on first load:** Linear scale · **Career** Y window · stepped line · **full X** from community `timelineStart` (not player debut).

**X-axis (locked Jun 2026):** Full Amiga ladder timeline only — `timelineStart` = first tournament day on `amiga_games` → end of today (or TT cutoff). **Not** player career span; Y toolbar **Career** does **not** trim X. No in-chart date zoom — sparse ~600 finalize points / ~25 years; product is happy with full X only.

**Data:** `amiga_player_elo_rank_at_event` — all global finalizes after debut (~489 points Fabio #109 vs ~39 participation snapshots).

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | User alignment — **done** |
| **1** | Read lib + JSON API + history loader JS | curl/JSON + row counts |
| **2** | Profile PHP shell + `theme.css` selector | Panel renders; scripts enqueue — **done** |
| **3** | Chart.js core (linear career + whole community, connected, inverted Y) | Fabio #109 + Darren #84 smoke — **done** |
| **4** | Full controls (Linear/Percentile scales + windows) + empty-band UX | Policy §6 toolbar complete — **done** |
| **5** | Time travel + QA closure + docs | Hero rank = last point at cutoff — **done** |

---

## Slice 1 — API + read library + loader

### Goal

Server returns rank-at-event series; client loader mirrors `player-rating-history.js`.

### Tasks

- [x] Create `site/public_html/includes/amiga_player_rank_history_lib.php`
  - `amiga_player_rank_history_points($con, int $playerId, ?AmigaSnapshotContext $ctx): array`
  - Query `amiga_player_elo_rank_at_event` JOIN `tournaments` for name
  - Order: `event_date ASC`, `event_chrono ASC`, `tournament_id ASC`
  - TT: omit rows `>` cutoff (same tuple order as `amiga_player_elo_rank_at_cutoff`)
  - Per point: `tournamentId`, `eventDate`, `eloRank`, `ladderSize` (count rows for `tournament_id`), `percentile` (policy R8), `tournamentName`
  - `meta`: `careerBestRank`, `careerWorstRank`, `careerBestPercentile`, `careerWorstPercentile`, `ceiling`, `cutoffActive`
  - `timelineStart`: community ladder origin (`amiga_player_rating_timeline_start()` → `MIN(game_date)` on `amiga_games`) — chart X min, **not** first player rank point
- [x] Create `site/public_html/api/player_rank_history.php`
  - `GET realm=amiga&id=` required; `as=` optional (wire from profile TT when present)
  - Reuse Amiga DB bootstrap pattern from `player_rating_history.php`
  - JSON shape per policy §4
- [x] Create `site/public_html/js/player-rank-history.js`
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

- [x] Fabio #109: ~489 points; `meta.careerBestRank=1`, `careerWorstRank` ≈ 135
- [x] Darren #84: points present; worst rank > best rank
- [x] Invalid id → 400; unknown player → `player_not_found` JSON (same family as rating API)
- [x] `percentile` matches `100 * (ladderSize - eloRank + 1) / ladderSize` spot-check

### Files

- `site/public_html/includes/amiga_player_rank_history_lib.php`
- `site/public_html/api/player_rank_history.php`
- `site/public_html/js/player-rank-history.js`

---

## Slice 2 — Profile shell + CSS registration

### Goal

Empty chart panel on profile with correct chrome and script tags.

### Tasks

- [x] `amiga_profile_render_rank_chart(int $playerId)` in [`amiga_profile_blocks.php`](../site/public_html/includes/amiga_profile_blocks.php)
  - Mirror rating block: section wrapper, `.player-rank-chart.k2-chart-panel`, `data-player-id`, `data-realm="amiga"`
  - `h3.k2-panel-heading` — **Elo rank**
  - `p.k2-chart-block__hint` — end-of-day rank after each tournament day
  - `.pm3d-chart-toolbar` placeholder rows for toggles (slice 4 fills behaviour)
  - Status + `.k2-chart-frame` + canvas `aria-label="Elo rank over time"`
- [x] Call from [`amiga/player/profile.php`](../site/public_html/amiga/player/profile.php) below rating chart
- [x] Enqueue scripts (after existing chart stack): `player-rank-history.js`, `player-rank-chart.js` with `filemtime` cache-bust
- [x] Add `body.k2-site .player-rank-chart` to panel selector list in [`theme.css`](../site/public_html/stylesheets/theme.css)
- [x] Optional: `.player-feast-body .k2-chart-frame` rules already apply if class structure matches rating chart

### Verification

- [x] Profile loads without JS errors when chart init is stub/no-op
- [x] Panel shows 960px max width, bordered surface, 271px frame
- [x] View source: script order correct

---

## Slice 3 — Chart core (linear + date X)

### Goal

One working chart: **Linear · Career · Stepped** (policy default).

### Tasks

- [x] Create `site/public_html/js/player-rank-chart.js`
  - Init on `.player-rank-chart` roots (same pattern as rating chart `initRoot`)
  - Load via `K2PlayerRankHistory`
  - Build `{ x: Date, y: rank | null, raw: {...} }` per point
  - X: time scale; **`timelineStart` month start → end of today** (`K2ChartDateRange.careerTimeRangeFromStart`) — community origin, not player debut; TT caps `xMax` at last point
  - Y: **inverted** linear rank — Career window (policy R13 padding)
  - Dataset: `T.lineStroke(T.amber(), 0.15)`, connected, `spanGaps: false`
  - Tooltip: date, tournament name, `#rank of N`, percentile one decimal
  - Empty series: status text (pre-debut / no data)
  - Use `withCareerPlotGutter` / `careerYScale` equivalents from rating chart

### Verification (browser)

- [x] Fabio #109 — Career: visible climb; not a flat hairline at top for whole career
- [x] Darren #84 — Career: drift ~57 → ~300 readable
- [x] Tooltip shows event-local N (e.g. `#135 of 177` early Fabio)
- [x] Resize window — canvas not stretched/blurry (frame contract)

---

## Slice 4 — Controls (scales, windows)

### Goal

Full policy §6 toolbar (Linear · Percentile; stepped line only).

### Tasks

- [x] **Scale toggle:** Linear · Percentile (`data-scale`) — log **removed** Jun 2026
- [x] **Window toggle** (contextual via `data-range-mode`):
  - Linear: Career · Top 20 · Top 50 · Top 100 · Full ladder
  - Percentile: Career · 95–100 · 90–100 · 80–100 · 50–100 · Full ladder
- [x] **Line:** stepped only (connected toggle removed Jun 2026)
- [x] Rebuild chart on any control change; preserve `careerChartYAxisOptions`
- [x] **Band clip (linear Top K):** edge clip on enter/exit only; **empty chart** (axes only) when never in band — no status copy
- [x] **Edge clip:** out-of-window stepped segments clip at band edge (transition-only; `null` gaps while out of window — no flat edge run)
- [x] **Percentile:** y = precomputed percentile; axis per preset; Career window from `meta.careerBestPercentile` / `careerWorstPercentile`
- [x] Hide invalid window groups when scale changes (`data-range-mode` CSS)
- [x] Y-axis tick colour: deep-merge `ticks` in `careerYScale()` so custom callbacks keep `T.tickColor()`

### Verification

| Player | Check |
|--------|-------|
| Fabio #109 | Full ladder shows early rank; Top 20 band from first ≤20; percentile Career shows personal % span |
| Darren #84 | Top 20 → **empty chart** (axes, no status); Career + Full ladder OK; Percentile Full stable ~36% recent |
| Never top 20 | Pick from QA list in policy §8 — empty chart, not crash |

---

## Slice 5 — Time travel + closure

### Goal

TT parity with hero; docs updated.

### Tasks

- [x] API + loader: honour profile `?as=` (pass from PHP `data-as` or URL on fetch)
- [x] Truncated series; recompute `meta.ceiling` / career min/max on client or trust server meta
- [x] Pre-debut cutoff: empty chart + muted status (no `#0`)
- [x] Spot-check: last chart point `eloRank` = hero rank at same cutoff
- [x] **UPDATE_DOCS** Part A: MEMORY, policy status → implemented, `amiga-profile-v0.md`, `feature-log.md`

### Verification

- [x] `/amiga/player/profile.php?id=237&as=year:2003` — rank chart truncated; hero `#N` matches last point
- [x] Present mode: last point rank = `amiga_player_current.elo_rank`

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
| `stylesheets/player-feast-sections.css` | 2, 4 |
| `js/player-rank-chart.js` | 3–4 |

Optional later: `scripts/oneoff/amiga_rank_history_probe.php` for CLI JSON smoke.

---

## Acceptance fixtures (manual QA)

From policy §8 — run after slice 4–5.

| ID | Name | Role |
|----|------|------|
| 109 | Fabio F | Elite arc; band clip; percentile |
| 84 | Darren G | Mid-table drift; Top 20 empty |
| TBD | never ≤ top 100 | Empty-band chart (axes only) |

---

## Post-ship tweak session (Jun 2026)

After slices 1–5 landed, a polish pass aligned toolbar, clip semantics, and docs:

- **Toolbar:** scale order Linear · Percentile · ~~Log~~ (log dropped); contextual window rows via `data-range-mode`; Career default on both scales; linear band order Career → Top 20/50/100 → Full ladder; percentile presets + Career (personal % span from API meta).
- **Line:** stepped only — connected toggle removed from markup + JS.
- **Clip:** transition-only edge anchors; `null` gaps while out of window (fixes misleading horizontal runs along plot edge).
- **Empty band:** render axes/grid with no line — **no** “Not in top N…” status text.
- **Y-axis:** tick colour preserved when scale callbacks override defaults (`careerYScale` deep-merge).
- **API meta:** `careerBestPercentile` / `careerWorstPercentile` for percentile Career window.
- **CSS:** `player-feast-sections.css` window visibility rules; profile cache-bust via `filemtime`.
- **Related:** `player-rating-chart.js` `readInitialView()` respects markup — Amiga profile opens **By date**.
- **X-axis locked:** full community timeline from first Amiga tournament (`timelineStart`) — not player career; Y **Career** ≠ X trim; no in-chart date zoom — see policy §5.1 / R3.
- **Peak text summary (solo):** `Peak: #N` / `Peak: P%` + date under toolbar — **no** dashed canvas peak line (R18).

---

## H2H compare slices (post-solo)

**Policy:** [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md)  
**Surface:** `/amiga/player/opponents/h2h.php` — after **Rating comparison** in `player_opponents_h2h_charts.php`.

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **6a** | Shared rank core refactor (`player-rank-chart-core.js`) | Solo chart still passes smoke — **done** |
| **6b** | `player_compare_rank_history.php` + lib helper | curl JSON dual payload + `timelineStart` — **done** |
| **6c** | H2H markup + script enqueue | Panel renders; waiting state — **done** |
| **6d** | `player-compare-rank-chart.js` — dual stepped lines, union Career default, band union, **dual peak text lines** | Fabio #109 vs Darren #84 smoke — **done** |
| **6e** | TT + QA + opponents wing doc closure | `?as=` on fetch; dual peak text; legend chrome/red — **done** |

### Slice 6b — compare API

- [x] `GET /api/player_compare_rank_history.php?realm=amiga&id=&opponent=` (+ `as=`)
- [x] Reuse `amiga_player_rank_history_payload()` per side; shared `timelineStart`
- [x] Errors mirror compare-rating API

### Slice 6d — chart behaviour (locked)

- [x] Default: Linear · **Career** Y = **union** of both players (H2H policy §3.1)
- [x] X: full community timeline (solo R3) — not player career span
- [x] Two stepped series: `T.h2hSubject*` / `T.h2hOpponent*`; legend on
- [x] Toolbar: same scale/window controls as solo; Top K empty only when **neither** player in band
- [x] **Dual peak text** — two `Peak:` summary lines (solo §5.9 format per player; chrome + red peak ink); updates on scale toggle; **no** dashed peak line on canvas
- [x] Hint: “End-of-day rank after each tournament day.” · Heading: “Rank comparison vs {opponent}”
- [x] Load on `kool-opponent-selected`; `K2PlayerOpponentsH2hContext` for `realm` + `as=`

### H2H file checklist

| File | Slice |
|------|-------|
| `docs/amiga-player-rank-chart-h2h-policy.md` | policy |
| `js/player-rank-chart-core.js` | 6a |
| `api/player_compare_rank_history.php` | 6b |
| `includes/amiga_player_rank_history_lib.php` (compare payload) | 6b |
| `includes/player_opponents_h2h_charts.php` (markup) | 6c |
| `includes/amiga_player_opponents_page.php` (scripts) | 6c |
| `js/player-compare-rank-chart.js` | 6d |
| `js/player-rank-chart.js` (uses core) | 6a, 3–4 |

---

## Related docs

| Doc | When |
|-----|------|
| [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md) | H2H compare rules |
| [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md) | Solo product rules |
| [`amiga-profile-v0.md`](amiga-profile-v0.md) | Profile surface register |
| [`design-direction.md`](design-direction.md) | Chart palette |
| [`player-profile-feast.md`](player-profile-feast.md) | Surface rhythm |
| [`activity-charts.md`](activity-charts.md) | Frame contract |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Cutoff behaviour |