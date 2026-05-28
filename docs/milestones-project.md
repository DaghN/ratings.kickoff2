# Milestones project — status & phases

**Kick Off 2 ratings site · May 2026**

Single place to see **where the milestone feature is** in the pipeline. Implementation has **not** started beyond the existing two DB keys and Activity digest/charts.

---

## Current phase

| | |
|--|--|
| **Completed** | **Phase 1 — Idea creation** (discovery + brainstorm + pass 1 curation) |
| **In progress** | **Phase 2 — Definition** — **curated tier list** in [`milestones-tier-curated.md`](milestones-tier-curated.md); naming TBD on some keys; implementation not started |
| **Not started** | Phase 3+ — schema/catalog, rebuild rules, UI, hub tab, leaderboard |

**Working set:** [`milestones-tier-curated.md`](milestones-tier-curated.md) — **decided for now** (May 2026): four bands, ~108 milestones. The full [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) remains the rule reference plus discarded ideas.

---

## Phase map

| Phase | Name | Status | Primary docs |
|-------|------|--------|----------------|
| 0 | **Discovery** | Done | [`milestones-system-discussion.md`](milestones-system-discussion.md) — DB reality, naming (Milestones vs achievements), tone, gaps |
| 1 | **Idea creation** | **Done (May 2026)** | [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) — wide brainstorm + pass 1 curation |
| 2 | **Definition** | **Next** | [`milestones-product-spec.md`](milestones-product-spec.md) — **plan**: four tier bands, garden UI, story, leaderboard tie-break; Key ~15–20; naming + league rules still TBD |
| 3 | **Data contract** | Not started | Extend [`website-data-contract.md`](website-data-contract.md), `milestone_definitions`, rebuild + post-game |
| 4 | **Build & ship** | Not started | APIs, hub tab, achiever lists, profile count, meta-leaderboard |

---

## Decisions on record

### Pass 1 (unchanged unless superseded)

- User-facing term: **Milestones**.
- **Dedicated hub tab** (not Activity-only); **milestone count on profile**; **most-milestones leaderboard**.
- **~115+ want** candidates in catalog; **4 maybe**; rest **discard** (kept for reference).
- Profile **layout not locked** — integrate when profile is rethought.
- Leagues: **2×8** medal/winner milestones + **10/50/100/500** career league-win totals; overlap OK until consolidated.

### Tier bands & presentation (May 2026 — **plan**, [`milestones-product-spec.md`](milestones-product-spec.md))

- **Four bands**, every milestone exactly one: **Aspirational** (`pitch`), **Veteran** (`chrome`), **Key** (`amber`), **Legendary** (`holo`).
- **Key** = amber tier = **~15–20** “completeness palette” milestones (same set as achiever-list prominence); e.g. first Double Digit Merchant, Established — not a separate “featured vs key” split.
- Hub: **tier-first sections**; cards **dim when locked**, **tier color + glow** when unlocked (“garden”).
- **Milestone story:** chronological unlocks with tier colors.
- **Leaderboard:** sort by total unlocks; tie-break pitch → chrome → amber → holo counts. Key completion % **TBD**.
- No off-palette “obscure” lane — all milestones use the four colors.

---

## Technical baseline (unchanged)

| Item | State |
|------|--------|
| `player_milestones` table | Live local + staging |
| Keys in DB today | `established_20`, `dd_merchant_10` only |
| Activity UI | Recent milestones digest + Established / DD chart groups |
| Profiles | Not wired to milestone table |

---

## Doc index

| Doc | Role |
|-----|------|
| **This file** | Phase status — read first for “where are we?” |
| [`milestones-product-spec.md`](milestones-product-spec.md) | **Plan** — tier bands, garden, story, leaderboard (not locked) |
| [`milestones-system-discussion.md`](milestones-system-discussion.md) | Discussion paper (discovery, naming, shape) |
| [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) | Pass 1 catalog — **draft**, not signed off |

---

## Suggested Phase 2 entry tasks

1. Add **`tier_band`** to catalog rows (aspirational / dedicated / accomplished / legendary) — use [`milestones-want-maybe-by-theme.md`](milestones-want-maybe-by-theme.md) for grouped review (**Unlock** / **%** columns = read-only probe, May 2026 local DB).
2. **Select Keystones (~15–20)** from want pool (amber accomplished band + achiever lists — one list).
3. Naming pass (TBD display names in catalog §XVIII).
4. League rules hardening (podium, win totals, overlap).
5. Hub wireframe from product spec §4–5 (four sections, garden states, story).

---

*Idea creation phase closed May 2026. Tier plan added May 2026. Next: catalog pass 2 + Key pick from product spec.*
