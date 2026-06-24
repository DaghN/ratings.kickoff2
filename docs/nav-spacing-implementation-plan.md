# Page nav spacing -- implementation plan

**Status:** Phases 1-3 shipped (Jun 2026). Track complete.
**Policy:** [`nav-spacing-policy.md`](nav-spacing-policy.md) (Phase 3 audit table lives there).
**Agents:** [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) -- read before new wing/sub-nav/hub shell.
**Primary file:** `site/public_html/stylesheets/theme.css`

---

## Phase 1 -- Spin (shipped)

- [x] `--k2-nav-gap: 12px` + wing `.k2-chrome-tabs` 4px->12px

## Phase 2 -- Language (shipped)

- [x] Bottom-only model; delete `:has()` lists + dead bar+table rule; token aliases removed
- [x] Markup: drop `lb_nav_end.php`; close `.k2-page-nav` on Games + Amiga WC shells

## Phase 3 -- Polish (shipped Jun 2026)

- [x] Grep/token audit; dead rules removed; exceptions documented in policy

## Agent onboarding (Jun 2026)

- [x] [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) -- reference includes, spacing rules, shell checklist
- [x] Wired into `AGENTS.md`, `kool-workspace.mdc`, `PROJECT_MAP.md`

---

## Smoke URLs (full stack)

See policy Phase 3 audit + Phase 2 paths (Milestones, League honours, Activity, Games, Amiga LB, WC, player opponents WDL).
