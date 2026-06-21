# Amiga cumulative matchup at event — policy

**Status:** **Locked** (Jun 2026).  
**Implementation plan:** [`amiga-matchup-at-event-implementation-plan.md`](amiga-matchup-at-event-implementation-plan.md)  
**Parent:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.4

---

## 1. Executive summary

Amiga head-to-head truth moves from **end-of-replay bulk SQL** to **tournament finalize commits**, matching the event-snapshot habit.

| Table | Grain | Role |
|-------|-------|------|
| **`amiga_player_matchup_at_event`** | `(player_id, opponent_id, as_of_tournament_id)` | **Canonical timeline** — cumulative directed pair stats **as of end of event E** |
| **`amiga_player_matchup_summary`** | `(player_id, opponent_id)` | **Present projection** — latest cumulative row per pair; upserted at each finalize |

**Network career scalars** (`DifferentOpponents`, `DifferentVictims`, …) on snapshots/current remain **product fields**, but are **derived** from the pairwise table at finalize (`COUNT` with filters), not from per-game set gymnastics or end-of-replay rescan.

**Commit boundary:** tournament finalize only (same as snapshots). No post-replay matchup rebuild.

---

## 2. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **M1** | **Cumulative, not event-local** | Each at-event row is **all-time vs that opponent through end of E**, not games within E only |
| **M2** | **Sparse participants** | Write at-event rows only for **players with ≥1 game in E**; one row per opponent they have **ever** faced through E |
| **M3** | **Present = summary** | Hot reads (future Opponents wing) use `amiga_player_matchup_summary`; no live scan of `amiga_games` |
| **M4** | **Network from pairs** | `different_opponents` = pair count; `different_victims` = pairs with `wins > 0`; culprits/DD/CS variants per column rules in §4 |
| **M5** | **No end-of-replay batch** | `replay` / `prove` must not call `matchup-rebuild` or network rescan; finalize writes everything |
| **M6** | **Peaks incremental** | `PeakRating` / `LowestRating` updated at each finalize from `rating_after` (no `commit_heavy` patch) |
| **M7** | **HoF deferred** | `amiga_generalstats` end-of-replay rebuild removed from holy loop until realm-snapshot slice |
| **M8** | **Catalog per finalize** | `amiga_tournament_catalog_stats` already refreshed per tournament; drop `rebuild_all_catalog_stats` tail |

---

## 3. Pair row shape

Directed `(player_id → opponent_id)`:

| Column | Meaning |
|--------|---------|
| `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against` | Cumulative vs opponent through E |
| `max_goals_for`, `max_goals_against`, `min_goals_for`, `min_goals_against`, `max_win_margin`, `max_loss_margin`, `max_draw_goals`, `max_goal_sum`, `min_goal_sum` | Cumulative goal extremes through E (SCH-031) |
| `dd_wins` | Games subject scored ≥10 vs opponent |
| `dd_losses` | Games opponent scored ≥10 vs subject |
| `cs_wins` | Games subject clean sheet vs opponent (`goals_against = 0`) |
| `cs_losses` | Games opponent clean sheet vs subject (`goals_for = 0`) |

At-event rows also denorm `event_date`, `event_chrono` from `as_of_tournament_id` for cutoff queries.

---

## 4. Network scalar derivation (career block)

From cumulative pairs for player P at end of event E:

| Career column | Rule |
|---------------|------|
| `DifferentOpponents` | `COUNT(*)` |
| `DifferentVictims` | `COUNT(*) WHERE wins > 0` |
| `DifferentCulprits` | `COUNT(*) WHERE losses > 0` |
| `DoubleDigitsVictims` | `COUNT(*) WHERE dd_wins > 0` |
| `DoubleDigitsCulprits` | `COUNT(*) WHERE dd_losses > 0` |
| `CleanSheetsVictims` | `COUNT(*) WHERE cs_wins > 0` |
| `CleanSheetsCulprits` | `COUNT(*) WHERE cs_losses > 0` |

Written onto `PlayerState` before snapshot persist so career rows stay self-contained for reads.

---

## 5. Finalize writer (conceptual)

```text
1. Apply tournament games → in-memory cumulative pair map (per participant)
2. Derive network scalars from pair map → PlayerState
3. Update peak/nadir from event rating_after
4. Persist event snapshots + current (existing)
5. DELETE + INSERT amiga_player_matchup_at_event rows for (participant × opponent × tournament_id)
6. UPSERT amiga_player_matchup_summary from cumulative map for touched pairs
7. Mark rating_finalized
```

Replay = loop finalize only. **No tail batches** for matchup, network, or catalog.

---

## 6. Historical reads (later product)

At cutoff T: pair rows where `event_date/chrono ≤ T`, take latest row per `(player_id, opponent_id)` — same pattern as rating history. Opponents hub / historical H2H is **out of scope** until product asks; storage is ready.

---

## 7. Out of scope

| Topic | Notes |
|-------|--------|
| `amiga_generalstats` / HoF timeline | Later slice |
| Online `player_matchup_summary` | Unchanged; per-game post-game |
| PHP ops parity | **Done** Jun 2026 — `finalize_tournament.php` mirrors cumulative matchup + network/peaks |

---

## 8. Verification

- `SUM(matchup_summary.games) = 2 × COUNT(amiga_games)` (unchanged)
- At-event row count ≈ sum of cumulative opponent counts at each snapshot event
- Summary row = at-event row for each pair at player's **latest participated event** (order by `event_date`, `event_chrono`, `as_of_tournament_id` — not `MAX(as_of_tournament_id)` alone; catalog ids are not chrono-monotonic)
- Snapshot `DifferentOpponents` = pair-count oracle at same `tournament_id`
- `python -m scripts.amiga prove` green
