# Amiga community stats — question catalog plan (v2 product)

**Status:** **Steps 0–6 done** (Jul 2026) — **49 ship**, registry v2 writers green; chart track **shipped** — [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) + [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md) (48 panels / 49 IDs). **Jul 2026 extension:** Nations player grains (Q-GEO-016…018) — [`amiga-activity-geography-nations-players-policy.md`](amiga-activity-geography-nations-players-policy.md).  
**Policy (shape):** [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) — **do not reopen** C1–C13 without Dagh.  
**V1 implementation (done):** [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) — slices 1–10 + Phase 2 verify hygiene.  
**Living product artifact:** [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) — step **3** done (**49 ship** incl. Jul 2026 extension).

**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-realm-vision.md`](amiga-realm-vision.md) (online↔Amiga skips) · online pattern [`activity-charts.md`](activity-charts.md)

**Derived writes:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) — registry v2 ships only after catalog curation; corrections = `prove` only.

---

## 1. Executive summary

V1 community stats shipped **infrastructure**: three tables, finalize writers, headline Activity summary, v1 fact registry (8 grains), verify in `prove`. The **metric catalog** was intentionally minimal — a structural seed, not a comprehensive product definition.

**V2 product work** narrows “what should we add?” by curating **questions we want charts to answer**, then deriving a minimal stored-truth registry from that list. Storage shape stays on the policy; this plan owns **method, lens taxonomy, wings IA, and storage decision rules**.

| Principle | Rule |
|-----------|------|
| **Question-first** | Charts follow questions; DB fields follow kept questions — not the reverse |
| **Year lens for breakdowns** | Calendar-year bars for volume, geography, WC, event-class slices — **not** month (activity too sparse) |
| **Snapshot timeline for realm cumulatives** | Games / players / goals / tournaments over time → headline snapshots at each `tournament_id` (~605 checkpoints) |
| **Texture has three meanings** | Year-local counts · year-local rates · cumulative rates at event (see §4) |
| **Distributions per-question** | Investigate store vs read-from-player-snapshots per histogram — no blind finalize bloat |
| **Sub-wings when needed** | Tag questions by wing early so Activity IA can split before chart count explodes |

---

## 2. Relationship to existing docs

| Doc | Role in v2 |
|-----|------------|
| [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) | Locked storage shape (headline + facts + snapshots); skips (§9) |
| [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) | V1 build track **complete**; Phase 2 verify hygiene **done**; follow-on product deferred here |
| **This plan** | How to build the v2 question catalog and derive registry + charts |
| [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md) | Curated questions table — step **3** done |
| [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) | Per-WC event table spec (parallel to registry v2; curation pending) |
| Future implementation slices | Registry v2 writers, chart APIs, Activity sub-wings — **after** catalog sign-off |

**Do not** add v2 metric lists to the policy file. Policy defines shape; catalog defines product.

---

## 3. What v1 already gives us (inventory baseline)

### Headline (`amiga_community_stats` + snapshots) — 14 columns

Realm-wide cumulative scalars at present and at every finalized event: players, games, draws, goals, DDs, clean sheets, averages and ratios (`DrawsRatio`, `GoalsPerGameAverage`, …).

**Already answers (snapshot timeline, often no new storage):**

- Cumulative games / players / goals over event time
- Cumulative all-time texture (draw rate, goals/game, DD rate, CS rate) **after each event**

### Facts (`amiga_community_stat_facts`) — v1 registry (8 patterns) + Jul 2026 nationality player extension

Calendar-**year** grains: realm games/goals/active_players; host_country games; player_nationality games/goals/**active_players**/**player_debuts**/**wc_active_players**; all_time host_country and nationality games/**active_players**.

Detail: [`amiga-activity-geography-nations-players-policy.md`](amiga-activity-geography-nations-players-policy.md) §4.

### Website

`/amiga/activity/` — summary block + **48 chart panels** across six wings (read-time APIs + stored facts). Helpers: `amiga_community_facts_query()`, chart APIs under `/api/amiga_community_*`.

### Boundaries (not community stats)

| Grain | Store |
|-------|-------|
| Record **holders** | `amiga_generalstats` / `amiga_realm_snapshots` |
| **Player** career | `amiga_player_current` / event snapshots |
| **Player × WC** career | `amiga_player_slice_*` |
| **Per-tournament** index | `amiga_tournament_catalog_stats` |

---

## 4. Lens taxonomy (steer the catalog)

Every catalog row must declare a **lens**. These are different questions — do not collapse them.

### L1 — Calendar year (breakdown bars)

*“How much happened in calendar year Y?”*

- Games, draws, goals, tournaments, active players, WC games, …
- Sliced: host country, player nationality, `world_cup`, event class, …
- **Storage:** `amiga_community_stat_facts` with `period_type = year`, `period_key = YYYY`
- **Chart:** bar (or stacked bar) per year
- **TT:** facts at cutoff `tournament_id` T

**Default period vocabulary for dimensional stats.** Month is **out of scope** unless a future question explicitly requires it (unlikely).

### L2 — Event / snapshot timeline (cumulative curves)

*“What was the running realm total after event E?”*

- Cumulative games, players, goals, tournaments, …
- Cumulative **all-time** texture ratios (draw rate, goals/game, …)
- **Storage:** headline snapshot columns at each `tournament_id` — **prefer existing columns**
- **Chart:** line (or stepped) vs `event_date` or event index; rich tooltips with tournament name
- **TT:** read snapshot at cutoff — canonical

**Default for realm-wide cumulative charts.** Do **not** default to summing year facts for cumulatives; the ~605 snapshot series is the Amiga-native asset.

New headline columns (e.g. `TournamentsFinalized`) are **deliberate DDL** — add only when a kept L2 question needs a scalar not already on snapshots.

### L3 — Year-local texture (rates in year Y only)

*“How scratchy was year Y specifically?”* — **not** the same as cumulative ratio at end of year.

- Draw rate among games **played in** Y; goals/game in Y; DD rate in Y; …
- **Storage:** year facts with **numerators** (`draws`, `games`, `goals`, …); rates at read time unless product needs stored rate
- **Chart:** bar per year
- **Contrast L2:** L2 `DrawsRatio` on snapshot = all-time rate **after** the event that closed Y (includes all prior years)

### L4 — Distributions (histogram at cutoff)

*“How many players (or games) fall in bucket N?”*

- Players who played in N countries; players in N World Cups; games with goal sum N; tournament size histogram; …
- **Storage:** **per-question decision** (§6) — not one default pattern
- **Chart:** category bar histogram
- **TT:** required — histogram must reflect state **as of cutoff T**

---

## 5. Question families (catalog sections)

Use these as **wing / section** tags in the question catalog.

| Wing | Example questions |
|------|-------------------|
| **Realm volume** | Games per year; tournaments per year; cumulative games/players/tournaments over events |
| **Calendar years** | Year-over-year comparison bars (realm totals) |
| **Geography** | Games in host country X per year; nationality X appearances per year; distinct countries active per year |
| **World Cups** | WC games per year; WC share of all games; nations at WC per year |
| **Texture** | Year-local draw/DD/CS/goals rates; cumulative texture on snapshot timeline |
| **Breadth & shape** | Matchup pairs per year; player/country/WC histograms; debut players per year |
| **Event ecosystem** | Kitchen vs open vs WC tournament counts per year; avg games per tournament per year |

Online Activity ([`activity-charts.md`](activity-charts.md)) is a **pattern reference** — port the *question* where Amiga semantics fit; **skip** UTC daily pulse, 30-day rolling active, milestones/established cohorts, and month-level texture unless explicitly revived.

---

## 6. Storage decision framework

For each catalog row, assign exactly one **storage class** before implementation.

| Class | When | Examples |
|-------|------|----------|
| **S0 — Existing snapshot headline** | L2 cumulative scalar or ratio already on snapshots | `GamesPlayed`, `DrawsRatio` timeline |
| **S1 — New headline column** | L2 cumulative scalar missing from snapshots | `TournamentsFinalized` (if kept) |
| **S2 — Year fact (numerator)** | L1 or L3 count in year Y | `tournaments`, `draws`, `wc_games` + `games` |
| **S3 — Year fact (rate)** | Only if read-time ratio from S2 is awkward | Rare; prefer derive |
| **S4 — Investigate player snapshots** | L4 player histogram at cutoff | Countries played, WC count buckets |
| **S5 — Investigate game scan / oracle** | L4 game histogram at cutoff | Goal-sum distribution |
| **S6 — New histogram store** | S4/S5 probe fails perf or TT ergonomics | Only for **shipped** hero charts |
| **S7 — Reject / defer** | Policy skip, wrong grain, or low priority | UTC daily active, milestones |

### Distribution rule

**Do not** precompute histograms “because we might chart them.”

1. Add question to catalog with L4 lens.
2. Default **S4 or S5** (read from materialized player/game state at cutoff).
3. Probe on work DB at representative cutoffs (first / mid / latest + TT).
4. Promote to **S6** only if kept **and** probe shows pain.

Player histograms at cutoff T naturally oracle from `amiga_player_event_snapshots` / `amiga_player_slice_at_event` / `amiga_player_current` (present) — not necessarily `amiga_community_stat_facts`.

### Cumulative from year facts

Summing year bars to draw a cumulative line is acceptable as a **present-only chart shortcut** in some cases. **Canonical** cumulative story remains **L2 snapshots**. Catalog should mark which charts are snapshot-canonical vs derivable shortcut.

---

## 7. Activity hub IA — sub-wings

Tag every catalog question with a **wing**. When a wing accumulates ~6–10 charts, plan a dedicated sub-area under `/amiga/activity/` (folder + hub nav segment) — same habit as Leaderboards foldered wings.

**Candidate wings (initial):**

1. **Volume** — realm growth, tournaments, games per year  
2. **Geography** — host + nationality year breakdowns  
3. **World Cups** — community WC lens (not player WC LB)  
4. **Texture** — year-local and cumulative rates  
5. **Shape** — histograms, breadth, debuts  

Single long `activity.php` is fine for v2 first ship; split when wing chart count warrants it. [`url-routes.md`](url-routes.md) § Sub-hub navigation applies when adding folders.

---

## 8. Hard skips (do not catalog)

From policy §9 and [`amiga-realm-vision.md`](amiga-realm-vision.md):

- UTC daily / weekly “active players” and server pulse  
- Match streaks and calendar play streaks  
- Online milestones / established-20 cohort charts  
- HoF record holders (wrong grain)  
- Per-tournament catalog index stats  
- Month-period community facts (default **out** unless Dagh reopens)

---

## 9. Workflow (execution order)

| Step | Deliverable | Owner |
|------|-------------|-------|
| **0** | This plan doc | **Done** |
| **1** | Create [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md); brainstorm wide (50–80 questions) | **Done** — 76 rows |
| **2** | Cluster by wing + lens; dedupe; mark S0–S7 | **Done** — 73 active, 3 cut, 9 clusters |
| **3** | Dagh curates: priority (ship / later / cut) | **Done** — **49 ship** (46 Jun 2026 + Q-GEO-016…018 Jul 2026), 2 later, 28 cut |
| **4** | Derive **registry v2** + finalize writers + `prove` | **Done** Jun 2026 — `community_stat_registry.py` v2 grains, DDL `036`/`037`, `verify-community-stats` green |
| **5** | Close **open chart IA** (§9.1) + chart track plan | **Done** Jul 2026 — [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) + [`amiga-activity-charts-implementation-plan.md`](amiga-activity-charts-implementation-plan.md) |
| **6** | Chart APIs + Activity UI (clusters C0–C9) | **Done** Jul 2026 — impl plan slices 0–10 + Nations player extension (48 panels / 49 IDs) |

**STOP gates:**

- No new finalize writers until step **3** sign-off. *(Step 4 complete — gate lifted for v2 grains.)*  
- No DDL without a catalog row referencing it.  
- `prove` must stay green after each implementation slice.  
- No full **46-row** chart implementation plan until step **5** IA decisions (§9.1) are closed or explicitly deferred per row.

### 9.1 Chart-track IA decisions — **closed Jul 2026**

Resolved in [`amiga-activity-charts-policy.md`](amiga-activity-charts-policy.md) (§11 decision register there is authoritative):

| # | Decision | Resolution |
|---|----------|------------|
| **IA-1** | One page vs split URLs | **Split** — foldered sub-hub `/amiga/activity/`, six wings / seven leaf pages |
| **IA-2** | Section grouping + order | **Growth · People · Geography (Hosts/Nations) · World Cups · Texture · Shape** |
| **IA-3** | Multi-line geography UX | **Duel bars + race multi-lines**; defaults by all-time volume at cutoff; `?hosts=`/`?nats=` URL state |
| **IA-4** | C8 histogram probes | **Probe gate** = implementation plan slice 8 (STOP before Shape UI) |
| **IA-5** | Histogram bucket policy | **Defaults locked** in policy §5.6/§10; edges adjustable at probe slice |

---

## 10. Question catalog row template

Each row in the living catalog should include:

| Column | Purpose |
|--------|---------|
| **ID** | Stable tag (`Q-GEO-003`) |
| **Question** | Plain language |
| **Lens** | L1 / L2 / L3 / L4 |
| **Wing** | Volume · Geography · … |
| **Chart type** | bar · line · histogram · multi-line · … |
| **Storage class** | S0–S7 |
| **TT** | yes / present-only |
| **Priority** | ship · later · cut |
| **Notes** | Oracle source, v1 overlap, online analogue |

---

## 11. Agent notes

- Read **this plan** + **policy** before editing the catalog or registry.  
- V1 code registry: `scripts/amiga/community_stat_registry.py` + PHP mirror.  
- Texture: before adding L3 year facts, check whether L2 snapshot ratios already answer the cumulative chart.  
- WC: community `slice_type = world_cup` for realm-wide WC volume; player WC career stays on `amiga_player_slice_*`; per-WC event table → [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md).  
- Part B registers apply when step 5 ships new DDL or writers — not when editing catalog docs alone.

---

## 12. Related docs

| Doc | Relationship |
|-----|----------------|
| [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) | Storage shape authority |
| [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) | V1 complete; § Follow-on product |
| [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) | Cutoff reads for charts |
| [`activity-charts.md`](activity-charts.md) | Online panel registry (pattern) |
| [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) | Per-WC table product spec; registry v2 impact §7 |
| [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) | Calendar year H1, country token H8 |

*Plan initiated Jun 2026 — question-first v2 product pass after v1 infrastructure ship.*
