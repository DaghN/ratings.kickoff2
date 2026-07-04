# Filter stack spacing -- implementation plan

**Status:** **Shipped (Jul 2026).**
**Policy:** [`filter-stack-spacing-policy.md`](filter-stack-spacing-policy.md) (F1–F8).
**Parent:** [`nav-spacing-policy.md`](nav-spacing-policy.md) N1–N2.
**Primary file:** `site/public_html/stylesheets/theme.css`
**Secondary:** `site/public_html/stylesheets/amiga-tournament.css` (dedupe segment-filter rules after theme.css is source of truth)

---

## Goal

Tier 1 filter pages: **12px vertical rhythm**, **bottom-only**, **no wrapper column `gap`**, **no form `margin-top`** inside filter wrappers. Expected wins:

- Amiga player games: wing pills → scope pills **12px** (not 18px)
- Tournaments catalog / player games: scope/toggles → listboxes **12px** (not 22px)
- Realm All: row stack **12px** between labelled rows (not 18px)
- One agent-discoverable pattern aligned with nav spacing

---

## Frozen carve-outs (do not change in this track)

- `--k2-player-tournaments-listbox-gap` (40px horizontal)
- `--k2-realm-games-filter-gap` (20px horizontal between picker fields)
- `.k2-amiga-player-games-filter-row` horizontal `gap: 10px`
- `.k2-realm-games-all > .k2-realm-games-filters { margin-top: 28px }`
- Global `.k2-player-games-controls { margin-top: 16px }` **outside** `.k2-player-games-filters` (Tier 1b pages)
- All Tier 2 surfaces (step nav, league with-player, TT picker, etc.)
- Jun 2026 nav exceptions (hero 24px, H2H 20px, …)

---

## Slice 1 — CSS spine (`theme.css`)

- [x] **Remove vertical wrapper gap:** `.k2-player-games-filters:not(.k2-realm-games-filters)` — delete `gap: 6px`; use `display: flex; flex-direction: column; gap: 0` or `display: block` (pick one; prefer block if no flex children need stretch).
- [x] **Remove tournaments override gap:** `.k2-amiga-player-tournaments-filters.k2-player-games-filters:not(.k2-realm-games-filters) { gap: var(--k2-nav-gap) }` — delete (replaced by margin layers).
- [x] **Segment inner wrapper:** `.k2-amiga-tournament-index-segment-filters` — remove column `gap`; each `.k2-chrome-tabs.k2-amiga-tournament-index-tabs` / `.k2-amiga-player-tournaments-tabs` inside gets `margin-bottom: var(--k2-nav-gap)`; zero margin on last row **only if** form immediately follows and form does not add top margin (prefer: every segment row keeps mb; form has mt 0 — last segment row owns toggle→listbox seam).
- [x] **Scope tabs inside filter wrapper:** `.k2-player-games-filters .k2-chrome-tabs.k2-amiga-player-games-scope-tabs` (and tournament index tabs when inside wrapper) — ensure `margin-bottom: var(--k2-nav-gap)` (override zeroing rule if needed for vertical stack only).
- [x] **Form inside wrapper:** `.k2-player-games-filters .k2-player-games-controls` — add `margin-top: 0` (player tournaments already has this on modifier; generalize to all `.k2-player-games-filters` children).
- [x] **Anchor non-layer:** `.k2-player-games-filters .k2-player-games-filters-anchor` — `margin: 0` (explicit).
- [x] **Whole filter block to table:** `.k2-player-games-filters:not(.k2-realm-games-filters)` — keep `margin: 0 0 var(--k2-nav-gap)` (already present).
- [x] **Realm rows:** `.k2-realm-games-filters__row` — `margin-bottom: var(--k2-nav-gap)` (from 18px).
- [x] **Amiga realm segment block:** `.k2-amiga-realm-games-all-segment-filters` — keep horizontal `gap`; vertical to form = `margin-bottom: var(--k2-nav-gap)` (already); confirm no double seam with form.

**Do not** change global `body.k2-site .k2-player-games-controls { margin-top: 16px }` unless Tier 1b is explicitly in scope.

---

## Slice 2 — Amiga player games seam (markup + CSS)

**Problem:** `.k2-player-wing-tabs` (outside wrapper) + `.k2-player-games-filters` (anchor + 6px gap inside) = 18px wing → scope.

- [x] **Option A (preferred):** Move `.k2-amiga-player-games-scope-tabs` **outside** `.k2-player-games-filters`, as sibling below wing tabs; wrapper contains only anchor + form. Wing tabs `margin-bottom: 12px` → scope tabs `margin-bottom: 12px` → form `margin-top: 0`.
- [x] **Option B:** Keep DOM; zero wing `margin-bottom` when immediately followed by `.k2-player-games-filters` — **rejected** (violates N2 simplicity; needs `:has()` or page class).
- [x] Verify hash `#k2-player-games-filters` still lands correctly (anchor can stay at top of filter block or move with scope).

Files: `site/public_html/amiga/player/games.php`, possibly `theme.css`.

---

## Slice 3 — Dedupe `amiga-tournament.css`

- [x] Remove or comment-sync duplicate rules for `.k2-amiga-tournament-index-segment-filters` / `.k2-amiga-realm-games-all-segment-filters` if identical to `theme.css` after Slice 1 (tournaments catalog loads both sheets).
- [x] Player tournaments page loads `player-feast.css` + `theme.css` only — no tournament.css dependency for filters.

---

## Slice 4 — Policy hygiene

- [x] Update [`nav-spacing-policy.md`](nav-spacing-policy.md) § Amiga tournaments index filter row — replace “parent 6px gap” with pointer to filter-stack policy F3.
- [x] Add filter-stack row to [`docs/UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A2 table.
- [x] Add one line to [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) §3 (filter stacks defer to filter-stack policy).
- [x] [`design-direction.md`](design-direction.md) — cross-link under page nav spacing bullet.

---

## Smoke URLs (Tier 1 — must pass)

| URL | Eyeball |
|-----|---------|
| `/amiga/tournaments.php` | 3 toggle rows each **12px** apart; last row → listboxes **12px**; block → table **12px** |
| `/amiga/player/games.php?id=66` | Wing → All games/World Cup **12px**; scope → first listbox row **12px** |
| `/amiga/player/tournaments.php?id=362` | Four toggles → country/year **12px**; block → table **12px**; horizontal country↔year gap unchanged |
| `/games/all.php` | Labelled rows **12px** apart; filters → table **12px** |
| `/amiga/games/all.php` | Segment toggles → picker rows **12px**; **28px** top inset unchanged; → table **12px** |

**Measured acceptance (DevTools):** wing bottom → scope top = **12px**; scope bottom → form top = **12px** (not 18 / 22).

---

## Regression guard (out of scope — must not change)

| URL | Expect |
|-----|--------|
| `/amiga/player/profile.php?id=66` | Hero **24px** → wing tabs unchanged |
| `/player/opponents.php?id=…` (H2H) | **20px** to picker block |
| `/amiga/tournament/event-stats.php?id=…` | Step nav + inline filters unchanged |
| `/player/games.php?id=…` | Unchanged if global form margin untouched |
| `/games/highlights.php` | Board filter unchanged |

---

## CSS grep audit (post-ship)

```text
rg "k2-player-games-filters.*gap" site/public_html/stylesheets/
rg "k2-amiga-tournament-index-segment-filters" site/public_html/stylesheets/
rg "margin-top: 16px" site/public_html/stylesheets/theme.css  # confirm only outside filter wrappers
```

---

## Agent checklist (before ship)

- [x] Read [`filter-stack-spacing-policy.md`](filter-stack-spacing-policy.md) F1–F8.
- [x] Open one Tier 1 reference include from policy §Scope; copy margin pattern, do not add wrapper `gap`.
- [x] No new page-specific vertical spacing literals — `--k2-nav-gap` only.
- [x] Run smoke + regression URLs above.
- [x] Part A docs: MEMORY + policy/plan status line when shipped.