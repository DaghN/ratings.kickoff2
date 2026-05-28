# Leagues integration — phase tracker

**Kick Off 2 ratings site · May 2026**

Extends Status **Leagues** (Phase 1 shipped) into **persistent awards**, career leaderboards, milestones, and profile surfaces.

**Rules authority:** [`leagues-rules-spec.md`](leagues-rules-spec.md)

---

## Current phase

| | |
|--|--|
| **Done** | Status Leagues UI · rules spec · SCH-009 local · shared ranker (`league_standings.php`) · REP-012 local (~22k awards) · PER-003 script (`finalize_league_periods.php`) · Status/API sort uses tie-breaks |
| **Next** | Track 2 — optional read medals from `player_league_award`; staging Steve SCH-009 + REP-012 |
| **Done (v1)** | League honours wing — `ranked9.php` ([`leagues-career-leaderboard-proposal.md`](leagues-career-leaderboard-proposal.md)) |
| **Done (slice DB)** | `player_league_slice_totals` (SCH-010, REP-013) — profile helper `k2_league_player_slice_totals()` |
| **Next** | Apply SCH-010 local + `--rebuild-aggregates` · staging · profile league block · prod PER-003 |

---

## Phase map

| Track | Name | Status | Deliverables |
|-------|------|--------|----------------|
| 0 | **Rules & identity** | **Done** | [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| 1 | **Stored truth** | **Done locally** | SCH-009, contract §, REP-012, `finalize_league_periods.php`, `run_league_awards_rebuild.ps1` |
| 2 | **Status integration** | **Partial** | Sort via `league_standings.php`; medals still rendered top-3 by rank (matches awards when closed) |
| 3 | **Career leaderboard** | **v1 shipped** | `ranked9.php` — playertable + `player_league_totals` |
| 4 | **Player universe** | Not started | Profile slot TBD — API from `player_league_award` only |
| 5 | **Milestones merge** | Not started | Phase 2–4 of [`milestones-project.md`](milestones-project.md) using awards + totals |
| 6 | **Prod** | Not started | Steve: schema, REP, cron PER-003 |

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
