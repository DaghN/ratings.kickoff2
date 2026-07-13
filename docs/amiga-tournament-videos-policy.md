# Amiga tournament videos — policy

> **Product policy (Jul 2026):** Catalog + UI rules below remain authoritative. **Forward video path:** [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) on **`ko2amiga_work`**. §12 oracle `sync_db_ids` / **`prove`** = frozen archaeology only.

**Status:** **Catalog live (Jun 2026)** — manifest + tournament Videos tab + player profile **Videos** wing shipped. **DB anchor sync (Jul 2026)** — oracle `sync_db_ids` + `verify-tournament-videos` after full L3 reimport on frozen DB ([§12](#12-db-anchors-vs-editorial-keys-jul-2026)); forward = **`align-video-work`** in simul. Deep-link interaction: [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md).

**Parent:** [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) · [`url-routes.md`](url-routes.md) · [`design-direction.md`](design-direction.md) · [`join-play-setup.md`](join-play-setup.md)

**Related:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §8 (Lane C — live community writes → staged DB; JSON manifest = canon archive) · [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) (match facts, sync/remap, verify — **Jul 2026**) · [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md) (WC Games/Atmosphere URL, Back, share links) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`self-hosted-assets.md`](self-hosted-assets.md) (YouTube embed precedent) · [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md)

**Authority:** Dagh’s exploration chats (Jun 2026). **Implementation plan:** [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) — slices TV-0–TV-6.

---

## 1. Executive summary

Embed **official tournament match video** on the Amiga site — primarily **World Cups**, but the catalog may include other rated events (UK Championships, national cups, Greek Championships, etc.) when mapped.

**v1 product:**

- **Videos** tab on `/amiga/tournament/` (foldered sibling of Event stats · Stages · Games) — label **Videos**, not Media.
- Tab appears **only** when the tournament has ≥1 catalogued clip.
- **Chronology** wing flags tournaments that have video.
- **Tournaments** hub filter **Has videos** — honest discovery (“unless video, it didn’t happen”).
- **Player profile Videos** wing when manifest has match rows for that player ([§9.5](#95-player-profile--videos-wing)).
- **Curated JSON manifest** in repo (no DB migration — editorial keys in CSV; numeric FKs are DB caches refreshed by sync — [§12](#12-db-anchors-vs-editorial-keys-jul-2026)).

**Later (explicitly out of scope):** realm-wide video index page; game-row “watch this match” links on every table; splitting long streams into per-game clips (Steve-assisted).

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **TV1** | **Tab name** | **Videos** — not Media. Photos may get their own tab later. |
| **TV2** | **Tab placement** | New foldered tab under `amiga/tournament/` — same nav pattern as `event-stats.php`, `stages.php`, `games.php` ([`url-routes.md`](url-routes.md)). Show tab only when manifest has rows for `tournament_id`. |
| **TV3** | **Primary home** | Per-tournament **Videos** tab is v1 home. WC **Chronology** + **Tournaments → Has videos** are discovery surfaces. |
| **TV4** | **Embed host** | YouTube via `youtube-nocookie.com/embed/…?origin={site}` — `k2_youtube_embed_url()` / JS `embedUrl()`; page `<meta name="referrer" content="strict-origin-when-cross-origin">` + iframe `referrerpolicy`. Same habit as `game.php` and `join_page_section.php`. |
| **TV5** | **Stored truth v1** | Checked-in manifest JSON — **not** a new DB table. **`youtube_id` + player/tournament names in `review.csv`** are stable editorial keys; **`tournament_id` / `player_*_id` / `game_ids`** are DB caches — must be re-synced after every full L3 witness reimport ([§12](#12-db-anchors-vs-editorial-keys-jul-2026)). |
| **TV6** | **Player association** | **`match`** clips with two clearly identified players → store `player_a_id` + `player_b_id` (nullable FKs to `amiga_players.id`). |
| **TV7** | **Streams** | Long multi-hour **day/stream** uploads → `kind: stream`; link to tournament; **no** player IDs. |
| **TV8** | **Ceremony / atmosphere** | Presentations, medals, venue mood → `kind: ceremony` or `atmosphere`; tournament link when obvious; no players. |
| **TV9** | **Dedupe** | **Never** merge rows because tournament + stage + players + score match. Each YouTube ID is its own row until a human marks a **relation** (§5). |
| **TV10** | **Canonical embed** | One **canonical** clip per logical group for default embed; alternates listed, not hidden. |
| **TV11** | **WC finals playlist** | Dagh’s [WC finals playlist](https://www.youtube.com/playlist?list=PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY) marks **featured finals** when multiple uploads exist — not a dedupe authority. |
| **TV12** | **Forum index** | [KO-Gathering t=15358](https://ko-gathering.com/forum/viewtopic.php?t=15358) is the **structured bootstrap** (scores, stages, alternate URLs). Parse before blind channel crawl. |
| **TV13** | **Non-Amiga events** | Online WC, pure tutorials, unrelated compilations → `kind: excluded` or omit from tournament pages (may still link from Join / elsewhere). |
| **TV14** | **Time travel** | Video is historical artifact — show under `as=` cutoffs (same as ceremony photos intent in [`design-direction.md`](design-direction.md)). |
| **TV15** | **No new derived writers** | Read-only editorial catalog — no new L5 aggregate tables or post-game writers. **`prove`** runs **`sync_db_ids`** to refresh manifest DB caches only ([§12](#12-db-anchors-vs-editorial-keys-jul-2026)). |

---

## 3. What this is (and is not)

**Is:**

- A **merged catalog** of community tournament footage from multiple YouTube channels + Dagh’s forum vault + playlist spine.
- A way to connect **stats tables** to **proof** — finals, knockouts, presentations, streams.
- A foundation for **player Videos** tabs (reverse index on `player_a_id` / `player_b_id` — shipped Jul 2026; requires fresh DB anchors — §12).

**Is not:**

- A replacement for YouTube hosting or re-uploading video files to the site.
- Automatic dedupe by metadata alone.
- In scope for **online realm** v1 (except marking online WC clips `excluded` in manifest).
- A promise that every WC year has video (2018 offline WC XVIII Bournemouth: **no known final** in current sources).

---

## 4. Source inventory (Jun 2026 exploration)

Harvest **all** sources into one union keyed by `youtube_id`. Assign `source` + optional `source_channel`.

| Source | URL | Role | Notes |
|--------|-----|------|-------|
| **Steve — KO2CV TV** | https://www.youtube.com/@KO2CV_TV/videos | Main archive (~203 uploads) | Knockouts, streams, UK Champs, recent WCs, legacy `Athens08_*.avi` titles |
| **Dagh — Alkelele** | https://www.youtube.com/@Alkelele/videos | Early finals + extras (~103) | WC 2001–2006 finals (often **only** here); 2004 semis/KOA cup; 2008 presentations; Milan I 2003; tutorials |
| **WC finals playlist** | https://www.youtube.com/playlist?list=PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY | Finals spine (40 entries) | By Dagh Nielsen; 22 offline WC years; **missing 2018**; includes one **Online WC 2024** entry |
| **Costas — 11costas11** | https://www.youtube.com/@11costas11/videos | Greek Champs 2011 (~20) | Matches forum § “3rd Greek Championships, 2011” |
| **Forum video vault** | https://ko-gathering.com/forum/viewtopic.php?t=15358 | Structured index | Event, stage, players, score, often **multiple URLs** per match (YouTube + WMV) |
| **Legacy file hosts** | `ko-gathering.com/media/tournament/*.WMV`, kickoffworld.net, kickoff2.it | Possible higher-quality alternates | Many Google Video / Veoh links **dead**; probe WMV paths in harvest |

**Overlap facts (do not dedupe blindly):**

- Early WC finals (2001–2006, parts of 2008–2009) live on **@Alkelele**, not always on @KO2CV under the same ID.
- WC 2008: @Alkelele has titled finals; @KO2CV has `Athens08_*` uploads — likely **same matches**, different uploads (relation group, not merge).
- WC 2010 Düsseldorf final: forum lists **two YouTube IDs per leg** (`Q73jrEIrBWQ` + `5fcMDx6bCPc`, `fmgSSgTmEXE` + `2hNTpnnu8AY`) — **alternate recordings or encodes**.
- Athens 2002 QF: forum lists YouTube **and** ko-gathering WMV — **alternate quality**, same match.

Steve’s forum reply: thread covers most of his camcorder tapes; **tape originals** may exceed YouTube quality — future recovery path, not v1 blocker.

---

## 5. Dedupe and relation policy

**Primary key:** `youtube_id` (11-char ID). One manifest row per ID.

**Forbidden:** collapsing rows because `tournament_id + stage + leg + player_a_id + player_b_id + score` match.

**Relation groups:** optional string `relation_group` (e.g. `wc2010-final-leg1-dagh-gianni`) linking rows that *might* depict the same match.

| `relation` | Meaning | Default UI |
|------------|---------|------------|
| `canonical` | Default embed for this group | Featured player / top of section |
| `alternate_recording` | Same match, different camera or upload | Listed under “Also available” |
| `alternate_quality` | Same recording, different encode (YouTube vs WMV mirror) | Link out; prefer higher quality when known |
| `uncertain` | Same metadata, not yet reviewed | Keep both; flag in review CSV |

**Review signals (human or assisted, not auto-merge):**

1. Duration within ~30s + same title/score → *candidate* same recording.
2. Forum bullet with **two YouTube URLs** → default `alternate_recording` or `alternate_quality` until spot-check.
3. Different filenames (`Athens08_08_Final_A` vs `WC 2008 Athens, The Final leg 1`) → relation group, separate rows.
4. Visual spot-check or Steve/Dagh confirmation for ambiguous pairs.

**WMV / non-YouTube URLs:** store as `external_url` + `kind: external_file` when probed alive; no `youtube_id`. YouTube row remains if both exist.

---

## 6. Video kinds and player rules

| `kind` | Description | `player_a_id` / `player_b_id` | Typical `stage` |
|--------|-------------|----------------------------------|-----------------|
| `match` | Single rated game, two players | **Set** when resolvable from title | `final`, `semi`, `quarter`, `bronze`, `silver`, `shame`, `exhibition`, `league`, … |
| `ceremony` | Presentations, medals, awards | null | `presentations`, `medals` |
| `atmosphere` | Venue / crowd / non-match | null | `atmosphere` |
| `stream` | Long day coverage (often 1–6+ hours) | null | `day1`, `day2`, `stream` |
| `compilation` | Multi-event highlights | null | — |
| `external_file` | WMV/other host | null or match if known | — |
| `excluded` | Online WC, tutorials, wrong realm | null | — |

**Dropped (off file):** rows removed from `review.csv` into `data/amiga/tournament_videos/dropped.csv` when not KO2 / not catalog material (channel noise). Re-harvest denylist; not shown on orphans page.

**Player name parsing:** YouTube titles use compact tokens (`DaghN`, `GianniT`, `FabioF`) or full names (`Dagh N - Gianni T`). Resolve via **first name + surname initial** against `amiga_players` (disambiguates multiple Fabios). If ambiguous → leave null; flag in review CSV.

**Streams:** titles like `KOA WC2006`, `WC2001 Dartford Part2a Saturday`, `Day 1` → `kind: stream`. Do not attach players. Future: Steve may split into per-game uploads → new manifest rows.

---

## 7. Tournament mapping

**Primary join:** `tournament_id` → `tournaments.id` in `ko2amiga_db`.

**Signals for auto-suggest (review required):**

- Year in title (`World Cup 2025`, `WC 2008`, `WC2001`)
- Host city (`Milan`, `Milano`, `Athens`, `Athens08` in filename)
- Event name in forum index section header

**Known gaps:**

| Item | Status |
|------|--------|
| WC XVIII **2018** (Bournemouth) | No final in playlist or explored channels |
| **3rd Greek Championships 2011** | 16+ clips on @11costas11; forum mapped; **no** `tournaments` row named “Greek Championships” — map manually to Athens 2011 event or add disposition |
| **Milan I 2003** | On @Alkelele; **not** a World Cup — separate tournament or exclude |
| **UK Championships** | Many clips; separate events from WC — map per year/host |

**WC detection:** reuse `amiga_tournament_is_world_cup()` for WC-specific UX (Chronology flags); non-WC events still eligible for Videos tab when mapped.

---

## 8. Data model (manifest v1)

**Path (locked):** `site/public_html/data/amiga/tournament_videos.json` (PHP reads at runtime). Source queue: `data/amiga/tournament_videos/review.csv` (mirrored under `site/public_html/data/amiga/tournament_videos/` on build).

**Row shape:**

```json
{
  "youtube_id": "wTqyB6iHKjU",
  "title": "Kick Off 2 Amiga, WC 2008 Athens, The Final leg 1, Dagh N - Gianni T (6 - 4)",
  "tournament_id": 358,
  "kind": "match",
  "stage": "final",
  "leg": 1,
  "score": "6-4",
  "player_a_id": 73,
  "player_b_id": 149,
  "duration_sec": 704,
  "sort": 10,
  "source": "forum_index",
  "source_channel": "alkelele",
  "source_playlist": null,
  "relation_group": "wc2008-final-leg1-dagh-gianni",
  "relation": "canonical",
  "featured_final": false,
  "verified": false,
  "notes": ""
}
```

**Field notes:**

- `source`: `forum_index` | `ko2cv_channel` | `alkelele_channel` | `costas_channel` | `wc_finals_playlist` | `manual`
- `featured_final`: true when row is the playlist spine entry for that WC year
- `verified`: true only after human review CSV sign-off
- `external_url`: optional sibling to YouTube for WMV mirrors

**PHP read libs (shipped):** `includes/amiga_tournament_videos_lib.php` (tournament index) · `includes/amiga_player_videos_lib.php` (player reverse index) — load JSON once per request; `amiga_tournament_has_videos($id)` · `amiga_player_has_videos($id)`.

---

## 9. UI contract (v1)

### 9.1 Tournament Videos tab

- **Route:** `/amiga/tournament/videos/games.php?id={tournament_id}` (default) · `/amiga/tournament/videos/atmosphere.php?id={tournament_id}` — register in `k2_amiga_routes.php`. Legacy `/amiga/tournament/videos.php` 302s to folder paths.
- **Layout (all events with manifest rows):** **Games** + **Atmosphere** mode tabs (folder paths), single spotlight embed, sortable index table, `?v=` / optional `game=` / optional `t=` deep links — same stack as World Cups (`amiga_tournament_videos_wc_body.inc.php` + `amiga-tournament-videos.js`). Hide **Games** when no linked games (`kind=match` or `game_link_mode` `multi`/`stream_map` with `game_ids[]`); hide **Atmosphere** when no ceremony/coverage rows.
- **Interaction:** [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md) §2 — Back-to-index, share URLs; manifest `game_start_sec[]` offsets wired on Games wing + `game.php` (Jul 2026).
- **Nav:** insert **Videos** in `amiga_tournament_page.php` pill row when `amiga_tournament_has_videos($id)`.
- **Games index:** one row per linked `game_id` — match uploads, dual-leg (`multi`), and stream timestamp maps (`stream_map`); player names from DB game row; WC knockout ordering when applicable; generic stage/kind sort otherwise.
- **Atmosphere index:** ceremony, coverage, stream, and other non-match `kind` rows.
- **Alternates:** secondary links in table when `relation_group` has non-canonical siblings.

### 9.2 World Cups Chronology

- Icon or clip count on rows where `amiga_tournament_has_videos($tournament_id)`.
- Optional: link directly to Videos tab.

### 9.3 Tournaments hub — Has videos filter

- New segment tab: **Has videos** (`?filter=has-videos` on `amiga/tournaments.php`).
- Filter: any tournament with ≥1 manifest row (any `kind` except `excluded`).
- Implement alongside existing All · World Cups · Leagues · … tabs ([`amiga_tournament_index_nav.php`](../site/public_html/includes/amiga_tournament_index_nav.php)).

### 9.4 Orphans dev page

- **Route:** `/amiga/videos/orphans.php` — **not linked from hub**; dev/reference for harvest leftovers.
- **Sections:** curated unassigned groups (tutorials, general KO2, dup candidates) + **Excluded** table (manifest dupes / catalog decisions, with `?` tooltips).
- **Human review sign-off (Jun 2026):** no remaining row should map to a tournament Videos tab. Re-open only when new harvest rows appear or a specific clip is reported misplaced.

### 9.5 Player profile — Videos wing

- **Route:** `/amiga/player/videos.php?id={player_id}` — `amiga-player-videos` in `k2_amiga_routes.php`.
- **Nav:** **Videos** pill on player wing nav when `amiga_player_has_videos($id)` — manifest `player_a_id` / `player_b_id` on **match** rows **or** any manifest `game_ids[]` row where the player participated (includes `stream_map` / `multi`); DB lookup when only stream links exist.
- **Index:** cross-tournament game table, **reverse chronological** (game date, tournament chrono fallback); ID · Date · Tournament · game row · play button.
- **Filter:** opponent listbox above table (same stack as player **Games** — `k2_archive_listbox`, `individual3-filters.js`); options = opponents with ≥1 linked video, **A–Z**, game count in panel meta; `?opponent={id}`.
- **Time travel:** index rows ≤ active cutoff (tournament event tuple on linked game); opponent facets recomputed on filtered set.
- **Player:** same spotlight embed stack as tournament Videos (`amiga-tournament-videos.js`, `?v=` / `game=` deep links).

### 9.6 Later UI (not v1)

- Player profile **Videos** tab — all manifest rows where `player_a_id` or `player_b_id` matches.
- Realm **Video index** page — sortable table of all clips.
- Event stats **featured final** embed above fold.

---

## 10. Harvest workflow (Slice 0)

```
Forum t=15358 parse ──┐
@KO2CV_TV crawl ──────┤
@Alkelele crawl ──────┼──► union by youtube_id ──► review.csv ──► verified JSON
WC finals playlist ───┤         │                      ▲
@11costas11 crawl ────┘         └── relation_group hints
Legacy WMV probe ───────────────► external_url rows (optional)
```

**Review CSV columns (minimum):** `youtube_id`, `title`, `guessed_tournament_id`, `year`, `kind`, `stage`, `player_a_guess`, `player_b_guess`, `score`, `source`, `relation_group`, `confidence`, `verified`, `notes`.

**Human review focuses on:**

- Greek Champs 2011 → `tournament_id`
- Milan I 2003 vs WC
- UK Championship events
- 2010-style dual URLs per match
- `excluded` vs tournament-linked online WC clip
- Dead forum links worth asking Steve about

**Human review sign-off (Jun 2026):** Dagh completed two manual passes:

| Queue | Scope | Outcome |
|-------|--------|---------|
| **Tournament catalog** | `/amiga/tournaments.php` — all events with manifest clips (World Cups + leagues + cups) | No obvious mis-assignments; manifest **~299** videos stable |
| **Orphan → tournament** | `/amiga/videos/orphans.php` — unassigned + excluded harvest rows | Nothing left that should ship on a tournament Videos tab |

**After sign-off — what orphans still contains (by design):**

- **Unassigned** — tutorials (e.g. Alkelele shot-height series), general KO2 misc, ko2cv duplicate candidates — not event-scoped.
- **Excluded** — lower-quality dupes, online WC (offline catalog), rows with no DB game — audit trail only.
- **Dropped** — `data/amiga/tournament_videos/dropped.csv` — not KO2; removed from `review.csv` + re-harvest denylist.

Re-open either queue only on **new harvest**, **manual adds**, or a **specific misfile report**. Per-row `verified=Y` on every CSV row is still not required for ship.

**Tooling:** Python script under `scripts/amiga/` or `scripts/oneoff/` — not production path.

---

## 11. Implementation slices (planned)

| Slice | Deliverable | Exit criteria |
|-------|-------------|---------------|
| **0 — Harvest** | Parser + crawlers + `review.csv` | All six source feeds represented; no auto-merge |
| **1 — Manifest** | Verified `amiga_tournament_videos.json` | Dagh sign-off on ambiguous rows |
| **2 — Read lib + one WC** | PHP lib + Videos tab on **WC XXIII Milan 2025** | Tab renders grouped sections; lazy embed |
| **3 — Discovery** | Chronology flag + **Has videos** filter | Filter returns only tournaments with clips |
| **4 — Rollout** | All mapped WCs + major non-WC events from manifest | No regressions on tournaments without video |
| **5+ — Player / index / game links** | Profile Videos wing, global index, stream splits | Profile wing **shipped**; **GL-0…GL-6** game-link pipeline **shipped**; **stream Games index UI shipped (Jul 2026)** — `stream_map`/`multi` fan-out on tournament + player Videos wings; `game_start_sec[]` on play links |

**Implementation plan doc:** [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) — **written Jun 2026**; execute TV-1 onward.

---

## 12. DB anchors vs editorial keys (Jul 2026)

**Game-link mechanics (Jul 2026):** [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) — stable match facts vs DB caches, sync/remap rules, verify oracle, multi-game links (dual-leg + **`video_game_links.csv`** streams). **GL-0…GL-6 shipped**; this § stays the short summary.

Full L3 witness reimport (`import-witness`, `python -m scripts.amiga prove`) **truncates and rebuilds** `amiga_players`, `amiga_games`, and `tournaments`. Auto-increment ids are **not stable** across holy loops — player merges and catalog changes shift ids for everyone after the affected sort position.

### Stable (do not lose)

| Layer | Keys |
|-------|------|
| Editorial | `youtube_id`, video title, `kind`, relations, harvest metadata |
| Human mapping | `tournament_guess_label`, `player_*_guess`, `score`, `stage`, `leg`, **`game_link_mode`**, optional **`video_game_links.csv`** sidecar — **authoritative for video→game links** ([game-links policy](amiga-tournament-videos-game-links-policy.md)) |

### DB cache (must refresh after every full reimport)

| Field | Source of truth after sync |
|-------|----------------------------|
| `guessed_tournament_id` / manifest `tournament_id` | `tournaments.name` lookup; label corrected from id when id is authoritative |
| `player_*_id_guess` / manifest `player_*_id` | Player display name lookup |
| `game_id_guess` / manifest `game_ids` | **Remapped** from match facts (+ optional `source_scores_id` cache) — **not** heuristic re-resolve on verified rows ([game-links policy §8.3](amiga-tournament-videos-game-links-policy.md)) |

**Player profile Videos tab** indexes on manifest `player_a_id` / `player_b_id` — stale ids hide the tab from the correct player and may attach clips to the wrong profile even when tournament Videos tabs still work (they use `game_ids`).

### Process (locked)

1. **`python -m scripts.amiga.tournament_videos.sync_db_ids`** — refresh CSV caches from live `ko2amiga_db`, **remap** match `game_id`s from editorial facts, rebuild `tournament_videos.json`. Flags: `--dry-run`, `--no-resolve`, `--no-rebuild`.
2. **`python -m scripts.amiga.verify_tournament_videos`** — read-only oracle (also **`prove`** step `verify-tournament-videos`); **target:** score + multi-id + fact-vs-cache checks ([game-links policy §8.4](amiga-tournament-videos-game-links-policy.md)).
3. **`python -m scripts.amiga prove`** — after L5 replay, runs **sync_db_ids** automatically, then verify suite includes tournament-video oracle.

Harvest / manual ROW_PATCHES edit **stable keys** only; never hand-edit numeric ids except via sync output.

---

## 13. Coverage snapshot (exploration, not canonical)

Approximate counts from Jun 2026 browser harvest:

| Metric | Value |
|--------|-------|
| @KO2CV_TV uploads | ~203 |
| @Alkelele uploads | ~103 |
| WC finals playlist entries | 40 (incl. 1 online WC; **missing offline 2018**) |
| @11costas11 | ~20 (Greek Champs 2011) |
| Forum index | 100+ structured match entries (many dead Google links) |
| WCs with any explored clip | ~19–22 of 23 offline WCs |

Treat this table as **exploration notes** — manifest counts after Slice 0 are authoritative.

---

## 14. Open questions

1. **Manifest path** — **Locked in plan:** shipped JSON at `site/public_html/data/amiga/tournament_videos.json`; harvest/review under `data/amiga/tournament_videos/`.
2. **Greek Championships 2011** — map to existing `Athens LXX*` row or new disposition.
3. **WMV mirrors** — v1 link only, or attempt self-host later (large assets).
4. **Featured final on Event stats** — v1 or slice 5+.
5. **Online realm** — keep online WC final on `game.php` only, or cross-link from manifest `excluded` row.

---

## 15. References

| Item | Link |
|------|------|
| Forum video vault (Dagh) | https://ko-gathering.com/forum/viewtopic.php?t=15358 |
| Steve channel | https://www.youtube.com/@KO2CV_TV/videos |
| Dagh channel | https://www.youtube.com/@Alkelele/videos |
| WC finals playlist | https://www.youtube.com/playlist?list=PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY |
| Greek 2011 channel | https://www.youtube.com/@11costas11/videos |
| Join page playlist link | [`join_page_links.php`](../site/public_html/includes/join_page_links.php) |
| Tournament nav reference | [`amiga_tournament_page.php`](../site/public_html/includes/amiga_tournament_page.php) |