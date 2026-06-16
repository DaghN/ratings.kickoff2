# Player profile (feast) — shipped layout

**Status:** Jun 2026 (layout + narrative contract). **Production page:** `player/profile.php?id={player}`.  
**Implementing v1 content:** [`docs/profile-build-playbook.md`](profile-build-playbook.md) (placement charter, module recipes, waves).  
**v1 content decisions (archive):** [`docs/archive/profile-content-candidates.md`](archive/profile-content-candidates.md).  
**Lab handoff (archive):** [`docs/archive/profile-lab-agent-handoff.md`](archive/profile-lab-agent-handoff.md) — optional `individual1-profile-lab{N}.php` previews.  
**Prior audits:** [`docs/archive/profile-redesign-framing.md`](archive/profile-redesign-framing.md), [`docs/archive/profile-data-audit-pass2.md`](archive/profile-data-audit-pass2.md).  
**Planned IA (not shipped):** [`player-opponents-hub.md`](player-opponents-hub.md) — Opponents umbrella tab, Profile slimming, optional Career totals expansion.

---

## Architecture

| Layer | Files |
|-------|--------|
| Page | `player/profile.php` |
| Data | `includes/player_feast_load.php` → `player_feast_load_pm()` |
| Blocks | `includes/player_feast_blocks.php` |
| Helpers | `includes/player_feast_helpers.php` |
| Hero | `includes/player_hero.php` (rank, rating, games, milestones when unlocked) |
| Wing context link | `includes/player_wing_up_link.php` — « Leaderboards (online + Amiga) |
| Nav pills | `includes/player_nav.php` — Profile · Games · **Opponents** · Milestones |
| Opponents wing | `player/opponents/*.php` — inner tabs Head-to-head · W/D/L · Goals · DDs |
| Milestones | `player/milestones.php` — tier garden (catalog count from DB, **112** after `year_in_heaven`); all card titles link to `milestone.php?key=` (locked = underline + hover brighten); date line uses unlock-event register; helpers `includes/player_milestones_helpers.php` |
| CSS | `player-feast.css`, `player-feast-sections.css`, `player-feast-glance.css`, `player-feast-personal-bests.css`; hero milestones in `theme.css`; garden in `player-milestones.css` |
| Calendar | `api/player_feast/player_calendar_days.php`, `player_calendar_day_games.php`, `player_calendar_weeks.php`; `js/player-feast/player-calendar.js`, `player-calendar-weeks.js` |

---

## Scroll order (as shipped)

1. Site header + **← Leaderboards** context link + **hero** (name → Profile tab; rank + rating → `leaderboards/rating.php` Rating LB; games → `leaderboards/activity-peaks.php#k2-peak-period-all-time`; **milestones** `{n}/{catalog}` when `NumberGames >= 1` → garden tab; stat links = pointer only, no hover ink)
2. Feast **pills**
4. **Presence** + **Career** (at-a-glance; career ranks when `Display = 1`)
5. **Played days** (UTC calendar; year segment picker; hint = per-year story line e.g. “In 2026, **Dagh** played on **110** rated days.”; 12 months in one row for the selected year — **first career year** and **current calendar year** always render the full Jan–Dec grid, including months before first play or after today). **Hover:** read-only preview (≤8 games, pre-game ratings; “Showing 8 of N” when truncated). **Click** a played day → **Games** tab `?day=YYYY-MM-DD#day-games` (banner; **prev/next played-day chevrons** use carry-scroll without `#day-games` so scroll position is preserved).
6. **Played weeks** (52 UTC week tiles per year from first rated game through today; tooltips = week range + game count)
7. **Personal bests** (busiest day / month / year for this player)
8. **Moments** (longest win streak + trophy games with links)
9. **Charts** — Activity-style full-width frames: rating over time / by game # toggle with peak dashed line, games per month, **goals per game** histogram (0..max GF; bar click → games tab `gf` filter).
10. **Most played opponents** — horizontal bar chart; click a bar opens **Opponents → Head-to-head** for that pairing. Cumulative H2H + rating comparison charts live on the H2H tab — see [`player-opponents-h2h-poster.md`](player-opponents-h2h-poster.md).
11. **Rivalry (placeholder)** — dashed teaser card after charts: top opponent by games, link to Opponents H2H; fuller record/form/all-games band TBD.

Win rate vs opponent rating was removed from the shipped page and the dormant API/JS were deleted in Jun 2026; do not reintroduce unless a future matchup-lab pass explicitly wants it.

Standalone **rivalry section** (inline charts) was removed; top-opponents bar + **rivalry placeholder card** link into Opponents H2H.

---

## Surface rhythm (panels vs open background)

**Intent (Jun 2026):** The profile deliberately **mixes** contained surfaces and open page background — not every block gets the same card. Uniform paneling reads as a generic dashboard; alternating rhythm keeps scroll pacing and lets accent colour read as “ink on the page” where appropriate.

**Rule of thumb — choose by module type, not habit:**

| Surface | Use when… | Shipped examples |
|---------|-----------|------------------|
| **Open background** (`pm3-cal--hero`, no border/surface) | The visual *is* the content; low chrome; accent cells should pop against `--k2-bg-hover` | Played days, played weeks |
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
| Goals per game histogram | Read-time `ratedresults` via `api/player_goals_scored_distribution.php` (same SQL as games tab GF listbox; buckets 0..max) |
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
| Games | `player/games.php` | Full match ledger |
| W/D/L / Goals / DDs | `player/opponents/{wdl,goals,dds}.php` | Per-opponent aggregates under **Opponents** tab |
| Milestones | `player/milestones.php` | Tier garden (112 cards from catalog + unlock rows) |

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

**Lab note (Jun 2026):** Production uses `pm3efg-duo` side-by-side stat tables. Lab B1/B2 experiments archived — see [`archive/profile-lab-agent-handoff.md`](archive/profile-lab-agent-handoff.md).
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
| Participation sentence (A04) | **Consider** — may compete with hero fold |
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
| **Moments** | M03 max rated victim (rank-gated for non-elite) · M08 favourite victim · M09 rivalry line · M12 Games tab opponent filter |
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
