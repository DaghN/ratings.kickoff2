# Amiga tournament Videos — folder modes implementation plan

**Status:** **Shipped (Jun 2026)**  
**Slice id:** **TV-FOLDER** (follows TV-URL Phase A)  
**Authority:** [`url-routes.md`](url-routes.md) § Sub-hub navigation · [`navigation-model.md`](navigation-model.md) NM4/NM6

**Problem:** Games / Atmosphere sub-tabs on tournament Videos use `?wing=extras` — a query-param mode switch. That conflicts with site policy (folder path = where am I; query = filters). It also collided with time-travel legacy `wing=` (band-aid fix in `amiga_snapshot_url.php`).

**Target:**

| Mode | Canonical path |
|------|----------------|
| **Games** (default) | `/amiga/tournament/videos/games.php?id={tid}` |
| **Atmosphere** | `/amiga/tournament/videos/atmosphere.php?id={tid}` |

Deep links: `?v=`, optional `?game=` (games only), optional `?t=` — unchanged semantics, path selects mode.

**Legacy (302, query preserved except `wing`):**

- `/amiga/tournament/videos.php?…` → `videos/games.php` (or `videos/atmosphere.php` when `wing=extras`)
- `wing=extras` and `wing=atmosphere` both map to atmosphere path

---

## Slice tasks

### TV-FOLDER-1 — Routes and path helpers

- [ ] **`includes/amiga_tournament_lib.php`**
  - Constants `AMIGA_TOURNAMENT_VIDEOS_PATH_GAMES`, `AMIGA_TOURNAMENT_VIDEOS_PATH_ATMOSPHERE`
  - `amiga_tournament_videos_path_for_mode(string $mode): string`
  - `amiga_tournament_videos_mode_from_request(?string $path = null): string`
  - `amiga_tournament_videos_resolve_mode(...)` — honour available rows; atmosphere-only events skip games
  - `amiga_tournament_videos_apply_mode_redirect(...)` — 302 when requested mode empty
  - `amiga_tournament_videos_legacy_redirect()` — old `videos.php` stub
  - Update `amiga_tournament_path_for_view('videos')` → games path
  - Update `amiga_tournament_view_from_request()` — map both new paths → `videos`
  - **`amiga_tournament_videos_url()`** — `$mode` (`games`|`atmosphere`); drop `wing` query param; path from helper

- [ ] **`includes/k2_amiga_routes.php`**
  - `amiga-tournament-videos` → `amiga/tournament/videos/games.php`
  - `amiga-tournament-videos-games` → same
  - `amiga-tournament-videos-atmosphere` → `amiga/tournament/videos/atmosphere.php`

### TV-FOLDER-2 — Entry files

- [ ] **`amiga/tournament/videos/games.php`** — `$k2AmigaTournamentView = 'videos'`, `$k2AmigaTournamentVideosMode = 'games'`; include page shell
- [ ] **`amiga/tournament/videos/atmosphere.php`** — mode `atmosphere`
- [ ] **`amiga/tournament/videos.php`** — replace body with `amiga_tournament_videos_legacy_redirect()` only

### TV-FOLDER-3 — Shell and render

- [ ] **`includes/amiga_tournament_page.php`** — read `$k2AmigaTournamentVideosMode`; replace `amiga_tournament_videos_wing_from_request()`; call mode redirect after partition; rename `$tournamentVideosWing` → `$tournamentVideosMode`
- [ ] **`includes/amiga_tournament_videos_lib.php`** — remove `wing_from_request`; play button uses `$mode`
- [ ] **`includes/amiga_tournament_videos_wc_render.inc.php`** — nav hrefs `games` / `atmosphere`; rename params
- [ ] **`includes/amiga_tournament_videos_wc_body.inc.php`** — `$tournamentVideosMode`, `data-k2-tv-mode`

### TV-FOLDER-4 — Time travel cleanup

- [ ] **`includes/amiga_snapshot_url.php`** — revert to stripping all `wing` when `as=` set (no page-local `wing=extras` left); simplify carry merge

### TV-FOLDER-5 — Verification

- [ ] Update **`scripts/oneoff/amiga_tournament_videos_wing_tt_probe.php`** → path assertions
- [ ] Run **`amiga_tournament_tt_link_probe.php`** + snapshot context probe
- [ ] Browser: `videos/games.php?id=26&as=year:2005` — Atmosphere tab navigates to `videos/atmosphere.php`

### TV-FOLDER-6 — Docs (Part A)

- [x] **`docs/url-routes.md`** — folder paths; remove `wing=extras`
- [x] **`docs/k2-page-structure-checklist.md`** — agent onboarding (this track motivated the checklist)
- [ ] **`docs/k2-embedded-video-page-policy.md`** §2.1 — path-based modes
- [ ] **`docs/amiga-tournament-videos-policy.md`** §9.1
- [ ] **`docs/amiga-tournament-videos-implementation-plan.md`** — TV-FOLDER slice row
- [ ] **`PROJECT_MEMORY.md`** — one line

**Migration:** L0 — URL/markup only; no DDL.

**Out of scope:** Player Videos wing (single surface); JS behaviour unchanged (path-aware index URL from server).