# Amiga player profile (v0)

**Status:** minimal template (Jun 2026). Extend deliberately — see data notes below.

## URLs

| Page | Path |
|------|------|
| Leaderboard | `/amiga/rating.php` |
| Profile | `/amiga/profile.php?id={amiga_players.id}` |
| Games | `/amiga/games.php?id={amiga_players.id}` |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): rank, rating, games, country line
- **Player nav** — Profile · Games (`amiga_player_nav.php`)
- **Career strip** — `amiga_players` + `amiga_player_stats` (W/D/L, goals, peak, opp avg)
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` (chronological games via `amiga_db.php` join)
- **Games tab** — server-side filters (result, opponent), sort, 100-row pages; tournament + phase from `amiga_games` + `tournaments`, ratings from `amiga_game_ratings`

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`amiga_players` + `amiga_player_stats`** | Hero, career strip, rank | 2 queries per page (row + rank) |
| **`amiga_games` + `amiga_game_ratings`** (via `amiga_db.php`) | Games list, rating history JSON | 1 query per chart load (~≤1k rows max per player) |

**A2 derived tables in use:** `amiga_game_ratings`, `amiga_player_stats` (rebuilt by `scripts/amiga/replay.py`). **Not yet:** `player_period_games`, milestones, calendars, H2H aggregates, tournament standings.

That is fine at current scale (27k games total; busiest player ~1.1k games). When profiles grow (activity calendars, top opponents, tournament honours), materialize hot paths per [`amiga-data-contract.md`](amiga-data-contract.md) — same pattern as online `website-data-contract.md`, not live scans on every request.

## Files

- `includes/amiga_db.php` — ground + derived join helpers
- `includes/amiga_player_load.php`
- `includes/amiga_player_hero.php`
- `includes/amiga_player_nav.php`
- `includes/amiga_profile_blocks.php`
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

- H2H, milestones, tournament index pages, per-game detail page, compare chart
