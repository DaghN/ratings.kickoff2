# K2 table entity links policy

**Status:** **Shipped** (Jun 2026). Inline Amiga flags + entity link helpers aligned with this doc.

**Authority:** Product + visual contract; defers to [`design-direction.md`](design-direction.md) for tokens. Table machinery (sort, cloak, mirror, anchor map): [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md). Amiga flag SVG map + roster URLs: [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) CH9. Dagh's latest chat wins on scope.

**For agents:** read this before adding or refactoring **player / tournament / country name links inside table cells**, **calm stat-value links** (`k2-table-cell-link` — § Calm cell links C1), or **inline Amiga country flags** beside those names. Pair with [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) for the table stack and [`k2-tooltip-policy.md`](k2-tooltip-policy.md) for column help.

---

## Purpose

Table cells that name a **player**, **tournament**, or **country** should look and behave the same: **link-star color + weight 600**, clickable, built from **shared PHP helpers** — not ad-hoc `<a>` markup or CSS accidents (anchor column paint, global `td a` catch-all, or `k2-country-roster-link` on text).

**Common agent failure:** add a dedicated **flag-only** Country column, or hand-roll `<a class="k2-country-roster-link">` on country **names** while player names use `k2-link-star` — same visual job, three different code paths.

---

## Locked decisions

| ID | Decision |
|----|----------|
| **E1** | **Entity name links** (readable label) always use **`class="k2-link-star"`** on the `<a>`, via the realm's link helper — never rely on anchor-column cell paint or `.k2-table tbody td a` alone. |
| **E2** | **Three entity types, three name-link helpers** — same contract, different destination: **player** → profile; **tournament** → tournament event stats; **country** → country roster (`#k2-country-roster`). |
| **E3** | **Flag links are not entity name links.** Country **flag images** link to roster via `k2_amiga_country_flag_link()` → `k2-country-roster-link` wrapping the `<img>` only. |
| **E4** | **Amiga inline table cells** — `[flag][name link]` in one column via compositors in `includes/k2_amiga_country_flag.php`. **No dedicated flag-only Country columns** on Amiga tables (migration list §4). |
| **E5** | **Country rows — dual link:** flag link + separate name link, both to the same roster URL (mirrors player inline: flag → nationality roster, name → entity destination). |
| **E6** | **Unmapped country tokens:** omit flag; name link still renders when the token is non-empty. No text fallback where a flag SVG was expected. |
| **C1** | **Calm cell links** — stat values that should **look like normal cell text** at rest use **`k2-table-cell-link`** on the `<a>` (§ Calm cell links below). **Not** bare `<a>` in `td` (global link-star trap). **Not** `k2-link-star` unless the value is an entity name or intentional accent drill-down (Elo, Opponents Games count). |

---

## Name-link helpers (E1–E2)

| Entity | Online | Amiga | Destination |
|--------|--------|-------|-------------|
| **Player** | `k2_player_link()` in `k2_safety.php` | `k2_amiga_player_link()` in `amiga_player_load.php` | Player profile |
| **Tournament** | (surface-specific; prefer `k2-link-star` when added) | `amiga_tournament_link()` in `amiga_tournament_lib.php` | Tournament event stats |
| **Country** | — | `k2_amiga_country_roster_link()` in `k2_amiga_country_flag.php` | `k2_amiga_country_roster_href()` |

All three return `<a class="k2-link-star" href="…">…label…</a>`.

**Do not** use `k2-country-roster-link` on **country name text** in tables — that class is for **flag img wrappers** (and hero flag chrome), with `color: inherit` for non-table contexts.

---

## Amiga inline compositors (E3–E5)

Implementation: `includes/k2_amiga_country_flag.php`.

| Compositor | Shape | When |
|------------|-------|------|
| `k2_amiga_lb_player_cell($playerId, $name, $country)` | flag + player name | Nationality beside player (rating LB, opponents, event stats, …) |
| `k2_amiga_lb_tournament_cell($tournamentId, $name, $hostCountry)` | host flag + tournament name | Host nation beside tournament (catalog, player history, WC chronology, WC stats, perf-rating Event col, …) |

**Inline prose (not table cells):** wrapper `k2-amiga-inline-flag-text` — 16×12 px `k2-amiga-country-flag-img--text` (2px radius), baseline-aligned; `margin-left: 0.45em` before flag, `margin-right: 0.15em` after flag (both em-based). PHP: `k2_amiga_inline_flag_text_and_link()`. Tournament name link in chart peak copy uses `pm3-chart-peak-link` (peak accent ink — **not** `k2-link-star`). Do **not** reuse `k2-amiga-wc-podium-player` in sentences. **TT Event ribbon stepper:** `k2_amiga_inline_flag_and_link()` (same table wrapper + 20×15 flag as `lb_tournament_cell`).
| `k2_amiga_lb_country_cell($countryToken)` | flag + country name | Countries index, WC Countries wings — row **is** the country |

Shared layout:

- Wrapper: `<span class="k2-amiga-wc-podium-player">` (`inline-flex`, gap — see `theme.css`)
- Flag img: `k2-amiga-country-flag-img` (20×15) via `k2_amiga_country_flag_link()`
- Built on: `k2_amiga_inline_flag_and_link($country, $nameLinkHtml)`

**Game-row flanking flags** (`k2-amiga-tgame-side` on tournament games tables) are a **separate layout** — same flag impression, not these prefix compositors.

**Video spotlight caption** keeps tgame-sized flag + `decorative: true` — caption-only; not governed by this policy's table compositors.

---

## Anchor column (unchanged)

Exactly one **`data-k2-anchor-col`** per sortable table ([`k2-table-and-games-plan.md` § Anchor column map](k2-table-and-games-plan.md)). Anchor styling (`k2-table-anchor-cell`) adds **calm-stats emphasis** on the editorial column; it does **not** replace E1 — entity name links still carry `k2-link-star` explicitly.

### Leaderboard player-row scroll targets

Any hub LB (online or Amiga) that may receive **`#k2-lb-player-{id}`** inbound links (profile mosaic, HoF, Elo drill-down) must emit **`k2_lb_player_row_anchor_markup($playerId)`** immediately before the Player cell content (`lb_player_filters.php`). Hash landing = `k2_carry_scroll_restore.php` — do not add page-local hash scroll JS.

**Jul 2026:** All online hub LB wings + league honours + Amiga career LB wings (rating/goals/DD already had anchors; remainder aligned same session).

---

## Calm cell links (C1)

**When:** a **numeric or stat value** in a calm-stats table (`k2-table--calm-stats`, usually `ranked-pages-table`) should stay visually quiet at rest but be clickable — peak rank, rating Δ, activity peaks → games, future GF-style inventory links, etc.

**Not C1:** player / tournament / country **names** (E1 `k2-link-star`); Elo column drill-downs (`k2_*_lb_rating_cell_link()` → `k2-link-star`); Opponents **Games** counts (`k2-link-star` — intentional accent drill-down).

### Pick link type (decision fork)

| Cell content | Class on `<a>` | Notes |
|--------------|----------------|-------|
| Plain calm stat (muted when column not sorted) | `k2-table-cell-link` | Inherits cell ink at rest |
| Editorial positive stat (`.blue` column / win count) | `k2-table-cell-link blue` | **Put `blue` on the `<a>`**, not only on a child `<span>` — `td .blue` on spans does not reliably paint anchors |
| Editorial negative stat | `k2-table-cell-link red` | Same rule as `.blue` |
| Hero stat link, **bold even when column not sorted** | add `k2-table-cell-link--rest-emphasis` | Peak-rating **Peak** links only today; do **not** default every `.blue` link to this — Goals GF-style links stay sort-gated weight at rest |

### Markup (copy patterns)

```html
<!-- plain calm value -->
<a class="k2-table-cell-link" href="…">42</a>

<!-- positive stat ink (--k2-table-positive; tint-aware, not always green) -->
<a class="k2-table-cell-link blue" href="…">1847</a>

<!-- negative stat ink -->
<a class="k2-table-cell-link red" href="…">−12</a>

<!-- hero stat link: bold at rest even when column not active sort -->
<a class="k2-table-cell-link blue k2-table-cell-link--rest-emphasis" href="…">2613</a>
```

With column tooltips: add `k2-table-helped` + `data-k2-help` per [`k2-tooltip-policy.md`](k2-tooltip-policy.md) (see peak-rating lib).

### Rest vs hover contract

| Rest | Hover |
|------|-------|
| Inherit cell ink (secondary when column not sorted; primary + 600 when sorted) | Underline + weight **600** |
| `.blue` / `.red` on `<a>`: stat palette at rest (`theme.css` explicit `a.k2-table-cell-link.blue` / `.red`) | Same stat hue — **no** primary lift |
| `--rest-emphasis`: weight 600 at rest regardless of sort | Underline + 600; `.blue`/`.red` still no hue lift |

**Do not** add page-specific link classes (`k2-lb-foo-link`). One class family + optional `.blue` / `.red` / `--rest-emphasis`.

### Agent traps

1. **Bare `<a href>` in `tbody td`** — global `body.k2-site .k2-table tbody td a` applies **link-star** accent. Always opt into `k2-table-cell-link`.
2. **`k2-link-star` on stat values** — wrong ink; reserved for entity names and accent drill-downs (Elo, Games count).
3. **`<span class="blue">` inside `<a class="k2-table-cell-link">`** — prefer `class="k2-table-cell-link blue"` on the anchor so rest-state stat color wins over `color: inherit` / link-star.
4. **`--rest-emphasis` on every `.blue` link** — only when product wants permanent bold off-sort (Peak pattern). Default `.blue` cell links match `<span class="blue">` sort-gated weight.
5. **Perf-rating LB** — table modifier `k2-table--perf-rating-lb` still forces all `td .blue` to weight 600 at rest; separate from C1 markup.

### References (read one first)

| Scenario | Reference |
|----------|-----------|
| Plain calm link + tooltip | `amiga_lb_peak_rating_peak_rank_cell_html()` in `includes/amiga_lb_peak_rating_lib.php` |
| `.blue` + `--rest-emphasis` + tooltip | `amiga_lb_peak_rating_peak_cell_html()` in same file |
| `.blue` / `.red` signed stat link | `amiga_lb_rating_delta_cell()` in `includes/amiga_lb_snapshot_lib.php` |
| Online activity peaks → games | `lb_activity_lib.php` (`k2-table-cell-link` on calm-stats table) |

---

## Not entity name links

These stay on their existing patterns:

| Pattern | Example |
|---------|---------|
| Numeric drill-down | Opponents **Games** count → filtered games list (`k2-link-star` on the number — drill-down, not an entity row label) |
| **Career Elo drill-down (Amiga)** | Hub LB + WC player stats + countries roster **Elo** column → `k2_amiga_lb_rating_cell_link()` → rating LB `#k2-lb-player-{id}` (`k2-link-star`; rating glance: name + flag, rank + rating, footer “Click to view rating leaderboard”) |
| **Career Elo drill-down (online)** | Hub LB wings + League honours + Status active-players table **Elo** column → `k2_lb_rating_cell_link()` → rating LB `#k2-lb-player-{id}` (`k2-link-star`; rating glance via `data-k2-player-glance-rating`, same footer) |
| **Profile hero rank / rating / games (online)** | **Policy:** [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) — rank/rating → rating LB `#k2-lb-player-{id}`; games → Games tab `#matching-games`. |
| Calm secondary body links | **C1** — `k2-table-cell-link` (full contract § Calm cell links) |
| Filter listbox labels | Text-only country/year pickers — no flags ([`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) CH9) |
| Hero / prose links | Player hero name, country hero title, hub chapter links — outside table compositors. **Profile hero + mosaic stat values:** [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) |

---

## Migration — retire Amiga flag-only columns (E4)

**Shipped (Jun 2026).** Dedicated Country / Flag columns were replaced with inline compositors; sort on the removed country-only columns was intentionally dropped. The table below is the canonical record of where each entity type now lives — keep it accurate if a new surface ships.

| Surface | Include | Inline target |
|---------|---------|---------------|
| Countries index | `amiga_countries_index_table.php` | Merge Flag + Country → `lb_country_cell` |
| WC Countries (5 wings) | `amiga_wc_countries_table.php` | Country col → `lb_country_cell` |
| Player Opponents (3 tables) | `amiga_player_opponents_tables.php` | Opponent col → `lb_player_cell` |
| Player tournament history | `amiga_profile_blocks.php` | Tournament col → `lb_tournament_cell` |
| Tournament catalog | `amiga_profile_blocks.php` | Tournament col → `lb_tournament_cell` |
| Tournament event stats | `amiga_profile_blocks.php` | Player col → `lb_player_cell` |
| WC chronology | `amiga_world_cups_events_table.php` | Tournament col → `lb_tournament_cell` |
| WC stats (4 sub-views) | `amiga_world_cup_stats_table.php` | Tournament anchor col → `lb_tournament_cell` |

**`k2_amiga_country_table_cell()`** and **`k2_lb_th_country()`** are now caller-free `@deprecated` stubs; **`k2_lb_td_country_open()`** was removed outright. The roster page (CH10) uses the inline `k2_amiga_lb_player_cell()` pattern, so no flag-only column remains.

**Follow-up (Jun 2026):** Country roster **Last event** col (`amiga_countries_roster_table.php`) and player videos **Tournament** col (`amiga_player_videos_render.inc.php`) now use `amiga_tournament_link()` — were hand-built `<a href>`. Live tournaments index stays on `amiga_live_tournament_link()` (separate URL space).

**Tier A+B (Jun 2026):** Tournament **standings** Player col → `lb_player_cell`; roster Last event + player videos Tournament → `lb_tournament_cell`; **player games** + **single game** rows → flanking nationality/host flags (`amiga_rated_game_player_side_cell`, `amiga_rated_game_tournament_cell`) matching realm games hub. Adjustment cols unchanged (no flags).

**Already inline (reference):** Amiga hub leaderboards (Rating, Goals, …), perf-rating Event col, WC chronology podium cols, game hub rows (leaderboard flag).

---

## Pick a reference (read one file first)

| Scenario | Reference |
|----------|-----------|
| Player + nationality inline | `amiga/leaderboards/rating.php` + `k2_amiga_lb_player_cell()` |
| Host flag + tournament inline | `includes/amiga_lb_performance_rating_table.php` Event column |
| Dual link pattern (flag + name) | `k2_amiga_inline_flag_and_link()` — same wrapper as above |
| Country name link | `k2_amiga_country_roster_link()` — `k2-link-star` to roster |
| Career Elo → rating LB row | `k2_amiga_lb_rating_cell_link()` in `amiga_lb_lib.php` (Amiga); `k2_lb_rating_cell_link()` in `lb_player_filters.php` (online) |
| **Calm cell link** (stat value) | **C1** — `amiga_lb_peak_rating_lib.php` (peak rank · peak); `amiga_lb_snapshot_lib.php` (rating Δ); `lb_activity_lib.php` (activity peaks) |
| Flag img link only | `k2_amiga_country_flag_link()` |

If unsure: **grep** `k2_amiga_lb_player_cell` / `k2_amiga_lb_tournament_cell` / `k2_amiga_lb_country_cell` / **`k2-table-cell-link`** in `site/public_html/`.

---

## Before shipping — self-check

- [ ] Entity **names** use `k2-link-star` via the correct helper (E1–E2) — not `k2-country-roster-link` on text.
- [ ] Amiga nationality/host/country identity uses inline compositors (E4) — no new flag-only columns.
- [ ] Country rows use **dual link** (E5): `flag_link` + `country_roster_link`.
- [ ] Unmapped tokens: no flag, name still linked when token non-empty (E6).
- [ ] **Calm stat links (C1):** `k2-table-cell-link` on the `<a>` — not bare `td a` / not `k2-link-star`; `.blue`/`.red` on the **anchor** when needed; `--rest-emphasis` only for permanent off-sort bold.
- [ ] Table stack still passes [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) §3.
- [ ] Update this doc's migration table if a new surface ships; Part A [`UPDATE_DOCS.md`](UPDATE_DOCS.md) + `PROJECT_MEMORY.md`.

---

## Related CSS (`theme.css`)

| Class | Role |
|-------|------|
| `a.k2-link-star` | Entity name links — `--k2-link-star`, weight 600, hover underline |
| `a.k2-table-cell-link` | Calm cell links — inherit rest ink; hover underline + 600; primary lift for muted ink; `.blue`/`.red` preserve stat color on hover |
| `a.k2-table-cell-link.blue` / `.red` | Rest stat ink on ranked LBs (beats global `tbody td a` link-star) — class on the `<a>` |
| `a.k2-table-cell-link--rest-emphasis` | Optional weight 600 at rest (peak-rating Peak links when column not sorted) |
| `a.k2-country-roster-link` | Flag **img** wrappers — `color: inherit`; not for country name text in tables |
| `.k2-amiga-wc-podium-player` | Inline `[flag][name]` flex row |
| `.k2-amiga-country-flag-img` | Table flag impression 20×15 |

Global `body.k2-site .k2-table tbody td a` also styles links link-star — **do not** depend on it for new entity names (E1) or calm cell links (C1); use explicit classes.

---

*Last updated: Jul 2026 — C1 calm cell links section (decision fork, markup, traps, references); unified hover; retired `k2-lb-amiga-peak-*` and `k2-lb-amiga-rating-delta-*`.*