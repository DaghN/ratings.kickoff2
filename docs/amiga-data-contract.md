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
| Tournament catalog | Names, dates, chrono, cup flag, country |
| Match results | Players, goals, tournament, phase |
| Player identity | Name, country (display fields only at import) |
| Provenance | `source_scores_id`, `source_id` where applicable |

Replay may **read** ground truth; it must not invent or overwrite canonical match facts. Replay game order follows § Chronology (`ORDER BY game_date ASC, id ASC`).

### 2. Derived truth

Computed from ground truth by chronological replay or per-game ops. **Always rebuildable** from canonical games in order.

| Fact | Notes |
|------|--------|
| Per-game Elo | Ratings before/after, adjustments, outcome flags |
| Player career stats | W/D/L, goals, streaks, peaks, opponent networks |
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
| `tournaments` | Ground | Import / submission |
| `amiga_players` | Ground | Import / submission |
| `amiga_games` | Ground | Import / submission |
| `amiga_game_ratings` | Derived | Replay (`scripts/amiga/replay.py`) or PHP `amiga_process_completed_game` / `replay-to` |
| `amiga_player_stats` | Derived | Replay or PHP `amiga_process_completed_game` / `replay-to` |
| `amiga_tournament_standings` | Derived | Replay (`scripts/amiga/replay.py`) or PHP `amiga_process_completed_game` / `replay-to` |
| `reference_*` (optional) | Reference | Parity tooling only |

DDL: [`scripts/amiga/sql/001_core.sql`](../scripts/amiga/sql/001_core.sql), Track B migration [`002_tournament_standings.sql`](../scripts/amiga/sql/002_tournament_standings.sql). Website read path: [`includes/amiga_db.php`](../site/public_html/includes/amiga_db.php), tournament pages [`includes/amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php).

### Tournament standings rules (Track B v1)

- **Source:** `amiga_games` grouped per `tournament_id`, ordered by `source_scores_id` within tournament.
- **Points:** 3 per win, 1 per draw, 0 per loss (W×3 + D×1). Tie-break: goal difference, goals scored.
- **Scopes:** `scope_type` + `scope_key` from phase labels (`scripts/amiga/tournament_phases.py`). Phase NULL → single `overall` table. Group labels → per-group league tables. Knockout phases (`Semi Finals`, `Places 9-16`, …) → `knockout` scope per **player pair** (`scope_key` = `{phase}|{id}-{id}`), two rows per tie.
- **Goals:** Regulation `goals_a` / `goals_b` only for league/group tables (Elo uses the same). `extra` column stores Access `Scores.Extra` (ET/penalties text); does not affect Elo.
- **Knockout tie winner** (per pair scope, all legs in that phase between the two players): (1) higher aggregate goal difference; (2) if tied, higher aggregate goals scored; (3) if still tied, `parse_standings_winner` on any leg with non-empty `extra` (penalties); (4) if unresolved, UI shows “Tie unresolved” and falls back to derived `position` order. Same rules in `scripts/amiga/tournament_standings.py` (`_knockout_positions`) and `includes/amiga_tournament_lib.php` (`amiga_tournament_knockout_resolve_winner`). Website knockout view lists per-leg fixtures via `amiga_tournament_knockout_fixture_games`.
- **Parity:** Access `Tables` / `World Cup * Tables` are reference only — `python -m scripts.amiga standings-parity`.
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
| Reference parity tables / diffs | **Partial** (`standings-parity` CLI vs Access ODBC) |

---

## Agent policy

- **Import:** ground truth only — see `scripts/amiga/import_access.py` and [`amiga-import-layer.md`](amiga-import-layer.md). Corrections to legacy Access belong in the import layer (`import_corrections.py`, `player_names.py`, `tournament_names.py`), not in edited `koatd.mdb`. Each import writes `data/amiga/exports/import_manifest.json`. A full import **truncates** `amiga_game_ratings` and `amiga_player_stats` (FK order) but does not repopulate them. **`import` alone leaves the website read path empty** until replay. Use `python -m scripts.amiga run` for import + replay, or always follow `import` with `replay`.
- **Replay:** derived truth only — clears derived rows, never truncates canonical game rows
- **Incremental post-game (live):** `php site/public_html/amiga/ops/run_process_game.php process-one --game-id=N` — `ko2amiga_db` only; idempotent (`already_processed` if rating row exists); **append-only** (game must be chronologically last; errors `not_append_only` / `derived_gap` otherwise). Parity smoke: full `replay` → `replay --limit (N-1)` → PHP `process-one` on last id → compare rating + both players' stats to full replay.
- **Simul (replay-to):** `zero-derived` then `replay-to [--limit N] [--until-game-id G]` — walks contract chronology (`game_date ASC, id ASC`); sim chronology = first unrated game in order (`derived_gap` if hole). Idempotent re-run skips `already_processed`. **v1 parity gate:** `--limit 500` vs `python -m scripts.amiga replay --limit 500` (counts + spot-check; not full 27k PHP sim).
- **New derived tables:** add row to § Table register + post-game rule before implementing
- **Website:** extend `includes/amiga_*.php`, not online `k2_*` game loaders
