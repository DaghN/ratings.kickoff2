# Amiga A2 restructure audit — 2026-06-06

**Scope:** `ko2amiga_db` ground/derived split (Phase A2), import/replay pipeline, PHP read path, staging export/import.  
**Landmark commit:** `d2f4b83` (referenced; not re-verified via `git show` in this pass).  
**Auditor mode:** Read-only at audit time. **Follow-up (same day):** F-01–F-04 fixed in code/docs; see § Resolutions.

---

## Executive summary

**Verdict: Pass with caveats** (at audit time). **Post-fix:** F-01–F-04 resolved; re-export + staging import recommended so live ratings match contract chronology.

The A2 split is implemented end-to-end and behaves correctly on a live local `ko2amiga_db`: ground tables are populated by import only, derived tables by replay only, PHP reads join via `amiga_db.php` without legacy SQL views, and staging export produces 16 manifest parts that all exist on disk. Spot-check ratings at audit time (Fabio F ~2550, Gianni T ~2541, Dagh N ~2494) matched expected sanity; double replay was idempotent (27,408 rating rows).

Remaining caveats: Amiga `ProcessCompletedGame` PHP ops unbuilt (Track A gap), optional comment/doc nits (F-05–F-07). After F-03 fix, contract-order replay shifts a few top ratings slightly (e.g. Fabio F 2550→2551, Dagh N 2494→2496) — expected and authoritative per [`amiga-data-contract.md`](../amiga-data-contract.md).

---

## Resolutions (2026-06-06)

| ID | Status | Change |
|----|--------|--------|
| F-01 | **Fixed** | `export_ko2amiga_db.ps1` queries `MAX(id)` from `amiga_games`; stats part index is dynamic |
| F-02 | **Fixed** | Contract + README + import docstring + post-import warning log |
| F-03 | **Fixed** | `replay.py` and `player_rating_history.php` (amiga) use `chrono`, `event_date`, `source_scores_id` order |
| F-04 | **Fixed** | `docs/amiga-profile-v0.md` updated to A2 table names |
| F-05 | **Fixed** | `amiga-schema-discovery.md` player count → 473 |
| F-06 | **Fixed** | `PROJECT_MEMORY.md` A1 changelog count → 473 |

---

## Findings

| ID | Severity | Area | Finding | Evidence | Recommendation |
|----|----------|------|---------|----------|----------------|
| F-01 | **Major** | Staging export | `maxId = 27408` is hard-coded for games/ratings chunking. New games after the next live submission or re-import will be **omitted** from parts 04–15 unless the script is updated. | `scripts/export_ko2amiga_db.ps1:50` | Query `MAX(id)` / `MAX(game_id)` from DB at export time (S). |
| F-02 | **Minor** | Import boundary | `truncate_ground_truth()` also `TRUNCATE`s `amiga_game_ratings` and `amiga_player_stats`. Import does not *write* derived data, but it **clears** derived on every import. Running `import` without `replay` leaves an empty read path. | `scripts/amiga/import_access.py:96–104` | Document explicitly in contract/README; consider moving derived truncate to `replay` only or `run` orchestration (S). |
| F-03 | **Minor** | Replay chronology | Replay uses `ORDER BY game_date ASC, id ASC`, not explicit `tournaments.chrono, event_date, source_scores_id`. Synthetic dates collide across tournaments (1,515 duplicate `game_date` groups; up to 6 games share one timestamp). Ratings match sanity checks today, but ordering is implicit via insert `id`. | `scripts/amiga/replay.py:18–23`; DB query | Add contract-order `ORDER BY` with tournament joins, or document + test that `(game_date, id)` ≡ contract order (M). |
| F-04 | **Minor** | Documentation | `docs/amiga-profile-v0.md` still references `playertable.ID`, `playertable` reads, `ratedresults` scans, and says “No Amiga derived tables yet” — contradicts A2 and its own § Data strategy table. | `docs/amiga-profile-v0.md:10–19,28,56` | Refresh v0 doc to A2 table names (S). |
| F-05 | **Nit** | Documentation | `docs/amiga-schema-discovery.md` says 474 players after merges; live DB and staging handoff say **473**. | `docs/amiga-schema-discovery.md:219` vs `docs/amiga-staging-handoff.md:54`; DB count | Align player count in discovery doc (S). |
| F-06 | **Nit** | Documentation | `PROJECT_MEMORY.md` changelog still says “474 players” for A1. | `PROJECT_MEMORY.md:120` | One-line correction (S). |
| F-07 | **Nit** | Comments | Amiga PHP includes use “ratedresults-shaped” / “playertable-shaped” in comments only — not SQL views. Harmless but grep-noisy. | `site/public_html/includes/amiga_db.php:3,7,53` | Optional comment cleanup (S). |
| F-08 | **Known gap** | Live ops | No Amiga `ProcessCompletedGame` PHP path. First live Amiga game cannot be submitted through the website; requires import + full replay or manual SQL + replay. | `docs/amiga-data-contract.md:97–98,143` | Track A follow-up after A2 sign-off (L). |
| F-09 | **Known gap** | Track B | `amiga_tournament_standings`, `reference_*` tables not implemented. | `docs/amiga-data-contract.md:126–127,144–145` | Planned; not an A2 failure. |
| F-10 | **Nit** | Security | Staging import password `coffee` is in source, docs, and URL examples. `_import/.htaccess` blocks direct SQL download; importer gated by `once=` + password. | `run_import_ko2amiga.php:204–205`; `_import/.htaccess` | Acceptable for staging ops; rotate if URL leaks (S). |
| F-11 | **Nit** | Tooling | `php -l` on import script not run — `php` not on PATH in audit environment. | Shell attempt | Run on Laragon/staging host before deploy (S). |

**Not raised as findings (verified OK):**

- No SQL `VIEW`s or persistent tables named `ratedresults` / `playertable` / `generalstatstable` in Amiga paths.
- `import_access.py` legacy drops (`ratedresults`, `playertable`) only run under `--recreate-schema` — migration cleanup, not hot path.
- API `realm=amiga` branches in `player_rating_history.php` and `player_search.php` use `ko2amiga_config.php` and A2 tables; no fall-through to online SQL.
- `scripts/ladder/engine.py` `apply_game_row()` is shared cleanly; Amiga replay passes `server_records=None` and skips online-only rebuild hooks.

---

## Contract compliance matrix

| Table | Layer | Writer (contract) | Compliant? | Notes |
|-------|-------|-------------------|------------|-------|
| `tournaments` | Ground | Import / submission | **Yes** | `import_access.py` INSERT only |
| `amiga_players` | Ground | Import / submission | **Yes** | Name `utf8mb4_bin` UNIQUE |
| `amiga_games` | Ground | Import / submission | **Yes** | `source_scores_id` UNIQUE; FKs to players/tournaments |
| `amiga_game_ratings` | Derived | Replay | **Yes** | 1:1 `game_id` PK; replay INSERT only; import TRUNCATEs on full reimport (F-02) |
| `amiga_player_stats` | Derived | Replay | **Yes** | Column names match `PlayerState.to_db_row()` |
| `amiga_tournament_standings` | Derived | Planned Track B | **N/A** | Not in DDL |
| `reference_*` | Reference | Parity tooling | **N/A** | Not in DDL |
| Legacy `ratedresults` / `playertable` | — | — | **Yes (absent)** | Dropped on `--recreate-schema` only |

**Layer boundary writes:**

| Path | Touches ground? | Touches derived? | Verdict |
|------|-----------------|------------------|---------|
| `import_access.py` | INSERT ground | TRUNCATE derived (no INSERT) | Pass* (see F-02) |
| `replay.py` | READ ground only | DELETE + INSERT derived | Pass |
| PHP read path | READ only | READ only | Pass |

**SQL views faking old shape:** None. Join logic centralized in `amiga_db.php`.

---

## Smoke test results

Environment: Windows, Laragon MySQL, repo root. PHP not on PATH locally.

| Command | Result |
|---------|--------|
| `python -m scripts.amiga replay --dry-run` | OK — 27,408 games, 473 players; sample game id=1 → 1616 / 1584 |
| `python -m scripts.amiga replay` | OK — 27,408 `amiga_game_ratings` rows; 473 `amiga_player_stats` rows (~8s) |
| Double replay idempotency | OK — 27,408 rows both runs; Fabio F `2550.27305` stable |
| `powershell -File scripts\export_ko2amiga_db.ps1` | OK — 16 parts + manifest + full dump |
| `php -l site/public_html/amiga/run_import_ko2amiga.php` | **Not run** — `php` not found on PATH |

### Row counts (local `ko2amiga_db`)

| Table | Count |
|-------|------:|
| `tournaments` | 604 |
| `amiga_players` | 473 |
| `amiga_games` | 27,408 |
| `amiga_game_ratings` | 27,408 |
| `amiga_player_stats` | 473 |

### Top ratings spot-check

| Player | Rating (rounded) | Expected (~) | Match |
|--------|------------------|--------------|-------|
| Fabio F | 2550 | ~2550 | Yes |
| Gianni T | 2541 | ~2540 | Yes |
| Dagh N | 2494 | ~2494 | Yes |

### Staging export manifest

- `ko2amiga_manifest.json`: **16 parts**, all files present under `site/public_html/amiga/_import/`
- Part order: schema → tournaments → players → 6×(games chunk + ratings chunk) → stats
- `ko2amiga_01_schema.sql` includes `DROP TABLE IF EXISTS` for all A2 tables (full rebuild on part 1)

### Chronology probe

- Duplicate `game_date` groups: **1,515** (max 6 games per timestamp)
- Full contract-order vs `(game_date, id)` violation count: **not completed** (O(n²) query timed out >95s)

---

## Stale references (Amiga-relevant grep)

### Code — intentional / migration only

| Location | Context |
|----------|---------|
| `scripts/amiga/import_access.py:31–32` | Legacy table names in `_AMIGA_TABLES_DROP_ORDER` for `--recreate-schema` |
| `site/public_html/includes/amiga_db.php` | Comments: “ratedresults-shaped rows” (subquery alias `r`, not a DB view) |
| `site/public_html/includes/amiga_profile_blocks.php:3` | Comment: “from playertable” |
| `site/public_html/includes/amiga_player_game_row.php:36` | PHPDoc: “ratedresults row” |

### Docs — need A2 refresh

| File | Issue |
|------|-------|
| `docs/amiga-profile-v0.md` | URLs and data sources still say `playertable` / `ratedresults`; “No derived tables yet” |
| `docs/amiga-schema-discovery.md` | § Proposed mapping still A1 vocabulary (historical); player count 474 vs 473 |
| `PROJECT_MEMORY.md:120` | “474 players” in A1 changelog |

### Out of scope (online only — no action for A2)

`site/public_html/includes/status_queries.php`, `site/public_html/ops/*`, online API endpoints, `docs/ratedresults-schema.md`, etc.

---

## Read path review (summary)

| Page / API | Ground source | Derived source | Join pattern | Issues |
|------------|---------------|----------------|--------------|--------|
| `amiga/rating.php` | `amiga_players` | `amiga_player_stats` | `amiga_player_base_from_sql()` INNER JOIN; `ORDER BY s.Rating DESC` | None |
| `amiga/profile.php` | `amiga_players` | `amiga_player_stats` | via `amiga_player_load()` | None |
| `amiga/games.php` | `amiga_games`, players, tournaments | `amiga_game_ratings` | `amiga_rated_games_from_sql()` INNER JOIN | Opponent list uses ground `amiga_games` only (OK) |
| `api/player_rating_history.php?realm=amiga` | games + players | ratings | `amiga_rated_games_from_sql()` | `timelineStart` from `MIN(game_date)` on ground |
| `api/player_search.php?realm=amiga` | `amiga_players` | `amiga_player_stats` | INNER JOIN, separate DB connection | No online leakage |

No double-counting: `amiga_game_ratings.game_id` is PK (1:1 with `amiga_games.id`).

---

## Schema integrity (static)

| Check | Status |
|-------|--------|
| FK creation order on empty DB | OK — `tournaments` → `amiga_players` → `amiga_games` → derived tables |
| `source_scores_id` UNIQUE | OK — `uq_amiga_games_source_scores_id` |
| Player name case sensitivity | OK — `utf8mb4_bin` on `amiga_players.name` |
| Ground/derived column mix on one table | OK — clean split |
| Derived columns vs replay vs PHP | OK — snake_case in DB; PHP subquery aliases legacy PascalCase for consumers |
| Indexes for read path | Adequate for current scale — `game_date`, player FKs, `game_id` PK |

---

## Import / replay correctness (static + runtime)

**Import (`import_access.py`):**

- Tournament catalog from `[Tournament players]`; games from `Scores`; countries from `Rankings`
- Chronology at import: sort `(chrono, event_date, source_scores_id)` then synthetic `game_date` = tournament day + N seconds per game within tournament
- Name merges via `player_names.py`; log to `data/amiga/exports/name_merges.json`
- `--recreate-schema`: drops legacy + A2 tables, applies `001_core.sql`, then truncates + reloads ground

**Replay (`replay.py`):**

- `clear_derived`: `DELETE` from ratings + stats only (does not truncate `amiga_games`)
- Elo: `START_RATING=1600`, `K_FACTOR=32` via `scripts/ladder/constants.py`
- `SET time_zone = '+00:00'` on connect
- Per-game row count matches games: 27,408 = 27,408
- Stats: all 473 imported players have ≥1 game → 473 stat rows

---

## Recommended follow-ups (ordered)

| # | Item | Effort | Priority |
|---|------|--------|----------|
| 1 | ~~Dynamic `MAX(id)` in export~~ | Done | — |
| 2 | ~~Refresh `amiga-profile-v0.md`~~ | Done | — |
| 3 | ~~Import/replay boundary docs~~ | Done | — |
| 4 | ~~Contract-order replay~~ | Done | Re-export staging after replay |
| 5 | Amiga `ProcessCompletedGame` PHP ops (ground INSERT + derived incremental) | **L** | High for live games |
| 6 | Track B: tournament standings + reference parity tables | **L** | Product backlog |
| 7 | Optional: reduce grep noise in Amiga PHP comments | **S** | Low |

---

## Audit commands reference

```powershell
# Full rebuild
python -m scripts.amiga run --recreate-schema
python -m scripts.amiga replay

# Stale names (Amiga paths)
rg -i "ratedresults|playertable|generalstatstable" scripts/amiga site/public_html/amiga site/public_html/includes/amiga_*.php site/public_html/api/player_rating_history.php site/public_html/api/player_search.php

# Export
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

---

*Generated 2026-06-06. Static code review + local MySQL smoke tests. Staging browser import (build `a2-2026-06-06-b4`) cited from handoff/docs as pre-verified; not re-run in this audit pass.*
