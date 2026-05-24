# Tint vs realm

**Status:** Phase 0–2 complete in repo (May 2026). Phase 3 (full doc pass) mostly done; header mock deferred.

## Decision

| Control | Role |
|---------|------|
| **Tint** (`data-k2-accent` on `<html>`) | UI accent — links, nav rings, glows, chrome. **Not** tied to Online/Amiga. |
| **Realm** (`data-realm`) | Which ladder/universe (data, copy, APIs later). **Does not** set site paint. |

**Default tint on load:** amber `#ffb74d` (CSS on `html`). Hub pills: Chrome · Pulse · Holo override when chosen. Session restore via `sessionStorage` (`k2-accent-tune`) — long-lived persistence deferred.

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
- [x] Tint pills set `--k2-accent` only (via `data-k2-accent` blocks)
- [x] Realm switch does **not** clear tint (`realm-switch.js`)

### Phase 2 — Rename sweep

- [x] Replace `--k2-realm-accent` in stylesheets with `--k2-accent`
- [x] Remove legacy alias variables on `html`
- [x] Realm switcher uses segment active tokens (site tint), not amber/green per realm
- [x] `design-direction.md` / `hub-ia-agreement.md` — tint vs realm summary

### Phase 3 — Remaining docs (optional)

- [ ] Sweep other docs for stale “online amber / amiga green UI” wording
- [ ] `PROJECT_BRIEF.md` if it mentions realm colours

### Deferred

- Header layout mock (realm vs search placement)
- Five tints + amber pill in hub
- `localStorage` / long-lived tint preference
- Search scoped to `data-realm`

## Smoke checklist (after deploy)

1. **Cold load** — no session tint: links/nav rings **amber**; Online active on realm switch.
2. **Show tint** → Chrome — links blue; reload same tab — still Chrome (session).
3. **Online ↔ Amiga** — link colour **unchanged**; realm button uses current tint outline.
4. **Re-click active Online** — tint **not** cleared.
5. **status.php**, **ranked7.php**, **individual1.php** — player names use current tint.
