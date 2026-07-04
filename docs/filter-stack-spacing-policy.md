# Filter stack spacing -- policy

**Status:** **Shipped (Jul 2026).** Implementation: [`filter-stack-spacing-implementation-plan.md`](filter-stack-spacing-implementation-plan.md).
**Extends:** [`nav-spacing-policy.md`](nav-spacing-policy.md) N1–N2 (same token, same bottom-only model).
**Authority:** Product + visual contract; defers to [`design-direction.md`](design-direction.md) for segment grammar. Dagh''s latest chat wins on scope.

---

## Purpose

Unify **vertical** spacing inside **filter composite stacks** (segment toggles + listbox pickers above sortable tables) so they obey the same mental model as page chrome nav — **one token, bottom-only, no wrapper `gap`**.

**Trigger:** Tier 1 filter pages stacked multiple spacing mechanisms (`margin-bottom` on wing tabs + wrapper `flex gap` + form `margin-top: 16px`), producing 18px / 22px seams where 12px was expected. Agents inherited a parallel “6px wrapper gap” dialect from an early tournaments-index slice.

**Goal:** One simple, grep-able rule for agents — extend N2 to filter stacks; keep `gap` only for **horizontal** picker rows.

---

## Locked decisions

| ID | Decision |
|----|----------|
| F1 | **Same token:** `--k2-nav-gap` (12px) for every **vertical stack layer** inside Tier 1 filter pages. No second vertical token. |
| F2 | **Bottom-only (extends N2):** Each vertical stack layer owns `margin-bottom: var(--k2-nav-gap)`. **No `margin-top`** on `.k2-player-games-controls` or segment rows for stack spacing. |
| F3 | **No wrapper vertical `gap`:** `.k2-player-games-filters` and `.k2-amiga-tournament-index-segment-filters` do **not** use `gap` for vertical rhythm. Use `display: block` or flex **without** column `gap`. |
| F4 | **Two tools only:** **`margin-bottom`** = vertical stack layers; **`gap`** = horizontal clusters (listbox fields in one row, chrome tab pills inside a bar). Never both on the same vertical seam. |
| F5 | **Tier 1 scope only (v1):** Five live pages (see §Scope). Tier 1b bare forms, Tier 2 bespoke surfaces, and Jun 2026 nav chrome remain unchanged unless this doc is amended. |
| F6 | **Non-layers:** Hash anchors (`.k2-player-games-filters-anchor`), hidden meta (`.k2-player-games-controls__meta`), and `display: none` / `[hidden]` nodes are **not** stack layers — `margin: 0` on anchors; hidden children do not own seams. |
| F7 | **Realm row spacing:** `.k2-realm-games-filters__row` vertical rhythm aligns to **`margin-bottom: var(--k2-nav-gap)`** in Tier 1 (replaces 18px row bottom). Horizontal picker `column-gap` tokens stay local. |
| F8 | **Frozen carve-outs (v1):** Do not change horizontal listbox gaps, Amiga games/all filter block `margin-top: 28px`, player-tournaments country/year **horizontal** gap, hero 24px, H2H 20px, or panel-internal filters. |

---

## Filter stack (in scope)

Applies to blocks using `.k2-player-games-filters` or `.k2-realm-games-filters` on Tier 1 pages:

```text
[optional: player wing tabs .k2-player-wing-tabs]   -> margin-bottom: --k2-nav-gap (page chrome; already N1)
.k2-player-games-filters                           -> margin-bottom: --k2-nav-gap to table (whole block)
  [segment row(s) .k2-chrome-tabs ...]              -> each row OR segment wrapper: margin-bottom: --k2-nav-gap
  [scope tabs .k2-amiga-player-games-scope-tabs]    -> margin-bottom: --k2-nav-gap (when present)
  form.k2-player-games-controls                     -> margin-top: 0; margin-bottom: 0 inside wrapper
    .k2-amiga-player-games-filter-row(s)            -> horizontal gap only (10px etc.)
[table / content]
```

**One owner per vertical seam:** the element **above** the boundary owns `margin-bottom`. The wrapper does not also use `gap` to the same next sibling.

**Show/hide:** `display: none` / `[hidden]` fields and rows are removed from layout; margin chain skips them (same as nav). Do not rely on `:last-child { margin-bottom: 0 }` when siblings may hide — give each **visible stack layer** a bottom margin, or one wrapper owns the seam below a group.

---

## Tier 1 scope (v1 pages)

| Page | Include / host | Wrapper class |
|------|----------------|---------------|
| `/amiga/tournaments.php` | `amiga_tournament_index_nav.php` | `.k2-amiga-tournament-index-filters` |
| `/amiga/player/games.php` | inline in `amiga/player/games.php` | `.k2-player-games-filters` |
| `/amiga/player/tournaments.php` | `amiga_player_tournaments_filters_nav.php` | `.k2-amiga-player-tournaments-filters` |
| `/games/all.php` | `k2_realm_games_all_filters_ui.php` | `.k2-realm-games-filters` |
| `/amiga/games/all.php` | `amiga_realm_games_all_filters_ui.php` | `.k2-amiga-realm-games-all-filters` |

---

## Out of scope (v1)

| Category | Examples | Why |
|----------|----------|-----|
| **Tier 1b** | `/player/games.php`, `/amiga/player/videos.php` | Bare `.k2-player-games-controls` without filter wrapper — follow-up slice |
| **Tier 2** | Tournament step nav, tournament games player picker, TT history picker, league with-player, activity geo controls | Bespoke layout/CSS — do not apply F3–F7 |
| **Page chrome nav** | Hub, LB, WC, player wing on profile/opponents | Shipped Jun 2026 — [`nav-spacing-policy.md`](nav-spacing-policy.md) |
| **Panel-internal** | Highlights board filter, status period tabs, milestone panel tabs | Nav policy v1 out of scope |
| **Horizontal-only** | `--k2-player-tournaments-listbox-gap` (40px), `--k2-realm-games-filter-gap` (20px), filter row `gap: 10px` | Layout width rhythm, not vertical stack |
| **Hub shell inset** | `.k2-realm-games-all > .k2-realm-games-filters { margin-top: 28px }` | Header-to-content on games hub, not filter-internal |

---

## Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Wrapper `flex gap: 6px` + form `margin-top` | Two owners per seam; caused 18px / 22px effective gaps |
| New token `--k2-filter-gap` | Duplicates `--k2-nav-gap`; agents must learn two vertical numbers |
| `gap` on wrapper “because children hide” | `display: none` works with margin chain; anchors need `margin: 0`, not gap |
| Unify entire site `gap` literals in one pass | Out of scope; panel internals and Amiga tournament CSS stay local |
| Tier 2 in same slice | High regression risk on tuned tournament / league / TT surfaces |

---

## Agent rule (one paragraph)

**Filter stacks (Tier 1):** vertical spacing = `margin-bottom: var(--k2-nav-gap)` on each stack layer; **no** column `gap` on `.k2-player-games-filters` or segment wrappers; **no** `margin-top` on `.k2-player-games-controls` for stack spacing; **horizontal** listbox rows may keep `gap`. Copy includes from §Scope; do not invent wrapper `gap` or form top margin.

---

## Related docs

- [`nav-spacing-policy.md`](nav-spacing-policy.md) — page chrome N1–N10 (parent policy)
- [`filter-stack-spacing-implementation-plan.md`](filter-stack-spacing-implementation-plan.md) — slices, CSS checklist, smoke URLs
- [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) — wing/sub-nav markup (filter includes listed in §1)
- [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) — sortable tables below filters
- [`design-direction.md`](design-direction.md) — segment track grammar