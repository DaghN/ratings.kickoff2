# Amiga surface expansion — overview (post player-universe)

**Status:** **Complete** (Jun 2026) — slices 0–8 shipped; see handoff [`archive/orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md`](archive/orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md). **Purpose:** capture what the player-universe derived-data expansion made possible, what is surfaced, and what remains **potential**.  
**Execution:** [`amiga-surface-expansion-implementation-plan.md`](amiga-surface-expansion-implementation-plan.md) (all slices done)

**Authority (data policy):** [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 — store hot-path stats on derived tables at rebuild/finalize; do not aggregate `amiga_games` on profile/leaderboard load.

**Prior track (complete):** Player universe slices 0–14 — participation, totals, H2H, `amiga_generalstats`, HoF, tournament honours LB, full tournament history table. See [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §9.

---

## 1. Executive summary

Rebuild/finalize maintains rich rows in `amiga_player_event_snapshots` + `amiga_player_current` (career + per-event facts, including honours rollups), `amiga_player_matchup_at_event`, and realm snapshots on `amiga_generalstats`. Legacy `amiga_player_tournament_participation`, `amiga_player_tournament_totals`, and `amiga_player_stats` were retired at snapshot slice 8 ([`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md)). **Surface expansion (slices 0–8)** shipped read-path UI on profile, tournament pages, seven leaderboard wings, HoF deep links, and H2H pair page — see [`amiga-profile-v0.md`](amiga-profile-v0.md) and [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §4.

**Richest surfaces:** `/amiga/player-tournaments.php` (full event history); `/amiga/profile.php` (honours, perf, moments, recent tournaments, top opponents + H2H); `/amiga/tournament.php?view=event-stats`.

**Remaining work** is **§4 Potential** only (new tables, tournament games tab, slice totals, activity charts, etc.) — not “hidden data on existing tables.”

---

## 2. Inventory gap (stored vs surfaced)

```text
STORED (rebuild/finalize)          SURFACED (Jun 2026)
─────────────────────────          ───────────────────
participation (rich row)     →     player-tournaments.php ✓ (filters: WC, cups, country)
                                   profile recent ✓ (finish + Winner + Perf)
                                   tournament.php event-stats ✓

tournament_totals            →     honours LB ✓ (WC + cup medals, podiums)
                                   profile honours strip ✓

matchup_summary              →     top opponents ✓ (goals, H2H links)
                                   /amiga/h2h.php ✓

player_stats (full career)   →     career strip ✓
                                   Tier A LB wings ✓ (goals, DD, victims, peak)
                                   profile moments ✓ (*GameID; peak game ID pending replay)

generalstats + ratio leaders →     HoF ✓ + LB deep links ✓

performance_rating           →     history + event-stats columns ✓
                                   profile highlight + perf LB wing ✓
```

---

## 3. Ready — data exists, UI missing (short-term candidates)

*Planning-time slice briefs (pre–Jun 2026). Most items below **shipped** in surface expansion slices 0–8 — see **§2** for current surfaced inventory. Rows still saying **Today** / **Gap** are historical unless §4 says otherwise.*

Items below need **no new derived tables** and **no new rebuild writers** unless noted. Work is primarily PHP read paths, wing pages, and copy/tooltips.

### 3.1 Profile honours strip

| | |
|---|---|
| **Source** | `amiga_player_current` honours columns (via `amiga_player_tournament_totals_row()`) |
| **Ready fields** | `wc_gold/silver/bronze`, `tournaments_won`, `event_podiums`, `event_gold/silver/bronze`, `last_event_date`, `tournaments_played` |
| **Note** | `profile.php` calls `amiga_player_tournament_totals_row()` — helper name is legacy; reads `amiga_player_current` |
| **Contract** | §4 marks “WC medals on profile” deferred — data plumbing is done |

### 3.2 Tier A leaderboard wings (pages only)

| | |
|---|---|
| **Source** | `amiga_player_stats` via `amiga_player_base_from_sql()` |
| **Wings** | Goals, DDs & CSs, Victims & Culprits, Peak rating |
| **Reference** | Online `site/public_html/leaderboards/*.php`; same column parity on Amiga stats from replay `PlayerState.to_db_row()` |
| **Nav** | Extend `includes/amiga_lb_nav.php`; HoF `amiga_records_hof_lb_href()` can deep-link once wings exist |
| **Realm vision** | [`amiga-realm-vision.md`](amiga-realm-vision.md) Phase A — **no streaks wing** |

### 3.3 Head-to-head pair page + richer top opponents

| | |
|---|---|
| **Source** | `amiga_player_matchup_summary` (directed pair: games, W-D-L, goals_for, goals_against) |
| **Today** | Profile top opponents: opponent link, W-D-L, games — goals omitted |
| **Gap** | No `/amiga/h2h.php` (or API) for two-player summary; online analogue `api/player_head_to_head.php` |
| **Games list** | Optional link to filtered `games.php` — paginated scan is allowed per contract |

### 3.4 Tournament page — event stats from participation

| | |
|---|---|
| **Source** | `amiga_player_tournament_participation` indexed by `(tournament_id, player_id)` |
| **Shipped** | `/amiga/tournament.php?view=event-stats` — participation roster per event: W-D-L, GF/GA/GD, GF/g, GA/g, Pts, Rating, Adj., New rating, **Perf. rating** ([`amiga-performance-rating.md`](amiga-performance-rating.md)); WC **Medal** column (not group rank) |
| **Also** | Standings tab(s) from `amiga_tournament_standings`; World Cups default to event-stats tab (Jun 2026) |
| **Also** | **Games** tab (`?view=games`) — scoped `amiga_games` read via `idx_amiga_games_tournament`; player filter from participation roster |

### 3.5 Performance rating as discovery

| | |
|---|---|
| **Source** | `participation.performance_rating` (denorm from `amiga_rating_events`) |
| **Shipped** | Sortable **Perf. rating** on `player-tournaments.php` and tournament event-stats; profile best/recent highlight; `/amiga/leaderboards/performance-rating.php` wing |
| **Non-goals** | `performance_rating − rating_before` column; phase-scoped TPR |

### 3.6 Career moments (trophy games)

| | |
|---|---|
| **Source** | `amiga_player_stats` `*GameID` pointers + single-row game fetch |
| **Contract** | §4 Tier A — “Moments / trophy games” — no scan |
| **Reference** | Online `player_feast_render_moments()` pattern |

### 3.7 Honours LB — cup medals and podiums

| | |
|---|---|
| **Source** | `amiga_player_current` — `event_*`, `wc_*`, `event_podiums`, `tournaments_played` |
| **Today** | Honours LB shipped — event + WC medal columns, podiums, events played |
| **Gap** | *(closed Jun 2026 — was cup/podium sortable home pre–v2)* |

### 3.8 Tournament history filters (partial)

| | |
|---|---|
| **Source** | Participation denorm: `is_cup`, `country` (+ existing WC name pattern) |
| **Today** | All / World Cups pills on `player-tournaments.php` |
| **Ready** | Cups-only, country/event-location filters (client or server) |

### 3.9 Profile recent tournaments — light enrich

| | |
|---|---|
| **Source** | Same participation rows as history table |
| **Policy** | Keep compact; respect `event_points` suffix rules (§5.2.1 — omit for league+cup marathons and WCs) |
| **Candidates** | `performance_rating`, `avg_goals_for`, `is_winner` / medal badge |

### 3.10 Career strip enrichment (optional, same slice family)

| | |
|---|---|
| **Source** | `amiga_player_stats` already loaded via `amiga_player_load()` |
| **Not shown today** | Goal ratio, avg goals, DD/CS counts — available without extra queries if load extended |

---

## 4. Potential — needs product rules, schema, or explicit deferral

Not “blocked forever”; treat as **medium bets** or **parallel tracks** unless the implementation plan explicitly includes them.

| Idea | Why potential, not ready |
|------|---------------------------|
| **`amiga_player_tournament_slice_totals`** | Phase-native honours (group vs event); new table + writer |
| **Tournament games tab** | Scoped `amiga_games` scan allowed but new surface; composite index TBD |
| **WC holistic `event_finish_position`** | Policy locked — [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md); medals + shared semi bronze first; numeric WC finish deferred to import job |
| **Event finish migration (`overall_position` → `event_finish_position`)** | **Done** Jun 2026 — [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md); migrations `017`–`019` |
| **Live incremental H2H / generalstats on finalize** | Batch replay authoritative today |
| **Activity / `player_period_games`** | Tier C; offline burst semantics TBD |
| **Cross-realm H2H** | Different player ID spaces |
| **Match streaks / calendar streaks** | Within-day order unknown — **skip** per realm contract |
| **Milestones catalog** | Deferred indefinitely |
| **Leaderboard “best avg goals in an event”** | Policy-ready (stored `avg_goals_*` on participation) but needs min-games / min-events rules |
| **Rating chart event annotations** | Nice-to-have; chart already reads `amiga_rating_events` |

---

## 5. Correctly out of scope (do not surface)

Document rationale in hub copy, not empty stubs:

- Match streak columns on `amiga_player_stats`
- Online Status / Activity pulse / UTC league honours / Milestones
- Cross-realm H2H

---

## 6. Shipped priority order (slices 0–8, Jun 2026)

All items below are **done** — retained for history:

1. Profile honours strip · 2. Tier A LB wings + HoF deep links · 3. H2H + top opponents goals · 4. Tournament event-stats tab · 5. Perf rating discovery · 6. Moments block · 7. Honours LB + history filters + recent enrich · 8. Documentation closure

---

## 7. Key files (read paths today)

| Area | Path |
|------|------|
| Profile | `site/public_html/amiga/profile.php`, `includes/amiga_profile_blocks.php` |
| Tournament history | `site/public_html/amiga/player-tournaments.php` |
| Participation read | `includes/amiga_player_tournament_lib.php` |
| Matchup read | `includes/amiga_player_matchup_lib.php` |
| Tournament page | `site/public_html/amiga/tournament.php`, `includes/amiga_tournament_lib.php` |
| LB nav | `includes/amiga_lb_nav.php`, `includes/amiga_hub_nav.php` |
| HoF | `site/public_html/amiga/hall-of-fame.php`, `includes/amiga_records_hof_links.php` |
| Honours LB | `site/public_html/amiga/leaderboards/tournament-honours.php` |
| Rating ladder | `site/public_html/amiga/rating.php` |
| Policy | `docs/amiga-player-universe-contract.md` §4–§5 |
| Perf rating | `docs/amiga-performance-rating.md` |
| Realm roadmap | `docs/amiga-realm-vision.md` |

---

## 8. Verification baseline (no regression)

After UI slices, run existing suite — surfaces must not break derived invariants:

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

This track adds **no new verify CLI** unless a slice introduces stored columns (none planned in v1).
