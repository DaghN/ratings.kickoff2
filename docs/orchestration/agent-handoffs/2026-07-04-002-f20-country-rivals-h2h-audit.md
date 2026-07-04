# F20 chrome audit — Country rivals H2H (handoff)

**Date:** 2026-07-04  
**Track:** Amiga TT chrome baseline · carry-scroll / hash landing  
**Failure targeted:** **F20** — TT flag / roster / entity nav header flash before content (y ≠ 0)  
**Status:** **Audit complete 2026-07-04** — query debt + chrome classified; no fix shipped (audit-only)  
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

## Audit results (2026-07-04)

**Probe:** `scripts/oneoff/amiga_country_rivals_h2h_audit_probe.php` · England vs Italy · local `ko2amiga_db`

### Query timings (ms)

| Phase | Present | `event:22` | `event:589` | `month:2025-09` |
|-------|---------|------------|-------------|-----------------|
| `country_summary` | 2.7 | 171 | 130 | 154 |
| `rivals_rows` (played + perf batch) | 536 | **1421** | 869 | **1509** |
| `rivals_bucket` (**dup** full `rivals_rows`) | 575 | 1321 | 850 | 1482 |
| `player_counts_by_token` (all countries) | 11 | 146 | 149 | 153 |
| `h2h_games_rows` (pair, sync page) | 579 | 305 | 280 | 321 |
| **Panel sequential total** | 1272 | **3155** | 2171 | **3727** |

**Full page (curl):** TTFB 71–117 ms; total 2.4–5.0 s (body streams after header+ribbon).

### Global overfetch (rivals H2H hot path)

1. **`amiga_country_rivals_render_h2h_panel`** calls `amiga_country_rivals_h2h_played_rivals` then `amiga_country_rivals_bucket` — each runs full `amiga_country_rivals_rows()` (~850–1500 ms TT).
2. **Poster** calls `amiga_countries_player_counts_by_token()` → full countries index (~145 ms TT) for two card counts (hero count already in `$summaryRow`).
3. **`amiga_country_rivals_h2h_games_rows`** on page (~280 ms TT); chart JS hits same game rows again via API endpoints.

Roster-path overfetch is fixed; **rivals path is a separate debt class** (matchup rollup + perf batch + duplicate panel reads).

### Chrome classification (manual + instrumentation)

| Scenario | Class | What you see |
|----------|-------|--------------|
| TT **`y ≈ 0`**, direct H2H URL | **Type B** | Header + hub + TT ribbon paint immediately; **2–5 s sub-ribbon void** before hero + H2H panel (no body cloak). Worse at `event:22` / `month:2025-09`. |
| TT **`y > 0`**, carry from ribbon/hub | **Type A** | Full body cloak up to 700 ms; reveal when TT nav + `minHeight` satisfy `carryReady()` — often **before hero/H2H stream** → header-only or empty band at carried Y. |
| Present **`y ≈ 0`** | **Type B (mild)** | ~2.4 s total; hero/H2H appear without harsh flash. |
| Hash `#k2-country-roster` (pending or URL) | **Type A → hash scroll** | Rivals page still emits `#k2-country-roster` anchor (before hero). Cloak until anchor exists; lands ~hero top, not H2H panel. Flag links target roster, not H2H. |
| After content paints | **Type C (charts only)** | Chart panels “Loading…” via deferred API — not primary F20 symptom. `$k2RankedCloak` set but no sortable table on this page. |

**Carry touchpoints on this route:** `k2_carry_scroll_restore.php` (head); country segment nav `aria-label="Country sections"`; rivals wings `aria-label="Rivals views"`; H2H listbox wrapper `data-k2-carry-scroll`. **`carryReady()`** gates on stored nav label (often TT ribbon) — **not** hero or `.k2-country-rivals-h2h`.

### Recommended next slice (priority)

1. **Query dedupe (first)** — single `rivals_rows` pass in panel; index row / summary for rival player count; memoize pair game rows for moments (chart APIs stay async).
2. **PHP `flush()` after hub nav (3b)** — emit hero + country/rivals nav shell before heavy rivals queries; fixes Type B at `y=0` without touching carry-scroll.
3. **Carry reveal gate (3d, optional)** — on country entity pages, defer reveal until `.k2-country-hero` exists when `y > 0`.
4. **Hash (optional)** — omit roster anchor on non-roster views, or defer hash scroll until hero parsed.

**Do not** remove realm/hub carry-scroll.

---

## Out of scope

- **F6** (TT ribbon nav blank at y=0) — separate slice (iter 3b PHP flush on rating LB / hub pages)
- Stored-truth / new DB tables