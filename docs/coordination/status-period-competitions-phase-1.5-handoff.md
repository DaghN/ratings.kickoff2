# Handoff — Status Leagues Phase 1.5

**Paste this entire file into a new Cursor Agent chat** (or say: “Read `docs/coordination/status-period-competitions-phase-1.5-handoff.md` and implement Phase 1.5 per the checklist.”)

---

## Bootstrap (agent: read first)

1. [`PROJECT_MEMORY.md`](../../PROJECT_MEMORY.md)
2. [`AGENTS.md`](../../AGENTS.md)
3. [`docs/PROJECT_MAP.md`](../PROJECT_MAP.md)
4. **This file** + [`docs/status-period-competitions-wip.md`](../status-period-competitions-wip.md) — Phase 1.5 checklist
5. [`docs/STATUS_PAGE_DATA.md`](../STATUS_PAGE_DATA.md) — Status hub context
6. [`docs/design-direction.md`](../design-direction.md) — if UI work

**Do not re-implement Phase 1 navigation** unless fixing a regression. Phase 1 is done (single table slot, cache, prewarm, lock-step floor to `first_rated_day`).

---

## What Phase 1 already shipped

- `status.php` **Leagues** block: paired **Activity** + **Points** tables
- Period tabs Day · Week · Month · Year; ←/→; archive pickers; day calendar (Flatpickr)
- Lock-step keys across periods; clamp day/week/month to **first rated game** (`data-first-rated-day`)
- One DOM table area + in-memory cache; optional prewarm of five “next clicks” (`data-competition-prewarm="1"`)
- Key files:
  - `site/public_html/includes/status_period_competitions_section.php`
  - `site/public_html/js/status-period-competitions.js`
  - `site/public_html/api/status_period_points_league.php`
  - `site/public_html/api/server_period_activity_leaderboard.php`
  - `site/public_html/includes/status_queries.php` → `period_competitions`

---

## Your mission: Phase 1.5

Work through the **Phase 1.5 backlog** in [`docs/status-period-competitions-wip.md`](../status-period-competitions-wip.md).

**Dagh priority to include in this slice:** item **6 — Day games list under tables** (rated games for the selected calendar day, below the two league tables when Day tab is active).

Other 1.5 items (pick what fits one session or ask Dagh to order):

1. Day activity one-liner (hub/tease copy)
2. Empty table copy (point to ← / last week)
3. Monday editorial strip (`data-k2-editorial`)
4. Archive always visible vs `<details>`
5. Points pickers chrome without archive

---

## Day games list — implementation hints

| Topic | Guidance |
|--------|----------|
| **When** | Only when active period tab is `day` and `keys.day` is set |
| **Data** | Rated games on that UTC/calendar day from `ratedresults` (indexed `Date` / `idA` / `idB`) **or** a small API + stored truth if the scan is too heavy — follow [`website-data-contract.md`](../website-data-contract.md) habit |
| **UI** | Compact list under existing `.k2-status-period-competitions__views`; match `theme.css` Status table/link patterns |
| **Nav** | Re-fetch or swap list when day changes (←/→, calendar, lock-step); respect same cache/prewarm patterns where sensible |
| **Empty** | Hide or short message when no games |

---

## Performance habit

Do not add live full-history aggregation on `ratedresults` for every Status page load. Prefer narrow day query or precomputed row set. Document in contract + Part B of [`UPDATE_DOCS.md`](../UPDATE_DOCS.md) if new stored truth.

---

## Done when

- [ ] Phase 1.5 checklist items Dagh agreed to are implemented or explicitly deferred with a wip doc note
- [ ] Day games list works on local/staging for several days (0 games, busy day, stepping days)
- [ ] Part A of `UPDATE_DOCS.md` in the same turn as shipping code
- [ ] No regression to Phase 1 arrow/tab/prewarm behaviour

---

## Test plan (quick)

1. Hard refresh `status.php` → Week default → arrows still smooth
2. **Day** tab → list appears under tables; matches selected day
3. ←/→ on Day → list updates with leagues
4. Switch to Week → list hidden (or not day list)
5. Year 2017 → month/day lock-step still sane (first-rated floor)

---

## Authority

Dagh’s message in chat > [`PROJECT_BRIEF.md`](../../PROJECT_BRIEF.md) > [`docs/design-direction.md`](../design-direction.md) > MEMORY
