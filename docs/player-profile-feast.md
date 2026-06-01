# Player profile (feast) — shipped layout

**Status:** Jun 2026 (layout + narrative contract). **Production page:** `individual1.php?id={player}`.  
**Prior audits:** [`docs/archive/profile-redesign-framing.md`](archive/profile-redesign-framing.md) (Phase 0 jobs + principles), [`docs/archive/profile-data-audit-pass2.md`](archive/profile-data-audit-pass2.md) (asset verdicts + feast contract Part C). **Jun 2026 confirmation** below — same plan, updated picks for milestones/league.

---

## Architecture

| Layer | Files |
|-------|--------|
| Page | `individual1.php` |
| Data | `includes/player_feast_load.php` → `player_feast_load_pm()` |
| Blocks | `includes/player_feast_blocks.php` |
| Helpers | `includes/player_feast_helpers.php` |
| Hero | `includes/player_hero.php` (rank, rating, games, milestones when unlocked) |
| Nav pills | `includes/player_nav.php` — Profile · Games · W/D/L · Goals · DDs · **Milestones** |
| Milestones | `individual_milestones.php` — tier garden (catalog count from DB, **112** after `year_in_heaven`); all card titles link to `milestone.php?key=` (locked = underline + hover brighten); date line uses unlock-event register; helpers `includes/player_milestones_helpers.php` |
| CSS | `player-feast.css`, `player-feast-sections.css`, `player-feast-glance.css`, `player-feast-personal-bests.css`; hero milestones in `theme.css`; garden in `player-milestones.css` |
| Calendar | `api/player_feast/player_calendar_days.php`, `player_calendar_weeks.php`; `js/player-feast/player-calendar.js`, `player-calendar-weeks.js` |

---

## Scroll order (as shipped)

1. Site header + **hero** (name → Profile tab; rank + rating → `ranked7.php` Rating LB; games → `ranked8.php#k2-peak-period-all-time`; **milestones** `{n}/{catalog}` when `NumberGames >= 1` → garden tab; stat links = pointer only, no hover ink)
2. Feast **pills**
4. **Presence** + **Career** (at-a-glance; career ranks when `Display = 1`)
5. **Played days** (UTC calendar; year segment picker; 12 months in one row for the selected year)
6. **Played weeks** (52 UTC week tiles per year from first rated game through today; tooltips = week range + game count)
7. **Personal bests** (busiest day / month / year for this player)
8. **Moments** (longest win streak + trophy games with links)
9. **Charts** — Activity-style full-width frames: rating over time / by game # toggle with peak dashed line, games per month, tall top-opponents bar chart (feeds H2H), head-to-head, rating comparison by date / by games-played toggle (`vs` beside toggle), then optional opponent search

Win rate vs opponent rating was removed from the shipped page and the dormant API/JS were deleted in Jun 2026; do not reintroduce unless a future matchup-lab pass explicitly wants it.

Standalone **rivalry section** was removed; top-opponents chart auto-selects the #1 opponent for H2H/compare.

---

## Surface rhythm (panels vs open background)

**Intent (Jun 2026):** The profile deliberately **mixes** contained surfaces and open page background — not every block gets the same card. Uniform paneling reads as a generic dashboard; alternating rhythm keeps scroll pacing and lets accent colour read as “ink on the page” where appropriate.

**Rule of thumb — choose by module type, not habit:**

| Surface | Use when… | Shipped examples |
|---------|-----------|------------------|
| **Open background** (`pm3-cal--hero`, no border/surface) | The visual *is* the content; low chrome; accent cells should pop against `--k2-bg-hover` | Played days, played weeks |
| **Chart panel** (`k2-chart-panel` + `k2-chart-frame`) | Chart.js canvas, toggles, tooltips, fixed frame height; same contract as Activity (`server1.php`) | Rating, games/month, matchup charts |
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
| Top opponents / H2H charts | `player_matchup_summary` via `api/player_top_opponents.php` and related APIs |
| Personal bests (busiest day/month/year) | `player_peak_period_games` via `player_feast_load_busiest()` (same cache as ranked8 peaks; `ratedresults` fallback only if table missing) |
| Moments | `playertable` extreme game IDs + single-row `ratedresults` lookups |
| Hero milestones / garden | `player_milestones` ⋈ `milestone_definitions` (`player_hero_vars.php` or feast load) |
| Other charts | `api/player_*.php` — prefer stored tables when listed in [`website-data-contract.md`](website-data-contract.md) |

**Indexes (May 2026):** `ratedresults.idx_ratedresults_idA`, `idx_ratedresults_idB` — profile load and narrow game-row fetches. Local apply: `scripts/apply_ratedresults_player_indexes.ps1` (see `PROJECT_MEMORY.md`).

---

## Sibling tabs (unchanged role)

| Pill | File | Role |
|------|------|------|
| Games | `individual3.php` | Full match ledger |
| W/D/L / Goals / DDs | `individual2a/b/c.php` | Per-opponent aggregates |
| Milestones | `individual_milestones.php` | Tier garden (112 cards from catalog + unlock rows) |

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
| **B — Celebrate** | Still around? Who *is* he? What’s worth remembering? | **Presence** (current relevance) · **Career** (personality in KO2 terms) · **Personal bests** · **Moments** · *planned snippets:* participation line, milestone beat, league beat |
| **C — Understand** | Patterns over time · rivals · analyst depth | **Heatmaps** (habit / texture) · **Charts** (rating arc, activity, matchups) |

**Block intent (Dagh map → shipped):**

| Block | Answers |
|-------|---------|
| Hero | Rank, strength, volume — “how does he sit on the ladder?” |
| Presence | Last seen, last game, this month/year — “is he still around?” |
| Career | Games, wins, goals, DDs, opponents + ranks — “what kind of player?” (not full W/D-L grid) |
| Personal bests | Busiest day / month / year — another story beat |
| Moments | Longest win streak + trophy games — specific memorable events |
| Heatmaps | Played days/weeks — visual life of the ladder; streak motivation |
| Charts | Rating over time, games/month, top opponents → H2H/compare — competitive depth last |

**Confirmed vs drift (Jun 2026):**

| Item | Status |
|------|--------|
| Charts last | **Confirmed** — matches original “rivalry at the bottom” intent |
| Heatmaps before Personal bests / Moments in DOM | **Accepted drift** — emphasizes activity texture early; editorial fork vs “Chronicle-first” mock A |
| Milestones on profile | **Partial** — hero count only; garden stays on Milestones tab |
| League on profile | **Gap** — stored awards exist; no profile snippet yet |
| Participation sentence (N21), rivalry one-liner (N4), recent matches strip (N5) | **Still backlog** — cheap narrative glue |

**Rough decisions now (next content passes — not all shipped):**

1. **Do not** move the full milestone garden or league history table onto Profile.
2. **Do** add **small Zone B snippets** when built:
   - **Milestone beat** — e.g. latest unlock or signature tier unlock → link to `milestone.php?key=` / garden tab
   - **League beat** — e.g. recent medal + compact career medal line from `player_league_award` / `player_league_totals` / `player_league_slice_totals` → link to League honours or Status leagues
3. **Do** consider **participation sentence** (N21) and **featured rivalry one-liner** (N4) before matchup charts — prose complements auto-selected opponent.
4. **Keep** mixed surface rhythm (open heatmaps, panelled charts) — see Surface rhythm § below.
5. **When evaluating any new field**, ask: which zone? one line or one card? celebrate or shame? duplicated on a tab? If it fails → tab or hub, not profile scroll.

**Agent jargon:** **“Fold”** = first screen before scroll (above the fold), **not** a named section. Usually hero ± top of Presence/Career — not “the Presence panel” by definition.

**Authority chain for profile content:** Dagh’s latest intent → this § → archive Part B/C → shipped `individual1.php`.

---

## Gradual improvements (backlog)

Prioritized from Jun 2026 confirmation — **Zone B story glue first**, then optional Zone C polish.

| ID | Asset | Zone | Notes |
|----|-------|------|-------|
| N21 | **Participation sentence** | B | Template under hero or above Presence: name · N games · O opponents · since year |
| — | **Milestone snippet** | B | Latest or signature unlock; link to garden / `milestone.php` — not the full garden |
| — | **League snippet** | B | Recent medal + compact career counts; link to League honours / Status |
| N4 | **Featured rivalry one-liner** | C (intro) | #1 opponent by volume + W–D–L before matchup charts (data exists; chart auto-selects) |
| N5 | **Recent matches strip** | B | 5–8 rows max — defer if it competes with Games tab |
| — | **Rated play streaks** | B? | `player_play_streaks` — live on Streaks LB + HoF; one line only if added |
| — | SQL / load consolidation | — | Diag-driven; not user-visible |

Do not revive the mock lab; iterate on `individual1.php` and this doc.

**CSS hygiene (May 2026):** `player-feast.css` pruned to shipped blocks only (calendar, moments mosaic, busiest inline); mock nav/CORE/rivalry variants removed.
