# Amiga player profile (v0)

**Status:** minimal template (Jun 2026). Extend deliberately — see data notes below.

## URLs

| Page | Path |
|------|------|
| Hub nav | Ladder · Tournaments · Hall of Fame (`includes/amiga_hub_nav.php`) |
| Leaderboard | `/amiga/rating.php` (Ladder tab) |
| Profile | `/amiga/profile.php?id={amiga_players.id}` |
| Games | `/amiga/games.php?id={amiga_players.id}` |
| Tournament index | `/amiga/tournaments.php` |
| Tournament standings | `/amiga/tournament.php?id={tournaments.id}` |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): rank, rating, games, country line
- **Player nav** — Profile · Games (`amiga_player_nav.php`)
- **Career strip** — `amiga_players` + `amiga_player_stats` (W/D/L, goals, peak, opp avg)
- **Recent tournaments** — `amiga_tournament_standings` overall scope (position, pts) with link to tournament page
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` reads `amiga_rating_events` (one point per finalized tournament); [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js): **By date** = end-of-day rating after tournament days; **By tournament #** = event series (no within-tournament zigzags)
- **Games tab** — server-side filters (result, opponent), sort, 100-row pages; tournament + phase from `amiga_games` + `tournaments`; per-game frozen `rating_a/b` and `adjustment_a/b` from `amiga_game_ratings` (`new_rating_*` NULL after finalize v1)

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`amiga_players` + `amiga_player_stats`** | Hero, career strip, rank | 2 queries per page (row + rank) |
| **`amiga_rating_events` + `tournaments`** | Profile rating chart JSON | 1 query per chart load (~≤1k events max per player) |
| **`amiga_games` + `amiga_game_ratings`** (via `amiga_db.php`) | Games list per-match adjustments | 1 query per games page |

**A2 derived tables in use:** `amiga_game_ratings`, `amiga_player_stats`, `amiga_rating_events`, `amiga_tournament_standings` (rebuilt by `scripts/amiga/replay.py` tournament finalize). **Not yet:** `player_period_games`, milestones, calendars, H2H aggregates.

That is fine at current scale (27k games total; busiest player ~1.1k games). When profiles grow (activity calendars, top opponents, tournament honours), materialize hot paths per [`amiga-data-contract.md`](amiga-data-contract.md) — same pattern as online `website-data-contract.md`, not live scans on every request.

## Hub navigation (v0)

- **`includes/amiga_hub_nav.php`** — segment tabs: **Ladder** (`/amiga/rating.php`), **Tournaments** (`/amiga/tournaments.php`), **Hall of Fame** (`/amiga/hall-of-fame.php` stub). Included on hub-level pages only (not player profile/games). Tint picker matches online hub.
- Set `$k2AmigaHubTabActive` before include: `ladder` | `tournaments` | `hall-of-fame`.

## Files

- `includes/amiga_hub_nav.php` — realm hub tabs
- `amiga/hall-of-fame.php` — HoF placeholder (content Phase B)
- `includes/amiga_db.php` — ground + derived join helpers
- `includes/amiga_player_load.php`
- `includes/amiga_player_hero.php`
- `includes/amiga_player_nav.php`
- `includes/amiga_profile_blocks.php`
- `includes/amiga_tournament_lib.php`
- `includes/amiga_tournament_bracket.php`
- `stylesheets/amiga-tournament.css`
- `amiga/tournament.php`
- `amiga/tournaments.php`
- `includes/amiga_player_games_lib.php`
- `includes/amiga_player_game_row.php`
- `amiga/profile.php`
- `amiga/games.php`

## Import: player identity

`scripts/amiga/player_names.py` merges Access duplicates at import:

- Collapsed whitespace (`Gianni  T` → `Gianni T`)
- Trailing period (`Knut L.` → `Knut L`, most games wins)
- Case variants (`Oliver ST` → `Oliver St`, most games wins)
- Country from `Rankings` when any alias had one

Re-run: `python -m scripts.amiga run --recreate-schema`. Import audit: `data/amiga/exports/import_manifest.json` (see [`amiga-import-layer.md`](amiga-import-layer.md)).

## Rating chart timeline

Amiga `api/player_rating_history.php?realm=amiga` returns `timelineStart` = `MIN(game_date)` from `amiga_games` (first ladder game, ~Nov 2001) and `meta.granularity = event`. Online still uses June 2017 and per-game points.

**Chart views** (`player-rating-chart.js`, shared shell with online profile):

| Toggle | Points plotted | Peak / summary |
|--------|----------------|----------------|
| **By date** | One per local day — rating after the last event that day | Career peak from event series; latest from end-of-day series (+ today) |
| **By tournament #** | One point per `amiga_rating_events` row (`rating_after`) | Event index on x-axis; tooltip links to tournament page |

API returns one point per finalized tournament (not per game). Multi-game tournaments appear as a single step on the tournament # axis.

## Tournament pages (cups + leagues)

- **Index** — `/amiga/tournaments.php` — Cup/League badges, optional All/Cups/Leagues filter; cup links with knockouts jump to `#bracket`
- **Standings** — `/amiga/tournament.php?id=` — hero (name, date, format badge), section nav (Overall / groups / Bracket), knockout bracket for cup events (`stylesheets/amiga-tournament.css`)
- **Bracket** — phase-grouped columns (Quarter → Semi → Final; placement finals/brackets below); click aggregate score for leg-by-leg tie detail (`scope=knockout&scope_key=…`); `extra` penalties on leg rows
- **League marathons** — e.g. London XXIII: overall table only, no empty bracket shell

## Not in v0

- H2H, milestones, per-game detail page, compare chart, full bracket advancement tree (cross-stage promotion)
- **Match streaks** — no leaderboard wing, HoF rows, or profile moments. Real within-day play order is unknown; synthetic `game_date` chronology is not valid for consecutive-result streaks. See [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks.

## Browser QA checklist (standings + bracket)

After `python -m scripts.amiga replay`, spot-check locally:

1. **Index** — `/amiga/tournaments.php` — Cup/League badges distinct; filter tabs work; cup links open standings (knockout cups → `#bracket`)
2. **League table** — find London XXIII — top 3: Dagh N (69), Gianni T (65), Sandro T (60); Cup badge absent; no bracket section
3. **Cup group** — World Cup XI → Group A tab — winner Alkis P (45 pts)
4. **Knockout bracket** — World Cup XI — bracket shows semi winners Gianni T over Lorenzo C (10–6) and Alkis P over Andy G; columns scroll on desktop, stack ~375px
5. **Knockout fixture** — click semi score → leg table; Gianni T / Lorenzo C (`scope_key=Semi Finals|149-253`) — 2 legs, winner Gianni T (10–6). Penalties: open a tied placement tie (e.g. `Places 17-24|445-467`) — leg row shows `extra` text on the score line (e.g. `(4-4) 5-4 p.k.`)
6. **Games links** — busy player games tab — tournament name → overall/group; phase → group or knockout scope
7. **Profile** — recent tournaments block; cups with knockouts link to `#bracket`; rating chart **By tournament #** has no zigzags inside multi-game events; busy player with 10+ events in one year readable on calendar axis
8. **Games tab** — adjustment column populated for finalized games (frozen rating + delta); sort by adjustment works
9. **Responsive** — tournament page usable at ~375px width (bracket stacks, nav wraps)

CLI parity: `python -m scripts.amiga standings-parity --tournament "London XXIII"`
