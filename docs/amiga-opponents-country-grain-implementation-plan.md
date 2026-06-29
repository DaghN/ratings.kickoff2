# Amiga Opponents — country grain implementation plan

**Status:** **Ready to execute** (Jun 2026). Policy locked; code not started.

**Policy:** [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) (**OCG1–OCG13**)

**Parent:** [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md)

**Execution:** Slices **OCG-1 → OCG-7** in order. Run each slice **Verification** before continuing. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

**Migration:** **L0** — read-time PHP only; no DDL, no finalize writers, no Part B registers.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**OCG1–OCG13**), data semantics, UI rules |
| **This plan** | File-level tasks, STOP gates, reference files, slice order |
| **Starter prompt** | Optional — use policy §13 + slice id in chat |

---

## How to use this plan

1. Execute slices **OCG-1 → OCG-7** in order (**OCG-0** = policy + plan — done).
2. **One slice per session** unless Dagh asks to batch.
3. **STOP** if roll-up parity fails on spot-check player (id **73** or Denmark/Sweden bucket).
4. **STOP** if grain toggle drops `as=` or wrong wing on toggle.
5. Wire **time travel in OCG-1 load lib** — do not ship present-only country rows and bolt TT on later.
6. After **OCG-7**: [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A (policy session log, MEMORY, feature-log L0 row).

---

## Locked decisions (do not re-open without user)

See policy **OCG1–OCG13**. Compressed:

- Country grain = folder `opponents/country/*` — **not** query-param navigation
- Grain segment **vs Player · vs Country** on **same row as wing tabs**, wings left, grain right, `--k2-nav-gap` between tracks
- Default grain **player**; Opponents pill unchanged
- Roll-up from pair matchup tables; country **Perf.** read-time TPR — **not stored**
- Country tables: **no Elo column**; Games → hero games with `opp_country=`
- H2H country: **omit rating + rank comparison charts** only
- **Online** — out of scope

---

## Reference implementation (copy patterns)

| Area | Reference |
|------|-----------|
| Policy + column map | [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) §4–§7 |
| Pair row load (present + TT) | [`amiga_player_opponents_load.php`](../site/public_html/includes/amiga_player_opponents_load.php) → `amiga_matchup_snapshot_lib.php` |
| Player grain tables | [`amiga_player_opponents_tables.php`](../site/public_html/includes/amiga_player_opponents_tables.php) |
| Player grain H2H | [`amiga_player_opponents_h2h.php`](../site/public_html/includes/amiga_player_opponents_h2h.php) |
| Page shell | [`amiga_player_opponents_page.php`](../site/public_html/includes/amiga_player_opponents_page.php) |
| Nav (Pattern B) | [`amiga_player_opponents_nav.php`](../site/public_html/includes/amiga_player_opponents_nav.php) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) |
| Nested mode URLs | [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) — `tournament/videos/*` row |
| Country token SQL | `amiga_countries_token_sql()` in [`amiga_countries_lib.php`](../site/public_html/includes/amiga_countries_lib.php) |
| Games filter by opp country | [`amiga_player_games_lib.php`](../site/public_html/includes/amiga_player_games_lib.php) — `opp_country` param |
| Read-time TPR from filtered games | [`amiga_player_games_perf_lib.php`](../site/public_html/includes/amiga_player_games_perf_lib.php) |
| Country table cell | `k2_amiga_lb_country_cell()` in [`k2_amiga_country_flag.php`](../site/public_html/includes/k2_amiga_country_flag.php) |
| H2H chart shell | [`player_opponents_h2h_charts.php`](../site/public_html/includes/player_opponents_h2h_charts.php) — hide rating/rank blocks for country grain |
| Pair game rows at cutoff | [`amiga_player_h2h_pair_lib.php`](../site/public_html/includes/amiga_player_h2h_pair_lib.php) |
| k2-table stack | [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **OCG-0** | Policy + this plan | Dagh OK — **done** |
| **OCG-1** | Routes, grain-aware href lib, country roll-up + TPR load lib | PHP probe: player 73 roll-up games sum = pair sum; early `as=` ≤ present |
| **OCG-2** | Dual-segment nav + `country/` thin entries + shell branch (placeholder OK) | Browser: toggle preserves wing + `as=`; both grains show nav row |
| **OCG-3** | Country **W/D/L** table (Perf., games links, column help) | Browser: id=73 country W/D/L; Games → games tab `opp_country=`; Perf. sane |
| **OCG-4** | Country **Goals** + **DDs** tables | Browser: extremes = MAX not SUM; sort works |
| **OCG-5** | Country **H2H** — pickers, poster, detail, all-games link (no charts) | Browser: pick country; poster W/D/L matches table bucket |
| **OCG-6** | Country H2H — moments + game charts (no rating/rank) | Charts load for `country=`; rating/rank sections absent |
| **OCG-7** | Audit + docs closure | `audit_k2_table_compliance.py` on country table pages; MEMORY updated |

---

## OCG-1 — Data layer + routes (no UI)

### Goal

All URL and data primitives exist before nav/tables. No visible page change required.

### Tasks

- [ ] **`k2_amiga_routes.php`:** add four routes:
  - `amiga-player-opponents-country-h2h` → `amiga/player/opponents/country/h2h.php`
  - `amiga-player-opponents-country-wdl` → `…/country/wdl.php`
  - `amiga-player-opponents-country-goals` → `…/country/goals.php`
  - `amiga-player-opponents-country-dds` → `…/country/dds.php`
- [ ] **`amiga_player_opponents_lib.php`:**
  - `K2_AMIGA_PLAYER_OPPONENTS_GRAINS = ['player', 'country']`
  - Extend route map with country keys (mirror player keys under `country/` path).
  - `amiga_player_opponents_parse_grain(?string $raw): string` — default `player`.
  - `amiga_player_opponents_grain_from_script(): string` — return `country` when script path contains `/opponents/country/`.
  - Extend `amiga_player_opponents_href($playerId, $view, $grain = 'player', ?int $opponentId = null, ?string $countryToken = null)`:
    - Select route key by view + grain.
    - Player H2H: `opponent` param when set.
    - Country H2H: `country` param when set (normalized token).
  - `amiga_player_opponents_games_filtered_by_country_href(int $playerId, string $countryToken): string` — `amiga-player-games` with `opp_country` + `#matching-games`; preserve `as=`.
- [ ] **`includes/amiga_player_opponents_country_load.php`** (new):
  - `amiga_player_opponents_country_token_sql(string $playerAlias = 'p'): string` — delegate to `amiga_countries_token_sql()`.
  - `amiga_player_opponents_normalize_country_row(array $row): array` — mirror player normalize shape but `country_token` key; map dd/cs column names for table renderers.
  - **Present path:** optional SQL `GROUP BY` on summary + join, **or** (preferred v1) PHP roll-up after `amiga_player_opponents_matchup_rows()` — group by `opponent_country` token with SUM/MAX rules from policy §4.2. Keep v1 simple; optimize to SQL only if profiling shows need.
  - **TT path:** same roll-up on at-event rows from existing snapshot lib — **do not** scan `amiga_games` for table scalars.
  - `amiga_player_opponents_country_rows(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx): array` — sorted by games DESC, name ASC tiebreak.
  - `amiga_player_opponents_country_bucket(mysqli $con, int $playerId, string $countryToken, ?AmigaSnapshotContext $ctx): ?array` — single bucket for H2H.
- [ ] **`includes/amiga_player_opponents_country_perf_lib.php`** (new):
  - `amiga_player_country_matchup_performance_rating(mysqli $con, int $playerId, string $countryToken, ?AmigaSnapshotContext $ctx): array` — same return shape as games perf API (`games`, `performance_rating`, `reason`).
  - Implementation: reuse `amiga_games_where_clause()` with only `opp_country` (+ hero id); collect pairs from rated games ≤ cutoff; `amiga_performance_rating_from_pairs()`.
  - `amiga_player_opponents_country_perf_ratings_batch(mysqli $con, int $playerId, list<string> $countryTokens, ?AmigaSnapshotContext $ctx): array` — map token → perf result; **one games query** joined to opponent country, bucket pairs in PHP, solve per token (avoids N queries on W/D/L table).

### Verification

```powershell
# From repo root — PHP one-liner or scripts/oneoff/amiga_opponents_country_rollup_probe.php (add if useful)

# SQL parity (player 73 vs Sweden example — adjust token if needed):
# SUM(m.games) FROM matchup_summary m JOIN players p ON p.id=m.opponent_id
#   WHERE m.player_id=73 AND token(p)=Sweden
# must equal bucket games from amiga_player_opponents_country_rows() for Sweden.

# Time travel: pick early as= on a long-career player — bucket games ≤ present row count.
```

**STOP** if additive roll-up disagrees with manual pair sum for two spot-check buckets (cross-border + own-country).

---

## OCG-2 — Nav, entries, shell branch

### Goal

User can switch grain and wing; country pages load shell with placeholder body.

### Tasks

- [ ] **Thin entries** (four files):
  - `site/public_html/amiga/player/opponents/country/h2h.php`
  - `…/country/wdl.php`, `goals.php`, `dds.php`
  - Each sets `$k2AmigaPlayerOpponentsGrain = 'country'` and `$k2AmigaPlayerOpponentsView`, then `require` page shell.
- [ ] **`amiga_player_opponents_nav.php`:**
  - Accept `$k2AmigaPlayerOpponentsGrain` (default `player`).
  - Wrap wing + grain bars in `.k2-player-opponents__nav-row`.
  - Wing links: `amiga_player_opponents_href($id, $viewId, $grain)`.
  - Grain links: same `$view`, toggle grain only.
  - `aria-label` on grain nav: e.g. *Opponent grouping*.
- [ ] **`theme.css`** (under `.k2-player-opponents` block):
  - `.k2-player-opponents__nav-row` — flex row, align center, wrap, gap `var(--k2-nav-gap)`.
  - `.k2-player-opponents__wings`, `.k2-player-opponents__grain` — `margin: 0`; inner bars `width: fit-content`.
  - Preserve H2H `20px` nav margin rule when `.k2-player-opponents-h2h` present.
- [ ] **`amiga_player_opponents_page.php`:**
  - Parse grain from entry var or script path.
  - Pass grain into nav include.
  - Branch render: country grain → temporary `<p class="k2-hub-page-intro">Country grain — W/D/L slice next.</p>` until OCG-3+.

### Verification

Browser on `http://ratingskickoff.test/amiga/player/opponents/wdl.php?id=73`:

- Wing tabs work; **vs Country** → `…/country/wdl.php?id=73`.
- From country W/D/L, **vs Player** returns player W/D/L.
- With `?as=` active, all toggle links keep cutoff.

**STOP** if grain segment stacks below wings on desktop at default site width (fix flex/gap before tables).

---

## OCG-3 — Country W/D/L table

### Goal

First shippable country-grain surface.

### Tasks

- [ ] **`includes/amiga_player_opponents_country_tables.php`** (new) — or dedicated W/D/L section if file stays small:
  - `amiga_player_opponents_render_country_wdl_table($con, $playerId, $ctx)`.
  - Columns: **Country** · **Games** · W · D · L · ratios · **Perf.**
  - **No Elo column** — re-anchor k2-table default sort column index (Games column shifts left vs player table).
  - Country cell: `k2_amiga_lb_country_cell($token)`.
  - Games cell: link via `amiga_player_opponents_games_filtered_by_country_href()`.
  - Perf.: batch helper from OCG-1; display via `performance_rating.php` format helpers (∞ / dash).
- [ ] **`lb_column_help.php`:** tooltips for country W/D/L where labels differ (e.g. *players from this country* on Country column).
- [ ] Wire shell branch: country + `wdl` → render country W/D/L table.
- [ ] Empty state: no rows → short intro line (no table).

### Verification

- `…/country/wdl.php?id=73` — table rows ~6–20; default sort Games ↓.
- Click Games on Sweden (or top row) → games tab with `opp_country` set.
- Compare one bucket to manual SQL sum (OCG-1 probe).
- Time travel: row games monotonic ≤ present.

Run `python scripts/audit_k2_table_compliance.py` on country W/D/L path if script supports path arg (note in OCG-7 if not).

---

## OCG-4 — Country Goals + DDs tables

### Goal

Complete ledger wings for country grain.

### Tasks

- [ ] **Goals table:** same row source; goal extremes from rolled-up **MAX** fields; reuse ratio / TG/g math from player grain where applicable.
- [ ] **DDs table:** summed dd/cs counts; ratios ÷ bucket games — mirror player grain column order minus Elo.
- [ ] Shell branches for `goals` and `dds` views.
- [ ] Column help for any country-specific wording.

### Verification

- Goals: `max_goals_for` for a country ≤ max of any constituent pair (spot-check).
- DDs: ratios match manual `count/games`.
- k2-table client sort on Country name and Games.

---

## OCG-5 — Country H2H (poster + pickers, no charts)

### Goal

Country drill-down parity with player H2H minus charts.

### Tasks

- [ ] **`includes/amiga_player_opponents_country_h2h.php`** (new):
  - `amiga_player_opponents_h2h_parse_country_param(mixed $raw): string` — normalize via `amiga_countries_normalize_country_param()` or shared helper; empty = no selection.
  - `amiga_player_opponents_render_country_h2h_panel(...)` — parallel to player panel.
  - **Pickers:** two listboxes only (games desc · A–Z); options from `amiga_player_opponents_country_rows()`; values = country token strings.
  - **Default opponent country:** when `country` param absent, use top bucket for **display** only (mirror player H2H — URL without param until user picks).
  - **Poster:** reuse `player_opponents_render_h2h_poster()` only if adaptable; otherwise add `player_opponents_render_h2h_country_poster()` — hero card + **country card** (flag, token, roster link) + centre W/D/L from bucket.
  - **Pair detail:** W/D/L + country Perf. (read-time).
  - **All games link:** hero games `opp_country=` filter.
- [ ] Listbox render: extend or duplicate `k2_h2h_opponent_listbox_render` for token labels (flag + country name sort).
- [ ] Shell: country + `h2h` → country H2H panel.
- [ ] **Do not** call `player_opponents_render_h2h_matchup_charts()` in this slice.

### Verification

- `…/country/h2h.php?id=73` — pickers populated; select country → poster record matches W/D/L table row for same token.
- `?country=Denmark` deep link works when hero faced Denmark.
- No player search box present.

---

## OCG-6 — Country H2H depth (moments + game charts)

### Goal

Game-level texture filtered by opponent country; **exclude** rating/rank compare.

### Tasks

- [ ] **Moments grid:**
  - New helper `amiga_player_h2h_country_games_rows($con, $playerId, $countryToken, $ctx)` — filter pair games where opponent nationality = token (reuse games WHERE + country join).
  - Feed `player_opponents_h2h_moments_slots()` with hero name + country label as “opponent name”.
- [ ] **Charts — PHP:**
  - Add `player_opponents_render_h2h_country_matchup_charts()` — copy structure from `player_opponents_render_h2h_matchup_charts()` but **omit** rating comparison + rank comparison sections entirely (not hidden — not in DOM).
  - Include: cumulative H2H wins, cumulative goals, goals-per-game histogram, total-goals histogram, scoreline heatmap.
- [ ] **Charts — API/JS:**
  - Extend Amiga branches to accept **`opp_country`** (or dedicated country endpoints) on:
    - `api/player_head_to_head.php`
    - `api/player_head_to_head_goals.php` (if separate)
    - `api/player_h2h_total_goals_distribution.php`
    - `api/player_goals_scored_distribution.php`
    - `api/player_h2h_scoreline_heatmap.php`
  - Prefer **one** shared lib function e.g. `amiga_player_h2h_cumulative_by_country_payload()` rather than duplicating pair logic.
  - Update chart JS fetch URLs when root has `data-h2h-grain="country"` and `data-chart-country="…"` on `.k2-player-opponents-h2h` (extend [`player-opponents-h2h-chart-context.js`](../site/public_html/js/player-opponents-h2h-chart-context.js)).
  - **Do not** wire `player_compare_rating_history.php` / `player_compare_rank_history.php` for country grain.

### Verification

- Select country with ≥10 games — cumulative H2H chart loads.
- Rating/rank chart headings **not** in page source for country H2H.
- Moments show real games vs nationals from that country only.
- `as=` cutoff respected on APIs.

**STOP** if chart APIs require scanning unbounded games without cutoff filter.

---

## OCG-7 — Closure

### Tasks

- [ ] `python scripts/audit_k2_table_compliance.py` — country W/D/L, Goals, DDs pages.
- [ ] Policy session log + this plan slice checkboxes.
- [ ] [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A: `PROJECT_MEMORY.md`, [`feature-log.md`](coordination/feature-log.md) (L0 UI row), policy status → **In progress** / partial shipped notes per slice.
- [ ] Confirm [`url-routes.md`](url-routes.md) route table matches registered keys (already drafted — verify at ship).

### Browser smoke script (manual)

```text
1. /amiga/player/opponents/h2h.php?id=73 — player grain unchanged
2. Toggle vs Country — same wing, country path
3. country/wdl.php?id=73 — table + perf
4. country/h2h.php?id=73&country=… — poster + charts (no rating/rank)
5. Repeat one URL with ?as=<early cutoff>
```

---

## Risk register

| Risk | Mitigation |
|------|------------|
| PHP roll-up slow at scale | v1 ~30 pairs/player — OK; SQL GROUP BY fallback documented in OCG-1 |
| Perf. N+1 on W/D/L | Batch perf helper in OCG-1 |
| Poster CSS assumes two players | Country card variant in OCG-5; do not fake second player profile |
| Chart JS hardcodes `opponent=` | Explicit `data-h2h-grain` + API `opp_country` in OCG-6 |
| Own-country row confuses users | Tooltip on Country column (*includes games vs compatriots*) |
| Online opponents lib drift | Touch **Amiga includes only**; no `player_opponents_page.php` change |

---

## Out of scope (reminder)

Persisted `amiga_player_country_matchup_*` tables · online port · country-vs-country Rivals · realm-wide LBs · average opp Elo column · H2H player search in country grain.

---

## Environment

| Tool | Path / command |
|------|----------------|
| Local site | `http://ratingskickoff.test/amiga/player/opponents/…` |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` — DB `ko2amiga_db` |
| Prove | Not required (no writers) — optional sanity: `python -m scripts.amiga verify-player-matchups` unchanged |

---

## Session log

| Date | Note |
|------|------|
| Jun 2026 | Implementation plan locked — slices OCG-1–OCG-7. |