# Player profile (feast) — shipped layout

**Status:** May 2026. **Production page:** `individual1.php?id={player}`.  
**Historical redesign notes:** `docs/archive/profile-data-audit-pass2.md`, `docs/archive/profile-redesign-framing.md` (git tag/checkpoint `b8c5a98` for mock lab).

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

1. Site header + **hero** (name, rank, rating, games; **milestones** column with `{n}/{catalog}` only when `NumberGames >= 1` — links to garden tab; catalog via `k2_milestone_catalog_total()`)
2. Feast **pills**
4. **Presence** + **Career** (at-a-glance; career ranks when `Display = 1`)
5. **Played days** (current calendar year, played-day cells)
6. **Played weeks** (52 UTC week tiles per year from first rated game through today; tooltips = week range + game count)
7. **Personal bests** (busiest day / month / year for this player)
8. **Moments** (longest win streak + trophy games with links)
9. **Charts** — rating over time, games per month, rating by game #, top opponents (feeds H2H), head-to-head, rating comparison, opponent search

(Win rate vs opponent rating chart was removed from the shipped page; `js/player-winrate-opponent-chart.js` remains in the tree if reintroduced in a lower “matchup lab” block.)

Standalone **rivalry section** was removed; top-opponents chart auto-selects the #1 opponent for H2H/compare.

---

## Data sources

| Block | Primary source |
|-------|----------------|
| Hero / career totals | `playertable` |
| Rank | Computed: count of `display=1` players with higher `rating` |
| Played days / weeks / games-by-month charts | `player_period_games` via APIs (`player_calendar_days.php`, `player_calendar_weeks.php`, `player_games_by_month.php`) — weeks use `period_type = week`, UTC Monday keys |
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

## Gradual improvements (backlog)

- **Rated play streaks** (current + personal best, day/week) — stored in `player_play_streaks`; live on Leaderboards **Streaks** (`ranked4.php`) and HoF (`server2.php`); not yet on profile hero/feast (May 2026).
- Participation sentence (N21) if not yet in Presence
- Achievement-style badges (P2 brainstorm)
- Optional server1-style precompute if Trends hall-of-fame pattern is reused
- Further SQL consolidation on profile load (diag-driven)

Do not revive the mock lab; iterate on `individual1.php` and this doc.

**CSS hygiene (May 2026):** `player-feast.css` pruned to shipped blocks only (calendar, moments mosaic, busiest inline); mock nav/CORE/rivalry variants removed.
