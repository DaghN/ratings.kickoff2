# Amiga tournament medals unification v2 — slice 6 handoff

**Date:** 2026-06-13  
**Slice:** 6 — Drop `wc_medal` column  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Remove duplicate `wc_medal` authority from `amiga_player_tournament_participation`. WC podium display and totals derive from `event_finish_position` only.

---

## Checklist

- [x] Migration `022_drop_wc_medal.sql` applied locally; fresh `010` updated
- [x] Python: removed `wc_medal` from INSERT; dropped `refresh_wc_medals` / `derive_wc_medal` paths
- [x] `compute_wc_podium_finish_from_standings()` replaces medal-enum derivation (Tier D)
- [x] PHP: participation INSERT + WC supplement without `wc_medal`; removed `amiga_ops_participation_refresh_wc_medals_for_tournament`
- [x] PHP reads: `amiga_player_tournament_lib`, `amiga_profile_wc_podium_word()` (finish-only)
- [x] Verify: WC non-podium finish check replaces wc_medal/finish parity
- [x] `rg "wc_medal" --glob "*.{py,php}"` — zero hits

---

## Verification

```
python -m scripts.amiga participation-rebuild
→ participation=4517 totals=473

python -m scripts.amiga verify-player-participation
→ OK

python -m unittest scripts.amiga.test_tournament_honours scripts.amiga.test_participation_placement scripts.amiga.test_player_tournament_participation
→ 49 tests OK

Alkis P (id=14): event_gold=58 wc_gold=2 event_podiums=85 wc_podiums=8 tournaments_won=58
```

---

## Files changed

| File | Change |
|------|--------|
| `scripts/amiga/sql/022_drop_wc_medal.sql` | **New** — DROP COLUMN |
| `scripts/amiga/sql/010_player_tournament_participation.sql` | Fresh install without `wc_medal` |
| `scripts/amiga/tournament_honours.py` | `compute_wc_podium_finish_from_standings`; writer paths removed |
| `scripts/amiga/participation_placement.py` | Tier D uses podium finish helper; `participation_is_winner` simplified |
| `scripts/amiga/player_tournament_participation.py` | INSERT without `wc_medal`; no post-rebuild medal refresh |
| `scripts/amiga/verify_player_participation.py` | WC finish-only invariant |
| `site/public_html/includes/amiga_participation_placement.php` | Podium finish helper + `amiga_participation_wc_podium_word_from_finish` |
| `site/public_html/amiga/ops/includes/amiga_post_game_participation.php` | Writers without `wc_medal` |
| `site/public_html/includes/amiga_profile_blocks.php` | `amiga_profile_wc_podium_word`; career WC honours label rename |
| `site/public_html/includes/amiga_player_tournament_lib.php` | SELECT without `wc_medal` |
| `site/public_html/includes/amiga_records_ratio_leaders.php` | `amiga_records_wc_totals_leaders` rename |
| `site/public_html/amiga/hall-of-fame.php` | Call site rename |
| Tests | honours / placement / participation unit tests updated |

**Not in slice 6:** `tournament-honours.php` leaderboard (slice 7 — STOP GATE C).

---

## Next

**Slice 7** — tournament honours LB (Elo, event block, WC block, medal SVG headers). Say **continue** or **do slice 7** when ready.
