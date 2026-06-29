# Amiga country Rivals — nation-pair grain (country vs country)

**Status:** **Shipped** (Jun 2026). Slices **CRV-1–CRV-7** complete.

**Implementation plan:** [`amiga-country-rivals-implementation-plan.md`](amiga-country-rivals-implementation-plan.md) — slices **CRV-1–CRV-7**.

**Parent:** [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) (CH19 · CH24) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) · [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) (player vs country — sibling grain)

**Related:** [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) · [`url-routes.md`](url-routes.md) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) · [`nav-spacing-policy.md`](nav-spacing-policy.md) · [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H8 country token) · [`amiga-performance-rating.md`](amiga-performance-rating.md)

**Online:** **Amiga-only** — online realm has no nationality grain.

---

## 1. Executive summary

Add **country vs country** compare on the existing **Rivals** segment of the Amiga **country entity** — same four inner wings as Opponents (Head-to-head · W/D/L · Goals · DDs), but rows bucketed by **directed nation pair** `(hero_country → rival_country)`.

| Surface | URL namespace | Hero key | Row / drill-down key |
|---------|---------------|----------|----------------------|
| **Roster** (shipped) | `amiga/country/roster.php` | `country=` | player roster |
| **Rivals** (this track) | `amiga/country/rivals/{h2h,wdl,goals,dds}.php` | `country=` | `rival=` country token |

**Navigation:** folder path for wing (like Opponents and player country grain). **Not** `?view=` / `?wing=` / `?tab=`.

**Data (v1):** **second read-time roll-up** from existing pair stored truth (`amiga_player_matchup_summary` / `amiga_player_matchup_at_event`) — join hero + opponent nationalities, **SUM/MAX** pair scalars. **Do not** scan `amiga_games` for table aggregates (measured ~3× slower at current scale). Game-level reads (H2H moments/charts) filter `amiga_games` by directed nation pair.

**Persisted nation-pair tables:** **Out of scope v1** (~318 directed pairs present; revisit only for realm-wide nation-pair LBs + hard `prove` verify).

---

## 1.1 Three matchup grains (player vs player · player vs country · country vs country)

Amiga has **three** directed matchup surfaces built from the same pair stored truth (`amiga_player_matchup_summary` / at-event). Do not conflate URL namespaces or drill-down params.

| Grain | Question | Hero entity | Row / pair key | URL namespace | H2H drill param | Own-country / domestic |
|-------|----------|-------------|----------------|---------------|-----------------|------------------------|
| **Player vs player** | How did *this player* fare vs *that player*? | Player (`?id=`) | `opponent_id` | `player/opponents/{h2h,wdl,goals,dds}.php` | `opponent=` | — |
| **Player vs country** | How did *this player* fare vs nationals from *country C*? | Player (`?id=`) | opponent `country_token` | `player/opponents/country/{h2h,wdl,goals,dds}.php` | `country=` | **Includes** hero's own country row (compatriots) — [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) OCG8 |
| **Country vs country** | How did nationals from *A* fare vs nationals from *B*? | Country (`?country=`) | directed `rival_token` (A→B) | `country/rivals/{h2h,wdl,goals,dds}.php` | `rival=` | **Excludes** A→A domestic row in all four wings — **CRV7** |

**Sibling policies:** player grain → [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md); player vs country → [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md); country vs country → this doc.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CRV1** | **Placement** | Rivals lives under **`/amiga/country/rivals/*`** — entity segment below country hero ([`navigation-model.md`](navigation-model.md) NM6). **Not** under `player/opponents/`. |
| **CRV2** | **Entity segment unchanged** | **Roster · Rivals** tabs remain on [`amiga_country_nav.php`](../site/public_html/includes/amiga_country_nav.php). Rivals wings (H2H · W/D/L · Goals · DDs) are **inner nav inside Rivals only** — Roster segment has no wing row. |
| **CRV3** | **Hero country = entity key** | `?country={token}` on every Rivals URL matches the country entity hero ([**H8**](amiga-hof-tournament-geo-policy.md)). Empty/invalid → 404 or redirect to Countries hub (same as roster). |
| **CRV4** | **Rival drill-down param** | H2H and games links use **`rival={token}`** for the opponent nation. **Do not** reuse `opponent=` (player id) or overload `country=` for the second nation. |
| **CRV5** | **Directed grain** | All stats are **hero_country → rival_country** (A→B). **Denmark→Sweden** and **Sweden→Denmark** are separate buckets with separate W/D/L. |
| **CRV6** | **Cross-border game count** | Each rated game between a national from A and a national from B counts **once** in **A→B** — via summing directed player-pair rows where hero ∈ A and opponent ∈ B. Symmetric **game totals** in A→B and B→A. |
| **CRV7** | **Domestic (A→A)** | Roll-up data may include compatriot-vs-compatriot games at nation grain elsewhere; **Rivals UI excludes the domestic row** (hero → same country) from all four wings. |
| **CRV8** | **Unknown nationality** | Empty/NULL player `country` → token **`Unknown`** ([**H8**](amiga-hof-tournament-geo-policy.md)). Include in faced/beaten sets. |
| **CRV9** | **Scope** | **Career-wide** rated games (same universe as Opponents). **Not** WC-only — WC nation tables remain [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md). |
| **CRV10** | **Time travel** | Present = `amiga_player_matchup_summary`; cutoff = latest `amiga_player_matchup_at_event` per `(player_id, opponent_id)` ≤ cutoff, **then** nation-pair roll-up — same window habit as player Opponents ([`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) O4–O5). |
| **CRV11** | **Stored truth v1** | **No DDL** — second roll-up only. Revisit `amiga_country_matchup_{summary,at_event}` (~500 rows) only if perf, verify, or realm-wide sortable LBs demand it. |
| **CRV12** | **Performance rating** | **Non-linear** — never SUM/SUM pair `performance_rating`. v1: **read-time TPR solve** per directed nation pair over all games in bucket (same min-games / perfect-record rules as [`amiga-performance-rating.md`](amiga-performance-rating.md)). **Defer Perf. column** only if batch cost blocks ship — do not fake roll-up. |
| **CRV13** | **H2H rating/rank charts** | **Omit** — no meaningful nation rating/rank time series (same reasoning as [OCG12](amiga-opponents-country-grain-policy.md)). Poster, moments, cumulative wins/goals charts, histograms, heatmap **in scope**. |
| **CRV14** | **Copy** | Prefer *players from {country}* on roster links; nation-pair labels *{A} vs {B}* / *{A} nationals vs {B} nationals* on poster — not “national team”. |
| **CRV15** | **Default rival (H2H)** | When `rival` omitted **or** equals hero (domestic): **302 redirect** to top cross-border rival by games (mirror player country H2H default). Canonical URL carries `rival=` + default `pick=games`. |
| **CRV16** | **Online** | Out of scope. |

---

## 3. URL and routes

### 3.1 Path map

| Wing | Path |
|------|------|
| Head-to-head | `/amiga/country/rivals/h2h.php` |
| W/D/L | `/amiga/country/rivals/wdl.php` |
| Goals | `/amiga/country/rivals/goals.php` |
| DDs | `/amiga/country/rivals/dds.php` |

**Required:** `?country={hero_token}`. Propagate `as=` via `amiga_url_with_context()` / route helpers.

**Legacy:** `/amiga/country/rivals.php?country=` → **301/302** to `rivals/h2h.php?country=` (preserve query) when wings ship (CRV-2).

### 3.2 Filter params (not navigation)

| Param | Meaning |
|-------|---------|
| `country` | Hero nation token (entity key) |
| `rival` | Opponent nation token for H2H drill-down and games filters |

### 3.3 Route keys (register at implementation)

| Route key | Path |
|-----------|------|
| `amiga-country-rivals-h2h` | `/amiga/country/rivals/h2h.php` |
| `amiga-country-rivals-wdl` | `/amiga/country/rivals/wdl.php` |
| `amiga-country-rivals-goals` | `/amiga/country/rivals/goals.php` |
| `amiga-country-rivals-dds` | `/amiga/country/rivals/dds.php` |

**Helpers:** `k2_amiga_country_rivals_href(string $heroToken, string $view = 'h2h', ?string $rivalToken = null)` — extend existing `k2_amiga_country_rivals_href()` to accept wing + optional rival.

### 3.4 Entry files

Thin one-liners under `amiga/country/rivals/` — set `$k2AmigaCountryRivalsView` and `$k2AmigaCountryView = 'rivals'`, then `require` shared shell (see §8).

---

## 4. Data architecture

### 4.1 Source tables (unchanged)

| Layer | Table | Role |
|-------|-------|------|
| Pair present | `amiga_player_matchup_summary` | Directed `(player_id → opponent_id)` cumulative scalars |
| Pair timeline | `amiga_player_matchup_at_event` | Same through each finalize cutoff |
| Labels | `amiga_players.country` | Hero + opponent nationality → token (**CRV8**) |
| Nation-pair TPR | `amiga_game_ratings` + `amiga_games` | Read-time solve per `(hero_country, rival_country)` bucket |
| Game depth | `amiga_games` | H2H moments/charts only — directed nation-pair filter |

### 4.2 Second roll-up (present) — directed nation pair

Conceptual SQL for hero country **A** listing all rivals:

```sql
SELECT
  COALESCE(NULLIF(TRIM(o.country), ''), 'Unknown') AS rival_token,
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
  … /* other extremes: MAX, not SUM */
FROM amiga_player_matchup_summary m
INNER JOIN amiga_players h ON h.id = m.player_id
INNER JOIN amiga_players o ON o.id = m.opponent_id
WHERE COALESCE(NULLIF(TRIM(h.country), ''), 'Unknown') = :hero_country
GROUP BY rival_token
```

Single pair **A→B:** add `AND rival_token = :rival_country`.

**Invariants:**

- Additive columns = **SUM** over player-pairs in bucket.
- Goal **extremes** = **MAX** (MIN where applicable) — never SUM.
- **Do not** roll up pair `performance_rating` (**CRV12**).

**Scale (Jun 2026 local benchmarks):**

| Query | Time |
|-------|------|
| One hero's rival list (matchup roll-up) | ~**62 ms** |
| Same list from `amiga_games` scan | ~**200 ms** |
| Full directed country×country matrix | ~**179 ms** (~318 pairs) |

Parity spot-check: **Denmark→Sweden** = **131 games / 40-17-74** (matchup roll-up vs hero-centric game count).

### 4.3 Roll-up algorithm (time travel)

1. Load latest at-event row per `(player_id, opponent_id)` ≤ cutoff — reuse [`amiga_matchup_snapshot_lib.php`](../site/public_html/includes/amiga_matchup_snapshot_lib.php) / `amiga_player_opponents_matchup_rows()` pattern.
2. Join hero + opponent `amiga_players.country`.
3. Filter hero country = entity token; **GROUP BY** rival token with same SUM/MAX rules.

Do **not** use `MAX(as_of_tournament_id)` alone for cutoff.

### 4.4 Nation-pair performance rating (read-time)

- **Game set:** all rated games where hero player ∈ **A**, opponent player ∈ **B**, tournament tuple ≤ cutoff (directed **A→B**).
- **`R_opp_g`:** frozen pre-game opponent rating on each game row (national from **B**).
- **Min games / perfect record:** same NULL / ∞ rules as pair perf.

**Batch:** one games query joined to countries, bucket by `(hero_country, rival_country)` for W/D/L table — mirror [`amiga_player_opponents_country_perf_lib.php`](../site/public_html/includes/amiga_player_opponents_country_perf_lib.php).

**H2H pair detail:** hero-side TPR for **A→B**; optional reverse aggregate for **B** nationals vs **A** (mirror `performance_rating_vs_hero` naming at nation grain).

### 4.5 H2H directed bucket

For hero **A** and rival **B:** one synthetic bucket row = roll-up aggregates for **A→B** (same field shape as player country bucket). Used for poster, pair detail, picker ordering (by `games` desc).

---

## 5. UI — tables (W/D/L · Goals · DDs)

Reuse k2-table stack. Default sort: **Games ↓**.

### 5.1 Column mapping

| Opponents country grain | Rivals (nation-pair) grain |
|-------------------------|----------------------------|
| Country (opponent) | **Rival** — `k2_amiga_lb_country_cell()` → rival roster |
| Games → hero `opp_country=` | Games → **realm games** with directed nation-pair filter (**§5.3**) |
| Perf. (read-time) | Perf. (read-time nation-pair TPR) |
| No Elo | **No Elo** |

Hero country is **not** a column — it is the entity hero above the nav.

### 5.2 Empty states

- Hero nation faced no rivals at cutoff: empty table + intro copy.
- Specific **A→B** with zero games: omit row from W/D/L table; H2H prompt if `rival=` invalid.

### 5.3 Games drill-down links

Table **Games** column and H2H **all games** link → **`/amiga/games/all.php`** with directed filter:

- `country={hero_token}` (hero nation — same param name as entity)
- `rival={rival_token}` (opponent nation)

Implement filter in [`amiga_realm_games_hub_lib.php`](../site/public_html/includes/amiga_realm_games_hub_lib.php) or sibling — **directed A→B** (hero national on side A perspective: wins = A national won). Preserve `as=`. Hash `#matching-games` or existing games hub anchor.

**Rejected v1:** link to a single player's games page (no hero player on this surface).

---

## 6. UI — navigation chrome

### 6.1 Structure

```text
[ Realm hub bar — no active pill ]
[ Country hero — Germany ]
[ Roster · Rivals ]                    ← entity segment (existing)
[ H2H · W/D/L · Goals · DDs ]          ← Rivals wing segment (NEW — Rivals pages only)
[ content ]
```

Extend [`amiga_country_page.php`](../site/public_html/includes/amiga_country_page.php) + new `includes/amiga_country_rivals_nav.php` (mirror [`amiga_player_opponents_nav.php`](../site/public_html/includes/amiga_player_opponents_nav.php) wing row only — **no grain segment**).

Spacing: `--k2-nav-gap` bottom-only ([`nav-spacing-policy.md`](nav-spacing-policy.md)).

### 6.2 Active states

- Entity segment: Roster vs Rivals by `$k2AmigaCountryView`.
- Wing segment: H2H / W/D/L / Goals / DDs by `$k2AmigaCountryRivalsView`.

---

## 7. UI — Head-to-head (Rivals)

Path: `country/rivals/h2h.php`.

### 7.1 Pickers

| Player Opponents country | Rivals nation-pair |
|--------------------------|-------------------|
| Hero = player (fixed) | Hero = **country** (fixed from entity) |
| Two listboxes: countries hero faced | Two listboxes: **rival countries** hero nation faced |
| `country={opp token}` | `rival={token}` |

**No player search** v1.

### 7.2 Poster / detail

- **Subject card:** hero **country** — flag + token + roster link.
- **Opponent card:** rival **country** — same country card pattern as [player country H2H](../site/public_html/includes/amiga_player_opponents_country_h2h.php).
- **Centre record:** W/D/L from **A→B** bucket.
- **Pair detail strip:** goals, margins, DD/CS, nation-pair **Perf.** (and reverse if shipped).

### 7.3 Moments + charts

- **Moments:** game rows filtered to directed **A→B**; kicker labels use **rival country name** (e.g. *Italy's best haul* — not first word of *players from Italy*).
- **Charts:** cumulative wins/goals, histograms, heatmap — API params `country` + `rival` (realm=amiga). **No** rating/rank compare sections in DOM (**CRV13**).

Reuse chart JS context pattern from [`player-opponents-h2h-chart-context.js`](../site/public_html/js/player-opponents-h2h-chart-context.js) with `data-h2h-grain="nation-pair"`.

---

## 8. Files (expected)

| Area | Files |
|------|-------|
| **Pages** | `amiga/country/rivals/{h2h,wdl,goals,dds}.php` (thin entries) · legacy `amiga/country/rivals.php` redirect |
| **Shell / nav** | `includes/amiga_country_page.php` (Rivals branch) · `includes/amiga_country_rivals_nav.php` |
| **Load / render** | `includes/amiga_country_rivals_load.php` · `includes/amiga_country_rivals_tables.php` · `includes/amiga_country_rivals_h2h.php` · `includes/amiga_country_rivals_h2h_country_lib.php` (game rows + chart payloads) |
| **Perf** | `includes/amiga_country_rivals_perf_lib.php` |
| **Games filter** | extend realm games hub filter state for `country` + `rival` |
| **Charts** | extend `player_opponents_h2h_charts.php` or Rivals-specific render + API branches |
| **Routes** | `k2_amiga_routes.php` · `amiga_countries_lib.php` href helpers |
| **Docs** | This policy · implementation plan · `url-routes.md` · `amiga-countries-hub-policy.md` §9 |

---

## 9. Out of scope (v1)

- Persisted `amiga_country_matchup_*` tables + `prove` verify
- Realm-wide sortable nation-pair leaderboards
- WC-only rivals mode (use WC country slice)
- Medal comparison / shared WC history blocks (nice follow-up)
- Player search on Rivals H2H
- Rating/rank comparison charts
- Online realm port

---

## 10. Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Scan `amiga_games` for table aggregates | ~3× slower than matchup second roll-up at current scale; violates stored-truth habit |
| Undirected nation pair (single row for A–B) | Breaks Opponents/H2H directed semantics and domestic rules |
| Free-floating `/amiga/compare-countries.php` | CH19 — must live on country entity Rivals segment |
| Reuse `player/opponents/country/*` URLs | Wrong hero entity (player vs nation) |
| `?a=` / `?b=` symmetric params | `country=` already entity key; `rival=` mirrors `opponent=` |

---

## 11. Verification (development)

| Check | Method |
|-------|--------|
| Roll-up parity | Denmark→Sweden games/W/D/L = sum of directed pair rows (hero ∈ DK, opp ∈ SE) |
| Domestic excluded | Denmark Rivals W/D/L has **no** Denmark row; H2H `?country=Denmark` redirects to top foreign rival (e.g. Sweden) |
| Domestic roll-up (data only) | Raw A→A roll-up may still double-count compatriot games at player-pair level — **not shown** in Rivals UI |
| TT | Early `as=` ≤ present; rival list shrinks |
| Games links | W/D/L Games cell → `games/all.php?country=&rival=` filtered list |
| H2H | Pick Sweden on Germany Rivals; poster matches W/D/L row |
| Charts | No rating/rank headings in page source |

No `prove` gate v1 (read-time only).

---

## 12. Session log

| Date | Note |
|------|------|
| Jun 2026 | **Shipped CRV-1–7** — four wings, H2H charts, games filter. |
| Jun 2026 | **Domestic exclusion** — A→A dropped from all wings; H2H default rival = top cross-border pair. |

---

## 13. Agent cold-start checklist

1. Read this policy + [`amiga-country-rivals-implementation-plan.md`](amiga-country-rivals-implementation-plan.md).
2. Confirm slice id (**CRV-n**).
3. Hero = **`country=`** entity param; rival = **`rival=`** — not player ids.
4. Table aggregates = **second roll-up** on matchup rows — not games scan.
5. Domestic **A→A** excluded from Rivals UI (**CRV7**); H2H redirects to top foreign rival (**CRV15**).
6. Run slice **Verification** before next slice.