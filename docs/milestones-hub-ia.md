# Milestones hub — IA & build plan (WIP)

**Kick Off 2 ratings site · May 2026**

**Status:** **Work in progress — subject to change.** This doc is the sandbox for the server-side Milestones universe. **Committed hub routing** (tab order, Games demotion) lives in [`hub-ia-agreement.md`](hub-ia-agreement.md). Tier bands and profile garden: [`milestones-product-spec.md`](milestones-product-spec.md).

**Hub v2 (repo):** Two sub-nav items — **Recent** (`milestones.php`) tier filter + vertical unlock feed (**100** rows, fixed) and **Catalog** (`milestones.php?view=catalog`) tier-colored cards sorted by holder count. Single-milestone package: **`milestone.php?key=`** (achievers + timeline chart + signature charts for DD / Established). Activity digest/charts still on `server1.php`.

---

## Purpose

Single **public home** for the milestone system:

- Explain what milestones are (many holders; not Hall of Fame records).
- Browse all **110** catalog keys and open **achiever lists** per key.
- Show **recent unlocks** and (later) server-wide **story** feeds.
- Host **milestone-domain charts** migrated from Activity when ready.

**Not here:** per-player garden (profile `individual_milestones.php`); competitive meta-sort (`ranked10.php` Leaderboards wing); single-holder extremes (`server2.php` Hall of Fame).

---

## Hub placement

| Item | Choice |
|------|--------|
| Top-level tab | **Milestones** — after Leaderboards, before Hall of Fame |
| Page | `milestones.php` |
| HoF achievers | Stay on `server2.php` until hub can host lists; then **remove** from HoF |

---

## Internal sub-nav (shipped v2)

| Sub-tab | URL | Role |
|---------|-----|------|
| **Recent** | `milestones.php` | Tier filter (All / band) + vertical scannable feed (100 unlocks, no count UI) |
| **Catalog** | `milestones.php?view=catalog` | All milestones as tier-colored cards, sorted by holder count |

Single milestone (no sub-nav tab):

- `milestone.php?key={milestone_key}` — achievers, unlock timeline chart, signature charts when defined
- `milestones.php?key=` → 302 to `milestone.php` (legacy)

---

## Home — target layout (WIP)

1. **Context block** — 2–4 sentences: shared career landmarks; many players per feat; link to HoF for single-holder records.
2. **Recent milestones** — global ticker / table from `player_milestones` (newest unlocks first); not mixed with server “busiest day” facts.
3. **Catalog navigator** — four panels (Legendary → Aspirational per [`milestones-tier-curated.md`](milestones-tier-curated.md) presentation order, or Aspirational → Legendary if product reverses). Each milestone: title + short rule in **tier color**; acts as in-page picker (not 110 separate routes).
4. **Achievers pane** — when a key is selected: table newest-first (spec default); columns player, unlocked (UTC), match/game link where `source_kind` allows. Optional sorts: **first unlock**, **latest unlock**.

### Per-key unlock chart (idea — v1.1 OK)

Beside achievers list: bar chart (or step) of **unlock counts over time** for that key only (bucket by month/year from `achieved_at`). Loads **on key select** — do not preload 110 charts.

Data: `player_milestones` + `milestone_definitions`; aggregate query or API per key. Stored-truth friendly.

---

## Story (planned)

Chronological unlock feed — server-wide or curated slice. Tier dot/stripe per row. Complements Home; does not replace per-key achievers.

---

## Charts (planned; defer with Activity slim)

Migrate from `server1.php` when hub exists:

- Milestone digest cards (`server-milestone-digest.js` / `api/server_recent_milestones.php`)
- Established / Double Digit Merchant chart groups

Until then, Activity keeps legacy milestone surfaces ([`hub-ia-agreement.md`](hub-ia-agreement.md)).

---

## Relationships

| Surface | Relationship |
|---------|----------------|
| `ranked10.php` | Meta-leaderboard (“most milestones”); link into hub for per-key lore |
| `individual_milestones.php` | Personal garden; hub links “your milestones” when logged in via profile |
| `server2.php` | Remove trial **Milestone achievers** section when hub hosts DD Merchant + more |
| `server1.php` | Charts/digest migrate later; no Activity slim in stub slice |
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
