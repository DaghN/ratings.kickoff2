# Amiga player chronologies — policy

**Status:** **Shipped v1** (Jul 2026) — opponent/victim/culprit kinds + **country unlock** kinds (host / faced / beaten / beaten by). Calendar & geo mosaic inventory complete. **WC slice kinds** (`wc_*`, ten kinds) — World Cups hub → Player stats → Opponents wing inventory links complete (read-time SQL; no mosaic).

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
| **Hub leaderboards** | **Comparison** — where does this player rank? **Exception (Jul 2026):** Amiga **Victims & Culprits** LB wing — Opponents→BW Culprits cols link to chronology Made-it (inventory drill-down; plain C1 `k2-table-cell-link` via `amiga_lb_victims_chronology_cell_html()`). **WC Player stats → Opponents wing** — Games + unlock cols → games tab `filter=world-cup` or matching `wc_*` chronology (§4.0). |
| **Milestone detail** (`milestone.php`) | Server-wide achievers for one milestone key — same *spotlight + Made it \| Graphs* UX family, different data scope. |
| **Online / Amiga milestone chronology** (`player/milestones/chronology.php`) | Player’s **milestone unlock timeline** — not opponent/victim inventories. |

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **PC1** | **Inventory mode** | Chronology pages serve **inventory** only ([`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) §2). No default links to LB row anchors from table cells. |
| **PC2** | **Folder per kind** | `/amiga/player/chronologies/{kind}/` — plural **`chronologies`**. Each segment = **separate PHP file** (`made-it.php`, `graphs.php`). `{kind}/index.php` → 302 default segment. **No** `?view=` / `?tab=` / `?wing=` for navigation ([`k2-page-structure-checklist.md`](k2-page-structure-checklist.md)). |
| **PC3** | **Entry** | **Profile mosaic** + **Victims & Culprits LB wing** (Jul 2026) — no new player wing pill; player nav shows with **no tab active** (`$k2AmigaPlayerTabActive = ''`). Wing nav remains visible (Profile · Opponents · Tournaments · Games · Videos). |
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
| `amiga-player-chronologies-wc-opponents-made-it` | `/amiga/player/chronologies/wc_opponents/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-opponents-graphs` | `/amiga/player/chronologies/wc_opponents/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_opponents/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-victims-made-it` | `/amiga/player/chronologies/wc_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-victims-graphs` | `/amiga/player/chronologies/wc_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-culprits-made-it` | `/amiga/player/chronologies/wc_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-culprits-graphs` | `/amiga/player/chronologies/wc_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-dd-victims-made-it` | `/amiga/player/chronologies/wc_dd_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-dd-victims-graphs` | `/amiga/player/chronologies/wc_dd_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_dd_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-dd-culprits-made-it` | `/amiga/player/chronologies/wc_dd_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-dd-culprits-graphs` | `/amiga/player/chronologies/wc_dd_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_dd_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-cs-victims-made-it` | `/amiga/player/chronologies/wc_cs_victims/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-cs-victims-graphs` | `/amiga/player/chronologies/wc_cs_victims/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_cs_victims/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-cs-culprits-made-it` | `/amiga/player/chronologies/wc_cs_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-cs-culprits-graphs` | `/amiga/player/chronologies/wc_cs_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_cs_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-countries-faced-made-it` | `/amiga/player/chronologies/wc_countries_faced/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-countries-faced-graphs` | `/amiga/player/chronologies/wc_countries_faced/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_countries_faced/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-countries-beaten-made-it` | `/amiga/player/chronologies/wc_countries_beaten/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-countries-beaten-graphs` | `/amiga/player/chronologies/wc_countries_beaten/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_countries_beaten/index.php` | 302 → made-it |
| `amiga-player-chronologies-wc-countries-beaten-by-made-it` | `/amiga/player/chronologies/wc_countries_beaten_by/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-wc-countries-beaten-by-graphs` | `/amiga/player/chronologies/wc_countries_beaten_by/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/wc_countries_beaten_by/index.php` | 302 → made-it |
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
| `amiga-player-chronologies-culprits-made-it` | `/amiga/player/chronologies/culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-culprits-graphs` | `/amiga/player/chronologies/culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-dd-culprits-made-it` | `/amiga/player/chronologies/dd_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-dd-culprits-graphs` | `/amiga/player/chronologies/dd_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/dd_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-cs-culprits-made-it` | `/amiga/player/chronologies/cs_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-cs-culprits-graphs` | `/amiga/player/chronologies/cs_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/cs_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-mgs-culprits-made-it` | `/amiga/player/chronologies/mgs_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-mgs-culprits-graphs` | `/amiga/player/chronologies/mgs_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/mgs_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-bw-culprits-made-it` | `/amiga/player/chronologies/bw_culprits/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-bw-culprits-graphs` | `/amiga/player/chronologies/bw_culprits/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/bw_culprits/index.php` | 302 → made-it |
| `amiga-player-chronologies-host-countries-made-it` | `/amiga/player/chronologies/host_countries/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-host-countries-graphs` | `/amiga/player/chronologies/host_countries/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/host_countries/index.php` | 302 → made-it |
| `amiga-player-chronologies-countries-faced-made-it` | `/amiga/player/chronologies/countries_faced/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-countries-faced-graphs` | `/amiga/player/chronologies/countries_faced/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/countries_faced/index.php` | 302 → made-it |
| `amiga-player-chronologies-countries-beaten-made-it` | `/amiga/player/chronologies/countries_beaten/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-countries-beaten-graphs` | `/amiga/player/chronologies/countries_beaten/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/countries_beaten/index.php` | 302 → made-it |
| `amiga-player-chronologies-countries-beaten-by-made-it` | `/amiga/player/chronologies/countries_beaten_by/made-it.php?id=` | Made it (default) |
| `amiga-player-chronologies-countries-beaten-by-graphs` | `/amiga/player/chronologies/countries_beaten_by/graphs.php?id=` | Graphs |
| *(folder default)* | `/amiga/player/chronologies/countries_beaten_by/index.php` | 302 → made-it |

Register in [`k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php). Document in [`url-routes.md`](url-routes.md).

**Helpers:**

| Helper | Use |
|--------|-----|
| `amiga_player_chronology_opponents_href($playerId, $segment)` | Internal segment nav (opponents) |
| `amiga_player_chronology_opponents_entry_href($playerId)` | Profile mosaic + external entry (includes `#k2-amiga-chronology-spotlight`) |
| `amiga_player_chronology_wc_opponents_href($playerId, $segment)` | Internal segment nav (WC opponents) |
| `amiga_player_chronology_wc_opponents_entry_href($playerId)` | WC player-stats Opponents LB + external entry (includes spotlight hash) |
| `amiga_player_chronology_wc_victims_href($playerId, $segment)` | Internal segment nav (WC victims) |
| `amiga_player_chronology_wc_victims_entry_href($playerId)` | WC player-stats Victims LB + external entry (includes spotlight hash) |
| `amiga_player_chronology_wc_culprits_entry_href($playerId)` | WC player-stats Culprits LB + external entry |
| `amiga_player_chronology_wc_dd_victims_entry_href($playerId)` | WC player-stats DD Victims LB + external entry |
| `amiga_player_chronology_wc_dd_culprits_entry_href($playerId)` | WC player-stats DD Culprits LB + external entry |
| `amiga_player_chronology_wc_cs_victims_entry_href($playerId)` | WC player-stats CS Victims LB + external entry |
| `amiga_player_chronology_wc_cs_culprits_entry_href($playerId)` | WC player-stats CS Culprits LB + external entry |
| `amiga_player_chronology_wc_countries_faced_entry_href($playerId)` | WC player-stats Opp. countries LB + external entry |
| `amiga_player_chronology_wc_countries_beaten_entry_href($playerId)` | WC player-stats Opp. beaten LB + external entry |
| `amiga_player_chronology_wc_countries_beaten_by_entry_href($playerId)` | WC player-stats Opp. beaten by LB + external entry |
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
| `amiga_player_chronology_culprits_href($playerId, $segment)` | Internal segment nav (culprits) |
| `amiga_player_chronology_culprits_entry_href($playerId)` | Profile mosaic Culprits row |
| `amiga_player_chronology_dd_culprits_href($playerId, $segment)` | Internal segment nav (DD culprits) |
| `amiga_player_chronology_dd_culprits_entry_href($playerId)` | Profile mosaic DD Culprits row |
| `amiga_player_chronology_cs_culprits_href($playerId, $segment)` | Internal segment nav (CS culprits) |
| `amiga_player_chronology_cs_culprits_entry_href($playerId)` | Profile mosaic CS Culprits row |
| `amiga_player_chronology_mgs_culprits_href($playerId, $segment)` | Internal segment nav (MGS culprits) |
| `amiga_player_chronology_mgs_culprits_entry_href($playerId)` | Profile mosaic MGS Culprits row |
| `amiga_player_chronology_bw_culprits_href($playerId, $segment)` | Internal segment nav (BW culprits) |
| `amiga_player_chronology_bw_culprits_entry_href($playerId)` | Profile mosaic BW Culprits row |
| `amiga_player_chronology_host_countries_href($playerId, $segment)` | Internal segment nav (host countries) |
| `amiga_player_chronology_host_countries_entry_href($playerId)` | Profile mosaic Host countries row |
| `amiga_player_chronology_countries_faced_href($playerId, $segment)` | Internal segment nav (countries faced) |
| `amiga_player_chronology_countries_faced_entry_href($playerId)` | Profile mosaic Countries faced row |
| `amiga_player_chronology_countries_beaten_href($playerId, $segment)` | Internal segment nav (countries beaten) |
| `amiga_player_chronology_countries_beaten_entry_href($playerId)` | Profile mosaic Countries beaten row |
| `amiga_player_chronology_countries_beaten_by_href($playerId, $segment)` | Internal segment nav (countries beaten by) |
| `amiga_player_chronology_countries_beaten_by_entry_href($playerId)` | Profile mosaic Countries beaten by row |
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

## 4.0 WC Opponents — World Cup slice (shipped Jul 2026)

**Kind token:** `wc_opponents` · **Title:** WC opponents · **Rule:** *Players that **{name}** has faced in World Cups*

**Data:** `amiga_player_chronology_wc_opponents_load()` — same window SQL as career Opponents §4 with `amiga_games_world_cup_flag_sql('r.is_world_cup')`. **No new stored table** (PC7 read-time). Row count must match `amiga_player_slice_totals.different_opponents` for slice `world_cup`.

**UI:** Reuses Opponents Made it table + Graphs (`amiga_player_chronology_render_opponents_*`). Charts payload label **WC opponents**.

**Entry:** WC hub → Player stats → Opponents wing — **Opponents** column → `amiga_player_chronology_wc_opponents_entry_href()` (`amiga_lb_victims_chronology_cell_html()`, `.blue` when count > 0). **Games** column → Games tab `filter=world-cup`.

**Opponents wing inventory (shipped Jul 2026):** remaining unlock columns use the same `wc_*` folder pattern — career kind SQL + `amiga_games_world_cup_flag_sql('r.is_world_cup')`; row count = matching `amiga_player_slice_totals` column on slice `world_cup`. All linked via `amiga_lb_victims_chronology_cell_html()` + `*_entry_href()` except Opponents (`.blue` wing anchor).

| Kind | Title | Rule tail | Slice parity col | Career UI reuse |
|------|-------|-----------|------------------|-----------------|
| `wc_victims` | WC victims | has beaten at least once in World Cups | `different_victims` | Victims |
| `wc_culprits` | WC culprits | has lost to at least once in World Cups | `different_culprits` | Culprits |
| `wc_dd_victims` | WC DD victims | scored 10+ against at least once in World Cups | `double_digits_victims` | DD Victims |
| `wc_dd_culprits` | WC DD culprits | scored 10+ against {name} at least once in World Cups | `double_digits_culprits` | DD Culprits |
| `wc_cs_victims` | WC CS victims | shut out at least once in World Cups | `clean_sheets_victims` | CS Victims |
| `wc_cs_culprits` | WC CS culprits | shut out {name} at least once in World Cups | `clean_sheets_culprits` | CS Culprits |
| `wc_countries_faced` | WC countries faced | has faced in World Cups | `opponent_countries_faced` | Countries faced |
| `wc_countries_beaten` | WC countries beaten | has beaten in World Cups | `opponent_countries_beaten` | Countries beaten |
| `wc_countries_beaten_by` | WC countries beaten by | have beaten {name} in World Cups | `opponent_countries_beaten_by` | Countries beaten by |

### WC Victims (`wc_victims`) — Jul 2026

**Title:** WC victims · **Rule:** *Players that **{name}** has beaten at least once in World Cups*

**Data:** `amiga_player_chronology_wc_victims_load()` — career victims SQL + WC game filter. Parity: `different_victims` on `world_cup` slice.

**UI:** Reuses career Victims Made it + Graphs. **LB:** Victims column on WC player-stats Opponents wing — plain C1 (not wing anchor blue; that stays on Opponents only).

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

## 4.12 Reference kind — Culprits (shipped)

### Rule line

Spotlight rule: *Players that **{name}** has lost to at least once* — same link-star name treatment as Victims §4.5.

### Made it table

**One row per distinct culprit** at cutoff — the **first rated loss** vs that opponent.

| Col | Header | Notes |
|-----|--------|-------|
| 1 | Culprit | Anchor column |
| 2 | First loss | Quiet date on default load; hero-loss games only |

**SQL:** `amiga_player_chronology_culprits_load()` — Victims partition + `amiga_player_chronology_hero_loss_sql()`. Parity: row count = `DifferentCulprits`.

### Graphs

Year bar + cumulative (`amiga-chronology-culprits-charts.js`).

### Profile mosaic link

**Culprits row:** `DifferentCulprits` → `amiga_player_chronology_culprits_entry_href()` when count > 0.

---

## 4.13 Reference kind — DD Culprits (shipped)

### Rule line

Spotlight rule: *Players that have scored 10 or more against **{name}** at least once* — matches `DoubleDigitsCulprits` / `dd_losses > 0`. **Not win/loss-gated**.

### Made it table

**One row per distinct DD culprit** — first rated game where hero GA ≥ 10 vs that opponent.

**SQL:** `amiga_player_chronology_dd_culprits_load()` + `amiga_player_chronology_hero_ga_min_sql($playerId, 10)`. Parity: `DoubleDigitsCulprits`.

### Profile mosaic link

**DD Culprits row:** `DoubleDigitsCulprits` → `amiga_player_chronology_dd_culprits_entry_href()` when count > 0.

---

## 4.14 Reference kind — CS Culprits (shipped)

### Rule line

Spotlight rule: *Players that have shut out **{name}** at least once* — matches `CleanSheetsCulprits` / `cs_losses > 0`. **Not win-gated** (0–0 counts).

### Made it table

**One row per distinct CS culprit** — first rated game where hero GF = 0 vs that opponent.

**SQL:** `amiga_player_chronology_cs_culprits_load()` + `amiga_player_chronology_hero_gf_max_sql($playerId, 0)`. Parity: `CleanSheetsCulprits`.

### Profile mosaic link

**CS Culprits row:** `CleanSheetsCulprits` → `amiga_player_chronology_cs_culprits_entry_href()` when count > 0.

---

## 4.15 Reference kinds — Country unlocks (shipped)

**Entity column = Country** (flag + roster link), not player. Folders use underscores (`host_countries`); route keys use hyphens (`host-countries`).

### Host countries

Spotlight: *Host countries where **{name}** has played*. **Event grain** — Made it columns: `#` · Country · First hosted · Event (no scoreboard). SQL: first `amiga_player_event_snapshots` row per `TRIM(country)` with `games > 0`. Parity: `countries_played_in`.

### Countries faced / beaten / beaten by

Spotlight lines as locked in product chat. **Game grain** — same scoreboard stack as Victims; Country anchor. Filters match geo H6–H8 (`GoalsA`/`GoalsB` win/loss; blank nationality skipped). Parity: `opponent_countries_faced` / `beaten` / `beaten_by`.

Loads live in `amiga_player_chronologies_countries_lib.php`.

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
| **culprits** | Culprits | First rated loss per culprit | Year bar + cumulative | **Shipped** |
| **dd_culprits** | DD Culprits | First rated game hero GA ≥ 10 per culprit | Year bar + cumulative | **Shipped** |
| **cs_culprits** | CS Culprits | First rated game hero GF = 0 per culprit | Year bar + cumulative | **Shipped** |
| **mgs_culprits** | MGS Culprits | Current culprits: credited MGS victim = hero | Year bar + cumulative (current set) | **Shipped** |
| **bw_culprits** | BW Culprits | Current culprits: credited BW victim = hero | Year bar + cumulative (current set) | **Shipped** |
| **host_countries** | Host countries | First event hosted in that country (games > 0) | Year bar + cumulative | **Shipped** |
| **countries_faced** | Countries faced | First rated game vs that opponent nationality | Year bar + cumulative | **Shipped** |
| **countries_beaten** | Countries beaten | First rated goals-win vs that nationality | Year bar + cumulative | **Shipped** |
| **countries_beaten_by** | Countries beaten by | First rated goals-loss vs that nationality | Year bar + cumulative | **Shipped** |

**WC slice kinds** (`wc_*`) — same Made it \| Graphs UX; career kind SQL ∩ `is_world_cup` games; **LB entry only** (World Cups hub → Player stats → Opponents wing). Parity = matching `amiga_player_slice_totals` column on `slice_key = 'world_cup'`. See §4.0.

| Kind | WC slice parity col | LB column | Wing anchor colour |
|------|---------------------|-----------|-------------------|
| **wc_opponents** | `different_opponents` | Opponents | `.blue` |
| **wc_victims** | `different_victims` | Victims | plain C1 |
| **wc_culprits** | `different_culprits` | Culprits | plain C1 |
| **wc_dd_victims** | `double_digits_victims` | DD Victims | plain C1 |
| **wc_dd_culprits** | `double_digits_culprits` | DD Culprits | plain C1 |
| **wc_cs_victims** | `clean_sheets_victims` | CS Victims | plain C1 |
| **wc_cs_culprits** | `clean_sheets_culprits` | CS Culprits | plain C1 |
| **wc_countries_faced** | `opponent_countries_faced` | Opp. countries | plain C1 |
| **wc_countries_beaten** | `opponent_countries_beaten` | Opp. beaten | plain C1 |
| **wc_countries_beaten_by** | `opponent_countries_beaten_by` | Opp. beaten by | plain C1 |

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
- *(none for first-occurrence culprit kinds — all shipped Jul 2026)*
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

*Last updated: Jul 2026 — WC slice chronologies (`wc_*`, ten kinds) + Opponents wing inventory links complete; host / faced / beaten / beaten-by country chronologies shipped; Calendar & geo mosaic inventory complete.*