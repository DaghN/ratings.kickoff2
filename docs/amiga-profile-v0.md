# Amiga player profile (v0)

**Status:** minimal template (Jun 2026). Extend deliberately — see data notes below.

## URLs

| Page | Path |
|------|------|
| Leaderboard | `/amiga/rating.php` |
| Profile | `/amiga/profile.php?id={playertable.ID}` |

## What v0 shows

- **Hero** — same feast shell as online (`amiga_player_hero.php`): rank, rating, games, country line
- **Career strip** — single `playertable` read (W/D/L, goals, peak, opp avg)
- **Rating chart** — `api/player_rating_history.php?realm=amiga&id=` (scans that player’s games in `ratedresults`)

## Data strategy (important)

| Source | Used for | v0 cost |
|--------|----------|---------|
| **`playertable`** (replay) | Hero, career strip, rank | 2 queries per page (row + rank) |
| **`ratedresults`** (per game) | Rating history JSON | 1 query per chart load (~≤1k rows max per player) |

**No Amiga derived tables yet** — no `player_period_games`, milestones, calendars, H2H aggregates.

That is fine at current scale (27k games total; busiest player ~1.1k games). When profiles grow (activity calendars, top opponents, tournament honours), add an **`amiga-data-contract.md`** and materialize hot paths — same pattern as online `website-data-contract.md`, not live scans on every request.

## Files

- `includes/amiga_player_load.php`
- `includes/amiga_player_hero.php`
- `includes/amiga_profile_blocks.php`
- `amiga/profile.php`

## Import: player identity

`scripts/amiga/player_names.py` merges Access duplicates at import:

- Collapsed whitespace (`Gianni  T` → `Gianni T`)
- Trailing period (`Knut L.` → `Knut L`, most games wins)
- Case variants (`Oliver ST` → `Oliver St`, most games wins)
- Country from `Rankings` when any alias had one

Re-run: `python -m scripts.amiga run --recreate-schema`. Merge log: `data/amiga/exports/name_merges.json`.

## Rating chart timeline

Amiga `api/player_rating_history.php?realm=amiga` returns `timelineStart` = `MIN(Date)` from `ratedresults` (first ladder game, ~Nov 2001). Online still uses June 2017.

## Not in v0

- Game list tab, H2H, milestones, tournament pages, compare chart
