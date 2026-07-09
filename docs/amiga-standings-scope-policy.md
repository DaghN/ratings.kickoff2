# Amiga tournament standings scope — policy (Jun 2026)

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Implemented** Jun 2026 (slices 0–7; migration `020` on existing DBs).  
**Purpose:** Unify the points-table standings primitive so NULL-phase and labeled-phase round-robin tables are the same `scope_type`, not accidental KOATD import splits.

**Authority:** This doc owns **standings scope taxonomy** and **primary league standings resolution** for honours. **Scoring contract / executor:** [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) (`scope_type` ≠ scoring primitive). Event finish tiers: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md). Table grain and participation: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5. Phase derivation rules: [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings.

**Implementation:** [`amiga-standings-scope-implementation-plan.md`](amiga-standings-scope-implementation-plan.md) · starter: [`archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md)

**History:** Track B introduced `scope_type` enum `overall` | `group` | `placement` | `knockout`. Access `Scores.Phase` NULL mapped to `overall`; any labeled round-robin phase mapped to `group` — including single-phase leagues like `League Stage` (Athens XCI) while NULL-phase marathons (Athens XCII) stayed `overall`. Same product primitive, two stored types. Jun 2026 event-finish migration retired `overall_position` on participation but left standings `overall`, worsening vocabulary collision.

---

## 1. Core decisions (locked)

| # | Decision | Rule |
|---|----------|------|
| **S1** | **One points-table primitive** | Merge `overall` and `group` into **`league`** on `amiga_tournament_standings.scope_type`. Phase identity = **`scope_key`** only. |
| **S2** | **Empty `scope_key`** | `league` + `scope_key = ''` = implicit single-phase table (NULL-phase games, kitchen-marathon builder, fixture league stage with empty key). **Not** “event finish” or holistic rank. |
| **S3** | **Knockout unchanged** | Elimination ties remain `knockout` + pair `scope_key` (`{phase}\|{id}-{id}`). Placement finals use `knockout` (not standings `placement`). |
| **S4** | **Drop legacy scope types** | After migration: standings enum is **`league` \| `knockout`** only. Remove `overall`, `group`, unused `placement` from standings enum. No dual-write period. |
| **S5** | **Layers stay separate** | `scope_type` = **aggregation primitive** (how we tabulate derived standings). **Format modules** (stages, templates, `tournament_fixtures.stage_type`) may grow beyond league/cup — this migration does not cap format builder work. |
| **S6** | **Synthetic aggregate** | When a tournament has both NULL-phase games and labeled league phases, writers may synthesize **`league` + `scope_key = ''`** aggregating all league-scope games (existing engine behaviour). Documented parity exception: Athens LXXXV (`mixed_overall_league_only` in data contract). |
| **S7** | **Primary league resolver** | Honours Tier B/C must not read `scope_type = 'overall'`. Use **`resolve_primary_league_standings()`** (§3) — fixes group-only league+cup events (e.g. Athens XCI) for minimal Tier B case. |
| **S8** | **URL compatibility** | Accept legacy `?scope=overall` and `?scope=group&scope_key=…`; redirect or map to `league` + key. |
| **S9** | **Catalog stats column** | Rename `amiga_tournament_catalog_stats.group_scopes` → **`league_scopes`** (count of distinct `league` scope keys per tournament). |
| **S10** | **Vocabulary** | Do not use “overall” for standings scope in new code or docs. Online ladder-honours “overall” cup tab is a **different realm** — unrelated. |
| **S11** | **`scope_type` ≠ scoring primitive** | `league` / `knockout` = L5 **storage shape** only. Executor math primitives = `league_table` / `knockout_tie` on stage contract — [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) §4. |
| **S12** | **`stage_id` target on L5** | Canonical module key = `tournament_stages.id` on standings rows. `scope_type` / `scope_key` → witness/URL compat (same arc as `amiga_games.phase`). |
| **S13** | **Synthetic `league`+`''` ≠ Event stats** | S6 aggregate is for standings/honours display — not event rollup ([`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) SC12). |

---

## 2. Data model (target)

```text
amiga_games (ground)
       │
       ▼
amiga_tournament_standings
  scope_type:  league  |  knockout
  scope_key:   '' | 'League Stage' | 'Round 1 - Group A' | …     |  '{phase}|{lo}-{hi}'
  position, points, W-D-L, goals  (per scope)

       │
       ▼
amiga_player_tournament_participation
  event_finish_position  ← from honours tiers (uses primary league resolver, not scope_type name)
```

**Two aggregation primitives today** (extensible later only with a new documented primitive):

| Primitive | `scope_type` | Meaning |
|-----------|--------------|---------|
| Points table | `league` | Round-robin (or league-stage) W/D/L tabulation within one phase |
| Elimination tie | `knockout` | One head-to-head tie; two rows (W/L) per scope |

**Not the same as** `tournaments.has_league` / `has_cup` catalog flags or hero badge “League” / “Cup”.

### Examples after migration

| Tournament | Before | After |
|------------|--------|-------|
| Athens XCII (24) — NULL phase | `overall` + `''` | `league` + `''` |
| Athens XCI (22) — `League Stage` | `group` + `League Stage` | `league` + `League Stage` |
| World Cup — group phase | `group` + `Round 1 - Group A` | `league` + `Round 1 - Group A` |
| Placement final | `knockout` + `3rd Place Final\|…` | unchanged |

---

## 3. Primary league standings resolver

**Purpose:** Single function used by honours derivation (Tier B league ranks, Tier C pure league) and any code that today calls `_overall_positions()` / `amiga_participation_overall_positions()`.

**Input:** All `amiga_tournament_standings` rows for one tournament (or pre-filtered list).  
**Output:** `dict[player_id → position]` from the chosen **primary** league scope, or empty when ambiguous.

**Resolution order** (first match wins):

1. **`league` + `scope_key = ''`** — use all rows in that scope (includes synthetic aggregate per S6).
2. **Exactly one distinct `scope_key`** among `league` rows — use that scope.
3. **Multiple `league` scopes** — use the scope with the **largest player count** (tie-break: lexicographically smallest `scope_key`).
4. **No `league` rows** — return empty (Tier A knockout-only path unchanged).

**World Cup:** Resolver may return a table for diagnostics, but Tier D keeps `event_finish_position` NULL; group ranks are not copied to participation (honours rules unchanged).

**WC group rank helper:** `derive_wc_group_positions()` filters `scope_type = 'league'` (was `group`).

---

## 4. Honours interaction (amendment to tiers)

| Tier | Change |
|------|--------|
| **A** | Unchanged — knockout scopes only when no primary league table applies to routing (see honours plan routing). |
| **B** | Non-cup players ranked from **`resolve_primary_league_standings()`**; cup KO assignments override as today. |
| **C** | Pure league finish = positions from **`resolve_primary_league_standings()`** (was: `overall` scope only). |
| **D** | Unchanged — WC NULL finish; `wc_medal` from knockout. |
| **E** | Unchanged — overrides win. |

Tier B/C wording updated in [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) (Jun 2026).

---

## 5. Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Rename `overall` → `league` only, keep `group` | Perpetuates NULL vs labeled split — does not achieve S1. |
| UI-only / read-layer merge | DB and honours still branch on two types; agents still confused. |
| Collapse all multi-group WC tables into one scope | Loses phase truth; breaks WC group display and parity. |
| New `scope_type` per format module (swiss, pool, …) | Premature — add only when aggregation rules differ from points table or knockout tie. |
| Store phase rank on participation | Rejected in honours policy — standings only. |

---

## 6. Out of scope (defer)

- Format-template / stage-graph builder
- Tournament tab IA (league-only hide “Overall” tab) — **fast follow** after slice 7
- Normalizing Access phase mislabels (e.g. `Semi-Final` as points table)
- Changing `tournament_fixtures.stage_type` enum (organizer layer may keep `group` as stage label)
- Staging WinSCP / server import (user deploys)
- Online `kooldb*` ladder — unrelated

---

## 7. Verification anchors

| Check | Expectation |
|-------|-------------|
| Athens XCII (24) | One `league` scope, `scope_key = ''`; 5 rows |
| Athens XCI (22) | `league` + `League Stage`; no `overall`/`group` types |
| Row counts | 5544 `league` + 2320 `knockout` post-replay (7864 total; was overall+group+knockout) |
| `verify-player-participation` after `prove` | `event_finish_position` unchanged for spot ids 22, 24, 544 |
| Full verify suite | Pass after slice 6 |
| Browser | `/amiga/tournament.php?id=24` and `id=22` sane tabs/standings |

---

## 8. Migration register (shipped)

| File | Purpose |
|------|---------|
| `scripts/amiga/sql/020_unify_league_standings_scope.sql` | Enum migration; `UPDATE` rows; `catalog_stats` column rename |

Fresh-install DDL files (`002_tournament_standings.sql`, `004_tournament_catalog_stats.sql`) use `league`/`knockout` + `league_scopes`. Handoffs: `docs/archive/orchestration/agent-handoffs/2026-06-11-012` … `018`.

---

## Related

- Format vision (empirical league/cup): [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §3.4
- Event finish (complete): [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md)
