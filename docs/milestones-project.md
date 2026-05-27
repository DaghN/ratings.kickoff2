# Milestones project — status & phases

**Kick Off 2 ratings site · May 2026**

Single place to see **where the milestone feature is** in the pipeline. Implementation has **not** started beyond the existing two DB keys and Activity digest/charts.

---

## Current phase

| | |
|--|--|
| **Completed** | **Phase 1 — Idea creation** (discovery + brainstorm + pass 1 curation) |
| **Next** | **Phase 2 — Definition** (naming, Key-10 selection, rules hardening, tab/profile IA, product spec) |
| **Not started** | Phase 3+ — schema/catalog, rebuild rules, UI, hub tab, leaderboard |

**Important:** The milestone **list is not finalized**. [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) is the output of a first brainstorm and one curation pass (`want` / `maybe` / `discard`). Pass 2+ may add, cut, or rename freely.

---

## Phase map

| Phase | Name | Status | Primary docs |
|-------|------|--------|----------------|
| 0 | **Discovery** | Done | [`milestones-system-discussion.md`](milestones-system-discussion.md) — DB reality, naming (Milestones vs achievements), tone, gaps |
| 1 | **Idea creation** | **Done (May 2026)** | [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) — wide brainstorm + pass 1 curation |
| 2 | **Definition** | **Next** | *(to create)* product spec: Key milestones (~10), display names, league rules, Milestones tab IA, profile rethink hooks |
| 3 | **Data contract** | Not started | Extend [`website-data-contract.md`](website-data-contract.md), `milestone_definitions`, rebuild + post-game |
| 4 | **Build & ship** | Not started | APIs, hub tab, achiever lists, profile count, meta-leaderboard |

---

## Decisions already on record (pass 1)

- User-facing term: **Milestones**; elevated subset: **Key milestones** (~10 TBD).
- **Dedicated hub tab** (not Activity-only); **milestone count on profile**; **most-milestones leaderboard**.
- **~115+ want** candidates in catalog; **4 maybe**; rest **discard** (kept for reference).
- Profile **layout not locked** — integrate when profile is rethought.
- Leagues: **2×8** medal/winner milestones + **10/50/100/500** career league-win totals; overlap OK until consolidated.

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
| [`milestones-system-discussion.md`](milestones-system-discussion.md) | Discussion paper (discovery, naming, shape) |
| [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) | Pass 1 catalog — **draft**, not signed off |

---

## Suggested Phase 2 entry tasks

1. Naming pass (TBD display names in catalog §XVIII).
2. Choose **Key milestones (~10)** for achiever-list prominence.
3. Sketch **Milestones tab** IA (sections, not pixel design).
4. Write **`docs/milestones-product-spec.md`** (or equivalent) when ready to implement.
5. Second curation pass if desired before locking rules.

---

*Idea creation phase closed May 2026. Next chat: start from this file + catalog.*
