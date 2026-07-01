# Self-hosted assets audit

**Purpose:** Inventory of runtime dependencies for the public site (`site/public_html/`). Goal: **no third-party CDN** for fonts, JS, or CSS that the themed shell needs on every page.

**Updated:** Jun 2026 (fonts moved off Google Fonts).

---

## Themed shell (`body.k2-site` via `includes/k2_head.php`)

| Asset | Status | Location / notes |
|-------|--------|------------------|
| **IBM Plex Sans** (400–700) | Self-hosted | `fonts/ibm-plex-sans-latin-*-normal.woff2`, `stylesheets/k2-fonts.css` |
| **IBM Plex Mono** (400, 500) | Self-hosted | `fonts/ibm-plex-mono-latin-*-normal.woff2` |
| **Exo 2** (500–700) | Self-hosted | `fonts/exo-2-latin-*-normal.woff2` |
| **theme.css** | Self-hosted | `stylesheets/theme.css` |
| **player-hero-rank.css** | Self-hosted | `stylesheets/player-hero-rank.css` |
| **k2-tint-schedule.js** | Self-hosted | `js/k2-tint-schedule.js` (inline boot in `theme_boot_head.php`) |
| **k2-carry-scroll.js** | Self-hosted | `js/k2-carry-scroll.js` |
| **favicon** | Self-hosted | `/favicon.ico` |

**Head order (performance):** `k2_fonts_head.php` preloads Exo 600 + Plex Sans 400 → `k2-fonts.css` → `theme.css` → tint boot scripts.

**Regenerate fonts:** `scripts/sync_self_hosted_fonts.ps1` (one-time download from jsDelivr @fontsource mirrors; files are committed in git).

---

## JS vendored in repo (already self-hosted)

Loaded per page as `<script src="js/...">` — not from a CDN at runtime.

| Library | File(s) | Used on |
|---------|---------|---------|
| **Chart.js** 4.4.7 | `js/chart.umd.min.js`, `js/chartjs-adapter-date-fns.bundle.min.js` | Profile, milestones detail, Activity (`activity.php`), charts lab |
| **flatpickr** | `js/flatpickr.min.js`, `stylesheets/flatpickr.min.css` | Status daily picker |
| **k2-table.js** | `js/k2-table.js` | Leaderboards, profile matchup tabs, many tables |
| **activity-charts-v2.js** | `js/activity-charts-v2.js` | Activity page |
| **chart-theme.js**, **chart-date-range.js** | `js/chart-theme.js`, `js/chart-date-range.js` | Chart pages |
| **player-search.js** | `js/player-search.js` | Header search, many pages |
| **realm-switch.js**, **k2-tint-toggle.js** | `js/` | Tint picker (hub + player nav): swatch choice + schedule override (`realm-switch.js`); disclosure open/close (`k2-tint-toggle.js`, closes on navigation) |
| Profile feast charts | `js/player-*-chart.js`, `js/player-feast/*` | `player/profile.php` |
| Status leagues | `js/status-period-competitions.js`, `js/k2-archive-listbox.js` | `status.php` |
| Other site JS | `js/*.js` | Page-specific |

License pointer: `js/flatpickr.LICENSE.txt`. Chart.js vendored build notes in first lines of `js/chart.umd.min.js`.

**Removed Jun 2026 (dead surface pass):** `js/elolist.js`, `js/status-league-toggle.js` — see [`DEAD_SURFACE.md`](DEAD_SURFACE.md).

**Legacy URL redirects (no extra JS):** `server1-charts-lab.php` → `activity.php`; `status-realm-lab.php` → `status.php`.

---

## CSS beyond theme (self-hosted)

| File | Pages |
|------|-------|
| `player-feast*.css`, `player-milestones.css` | Profile / milestones |
| `flatpickr.min.css` | Status |
| Page-specific `<link>` | As included per PHP |

No `@import` of external URLs in site CSS (after Jun 2026 font change).

---

## Images & inline SVG

| Asset | Status |
|-------|--------|
| `images/KO2BoxFront.jpg` | Self-hosted |
| Medal SVG inline in PHP | Generated in HTML (no external fetch) |
| Data-URI chevrons in `theme.css` | Inline (not a network request) |

---

## Intentionally external (not self-hosted)

These are **links or embeds** to other sites — not CDN dependencies for app chrome.

| What | Where | Why external |
|------|-------|----------------|
| **YouTube promo embeds** | `join.php` → `includes/join_page_section.php` iframe; `game.php` interim replay placeholder; tournament/player Videos spotlight — all via `k2_youtube_embed_url()` (`youtube-nocookie.com/embed/…?origin=…`) | Video host; self-hosting = hosting video files yourself |
| **Outbound links** | `includes/join_page_links.php` | Discord, kickoff2.net, shops, YouTube watch URLs, etc. |
| **Player profile links** | `k2_player_link()`, tables | `player/profile.php` on same site |
| **W3C DTD / xmlns URLs** | HTML doctype | Identifier strings; browsers do not fetch them |

To remove YouTube **runtime** dependency: replace iframes with self-hosted posters + links, or locally hosted MP4/WebM files (large asset; product decision).

---

## Former third-party (removed)

| Was | Now |
|-----|-----|
| Google Fonts `@import` in `theme.css` | `fonts/*.woff2` + `k2-fonts.css` + preload in `k2_fonts_head.php` |

---

## Deploy checklist

1. WinSCP/sync **`site/public_html/fonts/`** (9 `.woff2` files) with PHP/CSS/JS.
2. Hard-refresh after deploy (query `?v=` on `k2-fonts.css` from `filemtime`).
3. Confirm DevTools Network: no `fonts.googleapis.com` / `fonts.gstatic.com` on Status or Profile.
