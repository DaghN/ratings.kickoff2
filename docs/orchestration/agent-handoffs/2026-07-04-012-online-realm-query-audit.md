# Online realm query audit — 2026-07-04

**Track:** Online read-path performance census (audit-only) · **Realm:** `ko2unity_db` local · **Status:** Phase 1–3 complete — no query fixes shipped

**Starter:** [`2026-07-04-012-online-realm-query-audit-STARTER-PROMPT.md`](2026-07-04-012-online-realm-query-audit-STARTER-PROMPT.md)

---

## Executive summary

Probed **65** canonical online pages + JSON APIs on `http://ratingskickoff.test` (present-mode default; Status period APIs also tested with heavy year **2021**).

| Metric | Count |
|--------|------:|
| Paths probed | 65 |
| Feel **Heavy** (>0.70 s) | 5 |
| Flagged **>0.8 s** | 4 |
| Feel Instant (≤0.25 s) | 49 |
| HTTP errors (real) | 0 |
| Probe fixture errors | 2 (wrong API param — see below) |

**Top feel pain points:** Games All vault and busy-player Games tab dominate server time (~4 s and ~3.3 s curl). Both are **SQL facet waterfalls**, not pagination row fetch. Activity chart API `server_play_texture.php` is a live **full `ratedresults` GROUP BY** (~1.9 s curl / ~1.3 s SQL). Games Highlights and Status year-period APIs are secondary (~0.7–1.0 s).

Most leaderboards, Activity shell, Milestones, and stored-truth chart APIs are **Instant** (≤0.25 s) — online stored-truth investment on Activity charts paid off.

**Artifacts**

- Census probe: [`scripts/oneoff/online_realm_full_census_probe.php`](../../../scripts/oneoff/online_realm_full_census_probe.php)
- Ranked results: [`scripts/oneoff/online_realm_full_census_results.md`](../../../scripts/oneoff/online_realm_full_census_results.md)
- Hot-path lib probe: [`scripts/oneoff/online_realm_hot_path_probe.php`](../../../scripts/oneoff/online_realm_hot_path_probe.php)

**Fixtures:** busy player **537** (geo4444, 11087 games) · small **375** (5 games) · opponent **433** · game **74879** · milestone **absurd_day** · day **2026-07-04** · heavy year **2021**

---

## Top 5 slowest paths (curl total)

| Rank | Time (s) | Feel | Path |
|------|----------|------|------|
| 1 | **3.935** | Heavy | `/games/all.php` |
| 2 | **3.256** | Heavy | `/player/games.php?id=537` |
| 3 | **1.915** | Heavy | `/api/server_play_texture.php?realm=online` |
| 4 | **0.987** | Heavy | `/games/highlights.php` |
| 5 | **0.739** | Heavy | `/api/server_period_activity_leaderboard.php?period=day&key=2026-07-04` @ heavy year variant |

Paths **>0.8 s:** **4** (rows 1–4 above).

---

## Full ranked census

See [`scripts/oneoff/online_realm_full_census_results.md`](../../../scripts/oneoff/online_realm_full_census_results.md) — sorted worst-first with feel tiers (Instant ≤0.25 · Smooth ≤0.40 · Noticeable 0.40–0.70 · Heavy >0.70).

**Probe notes**

- `player_compare_rating_history.php` and `player_compare_rank_history.php` returned HTTP 400 in census — probe used `compare=` but APIs expect **`opponent=`**. Not a production bug; re-probe with `opponent=433` if adding to regression set.
- `activity.php` shell is **0.059 s** — chart cost is **client-side waterfall** (12 `api/server_*.php` calls after load); individual APIs are Instant except `server_play_texture.php`.

---

## Hot-path lib breakdown (SQL ms)

From [`online_realm_hot_path_probe.php`](../../../scripts/oneoff/online_realm_hot_path_probe.php) on cold CLI (no opcache warm parity to curl, but shape is authoritative):

### `/games/all.php` — **SQL-primary**

| Call | ms | Notes |
|------|---:|-------|
| `k2_realm_games_load_score_line_filter_facets` | **2816** | Dominates page; multiple DISTINCT scans on wide `ratedresults` |
| `k2_realm_games_all_fetch_years` | 23 | Minor |
| `k2_realm_games_all_count` | 0.5 | Cheap (indexed) |
| `k2_realm_games_all_fetch_page` | 9 | 250 rows — not the bottleneck |

**Classification:** SQL · anti-pattern **facet waterfall** (same family as Amiga Track M).

### `/player/games.php?id=537` — **SQL-primary**

| Call | ms | Notes |
|------|---:|-------|
| `k2_player_games_validate_filters_career_wide` | **1029** | Career-wide validate scans |
| `k2_player_games_load_filter_facets` | **1296** | Facet bundle |
| COUNT + fetch page | ~150 each | Acceptable once facets fixed |

**Classification:** SQL · duplicate facet/validate passes on `ratedresults` (Amiga player games Track M pattern applies directly).

### `/api/server_play_texture.php` — **SQL-only**

| Call | ms | Notes |
|------|---:|-------|
| Full-table `GROUP BY` month | **1291** | Inline SQL in API file |

**Classification:** SQL · live aggregate over ~75k wide rows. **Deferred stored-truth** candidate (see below); read-time fix = request cache only.

### `/games/highlights.php` — **SQL-primary**

| Call | ms | Notes |
|------|---:|-------|
| `k2_games_highlights_fetch` | **899** | ORDER BY spectacle + LIMIT 100 |
| `k2_games_hub_status_counts` | 14 | Fine |

**Classification:** SQL · wide sort scan (Amiga Track L Highlights inner-LIMIT pattern).

### `/hall-of-fame.php` — **SQL + HTML**

| Call | ms | Curl |
|------|---:|-----:|
| `records_load_career_celebration_leaders` | **435** | 0.689 s total |
| Other HoF loads | <50 each | — |

**Classification:** Mixed — SQL ~435 ms + large static HTML table render (~250 ms gap). Not a single scan; many sequential reads.

### Status period APIs (year 2021) — **SQL**

| Call | ms |
|------|---:|
| `k2_period_activity_leaderboard_entries` | 329 |
| `k2_status_league_for_key` | 341 |

**Classification:** SQL · year-wide `ratedresults` aggregation on tab switch (day key = Instant 0.013–0.030 s).

### `/player/profile.php?id=537` — **SQL + client**

| Call | ms | Notes |
|------|---:|-------|
| `player_feast_load_pm` | **319** | Many sub-queries in feast bundle |
| Chart JS (4 APIs) | — | Client waterfall after PHP |

**Classification:** Mixed — PHP feast ~320 ms; charts load async (not in census curl beyond initial HTML).

### Other probed suspects

| Path | SQL ms | Classification |
|------|-------:|----------------|
| `k2_lb_peak_rating_context_payload` | 278 | SQL · peak window on `ratedresults` |
| `k2_status_load_room` | 271 | SQL · multiple panel queries (acceptable) |

---

## SQL vs HTML vs client — flagged pages

| Path | Primary lever | Secondary |
|------|---------------|-----------|
| `/games/all.php` | **SQL** (score-line facets) | HTML 250 rows minor |
| `/player/games.php?id=537` | **SQL** (validate + facets) | HTML 500 rows minor |
| `/api/server_play_texture.php` | **SQL** (full scan) | — |
| `/games/highlights.php` | **SQL** (sort scan) | HTML 100 rows minor |
| `/hall-of-fame.php` | **SQL** (career leaders) + **HTML** (long table) | — |
| `/status.php` | **SQL** (~270 ms room load) | Client period tab APIs |
| `/activity.php` | **Client** (12 chart fetches) | PHP shell instant |
| `/player/profile.php?id=537` | **SQL** (feast) + **Client** (4 charts) | — |
| Status year period APIs | **SQL** | — |

---

## Recommended read-time fix batches

Read-time PHP/SQL only — **no schema, no ops, no new aggregate tables** in these tracks.

| Batch | Scope | Pattern | Est. ROI | Parity oracle |
|-------|-------|---------|----------|---------------|
| **O1** | `/games/all.php` score-line facet stack | Request-scoped facet bundle + dedupe DISTINCT scans (pattern D); mirror Amiga games facet dedupe | **Very high** (~2.8 s → target <0.4 s) | Row counts + facet choice parity vs current |
| **O2** | `/player/games.php` busy player | Port Amiga Track M: `validate_filters_career_wide` + `load_filter_facets` single bundle/cache | **Very high** (~2.3 s → target <0.5 s) | Facet listbox + filtered row-set parity |
| **O3** | `server_play_texture.php` | Request cache keyed `realm=online` (TTL = request); optional read from existing monthly panel source if already computed elsewhere | **High** (~1.3 s → <50 ms warm) | JSON month series byte parity |
| **O4** | `/games/highlights.php` | Inner LIMIT subquery before wide projection (Amiga Track L Highlights) | **Medium** (~0.9 s → target <0.15 s) | Board row IDs + sort order |
| **O5** | Status period year/month APIs | Request cache per `(period,key)`; dedupe activity + points league shared scan | **Medium** (~0.34 s → <0.10 s warm) | Leaderboard row parity |
| **O6** | HoF `records_load_career_celebration_leaders` | Narrow reads / batch player name fetch; avoid redundant playertable hits | **Low–medium** | HoF row text parity |
| **O7** | `player_feast_load_pm` busy players | Audit sub-loads in feast helpers; request cache for repeated playertable joins | **Low–medium** | Profile block parity |
| **O8** | `lb_peak_rating_context.php` | Indexed peak-game window read (id range around `PeakRatingGameID`) | **Low** | 11-game context payload parity |

**Suggested pick order for Dagh:** O1 → O2 → O3 → O4 (Games hub pain) → O5 (Status UX) → O6–O8 as polish.

---

## Explicitly deferred (stored-truth / schema — not this audit)

One-line each — **do not implement from this handoff without separate contract slice:**

- **`server_play_texture` monthly metrics** — natural fit for `server_activity_chart_panels` stored series (like daily active players); needs contract + ops writer, not read-time alone at scale.
- **Games All / Player games facet precomputation** — per-player or realm-wide facet snapshots would be stored truth on `ratedresults` churn.
- **Highlights boards materialized top-100** — periodic snapshot per board type.
- **Status year aggregation table** — if year tab stays hot after O5 cache insufficient.
- **New indexes on `ratedresults`** — ops migration 001 already has idA/idB; further DDL = Steve/cutover register.

---

## Verification commands

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_full_census_probe.php --out=scripts\oneoff\online_realm_full_census_results.md

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_hot_path_probe.php
```

---

## Blockers

None — Laragon up, `ko2unity_db` fixtures resolved, census + hot-path probes green.