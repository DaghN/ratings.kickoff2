# Page nav spacing -- policy

**Status:** **Locked intent (Jun 2026).** Phases 1-3 shipped -- implementation + audit: [`nav-spacing-implementation-plan.md`](nav-spacing-implementation-plan.md).
**Authority:** Product + visual contract; defers to [`design-direction.md`](design-direction.md) for segment grammar and surface rhythm. Dagh's latest chat wins on scope.
**Realms:** Online and Amiga -- **one rhythm, two realms** (same tokens and rules in `theme.css`; parallel includes must not fork spacing).

---

## Purpose

Unify vertical gaps between **page chrome navigation** and **content** so the site feels ordered by one hand -- not bar-by-bar or page-by-page ad hoc margins.

**Trigger:** Leaderboards **League honours** (segment pills + table) looked correctly spaced; **Milestones** and other plain table wings felt tight (~4px). Investigation showed a **half-built** spacing system: sub-nav pages got tokenized 12px rhythm; plain wing-to-table pages fell back to a vestigial `margin-bottom: 4px` on `.k2-chrome-tabs`, while a dead CSS rule (`.k2-chrome-tabs__bar + .k2-table-wrap`) never matched real markup.

This policy locks the **target model** and **scope** for a one-time fix that points forward.

---

## Locked decisions

| ID | Decision |
|----|----------|
| N1 | **Uniform gap:** `--k2-nav-gap: 12px` is the standard vertical space **below any page-chrome stack layer** (nav rows, entity hero cards, chapter blocks) until the next stack layer or content. |
| N2 | **Bottom-only ownership:** Each nav layer owns `margin-bottom: var(--k2-nav-gap)`. **Content never sets `margin-top`** for chrome spacing. Sub-layers never use `margin-top` for stack spacing. |
| N3 | **No `:has()` spacing switches:** Inter-nav and nav-to-content gaps are the same value under N1, so conditional zeroing (`:has(+ sub-nav)`) is unnecessary in the end state. Delete those lists in Phase 2 -- do not extend them. |
| N4 | **Cards with gaps, not nav caps:** Tables and panels stay bordered cards with visible air above them. Retire the dead "bar + table wrap" rule and defensive border-top reassertions tied to a never-shipped "cap" look. Scroll-mirror active state may still need local border fixes -- verify visually after retirement. |
| N5 | **One token spine:** Single `--k2-nav-gap` only (Phase 2 dropped legacy alias tokens). |
| N6 | **Optional hub exception (product gate):** After smoke, Games hub, Milestones hub, and LB Activity sub-tabs may keep **`--k2-hub-subnav-gap: 16px`** on **those three class hooks only** if 12px feels cramped. Never a tier system; never reintroduce `:has()`. Default recommendation: **collapse to 12px** (Option A). |
| N7 | **Realm parity:** Amiga hub, LB, player, and WC includes follow the same rules as online. No Amiga-only spacing forks. |
| N8 | **Markup normalization in scope:** Fix redundant `lb_nav_end.php` close, one `.k2-page-nav` close per shell (Games + Amiga WC shells currently diverge from Milestones shell). Spacing CSS must not rely on misleading wrapper comments. |
| N9 | **Optional semantic classes:** `k2-chrome-tabs--wing` / `--sub` allowed for grep/audit/documentation **only** -- zero CSS dependency unless a future slice explicitly adds it. |
| N10 | **Player nav 20px:** `.k2-player-nav-bar` is shared (e.g. `amiga/tournament.php` tournament + stages nav). Uniform 12px applies unless smoke shows tournament pages need a **documented local exception** -- not a silent 20px leftover. |

---

## Page chrome stack (in scope)

Applies to blocks inside `.k2-page-nav` opened by `includes/site_header.php`:

```text
[Amiga time-travel stamp when active]
.k2-hub-bar              -> primary hub tabs
.k2-hub-chapter          -> section title + lede (block bottom joins stack)
.k2-player-hero          -> player entity card (online + Amiga player wing)
.k2-amiga-tournament-hero -> tournament entity card (Amiga tournament detail)
.k2-chrome-tabs          -> wing ribbon (Rating | Activity | ...)
[optional sub-nav]        -> second segment row OR honours panel OR player inner nav
content                   -> .k2-table-wrap, charts, panels, <main> body
```

**Three content patterns** (all obey N2 in the end state):

| Pattern | Example | Spacing model |
|---------|---------|----------------|
| Wing -> content | LB Rating, Milestones | Wing `margin-bottom: 12px` |
| Wing -> sub-nav -> content | LB Activity, WC inner tabs | Wing `mb: 12px` + sub-nav `mb: 12px` (24px wing-to-table) |
| Wing -> panel -> pills -> content | LB League honours | Panel is a nav-like block; subnav `mb: 12px`; no special `:has()` |

**Player wing** adds hero + `.k2-player-nav-bar` above wing/sub-nav; same bottom-only rule on each layer.

**Header rhythm:** `.k2-site-header` padding and `.k2-hub-bar { margin: 16px 0 ... }` top margin are **header-to-hub** rhythm, not `--k2-nav-gap` (hub bar **bottom** uses `--k2-nav-gap`).

---

## Nav-like blocks -- Phase 2 audit list

Spacing migration must sweep **margin-bottom** on every block that separates nav from content -- not only bare `.k2-chrome-tabs`:

| Block | Current note | End state |
|-------|--------------|-----------|
| `.k2-hub-bar` | bottom already 12px via old token | `--k2-nav-gap` |
| `.k2-hub-chapter` | block `margin-bottom: 10px` | **10->12px** (stack only; title-to-lede internal rhythm stays local) |
| `.k2-chrome-tabs` (wing) | **4px** (bug) | `--k2-nav-gap` |
| Sub-nav chrome (activity, games hub, ms hub, WC, player opponents/milestones) | mixed 12/16px + `margin-top` lists | `margin-bottom: var(--k2-nav-gap)` or N6 exception; **`margin-top: 0`** |
| `.k2-lb-league-honours` panel | bespoke `margin-top` + subnav `margin-bottom` | drop panel `margin-top`; subnav `margin-bottom: var(--k2-nav-gap)` |
| `.k2-player-nav-bar` | **20px** default; **12px** on `body.k2-player-wing` | **20->12px** or documented exception (N10) |
| `.k2-player-hero` / `.k2-amiga-tournament-hero` | player wing **6px**; tournament **16px** | **`margin-bottom: var(--k2-nav-gap)`** (entity heroes in chrome stack) |
| Amiga tournament detail nav | reuses `.k2-player-nav-bar` on `tournament.php` | **12px** via shared `.k2-player-nav-bar` rule (N10) |
| Amiga tournaments index filter | `.k2-chrome-tabs` in `.k2-amiga-tournament-index-segment-filters` | segment rows: inner `gap: --k2-nav-gap`; segment block → listbox: parent `.k2-player-games-filters` default **6px**; one `--k2-nav-gap` below whole filter block |
| Player wing top tabs | `.k2-chrome-tabs.k2-player-wing-tabs` | segment width; tournament detail keeps full-width `.k2-player-nav-bar` |

---

## Phase 3 audit (Jun 2026)

Grep pass on nav-like blocks in `theme.css`. **Token-only** swaps (12px → `var(--k2-nav-gap)`) produce **no visual change** at default token value. One **dead-rule delete** and one **legacy margin neutralize** (also no visual change on live pages).

### CSS changed

| Selector | Change | Visual impact |
|----------|--------|---------------|
| `.k2-hub-chapter__nav` | `12px` → `var(--k2-nav-gap)` | None (token = 12px) |
| `.k2-games-highlights-board-filter` | `12px` → token | None — check Highlights board filter → cards anyway |
| `.k2-realm-games-filters` | `12px` → token | None — check Games → All filters → table anyway |
| `.server-peak-period-leaderboards__subnav` | **Deleted** (Jun 2026 — entire legacy peak/period leaderboard embed CSS block removed from `theme.css`; markup never shipped on Activity after charts v2) | None |
| `.k2-hub-tabs` | `margin: 16px 0 12px` → `margin: 0` | **None** — live pages nest tabs in `.k2-hub-bar`, which already zeroes inner margin |
| `.k2-chrome-tabs > .server-peak-period-leaderboards` | **Deleted** (dead DOM + violated N2 `margin-top`) | None (selector never matched live markup) |

### Documented exceptions — intentionally unchanged

| Item | Value | Why |
|------|-------|-----|
| `.k2-player-opponents:has(.k2-player-opponents-h2h) .k2-player-opponents__nav` | `margin-bottom: 20px` | H2H picker block needs more air than table wing |
| `.k2-player-opponents__nav-row` (shipped Jun 2026) | flex row; `gap: var(--k2-nav-gap)` between wing + grain segments | Amiga Opponents **vs Player · vs Country** beside wing tabs — [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) §6 |
| `.k2-hub-bar` top margin | `16px` | Header-to-hub rhythm, not `--k2-nav-gap` |
| `.k2-hub-chapter-to-content-gap` | `22px` | HoF-only editorial gap; out of nav stack v1 |
| `.k2-page-nav .k2-table` | `margin-bottom: 16px` | Profile multi-table stack spacing, not page chrome |
| Panel-internal controls | various | Status period tabs, ms detail panel tabs, tier filters — out of scope |
| Pattern A/B wrapper markup | — | P3.2 deferred; spacing already correct via CSS |
| Online LB wing (`.k2-chrome-tabs` without scope class) | full-width bar | Filter toggles on right (`__filters`); **Amiga** LB uses `.k2-amiga-lb-tabs` segment width instead |

### Visual smoke (optional — confirm token-only paths)

| URL | What to eyeball |
|-----|-----------------|
| `/games/highlights.php` | Highlight board segment filter → first highlight block (~12px) |
| `/games/all.php` | Filter panel → games table (~12px) |
| `/player/opponents.php?id=…` (H2H tab) | Inner tabs → picker row — should still feel **roomier than 12px** (~20px; unchanged) |
| Any hub page | Hub tabs → chapter/wing — unchanged from Phase 2 smoke |

---

## Out of scope (v1)

Panel-**internal** navigation stays local until explicitly widened:

- Status room period segment tabs (`k2-status-period-competitions__period-tabs`)
- Milestone detail panel tabs, games highlights board filter, recent-tier filter, chronology tier filter
- Any control row inside a bordered panel that is not part of the page chrome stack

**Not in v1:** Pattern A vs B wrapper unification (LB sibling content vs player wrapper content) unless needed for spacing -- markup shapes may differ; spacing rules must still match.

**Not in v1:** `--k2-hub-chapter-to-content-gap` (22px HoF-only rule) -- evaluate separately; may remain a one-off editorial gap or merge later.

---

## Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Global `margin-top` on `.k2-table-wrap` | Doubles gaps on every sub-nav page that already owns bottom margin |
| Keep `:has(+ ...)` lists for each new sub-nav | Ad hoc; four parallel selector lists today; does not scale |
| Role classes **required** for spacing (`--wing` / `--sub`) | Only needed if wing-to-sub and sub-to-content gaps **differ** -- they do not under N1 |
| Tier system (8px inter-nav / 12px to content) | More complex; no product ask; honours already uses 12+12 |
| Preserve "table cap" (merge bar + table top border) | Dead rule never worked on main LB pages; honours/opponents use cards-with-gaps |
| Fix spacing only on Leaderboards | Amiga duplicates the same classes and includes; would drift immediately |
| Content sets its own top margin "when needed" | Breaks single ownership; causes double gaps and one-off page fixes |

---

## Implementation pointer

| Phase | Goal |
|-------|------|
| **1 -- Spine** | `--k2-nav-gap`; wing `4px->12px`; keep `:has()` temporarily; smoke |
| **2 -- Language** | Bottom-only everywhere; delete `:has()` and dead rules; markup + Amiga parity; drop token aliases |
| **3 -- Polish** | Grep stray nav margins; tokenize holdouts; delete dead rules; document exceptions |

Detail, file list, smoke URLs: [`nav-spacing-implementation-plan.md`](nav-spacing-implementation-plan.md). **Agent checklist (new nav bars):** [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md).

---

## Related docs

- [`design-direction.md`](design-direction.md) -- Chrome and layout; segment track grammar
- [`hub-ia-agreement.md`](hub-ia-agreement.md) -- Hub tab IA
- [`url-routes.md`](url-routes.md) -- Sub-hub navigation routes