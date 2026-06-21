# Player Opponents hub — IA plan (online first)

**Status:** **Shipped Jun 2026** — Opponents top pill + inner sub-tabs (Head-to-head · W/D/L · Goals · DDs); H2H poster, charts, moments, scoreline heatmap. Live spec: [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md). **Profile** keeps career charts; matchup charts on Opponents → H2H.

**Authority:** Dagh’s latest message in chat → this doc → [`player-profile-feast.md`](player-profile-feast.md) (shipped layout) → [`profile-build-playbook.md`](profile-build-playbook.md).

**Related:** [`amiga-profile-v0.md`](amiga-profile-v0.md) (Amiga follow-on) · [`hub-ia-agreement.md`](hub-ia-agreement.md) (site hub, not player wing).

---

## Why

The three pills **W/D/L**, **Goals**, and **DDs** under the player hero are structurally the same thing: **per-opponent tallies**, not aggregate “about this player” stats. They crowd the top nav (six pills today) and prime visitors to think in opponent terms while browsing the main Profile story.

**Goals of this work:**

1. Group W/D/L · Goals · DDs under one **Opponents** top-level pill with inner sub-tabs.
2. Give head-to-head machinery a natural home (4th sub-tab) and **slim Profile** by moving matchup charts off the scroll.
3. Optionally **expand Profile career totals** once opponent noise is delegated — one place for “all the numbers about *this* player” without duplicating opponent tables.

---

## Decisions (locked)

| # | Decision |
|---|----------|
| 1 | Add top-level **Opponents** pill; remove W/D/L, Goals, DDs as siblings. |
| 2 | Inner sub-tabs: **Head-to-head · W/D/L · Goals · DDs** (default: Head-to-head). |
| 3 | **Milestones** stays top-level (achievement / identity, not opponent analysis). |
| 4 | Move Profile **pair matchup charts** (H2H cumulative, rating comparison) to **Opponents → Head-to-head**. |
| 5 | Profile keeps **self-centric** charts: ELO over time, games per month, **most-played opponents bar** (links into H2H). Optional small rivalry teaser (M09 one-liner) — not a full matchup section. |
| 6 | **Do not** mirror full `playertable` on Profile. Expand Career totals in a **curated** way (rows + `#rank` where useful), not a 40-column dump. |
| 7 | **Start online**; Amiga follows the same mental model later. |
| 8 | Legacy URLs not required pre-publish — old `player/wdl.php` etc. removed; canonical `/player/opponents/h2h.php` (Opponents pill default). |

---

## Target navigation

### Online — before (shipped)

```
Profile · Games · W/D/L · Goals · DDs · Milestones
```

### Online — after (target)

```
Profile · Opponents · Milestones · Games
```

**Opponents** inner bar (pattern: league-honours subnav, `k2-chrome-tabs`):

```
W/D/L · Goals · DDs · Head-to-head
```

### Amiga — later (target rhyme)

Today: `Profile · Tournaments · Games` (no Opponents pill yet).

Target: add **Opponents** under `amiga/player/opponents/*` (rhyme online `player/opponents/`). Keep **Tournaments** top-level (realm-specific).

```
Profile · Tournaments · Games · Opponents   (order TBD)
```

---

## Phasing

Work in slices. Dagh steers order; any slice can pause for taste calls.

### Phase 1 — IA only (low risk) — **shipped locally Jun 2026**

- [x] `player_nav.php`: Opponents top pill; remove W/D/L, Goals, DDs siblings.
- [x] `player/opponents.php?id=` + `includes/player_opponents_nav.php` (inner sub-tabs).
- [x] Table bodies in `includes/player_opponents_tables.php` (same `ratedresults` queries as before).
- [x] `k2_routes.php`: `player-opponents` route; removed `player-wdl` / `player-goals` / `player-double-digits`.
- [x] Head-to-head sub-tab: placeholder copy (charts in Phase 2).
- [ ] No data-layer changes (still live scan).

### Phase 2 — Head-to-head tab (in progress)

**Design (locked Jun 2026):** Pair-first — not bar-chart entry. **No sticky picker.** **Player names always → profile** (no table row deep-links to H2H). Three pickers: **search** (global autofill, `games` not rating; opponents-with-history first while typing) · **by games ▾** · **A–Z ▾** (played opponents only). Selection → `/player/opponents/h2h.php?opponent={id}` with **carry-scroll** (keeps `window.scrollY` on reload). **Default headline:** when `opponent` is omitted, show the most-played opponent (URL stays without `opponent=` until user picks another).

- [x] **H2H v1 (Jun 2026):** picker band + pair headline (`includes/player_opponents_h2h.php`, `api/player_h2h_opponent_search.php`, `js/player-opponents-h2h.js`). Games/A–Z use shared **`k2-archive-listbox`** (two-column options: name · N games). No games → text only; no charts yet.
- [x] **H2H poster v1 (Jun 2026)** — versus poster: diagonal arena, `VS` watermark, dual fighter cards (rank · rating · goals scored), centre W/D/L, full-width rivalry tug-of-war bar, leader glow/"Leads" chip. See [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md) § Implementation (as built).
- [x] **H2H pair detail (Jun 2026)** — symmetric stat race table below poster; see [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md) § Pair detail.
- [x] **H2H moments grid (Jun 2026)** — 3×3 pair trophy board below race table; **v2 (Jun 2026)** neutral shells + scoreline-only neon — see [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md) § Pair moments.
- [x] Move Profile **Matchups** charts (top opponents bar, H2H cumulative, rating compare) onto H2H tab below moments.
- [x] Remove `player_feast_render_charts()` **Matchups** section from `player/profile.php`.
- [x] Optional: rivalry one-liner on Profile → `opponents?view=h2h&opponent=…` — **placeholder card** (`player_feast_render_rivalry_teaser`) after Most played opponents; fuller band TBD.
- [x] Optional: compact top-opponent teaser on Profile — same placeholder card (most-played name + H2H link).

### Phase 3 — stored reads — **slice 3 shipped locally Jun 2026**

- [x] **Slice A:** W/D/L + core Goals from `player_matchup_summary`; live fallback if table missing.
- [x] **Slice 1:** SCH-019 contract in [`website-data-contract.md`](website-data-contract.md).
- [x] **Slice 2:** migration 019 + P5 + AB parity + batch rebuild SQL.
- [x] **Slice 3:** work simul proof (game 500) + Goals tail + DDs from stored.
- [ ] **Slice 4:** Steve re-simul on `kooldb1` after sync.

### Amiga — after online proves out

- [x] Opponents pill + inner sub-tabs — see [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) (shell shipped Jun 2026).
- [x] Wire W/D/L · Goals · DDs tables from stored matchup (+ snapshot read lib) — Jun 2026.
- [ ] H2H rivalry wing (poster, picker, charts).

---

## Profile vs Opponents — content split

| Surface | Job | Keep / move |
|---------|-----|-------------|
| Hero | Rank, rating, games, milestones | Keep |
| Presence | Last seen, recency | Keep |
| Career (today) | 5 totals + ranks | Keep; optionally expand in Phase 3 |
| Heatmaps, personal bests, moments | Story / celebrate | Keep |
| ELO rating chart, games/month | Self-centric time series | Keep on Profile |
| Top opponents bar chart | Opponent frequency | **Move** → Opponents → H2H |
| H2H cumulative wins chart | Pair rivalry | **Move** → Opponents → H2H |
| Rating comparison charts | Pair analyst depth | **Move** → Opponents → H2H |
| Opponent search | Rare matchup pick | **Move** → Opponents → H2H |
| W/D/L, Goals, DDs tables | Per-opponent ledgers | **Move** under Opponents sub-tabs |

**Feast narrative:** Profile = curated portrait. Opponents = analyst / rivalry depth. This **sharpens** [`player-profile-feast.md`](player-profile-feast.md) “peek on Profile, depth on tabs” — update that doc when Phase 1 ships.

---

## Technical reference (online, shipped today)

| Item | Location |
|------|----------|
| Top pills | `includes/player_nav.php` — Profile · **Opponents** · Milestones · Games |
| Opponents shell | `player/opponents/{h2h,wdl,goals,dds}.php` + shared `includes/player_opponents_page.php` |
| Inner sub-tabs | `includes/player_opponents_nav.php` |
| Table bodies | `includes/player_opponents_tables.php` + **`includes/player_opponents_load.php`** |
| H2H tab | `includes/player_opponents_h2h.php` · `api/player_h2h_opponent_search.php` · `js/player-opponents-h2h.js` |
| Profile matchup block | `includes/player_feast_blocks.php` → charts still on Profile until Phase 2 charts slice |
| Routes | `includes/k2_routes.php` — `player-opponents` + `player-opponents-{h2h,wdl,goals,dds}` |
| Inner subnav precedent | `includes/league_honours_panel.php` (`k2-lb-league-honours__subnav`) |

| View | URL |
|------|-----|
| Head-to-head | `/player/opponents/h2h.php?id={id}` · optional `&opponent={opponentId}` |
| W/D/L | `/player/opponents/wdl.php?id={id}` |
| Goals | `/player/opponents/goals.php?id={id}` — core GF/GA + **TG/g** (combined goals per game) after Ratio; extremes tail from stored summary when present |
| DDs | `/player/opponents/dds.php?id={id}` |

---

## Career totals expansion (Phase 3 — scope TBD)

**Problem:** No single “all career numbers for this player” view. Hero + Career strip show a thin slice; hub leaderboards show site-wide rankings; `playertable` has the rest.

**Direction:** Expand Zone B Career band — grouped rows (e.g. Scoring · Defence · Victims · Peaks), `(#rank)` where ladder context helps. Link out to relevant hub wings. **Not** opponent tables.

**Candidate fields** (from `playertable` — trim in implementation):

- Draws, losses, goals against, goal ratio
- Clean sheets, DD conceded, CS conceded
- Different victims / culprits (or headline counts)
- Peak rating + peak date (hero has rating; detail can live here)

Dagh taste call before building.

---

## Open questions (resolve in chat / during slices)

| # | Question |
|---|----------|
| Q1 | Phase 1 only first, or Phase 1+2 in one pass? |
| Q2 | Any Profile teaser for #1 opponent after Matchups move, or clean break? |
| Q3 | H2H sub-tab: charts only, or charts + summary stats + “all games” link (Games tab filter M12)? |
| Q4 | Phase 3 Career expansion — which groups in v1? |
| Q5 | Amiga pill order when Opponents lands? |

---

## Session log

| Date | Note |
|------|------|
| Jun 2026 | **Phase 1 shipped locally** — Opponents pill, inner sub-tabs, three tables, H2H placeholder; old `player/wdl|goals|double-digits.php` removed. |
| Jun 2026 | IA agreed in chat; this doc created as recenter reference. |
| Jun 2026 | **H2H v1** — three pickers + pair headline; contextual search API; games/A–Z themed listboxes; charts still on Profile. |
| Jun 2026 | **H2H poster v1 shipped locally** — versus poster replaces the plain `A vs B` headline (diagonal arena, `VS` watermark, per-fighter goals, rivalry tug-of-war bar, leader emphasis). No schema change. |

---

## When shipped — doc hygiene

1. Update [`player-profile-feast.md`](player-profile-feast.md) nav row + scroll order (remove Matchups from Profile; document Opponents wing).
2. [`profile-build-playbook.md`](profile-build-playbook.md) — adjust Zone C chart bands if Matchups moves.
3. `PROJECT_MEMORY.md` recent log.
4. Part B only if opponent tables switch to `player_matchup_summary` reads (no schema change expected).
