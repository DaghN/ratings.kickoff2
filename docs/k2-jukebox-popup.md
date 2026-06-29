# Jukebox popup window (gapless audio without Turbo)

**Status:** Live (Jun 2026). Replaces the Turbo Drive approach to gapless music.

## Why

Gapless audio across navigation needs either (a) one living document (SPA / Turbo)
or (b) the audio living in a separate browsing context. Turbo gave us (a) but at the
cost of a recurring bug class (body scripts re-executing on every in-page nav stacked
duplicate listeners; CSS-cloak/snapshot races hid the time-travel LED stamp; the tint
picker went "dead"; carry-scroll flashed). We removed Turbo and took option (b): the
jukebox is now a **separate popup window** that the main tab's navigation can't tear down.

## Pieces

| File | Role |
|------|------|
| `includes/k2_jukebox.php` | Floating **FAB launcher** button only (no panel/audio). Included in `site_header.php`. Carries `data-k2-jukebox-launch`. |
| `js/k2-jukebox-launcher.js` | Loaded on every page (head). Binds the FAB → opens the player window **centred** on screen, then **toggles** it forward/behind on subsequent clicks. Mirrors now-playing onto the FAB (`is-playing`) via `BroadcastChannel`. |
| `jukebox.php` | Standalone **player window** page. Reuses the jukebox cockpit markup + `k2-jukebox.css`, themed via `theme_boot_head.php`, panel fills the window. Two-row transport (prev/play/next centred on row 1; shuffle + volume on row 2). |
| `js/k2-jukebox-player.js` | Player logic inside the popup. Sets `window.__k2JukeboxReady`, persists track/time/volume/shuffle to `localStorage`, broadcasts `state` + `focus`/`blur`, replies to `ping` with state (does **not** steal focus). |

## Open / focus contract (no popup-block, no restart)

The launcher opens **synchronously inside the click handler** (so the popup blocker
allows it), and computes a **centred** `left`/`top` from `screen.availWidth/Height`
(+`availLeft/Top` so it lands on the right monitor):

```js
var w = window.open('', 'k2jukebox', 'popup=yes,width=360,height=500,left=…,top=…');
// existing window? window.open('', name) returns it WITHOUT navigating.
// new window?      it returns a fresh about:blank we then point at /jukebox.php.
if (!w.__k2JukeboxReady) { w.location.replace('/jukebox.php'); }  // load the player
w.focus();
```

`__k2JukeboxReady` is readable cross-window because both are same-origin. This avoids
re-navigating (and restarting) an already-open player when the FAB is clicked again.

**Playlist updates:** the player re-fetches `/audio/amiga/playlist.json` when the popup
gains focus or when the launcher sends `ping` (each main-tab navigation). Fetch uses
`cache: no-store` and a `?v=` query from `playlist.json` filemtime (set in `jukebox.php`
as `window.__k2JukeboxPlaylistVer`) so edits show up without a full window reload. If the
current track was removed, playback moves to the saved index (or the first track) and
stale `localStorage` track ids are cleared.

### Raise / send-behind toggle

The launcher keeps a live window handle + a `jukeboxFocused` flag (fed by the player's
`focus`/`blur` messages). Clicking the FAB **raises** the window if it's behind, or
**sends it behind** (`win.blur()` + `window.focus()`) if it's in front — there is no
standard "lower window" API, so this is the portable approximation (reliable in
Chromium; may vary elsewhere).

**Race note:** pressing the FAB focuses the main window, which blurs the popup; that
blur message would flip `jukeboxFocused` to false before the `click` fires. The launcher
therefore snapshots the front/behind state on **`pointerdown`** (synchronous, before the
async blur arrives) and uses that snapshot for the click decision. The first click after
a main-tab navigation re-acquires the handle and always raises; toggling resumes after.

## Cross-window state

`BroadcastChannel('k2-jukebox')` messages:

- player → `{ type:'state', playing, title, game }` on play/pause/track change.
- player → `{ type:'focus' | 'blur' }` on window focus/blur (drives the toggle).
- player → `{ type:'closed' }` on `pagehide`.
- launcher → `{ type:'ping' }` on each page load; player replies with `state` + current
  focus (it does **not** raise itself — the FAB pings on every navigation).

The FAB reflects `state.playing` via the `is-playing` class (reuses the existing LED
animation in `k2-jukebox.css`). Hover help uses the shared **`k2-table-tooltip`**
(`data-k2-help` on the FAB + `k2_table_js_enqueue()` from `k2_jukebox.php`) — not native
`title` — including dynamic **Playing: …** copy when a track is active.

## Tint in the popup

The popup includes `theme_boot_head.php`, so it picks up the current tint on open. It
also stays in sync while open: that boot adds a cross-window `storage` listener (re-applies
the accent when another tab changes the tint pick) **and** a self-rescheduling six-hour
boundary tick (so the scheduled rotation follows even with no tint picker in the popup;
self-cancels on pages where `realm-switch.js` already owns the schedule).

## Caveats (by design)

- **Browser frame can't be fully removed** — `popup=yes` minimises chrome but the OS/
  browser still draws a small title bar. The *inside* is fully skinned.
- **Mobile** opens a new tab rather than a floating window (mobile has no popups). Audio
  still continues in that tab; the cockpit feel is desktop-only.
- First open is a **user gesture**, so autoplay generally works; if a browser blocks it,
  the player shows paused and the user presses play once.

## No Turbo

There is no SPA layer. Page JS boots via `js/k2-page-boot.js`, a tiny shim that keeps the
old `k2OnPageReady` / `k2PageReady` / `k2:page-ready` API working on plain full-page loads.
See `docs/k2-turbo-page-init-checklist.md` (now historical).