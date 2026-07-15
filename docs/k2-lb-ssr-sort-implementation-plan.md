# K2 leaderboard server-side sort (SSR) — implementation plan

**Status:** **Complete** (Jul 2026) — slices **0–6** shipped; all hub wings in § Wing register are **Shipped**.  
**Policy:** [`k2-lb-ssr-sort-policy.md`](k2-lb-ssr-sort-policy.md) — **Implemented**  
**Starter prompt:** [`orchestration/agent-handoffs/k2-lb-ssr-sort-STARTER-PROMPT.md`](orchestration/agent-handoffs/k2-lb-ssr-sort-STARTER-PROMPT.md)

---

## Goal

Upgrade all hub **leaderboard wing** pages so `?k2_sort=` / `?k2_dir=` drive **SQL `ORDER BY`** on first paint (Track A). ~**5 tables per slice** for Dagh manual browser QA. **No HoF link rewrites** — verify column parity only.

**Not in this track:** profile mosaic link wiring (Track B — [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md)).

---

## How to use this plan

1. **New chat** — paste starter prompt from `k2-lb-ssr-sort-STARTER-PROMPT.md`.
2. Agent **readback** — Dagh says **go** before coding.
3. **One slice per session** unless Dagh asks for more.
4. End slice: browser QA checklist + UPDATE_DOCS Part A (policy register + MEMORY); **no Part B** (read-time only).
5. **No git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.
6. Handoff (optional): `docs/archive/orchestration/agent-handoffs/YYYY-MM-DD-NNN-k2-lb-ssr-slice-N.md`

---

## Per-wing implementation recipe

**Read first:** nearest shipped wing (`goals.php` or `double-digits.php`).

1. **Default sort column** — note wing `$defaultCol` (existing `k2_lb_table_sort_state($defaultCol)`).
2. **Column map** — add `amiga_lb_*_order_column_map()` / `k2_lb_*_order_column_map()` in the appropriate lib (`amiga_lb_lib.php`, wing lib, or page-local if trivial). Keys = `<th>` index; values = SQL expressions (alias-aware).
3. **Default order clause** — extract or reuse existing default `ORDER BY` (no prefix) as tiebreak.
4. **Wire query** — before fetch:
   - `$lbSort = k2_lb_table_sort_state($defaultCol);`
   - `$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);`
   - Append `'ORDER BY ' . $lbSqlOrder['order_clause']` to the wing's SQL (or pass into helper if query is centralized).
5. **Table attr** — replace `k2_table_skip_initial_sort_attr(...)` with `k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $defaultCol, $defaultDir, $lbSqlOrder['ssr_applied_url_sort'])`.
6. **TT / snapshot paths** — if wing uses `amiga_lb_snapshot_lib.php` or array fetch + PHP loop, apply ORDER BY in **both** present and cutoff branches (or sort in SQL only — prefer SQL).
7. **Policy register** — mark wing **Shipped** in this plan § Wing register + policy §5.
8. **HoF smoke** — hit every `amiga_records_hof_links.php` / `records_hof_links.php` metric targeting this wing (see § HoF parity tables).

**Anti-patterns:**

- `data-k2-skip-initial-sort="1"` always on — breaks default-view emphasis
- Column map index drift vs `<th>` order — breaks HoF deep links
- ORDER BY in PHP after fetch when SQL path exists — parity risk on large wings

---

## Wing register

Status key: **Shipped** (all hub wings — Track A complete Jul 2026)

### Amiga — career hub (`/amiga/leaderboards/`)

| Wing | Page | Default sort col | Lib / query | SSR |
|------|------|------------------|-------------|-----|
| Rating | `rating.php` | 2 (Elo) | `amiga_lb_query_career` | **Shipped** |
| Goals | `goals.php` | 4 (GF) | `amiga_lb_goals_order_column_map()` | **Shipped** |
| Double digits | `double-digits.php` | 4 (DD) | `amiga_lb_double_digits_order_column_map()` | **Shipped** |
| Victims | `victims.php` | 4 (Opponents) | `amiga_lb_query_career` | **Shipped** |
| Peak rating | `peak-rating.php` | 4 (Peak) | `amiga_lb_query_peak_rating()` | **Shipped** |
| Tournament honours | `tournament-honours.php` | 3 (Events) | `amiga_tournament_honours_leaderboard_rows()` | **Shipped** |
| Calendar & geo | `calendar-geo.php` | 3 (Games in year) | `amiga_calendar_geo_leaderboard_rows()` | **Shipped** |
| Perf. rating — Best | `performance-rating/best.php` | per view | `amiga_lb_performance_rating_table.php` | **Shipped** |
| Perf. rating — Top 100 | `performance-rating/top.php` | per view | shared table include | **Shipped** |
| Perf. rating — Perfect | `performance-rating/perfect.php` | per view | shared table include | **Shipped** |

### Amiga — World Cups player stats (`/amiga/world-cups/players/`)

HoF paths via `amiga_records_hof_lb_wing_path()` — **not** `leaderboards/world-cups/` duplicates.

| Wing | Page | SSR |
|------|------|-----|
| Honours | `honours.php` | **Shipped** |
| Results | `results.php` | **Shipped** |
| Goals | `goals.php` | **Shipped** |
| DDs | `dds.php` | **Shipped** |
| Opponents | `opponents.php` | **Shipped** |

Shared table stack: `includes/amiga_wc_players_table.php` — one SSR upgrade may cover all five pages.

### Online — hub (`/leaderboards/`)

| Wing | Page | Default sort col | SSR |
|------|------|------------------|-----|
| Rating | `rating.php` | 2 (Elo) | **Shipped** |
| Goals | `goals.php` | 4 (GF) | **Shipped** |
| Double digits | `double-digits.php` | 4 (DD) | **Shipped** |
| Victims | `victims.php` | 4 (Opponents) | **Shipped** |
| Peak rating | `peak-rating.php` | 4 (Peak) | **Shipped** |
| League honours | `league-honours.php` | 4 (Gold) | **Shipped** |
| Milestones | `milestones.php` | 8 (Count) | **Shipped** |
| Streaks | `streaks.php` | 4 (Win streak) | **Shipped** |
| Activity — Peaks | `activity/peaks.php` | — | **Shipped** |
| Activity — In a row | `activity/in-a-row.php` | 4 (Days) | **Shipped** |
| Activity — Participation | `activity/participation.php` | 3 (Games) | **Shipped** |

---

## HoF parity tables (verify after each wing ships)

### Amiga career — `amiga_records_hof_links.php`

| Metric | Wing | k2_sort | dir |
|--------|------|---------|-----|
| most_games | rating | 4 | desc |
| most_wins | rating | 5 | desc |
| win_ratio | rating | 8 | desc |
| most_goals | goals | 4 | desc |
| attack_avg | goals | 6 | desc |
| defense_avg | goals | 7 | asc |
| goal_ratio | goals | 9 | desc |
| most_dd | double-digits | 4 | desc |
| most_cs | double-digits | 5 | desc |
| dd_ratio | double-digits | 6 | desc |
| cs_ratio | double-digits | 7 | desc |
| most_opponents | victims | 4 | desc |
| most_victims | victims | 5 | desc |
| most_dd_victims | victims | 7 | desc |
| most_cs_victims | victims | 11 | desc |
| peak_rating | peak-rating | 4 | desc |
| most_games_in_year | calendar-geo | 3 | desc |
| most_tournaments_in_year | calendar-geo | 5 | desc |
| most_countries_played_in | calendar-geo | 7 | desc |
| most_opponent_countries_faced | calendar-geo | 8 | desc |
| most_opponent_countries_beaten | calendar-geo | 9 | desc |
| most_tournaments_played | tournament-honours | 3 | desc |
| most_tournament_wins | tournament-honours | 4 | desc |
| most_perfect_events | tournament-honours | 8 | desc |

### Amiga World Cups — same file

| Metric | Wing path key | k2_sort |
|--------|---------------|---------|
| most_wc_played, wc_gold | world-cups | 3–4 |
| wc_games … wc_win_rate | world-cups-results | 4–10 |
| wc_goals_for … wc_goal_ratio | world-cups-goals | 4–10 |
| wc_double_digits … wc_cs_ratio | world-cups-dds | 4–7 |
| wc_opponents … wc_cs_victims | world-cups-opponents | 4–9 |

(Full map: `amiga_records_hof_links.php` — do not duplicate by hand; grep metrics when QAing.)

### Online — `records_hof_links.php`

grep `'page' => 'lb-` in file when QAing online slices — metrics span rating, goals, double-digits, victims, peak-rating, streaks, milestones, league-honours, activity sub-wings.

---

## Slice map

| Slice | Deliverable | Wings (5 max) | STOP gate |
|-------|-------------|---------------|-----------|
| **0** | Doc trio (policy + plan + starter prompt) | — | **Done** |
| **1** | Amiga career SSR batch 1 | victims, peak-rating, tournament-honours, calendar-geo, perf-rating/best | **Done** (code); HoF browser smoke optional per wing |
| **2** | Amiga career SSR batch 2 | perf-rating/top, perf-rating/perfect (+ shared table lib closure) | **Done** |
| **3** | Amiga WC player stats | honours, results, goals, dds, opponents | **Done** |
| **4** | Online core LBs | rating, goals, double-digits, victims, peak-rating | **Done** |
| **5** | Online remainder | league-honours, milestones, streaks, activity/in-a-row, activity/participation | **Done** (code); HoF browser smoke optional |
| **6** | Closure | Policy status → **Implemented**; MEMORY; `python scripts/audit_k2_table_compliance.py` | **Done** — audit PASS (0 Tier C) |

**Track A complete.** No further slices unless a new hub wing ships without SSR.

### Amiga rating LB — fixed column indices (SSR-13)

Always-visible Δ column; constants in `includes/amiga_lb_lib.php`:

| Col | Field |
|-----|--------|
| 3 | Δ (WC-start present · event Δ when `as=`) |
| 4 | Games |
| 5 | Wins |
| 8 | Win rate |
| 9 | Opponent Average |

HoF: `most_games`=4, `most_wins`=5, `win_ratio`=8.

---

## Slice 1 task checklist (template)

- [x] Read policy SSR-1–SSR-13 + copy `goals.php` pattern
- [x] **victims.php** — column map + `amiga_lb_query_career` ORDER BY + skip attr
- [x] **peak-rating.php** — extend `amiga_lb_query_peak_rating()` or equivalent for ORDER BY + TT branch
- [x] **tournament-honours.php** — ORDER BY in row query (`amiga_lb_tournament_honours_order_sql` exists for default)
- [x] **calendar-geo.php** — ORDER BY on `amiga_calendar_geo_leaderboard_rows()` SQL
- [x] **performance-rating/best.php** — shared `amiga_lb_performance_rating_table.php` if possible
- [x] Update wing register (this file) + policy §5
- [x] HoF column parity audit (Jul 2026) — static maps match `<th>` order; optional browser smoke per wing
- [x] UPDATE_DOCS Part A

---

## Verification (every slice)

| Check | Pass criteria |
|-------|----------------|
| Default land | No query params → same order as before slice |
| HoF / deep link | `?k2_sort=N&k2_dir=` → correct top rows, no flash |
| Header click | Different column sorts client-side, no navigation |
| Amiga TT | `&as=` on one constructed URL → sort still correct |
| Include toggles | If wing has provisional/pool toggles, reload preserves sort |
| UTF-8 | No agent `Write` tool on PHP — StrReplace or PS UTF-8 |

**Local URLs:** `work.ratingskickoff.test` (Amiga work DB) · `ratingskickoff.test` (online).

---

## Files (typical touch set)

| Area | Paths |
|------|-------|
| Amiga pages | `site/public_html/amiga/leaderboards/*.php`, `performance-rating/*.php` |
| Amiga libs | `includes/amiga_lb_lib.php`, `amiga_lb_snapshot_lib.php`, `amiga_player_tournament_lib.php`, `amiga_lb_peak_rating_lib.php`, `amiga_lb_performance_rating_table.php`, `amiga_wc_players_table.php` |
| Online pages | `site/public_html/leaderboards/**/*.php` |
| Online libs | `includes/lb_player_filters.php`, wing-specific if any |
| Shared | `includes/k2_table_helpers.php` (use only — rarely edit) |
| Docs | this plan, policy, `PROJECT_MEMORY.md` |
| HoF (verify only) | `amiga_records_hof_links.php`, `records_hof_links.php` |

---

## Environment

- PHP: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` (or current Laragon)
- Amiga work DB: `ko2amiga_work` via Laragon
- Online local: `ko2unity_db` / work per [`LOCAL_DEV.md`](LOCAL_DEV.md)

---

## Agent one-liner

Today: K2 LB SSR Track A — slice N per `k2-lb-ssr-sort-implementation-plan.md`.

---

## Execution log

| Date | Slice | Note |
|------|-------|------|
| 2026-07-15 | 0 | Doc trio + starter prompt |
| 2026-07-15 | 1 | victims, peak-rating, tournament-honours, calendar-geo, perf-rating/best — SSR + column maps |
| 2026-07-15 | 2 | perf-rating/top + perfect — shared table lib closure |
| 2026-07-15 | 3 | Amiga WC player stats — five sub-wings via `amiga_wc_players_table.php` |
| 2026-07-15 | 4 | Online core LBs — rating, goals, double-digits, victims, peak-rating |
| 2026-07-15 | 5 | Online remainder — league-honours, milestones, streaks, activity/in-a-row, participation |
| 2026-07-15 | 6 | Closure — policy Implemented; compliance audit PASS |
| 2026-07-15 | — | Rating LB Δ always visible; `AMIGA_LB_RATING_COL_*`; HoF 4/5/8 stable |