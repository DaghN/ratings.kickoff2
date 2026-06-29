# Amiga Opponents — country grain (player vs country)

**Status:** **Shipped** (Jun 2026). OCG-1–OCG-7 complete — see [`amiga-opponents-country-grain-implementation-plan.md`](amiga-opponents-country-grain-implementation-plan.md).

**Implementation plan:** [`amiga-opponents-country-grain-implementation-plan.md`](amiga-opponents-country-grain-implementation-plan.md) — slices **OCG-1–OCG-7**.

**Parent:** [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-performance-rating.md`](amiga-performance-rating.md) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H8 country token)

**Related:** [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) · [`url-routes.md`](url-routes.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`nav-spacing-policy.md`](nav-spacing-policy.md) · [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) · [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md)

**Online:** **Amiga-only** — online realm has no nationality grain; do not port unless product asks.

---

## 1. Executive summary

Add a **country grain** to the Amiga **Opponents** wing — same four inner wings (Head-to-head · W/D/L · Goals · DDs), but rows bucketed by **opponent country** instead of opponent player.

| Grain | URL namespace | Row key |
|-------|---------------|---------|
| **Player** (default, shipped) | `amiga/player/opponents/{h2h,wdl,goals,dds}.php` | `opponent_id` |
| **Country** (this track) | `amiga/player/opponents/country/{h2h,wdl,goals,dds}.php` | `country_token` |

**Navigation:** folder path for grain (like `/amiga/` for realm; like `tournament/videos/` for nested modes). **Not** `?vs=` / `?grain=` / `?mode=`.

**Data (v1):** read-time roll-up from existing pair stored truth (`amiga_player_matchup_summary` / `amiga_player_matchup_at_event`) — **no new derived tables or finalize writers**. Country **performance rating** = read-time TPR solve from `amiga_game_ratings` filtered by opponent nationality.

**UI:** a second **segment** (vs Player · vs Country) sits **to the right** of the four wing tabs on one horizontal row, with a fixed gap — not stacked below the wing tabs on desktop.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **OCG1** | **Grain = folder** | Player grain stays at `opponents/*.php`. Country grain lives under `opponents/country/*.php`. Register routes in `k2_amiga_routes.php`. |
| **OCG2** | **Wing preserved on toggle** | Switching grain keeps the active wing: W/D/L → W/D/L, H2H → H2H, etc. |
| **OCG3** | **Default grain** | **Player**. Opponents pill default href remains `amiga-player-opponents-h2h` (player path). |
| **OCG4** | **Segment labels** | **vs Player** · **vs Country** (exact copy). |
| **OCG5** | **Segment placement** | **One nav row**, horizontal: wing segment **left**, grain segment **right**, `gap: var(--k2-nav-gap)` between the two segment tracks (see §6). **Not** a second row below the wings on viewports ≥ policy breakpoint. |
| **OCG6** | **Country token** | `TRIM(amiga_players.country)` when non-empty ([**H8**](amiga-hof-tournament-geo-policy.md)); empty/NULL opponent nationality → literal **`Unknown`**. |
| **OCG7** | **Directed hero perspective** | All stats are **hero → opponent country**: cumulative results of the subject player against all nationals from that token through cutoff. |
| **OCG8** | **Own country row** | Include games vs **compatriots** (hero vs own country token). Copy/tooltips: *players from {country}*, not “national team”. |
| **OCG9** | **Cross-border game count** | Each rated game between hero and an opponent from country *C* counts **once** in hero→*C* (same as summing directed pair rows). |
| **OCG10** | **Time travel** | Same cutoff habit as player grain ([`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) O4–O5): present = `matchup_summary`; cutoff = latest `matchup_at_event` per opponent, **then** roll up by country. |
| **OCG11** | **Stored truth v1** | **No DDL** — roll-up + read-time country TPR only. Revisit persisted `amiga_player_country_matchup_*` only if perf or verify demands it. |
| **OCG12** | **H2H rating/rank charts** | **Omit** in country grain — no meaningful country rating/rank time series. Other H2H blocks (poster, moments, cumulative H2H/goals charts, heatmap/histogram) **in scope** when filtered by opponent country. |
| **OCG13** | **Online** | Out of scope for this track. |

---

## 3. URL and routes

### 3.1 Path map

| Wing | Player grain | Country grain |
|------|--------------|---------------|
| Head-to-head | `/amiga/player/opponents/h2h.php` | `/amiga/player/opponents/country/h2h.php` |
| W/D/L | `…/opponents/wdl.php` | `…/opponents/country/wdl.php` |
| Goals | `…/opponents/goals.php` | `…/opponents/country/goals.php` |
| DDs | `…/opponents/dds.php` | `…/opponents/country/dds.php` |

**Required on all:** `?id={player_id}`. Propagate `as=` via `amiga_url_with_context()` / route helpers when time travelling.

### 3.2 Filter params (not navigation)

| Param | Grain | Meaning |
|-------|-------|---------|
| `opponent` | Player H2H | Opponent `player_id` (unchanged) |
| `country` | Country H2H | Opponent **country token** for drill-down (H8); omit for prompt-only state |

Do **not** reuse `opponent=` for country tokens. Clearing incompatible params when switching grain is automatic (different paths).

### 3.3 Route keys (register at implementation)

| Route key | Path |
|-----------|------|
| `amiga-player-opponents-country-h2h` | `/amiga/player/opponents/country/h2h.php` |
| `amiga-player-opponents-country-wdl` | `/amiga/player/opponents/country/wdl.php` |
| `amiga-player-opponents-country-goals` | `/amiga/player/opponents/country/goals.php` |
| `amiga-player-opponents-country-dds` | `/amiga/player/opponents/country/dds.php` |

**Href helper:** extend `amiga_player_opponents_href($playerId, $view, $grain = 'player', …)` — grain `'player'|'country'` selects folder. H2H drill-down adds `opponent` or `country` as appropriate.

### 3.4 Entry files

Thin one-liners under `country/` — set `$k2AmigaPlayerOpponentsGrain = 'country'` and `$k2AmigaPlayerOpponentsView`, then `require` shared `includes/amiga_player_opponents_page.php`.

---

## 4. Data architecture

### 4.1 Source tables (unchanged)

| Layer | Table | Role |
|-------|-------|------|
| Pair present | `amiga_player_matchup_summary` | Directed `(player_id → opponent_id)` cumulative scalars |
| Pair timeline | `amiga_player_matchup_at_event` | Same through each finalize cutoff |
| Labels | `amiga_players.country` | Opponent nationality → token (**OCG6**) |
| Country TPR | `amiga_game_ratings` + `amiga_games` | Read-time solve per `(hero, opp_country)` |
| Game depth | `amiga_games` | H2H moments/charts filtered by opponent country |

### 4.2 Roll-up algorithm (present)

Conceptual SQL (implement in `includes/amiga_player_opponents_country_load.php` or sibling):

```sql
SELECT
  COALESCE(NULLIF(TRIM(p.country), ''), 'Unknown') AS country_token,
  SUM(m.games) AS games,
  SUM(m.wins) AS wins,
  SUM(m.draws) AS draws,
  SUM(m.losses) AS losses,
  SUM(m.goals_for) AS goals_for,
  SUM(m.goals_against) AS goals_against,
  SUM(m.dd_wins) AS dd_wins,
  SUM(m.dd_losses) AS dd_losses,
  SUM(m.cs_wins) AS cs_wins,
  SUM(m.cs_losses) AS cs_losses,
  MAX(m.max_goals_for) AS max_goals_for,
  … /* other extremes: MAX per column, not SUM */
FROM amiga_player_matchup_summary m
INNER JOIN amiga_players p ON p.id = m.opponent_id
WHERE m.player_id = ?
GROUP BY country_token
```

**Invariants:**

- Additive columns (**W/D/L, GF/GA, DD/CS counts**) = **SUM** over pairs in bucket.
- Goal **extremes** = **MAX** (or MIN where applicable) across pairs in bucket — never SUM.
- **Do not** roll up `performance_rating` from pair rows (non-linear) — see §4.4.

**Scale (Jun 2026 local):** ~473 players, ~14k pair rows, ~2.8k player×country buckets realm-wide; ~6 buckets per player average. Single-player roll-up is hot-path safe.

### 4.3 Roll-up algorithm (time travel)

1. Load latest at-event row per `(player_id, opponent_id)` ≤ cutoff — reuse `amiga_matchup_at_event_latest_from_sql()` / `amiga_player_opponents_matchup_rows()` pattern from [`amiga_matchup_snapshot_lib.php`](../site/public_html/includes/amiga_matchup_snapshot_lib.php).
2. Join opponent `amiga_players.country`.
3. **GROUP BY** country token with same SUM/MAX rules as §4.2.

Do **not** use `MAX(as_of_tournament_id)` alone for cutoff.

### 4.4 Country performance rating (read-time)

Same TPR definition as pair and event perf ([`amiga-performance-rating.md`](amiga-performance-rating.md)):

- **Game set:** all rated games where hero = subject and opponent’s country token = *C*, tournament tuple ≤ cutoff.
- **`R_opp_g`:** frozen pre-game opponent rating on each game row.
- **Min games / perfect record:** same NULL / ∞ rules as pair perf (`≥2` games; all-win or all-loss → NULL; perfect win → ∞ display).

**Implementation:** dedicated helper e.g. `amiga_player_country_matchup_performance_rating($con, $playerId, $countryToken, $ctx)` — batch for W/D/L table rows in one query where practical. **Country H2H pair detail** also solves **reverse TPR** (nationals from *C* vs hero — each game uses hero’s frozen pre-game rating as `R_opp` and the national’s score) via `performance_rating_vs_hero` on the same batch pass.

**Not stored** in v1 — no `prove` gate unless tables are added later.

### 4.5 H2H directed bucket (country grain)

For hero *P* and country token *C*: one synthetic “pair” row = roll-up aggregates for that bucket (same fields as §4.2). Used for poster centre record, pair detail strip, and picker ordering (by `games` desc).

---

## 5. UI — tables (W/D/L · Goals · DDs)

Reuse k2-table stack ([`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md)). Same sort defaults as player grain (Games ↓).

### 5.1 Column mapping

| Player grain column | Country grain column | Notes |
|---------------------|----------------------|-------|
| Opponent (player cell) | **Country** | `k2_amiga_lb_country_cell()` → country roster (**OCG6**) |
| Elo | **Omit** | No single country Elo; do not show avg opp rating in v1 |
| Games (link) | Games (link) | Hero games tab with **`opp_country=`** filter ([`amiga/player/games.php`](../site/public_html/amiga/player/games.php)); preserve `as=` |
| W/D/L ratios | Same | From rolled-up counts |
| Perf. (W/D/L only) | Perf. | Read-time country TPR (§4.4) |
| Goal extremes (Goals wing) | Same labels | **MAX** roll-up (§4.2) |
| DD/CS counts + ratios (DDs wing) | Same | Summed counts; ratios ÷ bucket `games` |

### 5.2 Empty states

- No opponents in bucket at cutoff: omit row (table shows only countries faced).
- Hero never played anyone from a token: row absent (not zero row).

---

## 6. UI — navigation chrome

### 6.1 Structure

Extend [`includes/amiga_player_opponents_nav.php`](../site/public_html/includes/amiga_player_opponents_nav.php):

```text
.k2-player-opponents (Pattern B wrapper — unchanged)
  nav.k2-player-opponents__nav
    .k2-player-opponents__nav-row          ← NEW flex row
      .k2-chrome-tabs.k2-player-opponents__wings   ← H2H · W/D/L · Goals · DDs
      .k2-chrome-tabs.k2-player-opponents__grain  ← vs Player · vs Country
```

- **Horizontal layout:** `display: flex; flex-direction: row; align-items: center; flex-wrap: wrap; gap: var(--k2-nav-gap);`
- **Order:** wings first (inline-start), grain second (to the **right** of wings with gap — **OCG5**).
- **Spacing:** inner `__nav` keeps `margin-bottom: var(--k2-nav-gap)` to content (Pattern B). H2H picker exception (`20px` when `.k2-player-opponents-h2h` present) unchanged.
- **Markup grammar:** both children use standard `.k2-chrome-tabs` + `.k2-chrome-tabs__bar` + `.k2-chrome-tabs__tab` ([`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) §2).
- **Responsive:** on narrow viewports, `flex-wrap: wrap` may stack grain under wings — acceptable; **desktop default is side-by-side**, not a dedicated second nav row.

### 6.2 CSS hook

Add rules to `theme.css` under existing `.k2-player-opponents` block (or `player-feast-sections.css` if Amiga-only): `.k2-player-opponents__nav-row`, `.k2-player-opponents__wings`, `.k2-player-opponents__grain`. Document in [`nav-spacing-policy.md`](nav-spacing-policy.md) Phase 3 audit when shipped.

### 6.3 Active states

- Wing tab active by `$k2AmigaPlayerOpponentsView`.
- Grain tab active by `$k2AmigaPlayerOpponentsGrain` (`player`|`country`).

---

## 7. UI — Head-to-head (country grain)

Path: `opponents/country/h2h.php`. Reuse poster/moments/chart CSS where possible.

### 7.1 Pickers

| Player grain | Country grain |
|--------------|---------------|
| Player search + games listbox + A–Z listbox | **Two listboxes only:** by games played · A–Z **country token** |
| `opponent={id}` | `country={token}` |

- Options = countries appearing in hero’s rolled-up buckets at cutoff.
- Default when `country` omitted: country with most games (URL stays without `country=` until user picks another — mirror player H2H default).

**No player search** in country grain v1.

### 7.2 Poster / detail

- **Subject card:** hero player (unchanged).
- **Opponent side:** **country card** — flag + token + link to country roster; no fake player profile.
- **Centre record:** W/D/L from bucket roll-up.
- **Pair detail strip:** include country **Perf.** (read-time TPR).

### 7.3 Charts and depth

| Block | Country grain |
|-------|---------------|
| Cumulative H2H / goals charts | **Yes** — filter games by opponent country |
| Moments grid | **Yes** — same filter |
| Heatmap / histograms | **Yes** — same filter |
| **Rating comparison** | **No** (**OCG12**) |
| **Rank comparison** | **No** (**OCG12**) |
| All games link | Hero games with `opp_country=` |

Wire chart APIs / JS with country filter param (extend existing Amiga H2H chart context — do not fork new chart stack).

---

## 8. Shared page shell

[`includes/amiga_player_opponents_page.php`](../site/public_html/includes/amiga_player_opponents_page.php) branches on grain:

| `$k2AmigaPlayerOpponentsGrain` | Render |
|--------------------------------|--------|
| `player` | Existing tables / H2H panel (unchanged) |
| `country` | Country table renderers + `amiga_player_opponents_render_country_h2h_panel()` |

Set grain in thin entry files; default `player` when unset (existing `opponents/*.php` entries).

---

## 9. Verification (v1)

| Check | Method |
|-------|--------|
| Roll-up additive parity | Spot-check hero: SUM pair `games` for all opponents from *C* = country bucket `games` |
| Denmark → Sweden spot | Bucket matches directed pair sum (local oracle query) |
| Own-country bucket | Domestic compatriot games appear in hero→own-country row |
| Time travel | Early `as=` — bucket games ≤ present; rows may disappear |
| Country TPR | Sample: read-time TPR matches manual solve from `amiga_game_ratings` for same game set |
| Nav | Grain toggle preserves wing; `as=` preserved; Opponents pill → player H2H |
| Games link | Country grain Games cell → games tab with `opp_country=` |

No `prove` gate until persisted tables exist (**OCG11**).

---

## 10. Suggested implementation slices

```text
1. Routes + lib grain param + country roll-up load lib (no UI)
2. Nav row (horizontal dual segment) + country/ thin entries (placeholder body OK)
3. W/D/L country table + Perf. column + games links
4. Goals + DDs country tables
5. H2H country — pickers + poster + detail (no rating/rank charts)
6. H2H country — moments + remaining charts
```

Ship and proof after each slice. Update this doc session log + `PROJECT_MEMORY.md`.

---

## 11. Out of scope

| Topic | Notes |
|-------|--------|
| Persisted country matchup tables | v1 read-time only (**OCG11**) |
| Realm-wide LB “best vs Sweden” | Needs stored grain or heavy scan — defer |
| Country vs country (nation pairs) | [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) §9 Rivals — separate track |
| Online opponents country grain | No nationality |
| Average opponent Elo column | Defer (WC country slice uses on Results — different product) |

---

## 12. Files (expected)

| Area | Files |
|------|-------|
| **Entries** | `amiga/player/opponents/country/{h2h,wdl,goals,dds}.php` |
| **Nav** | `includes/amiga_player_opponents_nav.php`, `includes/amiga_player_opponents_lib.php` |
| **Load** | `includes/amiga_player_opponents_country_load.php` (new) |
| **Tables** | `includes/amiga_player_opponents_country_tables.php` (new) or extend `amiga_player_opponents_tables.php` |
| **H2H** | `includes/amiga_player_opponents_country_h2h.php` (new) or extend `amiga_player_opponents_h2h.php` |
| **Routes** | `includes/k2_amiga_routes.php` |
| **CSS** | `stylesheets/theme.css` (nav row) |
| **Docs** | This policy · `url-routes.md` · parent opponents policy cross-link |

---

## 13. Agent cold start

**Dagh says:** “Opponents country grain slice”.

**Read:** this doc → [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) → [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) → [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md).

**Then:** confirm slice (nav vs W/D/L vs H2H), grain from path, roll-up + TT source, unwired chart blocks.

**Finish:** [`UPDATE_DOCS.md`](UPDATE_DOCS.md) Part A when code ships; Part B only if DDL added.

---

## 14. Session log

| Date | Note |
|------|------|
| Jun 2026 | Policy locked — folder `opponents/country/` grain, horizontal dual segment nav, read-time roll-up + country TPR, H2H minus rating/rank compare. |
| Jun 2026 | Implementation plan — slices OCG-1–OCG-7. |
| Jun 2026 | **OCG-2 shipped** — nav + country entries + placeholder (routes/hrefs included). |