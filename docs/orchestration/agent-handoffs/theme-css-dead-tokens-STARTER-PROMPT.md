# Starter prompt — `theme.css` dead-token grep pass

> **Status: v1 EXECUTED Jul 3, 2026** — see `docs/DEAD_SURFACE.md` § Removed (Jul 2026) for the removal log. Audit script: `scripts/audit_theme_css_dead_tokens.py`. Remaining candidates kept on purpose: `.k2-status-room__now-col`, `.k2-status-room__panel-league` (protected family, grep-only proof). Optional v2 (page-scoped sheets) still open.

**Use a new chat** — one cleanup slice; no product features.  
**Goal:** Shrink `site/public_html/stylesheets/theme.css` by removing rules/selectors with **provable zero** runtime references.

**Not a track** — no policy doc or multi-slice plan required. This file is the whole contract.

**Read first:**
1. [`docs/DEAD_SURFACE.md`](../../DEAD_SURFACE.md) — prior removals + **do-not-delete** warnings (realm switcher re-shipped Jun 2026)
2. [`docs/design-direction.md`](../../design-direction.md) — Legacy cleanup + agent notes
3. [`docs/k2-table-and-games-plan.md`](../../k2-table-and-games-plan.md) — mobile `.k2-table-wrap { position: relative }` (Jul 2026; do not regress)

**Scope (v1):** `site/public_html/stylesheets/theme.css` **only**. Do not touch `amiga-tournament.css`, page-scoped sheets, or `fonts/` in pass 1.

---

## COPY INTO NEW CHAT

```
You are Dagh's **theme.css dead-token cleanup** agent — hygiene only, not a redesign.

**Mission:** Remove CSS rules in `site/public_html/stylesheets/theme.css` that have zero
references anywhere under `site/public_html/` (PHP, JS, includes). Shrink file size;
leave behaviour unchanged.

**Read first:**
- docs/DEAD_SURFACE.md (removed history + re-shipped `.k2-realm-switch*` warning)
- docs/design-direction.md (Legacy cleanup)
- docs/k2-table-and-games-plan.md (`.k2-table-wrap` mobile fix — do not touch)
- docs/orchestration/agent-handoffs/theme-css-dead-tokens-STARTER-PROMPT.md (this file)

**Scope (locked v1):**
- File: `site/public_html/stylesheets/theme.css` only
- Out of scope: other stylesheets, refactors, renaming surviving tokens, build pipeline

**Method — one batch at a time:**

1. **Inventory** — walk `theme.css` top to bottom by comment section (or ~50–100 lines).
   List candidate selectors: classes (`.foo`), ids (`#bar`), and attribute selectors tied to
   removed features. Skip `@font-face` unless clearly orphan.

2. **Prove zero hits** — for each candidate, grep `site/public_html/`:
   - PHP/includes: class strings in HTML and `class=` attributes
   - JS: `classList`, `className`, template strings, `querySelector(…)`
   - Also grep the selector string without the leading `.` / `#` (e.g. `k2-status-bridge`)
   - For `--k2-*` custom properties: grep `var(--name)` inside `theme.css` **and** other
     site CSS/JS before deleting the definition

3. **Never delete without explicit proof** — if grep is ambiguous, **keep** and note in handoff.

4. **Protected (grep alone is not enough to delete):**
   - `.k2-realm-switch*`, `.k2-site-header__realm*` — re-shipped Jun 2026 (DEAD_SURFACE)
   - `.k2-table-wrap`, `.visually-hidden`, ranked-table cloak (`.k2-ranked-cloak` / `k2RankedCloak`)
   - `body.k2-site`, hub chrome, tint picker (`.k2-tint-*`), player hero (`.k2-player-hero*`)
   - Status room grid (`.k2-status-room*`), games hub (`.k2-games-*`)

5. **Delete** — remove only proven-dead rule blocks (selector + declarations). One logical
   batch per commit-sized diff (~10–30 rules max). No drive-by formatting.

6. **Smoke** after each batch (hard refresh `ratingskickoff.test`):
   - `/status.php`
   - `/leaderboards/rating.php`
   - `/games/recent.php` + `/games/highlights.php`
   - `/player/profile.php?id=1` (or any known player)
   - `/amiga/leaderboards/rating.php`
   Report "smoke OK" or what broke.

7. **Record** — append removed blocks to `docs/DEAD_SURFACE.md` § Removed (this pass);
   one line in `PROJECT_MEMORY.md` Recent log when slice complete.

**Done when:**
- Grep pass finished for full `theme.css` (or Dagh says "stop for today")
- Short removal log in chat (selector → reason)
- Smoke URLs pass
- DEAD_SURFACE updated

**Do NOT:**
- Refactor surviving CSS or merge duplicate rules
- Change colours, spacing, or typography "while here"
- Delete `@media` blocks wholesale — prove inner selectors first
- Touch Amiga-only sheets in v1
- Assume PHP-dynamic classes are dead without grepping the PHP that emits them

**First message:** Confirm mission + scope, report `theme.css` line count + file size,
propose first comment section to audit (suggest starting at top or known legacy blocks
from DEAD_SURFACE candidates), ask Dagh whether to proceed batch-by-batch or stop after
first conservative batch.
```

---

## Verification checklist

| Check | Pass |
|-------|------|
| Every deleted selector had zero grep hits in `site/public_html/` | |
| Custom properties checked for `var()` consumers | |
| Smoke URLs (online + one Amiga) | |
| `DEAD_SURFACE.md` updated | |
| `PROJECT_MEMORY.md` one Recent log line | |

---

## Known starting points (from DEAD_SURFACE)

These families were **already removed** in Jun 2026 — grep `theme.css` for stragglers:

| Pattern | Note |
|---------|------|
| `.k2-realm-lab-*` | Realm lab retired |
| `.k2-status-league-toggle*` | Status Leagues Phase 1 |
| `.k2-status-bridge*` | Status room grid leftover |
| `body.k2-activity-charts-lab` | Activity v2 — **done** Jun 2026 |

**Re-shipped — do not delete:** `.k2-realm-switch*`, `.k2-site-header__realm*` (header realm switcher, Jun 2026).

---

## Optional v2 (out of scope for v1)

- Page-scoped stylesheets (`amiga-tournament.css`, `player-hero-rank.css`, …)
- Small Python audit script (selector extract + grep) if manual pass finds repeat patterns
- `theme.css` section reorder (cosmetic only — separate slice)

---

## After the pass

When v1 is complete or Dagh pauses: update [`DEAD_SURFACE.md`](../../DEAD_SURFACE.md) candidate row ("Legacy CSS tokens with zero grep hits") to **Done** or narrow remaining scope.