# Amiga Activity — Geography Nations player grains (policy & spec)

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Shipped in code** (Jul 2026) — slices **A–D** complete; **`python -m scripts.amiga prove`** green on local `ko2amiga_db` (Jul 2026). Slice **E** (name tooltips on duel bars) deferred.

**Implementation plan:** [`amiga-activity-geography-nations-players-implementation-plan.md`](amiga-activity-geography-nations-players-implementation-plan.md)

**Parent (do not reopen storage shape):** [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md)

**Charts / IA:** [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) §5.3 (Nations wing) · [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) §4 (L1/L2 lenses)

**Question catalog:** [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) — add rows **Q-GEO-016…018** when charts ship (§8 below).

---

## 1. Executive summary

The Geography **Nations** wing originally charted **volume of play** (appearances, goals) and **scene breadth** (distinct nationalities per year). This addendum covers **people-by-nationality** metrics: how many **distinct players** each country contributed per calendar year, how rosters **grew over events**, and (optionally) **who** they were in tooltips — without storing player ID lists at every event snapshot.

| Layer | Role |
|-------|------|
| **`amiga_community_stat_facts`** | Scalar **counts** at cutoff `tournament_id` (year + all_time grains) |
| **Realm scan (Python + PHP)** | Single oracle per finalize; builds counts from rated games through cutoff |
| **Activity APIs** | Read facts at cutoff; optional breakdown / hover payloads |
| **Player layer** | Identity for tooltips (`amiga_player_event_snapshots`, optional debut table) — **not** dense community facts |

**Holy prove:** every new **count** grain must ship in **both** `scripts/amiga/community_stat_facts.py` and `site/public_html/amiga/ops/includes/amiga_community_realm_scan_lib.php`, registered in `scripts/amiga/community_stat_registry.py`, persisted by finalize, verified by `verify_community_stats` + `verify_php_community_parity`.

---

## 2. Problem this solves

| Existing chart | What it measures | Gap |
|----------------|------------------|-----|
| Appearances per year (GEO-005) | Rated **game slots** by nationality | Same players can dominate; not headcount |
| Goals per year (GEO-006) | Goals attributed to nationality | Volume, not roster depth |
| Distinct nationalities (GEO-010) | How many **countries** appeared | Not players per country |
| People → Active players (VOL-003) | Realm-wide actives per year | Not England vs Germany |

**Product goal:** Nations wing answers *“who from each country played?”* and *“how deep did each country’s bench get?”* using the same duel + race patterns as appearances/goals.

---

## 3. Metric definitions (locked)

All definitions are evaluated **as of cutoff tournament T** (time travel included).

### 3.1 `active_players` — calendar year (L1)

**Question:** How many **distinct players** with nationality *N* played at least one rated game in calendar year *Y*?

- **Grain:** `period_type = year`, `period_key = YYYY`, `slice_type = player_nationality`, `slice_key = <country name>`, `metric_key = active_players`, `count_basis = participant`
- **Attribution:** Player’s nationality = `amiga_players.country` on the rated game row (same token as appearances/goals).
- **Not:** game appearances, returning-after-gap, or WC-only unless sliced separately.

### 3.2 `active_players` — all_time at snapshot (L2 race lines)

**Question:** How many **distinct players** with nationality *N* have **ever** played a rated game through cutoff *T*?

- **Grain:** `period_type = all_time`, `period_key = *`, same slice/metric/basis as §3.1
- **Chart:** Pattern B race line via `amiga_community_slice_series.php` (cumulative roster depth per country over events).

### 3.3 `player_debuts` — calendar year by nationality (L1)

**Question:** How many players **debuted** (first rated game ever) in calendar year *Y* with nationality *N*?

- **Grain:** `period_type = year`, `period_key = YYYY`, `slice_type = player_nationality`, `slice_key = <country>`, `metric_key = player_debuts`, `count_basis = participant`
- **Debut rule:** Same as realm `player_debuts` / People SHP-009 — calendar year of **first** rated game in chrono order at cutoff.
- **Nationality at debut:** Country on the debut game row (`country_a` / `country_b` join); equivalent to static `amiga_players.country` in practice.
- **Not:** “new to the scene after absence”, “first time playing in year Y but debuted earlier”.

### 3.4 Related existing metrics (do not conflate)

| Metric | Scope | Meaning |
|--------|-------|---------|
| `games` (participant) | nationality × year | Appearance **slots** (2 per game) |
| `player_debuts` | realm × year | New players **realm-wide** (SHP-009) |
| `distinct_nationalities` | realm × year | Count of **countries** with ≥1 appearance |
| `NumberOfPlayers` / `PlayersDebuted` | headline snapshot | Cumulative debuts through event |

---

## 4. Stored grains inventory

### 4.1 Shipped in code (Jul 2026 — slice A)

| Grain | Status | Writers |
|-------|--------|---------|
| `year × player_nationality × active_players` | **Code shipped**; DB backfill via re-prove | Python `_active_players_by_nationality` · PHP `$activePlayersByNationality` |

**Read helpers / API (shipped):**

- `amiga_community_nationality_active_by_year_at_cutoff()` — breakdown for GEO-010 tooltip
- `year_facts?slice=realm&metric=distinct_nationalities` → field `nationality_active_by_year`
- `year_facts` allows `player_nationality` + `active_players` for duel charts (API constant added)
- UI: `mountNationalitiesYear()` — HTML tooltip (flag + country + *N active players*)

### 4.2 Shipped (slices B–C — Jul 2026)

| Grain | Chart use |
|-------|-----------|
| `all_time × player_nationality × active_players` | Cumulative nation roster (race) — **Q-GEO-017** |
| `year × player_nationality × player_debuts` | New players per year duel — **Q-GEO-018** |

### 4.3 Explicitly rejected storage patterns

| Pattern | Verdict | Why |
|---------|---------|-----|
| Player ID lists in `amiga_community_stat_facts.value` | **Reject** | Breaks scalar model; ~605× explosion; verify parity impossible |
| Per-event “active roster” membership rows in community facts | **Reject** | Player layer already event-dense |
| Storing full year roster at every snapshot | **Reject** | Same data re-written 605 times; use cutoff scan once per snapshot as counts only |

**Scale reference (present DB):** ~305k fact rows / 605 snapshots ≈ **500 scalar rows per event** — nationality player counts add ~21 countries × (years + all_time) per snapshot, same order of magnitude as existing nationality games/goals.

### 4.4 Optional (slice E — not shipped)

Hover name lists on duel bars — read-time oracle or small debut table; see §5.

---

## 5. Tooltip & identity (optional slices)

Chart **bars/lines use counts only**. Listing **names** is optional and **must not** change stored facts.

| Need | Default approach | Cutoff rule |
|------|------------------|-------------|
| GEO-010 bar: countries + active counts | Read **`nationality_active_by_year`** from stored facts; HTML tooltip, **full list height** (no inner scroll) | Same `tournament_id` as bar |
| Duel bar: names active in year *Y* | **Read-time** query: distinct players from rated games in *Y* for country *C* through cutoff | **Must** use `amiga_realm_game_cutoff_sql` / snapshot context — never bare `YEAR(event_date) = Y` |
| Duel bar: names debuted in year *Y* | **Read-time** from first `amiga_player_event_snapshots` row per player, or optional **`amiga_player_debuts`** table (~469 rows, one per player) | `debut_tournament_id <= cutoff` |
| Race line step: “+N at this event” | Player snapshots: players whose **first** snapshot is this `tournament_id` | Event grain, not community facts |

**Partial year:** When cutoff falls mid-calendar-year, year counts and name lists include **only games through the cutoff event**. UI marks partial year via `partialYearFooter` / “Partial year — through …” (existing Activity rule).

---

## 6. Nations wing panels (shipped IA)

**Page:** `/amiga/activity/geography/nations.php` — shared selector (`?nats=`), Pattern A duel + Pattern B race unchanged.

**Target order (8 panels):** *who → volume → breadth*

| # | Panel | Q-ID | Pattern | Metric / API |
|---|-------|------|---------|--------------|
| 1 | Active players per year by nationality | **Q-GEO-016** | A duel | `year_facts` · `player_nationality` · `active_players` |
| 2 | Cumulative nation roster | **Q-GEO-017** | B race | `slice_series` · `player_nationality` · `active_players` |
| 3 | New players per year by nationality | **Q-GEO-018** | A duel | `year_facts` · `player_nationality` · `player_debuts` |
| 4 | Appearances per year | Q-GEO-005 | A duel | existing |
| 5 | Cumulative appearances | Q-GEO-007 | B race | existing |
| 6 | Goals per year | Q-GEO-006 | A duel | existing |
| 7 | Cumulative goals | Q-GEO-015 | B race | existing |
| 8 | Distinct nationalities per year | Q-GEO-010 | plain bar | existing + **tooltip breakdown** (slice A) |

Panel count **8** — still within sub-wing heuristic (5–9). *(Distinct nationalities stays last as scene-wide capstone.)*

---

## 7. Time travel (inherited rules)

Per [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) §8 and [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md):

1. API resolves `as=` → cutoff `tournament_id`.
2. Year bars read facts **`WHERE tournament_id = cutoff`** — last bar may be **partial calendar year**.
3. Race lines read snapshot series **`event_chrono <= cutoff`**.
4. Country pickers: `available_keys` from facts at cutoff only.
5. Tooltip / hover oracles **must** share the same cutoff boundary as the scan that wrote the bar.

---

## 8. Catalog & registry

Add to [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) when shipping:

| ID | Question | Lens | Storage class |
|----|----------|------|---------------|
| Q-GEO-016 | Nationality *N* active players per year? | L1 bar | S2 year fact |
| Q-GEO-017 | Cumulative distinct players by nationality *N*? | L2 multi-line | S2 all_time fact |
| Q-GEO-018 | Nationality *N* player debuts per year? | L1 bar | S2 year fact |

**Registry file:** `scripts/amiga/community_stat_registry.py` — add specs; mirror logic in PHP scan (no separate PHP registry list today).

**Post-game / finalize:** [`site/public_html/amiga/ops/modules/finalize_tournament.php`](site/public_html/amiga/ops/modules/finalize_tournament.php) → `amiga_community_persist_for_tournament()` — no new finalize hook beyond scan output.

---

## 9. Verify & backfill

| Step | Command / module |
|------|------------------|
| Backfill all snapshots | `python -m scripts.amiga prove` on `ko2amiga_db` |
| Stored vs Python oracle | `verify_community_stats` (in prove) |
| PHP vs Python build | `verify_php_community_parity` (in prove) |
| Staging | `scripts\export_ko2amiga_db.ps1` → WinSCP → import |

**Regression focus after changes:** sample cutoffs including **mid-year** event; compare England/Germany active counts vs manual oracle; race line endpoint of cumulative roster = all_time active_players fact at last visible snapshot.

---

## 10. Non-goals (this track)

- Month-level nationality actives
- “Returning player” / gap semantics
- Nationality changes mid-career (assume static `amiga_players.country`; document if ground data ever contradicts)
- WC-only nationality player slices (separate catalog row if ever requested)
- Online ladder / `ratedresults` — Amiga realm only

---

## 11. File map (quick reference)

| Area | Files |
|------|-------|
| Registry | `scripts/amiga/community_stat_registry.py` |
| Python scan | `scripts/amiga/community_stat_facts.py` |
| PHP scan | `site/public_html/amiga/ops/includes/amiga_community_realm_scan_lib.php` |
| Persist | `site/public_html/amiga/ops/includes/amiga_community_stats_lib.php` |
| Read lib | `site/public_html/includes/amiga_community_stats_lib.php` |
| APIs | `api/amiga_community_year_facts.php`, `api/amiga_community_slice_series.php` |
| UI | `includes/amiga_activity_geography_nations_panels.inc.php`, `js/amiga-activity-charts.js` |