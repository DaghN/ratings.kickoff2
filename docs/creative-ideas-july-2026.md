# Creative ideas — July 2026

**Status:** Living brainstorm ledger (Jun–Jul 2026 chat). **Not authority** for product decisions — Dagh's latest message wins. Use this to understand *how* features tend to emerge here and what is **approved to explore** vs **already shipped** vs **parked**.

**Audience:** Dagh + Cursor agents on **creative / product brainstorm** sessions (task-triggered — see §4.0).

**Related:** [`PROJECT_BRIEF.md`](../PROJECT_BRIEF.md) (taste) · [`agent-track-playbook.md`](orchestration/agent-track-playbook.md) (when an idea becomes policy + plan + slices) · [`design-direction.md`](design-direction.md) (visual device rules)

---

## 1. Why this doc exists

Many of the site's best features did **not** arrive from an upfront roadmap. They coalesced while browsing, using the site, or remembering an old dream — then got a **framing device** and slotted into infrastructure that already existed.

This file captures:

1. **The repeatable recipe** behind past wins (so we can brainstorm on purpose, not only by accident).
2. **Origin stories** for proud shipped work (context for future agents).
3. **An idea ledger** from the Jul 2026 creative session — with Dagh's feedback (keep / defer / reject).
4. **A lightweight blueprint** for future creative expansion sessions.

**Do not** treat rows here as committed work. Promote an idea to a **track** only when Dagh says so → policy doc + implementation plan per [`agent-track-playbook.md`](orchestration/agent-track-playbook.md).

---

## 2. The recipe (how hits happen here)

| Step | Question |
|------|----------|
| **1. Obvious human question** | What do I keep asking while browsing? ("Greeks vs Italians?" · "Ladder in 2003?" · "Who from this country?") |
| **2. Infrastructure exists** | Leaderboards, snapshots, matchups, video manifest, milestones, league periods, etc. |
| **3. Framing device** | Time travel · fight poster · jukebox cockpit · indexed video (not thumbnail hell) · milestone tiers · LED stamp · heatmap |
| **4. Mood / hang-out goal** | Control room, biography, immersion — "stay awhile" (pairs with jukebox, Status pulse, News landing) |

**Best brainstorming question:**

> What obvious question do I keep asking — and what **single device** makes the answer feel inevitable?

**Anti-pattern:** "Add another sortable column" or "another generic entry point" without a clear human question.

---

## 3. Origin stories (shipped — context for agents)

| Feature | How it happened | Device / framing |
|---------|-----------------|------------------|
| **Milestones** | Well-known concept; **1–2 day frenzy** shipped tier garden + hub + 112-key catalog | Tier colour language (Aspirational → Legendary) |
| **Video index** | **Old dream:** present games/video without thumbnail hell — overview + click-to-play | Manifest + embed on event/game/player; not a grid of tiles |
| **Jukebox** | Spur-of-moment — remembered early-internet autoplay joy; **no autoplay**; cockpit / control-room mood | FAB launcher + gapless popup window |
| **Time travel** | "Historical view" coalesced during Amiga work; **"why don't we…"** + LED / time-travel naming | Single `as=` cutoff; ribbon + stamp; bookmarkable |
| **H2H fight poster** | Spin-off of basic P vs P stats; framing added over time | Versus card: avatars + big W·D·L |
| **Countries + Rivals** | Obvious questions ("who from Italy?" · "Greeks vs Italians?") slotted into LB + H2H infrastructure | Roster + nation-pair poster |

---

## 4. How to use this doc (agents)

### 4.0 Discoverability (locked Jun 2026)

| Question | Answer |
|----------|--------|
| Default cold-start read? | **No** — not in `AGENTS.md` bootstrap table |
| How agents find this file | `docs/PROJECT_MAP.md` · grep `creative-ideas` · Dagh says "review creative ideas" |
| When to read | Creative / product brainstorm sessions only — not routine feature or bugfix chats |
| When to update | End of a creative session when Dagh approves ledger changes or says **update docs** — see §7.1 |

### 4.1 Status labels

| Label | Meaning |
|-------|---------|
| **shipped** | Done — do not re-pitch as new work |
| **approved** | Dagh wants this; ready for explore → doc trio when scheduled |
| **gestating** | Good direction; timing / surrounding infra not ready |
| **spark** | Interesting; needs Dagh pass before any build |
| **parked** | Maybe later; no active intent |
| **rejected** | Do not re-propose without new context |

### 4.2 Creative session ritual (optional)

1. **Question harvest** — browse the site; note every "I wonder…" that takes >2 clicks to answer.
2. **Device pass** — map each question to: poster · timeline · heatmap · cockpit widget · video moment · ticker · year lens · prose band.
3. **Infrastructure check** — answer already in a table? → **framing project** (fast). Needs new writer? → **bet** (separate schedule).
4. **Mood test ("jukebox test")** — would this make someone stay with music playing?
5. **Parity scan** — online feast vs Amiga profile · Activity vs community stats · H2H grains · media coverage.

### 4.3 When to graduate an idea

| Size | Path |
|------|------|
| UI-only, no stored truth | Implement directly; UPDATE_DOCS Part A |
| Locked rules + multi-session | [`agent-track-playbook.md`](orchestration/agent-track-playbook.md) — policy + plan + slices |
| Stored Amiga truth | + [`amiga-data-contract.md`](amiga-data-contract.md) · `prove` |
| Stored online truth | + [`website-data-contract.md`](website-data-contract.md) · ops simul |

### 4.4 Birds-eye (Jun 2026 — where ideas tend to land)

```text
                    ONLINE                          AMIGA
LIVE PULSE          Status · Activity               (historical realm)
ENTITY DEPTH        Profile feast ✓✓✓              Profile v0 (feast parity TBD)
COMPARE             H2H poster ✓✓✓                 H2H + country + nation rivals ✓✓✓
HISTORY LENS        (present only)                   Time travel ✓✓✓
MEDIA               —                                Videos + game embed ✓✓
ACHIEVEMENTS        Milestones + HoF                 HoF + WC HoF + perfect events
COMMUNITY TEXTURE   Activity server charts           Community stats writers ✓ / UI ◐
LANDING             Status default                   News tab ◻ (placeholder)
```

---

## 5. Idea ledger (Jul 2026 session)

### 5.1 Approved — build when scheduled

| ID | Idea | Human question | Device | Realm | Notes |
|----|------|----------------|--------|-------|-------|
| **C01** | **Amiga profile feast parity** | What was this player's rhythm across decades? | Played-days/weeks heatmaps, bursts, story bands — **shrink/grow with time travel** | Amiga | **Gestating** until surrounding infra fully visible (same defer pattern as online profile was last). Bundles **rivalry teaser** (C09). |
| **C03** | **Community stats → Activity wings** | What kind of scene was this? | Question-catalog charts (46 Qs drafted); year bars + snapshot timeline; TT-aware | Amiga | Writers done; UI TBD — [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) |
| **C04** | **News / present landing** | What's worth looking at in present mode? | Scrollable **weekly** news roll + **daily** pulse sidebar (HoF (New!), online activity → Status, finalize, clips) | Amiga | `/amiga/news.php` placeholder — **intent:** [`present-layer-ia.md`](present-layer-ia.md) PL1–PL3; pairs with jukebox hang-out |
| **C05** | **Milestones Story feed** | What's unlocking across the ladder? | Server-wide tier-coloured chronology ticker | Online | Hub phase in [`milestones-hub-ia.md`](milestones-hub-ia.md) |
| **C08** | **Tournament story page** | What happened at this event? | Minimal narrative band: winner, biggest upset, perf rating, **who debuted** in their first official tournament here, moments cards, video — scale down for 4-player kitchen events | Amiga | Magazine spread on `tournament.php`; pairs with **C14** (realm-wide tournament metadata LBs) |
| **C09** | **Rivalry teaser on profile** | Who's *the* rivalry? | One card → H2H poster | Both | **Deferred** with C01 (Amiga profile pass); online placeholder exists |
| **C10** | **Online season in review** | How was my 2024? | Calendar year lens on profile — rank Jan vs Dec, games, streak, top rival | Online | Lightweight TT sibling; `player_period_*` tables |
| **C11** | **WC highlight reel (editorial)** | Show me the best stuff across WC history | Curated story through WC years — a few videos per year + short editorial copy celebrating players | Amiga | **Must be editorial**, not algorithmic boards |
| **C12** | **Country story page** | Who *are* the Italians? | Prose band: best WC, top player, default rival link | Amiga | **Parked** — interesting but authenticity / data-to-prose unclear |
| **C14** | **Tournament metadata leaderboard** | Which events had the most debuts / biggest fields / …? | **Tournament stats** sub-wing on **Tournaments hub** — sortable metadata boards beside the chronological searchable catalog (mirror **World Cups hub → Tournament stats**, not player/country stats) | Amiga | **Approved** (Jun 2026) — firm to-do; follow-on from **C08**; extend **`amiga_tournament_catalog_stats`** at finalize (debut count, etc.); player/country tournament grains stay in normal LBs — §6.4 |

### 5.4 Sparks — needs Dagh pass before build

| ID | Idea | Human question | Device | Realm | Notes |
|----|------|----------------|--------|-------|-------|
| **C16** | **KO2 universe map** | Where does Kick Off 2 live on the internet — and how does *this* site fit in? | **Visual map** — illustrated ecosystem with glowing nodes, regions, and curated creator anchors; optional accessible list below | **Both** | **Gestating** (Jul 2026) — map-first wow piece; §6.6 · [`present-layer-ia.md`](present-layer-ia.md) §6 |

### 5.2 Rejected (session) — do not re-pitch

| Idea | Reason |
|------|--------|
| **Global "compare two players" picker** | Opponents path is already obvious (player → Opponents → H2H); extra entry point adds confusion |
| **Milestone proximity ("3 games from X")** | Data types don't support cleanly; prefer player discovery fun |
| **Online H2H rank chart** | **Amiga only** by design — shipped on Amiga; not wanted online |

### 5.3 Shipped — do not re-pitch as new

| Feature | Where | Doc |
|---------|-------|-----|
| **H2H rank comparison chart** | `/amiga/player/opponents/h2h.php` | [`amiga-player-rank-chart-h2h-policy.md`](amiga-player-rank-chart-h2h-policy.md) |
| **Solo rank chart** | `/amiga/player/profile.php` | [`amiga-player-rank-chart-policy.md`](amiga-player-rank-chart-policy.md) |
| **With player stepper filter** | TT Event (`as_with=`), tournament (`id_with=` + `id_country=`), league (`start_with=`) + filter auto-snap | [`with-player-stepper-policy.md`](with-player-stepper-policy.md) §10 |
| **Highlights: biggest upsets (C15)** | `/amiga/games/highlights.php?board=biggest_upsets` | [`amiga_games_highlights_helpers.php`](../site/public_html/includes/amiga_games_highlights_helpers.php) · underdog-wins only — §6.5 |
| **On this day last year (C07)** | Status arc panel → Points day league | [`status_queries.php`](../site/public_html/includes/status_queries.php) · §6.3 |
| **Sticky TT ribbon pin (C02)** | Amiga time-travel ribbon | **Retired** Jul 2026 — was [`k2-amiga-time-travel-pin.js`](../site/public_html/js/k2-amiga-time-travel-pin.js) · §6.1 |
| **Chronology video glyph (C06)** | Tournaments catalog + WC chronology — dedicated Videos column before Players | [`amiga_tournament_videos_lib.php`](../site/public_html/includes/amiga_tournament_videos_lib.php) `amiga_tournament_video_column_cell()` · §6.2 |
| Milestones v0, Time travel, Jukebox, Video embed, Fight poster, Countries/Rivals | (see origin stories) | respective policy docs |

---

## 6. Design notes (approved sparks)

### 6.1 Optional sticky time-travel ribbon (C02) — retired Jul 2026

**Status:** **Shipped** Jun 2026 · **Retired from code** Jul 2026 (baseline phase). Pin JS, pushpin control, and `--pinned` CSS removed. **Future:** CD track [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) §2.4 · CD4 (not yet implemented).

**Problem:** TT chevrons at top of page; long profile/tournament scroll loses the navigator. Always-on sticky felt too intrusive.

**Historical solution (Jun 2026, code removed Jul 2026):** pushpin at end of ribbon bar; default unpinned; `k2-amiga-time-travel--pinned` + fixed bar when pinned. See git commit `3567037` for the removed implementation.

### 6.2 Chronology video glyph (C06) — shipped

**Status:** **Shipped** Jun 2026.

**Surfaces:** Tournaments hub catalog (`/amiga/tournaments.php`) + World Cups chronology (`amiga_world_cups_events_table.php`). Player tournament-history table held back (different mental model, denser rows).

**Glyph:** **Phosphor play-circle-fill** (amber-tinted solid circle + play triangle) in a **dedicated Videos column** immediately before Players — centered, narrow; **empty cell** when no footage. Scale-up + accent glow on hover when linked. Shows only when `amiga_tournament_has_videos($id)`. One click → the event's **Videos tab landing on `#tournament`** (zero-height anchor flush above the hero), via `amiga_tournament_videos_url()` + `amiga_tournament_href()` so `as=` / with-player carry. Column header blank; no tooltips on glyphs. Sortable (has footage first/last).

**Table layout:** Both surfaces use `k2-table-cell--center` on **Players** and **Games** (header + body). Tournaments catalog aligned to WC chronology in Jul 2026.

**Implementation:** `amiga_tournament_video_column_cell()` in `amiga_tournament_videos_lib.php`; `amiga_tournament_index_render_table()` + `amiga_world_cups_events_render_table()`; CSS scoped to `.k2-table-cell--video-glyph` in `theme.css`. Closes TV-4.

### 6.3 "One year ago today" (C07) — shipped

**Status:** **Shipped** Jun 2026.

**Copy:** **On this day last year →** under the Status lifetime arc sentence.

**Target:** Points day league for the same UTC calendar day one year before server now — `league.php?cup=points&period=day&start=YYYY-MM-DD#k2-league-period`.

**Placement:** Status arc panel — `k2-status-room__arc-link` directly below the players/games since line, one line of spacing apart (`margin-top: 1.45em`).

**Later (optional):** 2y / 3y rotation for variety.

### 6.4 Tournament metadata LB (C14) — approved

**Status:** **Approved** — build when scheduled (Dagh Jun 2026).

**Origin:** While sketching **C08** (light editorial on `tournament.php` — winner, biggest upset, perf rating, debuts), the follow-on question: *which tournaments had the most debuts?* That generalizes to **leaderboards of tournament metadata**.

**Placement:** **Tournaments hub** (`/amiga/tournaments.php`) is thin today (title + filter pills + chronological sortable table). Split like **World Cups hub**:

| Wing | Question | Notes |
|------|----------|-------|
| **Chronology** (existing) | What happened when? | Searchable catalog; filter pills (videos, perfect, …) |
| **Tournament stats** (new) | Which events stand out on metadata axes? | e.g. most debuts, largest field, most games — sortable boards linking back to `tournament.php` |

**Explicitly not here:** player stats and country stats at tournament grain belong in **Leaderboards** (and maybe a later Countries hub wing) — same split as World Cups (player/country stats live outside the tournament-stats wing).

**Data habit:** Stored truth at finalize — extend **`amiga_tournament_catalog_stats`** (or a sibling table) rather than hot-path scans; debut count = players whose first official tournament is this event.

**Pairs with:** **C08** per-event story band (micro) ↔ **C14** realm-wide metadata boards (macro).

### 6.5 Highlights: biggest upsets (C15) — shipped

**Status:** **Shipped** Jun 2026.

**Device:** Fifth board tab on Amiga **Games → Highlights** — games sorted by largest **single-game rating gain** for the winner, framed as upsets.

**Rule:** **Underdog wins only** — winner’s pre-game rating must be **strictly lower** than the loser’s (excludes early 2001–2002 flat-rating noise and favourite wins). Decisive games only (no draws).

**Scope:** Reuses highlights cluster (`AMIGA_GAMES_HIGHLIGHT_BOARDS` + shared table); WC scope filter inherited from sibling boards.

### 6.6 KO2 universe map (C16) — gestating

**Status:** **Gestating** (Jul 2026) — **map-first** product intent locked; visual grammar and build track TBD.

**Origin:** Site-completion / Misc brainstorm. Dagh (Jul 2026): not a link dump — a **real map** with **wow** factor: entice newcomers, re-engage veterans, give content creators an attractive umbrella to **carve out a visible place**. [`present-layer-ia.md`](present-layer-ia.md) · Misc **Scene** bucket.

**Human question:** *Where does Kick Off 2 live — and where do **I** belong in it?*

---

#### Product intent (locked)

| Audience | Job the map does |
|----------|------------------|
| **Newcomers** | Orientation without overwhelm — “here is the whole scene; here is where you start playing; here is why this site matters.” |
| **Oldcomers** | Re-engagement — rediscover forums, channels, and corners they forgot; feel the scene is **still alive**, not a dead spreadsheet. |
| **Content creators** | **Carve-out** — YouTube channels, streamers, sites, and community projects get **named nodes** on the map (curated), not buried in a footer link list. The umbrella should feel **worth being on**. |

**Wow bar:** This should be a page people **show friends** — comparable to box art story or jukebox in “stay awhile” mood, not a sitemap.

---

#### Device — visual map (primary)

**Primary surface:** an **illustrated universe map** on a dark canvas — neon-noir kin to [`design-direction.md`](design-direction.md) (deep bg, accent glow on nodes and paths, no pixel fonts for labels).

| Element | Intent |
|---------|--------|
| **Nodes** | Destinations — sites, forums, Discord, YouTube channels, wikis, key community projects |
| **Regions / territories** | Loose geography — e.g. **Play online** · **Amiga offline** · **Community & forums** · **Video & creators** · **History & archives** — not rigid borders, more like labelled space |
| **Paths / constellations** | Visual relationships (play → stats → video; offline ↔ online) — suggest journey, not just a grid |
| **You are here** | **This ratings site** is an explicit anchor — the stats hub in the middle of the ecosystem, not an afterthought |
| **Live pulse (optional v1.1)** | Subtle glow or pulse on “hot” nodes (e.g. Discord, live online scene) — ties to daily pulse idea on News |

**Accessibility / maintenance:** Map is hero; **structured list or table below** (same data) for screen readers, search, and dead-link audits — hybrid for a11y, not a replacement for the map.

**Not:** auto-scraped directory, wiki, comments, or infinite user-submitted pins without curation.

---

#### Creator carve-out (intent)

Creators and community projects are **first-class map citizens**, not a footnote category:

- Each creator node: **name · one-line blurb · link · optional icon/thumbnail treatment**
- **Curated inclusion** — Dagh/community nomination; map stays trustworthy
- Room to **add nodes over time** without redrawing the whole universe (new star in a region, or new satellite)
- Long-term: map becomes the **social proof** of a living scene (“look who is here”)

Open: nomination process, max nodes per region, whether inactive channels grey out vs drop off.

---

#### Seed inventory (starter set)

Canonical outbound URLs in [`join_page_links.php`](../site/public_html/includes/join_page_links.php): Discord, kickoff2.net, kickoff2.com, KOA forum, KO2CV YouTube, tutorials/playlists. Expand with wikis, gathering sites, notable channels — **map layout drives what makes the cut**, not “every link we know.”

---

#### Placement & discovery

| | |
|--|--|
| **URL** | Tier-3 Misc leaf — **`/misc/ko2-universe.php`** (cross-realm) |
| **Discover** | News “From the shelf” · Misc catalog · About · Play & Setup “wider scene” · optional Status hook |
| **Hub tab** | No — leaf page (NM2) |

**Jukebox test:** Strong yes — immersion + exploration.

---

#### Still open before build track

1. **Visual grammar** — constellation vs island archipelago vs metro-style vs stylised pitch/planet (one mood board pass).
2. **Interaction** — pan/zoom canvas vs fixed illustration with clickable hotspots vs scroll-reveal sections.
3. **Data model** — static PHP/JSON manifest of nodes vs DB (likely **manifest in repo** for v1).
4. **Illustration** — custom art / CSS-SVG / generative layout from node coordinates.
5. **Promotion** — **gestating** → **approved** when visual direction is chosen; then policy + slices per [`agent-track-playbook.md`](orchestration/agent-track-playbook.md).

---

## 7. Blueprint for future creative sessions

### 7.1 Doc maintenance rules

- Add rows to **§5 Idea ledger** with ID, status, and one-line Dagh feedback.
- Move shipped items to **§5.3** with link to policy doc.
- **Never** duplicate full specs here — link out when a track starts.
- After a creative chat with decisions: one **PROJECT_MEMORY** Recent log line pointing here (see [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A2).

### 7.2 Optional sections to add later

| Section | When |
|---------|------|
| **Priority stack** | When Dagh ranks C01–C15 for a month |
| **Sparks inbox** | Raw one-liners not yet discussed |
| **Rejected with date** | Prevent agent re-pitch loops |
| **Cross-realm parity table** | After major Amiga/online passes |

### 7.3 Prompt starters for agents

- "Read [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) §5 — explore **C0N** only; no code until policy locked."
- "Creative harvest: question harvest + device pass for **Amiga profile** only."
- "Check §5.3 before suggesting rank chart H2H — it's shipped Amiga-only."

### 7.4 Relationship to other docs

| Doc | Role |
|-----|------|
| `PROJECT_BRIEF.md` | North star taste |
| `PROJECT_MEMORY.md` | What actually shipped recently |
| Feature policy docs | Locked rules for a track |
| This file | **Pre-track** creativity + context |

---

## 8. Changelog

| When | Note |
|------|------|
| 2026-07-01 | **C16 gestating** — map-first wow intent: visual universe map, three audiences (newcomers / oldcomers / creators), creator carve-out nodes; atlas demoted to a11y fallback; §6.6 expanded. |
| 2026-07-01 | **C16 spark** — KO2 universe map (Misc Scene leaf; visual map and/or link atlas); §5.4 + §6.6; ties to [`present-layer-ia.md`](present-layer-ia.md). |
| 2026-07-01 | **C06 cleanup** — dev glyph picker removed; production-only `amiga_tournament_video_column_cell()` + scoped CSS; §6.2. |
| 2026-07-01 | **C06 table polish** — blank Videos header; empty cells without footage; no glyph tooltips; tournaments catalog Players/Games centered (WC parity); §6.2. |
| 2026-07-01 | **C06 column layout** — video glyph moved from inline Tournament cell to dedicated Videos column (before Players) on tournaments catalog + WC chronology; sortable; §6.2. |
| 2026-07-01 | **C06 shipped** — Chronology video glyph: Phosphor play-circle-fill → Videos tab at `#tournament`; `amiga_tournament_video_glyph()`; closes TV-4; player history table held back; §6.2. |
| 2026-06-30 | **C02 shipped** — optional TT ribbon pin (pushpin icon); sticky full control bar; `localStorage`; §6.1. |
| 2026-06-30 | **C07 shipped** — Status arc **On this day last year →** → Points day league one UTC year back; §6.3. |
| 2026-06-30 | **C15 shipped** — Amiga Highlights fifth board **Biggest upsets** (`board=biggest_upsets`); underdog-wins only (lower-rated winner); §6.5. |
| 2026-06-30 | **C14–C15 promoted to approved** — firm to-do (was spark); Tournaments hub tournament-stats wing + Highlights biggest upsets board. `PROJECT_MEMORY` Next updated. |
| 2026-06-30 | **C14–C15 sparks** — tournament metadata LB wing (debuts etc., WC hub pattern on Tournaments hub); Amiga Highlights **biggest upsets** board (rating gain). C08 notes: winner + debut in editorial band. §6.4–§6.5. |
| 2026-06-30 | **C13 extensions documented** — `id_country`, faceted counts, filter auto-snap; policy §5.8 + §10. |
| 2026-06-30 | **C13 with-player stepper shipped** — slices 0–3 complete; moved to §5.3. |
| 2026-06-30 | **C13 planning revision** — per-surface params (`as_with` / `id_with` / `start_with`); slice 0 T18 removal; [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md). |
| 2026-06-30 | **C13 with-player stepper** — policy locked [`with-player-stepper-policy.md`](with-player-stepper-policy.md); supersedes Amiga TT T18. |
| 2026-06-30 | Creative session wrap — §4.0 discoverability; `UPDATE_DOCS` · `AGENTS.md` · `agent-track-playbook` cross-refs. |
| 2026-06-30 | Initial ledger from creative chat — recipe, origin stories, C01–C12, rejects, sticky TT + glyph notes. Fixed stale "H2H rank not built" refs in rank-chart policy/plan. |
