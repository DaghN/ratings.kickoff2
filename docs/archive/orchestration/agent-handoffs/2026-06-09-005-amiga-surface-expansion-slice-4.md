# Amiga surface expansion — slice 4 handoff

**Date:** 2026-06-09  
**Slice:** 4 — Tournament page event stats  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Add participation-backed per-player event stats on `/amiga/tournament.php` without scanning games on load.

## Checklist

- [x] `amiga_tournament_participation_rows()` in `amiga_player_tournament_lib.php`
- [x] Event stats nav tab + `view=event-stats` on tournament page
- [x] Table: W-D-L, F/A, GF/g, GA/g, Pts, rating columns, Perf. rating (tooltips match player-tournaments)
- [x] Player names from `amiga_players`; public lifecycle filter via `amiga_tournament_public_visibility_where`
- [x] WC events: **Medal** column (podium); non-WC: **Finish** (not group rank on WCs)
- [x] Full verify suite — pass
- [x] Parity spot-check: Athens XCI (id=22) top player Christopher D matches participation row

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_player_tournament_lib.php` | `amiga_tournament_participation_rows()` |
| `site/public_html/includes/amiga_tournament_lib.php` | `amiga_tournament_event_stats_url()` |
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_tournament_render_event_stats_table()` |
| `site/public_html/amiga/tournament.php` | Event stats tab + view routing |
| `docs/amiga-profile-v0.md`, `docs/amiga-performance-rating.md`, `PROJECT_MEMORY.md` | Routes / read paths |

## Verification

```
verify-chronology, verify-rating-events, verify-player-participation, verify-player-matchups — all OK
```

**Parity (tournament_id=22, player_id=66):** 13 games, 12-1-0, 87–36, 37 pts, perf 2639 — same grain as `player-tournaments.php` row for that event.

## STOP GATE C — user browser checks

1. **League+cup marathon** — `/amiga/tournament.php?id=22&view=event-stats` (Athens XCI): roster sorts; compare one player to their row on `/amiga/player-tournaments.php?id=66`.
2. **World Cup** — `/amiga/tournament.php?id=66&view=event-stats` (World Cup II): **Medal** column shows Gold/Silver/Bronze/—; no misleading group finish.
3. **Knockout cup** — pick a cup-only event from tournaments index; Event stats tab loads.

## Next slice

Slice 5 — Perf rating profile highlight + best-event LB. **STOP GATE D** after slice 5.
