# Amiga surface expansion — slice 7 handoff

**Date:** 2026-06-10  
**Slice:** 7 — Honours, filters, recent tournaments polish  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Close overview §3.7–§3.9: honours LB cup/podium columns, tournament history filters, light recent-tournament enrich.

## Checklist

- [x] Honours LB: `podiums` + `cup_gold` / `cup_silver` / `cup_bronze` columns (sortable)
- [x] `player-tournaments.php`: All / World Cups / **Cups** pills + **country** location row
- [x] `amiga_player_tournament_participation_filter_events()` — `cups` + optional `country`
- [x] Recent tournaments: **Winner** + **Perf NNN** suffix (games ≥ 2); event_points policy unchanged
- [x] `verify-player-participation` — pass

## Files changed

| File | Change |
|------|--------|
| `site/public_html/amiga/leaderboards/tournament-honours.php` | Cup medal + podiums columns |
| `site/public_html/includes/amiga_player_tournament_lib.php` | Filters, countries helper, URL builder, honours SQL |
| `site/public_html/amiga/player-tournaments.php` | Filter UI (type + country) |
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_profile_recent_tournament_extras()` |
| `site/public_html/includes/amiga_tournament_lib.php` | `amiga_player_all_tournaments()` country param |
| `docs/amiga-profile-v0.md`, `PROJECT_MEMORY.md` | Read paths |

## Verification

```
python -m scripts.amiga verify-player-participation
OK: player participation verified (4517 participation rows, 473 player totals)
```

**Honours LB podiums leaders:** Alkis P 95, Steve E 76, Gianni T 69.

**Dagh N (`id=73`) filters:** 2 cup events; 5 events in England.

## STOP GATE F — user browser checks

1. **Honours LB** — `/amiga/leaderboards/tournament-honours.php`: cup columns + podiums visible; click **Podiums** header — Alkis P on top.
2. **History filters** — `/amiga/player-tournaments.php?id=73`: **Cups** → 2 rows; **England** → 5 rows; combine Cups + country.
3. **Recent tournaments** — busy profile: lines show **Winner** / **Perf** where applicable; league+cup marathons still omit pts suffix.

## Next slice

Slice 8 — documentation closure + full verify suite.
