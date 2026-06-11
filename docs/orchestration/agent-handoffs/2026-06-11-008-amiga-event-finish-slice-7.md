# Amiga event finish — slice 7 handoff

**Date:** 2026-06-11  
**Slice:** 7 — UI read paths  
**Plan:** [`amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

---

## Goal

Profile and tournament history show `event_finish_position`; WC unchanged (medal only).

---

## Checklist

- [x] `amiga_player_tournament_lib.php` — `event_finish_position AS position` (player + event roster queries)
- [x] `amiga_profile_blocks.php` — finish labels use `position` alias; NULL-safe; comments updated
- [x] `player-tournaments.php` / event-stats — unchanged (consume lib + profile blocks)
- [x] `verify-player-participation` OK

---

## Files changed

| File | Change |
|------|--------|
| `site/public_html/includes/amiga_player_tournament_lib.php` | Read `event_finish_position` not `overall_position` |
| `site/public_html/includes/amiga_profile_blocks.php` | Comments + NULL-safe finish labels |
| `docs/amiga-profile-v0.md` | Recent tournaments wording |
| `docs/amiga-player-universe-contract.md` | Implementation status |

---

## STOP GATE C — user browser check

Please confirm locally or staging:

| URL | Expect |
|-----|--------|
| `/amiga/profile.php?id=73` | Recent list: WC rows show medal ordinal or —; **Copenhagen WC XIV not “1st”** |
| `/amiga/player-tournaments.php?id=73` | Finish column: Bournemouth II **2nd**; Copenhagen WC XIV **—** |
| Pure KO cup winner | e.g. Bournemouth I/III/IV → **1st**; runner-up **2nd** |

### SQL reference (player 73)

| Tournament | legacy `overall_position` | UI finish (`event_finish_position` / medal) |
|------------|---------------------------|---------------------------------------------|
| World Cup XIV (Copenhagen) | 1 (group) | **—** (no medal, NULL finish) |
| Bournemouth II | 2 | **2nd** |
| Bournemouth I | 1 | **1st** |
| World Cup XXIII (Milan) | 2 | **1st** (gold medal) |

**Wait for OK** before slice 8 (`overall_position` drop).

---

## Next slice

**Slice 8** — migration `018_drop_overall_position.sql`; remove all writer/reader references; full verify suite.
