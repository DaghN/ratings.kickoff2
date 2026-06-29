# Amiga Hall of Fame — World Cup records

**Status:** **Implemented** (Jun 2026-29) — all 8 slices (WCH-1…WCH-8) shipped; Python + PHP finalize parity; `prove` green incl. `verify-wc-hof`, `verify-hof-geo-year`, `verify-realm-snapshots`. Plan [`amiga-wc-hof-implementation-plan.md`](amiga-wc-hof-implementation-plan.md)  
**Implementation plan:** [`amiga-wc-hof-implementation-plan.md`](amiga-wc-hof-implementation-plan.md)
**Parent:** [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) · [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md) · [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md)  
**Related:** [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Supersedes (for this record set):** [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) **R11** and [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) **WC10** — WC HoF is in scope with **sparse WC-only snapshots**, not deferred.

---

## 1. Executive summary

Add a **World Cup block** to `/amiga/hall-of-fame.php` — **28 realm record rows** scoped to World Cup rated games and participations only (`slice_key = 'world_cup'` / `amiga_tournament_is_world_cup()`).

| Concept | Rule |
|---------|------|
| **Product home** | Same HoF table as career records; optional intra-table section header (WC block) |
| **Source truth (players)** | Existing `amiga_player_slice_totals` + `amiga_player_slice_at_event` (V1 + V2 columns) plus **new slice fields** for per-WC award counts and single-WC peaks |
| **Source truth (realm holders)** | **New sparse timeline** — one full HoF payload row per **World Cup finalize**, not per every tournament |
| **Present reads** | Materialized **present projection** from the latest WC HoF snapshot (implementation: dedicated present row/table or WC column block — see §5) |
| **Time travel** | At cutoff *T*, load **latest WC HoF snapshot** with `(event_date, event_chrono, tournament_id) ≤ T` — not career `amiga_realm_snapshots` |
| **Between non-WC events** | **No** WC HoF compute, **no** WC HoF storage write |
| **Ratio eligibility** | **`games ≥ 20`** on the player's WC slice (same threshold as career ratio HoF via `k2_established_min_games()`) |
| **Perfect events** | **Out of scope** — career **Most perfect events** already on HoF; **no** WC perfect HoF row |
| **Most World Cups played** | Already shipped (`MostWcPlayed`); **UI** moves into WC block; storage **consolidates** onto WC HoF store in the same implementation track (no long-term dual home) |

Holy loop: DDL → Python finalize (WC events only) → PHP finalize parity → verify oracles → `prove` green → HoF UI + LB deep links.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WCH1** | **Record count** | **28 rows** in the WC HoF block (§4 register), including **Most World Cups played** |
| **WCH2** | **WC detection** | Same as WC slice: `amiga_tournament_is_world_cup()` / catalog name `^World Cup\s+\S` |
| **WCH3** | **Sparse snapshots** | WC HoF timeline rows **only when a World Cup tournament finalizes** — **not** copied onto every `amiga_realm_snapshots` row |
| **WCH4** | **Time travel read** | HoF at cutoff = **latest WC HoF snapshot ≤ cutoff** (cheap chrono lookup) |
| **WCH5** | **No WC perfect row** | Do not add “most perfect WC events” |
| **WCH6** | **Ratio gate** | Rate/ratio/average HoF rows require holder to have **`games ≥ 20`** on WC slice at cutoff (WC games, not career games) |
| **WCH7** | **Per-WC attack/defense awards** | At each WC finalize, award **one** “best attack” and **one** “best defense” to participants with the best **event** GF/g and lowest **event** GA/g — **no** minimum games within that WC |
| **WCH8** | **Single-WC peaks** | “Record single WC GF/g” / “Record single WC GA/g” = best **one tournament's** averages — distinct from career WC GF/g and GA/g across all WC games |
| **WCH9** | **Tie policy** | Same as career HoF (**H11** / **R8**): strict `>` to beat holder; equal → **lowest `player_id`**; ratio sort direction as listed in §4 |
| **WCH10** | **Writer boundary** | Tournament finalize + full `replay` only — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) |
| **WCH11** | **UI placement** | Same `/amiga/hall-of-fame.php` table; WC rows grouped together (section header optional) |
| **WCH12** | **LB deep links** | Each row links to the matching WC player LB sub-wing sort where one exists (extend `amiga_records_hof_links.php` pattern) |
| **WCH13** | **Career HoF unchanged** | Career record book stays on `amiga_generalstats` + `amiga_realm_snapshots` per existing policies — WC HoF is a **parallel store** |

---

## 3. What this is (and is not)

**Is:**

- Realm-wide **holder** rows (one player, or two for draw-style game records) for WC-only metrics already visible on WC player leaderboards.
- **Snapshottable** history at **World Cup granularity** for time travel.
- Stored-truth writers at finalize — not live `ORDER BY` on hot HoF paths.

**Is not:**

- Per-tournament **event stats** rows ([`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md)) — those describe *one WC's texture*; this doc is *who holds the all-time WC record*.
- **Country-grain** WC records ([`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md)) — nation tables stay separate.
- Duplication of **career** HoF rows (goals, DDs, peak rating, etc.) unless the WC-scoped holder differs — only WC-scoped rows listed in §4 belong in the WC block.
- **Most perfect events** (career) — already implemented; not duplicated for WCs.

---

## 4. Record register (28 rows)

Working **UI labels** — final copy may change. **Column prefix** names are implementation hints (manifest TBD in plan doc).

### 4.1 Honours and volume (cumulative WC slice)

| # | UI label (working) | Prefix (hint) | Player source at cutoff | Beat rule |
|---|-------------------|---------------|---------------------------|-----------|
| 1 | Most World Cups played | `MostWcPlayed` | `slice.tournaments_played` | Higher |
| 2 | Most WC gold medals | `MostWcGold` | `slice.gold` | Higher |
| 3 | Most WC games | `MostWcGames` | `slice.games` | Higher |
| 4 | Most WC wins | `MostWcWins` | `slice.wins` | Higher |
| 5 | Most WC points | `MostWcPoints` | `slice.points` | Higher |

### 4.2 Results quality (WC slice; §WCH6 for ratios)

| # | UI label | Prefix | Definition | Beat rule |
|---|----------|--------|------------|-----------|
| 6 | Best WC Pts/g | `BestWcPtsPerGame` | `points / games` | Higher |
| 7 | Best WC win rate | `BestWcWinRate` | Same formula as WC Results LB (`amiga_wc_lb_win_rate`) | Higher |

### 4.3 Goals — career WC (numerators on slice)

| # | UI label | Prefix | Definition | Beat rule |
|---|----------|--------|------------|-----------|
| 8 | Most WC goals | `MostWcGoalsFor` | `slice.goals_for` | Higher |
| 9 | Most WC goals per game | `BestWcGoalsForPerGame` | `goals_for / games` | Higher |
| 10 | Least WC goals conceded per game | `BestWcGoalsAgainstPerGame` | `goals_against / games` | **Lower** |
| 11 | Best WC goal difference per game | `BestWcGoalDiffPerGame` | `(goals_for − goals_against) / games` | Higher |
| 12 | Best WC goal ratio | `BestWcGoalRatio` | `slice.goal_ratio` (same sentinel/display rules as career) | Higher |

### 4.4 Double digits and clean sheets

| # | UI label | Prefix | Player source | Beat rule |
|---|----------|--------|---------------|-----------|
| 13 | Most WC double digits | `MostWcDoubleDigits` | `slice.double_digits` | Higher |
| 14 | Best WC double digit ratio | `BestWcDoubleDigitsRatio` | `slice.double_digits_ratio` | Higher |
| 15 | Most WC clean sheets | `MostWcCleanSheets` | `slice.clean_sheets` | Higher |
| 16 | Best WC clean sheet ratio | `BestWcCleanSheetsRatio` | `slice.clean_sheets_ratio` | Higher |

### 4.5 Opponent network (WC slice V2)

| # | UI label | Prefix | Player source | Beat rule |
|---|----------|--------|---------------|-----------|
| 17 | Most WC opponents | `MostWcOpponents` | `slice.different_opponents` | Higher |
| 18 | Most WC victims | `MostWcVictims` | `slice.different_victims` | Higher |
| 19 | Most WC double digit victims | `MostWcDoubleDigitsVictims` | `slice.double_digits_victims` | Higher |
| 20 | Most WC clean sheet victims | `MostWcCleanSheetsVictims` | `slice.clean_sheets_victims` | Higher |

### 4.6 Single-game extremes (WC rated games only)

| # | UI label | Prefix | Player source | Beat rule |
|---|----------|--------|---------------|-----------|
| 21 | Most WC goals in one game | `MostWcGoalsInOneGame` | `slice.most_goals_scored` (realm holder = max across players) | Higher |
| 22 | Biggest WC winning margin | `BiggestWcWinDifference` | `slice.biggest_win_difference` | Higher |
| 23 | Biggest WC draw | `BiggestWcDrawSum` | `slice.biggest_draw_sum` | Higher |
| 24 | Biggest WC sum of goals | `BiggestWcSumOfGoals` | `slice.biggest_sum_of_goals` | Higher |

Game-anchored holder package must include **`*GameID`** (and date from that game) — mirror career single-game HoF ([`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)).

### 4.7 Per-WC category awards (new cumulative counters)

Count of World Cups in which a player **led the tournament** in the category (among all participants in that WC):

| # | UI label | Prefix | Increment rule | Beat rule |
|---|----------|--------|----------------|-----------|
| 25 | Most WC best attack awards | `MostWcBestAttackAwards` | +1 for player with highest **event** GF/g when WC *E* finalizes | Higher |
| 26 | Most WC best defense awards | `MostWcBestDefenseAwards` | +1 for player with lowest **event** GA/g when WC *E* finalizes | Higher |

**WCH7:** No minimum games within the WC to qualify. Ties on event GF/g or GA/g at finalize: **lowest `player_id`** wins the award (one increment only).

**Storage:** New cumulative fields on **`amiga_player_slice_*`** (recommended) with `*_last_rise_*` when count increases — same date habit as [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md).

### 4.8 Single-World-Cup peaks (best one tournament)

Best **single WC tournament's** averages for a player across their career (not career-wide average from §4.3):

| # | UI label | Prefix | Definition | Beat rule |
|---|----------|--------|------------|-----------|
| 27 | Record single WC GF/g | `BestSingleWcGoalsForPerGame` | Max over participations: `(goals_for in WC E) / (games in WC E)` | Higher |
| 28 | Record single WC GA/g | `BestSingleWcGoalsAgainstPerGame` | Min over participations: `(goals_against in WC E) / (games in WC E)` | **Lower** |

Holder package includes **`*TournamentID`** + date = that WC's `event_date` (tournament-anchored, not rise-from-participation elsewhere).

**Storage:** New per-player fields on slice (value + tournament id where peak was set) or recomputable oracle at WC finalize — implementation plan chooses; verify must oracle from ground truth.

---

## 5. Storage architecture (rough)

### 5.1 Design principle

Career HoF density (**one realm snapshot per finalize**) is **not** replicated for WC HoF. WC records change only when a **World Cup** finalizes (~23 rows in catalog), so storage is **sparse by design**.

```text
Career HoF:  amiga_realm_snapshots     — 1 row × every finalized tournament
WC HoF:      amiga_wc_hof_snapshots   — 1 row × every finalized World Cup only
```

### 5.2 Timeline table (canonical WC HoF authority)

**Proposed name:** `amiga_wc_hof_snapshots` (TBD in implementation plan)

| Key | `tournament_id` — **World Cup tournaments only** |
| Denorm | `event_date`, `event_chrono`, `tournament_name` (optional) |
| Payload | Full WC HoF holder row — **28 record groups** × (value + holder id/name + date + optional game/tournament ids) |
| Index | `(event_date, event_chrono, tournament_id)` for cutoff queries |

**Write rule:** INSERT/REPLACE one row when finalize completes for tournament *E* **and** *E* is a World Cup.

**No write** when a non-WC tournament finalizes.

### 5.3 Present projection

After each WC HoF snapshot write, update a **present** projection so `/amiga/hall-of-fame.php` present mode stays a single hot query:

| Option | Rule |
|--------|------|
| **A (preferred)** | `amiga_wc_hof_present` — `id = 1` row mirroring latest WC snapshot payload |
| **B** | WC HoF column block on `amiga_generalstats` — only if we accept mixed career+WC columns on one table |

Implementation plan picks A vs B; policy requires **present = latest WC snapshot column-wise**.

### 5.4 Migration of `MostWcPlayed`

Today `MostWcPlayed*` lives on **`amiga_generalstats`** (calendar/geo track). This track **moves** it into the WC HoF store and **removes** it from the career/geo HoF UI block. Implementation removes dual writes once WC HoF writers ship.

### 5.5 What stays on career realm snapshots

**No** WC HoF columns on `amiga_realm_snapshots`. Career snapshots remain the authority for non-WC HoF only.

---

## 6. Finalize writer (conceptual)

```text
On tournament finalize for event E:

  … existing finalize (games, ratings, player snapshots, slice, matchups, career realm snapshot) …

  IF E is NOT a World Cup:
      STOP — no WC HoF work

  IF E IS a World Cup:

      1. Slice totals/at-event for world_cup already updated (existing writer)

      2. Per-WC awards (§4.7):
           For each participant in E, compute event GF/g and GA/g from E's games
           Pick attack winner (max GF/g) and defense winner (min GA/g)
           Increment award counters on affected players' slice rows (+ last_rise_*)

      3. Update single-WC peak fields on slice if E improves a player's peak (§4.8)

      4. Compute realm WC HoF holders through chrono ≤ E:
           - Cumulative rows (§4.1–4.5): max/min over all players' WC slice at cutoff E
           - Single-game rows (§4.6): max over slice game-extreme columns (or games oracle)
           - Award counts (§4.7): max over slice award columns
           - Single-WC peaks (§4.8): max/min over player peak columns
           - Apply WCH6 eligibility pool for ratio rows
           - Apply WCH9 tie policy

      5. INSERT amiga_wc_hof_snapshots (full row for tournament_id = E)

      6. UPDATE present projection from that row
```

**Full replay:** each WC finalize in chrono order performs steps 2–6. Refinalize WC *T* → rewrite snapshot at *T* and recompute **later WC snapshots only** (forward chain within WC timeline).

**PHP finalize** must mirror Python on the same boundary ([`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)).

---

## 7. Holder package and dates

Each record group stores the same **family** of fields career HoF uses:

| Field | Meaning |
|-------|---------|
| `{Prefix}` | Record value |
| `{Prefix}ID` | Holder `player_id` |
| `{Prefix}Name` | Holder display name |
| `{Prefix}Date` | Display date (see below) |
| `{Prefix}GameID` | When game-anchored (§4.6) |
| `{Prefix}TournamentID` | When tournament-anchored (§4.8; draw records if needed) |
| `{Prefix}IDB` / `{Prefix}NameB` | When two-player draw record (§4.6 row 23 if shown as scoreline pair) |

### 7.1 Date semantics

| Record type | `{Prefix}Date` source |
|-------------|------------------------|
| Cumulative counts (§4.1–4.5, §4.7) | Holder's **last rise** `event_date` when metric strictly increased ([**D2–D8**](amiga-hof-record-date-policy.md)) |
| Ratio / rate rows (§4.2–4.4, §4.3 averages) | Same — last rise when **eligible** ratio improved |
| Single-game (§4.6) | **Game event date** (not last participation) |
| Single-WC peaks (§4.8) | **Event date of the WC tournament** where peak was set |

Rise fields live on **`amiga_player_slice_*`** for metrics that increment on slice (implementation extends slice rise registry beyond today's `tournaments_played_last_rise_*` only).

---

## 8. Read paths

### 8.1 Present HoF

Load WC block from **present projection** (§5.3). Career block unchanged from `amiga_generalstats`.

### 8.2 Time travel (`as=` on HoF)

```sql
-- Conceptual
SELECT * FROM amiga_wc_hof_snapshots s
INNER JOIN tournaments t ON t.id = s.tournament_id
WHERE (t.event_date, t.chrono, t.id) <= (:cutoff_date, :cutoff_chrono, :cutoff_tid)
ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC
LIMIT 1
```

If no WC snapshot exists before cutoff (pre-first-WC lens), WC block shows empty/dash rows — same empty habit as other HoF sections.

**Career HoF** at cutoff continues to use `amiga_realm_snapshots` ([`amiga-time-travel-policy.md`](amiga-time-travel-policy.md)).

### 8.3 Leaderboard deep links

Extend [`amiga_records_hof_links.php`](site/public_html/includes/amiga_records_hof_links.php) with WC wing targets (`/amiga/world-cups/players/*`) and sort indices from [`amiga_wc_players_table.php`](site/public_html/includes/amiga_wc_players_table.php).

**§4.6 single-game rows:** value links go to **Games → Highlights** (`/amiga/games/highlights.php?board=…`), same board mapping as online career HoF; WC rows append **`scope=world-cup`** (one Highlights page — no separate WC highlights hub).

Rows without a natural LB column (§4.7 awards, §4.8 single-WC peaks) may ship **without** value links until a LB column exists or product accepts HoF-only rows.

---

## 9. Verification (prove)

New verify module(s) in `python -m scripts.amiga prove`, read-only oracles:

| Check | Oracle |
|-------|--------|
| Snapshot count | `COUNT(amiga_wc_hof_snapshots)` = count of **finalized** World Cups in catalog |
| Present parity | Present projection = latest WC snapshot row (column-wise) |
| Cumulative holders | Each §4.1–4.5 holder = max/min over `amiga_player_slice_totals` at cutoff of that WC |
| Single-game holders | §4.6 vs WC-filtered `amiga_games` extremes |
| Award counts | §4.7 vs recomputed per-WC leaders through cutoff |
| Single-WC peaks | §4.8 vs per-participation event averages |
| Ratio eligibility | Holders have `games ≥ 20` on WC slice unless table empty |
| Dates | Rise dates + game/tournament anchors per §7 |

---

## 10. UI (HoF page)

| Element | Rule |
|---------|------|
| **Placement** | WC rows in one **contiguous block** in the existing table |
| **Section header** | Optional simple intra-table label (e.g. “World Cups”) — cosmetic; not blocking |
| **Order** | Match §4 register order (honours → results → goals → DD/CS → network → singles → awards → peaks) |
| **Most WCs played** | First row in WC block (or honours-adjacent — product tweak at UI slice) |
| **Time travel** | WC block uses §8.2; career block uses existing realm snapshot read |

---

## 11. Out of scope (this track)

| Topic | Notes |
|-------|--------|
| WC **perfect** event HoF row | Explicitly excluded (**WCH5**) |
| WC **country** HoF | Country slice tables — separate product |
| Per-WC **event stats** table as HoF | Event grain — see world-cup-stats plan |
| Storing WC HoF on **every** `amiga_realm_snapshots` row | Rejected — **WCH3** |
| New WC **leaderboard** sub-wings | Optional follow-on; not required to ship HoF |
| Online realm | Amiga-only |

---

## 12. Implementation sequencing

Detailed slices: **[`amiga-wc-hof-implementation-plan.md`](amiga-wc-hof-implementation-plan.md)** (WCH-0 → WCH-8).

| Phase | Slices |
|-------|--------|
| Schema + manifest | WCH-1 |
| Slice writers (awards, peaks, rise) | WCH-2 |
| WC HoF compute + persist | WCH-3 |
| Verify + prove | WCH-4 (**STOP**) |
| PHP parity | WCH-5 |
| HoF UI + TT | WCH-6 |
| `MostWcPlayed` migration | WCH-7 |
| Closure | WCH-8 (**STOP**) |

---

## 13. Related doc updates (when implemented)

| Doc | Change |
|-----|--------|
| [`amiga-data-contract.md`](amiga-data-contract.md) | Register new tables + slice columns |
| [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) | R11 superseded note for WC HoF set |
| [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) | WC10 closed |
| [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) | Remove `MostWcPlayed` from geo HoF table when UI/storage migrate |
| [`docs/UPDATE_DOCS.md`](UPDATE_DOCS.md) Part B | Schema register when DDL lands |