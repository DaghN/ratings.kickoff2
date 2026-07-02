# Amiga community stats — question catalog

**Status:** **Steps 3–5 complete** (Jul 2026) — **46 ship** curated; registry v2 + writers green (`036`/`037`, `prove`); chart IA **locked** — [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md). Step **6** = build track [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md) (45 panels / 46 IDs).  
**Method:** [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md)  
**Policy (shape):** [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md)

**How to read this doc**

- **Priority:** `ship` · `later` · `cut` (step 3 done).
- **Storage** S0–S7; **Impl** = cluster C0–C9 (writers done for C2–C7 grains; charts not started).
- **Lens:** L1 year · L2 snapshot cumulative · L3 year-local rate · L4 distribution at cutoff.

**Curation totals:** **46 ship** · **2 later** · **28 cut** (25 step 3 + 3 step 2) · **76** rows incl. 3 additions from curation comments

---

## Step 3 summary (Dagh)

| Priority | Count |
|----------|-------|
| **ship** | **46** |
| **later** | 2 |
| **cut** | 28 |

### Ship by wing

| Wing | Ship |
|------|------|
| Volume | 8 |
| Geography | 13 (10 + 3 cumulative companions) |
| World Cups | 6 (5 + goals/game per year) |
| Texture | 5 (4 year-local rates + high-scoring rate) |
| Shape | 13 |
| Event ecosystem | 1 |

### Writer numerators without a bar chart (important)

Several **cut** volume/texture count charts still need **year facts in finalize** because shipped **rate** charts derive from them:

| Store in C2/C5 | Feeds shipped chart | Volume/count chart |
|----------------|---------------------|-------------------|
| `draws` / year / realm | **Q-TEX-006** | Q-VOL-011 cut |
| `double_digits` / year / realm | **Q-TEX-008** | Q-VOL-013 cut |
| `clean_sheets` / year / realm | **Q-TEX-009** | Q-VOL-014 cut |
| `high_scoring_games` / year / realm | **Q-TEX-013** | Q-TEX-010 cut |
| `goals` / year / `world_cup` | **Q-WC-011** | Q-WC-005 cut |

---

## Product backlog (separate from Activity charts)

### Per–World Cup stats table(s)

**Spec:** [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) — **curated v1** (Jun 2026): must-have + nice = ship set; cut/defer excluded.

**Not in this catalog’s chart set.** One row per WC (`tournament_id`) for event-local texture (goals/game, draw rate, high-scoring rate, participants, nations, …). Informs why **Q-WC-004**, **Q-WC-005** (count), **Q-WC-009**, **Q-WC-010** are cut as charts; **Q-WC-006**, **Q-WC-007** ship as realm-year bars and **complement** (not replace) the WC table.

### Histogram chart UX (L4 ship rows)

**Q-SHP-006**, **007**, **008** — long tails (many players at low N, few at 100+ tournaments). Chart spec: **bucketed histogram** (e.g. 1, 2–5, 6–10, 11–20, 21–50, 51+) or **log-scale** tail; exact buckets = UI decision at chart slice.

**Q-SHP-015** — also a natural **hero profile** stat (“active calendar years”).

---

## Shipped set — quick list (46)

`Q-VOL-001`–`008` · `Q-GEO-001`–`010`, `013`–`015` · `Q-WC-001`–`003`, `006`–`007`, `011` · `Q-TEX-006`–`009`, `013` · `Q-SHP-001`–`010`, `014`–`016` · `Q-ECO-004`

---

## Implementation clusters (step 4 — ship only)

| Cluster | Shipped IDs | Deliverable |
|---------|-------------|-------------|
| **C0 — Snapshot charts** | Q-VOL-002,004,008 · (no TEX L2 ship) | Chart APIs |
| **C1 — v1 fact charts** | Q-VOL-001,003,007 · Q-GEO-001,002,005,006,007 | `facts_query` + panels |
| **C2 — Realm year numerators** | Q-VOL-005 · Q-SHP-001,009 · hidden: `draws`, `dd`, `cs`, `high_scoring` | One writer extension |
| **C3 — Host country** | Q-GEO-003,004,008,010 · Q-GEO-013,014 | Year + `all_time` goals/tournaments |
| **C4 — Nationality cumulative goals** | Q-GEO-015 | `all_time` + `player_nationality` + `goals` |
| **C5 — World Cup slice** | Q-WC-001,003,006,007 · numerators for Q-WC-011 | `world_cup` year facts + Q-WC-002 S1 |
| **C7 — Headline S1** | Q-VOL-006 · Q-GEO-009 · Q-SHP-002,010 | Snapshot cols |
| **C8 — L4 probes → APIs** | Q-SHP-003–008,014–016 | Read oracles + TT |
| **C9 — Derive charts** | Q-TEX-006–009,013 · Q-WC-003,011 · Q-ECO-004 | API math only |

**Dropped clusters for v2:** C6 event class (all cut).

---

## Volume

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-VOL-001 | Rated games per calendar year? | L1 | bar | S0v | v1 | yes | **ship** | |
| Q-VOL-002 | Cumulative rated games after each event? | L2 | line | S0 | chart-only | yes | **ship** | |
| Q-VOL-003 | Active players per calendar year? | L1 | bar | S0v | v1 | yes | **ship** | |
| Q-VOL-004 | Cumulative players after each event? | L2 | line | S0 | chart-only | yes | **ship** | |
| Q-VOL-005 | Tournaments finalized per calendar year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-VOL-006 | Cumulative tournament count after each event? | L2 | line | S1 | new-writer | yes | **ship** | |
| Q-VOL-007 | Goals per calendar year? | L1 | bar | S0v | v1 | yes | **ship** | |
| Q-VOL-008 | Cumulative goals after each event? | L2 | line | S0 | chart-only | yes | **ship** | |
| Q-VOL-009 | Decided games per calendar year? | L1 | bar | S2 | new-writer | yes | cut | |
| Q-VOL-010 | Cumulative decided games after each event? | L2 | line | S0 | chart-only | yes | cut | |
| Q-VOL-011 | Draws per calendar year? | L1 | bar | S2 | numerator-only | yes | cut | **Writer:** store `draws` for Q-TEX-006 |
| Q-VOL-012 | Cumulative draws after each event? | L2 | line | S0 | chart-only | yes | cut | |
| Q-VOL-013 | Double-digit games per calendar year? | L1 | bar | S2 | numerator-only | yes | cut | **Writer:** store `dd` for Q-TEX-008 |
| Q-VOL-014 | Clean-sheet games per calendar year? | L1 | bar | S2 | numerator-only | yes | cut | **Writer:** store `cs` for Q-TEX-009 |

---

## Geography

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-GEO-001 | Games in host country X per year? | L1 | bar | S0v | v1 | yes | **ship** | Country comparisons |
| Q-GEO-002 | Cumulative games in host country X? | L2 | multi-line | S0v | v1 | yes | **ship** | |
| Q-GEO-003 | Goals in host country X per year? | L1 | bar | S2 | new-writer | yes | **ship** | + Q-GEO-013 cumulative |
| Q-GEO-004 | Tournaments hosted in country X per year? | L1 | bar | S2 | new-writer | yes | **ship** | + Q-GEO-014 cumulative |
| Q-GEO-005 | Nationality X appearances per year? | L1 | bar | S0v | v1 | yes | **ship** | |
| Q-GEO-006 | Goals by nationality X per year? | L1 | bar | S0v | v1 | yes | **ship** | + Q-GEO-015 cumulative |
| Q-GEO-007 | Cumulative nationality X appearances? | L2 | multi-line | S0v | v1 | yes | **ship** | |
| Q-GEO-008 | Distinct host countries per year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-GEO-009 | Cumulative distinct host countries? | L2 | line | S1 | new-writer | yes | **ship** | |
| Q-GEO-010 | Distinct nationalities active per year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-GEO-011 | When did each host country first appear? | L2 | table | S5 | probe | yes | cut | |
| Q-GEO-012 | Share of games hosted in country X per year? | L3 | stacked % | S0d | derive | yes | cut | |
| Q-GEO-013 | Cumulative goals in host country X? | L2 | multi-line | S2 | new-writer | yes | **ship** | *Added step 3 — companion to Q-GEO-003* |
| Q-GEO-014 | Cumulative tournaments in host country X? | L2 | multi-line | S2 | new-writer | yes | **ship** | *Added step 3 — companion to Q-GEO-004* |
| Q-GEO-015 | Cumulative goals by nationality X? | L2 | multi-line | S2 | new-writer | yes | **ship** | *Added step 3 — companion to Q-GEO-006* |

---

## World Cups

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-WC-001 | WC rated games per calendar year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-WC-002 | Cumulative WC games after each event? | L2 | line | S1 | new-writer | yes | **ship** | |
| Q-WC-003 | % of games in year Y that were WC? | L3 | bar % | S0d | derive | yes | **ship** | |
| Q-WC-004 | WC tournaments per calendar year? | L1 | bar | S2 | new-writer | yes | cut | Prefer per-WC table (backlog) |
| Q-WC-005 | Goals in WC games per year? | L1 | bar | S2 | numerator-only | yes | cut | **Writer:** WC `goals` for Q-WC-011 |
| Q-WC-006 | Distinct nations in WC games per year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-WC-007 | Distinct active players in WC per year? | L1 | bar | S2 | new-writer | yes | **ship** | ~WC participants; see WC table backlog |
| Q-WC-009 | WC draw rate in year Y? | L3 | bar | S2 | new-writer | yes | cut | Per-WC table backlog |
| Q-WC-010 | Cumulative WC goals per game after each event? | L2 | line | S0d | derive | yes | cut | |
| Q-WC-011 | WC goals per game in year Y only? | L3 | bar | S0d | derive | yes | **ship** | *Added step 3*; Q-WC-005 goals ÷ Q-WC-001 games |

---

## Texture

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-TEX-001 | All-time draw rate after each event? | L2 | line | S0 | chart-only | yes | cut | Data exists; no chart |
| Q-TEX-002 | All-time goals per game after each event? | L2 | line | S0 | chart-only | yes | **later** | Year-local may be enough |
| Q-TEX-003 | All-time DD rate after each event? | L2 | line | S0 | chart-only | yes | **later** | Year-local may be enough |
| Q-TEX-004 | All-time CS rate after each event? | L2 | line | S0 | chart-only | yes | cut | |
| Q-TEX-005 | All-time decided-game rate after each event? | L2 | line | S0 | chart-only | yes | cut | |
| Q-TEX-006 | Draw rate in year Y only? | L3 | bar | S0d | derive | yes | **ship** | Needs `draws` numerator |
| Q-TEX-007 | Goals per game in year Y only? | L3 | bar | S0d | derive | yes | **ship** | |
| Q-TEX-008 | DD rate in year Y only? | L3 | bar | S0d | derive | yes | **ship** | Needs `double_digits` numerator |
| Q-TEX-009 | CS rate in year Y only? | L3 | bar | S0d | derive | yes | **ship** | Needs `clean_sheets` numerator |
| Q-TEX-010 | High-scoring games (sum ≥ 10) per year? | L1 | bar | S2 | numerator-only | yes | cut | Rate only — Q-TEX-013 |
| Q-TEX-011 | 0–0 draws per year? | L1 | bar | S2 | new-writer | yes | cut | |
| Q-TEX-013 | High-scoring game rate (sum ≥ 10) in year Y? | L3 | bar | S0d | derive | yes | **ship** | *Added step 3* |

---

## Shape (breadth & distributions)

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-SHP-001 | Distinct opponent pairs per year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-SHP-002 | Cumulative distinct pairs after each event? | L2 | line | S1 | new-writer | yes | **ship** | |
| Q-SHP-003 | Players with exactly N countries played? | L4 | histogram | S4 | probe | yes | **ship** | Bucketed chart |
| Q-SHP-004 | Players with exactly N World Cups played? | L4 | histogram | S4 | probe | yes | **ship** | |
| Q-SHP-005 | Games with goal sum N at cutoff? | L4 | histogram | S5 | probe | yes | **ship** | |
| Q-SHP-006 | Tournaments with exactly N rated games? | L4 | histogram | S4 | probe | yes | **ship** | Bucketed chart |
| Q-SHP-007 | Players with exactly N career games? | L4 | histogram | S4 | probe | yes | **ship** | Bucketed / log tail |
| Q-SHP-008 | Players with exactly N tournaments played? | L4 | histogram | S4 | probe | yes | **ship** | Bucketed / log tail |
| Q-SHP-009 | New player debuts per calendar year? | L1 | bar | S2 | new-writer | yes | **ship** | |
| Q-SHP-010 | Cumulative debuts after each event? | L2 | line | S1 | new-writer | yes | **ship** | |
| Q-SHP-011 | Players with only one tournament ever? | L4 | stat | S4 | probe | yes | cut | |
| Q-SHP-012 | Share of year Y games by top 10 players? | L3 | bar % | S4 | probe | yes | cut | |
| Q-SHP-013 | Median career games per player? | L4 | stat | S4 | probe | yes | cut | |
| Q-SHP-014 | Players with exactly N distinct opponents? | L4 | histogram | S4 | probe | yes | **ship** | |
| Q-SHP-015 | Players active in exactly N calendar years? | L4 | histogram | S5 | probe | yes | **ship** | Hero profile candidate |
| Q-SHP-016 | Rating distribution of active players? | L4 | histogram | S4 | probe | yes | **ship** | |

---

## Event ecosystem

| ID | Question | Lens | Chart | Storage | Impl | TT | Priority | Notes |
|----|----------|------|-------|---------|------|-----|----------|-------|
| Q-ECO-001 | Kitchen tournaments per year? | L1 | bar | S2 | new-writer | yes | cut | |
| Q-ECO-002 | Open tournaments per year? | L1 | bar | S2 | new-writer | yes | cut | |
| Q-ECO-003 | WC + open + kitchen mix per year? | L1 | stacked bar | S0d | derive | yes | cut | |
| Q-ECO-004 | Average games per tournament per year? | L3 | bar | S0d | derive | yes | **ship** | Q-VOL-001 ÷ Q-VOL-005 |
| Q-ECO-005 | Average standing field size per year? | L3 | bar | S4 | probe | yes | cut | |
| Q-ECO-006 | Total knockout ties per year? | L1 | bar | S4 | probe | yes | cut | |
| Q-ECO-007 | Total league scopes per year? | L1 | bar | S4 | probe | yes | cut | |
| Q-ECO-008 | Largest tournament by games each year? | L1 | table | S5 | probe | present-only | cut | |
| Q-ECO-010 | Tournaments with zero rated games? | L4 | stat | S4 | probe | yes | cut | |
| Q-ECO-011 | Cumulative kitchen vs open games? | L2 | multi-line | S2 | new-writer | yes | cut | |
| Q-ECO-012 | Tournament size distribution by year? | L4 | small multiples | S4 | probe | yes | cut | |

---

## Cut log (step 2 — unchanged)

| ID | Reason |
|----|--------|
| Q-WC-008 | Derive: Q-VOL-001 − Q-WC-001 |
| Q-ECO-009 | Duplicate of Q-SHP-006 (step 2) |
| Q-TEX-012 | Chart bundle (step 2) |

---

## Registry v2 sketch (step 4 input — ship-driven)

**Year facts — realm:** `tournaments`, `draws`, `double_digits`, `clean_sheets`, `high_scoring_games`, `distinct_pairs`, `player_debuts` (+ v1 `games`, `goals`, `active_players`)

**Year facts — host_country:** `goals`, `tournaments`, `distinct_host_countries` (realm), `distinct_nationalities_active` (realm)

**Year facts — all_time at snapshot:** `host_country` + `goals` / `tournaments`; `player_nationality` + `goals`

**Year facts — world_cup:** `games`, `goals`, `active_players`, `distinct_nationalities` (metric name TBD)

**Headline S1:** `TournamentsFinalized`, `DistinctHostCountries`, `WcGamesPlayed`, `DistinctOpponentPairs`, `PlayersDebuted` — `WcGoalsScored` only if needed for derivations (Q-WC-011 uses year facts)

**Not in v2:** `event_class` slice (ECO cut)

---

## Next steps

| Step | Status |
|------|--------|
| **3** Curation | **Done** — 46 ship |
| **4** Registry v2 + writers + `prove` | **Done** Jun 2026 |
| **5** Chart IA + track plan | **Done** Jul 2026 — [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) |
| **6** Chart APIs + Activity UI (C0–C9) | **Next** — [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md) slices 0–10 |

*Step 3 applied Jun 2026 — Dagh curation; 3 rows added (GEO-013–015, WC-011, TEX-013). Step 4 writers shipped Jun 2026-23.*
