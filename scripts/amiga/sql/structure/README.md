# L4 structure overlay DDL

**Tables:** `tournament_format_templates`, stages, fixtures, entrants, **scoring contract** (`tournament_stage_scoring_steps` + stage/tournament cols); `amiga_games.fixture_id`; tournament lifecycle columns.

**Apply:** `apply_schema_structure()` — see `scripts/amiga/schema_bundles.py`.

Policy: [`docs/amiga-tournament-structure-policy.md`](../../../../docs/amiga-tournament-structure-policy.md).
