# K2 mobile / smartphone policy

**Status:** Locked intent (Jul 2026). Read before mobile audits, responsive refactors, or "fix smartphone" slices.
**Authority:** Product taste in [`PROJECT_BRIEF.md`](../PROJECT_BRIEF.md) § "What we're *not* optimising". Visual contract: [`design-direction.md`](design-direction.md). Dagh's latest chat wins on scope.

**For agents:** dense tables on phone are **working as designed**, not backlog or catastrophic deficiency. Do **not** propose card reflow, column wrapping, or forced auto-zoom on leaderboards unless Dagh explicitly asks.

---

## Product stance

Kick Off 2 ratings is a **rich content platform** for a niche retro community (historically stats-first; still dense where comparison is the job) — not a consumer funnel app. On ladder and table pages, the primary artifact is **comparability across rows and columns** (leaderboards, game lists, standings, charts). PC remains the main workflow; smartphone use is **usable when convenient**.

**Deliberate compromise — read-first, pinch-second:**

1. **Overview at phone scale** — see table/chart structure; scroll vertically (phones are strong here).
2. **Swipe wide tables horizontally** — finger scroll inside `.k2-table-wrap` on phone is **desired**; easy and natural on touch.
3. **Pinch to inspect** — user-controlled zoom when a number or cell needs detail; the site does not guess which column matters.
4. **Tables stay tables** — no card stacks that hide cross-row comparison; no auto-zoom that locks the viewport to one column.

This is intentional. Generic "mobile-friendly" rubrics (card UI, thumb-first one-handed flows, marketing-site patterns) do **not** apply as default goals.

---

## What is already shipped (do not regress)

| Area | Pattern | Where |
|------|---------|--------|
| Wide tables | **Horizontal finger-scroll inside** `.k2-table-wrap` (`overflow: auto`) — intentional on phone, not a defect. Wrap also prevents the **whole page** from shrinking or scrolling sideways to fit a wide table | `theme.css` `.k2-table-wrap` |
| Charts on touch | `touch-action: pan-y pinch-zoom` on chart panels/canvases — vertical scroll + pinch, not pan-x lock | `theme.css` `@media (pointer: coarse)` |
| Coarse pointers | Chart tooltips often **off** on touch (hover-only Chart.js path) | `chart-theme.js`, Activity/Amiga activity policies |
| Multi-column layouts | Stack where it cuts scroll without losing table density — e.g. Status leagues: Activity first, then Points | `status-period-competitions.md` |
| Table density | Calm-stats, sortable stacks, entity links — same data model on all viewports | [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) |

**Do not** add `user-scalable=no`, global `touch-action: none`, or layouts that force the browser to shrink-zoom the entire document to fit a wide table.

---

## Out of scope (unless Dagh asks)

- Reflowing sortable leaderboards into per-row cards
- Wrapping wide table columns or hiding columns behind "mobile breakpoints"
- Forced initial zoom on table regions
- "Mobile parity identical to desktop" as a success metric
- Replatforming or a separate mobile site

---

## Known gaps — selective improvements welcome

These are **real follow-ups**, not proof the current model failed. Fix in small slices when asked; do **not** bundle with table reflow.

| Gap | Notes |
|-----|--------|
| **Chart tap-to-tooltip** | Desktop: Chart.js hover tooltips. Touch: often disabled on coarse pointers. **Tap-to-show tooltip on graphs** was deferred (technical problem unsolved that day) — still a valid future slice. |
| **Hover + click surfaces** | Some surfaces show help or glance on **hover** and navigate on **click** (player glance, helped table cells). **Tap-to-tooltip / double-tap-to-click-through** is not consistent site-wide — future polish, not a reason to card-ify tables. See [`k2-tooltip-policy.md`](k2-tooltip-policy.md) § Touch / coarse pointer. |
| **Chrome touch targets** | Hub nav, wing tabs, filters, listboxes, tint picker — may deserve larger hit areas or spacing on coarse pointers **without** changing table layout. |
| **Accessibility certification** | Basic readability matters; full WCAG/mobile-first certification is not a day-one goal ([`PROJECT_BRIEF.md`](../PROJECT_BRIEF.md)). |

---

## How to audit this site on mobile

Ask:

- Can a motivated reader **navigate**, **find data**, **swipe wide tables sideways** inside the wrap, and **pinch into** what they care about?
- Does the **whole page** stay stable (no browser shrink-zoom, no document-level horizontal scroll) while the table itself scrolls inside `.k2-table-wrap`?
- Are **nav/control** taps reasonably hit-able?

Do **not** ask by default:

- "Should this leaderboard become cards?"
- "Is lack of responsive column hiding a bug?"
- "Should we eliminate horizontal scroll on tables?" — **no**; in-wrap swipe is the intended phone interaction for wide tables.

Flag **regressions** (broken pinch, whole-page shrink-zoom or sideways page scroll, table wrap that blocks finger pan, hover-only critical info with no tap path). Flag **chrome gaps** from the table above when relevant. Treat **dense tables + in-wrap horizontal swipe + pinch** as **pass**, not debt.

---

## Related docs

- [`PROJECT_BRIEF.md`](../PROJECT_BRIEF.md) — "usable when convenient, without forcing compromises on dense desktop tables"
- [`design-direction.md`](design-direction.md) — visual contract; § Mobile and smartphones (summary)
- [`k2-tooltip-policy.md`](k2-tooltip-policy.md) — hover help + touch gaps
- [`nav-spacing-policy.md`](nav-spacing-policy.md) — chrome spacing (future touch target work may overlap)

---

*Last updated: Jul 2026.*