# Kick Off 2 ratings — design direction & cosmetics plan

**Status:** Agreed direction (May 2026). **Phase A hub shell** + **Status Phase B v1.2** shipped in repo. Staging URL: `https://ratings.kickoff2.com` (WinSCP deploy; not auto from `git push`). **Tint vs realm:** see `docs/tint-vs-realm.md` (UI paint = tint picker; realm = universe).

**Audience:** Dagh and Cursor agents. Captures **design decisions from discussion** so we do not re-derive them from chat history.

**Authority:** `PROJECT_BRIEF.md` for product taste. **Dagh’s latest message wins** on scope. This doc governs **visual identity, theme system, and cosmetics-track implementation** — not ladder engine or data correctness.

**Related docs:**

| Doc | Role |
|-----|------|
| `PROJECT_BRIEF.md` | North star: trustworthy, data-dense, gradually friendlier |
| `PROJECT_MEMORY.md` | Logistics, deploy loop, near-term intent |
| `docs/ladder-engine-plan.md` | Dual-realm data/engine (online vs Amiga); website realm routing still TBD |
| `docs/playertable-schema.md` | Player aggregates; includes unused `Profile_*` columns |

---

## 1. What we are trying to achieve

### Positioning (the “why”)

Two sibling surfaces serve different jobs:

| Surface | Role today | Signal we want |
|---------|----------|----------------|
| **kickoff2.com** | Community portal — downloads, history, links | Nostalgia, heritage, “remember this game?” |
| **ratings.kickoff2.com** | Statistics and ladder — results, Elo, charts | **Prestige, aliveness, competitive seriousness** — “this scene is elite and kicking” |

The ratings site should **not** clone kickoff2.com’s pixel-art football pitch aesthetic. That reads as **museum**. The ratings site counters that with a **dark, vibrant, professional data universe** — retro in *spirit* (Amiga-era neon sci-fi, synth energy) but executed as a **premium analytics product**.

**Impact vision (Dagh):** The site should look **fantastic** — super cool, amazing, professional. Quality of presentation is itself proof that the community is **alive**, not dead, ugly, or forgotten. Undersell in naming; let the look do the selling.

### Site naming

- Working title: **Kick Off 2 ratings** (lowercase **ratings** — descriptive, not declarative brand).
- Drop **“KOOL Rating”** from headers and `<title>` over time; “KOOL” remains community vocabulary, not visual identity.
- Tagline (optional, small): e.g. *online and real-world results* — only if it aids orientation.

### Dual realm (online + Amiga)

Soonish, a **second universe** duplicates the ladder for **real Amiga 500 games played in real life** (not online). Implications for design:

- Branding is **realm-neutral** at the shell level (“Kick Off 2 ratings”).
- **One design system**, two **realms** (`data-realm="online|amiga"`) for data; **UI accent** from tint picker (`data-k2-accent`), not from realm.
- Same UI patterns (tables, profiles, charts); APIs already accept `realm=` (online today; Amiga not wired yet — see `docs/ladder-engine-plan.md` §8).

**Amiga-specific identity opportunity:** Many players can have **actual photographs** in the Amiga realm — strong “we exist and matter” signal beyond avatars and stats. Plan layout for photos in player hero from the start.

---

## 2. Aesthetic direction

### Style name

**Neon noir statistics** (also describable as **dark analytics with electric accents**).

- **Attitude:** cyberpunk / neon — alive, cool, contrasts with kickoff2.com museum feel.
- **Execution:** disciplined data dashboard — not cosplay, not RGB gaming keyboard, not pixel-font body text.

### Reference sites

| Reference | Take | Leave |
|-----------|------|-------|
| **kickoff2.com** | Community umbrella link | Pixel buttons, grass texture, brochure layout |
| **sensiblesoccer.de** | Dark theme works for retro football community | Busy portal clutter, pixel headings on body content |
| **Existing Chart.js work on staging** | **Foundation palette** — already proven on dark background | — |

### What “cyberpunk” means here

**Do:**

- Deep charcoal/navy-black backgrounds (not flat `#000`).
- Vibrant accents **sparingly** — links, active tabs, rank highlights, chart series, realm strip.
- Subtle atmosphere: faint grid, soft focus glow, elevated glassy panels.
- Sharp typography and tabular numbers — “control room” energy.
- Purposeful motion later (chart draw-in, realm switch) — not decorative noise.

**Avoid:**

- Pixel/bitmap fonts for body text or table data.
- Glow on everything (tables become unreadable).
- Generic hot-pink + cyan template without structure.
- Animation that fights dense data pages.

Think **Blade Runner control room**, not **Cyberpunk 2077 menu screen**.

### Chrome vs imagery (agreed May 2026)

**Principle:** Chrome carries the brand; data carries the prestige; imagery earns its place page by page.

- **Global chrome:** minimal functional bar (wordmark, search, realm) — no full-width decorative header band on every page.
- **Data views:** tables and charts are the hero; do not push them down with repeating portal banners.
- **Imagery:** only where it helps orientation or heritage (e.g. Status landing), not on dense ladder/profile tables.
- **Legacy (removed from repo May 2026):** old portal mock (`rankings.php`, `Tab.css`, header JPEGs) — not the current ratings identity.

---

## 3. Color system

### Chart palette — six inks (May 2026, signed)

**Canonical colours** (CSS `--k2-chart-*` in `theme.css`, accessors in `js/chart-theme.js`):

| Token | Hex | Hub tint? | Role |
|-------|-----|-----------|------|
| **pitch** (`--k2-chart-pitch`) | `#9ccc65` | pitch pill | Games, profile subject, wins |
| **chrome** (`--k2-chart-chrome`) | `#64b5f6` | chrome pill | Active players, projections, opponent focus |
| **holo** (`--k2-chart-holo`) | `#b388ff` | holo pill | Cumulative established (line) |
| **amber** (`--k2-chart-amber`) | `#ffb74d` | default UI accent | Goals per month |
| **teal** (`--k2-chart-teal`) | `#4db6ac` | chart-only | Reserved for future distribution / analyst ink |
| **magenta** (`--k2-chart-magenta`) | `#ff4081` | chart-only | New established per year, rating distribution |

**Activity (`server1.php`) semantics:**

| Chart | Colour |
|-------|--------|
| Games month / year YTD | pitch |
| Games year projection | chrome |
| Goals month | amber |
| Active month | chrome |
| New established year | magenta |
| Cumulative established | holo (line, light area fill) |
| Rating distribution | magenta |

**Render (Activity bars):** `K2ChartTheme.barStroke()` — border 1px, fill alpha from `--k2-chart-bar-fill-alpha` (0.65). Dense monthly bars use `K2ChartTheme.softGrid()` so grid lines do not visually slice through narrow bars. Lines: `lineStroke()` + `--k2-chart-line-area-alpha` (0.12).

Use `pitch()` / `chrome()` or `profileCompare*` / `opponentFocus*` in chart JS — no legacy colour names.

| Text primary | `#d0d7de` | Body on dark |
| Text muted | `#8b949e` | Helpers, axis labels |
| Grid subtle | `rgba(255, 255, 255, 0.08)` | Chart grids |
| Grid soft | `rgba(255, 255, 255, 0.045)` | Dense monthly Activity bar charts |

Chart block hints use `.k2-chart-block__hint`.

### Global surface tokens (production target)

Conceptual — exact values tuned in theme lab:

```css
--k2-bg-page        /* ~ #0b0f14 or #0d1117 — blue-black, richer than #121212 */
--k2-bg-surface     /* ~ #151b26 — panels, table wrapper */
--k2-bg-elevated    /* ~ #1a1f2e — dropdowns, cards */
--k2-text-primary
--k2-text-muted
--k2-border-subtle  /* rgba white ~6% */
```

### UI accent / tint tokens (production — May 2026)

**Tint** (`--k2-accent` on `<html>`) drives links, nav rings, glows, and chrome. **Not** tied to Online/Amiga realm. See `docs/tint-vs-realm.md`.

| Tint id | Hex | Notes |
|---------|-----|--------|
| **amber** (default) | `#ffb74d` | CSS default when attribute absent |
| pitch | `#9ccc65` | Hub pill (legacy Amiga-era green) |
| chrome | `#64b5f6` | Hub pill |
| holo | `#b388ff` | Hub pill |

```html
<html data-realm="online" data-k2-accent="chrome">  <!-- realm = universe; accent optional -->
```

```css
html { --k2-accent: #ffb74d; /* + muted/glow; hub data-k2-accent overrides */ }
```

**Realm** (`data-realm`) selects ladder/universe for data and copy later; it does **not** set site paint.

**Charts** stay **realm-neutral** (multi-colour `chart-theme.js` palette). **Links / link-star** derive from `--k2-accent` via `color-mix` on `html`.

**Hub tint picker:** Amber · Pitch · Chrome · Holo; hidden by default — **Show tint** on hub bar. Long-lived persistence deferred (`docs/tint-vs-realm.md`).

### Text & link hierarchy (production — May 2026)

Agreed balance after nav/header/link passes: **data and player names lead**; accent is stepped down via `color-mix`, not removed.

| Layer | Token / rule | Role |
|-------|----------------|------|
| Table & body data | `--k2-text-primary`, weight 400 | Hero numbers and plain text |
| **Player names, game IDs, profile highlight text** | `--k2-link-star` (85% accent + primary) | Stars of the show; weight **600** in `.k2-table tbody td a` |
| Name/link hover | `--k2-link-star-hover` (94% accent + primary) | Clear affordance; no underline in tables |
| Prose / footer links | `--k2-link` (72% + secondary), weight 500 | Inline links outside dense grids |
| Prose hover | `--k2-link-hover` (85% + primary) | |
| Nav inactive | `--k2-text-muted` | |
| Nav hover | `--k2-text-secondary` | No white flash |
| Nav active (segment) | `--k2-segment-active-text` (72% + secondary) + `--k2-segment-active-ring` | Outline chrome, not fill |
| Column headers | `--k2-text-muted`, weight 500 | Sort hover → secondary + inset accent bar |
| Stat positive / negative | `.blue` / `.red` → `--k2-table-positive` / `--k2-table-negative` | 78% cyan/magenta mix to primary; chart bases stay full in `chart-theme.js` |
| Structure | `--k2-accent` at 100% | Rings, borders, avatar, decorative glows only |
| Table row hover | `--k2-bg-hover` | Neutral lift only — not `accent-muted` (avoids muddy tint on link-star names) |

All link/star tokens are **derived on `html`** from `--k2-accent` (default amber; optional `data-k2-accent` on hub). Do not duplicate hex link colours in per-page CSS.

**Profile feast CSS** (`player-feast*.css`, `player-hero-rank.css`): typographic highlights use `--k2-link-star` (no text-shadow glow on busiest/duel counts — avatar ring keeps glow).

**Staging tune:** `--k2-link-star` **85%**; table stat colours **78%** (`--k2-stat-positive-base` / `--k2-stat-negative-base`). Nudge stat mix 75–82% on staging if needed.

**Charts:** keep full `#4fc3f7` / `#f06292` (and series palette) in `chart-theme.js` — only table `.blue`/`.red` use the softened tokens.

### Neon intensity (locked for production — May 2026)

**Production (`theme.css`):** **C · Bold** — `--k2-glow-strength: 0.55`, `--k2-accent-glow-blur: 18px`. Used on **player hero avatar ring** only (realm switcher uses segment outline, no glow).

**Theme lab** still exposes A/B/C toggles for comparison. Lab mock does not use a special rank-#1 row style.

| Level | Name | Character |
|-------|------|-----------|
| **A** | Subtle | No accent glow (`strength: 0`) |
| **B** | Moderate | Light halo (`0.25` / `12px`) |
| **C** | Bold ✓ production | Stronger halo on realm + hero (`0.55` / `18px`) |

### Known visual debt (fix during theme rollout)

- ~~`main2.css`~~ **Removed (May 2026);** all tokens in `theme.css` (`--k2-*`). Themed pages load `includes/k2_head.php`.
- ~~`elolist.css` — legacy light-green table stripes~~ **Pruned (medium refactor, May 2026);** theme.css owns `.k2-table` look. `elolist.css` kept as companion to `elolist.js` (sort/cloak hooks).
- ~~~18 PHP files duplicate CSS `<head>` blocks~~ **Replaced with `includes/k2_head.php` (May 2026).**

---

## 4. Typography

**Body:** **IBM Plex Sans** (`--k2-font-body`) — clean sans for tables, lists, chart blocks, status panels.

**Stats / numbers:** **IBM Plex Mono** or `font-variant-numeric: tabular-nums` on sans — precision feel for ladder tables.

**Display / chrome:** **Exo 2** (`--k2-font-display`) — wordmark, player hero name, hero stat values, realm switcher labels, avatar initial. **Not** for routine panel or chart section titles.

**Data-surface headings:** class **`.k2-panel-heading`** — same face as hub tabs (IBM Plex Sans), weight **600**, `--k2-text-muted`, **14px**. Softer than table body (`--k2-text-primary`). Smaller sub-blocks (period/peak leaderboards) may override to 13px. Chart hints: **`.k2-chart-block__hint`** (muted, 12px).

**Never:** pixel/bitmap fonts for body, tables, or chart labels.

**Wordmark pattern:** `Kick Off 2` (normal) + `ratings` (smaller or muted).

---

## 5. Layout & content — scope boundaries

### In scope now (cosmetics track)

- Color theme, typography, atmosphere.
- Shared chrome concept (header, realm switcher, page frame).
- Table/chart/tab visual harmony.
- **Theme lab mock** before broad live changes.

### Explicitly deferred (content track — do not block theme on these)

- Perfect profile page information architecture.
- Fun stats / trophy cabinet implementation.
- Dashboard front door replacing `index.php` → `ranked1.php` redirect.
- Grouping H2H/compare charts into “matchup lab” section.
- YouTube embeds and photo galleries (design **containers** now; wire data later).

### Mild reorganization allowed alongside theme

When styling profiles, leave room for future zones without full reorder yet:

| Zone | Purpose | When |
|------|---------|------|
| **Hero** | Name, rank, rating, peak, avatar/photo, optional featured video | Style in mock; wire on `individual1.php` later |
| **Story** | Bio, fun highlights, recent games | Content track |
| **Lab** | Existing Chart.js analytics, H2H | Keep; style as distinct section |

---

## 6. Player identity & media (future data)

Player pages are the **showcase** for community aliveness — reading about yourself and others in a stats context.

### Existing schema (`playertable` — unused in PHP today)

| Column | Type | Intended use |
|--------|------|--------------|
| `Profile_Bio` | text | Short bio |
| `Profile_AvatarURL` | varchar(1024) | Avatar image URL |
| `Profile_LinkURL` | varchar(1024) | Generic profile link |

See `docs/playertable-schema.md`.

### Photos (especially Amiga realm)

- Use `Profile_AvatarURL` for v1 headshot, or dedicated photo URL field if avatar vs hero photo should differ.
- Hero layout in mock: **64–96px** avatar circle or **larger Amiga photo** slot (~120–160px) — TBD in theme lab.
- Real player photos = high-impact “we exist” signal for offline/real-world realm.

### YouTube / WC highlight videos

**v1 (simple):** add `Profile_YouTubeId` (11-char video ID) or `Profile_VideoURL` on `playertable` — one featured video per player.

**v2 (better):** `player_media` table:

```
player_media(id, player_id, realm, kind, url, title, sort_order, created_at)
kind = 'youtube' | 'link' | 'photo'
```

Supports multiple clips (WC finals, highlights), realm-specific curation, ordering.

**Embed rules (when implemented):**

- Store **video ID**, not full URL.
- Use `youtube-nocookie.com` iframe.
- Lazy-load or click-to-play (“Play highlight”) so every profile does not pull YouTube.
- If same human exists as two rows (online vs Amiga), media may need `realm` column from v1.

**Realm note:** WC videos may belong primarily to **Amiga** identity — plan accordingly before stuffing everything into online `playertable` only.

---

## 7. CSS & tooling strategy

### Production stack (default path)

- **No build step required** — matches WinSCP deploy (`site/public_html/` → server).
- **CSS custom properties** in `stylesheets/theme.css`; shared load via `includes/k2_head.php`.
- **Plain CSS component classes** — e.g. `.k2-site-header`, `.k2-realm-switch`, `.k2-tabs`, `.k2-card`, `.k2-player-hero`, `.k2-table`.
- **`js/chart-theme.js`** — single source for Chart.js colors aligned with CSS tokens.
- **PHP includes** for shared header/chrome (start with 2–3 pages, expand).

### Tailwind — deliberate non-bet (for now)

Tailwind is **not rejected forever**; it is **not the whole-site strategy today**.

| Approach | Use | Build step? |
|----------|-----|-------------|
| CSS variables + component classes | **Production** | No |
| Tailwind CDN | **Theme lab mock only** (fast iteration) | No |
| Tailwind CLI → committed CSS | Optional later if utility workflow sticks | Yes |

**Why not site-wide Tailwind yet:** no `package.json`; many legacy PHP pages; `elolist.css` + `elolist.js` table system; WinSCP sync loop; chart colors use shared `--k2-*` tokens via `chart-theme.js`.

**Workflow (historical):** experiments ran in a static theme lab (May 2026) → winning tokens landed in **`theme.css`** on production pages. Lab files removed; tune on staging via real PHP pages.

---

## 8. Theme lab mock (retired May 2026)

**Was:** `theme-lab.html` + `theme-lab.css` + `theme-lab.js` — static IA/CSS sandbox (port 8765 or Laragon). **Removed from repo** after Phase A–3 promotion to `theme.css`, `hub_nav.php`, ranked/server/individual pages.

**Locked choices** from that pass are in §11 below and in production CSS — not re-opened via a separate mock page.

---

## 9. Production rollout phases (after mock approval)

| Phase | Work | Risk |
|-------|------|------|
| **0** | This doc + theme lab (retired) | None |
| **1** | Site-wide dark theme + shared header | **Done** |
| **2** | `elolist.css` dark table pass | **Done** |
| **3–4** | Hub nav (`hub_nav.php`), wing tabs (`lb_nav.php`), **Status room v1.2**, theme on ranked/server/individual pages | **Done in repo** — deploy via WinSCP |
| **5** | Player hero styling on `individual1.php` (containers only) | Partial — layout simplified; full feast later |
| **6** | Live staging tune; launch decisions (accent pills, realm defaults, `<title>` rename) | **In progress** |
| **Later** | Content track: profile reorder, fun stats, dashboard, media embeds | Separate slices |

**Cosmetics additions (May 2026, staging-oriented):**

- **Leaderboard wing nav:** segment track + outline active cell (promoted from theme-lab); table detached from wing bar (~12px gap); `.k2-chrome-tabs` max-width 1200px.
- **Hub / player nav (production):** **segment** track + outline active (`data-k2-hub-nav="segment"` default); overrides via `?k2_hub_nav=`.
- **Hub tint picker:** Amber · Pitch · Chrome · Holo; **hidden by default**; **Show tint** on hub bar. Launch: keep hidden vs expose (open).
- **FOUC fix:** `includes/theme_boot_head.php` — sync `data-realm` / `data-k2-accent` from storage before first paint (included on hub + ranked cloak + key server pages).
- **UI tint:** default amber; hub pills (see § UI accent / tint tokens, `docs/tint-vs-realm.md`).
- **Status room:** 4-column grid, asymmetric cols (wider games + leaderboard); `.k2-panel-heading` on panel/chart titles; body text `--k2-text-primary` `#d0d7de` (May 2026 tune).

**Change style:** small, reversible slices — same as `PROJECT_BRIEF.md`.

---

## 10. Relationship to other work

| Track | Interaction |
|-------|-------------|
| **Charts (shipped)** | Palette is foundation; consolidate colors into theme |
| **Profile tone / fun stats** | Defer layout; theme leaves hero/story/lab zones |
| **Ladder engine / Amiga realm** | Realm switcher UI precedes data; `realm=` APIs already stubbed |
| **kickoff2.com portal** | Footer/sibling link only; portal may get “community pulse” widget later (Steve/separate repo) |

---

## 11. Open decisions (resolve on staging / in chat)

### Locked (Dagh, May 2026 — approved in theme lab, now in production CSS)

- [x] **Neon intensity:** **C · Bold** — glow on **player hero avatar** only (not feast stat text)
- [x] **Display font:** **Exo 2**
- [x] **UI tint (production):** default **amber** `#ffb74d`; hub **Amber · Pitch · Chrome · Holo** (see § UI accent / tint tokens)

### Still open (minor — not blocking staging)

- [x] **Tint picker** — **Amber · Pitch · Chrome · Holo**, hidden by default + **Show tint**
- [ ] **Tint picker at public launch** — remove, keep hidden, or expose (decide later)
- [x] **Link / text ladder** — `--k2-link-star` (names + profile highlights), `--k2-link` (prose); see § Text & link hierarchy.
- [ ] Exact surface hex fine-tune (`--k2-bg-page`, `--k2-bg-surface`) — current values OK unless staging feels too dark/light
- [ ] When to rename `<title>` from “KOOL Rating” to “Kick Off 2 ratings”
- [ ] Physical realm routing on website (see `docs/ladder-engine-plan.md` §8)

### Pitch green (theme lab — now tint id `pitch`)

| Key | Hex | Character |
|-----|-----|-----------|
| **pitch** ✓ | `#9ccc65` | Hub tint pill — pitch / chart green (not realm paint) |
| lime | `#aed581` | Softer green (not chosen) |
| teal | `#4db6ac` | Cool, analytical |
| cyan | `#4dd0e1` | Digital / cyber |
| blue | `#64b5f6` | Tech dashboard (not chosen) |

---

## 12. Agent notes

- **Do not** deep-reorganize profile content while doing theme work unless Dagh expands scope.
- **Do not** commit secrets or DB credentials.
- **Do not** adopt site-wide Tailwind without explicit Dagh approval and a documented build/deploy step.
- **Do** read this doc before cosmetics/CSS work.
- **Do** add themed page assets via `includes/k2_head.php` (not duplicated `<link>` blocks).
- **Do** preserve dense table functionality where pages still load `elolist.js` (sort, filter, ranked cloak); keep `elolist.css` as the JS companion sheet. Static tables (`game.php`, Games tab day buckets, Activity stats, Records) use `k2-table` + `theme.css` only — see `docs/k2-table-and-games-plan.md` Phase 3.
- **Do** use realm-neutral naming in new chrome.
- After completing a cosmetics slice: one line in `PROJECT_MEMORY.md` Recent log; update open decisions above if resolved.

---

*Last updated: May 2026 — `k2_head.php`, `main2.css` removed, production neon C, `--k2-text-muted` for chart helpers.*
