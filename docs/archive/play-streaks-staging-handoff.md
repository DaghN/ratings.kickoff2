# Rated play streaks — staging handoff (May 2026)

> **DO NOT RUN** `staging-scripts/` or `REP-015` blocks below. **Forward proof:** ops simul on **`kooldb1`** — [`../coordination/cutover-readiness.md`](../coordination/cutover-readiness.md). Streaks **proven** May 2026; live writer = PHP ops at cutover.

**Contract:** [`website-data-contract.md`](../website-data-contract.md) § `player_play_streaks`  
**Post-game:** PHP reference in `includes/player_play_streaks.php` → `k2_play_streak_after_rated_game()`; prod C++ after `player_period_games` (Steve).  
**HoF tie-break:** same `best_last_game_id` → holder is `ratedresults.idB`; else longer streak, then earlier `best_achieved_at`.

**Staging host:** `ratings.kickoff2.com` port **5322** (SFTP → `public_html/`).  
**DB:** `kooldb`.

---

## Staging verified (Steve — May 2026)

**Done:** SCH-014 + REP-015 (May 2026; former `staging-scripts/run_player_play_streaks_rebuild.php` — **deleted Jun 2026**).  
**UI synced:** `ranked4.php` (Streaks wing **Days** / **Weeks**), `server2.php` (HoF **Most days in a row** / **Most weeks in a row**), `includes/player_play_streaks.php`, `js/k2-table.js`, `stylesheets/theme.css`.

| Check | Staging `kooldb` |
|-------|------------------|
| `player_play_streaks` day | 264 rows, `MAX(best_streak)` = **87** |
| `player_play_streaks` week | 264 rows, `MAX(best_streak)` = **126** |
| HoF `LongestDailyPlayStreak` | **87**, player **582**, game **52468** |
| HoF `LongestWeeklyPlayStreak` | **126**, player **344**, game **39412** |

Matches local reference (May 2026). **Prod** schema + C++ post-game still **pending**.

**Milestone catalog (add-one):** ops `seed-catalog` or legacy `load_milestone_definitions.php` → **111** rows on staging (verified May 2026); `play_streak_100` = **100 days**, rule *100 consecutive UTC days with a rated game*. Unlock splice optional (`run_milestone_play_streak_100_unlock.php` — expect **0** rows). See [`milestones-add-one-playbook.md`](milestones-add-one-playbook.md).

---

## Part 1 — Dagh: WinSCP upload

| Local | Remote |
|-------|--------|
| `schema/migrations/014_player_play_streaks.sql` | `staging-sql/014_player_play_streaks.sql` |
| `site/public_html/includes/player_play_streaks.php` | `includes/player_play_streaks.php` |
| `site/public_html/staging-scripts/_staging_play_streaks_bootstrap.php` | `staging-scripts/_staging_play_streaks_bootstrap.php` |
| `site/public_html/staging-scripts/run_player_play_streaks_rebuild.php` | `staging-scripts/run_player_play_streaks_rebuild.php` |
| `site/public_html/ranked4.php` | `ranked4.php` |
| `site/public_html/server2.php` | `server2.php` |
| `site/public_html/js/k2-table.js` | `js/k2-table.js` |
| `site/public_html/stylesheets/theme.css` | `stylesheets/theme.css` |

**Prerequisite on staging:** `player_period_games` populated (REP-003, including week rows).

---

## Part 2 — Steve: schema + rebuild (first-time only)

SSH to staging; `cd` to `public_html`.

**Preflight:**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SELECT COUNT(*) AS day_rows FROM player_period_games WHERE period_type = 'day';
SHOW TABLES LIKE 'player_play_streaks';
"
```

Expect `day_rows` > 0; `player_play_streaks` missing until step 1.

**Step 1 — SCH-014** (skip if table exists)

```bash
mysql -u MYSQL_USER -p kooldb < staging-sql/014_player_play_streaks.sql
```

**Step 2 — REP-015 rebuild**

```bash
php staging-scripts/run_player_play_streaks_rebuild.php
```

**Note:** If you see `SQL syntax error ... near '?'` on `_staging_play_streaks_bootstrap.php`, re-upload that file — staging MariaDB does not support `SHOW TABLES LIKE ?` via `prepare()` (fixed May 2026).

**Expected after REP-015:**

| Check | Value |
|-------|--------|
| `player_play_streaks` rows per `streak_type` | **264** each (day + week) |
| Max day `best_streak` | **87** |
| Max week `best_streak` | **126** |
| HoF daily | **87**, player **582**, game **52468** |
| HoF weekly | **126**, player **344**, game **39412** |

**Verify:**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SELECT streak_type, COUNT(*) AS n, MAX(best_streak) AS mx
FROM player_play_streaks GROUP BY streak_type;
SELECT LongestDailyPlayStreak, LongestDailyPlayStreakID, LongestDailyPlayStreakGameID,
       LongestWeeklyPlayStreak, LongestWeeklyPlayStreakID, LongestWeeklyPlayStreakGameID
FROM generalstatstable WHERE id = 1;
"
```

**Browser smoke (after PHP sync):**

- Leaderboards → **Streaks** (`ranked4.php`): **Days** and **Weeks** columns (personal best from `player_play_streaks`).
- **Hall of Fame** (`server2.php`): **Most days in a row** / **Most weeks in a row** (after “most games in …”, before Most wins).

---

## Part 3 — Prod C++ (later)

After staging sign-off, merge post-game from contract § `player_play_streaks`:

1. Per player: update `player_play_streaks` (day + week) — personal best only when `best_streak` **strictly** increases.
2. If personal best increased: compare to `generalstatstable` HoF columns (`LongestDailyPlayStreak*`, `LongestWeeklyPlayStreak*`).
3. Session `SET time_zone = '+00:00'` before period buckets and record dates.

---

## Local dev

```powershell
Get-Content -Raw schema\migrations\014_player_play_streaks.sql | C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2unity_db
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\rebuild_player_play_streaks.php
```

Or after `scripts\rebuild_website_derived_data_local.ps1` (includes REP-015 when PHP is available).
