# Tint vs realm

**Status:** Phases 0–4 complete in repo (May 2026). Header layout mock still deferred.

## Decision

| Control | Role |
|---------|------|
| **Tint** (`data-k2-accent` on `<html>`) | UI accent — links, nav rings, glows, chrome. **Not** tied to Online/Amiga. |
| **Realm** (`data-realm`) | Which ladder/universe (data, copy, APIs later). **Does not** set site paint. |

**Default tint on load:** follows a **six-hour rotation** when the visitor has not chosen a pill manually (see schedule below). CSS still defaults to amber when `data-k2-accent` is absent. Hub/player nav: **Tint** disclosure (closed by default) reveals **Amber · Pitch · Chrome · Holo** swatch pills to the left of the fixed right-side Tint anchor (Pulse removed — clashed with stat colours). Inactive choices stay subdued when open; palette stays open after a pick until the visitor closes it.

**Six-hour schedule** (`js/k2-tint-schedule.js`, booted from `theme_boot_head.php`):

| Local hour (visitor) | Tint |
|----------------------|------|
| 00:00–05:59 | Amber |
| 06:00–11:59 | Pitch |
| 12:00–17:59 | Chrome |
| 18:00–23:59 | Holo |

Clock source defaults to **visitor local time**. Set `localStorage['k2-accent-clock']` to `'utc'` to use UTC boundaries instead (no UI yet). Open tabs re-apply at each boundary via `setTimeout`.

**Manual override (this period only):** first visit in a window uses the **scheduled** tint. Clicking a pill stores `k2-accent-tune` + `k2-accent-manual-period` (e.g. `2026-05-29-2` = that calendar day’s third six-hour slot). That choice lasts **until the next six-hour boundary**, then the site returns to the schedule for the new period (visitor may pick again). Stale keys are cleared on load when the period id no longer matches.

| Tint id | Pure token | Hex |
|---------|------------|-----|
| `amber` | `--k2-pure-amber` | `#ffb74d` (default) |
| `pitch` | `--k2-pure-pitch` | `#9ccc65` |
| `chrome` | `--k2-pure-chrome` | `#64b5f6` |
| `holo` | `--k2-pure-holo` | `#b388ff` |

Each pill sets `--k2-accent: var(--k2-pure-*)`. That drives link-star and nav mixes; it does **not** recolour amber chart series (`--k2-amber-soft`) or milestone card glow (`--k2-pure-*` on the card).

**Full pointer model, mixes, and “when to use what”:** [`design-direction.md` § Color System](design-direction.md#color-system).

**Table stat overrides (`.blue` / `.red`):** default cyan / magenta. Chrome & Holo tint → green positives (separate from pure/chart tokens).

**Charts:** six inks in CSS; hub four = pure tokens above; **teal** and **magenta** are chart-only.

## Phases

### Phase 0 — Plan

- [x] This document

### Phase 1 — Behaviour + default amber

- [x] `--k2-accent` default amber; derived link/segment tokens use tint
- [x] `html[data-realm]` no longer sets accent colours
- [x] Tint pills set `--k2-accent` only
- [x] Realm switch does **not** clear tint

### Phase 2 — Rename sweep

- [x] `--k2-accent` everywhere (no `--k2-realm-accent`)
- [x] Realm switcher uses site tint (segment tokens), not per-realm colours

### Phase 3 — Docs

- [x] `design-direction.md`, `hub-ia-agreement.md`, `PROJECT_MEMORY.md`
- [x] Tint pills in hub (`hub_nav.php`, `theme.css`, `realm-switch.js`)
- [x] Same tint picker on player nav (`player_nav.php`, shared `k2_tint_picker.php`)

### Phase 4 — Long-lived preference

- [x] Tint choice writes to `localStorage` and survives browser restarts
- [x] Early head boot reads the saved tint before first paint
- [x] Existing `sessionStorage` tint value migrates once to `localStorage`
- [x] Open tabs sync tint changes through the browser `storage` event

### Phase 5 — Six-hour schedule

- [x] `k2-tint-schedule.js` shared by head boot + `realm-switch.js`
- [x] Auto tint from local six-hour slots unless manual override
- [x] Timer rolls scheduled tint at period boundaries in open tabs
- [x] Optional UTC via `localStorage` `k2-accent-clock` = `utc`

### Header placement lab

- [x] `status-realm-lab.php?variant=identity` — mock Status shell with realm beside the wordmark and search isolated on the right.
- [x] `status-realm-lab.php?variant=strip` — mock Status shell with header search alone and a realm strip above hub nav.
- **Jun 2026:** Lab page removed; `status-realm-lab.php` **302 → `status.php`**. Production uses `site_header.php` (realm switcher hidden in CSS until Amiga).
- [x] Identity layout temporarily promoted to shared `site_header.php` as a visual discussion prompt.
- [ ] Decide whether to keep identity layout for deploy and whether to update search behavior.

### Deferred

- Search scoped to `data-realm`

## Smoke checklist (after deploy)

1. **Cold load (schedule)** — tint matches current six-hour slot; correct pill active.
2. **Manual Pitch** — green accent; survives reload within the same six-hour window; reverts to schedule after the boundary.
3. **Chrome / Holo** — manual preference survives browser restart.
4. **Online ↔ Amiga** — tint unchanged.
5. **status.php**, **ranked7.php**, **individual1.php** — player names follow tint.
6. **Long-open tab** — scheduled tint updates within ~1s of six-hour boundary (no reload).
