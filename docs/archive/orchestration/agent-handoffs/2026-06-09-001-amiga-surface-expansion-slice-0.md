# Amiga surface expansion — slice 0 handoff

**Date:** 2026-06-09  
**Slice:** 0 — Profile honours strip  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Surface career tournament honours on `/amiga/profile.php` from `amiga_player_tournament_totals` (row already loaded on profile).

## Checklist

- [x] Render honours block: WC medals (if any), `tournaments_won`, `podiums`; optional `last_event_date` line
- [x] Reuse existing load in `profile.php` — `amiga_profile_render_honours()` in `amiga_profile_blocks.php`
- [x] Link to `/amiga/leaderboards/tournament-honours.php` and WC-filtered `player-tournaments.php?filter=world-cup` when player has WC medals
- [x] Match career strip spacing/typography (`k2-panel-heading`, `k2-amiga-profile-dl` grid)
- [x] `python -m scripts.amiga verify-player-participation` — pass

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_profile_render_honours()`, visibility + WC label helpers |
| `site/public_html/amiga/profile.php` | Call honours render after career strip |
| `docs/amiga-profile-v0.md` | Document honours strip; remove WC medals from deferred list |
| `PROJECT_MEMORY.md` | Session log line |

## Verification

```
python -m scripts.amiga verify-player-participation
OK: player participation verified (4517 participation rows, 473 player totals)
```

## Browser checks (manual — slice 0 has no STOP gate; re-check at STOP A)

| Case | Suggested URL | Expect |
|------|---------------|--------|
| WC medals + wins | `/amiga/profile.php?id=149` (Gianni T) | Honours section: WC medals line, 43 won, 69 podiums, WC history link |
| WC medals | `/amiga/profile.php?id=73` (Dagh N) | WC medals `4 gold · 1 silver · 1 bronze`, wins/podiums, both links |
| Wins, no WC | `/amiga/profile.php?id=21` (Andreas Kl) | Honours without WC line; no WC history link |
| No honours | Player with `tournaments_played > 0` but 0 WC/wins/podiums | Section omitted (recent tournaments still shows) |

## Behaviour notes

- Strip shows only when `tournaments_played ≥ 1` **and** at least one of: WC medal count, `tournaments_won`, `podiums` is &gt; 0.
- Cup medal columns (`cup_gold` etc.) deferred to slice 7 honours LB polish — not on profile strip in slice 0.
- No extra DB query; uses `amiga_player_tournament_totals_row()` already called in `profile.php`.

## Next slice

Slice 1 — Tier A LB wings (Goals, DDs, Victims, Peak) + `amiga_lb_nav`. **STOP GATE A** after slice 1.
