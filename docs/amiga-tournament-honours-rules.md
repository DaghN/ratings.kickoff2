# Amiga tournament honours and event finish — policy (Jun 2026)

**Status:** Design lock — **policy and contracts**; schema/writer implementation **pending**.

**Purpose:** Replace the overloaded `overall_position` column with a clear event-finish model, fix honours counters (podiums, cup medals, wins), and document derivation tiers for knockout, league, league+cup, and World Cup events.

**Authority:** This doc owns **finish semantics and honours counting rules**. Table grain and writers: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2–§5.3. Phase standings: [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings. Profile read paths: [`amiga-profile-v0.md`](amiga-profile-v0.md).

**Related history:** Jun 2026 participation placement ladder fixed knockout-only cups on tournament history, but `overall_position` still means different things by event shape (group rank on WCs, league rank on league+cup marathons, bracket finish on pure cups). UI was patched (WC finish = medal only) while career totals still read `overall_position` naively.

---

## 1. Core decisions (locked)

| Decision | Rule |
|----------|------|
| **Event finish column** | Add `event_finish_position` (`SMALLINT NULL`) on `amiga_player_tournament_participation`. **NULL** = finish not defined for this player×event. **Never use 0** as “unknown.” |
| **Retire `overall_position`** | Drop after backfill and reader migration. Do **not** keep both columns with similar names. |
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

Shared helper (target): refactor `scripts/amiga/participation_placement.py` → event-finish derivation with PHP parity in `includes/amiga_participation_placement.php`.

Apply tiers in order; first match wins unless Tier E override applies.

### Tier A — Pure knockout (not WC; no `overall` scope)

Cup or bracket-only event. Knockout scopes in standings; no league `overall` table.

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

`tournaments.has_league` and `has_cup`; `overall` scope exists **and** cup knockout scopes exist.

| Step | Rule |
|------|------|
| 1 | Cup **Final** → **1**, **2** |
| 2 | Cup **3rd place final** → **3**, **4**; else shared semi bronze (same as Tier A step 3) on **cup** semis |
| 3 | Players **not** in the cup final (and not cup semi losers already assigned): rank from **`overall` league standings** |
| 4 | Merge: cup assignments override league rank for players who appear in those cup ties |

**Minimal case (locked):** League then **final only between top two** — 1st/2nd from cup Final; **3rd** = league table position 3 among players who did not contest the title match (and similarly 4th+ from league for non-finalists).

**When cup bracket is too small or ambiguous:** NULL for affected players unless Tier E override.

### Tier C — Pure league

`overall` scope (`scope_type='overall'`, `scope_key=''`), no meaningful cup knockout finish.

- `event_finish_position` = `position` from overall standings for that player.

Covers kitchen marathons, single-phase round-robins (e.g. London XXIII).

### Tier D — World Cup

- `event_finish_position` = **NULL** (deferred holistic rank).
- `wc_medal` from knockout scopes (see §4.2).
- Group ranks remain in `amiga_tournament_standings` only — **never** copied to participation as event finish.

### Tier E — Curated overrides

Formats that cannot be inferred reliably (two leagues then 1v1/2v2/3v3 playoffs, cross-group bronze cups, placement mini-brackets for quarter-final losers, etc.):

- **Preferred store:** `amiga_tournament_finish_override` (`tournament_id`, `player_id`, `event_finish_position`) filled at import or ops.
- **Alternative:** tournament-level `placement_mode` metadata + import manifest entries.
- Overrides win over generic tiers when present.

---

## 4. Honours and winner rules

### 4.1 Career rollups (`amiga_player_tournament_totals`)

| Column | Rule (target) |
|--------|----------------|
| `tournaments_won` | `event_finish_position = 1` **OR** `wc_medal = 'gold'` |
| `podiums` | (`event_finish_position IS NOT NULL AND event_finish_position <= 3`) **OR** `wc_medal IN ('gold','silver','bronze')` |
| `wc_gold` / `wc_silver` / `wc_bronze` | Count by `wc_medal` on WC events |
| `cup_gold` / `cup_silver` / `cup_bronze` | Non-WC cup events: `event_finish_position` = 1 / 2 / 3 (`is_cup` + not WC name pattern). Tied bronze (two players at 3) → both count `cup_bronze`. |

**Legacy bug (pre-migration):** `podiums` and `cup_bronze` use `overall_position`, which counts WC group top-3 and league rank on marathons — do not treat current totals as authoritative until rebuild.

### 4.2 World Cup medals (`wc_medal`)

Apply when tournament name matches `^World Cup\s+\S` (`amiga_tournament_is_world_cup()`).

| Medal | Rule |
|-------|------|
| **Gold** | Main Final winner (`position = 1` in Final knockout tie) |
| **Silver** | Main Final loser |
| **Bronze** | 3rd place final winner **OR**, when no 3rd-place match exists and Final is complete, **both** semi-final losers (`position = 2` in semi ties) |

Do not award WC medals from group `overall` or group scope rank alone.

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

## 6. UI read rules (target)

| Surface | Finish shown | Points shown |
|---------|--------------|--------------|
| `/amiga/tournament.php` phase tabs | Standings `position` per scope | Standings `points` per scope |
| Profile recent tournaments | WC: `wc_medal` ordinal; else `event_finish_position` ordinal or — | `event_points` when single-phase; omit for league+cup marathons and WCs |
| `/amiga/player-tournaments.php` | Same as profile finish column | `event_points` |
| Tournament honours LB | From `amiga_player_tournament_totals` (post-rebuild rules) | — |

---

## 7. Implementation

**Agent execution plan:** [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) (slices 0–10, STOP gates).  
**New chat starter:** [`orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md).

**Status:** Policy locked; implementation **pending** (slice 0 not started).

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
