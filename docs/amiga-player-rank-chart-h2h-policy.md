# Amiga player rank chart — H2H compare policy

**Status:** **Policy locked** (Jun 2026) — **implemented** local Jun 2026.  
**Surface:** `/amiga/player/opponents/h2h.php` — dual-line rank comparison when an opponent is selected (same chart stack region as **Rating comparison**).

**Solo chart (shared rules):** [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md) — data source, X-axis, scale types, stepped clip, percentile formula, time travel, empty-band UX.

**Implementation plan:** [`amiga-player-rank-chart-implementation-plan.md`](amiga-player-rank-chart-implementation-plan.md) § H2H slices.

**Parent wing:** [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md)

**Migration:** **L0** — read `amiga_player_elo_rank_at_event` only; **no Part B**.

---

## 1. Executive summary

Port the **profile solo rank chart** to Opponents **Head-to-head** as a **two-player** stepped line chart on a **shared date X-axis** and **shared Y-axis**.

| Dimension | Solo profile | H2H compare |
|-----------|--------------|-------------|
| Lines | One (amber) | Two — subject **chrome**, opponent **red** (`h2hSubject*` / `h2hOpponent*` — same as rating compare) |
| X-axis | Full community timeline (§solo 5.1) | **Same** — `timelineStart` → today; not union/intersection of active dates |
| Y **Career** default | Personal best→worst + pad | **Union** of both players' spans + pad |
| Peak summary | One text line (§solo 5.9) | **Two** text lines — one per player (§2 H6) |
| X view modes | Date only | Date only — **no** “By tournament #” (rank is finalize-step; unlike rating compare) |
| Legend | Hidden | **Shown** (two datasets) |

**Product stance:** Rating compare remains the primary **skill** crossover story on H2H. Rank compare shows **how both players' ladder standing evolved** on the same calendar — useful when ratings diverge from rank (ladder size, inactive tail).

---

## 2. Locked product decisions (H2H)

| # | Decision | Rule |
|---|----------|------|
| **H1** | **Placement** | In `player_opponents_render_h2h_matchup_charts()` — **after** **Rating comparison**, before goals histograms (when opponent selected) |
| **H2** | **Opponent gate** | Chart panel renders with stack; data loads on opponent pick (`kool-opponent-selected` + `K2PlayerOpponentsH2hContext` — same as other H2H charts). Status: “Waiting for opponent…” until paired |
| **H3** | **API** | `GET /api/player_compare_rank_history.php?realm=amiga&id={hero}&opponent={rival}` (+ optional `as=`). Dual payload mirroring `player_compare_rating_history.php` shape: `player`, `opponent`, shared `timelineStart`, per-player `points` + `meta` |
| **H4** | **X-axis** | Inherit solo **R3** / §5.1 — full Amiga timeline; each line starts at that player's first finalize; empty calendar before debut is OK |
| **H5** | **Default on load** | Linear scale · **Career** Y window — where **Career = union** of both players' career bands (§3.1), not hero-only solo semantics |
| **H6** | **Peak summary (text)** | **Two** lines under toolbar — one per player, same format as solo §5.9 (`Peak: #N …` / `Peak: P% …` + date + `, after {tournament}`; first attainment on ties). Subject line uses chrome peak ink; opponent uses red ink (match line colours). **No** dashed peak line on the chart canvas |
| **H7** | **Toolbar** | Same scale + window controls as solo (Linear · Percentile; contextual window rows via `data-range-mode`) |
| **H8** | **Line style** | Stepped only; same transition edge-clip as solo (§solo R6) — applied **per series** |
| **H9** | **Ceiling** | `max(ladderSize)` over **both** truncated series (union of displayed points) |
| **H10** | **Time travel** | Both series truncated at cutoff; pre-debut player → same empty/status habit as solo |
| **H11** | **Realm** | Amiga only — **online H2H rank compare not planned** |
| **H12** | **Copy** | Heading: **Rank comparison vs {opponent}** · Hint: **End-of-day rank after each tournament day.** (same lede as profile) · Tooltips: player name prefix + `#rank of N (P%)` + date/tournament |

---

## 3. Y-axis — union semantics

Solo **Career** uses one player's `careerBestRank` / `careerWorstRank` (or percentile meta). H2H **Career** uses the **union** so both lines are readable on first paint without manual window changes.

### 3.1 Career window (default)

**Linear:**

```
best  = min(player.bestRank, opponent.bestRank)
worst = max(player.worstRank, opponent.worstRank)
pad   = same habit as solo R12 on (worst − best)
y_min = max(1, best − pad)
y_max = min(ceiling, worst + pad)
```

**Percentile:**

```
best  = max(player.bestPct, opponent.bestPct)
worst = min(player.worstPct, opponent.worstPct)
pad   = same % padding habit as solo
y_min = max(0, worst − pad)
y_max = min(100, best + pad)
```

Toolbar label stays **Career** — docs + implementation comments must clarify **union in H2H context**.

### 3.2 Full ladder

Same as solo: `y_min = 1`, `y_max = ceiling` (H9).

### 3.3 Top K bands (linear)

| Rule | Behaviour |
|------|-----------|
| **Domain** | `1 … K` (inverted), same as solo |
| **Clip** | Per-series edge clip (§solo R6) |
| **Empty band** | **Neither** player ever ≤ K → empty chart (axes only, no status copy) |
| **Partial** | One player never ≤ K — show chart; that series clips/stays out of band; other series plots normally |

### 3.4 Percentile presets

Clamp axis to preset range. Clip each series at preset bounds. **Empty band:** neither player's percentile ever enters preset → empty chart (axes only).

---

## 4. API sketch

**Endpoint:** `GET /api/player_compare_rank_history.php`

| Param | Required | Notes |
|-------|----------|-------|
| `realm` | yes | `amiga` |
| `id` | yes | Hero / profile player |
| `opponent` | yes | Rival player id |
| `as` | no | Time-travel cutoff (same family as solo rank API) |

**Response (conceptual):**

```json
{
  "realm": "amiga",
  "playerId": 109,
  "opponentId": 84,
  "player": { "playerName": "…", "points": [ … ], "meta": { … } },
  "opponent": { "playerName": "…", "points": [ … ], "meta": { … } },
  "timelineStart": "2001-11-03"
}
```

Each side reuses solo point shape + meta from `amiga_player_rank_history_payload()`. Shared `timelineStart` from `amiga_player_rating_timeline_start()`.

Errors: `invalid_id`, `same_player`, `player_not_found` — same family as compare-rating API.

---

## 5. UI / chrome

| Element | Rule |
|---------|------|
| Panel class | `.player-compare-rank-chart.k2-chart-panel` (parallel naming to `.player-compare-rating-chart`) |
| Scripts | Share rank domain/series core with `player-rank-chart.js`; thin `player-compare-rank-chart.js` for dual fetch + render |
| Enqueue | `amiga_player_opponents_page.php` — after rank history loader + solo chart core |
| Chart.js legend | Display (two lines) |
| Peak summary | Two `.pm3d-chart__summary` lines — subject + opponent; text only (H6); no canvas overlay |
| Toolbar meta | Optional later: finalize counts per player (rating compare shows event counts) — **not** v1 blocker |

---

## 6. Acceptance fixtures

Run on `/amiga/player/opponents/h2h.php?id=109` with opponent picked.

| Pair | Check |
|------|-------|
| Fabio #109 vs Darren #84 | Default **Career** union shows both lines readable; chrome vs red; full X from ~2001 |
| Fabio vs low-ranked rival | Union Y wider than either solo Career alone would be |
| Top 20 band | Shows when **either** ever ≤20; empty only when **neither** |
| `?as=year:2003` | Both series truncated; `as=` on fetch via H2H context |
| Dual peak text | Fabio + Darren — two `Peak:` lines; scale toggle updates both; no canvas peak overlay |

---

## 7. Related deferred work

| Item | Notes |
|------|-------|
| Online H2H rank compare | **Not planned** (Amiga shipped Jun 2026) |
| Toolbar finalize-count meta | Nice-to-have |
