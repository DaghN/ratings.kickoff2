# Amiga player profile (v0)

**Status:** profile feast v1 (Jun 2026) — **surface expansion slices 0–8 complete**. Extend via [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4 Potential only.

## URLs

| Page | Path |
|------|------|
| Hub nav | News · Leaderboards · Tournaments · Live tournaments · Activity · Hall of Fame (`includes/amiga_hub_nav.php`; default landing `/amiga/news.php`) |
| Leaderboard (rating) | `/amiga/leaderboards/rating.php` (Leaderboards tab; `/amiga/rating.php` redirects) |
| Leaderboard wings | `/amiga/leaderboards/rating.php`, `goals.php`, `double-digits.php`, `victims.php`, `peak-rating.php`, `performance-rating.php` — via `amiga_lb_nav.php` (`/amiga/rating.php` → 302) |
| Tournament honours LB | `/amiga/leaderboards/tournament-honours.php` |
| Hall of Fame | `/amiga/hall-of-fame.php` |
| Profile | `/amiga/player/profile.php?id={amiga_players.id}` |
| Tournament history | `/amiga/player/tournaments.php?id={amiga_players.id}` |
| Games | `/amiga/player/games.php?id={amiga_players.id}` |
| Single game | `/amiga/game.php?id={amiga_games.id}` |
| Head-to-head | `/amiga/h2h.php?id1={id}&id2={id}` — directed `amiga_player_matchup_summary` rows |
| Tournament index | `/amiga/tournaments.php` |
| Tournament standings | `/amiga/tournament.php?id={tournaments.id}` |
| Tournament event stats | `/amiga/tournament.php?id={tournaments.id}&view=event-stats` — participation roster (all phases); includes **Perf. rating** column |
| Tournament games | `/amiga/tournament.php?id={tournaments.id}&view=games` — all games in event; player filter dropdown |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): **← Leaderboards** context link above panel; rank, rating, games, **country** (fourth stat column — label + flag when mapped); unmapped country strings show as stat text
- **Player nav** — Profile · Tournaments · Games (`amiga_player_nav.php`)
- **Career strip** — `amiga_players` + `amiga_player_stats` (W/D/L, goals, peak, opp avg)
- **Honours strip** — `amiga_player_tournament_totals` (already loaded): career WC medal counts (`wc_gold`/`wc_silver`/`wc_bronze`), tournaments won (`event_gold`), event podiums (`event_podiums`), optional last event date; links to tournament honours LB and WC-filtered history when applicable; hidden when no WC medals, wins, or podiums
- **Performance rating** — best event + latest event (participation, games ≥ 2); links to perf LB and tournament history; hidden when no qualifying perf rows
- **Moments** — trophy games from `amiga_player_stats` `*GameID` pointers (`amiga_player_moments_lib.php`): biggest win, goal festival, peak rating game; score links to games tab with opponent filter; hidden when no resolvable game rows; **no streak card**
- **Recent tournaments** — `amiga_player_tournament_participation`: finish from **`event_finish_position`** ordinal (all events including WC; NULL → —); **`event_points` suffix only for single-phase events** (not league+cup marathons, not WCs — see contract §5.2.1); compact **Winner** badge and **Perf** when games ≥ 2
- **Tournament history** — `/amiga/player/tournaments.php`: full participation list (no pagination); sortable table with per-event W-D-L, **F** / **A** totals, **GF/g** / **GA/g** averages, **`event_points` (Pts)**, rating before/delta/after, **Perf. rating** ([`amiga-performance-rating.md`](amiga-performance-rating.md)); labeled filter panel (Event: All / World Cups; Location: country pills when applicable) — `.k2-player-tournament-filters` in `theme.css`
- **Top opponents** — `amiga_player_matchup_summary` via `amiga_player_top_opponents()` (W-D-L, goals, games; W-D-L and games link to H2H pair page)
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` reads `amiga_rating_events` (one point per finalized tournament); [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js): **By date** = end-of-day rating after tournament days; **By tournament #** = event series (no within-tournament zigzags); toggle uses `player-feast-sections.css` segment control
- **Games tab** — server-side filters: Event pills (All / World Cups), listboxes (result, opponent, tournament, country, year, since year), sort; tournament + phase from `amiga_games` + `tournaments`; per-game frozen `rating_a/b` and `adjustment_a/b` from `amiga_game_ratings` (`new_rating_*` NULL after finalize v1); status line shows game count + **Performance rating** for the filtered set (async JSON — same chess-style rules as event TPR, read-time from `amiga_game_ratings`); no pagination (full list)

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`amiga_players` + `amiga_player_stats`** | Hero, career strip, rank | 2 queries per page (row + rank) |
| **`amiga_rating_events` + `tournaments`** | Profile rating chart JSON | 1 query per chart load (~≤1k events max per player) |
| **`amiga_games` + `amiga_game_ratings`** (via `amiga_db.php`) | Games list per-match adjustments | 1 query per games page |

**Derived tables in use:** `amiga_game_ratings`, `amiga_player_stats`, `amiga_rating_events`, `amiga_tournament_standings`, `amiga_player_tournament_participation`, `amiga_player_tournament_totals`, `amiga_player_matchup_summary`, `amiga_generalstats` (all rebuilt by `scripts/amiga/replay.py` after tournament finalize). See [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) and [`amiga-data-contract.md`](amiga-data-contract.md).

**Deferred on profile:** dedicated WC medals block, activity calendars, career strip DD/CS enrichment — see player-universe contract §4 and [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4.

### Participation points (read carefully)

| What | Where | Use on profile |
|------|--------|----------------|
| **Phase points** (league table, WC group, etc.) | `amiga_tournament_standings` per scope | Tournament page only — **not** on participation rows |
| **Event points** (`event_points`) | `amiga_player_tournament_participation` | Tournament history **Pts** column; recent block when one phase = whole event |

Participation **roster and W-D-L/goals** come from **`amiga_games`** — a row exists for every player with ≥1 game, regardless of standings shape.

**Event finish** (policy): [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md). Column **`event_finish_position`** (NULL when undefined). **Phase ranks are not stored on participation** — group/league tables live in `amiga_tournament_standings` only.

**World Cup finish** on history table: same as other events — **`event_finish_position`** ordinal (1st/2nd/3rd) or —; WC podium words derived from finish tier when needed.

## Hub navigation (v0)

- **`includes/amiga_hub_nav.php`** — segment tabs: **News** (`/amiga/news.php`, realm default), **Leaderboards** (`/amiga/leaderboards/rating.php`), **Tournaments**, **Live tournaments**, **Activity** (`/amiga/activity.php`, placeholder), **Hall of Fame**. Tournament honours is a leaderboard sub-wing only (no top-level hub tab). Included on hub-level pages only (not player profile/games). Tint picker matches online hub.
- Set `$k2AmigaHubTabActive` before include: `leaderboards` | `tournaments` | `live-tournaments` | `hall-of-fame`.

## Files

- `includes/amiga_hub_nav.php` — realm hub tabs
- `amiga/hall-of-fame.php` — HoF (generalstats + ratio leaders + WC medal panel)
- `includes/amiga_player_tournament_lib.php` — participation + totals reads
- `includes/amiga_player_matchup_lib.php` — top opponents + H2H pair reads
- `includes/amiga_lb_nav.php`, `includes/amiga_lb_lib.php` — leaderboard wing tabs + shared row helpers
- `includes/amiga_records_*.php`, `includes/amiga_records_hof_links.php` — HoF panels + metric deep links
- `amiga/h2h.php` — head-to-head pair page
- `amiga/leaderboards/*.php` — rating, goals, DDs, victims, peak, perf rating, tournament honours
- `includes/amiga_db.php` — ground + derived join helpers
- `includes/amiga_player_load.php`
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
- `amiga/profile.php`, `amiga/games.php`, `amiga/player-tournaments.php` — 302 redirects to `amiga/player/*`

## Import: player identity

`scripts/amiga/player_names.py` merges Access duplicates at import:

- Collapsed whitespace (`Gianni  T` → `Gianni T`)
- Trailing period (`Knut L.` → `Knut L`, most games wins)
- Case variants (`Oliver ST` → `Oliver St`, most games wins)
- Country from `Rankings` when any alias had one

Re-run: `python -m scripts.amiga prove`. Import audit: `data/amiga/exports/import_manifest.json` (see [`amiga-import-layer.md`](amiga-import-layer.md)).

## Rating chart timeline

Amiga `api/player_rating_history.php?realm=amiga` returns `timelineStart` = `MIN(game_date)` from `amiga_games` (first ladder game, ~Nov 2001) and `meta.granularity = event`. Online still uses June 2017 and per-game points. **By date** x-axis on Amiga profile uses `timelineStart` (month start → today), not the online server origin.

**Chart views** (`player-rating-chart.js`, shared shell with online profile):

| Toggle | Points plotted | Peak / summary |
|--------|----------------|----------------|
| **By date** | One per local day — rating after the last event that day | Career peak from event series; latest from end-of-day series (+ today) |
| **By tournament #** | Origin at tournament #0 (1600 Elo), then one point per `amiga_rating_events` row (`rating_after`) | Event index on x-axis; tooltip links to tournament page |

API returns one point per finalized tournament (not per game). Multi-game tournaments appear as a single step on the tournament # axis.

## Tournament pages (cups + leagues)

- **Index** — `/amiga/tournaments.php` — Cup/League badges, optional All/Cups/Leagues filter; cup links with knockouts jump to `#bracket`
- **Standings** — `/amiga/tournament.php?id=` — hero (name, date, country meta), section nav (League table / phase tabs / Bracket / **Event stats**); **Perf. rating** on event-stats; World Cups open on event-stats by default (`stylesheets/amiga-tournament.css`)
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
7. **Profile** — recent tournaments block; top opponents plausible for busy player; **Moments** (e.g. Oliver St `id=345` shows 26–0 goal festival); cups with knockouts link to `#bracket`; rating chart **By tournament #** has no zigzags inside multi-game events; busy player with 10+ events in one year readable on calendar axis
7b. **Tournament history** — `/amiga/player/tournaments.php?id=<busy_player>` — all events listed; **Pts** = `event_points`; WC rows show medal finish not group rank; **country** pills reduce row set (e.g. Dagh N `id=73`: 2 cups, 5 in England)
8. **Hall of Fame** — `/amiga/hall-of-fame.php` loads; record holders link to profiles; metric cells deep-link to LB wings; no streak rows
8b. **Tier A LB** — `/amiga/leaderboards/goals.php` (and siblings) sort; wing nav complete; HoF links land correctly
9. **Tournament honours LB** — `/amiga/leaderboards/tournament-honours.php` — Elo · Events · event medals/podiums · WC block; default sort Events (desc)
10. **Games tab** — adjustment column populated for finalized games (frozen rating + delta); sort by adjustment works
11. **Responsive** — tournament page usable at ~375px width (bracket stacks, nav wraps)

CLI parity: `python -m scripts.amiga standings-parity --tournament "London XXIII"`
