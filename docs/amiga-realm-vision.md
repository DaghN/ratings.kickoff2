# Amiga realm — data inventory, product vision, phased roadmap

**Status:** Investigation + phased delivery (Jun 2026). **Scope:** inventory and roadmap; implementation tracked in [`amiga-data-contract.md`](amiga-data-contract.md) migration table.

**Shipped since investigation:** Amiga hub nav v0 (`includes/amiga_hub_nav.php`) — **Ladder · Tournaments · Hall of Fame** on hub pages; HoF stub at `/amiga/hall-of-fame.php`. Leaderboards wing nav still Phase A backlog.

**Related:** [`amiga-data-contract.md`](amiga-data-contract.md) (layers + table register), [`amiga-profile-v0.md`](amiga-profile-v0.md), [`hub-ia-agreement.md`](hub-ia-agreement.md), [`website-data-contract.md`](website-data-contract.md).

---

## 1. Executive summary

### What Amiga is for

The **Amiga 500 realm** is the offline tournament ladder: ~27k canonical matches from historical Access `Scores`, synthetic chronology, Elo replay (K=32, start 1600), and **tournament-first** presentation (604 events, World Cups, kitchen leagues). It is **not** a live server — there is no “scene alive tonight,” no UTC league finalize, no account registration, and no milestone catalog.

The **online realm** is a mature hub: Status pulse, Activity charts, nine leaderboard wings, Milestones encyclopedia, Hall of Fame, and a profile feast fed by a wide derived-table stack on `playertable` + aggregates.

### Top 5 recommendations (priority order)

1. **Amiga hub IA v0 shipped** — three tabs (**Ladder · Tournaments · Hall of Fame**) via `amiga_hub_nav.php`; realm switcher still lands on `/amiga/rating.php`. **Next:** fourth **Leaderboards** tab when `/amiga/leaderboards/` wings exist.
2. **Phase A leaderboard wings as thin pages only** — Goals, DDs & CSs, Victims & Culprits, Peak rating already have **full column parity** in `amiga_player_stats`; replay + PHP post-game maintain them today. Only `/amiga/leaderboards/*.php` + shared nav are missing. **No Streaks wing** — match streaks are off the table (unknown real within-day play order; see [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks).
3. **HoF v1 = career extremes + ratio leaders from `amiga_player_stats`** plus a small **`amiga_generalstats`** (or replay `server_records.py` port) for single-holder server records; add **Amiga-native tournament honours** (WC medals, marathon league wins) from `amiga_tournament_standings` — do not mirror online UTC activity peaks, calendar play streaks, or **match streaks** in v1.
4. **Defer profile feast depth** until leaderboard + HoF ship; then add `amiga_player_matchup_summary` (direct port of online pattern) for top opponents / H2H — not live scans at 1.1k games/player.
5. **Explicit skips:** Milestones (deferred indefinitely), Status, Activity server pulse, League honours, Play & Setup, **match streaks** (unknown within-day order), cross-realm H2H — document rationale in hub copy, not empty stubs.

### The one product decision Dagh must make first

**Mirror the online leaderboard wing set under `/amiga/leaderboards/` vs a smaller Amiga-native hub centred on tournaments.**

| Option | Hub default | Leaderboards | HoF |
|--------|-------------|--------------|-----|
| **A — Mirror wings** | Ladder (`rating`) | Same nine wings minus Milestones + League honours; Activity peaks redefined later | Mostly port online record list |
| **B — Tournament-native (recommended)** | **Tournaments** or balanced **Ladder** | Five wings: Rating, Goals, DDs, Victims, Peak rating; **Tournament honours** as sixth wing or HoF section | Career extremes + **WC / league honours**; skip match streaks + UTC calendar records |

Option B matches offline play patterns (event bursts, medals, marathon leagues) without pretending the Amiga ladder is a live UTC server. Option A is faster to explain to online veterans but buries the Amiga differentiator.

---

## 2. Three-way inventory matrix

Legend: **Ship** = build for Amiga · **Skip** = no Amiga surface · **Amiga-native** = different semantics or new page · **Already** = data exists, page missing.

### Hub & navigation

| Surface / capability | Online page(s) | Online data source(s) | Amiga today | Amiga data already available? | New derived needed? | Recommendation | Notes |
|----------------------|----------------|------------------------|-------------|--------------------------------|---------------------|----------------|-------|
| **Status** | `status.php` | `resulttable`, `playertable`, `player_period_league`, `server_daily_activity`, … | None | N/A (no live server) | No | **Skip** | “Alive tonight” has no meaning offline |
| **Activity** | `activity.php` | `server_daily_activity`, `server_period_*`, APIs | None | N/A | No | **Skip** (v1) | Server pulse; optional later: realm games-per-year chart from `amiga_games` |
| **Play & Setup** | `join.php` | Account / client onboarding | None | N/A | No | **Skip** | No Amiga client join flow |
| **Milestones hub + wing** | `milestones.php`, `leaderboards/milestones.php` | `player_milestones`, `milestone_definitions` | None | No | No | **Skip** (deferred indefinitely) | Per Dagh — out of Amiga scope |
| **Realm switcher** | Header | — | `/amiga/rating.php` | — | No | **Ship** (update target) | Point to Amiga hub landing when built |
| **Amiga hub nav** | — | — | **`amiga_hub_nav.php`** on ladder / tournaments / HoF stub | — | No | **Shipped (v0)** | Three tabs; Leaderboards wing tab deferred — see §6 |
| **Games / match log (hub)** | `games.php` (off-hub) | `ratedresults` | None (realm-wide) | Ground truth in `amiga_games` | No | **Skip** (v1) | Per-player `amiga/games.php` is enough; realm highlights low value offline |
| **Cross-realm search** | `api/player_search.php?realm=all` | Both DBs | Shipped | `amiga_players` | No | **Already** | Profiles link correctly |
| **Cross-realm H2H** | `api/player_head_to_head.php` (online only) | `ratedresults` / `player_matchup_summary` | None | No aggregate | Yes if built | **Skip** (v1) | Different player ID spaces; Amiga H2H only inside realm |

### Leaderboard wings (wing-by-wing)

Online SQL sources verified from `site/public_html/leaderboards/*.php`. Amiga analogue: `amiga_players` + `amiga_player_stats` via `amiga_player_base_from_sql()`.

| Wing | Online page | Online data source(s) | Amiga today | Amiga data already available? | New derived needed? | Recommendation | Notes |
|------|-------------|------------------------|-------------|--------------------------------|---------------------|----------------|-------|
| **Rating** | `leaderboards/rating.php` | `playertable` | `/amiga/rating.php` | **Yes** — `Rating`, W/D/L, ratios, `AverageOpponentRating` | No | **Already** (move under hub) | Thin page exists; fold into `/amiga/leaderboards/rating.php` + wing nav |
| **Goals** | `leaderboards/goals.php` | `playertable` — `GoalsFor`, `GoalsAgainst`, averages, extremes, `BiggestWinDifference`, … | None | **Yes** — same columns on `amiga_player_stats` | No | **Ship** (page only) | `ORDER BY GoalsFor DESC`; replay populates via shared `PlayerState` |
| **DDs & CSs** | `leaderboards/double-digits.php` | `playertable` — `DoubleDigits`, `CleanSheets`, ratios, conceded cols | None | **Yes** | No | **Ship** (page only) | |
| **Streaks** | `leaderboards/streaks.php` | `playertable` longest match streaks + **`player_play_streaks`** (days/weeks) | None | Columns exist on `amiga_player_stats` from shared replay engine — **not product truth** | No | **Skip** | Real within-day match order unknown; synthetic `game_date` order is arbitrary for streaks. Calendar play streaks also skip (offline batch play ≠ UTC daily habit). See [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks |
| **Victims & Culprits** | `leaderboards/victims.php` | `playertable` network + inverse counts | None | **Yes** — full victim/culprit column set | No | **Ship** (page only) | Same `>` tie policy as online when porting copy |
| **League honours** | `leaderboards/league-honours.php` | `player_league_totals`, `player_league_slice_totals` | None | No UTC leagues | No | **Skip** | Replace with **Tournament honours** (Amiga-native) |
| **Milestones** | `leaderboards/milestones.php` | `player_milestones` | None | No | No | **Skip** (deferred indefinitely) | |
| **Activity peaks** | `leaderboards/activity-peaks.php` | `player_peak_period_games` → `player_period_games` | None | No period tables | **Yes** if meaningful | **Defer** → Amiga-native | Event-year or “games at one tournament day” semantics TBD; not UTC month/week |
| **Peak rating** | `leaderboards/peak-rating.php` | `playertable` — `PeakRating`, `LowestRating`, … | None | **Yes** — `PeakRating`, `LowestRating`, sentinels | No | **Ship** (page only) | Same establishment rules as online contract |

**Key insight:** Six of nine online wings (excluding Milestones, League honours, and **Streaks** — match streaks not valid offline) are **`playertable` scans**. Amiga already stores the same career columns in `amiga_player_stats`, written by `scripts/amiga/replay.py` (`PlayerState.to_db_row`) and `amiga_process_completed_game` — **stats exist, pages don’t** (streak columns exist too but must not be shown).

### Hall of Fame (record-by-record)

| Record (HoF row) | Online data source | Amiga equivalent | New derived? | Recommendation |
|------------------|-------------------|------------------|--------------|----------------|
| Most games (career) | `generalstatstable` | `MAX(NumberGames)` on stats — or generalstats row | Small **amiga_generalstats** preferred | **Ship** |
| Most games in one year/month/week/day | `player_peak_period_games` via `peak_month_leaderboard_query.php` | No period tables | **Yes** if porting | **Defer** → event-year native or skip |
| Most days/weeks in a row | `generalstatstable` + `player_play_streaks` | No | **Yes** | **Skip** (v1) — calendar streaks weak offline |
| Most wins / goals / DDs / CSs (career) | `generalstatstable` | `amiga_player_stats` columns | generalstats | **Ship** |
| Most opponents / victims / DD victims / CS victims | `generalstatstable` | stats columns | generalstats | **Ship** |
| Most goals in one game | `generalstatstable` + game id | `MostGoalsScored` on stats + `MostGoalsScoredGameID` | generalstats for holder date | **Ship** |
| Biggest win margin / draw / sum of goals | `generalstatstable` + game ids | stats extremes + game ids | generalstats | **Ship** |
| Highest peak rating (server record) | `generalstatstable.BiggestPeakRating` | scan `amiga_game_ratings.new_rating_*` or generalstats | generalstats | **Ship** |
| Longest win / undefeated / draw streaks | `generalstatstable` | `Longest*` on stats (non-authoritative) | — | **Skip** — same within-day order problem as streaks wing |
| Best attack/defense avg, goal ratio, win %, DD/CS % | `records_ratio_leaders.php` → `playertable` | same columns on `amiga_player_stats` | No (read-time) | **Ship** (read-time query) |
| **WC gold/silver/bronze** (Access) | `added_players` (reference) | Derive from `amiga_tournament_standings` + cup flags | **Tournament honours** aggregate | **Amiga-native Ship** |
| **Marathon league wins** (e.g. London XXIII) | — | `amiga_tournament_standings` overall `position=1` | Roll-up table optional | **Amiga-native Ship** |

### Profile feast (panel-by-panel)

| Panel | Online source | Amiga v0 | Data available? | Materialization | Recommendation |
|-------|---------------|----------|-----------------|-----------------|----------------|
| Hero | `playertable` | Shipped | Yes | stats + rank query | **Already** |
| Career strip | `playertable` | Shipped (subset) | Yes | stats | **Already** |
| Recent tournaments | — | Shipped | `amiga_tournament_standings` | derived | **Already** — deepen later |
| Rating chart | `amiga_game_ratings` API | Shipped | Yes | per-player ≤1.1k rows | **Already** |
| Played days/weeks heatmaps | `player_period_games` APIs | None | No | period table | **Defer** (Phase D) |
| Personal bests (busiest period) | `player_peak_period_games` | None | No | period + peak cache | **Defer** or event-native |
| Moments (trophy games) | `playertable` game ids + `ratedresults` | None | game ids on stats | 1-row lookups OK | **Ship** (small) |
| Games/month chart | `player_period_games` / API | None | No | period table | **Defer** |
| Top opponents / H2H / compare | `player_matchup_summary` APIs | None | No | **matchup summary table** | **Ship** Phase D |
| Milestones hero/garden | `player_milestones` | None | No | — | **Skip** |
| League beat | `player_league_award` | None | N/A | — | **Skip** |
| W/D/L · Goals · DDs tabs | matchup pages | None | No summary table | matchup table | **Defer** |

### Tournaments (Amiga-first)

| Surface | Online analogue | Amiga today | Data | Recommendation |
|---------|-----------------|-------------|------|----------------|
| Tournament index | — | `/amiga/tournaments.php` | `tournaments` | **Already** |
| Standings + bracket | — | `/amiga/tournament.php` | `amiga_tournament_standings` | **Already** |
| Profile tournament block | — | Recent 5 overall | standings | **Already** — add medals when honours exist |
| **Tournament honours LB** | — | None | standings roll-up | **Ship** Phase E |
| Cup winners / most events played | Access `added_players` | None | derivable | **Amiga-native Ship** |
| Cross-stage bracket promotion | — | Not shipped | — | Future (Track B gap) |

### Activity charts & games

| Surface | Online | Amiga | Recommendation |
|---------|--------|-------|----------------|
| Server Activity tab | `activity.php` + server period APIs | None | **Skip** |
| Realm activity view | — | None | Optional **Amiga-native**: games per calendar year (single aggregate query on `amiga_games`) — low priority |
| Hub games log | `games.php` | None | **Skip** — use per-player games |
| Per-player games | `player/games.php` | `/amiga/games.php` | **Already** |

---

## 3. Data layer inventory

| Store | Layer | Online | Amiga today | Shared engine? | Needed for Amiga? | Priority | Port / Skip / Replace |
|-------|-------|--------|-------------|----------------|------------------|----------|------------------------|
| **`playertable`** | Derived | Active | **`amiga_player_stats`** (+ `amiga_players` identity) | **Yes** — `scripts/ladder/player_state.py` via `replay.py` | **Already covered** | — | **Port** (split name; same columns) |
| **`ratedresults`** Elo cols | Derived | Active | **`amiga_game_ratings`** + **`amiga_games`** ground | Shared Elo/outcome PHP | **Already** | — | **Port** (split) |
| **`amiga_tournament_standings`** | Derived | — | Active | Amiga-only | **Yes** (honours) | High | **Amiga-native** |
| **`generalstatstable`** | Derived | Active (HoF) | None | `server_records.py` could port | **Yes** for HoF v1 | Medium | **Port** as `amiga_generalstats` |
| **`player_period_games`** | Derived | Active | None | SQL rebuild pattern portable | Only if activity peaks / calendars | Low | **Defer** / event semantics |
| **`player_peak_period_games`** | Cache | Active | None | — | Same as above | Low | **Defer** |
| **`player_play_streaks`** | Derived | Active | None | PHP module portable | **Skip** v1 | — | **Skip** |
| **`player_matchup_summary`** | Derived | Active | None | Same SQL pattern on `amiga_games` | Profile / H2H | Medium | **Port** (Amiga table name) |
| **`player_period_league`** + awards | Derived | Active | None | UTC leagues | **Skip** | — | **Skip** |
| **`server_daily_activity`** | Derived | Active | None | — | **Skip** | — | **Skip** |
| **`server_period_*`** | Derived | Active | None | — | **Skip** | — | **Skip** |
| **`player_milestones`** + definitions | Derived | Active | None | — | **Skip** (deferred) | — | **Skip** |
| **Access `Rankings`** | Reference | — | exports only | — | Parity tooling | Low | **Reference only** |
| **Access `added_players`** | Reference | — | exports / parity | — | Inform honours design | Low | **Replace** with derived honours |
| **Access `Tables` / WC tables** | Reference | — | `standings-parity` CLI | — | Parity only | Low | **Replace** by `amiga_tournament_standings` |
| **`reference_*`** (optional) | Reference | — | Partial | — | Parity | Low | Optional |

### `playertable` ↔ `amiga_player_stats` (summary)

Replay builds INSERT column list dynamically from `PlayerState.to_db_row()` — **every career stat column in DDL is populated** on full replay and incremental post-game. Identity fields (`Name`, `Country`) live on `amiga_players`; `Display` is on both.

**Not in `amiga_player_stats` (intentional):** account columns (`Email`, `JoinDate`, `PlayerRank`, prefs, feedback, profile URLs, `IsOnline`, …). **Milestones facilitators** (`ScoreStreak`, …) are stored — useful if milestones ever return, harmless otherwise.

**Gap vs online HoF only:** no `generalstatstable` row — server-wide single-holder records and aggregate totals are not materialized yet.

---

## 4. Read-path & scale analysis

Approximate query counts per page (Amiga today):

| Page | Queries (typical) | Heavy work | Breaks first when… |
|------|-------------------|------------|---------------------|
| `/amiga/rating.php` | 2 (leaderboard + game count) | Full scan ~473 stat rows | Fine at 10× players |
| `/amiga/profile.php` | 3 (player, rank, recent tournaments) + chart API | Chart API loads ≤1.1k rating rows/player | Chart JSON for busiest player |
| `/amiga/games.php` | 4+ (player, rank, count, page of 100) | Join `amiga_rated_games_from_sql()` with filters | Per-player scans OK; avoid realm-wide |
| `/amiga/tournaments.php` | 2 (count + 200 rows) | `tournaments` + `amiga_tournament_catalog_stats` join | Fine — **no** live `amiga_games` aggregation (see contract § Tournament index) |
| `/amiga/tournament.php` | 3–6 (meta, scopes, standings, optional knockout legs) | Per-tournament | Fine |
| **Future LB wing** | 1 | `amiga_player_stats` scan + sort | Same as online `playertable` wings |
| **Future HoF** | 1 generalstats + 4 peak queries + 6 ratio queries | Ratio queries cheap | Peak periods if ported without cache |
| **Future profile H2H** | N/A today | Live pairwise scan would be O(games) | **Must** materialize `matchup_summary` |

**Scale anchors (from contract):** ~27k games, ~473 players, busiest ~1.1k games/player.

| Pattern | Amiga scale verdict |
|---------|---------------------|
| Full-table leaderboard sort on `amiga_player_stats` | **Fine** (≈500 rows) |
| Per-player game list paginated | **Fine** |
| Per-player rating history API | **Fine** |
| Realm-wide `games.php` highlights | **Avoid** — full `amiga_games` scan |
| Activity calendars / period peaks | **Materialize** when built — same as online |
| Tournament honours roll-up | **One-time rebuild** from standings; cheap |

---

## 5. Writer architecture (for recommended new stores)

| Recommended store | Writer | Parity gate |
|-------------------|--------|-------------|
| **Leaderboard wings (no new table)** | Existing replay + `amiga_process_completed_game` | Already gated (`replay --limit 500` vs PHP `replay-to`) |
| **`amiga_generalstats`** | Port `scripts/ladder/server_records.py` to walk `amiga_games` + stats after each game or batch replay | Compare top holders to `MAX()` on stats + spot-check game ids |
| **`amiga_player_matchup_summary`** | SQL bulk rebuild from `amiga_games`; incremental upsert in post-game (mirror online P5) | `SUM(games) = COUNT(amiga_games)*2` |
| **`amiga_player_period_games`** (if activity) | SQL rebuild; define **period semantics first** (event date vs UTC) | Parity vs chosen semantics |
| **Tournament honours roll-up** | Batch from `amiga_tournament_standings` + `tournaments.is_cup`; incremental on post-game standings rebuild | Spot-check vs Access `added_players` medals (reference) |
| **Page-only surfaces** | No writer | — |

**Do not assume new tables for Phase A** — wings are SELECTs on `amiga_player_stats`.

---

## 6. Amiga hub IA proposal

### Recommended top-level nav

```
[ Ladder ] [ Tournaments ] [ Leaderboards ] [ Hall of Fame ]
```

| Tab | Default route | Role |
|-----|---------------|------|
| **Ladder** | `/amiga/leaderboards/rating.php` | Current Elo order — same as today’s rating page |
| **Tournaments** | `/amiga/tournaments.php` | Primary Amiga differentiator |
| **Leaderboards** | same default + wing tabs | Five wings v1 (+ Tournament honours wing or HoF section) |
| **Hall of Fame** | `/amiga/hall-of-fame.php` | Single-holder records + tournament heritage |

**Landing choice:** Either **Tournaments** (option B emphasis) or **Ladder** (continuity with today’s `/amiga/rating.php` switcher target). Update `includes/realm_switcher.php` `amiga` href when decided.

### Leaderboard wings under Amiga

- Path: `/amiga/leaderboards/{rating,goals,double-digits,victims,peak-rating}.php` — **no `streaks.php`** (match streaks skipped)
- Reuse patterns from `includes/lb_nav.php` — new **`includes/amiga_lb_nav.php`** with Amiga routes, `data-realm="amiga"`, Amiga profile links (`/amiga/profile.php`).
- Filters: port `lb_player_filters.php` concepts — Amiga has no “inactive 1 year” lobby; **provisional &lt;20 games** still applies for peak/ratio wings.

### Hall of Fame

- `/amiga/hall-of-fame.php` — structurally similar to online `hall-of-fame.php` but:
  - Profile links → `/amiga/profile.php`
  - LB deep links → `/amiga/leaderboards/…`
  - **Omit** match streak rows and day/week play streak rows
  - **Add** panel: World Cup medals, major cup wins, marathon league wins

### Chrome

- `html data-realm="amiga"` on all Amiga pages (already on shipped pages).
- **Do not** include online `hub_nav.php` on Amiga — use **`includes/amiga_hub_nav.php`** (**shipped** — three tabs; add Leaderboards when wings ship). Player pages omit hub tabs (same as online).
- Tint picker: same as online (realm-neutral).

---

## 7. Phased roadmap

| Phase | User-visible outcome | Tables | Pages / includes | Ops gates | Size | Deps |
|-------|---------------------|--------|------------------|-----------|------|------|
| **A — LB pages only** | Four new leaderboard wings + wing nav (Rating move + Goals, DDs, Victims, Peak); hub shell **partial** (v0 nav shipped) | None | `/amiga/leaderboards/*`, `amiga_lb_nav.php`; fourth hub tab — **no streaks wing** | None (read existing stats) | **S** | Hub nav v0 done |
| **B — HoF subset** | Amiga Hall of Fame: career + single-game + ratio rows | **`amiga_generalstats`** (new) | `/amiga/hall-of-fame.php`, `amiga_records_*.php` | Replay + post-game update generalstats | **M** | Phase A links |
| **C — Tournament honours** | WC medals LB + profile snippets; “most tournament wins” | Optional **`amiga_player_tournament_honours`** | LB wing or HoF section; profile block | Rebuild on standings post-game | **M** | Standings (done) |
| **D — Profile feast slices** | Top opponents, H2H, moments | **`amiga_player_matchup_summary`** | APIs + profile blocks | Post-game upsert + replay | **M** | — |
| **E — Period / activity (optional)** | Event-year peaks or games-per-month | **`amiga_player_period_games`** (+ peak cache) | Activity peaks wing or profile calendars | Define semantics first | **L** | Product decision |
| **Skipped** | Milestones, Status, Activity, League honours, Play & Setup, **match streaks**, calendar play streaks, cross-realm H2H | — | — | — | — | — |

---

## 8. Open questions for Dagh

1. **Hub landing:** Tournaments vs Ladder when switching realm?
2. **Leaderboard scope:** Full mirror (minus Milestones/League/Streaks) vs five wings + tournament honours?
3. **HoF:** Port online record list wholesale vs smaller set + Amiga tournament records upfront?
4. **Activity peaks:** Meaningful offline — calendar month on synthetic `game_date`, **event-year** (group by `tournaments.event_date` year), or skip?
5. **Calendar play streaks:** Skip (decided — offline batch play).
6. **Match streaks:** Skip entirely (decided — unknown real within-day order; [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks).
7. **Per-player W/D/L · Goals · DDs tabs:** Match online player nav or keep Profile + Games only?
8. **Access parity:** Show reference diffs for medals in admin/tooling only, or never surface?

---

## 9. Appendices

### A. Column mapping `playertable` → `amiga_player_stats`

| Category | `playertable` | `amiga_player_stats` | Gap? |
|----------|---------------|----------------------|------|
| Identity | `ID`, `Name`, `Country` | `player_id` → `amiga_players` | Split — not a gap |
| Display / eligibility | `Display` | `Display` | No |
| Current Elo | `Rating` | `Rating` | No |
| W/D/L + ratios | `NumberGames` … `LossRatio` | Same names | No |
| Goals | `GoalsFor` … `BiggestSumOfGoals` | Same | No |
| DD / CS | `DoubleDigits` … `CleanSheetsConcededRatio` | Same | No |
| Network | `DifferentOpponents` … `BiggestWinCulprits` | Same | No |
| Opponent rating | `SumOfOpponentsRating` … `LowestRatedCulprit` | Same | No |
| Rating extremes | `PeakRating`, `LowestRating`, ascent/descent | Same | No |
| Match streaks | `WinningStreak` … `LongestNonLossStreak` | Same column names (replay writes) | **Product gap** — columns populated but **must not be displayed**; order within a day is synthetic |
| Milestone facilitators | `ScoreStreak` … `LossMarginOneStreak` | Same | No |
| Game / victim pointers | `*GameID`, `*VictimID`, `*CulpritID` | Same | No |
| Account / online only | `JoinDate`, `PlayerRank`, `Email`, prefs, … | **Absent** | Intentional |
| Server HoF | `generalstatstable` | **Absent** | Needs new table |

**Highlighted gaps for product:** only **`generalstatstable` equivalent** and **period/matchup aggregates** — not career stat columns.

### B. Access `added_players` → proposed Amiga derived equivalents

| Access field | Proposed Amiga source | Notes |
|--------------|----------------------|-------|
| `won`, `drawn`, `lost`, `gfor`, `gagainst` | `amiga_player_stats` | Replay authority |
| `rankpos`, `rankpoints` | `amiga_player_stats.Rating` + rank query | Ignore Access points scale |
| `goldmedals`, `silvermedals`, `bronzemedals` | Derive from cup `tournaments.is_cup` + knockout/placement standings | Rules need definition (final / semi / bronze match) |
| `biggestwin`, `biggestdefeat` | `BiggestWinDifference`, `BiggestLossDifference` | On stats |
| `lasttournament` | Last `tournaments` via max `event_date` in standings | Profile snippet |
| `activityrating` | Skip or replace with games-in-last-year | Access-specific |
| `opponent*` arrays | `amiga_player_matchup_summary` when built | Reference only today |

### C. File / path checklist (future implementers)

| Item | Path |
|------|------|
| Amiga hub nav | `site/public_html/includes/amiga_hub_nav.php` |
| Amiga LB wing nav | `site/public_html/includes/amiga_lb_nav.php` |
| Leaderboard wings | `site/public_html/amiga/leaderboards/{rating,goals,double-digits,victims,peak-rating}.php` (no streaks) |
| Hall of Fame | `site/public_html/amiga/hall-of-fame.php` |
| HoF helpers | `site/public_html/includes/amiga_records_hof_links.php`, `amiga_records_ratio_leaders.php` |
| DB read helpers | Extend `site/public_html/includes/amiga_db.php` |
| Realm switcher | `site/public_html/includes/realm_switcher.php` — update `amiga` href |
| DDL (if needed) | `scripts/amiga/sql/003_generalstats.sql`, `004_matchup_summary.sql`, … |
| Writers | `scripts/amiga/replay.py`, `site/public_html/amiga/ops/modules/process_completed_game.php` |
| Docs | Register new tables in [`amiga-data-contract.md`](amiga-data-contract.md) |

---

*Investigation method: leaderboard PHP SQL review, `hall-of-fame.php` record trace, `001_core.sql` vs `playertable` schema, `replay.py` / `PlayerState` column parity, Amiga read-path review, Access discovery cross-walk. Local `ko2amiga_db` spot-check not run — `ko2amiga_config.local.php` absent in workspace; population inferred from shared replay engine (mandatory for parity gate).*
