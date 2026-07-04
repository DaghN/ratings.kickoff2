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

## Attempt 3 — PHP stream flush (`iter 3b`, 2026-07-04)

| | |
|--|--|
| **Hypothesis** | Type B at TT y=0 is PHP blocking on wing queries before hub chapter / wing tabs emit. Flush after chrome shell so browser paints ribbon + hub nav + LB wing nav before heavy query. |
| **Changes** | `k2_page_stream_flush.php` + `amiga_lb_emit_wing_nav.inc.php`. All seven Amiga LB wings: hub nav → wing nav (chapter + tabs) → `flush()` → wing query → table. `countries.php`: hub nav → `flush()` → index query → hub chapter. |
| **Worked?** | **Failed — reverted 2026-07-04.** Shipped with infinite `ob_flush` hang (hotfixed); Dagh retest: present LB felt slower; TT y=0 sub-ribbon blank unchanged; y>0 Type A (Countries full-page blank) unchanged. Wrong layer for F6. |
| **Out of scope** | Perf-rating sub-wings (query-before-shell layout); carry-scroll changes; iter 3d reveal gate. |

---

## Framework — two blank mechanisms (code-backed)

| Type | When | Mechanism | What user sees |
|------|------|-----------|----------------|
| **A — Carry cloak** | TT (and any carry nav) when **`y > 0`** stored | `html.k2-carry-cloak body { visibility: hidden }` in `k2_carry_scroll_restore.php` until reveal (~700ms or `carryReady`) | **Whole viewport** empty (theme fill) — includes header, ribbon, hub nav — then content appears (often at carried scroll Y) |
| **B — PHP streaming gap** | **`y = 0`** non-TT, or TT **before 3d-c** | Server emits ribbon at TTFB; paint holding ends → old page discarded → void below ribbon until hub chapter | Ribbon stable; void below until query finishes |
| **B′ — y=0 paint-holding break (fixed 3d-c)** | TT **`y = 0`** carry nav **before 3d-c** | First contentful paint (ribbon) ended paint holding instantly — "near-insta vanish" even with fast queries | **Fixed:** TT y=0 chrome gate (full cloak, no contentful paint until `.k2-hub-chapter`) |
| **C — Table-only** | Pages with `$k2RankedCloak` | `ranked-table-pending` hides table until `k2-table.js` | **Only table** vanishes; chrome stays — success bar target |

**Post-3d (2026-07-04):** TT y>0 fixed by query speed under 700 ms cloak. TT y=0 fixed by chrome gate + query speed. Residual vanish on any page = query still > ~700 ms (see playbook) or HTML render bound (player games 1500 rows).

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
4. LB wing nav (hub chapter + wing tabs) — **reverted:** query-then-nav (pre-3b order)
5. ── BLOCK: heavy page query ──
6. table / hub chapter body (Countries: chapter lede after index query)
```

**Present / non-TT:** step 2 has no snapshot chrome DB pass. **TT adds a second catalog/context load in header before step 5.**

At **y=0** on TT destinations (after **3d-c**): full-body **chrome gate** — old page held until `.k2-hub-chapter` parsed (~165–230 ms block after 3d-b). Non-TT y=0 unchanged (normal load).

At **y>0**, step 1 cloaks **entire body** until reveal — fixed when block ≪ 700 ms (3d-b).

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
| **3b — PHP flush** | ~~After hub nav, flush before heavy query~~ | **Failed — reverted.** Did not pass TT smokes; perceptual present slowdown. |
| **3c — Countries query slice** | Stored/prewarm or slimmer TT read for countries index | F18-adjacent; separate from carry |
| **3d — Carry reveal gate + query audit** | Shipped as **3d-b** (query) + **3d-c** (y=0 gate) | [`2026-07-04-003`](agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) — Dagh y>0 sign-off; realm S1 recommended |
| **Realm query sweep** | Tracks A–I + census + playbook | [`2026-07-04-004`](agent-handoffs/2026-07-04-004-amiga-tt-query-optimization-sweep.md) · [`amiga-tt-query-optimization-playbook.md`](../amiga-tt-query-optimization-playbook.md) |
| **Month catalog perf** | **Done** — in-memory from tournament list | — |

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
| 2026-07-04 | **Iter 3b reverted** — PHP flush failed F6 smokes; present LB slower feel; deleted flush helpers |
| 2026-07-04 | **F6 rating LB handoff** — iter 3d+ audit-first, flawless nav bar — [`2026-07-04-003`](docs/orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) |
| 2026-07-04 | **Iter 3b hotfix** — `k2_page_stream_flush()` used `ob_flush()` in while loop (infinite hang); fixed to `ob_end_flush()` |
| 2026-07-04 | **F6 Phase 0 audit** — 174-col `snap.*` window scan = root cost (500–2,000 ms; narrow+join-back ~50 ms, parity OK); Δ map duplicate ladder scan; double games count; classification matrix — § F6 Phase 0 |
| 2026-07-04 | **Iter 3d-b shipped** — narrow snapshot join + slim Δ map + games-count cache; TT block ~165–230 ms (was 710–906); parity probes green; **Dagh S1/S1b sign-off 2026-07-04** — § Attempt 4 |

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

---

## Country roster audit — `amiga/country/roster.php` (2026-07-04)

**Probe:** `scripts/oneoff/amiga_country_roster_audit_probe.php` · example: `?country=Greece&as=month:2025-09`

### Page load order

```
1. <head> + carry-scroll (hash #k2-country-roster and/or y>0 → body cloak)
2. site_header → TT snapshot chrome (catalog + ribbon DB)
3. amiga_hub_nav.php
4. ── BLOCK ── amiga_country_page.php queries (lines 79–87)
5. #k2-country-roster anchor + hero + roster table
```

**`#k2-country-roster` anchor is emitted only after step 4** — hash landing / cloak reveal can show header + hub bar while step 4 runs.

### What step 4 does today (`amiga_country_page.php`)

```82:87:site/public_html/includes/amiga_country_page.php
$playerRows = amiga_countries_player_rows($con, $ctx);
$indexRows = amiga_countries_index_rows($playerRows);
$summaryRow = amiga_countries_index_row_for_token($indexRows, $countryToken);
$rosterRows = $k2AmigaCountryView === 'roster'
    ? amiga_countries_roster_rows($playerRows, $countryToken)
    : [];
```

| Step | Work | Needed for Greece roster? |
|------|------|---------------------------|
| `player_rows` | **All** rated players at cutoff + snapshot join + WC slice + **global** `elo_attach` | **No** — only ~36 Greek players |
| `index_rows` | PHP roll-up **all countries** | **No** — hero needs **one** country summary row |
| `roster_rows` | PHP filter one token | Yes — but should be SQL `WHERE country = ?` |

**Same anti-pattern as pre-fix Countries index** — roster path was never split after index SQL slice.

### Timings (local `ko2amiga_db`, Greece)

| `as=` | `player_rows` (all) | Greece roster rows | Greece-only SQL count (sanity) |
|-------|---------------------|--------------------|--------------------------------|
| Present | **~25 ms** | 36 | **~2 ms** |
| `month:2002-01` (early) | **~205 ms** | 6 | **~77 ms** |
| `month:2025-09` (late) | **~845 ms** | 36 | **~210 ms** |
| `event:589` (late) | **~968 ms** | 35 | **~267 ms** |

Late TT cutoffs scan more snapshot / elo history → cost grows even though **displayed roster size is unchanged (~35 players)**.

### F20 — two coupled causes

1. **Overfetch (primary slowness):** step 4 global player + elo pipeline (~0.8–1 s late TT).
2. **Hash + carry chrome (header flash):** flag links use `k2_amiga_country_roster_href()` → `#k2-country-roster` → `k2_carry_scroll_restore.php` hash cloak; if user arrived with `y > 0` from prior hub/ribbon carry, same Type A void. PHP streams header + hub nav before step 4 → user sees header-only gap.

### Likely fix slice (not implemented)

1. ~~`amiga_countries_query_roster_rows($con, $ctx, $countryToken)`~~ — **Done 2026-07-04:** SQL filtered + elo attach scoped to roster player ids
2. ~~Hero summary~~ — **Done:** derived from roster rows (roster view) or `amiga_countries_query_country_summary` (rivals)
3. Optional: F20 chrome — flush hero shell before query, or hash scroll after content (separate from query perf)

### After roster fetch fix (local, Greece, 2026-07-04)

| `as=` | Before (all players) | After (`query_roster_rows`) | Parity |
|-------|----------------------|----------------------------|--------|
| Present | ~25 ms | **~2 ms** | OK |
| `month:2025-09` | ~845 ms | **~208 ms** | OK |
| `event:589` | ~968 ms | **~172 ms** | OK |

Elo attach now uses `player_id IN (...)` from fetched rows only (helps global path too).

---

## F20 audit — Country rivals H2H (2026-07-04)

**Handoff:** [`2026-07-04-002-f20-country-rivals-h2h-audit.md`](agent-handoffs/2026-07-04-002-f20-country-rivals-h2h-audit.md) · **Probe:** `scripts/oneoff/amiga_country_rivals_h2h_audit_probe.php`

Roster query fix does **not** cover rivals H2H — panel sequential total **2.2–3.7 s** at late TT (`event:589` / `month:2025-09`). Primary overfetch: **double `amiga_country_rivals_rows()`** in `render_h2h_panel` + full-index `player_counts_by_token` for poster.

| TT scenario | Blank type | Driver |
|-------------|------------|--------|
| `y=0` direct H2H | **Type B** | Header+ribbon stream first; ~2–5 s gap before hero/H2H |
| `y>0` carry from ribbon | **Type A** | Cloak + early `carryReady()` on TT nav before hero/H2H |
| Charts after paint | **Type C** | API-loaded chart shells only |

**Next slice:** query dedupe in panel, then PHP flush after hub nav (3b); optional carry gate on `.k2-country-hero` (3d).

---

## F6 Phase 0 — Rating LB TT query audit (2026-07-04)

**Probes:** `scripts/oneoff/amiga_rating_lb_tt_audit_probe.php` (per-phase timings) · `scripts/oneoff/amiga_rating_lb_sql_variant_probe.php` (wide vs narrow window scan, with parity check). Local `ko2amiga_db`.

### Root cause found — 174-column window scan

`amiga_player_event_snapshots` has **174 columns**. `amiga_lb_snapshot_from_sql()` runs `ROW_NUMBER()` over **`snap.*`** — MariaDB materializes the whole wide table (4,535 rows × 174 cols, incl. long text-ish cols) into a temp table before filtering `rn = 1`. That one shape costs **500–2,000 ms**. A **narrow window scan** (`player_id`, `tournament_id` + tuple only) followed by a **join back** to the wide row via PRIMARY key returns byte-identical rows in **~45–70 ms** (10–30×):

| Cutoff | A — wide `snap.*` (today) | B — narrow + join-back | Parity |
|--------|---------------------------|------------------------|--------|
| `month:2014-07` | 2,020 / 1,522 ms | **69 / 45 ms** | OK |
| `event:589` | 607 / 576 ms | **44 / 48 ms** | OK |
| `month:2025-09` | 718 / 739 ms | **69 / 54 ms** | OK |

`amiga_lb_snapshot_from_sql()` is shared by **all LB wings, Countries, histograms, realm snapshot reads** — one fix, whole TT read family.

### Per-phase timings — rating.php blocking segment (hub nav → wing nav)

Everything below runs **between** hub nav emit and hub chapter / wing tabs emit (PHP page order step 4):

| Scenario | ctx build (header) | career query | games count | Δ map | lede games count | lede tourn. count | **block total** |
|----------|-------------------|--------------|-------------|-------|------------------|-------------------|-----------------|
| present | 1 ms | 71 ms | 27 ms | **183 ms** (WC-start) | 21 ms | 3 ms | **~304 ms** |
| `month:2014-07` | 21 ms | **650 ms** | 48 ms | 176 ms | 29 ms | 4 ms | **~906 ms** |
| `event:22` *(= Athens XCI 2025 — actually **late**)* | 23 ms | 516 ms | 33 ms | 178 ms | 33 ms | 2 ms | ~762 ms |
| `event:589` | 24 ms | 503 ms | 29 ms | 209 ms | 40 ms | 3 ms | ~783 ms |
| `month:2025-09` | 25 ms | 542 ms | 32 ms | 168 ms | 34 ms | 2 ms | ~779 ms |
| `year:2014` | 15 ms | 463 ms | 29 ms | 177 ms | 39 ms | 2 ms | ~710 ms |
| `month:2002-06` (true early, 67 rows) | — | 324 ms | — | — | — | — | — |

Curl full page: TTFB **~50–90 ms** (header + ribbon emit immediately), total **1.2–1.75 s** TT vs 0.7 s present. The gap between TTFB and hub-chapter bytes **is** the F6 void.

Waste stacked on top of the slow scan:

1. **Δ map duplicates the career scan** — `amiga_lb_rating_delta_map()` → `resolve_view` re-fetches the **current ladder** (~70–115 ms) that the career query just produced, plus prev ladder (~70–115 ms) + event participants (~1 ms).
2. **Games count runs twice** — rating.php footer + hub chapter lede recompute the same count (~30 ms × 2).
3. **Lede opens a 3rd DB connection** (chrome had one, page has one) — ~5 ms.
4. Event-wing chrome eligible-players list ~11 ms (once, static-cached).

**No wing asymmetry left** in the header (month catalog fixed earlier, ctx build 15–25 ms all wings). Remaining cost is cutoff-scaled (wide scan reads all rows ≤ cutoff), so late cutoffs are worst — matching Dagh's repro. **Note:** `event:22` in the handoff test set is Athens XCI (2025-04-05) — a *late* cutoff despite the low id (fractional-chrono import); use `month:2002-06` or similar for a genuine early cell.

### Classification matrix (rating LB, code-backed)

| Matrix cell | Type | Mechanism |
|-------------|------|-----------|
| TT `y=0`, ribbon chevron / wing tab / picker / hub bar | **B** | Ribbon streams at ~60 ms TTFB; hub chapter blocked ~700–900 ms behind queries → old → void-below-ribbon → new |
| TT `y>0`, same-page ribbon nav | **A→(B)** | Full carry cloak; `carryReady()` needs `scrollHeight ≥ Y` which the block delays → **700 ms timeout** fires while block still running → reveal + `scrollTo(Y)` onto short document → empty band below ribbon |
| TT `y>0`, hub hop (Countries F18) | **A** | Same timeout race on a destination that blocks after hub nav (~137 ms post-fix, was ~515 ms) |
| Table swap after chrome painted | **C** | `$k2RankedCloak` — allowed by success bar |
| Present `y=0` | mild **B** | ~304 ms block — **183 ms is the present WC-start Δ map**; usually under perception threshold but explains "present feels slower" sensitivity in 3b test |
| Early vs late cutoff | scales B and the A-timeout race | Block ~400 ms (2002) → ~900 ms (2014-07) → ~800 ms (late) |

**Key insight:** both the y=0 void (Type B) and the y>0 "whole page blank" (Type A reveal race) share the same clock — the ~700–900 ms blocking segment vs the 700 ms `MAX_CLOAK_MS`. Shrink the block under ~150–200 ms and both symptoms collapse without touching carry-scroll.

---

## Attempt 4 — iter 3d-b query slices (2026-07-04, shipped)

| | |
|--|--|
| **Hypothesis** | F6/F18 clock is the DB block between hub nav and hub chapter; fix the query shapes, not the chrome. |
| **Changes** | **b1:** `amiga_lb_snapshot_from_sql()` narrow window scan (`player_id`+`tournament_id`) + PRIMARY-key join-back to wide row. **b2:** `amiga_lb_rating_delta_map()` slim — narrow `amiga_rating_history_rating_map_at_cutoff()` at current + prev wing cutoff (unfiltered catalog position, as_with-safe) + participants; no full `resolve_view` ladder; present WC-start baseline uses the same narrow map. **b3:** `amiga_lb_games_count()` request-scoped static cache (footer + lede shared). |
| **Parity** | SQL variant probe 3 cutoffs OK · delta map old-vs-new 7 scenarios (all wings, early+late, incl. `year:2001` no-prev) OK · Countries index parity probe OK · curl sweep rating/goals/peak/countries/roster/HoF TT — 200, no PHP errors. |
| **Result (local)** | Blocking segment: present ~304 → **~131 ms**; TT ~710–906 → **~165–230 ms** (career query 503–650 → **34–49 ms**). Curl total rating TT 1.2–1.75 s → **~0.6–0.7 s** (≈ present). All well under the 700 ms cloak timeout. |
| **Worked?** | **Yes — Dagh sign-off 2026-07-04 (S1/S1b).** Timings + browser matrix pass. |
| **Left on the block** | Δ map ~100–140 ms (two narrow window scans) — next candidate if browser feel still short of flawless. |
| **Follow-up (out of scope)** | **Peak-rating wing** own query still **~3.3 s** at TT cutoff — `amiga_player_elo_rank_at_event` window subquery needs the same narrow+join-back treatment when wings roll out. |

---

## Attempt 5 — iter 3d-c y=0 chrome gate (2026-07-04, shipped)

**Dagh feedback after Attempt 4:** y>0 fixed ("only the table waits"), but **y=0 still vanishes below the ribbon on every TT nav — near-insta on click, not a 0.7 s wait — and it is realm-wide, not LB-specific.**

| | |
|--|--|
| **Corrected mechanism** | The y=0 vanish is **not** the cloak and not (only) slow PHP. With no cloak at y=0 (iter 3a), the browser **commits the navigation at TTFB (~60 ms)** and progressively renders: header+ribbon paint immediately, which **ends Chrome's paint holding** — the old page is discarded the instant the ribbon paints, leaving a void below until the chapter bytes arrive. Even a ~200 ms block reads as old → *(instant)* → ribbon+void → new. That is the "near-insta-vanish on click". Iter 2's narrow cloak failed the same way: it *let the ribbon paint*, which is precisely what kills paint holding. |
| **Change** | `k2_carry_scroll_restore.php`: at `payload.y === 0` on a **TT destination** (`[?&]as=` in `location.search`), engage the **full-body cloak** as a chrome gate instead of skipping. No contentful paint → **Chrome paint holding keeps the OLD page on screen** through the block. Reveal when `.k2-hub-chapter` is parsed (hub pages emit it right after the blocking queries) or `domReady` fallback (non-hub TT pages), 700 ms timeout + load/setTimeout safety nets unchanged. No scroll ops, no `scrollRestoration` flip in this mode. Non-TT y=0 destinations unchanged (normal load). |
| **Why it needs Attempt 4 first** | Paint holding lasts ~500 ms; the gate only reads as in-place if the chapter arrives inside that window — true now the block is ~165–230 ms, impossible at the old 700–900 ms. |
| **Coverage** | All carry-scroll sources at y=0: hub tabs, wing tabs, realm pills, ribbon chevrons, pickers (form change). Direct URL loads / header search have no payload → stream as before. |
| **Verified** | Browser (IDE): rating LB TT `month:2014-07` at y=0 → Goals wing tab: destination revealed, cloak class removed, payload consumed, y=0. **Dagh S1/S1b sign-off 2026-07-04** — only table swaps; chrome stable. |

---

## World Cups hub — TT read fix (2026-07-04, same session)

Not an F6 chrome attempt but same clock family. All `/amiga/world-cups/*` pages multi-second in TT (`event:583` chronology ~5 s). **Cause:** `amiga_world_cup_stats_apply_share_of_year_games()` → `amiga_community_year_realm_games_at_cutoff()` — `amiga_community_stat_facts` has **446k rows** and only `tournament_id`-first indexes, so the latest-fact-per-year subquery (`tournament_id <= cutoff` + metric filters) full-scanned: **2,581 ms** at `event:583`. Fix: new index `idx_community_facts_metric_period` (`period_type, slice_type, slice_key, metric_key, count_basis, period_key, tournament_id`) → **48 ms** (54×); DDL in `scripts/amiga/sql/034_community_stats.sql` + `sql/derived/` mirror (staging picks it up on next export). Plus request cache on `amiga_world_cup_stats_rows()` (hub shell chapter count + page body each ran it, separate connections). Curl `event:583`: chronology 5+ s → **~0.5 s**; players/stats/countries wings 0.4–0.8 s.

---

## Three slow LB wings — TT reads fixed (2026-07-04, Dagh report)

**Dagh:** tournament-honours / calendar-geo / peak-rating vanish completely in TT (any y) before drawing slowly; "something quite poor or broken, maybe memory." **Diagnosis:** all three had private TT queries that never got the 3d-b1 narrow-scan treatment — blocks of 1.1–3.4 s at `year:2024`, far past the 700 ms cloak/gate ceiling, so both the y=0 chrome gate and the y>0 carry cloak timed out into a void. (PHP memory was fine, 4–6 MB — the "memory" feel was MySQL materializing wide temp tables server-side.) Probes: `amiga_lb_slow_wings_tt_probe.php`, `amiga_lb_slow_wings_variant_probe.php`, `amiga_lb_peak_rating_parity_probe.php`.

| Wing (TT `year:2024`) | Before | After | Fix |
|------|--------|-------|-----|
| tournament-honours | 586 ms **×2** (page reran the identical query for the footer count) | **43 ms ×1** | `amiga_lb_honours_rows_at_cutoff()` → shared `amiga_lb_snapshot_from_sql()` narrow shape + request cache (`amiga_lb_honours_player_count()` TT path counts the cached rows) |
| calendar-geo | 1,072 ms | **41 ms** | `amiga_lb_calendar_geo_rows_at_cutoff()` → same shared narrow shape (had its own inline wide `snap.*` window copy) |
| peak-rating | 2,248 ms | **92 ms** | `amiga_lb_query_peak_rating()` er-join rewritten: `amiga_player_elo_rank_at_event` is **dense** (each finalize writes one row per debuted player — verified rows-per-event == cumulative debuts across all 605 events), so latest-per-player ≤ cutoff ≡ `WHERE er.tournament_id = <cutoff event>` — the 173k-row ROW_NUMBER window was pure waste |

**Parity:** honours + calendar-geo old-vs-new across 3 cutoffs OK; peak-rating full row-set old-vs-new across **7 scenarios** (`year:2024`, `year:2001`, `month:2002-06`, `month:2014-07`, `event:589`, `event:22`, `month:2025-09`) all OK. Curl: all three TT pages **0.4–0.95 s** (≈ present), 468 rows, no PHP warnings.

**Pattern note for future wings:** any remaining `SELECT snap.*` + ROW_NUMBER copies are the same bug — one is left in `amiga_player_snapshot_lib.php` (`amiga_player_snapshot_row_at_cutoff`, single-player `player_id = ?` filter so cheap, fine as is). Dense-event equality is preferable to narrow+join-back wherever the table writes one row per player per finalize (er table: narrow+join-back still cost 0.9–2.3 s; dense equality 10–15 ms).

**Method generalized:** the full playbook for carrying these optimizations to the rest of the realm (patterns, probe/parity templates, budgets, remaining-suspects inventory) is [`docs/amiga-tt-query-optimization-playbook.md`](../amiga-tt-query-optimization-playbook.md).

---

## Issue closure summary (2026-07-04 evening)

Consolidated status after F6 track + realm query sweep. Symptom register updated in [`amiga-tt-chrome-sticky-invariants.md`](../amiga-tt-chrome-sticky-invariants.md).

| Failure | Status | Primary fix | Handoff / doc |
|---------|--------|-------------|---------------|
| **F6** sub-ribbon blank at y=0 | **Signed off 2026-07-04** | 3d-b query (~165–230 ms block) + 3d-c TT y=0 chrome gate | [`2026-07-04-003`](agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md) — Dagh S1/S1b pass |
| **F18** hub-tab whole-page blank | **Resolved (code)** | Countries index + WC facts index + stats cache + sweep | [`2026-07-04-004`](agent-handoffs/2026-07-04-004-amiga-tt-query-optimization-sweep.md) |
| **F19** LED co-waits with table | **Resolved** | Stamp `initStamp()` at script eval | TT policy §5.0 Motion |
| **F20** flag/roster header flash | **Query resolved** | Roster + rivals Track A query slices | [`2026-07-04-002`](agent-handoffs/2026-07-04-002-f20-country-rivals-h2h-audit.md) — optional chrome follow-up |

**Still open (not F6-class):** F1–F17 sticky/pin symptoms from reverted CD track; player games curl >0.8 s (HTML render, query fixed); F20 optional hash/reveal chrome.

**Recommended Dagh pass:** ~~S1/S1b on rating LB~~ — **passed 2026-07-04** (only table swaps; chrome stable).

---

## Changelog (this file) — continued

| Date | Entry |
|------|--------|
| 2026-07-04 | **Attempt 4 shipped** — 3d-b narrow snapshot + slim Δ + games-count cache |
| 2026-07-04 | **Attempt 5 shipped** — 3d-c TT y=0 chrome gate |
| 2026-07-04 | **WC hub TT fix** — `idx_community_facts_metric_period` + stats rows cache |
| 2026-07-04 | **Three slow LB wings** — honours, calendar-geo, peak-rating |
| 2026-07-04 | **Realm query sweep** — tracks A–I, census, playbook — handoff 004 |
| 2026-07-04 | **Issue closure summary** — F6/F18/F19 resolved; F20 query resolved |
| 2026-07-04 | **F6 Dagh sign-off** — S1/S1b pass on rating LB (only table swaps) |