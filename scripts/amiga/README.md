# Amiga realm scripts

Phase A0+ tooling for the offline Access source (`data/amiga/source/koatd.mdb`).

## Requirements

- Windows with **Microsoft Access Driver** (`*.mdb`, `*.accdb`) — usually installed with Office or Access Database Engine.
- Python 3 + `pyodbc` (already used elsewhere in the repo; `pip install pyodbc` if needed).

## Commands (repo root)

```powershell
# Finalize one tournament (frozen Elo — see docs/amiga-tournament-finalize-rating-contract.md):
python -m scripts.amiga finalize-tournament --tournament-id=N

# Corrections after finalize (rebuild-forward from T — contract § 6.3):
python -m scripts.amiga reopen-tournament --tournament-id=T
python -m scripts.amiga refinalize-from --tournament-id=T
python -m scripts.amiga refinalize-smoke

# One-shot local build (create DB, import, Elo replay)
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Or step by step (import alone clears derived tables — always replay before browsing):
python -m scripts.amiga import --recreate-schema
python -m scripts.amiga replay
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-import-manifest
python -m scripts.amiga verify-tournament-formats
python -m scripts.amiga fixtures verify
python -m scripts.amiga audit-catalog-dates

# Batch derived rebuild + verify (oracle):
python -m scripts.amiga replay
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups

# Full replay (~27k games, ~23s local Jun 2026): shared in-memory players across tournaments;
# each finalize writes amiga_game_ratings + amiga_rating_events. amiga_player_stats +
# network counts + peak/nadir once via commit_heavy_player_derived(players). Live
# finalize-tournament (PHP or Python CLI) loads from DB and persists full stats per event.
# Live one-event finalize at catalog tail (full history on disk): ~0.7s local (Jun 2026).

# Partial rebuild smoke (≥500 games in scope; verify-rating-events requires full replay):
python -m scripts.amiga replay --limit 500

# PHP replay-to removed — batch oracle is Python replay only.

# Tournament standings parity vs Access Tables (reference only):
python -m scripts.amiga standings-parity --tournament "London XXIII"
python -m scripts.amiga standings-parity --tournament "World Cup XI (Birmingham)" --scope group --scope-key "Round 1 - Group A"

# Full sweep (overall + World Cup groups; JSON report):
python -m scripts.amiga standings-parity --sweep
python -m scripts.amiga standings-parity --sweep --only-failures
python -m scripts.amiga standings-parity --sweep --tournament-id 42 --fail-fast

# Finalize one tournament (PHP — same semantics as Python finalize-tournament):
php site/public_html/amiga/ops/run_process_game.php finalize-tournament --tournament-id=N

# process-one hard-fails for tournament-tagged games — use fixtures ops + finalize-tournament

# Schema inventory from Access
python scripts/amiga/discover_access_schema.py

# SQL dump for staging (after local ko2amiga_db is ready)
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

Export writes **24 part files** + `ko2amiga_manifest.json` under `site/public_html/amiga/_import/` (plus optional full `ko2amiga_db.sql`). Part 24 is `amiga_rating_events`. Sync all of `_import/` via WinSCP.

**Staging refresh:** WinSCP sync `public_html/`, then browser import (verified Jun 2026, A2 schema):

- **Preview:** `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` — confirm `parts: 24`
- **Apply:** same URL with `&apply=1&part=1` (auto-continues)
- **Local dry-run:** `http://ratingskickoff.test/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`

Script: `site/public_html/amiga/run_import_ko2amiga.php` · payload: `site/public_html/amiga/_import/`

Browser (local pages):

- `http://ratingskickoff.test/amiga/rating.php`
- `http://ratingskickoff.test/amiga/profile.php?id=1`
- `http://ratingskickoff.test/amiga/games.php?id=1`
- `http://ratingskickoff.test/amiga/tournaments.php`
- `http://ratingskickoff.test/amiga/tournament.php?id=1`
- `http://ratingskickoff.test/amiga/hall-of-fame.php`
- `http://ratingskickoff.test/amiga/leaderboards/tournament-honours.php`

Browser QA checklist: [`docs/amiga-profile-v0.md`](../../docs/amiga-profile-v0.md) § Browser QA checklist (standings).

**Track B migration** (existing DBs without `extra` / standings table):

```powershell
# Applied automatically with --recreate-schema; otherwise run once:
mysql ko2amiga_db < scripts/amiga/sql/002_tournament_standings.sql
python -m scripts.amiga import   # reload ground truth with Scores.Extra
python -m scripts.amiga replay   # Elo + standings
```

**Tournament index stats** (`/amiga/tournaments.php` — existing DBs after Jun 2026 perf fix):

```powershell
mysql ko2amiga_db < scripts/amiga/sql/004_tournament_catalog_stats.sql
python -m scripts.amiga catalog-stats-rebuild   # or full replay
```

**Tournament format foundation** (legacy imports + future format templates):

```powershell
mysql ko2amiga_db < scripts/amiga/sql/005_tournament_formats.sql
python -m scripts.amiga import
python -m scripts.amiga verify-tournament-formats
```

**Tournament structure backfill** (historical events with NULL Access phases):

```powershell
# List registered specs (active + stub)
python -m scripts.amiga structure list

# Verify a spec against Access before enabling import (no MySQL required)
python -m scripts.amiga structure verify --tournament "Homburg"
python -m scripts.amiga structure verify --tournament "Athens LXI"   # stub — expect FAIL

# Find candidates still needing a spec
python -m scripts.amiga audit-suspicious-marathons
# JSON rows include structure_spec_status: null | stub | active
```

### How to add a structure spec

1. **Evidence** — forum thread or results page with groups + KO rounds.
2. **Spec module** — add `scripts/amiga/tournament_structure/<name>.py` defining a `StructureSpec` (group rosters + knockout `FixtureSpec` rows with `leg_no`).
3. **Register** — append a `RegistryEntry(..., status="active")` in `tournament_structure/registry.py`. Use `status="stub"` while drafting.
4. **Verify** — `python -m scripts.amiga structure verify --tournament "<Catalog name>"` must pass (fixture count = Access game count, all games link).
5. **Import** — `python -m scripts.amiga import` then `python -m scripts.amiga replay`. Active specs create stages/fixtures and set `amiga_games.fixture_id` automatically.

Pilot: **Homburg** (`homburg.py`, forum t=7711). No import code changes needed for tournament #2 — only registry + spec data.

**Format templates** (including planned stubs):

```powershell
python -m scripts.amiga verify-tournament-formats
# Reports: 6 templates, all implemented
```

Extension contract: [`docs/amiga-tournament-format-vision.md`](../../docs/amiga-tournament-format-vision.md) §9 · Swiss checklist: [`docs/amiga-format-add-swiss-checklist.md`](../../docs/amiga-format-add-swiss-checklist.md)

**Player universe derived tables** (participation, H2H, server records — contract [`amiga-player-universe-contract.md`](../../docs/amiga-player-universe-contract.md)):

```powershell
# Full stack is rebuilt by replay (after standings):
# participation → totals → matchup_summary → generalstats → catalog_stats

# Standalone rebuilds (idempotent):
python -m scripts.amiga performance-rating-rebuild   # after 015 migration; participation-rebuild runs this too
python -m scripts.amiga participation-rebuild
python -m scripts.amiga matchup-rebuild
python -m scripts.amiga generalstats-rebuild

# Live finalize hook (one tournament; optional --skip-standings):
python -m scripts.amiga participation-refresh-tournament --tournament-id N

# Parity gates (player universe contract §8):
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups

# WC medal spot-check vs Access (sample tournaments):
python -m scripts.amiga honours-parity-sample
```

PHP live path: `amiga_ops_participation_refresh_tournament` in `finalize_tournament.php` after standings commit.

Participation **roster = `amiga_games`**; finish from `participation_placement.py` / `includes/amiga_participation_placement.php` → `event_finish_position` per [`docs/amiga-tournament-honours-rules.md`](../docs/amiga-tournament-honours-rules.md) (tiers A–E; Tier E = `amiga_tournament_finish_override`). Phase ranks stay in `amiga_tournament_standings` only.

**Event finish migrations (Jun 2026):** apply `017` → `018` → `019` on existing DBs, then `participation-rebuild`. Fresh installs: `010` includes `event_finish_position` (no `overall_position`).

**Standings scope migration (Jun 2026 — complete):** apply `020` after `019` on existing DBs (`overall`+`group` → `league`; `group_scopes` → `league_scopes`). Fresh installs: `002`/`004` already use `league` enum. Policy: [`docs/amiga-standings-scope-policy.md`](../docs/amiga-standings-scope-policy.md). After migrate: full `python -m scripts.amiga replay` + verify suite. Parity CLI may still use `overall`/`group` as Access comparison labels (`standings_parity.py`).

Read surfaces: profile recent tournaments + top opponents; `/amiga/player-tournaments.php` (full history, `event_points`, Perf. rating — [`amiga-performance-rating.md`](../../docs/amiga-performance-rating.md)); `/amiga/hall-of-fame.php`; `/amiga/leaderboards/tournament-honours.php`.

**Participation columns (Jun 2026):** `event_points` + W-D-L from `amiga_games` rollup; phase league points only in `amiga_tournament_standings`. See contract §5.2.1. Existing DBs: apply `014` before rebuild if the table still has a `points` column.

**Tournament fixtures foundation** (internal ops only; public builder UI deferred):

```powershell
mysql ko2amiga_db < scripts/amiga/sql/006_tournament_fixtures.sql
mysql ko2amiga_db < scripts/amiga/sql/007_tournament_entrants.sql
mysql ko2amiga_db < scripts/amiga/sql/008_tournament_lifecycle.sql
mysql ko2amiga_db < scripts/amiga/sql/009_rating_events.sql
mysql ko2amiga_db < scripts/amiga/sql/010_player_tournament_participation.sql
mysql ko2amiga_db < scripts/amiga/sql/011_player_tournament_totals.sql
mysql ko2amiga_db < scripts/amiga/sql/012_player_matchup_summary.sql
mysql ko2amiga_db < scripts/amiga/sql/013_generalstats.sql
mysql ko2amiga_db < scripts/amiga/sql/014_participation_event_points.sql
mysql ko2amiga_db < scripts/amiga/sql/015_performance_rating.sql
mysql ko2amiga_db < scripts/amiga/sql/016_participation_avg_goals.sql
mysql ko2amiga_db < scripts/amiga/sql/017_event_finish_position.sql
mysql ko2amiga_db < scripts/amiga/sql/018_drop_overall_position.sql
mysql ko2amiga_db < scripts/amiga/sql/019_tournament_finish_override.sql
mysql ko2amiga_db < scripts/amiga/sql/020_unify_league_standings_scope.sql
python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle

# Backfill missing entrants on pre-foundation generated tournaments (dry-run first).
python -m scripts.amiga fixtures backfill-entrants --dry-run
python -m scripts.amiga fixtures backfill-entrants
python -m scripts.amiga fixtures backfill-entrants --tournament-id N --dry-run

# Entrant onboarding (generated tournaments only; dry-run first).
python -m scripts.amiga fixtures add-entrant --tournament-id N --player-id P --seed-no 5 --note "late signup" --dry-run
python -m scripts.amiga fixtures add-entrant --tournament-id N --player-id P --seed-no 5 --note "late signup"
python -m scripts.amiga fixtures onboard-newcomer --tournament-id N --full-name "Mark Bentley" --country "England" --seed-no 5 --dry-run
python -m scripts.amiga fixtures onboard-newcomer --tournament-id N --name "Mark Be" --country "England" --seed-no 5 --dry-run

# Entrant status changes (generated tournaments only; dry-run first).
python -m scripts.amiga fixtures withdraw-entrant --tournament-id N --player-id P --dry-run
python -m scripts.amiga fixtures withdraw-entrant --tournament-id N --player-id P --note "injury"
python -m scripts.amiga fixtures replace-entrant --tournament-id N --old-player-id OLD --new-player-id NEW --dry-run
python -m scripts.amiga fixtures replace-entrant --tournament-id N --old-player-id OLD --new-player-id NEW --note "late swap"

# Stage placement after late entrant registration (generated tournaments only; dry-run first).
python -m scripts.amiga fixtures add-stage-player --tournament-id N --stage-key overall --player-id P --seed-no 5 --dry-run
python -m scripts.amiga fixtures add-stage-player --tournament-id N --stage-key overall --player-id P --seed-no 5
python -m scripts.amiga fixtures place-entrant --tournament-id N --stage-key overall --player-id P --seed-no 5 --group-key A --dry-run

# Examples for future live events:
python -m scripts.amiga fixtures create-stage --tournament-id 1 --stage-key overall --name "Overall" --stage-type league
python -m scripts.amiga fixtures create-fixture --tournament-id 1 --stage-key overall --fixture-key overall-001 --player-a-id 1 --player-b-id 2
```

**Internal tournament builder** (supported starters: `kitchen_marathon`, `group_knockout`, `swiss`):

```powershell
# Swiss (round 1 by seed; later rounds via generate-swiss-round)
python -m scripts.amiga build-tournament create-swiss --name "Test Swiss I" --event-date 2026-06-08 --country Denmark --player-ids 1,2,3,4,5,6 --dry-run
python -m scripts.amiga build-tournament generate-swiss-round --tournament-id N --round 2 --dry-run
python -m scripts.amiga build-tournament smoke-swiss --player-ids 1,2,3,4

# Double elimination (4 or 8 players; advance after each round completes)
python -m scripts.amiga build-tournament create-double-elim --name "Test DE I" --event-date 2026-06-08 --player-ids 1,2,3,4 --dry-run
python -m scripts.amiga build-tournament advance-double-elim --tournament-id N --dry-run
python -m scripts.amiga build-tournament smoke-double-elim --player-ids 1,2,3,4

# Dry-run rolls back after building the structure.
python -m scripts.amiga build-tournament create-kitchen-marathon --name "Test Kitchen I" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4 --dry-run
python -m scripts.amiga build-tournament smoke-fixture-result --player-ids 1,2

# Real create, then verify before entering results in a later workflow.
python -m scripts.amiga build-tournament create-kitchen-marathon --name "Test Kitchen I" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4
python -m scripts.amiga build-tournament create-group-knockout --name "Test Cup I" --event-date 2026-06-07 --country Denmark --player-ids 1,2,3,4 --group-count 2
python -m scripts.amiga build-tournament verify-built --tournament-id N
python -m scripts.amiga fixtures list-entrants --tournament-id N

# Lifecycle transitions (dry-run first). Generated tournaments start as draft; set running before result entry.
python -m scripts.amiga fixtures set-tournament-status --tournament-id N --status running --dry-run
python -m scripts.amiga fixtures set-tournament-status --tournament-id N --status running
python -m scripts.amiga fixtures set-tournament-status --tournament-id N --status completed --force

# Browser ops: /amiga/ops/fixtures.php (password-gated tournament organizer) uses tabbed views
# (setup, players, fixtures, table, results, advanced). League create uses player search + chips;
# successful create redirects to view=fixtures. Setup tab shows friendly lifecycle labels and
# Start tournament / Mark complete / Void tournament actions (same guardrails as CLI:
# draft|registration→ready→running on start, running→completed when no scheduled fixtures,
# running→void when no games). Raw single-step transitions remain on Advanced.
# Imported tournaments and --force transitions remain CLI-only.
# Browser entrant ops (generated tournaments): list entrants, search existing players, add existing
# entrant (draft/registration/ready), withdraw, replace — same guardrails as fixtures add/withdraw/replace CLI.
# Browser stage placement: list stages/stage players, place/update registered entrant (draft/registration/ready)
# with optional seed/group — same guardrails as fixtures place-entrant. Late workflow: add entrant → place in stage → assign fixtures.
# Browser fixture assignment: incomplete scheduled fixtures show stage-scoped player selects on Advanced (fallback numeric ids when <2 stage players).
# POST assign_players — same guardrails as fixtures set-players (fixture-stage membership); no running lifecycle required.
# Browser fixtures preview: Fixtures tab groups matches by round with friendly schedule rows; technical ids on Advanced.
# Browser results entry: Results tab lists playable fixtures with score forms when running; played results for context;
# record_result POST redirects to view=results. Fixtures tab links to Results when scores can be entered.
# Browser table preview: Table tab shows registered entrants at zero before derived standings exist (read-only presentation).
# No browser player creation or onboard-newcomer; use CLI for those.

# Fixture-backed result entry creates one canonical game and marks the fixture played.
# record-result requires lifecycle_status=running and both players to be active (registered) entrants.
# attach-game links an existing unattached game to a scheduled fixture with the same players;
# requires lifecycle_status=running, active entrants, fixture players already set, and no existing attachment.
# set-players requires active entrants placed in the fixture's stage but does not require running lifecycle.
python -m scripts.amiga fixtures list --tournament-id N
python -m scripts.amiga fixtures detail --fixture-id F
python -m scripts.amiga fixtures set-players --fixture-id F --player-a-id 1 --player-b-id 2 --dry-run
python -m scripts.amiga fixtures attach-game --game-id G --fixture-id F --dry-run
python -m scripts.amiga fixtures record-result --fixture-id F --goals-a 3 --goals-b 2 --dry-run
python -m scripts.amiga fixtures record-result --fixture-id F --goals-a 3 --goals-b 2
# Live browser ops record results + rebuild standings; finalize via finalize-tournament when event closes.

# Cleanup is intentionally limited to unplayed tournament_builder output.
python -m scripts.amiga fixtures cleanup-generated --tournament-id N --dry-run
```

**KOA player naming** (internal CLI only; public newcomer registration deferred):

```powershell
# Check availability (normalizes spacing/trailing period; case-insensitive identity).
python -m scripts.amiga players check-name --name "  Mark   B. "
python -m scripts.amiga players check-name --name "Totally Unique Zz Player"

# Suggest KOA-style abbreviation for a full newcomer name.
python -m scripts.amiga players suggest-name --full-name "Mark Bentley"

# Create a player row (dry-run first; does not register tournament entrants).
python -m scripts.amiga players create --name "Mark Be" --country "England" --dry-run
python -m scripts.amiga players create --name "Mark Be" --country "England"
```

Live tournaments hub (Amiga realm tab): `/amiga/live-tournaments.php` — lists generated events; links to the tournament organizer.

Internal tournament organizer / create / result entry: `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee&tournament_id=N&view=fixtures` (same site chrome as the hub; default `view=fixtures` when `tournament_id` is set, else `view=setup`).

Staging pages: `https://ratings.kickoff2.com/amiga/…` — DB config in `site/config/ko2amiga_config.local.php`; handoff [`docs/amiga-staging-handoff.md`](../../docs/amiga-staging-handoff.md).

Profile template: [`docs/amiga-profile-v0.md`](../../docs/amiga-profile-v0.md)

## Docs

- **Import layer:** [`docs/amiga-import-layer.md`](../../docs/amiga-import-layer.md) — archival → ground truth, catalog overrides, supplemental Scores (e.g. Rodenbach II), manifest
- **Data contract:** [`docs/amiga-data-contract.md`](../../docs/amiga-data-contract.md) — ground / derived / reference layers; **match streaks are not product truth** (§ Match streaks)
- **Player universe:** [`docs/amiga-player-universe-contract.md`](../../docs/amiga-player-universe-contract.md) · plan [`docs/amiga-player-universe-implementation-plan.md`](../../docs/amiga-player-universe-implementation-plan.md)
- Discovery write-up: [`docs/amiga-schema-discovery.md`](../../docs/amiga-schema-discovery.md)
- Source layout: [`data/amiga/README.md`](../../data/amiga/README.md)
