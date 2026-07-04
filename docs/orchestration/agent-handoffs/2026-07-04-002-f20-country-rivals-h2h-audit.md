# F20 chrome audit — Country rivals H2H (handoff)

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline · carry-scroll / hash landing  
**Failure targeted:** **F20** — TT flag / roster / entity nav header flash before content (y ≠ 0)  
**Status:** Query perf slices done (roster snappy); **F20 chrome audit pending**  
**Dagh note:** May refer to this as **T20 audit** — same as **F20** in [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md).

---

## Goal

Audit **F20 remaining chrome** (not query overfetch) on Country **Rivals → H2H** in time travel — mid-scroll and scroll-top, with `as=` on URL. Confirm whether header-only flash / blank still occurs after roster fetch fixes; identify carry-scroll vs hash vs streaming causes.

**Test URL (local):**

`http://ratingskickoff.test/amiga/country/rivals/h2h.php?country=England&rival=Italy&pick=games&as=event%3A22`

Also try late cutoff: `as=event:589`, `as=month:2025-09`.

---

## Context (read first)

| Doc | Why |
|-----|-----|
| [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md) § **F20** | Symptom register + smoke **S11** |
| [`tt-chrome-baseline-f6-attempt-log.md`](../tt-chrome-baseline-f6-attempt-log.md) § Type A/B/C + roster audit | Blank mechanisms; roster query fix timings |
| [`amiga-country-rivals-policy.md`](../amiga-country-rivals-policy.md) | Rivals surface |

**Recent perf (2026-07-04):** Roster path optimized — `amiga_countries_query_roster_rows()`, scoped elo attach. Dagh sign-off: roster snappy in TT. **F20 chrome may still remain** (hash `#k2-country-roster`, carry cloak, PHP stream order).

---

## Page / code touchpoints

| Layer | Path |
|-------|------|
| Entry | `site/public_html/amiga/country/rivals/h2h.php` → `includes/amiga_country_page.php` |
| Rivals view | `$k2AmigaCountryView = 'rivals'` — hero via `amiga_countries_query_country_summary()`; H2H panel via `amiga_country_rivals_render_h2h_panel()` |
| H2H load | `includes/amiga_country_rivals_h2h.php`, `amiga_country_rivals_h2h_games_lib.php` |
| Carry / hash | `includes/k2_carry_scroll_restore.php`, `js/k2-carry-scroll.js` |
| Flag / roster links | `includes/k2_amiga_country_flag.php` — roster href includes `#k2-country-roster` (roster only; rivals may differ) |

**Page order:** `site_header` (TT snapshot) → hub nav → **DB block** (summary + H2H queries + charts assets in head) → hero → country nav → rivals panel.

---

## Audit tasks

1. **Manual repro** — S11 extended: TT rating LB or Countries, mid-scroll (`y > 0`) → navigate to rivals H2H URL above. Repeat at `scrollY ≈ 0`. Compare Present (no `as=`).
2. **Classify blank** — Type A (body cloak), Type B (streaming gap after ribbon), Type C (table/chart cloak only) per attempt log framework.
3. **Query audit** — Time each phase on hot path: `amiga_countries_query_country_summary`, `amiga_country_rivals_h2h_played_rivals`, `amiga_country_rivals_h2h_games_rows` / chart payloads at early vs late cutoff. Look for global overfetch (same class as pre-fix index/roster).
4. **Carry / hash** — Does rivals nav store carry? URL hash on this route? Does `carryReady()` / 700 ms reveal fire before hero + H2H panel exist?
5. **Recommend slice** — Query-only vs PHP `flush()` after hero shell vs hash deferral vs carry reveal gate. **Do not** remove realm/hub carry-scroll.

---

## Deliverables

- Timings table (local `ko2amiga_db` or staging) in attempt log or short addendum to this handoff
- Update F20 row in invariants if symptom changed after roster fix
- Optional: `scripts/oneoff/amiga_country_rivals_h2h_audit_probe.php` if useful
- **No fix required in audit slice** unless Dagh asks — audit + recommendation only

---

## Out of scope

- **F6** (TT ribbon nav blank at y=0) — separate slice (iter 3b PHP flush on rating LB / hub pages)
- Stored-truth / new DB tables