# Amiga realm scripts

**Forward path (Jul 2026):** [`docs/amiga-modern-ground-platform.md`](../../docs/amiga-modern-ground-platform.md) · daily **`simul`** on **`ko2amiga_work`** · code in [`modern/`](modern/README.md).

Legacy Access import (`koatd.mdb`, L0–L5 `prove`) is **oracle/archaeology** — [`docs/archive/amiga-access-pipeline-index.md`](../../docs/archive/amiga-access-pipeline-index.md).

## Requirements

- Windows with **Microsoft Access Driver** (`*.mdb`, `*.accdb`) — usually installed with Office or Access Database Engine.
- Python 3 + `pyodbc` (already used elsewhere in the repo; `pip install pyodbc` if needed).

## Commands (repo root)

**Modern ground cutover (Jul 2026):** [`docs/amiga-modern-ground-platform.md`](../../docs/amiga-modern-ground-platform.md) — forward path is **simul on `ko2amiga_work`**, not full `prove`. **Day 0 seal (D0-1):**

```powershell
python -m scripts.amiga seal-day0
# or: powershell -ExecutionPolicy Bypass -File scripts\export_amiga_day0.ps1
```

Writes L3 witness SQL + `manifest.json` to `data/amiga/day0/` from frozen `ko2amiga_db`. Legacy `prove` below is **archived** after cutover.

**Work DB seed (W-1):**

```powershell
python -m scripts.amiga seed-work
# or: powershell -ExecutionPolicy Bypass -File scripts\seed_ko2amiga_work.ps1
```

Creates **`ko2amiga_work`**, `apply_schema`, loads `manifest.json` → `sql_parts` (skips `day0_01_schema.sql`), clears derived placeholders. Exit: L3 counts match day 0 manifest.

**Modern simul (S-1) — daily path on `ko2amiga_work`:**

```powershell
python -m scripts.amiga simul
# or: powershell -ExecutionPolicy Bypass -File scripts\run_amiga_simul.ps1
python scripts/audit_amiga_modern_compartment.py   # MG11 gate before modern/ edits
```

On **`ko2amiga_work`**: preflight → `apply_schema` (migrate) → L4 disposition (first run) → `clear_derived` + full replay → **video align + verify** (default; `--skip-video` to opt out) → 22-step verify suite. **Preflight/postcheck:** L3 ground must exist and stay **unchanged during simul**; counts may grow above day 0 after forward append (day 0 pin is **`seed-work`** only). Work DB routing: `KO2AMIGA_DATABASE=ko2amiga_work`. Last run: `data/amiga/modern/simul-last.json` (gitignored). **Legacy `prove` on `ko2amiga_db` is frozen** — forward sign-off = simul.

**P-1 parity (oracle vs work):**

```powershell
python -m scripts.amiga parity
# or: powershell -ExecutionPolicy Bypass -File scripts\run_amiga_parity.ps1
```

Compares **29 tables** (export scope): row counts + semantic BIT_XOR/CRC32 signatures. Excludes replay metadata columns (`finalized_at`, `rating_finalized_at`, `created_at`, …) and surrogate `id` on `amiga_tournament_standings`. Full `amiga_generalstats` / `amiga_community_stats` row compare. Report: `data/amiga/modern/parity-last.json`.

**L4 structure verify (L4-1):**

```powershell
python -m scripts.amiga verify-structure-work
```

Disposition register complete; Homburg (`id=137`) + pure_rr smoke (`id=1`) fixtures linked. Report: `data/amiga/modern/l4-verify-last.json`.

**V-1 video (work compartment):**

```powershell
python -m scripts.amiga seal-video-oracle    # one-time oracle snapshot
python -m scripts.amiga seed-video-work       # copy shared editorial → work/
python -m scripts.amiga align-video-work      # sync caches + build work manifest
python -m scripts.amiga verify-tournament-videos-work
python -m scripts.amiga promote-video-deploy
# simul includes video by default; use --skip-video to opt out
```

Work manifest: `data/amiga/work/tournament_videos.json` (gitignored). Oracle snapshot: `data/amiga/oracle/tournament_videos/`. Report: `data/amiga/modern/video-last.json`. Policy: [`docs/amiga-modern-video-policy.md`](../docs/amiga-modern-video-policy.md).

**PROMOTE-1 staging export (living ground):**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1
# one-time local PHP -> ko2amiga_work:
powershell -ExecutionPolicy Bypass -File scripts\promote_ko2amiga_work_local.ps1
```

Oracle archaeology only: `export_ko2amiga_db.ps1` (frozen `ko2amiga_db` after legacy `prove`).

**PULL-1a — staged prod → local repair shop:**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force
```

Staging generate (JSON) → download → replace `ko2amiga_work` → `staging-sync-last.json`. Add `-Simul` when sign-off needs it (not default). **Verified Jul 2026.** Runbook: [`docs/amiga-staging-handoff.md`](../../docs/amiga-staging-handoff.md).

**Sign-off / daily dev (legacy Access path — retiring):**

```powershell
# Nuclear reset + replay + verify (holy Amiga loop):
python -m scripts.amiga prove

# One-shot local build + staging export:
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1
```

`prove` = L1 `import-pristine` → L2 `import-prune` → L3 `import-witness` → L4 `apply-structure` → L5 `replay` → **tournament-video DB anchor sync** → verify suite (strict stack, slices 1–11 complete). Includes `verify-tournament-videos` — see [`tournament_videos/README.md`](tournament_videos/README.md).

**Strict stack policy (archived):** [`amiga-ground-stack.md`](../../docs/amiga-ground-stack.md) · [`amiga-ground-layers-policy.md`](../../docs/amiga-ground-layers-policy.md) · index [`archive/amiga-access-pipeline-index.md`](../../docs/archive/amiga-access-pipeline-index.md). **Live ops:** [`amiga-live-ops-platform.md`](../../docs/amiga-live-ops-platform.md).

**Modular pipeline (L0–L5):** DDL bundles `sql/ground|structure|derived` = L3|L4|L5. L1/L2 = SQL dumps under `data/amiga/exports/`.

```powershell
# L1 full mechanical mirror (all Access tables → SQL; not sign-off):
python -m scripts.amiga import-pristine
python -m scripts.amiga verify-pristine

# L2 hard prune (witness candidates; witness_player_identity from Rankings):
python -m scripts.amiga import-prune
python -m scripts.amiga verify-prune
# L1: data/amiga/exports/pristine/  →  L2: data/amiga/exports/pruned/
# L2 tables: Scores, Tournament players, witness_player_identity

# L3 witness (corrections + ground rows; input = L2_pruned.sql):
python -m scripts.amiga import-witness --recreate-ground
# Optional: --l2-dir data/amiga/exports/pruned
python -m scripts.amiga verify-witness
python -m scripts.amiga verify-l2-l3

# Country registry (L3 canonical names + site flags — docs/amiga-country-registry-policy.md):
python -m scripts.amiga build-country-registry
python -m scripts.amiga sync-country-flags
python -m scripts.amiga verify-country-registry

# L4 structure (disposition register dispatch; requires L3 first):
python -m scripts.amiga apply-structure --from-disposition
python -m scripts.amiga verify-structure

# Export packs (Mirror / Ground / Structure / Product):
python -m scripts.amiga export-pack all
python -m scripts.amiga verify-export-pack structure
# Output: data/amiga/exports/packs/{mirror|ground|structure|product}/
```

**Utilities (finalize, parity, staging export):**

```powershell
# Finalize one tournament (frozen Elo — see docs/amiga-tournament-finalize-rating-contract.md):
python -m scripts.amiga finalize-tournament --tournament-id=N
php site/public_html/amiga/ops/run_process_game.php finalize-tournament --tournament-id=N

# Oracle replay smoke on frozen ko2amiga_db (≥500 games):
python -m scripts.amiga replay --limit 500

# Tournament standings parity vs Access Tables (reference only):
python -m scripts.amiga standings-parity --tournament "London XXIII"
python -m scripts.amiga standings-parity --sweep

# PHP vs Python standings executor parity (SC-5; work DB + Laragon PHP):
python -m scripts.amiga verify-php-standings-parity --sample 5
python -m scripts.amiga verify-php-standings-parity --sweep
python -m scripts.amiga verify-rtb-standings-parity --sample 5
python -m scripts.amiga verify-rtb-standings-parity --sweep
python -m scripts.amiga backfill-standings-stage-id
python -m scripts.amiga verify-standings-stage-id --sweep

# SC-11 structured match extensions (ET/pens cols; work DB):
python -m scripts.amiga backfill-match-extensions
python -m scripts.amiga backfill-match-extensions --dry-run
python -m scripts.amiga verify-match-extensions

# L4b scoring contract catalog backfill (SC-6; work DB):
python -m scripts.amiga backfill-scoring-contracts --dry-run
python -m scripts.amiga backfill-scoring-contracts

# L4b scoring contract freeze on finalized tournaments (SC-7; catalog repair):
python -m scripts.amiga freeze-scoring-contracts --dry-run
python -m scripts.amiga freeze-scoring-contracts

# Schema inventory from Access (archaeology)
python scripts/amiga/discover_access_schema.py

# Staging export (forward — ko2amiga_work):
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1
```

Derived write policy: [`docs/amiga-derived-write-policy.md`](../../docs/amiga-derived-write-policy.md). Forward sign-off = **simul** on work.

Export writes part files + `ko2amiga_manifest.json` under `site/public_html/amiga/_import/` (plus optional full `ko2amiga_db.sql`). **38 parts** (Jun 2026-26): ground + structure through games/ratings chunks, then derived tail — snapshots, current, elo rank, matchup at-event, standings, catalog, matchup summary, generalstats, realm snapshots, community stats (+ snapshots + facts), world cup stats, player slice (+ at-event), **country slice (+ at-event)**. Audit: `python scripts/oneoff/audit_ko2amiga_export_tables.py`. Sync all of `_import/` via WinSCP.

**Staging refresh:** WinSCP sync `public_html/`, then browser import (verified Jun 2026, A2 schema):

- **Preview:** `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` — confirm manifest part count matches export
- **Apply:** same URL with `&apply=1&part=1` (auto-continues)
- **Local dry-run:** `http://ratingskickoff.test/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`

Script: `site/public_html/amiga/run_import_ko2amiga.php` · payload: `site/public_html/amiga/_import/`

Browser (local pages):

- `http://ratingskickoff.test/amiga/rating.php`
- `http://ratingskickoff.test/amiga/player/profile.php?id=1`
- `http://ratingskickoff.test/amiga/player/games.php?id=1`
- `http://ratingskickoff.test/amiga/game.php?id=1`
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
python -m scripts.amiga simul   # forward — catalog stats at each finalize
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

**Player universe derived tables** (snapshots, current, H2H, server records — contract [`amiga-player-universe-contract.md`](../../docs/amiga-player-universe-contract.md)):

```powershell
# Full stack is rebuilt by replay (after standings):
# snapshots + current → matchup_summary → generalstats → catalog_stats

# Forward sign-off on ko2amiga_work:
python -m scripts.amiga simul

# Oracle only (frozen ko2amiga_db):
# python -m scripts.amiga prove

# Verify only (read-only oracles — do not write derived tables):
python -m scripts.amiga verify-realm-snapshots
python -m scripts.amiga verify-community-stats
python -m scripts.amiga.verify_php_community_parity
# Optional dev gate when PHP must be present: $env:AMIGA_REQUIRE_PHP=1
python -m unittest scripts.amiga.test_community_registry_parity -v

# Derived write policy: docs/amiga-derived-write-policy.md
# Wrong derived state on work → simul again (no batch *-rebuild CLIs).

# Parity gates (player universe contract §8):
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
python -m scripts.amiga verify-event-snapshots
python -m scripts.amiga verify-rating-events

# WC medal spot-check vs Access (sample tournaments):
python -m scripts.amiga honours-parity-sample
```

PHP live path: `amiga_finalize_tournament` persists snapshots + current after standings commit.

Participation **roster = `amiga_games`**; finish from `participation_placement.py` / `includes/amiga_participation_placement.php` → `event_finish_position` per [`docs/amiga-tournament-honours-rules.md`](../docs/amiga-tournament-honours-rules.md) (tiers A–E; Tier E = `amiga_tournament_finish_override`). Phase ranks stay in `amiga_tournament_standings` only.

**Schema (Jun 2026):** **Forward:** DDL bundles `sql/ground/`, `sql/structure/`, `sql/derived/` via **simul** on **`ko2amiga_work`**. **Oracle:** legacy **`prove`** on frozen `ko2amiga_db`. Archived incremental `010`–`023`: [`sql/archive/incremental/README.md`](sql/archive/incremental/README.md).

**Event finish:** `019` (Tier E override table) is in the fresh bundle; `010` has `event_finish_position`.

**Standings scope:** `002`/`004` use `league`|`knockout` enum ( `003` retired from bundle).

**Tournament medals v2:** `010`/`011` end-state columns; policy [`docs/amiga-tournament-honours-rules.md`](../docs/amiga-tournament-honours-rules.md) v2.

**Event snapshots:** `024` in fresh bundle. Policy: [`docs/amiga-event-snapshot-policy.md`](../docs/amiga-event-snapshot-policy.md).

**Tournament structure (Jun 2026):** Disposition register **603/603** — [`amiga-tournament-structure-handlers.md`](../docs/amiga-tournament-structure-handlers.md). Review: [`disposition-REVIEW-STARTER`](../docs/orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md).

```powershell
python -m scripts.amiga tournament-structure generate-disposition-register
python -m scripts.amiga tournament-structure verify-disposition-register
python -m scripts.amiga tournament-structure preview-pure-knockout --tournament-id <id>
python -m scripts.amiga tournament-structure materialize-pure-knockout --tournament-id <id> [--replace]
python -m scripts.amiga tournament-structure materialize --tournament-id <id> [--dry-run] [--replace]
python -m scripts.amiga tournament-structure dematerialize --tournament-id <id>
python -m scripts.amiga tournament-structure verify-legacy --tournament-id <id> [--check-standings]
python -m scripts.amiga tournament-structure audit-inventory [--tier A|B|C|D] [--json] [--out path.json]
python -m scripts.amiga tournament-structure materialize-tier-a --dry-run
python -m scripts.amiga tournament-structure materialize-tier-a --apply --rebuild-standings --verify-sample 10
python -m scripts.amiga standings-rebuild --tournament-id <id>
```

Read surfaces: profile recent tournaments + top opponents; `/amiga/player/tournaments.php` (full history, `event_points`, Perf. rating — [`amiga-performance-rating.md`](../../docs/amiga-performance-rating.md)); `/amiga/hall-of-fame.php`; `/amiga/leaderboards/tournament-honours.php`.

**Participation columns (Jun 2026):** `event_points` + W-D-L from `amiga_games` rollup; phase league points only in `amiga_tournament_standings`. See contract §5.2.1.

**Tournament fixtures foundation** (internal ops only; public builder UI deferred):

```powershell
# Forward: simul on ko2amiga_work (not manual mysql ladder).
python -m scripts.amiga simul
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
# Browser create player (compose league): full name + nationality → Preview KOA name → confirm → draft chip;
# orphan delete on chip remove for live_ops zero-game players. See docs/amiga-player-create-policy.md.
# Requires amiga_players.player_source (ground bundle via prove).

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
# Prints fixture_id=…; writes running columns only (no amiga_games). Make official: finalize-tournament (promote + derive).

python -m scripts.amiga promote-running-tournament --tournament-id N --dry-run
python -m scripts.amiga verify-running-tournament-boundary

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
