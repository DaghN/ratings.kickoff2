# Milestones — facilitation matrix (Phase 3)

**Kick Off 2 ratings site · May 2026**

Maps the **110 curated keys** to implementation families: what stored truth to read, how rebuild unlock rows, and post-game expectations. Authoritative keys: [`milestones-tier-curated.md`](milestones-tier-curated.md). Catalog metadata: [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json) → `milestone_definitions` table.

**Status:** All rebuild waves done. **110/110** keys in `player_milestones` (splice: core + exists + streaks + chrono + tail + period + league). Next: Phase 4 UI + live post-game inserts.

---

## Source pointers (SCH-012)

Every `player_milestones` row should identify the **unlock event**:

| `source_kind` | Columns | Use |
|---------------|---------|-----|
| `game` | `source_game_id` | Link to `ratedresults` / game page |
| `league` | `source_league_kind`, `source_period_type`, `source_period_start` | Link to Status leagues / `player_league_award` |
| `lobby` | *(none — time lives in `achieved_at`)* | `entered_arena` only; source = `playertable.JoinDate` |

Waves 2–6 writers must set these on insert (chronological first-cross for game-backed keys).

**`entered_arena` (resolved):** `source_kind = lobby`. **Registering = entering the lobby** → `achieved_at = playertable.JoinDate`. Not the same as `debut` (first **rated** game). Rebuild: one row per ladder player (`NumberGames >= 1`). Live: insert at account registration (Steve/C++ when row created); do not use `LobbyTime` for this milestone.

---

## Implementation waves

| Wave | Family | Keys (approx.) | Rebuild source | Post-game |
|------|--------|----------------:|----------------|-----------|
| **0** | Catalog | 110 | `load_milestone_definitions.py` | N/A (metadata) |
| **1** | League awards + career wins | 20 | `player_league_award`, `player_league_totals` | On league finalize (PER-003) |
| **2** | `playertable` thresholds | ~34 | Chronological or column-at-cross (TBD per key) | Per rated game (C++ / engine) |
| **3** | `ratedresults` exists | ~18 | First matching game `Date` | Per game |
| **4** | Period peaks | 5 | `player_period_games` + last game LATERAL (`player_milestones_rebuild_period.sql`) | After period bucket update |
| **5** | Matchup aggregates | ~4 | `player_matchup_summary` / ratedresults | Per game |
| **6** | Chronological state | 16 | `gen_milestone_chrono_sql.py` (peace_streak in streaks SQL) | Per game |

Win/loss **streak** keys (`win_hat_trick`, `ten_wins_straight`, `rampage`, `win_streak_30`, `cold_streak`, `win_drought`): use **`playertable` longest-streak columns** at first cross — see [`milestones-tier-curated.md`](milestones-tier-curated.md) § Win-streak milestones.

---

## Wave 1 — league keys (done in rebuild SQL)

| `milestone_key` | Rule | `achieved_at` |
|-----------------|------|----------------|
| `moment_of_glory` | First daily **points** league win | `MIN(period_end)` where winner |
| `activity_king` | First monthly **activity** league win | same |
| `league_*_medal` | First top-3 in that league slice | `MIN(period_end)`, `finish_rank <= 3` |
| `league_*_winner` | First #1 in that league slice | `MIN(period_end)`, `is_winner = 1` |
| `league_wins_10/50/100/500` | Nth career league win (any of 8) | `period_end` of Nth `is_winner` row |

League rules: [`leagues-rules-spec.md`](leagues-rules-spec.md). Awards must exist (REP-012) **before** `player_milestones_rebuild.sql`.

---

## Probe → production checklist

| Step | Artifact |
|------|----------|
| Count / rule hint | `scripts/oneoff/milestone_unlock_counts.py` + seed `rule_probe` |
| First-unlock rows | `player_milestones` rebuild or post-game insert |
| Display | `milestone_definitions` |

Regenerate seed: `python scripts/oneoff/milestone_unlock_counts.py --write-doc --export-seed` (omit `--doc-only` when DB is up).

---

## Family counts (from seed `rule_probe`, May 2026)

| Grouped family | ~Keys |
|----------------|------:|
| `playertable` | 28 |
| `ratedresults any game` | 18 |
| `chronological` | 17 |
| `player_league_award` | 16 (+ aliases `moment_of_glory`, `activity_king`) |
| `player_league_totals` | 4 |
| `player_period_games` | 5 |
| Streak proxies (`playertable` longest streak) | 6 |
| `player_matchup_summary` / specials | 6 |

---

## Rebuild parity scripts

| Batch | Script |
|-------|--------|
| Exists (18) | `milestone_exists_parity.py` |
| Streaks (8) | `milestone_streak_parity.py` |
| Period bursts (5) | `milestone_period_parity.py` |
| Chronological (16) | `milestone_chrono_parity.py` (+ `milestone_chrono_gen_check.py` pre-rebuild) |
| Tail playertable + matchup (30) | `milestone_tail_parity.py` (+ `milestone_tail_gen_check.py` pre-rebuild) |

## Rebuild generators

| Script | Output |
|--------|--------|
| `gen_milestone_exists_sql.py` | `player_milestones_rebuild_exists.sql` |
| `gen_milestone_streak_sql.py` | `player_milestones_rebuild_streaks.sql` |
| `gen_milestone_chrono_sql.py` | `player_milestones_rebuild_chrono.sql` |
| `gen_milestone_chrono_sql.py` (surgical) | `player_milestones_rebuild_giant_slayer.sql` — DELETE + INSERT `giant_slayer` only |
| `gen_milestone_tail_sql.py` | `player_milestones_rebuild_tail.sql` |

---

*Phase 3 design doc. Update when a wave ships.*
