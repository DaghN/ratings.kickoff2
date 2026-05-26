# Tint vs realm

**Status:** Phases 0–4 complete in repo (May 2026). Header layout mock still deferred.

## Decision

| Control | Role |
|---------|------|
| **Tint** (`data-k2-accent` on `<html>`) | UI accent — links, nav rings, glows, chrome. **Not** tied to Online/Amiga. |
| **Realm** (`data-realm`) | Which ladder/universe (data, copy, APIs later). **Does not** set site paint. |

**Default tint on load:** amber (`#ffb74d`) when `data-k2-accent` is absent (CSS on `html`). Hub picker: **Amber · Pitch · Chrome · Holo** (Pulse removed — clashed with stat colours), hidden by default behind **Show tint**. User tint choice persists in `localStorage` (`k2-accent-tune`), with a one-time fallback/migration from the old `sessionStorage` value for open pre-persistence sessions.

| Tint id | Hex | Notes |
|---------|-----|--------|
| `amber` | `#ffb74d` | Default |
| `pitch` | `#9ccc65` | Former “Amiga green” realm chrome — now a tint only |
| `chrome` | `#64b5f6` | |
| `holo` | `#b388ff` | |

**Table stat overrides (`.blue` / `.red`):** default cyan / magenta. Chrome & Holo → green positives.

**Charts:** fixed six-ink palette (pitch, chrome, holo, amber, teal, magenta) — see `docs/design-direction.md`. Hub tints match the first four; **teal** and **hot magenta** (`#ff4081`) are chart-only, not tint pills.

## Tokens

| Token | Set by |
|-------|--------|
| `--k2-accent` | `html` default + `html[data-k2-accent="…"]` |
| `--k2-accent-muted`, `--k2-accent-glow` | Same |

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

### Header placement lab

- [x] `status-realm-lab.php?variant=identity` — mock Status shell with realm beside the wordmark and search isolated on the right.
- [x] `status-realm-lab.php?variant=strip` — mock Status shell with header search alone and a realm strip above hub nav.
- [x] Identity layout temporarily promoted to shared `site_header.php` as a visual discussion prompt.
- [ ] Decide whether to keep identity layout for deploy and whether to update search behavior.

### Deferred

- Search scoped to `data-realm`

## Smoke checklist (after deploy)

1. **Cold load** — amber links; **Amber** pill active (or none set + amber visually).
2. **Pitch** — green accent; realm switch still independent.
3. **Chrome / Holo** — preference survives reload and browser restart.
4. **Online ↔ Amiga** — tint unchanged.
5. **status.php**, **ranked7.php**, **individual1.php** — player names follow tint.
