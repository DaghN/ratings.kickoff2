# Amiga Countries hub — implementation plan

**Status:** **Complete** (Jun 2026) — CH-1–CH-6 shipped.

**Policy:** [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md)

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit** unless Dagh asks.

**Migration:** **L0** — read-time PHP only; no DDL, no finalize writers, no Part B registers.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**CH1–CH22**), columns, data grains, TT rules |
| **This plan** | File-level tasks, STOP gates, reference files, slice order |
| **Starter prompt** | **Skipped** — continue in the policy chat |

---

## How to use this plan

1. Execute slices **CH-1 → CH-6** in order (**CH-0** = policy + plan — done).
2. **STOP** if index aggregates disagree with manual SQL spot-check (Denmark).
3. **STOP** if time-travel roster/index break `as=` carry on links.
4. Wire **time travel in CH-1 data lib** — do not ship present-only pages and bolt TT on later.
5. After **CH-6**: UPDATE_DOCS Part A (`url-routes.md`, `amiga-profile-v0.md`, MEMORY, feature-log).

---

## Locked decisions (do not re-open without user)

See policy **CH1–CH22**. Compressed:

- Hub tab **Countries** after World Cups; TT tab allowlist includes `countries`
- Index default sort: **Players** descending
- Roster default sort: **Rating** descending; **flag on every roster row**
- WC entries label + **two tooltips** (country sum vs player count)
- Medal headers: `k2_status_league_podium_medal(1|2|3)`
- Read-time from `amiga_player_current` / snapshot-at-cutoff — **no new tables**
- Host country flags **do not** link to roster
- **LB country filter** and **country vs country** — out of scope

---

## Reference implementation (copy patterns)

| Area | Reference |
|------|-----------|
| Policy columns + grains | [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §3–§5 |
| Country token SQL | `amiga_country_slice_token_sql()` in `amiga/ops/includes/amiga_country_slice_compute_lib.php` |
| Present player base | `amiga_player_base_from_sql()` in `amiga_player_current_lib.php` |
| Time-travel player base | `amiga_lb_snapshot_from_sql()` in `amiga_lb_snapshot_lib.php` |
| LB context / cutoff | `amiga_lb_context()` + `AmigaSnapshotContext` |
| WC honours table (medals + WC cols) | `includes/amiga_wc_players_table.php` — honours view |
| WC country honours (parity check) | `includes/amiga_wc_countries_table.php` + `amiga_wc_countries_lb_lib.php` |
| Hub tab add | `includes/amiga_hub_nav_lib.php` — mirror World Cups entry + TT allowlist |
| Hub chapter block | `includes/k2_hub_chapter.inc.php` |
| Player hero (country stat) | `includes/amiga_player_hero.php` |
| Flag helpers | `includes/k2_amiga_country_flag.php` |
| Routes | `includes/k2_amiga_routes.php` |
| K2 table stack | [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) — reference `amiga_wc_players_table.php` / `amiga_wc_countries_table.php` |
| K2 nav | [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) — `amiga_hub_nav.php` |
| Tournament link from last event | `k2_amiga_route('amiga-tournament-standings', ['id' => …])` or existing tournament href helper |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **CH-0** | Policy + this plan | Dagh OK — **done** |
| **CH-1** | Routes, hub tab (+ TT), shared lib (token SQL, player rows at cutoff, roster href) | Hub tab visible; `k2_amiga_country_roster_href('Denmark')` returns routed URL |
| **CH-2** | Countries **index** page + sortable table | Browser: 21 rows; Denmark players=12; default sort players DESC; TT `as=` shrinks counts |
| **CH-3** | Country **roster** page + hero + sortable table | Browser: Denmark 12 rows, flags repeated; last event name+date; rating DESC default |
| **CH-4** | **Flag links** — profile hero, LB country column, WC **player** country cells | Click Denmark flag on profile → roster; host country unchanged |
| **CH-5** | **Cross-links** + column help tooltips | Roster ↔ WC country honours; WC entries tooltips on both tables |
| **CH-6** | Docs closure + table audit | `audit_k2_table_compliance.py` on new pages; MEMORY / url-routes / profile-v0 |

---

## CH-1 — Scaffolding (routes, hub, shared lib)

### Goal

Navigation and data primitives exist before any page HTML.

### Tasks

- [x] **`k2_amiga_routes.php`:** add `amiga-countries` → `amiga/countries/index.php`, `amiga-countries-roster` → `amiga/countries/roster.php`.
- [x] **`k2_amiga_country_roster_href(string $countryToken, array $extra = []): string`** — build via `k2_amiga_route('amiga-countries-roster', ['country' => $token] + $extra)`; preserve `as=` through `k2_amiga_route` / `amiga_url_with_context`.
- [x] **`amiga_hub_nav_lib.php`:**
  - Add `'countries' => ['href' => '/amiga/countries/index.php', 'label' => 'Countries']` **after** `world-cups`, **before** `activity` in `amiga_hub_all_tabs()`.
  - Append `'countries'` to `K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS`.
- [x] **`includes/amiga_countries_lib.php`** (new):
  - `amiga_countries_token_sql(string $playerAlias = 'p'): string` — delegate to or copy `amiga_country_slice_token_sql()` expression (**Unknown** bucket).
  - `amiga_countries_player_rows(mysqli $con, AmigaSnapshotContext $ctx): array` — all players with `NumberGames > 0` at cutoff; each row: `player_id`, `player_name`, `country_token`, `rating`, `elo_rank`, `number_games`, `wc_played`, `wc_gold`, `wc_silver`, `wc_bronze`, `last_tournament_id`, `last_event_date`, `last_tournament_name` (JOIN `tournaments` on last event id).
  - Present: `amiga_player_base_from_sql()` + `amiga_player_current` honours cols (`wc_*`, `last_*`).
  - TT: `amiga_lb_snapshot_from_sql('s')` + same career/honours columns on snapshot row; last event = snapshot `tournament_id` + `event_date`.
  - `amiga_countries_index_rows(array $playerRows): array` — GROUP BY `country_token`; compute players, games, games_per_player, wc_entries (sum wc_played), gold/silver/bronze sums.
  - `amiga_countries_roster_rows(array $playerRows, string $countryToken): array` — filter one token; sort rating DESC.
  - `amiga_countries_normalize_country_param(string $raw): string` — trim; allow `Unknown`; 404 or redirect if empty param on roster page.

### Verification

```text
# PHP lint / load helpers in a one-liner or temp probe
# Confirm hub nav shows Countries on /amiga/countries/index.php (404 OK until CH-2)
# Confirm amiga_hub_tabs_for_nav(true) includes countries
```

---

## CH-2 — Countries index page

### Goal

`/amiga/countries/index.php` — sortable index table; TT from day one.

### Tasks

- [x] **`amiga/countries/index.php`:**
  - Standard Amiga page shell: `site_header`, `$k2AmigaHubTabActive = 'countries'`, `amiga_hub_nav.php`.
  - `k2_hub_chapter.inc.php`: title *Countries*, lede per policy §3.1.
  - DB connect, `amiga_lb_context($con)`, load player rows → index rows, close DB.
- [x] **`includes/amiga_countries_index_table.php`** (new render):
  - Full k2-table stack (`$k2RankedCloak`, sortable assets).
  - Columns: Rank (autorank) · Flag (link) · Country (link) · Players · Games · Games/player (1 decimal) · WC entries · Gold · Silver · Bronze.
  - Medal `<th>`: `k2_status_league_podium_medal(1|2|3)` + `k2-lb-honours-medal-th`.
  - Default sort col **Players**, direction **desc** (`k2_lb_table_sort_state()` anchor on country name col).
  - Flag + name cells link via `k2_amiga_country_roster_href()`.
- [x] **`lb_column_help.php`:** add `k2_lb_help_amiga_countries_wc_entries_index()` for country-sum grain tooltip.

### Verification

| Check | Expected |
|-------|----------|
| Row count | ~21 (+ Unknown if applicable) |
| Germany | players ≈ 119, top of default sort |
| Denmark | players = 12 |
| WC entries Denmark | = sum of roster `wc_played` (after CH-3) or manual SQL |
| Time travel early cutoff | fewer countries / smaller counts |
| `audit_k2_table_compliance.py` | pass for index page |

Manual SQL (present):

```sql
SELECT TRIM(country), COUNT(*), SUM(NumberGames), SUM(wc_played),
       SUM(wc_gold), SUM(wc_silver), SUM(wc_bronze)
FROM amiga_players p
JOIN amiga_player_current c ON c.player_id = p.id
WHERE c.NumberGames > 0
GROUP BY CASE WHEN TRIM(p.country) = '' OR p.country IS NULL THEN 'Unknown' ELSE TRIM(p.country) END
HAVING country_token = 'Denmark';
```

*(Adjust column names if local DB uses different honour column casing — schema has `wc_played` on snapshots/current.)*

---

## CH-3 — Country roster page

### Goal

`/amiga/countries/roster.php?country=Denmark` — hero + roster table.

### Tasks

- [x] **`amiga/countries/roster.php`:**
  - Require `country` query param; invalid/empty → 404 or redirect to index.
  - Same hub shell; chapter title *{country} roster* (or *Players from {country}*).
  - Optional breadcrumb / link back to index in lede area.
- [x] **`includes/amiga_country_hero.php`** (new):
  - Large flag (when mapped), country name.
  - Summary line from index aggregates for this token: N players · X games · Y WC entries · medal totals (compact).
  - Mirror feast hero spacing where sensible — country-only, no avatar.
- [x] **`includes/amiga_countries_roster_table.php`** (new render):
  - Columns: Flag (every row, same country) · Player · Rating · Rank · Games · WC entries · Gold · Silver · Bronze · Last event (link) · Last event date.
  - Default sort: Rating desc.
  - Player → `k2_amiga_player_profile_href`; last event → tournament standings route.
  - Rank: global `elo_rank`; em dash when null/unranked.
- [x] **`lb_column_help.php`:** roster WC entries tooltip (`k2_lb_help_amiga_wc_played` or dedicated roster string per policy §4).

### Verification

| Check | Expected |
|-------|----------|
| Denmark roster | 12 rows, flags on each row |
| Sort default | Dagh N first by rating |
| Last event | Name links to tournament; date matches DB |
| Unknown roster | `?country=Unknown` works if index shows row |
| TT | Roster at cutoff excludes players not yet debuted |

---

## CH-4 — Flag links (site-wide nationality)

### Goal

Nationality flags become entry points to roster (**CH9**).

### Tasks

- [x] **`k2_amiga_country_flag.php`:** `k2_amiga_country_table_cell()` / `_or_dash()` default **`$link = true`**; wrap with `k2_amiga_country_roster_href()` + accessible *Players from {label}*.
- [x] **`amiga_player_hero.php`:** country flag → roster link.
- [x] **Amiga LB / WC / profile host columns** — all flag cells via table_cell helpers (linked).
- [x] **WC chronology podium** nationality flags → roster for that country token.
- [x] **Roster hero + roster table flag column** — linked (same-page `#k2-country-roster`).

### Verification

- Any flag on profile, LB, WC events/stats, profile tournament tables → country roster at hero anchor.
- Host-country flag on WC chronology → roster for host token.
- Podium nationality flag → roster (player name link unchanged).
- Roster page flags → same roster URL + hash.

---

## CH-5 — Cross-links + tooltips polish

### Goal

Sibling surfaces connect; WC entries help complete on both tables.

### Tasks

- [x] **Roster page:** one-line link to WC country honours when `amiga_country_slice_totals` row exists for token (`slice_key = 'world_cup'`, honours metrics) — e.g. *World Cup country stats →*.
- [x] **`amiga_wc_countries_table.php`:** country name/flag cell links to career roster (index of WC hub wing 4 — do not conflate products).
- [x] Confirm index + roster both use WC entries tooltips (distinct help strings).
- [x] Index lede optional: mention World Cups country stats as related surface.

### Verification

- Denmark roster → WC country honours wing.
- WC country honours row flag/name → Denmark career roster.
- Column help popovers show correct grain text on index vs roster.

---

## 6 — Docs closure

**Status:** Complete (Jun 2026-25) — doc sweep aligned policy, plan, profile-v0, url-routes, time-travel policy, WC cross-links, k2 checklists, surface-expansion, UPDATE_DOCS, AGENTS, MEMORY, feature-log.

---

## Data notes for implementer

### Honour columns on `amiga_player_current`

Schema includes `wc_played`, `wc_gold`, `wc_silver`, `wc_bronze` on both `amiga_player_current` and `amiga_player_event_snapshots` (medals unification v2). If local Laragon DB errors on `wc_played`, run schema bundle / refresh `ko2amiga_db` before slice work.

### Index WC entries parity (optional dev check)

At present, for each country token:

```text
index.wc_entries === SUM(roster.wc_played)
index.wc_entries === amiga_country_slice_totals.wc_participations  (honours slice, when row exists)
```

Second line validates alignment with WC country slice; first line is the product invariant.

### Performance

Single query for all player rows at cutoff (~470 max), aggregate in PHP — acceptable for v1. If slow, add indexed GROUP BY SQL in a follow-up slice (still read-only, no stored table).

---

## Out of scope (reminder)

- Country vs country compare (v2)
- LB country filter
- New derived tables / `prove` oracle
- Activity nationality charts
- Online realm

---

## Suggested execution order in this chat

```text
CH-1  →  CH-2  →  CH-3  →  browser proof (Denmark + Germany + TT)
CH-4  →  CH-5  →  CH-6
```

Say **"Do CH-1"** (or **"Continue CH-2"**) to start implementation.