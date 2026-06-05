# Milestone `year_in_heaven` — staging handoff (May 2026)

> **DO NOT RUN** `staging-scripts/` steps below. Milestone is in catalog + simul path; forward work = [`../coordination/cutover-readiness.md`](../coordination/cutover-readiness.md) / ops post-game — not this packet.

**Catalog key:** `year_in_heaven` — **Year in Heaven**  
**Rule:** Rated game in **every UTC week** of a **calendar year** (52 Monday slots; same grid as profile Played weeks).  
**Unlock game (live):** the rated game that fills the **52nd** UTC week slot — `achieved_at` = that game's `Date` (shows in Recent immediately).  
**Post-game:** `k2_milestone_maybe_unlock_year_in_heaven()` when week `games` = **1** after upsert (ops `post_game_milestones.php` + `player_play_streaks.php` on new UTC week).  
**Batch rebuild SQL:** may still use establishing-game `MIN(id)` on the completing week Monday for historical backfill.

**Generator:** `python scripts/oneoff/gen_milestone_year_in_heaven_sql.py` → `scripts/ladder/sql/player_milestones_rebuild_year_in_heaven.sql`

**Garden order (May 2026):** `year_in_heaven` is **last in Legendary** (after `play_streak_100`) as add-one placement; probe = **5** holders (~4.7%) — same band as `monthly_regular`, rarer than `club_10000` (1). Re-sort within Legendary optional if display should follow holder count exactly.

---

## Staging verified (May 2026)

After `load_milestone_definitions.php` + `run_milestone_year_in_heaven_unlock.php` (or equivalent SQL):

| Check | `kooldb` |
|-------|----------|
| `milestone_definitions` | **112** |
| `year_in_heaven` rule | *Rated game in every UTC week of a calendar year* |
| Unlock rows | **5** — players **263** (2018), **237** (2019), **260** (2020), **344** / geo4444 (2021), **537** (2025) |

---

## Part 1 — Dagh: WinSCP upload

| Local | Remote (`public_html/` unless noted) |
|-------|-------------------------------------|
| `data/milestones_definitions_seed.json` | `staging-data/milestones_definitions_seed.json` |
| `scripts/ladder/sql/player_milestones_rebuild_year_in_heaven.sql` | `staging-sql/milestones/player_milestones_rebuild_year_in_heaven.sql` |
| `site/public_html/includes/player_milestone_year_in_heaven.php` | `includes/player_milestone_year_in_heaven.php` |
| `site/public_html/includes/player_play_streaks.php` | `includes/player_play_streaks.php` |
| `site/public_html/includes/player_milestones_garden_order.php` | `includes/player_milestones_garden_order.php` |
| `site/public_html/staging-scripts/run_milestone_year_in_heaven_unlock.php` | `staging-scripts/run_milestone_year_in_heaven_unlock.php` |
| `site/public_html/staging-scripts/run_player_milestones_rebuild.php` | `staging-scripts/run_player_milestones_rebuild.php` (splice list) |

**Prerequisite:** `player_period_games` with `period_type = week` (REP-003).

---

## Part 2 — Steve: commands (staging)

SSH to staging; `cd` to `public_html`.

**Option A — surgical (no full milestones truncate)**

```bash
php staging-scripts/run_milestone_year_in_heaven_unlock.php
```

**Option B — full milestones rebuild** (includes splice if SQL uploaded and `run_player_milestones_rebuild.php` updated)

```bash
php staging-scripts/run_player_milestones_rebuild.php
```

**Verify catalog + unlocks**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SELECT COUNT(*) AS defs FROM milestone_definitions;
SELECT display_name, rule_short FROM milestone_definitions WHERE milestone_key = 'year_in_heaven';
SELECT COUNT(*) AS holders FROM player_milestones WHERE milestone_key = 'year_in_heaven';
SELECT player_id, value, source_game_id, achieved_at FROM player_milestones WHERE milestone_key = 'year_in_heaven' LIMIT 5;
"
```

| Check | Expected |
|-------|----------|
| `milestone_definitions` count | **112** |
| `year_in_heaven` row | `Year in Heaven` · *Rated game in every UTC week of a calendar year* |
| Holders | **5** (see **Staging verified** above) |

**Browser:** Milestones garden — **Year in Heaven** last in Legendary; hero `{n}/112` after catalog reload.

---

## Post-game order (prod C++ / PHP reference)

1. Upsert `player_period_games` (day + week + month + year) for both players.  
2. `k2_play_streak_after_rated_game()` (day/week streaks + HoF).  
3. On **first game of a new UTC week** only: `k2_milestone_maybe_unlock_year_in_heaven($con, $playerId, $weekMonday)`.

Steve phase: **M7** (chronological / calendar habit) in [`website-data-contract.md`](../website-data-contract.md) § `player_milestones`.

---

## Local regen (required after pulling catalog changes)

Git/seed changes alone do **not** update the site. Hero `{n}/{catalog}`, garden cards, and `ranked10` totals all read **`milestone_definitions`** + **`player_milestones`** in MySQL.

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python scripts/oneoff/gen_milestone_year_in_heaven_sql.py
python scripts/oneoff/load_milestone_definitions.py
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root ko2unity_db -e "source C:/Users/daghn/Desktop/Online and Amiga 500 ELO/scripts/ladder/sql/player_milestones_rebuild_year_in_heaven.sql"
```

Then hard-refresh profile/garden (`Ctrl+F5`). Expect **112** catalog; geo4444 **101/112** if they hold `year_in_heaven` (5 players do on May 2026 import).

Optional: full `scripts\rebuild_website_derived_data_local.ps1` (re-splices all milestone SQL; truncates `player_milestones`).
