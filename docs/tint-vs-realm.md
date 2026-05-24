# Tint vs realm

**Status:** Phases 0–3 complete in repo (May 2026). Header layout mock still deferred.

## Decision

| Control | Role |
|---------|------|
| **Tint** (`data-k2-accent` on `<html>`) | UI accent — links, nav rings, glows, chrome. **Not** tied to Online/Amiga. |
| **Realm** (`data-realm`) | Which ladder/universe (data, copy, APIs later). **Does not** set site paint. |

**Default tint on load:** amber (`#ffb74d`) when `data-k2-accent` is absent (CSS on `html`). Hub picker: **Amber · Pitch · Chrome · Pulse · Holo**. Session restore via `sessionStorage` (`k2-accent-tune`) — long-lived persistence deferred.

| Tint id | Hex | Notes |
|---------|-----|--------|
| `amber` | `#ffb74d` | Default |
| `pitch` | `#9ccc65` | Former “Amiga green” realm chrome — now a tint only |
| `chrome` | `#64b5f6` | |
| `pulse` | `#f06292` | |
| `holo` | `#b388ff` | |

**Table stat overrides (`.blue` / `.red`):** default cyan / magenta. Chrome & Holo → green positives. Pulse → coral negatives (accent = default negative). Charts unchanged.

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
- [x] Five tint pills in hub (`hub_nav.php`, `theme.css`, `realm-switch.js`)
- [x] Same tint picker on player nav (`player_nav.php`, shared `k2_tint_picker.php`)

### Deferred

- Header layout mock (realm vs search placement)
- `localStorage` / long-lived tint preference
- Search scoped to `data-realm`
- **Accent pills at public launch** — hidden by default today; expose or remove later

## Smoke checklist (after deploy)

1. **Cold load** — amber links; **Amber** pill active (or none set + amber visually).
2. **Pitch** — green accent; realm switch still independent.
3. **Chrome / Pulse / Holo** — as before; session survives reload.
4. **Online ↔ Amiga** — tint unchanged.
5. **status.php**, **ranked7.php**, **individual1.php** — player names follow tint.
