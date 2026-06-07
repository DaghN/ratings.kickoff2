# Agent handoff — tournament lifecycle foundation

## Goal

Add conservative tournament lifecycle ground truth to `ko2amiga_db` and internal tooling: status column, transition CLI, import/builder defaults, result-entry guardrails, and verification.

## Classification

`foundation` / `internal ops`

## Files changed

- `scripts/amiga/sql/008_tournament_lifecycle.sql` — `lifecycle_status`, `started_at`, `completed_at` + backfill rules
- `scripts/amiga/import_access.py` — apply 008 in schema order; import tournaments as `completed`
- `scripts/amiga/tournament_fixtures.py` — lifecycle helpers, `set-tournament-status`, `verify-lifecycle`, result-entry guard
- `scripts/amiga/tournament_builder.py` — generated tournaments default `draft`; smoke transitions to `running`
- `site/public_html/amiga/ops/fixtures.php` — kitchen create sets `draft`; result entry requires `running`
- `docs/amiga-data-contract.md` — lifecycle section + migration row
- `scripts/amiga/README.md` — lifecycle migration and CLI examples
- `docs/orchestration/agent-handoffs/2026-06-07-005-tournament-lifecycle-foundation.md` — this handoff

## Schema/data implications

- New columns on `tournaments`: `lifecycle_status` enum (`draft`, `registration`, `ready`, `running`, `completed`, `archived`, `void`), `started_at`, `completed_at`.
- **Backfill (008):** imported rows (`source_id IS NOT NULL`) → `completed` with `completed_at` from `event_date`; generated rows with games → `running`; generated rows with all fixtures played → `completed`; others remain `draft`.
- Full re-import writes `completed` directly (no reliance on backfill).
- Staging export will pick up new columns on `tournaments` automatically.

## Behavior added or changed

- **Defaults:** Access import → `completed`. Python builders + browser kitchen create → `draft` (conservative: ops must explicitly move to `running` before result entry).
- **Result entry:** allowed only when `lifecycle_status = running` (not `ready`). Refused for `completed`, `archived`, `void`.
- **`fixtures set-tournament-status`:** validates status; refuses imported historical transitions away from `completed`/`archived` without `--force`; refuses `completed` when scheduled fixtures remain without `--force`; sets `started_at` / `completed_at` on first transition to `running` / `completed`|`archived`.
- **`fixtures verify-lifecycle`:** imported must be `completed` or `archived`; generated with games must not stay in `draft`/`registration`/`ready`; `completed`/`archived` must not have scheduled fixtures.

## Tests/checks run with exact commands and results

```powershell
python -m py_compile scripts/amiga/tournament_fixtures.py scripts/amiga/tournament_builder.py scripts/amiga/import_access.py
# exit 0

# Applied 008 via Python (mysql CLI not on PATH):
python -c "from pathlib import Path; ... apply 008_tournament_lifecycle.sql ..."
# OK: 008_tournament_lifecycle.sql applied

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l site/public_html/amiga/ops/fixtures.php
# No syntax errors detected

python -m scripts.amiga fixtures verify
# OK: fixture integrity checks passed

python -m scripts.amiga fixtures verify-entrants
# OK: tournament entrant integrity checks passed

python -m scripts.amiga fixtures verify-lifecycle
# lifecycle_status=running count=3 (then 4 after smoke)
# lifecycle_status=completed count=603
# OK: tournament lifecycle integrity checks passed

python -m scripts.amiga verify-tournament-formats
# OK: every tournament with games has has_league or has_cup

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Lifecycle Smoke Kitchen" ... --dry-run
# DRY RUN: rolled back; created tournament_id=623 ...

python -m scripts.amiga build-tournament create-group-knockout --name "Lifecycle Smoke Cup" ... --dry-run
# DRY RUN: rolled back; created tournament_id=624 ...

python -m scripts.amiga build-tournament smoke-fixture-result --player-ids 1,2
# DRY RUN: rolled back; smoke tournament_id=625 ...

python -m scripts.amiga build-tournament create-kitchen-marathon --name "Lifecycle Transition Smoke" ... --player-ids 1,2,3,4
# created tournament_id=626

python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status ready --dry-run
# DRY RUN: rolled back

python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status ready
python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status running
# started_at set

python -m scripts.amiga fixtures record-result --fixture-id 84 --goals-a 2 --goals-b 1
# game_id=27419

python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status completed
# exit 1 — 5 scheduled fixtures; refused without --force

python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status completed --force
# completed with unplayed_scheduled_fixtures=5

python -m scripts.amiga fixtures record-result --fixture-id 85 --goals-a 1 --goals-b 0
# exit 1 — lifecycle_status is 'completed'; result entry allowed only in running

python -m scripts.amiga fixtures set-tournament-status --tournament-id 1 --status draft
# exit 1 — imported historical tournament; refused without --force

python -m scripts.amiga fixtures set-tournament-status --tournament-id 626 --status running --force
# restored running so verify-lifecycle passes
```

## Local data backfill/migration performed

- Applied `008_tournament_lifecycle.sql` locally via pymysql (603 imported → `completed`, 3 existing generated with games → `running`).
- Smoke tournament `626` left in `running` with one recorded game (cannot `cleanup-generated` once games exist).

## Risks/limitations/not verified

- PHP browser ops not exercised via HTTP (PHP lint only); lifecycle check mirrors CLI in `amiga_fixture_record_result`.
- `mysql` CLI not on PATH locally; migration applied via Python helper.
- `completed` with `--force` while scheduled fixtures remain is allowed but fails `verify-lifecycle` until fixtures are played or voided.
- No public lifecycle UI; no automatic transition to `running` on first result entry.
- Staging re-export not run.

## Commit hash and push target

- Implementation commit: `508c205c7363784f53d59242754bf2f1caca9972`
- Push target: `origin/main`

## Recommended next steps

1. Re-run `scripts/export_ko2amiga_db.ps1` before next staging import.
2. Add browser ops control for `set-tournament-status` if internal UI needs it.
3. Consider auto-transition `draft` → `running` on first result entry (policy decision).
4. Public tournament navigation should filter on lifecycle when exposed.
5. Registration workflow (`registration` status) when entrant UI lands.
