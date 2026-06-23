# Amiga World Cup stats table — product spec (event grain)

**Status:** **Curated v1** (Jun 2026) — product/design only; no DDL, writers, or PHP in this doc. Dagh curation pass 1 applied.  
**Parent:** [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) § Product backlog · [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) (boundary only — **do not reopen C1–C13**)  
**Related:** [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (player WC slice) · [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)

**WC detection (locked):** `amiga_tournament_is_world_cup()` / catalog name `^World Cup\s+\S` — same as player WC slice and honours.

**Priority vocabulary (post-curation):**

| Tag | Meaning |
|-----|---------|
| **must-have** | Core ship set |
| **nice** | Ship with must-haves — same implementation track; not deferred |
| **defer** | Out of v1; may revisit |
| **cut** | Rejected for this table |

---

## 1. Executive summary

### What this answers

**“What happened at *this* World Cup?”** — one sortable row per WC tournament with identity, volume, texture, participation, geography, and light honours context. The grain is **the event**, not a calendar year and not a player career.

This complements:

| Surface | Grain | Question |
|---------|-------|----------|
| **Community WC year charts** (Activity) | Calendar year × realm (`world_cup` slice facts) | How much WC activity happened in year *Y* across the realm? |
| **This WC table** | One row per `tournament_id` where event is a World Cup | How wild / big / diverse was *World Cup XVII* specifically? |
| **Player WC LB** (`amiga_player_slice_*`) | Player × WC career cumulative | Who scored the most WC goals in their career? |
| **Tournament catalog index** (`amiga_tournament_catalog_stats`) | One row per *all* tournaments — minimal index cols | How big is this tournament vs others on the catalog page? |
| **Per-tournament page** (`tournament.php`) | Single event drill-down | Bracket, standings, game list |

### Product fact

There is almost always **exactly one World Cup per calendar year** (~23 WCs in catalog). A **one-row-per-WC** table aligns with “one WC per year” mentally. Year-level community charts remain useful for **cross-era trends** (WC share of all games, cumulative WC games over time); the WC table is the right home for **per-tournament texture** that year bars misrepresent (draw rate, goals/game, high-scoring rate *of that WC*).

### Relationship to community stats v2

Several WC questions were **cut** from the Activity chart catalog in favour of this table:

- **Q-WC-004** — WC tournaments per year (bar) → trivially 1 per year; table row count is the chart
- **Q-WC-005** — WC goals per year (count bar) → column on WC row; still feeds **Q-WC-011** via community year numerators
- **Q-WC-009** — WC draw rate per year → per-WC `draw_rate` column
- **Q-WC-010** — cumulative WC goals/game timeline → wrong lens; per-WC + optional sparkline link

**Shipped** community WC charts (**Q-WC-001**–**003**, **006**–**007**, **011**) stay: realm-year volume and share trends. This table **does not replace** them; it **absorbs** event-local metrics cut from charts.

### UI placement (locked Jun 2026)

**Canonical home:** **Amiga → World Cups hub → Tournament stats wing** — [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) WCH6.

| Option | Status |
|--------|--------|
| **A — Activity → World Cups sub-wing** | **Superseded** |
| **B — Leaderboards → World Cups tab** | **Superseded** for event table (player LB → hub wing 3) |
| **C — Dedicated `/amiga/world-cups/` hub** | **Locked** — wing 2 hosts this table |

Realm **year-level** WC charts (**Q-WC-001**–**003**, **006**–**007**, **011**) remain on **Activity**. Wing 2 may link to them.

### Implementation timing

Spec only. May ship **before**, **alongside**, or **after** community stats registry v2 (step 4). Storage-neutral below; **likely** store = one wide derived table keyed by `tournament_id` (WC rows only), written at tournament finalize when `E` is a World Cup.

---

## 2. Grain and keys

### Primary grain

| Decision | Rule |
|----------|------|
| **One row per** | `tournament_id` where `amiga_tournament_is_world_cup(tournaments.name)` |
| **Expected row count** | ~23 (WC I–XXIII); grows when new WCs finalize |
| **Primary key** | `tournament_id` (FK → `tournaments.id`) |
| **Not** one row per calendar year | Year is denormalized attribute; rare same-year edge cases (see §2.4) get distinct rows |

### Denormalized identity (on every row)

| Field | Source | Notes |
|-------|--------|-------|
| `tournament_id` | PK | Stable join key |
| `tournament_name` | `tournaments.name` | e.g. `World Cup XVII (Landskrona)` |
| `calendar_year` | `YEAR(tournaments.event_date)` | Same rule as community policy **H1** / **C11**; NULL `event_date` → row omitted or year NULL + UI “—” |
| `event_date` | `tournaments.event_date` | Sort default: **desc** (recent WC at top) |
| `event_chrono` | `tournaments.chrono` | Tie-break when same date; same-day non-WC events irrelevant |
| `host_country` | `tournaments.country` | Real nation (import corrections retire Access `WC` placeholder) |
| `host_city` | Parsed from name / `WORLD_CUP_VENUES` | Display only; not a slice key in v1 |

Optional display helpers (nice, not required for v1): `wc_ordinal` (1–23), `roman_label` (`XVII`), `era_bucket` (decade).

### Row semantics

Each row holds **intrinsic stats for that WC only** — games and aggregates scoped to `amiga_games` (rated) where `tournament_id = E`. Values are **fixed when the WC finalizes**; they do not change at later cutoffs.

### Time travel

| Rule | Behaviour |
|------|-----------|
| **Visibility** | Row included in TT result set iff WC’s `(event_date, event_chrono, tournament_id)` ≤ cutoff tuple |
| **Cell values** | Unchanged — still that WC’s own stats (not “WC games through cutoff”) |
| **Empty state** | Before first WC in history: zero rows |

Contrast: community `world_cup` year facts at cutoff are **cumulative realm state through cutoff**; this table filters **which WCs exist** by cutoff.

### 2.4 Edge cases

| Case | Handling |
|------|----------|
| **COVID / scheduling** | If two WCs share a calendar year, **two rows** same `calendar_year` — table sort by `event_date` / `chrono`, not year alone |
| **WC V KOA Cup** | Not a separate catalog WC — phases under WC V parent ([`amiga-import-layer.md`](amiga-import-layer.md)); stats roll into WC V row |
| **Unfinalized / draft WC** | No row until tournament finalize (same as catalog stats) |
| **Zero rated games** | Row may still exist (identity + zeros) if WC finalized with structure but no rated games — product choice: show or hide; recommend **show** with `rated_games = 0` |

### Likely storage shape (proposal — not DDL)

| Object | Role |
|--------|------|
| **`amiga_world_cup_stats`** (name TBD) | One row per WC `tournament_id`; wide metric columns |
| **No snapshot table** | Row immutable after WC finalize — unlike community headline snapshots |
| **No TT duplicate rows** | TT = filter on existing rows |

Register in [`amiga-data-contract.md`](amiga-data-contract.md) when implementation ships.

---

## 3. Column inventory

**Legend**

- **Derive or store:** `store` = persist numerator or scalar at finalize; `derive` = compute at read from stored numerators; `read` = compute from other stored cols in same row
- **Also feeds community?** Links to question catalog ID if the same game pass can feed community `world_cup` year facts
- **Player slice overlap?** Y if `amiga_player_slice_*` already exposes the same *player-career* question (event table still OK for realm totals)

Counts use **rated games** (`amiga_games` joined to `amiga_game_ratings`) unless noted.

**Texture definitions** (match community headline / v2 catalog):

- **Draw:** `actual_score = 0.5`
- **Decided:** non-draw games
- **DD (double-digit):** `dd_player_a + dd_player_b` per game (participant-slot count, not “DD wins”)
- **Clean sheet:** `cs_player_a + cs_player_b` per game
- **High-scoring:** `sum_of_goals >= 10`

---

### 3.1 Identity

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `tournament_id` | Which World Cup is this? | identity | tournament | store | N | N | **must-have** |
| `tournament_name` | What is it called? | identity | tournament | store (denorm) | N | N | **must-have** |
| `calendar_year` | Which calendar year? | identity | tournament | store (denorm) | Y — year key for **Q-WC-001**–**011** aggregation | N | **must-have** |
| `event_date` | When did it take place? | identity | tournament | store (denorm) | N | N | **must-have** |
| `event_chrono` | Sort order among same-day events? | identity | tournament | store (denorm) | N | N | **must-have** |
| `host_country` | Which nation hosted? | identity | tournament | store (denorm) | Y — host_country slice family | N | **must-have** |
| `host_city` | Which city hosted? | identity | tournament | store (denorm) | N | N | nice |
| `wc_ordinal` | Which numbered WC (1–23)? | identity | tournament | derive or store | N | N | nice |
| `roman_label` | Roman numeral label (XVII, …)? | identity | tournament | derive from name | N | N | defer |
| `days_span` | How many calendar days did rated play span? | identity | tournament | derive from min/max `game_date` | N | N | defer |
| `finalized_at` | When was rating finalize committed? | identity | tournament | store (denorm) | N | N | defer |

---

### 3.2 Volume — match counts

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `rated_games` | How many rated games in this WC? | volume | game | store | Y — **Q-WC-001** (year sum) | Y — sum of player `games` ≠ this (career) | **must-have** |
| `decided_games` | How many non-draw games? | volume | game | store or derive (`games − draws`) | N | N | **must-have** |
| `draws` | How many draws? | volume | game | store | Y — realm `draws` year fact feeds **Q-TEX-006**; WC-specific draw count not in community year facts today | N | **must-have** |
| `goals` | Total goals scored in WC games? | volume | game | store | Y — **Q-WC-005** numerator for **Q-WC-011** | N | **must-have** |
| `double_digit_slots` | How many DD participant-slots in games? | volume | participant-slot | store | Y — same pass as realm `double_digits` (**Q-TEX-008**) | Y — player slice DD wins differ | nice |
| `clean_sheet_slots` | How many CS participant-slots? | volume | participant-slot | store | Y — realm `clean_sheets` (**Q-TEX-009**) | Y — player slice CS wins differ | nice |
| `high_scoring_games` | How many games with goal sum ≥ 10? | volume | game | store | Y — realm `high_scoring_games` (**Q-TEX-013**) | N | **must-have** |
| `nil_nil_draws` | How many 0–0 draws? | volume | game | store | N (**Q-TEX-011** cut at realm) | N | **cut** |
| `one_goal_games` | How many games with exactly 1 total goal? | volume | game | store | N | N | **cut** |
| `low_scoring_games` | How many games with goal sum ≤ 3? | volume | game | store | N | N | nice |
| `blowout_games` | How many games with margin ≥ 5? | volume | game | store | N | N | nice |
| `total_home_goals` | Goals by designated home side? | volume | game | defer | N | N | **defer** — no stable home/away in KO |
| `knockout_games` | Rated games in knockout phases? | volume | game | store | N | N | nice |
| `group_games` | Rated games in group/league phases? | volume | game | store or derive | N | N | nice |
| `koa_cup_games` | Rated games in KOA / consolation phases? | volume | game | store | N | N | defer |

---

### 3.3 Texture — rates and averages

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `goals_per_game` | Average goals per game in this WC? | texture | game | derive (`goals ÷ rated_games`) | Y — **Q-WC-011** is year-local; this is event-local | N | **must-have** |
| `draw_rate` | Share of games that were draws? | texture | game | derive | Y — **Q-WC-009** cut as year chart; event home | N | **must-have** |
| `decided_rate` | Share of games with a winner? | texture | game | derive | N | N | nice |
| `double_digit_rate` | DD slots per game? | texture | game | derive (`dd_slots ÷ rated_games`) | Y — parallel to **Q-TEX-008** | N | nice |
| `clean_sheet_rate` | CS slots per game? | texture | game | derive | Y — parallel to **Q-TEX-009** | N | nice |
| `high_scoring_rate` | Share of games with sum ≥ 10? | texture | game | derive | Y — parallel to **Q-TEX-013** | N | **must-have** |
| `low_scoring_rate` | Share of games with goal sum ≤ 3? | texture | game | derive (`low_scoring_games ÷ rated_games`) | N | N | nice |
| `nil_nil_rate` | Share of games that were 0–0? | texture | game | derive | N | N | **cut** |
| `goals_per_decided_game` | Goals per non-draw game? | texture | game | derive | N | N | defer |
| `avg_margin_decided` | Average goal margin in decided games? | texture | game | derive | N | N | defer |
| `median_goal_sum` | Typical total goals per game? | texture | game | store or derive | N | N | defer |
| `points_per_game` | Avg match points per game (3/1/0)? | texture | game | derive | N | Y — player Pts/g on slice | **cut** |

---

### 3.4 Participation

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `distinct_players` | How many different players appeared? | participation | participant | store | Y — **Q-WC-007** (year) | Y — slice is per-player not count | **must-have** |
| `distinct_player_nationalities` | How many player countries represented? | participation | participant | store | Y — **Q-WC-006** (year) | N | **must-have** |
| `standing_entrants` | Players on standings / supplement? | participation | participant | store from participation | N | N | **cut** — same as `distinct_players` in practice |
| `avg_games_per_player` | Mean games per participant? | participation | participant | derive | N | N | nice |
| `median_games_per_player` | Typical player workload? | participation | participant | store or derive | N | N | defer |
| `max_games_one_player` | Heaviest single-player schedule? | participation | participant | store | N | Y — player `games` in slice | nice |
| `players_one_game_only` | How many players played exactly once? | participation | participant | store | N | N | defer |
| `debut_players` | First-ever rated game in this WC? | participation | participant | store | Y — realm `player_debuts` year (**Q-SHP-009**) | N | defer |
| `returning_wc_players` | Players who had prior WC games? | participation | participant | store | N | Y — `tournaments_played > 1` on slice | defer |
| `first_time_wc_players` | Players whose first WC was this one? | participation | participant | store | N | Y — slice `tournaments_played` at event | nice |
| `distinct_opponent_pairs` | Unique matchups in this WC? | participation | game | store | N | N | nice |
| `avg_opponents_per_player` | Mean distinct opponents per entrant? | participation | participant | derive | N | N | nice |

---

### 3.5 Geography

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `host_country` | (see identity) | geography | tournament | store | Y — **Q-GEO-001**–**004** host slices | N | **must-have** |
| `distinct_host_country_players` | How many distinct players from the host country? | geography | participant | store | N | N | nice |
| `distinct_guest_players` | How many distinct players **not** from the host country (guests)? | geography | participant | store | N | N | nice |
| `guest_player_share` | Share of participants who were guests (non-host nationals)? | geography | participant | derive (`distinct_guest_players ÷ distinct_players`) | N | N | nice |
| `share_games_home_country` | Share of WC games “at home” for host nation players? | geography | game | derive | N | N | defer — fuzzy definition |
| `top_nationality_by_games` | Which country supplied the most player-games? | geography | participant | store + id | N | N | defer |
| `top_nationality_games_count` | How many player-games from top country? | geography | participant | store | N | N | defer |
| `nationalities_with_one_player` | Countries with exactly one entrant? | geography | participant | store | N | N | defer |
| `continent_count` | How many continents represented? | geography | participant | store | N | N | defer — needs country→continent map |
| `foreign_player_share` | Share of **player-games** by non-host nationals? | geography | participant-slot | derive | N | N | defer — game-slot basis; prefer `guest_player_share` (distinct players) |
| `distinct_opponent_countries_pairs` | Unique nationality pairings in games? | geography | game | store | N | N | nice |

---

### 3.6 Structure and catalog overlap

Reuse or extend [`amiga_tournament_catalog_stats`](amiga-data-contract.md) semantics where the question is “how is this tournament shaped?” — avoid duplicating on WC table if catalog row suffices for generic tournament index.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `standing_players` | Standings rows / entrant count? | volume | tournament | read from catalog stats | N | N | nice — **join catalog** instead of duplicate |
| `league_scopes` | Group / league tables count? | volume | tournament | read from catalog | N | N | defer |
| `knockout_ties` | Knockout ties count? | volume | tournament | read from catalog | N | N | defer |
| `has_koa_phases` | Includes consolation bracket? | identity | tournament | store flag | N | N | defer |
| `group_stage_game_share` | % games in group phase? | texture | game | derive | N | N | defer |
| `knockout_game_share` | % games in KO phase? | texture | game | derive | N | N | defer |

**Recommendation:** WC table **does not copy** catalog index columns — UI joins `amiga_tournament_catalog_stats` when needed. Catalog stats stay generic (all tournaments).

---

### 3.7 Honours-adjacent (event grain only)

Podium **players** are single-valued per medal; counts of **medals** at realm level are trivial (0–1 gold per WC). Store **holder ids** for display links, not leaderboard competition.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `gold_player_id` | Who won? | honours-adjacent | tournament | store | N | Y — slice `gold` | nice |
| `silver_player_id` | Runner-up? | honours-adjacent | tournament | store | N | Y — slice `silver` | nice |
| `bronze_player_id` | Third place? | honours-adjacent | tournament | store | N | Y — slice `bronze` | nice |
| `podium_nationalities` | Countries of medalists? | honours-adjacent | tournament | derive from players | N | N | defer |
| `champion_game_count` | How many games did the winner play? | honours-adjacent | participant | store | N | Y — per-player | nice |
| `champion_goals_for` | Winner goals in this WC? | honours-adjacent | participant | store | N | Y — slice GF | defer |
| `medalists_combined_goals` | Total goals by podium three? | honours-adjacent | participant | store | N | N | defer |
| `fourth_place_player_id` | Fourth place (if derivable)? | honours-adjacent | tournament | store | N | N | **defer** — finish taxonomy blocked ([WC LB policy](amiga-world-cups-leaderboard-policy.md) WC5) |
| `final_was_draw` | Was the final a draw before tie-break? | honours-adjacent | game | store flag | N | N | defer |

**Boundary:** Career WC honours leaderboard (`gold`, `silver`, `bronze` counts per player) stays on **`amiga_player_slice_*`** — not duplicated as realm aggregates.

---

### 3.8 Extremes and single-game records (HoF overlap)

Realm **Hall of Fame** holds all-time single-game extremes with `game_id` holders ([`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)). WC table may show **this WC’s** extrema for table sort/filter; **all-time** records stay HoF.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `highest_goal_sum` | Highest-scoring game total in this WC? | volume | game | store | N | N — HoF `BiggestSumOfGoals` | nice |
| `highest_goal_sum_game_id` | Which game? | identity | game | store | N | HoF overlap | nice |
| `lowest_goal_sum` | Lowest-scoring game? | volume | game | store | N | HoF `SmallestSumOfGoals` | nice |
| `lowest_goal_sum_game_id` | Which game? | identity | game | store | N | HoF overlap | nice |
| `biggest_margin` | Largest win margin in this WC? | volume | game | store | N | HoF `BiggestWinDifference` | nice |
| `biggest_margin_game_id` | Which game? | identity | game | store | N | HoF overlap | nice |
| `highest_scoring_draw_sum` | Highest total goals in a draw? | volume | game | store | N | HoF `BiggestDrawSum` | nice |
| `highest_scoring_draw_game_id` | Which game? | identity | game | store | N | HoF overlap | nice |
| `most_goals_one_player_game` | Most goals by one player in a game? | volume | participant | store | N | N | nice |
| `most_goals_one_player_game_id` | Which game? | identity | game | store | N | N | nice |

**Curation hint:** If UI links to HoF for “all-time”, store only **scalar** extrema on WC row; game ids optional.

---

### 3.9 Comparative / era ranks

Ranks are **across all WCs** — stable once later WCs exist. Prefer **read-time rank** from full table unless sort performance requires stored rank.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `rank_goals_per_game` | Where does this WC rank for goals/game? | derived rate | tournament | read-time rank | N | N | nice |
| `rank_high_scoring_rate` | Rank for wildness (≥10 rate)? | derived rate | tournament | read-time rank | N | N | nice |
| `rank_draw_rate` | Rank for draw rate? | derived rate | tournament | read-time rank | N | N | nice |
| `rank_rated_games` | Rank for size (game count)? | volume | tournament | read-time rank | N | N | nice |
| `rank_distinct_players` | Rank for participation breadth? | participation | tournament | read-time rank | N | N | nice |
| `rank_goals_total` | Rank for total goals? | volume | tournament | read-time rank | N | N | nice |
| `pctile_goals_per_game` | Percentile vs all WCs? | derived rate | tournament | read-time | N | N | defer |
| `z_score_goals_per_game` | Standardized vs historical mean? | derived rate | tournament | read-time | N | N | defer |
| `era_decade` | 1990s / 2000s / … bucket? | identity | tournament | derive | N | N | **cut** |
| `vs_prev_wc_delta_gpg` | Change in goals/game vs previous WC? | derived rate | tournament | read-time | N | N | **cut** |
| `vs_prev_wc_delta_games` | Change in game count vs previous WC? | volume | tournament | read-time | N | N | **cut** |

**TT note:** Ranks at cutoff use only WCs ≤ cutoff (recompute or store per-era snapshot — prefer recompute at read for v1).

---

### 3.10 Realm context (single WC vs all games that year)

Useful when two events share a year or when WC is small fraction of annual volume.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `share_of_year_games` | This WC as % of all rated games in `calendar_year`? | derived rate | game | derive | Y — same ratio as **Q-WC-003** (year chart); per-WC row lens | N | nice |

**Q-WC-003 vs `share_of_year_games`:** Not a contradiction. **Q-WC-003** is the Activity **year bar chart** (realm-wide: “in year *Y*, what % of all games were WC?”). **`share_of_year_games`** on each WC row is the **same ratio** shown in context on that tournament’s row (`rated_games ÷ realm games in calendar_year`). Numerator is this WC; denominator is community/year fact. Store on WC row for table sort/tooltip; chart stays on community facts.

| Column / metric | Plain-language question | Category | Unit / basis | Derive or store | Also feeds community? | Player slice overlap? | Priority |
|---------------|-------------------------|----------|--------------|-----------------|----------------------|----------------------|----------|
| `share_of_year_goals` | WC goals as % of year goals? | derived rate | game | derive | N | N | defer |
| `realm_games_same_year` | Total realm games in that year? | volume | game | read from community facts or derive | Y — **Q-VOL-001** | N | defer — duplicate of community |
| `wc_games_rank_in_year` | If multiple WCs in year, which sequence? | identity | tournament | store | N | N | defer |

---

### 3.11 Ship set (must-have + nice)

**Implementation scope:** all **must-have** and **nice** rows in §3.1–§3.10. **Exclude** only **cut** and **defer**.

| Tier | Count (approx.) | Role |
|------|-----------------|------|
| **must-have** | ~18 | Identity, core volume/texture/participation |
| **nice** | ~45 | Full table product — ship together with must-haves |
| **defer** | remainder | Later |
| **cut** | see §3.12 | Do not implement |

**Must-have columns:**

**Identity:** `tournament_id`, `tournament_name`, `calendar_year`, `event_date`, `event_chrono`, `host_country`  
**Volume:** `rated_games`, `decided_games`, `draws`, `goals`, `high_scoring_games`  
**Texture:** `goals_per_game`, `draw_rate`, `high_scoring_rate`  
**Participation:** `distinct_players`, `distinct_player_nationalities`

**Nice columns (non-exhaustive):** all other inventory rows tagged **nice** — including geography guests/hosts, extremes, ranks, honours ids, phase splits, `share_of_year_games`, etc.

---

### 3.12 Cut log (Dagh curation pass 1)

| Column | Reason |
|--------|--------|
| `nil_nil_draws` | Low product value vs other texture cols |
| `one_goal_games` | Cut |
| `nil_nil_rate` | Cut (pairs with nil-nil counts) |
| `points_per_game` | Player slice / results LB territory |
| `standing_entrants` | Same as `distinct_players` in practice |
| `era_decade` | Cut |
| `vs_prev_wc_delta_games` | Cut |
| `vs_prev_wc_delta_gpg` | Cut — sequential WC deltas are noise; sort columns or Activity year charts instead (Dagh Jun 2026) |


### 3.13 UI sub-wings (shipped Jun 2026)

Wing 2 uses **five sortable tables** under `/amiga/world-cups/stats/` — shared anchor (**Tournament · Year · Players · Games**), count/rate pairs on the same table, horizontal scroll per table.

| Sub-wing | Path | Stat columns (after anchor) |
|----------|------|-----------------------------|
| **Goals** | `stats/index.php` | Goals, G/G, High + High %, Low + Low %, Blowouts + Blowout %, Draw %, Max/Min sum, Max draw, Max margin, Max player goals (peaks link to game) |
| **DDs & CSs** | `stats/dds.php` | DDs + DD %, CSs + CS % |
| **Participation** | `stats/participation.php` | Players → **1st WC** → Games, Matchups, G/player, Opp/player, Champ g, Group, KO, **Year %** |
| **Geography** | `stats/geography.php` | Nations, Guests, Host players, Guest %, Nation pairs, **Intl games**, **Intl %** |
| **Podium** | `stats/podium.php` | Gold, Silver, Bronze |

**UI omit (still stored):** `draws`, `decided_games`, `decided_rate`, `max_games_one_player`, identity host/city/date cols.

**Geography intl (shipped Jun 2026):** `international_games` (rated games where both nations set and differ) + `international_game_share`; DDL `038`. **Blowout rate:** stored `blowout_rate` in same migration.

**Layout (Jun 2026):** Goals wing = scroll mirror (`width: 100%`); narrow wings = standard `k2-table-wrap` with `min-width: 100%` table (same habit as tournaments list). Sortable tables use **`ranked-table-pending`** + scoped cloak until `k2-table.js` init — same pattern as hub leaderboards, unlike the first WC stats cut which painted during JS anchor/sort setup.

**Podium v2 (backlog):** full placement table from standings — site-unique collection view.

---

## 4. Rejected / wrong grain

Explicit **do not store here** (belongs elsewhere):

| Question / metric | Correct home | Why |
|-------------------|--------------|-----|
| Cumulative WC games over time | Community snapshot **Q-WC-002** | L2 cumulative lens |
| WC games per calendar year (bar) | Community facts `world_cup` + **Q-WC-001** | Year × realm chart — not per-event column |
| Distinct nations / players in WC **per year** | Community **Q-WC-006**, **Q-WC-007** | Year aggregation; table is per WC |
| Player career WC goals, W/D/L, points | `amiga_player_slice_*` + WC LB | Player grain |
| Most WC golds in career | Player slice honours LB | Player grain |
| All-time highest-scoring game ever | `amiga_generalstats` / realm snapshots | HoF record book |
| All-time WC draw rate across all years | Community headline / year facts | Realm cumulative |
| Games with goal sum N histogram | Community **Q-SHP-005** (L4 probe) | Distribution at cutoff, not per WC |
| Players in exactly N World Cups histogram | Community **Q-SHP-004** | Player distribution |
| Tournament list game_count for all events | `amiga_tournament_catalog_stats` | Generic catalog index |
| Bracket / standings detail | `tournament.php` + standings tables | Page grain |
| WC texture **cumulative** line after each event | Community snapshot (cut **Q-WC-010**) | Wrong lens for event table |
| Kitchen vs open tournament mix | Community event ecosystem (cut) | Not WC-specific |
| Match streaks | **Skip** Amiga-wide | [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks |
| Per-game Elo / rating movement | `amiga_game_ratings` | Game grain |

---

## 5. Writer sketch (conceptual)

### Trigger

On **tournament finalize** when `E` is a World Cup (`is_world_cup_tournament(name)`):

```text
1. Load all rated games for tournament_id = E (in-memory pool from finalize — same as slice_totals)
2. Single pass: accumulate volume, texture numerators, participation sets, geography sets, phase splits, extrema
3. UPSERT one row in amiga_world_cup_stats keyed by tournament_id = E
4. (Same finalize) community writer may increment world_cup year facts for YEAR(E.event_date)
   — share numerators where metric definitions match (games, goals, distinct players, …)
```

### Shared pass with community stats

| WC table column | Community `world_cup` year fact (if registry v2 ships) |
|-----------------|--------------------------------------------------------|
| `rated_games` | `games` / year / `world_cup` → **Q-WC-001** |
| `goals` | `goals` / year → **Q-WC-005**, **Q-WC-011** |
| `distinct_players` | `active_players` or dedicated key → **Q-WC-007** |
| `distinct_player_nationalities` | `distinct_nationalities` → **Q-WC-006** |
| `draws`, `high_scoring_games`, … | Realm-wide year facts only unless product adds WC-specific year numerators |

**Rule:** One game loop in finalize should feed **both** WC row (event scope) and community facts (year scope) where definitions align — no second scan of `amiga_games`.

### Idempotency and repair

Per [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md):

- Writers run only at finalize + full `prove` replay
- Wrong row → **`python -m scripts.amiga prove`**, not ad-hoc SQL
- Verify oracle: recompute WC row from `amiga_games` + `amiga_game_ratings` for sample tournaments; compare to stored row
- PHP finalize parity when community PHP path exists

### Refinalize

If tournament `E` is refinalized, recompute WC row for `E` only. Later WCs unchanged. Community year facts for `YEAR(E)` may need same refinalize pass.

---

## 6. UI sketch (light)

### Primary surface

- **Sortable table**, one WC per row, default sort `event_date DESC` (recent at top)
- **Sticky identity columns:** year, name (link to `tournament.php`), host country
- **Hero numeric columns:** games, goals, goals/game, high-scoring rate, draw rate, players, nations
- **Column picker** optional in v2 — default columns = must-have + selected nice
- **Time travel:** filter rows to WCs ≤ cutoff; banner same as Activity/LB
- **Footnote:** texture definitions (DD/CS/high-scoring) match community Activity tooltips

### Optional enhancements

| Enhancement | Notes |
|-------------|-------|
| Sparkline / link | “See WC games per year” → scroll to **Q-WC-001** chart in same sub-wing |
| Row expand | Medalists + extreme game links |
| Era band | Subtle decade grouping in table |
| Highlight | Top-3 goals/game WCs with calm accent (design-direction) |
| Export | Defer |

### COVID / two-WCs-one-year

Show **both rows** with same `calendar_year`; disambiguate by `tournament_name` / `event_date`. Do not merge into one year row. Year-level charts may show **two** WC contributions in one bar — table clarifies per event.

---

## 7. Cross-links and registry v2 impact

### Related docs

| Doc | Relationship |
|-----|----------------|
| [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) | Backlog origin; cut WC charts; shipped year charts remain |
| [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) | Step 4 registry; WC table is parallel track |
| [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) | Community shape unchanged; WC table is new grain |
| [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) | Player slice — no duplication |
| [`amiga-data-contract.md`](amiga-data-contract.md) | Register table at implementation |
| [`amiga-tournament-catalog-stats`](amiga-data-contract.md) | Join for index cols; no WC duplication |
| [`amiga-import-layer.md`](amiga-import-layer.md) | Host city/country corrections |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Row visibility filter |

### Registry v2 (step 4) — what stays vs moves

| Community registry item | After WC table spec |
|-------------------------|---------------------|
| **C5 — World Cup slice** year facts: `games`, `goals`, `active_players`, `distinct_nationalities` | **Keep** — feeds shipped year charts **Q-WC-001**, **003**, **006**, **007**, **011** |
| WC year numerators only for cut charts (`draw_rate`, goals count bar) | **Optional** — realm year `draws` already needed for **Q-TEX-006**; WC-specific draw year fact **not** required if table ships |
| **Q-WC-004**, **009**, **010** | **Drop** from chart registry — table columns replace |
| New DDL for WC table | **Separate** from `amiga_community_stat_facts` — event grain wide table |
| Implementation order | Parent chat decision: (1) registry v2 + year charts, (2) WC table, or parallel writers in one finalize module |

### Suggested handoff to parent community-stats chat

1. **Ship set locked:** all **must-have** + **nice** columns (§3.11); **cut** and **defer** excluded.  
2. Lock table name + DDL column list from curated inventory.  
3. Add implementation plan slice: DDL → finalize writer (shared game pass with community `world_cup` year facts) → verify oracle → Activity UI table.  
4. Confirm year charts still ship from community facts (**Q-WC-003** chart + **`share_of_year_games`** per-WC column are the same ratio, different surfaces).  
5. Registry v2 can proceed in parallel — C5 year facts unchanged.

---

## Revision log

| When | What |
|------|------|
| 2026-06 | **Dagh curation pass 1** — priorities locked; must-have + nice = ship set; cut log §3.12; guest/host player counts; `low_scoring_*` (≤3); Q-WC-003 / `share_of_year_games` clarified |
| 2026-06 | Initial wide inventory — product spec for per-WC table |
