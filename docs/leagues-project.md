# Leagues integration — phase tracker

**Kick Off 2 ratings site · May 2026**

Extends Status **Leagues** (Phase 1 shipped) into **persistent awards**, career leaderboards, milestones, and profile surfaces.

**Rules authority:** [`leagues-rules-spec.md`](leagues-rules-spec.md)

---

## Current phase

| | |
|--|--|
| **Done** | Status Leagues UI · rules spec · shared ranker · SCH-009/010 + REP-012/013 **local + staging** · League honours `ranked9.php` · slice helper for profile |
| **Next** | Profile league block · prod schema/REP at cutover · optional daily finalize (PER-003) later |
| **Not started** | Milestones merge (Track 5) · prod cron |

---

## Phase map

| Track | Name | Status | Deliverables |
|-------|------|--------|----------------|
| 0 | **Rules & identity** | **Done** | [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| 1 | **Stored truth** | **Done local + staging** | SCH-009/010, REP-012/013; ops `run_finalize_league.php` (staging one-shot was `run_league_awards_rebuild.php`) |
| 2 | **Status integration** | **Partial** | Sort via `league_standings.php`; medals top-3 by rank (matches awards when closed) |
| 3 | **Career leaderboard** | **v1 shipped** | `ranked9.php` — `player_league_totals` + `player_league_slice_totals` |
| 4 | **Player universe** | Not started | Profile slot TBD — `k2_league_player_slice_totals()` ready |
| 5 | **Milestones merge** | Not started | [`milestones-project.md`](milestones-project.md) |
| 6 | **Prod** | Not started | Schema + REP; no PER-003 unless requested |

**Parallel:** Status Phase 1.5 polish — **not** blocking Track 1; day games list deferred.

---

## Deferred product slots

| Slot | Notes |
|------|--------|
| Profile league story / eye candy | Needs layout rethink; only plan a block in profile feast when Track 4 starts |
| Milestones tab IA | [`milestones-project.md`](milestones-project.md) Phase 2 |

---

## Doc index

| Doc | Role |
|-----|------|
| **This file** | Where we are in the pipeline |
| [`leagues-rules-spec.md`](leagues-rules-spec.md) | Sort, finality, URLs, timestamps |
| [`status-period-competitions-wip.md`](status-period-competitions-wip.md) | Status Leagues UI |
| [`website-data-contract.md`](website-data-contract.md) | Table rebuild + post-game + periodic |
| [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) | §IIIb–IIIc league milestone keys |

---

*Track 0 closed May 2026.*
