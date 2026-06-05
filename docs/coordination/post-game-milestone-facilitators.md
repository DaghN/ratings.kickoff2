# Post-game milestones — playertable facilitators

**Done (Jun 2026, SCH-018)** — five chrono facilitators on `playertable`. Post-game PHP updates columns in P2 and unlocks in P6 (`k2_post_game_milestones_streak_keys`).

**Parity:** `ab-post-game` layer 6 excludes only `perfect_day`, `nightmare_day`, `entered_arena`.

---

## `playertable` (SCH-018 — increment per game, same transaction as P2)

| Column | Milestone key | Rule (unlock on this game when streak equals) |
|--------|---------------|-----------------------------------------------|
| `ScoreStreak` | `on_the_scoresheet` | Reset when `goals_for = 0`; unlock at **10** |
| `MerchantStreak` | `merchant_streak` | Reset when `goals_for < 10`; unlock at **5** |
| `ExactTenGoalStreak` | `minimalist_merchant` | Reset when `goals_for != 10`; unlock at **3** |
| `WinMarginOneStreak` | `knife_edge` | Consecutive 1-goal margin **wins**; unlock at **5** |
| `LossMarginOneStreak` | `unlucky` | Consecutive 1-goal margin **losses**; unlock at **5** |

**Migration:** `site/public_html/ops/sql/migrations/018_playertable_milestone_streak_facilitators.sql` · contract `docs/website-data-contract.md` · oracle `scripts/ladder/player_state.py`.

---

## Use existing / other tables (no notebook)

| Key | Source (target) | Notes |
|-----|-----------------|-------|
| `daily_habit` | **`player_period_games`** day rows in UTC week | **Implemented** |
| `monthly_regular` | **`player_period_games`** day rows in calendar month | **Implemented** |
| `weekly_regular` | **`player_period_games`** week rows | **Implemented** |
| `year_round` | **`player_period_games`** month rows | **Implemented** |
| `giant_slayer` | **`playertable`** kickoff active #1 (before this game write) | **Implemented** |
| `united_nations` | **`DrawingStreak`** on `playertable` | **Implemented** |
| `perfect_day` / `nightmare_day` | Mode C UTC day-close job | **Not** post-game |

---

## Already DB-backed (no facilitator work)

Exists, streak (W/L/D from `playertable`), tail, network, matchup, period burst, rating `club_*` crosses, `rare_blank`, debut opponent keys, `year_in_heaven` helper, and the five SCH-018 keys above.
