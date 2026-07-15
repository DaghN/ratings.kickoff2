# Amiga player chronologies — policy

**Status:** **Shipped v1** (Jul 2026) — **Opponents** kind only (Made it + Graphs). Victims / culprits kinds **Planned**.

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

Register in [`k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php). Document in [`url-routes.md`](url-routes.md).

**Helpers:**

| Helper | Use |
|--------|-----|
| `amiga_player_chronology_opponents_href($playerId, $segment)` | Internal segment nav |
| `amiga_player_chronology_opponents_entry_href($playerId)` | Profile mosaic + external entry (includes `#k2-amiga-chronology-spotlight`) |
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

## 5. Kind register

| Kind | Mosaic source | Made it row | Graphs | Status |
|------|---------------|-------------|--------|--------|
| **opponents** | Victims panel · Opponents | First rated meeting per opponent | Year bar + cumulative | **Shipped** |
| **victims** | Victims | First win vs victim (TBD) | TBD | **Planned** |
| **dd_victims** | DD Victims | First DD win vs victim | TBD | **Planned** |
| **cs_victims** | CS Victims | … | TBD | **Planned** |
| **mgc_victims** | MGC Victims | … | TBD | **Planned** |
| **bl_victims** | BL Victims | … | TBD | **Planned** |
| **culprits** | Culprits | First loss to culprit | TBD | **Planned** |
| **dd_culprits** | DD Culprits | … | TBD | **Planned** |
| **cs_culprits** | CS Culprits | … | TBD | **Planned** |
| **mgs_culprits** | MGS Culprits | … | TBD | **Planned** |
| **bw_culprits** | BW Culprits | … | TBD | **Planned** |

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
- Victims / culprits kinds (register only)
- Online realm chronologies (Amiga-only track)

---

## 8. Agent traps

- **Do not** send mosaic Opponents count to `/amiga/player/opponents/h2h.php` — that is the **comparison wing**.
- **Do not** close `$con` before `amiga_player_nav.php` — set `$k2AmigaPlayerHasVideos` first.
- **Do not** put anchor col on First met — use Opponent col + quiet-date cols or dates get link-star ink.
- **Do not** rely on client-only sort for initial row order — SQL `ORDER BY` must match default sort; use `data-k2-skip-initial-sort="1"`.
- **Do not** use agent `Write` on new PHP under Windows — StrReplace or PowerShell UTF-8 ([`.cursor/rules/utf8-windows.mdc`](../.cursor/rules/utf8-windows.mdc)).

---

*Last updated: Jul 2026 — Opponents v1 shipped; policy locked after look-and-feel sign-off.*