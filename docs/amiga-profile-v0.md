# Amiga player profile (v0)

**Status:** profile feast v1 (Jun 2026) — **surface expansion slices 0–8 complete**. Extend via [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4 Potential only.

## URLs

| Page | Path |
|------|------|
| Hub nav | News · Leaderboards · World Cups · **Countries** · Activity · Hall of Fame · Tournaments · Live tournaments (`includes/amiga_hub_nav.php`; default landing `/amiga/news.php`) |
| **Countries hub** | `/amiga/countries/index.php` (hub index) · `/amiga/country/roster.php?country={token}` + `/amiga/country/rivals.php?country={token}` (country entity — Roster · Rivals segment) — [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) |
| Leaderboard (rating) | `/amiga/leaderboards/rating.php` (Leaderboards tab; `/amiga/rating.php` redirects) |
| Leaderboard wings | Tab order in `amiga_lb_nav.php`: Rating · World Cups · Tournament honours · Calendar &amp; geography · Goals · DDs &amp; CSs · Victims · Peak · Perf. rating |
| Tournament honours LB | `/amiga/leaderboards/tournament-honours.php` (all-events medals; WC → World Cups wing) |
| World Cups LB | `/amiga/leaderboards/world-cups/honours.php` (+ Results · Goals slices 2–3) — [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) |
| Hall of Fame | `/amiga/hall-of-fame.php` |
| Profile | `/amiga/player/profile.php?id={amiga_players.id}` |
| Tournament history | `/amiga/player/tournaments.php?id={amiga_players.id}` |
| Opponents | `/amiga/player/opponents/h2h.php?id={id}` (default wing) · `wdl.php` · `goals.php` · `dds.php` |
| Games | `/amiga/player/games.php?id={amiga_players.id}` |
| Single game | `/amiga/game.php?id={amiga_games.id}` |
| Tournament index | `/amiga/tournaments.php` |
| Tournament standings | `/amiga/tournament.php?id={tournaments.id}` |
| Tournament event stats | `/amiga/tournament/event-stats.php?id={tournaments.id}` — participation roster (all phases); **`#` rank** (autorank on default Pts sort) + inline player flag + **Perf. rating** |
| Tournament games | `/amiga/tournament/games.php?id={tournaments.id}` — all games in event; player filter dropdown (A–Z) |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): avatar + name → Profile tab; **rank** from stored `elo_rank` (present: `amiga_player_current`; time travel: `amiga_player_elo_rank_at_event`); rating → Rating LB; games → player games tab; rank · rating · games stat values use link-star + glow; **country** (fourth stat column — label + flag when mapped; **flag links to country roster**); unmapped country strings show as stat text; pre-debut at cutoff → — + note (T17)
- **Player nav** — Profile · Opponents · Tournaments · Games (`amiga_player_nav.php`)
- **Career strip** — `amiga_players` + `amiga_player_current` (W/D/L, goals, peak, opp avg)
- **Honours strip** — `amiga_player_current` honours columns: career WC medal counts (`wc_gold`/`wc_silver`/`wc_bronze`), tournaments won (`event_gold`), event podiums (`event_podiums`), optional last event date; links to tournament honours LB and WC-filtered history when applicable; hidden when no WC medals, wins, or podiums
- **Performance rating** — best event + latest event (snapshots, games ≥ 2); links to perf LB and tournament history; hidden when no qualifying perf rows
- **Moments** — trophy games from `amiga_player_current` `*GameID` pointers (`amiga_player_moments_lib.php`): biggest win, goal festival, peak rating game; score links to games tab with opponent filter; hidden when no resolvable game rows; **no streak card**
- **Recent tournaments** — `amiga_player_event_snapshots`: finish from **`event_finish_position`** ordinal (all events including WC; NULL → —); **`event_points` suffix only for single-phase events** (not league+cup marathons, not WCs — see contract §5.2.1); compact **Winner** badge and **Perf** when games ≥ 2
- **Tournament history** — `/amiga/player/tournaments.php`: full snapshot list (no pagination); sortable wide table (`amiga_profile_render_tournament_history_table()` — Jun 2026 k2-table stack: cloak + SSR th/td + mirror; filter URLs merge `k2_table_sort_query_params()`); per-event W-D-L, **F** / **A** totals, **GF/g** / **GA/g** averages, **`event_points` (Pts)**, rating before/delta/after, **Perf. rating** ([`amiga-performance-rating.md`](amiga-performance-rating.md)); **Event** chrome-tab segment (All / World Cups) + **Host country** / **Year** archive listboxes (`amiga_player_tournaments_filters_nav.php` — catalog index parity)
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` reads `amiga_player_event_snapshots` (one point per finalized tournament); [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js): **By date** = end-of-day rating after tournament days; **By tournament #** = event series (no within-tournament zigzags); toggle uses `player-feast-sections.css` segment control
- **Rank chart** — **shipped** solo + **H2H rank compare** (Jun 2026) — profile + `h2h.php` · hint: end-of-day rank after each tournament day · peak text summary (solo one line; H2H dual chrome/red) · **X:** full Amiga timeline · API solo + compare · [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md) · [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md)
- **Games tab** — server-side filters: scope segment (**All games** / **World Cup**, `k2-chrome-tabs` filter bar), three filter rows — (1) opponent, tournament, host country, opponent country; (2) year, since, until (through end of calendar year, inclusive); (3) result, GF, GA, GD (hero-signed), sum — with faceted omit-self listbox counts (`amiga_player_games_filter_facets.php`); online-style idle triggers (empty + link-star when active), ghost-width listboxes, panel matches trigger; sort; **k2-table cloak** + `ranked-table-pending` + scroll-mirror reveal (Jun 2026); ID anchor SSR; tournament + phase from `amiga_games` + `tournaments`; per-game frozen `rating_a/b` and `adjustment_a/b` from `amiga_game_ratings` (`new_rating_*` NULL after finalize v1); status line: game count · **Performance rating** (async JSON) · **Reset filters** pill (`data-k2-carry-scroll`); no pagination (full list). Contract: [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md) § Amiga Player Games.

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`amiga_players` + `amiga_player_current`** | Hero (incl. `elo_rank`), career strip | 1 query present; TT hero adds 1 indexed read on `amiga_player_elo_rank_at_event` |
| **`amiga_player_elo_rank_at_event`** | Rank-over-time chart (all finalizes after debut); TT hero rank | 1 query per chart load (~≤600 points max) |
| **`amiga_player_event_snapshots`** | Per-event `elo_rank` on participated rows only (insufficient alone for rank chart) | — |
| **`amiga_player_event_snapshots` + `tournaments`** | Profile rating chart JSON | 1 query per chart load (~≤1k events max per player) |
| **`amiga_games` + `amiga_game_ratings`** (via `amiga_db.php`) | Games list per-match adjustments | 1 query per games page |

**Derived tables in use:** `amiga_game_ratings`, `amiga_player_event_snapshots`, `amiga_player_current`, `amiga_tournament_standings`, `amiga_player_matchup_summary`, `amiga_player_matchup_at_event`, `amiga_generalstats` (HoF table stale until next slice — matchup/network written at finalize). See [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) and [`amiga-data-contract.md`](amiga-data-contract.md).

**Deferred on profile:** dedicated WC medals block, activity calendars, career strip DD/CS enrichment. **Opponents wing** shipped (`amiga/player/opponents/*` — W/D/L · Goals · DDs + **full H2H** poster/pair detail/moments/charts) — sortable ledger via `amiga_player_opponents_tables.php` (cloak on parent page; Tier B). Wide-table stack: [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md). See also [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4.

### Participation points (read carefully)

| What | Where | Use on profile |
|------|--------|----------------|
| **Phase points** (league table, WC group, etc.) | `amiga_tournament_standings` per scope | Tournament page only — **not** on participation rows |
| **Event points** (`event_points`) | `amiga_player_event_snapshots` | Tournament history **Pts** column; recent block when one phase = whole event |

Participation **roster and W-D-L/goals** come from **`amiga_games`** — a row exists for every player with ≥1 game, regardless of standings shape.

**Event finish** (policy): [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md). Column **`event_finish_position`** (NULL when undefined). **Phase ranks are not stored on participation** — group/league tables live in `amiga_tournament_standings` only.

**World Cup finish** on history table: same as other events — **`event_finish_position`** ordinal (1st/2nd/3rd) or —; WC podium words derived from finish tier when needed.

## Hub navigation (v0)

- **`includes/amiga_hub_nav.php`** — segment tabs: **News** (realm default), **Leaderboards**, **World Cups**, **Countries**, **Activity**, **Hall of Fame**, **Tournaments**, **Live tournaments**. Tournament honours is a leaderboard sub-wing only. Included on hub-level pages only (not player profile/games).
- Set `$k2AmigaHubTabActive` before include: `leaderboards` | `world-cups` | `countries` | `activity` | `tournaments` | `live-tournaments` | `hall-of-fame`.

## Files

- `includes/amiga_hub_nav.php` — realm hub tabs
- `amiga/countries/index.php` (hub) · `amiga/country/roster.php`, `amiga/country/rivals.php` (country entity) — Countries hub + per-country roster by nationality
- `includes/amiga_countries_lib.php`, `includes/amiga_countries_index_table.php`, `includes/amiga_countries_roster_table.php`, `includes/amiga_country_hero.php`
- `includes/k2_amiga_country_flag.php` — flag SVG map + optional roster link on nationality cells
- `amiga/hall-of-fame.php` — HoF (generalstats + ratio leaders + WC medal panel)
- `includes/amiga_player_tournament_lib.php` — snapshot + current reads
- `includes/amiga_player_matchup_lib.php` — directed pair reads (H2H poster, future slice)
- `includes/amiga_matchup_snapshot_lib.php`, `includes/amiga_player_opponents_load.php`, `includes/amiga_player_opponents_tables.php`
- `includes/amiga_player_opponents_lib.php`, `includes/amiga_player_opponents_nav.php`, `includes/amiga_player_opponents_page.php`
- `amiga/player/opponents/{h2h,wdl,goals,dds}.php` — Opponents wing (W/D/L · Goals · DDs + full H2H poster/pair detail/moments/charts)
- `includes/k2_lb_sortable_table_head.inc.php`, `includes/k2_table_helpers.php` — hub LB + wide sortable tables ([`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md))
- `includes/amiga_lb_nav.php`, `includes/amiga_lb_lib.php` — leaderboard wing tabs + shared row helpers
- `includes/amiga_records_*.php`, `includes/amiga_records_hof_links.php` — HoF panels + metric deep links
- `amiga/leaderboards/*.php` — rating, goals, DDs, victims, peak, perf rating, tournament honours
- `includes/amiga_db.php` — ground + derived join helpers
- `includes/amiga_player_load.php` — present + snapshot-at-cutoff; persisted `elo_rank`; pre-debut at cutoff (T17) via `amiga_player_publish_hero_context()`
- `includes/amiga_elo_rank_lib.php` — time-travel rank from `amiga_player_elo_rank_at_event`
- `includes/amiga_player_snapshot_lib.php`
- `includes/amiga_player_hero.php`
- `includes/amiga_player_nav.php`
- `includes/amiga_profile_blocks.php`
- `includes/amiga_player_moments_lib.php`
- `includes/amiga_tournament_lib.php`
- `includes/amiga_tournament_bracket.php`
- `stylesheets/amiga-tournament.css`
- `amiga/tournament.php`
- `amiga/tournaments.php`
- `includes/amiga_player_games_lib.php` — filters, WHERE, sort URLs; `amiga_player_games_filters_from_request()`
- `includes/amiga_player_games_filter_facets.php` — faceted listbox counts (omit-self per dimension)
- `includes/amiga_player_games_perf_lib.php` — filtered games-list performance rating (API)
- `api/amiga_player_games_perf_rating.php` — JSON GET (same filter params as games tab)
- `js/amiga-player-games-perf.js` — lazy-load perf into status line
- `includes/amiga_player_game_row.php`
- `includes/amiga_rated_game_row.php` — neutral game row (`amiga/game.php`, list ID links)
- `amiga/game.php`
- `amiga/player/profile.php`
- `amiga/player/tournaments.php` — full tournament history
- `amiga/player/games.php`
- `includes/k2_amiga_routes.php` — canonical paths + legacy redirect helper
- `amiga/profile.php`, `amiga/games.php?id=`, `amiga/player-tournaments.php` — 302 redirects to `amiga/player/*`; bare `/amiga/games.php` → Games hub Recent

## Import: player identity

`scripts/amiga/player_names.py` merges Access duplicates at import:

- Collapsed whitespace (`Gianni  T` → `Gianni T`)
- Trailing period (`Knut L.` → `Knut L`, most games wins)
- Case variants (`Oliver ST` → `Oliver St`, most games wins)
- Country from `Rankings` when any alias had one

Re-run: `python -m scripts.amiga prove`. Import audit: `data/amiga/exports/import_manifest.json` (see [`amiga-import-layer.md`](amiga-import-layer.md)).

## Rating chart timeline

Amiga `api/player_rating_history.php?realm=amiga` returns `timelineStart` = `MIN(game_date)` from `amiga_games` (first ladder game, ~Nov 2001) and `meta.granularity = event`. Optional `as=` filters snapshot points ≤ cutoff and sets `meta.cutoffActive`; date x-axis ends at the last cutoff event (no flat line to today). Online still uses June 2017 and per-game points. **By date** x-axis on Amiga profile uses `timelineStart` (month start → today in present mode), not the online server origin.

**Chart views** (`player-rating-chart.js`, shared shell with online profile). **Default tab** comes from page markup: Amiga profile = **By date**; online profile = **By game #**.

| Toggle | Points plotted | Peak / summary |
|--------|----------------|----------------|
| **By date** | One per local day — rating after the last event that day | Career peak from event series; latest from end-of-day series (+ today) |
| **By tournament #** | Origin at tournament #0 (1600 Elo), then one point per `amiga_rating_events` row (`rating_after`) | Event index on x-axis; tooltip links to tournament page |

API returns one point per finalized tournament (not per game). Multi-game tournaments appear as a single step on the tournament # axis.

## Tournament pages (cups + leagues)

- **Index** — `/amiga/tournaments.php` — Cup/League badges, optional All/Cups/Leagues filter; cup links with knockouts jump to `#bracket`
- **Tournament detail** — `/amiga/tournament/event-stats.php?id=` (default) · `standings.php` · `stages.php` (WC) · `games.php` — hero + section nav (**Event stats** first); legacy `/amiga/tournament.php?id=` 302s to event-stats; phase scope via `?scope=` / `?scope_key=`; k2-table helpers in `amiga_tournament_lib.php` / `amiga_profile_blocks.php`
- **Bracket** — phase-grouped columns (Quarter → Semi → Final; placement finals/brackets below); click aggregate score for leg-by-leg tie detail (`scope=knockout&scope_key=…`); `extra` penalties on leg rows
- **League marathons** — e.g. London XXIII: overall table only, no empty bracket shell

## Not shipped (deferred)

- Per-game detail page, compare chart, cross-realm H2H, milestones
- Dedicated profile WC medals block (honours strip covers career summary)
- **Match streaks** — no leaderboard wing, HoF rows, or profile streak moment. Real within-day play order is unknown; synthetic `game_date` chronology is not valid for consecutive-result streaks. See [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks.

## Browser QA checklist (standings + bracket)

After `python -m scripts.amiga replay`, spot-check locally:

1. **Index** — `/amiga/tournaments.php` — Cup/League badges distinct; filter tabs work; cup links open standings (knockout cups → `#bracket`)
2. **League table** — find London XXIII — top 3: Dagh N (69), Gianni T (65), Sandro T (60); Cup badge absent; no bracket section
3. **Cup group** — World Cup XI → Group A tab — winner Alkis P (45 pts)
4. **Knockout bracket** — World Cup XI — bracket shows semi winners Gianni T over Lorenzo C (10–6) and Alkis P over Andy G; columns scroll on desktop, stack ~375px
5. **Knockout fixture** — click semi score → leg table; Gianni T / Lorenzo C (`scope_key=Semi Finals|149-253`) — 2 legs, winner Gianni T (10–6). Penalties: open a tied placement tie (e.g. `Places 17-24|445-467`) — leg row shows `extra` text on the score line (e.g. `(4-4) 5-4 p.k.`)
6. **Games links** — busy player games tab — tournament name → overall/group; phase → group or knockout scope
7. **Profile** — recent tournaments block; **Moments** (e.g. Oliver St `id=345` shows 26–0 goal festival); cups with knockouts link to `#bracket`; rating chart **By tournament #** has no zigzags inside multi-game events; busy player with 10+ events in one year readable on calendar axis
7b. **Tournament history** — `/amiga/player/tournaments.php?id=<busy_player>` — all events listed; **Pts** = `event_points`; WC rows show medal finish not group rank; **country** pills reduce row set (e.g. Dagh N `id=73`: 2 cups, 5 in England)
8. **Hall of Fame** — `/amiga/hall-of-fame.php` loads; record holders link to profiles; metric cells deep-link to LB wings; no streak rows
8b. **Tier A LB** — `/amiga/leaderboards/goals.php` (and siblings) sort; wing nav complete; HoF links land correctly
9. **Tournament honours LB** — `/amiga/leaderboards/tournament-honours.php` — Elo · Events · event medals/podiums · WC block; default sort Events (desc)
10. **Games tab** — adjustment column populated for finalized games (frozen rating + delta); sort by adjustment works
11. **Responsive** — tournament page usable at ~375px width (bracket stacks, nav wraps)

CLI parity: `python -m scripts.amiga standings-parity --tournament "London XXIII"`
