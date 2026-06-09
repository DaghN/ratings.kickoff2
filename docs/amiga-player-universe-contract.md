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
| **Persist for reads** | Hot paths use materialized tables — no realm-wide or per-profile scans on `amiga_games` at page load |
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
| `amiga_tournament_standings` | 1 row / (player, tournament, scope) | Tournament pages; standings source for participation |
| `amiga_player_tournament_participation` | 1 row / (player, tournament) | Profile recent tournaments; honours context |
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
| **Hero + rank** | `/amiga/profile.php` | `amiga_player_stats` + rank subquery | `amiga_players` | A (shipped) |
| **Career strip** | profile | `amiga_player_stats` | — | A (shipped) |
| **Rating chart** | `api/player_rating_history.php?realm=amiga` | `amiga_rating_events` → `tournaments` | — | A (shipped) |
| **Recent tournaments** | profile (5 rows) | `amiga_player_tournament_participation` | — | B (shipped) |
| **Full tournament history** | profile expansion | `amiga_player_tournament_participation` | filters on `is_cup`, `country` | B (deferred UI) |
| **Games list** | `/amiga/games.php` | `amiga_games` + `amiga_game_ratings` | paginated; OK at scale | A (shipped) |
| **Moments / trophy games** | profile block | `amiga_player_stats` `*GameID` + single-game fetch | no scan | A |
| **Top opponents** | profile | `amiga_player_matchup_summary` | sort by `games` | B (shipped) |
| **H2H pair page** | future API | `amiga_player_matchup_summary` + game list optional | — | B (deferred) |
| **Leaderboard wings** | `/amiga/leaderboards/*` | `amiga_player_stats` + `amiga_player_tournament_totals` | Rating + honours wings | A/B |
| **Tournament honours LB** | `/amiga/leaderboards/tournament-honours.php` | `amiga_player_tournament_totals` | — | B (shipped) |
| **Hall of Fame** | `/amiga/hall-of-fame.php` | `amiga_generalstats` + ratio queries on stats | WC medal panel | B (shipped) |
| **WC medals on profile** | profile block | `amiga_player_tournament_totals` or participation filter | — | B (deferred) |

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

### 5.1 Career row — `amiga_player_stats` (existing)

**Grain:** one row per `player_id`. **Writer:** tournament finalize batch (`commit_heavy_player_derived`) + shared `PlayerState.to_db_row()`.

**Authoritative for:** current `Rating`, career W/D/L, goals, DD/CS, victim/culprit network counts, peak/lowest rating, game-id pointers for extremes.

**Not authoritative for Amiga product:** all `*Streak*` columns; facilitator streak columns (`ScoreStreak`, …).

**Tier A action:** Ship leaderboard wings and HoF ratio rows reading this table only — no schema change.

### 5.2 Player ↔ tournament participation — `amiga_player_tournament_participation` (NEW)

**Purpose:** Canonical **“player played in event”** fact with everything profile and honours need in one row. This is the Amiga analogue of online’s per-period participation row + award context, merged for tournament-shaped events.

**Grain:** one row per `(player_id, tournament_id)` where the player has **≥1 game** in that tournament (participation = results, not registration).

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
| `overall_position` | smallint | `amiga_tournament_standings` `scope_type='overall'`, `scope_key=''` |
| `points` | smallint | same standing row |
| `games` | smallint | same standing row |
| `wins` | smallint | same standing row |
| `draws` | smallint | same standing row |
| `losses` | smallint | same standing row |
| `goals_for` | smallint | same standing row |
| `goals_against` | smallint | same standing row |
| `rating_before` | decimal | `amiga_rating_events` |
| `rating_delta` | decimal | `amiga_rating_events` |
| `rating_after` | decimal | `amiga_rating_events` |
| `games_in_event` | smallint | `amiga_rating_events` |
| `finalized_at` | datetime | `amiga_rating_events.finalized_at` |
| `is_winner` | tinyint | `overall_position = 1` (marathon / kitchen win) |
| `wc_medal` | enum NULL | `none`, `gold`, `silver`, `bronze` — see §6 honours rules |
| `best_knockout_phase` | varchar(50) NULL | optional: deepest knockout scope reached (Phase C+) |

**Writer:** batch rebuild after standings + rating events for a tournament (full replay: end-of-pass rebuild all; live: after finalize + standings refresh for touched `tournament_id`).

**Not a substitute for:** `tournament_entrants` (registration), full multi-scope standings (group/knockout tables still read `amiga_tournament_standings`).

**Read-path rule:** Profile tournament blocks and “events played” APIs read **this table first**. Rating chart continues to use `amiga_rating_events` (same underlying facts; chart is specialized).

### 5.3 Career tournament rollups — `amiga_player_tournament_totals` (NEW)

**Purpose:** O(1) career counts for hero lines, honours leaderboards, milestone-style thresholds — Amiga analogue of `player_league_totals`.

**Grain:** one row per `player_id`.

**Primary key:** `player_id`.

| Column | Meaning |
|--------|---------|
| `tournaments_played` | COUNT participation rows |
| `tournaments_won` | COUNT `is_winner = 1` (overall position 1) |
| `wc_gold` / `wc_silver` / `wc_bronze` | COUNT by `wc_medal` on World Cup events |
| `cup_gold` / `cup_silver` / `cup_bronze` | Non-WC cup events (`is_cup` and not WC name pattern) — optional split |
| `podiums` | overall position ≤ 3 in any event |
| `last_event_date` | MAX `event_date` |
| `last_tournament_id` | tournament id at max chrono/date |

**Writer:** `GROUP BY player_id` from `amiga_player_tournament_participation` after participation rebuild.

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

Replace Access `added_players.goldmedals` / `silvermedals` / `bronzemedals` with derived rules on standings + catalog. **Reference parity** against Access is tooling-only ([`standings_parity.py`](../scripts/amiga/standings_parity.py) pattern).

### World Cup medals (`wc_medal`)

Apply only when tournament name matches World Cup pattern (same as `amiga_tournament_is_world_cup()`).

| Medal | Rule (v1 proposal) |
|-------|-------------------|
| **Gold** | Winner of World Cup final knockout tie OR overall position 1 when no knockout scope (verify per event) |
| **Silver** | Final runner-up OR `placement` scope “2nd” if modeled |
| **Bronze** | Bronze match winner OR third-place placement scope |

**Implementation note:** Many WCs use knockout scopes in `amiga_tournament_standings`. Medal derivation should consult `scope_type IN ('knockout','placement')` for that `tournament_id`, not overall league position alone. Lock exact mapping in a follow-up `amiga-tournament-honours-rules.md` if needed; participation rebuild calls a shared `derive_wc_medal(tournament_id, player_id)` helper.

### Marathon / kitchen wins (`is_winner`)

`overall_position = 1` on `scope_type='overall'`, `scope_key=''` — covers London XXIII-style marathons and kitchen round-robins.

### Cup vs league events

Use `tournaments.has_cup`, `has_league`, `is_cup` for honours slices and profile filters — not mutually exclusive in catalog.

---

## 7. Online inspiration map

| Online store | Amiga target | Port? |
|--------------|--------------|-------|
| `playertable` | `amiga_player_stats` | **Done** (split identity) |
| `player_period_league` | `amiga_player_tournament_participation` | **Pattern** — junction with stats |
| `player_league_award` | columns on participation (`wc_medal`, `is_winner`, position) | **Pattern** — denorm on junction |
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
| Participation ⊇ standings overall | Every overall standing row has a participation row |
| Rating join | `rating_before/delta/after` matches `amiga_rating_events` when event exists |
| Totals | `tournaments_played` = COUNT participation rows per player |
| Matchups | `SUM(games) = 2 × COUNT(amiga_games)` |
| WC medals (sample) | Spot-check vs Access `added_players` — reference report only |

---

## 9. Implementation execution

**Agent slices (authoritative):** [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md) — Slices 0–14 with verification commands and browser STOP gates.

**Starter prompt for a new agent chat:** [`orchestration/agent-handoffs/amiga-player-universe-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-player-universe-STARTER-PROMPT.md)

**Parallel track (not in slices 0–14):** Tier A leaderboard wings — [`amiga-realm-vision.md`](amiga-realm-vision.md) Phase A; pages only, no new tables.

**Deferred:** Tier C activity (`player_period_games` semantics TBD).

---

## 10. DDL and modules (implemented Jun 2026)

SQL under `scripts/amiga/sql/`:

| File | Contents |
|------|----------|
| `010_player_tournament_participation.sql` | participation table + indexes |
| `011_player_tournament_totals.sql` | totals table |
| `012_player_matchup_summary.sql` | H2H table |
| `013_generalstats.sql` | server records (no streak columns) |

Python modules:

| Module | Role |
|--------|------|
| `scripts/amiga/player_tournament_participation.py` | rebuild + WC medal derivation + live finalize hook |
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
| 5 | Full tournament history on profile vs paginated API | **Paginated** (20/page) reading participation table |
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
