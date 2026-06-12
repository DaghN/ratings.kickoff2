# Amiga tournament honours and event finish — policy (Jun 2026)

**Status:** **Implemented** (Jun 2026) — slices 0–10 complete; migrations `017`–`019` on `ko2amiga_db`.

**Purpose:** Holistic event finish (`event_finish_position`), honours counters (podiums, cup medals, wins), and derivation tiers for knockout, league, league+cup, World Cup, and curated overrides.

**Authority:** This doc owns **finish semantics and honours counting rules**. Table grain and writers: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2–§5.3. Phase standings: [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings. Profile read paths: [`amiga-profile-v0.md`](amiga-profile-v0.md).

**History:** Pre–Jun 2026 `overall_position` overloaded phase ranks (WC group, league marathon, KO bracket). Replaced by `event_finish_position` + `wc_medal`; column dropped migration `018`.

---

## 1. Core decisions (locked)

| Decision | Rule |
|----------|------|
| **Event finish column** | Add `event_finish_position` (`SMALLINT NULL`) on `amiga_player_tournament_participation`. **NULL** = finish not defined for this player×event. **Never use 0** as “unknown.” |
| **Retire `overall_position`** | **Done** (migration `018`). Do **not** reintroduce a similarly named column. |
| **No phase ranks on participation** | Do **not** add `league_position` or `group_position`. Phase tables live in `amiga_tournament_standings` only. |
| **Phase vs event** | “What did the group/league table look like?” → standings per scope. “How did the player finish the **event**?” → `event_finish_position` or WC `wc_medal`. |
| **World Cup holistic finish** | `event_finish_position` stays **NULL** on WC rows until a dedicated WC finish import/verification job ships. |
| **WC podium (interim)** | Use `wc_medal` (`gold` / `silver` / `bronze`) for display and WC career counts until WC `event_finish_position` exists. |
| **Exotic formats** | Generic derivation returns **NULL** when structure is ambiguous; curated overrides per tournament (see §5). |

---

## 2. Data layers

```text
amiga_games (ground — roster, volume, event_points rollup)
       │
       ├─► amiga_tournament_standings (phase truth: group, league, knockout ties)
       │         position, points, W-D-L, goals per scope
       │
       └─► amiga_player_tournament_participation (player×event summary)
                 event_points, volume stats, rating_*
                 event_finish_position  ← holistic finish when known (NULL otherwise)
                 best_knockout_phase    ← deepest main-bracket KO round (display / diagnostics)
                 wc_medal               ← WC podium tier only
                 is_winner              ← derived from finish + medals (§4)
                       │
                       └─► amiga_player_tournament_totals (career rollups)
```

**Participation does not store phase points** (`event_points` is full-event 3-1-0 from games; phase points remain in standings only). See universe contract §5.2.1.

---

## 3. `event_finish_position` derivation tiers

Shared helpers: `resolve_primary_league_standings()` in `scripts/amiga/participation_placement.py` + `amiga_participation_resolve_primary_league_standings()` in `includes/amiga_participation_placement.php`; event-finish derivation with PHP parity in the same modules.

Apply tiers in order; first match wins unless Tier E override applies.

### Tier A — Pure knockout (not WC; no primary league standings)

Cup or bracket-only event. Knockout scopes in standings; `resolve_primary_league_standings()` empty (no `league` points table).

| Step | Condition | Assignment |
|------|-----------|------------|
| 1 | Main **Final** played | Winner **1**, loser **2** |
| 2 | **3rd place final** scope exists | Winner **3**, loser **4** |
| 3 | No 3rd-place match, Final resolved | Both **semi final** losers → **3** (tied bronze; rank **4** unused) |
| 4 | Remaining players | Order by deepest knockout round reached; ranks from **5** upward |

**Bronze without 3rd-place match:** Olympic-style — two semi-final losers share bronze tier (both `event_finish_position = 3`). This is the default for p-vs-p knockouts when no bronze match was played.

**Guards:**

- Step 3 only when main Final has both 1st and 2nd assigned (complete final).
- “Final” = normalized main final label only — not subsidiary cups (`Silver Cup Final`, etc.) unless Tier E says otherwise.
- Semi detection: `position = 2` in `Semi Final` / `Semi Finals` knockout ties.

### Tier B — League + cup

`tournaments.has_league` and `has_cup`; primary league standings exist (`resolve_primary_league_standings()` non-empty) **and** cup knockout scopes exist.

| Step | Rule |
|------|------|
| 1 | Cup **Final** → **1**, **2** |
| 2 | Cup **3rd place final** → **3**, **4**; else shared semi bronze (same as Tier A step 3) on **cup** semis |
| 3 | Players **not** in the cup final (and not cup semi losers already assigned): rank from **`resolve_primary_league_standings()`** |
| 4 | Merge: cup assignments override league rank for players who appear in those cup ties |

**Minimal case (locked):** League then **final only between top two** — 1st/2nd from cup Final; **3rd** = league table position 3 among players who did not contest the title match (and similarly 4th+ from league for non-finalists).

**When cup bracket is too small or ambiguous:** NULL for affected players unless Tier E override.

### Tier C — Pure league

Primary league table only (`resolve_primary_league_standings()` non-empty), no meaningful cup knockout finish.

- `event_finish_position` = `position` from primary league standings for that player.

Covers kitchen marathons, single-phase round-robins (e.g. London XXIII).

### Tier D — World Cup

- `event_finish_position` = **NULL** (deferred holistic rank).
- `wc_medal` from knockout scopes (see §4.2).
- Group ranks remain in `amiga_tournament_standings` only — **never** copied to participation as event finish.

### Tier E — Curated overrides

Formats that cannot be inferred reliably (two leagues then 1v1/2v2/3v3 playoffs, cross-group bronze cups, placement mini-brackets for quarter-final losers, etc.):

- **Store (shipped slice 9):** `amiga_tournament_finish_override` (`tournament_id`, `player_id`, `event_finish_position`) — migration `019`; empty by default; filled at import or ops curation.
- **Derivation:** `derive_event_finish_position` / PHP `amiga_participation_derive_event_finish_position` merge override rows **after** tiers A–D; override wins per player.
- **Alternative (future):** tournament-level `placement_mode` metadata + import manifest entries.
- Overrides win over generic tiers when present; can assign finish where generic derivation returns NULL.

---

## 4. Honours and winner rules

### 4.1 Career rollups (`amiga_player_tournament_totals`)

| Column | Rule |
|--------|------|
| `tournaments_won` | `event_finish_position = 1` **OR** `wc_medal = 'gold'` |
| `podiums` | (`event_finish_position IS NOT NULL AND event_finish_position <= 3`) **OR** `wc_medal IN ('gold','silver','bronze')` |
| `wc_gold` / `wc_silver` / `wc_bronze` | Count by `wc_medal` on WC events |
| `cup_gold` / `cup_silver` / `cup_bronze` | Non-WC cup events: `event_finish_position` = 1 / 2 / 3 (`is_cup` + not WC name pattern). Tied bronze (two players at 3) → both count `cup_bronze`. |

### 4.2 World Cup medals (`wc_medal`)

Apply when tournament name matches `^World Cup\s+\S` (`amiga_tournament_is_world_cup()`).

| Medal | Rule |
|-------|------|
| **Gold** | Main Final winner (`position = 1` in Final knockout tie) |
| **Silver** | Main Final loser |
| **Bronze** | 3rd place final winner **OR**, when no 3rd-place match exists and Final is complete, **both** semi-final losers (`position = 2` in semi ties) |

Do not award WC medals from `league` scope rank alone.

### 4.3 `is_winner` (participation row)

| Event | Rule |
|-------|------|
| World Cup | `wc_medal = 'gold'` |
| All other | `event_finish_position = 1` |

---

## 5. `best_knockout_phase`

Populate on participation rebuild: deepest main-bracket knockout round label reached (e.g. `Quarter Finals`, `Semi Finals`), from the same depth ordering used in Tier A step 4.

**Use:**

- Display (“reached semis”) when numeric finish is NULL or incomplete.
- Diagnostics and parity tooling.

**Not** a substitute for `event_finish_position` when a defined integer finish exists. Podium counting uses `event_finish_position` and `wc_medal`, not phase depth alone (except shared semi bronze encoded as `event_finish_position = 3`).

---

## 6. UI read rules (shipped)

| Surface | Finish shown | Points shown |
|---------|--------------|--------------|
| `/amiga/tournament.php` phase tabs | Standings `position` per scope | Standings `points` per scope |
| Profile recent tournaments | WC: `wc_medal` ordinal; else `event_finish_position` ordinal or — | `event_points` when single-phase; omit for league+cup marathons and WCs |
| `/amiga/player-tournaments.php` | Same as profile finish column | `event_points` |
| Tournament honours LB | From `amiga_player_tournament_totals` (post-rebuild rules) | — |

---

## 7. Implementation

**Plan (complete):** [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) · handoff log under `docs/orchestration/agent-handoffs/2026-06-11-*-amiga-event-finish-slice-*.md`.

**Status:** **Implemented** Jun 2026 (slices 0–10).

| Slice | Deliverable | Done |
|-------|-------------|------|
| 0 | Schema `017` — `event_finish_position`, `best_knockout_phase` | [x] |
| 1–4 | Python derivation tiers A–D + `best_knockout_phase` | [x] |
| 5 | Writers + honours totals rebuild | [x] |
| 6 | PHP writer parity | [x] |
| 7 | UI read paths | [x] |
| 8 | Drop `overall_position` (`018`) | [x] |
| 9 | Tier E override table + hook (`019`) | [x] |
| 10 | Documentation closure | [x] |

**Migrations (existing DBs — apply in order):**

| File | Change |
|------|--------|
| `scripts/amiga/sql/017_event_finish_position.sql` | Add `event_finish_position`, `best_knockout_phase` |
| `scripts/amiga/sql/018_drop_overall_position.sql` | Drop legacy `overall_position` |
| `scripts/amiga/sql/019_tournament_finish_override.sql` | Tier E curated overrides (empty by default) |

**Rebuild after migrate:** `python -m scripts.amiga participation-rebuild` · verify: `verify-player-participation` (includes finish invariants).

---

## 8. Explicit non-goals (defer)

| Topic | Policy |
|-------|--------|
| WC numeric finish beyond medals | `event_finish_position` NULL until verified external/import data |
| Finish bands (e.g. 5–8) without exact rank | Optional later columns; v1 uses NULL or override |
| `league_position` / `group_position` on participation | **Rejected** — use standings |
| Re-deriving phase tables from participation | **Never** — one direction only |

---

*Parent: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) · Implementation plan: [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md) § Event finish migration*
