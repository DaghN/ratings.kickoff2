# Pure knockout handler — contract

**Code:** `scripts/amiga/tournament_structure/pure_knockout.py`  
**Policy:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T3, T8, T13  
**Register handler id:** `pure_knockout`

---

## Semantic contract (human review)

Assign `pure_knockout` only when **every played game** is a **two-player elimination tie**:

- Single-leg ties (one game per pair per phase)
- Two-leg ties (same phase, same pair, two games — home/away swap)
- Placement “finals” between two players (3rd place, etc.)
- Bye rounds OK (player has no game that round — not modeled as a stage)

**Not pure knockout** (use `structure_spec` or stay `pending_review`):

- Group stages / round-robin phases
- Multi-player “Places 5–8” mini-leagues
- Multiple unrelated KO tracks (Gold/Silver cup parallel) without spec
- NULL-phase events

---

## Mechanical contract (script)

The script **does not** call `parse_phase()` for stage typing. Phase text is used only for **grouping**.

### Grouping key

For each game:

```
tie_key = (phase_label_normalized, min(player_a_id, player_b_id), max(player_a_id, player_b_id))
```

- `phase_label_normalized` = stripped `amiga_games.phase`, or `"Knockout"` if empty
- All games with the same `tie_key` → **one** `knockout` stage (one module per tie)

### Legs

Games in a tie group sorted by `(game_date, id)` → `leg_no` 1, 2, 3, …

- One game → single-leg tie
- Two games, same phase, same pair → two-leg tie (WC pattern)

### Stages and fixtures

Per tie group:

1. One `tournament_stages` row: `stage_type = knockout`
2. One `tournament_fixtures` row per game (`leg_no` as above)
3. Link `amiga_games.fixture_id`

### Preflight warnings (preview)

Preview lists **warnings** — review must clear before promotion:

| Warning | Meaning |
|---------|---------|
| `null_phase` | Game has no phase label |
| `group_like_phase` | Phase contains `Group` (likely not pure KO) |
| `many_legs` | Same tie_key has >2 games |
| `no_games` | Empty tournament |

Warnings do not block preview; they block **apply** unless `--force` (dev only).

---

## Review workflow

1. Run preview CLI; read tie list + warnings
2. Open games on site; confirm format matches preview
3. If happy → set register `"handler": "pure_knockout"`
4. If not → `structure_spec` or keep `pending_review`

---

## Examples

| Event | Ties | Notes |
|-------|------|-------|
| 8p / 7g cup | 7 single-leg ties | Birmingham XIV Gold |
| WC semi 2-leg | 1 tie, 2 legs | Same `Semi Finals` phase twice |
| Stoke 15p / 14g | 7 R1 ties + QF/SF/F | `Round 1` is phase label only — still KO stages |
