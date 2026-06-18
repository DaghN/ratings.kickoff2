# Player profile (feast) — shipped layout

**Status:** Jun 2026 (layout + narrative contract). **Production page:** `player/profile.php?id={player}`.  
**Implementing v1 content:** [`docs/profile-build-playbook.md`](profile-build-playbook.md) (placement charter, module recipes, waves).  
**v1 content decisions (archive):** [`docs/archive/profile-content-candidates.md`](archive/profile-content-candidates.md).  
**Prior audits:** [`docs/archive/profile-redesign-framing.md`](archive/profile-redesign-framing.md), [`docs/archive/profile-data-audit-pass2.md`](archive/profile-data-audit-pass2.md).  
**Opponents hub (shipped Jun 2026):** [`player-opponents-hub.md`](player-opponents-hub.md) — Opponents top pill + inner sub-tabs; H2H poster/charts: [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md). Optional Career totals expansion still backlog.

---

## Architecture

| Layer | Files |
|-------|--------|
| Page | `player/profile.php` |
| Data | `includes/player_feast_load.php` → `player_feast_load_pm()` |
| Blocks | `includes/player_feast_blocks.php` |
| Helpers | `includes/player_feast_helpers.php` |
| Hero | `includes/player_hero.php` (rank, rating, games, milestones when unlocked) |
| Nav pills | `includes/player_nav.php` — Profile · **Opponents** · Milestones · Games |
| Opponents wing | `player/opponents/*.php` — inner tabs Head-to-head · W/D/L · Goals · DDs |
| Milestones | `player/milestones/garden.php` · `player/milestones/chronology.php` — inner wings **Garden** · **Chronology**; Chronology = player-filtered Recent feed (tier filter, newest-first, no player column); tier garden on Garden (`#garden-aspirational` …); helpers `includes/player_milestones_helpers.php`, `includes/player_milestones_lib.php` |
| CSS | `player-feast.css`, `player-feast-sections.css`, `player-feast-glance.css`, `player-feast-story.css`, `player-feast-personal-bests.css`; hero milestones in `theme.css`; garden in `player-milestones.css` |
| Calendar | `api/player_feast/player_calendar_days.php`, `player_calendar_day_games.php`, `player_calendar_weeks.php`, `player_calendar_week_games.php`; `js/player-feast/player-calendar.js`, `player-calendar-weeks.js` |

---

## Scroll order (as shipped)

1. Site header + **hero** (name → Profile tab; rank + rating → Rating LB; games → Activity peaks; milestones `{n}/{catalog}` → garden tab) + **player nav** (6px hero→nav, 12px nav→content on all `k2-player-wing` tabs — `theme.css`)
4. **At a glance** — three columns × four rows: **Presence** (first/last rated game, days & games this year), **Career** (opponents, games, wins, goals — no DDs, no ranks), **Achievements** (milestones + gold/silver/bronze). Narrow viewports: columns stay side-by-side; panel scrolls horizontally if needed (no vertical stack).
5. **Story so far** — heading *Let's take a look at **{name}**'s story so far...* (name in link-star); open-background prose ticker: current win streak (≥3), active play streak day/week (50/50 per page load), opponents/victims, standout calendar year (games + wins), career distinct days, then longest play-streak run (when no active streak). Omitted when no lines qualify.
6. **Played days → weeks narrative** — no section headings on production; one warm prose arc: per-year days line ends `…` (“In 2026, **Dagh** enjoyed **110** days of online Kick Off 2…”), days heatmap + year picker, then weeks line continues (“… and since **12 Mar 2019**, **Dagh** has played in no less than **210** different weeks…”) into the weeks heatmap. `#played-days` / `#played-weeks` anchors + sr-only titles kept for back links and a11y. **Played days heatmap:** full page column (`--k2-max-width` 1200px); day cells and inter-cell gaps scale with month column width (`cqi`); gap between month mini-calendars ≈ **1.3×** one cell width (scales with 12 / 6 / 4 column breakpoints); 12 → 6 → 4 month columns at 900px / 600px breakpoints.
7. **Played days detail** — UTC calendar; 12 months in one row for the selected year — **first career year** and **current calendar year** always render the full Jan–Dec grid, including months before first play or after today). **Future** day tiles are ghosted (faint outline) vs empty past days (full empty fill = missed). **Hover (fine pointer):** read-only preview (≤8 games, pre-game ratings; “Showing 8 of N” when truncated). **Click** a played day → **Games** tab `?day=YYYY-MM-DD#day-games` (context banner with **← Played days** + prev/next played-day chevrons; **filter bar hidden**; table only — up to 500 games per page). **Coarse pointer:** first tap pins tile + external tooltip (“Tap again to view games”); second tap same tile navigates (parity with H2H scoreline heatmap). Day-step chevrons use carry-scroll without `#day-games` on peer URLs.
8. **Played weeks detail** — all career years at once (52 UTC week tiles per year row). **Current calendar year:** remaining weeks use the same ghosted **future** tiles as played days (vs solid empty = missed). **Hover (fine pointer):** read-only preview (≤8 games, pre-game ratings; “Showing 8 of N” when truncated). **Click** a played week → **Games** tab `?from=played-weeks&period=week&anchor=YYYY-MM-DD#day-games` (banner; prev/next played-week chevrons; **← Played weeks** back link to `#played-weeks`). **Coarse pointer:** same two-tap preview → navigate pattern as played days.
9. **Bursts of activity** — no visible heading; warm hint continues the participation arc; four linked busiest cards (day · week · month · year; natural size centred in full-width quarters, 2×2 under 900px, 6:5 aspect, open bg + hairline border, moment hover on link only, no underline) → Games tab day / week / month / year filter; **← Bursts of activity** back link on filtered view.
10. **Moments** — no visible heading; warm lede; giant-killing + trophy mosaic (accent tag, secondary label/scoreline, muted meta); linked games/opponents. **Total goals bonanza:** show `BiggestSumOfGoalsGameID` only when opponent scored **&lt; 3×** hero; else highest-`SumOfGoals` career game where the ratio passes (global walk, not H2H); omit if none (`player_feast_load_bonanza.php`).
11. **Charts** — warm lede before career charts (rating by date / by game # toggle, games per month, goals-per-game histogram); sr-only **Career rating** title; per-panel `k2-chart-block__hint` unchanged inside frames. Career chart stack + opponents chart centred in page column (960px max). **Games per month** panel anchor `#games-per-month`. **Games per month:** click a bar → **Games** tab `?from=profile-games-chart&period=month&anchor=YYYY-MM-01#day-games` (**← Games per month** back link). **Goals histogram:** click bar → games tab GF filter (`#k2-player-games-filters`). **Coarse pointer** on drill-down bars: first tap = pinned bar + external tooltip; second tap = navigate (shared `k2-coarse-tap.js`; Chart.js built-in tooltips stay off on coarse per `chart-theme.js`).
12. **Most played opponents** — separate closing section (`#top-opponents`): warm finale lede (friends and rivalries; “Let's not forget… we picked up along the way”); horizontal bar chart (top 20, uniform H2H opponent red — `tableNegative` / `h2hOpponentFill`); no default bar highlight; click a bar opens **Opponents → Head-to-head** at `#h2h-rivalry` (fighter poster; skips carry-scroll restore; coarse: two-tap preview then navigate). Panel title only — no `k2-chart-block__hint` under the heading. Cumulative H2H + rating comparison charts live on the H2H tab — see [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md). H2H goals histograms + total-goals chart use the same coarse two-tap drill-down.

Win rate vs opponent rating was removed from the shipped page and the dormant API/JS were deleted in Jun 2026; do not reintroduce unless a future matchup-lab pass explicitly wants it.

Profile ends on the **most played opponents** bar chart; click-through opens **Opponents → Head-to-head**. Standalone rivalry section (inline matchup charts) was removed Jun 2026 — cumulative H2H + rating comparison live on the H2H tab.

---

## Surface rhythm (panels vs open background)

**Intent (Jun 2026):** The profile deliberately **mixes** contained surfaces and open page background — not every block gets the same card. Uniform paneling reads as a generic dashboard; alternating rhythm keeps scroll pacing and lets accent colour read as “ink on the page” where appropriate.

**Rule of thumb — choose by module type, not habit:**

| Surface | Use when… | Shipped examples |
|---------|-----------|------------------|
| **Open background** (`pm3-cal--hero`, no border/surface) | The visual *is* the content; low chrome; accent cells should pop against `--k2-bg-hover` | Played days, played weeks, **The story so far** |
| **Chart panel** (`k2-chart-panel` + `k2-chart-frame`) | Chart.js canvas, toggles, tooltips, fixed frame height; same contract as Activity (`activity.php`) | Rating, games/month, matchup charts |
| **Light tile / mosaic** | Small stat clusters or story cards; containment without full chart chrome | Presence/career tiles, personal bests, moments |

**Do not** “helpfully” panel everything for consistency. **Do** keep the split **typed** (same module type → same surface treatment) so the mix feels editorial, not random.

**CSS hooks:**

- Open heatmaps: `.pm3d-section__content > .pm3-cal--hero` — `background: none`, empty cells use `--pm3-cal-cell-empty: var(--k2-bg-hover)` (brighter grid on page bg, not inside a card).
- Chart panels: shared `theme.css` chart-panel rules + profile `player-feast-sections.css` frame heights.
- Section titles: `.pm3d-section` + `.k2-panel-heading` apply regardless of inner surface — headings group open and panel blocks alike.

**Future blocks:** Before adding a panel, ask whether the module is an **instrument** (chart/control) or a **texture/story** (heatmap, mosaic). Colour/copy polish can iterate later; surface choice should follow this split. Cross-ref: [`design-direction.md`](design-direction.md) — Chrome and layout.

---

## Data sources

| Block | Primary source |
|-------|----------------|
| Hero / career totals | `playertable` |
| Rank | Computed: count of `display=1` players with higher `rating` |
| Played days / weeks / games-by-month charts | `player_period_games` via APIs (`player_calendar_days.php`, `player_calendar_weeks.php`, `player_games_by_month.php`) — days/weeks use UTC period keys; graph time axes start at the server origin (**2017-06-09**) for comparability |
| Story so far | `playertable` (win streak, opponents, victims) + `player_play_streaks` + `player_period_league` (best year wins) + `player_period_games` (distinct days) — load: `player_feast_load_story.php` |
| Goals per game histogram | Read-time `ratedresults` via `api/player_goals_scored_distribution.php` (same SQL as games tab GF listbox; buckets 0..max) |
| Top opponents / H2H charts | `player_matchup_summary` via `api/player_top_opponents.php` and related APIs |
| Bursts of activity (busiest day/week/month/year) | `player_peak_period_games` via `player_feast_load_busiest()` (same cache as ranked8 peaks; `ratedresults` fallback only if table missing) |
| Moments | `playertable` extreme game IDs + single-row `ratedresults` lookups |
| Hero milestones / garden | `player_milestones` ⋈ `milestone_definitions` (`player_hero_vars.php` or feast load) |
| Other charts | `api/player_*.php` — prefer stored tables when listed in [`website-data-contract.md`](website-data-contract.md) |
| Rating over time / compare | `api/player_rating_history.php`, `api/player_compare_rating_history.php` — **processed games only** (`NewRatingA IS NOT NULL`); unprocessed tail omitted; chart **data** tail = today via `appendRatingThroughToday`; **By date** x-axis = `profileCareerTimeRange()` (Jun 2017 month start → end of current month, line stops at today — no fake future segment). Games/month uses same range + `offset: false`. Plot gutters: slice A (`chart-theme.js`). |

**Indexes (May 2026):** `ratedresults.idx_ratedresults_idA`, `idx_ratedresults_idB` — profile load and narrow game-row fetches. Local apply: `scripts/apply_ratedresults_player_indexes.ps1` (see `PROJECT_MEMORY.md`).

---

## Sibling tabs (unchanged role)

| Pill | File | Role |
|------|------|------|
| Games | `player/games.php` | Full match ledger |
| Opponents | `player/opponents/*.php` | Inner tabs Head-to-head · W/D/L · Goals · DDs — see [`player-opponents-hub.md`](player-opponents-hub.md) |
| Milestones | `player/milestones/garden.php` · `player/milestones/chronology.php` | **Garden** — tier garden (112 cards from catalog + unlock rows); **Chronology** — player-filtered Recent feed (tier filter, newest-first) |

Profile does **not** duplicate those tables.

---

## Narrative model (Jun 2026 confirmation)

**What this section is:** A **re-audit** after milestones and league shipped elsewhere on the site. It **confirms** the May 2026 feast direction — profile is a **curated portrait**, not a spreadsheet — and records **rough decisions now** for the next content passes. It does **not** lock pixel-perfect DOM order; it locks **jobs, zones, and pick rules**.

**Still the same plan:**

| Principle | Meaning |
|-----------|---------|
| **Story before analyst mode** | Celebrate participation and memorable events before rating obsession and H2H machinery |
| **Peek, not ledger** | Profile shows a glance; Games / W-D-L / Goals / DDs tabs and hub pages own depth |
| **Curate, don’t dump** | May audit **DROP** list stays valid (nadir row, recent avg rating, obscure victim counts, etc.) |
| **Comparison is opt-in** | Matchup charts at the bottom; auto-select #1 opponent is enough default — no guilt-first stats |

**Three zones** (from archive Part C — order within a zone can flex):

| Zone | Job | Blocks (human question) |
|------|-----|-------------------------|
| **A — Identity** | Who · ladder position · major actor? | **Hero** — rank, rating, games; milestones `{n}/{catalog}` when `NumberGames ≥ 1` |
| **B — Celebrate** | Still around? Who *is* he? What’s worth remembering? | **Presence** (current relevance) · **Career** (personality in KO2 terms) · **Bursts of activity** · **Moments** · *planned snippets:* participation line, milestone beat, league beat |
| **C — Understand** | Patterns over time · rivals · analyst depth | **Heatmaps** (habit / texture) · **Charts** (rating arc, activity, matchups) |

**Block intent (Dagh map → shipped):**

| Block | Answers |
|-------|---------|
| Hero | Rank, strength, volume — “how does he sit on the ladder?” |
| Presence | First rated game, last rated game, days this year, games this year — “is he still around?” |
| Career | Games, wins, goals, opponents — “what kind of player?” (not full W/D-L grid; no DDs in at-a-glance) |
| Bursts of activity | Busiest day / week / month / year — celebrate volume spikes after participation arc |
| Moments | Giant-killing + trophy games — specific memorable events |
| Heatmaps | Played days/weeks — visual life of the ladder; streak motivation |
| Charts | Rating over time, games/month, top opponents → H2H/compare — competitive depth last |

**Confirmed vs drift (Jun 2026):**

| Item | Status |
|------|--------|
| Charts last | **Confirmed** — matches original “rivalry at the bottom” intent |
| Heatmaps before Personal bests / Moments in DOM | **Accepted drift** — emphasizes activity texture early; editorial fork vs “Chronicle-first” mock A |
| Milestones on profile | **Partial** — hero count only; garden stays on Milestones tab |
| League on profile | **Gap** — stored awards exist; no profile snippet yet |
| Participation sentence (A04) | **Consider** — may compete with hero fold |
| Story so far (B06/B07/B08/C12/P02/P05) | **Shipped** Jun 2026 — after At a glance |
| Rivalry line (M09), milestone/league snippets | **v1 curated** — see backlog |

**Rough decisions now (next content passes — not all shipped):**

1. **Do not** move the full milestone garden or league history table onto Profile.
2. **Do** add **small Zone B snippets** when built:
   - **Milestone beat** — e.g. latest unlock or signature tier unlock → link to `milestone.php?key=` / garden tab
   - **League beat** — e.g. recent medal + compact career medal line from `player_league_award` / `player_league_totals` / `player_league_slice_totals` → link to League honours or Status leagues
3. **Do** consider **participation sentence** (N21) and **featured rivalry one-liner** (N4) before matchup charts — prose complements auto-selected opponent.
4. **Keep** mixed surface rhythm (open heatmaps, panelled charts) — see Surface rhythm § below.
5. **When evaluating any new field**, ask: which zone? one line or one card? celebrate or shame? duplicated on a tab? If it fails → tab or hub, not profile scroll.

**Agent jargon:** **“Fold”** = first screen before scroll (above the fold), **not** a named section. Usually hero ± top of Presence/Career — not “the Presence panel” by definition.

**Authority chain for profile content:** Dagh’s latest intent → [`profile-build-playbook.md`](profile-build-playbook.md) → v1 in [`archive/profile-content-candidates.md`](archive/profile-content-candidates.md) → this §.

---

## Gradual improvements (backlog)

**Curated v1:** [`archive/profile-content-candidates.md`](archive/profile-content-candidates.md). **How to build:** [`profile-build-playbook.md`](profile-build-playbook.md) (waves §7). Below = short summary.

### Ship next (v1)

| Theme | Items |
|-------|--------|
| **Presence** | B06 win streak · B07/B08 play streak (current vs best narrative; day/week; optional rotate on load) |
| **Career** | C01–C05 tiles — revisit `(#rank)` styling · C12 victims line if not redundant |
| **Moments** | M03 max rated victim (all ranks) · M08 favourite victim · M09 rivalry line · M12 Games tab opponent filter |
| **Peaks** | P02 best-year ticker · **P05 distinct days played** (Profile + site-wide HoF later) |
| **Milestones** | MS01 latest-unlock card · MS02 holo/amber count · MS04 unlocks last 12 months · MS08 league milestone card |
| **League** | L01 latest medal (bling) · L02/L07/L08 career medals + honours link · L04 league wins count |
| **UX rules** | X01 optimistic empty states · X04 conditional show / rotate snippets · deep links X05/X06 |

### Consider / design pass

A04 (vs hero fold) · B09 recent matches strip · M10/M11 loss cards (tone) · C14 historic peak rank (elite cutoff) · L06 first gold moment card · H05 heatmap overlays (DD days = DB work).

### Defer

A03, A07, A08 · B05 · H04.

### Reject (v1)

See catalog v1 § Reject — includes extra charts, tier-count MS05, MS07 list, participation-adjacent A01–A02, etc.

Do not revive the mock lab; iterate on `player/profile.php` and this doc.

**CSS hygiene (May 2026):** `player-feast.css` pruned to shipped blocks only (calendar, moments mosaic, busiest inline); mock nav/CORE/rivalry variants removed.
