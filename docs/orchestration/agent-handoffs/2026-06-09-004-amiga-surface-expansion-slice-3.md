# Amiga surface expansion — slice 3 handoff

**Date:** 2026-06-09  
**Slice:** 3 — Top opponents goals + H2H pair page  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

## Goal

Complete the H2H read path: richer top opponents on profile + realm-internal pair page.

## Checklist

- [x] Top opponents table: Goals (GF – GA) from `amiga_player_matchup_summary`
- [x] `/amiga/h2h.php?id1=&id2=` — directed row for id1 vs id2 + reverse summary for id2 vs id1
- [x] 404 when either ID missing or id1 === id2
- [x] Link to `games.php?id=&opponent=` (existing filter, no new aggregation)
- [x] Profile top opponents: W-D-L and Games cells link to H2H page
- [x] `python -m scripts.amiga verify-player-matchups` — pass

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_player_matchup_lib.php` | Pair row fetch, identity, H2H URL helpers |
| `site/public_html/includes/amiga_profile_blocks.php` | Goals column + H2H links |
| `site/public_html/amiga/profile.php` | Pass `$playerId` to top opponents render |
| `site/public_html/amiga/h2h.php` | New pair page |
| `docs/amiga-profile-v0.md`, `PROJECT_MEMORY.md` | Routes + top opponents copy |

## Verification

```
python -m scripts.amiga verify-player-matchups
OK: player matchups verified (14024 directed pairs, SUM(games)=54836 = 2×27418)
```

**Top pair (ko2amiga_db):** Garry C (134) vs Steve E (422) — 176 games, 56-36-84, 565–688 goals (Garry’s view).  
Browser: `/amiga/h2h.php?id1=134&id2=422`

## STOP GATE B — user browser checks

1. **Profile top opponents** — e.g. `/amiga/profile.php?id=134`: Goals column populated; click W-D-L or Games → H2H page.
2. **H2H page** — `/amiga/h2h.php?id1=134&id2=422`: both directed blocks match summary; games links work.
3. **Invalid pair** — `/amiga/h2h.php?id1=1&id2=1` or unknown ID → 404.

## Next slice

Slice 4 — Tournament page event stats from participation. **STOP GATE C** after slice 4.
