# Amiga tournament medals unification v2 — slice 5 handoff

**Date:** 2026-06-13  
**Slice:** 5 — PHP read paths (profile + tournaments)  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Profile and player tournament history display holistic finish from `event_finish_position` (ordinals for all events including WC). Career honours strip reads v2 totals columns.

---

## Checklist

- [x] Unified finish helpers in `amiga_profile_blocks.php` (`amiga_profile_row_event_finish`, `amiga_profile_event_finish_ordinal_label`)
- [x] Removed `wc_medal` as primary finish source in recent-tournament + history table labels
- [x] `amiga_tournament_wc_medal_label()` — derives Gold/Silver/Bronze from finish tier; `wc_medal` fallback until slice 6
- [x] `amiga_player_tournament_totals_row()` — v2 column set (`event_*`, `wc_*`, `event_podiums`, `wc_podiums`)
- [x] Honours strip — `event_podiums` (was `podiums`)
- [x] `amiga-tournament-honours-rules.md` §6 updated

---

## STOP GATE B — results

### Profile — Alkis P (id=14)

URL: `http://ratingskickoff.test/amiga/player/profile.php?id=14`

**Honours block:**
- WC medals: 2 gold · 2 silver · 4 bronze
- Tournaments won: **58**
- Podiums: **85**

**Recent tournaments:** WC rows without podium show `—`; league wins show `1st`/`2nd` ordinals.

### Player tournaments — World Cup filter

URL: `http://ratingskickoff.test/amiga/player/tournaments.php?id=14&filter=world-cup`

**Finish column (sample):**

| Tournament | Finish |
|------------|--------|
| World Cup XIV (Copenhagen) | 3rd |
| World Cup XIII (Voitsberg) | 1st |
| World Cup XI (Birmingham) | (2nd in DB) |
| Non-podium WCs | — |

Ordinals from `event_finish_position`, not medal words alone.

---

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_profile_blocks.php` | Finish helpers; honours strip; unified ordinal labels |
| `site/public_html/includes/amiga_player_tournament_lib.php` | `amiga_player_tournament_totals_row()` v2 SELECT |
| `docs/amiga-tournament-honours-rules.md` | §6 UI read rules |
| `docs/amiga-tournament-medals-unification-implementation-plan.md` | Slice 5 checked |
| `PROJECT_MEMORY.md` | Recent log |

**Not in slice 5:** `tournament-honours.php` leaderboard (slice 7 — still references `podiums`).

---

## Awaiting user OK

Per plan **STOP GATE B** — confirm browser results before slice 6 (drop `wc_medal` column).

**Next after OK:** Slice 6 — DDL `022`, remove `wc_medal` from writers/readers.
