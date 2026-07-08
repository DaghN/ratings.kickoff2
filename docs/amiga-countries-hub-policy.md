# Amiga Countries hub — policy

**Status:** **Shipped** (Jun 2026) — slices CH-1–CH-6. **Sign-off:** v1 is read-time aggregation over stored player snapshots — no **`prove`** gate; if a slice adds DDL, use **`simul`** on **`ko2amiga_work`** ([`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0).

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H8 country token)

**Related:** [`amiga-country-registry-policy.md`](amiga-country-registry-policy.md) (canonical tokens + flags — **shipped Jul 2026**) · [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) (WC-only nation roll-ups — sibling surface) · [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`url-routes.md`](url-routes.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md)

**Implementation plan:** [`amiga-countries-hub-implementation-plan.md`](amiga-countries-hub-implementation-plan.md) — slices CH-1–CH-6.

---

## 1. Executive summary

Add a **Countries** hub on the Amiga realm — a first-class answer to *“which players does country X have?”*

| Surface | Question |
|---------|----------|
| **Countries index** (`/amiga/countries.php`) | Which nations exist on the ladder, how large are they, how active, what is their WC footprint? |
| **Country roster** (`/amiga/country/roster.php?country=`) | Who are the players from this country — strength, activity, WC medals, last event? |

This is **career-wide** nationality browse (all tournaments), not WC-only nation stats. World Cups → **Country stats** (`/amiga/world-cups/countries/*`) remains the WC performance surface; cross-link both ways.

**Chess-club analogy:** click a nation → see the roster → see strength and activity. **Not** a federation or “national team” — copy uses *players from {country}* / *{country} roster*.

**Rivals (shipped Jun 2026):** **Country vs country** compare on the country entity — [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) · §9 below.

**Explicitly out of scope for this track:** leaderboard country filter (`?country=` on Rating/Goals wings) — separate future project.

**Not the same as:** L1 Access **`Countries`** lookup table (ground pipeline — [`amiga-ground-stack.md`](amiga-ground-stack.md)); WC **Country stats** wing (WC-only metrics — [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md)).

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CH1** | **Hub tab** | **Countries** is a **top-level Amiga hub tab** — present in full hub nav and in **time-travel** hub tab set (`K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS`). |
| **CH2** | **Tab order (present)** | After **Tournaments**, before **Games**: News · Leaderboards · World Cups · Tournaments · **Countries** · Games · Activity · Hall of Fame · Live. |
| **CH3** | **URL shape** | **Hub place** = plural leaf `countries.php`: `/amiga/countries.php` (Countries hub tab; same single-screen pattern as `tournaments.php`). **Entity page** = singular `country/` per [`navigation-model.md`](navigation-model.md) NM3: `/amiga/country/roster.php?country={token}` (Roster, default segment) · `/amiga/country/rivals.php?country={token}` (Rivals). `country` query param = **country token** lookup (entity key), not hub mode. Register routes in `k2_amiga_routes.php`. Legacy **`countries/index.php`** 302s to `countries.php` (query preserved). Legacy **`countries/roster.php`** 302s to `country/roster.php` (preserves `country` + `as`). Shared shell `includes/amiga_country_page.php` + segment `includes/amiga_country_nav.php`. |
| **CH4** | **Country token** | `TRIM(amiga_players.country)` when non-empty ([**H8**](amiga-hof-tournament-geo-policy.md)); empty/NULL → literal **`Unknown`**. |
| **CH5** | **Index row eligibility** | One row per country token with **≥1 national** where `NumberGames > 0` at the active cutoff (present or time travel). |
| **CH6** | **Roster eligibility** | All nationals with `NumberGames > 0` at cutoff, sorted **rating descending** (default). |
| **CH7** | **Index default sort** | **Player count descending** (largest nations first); tiebreak **games descending**, then country token ascending. |
| **CH8** | **Roster default sort** | **Float `Rating` descending** (strongest first); display still `ROUND(Rating)`; tiebreak `player_id` ASC. |
| **CH9** | **Flag + entity links** | **Every mapped Amiga country flag** links to that country’s roster with `#k2-country-roster` — via `k2_amiga_country_flag_link()` (`k2-country-roster-link` on the **img** only). **Entity name links** (player, tournament, country text) use **`k2-link-star`** via shared helpers — see [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md). **Tables:** inline `[flag][name]` compositors (`k2_amiga_lb_player_cell`, `k2_amiga_lb_tournament_cell`, `k2_amiga_lb_country_cell`); **no dedicated flag-only Country columns** (migration list in entity-links policy §4). **No text fallback** for unmapped flag tokens. **Not** filter listbox labels. Video spotlight caption: `flag_link(..., tgame class + decorative)`. |
| **CH10** | **Roster flag column** | **One flag per roster row** — same country flag repeated on every player row; **each flag links** to that roster (`#k2-country-roster`). |
| **CH11** | **Medal columns UI** | Reuse Status/Leagues podium medal glyphs: `k2_status_league_podium_medal(1|2|3)` in `<th>` (`k2-lb-honours-medal-th`), **integer counts** in `<td>`. Same pattern as [`amiga_wc_players_table.php`](../site/public_html/includes/amiga_wc_players_table.php). **Gold sort tiebreak:** when sorting the index gold column, equal gold counts → silver, then bronze (same direction); gold `<th>` carries `data-k2-sort-tie-cols="8,9"` (`k2-table.js`). |
| **CH12** | **WC entries label** | UI label **WC entries** on both surfaces; tooltips **must** clarify the two grains (§5.2). |
| **CH13** | **Games / player** | Index column **Games / player** = `games ÷ players`; display **one decimal** (e.g. `58.3`). |
| **CH14** | **Rank on roster** | **Global** `elo_rank` at cutoff — not rank-within-country. |
| **CH15** | **Last event** | **Name** (link to tournament) + **date** from player’s last participation at cutoff: present → `amiga_player_current.last_tournament_id` + `last_event_date`; time travel → snapshot row’s `tournament_id` + `event_date` (that row is last event ≤ cutoff). |
| **CH16** | **Time travel** | Both index and roster **must** honour `as=` from slice 1 — same cutoff tuple as leaderboards (`AmigaSnapshotContext`). |
| **CH17** | **Host vs nationality (data only)** | Roster roll-ups and index eligibility use **player nationality**. Tournament **host** country is a separate field on events — but **both** host and nationality **flags** link to the same country roster URL for that token (**CH9**). Host/opponent **filter listboxes** on player games stay text-only. |
| **CH18** | **Cross-links** | Roster page → WC country stats for that token when row exists. WC country index row → country roster. Optional one-line on index lede. |
| **CH19** | **Rivals (country vs country)** | Nation-pair compare (texture similar to player H2H) on the **Rivals** segment under the `country/` entity (CH24). **Shipped Jun 2026** — four wings under `country/rivals/*`; policy [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md). |
| **CH20** | **LB country filter** | **Out of scope** for this track. |
| **CH21** | **Stored truth v1** | **No new derived tables or finalize writers** — read-time aggregation from `amiga_player_current` (present) or latest `amiga_player_event_snapshots` row per player ≤ cutoff (time travel). **Index:** `amiga_countries_query_index_rows()` — one SQL `GROUP BY country_token`. **Roster:** `amiga_countries_query_roster_rows()` — SQL filtered by country + scoped elo attach; rivals hero via `amiga_countries_query_country_summary()`. Revisit stored country roll-ups only if verify demands it. |
| **CH22** | **k2-table stack** | Both tables use full k2-table checklist (cloak, SSR sort, mirror, column help where needed). |
| **CH23** | **Country = entity page** | A single country (`country/roster.php`, `country/rivals.php`) is an **entity page** ([`navigation-model.md`](navigation-model.md) NM2), not a hub mode: realm hub bar present with **no active pill** (not `countries`). The **Countries** pill is active only on the `countries.php` hub place. |
| **CH24** | **Country segment (Roster · Rivals)** | Singular `country/` carries an NM6 context sub-nav below the hub bar: **Roster** (default — career roster table) · **Rivals** (country-vs-country — four inner wings H2H · W/D/L · Goals · DDs). Segment markup follows [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) (segment track, active state via PHP var). |

---

## 3. Surfaces

### 3.1 Countries index

**Path:** `/amiga/countries.php`  
**Hub:** `$k2AmigaHubTabActive = 'countries'`

**Chapter lede:** *Over the years, **N** countries have sent their best and brightest…* — dynamic `<span class="blue">` count; roster + rivalries CTA. Helper: `amiga_countries_index_chapter_lede_html()`.

| # | Column | Definition |
|---|--------|------------|
| 1 | **Rank** | Auto-rank (k2-table) |
| 2 | **Flag** | Mapped flag when available; links to roster |
| 3 | **Country** | Country token (display name); links to roster |
| 4 | **Players** | `COUNT(DISTINCT player_id)` with `NumberGames > 0` |
| 5 | **Games** | `SUM(NumberGames)` — **career** games across all nationals |
| 6 | **Games / player** | `games ÷ players` — one decimal |
| 7 | **WC players** | `COUNT` of rated nationals with `wc_played ≥ 1` (distinct players who entered ≥1 World Cup) |
| 8 | **WC entries** | `SUM(wc_played)` across nationals — national **headcount** across all WCs (five Danes in one WC = 5). Same value as **`wc_participations`** on WC country honours slice. |
| 9 | **Gold** | `SUM(wc_gold)` |
| 10 | **Silver** | `SUM(wc_silver)` |
| 11 | **Bronze** | `SUM(wc_bronze)` |

**Default sort:** Players descending (**CH7**); equal player counts → games descending, then country token ascending.

**Gold sort:** Equal gold counts → silver descending, then bronze descending (**CH11**).

### 3.2 Country roster

**Path:** `/amiga/country/roster.php?country={token}` (entity namespace — NM3)  
**Sub-nav:** **Roster · Rivals** segment (NM6, `includes/amiga_country_nav.php`); Roster default. Hero carries country name + summary; no chapter block above hero.

**Scroll anchor:** Zero-height `#k2-country-roster` immediately above the country hero — off-page roster links append this hash so the hero lands in viewport.

**Country hero:** Player-feast grid — flag left (72×54, vertically centred), name + stat row right; plain labels for Players · Games · WC players · WC entries; **gradient podium metal labels** for Gold · Silver · Bronze (no 1st/2nd/3rd rank line). Chronology table keeps full rank + metal stack.

| # | Column | Definition |
|---|--------|------------|
| 1 | **Flag** | National flag **on every row** (**CH10**) — same mapped flag repeated |
| 2 | **Player** | Name → profile (`k2_amiga_player_profile_href`) |
| 3 | **Rating** | `ROUND(Rating)` at cutoff |
| 4 | **Rank** | Global `elo_rank` at cutoff; em dash when unranked |
| 5 | **Games** | Career `NumberGames` |
| 6 | **WC entries** | Per-player **`wc_played`** — count of **distinct World Cups entered** |
| 7 | **Gold** | `wc_gold` |
| 8 | **Silver** | `wc_silver` |
| 9 | **Bronze** | `wc_bronze` |
| 10 | **Last event** | Tournament name → tournament page |
| 11 | **Last event date** | `last_event_date` (present) or snapshot `event_date` (TT) |

**Default sort:** Float rating descending (**CH8**); Elo column `data-k2-sort-value` uses full precision so client re-sort matches server order.

---

## 4. WC entries — two grains (tooltips required)

| Surface | Column | Source field | Meaning | Example |
|---------|--------|--------------|---------|---------|
| **Index (country)** | WC players | count where `wc_played ≥ 1` | Distinct rated nationals who entered ≥1 World Cup | 8 Danish players with WC history |
| **Index (country)** | WC entries | `SUM(wc_played)` | Total **national appearances** across all World Cups — each player × each WC they entered counts once | 5 Danish players in WC 2003 → +5 for Denmark |
| **Roster (player)** | WC entries | `wc_played` | **World Cups this player entered** | One player, three WCs → 3 |

**Tooltip copy (proposed):**

- **Index WC players:** *Rated players from this country who have entered at least one World Cup.*
- **Index WC entries:** *Total national entries across all World Cups — each player who entered a World Cup counts once per event.*
- **Roster:** *World Cup events this player entered.*

Align help keys with existing WC helpers in `lb_column_help.php` where possible (`k2_lb_help_amiga_wc_played` family); add country-index-specific help for the sum grain.

---

## 5. Data model

### 5.1 Present mode

```text
amiga_players p
  JOIN amiga_player_current c ON c.player_id = p.id
  WHERE c.NumberGames > 0
  GROUP BY country_token   -- index
  OR country_token = ?     -- roster
```

**Country token SQL (locked):**

```sql
CASE WHEN TRIM(p.country) IS NULL OR TRIM(p.country) = ''
     THEN 'Unknown' ELSE TRIM(p.country) END
```

Reuse the same expression as [`amiga_country_slice_token_sql()`](../site/public_html/amiga/ops/includes/amiga_country_slice_compute_lib.php) / WC country slice (**H8** + `Unknown`).

**Fields read from `amiga_player_current`:** `NumberGames`, `Rating`, `elo_rank`, `wc_played`, `wc_gold`, `wc_silver`, `wc_bronze`, `last_tournament_id`, `last_event_date`.

### 5.2 Time travel

Mirror [`amiga_lb_snapshot_from_sql()`](../site/public_html/includes/amiga_lb_snapshot_lib.php): latest `amiga_player_event_snapshots` row per player on or before cutoff tuple `(event_date, event_chrono, tournament_id)`.

Same GROUP BY / filter as present, but snapshot alias `s` instead of `amiga_player_current`.

**Last event at cutoff:** use snapshot row’s `tournament_id` + `event_date` (that snapshot **is** the player’s state after their last event ≤ cutoff). Join `tournaments` for name.

**Players not yet debuted at cutoff:** excluded (`NumberGames > 0` on snapshot row). Countries with zero qualifying nationals at cutoff: **omit from index**.

### 5.3 Relation to WC country slice

| | **Countries hub (this track)** | **WC Country stats** (`amiga_country_slice_totals`) |
|---|-------------------------------|------------------------------------------------------|
| **Scope** | Career roster + career games | WC games/results/goals/opponents only |
| **WC entries** | Same sum semantics as `wc_participations` | Stored `wc_participations` |
| **Medals** | Sum of career `wc_*` medal cols | Same on honours slice |
| **Games** | Career `SUM(NumberGames)` | WC player-games only |

V1 may **verify** index WC columns against WC country slice at present for parity; time-travel index uses player snapshots, not `amiga_country_slice_at_event` (career games differ).

### 5.4 No v1 writers

**CH21:** No DDL, no finalize writer, no `prove` oracle required for v1 unless a slice adds stored roll-ups. Optional read-time parity check in development: index row for Denmark matches manual sum over player rows.

---

## 6. Navigation and entry points

| Entry | Behaviour |
|-------|-----------|
| **Hub tab Countries** | → `/amiga/countries.php` |
| **Any country flag cell** | → `/amiga/country/roster.php?country={token}#k2-country-roster` via `k2_amiga_country_table_cell()` (**CH9**) |
| **Index country name** (text link) | → same roster URL + hash |
| **Roster hero + row flags** | → same roster URL + hash (same country; scrolls to hero) |
| **WC podium nationality flag** | → roster for that player’s country token |

All Amiga links carry `as=` via `amiga_url_with_context()` / `k2_amiga_route()` when time travel active. **Hash landing:** off-page roster links append `#k2-country-roster`; scroll timing is handled site-wide by `includes/k2_carry_scroll_restore.php` (pre-paint cloak) — see [`k2-turbo-page-init-checklist.md`](k2-turbo-page-init-checklist.md) § Hash anchor landing (do not add page-local scroll JS).

**Route keys (shipped):**

| Key | Path |
|-----|------|
| `amiga-countries` | `amiga/countries.php` (hub place) |
| `amiga-country-roster` | `amiga/country/roster.php` (entity — Roster) |
| `amiga-country-rivals` | `amiga/country/rivals.php` (entity — Rivals) |
| `amiga-countries-roster` | `amiga/countries/roster.php` — legacy, **302** → `country/roster.php` |

**Helpers:** `k2_amiga_country_roster_href(string $countryToken, bool $scrollToHero = true)` · `k2_amiga_country_rivals_href(string $countryToken)`.

---

## 7. Time travel

| Rule | Detail |
|------|--------|
| **Hub visibility** | Countries tab shown when `as=` active — add `'countries'` to `K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS` in `amiga_hub_nav_lib.php`. |
| **Not present-only** | Unlike News / Live tournaments — Countries is snapshot-worthy. |
| **Cutoff carry** | Index and roster honour `as=`; profile/tournament links from roster append context. |
| **Empty index at early cutoffs** | Fewer countries and smaller rosters — expected; no special empty-state beyond normal k2-table zero rows. |

See [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) — same cutoff resolution as leaderboards.

---

## 8. UI and copy

| Topic | Rule |
|-------|------|
| **Product name** | Hub tab: **Countries**. Page titles: *Amiga ladder — Countries* / *Amiga ladder — {country} roster* |
| **Avoid** | “National team”, “federation”, “squad selection” |
| **Prefer** | *Players from Denmark*, *Denmark roster*, *Browse by country* |
| **Unknown** | Show **`Unknown`** row on index when blank-nationality players have games; roster page for `?country=Unknown` |
| **Unmapped flags** | Registry-backed ([`k2_amiga_country_registry.php`](../site/public_html/includes/k2_amiga_country_registry.php)); unknown/drift token → **no flag img** (name text still via entity link helpers — CH9) |
| **Medals zero** | Show `0` (consistent with WC tables) unless a slice chooses em dash for zero — pick one at implementation |

---

## 9. Rivals — country vs country (**shipped** Jun 2026)

**Intent:** A **nation-pair** surface comparable to player H2H — e.g. Denmark vs Sweden: games between nationals, W/D/L · Goals · DDs tables, H2H poster/moments/charts at directed nation grain.

**Placement:** **Rivals** segment of the `country/` entity — `/amiga/country/rivals/{h2h,wdl,goals,dds}.php?country={token}` + `rival=` for H2H drill-down. Shared shell `includes/amiga_country_page.php` + wing nav `includes/amiga_country_rivals_nav.php`.

**Policy · plan:** [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) (**CRV1–CRV16**) · [`amiga-country-rivals-implementation-plan.md`](amiga-country-rivals-implementation-plan.md) (slices **CRV-1–CRV-7**).

**Data (v1):** second read-time roll-up from `amiga_player_matchup_summary` / at-event — **not** a games rescan for table aggregates (benchmarked faster Jun 2026). Game-level reads for H2H depth filter `amiga_games` by directed nation pair.

**Analogy:** Player Opponents **country grain** (player vs country) = [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md); Rivals = same four wings at **country vs country** nation-pair hero. See **Three matchup grains** in that policy and [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) §1.1.

**Domestic row:** Rivals UI **excludes** hero→same-country (A→A); player Opponents country grain **includes** the hero's own country row (compatriots).

---

## 10. Out of scope (this track)

- Leaderboard country filter
- Activity charts per nationality (community stats `player_nationality` facts — separate Activity slice)
- New HoF rows for countries
- Stored `amiga_country_career_totals` table (unless perf review forces later)
- Online realm — nationality not meaningful there

---

## 11. Files (expected at implementation)

| Area | Files |
|------|-------|
| **Pages** | `amiga/countries.php` (hub) · `amiga/country/roster.php` + `amiga/country/rivals.php` (entity, thin entries) · shared shell `includes/amiga_country_page.php` · segment `includes/amiga_country_nav.php` · legacy `amiga/countries/index.php` + `amiga/countries/roster.php` (302) |
| **Load / render** | `includes/amiga_countries_index_lib.php`, `includes/amiga_countries_roster_lib.php`, `includes/amiga_countries_table.php` (or split index/roster render) |
| **Hero** | `includes/amiga_country_hero.php` |
| **Routes** | `k2_amiga_routes.php` — `amiga-countries`, `amiga-country-roster`, `amiga-country-rivals` (+ legacy `amiga-countries-roster` 302) |
| **Hub nav** | `amiga_hub_nav_lib.php` — tab + TT allowlist |
| **Flag links** | `k2_amiga_country_flag.php` — optional `link` opt + `k2_amiga_country_roster_href()` |
| **Help** | `lb_column_help.php` — WC entries tooltips (index + roster grains) |
| **Docs** | This policy · implementation plan · `url-routes.md` · `amiga-profile-v0.md` hub table |

---

## 12. Verification (v1)

| Check | Method |
|-------|--------|
| Index aggregates | Spot-check Denmark: player count, sum games, wc_players, sum `wc_played`, sum medals vs manual SQL |
| WC players parity | Index `WC players` = count of roster rows with `wc_played ≥ 1`; at present should match WC Country stats **Players** for same token |
| WC entries parity | Index `WC entries` = sum of roster column for same country at present |
| Time travel | Same country at early `as=` — roster shrinks; index row counts drop |
| Flag links | Profile + LB + index + host columns + podium + roster → roster with `as=` preserved |

No `prove` gate for v1 read-only aggregation unless stored tables added later.