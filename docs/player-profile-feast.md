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
| Hero | `includes/player_hero.php` (rank, rating, peak, games) |
| Nav pills | `includes/player_nav.php` — Profile · Games · Wins · Goals · DDs |
| CSS | `player-feast.css`, `player-feast-sections.css`, `player-feast-glance.css`, `player-feast-personal-bests.css` |
| Calendar | `api/player_feast/player_calendar_days.php`, `js/player-feast/player-calendar.js` |

---

## Scroll order (as shipped)

1. Site header + **hero** (name, rank, rating, peak, games)
2. Feast **pills**
3. **Presence** + **Career** (at-a-glance; career ranks when `Display = 1`)
4. **Played days** (calendar year, played-day cells)
5. **Personal bests** (busiest day / month / year for this player)
6. **Moments** (longest win streak + trophy games with links)
7. **Charts** — rating over time, games per month, rating by game #, top opponents (feeds H2H), head-to-head, rating comparison, opponent search

(Win rate vs opponent rating chart was removed from the shipped page; `js/player-winrate-opponent-chart.js` remains in the tree if reintroduced in a lower “matchup lab” block.)

Standalone **rivalry section** was removed; top-opponents chart auto-selects the #1 opponent for H2H/compare.

---

## Data sources

| Block | Primary source |
|-------|----------------|
| Hero / career totals | `playertable` |
| Rank | Computed: count of `display=1` players with higher `rating` |
| Games this month / year | `ratedresults` counts for player |
| Played days | `ratedresults` distinct dates per year |
| Personal bests | Per-player max games in one day / month / year (`ratedresults`) |
| Moments | `playertable` extreme game IDs + `ratedresults` row lookups |
| Charts | JSON APIs under `api/player_*.php` (see audit archive API table) |

**Indexes (May 2026):** `ratedresults.idx_ratedresults_idA`, `idx_ratedresults_idB` — profile load and player APIs. See `PROJECT_MEMORY.md` and `individual1_profile_diag.php` (localhost).

---

## Sibling tabs (unchanged role)

| Pill | File | Role |
|------|------|------|
| Games | `individual3.php` | Full match ledger |
| Wins / Goals / DDs | `individual2a/b/c.php` | Per-opponent aggregates |

Profile does **not** duplicate those tables.

---

## Gradual improvements (backlog)

- Tone/copy pass on chart helpers
- Participation sentence (N21) if not yet in Presence
- Achievement-style badges (P2 brainstorm)
- Optional server1-style precompute if Trends hall-of-fame pattern is reused
- Further SQL consolidation on profile load (diag-driven)

Do not revive the mock lab; iterate on `individual1.php` and this doc.

**CSS hygiene (May 2026):** `player-feast.css` pruned to shipped blocks only (calendar, moments mosaic, busiest inline); mock nav/CORE/rivalry variants removed.
