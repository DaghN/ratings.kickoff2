# Amiga time travel — chrome dock policy (CD track)

**Status:** **Approved** Jul 2026 — product + technical direction locked; **not yet implemented**. Supersedes parts of C02 and §5.0 placement when shipped.

**Parent:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`design-direction.md`](design-direction.md)

**Related:** [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) (C02 optional pin — evolved here) · [`with-player-stepper-policy.md`](with-player-stepper-policy.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`nav-spacing-policy.md`](nav-spacing-policy.md)

**Implementation plan:** [`amiga-tt-chrome-dock-implementation-plan.md`](amiga-tt-chrome-dock-implementation-plan.md) (slices 0–6).

---

## 1. Executive summary

When `as=` is active, the **snapshot ribbon** becomes the primary, always-available navigator; the **temporal stamp** becomes a **derived readout** of the current cutoff choice — a receipt stamped onto the environment, not a master clock above the controls.

**Ribbon dock (default on):** scroll-linked sticky behaviour — ribbon sits **below the site header** at scroll top; once the header scrolls away, ribbon docks to **viewport top** (`top: 0`). Reclaims vertical space while keeping the header reachable and clickable.

**Chrome order:** site header → **ribbon** → **stamp** → hub chapter / page body.

**Pushpin:** inverts C02 — dock is **default**; pushpin **opts out** (ribbon scrolls with the page like today unpinned).

**Nav stability:** full-page loads (Turbo removed Jun 2026) require a **TT chrome coordinator** — wrapper markup, pre-paint boot, carry-scroll readiness — so ribbon dock and stamp do not flash, pop in, or “disappear” on chevron/hub navigation.

---

## 2. Product intent

### 2.1 Hierarchy

| Layer | Role | User mental model |
|-------|------|-------------------|
| **Site header** | Realm, mode toggle, search | Global chrome — always reachable at scroll top |
| **Snapshot ribbon** | Event · Month · Year + stepper + picker (+ with-player on Event) | **Vehicle** — how you move through time; always visible under dock |
| **Temporal stamp** | LED kicker + DSEG7 date + cursor | **Receipt** — where you landed after ribbon/picker choices |
| **Hub chapter / body** | Section title + content at cutoff | The **environment** the stamp labels |

**Rejected framing:** stamp above ribbon (“LED master, now enter my vehicle”). Stamp content rules (kicker, LED formats, toggle/wing motion) stay per [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §5.0 unless amended below.

### 2.2 Why default dock

- Long player/tournament/LB pages lose TT controls without sticky chrome (C02 motivation).
- Optional pin (C02) under-used the feature; users forget time travel mid-scroll.
- Scroll-linked dock preserves screen real estate after header scrolls off (see §4).
- Header + ribbon overlap bug (pinned bar at `top: 0` over header) is avoided by construction.

### 2.3 Present mode

Unchanged: no stamp, no ribbon, no dock. Header **Present day | Time travel** only.

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CD1** | **Ribbon before stamp** | In `.k2-page-nav`, snapshot ribbon markup **precedes** temporal stamp in DOM and visual order |
| **CD2** | **Default dock** | When `as=` active, ribbon dock is **on** without user action |
| **CD3** | **Pushpin = opt out** | Pushpin **disables** dock; ribbon returns to in-flow scroll (C02 unpinned behaviour). Preference persisted (see §6) |
| **CD4** | **Scroll-linked top** | Docked ribbon `top` = header height while header intersects viewport; `top: 0` once header has scrolled away |
| **CD5** | **Header always reachable** | At `scrollY ≈ 0`, ribbon must not paint over or block site header (wordmark, realm switcher, mode toggle, search) |
| **CD6** | **Stamp scrolls** | Temporal stamp is **not** docked; it scrolls with page content below the ribbon reserve |
| **CD7** | **Stamp motion on nav** | **Chevrons, picker, hub tabs, direct URL** (no `k2_tt_entry`): stamp updates **in place** — no LED fade, no kicker typewriter, no panel arrival. **Wing tab** (`k2_tt_entry=wing`): keep LED fade + kicker typewriter. **Toggle entry** (`k2_tt_entry=1`): keep full arrival. |
| **CD8** | **Carry-scroll preserved** | Ribbon stepper/wing tabs/picker forms keep `data-k2-carry-scroll`; dock must not fight carry-scroll restore |
| **CD9** | **Surfaces** | Same as snapshot chrome today — all Amiga pages with active `as=`; ops/import excluded (T10) |
| **CD10** | **Event wing wrap** | Docked ribbon may wrap (`flex-wrap`) on Event wing — same as C02 pinned |

---

## 4. Scroll-linked dock (CD4 detail)

### 4.1 Behaviour

```
scroll top (header visible):
  ┌─ site header (z-index 1300, in flow) ─────────────┐
  ├─ ribbon bar (fixed, top = headerHeight) ────────────┤  ← dock under header
  ├─ stamp (in flow, below ribbon reserve) ─────────────┤
  └─ page body ─────────────────────────────────────────┘

scrolled (header gone):
  ┌─ ribbon bar (fixed, top = 0) ───────────────────────┐  ← claims viewport top
  │  page content scrolls beneath ─────────────────────  │
  └─────────────────────────────────────────────────────┘
```

- Measure `--k2-site-header-height` from `.k2-site-header` (resize + wrap).
- Set `--k2-tt-dock-top` = header height or `0` on scroll (passive listener) + resize.
- Prefer **instant** snap at threshold; optional ≤120ms transition only if visual testing demands it.

### 4.2 Z-index ladder (unchanged intent)

| Layer | z-index | Notes |
|-------|---------|-------|
| Hub bar | 1210 | |
| Snapshot ribbon (in flow) | 1220 | picker below header when not docked |
| Site header | 1300 | search dropdown |
| Docked ribbon context | 1390 | same as C02 pinned section |
| Jukebox FAB | 1400 | |
| Tooltips | 1500 | |

When docked under header, ribbon is **physically below** header — picker panels stay below header z-index without covering it.

---

## 5. Chrome stack placement

### 5.1 Document order (when `as=` active)

Inside `.k2-page-nav`, top to bottom:

1. **`.k2-amiga-tt-chrome`** wrapper (new — §7)
   - **Ribbon** `<section class="k2-amiga-time-travel …">`
   - **Stamp** `<aside class="k2-amiga-tt-stamp">`
2. Hub chapter (`k2-hub-chapter`) where applicable
3. Hub bar / wing sub-nav / page body

Site header remains **outside** `.k2-page-nav` in `site_header.php`.

### 5.2 Amends parent policy §5.0

When this track ships, replace:

- “Temporal stamp … **above** snapshot ribbon” → **below** snapshot ribbon (CD1).
- Table row “stamp above ribbon” in §5.0 layer diagram → ribbon then stamp.

Stamp **content** rules (kicker strings, LED formats, DSEG7, a11y, cursor blink) remain unless CD7 narrows motion.

---

## 6. Pushpin opt-out (CD3)

| State | Behaviour | Storage |
|-------|-----------|---------|
| **Default (dock on)** | Scroll-linked dock active | `localStorage` absent or `k2-amiga-tt-ribbon-docked=1` (exact key TBD at implement — may migrate C02 key) |
| **Opt out (pushpin)** | No fixed positioning; ribbon in document flow | User toggles pushpin; persisted until re-enabled |

**UI copy (draft):** pushpin **pressed** = “Float time travel controls with the page” / **unpressed** = “Keep time travel controls docked while scrolling” (invert today’s C02 labels).

**Migration:** C02 `k2-amiga-tt-ribbon-pinned=1` maps to **dock on**; absent = dock on (new default). Users who never pinned get dock for free. Document mapping in slice 2.

---

## 7. Nav stability — no idiotic blinks

Full-page navigation destroys TT chrome every time. Jul 2026 order-swap experiment showed **naive reorder alone** causes stamp pop-in and carry-scroll uncloak before stamp exists. Dock-default will worsen flashes unless boot is coordinated.

### 7.1 Root causes (diagnosis)

| Symptom | Cause |
|---------|--------|
| Stamp pop-in after ribbon | Stamp parsed late; browser paints ribbon first |
| Stamp blink on every chevron | Carry-scroll reveals when stepper nav exists but stamp not yet in DOM |
| Ribbon “disappears” on nav | Body cloak (`k2-carry-cloak`) + dock class applied only after deferred JS |
| Pin flash | `k2-amiga-time-travel--pinned` / dock class not present on first paint |

### 7.2 Architecture — TT chrome wrapper

Introduce **`.k2-amiga-tt-chrome`** wrapping ribbon + stamp:

- Single render unit in `amiga_snapshot_chrome.php`.
- **Stable internal order:** ribbon HTML first, stamp second (CD1).
- **Script contract:** stamp sync JS (`k2-amiga-tt-stamp.js`) immediately after stamp markup inside wrapper; dock boot script early (head or inline after ribbon bar) — see §7.3.
- Carry-scroll anchor (`aria-label="Time travel snapshot"`) stays on ribbon stepper — unchanged.

**Do not** reorder by CSS `flex-order` on `.k2-page-nav` alone — too many sibling side effects.

### 7.3 Boot coordinator (target)

One coordinator (extend `k2-amiga-time-travel-pin.js` or rename to `k2-amiga-tt-chrome-dock.js`) owns:

| Responsibility | Detail |
|----------------|--------|
| Dock geometry | Header height, `--k2-tt-dock-top`, bar `left`/`width` sync (reuse C02 geometry) |
| Pre-paint dock | Inline `<head>` or first-body script: read `localStorage`, set `html.k2-amiga-tt-dock-active` / opt-out class **before first paint** when possible |
| Carry-scroll gate | Extend readiness: do not `reveal()` carry-cloak until **stamp root** exists when `as=` page (extend `k2_carry_scroll_restore.php` or coordinator hook) |
| Opt-out pin | Pushpin toggles dock off; geometry cleared |
| Scroll link | CD4 threshold |

### 7.4 Stamp script placement (hard requirement)

`amiga_time_travel_stamp_js_enqueue(false)` — **non-deferred** — must run **immediately after stamp markup** inside wrapper, regardless of ribbon-first order. Toggle entry head cloak (`html.k2-tt-arrival-pending` in `k2_head.php`) depends on early release.

### 7.5 Motion vs accidental blink (CD7)

| Navigation | `k2_tt_entry` | Stamp motion |
|------------|---------------|--------------|
| Present → Time travel toggle | `1` | Full arrival (cloak + fade + typewriter) |
| Wing tab change | `wing` | LED fade + kicker typewriter |
| Chevrons, picker, hub tabs, sort links | — | **None** — update text/LED in PHP; cursor CSS blink may restart on full reload (acceptable once pop-in fixed) |
| Browser back | — | Prefer restored scroll; no forced arrival |

---

## 8. Implementation slices (suggested order)

| Slice | Scope | Exit criteria |
|-------|--------|---------------|
| **0** | Wrapper `.k2-amiga-tt-chrome` + CD1 render order (ribbon → stamp); no dock yet | Order visible; stamp sync JS placement verified; carry-scroll still works |
| **1** | Carry-scroll gate: reveal only when stamp + ribbon parsed | No stamp pop-in on chevron nav in manual smoke |
| **2** | Scroll-linked dock default-on; CD4–CD5; migrate C02 CSS/JS | Header clickable at scroll top; ribbon at viewport top after header scrolls away |
| **3** | Pushpin opt-out (CD3); invert labels/tooltip; storage migration | Toggle persists; opt-out matches C02 unpinned scroll |
| **4** | CD7 motion policy in stamp JS/PHP | Chevrons/picker: no LED fade/typewriter |
| **5** | Pre-paint dock boot + polish | No ribbon vanish on chevron with dock on; mobile header wrap |
| **6** | Docs sweep: §5.0, design-direction, hub-ia, C02 note, AGENTS trap | Policy matches shipped behaviour |

**Audit:** manual smoke on rating LB chevrons, wing tabs, hub pill carry-scroll, Event wing wrap + picker, pinned-opt-out, toggle entry arrival, mobile narrow width.

---

## 9. Key files (current → target)

| File | Role |
|------|------|
| `includes/amiga_snapshot_chrome.php` | Wrapper render; ribbon → stamp order |
| `includes/amiga_time_travel_stamp.php` | Stamp markup + sync JS enqueue |
| `includes/k2_head.php` | Toggle arrival cloak; optional dock boot snippet |
| `includes/k2_carry_scroll_restore.php` | Stamp-aware carry reveal |
| `js/k2-amiga-time-travel-pin.js` | Evolve → dock coordinator (or rename) |
| `js/k2-amiga-tt-stamp.js` | CD7 motion gating |
| `stylesheets/theme.css` | Dock top var, wrapper spacing, C02 class rename/alias |
| `includes/site_header.php` | Unchanged structurally; header height source for CD4 |

---

## 10. Supersedes / amends

| Prior | Change |
|-------|--------|
| **C02** (optional pin, default off, `top: 0`) | **Superseded** by default dock + scroll-linked top + opt-out pushpin when shipped |
| **`amiga-time-travel-policy.md` §5.0 placement** | Stamp **below** ribbon when shipped |
| **`amiga-time-travel-policy.md` §5.1 ribbon placement** | “Below temporal stamp” → **above** stamp |
| **WP12** (with-player + optional sticky) | Dock default satisfies long-page `as_with=` scrubbing; update cross-ref when shipped |

Until slice 6 ships, **implemented behaviour remains C02 + stamp-above-ribbon**.

---

## 11. Open questions (non-blocking)

- **Storage key:** new `k2-amiga-tt-ribbon-docked` vs migrate `k2-amiga-tt-ribbon-pinned` — decide in slice 2.
- **Mobile:** dock under header may stack ~130px chrome at scroll top — acceptable per product; revisit only if user testing complains.
- **Reduced motion:** dock snap stays instant; stamp arrival respects `prefers-reduced-motion` (existing).

---

## 12. Changelog

| Date | Note |
|------|------|
| 2026-07-04 | Policy drafted from product discussion — ribbon-first, default scroll-linked dock, pushpin opt-out, nav stability architecture |