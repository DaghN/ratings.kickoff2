# TT baseline F6 — attempt log (informal)

**Not authoritative.** Symptom register stays in [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md). Verified fixes stay in slice handoffs. This file is a **lab notebook**: what we tried, observed result, and post-mortem guesses.

**Failure targeted:** F6 (sub-ribbon blank on TT ribbon nav at scroll top) · related F19 (LED) · accidental realm-switch regression (discovered iter 2).

---

## Attempt 0 — Explore + register (2026-07-04)

| | |
|--|--|
| **What** | Documented F6/F18 from Dagh repro; no code. |
| **Worked?** | Yes for process — clear scroll-top vs mid-scroll split, picker vs chevron, month wing sensitivity. |
| **Why / notes** | Established that “only table vanishes” = ranked-table cloak; “everything below ribbon” = carry-scroll / streaming timing. |

---

## Attempt 1 — Slice 0 iter 1 (`cec928f`)

| | |
|--|--|
| **Hypothesis** | At scroll top, `carryReady()` passes too early (`maxScrollTop >= 0`); reveal before `.k2-hub-chapter` exists. |
| **Changes** | (1) Skip body cloak when payload `{ y: 0, no anchor }`. (2) `carrySubRibbonReady()` gate when `targetY <= 0`. (3) Chevrons store TT stepper nav anchor. |
| **Dagh result** | **Partial.** Chevrons/wings often OK. **Pickers bad.** Month wing worse. LED co-waits with table. |
| **Worked?** | Partially — not sign-off. |
| **Why it failed (post-mortem)** | **Skip-cloak path wrong for pickers:** no anchor → no cloak → browser streams HTML (ribbon first, hub chapter after slow PHP) → visible blank below ribbon. **Month wing:** same streaming + 700ms / `domReady` still forced early reveal on some paths. **Chevrons OK when query fast** (< ~700ms). Iter 1 accidentally improved pickers’ *theory* while breaking pickers in practice by removing cloak. |

---

## Attempt 2 — Slice 0 iter 2 (`1d875ed`, WIP)

| | |
|--|--|
| **Hypothesis** | Keep cloak at scroll top; wait past 700ms for hub chapter; show header+stamp+ribbon during wait (`k2-carry-cloak-top`); picker forms store TT anchor. |
| **Changes** | (1) Reverted y=0 cloak skip. (2) Added `k2-carry-cloak-top` CSS (hide `body *` except header / stamp / TT ribbon). (3) No `domReady` / 700ms early reveal when `targetY <= 0`. (4) `storeScrollYFromForm` for period + with-player pickers. |
| **Dagh result** | **Worse.** Complete sub-ribbon blank on **every** TT action (wings, chevrons, pickers). **New:** Amiga → Online realm switch → header only, blank below, then online content. |
| **Worked?** | No. |
| **Why it failed (post-mortem)** | See investigation below. |

### Investigation — iter 2 regressions (2026-07-04)

#### A) Visible blank is now intentional CSS (TT pages)

`k2-carry-cloak-top` sets `body * { visibility: hidden }` except header, stamp, and `.k2-amiga-time-travel`. Until `reveal()`, **sub-ribbon is forcibly hidden** — not merely “not streamed yet”. User sees a stable ribbon + **empty viewport below** for the whole wait (until hub chapter exists or `window` load / 900ms safety `reveal()`). That reads as “complete sub-ribbon blanking” on every nav, even when iter 1 chevrons felt OK.

#### B) Realm switch — cross-realm carry-scroll (`NEW`)

`realm_switcher_nav.php` uses `data-k2-carry-scroll` on **“Kick Off 2 realm”** pills. Leaving Amiga at scroll top stores `{ y: 0, anchor: realm nav }` → scroll-top carry on **Online** destination.

Online pages have **no** `.k2-amiga-tt-stamp` / `.k2-amiga-time-travel`. Narrow-cloak whitelist leaves **only `.k2-site-header` visible** — **entire Online body hidden** until reveal. Matches: *amiga content → blank under header → online content*.

Before iter 2: full-body cloak (`body { visibility: hidden }`) → solid theme fill, or faster reveal paths — Dagh saw cross-fade without obvious header+void.

#### C) PHP stream order unchanged

Rating LB still emits: header → TT chrome → hub nav → **LB query** → `.k2-hub-chapter` → table. Waiting for hub chapter is correct in theory but **iter 2 makes the wait visually harsh** via narrow cloak. Slow month queries lengthen the void.

#### D) Safety nets

`window` `load` and `setTimeout(reveal, 900ms)` still call `reveal()` and remove cloak classes. Persistent blank likely = **long period with `k2-carry-cloak-top` active** (user-perceived “always”), not permanent stuck cloak — but realm case hides all Online content until reveal.

#### E) y=0 only (Dagh, 2026-07-04)

**All reported problems occur at `scrollY ≈ 0`. Mid-scroll carry (`y > 0`) behaves fine** — including realm switch when scrolled. The regression surface is **scroll-top navigation only**, not carry-scroll as a feature.

**Design tension:** When `y = 0`, scroll restore is a **no-op** (target is already top). Engaging the full pre-paint cloak pipeline at y=0 is trying to solve **HTML streaming order** with a tool built for **mid-page scroll flash**. That is harder than mid-scroll, not easier — and it should not be.

---

## Rejected ideas (spec creep)

| Idea | Verdict |
|------|---------|
| **Stop carry-scroll on realm pills** | **Rejected** — realm switch must keep carry-scroll; iter 2 broke it, fix the bug, do not remove the feature. |

---

## Likely next directions (not decided)

| Option | Idea | Risk |
|--------|------|------|
| **Revert iter 2 narrow cloak** | Drop `k2-carry-cloak-top`; restore iter 1 or pre-iter-2 base | Pickers/month still need y=0 path |
| **y=0 noop path (recommended)** | Destination: if stored `y === 0`, **skip cloak + restore loop** (normal full-page load at top). Source: still store payload for back/forward. Mid-scroll unchanged. | Brief streaming flash at y=0 may remain → address with PHP flush if needed |
| **PHP flush** | Emit hub chapter before heavy LB query on hot paths | Larger slice; fixes stream order at source |

---

## Attempt 3 — y=0 noop destination (`iter 3a`, approved 2026-07-04)

| | |
|--|--|
| **Hypothesis** | Scroll-top bugs came from running mid-scroll cloak machinery when `y=0` (nothing to restore). Split paths: `y>0` = cloak + restore; `y=0` = clear payload, normal load. |
| **Changes** | Removed `k2-carry-cloak-top`, `carrySubRibbonReady`, scroll-top timeout hacks. Destination: if `payload.y === 0` && no hash → clear key/back-scroll, skip `hasPending`. Kept chevron anchor + `storeScrollYFromForm` on source side. |
| **Worked?** | **Partial — split result** (see below). |
| **If streaming flash remains at y=0** | Address in iter 3b (PHP flush), not carry-scroll |

### Dagh result — iter 3a (2026-07-04)

| Context | Result |
|---------|--------|
| **Outside TT** (realm switch, present pages) | **Good** — smooth at y=0 and y>0 |
| **TT, y=0** | **Bad** — sub-ribbon blank on wings, chevrons, pickers, **and hub nav** |
| **TT, y>0, event/year** | **Mostly good** — **except Countries hub tab always whole-page blank** |
| **TT, y>0, month** | **Mixed** — sometimes sub-ribbon blank on nav; Countries / WC tabs **not** whole-page blank |

Iter 3a fixed non-TT / realm as predicted. **TT blanking is not one bug** — it splits by scroll position and blank *type*.

---

## Framework — two blank mechanisms (code-backed)

| Type | When | Mechanism | What user sees |
|------|------|-----------|----------------|
| **A — Carry cloak** | TT (and any carry nav) when **`y > 0`** stored | `html.k2-carry-cloak body { visibility: hidden }` in `k2_carry_scroll_restore.php` until reveal (~700ms or `carryReady`) | **Whole viewport** empty (theme fill) — includes header, ribbon, hub nav — then content appears (often at carried scroll Y) |
| **B — PHP streaming gap** | **`y = 0`** (iter 3a: no carry cloak) + TT pages | Server emits HTML **top-down**, then **blocks on DB** before hub chapter / table. No `flush()` on normal Amiga pages. | **Ribbon stable** (+ hub tabs if already sent); **everything below** empty until query finishes — old → gap → new |
| **C — Table-only** | Pages with `$k2RankedCloak` | `ranked-table-pending` hides table until `k2-table.js` | **Only table** vanishes; chrome stays — “mostly good” mid-scroll |

**Iter 3 split:** Non-TT mid-scroll = Type A only when needed. TT y=0 = Type B only (carry disengaged). TT y>0 = Type A on nav, then often Type C on table — **unless** reveal fires while body below viewport still missing (looks like Type A again).

---

## Why whole-page vs sub-ribbon blank?

- **Whole-page blank** = almost always **Type A** (full body cloak) for some period — user sees nothing (or solid theme), not “ribbon + void”.
- **Sub-ribbon blank** = **Type B** at y=0, or Type A ended but **viewport at carried Y** points at a region PHP has not emitted yet.

---

## PHP page order (why TT differs from Present)

Typical TT hub page (e.g. `rating.php`, `countries.php`):

```
1. <head> + carry-scroll script (may cloak if y>0)
2. site_header.php → amiga_snapshot_chrome (extra DB: context + wing catalog)
3. amiga_hub_nav.php (hub tabs — data-k2-carry-scroll)
4. ── BLOCK: heavy page query ──
5. k2_hub_chapter + body (table, etc.)
```

**Present / non-TT:** step 2 has no snapshot chrome DB pass. **TT adds a second catalog/context load in header before step 4.**

At **y=0**, carry does not cloak — browser may paint 1–3 as they arrive, then **gap at 4**. That matches “everything under ribbon” (hub tabs are under ribbon too once 3 is painted).

At **y>0**, step 1 cloaks **entire body** until reveal — **whole-page blank** even if 2–3 are already buffered.

---

## Mystery (a) — Why month vs event/year differ?

**Code does not give month/year/event equal cost in the header.**

| Wing | Catalog build (`amiga_rating_history_catalog_for_wing`) | Header work |
|------|-----------------------------------------------------------|-------------|
| **Event** | One `amiga_rating_history_tournaments()` load | Event wing only: large picker HTML, `amiga_snapshot_chrome_event_layout_style()`, as-with listbox |
| **Year** | Loop years → **one SQL per year** (`cutoff_tournament_for_year_end`) | Smaller picker |
| **Month** | Loop every calendar month → **one SQL per month** (`cutoff_tournament_for_month_end`) | Smaller picker but **~200+ header queries** possible |

So month is **not** “equally cheap” to year/event in code — **month catalog is the most expensive header path** (O(months) queries). That does **not** explain every Dagh observation but explains why wing mode is not interchangeable.

**Observed asymmetry (hypothesis stack):**

1. **y=0:** All wings → Type B streaming; month may feel worse when **header catalog** + **page query** both run before hub chapter (longer total gap).
2. **y>0, month, Countries OK:** May reflect **how** you test (scroll position, cutoff early vs late) as much as wing — same cutoff tuple should hit same Countries query cost regardless of wing label on `as=`.
3. **y>0, month, “sometimes” sub-ribbon:** Type A reveal at 700ms while document height ≥ Y but **hub chapter not yet parsed** — viewport shows empty band below ribbon.

**Not a separate month nav code path** — same carry-scroll, same hub/ribbon forms; difference is **DB cost + cutoff + scroll Y**.

---

## Mystery (b) — Countries tab, y>0, event/year only, always whole-page blank?

**Countries is the heaviest hub destination in code.**

`countries.php` after hub nav:

1. `amiga_countries_player_rows_at_cutoff()` — snapshot join + WC slice stats  
2. `amiga_countries_attach_elo_ranks_at_cutoff()` — window over `amiga_player_elo_rank_at_event` ≤ cutoff  

Both scale with **how much history exists before cutoff** (late cutoff → more rows scanned).

Rating LB uses one primary snapshot query — usually **faster** than Countries’ pair.

**Why whole-page (Type A) specifically:**

- Hub nav to Countries at **y>0** → full carry cloak.  
- `carryReady()` can pass when hub anchor exists and `scrollHeight >= Y`, often **before step 4–5 complete** (700ms timeout also forces reveal).  
- `reveal()` + `scrollTo(Y)` → user looks at mid-page **before hub chapter/table exist** → **empty viewport = “whole page blank”**.  
- Countries’ long step 4 makes this **deterministic** on event/year tests at late cutoffs.

**Why month wing Countries tab might not show the same:**

- If you often open Countries at **y=0** while on month wing → Type B (sub-ribbon only), not Type A.  
- If cutoff is **early** on month picks → query fast → less time in Type A after reveal.  
- **Timing interaction (strong):** event/year header catalog is **cheap** (one tournaments load) → hub nav hits the wire early → `carryReady()` or **700ms** fires **before** Countries’ heavy query finishes → reveal + `scrollTo(Y)` → empty viewport. **Month header catalog is expensive** (one SQL per calendar month) → hub nav is **late in the stream** → cloak often lasts through header **and** page query → reveal closer to hub chapter ready → less post-reveal void (still a long cloak, but not the same “flash blank after uncloak” pattern).  
- **Not** because Countries PHP branches on wing — it uses `AmigaSnapshotContext` cutoff only.

**Wing label on `as=` does not change Countries SQL** — **cutoff date + scroll Y + page weight** do.

---

## Attempt 3 post-mortem (short)

| Prediction | Outcome |
|------------|---------|
| y=0 noop fixes realm / non-TT | **Yes** |
| y=0 noop fixes TT sub-ribbon | **No** — Type B streaming exposed |
| Mid-scroll unchanged | **Mostly yes** — except Countries cross-hub at y>0 |

**Conclusion:** Carry-scroll was the wrong layer for TT y=0. The remaining TT issues are **page emission order + query latency**, compounded by Type A on y>0 cross-page hops to slow destinations (Countries).

---

## Likely next directions (updated)

| Option | Idea | Notes |
|--------|------|-------|
| **3b — PHP flush (recommended)** | After hub nav (or hub chapter shell), `flush()` before heavy query on `rating.php`, `countries.php`, other TT hot paths | Fixes Type B at y=0 without touching carry-scroll; helps y>0 after reveal too |
| **3c — Countries query slice** | Stored/prewarm or slimmer TT read for countries index | F18-adjacent; separate from carry |
| **3d — Carry reveal gate (y>0 only)** | Do not `reveal()` until `.k2-hub-chapter` exists when cross-hub nav | Narrow fix for Type A + empty viewport; keep mid-scroll carry |
| **Month catalog perf** | Cache or batch month catalog cutoff lookups | Header perf; not F6 per se but explains month wing pain |

**Still rejected:** removing carry-scroll on realm or hub pills.

---

## Changelog (this file)

| Date | Entry |
|------|--------|
| 2026-07-04 | Log created after iter 2 feedback — iter 1 partial, iter 2 worse + realm regression |
| 2026-07-04 | **y=0 only** — mid-scroll OK; realm carry-scroll is required (no feature removal); rejected “skip realm carry” |
| 2026-07-04 | **Iter 3a shipped** — y=0 noop destination; revert narrow cloak |
| 2026-07-04 | **Iter 3a result** — non-TT good; TT y=0 Type B; TT y>0 Countries Type A; framework + month/year code notes |
| 2026-07-04 | **Perf fixes shipped** — month catalog in-memory; Countries index SQL GROUP BY |

---

## Perf investigation — month catalog + Countries (2026-07-04)

**Probe:** `scripts/oneoff/amiga_tt_perf_probe.php` on local `ko2amiga_db`.

### What is the “catalog”?

Time-travel **ribbon** needs a list of every steppable period in the active wing (month / year / event) so PHP can render:

- Period **picker** (dropdown of all months/years/events)
- **Chevron** prev/next keys
- **Cutoff tuple** for the current `as=` key

Built by `amiga_rating_history_catalog_for_wing()` → cached in `AmigaSnapshotContext` on **every TT page** via `amiga_snapshot_context_from_request()` in `site_header` / snapshot chrome.

| Wing | How catalog is built | Local timing |
|------|----------------------|--------------|
| **Event** | One `amiga_rating_history_tournaments()` load; cutoff = that event | **~9 ms** |
| **Year** | Loop years; **one SQL per year** (`cutoff_tournament_for_year_end`) | **~27 ms** |
| **Month** | Loop every calendar month (2001-11 → 2025-11); **one SQL per month** (`cutoff_tournament_for_month_end`) | **~283 ms** (289 queries) — **fixed 2026-07-04:** in-memory from tournament list **~2 ms** |

**Month is slow because of N+1 queries in `amiga_rating_history_catalog_month()`** — not because month cutoffs are semantically harder than year. Event wing avoids per-row cutoff lookups because each catalog row *is* a tournament.

**Header cost when wing=month:** `amiga_snapshot_context_from_request()` alone **~380 ms** vs **~3 ms** for event wing (same cutoff date, different `as=` wing label).

That runs in **`site_header` before hub nav or page body** — explains month wing feeling “weird” on TT nav and why month can **mask** Countries carry-cloak timing (slow header keeps body cloaked longer).

### Countries index — why so slow?

**Index table columns** (`amiga_countries_index_table.php`): Rank, Country, Players, Games, Games/player, WC columns. **No rating, no elo_rank.**

**Policy CH14:** `elo_rank` is for **roster** only, not index.

**But code path for `countries.php`:**

1. `amiga_countries_player_rows_at_cutoff()` — full snapshot join + WC slice-at-cutoff join for **every rated player** (~435 rows)
2. **`amiga_countries_attach_elo_ranks_at_cutoff()`** — window scan over **`amiga_player_elo_rank_at_event`** for all rows ≤ cutoff
3. `amiga_countries_index_rows()` — PHP roll-up to **~19 countries** (1.5 ms)

**Local timings at `event:589` (2016-03-19):**

| Step | ms |
|------|-----|
| `countries_player_rows_at_cutoff` (total) | **515** |
| — of which `elo_attach_only` | **358** (~70%) |
| Slim fetch (index fields only, no elo) | **~143** |
| `amiga_countries_index_rows` | **1.5** |
| Rating LB snapshot (same cutoff) | **145** |

**Conclusion:** Countries index reuses the **roster player-row pipeline** including elo rank attachment that **the index never displays**. That is genuinely bad code relative to the simple table — not a data-size mystery. CH21 said ~470×21 is acceptable; the bug is **extra work**, not inherent aggregation cost.

**Likely fixes (separate slices, not F6):**

1. ~~**Index-only read**~~ — **Done 2026-07-04:** `amiga_countries_query_index_rows()` SQL `GROUP BY country_token`; `countries.php` uses it (no elo attach).
2. **Split attach** — elo attach remains on roster path only (`amiga_countries_player_rows_at_cutoff`).
3. ~~**Month catalog**~~ — **Done 2026-07-04:** derive month/year cutoffs from cached `amiga_rating_history_tournaments()` list (O(months+tournaments), no N+1 SQL).

### After fix (local `ko2amiga_db`, 2026-07-04)

| Path | Before | After |
|------|--------|-------|
| `catalog_month` | ~283 ms (289 SQL) | **~2 ms** |
| TT header context (month wing) | ~380 ms | **~5 ms** |
| Countries index TT (`event:589`) | ~515 ms (+358 ms elo) | **~137 ms** (parity OK) |
| Countries index present | (via roster path) | **~8 ms** |

### Dagh sign-off (2026-07-04)

- **Countries hub** — snappy in browser after index SQL path.
- **Month wing** — no longer exhibits abnormal TT nav behavior (header catalog no longer blocks).