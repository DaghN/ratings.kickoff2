# Add one milestone after v0 (playbook)

**First use:** `play_streak_100` — **100 days** (May 2026). Use this checklist whenever the catalog grows after the 110-key rebuild shipped.

---

## Catalog total (112, not hard-coded)

Garden intro and profile hero **`{n}/{catalog}`** use `k2_milestone_catalog_total($con)` → `COUNT(*)` from `milestone_definitions`. After adding a key, run `load_milestone_definitions.php` (local: `python scripts/oneoff/load_milestone_definitions.py`) so the count updates. **Editing the seed JSON alone does not change the site.** No PHP constant to bump for display (fallback only if the table is missing).

---

## Garden copy — is it in the DB?

**Yes.** The milestone garden reads **`milestone_definitions.rule_short`** (and `display_name`, tier, token) via `k2_milestone_garden_by_tier()` → card `rule_short` in `player_milestones_helpers.php`. It is **not** hard-coded in PHP.

Optional long copy: `description` column (usually NULL). Tweak `rule_short` in seed and reload catalog.

---

## Checklist (repo)

| Step | What |
|------|------|
| 1 | **Catalog** — Add object to `data/milestones_definitions_seed.json` (`milestone_key`, `display_name`, `tier_band`, `chart_token`, `rule_short`, …). Bump `milestone_count`. |
| 2 | **Garden order** — Add `milestone_key` to `site/public_html/includes/player_milestones_garden_order.php` in the right tier list. **Within a tier, list runs common → rare** (more holders first, fewer holders later). Regenerate probe: `python scripts/oneoff/milestone_unlock_counts.py --write-doc --export-seed` and read `unlock_veterans`. **0** holders → last in Legendary. **Do not** blindly append every new key after the previous add-one unless probe count is truly lowest (e.g. `year_in_heaven` = **5** holders sits with other 5s like `monthly_regular`, not after `club_10000` at 1). |
| 3 | **Unlock SQL** — Generator or hand-written `INSERT` into `player_milestones` (first cross only, `source_kind = game`, `source_game_id`). For `play_streak_100`: `python scripts/oneoff/gen_milestone_play_streak_100_sql.py` → `scripts/ladder/sql/player_milestones_rebuild_play_streak_100.sql`. |
| 4 | **Full rebuild splice** — Append new SQL file to splice list in `scripts/rebuild_website_derived_data_local.ps1` and `staging-scripts/run_player_milestones_rebuild.php` (before league marker block). |
| 5 | **Post-game** — Document in `docs/website-data-contract.md` § `player_milestones`; implement PHP reference (and later C++). `play_streak_100`: `k2_play_streak_maybe_unlock_milestone_100()` when day streak hits 100. |
| 6 | **Parity** — `milestone_definitions` count = N. `COUNT(DISTINCT milestone_key)` in `player_milestones` may be **N−1** if no player has unlocked yet (ultra-rare). |
| 7 | **Sanity** — `python scripts/oneoff/milestone_v0_sanity_check.py` (update expected N if needed). Spot-check garden for a player with/without unlock. |
| 8 | **Local verify (required before browser)** — Run catalog reload + apply unlock SQL (step 9 below). Without this, hero still shows old **/111** and new cards are missing. |

---

## Local verify (required — same as staging surgical path)

After repo edits, **before** expecting UI changes on Laragon:

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python scripts/oneoff/load_milestone_definitions.py
# Apply the new splice SQL (example: year_in_heaven)
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root ko2unity_db -e "source C:/Users/daghn/Desktop/Online and Amiga 500 ELO/scripts/ladder/sql/player_milestones_rebuild_year_in_heaven.sql"
```

Use your key’s `player_milestones_rebuild_<key>.sql` file instead when different. Hard-refresh (`Ctrl+F5`). Confirm `SELECT COUNT(*) FROM milestone_definitions` = **N**.

**Alternative:** full `scripts\rebuild_website_derived_data_local.ps1` (truncates `player_milestones`, re-splices all milestone SQL).

---

## Local commands (regen only)

```powershell
# Regenerate unlock SQL (examples)
python scripts/oneoff/gen_milestone_play_streak_100_sql.py
python scripts/oneoff/gen_milestone_year_in_heaven_sql.py

# Reload catalog only (still required after seed edit)
python scripts/oneoff/load_milestone_definitions.py

# Full milestones rebuild (truncates player_milestones) — last step of derived rebuild
powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
```

---

## Staging verified — `play_streak_100` catalog (May 2026)

Steve / Dagh on `kooldb` after `load_milestone_definitions.php`:

```sql
SELECT COUNT(*) FROM milestone_definitions;
SELECT display_name, rule_short FROM milestone_definitions WHERE milestone_key = 'play_streak_100';
```

| Check | Expected |
|-------|----------|
| `COUNT(*)` | **111** |
| `play_streak_100` | `display_name` **100 days**; `rule_short` **100 consecutive UTC days with a rated game** |

Unlock rows for this key still **0** on May 2026 import (max personal day streak 87). Run `run_milestone_play_streak_100_unlock.php` when you want the splice applied without full REP-008.

---

## Staging (no full truncate if prod-like DB already at 110 keys)

| Step | Command / file |
|------|----------------|
| WinSCP | `data/milestones_definitions_seed.json` → `staging-data/` |
| WinSCP | `player_milestones_rebuild_play_streak_100.sql` → `staging-sql/milestones/` |
| WinSCP | PHP: `load_milestone_definitions.php`, `run_milestone_play_streak_100_unlock.php`, helpers, garden order, `player_play_streaks.php` |
| Steve | `php staging-scripts/load_milestone_definitions.php` (expect **111** definitions) |
| Steve | `php staging-scripts/run_milestone_play_streak_100_unlock.php` (expect **0** unlock rows on May 2026 import — max day streak 87) |
| Browser | Garden shows **100 days** locked card; geo4444 still **100/111** or **100/110** until first holder |

Full REP-008 re-run still valid after splice update; surgical path avoids wiping 6658 rows.

---

## `year_in_heaven` reference (May 2026)

| Field | Value |
|-------|--------|
| `milestone_key` | `year_in_heaven` |
| `display_name` | **Year in Heaven** |
| `rule_short` | **Rated game in every UTC week of a calendar year** |
| Rule | All **52** Monday slots for calendar year **Y** (profile Played weeks grid); first cross only |
| Unlock game | `MIN(ratedresults.id)` on the **week Monday that completes** 52/52 — not a later game that week |
| Depends | `player_period_games` week rows; post-game after week upsert |
| Handoff | [`milestones-year-in-heaven-handoff.md`](milestones-year-in-heaven-handoff.md) |

---

## `play_streak_100` reference

| Field | Value |
|-------|--------|
| `milestone_key` | `play_streak_100` |
| `display_name` | **100 days** |
| `rule_short` | **100 consecutive UTC days with a rated game** |
| Rule | First time `player_period_games` day streak reaches 100; establishing game = `MIN(id)` on day 100 of the run |
| Depends | `player_period_games` (day rows); **REP-015 staging done** May 2026 (play-streak UI/HoF on `kooldb`) |

---

## Prod

Steve: reload catalog + surgical unlock SQL (or wait for full rebuild). C++ post-game when M4+ includes play-streak day counter / reads `player_play_streaks`.
