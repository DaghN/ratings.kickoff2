# Amiga player profile (v0)

**Status:** minimal template (Jun 2026). Extend deliberately — see data notes below.

## URLs

| Page | Path |
|------|------|
| Leaderboard | `/amiga/rating.php` |
| Profile | `/amiga/profile.php?id={amiga_players.id}` |
| Games | `/amiga/games.php?id={amiga_players.id}` |
| Tournament index | `/amiga/tournaments.php` |
| Tournament standings | `/amiga/tournament.php?id={tournaments.id}` |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): rank, rating, games, country line
- **Player nav** — Profile · Games (`amiga_player_nav.php`)
- **Career strip** — `amiga_players` + `amiga_player_stats` (W/D/L, goals, peak, opp avg)
- **Recent tournaments** — `amiga_tournament_standings` overall scope (position, pts) with link to tournament page
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` (chronological games via `amiga_db.php` join)
- **Games tab** — server-side filters (result, opponent), sort, 100-row pages; tournament + phase from `amiga_games` + `tournaments`, ratings from `amiga_game_ratings`

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`amiga_players` + `amiga_player_stats`** | Hero, career strip, rank | 2 queries per page (row + rank) |
| **`amiga_games` + `amiga_game_ratings`** (via `amiga_db.php`) | Games list, rating history JSON | 1 query per chart load (~≤1k rows max per player) |

**A2 derived tables in use:** `amiga_game_ratings`, `amiga_player_stats`, `amiga_tournament_standings` (rebuilt by `scripts/amiga/replay.py`). **Not yet:** `player_period_games`, milestones, calendars, H2H aggregates.

That is fine at current scale (27k games total; busiest player ~1.1k games). When profiles grow (activity calendars, top opponents, tournament honours), materialize hot paths per [`amiga-data-contract.md`](amiga-data-contract.md) — same pattern as online `website-data-contract.md`, not live scans on every request.

## Files

- `includes/amiga_db.php` — ground + derived join helpers
- `includes/amiga_player_load.php`
- `includes/amiga_player_hero.php`
- `includes/amiga_player_nav.php`
- `includes/amiga_profile_blocks.php`
- `includes/amiga_tournament_lib.php`
- `amiga/tournament.php`
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

Re-run: `python -m scripts.amiga run --recreate-schema`. Merge log: `data/amiga/exports/name_merges.json`.

## Rating chart timeline

Amiga `api/player_rating_history.php?realm=amiga` returns `timelineStart` = `MIN(game_date)` from `amiga_games` (first ladder game, ~Nov 2001). Online still uses June 2017.

## Not in v0

- H2H, milestones, per-game detail page, compare chart, knockout brackets

## Browser QA checklist (standings)

After `python -m scripts.amiga replay`, spot-check locally:

1. **Index** — `/amiga/tournaments.php` — events list, game counts, links open standings
2. **League table** — find London XXIII — top 3: Dagh N (69), Gianni T (65), Sandro T (60)
3. **Cup group** — World Cup XI → Group A tab — winner Alkis P (45 pts)
4. **Games links** — busy player games tab — tournament name → overall table; phase → group scope
5. **Profile** — recent tournaments block links match index; positions look plausible
6. **Empty scope** — click a knockout phase link (e.g. Final) — empty table + hint (expected v1)

CLI parity: `python -m scripts.amiga standings-parity --tournament "London XXIII"`
