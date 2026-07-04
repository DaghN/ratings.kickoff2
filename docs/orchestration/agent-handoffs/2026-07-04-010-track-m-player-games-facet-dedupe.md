# Handoff 2026-07-04-010 — Track M player games facet dedupe

**Status:** Done · **Fixture:** player **382** · **Method:** request cache + validate/load dedupe

---

## Problem

After Track J pagination, facet stack still ~550 ms SQL per request (validate 8 scans + load 12 scans, many duplicated on unfiltered load).

## Shipped

| Change | File |
|--------|------|
| Career-wide facet bundle (cached) shared by validate + unfiltered load | `amiga_player_games_filter_facets.php` |
| Single year histogram → derive year/since/until when all idle | same |
| Single-pass gf/ga/gs/gd scan when numerics idle | same |
| `validate_filters_career_wide` reads bundle instead of 8 duplicate queries | same |

## Probe (lib ms, player 382)

| Cutoff | validate + load (before ~548 ms) | After |
|--------|----------------------------------|-------|
| present | ~178 ms | load **0 ms** (cache hit) |
| year:2024 | ~210 ms | load **0 ms** |
| month:2014-07 | ~193 ms | load **0 ms** |

**Curl** (`amiga_player_games_pagination_probe.php` id=382 @ year:2024): best **0.531 s** (was census **1.09 s**, post-J ~0.9–1.5 s).

## Files

- `site/public_html/includes/amiga_player_games_filter_facets.php`
- `scripts/oneoff/amiga_player_games_tt_probe.php` (validate + load timings)