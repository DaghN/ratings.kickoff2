# Agent handoff — KOA player naming foundation

## Goal

Add internal, KOA-aware player naming and creation tooling for `amiga_players` so operators can check name availability, get conservative KOA-style suggestions for newcomers, and create player rows via CLI with `--dry-run` support — without public UI or entrant auto-registration.

## Classification

`ground truth` / `internal ops`

## Files changed

- `scripts/amiga/player_names.py` — KOA suggestion helpers (`split_full_name`, `koa_abbreviation_candidates`, `suggest_koa_display_name`)
- `scripts/amiga/player_registry.py` — DB-backed check/suggest/create + CLI `main`
- `scripts/amiga/__main__.py` — `players` subcommand dispatcher
- `scripts/amiga/test_player_names.py` — unit tests for pure naming logic
- `scripts/amiga/README.md` — player naming CLI examples
- `docs/amiga-data-contract.md` — player identity / KOA naming ops section
- `docs/orchestration/amiga-tournament-orchestration-model.md` — note internal CLI shipped, public UX deferred
- `docs/orchestration/agent-handoffs/2026-06-07-008-koa-player-naming-foundation.md` — this handoff

## Behavior added

- `python -m scripts.amiga players check-name --name TEXT` normalizes input (trim, collapse whitespace, strip trailing period) and reports availability using casefolded `identity_key` plus exact `amiga_players.name` lookup. Exit `0` when available, `1` when colliding.
- `python -m scripts.amiga players suggest-name --full-name TEXT` produces conservative KOA abbreviations (`First S`, `First Su`, … through full surname) skipping identities already in the database. Short canonical-style two-token names are validated as-is. Does not merge with existing players.
- `python -m scripts.amiga players create --name TEXT [--country TEXT] [--dry-run]` inserts one `amiga_players` row (`display=1`). Refuses empty names and identity/exact collisions. Prints created `player_id` on success. Does not create `tournament_entrants`.

## Tests/checks run with exact commands and results

```powershell
git status --short --branch
# ## main...origin/main
#  M docs/amiga-data-contract.md
#  M docs/orchestration/amiga-tournament-orchestration-model.md
#  M scripts/amiga/README.md
#  M scripts/amiga/__main__.py
#  M scripts/amiga/player_names.py
# ?? scripts/amiga/player_registry.py
# ?? scripts/amiga/test_player_names.py

python -m compileall scripts/amiga
# exit 0

python -m unittest scripts.amiga.test_player_names -v
# Ran 6 tests — OK

python -m scripts.amiga players check-name --name "  Mark   B. "
# exit 1 — normalized_name=Mark B, conflict player_id=279 (Mark B, England)

python -m scripts.amiga players check-name --name "Totally Unique Zz Player"
# exit 0 — available: true

python -m scripts.amiga players suggest-name --full-name "Mark Bentley"
# exit 0 — suggested_name=Mark Be (Mark B already exists in local DB)

python -m scripts.amiga players create --name "Mark Be" --country "England" --dry-run
# exit 0 — DRY RUN row {name: Mark Be, country: England, display: 1}

python -m scripts.amiga players create --name "Mark B" --country "England" --dry-run
# exit 1 — ERROR: name conflict (exact) with player_id=279

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup
```

## Sample command output

```json
{
  "available": true,
  "input": "Mark Bentley",
  "normalized_input": "Mark Bentley",
  "normalized_suggestion": "Mark Be",
  "suggested_name": "Mark Be"
}
```

## Schema/data implications

- No schema changes.
- New ground-truth writes only via `players create` (non-dry-run). Import and replay behavior unchanged.
- `amiga_players.name` remains `utf8mb4_bin` unique; identity refusal uses normalized casefold in addition to exact name.

## Risks/limitations/not verified

- Multi-word surnames use the **last token** only for abbreviation (conservative; may differ from manual KOA edge cases).
- No browser ops UI for player creation; operators must use CLI.
- No automatic entrant registration after player create.
- No player merge/rename tooling.
- `players create` without `--dry-run` not exercised in this handoff (dry-run and collision refusal only).
- Staging export/import not refreshed.

## Commit hash and push target

- Implementation commit: `d04b57d`
- Push target: `origin/main`

## Recommended next steps

1. Wire `players suggest-name` / `create` into an internal entrant onboarding flow (create player, then add entrant).
2. Add browser ops parity for name check/suggest if operators need it without CLI.
3. Document multi-word surname policy with real KOA examples if operators report mismatches.
4. Public newcomer registration page (deferred product slice).
