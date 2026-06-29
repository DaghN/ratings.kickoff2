# Amiga World Cups — country slice (WC career by nation)

**Status:** **Policy locked** (Jun 2026-24) — **shipped** Jun 2026-24 (DDL `040`, Python + PHP writers, hub wing 4 UI, `verify-country-slice` in `prove`).

**Parent:** [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) · [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)

**Related:** [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H6–H8 country tokens) · [`amiga-performance-rating.md`](amiga-performance-rating.md) (TPR formula) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) (network semantics) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 (stored truth) · [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) (career roster by nationality — sibling surface) · **Implementation:** [`amiga-world-cups-country-slice-implementation-plan.md`](amiga-world-cups-country-slice-implementation-plan.md)

---

## 1. Executive summary

Add **Wing 4 — Country stats** on the Amiga **World Cups** hub — beside **Player stats** (wing 3). Same **five sub-wings** as player WC stats:

| # | Sub-wing | Grain |
|---|----------|-------|
| 1 | **Honours** | Nation × WC career |
| 2 | **Results** | Nation × WC career |
| 3 | **Goals** | Nation × WC career |
| 4 | **DDs & CSs** | Nation × WC career |
| 5 | **Opponents** | Nation × WC career |

**Rows** = countries (plus an **`Unknown`** bucket for blank nationality). **Columns** mirror the player WC tables where sensible, with nation-level roll-up rules (mostly **sums** of player slice rows, **max-of-player-maxes** for goal extremes, **set unions** for Opponents network counts).

**Extra metrics** (participation depth, domestic/international texture, realm shares, win rate, performance rating, opponent average rating) are **in scope**; **precise column placement per sub-wing is deferred** — this doc catalogs definitions only.

**Storage:** new stored-truth tables `amiga_country_slice_totals` (+ `amiga_country_slice_at_event` for time travel). **No** live aggregation from `amiga_games` on read paths.

**Holy loop:** DDL → Python finalize writer → PHP finalize parity → `verify_country_slice` oracles → `prove` green → UI.

---

## 2. Problem statement

Player WC stats answer *who dominated?* Country stats answer *which nations dominated?* — the same product vocabulary (honours, results, goals, DD/CS texture, opponent network) at **nation grain**, using **player-games** accounting (Italy vs Italy counts twice in games) and **national medal haul** (sum all players’ medals).

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WCCS1** | **Hub wing** | **Wing 4 — Country stats** under `/amiga/world-cups/countries/*` (exact routes at implementation). |
| **WCCS2** | **Six sub-wings** | Honours · Results · Participation · Goals · DDs & CSs · Opponents. |
| **WCCS3** | **Row key** | `country_token` — `TRIM(amiga_players.country)` when non-empty ([**H8**](amiga-hof-tournament-geo-policy.md)); empty/NULL → literal **`Unknown`**. |
| **WCCS4** | **Eligibility** | Rows where **≥1 distinct player** from that token has WC slice `tournaments_played ≥ 1` (equivalent to ≥1 WC player-game in practice). **`Unknown`** row when any such player has blank country. |
| **WCCS5** | **Leading columns** | **Rank · Country** (flag when mapped) · **Players** (distinct WC participants) · data columns. **No Elo.** |
| **WCCS6** | **Honours medals** | **Sum** all podium medals of nationals — two Italians on the same podium both count toward Italy. |
| **WCCS7** | **Games accounting** | **Player-games** — each rated WC game contributes **once per participating national** on that side. Italy vs Italy = **2** toward Italy’s games. |
| **WCCS8** | **Goal extremes** | **Max across players** — country `most_goals_scored` = max of each national’s WC `most_goals_scored`; same for Max GA, Max win, Max loss, Max sum, Max draw. |
| **WCCS9** | **Opponents network** | **Set unions at country grain** from WC games — **not** sum of per-player network counts (avoids double-counting the same opponent faced by two nationals). |
| **WCCS10** | **DD / CS victims** | **No win required** — match player slice ops: DD victim = opponent when a national scored **≥10**; CS victim = opponent when a national **conceded 0**. See §5.5. |
| **WCCS11** | **Victims (W column)** | **Win required** — distinct players beaten by any national (same as player `different_victims`). |
| **WCCS12** | **Own country in faced** | `opponent_countries_faced` includes **own country** when two nationals play each other ([**H6**](amiga-hof-tournament-geo-policy.md) at nation grain). |
| **WCCS13** | **Beaten countries** | `opponent_countries_beaten` = nations with ≥1 **loss** to a national in a WC game — **no** own-country seed ([**H7**](amiga-hof-tournament-geo-policy.md)). |
| **WCCS14** | **Performance rating** | Lives on **Results** sub-wing — chess-style TPR treating the nation as one virtual player over all WC **player-games** (§5.2). |
| **WCCS15** | **Opponent avg rating** | Arithmetic mean of **frozen opponent ratings** across all national **player-games** — Results sub-wing (§5.2). |
| **WCCS16** | **Win rate** | `(wins + 0.5 × draws) ÷ games` on summed W/D/L — draw counts as half a win. |
| **WCCS17** | **Pts / WC (realm)** | Total national match points ÷ **count of all WC tournaments in the realm** at cutoff — not ÷ “WCs with nationals”. |
| **WCCS18** | **Realm shares** | `games_share` = national games ÷ realm WC player-games; `goals_share` = national GF ÷ realm WC GF — both stored at finalize. |
| **WCCS19** | **Rejected metrics** | Medals/podium/pts **per participation**; representation rate; single-WC peaks; “top national” player link. |
| **WCCS20** | **Column placement** | **Deferred** — metric catalog in §5 is authoritative; UI column order decided at implementation. |
| **WCCS21** | **No LB mirror** | **Hub only** for v1 — WC player + country career stats live under World Cups hub, not Leaderboards (same pattern as career Countries hub). |
| **WCCS22** | **Time travel** | Every stored column on **`country_slice_at_event`**; reads at cutoff = latest row ≤ cutoff (same pattern as player slice). |
| **WCCS23** | **WC detection** | `is_world_cup_tournament()` / name `^World Cup\s+\S` — same as player slice. |
| **WCCS24** | **HoF / rise dates** | **Out of scope** for v1 — no new HoF rows unless follow-on slice asks. |

---

## 4. Roll-up cheat sheet (Italy example)

| Metric kind | Rule |
|-------------|------|
| Honours WCs | **Distinct** WC tournaments with ≥1 Italian participant |
| Medals, W/D/L, Pts, GF, GA, DD/CS counts (incl. conceded) | **Sum** across all Italian player slice rows |
| Games | **Sum** of Italians’ `games` (double-counts Italy–Italy) |
| Per-game ratios (Pts/g, GF/g, DD ratio, …) | Numerator sum ÷ **Italian games sum** |
| Goal ratio | Sum GF ÷ sum GA |
| Max GF … Max draw | **Max** of each Italian’s stored extreme |
| Opponents / victims / DD·CS victims / geo faced·beaten | **Union** of sets built from WC games (§6.2) |
| Player count | Distinct Italian `player_id` with WC participation |
| WC participations | Σ (Italians in WC *X*) = sum of per-WC Italian headcounts |
| Domestic games | Player-games where **both** sides’ country token = Italy |
| International games | Player-games where opponent country token **≠** Italy |

---

## 5. Column catalog

**Naming:** `snake_case` on slice rows. **UI labels** follow player WC tables where listed.

**Registry:** extend `scripts/amiga/slice_columns.py` (or sibling `country_slice_columns.py`) at implementation.

### 5.1 Shared / participation metrics

| Slice column | UI label (proposed) | Definition |
|--------------|---------------------|------------|
| `players` | Players | Distinct nationals with `tournaments_played ≥ 1` on player WC slice |
| `wc_participations` | WC participations | Σ per-WC headcount of nationals (one Italian × one WC = 1; seven Italians × one WC = 7) |
| `wc_participations_per_player` | Participations / player | `wc_participations ÷ players` |
| `games_per_player` | Games / player | `games ÷ players` |
| `domestic_games` | Domestic games | Player-games where both competitors share this `country_token` |
| `domestic_game_share` | Domestic share | `domestic_games ÷ games` |
| `international_games` | International games | Player-games vs opponent with **different** country token |
| `international_game_share` | International share | `international_games ÷ games` |
| `games_share` | Game share | National `games ÷ realm_wc_player_games` |
| `goals_share` | Goal share | National `goals_for ÷ realm_wc_goals_for` |

**Realm denominators** (`realm_wc_player_games`, `realm_wc_goals_for`, `realm_wc_tournament_count`) computed once per finalize snapshot and stored on each country row (or equivalent realm metadata row — implementer choice; verify must use same source).

### 5.2 Honours sub-wing

| Slice column | UI label | Definition |
|--------------|----------|------------|
| `tournaments_with_nation` | WCs | Distinct WC tournaments with ≥1 national participant |
| `wc_participations` | WC entries | Σ per-WC headcount of nationals (same grain as Countries index **WC entries**) |
| `gold` | Gold | Sum of nationals’ WC `gold` |
| `silver` | Silver | Sum of nationals’ WC `silver` |
| `bronze` | Bronze | Sum of nationals’ WC `bronze` |
| `podiums` | Podiums | `gold + silver + bronze` |

### 5.3 Results sub-wing

| Slice column | UI label | Definition |
|--------------|----------|------------|
| `tournaments_with_nation` | WCs | Same as Honours |
| `games` | Games | Sum of nationals’ WC `games` |
| `wins` | W | Sum of nationals’ `wins` |
| `draws` | D | Sum of nationals’ `draws` |
| `losses` | L | Sum of nationals’ `losses` |
| `points` | Pts | Sum of nationals’ WC match points (3/1/0) |
| `points_per_game` | Pts/g | `points ÷ games` — **PHP-derived OK** |
| `points_per_realm_wc` | Pts / WC | `points ÷ realm_wc_tournament_count` |
| `win_rate` | Win rate | `(wins + 0.5 × draws) ÷ games` |
| `average_opponent_rating` | Avg opp. rating | `Σ frozen_opponent_rating ÷ games` over all national player-games |
| `performance_rating` | Perf. rating | Chess-style TPR (§5.3.1) |

### 5.3a Participation sub-wing

**Path:** `…/participation.php` — default sort **Entries** desc. Prefix **Rank · Country · Players · WCs · Games**; then §5.1 participation / geography / realm-share columns (`wc_participations` → **Entries**).

#### 5.3.1 Performance rating (nation)

Treat the country as **one virtual player** across all WC rated **player-games** by its nationals.

For each player-game *g* by national *P* from country *C*:

| Symbol | Source |
|--------|--------|
| `s_g` | `actual_score` from `amiga_game_ratings` (A side) or `1 − actual_score` (B side) |
| `R_opp_g` | Opponent’s **frozen** rating on the same row (`rating_b` / `rating_a`) |

Find `R_perf` such that `Σ_g E(R_perf, R_opp_g) = Σ_g s_g` with the same logistic as [`amiga-performance-rating.md`](amiga-performance-rating.md).

| Rule | Behaviour |
|------|-----------|
| **Minimum games** | `games ≥ 2` else NULL |
| **Perfect 0% or 100%** | NULL; UI shows **∞** on Results sub-wing only for **perfect win** (all wins, ≥2 games, no draws) |
| **Domestic games** | Included — opponent = other national’s frozen rating |
| **Scope** | All WC games in realm history at cutoff |

**Writer:** same `solve_performance_rating` / `performance_rating_from_pairs` as event TPR — [`scripts/amiga/performance_rating.py`](../scripts/amiga/performance_rating.py).

#### 5.3.2 Average opponent rating

`average_opponent_rating = round(Σ R_opp_g / games, …)` — **not** the performance rating; simple mean of frozen opponent inputs. NULL when `games = 0`.

### 5.4 Goals sub-wing

| Slice column | UI label | Definition |
|--------------|----------|------------|
| `games` | Games | National games sum |
| `goals_for` | GF | Sum of nationals’ `goals_for` |
| `goals_against` | GA | Sum of nationals’ `goals_against` |
| `goal_ratio` | Ratio | `goals_for ÷ goals_against` when GA > 0; else career sentinel |
| `most_goals_scored` | Max GF | Max of nationals’ `most_goals_scored` |
| `most_goals_conceded` | Max GA | Max of nationals’ `most_goals_conceded` |
| `biggest_win_difference` | Max win | Max of nationals’ `biggest_win_difference` |
| `biggest_loss_difference` | Max loss | Max of nationals’ `biggest_loss_difference` |
| `biggest_sum_of_goals` | Max sum | Max of nationals’ `biggest_sum_of_goals` |
| `biggest_draw_sum` | Max draw | Max of nationals’ `biggest_draw_sum` |

**PHP-derived (not stored):** `goal_difference`, `goals_for_per_game`, `goals_against_per_game`, `goal_difference_per_game` — same habit as player WC Goals wing.

### 5.5 DDs & CSs sub-wing

Mirror player WC DDs wing — **sums** of nationals’ counters; ratios ÷ national `games`.

| Slice column | UI label | Definition |
|--------------|----------|------------|
| `games` | Games | National games sum |
| `double_digits` | Double Digits | Sum of nationals’ `double_digits` |
| `clean_sheets` | Clean Sheets | Sum of nationals’ `clean_sheets` |
| `double_digits_ratio` | DD Ratio | `double_digits ÷ games` |
| `clean_sheets_ratio` | CS Ratio | `clean_sheets ÷ games` |
| `double_digits_conceded` | DD conceded | Sum of nationals’ `double_digits_conceded` |
| `clean_sheets_conceded` | CS conceded | Sum of nationals’ `clean_sheets_conceded` |
| `double_digits_conceded_ratio` | DD C Ratio | `double_digits_conceded ÷ games` |
| `clean_sheets_conceded_ratio` | CS C Ratio | `clean_sheets_conceded ÷ games` |

**DD threshold:** 10 goals — unchanged.

### 5.6 Opponents sub-wing

**Build from WC games** (country-grain sets), mirroring [`slice_game_stats.py`](../scripts/amiga/slice_game_stats.py) perspective rules:

| Slice column | UI label | Definition |
|--------------|----------|------------|
| `opponent_countries_faced` | Opp. countries | Distinct opponent **country tokens** from national player-games ∪ **own country** when set (**WCCS12**) |
| `opponent_countries_beaten` | Opp. beaten | Distinct opponent country tokens where a national **won** (**WCCS13**) |
| `different_opponents` | Opponents | Distinct **player_ids** faced by any national |
| `different_victims` | Victims | Distinct player_ids **beaten** by any national |
| `double_digits_victims` | DD Victims | Distinct player_ids against whom any national scored **≥10** — **any result** (**WCCS10**) |
| `clean_sheets_victims` | CS Victims | Distinct player_ids any national **shut out** (conceded 0) — **any result** |

**Ops reference (player slice — no win gate on DD/CS victims):**

```96:103:scripts/amiga/slice_game_stats.py
        if dd_for:
            self.row["double_digits"] = int(self.row["double_digits"]) + 1
            self._dd_victims.add(opponent_id)
        ...
        if goals_against == 0:
            self.row["clean_sheets"] = int(self.row["clean_sheets"]) + 1
            self._cs_victims.add(opponent_id)
```

---

## 6. Data architecture

### 6.1 Schema (tentative)

- **New migration:** `scripts/amiga/sql/derived/04x_country_slice.sql` (number at implementation).
- **`amiga_country_slice_totals`** — one row per `(country_token, slice_key='world_cup')`.
- **`amiga_country_slice_at_event`** — same columns + `as_of_tournament_id` for TT.
- **Indexes** on default-sort columns per sub-wing (follow player slice pattern).
- **Backfill:** full `python -m scripts.amiga prove` — no ad-hoc SQL repair.

### 6.2 Writer boundary

**When:** tournament finalize only — same commit family as player WC slice persist.

**Scope gate:** WC tournaments only.

**State machine (conceptual):**

```text
After player WC slice persist for tournament E:

  1. Realm scalars — recompute realm_wc_tournament_count, realm_wc_player_games,
     realm_wc_goals_for at cutoff (incremental or full replay)

  2. For each country_token C with ≥1 participant in E (or cumulative):
     a. Sum roll-ups from player slice rows of nationals(C)
     b. Max roll-ups from same player rows (goal extremes)
     c. CountryWorldCupSliceTracker — replay WC games involving nationals(C):
        - union network / geo sets (§5.6)
        - domestic / international game counters
        - collect (R_opp, score) pairs for performance rating
        - sum opponent ratings for average_opponent_rating
     d. Derive ratios, win_rate, shares, performance_rating
     e. Write country_slice_at_event(C, 'world_cup', tournament_id)
     f. Upsert country_slice_totals(C, 'world_cup')
```

**Implementation note:** Prefer **`CountryWorldCupSliceTracker`** (Python + PHP mirror) — analogous to `WorldCupSliceTracker` but keyed by `country_token` and fed all games where either side maps to *C* for perspective accumulation. **Do not** sum player `double_digits_victims` counts for country DD victims.

**Player → country map:** `amiga_players.country` at finalize time; blank → `Unknown`.

### 6.3 PHP parity

Extend ops libs under `site/public_html/amiga/ops/includes/` — mirror Python finalize output byte-for-byte on slice rows.

### 6.4 Read path

- New `includes/amiga_wc_countries_*` partials (table render + wing body) — **separate** from player partials.
- Hub shell wing 4 sub-nav under `world-cups/countries/*`.
- Time travel: `as=` on all links from first UI ship.

---

## 7. Verification (`prove`)

New CLI **`verify_country_slice`** (or extend `verify_player_slice` sibling) with oracles:

| Group | Oracle |
|-------|--------|
| Sum metrics | Sum player WC slice rows grouped by `country_token` (incl. `Unknown`) |
| Max metrics | `MAX(player column)` per country |
| Network / geo six | Replay game loop with country-grain sets — match tracker |
| Domestic / international | Count player-games from `amiga_games` × WC × country map |
| Realm denominators | SQL sums over all WC player-games / GF / distinct WC tournament count |
| `average_opponent_rating` | `SUM(opponent_frozen_rating) / games` from `amiga_game_ratings` |
| `performance_rating` | `performance_rating_from_pairs` on collected national pairs |
| `win_rate` | `(wins + 0.5*draws)/games` from summed W/D/L |
| TT spot check | `country_slice_at_event` at mid-realm cutoff vs oracle |

**Gate:** must pass in `prove` manifest before UI ship.

---

## 8. UI contract (hub wing 4)

### 8.1 Sub-wing URLs (proposed)

| Sub-wing | Hub path |
|----------|----------|
| Honours (default) | `/amiga/world-cups/countries/honours.php` |
| Results | `…/results.php` |
| Participation | `…/participation.php` |
| Goals | `…/goals.php` |
| DDs & CSs | `…/dds.php` |
| Opponents | `…/opponents.php` |

Register in `includes/k2_amiga_routes.php` + [`url-routes.md`](url-routes.md) at implementation.

### 8.2 Chapter lede (hub)

One line per sub-wing — nation-centric tone (*“World Cup honours by country…”*).

### 8.3 Flags

Use `k2_amiga_country_flag.php` when `country_token` maps; **`Unknown`** — text only until players are corrected.

---

## 9. Implementation slices (suggested)

**Execution detail:** [`amiga-world-cups-country-slice-implementation-plan.md`](amiga-world-cups-country-slice-implementation-plan.md) (STOP gates, file paths, verify commands).

| Slice | Deliverable |
|-------|-------------|
| **CS-0** | This policy locked |
| **CS-1** | DDL + column registry stub |
| **CS-2** | Python `CountryWorldCupSliceTracker` + finalize hook |
| **CS-3** | `verify_country_slice` oracles |
| **CS-4** | PHP writer parity |
| **CS-5** | `prove` green |
| **CS-6** | Hub wing 4 UI (five sub-wings; column placement per WCCS20) |
| **CS-7** | Docs closure — hub policy, data contract, feature-log |

---

## 10. Out of scope (v1)

| Topic | Notes |
|-------|--------|
| Leaderboards → Countries mirror | Hub only (**WCCS21**) — **not** the career **Countries** hub tab ([`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md)) |
| Medals/podium/pts per participation | Rejected (**WCCS19**) |
| Representation rate | Rejected |
| Single-WC peaks / top national player | Rejected |
| WC HoF rows for country metrics | Follow-on |
| `*_last_rise_*` on country slice | Follow-on |
| Live `amiga_games` aggregation on read | Forbidden |
| Continent / host-nation story columns | Not requested — may revisit |

---

## 11. Docs to update at implementation

| Doc | Change |
|-----|--------|
| [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) | Wing 4 shipped; four-wing summary |
| [`amiga-data-contract.md`](amiga-data-contract.md) | Register `04x` tables + columns |
| [`url-routes.md`](url-routes.md) | Country stats routes |
| [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) | Country slice paragraph |
| [`docs/coordination/feature-log.md`](coordination/feature-log.md) | L1 row when DDL lands |

---

## 12. Agent checklist (new chat)

1. Read **this file** + [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md) + [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).
2. **Do not** sum player network counts for country Opponents columns — use game unions.
3. **Do not** require wins for DD/CS victims (match player ops).
4. **Do not** ship UI before `verify-country-slice` passes.
5. Python + PHP country slice writers stay in parity.
6. `Unknown` bucket for blank nationality — three players today.

---

## Revision log

| When | What |
|------|------|
| 2026-06-24 | Policy locked from product chat — wing 4 Country stats; five sub-wings; roll-up rules; participation + domestic/international + realm shares + win rate + perf rating + avg opp rating; storage/verify/UI contract |
