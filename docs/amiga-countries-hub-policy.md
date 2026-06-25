# Amiga Countries hub — policy

**Status:** **Policy locked** (Jun 2026) — not yet implemented.

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H8 country token)

**Related:** [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) (WC-only nation roll-ups — sibling surface) · [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`url-routes.md`](url-routes.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md)

**Implementation plan:** TBD — add [`amiga-countries-hub-implementation-plan.md`](amiga-countries-hub-implementation-plan.md) before slice 0.

---

## 1. Executive summary

Add a **Countries** hub on the Amiga realm — a first-class answer to *“which players does country X have?”*

| Surface | Question |
|---------|----------|
| **Countries index** (`/amiga/countries/index.php`) | Which nations exist on the ladder, how large are they, how active, what is their WC footprint? |
| **Country roster** (`/amiga/countries/roster.php?country=`) | Who are the players from this country — strength, activity, WC medals, last event? |

This is **career-wide** nationality browse (all tournaments), not WC-only nation stats. World Cups → **Country stats** (`/amiga/world-cups/countries/*`) remains the WC performance surface; cross-link both ways.

**Chess-club analogy:** click a nation → see the roster → see strength and activity. **Not** a federation or “national team” — copy uses *players from {country}* / *{country} roster*.

**V2 (out of v1 scope):** **Country vs country** compare page — same product role as player H2H (`amiga/player/opponents/h2h.php`), at nation grain.

**Explicitly out of scope for this track:** leaderboard country filter (`?country=` on Rating/Goals wings) — separate future project.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CH1** | **Hub tab** | **Countries** is a **top-level Amiga hub tab** — present in full hub nav and in **time-travel** hub tab set (`K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS`). |
| **CH2** | **Tab order (present)** | After **World Cups**, before **Activity**: News · Leaderboards · World Cups · **Countries** · Activity · Hall of Fame · Tournaments · Live tournaments. |
| **CH3** | **URL shape** | Foldered hub per [`url-routes.md`](url-routes.md): `/amiga/countries/index.php` (default) · `/amiga/countries/roster.php?country={token}`. Register routes in `k2_amiga_routes.php`. `country` query param = **country token** lookup (entity key), not hub mode. |
| **CH4** | **Country token** | `TRIM(amiga_players.country)` when non-empty ([**H8**](amiga-hof-tournament-geo-policy.md)); empty/NULL → literal **`Unknown`**. |
| **CH5** | **Index row eligibility** | One row per country token with **≥1 national** where `NumberGames > 0` at the active cutoff (present or time travel). |
| **CH6** | **Roster eligibility** | All nationals with `NumberGames > 0` at cutoff, sorted **rating descending** (default). |
| **CH7** | **Index default sort** | **Player count descending** (largest nations first). |
| **CH8** | **Roster default sort** | **Rating descending** (strongest first). |
| **CH9** | **Flag links** | Flag and country name on the index both link to the country roster. Site-wide nationality flags (profile hero, LB country column, WC **player** tables) link to the same roster URL when wired — **not** tournament **host** country cells. |
| **CH10** | **Roster flag column** | **One flag per roster row** — same country flag repeated on every player row (visual rhythm; “lovely flags repeated”). |
| **CH11** | **Medal columns UI** | Reuse Status/Leagues podium medal glyphs: `k2_status_league_podium_medal(1|2|3)` in `<th>` (`k2-lb-honours-medal-th`), **integer counts** in `<td>`. Same pattern as [`amiga_wc_players_table.php`](../site/public_html/includes/amiga_wc_players_table.php). |
| **CH12** | **WC entries label** | UI label **WC entries** on both surfaces; tooltips **must** clarify the two grains (§5.2). |
| **CH13** | **Games / player** | Index column **Games / player** = `games ÷ players`; display **one decimal** (e.g. `58.3`). |
| **CH14** | **Rank on roster** | **Global** `elo_rank` at cutoff — not rank-within-country. |
| **CH15** | **Last event** | **Name** (link to tournament) + **date** from player’s last participation at cutoff: present → `amiga_player_current.last_tournament_id` + `last_event_date`; time travel → snapshot row’s `tournament_id` + `event_date` (that row is last event ≤ cutoff). |
| **CH16** | **Time travel** | Both index and roster **must** honour `as=` from slice 1 — same cutoff tuple as leaderboards (`AmigaSnapshotContext`). |
| **CH17** | **Host vs nationality** | Roster/index = **player nationality**. Tournament **host** country (where an event was held) stays on tournament/games filters — do not route host flags to country roster. |
| **CH18** | **Cross-links** | Roster page → WC country stats for that token when row exists. WC country index row → country roster. Optional one-line on index lede. |
| **CH19** | **V2 — Country vs country** | Nation-pair compare page (texture similar to player H2H) — **deferred**; note in policy only; no v1 URLs or stubs. |
| **CH20** | **LB country filter** | **Out of scope** for this track. |
| **CH21** | **Stored truth v1** | **No new derived tables or finalize writers** — read-time aggregation from `amiga_player_current` (present) or latest `amiga_player_event_snapshots` row per player ≤ cutoff (time travel). ~470 players × ~21 countries is acceptable. Revisit stored country roll-ups only if perf or verify demands it. |
| **CH22** | **k2-table stack** | Both tables use full k2-table checklist (cloak, SSR sort, mirror, column help where needed). |

---

## 3. Surfaces

### 3.1 Countries index

**Path:** `/amiga/countries/index.php`  
**Hub:** `$k2AmigaHubTabActive = 'countries'`

**Chapter lede (proposed):** *Browse players by country — roster size, activity, and World Cup footprint.*

| # | Column | Definition |
|---|--------|------------|
| 1 | **Rank** | Auto-rank (k2-table) |
| 2 | **Flag** | Mapped flag when available; links to roster |
| 3 | **Country** | Country token (display name); links to roster |
| 4 | **Players** | `COUNT(DISTINCT player_id)` with `NumberGames > 0` |
| 5 | **Games** | `SUM(NumberGames)` — **career** games across all nationals |
| 6 | **Games / player** | `games ÷ players` — one decimal |
| 7 | **WC entries** | `SUM(wc_played)` across nationals — national **headcount** across all WCs (five Danes in one WC = 5). Same value as **`wc_participations`** on WC country honours slice. |
| 8 | **Gold** | `SUM(wc_gold)` |
| 9 | **Silver** | `SUM(wc_silver)` |
| 10 | **Bronze** | `SUM(wc_bronze)` |

**Default sort:** Players descending (**CH7**).

### 3.2 Country roster

**Path:** `/amiga/countries/roster.php?country={token}`  
**Sub-nav:** None for v1 (single table). Optional return link to index in chapter area.

**Country hero:** Large flag (when mapped), country name, optional compact summary line (player count · total games · WC entries · medal totals) — derived from same aggregates as index row for this token.

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

**Default sort:** Rating descending (**CH8**).

---

## 4. WC entries — two grains (tooltips required)

| Surface | Column | Source field | Meaning | Example |
|---------|--------|--------------|---------|---------|
| **Index (country)** | WC entries | `SUM(wc_played)` | Total **national appearances** across all World Cups — each player × each WC they entered counts once | 5 Danish players in WC 2003 → +5 for Denmark |
| **Roster (player)** | WC entries | `wc_played` | **World Cups this player entered** | One player, three WCs → 3 |

**Tooltip copy (proposed):**

- **Index:** *Total national entries across all World Cups — each player who entered a World Cup counts once per event.*
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
| **Hub tab Countries** | → `/amiga/countries/index.php` |
| **Index flag / name** | → `/amiga/countries/roster.php?country={token}` |
| **Profile hero flag** | → roster (when wired) |
| **LB country column flag** | → roster (when wired) |
| **WC player table country cell** | → roster (when wired) |
| **Tournament host country** | **No link** to roster (**CH17**) |

All Amiga links carry `as=` via `amiga_url_with_context()` / `k2_amiga_route()` when time travel active.

**Route keys (proposed):**

| Key | Path |
|-----|------|
| `amiga-countries` | `amiga/countries/index.php` |
| `amiga-countries-roster` | `amiga/countries/roster.php` |

**Helper (proposed):** `k2_amiga_country_roster_href(string $countryToken): string`

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
| **Unmapped flags** | Fall back to escaped country name text ([`k2_amiga_country_flag.php`](../site/public_html/includes/k2_amiga_country_flag.php)) |
| **Medals zero** | Show `0` (consistent with WC tables) unless a slice chooses em dash for zero — pick one at implementation |

---

## 9. V2 — Country vs country (deferred)

**Intent:** A **nation-pair** page comparable to player H2H — e.g. Denmark vs Sweden: combined headcount, shared WC history, games between nationals, medal comparison, maybe cumulative charts at nation grain.

**Not in v1:** no routes, nav, or stubs. When scoped, likely under `/amiga/countries/compare.php?a=&b=` or similar; may require stored nation-pair facts or heavy read-time scans — separate policy/plan.

**Analogy:** Player H2H = [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md); Country vs country = same *compare two entities* product pattern at country grain.

---

## 10. Out of scope (this track)

- Leaderboard country filter
- Country vs country compare (v2)
- Activity charts per nationality (community stats `player_nationality` facts — separate Activity slice)
- New HoF rows for countries
- Stored `amiga_country_career_totals` table (unless perf review forces later)
- Online realm — nationality not meaningful there

---

## 11. Files (expected at implementation)

| Area | Files |
|------|-------|
| **Pages** | `amiga/countries/index.php`, `amiga/countries/roster.php` |
| **Load / render** | `includes/amiga_countries_index_lib.php`, `includes/amiga_countries_roster_lib.php`, `includes/amiga_countries_table.php` (or split index/roster render) |
| **Hero** | `includes/amiga_country_hero.php` |
| **Routes** | `k2_amiga_routes.php` — `amiga-countries`, `amiga-countries-roster` |
| **Hub nav** | `amiga_hub_nav_lib.php` — tab + TT allowlist |
| **Flag links** | `k2_amiga_country_flag.php` — optional `link` opt + `k2_amiga_country_roster_href()` |
| **Help** | `lb_column_help.php` — WC entries tooltips (index + roster grains) |
| **Docs** | This policy · implementation plan · `url-routes.md` · `amiga-profile-v0.md` hub table |

---

## 12. Verification (v1)

| Check | Method |
|-------|--------|
| Index aggregates | Spot-check Denmark: player count, sum games, sum `wc_played`, sum medals vs manual SQL |
| WC entries parity | Index `WC entries` = sum of roster column for same country at present |
| Time travel | Same country at early `as=` — roster shrinks; index row counts drop |
| Flag links | Profile + LB + index → roster with `as=` preserved |
| Host country | Tournament catalog host flag does **not** link to roster |

No `prove` gate for v1 read-only aggregation unless stored tables added later.