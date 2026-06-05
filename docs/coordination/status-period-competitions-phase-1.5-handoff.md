# Handoff — Status Leagues Phase 1.5

**Status:** **Active** (Jun 2026). **Paste into a new agent chat** or say: “Read `docs/coordination/status-period-competitions-phase-1.5-handoff.md` and implement Phase 1.5 per the checklist.”

**Note:** **Daily games list** under Day tab shipped Jun 2026 — skip that item; pick remaining 1.5 backlog from [`status-period-competitions-wip.md`](../status-period-competitions-wip.md).

---

## Bootstrap (agent: read first)

1. [`PROJECT_MEMORY.md`](../../PROJECT_MEMORY.md)
2. [`AGENTS.md`](../../AGENTS.md)
3. [`docs/PROJECT_MAP.md`](../PROJECT_MAP.md)
4. **This file** + [`docs/status-period-competitions-wip.md`](../status-period-competitions-wip.md) — Phase 1.5 checklist
5. [`docs/STATUS_PAGE_DATA.md`](../STATUS_PAGE_DATA.md) — Status hub context
6. [`docs/design-direction.md`](../design-direction.md) — if UI work

**Do not re-implement Phase 1 navigation** unless fixing a regression. Phase 1 is done (single table slot, cache, prewarm, lock-step floor to `first_rated_day`, Daily games list).

---

## What Phase 1 already shipped

- `status.php` **Leagues** block: paired **Activity** + **Points** tables
- Period tabs Day · Week · Month · Year; ←/→; archive pickers; day calendar (Flatpickr)
- Lock-step keys across periods; clamp day/week/month to **first rated game** (`data-first-rated-day`)
- One DOM table area + in-memory cache; optional prewarm (`data-competition-prewarm="1"`)
- **Daily tab:** games-this-day list below league tables (`api/status_period_day_games.php`)
- Key files:
  - `site/public_html/includes/status_period_competitions_section.php`
  - `site/public_html/js/status-period-competitions.js`
  - `site/public_html/api/status_period_points_league.php`
  - `site/public_html/api/server_period_activity_leaderboard.php`
  - `site/public_html/includes/status_queries.php` → `period_competitions`

---

## Your mission: Phase 1.5

Work through the **Phase 1.5 backlog** in [`docs/status-period-competitions-wip.md`](../status-period-competitions-wip.md) (items **not** marked shipped).

Typical remaining items: day activity one-liner, empty table copy, Monday editorial strip, archive UX polish, picker chrome.

---

## Performance habit

Do not add live full-history aggregation on `ratedresults` for every Status page load. Prefer narrow day query or precomputed row set. Document in contract + Part B of [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) if new stored truth.

---

## Done when

- [ ] Phase 1.5 checklist items Dagh agreed to are implemented or explicitly deferred with a wip doc note
- [ ] Part A of `UPDATE_DOCS.md` in the same turn as shipping code
- [ ] No regression to Phase 1 arrow/tab/prewarm / Daily games list

---

## Authority

Dagh’s message in chat > [`PROJECT_BRIEF.md`](../../PROJECT_BRIEF.md) > [`docs/design-direction.md`](../design-direction.md) > MEMORY
