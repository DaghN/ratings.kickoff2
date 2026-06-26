# K2 Turbo page-init checklist

**For agents — read before adding or editing page JavaScript that initializes widgets on load.**

Turbo Drive (`turbo.es2017-umd.js` + `k2-turbo-boot.js`) intercepts same-origin link clicks so the **jukebox `<audio>` stays alive** for gapless cross-page playback. Most site JS was written for full page reloads; Turbo breaks the old boot pattern unless you follow this contract.

**Do not add bare `DOMContentLoaded` boot on new page scripts.**

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

`k2-turbo-boot.js` defines `window.k2OnPageReady(fn)` — first load **and** every `turbo:load` (via `k2:page-ready`).

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
| **Jukebox** | `data-turbo-permanent` on `#k2-jukebox-root`; boots once on `<html>` |
| **Carry-scroll restore** | `k2_carry_scroll_restore.php` + `k2-carry-scroll.js` — restore scrollY **synchronously on `turbo:render`** (pre-paint) and set `Turbo.navigator.currentVisit.scrolled = true` to suppress Turbo's scroll-to-top. **No body-visibility cloak** (cloak = blank-page delay; restoring on `turbo:load` = wordmark flash because Turbo already painted at top). Anchor (`viewportOffset` of `nav[data-k2-carry-scroll]`) keeps the nav row visually stable; light downward-only re-assert on rAF / `fonts.ready` / `k2:page-ready` for late layout growth. **Hash landing links** are a separate path — see [§ Hash anchor landing](#hash-anchor-landing-turbo--carry-scroll) below |
| **Document/window listeners** | Register **once** (global click, scroll). Never per boot without a guard |
| **`setInterval` in boot** | Guard globally — see `metaRefreshInterval` in `status-period-competitions.js` |

---

## Reference files (copy nearest neighbour)

| Scenario | File |
|----------|------|
| Turbo boot + bridge | `js/k2-turbo-boot.js` |
| Simple widget + data-attribute guard | `js/player-search.js` |
| Chart + fetch + guard | `js/player-rating-chart.js` |
| Form filters + listbox | `js/individual3-filters.js`, `js/k2-realm-games-filters.js` |
| Carry-scroll + hash restore | `includes/k2_carry_scroll_restore.php` |
| Hash href + anchor (Amiga Countries roster) | `includes/amiga_countries_lib.php`, `amiga/countries/roster.php` |
| Hash href + anchor (LB table) | `includes/lb_player_filters.php`, `includes/amiga_lb_nav.php` |

---

## Before shipping

- [ ] Boot uses `k2OnPageReady` (not bare `DOMContentLoaded` only).
- [ ] Each widget root has an idempotent guard.
- [ ] No duplicate `setInterval` / duplicate document listeners per Turbo visit.
- [ ] Manual test: hard refresh → use widget → Turbo-nav away and back → widget still works.
- [ ] **Hash entry links:** Turbo-nav from another page → lands at `#fragment`, not page top (see [§ Hash anchor landing](#hash-anchor-landing-turbo--carry-scroll)).
- [ ] With jukebox playing: Turbo-nav across two pages — audio stays gapless.

---

## Escape hatch (rare)

`data-turbo="false"` on a link forces a full reload. Use only when a third-party script cannot be made Turbo-safe. Breaks gapless jukebox across that click — avoid for hub/player nav.

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

### The Turbo trap (symptom: lands at page top)

On **Turbo in-page navigation** (click from another page):

1. Turbo may run `performScroll()` **before** `window.location.hash` is applied.
2. If the target id is not in the live DOM yet, Turbo calls `scrollToTop()` (scrollY = 0).
3. A full reload with `#fragment` in the URL often works — so agents wrongly blame PHP/href and miss Turbo timing.

**Do not** fix this with bare `DOMContentLoaded` scroll snippets or `data-turbo="false"` on hub/table links unless you accept breaking gapless jukebox.

### Site infrastructure (use this — do not reinvent)

All hash landing is centralized in **`includes/k2_carry_scroll_restore.php`** (included from `k2_head.php` on every themed page):

| Mechanism | Purpose |
|-----------|---------|
| `hashTargetId()` | Reads `location.hash` or pending hash from click |
| Click capture on `a[href*="#"]` | Stores `k2:pendingHashScroll` **before** Turbo rewrites history; clears carry-scroll payload |
| `beginHashScrollWatch()` | Suppresses Turbo scroll-to-top (`currentVisit.scrolled = true`); scrolls with `scroll-margin-top`; re-runs on `turbo:load`, `k2:page-ready`, `ResizeObserver` (~3.5s) for ranked-table cloak reveal |
| Carry-scroll restore | **Skipped** when a hash target is active — hash wins |

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
    $href = k2_amiga_route('amiga-countries-roster', ['country' => $countryToken]);
    return $scrollToHero ? $href . k2_amiga_country_roster_anchor_hash() : $href;
}
```

4. **CSS** — `height: 0; scroll-margin-top: …` on the anchor class (`theme.css` → `.k2-countries-scroll-anchor`, `.k2-lb-table-anchor`, `.k2-player-page-anchor`).

### Reference implementations

| Target | Fragment | Anchor markup | Href helper | Host page |
|--------|----------|---------------|-------------|-----------|
| Leaderboard table | `k2-lb-table` | `k2_lb_table_anchor_markup()` | `k2_lb_table_href()` | After `amiga_lb_nav.php` / online LB wing |
| Player hero | `player` | `K2_PLAYER_PAGE_FRAGMENT` in hero include | `k2_player_profile_href()` / `k2_amiga_player_profile_href()` | Profile heroes |
| Country roster hero | `k2-country-roster` | `k2_amiga_country_roster_anchor_markup()` | `k2_amiga_country_roster_href()` | `amiga/countries/roster.php` |
| Games highlights | `k2-games-highlights` | constant in `games_highlights_helpers.php` | `$scrollToAnchor` param on board URLs | Games hub |

Policy pointers: [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §6 (navigation), [`hub-ia-agreement.md`](hub-ia-agreement.md) (peer pill scroll vs hash).

### Agent checklist (hash landing)

- [ ] SSR emits `<div id="…" class="…-scroll-anchor" tabindex="-1">` **above** the visible block (not on the hub tab URL).
- [ ] Off-page links use a PHP href helper that appends `#fragment` (default on).
- [ ] Hub tab / wing tab URLs stay hash-free unless the hash target is **on that same page**.
- [ ] Did **not** add a one-off scroll script — `k2_carry_scroll_restore.php` owns Turbo timing.
- [ ] Manual test: **hard refresh** with hash URL → lands correctly.
- [ ] Manual test: **Turbo click** from another page (e.g. countries index → Denmark roster) → lands at hero/table, not page top.
- [ ] With jukebox playing: hash link still gapless (no `data-turbo="false"`).

### When changing hash scroll behaviour

Edit **`k2_carry_scroll_restore.php` only** unless you are adding a new fragment family (new constant + markup + CSS class). Do not duplicate pending-hash or `turbo:load` retry logic in page scripts.

---

**Related:** jukebox + Turbo ship note in `PROJECT_MEMORY.md` (2026-06-26). Gapless playback **requires** keeping one `<audio>` alive; there is no zero-gap alternative without SPA-style navigation. Hash anchor landing (Jun 2026): Amiga Countries roster `#k2-country-roster` — [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §6.