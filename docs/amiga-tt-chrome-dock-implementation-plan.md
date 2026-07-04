# Amiga TT chrome dock — implementation plan

**Status:** **Not started** (Jul 2026) — slices **0 → 6** below.  
**Policy:** [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) (CD1–CD10)

**Migration:** **L0** — UI/JS/CSS only; **no Part B**.

**Supersedes when complete:** C02 optional pin (`k2-amiga-time-travel-pin.js` behaviour + default), stamp-above-ribbon render order.

---

## How to use this plan

1. Execute slices **in order** — later slices assume earlier contracts (wrapper, carry gate, dock geometry).
2. Run each slice **Verification** before starting the next.
3. Do **not** commit unless Dagh asks.
4. After **slice 6** (or agreed stop): **UPDATE_DOCS** Part A — MEMORY, policy status, parent `amiga-time-travel-policy.md` §5.0/§5.1, `design-direction.md`, `hub-ia-agreement.md`, `creative-ideas-july-2026.md` §6.1, `with-player-stepper-policy.md` WP12.
5. Read [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) before slice 0 — **do not** naive reorder without wrapper + script contracts.

**Local smoke base URL:** `http://ratingskickoff.test/amiga/leaderboards/rating.php?as=event:589` (adjust event id as needed).

---

## Architecture (locked)

### Chrome stack (target DOM)

```text
site_header.php
  <header class="k2-site-header"> … </header>
<div class="k2-page-nav">
  <div class="k2-amiga-tt-chrome">          ← slice 0
    <section class="k2-amiga-time-travel …">
      <div class="k2-amiga-time-travel__bar"> … stepper data-k2-carry-scroll … </div>
    </section>
    <aside class="k2-amiga-tt-stamp"> … </aside>
    <script sync> k2-amiga-tt-stamp.js </script>
  </div>
  … hub chapter, hub bar, body …
</div>
```

### Script ownership (target)

| Concern | Owner | Notes |
|---------|--------|-------|
| Scroll-linked `top` + bar geometry | Dock coordinator JS | Evolve `k2-amiga-time-travel-pin.js` → `k2-amiga-tt-chrome-dock.js` (rename optional slice 2) |
| Pre-paint dock / opt-out class on `<html>` | Inline head boot + coordinator | Slice 5 |
| Carry-scroll reveal gate | `k2_carry_scroll_restore.php` | Slice 1 — stamp root must exist on Amiga `as=` pages |
| Stamp theatrical motion | `k2-amiga-tt-stamp.js` | Slice 4 — CD7 |
| Toggle entry cloak | `k2_head.php` + stamp JS | Unchanged contract; stamp still sync-loaded after markup |
| Storage | `localStorage` | Slice 3 — see §Storage migration |

### CSS variables (target)

| Variable | Set by | Used for |
|----------|--------|----------|
| `--k2-site-header-height` | Dock coordinator (measure `.k2-site-header`) | Scroll threshold + dock top |
| `--k2-tt-dock-top` | Dock coordinator | Pinned bar `top` (header height or 0) |
| `--k2-tt-dock-bar-height` | Dock coordinator (measure bar) | Section min-height reserve |

### Class vocabulary (target)

| Class | Element | Meaning |
|-------|---------|---------|
| `k2-amiga-tt-chrome` | Wrapper | Ribbon + stamp unit |
| `k2-amiga-tt-dock-active` | `<html>` or ribbon section | Dock enabled (default when `as=`) |
| `k2-amiga-tt-dock-opt-out` | `<html>` or ribbon section | User pushed pushpin — float with page |
| `k2-amiga-time-travel--docked` | Ribbon section | Fixed bar geometry active (replaces/alises `k2-amiga-time-travel--pinned`) |

**Decision (slice 2):** prefer **`--docked`** as primary class; keep **`--pinned`** as alias one release if needed for cached CSS — then remove.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Wrapper + ribbon → stamp render order | Visual order correct; no behaviour change yet |
| **1** | Carry-scroll stamp gate | No stamp pop-in on chevron nav |
| **2** | Default scroll-linked dock | Header reachable; ribbon at viewport top after header scroll |
| **3** | Pushpin opt-out + storage | Invert C02 semantics; preference persists |
| **4** | CD7 stamp motion | Chevrons/picker silent; wing/toggle theatrical |
| **5** | Pre-paint boot + nav polish | No ribbon vanish on chevron with dock on |
| **6** | Docs sweep | Policy/plan status = shipped |

---

## Slice 0 — Wrapper + render order

### Goal

Introduce `.k2-amiga-tt-chrome` and **CD1** order (ribbon → stamp) without enabling dock. Establish markup/script contracts for later slices.

### Files

| File | Change |
|------|--------|
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Wrap ribbon `<section>` + `amiga_time_travel_stamp_render()` in `<div class="k2-amiga-tt-chrome">`; move stamp render **after** ribbon `</section>`, **before** deferred ribbon scripts OR move deferred scripts outside wrapper (see note) |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | Minimal wrapper rule (spacing only if needed); update stamp placement comment |

**Script order note:** Keep `k2-amiga-tt-stamp.js` **sync immediately after stamp** inside wrapper. Ribbon defer scripts (`k2-archive-listbox.js`, `individual3-filters.js`, pin/dock JS) may stay after wrapper close — stamp must not follow them.

### Tasks

- [ ] Add `k2-amiga-tt-chrome` open/close in `amiga_snapshot_chrome_render_active()`
- [ ] Render order: ribbon section → stamp → stamp sync script (via `amiga_time_travel_stamp_render`)
- [ ] Confirm `amiga_time_travel_stamp_js_enqueue(false)` still non-deferred
- [ ] Grep: no CSS `flex-order` hack on `.k2-page-nav` for reorder

### Verification

```powershell
# PHP syntax
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site\public_html\includes\amiga_snapshot_chrome.php
```

**Browser (hard refresh):**

- Any Amiga page with `as=` — ribbon **above** stamp
- Toggle entry arrival still works (`k2_tt_entry=1` from present)
- Wing tab LED fade still works
- C02 pin still behaves as today (optional, default off)

### Acceptance

- [ ] CD1 visual order on rating LB, player profile, tournament page
- [ ] No PHP notices; stamp a11y label present
- [ ] Deferred pin JS still loads (pushpin visible on Event/Month/Year bar)

---

## Slice 1 — Carry-scroll stamp gate

### Goal

Fix stamp pop-in on chevron / picker navigation: body must not uncloak until **both** ribbon stepper nav and stamp root exist.

### Root cause (recap)

`k2_carry_scroll_restore.php` `carryReady()` returns true when `aria-label="Time travel snapshot"` nav exists. With ribbon-first order, that nav appears **before** stamp is parsed → early reveal → stamp pops in.

### Files

| File | Change |
|------|--------|
| [`k2_carry_scroll_restore.php`](../site/public_html/includes/k2_carry_scroll_restore.php) | On Amiga `as=` pages (detect via `html[data-realm="amiga"]` + query or server flag from `k2_head.php`), extend `carryReady()`: require `.k2-amiga-tt-stamp` in DOM before reveal when carry payload active |
| [`k2_head.php`](../site/public_html/includes/k2_head.php) | Optional: emit `html` data flag `data-k2-tt-active="1"` when stamp would render — simplifies client gate |

**Preferred:** server sets `data-k2-tt-chrome="1"` on `<html>` in existing Amiga head path (same block as DSEG7 preload) when `as=` active.

### Tasks

- [ ] Add server hint on `<html>` when TT chrome active (if not inferrable cheaply client-side)
- [ ] Extend `carryReady()` / `tick()` to wait for `.k2-amiga-tt-stamp`
- [ ] Ensure hash-only navigations unchanged
- [ ] Timeout safety: existing `MAX_CLOAK_MS` still reveals (never stuck hidden)

### Verification

**Browser:**

1. Rating LB with `as=`, scroll down ~400px
2. Click **chevron** prev/next repeatedly (carry-scroll)
3. Click **picker** jump to another event
4. Hub pill with carry-scroll (e.g. Leaderboards → World Cups)

**Expect:** stamp visible in same frame as ribbon after reveal — no late pop-in.

### Acceptance

- [ ] No visible stamp appearing ~100–300ms after ribbon on chevron nav
- [ ] Normal navigations (no carry) unchanged — no unnecessary cloak
- [ ] `MAX_CLOAK_MS` fallback still uncloaks if stamp missing (broken page)

---

## Slice 2 — Scroll-linked dock (default on)

### Goal

**CD2, CD4, CD5:** ribbon dock **on by default**; `top` = header height until header scrolls away, then `top: 0`. Header clickable at scroll top.

### Files

| File | Change |
|------|--------|
| [`k2-amiga-time-travel-pin.js`](../site/public_html/js/k2-amiga-time-travel-pin.js) | Add scroll-linked `--k2-tt-dock-top`; default dock on; rename file optional |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | `.k2-amiga-time-travel--docked .k2-amiga-time-travel__bar { position: fixed; top: var(--k2-tt-dock-top, 0); … }`; section z-index 1390; min-height reserve |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Apply dock class from PHP only if needed for slice 5 — slice 2 may be JS-only default on |

### Tasks

- [ ] Measure `.k2-site-header` height (resize observer + load)
- [ ] Scroll handler (passive): if `scrollY < headerHeight` → dock top = header height; else → 0
- [ ] On `as=` page load with dock active: apply `--docked`, sync geometry (reuse C02 left/width sync)
- [ ] **Default on:** dock active when no opt-out in storage (slice 3 may refine key)
- [ ] Event wing: `flex-wrap` preserved when docked
- [ ] Remove hard-coded `top: 0` from C02 pinned rule or gate behind dock coordinator

### Verification

**Browser:**

| Step | Expect |
|------|--------|
| `scrollY = 0` | Header fully clickable; ribbon flush **below** header |
| Scroll until header gone | Ribbon snaps to viewport top |
| Scroll back to top | Ribbon moves down under header again |
| Narrow width / wrapped header | Remeasure; no overlap |

### Acceptance

- [ ] CD4/CD5 met on rating LB + long player games page
- [ ] Picker dropdown usable; z-index below header when docked under header
- [ ] No regression: stamp still scrolls (not fixed)

---

## Slice 3 — Pushpin opt-out

### Goal

**CD3:** invert C02 — pushpin **disables** dock; default is docked.

### Storage migration

| Legacy (`C02`) | New semantics |
|----------------|---------------|
| key absent | **Dock on** (changed from dock off) |
| `k2-amiga-tt-ribbon-pinned=1` | **Dock on** |
| New opt-out flag | `k2-amiga-tt-ribbon-dock-opt-out=1` → float with page |

On first read, if legacy pinned=0 explicitly set, treat as opt-out **or** ignore (product: old “unpinned” users get dock on — acceptable).

### Files

| File | Change |
|------|--------|
| [`k2-amiga-tt-chrome-dock.js`](../site/public_html/js/k2-amiga-time-travel-pin.js) | Read/write opt-out key; invert pushpin `aria-pressed` / labels |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Update pin tooltip `data-k2-help` copy per policy §6 |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | Style `.is-floating` or opt-out state on pushpin if needed |

### Tasks

- [ ] Pushpin click toggles opt-out, not dock-on
- [ ] Opt-out: remove fixed bar, clear geometry, ribbon in flow
- [ ] Re-enable dock: restore scroll-linked behaviour
- [ ] Update aria-labels: “Float time travel controls…” / “Keep time travel controls docked…”

### Verification

- [ ] Fresh browser profile: dock on without clicking pin
- [ ] Opt out → scroll long page → ribbon scrolls away
- [ ] Opt in again → dock restored after reload
- [ ] localStorage keys documented in policy §6

### Acceptance

- [ ] CD3 semantics match policy
- [ ] C02 users who pinned still get dock

---

## Slice 4 — CD7 stamp motion

### Goal

Chevrons, picker, hub tabs, sort links: **no** LED fade, no kicker typewriter. Wing tab + toggle entry: keep theatrical motion.

### Files

| File | Change |
|------|--------|
| [`amiga_time_travel_stamp.php`](../site/public_html/includes/amiga_time_travel_stamp.php) | Only emit `--led-fade-pending` / empty kicker when `k2_tt_entry=wing`; only `--arrival-pending` when toggle |
| [`k2-amiga-tt-stamp.js`](../site/public_html/js/k2-amiga-tt-stamp.js) | `initStamp()`: no accidental arrival clear that flashes; confirm chevron loads hit `else` branch |
| [`amiga_snapshot_chrome.php`](../site/public_html/includes/amiga_snapshot_chrome.php) | Confirm chevron hrefs do **not** append `k2_tt_entry` (already true) |

### Tasks

- [ ] Audit all `k2_tt_entry` append sites — only toggle + wing tab
- [ ] PHP: full kicker text in HTML on non-entry navigations (no empty kicker + typewriter)
- [ ] PHP: no `--led-fade-pending` except wing entry
- [ ] Manual: chevron 10× — stamp updates date with no fade animation

### Acceptance

- [ ] CD7 table in policy satisfied
- [ ] Toggle from present still animates
- [ ] Wing tab change still LED-fades

---

## Slice 5 — Pre-paint boot + polish

### Goal

Eliminate ribbon “disappear” flash on navigation with dock on: dock state and geometry applied as early as possible; coordinate with carry cloak.

### Files

| File | Change |
|------|--------|
| [`k2_head.php`](../site/public_html/includes/k2_head.php) | Inline script: if Amiga + `as=` + not opt-out (localStorage read) → `document.documentElement.classList.add('k2-amiga-tt-dock-active')` |
| [`k2-amiga-tt-chrome-dock.js`](../site/public_html/js/k2-amiga-time-travel-pin.js) | Idempotent init; sync geometry on `k2OnPageReady` immediately |
| [`theme.css`](../site/public_html/stylesheets/theme.css) | Optional: dock bar invisible until geometry synced **only if** flash persists — prefer head class over opacity hack |
| [`k2_carry_scroll_restore.php`](../site/public_html/includes/k2_carry_scroll_restore.php) | Reveal ordering: after dock class + stamp present (combine slice 1 + 5) |

### Tasks

- [ ] Head inline boot reads same storage key as coordinator
- [ ] First paint: docked bar at correct `top` (may need minimal inline `--k2-tt-dock-top` estimate — e.g. 64px — until measure)
- [ ] Chevron nav 10× with dock on: no full ribbon vanish
- [ ] Mobile: header wrap remeasure

### Acceptance

- [ ] Dock-on chevron nav subjectively stable (Dagh sign-off)
- [ ] No stuck cloak; no double flash

---

## Slice 6 — Docs sweep

### Goal

Mark track **shipped**; parent docs match behaviour.

### Files

| File | Change |
|------|--------|
| [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) | Status → shipped |
| [`amiga-tt-chrome-dock-implementation-plan.md`](amiga-tt-chrome-dock-implementation-plan.md) | Slice checkboxes; status shipped |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | §5.0 layer table + placement rows; remove “not shipped” callout |
| [`design-direction.md`](design-direction.md) | TT chrome stack order |
| [`hub-ia-agreement.md`](hub-ia-agreement.md) | Stamp/ribbon sentence |
| [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) | C02 → CD shipped note |
| [`with-player-stepper-policy.md`](with-player-stepper-policy.md) | WP12 default dock |
| [`AGENTS.md`](../AGENTS.md) | Trap text: shipped behaviour |
| [`PROJECT_MEMORY.md`](../PROJECT_MEMORY.md) | Recent log |

### Acceptance

- [ ] UPDATE_DOCS Part A complete
- [ ] No doc still says stamp above ribbon as shipped default

---

## End-to-end smoke checklist (after slice 5)

Use before calling track done (slice 6).

| # | Scenario | Pass |
|---|----------|------|
| 1 | Present → Time travel toggle | Arrival anim; ribbon docked; stamp below ribbon |
| 2 | Chevron ×5 on rating LB | No stamp pop-in; no ribbon vanish |
| 3 | Wing tab Event → Month → Year | LED fade on wing only |
| 4 | Picker jump | Silent stamp update |
| 5 | Hub pill carry-scroll | Scroll stable; chrome stable |
| 6 | Scroll top | Header clickable |
| 7 | Scroll deep | Ribbon at viewport top |
| 8 | Pushpin opt-out | Ribbon floats away on scroll |
| 9 | Event wing + `as_with=` listbox | Wrap + picker usable docked |
| 10 | Mobile ~390px width | Dock under header; no horizontal break |
| 11 | `prefers-reduced-motion` | No typewriter; dock instant |

---

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Stamp pop-in returns | Slice 1 gate + slice 0 script order — do not skip |
| Header height wrong on font load | Remeasure on `document.fonts.ready` + resize |
| Carry-scroll deadlock | Keep `MAX_CLOAK_MS` reveal; stamp optional fallback after timeout |
| Cached `theme.css` C02 pinned rules | Alias classes one release; bump cache busters |
| Duplicate scroll listeners | Single `__k2TtChromeDockBound` guard (mirror stamp.js pattern) |

---

## Probes / automation (optional)

No CLI probe required for L0 UI track. Optional future:

- `scripts/oneoff/amiga_tt_chrome_render_probe.php` — assert wrapper + order in rendered HTML fragment

Not a slice 0 blocker.

---

## Changelog

| Date | Note |
|------|------|
| 2026-07-04 | Plan created — slices 0–6 from CD policy |