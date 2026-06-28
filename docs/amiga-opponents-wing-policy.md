# Amiga Opponents wing — policy

**Status:** **In progress** (Jun 2026) — **SCH-031** goal extremes shipped; **Opponents IA shell** shipped; **W/D/L · Goals · DDs tables** wired with time travel (`amiga_matchup_snapshot_lib.php`). **H2H slices D+F shipped** — poster/pickers/pair detail/moments/charts on `amiga/player/opponents/h2h.php` (event-step rating compare; chart APIs `?realm=amiga`). **SCH-044** stored pair performance rating shipped — W/D/L **Perf.** column + H2H pair detail read stored value.  
**No implementation plan yet** — work incrementally; add a plan or slice handoff only when a concrete slice is about to ship.

**Parent:** [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Online reference (shipped):** [`player-opponents-hub.md`](player-opponents-hub.md) · [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md)

**Related:** [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`url-routes.md`](url-routes.md)

---

## 1. Executive summary

Port the **online Opponents wing** — inner tabs **Head-to-head · W/D/L · Goals · DDs** — into the Amiga realm under **`amiga/player/opponents/*`**, with **time travel wired from the start** wherever stored snapshot truth allows.

Amiga already has cumulative pair data at finalize (`amiga_player_matchup_summary` + `amiga_player_matchup_at_event`). The UI and read libs do not. Legacy shortcuts (`/amiga/h2h.php`, profile top-opponents table) were **removed Jun 2026** so the proper wing can land cleanly.

**How we work:** small slices, careful analysis before each slice, browser/CLI proof before the next step. **Not** a big upfront implementation plan — expect tweaks, fixes, and taste calls between slices.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **O1** | **Rhyme online IA** | Top player pill **Opponents**; inner sub-tabs **Head-to-head · W/D/L · Goals · DDs** (default: Head-to-head). See online [`player-opponents-hub.md`](player-opponents-hub.md). |
| **O2** | **Path under `player/`** | Canonical URLs: `amiga/player/opponents/h2h.php`, `wdl.php`, `goals.php`, `dds.php` — not loose files under `amiga/`. Register in `k2_amiga_routes.php`. |
| **O3** | **Keep Tournaments top-level** | Amiga keeps **Profile · Opponents · Tournaments · Games**. Tournaments stays realm-specific. |
| **O4** | **Time travel by default** | New Opponents surfaces **opt into** `as=` from slice 1 where data allows. Reuse `AmigaSnapshotContext` + link propagation — do not invent per-page history toggles. |
| **O5** | **Present = summary, cutoff = at-event** | Present reads: `amiga_player_matchup_summary`. Time travel: latest `amiga_player_matchup_at_event` row per `(player_id, opponent_id)` on or before cutoff — same chrono tuple as LB snapshots. |
| **O6** | **Directed H2H** | Hero-centric directed row (subject → opponent), matching online — not the old dual-block `/amiga/h2h.php` layout. |
| **O7** | **Incremental depth** | Ship tables and basic H2H before charts/moments. Do not block the wing on full online H2H chart parity. |
| **O8** | **No match streaks** | Amiga streak columns are non-authoritative — do not port streak banners or streak-based opponent UI. |
| **O9** | **Reuse online shell where sane** | Prefer adapting `player_opponents_*` patterns (nav, tables, poster CSS) over a parallel Amiga-only design system. |
| **O10** | **Analyze before every slice** | Each new chat/slice **starts with a short situation read** (this doc + relevant policy + current code). Snapshot quirks and unwired neighbours must be explicit before coding. |

---

## 3. Hygiene already done (Jun 2026)

| Removed | Why |
|---------|-----|
| `/amiga/h2h.php` | Legacy slob; wrong folder; minimal UI |
| Profile **Top opponents** table | Belongs on Opponents wing, not profile scroll |
| `amiga_player_h2h_href()` | Pointed at deleted page |

**Kept:** `includes/amiga_player_matchup_lib.php` — data-layer helpers for the wing (`amiga_player_top_opponents`, `amiga_player_matchup_directed_row`, …).

---

## 4. Data audit — what we have

### 4.1 Snapshot-ready (storage exists; read lib needed)

| Need | Present source | Time-travel source | Online analogue |
|------|----------------|-------------------|-----------------|
| W/D/L table | `matchup_summary` | `matchup_at_event` | `player_matchup_summary` |
| Goals core (GF, GA, averages, ratio, TG/g) | same | same | same |
| DDs counts | `dd_wins`, `dd_losses`, `cs_wins`, `cs_losses` | same | `double_digits`, `clean_sheets`, … |
| Top opponents list | summary | at-event | profile bar / picker ordering |
| H2H poster W/D/L | directed summary row | directed at-event row | poster centre record |
| Hero rank + rating at cutoff | `amiga_player_current.elo_rank` | `amiga_player_elo_rank_at_event` | `playertable` + LB rank pattern |
| **Pair performance rating** (SCH-044) | `matchup_summary.performance_rating` | `matchup_at_event.performance_rating` | online H2H perf (read-time) |

**Read pattern (locked):** mirror `amiga_lb_snapshot_from_sql()` — `ROW_NUMBER() OVER (PARTITION BY player_id, opponent_id ORDER BY event_date DESC, event_chrono DESC, as_of_tournament_id DESC)` with cutoff tuple `<=` resolved cutoff. **Do not** use `MAX(as_of_tournament_id)` alone (catalog ids are not chrono-monotonic).

### 4.2 Goal extremes (SCH-031 — shipped Jun 2026)

Per-pair **goal extremes** on `amiga_player_matchup_summary` and `amiga_player_matchup_at_event` (online SCH-019 parity):

`max_goals_for`, `max_goals_against`, `min_goals_for`, `min_goals_against`, `max_win_margin`, `max_loss_margin`, `max_draw_goals`, `max_goal_sum`, `min_goal_sum`

Written at **tournament finalize** (incremental `MatchupCumulative`) and verified by `verify_player_matchups` extremes oracle. DDL: `scripts/amiga/sql/derived/031_matchup_summary_opponents_ext.sql`.

### 4.3 Game-level depth (not snapshot tables)

| Feature | Source at cutoff | Notes |
|---------|------------------|-------|
| H2H **moments** grid | `amiga_games` + tournament chrono filter | Correct if filtered; not one snapshot row |
| Cumulative H2H / goals charts | filtered `amiga_games` | Online APIs are `realm=online` only today |
| Scoreline heatmap, histograms | filtered pair games | Same |
| Pair **performance rating** | `matchup_summary` / `matchup_at_event` `.performance_rating` | **Stored** (SCH-044) — written at finalize, read at cutoff; no read-time solve |
| **Rating comparison** chart | event-step (`amiga_rating_events` / snapshots) | **Shipped** Jun 2026 |
| **Rank comparison** chart | `amiga_player_elo_rank_at_event` (dual series) | **Shipped** Jun 2026 — [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md) |

### 4.4 Amiga APIs to build (none exist for H2H depth)

Online-only today: `player_head_to_head.php`, `player_compare_rating_history.php`, `player_h2h_opponent_search.php` (Amiga branch), histogram/heatmap APIs. Plan Amiga equivalents or shared `realm=amiga` branches when a chart slice ships.

---

## 5. Time travel — traps to re-check every slice

Read [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) before wiring any surface.

| Trap | Rule |
|------|------|
| **Unwired neighbour shows present** | Profile blocks, H2H drill-down charts may still be present-only until wired — target **T15** (uniform lens); never mix silently on the same wired surface. |
| **Opponent search ordering** | “Played opponents first” must use matchup rows **at cutoff**, not present summary. |
| **Sparse at-event writes** | Rows written when player **participates** in an event; each row holds **cumulative** totals through that event for **all** opponents ever faced. Latest row ≤ cutoff is correct. |
| **Column naming** | Amiga `dd_wins` = online `double_digits`; map at UI boundary. |
| **Link carry** | Opponents URLs append `as=` via `amiga_url_with_context()` when time travel active. |
| **Hero stats in H2H poster** | Rating/rank at cutoff via `amiga_player_load()` + `amiga_elo_rank_lib.php` — same as hero panel |
| **Games drill-down** | H2H heatmap/histogram → **hero → games** (`amiga/player/games.php?id=` + optional opponent filter); list ≤ cutoff (**shipped** Jun 2026) |

---

## 6. How to work (slice discipline)

This track is **policy + incremental slices**, not a monolithic plan.

### Before coding any slice

1. Read this doc + [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) §6 + [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §4.3.  
2. State **which surfaces** the slice wires and **present vs at-event** source for each.  
3. List **unwired neighbours** (games tab, profile, APIs) and how `as=` behaves on this slice.  
4. Confirm **no new DDL** unless the slice explicitly adds extremes or other stored fields.  
5. Define **proof**: browser spot-check + `python -m scripts.amiga prove` if writers touched.

### Slice sizing

- One slice = one shippable increment (e.g. read lib only, or W/D/L tab only, or H2H poster without charts).  
- Stop after proof; update this doc’s **Session log** and `PROJECT_MEMORY.md`.  
- Add a formal `amiga-opponents-wing-implementation-plan.md` only if Dagh asks — optional, not required to start.

### Suggested rough order (not locked — reorder after analysis)

```text
A. ~~amiga_matchup_snapshot_lib.php~~ **Done Jun 2026**
B. ~~Opponents pill + shell + W/D/L tab~~ **Done Jun 2026** (+ Goals + DDs same slice)
C. ~~DDs tab + Goals tab core columns~~ **Done Jun 2026**
D. ~~H2H tab: picker + poster + pair detail (stored; hero from snapshot)~~ **Done Jun 2026**
E. ~~Goals extremes — DDL + finalize~~ **Done (SCH-031 Jun 2026)**
F. ~~H2H moments + charts (game-filtered; Amiga API branches)~~ **Done Jun 2026**
G. ~~Hero → games cutoff + hero games tab filter parity under active `as=`~~ **Done Jun 2026** (+ player tournaments list ≤ cutoff)
H. ~~**H2H rank comparison chart**~~ **Done Jun 2026** — union Career default; dual peak text; [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md)
```

Reorder or split if analysis shows a smaller safe step.

---

## 7. Out of scope (for now)

| Topic | Notes |
|-------|--------|
| Online Opponents changes | Amiga port only; online is reference |
| Profile top-opponents bar chart | Online keeps on profile; Amiga may add later on Opponents H2H or omit |
| Match streaks | Amiga policy excludes |
| Cross-realm H2H | Not this track |
| Big-bang “full parity” before any UI ships | Rejected — incremental registry |

---

## 8. Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Revive `/amiga/h2h.php` | Wrong IA; removed Jun 2026 |
| Profile inline opponent table | Crowds profile; removed |
| Time travel only on LBs, Opponents present-only | Defeats purpose of port; cutoff must drive tables from slice 1 |
| Live scan of all `amiga_games` for W/D/L tables | Stored summary exists; use it |
| `MAX(tournament_id)` for cutoff reads | Breaks on non-monotonic catalog ids |

---

## 9. Agent cold start

**Dagh says:** “Amiga Opponents slice” or “continue Opponents wing”.

**Agent reads (minimum):** this doc → [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) → [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) → online [`player-opponents-hub.md`](player-opponents-hub.md) for UI rhyme.

**Then:** analyze current code (`amiga_player_matchup_lib.php`, `amiga_lb_snapshot_lib.php`, `player_opponents_*`) and state slice scope + snapshot traps **before** editing.

**Finish:** [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A; Part B only if slice adds DDL or finalize writers.

---

## 10. Session log

| Date | Note |
|------|------|
| Jun 2026 | **H2H slice D** — `amiga_player_opponents_h2h.php` + `amiga_player_matchup_directed_opponent_row()`; poster/pickers/pair races/all-games link; time travel via stored at-event rows; search API `realm=amiga`. |
| Jun 2026 | **Opponents Games column links** — W/D/L · Goals · DDs `Games` counts link to hero games tab filtered by opponent (`as=` preserved). |
| Jun 2026 | **Hero games + tournaments cutoff** — `amiga_snapshot_*_cutoff_and_sql()`; games tab + perf API + player tournament list ≤ cutoff under `as=`. |
| Jun 2026 | **Pair performance rating stored (SCH-044)** — `performance_rating` on `matchup_summary` + `matchup_at_event`; finalize recomputes touched pairs (replay in-memory samples / warm reseed from `amiga_game_ratings`). Surfaced as W/D/L **Perf.** column; H2H pair detail reads stored value (no on-the-fly solve). |
| Jun 2026 | **Opponents tables** — `amiga_matchup_snapshot_lib.php` + W/D/L · Goals · DDs wings (stored + `as=`); H2H still placeholder. |
| Jun 2026 | **Opponents IA shell** — pill + `amiga/player/opponents/{h2h,wdl,goals,dds}.php` + inner chrome; placeholder bodies. |
| Jun 2026 | **SCH-031 goal extremes** — `max_goals_*` / margins / goal sums on `matchup_summary` + `matchup_at_event`; Python + PHP finalize; `verify_player_matchups` extremes oracle; replay green. |
| Jun 2026 | Mini-audit (data + time-travel readiness); hygiene: deleted `h2h.php` + profile top opponents; this policy doc created. |
