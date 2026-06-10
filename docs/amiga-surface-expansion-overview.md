# Amiga surface expansion — overview (post player-universe)

**Status:** Reference overview (Jun 2026). **Purpose:** capture what the player-universe derived-data expansion made possible, what is already surfaced, and what is **ready** vs **potential** for short-term product work.  
**Execution:** [`amiga-surface-expansion-implementation-plan.md`](amiga-surface-expansion-implementation-plan.md) · starter prompt [`orchestration/agent-handoffs/amiga-surface-expansion-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-surface-expansion-STARTER-PROMPT.md)

**Authority (data policy):** [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 — store hot-path stats on derived tables at rebuild/finalize; do not aggregate `amiga_games` on profile/leaderboard load.

**Prior track (complete):** Player universe slices 0–14 — participation, totals, H2H, `amiga_generalstats`, HoF, tournament honours LB, full tournament history table. See [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §9.

---

## 1. Executive summary

The **derived layer is ahead of the UI**. Rebuild/finalize already maintains rich rows in `amiga_player_tournament_participation`, `amiga_player_tournament_totals`, `amiga_player_matchup_summary`, and full career columns on `amiga_player_stats`. The **richest single surface** today is `/amiga/player-tournaments.php` (sortable event history with W-D-L, goals, averages, points, rating journey, perf rating).

Most other pages still read like **profile v0**: thin career strip, compact recent tournaments, top opponents without goals or H2H deep links, tournament page on standings only, and only two leaderboard wings (Rating under Ladder + Tournament honours).

**Strategic pattern:** the participation row is the product atom. The highest-value short-term wins reuse that atom (and existing totals/stats) on **profile**, **tournament pages**, and **leaderboard wings** — mostly **read-path PHP**, not new writers.

---

## 2. Inventory gap (stored vs surfaced)

```text
STORED (rebuild/finalize)          SURFACED TODAY
─────────────────────────          ────────────────
participation (rich row)     →     player-tournaments.php ✓
                                   profile recent ✗ (thin)
                                   tournament.php ✗ (standings only)

tournament_totals            →     honours LB (partial)
                                   profile ✗ (count only — row already fetched)

matchup_summary              →     top opponents ✓ (W-D-L, games only)
                                   H2H pair page ✗

player_stats (full career)   →     career strip ✓ (subset)
                                   Tier A LB wings ✗ (4 missing)

generalstats + ratio leaders →     HoF ✓
                                   profile moments ✗

performance_rating           →     history column ✓
                                   discovery / compare ✗
```

---

## 3. Ready — data exists, UI missing (short-term candidates)

Items below need **no new derived tables** and **no new rebuild writers** unless noted. Work is primarily PHP read paths, wing pages, and copy/tooltips.

### 3.1 Profile honours strip

| | |
|---|---|
| **Source** | `amiga_player_tournament_totals` |
| **Ready fields** | `wc_gold/silver/bronze`, `tournaments_won`, `podiums`, `cup_gold/silver/bronze`, `last_event_date`, `tournaments_played` |
| **Note** | `profile.php` already calls `amiga_player_tournament_totals_row()` but only uses `tournaments_played` for the history link count |
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
| **Today** | `/amiga/tournament.php` — phase standings from `amiga_tournament_standings` only |
| **Ready columns** | Event W-D-L, goals, `avg_goals_*`, `event_points`, rating before/delta/after, `performance_rating`, `is_winner`, `wc_medal` |
| **Perf rating** | [`amiga-performance-rating.md`](amiga-performance-rating.md) — explicitly “not in v1” on tournament.php today |

### 3.5 Performance rating as discovery

| | |
|---|---|
| **Source** | `participation.performance_rating` (denorm from `amiga_rating_events`) |
| **Today** | Sortable column on player tournament history only |
| **Ready ideas** | Profile one-liner (best/worst event); realm LB “best perf in a single event” with min-games rule |
| **Non-goals (v1)** | `performance_rating − rating_before` column; phase-scoped TPR |

### 3.6 Career moments (trophy games)

| | |
|---|---|
| **Source** | `amiga_player_stats` `*GameID` pointers + single-row game fetch |
| **Contract** | §4 Tier A — “Moments / trophy games” — no scan |
| **Reference** | Online `player_feast_render_moments()` pattern |

### 3.7 Honours LB — cup medals and podiums

| | |
|---|---|
| **Source** | `amiga_player_tournament_totals` — `cup_*`, `podiums` already written |
| **Today** | Honours LB shows WC medals, tournaments won, tournaments played |
| **Gap** | Cup specialists and podium counts have no sortable home |

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
| **True WC finish beyond medals** | `overall_position` on WC rows is group rank; honours rules doc + Fix 4 |
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

## 6. Suggested priority (product, not execution order)

Execution order lives in the implementation plan. Product priority for **Amiga-native delight per effort**:

1. Profile honours strip (`tournament_totals` — already fetched on profile)
2. Tier A LB wings + HoF deep links
3. H2H pair page + goals on top opponents
4. Participation-backed roster on `tournament.php`
5. Perf rating discovery (profile highlight + optional LB)
6. Moments block
7. Honours LB podiums/cups + history filters + light recent-tournament enrich

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
