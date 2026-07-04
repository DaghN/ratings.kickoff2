# K2 Turbo page-init checklist

> **HISTORICAL (Turbo removed Jun 2026).** Turbo Drive was removed; the site uses normal
> full-page navigation again. Gapless music is now a **popup window** — see
> [`k2-jukebox-popup.md`](k2-jukebox-popup.md). The `k2OnPageReady` / `k2PageReady` /
> `k2:page-ready` API still exists via the tiny shim `js/k2-page-boot.js` (runs once per
> full load), so the boot patterns below remain a safe, recommended convention even though
> there is no longer an in-page navigation that re-executes body scripts. The Turbo-specific
> hazards (body-script re-exec, snapshot/cache cloak races, `turbo:*` events) **no longer
> apply**. Keep this file for context; do not reintroduce Turbo without revisiting it.

**For agents — read before adding or editing page JavaScript that initializes widgets on load.**

~~Turbo Drive (`turbo.es2017-umd.js` + `k2-turbo-boot.js`) intercepts same-origin link clicks so the **jukebox `<audio>` stays alive** for gapless cross-page playback.~~ (Removed Jun 2026.) Most site JS was written for full page reloads; the `k2OnPageReady` boot pattern below is still the recommended convention.

**Prefer `k2OnPageReady` / `k2PageReady` over bare `DOMContentLoaded` for new page scripts** (idempotent, future-proof).

---

## The trap

| Full reload | Turbo navigation |
|-------------|------------------|
| Every `<script>` runs | Head scripts run **once**; body scripts in the **new** page are **not** re-executed |
| `DOMContentLoaded` fires | Body is swapped; old listeners point at removed DOM |

Symptoms after the first in-page click: dead filters, blank charts, search boxes that never open, carry-scroll restore missing, duplicate widgets if boot runs twice without guards.

---

## Required pattern

### 1) Boot on every visit

`k2-page-boot.js` defines `window.k2OnPageReady(fn)` — runs once per full page load (dispatches `k2:page-ready`). ~~(Turbo-era: also re-ran on every `turbo:load`.)~~

```javascript
function boot() {
    document.querySelectorAll('.my-widget-root').forEach(initRoot);
}

(window.k2OnPageReady || function (fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
})(boot);
```

Fallback when `k2OnPageReady` is absent (should not happen on normal pages).

### 2) Idempotent init per root element

`k2OnPageReady` may run boot twice on the **first** full load (immediate + `turbo:load`). Turbo visits create **new** DOM nodes — boot must bind fresh roots **and** skip already-bound roots on the same visit.

```javascript
function initRoot(root) {
    if (root.getAttribute('data-k2-widget-bound') === '1') {
        return;
    }
    root.setAttribute('data-k2-widget-bound', '1');
    // attach listeners, fetch data, init chart…
}
```

Use a property (`root._k2FooBound = true`) when the root is a form and a data attribute is awkward — see `individual3-filters.js`.

### 3) Charts

`turbo:before-cache` in `k2-turbo-boot.js` calls `Chart.getChart(canvas).destroy()` on all canvases. Boot may create new charts on the next visit; guard the **root**, not the canvas instance.

Existing charts use `data-k2-chart-bound` on the widget root.

---

## Special cases (do not “fix” blindly)

| Case | Pattern |
|------|---------|
| **Sortable tables** | `k2-table.js` uses `k2PageReady` only (no immediate boot) — see [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) |
| **Archive listboxes** | Central re-init in `k2-archive-listbox.js` (`onPageReadyListbox` → `init(document)`); filter scripts still need form-level guards |
| **Jukebox** | Now a **popup window** (no in-page persistence needed) — `k2-jukebox-launcher.js` FAB + `jukebox.php`; see [`k2-jukebox-popup.md`](k2-jukebox-popup.md) |
| **Carry-scroll restore** | `k2_carry_scroll_restore.php` + `k2-carry-scroll.js`. **Current (post-Turbo, full-page nav):** a **pre-paint body cloak** (`html.k2-carry-cloak body{visibility:hidden}`) engages **only when** a carry payload or `#hash` target is pending, the scroll is applied inside a rAF loop the moment the document is tall enough (or the DOM is fully parsed), then the page reveals — `html` paints `--k2-bg-page` so the hold is a solid theme colour, not white. Hard 700 ms timeout + `load` listener guarantee it can never stay hidden. **Scroll top (Jul 2026, F6):** skip cloak when stored `{ y: 0 }` has **no nav anchor**; when `targetY <= 0` with anchor, delay reveal until **`.k2-hub-chapter`** or feast player hero exists (`carrySubRibbonReady`) — see [`2026-07-04-001-tt-chrome-baseline-slice-0.md`](orchestration/agent-handoffs/2026-07-04-001-tt-chrome-baseline-slice-0.md). ~~(Turbo-era: restore on `turbo:render` + `currentVisit.scrolled=true`, no cloak.)~~ |
| **Document/window listeners** | Register **once** (global click, scroll). Never per boot without a guard. **Body scripts re-run in full on every Turbo visit** — module-scope `document.addEventListener` / `window.addEventListener` / `setTimeout` chains stack one copy per navigation unless guarded with a `window.__flag`. Symptom: a toggle handler fires an even number of times and the control looks dead (see `k2-tint-toggle.js`, `realm-switch.js`, `k2-amiga-tt-stamp.js`, Jun 2026) |
| **CSS-animation cloaks** | If a class hides an element until `animationend` removes it, add a **fallback timeout** + clear the class on `turbo:before-cache`. Turbo's async body-script + snapshot timing can drop the `animationend`, freezing the cloak (see TT LED stamp, `k2-amiga-tt-stamp.js`) |
| **`setInterval` in boot** | Guard globally — see `metaRefreshInterval` in `status-period-competitions.js` |

---

## Reference files (copy nearest neighbour)

| Scenario | File |
|----------|------|
| Page-ready boot shim | `js/k2-page-boot.js` (replaced deleted `k2-turbo-boot.js`) |
| Simple widget + data-attribute guard | `js/player-search.js` |
| Chart + fetch + guard | `js/player-rating-chart.js` |
| Form filters + listbox | `js/individual3-filters.js`, `js/k2-realm-games-filters.js` |
| Carry-scroll + hash restore | `includes/k2_carry_scroll_restore.php` |
| Hash href + anchor (Amiga country roster) | `includes/amiga_countries_lib.php`, `includes/amiga_country_page.php` |
| Hash href + anchor (LB table) | `includes/lb_player_filters.php`, `includes/amiga_lb_nav.php` |

---

## Before shipping

- [ ] Boot uses `k2OnPageReady` (not bare `DOMContentLoaded` only).
- [ ] Each widget root has an idempotent guard.
- [ ] No duplicate `setInterval` / duplicate global document listeners (still good hygiene; the `window.__flag` guards are harmless on full loads).
- [ ] Manual test: hard refresh → use widget → navigate away and back → widget still works.
- [ ] **Hash entry links:** navigate from another page → lands at `#fragment`, not page top (see [§ Hash anchor landing](#hash-anchor-landing-turbo--carry-scroll)).
- [ ] Gapless music is now a **popup window** ([`k2-jukebox-popup.md`](k2-jukebox-popup.md)) — navigation no longer needs to preserve the `<audio>`.

---

## Hash anchor landing (Turbo + carry-scroll)

**Read this before adding `#fragment` links that should land mid-page** (profile hero, LB table top, country roster hero, games highlights, …).

### Product rule

| Link kind | Hash? | Example |
|-----------|-------|---------|
| **Hub tab** (primary nav pill) | Usually **no** — lands at page top | Countries tab → `/amiga/countries/index.php` |
| **Off-page / cross-surface entry** | **Yes** — skip chapter/lede, show target block | Index country row → roster `#k2-country-roster` |
| **Peer pill with carry-scroll** | **No** on wing tab URLs — carry-scroll replaces hash | LB wing tabs; player wing tabs |
| **Same-page in-page jump** | Yes | `#player`, `#k2-lb-table` on current path |

Do **not** point a hub tab at a drill-down URL just to scroll — hub tabs stay on the hub index; table/flag links append the hash.

### Why it can land at page top (current, post-Turbo)

On a normal full-page load, the browser paints the **top** of the page (wordmark) before
page JS can scroll to the `#fragment`. Restoring after paint = a visible jump from top to
target. The fix is the **pre-paint cloak** in `k2_carry_scroll_restore.php`: hold body
`visibility:hidden` until the target exists / page is tall enough, scroll, then reveal —
so the visitor never sees the top.

**Do not** fix this with bare `DOMContentLoaded` scroll snippets in page scripts — extend
`k2_carry_scroll_restore.php` instead (it owns the cloak + scroll timing).

~~(Turbo-era trap: Turbo ran `performScroll()` before `location.hash` applied and called
`scrollToTop()` if the id was not yet in the DOM. No longer applies.)~~

### Site infrastructure (use this — do not reinvent)

All hash landing is centralized in **`includes/k2_carry_scroll_restore.php`** (included from `k2_head.php` on every themed page):

| Mechanism | Purpose |
|-----------|---------|
| `hashTargetId()` | Reads `location.hash` or pending hash stored from a click |
| Click capture on `a[href*="#"]` | Stores `k2:pendingHashScroll` before navigation; clears any carry-scroll payload |
| Pre-paint cloak + rAF scroll | Cloaks body, scrolls to the target (honouring `scroll-margin-top`) once it exists / page is tall enough, then reveals; hard 700 ms + `load` safety nets; **strips `#hash` from the history entry after landing** so Back restores free scroll |
| **Browser Back** | `pagehide` stores scrollY per pathname+search; `back_forward` reload restores that Y (pre-paint cloak) instead of re-running hash landing |
| Carry-scroll restore | **Skipped** when a hash target is active — hash wins (except **browser Back**: saved `pagehide` scrollY wins over hash on `back_forward` reload) |

**`amiga_url_with_context()`** (and callers that append `#fragment` after it) must **preserve the hash** through query rewriting — do not strip `#…` when adding `as=`.

### PHP pattern (copy nearest reference)

1. **Constant** for fragment id (keep JS comments in sync if any).
2. **Zero-height target** immediately above the block users should see:

```php
// Fragment id + markup helpers — see includes/lb_player_filters.php, includes/amiga_countries_lib.php
echo k2_amiga_country_roster_anchor_markup(); // <div id="k2-country-roster" class="k2-countries-scroll-anchor" tabindex="-1">
include '…/amiga_country_hero.php';
```

3. **Href helper** appends hash for off-page entry links:

```php
function k2_amiga_country_roster_href(string $countryToken, bool $scrollToHero = true): string
{
    $href = k2_amiga_route('amiga-country-roster', ['country' => $countryToken]);
    return $scrollToHero ? $href . k2_amiga_country_roster_anchor_hash() : $href;
}
```

4. **CSS** — `height: 0; scroll-margin-top: …` on the anchor class (`theme.css` → `.k2-countries-scroll-anchor`, `.k2-lb-table-anchor`, `.k2-player-page-anchor`).

### Reference implementations

| Target | Fragment | Anchor markup | Href helper | Host page |
|--------|----------|---------------|-------------|-----------|
| Leaderboard table | `k2-lb-table` | `k2_lb_table_anchor_markup()` | `k2_lb_table_href()` | After `amiga_lb_nav.php` / online LB wing |
| Player hero | `player` | `K2_PLAYER_PAGE_FRAGMENT` in hero include | `k2_player_profile_href()` / `k2_amiga_player_profile_href()` | Profile heroes |
| Country roster hero | `k2-country-roster` | `k2_amiga_country_roster_anchor_markup()` | `k2_amiga_country_roster_href()` | `amiga/country/roster.php` (shell `amiga_country_page.php`) |
| Games highlights | `k2-games-highlights` | constant in `games_highlights_helpers.php` | `$scrollToAnchor` param on board URLs | Games hub |

Policy pointers: [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §6 (navigation), [`hub-ia-agreement.md`](hub-ia-agreement.md) (peer pill scroll vs hash).

### Agent checklist (hash landing)

- [ ] SSR emits `<div id="…" class="…-scroll-anchor" tabindex="-1">` **above** the visible block (not on the hub tab URL).
- [ ] Off-page links use a PHP href helper that appends `#fragment` (default on).
- [ ] Hub tab / wing tab URLs stay hash-free unless the hash target is **on that same page**.
- [ ] Did **not** add a one-off scroll script — `k2_carry_scroll_restore.php` owns the cloak + scroll timing.
- [ ] Manual test: **hard refresh** with hash URL → lands correctly, no top flash.
- [ ] Manual test: **click** from another page (e.g. countries index → Denmark roster) → lands at hero/table, not page top.

### When changing hash scroll behaviour

Edit **`k2_carry_scroll_restore.php` only** unless you are adding a new fragment family (new constant + markup + CSS class). Do not duplicate pending-hash or cloak/scroll logic in page scripts.

---

**Related:** jukebox popup ship note in `PROJECT_MEMORY.md` (2026-06-26) + [`k2-jukebox-popup.md`](k2-jukebox-popup.md). Gapless playback now lives in a **separate popup window** (no SPA / Turbo). Hash anchor landing (Jun 2026): Amiga Countries roster `#k2-country-roster` — [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §6.