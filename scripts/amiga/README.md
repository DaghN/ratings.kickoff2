# Amiga realm scripts

Phase A0+ tooling for the offline Access source (`data/amiga/source/koatd.mdb`).

## Requirements

- Windows with **Microsoft Access Driver** (`*.mdb`, `*.accdb`) — usually installed with Office or Access Database Engine.
- Python 3 + `pyodbc` (already used elsewhere in the repo; `pip install pyodbc` if needed).

## Commands (repo root)

```powershell
# One-shot local build (create DB, import, Elo replay)
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Or step by step (import alone clears derived tables — always replay before browsing):
python -m scripts.amiga import --recreate-schema
python -m scripts.amiga replay
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-import-manifest
python -m scripts.amiga verify-tournament-formats
python -m scripts.amiga fixtures verify
python -m scripts.amiga audit-catalog-dates

# Ops simul — parity gate (500 games; oracle: python -m scripts.amiga replay --limit 500):
python -m scripts.amiga replay --limit 500
python -m scripts.amiga verify-chronology
php site/public_html/amiga/ops/run_process_game.php zero-derived
php site/public_html/amiga/ops/run_process_game.php replay-to --limit 500
php site/public_html/amiga/ops/run_process_game.php verify   # ratings + standings spot-checks

# Full derived rebuild (batch repair — use Python, not unbounded PHP replay-to):
python -m scripts.amiga replay

# Tournament standings parity vs Access Tables (reference only):
python -m scripts.amiga standings-parity --tournament "London XXIII"
python -m scripts.amiga standings-parity --tournament "World Cup XI" --scope group --scope-key "Round 1 - Group A"

# Full sweep (overall + World Cup groups; JSON report):
python -m scripts.amiga standings-parity --sweep
python -m scripts.amiga standings-parity --sweep --only-failures
python -m scripts.amiga standings-parity --sweep --tournament-id 42 --fail-fast

# Incremental post-game (live append-only — last game in contract order):
php site/public_html/amiga/ops/run_process_game.php process-one --game-id=N

# Schema inventory from Access
python scripts/amiga/discover_access_schema.py

# SQL dump for staging (after local ko2amiga_db is ready)
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

Export writes **23 part files** + `ko2amiga_manifest.json` under `site/public_html/amiga/_import/` (plus optional full `ko2amiga_db.sql`). Sync all of `_import/` via WinSCP.

**Staging refresh:** WinSCP sync `public_html/`, then browser import (verified Jun 2026, A2 schema):

- **Preview:** `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` — confirm `parts: 23`
- **Apply:** same URL with `&apply=1&part=1` (auto-continues)
- **Local dry-run:** `http://ratingskickoff.test/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`

Script: `site/public_html/amiga/run_import_ko2amiga.php` · payload: `site/public_html/amiga/_import/`

Browser (local pages):

- `http://ratingskickoff.test/amiga/rating.php`
- `http://ratingskickoff.test/amiga/profile.php?id=1`
- `http://ratingskickoff.test/amiga/games.php?id=1`
- `http://ratingskickoff.test/amiga/tournaments.php`
- `http://ratingskickoff.test/amiga/tournament.php?id=1`

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

**Tournament fixtures foundation** (internal ops only; public builder UI deferred):

```powershell
mysql ko2amiga_db < scripts/amiga/sql/006_tournament_fixtures.sql
mysql ko2amiga_db < scripts/amiga/sql/007_tournament_entrants.sql
mysql ko2amiga_db < scripts/amiga/sql/008_tournament_lifecycle.sql
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

**Internal tournament builder** (supported starters: `kitchen_marathon`, minimal `group_knockout`):

```powershell
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

# Browser ops: /amiga/ops/fixtures.php (password-gated) shows lifecycle status and supports
# draft→ready, ready→running, running→completed (no scheduled fixtures), running→void (no games).
# Imported tournaments and --force transitions remain CLI-only.
# Browser entrant ops (generated tournaments): list entrants, search existing players, add existing
# entrant (draft/registration/ready), withdraw, replace — same guardrails as fixtures add/withdraw/replace CLI.
# Browser stage placement: list stages/stage players, place/update registered entrant (draft/registration/ready)
# with optional seed/group — same guardrails as fixtures place-entrant. Late workflow: add entrant → place in stage → assign fixtures.
# Browser fixture assignment: incomplete scheduled fixtures show stage-scoped player selects (fallback numeric ids when <2 stage players).
# POST assign_players — same guardrails as fixtures set-players; no running lifecycle required.
# No browser player creation or onboard-newcomer; use CLI for those.

# Fixture-backed result entry creates one canonical game and marks the fixture played.
# record-result requires lifecycle_status=running and both players to be active (registered) entrants.
# attach-game links an existing unattached game to a scheduled fixture with the same players;
# requires lifecycle_status=running, active entrants, fixture players already set, and no existing attachment.
# set-players requires active entrants but does not require running lifecycle.
python -m scripts.amiga fixtures list --tournament-id N
python -m scripts.amiga fixtures detail --fixture-id F
python -m scripts.amiga fixtures set-players --fixture-id F --player-a-id 1 --player-b-id 2 --dry-run
python -m scripts.amiga fixtures attach-game --game-id G --fixture-id F --dry-run
python -m scripts.amiga fixtures record-result --fixture-id F --goals-a 3 --goals-b 2 --dry-run
python -m scripts.amiga fixtures record-result --fixture-id F --goals-a 3 --goals-b 2
php site/public_html/amiga/ops/run_process_game.php process-one --game-id=G

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

Live tournaments hub (Amiga realm tab): `/amiga/live-tournaments.php` — lists generated events; links to the fixture manager.

Internal fixture browser/create/result entry: `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee&tournament_id=N` (same site chrome as the hub).

Staging pages: `https://ratings.kickoff2.com/amiga/…` — DB config in `site/config/ko2amiga_config.local.php`; handoff [`docs/amiga-staging-handoff.md`](../../docs/amiga-staging-handoff.md).

Profile template: [`docs/amiga-profile-v0.md`](../../docs/amiga-profile-v0.md)

## Docs

- **Import layer:** [`docs/amiga-import-layer.md`](../../docs/amiga-import-layer.md) — archival → ground truth, overrides, manifest
- **Data contract:** [`docs/amiga-data-contract.md`](../../docs/amiga-data-contract.md) — ground / derived / reference layers; **match streaks are not product truth** (§ Match streaks)
- Discovery write-up: [`docs/amiga-schema-discovery.md`](../../docs/amiga-schema-discovery.md)
- Source layout: [`data/amiga/README.md`](../../data/amiga/README.md)
