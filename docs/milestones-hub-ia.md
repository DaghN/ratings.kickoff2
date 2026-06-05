# Milestones hub — IA & build plan

**Kick Off 2 ratings site · Jun 2026**

**Status:** **v0 shipped** in repo (Recent · Catalog · `milestone.php`). **This doc** tracks **future** hub phases (Story, Charts migration, etc.) — subject to change. **Committed hub routing** lives in [`hub-ia-agreement.md`](hub-ia-agreement.md).

**Hub v2 (repo):** Two sub-nav items — **Recent** (`milestones.php`) tier filter + vertical unlock feed (**100** rows, fixed; each row = date + **three tier-colored links** (player · milestone · event) + muted **rule** column) and **Catalog** (`milestones.php?view=catalog`) four tier sections (garden headings) + compact cards (no glow); rule text **wraps** with `min-height` for two-line rhythm — **no** `-webkit-line-clamp` / hidden `<br>` pad (that falsely triggered `…`). `entered_arena` first in band; rarity within band. Single-milestone package: **`milestone.php?key=`** (spotlight; **Made it** | **Graphs**; Graphs = **New unlocks per year** + **Cumulative unlocks** for every key, tier chart colors, full ladder span). Established rating distribution lives on **Activity** (`activity.php`) only. Activity summary includes **busiest day** (from `server_period_game_totals`); milestone digest panel removed Jun 2026.

---

## Purpose

Single **public home** for the milestone system:

- Explain what milestones are (many holders; not Hall of Fame records).
- Browse all **112** catalog keys and open **achiever lists** per key.
- Show **recent unlocks** and (later) server-wide **story** feeds.
- Host **milestone-domain charts** migrated from Activity when ready.

**Not here:** per-player garden (profile `player/milestones.php`); competitive meta-sort (`leaderboards/milestones.php` Leaderboards wing); single-holder extremes (`hall-of-fame.php` Hall of Fame).

---

## Hub placement

| Item | Choice |
|------|--------|
| Top-level tab | **Milestones** — after Leaderboards, before Hall of Fame |
| Page | `milestones.php` |
| HoF achievers | **Removed** from `hall-of-fame.php` — per-key lists on `milestone.php` |

---

## Internal sub-nav (shipped v2)

| Sub-tab | URL | Role |
|---------|-----|------|
| **Recent** | `milestones.php` | Tier filter = `.k2-ms-recent-tier-filter` segment bar (`data-k2-carry-scroll`, **tier-colored** labels); centred over table; fixed cols (`--k2-ms-recent-col-*`) |
| **Catalog** | `milestones.php?view=catalog` | Four tier sections (garden headings); `entered_arena` first in its band; rarity within band |

Single milestone (no sub-nav tab):

- `milestone.php?key={milestone_key}` — spotlight card; segment **Made it** | **Graphs** (`?panel=graphs`); achievers table + charts in panels; hub sub-nav; segment bars use **`data-k2-carry-scroll`** (same y-lock as hub pills)
- `milestones.php?key=` → 302 to `milestone.php` (legacy)

---

## Home — target layout (WIP)

1. **Context block** — 2–4 sentences: shared career landmarks; many players per feat; link to HoF for single-holder records.
2. **Recent milestones** — global ticker / table from `player_milestones` (newest unlocks first); not mixed with server “busiest day” facts.
3. **Catalog navigator** — four panels (Legendary → Aspirational per [`milestones-catalog.md`](milestones-catalog.md) / product spec). Each milestone: title + short rule in **tier color**; acts as in-page picker (not 112 separate routes).
4. **Achievers pane** — when a key is selected: table newest-first (spec default); columns player, unlocked (UTC), match/game link where `source_kind` allows. Optional sorts: **first unlock**, **latest unlock**.

### Per-key unlock chart (idea — v1.1 OK)

Beside achievers list: bar chart (or step) of **unlock counts over time** for that key only (bucket by month/year from `achieved_at`). Loads **on key select** — do not preload 110 charts.

Data: `player_milestones` + `milestone_definitions`; aggregate query or API per key. Stored-truth friendly.

---

## Story (planned)

Chronological unlock feed — server-wide or curated slice. Tier dot/stripe per row. Complements Home; does not replace per-key achievers.

---

## Charts (planned; defer with Activity slim)

Migrate from `activity.php` when hub exists:

- Established chart group (Activity still has these on `activity.php` until hub Charts exists)

Until then, Activity keeps established charts ([`hub-ia-agreement.md`](hub-ia-agreement.md)); busiest day lives in the summary fact row. DD merchant / participation-depth Activity APIs and chart JS **deleted** Jun 2026.

---

## Relationships

| Surface | Relationship |
|---------|----------------|
| `leaderboards/milestones.php` | Meta-leaderboard (“most milestones”); link into hub for per-key lore |
| `player/milestones.php` | Personal garden; hub links “your milestones” when logged in via profile |
| `hall-of-fame.php` | Remove trial **Milestone achievers** section when hub hosts DD Merchant + more |
| `activity.php` | Charts/digest migrate later; no Activity slim in stub slice |
| Status | No planned unlock teaser strip (deferred; not documented elsewhere) |

---

## Build phases (suggested)

| Phase | Deliverable |
|-------|-------------|
| **0 (done)** | Hub tab + this WIP doc |
| **1 (done)** | Home: intro + recent unlocks + tier catalog + achievers for any `?key=` |
| **2** | All keys achievers; `?key=` routing; migrate HoF achievers block |
| **3** | Per-key unlock chart (lazy) |
| **4** | Story sub-tab |
| **5** | Charts sub-tab; slim Activity |

---

## Out of scope (explicit)

- Achiever lists on Hall of Fame tab (forbidden long-term).
- Status panel milestone strip.
- League stat silo (leagues stay on Status + League honours wing).
- 110 static PHP pages.

---

*WIP recorded May 2026. Revise freely; update [`hub-ia-agreement.md`](hub-ia-agreement.md) only when routing contract changes.*
