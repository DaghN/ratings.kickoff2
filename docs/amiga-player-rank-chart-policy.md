# Amiga player rank chart — policy (profile solo)

**Status:** **Policy locked** (Jun 2026). Implementation not started.  
**Purpose:** Career **Elo rank over time** on Amiga player profile — one player, event-step timeline, rich scale/window controls. Complements the existing **rating** chart (skill signal); rank = position among the full historical ladder.

**Non-goals (v1):** H2H rank compare · online realm · X-axis date-range zoom · smart default-picker algorithm · milestone annotations · explanatory copy blocks · percentile range slider (presets only).

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

- **X:** calendar **date** (one point per global finalize after player ladder debut)
- **Y:** user picks **scale type** (linear rank · log rank · percentile) and **window** (scale-dependent)
- **Line:** connected (default) or stepped (toggle)
- **Time travel:** truncate series at `?as=` cutoff (same habit as hero rank)

H2H rank overlay deferred.

---

## 2. Locked product decisions (v1)

| # | Decision | Rule |
|---|----------|------|
| **R1** | **Data source** | `amiga_player_elo_rank_at_event` only — **not** participation-only `amiga_player_event_snapshots` rows |
| **R2** | **Point density** | One point per **global finalize** after the player first appears on the ladder (`NumberGames > 0`) |
| **R3** | **X-axis** | **By date** only in v1 (no “by ladder step” toggle yet) |
| **R4** | **Scale types** | **Linear rank** · **Log rank** · **Percentile** — peer primary control |
| **R5** | **Linear Y windows** | Top 20 · Top 50 · Top 100 · **Career** (personal range) · **Whole community** |
| **R6** | **Band clip (linear)** | Outside selected band → **no line** (`null` y, `spanGaps: false`); line **starts** at first in-band point |
| **R7** | **Log Y domain** | **Whole-community ceiling** only in v1 (`1 … max ladder_size` over displayed series) |
| **R8** | **Percentile formula** | `100 × (N − rank + 1) / N` where `N = ladder_size` at that event (higher = better) |
| **R9** | **Percentile Y window** | Presets only v1: **Full (0–100)** · **50–100** · **90–100** · **95–100**; custom slider = later |
| **R10** | **Line interpolation** | **Connected** default · **Stepped** toggle |
| **R11** | **Y direction** | Rank **#1 at top** (inverted axis on linear/log rank) |
| **R12** | **Ceiling** | `Y_max = max(ladder_size)` over the **displayed** series (supports future date trim) |
| **R13** | **Career window padding** | `y_min = career_best − pad`, `y_max = career_worst + pad`, clamp to `[1, ceiling]`; `pad = max(5, min(20, round(0.05 × span)))` |
| **R14** | **Default on load** | Linear · **Career** window · Connected — no smart algorithm in v1 |
| **R15** | **Realm** | **Amiga only** v1 |
| **R16** | **Time travel** | Points with `(event_date, chrono, tournament_id) > cutoff` omitted; pre-debut → empty chart + status (align hero pre-debut) |
| **R17** | **Copy / annotations** | **Minimal** — tooltips + empty-band status only; no milestone markers or explainer paragraphs in v1 |
| **R18** | **Placement** | Profile page, exact block TBD; mirror `k2-chart-panel` / toolbar patterns from rating chart |

---

## 3. Data contract

### 3.1 Table

**`amiga_player_elo_rank_at_event`** — one row per `(player_id, tournament_id)` for every player with `NumberGames > 0` after each finalize. Written at finalize / `prove` ([`032_elo_rank.sql`](../scripts/amiga/sql/derived/032_elo_rank.sql)).

| Field | Use |
|-------|-----|
| `player_id` | Filter |
| `tournament_id` | Identity + TT ordering |
| `event_date`, `event_chrono` | X-axis + cutoff |
| `elo_rank` | Y (linear / log input) |
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
    "ceiling": 473,
    "cutoffActive": false
  }
}
```

| Field | Rule |
|-------|------|
| `percentile` | Precomputed server-side from §2 R8 (client may recompute for sanity) |
| `meta.ceiling` | `max(ladderSize)` over returned `points` |
| `meta.careerBestRank` / `careerWorstRank` | Min/max `eloRank` over returned `points` |

Errors: same family as `player_rating_history.php` (`invalid_id`, `player_not_found`, empty `points` when pre-debut at cutoff).

---

## 5. Chart semantics

### 5.1 X-axis — by date

- Time scale at finalize `event_date` (tie-break `event_chrono`, `tournament_id` for ordering — not for X position).
- Calendar gaps are real (no participation ≠ flat rank — rank points still exist on those dates).

**Deferred:** “By ladder step” index · draggable date-range trim.

### 5.2 Scale type × Y window matrix

| Scale | Y window options (v1) | Domain |
|-------|----------------------|--------|
| **Linear rank** | Top 20 · Top 50 · Top 100 · Career · Whole community | See §5.3–§5.4 |
| **Log rank** | *(none — implicit whole community)* | `log(1) … log(ceiling)`; display tick labels as rank integers at powers / nice steps |
| **Percentile** | Full · 50–100 · 90–100 · 95–100 | Linear 0–100 (or selected preset sub-range); **#1 at top** = high percentile at top |

Changing scale **resets or maps** the window control to valid options for that scale (no “Top 20” under percentile).

### 5.3 Linear — whole community

- `y_min = 1`, `y_max = ceiling` (§2 R12).
- Tooltip: `#135 of 177` — event-local **N**, not today’s ladder size.

### 5.4 Linear — career (personal range)

- `y_min = career_best − pad`, `y_max = career_worst + pad`, clamped to `[1, ceiling]`.
- Padding §2 R13. **Future refinement (not v1):** if career best is 18, optionally extend band to #1; v1 uses simple min/max + pad only.

### 5.5 Linear — Top K bands (20 / 50 / 100)

- Display domain: `1 … K` (inverted).
- Plot: `null` when `elo_rank > K`; first visible segment starts at first point with `elo_rank ≤ K`.
- **Empty state:** player never ≤ K — status text e.g. `Not in top 20 at any recorded event` (no fake line).

### 5.6 Log rank

- Transform: `y = log(rank)` for rank ≥ 1.
- Domain: **1 … ceiling** only (whole-community max **N** over series) — v1 simplification §2 R7.
- Same point series as linear; only axis transform changes.

### 5.7 Percentile

- `y = 100 × (N − rank + 1) / N`.
- **Full:** 0–100 (or 100–0 if inverted to match #1-at-top — pick one convention in implementation and keep tooltips consistent).
- **Presets:** clamp axis to sub-range; line clipped at preset bounds if needed.

### 5.8 Line style

| Mode | Chart.js / behaviour |
|------|---------------------|
| **Connected** (default) | `stepped: false`; optional light `tension` to match rating chart |
| **Stepped** | `stepped: true` — rank constant between finalize points |

No claim of intra-event rank movement in either mode.

### 5.9 Tooltips (v1)

Required per point:

- Date (+ tournament name when available)
- `#rank of N`
- Percentile (one decimal) when scale is percentile or as secondary line otherwise — **implementation choice:** always show percentile in tooltip for consistency

**Exclude from v1:** milestone callouts, “first top 10” labels, philosophy blurbs.

### 5.10 Summary strip (optional, minimal)

If present: **Best · Current · Worst** rank (integers only). No dates in v1 unless trivial from existing peak pattern on rating chart.

---

## 6. UI controls (toolbar)

**Tier 1 — always visible**

1. Scale: Linear · Log · Percentile  
2. Y window (contextual):  
   - Linear → Top 20 · Top 50 · Top 100 · Career · Whole community  
   - Log → *(hidden or single label “Full ladder”)*  
   - Percentile → Full · 50–100 · 90–100 · 95–100  
3. Line: Connected · Stepped  

**Tier 2 — deferred**

- Smart default picker · X date-range · percentile slider · H2H compare · ladder-step X-axis · career band extended to #1 when best > 20

Mirror segment-control styling from `player-feast-sections.css` / `pm3d-rating-toggle` where practical.

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
| **Fabio F** | 109 | Whole community: early ~#135 of ~177; elite plateau; Top 20 line starts once ≤20; Log spans full ceiling; percentile Full shows ~24% → ~100% |
| **Darren G** | 84 | Career: ~#57–#308 readable; Top 20 empty state; recent flat ~#304; percentile Full ~36% stable recent years |
| **Never top 100** | TBD id | Top 20 / Top 50 empty status, not a crash |

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

---

## 10. Related deferred work

| Item | Notes |
|------|-------|
| H2H rank compare | Separate policy; band union rules; after solo v1 ships |
| Online realm | After Amiga parity proof |
| X-axis date-range zoom | User-controlled trim; ceiling rule already supports it |
| Percentile slider (Option B) | After presets feel good |
| Career Y refinement | Extend band to #1 when career best > K |
| Copy / annotation pass | Deliberate later pass — avoid clutter during dev |
| `feature-log` rank chart API | Mark **Done** when API + UI ship |