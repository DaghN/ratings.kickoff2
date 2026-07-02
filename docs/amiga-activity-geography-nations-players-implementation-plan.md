# Amiga Activity — Geography Nations player grains (implementation plan)

**Status:** **Slices A–D shipped** (Jul 2026) — slice **E** (name tooltips) deferred.

**Policy (locked):** [`amiga-activity-geography-nations-players-policy.md`](amiga-activity-geography-nations-players-policy.md)

**Authority:** Policy definitions win over this plan on metric meaning; [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) wins on table shape.

---

## 0. Scope & invariants

| Invariant | Rule |
|-----------|------|
| **Stored truth** | New metrics = scalar rows in `amiga_community_stat_facts` only |
| **Dual writers** | Python scan + PHP scan stay in parity; prove must stay green |
| **No DDL** | Unless optional `amiga_player_debuts` table is promoted in slice E (Dagh sign-off) |
| **Charts read-only** | APIs + JS only; writers live in finalize / prove |
| **TT** | Every API fetch carries `as=`; partial-year footers on year bars |
| **Encoding** | New PHP/JS via UTF-8 no BOM (Windows agent rule) |

**Exit criterion:** Nations page shows 8 panels in policy order; prove green; mid-year TT spot-check documented in §6.

---

## 1. Slice overview

| Slice | Deliverable | Depends on | Status |
|-------|-------------|------------|--------|
| **A** | `year × player_nationality × active_players` + GEO-010 tooltip | — | **Done** |
| **B** | Registry + scan: `all_time × player_nationality × active_players` | A | **Done** |
| **C** | Registry + scan: `year × player_nationality × player_debuts` | — | **Done** |
| **D** | Charts: duel active, race roster, duel debuts + panel reorder | A, B, C | **Done** |
| **E** | Optional: hover name API + rich duel tooltips | D | Deferred (probe first) |
| **F** | Catalog rows Q-GEO-016…018 + policy/IA doc cross-links + MEMORY | D | **Done** |

Run **one prove** after B+C land (not after each slice).

---

## 2. Slice A — Shipped code checklist (verify & backfill)

### 2.1 Already in repo

- [x] `CommunityFactSpec("year", "player_nationality", "active_players", "participant")` in registry
- [x] Python `_active_players_by_nationality` finalize pass
- [x] PHP `$activePlayersByNationality` finalize pass
- [x] `K2_ACT_PLAYER_NATIONALITY_YEAR_METRICS` includes `active_players`
- [x] `amiga_community_nationality_active_by_year_at_cutoff()`
- [x] `year_facts` → `nationality_active_by_year` when `distinct_nationalities`
- [x] `mountNationalitiesYear()` + CSS for HTML tooltip

### 2.2 Operator actions

```powershell
# From repo root — rebuild all community snapshots + facts
python -m scripts.amiga prove
```

Confirm:

```sql
SELECT metric_key, COUNT(*) FROM amiga_community_stat_facts
WHERE slice_type = 'player_nationality' GROUP BY metric_key;
-- expect active_players row count same order as games/goals (~60k total across snapshots)
```

Browser: `/amiga/activity/geography/nations.php` — hover **Distinct nationalities** bar; rich tooltip when breakdown populated.

---

## 3. Slice B — Cumulative roster grain (`all_time × player_nationality × active_players`)

### 3.1 Registry

Add to `V1_FACT_SPECS` or `V2_EXTRA_FACT_SPECS` in `community_stat_registry.py`:

```python
CommunityFactSpec("all_time", "player_nationality", "active_players", "participant")
```

### 3.2 Python scan (`community_stat_facts.py`)

- Add `_active_players_by_nationality_all_time: dict[str, set[int]]` (key = country token).
- In `add_game`, for each non-null `country_a` / `country_b`, add `player_a_id` / `player_b_id` to the country set.
- In `finalize_rows`, emit one fact per country: `(all_time, *, player_nationality, country, active_players, participant) = len(set)`.

### 3.3 PHP scan (`amiga_community_realm_scan_lib.php`)

Mirror: `$activePlayersByNationalityAllTime[$country][$playerId] = true` in game loop; finalize loop writes all_time facts.

### 3.4 API

`api/amiga_community_slice_series.php` — extend:

```php
'player_nationality' => ['games', 'goals', 'active_players'],
```

No read-lib change if `amiga_community_slice_series()` already parameterizes `metric_key` (it does).

### 3.5 Verify

Prove parity; spot-check at present cutoff: England all_time active_players = distinct English players in oracle query.

---

## 4. Slice C — Nationality debuts per year (`year × player_nationality × player_debuts`)

### 4.1 Registry

```python
CommunityFactSpec("year", "player_nationality", "player_debuts", "participant")
```

Add to `PER_GAME_FACT_SPECS` exclusion via existing `player_debuts` metric_key block (post-pass aggregate).

### 4.2 Python scan

- Extend debut tracking: `_debut_year_by_player: dict[int, str]` already exists.
- Add `_debut_country_by_player: dict[int, str]` — set only on first sighting alongside debut year (use `country_a` / `country_b` for that player on debut game).
- Finalize: for each `(player_id, year, country)` in debut map, increment year × player_nationality × player_debuts count (aggregate by year+country).

### 4.3 PHP scan

Mirror: `$debutCountryByPlayer[$pid] = $country` when setting `$debutYearByPlayer`; post-pass `$debutCountsByNationality[$year][$country]++`.

### 4.4 API

`api/amiga_community_year_facts.php`:

```php
const K2_ACT_PLAYER_NATIONALITY_YEAR_METRICS = [
    'games', 'goals', 'active_players', 'player_debuts',
];
```

### 4.5 Verify

Realm `player_debuts` per year should equal **sum over nationalities** of nationality `player_debuts` for that year (each player debuts once).

---

## 5. Slice D — Charts & IA (8 panels)

### 5.1 Panel markup

File: `includes/amiga_activity_geography_nations_panels.inc.php`

Insert **before** appearances panels (policy §6 order):

1. `.amiga-act-nat-active-players-year-chart` — “Active players per year by nationality”
2. `.amiga-act-nat-roster-race-chart` — “Cumulative nation roster”
3. `.amiga-act-nat-debuts-year-chart` — “New players per year by nationality”

Keep existing four + distinct nationalities last. Update file header comment: **8 panels**.

### 5.2 JS registration

File: `js/amiga-activity-charts.js` — in Geography Nations section:

```javascript
registerGeoPanel({
    id: 'nat-active-players-year',
    selector: '.amiga-act-nat-active-players-year-chart',
    pattern: 'duel',
    metric: 'active_players',
    noun: 'active players',
    tone: 'teal'
});
registerGeoPanel({
    id: 'nat-roster-race',
    selector: '.amiga-act-nat-roster-race-chart',
    pattern: 'race',
    metric: 'active_players',
    noun: 'active players'
});
registerGeoPanel({
    id: 'nat-debuts-year',
    selector: '.amiga-act-nat-debuts-year-chart',
    pattern: 'duel',
    metric: 'player_debuts',
    noun: 'new players',
    tone: 'holo'
});
```

`registerGeoPanel` / `mountGeoDuelYear` / `mountGeoRace` need **no structural change** if metrics are in API allowlists.

**Loader order:** register new panels **before** appearances so DOM order matches drain order (or match DOM order in registry — drain follows registration order within wing block).

### 5.3 Intro copy (optional)

Nations wing intro: one line — *“Compare how many players each country fielded — not just appearances.”* (`geography/nations.php` or selector include).

### 5.4 Local verify

| URL | Check |
|-----|-------|
| `…/nations.php` | 8 panels load; duel responds to Compare A/B |
| `…/nations.php?nats=England,Germany,…` | Race lines include roster + appearances |
| `…/nations.php?as=event:<mid-year>` | 2007 (or partial year) bar < present; footer “Partial year — through …” |

---

## 6. Slice E — Tooltip names (deferred, optional)

**Only if** product wants named lists on duel bars beyond GEO-010.

### 6.1 Probe first

One-off PHP or script: at present + one mid-year cutoff, measure ms for:

- Active players in year Y, country C — distinct players from rated games through cutoff
- Debuts in year Y, country C — via first snapshot row or debut table

Target: **< ~50 ms** per hover at present on local Laragon.

### 6.2 If probe passes — read API

New endpoint e.g. `api/amiga_community_nationality_year_players.php`:

- GET: `kind=active|debut`, `year=YYYY`, `countries=England,Germany`, `as=`
- Returns: `[{ "country": "England", "players": [{ "id", "name" }] }]`
- **Must** use shared cutoff SQL from realm scan / snapshot context

### 6.3 If probe fails — small debut table (DDL slice)

Table `amiga_player_debuts` (~469 rows): `player_id`, `debut_year`, `debut_tournament_id`, `country` — written at finalize on first sighting; **Part B** register in `website-data-contract.md`.

Active lists still read-time from games; debuts from table.

### 6.4 JS

Extend `renderGroupedYearBar` or duel-specific external tooltip — reuse nationalities tooltip CSS patterns.

---

## 7. Slice F — Docs & catalog finish

- [ ] Add **Q-GEO-016…018** to `amiga-community-stats-question-catalog.md`
- [ ] Update `amiga-activity-charts-policy.md` §5.3 panel table to 8 rows + link this policy
- [ ] Update `amiga-activity-charts-implementation-plan.md` slice 7 note
- [ ] `amiga-community-stats-catalog-plan.md` §3 inventory line for nationality grains
- [ ] `PROJECT_MEMORY.md` one line when D ships
- [ ] `docs/UPDATE_DOCS.md` Part A area table row (optional)

**Part B:** only if slice E debuts table ships.

---

## 8. Risk register

| Risk | Mitigation |
|------|------------|
| Tooltip query ignores cutoff → full-year names vs partial bar | Shared cutoff helper; TT test in §5.4 |
| PHP/Python drift on new grains | prove parity modules |
| Forgot re-prove → empty charts | Ship note in policy §9; verify SQL in slice A |
| Panel order / loader desync | Register JS panels same order as DOM |
| `player_debuts` sum ≠ realm | Assert in verify or one-off test |

---

## 9. Suggested execution order (single agent session)

1. Complete **B + C** (registry + both scans) in one commit batch
2. Run **`python -m scripts.amiga prove`**
3. **D** (panels + JS + API constants if not done in step 1)
4. Manual TT check
5. **F** docs
6. **E** only if Dagh wants name tooltips on duel charts — probe gate

**Do not** start E until D is visible in browser with counts-only tooltips (default Chart.js labels).

---

## 10. Acceptance checklist

- [x] `python -m scripts.amiga prove` exit 0 (local Jul 2026)
- [x] Nationality facts include `active_players` (year + all_time) and `player_debuts` (year)
- [x] Nations page: 8 panels, policy order
- [x] Race chart “Cumulative nation roster” tracks distinct players, not appearances
- [ ] Mid-year TT spot-check (manual — partial year labelled)
- [x] Catalog Q-GEO-016…018 recorded