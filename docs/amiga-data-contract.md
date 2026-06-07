# Amiga data contract

**Purpose:** One canonical description of how `ko2amiga_db` is structured — ground truth vs derived truth vs reference parity — and how import, replay, and the website read path must behave.

**Database:** `ko2amiga_db` only. Separate from online `kooldb*` / `ko2unity*`. No cross-realm player linking.

**Online analogue:** [`website-data-contract.md`](website-data-contract.md) — same *philosophy* (replay = live simulation), much smaller scope.

---

## Authority map

| Topic | Document |
|--------|----------|
| Access inventory, quirks, chronology | [`amiga-schema-discovery.md`](amiga-schema-discovery.md) |
| **Import layer** (archival → ground truth) | [`amiga-import-layer.md`](amiga-import-layer.md) |
| Chronology fix | [`amiga-chronology-fix-plan.md`](amiga-chronology-fix-plan.md) |
| Profile / games UI (v0) | [`amiga-profile-v0.md`](amiga-profile-v0.md) |
| **Realm vision & roadmap** (inventory, hub IA, phases) | [`amiga-realm-vision.md`](amiga-realm-vision.md) |
| **Tournament format system** (legacy phases → templates/fixtures vision) | [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) · handoff [`amiga-tournament-format-handoff-prompt.md`](amiga-tournament-format-handoff-prompt.md) |
| Staging deploy | [`amiga-staging-handoff.md`](amiga-staging-handoff.md) |
| Import + replay commands | [`scripts/amiga/README.md`](../scripts/amiga/README.md) |
| DDL (current) | [`scripts/amiga/sql/001_core.sql`](../scripts/amiga/sql/001_core.sql) |

This document owns **layer definitions**, **table register**, **post-game/replay rules**, and **read-path policy**. It does not duplicate Access discovery or page mockups.

---

## Data layers

Archival Access (`koatd.mdb`) is **input**, not website ground truth. Import applies documented transforms (see [`amiga-import-layer.md`](amiga-import-layer.md)) and writes audit output to `data/amiga/exports/import_manifest.json`.

### 1. Ground truth

Canonical facts in MySQL after **import** or **future live submission** — never written by replay.

| Fact | Notes |
|------|--------|
| Tournament catalog | Names, dates, chrono, verbatim Access cup flag, country, format template + league/cup flags |
| Match results | Players, goals, tournament, phase |
| Player identity | Name, country (display fields only at import) |
| Provenance | `source_scores_id`, `source_id` where applicable |

Replay may **read** ground truth; it must not invent or overwrite canonical match facts. Replay game order follows § Chronology (`ORDER BY game_date ASC, id ASC`).

### 2. Derived truth

Computed from ground truth by chronological replay or per-game ops. **Always rebuildable** from canonical games in order.

| Fact | Notes |
|------|--------|
| Per-game Elo | Ratings before/after, adjustments, outcome flags |
| Player career stats | W/D/L, goals, peaks, opponent networks — **not match streaks** (see § Match streaks; columns exist but are not product truth) |
| Tournament standings | Points tables, group tables — from games via `scripts/amiga/tournament_standings.py` |
| Future aggregates | H2H summaries, period activity, etc. — when needed |

**Rule:** After one new canonical game, derived tables must match what a full replay from empty would produce.

### 3. Reference truth (parity only)

Legacy Access precomputes. **Neither ground nor derived.** Used to answer: “Did our engine reproduce what the old system claimed?”

| Source (Access) | Use |
|-----------------|-----|
| `Tables`, `World Cup * Tables`, … | Tournament standing parity |
| `Rankings` monthly grid | Elo history parity (optional) |
| `added_players` | Career-total spot checks |

Reference data is **never** written by post-game or replay. Store in `data/amiga/exports/` or optional `reference_*` tables loaded by one-off tooling — not in the website hot path.

---

## Chronology (ground truth)

Access has no per-game timestamp. **Import sort key** (walk only — not used at read time):

1. `tournaments.event_date` ASC
2. `tournaments.chrono` ASC (same-day tie-break, e.g. cup/main pairs)
3. `source_scores_id` ASC within the same tournament

**Synthetic `game_date` on each game row:**

- Base = parent tournament `event_date` at UTC midnight
- **Running second counter per calendar day** across the sorted walk (does not reset when tournament changes on the same day)
- After import: `id` (insert order) and `game_date` are the canonical sequence

**Read path** (replay, API, ops, charts):

```sql
ORDER BY g.game_date ASC, g.id ASC
```

`tournaments.chrono` remains imported metadata for import tie-breaks and catalog display — not for replay or API game walks. Verify: `python -m scripts.amiga verify-chronology`. Spec history: [`amiga-chronology-fix-plan.md`](amiga-chronology-fix-plan.md).

### Match streaks — off the table (product policy)

**Do not surface match streaks anywhere in the Amiga realm** — leaderboard wing, Hall of Fame rows, profile panels, or APIs.

**Why:** Access has no per-game timestamp. Import assigns a **synthetic** within-day order (`running second counter` on `game_date`; tie-break `id` / `source_scores_id` within tournament). That sequence is correct enough for **Elo**, cumulative **W/D/L**, goals, peaks, and opponent networks, but it is **not** the real order matches were played on tournament day. Consecutive-win / draw / loss streaks depend on that unknown order, so any `Longest*` or current `*Streak` value is **arbitrary**, not a historical fact.

**What agents should know:**

| Topic | Policy |
|--------|--------|
| **Website / hub** | **Skip** streaks leaderboard wing; **omit** HoF longest-streak records; **no** profile “moments” for streaks |
| **Calendar play streaks** (`player_play_streaks`, day/week) | Also **skip** — offline batch play ≠ UTC daily habit |
| **`amiga_player_stats` columns** | `WinningStreak`, `LongestWinningStreak`, `LongestNonLossStreak`, etc. still exist — shared `PlayerState` / replay engine writes them for schema parity with online. Treat as **non-authoritative for Amiga product**; do not read them in PHP templates or new features |
| **Removing columns / stopping replay writes** | Not required for v1; product simply never displays them. A future cleanup could zero or drop streak writers if desired |

Roadmap detail: [`amiga-realm-vision.md`](amiga-realm-vision.md) § Leaderboard wings (Streaks), § Hall of Fame.

---

## Post-game / replay

**Target architecture** (Amiga-owned ops, inspired by online — not shared online scripts):

```
canonical game in  →  ProcessCompletedGame (Amiga)  →  derived updates
full history      →  chronological replay            →  same derived state
```

- **Elo:** start 1600, K=32 (online sandbox constants)
- **Rating authority:** replay from `Scores` only — never display legacy Access `Rankings`
- **Connection:** `SET time_zone = '+00:00'` before period/date logic

**Current implementation (Phase A2 + Track B):** `python -m scripts.amiga import` (ground only) + `replay` via `scripts/amiga/replay.py` (Elo + tournament standings batch repair). **Incremental post-game:** `amiga_process_completed_game()` in `site/public_html/amiga/ops/` — live `process-one` (append-only last game) or sim `replay-to` (next unrated in contract order); updates Elo, player stats, and tournament standings in one transaction. Python `replay` remains the batch repair oracle (`rebuild_all_standings`).

**Simul pipeline (PHP, mirrors online Mode A):**

```bash
# Day zero — derived only
php site/public_html/amiga/ops/run_process_game.php zero-derived

# Parity gate (500 games — v1 sign-off)
python -m scripts.amiga replay --limit 500          # oracle
python -m scripts.amiga verify-chronology           # 0 backward game_date
python -m scripts.amiga audit-catalog-dates         # Access catalog inversions covered
php site/public_html/amiga/ops/run_process_game.php zero-derived
php site/public_html/amiga/ops/run_process_game.php replay-to --limit 500
php site/public_html/amiga/ops/run_process_game.php verify   # 500 ratings, standings spot-checks, no derived_gap

# Optional: full derived rebuild (batch oracle — ~10s Python; PHP replay-to unbounded is slow)
python -m scripts.amiga replay
```

**Parity rule:** after `replay-to --limit 500`, row counts and spot-checks must match `python -m scripts.amiga replay --limit 500` (500 `amiga_game_ratings`, same `amiga_player_stats` count, `amiga_tournament_standings` for walked tournaments, last-game ratings align to 6 dp). Full-history PHP simul is slow — use Python `replay` for batch repair. Repair path for gaps: `zero-derived` then `replay-to`.

---

## Read path (website)

Pages read through **Amiga PHP helpers** in `site/public_html/includes/amiga_*.php` — not raw storage tables in templates.

| Helper | Role |
|--------|------|
| `amiga_player_load.php` | Profile hero + career strip |
| `amiga_db.php` | Join ground + derived for read queries |
| `amiga_player_games_lib.php` | Games list filters/pagination |
| `api/player_rating_history.php?realm=amiga` | Rating chart JSON |

**Do not** add SQL views named `ratedresults` / `playertable` to fake the old shape. Join logic lives in `amiga_db.php` only.

---

## Table register

### Current (Phase A2 — split)

| Table | Layer | Writer |
|-------|-------|--------|
| `tournament_format_templates` | Ground/config | Import seed / future admin-managed templates |
| `tournaments` | Ground | Import / submission |
| `tournament_entrants` | Ground | Future live tournament ops / fixture tooling |
| `tournament_stages` | Ground | Future live tournament ops / fixture tooling |
| `tournament_stage_players` | Ground | Future live tournament ops / fixture tooling |
| `tournament_fixtures` | Ground | Future live tournament ops / fixture tooling |
| `amiga_players` | Ground | Import / submission |
| `amiga_games` | Ground | Import / submission |
| `amiga_game_ratings` | Derived | Replay (`scripts/amiga/replay.py`) or PHP `amiga_process_completed_game` / `replay-to` |
| `amiga_player_stats` | Derived | Replay or PHP `amiga_process_completed_game` / `replay-to` |
| `amiga_tournament_standings` | Derived | Replay (`scripts/amiga/replay.py`) or PHP `amiga_process_completed_game` / `replay-to` |
| `amiga_tournament_catalog_stats` | Derived | Replay / `catalog-stats-rebuild` (batch); PHP `amiga_ops_catalog_stats_refresh_tournament` per touched tournament on post-game |
| `reference_*` (optional) | Reference | Parity tooling only |

DDL: [`scripts/amiga/sql/001_core.sql`](../scripts/amiga/sql/001_core.sql), Track B [`002_tournament_standings.sql`](../scripts/amiga/sql/002_tournament_standings.sql), index aggregates [`004_tournament_catalog_stats.sql`](../scripts/amiga/sql/004_tournament_catalog_stats.sql), format foundation [`005_tournament_formats.sql`](../scripts/amiga/sql/005_tournament_formats.sql), fixture foundation [`006_tournament_fixtures.sql`](../scripts/amiga/sql/006_tournament_fixtures.sql), entrant foundation [`007_tournament_entrants.sql`](../scripts/amiga/sql/007_tournament_entrants.sql). Website read path: [`includes/amiga_db.php`](../site/public_html/includes/amiga_db.php), tournament pages [`includes/amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php).

### Tournament format metadata

- `tournament_format_templates` is canonical format/config metadata in `ko2amiga_db`, not an Access import table. Import seeds stable template slugs, including `legacy_inferred` for historical events and starter templates for future live tournament creation.
- `tournaments.format_template_id` points to the selected template. Legacy imports default to `legacy_inferred`; future live events may use concrete templates such as `kitchen_marathon`, `group_knockout`, or `world_cup_class`.
- `tournaments.has_league` and `tournaments.has_cup` are **non-exclusive** ground catalog flags computed at import from canonical game phase labels plus the verbatim Access `is_cup` flag. A tournament with games must have at least one of these flags true; verify with `python -m scripts.amiga verify-tournament-formats`.
- `tournaments.is_cup` remains the raw imported Access `Cup?` value. Do not use it as the product definition of cup play or honours eligibility.

### Tournament entrants, stages, and fixtures

- `tournament_entrants` is **tournament-level registration ground truth** for future live events: one row per player per tournament with seed, status (`registered`, `withdrawn`, `replaced`), and optional admin `note`. Player display names remain canonical in `amiga_players`; `display_name_snapshot` is deferred to avoid drift on rename. Legacy Access imports leave entrants empty; internal builders populate entrants before stage players.
- Verify entrant integrity with `python -m scripts.amiga fixtures verify-entrants` (stage players and fixture participants must be active `registered` entrants). List with `python -m scripts.amiga fixtures list-entrants --tournament-id N`.
- `python -m scripts.amiga fixtures backfill-entrants` conservatively inserts missing `registered` entrants for tournaments generated by approved fixture tooling (`format_overrides.generated_by` prefixes `scripts.amiga.tournament_builder` or `site.public_html.amiga.ops.fixtures`). It preserves existing entrant rows (including `withdrawn` / `replaced`), does not touch imported Access tournaments, and supports `--tournament-id N` and `--dry-run`.
- `tournament_stages` and `tournament_fixtures` are **ground truth for future live tournaments**. They are not derived from standings, and legacy Access imports leave them empty by default.
- `amiga_games.fixture_id` is nullable. Fixture-backed games should point at `tournament_fixtures.id`; imported legacy games keep `fixture_id = NULL` and continue through phase-parser fallback.
- Fixture attachment must preserve canonical game facts: tournament ids must match, and fixture players must match the game players when both fixture players are known. Verify integrity with `python -m scripts.amiga fixtures verify`.
- Standings scope resolution prefers fixture metadata when `amiga_games.fixture_id` is present: `league` stages feed overall/group tables, `group` stages feed group tables, and `knockout` / `placement` stages feed per-pair knockout scopes. If `fixture_id` is NULL, `scripts/amiga/tournament_phases.py` remains the legacy parser.
- Public tournament-builder UI is deferred. Until then, use internal ops/tooling only (`scripts/amiga/tournament_builder.py` and `scripts/amiga/tournament_fixtures.py`) and keep website reads behind existing Amiga helpers.
- `python -m scripts.amiga build-tournament create-kitchen-marathon` is the first internal builder: it creates one new `tournaments` row from the `kitchen_marathon` template, one `overall` league stage, stage players, and scheduled round-robin fixtures. It does **not** create `amiga_games`; use fixture result entry for that.
- `python -m scripts.amiga build-tournament create-group-knockout` is a minimal starter for group round robins plus a final placeholder. Advancing winners into knockout fixtures remains an explicit manual ops step until the promotion policy is modelled.
- `python -m scripts.amiga build-tournament smoke-fixture-result` creates a tiny generated tournament, records one fixture result, verifies the generated structure, and rolls back. Use it as the local end-to-end guard for the live fixture path.
- `python -m scripts.amiga fixtures record-result` is the first internal fixture-backed result entry path. It inserts one canonical `amiga_games` row for a scheduled fixture, marks the fixture `played`, and leaves derived writes to `replay` or PHP `process-one`.
- `python -m scripts.amiga fixtures list|detail` are read-only schedule inspection commands. `fixtures set-players` assigns participants to scheduled placeholder fixtures only when both players already belong to the tournament and no game is attached.
- `/amiga/ops/fixtures.php` is the matching password-gated browser for internal ops; it may create kitchen-marathon tournaments, assign scheduled placeholder fixture players, and record scheduled fixture-backed results, but remains internal tooling rather than public UI.
- `python -m scripts.amiga fixtures cleanup-generated` may delete only unplayed tournaments generated by approved fixture tooling; imported Access tournaments and generated tournaments with games are intentionally refused.
- Fixture-entered games use reserved synthetic `source_scores_id >= 1000000000` so they never collide with Access `Scores.ID`. They must be chronologically append-only: default `game_date` is the current max `game_date` + 1 second, and explicit `--played-at` values must be later than the current last game.

**Tournament index (`/amiga/tournaments.php`):** read **`amiga_tournament_catalog_stats`** only — one row per tournament (`game_count`, `standing_players`, `group_scopes`, `knockout_ties`). Do **not** aggregate `amiga_games` × `amiga_tournament_standings` at page load (cartesian explosion). Rebuild: `python -m scripts.amiga catalog-stats-rebuild` or full `replay`.

### Tournament standings rules (Track B v1)

- **Source:** `amiga_games` grouped per `tournament_id`, ordered by `source_scores_id` within tournament.
- **Points:** 3 per win, 1 per draw, 0 per loss (W×3 + D×1). Tie-break: goal difference, goals scored.
- **Scopes:** `scope_type` + `scope_key` from fixture stage metadata when `amiga_games.fixture_id` is present; otherwise from phase labels (`scripts/amiga/tournament_phases.py`). Phase NULL → single `overall` table. Group labels → per-group league tables. Knockout phases (`Semi Finals`, `Places 9-16`, …) → `knockout` scope per **player pair** (`scope_key` = `{phase}|{id}-{id}`), two rows per tie.
- **Goals:** Regulation `goals_a` / `goals_b` only for league/group tables (Elo uses the same). `extra` column stores Access `Scores.Extra` (ET/penalties text); does not affect Elo.
- **Knockout tie winner** (per pair scope, all legs in that phase between the two players): (1) higher aggregate goal difference; (2) if tied, higher aggregate goals scored; (3) if still tied, `parse_standings_winner` on any leg with non-empty `extra` (penalties); (4) if unresolved, UI shows “Tie unresolved” and falls back to derived `position` order. Same rules in `scripts/amiga/tournament_standings.py` (`_knockout_positions`) and `includes/amiga_tournament_lib.php` (`amiga_tournament_knockout_resolve_winner`). Website knockout view lists per-leg fixtures via `amiga_tournament_knockout_fixture_games`.
- **Parity:** Access `Tables` / `World Cup * Tables` are reference only — `python -m scripts.amiga standings-parity` (spot check) or `standings-parity --sweep` (full report → `data/amiga/exports/standings_parity_report.json`). Player names normalized via `normalize_display_name` at compare time; Silver/Bronze cup groups map to Access `Group A`…`H` labels.
- **PHP incremental:** per-game rebuild from rated `amiga_games` for the touched tournament (`amiga_post_game_standings.php`); knockout positions use aggregate GD/GF + `extra` via `amiga_parse_standings_winner`.
- **Future gaps:** full knockout bracket advancement; cross-stage promotion (Tier 4).

---

## Migration status

| Item | Status |
|------|--------|
| Access import → `ko2amiga_db` | **Done** (A1) |
| Elo replay, leaderboard, profile, games | **Done** (A1) |
| This contract (layer intent) | **Done** |
| Schema split (`amiga_games` / …) | **Done** (A2) |
| Staging multi-part browser import | **Done** (Jun 2026) |
| Amiga `ProcessCompletedGame` ops | **Done** (v1 CLI — `process-one` append-only) |
| Amiga ops simul (`zero-derived` + `replay-to`) | **Done** (v1 — 500-game parity gate vs Python `replay --limit 500`) |
| Tournament standings (derived) | **Done** (Track B — league + group + knockout; PHP incremental post-game) |
| Reference parity tables / diffs | **Done** (`standings-parity --sweep` vs Access ODBC; 0 engine FAILs Jun 2026) |
| Amiga hub nav (v0) | **Done** — `includes/amiga_hub_nav.php` (Ladder · Tournaments · Hall of Fame); HoF stub `/amiga/hall-of-fame.php` |
| Tournament format foundation | **In progress** — `tournament_format_templates` + non-exclusive `tournaments.has_league` / `has_cup` import flags |
| Stage/fixture foundation | **In progress** — ground tables + internal CLI; no public builder UI yet |
| Tournament entrants foundation | **In progress** — `tournament_entrants` + builder population + verify CLI |
| Internal tournament builder | **Started** — `kitchen_marathon` round-robin generator only; no result-entry UI yet |

---

## Known parity exceptions (reference only)

Full sweep (Jun 2026): **684 PASS**, **116 SKIP** (no Access reference or no derived rows), **26 EXCEPTION** (documented below), **0 FAIL** (engine matches game aggregation). Report: `data/amiga/exports/standings_parity_report.json`.

| Reason | Count | Meaning |
|--------|------:|---------|
| `ref_stale_tables` | 24 | Access `Tables` / `World Cup * Tables` disagree with aggregating `Scores` — legacy snapshot not updated after late result entry. Derived engine matches ground-truth games. |
| `ref_alias_merge` | 1 | **Gloucester III** — import merges `Gloucester III Team` games into parent (`tournament_names.py`); Access overall table counts only the parent label. |
| `mixed_overall_league_only` | 1 | **Athens LXXXV** — overall derived table = null-phase round-robin only; Access `Tables` includes knockout legs in the same overall row. |

Do not “fix” these by importing Access snapshots as truth. Re-run: `python -m scripts.amiga standings-parity --sweep`.

---

## Agent policy

- **Import:** ground truth only — see `scripts/amiga/import_access.py` and [`amiga-import-layer.md`](amiga-import-layer.md). Corrections to legacy Access belong in the import layer (`import_corrections.py`, `player_names.py`, `tournament_names.py`, `tournament_format.py`), not in edited `koatd.mdb`. Each import writes `data/amiga/exports/import_manifest.json`. A full import **truncates** `amiga_game_ratings` and `amiga_player_stats` (FK order) but does not repopulate them. **`import` alone leaves the website read path empty** until replay. Use `python -m scripts.amiga run` for import + replay, or always follow `import` with `replay`.
- **Replay:** derived truth only — clears derived rows, never truncates canonical game rows
- **Incremental post-game (live):** `php site/public_html/amiga/ops/run_process_game.php process-one --game-id=N` — `ko2amiga_db` only; idempotent (`already_processed` if rating row exists); **append-only** (game must be chronologically last; errors `not_append_only` / `derived_gap` otherwise). Parity smoke: full `replay` → `replay --limit (N-1)` → PHP `process-one` on last id → compare rating + both players' stats to full replay.
- **Simul (replay-to):** `zero-derived` then `replay-to [--limit N] [--until-game-id G]` — walks contract chronology (`game_date ASC, id ASC`); sim chronology = first unrated game in order (`derived_gap` if hole). Idempotent re-run skips `already_processed`. **v1 parity gate:** `--limit 500` vs `python -m scripts.amiga replay --limit 500` (counts + spot-check; not full 27k PHP sim).
- **New derived tables:** add row to § Table register + post-game rule before implementing
- **Website:** extend `includes/amiga_*.php`, not online `k2_*` game loaders
- **Match streaks:** never ship UI or APIs that display `*Streak` / `Longest*Streak` on Amiga — see § Match streaks
