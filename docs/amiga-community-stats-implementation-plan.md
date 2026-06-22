# Amiga community stats — implementation plan

**Status:** **Complete** (Jun 2026) — slices **1–10** done; `verify-community-stats` + `verify-php-community-parity` in `prove` (605 snapshots local). Legacy aggregate cols dropped from HoF tables (`035`).  
**Policy:** [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) (HoF — separate grain)

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit** unless Dagh asks.

**Out of scope for this plan:** full Activity chart panel port (online parity), `community-stats-rebuild` repair CLI, new fact grains beyond v1 registry (spec pass before charts).

**Migration:** **L1+** — new DDL, finalize/replay writers, verify in `prove` → **Part B** at slice 9 wrap-up.

---

## How to use this plan

1. Execute slices **1 → 9** in order (slice **0** = policy + this plan — done).
2. **STOP** if `prove` fails on `verify-community-stats` or `verify-php-community-parity`.
3. One Python compute module + one PHP mirror — no forked aggregate logic.
4. After slice 9: **UPDATE_DOCS** Part A + Part B.

---

## Locked decisions (do not re-open without user)

See policy **C1–C13**. Summary: headline wide tables + `amiga_community_stat_facts`; per-event snapshot anchor; `count_basis` column; separate from HoF; finalize-only writes.

---

## V1 metric registry (implementation scope)

Headline columns = existing **`GENERALSTATS_AGGREGATE_COLUMNS`** in [`generalstats_columns.py`](../scripts/amiga/generalstats_columns.py) (14 fields) — same names on community tables for parity during migration.

### V1 fact grains (`amiga_community_stat_facts`)

| `period_type` | `period_key` | `slice_type` | `slice_key` | `metric_key` | `count_basis` | Meaning |
|---------------|--------------|--------------|-------------|--------------|---------------|---------|
| `year` | `YYYY` | `realm` | `*` | `games` | `game` | Rated games in calendar year through cutoff |
| `year` | `YYYY` | `realm` | `*` | `goals` | `game` | Sum of goals in those games |
| `year` | `YYYY` | `realm` | `*` | `active_players` | `game` | Distinct players with ≥1 rated game in that year through cutoff |
| `year` | `YYYY` | `host_country` | country token | `games` | `game` | Games in tournaments hosted in that country (year bucket from tournament `event_date`) |
| `year` | `YYYY` | `player_nationality` | country token | `games` | `participant` | Player appearances (two same-nationality players → 2) |
| `year` | `YYYY` | `player_nationality` | country token | `goals` | `participant` | Goals scored by players of that nationality in that year |
| `all_time` | `*` | `host_country` | country token | `games` | `game` | Cumulative games in host country through cutoff |
| `all_time` | `*` | `player_nationality` | country token | `games` | `participant` | Cumulative participant appearances by nationality |

**Country token:** `TRIM(tournaments.country)` / `TRIM(amiga_players.country)`; empty/NULL excluded (same hygiene as [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) **H8**).

**Calendar year:** `YEAR(tournaments.event_date)`; NULL `event_date` → game excluded from year buckets (**H1**).

**Deferred to follow-on slice (not v1):** `slice_type = world_cup`, monthly periods, play-texture ratios as facts, chart JSON APIs beyond summary block.

Registry lives in **`scripts/amiga/community_stat_registry.py`** (Python) + mirrored constants in **`site/public_html/amiga/ops/includes/amiga_community_stat_registry.php`**.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | Dagh OK — **done** |
| **1** | DDL `034_community_stats.sql` + column manifests + registry modules | `prove` schema apply |
| **2** | Headline compute + `community_persist.py` (headline only) | unit smoke |
| **3** | Fact compute engine (v1 registry) + fact persist | unit smoke |
| **4** | Wire `finalize_tournament.py` + `replay.clear_derived` | one tournament smoke |
| **5** | `verify_community_stats.py` + `prove` step | 0 errors; headline = realm aggregate cols |
| **6** | Realm path: aggregates sourced from community headline (single compute) | `prove` green |
| **7** | PHP finalize parity + `includes/amiga_community_stats_lib.php` | PHP vs Python spot-check |
| **8** | `/amiga/activity.php` summary block (present reads) | browser spot-check |
| **9** | Export manifest, docs, MEMORY, feature-log, Part B | `prove` green |

---

## Slice 0 — Policy & plan

### Goal

Lock design before DDL.

### Tasks

- [x] [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md)
- [x] This implementation plan
- [x] Cross-links in `amiga-data-contract.md`, `amiga-realm-snapshot-policy.md`, `PROJECT_MAP.md`

### Verification

- Dagh reviewed policy C1–C13 and storage split.

---

## Slice 1 — DDL & manifests

### Goal

Empty community tables exist after `prove`; manifests match schema.

### Tasks

- [ ] Add `scripts/amiga/sql/derived/034_community_stats.sql` (mirror `scripts/amiga/sql/034_community_stats.sql` if flat copy pattern used elsewhere)
  - **`amiga_community_stats`**: `id` tinyint PK, 14 headline columns (same types/names as `GENERALSTATS_AGGREGATE_COLUMNS`), `INSERT IGNORE (id) VALUES (1)`
  - **`amiga_community_stats_snapshots`**: PK `tournament_id`; timeline cols (`event_date`, `event_chrono`, `tournament_name`, `finalized_at`); same 14 payload columns
  - **`amiga_community_stat_facts`**: composite unique key per policy §4.2; FK `tournament_id` → `tournaments`
- [ ] `scripts/amiga/community_stats_columns.py` — `COMMUNITY_HEADLINE_COLUMNS`, `COMMUNITY_SNAPSHOT_COLUMNS` (timeline + headline)
- [ ] `scripts/amiga/community_stat_registry.py` — v1 enums + `V1_FACT_SPECS` tuple
- [ ] `site/public_html/amiga/ops/includes/amiga_community_stat_registry.php` — mirror registry
- [ ] Update `schema_bundles.py` `DERIVED_SQL` (after `033_player_slice.sql`)
- [ ] Update `schema_bundles.py` `_DERIVED_DROP_ORDER` — community tables before realm_snapshots

### Verification

```powershell
python -m scripts.amiga prove
# Tables exist; 0 community rows until slice 4
```

---

## Slice 2 — Headline compute & persist

### Goal

`build_community_headline_at_cutoff(tournament_id)` returns the 14-col dict; persist writes snapshot + present row.

### Tasks

- [ ] `scripts/amiga/community_stats.py`
  - Refactor/extract from `server_records.compute_server_aggregates` — **one implementation**; `server_records` may delegate to community module for aggregates during transition
  - `build_community_headline_row(conn, as_of_tournament_id, …)` — includes timeline keys via `load_realm_cutoff`
- [ ] `scripts/amiga/community_persist.py`
  - `persist_community_headline_for_tournament(conn, tournament_id, …)`
  - Upsert `amiga_community_stats_snapshots`; `UPDATE amiga_community_stats` id=1
- [ ] `scripts/amiga/test_community_headline.py` — smoke on known cutoff vs `compute_server_aggregates` parity

### Verification

```powershell
python -m unittest scripts.amiga.test_community_headline -v
```

---

## Slice 3 — Fact compute & persist

### Goal

V1 fact rows for one tournament cutoff; idempotent upsert.

### Tasks

- [ ] `scripts/amiga/community_stat_facts.py`
  - `build_community_facts_at_cutoff(conn, as_of_tournament_id)` → list of fact dicts
  - Single pass over rated games ≤ cutoff (join `tournaments`, `amiga_players` for nationality)
  - Emit only non-zero values (sparse OK)
- [ ] `community_persist.py` — `persist_community_facts_for_tournament` — delete+insert or upsert all facts for `tournament_id`
- [ ] `scripts/amiga/test_community_facts.py` — spot vectors (realm year games, host country, participant nationality)

### Verification

```powershell
python -m unittest scripts.amiga.test_community_facts -v
```

---

## Slice 4 — Finalize wire (Python)

### Goal

Each `finalize_tournament` writes community headline + facts; replay reset clears community tables.

### Tasks

- [ ] `finalize_tournament.py` — after `persist_realm_snapshot_for_tournament`, call community persist (same transaction)
- [ ] `replay.py` `clear_derived` — truncate/delete community snapshots + facts; re-seed `amiga_community_stats` id=1
- [ ] `refinalize.py` — clear community rows for reopened tournaments + forward replay (mirror realm snapshot behaviour)

### Verification

```powershell
python -m scripts.amiga refinalize --tournament-id <known_id>
# SELECT COUNT(*) FROM amiga_community_stats_snapshots increases; facts rows > 0 for tail event
python -m scripts.amiga prove
```

---

## Slice 5 — Verify CLI

### Goal

Contract checks from policy §11; wired into holy loop.

### Tasks

- [ ] `scripts/amiga/verify_community_stats.py`
  - Row count: snapshots = finalized tournament count
  - Present headline = latest snapshot (all 14 cols)
  - **Dual-write gate:** latest community headline = latest realm snapshot aggregate columns (tolerance for DECIMAL rounding)
  - Fact oracle: recompute v1 facts from games for latest tournament_id matches stored rows
  - No invalid `count_basis` values
- [ ] `prove.py` — add `verify-community-stats` step after `verify-realm-snapshots`
- [ ] CLI: `python -m scripts.amiga verify-community-stats`

### Verification

```powershell
python -m scripts.amiga verify-community-stats
python -m scripts.amiga prove
```

---

## Slice 6 — Single source for headline aggregates

### Goal

Realm / HoF row no longer independently computes aggregates; copies community headline at finalize (or `realm_incremental` pulls aggregate patch from community builder).

### Tasks

- [ ] `realm_incremental.py` / `build_generalstats_payload` — aggregate block from `build_community_headline_row` (not second SQL scan)
- [ ] `server_records.compute_server_aggregates` — thin wrapper → community module (repair oracle uses same path)
- [ ] `verify_community_stats` remains green; `verify_realm_snapshots` unchanged

### Verification

```powershell
python -m scripts.amiga prove
# community + realm aggregate cols still match; only one compute path in code review
```

---

## Slice 7 — PHP finalize parity & read helpers

### Goal

Live PHP finalize writes community tables; shared read helpers for PHP surfaces.

### Tasks

- [ ] `site/public_html/amiga/ops/includes/amiga_community_stats_lib.php` — build + persist headline + facts (mirror Python)
- [ ] `site/public_html/amiga/ops/modules/finalize_tournament.php` — call after realm persist
- [ ] `site/public_html/includes/amiga_community_stats_lib.php` — read helpers: `amiga_community_headline_load`, `amiga_community_facts_query` (present + cutoff tournament_id)
- [ ] PHP `zero-derived` / reopen paths clear community tables (mirror Python)

### Verification

- Python vs PHP finalize on same generated tournament — headline + fact row counts match
- `verify_php_finalize_parity` extended or one-off smoke documented in slice notes

---

## Slice 8 — Activity summary UI (minimum)

### Goal

`/amiga/activity.php` shows present community headline summary (not empty placeholder).

### Tasks

- [ ] `site/public_html/includes/amiga_activity_summary.php` — pattern from [`server_activity_summary.php`](../site/public_html/includes/server_activity_summary.php); reads `amiga_community_stats` + Amiga-specific “since” label from first tournament/game
- [ ] Wire into `site/public_html/amiga/activity.php`
- [ ] Time-travel: when `as=` active, read headline snapshot at cutoff (reuse realm cutoff helper)

### Verification

- Browser: `/amiga/activity.php` — totals render; values match `SELECT * FROM amiga_community_stats`
- TT smoke: cutoff changes headline numbers when applicable

---

## Slice 9 — Export & docs closure

### Goal

Track complete; staging path includes new tables.

### Tasks

- [ ] `scripts/export_ko2amiga_db.ps1` — add community tables (part file after realm snapshots or grouped)
- [ ] `scripts/amiga/export_packs.py` manifest
- [ ] `scripts/amiga/README.md` — verify-community-stats, repair oracle note
- [ ] `amiga-data-contract.md` — Planned → Active
- [ ] `amiga-community-stats-policy.md` — link plan; status implemented
- [ ] `PROJECT_MEMORY.md`, `feature-log.md`
- [ ] Part B: Amiga L1 register note in `feature-log` (DDL `034`)

### Verification

```powershell
python -m scripts.amiga prove
python -m scripts.amiga verify-community-stats
```

---

## Repair oracle (non-sign-off)

- [ ] `python -m scripts.amiga community-stats-rebuild` — full recompute present + all snapshots + facts from games; compare to finalize-built state. **Not** on `prove` path (same class as `generalstats-rebuild`).

Optional in slice 5 or 9.

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Fact row explosion | v1 registry bounded; sparse non-zero rows only; index on `(tournament_id, period_type, period_key, slice_type)` |
| Refinalize forward pass | Same as realm snapshots — recompute community at *T* and forward |
| PHP/Python drift on facts | Shared test vectors; slice 7 gate |
| Dual-write period confusion | Slice 5 explicit gate; slice 6 removes duplicate compute |
| Export part count | Add one SQL part; update `ko2amiga_manifest.json` template |

---

## Follow-on (not numbered)

| Topic | When |
|-------|------|
| Activity chart APIs (`api/amiga_community_*.php`) | After summary block ships |
| `slice_type = world_cup` facts | Product slice |
| Drop aggregate cols from `amiga_realm_snapshots` | Separate DDL track after Activity fully on community tables |
| Historical community charts in TT hub | [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) follow-on |

*Track initiated Jun 2026 — implements [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md).*
