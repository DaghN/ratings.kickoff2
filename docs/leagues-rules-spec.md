# Leagues — rules & data contract (signed decisions)

**Status:** Rules locked for implementation (May 2026). Supersedes informal medal notes in archived [`status-period-competitions-wip.md`](archive/status-period-competitions-wip.md) for **ordering and persistence**.

**Related:** [`leagues-project.md`](leagues-project.md) (phase tracker) · [`website-data-contract.md`](website-data-contract.md) (tables, rebuild, periodic job) · [`status-period-competitions.md`](status-period-competitions.md) (Status UI)

---

## League types (8)

| `league_kind` | `period_type` | Standings source |
|---------------|---------------|------------------|
| `points` | `day` \| `week` \| `month` \| `year` | `player_period_league` |
| `activity` | same | `player_period_games` (`games` column) |

**Identity** of one league instance (no surrogate competition id):

```text
(league_kind, period_type, period_start)
```

`period_start` is a **UTC calendar anchor date** (same as existing aggregates): Monday for weeks, Y-m-01 for months, Jan 1 for years, calendar day for days.

---

## Period boundaries & when a league is final

All boundaries use **UTC** (`SET time_zone = '+00:00'` on writers and finalize job).

| `period_type` | Inclusive start | Exclusive end (`period_end`) |
|---------------|-----------------|--------------------------------|
| `day` | `period_start` 00:00:00 | `period_start + 1 day` 00:00:00 |
| `week` | Monday `period_start` 00:00:00 | +7 days 00:00:00 |
| `month` | first of month 00:00:00 | first of next month 00:00:00 |
| `year` | Jan 1 00:00:00 | Jan 1 next year 00:00:00 |

**Final by definition:** a league is **closed** when server time `now >= period_end`.

- Example: daily league for `2026-05-26` is final at **`2026-05-27 00:00:00` UTC** exactly.
- This matches existing Status meta (`end` = exclusive boundary) and MySQL `timestamp` in UTC session.

**Medal / achievement timestamp:** `period_end` is the canonical **“when”** the league ended and awards attach (not cron wall-clock). Job may run at `00:00:01` UTC but writes **`period_end`** on every award row and on `league_period.period_end`.

`finalized_at` on `league_period` = audit only (when the batch job persisted rows). **Product logic uses `period_end`.**

---

## Sorting — no shared medals

Exactly **one** player per rank 1, 2, 3. No shared gold/silver/bronze.

### Eligibility (points and activity)

Players with a row in **`player_period_league`** (points) or **`player_period_games`** (activity) for that period compete — **including `player_id` values with no `playertable` row** (deleted accounts; games still in `ratedresults`). `playertable` is used only for display name fallback (`#id` when missing), not to drop competitors.

Apply sort **after** restricting to players with **≥1 rated game** in the period (aggregate rows above).

### Points league (`league_kind = points`)

Descending strength, then tie-breakers in order:

1. **Points** (`player_period_league.points`)
2. **Goal difference** (`goal_difference`)
3. **Goals scored** (`goals_for`)
4. **Games played** (`played`)
5. **First game id** in the period — `ratedresults.id` of the player’s earliest game in that period (**lower id wins**)
6. **Same first game** — if two players still tied because they share that game row, the player who was **`idB` on that row** ranks **higher** (better) than `idA`

**First game in period (per player):** among all rows where the player is `idA` or `idB` and the game falls in `[period_start, period_end)`, take the row with minimum `(Date, id)` — i.e. `ORDER BY Date ASC, id ASC LIMIT 1`.

### Activity league (`league_kind = activity`)

1. **Games played** (`player_period_games.games`) — **no top-N cap**; full table defines order
2. **First game id** (same definition as above; lower wins)
3. **Same first game** — **`idB` ranks higher** than `idA`

### Podium

- **Rank 1** → gold + winner (`is_winner = 1`)
- **Rank 2** → silver
- **Rank 3** → bronze
- Fewer than three players → only award ranks that exist (e.g. one player → gold only)

---

## Recalculation

**Yes — full recompute required** for:

1. **Display order** on Status (currently naive row index; wrong under ties)
2. **`league_period` + `player_league_award` + `player_league_totals` + `player_league_slice_totals`** historical backfill (REP-012 + REP-013)

Standing **totals** in `player_period_league` / `player_period_games` stay as today; only **ranking / awards** change when tie-break rules are applied.

Implement **one shared sorter** (PHP for runtime, SQL or Python for rebuild) used by Status, finalize job, and parity checks.

---

## Stored truth (summary)

| Table | Role |
|-------|------|
| `league_period` | One row per league instance; **`period_end` stored**; `finalized_at` audit |
| `player_league_award` | **Player-centric** award history — all context on the row for fast profile reads |
| `player_league_totals` | Career counters across all 8 leagues |
| `player_league_slice_totals` | Career counters per **`(league_kind, period_type)`** — e.g. monthly points gold count |

`player_league_award` is **source of truth** for events.  
`player_league_totals` + `player_league_slice_totals` are **rebuilt from awards** after finalize (REP-012 / REP-013).  
Profile and League honours slices read slice totals, not live `GROUP BY` on awards.

Career milestone thresholds (`league_wins_10`, `league_wins_50`, `league_wins_100`, `league_wins_500`) count **`finish_rank = 1` in any of the 8 league types** (day/week/month/year × points/activity). One row in `player_league_award` with `is_winner = 1` = one career win. Totals live in **`player_league_totals.wins`**; optionally mirror first threshold cross into `player_milestones`.

---

## Player row — denormalized fields (no league lookup for profile)

Each `player_league_award` row carries at minimum:

| Field | Purpose |
|-------|---------|
| `player_id` | Who |
| `league_kind`, `period_type`, `period_start` | Which of 8 |
| `period_end` | **When** the league closed (milestone time) |
| `finish_rank`, `medal`, `is_winner` | Placement |
| `points`, `goal_difference`, `goals_for`, `played` | Points league snapshot (NULL for activity) |
| `games` | Activity snapshot (NULL for points) |
| `first_game_id` | Tie-break audit |
| `first_game_side` | `A` or `B` on that row |

Display names / icons come from **`milestone_definitions`** (Milestones track) — not duplicated on every row.

---

## Linking to a league (like `game.php?id=`)

Stable **instance** URL (Status deep-link; hash + query tolerated by JS):

```text
status.php?league_kind=points&period=week&start=2026-05-19
```

| Param | Values |
|-------|--------|
| `league_kind` | `points` \| `activity` |
| `period` | `day` \| `week` \| `month` \| `year` |
| `start` | `period_start` as `Y-m-d` (Monday for weeks) |

Status Leagues block opens the matching tab, key, and (when closed) frozen podium from **`player_league_award`**.

Optional future: `league.php` alias → same params. No numeric surrogate id required.

---

## Finalize job (production)

| Item | Decision |
|------|----------|
| Schedule | **Daily ~00:00:01 UTC** (or shortly after), server cron |
| Scope | All instances with `period_end <= now` and not yet finalized |
| Actions | Compute top 3 → insert `player_league_award` → upsert `player_league_totals` → set `league_period.finalized_at` → run milestone threshold checks |
| Post-game | **Does not** finalize; only maintains `player_period_league` / `player_period_games` per existing contract |

Register: **PER-003** in [`coordination/periodic-register.md`](coordination/periodic-register.md).

---

## UI / product (out of scope for this spec)

- Profile “story + eye candy” — placeholder in [`leagues-project.md`](leagues-project.md) only
- Status Leagues Daily games list — shipped; not blocking awards work

---

## Open edge cases (defaults unless Dagh overrides)

| Case | Default |
|------|---------|
| Player’s first game is a draw | Still counts as first game; `idB` rule applies only when **same** `first_game_id` between tied players |
| Zero rated games in period | No standing row; no award |
| Job missed a day | Next run finalizes all overdue `period_end` values (idempotent) |
| Re-finalize after rule change | REP-012 truncates awards + totals and rebuilds |

---

*Last updated: May 2026 — Dagh tie-break + `period_end` as achievement time.*
