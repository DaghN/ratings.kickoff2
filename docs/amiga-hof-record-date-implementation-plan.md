# Amiga HoF record dates — implementation plan

**Status:** Complete (Jun 2026)  
**Policy:** [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md)  
**Fixes:** SCH-028 date semantics only — counts and HoF rows already shipped

---

## Preconditions

- Local `ko2amiga_db` passes `python -m scripts.amiga prove` on SCH-028.
- Read policy D1–D10 before slice 1.

---

## Slices

| Slice | Goal | Deliverables | Proof / STOP |
|-------|------|--------------|--------------|
| **0** | Doc trio | Policy + this plan (done) | — |
| **1** | DDL SCH-029 | `029_hof_record_rise_dates.sql` + `schema_bundles.py` | **Done** — apply on local DB; 12 cols × snapshots/current |
| **2** | Honours rise tracking | `honours_totals.py`: per-metric rise id/date; carry in `honours_from_current_row` | **Done** — `test_honours_rise_dates.py` |
| **3** | Geo rise tracking | `player_geo_year.py`: track + expose rise id/date for three geo scalars; `scalars_for()` extended | **Done** — `test_player_geo_year.py`; `tournament_id` on `apply_tournament` |
| **4** | Snapshot wire | `generalstats_columns.py` rise manifests; `snapshot_row.py` / `snapshot_persist.py`; `verify_event_snapshots` current parity | **Done** — `test_snapshot_row.py` |
| **5** | Realm projection | `realm_incremental.py` `_HOLDER_DATE_FIELD` → `*_last_rise_event_date`; PHP `amiga_realm_incremental_lib.php` | **Done** — `test_realm_holder_dates.py` |
| **6** | Verify + prove | Extend `verify_hof_geo_year.py` (rise fields + HoF dates); wire stays in `prove.py` | **Done** — honours+geo rise oracle; HoF `*Date` vs holder rise; Alkis regression; `prove` green |
| **7** | PHP finalize parity | `amiga_event_snapshot_persist.php`, honours/geo libs mirror Python rise rules | **Done** — `amiga_honours_totals_lib.php`; geo rise in tracker; persist increments honours + copies rise cols |
| **8** | Closure | Update [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) date line; [`amiga-data-contract.md`](amiga-data-contract.md); MEMORY + feature-log; `export_ko2amiga_db.ps1` | **Done** — track complete; export ready for staging sync |

---

## Slice detail

### Slice 1 — DDL

Add to `amiga_player_event_snapshots` and `amiga_player_current` (identical set):

```text
tournaments_played_last_rise_tournament_id   INT NULL
tournaments_played_last_rise_event_date      DATE NULL
event_gold_last_rise_tournament_id           INT NULL
event_gold_last_rise_event_date              DATE NULL
wc_played_last_rise_tournament_id            INT NULL
wc_played_last_rise_event_date               DATE NULL
countries_played_in_last_rise_tournament_id  INT NULL
countries_played_in_last_rise_event_date     DATE NULL
opponent_countries_faced_last_rise_tournament_id  INT NULL
opponent_countries_faced_last_rise_event_date     DATE NULL
opponent_countries_beaten_last_rise_tournament_id INT NULL
opponent_countries_beaten_last_rise_event_date    DATE NULL
```

No change to `amiga_generalstats` / `amiga_realm_snapshots` column list (holder `*Date` already exist).

### Slice 2 — Honours

Extend `empty_honours_totals()` and `increment_honours_totals()`:

- Before increment, snapshot prior counts.
- After increment, if `tournaments_played` rose → set rise id/date.
- If `event_gold` rose → set `event_gold_last_rise_*`.
- If `wc_played` rose → set `wc_played_last_rise_*`.

Load prior totals from running dict in replay; from DB row on PHP single-finalize rebuild-through-tournament path.

### Slice 3 — Geo

In `PlayerGeoYearTracker.apply_tournament`, after mutating sets:

- Compare old vs new set sizes per player.
- On strict increase, set matching `*_last_rise_tournament_id` and `*_last_rise_event_date` to current tournament.

Expose in `scalars_for()` return dict for snapshot persist.

### Slice 4 — Snapshots

Add columns to snapshot builder manifest (new tuple e.g. `RECORD_RISE_COLUMNS` in `snapshot_row.py` or extend honours/geo group).

`build_event_snapshot_row` must include rise fields; `current_row_from_snapshot` copies them.

### Slice 5 — Realm

```python
# realm_incremental.py — target mapping
"MostTournamentsPlayed": "tournaments_played_last_rise_event_date",
"MostTournamentWins": "event_gold_last_rise_event_date",
"MostWcPlayed": "wc_played_last_rise_event_date",
"MostCountriesPlayedIn": "countries_played_in_last_rise_event_date",
"MostOpponentCountriesFaced": "opponent_countries_faced_last_rise_event_date",
"MostOpponentCountriesBeaten": "opponent_countries_beaten_last_rise_event_date",
```

Remove `honours_last_event_date` from these six prefixes.

### Slice 6 — Verify

Oracle path:

1. Replay honours + geo trackers across all tournaments (existing pattern in `verify_hof_geo_year.py`).
2. Compare rise id/date on `amiga_player_current` per player.
3. Compare `MostTournamentWinsDate` etc. on `generalstats` to holder’s rise date.
4. Hard-coded regression: player_id **14**, `event_gold` holder → rise date **2025-09-20**.

### Slice 7 — PHP

Mirror slices 2–5 in:

- `amiga_post_game_participation.php` / honours increment (or shared honours lib)
- `amiga_player_geo_year_lib.php`
- `amiga_event_snapshot_persist.php`
- `amiga_realm_incremental_lib.php`

### Slice 8 — Closure

- Policy line 41 in geo policy → point at record-date policy for rise semantics.
- Part A UPDATE_DOCS; feature-log note under Amiga HoF row.
- Export + staging handoff one-liner if export manifest unchanged except data.

---

## Execution order

```text
0 → 1 → 2 → 3 → 4 → (replay proves 2–4) → 5 → 6 → 7 → 8
```

Slices 2 and 3 can run in parallel; slice 4 depends on both. Full `prove` only required at slice 6+ after replay rebuilds all rise columns.

---

## Proof commands

```text
python -m unittest scripts.amiga.test_player_geo_year scripts.amiga.test_honours_rise_dates -v
python -m scripts.amiga verify-hof-geo-year
python -m scripts.amiga prove
```

(`test_honours_rise_dates` = new in slice 2.)

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Full replay required after DDL | Expected; same as SCH-028 |
| `honours_last_*` consumers confused | Document D6; grep before reusing |
| PHP single-finalize rebuild-through-tournament | Must replay honours + geo trackers through T, not read stale current |
| `refinalize.py` missing `load_player_states` import | Fix opportunistically in slice 4 or 8 |

---

## Starter prompt (new chat)

```text
Today: Amiga HoF record dates — execute slice N per docs/amiga-hof-record-date-implementation-plan.md.
Policy: docs/amiga-hof-record-date-policy.md (D1–D10).
Do not fix dates in HoF PHP; store rise tournament_id + event_date at finalize.
Year peaks unchanged. prove must pass verify-hof-geo-year including date oracle.
```
