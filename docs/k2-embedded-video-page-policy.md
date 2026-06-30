# K2 embedded video pages — URL and interaction policy

**Status:** **Phase A live (Jun 2026)** — WC tournament Videos tab deep links shipped. **Phase B** (`t=` manifest offsets) deferred. Site uses **normal full page loads** (Turbo removed Jun 2026).

**Purpose:** One expandable policy for pages that combine a **video index** (table or list) with a **shared spotlight player** (single iframe). Catalog/manifest rules stay in [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md).

**Authority:** Dagh product alignment (Jun 2026). Implementation: [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) (add slice **TV-URL** when scheduled).

---

## Document map (expandable)

| Surface | Route (Amiga) | Section | Status |
|---------|---------------|---------|--------|
| **WC tournament Videos tab** | `/amiga/tournament/videos/games.php?id=` · `/amiga/tournament/videos/atmosphere.php?id=` | [§2](#2-wc-tournament-videos-tab) | **Phase A shipped** · Phase B deferred |
| Player profile **Videos** wing | TBD | [§3](#3-player-profile-videos-wing-reserved) | Reserved |
| Online / other embed pages | TBD (e.g. `game.php`) | [§4](#4-other-surfaces-reserved) | **Amiga `game.php` shipped** (Jun 2026) |

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
| **`wing`** | *(retired Jun 2026)* — use folder path (`videos/games.php` vs `videos/atmosphere.php`) | — |
| **`t`** | Start offset in seconds inside the embed (YouTube `?start=`) | **Phase B** — optional; manifest offsets later |

**Playback language is always `v=`.** `game=` disambiguates the index UI only; it does not replace `v=` for the embed.

**Cold-load scroll (no `#hash` needed).** Share URLs stay **hashless** (`?id=&v=…&game=…`). On any **full load** with `v=` present, the page (`amiga_tournament_page.php`) sets `$k2ScrollTargetId = 'k2-tournament-video-player'` **before** `k2_head.php`; [`k2_carry_scroll_restore.php`](../site/public_html/includes/k2_carry_scroll_restore.php) reads it as a **server-declared pre-paint scroll target** (lowest priority after a real URL hash / pending-hash) and cloaks → scrolls → reasserts exactly like a hash landing. So copy-from-address-bar, reload, and shared links all land on the player **with no flash and no `#` clutter**. A real `#k2-tournament-video-player` hash still works (higher priority) for hand-edited links. `$k2ScrollTargetId` is a **generic** carry-scroll hook — any page can set it.

### 1.3 Progressive enhancement

- ▶ control should have a **real `href`** with the full deep link (works without JS).
- JS **may** `preventDefault` for smooth in-page swap + scroll; must **update the URL** (`pushState` / `replaceState`) so copy-from-address-bar matches what is playing.
- **Cold load:** server reads URL path + params and renders correct mode, spotlight iframe, and active row — first paint must match the link.

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

**Route:** `/amiga/tournament/videos/games.php?id={tournament_id}` (Games) · `/amiga/tournament/videos/atmosphere.php?id={tournament_id}` (Atmosphere)  
**Scope:** World Cup events only — **Games** mode (match index table) + **Atmosphere** mode (folder paths). Non-WC tournaments use stacked cards ([`amiga_tournament_videos_body.inc.php`](../site/public_html/includes/amiga_tournament_videos_body.inc.php)) — out of scope for §2 until a future section adds them.

### 2.1 Modes

| Mode | URL | Index | Spotlight |
|------|-----|-------|-----------|
| **Games** (default) | `/amiga/tournament/videos/games.php?id=` | One table row per linked `game_id` | Shared iframe below table |
| **Atmosphere** | `/amiga/tournament/videos/atmosphere.php?id=` | Title + duration list | Same spotlight pattern |

Atmosphere clips: deep link on the atmosphere path with **`v=`** only (no `game=`).

### 2.2 Cold visit (full page load)

| URL shape | Player | Active row | Scroll |
|-----------|--------|------------|--------|
| `?id=` only (index) | **Hidden** — no embed until user picks a clip or opens a deep link | **None** | Normal (top of page; table in view) |
| `?id=&v=…` (+ optional `game=`) on the correct mode path | Embed for **`v=`** (+ `t=` if set) | If **`game=`** valid for tournament and matches `v=` in manifest → that row; else if exactly one row has `v=` → that row; else **no** row (§1.4) | **Scrolls to player** via the server-declared carry-scroll target (§1.2) — hashless URLs land on the player, no flash |

Invalid or unknown `v=` / `game=` → fall back to **index** cold behaviour (no player, no row highlight); do not error page.

**Cold-load Back seeds the index.** A shared deep link has no index entry beneath it, so on cold load `syncColdLoad` (in [`amiga-tournament-videos.js`](../site/public_html/js/amiga-tournament-videos.js)) seeds one: `replaceState(indexUrl)` then `pushState(clipUrl)`. The stack becomes `[…, index, clip]`, so **Back (and the "↑ All videos" link) returns to the list** instead of leaving the site — even when the visitor scrolled up and picked a different clip first (switching clips `replaceState`s, keeping the cap at `[index, clip]`).

**Known deferred:** switching **Games ↔ Atmosphere** wing tabs after an in-session watch may land scroll at the player anchor instead of the table top (carry-scroll / hash interaction) — not in scope until wing nav + history are revisited.

### 2.3 In-session click (no reload)

On ▶ click:

1. Show spotlight + **mount the embed by replacing the iframe node** (never reassign `src` — see ⚠️ below). Include `?start=` when `t=` present, and **`autoplay=1`** (the click is a user gesture, so unmuted autoplay is allowed). `autoplay` is an **embed-only** param — it never appears in the site URL.
2. Set active row (Games: row for clicked `game_id`; Atmosphere: matching title row).
3. Smooth scroll to `#k2-tournament-video-player`.
4. Update URL — **stack capped at `[index, clip]`**: the **first** pick from the index `pushState`s one entry; switching clips **while already watching** (`?v=` present) `replaceState`s. So **Back always returns to the index**, never to an earlier clip. In-session history URLs are **hashless** (clean, directly shareable — cold-load scroll comes from the server target in §1.2, not a hash); clears carry-scroll pending hash when returning to index.

**Spotlight caption (game videos).** Above the player, game videos show a **headerless one-row `k2-table--tournament-games` table** (not a plain text label) that mirrors the index Games row: linked game id · [Phase] · 🇦 Player A *(pre-game Elo)* · A · B · Player B *(pre-game Elo)* 🇧 — winner's goal in blue, per-side flags only when the game carries country data. Each player's **pre-game** Elo is in parentheses as a link to the **top of the rating leaderboard table** (`/amiga/leaderboards/rating.php#k2-lb-table`, via `amiga_url_with_context` + `k2_lb_table_anchor_hash`); those Elo links use K2 `data-k2-help` tooltips ([`k2-tooltip-policy.md`](k2-tooltip-policy.md)). Built by `amiga_tournament_videos_wc_game_caption_html()` (single source for cold load + in-session). Cold load renders it server-side in `wc_body`; in-session, the play button carries the markup in **`data-spotlight-html`** and JS sets the label container's `innerHTML` (falling back to text `data-spotlight-label`). **Atmosphere/extras** videos (no `game_id`) keep the simple **title · duration** text caption. **Iframe `title`:** kept for the embed; player-internal YouTube tooltips are cross-origin and not K2-styleable ([`k2-tooltip-policy.md`](k2-tooltip-policy.md) § Cross-origin embeds). Spotlight vertical rhythm (CSS vars on `.k2-tournament-videos__spotlight`): **0.75rem** scroll-margin-top (caption ↔ viewport top), **0.15rem** caption ↔ video, **0.5rem** padding-bottom (video ↔ viewport bottom); player width `min(100%, vh-cap×16/9)`, **left-aligned** with the index table (no centering / viewport bleed).

**Player fit (viewport-height cap).** The 16:9 player uses the shared `.k2-game-page__video` aspect ratio. On this page the wrap is sized to `width: min(100%, calc((100svh − spotlight-chrome) × 16/9))` where **spotlight-chrome** = scroll-margin-top (0.75rem) + caption row (~2.5rem) + caption↔video gap (0.15rem) + bottom padding (0.5rem). **Left-aligned** with the index table (`margin-inline: 0`; no `100vw` centering). When the content column is narrower than the vh cap, width follows the column; when the vh cap binds, the player shrinks to fit height. Does **not** reserve space for the fixed jukebox FAB. Scoped in [`amiga-tournament-videos.css`](../site/public_html/stylesheets/amiga-tournament-videos.css); shared `game.php` rule untouched.

The URL drives rendering: `popstate` calls one `renderFromUrl(root)` — `?v=…` present → mount that clip; absent → hide player, show index. Clicks render the picked clip directly (so they can pass `autoplay`). There is **no** `history.state` tag or `historyBusy`/`lastCommittedWatchUrl` flag machine (those caused the Jun 2026 Back regressions); the only stored JS state is `lastWatchedState` (for the index highlight, below).

> ⚠️ **YouTube iframe history trap (root cause of the Back bug).** A cross-origin YouTube iframe pushes an entry onto the **shared session history** on *every* `src` navigation. Reassigning `iframe.src` therefore silently grows `history.length`, and the browser Back button steps **inside the iframe first** — the video clears but the page URL does not change and **no `popstate` fires**, so the player shell lingers and a second Back is needed. **Fix:** swap clips by creating a fresh `<iframe>` element (its first load *replaces* its own blank entry → adds nothing) and **remove the iframe node** when returning to the index. Verified: node replacement keeps `history.length` flat; `src` reassignment increments it. Implemented in [`amiga-tournament-videos.js`](../site/public_html/js/amiga-tournament-videos.js) (`mountEmbed` / `unmountEmbed`).

**Games row click** — URL shape (hashless):

```text
/amiga/tournament/videos/games.php?id={tid}&v={youtube_id}&game={game_id}
```

**Atmosphere row click:**

```text
/amiga/tournament/videos/atmosphere.php?id={tid}&v={youtube_id}
```

### 2.4 Back button (in-session)

Handled via **`popstate`** + URL shape:

| URL after Back | Player | Active row | Scroll |
|----------------|--------|------------|--------|
| Index (`?id=` with no `v=` / `game=`) | **Hide** spotlight, **remove iframe node** | **Keep `last-watched` row highlighted** (so the visitor can find the next leg) | Scroll that row into view (`block: center`); table top if none |
| Watch (`?v=…`) | Load that clip in spotlight | Matching row when unambiguous | Scroll to player |

**Behaviour (Jun 2026):** the stack is capped at `[index, clip]`, so **Back always returns to the index** — single Back, every time, even after switching clips. There is **no cycling** through earlier picks. Because clips are mounted by node replacement, every Back moves the **parent** page exactly one step (no iframe-internal Back steps).

**Last-watched highlight:** returning to the index keeps the row of the clip you just watched marked (`tr.is-active` + active ▶). `lastWatchedState = {v, game}` is set on every play / cold deep-link; `renderIndex` re-applies it after hiding the player. This makes locating the next clip (e.g. a semifinal’s second leg) fast.

**“↑ All videos” control (shipped Jun 2026).** A link anchored to the **player's top-right corner** (absolute, inside the `position:relative` `.k2-game-page__video-wrap`, `top:0; left:100%`), sitting in the right-hand gutter beside the video, and only shows while a clip is playing. (Nice-to-have placement — not given a small-screen fallback by request.) It carries a real `href` to the index URL (no-JS fallback). JS intercepts it (`onAllVideos`) to give a clean list context — **distinct from the browser Back button**: `pushState` to the index URL, hide the player, **clear the highlight**, and **smooth-scroll the tournament hero to the top of the viewport** (`.k2-amiga-tournament-hero` `scrollIntoView({block:'start'})`; global nav scrolls above it). Browser **Back** keeps its own behaviour (popstate → `renderIndex`: keeps the last-watched row highlighted and centred so you can pick the next leg).

### 2.5 Share / copy link

- URLs are **hashless** and directly shareable. From a **Games** row: **`v=` + `game=`** — “watch this game.”
- **Atmosphere:** atmosphere path + **`v=`**.
- **`t=`** when Phase B exists.
- A future **“share”** button can simply copy `location.href` (or build a row’s deep link); the recipient lands on the player via the server-declared cold-load scroll (§1.2) — no hash required.

Copy-from-address-bar after in-session navigation reflects the current clip (History API updates on click).

### 2.6 Implementation phases

| Phase | Deliverable | Exit criteria |
|-------|-------------|---------------|
| **A — Deep links** | PHP reads path mode + `v=` / `game=`; ▶ as link + JS enhance; History API; hash scroll; Back behaviour §2.4 | Shared Games semi link opens correct row + embed; cold index shows table only (no default clip) |
| **B — Timestamps** | Manifest offsets; `t=`; embed `start=` | Dual-leg / stream “start at game” works for curated rows |

**Out of scope for §2:** changing catalog dedupe rules; new DB tables; online realm.

---

## 3. Player profile Videos wing (reserved)

**Status:** Not specced.

**Placeholder intent:** Reverse index on manifest `player_a_id` / `player_b_id`; likely same **`v=`** playback param and optional **`game=`** when index is game-centric. Define index default and Back behaviour when this wing is designed.

---

## 4. Other surfaces (reserved)

**Status:** Amiga **`/amiga/game.php`** shipped Jun 2026; online realm still TBD.

### 4.1 Amiga single game page (shipped Jun 2026)

**Route:** `/amiga/game.php?id={game_id}` · optional `?v={youtube_id}` when multiple manifest clips link the same game.

| Behaviour | Rule |
|-----------|------|
| **When shown** | Only when `amiga_videos_for_game_id()` finds ≥1 manifest match row for the game id |
| **Layout** | Game table unchanged above; spotlight player + scoreboard caption below (same caption stack as tournament/player Videos) |
| **Single clip** | Player visible on load; no picker menu |
| **Multiple clips** | Stacked text links “Video 1”, “Video 2”, … (manifest sort); active = `k2-link-star`, others muted secondary; JS swaps iframe by node replacement + `replaceState` for `?v=` |
| **Back / index** | No “↑ All videos” control (no video index on this page) |

**Implementation:** `amiga_videos_for_game_id()` · `amiga_game_videos_render.inc.php` · `js/amiga-game-video.js` · reuses `amiga-tournament-videos.css` spotlight sizing.

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
| URL builder | `amiga_tournament_videos_url()` in [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) — `$mode` = `games` \| `atmosphere` |