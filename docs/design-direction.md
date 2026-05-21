# Kick Off 2 ratings — design direction & cosmetics plan

**Status:** Agreed direction (May 2026). **Theme lab** + **Phase A hub shell** shipped in repo. Staging URL: `https://ratings.kickoff2.com` (WinSCP deploy; not auto from `git push`). Open before launch: **TEST realm accent swap**, **accent preview pills** on hub row.

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
- **One design system**, two **realm accent** identities (switcher on `<html data-realm="online|amiga">` or equivalent).
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

### Chart palette (already in production JS — keep as design-system seed)

These Material-inspired accents on dark UI were chosen during the chart wave and **work well**. Promote to shared tokens; do not reinvent.

| Token role | Hex | Example use |
|------------|-----|-------------|
| Chart green | `#9ccc65` | Games, wins, primary rating line |
| Chart blue | `#64b5f6` | Players, comparisons, projections |
| Chart amber | `#ffb74d` | Peaks, milestones |
| Chart coral | `#ff8a65` | Goals |
| Chart teal | `#4db6ac` | Distributions |
| Chart purple | `#ba68c8` | Cumulative growth |
| Text primary | `#e3e3e3` | Body on dark |
| Text muted | `#b0b0b0` | Helper copy, axis labels |
| Grid subtle | `rgba(255, 255, 255, 0.08)` | Chart grids |

Chart files currently hardcode these — later consolidate in `js/chart-theme.js` (read CSS variables or single constant object).

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

### Realm accent tokens

| Realm | Accent (first pass) | Character | Notes |
|-------|---------------------|-----------|-------|
| **Online** | `#64b5f6` (electric blue) | Digital, competitive, server | Aligns with existing chart blue |
| **Amiga** | `#ffb74d` (amber) or `#ff8a65` (coral) | Warm, human, real-world | **TBD in theme lab** — also consider magenta `#f06292` |

Mechanism:

```html
<html data-realm="online">  <!-- or amiga -->
```

```css
[data-realm="online"]  { --k2-realm-accent: #64b5f6; /* + muted/glow variants */ }
[data-realm="amiga"]   { --k2-realm-accent: #ffb74d; /* TBD */ }
```

Shared chart multi-color series stay **realm-neutral**; realm accent used for chrome (active tab underline, hero strip, switcher, rank badge).

### Neon intensity (TBD — compare in theme lab)

Same components, three token presets:

| Level | Name | Character |
|-------|------|-----------|
| **A** | Subtle | Dark surfaces; accents only on interactive + charts; minimal glow |
| **B** | Moderate | Thin neon borders on active tabs; soft glow on rank #1; realm underline |
| **C** | Bold | Visible grid texture; stronger glow; realm-colored hero strip |

**Dagh has no fixed preference yet** — test A/B/C on mock and choose by feel.

### Known visual debt (fix during theme rollout)

- `main2.css` — dark `:root` variables and tab chrome.
- `elolist.css` — **legacy light-green table stripes** clash with dark page chrome. **High-impact fix** when theme lands.
- Legacy header/logo CSS in `main2.css` unused by current pages.
- ~18 PHP files duplicate nav HTML — only `includes/player_search_bar.php` and `ranked_table_cloak_head.php` exist today.

---

## 4. Typography

**Body:** clean sans — **IBM Plex Sans**, **Inter**, or **DM Sans** (free, professional).

**Stats / numbers:** **IBM Plex Mono** or `font-variant-numeric: tabular-nums` on sans — precision feel for ladder tables.

**Wordmark / display (header only):** geometric optional — **Rajdhani**, **Orbitron**, or **Exo** — one line, **not** pixel font.

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
- **CSS custom properties** in `stylesheets/theme.css` (new) or expanded `main2.css`.
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

**Why not site-wide Tailwind yet:** no `package.json`; 18 legacy PHP pages; `elolist.css` table system; WinSCP sync loop; chart colors still need shared tokens anyway.

**Workflow:** prototype in theme lab with Tailwind CDN if helpful → **extract winning tokens** to plain CSS → mock stays throwaway or Laragon-only until approved.

---

## 8. Theme lab mock (next implementation step)

**Goal:** Compare look and feel before touching live pages.

**Location:** `site/public_html/theme-lab.html` + `stylesheets/theme-lab.css` + `js/theme-lab.js` — **local/Laragon first** (`/theme-lab.html`); deploy to staging when Dagh wants feedback from a real URL.

**Sections on one page:**

1. Site header — wordmark, realm switcher (Online active / Amiga “coming soon”), link to kickoff2.com
2. Pulse strip — sample stats (games this month, active players, last updated)
3. One chart block — Chart.js with theme tokens applied
4. Table snippet — ~5 ranked rows, dark table styling
5. **Player hero card** — name, rank, rating, avatar/photo placeholder, video placeholder, realm badge
6. Tab row — Server Stats / Player Ranks + sub-tabs

**Controls:** toggle neon intensity A / B / C; toggle realm online / amiga accent.

**First-pass options:** Dagh has no fixed preference — mock should show **variations** (typography, Amiga accent candidates, photo vs avatar layout).

---

## 9. Production rollout phases (after mock approval)

| Phase | Work | Risk |
|-------|------|------|
| **0** | This doc + theme lab | None |
| **1** | Site-wide dark theme + shared header | **Done** |
| **2** | `elolist.css` dark table pass | **Done** |
| **3–4** | Hub nav (`hub_nav.php`), wing tabs (`lb_nav.php`), Status bridge, theme on ranked/server/individual pages | **Done in repo** — deploy via WinSCP |
| **5** | Player hero styling on `individual1.php` (containers only) | Partial — layout simplified; full feast later |
| **6** | Live staging tune; launch decisions (accent pills, realm defaults, `<title>` rename) | **In progress** |
| **Later** | Content track: profile reorder, fun stats, dashboard, media embeds | Separate slices |

**Cosmetics additions (May 2026, staging-oriented):**

- **Leaderboard wing nav:** segment track + outline active cell (promoted from theme-lab); table detached from wing bar (~12px gap); `.k2-chrome-tabs` max-width 1200px.
- **Hub accent preview pills:** seven neon-named tints (`data-k2-accent` on `<html>`); `js/realm-switch.js`; re-click active realm clears tune. **Staging lab only** — collapse or remove for public launch unless Dagh keeps a subtle “cockpit tint” control.
- **FOUC fix:** `includes/theme_boot_head.php` — sync `data-realm` / `data-k2-accent` from storage before first paint (included on hub + ranked cloak + key server pages).
- **TEST swap (temporary):** `theme.css` comment — online realm uses amber, amiga uses green (inverse of locked lab choice); revert or confirm before launch.

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

## 11. Open decisions (resolve in theme lab or later chat)

### Locked (Dagh, May 2026 — theme lab approved)

- [x] **Neon intensity:** **A · Subtle**
- [x] **Display font:** **Exo 2**
- [x] **Amiga realm accent:** **Amber** (`#ffb74d`)
- [x] **Online realm accent:** **Green** (`#9ccc65`) — warmer than blue; cohesive with chart palette

### Still open (minor — decide during production pass, not more mock rounds)

- [ ] **Accent preview pills** on hub row — keep for launch, collapse to 3–4, or remove (currently staging lab)
- [ ] **TEST realm accent swap** in `theme.css` (online amber / amiga green) — revert to locked lab defaults or adopt
- [ ] **Link colour** when online accent is also green — keep `#9ccc65` links or shift links to `#aed581` / teal for hover distinction?
- [ ] Exact surface hex fine-tune (`--k2-bg-page`, `--k2-bg-surface`) — current values OK unless staging feels too dark/light
- [ ] When to rename `<title>` from “KOOL Rating” to “Kick Off 2 ratings”
- [ ] Physical realm routing on website (see `docs/ladder-engine-plan.md` §8)

### Online accent options (theme lab — green chosen)

| Key | Hex | Character |
|-----|-----|-----------|
| **green** ✓ | `#9ccc65` | **Chosen** — pitch / chart green |
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
- **Do** preserve dense table functionality (`elolist.js` behavior untouched during styling).
- **Do** use realm-neutral naming in new chrome.
- After completing a cosmetics slice: one line in `PROJECT_MEMORY.md` Recent log; update open decisions above if resolved.

---

*Last updated: May 2026 — Phase A hub shell + wing nav shipped; accent pills + TEST swap on staging.*
