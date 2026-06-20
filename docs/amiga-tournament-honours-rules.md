# Amiga tournament honours and event finish — policy

**Status:** **Implemented** (v2, Jun 2026) — track complete locally — [`amiga-tournament-medals-unification-implementation-plan.md`](amiga-tournament-medals-unification-implementation-plan.md) · starter [`archive/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md) ✓

**Purpose:** One **tournament** universe. Holistic finish (`event_finish_position`) is canonical for every event including World Cups. Career honours = medal + podium counts over all tournaments, with **World Cup** counters as a **subset filter** — not a parallel event type.

**Authority:** Finish semantics + honours counting. Table grain and writers: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2–§5.3. Phase standings: [`amiga-data-contract.md`](amiga-data-contract.md). Profile reads: [`amiga-profile-v0.md`](amiga-profile-v0.md).

---

## Revision history

| Version | When | Summary |
|---------|------|---------|
| **v1** | Jun 2026 | Event finish migration complete — [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md). Dual encoding: WC `wc_medal` + NULL `event_finish_position`; `cup_*` career rollups. |
| **v2** | Jun 2026 | **Unified finish** — WC podium → `event_finish_position` 1/2/3; drop `cup_*`; `event_*` + `wc_*` medal/podium totals; drop `wc_medal` column. **Supersedes v1 §4 and event-finish locked decisions E6–E9.** |

**Pre–v1 history:** Legacy `overall_position` overloaded phase ranks; dropped migration `018`.

---

## 1. Core decisions (v2 — locked)

| # | Decision |
|---|----------|
| **M1** | **Tournament-first ontology** — World Cups are tournaments in `tournaments` / participation; honours WC block = filter (`amiga_tournament_is_world_cup()`), not a separate entity. |
| **M2** | **`event_finish_position`** (`SMALLINT NULL`) on participation is the **single canonical** holistic finish when known. **NULL** = unknown. **Never 0.** |
| **M3** | **Podium tier** = `event_finish_position IN (1, 2, 3)` for **all** tournaments including World Cups. |
| **M4** | **WC below podium** — `event_finish_position` stays **NULL** until a future holistic WC rank job (4th, 5th, …). **Out of scope for v2.** |
| **M5** | **Drop `wc_medal`** on participation after backfill (migration `022`). Display labels (gold/silver/bronze) derived from `event_finish_position` when `is_world_cup`. |
| **M6** | **Reject `cup_gold` / `cup_silver` / `cup_bronze`** career columns — wrong product model (Access `is_cup` flag). Replace with **`event_gold` / `event_silver` / `event_bronze`** (all tournaments). |
| **M7** | **Stored career counters** on `amiga_player_tournament_totals`: `event_gold`, `event_silver`, `event_bronze`, `event_podiums`, `wc_played`, `wc_gold`, `wc_silver`, `wc_bronze`, `wc_podiums`, plus `tournaments_played`. |
| **M8** | **`tournaments_won`** = `event_gold` (synonym; keep column for compatibility). |
| **M9** | **`is_winner`** on participation = `event_finish_position = 1` (all tournaments). |
| **M10** | **No `league_position` / `group_position`** on participation — phase tables in `amiga_tournament_standings` only. |
| **M11** | **Bracket / knockout derivation** (tiers A–B) is **implementation** for inferring 3rd when no bronze match — not a product “cup tournament” taxonomy. |
| **M12** | **`tournaments.is_cup`** (Access `Cup?`) — import artifact only; **never** honours eligibility ([`amiga-data-contract.md`](amiga-data-contract.md)). |

### Rejected alternatives (v2)

| Alternative | Why rejected |
|-------------|--------------|
| Keep `wc_medal` as co-authority with `event_finish_position` | Two sources of truth; agents re-split WC vs “non-WC” in rollups. |
| `cup_*` career columns | Counts ~30 Access-flag cups; ignores ~555 league/kitchen 1st places. |
| “Non-WC event” as primary product noun | WC is a subset of tournaments; filters are `is_world_cup`, not a parallel type. |
| Derive `event_podiums` only at read time | Stored-truth habit for honours LB; cheap to maintain; verify `gold+silver+bronze=podiums`. |

---

## 2. Data layers (v2 target)

```text
amiga_games (ground)
       │
       ├─► amiga_tournament_standings (phase truth per scope)
       │
       └─► amiga_player_tournament_participation (player × tournament)
                 event_finish_position  ← canonical holistic finish (1/2/3/4+/NULL)
                 best_knockout_phase    ← display / diagnostics
                 is_winner              ← event_finish_position = 1
                       │
                       └─► amiga_player_tournament_totals (career rollups)
                             tournaments_played
                             event_gold / event_silver / event_bronze / event_podiums
                             wc_played
                             wc_gold / wc_silver / wc_bronze / wc_podiums
                             tournaments_won (= event_gold)
```

**World Cup classifier:** `amiga_tournament_is_world_cup()` — tournament name matches `^World Cup\s+\S`.

Participation does not store phase points — see universe contract §5.2.1.

---

## 3. `event_finish_position` derivation tiers

Shared helpers: `participation_placement.py` + `amiga_participation_placement.php`. Apply in order; Tier E override wins per player.

### Tier A — Pure knockout (not WC; no primary league standings)

| Step | Condition | Assignment |
|------|-----------|------------|
| 1 | Main **Final** played | Winner **1**, loser **2** |
| 2 | **3rd place final** scope exists | Winner **3**, loser **4** |
| 3 | No 3rd-place match, Final resolved | Both **semi final** losers → **3** (shared bronze) |
| 4 | Remaining players | Deepest KO round; ranks from **5** upward |

**Shared semi bronze:** Olympic-style — both semi losers `event_finish_position = 3`.

**Guards:** Main **Final** label only (not `Silver Cup Final`, etc.) unless Tier E. Semi: `position = 2` in Semi Final(s) ties.

### Tier B — League + knockout finale

`has_league` + `has_cup`; primary league standings + cup knockout scopes.

| Step | Rule |
|------|------|
| 1 | Cup **Final** → **1**, **2** |
| 2 | Cup **3rd place final** → **3**, **4**; else shared semi bronze on cup semis |
| 3 | Non-finalists: rank from primary league standings |
| 4 | Cup assignments override league for bracket participants |

### Tier C — Pure league

- `event_finish_position` = `position` from primary league standings.

Covers kitchen marathons, single-phase round-robins.

### Tier D — World Cup (v2)

| Rule | Detail |
|------|--------|
| **Podium** | Same knockout medal rules as v1 §4.2 — write **`event_finish_position` 1 / 2 / 3`** (not a separate `wc_medal` column). |
| **Below podium** | **NULL** (holistic WC rank 4+ deferred). |
| **Group phase** | Standings only — **never** copied to `event_finish_position`. |

**Medal mapping (WC podium):**

| `event_finish_position` | Meaning |
|-------------------------|---------|
| 1 | Gold — main Final winner |
| 2 | Silver — main Final loser |
| 3 | Bronze — 3rd-place final winner **or** shared semi losers when no 3rd-place match |

### Tier E — Curated overrides

`amiga_tournament_finish_override` (`tournament_id`, `player_id`, `event_finish_position`) — L3 DDL `sql/ground/002_tournament_finish_override.sql`. Overrides win over tiers A–D.

---

## 4. Career rollups (`amiga_player_tournament_totals`)

### 4.1 Participation → totals

| Column | SQL rule |
|--------|----------|
| `tournaments_played` | `COUNT(*)` participation rows |
| `wc_played` | `COUNT(*)` WHERE `amiga_tournament_is_world_cup(tournament_name)` |
| `event_gold` | `COUNT(*)` WHERE `event_finish_position = 1` |
| `event_silver` | `COUNT(*)` WHERE `event_finish_position = 2` |
| `event_bronze` | `COUNT(*)` WHERE `event_finish_position = 3` |
| `event_podiums` | `COUNT(*)` WHERE `event_finish_position <= 3` |
| `wc_gold` | `COUNT(*)` WHERE `event_finish_position = 1` AND World Cup |
| `wc_silver` | `COUNT(*)` WHERE `event_finish_position = 2` AND World Cup |
| `wc_bronze` | `COUNT(*)` WHERE `event_finish_position = 3` AND World Cup |
| `wc_podiums` | `COUNT(*)` WHERE `event_finish_position <= 3` AND World Cup |
| `tournaments_won` | Same as `event_gold` |

### 4.2 Verify invariants

| Invariant | Rule |
|-----------|------|
| Subset | `wc_gold ≤ event_gold`; same for silver, bronze, podiums |
| Podium sum | `event_podiums = event_gold + event_silver + event_bronze` |
| WC podium sum | `wc_podiums = wc_gold + wc_silver + wc_bronze` |
| Wins | `tournaments_won = event_gold` |
| No zero finish | `event_finish_position != 0` |
| WC podium | WC rows with finish 1/2/3 must be World Cup tournaments |

### 4.3 `is_winner` (participation)

`is_winner = 1` when `event_finish_position = 1` (all tournaments).

---

## 5. `best_knockout_phase`

Unchanged from v1 — deepest main-bracket KO round label for display when numeric finish is NULL or incomplete. **Not** used for podium counting when `event_finish_position` is set.

---

## 6. UI read rules (v2)

| Surface | Finish shown |
|---------|--------------|
| `/amiga/tournament.php` phase tabs | Standings `position` per scope |
| Profile recent tournaments | `event_finish_position` ordinal (1st/2nd/3rd) or — (WC included) |
| `/amiga/player/tournaments.php` | Same — Finish column uses ordinals for all events including WC |
| `/amiga/tournament.php` WC event stats | Medal column word derived from finish tier (Gold/Silver/Bronze); finish is canonical |
| Tournament honours LB | `event_*` + `wc_*` blocks from totals — **shipped** slice 7 |

Points / `event_points` suffix rules unchanged (contract §5.2.1).

---

## 7. Implementation

| Track | Doc |
|-------|-----|
| **v1 (complete)** | [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) |
| **v2 (complete)** | [`amiga-tournament-medals-unification-implementation-plan.md`](amiga-tournament-medals-unification-implementation-plan.md) |

**v2 migrations:**

| File | Change | Status |
|------|--------|--------|
| `021_tournament_medals_totals.sql` | Totals: add `event_*`, `wc_played`, `wc_podiums`; rename `podiums` → `event_podiums`; drop `cup_*` | **Shipped** slice 0 |
| `021b_wc_finish_backfill.sql` | WC participation: `wc_medal` → `event_finish_position` 1/2/3 | **Shipped** slice 2 |
| `022_drop_wc_medal.sql` | Drop `wc_medal` from participation | **Shipped** slice 6 |

**Rebuild after v2:** `python -m scripts.amiga participation-rebuild` · `verify-player-participation`

---

## 8. Explicit non-goals (v2)

| Topic | Policy |
|-------|--------|
| WC holistic rank 4+ for all entrants | Deferred — NULL below podium |
| Fix all ~58 non-WC NULL finishes | Separate derivation/Tier E backlog |
| `league_position` / `group_position` on participation | **Rejected** |
| Access `is_cup` honours | **Rejected** |
| Access `added_players` medal parity | CLI report only — not ship blocker |

---

## 9. Superseded v1 rules (reference only — do not implement)

| v1 rule | v2 replacement |
|---------|----------------|
| WC `event_finish_position` always NULL | WC podium → 1/2/3 |
| `wc_medal` authoritative | Dropped; finish is canonical |
| Podiums = finish ≤ 3 OR `wc_medal` | `event_finish_position <= 3` only |
| `is_winner` = finish=1 OR wc gold | `event_finish_position = 1` |
| `cup_gold/silver/bronze` | `event_gold/silver/bronze` |
| Column `podiums` | `event_podiums` + `wc_podiums` |

Event-finish locked **E6–E9** in [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) are **superseded** by **M3–M9** above.

---

*Parent: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) · Track playbook: [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md)*
