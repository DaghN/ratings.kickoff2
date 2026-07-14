# Amiga performance rating (event TPR)

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** Shipped (Jun 2026). **Per-opponent (pair) TPR** added **SCH-044** (Jun 2026) — see [§ Per-opponent performance rating](#per-opponent-performance-rating-sch-044).  
**Scope:** `ko2amiga_db` — one performance rating per player per finalized tournament; plus one **directed pair** TPR per opponent (matchup tables)  
**Related:** [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2 · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md)

---

## Purpose

**Performance rating** (product label: **Perf. rating**) is the single Elo level that would produce the player’s **actual score** across all rated games in an event against the **frozen opponent ratings** used at finalize.

It answers: *“How strong did I play in this tournament?”* — distinct from **Adjustment** (`rating_delta`), which answers *“How did my ladder rating move given my entry rating?”*

Amiga-native only — no Access parity target.

---

## Definition

For player **P** in finalized tournament **T**, for each game **g** in **T**:

| Symbol | Source |
|--------|--------|
| `s_g` | Player’s score: `actual_score` from `amiga_game_ratings` (A side) or `1 − actual_score` (B side) |
| `R_opp_g` | Opponent’s **frozen** rating: `rating_b` / `rating_a` on the same row |

Find **R_perf** such that:

```text
Σ_g  E(R_perf, R_opp_g)  =  Σ_g  s_g
```

where `E(R, R_opp) = 1 / (1 + 10^((R_opp − R) / 400))` — same logistic as ladder Elo; **K does not apply**.

**Game set:** all phases in the event (same grain as `event_points`, `games_in_event`).

**Opponent inputs:** frozen batch-start ratings only — order-independent within the event ([finalize contract](amiga-tournament-finalize-rating-contract.md) §5.5).

---

## Product rules

| Rule | Behaviour |
|------|-----------|
| **Minimum games** | `games_in_event ≥ 2` — otherwise NULL |
| **Perfect 0% or 100%** | NULL (no finite logistic solution when every score is 0 or 1) |
| **Not finalized** | NULL (no `amiga_game_ratings` / rating event) |
| **Display** | Integer like entry rating; **∞** only for perfect **win** records (`is_perfect_event` on events; all wins with ≥2 games on pair/filter slices); all-loss and other NULL cases → narrow dash (`-`) + column tooltip |
| **vs entry delta** | Not surfaced in v1 |

---

## Storage

General placement rules (grain, stored truth, when to denorm): [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) **§5.0**.

| Table | Role |
|-------|------|
| `amiga_rating_events.performance_rating` | **Canonical** — written at finalize / backfill |
| `amiga_player_tournament_participation.performance_rating` | **Denorm copy** — player history reads |

Do **not** store on `amiga_tournament_standings` (phase-scoped, multiple rows per player).

**Writer order:**

1. `finalize_tournament` — after per-game `amiga_game_ratings`, before/with rating event insert (Python + PHP ops)
2. Full **`simul`** on **`ko2amiga_work`** (or oracle **`replay`** / **`prove`** on frozen DB) — same finalize loop for all events (no batch repair CLI)

**Verify:** `verify-player-participation` — participation `performance_rating` must match `amiga_rating_events` when either side is non-NULL.

**Derived writes:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).

---

## Read paths

| Surface | Source |
|---------|--------|
| `/amiga/player/tournaments.php` | `amiga_player_tournament_participation` — sortable **Perf. rating** column |
| `/amiga/tournament.php` | `view=event-stats` — participation roster; **Perf. rating** column |
| `/amiga/leaderboards/performance-rating/best.php` | Best single-event perf per player (`amiga_lb_performance_rating_rows`) — see [`amiga-performance-rating-leaderboard-policy.md`](amiga-performance-rating-leaderboard-policy.md) |
| `/amiga/leaderboards/performance-rating/top.php` | Fixed top 100 imperfect event ratings (`amiga_lb_performance_rating_top_rows`) |
| `/amiga/leaderboards/performance-rating/perfect.php` | All perfect runs at cutoff (`amiga_lb_performance_rating_perfect_rows`) — **∞** in Perf. column |
| `/amiga/leaderboards/performance-rating.php` | 302 → `best.php` (query preserved) |
| `/amiga/player/games.php` status line | **Read-time** over filtered games — `amiga_player_games_list_performance_rating()` via `api/amiga_player_games_perf_rating.php` (async after paint); frozen `rating_a`/`rating_b` per game; **∞** when `reason=perfect_win_record`; not stored |

When the games tab filters to a **single tournament**, the list perf should match that event’s stored `participation.performance_rating` (same game set and frozen inputs).

---

## Per-opponent performance rating (SCH-044)

Same TPR math, but the game set is **all rated games vs one opponent through the cutoff** (not one event). Answers: *“How strong did the hero play against this specific opponent?”* It is **directed** — the hero→opponent value and the opponent→hero value are independent rows.

| Property | Value |
|----------|-------|
| Game set | All rated games between the directed pair, tournament tuple ≤ cutoff |
| `R_opp_g` | Frozen `rating_b` / `rating_a` per game (same as event TPR) |
| Min games / perfect record | Same NULL rules (`< 2` games, all-win or all-loss → NULL) |
| Grain | Directed `(player_id, opponent_id)` |

### Storage (matchup tables — not rating_events)

| Table | Role |
|-------|------|
| `amiga_player_matchup_summary.performance_rating` | **Present** value per directed pair |
| `amiga_player_matchup_at_event.performance_rating` | **Cumulative through E** per directed pair (time travel: latest row ≤ cutoff) |

Written at **tournament finalize** by the cumulative matchup writer ([`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) §3, §5): recomputed only for **pairs played that event** (replay uses in-memory `(opponent_rating, score)` samples; warm/live reseeds touched pairs from `amiga_game_ratings`); untouched pairs carry the prior value forward. No batch repair CLI — wrong state → **`simul`** on work (or oracle **`replay`** / **`prove`**).

### Read paths

| Surface | Source |
|---------|--------|
| `/amiga/player/opponents/wdl.php` | **Perf.** column (last) — `amiga_player_matchup_summary` (present) / `amiga_player_matchup_at_event` (cutoff) via `amiga_matchup_snapshot_lib.php` |
| `/amiga/player/opponents/h2h.php` pair detail | `perf_rating_subject` = player→opponent row; `perf_rating_opponent` = opponent→player row — **stored**, no on-the-fly solve |

**Verify:** `verify-player-matchups` — re-solve pair TPR from `amiga_game_ratings` for sample pairs; summary `performance_rating` == latest at-event row (null-safe).

---

## CLI (oracle archaeology — frozen `ko2amiga_db` only)

```powershell
mysql ko2amiga_db < scripts/amiga/sql/015_performance_rating.sql
python -m scripts.amiga prove
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

**Forward:** DDL in bundle → **`simul`** on **`ko2amiga_work`**; verify steps run inside simul suite.

Full replay recomputes via finalize loop (no separate backfill needed after `replay`).

---

## Related product (perfect events)

Undefeated events (all wins, `games >= 2`) are excluded from perf rating but tracked separately as **Perfect** honours — see [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md). Do not infer perfect events from `performance_rating IS NULL` alone.

---

## Non-goals (v1)

- HoF “best performance in an event” deep link (LB wing shipped slice 5)
- `performance_rating − rating_before` column
- Phase-scoped performance (group-only TPR)
- Cross-realm or Access `activityrating` parity
