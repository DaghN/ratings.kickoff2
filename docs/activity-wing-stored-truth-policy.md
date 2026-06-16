# Activity wing — stored truth policy (online)

**Status:** Ops proven on work (Jun 2026); **UI shipped** on dev/staging (`leaderboards/activity/*`). Steve simul pending.
**Realm:** Online only (`ko2unity_*` / `kooldb*`) — Amiga defers per [`amiga-realm-vision.md`](amiga-realm-vision.md).  
**Authority:** Product + ops decisions here; table behaviour merges into [`website-data-contract.md`](website-data-contract.md) at track closure.  
**Implementation:** [`activity-wing-stored-truth-implementation-plan.md`](activity-wing-stored-truth-implementation-plan.md)

---

## Purpose

Support a restructured **Leaderboards → Activity** wing with three celebration lenses on rated **participation** (not match results):

| Lens | Question | Leaderboard home |
|------|----------|------------------|
| **Peaks** | What was your hottest single UTC period? | One sortable table |
| **Participation** | How many distinct periods did you show up in? (+ career span) | One sortable table |
| **In a row** | What was your longest unbroken run of active periods? | One sortable table |

**Out of scope for this track:** match result streaks (wins/losses/draws) — stay on **Streaks** wing; hub **Activity** charts (`activity.php`); Status activity league.

---

## Locked decisions

| ID | Decision |
|----|----------|
| A1 | **Peaks** continue to use existing `player_peak_period_games` (+ `playertable.NumberGames` for career total). Ops P4 behaviour unchanged except sharing `is_new_period` flags downstream. |
| A2 | **Participation** counts = number of rows in `player_period_games` per `(player_id, period_type)`. Maintained incrementally in new table `player_activity_participation` — **not** `COUNT(*)` per game in post-game. |
| A3 | **`is_new_period`** = after P4 upsert, `games === 1` for that `(period_type, period_start, player_id)`. Drives participation increments and streak gating. |
| A4 | **Longevity** = `DATEDIFF(last_rated_day, first_rated_day) + 1` from participation row; `first_rated_day` / `last_rated_day` maintained incrementally (first set once; last advances on new UTC day). |
| A5 | **Play streaks** extend `player_play_streaks.streak_type` to `day`, `week`, `month`, `year` (same UTC bucketing as P4). Post-game runs streak logic **only when** `is_new_period` for that type. |
| A6 | **Hall of Fame page UI** (`hall-of-fame.php` new rows, deep links, `RECORDS_PAGE_DATA.md`) — **deferred** to a dedicated HoF pass. Ops must write stored truth + GST so HoF can be wired later without rebuild. |
| A7 | **GST month/year in-a-row (ops):** **in scope** — add `LongestMonthlyPlayStreak*` / `LongestYearlyPlayStreak*` on `generalstatstable` (mirror day/week); post-game updates when personal `best_streak` strictly increases, same tie policy as day/week ([`records-post-game-exception.md`](coordination/records-post-game-exception.md)). |
| A7b | **GST participation counts** (most active days/weeks/…): **not** on `generalstatstable` — read from `player_activity_participation` when HoF UI is added later (same pattern as peak rows today). |
| A8 | **Streaks wing** (`leaderboards/streaks.php`): remove **Days** / **Weeks** columns in **UI slice** (after ops proof). |
| A9 | **Wing nav label:** **Activity**. Route/file: `leaderboards/activity.php` (replace `leaderboards/activity-peaks.php`); registry key update + redirect from old path. |
| A10 | **Column naming (UI):** Participation uses **Active days/weeks/months/years**; in-a-row uses **Days/weeks/months/years in a row** — never bare “Days” on a mixed table. |
| A11 | **Proof DB:** **`ko2unity_work`** (local-work) — the DB ops targets for simuls. **Not** a separate “fill schema on dev (`ko2unity_db`)” job; that is out of scope and must not be confused with this track. |
| A12 | **Orthogonal parity:** After incremental smoke simul on **work**, compare each new stored-truth table to **slow oracle queries** on data already on work (mainly `player_period_games`; `ratedresults` only for narrow spot checks). Two independent paths to the same answer — **not** “rebuild script is definition of correct.” |
| A13 | **Smoke ladder:** simul `--limit 100` → parity → Dagh OK → `--limit 1000` → parity → Dagh OK → longer/full only if Dagh approves (smokes take time). |
| A14 | **Milestones:** **out of scope** — no catalog keys, no post-game unlock hooks this burst. |
| A15 | **Execution order:** ops + orthogonal parity on work → smoke ladder → **Steve simul** → optional overnight local full simul (convenience for UI dev DB) → **UI** after Steve simul (WinSCP to staged anytime). |
| A16 | **This chat** owns the track — no starter prompt / separate agent handoff files unless Dagh asks later. |

---

## Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Ten separate leaderboard tables | Dilutes focus; agreed in product discussion. |
| `COUNT(*)` on `player_period_games` every post-game | O(player history) per game; hurts simul. |
| Participation counts read-only (no cache table) | Fine for LB page load, but no cheap per-player row for profile/HoF/verify; increment table is O(1). |
| Put participation columns on `playertable` | Crowds ground-truth table; harder zero-derived hygiene. |
| Recompute streaks from full period list per game | Rebuild-only; not post-game. |
| HoF participation on `generalstatstable` | Unnecessary post-game cost; peaks already prove read-time HoF works. |
| Month/year streaks without `is_new_period` gate | Would run streak load/save on every game; regresses simul. |
| Dev DB (`ko2unity_db`) fill as parity gate | Separate concern; parity runs on **work** where simul already runs. |
| Rebuild script output as sign-off truth | Rebuild/repair is optional oracle or batch repair; smoke sign-off = incremental vs SQL oracles on work. |

---

## Data model

```text
ratedresults (ground)
       │
       ▼ P4 (existing + flags)
player_period_games ─────────────────┬──► player_peak_period_games (existing peaks)
       │                             │
       │ is_new_period               │
       ├────────────────────────────► player_activity_participation (NEW)
       │
       └────────────────────────────► player_play_streaks (EXTEND month/year)
                                              │
                                              ▼ P7 when personal best_streak rises (day/week/month/year)
                                       generalstatstable (Longest*PlayStreak* for all four types)
```

**HoF page** does not render month/year/participation rows yet — GST + tables are populated by ops anyway.

### UTC period keys (unchanged)

| `period_type` | `period_start` |
|---------------|----------------|
| `day` | UTC `Y-m-d` |
| `week` | Monday UTC `Y-m-d` |
| `month` | `Y-m-01` |
| `year` | `Y-01-01` |

---

## Contract: `player_activity_participation` (NEW)

**Lifecycle:** Active after SCH-022.

**Purpose:** O(1) participation totals and longevity endpoints for Activity wing, profile, HoF.

**Source truth:** `player_period_games` (row existence per period).

**Grain:** one row per `player_id`.

| Column | Meaning |
|--------|---------|
| `active_days` | Distinct UTC days with ≥1 rated game |
| `active_weeks` | Distinct UTC weeks (Mon–Sun) with ≥1 rated game |
| `active_months` | Distinct calendar months with ≥1 rated game |
| `active_years` | Distinct calendar years with ≥1 rated game |
| `first_rated_day` | UTC date of first rated game |
| `last_rated_day` | UTC date of most recent rated game (by game date, not wall clock) |

**Derived at read (not stored):** `longevity_days = DATEDIFF(last_rated_day, first_rated_day) + 1`.

**Full rebuild:**

```sql
-- Per player, per period_type: COUNT(*) from player_period_games
-- first_rated_day = MIN(period_start) WHERE period_type = 'day'
-- last_rated_day  = MAX(period_start) WHERE period_type = 'day'
```

**Post-game rule (P4b, same transaction as P4):**

For each player A/B, after each period upsert:

1. If `is_new_period` for that `period_type`: `active_{type} += 1` (insert row if missing).
2. If `is_new_period` for `day`: set `first_rated_day` if NULL; set `last_rated_day = GREATEST(last_rated_day, day_start)`.
3. If not new day but career first game: `first_rated_day` still set when row created (first ever period of any type implies new day).

**Orthogonal parity (work DB, after incremental simul):**

```sql
-- Global: participation sums must match period row counts
SELECT SUM(active_days) FROM player_activity_participation;
SELECT COUNT(*) FROM player_period_games WHERE period_type = 'day';
-- Repeat for week / month / year.

-- Per player (spot):
SELECT active_days FROM player_activity_participation WHERE player_id = ?;
SELECT COUNT(*) FROM player_period_games
WHERE player_id = ? AND period_type = 'day';
```

**Implementation:** `ops/includes/post_game_period_activity.php`; `ops_prepare_constants.php` derived truncate list. Repair: `scripts/ladder/sql/archive/one-off-2026-06/player_activity_participation_rebuild.sql` (not smoke gate).

---

## Contract: `player_play_streaks` (EXTEND)

**Change:** `streak_type` enum adds `month`, `year`.

**Consecutive rules:**

| Type | Anchor | Next period |
|------|--------|-------------|
| `day` | UTC date | +1 day |
| `week` | Monday UTC | +7 days |
| `month` | `Y-m-01` | first day of next calendar month |
| `year` | `Y-01-01` | next calendar year |

**Post-game rule (P7, revised):**

1. Called from `process_completed_game.php` after P4/P6 with `periodStarts` + `is_new_period` map (no duplicate date math).
2. For each player and each `streak_type`: call `k2_play_streak_apply_game` **only if** `is_new_period` for that type.
3. Personal best + **HoF GST** for all four `streak_type` values when `best_streak` strictly increases — same rules as [`website-data-contract.md`](website-data-contract.md) § `player_play_streaks` (day/week today; month/year added this burst).
4. **No new milestone hooks** this burst (`play_streak_100` etc. unchanged).

**Orthogonal parity (work DB):** For each `streak_type`, `best_streak` in `player_play_streaks` must match walking sorted `period_start` lists from `player_period_games`. Global max per type must match corresponding `Longest*PlayStreak` on `generalstatstable` id=1 (after rebuild or full smoke).

**Repair (optional):** Extend `scripts/rebuild_player_play_streaks.php` for month/year table rows **and** GST month/year columns — repair oracle, not smoke gate definition.

**GST columns (SCH-023):** `LongestMonthlyPlayStreak`, `LongestMonthlyPlayStreakID`, `LongestMonthlyPlayStreakName`, `LongestMonthlyPlayStreakDate`, `LongestMonthlyPlayStreakGameID`, and `LongestYearlyPlayStreak*` set — mirror day/week naming in `014_player_play_streaks.sql`.

---

## Contract: `player_peak_period_games` (UNCHANGED)

Peaks + dates remain P4 incremental update. HoF peak rows remain read-time top-1 from cache. UI consolidates display only.

---

## UI contract (summary — detail in implementation plan)

| Section | Columns (default sort) |
|---------|------------------------|
| Peaks | Player · peak day · peak week · peak month · peak year · career games |
| Participation | Player · active days · active weeks · active months · active years · longevity days · (optional first game) |
| In a row | Player · days · weeks · months · years in a row |

Peak cells include **games + period date** (e.g. `47 · Mar 3, 2019`). **Three segment pages** under `leaderboards/activity/` (**Participation · In a row · Peaks**), not stacked on one URL. Default landing = Participation.

---

## Links

| Doc | Role |
|-----|------|
| [`website-data-contract.md`](website-data-contract.md) | Canonical merge target at closure |
| [`post-game-php-development.md`](post-game-php-development.md) | P4/P7 phase map |
| [`ladder-ops-platform.md`](ladder-ops-platform.md) | Ops boundary |
| [`coordination/ops-simul-runbook.md`](coordination/ops-simul-runbook.md) | Proof ritual |
| [`hub-ia-agreement.md`](hub-ia-agreement.md) | Wing IA |
| [`RECORDS_PAGE_DATA.md`](RECORDS_PAGE_DATA.md) | HoF deep links — **HoF UI pass** (GST populated by this track) |
