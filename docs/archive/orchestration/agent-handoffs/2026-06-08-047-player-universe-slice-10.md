# Slice 10 — Profile top opponents

**Date:** 2026-06-08  
**Plan:** [`amiga-player-universe-implementation-plan.md`](../../amiga-player-universe-implementation-plan.md) § Slice 10

## Goal

Amiga profile shows most-played opponents from `amiga_player_matchup_summary` (name, W-D-L, games, profile links).

## Done

- [x] `includes/amiga_player_matchup_lib.php` — `amiga_player_top_opponents($con, $playerId, $limit = 10)`
- [x] `amiga_profile_render_top_opponents()` in `amiga_profile_blocks.php` — table block
- [x] Wired in `/amiga/profile.php` (after recent tournaments, before rating chart)
- [x] `amiga-data-contract.md` read-path note

## Files changed

- `site/public_html/includes/amiga_player_matchup_lib.php` (new)
- `site/public_html/includes/amiga_profile_blocks.php`
- `site/public_html/amiga/profile.php`
- `docs/amiga-data-contract.md`

## Verification — **STOP GATE E**

**User checkpoint:** open a busy player profile and confirm top opponents look plausible.

Suggested URL (local): `/amiga/profile.php?id=386` — ~1520 rated games across opponents; top row should be highest `games` count with W-D-L summing to that games column.

| Agent check | Result |
|-------------|--------|
| SQL spot-check player 386 top 3 opponents | query returns rows ordered by `games` DESC |
| Block hidden when summary empty | `amiga_profile_render_top_opponents` returns early on `[]` |

## Known limitations

- No H2H pair page or chart (future slice / API).
- `api/player_top_opponents.php` still online-only (`realm_not_implemented` for amiga).
- Sorted by games only (not wins).

## Next slice hint

**Slice 11:** `amiga_generalstats` schema + `server_records.py` port.
