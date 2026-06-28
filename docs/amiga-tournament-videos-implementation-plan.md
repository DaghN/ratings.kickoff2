# Amiga tournament videos — implementation plan

**Status:** **In progress (Jun 2026)** — policy locked; **TV-0–TV-3 shipped**; **TV-1 human review sign-off (Jun 2026)** — tournaments index + orphan→tournament queues complete (see policy §10). **TV-URL** shipped (embedded player + deep links). **TV-4** partial — **With videos** filter shipped; Chronology clip indicator TBD. Future: admin bulk-verify page.

**Policy:** [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md)

**Parent:** [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) · [`url-routes.md`](url-routes.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`self-hosted-assets.md`](self-hosted-assets.md)

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

**Migration:** **L0** — read-only JSON manifest + PHP UI; no DDL, no `prove`, no Part B registers.

**Next artifact (optional):** [`orchestration/agent-handoffs/amiga-tournament-videos-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-videos-STARTER-PROMPT.md) for cold-start execution chats.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**TV1–TV15**), sources, dedupe rules, manifest schema, UI contract |
| **This plan** | File paths, harvest tooling, slice tasks, STOP gates, verification |
| **Starter prompt** | Copy-paste bootstrap for fresh agent chats (create when starting TV-1) |

---

## How to use this plan

1. Execute slices **TV-1 → TV-6** in order (**TV-0** = policy + plan — done).
2. **CSV review:** fix wrong `game_id` / tournament mappings as found (chat or future bulk-verify UI). Full `verified=Y` on every row is **not** a ship blocker — manifest carries `verified: true|false` per row.
3. **Future:** dedicated bulk-verify page — embed + proposed `game.php` link side-by-side (Dagh Jun 2026).
4. **STOP** if manifest JSON fails schema validation or references unknown `tournament_id` / `player_*_id`.
5. **STOP** if Videos tab appears on tournaments with zero manifest rows, or is missing when rows exist.
6. One slice per session; handoff note in chat + MEMORY line when a slice completes.
6. After **TV-6**: UPDATE_DOCS Part A (`url-routes.md`, policy status, MEMORY, feature-log row).

---

## Locked decisions (do not re-open without user)

See policy **TV1–TV15**. Compressed for implementers:

| # | Rule |
|---|------|
| Tab label | **Videos** (not Media) |
| Data | JSON manifest — **no DB table v1** |
| Dedupe | **One row per `youtube_id`** — relation groups only, never merge by match metadata |
| Players | Set on `kind: match` when resolvable; **null** on streams/ceremony |
| Sources | Six feeds + forum bootstrap (policy §4) |
| Embed | `youtube-nocookie.com` + lazy load |
| Discovery | Chronology flag + Tournaments **Has videos** filter |
| Out of v1 | Player Videos tab, global index, game-row links, stream splitting |

### Manifest paths (locked here)

| Path | Role | Git |
|------|------|-----|
| `data/amiga/tournament_videos/review.csv` | Human review queue (Slice TV-1 output) | Commit after Dagh edit, or commit template + local edits |
| `data/amiga/tournament_videos/raw/` | Optional per-source JSON dumps from harvest | Gitignore (`raw/*`) |
| `site/public_html/data/amiga/tournament_videos.json` | **Shipped manifest** PHP reads at runtime | **Commit** — WinSCP syncs with site |

PHP load path: `$_SERVER['DOCUMENT_ROOT'] . '/data/amiga/tournament_videos.json'`.

Build flow: harvest → `review.csv` → Dagh verifies → `scripts/amiga/build_tournament_videos_manifest.py` (or equivalent) writes shipped JSON.

---

## Reference implementation (copy patterns)

| Area | Reference |
|------|-----------|
| Policy + schema | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §5–§8 |
| Tournament page shell | `includes/amiga_tournament_page.php` — nav pills, `$k2AmigaTournamentView` |
| Tournament tab entry | `amiga/tournament/event-stats.php` — one-liner + include |
| Tournament routes | `includes/k2_amiga_routes.php` — `amiga-tournament-*` keys |
| Tournament index filter nav | `includes/amiga_tournament_index_nav.php` + `amiga/tournaments.php` |
| WC Chronology table | `includes/amiga_world_cups_events_table.php` |
| YouTube embed (lazy optional) | `site/public_html/game.php` — `k2-game-page__video` / iframe |
| Join embed | `includes/join_page_section.php` |
| Player name lookup | `amiga_tournament_player_names()` in `amiga_tournament_lib.php` |
| Player id from name | `amiga_players` table — match compact tokens (`DaghN` → first + initial) |
| WC year → id | Query `tournaments` where name REGEXP `^World Cup` + `event_date` year |
| K2 nav new tab | [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) — tournament nav in `amiga_tournament_page.php` |
| Forum index (bootstrap) | https://ko-gathering.com/forum/viewtopic.php?t=15358 |
| Existing Amiga data scripts | `scripts/amiga/` package layout |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **TV-0** | Policy + this plan | Dagh OK — **done** |
| **TV-1** | Harvest tooling + `review.csv` | All six sources represented; **no row merges**; relation_group hints on forum dual-URL bullets |
| **TV-2** | Verified `tournament_videos.json` | Dagh sign-off; JSON validates; all `verified: true` rows have valid FKs |
| **TV-3** | PHP read lib + Videos tab (pilot WC XXIII) | Browser: Milan 2025 shows tab, grouped sections, lazy embed, player links |
| **TV-4** | Chronology video flag + **Has videos** filter | Browser: filter only lists tournaments with clips; Chronology icon/count correct |
| **TV-5** | Rollout remaining mapped tournaments | Spot-check 3 WCs + 1 non-WC; tournaments without video unchanged |
| **TV-6** | Docs closure | url-routes, policy status, MEMORY, feature-log; optional CSS file note in self-hosted-assets |

**Pilot tournament:** `tournament_id = 25` — World Cup XXIII (Milan) 2025 — richest recent coverage.

---

## TV-1 — Harvest tooling + review CSV

### Goal

Automate catalog union from all sources into a **review queue**. No PHP yet.

### Tasks

- [x] **`scripts/amiga/tournament_videos/`** package (or `scripts/amiga/harvest_tournament_videos.py`):
  - **Forum parser** — fetch t=15358; extract section headers (event names), match lines (players, score, stage), all YouTube IDs + WMV URLs per bullet.
  - **YouTube harvesters** — flat list `{id, title, duration_sec?}` from:
    - `@KO2CV_TV/videos`
    - `@Alkelele/videos`
    - `@11costas11/videos`
    - Playlist `PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY`
  - Prefer **yt-dlp** `--flat-playlist` when SSL works; fallback documented in script README (browser export / manual JSON drop into `raw/`).
  - **WMV probe** (optional) — HEAD request ko-gathering / kickoffworld URLs from forum; emit `external_url` rows without `youtube_id`.
- [x] **`scripts/amiga/tournament_videos/enrich.py`** (or single module):
  - Guess `year`, `kind`, `stage`, `leg`, `score`, `player_a_guess`, `player_b_guess`, `guessed_tournament_id` from title + forum context.
  - Load WC catalog from MySQL (`tournaments` where WC) for year→id map.
  - Load `amiga_players` for name resolution hints.
  - Assign `source` / `source_channel` per policy §8.
  - Set `featured_final: true` when ID appears in WC finals playlist (offline WC years only).
  - **Relation hints:** when forum lists 2+ YouTube IDs under one bullet → shared `relation_group`, default `relation: uncertain`.
  - When title patterns suggest stream (`Part\d`, `KOA WC20\d\d`, `Day \d`, duration > 3600s) → `kind: stream`.
- [x] **Output:** `data/amiga/tournament_videos/review.csv` with columns:

  `youtube_id, title, duration_sec, guessed_tournament_id, tournament_guess_label, year, kind, stage, leg, score, player_a_guess, player_a_id_guess, player_b_guess, player_b_id_guess, source, source_channel, source_playlist, relation_group, relation, featured_final, confidence, verified, notes, external_url`

  (Fix column names — player_a_id_guess / player_b_id_guess as integers or empty.)

- [x] **`scripts/amiga/tournament_videos/README.md`** — how to run, SSL workaround, re-harvest cadence.

### Verification

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python -m scripts.amiga.tournament_videos.harvest
# Expect review.csv row count >> 200 (union, not deduped by match)
# Expect known IDs present: -OD-f0t92VQ, tEb--soimgs, wTqyB6iHKjU, gmVFrhEr_IQ (Greek 2011)
# Expect 2010 final legs each have 2 rows with same relation_group hint
# Expect NO duplicate youtube_id rows in CSV
```

**Human gate:** Dagh edits `review.csv` — set `verified=Y`, fix `guessed_tournament_id`, `relation`, `kind`, Greek Champs 2011, Milan I 2003, excluded online WC.

**Sign-off (Jun 2026):** Manual review complete — tournaments index (all events) + orphan→tournament assignment; no obvious misplacements. Orphans page retains non-event KO2 + excluded audit only. See policy §10 **Human review sign-off**.

---

## TV-2 — Manifest JSON

### Goal

Turn `review.csv` into shipped manifest (pragmatic v1: all non-`excluded` rows with `guessed_tournament_id`).

### Tasks

- [x] **`scripts/amiga/tournament_videos/build_manifest.py`**:
  - Default: all mapped rows (`kind != excluded`, `guessed_tournament_id` set).
  - Optional `--verified-only` for strict gate.
  - Writes `site/public_html/data/amiga/tournament_videos.json` with per-row `verified` from CSV.
  - `game_id_guess` → `game_ids` array in JSON.
- [x] **`scripts/amiga/tournament_videos/validate_manifest.py`** — unique IDs, FK spot-check, one canonical per relation group.

### Verification

```powershell
python -m scripts.amiga.tournament_videos.build_manifest
python -m scripts.amiga.tournament_videos.validate_manifest
# validate exits 0
# JSON pretty-print; count verified rows matches Dagh expectation
```

**STOP:** Do not start TV-3 until Dagh confirms ambiguous rows resolved (policy §13 open questions).

---

## TV-3 — PHP read lib + Videos tab (pilot)

### Goal

Read manifest on tournament pages; ship **Videos** tab for WC XXIII (id 25) first.

### Tasks

- [x] **`includes/amiga_tournament_videos_lib.php`** (new):
  - `amiga_tournament_videos_manifest(): array` — load JSON once per request (static cache).
  - `amiga_tournament_videos_for_id(int $tournamentId): array` — rows for tournament, excluding `kind: excluded`; sort by `sort` then stage order.
  - `amiga_tournament_has_videos(int $tournamentId): bool`
  - `amiga_tournament_videos_grouped(array $rows): array` — keys: `final`, `knockout`, `side`, `ceremony`, `coverage` per policy §9.1.
  - `amiga_tournament_video_embed_url(string $youtubeId): string` — nocookie embed URL.
  - `amiga_tournament_video_thumb_url(string $youtubeId): string` — `i.ytimg.com/vi/{id}/hqdefault.jpg`.
- [x] **`includes/k2_amiga_routes.php`:** add `amiga-tournament-videos` → `amiga/tournament/videos.php`.
- [x] **`includes/amiga_tournament_lib.php`:** add `amiga_tournament_videos_url(int $id): string` helper (mirror `amiga_tournament_games_url`).
- [x] **`amiga/tournament/videos.php`** — set `$k2AmigaTournamentView = 'videos'`; include `amiga_tournament_page.php`.
- [x] **`includes/amiga_tournament_page.php`:**
  - When `amiga_tournament_has_videos($id)` → add **Videos** pill (after Event stats or before Games — match WC nav order).
  - When `$pageView === 'videos'` → render body via new include.
- [x] **`includes/amiga_tournament_videos_body.inc.php`** (new):
  - Sections per grouped rows.
  - Match row: title, linked players (`k2_player_link` / Amiga profile href), score if set.
  - **Canonical** embed: lazy iframe (click thumbnail → load iframe) — reuse/adapt `k2-game-page__video` classes or add `amiga-tournament-videos.css`.
  - **Alternates:** list under “Also available” with link to YouTube; optional second lazy embed.
  - **Streams:** label “Long coverage”; link-first default (embed on click).
- [x] **`stylesheets/amiga-tournament-videos.css`** — minimal; enqueue from videos view only.
- [x] **`js/amiga-tournament-videos.js`** — lazy embed on thumbnail click; `k2:page-ready` hook for Turbo.
- [x] **Legacy redirect:** `?view=videos` → folder path (via `amiga_tournament_lib.php` view map).

### Verification

```text
Browser: http://ratingskickoff.test/amiga/tournament/videos.php?id=25
- Videos tab visible on id=25; absent on a tournament with no manifest rows
- Final section shows DaghN v GianniT embed
- Player names link to Amiga profiles when ids set
- Lazy embed: no iframe until click
- Time travel: append &as=… — videos still show (TV14)
```

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/includes/amiga_tournament_videos_lib.php
```

---

## TV-URL — WC spotlight deep links (specced, not started)

**Policy:** [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md) §2 (Phase A + B).

### Goal

Shareable (hashless) URLs and in-session navigation on WC **Games / Atmosphere** wings: `v=` playback, optional `game=` for row identity, cold index = table only (no default clip), simple `pushState` / `popstate` Back. Cold deep links scroll to the player via the server-declared carry-scroll target, seed an index entry beneath the clip (so Back returns to the list), expose an "↑ All videos" link, and cap the player to the viewport height.

### Tasks (Phase A)

- [x] **`amiga_tournament_videos_url()`** — accept optional `v`, `game`, `t`, hash target; document in `url-routes.md`.
- [x] **`amiga_tournament_page.php`** — read `v` / `game` / `wing` from request; set spotlight + active row per policy §2.2.
- [x] **`amiga_tournament_videos_play_button_html()`** — real `href` deep links; keep button UX.
- [x] **`js/amiga-tournament-videos.js`** — in-page swap + `pushState` + `popstate` (Back §2.4); no Turbo. **URL is the single source of truth** (flagless `renderFromUrl`); clips mounted by **iframe node replacement**, not `src` reassignment — see §2.3 ⚠️ (YouTube embeds pollute session history and hijack Back otherwise).
- [x] **`docs/url-routes.md`** — query params `v`, `game`, `wing`, future `t`.
- [x] **Spotlight caption (game videos)** — `amiga_tournament_videos_wc_game_caption_html()` renders a headerless `k2-table--tournament-games` row above the player (linked id · [Phase] · A *(pre-game Elo)* · A · B · Player B *(pre-game Elo)* · flags), Elo linking to the rating LB table top; carried in `data-spotlight-html` for in-session `innerHTML` swap; Atmosphere keeps text caption. Policy §2.3.

### Tasks (Phase B — deferred)

- [ ] Manifest fields for in-file offsets; `t=` → embed `?start=`.

### Verification

```text
Cold: videos.php?id=25 → table only (no player, no row highlight)
Share (hashless): …&v=…&game=… → correct row + embed, scrolls to player (server target, no flash)
Cold Back: deep link → Back once → index (seeded entry; never leaves the site)
Cold scroll-up + pick another + Back → index (switch replaceState; still one Back to list)
In-session: pick clip → autoplay; Back once → index (player hidden, iframe node removed)
Switch clips (A→B while watching): replaceState → Back once → index (no cycling)  ← stack capped [index, clip]
Index after Back: last-watched row stays highlighted + scrolled into view (find next leg)
"↑ All videos" link (in label row) → index, NO centred highlight, hero pinned to viewport top (global nav above)
Browser Back (distinct from "All videos") → index, last-watched row highlighted + centred
Viewport fit: player fills viewport height when scrolled (chrome vars: 0.75rem top, ~2.5rem caption, 0.15rem caption↔video, 0.5rem bottom); width min(vh-cap×16/9, 100vw−2rem); NOT jukebox FAB
Caption: transparent bg + no row hover; 0.75rem caption↔viewport top; caption rests on video (0.15rem); left edge tracks player. "↑ All videos" anchored to player top-right corner (gutter)
```

---

## TV-4 — Discovery surfaces

### Goal

Chronology + Tournaments hub **Has videos** filter.

### Tasks

- [ ] **`includes/amiga_world_cups_events_table.php`** (or events body):
  - Column or icon: clip count / play glyph when `amiga_tournament_has_videos($tournament_id)`.
  - Link icon → `amiga_tournament_videos_url($id)` (optional).
- [ ] **`includes/amiga_tournament_index_nav.php`:**
  - Add tab `has-videos` → label **Has videos**; href via new filter helper.
- [ ] **`includes/amiga_tournament_lib.php`:**
  - Extend `amiga_tournament_index_filter_url()` / `amiga_tournament_index_matches_filter()` for `has-videos`.
  - Filter: tournament id in manifest index (any non-excluded kind).
- [ ] **`amiga/tournaments.php`:** accept `?filter=has-videos`; footnote count correct.
- [ ] **`docs/url-routes.md`:** document filter param + `amiga-tournament-videos` route.

### Verification

```text
Browser:
/amiga/world-cups/chronology.php — WC rows with video show indicator
/amiga/tournaments.php?filter=has-videos — only tournaments with manifest rows; count in footnote
Click through to Videos tab works with as= preserved
```

---

## TV-5 — Rollout

### Goal

All verified manifest rows live; no regressions elsewhere.

### Tasks

- [ ] Expand manifest in TV-2 to full Dagh-approved set (not only pilot id).
- [ ] Spot-check rendering:
  - WC with **alternates** (2008 or 2010) — “Also available” visible
  - WC with **streams only** (2001 Dartford parts) — coverage section
  - **Ceremony** row (2008 Presentations on Alkelele)
  - Non-WC if mapped (UK Champs or Greek 2011 once `tournament_id` set)
- [ ] Confirm tournaments **without** video: no Videos tab; index filter excludes them.
- [ ] Optional: featured final star on Chronology row when `featured_final: true` exists.

### Verification

```text
Manual spot-check 5 tournaments (see tasks).
validate_manifest.py still 0 errors.
No PHP notices on empty sections (tournament with 1 clip only in one section).
```

---

## TV-6 — Docs closure

### Tasks

- [ ] Policy status → **TV-3–TV-5 shipped** (or partial if staged).
- [ ] **`docs/url-routes.md`** — `amiga-tournament-videos` + has-videos filter.
- [ ] **`docs/self-hosted-assets.md`** — note YouTube embed on tournament Videos tab.
- [ ] **`PROJECT_MEMORY.md`** — Recent log.
- [ ] **`docs/coordination/feature-log.md`** — one row (L0 cosmetics + editorial data).
- [ ] Optional: **`join-play-setup.md`** — cross-link to WC Videos on site (not required).

### Verification

Part A checklist in [`UPDATE_DOCS.md`](UPDATE_DOCS.md) complete.

---

## Environment

| Item | Value |
|------|--------|
| Dev site | `http://ratingskickoff.test/amiga/…` |
| Amiga DB | `ko2amiga_db` (Laragon) |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| Pilot WC | `id=25` World Cup XXIII (Milan) 2025 |

---

## Handoffs and naming

| Slice complete | Handoff |
|----------------|---------|
| TV-1 | `review.csv` path + row count + top ambiguous rows for Dagh |
| TV-2 | Manifest path + verified count + unresolved FK warnings |
| TV-3 | Pilot URL + screenshot description |
| TV-4 | Filter URL + Chronology example tournament id |
| TV-5 | Rollout count + known gaps (2018, etc.) |

Store optional slice notes in chat; no separate handoff file unless multi-agent.

---

## Out of scope (track boundary)

- Player profile **Videos** tab (reverse index) — future plan
- Realm-wide video index page
- `game.php` row-level “watch this match”
- Splitting long streams with Steve
- Self-hosting WMV files
- Online realm tournament pages
- DB migration / admin UI for manifest edits

---

## Open items (TV-1 / TV-2 review — resolved Jun 2026)

| Item | Resolution |
|------|------------|
| Greek Champs 2011 → `tournament_id` | Mapped via disposition / review passes |
| Milan I 2003 | Mapped or excluded per review |
| 2010 dual URLs | Canonical/alternate pairs in manifest |
| Online WC 2024 in playlist | `kind: excluded` — not on Amiga tournament page |
| WMV mirrors | `external_url` on YouTube row or separate `external_file` row |
| **Tournament catalog placement** | **Sign-off Jun 2026** — no obvious mis-assignments on tournaments index |
| **Orphan → tournament queue** | **Sign-off Jun 2026** — nothing left on orphans page for tournament tabs |

Re-open on new harvest rows or specific misfile reports only.

---

## References

| Item | Link |
|------|------|
| Policy | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) |
| Agent track playbook | [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md) |
| Forum vault | https://ko-gathering.com/forum/viewtopic.php?t=15358 |