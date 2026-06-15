# Profile build playbook — agent execution guide

**Status:** Jun 2026. **Use this doc when implementing** Profile tab content.

**Production:** `site/public_html/player/profile.php` — edit only when merging a winner.

**Multi-agent lab (archived):** Lab PHP may remain for comparison; handoff in [`archive/profile-lab-agent-handoff.md`](archive/profile-lab-agent-handoff.md).

**Authority chain:** Dagh’s latest message → this playbook → [`player-profile-feast.md`](player-profile-feast.md) (shipped layout + v1 summary) → v1 detail in [`archive/profile-content-candidates.md`](archive/profile-content-candidates.md).

---

## 1. Agent bootstrap (read before coding)

| Order | Doc / file | Why |
|-------|------------|-----|
| 1 | This playbook | Placement, recipes, waves, acceptance |
| 2 | [`archive/profile-content-candidates.md`](archive/profile-content-candidates.md) — **Profile content v1** | What to build / reject / defer |
| 3 | `player-profile-feast.md` — narrative model + surface rhythm | Story zones, panel vs open bg |
| 4 | `includes/player_feast_blocks.php` + `player_feast_load.php` | Patterns to extend, not replace |
| 5 | `docs/website-data-contract.md` | Stored tables for new reads |
| 6 | `docs/design-direction.md` | Tokens, tone, no pixel fonts on data |

**Optional:** `docs/archive/profile-redesign-framing.md` for tone examples (Chronicle thesis).

**Do not:** Rebuild the whole feast in one PR · add encyclopedia tables · move full milestone garden or league history onto Profile · restore May audit **DROP** rows · add new chart types (G05–G09 rejected).

---

## 2. North star (why the first pass worked)

Profile answers, in order of **meaning** (not necessarily current DOM order):

1. **Who** is this on the ladder? → Hero (Zone A)
2. **Are they still around?** → Presence pulse
3. **What kind of player?** → Career character
4. **What has the community noticed?** → Milestones + league recognition
5. **What do we remember?** → Personal bests + Moments
6. **What did habit look like?** → Heatmaps
7. **How did rating and rivals play out?** → Charts (opt-in depth)

**Tone:** Neon noir statistics — confident, celebratory, not cruel. Participation and volume are **prestige**. Losses exist on other tabs; Profile does not open with shame. Humour only where v1 explicitly allows (e.g. M11 Fisher-asleep gag — design pass first).

**Fold rule:** The **hero** owns CORE numbers (rank, rating, peak, games, milestone count). Do not add competing summary lines above the scroll fold without Dagh approval (A04 is **consider**, not default).

---

## 3. Placement charter (target scroll story)

Implement v1 **into bands**. Bands may map to one `pm3d-section` or a subsection inside it. **Reorder DOM** when a slice explicitly says “move band” — do not sprinkle new modules in catalog ID order.

### Zone A — Identity (fixed)

| Item | Shipped |
|------|---------|
| Hero + feast pills | Yes — do not duplicate CORE in tables |

### Zone B — Celebrate (five bands)

| Band | Job | v1 content | Target surface |
|------|-----|------------|----------------|
| **B1 — Pulse** | Still here? | B01–B03 (shipped) · **B06** win streak · **B07/B08** play streak · optional **B09** recent matches (consider) | Light tile / prose — **lab: free layout** (see §3.1) |
| **B2 — Character** | Who is he? | C01–C05 (shipped; **rethink ranks**) · **C12** victims line · **P02** best-year ticker · **P05** distinct days played | Same — **not** locked to duo tables |
| **B3 — Recognition** | Community marks | **MS01** latest unlock card · **MS02** holo/amber count · **MS04** unlocks last 12 mo · **MS03** link (hero has count) · **MS08** league milestone card · **L01** latest medal · **L04** league wins · **L02/L07/L08** career medals + honours link | **One section** e.g. “Honours” — combined strip, not three equal cards |
| **B4 — Memory** | Specific events | P01 (shipped) · M01–M02 (shipped) · **M03** max rated victim · **M08** favourite victim · **M10/M11** (consider, tone) | **Personal bests** then **Moments** mosaic |
| **B5 — Texture** | Habit over years | H01–H03 (shipped) | Open background heatmaps — **keep** |

### Zone C — Understand

| Band | Job | v1 content |
|------|-----|------------|
| **C1 — Rivalry intro** | Main opponent context | **M09** featured W–D–L line before charts |
| **C2 — Charts** | Analyst depth | G01–G04 (shipped) — no new chart types |
| **C3 — Action** | Rare matchup | Opponent search (shipped) · **M12** Games tab with opponent filter |

### Target vertical order (v1 build goal)

Use this as the **story spine** when reordering `player/profile.php` render calls:

```
Hero → pills
B1  Presence (+ pulse lines / optional B09)
B2  Career (+ C12, P02, P05)
B3  Honours (milestone + league snippets)
B5  Played days → Played weeks
B4  Personal bests → Moments (+ M03, M08)
C1  Matchups: M09 line → charts → search (+ M12 links in rivalry/moments)
```

**Current shipped order** (May–Jun 2026) places heatmaps **before** Personal bests/Moments. v1 build **may** move B4 above B5 to match Chronicle-first story, **or** keep heatmaps early — Dagh preferred activity texture early; confirm in slice prompt if moving.

### 3.1 B1 / B2 — Presence & Career (content vs presentation)

**Two questions, one contract:**

| Band | Question | v1 data (must appear somewhere in B1/B2) |
|------|----------|---------------------------------------------|
| **B1** | Is he still around? | Last seen, last game, games this month/year, win streak, play streak |
| **B2** | What kind of player? | Games, wins, goals, DDs, opponents (+ ranks if useful), victims line, best-year ticker, distinct days |

**Production today:** `player_feast_render_presence_career_duo()` — two bordered panels, HTML stat tables (`pm3efg-duo`). That is the **shipped reference**, not a layout law.

**Lab builds** (`individual1-profile-lab{N}.php`): **rethink presentation** — split or merge bands, tiles/chips/tickers instead of tables, open background, drop rank column, fewer rows with higher craft. Same facts; no encyclopedia.

**Production merge:** only after Dagh picks a lab winner; until then do not assume duo tables survive.

---

## 4. Module recipes (copy these patterns)

Read the **reference implementation** before inventing markup.

### 4.1 Story card (`pm3-moment`)

**Use for:** Trophy games, M03 max rated victim, MS01 latest milestone (card variant), MS08, L06 (if built), optional M10/M11.

**Reference:** `player_feast_render_moments()` in `player_feast_blocks.php`.

| Slot | Rule |
|------|------|
| Glyph | One emoji or tier dot — `aria-hidden` on decorative |
| Tag | Short category (Streak, Margin, Milestone tier name) |
| Label | Human title |
| Score | Primary fact — link to `game.php?id=` when game-backed |
| Meta | Outcome · vs opponent profile link · date — one line |

**Quality bar:** One emotional beat per card. No tables. Max **~6** visible cards in Moments grid; honour empty state (X01).

**M03 gate:** Show max-rated-victim card especially for **non-elite** players (e.g. current rank below cutoff — exact threshold in slice prompt or `player_feast_helpers.php` constant).

### 4.2 Streak / stat tile (`pm3efg-stat-table`) — production reference

**Production use:** Presence rows, Career rows (C01–C05) in the shipped duo.

**Reference:** `player_feast_render_presence_career_duo()`, `player_feast_render_career_stats_table()`.

**Lab:** Prefer **§4.3 prose/ticker** or **§4.1 card/tile** patterns when they tell the story better. Tables are allowed but not the default target.

| Slot | Rule (if using tables) |
|------|------------------------|
| Label | `th scope="row"` |
| Value | Muted or primary number |
| Rank | Optional `(#n)` — **rethink or omit** in lab |

**Do not** add ten new rows — prefer **one line** or ticker for B06/B07/C12/P05.

### 4.2b B1 / B2 lab patterns (encouraged)

Examples without duo tables:

- **Pulse strip:** “Last game May 17 · 32 this month · 12-day play streak”  
- **Character line:** “5,940 games · 142 opponents · favourite victim Lee (87 wins)”  
- **Split headings:** separate `pm3d-section` for Presence and Career with 2–3 tiles each  
- **Highlight one stat:** one large number (distinct days played) + supporting lines  

### 4.3 Prose line / ticker

**Use for:** P02 best year (“Won 135 games in 2023!”), M08 favourite victim, M09 rivalry line, B06/B07/B08 streak narrative, C12 victims.

| Rule | |
|------|--|
| One sentence, one primary number | |
| `pm3-muted` or `pm3d-chart__meta` scale — not hero size | |
| Links use `linkStar()` / existing profile link styles | |

**B07/B08 product rule:** One play-streak story per page load — **current run** OR **historical best with date** (“played 37 days in a row in …”); day **or** week; optional **rotate** on load (document in PHP comment).

### 4.4 Honours strip (new — compose from recipes)

**Use for:** B3 Recognition band (MS01, MS02, MS04, L01, L04, L02 aggregate).

| Element | Treatment |
|---------|-----------|
| Latest milestone | Small card or highlighted line → `milestone.php?key=` |
| Holo count | MS02 — if zero holo, show **amber** accomplishment count instead |
| Unlocks last 12 months | MS04 — number only, newcomer-friendly |
| Latest league medal | L01 — medal + period label, restrained **bling** (accent border, not new gradients) |
| League wins | L04 — career community presence |
| Career medals | L02 — gold/silver/bronze; link **League honours** wing (L08) |

**Do not** embed full garden or period history tables.

### 4.5 Personal bests row (`pm3-busiest`)

**Use for:** P01 — already shipped.

**Reference:** `player_feast_render_peak_activity()`.

**P02** sits adjacent (ticker), not inside the three-column busiest list unless merged copy-approved.

### 4.6 Open heatmap (`pm3-cal--hero`)

**Use for:** Played days/weeks only.

**Reference:** `player_feast_render_played_days/weeks`, `player-calendar.js`.

| Rule | |
|------|--|
| No `k2-chart-panel` wrapper | |
| Year segment picker for days — ascending years left→right; first + current year = full Jan–Dec grid | |
| P05 distinct days may appear in B2 **and** inform heatmap status line — not a second grid |

### 4.7 Chart panel (`k2-chart-panel` + `k2-chart-frame`)

**Use for:** G01–G04 only.

**Reference:** `player_feast_render_charts()`, Activity `activity.php` panels.

| Rule | |
|------|--|
| M09 rivalry line **above** matchup subsection | |
| Top opponents chart stays **tall**; plain Chart.js for horizontal bar (see graph restoration notes in MEMORY) | |
| No winrate-vs-Elo chart | |

### 4.8 Deep link chip

**Use for:** M12, X05, X06.

| Target | Pattern |
|--------|---------|
| Games vs opponent | `player/games.php` + opponent filter param (implement in Games slice) |
| Milestone garden | `player/milestones.php?id=` |
| League honours | League wing URL with player context (see `league_standings.php` / honours panel) |
| Streaks LB | `leaderboards/streaks.php` when streak is highlight-worthy |

---

## 5. Display rules (X04)

Apply on every new module:

| Condition | Behaviour |
|-----------|-----------|
| `NumberGames < 1` | Hero milestones hidden; honours strip minimal; optimistic X01 copy |
| `Display ≠ 1` | Rating/peak/ranks as —; thin honours; no shame copy |
| No league awards ever | Hide L01/L02/L04 block; keep milestones if any |
| No milestones unlocked | Hide MS01/MS02/MS04; hero count may show `0/112` |
| M03 rank gate | Show card when gate passes (non-elite celebration) |
| B06 | Show only if `WinningStreak >= 3` (or similar — codify in helper) |
| B07/B08 | Show one streak narrative; hide duplicate day+week unless rotate |
| Consider items (A04, B09, M10, M11) | Behind flag or separate slice after Dagh approves |

**Rotation (optional):** B07 day vs B08 week on alternate page loads — use deterministic `player_id % 2` or session-free daily hash so caches stay friendly.

---

## 6. Data loading conventions

| Rule | |
|------|--|
| Prefer **stored** tables per [`website-data-contract.md`](website-data-contract.md) | |
| Extend `player_feast_load_pm()` for Profile-wide fields; avoid N+1 `ratedresults` scans | |
| Milestones: `player_milestones_helpers.php` (`k2_milestone_player_counts`, catalog, tier) | |
| League: `league_standings.php` (`k2_league_player_slice_totals`, awards) | |
| Play streaks: `player_play_streaks` via `player_play_streaks.php` read helpers | |
| Matchups: `player_matchup_summary` or existing top-opponents API | |
| **P05** distinct days: `COUNT(*)` on `player_period_games` where `period_type='day'` — consider caching on `playertable` later for HoF |

Set `time_zone = '+00:00'` on connection (already on `player/profile.php`).

---

## 7. Implementation waves (suggested PR slices)

Each slice: **one band**, **2–6 v1 IDs**, extend blocks, update `player-profile-feast.md` scroll note if DOM order changes.

| Wave | Band | IDs | Notes |
|------|------|-----|-------|
| **1** | B3 Recognition | MS01, MS02, MS04, L01, L04, L02/L08, MS08 | New `player_feast_render_honours()`; load in `player_feast_load_pm` |
| **2** | B1 Pulse + C1 intro | B06, B07/B08, M09 | Extend presence duo; rivalry line above charts |
| **3** | B2 Character | C12, P02, P05, career rank styling | P05 may need contract note if HoF later |
| **4** | B4 Memory | M03, M08, X01 | Moments grid; rank gate helper |
| **5** | C3 Action | M12 | Games tab filter + link from M09/M08 |
| **6** | Consider | A04, B09, M10, M11, C14, L06 | Only after Dagh approves in prompt |
| **7** | Cross-site | P05 HoF, H05 DD heatmap | Data / schema slices — not Profile-only |

**Polish slice (last):** Copy pass, bling tuning, X04 edge cases, mobile scroll.

---

## 8. Acceptance checks (every slice)

Answer **yes** before marking done:

1. **10-second test:** Would a rival grasp who this is and that they matter on the ladder without reading charts?
2. **One question per module:** Can you state the single human question each new block answers?
3. **No hero competition:** New lines don’t repeat rank/rating/games louder than hero?
4. **Tab respect:** Not rebuilding Games / W-D-L / Goals / DDs / garden tables?
5. **Test trio:** `player/profile.php?id=237` (veteran) · one mid-volume active player · one sparse / &lt;20 games player
6. **Sparse = optimistic** (X01), not broken layout
7. **Docs:** Same-turn Part A — MEMORY line + feast spec / this playbook if behaviour changed

---

## 9. Anti-patterns (instant reject)

- Full-width table of 20+ playertable rows  
- Second rating summary competing with hero  
- Nadir, recent avg rating, rating ascent/descent rows  
- New Chart.js panels without v1 approval  
- Panel-wrapping heatmaps  
- Milestone tier histogram (MS05) or three-latest list (MS07)  
- Shame-first nemesis framing  
- Live SQL over full `ratedresults` for aggregates on page load  
- Refactoring shipped blocks unrelated to slice scope  

---

## 10. Prompt template for Dagh → agent

Copy and fill:

```
Profile slice — [Wave N: band name]

Read: docs/profile-build-playbook.md + archive/profile-content-candidates.md (v1) + player-profile-feast.md

Build: [IDs]
Band: [B1/B2/B3/B4/B5/C1/C2/C3]
Reuse: [e.g. pm3-moment recipe, extend player_feast_load_pm]
DOM: [keep order / move B4 above B5 per playbook §3]
Do not: [anything extra]

Done when: acceptance §8 + test id=237 + sparse player
```

---

## 11. v1 ID quick index

| IDs | Band |
|-----|------|
| B06, B07, B08, (B09) | B1 |
| C01–C05, C12, P02, P05 | B2 |
| MS01, MS02, MS03, MS04, MS08, L01, L02, L04, L07, L08 | B3 |
| M01–M03, M08, (M10, M11), P01 | B4 |
| H01–H03 | B5 |
| M09 | C1 |
| G01–G04, search | C2–C3 |
| M12, X01, X04, X05, X06 | cross-cutting |

**Reject / defer / consider:** see `archive/profile-content-candidates.md` v1 §.

---

*Playbook Jun 2026 — matches Dagh curation and first-pass Chronicle quality bar.*
