# Player Opponents — Head-to-head poster & pair detail

**Status:** Poster + **pair-detail race tables shipped** (Jun 2026). Charts still planned.  
**Authority:** Dagh’s latest message → this doc → [`player-opponents-hub.md`](player-opponents-hub.md) → [`design-direction.md`](design-direction.md).

**Related:** [`player-profile-feast.md`](player-profile-feast.md) (hero / surface rhythm) · [`website-data-contract.md`](website-data-contract.md) (`player_matchup_summary` SCH-008 / SCH-019).

---

## Page job (H2H tab)

When a pair is selected, the Head-to-head tab tells the **rivalry story** in layers:

| Layer | Job | Status |
|-------|-----|--------|
| **Pickers** | Choose opponent (search · by games · A–Z) | Shipped v1 |
| **Poster** | Pair identity + headline record — “fight card” summary | **Shipped locally (Jun 2026)** |
| **Pair detail** | Symmetric player-vs-player stat races (Results · Goals · DDs) for *this opponent only* | **Shipped Jun 2026** |
| **Charts** | Top opponents bar (context), cumulative H2H, rating comparison | Move from Profile (Phase 2) |
| **Moments** | Fixed 3×3 pair trophy grid (nine slots, dim until active; duplicates allowed) | **Shipped Jun 2026** |
| **Games link** | Optional deep link to Games tab filtered by opponent | Optional |

**Profile rhyme:** Profile opens with a **single-player hero** + curated bands. H2H opens with a **pair poster** + pair-specific analyst depth. The wing hero at page top stays **subject-only**; the poster is the relational introduction.

---

## Poster contract (v1)

### Purpose

Replace the plain `Gianni vs Fabio` headline with a **versus poster**: two players in opposition, record unmistakable at a glance. Size the panel to fit its information well — neither cramped nor wastefully tall. No country (online). No first/last game dates in v1.

### In scope

| Element | Both players | Notes |
|---------|--------------|--------|
| **Avatar** | Yes | Reuse `k2-player-hero__avatar` idiom (initial + accent ring) |
| **Name** | Yes | Link to player profile (locked policy) |
| **Rank** | Yes | Ladder rank when `Display = 1`; else em dash |
| **Rating** | Yes | Current rating when displayed |
| **Country / flag** | **No** | Online only — omit entirely |
| **Centre record** | W · D · L | **Hero stat** of the poster — large, clear, colour-coded |

### Out of scope (poster v1)

- First / last game date or score
- Country or flags
- DD/CS, max/min goals, ratios (→ **pair detail** below)
- Charts (separate panels below poster)
- Duplicate full wing-hero strip for subject (page hero already shows subject)

### W/D/L presentation (required)

- Three **large tabular numbers** with labels **W · D · L**
- Colour: reuse games-table semantics — **wins blue**, **losses red**, **draws muted/neutral** (see `theme.css` `.blue` / `.red` on player games)
- Colour on **numbers** or chips — not full neon panels
- Must read correctly for colour-blind users: labels always visible, not colour-only

### Layout principles

- **Height:** **Appropriately sized** for the content — let typography and spacing breathe; avoid cramming and avoid empty padding for its own sake. Not a fixed “must be short” rule; judge by feel on a real pair with a typical name length.
- **Composition:** Split card — player left · centre record · player right; subtle “home corner” for subject (left) optional
- **Surface:** Bare stage background — cards, counts and meter float with no outer panel border (card glow can spill). Prompt/empty states keep a small bordered plate.
- **Mobile:** Stack or scale; centre record stays legible
- **Empty states:** No pair selected → prompt (current). Pair with 0 games → both players visible, centre “No rated games”

### Data (read path)

| Field | Source |
|-------|--------|
| Subject / opponent identity, rating, rank, display | `playertable` + existing hero rank logic |
| W/D/L, goals, games | `player_matchup_summary` directed row `player_id` → `opponent_id` (live fallback if table missing) |

No new schema for poster v1.

### Implementation (as built — Jun 2026)

- Render: `k2_h2h_poster_card_html()` + `player_opponents_render_h2h_poster()` in `includes/player_opponents_h2h.php`; called from `player_opponents_render_h2h_panel()` inside `k2-player-opponents-h2h__stage`.
- CSS: `stylesheets/player-opponents-h2h-poster.css` (namespace `k2-h2h2-*`); linked from `player/opponents/h2h.php`.
- Composition:
  - **Mirrored identity cards** — avatar · name · rank · rating in glowing hero-style cards; opponent card mirrored so avatars face the `vs`. Whole card links to player profile (garden-style hover lift).
  - **W/D/L hero** — subject **Wins** (blue) · **Draws** (centre) · opponent **Wins** (red, same count as subject losses); each side reads from that fighter’s perspective.
  - **Lead meter** — proportional blue / muted / red bar under the counts.
  - **Goals removed from poster** — totals and goal depth live in pair-detail race tables only.
  - **0 games** — both cards visible; “No rated games yet” below.
- Reuses `k2_route('player-profile')`, `k2_h()`, `k2_fmt_int()`, `k2_db_is_null()`, and the same rank query idiom as the hero.
- W/D/L colours: subject **chrome-locked** (`--k2-pure-chrome` via `.k2-player-opponents-h2h`); opponent `--k2-table-negative` (`.red`). Wing hero above keeps picked tint — rivalry band only.

---

## Pair moments grid (shipped Jun 2026; scorecard redesign)

Fixed **3×3 deck** of **scorecard cards** below the race table. All nine slots always visible; inactive slots are dim ghosts. Active cards show: **kicker** (moment type) · **true scoreline** · **date** (whole card links to the game). **Double presence allowed** — same game may fill several cells.

### Slot order (kickers)

| | | |
|---|---|---|
| First game | Latest game | Goal feast |
| {Subject}'s best haul | {Opponent}'s best haul | Highest draw |
| {Subject}'s biggest win | {Opponent}'s biggest win | Tightest game |

### Score identity (locked)

- **Never flip a score.** The scoreline is the stored `NameA GoalsA – GoalsB NameB`; a 0–17 stays 0–17 with the correct names. Names shown in **full**, both sides.
- **Rivalry colours, not hero angle.** Winner **name** (own chrome/red) + **goals**, **dash**, and **date** use the winner's colour. Beaten **name + goals** are heavily dimmed grey. **Draw:** names, goals, dash, and date all holo (`--k2-pure-holo`). Card border glow follows outcome accent.

### Activation

| Slot | Active when |
|------|-------------|
| First / latest / goal feast / tightest / best haul ×2 | ≥1 rated game in pair |
| Highest draw | ≥1 draw |
| {Subject}'s biggest win | Subject has ≥1 win vs opponent |
| {Opponent}'s biggest win | Opponent has ≥1 win vs subject |

Ties on the same extreme → **first chronological** game (`Date ASC`, `id ASC`).

### Data

Read-time: one indexed `ratedresults` pair scan — `player_opponents_h2h_pair_games_rows()` in `includes/player_opponents_h2h_moments.php`. Keeps true A/B orientation; subject-relative metrics only pick which game fills each slot. Stored `*_game_id` per slot later (no schema in v1).

### Implementation

- Load + render: `player_opponents_h2h_moments.php`; CSS `player-opponents-h2h-moments.css` (self-contained `k2-h2h2-mcard` scorecards — no `player-feast.css` / emoji).
- **Outcome accent** per card (`--k2-mc-accent`): the game's **winner owns the card** — subject win = blue, rival win = red, **draw = holo** (`--k2-pure-holo`). Drives border glow (milestone garden `.k2-ms-card.is-unlocked` pattern) and kicker tint. **Hover** — profile-moment style: border lights to full accent + outer glow; extra `box-shadow` ring simulates thicker border without layout jump. Inner scoreline text glow unchanged.

---

## Pair detail — stat races (shipped Jun 2026)

Below the poster, a **single centred race table** (no band headers, no name repeat, no games line). Subject blue left · label centre · opponent red right. Leader tint only — no mini bars.

### Rows (v1 slim)

Goals scored · Goals per game · Most scored · Biggest winning margin · Least conceded (lower wins) · Double digits · Clean sheets

**Deferred:** wins/draws (on poster), goal ratio, highest/lowest-scoring game, highest-scoring draw, DD/CS rates — moments or later slices.

### Framing

- **Symmetric positives** — e.g. your goals scored vs their goals scored (`goals_for` vs `goals_against` on the directed row). Conceded columns are the opponent’s positive stat.
- **Leader tint** — winning value wrapped in `.blue` (subject) or `.red` (opponent); **ties** — both values in their own colour (chrome left, red right).

### Data

Single directed row from `player_matchup_summary` (SCH-019 when present). Live single-pair aggregation fallback when summary or extension columns missing — `player_opponents_h2h_pair_detail_load()` in `includes/player_opponents_h2h.php`.

### Implementation

- Render: `player_opponents_render_h2h_pair_detail()` — called from `player_opponents_render_h2h_panel()` after poster when `games &gt; 0`.
- CSS: `stylesheets/player-opponents-h2h-poster.css` (namespace `k2-h2h2-detail`, `k2-h2h2-race`).

---

## Pair detail band (archive note)

Earlier plan was three wide analyst bands mirroring sub-tab columns. **Superseded** by symmetric race tables above (Jun 2026).

### W/D/L row fields (from `player_opponents_tables.php`) — reference only

Games · Wins · Draws · Losses · Win Ratio · Draw Ratio · Loss Ratio

### Goals row fields — reference only

Games · GF · GA · GF/g · GA/g · Ratio · Max GF · Max GA · Max win · Max loss · Max sum · Draw · Min GF · Min GA · Min sum

### DDs row fields — reference only

Games · Double Digits · Clean Sheets · DD Ratio · CS Ratio · DD conceded · CS conceded · DD C Ratio · CS C Ratio

### Data

Single directed row from `player_matchup_summary` (SCH-019 columns when present). Ratios computed at read time — same as table renderers.

### UX direction (TBD — superseded)

- Not one wide 30-column table
- Likely **three grouped bands** (W/D/L · Goals · DDs) mirroring sub-tab semantics
- Lives **below poster**, above charts

---

## Scroll order (target)

1. Wing hero (subject) — existing page chrome  
2. Opponents sub-nav  
3. H2H pickers  
4. **Poster**  
5. **Pair detail** (stat races)  
6. **Moments** (3×3 grid)  
7. Matchup charts (from Profile)  
7. Optional games link  

---

## Session log

| Date | Note |
|------|------|
| Jun 2026 | Poster contract locked: rank + rating + W/D/L centre; no country; no first/last; appropriately sized (not “compact” mandate); pair detail band scoped as follow-on. |
| Jun 2026 | **H2H moments grid shipped.** 3×3 fixed slots, dim inactive, read-time pair scan, duplicate games allowed — `player_opponents_h2h_moments.php`. |
| Jun 2026 | **Pair detail shipped.** Symmetric stat races below poster (Results · Goals · DDs); goals line removed from poster; leader tint, no mini bars, no win-rate rows — `player_opponents_render_h2h_pair_detail()` + `k2-h2h2-detail` CSS. |
| Jun 2026 | **Poster v2 shipped.** Promoted lab v2 card design to production H2H tab: mirrored glowing identity cards, bare stage, W/D/L + meter; removed lab v1/v2 sandboxes (lab2 URL redirects). CSS in `player-opponents-h2h-poster.css`. |
| Jun 2026 | **Poster shipped (Opus redesign, superseded).** Diagonal arena poster — replaced by card design above. |
