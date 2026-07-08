# Amiga perfect event — policy

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Implemented** (Jun 2026) — SCH-045 shipped; `python -m scripts.amiga prove` green including `verify-perfect-event`.  
**Related:** [`amiga-performance-rating.md`](amiga-performance-rating.md) · [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 · [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) · [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md)

---

## 1. Executive summary

A **perfect event** (product label: **Perfect**) is a tournament participation where the player **won every rated game** in that event, with at least **two** games played. These are exactly the undefeated events that **cannot** receive a performance rating (all-win score vector) — the complement of the performance-rating leaderboard lede.

| Concept | Rule |
|---------|------|
| **Definition** | `games >= 2` AND `losses = 0` AND `draws = 0` (equivalently `wins = games`) |
| **Grain** | Player × event (`amiga_player_event_snapshots` event-local flag) |
| **Career honour** | Running count `perfect_events` on honours block (all tournaments) |
| **HoF** | `MostPerfectEvents` realm record (value + holder id/name/date) |
| **WC honours** | **No extra slice persistence** — count from snapshot rows filtered to World Cups |
| **Catalog filter** | `has_perfect_participant` on `amiga_tournament_catalog_stats` |
| **Time travel** | Snapshot honours block + realm snapshots at cutoff |

Amiga-native only — no Access parity target.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **P1** | **Minimum games** | `games >= 2`. One-game undefeated runs do **not** count. Aligns with performance-rating minimum and current corpus (zero 1-game participations). |
| **P2** | **Wins only** | Perfect **loss** streaks (all losses, NULL perf rating) are **out of scope** — not an honour. |
| **P3** | **Event-wide games** | Same game set as event W-D-L rollup and `performance_rating` — all phases in the tournament (`amiga_games` rollup at finalize). Not phase-scoped. |
| **P4** | **Perfect ≠ winner** | `is_perfect_event` is **orthogonal** to `is_winner`. A player may be undefeated without winning the event (e.g. strong group exit). Catalog filter uses **any** perfect participant, not “perfect winner” only. |
| **P5** | **Honours LB label** | Column header **Perfect**; tooltip explains *won every game (at least 2 games)* — distinct from perf-rating “perfect win or loss”. |
| **P6** | **WC honours column** | Same **Perfect** column on World Cups honours wing; **subset count** of perfect events in WC tournaments only. **No** new columns on `amiga_player_slice_totals` / `amiga_player_slice_at_event`. |
| **P7** | **HoF row** | **Most perfect events** — career cumulative count over **all** tournaments (not WC-only). Placement: honours block on `/amiga/hall-of-fame.php`, near Most tournament wins. |
| **P8** | **HoF tie-break** | Realm rule R8: strict `>` on value to replace holder; equal value → **lowest `player_id`**. HoF date from winning holder’s rise fields. |
| **P9** | **Time travel** | All surfaces use stored snapshot / realm timeline at cutoff — no present-only backfill. |
| **P10** | **Writer boundary** | Tournament finalize + full `replay` / `prove` only ([`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)). |

### Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Infer perfect from `performance_rating IS NULL` | Also matches all-loss records and sub-2-game events — wrong for honours. |
| `has_perfect_winner` catalog filter only | Hides undefeated non-winners; product chose broader participant flag. |
| Persist `perfect_events` on WC slice tables | Sparse WC counts; canonical fact already on each snapshot row; WC LB row set is small (~200 players). |
| Phase-scoped “perfect group stage” | Different grain (`amiga_tournament_standings`); defer unless product asks. |
| Separate junction table | Same fact as W-D-L on existing snapshot row. |

---

## 3. Definition (formal)

For player **P** in finalized tournament **T**:

```text
is_perfect_event(P, T) :=
    games(P, T) >= 2
    AND losses(P, T) = 0
    AND draws(P, T) = 0
```

Where `games`, `wins`, `draws`, `losses` are the existing event-local rollup columns on `amiga_player_event_snapshots` (all rated games in **T**).

**Relationship to performance rating:** When `is_perfect_event = 1`, `performance_rating` is **NULL** (all-win vector). The converse is false: NULL perf rating also covers all-loss and `< 2` games. **Always** use `is_perfect_event`, not perf NULL, for this product.

---

## 4. Data architecture

### 4.1 Layer diagram

```text
amiga_games (ground rollup at finalize)
       │
       ├─► amiga_player_event_snapshots.is_perfect_event   (player × event)
       │         │
       │         └─► honours block: perfect_events + perfect_events_last_rise_*
       │                   │
       │                   └─► amiga_player_current (present projection)
       │
       ├─► amiga_tournament_catalog_stats.has_perfect_participant   (event)
       │
       └─► realm incremental → MostPerfectEvents* on amiga_generalstats + amiga_realm_snapshots
```

### 4.2 Player × event — event-local

| Column | Type | Rule |
|--------|------|------|
| `is_perfect_event` | `tinyint(1) NOT NULL DEFAULT 0` | Set at finalize from W-D-L rollup |

**Writer:** same finalize path as `wins` / `draws` / `losses` / `performance_rating`.

**Verify:** oracle from `amiga_games` rollup per (player, tournament); must match stored flag.

### 4.3 Career honours — snapshots + current

| Column | Type | Rule |
|--------|------|------|
| `perfect_events` | `smallint NOT NULL DEFAULT 0` | +1 when `is_perfect_event` at this event |
| `perfect_events_last_rise_tournament_id` | `int NULL` | Event where count **last strictly increased** |
| `perfect_events_last_rise_event_date` | `date NULL` | Co-stored rise date (HoF date source) |

**Writer:** `honours_totals.py` / `amiga_honours_totals_lib.php` — mirror `event_gold` rise tracking ([`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) D2–D8).

**Verify:** replay honours tracker; rise id/date oracle in `verify-hof-geo-year` (or sibling).

### 4.4 Tournament catalog — event grain

| Column | Type | Rule |
|--------|------|------|
| `has_perfect_participant` | `tinyint(1) NOT NULL DEFAULT 0` | 1 if ∃ participant with `is_perfect_event = 1` in this tournament |

**Writer:** `tournament_catalog_stats.py` / PHP refresh at finalize (same table as `game_count`, `standing_players`, …).

**Verify:** EXISTS oracle against snapshot flags (or games rollup) per tournament.

### 4.5 Hall of Fame / realm — present + timeline

Four columns on `amiga_generalstats` and mirrored on each `amiga_realm_snapshots` row:

| Column | Role |
|--------|------|
| `MostPerfectEvents` | Holder’s `perfect_events` count |
| `MostPerfectEventsID` | Holder `player_id` |
| `MostPerfectEventsName` | Denorm holder name at projection |
| `MostPerfectEventsDate` | Holder’s `perfect_events_last_rise_event_date` |

**Holder selection:** scan `amiga_player_current` (or player state at cutoff) — max `perfect_events`, tie → lowest `player_id` (R8).

**Not stored on generalstats:** rise `tournament_id` (existing habit — date only on HoF row; rise id remains on holder player row).

**No `*GameID`:** count metric, not single-game extreme.

### 4.6 World Cups honours — read path (no slice DDL)

WC **Perfect** column does **not** add columns to `amiga_player_slice_*`.

**Present:** for each player with WC slice `tournaments_played >= 1`, count snapshot rows where `is_perfect_event = 1` AND `amiga_tournament_is_world_cup(tournament)` (name `^World Cup\s+\S`).

**Time travel:** same count over snapshot rows at cutoff (player’s participated events ≤ cutoff), WC filter unchanged.

**Rationale (P6):** deliberate read-path exception — eligible WC player set is small; WC perfect counts are sparse (often 0–1); canonical fact lives once on `is_perfect_event`. Avoids duplicating a honours counter into slice tables that exist for WC game aggregates and medals.

**Index note:** query uses `player_id` + join to `tournaments` (or denorm `tournament_name` on snapshot) — acceptable at WC honours scale; revisit only if `EXPLAIN` shows pain.

---

## 5. Read-path register

| Surface | Route | Source |
|---------|-------|--------|
| Tournament honours LB — **Perfect** | `/amiga/leaderboards/tournament-honours.php` | `perfect_events` on honours block (`amiga_player_current` / snapshot at cutoff) |
| World Cups honours — **Perfect** | `/amiga/world-cups/players/honours.php` | Count `is_perfect_event` on snapshots, WC filter (§4.6) |
| Hall of Fame | `/amiga/hall-of-fame.php` | `MostPerfectEvents*` from `amiga_generalstats` / realm snapshot at cutoff |
| HoF deep link | HoF value cell | Tournament honours LB sorted by Perfect column (`amiga_records_hof_lb_target`) |
| Player tournament history | `/amiga/player/tournaments.php` | Per-row `is_perfect_event` (badge/icon — UI slice); optional client filter |
| Tournament catalog | `/amiga/tournaments.php` | ~~`has_perfect_participant` facet~~ — **removed Jun 2026** (Perfect run toggle); data column retained in lib for other surfaces |
| Player tournament list (same player) | `/amiga/player/tournaments.php?id=` | Same catalog filter on player’s events |
| Profile (later) | `/amiga/player/profile.php` | Honours strip / recent tournaments — reuse rollup + flag |
| Performance rating LB | `/amiga/leaderboards/performance-rating.php` | Cross-link copy only — no merge |

---

## 6. Writers (finalize)

Order within existing finalize transaction (after event W-D-L rollup, with or immediately after `performance_rating`):

1. **Event-local:** compute `is_perfect_event` from rollup; persist on snapshot row.
2. **Honours:** if `is_perfect_event`, increment `perfect_events`; if count rose, set `perfect_events_last_rise_*`.
3. **Catalog:** set `has_perfect_participant` for tournament *T* if any participant has `is_perfect_event`.
4. **Realm:** recompute `MostPerfectEvents*` holder; upsert `amiga_realm_snapshots` + `amiga_generalstats`.

Python + PHP ops finalize **must** mirror ([`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md)).

Full **`replay`** / **`prove`** recomputes via finalize loop — no batch repair CLI.

---

## 7. Verification

Extend existing prove verbs:

| Check | Oracle |
|-------|--------|
| `is_perfect_event` | Games rollup per (player, tournament) |
| `perfect_events` + rise fields | Honours tracker replay |
| `has_perfect_participant` | EXISTS perfect participant per tournament |
| `MostPerfectEvents*` | Max `perfect_events` + tie policy; date from holder rise |
| WC honours count (spot) | Manual COUNT vs snapshot flags for sample players |

Wire into `prove` after implementation — same habit as SCH-029 honours rise oracles.

---

## 8. Schema id (reserved)

**SCH-045** (tentative) — DDL slice:

- `amiga_player_event_snapshots` + `amiga_player_current`: `is_perfect_event`, `perfect_events`, `perfect_events_last_rise_tournament_id`, `perfect_events_last_rise_event_date`
- `amiga_tournament_catalog_stats`: `has_perfect_participant`
- `amiga_generalstats` + `amiga_realm_snapshots`: `MostPerfectEvents`, `MostPerfectEventsID`, `MostPerfectEventsName`, `MostPerfectEventsDate`

Update `generalstats_columns.py`, `server_records.py`, `realm_incremental.py`, `schema_bundles.py` in implementation track.

---

## 9. Non-goals (v1)

- WC-only HoF row (`MostWcPerfectEvents`)
- `has_perfect_winner` catalog filter (can add later as second facet)
- Phase-scoped perfect runs
- HoF list of which events were perfect (use player tournament history)
- Online / Access parity

---

## 10. Implementation track (outline)

| Slice | Deliverable |
|-------|-------------|
| **0** | This policy locked ✓ |
| **1** | SCH-045 DDL + column registries |
| **2** | Finalize writers (Python + PHP): flag, honours rollup, catalog, realm |
| **3** | Verify + `prove` green |
| **4** | UI: tournament honours LB + HoF row + deep link |
| **5** | UI: WC honours Perfect column (read path §4.6) |
| **6** | UI: catalog + player tournament filters/badges; profile (optional) |

**Implementation plan:** [`amiga-perfect-event-implementation-plan.md`](amiga-perfect-event-implementation-plan.md)

---

## 11. Corpus reference (local ko2amiga_db, Jun 2026)

Illustrative counts after policy definition (not stored truth):

| Metric | Approx. |
|--------|--------:|
| Perfect participations (≥2 games, all wins) | 183 |
| Tournaments with ≥1 perfect participant | 181 |
| Undefeated non-winners | 4 |
| Career leader (all events) | Oliver St — 24 |
| WC perfect (max) | Gianni T — 1 |
