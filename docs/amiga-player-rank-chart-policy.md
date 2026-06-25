# Amiga player rank chart — policy (profile solo)

**Status:** **Implemented** (Jun 2026) — slices 1–5 + **post-ship tweak session** (Jun 2026) complete local.  
**Purpose:** Career **Elo rank over time** on Amiga player profile — one player, event-step timeline, rich scale/window controls. Complements the existing **rating** chart (skill signal); rank = position among the full historical ladder.

**Non-goals (v1):** H2H rank compare · online realm · **in-chart X-axis date trim / zoom** (full community timeline only — see §5.1) · smart default-picker algorithm · milestone annotations · explanatory copy blocks · percentile range slider (presets only).

**Authority:** Rank persistence = [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · data contract = [`amiga-data-contract.md`](amiga-data-contract.md) · profile shell = [`amiga-profile-v0.md`](amiga-profile-v0.md) · time travel = [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · rating chart (parallel) = [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md)

**Implementation plan:** [`amiga-player-rank-chart-implementation-plan.md`](amiga-player-rank-chart-implementation-plan.md)

---

## 1. Executive summary

### Ladder philosophy (why rank ≠ rating)

| Rule | Effect on charts |
|------|------------------|
| Fixed **1600** start · no dynamic K | New players clump near 1600; strong regulars cluster **above** ~1800 |
| **Everyone visible** — no hiding inactive / low-game players | Ladder tail is large; absolute rank in the middle can look “low” while rating is stable |
| Rank = sort of full ladder after each finalize | Rank moves when **others** move, even when this player did not play |

**Product stance:** Rating chart remains the primary **skill** story. Rank chart shows **standing in the whole community** over time. Keep UI text minimal in v1 — tooltips carry `#rank of N`; no philosophy paragraphs on the chart chrome until a later copy pass.

### What we are building (v1)

A **solo** rank-over-time line chart on `/amiga/player/profile.php`:

- **X:** calendar **date** — **full community timeline** from the **first tournament in Amiga ladder history** through today (see §5.1). **Not** trimmed to this player’s debut or to the Y **Career** window.
- **Y:** user picks **scale type** (linear rank · percentile) and **window** (**Career** = personal Y band only — rank/percentile padding; **does not** change X)
- **Line:** stepped only (rank constant between finalize points)
- **Time travel:** truncate series at `?as=` cutoff (same habit as hero rank)

H2H rank overlay deferred.

---

## 2. Locked product decisions (v1)

| # | Decision | Rule |
|---|----------|------|
| **R1** | **Data source** | `amiga_player_elo_rank_at_event` only — **not** participation-only `amiga_player_event_snapshots` rows |
| **R2** | **Point density** | One point per **global finalize** after the player first appears on the ladder (`NumberGames > 0`) |
| **R3** | **X-axis** | **By date** only · **full community timeline** — `timelineStart` (first tournament day on `amiga_games`, shared with Amiga rating chart) through end of today. **Not** this player’s career span: a 2010 debut still sees X from ~2001; the line starts at their first finalize. **No** in-chart date trim/zoom (product default locked Jun 2026 — ~600 sparse finalize points over ~25 years). |
| **R4** | **Scale types** | **Linear rank** · **Percentile** — primary control (log rank **dropped** Jun 2026 — redundant with linear + percentile) |
| **R5** | **Linear Y windows** | Career · Top 20 · Top 50 · Top 100 · **Full ladder** (whole community) |
| **R6** | **Band clip (linear / percentile)** | Out-of-window points hidden from tooltips; stepped line **clips at window edge on enter/exit only** (no flat run along edge while out of window) |
| **R7** | **Percentile formula** | `100 × (N − rank + 1) / N` where `N = ladder_size` at that event (higher = better) |
| **R8** | **Percentile Y window** | Presets: **Career** · **95–100** · **90–100** · **80–100** · **50–100** · **Full ladder**; custom slider = later |
| **R9** | **Line interpolation** | **Stepped** only — rank constant between finalize points |
| **R10** | **Y direction** | Rank **#1 at top** on linear; percentile rises upward (higher percentile at top) |
| **R11** | **Ceiling** | `Y_max = max(ladder_size)` over the **displayed** series (supports future date trim) |
| **R12** | **Career window padding** | Rank: `y_min = career_best − pad`, `y_max = career_worst + pad`, clamp to `[1, ceiling]`; percentile career uses same % padding habit; `pad = max(5, min(20, round(0.05 × span)))` on rank span |
| **R13** | **Default on load** | Linear · **Career** window — no smart algorithm in v1 |
| **R14** | **Realm** | **Amiga only** v1 |
| **R15** | **Time travel** | Points with `(event_date, chrono, tournament_id) > cutoff` omitted; pre-debut → empty chart + status (align hero pre-debut) |
| **R16** | **Copy / annotations** | **Minimal** — in-band tooltips only; **no** status text when a band/window has zero in-range points (empty chart with axes); pre-debut / no history still use status line |
| **R17** | **Placement** | Profile page, exact block TBD; mirror `k2-chart-panel` / toolbar patterns from rating chart |

---

## 3. Data contract

### 3.1 Table

**`amiga_player_elo_rank_at_event`** — one row per `(player_id, tournament_id)` for every player with `NumberGames > 0` after each finalize. Written at finalize / `prove` ([`032_elo_rank.sql`](../scripts/amiga/sql/derived/032_elo_rank.sql)).

| Field | Use |
|-------|-----|
| `player_id` | Filter |
| `tournament_id` | Identity + TT ordering |
| `event_date`, `event_chrono` | X-axis + cutoff |
| `elo_rank` | Y (linear rank input) |
| (derived) `ladder_size` | Count of rows for same `tournament_id` in this table — **N** for tooltip and percentile |

**Why not snapshots alone:** participation snapshots omit finalizes where the player did not play; rank still changes. Example (local): Fabio #109 — **39** snapshot rows vs **489** rank-at-event rows.

### 3.2 Series start / end

| Rule | Behaviour |
|------|-----------|
| **Start** | First row for `player_id` in chrono order |
| **End (present)** | Last row ≤ latest finalize |
| **End (time travel)** | Last row ≤ snapshot cutoff (`amiga_player_elo_rank_at_cutoff` ordering) |

### 3.3 Read path

- New API (§4) — **not** raw SQL in templates.
- Reuse cutoff helpers from `amiga_elo_rank_lib.php` / `amiga_snapshot_context.php` for TT parity with hero.

---

## 4. API (v1 sketch)

**Endpoint:** `GET /api/player_rank_history.php?realm=amiga&id={player_id}`

Optional: `as=` passthrough when profile is time-travelled (same param family as other Amiga profile APIs).

**Response (conceptual):**

```json
{
  "realm": "amiga",
  "playerId": 109,
  "playerName": "Fabio F",
  "points": [
    {
      "tournamentId": 140,
      "eventDate": "2004-11-13",
      "eloRank": 135,
      "ladderSize": 177,
      "percentile": 24.3,
      "tournamentName": "…"
    }
  ],
  "meta": {
    "careerBestRank": 1,
    "careerWorstRank": 135,
    "careerBestPercentile": 100.0,
    "careerWorstPercentile": 24.3,
    "ceiling": 473,
    "cutoffActive": false
  },
  "timelineStart": "2001-11-03"
}
```

| Field | Rule |
|-------|------|
| `percentile` | Precomputed server-side from §2 R8 (client may recompute for sanity) |
| `meta.ceiling` | `max(ladderSize)` over returned `points` |
| `meta.careerBestRank` / `careerWorstRank` | Min/max `eloRank` over returned `points` |
| `meta.careerBestPercentile` / `careerWorstPercentile` | Max/min `percentile` over returned `points` (percentile **Career** window) |
| `timelineStart` | `MIN(game_date)` on `amiga_games` — **community** ladder origin (first tournament day in ground data) for chart **X min** (month start); same helper as Amiga rating chart. **Not** this player’s first rank point and **not** the Y **Career** window. |

Errors: same family as `player_rating_history.php` (`invalid_id`, `player_not_found`, empty `points` when pre-debut at cutoff).

---

## 5. Chart semantics

### 5.1 X-axis — full community timeline (by date)

**Product decision (locked Jun 2026):** Ship the rank chart with **one X range only** — the **full** calendar axis from the **first tournament in Amiga ladder history** through today (or time-travel cutoff). **No** in-chart date trim, pan, or zoom. We are **happy with this default**: Amiga rank history is sparse (~one finalize point per player, ~600 global events over ~25 years). The calendar already reads clearly without shrinking X (unlike dense daily-play sites).

**Do not confuse with Y “Career”:** The toolbar **Career** control is a **Y-window** (personal rank or percentile band + padding). It improves legibility of this player’s ups and downs. It **does not** narrow the X-axis. X is always the whole Amiga timeline.

**What sets X min / max**

| Bound | Source |
|-------|--------|
| **X min** | API `timelineStart` = `MIN(game_date)` on **`amiga_games`** — first tournament day in the ground dataset (~Nov 2001); month start via `K2ChartDateRange.careerTimeRangeFromStart()` (helper name is historical — value is **community** origin, not player career) |
| **X max** | End of today (present) or last returned finalize date when time travel is active |

**Line vs axis:** Rank **points** begin at this player’s first global finalize after debut. The **X-axis frame** still spans the full community timeline — empty calendar to the left of their first point is intentional context.

**Points:** One x-position per global finalize after the player appears on the ladder (`event_date`; ordering tie-break `event_chrono`, `tournament_id`). Calendar gaps are real (no participation ≠ flat rank — rank points still exist on those dates).

**Deferred (not v1):** “By ladder step” index · user-controlled X date-range trim (revisit only if a realm gains much denser series, e.g. daily online play).

### 5.2 Scale type × Y window matrix

| Scale | Y window options (v1) | Domain |
|-------|----------------------|--------|
| **Linear rank** | Career · Top 20 · Top 50 · Top 100 · Full ladder | See §5.3–§5.5 |
| **Percentile** | Career · 95–100 · 90–100 · 80–100 · 50–100 · Full ladder | Linear 0–100 (or selected preset / career sub-range); higher percentile at top |

Changing scale **resets or maps** the window control to valid options for that scale (no “Top 20” under percentile).

### 5.3 Linear — whole community

- `y_min = 1`, `y_max = ceiling` (§2 R12).
- Tooltip: `#135 of 177` — event-local **N**, not today’s ladder size.

### 5.4 Linear — career (personal Y range only)

**Y window only** — does not affect X (§5.1).

- `y_min = career_best − pad`, `y_max = career_worst + pad`, clamped to `[1, ceiling]`.
- Padding §2 R13. **Future refinement (not v1):** if career best is 18, optionally extend band to #1; v1 uses simple min/max + pad only.

### 5.5 Linear — Top K bands (20 / 50 / 100)

- Display domain: `1 … K` (inverted).
- Plot: out-of-band points participate in **edge clip** only (§2 R6); first in-band segment starts at first point with `elo_rank ≤ K`.
- **Empty band:** player never ≤ K — render **empty chart** (axes + grid, no line, **no** status copy).

### 5.6 Percentile

- `y = 100 × (N − rank + 1) / N`.
- **Career:** personal percentile span + padding (from `meta.careerBestPercentile` / `careerWorstPercentile`).
- **Presets / Full ladder:** clamp axis to sub-range; same edge-clip habit as linear when out of preset.

### 5.7 Line style

| Mode | Chart.js / behaviour |
|------|---------------------|
| **Stepped** | `stepped: true` — rank constant between finalize points; edge clip on band/window exit (§2 R6) |

No claim of intra-event rank movement.

### 5.8 Tooltips (v1)

Required per point:

- Date (+ tournament name when available)
- `#rank of N`
- Percentile (one decimal) when scale is percentile or as secondary line otherwise — **implementation choice:** always show percentile in tooltip for consistency

**Exclude from v1:** milestone callouts, “first top 10” labels, philosophy blurbs.

### 5.9 Summary strip

**Peak line** (mirrors rating chart): `Peak: #N on MMM d, yyyy.` in **linear** scale; `Peak: P% on …` in **percentile** scale. Best = lowest rank / highest percentile over the loaded series (TT-truncated when active). **Ties:** first chronological attainment (`>` / `<`, not `>=` / `<=`). Updates when scale toggles; independent of Y-window band (Top 20 etc.).

---

## 6. UI controls (toolbar)

**Heading hint:** `k2-chart-block__hint` — “End-of-day rank after each tournament day.” (matches rating chart lede pattern.)

**Tier 1 — always visible**

1. Scale: Linear · Percentile  
2. Y window (contextual — **does not trim X**; full timeline §5.1):  
   - Linear → Career · Top 20 · Top 50 · Top 100 · Full ladder  
   - Percentile → Career · 95–100 · 90–100 · 80–100 · 50–100 · Full ladder  

**Tier 2 — deferred**

- Log rank scale · Connected line toggle · smart default picker · X date-range · percentile slider · H2H compare · ladder-step X-axis · career band extended to #1 when best > 20

Mirror segment-control styling from `player-feast-sections.css` / `pm3d-rating-toggle` where practical. Toolbar uses **`data-range-mode`** on `.player-rank-chart__toolbar` (`linear` | `percentile`) so only the matching window row is visible (CSS — not `hidden` on individual toggles alone).

---

## 7. Time travel

| Case | Chart |
|------|-------|
| Present | Full series through latest finalize |
| `?as=` active | Points strictly ≤ cutoff; `meta.ceiling` recomputed on truncated series |
| Pre-debut at cutoff | Empty `points`; status matches hero “not on ladder yet” tone — no `#0` |

Hero rank source (`amiga_player_elo_rank_at_cutoff`) and last chart point must agree at cutoff.

---

## 8. Acceptance fixtures (manual)

Use local `ko2amiga_db` after `prove` / export.

| Player | ID | Check |
|--------|-----|-------|
| **Fabio F** | 109 | Full ladder: early ~#135 of ~177; elite plateau; Top 20 band once ≤20; percentile Career shows personal % span |
| **Darren G** | 84 | Career: ~#57–#308 readable; Top 20 → **empty chart** (no status text); percentile Career ~36% span |
| **Never top 100** | TBD id | Top 20 / Top 50 → empty chart with axes, not a crash |

Time travel: profile `?as=year:2003` — truncated series; hero rank matches last point.

---

## 9. Implementation pointers (when coding)

| Area | Reference |
|------|-----------|
| Chart shell | `includes/amiga_profile_blocks.php` rating block · `js/player-rating-chart.js` |
| History loader pattern | `js/player-rating-history.js` |
| Rank reads | `includes/amiga_elo_rank_lib.php` |
| TT context | `includes/amiga_snapshot_context.php` |
| Stored truth | No new tables v1 — read `amiga_player_elo_rank_at_event` only |

**Files to add (expected):** `api/player_rank_history.php` · `includes/amiga_player_rank_history_lib.php` · `js/player-rank-history.js` · `js/player-rank-chart.js` · profile block include.

**Slice 1 shipped:** lib + API + `K2PlayerRankHistory` loader (`player-rank-history.js`). **Slices 2–5 shipped** — profile panel + `player-rank-chart.js` + TT parity.

---

## 10. Related deferred work

| Item | Notes |
|------|-------|
| H2H rank compare | Separate policy; band union rules; after solo v1 ships |
| Online realm | After Amiga parity proof |
| X-axis date-range zoom | **Not planned for Amiga v1** — product default is **full community timeline** only (§5.1). Sparse finalize cadence (~600 events / ~25 years); Y **Career** is not an X trim. Revisit only with much denser series (e.g. online daily play). |
| Percentile slider (Option B) | After presets feel good |
| Career Y refinement | Extend band to #1 when career best > K |
| Copy / annotation pass | Deliberate later pass — avoid clutter during dev |
| `feature-log` rank chart API | Mark **Done** when API + UI ship |