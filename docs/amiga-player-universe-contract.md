# Amiga player universe — derived data contract (intent)

**Status:** Intent / design lock candidate (Jun 2026). **Scope:** derived player facts, read paths, and phased expansion — not UI mockups.

**Purpose:** Define what the Amiga realm should store and serve for **players** (career, tournaments, opponents, honours, HoF) before schema DDL and replay writers expand. This document owns **player-centric derived design**; layer definitions and global replay rules remain in [`amiga-data-contract.md`](amiga-data-contract.md).

**Related:** [`amiga-realm-vision.md`](amiga-realm-vision.md) (online↔Amiga inventory) · [`amiga-profile-v0.md`](amiga-profile-v0.md) (shipped profile) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) (commit boundary) · [`website-data-contract.md`](website-data-contract.md) (online analogue)

---

## 1. Executive summary

### What we are building toward

A **rich player universe** for the offline Amiga ladder: career stats and extremes (mostly **already derived**), **event participation** (one row per player×tournament with placement + rating context), **tournament career rollups** (counts and medals), **head-to-head summaries**, and **server record holders** — without copying online milestones, UTC leagues, or match streaks.

### Design principles

| Principle | Rule |
|-----------|------|
| **Ground vs derived** | Games and catalog are ground truth; player product facts are derived and rebuildable |
| **Finalize boundary** | Global career rating commits at **tournament finalize**; per-game rows on `amiga_game_ratings` are game facts, not ladder authority |
| **Persist for reads** | Hot paths use materialized tables — no realm-wide or per-profile scans on `amiga_games` at page load. Placement rules: **§5.0** |
| **Amiga-native semantics** | Tournament events, World Cups, kitchen marathons — not UTC day/week leagues |
| **Online as pattern, not copy** | Port *shapes* (`matchup_summary`, `generalstats`, junction + totals) where they fit; skip what does not |
| **Reference ≠ product** | Access `added_players`, `Rankings`, `Tables` inform parity tooling only |

### Tier model (implementation order)

| Tier | Meaning | Examples |
|------|---------|----------|
| **A — Ship surfaces on existing data** | No new tables; document read paths | Leaderboard wings from `amiga_player_stats`; moments from `*GameID` columns |
| **B — New derived tables** | DDL + replay/finalize writers | `amiga_player_tournament_participation`, `amiga_generalstats`, `amiga_player_matchup_summary` |
| **C — Product decision / defer** | Semantics TBD | Event-year activity calendars, full profile feast parity with online |

---

## 2. Explicit scope

### In scope (this contract)

- Career row (`amiga_player_stats`) — read policy and gaps
- Player ↔ tournament participation (rich junction + career rollups)
- Tournament honours (WC medals, marathon wins, cup podiums)
- Head-to-head (`amiga_player_matchup_summary`)
- Server records (`amiga_generalstats`)
- Profile, leaderboard, HoF, and API read-path register
- Rebuild triggers and parity checks

### Out of scope (locked skips)

| Topic | Reason | Policy doc |
|-------|--------|------------|
| **Milestones catalog** (`player_milestones`, garden, encyclopedia) | Deferred indefinitely for Amiga | [`amiga-realm-vision.md`](amiga-realm-vision.md) |
| **Match streaks** (longest win/draw/loss, current streaks) | Unknown real order within tournament day; synthetic `game_date` | [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks |
| **Calendar play streaks** (`player_play_streaks`, UTC days/weeks) | Offline batch play ≠ daily online habit | Same |
| **UTC league honours** (`player_league_*`) | No UTC league instances in Amiga | [`leagues-project.md`](leagues-project.md) |
| **Status / server pulse** | No live Amiga server | Realm vision |
| **Cross-realm H2H** | Disjoint player ID spaces | Realm vision |
| **Account / lobby fields** | No Amiga registration flow | — |

### Streak columns on `amiga_player_stats`

`WinningStreak`, `LongestWinningStreak`, `*Streak`, milestone facilitator columns may still be **written** by shared `PlayerState` for engine parity. **Amiga product must not read or display them.** No new features may depend on within-day game order.

---

## 3. Current state (Jun 2026)

### Ground truth (identity + results)

| Table | Player-relevant role |
|-------|---------------------|
| `amiga_players` | `id`, `name`, `country`, `display` |
| `amiga_games` | Canonical results; links `player_a_id`, `player_b_id`, `tournament_id` |
| `tournaments` | Event catalog; `player_count` is **headcount only**, not a roster |
| `tournament_entrants` | **Live registration**; empty for historical Access import |

### Derived truth (shipped)

| Table | Grain | Player use today |
|-------|-------|------------------|
| `amiga_player_stats` | 1 row / player | Hero, career strip, leaderboard sorts, rank |
| `amiga_game_ratings` | 1 row / game | Games list; per-game frozen ratings |
| `amiga_rating_events` | 1 row / (player, tournament) | Rating chart API; event timeline |
| `amiga_tournament_standings` | 1 row / (player, tournament, scope) | Tournament pages; **per-phase** points + ranks (see §5.2.1) |
| `amiga_player_tournament_participation` | 1 row / (player, tournament) | Profile + full tournament history; event-wide W-D-L + `event_points` |
| `amiga_player_tournament_totals` | 1 row / player | Tournament honours LB; HoF WC panel |
| `amiga_player_matchup_summary` | 1 row / (player, opponent) | Profile top opponents |
| `amiga_generalstats` | 1 row (`id=1`) | Hall of Fame server records (no streak rows) |

### Fragmentation (resolved Jun 2026)

Player↔tournament facts and career rollups now live in `amiga_player_tournament_participation` + `amiga_player_tournament_totals`. H2H and server records use `amiga_player_matchup_summary` and `amiga_generalstats`. See slices 0–13 in [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md).

---

## 4. Player surfaces register

Each surface maps to **one primary derived source** (joins to `amiga_players` / `tournaments` for labels only).

| Surface | Route / entry | Primary read | Secondary | Tier |
|---------|---------------|--------------|-----------|------|
| **Hero + rank** | `/amiga/player/profile.php` | `amiga_player_stats` + rank subquery | `amiga_players` | A (shipped) |
| **Career strip** | profile | `amiga_player_stats` | — | A (shipped) |
| **Honours strip** | profile | `amiga_player_tournament_totals` | WC medals, wins, podiums | B (shipped) |
| **Performance rating highlight** | profile | `amiga_player_tournament_participation` | best + latest event | B (shipped) |
| **Moments / trophy games** | profile | `amiga_player_stats` `*GameID` + batched game fetch | no table scan; `PeakRatingGameID` not yet written by replay | A (shipped) |
| **Rating chart** | `api/player_rating_history.php?realm=amiga` | `amiga_rating_events` → `tournaments` | — | A (shipped) |
| **Recent tournaments** | profile (5 rows) | `amiga_player_tournament_participation` | finish suffix + Winner + Perf | B (shipped) |
| **Full tournament history** | `/amiga/player/tournaments.php` | `amiga_player_tournament_participation` | sortable; filters All / WC / Cups / country | B (shipped) |
| **Tournament event stats** | `/amiga/tournament.php?view=event-stats` | `amiga_player_tournament_participation` | roster for one event | B (shipped) |
| **Games list** | `/amiga/player/games.php` | `amiga_games` + `amiga_game_ratings` | paginated; OK at scale | A (shipped) |
| **Single game** | `/amiga/game.php` | `amiga_games` + `amiga_game_ratings` | 1 row by `id` | A (shipped) |
| **Top opponents** | profile | `amiga_player_matchup_summary` | goals column; H2H links | B (shipped) |
| **H2H pair page** | `/amiga/h2h.php` | `amiga_player_matchup_summary` | directed pair summary | B (shipped) |
| **Tier A LB wings** | `/amiga/leaderboards/rating.php`, `goals.php`, `double-digits.php`, `victims.php`, `peak-rating.php` | `amiga_player_stats` | `amiga_lb_nav.php` | A (shipped) |
| **Performance rating LB** | `/amiga/leaderboards/performance-rating.php` | `amiga_player_tournament_participation` | best event per player | B (shipped) |
| **Tournament honours LB** | `/amiga/leaderboards/tournament-honours.php` | `amiga_player_tournament_totals` + `amiga_player_stats` (Elo) | `event_*` + `wc_*` career blocks | B (shipped) |
| **Hall of Fame** | `/amiga/hall-of-fame.php` | `amiga_generalstats` + ratio queries on stats | WC panel; metric → LB deep links | B (shipped) |
| **Historical rating ladder** | `/amiga/history.php` | `amiga_rating_events` compute-on-read (`amiga_rating_history_lib.php`) | Event / World Cup / Month / Year wings | A (shipped V1) |
| **WC medals block (dedicated)** | profile | `amiga_player_tournament_totals` | honours strip covers summary | B (deferred) |

**Rule:** New PHP must not aggregate `amiga_games` on profile/leaderboard hot paths. Games tab remains the intentional scan surface (paginated, per player).

---

## 5. Target data model

### Layer diagram

```text
amiga_games (ground)
       │
       ├─► amiga_player_stats          … career totals, extremes, network (1 row/player)
       ├─► amiga_game_ratings          … per-game facts (1 row/game)
       ├─► amiga_rating_events         … rating commit per (player, tournament)
       ├─► amiga_tournament_standings  … points tables per scope (authoritative for placement)
       │
       ├─► amiga_player_tournament_participation  … rich player×event (NEW, Tier B)
       ├─► amiga_player_tournament_totals           … career event rollups (NEW, Tier B)
       ├─► amiga_player_matchup_summary             … directed H2H (NEW, Tier B)
       └─► amiga_generalstats                       … server records (NEW, Tier B)
```

### 5.0 Derived stat placement — stored truth (Jun 2026)

**Authority:** Repo-wide habit — stored / precomputed truth on DB-backed hot paths ([`AGENTS.md`](../AGENTS.md), [`.cursor/rules/kool-workspace.mdc`](../.cursor/rules/kool-workspace.mdc), online [`website-data-contract.md`](website-data-contract.md)). Amiga layer rules: [`amiga-data-contract.md`](amiga-data-contract.md). **Default question before any new profile / leaderboard / tournament stat:**

> *What table should hold this at rebuild/finalize time, and what must verify enforce?*

Do **not** default to aggregating `amiga_games` (or joining many `amiga_game_ratings` rows) on page load — same anti-pattern as live `ratedresults` scans online.

#### Glossary

| Term | Meaning |
|------|---------|
| **Hot surface** | A user-facing page or API loaded often (profile, tournament history, sortable leaderboard). Not “we need a sort” — “this read path should stay fast.” |
| **Stored truth** | Value written at **finalize**, **participation-rebuild**, or **replay** — rebuildable from ground truth, read cheaply later. |
| **Grain** | What one row represents (per game, per player×event, per player×event×phase, per player career). |
| **Denorm copy** | Same fact stored in a **second** table (or duplicated catalog columns on one row) so a read path avoids a join. One **canonical** writer; verify keeps copies equal. Not the same as “derive `goals_for / games` in PHP once per row.” |
| **Player-first / tournament-first** | Access pattern (`WHERE player_id = ?` vs `WHERE tournament_id = ?`). Usually **one junction table** with indexes for both — not automatically two tables. |

#### Decision tree (new player / tournament stat)

```text
1. What grain?
   per game           → amiga_game_ratings (+ amiga_games ground)
   per player×event   → amiga_player_tournament_participation (default)
   per player×event×phase → amiga_tournament_standings only
   per player career  → amiga_player_stats or amiga_player_tournament_totals

2. Event-wide or phase-scoped?
   all games in event → participation (or rating_events if rating-family)
   one league group / KO leg only → standings scope row — never participation alone

3. Source of volume counts?
   W-D-L, goals, event_points → roll up from amiga_games at rebuild (already on participation)
   never from standings volume columns for participation

4. Store a column or compute on read?
   Policy default: STORE on the junction/career row at rebuild if the surface is hot
   (profile, history table, leaderboard sort) — including ratios like avg goals per game.
   Exception: throwaway admin probe or genuinely trivial display-only formatting.

5. Second table (denorm copy)?
   Only when a second *home* needs the same fact without joining:
   e.g. performance_rating canonical on amiga_rating_events, copy on participation.
   Not required for “tournament roster sort” — participation already has (tournament_id, player_id) index.

6. Verify
   Add identity to verify-player-participation (or feature verify) when stored.
```

#### Placement matrix (examples)

| Stat | Grain | Store where | Second copy? | Notes |
|------|-------|-------------|--------------|-------|
| Phase league points | player×event×phase | `amiga_tournament_standings` | No | Tournament page phase tabs |
| Event W-D-L, goals, `event_points` | player×event | `participation` | No | From `amiga_games` rollup at rebuild |
| Rating before/delta/after | player×event | `amiga_rating_events` | Yes → `participation` | Finalize commit boundary |
| `performance_rating` | player×event | `amiga_rating_events` | Yes → `participation` | [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| Avg goals for/against per game | player×event | `participation.avg_goals_for`, `avg_goals_against` | No | `decimal(6,4)`; `goals/games` at rebuild; NULL when `games=0`; verify `ROUND(avg*games,4)≈goals` |
| Career WC gold count | player | `amiga_player_tournament_totals` | No | Aggregate from participation |
| Per-game adjustment | game | `amiga_game_ratings` | No | Games tab + `/amiga/game.php` |

#### Read-path rules (tournament vs player)

| Surface | Primary read | Scan `amiga_games` on load? |
|---------|--------------|-----------------------------|
| `/amiga/tournament.php` standings tabs | `amiga_tournament_standings` | **No** |
| `/amiga/tournament.php` bracket / KO leg | `amiga_games` | **Yes, but** `WHERE tournament_id = ?` only (indexed) |
| `/amiga/player/tournaments.php` | `participation` | **No** |
| `/amiga/player/games.php` | `amiga_games` + `amiga_game_ratings` | **Yes, but** per player, paginated — intentional scan surface |
| Hypothetical “top avg goals per event” LB | `participation` stored column + index | **No** |

**Index note:** `amiga_games` already has `idx_amiga_games_tournament` (`tournament_id`). A future tournament **Games** tab uses the same scoped query; a composite `(tournament_id, game_date, id)` is optional if `EXPLAIN` shows sort cost.

#### Anti-patterns

- Live `SUM` / `COUNT` over all `amiga_games` for profile or realm-wide leaderboards.
- Putting event-wide facts on every `amiga_tournament_standings` scope row (duplicates the same number per phase).
- Two tables for the same grain when one junction + two indexes suffices.
- Skipping store “because `goals_for` and `games` exist” on a **hot sortable leaderboard** — policy prefers a materialized column + verify.

### 5.1 Career row — `amiga_player_stats` (existing)

**Grain:** one row per `player_id`. **Writer:** tournament finalize batch (`commit_heavy_player_derived`) + shared `PlayerState.to_db_row()`.

**Authoritative for:** current `Rating`, career W/D/L, goals, DD/CS, victim/culprit network counts, peak/lowest rating, game-id pointers for extremes.

**Not authoritative for Amiga product:** all `*Streak*` columns; facilitator streak columns (`ScoreStreak`, …).

**Tier A action:** Ship leaderboard wings and HoF ratio rows reading this table only — no schema change.

### 5.2 Player ↔ tournament participation — `amiga_player_tournament_participation` (NEW)

**Purpose:** Canonical **“player played in event”** fact with everything profile and honours need in one row. This is the Amiga analogue of online’s per-period participation row + award context, merged for tournament-shaped events.

**Grain:** one row per `(player_id, tournament_id)` where the player has **≥1 game** in that tournament (participation = results, not registration). The **writer roster is always `amiga_games`** — not `amiga_tournament_standings` overall scope alone.

**Primary key:** `(player_id, tournament_id)`.

**Unique index:** `(tournament_id, player_id)` (symmetric lookup).

**Player-first index:** `(player_id, event_chrono DESC, tournament_id)` or `(player_id, event_date DESC)` for history lists.

| Column | Type | Source / rule |
|--------|------|----------------|
| `player_id` | int | FK `amiga_players` |
| `tournament_id` | int | FK `tournaments` |
| `event_date` | date | `tournaments.event_date` (denorm) |
| `event_chrono` | double | `tournaments.chrono` (denorm) |
| `tournament_name` | varchar(50) | `tournaments.name` (denorm; optional but speeds profile) |
| `is_cup` | tinyint | `tournaments.is_cup` |
| `country` | varchar(50) | `tournaments.country` |
| `has_league` | tinyint | `tournaments.has_league` |
| `has_cup` | tinyint | `tournaments.has_cup` |
| `event_finish_position` | smallint NULL | Holistic post-event finish when definable; **NULL** = unknown. Policy §5.2.2 + honours rules doc. |
| `event_points` | smallint | **3×wins + 1×draws** over **all** games in the event (`amiga_games` rollup); full-event result tally |
| `games` | smallint | `amiga_games` rollup (all phases) |
| `wins` | smallint | same rollup |
| `draws` | smallint | same rollup |
| `losses` | smallint | same rollup |
| `goals_for` | smallint | same rollup |
| `goals_against` | smallint | same rollup |
| `avg_goals_for` | decimal(6,4) NULL | `goals_for / games` at rebuild (4 d.p.); NULL when `games=0` |
| `avg_goals_against` | decimal(6,4) NULL | `goals_against / games` at rebuild |
| `rating_before` | decimal | `amiga_rating_events` |
| `rating_delta` | decimal | `amiga_rating_events` |
| `rating_after` | decimal | `amiga_rating_events` |
| `performance_rating` | decimal NULL | `amiga_rating_events` — chess-style event TPR; see [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| `games_in_event` | smallint | `amiga_rating_events` |
| `finalized_at` | datetime | `amiga_rating_events.finalized_at` |
| `is_winner` | tinyint | `event_finish_position = 1` (all tournaments). |
| `best_knockout_phase` | varchar(50) NULL | Deepest main-bracket KO round — `derive_best_knockout_phase()`; see honours rules §5 |

**Writer:** batch rebuild after standings + rating events for a tournament (full replay: end-of-pass rebuild all; live: after finalize + standings refresh for touched `tournament_id`). Shared placement helper: `scripts/amiga/participation_placement.py` · PHP parity `includes/amiga_participation_placement.php`.

#### 5.2.2 Event finish derivation (design lock Jun 2026)

**Authoritative policy:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md).

**Implementation status:** **Complete** (Jun 2026) — v1 event finish + **v2 medals unification** (`021`–`022`): unified `event_finish_position` (WC podium 1/2/3), `best_knockout_phase`, Tier E overrides; Python + PHP writers; UI read paths; honours totals + LB. Policy: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) v2. Plans: [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) (historical v1), [`amiga-tournament-medals-unification-implementation-plan.md`](amiga-tournament-medals-unification-implementation-plan.md) (v2).

Ground → product flow:

```text
amiga_games (roster + volume stats + event_points)
       │
       ├─► amiga_tournament_standings (phase views — groups, league, knockouts)
       │         NOT copied to participation as league_position / group_position
       ├─► event-finish derivation (tiers A–E) → event_finish_position, best_knockout_phase
       ├─► amiga_rating_events → rating_* / games_in_event / finalized_at
       └─► tournaments (catalog denorm)
                 └─► amiga_player_tournament_participation (one row / player×event)
```

**Tier summary** (detail in honours rules doc):

| Tier | Event shape | `event_finish_position` |
|------|-------------|-------------------------|
| A | Pure knockout (not WC) | Final 1/2; 3rd-place 3/4; else shared semi bronze (both 3); rest from 5+ |
| B | League + cup | Cup final + 3rd-place rules; non-finalists from league `overall`; cup overrides league for finalists |
| C | Pure league | `overall` scope `position` |
| D | World Cup | Podium **1 / 2 / 3** from main-bracket knockouts (shared semi bronze when no 3rd-place match); below podium **NULL** |
| E | Exotic / ambiguous | `amiga_tournament_finish_override` when curated; else NULL |

**Rejected:** `league_position` and `group_position` on participation — phase order and points stay in `amiga_tournament_standings` only.

**Not a substitute for:** `tournament_entrants` (registration), per-phase standings tables (`amiga_tournament_standings`).

#### 5.2.1 Points model — event tally vs phase tally (Jun 2026)

Participation was refined **after slice 14** (tournament history UI + WC data fixes). The product has **two distinct point concepts**; do not conflate them.

| Concept | Grain | Where stored | Rule |
|---------|-------|--------------|------|
| **Phase points** | player × tournament × **phase/scope** | `amiga_tournament_standings` only | 3×W + 1×D **within that phase** (league round-robin, WC Round 1 Group A, etc.). Standings **rank** within the scope from this tally. |
| **Event points** | player × tournament | `amiga_player_tournament_participation.event_points` | 3×W + 1×D over **all** games the player played in the event (`wins`/`draws` from `amiga_games` rollup). |

**Participation does not store phase points** and has **no column copied from standings `points`**. Phase tables on `/amiga/tournament.php` and group tabs always read `amiga_tournament_standings`.

**Writer sources (participation rebuild):**

| Column group | Source | Notes |
|--------------|--------|-------|
| `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against` | `amiga_games` rollup (all phases) | Not from standings volume columns |
| `event_points` | `wins * 3 + draws` (same rollup) | Renamed from `points` in migration `014` |
| `event_finish_position`, `is_winner` | §5.2.2 / honours rules v2 | Single finish path; `is_winner` = finish 1 |
| `best_knockout_phase` | KO depth from standings | Populate on rebuild |
| `rating_*`, `games_in_event`, `finalized_at` | `amiga_rating_events` | unchanged |
| Catalog denorm | `tournaments` | name, flags, dates |

**When the two tallies match:** pure single-phase leagues (e.g. London XXIII) — one phase, all games in that phase → `event_points` equals the only phase points row in standings.

**When they differ:** league+cup marathons and World Cups. Example **Athens LXXXV, Alkis P** — league phase (standings overall): **30 pts** (10W in 11 league games); **event_points**: **36** (12W including Final legs). Example **WC** — group phase points in standings per group; `event_points` sums all group + knockout games.

**UI read rules:**

| Surface | Points shown | Finish shown |
|---------|--------------|--------------|
| `/amiga/tournament.php` phase tables | standings `points` per scope | standings `position` per scope |
| `/amiga/player-tournaments.php` **Pts** column | `event_points` | **`event_finish_position`** ordinal (all events including WC) |
| Profile **recent tournaments** suffix | `event_points` only when single-phase; omitted for league+cup marathons and WCs | **`event_finish_position`** ordinal or — |

**Verify (`verify-player-participation`):** `event_points = wins * 3 + draws`; volume stats match `amiga_games` rollup; rating columns match `amiga_rating_events` when present.

**Apply on existing DBs:** `scripts/amiga/sql/014_participation_event_points.sql` then `python -m scripts.amiga participation-rebuild`.

**Read-path rule:** Profile tournament blocks and “events played” APIs read **this table first**. Rating chart continues to use `amiga_rating_events` (same underlying facts; chart is specialized).

### 5.3 Career tournament rollups — `amiga_player_tournament_totals`

**Purpose:** O(1) career counts for hero lines, honours leaderboards, milestone-style thresholds — Amiga analogue of `player_league_totals`.

**Grain:** one row per `player_id`.

**Primary key:** `player_id`.

| Column | Meaning |
|--------|---------|
| `tournaments_played` | COUNT participation rows |
| `tournaments_won` | Same as `event_gold` (`event_finish_position = 1`) |
| `event_gold` / `event_silver` / `event_bronze` | Holistic finish 1 / 2 / 3 across **all** tournaments |
| `event_podiums` | `event_gold + event_silver + event_bronze` |
| `wc_played` | COUNT participation on World Cup events (`amiga_tournament_is_world_cup`) |
| `wc_gold` / `wc_silver` / `wc_bronze` | WC subset: finish 1 / 2 / 3 |
| `wc_podiums` | `wc_gold + wc_silver + wc_bronze` |
| `last_event_date` | MAX `event_date` |
| `last_tournament_id` | tournament id at max chrono/date |

**Dropped (v2):** `cup_gold` / `cup_silver` / `cup_bronze`; column `podiums` renamed to `event_podiums`.

**Writer:** `GROUP BY player_id` from `amiga_player_tournament_participation` after participation rebuild (Python `player_tournament_participation.py` · PHP `amiga_ops_participation_rebuild_totals_for_players`).

**Optional later:** `amiga_player_tournament_slice_totals` (`player_id`, `slice_key`) — e.g. `world_cup`, `kitchen`, `milan` — if honours UI needs slice tabs like online `player_league_slice_totals`. Defer until honours wing design is fixed.

### 5.4 Head-to-head — `amiga_player_matchup_summary` (NEW)

**Purpose:** Directed pair totals for top opponents, H2H APIs, future compare UI. **Direct port** of online [`player_matchup_summary`](website-data-contract.md).

**Grain:** one row per `(player_id, opponent_id)`.

| Column | Meaning |
|--------|---------|
| `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against` | Subject player perspective |

**Source:** `amiga_games` (both perspectives).

**Writer:**

- **Full rebuild:** SQL bulk from games (mirror `player_matchup_summary_rebuild.sql`).
- **Incremental (live):** upsert two directed rows per new game (mirror online P5 post-game).

**Parity:** `SUM(games) = COUNT(amiga_games) × 2`.

### 5.5 Server records — `amiga_generalstats` (NEW)

**Purpose:** Single-row (or id=1) server-wide record holders for HoF — port of online `generalstatstable`.

**Grain:** `id = 1`.

**Source:** scan `amiga_player_stats` for career extremes + `amiga_games` / `amiga_game_ratings` for single-game records and peak-in-game.

**Include (Tier B):** most games, wins, goals, DDs, CSs, victims/culprits, biggest win margin, biggest draw, highest sum of goals, highest peak rating in a game, ratio leaders pointers.

**Exclude:** longest match streaks, longest play-day/week streaks.

**Writer:** port `scripts/ladder/server_records.py` logic to `scripts/amiga/server_records.py`; run at end of full `replay` and after live finalize (or batch nightly).

---

## 6. Tournament honours rules (Amiga-native)

**Authoritative doc:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) — event finish, podiums, WC medals, shared semi bronze, career rollups, and explicit rejection of phase ranks on participation.

Replace Access `added_players.goldmedals` / `silvermedals` / `bronzemedals` with derived rules on participation + standings. **Reference parity** against Access is tooling-only ([`standings_parity.py`](../scripts/amiga/standings_parity.py) pattern).

**Summary (v2):**

- **Event finish** → `event_finish_position` (nullable; WC podium = 1/2/3).
- **WC podium derivation** → knockout standings (`compute_wc_podium_finish_from_standings`); written as finish, not a separate medal column.
- **Career totals** → `event_*` (all tournaments) + `wc_*` (World Cup subset filter) on `amiga_player_tournament_totals`.
- **Phase tables** → `amiga_tournament_standings` only (no `league_position` / `group_position` on participation).

### Cup vs league events

Use `tournaments.has_cup`, `has_league`, `is_cup` for honours slices and profile filters — not mutually exclusive in catalog.

---

## 7. Online inspiration map

| Online store | Amiga target | Port? |
|--------------|--------------|-------|
| `playertable` | `amiga_player_stats` | **Done** (split identity) |
| `player_period_league` | `amiga_player_tournament_participation` | **Pattern** — junction with stats |
| `player_league_award` | `event_finish_position`, `is_winner` on participation | **Pattern** — denorm on junction |
| `player_league_totals` | `amiga_player_tournament_totals` | **Yes** |
| `player_league_slice_totals` | optional slice totals | **Defer** |
| `player_matchup_summary` | `amiga_player_matchup_summary` | **Yes** |
| `generalstatstable` | `amiga_generalstats` | **Yes** |
| `player_milestones` | — | **Skip** |
| `player_play_streaks` | — | **Skip** |
| `player_period_games` / peaks | event-year aggregates (Tier C) | **Defer** — define semantics first |
| `league_period` | `tournaments` + `amiga_tournament_catalog_stats` | **Analogue** (event metadata) |

---

## 8. Writer architecture

### Commit boundary (unchanged)

Global `amiga_player_stats.Rating` and `amiga_rating_events` commit at **tournament finalize**. See [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md).

### Rebuild order (full `python -m scripts.amiga replay`)

```text
1. Finalize each tournament in chrono order
     → amiga_game_ratings (per game)
     → amiga_rating_events (per player in event)
     → in-memory PlayerState career accumulation
2. commit_heavy_player_derived → amiga_player_stats
3. rebuild_all_standings → amiga_tournament_standings
4. rebuild_player_tournament_participation (NEW)
5. rebuild_player_tournament_totals (NEW)
6. rebuild_matchup_summary (NEW)
7. rebuild_generalstats (NEW)
8. rebuild_tournament_catalog_stats (existing)
```

Steps 4–7 are idempotent truncates or upsert-from-source passes. They must not mutate ground truth.

### Live ops (running tournament)

| Action | Updates |
|--------|---------|
| Result entry | `amiga_games`, standings for touched tournament |
| Finalize tournament | rating events, `amiga_player_stats`, then participation + totals for that tournament (incremental), matchup pairs for games in event, generalstats tail |
| Standings-only correction | standings → re-run participation for affected `tournament_id` only |

### Parity gates (add to verify suite)

| Check | Rule |
|-------|------|
| Participation ⊆ games | Every participation row has ≥1 game for `(player_id, tournament_id)` |
| Participation ⊇ games roster | Every `(player_id, tournament_id)` with ≥1 `amiga_games` row has a participation row |
| Participation ⊇ standings overall | Every overall standing row has a participation row (subset of games roster check) |
| Games rollup | `games`, W-D-L, goals on participation = `amiga_games` rollup for that player×event |
| Event points | `event_points = wins * 3 + draws` on every participation row |
| Avg goals | `avg_goals_for = ROUND(goals_for / games, 4)` (and against) when `games > 0`; NULL when `games = 0` |
| Rating join | `rating_before/delta/after/performance_rating` matches `amiga_rating_events` when event exists |
| Totals | `tournaments_played` = COUNT participation rows per player |
| Matchups | `SUM(games) = 2 × COUNT(amiga_games)` |
| WC medals (sample) | Spot-check vs Access `added_players` — reference report only |

---

## 9. Implementation execution

**Status:** Slices 0–14 **complete** (Jun 2026). Final handoff: [`archive/orchestration/agent-handoffs/2026-06-08-051-player-universe-slice-14.md`](archive/orchestration/agent-handoffs/2026-06-08-051-player-universe-slice-14.md). Plan checklist: [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md).

**Verify suite:**

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

**Surface expansion (slices 0–8, Jun 2026):** **Complete** — Tier A LB wings, profile honours/perf/moments, H2H, event-stats tab, honours LB polish. Handoff: [`archive/orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md`](archive/orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md). Overview deferred items: [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4.

**Deferred (potential):** Dedicated profile WC medals block; live incremental H2H/generalstats on result entry; `amiga_player_tournament_slice_totals`; tournament **Games** tab (scoped `amiga_games`); Tier C activity; `performance_rating − rating_before` column; `PeakRatingGameID` replay writer; `amiga-tournament-honours-rules.md` for edge-case WC medals.

---

## 10. DDL and modules (implemented Jun 2026)

SQL under `scripts/amiga/sql/`:

| File | Contents |
|------|----------|
| `010_player_tournament_participation.sql` | participation table + indexes (`event_points` on fresh install) |
| `014_participation_event_points.sql` | existing DBs: rename `points` → `event_points` |
| `015_performance_rating.sql` | `performance_rating` on rating events + participation |
| `016_participation_avg_goals.sql` | `avg_goals_for`, `avg_goals_against` on participation |
| `017_event_finish_position.sql` | `event_finish_position`, `best_knockout_phase` on participation (slice 0) |
| `011_player_tournament_totals.sql` | totals table |
| `012_player_matchup_summary.sql` | H2H table |
| `013_generalstats.sql` | server records (no streak columns) |

Python modules:

| Module | Role |
|--------|------|
| `scripts/amiga/participation_placement.py` | `derive_event_finish_position` (tiers A–D), `derive_best_knockout_phase`; PHP parity in `includes/amiga_participation_placement.php` |
| `scripts/amiga/player_tournament_participation.py` | games-driven rebuild + WC medal refresh + live finalize hook |
| `scripts/amiga/player_matchup_summary.py` | bulk H2H rebuild |
| `scripts/amiga/server_records.py` | `amiga_generalstats` rebuild |
| `scripts/amiga/verify_player_participation.py` | participation + totals parity |
| `scripts/amiga/verify_player_matchups.py` | H2H parity |
| `scripts/amiga/replay.py` | orchestrates derived rebuilds after finalize |

PHP read paths:

| File | Role |
|------|------|
| `includes/amiga_player_tournament_lib.php` | participation + totals + honours LB |
| `includes/amiga_player_matchup_lib.php` | top opponents |
| `includes/amiga_profile_blocks.php` | recent tournaments, top opponents |
| `includes/amiga_records_*.php` | Hall of Fame |
| `amiga/ops/modules/finalize_tournament.php` | incremental participation after finalize |

---

## 11. Open decisions (owner: Dagh)

| # | Question | Default recommendation |
|---|----------|------------------------|
| 1 | Denormalize `tournament_name` on participation rows? | **Yes** — avoids join on every profile row; rebuild on catalog rename is rare |
| 2 | WC medal rules v1 — knockout vs overall? | **Knockout/placement scopes first**; document exceptions per WC in honours helper |
| 3 | `amiga_player_tournament_slice_totals` now or later? | **Later** — until honours wing tabs are designed |
| 4 | Activity period semantics | **Event calendar year** (`YEAR(tournaments.event_date)`) preferred over synthetic `game_date` UTC months |
| 5 | Full tournament history on profile vs paginated API | **Shipped:** dedicated `/amiga/player-tournaments.php`, all rows, client sort (no pagination) |
| 6 | Access medal parity in UI | **Admin/tooling only** — never block ship on Access `added_players` match |

---

## 12. Migration register

Merged into [`amiga-data-contract.md`](amiga-data-contract.md) table register (Jun 2026).

| Table | Layer | Writer | Status |
|-------|-------|--------|--------|
| `amiga_player_tournament_participation` | Derived | `replay` / `participation-rebuild`; live `finalize_tournament` + PHP ops | **Active** |
| `amiga_player_tournament_totals` | Derived | same + per-player re-aggregate on live finalize | **Active** |
| `amiga_player_matchup_summary` | Derived | `replay` / `matchup-rebuild` | **Active** |
| `amiga_generalstats` | Derived | `replay` / `generalstats-rebuild` | **Active** |
| `amiga_player_stats` | Derived | finalize | **Active** |
| `amiga_rating_events` | Derived | finalize | **Active** |
| `amiga_tournament_standings` | Derived | replay / standings | **Active** |
| `tournament_entrants` | Ground | live ops | **Active** (empty historical) |

**Verify suite (player universe):**

```powershell
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

---

## 13. Appendix — Access `added_players` replacement map

| Access field | Amiga authority (target) |
|--------------|-------------------------|
| `won`, `drawn`, `lost`, `gfor`, `gagainst` | `amiga_player_stats` |
| `rankpos` | rank query on `amiga_player_stats.Rating` |
| `goldmedals`, `silvermedals`, `bronzemedals` | `amiga_player_tournament_totals.wc_*` |
| `biggestwin`, `biggestdefeat` | `amiga_player_stats` extremes |
| `lasttournament` | `amiga_player_tournament_totals.last_tournament_id` |
| `activityrating` | **Skip** or replace with `tournaments_played` / event-year games (Tier C) |
| `opponent*` arrays | `amiga_player_matchup_summary` |

---

*Investigation sources: `amiga-realm-vision.md`, `amiga-data-contract.md`, `amiga-profile-v0.md`, `website-data-contract.md`, `scripts/amiga/sql/001_core.sql`, `scripts/amiga/replay.py`, online `league_standings.php` / SCH-008–010 migrations, conversation on player↔tournament richness (Jun 2026).*
