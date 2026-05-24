# Tint vs realm

**Status:** Phase 0–1 complete in repo (May 2026). Phase 2+ pending.

## Decision

| Control | Role |
|---------|------|
| **Tint** (`data-k2-accent` on `<html>`) | UI accent — links, nav rings, glows, chrome. **Not** tied to Online/Amiga. |
| **Realm** (`data-realm`) | Which ladder/universe (data, copy, APIs later). **Does not** set site paint in Phase 1. |

**Default tint on load:** amber `#ffb74d` (CSS on `html`). Hub pills: Chrome · Pulse · Holo override when chosen. Session restore via `sessionStorage` (`k2-accent-tune`) — persistence design deferred.

## Tokens (Phase 1)

| Token | Set by |
|-------|--------|
| `--k2-accent` | `html` default + `html[data-k2-accent="…"]` |
| `--k2-accent-muted`, `--k2-accent-glow` | Same |
| `--k2-realm-accent` (etc.) | **Aliases** of tint tokens for legacy CSS — remove in Phase 2 |

## Phases

### Phase 0 — Plan

- [x] This document

### Phase 1 — Behaviour + default amber

- [x] `--k2-accent` default amber; derived link/segment tokens use tint
- [x] `html[data-realm]` no longer sets accent colours
- [x] Tint pills set `--k2-accent` only (via `data-k2-accent` blocks)
- [x] Realm switch does **not** clear tint (`realm-switch.js`)
- [x] Legacy alias `--k2-realm-accent: var(--k2-accent)` for feast CSS
- [ ] Realm switcher button styling neutral (Phase 2)

### Phase 2 — Rename sweep

- [ ] Replace `--k2-realm-accent` in all stylesheets with `--k2-accent`
- [ ] Remove alias variables
- [ ] Neutral realm switcher chrome

### Phase 3 — Docs

- [ ] `design-direction.md`, `hub-ia-agreement.md` — realm ≠ UI colour

### Deferred

- Header layout mock (realm vs search placement)
- Five tints + amber pill in hub
- `localStorage` / long-lived tint preference
- Search scoped to `data-realm`

## Smoke checklist (after deploy)

1. **Cold load** — no session tint: links/nav rings **amber**; Online active on realm switch.
2. **Show tint** → Chrome — links blue; reload same tab — still Chrome (session).
3. **Online ↔ Amiga** — link colour **unchanged**; only realm button state changes.
4. **Re-click active Online** — tint **not** cleared.
5. **status.php**, **ranked7.php**, **individual1.php** — player names use current tint.
