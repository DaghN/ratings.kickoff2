# K2 embedded video pages — URL and interaction policy

**Status:** **Specced (Jun 2026)** — WC tournament Videos tab behaviour locked below; **not implemented** yet (see Phase A / B). Site uses **normal full page loads** (Turbo removed Jun 2026).

**Purpose:** One expandable policy for pages that combine a **video index** (table or list) with a **shared spotlight player** (single iframe). Catalog/manifest rules stay in [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md).

**Authority:** Dagh product alignment (Jun 2026). Implementation: [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) (add slice **TV-URL** when scheduled).

---

## Document map (expandable)

| Surface | Route (Amiga) | Section | Status |
|---------|---------------|---------|--------|
| **WC tournament Videos tab** | `/amiga/tournament/videos.php?id=` | [§2](#2-wc-tournament-videos-tab) | **Specced** — Phase A / B below |
| Player profile **Videos** wing | TBD | [§3](#3-player-profile-videos-wing-reserved) | Reserved |
| Online / other embed pages | TBD (e.g. `game.php`) | [§4](#4-other-surfaces-reserved) | Reserved |

When adding a surface: copy the **URL param table** and **session vs cold** rules from §2; define surface-specific index defaults and param names only if they must differ.

**Related:** [`url-routes.md`](url-routes.md) · [`k2-turbo-page-init-checklist.md`](k2-turbo-page-init-checklist.md) (historical — no Turbo) · hash scroll [`k2_carry_scroll_restore.php`](../site/public_html/includes/k2_carry_scroll_restore.php) · player markup [`amiga_tournament_videos_wc_render.inc.php`](../site/public_html/includes/amiga_tournament_videos_wc_render.inc.php)

---

## 1. Shared principles (all surfaces)

These apply to every section in the document map unless a surface explicitly overrides.

### 1.1 No Turbo / no SPA framework

- Navigation is **full page load** unless the user stays on the same document and only the **History API** updates the URL (in-page video pick).
- Do **not** reintroduce Hotwired Turbo for this feature.
- Page JS may use `k2OnPageReady` / `k2:page-ready` once per load ([`k2-page-boot.js`](../site/public_html/js/k2-page-boot.js)).

### 1.2 URL is the share contract

| Param | Role | Required |
|-------|------|----------|
| **`v`** | YouTube video id — **what the spotlight player loads** | Yes for any “watch this clip” deep link |
| **`game`** | Amiga **rated game id** — which **Games index row** is active (scores, phase, highlight) | Optional — use when sharing “this game” or when multiple rows share the same `v` |
| **`wing`** | Sub-area of the page (WC tab: `extras` = Atmosphere) | When the clip lives off the default wing |
| **`t`** | Start offset in seconds inside the embed (YouTube `?start=`) | **Phase B** — optional; manifest offsets later |

**Playback language is always `v=`.** `game=` disambiguates the index UI only; it does not replace `v=` for the embed.

**Hash:** append `#k2-tournament-video-player` (or surface-specific player id) on shared links so cold visits land with the player in the viewport ([`k2_carry_scroll_restore.php`](../site/public_html/includes/k2_carry_scroll_restore.php) — pre-paint cloak when hash/carry pending).

### 1.3 Progressive enhancement

- ▶ control should have a **real `href`** with the full deep link (works without JS).
- JS **may** `preventDefault` for smooth in-page swap + scroll; must **update the URL** (`pushState` / `replaceState`) so copy-from-address-bar matches what is playing.
- **Cold load:** server reads URL params and renders correct wing, spotlight iframe, and active row — first paint must match the link.

### 1.4 Ambiguous `v=` without `game=`

If multiple index rows reference the same `youtube_id` and the URL has **`v=` only** (no `game=`):

- Load the embed from **`v=`**.
- **Do not** highlight any row.

Normal share flow from a Games row should copy **`v=` + `game=`**, so this case is rare (manual URL editing only).

### 1.5 Phase B — timestamps (deferred)

- **`t=`** in the site URL maps to YouTube embed `?start=` (and optionally end / chapters later).
- Requires **manifest metadata** (e.g. `start_sec` per `game_id` or per `youtube_id`+leg) for dual-leg single uploads and long streams — editorial/agent catalog work, not automatic from `ko2amiga_db`.
- **Phase A** must not block Phase B: accept `t=` in URL shape and pass through to embed when present, even if rarely set.

---

## 2. WC tournament Videos tab

**Route:** `/amiga/tournament/videos.php?id={tournament_id}`  
**Scope:** World Cup events only — **Games** wing (match index table) + **Atmosphere** wing (`?wing=extras`). Non-WC tournaments use stacked cards ([`amiga_tournament_videos_body.inc.php`](../site/public_html/includes/amiga_tournament_videos_body.inc.php)) — out of scope for §2 until a future section adds them.

### 2.1 Wings

| Wing | URL | Index | Spotlight |
|------|-----|-------|-----------|
| **Games** (default) | `?id=` | One table row per linked `game_id` | Shared iframe below table |
| **Atmosphere** | `?id=&wing=extras` | Title + duration list | Same spotlight pattern |

Atmosphere clips: deep link with **`v=`** + **`wing=extras`** only (no `game=`).

### 2.2 Cold visit (full page load)

| URL shape | Player | Active row | Scroll |
|-----------|--------|------------|--------|
| `?id=` only (index) | **Default main final** for that WC | **Same final row** highlighted — user sees what is loaded | Normal (top of page) |
| `?id=&v=…` (+ optional `game=`, `wing=`) | Embed for **`v=`** (+ `t=` if set) | If **`game=`** valid for tournament and matches `v=` in manifest → that row; else if exactly one row has `v=` → that row; else **no** row (§1.4) | Prefer `#k2-tournament-video-player` on shared links |

Invalid or unknown `v=` / `game=` → fall back to index cold behaviour (final + final row active); do not error page.

### 2.3 In-session click (no reload)

On ▶ click:

1. Swap spotlight iframe `src` (include `?start=` when `t=` present).
2. Set active row (Games: row for clicked `game_id`; Atmosphere: matching title row).
3. Smooth scroll to `#k2-tournament-video-player`.
4. Update URL:
   - From **index** (URL has no `v=`): **`pushState`** → adds history entry (enables Back to index).
   - Already watching (`v=` present), user picks **another** clip: **`replaceState`** → Back returns to index in **one** step, not a chain of every clip tried this session.

**Games row click** — URL shape:

```text
/amiga/tournament/videos.php?id={tid}&v={youtube_id}&game={game_id}#k2-tournament-video-player
```

**Atmosphere row click:**

```text
/amiga/tournament/videos.php?id={tid}&wing=extras&v={youtube_id}#k2-tournament-video-player
```

### 2.4 Back button → index (in-session)

When the user presses **Back** and the URL returns to **index shape** (`?id=` with no `v=` / `game=` — same wing param ok):

- **Do not** reset the player to the default final.
- **Do not** clear the active row highlight.
- **Do** scroll so the **index table** is back in view for choosing another clip.

Index URL shape and “cold index” therefore differ in **behaviour** though the path may look the same: cold load runs server default-final logic; **Back** is handled client-side (`popstate`) and preserves last pick in player + row.

Optional in-page **“Back to list”** control: same as one Back step (for visitors who landed on a shared deep link with no prior history entry).

### 2.5 Share / copy link

- From a **Games** row: prefer **`v=` + `game=`** (+ hash) — “watch this game.”
- **Atmosphere:** **`v=`** + **`wing=extras`** (+ hash).
- **`t=`** when Phase B exists.

Copy-from-address-bar after in-session navigation must reflect current clip (History API updates on click).

### 2.6 Implementation phases

| Phase | Deliverable | Exit criteria |
|-------|-------------|---------------|
| **A — Deep links** | PHP reads `v=` / `game=` / `wing=`; ▶ as link + JS enhance; History API; hash scroll; Back behaviour §2.4 | Shared Games semi link opens correct row + embed; cold index shows final + active final row |
| **B — Timestamps** | Manifest offsets; `t=`; embed `start=` | Dual-leg / stream “start at game” works for curated rows |

**Out of scope for §2:** changing catalog dedupe rules; new DB tables; online realm.

---

## 3. Player profile Videos wing (reserved)

**Status:** Not specced.

**Placeholder intent:** Reverse index on manifest `player_a_id` / `player_b_id`; likely same **`v=`** playback param and optional **`game=`** when index is game-centric. Define index default and Back behaviour when this wing is designed.

---

## 4. Other surfaces (reserved)

**Status:** Not specced.

Examples: online `game.php` watch links, realm-wide video index, non-WC tournament Videos cards. Each gets a row in the [document map](#document-map-expandable) before implementation.

---

## 5. References

| Item | Location |
|------|----------|
| WC Videos catalog policy | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) |
| Implementation slices | [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) |
| PHP lib + game index | [`amiga_tournament_videos_lib.php`](../site/public_html/includes/amiga_tournament_videos_lib.php) |
| WC render + spotlight | [`amiga_tournament_videos_wc_render.inc.php`](../site/public_html/includes/amiga_tournament_videos_wc_render.inc.php) |
| Click handler (today — no URL yet) | [`amiga-tournament-videos.js`](../site/public_html/js/amiga-tournament-videos.js) |
| URL builder (today — `id` + `wing` only) | `amiga_tournament_videos_url()` in [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) |