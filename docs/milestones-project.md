# Milestones project — status & phases

**Kick Off 2 ratings site · Jun 2026**

Single place to see **where the milestone feature is** in the pipeline.

---

## Current phase

| | |
|--|--|
| **Completed** | **Phase 1–4 v0** — catalog **112** keys; full rebuild + parity on local/work; **staging `kooldb`** (May 2026); UI (profile garden, `leaderboards/milestones.php`, hub stub); **rated play streaks** SCH-014 + REP-015; **`year_in_heaven`** + **`play_streak_100`** |
| **Next** | Post-game PHP on work/staging live (see [`ladder-ops-platform.md`](ladder-ops-platform.md)); hub Home [`milestones-hub-ia.md`](milestones-hub-ia.md); prod cutover when Steve ready |

**Catalog (112 keys):** [`milestones-README.md`](milestones-README.md) → [`milestones-catalog.md`](milestones-catalog.md) (generated). **Seed:** [`site/public_html/ops/data/milestones_definitions_seed.json`](../site/public_html/ops/data/milestones_definitions_seed.json). **Facilitation:** [`milestones-facilitation.md`](milestones-facilitation.md).

**Unlock row counts:** After full rebuild, expect **6615** rows in `player_milestones`. Timeline: [`coordination/replay-register.md`](coordination/replay-register.md) § Milestone unlock row counts.

---

## Phase map

| Phase | Name | Status | Primary docs |
|-------|------|--------|----------------|
| 0 | **Discovery** | Done | [`archive/milestones-system-discussion.md`](archive/milestones-system-discussion.md) |
| 1 | **Idea creation** | Done | [`archive/milestones-ideas-catalog.md`](archive/milestones-ideas-catalog.md) |
| 2 | **Definition** | Done (May 2026) | [`milestones-product-spec.md`](milestones-product-spec.md), seed + generated catalog |
| 3 | **Data contract + rebuild** | Done (local/work/staging) | [`milestones-facilitation.md`](milestones-facilitation.md), [`website-data-contract.md`](website-data-contract.md) |
| 4 | **Build & ship** | **v0 + hub stub** | Profile garden, `leaderboards/milestones.php`, hub `milestones.php` stub; full hub WIP |

---

## Technical baseline

| Item | State |
|------|--------|
| `milestone_definitions` | SCH-011; **112** rows from seed |
| `player_milestones` | **112/112** keys in rebuild; parity scripts on local/work |
| Post-game (forward) | PHP ops P6 — [`post-game-php-development.md`](post-game-php-development.md); prod C++ until cutover |
| Profiles / leaderboards | **v0** — profile pill + garden, `leaderboards/milestones.php`, hub stub |

---

## Doc index

| Doc | Role |
|-----|------|
| [`milestones-README.md`](milestones-README.md) | **Start here** — workflows & doc map |
| [`milestones-catalog.md`](milestones-catalog.md) | **Generated** per-key catalog |
| **This file** | Phase status |
| [`milestones-facilitation.md`](milestones-facilitation.md) | Implementation families & waves |
| [`milestones-product-spec.md`](milestones-product-spec.md) | Tier bands, garden UI, leaderboard |
| [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md) | Unlock link/event UI spec |
| [`milestones-hub-ia.md`](milestones-hub-ia.md) | Hub tab IA (WIP) |
| [`archive/`](archive/) | Brainstorm + staging cutover history |

**Historical staging runbooks:** [`coordination/milestones-staging-cutover-packet.md`](coordination/milestones-staging-cutover-packet.md) (redirect → archive).

---

*Phase 2 closed May 2026. Staging DB May 2026. Ops post-game Jun 2026.*
