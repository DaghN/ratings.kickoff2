# How to add Swiss later — implementation checklist

**Status:** Swiss v1 implemented (Jun 2026) — `create-swiss`, `generate-swiss-round`, `smoke-swiss`. Buchholz / PHP live-ops mirror deferred.

**Related:** [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §13 · [`scripts/amiga/tournament_format.py`](../scripts/amiga/tournament_format.py)

---

## Prerequisites (already shipped)

- [x] `tournament_format_templates` + `tournaments.format_template_id`
- [x] `tournament_stages`, `tournament_fixtures`, `amiga_games.fixture_id`
- [x] Fixture-backed standings branch in `tournament_standings.py` (`_fixture_scope`)
- [x] Structure backfill path (`tournament_structure/` + import hook) proven on Homburg
- [x] `swiss` template row seeded with `spec_json.status = "planned"`

---

## Checklist when implementing Swiss

### 1. Finalize template `spec_json`

In `FORMAT_TEMPLATES` for slug `swiss`, change `status` from `"planned"` to `"implemented"` and lock:

| Field | Purpose |
|-------|---------|
| `stages[]` | One or more `swiss_rounds` stages |
| `pairing_policy` | e.g. `swiss_standard` — Buchholz, no rematch until necessary |
| `round_count` | Fixed N or `ceil(log2(entrants))` |
| `standings_resolver` | Points per round + tie-break keys |
| `stage_factory` | Python entry point that creates round fixtures |

### 2. Stage factory (`tournament_builder.py` or new module)

- [ ] `create_swiss_tournament(...)` — entrants, round count, bye policy
- [ ] Per round: create `tournament_stages` row (`stage_type` TBD — likely `league` or new `swiss` enum value if DDL allows)
- [ ] Generate `tournament_fixtures` for round pairings (no players until draw run, or seed round 1 from `seed_no`)
- [ ] Register entrants + `tournament_stage_players`

### 3. Standings resolver hook

- [ ] Branch in `tournament_standings.py` / `_fixture_scope` for Swiss stage type
- [ ] Scope: one table per Swiss stage (or per round — product decision)
- [ ] Tie-breaks: points, Buchholz, head-to-head (document order)
- [ ] Mirror branch in PHP `amiga_post_game_standings.php` if live ops needed

### 4. Pairing engine (minimal v1)

- [ ] Round 1: sort by seed, pair 1–2, 3–4, …
- [ ] Later rounds: group by score, pair within score groups, avoid rematches
- [ ] Bye handling for odd player count
- [ ] CLI: `fixtures generate-swiss-round --tournament-id N --round R`

### 5. Result entry

- [ ] Reuse `fixtures record-result` → `amiga_games.fixture_id`
- [ ] After round complete, optional auto-advance to next round fixtures

### 6. Historical backfill (optional)

- [ ] Only if an old event used Swiss with evidence — add `StructureSpec` + registry entry
- [ ] `structure verify --tournament "…"` before enabling import

### 7. Verification

```powershell
python -m scripts.amiga build-tournament create-swiss --dry-run ...
python -m scripts.amiga verify-tournament-formats   # swiss no longer "planned"
python -m scripts.amiga replay
```

### 8. Docs & export

- [ ] Update [`amiga-data-contract.md`](amiga-data-contract.md) if new `stage_type` enum
- [ ] Staging export after DDL changes

---

## Explicit non-goals for Swiss v1

- Automated honour/medal rules
- Public registration UI
- Cross-realm player linking
- Replacing phase parser for legacy imports

---

*Double elimination follows the same pattern — see `double_elimination` planned template and `stage_factory: double_elim_bracket_factory` in `tournament_format.py`.*
