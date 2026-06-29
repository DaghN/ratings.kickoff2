# Amiga country Rivals — nation-pair implementation plan

**Status:** **Shipped** (Jun 2026). Slices **CRV-1–CRV-7** complete.

**Policy:** [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) (**CRV1–CRV16**)

**Parent:** [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-opponents-country-grain-implementation-plan.md`](amiga-opponents-country-grain-implementation-plan.md) (sibling — copy patterns)

**Execution:** Slices **CRV-1 → CRV-7** in order. Run each slice **Verification** before continuing. **Do not git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

**Migration:** **L0** — read-time PHP only; no DDL, no finalize writers, no Part B registers.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**CRV1–CRV16**), data semantics, UI rules |
| **This plan** | File-level tasks, STOP gates, reference files, slice order |
| **Starter prompt** | Optional — use policy §13 + slice id in chat |

---

## How to use this plan

1. Execute slices **CRV-1 → CRV-7** in order (**CRV-0** = policy + plan — done).
2. **One slice per session** unless Dagh asks to batch.
3. **STOP** if roll-up parity fails on **Denmark→Sweden** or **Germany→England** spot checks.
4. **STOP** if wing nav drops `country=` or `as=`.
5. Wire **time travel in CRV-1 load lib** — do not ship present-only rows and bolt TT on later.
6. After **CRV-7**: [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A (policy session log, MEMORY, feature-log L0 row).

---

## Locked decisions (do not re-open without user)

See policy **CRV1–CRV16**. Compressed:

- Rivals = folder **`country/rivals/*`** — four wings — **not** query-param navigation
- Entity **Roster · Rivals** unchanged; wings are **inside Rivals only**
- Hero = **`country=`**; drill-down = **`rival=`**
- **Directed** A→B grain; domestic A→A **excluded from Rivals UI** (CRV7); H2H default = top cross-border rival (CRV15)
- Second roll-up from **`amiga_player_matchup_summary` / at_event** — **not** games scan for tables
- Nation-pair **Perf.** read-time TPR — **not stored** v1
- H2H: **omit rating + rank comparison charts** only
- Games links → **`/amiga/games/all.php?country=&rival=`**
- **Online** — out of scope

---

## Reference implementation (copy patterns)

| Area | Reference |
|------|-----------|
| Policy + column map | [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) §4–§7 |
| **Player country grain (closest UI)** | [`amiga_player_opponents_country_load.php`](../site/public_html/includes/amiga_player_opponents_country_load.php) · [`amiga_player_opponents_country_tables.php`](../site/public_html/includes/amiga_player_opponents_country_tables.php) · [`amiga_player_opponents_country_h2h.php`](../site/public_html/includes/amiga_player_opponents_country_h2h.php) |
| Pair row load (present + TT) | [`amiga_player_opponents_load.php`](../site/public_html/includes/amiga_player_opponents_load.php) → [`amiga_matchup_snapshot_lib.php`](../site/public_html/includes/amiga_matchup_snapshot_lib.php) |
| Country entity shell | [`amiga_country_page.php`](../site/public_html/includes/amiga_country_page.php) · [`amiga_country_nav.php`](../site/public_html/includes/amiga_country_nav.php) |
| Opponents wing nav (inner row) | [`amiga_player_opponents_nav.php`](../site/public_html/includes/amiga_player_opponents_nav.php) — wings only, no grain |
| Country H2H charts + API grain | [`player_opponents_h2h_charts.php`](../site/public_html/includes/player_opponents_h2h_charts.php) · [`amiga_player_h2h_country_lib.php`](../site/public_html/includes/amiga_player_h2h_country_lib.php) · chart JS context |
| Country token SQL | `amiga_countries_token_sql()` in [`amiga_countries_lib.php`](../site/public_html/includes/amiga_countries_lib.php) |
| Read-time TPR batch | [`amiga_player_opponents_country_perf_lib.php`](../site/public_html/includes/amiga_player_opponents_country_perf_lib.php) |
| k2-table stack | [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) |
| Page structure | [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **CRV-0** | Policy + this plan | Dagh OK — **done** |
| **CRV-1** | Data layer + routes + href helpers + parity probe | Denmark→Sweden parity; TT ≤ present |
| **CRV-2** | Rivals wing nav + thin entries + shell branch (placeholder OK) | Browser: `rivals/wdl.php?country=Germany` shows wing row + hero |
| **CRV-3** | **W/D/L** table + realm games nation-pair filter | Games link filtered; Perf. sane |
| **CRV-4** | **Goals** + **DDs** tables | MAX extremes; sort works |
| **CRV-5** | **H2H** — pickers, poster, detail, all-games link (no charts) | Poster W/D/L = table row |
| **CRV-6** | H2H — moments + game charts (no rating/rank) | Charts load; rival kicker labels |
| **CRV-7** | Audit + docs closure | `audit_k2_table_compliance.py`; MEMORY updated |

---

## CRV-1 — Data layer + routes (no UI)

### Goal

All URL and data primitives exist before nav/tables.

### Tasks

- [ ] **`k2_amiga_routes.php`:** add four routes (`amiga-country-rivals-h2h` … `dds`).
- [ ] **`amiga_countries_lib.php`:**
  - Extend `k2_amiga_country_rivals_href($heroToken, $view = 'h2h', ?string $rivalToken = null)`.
  - Preserve `as=` via context helpers.
- [ ] **New `includes/amiga_country_rivals_load.php`:**
  - `amiga_country_rivals_normalize_token()` — delegate to H8 helpers.
  - `amiga_country_rivals_empty_bucket(string $rivalToken): array` — mirror country grain bucket shape.
  - `amiga_country_rivals_rollup_from_pair_rows(array $pairRows, string $heroCountry): array` — group directed player pairs by `(hero_country, rival_country)` with SUM/MAX rules (policy §4.2).
  - `amiga_country_rivals_rows(mysqli $con, string $heroCountry, ?AmigaSnapshotContext $ctx): array` — present + TT via matchup snapshot lib, then roll-up.
  - `amiga_country_rivals_bucket(mysqli $con, string $heroCountry, string $rivalCountry, ?AmigaSnapshotContext $ctx): ?array`.
- [ ] **New `includes/amiga_country_rivals_perf_lib.php`:**
  - Batch nation-pair TPR for W/D/L rows (mirror country perf lib; filter games hero ∈ A, opp ∈ B).
- [ ] **Probe script** (optional): `scripts/oneoff/amiga_country_rivals_rollup_probe.php` — parity Denmark→Sweden, domestic DK→DK note.

### Verification

```sql
-- Directed DK→SE from matchup roll-up (conceptual)
SELECT SUM(m.games), SUM(m.wins), SUM(m.draws), SUM(m.losses)
FROM amiga_player_matchup_summary m
JOIN amiga_players h ON h.id = m.player_id AND TRIM(h.country) = 'Denmark'
JOIN amiga_players o ON o.id = m.opponent_id AND TRIM(o.country) = 'Sweden';
-- Expect: 131 / 40 / 17 / 74 (local Jun 2026)
```

PHP: `amiga_country_rivals_rows($con, 'Germany', $ctx)` returns ~15–20 rival rows; sum of games ≥ cross-border count.

**STOP** if additive roll-up disagrees with manual pair sum for two spot-check pairs (cross-border + domestic).

---

## CRV-2 — Shell + wing nav + thin entries

### Goal

Rivals segment shows four-wing row; placeholder body per wing OK.

### Tasks

- [ ] Create **`amiga/country/rivals/{h2h,wdl,goals,dds}.php`** thin entries (`$k2AmigaCountryView = 'rivals'`, `$k2AmigaCountryRivalsView = …`).
- [ ] **`includes/amiga_country_rivals_nav.php`** — H2H · W/D/L · Goals · DDs; href via `k2_amiga_country_rivals_href()`.
- [ ] **`includes/amiga_country_page.php`:**
  - When `$k2AmigaCountryView === 'rivals'`: include rivals nav; branch on `$k2AmigaCountryRivalsView`.
  - Replace placeholder paragraph with wing-specific placeholder or table shell include stub.
- [ ] **Redirect:** `amiga/country/rivals.php` → `rivals/h2h.php` (302, preserve `country` + `as=`).
- [ ] Update **`url-routes.md`** route table.
- [ ] Enqueue scripts/CSS only when needed (defer chart JS to CRV-6).

### Verification

- `http://ratingskickoff.test/amiga/country/rivals/wdl.php?country=Germany` — hero + Roster·Rivals + wing row; W/D/L active.
- Toggle Roster ↔ Rivals preserves `country=` and `as=`.
- Wing links preserve `country=` (and `rival=` when present).

---

## CRV-3 — W/D/L table + games filter

### Goal

Sortable W/D/L table of rival countries for hero nation.

### Tasks

- [ ] **`includes/amiga_country_rivals_tables.php`** — W/D/L renderer (mirror country tables; **Rival** column instead of Country).
- [ ] **`lb_column_help.php`** — Rivals W/D/L tooltips (directed grain, domestic double-count on own row).
- [ ] **Realm games filter:** extend [`amiga_realm_games_hub_lib.php`](../site/public_html/includes/amiga_realm_games_hub_lib.php) (or filter facets) — `country` + `rival` directed nation-pair filter on **All games**.
- [ ] **`amiga_country_rivals_games_filtered_href($heroCountry, $rivalCountry)`** helper.
- [ ] Wire shell: rivals + `wdl` → render table; batch perf attach.

### Verification

- `rivals/wdl.php?country=Germany` — table populated; Games link opens filtered all-games list.
- Denmark row: domestic games tooltip mentions double-count if present.
- `audit_k2_table_compliance.py` on rivals W/D/L path (note in CRV-7 if script needs path arg).

---

## CRV-4 — Goals + DDs tables

### Goal

Same k2-table stack; MAX for goal extremes.

### Tasks

- [ ] Goals + DDs renderers in `amiga_country_rivals_tables.php`.
- [ ] Column help for extremes + DD/CS ratios.
- [ ] Wire shell branches for `goals` and `dds` views.
- [ ] Shared empty-state helper.

### Verification

- Extremes columns = MAX across pairs in bucket (spot-check one rival manually).
- DD sum for Germany→England matches roll-up probe.

---

## CRV-5 — H2H core (no charts)

### Goal

Poster, pickers, pair detail, all-games link.

### Tasks

- [ ] **`includes/amiga_country_rivals_h2h.php`:**
  - Parse `rival` param; resolve bucket; listboxes (by games · A–Z).
  - `player_opponents_render_h2h_country_poster` **or** new nation-pair poster (two country cards — no player subject).
  - Pair detail strip (goals, margins, DD, nation-pair perf).
  - All-games link via games filter helper.
- [ ] **`includes/amiga_country_rivals_h2h_games_lib.php`** (or sibling) — directed game rows for moments/charts prep.
- [ ] **`amiga_country_page.php`:** rivals + `h2h` panel; set `data-rival` / chart attrs on root when rival selected.
- [ ] **JS:** listbox navigation appends `rival=` (extend or mirror [`player-opponents-h2h.js`](../site/public_html/js/player-opponents-h2h.js) with `data-h2h-grain="nation-pair"`).

### Verification

- `rivals/h2h.php?country=Germany&rival=England` — poster W/D/L matches W/D/L table row.
- Pickers populated; deep link works.
- No player search box.

---

## CRV-6 — H2H depth (moments + charts)

### Goal

Game-level texture; **no** rating/rank compare.

### Tasks

- [ ] Moments grid — `player_opponents_h2h_moments_slots()` with **rival country kicker** override (e.g. *Italy's best haul*).
- [ ] **`player_opponents_render_h2h_nation_pair_matchup_charts()`** (or rivals-specific) — cumulative wins/goals, histograms, heatmap; **omit** rating/rank blocks from DOM.
- [ ] **API branches:** `country` + `rival` on head-to-head, goals distribution, total goals, scoreline heatmap (realm=amiga).
- [ ] **Chart JS:** extend context for nation-pair grain; games drill-down uses `country` + `rival` on games/all.php.
- [ ] Enqueue chart JS on rivals H2H page only.

### Verification

- Rival with ≥10 games — charts load; status text clears.
- Page source has **no** compare-rating / compare-rank sections.
- `as=` respected on APIs.

**STOP** if chart APIs require unbounded games scan without cutoff filter.

---

## CRV-7 — Closure

### Tasks

- [ ] `python scripts/audit_k2_table_compliance.py` — rivals W/D/L, Goals, DDs paths.
- [ ] Policy session log + this plan slice checkboxes.
- [ ] [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A: `PROJECT_MEMORY.md`, [`feature-log.md`](coordination/feature-log.md), [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §9 status.
- [ ] Confirm [`url-routes.md`](url-routes.md) matches registered keys.

### Browser smoke script (manual)

```text
1. /amiga/country/roster.php?country=Germany — unchanged
2. Rivals wing row on rivals/wdl.php?country=Germany
3. W/D/L table + Games drill-down
4. rivals/h2h.php?country=Germany&rival=England — poster + charts (no rating/rank)
5. Repeat one URL with ?as=<early cutoff>
6. rivals.php legacy URL redirects to rivals/h2h.php
```

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Confused with player country grain | Separate URL namespace `country/rivals/*`; **§1.1 three-grain table** in policy |
| Domestic A→A row | **Excluded** from all four wings + H2H redirect (**CRV7**, **CRV15**) — not a user-facing row |
| Games hub filter scope creep | Directed filter only; no unbounded new facets |
| Chart JS `opponent=` hardcoding | Explicit `data-h2h-grain="nation-pair"` + API `rival=` in CRV-6 |
| Perf batch cost on W/D/L | Batch one query; defer Perf column only if blocked |
| Poster CSS two players | Two **country** cards — copy OCG country poster |

---

## Deferred (post-v1)

- Persisted `amiga_country_matchup_*` + `prove`
- Realm-wide nation-pair leaderboard
- WC history / medal comparison blocks on Rivals H2H
- `rivals.php` hub index without wing (if ever needed)

---

## Session log

| Date | Note |
|------|------|
| Jun 2026 | Plan locked — slices CRV-1–CRV-7; second roll-up data path; games filter in CRV-3. |
| Jun 2026 | **CRV-1–7 shipped** — four wings + H2H charts + games filter. |
| Jun 2026 | **Domestic exclusion** — A→A filtered; H2H default rival redirect to top cross-border pair. |