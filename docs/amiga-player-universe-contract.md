# Amiga player universe — derived data contract (intent)

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

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
| **A — Ship surfaces on existing data** | No new tables; document read paths | Leaderboard wings from `amiga_player_current`; moments from `*GameID` columns |
| **B — New derived tables** | DDL + replay/finalize writers | `amiga_player_event_snapshots`, `amiga_generalstats`, `amiga_player_matchup_summary` |
| **C — Product decision / defer** | Semantics TBD | Event-year activity calendars, full profile feast parity with online |

---

## 2. Explicit scope

### In scope (this contract)

- Present career + honours (`amiga_player_current`) — read policy and gaps
- Player ↔ tournament participation (event-local columns on snapshots + career rollups on current)
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

### Streak columns on career rows (`amiga_player_current`)

`WinningStreak`, `LongestWinningStreak`, `*Streak`, milestone facilitator columns may still be **written** by shared `PlayerState` for engine parity. **Amiga product must not read or display them.** No new features may depend on within-day game order.

---

## 3. Current state (Jun 2026)

**Player truth (slice 8):** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md). Present = `amiga_player_current`; timeline = `amiga_player_event_snapshots`. Retired: `amiga_player_stats`, `amiga_rating_events`, `amiga_player_tournament_participation`, `amiga_player_tournament_totals`.

### Ground truth (identity + results)

| Table | Player-relevant role |
|-------|---------------------|
| `amiga_players` | `id`, `name`, `country`, `display` |
| `amiga_games` | Canonical results; links `player_a_id`, `player_b_id`, `tournament_id` |
| `tournaments` | Event catalog; `player_count` is **Access catalog witness** (`[Tournament players].Players` at import) — not product participant count; do not patch to match forum truth |
| `tournament_entrants` | **Live registration** ground truth for generated events; empty for historical Access import |

### Derived truth (shipped)

| Table | Grain | Player use today |
|-------|-------|------------------|
| `amiga_player_current` | 1 row / player | Present career + honours; hero, LB sorts, rank |
| `amiga_player_event_snapshots` | 1 row / (player, tournament) | Event-local + career-as-of + honours-as-of + rating block; history, tournament lists |
| `amiga_game_ratings` | 1 row / game | Games list; per-game frozen ratings |
| `amiga_tournament_standings` | 1 row / (player, tournament, scope) | Tournament pages; **per-phase** points + ranks (see §5.2.1) |
| `amiga_player_matchup_summary` | 1 row / (player, opponent) | Profile top opponents |
| `amiga_generalstats` | 1 row (`id=1`) | Hall of Fame server records (no streak rows) |

**Retired (slice 8):** `amiga_player_stats`, `amiga_rating_events`, `amiga_player_tournament_participation`, `amiga_player_tournament_totals` — column manifests folded into `024_player_snapshots.sql`.

### Fragmentation (resolved Jun 2026)

Player↔tournament facts and career honours live in **snapshots + current**. H2H and server records use `amiga_player_matchup_summary` and `amiga_generalstats`. Player-universe slices 0–14: [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md). Event-snapshot migration slices 0–9: [`amiga-event-snapshot-implementation-plan.md`](amiga-event-snapshot-implementation-plan.md).

---

## 4. Player surfaces register

Each surface maps to **one primary derived source** (joins to `amiga_players` / `tournaments` for labels only).

| Surface | Route / entry | Primary read | Secondary | Tier |
|---------|---------------|--------------|-----------|------|
| **Hero + rank** | `/amiga/player/profile.php` | Present: `amiga_player_current.elo_rank`; TT: snapshot + `amiga_player_elo_rank_at_event` at cutoff | — | A (shipped) |
| **LB wing stat mosaic** | profile | Present: `amiga_player_current`; TT: latest `amiga_player_event_snapshots` row ≤ cutoff | peak tournament joins | A (shipped Jul 2026) |
| **Career strip** | profile | `amiga_player_current` | — | A (retired from UI Jul 2026) |
| **Honours strip** | profile | `amiga_player_current` honours columns | WC medals, wins, podiums | B (retired from UI Jul 2026) |
| **Performance rating highlight** | profile | `amiga_player_event_snapshots` | best + latest event | B (retired from UI Jul 2026) |
| **Moments / trophy games** | profile | Present: `amiga_player_current` `*GameID`; TT: snapshot `*GameID` + batched game fetch ≤ cutoff | bonanza ratio fallback ≤ cutoff | A (shipped Jul 2026) |
| **Videos nav pill** | profile player nav | Present: manifest shortcut + game index; TT: `amiga_player_videos_game_index(..., ctx)` | — | A (shipped Jul 2026) |
| **Rating chart** | `api/player_rating_history.php?realm=amiga` | `amiga_player_event_snapshots` → `tournaments` | — | A (shipped) |
| **Recent tournaments** | profile (5 rows) | `amiga_player_event_snapshots` | finish suffix + Winner + Perf | B (shipped) |
| **Full tournament history** | `/amiga/player/tournaments.php` | `amiga_player_event_snapshots` | sortable k2-table stack; segment filters World Cups · Perfect run · Wins · Podiums + country/year listboxes | B (shipped) |
| **Tournament standings / games** | `/amiga/tournament.php` | `amiga_tournament_standings` / `amiga_games` | k2-table render helpers in `amiga_tournament_lib.php` | B (shipped) |
| **Tournament hero (players stat)** | `/amiga/tournament.php`, `/amiga/live-tournament.php` | `amiga_tournament_participant_count()` — live: `tournament_entrants`; historical: `standing_players` → snapshots → games | Not `tournaments.player_count` (Access witness) | B (shipped Jul 2026) |
| **Tournament event stats** | `/amiga/tournament.php?view=event-stats` | `amiga_player_event_snapshots` | roster for one event; k2-table SSR | B (shipped) |
| **Games list** | `/amiga/player/games.php` | `amiga_games` + `amiga_game_ratings` | paginated; OK at scale | A (shipped) |
| **Single game** | `/amiga/game.php` | `amiga_games` + `amiga_game_ratings` | 1 row by `id` | A (shipped) |
| **Top opponents** | profile | `amiga_player_matchup_summary` | goals column; H2H links | B (shipped) |
| **H2H / Opponents wing** | `amiga/player/opponents/*` | `amiga_player_matchup_summary` | W/D/L · Goals · DDs tables; H2H placeholder | B (shipped Jun 2026) |
| **WC player stats** | `/amiga/world-cups/players/*.php` | `amiga_player_slice_*` (`world_cup`) | `amiga_world_cups_players_nav.php`; hub wing 3 — **not** an LB wing | B (shipped) |
| **Tier A LB wings** | `/amiga/leaderboards/rating.php`, `goals.php`, … | `amiga_player_current` | `amiga_lb_nav.php`; k2 LB SSR (`k2_lb_th` / `k2_lb_td`) | A (shipped) |
| **Performance rating LB** | `/amiga/leaderboards/performance-rating/best.php` (+ Top 100 · Perfect sub-wings) | `amiga_player_event_snapshots` | best / top-100 / perfect | B (shipped) |
| **Tournament honours LB** | `/amiga/leaderboards/tournament-honours.php` | `amiga_player_current` honours + `Rating` | `event_*` + `wc_*` | B (shipped) |
| **Hall of Fame** | `/amiga/hall-of-fame.php` | `amiga_generalstats` + ratio queries on current | WC panel; metric → LB deep links | B (shipped) |
| **Historical rating ladder** | `/amiga/leaderboards/rating.php?as=` (legacy `/amiga/history.php` → redirect) | `amiga_player_event_snapshots` cutoff reads | Time-travel ribbon; Δ column not on LB wing (History page retired Jun 2026) | A (slice 7) |
| **WC medals block (dedicated)** | profile | `amiga_player_current` honours | honours strip covers summary | B (deferred) |

**Rule:** New PHP must not aggregate `amiga_games` on profile/leaderboard hot paths. Games tab remains the intentional scan surface (paginated, per player). Sortable wide tables: [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md).

---

## 5. Target data model

### Layer diagram

```text
amiga_games (ground)
       │
       ├─► amiga_player_current            … present career + honours (1 row/player)
       ├─► amiga_player_event_snapshots    … sparse timeline (1 row/player×event played)
       ├─► amiga_game_ratings              … per-game facts (1 row/game)
       ├─► amiga_tournament_standings      … points tables per scope (authoritative for placement)
       ├─► amiga_player_matchup_summary    … directed H2H
       └─► amiga_generalstats              … server records
```

### 5.0 Derived stat placement — stored truth (Jun 2026)

**Authority:** Repo-wide habit — stored / precomputed truth on DB-backed hot paths ([`AGENTS.md`](../AGENTS.md), [`.cursor/rules/kool-workspace.mdc`](../.cursor/rules/kool-workspace.mdc), online [`website-data-contract.md`](website-data-contract.md)). Amiga layer rules: [`amiga-data-contract.md`](amiga-data-contract.md). **Default question before any new profile / leaderboard / tournament stat:**

> *What table should hold this at rebuild/finalize time, and what must verify enforce?*

Do **not** default to aggregating `amiga_games` (or joining many `amiga_game_ratings` rows) on page load — same anti-pattern as live `ratedresults` scans online.

#### Glossary

| Term | Meaning |
|------|---------|
| **Hot surface** | A user-facing page or API loaded often (profile, tournament history, sortable leaderboard). Not “we need a sort” — “this read path should stay fast.” |
| **Stored truth** | Value written at **finalize** or **`replay`** — rebuildable from ground truth, read cheaply later. |
| **Grain** | What one row represents (per game, per player×event, per player×event×phase, per player career). |
| **Denorm copy** | Same fact stored in a **second** table (or duplicated catalog columns on one row) so a read path avoids a join. One **canonical** writer; verify keeps copies equal. Not the same as “derive `goals_for / games` in PHP once per row.” |
| **Player-first / tournament-first** | Access pattern (`WHERE player_id = ?` vs `WHERE tournament_id = ?`). Usually **one junction table** with indexes for both — not automatically two tables. |

#### Decision tree (new player / tournament stat)

```text
1. What grain?
   per game           → amiga_game_ratings (+ amiga_games ground)
   per player×event   → amiga_player_event_snapshots event-local block (default)
   per player×event×phase → amiga_tournament_standings only
   per player career  → amiga_player_current (present) or snapshot career block (historical)

2. Event-wide or phase-scoped?
   all games in event → snapshot event-local block
   one league group / KO leg only → standings scope row — never snapshot alone for phase ranks

3. Source of volume counts?
   W-D-L, goals, event_points → roll up from amiga_games at finalize (on snapshot event-local columns)
   never from standings volume columns for event-wide stats

4. Store a column or compute on read?
   Policy default: STORE on snapshot/current at finalize if the surface is hot
   (profile, history table, leaderboard sort) — including ratios like avg goals per game.
   Exception: throwaway admin probe or genuinely trivial display-only formatting.

5. Second table (denorm copy)?
   Avoid — one snapshot row carries event-local + career + honours + rating block.
   Present reads use `amiga_player_current` (= latest snapshot projection).

6. Verify
   `verify-event-snapshots`, `verify-rating-events`, `verify-player-participation` (rollup checks).
```

#### Placement matrix (examples)

| Stat | Grain | Store where | Second copy? | Notes |
|------|-------|-------------|--------------|-------|
| Phase league points | player×event×phase | `amiga_tournament_standings` | No | Tournament page phase tabs |
| Event W-D-L, goals, `event_points` | player×event | snapshot event-local | No | From `amiga_games` rollup at finalize |
| Rating before/delta/after | player×event | snapshot event-local | No | Finalize commit boundary |
| `performance_rating` | player×event | snapshot event-local | No | [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| Avg goals for/against per game | player×event | snapshot `avg_goals_*` | No | At finalize; verify rollup |
| Career WC gold count | player | `amiga_player_current` honours | No | Running totals at finalize |
| Per-game adjustment | game | `amiga_game_ratings` | No | Games tab + `/amiga/game.php` |

#### Read-path rules (tournament vs player)

| Surface | Primary read | Scan `amiga_games` on load? |
|---------|--------------|-----------------------------|
| `/amiga/tournament.php` standings tabs | `amiga_tournament_standings` | **No** |
| `/amiga/tournament.php` bracket / KO leg | `amiga_games` | **Yes, but** `WHERE tournament_id = ?` only (indexed) |
| `/amiga/player/tournaments.php` | `amiga_player_event_snapshots` | **No** |
| `/amiga/player/games.php` | `amiga_games` + `amiga_game_ratings` | **Yes, but** per player, paginated — intentional scan surface |
| Hypothetical “top avg goals per event” LB | snapshot stored column + index | **No** |

**Index note:** `amiga_games` already has `idx_amiga_games_tournament` (`tournament_id`). A future tournament **Games** tab uses the same scoped query; a composite `(tournament_id, game_date, id)` is optional if `EXPLAIN` shows sort cost.

#### Anti-patterns

- Live `SUM` / `COUNT` over all `amiga_games` for profile or realm-wide leaderboards.
- Putting event-wide facts on every `amiga_tournament_standings` scope row (duplicates the same number per phase).
- Two tables for the same grain when one junction + two indexes suffices.
- Skipping store “because `goals_for` and `games` exist” on a **hot sortable leaderboard** — policy prefers a materialized column + verify.

### 5.1 Present career — `amiga_player_current`

**Grain:** one row per `player_id`. **Writer:** tournament finalize (`persist_tournament_event_snapshots`); network counts + peaks from cumulative matchups at same boundary.

**Authoritative for:** current `Rating`, career W/D/L, goals, DD/CS, victim/culprit network counts, peak/lowest rating, game-id pointers for extremes, **honours career rollups** (`event_*`, `wc_*`, `tournaments_played`).

**Not authoritative for Amiga product:** all `*Streak*` columns; facilitator streak columns (`ScoreStreak`, …).

**Historical career at cutoff:** read snapshot career block from `amiga_player_event_snapshots` (not a live scan of `amiga_games`).

### 5.2 Player ↔ tournament event row — `amiga_player_event_snapshots` (event-local block)

**Status:** **Retired separate table** — event-local columns live on `amiga_player_event_snapshots` (DDL `024`). Below documents the **column manifest** (unchanged semantics from former `amiga_player_tournament_participation`).

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
| `rating_before` | decimal | finalize in-memory event commit |
| `rating_delta` | decimal | finalize in-memory event commit |
| `rating_after` | decimal | finalize in-memory event commit |
| `performance_rating` | decimal NULL | finalize — chess-style event TPR; see [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| `games_in_event` | smallint | finalize event commit |
| `finalized_at` | datetime | tournament finalize timestamp |
| `is_winner` | tinyint | `event_finish_position = 1` (all tournaments). |
| `best_knockout_phase` | varchar(50) NULL | Deepest main-bracket KO round — `derive_best_knockout_phase()`; see honours rules §5 |

**Writer:** per-tournament finalize — in-memory participation-shaped rows from `amiga_games` rollup + standings placement (`participation_placement.py` / `includes/amiga_participation_placement.php`) + event rating commits; persisted on `amiga_player_event_snapshots`.

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
       ├─► in-memory event rating commits → snapshot event-local rating_* columns
       └─► tournaments (catalog denorm)
                 └─► amiga_player_event_snapshots (one row / player×event)
                 └─► amiga_player_current (present projection)
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
| **Event points** | player × tournament | `amiga_player_event_snapshots.event_points` | 3×W + 1×D over **all** games the player played in the event (`wins`/`draws` from `amiga_games` rollup). |

**Participation does not store phase points** and has **no column copied from standings `points`**. Phase tables on `/amiga/tournament.php` and group tabs always read `amiga_tournament_standings`.

**Writer sources (finalize → snapshot row):**

| Column group | Source | Notes |
|--------------|--------|-------|
| `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against` | `amiga_games` rollup (all phases) | Not from standings volume columns |
| `event_points` | `wins * 3 + draws` (same rollup) | Former `points` / migration `014` |
| `event_finish_position`, `is_winner` | §5.2.2 / honours rules v2 | Single finish path; `is_winner` = finish 1 |
| `best_knockout_phase` | KO depth from standings | Populate on finalize |
| `rating_*`, `games_in_event`, `finalized_at` | finalize rating commits | persisted on snapshot row |
| Catalog denorm | `tournaments` | name, flags, dates |

**When the two tallies match:** pure single-phase leagues (e.g. London XXIII) — one phase, all games in that phase → `event_points` equals the only phase points row in standings.

**When they differ:** league+cup marathons and World Cups. Example **Athens LXXXV, Alkis P** — league phase (standings overall): **30 pts** (10W in 11 league games); **event_points**: **36** (12W including Final legs). Example **WC** — group phase points in standings per group; `event_points` sums all group + knockout games.

**UI read rules:**

| Surface | Points shown | Finish shown |
|---------|--------------|--------------|
| `/amiga/tournament.php` phase tables | standings `points` per scope | standings `position` per scope |
| `/amiga/player-tournaments.php` **Pts** column | `event_points` | **`event_finish_position`** ordinal (all events including WC) |
| Profile **recent tournaments** suffix | `event_points` only when single-phase; omitted for league+cup marathons and WCs | **`event_finish_position`** ordinal or — |

**Verify (`verify-player-participation`):** snapshot event-local games rollup vs `amiga_games`; rating identity on snapshots.

**Sign-off:** `python -m scripts.amiga prove` only — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).

**Read-path rule:** Profile tournament blocks and “events played” APIs read **`amiga_player_event_snapshots`**. Rating chart uses the same table’s event rating block.

### 5.3 Career tournament honours — `amiga_player_current` (honours block)

**Purpose:** O(1) career counts for hero lines, honours leaderboards, milestone-style thresholds — Amiga analogue of `player_league_totals`. **Stored on** `amiga_player_current` and mirrored on each snapshot (`honours_*` / `tournaments_*` columns).

**Grain:** one row per `player_id`.

**Primary key:** `player_id`.

| Column | Meaning |
|--------|---------|
| `tournaments_played` | running count at each finalize (honours block on snapshot + current) |
| `tournaments_won` | Same as `event_gold` (`event_finish_position = 1`) |
| `event_gold` / `event_silver` / `event_bronze` | Holistic finish 1 / 2 / 3 across **all** tournaments |
| `event_podiums` | `event_gold + event_silver + event_bronze` |
| ~~`wc_*`~~ | **Retired slice 0** — [`amiga_player_slice_totals`](amiga-world-cups-leaderboard-policy.md) / [`amiga_player_slice_at_event`](amiga-world-cups-leaderboard-policy.md) (`slice_key = 'world_cup'`); not on honours block |
| `last_event_date` | MAX `event_date` |
| `last_tournament_id` | tournament id at max chrono/date |

**Dropped (v2):** `cup_gold` / `cup_silver` / `cup_bronze`; column `podiums` renamed to `event_podiums`.

**Writer:** running honours totals in finalize (`honours_totals.py` / `amiga_ops_persist_tournament_event_snapshots`); batch replay carries `honours_by_player` across tournaments in memory.

**World Cup slice (locked Jun 2026):** [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) — `amiga_player_slice_totals` + `amiga_player_slice_at_event` (`slice_key = 'world_cup'`); honours + WC game stats; TT via at-event timeline. **UI:** five sub-wings on **World Cups hub → Player stats** (`amiga_wc_players_wing_body.inc.php`). **V2 expansion** (goals texture, DDs & CSs, Opponents): [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md).

**Optional later:** additional `slice_key` values (`kitchen`, `milan`, …) on the same tables when product asks.

### 5.4 Head-to-head — `amiga_player_matchup_summary` + `amiga_player_matchup_at_event`

**Purpose:** Directed pair totals for top opponents, H2H APIs, future compare UI. **Direct port** of online [`player_matchup_summary`](website-data-contract.md).

**Grains:**

| Table | Grain | Role |
|-------|-------|------|
| `amiga_player_matchup_at_event` | `(player_id, opponent_id, as_of_tournament_id)` | **Canonical timeline** — cumulative pair stats as of end of event E |
| `amiga_player_matchup_summary` | `(player_id, opponent_id)` | **Present projection** — latest cumulative row per pair |

| Column | Meaning |
|--------|---------|
| `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against` | Subject player perspective |
| `max_goals_for`, `max_goals_against`, `min_goals_for`, `min_goals_against`, `max_win_margin`, `max_loss_margin`, `max_draw_goals`, `max_goal_sum`, `min_goal_sum` | Per-pair goal extremes (SCH-031 / online SCH-019 parity) — nullable margins/draw peak until qualifying game exists |
| `dd_wins`, `dd_losses`, `cs_wins`, `cs_losses` | Double-dummy / clean-sheet pair counts (summary + at-event) |

**Source:** `amiga_games` (both perspectives), accumulated in memory during replay/finalize.

**Writer (Jun 2026):**

- **Tournament finalize:** `MatchupCumulative` applies games in event; `persist_matchup_at_event` + `upsert_matchup_summary`; network scalars on snapshots/current derived from pair counts (not per-game sets, not end-of-replay rescan).
- **Verify:** `verify-player-matchups` in `prove` — read-only oracle; no batch writer CLI.

**Policy:** [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md).

**Parity:** `SUM(games) = COUNT(amiga_games) × 2`; summary = latest at-event row per pair by **chrono** `(event_date, event_chrono, as_of_tournament_id)` — tournament id alone is not monotonic with time.

### 5.5 Server records — `amiga_generalstats` (NEW)

**Purpose:** Single-row (or id=1) server-wide record holders for HoF — port of online `generalstatstable`.

**Grain:** `id = 1`.

**Source:** scan `amiga_player_current` for career extremes + `amiga_games` / `amiga_game_ratings` for single-game records and peak-in-game.

**Include (Tier B):** most games, wins, goals, DDs, CSs, victims/culprits, biggest win margin, biggest draw, highest sum of goals, highest peak rating in a game, ratio leaders pointers.

**Include (Tier C — Jun 2026):** calendar-year peaks (`peak_year_games`, `peak_year_tournaments`), career honours (`tournaments_played`, `event_gold`, `wc_played`), geography counts (`countries_played_in`, `opponent_countries_faced`, `opponent_countries_beaten`). Policy [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md).

**Exclude:** longest match streaks, longest play-day/week streaks.

**Writer:** `scripts/amiga/server_records.py` — **realm snapshot track** ([`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md)): full row at each tournament finalize → `amiga_realm_snapshots` + `amiga_generalstats`. Ratio leaders persisted on row (not live SQL). Verify: `build_generalstats_payload` in `verify-realm-snapshots` only.

---

## 6. Tournament honours rules (Amiga-native)

**Authoritative doc:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) — event finish, podiums, WC medals, shared semi bronze, career rollups, and explicit rejection of phase ranks on participation.

Replace Access `added_players.goldmedals` / `silvermedals` / `bronzemedals` with derived rules on participation + standings. **Reference parity** against Access is tooling-only ([`standings_parity.py`](../scripts/amiga/standings_parity.py) pattern).

**Summary (v2):**

- **Event finish** → `event_finish_position` (nullable; WC podium = 1/2/3).
- **WC podium derivation** → knockout standings (`compute_wc_podium_finish_from_standings`); written as finish, not a separate medal column.
- **Career totals** → `event_*` + `wc_*` on `amiga_player_current` (and snapshot honours block).
- **Phase tables** → `amiga_tournament_standings` only (no `league_position` / `group_position` on participation).

### Cup vs league events

Use `tournaments.has_cup`, `has_league`, `is_cup` for honours slices and profile filters — not mutually exclusive in catalog.

---

## 7. Online inspiration map

| Online store | Amiga target | Port? |
|--------------|--------------|-------|
| `playertable` | `amiga_player_current` | **Done** |
| `player_period_league` | snapshot event-local block | **Done** |
| `player_league_award` | `event_finish_position`, `is_winner` on snapshot | **Done** |
| `player_league_totals` | `amiga_player_current` honours | **Done** |
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

Global `amiga_player_current.Rating` and snapshot event rating block commit at **tournament finalize**. See [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) · [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md).

### Rebuild order (full `python -m scripts.amiga prove`)

```text
1. import --recreate-schema (ground truth only)
2. replay — for each tournament in chrono order:
     → amiga_game_ratings (per game)
     → amiga_player_event_snapshots + amiga_player_current (per finalize)
     → network counts + peaks from cumulative matchups (per finalize)
     → amiga_player_matchup_at_event + amiga_player_matchup_summary (per finalize)
     → amiga_realm_snapshots + amiga_generalstats (incremental realm row per finalize)
     → in-memory PlayerState + MatchupCumulative carry forward
```

No post-replay tail batches for matchup, network, catalog, or realm. Corrections = **`prove`** ([`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)).

Steps 2 are idempotent. They must not mutate ground truth.

### Live ops (running tournament)

| Action | Updates |
|--------|---------|
| Result entry | `amiga_games`, standings for touched tournament |
| Finalize tournament | game ratings, snapshots + current, matchup at-event + summary, network + peaks on career block, realm snapshot + `amiga_generalstats` |
| Standings-only correction | standings rebuild + `python -m scripts.amiga prove` if ratings/snapshots affected |

### Parity gates (add to verify suite)

| Check | Rule |
|-------|------|
| Snapshot ⊆ games | Every snapshot row has ≥1 game for `(player_id, tournament_id)` |
| Snapshot ⊇ games roster | Every `(player_id, tournament_id)` with ≥1 `amiga_games` row has a snapshot row |
| Games rollup | event-local `games`, W-D-L, goals on snapshot = `amiga_games` rollup |
| Event points | `event_points = wins * 3 + draws` on every snapshot row |
| Rating identity | `rating_after = rating_before + rating_delta`; sum(game adjustments) = `rating_delta` |
| Current parity | `amiga_player_current` = latest snapshot per player (column-wise) |
| Honours monotonicity | honours counters on current match latest snapshot honours block |
| Matchups | `SUM(games) = 2 × COUNT(amiga_games)`; summary = latest at-event row per pair (chrono order) |
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
python -m scripts.amiga verify-realm-snapshots
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
| `012_player_matchup_summary.sql` | H2H present table |
| `026_matchup_at_event.sql` | H2H cumulative timeline |
| `013_generalstats.sql` | server records (no streak columns) |

Python modules:

| Module | Role |
|--------|------|
| `scripts/amiga/participation_placement.py` | `derive_event_finish_position` (tiers A–D), `derive_best_knockout_phase`; PHP parity in `includes/amiga_participation_placement.php` |
| `scripts/amiga/player_tournament_participation.py` | games-driven rebuild + WC medal refresh + live finalize hook |
| `scripts/amiga/matchup_cumulative.py` | in-memory pair totals + network derive |
| `scripts/amiga/matchup_persist.py` | at-event persist + summary upsert |
| `scripts/amiga/player_matchup_summary.py` | H2H upsert at finalize + verify oracle helpers |
| `scripts/amiga/server_records.py` | HoF payload oracle + realm persist helpers |
| `scripts/amiga/verify_player_participation.py` | participation + totals parity |
| `scripts/amiga/verify_player_matchups.py` | H2H parity |
| `scripts/amiga/replay.py` | tournament-order finalize loop (no tail batches) |

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
| `amiga_player_event_snapshots` | Derived | `replay` / `finalize_tournament` | **Active** |
| `amiga_player_current` | Derived | same (present projection) | **Active** |
| `amiga_player_matchup_summary` | Derived | `replay` / finalize | **Active** |
| `amiga_generalstats` | Derived | `replay` / finalize | **Active** |
| `amiga_player_tournament_participation` | Derived | — | **Retired** slice 8 |
| `amiga_player_tournament_totals` | Derived | — | **Retired** slice 8 |
| `amiga_player_stats` | Derived | — | **Retired** slice 8 |
| `amiga_rating_events` | Derived | — | **Retired** slice 8 |
| `amiga_tournament_standings` | Derived | finalize / standings | **Active** |
| `tournament_entrants` | Ground | live ops | **Active** (empty historical) |

**Verify suite (player universe + snapshots):**

```powershell
python -m scripts.amiga prove   # holy loop — preferred
# or individually:
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-event-snapshots
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

---

## 13. Appendix — Access `added_players` replacement map

| Access field | Amiga authority (target) |
|--------------|-------------------------|
| `won`, `drawn`, `lost`, `gfor`, `gagainst` | `amiga_player_current` |
| `rankpos` | `amiga_player_current.elo_rank` (present); `amiga_player_elo_rank_at_event` at cutoff |
| `goldmedals`, `silvermedals`, `bronzemedals` | `amiga_player_current.wc_*` |
| `biggestwin`, `biggestdefeat` | `amiga_player_current` extremes |
| `lasttournament` | `amiga_player_current.last_tournament_id` |
| `activityrating` | **Skip** or replace with `tournaments_played` / event-year games (Tier C) |
| `opponent*` arrays | `amiga_player_matchup_summary` |

---

*Investigation sources: `amiga-realm-vision.md`, `amiga-data-contract.md`, `amiga-profile-v0.md`, `website-data-contract.md`, `scripts/amiga/sql/001_core.sql`, `scripts/amiga/replay.py`, online `league_standings.php` / SCH-008–010 migrations, conversation on player↔tournament richness (Jun 2026).*
