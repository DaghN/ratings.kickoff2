# Milestones staging cutover packet (May 2026)

> **DO NOT RUN** commands below for current work. **Forward path:** [`../coordination/cutover-readiness.md`](../coordination/cutover-readiness.md) → `migrate-work` → `seed-catalog` → `zero-derived` → **`php ops/run_ops_sim.php run`** on **`kooldb1`** / **`ko2unity_work`**. `staging-scripts/` **deleted** from repo and remote Jun 2026.

**Historical runbook** — staging DB was cut over May 2026. CLI paths below used `staging-scripts/` (folder **deleted Jun 2026**). **Current:** [`staging-scripts-inventory.md`](staging-scripts-inventory.md) · ops `run_prepare.php seed-catalog` · `run_finalize_league.php` · `run_ops_sim.php`.

**Operational runbook** for Dagh (WinSCP) + Steve (SSH/`kooldb`). Overview: [`milestones-staging-steve-handoff.md`](milestones-staging-steve-handoff.md). Contract: [`website-data-contract.md`](../website-data-contract.md) § `player_milestones`.

**Staging host:** `ratings.kickoff2.com` port **5322** (SFTP → `public_html/`).  
**DB:** `kooldb` (no live game writes on staging).

**Already done on staging (do not repeat unless broken):**

- SCH-008 (`007_stored_truth`) — five aggregate tables incl. `player_milestones`  
- SCH-009/010 + **REP-012/013** — league awards (`staging-sql/008` + `009` + historical `run_league_awards_rebuild.php`; superseded by `ops/run_finalize_league.php`)  
- Full milestone rebuild waves + **REP-008b** — **canonical `player_milestones` total = 6615** (see [`replay-register.md`](replay-register.md) § Milestone unlock row counts; step tables below that say **6658** are wave-1 historical)

**Authority for “is staging DB current?”** [`schema-register.md`](schema-register.md) + [`replay-register.md`](replay-register.md) run log — not contradictory bullets in `PROJECT_MEMORY.md`.

**Catalog size (May 2026):** **112** keys in seed + [`milestones-catalog.md`](../milestones-catalog.md). Older steps below that say **110** are from wave-1 verification before `play_streak_100` / `year_in_heaven`.

---

## Part 1 — Dagh: WinSCP upload

Upload **local path → remote path** (remote root = `public_html/`).

### A) Schema SQL (Steve runs these)

| Local file | Remote file |
|------------|-------------|
| `schema/migrations/010_milestone_definitions.sql` | `staging-sql/010_milestone_definitions.sql` |
| `schema/migrations/011_player_milestones_source.sql` | `staging-sql/011_player_milestones_source.sql` |
| `schema/migrations/012_player_milestones_source_lobby.sql` | `staging-sql/012_player_milestones_source_lobby.sql` |

Create remote folder `staging-sql/milestones/` if missing.

| Local file | Remote file |
|------------|-------------|
| `scripts/ladder/sql/player_milestones_rebuild.sql` | `staging-sql/milestones/player_milestones_rebuild.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_exists.sql` | `staging-sql/milestones/player_milestones_rebuild_exists.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_streaks.sql` | `staging-sql/milestones/player_milestones_rebuild_streaks.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_chrono.sql` | `staging-sql/milestones/player_milestones_rebuild_chrono.sql` |
| `scripts/ladder/sql/player_milestones_fix_day_close.sql` | `staging-sql/milestones/player_milestones_fix_day_close.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_tail.sql` | `staging-sql/milestones/player_milestones_rebuild_tail.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_period.sql` | `staging-sql/milestones/player_milestones_rebuild_period.sql` |
| `scripts/ladder/sql/player_milestones_rebuild_giant_slayer.sql` | `staging-sql/milestones/player_milestones_rebuild_giant_slayer.sql` |

### B) Staging CLI scripts + catalog seed

| Local file | Remote file |
|------------|-------------|
| `site/public_html/staging-scripts/_staging_milestones_bootstrap.php` | `staging-scripts/_staging_milestones_bootstrap.php` |
| `site/public_html/staging-scripts/load_milestone_definitions.php` | `staging-scripts/load_milestone_definitions.php` |
| `site/public_html/staging-scripts/run_player_milestones_rebuild.php` | `staging-scripts/run_player_milestones_rebuild.php` |
| `site/public_html/ops/data/milestones_definitions_seed.json` | (inside synced `public_html/ops/data/`) |
| `data/milestone_garden_links.json` | `staging-data/milestone_garden_links.json` |

Create remote folder `staging-data/` if missing.

### C) Website PHP/CSS (milestones v0 UI)

| Local file | Remote file |
|------------|-------------|
| `site/public_html/includes/player_milestones_helpers.php` | `includes/player_milestones_helpers.php` |
| `site/public_html/includes/player_milestones_garden_order.php` | `includes/player_milestones_garden_order.php` |
| `site/public_html/includes/milestone_garden_links.php` | `includes/milestone_garden_links.php` |
| `site/public_html/player/games.php` | `player/games.php` |
| `site/public_html/includes/player_nav.php` | `includes/player_nav.php` |
| `site/public_html/includes/hub_nav.php` | `includes/hub_nav.php` |
| `site/public_html/includes/lb_nav.php` | `includes/lb_nav.php` |
| `site/public_html/includes/k2_league_period_page.php` | `includes/k2_league_period_page.php` |
| `site/public_html/player/milestones.php` | `player/milestones.php` |
| `site/public_html/player/profile.php` | `player/profile.php` |
| `site/public_html/milestones.php` | `milestones.php` |
| `site/public_html/leaderboards/milestones.php` | `leaderboards/milestones.php` |
| `site/public_html/league.php` | `league.php` |
| `site/public_html/hall-of-fame.php` | `hall-of-fame.php` |
| `site/public_html/stylesheets/player-milestones.css` | `stylesheets/player-milestones.css` |
| `site/public_html/api/server_established_players_by_year.php` | `api/server_established_players_by_year.php` |
| `site/public_html/api/server_cumulative_established_by_month.php` | `api/server_cumulative_established_by_month.php` |
| `site/public_html/api/server_recent_milestones.php` | `api/server_recent_milestones.php` |

**After upload:** hard refresh staging pages (Ctrl+F5).

**Do not upload:** whole repo, `scripts/ladder/` tree (unless debugging), `config/` (already on server).

---

## Part 2 — Steve: commands on staging

SSH to staging app server. `cd` to **`public_html`** (same folder as `index.php`). Replace `MYSQL_USER` with staging DB user. Staging is **MariaDB** — every query below already uses `COUNT(*)` (safe to copy as-is).

**Preflight (screenshot):**

```bash
cd /path/to/public_html
mysql -u MYSQL_USER -p kooldb -e "
SELECT DATABASE(), COUNT(*) AS rated_games FROM ratedresults;
SELECT COUNT(*) AS league_awards FROM player_league_award;
SHOW TABLES LIKE 'player_milestones';
SHOW TABLES LIKE 'milestone_definitions';
"
```

**Expected:** `rated_games` ≈ **74870**; `league_awards` ≈ **21873**; `player_milestones` exists; `milestone_definitions` may be missing until step 1.

### Step 1 — Schema (SCH-011–013)

```bash
mysql -u MYSQL_USER -p kooldb < staging-sql/010_milestone_definitions.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/011_player_milestones_source.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/012_player_milestones_source_lobby.sql
```

**Verify:**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SHOW COLUMNS FROM player_milestones LIKE 'source_kind';
SHOW TABLES LIKE 'milestone_definitions';
"
```

**Expected:** `source_kind` column present; `milestone_definitions` table exists.

### Step 2 — Catalog (REP-014)

```bash
php staging-scripts/load_milestone_definitions.php
```

**Expected stdout:** `Catalog rows: 110` then `Done.`

### Step 3 — Milestones rebuild + giant_slayer fix (REP-008)

Runs full 110-key backfill, then **required** `giant_slayer` active-#1 surgical SQL (not optional).

```bash
php staging-scripts/run_player_milestones_rebuild.php
```

**Expected:** several minutes (period wave is heavy). Ends with printed counts — **screenshot the whole tail**.

**Expected counts (must match local on same 74,870-game `kooldb`):**

| Label | Expected |
|-------|----------|
| `distinct_keys` | **110** |
| `total_rows` | **6658** |
| `null_source` | **0** |
| `giant_slayer` | **31** |
| `definitions` | **110** |
| `dd_merchant_10` | **44** |
| `established_20` | **107** |
| `established_20_diff` | **0** |

**Extra verify:**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SET time_zone = '+00:00';
SELECT COUNT(*) AS gs FROM player_milestones WHERE milestone_key = 'giant_slayer';
SELECT p.Name, COUNT(*) AS unlocked
FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444'
GROUP BY p.Name;
SELECT milestone_key FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444' AND milestone_key = 'giant_slayer';
"
```

**Expected:** `gs` = **31**; geo4444 **unlocked = 100**; one row `giant_slayer`.

---

## Part 3 — Dagh: browser smoke (after Steve screenshots OK)

Base URL: staging site root (same host as today’s staging ratings).

| URL | Check |
|-----|--------|
| `leaderboards/milestones.php` | Milestones meta-leaderboard loads; tier columns sort |
| `player/milestones.php?id=537` | geo4444 garden shows **100/110** + tier dots |
| `player/profile.php?id=537` | Profile glance **100/110** |
| `hall-of-fame.php` | “Milestone achievers” DD Merchant list (not single-holder records) |
| `milestones.php` | Hub stub + nav tab |
| `milestones.php` (Recent) | Perfect/Nightmare unlock times show **`00:00` UTC** (not last-game evening) — after § Day-close DB fix |
| `player/milestones.php?id=<player>` | Perfect/Nightmare garden link label **Games** → `player/games.php?id=&day=` (qualifying UTC day = calendar day before `achieved_at`) |

If counts match but a page errors, paste PHP error / screenshot to agent — likely a missing WinSCP file from § C.

---

## Part 4 — After verification (agent / next chat)

**Staging verified May 2026** (Steve): wave 1 — 110 keys, 6658 rows, `giant_slayer`=31, geo4444 100/110. **Wave 2 (REP-008b):** `diversity_merchant`=**25**, **6615** total rows (canonical staging totals). Registers updated.

**Wave 3 (add-one, May 2026):** `load_milestone_definitions.php` → **111** catalog rows; `play_streak_100` = **100 days** / *100 consecutive UTC days with a rated game*; **0** unlock holders (max day streak 87). Hero/garden **100/111** when PHP uses `k2_milestone_catalog_total()`. See [`milestones-add-one-playbook.md`](milestones-add-one-playbook.md).

**Your remaining checks:** Part 3 browser smoke on staging after WinSCP PHP (if not done).

**Prod:** not in this packet — post-game C++ M1–M7 per contract later.

---

## Day-close fix — `perfect_day` / `nightmare_day` (Jun 2026)

**Semantics:** `achieved_at` = **`00:00:00` UTC on the calendar day after** the qualifying UTC day (not the last game time that evening). **`source_game_id`** stays the last rated game that day (evidence only). Garden link kind: **`player_day_games`** → `player/games.php?id=&day=` (qualifying day derived from `achieved_at`). Contract: [`website-data-contract.md`](../website-data-contract.md) · register: [`milestones-unlock-event-ui.md`](../milestones-unlock-event-ui.md).

**Generate SQL (dev):** `python scripts/oneoff/apply_day_milestone_achieved_at_fix.py` → `scripts/ladder/sql/player_milestones_fix_day_close.sql` (113 INSERTs). Upload per § A table.

### Steve — apply + verify (from `public_html`)

**Preflight** — if newest `nightmare_day` times look like evening hours, run step 2.

```bash
mysql -u MYSQL_USER -p kooldb < staging-sql/milestones/player_milestones_fix_day_close.sql
```

**Verify (paste output to Dagh):**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SELECT COUNT(*) AS perfect_nightmare_after
FROM player_milestones
WHERE milestone_key IN ('perfect_day', 'nightmare_day');
SELECT milestone_key, achieved_at, source_game_id
FROM player_milestones
WHERE milestone_key IN ('perfect_day', 'nightmare_day')
ORDER BY achieved_at DESC
LIMIT 5;
SELECT COUNT(*) AS total_milestone_rows FROM player_milestones;
SELECT TIME(achieved_at) AS t, COUNT(*) AS n
FROM player_milestones
WHERE milestone_key IN ('perfect_day', 'nightmare_day')
GROUP BY TIME(achieved_at)
ORDER BY n DESC
LIMIT 5;
"
```

**Expected:** `perfect_nightmare_after` = **113**; samples `00:00:00`; total rows ≈ **6615+** (grows with new unlocks); TIME group only **`00:00:00`**.

**Staging verified (Jun 2026):** Steve SQL — **113** / **113** midnight; total **6620**; newest sample `nightmare_day` **2026-04-22 00:00:00** game **74055**. Dagh browser smoke **done** — `milestones.php` Recent `00:00` UTC; `player/milestones.php` Perfect/Nightmare **Games** → `player/games.php?day=` OK.

---

## Troubleshooting — MariaDB `LATERAL` error (May 2026)

**Symptom:** `run_player_milestones_rebuild.php` dies near `INNER JOIN LATERAL` / `ratedresults` r / `DATE(r.Date)`.

**Cause:** Staging **MariaDB** does not support `LATERAL` in `player_milestones_rebuild_period.sql` (local MySQL 8 did).

**Fix (repo):** Period SQL rewritten with correlated subqueries; staging bootstrap runs one statement at a time for clearer errors.

**Steve retry (after Dagh WinSCP):**

1. Upload `scripts/ladder/sql/player_milestones_rebuild_period.sql` → `staging-sql/milestones/` (overwrite).  
2. Upload `site/public_html/staging-scripts/_staging_milestones_bootstrap.php` → `staging-scripts/` (overwrite).  
3. Re-run only: `php staging-scripts/run_player_milestones_rebuild.php` (full rebuild — table was truncated at start of failed run).

---

## Email to Steve (copy-paste)

**Subject:** Staging `kooldb` — milestones schema + rebuild + PHP (read-only UI)

---

Hi Steve,

We’re putting **personal milestones** on staging (`kooldb`): catalog table + 110 unlock rows + website read UI. Dagh will WinSCP the SQL/PHP files to `public_html/` first.

**League awards are already done** — please do **not** re-run `008`/`009` or `run_league_awards_rebuild.php`.

**Please run everything below from `public_html`** (same folder as `index.php`). Replace `MYSQL_USER` with your staging DB user. All SQL is written for **MariaDB** (`COUNT(*)` throughout — copy as-is).

---

### 0 — Preflight (screenshot this)

```bash
cd /path/to/public_html

mysql -u MYSQL_USER -p kooldb -e "
SELECT DATABASE(), COUNT(*) AS rated_games FROM ratedresults;
SELECT COUNT(*) AS league_awards FROM player_league_award;
SHOW TABLES LIKE 'player_milestones';
SHOW TABLES LIKE 'milestone_definitions';
"
```

**Expected:** `rated_games` ≈ **74870**; `league_awards` ≈ **21873**; `player_milestones` exists; `milestone_definitions` may be missing until step 1.

---

### 1 — Schema (SCH-011–013)

```bash
mysql -u MYSQL_USER -p kooldb < staging-sql/010_milestone_definitions.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/011_player_milestones_source.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/012_player_milestones_source_lobby.sql
```

**Verify (screenshot):**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SHOW COLUMNS FROM player_milestones LIKE 'source_kind';
SHOW TABLES LIKE 'milestone_definitions';
"
```

**Expected:** `source_kind` column listed; `milestone_definitions` table exists.

---

### 2 — Catalog load (REP-014)

```bash
php staging-scripts/load_milestone_definitions.php
```

**Expected stdout:** `Catalog rows: 110` then `Done.`

---

### 3 — Full milestones rebuild + giant_slayer fix (REP-008)

This truncates/rebuilds `player_milestones` (110 keys), then applies the **giant_slayer active-#1** surgical fix (365d rolling UTC) — **required**, built into the script.

```bash
php staging-scripts/run_player_milestones_rebuild.php
```

**Expected:** several minutes (period wave is heavy). **Screenshot the full stdout tail** — the script prints these counts:

| Label | Expected |
|-------|----------|
| `distinct_keys` | **110** |
| `total_rows` | **6658** |
| `null_source` | **0** |
| `giant_slayer` | **31** |
| `definitions` | **110** |
| `dd_merchant_10` | **44** |
| `established_20` | **107** |
| `established_20_diff` | **0** |

**Extra verify (screenshot):**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SET time_zone = '+00:00';
SELECT COUNT(*) AS gs FROM player_milestones WHERE milestone_key = 'giant_slayer';
SELECT p.Name, COUNT(*) AS unlocked
FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444'
GROUP BY p.Name;
SELECT milestone_key FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444' AND milestone_key = 'giant_slayer';
"
```

**Expected:** `gs` = **31**; geo4444 **unlocked = 100**; one row `giant_slayer`.

---

### Screenshots to send Dagh

1. Preflight (step 0)  
2. Schema verify (step 1)  
3. Catalog script output (step 2)  
4. **Full tail** of `run_player_milestones_rebuild.php` (step 3)  
5. Extra verify query (step 3)

**Not in scope:** prod C++ post-game (spec in `website-data-contract.md` for later). Staging still has no live game writes.

Thanks,  
Dagh

---

*Repo detail: `docs/coordination/milestones-staging-cutover-packet.md`*

---

## WhatsApp to Steve (copy-paste)

Same steps as **Email to Steve** above. Plain text only. Uses `COUNT(1)` instead of `COUNT(*)` in SQL so WhatsApp does not strip the asterisk and turn it into invalid `COUNT()`.

Subject: Staging kooldb — milestones schema + rebuild + PHP (read-only UI)

---

Hi Steve,

We’re putting personal milestones on staging (kooldb): catalog table + 110 unlock rows + website read UI. Dagh will WinSCP the SQL/PHP files to public_html/ first.

League awards are already done — please do not re-run 008/009 or run_league_awards_rebuild.php.

Please run everything below from public_html (same folder as index.php). Replace MYSQL_USER with your staging DB user. All SQL below is MariaDB-safe (row counts use COUNT(1), same result as COUNT(*)).

---

0 — Preflight (screenshot this)

cd /path/to/public_html

mysql -u MYSQL_USER -p kooldb -e "
SELECT DATABASE(), COUNT(1) AS rated_games FROM ratedresults;
SELECT COUNT(1) AS league_awards FROM player_league_award;
SHOW TABLES LIKE 'player_milestones';
SHOW TABLES LIKE 'milestone_definitions';
"

Expected: rated_games ≈ 74870; league_awards ≈ 21873; player_milestones exists; milestone_definitions may be missing until step 1.

---

1 — Schema (SCH-011–013)

mysql -u MYSQL_USER -p kooldb < staging-sql/010_milestone_definitions.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/011_player_milestones_source.sql
mysql -u MYSQL_USER -p kooldb < staging-sql/012_player_milestones_source_lobby.sql

Verify (screenshot):

mysql -u MYSQL_USER -p kooldb -e "
SHOW COLUMNS FROM player_milestones LIKE 'source_kind';
SHOW TABLES LIKE 'milestone_definitions';
"

Expected: source_kind column listed; milestone_definitions table exists.

---

2 — Catalog load (REP-014)

php staging-scripts/load_milestone_definitions.php

Expected stdout: Catalog rows: 110 then Done.

---

3 — Full milestones rebuild + giant_slayer fix (REP-008)

This truncates/rebuilds player_milestones (110 keys), then applies the giant_slayer active-#1 surgical fix (365d rolling UTC) — required, built into the script.

php staging-scripts/run_player_milestones_rebuild.php

Expected: several minutes (period wave is heavy). Screenshot the full stdout tail — the script prints these counts:

distinct_keys: 110
total_rows: 6658
null_source: 0
giant_slayer: 31
definitions: 110
dd_merchant_10: 44
established_20: 107
established_20_diff: 0

Extra verify (screenshot):

mysql -u MYSQL_USER -p kooldb -e "
SET time_zone = '+00:00';
SELECT COUNT(1) AS gs FROM player_milestones WHERE milestone_key = 'giant_slayer';
SELECT p.Name, COUNT(1) AS unlocked
FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444'
GROUP BY p.Name;
SELECT milestone_key FROM player_milestones pm
JOIN playertable p ON p.ID = pm.player_id
WHERE p.Name = 'geo4444' AND milestone_key = 'giant_slayer';
"

Expected: gs = 31; geo4444 unlocked = 100; one row giant_slayer.

---

Screenshots to send Dagh

1. Preflight (step 0)
2. Schema verify (step 1)
3. Catalog script output (step 2)
4. Full tail of run_player_milestones_rebuild.php (step 3)
5. Extra verify query (step 3)

Not in scope: prod C++ post-game (spec in website-data-contract.md for later). Staging still has no live game writes.

Thanks,
Dagh

---

Repo detail: docs/coordination/milestones-staging-cutover-packet.md
