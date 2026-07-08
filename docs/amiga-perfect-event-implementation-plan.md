# Amiga perfect event — implementation plan (SCH-045)

> **Historical execution record (Jul 2026):** Feature **shipped** via **`prove`** on frozen **`ko2amiga_db`**. Steps below are archaeology — **do not re-run for new work**. Forward: **`simul`** on **`ko2amiga_work`** → **`export_ko2amiga_work.ps1`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Complete** (Jun 2026) — `prove` green including `verify-perfect-event`.

**Policy:** [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md)  
**Schema id:** **SCH-045**

---

## Goal

Ship **Perfect** as a first-class Amiga honours metric: event-local flag, career rollup, catalog facet, realm HoF row, and UI on tournament honours + WC honours leaderboards. Full finalize / replay / prove parity (Python + PHP ops).

**Definition (locked):** `games >= 2` AND `losses = 0` AND `draws = 0`.

---

## Preconditions

- Local `ko2amiga_db` passes `python -m scripts.amiga prove` before slice 1.
- Read policy P1–P10 before coding.
- **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue perfect event slice N”**.
2. Agent executes **only that slice** unless user asks for multiple slices in one session.
3. Run slice **Verification** before stopping; fix failures before handoff.
4. At **STOP gates**, list browser/SQL checks and wait for user OK when marked.
5. After slices that change stored truth: **UPDATE_DOCS** Part A; Part B when SCH-045 DDL + writers ship.

---

## Slice map

| Slice | Goal | STOP gate |
|-------|------|-----------|
| **0** | Policy locked | — (done) |
| **1** | DDL SCH-045 + column registries | — |
| **2** | Pure helpers + unit tests (`is_perfect_event` oracle) | — |
| **3** | Python finalize writers (flag, honours, catalog, realm) | **A** — SQL spot checks after `replay` |
| **4** | PHP ops finalize parity | — |
| **5** | Verify + `prove` green | **B** — `prove` must pass |
| **6** | UI: tournament honours LB + HoF + deep link | **C** — browser honours + HoF |
| **7** | UI: WC honours Perfect column (snapshot COUNT, no slice DDL) | — |
| **8** | UI: catalog + player tournament filters/badges | **D** — browser filters |
| **9** | Closure: specs, export, MEMORY, perf-rating cross-link | — |

Slices 6–8 can be split across sessions; slice 5 is the data **ship gate** before UI.

---

## SCH-045 DDL (slice 1)

### `amiga_player_event_snapshots` + `amiga_player_current`

| Column | Type | Block |
|--------|------|-------|
| `is_perfect_event` | `tinyint(1) NOT NULL DEFAULT 0` | event-local (snapshots only for flag; not on current meta) |
| `perfect_events` | `smallint NOT NULL DEFAULT 0` | honours |
| `perfect_events_last_rise_tournament_id` | `int NULL` | honours rise |
| `perfect_events_last_rise_event_date` | `date NULL` | honours rise |

**Note:** `is_perfect_event` lives on **snapshots only** (event-local). `perfect_events` + rise fields on **both** snapshots (honours-as-of) and current.

Place `is_perfect_event` after `best_knockout_phase` in event-local block. Place `perfect_events` after `event_podiums` in honours block.

### `amiga_tournament_catalog_stats`

| Column | Type |
|--------|------|
| `has_perfect_participant` | `tinyint(1) NOT NULL DEFAULT 0` |

### `amiga_generalstats` + `amiga_realm_snapshots`

| Column | Type |
|--------|------|
| `MostPerfectEvents` | `int NULL` |
| `MostPerfectEventsID` | `int NULL` |
| `MostPerfectEventsName` | `varchar(50) NULL` |
| `MostPerfectEventsDate` | `mediumtext NULL` |

Migration file: `scripts/amiga/sql/045_perfect_event.sql` (and mirror under `scripts/amiga/sql/derived/` if repo habit requires).

---

## Slice 1 — DDL + registries

### Deliverables

- [ ] `scripts/amiga/sql/045_perfect_event.sql`
- [ ] `scripts/amiga/schema_bundles.py` — include `045` in prove bundle
- [ ] `scripts/amiga/snapshot_row.py` — `EVENT_LOCAL_COLUMNS`, `HONOURS_*`, `HONOURS_RISE_METRICS` / rise column tuple
- [ ] `scripts/amiga/generalstats_columns.py` — `MostPerfectEvents*` in payload manifests; extend `HONOURS_RISE_PLAYER_COLUMNS`
- [ ] `site/public_html/amiga/ops/includes/amiga_event_snapshot_persist.php` — column lists if duplicated
- [ ] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/045_perfect_event.sql`

### Verification

```text
SHOW COLUMNS FROM amiga_player_event_snapshots LIKE 'is_perfect_event';
SHOW COLUMNS FROM amiga_generalstats LIKE 'MostPerfectEvents%';
```

---

## Slice 2 — Pure helpers + tests

### Deliverables

- [ ] `scripts/amiga/perfect_event.py` (or add to `player_tournament_participation.py`):
  - `is_perfect_event_from_rollup(games, wins, draws, losses) -> bool`
- [ ] `site/public_html/includes/amiga_perfect_event.php` — mirror for PHP ops
- [ ] `scripts/amiga/test_perfect_event.py` — cases:
  - 2–0 wins → True
  - 1–0 wins → False (min games)
  - 2–0 with 1 draw → False
  - all losses → False
  - wins=games, games=0 → False

### Verification

```text
python -m unittest scripts.amiga.test_perfect_event -v
```

---

## Slice 3 — Python finalize writers

### Deliverables

**Event-local flag**

- [ ] `scripts/amiga/snapshot_row.py` / `build_event_snapshot_row` — set `is_perfect_event` from participation rollup
- [ ] `scripts/amiga/finalize_tournament.py` — participation row includes flag before snapshot persist

**Honours rollup**

- [ ] `scripts/amiga/honours_totals.py`:
  - Add `perfect_events` to `empty_honours_totals()`
  - Add `"perfect_events"` to `HONOURS_RISE_METRICS`
  - In `increment_honours_totals`: if `participation["is_perfect_event"]`, increment; on strict rise set `perfect_events_last_rise_*`
- [ ] `scripts/amiga/test_honours_rise_dates.py` — extend for perfect_events rise

**Catalog**

- [ ] `scripts/amiga/tournament_catalog_stats.py` — `has_perfect_participant` in rebuild + per-tournament refresh (EXISTS on participant flags or rollup oracle during finalize)
- [ ] `scripts/amiga/finalize_tournament.py` — pass perfect flags into `refresh_catalog_stats_for_tournament`

**Realm / HoF**

- [ ] `scripts/amiga/server_records.py` — `_CAREER_HOLDERS` entry:
  - `("MostPerfectEvents", "perfect_events", "MostPerfectEvents")`
- [ ] `scripts/amiga/realm_incremental.py` — `_HOLDER_DATE_FIELD["MostPerfectEvents"] = "perfect_events_last_rise_event_date"`
- [ ] `scripts/amiga/generalstats_columns.py` — ensure new HoF cols in `GENERALSTATS_PAYLOAD_COLUMNS` / `REALM_SNAPSHOT_COLUMNS`

**Replay**

- [ ] Full `python -m scripts.amiga replay` (or `prove`) after writers — populates all new columns

### Verification (STOP A)

```sql
-- Oracle sample: perfect flag vs rollup
SELECT COUNT(*) FROM amiga_player_event_snapshots s
WHERE s.is_perfect_event = 1
  AND NOT (s.games >= 2 AND s.losses = 0 AND s.draws = 0);

-- Career leader
SELECT p.name, c.perfect_events
FROM amiga_player_current c
JOIN amiga_players p ON p.id = c.player_id
ORDER BY c.perfect_events DESC LIMIT 5;

-- HoF holder
SELECT MostPerfectEvents, MostPerfectEventsName, MostPerfectEventsDate
FROM amiga_generalstats WHERE id = 1;

-- Catalog
SELECT COUNT(*) FROM amiga_tournament_catalog_stats WHERE has_perfect_participant = 1;
```

Expect **0** mismatches on oracle query; Oliver St **24** on present corpus.

---

## Slice 4 — PHP ops finalize parity

### Deliverables

- [ ] `site/public_html/includes/amiga_perfect_event.php` — shared helper (if not done in slice 2)
- [ ] `site/public_html/amiga/ops/includes/amiga_honours_totals_lib.php` — mirror honours increment + rise
- [ ] `site/public_html/amiga/ops/includes/amiga_event_snapshot_persist.php` — persist new columns
- [ ] `site/public_html/amiga/ops/modules/finalize_tournament.php` — flag on participation + catalog refresh
- [ ] `site/public_html/amiga/ops/includes/amiga_realm_incremental_lib.php` — `MostPerfectEvents` holder date map
- [ ] Catalog refresh PHP sibling if separate from Python path

### Verification

- [ ] Spot-refinalize one tournament (ops) and compare snapshot row to Python replay for one player — or rely on slice 5 verify

---

## Slice 5 — Verify + prove

### Deliverables

- [ ] `scripts/amiga/verify_perfect_event.py` (or extend `verify_event_snapshots.py` + `verify_hof_geo_year.py`):
  - `is_perfect_event` oracle from `amiga_games` rollup per (player, tournament)
  - `perfect_events` + rise fields oracle via replay honours tracker
  - `has_perfect_participant` per tournament
  - `MostPerfectEvents*` holder + date vs holder rise (tie-break lowest `player_id`)
- [ ] `scripts/amiga/prove.py` — wire new verify verb
- [ ] `scripts/amiga/verify_event_snapshots.py` — include `perfect_events` in honours parity if applicable

### Verification (STOP B)

```text
python -m scripts.amiga verify-perfect-event
python -m scripts.amiga prove
```

Both must exit 0. Full `prove` runtime ~5–10 min — expected.

---

## Slice 6 — UI: tournament honours LB + HoF

### Deliverables

**Leaderboard**

- [ ] `site/public_html/includes/amiga_player_tournament_lib.php` — `amiga_tournament_honours_leaderboard_rows()` SELECT `perfect_events`; default sort unchanged unless product asks
- [ ] `site/public_html/includes/amiga_lb_snapshot_lib.php` — TT honours rows include `perfect_events`
- [ ] `site/public_html/amiga/leaderboards/tournament-honours.php` — **Perfect** column after Podiums; `data-k2-help` per [`k2-tooltip-policy.md`](k2-tooltip-policy.md)
- [ ] `site/public_html/includes/lb_column_help.php` — `k2_lb_help_amiga_perfect_events()`
- [ ] `k2_table` sort column indices — update `data-k2-default-sort` / skip-initial-sort if column count shifts

**Hall of Fame**

- [ ] `site/public_html/amiga/hall-of-fame.php` — row **Most perfect events** after Most tournament wins
- [ ] `site/public_html/includes/amiga_records_hof_links.php` — `most_perfect_events` → tournament-honours LB sort by Perfect col
- [ ] TT: HoF already reads realm snapshots — no new read path if slice 3 realm columns populated

**Cross-link (optional in slice 6 or 9)**

- [ ] `site/public_html/amiga/leaderboards/performance-rating.php` — one-line link to tournament honours Perfect column

### Verification (STOP C)

Browser (present):

- `/amiga/leaderboards/tournament-honours.php` — Perfect column visible; sort by Perfect — Oliver St top
- `/amiga/hall-of-fame.php` — Most perfect events row matches SQL holder
- `/amiga/hall-of-fame.php?as=year:2010` — holder/count plausible at cutoff

---

## Slice 7 — UI: WC honours Perfect column

**No slice DDL** — read path per policy §4.6.

### Deliverables

- [ ] `site/public_html/includes/amiga_slice_snapshot_lib.php` (or new helper in `amiga_player_slice_lib.php`):
  - `amiga_lb_wc_perfect_events_by_player(mysqli $con, ?AmigaSnapshotContext $ctx): array<int, int>`
  - Present: `COUNT(*)` from snapshots JOIN tournaments WHERE `is_perfect_event=1` AND WC name match, grouped by `player_id`
  - TT: same COUNT with snapshot cutoff SQL on event tuple
- [ ] `site/public_html/includes/amiga_wc_players_table.php` — honours view: **Perfect** column; adjust `amiga_lb_wc_slice_order_sql` if default sort should include Perfect (defer unless asked)
- [ ] `lb_column_help.php` — WC-specific tooltip if wording differs (likely same help text)

### Verification

- `/amiga/world-cups/players/honours.php` — Gianni T shows **1** Perfect (present corpus)
- TT spot-check one cutoff where WC perfect count should be 0

---

## Slice 8 — UI: catalog + player tournaments

### Filter query param (locked for implementation)

| Param | Value | Meaning |
|-------|-------|---------|
| `perfect` | `with-participant` | Tournaments with `has_perfect_participant = 1` |

Mirrors `videos=with-videos` habit. Omit param = all tournaments.

### Deliverables

**Catalog index**

- [ ] `site/public_html/includes/amiga_tournament_lib.php`:
  - `amiga_tournament_index_rows()` — SELECT `has_perfect_participant`
  - `amiga_tournament_index_filter_rows()` — facet for `perfect` param
  - `amiga_tournament_index_filter_url()` — preserve `perfect` in URL builder
- [ ] `site/public_html/includes/amiga_tournament_index_nav.php` — compact tab row **All | With perfect run** (label TBD: “Perfect run” or “Undefeated player”)
- [ ] `site/public_html/amiga/tournaments.php` — parse `perfect` GET param

**Player tournament history**

- [ ] `amiga_player_tournament_participation_rows()` / `participation_all` — expose `is_perfect_event`
- [ ] `site/public_html/includes/amiga_profile_blocks.php` or tournament table renderer — per-row badge/icon when perfect (subtle; match honours medal style)
- [ ] Optional client filter on player tournaments list (filter perfect events only) — `?perfect=1` on player page if useful

### Verification (STOP D)

- `/amiga/tournaments.php?perfect=with-participant` — ~181 events (present corpus)
- `/amiga/player/tournaments.php?id=<Oliver St>` — perfect events show badge
- Filter composes with existing `wc`, `type`, `country`, `year`, `videos` params

---

## Slice 9 — Closure

### Deliverables

- [ ] [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md) — status → **Implemented**
- [ ] [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5 placement matrix — add perfect event row
- [ ] [`amiga-performance-rating.md`](amiga-performance-rating.md) — cross-link shipped
- [ ] [`docs/coordination/schema-register.md`](coordination/schema-register.md) — SCH-045 entry (Part B)
- [ ] `PROJECT_MEMORY.md` + feature-log
- [ ] `scripts/export_ko2amiga_db.ps1` / export manifest — new columns flow to staging import packs automatically if tables already in export; spot-check manifest
- [ ] Staging handoff one-liner in MEMORY

---

## File touch register (summary)

| Area | Primary files |
|------|----------------|
| DDL | `045_perfect_event.sql`, `schema_bundles.py` |
| Oracle / tests | `perfect_event.py`, `test_perfect_event.py`, `test_honours_rise_dates.py` |
| Snapshots | `snapshot_row.py`, `snapshot_persist.py`, `verify_event_snapshots.py` |
| Honours | `honours_totals.py`, `amiga_honours_totals_lib.php` |
| Catalog | `tournament_catalog_stats.py`, `amiga_tournament_lib.php` |
| Realm | `server_records.py`, `realm_incremental.py`, `generalstats_columns.py`, `amiga_realm_incremental_lib.php` |
| Finalize | `finalize_tournament.py`, `finalize_tournament.php` |
| Verify | `verify_perfect_event.py`, `verify_hof_geo_year.py`, `prove.py` |
| LB / HoF UI | `tournament-honours.php`, `amiga_lb_snapshot_lib.php`, `hall-of-fame.php`, `amiga_records_hof_links.php` |
| WC UI | `amiga_slice_snapshot_lib.php`, `amiga_wc_players_table.php` |
| Catalog / player UI | `tournaments.php`, `amiga_tournament_index_nav.php`, `amiga_player_tournament_lib.php` |

---

## Execution order

```text
0 (done) → 1 → 2 → 3 → [replay] → 4 → 5 → 6 → 7 → 8 → 9
```

Slices 6, 7, 8 may run in parallel after slice 5. **Do not ship UI before slice 5 prove green.**

---

## Proof commands (quick reference)

```text
mysql ko2amiga_db < scripts/amiga/sql/045_perfect_event.sql
python -m unittest scripts.amiga.test_perfect_event scripts.amiga.test_honours_rise_dates -v
python -m scripts.amiga replay
python -m scripts.amiga verify-perfect-event
python -m scripts.amiga prove
```

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Full replay required after DDL | Expected; same as SCH-029/030 |
| `is_perfect_event` only on snapshots | Current table has no event-local flag — correct per policy |
| WC honours COUNT at read time | Small row set; document in policy §4.6; EXPLAIN if slow |
| k2-table column index drift | Update sort state constants when adding Perfect column |
| PHP/Python honours drift | Slice 4 mirrors slice 2 rise rules; slice 5 verify |
| Export / staging import | Re-run `export_ko2amiga_db.ps1` after prove; browser import apply |

---

## Starter prompt

```text
Today: Amiga perfect event SCH-045 — slice N per docs/amiga-perfect-event-implementation-plan.md.
Policy: docs/amiga-perfect-event-policy.md. prove must pass before UI slices 6+.
```