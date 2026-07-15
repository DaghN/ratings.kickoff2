# Amiga player inverse-count timeline — policy

> **Status:** **Implemented** (Jul 2026) — audit passed; sparse changelog shipped on `ko2amiga_work`.  
> **Purpose:** Inverse victim/culprit counts at TT via sparse changelog (S5 exception).  
> **Audit:** completed in-session Jul 2026 — root cause confirmed; size ~3.4k rows (not 10–15k).  
> **Code:** `scripts/amiga/inverse_count_changelog.py` · `sql/derived/051_inverse_count_changelog.sql` · PHP `amiga_inverse_count_*.php` · `verify-inverse-count-changelog`

**Related:** [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) (S5 non-participants) · [`website-data-contract.md`](website-data-contract.md) § Personal record pointers · [`amiga-player-chronologies-policy.md`](amiga-player-chronologies-policy.md) (pointer inventory reads) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5

---

## 1. Executive summary

Four **inverse count** columns on Amiga player career state are read from a **sparse event-indexed changelog** (`amiga_player_inverse_count_at_event`) so time-travel and present mosaic/LB stay aligned with pointer chronologies:

| Column | Meaning (on hero Joe) |
|--------|------------------------|
| `MostGoalsScoredCulprits` | Players whose **current** `MostGoalsScoredVictimID` is Joe |
| `BiggestWinCulprits` | Players whose **current** `BiggestWinVictimID` is Joe |
| `MostGoalsConcededVictims` | Players whose **current** `MostGoalsConcededCulpritID` is Joe |
| `BiggestLossVictims` | Players whose **current** `BiggestLossCulpritID` is Joe |

**Transfer logic in `PlayerState` is correct** (in-memory replay: **0** mismatches between stored inverse counts and pointer oracle). **Persistence was wrong:** counts change on a player when **someone else** plays, but we only wrote snapshot/current rows for **event participants**. **Fixed** by sparse changelog at finalize.

---

## 2. How the bug was found

Track B shipped pointer chronologies (MGC/BL Victims, MGS/BW Culprits). Parity smoke:

- **Present + TT** chronology row count = inverse pointer scan at cutoff → **passes** when pointers consistent.
- **Mosaic aggregate** (`MostGoalsScoredCulprits` on hero snapshot) vs chronology count → **fails** for many players (e.g. id=328: stored **11**, pointers **10**).

DB audit on `ko2amiga_work` (Jul 2026):

| Metric | Players with count > 0 | Stored ≠ pointer inverse |
|--------|------------------------:|-------------------------:|
| MGS culprits | 276 | 103 |
| BW culprits | 240 | 104 |
| MGC victims | 113 | 20 |
| BL victims | 108 | 17 |

Pattern: **stored ≥ inverse**, never the reverse — consistent with **missing decrements** when credit transfers away in events the hero did not attend.

**Rejected explanation:** “legacy import / old `>=` rules before simul.” `ko2amiga_work` is rebuilt via **`clear_derived` + full replay** (`scripts/amiga/modern/simul.py`). In-memory replay after all games: inverse counts match pointer oracle **perfectly**; DB does not.

---

## 3. Root cause

### 3.1 Contract (inverse counts)

From [`website-data-contract.md`](website-data-contract.md) § Personal record pointers:

- Single-game extremes use **`>`** (strict) on improvement; on strict beat with credited opponent change, apply **−1 / +1** on the two opponents' inverse columns.
- Hero's inverse count = **COUNT** of other players whose snapshot **pointer** currently names hero.

### 3.2 Writer path (correct in memory)

- Python: `scripts/k2_rating_core/player_state.py` → `_transfer_record_count()` during `apply_match`.
- Amiga simul: `scripts/amiga/modern/replay.py` → `finalize_tournament()` → `persist_tournament_event_snapshots()`.
- PHP ops mirror: `site/public_html/ops/includes/post_game_player_state.php` (online ladder; same semantics).

### 3.3 Persistence gap (sparse S5)

[`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) **S5**: non-participants get **no** new snapshot row; cutoff state = **last participated** snapshot.

That is valid for **self-owned** career fields (rating, goals, hero's own `MostGoalsScoredVictimID`).

It is **invalid** for **inverse counts** on hero: hero's count can change at event *E* while hero did **not** play *E*.

```text
Event 100 — B plays; snapshot written: MostGoalsScoredCulprits = 2
Event 101 — A plays; transfers MGS credit off B; B absent
  → A's snapshot at 101: new MostGoalsScoredVictimID (pointer correct)
  → B's latest snapshot still event 100: culprits still 2 (WRONG after cutoff 101)
```

Chronology at cutoff after 101 scans **all players'** latest snapshots ≤ cutoff for pointers → **correct** (A no longer points at B).

Mosaic/LB read **B's own** `MostGoalsScoredCulprits` column → **stale**.

### 3.4 Oracle proof (Jul 2026, local)

Full in-memory replay over 27,474 games:

| Check | Result |
|-------|--------|
| Memory inverse count vs memory pointer oracle (4 metrics) | **0 mismatches** |
| DB `amiga_player_current` vs memory (any of 4) | **153 players differ** |
| Example id=328 | mem MGS **10** = inv **10**; DB **11** |

---

## 4. Rejected fixes

| Approach | Why rejected |
|----------|----------------|
| “Legacy import drift” | Work DB is simul-replayed; not the cause. |
| Dirty flush to `amiga_player_current` only | Fixes **present** only; TT still uses stale last-participated snapshot for hero. |
| Dense `*_at_event` for all players every finalize | **~174k rows**, **~37 MB** (same scale as `amiga_player_elo_rank_at_event`); correct but heavy. |
| Pointer oracle as **primary** LB read | Mosaic: one `COUNT` — fine. **Victims LB:** must sort **all** players in SQL (`ORDER BY` on sort column). One `GROUP BY` on ~469 snapshot rows per metric is *possible* but changes every wing query; precomputed sort key preferred. |

---

## 5. Proposed solution — sparse inverse-count changelog

### 5.1 Mental model: ghost participant

For these four metrics only, define hero state at cutoff *T* as:

> Value after the **last event ≤ *T* where this player's count changed** — whether or not they played in that event.

“Ghost participant” = indexed at that event for **this metric only**; **not** a full fake row in `amiga_player_event_snapshots`.

### 5.2 Storage shape (proposed)

**Table (name TBD):** e.g. `amiga_player_inverse_count_at_event`

| Column | Role |
|--------|------|
| `player_id` | Hero whose inverse count changed |
| `tournament_id` | Event where change was committed (finalize boundary) |
| `metric` | Enum: `mgs_culprits` · `bw_culprits` · `mgc_victims` · `bl_victims` |
| `value_after` | Count after all games in that tournament are applied |
| `event_date`, `event_chrono` | TT ordering (mirror tournaments) |

**Primary key:** `(player_id, tournament_id, metric)` — at most one row per player per event per metric (batch to **end-of-event** value if multiple transfers in same tournament).

**Insert rule:** at tournament finalize, for each player whose in-memory count for a metric **≠** last changelog value for that metric, append row.

**Size estimate** (full replay over current `ko2amiga_work`, Jul 2026 audit + ship):

| | |
|--|--|
| Transfer calls (4 metrics combined) | **5,226** |
| Changelog rows (end-of-event coalesce) | **3,423** |
| Ghost rows (non-participant changes) | **886** |
| Disk (data + indexes) | **~1–3 MB** |
| Compare: dense all-players-at-event | ~174k rows, ~37 MB |
| Compare: sparse participant snapshots today | 4,547 rows |

### 5.3 Read paths

| Surface | Present | Time travel (`as=`) |
|---------|---------|---------------------|
| Victims LB (`/amiga/leaderboards/victims.php`) | `amiga_player_current` (synced from changelog) | Latest changelog row ≤ cutoff via `amiga_inverse_count_latest_join_sql()` |
| Profile mosaic (4 inverse cells) | `amiga_inverse_count_overlay_row()` from current | Same overlay from changelog ≤ cutoff |
| Chronology Made it | **Unchanged** — pointer scan on other players' snapshots | Already correct |
| `amiga_player_current` | Updated for all touched players at finalize (incl. ghosts) | — |

**Do not** use hero's sparse snapshot column as TT authority for these four metrics.

### 5.4 Writer

At end of `finalize_tournament` (after in-memory `players` dict is final for the event):

1. For each metric in the four-metric set, for each `player_id` with `NumberGames > 0` whose count **changed** this event, upsert changelog row.
2. Update `amiga_player_current` inverse columns from latest changelog (all touched players, not only participants).
3. Snapshot rows may still carry the four columns for participants (not TT authority).

**Simul verify:** `python -m scripts.amiga verify-inverse-count-changelog` — present current vs pointer oracle + Athens I / Nazim TT sample.

---

## 6. Retire two least-metrics (deferred)

**Amiga UI has no surface** for these today — retire from Amiga product when convenient; **not blocking** changelog ship:

| Column | Retire |
|--------|--------|
| `LeastGoalsScoredVictims` | **Yes** (deferred) |
| `LeastGoalsConcededCulprits` | **Yes** (deferred) |

Still referenced in shared `PlayerState`, PHP online ops, and snapshot DDL. Shared transfers **kept** until a separate online deprecation.

**Keep the four max/tie-record inverse metrics** above — those are surfaced on Victims LB and chronologies.

---

## 7. Alternatives considered (read-path)

### 7.1 Pointer oracle only (zero storage)

At cutoff, `COUNT(*)` from latest snapshot per player where `*VictimID` / `*CulpritID` = hero.

- **Pros:** No new table; definitionally correct.
- **Cons for LB:** Victims wing loads ~469 players and **SSR-sorts** on inverse columns (`amiga_lb_query_career` + `ORDER BY s.MostGoalsScoredCulprits`). Pointer oracle requires CTE + `GROUP BY` + join per load (cheap at n≈469 if done **once per metric**, not per-row correlated subquery) — but diverges from today's “sortable column on row” pattern.
- **Role after ship:** **parity oracle** for simul verify, not primary LB/mosaic read.

### 7.2 Dense at every event (elo_rank shape)

Write all four values for every established player every finalize (~174k rows).

- **Pros:** Simple TT join (`tournament_id = cutoff`).
- **Cons:** ~37 MB+; overkill when counts change ~15k times total over history.

**Chosen:** sparse changelog — TT-correct, LB-friendly, ~100× smaller than dense.

---

## 8. Impact on existing surfaces

| Surface | Today | After fix |
|---------|-------|-----------|
| Chronology Made it (MGC/BL/MGS/BW) | Pointer scan | No change |
| Mosaic inverse counts | Changelog overlay (present + TT) | Changelog |
| Victims LB sort cols 9–10, 13–14 | Present: `amiga_player_current`; TT: changelog join | Changelog |
| Sparse snapshot career block | Still writes four columns (participants only) | Authority = changelog; snapshot cols demoted |

---

## 9. Implementation status

| Item | Status |
|------|--------|
| Policy / analysis | **This doc** |
| Independent audit | **Passed** (Jul 2026) — root cause confirmed; size corrected to ~3.4k |
| DDL + finalize writer | **Shipped** — `051_inverse_count_changelog.sql` · `inverse_count_changelog.py` · PHP `amiga_inverse_count_changelog_lib.php` |
| PHP read paths (LB, mosaic) | **Shipped** — Victims LB TT join + profile mosaic overlay |
| Retire least-metrics in ops | **Deferred** — Amiga UI has no surface; shared `PlayerState`/online ops still write columns |
| Simul verify script | **Shipped** — `verify-inverse-count-changelog` in modern verify suite |

**Sign-off:** audit pass → implementation → `replay` on `ko2amiga_work` → verify oracle green → read-path switch. **Done** on work DB (3,423 changelog rows).

---

## 10. Locked decisions

| # | Decision |
|---|----------|
| **I1** | Four metrics only: MGS culprits, BW culprits, MGC victims, BL victims |
| **I2** | Authority at TT = sparse **changelog**, not hero's last participated snapshot |
| **I3** | Changelog grain = `(player_id, tournament_id, metric)` at **finalize** with end-of-event `value_after` |
| **I4** | Retire `LeastGoalsScoredVictims` / `LeastGoalsConcededCulprits` from **Amiga product surfaces** (no UI today). Shared `PlayerState` / online ops transfers **kept** until a separate online deprecation — **deferred** (not blocking changelog ship) |
| **I5** | Pointer oracle = simul verify, not primary hot-path read for LB |
| **I6** | Not dense all-players-at-event table (~174k rows) |

---

*Last updated: Jul 2026 — implemented after audit; changelog ~3,423 rows on `ko2amiga_work`.*