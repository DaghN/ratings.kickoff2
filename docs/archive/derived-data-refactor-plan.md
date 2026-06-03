# Derived data refactor plan (archived)

**Executed May 2026.** Durable outputs:

- [`../website-data-contract.md`](../website-data-contract.md) — behavior authority
- [`../../scripts/rebuild_website_derived_data_local.ps1`](../../scripts/rebuild_website_derived_data_local.ps1) — one-command local rebuild

This file is kept for history only. Do not treat open decisions or snippet-era notes below as current policy.

---

# Derived data refactor plan

**Purpose:** Simplify the project-owned database truth around website aggregates. The target is one canonical data contract, one rebuild entrypoint, and status-only deployment registers.

This plan is scaffolding for the refactor. Do not treat it as the final contract.

**Status:** Executed locally in May 2026. Durable outputs are `docs/website-data-contract.md` and `scripts/rebuild_website_derived_data_local.ps1`.

---

## Goal

Given a ground-truth ladder database, this repo should be able to produce a website-ready database in one routine, validated command.

The long-term mental model should be:

1. Ground truth: `ratedresults` and core live ladder tables.
2. Project-owned derived truth: aggregate/index tables the website reads.
3. Post-game behavior: the incremental updates needed after one new rated game so live production stays equivalent to a full rebuild.

Staging/prod coordination should sit on top of this model. It should not be the source of truth for what the data means.

---

## Non-goals

- Do not change data semantics during this refactor.
- Do not add new aggregate tables.
- Do not delete schema migrations.
- Do not merge all SQL into one giant file.
- Do not remove C++ snippets until Steve accepts a replacement handoff style.

---

## Target structure

### Canonical contract

Create one durable contract doc:

`docs/website-data-contract.md`

It should own, for every project-owned table or derived field:

- purpose and website readers
- source truth
- table grain and primary key
- column meanings
- UTC/timezone rule
- full rebuild rule
- post-game incremental rule
- dependencies
- parity checks
- lifecycle state: active, legacy, fallback, or superseded

### Rebuild entrypoint

Create one operator command:

`scripts/rebuild_website_derived_data_local.ps1`

It should:

- guard against accidental non-local DB use
- print DB identity before destructive work
- apply/assume schema prerequisites explicitly
- run modular SQL rebuild files in dependency order
- stop on failure
- print row counts and parity checks
- verify UTC period boundaries

Keep modular SQL files under `scripts/ladder/sql/` as implementation units.

### Status-only registers

Keep registers, but demote them to status ledgers:

- `docs/coordination/schema-register.md`
- `docs/coordination/replay-register.md`
- `docs/coordination/post-game-register.md`
- `docs/coordination/feature-log.md`

They should answer: what exists, what ID it has, what environment has it, and what remains pending.

They should link to `docs/website-data-contract.md` for behavior and computation rules.

### Post-game behavior

The canonical contract should include one post-game section:

> After one new rated game, these derived rows must change this way.

Individual `PG-*.md` snippet files can remain as implementation aids. They should no longer be treated as the conceptual source of truth.

---

## Cleanup map

| Current file / area | Refactor treatment |
|---------------------|-------------------|
| `docs/stored-truth-expansion.md` | Merged useful content into `website-data-contract.md`; replaced with short redirect |
| `docs/player-period-games.md` | Merged useful content into `website-data-contract.md`; replaced with short redirect |
| `docs/coordination/replay-register.md` | Keep as run/status log; remove or shorten duplicated behavior rules |
| `docs/coordination/schema-register.md` | Keep as migration status; link table behavior to contract |
| `docs/coordination/post-game-register.md` | Keep PG status; link behavior to contract |
| `docs/coordination/cpp-snippets/` | **Retired May 2026** — except records → `records-post-game-exception.md` |
| `scripts/rebuild_player_period_games_local.ps1` | Superseded by one rebuild entrypoint; kept as compatibility wrapper |
| `scripts/rebuild_player_monthly_league_local.ps1` | **Removed** Jun 2026 (legacy monthly table dropped) |
| `scripts/ladder/sql/server_daily_activity_rebuild_raw.sql` | Mark as emergency fallback, not normal pipeline |
| `player_monthly_league` | **Dropped** Jun 2026 (SCH-017); month via `player_period_league` only |

---

## Proposed rebuild order

1. `player_period_games`
2. `player_peak_period_games`
3. `server_daily_activity`
4. `player_period_league`
5. `player_milestones`
6. `player_matchup_summary`
7. `server_period_game_totals`
8. `server_period_matchups`

Legacy compatibility:

- ~~`player_monthly_league`~~ removed; month league is `player_period_league` only.

---

## Validation checklist

The new rebuild command must prove:

- MySQL session is pinned with `SET time_zone = '+00:00'`.
- `COUNT(*) FROM ratedresults` equals:
  - `SUM(games) / 2` from `player_period_games` day rows
  - `SUM(played) / 2` from `player_period_league` day rows
  - `SUM(rated_games)` from `server_daily_activity`
  - `SUM(rated_games)` from `server_period_game_totals` day rows
  - `SUM(games)` from `server_period_matchups` day rows
- `player_milestones.established_20` count matches `playertable.NumberGames >= 20`.
- Recent `server_period_matchups` month rows match raw UTC distinct-pair counts.
- Key PHP APIs keep the same JSON shape.

---

## Execution phases

1. Draft `docs/website-data-contract.md` from existing docs, schema, SQL rebuilds, and PG snippets.
2. Replace old explanatory docs with redirects/legacy notes.
3. Add the one rebuild entrypoint.
4. Run the rebuild entrypoint locally.
5. Update registers to point to the contract and keep only status/run-log details.
6. Update `PROJECT_MEMORY.md` with the new durable model.

---

## Open decisions (historical)

- Keep the redirect docs long-term, or move them to archive after links settle?
- ~~Should the first one-command rebuild include `player_monthly_league`?~~ Resolved: table dropped Jun 2026.
- Should the post-game contract replace most individual PG snippet prose now, or only become the authority while snippets remain unchanged?
