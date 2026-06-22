# Amiga community stats — foundational policy

**Status:** **Locked** (Jun 2026) — **implemented** (slices 1–9).  
**Implementation plan:** [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md)

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) (HoF / record book — separate grain) · [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) (player timeline)

**Online analogue (pattern only):** online **server stats** — `generalstatstable` headline totals + period aggregate tables behind [`activity-charts.md`](activity-charts.md). Amiga product term: **community stats** (realm-wide, not per-player).

---

## 1. Executive summary

**Community stats** describe the Amiga realm **as a whole**: totals and breakdowns that do not belong to one player — games played, goals, draw rates, activity by calendar year, games in a host country, participant appearances by player nationality, and similar.

They are **not** Hall of Fame record holders, **not** player career rows, and **not** per-tournament catalog index rows.

| Store | Grain | Role |
|-------|-------|------|
| **`amiga_community_stats`** | `id = 1` | **Materialized present** — headline all-time cumulative scalars |
| **`amiga_community_stats_snapshots`** | `tournament_id` | **Canonical headline timeline** — same scalar columns after each finalized event |
| **`amiga_community_stat_facts`** | `(tournament_id, period_type, period_key, slice_type, slice_key, metric_key, count_basis)` | **Dimensional / period facts** — running totals at each event cutoff |

**Snapshot rule:** after every **tournament finalize**, persist headline snapshot row + fact rows for that `tournament_id`, then update present headline row from the latest snapshot. Values are **cumulative state as of that cutoff**, not per-event deltas.

**HoF boundary:** [`amiga_generalstats`](amiga-realm-snapshot-policy.md) + [`amiga_realm_snapshots`](amiga-realm-snapshot-policy.md) are the **record book** only (career extremes, single-game records, ratio leaders). Realm-wide headline totals live on **community stats** tables (`035` dropped legacy aggregate columns from HoF tables Jun 2026).

---

## 2. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **C1** | **Product name** | **Community stats** — realm-wide aggregates. Contrast: **player stats** (`amiga_player_current` / event snapshots), **HoF** (`amiga_generalstats`), **tournament catalog stats** (`amiga_tournament_catalog_stats`) |
| **C2** | **Separate from HoF** | No community metric lives on `amiga_generalstats` or `amiga_realm_snapshots` after migration. Record holders stay on realm snapshots; community totals stay on community tables |
| **C3** | **Hybrid storage** | **Wide** tables for small fixed **headline** cumulative scalars · **Narrow fact table** for period × slice × metric combinations. **Not** one keyed table for everything; **not** wide DDL per country/year/metric |
| **C4** | **Snapshot anchor** | `tournament_id` of the **finalized event** just processed — same chrono boundary as player and realm snapshots |
| **C5** | **Snapshot semantics** | Each row stores **running totals through end of that event** (including games in the event). Historical charts and time travel read stored cutoff state — **no** live `amiga_games` aggregation on hot paths |
| **C6** | **Present projection** | `amiga_community_stats` id=1 updated atomically when a new headline snapshot is written. Verify: present row = latest headline snapshot (column-wise). Finalize **must not read** present community stats for inputs — only write as output |
| **C7** | **Commit boundary** | Tournament finalize only (plus full `replay` / `prove`). Per-game ops do not touch community stats |
| **C8** | **Counting basis** | `count_basis` is a **first-class column** on fact rows: `game` (one per match) or `participant` (one per player appearance). Same `metric_key` may exist under both bases where product needs both |
| **C9** | **Metric registry** | `metric_key` values come from a **code registry** (Python + PHP manifest), not ad-hoc strings in writers. Policy defines **shape**, not the metric catalog |
| **C10** | **Slice axes** | `slice_type` + `slice_key` identify breakdown dimensions (host country, player nationality, realm-wide within a slice family, event class, …). Registry in implementation plan; policy locks the **column pattern** only |
| **C11** | **Period buckets** | `period_type` + `period_key` identify time grouping. Calendar **year** uses `YEAR(tournaments.event_date)` — same rule as [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) **H1** (NULL `event_date` excluded). Additional period types (e.g. month) require an explicit policy addendum |
| **C12** | **Stored-truth reads** | Activity hub, APIs, and time-travel surfaces read **materialized** community tables. Raw game scans acceptable only for throwaway probes or rebuild oracles |
| **C13** | **No metric v1 list in policy** | Which charts and metrics ship first is **implementation plan** scope, not this document |

---

## 3. Mental model

```text
Question: "How many games has the Amiga community played?"
Answer:   amiga_community_stats.GamesPlayed (present)
          or headline snapshot at cutoff tournament_id

Question: "How many games in calendar year 2003 by end of event E?"
Answer:   amiga_community_stat_facts
          WHERE tournament_id = snapshot_at(E)
            AND period_type = 'year' AND period_key = '2003'
            AND slice_type = 'realm' AND slice_key = '*'
            AND metric_key = 'games' AND count_basis = 'game'

Question: "How many participant appearances by English players in 2003?"
Answer:   same table, slice_type = 'player_nationality', slice_key = 'England',
          count_basis = 'participant'
```

Between finalizes, community derived truth is unchanged — same habit as player and realm snapshots.

---

## 4. Table shapes

### 4.1 Headline — wide, fixed, small

**Tables:** `amiga_community_stats` · `amiga_community_stats_snapshots`

**Contents:** realm-wide **all-time cumulative** scalars only — the family aligned with online Activity summary / legacy realm aggregate columns, e.g.:

- player count (≥1 game), games played, decided games, draws, goals, double-digits, clean sheets, derived ratios and averages

**Column set:** fixed schema; new headline field = deliberate DDL (expected rare). Snapshot table mirrors every data column on present table plus timeline keys (`tournament_id`, `event_date`, `event_chrono`, `finalized_at`, optional `tournament_name` denorm).

**Not stored here:** per-year totals, per-country breakdowns, or any keyed dimensional fact.

### 4.2 Dimensional — narrow fact rows

**Table:** `amiga_community_stat_facts`

| Column | Role |
|--------|------|
| `tournament_id` | Snapshot anchor (FK → finalized tournament) |
| `period_type` | e.g. `year` · `all_time` (slice-only cumulative within a dimension) |
| `period_key` | e.g. `2003` · sentinel `*` where type allows |
| `slice_type` | Registry enum — e.g. `realm`, `host_country`, `player_nationality`, `world_cup` |
| `slice_key` | Dimension value — e.g. `England`, or `*` for whole-realm within that slice family |
| `metric_key` | Registry enum — e.g. `games`, `goals`, `active_players` |
| `count_basis` | `game` \| `participant` |
| `value` | Numeric total **as of** `tournament_id` cutoff |

**Unique key:** `(tournament_id, period_type, period_key, slice_type, slice_key, metric_key, count_basis)`.

**Upsert:** finalize replaces or upserts fact rows for the current `tournament_id`; idempotent replay-safe.

**Sparse rows:** omit `(slice_key, period_key)` combinations with zero value if writers prefer; verify oracles use full recompute from games.

### 4.3 Rejected shapes

| Alternative | Why not |
|-------------|---------|
| Community stats on `amiga_generalstats` / realm snapshots | Wrong product boundary; couples Activity to HoF row |
| Single keyed table for headline + facts | Headline summary reads worse for no gain |
| Wide columns per slice (`GamesEngland2003`, …) | DDL explosion; unmaintainable |
| JSON blob dimensions | Weak indexes; verify and chart SQL pain |
| Multiple parallel fact tables per `slice_type` | Duplicated writer/verify patterns |
| Per-event deltas only (no running totals at anchor) | Forces expensive read-time aggregation |

---

## 5. Counting semantics

| `count_basis` | Meaning | Example |
|---------------|---------|---------|
| **`game`** | One per match | Realm games in a year; games hosted in England; draw rate |
| **`participant`** | One per player slot in a match | English player appearances in a year (two English players in one game → 2) |

Writers and verify must use the same basis per row. Charts declare which basis they display.

---

## 6. Slice and period axes (registry — not exhaustive)

Policy locks **axes**, not every metric. Implementation maintains registries for `slice_type`, `metric_key`, and allowed `(period_type, period_key)` pairings.

| Axis | Examples | Notes |
|------|----------|-------|
| **Time** | `period_type = year` | `period_key = YYYY` from tournament `event_date` |
| **Place** | `slice_type = host_country` | From `tournaments` host country |
| **People** | `slice_type = player_nationality` | From `amiga_players.country`; participant basis for appearance counts |
| **Event class** | `slice_type = world_cup`, template flags | WC vs open vs kitchen — exact slice types in implementation registry |
| **Realm default** | `slice_type = realm`, `slice_key = '*'` | Whole-community bucket within period |

New axes require policy addendum if they change counting rules or period semantics.

---

## 7. Writer boundary (conceptual)

```text
1. … existing finalize: games → ratings → standings → player snapshots/current
   → matchups → realm snapshot (HoF only after migration)
2. Compute headline community scalars through end of tournament E
3. INSERT amiga_community_stats_snapshots (headline row for E)
4. UPSERT amiga_community_stat_facts for tournament_id = E (all registered grains)
5. UPDATE amiga_community_stats id=1 from headline snapshot row E
```

**Batch replay:** each tournament finalize performs steps 2–5 — no end-of-replay-only community rebuild on the sign-off path. A **repair oracle** CLI may full-recompute for parity (same class as `generalstats-rebuild` today).

**Refinalize tournament *T*:** rewrite community snapshot and facts at *T*, recompute forward anchors for later tournaments (same class of problem as realm snapshot refinalize).

---

## 8. Read-path register (target)

| Surface | Present | Historical (cutoff / time travel) |
|---------|---------|-----------------------------------|
| `/amiga/activity.php` summary block | `amiga_community_stats` | headline snapshot at cutoff |
| Activity charts / JSON APIs | `amiga_community_stat_facts` (+ headline where needed) | facts at cutoff `tournament_id` |
| Status-style pulse | — | **Skip** — no live Amiga server ([`amiga-realm-vision.md`](amiga-realm-vision.md)) |

**PHP rule:** templates and APIs use helpers in `includes/amiga_*` — not raw table names scattered in pages.

Chart panel taxonomy and online Activity parity are **implementation plan** scope — not required in this policy.

---

## 9. Explicit skips (Amiga-native)

| Topic | Reason |
|-------|--------|
| **UTC daily / weekly active players** | Offline batch play ≠ online “who played today” |
| **Match streaks** | Unknown within-day order — [`amiga-data-contract.md`](amiga-data-contract.md) § Match streaks |
| **Calendar play streaks** | Same |
| **Live server pulse** | No live Amiga realm |
| **HoF record holders on community tables** | Wrong grain — realm snapshots |
| **Per-tournament catalog index** | `amiga_tournament_catalog_stats` — different product question |

---

## 10. Legacy transitional state

**Done (Jun 2026):** fourteen headline aggregate columns removed from `amiga_generalstats` / `amiga_realm_snapshots` (`035_drop_realm_aggregate_columns.sql`). Community tables are the sole store for realm totals; HoF writers never patch aggregate cols on realm rows.

**Ongoing rule:** new community fields go only on community tables — never on HoF / realm snapshot holder columns.

---

## 11. Verification (target)

| Check | Expect |
|-------|--------|
| Headline row count | `COUNT(community_stats_snapshots)` = count of finalized tournaments in chrono replay |
| Present parity | `amiga_community_stats` = latest headline snapshot (all columns) |
| Fact anchor | Every finalized `tournament_id` has headline snapshot; facts cover registered grains |
| Oracle | Full recompute from `amiga_games` + catalog matches finalize-built rows at **sample** cutoffs (first / mid / latest) |
| PHP parity | `verify-php-community-parity` — Python vs PHP **build** (headline + facts) at sample tournaments incl. T24 |
| Basis | No row mixes game and participant semantics in `count_basis` |
| HoF isolation | Community writers do not mutate `amiga_generalstats` holder fields |

Wire into `python -m scripts.amiga prove` when implementation ships.

---

## 12. Agent policy

- **Part B** migration registers apply when DDL + finalize writers ship.
- Extend **one** community compute module (Python finalize + PHP parity) — do not fork parallel implementations.
- Implementation plan: [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md) — slices after Dagh approves plan doc.
- **Do not** add v1 metric lists to this policy file; use plan doc + metric registry in code.

---

## 13. Related docs

| Doc | Relationship |
|-----|----------------|
| [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) | HoF / record book — separate from community stats |
| [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) | Player-centric derived design |
| [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) | Calendar year definition (H1) reused for community period buckets |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Cutoff reads for historical community charts |
| [`activity-charts.md`](activity-charts.md) | Online Activity — pattern reference only |
| [`amiga-realm-vision.md`](amiga-realm-vision.md) | Hub IA; Activity tab placeholder |
