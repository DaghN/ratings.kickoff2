# Amiga performance rating (event TPR)

**Status:** Shipped (Jun 2026)  
**Scope:** `ko2amiga_db` — one performance rating per player per finalized tournament  
**Related:** [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2

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
| **Display** | Integer like entry rating; NULL → em dash + column tooltip |
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
2. Full **`replay`** / **`prove`** — same finalize loop for all events (no batch repair CLI)

**Verify:** `verify-player-participation` — participation `performance_rating` must match `amiga_rating_events` when either side is non-NULL.

**Derived writes:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).

---

## Read paths

| Surface | Source |
|---------|--------|
| `/amiga/player/tournaments.php` | `amiga_player_tournament_participation` — sortable **Perf. rating** column |
| Profile highlight | `amiga_player_perf_rating_highlight()` — best event + latest event lines |
| Profile recent tournaments | `amiga_profile_recent_tournament_extras()` — **Perf NNN** when games ≥ 2 (compact suffix) |
| `/amiga/tournament.php` | `view=event-stats` — participation roster; **Perf. rating** column |
| `/amiga/leaderboards/performance-rating.php` | Best single-event perf per player (`amiga_lb_performance_rating_rows`) |
| `/amiga/player/games.php` status line | **Read-time** over filtered games — `amiga_player_games_list_performance_rating()` via `api/amiga_player_games_perf_rating.php` (async after paint); frozen `rating_a`/`rating_b` per game; not stored |

When the games tab filters to a **single tournament**, the list perf should match that event’s stored `participation.performance_rating` (same game set and frozen inputs).

---

## CLI

```powershell
mysql ko2amiga_db < scripts/amiga/sql/015_performance_rating.sql
python -m scripts.amiga prove
python -m scripts.amiga verify-player-participation
```

Full replay recomputes via finalize loop (no separate backfill needed after `replay`).

---

## Non-goals (v1)

- HoF “best performance in an event” deep link (LB wing shipped slice 5)
- `performance_rating − rating_before` column
- Phase-scoped performance (group-only TPR)
- Cross-realm or Access `activityrating` parity
