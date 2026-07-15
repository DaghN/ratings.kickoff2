# Amiga player chronologies — policy

**Status:** **Shipped v1** (Jul 2026) — **Opponents** + **Victims** + **DD/CS/MGC/BL Victims** + **MGS/BW Culprits** pointer kinds. Remaining culprit kinds **Planned**.

**Parent:** [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) (inventory vs comparison — profile mosaic entry) · [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`navigation-model.md`](navigation-model.md) NM2–NM4

**Related:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §3 · [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) · [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) · [`k2-table-quiet-date-column-policy.md`](k2-table-quiet-date-column-policy.md) · [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) · [`k2-mobile-smartphone-policy.md`](k2-mobile-smartphone-policy.md) · milestone detail UX ([`milestones-product-spec.md`](milestones-product-spec.md) — spotlight + Made it \| Graphs pattern)

**Code:** `includes/amiga_player_chronologies_{lib,render,page}.php` · `amiga/player/chronologies/{kind}/` · routes in `k2_amiga_routes.php`

---

## 1. Executive summary

**Player chronologies** answer *“what is this career count made of?”* — a **player-scoped unlock inventory**, not a leaderboard comparison.

| Surface | Question |
|---------|----------|
| **Made it** | Who / what did this player encounter **for the first time**, in chronological unlock order? |
| **Graphs** (optional per kind) | How did that inventory grow over calendar time? |

Each **kind** (Opponents, Victims, DD Victims, …) is a separate folder under `/amiga/player/chronologies/{kind}/` with the same page chrome and segment nav (**Made it** \| **Graphs**).

**Not the same as:**

| Surface | Difference |
|---------|------------|
| **Opponents wing** (`/amiga/player/opponents/*`) | **Comparison** — H2H poster, W/D/L ledger, pair charts vs one selected opponent. Profile mosaic **Opponents count** links to **chronology**, not this wing. |
| **Hub leaderboards** | **Comparison** — where does this player rank? |
| **Milestone detail** (`milestone.php`) | Server-wide achievers for one milestone key — same *spotlight + Made it \| Graphs* UX family, different data scope. |
| **Online / Amiga milestone chronology** (`player/milestones/chronology.php`) | Player’s **milestone unlock timeline** — not opponent/victim inventories. |

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **PC1** | **Inventory mode** | Chronology pages serve **inventory** only ([`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) §2). No default links to LB row anchors from table cells. |
| **PC2** | **Folder per kind** | `/amiga/player/chronologies/{kind}/` — plural **`chronologies`**. Each segment = **separate PHP file** (`made-it.php`, `graphs.php`). `{kind}/index.php` → 302 default segment. **No** `?view=` / `?tab=` / `?wing=` for navigation ([`k2-page-structure-checklist.md`](k2-page-structure-checklist.md)). |
| **PC3** | **Entry v1** | **Profile mosaic only** — no new player wing pill; player nav shows with **no tab active** (`$k2AmigaPlayerTabActive = ''`). Wing nav remains visible (Profile · Opponents · Tournaments · Games · Videos). |
| **PC4** | **Page stack** | Realm hub bar → TT ribbon → player hero → **player wing nav** → **spotlight card** → **segment nav** (Made it \| Graphs, `data-k2-carry-scroll`) → table or charts. Same milestone-detail *family* as `milestone.php` ([`player_milestones_helpers.php`](../site/public_html/includes/player_milestones_helpers.php) spotlight). |
| **PC5** | **Spotlight** | Anchor `#k2-amiga-chronology-spotlight` **above** card (`AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT`). Mosaic entry uses `amiga_player_chronology_opponents_entry_href()` = route + hash. Page sets `$k2ScrollTargetId` to same fragment. Title ink = **`k2-link-star`**; card glow `--k2-ms-accent: var(--k2-link-star)` ([`player-feast-sections.css`](../site/public_html/stylesheets/player-feast-sections.css)). |
| **PC6** | **Time travel** | **`as=`** on all wired reads and internal links (`amiga_snapshot_context_from_request`, `amiga_url_with_context()` / `k2_amiga_route()`). Membership + first meeting ≤ cutoff. |
| **PC7** | **Data v1** | **Read-time SQL spike** — no stored unlock table yet. First rated meeting per entity via window `ROW_NUMBER()` over tournament chronology. Revisit stored truth only when a kind needs hot-path performance or post-game maintenance ([`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 habit). |
| **PC8** | **Made it table UX** | Full **k2-table** stack on Made it: sortable, calm-stats, cloak on first paint (`$k2RankedCloak`), anchor column, quiet date on default sort, **SSR row order = default sort** + `data-k2-skip-initial-sort="1"` ([`k2-table-quiet-date-column-policy.md`](k2-table-quiet-date-column-policy.md)). Dense table on phone = intentional ([`k2-mobile-smartphone-policy.md`](k2-mobile-smartphone-policy.md)). |
| **PC9** | **Graphs UX** | Chart panels max-width **960px**, bordered boxes ([`player-milestones.css`](../site/public_html/stylesheets/player-milestones.css) `.k2-ms-detail-charts`). Ink follows site tint via `K2ChartTheme.tintChartInk()` (`data-k2-accent` → `--k2-chart-*`). Inline JSON payload in page (v1 spike — no separate API). |
| **PC10** | **DB lifecycle** | Precompute **`$k2AmigaPlayerHasVideos`** before `mysqli_close($con)` on page shell — player nav needs it ([`amiga_player_chronologies_page.php`](../site/public_html/includes/amiga_player_chronologies_page.php)). |

---

## 3. URL map (shipped)

| Route key | Path | Segment |
|-----------|------|---------|
| `amiga-player-chronologies-opponents-made-it` | `/amiga/player/chronologies/opponents/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-opponents-graphs` | `/amiga/player/chronologies/opponents/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/opponents/index.php` | 302 → made-it |
| `amiga-player-chronologies-victims-made-it` | `/amiga/player/chronologies/victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-victims-graphs` | `/amiga/player/chronologies/victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-dd-victims-made-it` | `/amiga/player/chronologies/dd_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-dd-victims-graphs` | `/amiga/player/chronologies/dd_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/dd_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-cs-victims-made-it` | `/amiga/player/chronologies/cs_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-cs-victims-graphs` | `/amiga/player/chronologies/cs_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/cs_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-mgc-victims-made-it` | `/amiga/player/chronologies/mgc_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-mgc-victims-graphs` | `/amiga/player/chronologies/mgc_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/mgc_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-bl-victims-made-it` | `/amiga/player/chronologies/bl_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-bl-victims-graphs` | `/amiga/player/chronologies/bl_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/bl_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-mgs-culprits-made-it` | `/amiga/player/chronologies/mgs_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-mgs-culprits-graphs` | `/amiga/player/chronologies/mgs_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/mgs_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-bw-culprits-made-it` | `/amiga/player/chronologies/bw_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-bw-culprits-graphs` | `/amiga/player/chronologies/bw_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/bw_culprits/index.php` | 302 → made-it |

Register in [`k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php). Document in [`url-routes.md`](url-routes.md).

**Helpers:**

| Helper | Use |
|--------|-----|
| `amiga_player_chronology_opponents_href($playerId, $segment)` | Internal segment nav (opponents) |
| `amiga_player_chronology_opponents_entry_href($playerId)` | Profile mosaic + external entry (includes `#k2-amiga-chronology-spotlight`) |
| `amiga_player_chronology_victims_href($playerId, $segment)` | Internal segment nav (victims) |
| `amiga_player_chronology_victims_entry_href($playerId)` | Profile mosaic Victims row |
| `amiga_player_chronology_dd_victims_href($playerId, $segment)` | Internal segment nav (DD victims) |
| `amiga_player_chronology_dd_victims_entry_href($playerId)` | Profile mosaic DD Victims row |
| `amiga_player_chronology_cs_victims_href($playerId, $segment)` | Internal segment nav (CS victims) |
| `amiga_player_chronology_cs_victims_entry_href($playerId)` | Profile mosaic CS Victims row |
| `amiga_player_chronology_mgc_victims_href($playerId, $segment)` | Internal segment nav (MGC victims) |
| `amiga_player_chronology_mgc_victims_entry_href($playerId)` | Profile mosaic MGC Victims row |
| `amiga_player_chronology_bl_victims_href($playerId, $segment)` | Internal segment nav (BL victims) |
| `amiga_player_chronology_bl_victims_entry_href($playerId)` | Profile mosaic BL Victims row |
| `amiga_player_chronology_mgs_culprits_href($playerId, $segment)` | Internal segment nav (MGS culprits) |
| `amiga_player_chronology_mgs_culprits_entry_href($playerId)` | Profile mosaic MGS Culprits row |
| `amiga_player_chronology_bw_culprits_href($playerId, $segment)` | Internal segment nav (BW culprits) |
| `amiga_player_chronology_bw_culprits_entry_href($playerId)` | Profile mosaic BW Culprits row |
| `amiga_player_chronology_href($playerId, $kind, $segment)` | Generic segment nav |
| `amiga_player_chronology_spotlight_hash()` | Fragment only |

---

## 4. Reference kind — Opponents (shipped)

### 4.1 Rule line

Spotlight rule: *Players that **{name}** has faced* — player name = `k2-link-star` link to profile.

### 4.2 Made it table

**One row per distinct rated opponent** at cutoff — the **first rated meeting** (tournament event day + game).

| Col | Header | Notes |
|-----|--------|-------|
| 0 | `#` | **Fixed unlock order** — 1 = earliest first meeting; **does not renumber** when user sorts. SQL `unlock_rank` ASC; display order default newest-first. |
| 1 | Opponent | Flag + player link; **anchor column** (`data-k2-anchor-col="1"`) |
| 2 | First met | Event day only; **quiet date** on default load (`data-k2-quiet-default-sort-cols="2"`); secondary ink |
| 3 | Event | Tournament flag + link (`amiga_rated_game_tournament_cell`); `pad-x-md` |
| 4–7 | Team A · goals · goals · Team B | Tournament-games scoreboard mirror (`k2-table--tournament-games`); empty goal `<th>`; Team A `pad-left-md` |
| 8 | Result | Win / Draw / Loss from hero perspective |
| 9 | Adj. | Rating adjustment on first meeting game (— when unprocessed) |

**Default sort:** First met **desc** (newest unlock first). Tie on sort: `#` via `data-k2-sort-tie-order="match"`.

**SQL:** `amiga_player_chronology_opponents_load()` — partition by opponent id; order first meeting by `tournament_event_date`, `tournament_chrono`, `tournament_id`, `id`; outer `unlock_rank` + `ORDER BY` display desc. Chart payload sorts chronology ASC internally for cumulative series.

### 4.3 Graphs

| Chart | Description |
|-------|-------------|
| New opponents per year | Bar chart by calendar year of first meeting |
| Cumulative opponents | Stepped line — +1 per new opponent |

Empty state note when zero opponents; charts still span rated era.

### 4.4 Profile mosaic link

**Victims & Culprits panel — Opponents row:** `DifferentOpponents` count → `amiga_player_chronology_opponents_entry_href()` when count > 0. Register: [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) §4.

---

## 4.5 Reference kind — Victims (shipped)

### Rule line

Spotlight rule: *Players that **{name}** has beaten at least once* — same link-star name treatment as Opponents §4.1.

### Made it table

**One row per distinct victim** at cutoff — the **first rated win** vs that opponent (tournament event day + game). Same column layout as Opponents §4.2 except:

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Victim | Anchor column |
| 2 | First win | Quiet date on default load; hero-win games only |

**SQL:** `amiga_player_chronology_victims_load()` — same window partition as Opponents, inner filter `amiga_player_chronology_hero_win_sql()` (`ActualScore` win predicate). Parity oracle: row count = `DifferentVictims` on `amiga_player_current`.

### Graphs

| Chart | Description |
|-------|-------------|
| New victims per year | Bar chart by calendar year of first win |
| Cumulative victims | Stepped line — +1 per new victim |

### Profile mosaic link

**Victims row:** `DifferentVictims` count → `amiga_player_chronology_victims_entry_href()` when count > 0.

---

## 4.6 Reference kind — DD Victims (shipped)

### Rule line

Spotlight rule: *Players that **{name}** has scored 10 or more against at least once* — matches `DoubleDigitsVictims` / `dd_wins > 0` pair contract ([`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) §4). **Not win-gated** — a loss where hero GF ≥ 10 still counts.

### Made it table

**One row per distinct DD victim** at cutoff — the **first rated game** where hero GF ≥ 10 vs that opponent.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Victim | Anchor column |
| 2 | First DD | Quiet date on default load |

**SQL:** `amiga_player_chronology_dd_victims_load()` — Victims partition + `amiga_player_chronology_hero_gf_min_sql($playerId, 10)`. Parity: row count = `DoubleDigitsVictims`.

### Graphs

Year bar + cumulative stepped line (`amiga-chronology-dd-victims-charts.js`).

### Profile mosaic link

**DD Victims row:** `DoubleDigitsVictims` → `amiga_player_chronology_dd_victims_entry_href()` when count > 0.

---

## 4.7 Reference kind — CS Victims (shipped)

### Rule line

Spotlight rule: *Players that **{name}** has shut out at least once* — matches `CleanSheetsVictims` / `cs_wins > 0` (`goals_against = 0` for hero). **Not win-gated** — a 0–0 draw vs an opponent still counts.

### Made it table

**One row per distinct CS victim** at cutoff — the **first rated game** where hero GA = 0 vs that opponent.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Victim | Anchor column |
| 2 | First CS | Quiet date on default load |

**SQL:** `amiga_player_chronology_cs_victims_load()` — partition + `amiga_player_chronology_hero_ga_max_sql($playerId, 0)`. Parity: row count = `CleanSheetsVictims`.

### Graphs

Year bar + cumulative stepped line (`amiga-chronology-cs-victims-charts.js`).

### Profile mosaic link

**CS Victims row:** `CleanSheetsVictims` → `amiga_player_chronology_cs_victims_entry_href()` when count > 0.

---

## 4.8 Reference kind — MGC Victims (shipped)

### Rule line

Spotlight rule: *Players whose most conceded goals game was against **{name}*** — inverse personal-record pointer ([`website-data-contract.md`](website-data-contract.md) § Personal record pointers). Tie: first credited culprit keeps credit until strictly beaten.

### Made it table (current inventory at cutoff)

**Not** a monotonic first-occurrence list. Rows = opponents X where X's snapshot at cutoff has `MostGoalsConcededCulpritID = hero`. Players who **left** the set (credit transferred) are **omitted**. `#` ranks only current members by credited record-game chronology (oldest = 1); does not renumber on sort.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Victim | Anchor column |
| 2 | First MGC | Quiet date — victim's credited `MostGoalsConcededGameID` vs hero |

**SQL:** `amiga_player_chronology_mgc_victims_load()` — `amiga_player_chronology_mgc_victim_snapshots_sql()` (present: `amiga_player_current`; TT: latest `amiga_player_event_snapshots` row per player ≤ cutoff) + join credited rated game. Parity: row count = `MostGoalsConcededVictims` on hero snapshot.

### Graphs

Year bar + cumulative on **current** victims' credited games (`amiga-chronology-mgc-victims-charts.js`). Not a gain/loss event ledger.

### Profile mosaic link

**MGC Victims row:** `MostGoalsConcededVictims` → `amiga_player_chronology_mgc_victims_entry_href()` when count > 0.

---

## 4.9 Reference kind — BL Victims (shipped)

### Rule line

Spotlight rule: *Players whose biggest loss game was against **{name}*** — inverse personal-record pointer ([`website-data-contract.md`](website-data-contract.md) § Personal record pointers). Tie: first credited culprit keeps credit until strictly beaten.

### Made it table (current inventory at cutoff)

**Not** a monotonic first-occurrence list. Rows = opponents X where X's snapshot at cutoff has `BiggestLossCulpritID = hero`. Players who **left** the set (credit transferred) are **omitted**. `#` ranks only current members by credited record-game chronology (oldest = 1); does not renumber on sort.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Victim | Anchor column |
| 2 | First BL | Quiet date — victim's credited `BiggestLossGameID` vs hero |

**SQL:** `amiga_player_chronology_bl_victims_load()` — `amiga_player_chronology_bl_victim_snapshots_sql()` (present: `amiga_player_current`; TT: latest `amiga_player_event_snapshots` row per player ≤ cutoff) + join credited rated game. Parity: row count = `BiggestLossVictims` on hero snapshot.

### Graphs

Year bar + cumulative on **current** victims' credited games (`amiga-chronology-bl-victims-charts.js`). Not a gain/loss event ledger.

### Profile mosaic link

**BL Victims row:** `BiggestLossVictims` → `amiga_player_chronology_bl_victims_entry_href()` when count > 0.

---

## 4.10 Reference kind — MGS Culprits (shipped)

### Rule line

Spotlight rule: *Culprits whose most scored goals game was against **{name}*** — inverse victim pointer on culprit snapshots ([`website-data-contract.md`](website-data-contract.md) § Personal record pointers). Tie: first credited victim keeps credit until strictly beaten.

### Made it table (current inventory at cutoff)

Rows = culprits X where X's snapshot at cutoff has `MostGoalsScoredVictimID = hero`. Departed culprits omitted. `#` ranks current members by credited `MostGoalsScoredGameID` chronology.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Culprit | Anchor column |
| 2 | First MGS | Quiet date — culprit's credited record game vs hero |

**SQL:** `amiga_player_chronology_mgs_culprits_load()` — `amiga_player_chronology_mgs_culprit_snapshots_sql()` + credited rated game join. TT bind: **`sdii`** on cutoff tuple. Parity: row count = inverse scan at cutoff (= `MostGoalsScoredCulprits` on hero when pointers are consistent).

### Profile mosaic link

**MGS Culprits row:** `MostGoalsScoredCulprits` → `amiga_player_chronology_mgs_culprits_entry_href()` when count > 0.

---

## 4.11 Reference kind — BW Culprits (shipped)

### Rule line

Spotlight rule: *Culprits whose biggest win game was against **{name}*** — inverse victim pointer on culprit snapshots. Tie: first credited victim keeps credit until strictly beaten.

### Made it table (current inventory at cutoff)

Rows = culprits X where X's snapshot at cutoff has `BiggestWinVictimID = hero`. Credited game = `BiggestWinGameID`.

**SQL:** `amiga_player_chronology_bw_culprits_load()` — `amiga_player_chronology_bw_culprit_snapshots_sql()` + join. TT bind: **`sdii`**. Parity: inverse scan at cutoff (= `BiggestWinCulprits` on hero when consistent).

### Profile mosaic link

**BW Culprits row:** `BiggestWinCulprits` → `amiga_player_chronology_bw_culprits_entry_href()` when count > 0.

---

## 5. Kind register

| Kind | Mosaic source | Made it row | Graphs | Status |
|------|---------------|-------------|--------|--------|
| **opponents** | Victims panel · Opponents | First rated meeting per opponent | Year bar + cumulative | **Shipped** |
| **victims** | Victims | First rated win per victim | Year bar + cumulative | **Shipped** |
| **dd_victims** | DD Victims | First rated game hero GF ≥ 10 per victim | Year bar + cumulative | **Shipped** |
| **cs_victims** | CS Victims | First rated game hero GA = 0 per victim | Year bar + cumulative | **Shipped** |
| **mgc_victims** | MGC Victims | Current victims: credited MGC culprit = hero | Year bar + cumulative (current set) | **Shipped** |
| **bl_victims** | BL Victims | Current victims: credited BL culprit = hero | Year bar + cumulative (current set) | **Shipped** |
| **culprits** | Culprits | First loss to culprit | TBD | **Planned** |
| **dd_culprits** | DD Culprits | … | TBD | **Planned** |
| **cs_culprits** | CS Culprits | … | TBD | **Planned** |
| **mgs_culprits** | MGS Culprits | Current culprits: credited MGS victim = hero | Year bar + cumulative (current set) | **Shipped** |
| **bw_culprits** | BW Culprits | Current culprits: credited BW victim = hero | Year bar + cumulative (current set) | **Shipped** |

Add a row when a kind ships; do not link mosaic cells until Made it exists ([`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) §3).

---

## 6. Adding a new kind (agent checklist)

1. **Read** this policy + [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) register row.
2. **Copy** Opponents folder layout: `{kind}/made-it.php`, `graphs.php`, `index.php` → require shared `amiga_player_chronologies_page.php` with `$k2AmigaPlayerChronologyKind` / `$k2AmigaPlayerChronologySegment`.
3. **Extend** `amiga_player_chronologies_lib.php` — kind constant, load fn, chart payload, href helpers.
4. **Extend** `amiga_player_chronologies_render.php` — spotlight rule line, table/charts render (or kind-specific render file if large).
5. **Register** routes in `k2_amiga_routes.php` + [`url-routes.md`](url-routes.md).
6. **Wire mosaic** in `amiga_profile_lb_slices.php` — entry href + hash when count > 0.
7. **Table stack** — full [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md); quiet date if default sort is date without ID column.
8. **TT smoke** — present + `as=` cutoff; zero count → no link on mosaic.
9. **UPDATE_DOCS** — kind register row here + policy §4 register in stat-links doc.

---

## 7. Explicit non-goals (v1)

- Stored unlock table / post-game writer for chronology rows
- Player wing tab pill for chronologies (mosaic entry only)
- Links from Made it rows to hub leaderboards
- Card reflow / mobile column hiding on Made it table
- Remaining culprit kinds (Different / DD / CS culprits; register only)
- Online realm chronologies (Amiga-only track)

---

## 8. Agent traps

- **Do not** send mosaic Opponents count to `/amiga/player/opponents/h2h.php` — that is the **comparison wing**.
- **Do not** close `$con` before `amiga_player_nav.php` — set `$k2AmigaPlayerHasVideos` first.
- **Do not** put anchor col on First met — use Opponent col + quiet-date cols or dates get link-star ink.
- **Do not** rely on client-only sort for initial row order — SQL `ORDER BY` must match default sort; use `data-k2-skip-initial-sort="1"`.
- **Do not** use agent `Write` on new PHP under Windows — StrReplace or PowerShell UTF-8 ([`.cursor/rules/utf8-windows.mdc`](../.cursor/rules/utf8-windows.mdc)).
- **Mosaic aggregate vs Made-it row count:** pointer chronologies are TT-correct; hero inverse **columns** now read from sparse changelog ([`amiga-player-inverse-count-timeline-policy.md`](amiga-player-inverse-count-timeline-policy.md)) — mosaic/LB should match chronology after ship.

---

*Last updated: Jul 2026 — MGS/BW Culprits (inverse victim-pointer inventory, `sdii` TT bind) shipped.*