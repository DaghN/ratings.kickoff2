# Amiga World Cups — player slice V2 expansion

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Shipped** (Jun 2026-23) — DDL `039`, writers, `verify-player-slice` V2, **`prove` green**, **five sub-wing UI** on World Cups hub → Player stats. *(LB duplicate retired Jun 2026-29.)*

**Parent:** [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (V1 data home) · [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) (UI home) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) (prove-only writes)

**Related:** [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) (career network semantics) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (geo semantics) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.3

**Supersedes:** scattered V2 bullets in LB policy §8 only for **player-slice column + wing** scope — honours/results V1 unchanged.

**Sibling:** [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) — nation-grain WC stats (hub wing 4).

---

## 1. Executive summary

Expand the **`world_cup` player slice** (`amiga_player_slice_totals` + `amiga_player_slice_at_event`) and WC player-stats UI from **three sub-wings to five**:

| # | Sub-wing | V1 today | V2 change |
|---|----------|----------|-----------|
| 1 | **Honours** | Shipped | Unchanged |
| 2 | **Results** | Shipped | Unchanged |
| 3 | **Goals** | GF/GA/GD + per-game averages only | Add career-goals-style **texture** columns (Ratio, Max GF … Max draw) |
| 4 | **DDs & CSs** | — | New tab — mirror generic [`double-digits.php`](../site/public_html/amiga/leaderboards/double-digits.php) |
| 5 | **Opponents** | — | New tab — geography + victims subset (WC-scoped) |

**UI:** World Cups hub → Player stats (`world-cups/players/*`) — [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) WCH8–WCH9. Shared body: `includes/amiga_wc_players_wing_body.inc.php`.

**Storage:** All new metrics are **WC-game-scoped** stored truth on the slice row. **Do not** copy career values from `amiga_player_current` / snapshots (wrong scope). **Do not** live-scan `amiga_games` on LB hot paths.

**Holy loop:** DDL → Python finalize writer → PHP finalize parity → `verify_player_slice` oracles → `prove` green.

---

## 2. Problem statement (why V2 exists)

V1 slice stores **running sums** only (honours + games + W/D/L + goals + points). Generic Amiga leaderboards also show:

- **Per-game goal extremes** (max goals in one game, max margin, max draw scoreline, …)
- **DD / CS counts and ratios**
- **Network counts** (distinct opponents, victims, DD victims, CS victims)
- **Geography counts** (opponent nations faced / beaten)

Those career fields are maintained in **`PlayerState`** / **`PlayerGeoYearTracker`** during finalize over **all** games. World Cups need the **same product vocabulary** restricted to **World Cup rated games only** — the same reason V1 introduced slice tables instead of reading `wc_*` off the honours block.

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **V2P1** | **Five sub-wings** | Honours · Results · Goals · **DDs & CSs** · **Opponents** — fixed set for this track |
| **V2P2** | **Goals enrichment** | Add columns through **Max draw** inclusive; align labels with generic Goals LB |
| **V2P3** | **DDs wing** | Full generic DDs & CSs table (8 data columns + Games), not a subset |
| **V2P4** | **Opponents wing** | **Six** metrics only (see §5.5) — no culprits, no MGC/BL victims, no calendar-geo peak years or Hosts |
| **V2P5** | **Own country in faced** | `opponent_countries_faced` seeds player **own country** when set — same as career [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) **H6** |
| **V2P6** | **Beaten set** | `opponent_countries_beaten` = nations with ≥1 **win** in a WC game only — **no** own-country seed ([**H7**](amiga-hof-tournament-geo-policy.md)) |
| **V2P7** | **Network semantics** | Opponent / victim / DD victim / CS victim counts use **career rules** ([`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) §4) applied to **WC games only** |
| **V2P8** | **Eligibility** | Rows where slice `tournaments_played ≥ 1` (unchanged from V1) |
| **V2P9** | **Time travel** | Every new stored column mirrored on **`slice_at_event`**; reads at cutoff = latest row ≤ cutoff (unchanged V1 pattern) |
| **V2P10** | **Ratios** | Store DD/CS ratios on slice (match career persist habit). **Goal ratio** may be stored or PHP-derived from `goals_for` / `goals_against` — implementer choice; verify uses same rule |
| **V2P11** | **HoF / rise dates** | **Out of scope** for V2 — no new `*_last_rise_*` on slice unless a follow-on HoF slice explicitly asks |
| **V2P12** | **WC detection** | `is_world_cup_tournament()` / name `^World Cup\s+\S` — same as V1 |

---

## 4. V1 baseline (already shipped — do not re-litigate)

**Tables:** `scripts/amiga/sql/derived/033_player_slice.sql`

**Columns (both `slice_totals` and `slice_at_event`):**

`tournaments_played`, `gold`, `silver`, `bronze`, `podiums`, `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `points`, `tournaments_played_last_rise_tournament_id`, `tournaments_played_last_rise_event_date`

**Writers:** `scripts/amiga/slice_totals.py`, `site/public_html/amiga/ops/includes/amiga_slice_totals_lib.php` — increment from **participation rollups** at WC finalize.

**Verify:** `scripts/amiga/verify_player_slice.py` — honours + game **sums** vs `amiga_games` ∩ WC tournaments.

**UI:** Honours · Results · Goals (partial) on hub Player stats.

---

## 5. V2 column catalog

**Naming:** snake_case on slice rows (match V1). **UI labels** follow generic LB headers where listed.

**Registry:** extend `scripts/amiga/slice_columns.py` (`SLICE_STAT_COLUMNS` v2 group).

### 5.1 Goals sub-wing — new stored columns

Career analogues from `amiga_player_event_snapshots` / [`goals.php`](../site/public_html/amiga/leaderboards/goals.php).

| Slice column | UI label | Definition (WC games only) |
|--------------|----------|----------------------------|
| *(existing)* `games` | Games | Add **Games** column to Goals table UI (data already on row) |
| *(existing)* `goals_for`, `goals_against` | GF, GA | Unchanged |
| `goal_ratio` | Ratio | `goals_for / goals_against` when GA > 0; else career sentinel rules (`-1` / dash display) — match `k2_fmt` helpers |
| `most_goals_scored` | Max GF | Max goals scored by player in any single WC game |
| `most_goals_conceded` | Max GA | Max goals conceded in any single WC game |
| `biggest_win_difference` | Max win | Max `(gf − ga)` in a won WC game |
| `biggest_loss_difference` | Max loss | Max `(ga − gf)` in a lost WC game |
| `biggest_sum_of_goals` | Max sum | Max `(gf + ga)` in any WC game |
| `biggest_draw_sum` | Max draw | Max `(gf + ga)` in a drawn WC game; UI shows half–half scoreline when `draws > 0` |

**PHP-derived (not stored):** `gd`, `gf_per_game`, `ga_per_game`, `gd_per_game` — unchanged V1 habit.

**Default sort:** GF ↓ (unchanged unless product revises).

### 5.2 DDs & CSs sub-wing — new stored columns

Mirror [`double-digits.php`](../site/public_html/amiga/leaderboards/double-digits.php).

| Slice column | UI label | Definition (WC games only) |
|--------------|----------|----------------------------|
| *(existing)* `games` | Games | Rated WC games |
| `double_digits` | Double Digits | Games where player scored ≥ 10 |
| `clean_sheets` | Clean Sheets | Games where player conceded 0 |
| `double_digits_ratio` | DD Ratio | `double_digits / games` |
| `clean_sheets_ratio` | CS Ratio | `clean_sheets / games` |
| `double_digits_conceded` | DD conceded | Games where opponent scored ≥ 10 vs player |
| `clean_sheets_conceded` | CS conceded | Games where opponent scored 0 vs player |
| `double_digits_conceded_ratio` | DD C Ratio | `double_digits_conceded / games` |
| `clean_sheets_conceded_ratio` | CS C Ratio | `clean_sheets_conceded / games` |

**DD threshold:** 10 goals — same as `k2_rating_core` / career.

**Default sort:** Double Digits ↓ (match generic LB).

### 5.3 Opponents sub-wing — new stored columns

| Slice column | UI label | Career source | Definition (WC games only) |
|--------------|----------|---------------|----------------------------|
| `opponent_countries_faced` | Opp. countries | Calendar & geo | Distinct opponent **nations** from WC games ∪ own country when set (**H6**) |
| `opponent_countries_beaten` | Opp. beaten | Calendar & geo | Distinct opponent nations with ≥1 win (**H7**, no own-country seed) |
| `different_opponents` | Opponents | Victims LB | Distinct **players** faced |
| `different_victims` | Victims | Victims LB | Distinct opponents with ≥1 win vs player |
| `double_digits_victims` | DD Victims | Victims LB | Distinct opponents with ≥1 DD win vs player |
| `clean_sheets_victims` | CS Victims | Victims LB | Distinct opponents with ≥1 CS win vs player |

**Default sort:** Opponents ↓ (match generic victims LB default on `DifferentOpponents`).

**Explicitly excluded from this wing:** culprits family, MGC/BL victims, peak-year columns, `countries_played_in` (Hosts).

---

## 6. Data architecture

### 6.1 Schema change

- **New migration:** `scripts/amiga/sql/derived/039_player_slice_v2.sql` (number tentative — register in `schema_bundles.py`).
- Add **the same columns** to **`amiga_player_slice_totals`** and **`amiga_player_slice_at_event`**.
- Add sort indexes only where LB default sort needs them (e.g. `different_victims`, `double_digits`) — follow V1 index pattern.
- **Backfill:** full `python -m scripts.amiga prove` replay — no ad-hoc SQL repair ([`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)).

### 6.2 Writer boundary

**When:** tournament finalize only — same commit as V1 slice persist (`finalize_tournament.py` / `amiga_ops_persist_world_cup_slices`).

**Scope gate:** run V2 accumulators only when `is_world_cup_tournament(tournament_name)`.

**State machine (conceptual):**

```text
For each player P with slice state S (cumulative WC career):

  On each WC tournament finalize:
    1. V1 participation increment (existing) — honours + tournament-level W/D/L/goals/points
    2. V2 game loop — for each rated game G in this tournament:
         for each perspective (subject, opponent):
           update S game extremes (max GF, max GA, margins, sums, draw sum)
           update S DD/CS game counters (subject scored ≥10, conceded 0, etc.)
           update S network sets (opponents, victims, dd_victims, cs_victims)
           update S geo sets (opponent country faced / beaten)
    3. Derive ratios on S from counters + games
    4. Write slice_at_event(P, 'world_cup', tournament_id) = S
    5. Upsert slice_totals(P, 'world_cup') = S
```

**Implementation note:** Prefer a dedicated in-memory **`WorldCupSliceState`** (Python + PHP mirror) carried across replay in `slice_by_player`, analogous to `PlayerState` but **only mutated for WC tournaments** and **only fields in §5**. Do not fork `PlayerState` itself (career scope leak risk).

**Game source:** in-memory games for tournament E at finalize (same pool as matchup cumulative) — not post-hoc wide table scan on hot path.

**Country tokens:** `TRIM(country)`; empty/NULL excluded from game-derived sets ([**H8**](amiga-hof-tournament-geo-policy.md)).

### 6.3 PHP parity

Extend:

- `site/public_html/amiga/ops/includes/amiga_slice_totals_lib.php`
- `site/public_html/amiga/ops/includes/amiga_slice_persist_lib.php`

Python and PHP must produce **identical** slice rows for the same tournament replay (same habit as event snapshots).

### 6.4 Read path

- Extend `includes/amiga_wc_lb_lib.php` / slice snapshot lib to SELECT new columns.
- Extend `includes/amiga_wc_players_table.php` — new renderers `dds`, `opponents`; enrich `goals`.
- Register routes: `…/players/dds.php`, `…/players/opponents.php` (+ LB twins).
- Update `amiga_world_cups_players_nav.php` (hub inner tabs).
- Propagate `as=` on all new tabs.

---

## 7. Verification (`prove`)

Extend **`scripts/amiga/verify_player_slice.py`** (or sibling `verify_player_slice_v2.py` merged into same CLI) with **ground-truth oracles** per column group:

| Group | Oracle source |
|-------|----------------|
| V1 sums | Existing SQL (keep) |
| Goals extremes | `MAX` / conditional `MAX` over `amiga_games` × WC `tournaments`, both perspectives |
| DD/CS counts | Count WC games meeting DD/CS predicates per player |
| Network four | Distinct opponent `player_id` sets with filters (`wins>0`, `dd_wins>0`, `cs_wins>0`) — replay logic in SQL or Python oracle mirroring §5.3 |
| Geo two | Replay [`player_geo_year.py`](scripts/amiga/player_geo_year.py) rules on **WC games subset only**, or equivalent SQL |

**TT check:** spot player — `slice_at_event` at mid-realm cutoff matches oracle computed with games ≤ cutoff.

**Gate:** `verify-player-slice` remains in `prove` manifest; must pass before export/sign-off.

---

## 8. UI contract (hub Player stats)

### 8.1 Sub-wing URLs

| Sub-wing | Hub path |
|----------|----------|
| Honours | `/amiga/world-cups/players/honours.php` |
| Results | `…/results.php` |
| Goals | `…/goals.php` |
| DDs & CSs | `…/dds.php` |
| Opponents | `…/opponents.php` |

Legacy `/amiga/leaderboards/world-cups/*` → **302** matching hub path (Jun 2026-29).

Register keys in `includes/k2_amiga_routes.php`; document in [`url-routes.md`](url-routes.md).

### 8.2 Shared leading columns

Rank · Player · Elo · Country — all five wings (match V1).

### 8.3 Chapter ledes (hub)

One line per sub-wing on hub shell — mirror tone of existing wing 2 player-stats ledes.

---

## 9. Implementation slices (suggested order)

| Slice | Deliverable | Depends on |
|-------|-------------|------------|
| **V2-0** | This policy locked + column registry stub in `slice_columns.py` | — |
| **V2-1** | DDL `039` + `schema_bundles` + empty columns default 0/NULL | V2-0 |
| **V2-2** | Python `WorldCupSliceState` + finalize hook + unit tests | V2-1 |
| **V2-3** | `verify_player_slice` v2 oracles | V2-2 |
| **V2-4** | PHP writer parity | V2-2 |
| **V2-5** | `prove` green on fresh nuclear DB | V2-3, V2-4 |
| **V2-6** | UI — enrich Goals + add DDs & Opponents tabs (hub Player stats) | V2-5 |
| **V2-7** | Docs closure — LB policy §8, hub policy, `amiga-data-contract.md`, feature-log | V2-6 |

Slices **V2-2–V2-5** may land before UI; do not ship UI without `prove` sign-off on new columns.

---

## 10. Out of scope (V2 track)

| Topic | Notes |
|-------|--------|
| Culprits columns on Opponents wing | Generic victims tab only as inspiration |
| MGC / BL / BW victim or culprit counts | Not requested |
| `countries_played_in` (Hosts) | Calendar-geo only |
| Peak calendar-year columns | Calendar-geo only |
| WC HoF rows for new metrics | Follow-on after LB proves surface |
| `*_last_rise_*` on new slice columns | **V2P11** |
| Per-event WC matchup table | Pair table remains all-events; slice holds WC-scoped network **scalars** |
| Live `amiga_games` aggregation on read | Forbidden on hot path |

---

## 11. Docs to update at implementation

| Doc | Change |
|-----|--------|
| [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) | §8 V2 → pointer here; §6 paths for two new tabs |
| [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) | Wing 3 sub-wing list = five |
| [`amiga-data-contract.md`](amiga-data-contract.md) | Register `039` columns |
| [`url-routes.md`](url-routes.md) | Route keys for five sub-wings; legacy LB redirects |
| [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) | §5.3 slice paragraph |
| [`docs/coordination/feature-log.md`](coordination/feature-log.md) | L1 row when DDL lands |

---

## 12. Agent checklist (new chat)

1. Read **this file** + [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (V1) + [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).
2. **Do not** fill V2 columns from career snapshot fields.
3. **Do not** ship UI before `verify-player-slice` covers new columns.
4. Python + PHP slice writers stay in parity.
5. Dual surface: one `amiga_wc_players_table.php` renderer per sub-wing.
6. Geo **faced** includes own country; **beaten** does not — **H6/H7**.

---

## Revision log

| When | What |
|------|------|
| 2026-06-23 | Policy locked from product chat — five wings; goals texture + DDs & CSs + Opponents; slice DDL/writer/verify/UI contract |
