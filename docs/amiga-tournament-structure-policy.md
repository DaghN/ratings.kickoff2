# Amiga tournament structure — policy (modules vs structure)

**Status:** **Planned** — track initiated Jun 2026; execution slices 1+ pending.  
**Purpose:** Lock how we model **tournament modules (stages)** separately from **event structure** (composition, promotion, tracks), and how **legacy import** materializes fixtures without inventing draw-order schedules.

**Authority:** This doc owns **stage module taxonomy**, **structure vs semantics split**, and **legacy backfill rules**. Standings tally primitives: [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md). Honours finish: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md). Table register: [`amiga-data-contract.md`](amiga-data-contract.md). Prior exploration: [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) (partially superseded by locked decisions here).

**Implementation:** [`amiga-tournament-structure-implementation-plan.md`](amiga-tournament-structure-implementation-plan.md) · starter: [`orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md)

**History:** Jun 2026 exploration chat — rejected conflating `league`/`group` stage types (structural singleton vs multi-group encoded in type enum). Paused format-backbone slices (Homburg pilot) remain valid but must align with this policy before further backfill.

---

## 1. Two questions, two artifacts

| Question | Answered by | Examples |
|----------|-------------|----------|
| **What modules exist, and what type is each?** | `tournament_stages` + `stage_type` + `config_json` + roster/fixtures | One RR module; eight RR modules (Group A–H); KO semi module |
| **How do modules combine?** | `format_template` spec, `StructureSpec`, promotion/graph rules (evolving) | Single RR only (marathon); 8 groups → KO; silver/bronze tracks |

**Rule:** Stage **type** describes **local behaviour** (how results inside the module are interpreted). **Structure** describes **inventory, order, tracks, and promotion** — not a third stage type called `league`.

Modules may have **internal** structure via **parameters** (RR legs, bracket size, pairing policy) without a separate inter-stage graph.

---

## 2. Core decisions (locked)

| # | Decision | Rule |
|---|----------|------|
| **T1** | **Two module types (v1)** | `tournament_stages.stage_type` is **`round_robin`** or **`knockout`** only for product work. |
| **T2** | **Round-robin module** | All-play-all (or scheduled RR) in a player set → **points table semantics** on rebuild (standings `scope_type = league`). |
| **T3** | **Knockout module** | **Two-player ties** only (one or more legs per tie) → **tie-winner semantics** (standings `scope_type = knockout`). |
| **T4** | **No `placement` stage type** | Placement bands, ordinal pairing (1v2, 3v4…), 3rd-place finals = **`knockout`** modules + **structure/pairing** rules — not a third physics. |
| **T5** | **Retire `league` + `group` stage types** | Collapse to **`round_robin`**. “Single marathon” vs “Group A of eight” is **structure** (`stage_key`, `group_key`, template), not type. |
| **T6** | **Vocabulary: `league` on standings** | `amiga_tournament_standings.scope_type = league` remains the **points-table tally primitive** only — not a stage type. See standings policy S1–S10. |
| **T7** | **Games authoritative (legacy)** | For completed import, `amiga_games` is canonical for pairings, scores, and **Team A / Team B**. Do not generate fixtures from draw order. |
| **T8** | **Materialize fixtures from games** | Legacy backfill: **one fixture per game** (or per leg), copying `player_a_id` / `player_b_id` from the game row; set `fixture_id` on the game. |
| **T9** | **Side parity verify** | After link: `fixture.player_a_id = game.player_a_id` AND `fixture.player_b_id = game.player_b_id`. Flag mismatches; game wins on conflict. |
| **T10** | **Stage typing from phases** | NULL phase → one `round_robin` stage (implicit). Labeled RR phases → `round_robin` stage per bucket (`scope_key` / phase label). Knockout phases → `knockout` stage (or shared KO stage per policy in plan). Phase text retained as provenance. |
| **T11** | **Resolver precedence** | When `fixture_id` present: scope from **fixture → stage → stage_type**. Else: `tournament_phases.py` fallback (legacy_inferred). |
| **T12** | **Structure graph deferred (v1 import)** | Full promotion engine not required to ship bulk backfill. Inter-stage rules evolve in template/`StructureSpec` slices (Steve WC reference). |
| **T13** | **`is_cup` / phase histogram flags** | Recompute `has_league` / `has_cup` from **stages** after backfill (slice 7), not from Access `Cup?` + NULL-phase marathon rule alone. |
| **T14** | **Steve WC source** | Reference implementation for **structure + generation** on ~10 modern WCs; validate against games, do not replace game ground truth. |
| **T15** | **No koatd patches** | Corrections in import layer / version-controlled specs only ([`amiga-import-layer.md`](amiga-import-layer.md)). |

---

## 3. Data model (target)

```text
LEGACY GROUND
  amiga_games (scores, player_a/b, phase, fixture_id)
  tournaments (format_template_id, format_overrides, has_league, has_cup)

CANONICAL MODULES (ko2amiga)
  tournament_stages
    stage_type:  round_robin | knockout
    stage_key, group_key, track_key, sequence_no, config_json
  tournament_fixtures
    stage_id, player_a_id, player_b_id, leg_no, phase_label, status
  tournament_stage_players / tournament_entrants (live + optional legacy)

STRUCTURE (evolving)
  tournament_format_templates.spec_json
  tournament_structure/StructureSpec (per-event curated backfill)
  promotion / pairing rules (future)

DERIVED
  amiga_tournament_standings  (scope_type league | knockout — tally layer)
  honours / participation / catalog_stats
```

### Stage type → standings primitive

| `stage_type` | Result semantics (step 1) | Standings `scope_type` |
|--------------|---------------------------|-------------------------|
| `round_robin` | W/D/L, 3–1–0, rank in scope | `league` |
| `knockout` | Tie winner (GD, `extra`, …) | `knockout` |

### Examples

| Event | Modules (type) | Structure (template / graph) |
|-------|----------------|------------------------------|
| Kitchen marathon | 1 × `round_robin` | `kitchen_marathon` — single module, no promotion |
| WC Round 1 Group A | 1 × `round_robin` (key `group-a`) | `world_cup_class` — one of eight parallel RR modules |
| Semi-final tie | `knockout` fixture(s) | Bracket graph feeds pairings (live); legacy = games list |
| Dinner placement 1v2, 3v4… | `knockout` ties | Structure pairs by ordinal rank — not a `placement` type |

---

## 4. Legacy import (locked behaviour)

1. Import games and players as today (`import_access.py`).  
2. **After games exist:** classify stages per tournament; create stages + fixtures **from games** (materialize).  
3. **Do not** run circle-method or `combinations()` scheduling for completed events.  
4. **Do not** use unordered pair matching without **side parity** check ([`link.py`](../scripts/amiga/tournament_structure/link.py) pattern is insufficient alone — game-authoritative creation preferred).  
5. Standings rebuild unchanged in spirit: fixture path when `fixture_id` set; else phase parser.  
6. Full **promotion graph** optional for v1; honours may continue tier rules until structure slices land.

---

## 5. Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Keep `league` vs `group` stage types | Encodes structure (“singleton vs one of many”) in type enum |
| `placement` as third stage type | Same knockout tie semantics; pairing differs |
| Synthetic `Scores.Phase` as primary fix | Bridge only; not canonical format layer |
| Infer registration order from results | Underdetermined; games are authoritative for legacy |
| Generate RR fixtures then swap A/B | Extra step; copy sides from game at creation |
| Block all import on WC promotion engine | Bulk RR/KO materialization does not need full graph |

---

## 6. Out of scope (this track unless plan slice says otherwise)

- Live WC **generator** UI (post-import product)  
- Full automatic promotion interpreter for all 603 events  
- Online `kooldb*` ladder  
- Staging export / WinSCP (Dagh deploys)  
- Replacing phase parser entirely (legacy fallback remains)  
- Tournament index UI cutover (optional late slice)

---

## 7. Related documents

| Doc | Relation |
|-----|----------|
| [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) | Standings `league`/`knockout` tally — separate from stage types |
| [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) | Background; slice phasing partially outdated |
| [`amiga-format-backbone-orchestration-prompt.md`](amiga-format-backbone-orchestration-prompt.md) | Homburg pilot — align with T1–T5 before extending |
| [`amiga-import-layer.md`](amiga-import-layer.md) | Import transforms, manifest |
| [`docs/orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md) | Doc · plan · prompt · slices ritual |

---

*Track initiated from exploration Jun 2026 — modules vs structure, game-authoritative legacy backfill.*
