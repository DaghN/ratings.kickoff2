# Player Opponents — Head-to-head poster & pair detail

**Status:** Poster **shipped** (Jun 2026) — glowing mirrored identity cards, W/D/L hero, lead meter, goals on bare stage. Pair-detail band still planned.  
**Authority:** Dagh’s latest message → this doc → [`player-opponents-hub.md`](player-opponents-hub.md) → [`design-direction.md`](design-direction.md).

**Related:** [`player-profile-feast.md`](player-profile-feast.md) (hero / surface rhythm) · [`website-data-contract.md`](website-data-contract.md) (`player_matchup_summary` SCH-008 / SCH-019).

---

## Page job (H2H tab)

When a pair is selected, the Head-to-head tab tells the **rivalry story** in layers:

| Layer | Job | Status |
|-------|-----|--------|
| **Pickers** | Choose opponent (search · by games · A–Z) | Shipped v1 |
| **Poster** | Pair identity + headline record — “fight card” summary | **Shipped locally (Jun 2026)** |
| **Pair detail** | Full W/D/L · Goals · DDs stats for *this opponent only* (same fields as the three Opponents sub-tabs, one row each) | Planned after poster; not v1 |
| **Charts** | Top opponents bar (context), cumulative H2H, rating comparison | Move from Profile (Phase 2) |
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
| **Goals tally** | `GF – GA` | Secondary to W/D/L |
| **Games** | Count | Sample size (“27 games”) |
| **Who leads** | One short line | e.g. “Gianni leads 12–7–3” or “Level on wins” |

### Out of scope (poster v1)

- First / last game date or score
- Country or flags
- DD/CS, max/min goals, ratios (→ **pair detail** band below)
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
- **Surface:** Bare stage background — cards, counts, meter and goals float with no outer panel border (card glow can spill). Prompt/empty states keep a small bordered plate.
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
  - **W/D/L hero** — large blue Won / muted Drew / red Lost counts with full words (not W·D·L abbreviations).
  - **Lead meter** — proportional blue / muted / red bar under the counts.
  - **Goals** — centred `GF – GA` line with small label.
  - **0 games** — both cards visible; “No rated games yet” below.
- Reuses `k2_route('player-profile')`, `k2_h()`, `k2_fmt_int()`, `k2_db_is_null()`, and the same rank query idiom as the hero.
- W/D/L colours use `--k2-table-positive` / `--k2-table-negative` (via `.blue` / `.red` on counts).

---

## Pair detail band (planned — not poster v1)

Dagh intent: on H2H, show **everything** the three Opponents sub-tabs show (W/D/L · Goals · DDs), but **only the row for the selected opponent** — the full “data package” for the rivalry.

### W/D/L row fields (from `player_opponents_tables.php`)

Games · Wins · Draws · Losses · Win Ratio · Draw Ratio · Loss Ratio

### Goals row fields

Games · GF · GA · GF/g · GA/g · Ratio · Max GF · Max GA · Max win · Max loss · Max sum · Draw · Min GF · Min GA · Min sum

### DDs row fields

Games · Double Digits · Clean Sheets · DD Ratio · CS Ratio · DD conceded · CS conceded · DD C Ratio · CS C Ratio

### Data

Single directed row from `player_matchup_summary` (SCH-019 columns when present). Ratios computed at read time — same as table renderers.

### UX direction (TBD)

- Not one wide 30-column table
- Likely **three grouped bands** (W/D/L · Goals · DDs) mirroring sub-tab semantics
- Lives **below poster**, above charts

---

## Scroll order (target)

1. Wing hero (subject) — existing page chrome  
2. Opponents sub-nav  
3. H2H pickers  
4. **Poster**  
5. **Pair detail** (later)  
6. Matchup charts (from Profile)  
7. Optional games link  

---

## Session log

| Date | Note |
|------|------|
| Jun 2026 | Poster contract locked: rank + rating + W/D/L centre; no country; no first/last; appropriately sized (not “compact” mandate); pair detail band scoped as follow-on. |
| Jun 2026 | **Poster v2 shipped.** Promoted lab v2 card design to production H2H tab: mirrored glowing identity cards, bare stage, W/D/L + meter + goals; removed lab v1/v2 sandboxes (lab2 URL redirects). CSS in `player-opponents-h2h-poster.css`. |
| Jun 2026 | **Poster shipped (Opus redesign, superseded).** Diagonal arena poster — replaced by card design above. |
