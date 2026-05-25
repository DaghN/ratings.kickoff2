# Kick Off 2 Ratings — Design Direction

**Status:** current design contract, May 2026. Phase A hub shell and Status Phase B v1.2 are shipped in repo; staging deploy is via WinSCP, not `git push`.

**Authority:** `PROJECT_BRIEF.md` owns product taste. This doc owns visual identity, theme tokens, chrome, and cosmetics-track guidance. Dagh's latest chat instruction wins on scope.

**Related:** `PROJECT_MEMORY.md` for current focus, `docs/hub-ia-agreement.md` for hub IA, `docs/STATUS_PAGE_DATA.md` for Status panels, `docs/tint-vs-realm.md` for tint vs realm.

---

## Current Contract

The ratings site should feel like a live, premium stats surface for a competitive retro community: dark, precise, vibrant, and alive. It should not clone kickoff2.com's museum/pixel-pitch look.

Use **neon noir statistics** as the shorthand:

- Deep charcoal/navy backgrounds, not flat black.
- Data leads; chrome supports it.
- Electric accents are sparing: links, active tabs, chart series, avatar rings, small highlights.
- Tables, numbers, and charts stay readable before they are decorative.
- No pixel fonts for body/table/chart text.
- No glow on everything.

**Branding:** shell copy is realm-neutral: **Kick Off 2 ratings** as the product idea, with the header wordmark currently **Kick Off 2**. "KOOL" remains community vocabulary, not the primary visual identity.

---

## Realm vs Tint

**Realm** chooses the ladder universe and data: `online` now, `amiga` later.

**Tint** chooses UI paint: `amber` default, plus `pitch`, `chrome`, `holo`. Tint is stored on `html[data-k2-accent]` and must not imply realm.

```html
<html data-realm="online" data-k2-accent="amber">
```

Rules:

- Charts stay realm-neutral and use the chart palette.
- Links, nav rings, avatar ring, and small UI accents derive from `--k2-accent`.
- The realm switcher uses segment outline styling, not realm-colored paint.
- Amiga/offline can later add photos/media without forking the design system.

---

## Color System

Canonical chart tokens live in `stylesheets/theme.css` and are exposed through `js/chart-theme.js`.

| Token | Hex | Role |
|-------|-----|------|
| `pitch` | `#9ccc65` | Games, profile subject, wins |
| `chrome` | `#64b5f6` | Active players, projections, opponent focus |
| `holo` | `#b388ff` | Cumulative established line |
| `amber` | `#ffb74d` | Default UI accent, goals |
| `teal` | `#4db6ac` | Reserved analyst/distribution ink |
| `magenta` | `#ff4081` | New established, rating distribution |

Text/link hierarchy:

| Layer | Rule |
|-------|------|
| Body/table data | `--k2-text-primary`, normal weight |
| Muted helpers/headings | `--k2-text-muted` |
| Player names/game IDs/profile highlights | `--k2-link-star`, weight 600 in dense tables |
| Prose/footer links | `--k2-link` |
| Positive/negative table stats | `.blue` / `.red` mapped to softened table stat tokens |
| Structure and rings | `--k2-accent` at full strength |

Do not add one-off hex colors in page CSS when a token exists.

---

## Typography

| Use | Typeface / rule |
|-----|-----------------|
| Body, tables, chart labels | IBM Plex Sans |
| Numbers | tabular numbers or IBM Plex Mono where useful |
| Display chrome | Exo 2 for wordmark, hero name/stat values, avatar initial |
| Panel/chart headings | `.k2-panel-heading`: small, muted, weight 600 |

Never use pixel/bitmap fonts for readable data.

---

## Chrome And Layout

Current shared chrome:

- `includes/site_header.php` for wordmark, player search, realm switcher.
- `includes/hub_nav.php` for Status / Leaderboards / Games / Trends / Records.
- `includes/lb_nav.php` for leaderboard wing tabs.
- `includes/player_nav.php` for player context tabs.
- `includes/k2_head.php` for shared CSS and early theme boot.

Navigation pattern:

- Hub, player, and leaderboard wings use **segment track + outline active cell**.
- Default hub nav style is `segment`.
- `?k2_hub_nav=solid|segment|soft` and `nav-preview.php` still exist as staging/tuning scaffolding; prune when no longer useful.
- Tint picker is hidden by default behind **Show tint**; launch exposure is still open.

Imagery:

- No repeating site-wide decorative banner.
- Use imagery where it earns its place, e.g. Status heritage box or future Amiga photos.
- Dense tables and charts should start high on the page.

---

## Current Page Contracts

| Area | Current truth |
|------|---------------|
| Status hub | `status.php` is the default landing; Phase B v1.2 room grid is shipped in repo. |
| Leaderboards | `ranked1`-`ranked5`, `ranked7`, `ranked8` use `k2-table.js` for simple sort/autorank. |
| Games | `server3.php` renders seven static day buckets using shared rated-game rows. |
| Player profile | `individual1.php` is the shipped feast layout; gradual copy/UX improvements only. |
| Player games | `individual3.php` uses server-side Result/Opponent filters, URL sort links, 100-row slices, and shared row rendering. |
| Records/Hall of Fame | `server2.php` is the Hall of Fame page; `ranked8.php` is Activity. |
| Charts | `server1.php` and profile charts use chart-theme helpers; avoid legacy green/blue/coral/purple names. |

---

## CSS And Tooling

Default path:

- No build step.
- Plain CSS custom properties and component classes in `stylesheets/theme.css`.
- Shared head include via `includes/k2_head.php`.
- Chart colors through `js/chart-theme.js`.

Tailwind:

- Not the site-wide strategy today.
- Theme-lab Tailwind/CDN experiments are retired.
- Do not introduce a build pipeline without explicit approval and deploy notes.

Legacy cleanup:

- `main2.css` is removed.
- Visible table styling belongs to `theme.css`.
- `elolist.css` remains only for compatibility hooks / ranked cloak. `elolist.js` is no longer used by the migrated leaderboard/player-games paths.

---

## Open Decisions

- Public launch treatment for tint picker: remove, keep hidden, or expose.
- Exact `<title>` rename timing from old KOOL wording to Kick Off 2 ratings.
- Realm routing once Amiga/offline data exists.
- Whether to prune hub-nav A/B tuning (`nav-preview.php`, `k2-hub-nav-tune.js`, `?k2_hub_nav=`) after staging review.
- Further profile copy/UX and fun stats block.

---

## Agent Notes

- Read this before CSS/theme/chrome work.
- Keep cosmetics slices small and reversible.
- Add shared assets through existing includes; do not duplicate `<link>` blocks.
- Preserve dense table functionality while changing visuals.
- Update this doc only when a visual contract changes, not for every tiny CSS tweak.

*Last pruned: May 2026 — current contract separated from retired theme-lab history.*
